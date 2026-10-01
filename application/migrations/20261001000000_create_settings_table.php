<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_settings_table extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'name' => array('type' => 'VARCHAR', 'constraint' => 100),
            'value' => array('type' => 'TEXT', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('name', true);
        $this->dbforge->create_table('settings');
    }

    public function down()
    {
        $this->dbforge->drop_table('settings');
    }
}
