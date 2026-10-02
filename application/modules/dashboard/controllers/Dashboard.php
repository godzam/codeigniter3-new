<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Landing page after login, rendered in the admin layout: a greeting, a few
 * numbers, sign-ups of the last two weeks and shortcuts. What is shown
 * depends on the signed-in user's permissions, and nothing here breaks if a
 * table is missing (every query is allowed to fail quietly).
 *
 * @property Template $template
 * @property Rbac $rbac
 * @property Crud_store $crud_store
 * @property CI_DB_query_builder $db
 */
class Dashboard extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_login();
    }

    public function index()
    {
        $canUsers = can('users.view');

        $data = [
            'user' => current_user(),
            'greeting' => $this->greeting(),
            'can_users' => $canUsers,
            'can_roles' => can('roles.view'),
            'modules' => count($this->crud_store->menu_entries()),
            'permissions' => is_super_admin() ? 'All' : count($this->rbac->my_permissions()),
            'total_users' => 0,
            'new_users' => 0,
            'roles_count' => 0,
            'days' => [],
            'recent' => [],
            'role_names' => [],
        ];

        if ($canUsers || can('roles.view')) {
            $this->load->database();
            $db = $this->db;
            $debug = $db->db_debug;
            $db->db_debug = false;

            try {
                if ($canUsers) {
                    $data['total_users'] = (int) $db->count_all('users');
                    $data['new_users'] = (int) $db->where('created_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')))->count_all_results('users');
                    $data['days'] = $this->signups_by_day(14);

                    $recent = $db->select('name, email, role, created_at')->order_by('id', 'DESC')->limit(6)->get('users');
                    $data['recent'] = $recent ? $recent->result_array() : [];
                    $data['role_names'] = array_combine(
                        array_unique(array_column($data['recent'], 'role')),
                        array_map([$this->rbac, 'role_label'], array_unique(array_column($data['recent'], 'role')))
                    ) ?: [];
                }
                if (can('roles.view')) {
                    $data['roles_count'] = (int) $db->count_all('roles');
                }
            } finally {
                $db->db_debug = $debug;
            }
        }

        $this->template
            ->set_title('Dashboard')
            ->set_breadcrumbs(['Home' => '', 'Dashboard' => null])
            ->render('index', $data, 'admin');
    }

    protected function greeting()
    {
        $hour = (int) date('G');

        if ($hour < 11) {
            return 'Good morning';
        }

        return $hour < 15 ? 'Good afternoon' : ($hour < 19 ? 'Good evening' : 'Good night');
    }

    /**
     * New users per day for the last $days days, oldest first, zeros included.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    protected function signups_by_day($days)
    {
        $result = $this->db->query(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE created_at >= ? GROUP BY DATE(created_at)',
            [date('Y-m-d 00:00:00', strtotime('-'.($days - 1).' days'))]
        );
        $counts = $result ? array_column($result->result_array(), 'c', 'd') : [];

        $out = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $ts = strtotime('-'.$i.' days');
            $key = date('Y-m-d', $ts);
            $out[] = ['date' => $key, 'label' => date('j', $ts), 'long' => date('j M', $ts), 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $out;
    }
}
