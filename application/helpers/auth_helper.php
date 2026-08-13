<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Thin session-backed auth helpers used across the app. Not tied to the
 * auth module specifically — any controller can call these once a user is
 * logged in via application/modules/auth.
 */

if (!function_exists('is_logged_in')) {
    function is_logged_in()
    {
        $CI = &get_instance();

        return (bool) $CI->session->userdata('user_id');
    }
}

if (!function_exists('current_user')) {
    function current_user()
    {
        if (!is_logged_in()) {
            return null;
        }

        $CI = &get_instance();

        return [
            'id' => $CI->session->userdata('user_id'),
            'name' => $CI->session->userdata('user_name'),
            'email' => $CI->session->userdata('user_email'),
            'role' => $CI->session->userdata('user_role'),
        ];
    }
}

if (!function_exists('has_role')) {
    function has_role($role)
    {
        $CI = &get_instance();

        return $CI->session->userdata('user_role') === $role;
    }
}

if (!function_exists('require_login')) {
    /**
     * Call at the top of any controller method that needs a logged-in
     * user. Redirects to the login page (remembering where to return to)
     * if there isn't one.
     */
    function require_login()
    {
        if (is_logged_in()) {
            return;
        }

        $CI = &get_instance();
        $CI->session->set_flashdata('redirect_after_login', current_url());
        redirect('login');
    }
}

if (!function_exists('require_role')) {
    function require_role($role)
    {
        require_login();

        if (!has_role($role)) {
            show_error('You do not have permission to view this page.', 403);
        }
    }
}
