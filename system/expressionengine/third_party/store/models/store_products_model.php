<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_products_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();

		$this->load->helper('store');
	}

	public function find_by_id($entry_id)
	{
		$product = $this->db->select('p.*, sum(s.stock_level) as total_stock')
			->from('store_products p')
			->join('channel_titles t', 'p.entry_id = t.entry_id')
			->join('store_stock s', 's.entry_id = p.entry_id')
			->where('p.entry_id', (int)$entry_id)
			->group_by('p.entry_id')
			->get()->row_array();

		if (empty($product)) return FALSE;

		$product = $this->_process_product($product, TRUE, TRUE);

		return $product;
	}

	public function find_by_sku($sku)
	{
		if (empty($sku)) return FALSE;

		$this->db->from('store_stock s');
		$this->db->join('store_products p', 's.entry_id = p.entry_id');
		$this->db->join('channel_titles t', 's.entry_id = t.entry_id');
		$this->db->where('s.sku', $sku);
		$product = $this->db->get()->row_array();

		if (empty($product)) return FALSE;

		$product = $this->_process_product($product, TRUE);

		return $product;
	}

	public function get_product_sku_by_modifiers($entry_id, $modifiers)
	{
		$stock = $this->get_stock($entry_id);

		foreach ($stock as $stock_row)
		{
			$stock_mod = array_intersect_key($modifiers, $stock_row['opt_values']);

			// if all common modifiers match
			if ($stock_mod == $stock_row['opt_values']) return $stock_row['sku'];
		}

		return FALSE;
	}

	public function find_by_entry_ids($entry_ids)
	{
		if (empty($entry_ids)) return array();

		$products = $this->db->select('p.*, sum(s.stock_level) as total_stock')
			->from('store_products p')
			->join('store_stock s', 's.entry_id = p.entry_id')
			->where_in('p.entry_id', (array)$entry_ids)
			->group_by('p.entry_id')
			->get()->result_array();

		foreach ($products as $key => $product)
		{
			$products[$key] = $this->_process_product($product);
		}

		return $products;
	}

	private function _process_product($product, $fetch_modifiers = FALSE, $fetch_stock = FALSE)
	{
		$product['regular_price_val'] = (float)$product['regular_price'];
		$product['regular_price'] = store_format_currency($product['regular_price_val']);
		$product['sale_price_val'] = (float)$product['sale_price'];
		$product['sale_price'] = store_format_currency($product['sale_price_val']);
		$product['handling_val'] = (float)$product['handling'];
		$product['handling'] = store_format_currency($product['handling_val']);
		$product['price_val'] = $product['regular_price_val'];
		$product['price'] = $product['regular_price'];
		$product['free_shipping'] = $product['free_shipping'] == 'y';
		$product['tax_exempt'] = $product['tax_exempt'] == 'y';
		$product['on_sale'] = FALSE;

		// determine if the product is on sale
		if (($product['sale_price_enabled'] == 'y') AND
			(empty($product['sale_start_date']) OR $product['sale_start_date'] <= $this->localize->now) AND
			(empty($product['sale_end_date']) OR $product['sale_end_date'] >= $this->localize->now))
		{
			$product['price_val'] = $product['sale_price_val'];
			$product['price'] = $product['sale_price'];
			$product['on_sale'] = TRUE;
		}

		if ($fetch_modifiers) $product['modifiers'] = $this->get_product_modifiers($product['entry_id']);

		if ($fetch_stock) $product['stock'] = $this->get_stock($product['entry_id']);

		return $product;
	}

	public function find_all($options)
	{
		$options = array_merge(array(
			'count_all_results' => FALSE,
			'fetch_modifiers' => TRUE,
			'fetch_stock' => TRUE,
			'on_sale' => NULL,
			'order_by' => NULL,
			'sort' => NULL,
			'limit' => 50,
			'offset' => 0,
			'category_id' => NULL,
			'keywords' => FALSE,
			'exact_match' => FALSE,
		), $options);

		$this->db->select('*, sum(s.stock_level) as total_stock');
		$this->db->from('store_products p');
		$this->db->join('channel_titles t', 'p.entry_id = t.entry_id');
		$this->db->where('t.site_id', $this->config->item('site_id'));

		if ( ! empty($options['category_id']))
		{
			$this->db->join('category_posts', 'category_posts.entry_id = p.entry_id');
			$this->db->where('cat_id', $options['category_id']);
		}

		if ( ! empty($options['keywords']))
		{
			if ($options['exact_match'])
			{
				$this->db->where('p.entry_id', $options['keywords']);
				$this->db->or_where('t.title', $options['keywords']);
			}
			else
			{
				$this->db->like('p.entry_id', $options['keywords']);
				$this->db->or_like('t.title', $options['keywords']);
			}
		}

		if ($options['count_all_results'])
		{
			return $this->db->count_all_results();
		}

		if ( ! empty($options['order_by']))
		{
			$order_by = str_replace('store_products.', 'p.', $options['order_by']);
			$sort = strtoupper($options['sort']) == 'ASC' ? 'ASC' : 'DESC';
			$this->db->order_by($order_by, $sort);
		}
		else
		{
			$this->db->order_by('t.title', 'ASC');
		}

		$this->db->join('store_stock s', 's.entry_id = p.entry_id');
		$this->db->group_by('p.entry_id');

		$this->db->limit($options['limit'], $options['offset']);
		$products = $this->db->get()->result_array();

		foreach ($products as $key => $product)
		{
			$product = $this->_process_product($product, $options['fetch_modifiers'], $options['fetch_stock']);
			$product['stock_table'] = $this->generate_stock_matrix_html($product, 'store_product_field['.$product['entry_id'].']', FALSE);
			$product['channel_edit_link'] = BASE.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id='.$product['channel_id'].AMP.'entry_id='.$product['entry_id'];
			$products[$key] = $product;
		}

		return $products;
	}

	public function find_all_entry_ids($options)
	{
		$options = array_merge(array(
			'price_min' => 0,
			'price_max' => 0,
			'on_sale' => NULL,
			'in_stock' => NULL,
			'orderby' => NULL,
			'sort' => NULL,
		), $options);

		// CI Active Record sucks because it tries to escape functions in a select statement,
		// so we're going old school
		$sql = 'SELECT `p`.`entry_id`, COALESCE(SUM(`s`.`stock_level`), 0) AS `total_stock`
			FROM '.$this->db->protect_identifiers('store_products', TRUE).' p
			JOIN '.$this->db->protect_identifiers('store_stock', TRUE).' s
				ON `s`.`entry_id` = `p`.`entry_id`
			JOIN '.$this->db->protect_identifiers('channel_titles', TRUE).' t
				ON `t`.`entry_id` = `p`.`entry_id`
			WHERE 1';

		if ($options['price_min'] > 0)
		{
			$sql .= ' AND `p`.`regular_price` >= '.$this->db->escape($options['price_min']);
		}
		if ($options['price_max'] > 0)
		{
			$sql .= ' AND `p`.`regular_price` <= '.$this->db->escape($options['price_max']);
		}

		if ($options['on_sale'] == 'yes')
		{
			$sql .= ' AND `sale_price_enabled` = "y"
				AND (`sale_start_date` IS NULL OR `sale_start_date` <= '.$this->db->escape($this->localize->now).')
				AND (`sale_end_date` IS NULL OR `sale_end_date` >= '.$this->db->escape($this->localize->now).')';
		}
		elseif ($options['on_sale'] == 'no')
		{
			$sql .= ' AND `sale_price_enabled` = "n"';
		}

		$sql .= ' GROUP BY `p`.`entry_id`';

		if ($options['in_stock'] == 'yes')
		{
			$sql .= ' HAVING `total_stock` > 0';
		}
		elseif ($options['in_stock'] == 'no')
		{
			$sql .= ' HAVING `total_stock` <= 0';
		}

		if ($options['orderby'])
		{
			$options['sort'] = (strtoupper($options['sort']) == 'DESC') ? 'DESC' : 'ASC';

			if ($options['orderby'] == 'price')
			{
				$options['orderby'] = 'regular_price';
			}

			$sql .= ' ORDER BY '.$this->db->protect_identifiers($options['orderby']).' '.$options['sort'];
		}

		$query = $this->db->query($sql)->result_array();

		if (empty($query)) return array();

		$entry_ids = array();
		foreach ($query as $row) $entry_ids[] = $row['entry_id'];

		return $entry_ids;
	}

	public function update_product($entry_id, $data)
	{
	    $entry_id = (int)$entry_id;

		// important that we only update attributes which were submitted
		// sometime (e.g. inventory page) we don't submit all the attributes
		$product = array();

		// currency fields
		foreach (array('regular_price', 'sale_price', 'handling') as $field)
		{
			if (isset($data[$field]))
			{
				$product[$field] = store_parse_currency($data[$field]);
			}
		}

		// date fields
		foreach (array('sale_start_date', 'sale_end_date') as $field)
		{
			if (isset($data[$field]))
			{
				$product[$field] = empty($data[$field]) ? NULL : $this->store_config->string_to_timestamp($data[$field]);
			}
		}

		// float fields
		foreach (array('weight', 'dimension_l', 'dimension_w', 'dimension_h') as $field)
		{
			if (isset($data[$field]))
			{
				$product[$field] = (float)$data[$field];
			}
		}

		// checkbox fields
		foreach (array('sale_price_enabled', 'free_shipping', 'tax_exempt') as $field)
		{
			if (isset($data[$field]))
			{
				$product[$field] = $data[$field] == 'y' ? 'y' : 'n';
			}
		}

		// if no product details were submitted (e.g. custom safecracker form)
		// our work here is done...
		if (empty($product))
		{
			return;
		}

		// check for existing product
		$this->db->from('store_products');
		$this->db->where('entry_id', $entry_id);
		if ($this->db->count_all_results() > 0)
		{
			$this->db->where('entry_id', $entry_id);
			$this->db->update('store_products', $product);
		}
		else
		{
			$product['entry_id'] = $entry_id;
			$this->db->insert('store_products', $product);
		}

		// do we need to update product modifiers?
		if ( ! empty($data['modifiers']))
		{
			$data['modifiers'] = $this->update_product_modifiers($entry_id, $data['modifiers']);
		}

		// do we need to update stock?
		if ( ! empty($data['stock']))
		{
			$this->update_stock($entry_id, $data['stock'], isset($data['modifiers']) ? $data['modifiers'] : NULL);
		}
	}

	/**
	 * Get an array containing all modifiers and modifier values for a specific product
	 */
	public function get_product_modifiers($entry_id)
	{
		$this->db->select('m.*, o.product_opt_id, o.opt_name, o.opt_price_mod, o.opt_order');
		$this->db->from('store_product_modifiers m');
		$this->db->join('store_product_options o', 'm.product_mod_id = o.product_mod_id', 'left');
		$this->db->order_by('mod_order', 'asc');
		$this->db->order_by('m.product_mod_id', 'asc');
		$this->db->order_by('opt_order', 'asc');
		$this->db->order_by('o.product_opt_id', 'asc');
		$this->db->where('m.entry_id', (int)$entry_id);
		$query = $this->db->get()->result_array();

		// convert to multi dimensional array
		$result = array();
		foreach ($query as $row)
		{
			$mod_id = (int)$row['product_mod_id'];
			if ( ! isset($result[$mod_id]))
			{
				$result[$mod_id] = array(
					'product_mod_id' => $mod_id,
					'entry_id' => $row['entry_id'],
					'mod_type' => $row['mod_type'],
					'mod_name' => $row['mod_name'],
					'mod_instructions' => $row['mod_instructions'],
					'mod_order' => $row['mod_order'],
					'options' => array()
				);
			}

			if ( ! empty($row['product_opt_id']))
			{
				$opt_id = (int)$row['product_opt_id'];
				$opt_data = array(
					'product_opt_id' => $opt_id,
					'opt_name' => $row['opt_name'],
					'opt_order' => $row['opt_order']
				);

				$opt_data['opt_price_mod_val'] = (float)$row['opt_price_mod'];
				$opt_data['opt_price_mod'] = empty($opt_data['opt_price_mod_val']) ? '' : store_format_currency($opt_data['opt_price_mod_val'], TRUE);

				$result[$mod_id]['options'][$opt_id] = $opt_data;
			}
		}

		return $result;
	}

	/**
	 * Save all info about updated or inserted product modifiers and their options.
	 * Returns the modifiers array with new modifier and option ids added.
	 */
	public function update_product_modifiers($entry_id, $modifiers)
	{
		$entry_id = (int)$entry_id;

		foreach ($modifiers as $mod_key => $mod_row)
		{
			$mod_data = $this->_clean_product_modifier($mod_row);

			// insert/update modifier data
			if (isset($mod_row['product_mod_id']))
			{
				$product_mod_id = (int)$mod_row['product_mod_id'];
				if (empty($mod_data['mod_type']))
				{
					// modifier has been removed
					$this->db->where('product_mod_id', $product_mod_id);
					$this->db->delete('store_product_modifiers');
					$this->db->where('product_mod_id', $product_mod_id);
					$this->db->delete('store_product_options');
					unset($modifiers[$mod_key]);
					continue;
				}

				// update modifier
				$this->db->where('product_mod_id', $product_mod_id);
				$this->db->update('store_product_modifiers', $mod_data);
			}
			else
			{
				// insert modifier
				$mod_data['entry_id'] = $entry_id;
				$this->db->insert('store_product_modifiers', $mod_data);
				$product_mod_id = (int)$this->db->insert_id();
			}

			$modifiers[$mod_key]['product_mod_id'] = $product_mod_id;

			if (empty($mod_row['options'])) $mod_row['options'] = array();

			if ($mod_data['mod_type'] == 'var' OR $mod_data['mod_type'] == 'var_single_sku')
			{
				// update existing options
				foreach ($mod_row['options'] as $opt_key => $opt_row)
				{
					$opt_data = $this->_clean_product_option($opt_row);

					if (isset($opt_row['product_opt_id']))
					{
						// existing options
						$product_opt_id = (int)$opt_row['product_opt_id'];
						if (empty($opt_data['opt_name']))
						{
							// remove blank option
							$this->db->where('product_opt_id', $product_opt_id);
							$this->db->delete('store_product_options');
							unset($modifiers[$mod_key]['options'][$opt_key]);
						}
						else
						{
							// update option
							$this->db->where('product_opt_id', $product_opt_id);
							$this->db->update('store_product_options', $opt_data);
							$modifiers[$mod_key]['options'][$opt_key]['product_opt_id'] = $product_opt_id;
						}
					}
					else
					{
						// new options
						if (empty($opt_data['opt_name']))
						{
							// ignore new blank option
							unset($modifiers[$mod_key]['options'][$opt_key]);
						}
						else
						{
							// insert option
							$opt_data['product_mod_id'] = $product_mod_id;
							$this->db->insert('store_product_options', $opt_data);
							$modifiers[$mod_key]['options'][$opt_key]['product_opt_id'] = (int)$this->db->insert_id();
						}
					}
				}

				// variation group must have options
				if (empty($modifiers[$mod_key]['options']))
				{
					$this->db->where('product_mod_id', $product_mod_id);
					$this->db->delete('store_product_modifiers');
					$this->db->where('product_mod_id', $product_mod_id);
					$this->db->delete('store_product_options');
					unset($modifiers[$mod_key]);
					continue;
				}
			}
			else
			{
				// remove all options (must be a text input or something)
				$this->db->where('product_mod_id', $product_mod_id);
				$this->db->delete('store_product_options');
				$modifiers[$mod_key]['options'] = array();
			}
		}

		return $modifiers;
	}

	private function _clean_product_modifier($mod_data)
	{
		return array(
			'mod_type' => empty($mod_data['mod_type']) ? '' : $mod_data['mod_type'],
			'mod_name' => empty($mod_data['mod_name']) ? '' : $mod_data['mod_name'],
			'mod_instructions' => empty($mod_data['mod_instructions']) ? '' : $mod_data['mod_instructions'],
			'mod_order' => empty($mod_data['mod_order']) ? 0 : $mod_data['mod_order']
		);
	}

	private function _clean_product_option($opt_data)
	{
		return array(
			'opt_name' => empty($opt_data['opt_name']) ? '' : $opt_data['opt_name'],
			'opt_price_mod' => empty($opt_data['opt_price_mod']) ? NULL : store_parse_currency($opt_data['opt_price_mod']),
			'opt_order' => empty($opt_data['opt_order']) ? 0 : $opt_data['opt_order']
		);
	}

	public function delete_product($entry_id)
	{
		// delete product
		$this->db->where('entry_id', (int)$entry_id);
		$this->db->delete('store_products');

		// delete product options associated with product modifiers
		$this->db->select('product_mod_id');
		$this->db->where('entry_id', (int)$entry_id);
		$prod_mod_ids = $this->db->get('store_product_modifiers')->result_array();

		if ( ! empty($prod_mod_ids))
		{
			foreach ($prod_mod_ids as $key => $row) $prod_mod_ids[$key] = $row['product_mod_id'];

			$this->db->where_in('product_mod_id', $prod_mod_ids);
			$this->db->delete('store_product_options');
		}

		// delete product modifiers
		$this->db->where('entry_id', (int)$entry_id);
		$this->db->delete('store_product_modifiers');

		// delete stock
		$this->db->where('entry_id', (int)$entry_id);
		$this->db->delete('store_stock');

		// delete stock options
		$this->db->where('entry_id', (int)$entry_id);
		$this->db->delete('store_stock_options');
	}

	public function get_stock($entry_id)
	{
		$this->db->select('s.*, so.product_mod_id, so.product_opt_id');
		$this->db->from('store_stock s');
		$this->db->join('store_stock_options so', 's.sku = so.sku', 'left');
		$this->db->where('s.entry_id', (int)$entry_id);

		$query = $this->db->get()->result_array();
		$result = array();

		foreach ($query as $row)
		{
			$sku = $row['sku'];
			if ( ! isset($result[$sku]))
			{
				$result[$sku] = array(
					'sku' => $row['sku'],
					'entry_id' => $row['entry_id'],
					'stock_level' => $row['stock_level'],
					'min_order_qty' => $row['min_order_qty'],
					'track_stock' => $row['track_stock'],
					'opt_values' => array()
				);
			}

			if ( ! empty($row['product_mod_id']))
			{
				$result[$sku]['opt_values'][$row['product_mod_id']] = $row['product_opt_id'];
			}
		}

		return array_values($result);
	}

	public function update_stock($entry_id, $stock, $modifiers = NULL)
	{
		$entry_id = (int)$entry_id;

		// we will add the stock options back in as we go
		$this->db->where('entry_id', $entry_id);
		$this->db->delete('store_stock_options');

		// first remove any stock rows which aren't getting updated
		$update_skus = array();
		foreach ($stock as $row)
		{
			if ( ! empty($row['update_sku'])) $update_skus[] = $row['update_sku'];
		}

		$this->db->where('entry_id', $entry_id);
		if ( ! empty($update_skus))
		{
			$this->db->where_not_in('sku', $update_skus);
		}
		$this->db->delete('store_stock');

		// loop through stock table
		foreach ($stock as $row)
		{
			$sku = empty($row['update_sku']) ? $row['sku'] : $row['update_sku'];

			// sku should never be empty, just skip this row
			if (empty($sku)) continue;

			$this->db->set('track_stock', (isset($row['track_stock']) AND $row['track_stock'] == 'y') ? 'y' : 'n');
			$this->db->set('stock_level', isset($row['stock_level']) ? (int)$row['stock_level'] : NULL);
			$this->db->set('min_order_qty', (int)$row['min_order_qty'] < 1 ? 0 : (int)$row['min_order_qty']);

			if ( ! empty($row['update_sku']))
			{
				// update existing stock row
				$this->db->where('sku', $row['update_sku']);
				$this->db->update('store_stock');
			}
			else
			{
				// insert a new stock row
				$this->db->set('sku', $sku);
				$this->db->set('entry_id', $entry_id);
				$this->db->insert('store_stock');
			}

			// link stock rows to modifier options
			if (isset($row['opt_values']))
			{
				foreach ($row['opt_values'] as $mod_key => $opt_key)
				{
					$data = array('entry_id' => $entry_id, 'sku' => $sku);

					if ( ! empty($modifiers))
					{
						// find out what the real product_mod_id and product_opt_ids are
						$data['product_mod_id'] = (int)$modifiers[$mod_key]['product_mod_id'];
						$data['product_opt_id'] = (int)$modifiers[$mod_key]['options'][$opt_key]['product_opt_id'];
					}
					else
					{
						$data['product_mod_id'] = (int)$mod_key;
						$data['product_opt_id'] = (int)$opt_key;
					}

					$this->db->insert('store_stock_options', $data);
				}
			}
		}
	}

	public function generate_stock_matrix($product)
	{
		$existing_stock = empty($product['stock']) ? array() : $product['stock'];

		$opt_names = array();
		$opt_values = array();

		// collect modifiers and options which will contribute to stock matrix
		foreach ($product['modifiers'] as $mod_key => $mod_data)
		{
			// only mod_type='var' contributes to stock matrix
			if (isset($mod_data['mod_type']) AND $mod_data['mod_type'] == 'var' AND ! empty($mod_data['mod_name']))
			{
				$opt_names[$mod_key] = array();
				$opt_values[$mod_key] = array();

				if (isset($mod_data['options']))
				{
					foreach ($mod_data['options'] as $opt_key => $opt_data)
					{
						// options with no name are ignored
						if ( ! empty($opt_data['opt_name']))
						{
							$opt_names[$mod_key][$opt_key] = $opt_data['opt_name'];
							$opt_values[$mod_key][] = $opt_key;
						}
					}
				}

				if (empty($opt_values[$mod_key]))
				{
					unset($opt_names[$mod_key]);
					unset($opt_values[$mod_key]);
				}
			}
		}

		// generate stock matrix
		$stock_rows = array_cartesian($opt_values);

		// load stock data
		foreach ($stock_rows as $stock_key => $opt_values)
		{
			$new_row = array(
				'sku' => '',
				'sku_error' => '',
				'stock_level' => '',
				'min_order_qty' => '',
				'track_stock' => '',
				'opt_names' => array(),
				'opt_values' => $opt_values);

			foreach ($opt_values as $mod_key => $opt_key)
			{
				$new_row['opt_names'][$mod_key] = $opt_names[$mod_key][$opt_key];
			}

			// try to match some existing data for this row
			foreach ($existing_stock as $existing_stock_key => $existing_row)
			{
				// only try to match mod_keys present in the old stock row
				$match_existing_options = empty($existing_row['opt_values']) ? array() : $existing_row['opt_values'];
				$match_new_options = array_intersect_key($opt_values, $match_existing_options);
				$match_existing_options = array_intersect_key($match_existing_options, $opt_values);

				// this essentially tests whether all common array keys have equal values
				if ($match_existing_options == $match_new_options)
				{
					foreach (array('sku', 'sku_error', 'new_sku', 'stock_level', 'min_order_qty', 'track_stock') as $field)
					{
						if (isset($existing_row[$field])) $new_row[$field] = $existing_row[$field];
					}

					// don't match this row again
					unset($existing_stock[$existing_stock_key]);
					break;
				}
			}

			$stock_rows[$stock_key] = $new_row;
		}

		return $stock_rows;
	}

	/**
	 * Same as the generate_stock_matrix() function, except data is formatted using field_stock view file
	 */
	public function generate_stock_matrix_html($product, $prefix, $publish_page = TRUE)
	{
		if (empty($product['modifiers']))
		{
			$product['modifiers'] = array();
		}

		$data = array(
			'modifiers' => $product['modifiers'],
			'stock' => $this->generate_stock_matrix($product),
			'prefix' => $prefix,
			'publish_page' => $publish_page
		);

		return $this->load->view('field_stock', $data, TRUE);
	}

	public function stock_inventory($order_by_option)
	{
		$this->db->select('cs.*, ct.title, cp.*, group_concat(cpo.opt_name order by cpo.product_mod_id desc, cpo.product_opt_id asc separator \' - \') as description');
		$this->db->from('store_stock as cs');
		$this->db->join('channel_titles as ct', 'ct.entry_id = cs.entry_id');
		$this->db->join('store_products as cp', 'cp.entry_id = cs.entry_id');
		$this->db->join('store_stock_options as cso', 'cso.sku = cs.sku', 'left');
		$this->db->join('store_product_options as cpo', 'cpo.product_opt_id = cso.product_opt_id', 'left');
		$this->db->group_by('cs.sku');
		$this->db->order_by($order_by_option);
		if ($order_by_option == 'title') $this->db->order_by('description');

		$products = $this->db->get()->result_array();

		foreach ($products as $key => $product) $products[$key] = $this->_process_product($product);

		return $products;
	}

	public function total_products()
	{
		return $this->db->count_all_results('store_products');
	}

	public function check_existing_skus($entry_id, $sku)
	{
		$this->db->where('entry_id !=', $entry_id);
		$this->db->where('sku', $sku);
		$this->db->from('store_stock s');
		return $this->db->count_all_results();
	}
}
/* End of file ./models/store_products_model.php */