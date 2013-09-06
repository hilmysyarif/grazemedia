<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

require_once(PATH_THIRD.'store/config.php');

class Store_upd
{
	var $version = STORE_VERSION;

	public static function register_hook($hook, $method = NULL, $priority = 10)
	{
		$EE = get_instance();

		if (is_null($method))
		{
			$method = $hook;
		}

		if ($EE->db->where('class', STORE_CLASS.'_ext')
			->where('hook', $hook)
			->count_all_results('extensions') == 0)
		{
			$EE->db->insert('extensions', array(
				'class'		=> STORE_CLASS.'_ext',
				'method'	=> $method,
				'hook'		=> $hook,
				'settings'	=> '',
				'priority'	=> $priority,
				'version'	=> STORE_VERSION,
				'enabled'	=> 'y'
			));
		}
	}

	public static function register_action($method)
	{
		$EE = get_instance();

		if ($EE->db->where('class', STORE_CLASS)
			->where('method', $method)
			->count_all_results('actions') == 0)
		{
			$EE->db->insert('actions', array(
				'class' => STORE_CLASS,
				'method' => $method
			));
		}
	}

	public static function create_index($table_name, $col_names, $unique = FALSE)
	{
		$EE = get_instance();

		$table_name = $EE->db->protect_identifiers($table_name, TRUE);

		if (is_array($col_names))
		{
			$index_name = implode('_', $col_names);
			foreach ($col_names as $key => $col)
			{
				$col_names[$key] = $EE->db->protect_identifiers($col);
			}
			$col_names = implode(',', $col_names);
		}
		else
		{
			$index_name = $col_names;
			$col_names = $EE->db->protect_identifiers($col_names);
		}

		$sql = $unique ? "CREATE UNIQUE INDEX " : "CREATE INDEX ";
		$sql .= "$index_name ON $table_name ($col_names)";
		return $EE->db->query($sql);
	}

	public static function drop_index($table_name, $col_name)
	{
		$EE = get_instance();

		$table_name = $EE->db->protect_identifiers($table_name, TRUE);
		$col_name = $EE->db->protect_identifiers($col_name);
		$sql = "DROP INDEX $col_name ON $table_name";
		return $EE->db->query($sql);
	}

	public static function drop_column_if_exists($table_name, $col_name)
	{
		$EE = get_instance();

		if ($EE->db->field_exists($col_name, $table_name))
		{
			$EE->dbforge->drop_column($table_name, $col_name);
		}
	}

	public function __construct()
	{
		$this->EE =& get_instance();
	}

	public function install()
	{
		$this->EE->load->dbforge();

		// first make sure there is no existing zombie data
		$this->uninstall();

		// register module
		$this->EE->db->insert('modules', array(
			'module_name' => STORE_CLASS,
			'module_version' => $this->version,
			'has_cp_backend' => 'y',
			'has_publish_fields' => 'n'));

		// register actions
		self::register_action('act_add_to_cart');
		self::register_action('act_checkout');
		self::register_action('act_download_file');
		self::register_action('act_field_stock');
		self::register_action('act_payment');
		self::register_action('act_payment_return');

		$this->_install_sql_tables();
		$this->_install_extension();

		// install first site
		$site_id = $this->EE->config->item('site_id');
		$this->EE->load->model('store_common_model');
		$this->EE->store_common_model->install_site($site_id);
		$this->EE->store_common_model->install_templates($site_id);

		return TRUE;
	}

	protected function _install_sql_tables()
	{
		// carts table
		$this->EE->dbforge->add_field(array(
			'cart_id'				=> array('type' => 'varchar', 'constraint' => 32, 'null' => FALSE),
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'date'					=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'ip_address'			=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
			'contents'				=> array('type' => 'mediumtext', 'null' => FALSE)));

		$this->EE->dbforge->add_key('cart_id', TRUE);
		$this->EE->dbforge->create_table('store_carts');
		self::create_index('store_carts', 'site_id');
		self::create_index('store_carts', 'date');

		// config table
		$this->EE->dbforge->add_field(array(
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'store_preferences'		=> array('type' => 'text', 'null' => TRUE),
		));

		$this->EE->dbforge->add_key('site_id', TRUE);
		$this->EE->dbforge->create_table('store_config');

		// countries table
		$this->EE->dbforge->add_field(array(
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'country_code'			=> array('type' => 'char', 'constraint' => 2, 'null' => FALSE)));

		$this->EE->dbforge->add_key('site_id', TRUE);
		$this->EE->dbforge->add_key('country_code', TRUE);
		$this->EE->dbforge->create_table('store_countries');

		// email_templates table
		$this->EE->dbforge->add_field(array(
			'template_id'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'name'					=> array('type' => 'varchar', 'constraint' => 30, 'null' => FALSE),
			'subject'				=> array('type' => 'varchar', 'constraint' => 100, 'null' => FALSE),
			'contents'				=> array('type' => 'text', 'null' => FALSE),
			'bcc'					=> array('type' => 'varchar', 'constraint' => 255, 'null' => TRUE),
			'mail_format'			=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
			'word_wrap'				=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'enabled'				=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE)));

		$this->EE->dbforge->add_key('template_id', TRUE);
		$this->EE->dbforge->create_table('store_email_templates');
		self::create_index('store_email_templates', 'site_id');

		// orders table
		$this->EE->dbforge->add_field(array(
			'order_id'					=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'					=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'order_hash'				=> array('type' => 'varchar', 'constraint' => 32, 'null' => FALSE),
			'member_id'					=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'order_date'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'order_completed_date'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'ip_address'				=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
			'order_status'				=> array('type' => 'varchar', 'constraint' => 20, 'null' => TRUE),
			'order_status_updated'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'order_status_member'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
			'order_qty'					=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE),
			'order_subtotal'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_subtotal_tax'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_discount'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_discount_tax'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_shipping'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_shipping_tax'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_handling'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_handling_tax'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_tax'					=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_total'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_paid'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'order_paid_date'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'order_email'				=> array('type' => 'varchar', 'constraint' => 100),
			'promo_code_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'promo_code'				=> array('type' => 'varchar', 'constraint' => 20, 'null' => TRUE),
			'payment_method' 			=> array('type' => 'varchar', 'constraint' => 50),
			'shipping_method'			=> array('type' => 'varchar', 'constraint' => 100),
			'shipping_method_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'shipping_method_plugin'	=> array('type' => 'varchar', 'constraint' => 50),
			'shipping_method_rule'		=> array('type' => 'varchar', 'constraint' => 50),
			'tax_id'					=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'tax_name'					=> array('type' => 'varchar', 'constraint' => 40),
			'tax_rate'					=> array('type' => 'double', 'null' => FALSE),
			'order_length'				=> array('type' => 'double', 'null' => FALSE),
			'order_width'				=> array('type' => 'double', 'null' => FALSE),
			'order_height'				=> array('type' => 'double', 'null' => FALSE),
			'dimension_units'			=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
			'order_weight'				=> array('type' => 'double', 'null' => FALSE),
			'weight_units'				=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
			'billing_name'				=> array('type' => 'varchar', 'constraint' => 255),
			'billing_address1'			=> array('type' => 'varchar', 'constraint' => 255),
			'billing_address2'			=> array('type' => 'varchar', 'constraint' => 255),
			'billing_address3'			=> array('type' => 'varchar', 'constraint' => 255),
			'billing_region'			=> array('type' => 'varchar', 'constraint' => 255),
			'billing_country'			=> array('type' => 'char', 'constraint' => 2),
			'billing_postcode'			=> array('type' => 'varchar', 'constraint' => 10),
			'billing_phone'				=> array('type' => 'varchar', 'constraint' => 15),
			'shipping_name'				=> array('type' => 'varchar', 'constraint' => 255),
			'shipping_address1'			=> array('type' => 'varchar', 'constraint' => 255),
			'shipping_address2'			=> array('type' => 'varchar', 'constraint' => 255),
			'shipping_address3'			=> array('type' => 'varchar', 'constraint' => 255),
			'shipping_region'			=> array('type' => 'varchar', 'constraint' => 255),
			'shipping_country'			=> array('type' => 'char', 'constraint' => 2),
			'shipping_postcode'			=> array('type' => 'varchar', 'constraint' => 10),
			'shipping_phone'			=> array('type' => 'varchar', 'constraint' => 15),
			'billing_same_as_shipping'	=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'shipping_same_as_billing'	=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'order_custom1'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom2'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom3'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom4'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom5'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom6'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom7'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom8'				=> array('type' => 'varchar', 'constraint' => 255),
			'order_custom9'				=> array('type' => 'varchar', 'constraint' => 255),
			'return_url'				=> array('type' => 'varchar', 'constraint' => 255),
			'cancel_url'				=> array('type' => 'varchar', 'constraint' => 255)));

		$this->EE->dbforge->add_key('order_id', TRUE);
		$this->EE->dbforge->create_table('store_orders');
		self::create_index('store_orders', 'site_id');
		self::create_index('store_orders', 'order_hash', TRUE);
		self::create_index('store_orders', 'member_id');
		self::create_index('store_orders', 'order_date');

		// order history table
		$this->EE->dbforge->add_field(array(
			'order_history_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'order_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'order_status'			=> array('type' => 'varchar', 'constraint' => 20, 'null' => FALSE),
			'order_status_updated'	=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
			'order_status_member'	=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
			'message'				=> array('type' => 'text', 'null' => TRUE)));

		$this->EE->dbforge->add_key('order_history_id', TRUE);
		$this->EE->dbforge->create_table('store_order_history');
		self::create_index('store_order_history', 'order_id');

		// order items table
		$this->EE->dbforge->add_field(array(
			'order_item_id'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'order_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'entry_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'sku'					=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
			'title'					=> array('type' => 'varchar', 'constraint' => 100, 'null' => FALSE),
			'modifiers'				=> array('type' => 'text', 'null' => TRUE),
			'price'					=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'price_inc_tax'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'regular_price'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'regular_price_inc_tax' => array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'on_sale'				=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'weight'				=> array('type' => 'double'),
			'length'				=> array('type' => 'double'),
			'width'					=> array('type' => 'double'),
			'height'				=> array('type' => 'double'),
			'handling'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'handling_tax'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'free_shipping'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'tax_exempt'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'item_qty'				=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE),
			'item_subtotal'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'item_tax'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'item_total'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE)));

		$this->EE->dbforge->add_key('order_item_id', TRUE);
		$this->EE->dbforge->create_table('store_order_items');
		self::create_index('store_order_items', 'order_id');

		// order statuses table
		$this->EE->dbforge->add_field(array(
			'order_status_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'name'					=> array('type' => 'varchar', 'constraint' => 20, 'null' => FALSE),
			'highlight'				=> array('type' => 'varchar', 'constraint' => 6, 'null' => FALSE),
			'email_template'		=> array('type' => 'varchar', 'constraint' => 30, 'null' => TRUE),
			'display_order'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE),
			'is_default'			=> array('type' => 'char', 'constraint' => 1, 'null' => TRUE)));

		$this->EE->dbforge->add_key('order_status_id', TRUE);
		$this->EE->dbforge->create_table('store_order_statuses');
		self::create_index('store_order_statuses', 'site_id');
		self::create_index('store_order_statuses', 'display_order');

		// payments table
		$this->EE->dbforge->add_field(array(
			'payment_id'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'order_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'payment_hash'			=> array('type' => 'varchar', 'constraint' => 32, 'null' => FALSE),
			'payment_status'		=> array('type' => 'varchar', 'constraint' => 20, 'null' => FALSE),
			'member_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'payment_method' 		=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE),
			'payment_method_class'	=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE),
			'amount'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'payment_date'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'reference'				=> array('type' => 'varchar', 'constraint' => 255, 'null' => TRUE),
			'message'				=> array('type' => 'text')));

		$this->EE->dbforge->add_key('payment_id', TRUE);
		$this->EE->dbforge->create_table('store_payments');
		self::create_index('store_payments', 'order_id');
		self::create_index('store_payments', 'payment_hash', TRUE);

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
		self::create_index('store_payment_methods', array('site_id', 'name'), TRUE);

		// products table
		$this->EE->dbforge->add_field(array(
			'entry_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'regular_price'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'sale_price'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => TRUE),
			'sale_price_enabled'	=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'sale_start_date'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'sale_end_date'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE),
			'weight'				=> array('type' => 'double'),
			'dimension_l'			=> array('type' => 'double'),
			'dimension_w'			=> array('type' => 'double'),
			'dimension_h'			=> array('type' => 'double'),
			'handling'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE),
			'free_shipping'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'tax_exempt'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE)));

		$this->EE->dbforge->add_key('entry_id', TRUE);
		$this->EE->dbforge->create_table('store_products');

		// product modifiers table
		$this->EE->dbforge->add_field(array(
			'product_mod_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'entry_id'				=> array('type' => 'int','constraint' => 10,'unsigned' => TRUE, 'null' => FALSE),
			'mod_type'				=> array('type' => 'varchar', 'constraint' => 20, 'null' => FALSE),
			'mod_name'				=> array('type' => 'varchar', 'constraint' => 100, 'null' => FALSE),
			'mod_instructions'		=> array('type' => 'text'),
			'mod_order'				=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE)));

		$this->EE->dbforge->add_key('product_mod_id', TRUE);
		$this->EE->dbforge->create_table('store_product_modifiers');
		self::create_index('store_product_modifiers', 'entry_id');
		self::create_index('store_product_modifiers', 'mod_order');

		// product variation options table
		$this->EE->dbforge->add_field(array(
			'product_opt_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'product_mod_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'opt_name'				=> array('type' => 'varchar', 'constraint' => 100, 'null' => FALSE),
			'opt_price_mod'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => TRUE),
			'opt_order'				=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE)));

		$this->EE->dbforge->add_key('product_opt_id', TRUE);
		$this->EE->dbforge->create_table('store_product_options');
		self::create_index('store_product_options', 'product_mod_id');
		self::create_index('store_product_options', 'opt_order');

		// promo codes table
		$this->EE->dbforge->add_field(array(
			'promo_code_id'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'description'			=> array('type' => 'varchar', 'constraint' => 100, 'null' => FALSE),
			'promo_code'			=> array('type' => 'varchar', 'constraint' => 20, 'null' => TRUE),
			'type'					=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'value'					=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => TRUE),
			'free_shipping'			=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE),
			'start_date'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'end_date'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'use_limit'				=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => TRUE),
			'use_count'				=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => TRUE),
			'per_user_limit'		=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => TRUE),
			'member_group_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => TRUE),
			'enabled'				=> array('type' => 'char', 'constraint' => 1, 'null' => FALSE)));

		$this->EE->dbforge->add_key('promo_code_id', TRUE);
		$this->EE->dbforge->create_table('store_promo_codes');
		self::create_index('store_promo_codes', 'site_id');
		self::create_index('store_promo_codes', 'promo_code');
		self::create_index('store_promo_codes', 'enabled');

		// regions table
		$this->EE->dbforge->add_field(array(
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'country_code'			=> array('type' => 'char', 'constraint' => 2),
			'region_code'			=> array('type' => 'varchar', 'constraint' => 5),
			'region_name'			=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE)));

		$this->EE->dbforge->add_key('site_id', TRUE);
		$this->EE->dbforge->add_key('country_code', TRUE);
		$this->EE->dbforge->add_key('region_code', TRUE);
		$this->EE->dbforge->create_table('store_regions');

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
		self::create_index('store_shipping_methods', 'site_id');

		// shipping rules table
		$this->EE->dbforge->add_field(array(
			'shipping_rule_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'shipping_method_id'	=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'title'					=> array('type' => 'varchar', 'constraint' => 50, 'null' => FALSE, 'default' => ''),
			'country_code'			=> array('type' => 'char', 'constraint' => 2, 'null' => FALSE, 'default' => ''),
			'region_code'			=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE, 'default' => ''),
			'postcode'				=> array('type' => 'varchar', 'constraint' => 10, 'null' => FALSE, 'default' => ''),
			'min_weight'			=> array('type' => 'double'),
			'max_weight'			=> array('type' => 'double'),
			'min_order_total'		=> array('type' => 'decimal', 'constraint' => '19,4'),
			'max_order_total'		=> array('type' => 'decimal', 'constraint' => '19,4'),
			'min_order_qty'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
			'max_order_qty'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE),
			'base_rate'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'per_item_rate'			=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'per_weight_rate'		=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'percent_rate'			=> array('type' => 'double', 'null' => FALSE, 'default' => 0),
			'min_rate'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'max_rate'				=> array('type' => 'decimal', 'constraint' => '19,4', 'null' => FALSE, 'default' => 0),
			'priority'				=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 0),
			'enabled'				=> array('type' => 'tinyint', 'constraint' => 1, 'null' => FALSE, 'default' => 0)));

		$this->EE->dbforge->add_key('shipping_rule_id', TRUE);
		$this->EE->dbforge->create_table('store_shipping_rules');
		self::create_index('store_shipping_rules', 'shipping_method_id');
		self::create_index('store_shipping_rules', 'country_code');
		self::create_index('store_shipping_rules', 'region_code');
		self::create_index('store_shipping_rules', 'postcode');
		self::create_index('store_shipping_rules', 'priority');
		self::create_index('store_shipping_rules', 'enabled');

		// stock table
		$this->EE->dbforge->add_field(array(
			'sku'					=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
			'entry_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'stock_level'			=> array('type' => 'int', 'constraint' => 4, 'null' => TRUE),
			'min_order_qty'			=> array('type' => 'int', 'constraint' => 4, 'null' => TRUE),
			'track_stock'			=> array('type' => 'char', 'constraint' => 1, 'null' => TRUE)));

		$this->EE->dbforge->add_key('sku', TRUE);
		$this->EE->dbforge->create_table('store_stock');
		self::create_index('store_stock', 'entry_id');

		// stock options table
		$this->EE->dbforge->add_field(array(
			'sku'					=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
			'product_mod_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'entry_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE),
			'product_opt_id'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'null' => FALSE)));

		$this->EE->dbforge->add_key('sku', TRUE);
		$this->EE->dbforge->add_key('product_mod_id', TRUE);
		$this->EE->dbforge->create_table('store_stock_options');
		self::create_index('store_stock_options', 'entry_id');
		self::create_index('store_stock_options', 'product_opt_id');

		// tax rates table
		$this->EE->dbforge->add_field(array(
			'tax_id'				=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'site_id'				=> array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			'tax_name'				=> array('type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
			'country_code'			=> array('type' => 'char', 'constraint' => 2, 'null' => FALSE),
			'region_code'			=> array('type' => 'varchar', 'constraint' => 5, 'null' => FALSE),
			'tax_rate'				=> array('type' => 'double', 'null' => FALSE),
			'tax_shipping'			=> array('type' => 'tinyint', 'constraint' => 1, 'null' => FALSE),
			'enabled'				=> array('type' => 'tinyint', 'constraint' => 1, 'null' => FALSE, 'default' => 0)));

		$this->EE->dbforge->add_key('tax_id', TRUE);
		$this->EE->dbforge->create_table('store_tax_rates');
		self::create_index('store_tax_rates', 'site_id');
		self::create_index('store_tax_rates', 'enabled');
	}

	protected function _install_extension()
	{
		self::register_hook('channel_entries_query_result');
		self::register_hook('cp_menu_array');
		self::register_hook('sessions_end');
		self::register_hook('member_member_logout');
	}

	public function update($current = '')
	{
		if ($this->version == $current) return FALSE;

		$this->EE->load->dbforge();

		$updates = array(
			'1.1.3',
			'1.1.4',
			'1.2.2',
			'1.2.3',
			'1.2.4',
			'1.2.5',
			'1.2.6',
			'1.3.2',
			'1.5.0',
			'1.5.3',
			'1.6.0',
			'1.6.2',
			'1.6.3',
		);

		foreach ($updates as $version)
		{
			if ($current < $version)
			{
				$this->_run_update($version);
			}
		}

		// update extension and fieldtype version numbers (doesn't happen automatically)
		$this->EE->db->where('class', STORE_CLASS.'_ext');
		$this->EE->db->update('extensions', array('version' => $this->version));

		$this->EE->db->where('name', strtolower(STORE_CLASS));
		$this->EE->db->update('fieldtypes', array('version' => $this->version));

		return TRUE;
	}

	protected function _run_update($version)
	{
		// run the update file
		$class_name = 'Store_upd_'.str_replace('.', '', $version);
		require_once(PATH_THIRD.'store/updates/'.strtolower($class_name).'.php');
		$updater = new $class_name;
		$updater->up();

		// record our progress
		$this->EE->db->where('module_name', STORE_CLASS)
			->update('modules', array('module_version' => $version));
	}

	public function uninstall()
	{
		$this->EE->load->dbforge();

		$this->EE->dbforge->drop_table('store_carts');
		$this->EE->dbforge->drop_table('store_config');
		$this->EE->dbforge->drop_table('store_countries');
		$this->EE->dbforge->drop_table('store_email_templates');
		$this->EE->dbforge->drop_table('store_orders');
		$this->EE->dbforge->drop_table('store_order_history');
		$this->EE->dbforge->drop_table('store_order_items');
		$this->EE->dbforge->drop_table('store_order_statuses');
		$this->EE->dbforge->drop_table('store_payments');
		$this->EE->dbforge->drop_table('store_payment_methods');
		$this->EE->dbforge->drop_table('store_plugins');
		$this->EE->dbforge->drop_table('store_products');
		$this->EE->dbforge->drop_table('store_product_modifiers');
		$this->EE->dbforge->drop_table('store_product_options');
		$this->EE->dbforge->drop_table('store_promo_codes');
		$this->EE->dbforge->drop_table('store_regions');
		$this->EE->dbforge->drop_table('store_shipping_methods');
		$this->EE->dbforge->drop_table('store_shipping_rules');
		$this->EE->dbforge->drop_table('store_stock');
		$this->EE->dbforge->drop_table('store_stock_options');
		$this->EE->dbforge->drop_table('store_tax_rates');

		$this->EE->db->where('class', STORE_CLASS);
		$this->EE->db->delete('actions');

		$this->EE->db->where('module_name', STORE_CLASS);
		$this->EE->db->delete('modules');

		$this->EE->db->where('class', STORE_CLASS.'_ext');
		$this->EE->db->delete('extensions');

		return TRUE;
	}
}

/* End of file upd.store.php */