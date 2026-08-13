<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_users_table extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'name' => array('type' => 'VARCHAR', 'constraint' => 255),
            'email' => array('type' => 'VARCHAR', 'constraint' => 255),
            'password' => array('type' => 'VARCHAR', 'constraint' => 255),
            'role' => array('type' => 'VARCHAR', 'constraint' => 50, 'default' => 'user'),
            'created_at' => array('type' => 'DATETIME', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('users');

        $this->db->query('ALTER TABLE users ADD UNIQUE KEY users_email_unique (email)');
    }

    public function down()
    {
        $this->dbforge->drop_table('users');
    }
}
