<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The table CodeIgniter's database session driver uses (SESS_DRIVER=database in
 * .env), so several app servers can share sessions without Redis. It is created
 * on every install, so switching the driver later is only a setting.
 */
class Migration_Create_sessions_table extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'VARCHAR', 'constraint' => 128),
            'ip_address' => array('type' => 'VARCHAR', 'constraint' => 45),
            'timestamp' => array('type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0),
            'data' => array('type' => 'BLOB'),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('ci_sessions', true);
        $this->db->query('CREATE INDEX ci_sessions_timestamp ON ci_sessions (timestamp)');
    }

    public function down()
    {
        $this->dbforge->drop_table('ci_sessions', true);
    }
}
