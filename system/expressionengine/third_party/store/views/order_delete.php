<?= form_open($post_url) ?>

<?php
	foreach ($order_ids as $order_id)
	{
		echo form_hidden('orders_to_delete[]', $order_id);
	}
	?>
<strong>
<?php if (count($order_ids) > 1): ?>
	<?= lang('delete_orders_question') ?>
<?php else: ?>
	<?= lang('delete_order_question') ?>
<?php endif ?>
</strong>
<p>&nbsp;</p>
<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '#', 'width' => "2%"),
		lang('member'),
		lang('billing_name'),
		lang('order_date'),
		lang('total'),
		lang('paid?'),
		lang('status')
		);
	echo $this->table->generate($orders);
?>
<span class="notice"> <?= lang('delete_warning') ?> </span>
<br />
<?= form_submit(array('name' => 'action_submit', 'value' => lang('delete'), 'class' => 'submit')) ?>
<?= form_close() ?>