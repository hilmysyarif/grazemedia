<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_shipping_default extends Store_shipping_driver
{
	public function display_settings($data)
	{
		foreach (array('shipping_per_weight_rate', 'shipping_per_weight_unit') as $key)
		{
			$this->EE->lang->language[$key] = sprintf(
				$this->EE->lang->language[$key],
				$this->EE->store_config->item('weight_units')
			);
		}

		if (isset($_GET['shipping_rule_id']))
		{
			return $this->_shipping_rule_edit($data, $this->EE->input->get('shipping_rule_id', TRUE));
		}

		if ( ! empty($_POST))
		{
			$selected_ids = $this->EE->input->post('selected');
			if ( ! is_array($selected_ids))
			{
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			foreach ($selected_ids as $key => $value) { $selected_ids[$key] = (int)$value; }

			switch ($this->EE->input->post('with_selected'))
			{
				case 'enable':
					$this->EE->store_shipping_model->enable_shipping_rules($selected_ids);
					break;
				case 'disable':
					$this->EE->store_shipping_model->disable_shipping_rules($selected_ids);
					break;
				case 'delete':
					$this->EE->store_shipping_model->delete_shipping_rules($selected_ids);
					break;
				default:
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$data['shipping_rules'] = $this->EE->store_shipping_model->get_all_shipping_rules($this->shipping_method_id);
		$data['shipping_add_rule_link'] = BASE.AMP.$data['post_url'].AMP.'shipping_rule_id=new';

		$countries = $this->EE->store_shipping_model->get_countries(FALSE, TRUE);

		foreach ($data['shipping_rules'] as $key => $rule)
		{
			$rule['country_name'] = isset($countries[$rule['country_code']]['name']) ? $countries[$rule['country_code']]['name'] : lang('region_any');
			$rule['region_name'] = isset($countries[$rule['country_code']]['regions'][$rule['region_code']]) ? $countries[$rule['country_code']]['regions'][$rule['region_code']] : lang('region_any');
			$rule['edit_link'] = BASE.AMP.$data['post_url'].AMP.'shipping_rule_id='.$rule['shipping_rule_id'];

			$rule['order_qty_text'] = '';
			if ($rule['min_order_qty'] OR $rule['max_order_qty'])
			{
				$rule['order_qty_text'] = (int)$rule['min_order_qty'];
				$rule['order_qty_text'] .= $rule['max_order_qty'] ? ' - '.$rule['max_order_qty'] : '+';
			}

			$rule['order_total_text'] = '';
			if ($rule['min_order_total_val'] OR $rule['max_order_total_val'])
			{
				$rule['order_total_text'] = store_cp_format_currency($rule['min_order_total_val']);
				$rule['order_total_text'] .= $rule['max_order_total_val'] ? ' - '.$rule['max_order_total'] : '+';
			}

			$rule['weight_text'] = '';
			if ($rule['min_weight'] OR $rule['max_weight'])
			{
				$rule['weight_text'] = (float)$rule['min_weight'];
				$rule['weight_text'] .= $rule['max_weight'] ? ' - '.$rule['max_weight'] : '+';
				$rule['weight_text'] .= ' '.$this->EE->store_config->item('weight_units');
			}

			// describe rate in an easy to read manner
			$rule['rate_text'] = '';
			if ($rule['base_rate_val'])
			{
				$rule['rate_text'] .= $rule['base_rate'].' + ';
			}
			if ($rule['per_item_rate'])
			{
				$rule['rate_text'] .= $rule['per_item_rate'].' '.lang('shipping_per_item').' + ';
			}
			if ($rule['per_weight_rate'])
			{
				$rule['rate_text'] .= $rule['per_weight_rate'].' '.lang('shipping_per_weight_unit').' + ';
			}
			if ($rule['percent_rate'])
			{
				$rule['rate_text'] .= $rule['percent_rate'].'% '.lang('shipping_of_order_total');
			}
			$rule['rate_text'] = trim($rule['rate_text'], ' +');
			if ($rule['min_rate_val'])
			{
				$rule['rate_text'] .= ', '.sprintf(lang('shipping_with_a_min_of'), $rule['min_rate']);
			}
			if ($rule['max_rate_val'])
			{
				$rule['rate_text'] .= ', '.sprintf(lang('shipping_up_to_a_max_of'), $rule['max_rate']);
			}
			$rule['rate_text'] = trim($rule['rate_text'], ' ,');

			$data['shipping_rules'][$key] = $rule;
		}

		return $this->EE->load->view('settings/shipping_default', $data, TRUE);
	}

	private function _shipping_rule_edit($data, $shipping_rule_id)
	{
		Store_mcp::add_breadcrumb(BASE.AMP.$data['post_url'], $this->title);

		if ($shipping_rule_id == 'new')
		{
			Store_mcp::set_title(lang('shipping_add_rule'));
			$data['shipping_rule'] = array(
				'shipping_rule_id' => 0,
				'title' => '',
				'country_code' => '*',
				'region_code' => '*',
				'postcode' => '',
				'min_order_qty' => '',
				'max_order_qty' => '',
				'min_order_total' => '',
				'max_order_total' => '',
				'min_weight' => '',
				'max_weight' => '',
				'base_rate' => '',
				'per_item_rate' => '',
				'per_weight_rate' => '',
				'percent_rate' => '',
				'min_rate' => '',
				'max_rate' => '',
				'priority' => 0,
				'enabled' => 1,
			);
		}
		else
		{
			$data['shipping_rule'] = $this->EE->store_shipping_model->get_shipping_rule($this->shipping_method_id, $shipping_rule_id);
			if (empty($data['shipping_rule']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_shipping_method'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}
			$data['shipping_rule']['percent_rate'] = empty($data['shipping_rule']['percent_rate']) ? '' : $data['shipping_rule']['percent_rate'].'%';
			Store_mcp::set_title(lang('shipping_edit_rule').': '.$data['shipping_rule']['title']);
		}

		if ($shipping_rule = $this->EE->input->post('shipping_rule', TRUE))
		{
			// insert/update shipping rule
			$shipping_rule['shipping_method_id'] = $this->shipping_method_id;

			if ($shipping_rule_id == 'new')
			{
				$this->EE->store_shipping_model->insert_shipping_rule($shipping_rule);
			}
			else
			{
				$this->EE->store_shipping_model->update_shipping_rule($shipping_rule_id, $shipping_rule);
			}

			// redirect
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$data['post_url'] .= AMP.'shipping_rule_id='.$shipping_rule_id;

		$data['country_select'] = array('*' => lang('region_any'));
		$data['region_select'] = array('*' => lang('region_any'));

		$enabled_countries = $this->EE->store_shipping_model->get_countries(TRUE, TRUE);
		$selected_country = $data['shipping_rule']['country_code'];
		foreach ($enabled_countries as $code => $country)
		{
			$data['country_select'][$code] = $country['name'];
		}
		if (isset($enabled_countries[$selected_country]))
		{
			$data['region_select'] = array_merge($data['region_select'], $enabled_countries[$selected_country]['regions']);
		}

		$this->EE->javascript->output('ExpressoStore.countries = '.json_encode($enabled_countries).';');
		$this->EE->javascript->output('$("#mainContent select.store_country_select").data("oldVal", "'.$selected_country.'").change();');
		$this->EE->javascript->compile();

		return $this->EE->load->view('settings/shipping_default_edit', $data, TRUE);
	}

	public function calculate_shipping($order)
	{
		// find the first matching rule for this plugin
		$rules = $this->EE->store_shipping_model->get_all_shipping_rules($this->shipping_method_id, TRUE);
		$found_rule = FALSE;
		foreach ($rules as $rule)
		{
			if ($this->_test_shipping_rule($rule, $order))
			{
				$found_rule = $rule;
				break;
			}
		}

		if ($found_rule === FALSE)
		{
			return array('error:shipping_method' => lang('no_rules_match_cart_error'));
		}

		return array(
			'order_shipping_val' => $this->_calc_shipping_rule($found_rule, $order),
			'shipping_method_rule' => $found_rule['title']
		);
	}

	protected function _test_shipping_rule($rule, $order)
	{
		// geographical filters
		if ($rule['country_code'] AND $rule['country_code'] != $order['shipping_country']) return FALSE;
		if ($rule['region_code'] AND $rule['region_code'] != $order['shipping_region']) return FALSE;

		if ($rule['postcode'] != '' AND !$this->_match_glob($rule['postcode'], $order['shipping_postcode'])) return FALSE;

		// order qty rules are inclusive (min <= x <= max)
		if ($rule['min_order_qty'] AND $rule['min_order_qty'] > $order['order_shipping_qty']) return FALSE;
		if ($rule['max_order_qty'] AND $rule['max_order_qty'] < $order['order_shipping_qty']) return FALSE;

		// order total rules exclude maximum limit (min <= x < max)
		if ($rule['min_order_total_val'] AND $rule['min_order_total_val'] > $order['order_shipping_subtotal_val']) return FALSE;
		if ($rule['max_order_total_val'] AND $rule['max_order_total_val'] <= $order['order_shipping_subtotal_val']) return FALSE;

		// order weight rules exclude maximum limit (min <= x < max)
		if ($rule['min_weight'] AND $rule['min_weight'] > $order['order_shipping_weight']) return FALSE;
		if ($rule['max_weight'] AND $rule['max_weight'] <= $order['order_shipping_weight']) return FALSE;

		// all rules match
		return TRUE;
	}

	protected function _match_glob($pattern, $subject)
	{
		// convert glob pattern to regex
		$regex = '/^'.str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, '/')).'$/i';

		return (bool) preg_match($regex, $subject);
	}

	protected function _calc_shipping_rule($rule, $order)
	{
		// this isn't particularly tidy, but it solves the tax rounding problem
		if ($this->EE->store_config->item('tax_rounding') == 'y')
		{
			$tax_multiplier = 1 + $order['tax_rate'];
			foreach (array('base_rate_val', 'per_item_rate_val', 'per_weight_rate_val', 'min_rate_val') as $field)
			{
				$rule[$field] = store_round_currency($rule[$field] * $tax_multiplier) / $tax_multiplier;
			}
		}

		$result = $rule['base_rate_val'];
		$result += $rule['per_item_rate_val'] * $order['order_shipping_qty'];
		$result += $rule['per_weight_rate_val'] * $order['order_shipping_weight'];
		$result += $rule['percent_rate'] / 100 * $order['order_shipping_subtotal_val'];
		$result = max($result, $rule['min_rate_val']);

		if ($rule['max_rate_val'] > 0)
		{
			$result = min($result, $rule['max_rate_val']);
		}

		return $result;
	}

	public function delete()
	{
		$this->EE->store_shipping_model->delete_instance_shipping_rules($this->shipping_method_id);
	}
}

/* End of file ./plugins/shipping/default/default_shipping_plugin.php */