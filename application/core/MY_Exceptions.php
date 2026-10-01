<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/Exceptions/bootstrap.php';

use App\Exceptions\HttpException;

/**
 * CI4/Laravel-style error handling for CodeIgniter 3.
 *
 * - Uncaught exceptions and fatal errors get a detailed debug page in
 *   development (stack trace with code snippets, request data, app
 *   info) and a clean error page everywhere else — instead of CI3's
 *   inline box in development and a blank page in production.
 * - Error pages are resolved per status code, Laravel-style:
 *   views/errors/html/{status}.php, then {4xx,5xx}.php, then minimal.php.
 *   show_404(), show_error(), CSRF failures, and abort() all use them.
 * - abort()/HttpException (see application/helpers/error_helper.php)
 *   end the request with any status code from anywhere in the app.
 * - AJAX/API requests (X-Requested-With or Accept: application/json)
 *   get a JSON error body instead of HTML.
 *
 * Wired in by application/core/error_handlers.php (loaded from
 * index.php), which replaces CI3's global error/exception handlers.
 * Settings live in application/config/errors.php.
 */
class MY_Exceptions extends CI_Exceptions
{
    /** @var array<string, mixed>|null */
    protected $settings;

    /** @var array<int, string> */
    protected static $status_texts = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        413 => 'Payload Too Large',
        415 => 'Unsupported Media Type',
        419 => 'Page Expired',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        429 => 'Too Many Requests',
        500 => 'Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    /** @var array<string, string> */
    protected static $editors = [
        'vscode' => 'vscode://file/%file:%line',
        'vscodium' => 'vscodium://file/%file:%line',
        'cursor' => 'cursor://file/%file:%line',
        'phpstorm' => 'phpstorm://open?file=%file&line=%line',
        'idea' => 'idea://open?file=%file&line=%line',
        'sublime' => 'subl://open?url=file://%file&line=%line',
        'atom' => 'atom://core/open/file?filename=%file&line=%line',
        'textmate' => 'txmt://open?url=file://%file&line=%line',
        'emacs' => 'emacs://open?url=file://%file&line=%line',
        'macvim' => 'mvim://open/?url=file://%file&line=%line',
        'nova' => 'nova://core/open/file?filename=%file&line=%line',
    ];

    // --------------------------------------------------------------------
    // Entry points (called from application/core/error_handlers.php)
    // --------------------------------------------------------------------

    /**
     * Handles an uncaught exception: logs it (unless it shouldn't be),
     * then renders the right response. The caller exits afterwards.
     */
    public function handle_exception(\Throwable $exception)
    {
        $this->report($exception);

        if (is_cli()) {
            $this->render_cli($exception);

            return;
        }

        $this->render($exception);
    }

    /**
     * Handles a fatal PHP error (E_ERROR, E_PARSE, ...) the same way as
     * an uncaught exception. Logging has already been done by
     * _error_handler().
     */
    public function handle_fatal_error($severity, $message, $filepath, $line)
    {
        $exception = new \ErrorException($message, 0, $severity, $filepath, $line);

        if (is_cli()) {
            if ($this->is_debug()) {
                $this->show_php_error($severity, $message, $filepath, $line);
            }

            return;
        }

        $this->render($exception);
    }

    // --------------------------------------------------------------------
    // Reporting
    // --------------------------------------------------------------------

    public function report(\Throwable $exception)
    {
        if (!$this->should_report($exception)) {
            return;
        }

        log_message('error', sprintf(
            "Uncaught %s: %s in %s:%d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));
    }

    public function should_report(\Throwable $exception)
    {
        if ($exception instanceof HttpException && $exception->getStatusCode() < 500) {
            return false;
        }

        foreach ((array) $this->setting('dont_report', []) as $class) {
            if ($exception instanceof $class) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------
    // Rendering
    // --------------------------------------------------------------------

    /**
     * Sends the full error response for an exception: status code,
     * headers, and a JSON body, the debug page, or an error page.
     */
    public function render(\Throwable $exception)
    {
        $is_http = $exception instanceof HttpException;
        $status = $this->normalize_status($is_http ? $exception->getStatusCode() : 500);

        $this->clean_output_buffers();

        if (!headers_sent()) {
            set_status_header($status, $this->status_text($status));

            if ($is_http) {
                foreach ($exception->getHeaders() as $name => $value) {
                    header($name.': '.$value);
                }
            }
        }

        // An HttpException's message is meant for the user; anything
        // else might leak internals, so only the debug page shows it.
        $message = $is_http ? $exception->getMessage() : '';

        if ($this->wants_json()) {
            echo $this->render_json($status, $message, $is_http ? null : $exception);

            return;
        }

        if ($this->is_debug() && !$is_http) {
            echo $this->render_debug_page($exception, $status);

            return;
        }

        echo $this->render_error_page($status, $message);
    }

    /**
     * Overrides CI3's show_error() — used by show_error(), show_404(), the
     * CSRF check, and the database layer — so they all render the same
     * status-code error pages (or JSON) as uncaught exceptions do.
     */
    public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
    {
        // Keep CI3's text output on the CLI, and its detailed database
        // error page (failed query, file, line) while debugging.
        if (is_cli() || ($template === 'error_db' && $this->is_debug())) {
            return parent::show_error($heading, $message, $template, $status_code);
        }

        $status = $this->normalize_status($status_code);
        $message = is_array($message) ? implode("\n", $message) : (string) $message;

        $this->clean_output_buffers();
        set_status_header($status, $this->status_text($status));

        if ($this->wants_json()) {
            return $this->render_json($status, $message);
        }

        return $this->render_error_page($status, $message, $heading);
    }

    /**
     * Renders an error page for $status: views/errors/html/{status}.php,
     * then {first digit}xx.php, then minimal.php.
     */
    public function render_error_page($status, $message = '', $heading = '')
    {
        $path = $this->templates_path().'html'.DIRECTORY_SEPARATOR;

        $view = $path.'minimal.php';
        foreach ([$status, substr((string) $status, 0, 1).'xx'] as $name) {
            if (is_file($path.$name.'.php')) {
                $view = $path.$name.'.php';
                break;
            }
        }

        return $this->render_view($view, [
            'status_code' => $status,
            'status_text' => $this->status_text($status),
            'message' => $message,
            'heading' => $heading,
        ]);
    }

    public function render_json($status, $message = '', ?\Throwable $exception = null)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        $body = [
            'status' => $status,
            'error' => $this->status_text($status),
            'message' => $message !== '' ? $message : $this->status_text($status),
        ];

        if ($exception !== null && $this->is_debug()) {
            $body['message'] = $exception->getMessage();
            $body['exception'] = get_class($exception);
            $body['file'] = $exception->getFile();
            $body['line'] = $exception->getLine();
            $body['trace'] = explode("\n", $exception->getTraceAsString());
        }

        return (string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public function render_debug_page(\Throwable $exception, $status = 500)
    {
        $exceptions = [];
        for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
            $exceptions[] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'frames' => $this->build_frames($e),
            ];
        }

        return $this->render_view($this->templates_path().'html'.DIRECTORY_SEPARATOR.'debug.php', [
            'status_code' => $status,
            'status_text' => $this->status_text($status),
            'exceptions' => $exceptions,
            'trace_text' => (string) $exception,
            'request' => $this->request_info(),
            'app' => $this->app_info(),
            'queries' => $this->database_queries(),
            'handler' => $this,
        ]);
    }

    protected function render_cli(\Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $message = $exception->getMessage() !== '' ? $exception->getMessage() : $this->status_text($exception->getStatusCode());
            fwrite(STDERR, 'HTTP '.$exception->getStatusCode().': '.$message.PHP_EOL);

            return;
        }

        if ($this->is_debug()) {
            $this->show_exception($exception);
        } else {
            fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
        }
    }

    // --------------------------------------------------------------------
    // Debug page data
    // --------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function build_frames(\Throwable $exception)
    {
        $frames = [[
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => null,
        ]];

        foreach ($exception->getTrace() as $trace) {
            // Skip the error handler's own frames (e.g. for fatal errors,
            // which are turned into an ErrorException at shutdown).
            if (in_array($trace['function'], ['_error_handler', '_shutdown_handler', '_exception_handler'], true)
                || (isset($trace['class']) && $trace['class'] === self::class)) {
                continue;
            }

            // The function in a trace entry is the one *called* from that
            // file/line, so it describes the previous frame.
            $frames[count($frames) - 1]['function'] = (isset($trace['class']) ? $trace['class'].($trace['type'] ?? '::') : '').$trace['function'].'()';

            if (isset($trace['file'])) {
                $frames[] = ['file' => $trace['file'], 'line' => $trace['line'] ?? 0, 'function' => null];
            }
        }

        foreach ($frames as $i => $frame) {
            $frames[$i]['relative'] = $this->relative_path($frame['file']);
            $frames[$i]['vendor'] = $this->is_vendor_file($frame['file']);
            $frames[$i]['snippet'] = $this->code_snippet($frame['file'], (int) $frame['line']);
            $frames[$i]['editor_url'] = $this->editor_url($frame['file'], (int) $frame['line']);
        }

        return $frames;
    }

    /**
     * @return array<int, string> line number => source line
     */
    protected function code_snippet($file, $line, $padding = 8)
    {
        if ($line < 1 || !is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $start = max(1, $line - $padding);
        $end = min(count($lines), $line + $padding);

        $snippet = [];
        for ($i = $start; $i <= $end; $i++) {
            $snippet[$i] = $lines[$i - 1];
        }

        return $snippet;
    }

    /**
     * @return array<string, mixed>
     */
    protected function request_info()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))))] = $value;
            }
        }

        $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => $scheme.'://'.$host.($_SERVER['REQUEST_URI'] ?? '/'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'headers' => $this->mask($headers),
            'get' => $this->mask($_GET),
            'post' => $this->mask($_POST),
            'files' => array_map(static function ($file) {
                return is_array($file) ? ($file['name'] ?? '') : $file;
            }, $_FILES),
            'cookies' => $this->mask($_COOKIE),
            'session' => (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) ? $this->mask($_SESSION) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function app_info()
    {
        $info = [
            'PHP version' => PHP_VERSION,
            'CodeIgniter version' => defined('CI_VERSION') ? CI_VERSION : '',
            'Environment' => ENVIRONMENT,
            'Memory peak' => round(memory_get_peak_usage(true) / 1048576, 2).' MB',
        ];

        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $info['Time until error'] = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1).' ms';
        }

        $CI = $this->ci_instance();
        if ($CI !== null && isset($CI->router) && is_object($CI->router)) {
            $router = $CI->router;
            if (method_exists($router, 'fetch_module') && $router->fetch_module()) {
                $info['Module'] = $router->fetch_module();
            }
            $info['Controller'] = $router->directory.$router->class;
            $info['Method'] = $router->method;
        }

        return $info;
    }

    /**
     * @return array<int, string>
     */
    protected function database_queries()
    {
        $CI = $this->ci_instance();
        if ($CI === null || !isset($CI->db) || !is_object($CI->db) || !isset($CI->db->queries) || !is_array($CI->db->queries)) {
            return [];
        }

        return array_slice($CI->db->queries, -50);
    }

    protected function ci_instance()
    {
        if (!function_exists('get_instance') || !class_exists('CI_Controller', false)) {
            return null;
        }

        return get_instance();
    }

    /**
     * Masks values whose key looks sensitive (see 'hidden_keys').
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    protected function mask(array $data)
    {
        $hidden = (array) $this->setting('hidden_keys', []);

        foreach ($data as $key => $value) {
            foreach ($hidden as $needle) {
                if ($needle !== '' && stripos((string) $key, $needle) !== false) {
                    $data[$key] = '********';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }

    public function editor_url($file, $line)
    {
        $editor = (string) $this->setting('editor', '');
        if (!isset(self::$editors[$editor]) || !is_file($file)) {
            return null;
        }

        $url = self::$editors[$editor];
        $path = strpos($url, '?') !== false ? rawurlencode($file) : $file;

        return str_replace(['%file', '%line'], [$path, (string) (int) $line], $url);
    }

    public function relative_path($file)
    {
        $root = defined('FCPATH') ? rtrim(str_replace('\\', '/', FCPATH), '/').'/' : '';
        $file = str_replace('\\', '/', (string) $file);

        return ($root !== '' && strpos($file, $root) === 0) ? substr($file, strlen($root)) : $file;
    }

    protected function is_vendor_file($file)
    {
        $relative = $this->relative_path($file);

        return strpos($relative, 'system/') === 0
            || strpos($relative, 'vendor/') === 0
            || strpos($relative, 'application/third_party/') === 0;
    }

    // --------------------------------------------------------------------
    // Helpers
    // --------------------------------------------------------------------

    public function is_debug()
    {
        $debug = $this->setting('debug');

        if ($debug === null) {
            // DISPLAY_ERRORS holds index.php's display_errors setting (see error_handlers.php).
            return defined('DISPLAY_ERRORS')
                ? DISPLAY_ERRORS
                : (bool) str_ireplace(['off', 'none', 'no', 'false', 'null'], '', (string) ini_get('display_errors'));
        }

        return (bool) $debug;
    }

    public function wants_json()
    {
        if (is_cli()) {
            return false;
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');

        return strpos($accept, '/json') !== false || strpos($accept, '+json') !== false;
    }

    public function status_text($status)
    {
        return self::$status_texts[$status] ?? ($status >= 500 ? 'Server Error' : 'Error');
    }

    protected function normalize_status($status)
    {
        $status = (int) $status;

        return ($status >= 400 && $status <= 599) ? $status : 500;
    }

    /**
     * Discards any partially rendered output (e.g. half a view), so the
     * error page isn't appended to a broken page.
     */
    protected function clean_output_buffers()
    {
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
    }

    protected function templates_path()
    {
        $path = config_item('error_views_path');

        return empty($path) ? VIEWPATH.'errors'.DIRECTORY_SEPARATOR : rtrim($path, '/\\').DIRECTORY_SEPARATOR;
    }

    /**
     * @param array<string, mixed> $vars
     */
    protected function render_view($__view, array $vars)
    {
        extract($vars);

        ob_start();
        include $__view;

        return (string) ob_get_clean();
    }

    /**
     * Reads application/config/errors.php (plus an ENVIRONMENT-specific
     * override) directly, since errors can happen before CI's Config
     * class is loaded.
     */
    protected function setting($key, $default = null)
    {
        if ($this->settings === null) {
            $this->settings = [];

            foreach ([APPPATH.'config/errors.php', APPPATH.'config/'.ENVIRONMENT.'/errors.php'] as $file) {
                if (is_file($file)) {
                    $config = [];
                    include $file;
                    $this->settings = array_merge($this->settings, $config);
                }
            }
        }

        return array_key_exists($key, $this->settings) ? $this->settings[$key] : $default;
    }
}
