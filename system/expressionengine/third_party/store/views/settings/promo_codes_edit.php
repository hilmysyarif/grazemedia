<?= form_open($post_url) ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => '', 'width' => "40%"),
		array('data' => ''));

	$this->table->add_row(
			lang('code','promo_code[promo_code]').
			'<div class="subtext">'.lang('if_promo_code_empty_spiel').'</div>',
			form_input('promo_code[promo_code]', set_value('promo_code[promo_code]', $promo_code['promo_code']))
	);

	$this->table->add_row(
			lang('description', 'promo_code[description]'),
			form_input('promo_code[description]', set_value('promo_code[description]', $promo_code['description']))
	);

	$this->table->add_row(
			'<strong class="notice">*</strong> '.lang('promo_value', 'promo_code[value]'),
			form_input('promo_code[value]', set_value('promo_code[value]', $promo_code['value_str'])).
			form_error('promo_code[value]')
	);

	$this->table->add_row(
			lang('promo_type', 'promo_code[type]'),
			form_dropdown('promo_code[type]', array('p' => lang('promo_type_p'), 'v' => lang('promo_type_v')), set_value('promo_code[type]', $promo_code['type']))
	);

	$this->table->add_row(
			lang('free_shipping', 'promo_code[free_shipping]'),
			form_dropdown('promo_code[free_shipping]', array('n' => lang('no'), 'y' => lang('yes')), set_value('promo_code[free_shipping]', $promo_code['free_shipping']))
	);

	$this->table->add_row(
			lang('promo_start_date','promo_code[start_date]'),
			form_input(array(
				'class' => 'store_datetimepicker',
				'name' => 'promo_code[start_date]',
				'value' => set_value('promo_code[start_date]', empty($promo_code['start_date']) ? '' : $this->store_config->human_time($promo_code['start_date']))))
	);

	$this->table->add_row(
			lang('promo_end_date','promo_code[end_date]'),
			form_input(array(
				'class' => 'store_datetimepicker',
				'name' => 'promo_code[end_date]',
				'value' => set_value('promo_code[end_date]', empty($promo_code['end_date']) ? '' : $this->store_config->human_time($promo_code['end_date']))))
	);

	$this->table->add_row(
			lang('promo_member_group','promo_code[member_group_id]'),
			form_dropdown('promo_code[member_group_id]', $member_groups, $promo_code['member_group_id'])
	);

	$this->table->add_row(
			lang('per_user_limit','promo_code[per_user_limit]'),
			form_input('promo_code[per_user_limit]', set_value('promo_code[per_user_limit]', $promo_code['per_user_limit']))
	);

	$this->table->add_row(
			lang('use_limit','promo_code[use_limit]'),
			form_input('promo_code[use_limit]', set_value('promo_code[use_limit]', $promo_code['use_limit']))
	);

	$this->table->add_row(
		lang('enabled', 'promo_code[enabled]'),
		form_hidden('promo_code[enabled]', 'n').
		form_checkbox('promo_code[enabled]', 'y', set_value('promo_code[enabled]', $promo_code['enabled']) == 'y')
	);

	echo $this->table->generate();
?>

<p><span class="notice">*</span> <?= lang('required_fields') ?></p>

<div style="clear: left; text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</div>

<?= form_close() ?>