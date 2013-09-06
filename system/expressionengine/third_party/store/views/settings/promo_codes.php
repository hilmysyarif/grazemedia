<div style="text-align: right; margin: 5px 0 15px 0;">
	<a href="<?= $new_promo_code_link ?>" class="submit"><?= lang('new_promo_code') ?></a>
</div>

<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		lang('code'),
		lang('description'),
		lang('promo_value'),
		lang('status'),
		lang('options'),
		array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
	);

	foreach($promo_codes as $promo_code)
	{
		$this->table->add_row(
			$promo_code['promo_code'],
			$promo_code['description'],
			$promo_code['value_str'],
			store_enabled_str($promo_code['enabled']),
			'<a href="'.$promo_code['edit_link'].'">'.lang('edit').'</a>',
			form_checkbox('selected[]', $promo_code['promo_code_id'], FALSE));
	}

	if (empty($promo_codes))
	{
		$this->table->add_row(array('data' => '<i>'.lang('no_promo_codes').'</i>', 'colspan' => 11));
	}

	echo $this->table->generate();
?>


<div style="text-align: right;">
	<?= form_dropdown('with_selected', $with_selected_options) ?>
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>