<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| PRODUCTION DATABASE OVERRIDES
| -------------------------------------------------------------------
| Merged over application/config/database.php when
| ENVIRONMENT === 'production'. Only override what actually needs to
| differ — real credentials should still come from .env / the server
| environment (see database.php.example), not be hardcoded here.
*/

$db['default']['db_debug'] = FALSE;
