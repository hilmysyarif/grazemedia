<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_shipping_model extends CI_Model {

	public $countries;

	public function __construct()
	{
		parent::__construct();

		// include standard expressionengine countries list
		if ( ! include(APPPATH.'config/countries.php'))
		{
			show_error($this->EE->lang->line('countryfile_missing'));
		}

		// support 'uk' country code which was removed in 2.6, since lots of sites depend on it
		// this will be fixed properly in 2.0
		if ( ! isset($countries['uk']) && isset($countries['gb']))
		{
			$countries['uk'] = $countries['gb'];
		}

		unset($countries['01']); // wtf is this for
		$this->countries = $countries;
	}

	public function get_shipping_method($shipping_method_id)
	{
		$result = $this->db->where('shipping_method_id', (int)$shipping_method_id)
			->where('site_id', $this->config->item('site_id'))
			->get('store_shipping_methods')->row_array();

		if (empty($result)) return FALSE;

		return $this->_process_shipping_method($result);
	}

	public function get_all_shipping_methods($enabled_only = FALSE)
	{
		$this->db->where('site_id', $this->config->item('site_id'));

		if ($enabled_only) $this->db->where('enabled', 1);

		$this->db->order_by('display_order', 'asc');
		$this->db->order_by('title', 'asc');
		$result = $this->db->get('store_shipping_methods')->result_array();

		if (empty($result)) return array();

		foreach ($result as $key => $row)
		{
			$result[$key] = $this->_process_shipping_method($row);
		}

		return $result;
	}

	protected function _process_shipping_method($shipping_method)
	{
		$shipping_method['class_name'] = lang(strtolower($shipping_method['class']));
		return $shipping_method;
	}

	public function insert_shipping_method($data)
	{
		$data['site_id'] = $this->config->item('site_id');

		$data['display_order'] = (int)$this->db->select('MAX(display_order)+1 AS display_order')
			->where('site_id', $this->config->item('site_id'))
			->get('store_shipping_methods')->row('display_order');

		$this->db->insert('store_shipping_methods', $data);
		return (int)$this->db->insert_id();
	}

	public function update_shipping_method($shipping_method_id, $data)
	{
		$this->db->where('shipping_method_id', (int)$shipping_method_id)
			->where('site_id', $this->config->item('site_id'))
			->update('store_shipping_methods', $data);
	}

	public function enable_shipping_methods($shipping_method_ids)
	{
		$this->db->where_in('shipping_method_id', $shipping_method_ids)
			->where('site_id', $this->config->item('site_id'))
			->update('store_shipping_methods', array('enabled' => 1));
	}

	public function disable_shipping_methods($shipping_method_ids)
	{
		$this->db->where_in('shipping_method_id', $shipping_method_ids)
			->where('site_id', $this->config->item('site_id'))
			->update('store_shipping_methods', array('enabled' => 0));
	}

	public function delete_shipping_method($shipping_method_id)
	{
		$this->db->where('shipping_method_id', $shipping_method_id)
			->where('site_id', $this->config->item('site_id'))
			->delete('store_shipping_methods');
	}

	public function update_shipping_methods_display_order($plugins_data)
	{
		foreach ($plugins_data as $shipping_method_id => $display_order)
		{
			$this->db->where('shipping_method_id', (int)$shipping_method_id)
				->where('site_id', $this->config->item('site_id'))
				->update('store_shipping_methods', array('display_order' => (int)$display_order));
		}
	}

	/*
	 * Functions used by the default shipping plugin
	 */

	public function get_all_shipping_rules($shipping_method_id, $enabled_only = FALSE)
	{
		$this->db->select('*,
			IF (`country_code` = "", 1, 0) AS `country_code_order`,
			IF (`region_code` = "", 1, 0) AS `region_code_order`,
			IF (`postcode` = "", 1, 0) AS `postcode_order`', FALSE);
		$this->db->where('shipping_method_id', (int)$shipping_method_id);

		if ($enabled_only) $this->db->where('enabled', 1);

		$this->db->order_by('priority DESC, country_code_order ASC, country_code ASC,
			region_code_order ASC, region_code ASC, postcode_order ASC, postcode ASC,
			min_order_qty ASC, max_order_qty ASC, min_order_total ASC, max_order_qty ASC,
			min_weight ASC, max_weight ASC');
		$result = $this->db->get('store_shipping_rules')->result_array();

		foreach ($result as $key => $row) $result[$key] = $this->_process_shipping_rule($row);

		return $result;
	}

	public function get_shipping_rule($shipping_method_id, $shipping_rule_id)
	{
		$this->db->where('shipping_method_id', (int)$shipping_method_id);
		$this->db->where('shipping_rule_id', (int)$shipping_rule_id);
		$row = $this->db->get('store_shipping_rules')->row_array();

		if (empty($row)) return FALSE;

		return $this->_process_shipping_rule($row);
	}

	private function _process_shipping_rule($data)
	{
		foreach (array('min_order_total', 'max_order_total', 'base_rate', 'per_item_rate',
			'per_weight_rate', 'min_rate', 'max_rate') as $field)
		{
			$data[$field.'_val'] = (float)$data[$field];
			$data[$field] = $data[$field] > 0 ? store_cp_format_currency($data[$field]) : '';
		}

		foreach (array('min_order_qty', 'max_order_qty', 'min_weight', 'max_weight') as $field)
		{
			if (empty($data[$field]))
			{
				$data[$field] = '';
			}
		}

		return $data;
	}

	public function insert_shipping_rule($data)
	{
		$this->db->insert('store_shipping_rules', $this->_clean_shipping_rule($data));
	}

	public function update_shipping_rule($shipping_rule_id, $data)
	{
		$this->db->where('shipping_rule_id', (int)$shipping_rule_id);
		$this->db->update('store_shipping_rules', $this->_clean_shipping_rule($data));
	}

	public function enable_shipping_rules($shipping_rule_ids)
	{
		$this->db->where_in('shipping_rule_id', $shipping_rule_ids);
		$this->db->update('store_shipping_rules', array('enabled' => 1));
	}

	public function disable_shipping_rules($shipping_rule_ids)
	{
		$this->db->where_in('shipping_rule_id', $shipping_rule_ids);
		$this->db->update('store_shipping_rules', array('enabled' => 0));
	}

	public function delete_shipping_rules($shipping_rule_ids)
	{
		$this->db->where_in('shipping_rule_id', $shipping_rule_ids);
		$this->db->delete('store_shipping_rules');
	}

	public function delete_instance_shipping_rules($shipping_method_id)
	{
		$this->db->where('shipping_method_id', (int)$shipping_method_id);
		$this->db->delete('store_shipping_rules');
	}

	private function _clean_shipping_rule($data)
	{
		$output = array();

		if (isset($data['shipping_method_id'])) $output['shipping_method_id'] = (int)$data['shipping_method_id'];
		if (isset($data['title'])) $output['title'] = $data['title'];

		if (isset($data['country_code']))
		{
			$output['country_code'] = $data['country_code'] == '*' ? '' : $data['country_code'];
		}
		if (isset($data['region_code']))
		{
			$output['region_code'] = $data['region_code'] == '*' ? '' : $data['region_code'];
		}
		if (isset($data['postcode'])) $output['postcode'] = $data['postcode'];

		foreach (array('min_order_total', 'max_order_total', 'base_rate', 'per_item_rate',
			'per_weight_rate', 'min_rate', 'max_rate') as $field)
		{
			if (isset($data[$field])) $output[$field] = store_parse_currency($data[$field]);
		}

		foreach (array('min_weight', 'max_weight', 'percent_rate') as $field)
		{
			if (isset($data[$field])) $output[$field] = (float)$data[$field];
		}

		foreach (array('min_order_qty', 'max_order_qty') as $field)
		{
			if (isset($data[$field])) $output[$field] = (int)$data[$field];
		}

		if (isset($data['enabled'])) $output['enabled'] = (int)$data['enabled'];
		if (isset($data['priority'])) $output['priority'] = $data['priority'];

		return $output;
	}

	public function get_countries($enabled_only, $fetch_regions = FALSE)
	{
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->order_by('country_code');
		$query = $this->db->get('store_countries')->result_array();

		$enabled_countries = array();
		foreach ($query as $row) $enabled_countries[] = $row['country_code'];

		$countries = array();
		foreach ($this->countries as $country_code => $country_name)
		{
			if (in_array($country_code, $enabled_countries))
			{
				$countries[$country_code] = array(
					'enabled' => 'y',
					'name' => $this->countries[$country_code],
					'regions' => array()
				);
			}
			elseif ( ! $enabled_only)
			{
				$countries[$country_code] = array(
					'enabled' => 'n',
					'name' => $country_name,
					'regions' => array()
				);
			}
		}
		if ($fetch_regions)
		{
			$this->db->where('site_id', $this->config->item('site_id'));
			$this->db->order_by('region_name');
			$query = $this->db->get('store_regions')->result_array();
			foreach ($query as $row)
			{
				if (isset($countries[$row['country_code']]))
				{
					$countries[$row['country_code']]['regions'][$row['region_code']] = $row['region_name'];
				}
			}
		}
		return $countries;
	}

	public function get_country_by_code($country_code, $fetch_regions = TRUE)
	{
		if (empty($country_code)) return FALSE;

		$this->db->where('country_code', $country_code);
		$this->db->where('site_id', $this->config->item('site_id'));
		$row = $this->db->get('store_countries')->row_array();

		if (empty($row)) return FALSE;

		$country = array(
			'code' => $row['country_code'],
			'name' => $this->countries[$row['country_code']],
			'regions' => array()
		);

		if ($fetch_regions)
		{
			$this->db->where('country_code', $country['code']);
			$this->db->where('site_id', $this->config->item('site_id'));
			$this->db->order_by('region_name');
			$regions = $this->db->get('store_regions')->result_array();

			foreach ($regions as $region)
			{
				$country['regions'][$region['region_code']] = array(
					'code' => $region['region_code'],
					'name' => $region['region_name']
				);
			}
		}

		return $country;
	}

	public function enable_countries($country_codes)
	{
		if (empty($country_codes) OR ! is_array($country_codes)) return;

		$enabled_countries = $this->get_countries(TRUE);
		foreach ($country_codes as $key)
		{
			if (isset($this->countries[$key]) AND ! isset($enabled_countries[$key]))
			{
				$this->db->insert('store_countries', array(
					'site_id' => $this->config->item('site_id'),
					'country_code' => $key,
				));
			}
		}
	}

	public function disable_countries($country_codes)
	{
		$this->load->library('store_config');

		if (empty($country_codes) OR ! is_array($country_codes)) return;

		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->where_in('country_code', $country_codes);
		$this->db->delete('store_countries');

		$default_country = $this->store_config->item('default_country');
		if (in_array($default_country, $country_codes))
		{
			$this->store_config->set_item('default_country', '');
			$this->store_config->set_item('default_region', '');
			$this->store_config->save();
		}
	}

	public function insert_regions($country_code, $region_data)
	{
		if (empty($country_code) OR empty($region_data)) return;

		$insert_regions = array();

		foreach ($region_data as $region)
		{
			if ((isset($region['delete']) AND $region['delete'] == 'y') OR
					(empty($region['code']) AND empty($region['name'])))
			{
				continue;
			}

			$new_region = array(
				'site_id' => $this->config->item('site_id'),
				'country_code' => $country_code,
				'region_code' => isset($region['code']) ? $region['code'] : '',
				'region_name' => isset($region['name']) ? $region['name'] : ''
			);

			$insert_regions[] = $new_region;
		}

		if ( ! empty($insert_regions))
		{
			$this->db->insert_batch('store_regions', $insert_regions);
		}
	}

	public function update_regions($country_code, $region_data)
	{
		if (empty($country_code) OR empty($region_data)) return;

		foreach ($region_data as $region_code => $region)
		{
			if ((isset($region['delete']) AND $region['delete'] == 'y') OR
					(empty($region['code']) AND empty($region['name'])))
			{
				$this->db->where('site_id', $this->config->item('site_id'));
				$this->db->where('country_code', $country_code);
				$this->db->where('region_code', $region_code);
				$this->db->delete('store_regions');
				continue;
			}

			$update_region = array(
				'region_code' => isset($region['code']) ? $region['code'] : '',
				'region_name' => isset($region['name']) ? $region['name'] : ''
			);

			if ( ! empty($region_code))
			{
				$this->db->where('site_id', $this->config->item('site_id'));
				$this->db->where('country_code', $country_code);
				$this->db->where('region_code', $region_code);
				$this->db->update('store_regions', $update_region);
			}
		}
	}

	public function get_tax_rates($enabled_only = FALSE)
	{
		$this->db->select('*,
			IF (`country_code` = "", 1, 0) AS `country_code_order`,
			IF (`region_code` = "", 1, 0) AS `region_code_order`', FALSE);

		$this->db->where('site_id', $this->config->item('site_id'));

		if ($enabled_only) $this->db->where('enabled', 1);

		$this->db->order_by('country_code_order ASC, country_code ASC, region_code_order ASC, region_code ASC');
		return $this->db->get('store_tax_rates')->result_array();
	}

	public function get_tax_rates_array()
	{
		$query = $this->get_tax_rates(TRUE);
		$output = array();

		foreach ($query as $row)
		{
			if ($row['country_code'] == NULL) $row['country_code'] = '*';
			if ($row['region_code'] == NULL) $row['region_code'] = '*';
			if ( ! isset($output[$row['country_code']])) $output[$row['country_code']] = array();
			if ( ! isset($output[$row['country_code']][$row['region_code']]))
			{
				$output[$row['country_code']][$row['region_code']] = $row;
			}
		}

		return $output;
	}

	public function get_tax_rate($tax_id)
	{
		$this->db->where('tax_id', (int)$tax_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		return $this->db->get('store_tax_rates')->row_array();
	}

	public function insert_tax_rate($data)
	{
		$data = $this->_clean_tax_rate_input($data);
		$data['site_id'] = $this->config->item('site_id');
		$this->db->insert('store_tax_rates', $data);
	}

	public function update_tax_rate($tax_id, $data)
	{
		$this->db->where('tax_id', (int)$tax_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_tax_rates', $this->_clean_tax_rate_input($data));
	}

	public function enable_tax_rates($tax_ids)
	{
		$this->db->where_in('tax_id', $tax_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_tax_rates', array('enabled' => 1));
	}

	public function disable_tax_rates($tax_ids)
	{
		$this->db->where_in('tax_id', $tax_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_tax_rates', array('enabled' => 0));
	}

	public function delete_tax_rates($tax_ids)
	{
		$this->db->where_in('tax_id', $tax_ids);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->delete('store_tax_rates');
	}

	private function _clean_tax_rate_input($data)
	{
		$output = array();

		if (isset($data['tax_name'])) $output['tax_name'] = $data['tax_name'];
		if (isset($data['country_code']))
		{
			$output['country_code'] = $data['country_code'] == '*' ? '' : $data['country_code'];
		}
		if (isset($data['region_code']))
		{
			$output['region_code'] = $data['region_code'] == '*' ? '' : $data['region_code'];
		}
		if (isset($data['tax_rate'])) $output['tax_rate'] = (float)$data['tax_rate'];
		if (isset($data['tax_percent'])) $output['tax_rate'] = (float)$data['tax_percent'] / 100;

		if (isset($data['tax_shipping'])) $output['tax_shipping'] = (int)$data['tax_shipping'];
		if (isset($data['enabled'])) $output['enabled'] = (int)$data['enabled'];

		return $output;
	}
}
/* End of file ./models/store_shipping_model.php */