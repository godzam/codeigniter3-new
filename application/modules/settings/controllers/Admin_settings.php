<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin page for the settings declared in application/config/app_settings.php.
 * The form, validation, and defaults are all generated from that schema.
 *
 * Routes (application/config/routes.php):
 *   GET|POST /admin/settings
 *   POST     /admin/settings/reset
 *
 * @property Template $template
 * @property Settings $settings
 * @property CI_Input $input
 */
class Admin_settings extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_permission('settings.manage');
        $this->load->helper('form');
    }

    public function index()
    {
        if ($this->input->method() === 'post') {
            $this->save();

            return;
        }

        $this->show($this->settings->all(), []);
    }

    protected function save()
    {
        $input = $this->input->post('settings');
        $result = $this->settings->validate(is_array($input) ? $input : []);

        if ($result['errors']) {
            // Re-show the form with what was typed (secrets are never echoed back).
            $values = $result['values'] + $this->settings->all();
            $this->show($values, $result['errors']);

            return;
        }

        if (!$this->settings->save($result['values'])) {
            flash('error', 'Settings could not be saved. Run the migrations first: php index.php console migrate');
            redirect('admin/settings');
        }

        flash('success', 'Settings saved.');
        redirect('admin/settings');
    }

    public function reset()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $ok = $this->settings->reset();
        flash($ok ? 'success' : 'error', $ok ? 'Settings reset to their defaults.' : 'Settings could not be reset.');
        redirect('admin/settings');
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    protected function show(array $values, array $errors)
    {
        $this->template
            ->set_title('Settings')
            ->set_breadcrumbs(['Home' => '', 'Settings' => null])
            ->add_foot('<script src="'.asset_url('js/settings.js').'"></script>')
            ->render('index', [
                'groups' => $this->settings->schema()->groups(),
                'values' => $values,
                'errors' => $errors,
                'available' => $this->settings->available(),
            ], 'admin');
    }
}
