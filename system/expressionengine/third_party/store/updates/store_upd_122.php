<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_122
{
	/**
	 * Add new logout hook
	 */
	public function up()
	{
		Store_upd::register_hook('member_member_logout');
	}
}
