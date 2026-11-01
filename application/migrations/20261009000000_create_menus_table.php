<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sidebar menu items built with Admin -> Menus (application/modules/menus).
 *
 * Rows are merged with application/config/menu.php by menu_items(); a row
 * with type "section" is a small heading, anything else is a link (which may
 * have children through parent_id).
 */
class Migration_Create_menus_table extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'unsigned' => true, 'auto_increment' => true),
            'parent_id' => array('type' => 'INT', 'unsigned' => true, 'null' => true),
            'type' => array('type' => 'VARCHAR', 'constraint' => 20, 'default' => 'link'), // link | section
            'label' => array('type' => 'VARCHAR', 'constraint' => 120),
            'icon' => array('type' => 'VARCHAR', 'constraint' => 60, 'null' => true),
            'url' => array('type' => 'VARCHAR', 'constraint' => 255, 'null' => true),
            'permission' => array('type' => 'VARCHAR', 'constraint' => 100, 'null' => true),
            'super_admin' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'position' => array('type' => 'INT', 'unsigned' => true, 'default' => 0),
            'is_active' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 1),
            'created_at' => array('type' => 'DATETIME', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('menus');

        $this->db->query('CREATE INDEX menus_parent_id ON menus (parent_id)');
        $this->db->query('CREATE INDEX menus_position ON menus (position)');
    }

    public function down()
    {
        $this->dbforge->drop_table('menus');
    }
}
