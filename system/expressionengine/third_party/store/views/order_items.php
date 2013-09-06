<div class="store_datatables_details_row" style="display: none;">
<table class="store_ft">
	<thead>
		<tr>
			<th style="width:2%"><strong>#</strong></th>
			<th><strong><?= lang('product') ?></strong></th>
			<th><strong><?= lang('sku') ?></strong></th>
			<th><strong><?= lang('modifiers') ?></strong></th>
			<th style="width:10%"><strong><?= lang('price') ?></strong></th>
			<th style="width:5%"><strong><?= lang('quantity') ?></strong></th>
			<th style="width:10%"><strong><?= lang('total') ?></strong></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($order_items as $item): ?>
			<tr>
				<th><?= $item['entry_id'] ?></th>
				<td><?= $item['title'] ?></td>
				<td><?= $item['sku'] ?></td>
				<td><?= $item['modifiers_desc'] ?></td>
				<td><?= $item['price_inc_tax'] ?></td>
				<td><?= $item['item_qty'] ?></td>
				<td><?= $item['item_total'] ?></td>
			</tr>
		<?php endforeach ?>
	</tbody>
</table>
</div>