<div style="float: right; padding: 1em 0;">
	<a href="<?= $shipping_add_rule_link ?>" class="submit"><?= lang('shipping_add_rule') ?></a>
</div>
<ul class="bulleted" style="padding: 0 1em 1em 1em; font-style: italic;">
	<?= lang('shipping_default_help') ?>
</ul>

<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '#', 'width' => '2%'),
		array('data' => lang('country'), 'width' => '10%'),
		array('data' => lang('region'), 'width' => '10%'),
		array('data' => lang('postcode'), 'width' => '7%'),
		array('data' => lang('items'), 'width' => '7%'),
		array('data' => lang('order_total'), 'width' => '10%'),
		array('data' => lang('weight'), 'width' => '10%'),
		array('data' => lang('shipping_charges'), 'width' => ''),
		array('data' => lang('priority'), 'width' => '7%'),
		array('data' => lang('enabled'), 'width' => '5%'),
		array('data' => lang('options'), 'width' => '5%'),
		array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%'));

	$counter = 0;
	foreach ($shipping_rules as $shipping_rule)
	{
		$counter++;
		$this->table->add_row(array(
			$counter,
			$shipping_rule['country_name'],
			$shipping_rule['region_name'],
			$shipping_rule['postcode'],
			$shipping_rule['order_qty_text'],
			$shipping_rule['order_total_text'],
			$shipping_rule['weight_text'],
			$shipping_rule['rate_text'],
			$shipping_rule['priority'],
			store_enabled_str($shipping_rule['enabled']),
			'<a href="'.$shipping_rule['edit_link'].'">'.lang('edit').'</a>',
			form_checkbox('selected[]', $shipping_rule['shipping_rule_id']),
		));
	}

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_dropdown('with_selected', array('enable' => lang('enable_selected'), 'disable' => lang('disable_selected'), 'delete' => lang('delete_selected'))) ?>
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close() ?>