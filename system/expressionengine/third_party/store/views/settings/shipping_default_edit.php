<?= form_open($post_url) ?>

<p class="notice"><?= $this->session->flashdata('store_shipping_rule') ?></p>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => lang('shipping_filters'), 'width' => "40%"),
		array('data' => '<i>'.lang('shipping_filters_desc').'</i>'));

	$this->table->add_row(
			lang('country', 'shipping_rule[country_code]'),
			form_dropdown("shipping_rule[country_code]", $country_select, $shipping_rule['country_code'], 'class="store_country_select"')
	);

	$this->table->add_row(
			lang('region', 'shipping_rule[region_code]'),
			form_dropdown("shipping_rule[region_code]", $region_select, $shipping_rule['region_code'])
	);

	$this->table->add_row(
		lang("postcode", "shipping_rule[postcode]"),
		form_input("shipping_rule[postcode]", set_value("shipping_rule[postcode]", $shipping_rule['postcode']))
	);

	foreach (array('min_order_qty', 'max_order_qty', 'min_order_total', 'max_order_total', 'min_weight', 'max_weight') as $field)
	{
		$this->table->add_row(
			lang("shipping_$field", "shipping_rule[$field]"),
			form_input("shipping_rule[$field]", set_value("shipping_rule[$field]", $shipping_rule[$field]), 'placeholder="'.lang('none').'"')
		);
	}

	echo $this->table->generate();

	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => lang('shipping_charges'), 'width' => "40%"),
		array('data' => ''));

	$this->table->add_row(
			lang('shipping_base_rate', 'shipping_rule[base_rate]').BR.
			'<small>'.lang('prices_excluding_tax').'</small>',
			form_input('shipping_rule[base_rate]', set_value('shipping_rule[base_rate]', $shipping_rule['base_rate']), 'placeholder="'.lang('none').'"')
	);

	foreach (array('per_item_rate', 'per_weight_rate', 'percent_rate', 'min_rate', 'max_rate') as $field)
	{
		$this->table->add_row(
			lang("shipping_$field", "shipping_rule[$field]"),
			form_input("shipping_rule[$field]", set_value("shipping_rule[$field]", $shipping_rule[$field]), 'placeholder="'.lang('none').'"')
		);
	}

	$this->table->add_row(
			lang('enabled', 'shipping_rule[enabled]'),
			store_form_checkbox('shipping_rule[enabled]', $shipping_rule['enabled'])
	);

	echo $this->table->generate();

	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '', 'width' => "40%"),
		array('data' => ''));

	$this->table->add_row(
			lang('shipping_rule_desc', 'shipping_rule[title]'),
			form_input('shipping_rule[title]', set_value('shipping_rule[title]', $shipping_rule['title']))
	);

	$this->table->add_row(
			lang('priority', 'shipping_rule[priority]').BR.
			'<small>'.lang('shipping_priority_desc').'</small>',
			form_input('shipping_rule[priority]', set_value('shipping_rule[priority]', $shipping_rule['priority']), 'placeholder="'.lang('none').'"')
	);

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>