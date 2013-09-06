<div <?= $publish_page ? '' : 'class="store_datatables_details_row" style="display: none;"' ?> >
<table class="store_ft">
<thead>
	<tr>
		<?php if ( ! empty($stock[0]['opt_names'])): ?>
			<?php foreach ($stock[0]['opt_names'] as $mod_key => $opt_name): ?>
				<th><?= $modifiers[$mod_key]['mod_name'] ?></th>
			<?php endforeach ?>
		<?php endif ?>
		<?php if ($publish_page): ?>
			<th><em class="required">*</em> <?= lang('sku') ?></th>
		<?php else: ?>
			<th><?= lang('sku') ?></th>
		<?php endif ?>
		<th>
			<?= form_checkbox(array('value' => 'true', 'checked' => FALSE, 'class' => 'checkall_stock_publish')) ?>
			<?= lang('limit_stock') ?>
		</th>
		<th><?= lang('min_order_qty') ?></th>
	</tr>
</thead>
<tbody>
	<?php foreach ($stock as $key => $row): ?>
		<?php $input_prefix = $prefix."[stock][{$key}]"; ?>
		<tr>
			<?php if ( ! empty($row['opt_names'])): ?>
				<?php foreach ($row['opt_names'] as $key => $value): ?>
					<th>
						<?= form_hidden($input_prefix."[opt_values][{$key}]", $row['opt_values'][$key]) ?>
						<?= $value ?>
					</th>
				<?php endforeach ?>
			<?php endif ?>
			<?php if ($publish_page): ?>
				<td class="store_ft_text">
					<?= form_input($input_prefix.'[sku]', $row['sku'], 'required autocomplete="off"') ?>
					<?php if (form_error($input_prefix.'[sku]')): ?>
						<span class="notice"><?= $row['sku_error'] ?></span>
					<?php endif ?>
				</td>
			<?php else: ?>
				<th>
					<?= form_hidden($input_prefix.'[update_sku]', $row['sku']) ?>
					<?= $row['sku'] ?>
				</th>
			<?php endif ?>
			<?php $editable = ($row['track_stock'] == 'y'); ?>
			<td class="store_ft_text">
				<div class="store_track_stock"><?= form_checkbox($input_prefix.'[track_stock]', 'y', $editable) ?></div>
				<?php
					$stock_level_style = 'placeholder="'.lang('none').'" autocomplete="off"';
					$stock_level_style .= $editable ? '' : ' disabled="disabled" class="disabled"';
				?>
				<div class="store_stock_level"><?= form_input($input_prefix.'[stock_level]', $row['stock_level'], $stock_level_style) ?></div>
			</td>
			<td class="store_ft_text"><?= form_input($input_prefix.'[min_order_qty]', $row['min_order_qty'] > 0 ? $row['min_order_qty'] : '', 'placeholder="'.lang('none').'" autocomplete="off"') ?></td>
		</tr>
	<?php endforeach ?>
</tbody>
</table>
</div>