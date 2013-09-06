<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_common_model extends CI_Model
{
	protected $_action_ids = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->library('store_config');
	}

	public function secure_request()
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) AND $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		{
			return TRUE;
		}
		if (empty($_SERVER['HTTPS']) OR strtolower($_SERVER['HTTPS']) == 'off')
		{
			return FALSE;
		}

		return TRUE;
	}

	public function is_store_ext_enabled()
	{
		return $this->config->item('allow_extensions') == 'y';
	}

	public function is_store_ft_enabled()
	{
		// find enabled fieldtype rows
		$this->db->from('fieldtypes');
		$this->db->where('name', 'store');

		return $this->db->count_all_results() > 0;
	}

	public function get_store_action_id($method)
	{
		$this->db->where('class', 'Store');
		$this->db->where('method', $method);
		$row = $this->db->get('actions')->row_array();
		if (empty($row)) return FALSE;
		else return (int)$row['action_id'];
	}

	public function get_enabled_sites()
	{
		return $this->db->from('store_config')
			->join('sites', 'sites.site_id = store_config.site_id')
			->get()->result_array();
	}

	public function install_templates($site_id)
	{
		$site_id = (int)$site_id;

		$this->load->model('template_model');
		$this->load->helper('directory');

		// ensure the example template group does not already exist
		$this->db->from('template_groups')
			->where('site_id', $site_id)
			->where('group_name', 'store_example');
		if ($this->db->count_all_results() > 0) return;

		// create the example template group
		$group_order = $this->super_model->count('template_groups') + 1;

		$group_id = $this->template_model->create_group(array(
			'group_name' => 'store_example',
			'group_order' => $group_order,
			'is_site_default' => 'n',
			'site_id' => $site_id,
		));

		$templates_dir = PATH_THIRD.'store/templates/';
		foreach (scandir($templates_dir) as $file_name)
		{
			if (substr($file_name, -4) == '.css')
			{
				$template_name = substr($file_name, 0, -4);
				$template_type = 'css';
			}
			elseif (substr($file_name, -5) == '.html')
			{
				$template_name = substr($file_name, 0, -5);
				$template_type = 'webpage';
			}
			else
			{
				continue;
			}

			$template_data = file_get_contents($templates_dir.$file_name);
			$data = array(
				'group_id'				=> $group_id,
				'template_name'  		=> $template_name,
				'template_notes'  		=> '',
				'cache'  				=> 'n',
				'refresh'  				=> 0,
				'no_auth_bounce'  		=> '',
				'php_parse_location'	=> 'o',
				'allow_php'  			=> 'n',
				'template_type' 		=> $template_type,
				'template_data'  		=> $template_data,
				'edit_date'				=> $this->localize->now,
				'site_id'				=> $site_id
	 		);

			$this->template_model->create_template($data);
		}
	}

	public function install_site($site_id, $duplicate_site_id = 0)
	{
		$this->load->model('store_shipping_model');

		$site_id = (int)$site_id;
		$duplicate_site_id = (int)$duplicate_site_id;

		if ($this->db->where('site_id', $site_id)->get('store_config')->num_rows() > 0)
		{
			// trying to install a site which already exists...
			return;
		}

		// ensure there are no existing countries or regions
		$this->db->where('site_id', $site_id)->delete('store_countries');
		$this->db->where('site_id', $site_id)->delete('store_regions');

		if ($duplicate_site_id)
		{
			return $this->_duplicate_site($site_id, $duplicate_site_id);
		}
		else
		{
			return $this->_install_new_site($site_id);
		}
	}

	protected function _install_new_site($site_id)
	{
		// install default countries
		$countries = array();
		foreach ($this->store_shipping_model->countries as $country_code => $country_name)
		{
			$countries[] = array('site_id' => $site_id, 'country_code' => $country_code);
		}
		$this->db->insert_batch('store_countries', $countries);

		// install default regions
		$regions = array();
		foreach ($this->_default_us_states() as $region_code => $region_name)
		{
			$regions[] = array('site_id' => $site_id, 'country_code' => 'us',
				'region_code' => $region_code, 'region_name' => $region_name);
		}
		foreach ($this->_default_canadian_states() as $region_code => $region_name)
		{
			$regions[] = array('site_id' => $site_id, 'country_code' => 'ca',
				'region_code' => $region_code, 'region_name' => $region_name);
		}
		foreach ($this->_default_australian_states() as $region_code => $region_name)
		{
			$regions[] = array('site_id' => $site_id, 'country_code' => 'au',
				'region_code' => $region_code, 'region_name' => $region_name);
		}
		$this->db->insert_batch('store_regions', $regions);

		// add default email templates
		$this->lang->loadfile('store_email', 'store');
		$this->db->insert('store_email_templates', array(
			'site_id' => $site_id,
			'name' => 'order_confirmation',
			'subject' => lang('order_confirmation_subject'),
			'contents' => lang('order_confirmation_contents'),
			'mail_format' => 'text',
			'word_wrap' => 'y',
			'enabled' => 'y',
		));
		$order_confirmation_email_id = (int)$this->db->insert_id();

		$this->db->insert('store_email_templates', array(
			'site_id' => $site_id,
			'name' => 'payment_confirmation',
			'subject' => lang('payment_confirmation_subject'),
			'contents' => lang('payment_confirmation_contents'),
			'mail_format' => 'text',
			'word_wrap' => 'y',
			'enabled' => 'y',
		));

		// add default order status and link to order confirmation email template
		$this->db->insert('store_order_statuses', array(
			'site_id' => $site_id,
			'name' => 'new',
			'highlight' => '',
			'email_template' => $order_confirmation_email_id,
			'display_order' => 0,
			'is_default' => 'y'));

		// install default settings
		$this->db->set('site_id', $site_id)->insert('store_config');
	}

	protected function _duplicate_site($site_id, $duplicate_site_id)
	{
		$site_config = $this->db->where('site_id', $duplicate_site_id)
			->get('store_config')->row_array();
		if (empty($site_config))
		{
			// trying to duplicate a site which doesn't exist...
			return;
		}

		// duplicate countries
		$this->db->query('INSERT INTO '.$this->db->protect_identifiers('store_countries', TRUE).
			' (site_id, country_code) SELECT ?, country_code'.
			' FROM '.$this->db->protect_identifiers('store_countries', TRUE).
			' WHERE site_id = ?', array($site_id, $duplicate_site_id));

		// duplicate regions
		$this->db->query('INSERT INTO '.$this->db->protect_identifiers('store_regions', TRUE).
			' (site_id, country_code, region_code, region_name)'.
			' SELECT ?, country_code, region_code, region_name'.
			' FROM '.$this->db->protect_identifiers('store_regions', TRUE).
			' WHERE site_id = ?', array($site_id, $duplicate_site_id));

		// duplicate payment methods
		$this->db->query('INSERT INTO '.$this->db->protect_identifiers('store_payment_methods', TRUE).
			' (site_id, class, name, title, settings, enabled)'.
			' SELECT ?, class, name, title, settings, enabled'.
			' FROM '.$this->db->protect_identifiers('store_payment_methods', TRUE).
			' WHERE site_id = ?', array($site_id, $duplicate_site_id));

		// duplicate shipping methods
		$shipping_methods = $this->db->where('site_id', $duplicate_site_id)
			->get('store_shipping_methods')->result_array();
		if ( ! empty($shipping_methods))
		{
			$shipping_method_ids = array();
			foreach ($shipping_methods as $key => $row)
			{
				$old_id = $row['shipping_method_id'];
				unset($row['shipping_method_id']);
				$row['site_id'] = $site_id;
				$this->db->insert('store_shipping_methods', $row);

				// record new plugin ID to match with shipping rules
				$shipping_method_ids[$old_id] = $this->db->insert_id();
			}

			// duplicate shipping rules for existing plugins
			$shipping_rules = $this->db->where_in('shipping_method_id', array_keys($shipping_method_ids))
				->get('store_shipping_rules')->result_array();
			if ( ! empty($shipping_rules))
			{
				foreach ($shipping_rules as $key => $row)
				{
					// update plugin id
					unset($shipping_rules[$key]['shipping_rule_id']);
					$shipping_rules[$key]['shipping_method_id'] = $shipping_method_ids[$row['shipping_method_id']];
				}
				$this->db->insert_batch('store_shipping_rules', $shipping_rules);
			}
		}

		// duplicate tax rates
		$tax_rates = $this->db->where('site_id', $duplicate_site_id)
			->get('store_tax_rates')->result_array();
		if ( ! empty($tax_rates))
		{
			foreach ($tax_rates as $key => $row)
			{
				$tax_rates[$key]['site_id'] = $site_id;
				unset($tax_rates[$key]['tax_id']);
			}
			$this->db->insert_batch('store_tax_rates', $tax_rates);
		}

		// duplicate email templates (should always be at least two)
		$email_templates = $this->db->where('site_id', $duplicate_site_id)
			->get('store_email_templates')->result_array();
		$email_ids = array();
		foreach ($email_templates as $key => $row)
		{
			$old_id = $row['template_id'];
			unset($row['template_id']);
			$row['site_id'] = $site_id;
			$this->db->insert('store_email_templates', $row);

			// record new template ID to match with order statuses
			$email_ids[$old_id] = $this->db->insert_id();
		}

		// duplicate order statuses (should always be at least one)
		$order_statuses = $this->db->where('site_id', $duplicate_site_id)
			->get('store_order_statuses')->result_array();
		foreach ($order_statuses as $key => $row)
		{
			unset($order_statuses[$key]['order_status_id']);
			$order_statuses[$key]['site_id'] = $site_id;

			// update email template id
			$old_email = $row['email_template'];
			$order_statuses[$key]['email_template'] =
				($old_email AND isset($email_ids[$old_email])) ? $email_ids[$old_email] : NULL;
		}
		$this->db->insert_batch('store_order_statuses', $order_statuses);

		// duplicate settings
		$site_config['site_id'] = $site_id;
		$this->db->insert('store_config', $site_config);
	}

	protected function _default_us_states()
	{
		return array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);
	}

	protected function _default_canadian_states()
	{
		return array(
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NT' => 'Northwest Territories',
			'NS' => 'Nova Scotia',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon',
		);
	}

	protected function _default_australian_states()
	{
		return array(
			'ACT' => 'Australian Capital Territory',
			'NSW' => 'New South Wales',
			'NT' => 'Northern Territory',
			'QLD' => 'Queensland',
			'SA' => 'South Australia',
			'TAS' => 'Tasmania',
			'VIC' => 'Victoria',
			'WA' => 'Western Australia',
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Cart functions
	|--------------------------------------------------------------------------
	*/

	public function get_cart_by_id($cart_id)
	{
		$this->db->from('store_carts')
			->where('site_id', $this->config->item('site_id'))
			->where('cart_id', $cart_id);

		/* Hidden config option: store_cart_match_ip
		 * If set to true, shopping carts will be restricted to the IP address
		 * used to create them. This improves security, but will cause issues for
		 * users with dynamic IP addresses.
		 */
		if ($this->config->item('store_cart_match_ip') === TRUE)
		{
			$this->db->where('ip_address', $this->input->ip_address());
		}

		return $this->db->get()->row_array();
	}

	public function insert_cart($data)
	{
		$data['site_id'] = $this->config->item('site_id');
		$this->db->insert('store_carts', $data);
	}

	public function update_cart($cart_id, $data)
	{
		$this->db->where('cart_id', $cart_id);
		$this->db->update('store_carts', $data);
	}

	/**
	 * Remove a specific cart. Also cleans out any other expired carts at the same time.
	 */
	public function remove_cart($cart_id)
	{
		$this->db->where('cart_id', $cart_id);
		$this->db->or_where('date <', $this->localize->now - ($this->store_config->item('cart_expiry') * 60));
		$this->db->delete('store_carts');
	}

	private function _process_promo_code($promo_code, $cp_format_currency = FALSE)
	{
		if ($promo_code['type'] == 'p')
		{
			$promo_code['value_str'] =  (float)$promo_code['value'].'%';
		}
		else
		{
			$promo_code['value_str'] = $cp_format_currency ?
				store_cp_format_currency($promo_code['value']) :
				store_format_currency($promo_code['value']);
		}

		if ($promo_code['member_group_id'] == 0) $promo_code['member_group_title'] = lang('all');

		return $promo_code;
	}

	public function get_promo_code_by_id($promo_code_id, $cp_format_currency = FALSE)
	{
		$this->db->select('p.*, g.group_title as member_group_title');
		$this->db->from('store_promo_codes p');
		$this->db->join('member_groups g', 'p.member_group_id = g.group_id', 'left');
		$this->db->where('p.promo_code_id' , (int)$promo_code_id);
		$this->db->where('p.site_id', $this->config->item('site_id'));

		$promo_code = $this->db->get()->row_array();

		if (empty($promo_code)) return FALSE;
		else return $this->_process_promo_code($promo_code, $cp_format_currency);
	}

	public function get_promo_code_by_code($code, $only_enabled = FALSE)
	{
		$this->db->where('promo_code', $code);
		$this->db->where('site_id', $this->config->item('site_id'));

		if ($only_enabled) $this->db->where('enabled', 'y');

		$promo_code = $this->db->get('store_promo_codes')->row_array();

		if (empty($promo_code)) return FALSE;
		else return $this->_process_promo_code($promo_code);
	}

	public function validate_promo_code($promo_code_data)
	{
		if (empty($promo_code_data)) return lang('promo_code_invalid');

		if ($promo_code_data['enabled'] != 'y') return lang('promo_code_invalid');

		if ($promo_code_data['member_group_id'] > 0 AND $promo_code_data['member_group_id'] != $this->session->userdata['group_id'])
		{
			return lang('promo_code_invalid');
		}

		if ( ! empty($promo_code_data['start_date']) AND $promo_code_data['start_date'] > $this->localize->now)
		{
			return lang('promo_code_invalid');
		}

		if ($promo_code_data['per_user_limit'] > 0 AND $this->session->userdata['member_id'] == 0)
		{
			// must be logged in if promo code has a per-user limit
			return lang('promo_code_invalid');
		}

		if ( ! empty($promo_code_data['end_date']) AND $promo_code_data['end_date'] < $this->localize->now)
		{
			return lang('promo_code_expired');
		}

		if ($promo_code_data['use_limit'] > 0 AND $promo_code_data['use_count'] >= $promo_code_data['use_limit'])
		{
			return lang('promo_code_expired');
		}

		if ($promo_code_data['per_user_limit'] > 0)
		{
			$this->db->from('store_orders');
			$this->db->where('member_id', $this->session->userdata['member_id']);
			$this->db->where('promo_code_id', $promo_code_data['promo_code_id']);
			$user_use_count = $this->db->count_all_results();
			if ($user_use_count >= $promo_code_data['per_user_limit'])
			{
				return lang('promo_code_user_limit');
			}
		}

		// no errors
		return FALSE;
	}

	public function get_all_promo_codes()
	{
		$this->db->select('p.*, g.group_title as member_group_title');
		$this->db->from('store_promo_codes as p');
		$this->db->join('member_groups g', 'p.member_group_id = g.group_id', 'left');
		$this->db->where('p.site_id', $this->config->item('site_id'));
		$result = $this->db->get()->result_array();

		foreach ($result as $key => $promo_code) $result[$key] = $this->_process_promo_code($promo_code, TRUE);

		return $result;
	}

	/**
	 * Get an array of channel ids which contain Store products.
	 * Cached to avoid extra db queries.
	 *
	 * @return array
	 */
	public function get_store_channels()
	{
		static $product_channels = NULL;

		if (is_null($product_channels))
		{
			$query = $this->db->distinct()
				->select('channel_id')
				->from('channels')
				->join('channel_fields', 'channel_fields.group_id = channels.field_group')
				->where('channel_fields.field_type', 'store')
				->get()->result_array();

			$product_channels = array();
			foreach ($query as $row)
			{
				$product_channels[] = $row['channel_id'];
			}
		}

		return $product_channels;
	}

	public function get_product_categories_select()
	{
		$results = $this->db->select('cat_group')
			->from('channels')
			->join('channel_fields', 'channels.field_group = channel_fields.group_id')
			->where('channel_fields.field_type', 'store')
			->get()->result_array();

		$categories = array();
		foreach ($results as $row)
		{
			$categories[] = $row['cat_group'];
		}

		$this->load->library('api');
		$this->api->instantiate('channel_categories');
		return $this->api_channel_categories->category_tree(implode('|', $categories), NULL, 'a');
	}

	public function get_entry_page_url($site_id, $entry_id)
	{
		$site_pages = $this->config->item('site_pages');

		if ($site_pages !== FALSE && isset($site_pages[$site_id]['uris'][$entry_id]))
		{
			return array(
				'page_uri' => $site_pages[$site_id]['uris'][$entry_id],
				'page_url' => $this->functions->create_page_url($site_pages[$site_id]['url'], $site_pages[$site_id]['uris'][$entry_id])
			);
		}
		else
		{
			return array(
				'page_uri' => NULL,
				'page_url' => NULL,
			);
		}
	}

	public function insert_promo_code($promo_code)
	{
		$promo_code['site_id'] = $this->config->item('site_id');
		$this->db->insert('store_promo_codes', $promo_code);
	}

	public function update_promo_code($promo_code_id, $promo_code)
	{
		$this->db->where('promo_code_id', (int)$promo_code_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_promo_codes', $promo_code);
	}

	public function enable_promo_codes($promo_code_ids)
	{
		$this->db->where_in('promo_code_id', $promo_code_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_promo_codes', array('enabled' => 'y'));
	}

	public function disable_promo_codes($promo_code_ids)
	{
		$this->db->where_in('promo_code_id', $promo_code_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_promo_codes', array('enabled' => 'n'));
	}

	public function delete_promo_codes($promo_code_ids)
	{
		$this->db->where_in('promo_code_id', $promo_code_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->delete('store_promo_codes');
	}

	private function _process_email_template($template)
	{
		if ($template['name'] == 'order_confirmation' OR $template['name'] == 'payment_confirmation')
		{
			$template['locked'] = 'y';
		}
		else
		{
			$template['locked'] = 'n';
		}
		return $template;
	}

	public function get_email_template($template_id)
	{
		$this->db->where('template_id' , (int)$template_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		$template = $this->db->get('store_email_templates')->row_array();

		return $this->_process_email_template($template);
	}

	public function get_email_template_by_name($template_name)
	{
		$this->db->where('name', $template_name);
		$this->db->where('site_id', $this->config->item('site_id'));
		$template = $this->db->get('store_email_templates')->row_array();

		return $this->_process_email_template($template);
	}

	public function get_all_email_templates()
	{
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->order_by('name');
		$query = $this->db->get('store_email_templates')->result_array();
		$result = array();

		foreach ($query as $row)
		{
			$result[$row['template_id']] = $this->_process_email_template($row);
		}

		return $result;
	}

	public function insert_email_template($email_template)
	{
		$email_template = $this->_clean_email_template_input($email_template);
		$email_template['site_id'] = $this->config->item('site_id');
		$this->db->insert('store_email_templates', $email_template);
	}

	public function update_email_template($template_id, $email_template)
	{
		$email_template = $this->_clean_email_template_input($email_template);
		$this->db->where('template_id', (int)$template_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_email_templates', $email_template);
	}

	public function enable_email_templates($template_ids)
	{
		$this->db->where_in('template_id', $template_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_email_templates', array('enabled' => 'y'));
	}

	public function disable_email_templates($template_ids)
	{
		$this->db->where_in('template_id', $template_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_email_templates', array('enabled' => 'n'));
	}

	public function delete_email_templates($template_ids)
	{
		$this->db->where_in('template_id', $template_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->delete('store_email_templates');

		$this->db->where_in('email_template', $template_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_order_statuses', array('email_template' => '0'));
	}

	private function _clean_email_template_input($data)
	{
		$output = array();

		if (isset($data['name'])) $output['name'] = $data['name'];
		if (isset($data['subject'])) $output['subject'] = $data['subject'];
		if (isset($data['contents'])) $output['contents'] = $data['contents'];
		if (isset($data['bcc'])) $output['bcc'] = $data['bcc'];
		if (isset($data['mail_format'])) $output['mail_format'] = $data['mail_format'];
		if (isset($data['word_wrap'])) $output['word_wrap'] = $data['word_wrap'];
		if (isset($data['enabled'])) $output['enabled'] = $data['enabled'] == 'y' ? 'y' : 'n';

		return $output;
	}

	public function get_member_fields_select()
	{
		$query = $this->db->select('m_field_id, m_field_name, m_field_label')
			->from('member_fields')->get()->result_array();

		$member_fields = array();
		foreach ($query as $row)
		{
			$member_fields['m_field_id_'.$row['m_field_id']] = $row['m_field_label'];
		}

		/**
		 * Handles the selection of Zoo Visitor fields
		 *
		 * @author  Nico De Gols <nico@ee-zoo.com>
		 * @since   1.5.3
		 */
		if ( ! empty($this->config->_global_vars['zoo_visitor_channel_name']))
		{
			$query = $this->db->select('cf.field_id, cf.field_label')
				->from('exp_channels c')
				->join('exp_channel_fields cf', 'cf.group_id = c.field_group')
				->where('channel_name', $this->config->_global_vars['zoo_visitor_channel_name'])
				->where_not_in('cf.field_type', array('zoo_visitor', 'zoo_plus', 'playa', 'matrix', 'channel_images', 'channel_files'))
				->get()->result_array();

			$zoo_fields = array();
			foreach ($query as $row)
			{
				$zoo_fields['field_id_'.$row['field_id']] = $row['field_label'];
			}

			// add zoo optgroup
			if ( ! empty($zoo_fields))
			{
				$member_fields = array(lang('optgroup_member_fields') => $member_fields);
				$member_fields[lang('optgroup_zoo_fields')] = $zoo_fields;
			}
		}

		return array_merge(array('' => ''), array_filter($member_fields));
	}

	public function count_member_custom_fields()
	{
		$this->db->select('mf.*');
		$this->db->from('member_fields as mf');
		return $this->db->get()->num_rows();
	}

	public function load_member_data($member_id)
	{
		// Standard member fields
		$member_data = $this->db->where('member_id', $member_id)
			->get('member_data')->row_array();

		// Zoo Visitor fields
		if ( ! empty($this->config->_global_vars['zoo_visitor_id']))
		{
			foreach ($this->config->_global_vars as $key => $value)
			{
				if (strpos($key, 'visitor:global:field_id_') === 0)
				{
					$member_data[str_replace('visitor:global:', '', $key)] = $value;
				}
			}
		}

		return $member_data;
	}

	public function save_member_data($member_id, $data)
	{
		$order_fields = $this->store_config->get_order_fields();

		// split out standard & channel member fields
		$member_fields = array();
		$channel_fields = array();

		foreach ($order_fields as $field_name => $field)
		{
			$member_field = $field['member_field'];
			if (strpos($member_field, 'm_field_id_') === 0)
			{
				$member_fields[$member_field] = $data[$field_name];
			}
			elseif (strpos($member_field, 'field_id_') === 0)
			{
				$channel_fields[$member_field] = $data[$field_name];
			}
		}

		// update standard member fields
		if ( ! empty($member_fields))
		{
			$this->db->where('member_id', $member_id)
				->update('member_data', $member_fields);
		}

		// update Zoo Visitor fields
		if ( ! empty($channel_fields) AND ! empty($this->config->_global_vars['zoo_visitor_id']) AND
			$this->config->_global_vars['zoo_member_id'] == $member_id)
		{
			$this->db->where('entry_id', $this->config->_global_vars['zoo_visitor_id'])
				->update('channel_data', $channel_fields);
		}
	}

	public function get_file_id($fileurl, $filename)
	{
		if (empty($fileurl) OR empty($filename)) return FALSE;
		$fileurl .= '/';

		$this->db->select('id');
		$this->db->where('url', $fileurl);
		$upload_dir = $this->db->get('upload_prefs')->row_array();
		if (empty($upload_dir)) return FALSE;

		$this->db->select('file_id');
		$this->db->where('upload_location_id', $upload_dir['id']);
		$this->db->where('file_name', $filename);
		$file = $this->db->get('files')->row_array();
		if (empty($file)) return FALSE;

		return $file['file_id'];
	}

	public function get_file_path($file_id)
	{
		$path = $this->db->where('file_id', $file_id)
		    ->get('files')->row('rel_path');

		if (empty($path)) return FALSE;

		// is this a relative path?
		if (strpos($path, '/') !== 0)
		{
			$path = APPPATH.'../'.$path;
		}

		return $path;
	}

	public function get_action_id($method)
	{
		if (empty($this->_action_ids))
		{
			$result = $this->db->where('class', 'Store')
				->get('actions')->result_array();

			foreach ($result as $row)
			{
				$this->_action_ids[$row['method']] = (int)$row['action_id'];
			}
		}

		return isset($this->_action_ids[$method]) ? $this->_action_ids[$method] : 0;
	}

	public function get_action_url($method)
	{
		$url = $this->functions->fetch_site_index().QUERY_MARKER.
			'ACT='.$this->get_action_id($method);

		if ($this->secure_request())
		{
			$url = str_ireplace('http://', 'https://', $url);
		}

		return $url;
	}

	public function create_url($path = FALSE)
	{
		if ($path === FALSE)
		{
			$url = $this->functions->fetch_current_uri();
		}
		else
		{
			$url = $this->functions->create_url($path);
		}

		if ($this->secure_request())
		{
			$url = str_ireplace('http://', 'https://', $url);
		}

		return $url;
	}

	public function get_snippets()
	{
		$result = $this->db->select('snippet_name, snippet_contents')
			->where('site_id', $this->config->item('site_id'))
			->or_where('site_id', 0)
			->get('snippets')->result_array();

		$snippets = array();
		foreach ($result as $row)
		{
			$snippets[$row['snippet_name']] = $row['snippet_contents'];
		}
		return $snippets;
	}
}

/* End of file store_common_model.php */