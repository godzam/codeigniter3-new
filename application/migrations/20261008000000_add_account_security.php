<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * E-mail verification, password reset and Google sign-in.
 *
 * - users.email_verified_at: NULL = the address was never confirmed. Every user
 *   that exists today is marked confirmed (they signed up before verification
 *   existed, and locking them out would be wrong).
 * - users.google_sub: the stable Google account id of a user who signed in with
 *   Google (NULL for everybody else; unique).
 * - user_tokens: the one-time links sent by e-mail. Only the SHA-256 hash of a
 *   token is stored, never the token, so a database copy cannot be used to
 *   reset anybody's password. Rows are removed by `console prune`.
 */
class Migration_Add_account_security extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_column('users', array(
            'email_verified_at' => array('type' => 'DATETIME', 'null' => true),
            'google_sub' => array('type' => 'VARCHAR', 'constraint' => 64, 'null' => true),
        ));
        $this->db->query('CREATE UNIQUE INDEX users_google_sub_unique ON users (google_sub)');
        $this->db->query('UPDATE users SET email_verified_at = COALESCE(created_at, ?) WHERE email_verified_at IS NULL', array(date('Y-m-d H:i:s')));

        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'user_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true),
            'type' => array('type' => 'VARCHAR', 'constraint' => 20),
            'token_hash' => array('type' => 'CHAR', 'constraint' => 64),
            'expires_at' => array('type' => 'DATETIME'),
            'used_at' => array('type' => 'DATETIME', 'null' => true),
            'created_at' => array('type' => 'DATETIME'),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('user_tokens');
        $this->db->query('CREATE UNIQUE INDEX user_tokens_hash_unique ON user_tokens (token_hash)');
        $this->db->query('CREATE INDEX user_tokens_user_type ON user_tokens (user_id, type)');
        $this->db->query('CREATE INDEX user_tokens_expires ON user_tokens (expires_at)');
    }

    public function down()
    {
        $this->dbforge->drop_table('user_tokens', true);
        $this->db->query('DROP INDEX users_google_sub_unique ON users');
        $this->dbforge->drop_column('users', 'google_sub');
        $this->dbforge->drop_column('users', 'email_verified_at');
    }
}
