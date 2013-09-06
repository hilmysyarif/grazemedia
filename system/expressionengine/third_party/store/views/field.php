<?php
if ( ! function_exists('store_product_modifier'))
{
	/**
	 * Helper function to create product modifier row
	 */
	function store_product_modifier($key, $mod_data, $modifier_select, $tbody_style = '')
	{
		$mod_prefix = "store_product_field[modifiers][{$key}]";

		if (empty($mod_data['mod_type'])) { $mod_data['mod_type'] = key($modifier_select); }

		// always ensure there is at least one option (even on text inputs it doesn't matter)
		if (empty($mod_data['options']))
		{
			$mod_data['options'] = array(1 => array('opt_name' => '', 'opt_price_mod' => '', 'opt_order' => 0));
		}

		$new_opt_key = max(array_keys($mod_data['options'])) + 1;
		$hide_options = in_array($mod_data['mod_type'], array('var', 'var_single_sku')) ? '' : 'style="display: none"';
		?>
		<tbody class="store_product_modifier" <?= $tbody_style ?> >
			<tr>
				<th>
					<input type="hidden" name="<?= $mod_prefix ?>[mod_order]" value="<?= $mod_data['mod_order'] ?>" class="store_input_mod_order" />
					<div class="store_handle store_modifier_handle"></div>
				</th>
				<td><?= form_dropdown($mod_prefix.'[mod_type]', $modifier_select, $mod_data['mod_type'], 'class="store_select_mod_type"') ?></td>
				<td class="store_ft_text"><?= form_input($mod_prefix.'[mod_name]', $mod_data['mod_name'], 'class="store_input_mod_name" autocomplete="off" required').form_error($mod_prefix.'[mod_name]') ?></td>
				<td class="store_ft_text"><?= form_input($mod_prefix.'[mod_instructions]', $mod_data['mod_instructions'], 'autocomplete="off"') ?></td>
				<td>
					<div style="margin:0;padding:0;display:inline;"><!-- ie7 spacer --></div>
					<div class="store_product_options_wrap" <?= $hide_options ?>>
						<table class="store_ft store_product_options_table">
							<thead>
								<tr>
									<th style="width:2%">&nbsp;</th>
									<th style="width:48%"><?= lang('option') ?></th>
									<th style="width:48%"><?= lang('price_modifier') ?></th>
									<th style="width:2%">&nbsp;</th>
								</tr>
							</thead>
							<tbody>
								<?php
									// print any new or existing variation options
									foreach ($mod_data['options'] as $opt_id => $opt_data)
									{
										$opt_prefix = $mod_prefix."[options][{$opt_id}]";
										?>
										<tr class="store_product_option_row">
											<th>
												<input type="hidden" name="<?= $opt_prefix ?>[opt_order]" value="<?= $opt_data['opt_order'] ?>" class="store_input_opt_order" />
												<div class="store_handle store_option_handle"></div>
											</th>
											<td class="store_ft_text"><?= form_input($opt_prefix.'[opt_name]', $opt_data['opt_name'], 'class="store_input_opt_name" autocomplete="off"') ?></td>
											<td class="store_ft_text"><?= form_input($opt_prefix.'[opt_price_mod]', $opt_data['opt_price_mod'], 'placeholder="'.lang('none').'" autocomplete="off"') ?></td>
											<td><a href="#" class="store_product_option_remove"><?= lang('remove') ?></a></td>
										</tr>
										<?php
									}
								?>
							</tbody>
						</table>
						<div class="store_ft_add"><a href="#" class="store_product_option_add" data-mod-key="<?= $key ?>" data-new-opt-key="<?= $new_opt_key ?>"><i class="store_icon_add"></i><?= lang('add_new_option') ?></a></div>
					</div>
				</td>
				<td><a href="#" class="store_product_modifier_remove"><?= lang('remove') ?></a></td>
			</tr>
		</tbody>
		<?php
	}
}
?>

<?= form_hidden($field_name, 'store'); ?>
<div id="store_product_field">

<div class="store_field_pane">

	<div style="margin-bottom: 0.5em"><small><?= lang('prices_excluding_tax') ?></small></div>

	<table class="store_ft">
		<thead>
			<tr>
				<th style="width: 20%"><em class="required">* </em><?= lang('price') ?></th>
				<th style="width: 40%" colspan="2"><?= lang('sale_price') ?></th>
				<th style="width: 20%"><?= lang('sale_start_date') ?></th>
				<th style="width: 20%"><?= lang('sale_end_date') ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="store_ft_text"><?= form_input('store_product_field[regular_price]', $product['regular_price'], 'required').form_error('store_product_field[regular_price]') ?></td>
				<td class="store_ft_text"><?= form_input('store_product_field[sale_price]', $product['sale_price']) ?></td>
				<td>
					<?= form_hidden('store_product_field[sale_price_enabled]', '') ?>
					<label><?= form_checkbox('store_product_field[sale_price_enabled]', 'y', $product['sale_price_enabled'] == 'y') ?> <?= lang('sale_price_enabled') ?></label>
				</td>
				<td class="store_ft_text"><?= form_input('store_product_field[sale_start_date]', $product['sale_start_date'], 'class="store_datetimepicker"') ?></td>
				<td class="store_ft_text"><?= form_input('store_product_field[sale_end_date]', $product['sale_end_date'], 'class="store_datetimepicker"') ?></td>
			</tr>
		</tbody>
	</table>

</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" style="width: 10px; height: 13px;" alt="" />
	<?= lang('product_modifiers') ?>
</label>
<div class="store_field_pane">

<div style="margin:0;padding:0;display:inline;">
	<!-- this allows us to detect removed product modifiers and options -->
	<?php
		foreach ($product['modifiers'] as $mod_key => $mod_data)
		{
			if (isset($mod_data['product_mod_id']))
			{
				echo form_hidden("store_product_field[modifiers][{$mod_key}][product_mod_id]", $mod_data['product_mod_id']);

				if (empty($mod_data['options'])) continue;

				foreach ($mod_data['options'] as $opt_key => $opt_data)
				{
					if (isset($opt_data['product_opt_id']))
					{
						echo form_hidden("store_product_field[modifiers][{$mod_key}][options][{$opt_key}][product_opt_id]", $opt_data['product_opt_id']);
					}
				}
			}
		}
	?>
</div>

<table id="store_product_modifiers_table" cellspacing="0" cellpadding="0" border="0" class="store_ft">
	<thead>
		<tr>
			<th style="width:2%">&nbsp;</th>
			<th style="width:18%"><?= lang('mod_type') ?></th>
			<th style="width:15%"><em class="required">* </em><?= lang('name') ?></th>
			<th style="width:25%"><?= lang('mod_instructions') ?></th>
			<th><?= lang('options') ?></th>
			<th style="width:2%">&nbsp;</th>
		</tr>
	</thead>
	<tbody id="store_product_modifier_empty" <?php if (count($product['modifiers']) > 0): ?>style="display: none"<?php endif ?> >
		<tr><td colspan="6"><?= lang('no_product_modifiers_defined') ?></td></tr>
	</tbody>
	<?php
		// print any new or existing product modifiers
		foreach ($product['modifiers'] as $key => $mod_data)
		{
			store_product_modifier($key, $mod_data, $modifier_select);
		}

		// print the product modifier template row (used in javascript)
		store_product_modifier(
			'new',
			array('mod_type' => '', 'mod_name' => '', 'mod_instructions' => '', 'mod_order' => 0),
			$modifier_select,
			'id="store_product_modifier_template" style="display: none"'
		);
	?>
</table>
<div class="store_ft_add"><a href="#" id="store_product_modifiers_add" data-new-mod-key="<?= $new_mod_key ?>"><i class="store_icon_add"></i><?= lang('add_product_modifier') ?></a></div>
</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_expand.png" style="width: 10px; height: 13px;" alt="" />
	<?= lang('stock') ?>
</label>
<div class="store_field_pane">
	<div id="store_product_stock_loading" style="display: none">
		<img src="<?= PATH_CP_GBL_IMG ?>loadingAnimation.gif" alt="<?= lang('loading') ?>" style="width: 208px; height: 13px;" />
	</div>
	<div id="store_product_stock">
		<?= $stock_html ?>
	</div>
</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_collapse.png" style="width: 10px; height: 13px;" alt="" />
	<?= lang('shipping') ?>
</label>
<div class="store_field_pane" style="display: none;">
	<table class="store_ft">
		<thead>
			<tr>
				<th style="width: 16%"><?= lang('weight') ?> (<?= lang($weight_units) ?>)</th>
				<th style="width: 16%"><?= lang('dimension_l') ?> (<?= lang($dimension_units) ?>)</th>
				<th style="width: 16%"><?= lang('dimension_w') ?> (<?= lang($dimension_units) ?>)</th>
				<th style="width: 16%"><?= lang('dimension_h') ?> (<?= lang($dimension_units) ?>)</th>
				<th style="width: 16%"><?= lang('handling') ?></th>
				<th style="width: 16%"></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="store_ft_text"><?= form_input('store_product_field[weight]', $product['weight'], 'autocomplete="off"') ?></td>
				<td class="store_ft_text"><?= form_input('store_product_field[dimension_l]', $product['dimension_l'], 'autocomplete="off"') ?></td>
				<td class="store_ft_text"><?= form_input('store_product_field[dimension_w]', $product['dimension_w'], 'autocomplete="off"') ?></td>
				<td class="store_ft_text"><?= form_input('store_product_field[dimension_h]', $product['dimension_h'], 'autocomplete="off"') ?></td>
				<td class="store_ft_text"><?= form_input('store_product_field[handling]', $product['handling'], 'autocomplete="off"') ?></td>
				<td>
					<?= form_hidden('store_product_field[free_shipping]', '') ?>
					<label><?= form_checkbox('store_product_field[free_shipping]', 'y', $product['free_shipping']) ?> <?= lang('free_shipping') ?></label>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<label class="store_hide_field">
	<img src="<?= $this->cp->cp_theme_url ?>images/field_collapse.png" style="width: 10px; height: 13px;" alt="" />
	<?= lang('advanced') ?>
</label>
<div class="store_field_pane" style="display: none;">
	<?= form_hidden('store_product_field[tax_exempt]', '') ?>
	<label>
		<?= form_checkbox('store_product_field[tax_exempt]', 'y', $product['tax_exempt']) ?>
		<?= lang('product_tax_exempt') ?>
	</label>
</div>

</div>