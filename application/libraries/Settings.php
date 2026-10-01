<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Settings\Schema;

/**
 * Database-backed application settings with defaults from
 * application/config/app_settings.php.
 *
 *   $this->settings->get('app_name');            // saved value, else the default
 *   $this->settings->get('theme_primary');
 *   $this->settings->set('app_name', 'Acme');
 *   $this->settings->register('blog', [...]);    // a module adds its own group
 *
 * Designed never to break a page: with no database configured, an
 * unreachable server, or a missing `settings` table (migrations not run
 * yet), every get() quietly returns the default. Values are read once per
 * request.
 *
 * Note: password-type fields are stored as plain text. They're meant for
 * third-party keys the app needs in the clear (e.g. a Turnstile secret) —
 * keep the database itself protected, and don't store user passwords here.
 */
class Settings
{
    protected $CI;

    /** @var Schema */
    protected $schema;

    /** @var array<string, string>|null raw stored values; null = not loaded yet */
    protected $stored;

    /** @var bool whether the settings table could be read */
    protected $available = false;

    protected $db;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->schema = new Schema($this->load_definitions());
    }

    public function schema()
    {
        return $this->schema;
    }

    /**
     * Adds a settings group (or fields to an existing one), e.g. from a
     * module's constructor.
     *
     * @param array<string, mixed> $group ['label' => ..., 'icon' => ..., 'fields' => [...]]
     */
    public function register($key, array $group)
    {
        $this->schema->addGroup($key, $group);
    }

    public function get($key, $default = null)
    {
        $this->load();

        if (array_key_exists($key, $this->stored ?? [])) {
            return $this->schema->has($key) ? $this->schema->cast($key, $this->stored[$key]) : $this->stored[$key];
        }

        if ($this->schema->has($key)) {
            return $this->schema->defaults()[$key];
        }

        return $default;
    }

    /**
     * @return array<string, mixed> every known setting, stored value or default
     */
    public function all()
    {
        $values = [];
        foreach (array_keys($this->schema->fields()) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function set($key, $value)
    {
        return $this->save([$key => $value]);
    }

    /**
     * Upserts several settings. Returns false if the database or the
     * settings table isn't available.
     *
     * @param array<string, mixed> $values name => value (already validated)
     */
    public function save(array $values)
    {
        $db = $this->database();
        if ($db === null || !$this->table_exists()) {
            return false;
        }

        $debug = $db->db_debug;
        $db->db_debug = false;
        $now = date('Y-m-d H:i:s');
        $ok = true;

        $db->trans_start();
        foreach ($values as $name => $value) {
            $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $exists = $db->where('name', $name)->count_all_results('settings') > 0;

            $ok = $ok && ($exists
                ? $db->where('name', $name)->update('settings', ['value' => $value, 'updated_at' => $now])
                : $db->insert('settings', ['name' => $name, 'value' => $value, 'updated_at' => $now]));
        }
        $db->trans_complete();
        $db->db_debug = $debug;

        $ok = $ok && $db->trans_status() !== false;
        $this->stored = null; // re-read on next get()

        return $ok;
    }

    /**
     * Deletes every saved value, so everything falls back to the defaults.
     */
    public function reset()
    {
        $db = $this->database();
        if ($db === null || !$this->table_exists()) {
            return false;
        }

        $debug = $db->db_debug;
        $db->db_debug = false;
        $ok = (bool) $db->empty_table('settings');
        $db->db_debug = $debug;
        $this->stored = null;

        return $ok;
    }

    /**
     * Whether settings can be saved (database reachable, table exists).
     */
    public function available()
    {
        $this->load();

        return $this->available;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string>        $only
     *
     * @return array{values: array<string, mixed>, errors: array<string, string>, keep: array<string>}
     */
    public function validate(array $input, array $only = [])
    {
        return $this->schema->validate($input, $only);
    }

    protected function load()
    {
        if ($this->stored !== null) {
            return;
        }

        $this->stored = [];
        $this->available = false;

        $db = $this->database();
        if ($db === null) {
            return;
        }

        $debug = $db->db_debug;
        $db->db_debug = false;
        $result = $db->query('SELECT '.$db->escape_identifiers('name').', '.$db->escape_identifiers('value').' FROM '.$db->protect_identifiers('settings', true));
        $db->db_debug = $debug;

        if ($result === false) {
            return; // table missing: migrations haven't been run
        }

        foreach ($result->result_array() as $row) {
            $this->stored[$row['name']] = (string) $row['value'];
        }
        $this->available = true;
    }

    /**
     * The app's shared connection ($CI->db), opened here if nothing has
     * opened it yet — and only if a database is configured and reachable.
     * CI's normal loader calls show_error() (and exits) on a missing config
     * or failed connection, which would take every page down with it, so
     * the first attempt runs with db_debug off.
     *
     * It has to be the shared connection, not a private one: opening a
     * private one defines the CI_DB class, which makes CI's dbforge /
     * dbutil loaders believe $CI->db exists and crash (`php index.php
     * console migrate` died that way).
     */
    protected function database()
    {
        if ($this->db !== null) {
            return $this->db ?: null;
        }

        $this->db = false;

        if (isset($this->CI->db) && is_object($this->CI->db) && !empty($this->CI->db->conn_id)) {
            return $this->db = $this->CI->db;
        }

        $file = is_file(APPPATH.'config/'.ENVIRONMENT.'/database.php')
            ? APPPATH.'config/'.ENVIRONMENT.'/database.php'
            : APPPATH.'config/database.php';

        if (!is_file($file)) {
            return null;
        }

        $db = $active_group = null;
        include $file;

        if (!isset($db[$active_group])) {
            return null;
        }

        $params = $db[$active_group];
        $debug = $params['db_debug'] ?? true;
        $params['db_debug'] = false;

        try {
            $this->CI->load->database($params);
        } catch (\Throwable $e) {
            unset($this->CI->db);

            return null;
        }

        $connection = $this->CI->db ?? null;

        if (is_object($connection) && !empty($connection->conn_id)) {
            $connection->db_debug = $debug; // the app's own setting applies from here on

            return $this->db = $connection;
        }

        unset($this->CI->db); // let the app's own load->database() try (and report) again

        return null;
    }

    protected function table_exists()
    {
        $this->load();

        return $this->available;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function load_definitions()
    {
        $groups = [];

        foreach ([APPPATH.'config/app_settings.php', APPPATH.'config/'.ENVIRONMENT.'/app_settings.php'] as $file) {
            if (is_file($file)) {
                $config = [];
                include $file;
                $groups = array_replace_recursive($groups, $config['app_settings'] ?? []);
            }
        }

        return $groups;
    }
}
