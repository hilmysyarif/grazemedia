<?= form_open($post_url); ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => lang('preference'), 'style' => "width:40%"),
		array('data' => lang('setting')));

	foreach ($setting_defaults as $key => $default)
	{
		$label = '<strong>'.lang($key, "settings_$key").'</strong>';

		if (($label_subtext = lang($key.'_subtext')) != $key.'_subtext')
		{
			$label .= '<div class="subtext">'.$label_subtext.'</div>';
		}

	    $this->table->add_row($label, store_setting_input($key, $default, $settings[$key]));
	}

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>
