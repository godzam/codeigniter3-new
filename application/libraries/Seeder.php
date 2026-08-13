<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Minimal seeder runner — CI3 has no built-in seeding, unlike migrations.
 * Seeder classes live in application/seeds/, extend CI_Seeder, and
 * implement run(). Invoke via the console:
 *
 *   php index.php console seed             (runs every *_seeder.php)
 *   php index.php console seed users       (runs application/seeds/users_seeder.php)
 */
class Seeder
{
    protected $CI;
    protected $path;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->path = APPPATH.'seeds/';
    }

    /**
     * Run every *_seeder.php file in application/seeds/, in filename order.
     *
     * @return int number of seeders run
     */
    public function run_all()
    {
        $files = glob($this->path.'*_seeder.php');
        sort($files);

        foreach ($files as $file) {
            $this->run_file($file);
        }

        return count($files);
    }

    /**
     * Run one seeder by bare name ('users') or full name ('users_seeder') —
     * both resolve to application/seeds/users_seeder.php.
     */
    public function run($name)
    {
        $name = preg_replace('/_seeder$/i', '', strtolower($name));
        $file = $this->path.$name.'_seeder.php';

        if (!is_file($file)) {
            show_error("Seeder not found: {$file}");
        }

        $this->run_file($file);
    }

    protected function run_file($file)
    {
        $class = $this->class_name_from_file($file);

        require_once $file;

        if (!class_exists($class, false)) {
            show_error("{$file} does not declare class {$class}");
        }

        $seeder = new $class();
        $seeder->CI = $this->CI;
        $seeder->run();
    }

    protected function class_name_from_file($file)
    {
        $base = basename($file, '.php');

        return str_replace(' ', '_', ucwords(str_replace('_', ' ', $base)));
    }
}

/**
 * Base class for seeders in application/seeds/. $CI is injected by
 * Seeder::run_file() before run() is called.
 */
abstract class CI_Seeder
{
    /** @var CI_Controller */
    public $CI;

    abstract public function run();
}
