<?= form_open($post_url) ?>
<div style="text-align: right; margin: 5px 0 15px 0;">
	<a href="<?= $new_email_template_link ?>" class="submit"><?= lang('new_email_template') ?></a>
</div>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		lang('email_name'),
		lang('email_subject'),
		lang('status'),
		array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
	);

	$i = 0;
	foreach($templates as $email)
	{
		$this->table->add_row(
			'<a href="'.$email_template_link.$email['template_id'].'">'.lang($email['name']).'</a>',
			$email['subject'],
			store_enabled_str($email['enabled']),
			form_checkbox('selected[]', $email['template_id'], FALSE));
	}

	echo $this->table->generate();
?>


<div style="text-align: right;">
	<?= form_dropdown('with_selected', $with_selected_options) ?>
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>
<?= form_close() ?>