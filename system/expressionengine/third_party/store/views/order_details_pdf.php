<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?= $report_title ?></title>
	<style type="text/css">
		div.report { font-size: 66%; }
		table { width: 100%; border-collapse: collapse; }
		table td, table th { text-align: left; border: 1px solid black; padding: 0.5em; margin: 0px; }
		table.no_border td { border: none; }
		table td.empty { border: none; background-color: #FFFFFF; }
		table tr.even { background-color: #EBF0F2; }
		table tr.odd { background-color: #F4F6F6; }
		div.header_right { text-align: right; }
	</style>
</head>
<body>
	<?php $table_open = '<table class="mainTable store_table">'; ?>

	<div class="report">

	<h1><?= $report_title ?></h1>
	<div class="header_right"><?= $header_right ?></div>
	<strong><?= lang('order_details') ?></strong>
	<br />
	<?= $table_open ?>
		<thead>
		<tr>
			<th style="width:13%"><?= lang('order_id') ?></th>
			<th style="width:20%"><?= lang('member') ?></th>
			<th style="width:20%"><?= lang('order_total') ?></th>
			<th style="width:17%"><?= lang('paid?') ?></th>
			<th style="width:30%"><?= lang('order_status') ?></th>
		</tr>
		</thead>
		<tbody>
		<tr>
			<td style="width:13%"><?= $order['order_id'] ?></td>
			<td style="width:20%"><?= $order['screen_name'] ?></td>
			<td style="width:20%"><?= $order['order_total'] ?></td>
			<td style="width:17%"><?= $order['order_paid_str'] ?></td>
			<td style="width:30%">
				<?= $order['order_status_html'] ?>
				<?php if ($order['order_completed_date']): ?>
					(<?= $this->store_config->human_time($order['order_status_updated']) ?>)
				<?php endif ?>
			</td>
		</tr>
		</tbody>
	</table>
	<table class="no_border">
		<tr>
			<td style="vertical-align:top">
				<table class="mainTable store_table" style="padding-right:10px;">
					<tr>
						<td style="width:40%" class="top_td_no_header"><strong><?= lang('billing_name') ?></strong></td>
						<td style="width:60%" class="top_td_no_header"><?= $order['billing_name'] ?></td>
					</tr>
					<tr>
						<td style="width:40%"><strong><?= lang('billing_address') ?></strong></td>
						<td style="width:60%"><?= $order['billing_address_full'] ?></td>
					</tr>
					<tr>
						<td style="width:40%"><strong><?= lang('billing_phone') ?></strong></td>
						<td style="width:60%"><?= $order['billing_phone'] ?></td>
					</tr>
					<tr>
						<td style="width:40%"><strong><?= lang('order_email') ?></strong></td>
						<td style="width:60%"><?= $order['order_email'] ?></td>
					</tr>
					<?php foreach ($order_fields as $field_name => $field): ?>
						<?php if (strpos($field_name, 'order_custom') !== FALSE AND ! empty($order[$field_name.'_name'])): ?>
							<tr>
								<td style="width:40%"><strong><?= $order[$field_name.'_name'] ?></strong></td>
								<td style="width:60%"><?= $order[$field_name] ?></td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</table>
			</td>
			<td style="vertical-align:top">
				<table class="mainTable store_table" style="padding-left:10px;">
					<tr>
						<td style="width:40%" class="top_td_no_header"><strong><?= lang('shipping_name') ?></strong></td>
						<td style="width:60%" class="top_td_no_header"><?= $order['shipping_name'] ?></td>
					</tr>
					<tr>
						<td style="width:40%"><strong><?= lang('shipping_address') ?></strong></td>
						<td style="width:60%"><?= $order['shipping_address_full'] ?></td>
					</tr>
					<tr>
						<td style="width:40%"><strong><?= lang('shipping_phone') ?></strong></td>
						<td style="width:60%"><?= $order['shipping_phone'] ?></td>
					</tr>
					<tr>
						<td style="width:40%"><strong><?= lang('shipping_method') ?></strong></td>
						<td style="width:60%"><?= $order['shipping_method'] ?></td>
					</tr>
					<?php if ($order['promo_code']): ?>
						<tr>
							<td><strong><?= lang('promo_code') ?></strong></td>
							<td><?= $order['promo_code'] ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</td>
		</tr>
	</table>
	<br />
	<strong><?= lang('items') ?></strong>
	<br />
	<?= $table_open ?>
		<thead>
		<tr>
			<th style="width:5%">#</th>
			<th style="width:20%"><?= lang('product') ?></th>
			<th style="width:13%"><?= lang('sku') ?></th>
			<th style="width:32%"><?= lang('modifiers') ?></th>
			<th style="width:10%"><?= lang('price') ?></th>
			<th style="width:10%"><?= lang('quantity') ?></th>
			<th style="width:10%"><?= lang('total') ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($order['items'] as $item): ?>
			<tr>
				<td style="width:5%"><?= $item['entry_id'] ?></td>
				<td style="width:20%"><?= $item['title'] ?></td>
				<td style="width:13%"><?= $item['sku'] ?></td>
				<td style="width:32%"><?= $item['modifiers_desc'] ?></td>
				<td style="width:10%"><?= $item['price'] ?></td>
				<td style="width:10%"><?= $item['item_qty'] ?></td>
				<td style="width:10%"><?= $item['item_subtotal'] ?></td>
			</tr>
		<?php endforeach ?>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2"><?= lang('order_subtotal') ?></td>
			<td><?= $order['order_subtotal'] ?></td>
		</tr>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2"><?= lang('order_discount') ?></td>
			<td><?= $order['order_discount'] ?></td>
		</tr>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2"><?= lang('order_shipping').' ('.$order['shipping_method'].')' ?></td>
			<td><?= $order['order_shipping'] ?></td>
		</tr>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2"><?= empty($order['tax_name']) ? lang('order_tax') : lang('order_tax').' ('.$order['tax_name'].' @ '.((double)$order['tax_rate']*100).'%)' ?></td>
			<td><?= $order['order_tax'] ?></td>
		</tr>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2" style="font-weight:bold"><?= lang('order_total') ?></td>
			<td style="font-weight:bold"><?= $order['order_total'] ?></td>
		</tr>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2"><?= lang('paid') ?></td>
			<td><?= $order['order_paid'] ?></td>
		</tr>
		<tr>
			<td colspan="4" class="empty"></td>
			<td colspan="2" style="font-weight:bold"><?= lang('balance_due') ?></td>
			<td style="font-weight:bold"><?= $order['order_owing'] ?></td>
		</tr>
		</tbody>
	</table>
	<br />
	<strong><?= lang('payments') ?></strong>
	<br />
	<?= $table_open ?>
		<thead>
		<tr>
			<th style="width:5%">#</th>
			<th style="width:10%"><?= lang('payment_method') ?></th>
			<th style="width:15%"><?= lang('reference') ?></th>
			<th style="width:20%"><?= lang('payment_message') ?></th>
			<th style="width:10%"><?= lang('amount') ?></th>
			<th style="width:20%"><?= lang('payment_date') ?></th>
			<th style="width:10%"><?= lang('recorded_by') ?></th>
			<th style="width:10%"><?= lang('payment_status') ?></th>
		</tr>
		</thead>
		<tbody>
		<?php if (empty($order_payments)): ?>
			<tr>
				<td colspan="8"><em><?=lang('no_payments')?></em></td>
			</tr>
		<?php else: ?>
			<?php foreach ($order_payments as $payment): ?>
				<tr>
					<td style="width:5%"><?= $payment['payment_id'] ?></td>
					<td style="width:10%"><?= $payment['payment_method_title'] ?></td>
					<td style="width:15%"><?= $payment['reference'] ?></td>
					<td style="width:20%"><?= $payment['message'] ?></td>
					<td style="width:10%"><?= store_format_currency($payment['amount']) ?></td>
					<td style="width:20%"><?= $this->store_config->human_time($payment['payment_date']) ?></td>
					<td style="width:10%"><?= $payment['payment_member'] ?></td>
					<td style="width:10%"><?= store_payment_status($payment['payment_status']) ?></td>
				</tr>
			<?php endforeach ?>
		<?php endif ?>
		</tbody>
	</table>

	<div><?= $footer ?></div>

	</div>
</body>
</html>
