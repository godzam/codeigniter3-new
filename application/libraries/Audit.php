<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Audit\Changes;

/**
 * The audit log: who did what, when, from where, and with which content.
 *
 *   $this->audit->created('products', $id, 'Widget', $row);            // what was added
 *   $this->audit->updated('products', $id, 'Widget', $before, $after); // only what changed
 *   $this->audit->deleted('products', $id, 'Widget', $row);            // what was removed
 *   $this->audit->event('login', 'auth', $userId, 'Ada', ['ip' => ...]); // anything else
 *
 * The generated CRUD modules, users, roles, settings and the generator log
 * themselves; call these from your own modules too. The actor is whoever is
 * signed in (pass an array with id / name / email to say otherwise).
 *
 * Passwords, tokens, keys and other secrets are never written, only
 * "[hidden]" (see App\Audit\Changes). A failure to write never breaks the
 * request: it is reported in the application log instead.
 *
 * @property CI_DB_query_builder $db
 * @property CI_Input $input
 */
class Audit
{
    protected $CI;

    /** @var bool|null */
    protected $available;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * A record that was added.
     *
     * @param array<mixed>|object                                     $values
     * @param array<int, string>                                      $hidden extra keys whose values must not be logged
     * @param array{id?: int, name?: string, email?: string}|null     $actor
     */
    public function created($entity, $id, $label, $values, array $hidden = [], $actor = null)
    {
        $this->write('create', $entity, $id, $label, ['new' => Changes::snapshot($values, $hidden)], $actor);
    }

    /**
     * A record that was changed. Nothing is written when nothing differs.
     *
     * @param array<mixed>|object                                 $before
     * @param array<mixed>|object                                 $after
     * @param array<int, string>                                  $hidden
     * @param array{id?: int, name?: string, email?: string}|null $actor
     */
    public function updated($entity, $id, $label, $before, $after, array $hidden = [], $actor = null)
    {
        $changes = Changes::diff($before, $after, $hidden);

        if ($changes !== []) {
            $this->write('update', $entity, $id, $label, ['changes' => $changes], $actor);
        }
    }

    /**
     * A record that was removed, with what it contained.
     *
     * @param array<mixed>|object                                 $values
     * @param array<int, string>                                  $hidden
     * @param array{id?: int, name?: string, email?: string}|null $actor
     */
    public function deleted($entity, $id, $label, $values, array $hidden = [], $actor = null)
    {
        $this->write('delete', $entity, $id, $label, ['old' => Changes::snapshot($values, $hidden)], $actor);
    }

    /**
     * Anything else worth remembering (sign-in, a block, an unblock, ...).
     *
     * @param array<string, mixed>                                $data
     * @param array{id?: int, name?: string, email?: string}|null $actor
     */
    public function event($action, $entity, $id, $label, array $data = [], $actor = null)
    {
        $this->write($action, $entity, $id, $label, $data === [] ? [] : ['data' => Changes::snapshot($data)], $actor);
    }

    /**
     * @param array<string, mixed>                                $payload
     * @param array{id?: int, name?: string, email?: string}|null $actor
     */
    protected function write($action, $entity, $id, $label, array $payload, $actor)
    {
        try {
            $db = $this->database();
            if ($db === null) {
                return;
            }

            if ($actor === null && function_exists('is_logged_in') && is_logged_in()) {
                $user = current_user();
                $actor = ['id' => (int) $user['id'], 'name' => (string) $user['name'], 'email' => (string) $user['email']];
            }

            $debug = $db->db_debug;
            $db->db_debug = false;

            try {
                $db->insert('audit_logs', [
                    'created_at' => date('Y-m-d H:i:s'),
                    'actor_id' => isset($actor['id']) ? (int) $actor['id'] : null,
                    'actor_name' => isset($actor['name']) ? mb_substr((string) $actor['name'], 0, 100) : null,
                    'actor_email' => isset($actor['email']) ? mb_substr((string) $actor['email'], 0, 190) : null,
                    'ip' => is_cli() ? 'cli' : mb_substr((string) $this->CI->input->ip_address(), 0, 45),
                    'action' => mb_substr((string) $action, 0, 40),
                    'entity' => mb_substr((string) $entity, 0, 60),
                    'entity_id' => $id === null ? null : mb_substr((string) $id, 0, 64),
                    'label' => $label === null || $label === '' ? null : mb_substr((string) $label, 0, 190),
                    'changes' => $payload === [] ? null : Changes::encode($payload),
                ]);
            } finally {
                $db->db_debug = $debug;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Audit log write failed: '.get_class($e).': '.$e->getMessage());
        }
    }

    /**
     * The shared connection, or null when there is no database or the table
     * does not exist yet (before the migration has run).
     */
    protected function database()
    {
        if ($this->available === false) {
            return null;
        }

        if (!isset($this->CI->db) || !is_object($this->CI->db) || empty($this->CI->db->conn_id)) {
            $file = is_file(APPPATH.'config/'.ENVIRONMENT.'/database.php') || is_file(APPPATH.'config/database.php');
            if (!$file) {
                return $this->available = null;
            }
            $this->CI->load->database();
        }

        $db = $this->CI->db;

        if ($this->available === null) {
            $debug = $db->db_debug;
            $db->db_debug = false;
            $this->available = $db->table_exists('audit_logs');
            $db->db_debug = $debug;
        }

        return $this->available ? $db : null;
    }
}
