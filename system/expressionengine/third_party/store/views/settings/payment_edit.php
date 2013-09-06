<?= form_open($post_url); ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '', 'style' => 'width:40%'),
		array('data' => ''));

	if (empty($payment_method['payment_method_id']))
	{
		$this->table->add_row(
			'<span class="notice">* </span>'.lang('payment_plugin', 'payment_method_class'),
			form_dropdown('payment_method[class]', $payment_class_options, set_value('payment_method[class]', $payment_method['class']), 'id="payment_method_class"').
			form_error('payment_method[class]')
		);
	}
	else
	{
		$this->table->add_row(
			lang('payment_plugin', 'payment_method_class'),
			form_hidden('payment_method[class]', $payment_method['class']).
			$payment_method['class_name']
		);
	}

	$this->table->add_row(
		'<span class="notice">* </span>'.lang('name', 'payment_method_title'),
		form_input('payment_method[title]', set_value('payment_method[title]', $payment_method['title']), 'id="payment_method_title"').
		form_error('payment_method[title]')
	);

	$this->table->add_row(
		'<span class="notice">* </span>'.lang('short_name', 'payment_method_name'),
		form_input('payment_method[name]', set_value('payment_method[name]', $payment_method['name']), 'id="payment_method_name"').
		form_error('payment_method[name]')
	);

	$this->table->add_row(
		lang('enabled', 'payment_method_enabled'),
		store_form_checkbox('payment_method[enabled]', $payment_method['enabled'])
	);

	echo $this->table->generate();
?>

<?php foreach ($payment_drivers as $driver): ?>
	<div id="<?= $driver['class'] ?>_settings" class="payment_driver_settings" <?php if ($driver['class'] != $payment_method['class']) echo 'style="display:none"'; ?>>
		<?php
			$this->table->clear();
			$this->table->set_template($cp_store_table_template);
			$this->table->set_heading(
				array('data' => lang(strtolower($driver['class'])), 'style' => "width:40%"), '');

			foreach ($driver['default_settings'] as $key => $default)
			{
			    $this->table->add_row(
					'<strong>'.lang("merchant_$key", "settings_$key").'</strong>',
					store_setting_input($key, $default, $driver['settings'][$key]));
			}

			if (empty($driver['default_settings']))
			{
			    $this->table->add_row(array('data' => lang('payment_method_no_settings'), 'colspan' => 2));
			}

			echo $this->table->generate();
		?>
	</div>
<?php endforeach; ?>

<p><strong class="notice">*</strong> <?= lang('required_fields') ?></p>
<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>
