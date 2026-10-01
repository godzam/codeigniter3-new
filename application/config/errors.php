<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Error handling
| -------------------------------------------------------------------------
| Settings for application/core/MY_Exceptions.php — the CI4/Laravel-style
| error handler. Error page templates live in
| application/views/errors/html/ ({status}.php for a specific status code,
| e.g. 404.php/500.php, falling back to minimal.php), and the development
| exception page is application/views/errors/html/debug.php.
|
| This file is read directly by the error handler (not through
| $this->config), because errors can happen before the Config class is
| ready — keep it free of calls into CodeIgniter.
*/

/*
| 'debug'
|
| TRUE shows the detailed exception page (stack trace, code snippets,
| request data). FALSE shows the friendly error pages instead. NULL
| (the default) follows PHP's display_errors setting, which index.php
| turns on for 'development' and off for 'testing'/'production'.
|
| NEVER enable this in production: the debug page exposes source code,
| file paths, and request data.
*/
$config['debug'] = NULL;

/*
| 'editor'
|
| Makes file paths on the debug page clickable, opening the file at the
| right line in your editor. One of: 'vscode', 'vscodium', 'cursor',
| 'phpstorm', 'idea', 'sublime', 'atom', 'textmate', 'emacs', 'macvim',
| 'nova', or '' to disable. Can also be set with the ERROR_EDITOR
| environment variable (.env).
*/
$config['editor'] = isset($_ENV['ERROR_EDITOR']) ? $_ENV['ERROR_EDITOR'] : (getenv('ERROR_EDITOR') ?: 'vscode');

/*
| 'dont_report'
|
| Exception classes (including subclasses) that are never written to the
| error log — Laravel's $dontReport. App\Exceptions\HttpException with a
| status below 500 (abort(404), abort(403), ...) is never logged either,
| regardless of this list.
*/
$config['dont_report'] = array();

/*
| 'hidden_keys'
|
| Request/server/session values whose key contains any of these strings
| (case-insensitive) are masked on the debug page.
*/
$config['hidden_keys'] = array(
	'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
	'auth', 'cookie', 'csrf', 'session', 'private', 'credential',
);
