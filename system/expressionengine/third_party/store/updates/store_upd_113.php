<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_113
{
	/**
	 * Update all tables to be MSM-compatible
	 */
	public function up()
	{
		$this->EE = get_instance();

		// get list of existing sites
		$sites = $this->EE->db->select('site_id')->get('sites')->result_array();
		if (empty($sites))
		{
			$sites = array(1);
		}
		else
		{
			foreach ($sites as $key => $site)
			{
				$sites[$key] = $site['site_id'];
			}
		}

		// try to determine which site Store is currently using
		$site_id = (int)$this->EE->config->item('site_id');
		$product = $this->EE->db->select('channel_titles.site_id')
			->from('store_products')
			->join('channel_titles', 'channel_titles.entry_id = store_products.entry_id')
			->limit(1)->get()->row_array();
		if ( ! empty($product))
		{
			$site_id = (int)$product['site_id'];
		}

		// add msm-compatible store config table
		if ( ! $this->EE->db->table_exists('store_config'))
		{
			$this->EE->dbforge->add_field(array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
				'store_preferences' => array('type' => 'text', 'null' => TRUE),
			));

			$this->EE->dbforge->add_key('site_id', TRUE);
			$this->EE->dbforge->create_table('store_config');

			// copy existing config to new table
			$this->EE->db->where('module_name', STORE_CLASS);
			$row = $this->EE->db->get('modules')->row_array();

			$config = array('site_id' => $site_id);
			if ( ! empty($row['settings']))
			{
				$config['store_preferences'] = $row['settings'];
			}
			$this->EE->db->insert('store_config', $config);
		}

		// add site_id to store_carts
		if ( ! $this->EE->db->field_exists('site_id', 'store_carts'))
		{
			$this->EE->dbforge->add_column('store_carts', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'cart_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_carts');

		// add site_id to store_countries
		if ( ! $this->EE->db->field_exists('site_id', 'store_countries'))
		{
			$this->EE->dbforge->add_column('store_countries', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			));
			$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_countries');
			$this->EE->db->query('ALTER TABLE '.$this->EE->db->protect_identifiers('store_countries', TRUE).
				' DROP PRIMARY KEY, ADD PRIMARY KEY(site_id,country_code);');
		}

		// add site_id to store_email_templates
		if ( ! $this->EE->db->field_exists('site_id', 'store_email_templates'))
		{
			$this->EE->dbforge->add_column('store_email_templates', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'template_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_email_templates');

		// add site_id to store_orders
		if ( ! $this->EE->db->field_exists('site_id', 'store_orders'))
		{
			$this->EE->dbforge->add_column('store_orders', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'order_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_orders');

		// add site_id to store_order_statuses
		if ( ! $this->EE->db->field_exists('site_id', 'store_order_statuses'))
		{
			$this->EE->dbforge->add_column('store_order_statuses', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'order_status_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_order_statuses');

		// add site_id to store_plugins
		if ( ! $this->EE->db->field_exists('site_id', 'store_plugins'))
		{
			$this->EE->dbforge->add_column('store_plugins', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'plugin_instance_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_plugins');

		// add site_id to store_promo_codes
		if ( ! $this->EE->db->field_exists('site_id', 'store_promo_codes'))
		{
			$this->EE->dbforge->add_column('store_promo_codes', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'promo_code_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_promo_codes');

		// add site_id to store_regions
		if ( ! $this->EE->db->field_exists('site_id', 'store_regions'))
		{
			$this->EE->dbforge->add_column('store_regions', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'country_code');
			$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_regions');
			$this->EE->db->query('ALTER TABLE '.$this->EE->db->protect_identifiers('store_regions', TRUE).
				' DROP PRIMARY KEY, ADD PRIMARY KEY(site_id,country_code,region_code);');
		}

		// add site_id to store_tax_rates
		if ( ! $this->EE->db->field_exists('site_id', 'store_tax_rates'))
		{
			$this->EE->dbforge->add_column('store_tax_rates', array(
				'site_id' => array('type' => 'int', 'constraint' => 5, 'null' => FALSE),
			), 'tax_id');
		}
		$this->EE->db->where('site_id', 0)->set('site_id', $site_id)->update('store_tax_rates');
	}
}
