<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Applies a conservative set of security headers to every response.
 * Registered on the post_controller_constructor hook (see
 * application/config/hooks.php) so $CI->output is available.
 *
 * The CSP here is deliberately permissive in development — DevelBar
 * renders inline <style>/<script> tags — and stricter in production.
 * Adjust both to match what your app actually loads (CDNs, fonts,
 * analytics, etc.): a CSP that's too strict silently breaks pages, one
 * that's too loose defeats the point. Test with DevTools' console open
 * after any change — CSP violations show up there, not as PHP errors.
 */
require_once APPPATH.'src/autoload.php';

use App\Security\Csp;

class SecurityHeaders
{
    public function apply()
    {
        /** @var CI_Controller&object{turnstile: Turnstile} $CI the Turnstile library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        // Outside development, inline <script>/<style> run only with this
        // request's nonce (csp_nonce_attr() in theme_helper.php).
        $nonce = Csp::nonce();

        $CI->output->set_header('X-Content-Type-Options: nosniff');
        $CI->output->set_header('X-Frame-Options: SAMEORIGIN');
        $CI->output->set_header('Referrer-Policy: strict-origin-when-cross-origin');
        $CI->output->set_header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        $CI->output->set_header('Content-Security-Policy: '.$this->content_security_policy($nonce, $CI->turnstile->csp_origin()));
    }

    /**
     * @param string $nonce  per-request nonce ('' in development)
     * @param string $origin extra origin for the Turnstile widget ('' when it's off)
     */
    private function content_security_policy($nonce, $origin = '')
    {
        // Turnstile loads a script, renders its widget in an iframe, and
        // talks back to Cloudflare, so it needs all three.
        $extra = $origin === '' ? '' : " {$origin}";
        $widget = $origin === '' ? '' : " frame-src {$origin}; connect-src 'self' {$origin};";

        if (ENVIRONMENT === 'development') {
            return "default-src 'self'; script-src 'self' 'unsafe-inline'{$extra}; style-src 'self' 'unsafe-inline'; img-src 'self' data:;{$widget}";
        }

        // style-src-attr allows style="..." attributes (a nonce can't cover
        // those); inline <style> blocks and <script> tags still need the nonce.
        return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'{$extra}; style-src 'self' 'nonce-{$nonce}'; style-src-attr 'unsafe-inline'; img-src 'self' data:;{$widget}";
    }
}
