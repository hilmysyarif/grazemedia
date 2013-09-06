<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

require_once(PATH_THIRD.'store/config.php');

class Store_ft extends EE_Fieldtype
{
	public $info = array(
		'name' => 'Store Product Details',
		'version' => STORE_VERSION
	);

	/*
	 * WARNING: Don't overload the constructor (it breaks things)
	 */

	/**
	 * Display field on the publish tab
	 */
	function display_field($field_data)
	{
		$this->EE->lang->loadfile('store');
		$this->EE->load->library(array('store_config', 'table'));
		$this->EE->load->model(array('store_common_model', 'store_products_model'));

		// load store css + js
		$this->EE->store_config->cp_head_script();
		$this->EE->cp->add_to_foot('
			<script type="text/javascript">
			ExpressoStore.fieldStockUrl = "'.$this->EE->store_common_model->get_action_url('act_field_stock').'";
			</script>');
		$this->EE->cp->add_js_script(array('ui' => array('datepicker', 'sortable')));

		$data = array(
			'field_name' => $this->field_name,
			'weight_units' => $this->EE->store_config->item('weight_units'),
			'dimension_units' => $this->EE->store_config->item('dimension_units'),
			'modifier_select' => array(
				'var' => lang('variation'),
				'var_single_sku' => lang('variation_single_sku'),
				'text' => lang('text_input')),
			'product' => array(
				'entry_id' => (int)$this->EE->input->get('entry_id'),
				'regular_price' => '',
				'sale_price' => '',
				'sale_price_enabled' => 'n',
				'sale_start_date' => '',
				'sale_end_date' => '',
				'weight' => '',
				'dimension_l' => '',
				'dimension_w' => '',
				'dimension_h' => '',
				'handling' => '',
				'free_shipping' => '',
				'tax_exempt' => '',
				'modifiers' => array(),
				'stock' => array())
		);

		$post_data = $this->EE->input->post('store_product_field', TRUE);
		if (is_array($post_data))
		{
			$data['product'] = array_merge($data['product'], $post_data);
		}
		else
		{
			$product = $this->EE->store_products_model->find_by_id($data['product']['entry_id']);
			if ( ! empty($product))
			{
				$data['product'] = $product;

				// use CP currency format
				foreach (array('regular_price', 'sale_price', 'handling') as $field_name)
				{
					$data['product'][$field_name] = store_cp_format_currency($data['product'][$field_name.'_val']);
				}
				foreach ($data['product']['modifiers'] as $mod_key => $mod)
				{
					foreach ($mod['options'] as $opt_key => $opt)
					{
						if ( ! empty($opt['opt_price_mod_val']))
						{
							$data['product']['modifiers'][$mod_key]['options'][$opt_key]['opt_price_mod'] =
								store_cp_format_currency($opt['opt_price_mod_val']);
						}
					}
				}

				// format sale dates
				$data['product']['sale_start_date'] = $data['product']['sale_start_date'] ? $this->EE->store_config->human_time($data['product']['sale_start_date']) : '';
				$data['product']['sale_end_date'] = $data['product']['sale_end_date'] ? $this->EE->store_config->human_time($data['product']['sale_end_date']) : '';
			}
		}

		$data['new_mod_key'] = empty($data['product']['modifiers']) ? 1 : max(array_keys($data['product']['modifiers'])) + 1;
		$data['stock_html'] = $this->EE->store_products_model->generate_stock_matrix_html($data['product'], 'store_product_field');

		return $this->EE->load->view('field', $data, TRUE);
	}

	/**
	 * Prep the data for saving
	 *
	 * Cache product SKUs inside our custom field, so that it can be found by EE search tags.
	 * We never actually use the data stored in the custom field, it is purely here for search.
	 */
	function save($data)
	{
		$field_data = $this->EE->input->post('store_product_field', TRUE);
		$skus = array();

		if (isset($field_data['stock']))
		{
			foreach ($field_data['stock'] as $stock)
			{
				$skus[] = $stock['sku'];
			}
		}

		return implode(' ', $skus);
	}

	/**
	 * Runs after an entry has been saved
	 */
	function post_save($data)
	{
		$this->EE->load->library('store_config');
		$this->EE->load->model('store_products_model');

		$entry_id = $this->settings['entry_id'];
		$post_data = $this->EE->input->post('store_product_field', TRUE);
		$this->EE->store_products_model->update_product($entry_id, $post_data);
	}

	function delete($entry_ids)
	{
		$this->EE->load->model('store_products_model');
		foreach($entry_ids as $entry_id)
		{
			$this->EE->store_products_model->delete_product($entry_id);
		}
	}

	function validate($data)
	{
		$this->EE->load->model('store_products_model');
		$field_data = $this->EE->input->post('store_product_field', TRUE);
		$entry_id = $this->EE->input->post('entry_id', TRUE);

		if (isset($field_data['modifiers']))
		{
			foreach ($field_data['modifiers'] as $mod_id => $modifier)
			{
				// require that mod names are inputted (if row hasn't been removed)
				if (isset($modifier['mod_type']))
				{
					$this->_validate_field("store_product_field[modifiers][{$mod_id}][mod_name]", $modifier['mod_name'], 'Modifier Title', 'required');
				}
			}
		}

		// require that prices be put in
		if (isset($field_data['regular_price']))
		{
			$this->_validate_field("store_product_field[regular_price]", $field_data['regular_price'], 'Price', 'required');
		}

		// require that inputted SKUs all be unique
		if (isset($field_data['stock']))
		{
			$skus = array();
			foreach ($field_data['stock'] as $row_id => $stock)
			{
				$skus[] = $stock['sku'];

				// note that we don't actually use real validation rules here, just set something that will
				// always fail (max_length[0] or required), so that we can display our own error text.
				if ($stock['sku'] == '')
				{
					// require that SKUs exist
					$this->_validate_field("store_product_field[stock][{$row_id}][sku]", $stock['sku'], 'sku', 'required');
					$_POST['store_product_field']['stock'][$row_id]['sku_error'] = lang('sku_required');
				}
				elseif (strlen($stock['sku']) > 40)
				{
					// must be no more than 40 chars
					$this->_validate_field("store_product_field[stock][{$row_id}][sku]", $stock['sku'], 'sku', 'max_length[0]');
					$_POST['store_product_field']['stock'][$row_id]['sku_error'] = lang('sku_too_long');
				}
				elseif ($this->EE->store_products_model->check_existing_skus($entry_id, $stock['sku']) > 0)
				{
					// must be unique
					$this->_validate_field("store_product_field[stock][{$row_id}][sku]", $stock['sku'], 'sku', 'max_length[0]');
					$_POST['store_product_field']['stock'][$row_id]['sku_error'] = lang('sku_not_unique');
				}
			}

			$sku_counts = array_count_values($skus); // produces array ( 'sku' => 'count of sku occurence', 'sku2' => etc)
			foreach ($sku_counts as $sku => $count)
			{
				if ($count > 1)
				{
					foreach ($field_data['stock'] as $row_id => $stock)
					{
						if ($stock['sku'] == $sku)
						{
							$this->_validate_field("store_product_field[stock][{$row_id}][sku]", $sku, 'sku', 'max_length[0]');
							$_POST['store_product_field']['stock'][$row_id]['sku_error'] = lang('sku_not_unique');
						}
					}
				}
			}
		}

		return TRUE;
	}

	private function _validate_field($post_name, $post_value, $lang_name, $rules)
	{
		$this->EE->form_validation->set_rules($post_name, 'lang:'.$lang_name, $rules);
		$row =& $this->EE->form_validation->_field_data[$post_name];

		$row['postdata'] = isset($post_value) ? $post_value : NULL;
		$this->EE->form_validation->_execute($row, explode('|', $row['rules']), $row['postdata']);
	}

	/**
	 * For all of the following functions, the $data array should be pre-populated by
	 * our channel_entries_query_result() function in ext.store.php
	 */
	function replace_tag($data, $params = array(), $tagdata = FALSE)
	{

	}

	function replace_price($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['price'];
	}

	function replace_price_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['price_val'];
	}

	function replace_price_inc_tax($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['price_inc_tax'];
	}

	function replace_price_inc_tax_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['price_inc_tax_val'];
	}

	function replace_regular_price($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['regular_price'];
	}

	function replace_regular_price_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['regular_price_val'];
	}

	function replace_regular_price_inc_tax($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['regular_price_inc_tax'];
	}

	function replace_regular_price_inc_tax_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['regular_price_inc_tax_val'];
	}

	function replace_sale_price($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['sale_price'];
	}

	function replace_sale_price_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['sale_price_val'];
	}

	function replace_sale_price_inc_tax($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['sale_price_inc_tax'];
	}

	function replace_sale_price_inc_tax_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['sale_price_inc_tax_val'];
	}

	function replace_on_sale($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['on_sale'];
	}

	function replace_sale_start_date($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $this->_replace_date($data['sale_start_date'], $params);
	}

	function replace_sale_end_date($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $this->_replace_date($data['sale_end_date'], $params);
	}

	protected function _replace_date($timestamp, $params)
	{
		if ( ! empty($timestamp))
		{
			if (isset($params['format']))
			{
				$this->EE->load->library('store_config');

				return $this->EE->store_config->format_date($params['format'], $timestamp);
			}

			return $timestamp;
		}
	}

	function replace_you_save($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['you_save'];
	}

	function replace_you_save_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['you_save_val'];
	}

	function replace_you_save_inc_tax($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['you_save_inc_tax'];
	}

	function replace_you_save_inc_tax_val($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['you_save_inc_tax_val'];
	}

	function replace_you_save_percent($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data))
		{
			return $data['you_save_percent'];
		}
	}

	function replace_total_stock($data, $params = array(), $tagdata = FALSE)
	{
		if (is_array($data)) return $data['total_stock'];
	}

	/**
	 * Display Settings Screen
	 *
	 * @access	public
	 * @return	default global settings
	 *
	 */
	function display_settings($data)
	{

	}

	/**
	 * Save Settings
	 *
	 * @access	public
	 * @return	field settings
	 *
	 */
	function save_settings($data)
	{
		return array();
	}
}

/* End of file ft.store.php */