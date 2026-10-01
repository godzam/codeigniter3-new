<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Application settings schema
| -------------------------------------------------------------------------
| Declares the settings editable from the admin Settings page (stored in
| the `settings` table). Each field has a type, default, and label; the
| form, validation, and defaults are all generated from this file, so
| adding a setting here is all it takes for it to appear in the UI:
|
|     $config['app_settings']['general']['fields']['support_email'] = array(
|         'label' => 'Support email', 'type' => 'text', 'default' => '',
|     );
|
| Read it anywhere with $this->settings->get('support_email'). Modules can
| also add their own with $this->settings->register('blog', [...]).
|
| Field types: text, textarea, color, select (needs 'options'), switch,
| number (optional 'min'/'max'), password (blank = keep current).
|
| Defaults here apply until a value is saved, and when no database is
| configured at all — the site still renders.
*/

$config['app_settings'] = array(

	'general' => array(
		'label' => 'General',
		'icon' => 'bi-sliders',
		'fields' => array(
			'app_name' => array(
				'label' => 'Application name',
				'type' => 'text',
				'default' => 'My App',
				'required' => TRUE,
				'help' => 'Shown in the navbar, page titles, and the login page.',
			),
			'app_tagline' => array(
				'label' => 'Tagline',
				'type' => 'text',
				'default' => 'Built on CodeIgniter 3',
			),
			'app_logo' => array(
				'label' => 'Logo URL',
				'type' => 'text',
				'default' => '',
				'help' => 'Path or URL to an image (e.g. assets/img/logo.svg). Leave empty to show only the name.',
			),
			'footer_text' => array(
				'label' => 'Footer text',
				'type' => 'text',
				'default' => '',
				'help' => 'Leave empty to show "© year application name".',
			),
		),
	),

	'appearance' => array(
		'label' => 'Appearance',
		'icon' => 'bi-palette',
		'fields' => array(
			'theme_primary' => array(
				'label' => 'Primary color',
				'type' => 'color',
				'default' => '#0d6efd',
				'help' => 'Buttons, links, active menu items. Hover and dark-mode shades are derived automatically.',
			),
			'theme_secondary' => array(
				'label' => 'Secondary color',
				'type' => 'color',
				'default' => '#6c757d',
			),
			'theme_mode' => array(
				'label' => 'Default color mode',
				'type' => 'select',
				'default' => 'auto',
				'options' => array('auto' => 'Auto (follow the visitor\'s system)', 'light' => 'Light', 'dark' => 'Dark'),
			),
			'theme_allow_toggle' => array(
				'label' => 'Let visitors switch light/dark',
				'type' => 'switch',
				'default' => TRUE,
				'help' => 'Shows a toggle in the navbar; the choice is remembered in the visitor\'s browser.',
			),
			'theme_radius' => array(
				'label' => 'Corner roundness',
				'type' => 'select',
				'default' => 'md',
				'options' => array('none' => 'Square', 'sm' => 'Slightly rounded', 'md' => 'Rounded (default)', 'lg' => 'Very rounded'),
			),
			'theme_font' => array(
				'label' => 'Font',
				'type' => 'select',
				'default' => 'system',
				'options' => array('system' => 'System UI', 'serif' => 'Serif', 'mono' => 'Monospace'),
			),
		),
	),

	'layout' => array(
		'label' => 'Layout',
		'icon' => 'bi-layout-sidebar',
		'fields' => array(
			'layout_fluid' => array(
				'label' => 'Full-width content',
				'type' => 'switch',
				'default' => FALSE,
				'help' => 'Use the whole screen width instead of a centered container.',
			),
			'layout_sidebar' => array(
				'label' => 'Admin sidebar',
				'type' => 'select',
				'default' => 'expanded',
				'options' => array('expanded' => 'Expanded (icons + labels)', 'compact' => 'Compact (icons only)'),
			),
			'layout_navbar' => array(
				'label' => 'Navbar style',
				'type' => 'select',
				'default' => 'default',
				'options' => array('default' => 'Default', 'primary' => 'Filled with primary color'),
			),
		),
	),

);
