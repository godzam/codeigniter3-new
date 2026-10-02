<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property Template $template
 * @property Installer $installer
 */
class Welcome extends CI_Controller
{
    /**
     * Index Page for this controller.
     *
     * Maps to the following URL
     * 		http://example.com/index.php/welcome
     *	- or -
     * 		http://example.com/index.php/welcome/index
     *	- or -
     * Since this controller is set as the default controller in
     * config/routes.php, it's displayed at http://example.com/
     *
     * So any other public methods not prefixed with an underscore will
     * map to /index.php/welcome/<method_name>
     * @see https://codeigniter.com/userguide3/general/urls.html
     */
    public function index()
    {
        // Until the app has a user (and only where the installer is allowed) the
        // page offers the one-click setup. A signed-in visitor is proof enough
        // that it is installed, so they skip the check.
        $setup = null;
        $this->load->library('installer');
        if (!is_logged_in() && $this->installer->enabled() && !$this->installer->installed()) {
            $setup = $this->installer->status();
        }

        $this->template->render('welcome_message', ['setup' => $setup], 'public');
    }
}
