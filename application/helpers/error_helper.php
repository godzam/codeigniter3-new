<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/Exceptions/bootstrap.php';

use App\Exceptions\HttpException;
use App\Exceptions\PageNotFoundException;

/**
 * Laravel-style abort helpers. Each one throws an HttpException, which
 * MY_Exceptions turns into the matching error page — or a JSON body for
 * AJAX/API requests — so there's no need to return early from the
 * calling code:
 *
 *     abort(404);
 *     abort(403, 'You cannot edit this post.');
 *     abort_if(!$post, 404, 'Post not found.');
 *     abort_unless(has_role('admin'), 403);
 */

if (!function_exists('abort')) {
    /**
     * @param array<string, string> $headers
     */
    function abort($code, $message = '', array $headers = [])
    {
        if ((int) $code === 404) {
            throw new PageNotFoundException($message, $headers);
        }

        throw new HttpException($code, $message, $headers);
    }
}

if (!function_exists('abort_if')) {
    /**
     * @param array<string, string> $headers
     */
    function abort_if($condition, $code, $message = '', array $headers = [])
    {
        if ($condition) {
            abort($code, $message, $headers);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * @param array<string, string> $headers
     */
    function abort_unless($condition, $code, $message = '', array $headers = [])
    {
        if (!$condition) {
            abort($code, $message, $headers);
        }
    }
}
