<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('turnstile_widget')) {
    /**
     * Cloudflare Turnstile widget for a form ('login', 'register', ...),
     * or an empty string when it's switched off. Put it just above the
     * submit button. See application/libraries/Turnstile.php.
     */
    function turnstile_widget($form)
    {
        /** @var CI_Controller&object{turnstile: Turnstile} $CI the Turnstile library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        return $CI->turnstile->widget($form);
    }
}
