<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Auth\GoogleOAuth;

/**
 * "Continue with Google" (OpenID Connect, authorization-code flow with PKCE;
 * the protocol side is App\Auth\GoogleOAuth).
 *
 * Routes (application/config/routes.php):
 *   GET /google/login     sends the visitor to Google
 *   GET /google/callback  where Google sends them back (register this exact URL as the
 *                         "authorized redirect URI" of the OAuth client)
 *
 * What is checked, in this order, before anybody is signed in:
 *   - the feature is on and both client keys are saved (otherwise 404);
 *   - the one-time sign-in session we started exists, is under 10 minutes old and is used up now;
 *   - `state` equals the one we generated (constant-time), and `code` is present;
 *   - the ID token Google hands over has our client id, is not expired, repeats our `nonce`,
 *     and Google says the e-mail address is verified;
 *   - the account is not blocked by the login-security module.
 *
 * Who gets in:
 *   - an account already linked to this Google id;
 *   - else the account with the same e-mail address, which then gets linked. If that account
 *     never confirmed its address, its password is also replaced by a random one (whoever
 *     registered it first does not get to keep a way in);
 *   - else a new account with the default role, a random password and a confirmed address.
 *
 * @property User_model $user_model
 * @property Loginguard $loginguard
 * @property Audit $audit
 * @property Rbac $rbac
 * @property Settings $settings
 * @property CI_Input $input
 * @property CI_Session $session
 */
class Google_auth extends MX_Controller
{
    private const SESSION_KEY = 'google_oauth';
    private const MAX_AGE = 600;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->library(['session', 'loginguard']);
        $this->load->helper(['url']);
    }

    public function login()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }
        $oauth = $this->client();

        $state = GoogleOAuth::randomToken();
        $nonce = GoogleOAuth::randomToken();
        $verifier = GoogleOAuth::newVerifier();
        $this->session->set_userdata(self::SESSION_KEY, ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'at' => time()]);

        $this->output->set_header('Cache-Control: no-store');
        redirect($oauth->authorizationUrl($state, $nonce, $verifier), 'location', 302);
    }

    public function callback()
    {
        if (is_logged_in()) {
            redirect('dashboard');
        }
        $oauth = $this->client();

        // The sign-in we started is single use, whatever happens next.
        $started = $this->session->userdata(self::SESSION_KEY);
        $this->session->unset_userdata(self::SESSION_KEY);

        $this->output->set_header('Cache-Control: no-store');

        $state = $this->input->get('state');
        if (!is_array($started) || !isset($started['state'], $started['nonce'], $started['verifier'], $started['at'])
            || (time() - (int) $started['at']) > self::MAX_AGE
            || !is_string($state) || $state === '' || !hash_equals((string) $started['state'], $state)) {
            $this->fail('Your Google sign-in expired or did not start here. Please try again.', 'state');
        }

        if ($this->input->get('error') !== null) {
            $this->fail('Google sign-in was cancelled.', 'google said: '.substr((string) $this->input->get('error'), 0, 40));
        }

        $code = $this->input->get('code');
        if (!is_string($code) || $code === '' || strlen($code) > 2048) {
            $this->fail('Google did not return a sign-in code. Please try again.', 'no code');
        }

        $result = $oauth->exchange($code, (string) $started['verifier']);
        $claims = $result['claims'];
        if ($claims === null) {
            $this->fail('Google could not be reached or refused the sign-in. Please try again.', $result['error']);
        }

        $problem = $oauth->validate($claims, (string) $started['nonce'], time());
        if ($problem !== '') {
            $this->fail($problem === 'e-mail not verified by Google'
                ? 'Google has not verified that email address, so it cannot be used to sign in.'
                : 'Google sign-in failed. Please try again.', $problem);
        }

        $user = $this->resolve_user($claims);

        $guard = $this->loginguard;
        if ($guard->available() && $guard->seconds_blocked((int) $user->id) > 0) {
            $this->fail('Too many failed login attempts. Please try again later.', 'blocked');
        }

        $this->session->set_userdata([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
        ]);
        $this->audit->event('login', 'auth', $user->id, $user->name.' ('.$user->email.')', ['via' => 'google']);

        $redirect = $this->session->flashdata('redirect_after_login');
        redirect($redirect ?: 'dashboard');
    }

    /**
     * Finds, links or creates the account for validated claims.
     *
     * @param array<string, mixed> $claims
     *
     * @return object
     */
    protected function resolve_user(array $claims)
    {
        $sub = (string) $claims['sub'];
        $email = strtolower((string) $claims['email']);
        $now = date('Y-m-d H:i:s');

        $user = $this->user_model->find_by_google_sub($sub);
        if ($user) {
            return $user;
        }

        $user = $this->user_model->find_by_email($email);
        if ($user) {
            if (!empty($user->google_sub)) {
                // The address belongs to an account tied to a different Google identity.
                $this->fail('That email address is linked to another Google account.', 'sub mismatch for user '.$user->id);
            }

            $changes = ['google_sub' => $sub];
            if (empty($user->email_verified_at)) {
                $changes['email_verified_at'] = $now;
                $changes['password'] = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            }
            $this->user_model->update($user->id, $changes);
            $this->audit->event('google.linked', 'users', $user->id, $user->name.' ('.$user->email.')', [
                'password_replaced' => isset($changes['password']) ? 'yes' : 'no',
            ]);

            return $this->user_model->find($user->id);
        }

        $name = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($claims['name'] ?? ''))));
        $name = mb_substr($name !== '' ? $name : strstr($email, '@', true), 0, 100);

        $id = $this->user_model->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'role' => $this->rbac->default_role_slug(),
            'email_verified_at' => $now,
            'google_sub' => $sub,
        ]);
        $created = $this->user_model->find($id);
        $this->audit->created('users', $id, $created->name.' ('.$created->email.')', ['name' => $created->name, 'email' => $created->email, 'role' => $created->role, 'via' => 'google']);

        return $created;
    }

    /**
     * @return GoogleOAuth
     */
    protected function client()
    {
        $id = trim((string) $this->settings->get('google_client_id'));
        $secret = trim((string) $this->settings->get('google_client_secret'));

        if (!$this->settings->get('google_enabled') || $id === '' || $secret === '' || !$this->user_model->has_verification()) {
            show_404();
        }

        // Test servers only: point the flow at a fake identity provider.
        $auth = $token = $issuers = null;
        if (ENVIRONMENT !== 'production' && getenv('GOOGLE_TEST_BASE')) {
            $base = rtrim((string) getenv('GOOGLE_TEST_BASE'), '/');
            $auth = $base.'/auth';
            $token = $base.'/token';
            $issuers = [$base];
        }

        return new GoogleOAuth($id, $secret, site_url('google/callback'), $auth, $token, null, $issuers);
    }

    /**
     * Logs the real reason, shows a plain one, and goes back to the login page.
     */
    protected function fail($message, $reason)
    {
        log_message('error', 'Google sign-in refused: '.$reason);
        flash('error', $message);
        redirect('login');
    }
}
