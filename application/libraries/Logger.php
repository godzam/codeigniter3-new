<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

/**
 * Opt-in structured logging via Monolog, alongside (not replacing) CI3's
 * own log_message()/CI_Log. Reach for this when you want multiple
 * handlers (file + stderr, or a service like Sentry/Slack later) or
 * structured context arrays — log_message() is still fine for simple,
 * framework-style logging and needs no setup.
 *
 * Usage:
 *   $this->load->library('logger');
 *   $this->logger->info('User logged in', ['user_id' => $user->id]);
 *
 * Writes to application/logs/app.log (gitignored, same as CI3's own log
 * files) and, in CLI, also to stderr — useful under process managers/
 * containers that expect logs on stderr rather than in a file.
 */
class Logger
{
    /** @var MonologLogger */
    protected $monolog;

    public function __construct()
    {
        $this->monolog = new MonologLogger('app');
        $this->monolog->pushHandler(new StreamHandler(APPPATH.'logs/app.log', Level::Debug));

        if (is_cli()) {
            $this->monolog->pushHandler(new StreamHandler('php://stderr', Level::Notice));
        }
    }

    public function debug($message, array $context = [])
    {
        $this->monolog->debug($message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->monolog->info($message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->monolog->warning($message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->monolog->error($message, $context);
    }

    /**
     * Escape hatch for anything not wrapped above — adding handlers,
     * processors, etc.
     */
    public function monolog()
    {
        return $this->monolog;
    }
}
