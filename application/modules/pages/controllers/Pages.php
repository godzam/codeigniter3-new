<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Page\SectionTypes;

/**
 * Content pages: Admin -> Pages builds them, /page/{slug} serves them.
 *
 * Routes (application/config/routes.php):
 *   GET       /admin/pages             pages.view    the list
 *   GET|POST  /admin/pages/create      pages.create
 *   GET|POST  /admin/pages/edit/{id}   pages.edit
 *   POST      /admin/pages/delete/{id} pages.delete
 *   GET       /page/{slug}             public        published pages (a draft
 *                                                   is a preview for editors)
 *
 * A page is a list of sections (hero, text, features, cta, image, html) stored
 * as JSON; see App\Page\SectionTypes for the blocks and their rendering.
 *
 * @property Template $template
 * @property Page_model $page_model
 * @property Audit $audit
 * @property CI_Input $input
 * @property CI_Output $output
 */
class Pages extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('form');
        $this->load->model('pages/page_model', 'page_model');
    }

    // ------------------------------------------------------------------ Admin

    public function index()
    {
        require_permission('pages.view');

        $this->template
            ->set_title('Pages')
            ->set_subtitle('Pages built here are served at /page/{slug}. A draft is only visible to editors.')
            ->set_breadcrumbs(['Home' => '', 'Pages' => null])
            ->render('index', [
                'pages' => $this->page_model->all(),
                'available' => $this->page_model->available(),
                'can_create' => can('pages.create'),
                'can_edit' => can('pages.edit'),
                'can_delete' => can('pages.delete'),
            ], 'admin');
    }

    public function create()
    {
        require_permission('pages.create');

        if ($this->input->method() === 'post') {
            $this->save(null);

            return;
        }

        $this->form(null, ['title' => '', 'slug' => '', 'status' => Page_model::STATUS_DRAFT, 'meta_description' => '', 'sections' => []], []);
    }

    public function edit($id = 0)
    {
        require_permission('pages.edit');

        $page = $this->page_model->find((int) $id);
        if (!$page) {
            show_404();
        }

        if ($this->input->method() === 'post') {
            $this->save($page);

            return;
        }

        $this->form($page, $page, []);
    }

    public function delete($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        require_permission('pages.delete');

        $page = $this->page_model->find((int) $id);
        if (!$page) {
            show_404();
        }

        if ($this->page_model->delete((int) $id)) {
            $this->audit->deleted('pages', $page['id'], $page['title'], $page);
            flash('success', 'Page deleted.');
        } else {
            flash('error', 'The page could not be deleted. Run the migrations first: php index.php console migrate');
        }

        redirect('admin/pages');
    }

    // ----------------------------------------------------------------- Public

    /**
     * GET /page/{slug} — published pages for everyone; a draft is a preview
     * for whoever may edit pages.
     */
    public function view($slug = '')
    {
        $page = $this->page_model->find_by_slug($slug, true);

        if ($page === null) {
            // Not published: editors may preview it, everyone else gets a 404.
            $preview = is_logged_in() && can('pages.edit') ? $this->page_model->find_by_slug($slug, false) : null;
            if ($preview === null) {
                show_404();

                return;
            }
            $page = $preview;
            $preview_note = true;
        } else {
            $preview_note = false;
        }

        $description = trim((string) ($page['meta_description'] ?? ''));
        if ($description !== '') {
            $this->template->add_head('<meta name="description" content="'.htmlspecialchars($description, ENT_QUOTES, 'UTF-8').'">');
        }

        $this->template
            ->set_title($page['title'])
            ->set_breadcrumbs(['Home' => '', $page['title'] => null])
            ->render('view', [
                'page' => $page,
                'content' => SectionTypes::renderAll($page['sections']),
                'preview' => !empty($preview_note),
                'edit_url' => can('pages.edit') ? site_url('admin/pages/edit/'.(int) $page['id']) : null,
            ], 'public');
    }

    // ------------------------------------------------------------- Form & save

    /**
     * @param array<string, mixed>      $page
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    protected function form($page, array $values, array $errors)
    {
        $this->template
            ->set_title($page === null ? 'New page' : 'Edit '.$page['title'])
            ->set_breadcrumbs(['Home' => '', 'Pages' => 'admin/pages', ($page === null ? 'New page' : 'Edit') => null])
            ->add_foot('<script src="'.asset_url('js/pages.js').'"></script>')
            ->render('form', [
                'page' => $page,
                'values' => $values,
                'errors' => $errors,
                'catalog' => [
                    'labels' => SectionTypes::labels(),
                    'fields' => array_combine(array_keys(SectionTypes::labels()), array_map([SectionTypes::class, 'fields'], array_keys(SectionTypes::labels()))),
                ],
            ], 'admin');
    }

    /**
     * @param array<string, mixed>|null $page
     */
    protected function save($page)
    {
        $input = (array) $this->input->post(null, false);

        $title = trim((string) ($input['title'] ?? ''));
        $status = ($input['status'] ?? '') === Page_model::STATUS_PUBLISHED ? Page_model::STATUS_PUBLISHED : Page_model::STATUS_DRAFT;

        // The layout arrives as indexed form fields (sections[0][settings][heading])
        // when JS renumbers them; sections_json is the fallback written by the
        // editor when it only changed the JSON (or before JS ran).
        $sections = null;
        if (array_key_exists('sections', $input) && is_array($input['sections'])) {
            $sections = array_values($input['sections']);
        }
        if ($sections === null) {
            $sections = json_decode((string) ($input['sections_json'] ?? ''), true);
        }
        $sections = SectionTypes::normalize(is_array($sections) ? $sections : []);

        $slug = $page === null ? trim((string) ($input['slug'] ?? '')) : trim((string) ($input['slug'] ?? ($page['slug'] ?? '')));
        if ($slug !== '') {
            $slug = Page_model::slugify($slug);
        }

        $values = [
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'meta_description' => trim((string) ($input['meta_description'] ?? '')),
            'sections' => $sections,
        ];

        $errors = [];
        if ($title === '' || mb_strlen($title) > 190) {
            $errors['title'] = 'Enter a title of up to 190 characters.';
        }
        if (mb_strlen($values['meta_description']) > 300) {
            $errors['meta_description'] = 'Keep the description under 300 characters.';
        }
        if ($sections === []) {
            $errors['sections'] = 'Add at least one section to the layout.';
        }

        if ($errors === []) {
            if ($slug === '') {
                $slug = $this->page_model->free_slug($title, $page['id'] ?? null);
            }
            if ($this->page_model->slug_taken($slug, $page['id'] ?? null)) {
                $errors['slug'] = 'That address is already used by another page.';
            } else {
                $values['slug'] = $slug;
            }
        }

        if ($errors !== []) {
            $this->form($page, $values, $errors);

            return;
        }

        if ($page === null) {
            $id = $this->page_model->create($values);
            if ($id === null) {
                flash('error', 'The page could not be saved. Run the migrations first: php index.php console migrate');
                redirect('admin/pages');
            }
            $this->audit->created('pages', $id, $values['title'], $values + ['slug' => $slug]);
            flash('success', 'Page created. '.site_url('page/'.$slug));
        } else {
            if (!$this->page_model->update((int) $page['id'], $values)) {
                flash('error', 'The page could not be saved. Run the migrations first: php index.php console migrate');
                redirect('admin/pages');
            }
            $this->audit->updated('pages', $page['id'], $values['title'], $page + ['slug' => $page['slug']], $values + ['slug' => $slug]);
            flash('success', 'Page saved.');
        }

        redirect('admin/pages');
    }
}
