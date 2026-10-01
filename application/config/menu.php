<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Admin sidebar menu
| -------------------------------------------------------------------------
| Items shown in the admin layout's sidebar (application/views/layouts/
| admin.php). Each item:
|
|   'label' => text
|   'icon'  => a Bootstrap Icons class (https://icons.getbootstrap.com)
|   'url'   => site URL, e.g. 'dashboard'
|   'role'  => optional; only users with this role see the item
|   'children' => optional list of the same structure (renders a submenu)
|
| An item is highlighted when the current URL equals or starts with its
| 'url'. A project adds its own pages here.
*/

$config['menu'] = array(
	array('label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => 'dashboard'),
	array('label' => 'Settings', 'icon' => 'bi-gear', 'url' => 'admin/settings', 'role' => 'admin'),
);
