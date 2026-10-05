<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Minimal email/password auth. Each user has one role (users.role); what a
 * role may do is managed on the Roles page (see application/libraries/Rbac.php
 * and can()/require_permission() in application/helpers/auth_helper.php).
 * Wrong passwords block the IP address and the account (Loginguard: 5 wrong =
 * 1 hour, the 3rd block in a row = 1 day); sign-ins, sign-outs, blocks and
 * registrations are written to the audit log. Where the security tables are
 * missing (migrations not run) it falls back to a plain per-minute limit.
 *
 * Routes (see application/modules/auth/config/routes.php):
 *   GET|POST /login
 *   GET|POST /register
 *   GET      /logout
 *
 * @property User_model $user_model
 * @property CI_Form_validation $form_validation
 * @property Ratelimiter $ratelimiter
 * @property Loginguard $loginguard
 * @property Audit $audit
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
        $this->load->library(['form_validation', 'ratelimiter', 'session', 'loginguard']);
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

        $user = $this->user_model->find_by_email($this->input->post('email'));
        $guard = $this->loginguard;

        if ($guard->available()) {
            // Blocked (this IP address, or this account): say so without even looking at the password.
            $left = $guard->seconds_blocked($user ? (int) $user->id : null);
            if ($left > 0) {
                $this->show('login', 'Log in', ['error' => $this->blocked_message($left)]);

                return;
            }
        } else {
            $rate_key = 'login:'.$this->input->ip_address();
            if (!$this->ratelimiter->attempt($rate_key, 5, 60)) {
                $retry_after = $this->ratelimiter->retry_after($rate_key);
                $this->show('login', 'Log in', ['error' => "Too many attempts. Try again in {$retry_after}s."]);

                return;
            }
        }

        if (!$user || !password_verify((string) $this->input->post('password'), $user->password)) {
            $result = $guard->failure($user ? (int) $user->id : null);

            if ($result['locked']) {
                $message = $this->blocked_message($result['seconds']);
            } elseif ($guard->available() && $result['attempts_left'] <= 3 && $result['attempts_left'] > 0) {
                $message = 'Invalid email or password. '.$result['attempts_left'].' attempt'.($result['attempts_left'] === 1 ? '' : 's').' left before a temporary block.';
            } else {
                $message = 'Invalid email or password.';
            }

            $this->show('login', 'Log in', ['error' => $message]);

            return;
        }

        if ($guard->available()) {
            $guard->success((int) $user->id);
        } else {
            $this->ratelimiter->reset('login:'.$this->input->ip_address());
        }

        $this->log_in_as($user);
        $this->audit->event('login', 'auth', $user->id, $user->name.' ('.$user->email.')');

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

        $created = $this->user_model->find($user_id);
        $this->log_in_as($created);
        $this->audit->created('users', $user_id, $created->name.' ('.$created->email.')', $created);
        redirect('dashboard');
    }

    public function logout()
    {
        if (is_logged_in()) {
            $me = current_user();
            $this->audit->event('logout', 'auth', $me['id'], $me['name'].' ('.$me['email'].')');
        }

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

    /**
     * What the visitor reads when they are (or just became) blocked.
     */
    protected function blocked_message($seconds)
    {
        return 'Too many failed login attempts. Please try again in about '.$this->loginguard->human($seconds).'.';
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
