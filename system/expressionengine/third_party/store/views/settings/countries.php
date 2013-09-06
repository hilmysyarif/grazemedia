<?= form_open($post_url) ?>
<fieldset>
	<legend><?= lang('defaults') ?></legend>
	<table>
		<tr>
			<td>
				<?= lang('default_country').':  '?>
			</td>
			<td>
				<?= form_dropdown("default[country_code]", $country_select, set_value('default[country_code]', $default_country), 'class="store_country_select"')?>
			</td>
		</tr>
		<tr>
			<td>
				<?= lang('default_region').':  '?>
			</td>
			<td>
				<?= form_dropdown("default[region_code]", $region_select, set_value('default[region_code]', $default_region))?>
			</td>
		</tr>
	</table>
<?= form_submit(array('name' => 'submit_default', 'value' => lang('submit'), 'class' => 'submit')) ?>
</fieldset>
<?= form_close() ?>

<?= form_open($post_url) ?>

<div style="text-align: right; margin: 0 0 10px 0;">
	<?= form_dropdown('action_top', array('enable' => lang('enable_selected'), 'disable' => lang('disable_selected'))) ?>
	<?= form_submit(array('name' => 'submit_top', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		lang('code'),
		lang('country'),
		lang('status'),
		array('data' => form_checkbox(array('id' => 'checkall')), 'width' => '2%')
	);

	foreach ($enabled_countries as $country_code => $country)
	{
		$this->table->add_row(
			$country_code,
			'<a href="'.$country['edit_link'].'">'.$country['name'].'</a>',
			store_enabled_str(TRUE),
			form_checkbox('selected[]', $country_code));
	}

	foreach ($disabled_countries as $country_code => $country)
	{
		$this->table->add_row(
			$country_code,
			$country['name'],
			store_enabled_str(FALSE),
			form_checkbox('selected[]', $country_code));
	}

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_dropdown('action_bot', array('enable' => lang('enable_selected'), 'disable' => lang('disable_selected'))) ?>
	<?= form_submit(array('name' => 'submit_bot', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>