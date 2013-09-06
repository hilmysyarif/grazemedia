<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

define('STORE_SHIPPING_PATH', PATH_THIRD.'store/libraries/store_shipping/');

class Store_shipping
{
	const CM_PER_INCH = 2.54;
	const LB_PER_KG = 2.20462;

	protected $_driver;
	protected $EE;

	public function __construct()
	{
		$this->EE =& get_instance();
	}

	public function __call($function, $arguments)
	{
		if ( ! empty($this->_driver))
		{
			return call_user_func_array(array($this->_driver, $function), $arguments);
		}
	}

	/**
	 * Load the specified driver
	 */
	public function load($shipping_method_id, $load_if_disabled = FALSE)
	{
		$this->EE->load->model('store_shipping_model');

		$row = $this->EE->store_shipping_model->get_shipping_method($shipping_method_id);
		if (empty($row)) return FALSE;
		if ($load_if_disabled == FALSE AND $row['enabled'] == FALSE) return FALSE;

		$this->_driver = $this->_create_instance($row['class']);
		if ($this->_driver === FALSE) return FALSE;

		$this->_driver->shipping_method_id = (int)$row['shipping_method_id'];
		$this->_driver->title = $row['title'];

		$settings = unserialize(base64_decode($row['settings']));
		$this->initialize($settings);

		return TRUE;
	}

	/**
	 * Load and create an instance of a shipping driver.
	 */
	protected function _create_instance($driver_class)
	{
		$driver_class = ucfirst(strtolower($driver_class));

		if (class_exists($driver_class)) return new $driver_class;

		foreach (array(
			STORE_SHIPPING_PATH.strtolower($driver_class).'.php',
			STORE_SHIPPING_PATH.ucfirst(strtolower($driver_class)).'.php') as $file_path)
		{
			if (file_exists($file_path))
			{
				require $file_path;
				if (class_exists($driver_class)) return new $driver_class;
			}
		}

		return FALSE;
	}

	public function initialize($settings)
	{
		foreach ($this->_driver->default_settings() as $key => $default)
		{
			if (isset($settings[$key]))
			{
				$value = $settings[$key];
				if (is_bool($default)) $value = (bool)$value;

				// TODO: validate select options

				$this->_driver->settings[$key] = $value;
			}
			elseif ( ! isset($this->_driver->settings[$key]))
			{
				$this->_driver->settings[$key] = store_setting_default($default);
			}
		}
	}

	public function valid_drivers()
	{
		// we always want default to be first in the list
		$valid_drivers = array('Store_shipping_default');

		foreach (scandir(STORE_SHIPPING_PATH) as $file_name)
		{
			if (stripos($file_name, 'store_shipping_') === 0 AND is_file(STORE_SHIPPING_PATH.$file_name))
			{
				$class_name = ucfirst(str_replace('.php', '', strtolower($file_name)));

				if ($this->_create_instance($class_name) !== FALSE)
				{
					$valid_drivers[] = $class_name;
				}
			}
		}

		return array_unique($valid_drivers);
	}

	public function valid_drivers_select()
	{
		$drivers = $this->valid_drivers();
		$select = array();

		foreach ($drivers as $class)
		{
			$select[$class] = lang(strtolower($class));
		}

		return $select;
	}

	public function title()
	{
		return $this->_driver->title;
	}

	public function name()
	{
		return lang(strtolower(get_class($this->_driver)));
	}

	/**
	 * Does the currently loaded shipping driver make remote requests?
	 *
	 * @return bool
	 */
	public function is_remote()
	{
		return $this->_driver->remote;
	}

	/**
	 * Delete the currently loaded plugin.
	 */
	public function delete()
	{
		if (isset($this->_driver->shipping_method_id))
		{
			if (method_exists($this->_driver, 'delete'))
			{
				$this->_driver->delete();
			}

			$this->EE->store_shipping_model->delete_shipping_method($this->_driver->shipping_method_id);
		}
	}

	public function display_settings($data)
	{
		if (method_exists($this->_driver, 'display_settings'))
		{
			// driver has its own custom settings logic
			return $this->_driver->display_settings($data);
		}

		// check for submitted data
		if ( ! empty($_POST))
		{
			$settings = $this->EE->input->post('settings', TRUE);
			$this->initialize($settings);

			$this->EE->store_shipping_model->update_shipping_method($this->_driver->shipping_method_id, array(
				'settings' => base64_encode(serialize($this->_driver->settings))
			));

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect($data['back_url']);
		}

		$data['setting_defaults'] = $this->_driver->default_settings();
		$data['settings'] = $this->_driver->settings;

		return $this->EE->load->view('settings/general', $data, TRUE);
	}
}

abstract class Store_shipping_driver
{
	public $shipping_method_id;
	public $name;
	public $title;

	/**
	 * Does the shipping driver make external requests?
	 * If true, Store won't query the total on every checkout page load for better performance.
	 */
	public $remote = false;

	public $settings;
	protected $EE;

	public function __construct()
	{
		$this->EE =& get_instance();
	}

	/**
	 * All but the most basic drivers should override this function.
	 */
	public function default_settings()
	{
		return array();
	}

	/**
	 * Calculate the shipping cost for an order.
	 */
	public abstract function calculate_shipping($order);

	/**
	 * Helper function to use our distributed SSL root certificate
	 */
	protected function default_curl_options()
	{
		return array(
			'ssl_verifypeer' => TRUE,
			'ssl_verifyhost' => 2,
			'cainfo' => PATH_THIRD.'store/ci-merchant/config/cacert.pem',
		);
	}
}

/* End of file ./libraries/store_shipping/store_shipping.php */