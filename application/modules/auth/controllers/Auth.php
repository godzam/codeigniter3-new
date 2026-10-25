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
 * Sign-ups are rate limited per IP address too (5 attempts per 10 minutes, the
 * same Ratelimiter the login fallback uses): open registration is cheap to
 * flood, and Turnstile only covers it once its keys are configured.
 *
 * Routes (see application/modules/auth/config/routes.php):
 *   GET|POST /login
 *   GET|POST /register
 *   GET      /logout
 *   GET|POST /forgot_password
 *   GET|POST /reset_password/{id}/{token}
 *   GET      /verify_email/{id}/{token}
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
        // Count every POST, valid or not, before doing any work: hammering
        // the form with garbage is exactly what this is here to stop.
        $rate_key = 'register:'.$this->input->ip_address();
        if (!$this->ratelimiter->attempt($rate_key, 5, 600)) {
            $retry_after = $this->ratelimiter->retry_after($rate_key);
            $this->show('register', 'Register', [
                'error' => 'Too many sign-up attempts from this address. Please try again in '.$this->loginguard->human($retry_after).'.',
            ]);

            return;
        }

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
            'email_verified' => 0,
            'email_verification_token' => md5(uniqid().$this->input->post('email')),
        ]);

        $this->load->library('email');

        $verification_url = site_url('auth/verify_email/'.$user_id.'/'.$this->user_model->find($user_id)->email_verification_token);

        $this->email->from($this->settings->get('email_from_address') ?: 'no-reply@localhost', $this->settings->get('email_from_name') ?: 'My App');
        $this->email->to($this->input->post('email'));
        $this->email->subject('Verify your email');
        $this->email->message("Please click this link to verify your email: ".site_url('auth/verify_email/'.$user_id.'/'.$this->user_model->find($user_id)->email_verification_token));
        $this->email->send();

        $created = $this->user_model->find($user_id);
        $created->email_verified = 0;
        $created->email_verification_token = md5(uniqid().$created->email);
        $this->audit->created('users', $user_id, $created->name.' ('.$created->email.')', $created);
        $this->show('register', 'Register', ['msg' => 'A verification email has been sent to your address. Please check your inbox.']);
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
     * Google OAuth2 login
     */
    public function google_login()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $enabled = $this->settings->get('google_enabled');

        if (!$enabled) {
            show_404();
        }

        $client_id = $this->settings->get('google_client_id');
        $client_secret = $this->settings->get('google_client_secret');

        if ($client_id === '' || $client_secret === '') {
            show_404();
        }

        $redirect_uri = site_url('auth/google_callback');

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ]);

        $_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));

        header('Location: ' . $auth_url);
        exit;    }

    /**
     * Google OAuth2 callback
     */
    public function google_callback()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $enabled = $this->settings->get('google_enabled');

        if (!$enabled) {
            show_404();
        }

        $client_id = $this->settings->get('google_client_id');
        $client_secret = $this->settings->get('google_client_secret');

        if ($client_id === '' || $client_secret === '') {
            show_404();
        }

        // Verify state token
        $state = $_GET['state'] ?? '';
        $session_state = $_SESSION['google_oauth_state'] ?? '';

        if ($state !== $session_state) {
            $this->show('login', 'Log in', ['error' => 'Invalid state token.']);
            return;
        }

        // Exchange authorization code for tokens
        $code = $_GET['code'] ?? '';

        $token_url = 'https://oauth2.googleapis.com/token';
        $token_data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => site_url('auth/google_callback'),
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($token_data),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($token_url, false, $context);
        $token_response = json_decode($result, true);

        if (!$token_response || !isset($token_response['access_token'])) {
            $this->show('login', 'Log in', ['error' => 'Failed to obtain Google tokens.']);
            return;
        }

        $access_token = $token_response['access_token'];

        // Get user info from Google
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $access_token;
        $options = ['http' => ['method' => 'GET']];
        $context = stream_context_create($options);
        $result = file_get_contents($user_info_url, false, $context);
        $user_info = json_decode($result, true);

        if (!isset($user_info['email'])) {
            $this->show('login', 'Log in', ['error' => 'Failed to get Google user email.']);
            return;
        }

        $email = $user_info['email'];
        $name = $user_info['name'] ?? '';
        $picture = $user_info['picture'] ?? '';

        // Check if user exists by email
        $user = $this->user_model->find_by_email($email);

        if (!$user) {
            // Create new user with Google email
            $password = password_hash(uniqid(), PASSWORD_DEFAULT);
            $user_id = $this->user_model->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'user',
            ]);

            $user = $this->user_model->find($user_id);
        }

        // Log in the user
        $this->log_in_as($user);
        $this->audit->event('login', 'auth', $user->id, $user->name.' ('.$user->email.')');

        $redirect = $this->session->flashdata('redirect_after_login');
        redirect($redirect ?: 'dashboard');
    }

    /** Verify email with token */
    public function verify_email($id = null, $token = null)
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $user = $this->user_model->find((int) $id);

        if (!$user || $user->email_verification_token !== $token || $user->email_verified) {
            show_404();
        }

        $user->email_verified = 1;
        $user->email_verification_token = '';
        $this->user_model->update($user->id, ['email_verified' => 1, 'email_verification_token' => '']);

        $this->log_in_as($user);
        $this->audit->event('login', 'auth', $user->id, $user->name.' ('.$user->email.')');

        $redirect = $this->session->flashdata('redirect_after_login');
        redirect($redirect ?: 'dashboard');
    }

    /** Forgot password: send reset link via email */
    public function forgot_password()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');

        if (!$this->form_validation->run()) {
            $this->show('auth', 'Forgot Password');
            return;
        }

        $user = $this->user_model->find_by_email($this->input->post('email'));

        if (!$user) {
            $this->show('auth', 'Forgot Password', ['error' => 'No account found with that email address.']);
            return;
        }

        if (!$this->settings->get('password_reset_enabled')) {
            $this->show('auth', 'Forgot Password', ['error' => 'Password reset is currently disabled.']);
            return;
        }

        $token = md5(uniqid().$user->email);
        $user->password_reset_token = $token;
        $user->password_reset_expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        $this->user_model->update($user->id, ['password_reset_token' => $token, 'password_reset_expires' => $user->password_reset_expires]);

        $reset_url = site_url('auth/reset_password/'.$user->id.'/'.$token);

        $this->email->from($this->settings->get('email_from_address') ?: 'no-reply@localhost', $this->settings->get('email_from_name') ?: 'My App');
        $this->email->to($user->email);
        $this->email->subject('Reset your password');
        $this->email->message("Click this link to reset your password: ".site_url('auth/reset_password/'.$user->id.'/'.$token));
        $this->email->send();

        $this->show('auth', 'Forgot Password', ['msg' => 'A password reset link has been sent to your email address.']);
    }

    /** Reset password with token (show form) */
    public function reset_password($id = null, $token = null)
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $user = $this->user_model->find((int) $id);

        if (!$user || $user->password_reset_token !== $token || $user->password_reset_expires < date('Y-m-d H:i:s')) {
            show_404();
        }

        $this->show('auth', 'Reset Password');
    }

    /** Process the new password submission */
    public function do_reset_password($id = null, $token = null)
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }

        $user = $this->user_model->find((int) $id);

        if (!$user || $user->password_reset_token !== $token || $user->password_reset_expires < date('Y-m-d H:i:s')) {
            show_404();
        }

        $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');

        if (!$this->form_validation->run()) {
            $this->show('auth', 'Reset Password');
            return;
        }

        $user->password = password_hash((string) $this->input->post('password'), PASSWORD_DEFAULT);
        $this->user_model->update($user->id, ['password' => $user->password, 'password_reset_token' => '', 'password_reset_expires' => '']);

        $this->log_in_as($user);
        $this->audit->event('login', 'auth', $user->id, $user->name.' ('.$user->email.')');

        $this->show('auth', 'Reset Password', ['msg' => 'Your password has been reset successfully. You can now log in with your new password.']);
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

    /** What the visitor reads when they are (or just became) blocked. */
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