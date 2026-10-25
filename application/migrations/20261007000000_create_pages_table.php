<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Content pages built with Admin -> Pages (application/modules/pages).
 *
 * The sections column holds the whole layout as JSON (see App\Page\SectionTypes)
 * and is rendered by the pages module's view controller at /page/{slug}.
 */
class Migration_Create_pages_table extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'unsigned' => true, 'auto_increment' => true),
            'slug' => array('type' => 'VARCHAR', 'constraint' => 150),
            'title' => array('type' => 'VARCHAR', 'constraint' => 190),
            'status' => array('type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'), // draft | published
            'sections' => array('type' => 'TEXT', 'null' => true),
            'meta_description' => array('type' => 'VARCHAR', 'constraint' => 300, 'null' => true),
            'created_at' => array('type' => 'DATETIME', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('slug');
        $this->dbforge->create_table('pages');

        $this->db->query('CREATE UNIQUE INDEX pages_slug ON pages (slug)');
    }

    public function down()
    {
        $this->dbforge->drop_table('pages');
    }
}
