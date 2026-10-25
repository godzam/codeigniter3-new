<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_verification_columns extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'email_verified' => array('type' => 'TINY', 'constraint' => 1, 'default' => 0),
            'email_verification_token' => array('type' => 'VARCHAR', 'constraint' => 255, 'null' => true),
            'password_reset_token' => array('type' => 'VARCHAR', 'constraint' => 255, 'null' => true),
            'password_reset_expires' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->modify_table('users', array(
            'email_verified' => array('after' => 'password'),
            'email_verification_token' => array('after' => 'email_verified'),
            'password_reset_token' => array('after' => 'email_verification_token'),
            'password_reset_expires' => array('after' => 'password_reset_token'),
        ));

        // Set default: first super_admin gets verified email automatically
        $this->db->query("UPDATE users SET email_verified = 1 WHERE role = 'super_admin' LIMIT 1");
    }

    public function down()
    {
        $this->dbforge->drop_column('users', 'email_verified');
        $this->dbforge->drop_column('users', 'email_verification_token');
        $this->dbforge->drop_column('users', 'password_reset_token');
        $this->dbforge->drop_column('users', 'password_reset_expires');
    }
}