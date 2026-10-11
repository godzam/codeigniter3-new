<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * A single unauthenticated JSON endpoint for load balancers / uptime
 * monitors — GET /health. Checks the database only if it's actually
 * configured for use (config.php doesn't autoload it by default), so
 * this doesn't fail on a install that's never touched a database.
 *
 * Two endpoints for a load balancer:
 *
 *   GET /health        liveness  - the app answers (and the database connects, if one is configured)
 *   GET /health/ready  readiness - it can actually serve: a real query runs, the shared cache
 *                      answers, and the cache and log folders are writable. Answers 503 if any
 *                      fails, so the balancer takes this server out until it recovers.
 *
 * Both are unauthenticated and say only ok/failed per check, never why.
 *
 * @property CI_DB_query_builder $db Loaded by check_database() below.
 * @property Sharedcache $sharedcache
 */
class Health extends CI_Controller
{
    public function index()
    {
        $checks = [
            'app' => true,
            'database' => $this->check_database(),
        ];

        $healthy = !in_array(false, $checks, true);

        $this->output
            ->set_content_type('application/json')
            ->set_status_header($healthy ? 200 : 503)
            ->set_output(json_encode([
                'status' => $healthy ? 'ok' : 'error',
                'checks' => $checks,
                'time' => date('c'),
            ]));
    }

    public function ready()
    {
        $started = microtime(true);
        $checks = [
            'database' => $this->check_query(),
            'cache' => $this->check_cache(),
            'storage' => is_writable(APPPATH.'cache') && is_writable(APPPATH.'logs'),
        ];
        $healthy = !in_array(false, $checks, true);

        $this->output
            ->set_header('Cache-Control: no-store')
            ->set_content_type('application/json')
            ->set_status_header($healthy ? 200 : 503)
            ->set_output(json_encode([
                'status' => $healthy ? 'ok' : 'error',
                'checks' => $checks,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'time' => date('c'),
            ]));
    }

    protected function check_query()
    {
        try {
            $this->load->database();

            return $this->db->conn_id !== false && $this->db->simple_query('SELECT 1') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function check_cache()
    {
        try {
            $this->load->library('sharedcache');
            $key = 'health_'.bin2hex(random_bytes(4));
            $this->sharedcache->save($key, 'ok', 10);
            $ok = $this->sharedcache->get($key) === 'ok';
            $this->sharedcache->delete($key);

            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function check_database()
    {
        try {
            $this->load->database();

            return $this->db->conn_id !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
