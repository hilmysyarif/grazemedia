<div style="text-align: right; margin: 5px 0 15px 0;">
	<a href="<?= $add_tax_rate_link ?>" class="submit"><?= lang('tax_rate_add') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => lang('tax_name'), 'width' => '20%'),
		array('data' => lang('country'), 'width' => '20%'),
		array('data' => lang('region'), 'width' => '20%'),
		array('data' => lang('tax_rate'), 'width' => '15%'),
		lang('status'),
		array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
	);

	foreach ($tax_rates as $tax)
	{
		$this->table->add_row(
			'<a href="'.$tax['edit_link'].'">'.$tax['tax_name'].'</a>',
			$tax['country_name'],
			$tax['region_name'],
			empty($tax['tax_rate']) ? '' : $tax['tax_percent'].'%',
			store_enabled_str($tax['enabled']),
			form_checkbox("selected[]", $tax['tax_id'])
		);
	}

	if (empty($tax_rates))
	{
		$this->table->add_row(array(
			'colspan' => 5,
			'style' => 'font-style:italic',
			'data' => lang('no_tax_rates'),
		));
	}

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_dropdown('with_selected', array('enable' => lang('enable_selected'), 'disable' => lang('disable_selected'), 'delete' => lang('delete_selected'))) ?>
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close() ?>