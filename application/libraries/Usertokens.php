<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Auth\Tokens;

/**
 * Database side of the e-mailed one-time links (see App\Auth\Tokens).
 *
 *   $plain = $this->usertokens->issue($userId, Tokens::RESET_PASSWORD);  // goes into the e-mail
 *   $userId = $this->usertokens->peek(Tokens::RESET_PASSWORD, $plain);   // look, do not use up
 *   $userId = $this->usertokens->consume(Tokens::RESET_PASSWORD, $plain); // use up, once
 *
 * consume() is a single UPDATE ... WHERE used_at IS NULL AND expires_at > now, so
 * two requests with the same link can never both succeed.
 *
 * @property CI_DB_query_builder $db
 */
class Usertokens
{
    protected $CI;

    /** @var bool|null */
    protected $available;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * False while the migration has not run (the features that need links are then off).
     */
    public function available()
    {
        if ($this->available === null) {
            try {
                $db = $this->db();
                $this->available = $db !== null && $db->table_exists('user_tokens') && $db->field_exists('email_verified_at', 'users');
            } catch (\Throwable $e) {
                $this->available = false;
            }
        }

        return $this->available;
    }

    /**
     * Creates a link token for the user, replacing any unused one of the same kind.
     *
     * @return string the token to put in the link (it is not stored anywhere)
     */
    public function issue($userId, $type)
    {
        $db = $this->db();
        $token = Tokens::generate();
        $now = time();

        $db->where(['user_id' => (int) $userId, 'type' => $type, 'used_at' => null])->delete('user_tokens');
        $db->insert('user_tokens', [
            'user_id' => (int) $userId,
            'type' => $type,
            'token_hash' => $token['hash'],
            'expires_at' => date('Y-m-d H:i:s', $now + Tokens::ttl($type)),
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);

        return $token['plain'];
    }

    /**
     * @return int|null the user a still-valid token belongs to, without using it up
     */
    public function peek($type, $plain)
    {
        if (!Tokens::wellFormed($plain)) {
            return null;
        }

        $row = $this->db()
            ->select('user_id')
            ->where(['token_hash' => Tokens::hash($plain), 'type' => $type, 'used_at' => null])
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get('user_tokens')->row();

        return $row ? (int) $row->user_id : null;
    }

    /**
     * Uses the token up. Only the first caller gets the user id back.
     *
     * @return int|null
     */
    public function consume($type, $plain)
    {
        if (!Tokens::wellFormed($plain)) {
            return null;
        }

        $db = $this->db();
        $hash = Tokens::hash($plain);
        $now = date('Y-m-d H:i:s');

        $db->where(['token_hash' => $hash, 'type' => $type, 'used_at' => null])
            ->where('expires_at >', $now)
            ->update('user_tokens', ['used_at' => $now]);

        if ($db->affected_rows() !== 1) {
            return null;
        }

        $row = $db->select('user_id')->get_where('user_tokens', ['token_hash' => $hash])->row();

        return $row ? (int) $row->user_id : null;
    }

    /**
     * Cancels every unused link of a kind (after a password change, for example).
     */
    public function revoke($userId, $type)
    {
        $this->db()->where(['user_id' => (int) $userId, 'type' => $type, 'used_at' => null])->delete('user_tokens');
    }

    protected function db()
    {
        if (!isset($this->CI->db) || !is_object($this->CI->db) || empty($this->CI->db->conn_id)) {
            $this->CI->load->database();
        }

        return $this->CI->db;
    }
}
