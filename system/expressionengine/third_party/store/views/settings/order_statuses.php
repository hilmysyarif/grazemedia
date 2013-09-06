<div style="text-align: right; margin: 5px 0 15px 0;">
	<a href="<?= $new_order_status_link ?>" class="submit"><?= lang('add_new_status') ?></a>
</div>

<table class="mainTable store_table" id="order_status_table" border="0" cellspacing="0" cellpadding="0">
<thead>
	<tr>
		<th width="2%"></th>
		<th><?=lang('title')?></th>
		<th><?=lang('status_color')?></th>
		<th><?=lang('email_template')?></th>
	</tr>
</thead>
<tbody class="store_sortable_table" id="order_statuses">
	<?php foreach($statuses as $status): ?>
		<?php $status_name = $status['is_default'] == 'y' ?
	       	  '<strong><a href="'.$status['edit_link'].'">'.lang($status['name']).'</a></strong> ('.lang('default').')' :
	       	  '<a href="'.$status['edit_link'].'">'.lang($status['name']).'</a>'; ?>
		<tr id="<?="status_id_".$status['order_status_id']?>" data-order="">
			<td><div class="store_handle"></div></td>
			<td> <?= $status_name ?> </td>
			<td>
				<?= empty($status['highlight']) ? lang('default') : '<span style="color:#'.$status['highlight'].';">'.$status['highlight'].'</span>'?>
			</td>
			<td> <?= lang($status['email_template_name']) ?> </td>
		</tr>
	<?php endforeach ?>
</tbody>
</table>