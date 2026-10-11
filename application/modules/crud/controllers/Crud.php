<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Crud\FieldTypes;
use App\Crud\RecordValidator;
use App\Crud\Uploader;
use App\DataTables\Params;
use App\Export\Cell;
use App\Export\Exporter;

/**
 * The pages of every module made with the CRUD generator. One controller
 * serves them all; the module comes from the URL and its definition from the
 * database.
 *
 *   GET   /admin/c/{slug}                 list (DataTable) + add/edit modal   {slug}.view
 *   GET   /admin/c/{slug}/data            JSON rows for the list              {slug}.view
 *   GET   /admin/c/{slug}/form[/{id}]     the form shown in the modal         {slug}.create / {slug}.edit
 *   POST  /admin/c/{slug}/save            create or update (id in the body)   {slug}.create / {slug}.edit
 *   POST  /admin/c/{slug}/delete/{id}     delete                              {slug}.delete
 *   GET   /admin/c/{slug}/export/{fmt}    csv | xlsx | pdf download           {slug}.export (and the module offers it)
 *
 * Save and delete answer with JSON {ok, message, errors?, csrf}; the page
 * script (assets/js/crud.js) sends the new CSRF token back with the next request.
 *
 * @property Template $template
 * @property Crud_store $crud_store
 * @property Crud_rules $crud_rules
 * @property Audit $audit
 * @property Sharedcache $sharedcache
 * @property Datatable $datatable
 * @property CI_Input $input
 * @property CI_Output $output
 * @property CI_Security $security
 */
class Crud extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_login();
        $this->load->helper('form');
        $this->load->library('crud_rules');
    }

    public function index($slug = '')
    {
        $module = $this->module($slug, 'view');

        $this->template
            ->set_title($module['title'])
            ->set_subtitle('Add, edit and remove '.mb_strtolower($module['title']).' records.')
            ->set_breadcrumbs(['Home' => '', $module['title'] => null])
            ->add_foot('<script src="'.asset_url('js/crud.js').'"></script>')
            ->render('index', [
                'module' => $module,
                'can_create' => can($module['slug'].'.create'),
                'can_edit' => can($module['slug'].'.edit'),
                'can_delete' => can($module['slug'].'.delete'),
                'export_formats' => $this->export_formats($module),
                'csrf_name' => $this->security->get_csrf_token_name(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ], 'admin');
    }

    public function data($slug = '')
    {
        $module = $this->module($slug, 'view');
        $store = $this->crud_store;
        $canEdit = can($module['slug'].'.edit');
        $canDelete = can($module['slug'].'.delete');

        $listed = array_values(array_filter($module['fields'], static function ($f) {
            return !empty($f['list']);
        }));

        $select = [$store->col('id')];
        foreach ($listed as $f) {
            $select[] = $store->col($f['name']);
        }

        $table = $this->datatable
            ->from(static function ($db) use ($module) {
                $db->from($module['table'].' t');
            })
            ->select(implode(', ', $select));

        foreach ($listed as $f) {
            $plain = !FieldTypes::isUpload($f['type']) && $f['type'] !== FieldTypes::MULTISELECT;
            $table->column($f['name'], $store->col($f['name']), !empty($f['search']), $plain);
        }
        $table->column('_actions', null)->default_order([$store->col('id').' DESC']);

        // Labels of dropdown values for the whole page, in one query per field.
        $labels = [];
        $prepare = static function (array $rows) use ($store, $listed, &$labels) {
            foreach ($listed as $f) {
                if (!FieldTypes::hasOptions($f['type'])) {
                    continue;
                }
                $values = [];
                foreach ($rows as $row) {
                    $raw = (string) ($row[$f['name']] ?? '');
                    $values = array_merge($values, $f['type'] === FieldTypes::MULTISELECT ? (array) json_decode($raw, true) : [$raw]);
                }
                $labels[$f['name']] = $store->labels($f, array_map('strval', $values));
            }
        };

        $table->respond(function ($row) use ($listed, &$labels, $canEdit, $canDelete) {
            $cells = [];
            foreach ($listed as $f) {
                $cells[$f['name']] = $this->cell($f, $row[$f['name']] ?? null, $labels[$f['name']] ?? []);
            }

            $actions = '';
            if ($canEdit) {
                $actions .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-crud-edit="'.(int) $row['id'].'" aria-label="Edit"><i class="bi bi-pencil"></i></button> ';
            }
            if ($canDelete) {
                $actions .= '<button type="button" class="btn btn-sm btn-outline-danger" data-crud-delete="'.(int) $row['id'].'" aria-label="Delete"><i class="bi bi-trash"></i></button>';
            }
            $cells['_actions'] = $actions;

            return $cells;
        }, $prepare);
    }


    /**
     * Downloads the list as CSV, Excel or PDF: the rows the search box matches,
     * oldest first. The file is built from chunks of 1,000 rows (keyset paging),
     * so a big table does not fill the server's memory; CSV is sent while it is
     * being read. A match larger than the format's limit is refused up front
     * with a hint, before any of the file is sent.
     */
    public function export($slug = '', $format = '')
    {
        $module = $this->module($slug, 'export');
        $formats = $this->export_formats($module);
        if (!isset($formats[$format])) {
            abort(404);
        }

        $store = $this->crud_store;
        $fields = array_values(array_filter($module['fields'], static function ($f) {
            return $f['type'] !== FieldTypes::PASSWORD;
        }));

        $select = [$store->col('id')];
        foreach ($fields as $f) {
            $select[] = $store->col($f['name']);
        }
        $select[] = $store->col('created_at');

        $table = $this->datatable
            ->from(static function ($db) use ($module) {
                $db->from($module['table'].' t');
            })
            ->select(implode(', ', $select));
        foreach ($fields as $f) {
            if (!FieldTypes::isUpload($f['type']) && $f['type'] !== FieldTypes::MULTISELECT) {
                $table->column($f['name'], $store->col($f['name']), !empty($f['search']), false);
            }
        }

        $terms = Params::fromRequest(['search' => ['value' => (string) $this->input->get('search')]], [])->terms();
        $limit = Exporter::limit($format);

        if ($table->count_matches($terms, $limit + 1) > $limit) {
            flash('error', 'Too many records for one '.strtoupper($format).' file (the limit is '.number_format($limit).'). Narrow it down with the search box'.($format !== 'csv' && isset($formats['csv']) ? ' or download CSV instead' : '').'.');
            redirect('admin/c/'.$module['slug']);
        }

        // One export at a time per person: it keeps one user (or one bug) from tying up every PHP worker.
        $lock = $this->export_lock();
        if ($lock === false) {
            flash('error', 'Another export of yours is still running. Wait for it to finish.');
            redirect('admin/c/'.$module['slug']);
        }

        @set_time_limit((int) (getenv('EXPORT_TIME_LIMIT') ?: 300));
        $labels = array_merge(['ID'], array_column($fields, 'label'));
        $widths = array_merge([8], array_map(static function ($f) {
            return $f['type'] === FieldTypes::TEXTAREA ? 40 : ($f['type'] === FieldTypes::NUMBER ? 14 : 22);
        }, $fields));
        $labels[] = 'Created';
        $widths[] = 19;

        $feed = function ($push) use ($table, $terms, $fields, $store, $limit) {
            $table->each_chunk($terms, $store->col('id'), 'id', function (array $rows) use ($push, $fields, $store) {
                $labelsFor = [];
                foreach ($fields as $f) {
                    if (!FieldTypes::hasOptions($f['type'])) {
                        continue;
                    }
                    $values = [];
                    foreach ($rows as $row) {
                        $raw = (string) ($row[$f['name']] ?? '');
                        $values = array_merge($values, $f['type'] === FieldTypes::MULTISELECT ? (array) json_decode($raw, true) : [$raw]);
                    }
                    $labelsFor[$f['name']] = $store->labels($f, array_map('strval', $values));
                }

                $out = [];
                foreach ($rows as $row) {
                    $line = [(int) $row['id']];
                    foreach ($fields as $f) {
                        $line[] = Cell::value($f, $row[$f['name']] ?? null, $labelsFor[$f['name']] ?? []);
                    }
                    $line[] = $row['created_at'] ?? '';
                    $out[] = $line;
                }
                $push($out);
            }, 1000, $limit);
        };

        $name = Exporter::filename($module['slug'], $format);
        $written = 0;

        try {
            if ($format === 'csv') {
                $this->send_headers($format, $name);
                $out = fopen('php://output', 'wb');
                $written = Exporter::csv($out, $labels, $feed);
                fclose($out);
            } elseif ($format === 'xlsx') {
                [$path, $written] = Exporter::xlsx($module['title'], $labels, $widths, $feed);
                $this->send_headers($format, $name, filesize($path));
                readfile($path);
                @unlink($path);
            } else {
                [$bytes, $written] = Exporter::pdf($module['title'], 'Exported '.date('Y-m-d H:i').' · '.(count($terms) ? 'search: '.implode(' ', $terms) : 'all records'), $labels, $feed);
                $this->send_headers($format, $name, strlen($bytes));
                echo $bytes;
            }
        } finally {
            $this->export_unlock($lock);
        }

        $this->audit->event('export', $module['slug'], null, $module['title'], [
            'format' => $format,
            'rows' => $written,
            'search' => implode(' ', $terms),
        ]);
        exit;
    }

    /**
     * The form for the modal: empty for a new record, filled for an edit.
     */
    public function form($slug = '', $id = null)
    {
        $module = $this->module($slug, $id === null ? 'create' : 'edit');
        $row = [];

        if ($id !== null) {
            $row = $this->crud_store->find_record($module, $id);
            if (!$row) {
                show_404();
            }
        }

        $choices = [];
        foreach ($module['fields'] as $f) {
            if (FieldTypes::hasOptions($f['type'])) {
                $choices[$f['name']] = $this->crud_store->choices($f);
            }
        }

        $this->output->set_header('Cache-Control: no-store');
        $this->load->view('form', [
            'module' => $module,
            'row' => $row,
            'choices' => $choices,
            'csrf_name' => $this->security->get_csrf_token_name(),
            'csrf_hash' => $this->security->get_csrf_hash(),
        ]);
    }

    public function save($slug = '')
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $id = (int) $this->input->post('id');
        $module = $this->module($slug, $id > 0 ? 'edit' : 'create');
        $store = $this->crud_store;

        $existing = null;
        if ($id > 0) {
            $existing = $store->find_record($module, $id);
            if (!$existing) {
                $this->json(['ok' => false, 'message' => 'That record no longer exists.'], 404);

                return;
            }
        }

        // Which upload fields get a new file, or are cleared.
        $uploads = [];
        foreach ($module['fields'] as $f) {
            if (!FieldTypes::isUpload($f['type'])) {
                continue;
            }
            $file = $_FILES[$f['name']] ?? null;
            if (is_array($file) && !is_array($file['error'] ?? null) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploads[$f['name']] = 'new';
            } elseif ($this->input->post($f['name'].'__remove')) {
                $uploads[$f['name']] = 'remove';
            }
        }

        $result = RecordValidator::validate(
            $module,
            (array) $this->input->post(null, false),
            $existing,
            $uploads,
            function ($field, $values) use ($store) {
                return $store->existing_values($field, $values);
            },
            function ($column, $value, $ignoreId) use ($store, $module) {
                return $store->value_taken($module, $column, $value, $ignoreId);
            },
            function ($field, $value, $input) use ($module) {
                return $this->crud_rules->check($module, $field, $value, $input);
            }
        );
        $values = $result['values'];
        $errors = $result['errors'];

        // Store new files only when everything else is fine.
        $uploader = $store->uploader();
        $stored = [];
        if ($errors === []) {
            foreach ($module['fields'] as $f) {
                if (($uploads[$f['name']] ?? null) !== 'new') {
                    continue;
                }
                $saved = $uploader->store($_FILES[$f['name']], $f, $module['slug']);
                if ($saved['error'] !== null) {
                    $errors[$f['name']] = $saved['error'];
                } else {
                    $stored[] = $saved['path'];
                    $values[$f['name']] = $saved['path'];
                }
            }
        }

        if ($errors !== []) {
            foreach ($stored as $path) {
                $uploader->delete($path);
            }
            $this->json(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);

            return;
        }

        foreach ($uploads as $name => $change) {
            if ($change === 'remove') {
                $values[$name] = null;
            }
        }

        if ($existing === null) {
            $newId = $store->insert_record($module, $values);
            $row = ['id' => $newId] + $values;
            $this->audit->created($module['slug'], $newId, $this->record_label($module, $row), $this->audit_row($row), $this->secret_fields($module));
        } else {
            $store->update_record($module, $id, $values);
            foreach ($uploads as $name => $change) {
                $uploader->delete((string) ($existing[$name] ?? ''));
            }
            $this->audit->updated($module['slug'], $id, $this->record_label($module, $values + $existing), $this->audit_row($existing), $this->audit_row($values + $existing), $this->secret_fields($module));
        }

        $this->json(['ok' => true, 'message' => $existing === null ? 'Record added.' : 'Record saved.']);
    }

    public function delete($slug = '', $id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $module = $this->module($slug, 'delete');
        $row = $this->crud_store->find_record($module, $id);
        if (!$row) {
            $this->json(['ok' => false, 'message' => 'That record no longer exists.'], 404);

            return;
        }

        $this->crud_store->delete_record($module, $id);
        $this->audit->deleted($module['slug'], $id, $this->record_label($module, $row), $this->audit_row($row), $this->secret_fields($module));

        $uploader = $this->crud_store->uploader();
        foreach ($module['fields'] as $f) {
            if (FieldTypes::isUpload($f['type'])) {
                $uploader->delete((string) ($row[$f['name']] ?? ''));
            }
        }

        $this->json(['ok' => true, 'message' => 'Record deleted.']);
    }

    /**
     * A record without the timestamps, which only add noise to the audit log.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    protected function audit_row(array $row)
    {
        unset($row['created_at'], $row['updated_at']);

        return $row;
    }

    /**
     * Columns that hold a secret (password fields): their values are never logged.
     *
     * @param array<string, mixed> $module
     *
     * @return array<int, string>
     */
    protected function secret_fields(array $module)
    {
        return array_column(array_filter($module['fields'], static function ($f) {
            return $f['type'] === FieldTypes::PASSWORD;
        }), 'name');
    }

    /**
     * What the audit log calls a record: its first listed text value, or "Title #id".
     *
     * @param array<string, mixed> $module
     * @param array<string, mixed> $row
     */
    protected function record_label(array $module, array $row)
    {
        foreach ($module['fields'] as $f) {
            if (!empty($f['list']) && in_array($f['type'], [FieldTypes::TEXT, FieldTypes::EMAIL, FieldTypes::TEXTAREA, FieldTypes::NUMBER, FieldTypes::DATE], true)
                && isset($row[$f['name']]) && trim((string) $row[$f['name']]) !== '') {
                return mb_strimwidth(trim((string) $row[$f['name']]), 0, 80, '…');
            }
        }

        return $module['title'].' #'.($row['id'] ?? '');
    }


    /**
     * The formats the signed-in user may download this module as: the ones the
     * module turned on, this server can produce, and the user's role allows.
     *
     * @param array<string, mixed> $module
     *
     * @return array<string, string> format => label
     */
    protected function export_formats(array $module)
    {
        if (!can($module['slug'].'.export')) {
            return [];
        }

        return array_intersect_key(Exporter::available(), array_flip((array) ($module['export'] ?? [])));
    }

    /**
     * @return string|false the lock key, or false when this user already has an export running
     */
    protected function export_lock()
    {
        $this->load->library('sharedcache');
        $key = 'export_lock_'.(int) (current_user()['id'] ?? 0);
        if ($this->sharedcache->get($key) !== false) {
            return false;
        }
        // Expires on its own if the request dies before it can unlock.
        $this->sharedcache->save($key, 1, (int) (getenv('EXPORT_TIME_LIMIT') ?: 300) + 30);

        return $key;
    }

    protected function export_unlock($key)
    {
        $this->sharedcache->delete($key);
    }

    /**
     * Raw headers for a download. CodeIgniter's output buffering is dropped
     * first so a CSV can be sent while it is still being read.
     */
    protected function send_headers($format, $name, $length = null)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: '.Exporter::TYPES[$format]);
        header('Content-Disposition: attachment; filename="'.$name.'"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if ($length !== null) {
            header('Content-Length: '.(int) $length);
        }
    }

    /**
     * Loads the module for the URL (404 if there is none) and checks that
     * the user may do $action on it.
     *
     * @return array<string, mixed>
     */
    protected function module($slug, $action)
    {
        $module = $this->crud_store->find($slug);
        if (!$module) {
            show_404();
        }

        require_permission($module['slug'].'.'.$action);

        return $module;
    }

    /**
     * Answers with JSON and the CSRF token to use for the next request.
     *
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, $status = 200)
    {
        $payload['csrf'] = $this->security->get_csrf_hash();

        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }

    /**
     * One list cell as safe HTML.
     *
     * @param array<string, mixed>  $field
     * @param array<string, string> $labels value => label for dropdown fields
     */
    protected function cell(array $field, $value, array $labels)
    {
        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        if ($value === null || $value === '') {
            return '<span class="text-body-secondary">—</span>';
        }

        switch ($field['type']) {
            case FieldTypes::TEXTAREA:
                return $e(mb_strimwidth((string) $value, 0, 80, '…'));

            case FieldTypes::SELECT:
            case FieldTypes::RADIO:
                return $e($labels[$value] ?? $value);

            case FieldTypes::MULTISELECT:
                $html = '';
                foreach ((array) json_decode((string) $value, true) as $v) {
                    $html .= '<span class="badge text-bg-secondary me-1">'.$e($labels[$v] ?? $v).'</span>';
                }

                return $html;

            case FieldTypes::IMAGE:
                return Uploader::validPath((string) $value)
                    ? '<a href="'.$e(base_url('uploads/'.$value)).'" target="_blank" rel="noopener"><img src="'.$e(base_url('uploads/'.$value)).'" alt="" class="crud-thumb" loading="lazy"></a>'
                    : '';

            case FieldTypes::FILE:
                return Uploader::validPath((string) $value)
                    ? '<a href="'.$e(base_url('uploads/'.$value)).'" target="_blank" rel="noopener" download><i class="bi bi-paperclip me-1"></i>'.$e(strtoupper(pathinfo((string) $value, PATHINFO_EXTENSION))).'</a>'
                    : '';

            default:
                return $e($value);
        }
    }
}
