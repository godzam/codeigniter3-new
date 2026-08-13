<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Deliberately doesn't touch $this->db — so this module works right after
 * clone, before anyone has set up a database. Swap get_sample_items() for
 * a real query once you have tables to query.
 */
class Example_model extends CI_Model
{
    public function get_sample_items()
    {
        return [
            'HMVC modules live under application/modules/<name>/',
            'Each module has its own controllers/, models/, views/',
            'This page is application/modules/example/views/index.php',
        ];
    }
}
