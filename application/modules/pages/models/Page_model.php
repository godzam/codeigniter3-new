<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Page\SectionTypes;

/**
 * Content pages built with Admin -> Pages; served at /page/{slug}.
 *
 * The layout lives in `sections` as JSON (App\Page\SectionTypes). Every query
 * is quiet: before the migration has run — or with no database at all — pages
 * simply do not exist and the routes fall through to 404.
 *
 * @property CI_DB_query_builder $db
 */
class Page_model extends CI_Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /** @var bool|null table existence, checked once per request */
    protected $available;

    /**
     * Every page, newest edit first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all()
    {
        if (!$this->available()) {
            return [];
        }

        $result = $this->quiet(function ($db) {
            return $db->order_by('updated_at DESC, id DESC')->get('pages');
        });

        $rows = $result === false || $result === null ? [] : $result->result_array();

        return array_map(static function ($row) {
            $row['sections'] = SectionTypes::toArray($row['sections'] ?? '');
            $row['section_count'] = count($row['sections']);

            return $row;
        }, $rows);
    }

    /**
     * @param int $id
     *
     * @return array<string, mixed>|null
     */
    public function find($id)
    {
        return $this->one('id', (int) $id, false);
    }

    /**
     * A page by its public slug; only published ones (or any, for a draft
     * preview by someone who may edit pages).
     *
     * @param string $slug
     * @param bool   $publishedOnly
     *
     * @return array<string, mixed>|null
     */
    public function find_by_slug($slug, $publishedOnly = true)
    {
        $row = $this->one('slug', (string) $slug, $publishedOnly);

        return $row;
    }

    /**
     * @param array<string, mixed> $data (slug, title, status, sections, meta_description)
     *
     * @return int|null new id
     */
    public function create(array $data)
    {
        if (!$this->available()) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $row = $this->clean($data) + ['created_at' => $now, 'updated_at' => $now];

        return $this->quiet(function ($db) use ($row) {
            return $db->insert('pages', $row) ? (int) $db->insert_id() : null;
        }) ?: null;
    }

    /**
     * @param int                  $id
     * @param array<string, mixed> $data
     */
    public function update($id, array $data)
    {
        if (!$this->available()) {
            return false;
        }

        $row = $this->clean($data) + ['updated_at' => date('Y-m-d H:i:s')];

        return (bool) $this->quiet(function ($db) use ($id, $row) {
            return $db->where('id', (int) $id)->update('pages', $row);
        });
    }

    /**
     * @param int $id
     */
    public function delete($id)
    {
        if (!$this->available()) {
            return false;
        }

        return (bool) $this->quiet(function ($db) use ($id) {
            return $db->where('id', (int) $id)->delete('pages');
        });
    }

    /**
     * A slug that is free (used by the admin form when it makes one up).
     *
     * @param string      $wanted
     * @param int|null    $ignoreId
     *
     * @return string
     */
    public function free_slug($wanted, $ignoreId = null)
    {
        $slug = $this->slugify($wanted);
        if ($slug === '') {
            $slug = 'page';
        }
        if (!$this->available()) {
            return $slug;
        }

        $candidate = $slug;
        $i = 2;
        while ($this->slug_exists($candidate, $ignoreId)) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }

    /**
     * Whether another page already uses this slug.
     *
     * @param string   $slug
     * @param int|null $ignoreId
     */
    public function slug_taken($slug, $ignoreId = null)
    {
        return $this->slug_exists($slug, $ignoreId);
    }

    public static function slugify($text)
    {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return mb_substr($text, 0, 150);
    }

    public function available()
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (!isset($this->db) || !is_object($this->db) || empty($this->db->conn_id)) {
            $file = is_file(APPPATH.'config/'.ENVIRONMENT.'/database.php') || is_file(APPPATH.'config/database.php');
            if (!$file) {
                return $this->available = false;
            }
            $this->load->database();
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->available = (bool) $this->db->table_exists('pages');
        $this->db->db_debug = $debug;

        return $this->available;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @param string $column
     * @param mixed  $value
     * @param bool   $publishedOnly
     *
     * @return array<string, mixed>|null
     */
    protected function one($column, $value, $publishedOnly)
    {
        if (!$this->available()) {
            return null;
        }

        $row = $this->quiet(function ($db) use ($column, $value, $publishedOnly) {
            if ($publishedOnly) {
                $db->where('status', self::STATUS_PUBLISHED);
            }

            return $db->where($column, $value)->limit(1)->get('pages')->row_array();
        });

        if (!is_array($row) || $row === []) {
            return null;
        }
        $row['sections'] = SectionTypes::toArray($row['sections'] ?? '');

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clean(array $data)
    {
        $status = ($data['status'] ?? '') === self::STATUS_PUBLISHED ? self::STATUS_PUBLISHED : self::STATUS_DRAFT;

        return [
            'slug' => mb_substr((string) ($data['slug'] ?? ''), 0, 150),
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 190),
            'status' => $status,
            'sections' => SectionTypes::encode($data['sections'] ?? []),
            'meta_description' => mb_substr(trim((string) ($data['meta_description'] ?? '')), 0, 300),
        ];
    }

    /**
     * @param string $slug
     * @param int|null $ignoreId
     */
    protected function slug_exists($slug, $ignoreId = null)
    {
        $found = $this->quiet(function ($db) use ($slug, $ignoreId) {
            if ($ignoreId) {
                $db->where('id !=', (int) $ignoreId);
            }

            return $db->where('slug', $slug)->limit(1)->count_all_results('pages') > 0;
        });

        return (bool) $found;
    }

    /**
     * Runs $fn with db_debug off, so a missing table or unreachable server
     * returns instead of taking the page down.
     *
     * @param callable $fn
     *
     * @return mixed
     */
    protected function quiet($fn)
    {
        if (!$this->available()) {
            return null;
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = false;

        try {
            return $fn($this->db);
        } catch (\Throwable $e) {
            return null;
        } finally {
            $this->db->db_debug = $debug;
        }
    }
}
