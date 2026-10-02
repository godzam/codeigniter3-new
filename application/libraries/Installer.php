<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Install\DatabaseConfig;
use App\Install\Requirements;

/**
 * One-click setup behind /install and the button on the Welcome page: makes
 * the database (and its connection file) if needed, runs every migration,
 * saves the application name and creates the first administrator.
 *
 * Who may use it:
 *   - only while no user exists (afterwards /install is gone for good), and
 *   - only in the development / testing environments, or when INSTALLER_ENABLED=true
 *     is set in .env — so a production site never exposes it by accident.
 *
 * @property CI_DB_query_builder $db
 * @property CI_Loader $load
 * @property CI_Migration $migration
 */
class Installer
{
    protected $CI;

    /** @var array<string, mixed>|null */
    protected $state;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function enabled()
    {
        return in_array(ENVIRONMENT, ['development', 'testing'], true)
            || filter_var(getenv('INSTALLER_ENABLED'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * True once the users table exists and has at least one row.
     */
    public function installed()
    {
        if ($this->db_state()['state'] !== 'ok') {
            return false;
        }

        $db = $this->connect($this->db_params());
        if ($db === null) {
            return false;
        }

        $debug = $db->db_debug;
        $db->db_debug = false;

        try {
            return $db->table_exists('users') && $db->count_all_results('users') > 0;
        } catch (\Throwable $e) {
            return false;
        } finally {
            $db->db_debug = $debug;
        }
    }

    /**
     * Everything the installer page shows.
     *
     * @return array<string, mixed>
     */
    public function status()
    {
        $state = $this->db_state();
        $needsDatabase = $this->needs_database($state);
        $checks = Requirements::evaluate($this->facts($needsDatabase));

        return [
            'db' => $state,
            'needs_database' => $needsDatabase,
            'checks' => $checks,
            'can_install' => Requirements::canInstall($checks),
        ];
    }

    /**
     * Where the connection stands.
     *
     * state: ok | no_config (no database.php) | cannot_connect | no_database (server reachable, database missing)
     *
     * @return array{state: string, error: string, params: array<string, mixed>|null}
     */
    public function db_state($refresh = false)
    {
        if ($this->state !== null && !$refresh) {
            return $this->state;
        }

        $params = $this->db_params();

        if ($params === null) {
            return $this->state = ['state' => 'no_config', 'error' => '', 'params' => null];
        }

        if (($params['dbdriver'] ?? '') === 'mysqli') {
            return $this->state = $this->probe_mysqli($params);
        }

        // Any other driver (sqlite3, pdo, ...): let CodeIgniter's own layer try, quietly.
        $ok = $this->connect($params) !== null;

        return $this->state = ['state' => $ok ? 'ok' : 'cannot_connect', 'error' => $ok ? '' : 'The database could not be opened.', 'params' => $params];
    }

    /**
     * Runs the installation with values already checked by InstallInput.
     *
     * @param array<string, string> $v
     *
     * @return array{ok: bool, error?: string, steps: array<int, string>, manual_config?: string, user?: array<string, mixed>}
     */
    public function run(array $v)
    {
        $steps = [];

        try {
            $state = $this->db_state(true);
            $checks = Requirements::evaluate($this->facts($this->needs_database($state)));
            if (!Requirements::canInstall($checks)) {
                return ['ok' => false, 'error' => 'This server does not meet the requirements listed above.', 'steps' => $steps];
            }

            $params = $state['params'];
            $write = null;

            // 1. The database -------------------------------------------------
            if ($this->needs_database($state)) {
                if (!isset($v['db_name'])) {
                    return ['ok' => false, 'error' => 'Enter the database connection first.', 'steps' => $steps];
                }

                $db = ['host' => $v['db_host'], 'user' => $v['db_user'], 'pass' => $v['db_pass'], 'name' => $v['db_name']];
                $params = DatabaseConfig::params($db);
                $write = $db;
                $probe = $this->probe_mysqli($params);

                if ($probe['state'] === 'cannot_connect') {
                    return ['ok' => false, 'error' => 'Could not connect to the database server: '.$probe['error'], 'steps' => $steps];
                }
                $state = $probe;
            }

            if ($state['state'] === 'no_database') {
                $error = $this->create_database($params);
                if ($error !== null) {
                    return ['ok' => false, 'error' => $error, 'steps' => $steps];
                }
                $steps[] = 'Created the database "'.$params['database'].'"';
            }

            $connection = $this->connect($params);
            if ($connection === null) {
                return ['ok' => false, 'error' => 'The database connection failed.', 'steps' => $steps];
            }

            if ($write !== null) {
                $target = $this->config_file() ?? APPPATH.'config/database.php';
                $source = DatabaseConfig::render($write);

                if (is_file($target)) {
                    @copy($target, $target.'.bak');
                }
                if (@file_put_contents($target, $source, LOCK_EX) === false) {
                    return [
                        'ok' => false,
                        'steps' => $steps,
                        'manual_config' => $source,
                        'error' => 'The connection works, but '.str_replace(FCPATH, '', $target).' could not be written (folder not writable). Save the text below as that file and run the installer again.',
                    ];
                }
                $steps[] = 'Saved the connection in '.str_replace(FCPATH, '', $target);
            }

            // 2. The tables ----------------------------------------------------
            $this->CI->load->library('migration');
            if ($this->CI->migration->latest() === false) {
                return ['ok' => false, 'error' => 'Migration failed: '.$this->CI->migration->error_string(), 'steps' => $steps];
            }
            $steps[] = 'Created all tables (migrations)';

            // 3. Settings and the first administrator -------------------------
            $now = date('Y-m-d H:i:s');
            $connection->where('name', 'app_name')->delete('settings');
            $connection->insert('settings', ['name' => 'app_name', 'value' => $v['app_name'], 'updated_at' => $now]);
            $steps[] = 'Saved the application name';

            if ($connection->where('email', $v['admin_email'])->count_all_results('users') > 0) {
                return ['ok' => false, 'error' => 'That email already has an account.', 'steps' => $steps];
            }

            $connection->insert('users', [
                'name' => $v['admin_name'],
                'email' => $v['admin_email'],
                'password' => password_hash($v['admin_password'], PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $steps[] = 'Created the administrator account';

            return [
                'ok' => true,
                'steps' => $steps,
                'user' => ['id' => (int) $connection->insert_id(), 'name' => $v['admin_name'], 'email' => $v['admin_email'], 'role' => 'super_admin'],
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Installer failed: '.get_class($e).': '.$e->getMessage());

            return ['ok' => false, 'error' => 'Installation stopped: '.$e->getMessage(), 'steps' => $steps];
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function needs_database(array $state)
    {
        return in_array($state['state'], ['no_config', 'cannot_connect'], true);
    }

    /**
     * The config file CodeIgniter would use in this environment, if any.
     */
    protected function config_file()
    {
        foreach ([APPPATH.'config/'.ENVIRONMENT.'/database.php', APPPATH.'config/database.php'] as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null the active database group, null without a config file
     */
    protected function db_params()
    {
        $file = $this->config_file();
        if ($file === null) {
            return null;
        }

        $db = $active_group = null;
        include $file;

        return isset($db[$active_group]) && is_array($db[$active_group]) ? $db[$active_group] : null;
    }

    /**
     * A quiet connection check for MySQL / MariaDB, using mysqli directly so
     * that a wrong password or a missing database is an answer, not an error page.
     *
     * @param array<string, mixed> $p
     *
     * @return array{state: string, error: string, params: array<string, mixed>}
     */
    protected function probe_mysqli(array $p)
    {
        if (!extension_loaded('mysqli')) {
            return ['state' => 'cannot_connect', 'error' => 'The mysqli PHP extension is not enabled (php.ini).', 'params' => $p];
        }

        [$errno, $message] = $this->mysqli_try($p, true);
        if ($errno === 0) {
            return ['state' => 'ok', 'error' => '', 'params' => $p];
        }

        if ($errno === 1049) { // unknown database: is the server itself reachable?
            [$serverErrno, $serverMessage] = $this->mysqli_try($p, false);

            return $serverErrno === 0
                ? ['state' => 'no_database', 'error' => '', 'params' => $p]
                : ['state' => 'cannot_connect', 'error' => $serverMessage, 'params' => $p];
        }

        return ['state' => 'cannot_connect', 'error' => $message, 'params' => $p];
    }

    /**
     * @param array<string, mixed> $p
     *
     * @return array{0: int, 1: string} [error number (0 = connected), message]
     */
    protected function mysqli_try(array $p, $withDatabase)
    {
        try {
            $link = $this->mysqli_link($p, $withDatabase);
            if ($link->connect_errno) {
                return [(int) $link->connect_errno, (string) $link->connect_error];
            }
            $link->close();

            return [0, ''];
        } catch (\mysqli_sql_exception $e) {
            return [(int) $e->getCode() ?: 2002, $e->getMessage()];
        }
    }

    /**
     * A connection attempt that gives up after 3 seconds, so a database server
     * that is switched off cannot freeze the Welcome page.
     *
     * @param array<string, mixed> $p
     *
     * @return mysqli
     */
    protected function mysqli_link(array $p, $withDatabase)
    {
        $port = !empty($p['port']) ? (int) $p['port'] : ((int) ini_get('mysqli.default_port') ?: 3306);

        $link = mysqli_init();
        $link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        @$link->real_connect((string) ($p['hostname'] ?? ''), (string) ($p['username'] ?? ''), (string) ($p['password'] ?? ''), $withDatabase ? (string) ($p['database'] ?? '') : '', $port);

        return $link;
    }

    /**
     * @param array<string, mixed> $p
     *
     * @return string|null an error message, null on success
     */
    protected function create_database(array $p)
    {
        try {
            $link = $this->mysqli_link($p, false);
            if ($link->connect_errno) {
                return 'Could not connect to the database server: '.$link->connect_error;
            }
            $created = $link->query(DatabaseConfig::createStatement($p['database']));
            $link->close();

            return $created ? null : 'The database "'.$p['database'].'" could not be created.';
        } catch (\mysqli_sql_exception $e) {
            return 'The database "'.$p['database'].'" does not exist and could not be created ('.$e->getMessage().'). Create it yourself (phpMyAdmin, HeidiSQL) and run the installer again.';
        }
    }

    /**
     * Opens (or reuses) the shared CodeIgniter connection quietly.
     *
     * @param array<string, mixed>|null $params
     */
    protected function connect($params)
    {
        if ($params === null) {
            return null;
        }

        $params['db_debug'] = false;

        try {
            $this->CI->load->database($params);
        } catch (\Throwable $e) {
            unset($this->CI->db);

            return null;
        }

        $db = $this->CI->db ?? null;
        if (is_object($db) && !empty($db->conn_id)) {
            return $db;
        }

        unset($this->CI->db); // let the app's own load->database() report it again

        return null;
    }

    /**
     * @return array{php: string, extensions: array<string, bool>, writable: array<string, bool>, composer: bool}
     */
    protected function facts($writeConfig)
    {
        $writable = [
            'application/logs' => is_writable(APPPATH.'logs'),
            'application/cache' => is_writable(APPPATH.'cache'),
            'uploads' => is_dir(FCPATH.'uploads') ? is_writable(FCPATH.'uploads') : is_writable(FCPATH),
        ];
        if ($writeConfig) {
            $writable['application/config'] = is_writable(APPPATH.'config');
        }

        $extensions = [];
        foreach (['mbstring', 'json', 'mysqli', 'fileinfo', 'gd'] as $name) {
            $extensions[$name] = extension_loaded($name);
        }

        return ['php' => PHP_VERSION, 'extensions' => $extensions, 'writable' => $writable, 'composer' => is_file(FCPATH.'vendor/autoload.php')];
    }
}
