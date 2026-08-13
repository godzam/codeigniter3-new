<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reference HMVC module. Doesn't require a database — clone, configure,
 * and `/example` works immediately, showing view loading, a module-local
 * model, and calling another method on this module as a "widget" via
 * Modules::run().
 */
class Example extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('example_model');
	}

	public function index()
	{
		$data = array(
			'items' => $this->example_model->get_sample_items(),
			'widget' => Modules::run('example/widget'),
		);

		$this->load->view('index', $data);
	}

	public function widget()
	{
		return 'rendered by Modules::run(\'example/widget\')';
	}
}
