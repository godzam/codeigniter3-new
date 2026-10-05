<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Login security and the audit log.
 *
 * - `login_locks`: one row per IP address or user account that has failed to
 *   log in (type 'ip' / 'user', subject = the address or the user id): the
 *   wrong attempts since the last block, the blocks in a row, and until when
 *   it is blocked. Deleting a row is how an admin unblocks.
 * - `audit_logs`: who did what and when, with the content that was added,
 *   changed or removed (secrets are never written). Nothing in the app edits
 *   or deletes these rows.
 *
 * The admin role also receives the three new permissions, so existing sites
 * keep working without a visit to the Roles page.
 */
class Migration_Create_security_tables extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'type' => array('type' => 'VARCHAR', 'constraint' => 10),
            'subject' => array('type' => 'VARCHAR', 'constraint' => 64),
            'fails' => array('type' => 'INT', 'constraint' => 11, 'default' => 0),
            'strikes' => array('type' => 'INT', 'constraint' => 11, 'default' => 0),
            'locked_until' => array('type' => 'DATETIME', 'null' => true),
            'last_fail_at' => array('type' => 'DATETIME', 'null' => true),
            'created_at' => array('type' => 'DATETIME', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('login_locks');
        $this->db->query('CREATE UNIQUE INDEX login_locks_subject_unique ON login_locks (type, subject)');

        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'created_at' => array('type' => 'DATETIME'),
            'actor_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true),
            'actor_name' => array('type' => 'VARCHAR', 'constraint' => 100, 'null' => true),
            'actor_email' => array('type' => 'VARCHAR', 'constraint' => 190, 'null' => true),
            'ip' => array('type' => 'VARCHAR', 'constraint' => 45, 'null' => true),
            'action' => array('type' => 'VARCHAR', 'constraint' => 40),
            'entity' => array('type' => 'VARCHAR', 'constraint' => 60),
            'entity_id' => array('type' => 'VARCHAR', 'constraint' => 64, 'null' => true),
            'label' => array('type' => 'VARCHAR', 'constraint' => 190, 'null' => true),
            'changes' => array('type' => 'TEXT', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('audit_logs');
        $this->db->query('CREATE INDEX audit_logs_created_at ON audit_logs (created_at)');
        $this->db->query('CREATE INDEX audit_logs_entity ON audit_logs (entity, entity_id)');
        $this->db->query('CREATE INDEX audit_logs_action ON audit_logs (action)');
        $this->db->query('CREATE INDEX audit_logs_actor ON audit_logs (actor_id)');

        $this->grant_to_admin(array('security.view', 'security.unblock', 'audit.view'));
    }

    public function down()
    {
        $this->dbforge->drop_table('audit_logs');
        $this->dbforge->drop_table('login_locks');
        $this->db->where_in('permission', array('security.view', 'security.unblock', 'audit.view'))->delete('role_permissions');
    }

    /**
     * @param array<int, string> $permissions
     */
    protected function grant_to_admin(array $permissions)
    {
        $admin = $this->db->get_where('roles', array('slug' => 'admin'))->row();
        if (!$admin) {
            return;
        }

        foreach ($permissions as $permission) {
            $has = $this->db->where(array('role_id' => $admin->id, 'permission' => $permission))->count_all_results('role_permissions') > 0;
            if (!$has) {
                $this->db->insert('role_permissions', array('role_id' => $admin->id, 'permission' => $permission));
            }
        }
    }
}
