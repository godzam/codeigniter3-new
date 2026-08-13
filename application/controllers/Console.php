<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI-only maintenance tasks. CodeIgniter 3 has no built-in artisan-like
 * CLI, so this is the idiomatic CI3 way to get one: a normal controller,
 * invoked from the command line instead of over HTTP. is_cli() below
 * refuses to run it any other way, so it's never reachable through a
 * browser regardless of routing.
 *
 * Usage:
 *   php index.php console migrate
 *   php index.php console seed
 *   php index.php console seed users
 *
 * @property CI_Migration $migration
 * @property Seeder $seeder
 */
class Console extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!is_cli()) {
            show_error('The console can only be run from the command line.', 403);
        }
    }

    public function migrate()
    {
        $this->load->library('migration');

        if (!$this->migration->latest()) {
            echo 'Migration failed: '.$this->migration->error_string().PHP_EOL;

            return;
        }

        echo 'Migrated to the latest version.'.PHP_EOL;
    }

    public function seed($seeder = null)
    {
        $this->load->library('seeder');

        if ($seeder !== null) {
            $this->seeder->run($seeder);
            echo "Seeded: {$seeder}".PHP_EOL;

            return;
        }

        $count = $this->seeder->run_all();
        echo "Ran {$count} seeder(s).".PHP_EOL;
    }
}
