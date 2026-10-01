<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Auth\PermissionRegistry;

/**
 * Roles and their permissions.
 *
 * - `roles`: one row per role. `slug` is the stable key stored in users.role.
 *   `super_admin` is built in: it holds every permission without any rows in
 *   role_permissions, and the application refuses to edit or delete it.
 *   Exactly one role has is_default = 1 (given to new sign-ups).
 * - `role_permissions`: which "module.action" permission keys a role holds.
 *
 * Existing users keep working: whoever was an 'admin' (the only privileged
 * role before this migration) becomes a super_admin so nobody loses access,
 * and any other role name already in use becomes a role without permissions.
 */
class Migration_Create_roles_tables extends CI_Migration
{
    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'slug' => array('type' => 'VARCHAR', 'constraint' => 50),
            'name' => array('type' => 'VARCHAR', 'constraint' => 100),
            'description' => array('type' => 'VARCHAR', 'constraint' => 255, 'null' => true),
            'is_system' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'is_default' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'created_at' => array('type' => 'DATETIME', 'null' => true),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->dbforge->add_key('id', true);
        $this->dbforge->create_table('roles');
        $this->db->query('CREATE UNIQUE INDEX roles_slug_unique ON roles (slug)');

        $this->dbforge->add_field(array(
            'role_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true),
            'permission' => array('type' => 'VARCHAR', 'constraint' => 100),
        ));
        $this->dbforge->add_key(array('role_id', 'permission'), true);
        $this->dbforge->create_table('role_permissions');

        $now = date('Y-m-d H:i:s');
        $roles = array(
            array('slug' => 'super_admin', 'name' => 'Super admin', 'description' => 'Built in. Has every permission and cannot be changed or deleted.', 'is_system' => 1, 'is_default' => 0),
            array('slug' => 'admin', 'name' => 'Admin', 'description' => 'Manages the application day to day.', 'is_system' => 0, 'is_default' => 0),
            array('slug' => 'user', 'name' => 'User', 'description' => 'Signed-in visitor with no admin access.', 'is_system' => 0, 'is_default' => 1),
        );
        foreach ($roles as $role) {
            $this->db->insert('roles', $role + array('created_at' => $now, 'updated_at' => $now));
        }

        // Grant the default permissions.
        $defaults = array();
        $config = array();
        include APPPATH.'config/permissions.php';
        $defaults = $config['default_role_permissions'] ?? array();

        $files = array_merge(array(APPPATH.'config/permissions.php'), glob(APPPATH.'modules/*/config/permissions.php') ?: array());
        $all = PermissionRegistry::fromFiles($files)->keys();

        foreach ($defaults as $slug => $permissions) {
            $role = $this->db->get_where('roles', array('slug' => $slug))->row();
            if (!$role) {
                continue;
            }
            $granted = in_array('*', $permissions, true) ? $all : array_intersect($permissions, $all);
            foreach ($granted as $permission) {
                $this->db->insert('role_permissions', array('role_id' => $role->id, 'permission' => $permission));
            }
        }

        // Existing users: the old top role becomes super_admin; other role names get a (permission-less) role.
        $this->db->where('role', 'admin')->update('users', array('role' => 'super_admin'));

        foreach ($this->db->select('role')->distinct()->get('users')->result() as $row) {
            if ($row->role !== '' && !$this->db->get_where('roles', array('slug' => $row->role))->row()) {
                $name = ucwords(str_replace('_', ' ', $row->role));
                $this->db->insert('roles', array(
                    'slug' => $row->role, 'name' => $name, 'description' => 'Created from an existing user role.',
                    'is_system' => 0, 'is_default' => 0, 'created_at' => $now, 'updated_at' => $now,
                ));
            }
        }
    }

    public function down()
    {
        $this->db->where('role', 'super_admin')->update('users', array('role' => 'admin'));
        $this->dbforge->drop_table('role_permissions', true);
        $this->dbforge->drop_table('roles', true);
    }
}
