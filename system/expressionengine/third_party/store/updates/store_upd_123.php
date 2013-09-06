<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_123
{
	/**
	 * Add missing promo codes to orders table
	 */
	public function up()
	{
		$this->EE = get_instance();

		$sql = 'UPDATE '.$this->EE->db->protect_identifiers('store_orders', TRUE).' o
			JOIN '.$this->EE->db->protect_identifiers('store_promo_codes', TRUE).' p
			ON p.promo_code_id = o.promo_code_id
			SET o.promo_code = p.promo_code
			WHERE o.promo_code IS NULL';
		$this->EE->db->query($sql);
	}
}
