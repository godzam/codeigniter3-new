<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Crud\FieldTypes;
use App\Crud\RecordValidator;
use App\Crud\Uploader;

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
 *
 * Save and delete answer with JSON {ok, message, errors?, csrf}; the page
 * script (assets/js/crud.js) sends the new CSRF token back with the next request.
 *
 * @property Template $template
 * @property Crud_store $crud_store
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
    }

    public function index($slug = '')
    {
        $module = $this->module($slug, 'view');

        $this->template
            ->set_title($module['title'])
            ->set_breadcrumbs(['Home' => '', $module['title'] => null])
            ->add_foot('<script src="'.asset_url('js/crud.js').'"></script>')
            ->render('index', [
                'module' => $module,
                'can_create' => can($module['slug'].'.create'),
                'can_edit' => can($module['slug'].'.edit'),
                'can_delete' => can($module['slug'].'.delete'),
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
            $store->insert_record($module, $values);
        } else {
            $store->update_record($module, $id, $values);
            foreach ($uploads as $name => $change) {
                $uploader->delete((string) ($existing[$name] ?? ''));
            }
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

        $uploader = $this->crud_store->uploader();
        foreach ($module['fields'] as $f) {
            if (FieldTypes::isUpload($f['type'])) {
                $uploader->delete((string) ($row[$f['name']] ?? ''));
            }
        }

        $this->json(['ok' => true, 'message' => 'Record deleted.']);
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
