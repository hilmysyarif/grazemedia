<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_cart
{
	const COOKIE_NAME = 'cmcartid';

	public $EE;
	public $cart_id;

	/**
	 * The current cart contents. If you need to access the cart contents from outside this
	 * class, please use the contents() method.
	 *
	 * @var array
	 */
	protected $cart_contents = array();

	protected $_update_data;

	protected $_total_fields = array('order_qty', 'order_shipping_qty', 'order_length', 'order_width',
		'order_height', 'order_weight', 'order_shipping_length', 'order_shipping_width',
		'order_shipping_height', 'order_shipping_weight');

	protected $_total_price_fields = array('order_subtotal', 'order_subtotal_tax', 'order_subtotal_inc_tax',
		'order_handling', 'order_handling_tax', 'order_handling_inc_tax',
		'order_shipping_ex_handling', 'order_shipping_ex_handling_tax', 'order_shipping_ex_handling_inc_tax',
		'order_shipping', 'order_shipping_tax', 'order_shipping_inc_tax',
		'order_subtotal_inc_shipping', 'order_subtotal_inc_shipping_tax', 'order_subtotal_inc_shipping_inc_tax',
		'order_discount', 'order_discount_tax', 'order_discount_inc_tax',
		'order_subtotal_inc_discount', 'order_subtotal_inc_discount_tax', 'order_subtotal_inc_discount_inc_tax',
		'order_shipping_subtotal', 'order_total_ex_tax', 'order_tax', 'order_owing', 'order_total');

	public function __construct()
	{
		$this->EE =& get_instance();

		$this->EE->load->model(array('store_common_model', 'store_products_model', 'store_shipping_model'));
		$this->EE->load->library(array('store_shipping', 'store_payments'));

		$this->cart_id = $this->EE->input->cookie(self::COOKIE_NAME);
		if ($this->cart_id) $this->_load_cart();

		if ($this->is_empty()) $this->update();
	}

	/**
	 * Get the contents of the current cart.
	 *
	 * @return array An array representing the current shopping cart
	 */
	public function contents()
	{
		return $this->cart_contents;
	}

	/**
	 * Adds item to the current cart
	 */
	public function insert($entry_id, $item_qty, $mod_values, $input_values, $update_qty = FALSE)
	{
		if (empty($this->cart_contents['items'])) $this->cart_contents['items'] = array();

		// check item doesn't already exist in cart
		if (empty($mod_values) OR ! is_array($mod_values)) $mod_values = array();
		if (empty($input_values) OR ! is_array($input_values)) $input_values = array();

		$existing_key = $this->find($entry_id, $mod_values, $input_values);

		if ($existing_key === FALSE)
		{
			// add to cart
			$item = array(
				'key' => $this->_next_key(),
				'entry_id' => $entry_id,
				'item_qty' => $item_qty,
				'mod_values' => $mod_values,
				'input_values' => $input_values
			);

			$this->cart_contents['items'][$item['key']] = $item;
		}
		elseif ($update_qty)
		{
			// overwrite item quantity
			$this->cart_contents['items'][$existing_key]['item_qty'] = $item_qty;
		}
		else
		{
			// otherwise just increment the quantity
			$this->cart_contents['items'][$existing_key]['item_qty'] += $item_qty;
		}

		$this->update();
	}

	/**
	 * Find the key of a specified product in the array, if it exists
	 */
	public function find($entry_id, $mod_values, $input_values)
	{
		foreach ($this->cart_contents['items'] as $item_key => $item)
		{
			if ($item['entry_id'] == $entry_id AND
				$item['mod_values'] == $mod_values AND
				$item['input_values'] == $input_values)
			{
				return $item_key;
			}
		}

		return FALSE;
	}

	/**
	 * Count the number of items of a specific entry is in the cart
	 */
	public function count_contents($entry_id)
	{
		$count = 0;
		foreach ($this->cart_contents['items'] as $item)
		{
			if ($item['entry_id'] == $entry_id)
			{
				$count += $item['item_qty'];
			}
		}

		return $count;
	}

	/**
	 * Find the next available item key for the current cart
	 */
	protected function _next_key()
	{
		return $this->is_empty() ? 0 : max(array_keys($this->cart_contents['items'])) + 1;
	}

	/**
	 * Update order details, revalidate cart items and update totals
	 */
	public function update($update_data = NULL)
	{
		$this->cart_contents['cart_id'] = $this->cart_id;
		$this->cart_contents['ip_address'] = $this->EE->input->ip_address();
		$this->cart_contents['member_id'] = $this->EE->session->userdata['member_id'];
		$this->_update_data = $update_data;

 		/* -------------------------------------------
		/* 'store_cart_update_start' hook.
		/*  - Modify cart before it is updated
		/*  - Added: 1.6.2
		*/
			if ($this->EE->extensions->active_hook('store_cart_update_start') === TRUE)
			{
				$this->cart_contents = $this->EE->extensions->call('store_cart_update_start', $this->cart_contents, $update_data);
			}
		/*
		/* -------------------------------------------*/

		// pre-populate the order details based mapped member fields
		if ($this->cart_contents['member_id'] AND empty($this->cart_contents['member_data_loaded']))
		{
			$this->_load_member_data();
		}

		$this->_update_cart_details();
		$this->_update_tax_rate();
		$this->_update_promo_code();

		$this->_reset_totals();

		$this->cart_contents['weight_units'] = $this->EE->store_config->item('weight_units');
		$this->cart_contents['dimension_units'] = $this->EE->store_config->item('dimension_units');

		if (empty($this->cart_contents['items']))
		{
			$this->cart_contents['items'] = array();
		}

		foreach ($this->cart_contents['items'] as $item_key => $item)
		{
			$item['key'] = $item_key;
			$item = $this->_update_item($item);

			if ($item)
			{
				// save item back to cart
				$this->cart_contents['items'][$item_key] = $item;

				// update order totals
				$this->cart_contents['order_qty'] += $item['item_qty'];
				$this->cart_contents['order_subtotal_val'] += $item['item_subtotal_val'];
				$this->cart_contents['order_subtotal_tax_val'] += $item['item_tax_val'];
				$this->cart_contents['order_subtotal_inc_tax_val'] += $item['item_total_val'];
				$this->cart_contents['order_handling_val'] += $item['handling_val'] * $item['item_qty'];
				$this->cart_contents['order_weight'] += $item['weight'] * $item['item_qty'];

				// update order dimensions
				$dimensions = array((float)$item['length'], (float)$item['width'], (float)$item['height']);
				sort($dimensions);
				$this->cart_contents['order_length'] = max($this->cart_contents['order_length'], $dimensions[2]);
				$this->cart_contents['order_width'] = max($this->cart_contents['order_width'], $dimensions[1]);
				$this->cart_contents['order_height'] += $dimensions[0] * $item['item_qty'];

				if ( ! $item['free_shipping'])
				{
					$this->cart_contents['order_shipping_qty'] += $item['item_qty'];
					$this->cart_contents['order_shipping_subtotal_val'] += $item['item_subtotal_val'];
					$this->cart_contents['order_shipping_weight'] += $item['weight'] * $item['item_qty'];
					$this->cart_contents['order_shipping_length'] = max($this->cart_contents['order_shipping_length'], $dimensions[2]);
					$this->cart_contents['order_shipping_width'] = max($this->cart_contents['order_shipping_width'], $dimensions[1]);
					$this->cart_contents['order_shipping_height'] += $dimensions[0] * $item['item_qty'];
				}
			}
			else
			{
				unset($this->cart_contents['items'][$item_key]);
			}
		}

		// add discount if applicable
		if ($this->cart_contents['promo_code_value'])
		{
			// rounding is handled by the update_totals method
			if ($this->cart_contents['promo_code_type'] == 'p')
			{
				$this->cart_contents['order_discount_val'] = store_round_currency($this->cart_contents['order_subtotal_val'] * ($this->cart_contents['promo_code_value'] / 100));
			}
			else
			{
				$this->cart_contents['order_discount_val'] = store_round_currency($this->cart_contents['promo_code_value']);
			}
		}

		// validate shipping & payment methods
		$this->_normalize_units();
		$this->_update_shipping();
		$this->_update_payment_method();

		$this->_update_totals();

 		/* -------------------------------------------
		/* 'store_cart_update_end' hook.
		/*  - Modify cart array
		/*  - Added: 1.2.0
		*/
			if ($this->EE->extensions->active_hook('store_cart_update_end') === TRUE)
			{
				$this->cart_contents = $this->EE->extensions->call('store_cart_update_end', $this->cart_contents);
			}
		/*
		/* -------------------------------------------*/

		// format order totals
		foreach ($this->_total_price_fields as $field_name)
		{
			$this->cart_contents[$field_name] = store_format_currency($this->cart_contents[$field_name.'_val']);
		}

		// re-index items array and update keys (EE template engine requires this)
		$this->cart_contents['items'] = array_values($this->cart_contents['items']);
		foreach ($this->cart_contents['items'] as $item_key => $item)
		{
			$this->cart_contents['items'][$item_key]['key'] = $item_key;
		}

		$this->_update_data = NULL;
		$this->_save_cart();
	}

	protected function _load_member_data()
	{
		$member_data = $this->EE->store_common_model->load_member_data($this->EE->session->userdata['member_id']);
		$order_fields = $this->EE->store_config->get_order_fields();

		foreach ($order_fields as $field_name => $field)
		{
			if (empty($this->cart_contents[$field_name]) AND ! empty($field['member_field']) AND isset($member_data[$field['member_field']]))
			{
				$this->cart_contents[$field_name] = $member_data[$field['member_field']];
			}
		}

		if (empty($this->cart_contents['order_email']))
		{
		    $this->cart_contents['order_email'] = $this->EE->session->userdata['email'];
	    }

		$this->cart_contents['member_data_loaded'] = TRUE;
	}

	protected function _update_cart_details()
	{
		// define the default order address based on config
		if ( ! isset($this->cart_contents['shipping_same_as_billing']) AND $this->EE->store_config->item('default_order_address') == 'shipping_same_as_billing')
		{
			$this->cart_contents['shipping_same_as_billing'] = TRUE;
		}
		elseif ( ! isset($this->cart_contents['billing_same_as_shipping']) AND $this->EE->store_config->item('default_order_address') == 'billing_same_as_shipping')
		{
			$this->cart_contents['billing_same_as_shipping'] = TRUE;
		}

		// set default country/region for new carts
		if ( ! isset($this->cart_contents['billing_country']))
		{
			$this->cart_contents['billing_country'] = $this->EE->store_config->item('default_country');
			$this->cart_contents['shipping_country'] = $this->EE->store_config->item('default_country');
			$this->cart_contents['billing_region'] = $this->EE->store_config->item('default_region');
			$this->cart_contents['shipping_region'] = $this->EE->store_config->item('default_region');
		}

		if ( ! isset($this->cart_contents['shipping_method_id']))
		{
			$this->cart_contents['shipping_method_id'] = $this->EE->store_config->item('default_shipping_method_id');
		}

		// update order details fields
		foreach (array( 'billing_name', 'billing_address1', 'billing_address2', 'billing_address3', 'billing_region', 'billing_country', 'billing_postcode', 'billing_phone',
						'shipping_name', 'shipping_address1', 'shipping_address2', 'shipping_address3', 'shipping_region', 'shipping_country', 'shipping_postcode', 'shipping_phone',
						'order_custom1', 'order_custom2', 'order_custom3', 'order_custom4', 'order_custom5', 'order_custom6', 'order_custom7', 'order_custom8', 'order_custom9',
						'order_email', 'billing_same_as_shipping', 'shipping_same_as_billing', 'promo_code', 'shipping_method_id', 'payment_method', 'return_url', 'cancel_url',
						'accept_terms', 'register_member', 'username', 'screen_name', 'password', 'password_confirm') as $field_name)
		{
			if (isset($this->_update_data[$field_name]))
			{
				$this->cart_contents[$field_name] = $this->_update_data[$field_name];
			}
			elseif ( ! isset($this->cart_contents[$field_name]))
			{
				$this->cart_contents[$field_name] = NULL;
			}
		}

		// validate country code
		$countries = $this->EE->store_shipping_model->get_countries(TRUE, TRUE);
		foreach (array('billing', 'shipping') as $field_name)
		{
			// region name defaults to same as region (can just be used as a regular text field)
			$this->cart_contents[$field_name.'_region_name'] = $this->cart_contents[$field_name.'_region'];

			$country_code = $this->cart_contents[$field_name.'_country'];
			if (isset($countries[$country_code]))
			{
				$this->cart_contents[$field_name.'_country_name'] = $countries[$country_code]['name'];

				// get the region name if valid
				$region_code = $this->cart_contents[$field_name.'_region'];
				if (isset($countries[$country_code]['regions'][$region_code]))
				{
					$this->cart_contents[$field_name.'_region_name'] = $countries[$country_code]['regions'][$region_code];
				}
			}
			else
			{
				// country must be valid
				$this->cart_contents[$field_name.'_country'] = NULL;
				$this->cart_contents[$field_name.'_country_name'] = NULL;
			}
		}

		if ($this->cart_contents['billing_same_as_shipping'])
		{
			foreach (array('name', 'address1', 'address2', 'address3', 'region', 'country', 'postcode', 'phone') as $field_name)
			{
				$this->cart_contents['billing_'.$field_name] = $this->cart_contents['shipping_'.$field_name];
			}

			$this->cart_contents['shipping_same_as_billing'] = FALSE;
		}

		if ($this->cart_contents['shipping_same_as_billing'])
		{
			foreach (array('name', 'address1', 'address2', 'address3', 'region', 'country', 'postcode', 'phone') as $field_name)
			{
				$this->cart_contents['shipping_'.$field_name] = $this->cart_contents['billing_'.$field_name];
			}
		}
	}

	protected function _update_tax_rate()
	{
		// update current tax rate based on billing country/region
		$tax = $this->_get_tax_rate($this->cart_contents['billing_country'], $this->cart_contents['billing_region']);
		$this->cart_contents['tax_id'] = $tax['tax_id'];
		$this->cart_contents['tax_name'] = $tax['tax_name'];
		$this->cart_contents['tax_rate'] = $tax['tax_rate'];
		$this->cart_contents['tax_percent'] = $tax['tax_rate'] * 100;
		$this->cart_contents['tax_shipping'] = (bool)$tax['tax_shipping'];

 		/* -------------------------------------------
		/* 'store_cart_update_tax_rate' hook.
		/*  - Adjust the current tax rate when the cart is updated
		/*  - Added: 1.6.4
		*/
			if ($this->EE->extensions->active_hook('store_cart_update_tax_rate') === TRUE)
			{
				$this->cart_contents = $this->EE->extensions->call('store_cart_update_tax_rate', $this->cart_contents, $tax);
			}
		/*
		/* -------------------------------------------*/
	}

	/**
	 * Update the promo code details in the cart.
	 * The promo code should already be valid before it is added to the cart, so
	 * the error handling in this function is pretty crude.
	 */
	protected function _update_promo_code()
	{
		// update promo code details
		foreach (array('promo_code_id', 'promo_code_desc', 'promo_code_type',
			'promo_code_value', 'promo_code_free_shipping') as $field_name)
		{
			$this->cart_contents[$field_name] = NULL;
		}

		// always check for a promo code, because an automatic one might apply
		$promo_code = (string)$this->cart_contents['promo_code'];
		$promo_code_data = $this->EE->store_common_model->get_promo_code_by_code($promo_code, TRUE);
		if (empty($promo_code_data))
		{
			// promo code doesn't exist
			$this->cart_contents['promo_code'] = NULL;
			return;
		}

		// check the promo code hasn't expired
		$promo_code_error = $this->EE->store_common_model->validate_promo_code($promo_code_data);
		if ($promo_code_error)
		{
			if ($promo_code == '')
			{
				// automatic promo code wasn't valid, whatever...
				return;
			}

			// fringe case.. the promo code has become invalid since it was added to the cart
			// probably it has reached the use limit very quickly...
			$this->cart_contents['promo_code'] = NULL;
			$this->_save_cart();
			$this->EE->output->show_user_error(FALSE, array(lang('promo_code_no_longer_valid')));
		}

		// load promo code data into cart
		$this->cart_contents['promo_code_id'] = $promo_code_data['promo_code_id'];
		$this->cart_contents['promo_code'] = $promo_code_data['promo_code'];
		$this->cart_contents['promo_code_desc'] = $promo_code_data['description'];
		$this->cart_contents['promo_code_type'] = $promo_code_data['type'];
		$this->cart_contents['promo_code_value'] = $promo_code_data['value'];
		$this->cart_contents['promo_code_free_shipping'] = $promo_code_data['free_shipping'];
	}

	protected function _reset_totals()
	{
		foreach ($this->_total_fields as $field_name)
		{
			$this->cart_contents[$field_name] = 0;
		}

		foreach ($this->_total_price_fields as $field_name)
		{
			$this->cart_contents[$field_name] = '';
			$this->cart_contents[$field_name.'_val'] = 0;
		}
	}

	protected function _update_totals()
	{
		if ($this->cart_contents['tax_shipping'])
		{
			// calculate handling tax
			$this->_calculate_tax('order_handling');

			// calculate shipping (ex handling) tax
			$this->_calculate_tax('order_shipping_ex_handling');
		}
		else
		{
			$this->cart_contents['order_handling_tax_val'] = 0;
			$this->cart_contents['order_handling_inc_tax_val'] = $this->cart_contents['order_handling_val'];

			$this->cart_contents['order_shipping_ex_handling_tax_val'] = 0;
			$this->cart_contents['order_shipping_ex_handling_inc_tax_val'] = $this->cart_contents['order_shipping_ex_handling_val'];
		}

		// total shipping
		$this->cart_contents['order_shipping_val'] = $this->cart_contents['order_shipping_ex_handling_val'] + $this->cart_contents['order_handling_val'];
		$this->cart_contents['order_shipping_tax_val'] = $this->cart_contents['order_shipping_ex_handling_tax_val'] + $this->cart_contents['order_handling_tax_val'];
		$this->cart_contents['order_shipping_inc_tax_val'] = $this->cart_contents['order_shipping_ex_handling_inc_tax_val'] + $this->cart_contents['order_handling_inc_tax_val'];

		$this->cart_contents['order_subtotal_inc_shipping_val'] = $this->cart_contents['order_subtotal_val'] + $this->cart_contents['order_shipping_val'];
		$this->cart_contents['order_subtotal_inc_shipping_tax_val'] = $this->cart_contents['order_subtotal_tax_val'] + $this->cart_contents['order_shipping_tax_val'];
		$this->cart_contents['order_subtotal_inc_shipping_inc_tax_val'] = $this->cart_contents['order_subtotal_inc_tax_val'] + $this->cart_contents['order_shipping_inc_tax_val'];

		// calculate discount tax
		$this->_calculate_tax('order_discount');

		$this->cart_contents['order_subtotal_inc_discount_val'] = store_round_currency($this->cart_contents['order_subtotal_val'] - $this->cart_contents['order_discount_val']);
		$this->cart_contents['order_subtotal_inc_discount_tax_val'] = store_round_currency($this->cart_contents['order_subtotal_tax_val'] - $this->cart_contents['order_discount_tax_val']);
		$this->cart_contents['order_subtotal_inc_discount_inc_tax_val'] = store_round_currency($this->cart_contents['order_subtotal_inc_tax_val'] - $this->cart_contents['order_discount_inc_tax_val']);

		$this->cart_contents['order_total_ex_tax_val'] = store_round_currency($this->cart_contents['order_subtotal_val'] + $this->cart_contents['order_shipping_val'] - $this->cart_contents['order_discount_val']);
		$this->cart_contents['order_tax_val'] = store_round_currency($this->cart_contents['order_subtotal_tax_val'] + $this->cart_contents['order_shipping_tax_val'] - $this->cart_contents['order_discount_tax_val']);
		$this->cart_contents['order_total_val'] = store_round_currency($this->cart_contents['order_total_ex_tax_val'] + $this->cart_contents['order_tax_val']);

		$this->cart_contents['order_owing_val'] = $this->cart_contents['order_total_val'];
		$this->cart_contents['is_order_paid'] = FALSE;
		$this->cart_contents['is_order_unpaid'] = TRUE;
	}

	protected function _calculate_tax($key)
	{
		$tax_rate = $this->cart_contents['tax_rate'];
		if ($this->EE->store_config->item('tax_rounding') == 'y')
		{
			// tax back-calculated from tax-inclusive price
			$this->cart_contents[$key.'_inc_tax_val'] = store_round_currency($this->cart_contents[$key.'_val'] * (1 + $tax_rate));
			$this->cart_contents[$key.'_val'] = store_round_currency($this->cart_contents[$key.'_inc_tax_val'] / (1 + $tax_rate));
			$this->cart_contents[$key.'_tax_val'] = $this->cart_contents[$key.'_inc_tax_val'] - $this->cart_contents[$key.'_val'];
		}
		else
		{
			// tax calculated from tax-exclusive price
			$this->cart_contents[$key.'_val'] = store_round_currency($this->cart_contents[$key.'_val']);
			$this->cart_contents[$key.'_tax_val'] = store_round_currency($this->cart_contents[$key.'_val'] * $tax_rate);
			$this->cart_contents[$key.'_inc_tax_val'] = $this->cart_contents[$key.'_val'] + $this->cart_contents[$key.'_tax_val'];

		}
	}

	protected function _update_item($item)
	{
		if (empty($item['sku']))
		{
			// try to guess the SKU (product was probably just added to cart)
			$item['sku'] = $this->EE->store_products_model->get_product_sku_by_modifiers($item['entry_id'], $item['mod_values']);
		}

		// make sure a product with that SKU exists
		$stock_item = $this->EE->store_products_model->find_by_sku($item['sku']);
		if (empty($stock_item))	return FALSE;

		// make a fresh item
		$item = array(
			'key' => $item['key'],
			'site_id' => $stock_item['site_id'],
			'entry_id' => $stock_item['entry_id'],
			'sku' => $stock_item['sku'],
			'mod_values' => $item['mod_values'],
			'input_values' => $item['input_values'],
			'item_qty' => empty($item['item_qty']) ? 0 : $item['item_qty'],
			'title' => $stock_item['title'],
			'url_title' => $stock_item['url_title'],
			'regular_price' => $stock_item['regular_price'],
			'regular_price_val' => $stock_item['regular_price_val'],
			'sale_price' => $stock_item['sale_price'],
			'sale_price_val' => $stock_item['sale_price_val'],
			'price_val' => $stock_item['price_val'],
			'on_sale' => $stock_item['on_sale'],
			'free_shipping' => $stock_item['free_shipping'],
			'tax_exempt' => $stock_item['tax_exempt'],
			'width' => $stock_item['dimension_w'],
			'length' => $stock_item['dimension_l'],
			'height' => $stock_item['dimension_h'],
			'weight' => $stock_item['weight'],
		);

		// does the entry have a page_url?
		$item += $this->EE->store_common_model->get_entry_page_url($item['site_id'], $item['entry_id']);

		// process modifiers and any price changes
		$item = $this->_process_item_modifiers($item, $stock_item);
		$item['price'] = store_format_currency($item['price_val']);

		// update quantity if necessary
		if (isset($this->_update_data['items'][$item['key']]['item_qty']))
		{
			$item['item_qty'] = (int)$this->_update_data['items'][$item['key']]['item_qty'];
		}

		// if qty was updated to 0, remove item from cart
		if ($item['item_qty'] <= 0)	return FALSE;

		// check if the product has a minimum order qty
		if ( ! empty($stock_item['min_order_qty']) AND $item['item_qty'] < $stock_item['min_order_qty'])
		{
			$item['item_qty'] = $stock_item['min_order_qty'];
		}

		// if we don't allow backorders, then user cannot order more than we have in stock
		if ($stock_item['track_stock'] == 'y' AND
			$item['item_qty'] > $stock_item['stock_level'])
		{
			$item['item_qty'] = $stock_item['stock_level'];

			// make sure new order qty complies with min order qty
			if ( ! empty($stock_item['min_order_qty']) AND $item['item_qty'] < $stock_item['min_order_qty'])
			{
				// this product is essentially out of stock
				// because the current stock level is below minimum order qty
				$item['item_qty'] = 0;
			}
		}

		// if item is out of stock, remove from cart
		if ($item['item_qty'] <= 0)	return FALSE;

		// work out item totals and tax
		$item = $this->process_product_tax($item);

		$item_tax_rate = $item['tax_exempt'] ? 0 : $this->cart_contents['tax_rate'];

		// important we do this in the correct order to remove rounding errors
		if ($this->EE->store_config->item('tax_rounding') == 'y')
		{
			// round(price * tax_rate) * qty
			// tax is back-calculated on item total
			$item['handling_inc_tax_val'] = store_round_currency($stock_item['handling_val'] * (1 + $this->cart_contents['tax_rate']));
			$item['handling_val'] = store_round_currency($stock_item['handling_val']);
			$item['handling_tax_val'] = $item['handling_inc_tax_val'] - $item['handling_val'];

			$item['item_total_val'] = store_round_currency($item['price_inc_tax_val'] * $item['item_qty']);
			$item['item_subtotal_val'] = store_round_currency($item['item_total_val'] / (1 + $item_tax_rate));
			$item['item_tax_val'] = $item['item_total_val'] - $item['item_subtotal_val'];
		}
		else
		{
			// round(price * tax_rate * qty)
			// tax is calculated on item subtotal
			$item['handling_val'] = store_round_currency($stock_item['handling_val']);
			$item['handling_tax_val'] = store_round_currency($item['handling_val'] * $this->cart_contents['tax_rate']);
			$item['handling_inc_tax_val'] = $item['handling_val'] + $item['handling_tax_val'];

			$item['item_subtotal_val'] = store_round_currency($item['price_val'] * $item['item_qty']);
			$item['item_tax_val'] = store_round_currency($item['item_subtotal_val'] * $item_tax_rate);
			$item['item_total_val'] = $item['item_subtotal_val'] + $item['item_tax_val'];
		}

 		/* -------------------------------------------
		 * DEPRECATED 'store_cart_item_update' hook.
		 *  - Modify cart item
		 *  - Added: 1.2.0
		 * WILL BE REMOVED IN A FUTURE VERSION
		 * Please use 'store_cart_item_update_end' hook instead
		 */
			if ($this->EE->extensions->active_hook('store_cart_item_update') === TRUE)
			{
				$this->cart_contents = $this->EE->extensions->call('store_cart_item_update', $this->cart_contents);
			}
		/* -------------------------------------------*/

 		/* -------------------------------------------
		/* 'store_cart_item_update_end' hook.
		/*  - Modify a cart item when the cart is updated
		/*  - Added: 1.5.0
		*/
			if ($this->EE->extensions->active_hook('store_cart_item_update_end') === TRUE)
			{
				$item = $this->EE->extensions->call('store_cart_item_update_end', $item);
			}
		/*
		/* -------------------------------------------*/

		$item['handling'] = store_format_currency($item['handling_val']);
		$item['handling_tax'] = store_format_currency($item['handling_tax_val']);
		$item['handling_inc_tax'] = store_format_currency($item['handling_inc_tax_val']);

		$item['item_subtotal'] = store_format_currency($item['item_subtotal_val']);
		$item['item_tax'] = store_format_currency($item['item_tax_val']);
		$item['item_total'] = store_format_currency($item['item_total_val']);

		return $item;
	}

	protected function _process_item_modifiers($item, $stock_item)
	{
		// validate and update product modifiers
		$item['modifiers'] = array();

		foreach ($item['mod_values'] as $mod_id => $mod_value)
		{
			if ( ! isset($stock_item['modifiers'][$mod_id]))
			{
				unset($item['mod_values'][$mod_id]);
				continue;
			}

			$stock_mod = $stock_item['modifiers'][$mod_id];

			$mod_data = array(
				'modifier_id' => $stock_mod['product_mod_id'],
				'modifier_name' => $stock_mod['mod_name'],
				'modifier_type' => $stock_mod['mod_type'],
				'modifier_value' => $mod_value,
				'option_id' => '',
				'price_mod' => '',
				'price_mod_val' => '',
				'price_mod_inc_tax' => '',
				'price_mod_inc_tax_val' => ''
			);

			if ($stock_mod['mod_type'] == 'var' OR $stock_mod['mod_type'] == 'var_single_sku')
			{
				// check mod_value is a valid option id
				if ( ! isset($stock_mod['options'][$mod_value]))
				{
					unset($item['mod_values'][$mod_id]);
					continue;
				}

				$stock_opt = $stock_mod['options'][$mod_value];
				$mod_data['option_id'] = $mod_value;
				$mod_data['modifier_value'] = $stock_opt['opt_name'];
				$mod_data['price_mod'] = $stock_opt['opt_price_mod'];
				$mod_data['price_mod_val'] = $stock_opt['opt_price_mod_val'];
				$mod_data['price_mod_inc_tax_val'] = store_round_currency($mod_data['price_mod_val'] * (1 + $this->cart_contents['tax_rate']), TRUE);
				$mod_data['price_mod_inc_tax'] = store_format_currency($mod_data['price_mod_inc_tax_val'], TRUE);

				$item['price_val'] += $stock_opt['opt_price_mod_val'];
			}

			$item['modifiers'][] = $mod_data;
		}

		// add modifier entries for template inputs
		foreach ($item['input_values'] as $name => $value)
		{
			$item['modifiers'][] = array(
				'modifier_id' => '',
				'modifier_name' => $name,
				'modifier_type' => 'custom',
				'modifier_value' => $value,
				'option_id' => '',
				'price_mod' => '',
				'price_mod_val' => '',
				'price_mod_inc_tax' => '',
				'price_mod_inc_tax_val' => ''
			);
		}

		// work around weird bug in EE template engine
		if (empty($item['modifiers'])) $item['modifiers'] = array(array());

		return $item;
	}

	/**
	 * Provides easy access to the current tax rate
	 */
	public function tax_rate()
	{
		return $this->cart_contents['tax_rate'];
	}

	/**
	 * This function goes over a product and adds tax-inclusive prices based on the
	 * current cart tax rate. Also used from module & extension classes.
	 */
	public function process_product_tax($product)
	{
		$tax_rate = $product['tax_exempt'] ? 0 : $this->cart_contents['tax_rate'];

		$product['price_inc_tax_val'] = store_round_currency($product['price_val'] * (1 + $tax_rate));
		$product['price_inc_tax'] = store_format_currency($product['price_inc_tax_val']);
		$product['regular_price_inc_tax_val'] = store_round_currency($product['regular_price_val'] * (1 + $tax_rate));
		$product['regular_price_inc_tax'] = store_format_currency($product['regular_price_inc_tax_val']);
		$product['sale_price_inc_tax_val'] = store_round_currency($product['sale_price_val'] * (1 + $tax_rate));
		$product['sale_price_inc_tax'] = store_format_currency($product['sale_price_inc_tax_val']);

		$product['you_save_val'] = $product['regular_price_val'] - $product['price_val'];
		$product['you_save_inc_tax_val'] = $product['regular_price_inc_tax_val'] - $product['price_inc_tax_val'];
		$product['you_save'] = store_format_currency($product['you_save_val']);
		$product['you_save_inc_tax'] = store_format_currency($product['you_save_inc_tax_val']);
		$product['you_save_percent'] = empty($product['regular_price_val']) ? 0 : round(($product['regular_price_val'] - $product['price_val']) / $product['regular_price_val'] * 100);

 		/* -------------------------------------------
		/* 'store_process_product_tax' hook.
		/*  - Modify a product before it is displayed to the user
		/*  - Added: 1.2.0
		*/
			if ($this->EE->extensions->active_hook('store_process_product_tax') === TRUE)
			{
				$product = $this->EE->extensions->call('store_process_product_tax', $product);
			}
		/*
		/* -------------------------------------------*/

		return $product;
	}

	protected function _normalize_units()
	{
		// create some handy normalized shipping units
		if ($this->cart_contents['weight_units'] == 'kg')
		{
			$this->cart_contents['order_shipping_weight_kg'] = $this->cart_contents['order_shipping_weight'];
			$this->cart_contents['order_shipping_weight_lb'] = $this->cart_contents['order_shipping_weight'] * Store_shipping::LB_PER_KG;
		}
		else
		{
			$this->cart_contents['order_shipping_weight_kg'] = $this->cart_contents['order_shipping_weight'] / Store_shipping::LB_PER_KG;
			$this->cart_contents['order_shipping_weight_lb'] = $this->cart_contents['order_shipping_weight'];
		}

		switch ($this->cart_contents['dimension_units'])
		{
			case 'cm':
				$this->cart_contents['order_shipping_length_cm'] = $this->cart_contents['order_shipping_length'];
				$this->cart_contents['order_shipping_width_cm'] = $this->cart_contents['order_shipping_width'];
				$this->cart_contents['order_shipping_height_cm'] = $this->cart_contents['order_shipping_height'];
				$this->cart_contents['order_shipping_length_in'] = $this->cart_contents['order_shipping_length_cm'] / Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_width_in'] = $this->cart_contents['order_shipping_width_cm'] / Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_height_in'] = $this->cart_contents['order_shipping_height_cm'] / Store_shipping::CM_PER_INCH;
				break;
			case 'm':
				$this->cart_contents['order_shipping_length_cm'] = $this->cart_contents['order_shipping_length'] * 100;
				$this->cart_contents['order_shipping_width_cm'] = $this->cart_contents['order_shipping_width'] * 100;
				$this->cart_contents['order_shipping_height_cm'] = $this->cart_contents['order_shipping_height'] * 100;
				$this->cart_contents['order_shipping_length_in'] = $this->cart_contents['order_shipping_length_cm'] / Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_width_in'] = $this->cart_contents['order_shipping_width_cm'] / Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_height_in'] = $this->cart_contents['order_shipping_height_cm'] / Store_shipping::CM_PER_INCH;
				break;
			case 'in':
				$this->cart_contents['order_shipping_length_in'] = $this->cart_contents['order_shipping_length'];
				$this->cart_contents['order_shipping_width_in'] = $this->cart_contents['order_shipping_width'];
				$this->cart_contents['order_shipping_height_in'] = $this->cart_contents['order_shipping_height'];
				$this->cart_contents['order_shipping_length_cm'] = $this->cart_contents['order_shipping_length_in'] * Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_width_cm'] = $this->cart_contents['order_shipping_width_in'] * Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_height_cm'] = $this->cart_contents['order_shipping_height_in'] * Store_shipping::CM_PER_INCH;
				break;
			case 'ft':
				$this->cart_contents['order_shipping_length_in'] = $this->cart_contents['order_shipping_length'] * 12;
				$this->cart_contents['order_shipping_width_in'] = $this->cart_contents['order_shipping_width'] * 12;
				$this->cart_contents['order_shipping_height_in'] = $this->cart_contents['order_shipping_height'] * 12;
				$this->cart_contents['order_shipping_length_cm'] = $this->cart_contents['order_shipping_length_in'] * Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_width_cm'] = $this->cart_contents['order_shipping_width_in'] * Store_shipping::CM_PER_INCH;
				$this->cart_contents['order_shipping_height_cm'] = $this->cart_contents['order_shipping_height_in'] * Store_shipping::CM_PER_INCH;
				break;
		}
	}

	protected function _update_shipping()
	{
		$this->cart_contents['shipping_method_id'] = (int)$this->cart_contents['shipping_method_id'];
		$this->cart_contents['shipping_method_rule'] = NULL;
		$this->cart_contents['error:shipping_method'] = FALSE;

		if ($this->EE->store_shipping->load($this->cart_contents['shipping_method_id']))
		{
			$this->cart_contents['shipping_method'] = $this->EE->store_shipping->title();
			$this->cart_contents['shipping_method_plugin'] = $this->EE->store_shipping->name();

			// dont bother calculating yet if no items currently
			if ($this->cart_contents['order_qty'] == 0 OR
				$this->cart_contents['promo_code_free_shipping'] == 'y')
			{
				return;
			}

			if ($this->cart_contents['order_shipping_qty'] > 0)
			{
				$shipping = $this->EE->store_shipping->calculate_shipping($this->cart_contents);
				if (is_array($shipping))
				{
					$this->cart_contents = array_merge($this->cart_contents, $shipping);
				}
				else
				{
					$this->cart_contents['order_shipping_val'] = (float)$shipping;
				}
			}

			$this->cart_contents['order_shipping_ex_handling_val'] = $this->cart_contents['order_shipping_val'];
		}
		else
		{
			$this->cart_contents['shipping_method_id'] = '';
			$this->cart_contents['shipping_method'] = '';
			$this->cart_contents['shipping_method_plugin'] = '';
		}
	}

	protected function _update_payment_method()
	{
		$payment_method = $this->EE->store_payments_model->find_payment_method_by_name($this->cart_contents['payment_method']);
		if (empty($payment_method['enabled']))
		{
			// somehow the payment method has been disabled since it was added to the cart..
			$this->cart_contents['payment_method'] = '';
			$this->cart_contents['payment_method_name'] = '';
			$this->cart_contents['payment_method_title'] = '';
		}

		$this->cart_contents['payment_method_name'] = $payment_method['title'];
		$this->cart_contents['payment_method_title'] = $payment_method['title']; // deprecated
	}

	public function empty_cart()
	{
		$this->cart_contents = array();
		$this->_save_cart();
		$this->update();
	}

	/**
	 * Checks whether the current cart is empty
	 */
	public function is_empty()
	{
		return empty($this->cart_contents['items']);
	}

	/**
	 * Create an order from the contents of the current cart
	 */
	public function submit()
	{
		// set submit cookie (triggers conversion tracking code on order summary page)
		$this->EE->functions->set_cookie('cmcartsubmit', $this->cart_id, 0);

		return $this->EE->store_orders_model->insert_order($this->cart_contents);
	}

	/**
	 * Load the user's current cart from the database
	 */
	protected function _load_cart()
	{
		$row = $this->EE->store_common_model->get_cart_by_id($this->cart_id);

		if (empty($row))
		{
			// no such cart, un-set cookie
			$this->cart_id = NULL;
			$this->EE->functions->set_cookie(self::COOKIE_NAME);
		}
		else
		{
			$this->cart_contents = unserialize(base64_decode($row['contents']));

			if (isset($this->cart_contents['member_id']) AND
				$this->cart_contents['member_id'] != $this->EE->session->userdata['member_id'])
			{
				// member_id has changed, reload the cart
				$this->update();
			}
		}
	}

	/**
	 * Save the current cart contents to the database
	 */
	protected function _save_cart()
	{
		// does the cart contain items?
		if ($this->is_empty())
		{
			if ($this->cart_id)
			{
				// delete the cart from database
				$this->EE->store_common_model->remove_cart($this->cart_id);

				$this->cart_id = NULL;
				$this->EE->functions->set_cookie(self::COOKIE_NAME);
			}

			return;
		}

		// data to insert/update
		$data = array(
			'date' => $this->EE->localize->now,
			'ip_address' => $this->cart_contents['ip_address'],
			'contents' => base64_encode(serialize($this->cart_contents)),
		);

		// is this an insert or update?
		if ($this->cart_id)
		{
			$this->EE->store_common_model->update_cart($this->cart_id, $data);
		}
		else
		{
			$this->cart_id = $data['cart_id'] = md5(uniqid(mt_rand(), TRUE));
			$this->EE->store_common_model->insert_cart($data);
		}

		// update cookie
		$this->EE->functions->set_cookie(self::COOKIE_NAME, $this->cart_id, $this->EE->store_config->item('cart_expiry') * 60);
	}

	/**
	 * Calculates the current tax rate based on country and region
	 */
	protected function _get_tax_rate($country, $region)
	{
		$tax_rates = $this->EE->store_shipping_model->get_tax_rates_array();

		if ( ! empty($country))
		{
			if ( ! empty($region) AND isset($tax_rates[$country][$region]))
			{
				return $tax_rates[$country][$region];
			}

			if (isset($tax_rates[$country]['*'])) return $tax_rates[$country]['*'];
		}

		if (isset($tax_rates['*']['*'])) return $tax_rates['*']['*'];

		return array('tax_id' => 0, 'tax_name' => '', 'tax_rate' => 0, 'tax_shipping' => FALSE);
	}
}
/* End of file ./libraries/store_cart.php */