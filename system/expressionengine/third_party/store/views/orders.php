<fieldset>
<legend><?=lang('search_entries')?></legend>

<?=form_open($search_form, array('name'=>'filterform', 'id'=>'filterform'))?>

<div class="group">
	<?=form_dropdown('order_status', $order_status_select_options, $orders['order_status']).NBS.NBS?>
	<?=form_dropdown('order_paid_status', $order_paid_select_options, $orders['order_paid_status']).NBS.NBS?>
	<?=form_dropdown('date_range', $date_select_options, $orders['date_range']).NBS.NBS?>
	<?php
		// JS required theme, so ordering handled by table sorter
		//form_dropdown('order', $order_select_options, $order_selected, 'id="f_select_options"').NBS.NBS
	?>
	<?=form_dropdown('perpage', $perpage_select_options, $orders['perpage'])?>
</div>

<div id="custom_date_picker" style="display: none; margin: 0 auto 50px auto;width: 500px; height: 235px; padding: 5px 15px 5px 15px;border: 1px solid black;  background: #FFF;">
	<div id="cal1" style="width:250px; float:left; text-align:center;">
		<p style="text-align:left; margin-bottom:5px"><?=lang('start_date', 'custom_date_start')?>:&nbsp; <input type="text" name="custom_date_start" id="custom_date_start" value="yyyy-mm-dd" size="12" tabindex="1" /></p>
		<span id="custom_date_start_span"></span>
	</div>
    <div id="cal2" style="width:250px; float:left; text-align:center;">
		<p style="text-align:left; margin-bottom:5px"><?=lang('end_date', 'custom_date_end')?>:&nbsp; <input type="text" name="custom_date_end" id="custom_date_end" value="yyyy-mm-dd" size="12" tabindex="2" /></p>
		<span id="custom_date_end_span"></span>
	</div>
</div>
<br />
<div>
	<?=lang('keywords', 'keywords')?> <?=form_input('keywords', $orders['keywords'], 'class = "field_shun" style = "width:260px;" maxlength = 100')?>
	<?=form_checkbox('exact_match', 'y', $orders['exact_match'])?> <?=lang('exact_match', 'exact_match').NBS.NBS?>
	<?=form_dropdown('search_in', $search_in_options, $orders['search_in']).NBS.NBS?>
	<?=form_submit('search_submit', lang('search'), 'class="submit"')?>
</div>

<?= form_close(); ?>
</fieldset>

<?= form_open($post_url, array('id' => 'dataForm')); ?>
<?php
	$this->table->clear();
	$table_template = $cp_store_table_template;
	$table_template['table_open'] = '<table class="mainTable store_datatable" id="store_orders_table" cellspacing="0" cellpadding="0" border="0">';
	$this->table->set_template($table_template);
	$this->table->set_heading(
		array('data' => '<a id="all" href="#"><img src="'.$this->config->item('theme_folder_url').'cp_global_images/expand.gif"></a>', 'width' => "2%"), //put expand / collapse all button here
		array('data' => '#', 'width' => "2%"),
		lang('billing_name'),
		lang('member'),
		lang('order_date'),
		lang('total'),
		lang('paid?'),
		lang('status'),
		lang('details'),
		form_checkbox(array('id' => 'checkall')),
		array('data' => '', 'width' => '1%')
	);

	echo $this->table->generate($orders['aaData']);
?>

<div style="clear: left; text-align: right;">
	<?= lang('with_selected') ?>
	<?= form_dropdown('action', $actions) ?>
	<?= form_submit(array('name' => 'action_submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>
<?= form_close(); ?>
