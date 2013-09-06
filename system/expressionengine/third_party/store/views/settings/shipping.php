<?= form_open($post_url) ?>

<fieldset style="margin-bottom: 15px;">
	<legend><?= lang('defaults') ?></legend>
	<?= lang('pre_selected_shipping_method') ?>&nbsp;
	<?= form_dropdown('default_shipping_method_id', $shipping_method_select, $default_shipping_method_id) ?>&nbsp;
	<?= form_submit(array('name' => 'submit_default', 'value' => lang('submit'), 'class' => 'submit')) ?>
</fieldset>

<?= form_close() ?>

<?= form_open($post_url) ?>

<div style="text-align: right; margin: 5px 0 15px 0;">
	<a href="<?= $add_plugin_link ?>" class="submit"><?= lang('shipping_add_plugin') ?></a>
</div>

<table class="mainTable store_table" id="plugins_table" border="0" cellspacing="0" cellpadding="0">
<thead>
	<tr>
		<th width="2%"></th>
		<th width="2%">#</th>
		<th width="30%"><?= lang('shipping_method') ?></th>
		<th width="30%"><?= lang('plugin') ?></th>
		<th width="20%"><?= lang('status') ?></th>
		<th width="2%"><?= form_checkbox(array('id' => 'checkall')) ?></th>
	</tr>
</thead>
<tbody class="store_sortable_table" id="plugins">
	<?php foreach($shipping_methods as $method): ?>
		<tr id="<?="shipping_method_id_".$method['shipping_method_id']?>" data-order="">
			<td><div class="store_handle"></div></td>
			<td><?= $method['shipping_method_id'] ?></td>
			<td><?= $method['enabled'] ?
					 '<a href="'.$method['settings_link'].'">'.$method['title'].'</a>' :
					 $method['title']?></td>
			<td><?= lang(strtolower($method['class'])) ?></td>
			<td><?= store_enabled_str($method['enabled']).' (<a href="'.$method['edit_link'].'">'.lang('rename').'</a>)' ?></td>
			<td><?= form_checkbox('selected[]', $method['shipping_method_id']) ?></td>
		</tr>
	<?php endforeach ?>
	<?php if (empty($shipping_methods)): ?>
		<tr>
			<td colspan="6" style="font-style:italic">
				<?= lang('no_shipping_methods') ?>
			</td>
	<?php endif ?>
</tbody>
</table>
<div style="text-align: right;">
	<?= form_dropdown('with_selected', array('enable' => lang('enable_selected'), 'disable' => lang('disable_selected'), 'delete' => lang('delete_selected'))) ?>
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>