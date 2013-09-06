<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '', 'width' => "40%"),
		array('data' => ''));

	$this->table->add_row(
			'<strong class="notice">*</strong> '.lang('tax_name', 'tax[tax_name]'),
			form_input('tax[tax_name]', set_value('tax[tax_name]', $tax['tax_name'])).
			form_error('tax[tax_name]')
	);

	$this->table->add_row(
			lang('country', 'tax[country_code]'),
			form_dropdown("tax[country_code]", $country_select, set_value('tax[country_code]', $tax['country_code']), 'class="store_country_select"').
			form_error('tax[country_code]')
	);

	$this->table->add_row(
			lang('region', 'tax[region_code]'),
			form_dropdown("tax[region_code]", $region_select, set_value('tax[region_code]', $tax['region_code'])).
			form_error('tax[region_code]')
	);

	$this->table->add_row(
			'<span class="notice">* </span>'.lang('tax_rate', 'tax[tax_percent]'),
			form_input('tax[tax_percent]', set_value('tax[tax_percent]', empty($tax['tax_percent']) ? '' : $tax['tax_percent'].'%')).
			form_error('tax[tax_percent]')
	);

	$this->table->add_row(
			lang('tax_shipping', 'tax_tax_shipping'),
			store_form_checkbox('tax[tax_shipping]', $tax['tax_shipping'])
	);

	$this->table->add_row(
			lang('enabled', 'tax_enabled'),
			store_form_checkbox('tax[enabled]', $tax['enabled'])
	);

	echo $this->table->generate();
?>
<p><strong class="notice">*</strong> <?= lang('required_fields') ?></p>
<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>