<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Replacements for CI3's global error handlers (system/core/Common.php).
 *
 * CI3 declares _error_handler(), _exception_handler() and
 * _shutdown_handler() inside function_exists() checks precisely so an
 * application can supply its own; index.php loads this file before
 * CodeIgniter.php so these win. They hand off to MY_Exceptions, which
 * renders the debug page / error pages / JSON responses.
 *
 * Behavior kept from CI3: which errors are logged, non-fatal errors
 * (warnings, notices, deprecations) shown inline only when display_errors
 * is on, and the same exit codes. What changes: uncaught exceptions and
 * fatal errors always produce a response — the debug page in
 * development, a proper 500 (or the HttpException's status) page
 * otherwise — instead of CI3's blank page in production.
 */

/*
 * With display_errors on, PHP prints fatal errors itself before any
 * handler runs, which sends the response headers (a 200) and puts raw
 * output above the error page. Remember what index.php asked for, so the
 * handlers below and MY_Exceptions can honor it, then switch PHP's own
 * output off and let the handlers do all the displaying.
 */
if (!defined('DISPLAY_ERRORS')) {
    define('DISPLAY_ERRORS', (bool) str_ireplace(['off', 'none', 'no', 'false', 'null'], '', (string) ini_get('display_errors')));
    ini_set('display_errors', '0');
}

if (!function_exists('_error_handler')) {
    function _error_handler($severity, $message, $filepath, $line)
    {
        $is_error = (((E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

        if ($is_error) {
            set_status_header(500);
        }

        // Ignore errors excluded by error_reporting() (including @-suppressed ones).
        if (($severity & error_reporting()) !== $severity) {
            return;
        }

        $_error = &load_class('Exceptions', 'core');
        $_error->log_exception($severity, $message, $filepath, $line);

        if ($is_error && $_error instanceof MY_Exceptions) {
            $_error->handle_fatal_error($severity, $message, $filepath, $line);
            exit(1); // EXIT_ERROR
        }

        if (DISPLAY_ERRORS) {
            $_error->show_php_error($severity, $message, $filepath, $line);
        }

        if ($is_error) {
            exit(1); // EXIT_ERROR
        }
    }
}

if (!function_exists('_exception_handler')) {
    function _exception_handler($exception)
    {
        $_error = &load_class('Exceptions', 'core');

        if ($_error instanceof MY_Exceptions) {
            $_error->handle_exception($exception);
            exit(1); // EXIT_ERROR
        }

        $_error->log_exception('error', 'Exception: '.$exception->getMessage(), $exception->getFile(), $exception->getLine());

        is_cli() or set_status_header(500);
        if (DISPLAY_ERRORS) {
            $_error->show_exception($exception);
        }

        exit(1); // EXIT_ERROR
    }
}

if (!function_exists('_shutdown_handler')) {
    function _shutdown_handler()
    {
        $last_error = error_get_last();
        if (isset($last_error)
            && ($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))) {
            _error_handler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
        }
    }
}
