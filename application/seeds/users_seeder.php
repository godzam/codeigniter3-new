<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Seeds one default admin so there's something to log in with locally.
 *
 * SECURITY: change or remove this before deploying anywhere real —
 * "password" is a placeholder, not a real credential.
 */
class Users_Seeder extends CI_Seeder
{
    public function run()
    {
        $this->CI->load->database();

        if ($this->CI->db->count_all_results('users') > 0) {
            echo 'users table already has data, skipping.'.PHP_EOL;

            return;
        }

        $this->CI->db->insert('users', array(
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        echo 'Seeded default admin: admin@example.com / password — change this before deploying anywhere real.'.PHP_EOL;
    }
}
