<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\DataTables\Params;

/**
 * Server-side data for a DataTables table (paging, search and sorting done
 * in SQL, so it stays fast with big tables).
 *
 *   $this->datatable
 *       ->from(function ($db) { $db->from('users u'); })
 *       ->select('u.id, u.name, u.email, u.role')
 *       ->column('name',  'u.name')                      // searchable + sortable
 *       ->column('email', 'u.email')
 *       ->column('actions', null)                        // computed, neither
 *       ->respond(function ($row) {                      // build one output row
 *           return ['name' => esc($row['name']), ...];
 *       });
 *
 * Columns are declared in the same order as the <th>s (see datatable_table()
 * in datatable_helper.php), because DataTables refers to them by position.
 * Search and sort columns should be text or numbers (they are compared with LIKE).
 * The cells you return are HTML: escape anything that came from users.
 *
 * @property CI_DB_query_builder $db
 * @property CI_Input $input
 * @property CI_Output $output
 */
class Datatable
{
    protected $CI;

    /** @var callable|null */
    protected $source;

    /** @var string */
    protected $select = '*';

    /** @var array<int, array{name: string, sql: string|null, search: bool, order: bool}> */
    protected $columns = [];

    /** @var array<int, string> fallback ORDER BY, e.g. ['u.id ASC'] */
    protected $defaultOrder = [];

    /** @var int rows counted at most, see count_cap() */
    protected $countCap;

    public function __construct()
    {
        $this->CI = &get_instance();
        $cap = (int) (getenv('DATATABLE_COUNT_CAP') ?: 100000);
        $this->countCap = max(100, min($cap, 10000000));
    }

    /**
     * Counting every row of a table with millions of rows on each page turn is
     * what makes list pages slow. Counts stop at this many rows: past it the
     * table says "100,000+", and paging stops there too (an OFFSET that deep is
     * slow anyway; searching narrows it down). Set DATATABLE_COUNT_CAP in .env.
     */
    public function count_cap()
    {
        return $this->countCap;
    }

    /**
     * Resets state so the library can serve several tables in one request.
     */
    public function reset()
    {
        $this->source = null;
        $this->select = '*';
        $this->columns = [];
        $this->defaultOrder = [];

        return $this;
    }

    /**
     * @param callable $source receives the query builder and adds FROM/JOIN/WHERE
     *                         (it runs once per query, so it must not echo or have side effects)
     */
    public function from(callable $source)
    {
        $this->source = $source;

        return $this;
    }

    public function select($select)
    {
        $this->select = $select;

        return $this;
    }

    /**
     * @param string      $name   key in the row array handed to respond()'s callback
     * @param string|null $sql    column or expression used for search and sort; null = neither
     * @param bool        $search include in the search box
     * @param bool        $order  allow sorting by this column
     */
    public function column($name, $sql, $search = true, $order = true)
    {
        $this->columns[] = [
            'name' => $name,
            'sql' => $sql,
            'search' => $sql !== null && $search,
            'order' => $sql !== null && $order,
        ];

        return $this;
    }

    /**
     * @param array<int, string> $orderBy used when the table has no sort, and as a tie-break
     */
    public function default_order(array $orderBy)
    {
        $this->defaultOrder = $orderBy;

        return $this;
    }

    /**
     * Runs the queries and returns DataTables' JSON structure.
     *
     * @param callable      $format  row array => associative array of cell HTML keyed by column name
     * @param array|null    $request defaults to $_GET
     * @param callable|null $prepare called once with the page's rows before formatting
     *                               (look up labels for all of them in one query)
     *
     * @return array<string, mixed>
     */
    public function build(callable $format, $request = null, ?callable $prepare = null)
    {
        $request = $request ?? $this->CI->input->get();
        $request = is_array($request) ? $request : [];

        $orderable = [];
        foreach ($this->columns as $i => $c) {
            $orderable[$i] = $c['order'];
        }
        $params = Params::fromRequest($request, $orderable);
        $params->start = min($params->start, $this->countCap);

        $db = $this->db();

        $total = $this->count($db, []);
        $filtered = $params->terms() === [] ? $total : $this->count($db, $params->terms());
        $capped = $total >= $this->countCap || $filtered >= $this->countCap;

        $this->apply_source($db);
        $db->select($this->select, false);
        $this->apply_search($db, $params->terms());
        foreach ($params->order as $o) {
            $db->order_by($this->columns[$o['column']]['sql'], $o['dir'] === 'desc' ? 'DESC' : 'ASC', false);
        }
        foreach ($this->defaultOrder as $clause) {
            $db->order_by($clause, '', false);
        }
        $rows = $db->limit($params->length, $params->start)->get()->result_array();

        if ($prepare !== null) {
            $prepare($rows);
        }

        $data = [];
        foreach ($rows as $row) {
            $cells = $format($row);
            $out = [];
            foreach ($this->columns as $c) {
                $out[] = (string) ($cells[$c['name']] ?? '');
            }
            $data[] = $out;
        }

        return [
            'draw' => $params->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'capped' => $capped,
            'data' => $data,
        ];
    }

    /**
     * How many rows the search matches, counted up to $upTo (so a huge table
     * is never counted in full). Used to refuse an export that is too big
     * before any of it is sent.
     *
     * @param array<int, string> $terms
     */
    public function count_matches(array $terms, $upTo)
    {
        $db = $this->db();
        $this->apply_source($db);
        $this->apply_search($db, $terms);
        $db->select('1', false)->limit(max(1, (int) $upTo));
        $inner = $db->get_compiled_select();

        $row = $db->query('SELECT COUNT(*) AS c FROM ('.$inner.') counted')->row_array();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Walks every row the current search matches, in chunks, without loading
     * them all: each chunk is read with "WHERE key > last ORDER BY key LIMIT n"
     * (keyset paging), so the cost of a chunk does not grow with its position
     * the way OFFSET does. Used by exports. Stops after $max rows.
     *
     * @param array<int, string> $terms  the search words (see Params::terms())
     * @param string             $keySql sortable unique column in the query, e.g. 't.id'
     * @param string             $keyAs  its name in the selected rows, e.g. 'id'
     * @param callable           $each   receives an array of rows per chunk; return false to stop
     *
     * @return int rows delivered
     */
    public function each_chunk(array $terms, $keySql, $keyAs, callable $each, $chunk = 1000, $max = PHP_INT_MAX)
    {
        $db = $this->db();
        $last = 0;
        $done = 0;

        while ($done < $max) {
            $this->apply_source($db);
            $db->select($this->select, false);
            $this->apply_search($db, $terms);
            $db->where($keySql.' >', $last, false);
            $db->order_by($keySql, 'ASC', false);
            $rows = $db->limit(min($chunk, $max - $done))->get()->result_array();

            if ($rows === []) {
                break;
            }

            $done += count($rows);
            $last = (int) end($rows)[$keyAs];

            if ($each($rows) === false || count($rows) < $chunk) {
                break;
            }
        }

        return $done;
    }

    /**
     * build() + send it as JSON.
     */
    public function respond(callable $format, ?callable $prepare = null)
    {
        $json = json_encode(
            $this->build($format, null, $prepare),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        $this->CI->output
            ->set_status_header(200)
            ->set_header('Cache-Control: no-store')
            ->set_content_type('application/json', 'utf-8')
            ->set_output($json);
    }

    /**
     * The shared connection, opened on first use (this library is autoloaded
     * on pages that never touch the database).
     */
    protected function db()
    {
        if (!isset($this->CI->db) || !is_object($this->CI->db) || empty($this->CI->db->conn_id)) {
            $this->CI->load->database();
        }

        return $this->CI->db;
    }

    /**
     * @param array<int, string> $terms
     */
    protected function count($db, array $terms)
    {
        $this->apply_source($db);
        $this->apply_search($db, $terms);
        $db->select('1', false)->limit($this->countCap);
        $inner = $db->get_compiled_select();

        $row = $db->query('SELECT COUNT(*) AS c FROM ('.$inner.') counted')->row_array();

        return (int) ($row['c'] ?? 0);
    }

    protected function apply_source($db)
    {
        if ($this->source === null) {
            throw new LogicException('Datatable::from() was not called.');
        }
        ($this->source)($db);
    }

    /**
     * Every term must appear in at least one searchable column.
     *
     * @param array<int, string> $terms
     */
    protected function apply_search($db, array $terms)
    {
        $searchable = array_values(array_filter($this->columns, static function ($c) {
            return $c['search'];
        }));

        if ($terms === [] || $searchable === []) {
            return;
        }

        foreach ($terms as $term) {
            $like = "'%".$db->escape_like_str($term)."%' ESCAPE '!'";
            $parts = [];
            foreach ($searchable as $c) {
                $parts[] = $c['sql'].' LIKE '.$like;
            }
            $db->where('('.implode(' OR ', $parts).')', null, false);
        }
    }
}
