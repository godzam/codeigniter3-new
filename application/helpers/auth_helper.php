<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Auth helpers used across the app. Not tied to the auth module
 * specifically — any controller or view can call these once a user is
 * logged in via application/modules/auth.
 *
 * Who is logged in comes from the session; what they may do comes from
 * their role in the database (application/libraries/Rbac.php), checked
 * fresh on each request:
 *
 *     can('users.view')                // bool — e.g. to show or hide a button
 *     require_permission('users.view') // top of a controller method: 403 if not
 *     has_role('editor')               // exact role (a super_admin passes any role check)
 *
 * Prefer permissions to role names in code: roles are created and renamed
 * by admins, but "users.view" always means the same thing.
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

        /** @var CI_Controller&object{rbac: Rbac} $CI the Rbac library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        return [
            'id' => $CI->session->userdata('user_id'),
            'name' => $CI->session->userdata('user_name'),
            'email' => $CI->session->userdata('user_email'),
            // The role as it is now, not as it was when the user logged in.
            'role' => $CI->rbac->current_role() ?? $CI->session->userdata('user_role'),
        ];
    }
}

if (!function_exists('has_role')) {
    /**
     * True if the signed-in user has exactly this role — or is a super_admin,
     * the top role, which passes every role check.
     */
    function has_role($role)
    {
        /** @var CI_Controller&object{rbac: Rbac} $CI the Rbac library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        return $CI->rbac->has_role($role);
    }
}

if (!function_exists('is_super_admin')) {
    function is_super_admin()
    {
        /** @var CI_Controller&object{rbac: Rbac} $CI the Rbac library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        return $CI->rbac->is_super_admin();
    }
}

if (!function_exists('can')) {
    /**
     * Whether the signed-in user's role holds this permission ("module.action").
     * False for guests. A super_admin can do everything.
     */
    function can($permission)
    {
        /** @var CI_Controller&object{rbac: Rbac} $CI the Rbac library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        return $CI->rbac->can($permission);
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
            abort(403, 'You do not have permission to view this page.');
        }
    }
}

if (!function_exists('require_permission')) {
    /**
     * Call at the top of any controller method (or constructor) that needs a
     * permission: sends guests to the login page and everyone else without
     * the permission to a 403 page (JSON for API requests).
     */
    function require_permission($permission)
    {
        require_login();

        if (!can($permission)) {
            abort(403, 'You do not have permission to view this page.');
        }
    }
}

if (!function_exists('google_login_enabled')) {
    /**
     * Whether the "Continue with Google" button should be shown: the switch is on
     * and both client keys are saved (Settings -> Accounts & e-mail).
     */
    function google_login_enabled()
    {
        return (bool) app_setting('google_enabled')
            && trim((string) app_setting('google_client_id')) !== ''
            && trim((string) app_setting('google_client_secret')) !== '';
    }
}

if (!function_exists('google_login_button')) {
    /**
     * The button (and the "or" line above it) for the login and register pages. A plain
     * link: the sign-in starts on the server, so no script is needed (and the strict
     * Content-Security-Policy stays as it is).
     */
    function google_login_button($label = 'Continue with Google')
    {
        if (!google_login_enabled()) {
            return '';
        }

        return '<div class="auth-or" role="separator"><span>or</span></div>'
            .'<a href="'.htmlspecialchars(site_url('google/login'), ENT_QUOTES, 'UTF-8').'" class="btn btn-outline-secondary w-100 btn-google">'
            .'<i class="bi bi-google me-2"></i>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a>';
    }
}

if (!function_exists('password_reset_enabled')) {
    function password_reset_enabled()
    {
        return (bool) app_setting('password_reset_enabled');
    }
}
