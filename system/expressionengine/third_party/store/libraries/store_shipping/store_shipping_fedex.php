<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_shipping_fedex extends Store_shipping_driver
{
	const LIVE_ENDPOINT = 'https://ws.fedex.com/xml/';
	const TEST_ENDPOINT = 'https://wsbeta.fedex.com/xml/';

	const XML_NAMESPACE = 'http://fedex.com/ws/rate/v10';

	public $remote = true;

	public function default_settings()
	{
		return array(
			'api_key' => '',
			'password' => '',
			'account_no' => '',
			'meter_no' => '',
			'dropoff_type' => array('type' => 'select', 'default' => 'REGULAR_PICKUP', 'options' => array(
				'BUSINESS_SERVICE_CENTER' => 'Business Service Center',
				'DROP_BOX' => 'Drop Box',
				'REGULAR_PICKUP' => 'Regular Pickup',
				'REQUEST_COURIER' => 'Request Courier',
				'STATION' => 'Station')),
			'service_type' => array('type' => 'select', 'default' => 'FEDEX_GROUND', 'options' => array(
				'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'Europe First International Priority',
				'FEDEX_1_DAY_FREIGHT' => 'FedEx 1 Day Freight',
				'FEDEX_2_DAY' => 'FedEx 2 Day',
				'FEDEX_2_DAY_AM' => 'FedEx 2 Day AM',
				'FEDEX_2_DAY_FREIGHT' => 'FedEx 2 Day Freight',
				'FEDEX_3_DAY_FREIGHT' => 'FedEx 3 Day Freight',
				'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
				'FEDEX_FIRST_FREIGHT' => 'FedEx First Freight',
				'FEDEX_FREIGHT_ECONOMY' => 'FedEx Freight Economy',
				'FEDEX_FREIGHT_PRIORITY' => 'FedEx Freight Priority',
				'FEDEX_GROUND' => 'FedEx Ground',
				'FIRST_OVERNIGHT' => 'Overnight',
				'GROUND_HOME_DELIVERY' => 'Ground Home Delivery',
				'INTERNATIONAL_ECONOMY' => 'International Economy',
				'INTERNATIONAL_ECONOMY_FREIGHT' => 'International Economy Freight',
				'INTERNATIONAL_FIRST' => 'International First',
				'INTERNATIONAL_PRIORITY' => 'International Priority',
				'INTERNATIONAL_PRIORITY_FREIGHT' => 'International Priority Freight',
				'PRIORITY_OVERNIGHT' => 'Priority Overnight',
				'SMART_POST' => 'Smart Post',
				'STANDARD_OVERNIGHT' => 'Standard Overnight')),
			'packaging_type' => array('type' => 'select', 'default' => 'YOUR_PACKAGING', 'options' => array(
				'FEDEX_10KG_BOX' => 'FedEx 10kg Box',
				'FEDEX_25KG_BOX' => 'FedEx 25kg Box',
				'FEDEX_BOX' => 'FedEx Box',
				'FEDEX_ENVELOPE' => 'FedEx Envelope',
				'FEDEX_PAK' => 'FedEx Pak',
				'FEDEX_TUBE' => 'FedEx Tube',
				'YOUR_PACKAGING' => 'Your Packaging')),
			'source_city' => '',
			'source_zip' => '',
			'source_country' => array(
				'type' => 'select',
				'default' => 'us',
				'options' => $this->EE->store_shipping_model->countries),
			'residential_delivery' => TRUE,
			'test_mode' => FALSE,
		);
	}

	public function calculate_shipping($order)
	{
		// don't bother unless we at least have a country, and city or ZIP
		if (empty($order['shipping_country'])) return 0;
		if (empty($order['shipping_postcode']) AND empty($order['shipping_address3'])) return 0;

		$request = $this->_build_request($order);

		$this->EE->load->library('curl');
		$response = $this->EE->curl->simple_post($this->_endpoint(), $request->asXML(), $this->default_curl_options());
		if (empty($response))
		{
			return array('error:shipping_method' => $this->EE->curl->error_string);
		}

		return $this->_parse_response($response);
	}

	private function _build_request($order)
	{
		$xml = new SimpleXMLElement('<RateRequest xmlns="'.self::XML_NAMESPACE.'" />');
		$xml->WebAuthenticationDetail->UserCredential->Key = $this->settings['api_key'];
		$xml->WebAuthenticationDetail->UserCredential->Password = $this->settings['password'];
		$xml->ClientDetail->AccountNumber = $this->settings['account_no'];
		$xml->ClientDetail->MeterNumber = $this->settings['meter_no'];
		$xml->Version->ServiceId = 'crs';
		$xml->Version->Major = 10;
		$xml->Version->Intermediate = 0;
		$xml->Version->Minor = 0;
		$xml->RequestedShipment->DropoffType = $this->settings['dropoff_type'];
		$xml->RequestedShipment->ServiceType = $this->settings['service_type'];
		$xml->RequestedShipment->PackagingType = $this->settings['packaging_type'];
		$xml->RequestedShipment->PreferredCurrency = $this->EE->store_config->item('currency_code');
		$xml->RequestedShipment->Shipper->Address->City = $this->settings['source_city'];;
		$xml->RequestedShipment->Shipper->Address->PostalCode = $this->settings['source_zip'];
		$xml->RequestedShipment->Shipper->Address->CountryCode = strtoupper($this->settings['source_country']);
		$xml->RequestedShipment->Recipient->Address->StreetLines[] = $order['shipping_address1'];
		$xml->RequestedShipment->Recipient->Address->StreetLines[] = $order['shipping_address2'];
		$xml->RequestedShipment->Recipient->Address->City = $order['shipping_address3'];
		$xml->RequestedShipment->Recipient->Address->StateOrProvinceCode = $order['shipping_region'];
		$xml->RequestedShipment->Recipient->Address->PostalCode = $order['shipping_postcode'];
		$xml->RequestedShipment->Recipient->Address->CountryCode = strtoupper($order['shipping_country']);

		if ($this->settings['residential_delivery'])
		{
			$xml->RequestedShipment->Recipient->Address->Residential = 1;
		}

		$xml->RequestedShipment->PackageCount = 1;
		$xml->RequestedShipment->RequestedPackageLineItems->SequenceNumber = 1;
		$xml->RequestedShipment->RequestedPackageLineItems->GroupPackageCount = 1;
		$xml->RequestedShipment->RequestedPackageLineItems->Weight->Units = 'LB';
		$xml->RequestedShipment->RequestedPackageLineItems->Weight->Value = max(0.1, $order['order_shipping_weight_lb']);
		$xml->RequestedShipment->RequestedPackageLineItems->Dimensions->Length = round($order['order_shipping_length_in']);
		$xml->RequestedShipment->RequestedPackageLineItems->Dimensions->Width = round($order['order_shipping_width_in']);
		$xml->RequestedShipment->RequestedPackageLineItems->Dimensions->Height = round($order['order_shipping_height_in']);
		$xml->RequestedShipment->RequestedPackageLineItems->Dimensions->Units = 'IN';

		return $xml;
	}

	private function _parse_response($response)
	{
		$xml = simplexml_load_string($response);

		$rate = $xml->children(self::XML_NAMESPACE);
		if (empty($rate))
		{
			return array('error:shipping_method' => lang('shipping_communication_error'));
		}

		if ((string)$rate->HighestSeverity == 'ERROR')
		{
			if (isset($rate->Notifications->LocalizedMessage))
			{
				return array('error:shipping_method' => (string)$rate->Notifications->LocalizedMessage);
			}

			return array('error:shipping_method' => (string)$rate->Notifications->Message);
		}

		return (float)$rate->RateReplyDetails->RatedShipmentDetails->ShipmentRateDetail->TotalNetCharge->Amount;
	}

	private function _endpoint()
	{
		return $this->settings['test_mode'] ? self::TEST_ENDPOINT : self::LIVE_ENDPOINT;
	}
}

/* End of file ./libraries/store_shipping/store_shipping_fedex.php */