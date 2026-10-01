<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Security\Csp;
use App\Theme\Color;

/**
 * View helpers for the Bootstrap 5 template: asset URLs, the <head>/footer
 * includes, the theme CSS generated from Settings (primary/secondary
 * color, corner roundness, font, light/dark mode), and flash messages
 * shown with SweetAlert2. Used by the layouts in application/views/layouts/.
 */

if (!function_exists('app_setting')) {
    /**
     * Shortcut for $this->settings->get() that works in any view.
     */
    function app_setting($key, $default = null)
    {
        /** @var CI_Controller&object{settings: Settings} $CI the Settings library is autoloaded (config/autoload.php) */
        $CI = &get_instance();

        return $CI->settings->get($key, $default);
    }
}

if (!function_exists('asset_url')) {
    /**
     * URL of a file under assets/, with its modification time appended so
     * browsers fetch it again whenever it changes.
     */
    function asset_url($path)
    {
        $path = ltrim($path, '/');
        $file = FCPATH.'assets/'.$path;

        return base_url('assets/'.$path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (!function_exists('csp_nonce_attr')) {
    /**
     * ' nonce="..."' for an inline <script>/<style> tag, so it runs under
     * the production Content-Security-Policy (application/hooks/
     * SecurityHeaders.php). Empty in development, where 'unsafe-inline'
     * is already allowed.
     */
    function csp_nonce_attr()
    {
        $nonce = Csp::nonce();

        return $nonce === '' ? '' : ' nonce="'.htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8').'"';
    }
}

if (!function_exists('theme_css_vars')) {
    /**
     * The <style> block that turns the saved settings into Bootstrap CSS
     * variables, for both the light and the dark theme.
     */
    function theme_css_vars()
    {
        $light = $dark = [];

        foreach (['primary' => 'theme_primary', 'secondary' => 'theme_secondary'] as $name => $setting) {
            $palette = Color::palette($name, app_setting($setting));
            $light += $palette['light'];
            $dark += $palette['dark'];
        }

        // Links follow the primary color.
        foreach ([&$light, &$dark] as &$vars) {
            $vars['--bs-link-color'] = $vars['--bs-primary'];
            $vars['--bs-link-color-rgb'] = $vars['--bs-primary-rgb'];
            $vars['--bs-link-hover-color'] = $vars['--app-primary-hover'];
            $vars['--bs-focus-ring-color'] = 'rgba('.$vars['--bs-primary-rgb'].', .25)';
        }
        unset($vars);

        $radius = [
            'none' => ['0', '0', '0', '0', '0'],
            'sm' => ['.2rem', '.15rem', '.3rem', '.4rem', '.6rem'],
            'lg' => ['.6rem', '.4rem', '.9rem', '1.3rem', '2rem'],
        ][app_setting('theme_radius')] ?? null;

        $shared = [];
        if ($radius !== null) {
            $shared = [
                '--bs-border-radius' => $radius[0], '--bs-border-radius-sm' => $radius[1], '--bs-border-radius-lg' => $radius[2],
                '--bs-border-radius-xl' => $radius[3], '--bs-border-radius-xxl' => $radius[4],
            ];
        }

        $fonts = [
            'serif' => 'Georgia, Cambria, "Times New Roman", Times, serif',
            'mono' => 'SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace',
        ];
        if (isset($fonts[app_setting('theme_font')])) {
            $shared['--bs-body-font-family'] = $fonts[app_setting('theme_font')];
        }

        $block = static function ($selector, array $vars) {
            $css = '';
            foreach ($vars as $name => $value) {
                $css .= $name.':'.$value.';';
            }

            return $selector.'{'.$css.'}';
        };

        return '<style id="app-theme-vars"'.csp_nonce_attr().'>'
            .$block(':root,[data-bs-theme=light]', $light + $shared)
            .$block('[data-bs-theme=dark]', $dark)
            .'</style>';
    }
}

if (!function_exists('theme_mode_script')) {
    /**
     * Inline script, placed in <head> so the right color mode is applied
     * before the first paint (no light flash on a dark page). Mode comes
     * from the visitor's saved choice, if toggling is allowed, else from
     * the default set in Settings; 'auto' follows the operating system.
     */
    function theme_mode_script()
    {
        $mode = in_array(app_setting('theme_mode'), ['light', 'dark', 'auto'], true) ? app_setting('theme_mode') : 'auto';
        $allow = app_setting('theme_allow_toggle') ? 'true' : 'false';

        return '<script'.csp_nonce_attr().'>(function(){var d="'.$mode.'",a='.$allow.',s=null;'
            .'try{if(a)s=localStorage.getItem("app-theme")}catch(e){}'
            .'var m=(s==="light"||s==="dark"||s==="auto")?s:d,q=window.matchMedia("(prefers-color-scheme: dark)");'
            .'function f(){document.documentElement.setAttribute("data-bs-theme",m==="auto"?(q.matches?"dark":"light"):m)}'
            .'f();if(m==="auto"&&q.addEventListener)q.addEventListener("change",f)})();</script>';
    }
}

if (!function_exists('theme_head')) {
    /**
     * Everything that goes inside <head> after the title: color-mode
     * script, stylesheets, and the generated theme variables.
     */
    function theme_head()
    {
        return implode("\n\t", [
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            theme_mode_script(),
            '<link rel="stylesheet" href="'.asset_url('vendor/bootstrap/css/bootstrap.min.css').'">',
            '<link rel="stylesheet" href="'.asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css').'">',
            '<link rel="stylesheet" href="'.asset_url('vendor/sweetalert2/sweetalert2.min.css').'">',
            '<link rel="stylesheet" href="'.asset_url('css/app.css').'">',
            theme_css_vars(),
        ])."\n";
    }
}

if (!function_exists('theme_foot')) {
    /**
     * Scripts for the end of <body>, plus the pending flash messages
     * (shown by assets/js/app.js as SweetAlert2 toasts).
     */
    function theme_foot()
    {
        $flash = json_encode(flash_messages(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return '<script src="'.asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js').'"></script>'."\n\t"
            .'<script src="'.asset_url('vendor/sweetalert2/sweetalert2.min.js').'"></script>'."\n\t"
            .'<script'.csp_nonce_attr().'>window.AppFlash = '.$flash.';</script>'."\n\t"
            .'<script src="'.asset_url('js/app.js').'"></script>'."\n";
    }
}

if (!function_exists('flash')) {
    /**
     * Queues a message for the next page, shown as a SweetAlert2 toast.
     * $type: success, error, warning, info.
     */
    function flash($type, $message)
    {
        $CI = &get_instance();
        $messages = $CI->session->flashdata('app_flash') ?: [];
        $messages[] = ['type' => $type, 'message' => (string) $message];
        $CI->session->set_flashdata('app_flash', $messages);
    }
}

if (!function_exists('flash_messages')) {
    /**
     * Messages queued with flash() on the previous request.
     *
     * @return array<int, array{type: string, message: string}>
     */
    function flash_messages()
    {
        $CI = &get_instance();

        return isset($CI->session) ? ($CI->session->flashdata('app_flash') ?: []) : [];
    }
}

if (!function_exists('app_brand')) {
    /**
     * Logo (if set) and application name, for the navbar/sidebar/login.
     */
    function app_brand($with_name = true)
    {
        $logo = trim((string) app_setting('app_logo'));
        $name = htmlspecialchars((string) app_setting('app_name'), ENT_QUOTES, 'UTF-8');
        $html = '';

        if ($logo !== '') {
            $src = preg_match('#^(https?:)?//#', $logo) ? $logo : base_url($logo);
            $html .= '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" alt="" height="28" class="me-2">';
        }

        if ($logo === '') {
            // Compact sidebar shows just the first letter (CSS toggles it).
            $html .= '<span class="brand-initial">'.htmlspecialchars(mb_strtoupper(mb_substr((string) app_setting('app_name'), 0, 1)), ENT_QUOTES, 'UTF-8').'</span>';
        }

        return $html.($with_name || $logo === '' ? '<span class="brand-name">'.$name.'</span>' : '');
    }
}

if (!function_exists('theme_toggle')) {
    /**
     * Light/Dark/Auto dropdown. Renders nothing when visitors aren't
     * allowed to switch (Settings → Appearance).
     */
    function theme_toggle($class = '')
    {
        if (!app_setting('theme_allow_toggle')) {
            return '';
        }

        $items = ['light' => ['bi-sun-fill', 'Light'], 'dark' => ['bi-moon-stars-fill', 'Dark'], 'auto' => ['bi-circle-half', 'Auto']];
        $html = '<div class="dropdown '.$class.'"><button class="btn btn-link nav-link px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Toggle color mode">'
            .'<i class="bi bi-circle-half theme-icon-active"></i></button><ul class="dropdown-menu dropdown-menu-end">';

        foreach ($items as $mode => [$icon, $label]) {
            $html .= '<li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-theme-choice="'.$mode.'"><i class="bi '.$icon.'"></i>'.$label.'</button></li>';
        }

        return $html.'</ul></div>';
    }
}

if (!function_exists('menu_items')) {
    /**
     * Sidebar items from application/config/menu.php that the current user
     * may see, each with an 'active' flag for the current URL.
     *
     * @return array<int, array<string, mixed>>
     */
    function menu_items()
    {
        $CI = &get_instance();
        $CI->config->load('menu', true, true);
        $items = $CI->config->item('menu', 'menu') ?: [];
        $current = trim($CI->uri->uri_string(), '/');

        $visible = static function (array $items) use (&$visible, $current) {
            $out = [];
            foreach ($items as $item) {
                if (isset($item['role']) && !has_role($item['role'])) {
                    continue;
                }
                if (isset($item['permission']) && !can($item['permission'])) {
                    continue;
                }

                if (!empty($item['children'])) {
                    $item['children'] = $visible($item['children']);
                }

                $url = trim($item['url'] ?? '', '/');
                $item['active'] = $url !== '' && ($current === $url || strpos($current, $url.'/') === 0)
                    || !empty(array_filter($item['children'] ?? [], static function ($c) {
                        return !empty($c['active']);
                    }));
                $out[] = $item;
            }

            return $out;
        };

        return $visible($items);
    }
}
