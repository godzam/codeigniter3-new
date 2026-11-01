<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Menu builder permissions (application/modules/menus)
| -------------------------------------------------------------------------
| Admin -> Menus edits the admin sidebar. The built-in items in
| application/config/menu.php are always shown; these permissions only
| decide who may change the menu.
*/

$config['permissions']['menus'] = [
    'label' => 'Menu builder',
    'icon' => 'bi-list',
    'permissions' => [
        'view' => 'See the menu builder',
        'create' => 'Add menu items',
        'edit' => 'Edit and reorder menu items',
        'delete' => 'Delete menu items',
    ],
];
