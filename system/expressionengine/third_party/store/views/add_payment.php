<?= form_open($post_url); ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);

	$this->table->add_row(
		lang('order_id'),
		$order['order_id']);

	$this->table->add_row(
		lang('owing'),
		$order['order_owing']);

 	$this->table->add_row(
		'<span class="notice">*</span> '.lang('amount'),
		form_input('payment[amount]', set_value('payment[amount]', store_cp_format_currency($order['order_owing_val']))).
		form_error('payment[amount]'));

	$this->table->add_row(
		'<span class="notice">*</span> '.lang('payment_date'),
		form_input(array(
			'class' => 'store_datetimepicker',
			'name' => 'payment[payment_date]',
			'value' => set_value('payment[payment_date]', $this->store_config->human_time($this->localize->now)))).
		form_error('payment[payment_date]'));

	$this->table->add_row(
		lang('payment_message'),
		form_input('payment[message]', set_value('payment[message]')));

	$this->table->add_row(
		lang('payment_reference'),
		form_input('payment[reference]', set_value('payment[reference]')));

	echo $this->table->generate();
?>
<p><span class="notice">*</span> <?= lang('required_fields') ?></p>
<div style="clear: left; text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>