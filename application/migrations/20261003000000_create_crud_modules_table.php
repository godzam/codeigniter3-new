<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modules made with the CRUD generator (Admin → Generator).
 *
 * One row per module: its key (`slug`, also the permission prefix and the
 * URL admin/c/<slug>), the table it manages, and the field definitions as
 * JSON. The module's own table is created by the generator, not here.
 */
class Migration_Create_crud_modules_table extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'slug' => array('type' => 'VARCHAR', 'constraint' => 30),
            'table_name' => array('type' => 'VARCHAR', 'constraint' => 60),
            'title' => array('type' => 'VARCHAR', 'constraint' => 60),
            'icon' => array('type' => 'VARCHAR', 'constraint' => 64, 'default' => 'bi-table'),
            'definition' => array('type' => 'TEXT'),
            'created_at' => array('type' => 'DATETIME', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('crud_modules');
        $this->db->query('CREATE UNIQUE INDEX crud_modules_slug_unique ON crud_modules (slug)');
    }

    public function down()
    {
        // The generated tables are left alone: they hold real data.
        $this->dbforge->drop_table('crud_modules');
    }
}
