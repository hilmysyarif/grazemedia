<?= form_open($post_url) ?>

<div id="store_edit_country_form">
	<?php
		$this->table->clear();
		$this->table->set_template($cp_store_table_template);
		$this->table->set_heading(
			lang('region'),
			array('data' => lang('code'), 'width' => '20%'),
			array('data' => lang('delete'), 'width' => '5%'));

		foreach (array('regions', 'new_regions') as $region_type)
		{
			foreach ($country[$region_type] as $key => $region)
			{
				$region_code_error = (in_array($region['code'], $duplicate_region_codes)) ? '<span class="notice">'.lang('region_code_error').'</span>' : '';
				$this->table->add_row(
					form_input("{$region_type}[{$key}][name]", $region['name']).
					form_error("{$region_type}[{$key}][name]"),
					form_input("{$region_type}[{$key}][code]", $region['code']).
					form_error("{$region_type}[{$key}][code]").$region_code_error,
					form_checkbox("{$region_type}[{$key}][delete]", 'y')
					);
			}
		}

		$this->table->add_row(array(
			'data' => '<a id="store_settings_add_region" href="#">'.lang('add_region').'</a>',
			'colspan' => 3
		));

		echo $this->table->generate();
	?>

	<div style="clear: left; text-align: right;">
		<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
	</div>

</div>

<?= form_close() ?>

<script type="text/javascript">
// <![CDATA[
	$('#store_settings_add_region').click(function() {

		// find the next available row id
		var new_region_id = 0;
		while ($('#store_edit_country_form input:text[name="new_regions['+new_region_id+'][name]"]').size() > 0) {
			new_region_id++;
		}

		elemTr = $(document.createElement('tr'));
		elemTr.append('<td><input type="text" name="new_regions['+new_region_id+'][name]" /></td>');
		elemTr.append('<td><input type="text" name="new_regions['+new_region_id+'][code]" /></td>');
		elemTr.append('<td><input type="checkbox" name="new_regions['+new_region_id+'][delete]" value="y" /></td>');

		$('#store_settings_add_region').closest('tr').before(elemTr);
		return false;
	});
// ]]>
</script>