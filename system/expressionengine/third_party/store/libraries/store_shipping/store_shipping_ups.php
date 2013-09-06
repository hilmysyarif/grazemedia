<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_shipping_ups extends Store_shipping_driver
{
	const PROCESS_URL = 'https://onlinetools.ups.com/ups.app/xml/Rate';
	const PROCESS_URL_TEST = 'https://wwwcie.ups.com/ups.app/xml/Rate';

	// weight (lb)
	const MAX_WEIGHT = 50;

	public $remote = true;

	public static $imperial_countries = array('us');

	public function default_settings()
	{
		return array(
			'access_key' => '',
			'user_id' => '',
			'password' => array('type' => 'password'),
			'pickup_type' => array('type' => 'select', 'default' => '01', 'options' => array(
				'01' => 'Daily Pickup',
				'03' => 'Customer Counter',
				'06' => 'One Time Pickup',
				'07' => 'On Call Air',
				'19' => 'Letter Center',
				'20' => 'Air Service Center')),
			'service' => array('type' => 'select', 'options' => array(
				''   => 'Any Available',
				'03' => 'Domestic Ground',
				'12' => 'Domestic 3 Day Select',
				'01' => 'Domestic Next Day Air',
				'14' => 'Domestic Next Day Air Early AM',
				'13' => 'Domestic Next Day Air Saver',
				'02' => 'Domestic Second Day Air',
				'59' => 'Domestic Second Day Air AM',
				'11' => 'International Standard',
				'65' => 'International Saver',
				'07' => 'International Worldwide Express',
				'54' => 'International Worldwide Express Plus',
				'08' => 'International Worldwide Expedited')),
			'packaging' => array('type' => 'select', 'default' => '02', 'options' => array(
				'02' => 'Package',
				'01' => 'UPS Letter',
				'03' => 'Tube',
				'04' => 'Pak',
				'25' => '10KG Box',
				'24' => '25KG Box',
				'30' => 'Pallet',
				'21' => 'Express Box',
				'2a' => 'Small Express Box',
				'2b' => 'Medium Express Box',
				'2c' => 'Large Express Box',
				'00' => 'Unknown')),
			'source_city' => '',
			'source_zip' => '',
			'source_country' => array(
				'type' => 'select',
				'default' => 'us',
				'options' => $this->EE->store_shipping_model->countries),
			'insure_order' => false,
			'test_mode' => true,
		);
	}

	public function __construct()
	{
		$this->EE =& get_instance();
	}

	public function calculate_shipping($order)
	{
		// don't bother unless we at least have a country, and city or ZIP
		if (empty($order['shipping_country'])) return 0;
		if (empty($order['shipping_postcode']) AND empty($order['shipping_address3'])) return 0;

		$access_request = new SimpleXMLElement('<AccessRequest />');
		$access_request->AccessLicenseNumber = $this->settings['access_key'];
		$access_request->UserId = $this->settings['user_id'];
		$access_request->Password = $this->settings['password'];

		$rating_request = new SimpleXMLElement('<RatingServiceSelectionRequest />');
		$rating_request->Request->TransactionReference->CustomerContext = 'Rating and Service';
		$rating_request->Request->TransactionReference->XpciVersion = '1.0';
		$rating_request->Request->RequestAction = 'Rate';
		$rating_request->Request->RequestOption = $this->settings['service'] == '' ? 'Shop' : 'Rate';
		$rating_request->PickupType->Code = $this->settings['pickup_type'];

		$rating_request->Shipment->Shipper->Address->City = $this->settings['source_city'];
		$rating_request->Shipment->Shipper->Address->PostalCode = $this->settings['source_zip'];
		$rating_request->Shipment->Shipper->Address->CountryCode = $this->country_code($this->settings['source_country']);

		$rating_request->Shipment->ShipTo->PhoneNumber = $order['shipping_phone'];
		$rating_request->Shipment->ShipTo->Address->AddressLine1 = $order['shipping_address1'];
		$rating_request->Shipment->ShipTo->Address->AddressLine2 = $order['shipping_address2'];
		$rating_request->Shipment->ShipTo->Address->City = $order['shipping_address3'];
		$rating_request->Shipment->ShipTo->Address->StateProvinceCode = $order['shipping_region'];
		$rating_request->Shipment->ShipTo->Address->PostalCode = $order['shipping_postcode'];
		$rating_request->Shipment->ShipTo->Address->CountryCode = $this->country_code($order['shipping_country']);

		$rating_request->Shipment->Service->Code = $this->settings['service'];

		$num_packages = ceil($order['order_shipping_weight_lb'] / self::MAX_WEIGHT);
		for ($i = 0; $i < $num_packages; $i++ )
		{
			$rating_request->Shipment->Package[$i]->PackagingType->Code = $this->settings['packaging'];

			// units must match source address/country for some reason
			if (in_array($this->settings['source_country'], self::$imperial_countries))
			{
				$rating_request->Shipment->Package[$i]->Dimensions->UnitOfMeasurement->Code = 'IN';
				$rating_request->Shipment->Package[$i]->Dimensions->Length = sprintf("%.1f", $order['order_shipping_length_in'] / $num_packages);
				$rating_request->Shipment->Package[$i]->Dimensions->Height = sprintf("%.1f", $order['order_shipping_height_in']);
				$rating_request->Shipment->Package[$i]->Dimensions->Width = sprintf("%.1f", $order['order_shipping_width_in']);
				$rating_request->Shipment->Package[$i]->PackageWeight->UnitOfMeasurement->Code = 'LBS';
				$rating_request->Shipment->Package[$i]->PackageWeight->Weight = sprintf("%.1f", $order['order_shipping_weight_lb'] / $num_packages);
			}
			else
			{
				$rating_request->Shipment->Package[$i]->Dimensions->UnitOfMeasurement->Code = 'CM';
				$rating_request->Shipment->Package[$i]->Dimensions->Length = sprintf("%.1f", $order['order_shipping_length_cm'] / $num_packages);
				$rating_request->Shipment->Package[$i]->Dimensions->Height = sprintf("%.1f", $order['order_shipping_height_cm']);
				$rating_request->Shipment->Package[$i]->Dimensions->Width = sprintf("%.1f", $order['order_shipping_width_cm']);
				$rating_request->Shipment->Package[$i]->PackageWeight->UnitOfMeasurement->Code = 'KGS';
				$rating_request->Shipment->Package[$i]->PackageWeight->Weight = sprintf("%.1f", $order['order_shipping_weight_kg'] / $num_packages);
			}

			// order weight must not be zero
			if ((float)$rating_request->Shipment->Package[$i]->PackageWeight->Weight <= 0)
			{
				$rating_request->Shipment->Package[$i]->PackageWeight->Weight = '0.1';
			}

			if ($this->settings['insure_order'])
			{
				$rating_request->Shipment->Package[$i]->PackageServiceOptions->InsuredValue->CurrencyCode = $this->EE->store_config->item('currency_code');
				$rating_request->Shipment->Package[$i]->PackageServiceOptions->InsuredValue->MonetaryValue = $order['order_total_val'];
			}
		}

		$this->EE->load->library('curl');

		$url = ($this->settings['test_mode']) ? self::PROCESS_URL_TEST : self::PROCESS_URL;
		$request = $access_request->asXML().$rating_request->asXML();
		$response = $this->EE->curl->simple_post($url, $request, $this->default_curl_options());

		if (empty($response))
		{
			return array('error:shipping_method' => $this->EE->curl->error_string);
		}

		$xml = simplexml_load_string($response);
		if ((int)$xml->Response->ResponseStatusCode === 1)
		{
			return (float)$xml->RatedShipment->TotalCharges->MonetaryValue;
		}
		else
		{
			return array('error:shipping_method' => (string)$xml->Response->Error->ErrorDescription);
		}
	}

	/**
	 * Produce a valid UPS country code
	 */
	protected function country_code($value)
	{
		$value = strtoupper($value);
		return $value == 'UK' ? 'GB' : $value;
	}
}

/* End of file ./plugins/shipping/default/default_shipping_plugin.php */