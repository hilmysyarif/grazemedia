<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		lang('orders_field_name'),
		lang('title'),
		lang('mapped_member_field')
	);

	foreach($order_fields as $field_name => $field)
	{
		$this->table->add_row(
			$field_name,
			isset($field['title']) ? form_input("order_fields[{$field_name}][title]", $field['title']) : lang($field_name),
			$field_name == 'order_email' ? '' : form_dropdown("order_fields[{$field_name}][member_field]", $member_fields, $field['member_field']));
	}

	echo $this->table->generate();
?>

<div>
	<div style="float: right;"><?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?></div>
	<?= form_submit(array('name' => 'restore_defaults', 'value' => lang('restore_defaults'), 'class' => 'submit', 'data-store-confirm' => lang('restore_fields_confirm'))) ?>
</div>

<?= form_close() ?>