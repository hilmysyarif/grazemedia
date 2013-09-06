<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_114
{
	/**
	 * Add new order variables
	 */
	public function up()
	{
		$this->EE = get_instance();

		// add order_handling to store_orders
		if ( ! $this->EE->db->field_exists('order_handling', 'store_orders'))
		{
			$this->EE->dbforge->add_column('store_orders', array(
				'order_handling_tax'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
				'order_handling'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			), 'order_shipping_tax');
		}

		// add shipping_method_rule to store_orders
		if ( ! $this->EE->db->field_exists('shipping_method_rule', 'store_orders'))
		{
			$this->EE->dbforge->add_column('store_orders', array(
				'shipping_method_rule'		=> array('type' => 'varchar', 'constraint' => 50),
			), 'shipping_method_plugin');
		}

		// add weight_units and dimension_units to store_orders
		if ( ! $this->EE->db->field_exists('dimension_units', 'store_orders'))
		{
			$this->EE->dbforge->add_column('store_orders', array(
				'weight_units'				=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
				'order_weight'				=> array('type' => 'double', 'null' => FALSE),
				'dimension_units'			=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
				'order_height'				=> array('type' => 'double', 'null' => FALSE),
				'order_width'				=> array('type' => 'double', 'null' => FALSE),
				'order_length'				=> array('type' => 'double', 'null' => FALSE),
			), 'tax_rate');
		}

		// add dimensions to store_order_items
		if ( ! $this->EE->db->field_exists('weight', 'store_order_items'))
		{
			$this->EE->dbforge->add_column('store_order_items', array(
				'tax_exempt'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
				'free_shipping'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
				'handling_tax'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
				'handling'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
				'height'				=> array('type' => 'double'),
				'width'					=> array('type' => 'double'),
				'length'				=> array('type' => 'double'),
				'weight'				=> array('type' => 'double'),
			), 'on_sale');
		}

		// add tax_exempt to store_products
		if ( ! $this->EE->db->field_exists('tax_exempt', 'store_products'))
		{
			$this->EE->dbforge->add_column('store_products', array(
				'tax_exempt'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			), 'free_shipping');
		}
	}
}
