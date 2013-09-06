<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

/**
 * Store Shipping USPS Class
 * @link https://www.usps.com/webtools/_pdf/Rate-Calculators-v1-5.pdf
 */
class Store_shipping_usps extends Store_shipping_driver
{
	const API_ENDPOINT = 'http://production.shippingapis.com/ShippingAPI.dll';

	public $remote = true;

	public function default_settings()
	{
		return array(
			'username' => '',
			'source_zip' => '',
			'service' => array('type' => 'select', 'options' => array(
				'FIRST CLASS'   => 'First Class',
				'FIRST CLASS COMMERCIAL'   => 'First Class Commercial',
				'FIRST CLASS HFP COMMERCIAL'   => 'First Class Hold For Pickup Commercial',
				'PRIORITY'   => 'Priority',
				'PRIORITY COMMERCIAL'   => 'Priority Commercial',
				'PRIORITY HFP COMMERCIAL'   => 'Priority Hold For Pickup Commercial',
				'EXPRESS'   => 'Express',
				'EXPRESS COMMERCIAL'   => 'Express Commercial',
				'EXPRESS SH'   => 'Express SH',
				'EXPRESS SH COMMERCIAL'   => 'Express SH Commercial',
				'EXPRESS HFP'   => 'Express Hold For Pickup',
				'EXPRESS HFP COMMERCIAL'   => 'Express Hold For Pickup Commercial',
				'PARCEL'   => 'Parcel',
				'MEDIA'   => 'Media',
				'LIBRARY'   => 'Library',
				'ALL'   => 'All',
				'ONLINE'   => 'Online',
			)),
			'first_class_mail_type' => array('type' => 'select', 'options' => array(
				'LETTER' => 'Letter',
				'FLAT' => 'Flat',
				'PARCEL' => 'Parcel',
				'POSTCARD' => 'Postcard',
				'PACKAGE SERVICE' => 'Package Service',
			)),
			'container' => array('type' => 'select', 'options' => array(
				'VARIABLE' => 'Variable',
				'FLAT RATE ENVELOPE' => 'Flat Rate Envelope',
				'PADDED FLAT RATE ENVELOPE' => 'Padded Flat Rate Envelope',
				'LEGAL FLAT RATE ENVELOPE' => 'Legal Flat Rate Envelope',
				'SM FLAT RATE ENVELOPE' => 'Sm Flat Rate Envelope',
				'WINDOW FLAT RATE ENVELOPE' => 'Window Flat Rate Envelope',
				'GIFT CARD FLAT RATE ENVELOPE' => 'Gift Card Flat Rate Envelope',
				'FLAT RATE BOX' => 'Flat Rate Box',
				'SM FLAT RATE BOX' => 'Sm Flat Rate Box',
				'MD FLAT RATE BOX' => 'Md Flat Rate Box',
				'LG FLAT RATE BOX' => 'Lg Flat Rate Box',
				'REGIONALRATEBOXA' => 'Regional Rate Box A',
				'REGIONALRATEBOXB' => 'Regional Rate Box B',
				'REGIONALRATEBOXC' => 'Regional Rate Box C',
				'RECTANGULAR' => 'Rectangular',
				'NONRECTANGULAR' => 'Nonrectangular',
			)),
			'size' => array('type' => 'select', 'options' => array(
				'REGULAR' => 'Regular',
				'LARGE' => 'Large',
			)),
			'machinable' => true,
		);
	}

	public function calculate_shipping($order)
	{
		// don't bother unless we at least have a country, and city or ZIP
		if (empty($order['shipping_country'])) return 0;
		if (empty($order['shipping_postcode']) AND empty($order['shipping_address3'])) return 0;

		$request = $this->_build_request($order);

		$this->EE->load->library('curl');
		$query = http_build_query(array(
			'API' => 'RateV4',
			'XML' => $request->asXML(),
		));
		$response = $this->EE->curl->simple_get(self::API_ENDPOINT.'?'.$query, NULL, $this->default_curl_options());
		if (empty($response))
		{
			return array('error:shipping_method' => $this->EE->curl->error_string);
		}

		$xml = simplexml_load_string($response);
		if ($xml->getName() == 'Error')
		{
			return array('error:shipping_method' => (string)$xml->Description);
		}

		if (isset($xml->Package->Error))
		{
			return array('error:shipping_method' => (string)$xml->Package->Error->Description);
		}

		return (float)$xml->Package->Postage->Rate;
	}

	protected function _build_request($order)
	{
		$request = new SimpleXMLElement('<RateV4Request/>');
		$request['USERID'] = $this->settings['username'];
		$request->Revision = 2;
		$request->Package[0]['ID'] = '0';
		$request->Package[0]->Service = $this->settings['service'];
		$request->Package[0]->FirstClassMailType = 'LETTER'; // TODO - only if service = first class
		$request->Package[0]->ZipOrigination = $this->settings['source_zip'];
		$request->Package[0]->ZipDestination = $order['shipping_postcode'];
		$request->Package[0]->Pounds = 0;
		$request->Package[0]->Ounces = max(1, round($order['order_shipping_weight_lb'] * 16));
		$request->Package[0]->Container = $this->settings['container'];
		$request->Package[0]->Size = $this->settings['size'];
		$request->Package[0]->Width = $order['order_shipping_width_in'];
		$request->Package[0]->Length = $order['order_shipping_length_in'];
		$request->Package[0]->Height = $order['order_shipping_height_in'];
		$request->Package[0]->Machinable = $this->settings['machinable'] ? 'true' : 'false';

		return $request;
	}
}

/* End of file ./libraries/store_shipping/store_shipping_usps.php */