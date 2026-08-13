<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| PRODUCTION OVERRIDES
| -------------------------------------------------------------------
| CodeIgniter merges this file over application/config/config.php
| whenever ENVIRONMENT === 'production' (see system/core/Config.php).
| Only list the keys you want to *change* — everything else keeps
| its value from the base config.php.
|
| The same pattern works for any environment name: create
| application/config/{environment}/config.php (or any other config
| file) and it'll be merged in the same way. This one ships as the
| practical example since production is where these actually matter.
*/

// Don't log everything in production — errors only.
$config['log_threshold'] = 1;

// Reduce bandwidth on production traffic.
$config['compress_output'] = TRUE;
