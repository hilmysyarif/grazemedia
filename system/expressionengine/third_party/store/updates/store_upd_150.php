<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_150
{
	public function up()
	{
		$this->EE = get_instance();

		Store_upd::register_action('act_payment_return');

		if ($this->EE->db->field_exists('cart_hash', 'store_carts'))
		{
			// remove redundant cart_hash column, store hash in cart_id
			$this->EE->dbforge->modify_column('store_carts', array(
				'cart_id' => array('name' => 'cart_id', 'type' => 'varchar', 'constraint' => 32, 'null' => FALSE)
			));

			$this->EE->db->query('UPDATE '.$this->EE->db->protect_identifiers('store_carts', TRUE).'
				SET cart_id = cart_hash');

			$this->EE->dbforge->drop_column('store_carts', 'cart_hash');
		}

		if ( ! $this->EE->db->field_exists('payment_hash', 'store_payments'))
		{
			$this->EE->dbforge->add_column('store_payments', array(
				'payment_status'		=> array('type' => 'varchar', 'constraint' => 20, 'null' => FALSE),
				'payment_hash'			=> array('type' => 'varchar', 'constraint' => 32, 'null' => FALSE),
			), 'order_id');

			$this->EE->db->query('UPDATE '.$this->EE->db->protect_identifiers('store_payments', TRUE).'
				SET payment_status = "complete", payment_hash = MD5(RAND())');

			Store_upd::create_index('store_payments', 'payment_hash', TRUE);
		}

		if ( ! $this->EE->db->field_exists('payment_method_class', 'store_payments'))
		{
			$this->EE->dbforge->add_column('store_payments', array(
				'payment_method_class'		=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE),
			), 'payment_method');

			$this->EE->db->query('UPDATE '.$this->EE->db->protect_identifiers('store_payments', TRUE).'
				SET `payment_method_class` = CONCAT("Merchant_", `payment_method`)
				WHERE `payment_method_class` = "" AND `payment_method` != ""');
		}

		// txn_id is now known as reference, and stored as VARCHAR(255)
		if ($this->EE->db->field_exists('txn_id', 'store_payments'))
		{
			$this->EE->dbforge->modify_column('store_payments', array(
				'txn_id' => array('name' => 'reference', 'type' => 'varchar', 'constraint' => 255, 'null' => TRUE),
			));
		}

		// cart_id is now same as order_hash
		Store_upd::drop_column_if_exists('store_orders', 'cart_id');

		// this field temporarily existed in 1.4.2.1
		Store_upd::drop_column_if_exists('store_payments', 'cart_id');

		if ( ! $this->EE->db->field_exists('cancel_url', 'store_orders'))
		{
			$this->EE->dbforge->add_column('store_orders', array(
				'cancel_url'				=> array('type' => 'varchar', 'constraint' => 255)
			), 'return_url');
		}

		if ( ! $this->EE->db->field_exists('order_completed_date', 'store_orders'))
		{
			$this->EE->dbforge->add_column('store_orders', array(
				'order_completed_date'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			), 'order_date');

			// all existing orders should be considered "complete"
			$this->EE->db->query('UPDATE '.$this->EE->db->protect_identifiers('store_orders', TRUE).'
				SET order_completed_date = order_date');
		}

		// order_status and order_status_updated are now allowed to be NULL (for incomplete orders)
		$this->EE->dbforge->modify_column('store_orders', array(
			'order_status'				=> array('name' => 'order_status', 'type' => 'varchar', 'constraint' => 20, 'null' => TRUE),
			'order_status_updated'		=> array('name' => 'order_status_updated', 'type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
		));

		// new payment and shipping method tables
		if ( ! $this->EE->db->table_exists('store_payment_methods'))
		{
			// payment methods table
			$this->EE->dbforge->add_field(array(
				'payment_method_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
				'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
				'class'					=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE),
				'name'					=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE),
				'title'					=> array('type' => 'varchar', 'constraint' => 255),
				'settings'				=> array('type' => 'text'),
				'enabled'				=> array('type' => 'tinyint', 'constraint' => '1', 'null' => FALSE),
			));

			$this->EE->dbforge->add_key('payment_method_id', TRUE);
			$this->EE->dbforge->create_table('store_payment_methods');
			Store_upd::create_index('store_payment_methods', array('site_id', 'name'), TRUE);

			// migrate data
			$this->EE->db->query('
				INSERT INTO '.$this->EE->db->protect_identifiers('store_payment_methods', TRUE).'
					(`payment_method_id`, `site_id`, `class`, `name`, `settings`, `enabled`)
				SELECT `plugin_instance_id`,
					`site_id`,
					CONCAT("Merchant_", `plugin_name`),
					`plugin_name`,
					`settings`,
					(CASE `enabled` WHEN "y" THEN 1 ELSE 0 END)
				FROM '.$this->EE->db->protect_identifiers('store_plugins', TRUE).'
				WHERE `plugin_type` = "p"');
		}

		if ( ! $this->EE->db->table_exists('store_shipping_methods'))
		{
			// shipping methods table
			$this->EE->dbforge->add_field(array(
				'shipping_method_id'	=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
				'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
				'class'					=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE),
				'title'					=> array('type' => 'varchar', 'constraint' => 255),
				'settings'				=> array('type' => 'text'),
				'enabled'				=> array('type' => 'tinyint', 'constraint' => '1', 'null' => FALSE),
				'display_order'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE),
			));

			$this->EE->dbforge->add_key('shipping_method_id', TRUE);
			$this->EE->dbforge->create_table('store_shipping_methods');
			Store_upd::create_index('store_shipping_methods', 'site_id');

			// migrate data
			$this->EE->db->query('
				INSERT INTO '.$this->EE->db->protect_identifiers('store_shipping_methods', TRUE).'
					(`shipping_method_id`, `site_id`, `class`, `title`, `settings`, `enabled`, `display_order`)
				SELECT `plugin_instance_id`,
					`site_id`,
					CONCAT("Store_shipping_", `plugin_name`),
					`instance_title`,
					`settings`,
					(CASE `enabled` WHEN "y" THEN 1 ELSE 0 END),
					`display_order`
				FROM '.$this->EE->db->protect_identifiers('store_plugins', TRUE).'
				WHERE `plugin_type` = "s"');
		}

		// remove old plugins table
		$this->EE->dbforge->drop_table('store_plugins');

		// rename plugin_instance_id foreign key to shipping_method_id
		if ($this->EE->db->field_exists('plugin_instance_id', 'store_shipping_rules'))
		{
			$this->EE->dbforge->modify_column('store_shipping_rules', array(
				'plugin_instance_id'	=> array('name' => 'shipping_method_id', 'type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			));
		}

		// add tax_shipping to store_tax_rates
		if ( ! $this->EE->db->field_exists('tax_shipping', 'store_tax_rates'))
		{
			$this->EE->dbforge->add_column('store_tax_rates', array(
				'tax_shipping' => array('type' => 'tinyint', 'constraint' => 1, 'null' => FALSE)
			), 'tax_rate');

			// existing tax rates keep current behaviour (shipping is taxable)
			$this->EE->db->update('store_tax_rates', array('tax_shipping' => 1));
		}

		// modify store_tax_rates enabled column to type TINYINT(1)
		$this->EE->db->where('enabled', 'y')->update('store_tax_rates', array('enabled' => 1));
		$this->EE->db->where('enabled', 'n')->update('store_tax_rates', array('enabled' => 0));

		$this->EE->dbforge->modify_column('store_tax_rates', array(
			'enabled' => array('name' => 'enabled', 'type' => 'tinyint', 'constraint' => 1, 'null' => FALSE, 'default' => 0),
		));

		// add shipping rule defaults
		$this->EE->db->where('enabled', 'y')->update('store_shipping_rules', array('enabled' => 1));
		$this->EE->db->where('enabled', 'n')->update('store_shipping_rules', array('enabled' => 0));

		$this->EE->dbforge->modify_column('store_shipping_rules', array(
			'title'					=> array('name' => 'title', 'type' => 'varchar', 'constraint' => 50, 'null' => FALSE, 'default' => ''),
			'country_code'			=> array('name' => 'country_code', 'type' => 'char', 'constraint' => 2, 'null' => FALSE, 'default' => ''),
			'region_code'			=> array('name' => 'region_code', 'type' => 'varchar', 'constraint' => 5, 'null' => FALSE, 'default' => ''),
			'postcode'				=> array('name' => 'postcode', 'type' => 'varchar', 'constraint' => 10, 'null' => FALSE, 'default' => ''),
			'base_rate'				=> array('name' => 'base_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'per_item_rate'			=> array('name' => 'per_item_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'per_weight_rate'		=> array('name' => 'per_weight_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'percent_rate'			=> array('name' => 'percent_rate', 'type' => 'double', 'null' => FALSE, 'default' => 0),
			'min_rate'				=> array('name' => 'min_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'max_rate'				=> array('name' => 'max_rate', 'type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'priority'				=> array('name' => 'priority', 'type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
			'enabled'				=> array('name' => 'enabled', 'type' => 'tinyint', 'constraint' => 1, 'null' => FALSE, 'default' => 0),
		));

		// add order defaults
		$this->EE->dbforge->modify_column('store_orders', array(
			'order_status_member'		=> array('name' => 'order_status_member', 'type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
		));

		$this->EE->dbforge->modify_column('store_order_history', array(
			'order_status_updated'	=> array('name' => 'order_status_updated', 'type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
			'order_status_member'	=> array('name' => 'order_status_member', 'type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
		));

		// remove unused enabled column from store stock
		Store_upd::drop_column_if_exists('store_stock', 'enabled');
	}
}
