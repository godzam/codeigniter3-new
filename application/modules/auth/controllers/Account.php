<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Auth\Tokens;

/**
 * Everything that works with an e-mailed link: confirming an address, asking
 * for a new confirmation link, and resetting a forgotten password.
 *
 * Routes (application/config/routes.php):
 *   GET|POST /forgot-password        ask for a reset link             (Settings -> Accounts & e-mail)
 *   GET|POST /reset-password/{token} choose a new password
 *   GET|POST /verify-email/{token}   confirm an address (GET shows a button, POST confirms)
 *   POST     /resend-verification    send a new confirmation link
 *
 * Rules that hold throughout:
 *  - Links carry a 64-hex-character random token; only its SHA-256 hash is stored (Usertokens).
 *  - What the visitor reads never depends on whether an address has an account.
 *  - Asking is rate limited per IP address and per address.
 *  - Following a link never logs anybody in; they log in with their password afterwards.
 *  - A confirmation link needs a POST, so mail scanners that open links cannot use it up.
 *
 * @property User_model $user_model
 * @property Usertokens $usertokens
 * @property Accountlinks $accountlinks
 * @property Ratelimiter $ratelimiter
 * @property Audit $audit
 * @property Settings $settings
 * @property Turnstile $turnstile
 * @property Template $template
 * @property CI_Form_validation $form_validation
 * @property CI_Input $input
 * @property CI_Output $output
 * @property CI_Session $session
 */
class Account extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->library(['form_validation', 'ratelimiter', 'session', 'usertokens', 'accountlinks']);
        $this->load->helper(['form', 'url']);
    }

    // ------------------------------------------------------------------
    // Forgot password
    // ------------------------------------------------------------------

    public function forgot()
    {
        $this->feature('password_reset_enabled');

        if ($this->input->method() !== 'post') {
            $this->show('forgot_password', 'Forgot your password?');

            return;
        }

        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[190]');
        if (!$this->form_validation->run()) {
            $this->show('forgot_password', 'Forgot your password?');

            return;
        }

        if (!$this->turnstile->verify_for('forgot')) {
            $this->show('forgot_password', 'Forgot your password?', ['error' => $this->turnstile->error_message()]);

            return;
        }

        $email = strtolower(trim((string) $this->input->post('email')));
        if (!$this->allowed('forgot', $email, 5, 3)) {
            $this->show('forgot_password', 'Forgot your password?', ['error' => 'Too many requests. Please try again later.']);

            return;
        }

        $user = $this->user_model->find_by_email($email);
        if ($user) {
            $sent = $this->accountlinks->send_reset($user);
            $this->audit->event('password.reset_requested', 'users', $user->id, $user->name.' ('.$user->email.')', ['mail_sent' => $sent ? 'yes' : 'no']);
        }

        // The same answer whether or not the address has an account.
        $this->show('forgot_password', 'Forgot your password?', ['msg' => 'If that address belongs to an account, a link to reset the password is on its way. It works for 1 hour.']);
    }

    public function reset($token = '')
    {
        $this->feature('password_reset_enabled');
        $this->private_page();

        $userId = $this->usertokens->peek(Tokens::RESET_PASSWORD, $token);
        if ($userId === null) {
            $this->show('link_invalid', 'Link not valid', ['what' => 'password reset']);

            return;
        }

        if ($this->input->method() !== 'post') {
            $this->show('reset_password', 'Choose a new password', ['token' => $token]);

            return;
        }

        $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[255]');
        $this->form_validation->set_rules('password_confirm', 'Password confirmation', 'required|matches[password]');
        if (!$this->form_validation->run()) {
            $this->show('reset_password', 'Choose a new password', ['token' => $token]);

            return;
        }

        if (!$this->ratelimiter->attempt('reset-ip:'.$this->input->ip_address(), 10, 3600)) {
            $this->show('reset_password', 'Choose a new password', ['token' => $token, 'error' => 'Too many attempts. Please try again later.']);

            return;
        }

        // Use the link up first: if two requests race, only one gets the user.
        $userId = $this->usertokens->consume(Tokens::RESET_PASSWORD, $token);
        $user = $userId === null ? null : $this->user_model->find($userId);
        if (!$user) {
            $this->show('link_invalid', 'Link not valid', ['what' => 'password reset']);

            return;
        }

        $changes = ['password' => password_hash((string) $this->input->post('password'), PASSWORD_DEFAULT)];
        // Whoever can read this mailbox owns the address, so it counts as confirmed now.
        if ($this->user_model->has_verification() && empty($user->email_verified_at)) {
            $changes['email_verified_at'] = date('Y-m-d H:i:s');
        }
        $this->user_model->update($user->id, $changes);
        $this->usertokens->revoke($user->id, Tokens::RESET_PASSWORD);
        $this->audit->event('password.reset', 'users', $user->id, $user->name.' ('.$user->email.')');

        flash('success', 'Your password was changed. Log in with the new one.');
        redirect('login');
    }

    // ------------------------------------------------------------------
    // E-mail verification
    // ------------------------------------------------------------------

    public function verify($token = '')
    {
        $this->private_page();
        if (!$this->usertokens->available()) {
            show_404();
        }

        $userId = $this->usertokens->peek(Tokens::VERIFY_EMAIL, $token);
        if ($userId === null) {
            $this->show('link_invalid', 'Link not valid', ['what' => 'confirmation']);

            return;
        }

        if ($this->input->method() !== 'post') {
            $this->show('verify_email', 'Confirm your email', ['token' => $token]);

            return;
        }

        $userId = $this->usertokens->consume(Tokens::VERIFY_EMAIL, $token);
        $user = $userId === null ? null : $this->user_model->find($userId);
        if (!$user) {
            $this->show('link_invalid', 'Link not valid', ['what' => 'confirmation']);

            return;
        }

        if (empty($user->email_verified_at)) {
            $this->user_model->update($user->id, ['email_verified_at' => date('Y-m-d H:i:s')]);
            $this->audit->event('email.verified', 'users', $user->id, $user->name.' ('.$user->email.')');
        }

        flash('success', 'Your email address is confirmed. You can log in now.');
        redirect('login');
    }

    public function resend()
    {
        if ($this->input->method() !== 'post') {
            redirect('login');
        }
        $this->feature('email_verification_enabled');

        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[190]');
        if (!$this->form_validation->run()) {
            flash('error', 'Enter a valid email address.');
            redirect('login');
        }

        $email = strtolower(trim((string) $this->input->post('email')));
        if (!$this->allowed('resend', $email, 5, 3)) {
            flash('error', 'Too many requests. Please try again later.');
            redirect('login');
        }

        $user = $this->user_model->find_by_email($email);
        if ($user && empty($user->email_verified_at)) {
            $this->accountlinks->send_verification($user);
        }

        flash('success', 'If that address belongs to an account that still needs confirming, a new link is on its way.');
        redirect('login');
    }

    // ------------------------------------------------------------------

    /**
     * 404 unless the feature is switched on and its tables exist; signed-in people have no use for these pages.
     */
    protected function feature($setting)
    {
        if (!$this->settings->get($setting) || !$this->usertokens->available()) {
            show_404();
        }
        if (is_logged_in()) {
            redirect('dashboard');
        }
    }

    /**
     * One attempt counts against the IP address and against the address typed, answering whether both are within limits.
     */
    protected function allowed($kind, $email, $perIp, $perEmail)
    {
        $ip = $this->ratelimiter->attempt($kind.'-ip:'.$this->input->ip_address(), $perIp, 3600);
        $mail = $this->ratelimiter->attempt($kind.'-mail:'.sha1($email), $perEmail, 3600);

        return $ip && $mail;
    }

    /**
     * Pages whose address contains a secret must not be cached or leak it through the Referer header.
     */
    protected function private_page()
    {
        $this->output->set_header('Cache-Control: no-store')->set_header('Referrer-Policy: no-referrer');
    }

    protected function show($view, $title, array $data = [])
    {
        $this->template->set_title($title)->render($view, $data, 'auth');
    }
}
