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
		if(!isset($_POST['StatusCode'])){
			$response new Merchant_response('complete', 'Transaction Complete', '12345');
			$response->_data = $_POST;
			return $response;
		}
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
					$transauthorised = TRUE;
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
	
		if ($transauthorised == TRUE) {
			
			echo "StatusCode=0&Message="; 
			exit;
		} 
		else 
		{
			echo "StatusCode=30&Message=". $this->lang("cardsave_server_transaction_not_authorized"). " ". $post['Message']; 
			exit;
		}
 		exit;
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
			'ResultDeliveryMethod'					=> 'SERVER',
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

	private function _build_3dauth()
	{
		if (empty($_POST['MD']) OR empty($_POST['PaRes']))
		{
			throw new Merchant_exception(lang('merchant_invalid_response'));
		}

		$request = $this->_new_request('ThreeDSecureAuthentication');
		$request->ThreeDSecureMessage->MerchantAuthentication['MerchantID'] = $this->setting('merchant_id');
		$request->ThreeDSecureMessage->MerchantAuthentication['Password'] = $this->setting('password');
		$request->ThreeDSecureMessage->ThreeDSecureInputData['CrossReference'] = $this->CI->input->post('MD');
		$request->ThreeDSecureMessage->ThreeDSecureInputData->PaRES = $this->CI->input->post('PaRes');

		return $request;
	}

	private function _new_request($action)
	{
		$request = new SimpleXMLElement("<$action></$action>");
		$request->addAttribute('xmlns', 'https://www.thepaymentgateway.net/');
		return $request;
	}

	private function _post_cardsave_request($request)
	{
		// the PHP SOAP library sucks, and SimpleXML can't append element trees
		$document = new DOMDocument('1.0', 'utf-8');
		$envelope = $document->appendChild($document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soap:Envelope'));
		$envelope->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$envelope->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
		$body = $envelope->appendChild($document->createElement('soap:Body'));
		$body->appendChild($document->importNode(dom_import_simplexml($request), TRUE));

		// post to Cardsave
		$http_headers = array(
			'Content-Type: text/xml; charset=utf-8',
			'SOAPAction: https://www.thepaymentgateway.net/'.$request->getName());
		$response_str = $this->post_request(self::PROCESS_URL, $document->saveXML(), NULL, NULL, $http_headers);

		// we only care about the content of the soap:Body element
		$response_dom = DOMDocument::loadXML($response_str);
		$response = simplexml_import_dom($response_dom->documentElement->firstChild->firstChild);

		$result_elem = $request->getName().'Result';
		$status = (int)$response->$result_elem->StatusCode;
		switch ($status)
		{
			case 0:
				// success
				return $response;
			case 3:
				// redirect for 3d authentication
				$data = array(
					'PaReq' => (string)$response->TransactionOutputData->ThreeDSecureOutputData->PaREQ,
					'TermUrl' => $this->param('return_url'),
					'MD' => (string)$response->TransactionOutputData['CrossReference'],
				);

				$acs_url = (string)$response->TransactionOutputData->ThreeDSecureOutputData->ACSURL;
				$this->post_redirect($acs_url, $data, lang('merchant_3dauth_redirect'));
				break;
			default:
				// error
				throw new Merchant_exception((string)$response->$result_elem->Message);
		}
	}
}

class Merchant_cardsave_redirect_hosted_response extends Merchant_response
{
	protected $_response;

	public function __construct($response)
	{

		$this->_response = $response;
		$this->_status = $response['status'];
		$this->_reference = (string)$response->TransactionOutputData['CrossReference'];
	}
}

/* End of file ./libraries/merchant/drivers/merchant_cardsave.php */