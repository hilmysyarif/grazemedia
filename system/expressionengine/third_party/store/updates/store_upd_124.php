<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_124
{
	/**
	 * Register new add to cart action
	 */
	public function up()
	{
		Store_upd::register_action('act_add_to_cart');
	}
}
