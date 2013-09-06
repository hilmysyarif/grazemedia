<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_shipping_aus_post extends Store_shipping_driver
{
	const PROCESS_URL = 'http://drc.edeliver.com.au/ratecalc.asp';

	// dimensions (mm)
	const MIN_DIMENSION = 50;
	const MAX_LENGTH = 1050;
	const MAX_GIRTH = 1400; // 2 * width + 2 * height

	// weight (g)
	const MIN_WEIGHT = 100;
	const MAX_WEIGHT = 20000;

	public $remote = true;

	public function default_settings()
	{
		return array(
			'source_postcode' => '',
			'service' => array('type' => 'select', 'default' => 'Standard', 'options' => array(
				'Standard' => 'Regular',
				'Express' => 'Express',
				'Exp_Plt' => 'Express Platinum',
				'ECI_D' => 'Express Courier International Document',
				'ECI_M' => 'Express Courier International Merchandise',
				'Air' => 'International Air',
				'Sea' => 'International Sea',
			)),
			'surcharge' => '',
		);
	}

	public function calculate_shipping($order)
	{
		// if no postcode specified, assume sending locally
		if (empty($order['shipping_country'])) $order['shipping_country'] = 'au';
		if (empty($order['shipping_postcode'])) $order['shipping_postcode'] = $this->settings['source_postcode'];

		// prep aus post query
		$args = array(
			'Length' => max(self::MIN_DIMENSION, round($order['order_shipping_length_cm'] * 10)),
			'Width' => max(self::MIN_DIMENSION, round($order['order_shipping_width_cm'] * 10)),
			'Height' => max(self::MIN_DIMENSION, round($order['order_shipping_height_cm'] * 10)),
			'Weight' => max(self::MIN_WEIGHT, round($order['order_shipping_weight_kg'] * 1000)),
			'Pickup_Postcode' => $this->settings['source_postcode'],
			'Destination_Postcode' => $order['shipping_postcode'],
			'Country' => $order['shipping_country'],
			'Service_Type' => $this->settings['service'],
		);

		// protect against extreme girth
		$max_height = self::MAX_GIRTH / 4;
		if ($args['Height'] > $max_height) $args['Height'] = $max_height;

		$max_width = (self::MAX_GIRTH / 2) - $args['Height'];
		if ($args['Width'] > $max_width) $args['Width'] = $max_width;

		// if we have exceeded length or weight restriction, need to split into multiple packages
		$args['Quantity'] = ceil(max($args['Weight'] / self::MAX_WEIGHT, $args['Length'] / self::MAX_LENGTH));
		if ($args['Quantity'] > 1)
		{
			// we still must ensure parcels don't go below minimums
			$args['Length'] = max(self::MIN_DIMENSION, round($args['Length'] / $args['Quantity']));
			$args['Weight'] = max(self::MIN_WEIGHT, round($args['Weight'] / $args['Quantity']));
		}

		$this->EE->load->library('curl');
		$url = self::PROCESS_URL.QUERY_MARKER.http_build_query($args).'&';

		$response = $this->EE->curl->simple_get($url, NULL, $this->default_curl_options());
		if (empty($response))
		{
			return array('error:shipping_method' => $this->EE->curl->error_string);
		}

		$response_array = $this->decode_response($response);
		if ($response_array['err_msg'] == 'OK')
		{
			return (float)$response_array['charge'] + (float)$this->settings['surcharge'];
		}
		else
		{
			return array('error:shipping_method' => $response_array['err_msg']);
		}
	}

	/**
	 * Convert the response from Australia Post into an associative array
	 */
	public function decode_response($response)
	{
		$lines = explode("\n", $response);
		$out = array();

		foreach ($lines as $line)
		{
			$parts = explode('=', $line, 2);
			if (count($parts) == 2)
			{
				$out[trim($parts[0])] = trim($parts[1]);
			}
		}

		foreach (array('charge', 'days', 'err_msg') as $key)
		{
			if ( ! isset($out[$key])) $out[$key] = NULL;
		}

		return $out;
	}
}

/* End of file ./plugins/shipping/default/default_shipping_plugin.php */