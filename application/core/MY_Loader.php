<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'third_party/MX/Loader.php';

/**
 * Extends MX_Loader (HMVC) rather than CI_Loader directly, so module-aware
 * loading (views/models/libraries/controllers under application/modules)
 * keeps working. The DevelBar memory-tracking wrapper below only resolves
 * the view path being loaded and records per-view memory usage around the
 * real parent::_ci_load() call, then delegates — it does not reimplement
 * view rendering itself.
 */
class MY_Loader extends MX_Loader
{
	protected $_ci_views = array();
	protected $_ci_views_memory = array();

	public function get_helpers()
	{
		return $this->_ci_helpers;
	}

	public function get_models()
	{
		return $this->_ci_models;
	}

	public function get_views()
	{
		return $this->_ci_views;
	}

	public function get_views_memory()
	{
		return $this->_ci_views_memory;
	}

	public function _ci_load($_ci_data)
	{
		$_ci_path = isset($_ci_data['_ci_path']) ? $_ci_data['_ci_path'] : '';

		if (is_string($_ci_path) && $_ci_path !== '')
		{
			$_ci_resolved_path = $_ci_path;
		}
		else
		{
			$_ci_view = isset($_ci_data['_ci_view']) ? $_ci_data['_ci_view'] : '';
			$_ci_ext = pathinfo($_ci_view, PATHINFO_EXTENSION);
			$_ci_file = ($_ci_ext === '') ? $_ci_view.'.php' : $_ci_view;

			$_ci_resolved_path = $_ci_file;
			foreach ($this->_ci_view_paths as $_ci_view_file => $cascade)
			{
				if (file_exists($_ci_view_file.$_ci_file))
				{
					$_ci_resolved_path = $_ci_view_file.$_ci_file;
					break;
				}

				if ( ! $cascade)
				{
					break;
				}
			}
		}

		$this->_ci_views[$_ci_resolved_path] = isset($_ci_data['_ci_vars']) ? $_ci_data['_ci_vars'] : array();

		$_ci_memory_before = memory_get_usage();
		$_ci_result = parent::_ci_load($_ci_data);
		$_ci_memory_delta = memory_get_usage() - $_ci_memory_before;

		$this->_ci_views_memory[$_ci_resolved_path] = isset($this->_ci_views_memory[$_ci_resolved_path])
			? $this->_ci_views_memory[$_ci_resolved_path] + $_ci_memory_delta
			: $_ci_memory_delta;

		return $_ci_result;
	}
}
