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

	'auth' => array(
		'label' => 'Login page',
		'icon' => 'bi-box-arrow-in-right',
		'fields' => array(
			'auth_headline' => array(
				'label' => 'Headline',
				'type' => 'text',
				'default' => 'Everything you need, in one place.',
				'help' => 'Big text on the colored side of the login and register pages.',
			),
			'auth_subtext' => array(
				'label' => 'Description',
				'type' => 'text',
				'default' => 'Sign in to manage your users, roles and data.',
			),
			'auth_points' => array(
				'label' => 'Highlights',
				'type' => 'textarea',
				'default' => "Role-based access for every module\nLight, dark and mobile-ready\nBuilt on CodeIgniter 3",
				'help' => 'One per line (up to 5). Leave empty to hide the list.',
			),
			'auth_image' => array(
				'label' => 'Background image',
				'type' => 'text',
				'default' => '',
				'help' => 'Optional path or URL (e.g. assets/img/login.jpg). Shown under a dark overlay instead of the color gradient.',
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

	'security' => array(
		'label' => 'Security',
		'icon' => 'bi-shield-check',
		'fields' => array(
			'turnstile_enabled' => array(
				'label' => 'Protect forms with Cloudflare Turnstile',
				'type' => 'switch',
				'default' => FALSE,
				'requires' => array('turnstile_site_key', 'turnstile_secret_key'),
				'help' => 'A privacy-friendly CAPTCHA. Create a site at dash.cloudflare.com → Turnstile and paste its keys below.',
			),
			'turnstile_site_key' => array(
				'label' => 'Turnstile site key',
				'type' => 'text',
				'default' => getenv('TURNSTILE_SITE_KEY') ?: '',
				'help' => 'Public key shown in the page. For local testing Cloudflare offers dummy keys: 1x00000000000000000000AA always passes, 2x00000000000000000000AB always blocks.',
			),
			'turnstile_secret_key' => array(
				'label' => 'Turnstile secret key',
				'type' => 'password',
				'default' => getenv('TURNSTILE_SECRET_KEY') ?: '',
				'help' => 'Private key, used only on the server. Dummy secret for testing: 1x0000000000000000000000000000000AA (passes) or 2x0000000000000000000000000000000AA (fails). Leave blank to keep the current one. It can also come from the TURNSTILE_SECRET_KEY environment variable.',
			),
			'turnstile_on_login' => array(
				'label' => 'Require it on the login form',
				'type' => 'switch',
				'default' => TRUE,
			),
			'turnstile_on_register' => array(
				'label' => 'Require it on the registration form',
				'type' => 'switch',
				'default' => TRUE,
			),
			'turnstile_theme' => array(
				'label' => 'Widget theme',
				'type' => 'select',
				'default' => 'auto',
				'options' => array('auto' => 'Match the site (light/dark)', 'light' => 'Light', 'dark' => 'Dark'),
			),
			'turnstile_size' => array(
				'label' => 'Widget size',
				'type' => 'select',
				'default' => 'normal',
				'options' => array('normal' => 'Normal', 'flexible' => 'Flexible (fills the form width)', 'compact' => 'Compact'),
			),
		),
	),

);
