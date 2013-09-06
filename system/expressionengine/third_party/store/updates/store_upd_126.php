<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_126
{
	/**
	 * Add indexes to frequently used columns for better performance
	 */
	public function up()
	{
		Store_upd::drop_index('store_carts', 'cart_hash');
		Store_upd::create_index('store_carts', 'site_id');
		Store_upd::create_index('store_carts', 'cart_hash', TRUE);
		Store_upd::create_index('store_carts', 'date');
		Store_upd::create_index('store_email_templates', 'site_id');
		Store_upd::create_index('store_orders', 'site_id');
		Store_upd::create_index('store_orders', 'order_hash', TRUE);
		Store_upd::create_index('store_orders', 'member_id');
		Store_upd::create_index('store_orders', 'order_date');
		Store_upd::create_index('store_order_history', 'order_id');
		Store_upd::create_index('store_order_items', 'order_id');
		Store_upd::create_index('store_order_statuses', 'site_id');
		Store_upd::create_index('store_order_statuses', 'display_order');
		Store_upd::create_index('store_payments', 'order_id');
		Store_upd::create_index('store_plugins', 'site_id');
		Store_upd::create_index('store_plugins', 'plugin_type');
		Store_upd::create_index('store_plugins', 'plugin_name');
		Store_upd::create_index('store_plugins', 'display_order');
		Store_upd::create_index('store_plugins', 'enabled');
		Store_upd::create_index('store_product_modifiers', 'entry_id');
		Store_upd::create_index('store_product_modifiers', 'mod_order');
		Store_upd::create_index('store_product_options', 'product_mod_id');
		Store_upd::create_index('store_product_options', 'opt_order');
		Store_upd::create_index('store_promo_codes', 'site_id');
		Store_upd::create_index('store_promo_codes', 'promo_code');
		Store_upd::create_index('store_promo_codes', 'enabled');
		Store_upd::create_index('store_shipping_rules', 'plugin_instance_id');
		Store_upd::create_index('store_shipping_rules', 'country_code');
		Store_upd::create_index('store_shipping_rules', 'region_code');
		Store_upd::create_index('store_shipping_rules', 'postcode');
		Store_upd::create_index('store_shipping_rules', 'priority');
		Store_upd::create_index('store_shipping_rules', 'enabled');
		Store_upd::create_index('store_stock', 'entry_id');
		Store_upd::create_index('store_stock_options', 'entry_id');
		Store_upd::create_index('store_stock_options', 'product_opt_id');
		Store_upd::create_index('store_tax_rates', 'site_id');
		Store_upd::create_index('store_tax_rates', 'enabled');
	}
}
