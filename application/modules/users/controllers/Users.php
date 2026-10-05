<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Auth\RoleRules;

/**
 * Admin page listing the users and letting you change each one's role.
 *
 * Routes (application/config/routes.php):
 *   GET  /admin/users               users.view
 *   GET  /admin/users/data          users.view   (JSON for the DataTable)
 *   POST /admin/users/role/{id}     users.assign_role
 *
 * Guard rails (see RoleRules::assignmentBlocker): only a super admin can
 * grant the super_admin role or change a super admin's role, nobody but a
 * super admin can change their own role, and the last super admin can't be
 * demoted.
 *
 * @property Template $template
 * @property Rbac $rbac
 * @property Datatable $datatable
 * @property Audit $audit
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
            ->set_subtitle('Everyone who can sign in, and the role each person has.')
            ->set_breadcrumbs(['Home' => '', 'Users' => null])
            ->render('index', ['can_assign' => can('users.assign_role')], 'admin');
    }

    /**
     * Server-side rows for the Users table.
     */
    public function data()
    {
        $roles = $this->assignable_roles();
        $names = array_column($this->rbac->roles(), 'name', 'slug');
        $canAssign = can('users.assign_role');
        $myId = (int) current_user()['id'];
        $viewerIsSuper = is_super_admin();
        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $this->datatable
            ->from(static function ($db) use ($viewerIsSuper) {
                $db->from('users u');
                // super_admin is for the developers: other users never see those accounts.
                if (!$viewerIsSuper) {
                    $db->where('u.role !=', RoleRules::SUPER_ADMIN);
                }
            })
            ->select('u.id, u.name, u.email, u.role, u.created_at')
            ->column('name', 'u.name')
            ->column('email', 'u.email')
            ->column('role', 'u.role')
            ->column('joined', 'u.created_at', false)
            ->default_order(['u.id ASC'])
            ->respond(function ($u) use ($roles, $names, $canAssign, $myId, $viewerIsSuper, $e) {
                $isSuper = $u['role'] === RoleRules::SUPER_ADMIN;
                // Only a super admin can change a super admin's role, or their own.
                $locked = !$viewerIsSuper && ($isSuper || (int) $u['id'] === $myId);

                $name = '<div class="cell-person">'.user_avatar($u['name'])
                    .'<div class="text-truncate"><strong>'.$e($u['name']).'</strong>'
                    .((int) $u['id'] === $myId ? ' <span class="pill pill-primary ms-1">you</span>' : '').'</div></div>';

                if ($canAssign && !$locked) {
                    $options = '';
                    if (!isset($names[$u['role']])) {
                        $options .= '<option value="'.$e($u['role']).'" selected>'.$e($u['role']).'</option>';
                    }
                    foreach ($roles as $r) {
                        $options .= '<option value="'.$e($r['slug']).'"'.($r['slug'] === $u['role'] ? ' selected' : '').'>'.$e($r['name']).'</option>';
                    }
                    $role = form_open('admin/users/role/'.(int) $u['id'], ['class' => 'd-flex gap-2'])
                        .'<select name="role" class="form-select form-select-sm" aria-label="Role for '.$e($u['name']).'">'.$options.'</select>'
                        .'<button type="submit" class="btn btn-sm btn-outline-primary">Save</button>'
                        .form_close();
                } else {
                    $role = '<span class="pill '.($isSuper ? 'pill-danger' : 'pill-secondary').'">'.$e($names[$u['role']] ?? $u['role']).'</span>'
                        .($canAssign && $locked ? ' <i class="bi bi-lock-fill text-body-secondary ms-1" title="You cannot change this role"></i>' : '');
                }

                return [
                    'name' => $name,
                    'email' => '<span class="text-body-secondary">'.$e($u['email']).'</span>',
                    'role' => $role,
                    'joined' => '<span class="text-body-secondary small">'.$e(substr((string) $u['created_at'], 0, 10)).'</span>',
                ];
            });
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
            $this->audit->updated('users', $user['id'], $user['name'].' ('.$user['email'].')', ['role' => $user['role']], ['role' => $newRole]);
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
