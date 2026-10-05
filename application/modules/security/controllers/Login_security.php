<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin page for the login blocks: which IP addresses and user accounts are
 * blocked (or being watched after wrong passwords), and a button to lift any
 * of them. The rule itself is in the Loginguard library.
 *
 * Routes (application/config/routes.php):
 *   GET   /admin/security                 security.view
 *   GET   /admin/security/data            security.view   (JSON for the table)
 *   POST  /admin/security/unblock/{id}    security.unblock
 *
 * @property Template $template
 * @property Loginguard $loginguard
 * @property Datatable $datatable
 * @property Audit $audit
 * @property CI_Input $input
 * @property CI_DB_query_builder $db
 */
class Login_security extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_permission('security.view');
        $this->load->helper('form');
        $this->load->library('loginguard');
    }

    public function index()
    {
        $policy = $this->loginguard->policy();
        $blocked = ['ip' => 0, 'user' => 0];

        if ($this->loginguard->available()) {
            $this->load->database();
            $rows = $this->db->select('type, COUNT(*) AS n', false)
                ->where('locked_until >', date('Y-m-d H:i:s'))
                ->group_by('type')
                ->get('login_locks')->result_array();
            foreach ($rows as $row) {
                $blocked[$row['type']] = (int) $row['n'];
            }
        }

        $this->template
            ->set_title('Login security')
            ->set_subtitle('Who is blocked after wrong passwords, and a way to let them back in.')
            ->set_breadcrumbs(['Home' => '', 'Login security' => null])
            ->render('index', [
                'available' => $this->loginguard->available(),
                'policy' => $policy,
                'guard' => $this->loginguard,
                'blocked' => $blocked,
                'can_unblock' => can('security.unblock'),
                'can_settings' => can('settings.manage'),
            ], 'admin');
    }

    /**
     * Server-side rows for the table.
     */
    public function data()
    {
        $now = date('Y-m-d H:i:s');
        $status = (string) $this->input->get('f_status');
        $type = (string) $this->input->get('f_type');
        $canUnblock = can('security.unblock');
        $max = $this->loginguard->policy()->maxAttempts();
        $strikesMax = $this->loginguard->policy()->strikesForLongBlock();
        $guard = $this->loginguard;
        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $this->datatable
            ->from(static function ($db) use ($now, $status, $type) {
                $db->from('login_locks l');
                $db->join('users u', "u.id = CAST(l.subject AS UNSIGNED) AND l.type = 'user'", 'left', false);

                if ($status === 'blocked' || $status === '') {
                    $db->where('l.locked_until >', $now);
                } elseif ($status === 'watching') {
                    $db->group_start()->where('l.locked_until IS NULL', null, false)->or_where('l.locked_until <=', $now)->group_end();
                    $db->group_start()->where('l.fails >', 0)->or_where('l.strikes >', 0)->group_end();
                }
                if (in_array($type, ['ip', 'user'], true)) {
                    $db->where('l.type', $type);
                }
            })
            ->select('l.id, l.type, l.subject, l.fails, l.strikes, l.locked_until, l.last_fail_at, u.name AS user_name, u.email AS user_email')
            ->column('type', 'l.type', false)
            ->column('who', 'COALESCE(u.email, l.subject)')
            ->column('status', 'l.locked_until', false)
            ->column('strikes', 'l.strikes', false)
            ->column('last', 'l.last_fail_at', false)
            ->column('actions', null)
            ->default_order(['l.locked_until DESC', 'l.updated_at DESC'])
            ->respond(function ($r) use ($now, $canUnblock, $max, $strikesMax, $guard, $e) {
                $isIp = $r['type'] === 'ip';
                $until = $r['locked_until'] ? strtotime($r['locked_until']) : 0;
                $left = $until - strtotime($now);

                if ($isIp) {
                    $who = '<code>'.$e($r['subject']).'</code>';
                } elseif ($r['user_email'] !== null) {
                    $who = '<div class="cell-person">'.user_avatar($r['user_name']).'<div class="text-truncate"><strong>'.$e($r['user_name']).'</strong><div class="small text-body-secondary">'.$e($r['user_email']).'</div></div></div>';
                } else {
                    $who = '<span class="text-body-secondary">User #'.$e($r['subject']).' (deleted)</span>';
                }

                if ($left > 0) {
                    $status = '<span class="pill pill-danger"><i class="bi bi-slash-circle"></i>Blocked</span>'
                        .'<div class="small text-body-secondary mt-1">'.$e($guard->human($left)).' left · until '.$e(date('M j, H:i', $until)).'</div>';
                } elseif ((int) $r['fails'] > 0 || (int) $r['strikes'] > 0) {
                    $status = '<span class="pill pill-warning">Watching</span>'
                        .'<div class="small text-body-secondary mt-1">'.(int) $r['fails'].' of '.$max.' wrong passwords</div>';
                } else {
                    $status = '<span class="pill pill-secondary">Clear</span>';
                }

                $actions = '';
                if ($canUnblock) {
                    $label = $isIp ? 'IP '.$r['subject'] : ($r['user_email'] ?? 'user #'.$r['subject']);
                    $actions = form_open('admin/security/unblock/'.(int) $r['id'], ['class' => 'd-inline', 'data-confirm' => 'Unblock '.$label.'?'])
                        .'<button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-unlock me-1"></i>Unblock</button>'
                        .form_close();
                }

                return [
                    'type' => $isIp
                        ? '<span class="pill pill-secondary"><i class="bi bi-globe2"></i>IP address</span>'
                        : '<span class="pill pill-primary"><i class="bi bi-person"></i>User</span>',
                    'who' => $who,
                    'status' => $status,
                    'strikes' => (int) $r['strikes'] > 0 ? '<span title="Blocks in a row; the long block starts at '.$strikesMax.'">'.(int) $r['strikes'].' of '.$strikesMax.'</span>' : '<span class="text-body-secondary">0</span>',
                    'last' => $r['last_fail_at'] ? '<span class="text-nowrap small">'.$e(date('M j, H:i', strtotime($r['last_fail_at']))).'</span>' : '<span class="text-body-secondary">—</span>',
                    'actions' => $actions,
                ];
            });
    }

    public function unblock($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        require_permission('security.unblock');

        $done = $this->loginguard->unblock($id);

        if ($done === null) {
            flash('error', 'That block no longer exists.');
            redirect('admin/security');
        }

        $this->audit->event('login.unblocked', 'security', $done['type'].':'.$done['subject'], $done['label']);
        flash('success', $done['label'].' can sign in again.');
        redirect('admin/security');
    }
}
