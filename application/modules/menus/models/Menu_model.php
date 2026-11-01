<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Menus\MenuTree;

/**
 * The admin sidebar items built with Admin -> Menus (application/modules/menus).
 *
 * Rows are stored flat: parent_id + position. menu_items() in
 * application/helpers/theme_helper.php asks for sidebar_items() and merges the
 * result with application/config/menu.php, so a menu built here appears in the
 * sidebar next to the built-in entries.
 *
 * Every query is quiet: before the migration has run — or with no database at
 * all — the menu simply stays config-only and nothing breaks.
 *
 * @property CI_DB_query_builder $db
 */
class Menu_model extends CI_Model
{
    public const TYPE_LINK = 'link';
    public const TYPE_SECTION = 'section';

    /** Deepest nesting the builder allows (keeps the sidebar readable). */
    public const MAX_DEPTH = 2;

    /** @var bool|null table existence, checked once per request */
    protected $available;

    /**
     * Every row, ordered for display: top level first, children under their parent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all()
    {
        $rows = $this->rows();
        $out = [];

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
        }

        $walk = function ($parentId, $depth) use (&$walk, &$out, $byParent) {
            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['depth'] = $depth;
                $out[] = $row;
                $walk((int) $row['id'], $depth + 1);
            }
        };
        $walk(0, 0);

        return $out;
    }

    /**
     * @param int $id
     *
     * @return array<string, mixed>|null
     */
    public function find($id)
    {
        foreach ($this->rows() as $row) {
            if ((int) $row['id'] === (int) $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The items a new row starts out with (used for defaults and validation).
     *
     * @return array<string, mixed>
     */
    public function defaults()
    {
        return ['type' => self::TYPE_LINK, 'label' => '', 'icon' => '', 'url' => '', 'permission' => '', 'super_admin' => 0, 'is_active' => 1];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int|null the new id, null on failure
     */
    public function create(array $data)
    {
        if (!$this->available()) {
            return null;
        }

        $data['position'] = (int) $this->db->select_max('position')->get('menus')->row()->position + 1;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->insert('menus', $data) ? (int) $this->db->insert_id() : null;
    }

    /**
     * @param int                 $id
     * @param array<string, mixed> $data
     */
    public function update($id, array $data)
    {
        if (!$this->available()) {
            return false;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return (bool) $this->db->where('id', (int) $id)->update('menus', $data);
    }

    /**
     * Removes an item; its children move up to its parent, so the sidebar
     * never shows an orphan.
     */
    public function delete($id)
    {
        if (!$this->available()) {
            return false;
        }

        $row = $this->find($id);
        if ($row === null) {
            return false;
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_start();
        $this->db->where('parent_id', (int) $id)->update('menus', ['parent_id' => (int) ($row['parent_id'] ?? 0)]);
        $this->db->where('id', (int) $id)->delete('menus');
        $this->db->trans_complete();
        $this->db->db_debug = $debug;

        return $this->db->trans_status() !== false;
    }

    /**
     * Rebuilds parent_id / position from the order the builder posted:
     * a flat list of [id, depth] pairs, depth 0 = top level.
     *
     * @param array<int, array{0: int, 1: int}> $order
     *
     * @return bool
     */
    public function reorder(array $order)
    {
        if (!$this->available() || $order === []) {
            return false;
        }

        $known = [];
        foreach ($this->rows() as $row) {
            $known[(int) $row['id']] = (int) ($row['parent_id'] ?? 0);
        }

        $parents = MenuTree::parents($order, $known, self::MAX_DEPTH);
        if ($parents === false) {
            return false;
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_start();
        $position = 0;
        foreach ($parents as $id => $parent) {
            $position++;
            $this->db->where('id', $id)->update('menus', ['parent_id' => $parent, 'position' => $position, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->trans_complete();
        $this->db->db_debug = $debug;

        return $this->db->trans_status() !== false;
    }

    /**
     * The rows as menu_items() wants them (label / icon / url / permission /
     * children), ready to merge with the config menu. Inactive items, sections
     * with nothing under them and links without a URL are skipped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sidebar_items()
    {
        $rows = array_values(array_filter($this->rows(), static function ($row) {
            return !empty($row['is_active']);
        }));
        if ($rows === []) {
            return [];
        }

        $children = [];
        $top = [];
        foreach ($rows as $row) {
            if ((int) ($row['parent_id'] ?? 0) === 0) {
                $top[] = $row;
            } else {
                $children[(int) $row['parent_id']][] = $row;
            }
        }

        usort($top, static function ($a, $b) {
            return [(int) $a['position'], (int) $a['id']] <=> [(int) $b['position'], (int) $b['id']];
        });

        $build = function ($rows) use (&$build, $children) {
            $out = [];
            foreach ($rows as $row) {
                if ($row['type'] === self::TYPE_SECTION) {
                    $out[] = ['section' => (string) $row['label']];

                    continue;
                }
                if (trim((string) ($row['url'] ?? '')) === '') {
                    continue;
                }

                $item = [
                    'label' => (string) $row['label'],
                    'url' => (string) $row['url'],
                ];
                if (trim((string) ($row['icon'] ?? '')) !== '') {
                    $item['icon'] = (string) $row['icon'];
                }
                if (trim((string) ($row['permission'] ?? '')) !== '') {
                    $item['permission'] = (string) $row['permission'];
                }
                if (!empty($row['super_admin'])) {
                    $item['super_admin'] = true;
                }

                $kids = $build($children[(int) $row['id']] ?? []);
                if ($kids !== []) {
                    $item['children'] = $kids;
                }

                $out[] = $item;
            }

            return $out;
        };

        return $build($top);
    }

    /**
     * Whether the table exists (checked once; false before the migration).
     */
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
        $this->available = (bool) $this->db->table_exists('menus');
        $this->db->db_debug = $debug;

        return $this->available;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows()
    {
        if (!$this->available()) {
            return [];
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $result = $this->db->order_by('position ASC, id ASC')->get('menus');
        $this->db->db_debug = $debug;

        return $result === false ? [] : $result->result_array();
    }
}
