<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

require_once(PATH_THIRD.'store/config.php');

class Store
{
	protected $_form_params;
	protected static $_form_errors;

	public function __construct()
	{
		$this->EE =& get_instance();

		$this->EE->lang->loadfile('store');
		$this->EE->lang->loadfile('myaccount');
		$this->EE->load->model('store_common_model');
		$this->EE->load->helper(array('store', 'form'));
		$this->EE->load->library(array('store_cart', 'store_config', 'javascript'));

		// sometimes having a submit button named "submit" can cause JS issues
		// we provide "commit" as an alternative button name
		if (isset($_POST['commit'])) $_POST['submit'] = $_POST['commit'];
	}

	/*
	 * TEMPLATE TAGS
	 */

	public function product()
	{
		$this->_tmpl_secure_check(FALSE);

		$entry_id = (int)$this->EE->TMPL->fetch_param('entry_id');
		if ($entry_id == 0)
		{
			return '<p><strong>'.sprintf(lang('invalid_parameter'), 'entry_id').'</strong></p>';
		}

		$this->EE->load->model('store_products_model');
		$product = $this->EE->store_products_model->find_by_id($entry_id);
		if (empty($product)) return;

		$product = $this->EE->store_cart->process_product_tax($product);

		$tag_vars = array($product);
		$tag_vars[0]['modifiers'] = array();
		$tag_vars[0]['cart_updated'] = $this->EE->session->flashdata('store_cart_updated');
		$tag_vars[0]['min_order_qty'] = 1;
		$tag_vars[0]['qty_in_cart'] = $this->EE->store_cart->count_contents($entry_id);

		foreach ($product['stock'] as $sku)
		{
			// update product min order qty
			if ($sku['min_order_qty'] > $tag_vars[0]['min_order_qty'])
			{
				$tag_vars[0]['min_order_qty'] = $sku['min_order_qty'];
			}

			// add sku details to modifiers
			// these variables are only useful if the product has one "variation" modifier
			// if the product has more than one variation group then each SKU doesn't align with
			// only one option, so these variables are not available
			if (count($sku['opt_values']) == 1)
			{
				$opt_id = reset($sku['opt_values']);
				$mod_id = key($sku['opt_values']);
				$option =& $product['modifiers'][$mod_id]['options'][$opt_id];
				$option['sku'] = $sku['sku'];
				$option['track_stock'] = $sku['track_stock'] == 'y';
				$option['stock_level'] = $option['track_stock'] ? $sku['stock_level'] : FALSE;
				$option['min_order_qty'] = $sku['min_order_qty'];
			}
		}

		// build product modifiers array
		foreach ($product['modifiers'] as $mod_data)
		{
			$new_mod = array(
				'modifier_id' => $mod_data['product_mod_id'],
				'modifier_name' => $mod_data['mod_name'],
				'modifier_input_name' => "modifiers_{$mod_data['product_mod_id']}",
				'modifier_type' => $mod_data['mod_type'],
				'modifier_instructions' => $mod_data['mod_instructions'],
				'modifier_options' => array()
			);

			$select_options = array();

			foreach ($mod_data['options'] as $opt_data)
			{
				$new_opt = array(
					'option_id' => $opt_data['product_opt_id'],
					'option_name' => $opt_data['opt_name'],
					'option_first' => FALSE,
					'option_last' => FALSE,
					// these variables only appear if the product has one "var" type modifier
					'option_sku' => isset($opt_data['sku']) ? $opt_data['sku'] : FALSE,
					'option_track_stock' => isset($opt_data['track_stock']) ? $opt_data['track_stock'] : FALSE,
					'option_stock_level' => isset($opt_data['stock_level']) ? $opt_data['stock_level'] : FALSE,
					'option_min_order_qty' => isset($opt_data['min_order_qty']) ? $opt_data['min_order_qty'] : FALSE,
				);

				$new_opt['price_mod'] = $opt_data['opt_price_mod'];
				$new_opt['price_mod_val'] = $opt_data['opt_price_mod_val'];
				$new_opt['price_mod_inc_tax_val'] = store_round_currency($new_opt['price_mod_val'] * (1 + $this->EE->store_cart->tax_rate()), TRUE);
				$new_opt['price_mod_inc_tax'] = store_format_currency($new_opt['price_mod_inc_tax_val'], TRUE);
				$new_opt['price_inc_mod_val'] = store_round_currency($new_opt['price_mod_val'] + $product['price_val']);
				$new_opt['price_inc_mod'] = store_format_currency($new_opt['price_inc_mod_val']);
				$new_opt['price_inc_mod_inc_tax_val'] = store_round_currency($new_opt['price_inc_mod_val'] * (1 + $this->EE->store_cart->tax_rate()));
				$new_opt['price_inc_mod_inc_tax'] = store_format_currency($new_opt['price_inc_mod_inc_tax_val']);

				$new_mod['modifier_options'][] = $new_opt;
				$select_options[$opt_data['product_opt_id']] = $opt_data['opt_name'];
			}

			$modifier_options_count = count($new_mod['modifier_options']);
			if ($modifier_options_count > 0)
			{
				$new_mod['modifier_options'][0]['option_first'] = TRUE;
				$new_mod['modifier_options'][$modifier_options_count-1]['option_last'] = TRUE;
			}
			else
			{
				$new_mod['modifier_options'] = array(array());
			}
			$new_mod['no_modifier_options'] = $modifier_options_count == 0;

			$new_mod['modifier_select'] = form_dropdown($new_mod['modifier_input_name'], $select_options);
			$new_mod['modifier_input'] = form_input($new_mod['modifier_input_name']);

			$tag_vars[0]['modifiers'][] = $new_mod;
		}

		$tag_vars[0]['no_modifiers'] = count($tag_vars[0]['modifiers']) == 0;

		// sku-related fields really only make sense if there is only one sku per product..
		if (count($product['stock']) == 1)
		{
			$tag_vars[0]['sku'] = $product['stock'][0]['sku'];
			$tag_vars[0]['track_stock'] = $product['stock'][0]['track_stock'] == 'y';
			$tag_vars[0]['stock_level'] = $tag_vars[0]['track_stock'] ? $product['stock'][0]['stock_level'] : FALSE;
		}
		else
		{
			$tag_vars[0]['sku'] = FALSE;
			$tag_vars[0]['track_stock'] = FALSE;
			$tag_vars[0]['stock_level'] = FALSE;
		}

		// parse tagdata variables
		$out = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $tag_vars);

		// start our form output
		if ($this->EE->TMPL->fetch_param('disable_form') != 'yes')
		{
			// initialize form hidden fields
			$hidden_fields = array();
			$hidden_fields['return_url'] = $this->EE->uri->uri_string;
			$hidden_fields['entry_id'] = $entry_id;

			if ($this->EE->TMPL->fetch_param('return') !== FALSE)
			{
				$hidden_fields['return_url'] = $this->EE->TMPL->fetch_param('return');
			}
			if ($this->EE->TMPL->fetch_param('empty_cart') == 'yes')
			{
				$hidden_fields['empty_cart'] = 1;
			}

			$out = $this->_form_open('act_add_to_cart', $hidden_fields, array(
				'class' => 'store_product_form'
			)).$out.'</form>';
		}

		// include product stock javascript
		if ($this->EE->TMPL->fetch_param('disable_javascript') != 'yes')
		{
			$out .= '
				<script type="text/javascript">
				window.ExpressoStore = window.ExpressoStore || {};
				ExpressoStore.products = ExpressoStore.products || {};
				ExpressoStore.products['.$entry_id.'] = '.json_encode(array(
					'price' => $product['price_val'],
					'modifiers' => $product['modifiers'],
					'stock' => $product['stock'],
				)).';
				'.$this->_async_store_js().'
				</script>';
		}

		return $out;
	}

	public function product_form()
	{
		// initialize form hidden fields
		$hidden_fields = array();
		$hidden_fields['return_url'] = $this->EE->uri->uri_string;

		if ($this->EE->TMPL->fetch_param('return') !== FALSE)
		{
			$hidden_fields['return_url'] = $this->EE->TMPL->fetch_param('return');
		}
		if ($this->EE->TMPL->fetch_param('empty_cart') == 'yes')
		{
			$hidden_fields['empty_cart'] = 1;
		}

		$out = $this->_form_open('act_add_to_cart', $hidden_fields, array(
			'class' => 'store_product_form'
		)).$this->EE->TMPL->tagdata.'</form>';

		return $out;
	}

	protected function _async_store_js()
	{
		$theme_url = str_ireplace('http://', '//', URL_THIRD_THEMES.'store/store.js');

		return '
			(function() {
				'.$this->EE->store_config->js_format_currency().'
				ExpressoStore.cart = ExpressoStore.cart || {};
				ExpressoStore.cart["tax_rate"] = '.(float)$this->EE->store_cart->tax_rate().';
				if (!ExpressoStore.scriptElement) {
					var script = ExpressoStore.scriptElement = document.createElement("script");
					script.type = "text/javascript"; script.async = true;
					script.src = "'.$theme_url.'";
					(document.getElementsByTagName("head")[0] || document.getElementsByTagName("body")[0]).appendChild(script);
				}
			})();';
	}

	public function act_add_to_cart()
	{
		/*echo $this->EE->security->check_xid($this->EE->input->post('XID'));
		if ( ! $this->EE->security->check_xid($this->EE->input->post('XID')))
		{
			$this->EE->functions->redirect($this->EE->store_common_model->create_url());
		}*/

		// do we need to empty the cart first? (useful on single product stores)
		if ($this->EE->input->post('empty_cart')) $this->EE->store_cart->empty_cart();

		$items = $this->EE->input->post('items', TRUE);
		if (empty($items))
		{
			// single item form
			$items = array($this->EE->security->xss_clean($_POST));
		}

		foreach ($items as $item)
		{
			$entry_id = isset($item['entry_id']) ? (int)$item['entry_id'] : 0;
			if (empty($entry_id)) continue;

			// add product to cart
			$item_qty = isset($item['item_qty']) ? (int)$item['item_qty'] : 1;
			$update_qty = FALSE;
			if (isset($item['update_qty']))
			{
				$item_qty = (int)$item['update_qty'];
				$update_qty = TRUE;
			}

			if (isset($item['modifiers']))
			{
				$mod_values = $item['modifiers'];
			}
			else
			{
				$mod_values = array();
				foreach ($item as $key => $value)
				{
					if (strpos($key, 'modifiers_') === 0)
					{
						$mod_values[substr($key, 10)] = $value;
					}
				}
			}

			// are there any custom input fields?
			$input_values = array();
			foreach ($this->_form_params() as $param => $name)
			{
				if (strpos($param, 'input:') !== 0) continue;
				$param = substr($param, 6);

				// only use param if it was submitted
				if (isset($item[$param]))
				{
					$input_values[$name] = $item[$param];
				}
			}

			$this->EE->store_cart->insert($entry_id, $item_qty, $mod_values, $input_values, $update_qty);
		}

		// AJAX requests return JSON
		if ($this->EE->input->is_ajax_request())
		{
			$this->EE->output->send_ajax_response($this->EE->store_cart->contents());
		}

		// redirect
		$this->EE->session->set_flashdata('store_cart_updated', TRUE);
		$this->EE->functions->redirect($this->_get_return_url());
	}

	public function search()
	{
		$this->EE->load->model('store_products_model');

		$options = array(
			'price_min' => (float)$this->EE->TMPL->fetch_param('search:price:min'),
			'price_max' => (float)$this->EE->TMPL->fetch_param('search:price:max'),
			'on_sale' => $this->EE->TMPL->fetch_param('search:on_sale'),
			'in_stock' => $this->EE->TMPL->fetch_param('search:in_stock'),
		);

		// remove Store-specific params from channel entries tag
		unset($this->EE->TMPL->tagparams['search:price:min']);
		unset($this->EE->TMPL->tagparams['search:price:max']);
		unset($this->EE->TMPL->tagparams['search:on_sale']);
		unset($this->EE->TMPL->tagparams['search:in_stock']);

		$orderby = $this->EE->TMPL->fetch_param('orderby');
		if (in_array($orderby, array('price', 'regular_price', 'total_stock')))
		{
			$options['orderby'] = $orderby;
			$options['sort'] = $this->EE->TMPL->fetch_param('sort');

			// don't pass order & sort params to channel entries tag if we are handling it
			unset($this->EE->TMPL->tagparams['orderby']);
			unset($this->EE->TMPL->tagparams['sort']);

			// entries are already in correct order
			$entries_param = 'fixed_order';
		}
		else
		{
			$entries_param = 'entry_id';
		}

		$entry_ids = $this->EE->store_products_model->find_all_entry_ids($options);

		// only the first parsed tag has access to this variable
		if (empty($entry_ids)) return $this->EE->TMPL->no_results;

		// pass everything else through to channel entries loop
		$this->EE->TMPL->tagparams[$entries_param] = implode('|', $entry_ids);

		if ( ! class_exists('Channel'))
		{
			require_once(APPPATH.'modules/channel/mod.channel.php');
		}

		$channel = new Channel();
		return $channel->entries();
	}

	/**
	 * {exp:store:cart} provides a simple non-interactive method to display the current cart contents,
	 * for use in a sidebar etc
	 */
	public function cart()
	{
		$this->_tmpl_secure_check(FALSE);

		$tagdata = $this->EE->TMPL->tagdata;
		$tag_vars = array($this->EE->store_cart->contents());
		$tag_vars[0]['cart_updated'] = $this->EE->session->flashdata('store_cart_updated');

		// check for empty cart
		if ($this->EE->store_cart->is_empty()) return tmpl_no_results($tagdata, 'no_items');

		return $this->EE->TMPL->parse_variables($tagdata, $tag_vars);
	}

	/**
	 * {exp:store:checkout} is the main tag used for displaying the cart to update totals
	 * and submit orders
	 */
	public function checkout()
	{
		$this->_tmpl_secure_check();

		$this->EE->load->library(array('store_payments', 'store_shipping'));

		$tagdata = $this->EE->TMPL->tagdata;
		$tag_vars = array($this->EE->store_cart->contents());
		$tag_vars[0]['cart_updated'] = $this->EE->session->flashdata('store_cart_updated');

		$country_list = $this->EE->store_shipping_model->get_countries(TRUE, TRUE);
		$region_list = array();
		foreach ($country_list as $key => $value)
		{
			foreach ($value['regions'] as $region_code => $region_name)
			{
				$region_list[$key][$region_code] = $region_name;
			}
			$country_list[$key] = $value['name'];
		}

		$order_fields = $this->EE->store_config->get_order_fields();
		foreach ($order_fields as $field_name => $field)
		{
			// if there are validation errors, we want to redisplay posted form values
			if (isset($_POST[$field_name]))
			{
				$tag_vars[0][$field_name] = $this->EE->input->post($field_name, TRUE);
			}

			$tag_vars[0]['error:'.$field_name] = FALSE;
		}

		foreach (array('promo_code', 'accept_terms', 'register_member', 'shipping_same_as_billing',
			'billing_same_as_shipping', 'username', 'screen_name', 'password', 'password_confirm') as $field_name)
		{
			if (isset($_POST[$field_name]))
			{
				$tag_vars[0][$field_name] = $this->EE->input->post($field_name, TRUE);
			}

			$tag_vars[0]['error:'.$field_name] = FALSE;
		}

		// display any inline form validation errors
		if (is_array(self::$_form_errors))
		{
			foreach (self::$_form_errors as $key => $message)
			{
				$tag_vars[0]["error:$key"] = $this->_wrap_error($message);
			}
		}

		// check for empty cart
		if ($this->EE->store_cart->is_empty()) return tmpl_no_results($tagdata, 'no_items');

		// load available shipping & payment methods
		$tag_vars[0]['shipping_methods'] = array();
		$tag_vars[0]['shipping_method_options'] = '';

		$shipping_methods = $this->EE->store_shipping_model->get_all_shipping_methods(TRUE);
		foreach ($shipping_methods as $row)
		{
			$method = array();
			$method['method_id'] = $row['shipping_method_id'];
			$method['method_title'] = $row['title'];
			$method['method_selected'] = $tag_vars[0]['shipping_method_id'] == $row['shipping_method_id'] ? ' selected="selected" ' : '';

			$method['method_price'] = '';
			$method['method_price_val'] = '';
			$method['method_price_inc_tax'] = '';
			$method['method_price_inc_tax_val'] = '';

			if ($this->EE->store_shipping->load($row['shipping_method_id']) AND ( ! $this->EE->store_shipping->is_remote()))
			{
				$method_price = $this->EE->store_shipping->calculate_shipping($this->EE->store_cart->contents());
				if (is_numeric($method_price))
				{
					$method['method_price_val'] = store_round_currency($method_price);
					$method['method_price'] = store_format_currency($method['method_price_val']);
				}
				elseif (isset($method_price['order_shipping_val']))
				{
					$method['method_price_val'] = store_round_currency($method_price['order_shipping_val']);
					$method['method_price'] = store_format_currency($method['method_price_val']);
				}

				if ($tag_vars[0]['tax_shipping'])
				{
					$method['method_price_inc_tax_val'] = store_round_currency($method['method_price_val'] * (1 + $tag_vars[0]['tax_rate']));
					$method['method_price_inc_tax'] = store_format_currency($method['method_price_inc_tax_val']);
				}
				else
				{
					$method['method_price_inc_tax_val'] = $method['method_price_val'];
					$method['method_price_inc_tax'] = $method['method_price'];
				}
			}

			$tag_vars[0]['shipping_method_options'] .= '<option value="'.$method['method_id'].'"'.$method['method_selected'].'>'.$method['method_title'].'</option>';
			$tag_vars[0]['shipping_methods'][] = $method;
		}

		// load available countries and regions
		$enabled_countries = $this->EE->store_shipping_model->get_countries(TRUE, TRUE);
		$tag_vars[0]['billing_country_options'] = $this->_get_country_options($enabled_countries, $tag_vars[0]['billing_country']);
		$tag_vars[0]['shipping_country_options'] = $this->_get_country_options($enabled_countries, $tag_vars[0]['shipping_country']);
		$tag_vars[0]['billing_region_options'] = $this->_get_region_options($enabled_countries, $tag_vars[0]['billing_country'], $tag_vars[0]['billing_region']);
		$tag_vars[0]['shipping_region_options'] = $this->_get_region_options($enabled_countries, $tag_vars[0]['shipping_country'], $tag_vars[0]['shipping_region']);

		// helper variables for checkboxes
		$tag_vars[0]['shipping_same_as_billing_checked'] = $tag_vars[0]['shipping_same_as_billing'] ? 'checked="checked"' : NULL;
		$tag_vars[0]['billing_same_as_shipping_checked'] = $tag_vars[0]['billing_same_as_shipping'] ? 'checked="checked"' : NULL;
		$tag_vars[0]['accept_terms_checked'] = $tag_vars[0]['accept_terms'] ? 'checked="checked"' : NULL;
		$tag_vars[0]['register_member_checked'] = $tag_vars[0]['register_member'] ? 'checked="checked"' : NULL;

		// form input helpers
		$text_inputs = array_merge(array_keys($order_fields),
			array('promo_code', 'username', 'screen_name', 'password', 'password_confirm'));
		foreach ($text_inputs as $field_name)
		{
			$field_type = 'text';
			if ($field_name == 'order_email') $field_type = 'email';
			if (strpos($field_name, 'password') === 0) $field_type = 'password';
			$tag_vars[0]['field:'.$field_name] = '<input type="'.$field_type.'" '.
				'id="'.$field_name.'" name="'.$field_name.'" value="'.$tag_vars[0][$field_name].'" />';
		}
		// select inputs
		foreach (array('billing_region', 'billing_country', 'shipping_region', 'shipping_country', 'shipping_method') as $field_name)
		{
			$tag_vars[0]['field:'.$field_name] = '<select id="'.$field_name.'" name="'.$field_name.'">'.$tag_vars[0][$field_name.'_options'].'</select>';
		}
		// hidden inputs
		foreach (array('shipping_same_as_billing', 'billing_same_as_shipping', 'accept_terms', 'register_member') as $field_name)
		{
			$tag_vars[0]['field:'.$field_name] = '<input type="hidden" name="'.$field_name.'" value="0" />'.
				'<input type="checkbox" id="'.$field_name.'" name="'.$field_name.'" value="1" '.$tag_vars[0][$field_name.'_checked'].' />';
		}

		$out = '';

		// store regions array as js array
		if ($this->EE->TMPL->fetch_param('disable_javascript') != 'yes')
		{
			$out .= '<script type="text/javascript">
				window.ExpressoStore = window.ExpressoStore || {};
				ExpressoStore.countries = '.json_encode($enabled_countries).';
				'.$this->_async_store_js().'
			</script>';
		}

		$hidden_fields = array(
			'return_url' => $this->EE->uri->uri_string,
		);

		$this->_add_payment_tag_vars($tag_vars);
		if (($payment_method = $this->EE->TMPL->fetch_param('payment_method')) != FALSE)
		{
			$hidden_fields['payment_method'] = $payment_method;
		}

		if ($this->EE->TMPL->fetch_param('register_member') == 'yes')
		{
			$hidden_fields['register_member'] = 1;
		}

		// previous_url variable helpful for a "continue shopping" link
		$tag_vars[0]['previous_url'] = isset($this->EE->session->tracker[1]) ?
			$this->EE->functions->create_url($this->EE->session->tracker[1]) : FALSE;

		foreach (array('next', 'return') as $param)
		{
			if ($this->EE->TMPL->fetch_param($param))
			{
				$hidden_fields[$param.'_url'] = $this->EE->TMPL->fetch_param($param);
			}
		}

		// start our form output
		$out .= $this->_form_open('act_checkout', $hidden_fields, array(
			'data-order-total' => $tag_vars[0]['order_total_val']
		));

		// parse tagdata variables
		$out .= $this->EE->TMPL->parse_variables($tagdata, $tag_vars);

		// end form output and return
		$out .= '</form>';
		return $out;
	}

	public function act_checkout()
	{
		if ( ! $this->EE->security->check_xid($this->EE->input->post('XID')))
		{
			$this->EE->functions->redirect($this->EE->store_common_model->create_url());
		}

		$this->_load_validation_library();
		$this->EE->load->library('store_payments');

		if ($this->EE->input->post('empty_cart'))
		{
			$this->EE->store_cart->empty_cart();
			$return_url = $this->EE->store_common_model->create_url($this->EE->input->post('RET'));
			$this->EE->functions->redirect($return_url);
		}

		// lazy way to make form validation library take into account existing cart values
		$update_data = $this->EE->security->xss_clean($_POST);
		$cart = $this->EE->store_cart->contents();
		unset($cart['items']);
		$_POST = array_merge($cart, $update_data);

		if (empty($update_data['items']) OR ! is_array($update_data['items']))
		{
			$update_data['items'] = array();
		}

		// do we need to remove any items?
		if (isset($update_data['remove_items']) AND is_array($update_data['remove_items']))
		{
			foreach ($update_data['remove_items'] as $key => $value)
			{
				if ( ! empty($value)) $update_data['items'][$key]['item_qty'] = 0;
			}
		}

		// remember whether return_url in cart should be https
		if (isset($update_data['return_url']))
		{
			$update_data['return_url'] = $this->_get_return_url();
		}
		$update_data['cancel_url'] = $this->EE->store_common_model->create_url();

		// convert require="" parameter into rules:field="required"
		$require_fields = explode('|', $this->_form_param('require'));
		foreach ($require_fields as $field_name)
		{
			$this->_form_params['rules:'.$field_name] = $this->_form_param('rules:'.$field_name).'|required';
		}

		// quick fields are for lazy people when templating to save repeating billing/shipping
		$quick_fields = array('name', 'address1', 'address2', 'address3', 'region', 'country', 'postcode', 'phone');
		foreach ($quick_fields as $field_name)
		{
			if ($rules = $this->_form_param('rules:'.$field_name))
			{
				$this->_form_params['rules:billing_'.$field_name] = $rules;
				$this->_form_params['rules:shipping_'.$field_name] = $rules;
				unset($this->_form_params['rules:'.$field_name]);
			}
		}

		$order_fields = $this->EE->store_config->get_order_fields();

		foreach ($this->_form_params() as $param_name => $rules)
		{
			if (strpos($param_name, 'rules:') !== 0) continue;

			$field_name = str_replace('rules:', '', $param_name);

			// don't show shipping error messages if same as billing and vice versa
			if ($_POST['shipping_same_as_billing'] AND strpos($field_name, 'shipping_') === 0 AND strpos($field_name, 'shipping_method') === false) continue;
			if ($_POST['billing_same_as_shipping'] AND strpos($field_name, 'billing_') === 0) continue;

			$this->EE->form_validation->add_rules($field_name, 'lang:'.$field_name, $rules);
		}

		// on final checkout step, order_email and payment_method are required
		if (isset($update_data['submit']))
		{
			$this->EE->form_validation->add_rules('order_email', 'lang:order_email', 'required');
			$this->EE->form_validation->add_rules('payment_method', 'lang:payment_method', 'required|valid_payment_method');
		}
		// accept terms checkbox
		if (isset($update_data['accept_terms']))
		{
			$this->EE->form_validation->add_rules('accept_terms', 'lang:accept_terms', 'require_accept_terms');
			$update_data['accept_terms'] = TRUE; // cast to bool
		}

		// ensure order_email field contains the valid_email rule
		$this->EE->form_validation->add_rules('order_email', 'lang:order_email', 'valid_email');

		// if registering member, ensure email does not already exist
		if ($_POST['register_member'])
		{
			$this->EE->form_validation->add_rules('order_email', 'lang:order_email', 'valid_user_email');
			$this->EE->form_validation->add_rules('username', 'lang:username', 'valid_username');
			$this->EE->form_validation->add_rules('screen_name', 'lang:screen_name', 'valid_screen_name');
			$this->EE->form_validation->add_rules('password', 'lang:password', 'valid_password');
			$this->EE->form_validation->add_rules('password_confirm', 'lang:password', 'matches[password]');
		}

		// trigger unique checks
		$this->EE->form_validation->set_old_value('username', ' ');
		$this->EE->form_validation->set_old_value('email', ' ');
		$this->EE->form_validation->set_old_value('screen_name', ' ');

		// shipping_method is actually stored in cart as shipping_method_id (keep for backwards compatibility)
		if (isset($update_data['shipping_method']))
		{
			$update_data['shipping_method_id'] = $update_data['shipping_method'];
		}

		if ( ! empty($update_data['remove_promo_code']))
		{
			$update_data['promo_code'] = '';
		}

		if (isset($update_data['promo_code']))
		{
			$this->EE->form_validation->add_rules('promo_code', 'lang:promo_code', 'valid_promo_code');
		}

		// validate form
		$valid_form = $this->EE->form_validation->run();

		// if this is the final step, shipping method must also validate
		// NOTE: shipping_method errors are stored in cart
		if (isset($update_data['submit']) AND $cart['error:shipping_method'])
		{
			$valid_form = FALSE;
		}

		if ($valid_form)
		{
			// update cart
			$this->EE->store_cart->update($update_data);

			// where to next?
			// we use isset instead of input->post() because some servers seem to treat
			// unicode chars as false
			if (isset($_POST['submit']))
			{
				if ($this->EE->store_config->item('force_member_login') == 'y' AND
					empty($this->EE->session->userdata['member_id']))
				{
					// admin has set order submission to members only,
					// but customer is not logged in
					$this->EE->output->show_user_error(FALSE, array(lang('submit_order_not_logged_in')));
				}

				// delete XID to prevent duplicate form submissions
				$this->EE->security->delete_xid($this->EE->input->post('XID'));

				// create order
				$order = $this->EE->store_cart->submit();

				if ($order['is_order_paid'])
				{
					// skip payment for free orders
					$this->EE->store_payments->redirect_to_return_url($order);
				}

				// submit to payment gateway (this will either redirect to a third party site,
				// or the order's return or cancel url)
				$payment = $this->EE->input->post('payment');
				$this->EE->store_payments->process($order, $payment);
			}
			elseif (isset($_POST['next']) AND isset($_POST['next_url']))
			{
				$this->EE->functions->redirect($this->_get_return_url('next_url'));
			}

			// AJAX requests return JSON
			if ($this->EE->input->is_ajax_request())
			{
				$this->EE->output->send_ajax_response($this->EE->store_cart->contents());
			}

			// default is to update totals and return
			$return_url = $this->EE->store_common_model->create_url($this->EE->input->post('RET'));
			$this->EE->functions->redirect($return_url);
		}

		self::$_form_errors = $this->EE->form_validation->error_array();

		if ($cart['error:shipping_method'])
		{
			self::$_form_errors['shipping_method'] = $cart['error:shipping_method'];
		}

		if ($this->EE->input->is_ajax_request())
		{
			$response_data = array_merge($cart, $update_data, self::$_form_errors);
			$this->EE->output->send_ajax_response($response_data);
		}

		if ($this->_form_param('error_handling') != 'inline')
		{
			$this->EE->output->show_user_error(FALSE, self::$_form_errors);
		}

		return $this->EE->core->generate_page();
	}

	/**
	 * Standard form opening tag
	 */
	protected function _form_open($action, $hidden_fields = array(), $extra_html = array())
	{
		$defaults = array(
			'method' => 'post',
			'id' => $this->EE->TMPL->fetch_param('form_id'),
			'name' => $this->EE->TMPL->fetch_param('form_name'),
			'class' => '',
			'enctype' => '',
		);

		$data = array_merge($defaults, $extra_html);

		// class gets appended
		$data['class'] = trim($data['class'].' '.$this->EE->TMPL->fetch_param('form_class'));

		$hidden_fields['ACT'] = $this->EE->functions->fetch_action_id(__CLASS__, $action);
		$hidden_fields['RET'] = $this->EE->uri->uri_string;
		$hidden_fields['site_id'] = $this->EE->config->item('site_id');

		// prevents errors in case there are no tag params
		$this->EE->TMPL->tagparams['encrypted_params'] = 1;
		$hidden_fields['_params'] = $this->_encrypt_input(json_encode($this->EE->TMPL->tagparams));

		if ($this->EE->config->item('secure_forms') == 'y')
		{
			$hidden_fields['XID'] = '{XID_HASH}';
		}

		if ($this->EE->TMPL->fetch_param('secure_return') == 'yes')
		{
			$hidden_fields['secure_return'] = 1;
		}

		// Add the CSRF Protection Hash
		if ($this->EE->config->item('csrf_protection') == TRUE)
		{
			$hidden_fields[$this->EE->security->get_csrf_token_name()] = $this->EE->security->get_csrf_hash();
		}

		if ($data['enctype'] == 'multi' OR strtolower($data['enctype']) == 'multipart/form-data')
		{
			$data['enctype'] = 'multipart/form-data';
		}

		$out = '<form ';

		foreach ($data as $key => $value)
		{
			if ($value !== '')
			{
				$out .= htmlspecialchars($key).'="'.htmlspecialchars($value).'" ';
			}
		}

		$out .= ">\n<div style=\"margin:0;padding:0;display:inline;\">\n";

		foreach ($hidden_fields as $key => $value)
		{
			$out .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />'."\n";
		}

		$out .= "</div>\n\n";
		return $out;
	}

	protected function _form_param($key)
	{
		$this->_form_params();
		return isset($this->_form_params[$key]) ? $this->_form_params[$key] : FALSE;
	}

	protected function _form_params()
	{
		if (NULL === $this->_form_params)
		{
			$this->_form_params = json_decode($this->_decrypt_input($this->EE->input->post('_params')), TRUE);

			if (empty($this->_form_params))
			{
				return $this->EE->output->show_user_error('general', array(lang('not_authorized')));
			}
		}

		return $this->_form_params;
	}

	/**
	 * Generates a list of HTML <option> tags for the list of countries
	 */
	private function _get_country_options($enabled_countries, $selected_country)
	{
		$options = array();

		foreach ($enabled_countries as $country_code => $country)
		{
			if ($country_code == $selected_country)
			{
				$options[] = '<option value="'.$country_code.'" selected="selected">'.$country['name'].'</option>';
			}
			else
			{
				$options[] = '<option value="'.$country_code.'">'.$country['name'].'</option>';
			}
		}

		if (empty($options))
		{
			return '<option></option>';
		}
		else
		{
			return implode("\n", $options);
		}
	}

	/**
	 * Generates a list of HTML <option> tags for the list of regions
	 */
	private function _get_region_options($enabled_countries, $selected_country, $selected_region)
	{
		$options = array();

		// find and display the appropriate list of regions
		if (isset($enabled_countries[$selected_country]))
		{
			foreach ($enabled_countries[$selected_country]['regions'] as $region_code => $region_name)
			{
				if ($region_code == $selected_region)
				{
					$options[] = '<option value="'.$region_code.'" selected="selected">'.$region_name.'</option>';
				}
				else
				{
					$options[] = '<option value="'.$region_code.'">'.$region_name.'</option>';
				}
			}
		}

		if (empty($options))
		{
			return '<option></option>';
		}
		else
		{
			return implode("\n", $options);
		}
	}

	/**
	 * Load the form validation library and configure it
	 * with error delimiters specified in the template.
	 */
	protected function _load_validation_library()
	{
		$this->EE->load->library('store_form_validation');

		$error_delimiters = explode('|', $this->_form_param('error_delimiters'));
		if (count($error_delimiters) == 2)
		{
			$this->EE->form_validation->set_error_delimiters($error_delimiters[0], $error_delimiters[1]);
		}
	}

	/**
	 * Wrap an error message with delimiters specified in the template
	 */
	protected function _wrap_error($message)
	{
		if (empty($message)) return FALSE;

		if (isset($this->EE->TMPL))
		{
			$error_delimiters = explode('|', $this->EE->TMPL->fetch_param('error_delimiters'));
		}
		else
		{
			$error_delimiters = explode('|', $this->_form_param('error_delimiters'));
		}

		if (count($error_delimiters) == 2)
		{
			return $error_delimiters[0].$message.$error_delimiters[1];
		}

		return $message;
	}

	private function _tmpl_secure_check($use_global = TRUE)
	{
		if ($this->EE->TMPL->fetch_param('secure') == 'yes' OR
			($use_global AND $this->EE->store_config->item('secure_template_tags') == 'y'))
		{
			if ($this->EE->store_common_model->secure_request())
			{
				// connection is secure - good. make sure form submissions are secure too
				$this->EE->TMPL->tagparams['secure_action'] = 'yes';
				$this->EE->TMPL->tagparams['secure_return'] = 'yes';
			}
			else
			{
				$this->EE->functions->redirect(str_replace('http://', 'https://', $this->EE->functions->fetch_current_uri()));
			}
		}
	}

	protected function _get_return_url($name = 'return_url')
	{
		$url = $this->EE->functions->create_url($this->EE->input->post($name));
		if ($this->EE->input->post('secure_return'))
		{
			$url = str_replace('http://', 'https://', $url);
		}
		return $url;
	}

	protected function _add_payment_tag_vars(&$tag_vars)
	{
		// these fields are deprecated and will be removed in a future version
		$tag_vars[0]['payment_status'] = FALSE;
		$tag_vars[0]['payment_message'] = FALSE;
		$tag_vars[0]['payment_txn_id'] = FALSE;

		// ensure we have an error:payment_method
		if ( ! isset($tag_vars[0]['error:payment_method']))
		{
			$tag_vars[0]['error:payment_method'] = FALSE;
		}

		if (($payment_error = $this->EE->session->flashdata('store_payment_error')) !== FALSE)
		{
			$tag_vars[0]['error:payment_method'] = $this->_wrap_error($payment_error);

			// support deprecated payment_status variable
			$tag_vars[0]['payment_status'] = 'failed';
			$tag_vars[0]['payment_message'] = $payment_error;
		}

		// payment_method_options variable for lazy people
		$tag_vars[0]['payment_method_options'] = '';
		if (strpos($this->EE->TMPL->tagdata, '{payment_method_options}') !== FALSE)
		{
			if (($payment_method = $this->EE->TMPL->fetch_param('payment_method')) == FALSE)
			{
				$payment_method = $tag_vars[0]['payment_method'];
			}

			$tag_vars[0]['payment_method_options'] = $this->EE->store_payments_model->enabled_payment_methods_select($payment_method);
		}

		$tag_vars[0]['field:payment_method'] = '<select id="payment_method" name="payment_method">'.$tag_vars[0]['payment_method_options'].'</select>';

		// ideal payment issuer options
		if (strpos($this->EE->TMPL->tagdata, '{ideal_issuer_options}') !== FALSE)
		{
			$tag_vars[0]['ideal_issuer_options'] = $this->EE->store_payments->ideal_issuer_options();
		}
		if (strpos($this->EE->TMPL->tagdata, '{mollie_issuer_options}') !== FALSE)
		{
			$tag_vars[0]['mollie_issuer_options'] = $this->EE->store_payments->mollie_issuer_options();
		}

		$tag_vars[0]['exp_month_options'] = $this->EE->store_payments->get_exp_month_options();
		$tag_vars[0]['exp_year_options'] = $this->EE->store_payments->get_exp_year_options();
	}

	/*
	 * Parameters - order_id, order_hash, member_id, orderby, sort, limit
	 * order_id overrides anything else order_id pipe seperated but only 0-1 order_hash
	 * member_id can be pipe seperated as well
	 *
	 * start_on? stop_before? status
	 * orderby and sort pipe seperated?
	 *
	 */
	public function orders()
	{
		$this->_tmpl_secure_check();

		$this->EE->load->model('store_orders_model');

		$query = array(
			'order_id' => $this->EE->TMPL->fetch_param('order_id'),
			'order_hash' => $this->EE->TMPL->fetch_param('order_hash'),
			'member_id' => $this->EE->TMPL->fetch_param('member_id'),
			'order_status' => $this->EE->TMPL->fetch_param('order_status'),
			'orderby' => $this->EE->TMPL->fetch_param('orderby'),
			'sort' => $this->EE->TMPL->fetch_param('sort'),
			'limit' => (int)$this->EE->TMPL->fetch_param('limit'),
			'offset' => (int)$this->EE->TMPL->fetch_param('offset'),
		);

		if (($paid = $this->EE->TMPL->fetch_param('paid')) != '')
		{
			$query['is_order_paid'] = $paid == 'yes';
		}

		$tag_vars = $this->EE->store_orders_model->get_orders_tag($query);

		if (empty($tag_vars)) return tmpl_no_results($this->EE->TMPL->tagdata, 'no_orders');

		$out = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $tag_vars);

		return $out.$this->_track_conversion($tag_vars);
	}

	public function payment()
	{
		$this->_tmpl_secure_check();

		$this->EE->load->model(array('store_orders_model', 'store_shipping_model'));
		$this->EE->load->library('store_payments');

		$query = array(
			'order_id' => $this->EE->TMPL->fetch_param('order_id'),
			'order_hash' => $this->EE->TMPL->fetch_param('order_hash'),
			'member_id' => $this->EE->TMPL->fetch_param('member_id'),
			'limit' => 1,
		);

		// either order_id or order_hash must be specified
		if ($query['order_id'] === FALSE)
		{
			$query['order_hash'] = (string)$query['order_hash'];
		}
		else
		{
			$query['order_id'] = (int)$query['order_id'];
		}

		$tag_vars = $this->EE->store_orders_model->get_orders_tag($query);

		if (empty($tag_vars))
		{
			return tmpl_no_results($this->EE->TMPL->tagdata, 'no_orders');
		}

		// display any inline form validation errors
		if (is_array(self::$_form_errors))
		{
			foreach (self::$_form_errors as $key => $message)
			{
				$tag_vars[0]["error:$key"] = $this->_wrap_error($message);
			}
		}

		$hidden_fields = array(
			'order_hash' => $tag_vars[0]['order_hash'],
			'return_url' => $this->EE->uri->uri_string,
		);

		$this->_add_payment_tag_vars($tag_vars);
		if (($payment_method = $this->EE->TMPL->fetch_param('payment_method')) != FALSE)
		{
			$hidden_fields['payment_method'] = $payment_method;
		}

		// start our form output
		$out = $this->_form_open('act_payment', $hidden_fields, array(
			'data-order-total' => $tag_vars[0]['order_total_val']
		));

		// parse tagdata variables
		$out .= $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $tag_vars);

		// end form output and return
		$out .= '</form>';
		return $out.$this->_track_conversion($tag_vars);
	}

	public function act_payment()
	{
		if ( ! $this->EE->security->secure_forms_check($this->EE->input->post('XID')))
		{
			$this->EE->functions->redirect($this->EE->store_common_model->create_url());
		}

		$this->_load_validation_library();
		$this->EE->load->model(array('store_orders_model', 'store_shipping_model'));
		$this->EE->load->library('store_payments');

		$query = array(
			'order_hash' => (string)$this->EE->input->post('order_hash'),
			'limit' => 1,
		);

		$matching_orders = $this->EE->store_orders_model->get_orders_tag($query);
		if (empty($matching_orders))
		{
			return $this->EE->output->show_user_error('general', array(lang('not_authorized')));
		}
		$order = $matching_orders[0];

		$order['payment_method'] = $this->EE->input->post('payment_method', TRUE);
		$order['return_url'] = $this->_get_return_url();
		$order['cancel_url'] = $this->EE->store_common_model->create_url();
		$this->EE->store_payments->return_if_already_paid($order);

		$this->EE->load->library('store_form_validation');
		$this->EE->form_validation->add_rules('payment_method', 'lang:payment_method', 'required|valid_payment_method');

		if ($this->EE->form_validation->run())
		{
			// save new payment method and return url
			$this->EE->store_orders_model->update_order($order['order_id'], array(
				'payment_method' => $order['payment_method'],
				'return_url' => $order['return_url'],
				'cancel_url' => $order['cancel_url'],
			));

			// process payment info
			$payment = $this->EE->input->post('payment');
			$this->EE->store_payments->process($order, $payment);
		}

		self::$_form_errors = $this->EE->form_validation->error_array();

		if ($this->_form_param('error_handling') != 'inline')
		{
			$this->EE->output->show_user_error(FALSE, self::$_form_errors);
		}

		return $this->EE->core->generate_page();
	}

	/**
	 * Insert Google Analytics tracking data if order has recently been completed
	 * We use cookies to ensure this only happens once per order
	 */
	protected function _track_conversion($tag_vars)
	{
		$order_hash = $this->EE->input->cookie('cmcartsubmit');
		if (empty($order_hash)) return;

		// does the current page actually contain the submitted order?
		$order = FALSE;
		foreach ($tag_vars as $tag_var)
		{
			if ($tag_var['order_hash'] == $order_hash)
			{
				// user has just completed the order, this must be an "order completed" page
				$order = $tag_var;
			}
		}

		if (empty($order)) return;

		$out = '';

		if ($this->EE->store_config->item('google_analytics_ecommerce') == 'y')
		{
			$ga = array();
			$ga[] = array(
				'_addTrans',
				$order['order_id'],
				$this->EE->config->item('site_name'),
				sprintf("%0.2f", $order['order_total_val']),
				sprintf("%0.2f", $order['order_tax_val']),
				sprintf("%0.2f", $order['order_shipping_val']),
				$order['billing_address3'],
				$order['billing_region_name'],
				$order['billing_country_name']);

			// GA will only allow one entry per sku
			// we need to aggregate any items in cart which have the same sku
			$items = array();
			foreach ($order['items'] as $item)
			{
				$sku = $item['sku'];
				if (isset($items[$sku]))
				{
					// sku exists, just increase quantity
					$items[$sku]['item_qty'] += $item['item_qty'];
				}
				else
				{
					$items[$sku] = $item;
				}
			}

			foreach ($items as $item)
			{
				$ga[] = array(
					'_addItem',
					$order['order_id'],
					$item['sku'],
					$item['title'],
					'',
					sprintf("%0.2f", $item['price_val']),
					$item['item_qty'],
				);
			}

			$ga[] = array('_trackTrans');

			$out = "\n<script type='text/javascript'>\nvar _gaq = _gaq || [];";

			foreach ($ga as $command)
			{
				$command = json_encode($command);
				$out .= "\n_gaq.push($command);";
			}

			$out .= "\n</script>";
		}

		if ($conversion_tracking_extra = $this->EE->store_config->item('conversion_tracking_extra'))
		{
			$out .= $this->EE->TMPL->parse_variables($conversion_tracking_extra, array($order));
		}

		// conversion tracking should only ever happen once per order, unset cookie
		$this->EE->functions->set_cookie('cmcartsubmit');

		return $out;
	}

	public function download()
	{
		$this->EE->load->model('store_orders_model');
		$url = $this->EE->TMPL->fetch_param('url');
		if (empty($url)) return;

		$order_id = (int)$this->EE->TMPL->fetch_param('order_id');
		$expire = (int)$this->EE->TMPL->fetch_param('expire');

		$order = $this->EE->store_orders_model->find_by_id($order_id);
		if (empty($order) OR $order['is_order_unpaid']) return;

		// make sure download hasn't expired
		if ($this->_is_download_expired($order, $expire)) return;

		$file_id = (int)$this->EE->store_common_model->get_file_id(dirname($url), basename($url));

		if ($file_id <= 0) return '<span style="font-weight: bold; color: red;">'.lang('download_not_found').'</span>';

		$params = array('o' => $order_id, 'f' => $file_id);
		if ($expire > 0) $params['e'] = $expire;

		// generate file download key to verify parameters (prevents people guessing download URLs)
		$params['k'] = $this->_generate_download_key($order, $file_id, $expire);

		$out = '<a href="'.$this->EE->store_common_model->get_action_url('act_download_file').AMP.
			htmlspecialchars(http_build_query($params)).'"';

		foreach (array('id', 'class', 'style') as $param)
		{
			if (($param_val = $this->EE->TMPL->fetch_param($param)) !== FALSE)
			{
				$out .= ' '.$param.'="'.htmlspecialchars($param_val).'"';
			}
		}
		$out .= '>'.$this->EE->TMPL->tagdata.'</a>';
		return $out;
	}

	public function act_download_file()
	{
		$this->EE->load->model('store_orders_model');

		$order_id = (int)$this->EE->input->get('o');
		$file_id = (int)$this->EE->input->get('f');
		$expire = (int)$this->EE->input->get('e');
		$key = $this->EE->input->get('k');

		$order = $this->EE->store_orders_model->find_by_id($order_id);
		if (empty($order) OR $order['is_order_unpaid'])
		{
			return $this->_download_error('Order is not paid!');
		}

		// make sure download key matches
		if ($key !== $this->_generate_download_key($order, $file_id, $expire))
		{
			return $this->_download_error('Incorrect download key!');
		}

		// make sure download link hasn't expired
		if ($this->_is_download_expired($order, $expire))
		{
			exit(lang('download_link_expired'));
		}

		$file_path = $this->EE->store_common_model->get_file_path($file_id);
		if (empty($file_path))
		{
			return $this->_download_error("Can't find file with ID: $file_id");
		}

		if (($real_path = realpath($file_path)) === FALSE)
		{
			return $this->_download_error("Can't find file: $file_path");
		}

		$path_parts = pathinfo($real_path);
		$extension = $path_parts['extension'];
		$filename = $path_parts['basename'];

		// Load the mime types
		@include(APPPATH.'config/mimes.php');

		// Set a default mime if we can't find it
		$mime = isset($mimes[$extension]) ? $mimes[$extension] : 'application/octet-stream';
		if (is_array($mime)) $mime = $mime[0];

		// dump the file data
		header('Content-Type: "'.$mime.'"');
		header('Content-Disposition: attachment; filename="'.$filename.'"');

		/* Hidden config option: store_download_output_method
		 * If set to 'xsendfile', downloads will be sent with the X-Sendfile header.
		 * This gives better performance, but has not been thoroughly tested yet.
		 */
		if ($this->EE->config->item('store_download_output_method') === 'xsendfile')
		{
			header('X-Sendfile: '.$real_path);
			exit;
		}

		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Content-Length: '.filesize($real_path));

		if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE)
		{
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
		}
		else
		{
			header('Pragma: no-cache');
		}

		readfile($real_path);
		exit;
	}

	/**
	 * Generate secure download key
	 *
	 * Secure enough that someone would need to know your EE license number before
	 * they would be able to brute force a valid download link
	 */
	private function _generate_download_key($order, $file_id, $expire)
	{
		return sha1($order['order_id'].$order['order_hash'].$file_id.$expire.$this->EE->config->item('license_number'));
	}

	private function _is_download_expired($order, $expire)
	{
		// return FALSE if there is no expiry date
		if ($expire <= 0) return FALSE;

		// return TRUE if the expiry date is in the past
		$expire_date = $order['order_paid_date'] + ($expire * 60);
		return $expire_date <= $this->EE->localize->now;
	}

	/**
	 * Specific download error messages are only displayed to super admins
	 */
	private function _download_error($message)
	{
		if ($this->EE->session->userdata('group_id') == 1)
		{
			show_error($message);
		}

		show_404();
	}

	public function act_field_stock()
	{
		$this->EE->load->model('store_products_model');

		// get post data for our store product details field
		$post_data = $this->EE->input->post('store_product_field', TRUE);
		if (empty($post_data)) exit('Store');

		$output = $this->EE->store_products_model->generate_stock_matrix_html($post_data, 'store_product_field');
		exit($output);
	}

	public function act_payment_return()
	{
		$this->EE->load->model(array('store_orders_model', 'store_payments_model'));
		$this->EE->load->library('store_payments');

		// some gateways post the ACT and payment hash back to us
		$payment_hash = (string)$this->EE->input->get_post('H');

		// find payment
		$payment = $this->EE->store_orders_model->get_payment_by_hash($payment_hash);
		if (empty($payment)) show_error(lang('error_processing_order'));

		// find order
		$order = $this->EE->store_orders_model->find_by_id($payment['order_id']);
		if (empty($order)) show_error(lang('error_processing_order'));

		// process payment
		$this->EE->store_payments->process_return($order, $payment);
	}

	/**
	 * Encrypt input (used for form params)
	 */
	protected function _encrypt_input($input)
	{
		$this->EE->load->library('encrypt');
		return $this->EE->encrypt->encode($input, $this->_encryption_key());
	}

	/**
	 * Decrypt input (used for form params)
	 */
	protected function _decrypt_input($input)
	{
		$this->EE->load->library('encrypt');
		return $this->EE->encrypt->decode($input, $this->_encryption_key());
	}

	protected function _encryption_key()
	{
		$key = (string)$this->EE->config->item('encryption_key');
		if ('' === $key)
		{
			// use license_number to encrypt params instead, not really ideal
			// in future make setting encryption_key an install requirement
			$key = md5($this->EE->config->item('license_number'));
		}

		return $key;
	}
}
/* End of file mod.store.php */