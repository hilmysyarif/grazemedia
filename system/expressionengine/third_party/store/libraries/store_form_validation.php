<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

// requires EE_Form_validation class
get_instance()->load->library('form_validation');

class Store_form_validation extends EE_Form_validation
{
	public function __construct($rules = array())
	{
		parent::__construct($rules);
		$this->CI =& get_instance();
		$this->EE =& $this->CI;

		// overwrite EE form validation library
		$this->EE->form_validation =& $this;
	}

	public function error_array()
	{
		return $this->_error_array;
	}

	/**
	 * Awesome function to manually add an error to the form
	 */
	public function add_error($field, $message)
	{
		// make sure we have data for this field
		if (empty($this->_field_data[$field]))
		{
			$this->set_rules($field, "lang:$field", '');
		}

		$this->_field_data[$field]['error'] = $message;
		$this->_error_array[$field] = $message;
	}

	/**
	 * Add validation rules instead of overwriting them
	 */
	public function add_rules($field, $label = '', $rules = '')
	{
		// are there any existing rules for this field?
		if ( ! empty($this->_field_data[$field]['rules']))
		{
			$rules = trim($this->_field_data[$field]['rules'].'|'.$rules, '|');
		}

		$this->set_rules($field, $label, $rules);
	}

	public function store_currency_non_zero($str)
	{
		return store_round_currency(store_parse_currency($str), TRUE) != 0;
	}

	public function unique_payment_method_name($name)
	{
		if (empty($name)) return TRUE;

		return $this->EE->store_payments_model->find_payment_method_by_name($name) ? FALSE : TRUE;
	}

	public function valid_payment_method($name)
	{
		if (empty($name)) return TRUE;

		$payment_method = $this->EE->store_payments_model->find_payment_method_by_name($name);
		return empty($payment_method['enabled']) ? FALSE : TRUE;
	}

	public function valid_promo_code($promo_code)
	{
		$promo_code = (string)$promo_code;
		if ($promo_code == '') return TRUE;

		$promo_code_data = $this->EE->store_common_model->get_promo_code_by_code($promo_code, TRUE);
		$promo_code_error = $this->EE->store_common_model->validate_promo_code($promo_code_data);
		if (empty($promo_code_error)) return TRUE;

		$this->set_message('valid_promo_code', $promo_code_error);
		return FALSE;
	}

	public function require_accept_terms($str)
	{
		return ( ! empty($str) AND substr(strtolower($str), 0, 1) != 'n');
	}
}

/* End of file ./libraries/store_form_validation.php */