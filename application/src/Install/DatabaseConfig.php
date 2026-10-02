<?php

namespace App\Install;

/**
 * The text of application/config/database.php written by the installer.
 *
 * Every value goes through var_export(), so whatever was typed in the form
 * can never become code.
 */
final class DatabaseConfig
{
    /**
     * @param array{host: string, user: string, pass: string, name: string} $db
     *
     * @return string PHP source
     */
    public static function render(array $db)
    {
        $s = static function ($value) {
            return var_export((string) $value, true);
        };

        return <<<PHP
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Written by the installer (/install). Edit it freely; see
| database.php.example for what every key means. The installer only ever
| rewrites this file when the connection stops working, and then keeps a
| copy as database.php.bak.
*/
\$active_group = 'default';
\$db['default'] = array(
	'dsn' => '',
	'hostname' => {$s($db['host'])},
	'username' => {$s($db['user'])},
	'password' => {$s($db['pass'])},
	'database' => {$s($db['name'])},
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8mb4',
	'dbcollat' => 'utf8mb4_unicode_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE
);

PHP;
    }

    /**
     * The connection render() writes, as the array CodeIgniter's DB() takes.
     *
     * @param array{host: string, user: string, pass: string, name: string} $db
     *
     * @return array<string, mixed>
     */
    public static function params(array $db)
    {
        return [
            'dsn' => '', 'hostname' => (string) $db['host'], 'username' => (string) $db['user'], 'password' => (string) $db['pass'],
            'database' => (string) $db['name'], 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
            'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci',
            'swap_pre' => '', 'encrypt' => false, 'compress' => false, 'stricton' => false, 'failover' => [], 'save_queries' => true,
        ];
    }

    /**
     * `CREATE DATABASE` statement for a MySQL / MariaDB database name.
     */
    public static function createStatement($name)
    {
        return 'CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', (string) $name).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}
