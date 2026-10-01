<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Crud\FieldTypes;
use App\Crud\Uploader;

/**
 * Everything the CRUD generator needs from the database: the stored module
 * definitions, creating/altering/dropping the module tables, and reading and
 * writing their records.
 *
 * Identifiers (table and column names) always come from definitions that
 * App\Crud\Definition validated against /^[a-z][a-z0-9_]*$/, and are escaped
 * again here; values always go through the query builder's bindings.
 *
 * @property CI_DB_query_builder $db
 * @property CI_DB_forge $dbforge
 */
class Crud_store
{
    protected $CI;

    /** @var array<int, array<string, mixed>>|null */
    protected $modules;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    // ------------------------------------------------------------------
    // Module definitions
    // ------------------------------------------------------------------

    /**
     * Every module, oldest first. Empty when the table doesn't exist yet
     * (the migration hasn't run), so the rest of the app keeps working.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all()
    {
        if ($this->modules === null) {
            $result = $this->quietly(function ($db) {
                return $db->order_by('id', 'ASC')->get('crud_modules');
            });

            $this->modules = [];
            foreach ($result === false ? [] : $result->result_array() as $row) {
                $definition = json_decode((string) $row['definition'], true);
                if (!is_array($definition)) {
                    continue;
                }
                $this->modules[] = ['id' => (int) $row['id']] + $definition + ['slug' => $row['slug'], 'table' => $row['table_name']];
            }
        }

        return $this->modules;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find($slug)
    {
        foreach ($this->all() as $module) {
            if ($module['slug'] === (string) $slug) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Sidebar entries for the modules the signed-in user may open.
     *
     * @return array<int, array<string, string>>
     */
    public function menu_entries()
    {
        $items = [];
        foreach ($this->all() as $module) {
            if (can($module['slug'].'.view')) {
                $items[] = ['label' => $module['title'], 'icon' => $module['icon'], 'url' => 'admin/c/'.$module['slug']];
            }
        }

        return $items;
    }

    /**
     * Whether a table (and optionally a column) exists, for Definition.
     */
    public function schema_has($table, $column = null)
    {
        $db = $this->db();

        if (!$db->table_exists($table)) {
            return false;
        }

        return $column === null || $db->field_exists($column, $table);
    }

    /**
     * Keys that a new module cannot use: other modules and permission groups.
     *
     * @return array<int, string>
     */
    public function taken_slugs()
    {
        return array_merge(
            array_column($this->all(), 'slug'),
            array_keys($this->CI->rbac->registry()->groups())
        );
    }

    /**
     * Creates the table, stores the definition and grants the new
     * permissions to the admin role.
     *
     * @param array<string, mixed> $definition
     *
     * @return string|null an error message, null on success
     */
    public function create_module(array $definition)
    {
        $db = $this->db();
        $this->CI->load->dbforge();
        $dbforge = $this->CI->dbforge;

        $columns = [
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        ];
        foreach ($definition['fields'] as $field) {
            $columns[$field['name']] = FieldTypes::column($field);
        }
        $columns['created_at'] = ['type' => 'DATETIME', 'null' => true];
        $columns['updated_at'] = ['type' => 'DATETIME', 'null' => true];

        $dbforge->add_field($columns);
        $dbforge->add_key('id', true);
        if (!$dbforge->create_table($definition['table'], false)) {
            return 'The table could not be created.';
        }

        $now = date('Y-m-d H:i:s');
        $stored = $db->insert('crud_modules', [
            'slug' => $definition['slug'],
            'table_name' => $definition['table'],
            'title' => $definition['title'],
            'icon' => $definition['icon'],
            'definition' => json_encode($definition, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!$stored) {
            $dbforge->drop_table($definition['table'], true);

            return 'The module could not be saved.';
        }

        $this->modules = null;
        $this->grant_to_admin($definition['slug']);

        return null;
    }

    /**
     * Saves an edited definition and adds columns for any new fields.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $definition
     *
     * @return string|null
     */
    public function update_module(array $old, array $definition)
    {
        $db = $this->db();
        $known = array_column($old['fields'], 'name');
        $added = [];
        foreach ($definition['fields'] as $field) {
            if (!in_array($field['name'], $known, true)) {
                $added[$field['name']] = FieldTypes::column($field);
            }
        }

        if ($added !== []) {
            $this->CI->load->dbforge();
            foreach ($added as $name => $column) {
                if ($db->field_exists($name, $old['table'])) {
                    continue;
                }
                if (!$this->CI->dbforge->add_column($old['table'], [$name => $column])) {
                    return 'The column "'.$name.'" could not be added.';
                }
            }
        }

        $db->where('slug', $old['slug'])->update('crud_modules', [
            'title' => $definition['title'],
            'icon' => $definition['icon'],
            'definition' => json_encode($definition, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->modules = null;

        return null;
    }

    /**
     * Removes the module: its table (all records!), its uploaded files, its
     * permissions and its definition.
     *
     * @param array<string, mixed> $module
     */
    public function delete_module(array $module)
    {
        $db = $this->db();
        $this->CI->load->dbforge();

        $this->CI->dbforge->drop_table($module['table'], true);
        $this->uploader()->deleteAll($module['slug']);

        $db->where_in('permission', $this->permission_keys($module['slug']))->delete('role_permissions');
        $db->where('slug', $module['slug'])->delete('crud_modules');
        $this->modules = null;
    }

    /**
     * @return array<int, string>
     */
    public function permission_keys($slug)
    {
        return array_map(static function ($action) use ($slug) {
            return $slug.'.'.$action;
        }, ['view', 'create', 'edit', 'delete']);
    }

    // ------------------------------------------------------------------
    // Records
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $module
     *
     * @return array<string, mixed>|null
     */
    public function find_record(array $module, $id)
    {
        $row = $this->db()->get_where($module['table'], ['id' => (int) $id])->row_array();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $module
     * @param array<string, mixed> $values
     */
    public function insert_record(array $module, array $values)
    {
        $now = date('Y-m-d H:i:s');
        $db = $this->db();
        $db->insert($module['table'], $values + ['created_at' => $now, 'updated_at' => $now]);

        return (int) $db->insert_id();
    }

    /**
     * @param array<string, mixed> $module
     * @param array<string, mixed> $values
     */
    public function update_record(array $module, $id, array $values)
    {
        $this->db()->where('id', (int) $id)->update($module['table'], $values + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $module
     */
    public function delete_record(array $module, $id)
    {
        $this->db()->where('id', (int) $id)->delete($module['table']);
    }

    /**
     * Is $value already used in $column by a row other than $ignoreId?
     *
     * @param array<string, mixed> $module
     */
    public function value_taken(array $module, $column, $value, $ignoreId)
    {
        $db = $this->db();
        $db->where($column, $value);
        if ($ignoreId !== null) {
            $db->where('id !=', (int) $ignoreId);
        }

        return $db->count_all_results($module['table']) > 0;
    }

    // ------------------------------------------------------------------
    // Option lists
    // ------------------------------------------------------------------

    /**
     * Choices for a select/radio/multiselect field as value => label
     * (at most 500 rows for a table-backed list).
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, string>
     */
    public function choices(array $field)
    {
        $options = $field['options'];

        if ($options['mode'] === 'static') {
            return array_column($options['items'], 'label', 'value');
        }

        $result = $this->quietly(function ($db) use ($options) {
            return $db
                ->select($db->escape_identifiers($options['value_column']).' AS v, '.$db->escape_identifiers($options['label_column']).' AS l', false)
                ->order_by($options['label_column'], 'ASC')
                ->limit(500)
                ->get($options['table']);
        });

        $out = [];
        foreach ($result === false ? [] : $result->result_array() as $row) {
            $out[(string) $row['v']] = (string) ($row['l'] ?? '') !== '' ? (string) $row['l'] : (string) $row['v'];
        }

        return $out;
    }

    /**
     * Labels for specific stored values (the list shows labels, not keys).
     *
     * @param array<string, mixed> $field
     * @param array<int, string>   $values
     *
     * @return array<string, string> value => label
     */
    public function labels(array $field, array $values)
    {
        $values = array_values(array_unique(array_filter(array_map('strval', $values), 'strlen')));
        if ($values === []) {
            return [];
        }

        $options = $field['options'];
        if ($options['mode'] === 'static') {
            return array_intersect_key(array_column($options['items'], 'label', 'value'), array_flip($values));
        }

        $result = $this->quietly(function ($db) use ($options, $values) {
            return $db
                ->select($db->escape_identifiers($options['value_column']).' AS v, '.$db->escape_identifiers($options['label_column']).' AS l', false)
                ->where_in($options['value_column'], $values)
                ->get($options['table']);
        });

        $out = [];
        foreach ($result === false ? [] : $result->result_array() as $row) {
            $out[(string) $row['v']] = (string) ($row['l'] ?? '') !== '' ? (string) $row['l'] : (string) $row['v'];
        }

        return $out;
    }

    /**
     * Which of $values exist as options (used to validate submissions).
     *
     * @param array<string, mixed> $field
     * @param array<int, string>   $values
     *
     * @return array<int, string>
     */
    public function existing_values(array $field, array $values)
    {
        return array_keys($this->labels($field, $values));
    }

    /**
     * A column of the module table (aliased "t") escaped for raw SQL.
     */
    public function col($name)
    {
        return $this->db()->escape_identifiers('t.'.$name);
    }

    public function uploader()
    {
        return new Uploader(FCPATH.'uploads');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function grant_to_admin($slug)
    {
        $db = $this->db();
        $admin = $db->get_where('roles', ['slug' => 'admin'])->row();
        if (!$admin) {
            return;
        }

        foreach ($this->permission_keys($slug) as $key) {
            $exists = $db->where(['role_id' => $admin->id, 'permission' => $key])->count_all_results('role_permissions') > 0;
            if (!$exists) {
                $db->insert('role_permissions', ['role_id' => $admin->id, 'permission' => $key]);
            }
        }
    }

    protected function db()
    {
        if (!isset($this->CI->db) || !is_object($this->CI->db) || empty($this->CI->db->conn_id)) {
            $this->CI->load->database();
        }

        return $this->CI->db;
    }

    /**
     * Runs a read with db_debug off so a missing table yields `false`.
     *
     * @return mixed
     */
    protected function quietly(callable $query)
    {
        $db = $this->db();
        $debug = $db->db_debug;
        $db->db_debug = false;

        try {
            return $query($db);
        } finally {
            $db->db_debug = $debug;
        }
    }
}
