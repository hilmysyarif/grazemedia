<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

if ( ! function_exists('array_cartesian'))
{
	/**
	 * Generates the cartesian product of associative php arrays
	 *
	 * Input: array('color' => array('Red', 'Blue'),
	 *              'size' => array('Small', 'Large'));
	 *
	 * Output: array(array('color' => 'Red', 'size' => 'Small'),
	 *               array('color' => 'Red', 'size' => 'Large'),
	 *               array('color' => 'Blue', 'size' => 'Small'),
	 *               array('color' => 'Blue', 'size' => 'Large'));
	 */
	function array_cartesian($input)
	{
		if ( ! is_array($input)) { return NULL; }

		$output = array(array());

		foreach ($input as $group => $values)
		{
			if ( ! is_array($values)) { $values = array($values); }

			foreach ($output as $key => $row)
			{
				unset($output[$key]);
				foreach ($values as $val)
				{
					$new_row = $row;
					$new_row[$group] = $val;
					$output[] = $new_row;
				}
			}
		}

		return array_values($output);
	}
}

if ( ! function_exists('tmpl_no_results'))
{
	/**
	 * Returns the portion of tagdata found between the specified {if no_results} tags,
	 * or returns FALSE if no tag exists
	 */
	function tmpl_no_results($tagdata, $tag_name)
	{
		if ( ! empty($tag_name) AND strpos($tagdata, 'if '.$tag_name) !== FALSE AND
			preg_match('/'.LD.'if '.$tag_name.RD.'(.*?)'.LD.'\/if'.RD.'/s', $tagdata, $match))
		{
			// currently this won't handle nested conditional statements.. lame
			return $match[1];
		}
		else
		{
			return FALSE;
		}
	}
}

if ( ! function_exists('store_enabled_str'))
{
	/**
	 * Lazy way to return a localised 'Enabled' or 'Disabled' string in view files
	 */
	function store_enabled_str($enabled)
	{
		if (empty($enabled) OR strtolower($enabled) == 'n')
		{
			return '<span class="notice">'.lang('disabled').'</span>';
		}

		return '<span class="go_notice">'.lang('enabled').'</span>';
	}
}

if ( ! function_exists('store_format_currency'))
{
	/**
	 * Formats currency as a string, based on the current preferences
	 */
	function store_format_currency($number, $force_sign = FALSE)
	{
		$EE =& get_instance();

		$output = $EE->store_config->item('currency_symbol').
			number_format(abs($number),
				(int)$EE->store_config->item('currency_decimals'),
				$EE->store_config->item('currency_dec_point'),
				$EE->store_config->item('currency_thousands_sep')).
			$EE->store_config->item('currency_suffix');

		if ($force_sign) return $number < 0 ? '-'.$output : '+'.$output;
		else return $number < 0 ? '-'.$output : $output;
	}
}

if ( ! function_exists('store_cp_format_currency'))
{
	/**
	 * Formats currency as a string, based on the current preferences
	 */
	function store_cp_format_currency($number)
	{
		$EE =& get_instance();
		$currency_decimals = (int)$EE->store_config->item('currency_decimals');

		$number = round($number, 4);
		if ($number == round($number, $currency_decimals))
		{
			$number = sprintf('%.0'.$currency_decimals.'f', $number);
		}

		return str_replace('.', $EE->store_config->item('currency_dec_point'), (string)$number);
	}
}

if ( ! function_exists('store_parse_currency'))
{
	/**
	 * Converts a currency string into a float, based on the current preferences
	 */
	function store_parse_currency($number)
	{
		$EE =& get_instance();
		$dec_point = $EE->store_config->item('currency_dec_point');

		$number = preg_replace('/[^\-0-9'.preg_quote($dec_point, '/').']+/', '', $number);
		$number = str_replace($dec_point, '.', $number);
		return (float)$number;
	}
}

if ( ! function_exists('store_round_currency'))
{
	/**
	 * Round a decimal to the correct number of decimal places
	 *
	 * @param float $number
	 * @param bool $allow_negative
	 */
	function store_round_currency($number, $allow_negative = FALSE)
	{
		$number = (float)$number;
		$decimals = (int)get_instance()->store_config->item('currency_decimals');

		if ($allow_negative)
		{
			return round($number, $decimals);
		}

		return max(0, round($number, $decimals));
	}
}

if ( ! function_exists('store_setting_input'))
{
	/**
	 * Generate an input field for settings using our config array format
	 */
	function store_setting_input($key, $default, $value)
	{
		$input_name = "settings[$key]";
		$extra_attrs = 'id="settings_'.$key.'"';

		if ($key == 'password')
		{
			$extra_attrs .= ' autocomplete="off"';
		}

		if (is_bool($default))
		{
			if ($value === TRUE) $value = 'y';
			return form_dropdown($input_name, array('y' => lang('true'), '' => lang('false')), $value, $extra_attrs);
		}

		if (is_array($default))
		{
			if (empty($default['type']))
			{
				throw new InvalidArgumentException('Missing setting type in default setting array');
			}

			switch ($default['type'])
			{
				case 'select':
					if (empty($default['options']))
					{
						throw new InvalidArgumentException('Missing setting options in default setting array');
					}

					// run options through lang()
					foreach ($default['options'] as $opt_value => $opt_title)
					{
						$default['options'][$opt_value] = lang($opt_title);
					}

					return form_dropdown($input_name, $default['options'], $value, $extra_attrs);
				case 'textarea':
					return form_textarea($input_name, $value, $extra_attrs);
				case 'password':
					return form_password($input_name, $value, $extra_attrs);
				default:
					throw new InvalidArgumentException('Invalid setting type "'.$default['type'].'" in default setting array');
			}
		}

		// default is just a plain text input
		return form_input($input_name, $value, $extra_attrs);
	}
}

if ( ! function_exists('store_setting_default'))
{
	/**
	 * Get the default value for settings using our config array format
	 */
	function store_setting_default($setting)
	{
		if (is_array($setting))
		{
			return isset($setting['default']) ? $setting['default'] : NULL;
		}

		return $setting;
	}
}

if ( ! function_exists('store_form_checkbox'))
{
	function store_form_checkbox($name, $checked, $options = array())
	{
		$checkbox = array(
			'id' => trim(preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'),
			'name' => $name,
			'value' => '1',
			'checked' => (bool)$checked,
		);

		if ( ! empty($options['disabled']))
		{
			$checkbox['disabled'] = true;
		}

		return form_hidden($name, '0')."\n".form_checkbox($checkbox);
	}
}

if ( ! function_exists('store_payment_status'))
{
	function store_payment_status($payment_status)
	{
		return '<span class="store_payment_status_'.$payment_status.'">'.
			lang('payment_status_'.$payment_status).'</span>';
	}
}

/* End of file ./helpers/store_helper.php */