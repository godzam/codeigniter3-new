<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Install\InstallInput;

/**
 * The page behind the "Install" button on the Welcome page: one form that
 * sets everything up (see the Installer library) and signs the new
 * administrator in.
 *
 * Route: /install (GET shows the form, POST installs). It disappears (404 or
 * redirect) as soon as the application has a user, and is switched off
 * outside development unless INSTALLER_ENABLED=true is set in .env.
 *
 * @property Template $template
 * @property Installer $installer
 * @property CI_Input $input
 * @property CI_Session $session
 */
class Install extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('installer');
        $this->load->helper('form');

        if (!$this->installer->enabled()) {
            show_404();
        }

        if ($this->installer->installed()) {
            redirect(is_logged_in() ? 'dashboard' : 'login');
        }
    }

    public function index()
    {
        if ($this->input->method() === 'post') {
            $this->install();

            return;
        }

        $this->form([], [], []);
    }

    protected function install()
    {
        $status = $this->installer->status();
        $checked = InstallInput::validate((array) $this->input->post(null, false), $status['needs_database']);

        if ($checked['errors'] !== []) {
            $this->form($checked['values'], $checked['errors'], []);

            return;
        }

        $result = $this->installer->run($checked['values']);

        if (!$result['ok']) {
            $this->form($checked['values'], [], $result);

            return;
        }

        // Sign the new administrator in and send them to the dashboard.
        $this->session->sess_regenerate(true);
        $this->session->set_userdata([
            'user_id' => $result['user']['id'],
            'user_name' => $result['user']['name'],
            'user_email' => $result['user']['email'],
            'user_role' => $result['user']['role'],
        ]);
        flash('success', 'All set! '.implode('. ', $result['steps']).'.');
        redirect('dashboard');
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $errors
     * @param array<string, mixed>  $failure a failed run(): error, steps, manual_config
     */
    protected function form(array $values, array $errors, array $failure)
    {
        $this->template
            ->set_title('Install')
            ->render('index', [
                'status' => $this->installer->status(),
                'values' => $values + $this->defaults(),
                'errors' => $errors,
                'failure' => $failure,
            ], 'auth');
    }

    /**
     * @return array<string, string>
     */
    protected function defaults()
    {
        $folder = preg_replace('/[^a-z0-9_]+/', '_', strtolower(basename(rtrim(FCPATH, '/\\')))) ?: 'app';

        return [
            'app_name' => (string) app_setting('app_name'),
            'admin_name' => '',
            'admin_email' => '',
            'admin_password' => '',
            'db_host' => 'localhost',
            'db_user' => 'root',
            'db_pass' => '',
            'db_name' => trim($folder, '_') ?: 'app',
        ];
    }
}
