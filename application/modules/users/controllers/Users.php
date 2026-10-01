<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Auth\RoleRules;

/**
 * Admin page listing the users and letting you change each one's role.
 *
 * Routes (application/config/routes.php):
 *   GET  /admin/users               users.view
 *   POST /admin/users/role/{id}     users.assign_role
 *
 * Guard rails (see RoleRules::assignmentBlocker): only a super admin can
 * grant the super_admin role or change a super admin's role, nobody but a
 * super admin can change their own role, and the last super admin can't be
 * demoted.
 *
 * @property Template $template
 * @property Rbac $rbac
 * @property CI_Input $input
 */
class Users extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_permission('users.view');
        $this->load->helper('form');
    }

    public function index()
    {
        $this->template
            ->set_title('Users')
            ->set_breadcrumbs(['Home' => '', 'Users' => null])
            ->render('index', [
                'users' => $this->rbac->users(),
                'roles' => $this->assignable_roles(),
                'can_assign' => can('users.assign_role'),
                'my_id' => (int) current_user()['id'],
                'viewer_is_super' => is_super_admin(),
            ], 'admin');
    }

    public function role($id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        require_permission('users.assign_role');

        $user = $this->rbac->find_user($id);
        if (!$user) {
            show_404();
        }

        $newRole = (string) $this->input->post('role');
        $roles = array_column($this->rbac->roles(), null, 'slug');

        if (!isset($roles[$newRole])) {
            flash('error', 'That role does not exist.');
            redirect('admin/users');
        }

        $blocker = RoleRules::assignmentBlocker(
            ['id' => (int) current_user()['id'], 'is_super_admin' => is_super_admin()],
            ['id' => (int) $user['id'], 'role' => (string) $user['role']],
            $newRole,
            $this->rbac->super_admin_count()
        );

        if ($blocker !== null) {
            flash('error', $blocker);
            redirect('admin/users');
        }

        if ($newRole !== $user['role']) {
            $this->rbac->assign_role((int) $user['id'], $newRole);
            flash('success', sprintf('%s is now %s.', $user['name'], $roles[$newRole]['name']));
        }

        redirect('admin/users');
    }

    /**
     * Roles offered in the dropdown. super_admin is only offered to a super admin.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function assignable_roles()
    {
        return array_values(array_filter($this->rbac->roles(), static function ($role) {
            return !RoleRules::isSuperAdmin($role['slug']) || is_super_admin();
        }));
    }
}
