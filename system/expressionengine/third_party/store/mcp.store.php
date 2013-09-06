<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

require_once(PATH_THIRD.'store/config.php');

class Store_mcp {

	// the default number of results per page for datatables
	const DATATABLES_PAGE_SIZE = 50;

	protected static $_breadcrumbs = array();

	public function __construct()
	{
		$this->EE =& get_instance();

		$this->EE->lang->loadfile('content');
		$this->EE->lang->loadfile('design');
		$this->EE->load->library(array('store_config', 'store_form_validation', 'javascript', 'table'));
		$this->EE->load->helper(array('store', 'form', 'text', 'search'));
		$this->EE->load->model(array('store_common_model', 'store_orders_model', 'store_shipping_model', 'store_payments_model'));

		$this->EE->form_validation->set_error_delimiters('<p><strong class="notice">', '</strong></p>');

		// load store css + js
		$this->EE->store_config->cp_head_script();

		// default global view variables
		$this->EE->load->vars(array(
			'cp_store_table_template' => array(
				'table_open'		=> '<table class="mainTable store_table">',
				'row_start'			=> '<tr class="even">',
				'row_alt_start'		=> '<tr class="odd">')
		));

		$this->add_breadcrumb(BASE.AMP.STORE_CP, lang('store_module_name'));

		if ($this->EE->store_config->item('report_stats') == 'y' AND
			$this->EE->store_config->item('report_date') < $this->EE->localize->now)
		{
			$url = json_encode(html_entity_decode(BASE.AMP.STORE_CP.'&method=stats'));
			$this->EE->cp->add_to_foot("<script type='text/javascript'>jQuery.post($url);</script>");
		}
	}

	public function index()
	{
		if ( ! $this->EE->store_config->site_enabled())
		{
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=install');
		}

		$this->set_title(lang('dashboard'));

		if ($this->EE->input->get_post('graph_period') !== FALSE)
		{
			$data_period = $this->EE->input->get_post('graph_period');

			if ($data_period == 'daily')
			{
				$data_label = lang('dashboard_graph_daily');
			}
			elseif ($data_period == 'weekly')
			{
				$data_label = lang('dashboard_graph_weekly');
			}
			elseif ($data_period == 'monthly')
			{
				$data_label = lang('dashboard_graph_monthly');
			}
			elseif ($data_period == 'quarterly')
			{
				$data_label = lang('dashboard_graph_quarterly');
			}

		}
		else
		{
			$data_label = lang('dashboard_graph_weekly');
		}

		$order_status_select_options = $this->EE->store_orders_model->get_order_statuses();

		foreach ($order_status_select_options as $key => $option)
		{
			if (isset($option['name']))
			{
				$select_options[$option['name']] = lang($option['name']);
			}
		}

		$default_status = $this->EE->store_orders_model->get_default_status();

		$data = array(
			'post_url' => STORE_CP,
			'data_label' => array( 'name' => 'data_series_label', 'value' => $data_label, 'class'=> "field shun", 'style' => 'width: 260px', 'maxlength' => 100 ),
			'graph_period_options' => array('daily' => lang('daily_sales'), 'weekly' => lang('weekly_sales'), 'monthly' => lang('monthly_sales'), 'quarterly' => lang('quarterly_sales')),
			'graph_period_selected' => ($this->EE->input->get_post('graph_period')) ? $this->EE->input->get_post('graph_period') : 'weekly',
			'start_date' => array(
								'class' => 'store_datetimepicker',
								'name' => 'start_date',
								'value' => ($this->EE->input->get_post('start_date')) ? $this->EE->input->get_post('start_date') : $this->EE->store_config->format_date('%Y-%m-%d',$this->EE->localize->now - 52*7*24*60*60),
								'style' => 'width: 125px'
								),
			'end_date' => array(
								'class' => 'store_datetimepicker',
								'name' => 'end_date',
								'value' => ($this->EE->input->get_post('end_date')) ? $this->EE->input->get_post('end_date') : $this->EE->store_config->format_date('%Y-%m-%d', $this->EE->localize->now),
								'style' => 'width: 125px'
								),
			'store_ext_enabled' => $this->EE->store_common_model->is_store_ext_enabled(),
			'store_ft_enabled' => $this->EE->store_common_model->is_store_ft_enabled(),
			'extensions_link' => BASE.AMP.'C=addons_extensions',
			'fieldtypes_link' => BASE.AMP.'C=addons_fieldtypes',
			'orders' => $this->EE->store_orders_model->find_all(array('order_by' => 'order_date', 'sort' => 'desc', 'limit' => 10)),
			'status_select_options' => $select_options
		);

		if (isset($_POST['status']))
		{
			$this->EE->store_orders_model->update_order_status(
				$this->EE->input->post('order_id'),
				$this->EE->input->post('status'),
				$this->EE->session->userdata['member_id'],
				$this->EE->input->post('message')
				);
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP);
		}

		foreach ($data['orders'] as $key => $order)
		{
			$data['orders'][$key]['member_link'] = BASE.AMP.'C=myaccount'.AMP.'id='.$order['member_id'];
			$data['orders'][$key]['order_status_color'] = $this->EE->store_orders_model->get_status_color($data['orders'][$key]['order_status']);
		}

		//These must be in unix time...
		$date_start = strtotime($data['start_date']['value']);
		$end_date = strtotime($data['end_date']['value']) + 60*60*24;

		switch ($this->EE->input->post('graph_period'))
		{
			case 'daily':
				$period_size = 24*60*60;
				break;
			case 'weekly':
				$period_size = 7*24*60*60;
				break;
			case 'monthly':
				$period_size = 52*7*24*60*60/12;
				break;
			case 'quarterly':
				$period_size = 52*7*24*60*60/4;
				break;
			default :
				$period_size = 7*24*60*60;
		}

		$query = $this->EE->store_orders_model->get_orders_graph($date_start, $period_size, $end_date);

		$graph_data = array();
		foreach ($query as $row)
		{
			$graph_data[(int)$row['period_paid']] = array((int)$row['period_paid'], (float)$row['period_total']);
		}

		for ($i = 0; $i < ($end_date-$date_start)/$period_size; $i++)
		{
			if ( ! isset($graph_data[$i])) { $graph_data[$i] = array($i, 0); }
		}

		ksort($graph_data);

		$this->EE->cp->add_js_script(array('ui' => 'datepicker'));

		$this->EE->javascript->output('
			$(function() {
				ExpressoStore.dashboardGraph("'.$data['data_label']['value'].'", '.
					json_encode($graph_data).');
			});
		');

		$this->EE->javascript->compile();

		return $this->EE->load->view('dashboard', $data, TRUE);
	}

	public function install()
	{
		if ($this->EE->store_config->site_enabled())
		{
			$this->EE->functions->redirect(BASE.AMP.STORE_CP);
		}

		$this->set_title(lang('install_new_site'));

		$data = array(
			'site_name' => $this->EE->config->item('site_name'),
			'post_url' => STORE_CP.AMP.'method=install',
			'duplicate_options' => array('' => lang('none')),
			'is_super_admin' => $this->EE->store_config->is_super_admin(),
		);

		$sites = $this->EE->store_common_model->get_enabled_sites();
		foreach ($sites as $row)
		{
			$data['duplicate_options'][$row['site_id']] = $row['site_label'];
		}

		if ($this->EE->input->post('submit'))
		{
			if ( ! $data['is_super_admin'])
			{
				show_error(lang('store_no_access'));
			}

			// install default settings
			$site_id = $this->EE->config->item('site_id');
			$duplicate_site = $this->EE->input->post('duplicate_site');
			$this->EE->store_common_model->install_site($site_id, $duplicate_site);

			// install example templates?
			if ($this->EE->input->post('install_example_templates'))
			{
				$this->EE->store_common_model->install_templates($site_id);
			}

			// redirect
			$this->EE->session->set_flashdata('message_success', lang('site_installed_successfully'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP);
		}

		return $this->EE->load->view('install', $data, TRUE);
	}

	public function orders()
	{
		if ( ! $this->EE->store_config->site_enabled())
		{
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=install');
		}

		$this->set_title(lang('orders'));

		$order_id = (int)$this->EE->input->get('order_id');
		if ($order_id > 0)
		{
			return $this->_order_details($order_id);
		}

		$order_status_select_options = $this->EE->store_orders_model->get_order_statuses();
		$select_options[''] = lang('filter_by_order_status');

		foreach ($order_status_select_options as $key => $option)
		{
			if (isset($option['name']))
			{
				$select_options[$option['name']] = lang($option['name']);
				$actions[$option['name']] = 'Mark As '.lang($option['name']);
			}
		}

		$select_options['@incomplete'] = lang('incomplete');

		$actions['delete'] = lang('delete');

		$data = array(
			'post_url' => STORE_CP.AMP.'method=orders',
			'orders' => $this->orders_datatable(TRUE),
			'order_status_select_options' => $select_options,
			'order_paid_select_options' => array('any' => lang('filter_by_payment_status'), 'paid' => lang('paid'), 'unpaid' => lang('unpaid'), 'overpaid' => lang('overpaid')),
			'date_select_options' => array( 'date_range' => lang('date_range'), 'today' => lang('today'), 'yesterday' => lang('yesterday'), 'prev_month' => lang('prev_month'), 'past_day' => lang('past_day'), 'past_week' => lang('past_week'), 'past_month' => lang('past_month'), 'past_six_months' => lang('past_six_months'), 'past_year' => lang('past_year')),
			'perpage_select_options' => array( '10' => '10 '.lang('results'), '25' => '25 '.lang('results'), '50' => '50 '.lang('results'), '75' => '75 '.lang('results'), '100' => '100 '.lang('results'), '150' => '150 '.lang('results')),
			'search_form' => STORE_CP.AMP.'method=orders',
			'search_in_options' => array( 'All' => lang('all'), 'order_billing_name' => lang('billing_name'), 'order_shipping_name' => lang('shipping_name'), 'member' => lang('member'), 'order_id' => lang('order_id')),
			'actions' => $actions
		);

		if ($this->EE->input->post('action_submit') !== FALSE)
		{
			$selected_ids = $this->EE->input->post('selected', TRUE);
			$action = $this->EE->input->post('action', TRUE);

			if (is_array($selected_ids))
			{
				if ($action == 'delete')
				{
					return $this->delete_orders($selected_ids);
				}
				else
				{
					$this->EE->store_orders_model->update_orders_statuses($selected_ids, $action);

					$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
					$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
				}
			}

			$this->EE->session->set_flashdata('message_error', lang('no_orders_selected'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
		}

		$this->EE->cp->add_js_script(array('ui' => 'datepicker'));
		$this->_datatables_js(
			'orders_datatable',
			$data['orders']['non_sortable_columns'],
			$data['orders']['clickable_columns'],
			$data['orders']['default_sort'],
			$data['orders']['perpage']
		);
		$this->EE->javascript->compile();

		return $this->EE->load->view('orders', $data, TRUE);
	}

	public function orders_datatable($return_data = FALSE)
	{
		$col_map = array('expand', 'order_id', 'billing_name', 'screen_name', 'order_date',
			'order_total', 'order_owing', 'order_status', lang('order_details'), 'select_all', 'order_item');

		// order list filters
		$filters = array(
			'perpage' => $this->EE->input->get_post('perpage') ? (int)$this->EE->input->get_post('perpage') : self::DATATABLES_PAGE_SIZE,
			'date_range' => $this->EE->input->get_post('date_range'),
			'start_date' => 0,
			'end_date' => $this->EE->localize->now,
			'order_status' => $this->EE->input->get_post('order_status'),
			'order_paid_status' => $this->EE->input->get_post('order_paid_status'),
		);

		$filters['limit'] = $filters['perpage'];
		$filters['offset'] = (int)$this->EE->input->get_post('iDisplayStart');

		switch ($filters['date_range'])
		{
			case 'today':
				// get timestamp at start of current day
				$filters['start_date'] = strtotime(strftime('%F', $this->EE->localize->now));
				break;

			case 'yesterday':
				// end timestamp is start of current day, start 24 hours before
				$filters['end_date'] = strtotime(strftime('%F', $this->EE->localize->now));
				$filters['start_date'] = $filters['end_date'] - 60*60*24;
				break;

			case 'prev_month':
				$year = gmdate('y');
				$month = (int)date('n', $this->EE->localize->now);
				$day = 1;
				if ($month == 1)
				{
					$filters['start_date'] = gmmktime( 0, 0, 0, 12, $day, $year);
					$filters['end_date'] = gmmktime( 0, 0, 0, 1, $day,$year);
				}
				else
				{
					$filters['start_date'] = gmmktime( 0, 0, 0, $month-1, $day, $year);
					$filters['end_date'] = gmmktime( 0, 0, 0, $month, $day, $year);
				}
				break;

			case 'past_day':
				$filters['start_date'] = $this->EE->localize->now - (1*60*60*24);
				break;

			case 'past_week':
				$filters['start_date'] = $this->EE->localize->now - (7*60*60*24);
				break;

			case 'past_month':
				$filters['start_date'] = $this->EE->localize->now - (30*60*60*24);
				break;

			case 'past_six_months':
				$filters['start_date'] = $this->EE->localize->now - (180*60*60*24);
				break;

			case 'past_year':
				$filters['start_date'] = $this->EE->localize->now - (365*60*60*24);
				break;
		}

		// intentionally not using sanitize_search_terms() here, it breaks "+" signs etc
		$filters['keywords'] = $this->EE->input->get_post('keywords', TRUE);
		$filters['search_in'] = $this->EE->input->get_post('search_in');
		$filters['exact_match'] = $this->EE->input->get_post('exact_match');

		if (($order_by = $this->EE->input->get('iSortCol_0')) !== FALSE)
		{
			if (isset($col_map[$order_by]))
			{
				$filters['order_by'] = $col_map[$order_by];
				$filters['sort'] = $this->EE->input->get('sSortDir_0');
			}
		}

		// Note- we pipeline the js, so pull more data than are displayed on the page
		$filters['limit'] = (int)$this->EE->input->get_post('perpage');
		if (empty($filters['limit'])) $filters['limit'] = self::DATATABLES_PAGE_SIZE;
		$filters['offset'] = (int)$this->EE->input->get_post('iDisplayStart');

		$query = $this->EE->store_orders_model->find_all($filters);
		$total_filtered = $this->EE->store_orders_model->find_all(array_merge($filters, array('count_all_results' => TRUE)));
		$total = $this->EE->store_orders_model->total_orders(); //Total number of orders

		$response = array(
			'aaData' => array(),
			'sEcho' => (int)$this->EE->input->get_post('sEcho'),
			'iTotalRecords' => $total,
			'iTotalDisplayRecords' => $total_filtered,
		);

		foreach ($query as $row)
		{
			$response['aaData'][] = array(
				'<a href="#"><img src="'.$this->EE->config->item('theme_folder_url').'cp_global_images/expand.gif"></a> ',
				$row['order_id'],
				$row['billing_name'],
				"<strong><a href='".BASE.AMP.'C=myaccount'.AMP.'id='.$row['member_id']."'>{$row['screen_name']}</a></strong>",
				$this->EE->store_config->human_time($row['order_date']),
				$row['order_total'],
				$row['order_paid_str'],
				$row['order_status_html'],
				'<a href="'.BASE.AMP.STORE_CP.AMP.'method=orders'.AMP.'order_id='.$row['order_id'].'">'.lang('details').'</a>',
				form_checkbox('selected[]', $row['order_id']),
				$this->EE->load->view('order_items', array('order_items' => $row['items']), TRUE)
			);
		}

		$response = array_merge($response, $filters);

		$response['non_sortable_columns'] = array(0, 8, 9);
		$response['clickable_columns'] = array(0, 1, 2, 4, 5, 6, 7);
		$response['default_sort'] = array(4, 'desc');

		/* -------------------------------------------
		/* 'store_orders_datatable' hook.
		/*  - Modify the control panel orders datatable
		/*  - Added: 1.2.1
		*/
			if ($this->EE->extensions->active_hook('store_orders_datatable') === TRUE)
			{
				$response = $this->EE->extensions->call('store_orders_datatable', $response);
			}
		/*
		/* -------------------------------------------*/

		if ($return_data) return $response;
		else $this->EE->output->send_ajax_response($response);
	}

	private function _order_details($order_id)
	{
		$this->EE->load->library('store_payments');
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=orders', lang('orders'));
		$this->set_title(lang('order').' #'.$order_id);

		$order_status_select_options = $this->EE->store_orders_model->get_order_statuses();

		foreach ($order_status_select_options as $key => $option)
		{
			if (isset($option['name']))
			{
				$select_options[$option['name']] = lang($option['name']);
			}
		}

		$data = array(
			'post_url' => STORE_CP.AMP.'method=orders'.AMP.'order_id='.$order_id,
			'order' => $this->EE->store_orders_model->find_by_id($order_id),
			'order_fields' => $this->EE->store_config->get_order_fields(),
			'order_payments' => $this->EE->store_orders_model->get_order_payments($order_id),
			'order_statuses' => $this->EE->store_orders_model->get_order_status_history($order_id),
			'status_select_options' => $select_options,
			'can_add_payments' => $this->EE->store_config->has_privilege('can_add_payments'),
		);

		if (empty($data['order']))
		{
			$this->EE->session->set_flashdata('message_failure', lang('invalid_order_id'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
		}

		if (isset($_POST['status']))
		{
			$this->EE->store_orders_model->update_order_status(
														$order_id,
														$_POST['status'],
														isset($this->EE->session->userdata['member_id']) ? $this->EE->session->userdata['member_id'] : 0,
														isset($_POST['message']) ? $_POST['message'] : NULL
														);
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders'.AMP.'order_id='.$order_id);
		}

		if ($payment_id = (int)$this->EE->input->post('payment_id'))
		{
			$this->_capture_or_refund_payment($data['order'], $payment_id);
		}

		$data['add_payment_link'] = BASE.AMP.STORE_CP.AMP.'method=add_payment'.AMP.'order_id='.$data['order']['order_id'];
		$data['export_pdf_link'] = BASE.AMP.$data['post_url'].AMP.'export=pdf';

		$data['invoice_link'] = $this->EE->store_config->item('order_invoice_url');
		if ( ! empty($data['invoice_link']))
		{
			$data['invoice_link'] = str_replace('ORDER_ID', $data['order']['order_id'], $data['invoice_link']);
			$data['invoice_link'] = str_replace('ORDER_HASH', $data['order']['order_hash'], $data['invoice_link']);
			$data['invoice_link'] = $this->EE->functions->create_url($data['invoice_link']);
		}

		$data['order']['member_link'] = BASE.AMP.'C=myaccount'.AMP.'id='.$data['order']['member_id'];
		$data['order']['order_status_color'] = $this->EE->store_orders_model->get_status_color($data['order']['order_status']);

		foreach ($data['order_fields'] as $field_name => $field)
		{
			$data['order'][$field_name.'_name'] = empty($field['title']) ? NULL : $field['title'];
			if ( ! empty($data['order'][$field_name]) AND empty($data['order'][$field_name.'_name']))
			{
				$data['order'][$field_name.'_name'] = $field_name;
			}
		}

		foreach ($data['order_payments'] as $key => $payment)
		{
			$data['order_payments'][$key]['payment_member'] = empty($data['order_payments'][$key]['payment_member']) ? 'System' : $data['order_payments'][$key]['payment_member'];
			$data['order_payments'][$key]['member_link'] = BASE.AMP.'C=myaccount'.AMP.'id='.$data['order_payments'][$key]['member_id'];
			$data['order_payments'][$key]['payment_actions'] = '';

			$payment_actions_form = form_open($data['post_url']).form_hidden('payment_id', $payment['payment_id']);

			switch ($payment['payment_status'])
			{
				case 'authorized':
					if ($this->EE->store_payments->load($payment['payment_method']) AND
						$this->EE->merchant->can_capture())
					{
						$data['order_payments'][$key]['payment_actions'] = $payment_actions_form.
							form_submit(array(
								'name' => 'capture_payment',
								'class' => 'capture_payment',
								'value' => lang('capture_payment'),
								'data-store-confirm' => lang('capture_payment_confirm'))).
							form_close();
					}
					break;
				case 'complete':
					if ($this->EE->store_payments->load($payment['payment_method']) AND
						$this->EE->merchant->can_refund())
					{
						$data['order_payments'][$key]['payment_actions'] = $payment_actions_form.
							form_submit(array(
								'name' => 'refund_payment',
								'class' => 'refund_payment',
								'value' => lang('refund_payment'),
								'data-store-confirm' => lang('refund_payment_confirm'))).
							form_close();
					}
					break;
			}
		}

		foreach ($data['order_statuses'] as $key => $status)
		{
			$data['order_statuses'][$key]['screen_name'] = empty($data['order_statuses'][$key]['screen_name']) ? 'System' : $data['order_statuses'][$key]['screen_name'];
			$data['order_statuses'][$key]['member_link'] = BASE.AMP.'C=myaccount'.AMP.'id='.$data['order_statuses'][$key]['order_status_member'];
			$data['order_statuses'][$key]['color'] = $this->EE->store_orders_model->get_status_color($data['order_statuses'][$key]['order_status']);
		}

		if ($this->EE->input->get('export') == 'pdf')
		{
			$data['report_title'] = $this->EE->store_config->item('order_details_header');
			if (empty($data['report_title'])) $data['report_title'] = lang('order_details');
			$data['header_right'] = $this->EE->store_config->item('order_details_header_right');
			$data['footer'] = $this->EE->store_config->item('order_details_footer');

			$html = $this->EE->load->view('order_details_pdf', $data, TRUE);

			$this->EE->load->library('store_pdf');
			$this->EE->store_pdf->output($html, lang('order').' '.$order_id.'.pdf');
		}
		else
		{
			return $this->EE->load->view('order_details', $data, TRUE);
		}
	}

	protected function _capture_or_refund_payment($order, $payment_id)
	{
		$payment = $this->EE->store_orders_model->get_payment_by_id($payment_id);

		if (empty($payment))
		{
			$this->EE->session->set_flashdata('message_failure', lang('invalid_payment_id'));
		}
		elseif (isset($_POST['capture_payment']))
		{
			$result = $this->EE->store_payments->capture($order, $payment);
			if (empty($result))
			{
				$this->EE->session->set_flashdata('message_failure', lang('payment_capture_failure'));
			}
			elseif ($result->success())
			{
				$this->EE->session->set_flashdata('message_success', lang('payment_capture_success'));
			}
			else
			{
				$this->EE->session->set_flashdata('message_failure',
					lang('payment_capture_failure').' ('.$result->message().')');
			}
		}
		elseif (isset($_POST['refund_payment']))
		{
			$result = $this->EE->store_payments->refund($order, $payment);
			if (empty($result))
			{
				$this->EE->session->set_flashdata('message_failure', lang('payment_refund_failure'));
			}
			elseif ($result->success())
			{
				$this->EE->session->set_flashdata('message_success', lang('payment_refund_success'));
			}
			else
			{
				$this->EE->session->set_flashdata('message_failure',
					lang('payment_refund_failure').' ('.$result->message().')');
			}
		}

		$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders'.AMP.'order_id='.$order['order_id'].'#payments');
	}

	public function delete_orders($order_ids = NULL)
	{

		if (empty($order_ids) AND !$this->EE->input->post('orders_to_delete'))
		{
			$this->EE->session->set_flashdata('message_failure', lang('invalid_order_ids'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
		}

		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=orders', lang('orders'));
		$this->set_title(lang('delete_confirm'));

		$data = array(
					'post_url' => STORE_CP.AMP.'method=delete_orders',
					'order_ids' => $order_ids,
				);

		if ($this->EE->input->post('orders_to_delete'))
		{
			$this->EE->store_orders_model->remove_orders($this->EE->input->post('orders_to_delete'), TRUE);

			if (count($this->EE->input->post('orders_to_delete')) == 1)
			{
				$this->EE->session->set_flashdata('message_success', lang('order_deleted'));
			}
			else
			{
				$this->EE->session->set_flashdata('message_success', lang('orders_deleted'));
			}
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
		}

		$tdata = array();
		$query = $this->EE->store_orders_model->get_orders_by_id($order_ids);

		foreach ($query as $row)
		{
			$m = array(
					$row['order_id'],
					"<strong><a href='".BASE.AMP.'C=myaccount'.AMP.'id='.$row['member_id']."'>{$row['screen_name']}</a></strong>",
					$row['billing_name'],
					$this->EE->store_config->human_time($row['order_date']),
					$row['order_total'],
					$row['order_paid_str'],
					'<span style="color:#'.$this->EE->store_orders_model->get_status_color($row['order_status']).'">'.lang($row['order_status']).'</span>',
			);
			$tdata[] = $m;
		}

		$data['orders'] = $tdata;

		return $this->EE->load->view('order_delete', $data, TRUE);
	}

	public function add_payment()
	{
		$this->_require_privilege('can_add_payments');
		$order_id = (int)$this->EE->input->get('order_id');
		if ($order_id == 0)
		{
			$this->EE->session->set_flashdata('message_failure', lang('invalid_order_id'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
		}

		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=orders', lang('orders'));
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=orders'.AMP.'order_id='.$order_id, lang('order').' #'.$order_id);
		$this->set_title(lang('add_payment'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=add_payment'.AMP.'order_id='.$order_id,
			'order' => $this->EE->store_orders_model->find_by_id($order_id)
		);

		if (empty($data['order']))
		{
			$this->EE->session->set_flashdata('message_failure', lang('invalid_order_id'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders');
		}

		$this->EE->form_validation->set_rules('payment[amount]', 'lang:amount', 'required|store_currency_non_zero');
		$this->EE->form_validation->set_rules('payment[payment_date]', 'lang:payment_date', 'required');

		if ($this->EE->form_validation->run() === TRUE)
		{
			$payment = $this->EE->input->post('payment', TRUE);
			$amount = store_round_currency(store_parse_currency($payment['amount']), TRUE);

			$this->EE->store_orders_model->add_manual_payment($data['order'], $payment['payment_date'], $amount, $payment['message'], $payment['reference']);

			$this->EE->session->set_flashdata('message_success', lang('payment_added'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=orders'.AMP.'order_id='.$order_id, lang('order_details'));
		}

		$this->EE->cp->add_js_script(array('ui' => 'datepicker'));

		return $this->EE->load->view('add_payment', $data, TRUE);
	}

	public function settings()
	{
		if ( ! $this->EE->store_config->site_enabled())
		{
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=install');
		}

		$this->_require_privilege('can_access_settings');
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=settings', lang('settings'));

		$settings_url = BASE.AMP.STORE_CP.AMP.'method=settings';
		$data = array(
			'pages' => array(
				'general' => $settings_url,
				'reports' => $settings_url.AMP.'page=reports',
				'email' => $settings_url.AMP.'page=email',
				'promo_codes' => $settings_url.AMP.'page=promo_codes',
				'order_fields' => $settings_url.AMP.'page=order_fields',
				'order_statuses' => $settings_url.AMP.'page=order_statuses',
				'payment' => $settings_url.AMP.'page=payment',
				'shipping' => $settings_url.AMP.'page=shipping',
				'regions' => $settings_url.AMP.'page=regions',
				'tax' => $settings_url.AMP.'page=tax',
				'conversions' => $settings_url.AMP.'page=conversions',
				'security' => $settings_url.AMP.'page=security'
			)
		);

		$page = $this->EE->input->get('page');
		if (empty($page))
		{
			$data['current_page'] = 'general';
		}
		elseif (in_array($page, array_keys($data['pages'])))
		{
			$data['current_page'] = $page;
		}
		else
		{
			// invalid page specified
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings');
		}

		$data['content'] = $this->{'_settings_'.$data['current_page']}();

		return $this->EE->load->view('settings/base', $data, TRUE);
	}

	private function _settings_general()
	{
		$this->set_title(lang('settings_general'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings',
			'settings' => array(),
			'setting_defaults' => array(),
		);

		// check for submitted general form
		if ( ! empty($_POST))
		{
			// load submitted settings
			$settings = $this->EE->input->post('settings', TRUE);
			foreach ($settings as $key => $value)
			{
				$this->EE->store_config->set_item($key, $value);
			}

			$this->EE->store_config->save();
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		// generate setting inputs
		foreach (array('currency_symbol', 'currency_suffix', 'currency_decimals', 'currency_dec_point',
			'currency_thousands_sep', 'currency_code', 'weight_units', 'dimension_units', 'from_email',
			'from_name', 'default_order_address', 'cc_payment_method', 'tax_rounding',
			'force_member_login', 'report_stats', 'cart_expiry', 'empty_cart_on_logout', 'secure_template_tags',
			'order_invoice_url') as $key)
		{
			$data['settings'][$key] = $this->EE->store_config->item($key);
			$data['setting_defaults'][$key] = $this->EE->store_config->item_config($key);
		}

		return $this->EE->load->view('settings/general', $data, TRUE);
	}

	private function _settings_reports()
	{
		$this->set_title(lang('settings_reports'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=reports',
			'settings' => array(),
			'setting_defaults' => array(),
		);

		// check for submitted general form
		if ( ! empty($_POST))
		{
			// load submitted settings
			$settings = $this->EE->input->post('settings', TRUE);
			foreach ($settings as $key => $value)
			{
				$this->EE->store_config->set_item($key, $value);
			}

			$this->EE->store_config->save();
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		foreach (array('export_pdf_orientation','export_pdf_page_format', 'order_details_header',
			'order_details_header_right', 'order_details_footer') as $key)
		{
			$data['settings'][$key] = $this->EE->store_config->item($key);
			$data['setting_defaults'][$key] = $this->EE->store_config->item_config($key);
		}

		return $this->EE->load->view('settings/general', $data, TRUE);
	}

	private function _settings_conversions()
	{
		$this->set_title(lang('settings_conversions'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=conversions',
			'settings' => array(),
			'setting_defaults' => array(),
		);

		// check for submitted general form
		if ( ! empty($_POST))
		{
			// load submitted settings (don't run through XSS filter)
			$settings = $this->EE->input->post('settings');
			foreach ($settings as $key => $value)
			{
				$this->EE->store_config->set_item($key, $value);
			}

			$this->EE->store_config->save();
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		// generate setting inputs
		foreach (array('google_analytics_ecommerce', 'conversion_tracking_extra') as $key)
		{
			$data['settings'][$key] = $this->EE->store_config->item($key);
			$data['setting_defaults'][$key] = $this->EE->store_config->item_config($key);
		}

		return $this->EE->load->view('settings/general', $data, TRUE);
	}

	private function _settings_email()
	{
		$this->set_title(lang('settings_email'));

		// are we editing an existing email template?
		$template_id = $this->EE->input->get('template_id');
		if ($template_id !== FALSE)
		{
			return $this->_settings_email_edit($template_id);
		}

		$results = $this->EE->store_common_model->get_all_email_templates();

		$data = array(
					'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=email',
					'new_email_template_link' => BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=email'.AMP.'template_id=new',
					'email_template_link' => BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=email'.AMP.'template_id=',
					'with_selected_options' => array( 'enable' => lang('enable_selected'), 'disable' => lang('disable_selected'), 'delete' => lang('delete_selected')),
					'templates' => $results,
				);

		if ( ! empty($_POST))
		{
			$selected_ids = $this->EE->input->post('selected', TRUE);
			if ( ! is_array($selected_ids))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_template_ids'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			foreach ($selected_ids as $key => $value)
			{
				$selected_ids[$key] = (int)$value;
			}

			switch ($this->EE->input->post('with_selected', TRUE))
			{
				case 'enable':
					$this->EE->store_common_model->enable_email_templates($selected_ids);
					break;
				case 'disable':
					$this->EE->store_common_model->disable_email_templates($selected_ids);
					break;
				case 'delete':
					foreach ($selected_ids as $id)
					{
						if (isset($results[$id]) AND $results[$id]['locked'] == 'y')
						{
							$this->EE->session->set_flashdata('message_failure', sprintf(lang('no_system_email_delete'), lang($results[$id]['name'])));
							$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
						}
					}

					$this->EE->store_common_model->delete_email_templates($selected_ids);
					break;
				default:
					$this->EE->session->set_flashdata('message_failure', lang('invalid_selection'));
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		return $this->EE->load->view('settings/email', $data, TRUE);
	}

	private function _settings_email_edit($template_id)
	{
		$this->EE->lang->loadfile('communicate');
		$this->EE->load->model('member_model');
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=email', lang('settings_email'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=email'.AMP.'template_id='.$template_id,
		);

		if ($template_id == 'new')
		{
			// display add group page
			$this->set_title(lang('new_email_template'));

			$data['template'] = array(
									'name' => '',
									'bcc' => '',
									'subject' => '',
									'contents' => '',
									'mail_format' => 'text',
									'word_wrap' => 'y',
									'enabled' => 'y',
									'locked' => 'n'
			);
		}
		else
		{
			// display edit group page
			$template_id = (int)$template_id;
			$this->set_title(lang('settings_edit_email'));

			$data['template'] = $this->EE->store_common_model->get_email_template($template_id);

			if (empty($data['template']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_template_id'));
				$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=email');
			}
		}

		$this->EE->form_validation->set_rules('name', 'lang:name', 'required');
		$this->EE->form_validation->set_rules('subject', 'lang:subject', 'required');
		$this->EE->form_validation->set_rules('contents', 'lang:message', 'required');
		$this->EE->form_validation->set_rules('bcc', 'lang:bcc', 'valid_emails');

		// validation
		if ($this->EE->form_validation->run() === TRUE)
		{
			// DON'T run the email through XSS filter - it breaks inline CSS
			$email_template = $_POST;

			if ($data['template']['locked'] == 'y')
			{
				unset($email_template['name']);
			}

			// insert or update
			if ($template_id == 'new')
			{
				$this->EE->store_common_model->insert_email_template($email_template);
			}
			else
			{
				$this->EE->store_common_model->update_email_template($template_id, $email_template);
			}

			// redirect
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=email');
		}

		return $this->EE->load->view('settings/email_edit', $data, TRUE);
	}

	private function _settings_promo_codes()
	{
		$this->set_title(lang('settings_promo_codes'));

		// are we editing an existing promo code?
		$promo_code_id = $this->EE->input->get('promo_code_id');
		if ($promo_code_id !== FALSE)
		{
			return $this->_settings_promo_codes_edit($promo_code_id);
		}

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=promo_codes',
			'with_selected_options' => array(
				'enable' => lang('enable_selected'),
				'disable' => lang('disable_selected'),
				'delete' => lang('delete_selected'))
		);

		$data['new_promo_code_link'] = BASE.AMP.$data['post_url'].AMP.'promo_code_id=new';
		$data['promo_codes'] = $this->EE->store_common_model->get_all_promo_codes();

		foreach ($data['promo_codes'] as $key => $promo_code)
		{
			$data['promo_codes'][$key]['edit_link'] = BASE.AMP.$data['post_url'].AMP.'promo_code_id='.$promo_code['promo_code_id'];
		}

		if ( ! empty($_POST))
		{
			$selected_ids = $this->EE->input->post('selected');
			if ( ! is_array($selected_ids))
			{
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			foreach ($selected_ids as $key => $value) { $selected_ids[$key] = (int)$value; }

			switch ($this->EE->input->post('with_selected'))
			{
				case 'enable':
					$this->EE->store_common_model->enable_promo_codes($selected_ids);
					break;
				case 'disable':
					$this->EE->store_common_model->disable_promo_codes($selected_ids);
					break;
				case 'delete':
					$this->EE->store_common_model->delete_promo_codes($selected_ids);
					break;
				default:
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		return $this->EE->load->view('settings/promo_codes', $data, TRUE);
	}

	private function _settings_promo_codes_edit($promo_code_id)
	{
		$this->EE->load->model('member_model');
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=promo_codes', lang('settings_promo_codes'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=promo_codes'.AMP.'promo_code_id='.$promo_code_id,
		);

		if ($promo_code_id == 'new')
		{
			// display add group page
			$this->set_title(lang('new_promo_code'));

			$data['promo_code'] = array(
				'promo_code' => '',
				'description' => '',
				'type' => '',
				'value_str' => '',
				'free_shipping' => 'n',
				'start_date' => '',
				'end_date' => '',
				'use_limit' => '',
				'per_user_limit' => '',
				'member_group_id' => '',
				'enabled' => 'y'
			);
		}
		else
		{
			// display edit group page
			$this->set_title(lang('edit_promo_code'));

			$promo_code_id = (int)$promo_code_id;
			$data['promo_code'] = $this->EE->store_common_model->get_promo_code_by_id($promo_code_id, TRUE);
			if (empty($data['promo_code']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_promo_code'));
				$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=promo_codes');
			}
		}

		$member_groups = $this->EE->member_model->get_member_groups()->result_array();

		$data['member_groups'] = array(0 => lang('all'));
		foreach($member_groups as $row)
		{
			$data['member_groups'][$row['group_id']] = $row['group_title'];
		}

		//validation rules
		$this->EE->form_validation->set_rules('promo_code[value]', 'lang:promo_value', 'required');

		// handle form submit
		if ($this->EE->form_validation->run() === TRUE)
		{
			$code = $this->EE->input->post('promo_code', TRUE);
			$promo_code = $this->EE->input->post('promo_code');
			$promo_code['value'] = store_parse_currency($promo_code['value']);
			$promo_code['start_date'] = strtotime($promo_code['start_date']);
			$promo_code['end_date'] = strtotime($promo_code['end_date']);

			// insert or update
			if ($promo_code_id == 'new')
			{
				$promo_code['use_count'] = 0;
				$this->EE->store_common_model->insert_promo_code($promo_code);
			}
			else
			{
				$this->EE->store_common_model->update_promo_code($promo_code_id, $promo_code);
			}

			// redirect
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=promo_codes');
		}

		$this->EE->cp->add_js_script(array('ui' => 'datepicker'));
		return $this->EE->load->view('settings/promo_codes_edit', $data, TRUE);
	}

	private function _settings_order_fields()
	{
		$this->EE->load->model('store_orders_model');
		$this->set_title(lang('settings_order_fields'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=order_fields',
			'order_fields' => $this->EE->store_config->get_order_fields(),
			'member_fields' => $this->EE->store_common_model->get_member_fields_select()
		);

		// check for submitted form
		if ( ! empty($_POST))
		{
			if ($this->EE->input->post('restore_defaults'))
			{
				$data['order_fields'] = $this->EE->store_config->get_order_fields(TRUE);
			}
			else
			{
				$post_order_fields = $this->EE->input->post('order_fields', TRUE);
				foreach ($data['order_fields'] as $field_name => $field)
				{
					if (isset($field['title']))
					{
						$data['order_fields'][$field_name]['title'] = isset($post_order_fields[$field_name]['title']) ? $post_order_fields[$field_name]['title'] : '';
					}

					$data['order_fields'][$field_name]['member_field'] = isset($post_order_fields[$field_name]['member_field']) ? $post_order_fields[$field_name]['member_field'] : '';
				}
			}

			// update database and redirect
			$this->EE->store_config->set_item('order_fields', $data['order_fields']);
			$this->EE->store_config->save();

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		return $this->EE->load->view('settings/order_fields', $data, TRUE);
	}

	private function _settings_order_statuses()
	{
		$this->EE->load->model('store_orders_model');
		$this->set_title(lang('settings_order_statuses'));

		$order_status_id = $this->EE->input->get('order_status_id');
		if ($order_status_id !== FALSE)
		{
			return $this->_settings_order_status_edit((int)$order_status_id);
		}

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses',
			'statuses' => $this->EE->store_orders_model->get_order_statuses(),
			'new_order_status_link' => BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses'.AMP.'order_status_id=new',
		);

		foreach ($data['statuses'] as $key => $status)
		{
			$data['statuses'][$key]['edit_link'] = BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses'.AMP.'order_status_id='.$status['order_status_id'];
		}

		$this->EE->cp->add_js_script(array('ui' => 'sortable'));
		$this->EE->javascript->compile();
		return $this->EE->load->view('settings/order_statuses', $data, TRUE);
	}

	private function _settings_order_status_edit($order_status_id)
	{
		$this->EE->lang->loadfile('communicate');
		$this->EE->load->model('member_model', 'store_orders_model');
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses', lang('order_statuses'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses'.AMP.'order_status_id='.$order_status_id,
			'editable' => $this->EE->store_orders_model->get_status_editable($order_status_id),
			'email_templates' => array(0 => '')
		);

		$templates = $this->EE->store_common_model->get_all_email_templates();
		foreach ($templates as $template)
		{
			$data['email_templates'][$template['template_id']] = lang($template['name']);
		}

		if ($order_status_id == 0)
		{
			// display add status page
			$this->set_title(lang('new_order_status'));

			$data['status'] = array(
				'name' => '',
				'highlight' => '',
				'email_template' => '',
				'is_default' => ''
			);
		}
		else
		{
			// display edit status page
			$data['status'] = $this->EE->store_orders_model->get_order_status($order_status_id);
			if (empty($data['status']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_order_status'));
				$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses');
			}

			$this->set_title(lang($data['status']['name']));
		}

		// is this a delete status request?
		if ($this->EE->input->post('delete') AND $data['editable'] AND $data['status']['is_default'] != 'y')
		{
			$this->EE->store_orders_model->delete_status($order_status_id);

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses');
		}

		// validate POST data
		if (isset($_POST['status']['highlight']))
		{
			$_POST['status']['highlight'] = preg_replace('/[^a-z0-9]*/i', '', $_POST['status']['highlight']);
		}

		// Form validation needs at least one rule to run and be TRUE so in case status is not
		// editable we add this default rule that will always pass.
		$this->EE->form_validation->set_rules('submit', 'lang:submit', 'required');

		// status name is requred if status is editable
		if ($data['editable'])
		{
			$this->EE->form_validation->set_rules('status[name]', 'lang:status_name', 'required');
		}

		$data['duplicate_name'] = FALSE;
		if ($data['editable'] AND ! empty($_POST))
		{
			$status = $this->EE->input->post('status', TRUE);
			$result = $this->EE->store_orders_model->check_duplicate_status_name($status['name'], $order_status_id);
			$data['duplicate_name'] = ! empty($result);
		}

		// check for form submission
		if ($this->EE->form_validation->run() === TRUE AND ! $data['duplicate_name'])
		{
			$status = $this->EE->input->post('status', TRUE);

			if ( ! $data['editable']) { unset($status['name']); }
			if ($data['status']['is_default'] == 'y') { $status['is_default'] = 'y'; }

			$this->EE->store_orders_model->update_status($order_status_id, $status);

			// redirect
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=order_statuses');
		}

		return $this->EE->load->view('settings/order_status_edit', $data, TRUE);
	}

	private function _settings_payment()
	{
		$this->EE->load->library('store_payments');

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=payment',
		);

		if ($payment_method = $this->EE->input->get('payment_method'))
		{
			return $this->_settings_payment_edit($payment_method, $data);
		}

		$this->set_title(lang('settings_payment'));

		if (isset($_POST['submit']))
		{
			$selected_ids = $this->EE->input->post('selected');
			if ( ! is_array($selected_ids))
			{
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			switch ($this->EE->input->post('with_selected'))
			{
				case 'enable':
					$this->EE->store_payments_model->enable_payment_methods($selected_ids);
					break;
				case 'disable':
					$this->EE->store_payments_model->disable_payment_methods($selected_ids);
					break;
				case 'delete':
					$this->EE->store_payments_model->delete_payment_methods($selected_ids);
					break;
				default:
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$valid_drivers = $this->EE->merchant->valid_drivers();
		$data['payment_methods'] = $this->EE->store_payments_model->find_all_payment_methods();
		foreach ($data['payment_methods'] as $key => $payment_method)
		{
			$data['payment_methods'][$key]['settings_link'] = BASE.AMP.$data['post_url'].AMP.'payment_method='.$payment_method['payment_method_id'];
			$data['payment_methods'][$key]['missing'] = !in_array(str_replace('Merchant_', '', $payment_method['class']), $valid_drivers);
		}

		$data['add_payment_method_link'] = BASE.AMP.$data['post_url'].AMP.'payment_method=new';

		return $this->EE->load->view('settings/payment', $data, TRUE);
	}

	private function _settings_payment_edit($payment_method_id, $data)
	{
		$this->add_breadcrumb(BASE.AMP.$data['post_url'], lang('settings_payment'));

		$data['payment_class_options'] = array('' => '');
		$data['payment_drivers'] = array();

		if ($payment_method_id == 'new')
		{
			$data['payment_method'] = array(
				'payment_method_id' => NULL,
				'class' => NULL,
				'name' => NULL,
				'title' => NULL,
				'settings' => array(),
				'enabled' => TRUE,
			);

			$this->set_title(lang('add_payment_method'));

			// loop through drivers
			$valid_drivers = $this->EE->merchant->valid_drivers();
			$payment_drivers_json = array();
			foreach ($valid_drivers as $driver)
			{
				$this->EE->merchant->load($driver);
				$driver_class = "Merchant_$driver";

				// available drivers select
				$data['payment_class_options'][$driver_class] = lang("merchant_$driver");

				// settings for all available drivers
				$data['payment_drivers'][] = array(
					'class' => $driver_class,
					'settings' => $this->EE->merchant->settings(),
					'default_settings' => $this->EE->merchant->default_settings(),
				);

				// json to pre-populate name and title inputs
				$payment_drivers_json[$driver_class] = array(
					'name' => $driver,
					'title' => lang("merchant_$driver"),
				);
			}

			$this->EE->cp->add_to_foot('
				<script type="text/javascript">
				ExpressoStore.payment_drivers = '.json_encode($payment_drivers_json).';
				</script>');
		}
		else
		{
			$payment_method_id = (int)$payment_method_id;
			$data['payment_method'] = $this->EE->store_payments_model->find_payment_method_by_id($payment_method_id);

			if (empty($data['payment_method']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_payment_method'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->merchant->load($data['payment_method']['class']) OR show_error(lang('payment_plugin_load_error'));
			$this->EE->merchant->initialize($data['payment_method']['settings']);

			$this->set_title(lang($data['payment_method']['title']));

			$data['payment_drivers'][] = array(
				'class' => $data['payment_method']['class'],
				'settings' => $this->EE->merchant->settings(),
				'default_settings' => $this->EE->merchant->default_settings(),
			);
		}

		// check for submitted data
		$this->EE->form_validation->set_rules('payment_method[class]', 'lang:payment_plugin', 'required');
		$this->EE->form_validation->set_rules('payment_method[name]', 'lang:short_name', 'required');
		$this->EE->form_validation->set_rules('payment_method[title]', 'lang:name', 'required');

		if ($payment_method_id == 'new')
		{
			$this->EE->form_validation->set_rules('payment_method[name]', 'lang:short_name', 'required|unique_payment_method_name');
		}

		if ($this->EE->form_validation->run() === TRUE)
		{
			$payment_method = $this->EE->input->post('payment_method', TRUE);

			$settings = $this->EE->input->post('settings', TRUE);
			$this->EE->merchant->load($payment_method['class']) OR show_error(lang('payment_plugin_load_error'));
			$this->EE->merchant->initialize($settings);

			$payment_method['settings'] = base64_encode(serialize($this->EE->merchant->settings()));

			if ($payment_method_id == 'new')
			{
				$this->EE->store_payments_model->insert_payment_method($payment_method);
			}
			else
			{
				$this->EE->store_payments_model->update_payment_method($payment_method_id, $payment_method);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=payment');
		}

		$data['post_url'] .= AMP.'payment_method='.$payment_method_id;

		return $this->EE->load->view('settings/payment_edit', $data, TRUE);
	}

	private function _settings_shipping()
	{
		$this->EE->load->library('store_shipping');

		$data = array(
			'page_title' => lang('settings_shipping'),
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=shipping',
			'add_plugin_link' => BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=shipping'.AMP.'shipping_method=new',
		);

		if (isset($_GET['shipping_method']))
		{
			return $this->_settings_shipping_edit($data);
		}

		if (isset($_GET['plugin_settings']))
		{
			return $this->_settings_shipping_instance($data);
		}

		if (isset($_POST['submit_default']))
		{
			$default_shipping_method_id = (int)$this->EE->input->post('default_shipping_method_id');
			$this->EE->store_config->set_item('default_shipping_method_id', $default_shipping_method_id);
			$this->EE->store_config->save();

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}
		elseif (isset($_POST['submit']))
		{
			$selected_ids = $this->EE->input->post('selected');
			if ( ! is_array($selected_ids))
			{
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			foreach ($selected_ids as $key => $value) { $selected_ids[$key] = (int)$value; }

			switch ($this->EE->input->post('with_selected'))
			{
				case 'enable':
					$this->EE->store_shipping_model->enable_shipping_methods($selected_ids);
					break;
				case 'disable':
					$this->EE->store_shipping_model->disable_shipping_methods($selected_ids);
					break;
				case 'delete':
					foreach ($selected_ids as $selected_id)
					{
						if ($this->EE->store_shipping->load($selected_id, TRUE))
						{
							$this->EE->store_shipping->delete();
						}
					}
					break;
				default:
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$this->set_title($data['page_title']);

		// display list of plugins
		$data['shipping_methods'] = $this->EE->store_shipping_model->get_all_shipping_methods();
		$data['shipping_method_select'] = array('' => lang('none'));

		foreach ($data['shipping_methods'] as $key => $plugin)
		{
			$data['shipping_methods'][$key]['edit_link'] = BASE.AMP.$data['post_url'].AMP.'shipping_method='.$plugin['shipping_method_id'];
			$data['shipping_methods'][$key]['settings_link'] = BASE.AMP.$data['post_url'].AMP.'plugin_settings='.$plugin['shipping_method_id'];
			$data['shipping_method_select'][$plugin['shipping_method_id']] = $plugin['title'];
		}

		$data['default_shipping_method_id'] = $this->EE->store_config->item('default_shipping_method_id');

		$this->EE->cp->add_js_script(array('ui' => 'sortable'));
		$this->EE->javascript->compile();

		return $this->EE->load->view('settings/shipping', $data, TRUE);
	}

	private function _settings_shipping_edit($data)
	{
		$shipping_method_id = $this->EE->input->get('shipping_method', TRUE);
		if ($shipping_method_id == 'new')
		{
			$data['shipping_method'] = array(
				'title' => '',
				'class' => '',
				'enabled' => 1
			);
		}
		else
		{
			$shipping_method_id = (int)$shipping_method_id;
			$data['shipping_method'] = $this->EE->store_shipping_model->get_shipping_method($shipping_method_id);

			if (empty($data['shipping_method']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_shipping_method'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}
		}

		$this->EE->form_validation->set_rules('shipping_method[title]', 'lang:title', 'required');
		$this->EE->form_validation->set_rules('shipping_method[class]', "lang:shipping_plugin", 'required');

		if ($this->EE->form_validation->run() === TRUE)
		{
			$instance = $this->EE->input->post('shipping_method', TRUE);

			// insert or update
			if ($shipping_method_id == 'new')
			{
				$shipping_method_id = $this->EE->store_shipping_model->insert_shipping_method($instance);

				// if a default shipping plugin was created, add a blank rule to get started
				if ($instance['class'] == 'Store_shipping_default')
				{
					$this->EE->store_shipping_model->insert_shipping_rule(array(
						'shipping_method_id' => $shipping_method_id,
						'enabled' => '1',
					));
				}

				$data['post_url'] = STORE_CP.AMP.'method=settings'.AMP.'page=shipping'.AMP.'plugin_settings='.$shipping_method_id;
				$this->EE->session->set_flashdata('store_shipping_rule', lang('add_rule_now'));
			}
			else
			{
				unset($instance['class']);
				$this->EE->store_shipping_model->update_shipping_method($shipping_method_id, $instance);
				$data['post_url'] = STORE_CP.AMP.'method=settings'.AMP.'page=shipping';
			}

			// redirect to new plugin edit information / shipping rules if was a new plugin
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$this->add_breadcrumb(BASE.AMP.$data['post_url'], $data['page_title']);
		if ($shipping_method_id == 'new')
		{
			$this->set_title(lang('shipping_add_plugin'));
		}
		else
		{
			$this->set_title($data['shipping_method']['title']);
		}

		$data['shipping_class_select'] = $this->EE->store_shipping->valid_drivers_select();
		$data['post_url'] .= AMP.'shipping_method='.$shipping_method_id;

		return $this->EE->load->view('settings/shipping_edit', $data, TRUE);
	}

	private function _settings_shipping_instance($data)
	{
		$shipping_method_id = (int)$this->EE->input->get('plugin_settings');

		if ($this->EE->store_shipping->load($shipping_method_id) === FALSE)
		{
			$this->EE->session->set_flashdata('message_failure', lang('invalid_shipping_method'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$this->add_breadcrumb(BASE.AMP.$data['post_url'], $data['page_title']);
		$this->set_title($this->EE->store_shipping->title());

		$data['back_url'] = BASE.AMP.$data['post_url'];
		$data['post_url'] .= AMP.'plugin_settings='.$shipping_method_id;
		$plugin = $this->EE->store_shipping_model->get_shipping_method($shipping_method_id);
		$data['shipping_method_id'] = $shipping_method_id;
		return $this->EE->store_shipping->display_settings($data);
	}

	private function _settings_regions()
	{
		$country_code = $this->EE->input->get('country', TRUE);
		if ($country_code !== FALSE)
		{
			$country = $this->EE->store_shipping_model->get_country_by_code($country_code);
			if ($country === FALSE)
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_country_id'));
				$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=regions');
			}
			$country['new_regions'] = array();
			$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=regions', lang('settings_regions'));
			$this->set_title($country['name']);

			$data = array(
				'country' => $country,
				'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=regions'.AMP.'country='.$country_code,
				'duplicate_region_codes' => array()
			);

			// check for submitted form
			if ( ! empty($_POST))
			{
				foreach (array('regions', 'new_regions') as $field)
				{
					$field_post = $this->EE->input->post($field, TRUE);
					if (is_array($field_post))
					{
						foreach ($field_post as $key => $value)
						{
							if (empty($value['name']) AND empty($value['code']))
							{
								continue;
							}
							else
							{
								$this->EE->form_validation->set_rules($field.'['.$key.'][name]', 'lang:name', 'required');
								$this->EE->form_validation->set_rules($field.'['.$key.'][code]', 'lang:code', 'required|max_length[5]');
							}
						}
					}
				}

				// Form validation needs at least one rule to run and be TRUE so incase no other
				// rules are were added this default rule makes validation always pass.
				$this->EE->form_validation->set_rules('submit', 'lang:submit', 'required');
				$data['duplicate_region_codes'] = $this->_unique_region_codes($this->EE->input->post('regions', TRUE), $this->EE->input->post('new_regions', TRUE));

				if ($this->EE->form_validation->run() === TRUE AND empty($data['duplicate_region_codes']))
				{
					$this->EE->store_shipping_model->update_regions($country_code, $this->EE->input->post('regions', TRUE));
					$this->EE->store_shipping_model->insert_regions($country_code, $this->EE->input->post('new_regions', TRUE));

					$this->EE->session->set_flashdata('message_success', lang('regions_updated'));
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
				}
				foreach (array('regions', 'new_regions') as $region_type)
				{
					if (isset($_POST[$region_type]))
					{
						$data['country'][$region_type] = $_POST[$region_type];
					}
				}
			}

			return $this->EE->load->view('settings/country_edit', $data, TRUE);
		}
		else
		{
			$this->set_title(lang('settings_regions'));

			$data = array(
				'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=regions',
				'enabled_countries' => $this->EE->store_shipping_model->get_countries(TRUE),
				'disabled_countries' => array()
			);

			$enabled_countries = $this->EE->store_shipping_model->get_countries(TRUE, TRUE);
			$data['country_select'][''] = '';
			$data['region_select'][''] = '';
			if (! empty($enabled_countries))
			{
				$data['default_country'] = $this->EE->store_config->item('default_country');
				foreach ($enabled_countries as $code => $country)
				{
					$data['country_select'][$code] = $country['name'];
				}

				$data['default_region'] = $this->EE->store_config->item('default_region');
				if (! empty($enabled_countries[$data['default_country']]['regions']))
				{
					foreach ($enabled_countries[$data['default_country']]['regions'] as $code => $region)
					{
						$data['region_select'][$code] = $region;
					}
				}
			}
			else
			{
				$data['default_country'] = '';
				$data['default_region'] = '';
			}

			if ( ! empty($_POST['selected']))
			{
				$selected = $this->EE->input->post('selected', TRUE);

				// use the top or bottom action dropdowns, depending on submit button clicked
				if ($this->EE->input->post('submit_top'))
				{
					$action = $this->EE->input->post('action_top');
				}
				else
				{
					$action = $this->EE->input->post('action_bot');
				}

				if ($action == 'enable')
				{
					$this->EE->store_shipping_model->enable_countries($selected);
				}
				else
				{
					$this->EE->store_shipping_model->disable_countries($selected);
				}

				$this->EE->session->set_flashdata('message_success', lang('regions_updated'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			if ( ! empty($_POST['submit_default']))
			{
				$defaults = $this->EE->input->post('default', TRUE);
				$this->EE->store_config->set_item('default_country', isset($defaults['country_code']) ? $defaults['country_code'] : '');
				$this->EE->store_config->set_item('default_region', isset($defaults['region_code']) ? $defaults['region_code'] : '');
				$this->EE->store_config->save();

				$this->EE->session->set_flashdata('message_success', lang('regions_updated'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			foreach ($this->EE->store_shipping_model->countries as $key => $name)
			{
				if (isset($data['enabled_countries'][$key]))
				{
					$data['enabled_countries'][$key]['edit_link'] = BASE.AMP.STORE_CP.AMP.'method=settings'.AMP.'page=regions'.AMP.'country='.$key;
				}
				else
				{
					$data['disabled_countries'][$key] = array('name' => $name);
				}
			}

			$this->EE->javascript->output('
				ExpressoStore.countries = '.json_encode($enabled_countries).';
				$("#mainContent select.store_country_select").data("oldVal", "'.$data['default_country'].'").change();
			');
			$this->EE->javascript->compile();

			return $this->EE->load->view('settings/countries', $data, TRUE);
		}
	}

	private function _settings_tax()
	{
		$this->set_title(lang('settings_tax'));

		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=tax',
			'tax_rates' => $this->EE->store_shipping_model->get_tax_rates()
		);

		if (isset($_GET['tax_id']))
		{
			return $this->_settings_tax_edit($data);
		}

		$countries = $this->EE->store_shipping_model->get_countries(FALSE, TRUE);

		foreach ($data['tax_rates'] as $key => $tax)
		{
			$data['tax_rates'][$key]['tax_percent'] = $tax['tax_rate'] * 100;
			$data['tax_rates'][$key]['country_name'] = isset($countries[$tax['country_code']]['name']) ? $countries[$tax['country_code']]['name'] : lang('region_any');
			$data['tax_rates'][$key]['region_name'] = isset($countries[$tax['country_code']]['regions'][$tax['region_code']]) ? $countries[$tax['country_code']]['regions'][$tax['region_code']] : lang('region_any');
			$data['tax_rates'][$key]['edit_link'] = BASE.AMP.$data['post_url'].AMP.'tax_id='.$tax['tax_id'];
		}

		if ( ! empty($_POST))
		{
			$selected_ids = $this->EE->input->post('selected');
			if ( ! is_array($selected_ids))
			{
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			foreach ($selected_ids as $key => $value) { $selected_ids[$key] = (int)$value; }

			switch ($this->EE->input->post('with_selected'))
			{
				case 'enable':
					$this->EE->store_shipping_model->enable_tax_rates($selected_ids);
					break;
				case 'disable':
					$this->EE->store_shipping_model->disable_tax_rates($selected_ids);
					break;
				case 'delete':
					$this->EE->store_shipping_model->delete_tax_rates($selected_ids);
					break;
				default:
					$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$data['add_tax_rate_link'] = BASE.AMP.$data['post_url'].AMP.'tax_id=new';

		return $this->EE->load->view('settings/tax', $data, TRUE);
	}

	private function _settings_tax_edit($data)
	{
		$tax_id = $this->EE->input->get('tax_id');

		if ($tax_id == 'new')
		{
			$data['tax'] = array(
				'tax_name' => '',
				'country_code' => '*',
				'region_code' => '*',
				'tax_percent' => '',
				'tax_shipping' => 0,
				'enabled' => 1,
			);
		}
		else
		{
			$tax_id = (int)$tax_id;
			$data['tax'] = $this->EE->store_shipping_model->get_tax_rate($tax_id);
			if (empty($data['tax']))
			{
				$this->EE->session->set_flashdata('message_failure', lang('invalid_tax_rate'));
				$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
			}

			$data['tax']['tax_percent'] = $data['tax']['tax_rate'] * 100;
		}

		$this->EE->form_validation->set_rules('tax[tax_name]', "lang:tax_name", 'required');

		if ($this->EE->form_validation->run() === TRUE)
		{
			// insert/update shipping rule
			$tax_rate = $this->EE->input->post('tax', TRUE);

			if ($tax_id == 'new')
			{
				$this->EE->store_shipping_model->insert_tax_rate($tax_rate);
			}
			else
			{
				$this->EE->store_shipping_model->update_tax_rate($tax_id, $tax_rate);
			}

			// redirect
			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		$data['post_url'] .= AMP.'tax_id='.$tax_id;

		$data['country_select'] = array('*' => lang('region_any'));
		$data['region_select'] = array('*' => lang('region_any'));

		$enabled_countries = $this->EE->store_shipping_model->get_countries(TRUE, TRUE);
		$selected_country = $data['tax']['country_code'];
		foreach ($enabled_countries as $code => $country)
		{
			$data['country_select'][$code] = $country['name'];
		}
		if (isset($enabled_countries[$selected_country]))
		{
			$data['region_select'] = array_merge($data['region_select'], $enabled_countries[$selected_country]['regions']);
		}

		$this->EE->javascript->output('
			ExpressoStore.countries = '.json_encode($enabled_countries).';
			$("#mainContent select.store_country_select").data("oldVal", "'.$selected_country.'").change();
		');
		$this->EE->javascript->compile();

		return $this->EE->load->view('settings/tax_edit', $data, TRUE);
	}

	private function _settings_security()
	{
		$this->set_title(lang('settings_security'));
		$data = array(
			'post_url' => STORE_CP.AMP.'method=settings'.AMP.'page=security',
			'security' => $this->EE->store_config->get_security(),
			'member_groups' => $this->EE->member_model->get_member_groups(array(),array('can_access_cp' => 'y'))->result_array(),
		);

		if ( ! empty($_POST))
		{
			$security_settings = $this->EE->input->post('security', TRUE);

			$this->EE->store_config->set_item('security', $security_settings);
			$this->EE->store_config->save();

			$this->EE->session->set_flashdata('message_success', lang('settings_updated'));
			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		return $this->EE->load->view('settings/security', $data, TRUE);
	}



	/**
	 * Anonymously report EE & PHP versions used to improve the product.
	 */
	public function stats()
	{
		if (function_exists('curl_init'))
		{
			$data = http_build_query(array(
				// anonymous reference generated using one-way hash
				'site' => sha1($this->EE->config->item('license_number')),
				'product' => 'store',
				'version' => STORE_VERSION,
				'ee' => APP_VER,
				'php' => PHP_VERSION,
			));
			$this->EE->load->library('curl');
			$this->EE->curl->simple_post("http://hello.exp-resso.com/v1", $data);
		}

		// report again in 28 days
		$this->EE->store_config->set_item('report_date', $this->EE->localize->now + 28*24*60*60);
		$this->EE->store_config->save();
		exit('OK');
	}

	public function inventory()
	{
		if ( ! $this->EE->store_config->site_enabled())
		{
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=install');
		}

		$this->EE->load->model('store_products_model');

		$this->set_title(lang('inventory'));
		$this->_require_privilege('can_access_inventory');


		if ( $this->EE->input->post('store_product_field') != FALSE)
		{
			$products = $this->EE->input->post('store_product_field');
			foreach ($products as $entry_id => $product)
			{
				$this->EE->store_products_model->update_product($entry_id, $product);
			}
		}

		$cat_groups = $this->EE->store_common_model->get_product_categories_select();
		$options['0'] = 'Filter by Category';

		if (! empty($cat_groups))
		{
			foreach($cat_groups as $val)
			{
				$indent = ($val['5'] != 1) ? repeater(NBS.NBS.NBS.NBS, $val['5']) : '';
				$options[$val['0']] = $indent.$val['1'];
			}
		}
		$data = array(
			'post_url' => STORE_CP.AMP.'method=inventory',
			'search_form' => STORE_CP.AMP.'method=inventory',
			'inventory' => $this->inventory_datatable(TRUE),
			'product_category_select_options' => $options,
			'perpage_select_options' => array( '10' => '10 '.lang('results'), '25' => '25 '.lang('results'), '50' => '50 '.lang('results'), '75' => '75 '.lang('results'), '100' => '100 '.lang('results'), '150' => '150 '.lang('results')),
		);

		$this->_datatables_js(
			'inventory_datatable',
			$data['inventory']['non_sortable_columns'],
			$data['inventory']['clickable_columns'],
			$data['inventory']['default_sort'],
			$data['inventory']['perpage']
		);
		$this->EE->javascript->compile();

		return $this->EE->load->view('inventory', $data, TRUE);
	}

	public function inventory_datatable($return_data = FALSE)
	{
		$this->EE->load->model('store_products_model');

		$col_map = array('expand', 'store_products.entry_id', 'title', 'total_stock', 'regular_price', 'sale_price', 'sale_price_enabled', 'options', 'stock_table');

		$filters = array(
			'perpage' 		=> $this->EE->input->get_post('perpage') ? (int)$this->EE->input->get_post('perpage') : self::DATATABLES_PAGE_SIZE,
			'category_id'	=> (int)$this->EE->input->get_post('product_categories'),
			'keywords'		=> $this->EE->input->get_post('keywords', TRUE),
			'exact_match'	=> $this->EE->input->get_post('exact_match')
		);

		$filters['limit'] = $filters['perpage'];
		$filters['offset'] = (int)$this->EE->input->get_post('iDisplayStart');

		/* Ordering */
		if (($order_by = $this->EE->input->get('iSortCol_0')) !== FALSE)
		{
			if (isset($col_map[$order_by]))
			{
				$filters['order_by'] = $col_map[$order_by];
				$filters['sort'] = $this->EE->input->get('sSortDir_0');
			}
		}

		$query = $this->EE->store_products_model->find_all($filters);
		$total_filtered = $this->EE->store_products_model->find_all(array_merge($filters, array('count_all_results' => TRUE)));
		$total = $this->EE->store_products_model->total_products();

		$response = array(
			'aaData' => array(),
			'sEcho' => (int)$this->EE->input->get_post('sEcho'),
			'iTotalRecords' => $total,
			'iTotalDisplayRecords' => $total_filtered,
		);

		foreach ($query as $row)
		{
			$row['regular_price'] = store_cp_format_currency($row['regular_price_val']);
			$row['sale_price'] = store_cp_format_currency($row['sale_price_val']);

			$response['aaData'][] = array(
				'<a href="#"><img src="'.$this->EE->config->item('theme_folder_url').'cp_global_images/expand.gif"></a>',
				$row['entry_id'],
				$row['title'].NBS.NBS.'('.(substr_count($row['stock_table'], '<tr>')-1).' '.lang('variations').')',
				$row['total_stock'].NBS,
				form_input('store_product_field['.$row['entry_id'].'][regular_price]', $row['regular_price'], 'style="text-align: right"'),
				form_input('store_product_field['.$row['entry_id'].'][sale_price]', $row['sale_price'], 'style="text-align: right"'),
				form_hidden('store_product_field['.$row['entry_id'].'][sale_price_enabled]', 'n').
				form_checkbox('store_product_field['.$row['entry_id'].'][sale_price_enabled]', 'y', $row['sale_price_enabled'] == 'y'),
				'<strong><a href="'.$row['channel_edit_link'].'">'.lang('edit_entry').'</a></strong>',
				$row['stock_table']
			);
		}

		$response = array_merge($response, $filters);

		$response['non_sortable_columns'] = array(0, 7);
		$response['clickable_columns'] = array(0, 1, 2, 3);
		$response['default_sort'] = array(2, 'asc');

		/* -------------------------------------------
		/* 'store_inventory_datatable' hook.
		/*  - Modify the control panel inventory datatable
		/*  - Added: 1.2.1
		*/
			if ($this->EE->extensions->active_hook('store_inventory_datatable') === TRUE)
			{
				$response = $this->EE->extensions->call('store_inventory_datatable', $response);
			}
		/*
		/* -------------------------------------------*/

		if ($return_data) return $response;
		else $this->EE->output->send_ajax_response($response);
	}

	public function reports()
	{
		if ( ! $this->EE->store_config->site_enabled())
		{
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=install');
		}

		// handle submissions for specific reports
		if ( ! empty($_POST))
		{
			unset($_POST['submit']); // looks ugly
			$report = $this->EE->input->get('report', TRUE);
			$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=reports'.AMP.'report='.$report.AMP.http_build_query($_POST));
		}

		if ($this->EE->input->get('report'))
		{
			return $this->_reports_view($this->EE->input->get('report'));
		}

		$data = array(
			'post_url' => STORE_CP.AMP.'method=reports',
			'date_options' => array(),
			'stock_products_options' => array('sku' => lang('sku'), 'order_item_title' => lang('title'), 'item_subtotal' => lang('net_sales'), 'item_qty' => lang('quantity_sold')),
			'stock_inventory_options' => array('stockcode' => lang('sku'), 'product title' => lang('title')),
			'order_sort_options' => array('total' => lang('total'), 'last status update' => lang('last_status_update')),
			'start_date' => $this->EE->store_config->format_date('%Y-%m-%d',$this->EE->localize->now - 52*7*24*60*60),
			'end_date' => $this->EE->store_config->format_date('%Y-%m-%d', $this->EE->localize->now),
		);

		$current_month = (int)date('m', $this->EE->localize->now);
		$current_year = date('Y', $this->EE->localize->now);
		$data['date_options'] = array();

		for ($month = $current_month; $month > $current_month - 3; $month--)
		{
			if ($month > 0)
			{
				$date = gmmktime(0, 0, 0, $month, 1, $current_year);
			}
			else
			{
				$date = gmmktime(0, 0, 0, $month+12, 1, $current_year-1);
			}

			$key = date('Y-m', $date);
			$data['date_options'][$key] = strftime('%B %Y', $date);
		}
		$data['date_options']['custom_range'] = lang('custom_range');


		$data['status_options'] = array(lang('any'));
		$order_status_select_options = $this->EE->store_orders_model->get_order_statuses();
		foreach ($order_status_select_options as $option)
		{
			$data['status_options'][$option['name']] = lang($option['name']);
		}

		$this->set_title(lang('reports'));
		$this->EE->cp->add_js_script(array('ui' => 'datepicker'));

		return $this->EE->load->view('reports/report_list', $data, TRUE);
	}

	private function _reports_view($report_name, $order_id = NULL)
	{
		$this->add_breadcrumb(BASE.AMP.STORE_CP.AMP.'method=reports', lang('reports'));

		$this->EE->load->library('store_reports');

		switch ($report_name)
		{
			case 'orders':
				if ($this->EE->input->get('orders_report_date') == 'custom_range')
				{
					$start_date = strtotime($this->EE->input->get('start_date'));
					$end_date = strtotime($this->EE->input->get('end_date'))+(60*60*24);
					$report_title = lang('orders_report_all_orders').' '.lang('starting_from').strftime(' %e %B %Y ', $start_date).lang('through').strftime(' %e %B %Y', ($end_date-1));
				}
				else
				{
					$date = $this->_get_report_dates($this->EE->input->get('orders_report_date'));
					$start_date = $date['start_date'];
					$end_date = $date['end_date'];
					$report_title = lang('orders_report_list_all').strftime(' %B %Y', $start_date);
				}

				$status = $this->EE->input->get('orders_report_status', TRUE);
				if ( ! empty($status))
				{
					$report_title .= ' '.lang('orders_report_with_status').' '.lang($status);
				}

				$data = $this->EE->store_reports->orders($start_date, $end_date, $status);
				$data['page_title'] = lang('orders_report');
				break;

			case 'sales_by_date':
				if ($this->EE->input->get('sales_report_options') == 'custom_range')
				{
					$start_date = strtotime($this->EE->input->get('sales_start_date'));
					$end_date = strtotime($this->EE->input->get('sales_end_date'))+(60*60*24);
					$report_title = lang('total_sales').' '.lang('starting_from').strftime(' %e %B %Y ', $start_date).lang('through').strftime(' %e %B %Y', ($end_date-1));
				}
				else
				{
					$date = $this->_get_report_dates($this->EE->input->get('sales_report_options'));
					$start_date = $date['start_date'];
					$end_date = $date['end_date'];
					$report_title = lang('total_sales_report_desc').' '.strftime('%B %Y', $start_date);
				}

				$data = $this->EE->store_reports->sales_by_date($start_date, $end_date);
				$data['page_title'] = lang('sales_report1');
				break;

			case 'stock_value':
				$data = $this->EE->store_reports->stock_value($this->EE->input->get('stock_inventory_options'));
				$data['page_title'] = lang('stock_report3');
				$report_title = lang('stock_inventory_report_desc').' '.lang('sorted_by').' '.$this->EE->input->get('stock_inventory_options');
				break;

			case 'stock_products':
				if ($this->EE->input->get('stock_report_options') == 'custom_range')
				{
					$start_date = strtotime($this->EE->input->get('stock_start_date'));
					$end_date = strtotime($this->EE->input->get('stock_end_date'))+(60*60*24);
					$report_title = lang('products_sold').' '.lang('starting from').' '.strftime('%e %B %Y', $start_date).' '.lang('through').' '.strftime('%e %B %Y', ($end_date-1));
				}
				else
				{
					$date = $this->_get_report_dates($this->EE->input->get('stock_report_options'));
					$start_date = $date['start_date'];
					$end_date = $date['end_date'];
					$report_title = lang('stock_products_report_desc').' '.strftime('%B %Y', $start_date);
				}
				$data = $this->EE->store_reports->stock_products($start_date, $end_date, $this->EE->input->get('stock_orderby_options'));
				$data['page_title'] = lang('sales_report2');
				break;

			default:
				$this->EE->session->set_flashdata('message_error', lang('invalid_report'));
				$this->EE->functions->redirect(BASE.AMP.STORE_CP.AMP.'method=reports');
		}

		$data['report_title'] = $report_title;
		$data['post_url'] = STORE_CP.AMP.'method=reports'.AMP.'report='.$report_name;
		$data['export_link'] = $this->EE->config->item('cp_url').QUERY_MARKER.htmlentities(http_build_query($this->EE->security->xss_clean($_GET)));

		if ($this->EE->input->get('pdf'))
		{
			$html = $this->EE->load->view('reports/report_pdf', $data, TRUE);
			$this->EE->load->library('store_pdf');
			$this->EE->store_pdf->output($html, $data['page_title'].'_'.$this->EE->store_config->human_time($this->EE->localize->now).'.pdf');
		}
		elseif ($this->EE->input->get('csv'))
		{
			$output = $this->EE->load->view('reports/report_csv', $data, TRUE);
			$this->EE->load->helper('download');
			force_download($data['page_title'].'_'.$this->EE->store_config->human_time($this->EE->localize->now).'.csv', $output);
		}
		else
		{
			$this->set_title($data['page_title']);
			return $this->EE->load->view('reports/report_html', $data, TRUE);
		}
	}

	private function _datatables_js($ajax_method, $non_sortable_columns, $clickable_columns, $default_sort, $perpage)
	{
		$col_defs = array(
			array('sClass' => 'clickable', 'aTargets' => $clickable_columns),
			array('bVisible' => false, 'aTargets' => array(-1)),
			array('bSortable' => false, 'aTargets' => $non_sortable_columns),
		);

		$js = '
			window.oTable = $(".store_datatable").dataTable({
				"sPaginationType": "full_numbers",
				"bLengthChange": false,
				"aaSorting": '.json_encode(array($default_sort)).',
				"bFilter": false,
				"bAutoWidth": false,
				"iDisplayLength": '.$perpage.',
				"aoColumnDefs" : '.json_encode($col_defs).',
				"bProcessing": true,
				"bServerSide": true,
				"sAjaxSource": "'.html_entity_decode(BASE.AMP.STORE_CP.AMP.'method='.$ajax_method).'",
				"fnServerData": function (sSource, aoData, fnCallback) {
					var extraData = $("#filterform").serializeArray();
					for (var i = 0; i < extraData.length; i++) {
						if (extraData[i].name == "perpage") {
							$(".store_datatable").dataTable().fnSettings()._iDisplayLength = parseInt(extraData[i].value);
						}
						aoData.push(extraData[i]);
					}
					$.getJSON(sSource, aoData, fnCallback);
				},
				"oLanguage": {
					"sZeroRecords": "'.$this->EE->lang->line('no_entries_matching_that_criteria').'",
					"sInfo": "'.lang('dataTables_info').'",
					"sInfoEmpty": "'.lang('dataTables_info_empty').'",
					"sInfoFiltered": "'.lang('dataTables_info_filtered').'",
					"sProcessing": "'.lang('dataTables_processing').'",
					"oPaginate": {
						"sFirst": "<img src=\"'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif\" width=\"13\" height=\"13\" alt=\"&lt; &lt;\" />",
						"sPrevious": "<img src=\"'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif\" width=\"13\" height=\"13\" alt=\"&lt;\" />",
						"sNext": "<img src=\"'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif\" width=\"13\" height=\"13\" alt=\"&gt;\" />",
						"sLast": "<img src=\"'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif\" width=\"13\" height=\"13\" alt=\"&gt; &gt;\" />"
					}
				},
				"fnRowCallback": function(nRow,aData,iDisplayIndex) {

							 $(nRow).addClass("collapse");
							 return nRow;
				}
			});
			window.oSettings = window.oTable.fnSettings();
			oSettings.oClasses.sSortAsc = "headerSortUp";
			oSettings.oClasses.sSortDesc = "headerSortDown";
		';

		$this->EE->javascript->output($js);
	}

	private function _require_privilege($privilege)
	{
		if ( ! $this->EE->store_config->has_privilege($privilege))
		{
			show_error(lang('store_no_access'));
		}
	}

	private function _get_report_dates($date)
	{
		$date = explode('-', $date);

		switch ($date[1])
		{
			case 12:
				$date['start_date'] = gmmktime( 0, 0, 0, 12, 1, $date[0]);
				$date['end_date'] = gmmktime( 0, 0, 0, 1, 1, $date[0]+1);
				break;
			default:
				$date['start_date'] = gmmktime( 0, 0, 0, $date[1], 1, $date[0]);
				$date['end_date'] = gmmktime( 0, 0, 0, $date[1]+1, 1, $date[0]);
		}
		return $date;
	}

	private function _unique_region_codes($regions, $new_regions)
	{
		$duplicate_region_codes = array();
		if ( ! empty($regions))
		{
			$old_regions = array();
			foreach ($regions as $region_code => $region)
			{
				if (array_key_exists($region['code'], $old_regions) AND $region['code'] != '' AND !isset($region['delete']))
				{
					$duplicate_region_codes[$region['code']] = $region['code'];
				}
				elseif (isset($region['delete']))
				{
					continue;
				}
				$old_regions[$region['code']] = $region;
			}
		}
		if ( ! empty($new_regions))
		{
			$updated_regions = array();
			foreach ($new_regions as $region_code => $region)
			{
				if (array_key_exists($region['code'], $updated_regions) AND $region['code'] != '' AND !isset($region['delete']))
				{
					$duplicate_region_codes[$region['code']] = $region['code'];
				}
				elseif (isset($region['delete']))
				{
					continue;
				}
				$updated_regions[$region['code']] = $region;
			}

			if (! empty($old_regions))
			{
				foreach ($updated_regions as $region_code => $region)
				{
					if(array_key_exists($region_code, $old_regions) AND $region_code != '')
					{
						$duplicate_region_codes[$region_code] = $region_code;
					}
				}
			}
		}
		return $duplicate_region_codes;
	}

	public function ajax_reorder()
	{
		$table = $this->EE->input->get('table');
		$order = explode(',', $this->EE->input->get_post('order', TRUE));
		foreach ($order as $key => $value)
		{
			$id = (strpos($value, 'status') !== FALSE) ? str_replace('status_id_', '', $value) : str_replace('shipping_method_id_', '', $value);
			$id = trim(preg_replace('/ => .+/', '', $id));
			$display_order = (strpos($value, 'status') !== FALSE) ? preg_replace('/status_id_[0-9]+ => /', '', $value) : preg_replace('/shipping_method_id_[0-9]+ => /', '', $value);
			$update_data[$id] = trim($display_order);
		}
		if($table == 'order_statuses')
		{
			$this->EE->store_orders_model->update_status_display_orders($update_data);
		}
		else if($table == 'plugins')
		{
			$this->EE->store_shipping_model->update_shipping_methods_display_order($update_data);
		}
		// Not needed but sent back for debugging purposes in case you need to double check the new ordering
		$this->EE->output->send_ajax_response($update_data);
	}

	/**
	 * Set the CP page title with support for EE < 2.6
	 */
	public static function set_title($title)
	{
		if (version_compare(APP_VER, '2.6.0', '<')) {
			get_instance()->cp->set_variable('cp_page_title', $title);
		} else {
			get_instance()->view->cp_page_title = $title;
		}
	}

	/**
	 * We use our own breadcrumb function to override the useless "Modules" crumb added by
	 * the modules controller.
	 */
	public static function add_breadcrumb($link, $title)
	{
		self::$_breadcrumbs[$link] = $title;
		get_instance()->load->vars(array('cp_breadcrumbs' => self::$_breadcrumbs));
	}
}
/* End of file mcp.store.php */