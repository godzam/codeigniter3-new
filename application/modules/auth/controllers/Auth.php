<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Minimal email/password auth. Each user has one role (users.role); what a
 * role may do is managed on the Roles page (see application/libraries/Rbac.php
 * and can()/require_permission() in application/helpers/auth_helper.php).
 * Login attempts are throttled per-IP via Ratelimiter.
 *
 * Routes (see application/modules/auth/config/routes.php):
 *   GET|POST /login
 *   GET|POST /register
 *   GET      /logout
 *
 * @property User_model $user_model
 * @property CI_Form_validation $form_validation
 * @property Ratelimiter $ratelimiter
 * @property CI_Session $session
 * @property Template $template
 * @property Turnstile $turnstile
 * @property Rbac $rbac
 */
class Auth extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->library(['form_validation', 'ratelimiter', 'session']);
        $this->load->helper(['form', 'url']);
    }

    public function login()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        if ($this->input->method() === 'post') {
            $this->handle_login();

            return;
        }

        $this->show('login', 'Log in');
    }

    protected function handle_login()
    {
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'required');

        if (!$this->form_validation->run()) {
            $this->show('login', 'Log in');

            return;
        }

        if (!$this->turnstile->verify_for('login')) {
            $this->show('login', 'Log in', ['error' => $this->turnstile->error_message()]);

            return;
        }

        $rate_key = 'login:'.$this->input->ip_address();

        if (!$this->ratelimiter->attempt($rate_key, 5, 60)) {
            $retry_after = $this->ratelimiter->retry_after($rate_key);
            $this->show('login', 'Log in', ['error' => "Too many attempts. Try again in {$retry_after}s."]);

            return;
        }

        $user = $this->user_model->find_by_email($this->input->post('email'));

        if (!$user || !password_verify((string) $this->input->post('password'), $user->password)) {
            $this->show('login', 'Log in', ['error' => 'Invalid email or password.']);

            return;
        }

        $this->ratelimiter->reset($rate_key);
        $this->log_in_as($user);

        $redirect = $this->session->flashdata('redirect_after_login');
        redirect($redirect ?: 'dashboard');
    }

    public function register()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        if ($this->input->method() === 'post') {
            $this->handle_register();

            return;
        }

        $this->show('register', 'Register');
    }

    protected function handle_register()
    {
        $this->form_validation->set_rules('name', 'Name', 'required|min_length[2]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');

        if (!$this->form_validation->run()) {
            $this->show('register', 'Register');

            return;
        }

        if (!$this->turnstile->verify_for('register')) {
            $this->show('register', 'Register', ['error' => $this->turnstile->error_message()]);

            return;
        }

        $user_id = $this->user_model->create([
            'name' => $this->input->post('name'),
            'email' => $this->input->post('email'),
            'password' => password_hash((string) $this->input->post('password'), PASSWORD_DEFAULT),
            'role' => $this->rbac->default_role_slug(), // set on the Roles page
        ]);

        $this->log_in_as($this->user_model->find($user_id));
        redirect('dashboard');
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect('login');
    }

    /**
     * Renders a form view inside the centered 'auth' layout.
     *
     * @param array<string, mixed> $data
     */
    protected function show($view, $title, array $data = [])
    {
        $this->template->set_title($title)->render($view, $data, 'auth');
    }

    protected function log_in_as($user)
    {
        $this->session->set_userdata([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
        ]);
    }
}
