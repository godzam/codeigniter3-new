<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Landing page after login, rendered in the admin layout. Replace the
 * sample cards in views/index.php with whatever your project needs.
 *
 * @property Template $template
 */
class Dashboard extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
        require_login();
    }

    public function index()
    {
        $this->template
            ->set_title('Dashboard')
            ->set_breadcrumbs(['Home' => '', 'Dashboard' => null])
            ->render('index', ['user' => current_user()], 'admin');
    }
}
