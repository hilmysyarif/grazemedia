<?= form_open($post_url); ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '', 'width' => "40%"),
		array('data' => ''));

	if ( ! empty($shipping_method['shipping_method_id']))
	{
		$this->table->add_row(
			form_label(lang('shipping_method_id')),
			$shipping_method['shipping_method_id']
		);
	}

	$this->table->add_row(
		'<span class="notice">* </span>'.lang('name', 'shipping_method_title'),
		form_input('shipping_method[title]', set_value('shipping_method[title]', $shipping_method['title'])).
		form_error('shipping_method[title]')
	);

	if (empty($shipping_method['shipping_method_id']))
	{
		$this->table->add_row(
			'<span class="notice">* </span>'.lang('shipping_plugin', 'shipping_method_class'),
			form_dropdown('shipping_method[class]', $shipping_class_select, set_value('shipping_method[class]', $shipping_method['class'])).
			form_error('shipping_method[class]')
		);
	}
	else
	{
		$this->table->add_row(
			lang('shipping_plugin', 'shipping_method_class'),
			form_hidden('shipping_method[class]', $shipping_method['class']).
			$shipping_method['class_name']
		);
	}

	$this->table->add_row(
		lang('enabled', 'shipping_method_enabled'),
		store_form_checkbox('shipping_method[enabled]', $shipping_method['enabled'])
	);

	echo $this->table->generate();
?>

<p><strong class="notice">*</strong> <?= lang('required_fields') ?></p>
<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>