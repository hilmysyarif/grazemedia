<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

defined('STORE_CACHE_PATH') OR define('STORE_CACHE_PATH', APPPATH.'cache/store/');

class Store_config
{
	const CONFIG_CLASS = 'Store';

	private $EE;
	private $_config_defaults;
	private $_config_items;
	private $_site_enabled = FALSE;

	function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->helper('store');

		// backwards compabitility for EE < 2.4
		// placed here since Store_config library is loaded early
		defined('URL_THIRD_THEMES') OR define('URL_THIRD_THEMES', $this->EE->config->item('theme_folder_url').'third_party/');

		$this->_config_defaults = array(
			'currency_symbol' => '$',
			'currency_suffix' => '',
			'currency_decimals' => 2,
			'currency_dec_point' => '.',
			'currency_thousands_sep' => ',',
			'currency_code' => 'USD',
			'weight_units' => array('type' => 'select', 'options' => array('kg' => 'weight_units_kg', 'lb' => 'weight_units_lb'), 'default' => 'kg'),
			'dimension_units' => array('type' => 'select', 'options' => array('cm' => 'dimension_units_cm', 'm' => 'dimension_units_m', 'ft' => 'dimension_units_ft', 'in' => 'dimension_units_in'), 'default' => 'm'),
			'from_email' => '',
			'from_name' => '',
			'export_pdf_orientation' => array('type' => 'select', 'options' => array('P' => 'pdf_orientation_portrait', 'L' => 'pdf_orientation_landscape'), 'default' => 'P'),
			'export_pdf_page_format' => array('type' => 'select', 'options' => array('A4' => 'pdf_page_format_a4', 'A3' => 'pdf_page_format_a3', 'LETTER' => 'pdf_page_format_letter'), 'default' => 'A4'),
			'order_details_header' => array('type' => 'textarea', 'default' => ''),
			'order_details_header_right' => array('type' => 'textarea', 'default' => ''),
			'order_details_footer' => array('type' => 'textarea', 'default' => ''),
			'default_order_address' => array('type' => 'select', 'options' => array('shipping_same_as_billing' => 'shipping_same_as_billing', 'billing_same_as_shipping' => 'billing_same_as_shipping', 'none' => 'none'), 'default' => 'shipping_same_as_billing'),
			'tax_rounding' => array('type' => 'select', 'options' => array('y' => 'yes', 'n' => 'no'), 'default' => 'n'),
			'force_member_login' => array('type' => 'select', 'options' => array('y' => 'yes', 'n' => 'no'), 'default' => 'n'),
			'order_fields' => '',
			'security' => '',
			'default_country' => '',
			'default_region' => '',
			'default_shipping_method_id' => '',
			'secure_template_tags' => array('type' => 'select', 'options' => array('y' => 'yes', 'n' => 'no'), 'default' => 'n'),
			'show_cp_menu' => array('type' => 'select', 'options' => array('y' => 'yes', 'n' => 'no'), 'default' => 'y'),
			'order_invoice_url' => '',
			'empty_cart_on_logout' => array('type' => 'select', 'options' => array('y' => 'yes', 'n' => 'no'), 'default' => 'y'),
			'cc_payment_method' => array('type' => 'select', 'options' => array('purchase' => 'cc_payment_purchase', 'authorize' => 'cc_payment_authorize'), 'default' => 'purchase'),
			'cart_expiry' => 1440,
			'report_stats' => array('type' => 'select', 'options' => array('y' => 'yes', 'n' => 'no'), 'default' => 'y'),
			'report_date' => 0,
			'google_analytics_ecommerce' => array('type' => 'select', 'options' => array('y' => 'enabled', 'n' => 'disabled'), 'default' => 'y'),
			'conversion_tracking_extra' => array('type' => 'textarea', 'default' => ''),
		);

		$this->load();
	}

	/**
	 * Retrieve a config item
	 */
	function item($item)
	{
		return isset($this->_config_items[$item]) ? $this->_config_items[$item] : FALSE;
	}

	function item_config($item)
	{
		return isset($this->_config_defaults[$item]) ? $this->_config_defaults[$item] : FALSE;
	}

	/**
	 * Set a config item
	 */
	function set_item($key, $value)
	{
		if (isset($this->_config_defaults[$key]))
		{
			$this->_config_items[$key] = $value;
		}
	}

	/**
	 * Returns true if the current site has been configured for Store
	 */
	public function site_enabled()
	{
		return $this->_site_enabled;
	}

	/**
	 * Load current config from the database
	 */
	function load()
	{
		$this->_config_items = array();

		// load default values
		foreach ($this->_config_defaults as $key => $value)
		{
			$this->_config_items[$key] = store_setting_default($value);
		}

		// if user just installed 1.1.2 or earlier but hasn't run upgrade script yet,
		// this table won't exist yet, so just ignore database errors
		$db_debug = $this->EE->db->db_debug;
		$this->EE->db->db_debug = FALSE;
		$query = $this->EE->db->where('site_id', $this->EE->config->item('site_id'))->get('store_config');
		$this->EE->db->db_debug = $db_debug;

		// check for a result
		if ($query === FALSE OR $query->num_rows() == 0) return;

		// load settings
		$this->_site_enabled = TRUE;
		$row = $query->row_array();
		if ( ! empty($row['store_preferences']))
		{
			$settings = unserialize(base64_decode($row['store_preferences']));
			foreach ($settings as $key => $value)
			{
				$this->set_item($key, $value);
			}
		}

		// upgrade legacy weight units from <= 1.2.2
		if ($this->item('weight_units') == 'lbs')
		{
			$this->set_item('weight_units', 'lb');
			$this->save();
		}
	}

	/**
	 * Save current config to the database
	 */
	function save()
	{
		$this->EE->db->where('site_id', $this->EE->config->item('site_id'))
			->set('store_preferences', base64_encode(serialize($this->_config_items)))
			->update('store_config');
	}

	/**
	 * Kind of messy place to keep this function to get details for required order fields but whatever
	 */
	public function get_order_fields($get_defaults = FALSE)
	{
		$order_fields = array(
			'billing_name' => array('member_field' => ''),
			'billing_address1' => array('member_field' => ''),
			'billing_address2' => array('member_field' => ''),
			'billing_address3' => array('member_field' => ''),
			'billing_region' => array('member_field' => ''),
			'billing_country' => array('member_field' => ''),
			'billing_postcode' => array('member_field' => ''),
			'billing_phone' => array('member_field' => ''),
			'shipping_name' => array('member_field' => ''),
			'shipping_address1' => array('member_field' => ''),
			'shipping_address2' => array('member_field' => ''),
			'shipping_address3' => array('member_field' => ''),
			'shipping_region' => array('member_field' => ''),
			'shipping_country' => array('member_field' => ''),
			'shipping_postcode' => array('member_field' => ''),
			'shipping_phone' => array('member_field' => ''),
			'order_email' => array('member_field' => ''),
			'order_custom1' => array('title' => '', 'member_field' => ''),
			'order_custom2' => array('title' => '', 'member_field' => ''),
			'order_custom3' => array('title' => '', 'member_field' => ''),
			'order_custom4' => array('title' => '', 'member_field' => ''),
			'order_custom5' => array('title' => '', 'member_field' => ''),
			'order_custom6' => array('title' => '', 'member_field' => ''),
			'order_custom7' => array('title' => '', 'member_field' => ''),
			'order_custom8' => array('title' => '', 'member_field' => ''),
			'order_custom9' => array('title' => '', 'member_field' => ''),
		);

		if ($get_defaults) return $order_fields;

		// load data from current config
		$fields_config = $this->item('order_fields');
		if ( ! is_array($fields_config)) $fields_config = array();
		foreach ($order_fields as $field_name => $field)
		{
			if (isset($field['title']) AND isset($fields_config[$field_name]['title']))
			{
				$order_fields[$field_name]['title'] = $fields_config[$field_name]['title'];
			}

			if (isset($fields_config[$field_name]['member_field']))
			{
				$order_fields[$field_name]['member_field'] = $fields_config[$field_name]['member_field'];
			}
		}
		return $order_fields;
	}

	public function get_security()
	{
		$security_defaults = array('can_access_settings', 'can_add_payments');

		$result = array();
		$security = $this->item('security');

		foreach ($security_defaults as $key)
		{
			$result[$key] = (isset($security[$key]) AND is_array($security[$key])) ? $security[$key] : array();
		}

		return $result;
	}

	public function is_super_admin()
	{
		return $this->EE->session->userdata['group_id'] == 1;
	}

	public function has_privilege($privilege)
	{
		if ($this->is_super_admin())
		{
			return TRUE;
		}

		if ($privilege == 'can_access_inventory')
		{
			$store_channels = $this->EE->store_common_model->get_store_channels();
			$assigned_channels = $this->EE->functions->fetch_assigned_channels();

			// must be assigned to all Store channels
			return array_intersect($store_channels, $assigned_channels) == $store_channels;
		}

		$security = $this->EE->store_config->get_security();
		if ( in_array($this->EE->session->userdata['group_id'], $security[$privilege]))
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Shared between extension and mcp file
	 */
	public function generate_menu()
	{
		$menu = array();
		$menu['dashboard'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store';
		$menu[] = '----';
		$menu['orders'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;method=orders';

		if ($this->has_privilege('can_access_inventory'))
		{
			$menu['inventory'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;method=inventory';
		}

		$menu['reports'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;method=reports';

		if ($this->has_privilege('can_access_settings'))
		{
			$menu[] = '----';
			$menu['settings'] = BASE.'&amp;C=addons_modules&amp;M=show_module_cp&amp;module=store&amp;method=settings';
		}

		return $menu;
	}

	/**
	 * Add Store javascript & css to CP page header
	 * Used in both mcp.store.php and ft.store.php
	 */
	public function cp_head_script()
	{
		$this->EE->cp->add_to_head('<link rel="stylesheet" type="text/css" href="'.URL_THIRD_THEMES.'store/store.css" />');
		$this->EE->cp->add_to_foot('
			<script type="text/javascript">
			window.ExpressoStore = window.ExpressoStore || {};
			'.$this->js_format_currency().'
			</script>');
		$this->EE->cp->add_to_foot('<script type="text/javascript" src="'.URL_THIRD_THEMES.'store/cp.min.js'.'"></script>');
	}

	/**
	 * Javascript helper function to format currency based on current settings
	 */
	public function js_format_currency()
	{
		$config = array(
			'currencySymbol' => $this->item('currency_symbol'),
			'currencyDecimals' => (int)$this->EE->store_config->item('currency_decimals'),
			'currencyThousandsSep' => $this->item('currency_thousands_sep'),
			'currencyDecPoint' => $this->item('currency_dec_point'),
			'currencySuffix' => $this->item('currency_suffix'),
		);

		return 'window.ExpressoStore.currencyConfig = '.json_encode($config).';';
	}

	/**
	 * Add a file to the cache
	 */
	public function write_cache($filename, $data, $expires)
	{
		if ( ! @is_dir(STORE_CACHE_PATH))
		{
			@mkdir(STORE_CACHE_PATH, DIR_WRITE_MODE);
		}

		$content = json_encode(array('expires' => $expires, 'data' => $data));

		$success = @file_put_contents(STORE_CACHE_PATH.$filename, $content, LOCK_EX);
		@chmod(STORE_CACHE_PATH.$filename, FILE_WRITE_MODE);

		return (bool)$success;
	}

	/**
	 * Get the contents of a cached file, or FALSE for miss
	 */
	public function read_cache($filename)
	{
		$content = @file_get_contents(STORE_CACHE_PATH.$filename);
		if (empty($content)) return FALSE;

		// check cache hasn't expired
		$content = json_decode($content);
		if ($content->expires < $this->EE->localize->now)
		{
			$this->clear_cache($filename);
			return FALSE;
		}

		return $content->data;
	}

	/**
	 * Remove a file from the cache
	 */
	public function clear_cache($filename)
	{
		@unlink(STORE_CACHE_PATH.$filename);
	}

	/**
	 * Human Time: Compatibility method
	 */
	public function human_time($timestamp = NULL, $localize = TRUE, $seconds = FALSE)
	{
		if (version_compare(APP_VER, '2.6.0', '<'))
		{
			return $this->EE->localize->set_human_time($timestamp, $localize, $seconds);
		}
		else
		{
			return $this->EE->localize->human_time($timestamp, $localize, $seconds);
		}
	}

	/**
	 * Format Date: Compatibility method
	 */
	public function format_date($format, $timestamp = NULL, $localize = TRUE)
	{
		if (version_compare(APP_VER, '2.6.0', '<'))
		{
			return $this->EE->localize->decode_date($format, $timestamp, $localize);
		}
		else
		{
			return $this->EE->localize->format_date($format, $timestamp, $localize);
		}
	}

	/**
	 * String to Timestamp: Compatibility method
	 */
	public function string_to_timestamp($human_string, $localized = TRUE)
	{
		if (version_compare(APP_VER, '2.6.0', '<'))
		{
			return $this->EE->localize->convert_human_date_to_gmt($human_string);
		}
		else
		{
			return $this->EE->localize->string_to_timestamp($human_string, $localized);
		}
	}
}

/* End of file ./libraries/store_config.php */