<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Page builder permissions (application/modules/pages)
| -------------------------------------------------------------------------
| Admin -> Pages builds the content pages served at /page/{slug}.
*/

$config['permissions']['pages'] = array(
	'label' => 'Page builder',
	'icon' => 'bi-file-earmark-richtext',
	'permissions' => array(
		'view' => 'See the list of pages',
		'create' => 'Create pages',
		'edit' => 'Edit pages and their layout',
		'delete' => 'Delete pages',
	),
);
