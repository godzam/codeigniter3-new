<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Page builder permissions (application/modules/pages)
| -------------------------------------------------------------------------
| Admin -> Pages builds the content pages served at /page/{slug}.
*/

$config['permissions']['pages'] = [
    'label' => 'Page builder',
    'icon' => 'bi-file-earmark-richtext',
    'permissions' => [
        'view' => 'See the list of pages',
        'create' => 'Create pages',
        'edit' => 'Edit pages and their layout',
        'delete' => 'Delete pages',
        'html' => 'Write raw HTML in pages (can run scripts for every visitor: give it only to people you trust as much as a developer)',
    ],
];
