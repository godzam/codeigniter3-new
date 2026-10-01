<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes with
| underscores in the controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Shorter URLs for the auth module (application/modules/auth) — plain CI3
// routing still runs before HMVC's module dispatch, so this works exactly
// like it would without HMVC installed at all.
$route['login'] = 'auth/login';
$route['register'] = 'auth/register';
$route['logout'] = 'auth/logout';

// Admin settings (application/modules/settings). The controller is called
// Admin_settings because a class named Settings would clash with the
// Settings library.
$route['admin/settings'] = 'settings/admin_settings/index';
$route['admin/settings/reset'] = 'settings/admin_settings/reset';

// Access control (application/modules/users and application/modules/roles).
$route['admin/users'] = 'users/users/index';
$route['admin/users/data'] = 'users/users/data';
$route['admin/users/role/(:num)'] = 'users/users/role/$1';
$route['admin/roles'] = 'roles/roles/index';
$route['admin/roles/data'] = 'roles/roles/data';
$route['admin/roles/create'] = 'roles/roles/create';
$route['admin/roles/edit/(:any)'] = 'roles/roles/edit/$1';
$route['admin/roles/delete/(:any)'] = 'roles/roles/delete/$1';

// ---- CRUD generator (super_admin) and the modules it makes ----------------
$route['admin/generator'] = 'generator/module_generator/index';
$route['admin/generator/data'] = 'generator/module_generator/data';
$route['admin/generator/create'] = 'generator/module_generator/create';
$route['admin/generator/edit/(:any)'] = 'generator/module_generator/edit/$1';
$route['admin/generator/delete/(:any)'] = 'generator/module_generator/delete/$1';
$route['admin/c/([a-z][a-z0-9_]*)'] = 'crud/crud/index/$1';
$route['admin/c/([a-z][a-z0-9_]*)/data'] = 'crud/crud/data/$1';
$route['admin/c/([a-z][a-z0-9_]*)/form'] = 'crud/crud/form/$1';
$route['admin/c/([a-z][a-z0-9_]*)/form/(\d+)'] = 'crud/crud/form/$1/$2';
$route['admin/c/([a-z][a-z0-9_]*)/save'] = 'crud/crud/save/$1';
$route['admin/c/([a-z][a-z0-9_]*)/delete/(\d+)'] = 'crud/crud/delete/$1/$2';
