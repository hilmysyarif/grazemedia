<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_orders_model extends CI_Model {

	protected $_countries;

	public function __construct()
	{
		parent::__construct();

		$this->load->model(array('store_payments_model', 'store_shipping_model', 'store_common_model'));
		$this->load->helper('store_helper');
	}

	public function find_by_id($order_id)
	{
		$order = $this->db->select('*, order_total - order_paid as order_owing')
			->from('store_orders')
			->join('members', 'store_orders.member_id = members.member_id', 'left')
			->where('order_id', (int)$order_id)
			->where('site_id', $this->config->item('site_id'))
			->get()->row_array();

		if (empty($order)) return FALSE;

		$order = $this->_process_order($order);
		$this->fetch_order_items($order);
		return $order;
	}

	public function find_all($options)
	{
		$options = array_merge(array(
			'count_all_results' => FALSE,
			'order_by' => NULL,
			'sort' => NULL,
			'limit' => 50,
			'offset' => 0,
			'order_status' => NULL,
			'order_paid_status' => NULL,
			'keywords' => NULL,
			'exact_match' => FALSE,
			'search_in' => NULL,
		), $options);

		$this->db->select('o.*, o.order_total - o.order_paid as order_owing, members.screen_name')
			->from('store_orders o')
			->join('members', 'o.member_id = members.member_id', 'left')
			->where('o.site_id', $this->config->item('site_id'));

		if ($options['order_status'] == '@incomplete')
		{
			$this->db->where('(order_completed_date IS NULL OR order_completed_date = 0)');
			$options['order_status'] = NULL;
		}
		else
		{
			// by default we only return completed orders
			$this->db->where('order_completed_date > 0');
		}

		if ( ! empty($options['order_status']))
		{
			$this->db->where('order_status', $options['order_status']);
		}

		if ( ! empty($options['start_date']) AND ! empty($options['end_date']))
		{
			$this->db->where('order_date >', $options['start_date']);
			$this->db->where('order_date <', $options['end_date']);
		}

		switch ($options['order_paid_status'])
		{
			case 'unpaid':
				$this->db->where('o.order_paid < o.order_total');
				break;
			case 'paid':
				$this->db->where('o.order_paid = o.order_total');
				break;
			case 'overpaid':
				$this->db->where('o.order_paid > o.order_total');
				break;
		}

		if ( ! empty($options['keywords']))
		{
			if ($options['exact_match'])
			{
				switch ($options['search_in'])
				{
					case 'order_shipping_name':
						$this->db->where('shipping_name', $options['keywords']);
						break;
					case 'order_billing_name':
						$this->db->where('billing_name', $options['keywords']);
						break;
					case 'member':
						$this->db->where('screen_name', $options['keywords']);
						break;
					case 'order_id':
						$this->db->where('order_id', $options['keywords']);
						break;
					default:
						$this->db->where('shipping_name', $options['keywords']);
						$this->db->or_where('billing_name', $options['keywords']);
						$this->db->or_where('screen_name', $options['keywords']);
						$this->db->or_where('order_id', $options['keywords']);
						break;
				}
			}
			else
			{
				switch ($options['search_in'])
				{
					case 'order_shipping_name':
						$this->db->like('shipping_name', $options['keywords']);
						break;
					case 'order_billing_name':
						$this->db->like('billing_name', $options['keywords']);
						break;
					case 'member':
						$this->db->like('screen_name', $options['keywords']);
						break;
					case 'order_id':
						$this->db->like('order_id', $options['keywords']);
						break;
					default:
						$this->db->like('shipping_name', $options['keywords']);
						$this->db->or_like('billing_name', $options['keywords']);
						$this->db->or_like('screen_name', $options['keywords']);
						$this->db->or_like('order_id', $options['keywords']);
						break;
				}
			}
		}

		if ($options['count_all_results'])
		{
			return $this->db->count_all_results();
		}

		if ( ! empty($options['order_by']))
		{
			$this->db->order_by($options['order_by'],
				strtoupper($options['sort']) == 'ASC' ? 'ASC' : 'DESC');
		}

		$this->db->limit($options['limit'], $options['offset']);

		$result = $this->db->get()->result_array();

		// index array by order_id
		$orders = array();
		foreach ($result as $order)
		{
			$orders[$order['order_id']] = $this->_process_order($order);
		}
		unset($result);

		$this->fetch_order_items($orders);
		return $orders;
	}

	public function get_order_by_id($order_id, $join_members = FALSE, $fetch_items = FALSE)
	{
		$this->logger->deprecated();
		return $this->find_by_id($order_id);
	}

	public function get_orders($limit = 50, $offset = 0, $order_by = NULL, $filter = NULL, $count_only = FALSE)
	{
		$this->logger->deprecated();

		$options = empty($filter) ? array() : $filter;
		$options['limit'] = $limit;
		$options['offset'] = $offset;
		$options['order_by'] = $order_by;
		$options['count_all_results'] = $count_only;
		return $this->find_all($options);
	}

	public function get_orders_by_id($order_ids)
	{
		$this->db->select('o.*, o.order_total - o.order_paid as order_owing, members.screen_name');

		$this->db->from('store_orders o');
		$this->db->join('members', 'o.member_id = members.member_id', 'left');

		$this->db->where_in('order_id', $order_ids);
		$this->db->where('site_id', $this->config->item('site_id'));

		$result = $this->db->get()->result_array();

		foreach ($result as $key => $order) $result[$key] = $this->_process_order($order);

		return $result;
	}

	public function get_orders_by_date($start_date, $end_date, $status = NULL)
	{
		$this->db->select('o.*, p.payment_method as p_payment_method, sum(amount) as p_amount_paid');
		$this->db->from('store_orders o');
		$this->db->where('o.site_id', $this->config->item('site_id'));
		$this->db->where('o.order_completed_date > 0');
		$this->db->where('o.order_date >=', $start_date);
		$this->db->where('o.order_date <', $end_date);

		if ( ! empty($status))
		{
			$this->db->where('o.order_status', $status);
		}

		$this->db->join('store_payments p', 'o.order_id = p.order_id', 'left');
		$this->db->where('p.payment_status = "complete"');
		$this->db->group_by('o.order_id');
		$this->db->group_by('p.payment_method');
		$this->db->order_by('order_date', 'desc');
		$result = $this->db->get()->result_array();

		$orders = array();
		foreach ($result as $row)
		{
			$order_id = $row['order_id'];
			if (empty($orders[$order_id]))
			{
				$orders[$order_id] = $this->_process_order($row);
			}

			if ( ! empty($row['p_payment_method']))
			{
				$orders[$order_id][$row['p_payment_method']] = $row['p_amount_paid'];
			}
		}

		unset($result);

		$this->fetch_order_items($orders);
		return $orders;
	}

	public function get_order_payment_methods($start_date, $end_date, $status = NULL)
	{
		$this->db->distinct();
		$this->db->select('payment_method');
		$this->db->from('store_orders o');
		$this->db->where('o.site_id', $this->config->item('site_id'));
		$this->db->where('o.order_completed_date > 0');
		$this->db->where('o.order_date >=', $start_date);
		$this->db->where('o.order_date <', $end_date);

		if ($status)
		{
			$this->db->where('order_status', $status);
		}

		$query = $this->db->get()->result_array();
		if (empty($query)) return array();

		$result = array();
		foreach ($query as $key => $row)
		{
			$result[$row['payment_method']] = $row['payment_method'];
		}

		return $result;
	}

	protected function _process_order($order)
	{
		if (is_null($this->_countries))
		{
			$this->_countries = $this->store_shipping_model->get_countries(FALSE, TRUE);
		}

		if ( ! isset($order['order_owing']))
		{
			$order['order_owing'] = $order['order_total'] - $order['order_paid'];
		}

		$order['order_subtotal_inc_tax'] = $order['order_subtotal'] + $order['order_subtotal_tax'];
		$order['order_discount_inc_tax'] = $order['order_discount'] + $order['order_discount_tax'];
		$order['order_shipping_inc_tax'] = $order['order_shipping'] + $order['order_shipping_tax'];
		$order['order_handling_inc_tax'] = $order['order_handling'] + $order['order_handling_tax'];

		$order['order_shipping_ex_handling'] = $order['order_shipping'] - $order['order_handling'];
		$order['order_shipping_ex_handling_tax'] = $order['order_shipping_tax'] - $order['order_handling_tax'];
		$order['order_shipping_ex_handling_inc_tax'] = $order['order_shipping_inc_tax'] - $order['order_handling_inc_tax'];

		$order['order_subtotal_inc_shipping'] = $order['order_subtotal'] + $order['order_shipping'];
		$order['order_subtotal_inc_shipping_tax'] = $order['order_subtotal_tax'] + $order['order_shipping_tax'];
		$order['order_subtotal_inc_shipping_inc_tax'] = $order['order_subtotal_inc_tax'] + $order['order_shipping_inc_tax'];

		$order['order_subtotal_inc_discount'] = $order['order_subtotal'] - $order['order_discount'];
		$order['order_subtotal_inc_discount_tax'] = $order['order_subtotal_tax'] - $order['order_discount_tax'];
		$order['order_subtotal_inc_discount_inc_tax'] = $order['order_subtotal_inc_tax'] - $order['order_discount_inc_tax'];

		$order['order_total_ex_tax'] = $order['order_total'] - $order['order_tax'];

		foreach (array('order_subtotal', 'order_subtotal_tax', 'order_subtotal_inc_tax',
			'order_handling', 'order_handling_tax', 'order_handling_inc_tax',
			'order_shipping_ex_handling', 'order_shipping_ex_handling_tax', 'order_shipping_ex_handling_inc_tax',
			'order_shipping', 'order_shipping_tax', 'order_shipping_inc_tax',
			'order_subtotal_inc_shipping', 'order_subtotal_inc_shipping_tax', 'order_subtotal_inc_shipping_inc_tax',
			'order_discount', 'order_discount_tax', 'order_discount_inc_tax',
			'order_subtotal_inc_discount', 'order_subtotal_inc_discount_tax', 'order_subtotal_inc_discount_inc_tax',
			'order_total_ex_tax', 'order_tax', 'order_total',
			'order_paid', 'order_owing') as $field_name)
		{
			$order[$field_name.'_val'] = $order[$field_name];
			$order[$field_name] = store_format_currency($order[$field_name]);
		}

		$order['tax_percent'] = $order['tax_rate'] * 100;

		foreach (array('billing', 'shipping') as $field)
		{
			$country_code = $order[$field.'_country'];
			$region_code = $order[$field.'_region'];

			if (isset($this->_countries[$country_code]))
			{
				$order[$field.'_country_name'] = $this->_countries[$country_code]['name'];
			}
			else
			{
				$order[$field.'_country_name'] = $country_code;
			}

			if (isset($this->_countries[$country_code]['regions'][$region_code]))
			{
				$order[$field.'_region_name'] = $this->_countries[$country_code]['regions'][$region_code];
			}
			else
			{
				$order[$field.'_region_name'] = $region_code;
			}
		}

		$order['billing_address_full'] = implode(BR, array(
			$order['billing_address1'], $order['billing_address2'], $order['billing_address3'],
			$order['billing_region_name'].NBS.$order['billing_postcode'],
			$order['billing_country_name']
		));

		$order['shipping_address_full'] = implode(BR, array(
			$order['shipping_address1'], $order['shipping_address2'], $order['shipping_address3'],
			$order['shipping_region_name'].NBS.$order['shipping_postcode'],
			$order['shipping_country_name']
		));

		$order['billing_same_as_shipping'] = $order['billing_same_as_shipping'] == 'y';
		$order['shipping_same_as_billing'] = $order['shipping_same_as_billing'] == 'y';

		$order['is_order_paid'] = $order['order_owing_val'] <= 0;
		$order['is_order_unpaid'] = ! $order['is_order_paid'];

		$payment_method = $this->store_payments_model->find_payment_method_by_name($order['payment_method']);
		if (empty($payment_method))
		{
			$order['payment_method_name'] = $order['payment_method'];
			$order['payment_method_title'] = $order['payment_method'];
		}
		else
		{
			$order['payment_method_name'] = $payment_method['title'];
			$order['payment_method_title'] = $payment_method['title'];
		}

		if ($order['order_owing_val'] < 0)
		{
			$order['order_paid_str'] = '<span class="store_order_paid_over">'.lang('overpaid').'</span>';
		}
		elseif ($order['order_owing_val'] == 0)
		{
			$order['order_paid_str'] = '<span class="store_order_paid_yes">'.lang('yes').'</span>';
		}
		elseif ($order['order_paid_val'] > 0)
		{
			$order['order_paid_str'] = $order['order_paid'];
		}
		else
		{
			$order['order_paid_str'] = lang('no');
		}

		if (empty($order['order_completed_date']))
		{
			$order['order_status_html'] = '<span class="store_order_status_incomplete">'.lang('incomplete').'</span>';
		}
		else
		{
			$order['order_status_html'] = '<span style="color:#'.$this->get_status_color($order['order_status']).'">'.lang($order['order_status']).'</span>';
		}

		return $order;
	}

	/**
	 * Efficiently fetch the items for multiple orders.
	 * $orders must either be a single order, or an array of orders indexed by order_id
	 */
	public function fetch_order_items(&$orders)
	{
		if (empty($orders)) return;

		if (isset($orders['order_id']))
		{
			// load single order
			$orders['items'] = array();

			$result = $this->db->from('store_order_items i')
				->join('channel_titles t', 't.entry_id = i.entry_id', 'left')
				->where('order_id', $orders['order_id'])
				->order_by('i.order_item_id', 'ASC')
				->get()->result_array();

			foreach ($result as $row)
			{
				$orders['items'][] = $this->_process_order_item($row);
			}
		}
		else
		{
			// load multiple orders
			foreach ($orders as $order_id => $order)
			{
				$orders[$order_id]['items'] = array();
			}

			$result = $this->db->from('store_order_items i')
				->join('channel_titles t', 't.entry_id = i.entry_id', 'left')
				->where_in('order_id', array_keys($orders))
				->order_by('i.order_item_id', 'ASC')
				->get()->result_array();

			foreach ($result as $row)
			{
				$orders[$row['order_id']]['items'][] = $this->_process_order_item($row);
			}
		}
	}

	protected function _process_order_item($item)
	{
		$item['handling_inc_tax'] = $item['handling'] + $item['handling_tax'];

		foreach (array('price', 'price_inc_tax', 'regular_price', 'regular_price_inc_tax',
			'handling', 'handling_tax', 'handling_inc_tax',
			'item_subtotal', 'item_tax', 'item_total') as $field_name)
		{
			$item[$field_name.'_val'] = store_round_currency($item[$field_name]);
			$item[$field_name] = store_format_currency($item[$field_name]);
		}

		$item['modifiers'] = empty($item['modifiers']) ? array() : unserialize(base64_decode($item['modifiers']));

		$modifiers_desc = array();
		foreach ($item['modifiers'] as $mod_data)
		{
			if ( ! empty($mod_data))
			{
				$modifiers_desc[] = "<strong>{$mod_data['modifier_name']}</strong>: {$mod_data['modifier_value']}";
			}
		}

		$item['modifiers_desc'] = implode(', ', $modifiers_desc);

		$item['on_sale'] = $item['on_sale'] == 'y';
		$item['free_shipping'] = $item['free_shipping'] == 'y';
		$item['tax_exempt'] = $item['tax_exempt'] == 'y';

		// does the entry have a page_url?
		$item += $this->store_common_model->get_entry_page_url($item['site_id'], $item['entry_id']);

		return $item;
	}

	public function get_order_return_url($order)
	{
		$return_url = $order['return_url'];
		$return_url = str_replace('ORDER_ID', $order['order_id'], $return_url);
		$return_url = str_replace('ORDER_HASH', $order['order_hash'], $return_url);
		return $return_url;
	}

	public function total_orders()
	{
		return $this->db->where('site_id', $this->config->item('site_id'))
			->count_all_results('store_orders');
	}

	/**
	 * Creates a new order. Initially the order will be in an "incomplete" state (indicated by
	 * the order_completed_date being NULL), until the payment has been authorized.
	 */
	public function insert_order($data)
	{
		// add order to database
		$order = array(
			'site_id' => $this->config->item('site_id'),
			'member_id' => (int)$this->session->userdata['member_id'],
			'order_hash' => $data['cart_id'],
			'order_date' => $this->localize->now,
			'ip_address' => $this->input->ip_address(),
			'order_paid' => 0,
			'order_paid_date' => NULL,
		);

		// check for an existing incomplete order
		$row = $this->db->where('order_hash', $data['cart_id'])
			->get('store_orders')->row_array();

		if ( ! empty($row))
		{
			$order['order_id'] = $row['order_id'];

			if ( ! empty($row['order_completed_date']))
			{
				// this should never happen, we are just being cautious:
				// for some reason the submitted cart is already a completed order,
				// so remove the offending cart and display a generic error to the user
				$this->store_common_model->remove_cart($data['cart_id']);
				show_error(lang('error_processing_order'));
			}

			// remove existing order and order items, we will insert it again
			$this->db->where('order_id', $row['order_id'])->delete('store_orders');
			$this->db->where('order_id', $row['order_id'])->delete('store_order_items');
		}

		foreach (array( 'billing_name', 'billing_address1', 'billing_address2', 'billing_address3', 'billing_region', 'billing_country', 'billing_postcode', 'billing_phone',
				'shipping_name', 'shipping_address1', 'shipping_address2', 'shipping_address3', 'shipping_region', 'shipping_country', 'shipping_postcode', 'shipping_phone',
				'billing_same_as_shipping', 'shipping_same_as_billing', 'order_custom1', 'order_custom2', 'order_custom3', 'order_custom4', 'order_custom5',
				'order_custom6', 'order_custom7', 'order_custom8', 'order_custom9',
				'order_email', 'promo_code_id', 'promo_code', 'shipping_method_id', 'shipping_method', 'shipping_method_plugin', 'shipping_method_rule',
				'payment_method', 'order_qty', 'tax_id', 'tax_name', 'tax_rate',
				'order_length', 'order_width', 'order_height', 'dimension_units', 'order_weight', 'weight_units', 'return_url', 'cancel_url') as $field_name)
		{
			if (isset($data[$field_name]))
			{
				$order[$field_name] = $data[$field_name];
			}
			else
			{
				$order[$field_name] = NULL;
			}
		}

		foreach (array('order_subtotal', 'order_subtotal_tax', 'order_discount', 'order_discount_tax',
				'order_shipping', 'order_shipping_tax', 'order_handling', 'order_handling_tax',
				'order_tax', 'order_total') as $field_name)
		{
			if (isset($data[$field_name.'_val'])) $order[$field_name] = $data[$field_name.'_val'];
		}

		$order['billing_same_as_shipping'] = $order['billing_same_as_shipping'] ? 'y' : 'n';
		$order['shipping_same_as_billing'] = $order['shipping_same_as_billing'] ? 'y' : 'n';

		// should we mark the order as paid?
		if ($order['order_total'] == 0)
		{
			$order['order_paid_date'] = $this->localize->now;
		}

 		/* -------------------------------------------
		/* 'store_order_submit_start' hook.
		/*  - Modify an order before it is submitted
		/*  - Added: 1.4.0
		*/
			if ($this->extensions->active_hook('store_order_submit_start') === TRUE)
			{
				$order = $this->extensions->call('store_order_submit_start', $order);
				if ($this->extensions->end_script === TRUE) return $order;
			}
		/*
		/* -------------------------------------------*/

		$this->db->insert('store_orders', $order);
		if (empty($order['order_id']))
		{
			// record generated order_id
			$order['order_id'] = (int)$this->db->insert_id();
		}

		// add order items to database
		foreach ($data['items'] as $item)
		{
			$insert_item = array(
				'order_id' => $order['order_id'],
				'entry_id' => $item['entry_id'],
				'sku' => $item['sku'],
				'title' => $item['title'],
				'modifiers' => empty($item['modifiers']) ? NULL : base64_encode(serialize($item['modifiers'])),
				'price' => $item['price_val'],
				'price_inc_tax' => $item['price_inc_tax_val'],
				'regular_price' => $item['regular_price_val'],
				'regular_price_inc_tax' => $item['regular_price_inc_tax_val'],
				'on_sale' => $item['on_sale'] ? 'y' : 'n',
				'weight' => $item['weight'],
				'length' => $item['length'],
				'width' => $item['width'],
				'height' => $item['height'],
				'handling' => $item['handling_val'],
				'handling_tax' => $item['handling_tax_val'],
				'free_shipping' => $item['free_shipping'] ? 'y' : 'n',
				'tax_exempt' => $item['tax_exempt'] ? 'y' : 'n',
				'item_qty' => $item['item_qty'],
				'item_subtotal' => $item['item_subtotal_val'],
				'item_tax' => $item['item_tax_val'],
				'item_total' => $item['item_total_val'],
			);
			$this->db->insert('store_order_items', $insert_item);
		}

		// refresh order details
		$order = $this->_process_order($order);
		$this->fetch_order_items($order);

		if ($order['order_owing_val'] == 0)
		{
			// free orders should be marked as complete
			$this->complete_order($order);
		}

 		/* -------------------------------------------
		/* 'store_order_submit_end' hook.
		/*  - Extra processing after an order has been submitted
		/*  - Added: 1.4.0
		*/
			if ($this->extensions->active_hook('store_order_submit_end') === TRUE)
			{
				$order = $this->extensions->call('store_order_submit_end', $order);
			}
		/*
		/* -------------------------------------------*/

		return $order;
	}

	/**
	 * Mark an order as "complete". This decrements product stock levels and removes
	 * the cart used to create an order.
	 */
	public function complete_order($order)
	{
 		/* -------------------------------------------
		/* 'store_order_complete_start' hook.
		/*  - Extra processing before an order is completed
		/*  - Added: 1.5.0
		*/
			if ($this->extensions->active_hook('store_order_complete_start') === TRUE)
			{
				$order = $this->extensions->call('store_order_complete_start', $order);
				if ($this->extensions->end_script === TRUE) return $order;
			}
		/*
		/* -------------------------------------------*/

		// mark order as complete
		$this->db->where('site_id', $this->config->item('site_id'))
			->where('order_id', $order['order_id'])
			->where('order_completed_date IS NULL')
			->update('store_orders', array('order_completed_date' => $this->localize->now));

		if ($this->db->affected_rows() == 0)
		{
			// something isn't right, either order doesn't exist or is already completed
			return FALSE;
		}

		// find cart (can't rely on session because payment gateway might initiate request)
		$cart = $this->db->where('cart_id', $order['order_hash'])->get('store_carts')->row_array();

		// did the customer request a user account with their order?
		// if order is already associated with a member, skip account creation
		if (( ! empty($cart)) && empty($order['member_id']))
		{
			$cart = unserialize(base64_decode($cart['contents']));
			if ( ! empty($cart['register_member']))
			{
				$this->load->library('store_members');
				$order['member_id'] = $this->store_members->register($cart);
			}
		}

		// update member details with order data
		if ( ! empty($order['member_id']))
		{
			$this->store_common_model->save_member_data($order['member_id'], $order);
		}

		// remove/empty cart
		$this->db->where('cart_id', $order['order_hash'])
			->delete('store_carts');

		// adjust our stock levels
		foreach ($order['items'] as $item)
		{
			$this->db->set('stock_level', 'stock_level - '.$item['item_qty'], FALSE);
			$this->db->where('sku', $item['sku']);
			$this->db->where('track_stock', 'y');
			$this->db->update('store_stock');
		}

		// if a promo code was used, increment its use count
		if ( ! empty($order['promo_code_id']))
		{
			$promo_data = $this->store_common_model->get_promo_code_by_id($order['promo_code_id']);
			$promo_data['use_count'] += 1;
			$this->store_common_model->update_promo_code($order['promo_code_id'], array('use_count' => $promo_data['use_count']));
		}

		// update the order status (will trigger any associated email confirmation)
		$default_status = $this->get_default_status();
		$this->update_order_status($order['order_id'], $default_status['name']);

 		/* -------------------------------------------
		/* 'store_order_complete_end' hook.
		/*  - Extra processing after an order has been completed
		/*  - Added: 1.5.0
		*/
			if ($this->extensions->active_hook('store_order_complete_end') === TRUE)
			{
				// refresh order details
				$order = $this->find_by_id($order['order_id']);

				$this->extensions->call('store_order_complete_end', $order);
			}
		/*
		/* -------------------------------------------*/
	}

	public function update_order($order_id, $data)
	{
		$this->db->where('order_id', (int)$order_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->update('store_orders', $data);
	}

	public function order_exists($order_id)
	{
		$count = $this->db->from('store_orders')
			->where('order_id', $order_id)
			->where('site_id', $this->config->item('site_id'))
			->count_all_results();
		return $count > 0;
	}

	public function remove_order($order_id, $also_remove_related = TRUE)
	{
		$order_id = (int)$order_id;

		// first ensure the order exists in the current site
		if ( ! $this->order_exists($order_id)) return;

		$this->db->where('order_id', (int)$order_id);
		$this->db->delete('store_orders');

		if ($also_remove_related)
		{
			$this->db->where('order_id', (int)$order_id);
			$this->db->delete('store_order_items');

			$this->db->where('order_id', (int)$order_id);
			$this->db->delete('store_order_history');

			$this->db->where('order_id', (int)$order_id);
			$this->db->delete('store_payments');
		}
	}

	public function remove_orders($order_ids, $also_remove_related = TRUE)
	{
		foreach ($order_ids as $order_id)
		{
			$this->remove_order($order_id, $also_remove_related);
		}
	}

	public function get_order_status($status_id)
	{
		$this->db->where('order_status_id', $status_id);
		$this->db->where('site_id', $this->config->item('site_id'));
		return $this->db->get('store_order_statuses')->row_array();
	}

	/**
	 * Lazy load and cache all order statuses (we use them often)
	 */
	public function get_order_statuses()
	{
		static $order_statuses = array();

		if (empty($order_statuses))
		{
			$result = $this->db->select('s.*, t.name as email_template_name')
				->from('store_order_statuses s')
				->join('store_email_templates t', 's.email_template = t.template_id', 'left')
				->where('s.site_id', $this->config->item('site_id'))
				->order_by('s.display_order', 'asc')
				->get()->result_array();

			foreach ($result as $row)
			{
				$order_statuses[$row['name']] = $row;
			}

			unset($result);
		}

		return $order_statuses;
	}

	public function get_status_editable($status_id)
	{
		$this->db->from('store_orders');
		$this->db->join('store_order_statuses', 'store_orders.order_status = store_order_statuses.name');
		$this->db->where('order_status_id', $status_id);
		$this->db->where('store_orders.site_id', $this->config->item('site_id'));
		return $this->db->count_all_results() == 0;
	}

	public function check_duplicate_status_name($status_name, $status_id)
	{
		if ($status_id != 0) $this->db->where('order_status_id !=', $status_id);
		$this->db->where('name', $status_name);
		$this->db->where('site_id', $this->config->item('site_id'));
		return $this->db->get('store_order_statuses')->result_array();
	}

	public function get_status_color($status_name)
	{
		$statuses = $this->get_order_statuses();
		return empty($statuses[$status_name]['highlight']) ? '' : $statuses[$status_name]['highlight'];
	}

	public function update_status($status_id, $data)
	{
		// only one status can be default
		if (isset($data['is_default']) AND $data['is_default'] == 'y')
		{
			$this->db->update('store_order_statuses', array('is_default' => 'n'));
		}
		else
		{
			$data['is_default'] = 'n';
		}

		$data['highlight'] = str_replace('#', '', $data['highlight']);

		if ($status_id > 0)
		{
			// name will be empty if status currently in use
			if (empty($data['name'])) { unset($data['name']); }

			$this->db->where('order_status_id', $status_id);
			$this->db->where('site_id', $this->config->item('site_id'));
			$this->db->update('store_order_statuses', $data);
		}
		else
		{
			// set display order to last
			$this->db->select('max(display_order) + 1 as display_order');
			$data['display_order'] = (int)$this->db->get('store_order_statuses')->row('display_order');
			$data['site_id'] = $this->config->item('site_id');

			$this->db->insert('store_order_statuses', $data);
		}
	}

	public function update_status_display_orders($statuses_data)
	{
		foreach ($statuses_data as $status_id => $display_order)
		{
			$this->db->where('order_status_id', (int)$status_id);
			$this->db->where('site_id', $this->config->item('site_id'));
			$this->db->update('store_order_statuses', array('display_order' => (int)$display_order));
		}
	}

	public function delete_status($status_id)
	{
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->delete('store_order_statuses', array('order_status_id' => $status_id));
	}

	public function get_default_status()
	{
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->where('is_default', 'y');
		return $this->db->get('store_order_statuses')->row_array();
	}

	public function get_order_status_history($order_id)
	{
		$this->db->where('order_id', $order_id);
		$this->db->join('members', 'store_order_history.order_status_member = members.member_id', 'left');
		$this->db->order_by('order_status_updated', 'desc');
		return $this->db->get('store_order_history')->result_array();
	}

	public function update_order_status($order_id, $order_status, $member_id = 0, $message = NULL)
	{
		$order_id = (int)$order_id;
		$member_id = (int)$member_id;
		if (empty($order_status)) return;
		if (empty($message)) $message = NULL;

		// first ensure the order exists in the current site
		if ( ! $this->order_exists($order_id)) return;

		// update order table
		$data = array(
			'order_status' => $order_status,
			'order_status_updated' => $this->localize->now,
			'order_status_member' => $member_id
		);

		$this->db->where('order_id', $order_id);
		$this->db->update('store_orders', $data);

		// update order history table
		$data['order_id'] = $order_id;
		$data['message'] = $message;

		$this->db->insert('store_order_history', $data);

		// is there an email assigned to the new status?
		$this->db->select('t.name as template_name');
		$this->db->from('store_order_statuses as s');
		$this->db->join('store_email_templates as t', 't.template_id = s.email_template');
		$this->db->where('s.name', $order_status);
		$this->db->where('t.enabled', 'y');
		$result = $this->db->get()->row_array();

		if ( ! empty($result))
		{
			$template_name = $result['template_name'];
			if ($member_id == 0) $member_id = NULL;

			$this->load->library('store_emails');
			$this->store_emails->send_email($template_name, $order_id);
		}
	}

	public function update_orders_statuses($order_ids, $order_status)
	{
		foreach ($order_ids as $order_id)
		{
			$this->update_order_status($order_id, $order_status, $this->session->userdata['member_id']);
		}
	}

	public function new_payment($order)
	{
		$data = array(
			'order_id' => $order['order_id'],
			'member_id' => 0,
			'amount' => $order['order_owing_val'],
			'payment_hash' => md5(uniqid(mt_rand(), TRUE)),
			'payment_status' => 'pending',
			'payment_method' => $order['payment_method'],
			'payment_date' => $this->localize->now,
			'reference' => NULL,
			'message' => NULL,
		);

		// look up payment_method_class
		$payment_method = $this->store_payments_model->find_payment_method_by_name($order['payment_method']);
		$data['payment_method_class'] = isset($payment_method['class']) ? $payment_method['class'] : NULL;

		$this->db->insert('store_payments', $data);
		$data['payment_id'] = (int)$this->db->insert_id();

		return $data;
	}

	public function get_payment_by_id($payment_id)
	{
		return $this->db->where('payment_id', $payment_id)
			->get('store_payments')->row_array();
	}

	public function get_payment_by_hash($payment_hash)
	{
		return $this->db->where('payment_hash', $payment_hash)
			->get('store_payments')->row_array();
	}

	/**
	 * Update an existing payment (e.g. after payment was successful or failed)
	 *
	 * @param int $payment_id
	 * @param array $order
	 * @param Merchant_response $result the payment response object
	 * @return the updated $order object
	 */
	public function update_payment($order, $payment, $result)
	{
		$data = array(
			'payment_id' => $payment['payment_id'],
			'payment_status' => $result->status(),
			'payment_date' => $this->localize->now,
			'message' => $result->message(),
		);

		if ($result->reference())
		{
			$data['reference'] = $result->reference();
		}

		return $this->_process_payment($order, $data);
	}

	public function add_manual_payment($order, $payment_date, $amount, $message, $reference)
	{
		$payment = array(
			'order_id' => $order['order_id'],
			'member_id' => (int)$this->session->userdata['member_id'],
			'amount' => $amount,
			'payment_hash' => md5(uniqid(mt_rand(), TRUE)),
			'payment_status' => 'complete',
			'payment_method' => 'manual',
			'payment_method_class' => 'Merchant_manual',
			'payment_date' => $this->store_config->string_to_timestamp($payment_date),
			'message' => $message,
			'reference' => $reference,
		);

		return $this->_process_payment($order, $payment);
	}

	protected function _process_payment($order, $payment)
	{
 		/* -------------------------------------------
		/* 'store_order_payment_start' hook.
		/*  - Modify a payment before it is submitted
		/*  - Added: 1.4.0
		*/
			if ($this->extensions->active_hook('store_order_payment_start') === TRUE)
			{
				$payment = $this->extensions->call('store_order_payment_start', $order, $payment);
				if ($this->extensions->end_script) return;
			}
		/*
		/* -------------------------------------------*/

		if (empty($payment['payment_id']))
		{
			$this->db->insert('store_payments', $payment);
			$payment['payment_id'] = (int)$this->db->insert_id();
		}
		else
		{
			// update payment
			$this->db->where('payment_id', $payment['payment_id'])
				->update('store_payments', $payment);
		}

		if ($payment['payment_status'] !== 'failed')
		{
			// update orders table with new amount paid
			$total_paid = (float)$this->db->select('sum(amount) as total_paid')
				->where('order_id', $order['order_id'])
				->where('payment_status', 'complete')
				->get('store_payments')->row('total_paid');

			$order_update = array(
				'order_paid' => $total_paid,
			);

			// should we mark the order as paid?
			$is_order_paid = ($total_paid >= $order['order_total_val']);
			if ($is_order_paid AND empty($order['order_paid_date']))
			{
				$order_update['order_paid_date'] = $this->localize->now;
			}

			$this->db->where('order_id', $order['order_id']);
			$this->db->update('store_orders', $order_update);

			// should we mark the order as complete?
			if (empty($order['order_completed_date']))
			{
				if ($is_order_paid)
				{
					$this->complete_order($order);
				}
				else
				{
					// maybe enough funds are authorized to complete the order
					$total_authorized = $this->db->select('sum(amount) as total_authorized')
						->where('order_id', $order['order_id'])
						->where('(`payment_status` = "complete" OR `payment_status` = "authorized")')
						->get('store_payments')->row('total_authorized');

					if ($total_authorized >= $order['order_total_val'])
					{
						$this->complete_order($order);
					}
				}
			}
		}

		// refresh order details
		$order = $this->find_by_id($order['order_id']);

 		/* -------------------------------------------
		/* 'store_order_payment_end' hook.
		/*  - Extra processing after a payment has been submitted
		/*  - Added: 1.4.0
		*/
			if ($this->extensions->active_hook('store_order_payment_end') === TRUE)
			{
				$this->extensions->call('store_order_payment_end', $order, $payment);
			}
		/*
		/* -------------------------------------------*/

		return $order;
	}

	public function get_order_payments($order_id)
	{
		$payments = $this->db->select('p.*, m.screen_name as payment_member')
			->from('store_payments as p')
			->join('members as m', 'p.member_id = m.member_id', 'left')
			->where('order_id', (int)$order_id)
			->order_by('p.payment_date', 'desc')
			->get()->result_array();

		foreach ($payments as $key => $payment)
		{
			$payment_method = $this->store_payments_model->find_payment_method_by_name($payment['payment_method']);
			$payments[$key]['payment_method_title'] = empty($payment_method) ? $payment['payment_method'] : $payment_method['title'];
		}

		return $payments;
	}

	/*
	*@param start_date = The start date of the time period the graph will display. For a graph of
	*					 1 year start_date would be one year before current time.
	*
	*@param period_size = The time equivalent of one period. E.g. If graph is to display 1 year by
	*					  each week then the period size is 1 week in unix time.
	*/
	public function get_orders_graph($start_date, $period_size, $end_date)
	{
		$this->db->select('floor((order_paid_date -'.$start_date.')/'. $period_size.') as period_paid, sum(order_total) as period_total'); //CORRECT LOGIC & SYNTAX ??
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->where('order_paid >= order_total');
		$this->db->where('order_paid_date <', $end_date);
		$this->db->where('order_paid_date >', $start_date);
		$this->db->group_by('period_paid');
		$this->db->order_by('period_paid');
		return $this->db->get('store_orders')->result_array();
	}

	public function get_all_selling_stock($start_date, $end_date, $orderby_option)
	{
		$this->load->model('store_products_model');
		$this->db->select('oi.sku, oi.title as order_item_title, oi.modifiers, sum(item_qty) as item_qty, sum(item_subtotal) as item_subtotal, sum(item_tax) as item_tax, sum(item_total) as item_total');
		$this->db->from('store_order_items oi');
		$this->db->join('store_orders o', 'oi.order_id = o.order_id');
		$this->db->join('store_stock s', 'oi.sku = s.sku', 'left');
		$this->db->join('channel_titles t', 's.entry_id = t.entry_id', 'left');
		$this->db->where('o.site_id', $this->config->item('site_id'));
		$this->db->where('o.order_completed_date > 0');
		$this->db->where('o.order_date >', $start_date);
		$this->db->where('o.order_date <', $end_date);
		$this->db->group_by('oi.sku');
		$this->db->order_by($orderby_option, 'desc');

		$result = $this->db->get()->result_array();

		$all_sku_prices = $this->get_current_sku_prices();
		foreach ($result as $key => $row)
		{
			$result[$key]['item_current_price'] = isset($all_sku_prices[$row['sku']]) ? $all_sku_prices[$row['sku']] : 0;
			$result[$key]['item_avg_price'] = $row['item_subtotal'] / $row['item_qty'];
			$result[$key]['channel_title'] = empty($row['channel_title']) ? $row['order_item_title'] : $row['channel_title'];
			$result[$key]['modifiers'] = empty($row['modifiers']) ? array() : unserialize(base64_decode($row['modifiers']));
			$modifiers_desc = array();

			foreach ($result[$key]['modifiers'] as $mod_data)
			{
				// only display modifiers affecting the SKU
				if ( ! empty($mod_data) AND $mod_data['modifier_type'] == 'var')
				{
					$modifiers_desc[] = "<strong>{$mod_data['modifier_name']}</strong>: {$mod_data['modifier_value']}";
				}
			}

			$result[$key]['modifiers_desc'] = implode(', ', $modifiers_desc);
		}

		return $result;
	}

	public function get_current_sku_prices()
	{
		$this->db->select('s.entry_id, s.sku, p.regular_price, sum(po.opt_price_mod) as mod_price');
		$this->db->from('store_stock s');
		$this->db->join('store_products p', 's.entry_id = p.entry_id');
		$this->db->join('store_stock_options so', 's.sku = so.sku', 'left');
		$this->db->join('store_product_options po', 'po.product_opt_id = so.product_opt_id', 'left');
		$this->db->group_by('s.sku');
		$result = $this->db->get()->result_array();

		$prices = array();
		foreach ($result as $key => $row) $prices[$row['sku']] = $row['mod_price'] + $row['regular_price'];

		return $prices;
	}

	public function get_orders_tag($query)
	{
		// possible query fields
		foreach (array('order_id', 'order_hash', 'member_id', 'order_status', 'limit', 'offset', 'orderby', 'sort') as $field)
		{
			if ( ! isset($query[$field])) $query[$field] = FALSE;
		}

		// if fields are specified in template, they must not be empty
		if ($query['order_id'] !== FALSE AND empty($query['order_id'])) return array();
		if ($query['order_hash'] !== FALSE AND empty($query['order_hash'])) return array();

		if ($query['member_id'] !== FALSE)
		{
			if ($query['member_id'] == 'CURRENT_USER')
			{
				$query['member_id'] = $this->session->userdata['member_id'];
			}
			if (empty($query['member_id'])) return array();
		}

		// valid orderby/sort
		if ( ! in_array($query['orderby'], array('screen_name', 'username', 'order_status', 'order_total', 'order_id')))
		{
			$query['orderby'] = 'order_date';
		}
		$query['sort'] = (strtoupper($query['sort']) == 'ASC') ? 'ASC' : 'DESC';

		// valid limit/offset
		if ($query['limit'] <= 0) $query['limit'] = 100;
		if ($query['offset'] <= 0) $query['offset'] = 0;

		// build sql (only return completed orders)
		$sql = "SELECT o.*, m.screen_name, m.username
			FROM exp_store_orders o
			LEFT JOIN exp_members m ON o.member_id = m.member_id
			WHERE o.order_completed_date > 0
				AND o.site_id = ".$this->db->escape($this->config->item('site_id'));

		$sql .= $this->functions->sql_andor_string($query['order_id'], 'o.order_id');
		$sql .= $this->functions->sql_andor_string($query['member_id'], 'o.member_id');
		$sql .= $this->functions->sql_andor_string($query['order_status'], 'o.order_status');

		if ($query['order_hash'] !== FALSE) $sql .= " AND o.order_hash = ".$this->db->escape($query['order_hash']);

		if (isset($query['is_order_paid']))
		{
			if ($query['is_order_paid'])
			{
				$sql .= ' AND o.order_paid >= o.order_total';
			}
			else
			{
				$sql .= ' AND o.order_paid < o.order_total';
			}
		}

		$sql .= " ORDER BY ".$this->db->protect_identifiers($query['orderby'])." ".$query['sort']." LIMIT ?, ?";

		$result = $this->db->query($sql, array($query['offset'], $query['limit']))->result_array();

		$orders = array();
		foreach ($result as $row)
		{
			$orders[$row['order_id']] = $this->_process_order($row);
		}

		unset($result);

		$this->fetch_order_items($orders);
		return array_values($orders);
	}
}
/* End of file ./models/store_orders_model.php */