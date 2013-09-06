<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_payments {

	private $EE;

	public function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->library('store_config');
		$this->EE->load->model(array('store_orders_model', 'store_payments_model'));

		$this->EE->load->add_package_path(PATH_THIRD.'store/ci-merchant', TRUE);
		$this->EE->load->library('merchant');

		// load the merchant language file (the hard way)
		$this->EE->lang->load('merchant', $this->EE->lang->user_lang, FALSE, TRUE, PATH_THIRD.'store/ci-merchant/', TRUE);
	}

	public function load($payment_method_name)
	{
		$payment_method = $this->EE->store_payments_model->find_payment_method_by_name($payment_method_name);
		if (empty($payment_method['enabled']))
		{
			return FALSE;
		}

		if ( ! $this->EE->merchant->load($payment_method['class']))
		{
			return FALSE;
		}

		$this->EE->merchant->initialize($payment_method['settings']);

		return TRUE;
	}

	/**
	 * Process payment for an order.
	 * The payment method will be loaded from $order['payment_method']
	 *
	 * @param array $order
	 * @param array $form_data
	 */
	public function process($order, $form_data)
	{
		$this->return_if_already_paid($order);

		if ($this->load($order['payment_method']))
		{
			// create new payment
			$payment = $this->EE->store_orders_model->new_payment($order);

			// process payment
			$params = $this->_get_payment_params($order, $payment, $form_data);

			if ($this->EE->merchant->active_driver() == 'manual')
			{
				// force authorize for manual payments
				$purchase_method = 'authorize';
			}
			elseif ($this->EE->store_config->item('cc_payment_method') == 'authorize' AND
					$this->EE->merchant->can_authorize())
			{
				$purchase_method = 'authorize';
			}
			else
			{
				$purchase_method = 'purchase';
			}

			$result = $this->EE->merchant->$purchase_method($params);
		}
		else
		{
			$result = new Merchant_response(Merchant_response::FAILED, lang('invalid_payment_method'));
		}

		$this->_update_payment_and_return($order, $payment, $result);
	}

	/**
	 * Process return from an external payment gateway for an order.
	 * The payment method will be loaded from $payment['payment_method'].
	 *
	 * @param array $order
	 * @param array $payment
	 */
	public function process_return($order, $payment)
	{
		// people shouldn't be returning payments from non-existant gateways
		if ($this->load($order['payment_method']) AND $this->EE->merchant->can_return())
		{
			$params = $this->_get_payment_params($order, $payment);

			if ($this->EE->store_config->item('cc_payment_method') == 'authorize' AND
				$this->EE->merchant->can_authorize())
			{
				$result = $this->EE->merchant->authorize_return($params);
			}
			else
			{
				$result = $this->EE->merchant->purchase_return($params);
			}

			// we only update payment for successful responses (otherwise we might
			// overwrite a successful payment simply because we got an invalid IPN)
			if ($result->success())
			{
				$order = $this->EE->store_orders_model->update_payment($order, $payment, $result);

				// exceptions are a pain...
				switch ($this->EE->merchant->active_driver())
				{
					case 'authorize_net_sim':
					case 'realex_redirect':
					case 'worldpay':
						$this->post_to_return_url($order);
						break;
					case 'sagepay_server':
						$return_url = $this->EE->store_orders_model->get_order_return_url($order);
						$this->EE->merchant->confirm_return($return_url);
						break;
					default:
						$this->redirect_to_return_url($order);
				}
			}

			if ($this->EE->merchant->active_driver() == 'sagepay_server')
			{
				$this->EE->merchant->confirm_return($order['cancel_url']);
			}

			$this->EE->session->set_flashdata('store_payment_error', $result->message());
			$this->redirect_to_cancel_url($order);
		}

		show_error(lang('invalid_payment_method'));
	}

	public function capture($order, $payment)
	{
		if ($payment['payment_status'] != 'authorized')
		{
			return FALSE;
		}

		return $this->_do_capture_or_refund('capture', $order, $payment);
	}

	public function refund($order, $payment)
	{
		if ($payment['payment_status'] != 'complete')
		{
			return FALSE;
		}

		return $this->_do_capture_or_refund('refund', $order, $payment);
	}

	protected function _do_capture_or_refund($method, $order, $payment)
	{
		if ($this->load($payment['payment_method']) AND $this->EE->merchant->{"can_$method"}())
		{
			// existing payment details
			$params = $this->_get_basic_params($order, $payment, array());

			$result = $this->EE->merchant->$method($params);

			// we only update the payment status if capture/refund was successful
			if ($result->success())
			{
				$this->EE->store_orders_model->update_payment($order, $payment, $result);
			}

			return $result;
		}

		return FALSE;
	}

	protected function _get_basic_params($order, $payment, $params)
	{
		$params['amount'] = $payment['amount'];
		$params['currency'] = $this->EE->store_config->item('currency_code');
		$params['order_id'] = $order['order_id'];
		$params['description'] = lang('order').' #'.$order['order_id'];
		$params['transaction_id'] = $payment['payment_id'];
		$params['transaction_hash'] = $payment['payment_hash'];
		$params['reference'] = $payment['reference'];

		return $params;
	}

	protected function _get_payment_params($order, $payment, $form_data = NULL)
	{
		// include any submitted form data in params
		$params = is_array($form_data) ? $form_data : array();

		$params = $this->_get_basic_params($order, $payment, $params);

		// return URLs
		$params['return_url'] = $this->EE->store_common_model->get_action_url('act_payment_return').'&H='.$payment['payment_hash'];
		$params['cancel_url'] = $order['cancel_url'];

		// some gateways can POST ACT and payment hash back to us
		$params['return_post_data'] = array(
			'ACT' => $this->EE->store_common_model->get_action_id('act_payment_return'),
			'H' => $payment['payment_hash'],
		);

		// DPS won't accept a return URL with query string in it
		$active_driver = $this->EE->merchant->active_driver();
		$no_query_string_drivers = array('dps_pxpay', 'secure_hosting');
		if (in_array($active_driver, $no_query_string_drivers))
		{
			$params['return_url'] = $this->EE->functions->create_url('payment_return/'.$payment['payment_hash']).'/';
		}

		foreach (array(
			'card_name' => 'billing_name',
			'address1' => 'billing_address1',
			'address2' => 'billing_address2',
			'city' => 'billing_address3',
			'region' => 'billing_region',
			'country' => 'billing_country',
			'postcode' => 'billing_postcode',
			'phone' => 'billing_phone',
			'email' => 'order_email',
			// these are available in case someone wants to use them in a custom gateway
			'custom1' => 'order_custom1',
			'custom2' => 'order_custom2',
			'custom3' => 'order_custom3',
			'custom4' => 'order_custom4',
			'custom5' => 'order_custom5',
			'custom6' => 'order_custom6',
			'custom7' => 'order_custom7',
			'custom8' => 'order_custom8',
			'custom9' => 'order_custom9',
			) as $key => $order_field)
		{
			if ( ! isset($params[$key]))
			{
				$params[$key] = $order[$order_field];
			}
		}

		// API not finalised - please don't rely on this data format
		$params['items'] = array();
		foreach ($order['items'] as $item)
		{
			$params['items'][] = array(
				'name' => $item['title'],
				'sku' => $item['sku'],
				'price' => $item['price_inc_tax_val'],
				'qty' => $item['item_qty'],
				'tax_exempt' => $item['tax_exempt'],
			);
		}
		$params['tax_rate'] = $order['tax_rate'];
		$params['shipping_name'] = $order['shipping_method'];
		$params['shipping_price'] = $order['order_shipping_inc_tax_val'];

		return $params;
	}

	protected function _update_payment_and_return($order, $payment, $result)
	{
		$order = $this->EE->store_orders_model->update_payment($order, $payment, $result);

		if ($result->is_redirect())
		{
			// payment requires redirect to gateway for further processing
			$result->redirect();
		}
		elseif ($result->success())
		{
			$this->redirect_to_return_url($order);
		}
		else
		{
			$this->EE->session->set_flashdata('store_payment_error', $result->message());
			$this->redirect_to_cancel_url($order);
		}
	}

	/**
	 * If the order has already been paid, we don't want to accept any more payments
	 */
	public function return_if_already_paid($order)
	{
		// check order has not already been paid
		if ($order['is_order_paid'])
		{
			$this->EE->session->set_flashdata('store_payment_error', lang('order_already_paid'));
			$this->redirect_to_return_url($order);
		}
	}

	/**
	 * Send the user back to the return_url stored with this order
	 */
	public function redirect_to_return_url($order)
	{
		$return_url = $this->EE->store_orders_model->get_order_return_url($order);
		$this->EE->functions->redirect($return_url);
	}

	/**
	 * Send the user back to the cancel_url stored with this order
	 */
	public function redirect_to_cancel_url($order)
	{
		$this->EE->functions->redirect($order['cancel_url']);
	}

	/**
	 * Authorize.net calls our return page directly and then serves it up to the user.
	 * This is a pain because we can't get or set cookies for them, so instead we redirect
	 * them back to our site using handy Javascript
	 */
	public function post_to_return_url($order)
	{
		$return_url = $this->EE->store_orders_model->get_order_return_url($order);
		?>
<html>
<head>
	<meta http-equiv="refresh" content="1;URL=<?php echo htmlspecialchars($return_url); ?>" />
	<title>Redirecting...</title>
</head>
<body onload="document.payment.submit();">
	<p>Please wait while we redirect you back to <?php echo htmlspecialchars($this->EE->config->item('site_name')); ?>...</p>
	<form name="payment" action="<?php echo htmlspecialchars($return_url); ?>" method="post">
		<p>
			<input type="submit" value="Continue" />
		</p>
	</form>
</body>
</html>
<?php
		exit();
	}

	public function get_exp_month_options()
	{
		$out = '';
		for ($i = 1; $i <= 12; $i++)
		{
			$out .= '<option value="'.sprintf('%02d', $i).'">'.sprintf('%02d', $i).'</option>';
		}
		return $out;
	}

	public function get_exp_year_options()
	{
		$out = '';
		for ($i = date('Y'); $i <= (date('Y') + 9); $i++)
		{
			$out .= '<option value="'.$i.'">'.$i.'</option>';
		}
		return $out;
	}

	/**
	 * Get the issuer options from the active iDEAL account. These will be cached for 24 hours,
	 * to speed up page loads, as recommended by the iDEAL documentation.
	 * This method should only be called if {ideal_issuer_options} is used in the template,
	 * to improve performance for non-iDEAL users.
	 */
	public function ideal_issuer_options()
	{
		if ( ! $this->load('ideal'))
		{
			return '<option value="">iDEAL gateway not enabled!</option>';
		}

		// breaks cache if settings are updated
		$cache_key = 'ideal-issuers-'.md5(serialize($this->EE->merchant->settings())).'.txt';

		$out = $this->EE->store_config->read_cache($cache_key);

		if (empty($out))
		{
			// cache doesn't exist or is out of date, hit server
			try
			{
				$response = $this->EE->merchant->issuers();
			}
			catch (Merchant_exception $e)
			{
				return '<option value="">'.htmlspecialchars($e->getMessage()).'</option>';
			}

			// create issuers array
			$out = '<option value="">'.lang('ideal_choose_bank')."</option>\n";
			$other_banks = '';

			foreach ($response->Directory->Issuer as $issuer)
			{
				if ((string)$issuer->issuerList == 'Short')
				{
					$out .= '<option value="'.htmlspecialchars((string)$issuer->issuerID).'">'.
						htmlspecialchars((string)$issuer->issuerName)."</option>\n";
				}
				else
				{
					$other_banks .= '<option value="'.htmlspecialchars((string)$issuer->issuerID).'">'.
						htmlspecialchars((string)$issuer->issuerName)."</option>\n";
				}
			}

			if ($other_banks)
			{
				$out .= '<option value="">'.lang('ideal_other_banks')."</option>\n".$other_banks;
			}

			$cache_expiry = $this->EE->localize->now + 86400; // 24 hours
			$this->EE->store_config->write_cache($cache_key, $out, $cache_expiry);
		}

		return $out;
	}
	/**
	 * Get the issuer options from the active Mollie account. These will be cached for 24 hours.
	 * This method should only be called if {mollie_issuer_options} is used in the template.
	 */
	public function mollie_issuer_options()
	{
		if ( ! $this->load('mollie'))
		{
			return '<option value="">Mollie gateway not enabled!</option>';
		}

		// breaks cache if settings are updated
		$cache_key = 'mollie-issuers-'.md5(serialize($this->EE->merchant->settings())).'.txt';

		$out = $this->EE->store_config->read_cache($cache_key);

		if (empty($out))
		{
			// cache doesn't exist or is out of date, hit server
			try
			{
				$response = $this->EE->merchant->issuers();
			}
			catch (Merchant_exception $e)
			{
				return '<option value="">'.htmlspecialchars($e->getMessage()).'</option>';
			}

			// create issuers array
			$out = '<option value="">'.lang('ideal_choose_bank')."</option>\n";

			foreach ($response->bank as $bank)
			{
				$out .= '<option value="'.htmlspecialchars((string)$bank->bank_id).'">'.
					htmlspecialchars((string)$bank->bank_name)."</option>\n";
			}

			$cache_expiry = $this->EE->localize->now + 86400; // 24 hours
			$this->EE->store_config->write_cache($cache_key, $out, $cache_expiry);
		}

		return $out;
	}
}

/* End of file ./libraries/store_payments.php */