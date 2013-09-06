<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_emails {

	function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->model('store_common_model');
	}

	function send_email($template_name, $order_id)
	{
		$this->EE->load->library(array('email', 'template'));
		$this->EE->load->helper('text');

		$order = $this->EE->store_orders_model->find_by_id($order_id);
		if (empty($order['order_email'])) return;

		$order_statuses = $this->EE->store_orders_model->get_order_status_history($order['order_id']);

		// make order status message available in email template
		$order['order_status_message'] = $order_statuses[0]['message'];

		$email = $this->EE->store_common_model->get_email_template_by_name($template_name);

		if ( ! empty($email) AND $email['enabled'] == 'y')
		{
			$email = $this->parse_email($email, array($order));

			$this->EE->email->EE_initialize();
			$this->EE->email->to($order['order_email']);
			$this->EE->email->wordwrap = $email['word_wrap'];
			$this->EE->email->mailtype = $email['mail_format'];

			if ($this->EE->store_config->item('from_email'))
			{
				$this->EE->email->from($this->EE->store_config->item('from_email'), $this->EE->store_config->item('from_name'));
			}
			else
			{
				$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
			}

			if ( ! empty($email['bcc']))
			{
				$this->EE->email->bcc($email['bcc']);
			}

			$this->EE->email->subject(html_entity_decode($email['subject']));
			$this->EE->email->message($email['contents']);
			$this->EE->email->send();
		}
	}

	public function parse_email($email, $tag_vars)
	{
		// back up existing TMPL class
		$OLD_TMPL = isset($this->EE->TMPL) ? $this->EE->TMPL : NULL;
		$this->EE->TMPL = new EE_Template();

		// parse simple order variables
		$email['subject'] = $this->EE->template->parse_variables($email['subject'], $tag_vars);
		$email['contents'] = $this->EE->TMPL->parse_variables($email['contents'], $tag_vars);

		// pretty lame that we need to manually load snippets
		$snippets = $this->EE->store_common_model->get_snippets();
		$this->EE->config->_global_vars = array_merge($this->EE->config->_global_vars, $snippets);

		// parse email contents as complete template
		$this->EE->TMPL->parse($email['contents']);
		$email['contents'] = $this->EE->TMPL->parse_globals($this->EE->TMPL->final_template);

		// restore old TMPL class
		$this->EE->TMPL = $OLD_TMPL;

		return $email;
	}
}

/* End of file ./libraries/store_emails.php */