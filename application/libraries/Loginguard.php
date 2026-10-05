<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Security\LoginLockPolicy;

/**
 * Blocks IP addresses and user accounts after repeated wrong passwords, and
 * lets an admin lift a block. The rule itself lives in App\Security\LoginLockPolicy
 * (5 wrong = 1 hour, the 3rd block in a row = 1 day; Settings -> Security).
 *
 * Both are tracked independently: the IP address of the visitor, and the
 * account being attacked (only accounts that exist, so typing random emails
 * cannot fill the table). A successful login clears the account's record and
 * the IP's, unless that IP is blocked.
 *
 * If the login_locks table is missing (the migration has not run yet) the
 * guard switches itself off instead of locking everyone out; Auth then falls
 * back to the plain per-minute limiter.
 *
 * @property CI_DB_query_builder $db
 * @property CI_Input $input
 * @property Audit $audit
 */
class Loginguard
{
    protected $CI;

    /** @var LoginLockPolicy|null */
    protected $policy;

    /** @var bool|null */
    protected $available;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function policy()
    {
        if ($this->policy === null) {
            $this->policy = new LoginLockPolicy(
                app_setting('lock_max_attempts') ?? 5,
                app_setting('lock_minutes') ?? 60,
                app_setting('lock_strikes') ?? 3,
                app_setting('lock_long_hours') ?? 24
            );
        }

        return $this->policy;
    }

    /**
     * Whether blocking is active (the table exists).
     */
    public function available()
    {
        if ($this->available === null) {
            $db = $this->db();
            $this->available = false;

            if ($db !== null) {
                $debug = $db->db_debug;
                $db->db_debug = false;
                $this->available = $db->table_exists('login_locks');
                $db->db_debug = $debug;
            }
        }

        return $this->available;
    }

    /**
     * Seconds left on a block that applies to this visitor (their IP address
     * or the account they are logging in to), 0 if none.
     *
     * @param int|null $userId the account, if the email belongs to one
     */
    public function seconds_blocked($userId = null)
    {
        if (!$this->available()) {
            return 0;
        }

        $now = time();
        $left = 0;

        foreach ($this->subjects($userId) as [$type, $subject]) {
            $left = max($left, $this->policy()->secondsLeft($this->state($this->row($type, $subject)), $now));
        }

        return $left;
    }

    /**
     * Records a wrong password against the IP address and, if the email
     * belongs to an account, against that account.
     *
     * @return array{locked: bool, seconds: int, attempts_left: int}
     */
    public function failure($userId = null)
    {
        if (!$this->available()) {
            return ['locked' => false, 'seconds' => 0, 'attempts_left' => 0];
        }

        $now = time();
        $policy = $this->policy();
        $locked = false;
        $seconds = 0;
        $attemptsLeft = $policy->maxAttempts();

        foreach ($this->subjects($userId) as [$type, $subject]) {
            $row = $this->row($type, $subject);
            $before = $this->state($row);
            $result = $policy->afterFailure($before, $now);

            $this->save($row, $type, $subject, $result['state'], $now);

            if ($result['locked'] && !$policy->isLocked($before, $now)) {
                $locked = true;
                $seconds = max($seconds, $result['seconds']);
                $this->CI->audit->event('login.locked', 'security', $type.':'.$subject, $this->label($type, $subject), [
                    'blocked_for' => $this->human($result['seconds']),
                    'blocks_in_a_row' => $result['state']['strikes'],
                    'blocked_until' => date('Y-m-d H:i:s', (int) $result['state']['locked_until']),
                ]);
            }

            if ($type === 'ip') {
                $attemptsLeft = $policy->attemptsLeft($result['state'], $now);
            }
        }

        return ['locked' => $locked, 'seconds' => $seconds, 'attempts_left' => $attemptsLeft];
    }

    /**
     * A correct password: forget the failures of the account, and of the IP
     * address too unless it is blocked.
     */
    public function success($userId)
    {
        if (!$this->available()) {
            return;
        }

        $now = time();
        $this->db()->where(['type' => 'user', 'subject' => (string) $userId])->delete('login_locks');

        $ip = $this->row('ip', $this->ip());
        if ($ip && !$this->policy()->isLocked($this->state($ip), $now)) {
            $this->db()->where('id', $ip->id)->delete('login_locks');
        }
    }

    /**
     * Lifts one block (and forgets its failures) by the id of its row.
     *
     * @return array{type: string, subject: string, label: string}|null what was unblocked, null if there was no such block
     */
    public function unblock($id)
    {
        if (!$this->available()) {
            return null;
        }

        $row = $this->db()->get_where('login_locks', ['id' => (int) $id])->row();
        if (!$row) {
            return null;
        }

        $this->db()->where('id', $row->id)->delete('login_locks');

        return ['type' => $row->type, 'subject' => $row->subject, 'label' => $this->label($row->type, $row->subject)];
    }

    /**
     * Lifts the block on an IP address or an account (by email), for the console.
     *
     * @return array{type: string, subject: string, label: string}|null
     */
    public function unblock_who($who)
    {
        if (!$this->available()) {
            return null;
        }

        if (filter_var($who, FILTER_VALIDATE_IP)) {
            $row = $this->row('ip', $who);
        } else {
            $user = $this->db()->select('id')->get_where('users', ['email' => (string) $who])->row();
            $row = $user ? $this->row('user', (string) $user->id) : null;
        }

        return $row ? $this->unblock($row->id) : null;
    }

    /**
     * Human text for a length of time: "about 1 hour".
     */
    public function human($seconds)
    {
        $seconds = max(0, (int) $seconds);

        if ($seconds < 60) {
            return 'less than a minute';
        }
        if ($seconds < 3600) {
            $n = (int) ceil($seconds / 60);

            return $n.' minute'.($n === 1 ? '' : 's');
        }
        if ($seconds < 86400) {
            $n = (int) ceil($seconds / 3600);

            return $n.' hour'.($n === 1 ? '' : 's');
        }

        $n = (int) ceil($seconds / 86400);

        return $n.' day'.($n === 1 ? '' : 's');
    }

    /**
     * "IP 203.0.113.5" or "User Ada (ada@example.com)".
     */
    public function label($type, $subject)
    {
        if ($type === 'ip') {
            return 'IP '.$subject;
        }

        $user = $this->db()->select('name, email')->get_where('users', ['id' => (int) $subject])->row();

        return $user ? 'User '.$user->name.' ('.$user->email.')' : 'User #'.$subject;
    }

    public function ip()
    {
        return (string) $this->CI->input->ip_address();
    }

    // ------------------------------------------------------------------

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function subjects($userId)
    {
        $subjects = [['ip', $this->ip()]];
        if ($userId !== null) {
            $subjects[] = ['user', (string) $userId];
        }

        return $subjects;
    }

    protected function row($type, $subject)
    {
        return $this->db()->get_where('login_locks', ['type' => $type, 'subject' => (string) $subject])->row();
    }

    /**
     * @param object|null $row
     *
     * @return array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null}
     */
    protected function state($row)
    {
        if (!$row) {
            return LoginLockPolicy::emptyState();
        }

        return [
            'fails' => (int) $row->fails,
            'strikes' => (int) $row->strikes,
            'locked_until' => $row->locked_until ? (int) strtotime($row->locked_until) : null,
            'last_fail_at' => $row->last_fail_at ? (int) strtotime($row->last_fail_at) : null,
        ];
    }

    /**
     * @param object|null                                                                      $row
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     */
    protected function save($row, $type, $subject, array $state, $now)
    {
        $data = [
            'fails' => $state['fails'],
            'strikes' => $state['strikes'],
            'locked_until' => $state['locked_until'] === null ? null : date('Y-m-d H:i:s', $state['locked_until']),
            'last_fail_at' => $state['last_fail_at'] === null ? null : date('Y-m-d H:i:s', $state['last_fail_at']),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ];

        if ($row) {
            $this->db()->where('id', $row->id)->update('login_locks', $data);
        } else {
            $this->db()->insert('login_locks', $data + ['type' => $type, 'subject' => (string) $subject, 'created_at' => date('Y-m-d H:i:s', $now)]);
        }
    }

    protected function db()
    {
        if (!isset($this->CI->db) || !is_object($this->CI->db) || empty($this->CI->db->conn_id)) {
            if (!is_file(APPPATH.'config/'.ENVIRONMENT.'/database.php') && !is_file(APPPATH.'config/database.php')) {
                return null;
            }
            $this->CI->load->database();
        }

        return $this->CI->db;
    }
}
