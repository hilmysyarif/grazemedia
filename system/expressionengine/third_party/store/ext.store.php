<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

require_once(PATH_THIRD.'store/config.php');

class Store_ext
{
	public $name = STORE_NAME;
	public $description = STORE_DESCRIPTION;
	public $version = STORE_VERSION;
	public $docs_url = STORE_DOCS;
	public $settings_exist = 'y';
	public $settings = array();
	public $required_by = array('module');

	protected $_store_custom_fields;

	public function __construct()
	{
		$this->EE =& get_instance();
	}

	public function activate_extension()
	{
		// install handled by module
		return TRUE;
	}

	public function update_extension($current = '')
	{
		return TRUE;
	}

	public function disable_extension()
	{
		return TRUE;
	}

	public function settings_form()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=store'.AMP.'method=settings');
	}

	/**
	 * Add product information to channel entries tags
	 */
	public function channel_entries_query_result($channel, $query_result)
	{
		if ($this->EE->extensions->last_call !== FALSE)
		{
			$query_result = $this->EE->extensions->last_call;
		}

		$this->EE->load->helper('store');
		$this->EE->load->library('store_cart');

		$custom_fields = $this->_get_store_custom_fields();
		$store_entry_ids = array();

		foreach ($query_result as $row => $entry)
		{
			foreach ($custom_fields as $field_name)
			{
				if ( ! empty($entry[$field_name]))
				{
					// this field needs to be replaced with store product data
					$store_entry_ids[$entry['entry_id']] = $row;
				}
			}
		}

		// do we need to load additional product data?
		if ( ! empty($store_entry_ids))
		{
			$this->EE->load->model('store_products_model');
			$products = $this->EE->store_products_model->find_by_entry_ids(array_keys($store_entry_ids));

			foreach ($products as $product)
			{
				$entry_id = (int)$product['entry_id'];
				$product = $this->EE->store_cart->process_product_tax($product);
				$row = $store_entry_ids[$entry_id];

				foreach ($custom_fields as $field_name)
				{
					if ( ! empty($query_result[$row][$field_name]))
					{
						$query_result[$row][$field_name] = $product;
					}
				}
			}
		}

		return $query_result;
	}

	private function _get_store_custom_fields()
	{
		if (is_null($this->_store_custom_fields))
		{
			$this->EE->load->library('api');
			$this->EE->api->instantiate('channel_fields');

			$this->_store_custom_fields = array();

			foreach ($this->EE->api_channel_fields->custom_fields as $id => $field_type)
			{
				if ($field_type == 'store') { $this->_store_custom_fields[] = 'field_id_'.$id; }
			}
		}

		return $this->_store_custom_fields;
	}

	public function cp_menu_array($menu)
	{
		if ($this->EE->extensions->last_call !== FALSE)
		{
			$menu = $this->EE->extensions->last_call;
		}

		$this->EE->lang->loadfile('store');
		$this->EE->load->model('store_common_model');
		$this->EE->load->library('store_config');

		if ($this->EE->store_config->site_enabled() == FALSE)
		{
			return $menu;
		}

		if ($this->EE->session->userdata['group_id'] != 1)
		{
			// check whether the current user can access the store module
			if ( ! $this->EE->cp->allowed_group('can_access_addons', 'can_access_modules')) return $menu;

			$this->EE->db->from('modules m');
			$this->EE->db->join('module_member_groups mg', 'mg.module_id = m.module_id');
			$this->EE->db->where('mg.group_id', $this->EE->session->userdata('group_id'));
			$this->EE->db->where('m.module_name', 'Store');
			if ($this->EE->db->count_all_results() == 0) return $menu;
		}

		// if we got to this point, add the store menu
		$menu['store'] = $this->EE->store_config->generate_menu();
		return $menu;
	}

	/**
	 * This hook is used to work around the fact that some gateways (namely DPS) will not allow
	 * return URLs which include a query string, which prevents us from using regular ACT URLs.
	 */
	public function sessions_end($session)
	{
		if ($this->EE->uri->segment(1) === 'payment_return')
		{
			// assign the session object prematurely, since EE won't need it anyway
			// (this hook runs inside the Session object constructor, which is a bit weird)
			$this->EE->session =& $session;

			$_GET['H'] = (string)$this->EE->uri->segment(2);

			require_once(PATH_THIRD.'store/mod.store.php');
			$store = new Store();
			$store->act_payment_return();
		}
	}

	/**
	 * Member logout hook
	 *
	 * Clear the user's cart on logout
	 */
	public function member_member_logout()
	{
		$this->EE->load->library('store_config');
		if ($this->EE->store_config->item('empty_cart_on_logout') == 'y')
		{
			$this->EE->load->library('store_cart');
			$this->EE->store_cart->empty_cart();
		}
	}
}

/* End of file ext.store.php */