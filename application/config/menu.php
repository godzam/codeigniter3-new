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
|   'permission' => optional; only users whose role holds this permission
|                   ("module.action", see config/permissions.php) see the item
|   'role'  => optional; only users with this role see the item (a super_admin always does)
|   'children' => optional list of the same structure (renders a submenu)
|
| An item is highlighted when the current URL equals or starts with its
| 'url'. A project adds its own pages here.
*/

$config['menu'] = array(
	array('label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => 'dashboard'),
	array('label' => 'Users', 'icon' => 'bi-people', 'url' => 'admin/users', 'permission' => 'users.view'),
	array('label' => 'Roles', 'icon' => 'bi-shield-lock', 'url' => 'admin/roles', 'permission' => 'roles.view'),
	array('label' => 'Settings', 'icon' => 'bi-gear', 'url' => 'admin/settings', 'permission' => 'settings.manage'),
);
