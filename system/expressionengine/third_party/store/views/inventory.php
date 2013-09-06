<?=form_open($search_form, array('name'=>'filterform', 'id'=>'filterform'))?>
<fieldset>
<legend><?=lang('search_entries')?></legend>

<div class="filters">
<?=form_dropdown('product_categories', $product_category_select_options, $inventory['category_id']).NBS.NBS?>
<?=form_dropdown('perpage', $perpage_select_options, $inventory['perpage'])?>
</div>
<br />
<div>
<?=lang('keywords', 'keywords')?> <?=form_input('keywords', $inventory['keywords'], 'class = "field_shun" style = "width:260px;" maxlength = 100')?>&nbsp;
<?=form_checkbox('exact_match', '1', $inventory['exact_match'])?> <?=lang('exact_match', 'exact_match').NBS.NBS?>
</div>

</fieldset>
<?= form_close(); ?>
<div id="store_product_field"><div id="store_product_stock">
<?php
	echo form_open($post_url, array('id' => 'store_inventory'));
	$this->table->clear();
	$table_template = $cp_store_table_template;
	$table_template['table_open'] = '<table class="mainTable store_datatable" id="store_inventory_table" cellspacing="0" cellpadding="0" border="0">';
	$this->table->set_template($table_template);
	$this->table->set_heading(
		array('data' => '<a id="all" href="#"><img src="'.$this->config->item('theme_folder_url').'cp_global_images/expand.gif"></a>', 'width' => "2%"), //put expand / collapse all button here
		array('data' => '#', 'width' => "5%"),
		lang('title'),
		array('data' => lang('total_stock'), 'width' => '10%'),
		array('data' => lang('price'), 'width' => '10%'),
		array('data' => lang('sale_price'), 'width' => '10%'),
		array('data' => lang('sale_price_enabled'), 'width' => '11%'),
		array('data' => lang('options'), 'width' => '10%'),
		array('data' => '', 'width' => '1%')
	);

	echo $this->table->generate($inventory['aaData']);
?>
</div></div>
<div style="clear: left; text-align: right;">
<?= form_submit(array('name' => 'action_submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>