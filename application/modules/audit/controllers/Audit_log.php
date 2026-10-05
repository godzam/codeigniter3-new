<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Audit\Labels;

/**
 * Admin page for the audit log: every add, change and delete (with the
 * content), sign-ins, blocks and unblocks. Read-only on purpose: nothing in
 * the app edits or deletes these rows.
 *
 * Routes (application/config/routes.php), all need audit.view:
 *   GET  /admin/audit          the list with filters
 *   GET  /admin/audit/data     JSON for the table
 *   GET  /admin/audit/{id}     one entry's details (HTML for the dialog)
 *
 * @property Template $template
 * @property Datatable $datatable
 * @property CI_Input $input
 * @property CI_DB_query_builder $db
 */
class Audit_log extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_permission('audit.view');
    }

    public function index()
    {
        $this->load->database();
        $db = $this->db;
        $debug = $db->db_debug;
        $db->db_debug = false;
        $available = $db->table_exists('audit_logs');
        $actions = $entities = [];

        if ($available) {
            $actions = array_column($db->select('action')->distinct()->order_by('action')->get('audit_logs')->result_array(), 'action');
            $entities = array_column($db->select('entity')->distinct()->order_by('entity')->get('audit_logs')->result_array(), 'entity');
        }
        $db->db_debug = $debug;

        $this->template
            ->set_title('Audit log')
            ->set_subtitle('Who added, changed or deleted what, and when. Entries cannot be edited or removed here.')
            ->set_breadcrumbs(['Home' => '', 'Audit log' => null])
            ->add_foot('<script src="'.asset_url('js/audit.js').'"></script>')
            ->render('index', [
                'available' => $available,
                'actions' => $actions,
                'entities' => $entities,
            ], 'admin');
    }

    public function data()
    {
        $action = (string) $this->input->get('f_action');
        $entity = (string) $this->input->get('f_entity');
        $from = $this->date($this->input->get('f_from'));
        $to = $this->date($this->input->get('f_to'));
        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $this->datatable
            ->from(static function ($db) use ($action, $entity, $from, $to) {
                $db->from('audit_logs a');
                if ($action !== '') {
                    $db->where('a.action', $action);
                }
                if ($entity !== '') {
                    $db->where('a.entity', $entity);
                }
                if ($from !== null) {
                    $db->where('a.created_at >=', $from.' 00:00:00');
                }
                if ($to !== null) {
                    $db->where('a.created_at <=', $to.' 23:59:59');
                }
            })
            ->select('a.id, a.created_at, a.actor_id, a.actor_name, a.actor_email, a.ip, a.action, a.entity, a.entity_id, a.label, a.changes')
            ->column('when', 'a.created_at', false)
            ->column('who', 'COALESCE(a.actor_name, a.actor_email, \'\')')
            ->column('action', 'a.action')
            ->column('entity', 'a.entity')
            ->column('record', 'a.label')
            ->column('ip', 'a.ip')
            ->column('details', null)
            ->default_order(['a.id DESC'])
            ->respond(function ($r) use ($e) {
                $action = Labels::action($r['action']);
                $who = $r['actor_name'] !== null || $r['actor_email'] !== null
                    ? '<div class="cell-person">'.user_avatar($r['actor_name'] ?? $r['actor_email']).'<div class="text-truncate"><strong>'.$e($r['actor_name'] ?? $r['actor_email']).'</strong>'
                        .($r['actor_name'] !== null && $r['actor_email'] !== null ? '<div class="small text-body-secondary">'.$e($r['actor_email']).'</div>' : '').'</div></div>'
                    : '<span class="text-body-secondary">System / not signed in</span>';

                return [
                    'when' => '<span class="text-nowrap small">'.$e(date('M j, Y', strtotime($r['created_at']))).'<br><span class="text-body-secondary">'.$e(date('H:i:s', strtotime($r['created_at']))).'</span></span>',
                    'who' => $who,
                    'action' => '<span class="pill pill-'.$e($action['tone']).'">'.$e($action['label']).'</span>',
                    'entity' => $e(Labels::entity($r['entity'])),
                    'record' => $r['label'] !== null ? '<span class="fw-semibold">'.$e($r['label']).'</span>'.($r['entity_id'] !== null ? '<div class="small text-body-secondary">#'.$e($r['entity_id']).'</div>' : '') : '<span class="text-body-secondary">—</span>',
                    'ip' => '<code class="small">'.$e($r['ip'] ?? '').'</code>',
                    'details' => $r['changes'] !== null
                        ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-audit-show="'.(int) $r['id'].'"><i class="bi bi-eye me-1"></i>Details</button>'
                        : '',
                ];
            });
    }

    /**
     * One entry, as HTML for the dialog on the list page.
     */
    public function show($id = 0)
    {
        $this->load->database();
        $row = $this->db->get_where('audit_logs', ['id' => (int) $id])->row_array();

        if (!$row) {
            show_404();
        }

        $this->output->set_header('Cache-Control: no-store');
        $this->load->view('show', [
            'row' => $row,
            'payload' => json_decode((string) $row['changes'], true) ?: [],
        ]);
    }

    /**
     * @param mixed $value
     */
    protected function date($value)
    {
        $value = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
