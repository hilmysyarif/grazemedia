<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_reports
{

	function __construct()
	{
		$this->EE =& get_instance();

		$this->EE->load->model(array('store_common_model', 'store_orders_model', 'store_products_model'));
		$this->EE->load->library('store_payments');
	}

	function orders($start_date, $end_date, $status)
	{
		$table_head = array(
			'#',
			lang('order_date'),
		);

		$order_fields = $this->EE->store_config->get_order_fields();
		foreach ($order_fields as $field_name => $field)
		{
			if (isset($field['title']))
			{
				if (empty($field['title']))
				{
					unset($order_fields[$field_name]);
				}
				else
				{
					$order_fields[$field_name] = $field['title'];
					$table_head[] = $field['title'];
				}
			}
			else
			{
				$order_fields[$field_name] = lang($field_name);
				$table_head[] = lang($field_name);
			}
		}

		$table_head[] = lang('promo_code');
		$table_head[] = lang('items');
		$table_head[] = lang('order_qty');
		$table_head[] = lang('order_subtotal');
		$table_head[] = lang('order_discount');
		$table_head[] = lang('shipping');
		$table_head[] = lang('order_tax');
		$table_head[] = lang('order_total');
		$table_head[] = lang('paid');
		$table_head[] = lang('balance_due');
		$table_head[] = lang('paid?');
		$table_head[] = lang('date_paid');

		$sum = array();
		$sum['balance_due'] = 0;

		$data = array(
			'table_head' => $table_head,
			'table_data' => array(),
		);

		$orders = $this->EE->store_orders_model->get_orders_by_date($start_date, $end_date, $status);

		$sum = array(
			'order_qty' => 0,
			'order_subtotal' => 0,
			'order_discount' => 0,
			'order_shipping' => 0,
			'order_tax' => 0,
			'order_total' => 0,
			'order_paid' => 0,
			'order_owing' => 0,
		);

		foreach ($orders as $key => $order)
		{
			$sum['order_qty'] += $order['order_qty'];
			$sum['order_subtotal'] += $order['order_subtotal_val'];
			$sum['order_discount'] += $order['order_discount_val'];
			$sum['order_shipping'] += $order['order_shipping_val'];
			$sum['order_tax'] += $order['order_shipping_tax_val'] + $order['order_subtotal_tax_val'];
			$sum['order_total'] += $order['order_total_val'];
			$sum['order_paid'] += $order['order_paid_val'];
			$sum['order_owing'] += $order['order_owing_val'];

			$order_items = '';
			foreach ($order['items'] as $key => $item)
			{
				$order_items .= $item['title']."\n";
			}
			$order_items = trim($order_items);

			$table_row = array(
				$order['order_id'],
				$this->EE->store_config->human_time($order['order_date'])
			);

			foreach ($order_fields as $field_name => $field)
			{
				$table_row[] = $order[$field_name];
			}

			$table_row[] = $order['promo_code'];
			$table_row[] = $order_items;
			$table_row[] = $order['order_qty'];
			$table_row[] = $order['order_subtotal'];
			$table_row[] = $order['order_discount'];
			$table_row[] = $order['order_shipping'];
			$table_row[] = $order['order_tax'];
			$table_row[] = $order['order_total'];
			$table_row[] = $order['order_paid'];
			$table_row[] = $order['order_owing'];
			$table_row[] = $order['is_order_paid'] ? lang('yes') : lang('no');
			$table_row[] = empty($order['order_paid_date']) ? '' : $this->EE->store_config->human_time($order['order_paid_date']);

			$data['table_data'][] = $table_row;
		}

		$row_totals = array('<strong>'.lang('totals').'</strong>', '');
		foreach ($order_fields as $field_name => $field)
		{
			$row_totals[] = '';
		}
		$row_totals[] = '';
		$row_totals[] = '';

		$row_totals[] = '<strong>'.$sum['order_qty'].'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_subtotal']).'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_discount']).'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_shipping']).'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_tax']).'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_total']).'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_paid']).'</strong>';
		$row_totals[] = '<strong>'.store_format_currency($sum['order_owing']).'</strong>';
		$row_totals[] = '';
		$row_totals[] = '';

		$data['table_data'][] = $row_totals;
		return $data;
	}

	//Returns an array of all sales occuring between the start and end dates specified
	function sales_by_date($start_date, $end_date)
	{
		$table_head = array(
			'#',
			lang('order_date'),
			lang('billing_name'),
			lang('sku'),
			lang('product'),
			lang('item_qty'),
			lang('item_price'),
			lang('item_subtotal'),
			lang('order_qty'),
			lang('order_subtotal'),
			lang('shipping'),
			lang('order_tax'),
			lang('order_total'),
		);

		// figure out which payment methods were used
		$sum = array();
		$payment_methods = $this->EE->store_orders_model->get_order_payment_methods($start_date, $end_date);

		// always display maunal payments last
		unset($payment_methods['manual']);
		$payment_methods['manual'] = 'manual';

		foreach ($payment_methods as $key => $method)
		{
			$table_head[] = lang($method);
			$sum[$method] = 0;
		}

		$table_head[] = lang('owing');
		$table_head[] = lang('date_paid');
		$sum['balance_due'] = 0;

		$data = array(
			'table_head' => $table_head,
			'table_data' => array(),
		);

		$orders = $this->EE->store_orders_model->get_orders_by_date($start_date, $end_date);

		$sum_order_items = 0;
		$sum_subtotals = 0;
		$sum_shipping = 0;
		$sum_tax = 0;
		$sum_totals = 0;
		$sum_items_qty = 0;
		$sum_items_subtotal = 0;

		foreach ($orders as $key => $order)
		{
			foreach ($order['items'] as $key => $item)
			{
				$item_title = $item['title'];
				if ( ! empty($item['modifiers_desc'])) $item_title .= NBS.'('.$item['modifiers_desc'].')';

				$table_row = array(
				$order['order_id'],
				$this->EE->store_config->human_time($order['order_date']),
				$order['billing_name'],
				$item['sku'],
				$item_title,
				$item['item_qty'],
				$item['price'],
				$item['item_subtotal'],
				'', // order qty
				'', // order subtotal
				'', // shipping total
				'', // tax total
				'', // order total
				);

				foreach($payment_methods as $method)
				{
					$table_row[] = ''; // each plugin
				}
				$table_row[] = ''; // amount owed
				$table_row[] = ''; // date paid

				$data['table_data'][] = $table_row;

				$sum_items_qty += $item['item_qty'];
				$sum_items_subtotal += $item['item_subtotal_val'];
			}

			$sum_order_items += $order['order_qty'];
			$sum_subtotals += $order['order_subtotal_val'];
			$sum_shipping += $order['order_shipping_val'];
			$sum_tax += $order['order_tax_val'];
			$sum_totals += $order['order_total_val'];

			$table_row = array(
				$order['order_id'],
				$this->EE->store_config->human_time($order['order_date']),
				$order['billing_name'],
				'', // $item['sku']
				'', // $item['title'].' ('.$item['modifiers_desc'].')'
				'', // $item['item_qty']
				'', // $item['price']
				'', // $item['item_subtotal']
				$order['order_qty'],
				$order['order_subtotal'],
				$order['order_shipping'],
				$order['order_tax'],
				$order['order_total']
			);

			foreach($payment_methods as $method)
			{
				if (isset($order[$method]))
				{
					$table_row[] = store_format_currency($order[$method]);
					$sum[$method] += $order[$method]; // sum amount paid for individual payment methods
				}
				else
				{
					$table_row[] = store_format_currency(0);
				}
			}

			$table_row[] = $order['order_owing'];
			$table_row[] = empty($order['order_paid_date']) ? '' : $this->EE->store_config->human_time($order['order_paid_date']);
			$sum['balance_due'] += $order['order_owing_val'];

			$data['table_data'][] = $table_row;
		}

		$row_totals = array(
			array('data' => '<strong>'.lang('totals').'</strong>', 'colspan' => 5),
			array('data' => '<strong>'.$sum_items_qty.'</strong>'),
			array('data' => ''),
			array('data' => '<strong>'.store_format_currency($sum_items_subtotal).'</strong>'),
			array('data' => '<strong>'.$sum_order_items.'</strong>'),
			array('data' => '<strong>'.store_format_currency($sum_subtotals).'</strong>'),
			array('data' => '<strong>'.store_format_currency($sum_shipping).'</strong>'),
			array('data' => '<strong>'.store_format_currency($sum_tax).'</strong>'),
			array('data' => '<strong>'.store_format_currency($sum_totals).'</strong>'),
		);

		foreach($payment_methods as $method)
		{
			$row_totals[] = array('data' =>'<strong>'.store_format_currency($sum[$method]).'</strong>');
		}

		$row_totals[] = array('data' =>'<strong>'.store_format_currency($sum['balance_due']).'</strong>');
		$row_totals[] = array('data' =>'');

		$data['table_data'][] = $row_totals;
		return $data;
	}

	function stock_value($stock_inventory_options)
	{
		switch($stock_inventory_options)
		{
			case 'product title':
				$stock_inventory_options = 'title';
				break;
			case 'stockcode':
				$stock_inventory_options = 'sku';
				break;
		}

		$data = array(
			'table_head' => array(lang('sku'), lang('product_title'), lang('price'), lang('current_stock_level'), lang('total_stock_value')),
			'table_data' => array(),
		);

		$query = $this->EE->store_products_model->stock_inventory($stock_inventory_options);

		$sum_total_qty = 0;
		$sum_total_value = 0;
		foreach ($query as $key => $row)
		{
			$row['stock_level'] = ($row['stock_level'] < 0) ? 0 : $row['stock_level'];
			$sum_total_qty += $row['stock_level'];
			$sum_total_value += $row['stock_level'] * $row['price_val'];
			$data['table_data'][] = array(
				$row['sku'],
				isset($row['description']) ? array('data' => $row['title'].NBS.'('.$row['description'].')', 'width' => '40%') : array('data' => $row['title'], 'width' => '40%'),
				$row['price'],
				isset($row['stock_level']) ? $row['stock_level'] : 0,
				store_format_currency( $row['stock_level'] * $row['price_val'] )
			);
		}
		$data['table_data'][] = array( array('data'=>'<strong>'.lang('totals').'</strong>', 'colspan'=>"3"), array('data' =>'<strong>'.$sum_total_qty.'</strong>'), array('data' =>'<strong>'.store_format_currency($sum_total_value).'</strong>'));
		return $data;
	}

	function stock_products($start_date, $end_date, $orderby_option)
	{
		$data = array(
			'table_head' => array(lang('sku'), lang('product_title'), lang('quantity_sold'), lang('current_price'), lang('average_price'), lang('net_sales')),
			'table_data' => array(),
		);

		$query = $this->EE->store_orders_model->get_all_selling_stock($start_date, $end_date, $orderby_option);

		$sum_qty = 0;
		$sum_net_totals = 0;

		foreach ($query as $key => $row)
		{
			$sum_qty += $row['item_qty'];
			$sum_net_totals += $row['item_subtotal'];

			$product_title = $row['order_item_title'];
			if ( ! empty($row['modifiers_desc'])) $product_title .= NBS.'('.$row['modifiers_desc'].')';

			$data['table_data'][] = array(
				$row['sku'],
				$product_title,
				$row['item_qty'],
				store_format_currency($row['item_current_price']),
				store_format_currency($row['item_avg_price']),
				store_format_currency($row['item_subtotal']),
			);
		}
		$data['table_data'][] = array( array('data'=>'<strong>'.lang('totals').'</strong>', 'colspan'=>"2"), array('data' =>'<strong>'.$sum_qty.'</strong>'), '', '', array('data' =>'<strong>'.store_format_currency($sum_net_totals).'</strong>'));
		return $data;
	}

	function table_from_csv($table_head, $table_data)
	{
		foreach ($table_data as $row) $string = isset($row['data']) ? $row['data'] : $row;
	}
}
/* End of file ./libraries/store_reports.php */