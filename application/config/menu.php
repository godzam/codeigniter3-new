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
|   'super_admin' => optional; true = only super admins (the developers) see the item
|   'role'  => optional; only users with this role see the item (a super_admin always does)
|   'children' => optional list of the same structure (renders a submenu)
|
| Modules made with the CRUD generator are appended automatically, under a
| "Modules" heading.
|
|   array('section' => 'Label') is a small heading above the items that follow it
|                   (hidden when none of them is visible to the user)
|
| An item is highlighted when the current URL equals or starts with its
| 'url'. A project adds its own pages here.
*/

$config['menu'] = array(
	array('label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => 'dashboard'),
	array('section' => 'Access'),
	array('label' => 'Users', 'icon' => 'bi-people', 'url' => 'admin/users', 'permission' => 'users.view'),
	array('label' => 'Roles', 'icon' => 'bi-shield-lock', 'url' => 'admin/roles', 'permission' => 'roles.view'),
	array('section' => 'System'),
	array('label' => 'Settings', 'icon' => 'bi-gear', 'url' => 'admin/settings', 'permission' => 'settings.manage'),
	array('label' => 'Generator', 'icon' => 'bi-magic', 'url' => 'admin/generator', 'super_admin' => true),
);
