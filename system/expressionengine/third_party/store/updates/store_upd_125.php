<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_125
{
	/**
	 * Add extra shipping rule columns
	 */
	public function up()
	{
		$this->EE = get_instance();

		if ( ! $this->EE->db->field_exists('per_weight_rate', 'store_shipping_rules'))
		{
			$this->EE->dbforge->add_column('store_shipping_rules', array(
				'per_weight_rate'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE)
			), 'per_item_rate');

			$this->EE->dbforge->add_column('store_shipping_rules', array(
				'max_order_qty'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
				'min_order_qty'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
				'max_order_total'		=> array('type' => 'decimal', 'constraint' => '19,4'),
				'min_order_total'		=> array('type' => 'decimal', 'constraint' => '19,4'),
				'max_weight'			=> array('type' => 'double'),
				'min_weight' 			=> array('type' => 'double'),
				'postcode'				=> array('type' => 'varchar', 'constraint' => 10, 'null' => FALSE),
			), 'region_code');
		}

		$this->EE->dbforge->modify_column('store_shipping_rules', array(
			'country_code' => array('name' => 'country_code', 'type' => 'char', 'constraint' => 2, 'null' => FALSE),
			'region_code'  => array('name' => 'region_code', 'type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
		));

		$this->EE->dbforge->modify_column('store_tax_rates', array(
			'country_code' => array('name' => 'country_code', 'type' => 'char', 'constraint' => 2, 'null' => FALSE),
			'region_code'  => array('name' => 'region_code', 'type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
		));

		// update legacy weight units stored in orders table
		$this->EE->db->where('weight_units', 'lbs')
			->set('weight_units', 'lb')
			->update('store_orders');
	}
}
