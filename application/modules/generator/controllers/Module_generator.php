<?php

defined('BASEPATH') or exit('No direct script access allowed');

use App\Crud\Definition;
use App\Crud\FieldTypes;

/**
 * The CRUD generator: define a table with its fields and get a working
 * list + add/edit modal + delete page, with its own permissions and menu
 * entry. For developers only — every action requires the super_admin role
 * (there is deliberately no permission to hand it to anyone else).
 * (Named Module_generator because PHP already has a built-in Generator class.)
 *
 * Routes (application/config/routes.php):
 *   GET       /admin/generator               list of modules
 *   GET       /admin/generator/data          JSON for that list
 *   GET|POST  /admin/generator/create        builder: new module
 *   GET|POST  /admin/generator/edit/{slug}   builder: add fields, change labels/options
 *   POST      /admin/generator/delete/{slug} drop the module AND its table
 *
 * @property Template $template
 * @property Crud_store $crud_store
 * @property Crud_rules $crud_rules
 * @property Audit $audit
 * @property Datatable $datatable
 * @property CI_Input $input
 */
class Module_generator extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_login();

        if (!is_super_admin()) {
            abort(403, 'The generator is for developers (super admin) only.');
        }

        $this->load->helper('form');
        $this->load->library('crud_rules');
    }

    public function index()
    {
        $this->template
            ->set_title('Generator')
            ->set_subtitle('Define a table and its fields; get a list, an add/edit dialog, delete, permissions and a menu entry.')
            ->set_breadcrumbs(['Home' => '', 'Generator' => null])
            ->add_foot('<script src="'.asset_url('js/generator.js').'"></script>')
            ->render('index', [], 'admin');
    }

    public function data()
    {
        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $this->datatable
            ->from(static function ($db) {
                $db->from('crud_modules m');
            })
            ->select('m.slug, m.table_name, m.title, m.icon, m.definition, m.created_at')
            ->column('title', 'm.title')
            ->column('key', 'm.slug')
            ->column('table', 'm.table_name')
            ->column('fields', null)
            ->column('actions', null)
            ->default_order(['m.id DESC'])
            ->respond(function ($m) use ($e) {
                $definition = json_decode((string) $m['definition'], true) ?: [];
                $slug = $e($m['slug']);

                return [
                    'title' => '<i class="bi '.$e($m['icon']).' me-2 text-body-secondary"></i><span class="fw-semibold">'.$e($m['title']).'</span>',
                    'key' => '<code>'.$slug.'</code>',
                    'table' => '<code>'.$e($m['table_name']).'</code>',
                    'fields' => count($definition['fields'] ?? []),
                    'actions' => '<a class="btn btn-sm btn-primary" href="'.site_url('admin/c/'.$m['slug']).'">Open</a> '
                        .'<a class="btn btn-sm btn-outline-secondary" href="'.site_url('admin/generator/edit/'.rawurlencode($m['slug'])).'">Edit</a> '
                        .form_open('admin/generator/delete/'.rawurlencode($m['slug']), ['class' => 'd-inline'])
                        .'<input type="hidden" name="confirm_slug" value="">'
                        .'<button type="button" class="btn btn-sm btn-outline-danger" data-generator-delete="'.$slug.'" data-title="'.$e($m['title']).'" aria-label="Delete"><i class="bi bi-trash"></i></button>'
                        .form_close(),
                ];
            });
    }

    public function create()
    {
        if ($this->input->method() === 'post') {
            $this->save(null);

            return;
        }

        $this->form(null, ['title' => '', 'slug' => '', 'table' => '', 'icon' => 'bi-table', 'fields' => []], []);
    }

    public function edit($slug = '')
    {
        $module = $this->crud_store->find($slug);
        if (!$module) {
            show_404();
        }

        if ($this->input->method() === 'post') {
            $this->save($module);

            return;
        }

        $this->form($module, $module, []);
    }

    public function delete($slug = '')
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $module = $this->crud_store->find($slug);
        if (!$module) {
            show_404();
        }

        // Typing the key is the confirmation: the table and its records are dropped for good.
        if ((string) $this->input->post('confirm_slug') !== $module['slug']) {
            flash('error', 'Type the module key to confirm the deletion.');
            redirect('admin/generator');
        }

        $before = $this->audit_view($module) + ['records_deleted' => $this->crud_store->count_records($module)];

        $this->crud_store->delete_module($module);
        $this->audit->deleted('generator', $module['slug'], $module['title'], $before);
        flash('success', 'Module "'.$module['title'].'" and its table were deleted.');
        redirect('admin/generator');
    }

    /**
     * @param array<string, mixed>|null $module
     */
    protected function save($module)
    {
        $input = json_decode((string) $this->input->post('module_json'), true);
        $input = is_array($input) ? $input : [];

        $result = Definition::normalize(
            $input,
            $module,
            $module === null ? $this->crud_store->taken_slugs() : [],
            function ($table, $column = null) {
                return $this->crud_store->schema_has($table, $column);
            },
            $this->crud_rules->meta()
        );

        if ($result['definition'] === null) {
            $this->form($module, $input + ['title' => '', 'slug' => '', 'table' => '', 'icon' => 'bi-table', 'fields' => []], $result['errors']);

            return;
        }

        $error = $module === null
            ? $this->crud_store->create_module($result['definition'])
            : $this->crud_store->update_module($module, $result['definition']);

        if ($error !== null) {
            $this->form($module, $input, ['form' => $error]);

            return;
        }

        $slug = $result['definition']['slug'];
        $label = $result['definition']['title'];
        if ($module === null) {
            $this->audit->created('generator', $slug, $label, $this->audit_view($result['definition']));
        } else {
            $this->audit->updated('generator', $slug, $label, $this->audit_view($module), $this->audit_view($result['definition']));
        }
        flash('success', $module === null
            ? 'Module created. The admin role can use it; give other roles access on the Roles page.'
            : 'Module saved.');
        redirect('admin/c/'.$slug);
    }

    /**
     * A module definition as the audit log records it: fields keyed by their
     * column name, so a change reads "fields.price.label" instead of "fields.3.label".
     *
     * @param array<string, mixed> $module
     *
     * @return array<string, mixed>
     */
    protected function audit_view(array $module)
    {
        $fields = [];
        foreach ($module['fields'] as $field) {
            $fields[$field['name']] = $field;
        }

        return ['slug' => $module['slug'], 'table' => $module['table'], 'title' => $module['title'], 'icon' => $module['icon'], 'fields' => $fields];
    }

    /**
     * @param array<string, mixed>|null $module
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    protected function form($module, array $values, array $errors)
    {
        $locked = [];
        foreach ($module['fields'] ?? [] as $field) {
            $locked[] = $field['name'];
        }

        $this->template
            ->set_title($module === null ? 'New module' : 'Edit '.$module['title'])
            ->set_breadcrumbs(['Home' => '', 'Generator' => 'admin/generator', ($module === null ? 'New module' : 'Edit') => null])
            ->add_foot('<script src="'.asset_url('js/generator.js').'"></script>')
            ->render('form', [
                'module' => $module,
                'values' => $values,
                'errors' => $errors,
                'locked' => $locked,
                'types' => FieldTypes::labels(),
                'rule_catalog' => $this->crud_rules->catalog(),
            ], 'admin');
    }
}
