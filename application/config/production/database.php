<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| PRODUCTION DATABASE OVERRIDES
| -------------------------------------------------------------------
| Unlike config.php, CodeIgniter does NOT merge database.php files: it
| loads this one *instead of* application/config/database.php whenever
| ENVIRONMENT === 'production'. So the base file is pulled in first, and
| only what differs is overridden below. Real credentials still come from
| .env / the server environment (see database.php.example), not from here.
*/

if (is_file(APPPATH.'config/database.php'))
{
	require APPPATH.'config/database.php';
}

$db['default']['db_debug'] = FALSE;
