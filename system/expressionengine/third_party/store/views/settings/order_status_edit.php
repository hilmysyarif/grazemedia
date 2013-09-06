<?= form_open($post_url); ?>

<?php if ( ! $editable): ?>

	<p style="margin-bottom: 1em;"><strong class="notice"><?= lang('status_in_use') ?></strong></p>

<?php elseif ($status['is_default'] == 'y'): ?>

	<p style="margin-bottom: 1em;"><strong class="notice"><?= lang('status_is_default') ?></strong></p>

<?php endif ?>

<?php
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading(
		array('data' => lang('status_field_name'), 'width' => '40%'),
		lang('status_field_value'));

	$status_name_error = ($duplicate_name) ? '<span class="notice">'.lang('duplicate_status_error').'</span>' : '';

	$this->table->add_row(array(
		'<span class="notice"> * </span>'.lang('status_name'),
		form_input('status[name]', set_value('status[name]', $status['name']), $editable ? 'autofocus' : 'disabled').
		form_error('status[name]').$status_name_error));

	$this->table->add_row(array(
		lang('status_highlight'),
		form_input('status[highlight]', isset($_POST['status']['highlight']) ? $_POST['status']['highlight'] : $status['highlight'], 'id="store_status_highlight" placeholder="'.lang('default').'"').BR.
		'<a href="#" class="store_colorswatch" data-color=""></a>'.
		'<a href="#" class="store_colorswatch" data-color="e7174b" style="background-color: #e7174b"></a>'.
		'<a href="#" class="store_colorswatch" data-color="f77400" style="background-color: #f77400"></a>'.
		'<a href="#" class="store_colorswatch" data-color="009933" style="background-color: #009933"></a>'.
		'<a href="#" class="store_colorswatch" data-color="02d7e1" style="background-color: #02d7e1"></a>'.
		'<a href="#" class="store_colorswatch" data-color="0b02e1" style="background-color: #0b02e1"></a>'.
		'<a href="#" class="store_colorswatch" data-color="e102d8" style="background-color: #e102d8"></a>'));

	$this->table->add_row(array(
		lang('make_default'),
		$status['is_default'] == 'y' ?
			form_checkbox('status[is_default]', 'y', TRUE, 'disabled') :
			form_checkbox('status[is_default]', 'y', isset($_POST['status']['is_default']))));

	$this->table->add_row(array(
		lang('email_template'),
		form_dropdown('status[email_template]', $email_templates, isset($_POST['status']['email_template']) ? $_POST['status']['email_template'] : $status['email_template'])));

	echo $this->table->generate();
?>

<p><strong class="notice">*</strong> <?= lang('required_fields') ?></p>

<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?php if ($editable AND $status['is_default'] != 'y'): ?>
	<div style="text-align: left; align:left">
		<?= form_submit(array('name' => 'delete', 'value' => lang('delete'), 'class' => 'submit')); ?>
	</div>
<?php endif ?>

<?= form_close(); ?>

<script type="text/javascript">
$(document).ready(function() {
	$('#mainContent .store_colorswatch').click(function() {
		$('#store_status_highlight').val($(this).attr('data-color')).keyup();
		return false;
	});
	$('#store_status_highlight').keyup(function() {
		$(this).css('color', '#'+$(this).val());
	});
});
</script>