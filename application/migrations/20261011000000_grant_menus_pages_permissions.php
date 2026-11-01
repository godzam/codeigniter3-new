<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Gives the built-in `admin` role the menu builder and page builder permissions, like the
 * security and audit ones, so existing sites do not need a visit to the Roles page.
 *
 * `pages.html` (raw HTML in pages, which can run scripts for every visitor) is deliberately
 * NOT granted: only super_admin has it until somebody decides to give it to a role.
 */
class Migration_Grant_menus_pages_permissions extends CI_Migration
{
    private $permissions = array('menus.view', 'menus.create', 'menus.edit', 'menus.delete', 'pages.view', 'pages.create', 'pages.edit', 'pages.delete');

    public function up()
    {
        $admin = $this->db->get_where('roles', array('slug' => 'admin'))->row();
        if (!$admin) {
            return;
        }

        foreach ($this->permissions as $permission) {
            $exists = $this->db->where(array('role_id' => $admin->id, 'permission' => $permission))->count_all_results('role_permissions') > 0;
            if (!$exists) {
                $this->db->insert('role_permissions', array('role_id' => $admin->id, 'permission' => $permission));
            }
        }
    }

    public function down()
    {
        $this->db->where_in('permission', array_merge($this->permissions, array('pages.html')))->delete('role_permissions');
    }
}
