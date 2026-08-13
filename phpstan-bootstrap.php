<?php
/**
 * Defines CodeIgniter's runtime constants for PHPStan's benefit only.
 * Never included by the actual application — index.php defines these
 * for real when the app boots.
 */

defined('BASEPATH') OR define('BASEPATH', __DIR__.'/system/');
defined('APPPATH') OR define('APPPATH', __DIR__.'/application/');
defined('VIEWPATH') OR define('VIEWPATH', __DIR__.'/application/views/');
defined('FCPATH') OR define('FCPATH', __DIR__.'/');
defined('SELF') OR define('SELF', 'index.php');
defined('EXT') OR define('EXT', '.php');
defined('ENVIRONMENT') OR define('ENVIRONMENT', 'development');
