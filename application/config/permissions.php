<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Permissions
| -------------------------------------------------------------------------
| Every permission the application checks, grouped by module. A permission
| key is "module.action"; roles are granted a subset of them on the Roles
| page (Admin -> Roles), and code checks them with:
|
|     can('users.view')               // bool, e.g. in a view or the menu
|     require_permission('users.view') // at the top of a controller method
|
| The `super_admin` role holds every permission automatically.
|
| A module adds its own permissions without editing this file: put the same
| structure in application/modules/<module>/config/permissions.php.
|
|     $config['permissions']['blog'] = array(
|         'label' => 'Blog',
|         'icon' => 'bi-journal-text',
|         'permissions' => array(
|             'view'   => 'See posts in the admin',
|             'edit'   => 'Create and edit posts',
|             'delete' => 'Delete posts',
|         ),
|     );
|
| Only list a permission once some code actually checks it.
*/

$config['permissions'] = array(

	'users' => array(
		'label' => 'Users',
		'icon' => 'bi-people',
		'permissions' => array(
			'view' => 'See the list of users',
			'assign_role' => 'Change the role of a user',
		),
	),

	'roles' => array(
		'label' => 'Roles',
		'icon' => 'bi-shield-lock',
		'permissions' => array(
			'view' => 'See roles and their permissions',
			'create' => 'Create new roles',
			'edit' => 'Edit roles and their permissions',
			'delete' => 'Delete roles',
		),
	),

	'settings' => array(
		'label' => 'Settings',
		'icon' => 'bi-gear',
		'permissions' => array(
			'manage' => 'Change application settings (theme, security, ...)',
		),
	),

);

/*
| Permissions granted to the roles the migration creates. Only used when the
| roles are first created; after that, change them on the Roles page.
| '*' means every permission known at that moment.
*/
$config['default_role_permissions'] = array(
	'admin' => array('*'),
	'user' => array(),
);
