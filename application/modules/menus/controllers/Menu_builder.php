<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

/**
 * Admin -> Menus: build the admin sidebar without touching menu.php.
 *
 * Routes (application/config/routes.php):
 *   GET       /admin/menus            menus.view      the tree, drag to reorder
 *   GET|POST  /admin/menus/create     menus.create
 *   GET|POST  /admin/menus/edit/{id}  menus.edit
 *   POST      /admin/menus/delete/{id} menus.delete
 *   POST      /admin/menus/reorder    menus.edit      new order (JSON)
 *
 * The items in application/config/menu.php stay; what is built here is merged
 * into the sidebar by menu_items() (application/helpers/theme_helper.php).
 * A section row is a heading, a link row is an entry (links may be nested
 * one level, depth 2 in the builder).
 *
 * @property Template $template
 * @property Menu_model $menu_model
 * @property Audit $audit
 * @property Rbac $rbac
 * @property CI_Input $input
 * @property CI_Output $output
 * @property CI_Form_validation $form_validation
 */
class Menu_builder extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_login();
        $this->load->helper('form');
        $this->load->model('menus/menu_model', 'menu_model');
    }

    public function index()
    {
        require_permission('menus.view');

        $this->template
            ->set_title('Menu builder')
            ->set_subtitle('Drag an item onto another to nest it. Changes appear in the sidebar straight away.')
            ->set_breadcrumbs(['Home' => '', 'Menu builder' => null])
            ->add_foot('<script src="'.asset_url('js/menus.js').'"></script>')
            ->render('index', [
                'items' => $this->menu_model->all(),
                'available' => $this->menu_model->available(),
                'can_create' => can('menus.create'),
                'can_edit' => can('menus.edit'),
                'can_delete' => can('menus.delete'),
                'csrf_name' => $this->security->get_csrf_token_name(),
                'csrf_hash' => $this->security->get_csrf_hash(),
            ], 'admin');
    }

    public function create()
    {
        require_permission('menus.create');

        if ($this->input->method() === 'post') {
            $this->save(null);

            return;
        }

        $this->form(null, $this->menu_model->defaults(), []);
    }

    public function edit($id = 0)
    {
        require_permission('menus.edit');

        $item = $this->menu_model->find((int) $id);
        if (!$item) {
            show_404();
        }

        if ($this->input->method() === 'post') {
            $this->save($item);

            return;
        }

        $this->form($item, $item, []);
    }

    public function delete($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        require_permission('menus.delete');

        $item = $this->menu_model->find((int) $id);
        if (!$item) {
            show_404();
        }

        if ($this->menu_model->delete((int) $id)) {
            $this->audit->deleted('menus', $item['id'], $item['label'], $item);
            flash('success', 'Menu item deleted. Its children moved up one level.');
        } else {
            flash('error', 'The menu could not be changed. Run the migrations first: php index.php console migrate');
        }

        redirect('admin/menus');
    }

    /**
     * The order the builder dropped things into: [[id, depth], ...].
     */
    public function reorder()
    {
        if ($this->input->method() !== 'post') {
            $this->json(['ok' => false, 'message' => 'POST only.'], 405);

            return;
        }
        require_permission('menus.edit');

        $raw = json_decode((string) $this->input->post('order'), true);
        $order = [];
        foreach ((array) $raw as $pair) {
            if (is_array($pair) && isset($pair[0], $pair[1])) {
                $order[] = [(int) $pair[0], (int) $pair[1]];
            }
        }

        $ok = $this->menu_model->reorder($order);
        if ($ok) {
            $this->audit->event('reorder', 'menus', null, 'Sidebar menu', ['order' => implode(',', array_map(static function ($p) {
                return $p[0].':'.$p[1];
            }, $order))]);
        }

        $this->json(['ok' => $ok, 'message' => $ok ? 'Menu saved.' : 'The order could not be saved.'], $ok ? 200 : 500);
    }

    /**
     * @param array<string, mixed>|null $item
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    protected function form($item, array $values, array $errors)
    {
        $parents = array_values(array_filter($this->menu_model->all(), static function ($row) use ($item) {
            // A link may be nested under another link, never under itself.
            return $row['type'] === Menu_model::TYPE_LINK && (int) $row['id'] !== (int) ($item['id'] ?? 0);
        }));

        $this->template
            ->set_title($item === null ? 'New menu item' : 'Edit '.$item['label'])
            ->set_breadcrumbs(['Home' => '', 'Menu builder' => 'admin/menus', ($item === null ? 'New item' : 'Edit') => null])
            ->render('form', [
                'item' => $item,
                'values' => $values,
                'errors' => $errors,
                'parents' => $parents,
                'permissions' => $this->rbac->registry()->keys(),
            ], 'admin');
    }

    /**
     * @param array<string, mixed>|null $item
     */
    protected function save($item)
    {
        $input = (array) $this->input->post(null, false);
        $values = [
            'type' => ($input['type'] ?? '') === Menu_model::TYPE_SECTION ? Menu_model::TYPE_SECTION : Menu_model::TYPE_LINK,
            'label' => trim((string) ($input['label'] ?? '')),
            'icon' => preg_replace('/[^a-z0-9 \-]/i', '', (string) ($input['icon'] ?? '')),
            'url' => trim((string) ($input['url'] ?? '')),
            'permission' => trim((string) ($input['permission'] ?? '')),
            'super_admin' => isset($input['super_admin']) ? 1 : 0,
            'is_active' => isset($input['is_active']) ? 1 : 0,
            'parent_id' => (int) ($input['parent_id'] ?? 0),
        ];

        $errors = [];
        if ($values['label'] === '' || mb_strlen($values['label']) > 120) {
            $errors['label'] = 'Enter a label of up to 120 characters.';
        }
        if ($values['type'] === Menu_model::TYPE_LINK) {
            if ($values['url'] === '' || mb_strlen($values['url']) > 255) {
                $errors['url'] = 'A link needs a URL of up to 255 characters (e.g. dashboard or https://…).';
            }
        } else {
            $values['url'] = '';
        }
        if ($values['permission'] !== '' && !in_array($values['permission'], $this->rbac->registry()->keys(), true)) {
            $errors['permission'] = 'Unknown permission "'.mb_substr($values['permission'], 0, 40).'". Pick one from the list.';
        }
        // Never let an item become its own parent (a loop would hide it).
        if ($values['parent_id'] === (int) ($item['id'] ?? 0)) {
            $values['parent_id'] = 0;
        }

        if ($errors !== []) {
            if ($item === null) {
                $this->form(null, $values + $this->menu_model->defaults(), $errors);
            } else {
                $this->form($item, $values + $item, $errors);
            }

            return;
        }

        if ($item === null) {
            $id = $this->menu_model->create($values);
            if ($id === null) {
                flash('error', 'The item could not be saved. Run the migrations first: php index.php console migrate');
                redirect('admin/menus');
            }
            $this->audit->created('menus', $id, $values['label'], $values);
            flash('success', 'Menu item added.');
        } else {
            if (!$this->menu_model->update((int) $item['id'], $values)) {
                flash('error', 'The item could not be saved. Run the migrations first: php index.php console migrate');
                redirect('admin/menus');
            }
            $this->audit->updated('menus', $item['id'], $values['label'], $item, $values + $item);
            flash('success', 'Menu item saved.');
        }

        redirect('admin/menus');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }
}
