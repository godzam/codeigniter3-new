<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Auth\RoleRules;

/**
 * Admin pages to create, edit and delete roles and choose which permissions
 * ("module.action", see config/permissions.php) each one holds.
 *
 * Routes (application/config/routes.php):
 *   GET       /admin/roles                 roles.view
 *   GET       /admin/roles/data            roles.view   (JSON for the DataTable)
 *   GET|POST  /admin/roles/create          roles.create
 *   GET|POST  /admin/roles/edit/{slug}     roles.edit   (view-only for super_admin)
 *   POST      /admin/roles/delete/{slug}   roles.delete
 *
 * `super_admin` is built in: it holds every permission and can't be created,
 * edited or deleted. Everyone else may only hand out permissions they hold
 * themselves (RoleRules::disallowedChanges); the checkboxes for the rest are
 * shown disabled and ignored by the server.
 *
 * @property Template $template
 * @property Rbac $rbac
 * @property Datatable $datatable
 * @property CI_Input $input
 */
class Roles extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_login();
        $this->load->helper('form');
    }

    public function index()
    {
        require_permission('roles.view');

        $this->template
            ->set_title('Roles')
            ->set_breadcrumbs(['Home' => '', 'Roles' => null])
            ->render('index', [
                'can_create' => can('roles.create'),
            ], 'admin');
    }

    /**
     * Server-side rows for the Roles table.
     */
    public function data()
    {
        require_permission('roles.view');

        $canEdit = can('roles.edit');
        $canDelete = can('roles.delete');
        $viewerIsSuper = is_super_admin();
        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $this->datatable
            ->from(static function ($db) use ($viewerIsSuper) {
                $db->from('roles r');
                // super_admin is for the developers: other users never see that role.
                if (!$viewerIsSuper) {
                    $db->where('r.slug !=', RoleRules::SUPER_ADMIN);
                }
            })
            ->select(
                'r.slug, r.name, r.description, r.is_system, r.is_default,
                 (SELECT COUNT(*) FROM users u WHERE u.role = r.slug) AS users_count,
                 (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permissions_count'
            )
            ->column('role', 'r.name')
            ->column('key', 'r.slug')
            ->column('users', '(SELECT COUNT(*) FROM users u WHERE u.role = r.slug)', false)
            ->column('permissions', '(SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id)', false, false)
            ->column('actions', null)
            ->default_order(['r.is_system DESC', 'r.name ASC'])
            ->respond(function ($r) use ($canEdit, $canDelete, $e) {
                $locked = (bool) $r['is_system'];

                $role = '<span class="fw-semibold">'.$e($r['name']).'</span>'
                    .($locked ? ' <span class="badge text-bg-danger ms-1"><i class="bi bi-lock-fill"></i> Built in</span>' : '')
                    .($r['is_default'] ? ' <span class="badge text-bg-primary ms-1">Default for sign-ups</span>' : '')
                    .(!empty($r['description']) ? '<div class="small text-body-secondary">'.$e($r['description']).'</div>' : '');

                $actions = '<a href="'.site_url('admin/roles/edit/'.rawurlencode($r['slug'])).'" class="btn btn-sm btn-outline-secondary">'
                    .(($locked || !$canEdit) ? 'View' : 'Edit').'</a>';
                if ($canDelete && !$locked) {
                    $actions .= ' '.form_open('admin/roles/delete/'.rawurlencode($r['slug']), ['class' => 'd-inline', 'data-confirm' => 'Delete the role "'.$r['name'].'"?'])
                        .'<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>'
                        .form_close();
                }

                return [
                    'role' => $role,
                    'key' => '<code>'.$e($r['slug']).'</code>',
                    'users' => (int) $r['users_count'],
                    'permissions' => $locked ? 'All' : (int) $r['permissions_count'],
                    'actions' => $actions,
                ];
            });
    }

    public function create()
    {
        require_permission('roles.create');

        if ($this->input->method() === 'post') {
            $this->store();

            return;
        }

        $this->form(null, ['name' => '', 'slug' => '', 'description' => '', 'is_default' => false], [], []);
    }

    public function edit($slug = '')
    {
        require_permission('roles.view');

        $role = $this->rbac->find_role($slug);
        if ($role && RoleRules::isSuperAdmin($role['slug']) && !is_super_admin()) {
            $role = null; // developers only: looks like it does not exist
        }
        if (!$role) {
            show_404();
        }

        // Built-in role: show it, locked.
        if (RoleRules::editBlocker($role) !== null) {
            $this->form($role, $role, $this->rbac->registry()->keys(), []);

            return;
        }

        require_permission('roles.edit');

        if ($this->input->method() === 'post') {
            $this->update($role);

            return;
        }

        $this->form($role, $role, $this->rbac->permissions_of($role['slug']), []);
    }

    public function delete($slug = '')
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        require_permission('roles.delete');

        $role = $this->rbac->find_role($slug);
        if (!$role) {
            show_404();
        }

        $blocker = RoleRules::deleteBlocker($role, $this->rbac->users_count($role['slug']));
        if ($blocker !== null) {
            flash('error', $blocker);
            redirect('admin/roles');
        }

        $this->rbac->delete_role($role['slug']);
        flash('success', sprintf('Role "%s" deleted.', $role['name']));
        redirect('admin/roles');
    }

    // ------------------------------------------------------------------

    protected function store()
    {
        $name = trim((string) $this->input->post('name'));
        $slug = trim((string) $this->input->post('slug'));
        $slug = $slug !== '' ? $slug : RoleRules::slugFromName($name);
        $description = trim((string) $this->input->post('description'));
        $makeDefault = $this->input->post('is_default') === '1';
        $permissions = $this->requested_permissions([]);

        $errors = $this->validate($name, $description);
        if (($error = RoleRules::slugError($slug)) !== null) {
            $errors['slug'] = $error;
        } elseif ($this->rbac->find_role($slug)) {
            $errors['slug'] = 'A role with this key already exists.';
        }

        if ($errors) {
            $this->form(null, ['name' => $name, 'slug' => $slug, 'description' => $description, 'is_default' => $makeDefault], $permissions, $errors);

            return;
        }

        if (!$this->rbac->create_role($slug, $name, $description, $permissions, $makeDefault)) {
            flash('error', 'The role could not be saved.');
            redirect('admin/roles');
        }

        flash('success', sprintf('Role "%s" created.', $name));
        redirect('admin/roles');
    }

    /**
     * @param array<string, mixed> $role
     */
    protected function update(array $role)
    {
        $name = trim((string) $this->input->post('name'));
        $description = trim((string) $this->input->post('description'));
        $makeDefault = $this->input->post('is_default') === '1';
        $old = $this->rbac->permissions_of($role['slug']);
        $permissions = $this->requested_permissions($old);

        $errors = $this->validate($name, $description);

        // Belt and braces: requested_permissions() already leaves alone what
        // the user may not touch, so this should never trigger.
        $denied = RoleRules::disallowedChanges($old, $permissions, $this->rbac->my_permissions(), is_super_admin());
        if ($denied) {
            $errors['permissions'] = 'You cannot grant or revoke permissions you do not have yourself.';
        }

        if ($errors) {
            $this->form($role, ['name' => $name, 'slug' => $role['slug'], 'description' => $description, 'is_default' => $makeDefault || !empty($role['is_default'])] + $role, $permissions, $errors);

            return;
        }

        if (!$this->rbac->update_role($role['slug'], $name, $description, $permissions, $makeDefault)) {
            flash('error', 'The role could not be saved.');
            redirect('admin/roles');
        }

        flash('success', sprintf('Role "%s" saved.', $name));
        redirect('admin/roles');
    }

    /**
     * The permissions the form asks for, limited to what the signed-in user
     * is allowed to change: anything they don't hold keeps the role's current
     * state, so an admin can neither grant nor revoke it.
     *
     * @param array<int, string> $current permissions the role has now
     *
     * @return array<int, string>
     */
    protected function requested_permissions(array $current)
    {
        $registry = $this->rbac->registry();
        $submitted = $registry->filter((array) $this->input->post('permissions'));
        $changeable = is_super_admin() ? $registry->keys() : $this->rbac->my_permissions();

        return $registry->filter(array_merge(
            array_intersect($submitted, $changeable),
            array_diff($current, $changeable)
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function validate($name, $description)
    {
        $errors = [];

        if (($error = RoleRules::nameError($name)) !== null) {
            $errors['name'] = $error;
        }
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'The description must be at most 255 characters.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $role   the stored role (null when creating)
     * @param array<string, mixed>      $values what the form fields show
     * @param array<int, string>        $granted
     * @param array<string, string>     $errors
     */
    protected function form($role, array $values, array $granted, array $errors)
    {
        $locked = $role !== null && RoleRules::editBlocker($role) !== null;
        $title = $role === null ? 'New role' : ($locked ? $role['name'] : 'Edit role: '.$role['name']);

        $this->template
            ->set_title($title)
            ->set_breadcrumbs(['Home' => '', 'Roles' => 'admin/roles', $title => null])
            ->add_foot('<script src="'.asset_url('js/roles.js').'"></script>')
            ->render('form', [
                'role' => $role,
                'values' => $values,
                'granted' => $granted,
                'errors' => $errors,
                'locked' => $locked,
                'groups' => $this->rbac->registry()->groups(),
                'all_keys' => $this->rbac->registry()->keys(),
                'changeable' => is_super_admin() ? $this->rbac->registry()->keys() : $this->rbac->my_permissions(),
                'users_count' => $role === null ? 0 : $this->rbac->users_count($role['slug']),
            ], 'admin');
    }
}
