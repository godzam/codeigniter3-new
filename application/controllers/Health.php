<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * A single unauthenticated JSON endpoint for load balancers / uptime
 * monitors — GET /health. Checks the database only if it's actually
 * configured for use (config.php doesn't autoload it by default), so
 * this doesn't fail on a install that's never touched a database.
 *
 * @property CI_DB_query_builder $db Loaded by check_database() below.
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
