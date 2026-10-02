<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Auth\PermissionRegistry;
use App\Auth\RoleRules;

/**
 * Role-based access control: which role the signed-in user has *right now*,
 * which permissions that role holds, and the data access behind the Roles
 * and Users admin pages.
 *
 *   $this->rbac->can('users.view');        // or the can() helper
 *   $this->rbac->current_role();           // 'editor'
 *   $this->rbac->is_super_admin();
 *
 * The role is read from the database on the first check of each request
 * rather than trusted from the session, so changing someone's role (or the
 * permissions of their role) takes effect on their next click instead of
 * their next login. If the roles tables can't be read (database down, or
 * the migration not run yet) nothing is allowed, except for a user whose
 * session says super_admin — so a broken setup can't lock the owner out of
 * fixing it, and never grants anyone else more than they had.
 *
 * `super_admin` holds every permission implicitly: no rows are stored for it
 * and the Roles page never lets it be edited or deleted (see RoleRules).
 *
 * @property CI_Session $session
 */
class Rbac
{
    protected $CI;

    /** @var PermissionRegistry|null */
    protected $registry;

    /** @var string|false|null null = not looked up yet, false = no such user */
    protected $currentRole;

    /** @var array<string, array<int, string>> */
    protected $permissionCache = [];

    /** @var array<string, string>|null slug => display name */
    protected $roleNames;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    // ------------------------------------------------------------------
    // Checks
    // ------------------------------------------------------------------

    public function registry()
    {
        if ($this->registry === null) {
            $files = array_merge(
                [APPPATH.'config/permissions.php'],
                glob(APPPATH.'modules/*/config/permissions.php') ?: [],
                [APPPATH.'config/'.ENVIRONMENT.'/permissions.php']
            );
            $this->registry = PermissionRegistry::fromFiles($files);

            // Modules made with the CRUD generator declare theirs in the database.
            $modules = $this->quietly(function ($db) {
                return $db->select('slug, title, icon')->order_by('id', 'ASC')->get('crud_modules');
            });
            foreach ($modules === false ? [] : $modules->result_array() as $module) {
                $title = $module['title'];
                $this->registry->addGroup($module['slug'], [
                    'label' => $title,
                    'icon' => $module['icon'],
                    'permissions' => [
                        'view' => 'See the '.$title.' list',
                        'create' => 'Add '.$title.' records',
                        'edit' => 'Edit '.$title.' records',
                        'delete' => 'Delete '.$title.' records',
                    ],
                ]);
            }
        }

        return $this->registry;
    }

    /**
     * The signed-in user's role slug as it is in the database now, or null
     * when nobody is signed in or the user no longer exists.
     *
     * @return string|null
     */
    public function current_role()
    {
        if (!is_logged_in()) {
            return null;
        }

        if ($this->currentRole === null) {
            $result = $this->quietly(function ($db) {
                return $db->select('role')->get_where('users', ['id' => $this->CI->session->userdata('user_id')]);
            });

            if ($result === false) {
                // Can't read the database: fall back to what the session says.
                $this->currentRole = (string) $this->CI->session->userdata('user_role');
            } else {
                $row = $result->row();
                $this->currentRole = $row ? (string) $row->role : false;
            }
        }

        return $this->currentRole === false ? null : $this->currentRole;
    }

    public function is_super_admin()
    {
        return RoleRules::isSuperAdmin($this->current_role());
    }

    /**
     * The role itself, or — for the super admin — anything: a role is always
     * allowed if it is the exact role or the user is a super admin.
     */
    public function has_role($role)
    {
        $current = $this->current_role();

        return $current !== null && ($current === (string) $role || RoleRules::isSuperAdmin($current));
    }

    public function can($permission)
    {
        $role = $this->current_role();

        if ($role === null) {
            return false;
        }

        return RoleRules::isSuperAdmin($role) || in_array((string) $permission, $this->permissions_of($role), true);
    }

    /**
     * Permission keys held by a role. For super_admin that is everything the
     * registry knows. Unknown keys left in the table (a module was removed)
     * are ignored.
     *
     * @return array<int, string>
     */
    public function permissions_of($slug)
    {
        $slug = (string) $slug;

        if (RoleRules::isSuperAdmin($slug)) {
            return $this->registry()->keys();
        }

        if (!isset($this->permissionCache[$slug])) {
            $result = $this->quietly(function ($db) use ($slug) {
                return $db->query(
                    'SELECT rp.permission FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.slug = ?',
                    [$slug]
                );
            });

            $stored = $result === false ? [] : array_column($result->result_array(), 'permission');
            $this->permissionCache[$slug] = $this->registry()->filter($stored);
        }

        return $this->permissionCache[$slug];
    }

    /**
     * What the signed-in user may do — used to stop someone from handing out
     * permissions they don't have themselves.
     *
     * @return array<int, string>
     */
    public function my_permissions()
    {
        $role = $this->current_role();

        return $role === null ? [] : $this->permissions_of($role);
    }

    // ------------------------------------------------------------------
    // Roles (data access for the Roles page)
    // ------------------------------------------------------------------

    /**
     * Every role, super_admin first, with how many users hold it and how
     * many permissions it was granted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function roles()
    {
        $rows = $this->db()->query(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM users u WHERE u.role = r.slug) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permissions_count
               FROM roles r
              ORDER BY r.is_system DESC, r.name ASC'
        )->result_array();

        foreach ($rows as &$row) {
            if (RoleRules::isSuperAdmin($row['slug'])) {
                $row['permissions_count'] = count($this->registry()->keys());
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_role($slug)
    {
        $row = $this->db()->get_where('roles', ['slug' => (string) $slug])->row_array();

        return $row ?: null;
    }

    /**
     * Display name of a role slug ("editor" => "Editor"); falls back to a
     * prettified slug when the role table can't be read.
     */
    public function role_label($slug)
    {
        if ($this->roleNames === null) {
            $result = $this->quietly(function ($db) {
                return $db->select('slug, name')->get('roles');
            });
            $this->roleNames = $result === false ? [] : array_column($result->result_array(), 'name', 'slug');
        }

        return $this->roleNames[(string) $slug] ?? ucwords(str_replace('_', ' ', (string) $slug));
    }

    public function default_role_slug()
    {
        $row = $this->quietly(function ($db) {
            return $db->select('slug')->get_where('roles', ['is_default' => 1]);
        });

        $found = $row === false ? null : $row->row();

        return $found ? (string) $found->slug : 'user';
    }

    /**
     * Creates a role with its permissions. Callers validate first (RoleRules).
     *
     * @param array<int, string> $permissions already filtered through the registry
     *
     * @return bool
     */
    public function create_role($slug, $name, $description, array $permissions, $makeDefault)
    {
        $db = $this->db();
        $now = date('Y-m-d H:i:s');

        $db->trans_start();
        $db->insert('roles', [
            'slug' => $slug,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'is_system' => 0,
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $roleId = (int) $db->insert_id();
        $this->write_permissions($roleId, $permissions);
        if ($makeDefault) {
            $this->write_default($roleId);
        }
        $db->trans_complete();
        $this->permissionCache = [];

        return $db->trans_status() !== false;
    }

    /**
     * @param array<int, string>|null $permissions null leaves them untouched
     *
     * @return bool
     */
    public function update_role($slug, $name, $description, $permissions, $makeDefault)
    {
        $db = $this->db();
        $role = $this->find_role($slug);
        if (!$role) {
            return false;
        }

        $db->trans_start();
        $db->where('id', $role['id'])->update('roles', [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($permissions !== null) {
            $this->write_permissions((int) $role['id'], $permissions);
        }
        if ($makeDefault) {
            $this->write_default((int) $role['id']);
        }
        $db->trans_complete();
        $this->permissionCache = [];

        return $db->trans_status() !== false;
    }

    public function delete_role($slug)
    {
        $db = $this->db();
        $role = $this->find_role($slug);
        if (!$role) {
            return false;
        }

        $db->trans_start();
        $db->where('role_id', $role['id'])->delete('role_permissions');
        $db->where('id', $role['id'])->delete('roles');
        $db->trans_complete();
        $this->permissionCache = [];

        return $db->trans_status() !== false;
    }

    // ------------------------------------------------------------------
    // Users (data access for the Users page)
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function users()
    {
        return $this->db()->select('id, name, email, role, created_at')->order_by('id', 'ASC')->get('users')->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_user($id)
    {
        $row = $this->db()->select('id, name, email, role')->get_where('users', ['id' => (int) $id])->row_array();

        return $row ?: null;
    }

    public function users_count($slug)
    {
        return (int) $this->db()->where('role', (string) $slug)->count_all_results('users');
    }

    public function super_admin_count()
    {
        return $this->users_count(RoleRules::SUPER_ADMIN);
    }

    public function assign_role($userId, $slug)
    {
        $this->db()->where('id', (int) $userId)->update('users', ['role' => (string) $slug, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->db()->affected_rows() >= 0;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @param array<int, string> $permissions
     */
    protected function write_permissions($roleId, array $permissions)
    {
        $db = $this->db();
        $db->where('role_id', $roleId)->delete('role_permissions');

        foreach (array_unique($permissions) as $permission) {
            $db->insert('role_permissions', ['role_id' => $roleId, 'permission' => $permission]);
        }
    }

    /**
     * Makes this the one default role (given to new sign-ups).
     */
    protected function write_default($roleId)
    {
        $db = $this->db();
        $db->update('roles', ['is_default' => 0]);
        $db->where('id', $roleId)->update('roles', ['is_default' => 1]);
    }

    /**
     * The shared database connection, opened on first use. (Libraries are
     * autoloaded on every request, including pages that never touch the
     * database, so this isn't done in the constructor.)
     */
    protected function db()
    {
        if (!isset($this->CI->db) || !is_object($this->CI->db) || empty($this->CI->db->conn_id)) {
            $this->CI->load->database();
        }

        return $this->CI->db;
    }

    /**
     * Runs a read with db_debug off so a missing table yields `false`
     * instead of CodeIgniter's error page.
     *
     * @return mixed whatever the callback returned (false when the query failed)
     */
    protected function quietly(callable $query)
    {
        $db = $this->db();
        $debug = $db->db_debug;
        $db->db_debug = false;

        try {
            return $query($db);
        } finally {
            $db->db_debug = $debug;
        }
    }
}
