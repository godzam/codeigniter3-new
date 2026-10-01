<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Security\TurnstileVerifier;

/**
 * Cloudflare Turnstile for forms, configured on the admin Settings page
 * (Security tab). Usage:
 *
 *   // in the form view
 *   <?php echo turnstile_widget('login') ?>
 *
 *   // in the controller, after the form's own validation
 *   if (!$this->turnstile->verify_for('login')) {
 *       $error = $this->turnstile->error_message();
 *   }
 *
 * "login" and "register" are the built-in form names; any other name N is
 * switched with a "turnstile_on_N" setting (declare it in config/app_settings.php).
 * When Turnstile is off — or its keys are missing — widget() renders nothing
 * and verify_for() passes, so the forms keep working.
 *
 * Verification fails closed: if Cloudflare can't be reached, the form is
 * rejected (and the cause is logged) rather than silently skipped.
 *
 * @property Settings $settings
 */
class Turnstile
{
    public const ORIGIN = 'https://challenges.cloudflare.com';

    /** Failures that aren't the visitor's fault: misconfiguration or an outage. */
    private const OPERATIONAL_ERRORS = ['connection-failed', 'bad-response', 'missing-input-secret', 'invalid-input-secret'];

    protected $CI;

    /** @var array<int, string> */
    protected $errors = [];

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Whether Turnstile is switched on (with both keys) for this form.
     */
    public function enabled($form = null)
    {
        if (!$this->CI->settings->get('turnstile_enabled')
            || trim((string) $this->CI->settings->get('turnstile_site_key')) === ''
            || trim((string) $this->CI->settings->get('turnstile_secret_key')) === '') {
            return false;
        }

        return $form === null || (bool) $this->CI->settings->get('turnstile_on_'.$form, false);
    }

    /**
     * HTML for the widget plus the scripts that render it; empty when
     * Turnstile isn't active for this form. The widget picks its theme at
     * render time (assets/js/turnstile.js), so "auto" follows the site's
     * current light/dark mode.
     */
    public function widget($form)
    {
        if (!$this->enabled($form)) {
            return '';
        }

        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        return '<div class="cf-turnstile mb-3" data-turnstile-widget'
            .' data-sitekey="'.$e($this->CI->settings->get('turnstile_site_key')).'"'
            .' data-theme="'.$e($this->CI->settings->get('turnstile_theme')).'"'
            .' data-size="'.$e($this->CI->settings->get('turnstile_size')).'"></div>'
            .'<noscript><div class="alert alert-warning small">Please enable JavaScript to complete the security check.</div></noscript>'
            .'<script src="'.asset_url('js/turnstile.js').'"></script>'
            .'<script src="'.self::ORIGIN.'/turnstile/v0/api.js?render=explicit&amp;onload=onTurnstileLoad" async defer></script>';
    }

    /**
     * True when Turnstile isn't required for this form, or the submitted
     * token checks out.
     */
    public function verify_for($form)
    {
        if (!$this->enabled($form)) {
            return true;
        }

        return $this->verify($this->CI->input->post('cf-turnstile-response'));
    }

    /**
     * @param string|null $token
     */
    public function verify($token)
    {
        $verifier = new TurnstileVerifier(
            (string) $this->CI->settings->get('turnstile_secret_key'),
            getenv('TURNSTILE_VERIFY_URL') ?: TurnstileVerifier::VERIFY_URL
        );

        $result = $verifier->verify($token, $this->CI->input->ip_address());
        $this->errors = $result['errors'];

        if (!$result['success']) {
            // User mistakes (no/expired/used token) aren't worth a log line;
            // configuration and connectivity problems are.
            $operational = array_intersect($this->errors, self::OPERATIONAL_ERRORS);
            if ($operational) {
                log_message('error', 'Turnstile verification could not run: '.implode(', ', $operational));
            }
        }

        return $result['success'];
    }

    /**
     * @return array<int, string> Cloudflare error codes from the last verify()
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * A message that's safe to show the visitor.
     */
    public function error_message()
    {
        if (array_intersect($this->errors, self::OPERATIONAL_ERRORS)) {
            return 'The security check is temporarily unavailable. Please try again in a moment.';
        }

        return 'Please complete the security check and try again.';
    }

    /**
     * Origins the page must be allowed to load/connect to, for the CSP
     * (application/hooks/SecurityHeaders.php). Empty when Turnstile is off.
     */
    public function csp_origin()
    {
        return $this->enabled() ? self::ORIGIN : '';
    }
}
