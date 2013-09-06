<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_payments_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();
	}

	public function find_payment_method_by_id($payment_method_id)
	{
		$row = $this->db->where('payment_method_id', $payment_method_id)
			->where('site_id', $this->config->item('site_id'))
			->get('store_payment_methods')->row_array();

		if (empty($row)) return FALSE;

		return $this->_process_payment_method($row);
	}

	public function find_payment_method_by_name($name)
	{
		$payment_methods = $this->find_all_payment_methods();
		return isset($payment_methods[$name]) ? $payment_methods[$name] : FALSE;
	}

	/**
	 * Lazy load and cache all payment methods, indexed by name
	 */
	public function find_all_payment_methods()
	{
		static $payment_methods = array();

		if (empty($payment_methods))
		{
			$result = $this->db->where('site_id', $this->config->item('site_id'))
				->get('store_payment_methods')->result_array();

			foreach ($result as $row)
			{
				$payment_methods[$row['name']] = $this->_process_payment_method($row);
			}
		}

		return $payment_methods;
	}

	public function enabled_payment_methods_select($selected_name)
	{
		$payment_methods = $this->find_all_payment_methods();
		$options = '';
		foreach ($payment_methods as $payment_method)
		{
			if ($payment_method['enabled'])
			{
				$selected = $payment_method['name'] == $selected_name ? 'selected="selected"' : '';
				$options .= "<option value='{$payment_method['name']}' {$selected}>{$payment_method['title']}</option>";
			}
		}
		return $options;
	}

	protected function _process_payment_method($payment_method)
	{
		$payment_method['class_name'] = lang(strtolower($payment_method['class']));
		if (empty($payment_method['title']))
		{
			$payment_method['title'] = $payment_method['class_name'];
		}

		$payment_method['settings'] = unserialize(base64_decode($payment_method['settings']));
		return $payment_method;
	}

	public function insert_payment_method($data)
	{
		unset($data['payment_method_id']);
		$data['site_id'] = $this->config->item('site_id');
		$this->db->insert('store_payment_methods', $data);
	}

	public function update_payment_method($payment_method_id, $data)
	{
		unset($data['payment_method_id']);
		unset($data['site_id']);
		unset($data['class']);
		$this->db->where('payment_method_id', $payment_method_id)
			->where('site_id', $this->config->item('site_id'))
			->update('store_payment_methods', $data);
	}

	public function enable_payment_methods($payment_method_ids)
	{
		$this->db->where_in('payment_method_id', $payment_method_ids)
			->where('site_id', $this->config->item('site_id'))
			->update('store_payment_methods', array('enabled' => 1));
	}

	public function disable_payment_methods($payment_method_ids)
	{
		$this->db->where_in('payment_method_id', $payment_method_ids)
			->where('site_id', $this->config->item('site_id'))
			->update('store_payment_methods', array('enabled' => 0));
	}

	public function delete_payment_methods($payment_method_ids)
	{
		$this->db->where_in('payment_method_id', $payment_method_ids)
			->where('site_id', $this->config->item('site_id'))
			->delete('store_payment_methods');
	}
}

/* End of file ./models/store_payments_model.php */