<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_members
{
	public function __construct()
	{
		$this->EE =& get_instance();
	}

	/**
	 * Process member registration using cart data
	 */
	public function register($cart)
	{
		if ( ! class_exists('Member')) require PATH_MOD.'member/mod.member.php';
		if ( ! class_exists('Member_register')) require PATH_MOD.'member/mod.member_register.php';

		// fake POST data so we can re-use the Member_register class methods
		$old_post = $_POST;
		$_POST = array();
		$_POST['email'] = $cart['order_email'];
		$_POST['username'] = empty($cart['username']) ? $cart['order_email'] : $cart['username'];
		$_POST['screen_name'] = empty($cart['screen_name']) ? $cart['order_email'] : $cart['screen_name'];

		if (empty($cart['password'])) return;
		$_POST['password'] = $cart['password'];
		$_POST['password_confirm'] = $cart['password'];

		// fake EE_Output library to prevent rending user message
		$this->EE->old_output =& $this->EE->output;
		$new_output = new Store_members_mock_output();
		$this->EE->output =& $new_output;

		// skip some of the boring stuff
		$this->EE->config->set_item('use_membership_captcha', 'n');
		$this->EE->config->set_item('require_terms_of_service', 'n');
		$this->EE->config->set_item('secure_forms', 'n');

		// run the member registration process
		$member = new Member_register();
		$member->register_member();

		// restore EE_Output library
		$this->EE->output =& $this->EE->old_output;
		unset($this->EE->old_output);

		// restore POST data
		$_POST = $old_post;

		// find new member id
		$member_id = (int)$this->EE->db->select('member_id')
			->where('email', $cart['order_email'])
			->get('members')->row('member_id');

		// assign existing orders from this email address to new member account
		$this->EE->db->where('order_email', $cart['order_email'])
			->where('member_id = 0 OR member_id IS NULL')
			->update('store_orders', array('member_id' => $member_id));

		return $member_id;
	}
}

/**
 * Temporarily hijack EE_Output class to prevent it rendering user messages
 */
class Store_members_mock_output extends EE_Output
{
	/**
	 * Stub show_message() function
	 */
	public function show_message() {}

	/**
	 * We still want show_user_error to call the real show_message function
	 */
	public function show_user_error($type = 'submission', $errors, $heading = '')
	{
		get_instance()->old_output->show_user_error($type, $errors, $heading);
	}
}
