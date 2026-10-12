<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI-only maintenance tasks. CodeIgniter 3 has no built-in artisan-like
 * CLI, so this is the idiomatic CI3 way to get one: a normal controller,
 * invoked from the command line instead of over HTTP. is_cli() below
 * refuses to run it any other way, so it's never reachable through a
 * browser regardless of routing.
 *
 * Usage:
 *   php index.php console migrate
 *   php index.php console seed
 *   php index.php console seed users
 *
 * @property CI_Migration $migration
 * @property Seeder $seeder
 * @property Loginguard $loginguard
 * @property Audit $audit
 * @property Settings $settings
 * @property CI_DB_query_builder $db
 */
class Console extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!is_cli()) {
            show_error('The console can only be run from the command line.', 403);
        }
    }

    public function migrate()
    {
        $this->load->library('migration');

        // When several servers deploy at once, only one may migrate; the others wait for it
        // (MySQL advisory lock, released when this connection closes).
        $locked = $this->advisory_lock('ci_migrate', 120);
        if ($locked === false) {
            echo 'Another server is migrating right now; gave up waiting. Run it again in a minute.'.PHP_EOL;

            return;
        }

        if (!$this->migration->latest()) {
            echo 'Migration failed: '.$this->migration->error_string().PHP_EOL;

            return;
        }

        echo 'Migrated to the latest version.'.PHP_EOL;
    }

    /**
     * Housekeeping for tables that only grow. Run it from cron, e.g. once a night:
     *
     *   php index.php console prune
     *
     * - audit_logs older than Settings -> Security -> "Keep the audit log for" days (0 = keep forever)
     * - login_locks rows that are not blocking anyone and had no wrong password for 30 days
     *
     * Rows go in batches of 2,000 so the table is never locked for long.
     */
    public function prune()
    {
        $this->load->database();
        $this->load->library('settings');
        $removed = ['audit_logs' => 0, 'login_locks' => 0];

        $days = (int) $this->settings->get('audit_retention_days', 0);
        if ($days > 0 && $this->db->table_exists('audit_logs')) {
            $removed['audit_logs'] = $this->delete_in_batches('audit_logs', function ($db) use ($days) {
                $db->where('created_at <', date('Y-m-d H:i:s', time() - $days * 86400));
            });
        }

        if ($this->db->table_exists('login_locks')) {
            $removed['login_locks'] = $this->delete_in_batches('login_locks', function ($db) {
                $db->group_start()->where('locked_until IS NULL', null, false)->or_where('locked_until <', date('Y-m-d H:i:s'))->group_end();
                $db->where('updated_at <', date('Y-m-d H:i:s', time() - 30 * 86400));
            });
        }

        foreach ($removed as $table => $n) {
            echo str_pad($table, 12).' '.$n.' row(s) removed'.PHP_EOL;
        }
        if ($days === 0) {
            echo 'The audit log is kept forever (Settings -> Security -> "Keep the audit log for").'.PHP_EOL;
        }
    }

    /**
     * Deletes the rows the filter matches, 2,000 at a time (selected by id first, which works on every driver).
     */
    protected function delete_in_batches($table, callable $filter)
    {
        $total = 0;
        do {
            $filter($this->db);
            $ids = array_column($this->db->select('id')->limit(2000)->get($table)->result_array(), 'id');
            if ($ids !== []) {
                $this->db->where_in('id', $ids)->delete($table);
                $total += count($ids);
            }
        } while (count($ids) === 2000);

        return $total;
    }

    /**
     * @return bool|null true when taken, false when it timed out, null when this database has no such lock
     */
    protected function advisory_lock($name, $seconds)
    {
        $this->load->database();
        if ($this->db->dbdriver !== 'mysqli') {
            return null;
        }

        $row = $this->db->query('SELECT GET_LOCK(?, ?) AS l', [$name, (int) $seconds])->row_array();

        return (int) ($row['l'] ?? 0) === 1;
    }

    public function seed($seeder = null)
    {
        $this->load->library('seeder');

        if ($seeder !== null) {
            $this->seeder->run($seeder);
            echo "Seeded: {$seeder}".PHP_EOL;

            return;
        }

        $count = $this->seeder->run_all();
        echo "Ran {$count} seeder(s).".PHP_EOL;
    }

    /**
     * Lifts a login block from the command line, e.g. when the only admin
     * is locked out:
     *
     *   php index.php console unblock 203.0.113.5
     *   php index.php console unblock admin@example.com
     *   php index.php console unblock all
     */
    public function unblock($who = null)
    {
        $this->load->library('loginguard');

        if ($who === null || $who === '') {
            echo 'Usage: php index.php console unblock <ip address | email | all>'.PHP_EOL;

            return;
        }

        if (!$this->loginguard->available()) {
            echo 'The login security tables do not exist. Run: php index.php console migrate'.PHP_EOL;

            return;
        }

        if ($who === 'all') {
            $this->load->database();
            $this->db->empty_table('login_locks');
            $this->audit->event('login.unblocked', 'security', null, 'Every IP address and user (console)');
            echo 'All blocks lifted.'.PHP_EOL;

            return;
        }

        $done = $this->loginguard->unblock_who($who);

        if ($done === null) {
            echo "Nothing is blocked for {$who}.".PHP_EOL;

            return;
        }

        $this->audit->event('login.unblocked', 'security', $done['type'].':'.$done['subject'], $done['label'].' (console)');
        echo $done['label'].' can sign in again.'.PHP_EOL;
    }
}
