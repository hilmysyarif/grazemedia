<div style="text-align: right; margin: 5px 0 15px 0;">
	<a href="<?= $add_payment_method_link ?>" class="submit"><?= lang('add_payment_method') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => lang('payment_method'), 'style' => 'width:30%'),
		array('data' => lang('short_name'), 'style' => 'width:20%'),
		array('data' => lang('payment_plugin'), 'style' => 'width:30%'),
		array('data' => lang('status'), 'style' => 'width:18%'),
		array('data' => form_checkbox(array('id' => 'checkall')), 'style' => 'width:2%'));

	foreach ($payment_methods as $payment_method)
	{
		if ($payment_method['missing'])
		{
			$this->table->add_row(
				$payment_method['title'],
				$payment_method['name'],
				array('data' => $payment_method['class_name'], 'class' => 'notice'),
				array('data' => lang('missing'), 'class' => 'notice'),
				form_checkbox('selected[]', $payment_method['payment_method_id']));
		}
		else
		{
			$this->table->add_row(
				'<a href="'.$payment_method['settings_link'].'">'.$payment_method['title'].'</a>',
				$payment_method['name'],
				$payment_method['class_name'],
				store_enabled_str($payment_method['enabled']),
				form_checkbox('selected[]', $payment_method['payment_method_id']));
		}
	}

	if (empty($payment_methods))
	{
		$this->table->add_row(array(
			'colspan' => 5,
			'style' => 'font-style:italic',
			'data' => lang('no_payment_methods'),
		));
	}

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_dropdown('with_selected', array('enable' => lang('enable_selected'), 'disable' => lang('disable_selected'), 'delete' => lang('delete_selected'))) ?>
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>
