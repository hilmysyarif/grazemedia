<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * CI-Merchant Library
 *
 * Copyright (c) 2011-2012 Adrian Macneil
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Merchant CardSave Class
 *
 * Payment processing using CardSave
 * Documentation: http://www.cardsave.net/dev-downloads
 */

class Merchant_cardsave_redirect_hosted extends Merchant_driver
{
	const PROCESS_URL = 'https://mms.cardsaveonlinepayments.com/Pages/PublicPages/PaymentForm.aspx';

	public function default_settings()
	{
		return array(
			'merchant_id' => '',
			'password' => '',
			'preshared_key' => '',
		);
	}

	public function purchase()
	{
		$request = $this->_build_authorize_or_purchase('SALE');
		//$response = $this->_post_cardsave_request($request);
		//return new Merchant_cardsave_response($response);
		$this->post_redirect(self::PROCESS_URL, $request);
	}

	public function purchase_return()
	{
		//echo intval($this->CI->input->post('PreviousStatusCode'));
		echo '<pre>';
		print_r($_POST);
		echo '</pre>';
		exit();
		$transauthorised = FALSE; 
		switch (intval($this->CI->input->post('StatusCode')))
		{
			// transaction authorised
			case 0:
				$transauthorised = TRUE;
				break;
			// card referred (treat as decline)
			case 4:
				$transauthorised = FALSE;
				break;
			// transaction declined
			case 5:
				$transauthorised = FALSE;
				break;
			// duplicate transaction
			case 20:
				// need to look at the previous status code to see if the
				// transaction was successful
				if (intval($this->CI->input->post('PreviousStatusCode')) == 0)
				{
					// transaction authorised
					$transauthorised = FALSE;
				}
				else
				{
					// transaction not authorised
					$transauthorised = FALSE;
				}
				break;
			// error occurred
			case 30:
				$transauthorised = FALSE;
				break;
			default:
				$transauthorised = FALSE;
				break;
		}
		echo $transauthorised;
		exit();
		if ($transauthorised == TRUE) 
		{
			$transaction_id = str_replace("AuthCode: ", "", $this->CI->input->post('Message'));	
			return new Merchant_response('complete', $this->CI->input->post('Message'), $transaction_id);
		} 
		else 
		{		
			return new Merchant_response('failed', $this->CI->input->post('Message'));
		}
	}

	private function _build_authorize_or_purchase($method)
	{
		$this->require_params('name', 'return_url');
		$TransactionDateTime = date('Y-m-d H:i:s P');
		$state = $this->param('country') == 'us' ? $this->param('region') : '';
		$order_desc = urlencode($this->param('description'));

		$request = array(
			'PreSharedKey'							=> $this->setting('preshared_key'),
			'MerchantID'							=> $this->setting('merchant_id'),
			'Password'								=> $this->setting('password'),
			'Amount'								=> $this->amount_cents(),
			'CurrencyCode'							=> $this->currency_numeric(),
			'EchoAVSCheckResult'					=> 'false',
			'EchoCV2CheckResult'					=> 'false',
			'EchoThreeDSecureAuthenticationCheckResult'	=> 'false',
			'EchoCardType'							=> 'false',
			//'AVSOverridePolicy'						=> 'false',
			//'CV2OverridePolicy'						=> 'false',
			'ThreeDSecureOverridePolicy'			=> 'true',
			'OrderID'								=> $this->param('transaction_id'),
			'TransactionType'						=> $method,
			'TransactionDateTime'					=> $TransactionDateTime,
			'CallbackURL'							=> $this->param('return_url'), 
			'OrderDescription'						=> $order_desc, 
			'CustomerName'							=> $this->param('first_name') . ' ' . $this->param('last_name'),
			'Address1'								=> $this->param('address1'),
			'Address2'								=> $this->param('address2'),
			'Address3'								=> '',
			'Address4'								=> '',
			'City'									=> $this->param('city'),
			'State'									=> $state,
			'PostCode'								=> $this->param('postcode'),
			'CountryCode'							=> 826,
			'EmailAddress'							=> '',
			'PhoneNumber'							=> '',
			'EmailAddressEditable'					=> 'false',
			'PhoneNumberEditable'					=> 'false',
			'CV2Mandatory'							=> 'false',
			'Address1Mandatory'						=> 'true',
			'CityMandatory'							=> 'true',
			'PostCodeMandatory'						=> 'true',
			'StateMandatory'						=> 'false',
			'CountryMandatory'						=> 'true',
			'ResultDeliveryMethod'					=> 'POST',
			'ServerResultURL'						=> $this->param('return_url'),  
			'PaymentFormDisplaysResult'				=> 'false',
			//'ServerResultURLCookieVariables'		=> 'false',
			//'ServerResultURLFormVariables'			=> 'false',
			//'ServerResultURLQueryStringVariables'	=> 'false',
		);
	
		$hash = '';
		$revised_request = array(); 
		$bad_keys = array("PreSharedKey", "Password");

		while (list($key, $val) = each($request)) 
		{
			//$val = urlencode(stripslashes(str_replace("\n", "\r\n", $val))); 

			if ($key != ('TransactionDateTime' || 'ServerResultURL')) 
			{
				$val = urlencode(stripslashes(str_replace("\n", "\r\n", $val)));
			} 
			else 
			{
				$val = stripslashes(str_replace("\n", "\r\n", $val)); 
			};
			$hash .= $key."=".$val.'&';

			// removing certain items from post data. 
			if (!in_array($key, $bad_keys))
			{
				$revised_request[$key] = $val; 
			}
		}
		// pop the last ampersand
		$hash = substr($hash, 0, -1);
		$revised_request['HashDigest'] = sha1($hash);
		
		return $revised_request;
	}
}

/* End of file ./libraries/merchant/drivers/merchant_cardsave.php */