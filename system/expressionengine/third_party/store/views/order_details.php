<div style='float: right;'>
	<?php if ($invoice_link): ?>
		<a href="<?= $invoice_link; ?>" class="submit" target="_blank"><?= lang('show_invoice'); ?></a>
	<?php endif; ?>
	<a href="<?= $export_pdf_link; ?>" class="submit"><?= lang('export_pdf') ?></a>
</div>

<div id="store_product_field"><!-- needed to activate JS -->

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
	<?= lang('order_details') ?>
</label>
<div class="store_field_pane">
<table cellspacing="0" cellpadding="0" border="0" class="mainTable store_table">
	<tr>
		<th style="width: 10%;"><?= lang('order_id') ?></th>
		<th style="width: 15%;"><?= lang('member') ?></th>
		<th style="width: 15%;"><?= lang('order_total') ?></th>
		<th style="width: 15%;"><?= lang('payment_method') ?></th>
		<th style="width: 15%;"><?= lang('paid?') ?></th>
		<th style="width: 30%;"><?= lang('order_status') ?></th>
	</tr>
	<tr>
		<td><?= $order['order_id'] ?></td>
		<td><?= '<a href="'.$order['member_link'].'">'.$order['screen_name'].'</a>' ?></td>
		<td><?= $order['order_total'] ?></td>
		<td><?= $order['payment_method_title'] ?></td>
		<td><?= $order['order_paid_str'] ?></td>
		<td>
			<?= $order['order_status_html'] ?>
			<?php if ($order['order_completed_date']): ?>
				(<?= $this->store_config->human_time($order['order_status_updated']) ?>)
				<a class="edit_status" href="#"><?= lang('edit_status') ?></a>
				<a class="cancel_edit_status" href="#" style="display: none;"><?= lang('cancel') ?></a>
				<div style="display: none;" class="edit_status_info">
					<br />
					<?= form_open($post_url).
						lang('order_status').': '.form_dropdown('status', $status_select_options, set_value('status', $order['order_status'])).BR.BR.
						lang('message').': '.form_input('message','','width=10%, style=width:50%').BR.BR.
						form_hidden('order_id', (int)$order['order_id']).
						form_submit(array('name' => 'action_submit', 'value' => lang('submit'), 'class' => 'submit', 'id' => 'status_submit')).
						form_close(); ?>
				</div>
			<?php endif ?>
		</td>
	</tr>
</table>

<table style="width:100%;" rules="none" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td style="vertical-align:top" width="50%">
			<table class="mainTable store_table" width="100%" cellspacing="0" cellpadding="10" border="0" style="padding-right:10px;">
				<tr>
					<td class="top_td_no_header"><strong><?= lang('billing_name') ?></strong></td>
					<td class="top_td_no_header"><?= $order['billing_name'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('billing_address') ?></strong></td>
					<td><?= $order['billing_address_full'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('billing_phone') ?></strong></td>
					<td><?= $order['billing_phone'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('order_email') ?></strong></td>
					<td><?= $order['order_email'] ?></td>
				</tr>
				<?php foreach ($order_fields as $field_name => $field): ?>
					<?php if (strpos($field_name, 'order_custom') !== FALSE AND ! empty($order[$field_name.'_name'])): ?>
						<tr>
							<td><strong><?= $order[$field_name.'_name'] ?></strong></td>
							<td><?= $order[$field_name] ?></td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</table>
		</td>
		<td style="vertical-align:top" width="50%">
			<table class="mainTable store_table" width="100%" cellspacing="0" cellpadding="10" border="0" style="padding-left:10px;">
				<tr>
					<td class="top_td_no_header"><strong><?= lang('shipping_name') ?></strong></td>
					<td class="top_td_no_header"><?= $order['shipping_name'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('shipping_address') ?></strong></td>
					<td><?= $order['shipping_address_full'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('shipping_phone') ?></strong></td>
					<td><?= $order['shipping_phone'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('shipping_method') ?></strong></td>
					<td><?= $order['shipping_method'] ?></td>
				</tr>
				<tr>
					<td><strong><?= lang('promo_code') ?></strong></td>
					<td><?= $order['promo_code'] ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
	<?= lang('items') ?>
</label>
<div class="store_field_pane">
<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading('#', lang('product'), lang('sku'), lang('modifiers'), lang('price'), array('data' => lang('quantity'), 'style'=>'width:15%'), lang('total'));

	foreach ($order['items'] as $item)
	{
		$this->table->add_row($item['entry_id'],
				$item['title'],
				$item['sku'],
				$item['modifiers_desc'],
				$item['price'],
				$item['item_qty'],
				$item['item_subtotal']
		);
	}

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => lang('order_subtotal'), 'colspan' => 2),
		$order['order_subtotal']);

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => lang('order_discount'), 'colspan' => 2),
		$order['order_discount']);

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => lang('order_shipping').' ('.$order['shipping_method'].')', 'colspan' => 2),
		$order['order_shipping']);

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => empty($order['tax_name']) ? lang('order_tax') : $order['tax_name'].' @ '.((double)$order['tax_rate']*100).'%', 'colspan' => 2),
		$order['order_tax']);

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => lang('order_total'), 'colspan' => 2, 'style' => 'font-weight:bold'),
		array('data' => $order['order_total'], 'style' => 'font-weight:bold'));

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => lang('paid'), 'colspan' => 2),
		$order['order_paid']);

	$this->table->add_row(array('data' => '', 'colspan' => 4),
		array('data' => lang('balance_due'), 'colspan' => 2, 'style' => 'font-weight:bold'),
		array('data' => $order['order_owing'], 'style' => 'font-weight:bold'));

	echo $this->table->generate();
?>
</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
	<?= lang('payments') ?>
</label>
<div class="store_field_pane" id="payments">
<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading('#', lang('payment_method'), lang('reference'),
		lang('payment_message'), lang('amount'), lang('payment_date'), lang('recorded_by'),
		lang('payment_status'), lang('actions'));

	foreach ($order_payments as $payment)
	{
		$this->table->add_row($payment['payment_id'],
			$payment['payment_method_title'],
			$payment['reference'],
			$payment['message'],
			store_format_currency($payment['amount']),
			$this->store_config->human_time($payment['payment_date']),
			$payment['payment_member'] == 'System' ? $payment['payment_member'] : '<a href="'.$payment['member_link'].'">'.$payment['payment_member'].'</a>',
			store_payment_status($payment['payment_status']),
			$payment['payment_actions']
		);
	}

	if (empty($order_payments))
	{
		$this->table->add_row(array('data' => '<i>'.lang('no_payments').'</i>', 'colspan' => 8));
	}

	echo $this->table->generate();
?>

<?php if ($can_add_payments): ?>
	<div style="clear: left;">
		<a href="<?= $add_payment_link ?>" class="submit"><?= lang('add_payment') ?></a>
	</div>
<?php endif ?>

</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" width="10" height="13" alt="" />
	<?= lang('order_status_history') ?>
</label>
<div class="store_field_pane">
<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(lang('status'), lang('order_status_updated'), lang('updated_by'), lang('message'));

	foreach ($order_statuses as $num => $status)
	{
		$status['order_status_updated'] = $this->store_config->human_time($status['order_status_updated']);
		$status['order_status'] = lang($status['order_status']);

		if ($num === 0)
		{
			foreach ($status as $key => $result)
			{
				if ($key != 'member_link' AND $key != 'color') $status[$key] = '<strong>'.$status[$key].'</strong>';
			}
		}

		$this->table->add_row(
				'<span style="color:#'.$status['color'].'">'.$status['order_status'].'</span>',
				$status['order_status_updated'],
				($status['screen_name'] == '<strong>System</strong>' OR $status['screen_name'] == 'System' ) ? $status['screen_name'] : '<a href="'.$status['member_link'].'">'.$status['screen_name'].'</a>',
				$status['message']
				);
	}

	echo $this->table->generate();
?>
</div>

</div>