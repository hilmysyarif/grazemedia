<?= form_open($post_url); ?>
<div id="communicate_info">
	<div id="email_help_text">
		<p><?= lang('email_templates_can_contain') ?></p>
		<p><?= lang('email_templates_to_display_order_details') ?></p>
		<ul>
			<li>{order_id}</li>
			<li>{order_total}</li>
			<li>{shipping_name}</li>
			<li>{shipping_address1}</li>
			<li>{order_status_message}</li>
		</ul>
		<br />
		<p><?= lang('email_templates_to_display_items') ?></p>
		<ul>
			<li>{items}</li>
			<li>
				<ul>
					<li>{title}</li>
					<li>{item_total}</li>
					<li>{description}</li>
					<li>{sku}</li>
				</ul>
			</li>
			<li>{/items}</li>
		</ul>
		<br />
		<p><?= lang('email_templates_member_details') ?></p>
		<ul>
			<li>{screen_name}</li>
			<li>{ip_address}</li>
			<li>{total_comments}</li>
			<li>{timezone}</li>
		</ul>
	</div>
</div>
<div id="communicate_compose">
	<p></p>
		<?php if ($template['locked'] == 'n'): ?>
			<strong class="notice">*</strong> <?= lang('name', 'name').':' ?>
			<?= form_label($template['name'],'name') ?>
			<?= form_input('name', set_value('name', $template['name'])) ?>
			<?= form_error('name') ?>
		<?php else: ?>
			<?= form_hidden('name',$template['name']) ?>
			<strong><?=lang('name', 'name').':'.NBS.NBS ?> <?= $template['name'] ?> </strong>
		<?php endif; ?>
	<p></p>
		<strong class="notice">*</strong> <?=lang('subject', 'subject') ?>
		<?= form_input(array('id'=>'subject','name'=>'subject','class'=>'fullfield','value'=>set_value('subject', $template['subject']))) ?>
		<?= form_error('subject') ?>
	<p></p>
		<?= lang('bcc', 'bcc') ?>
		<?= form_input('bcc', set_value('bcc', $template['bcc'])) ?>
		<?= form_error('bcc') ?>
	<p style="margin-bottom:15px"></p>
		<strong class="notice">*</strong> <?= lang('message', 'message') ?><br />
		<?= form_error('contents') ?>
		<?= form_textarea(array('id'=>'message','name'=>'contents','rows'=>20,'cols'=>85,'class'=>'fullfield','value'=>set_value('contents', $template['contents']))) ?>
	<?php
		$this->table->set_template($cp_store_table_template);

		$this->table->add_row(
					array(
						array('data' => lang('mail_format', 'mail_format'), 'style' => 'width:30%;'),
						form_dropdown('mail_format', array('text' => lang('plain_text'), 'html' => lang('html')), $template['mail_format'])
						)
					);

		$this->table->add_row(
					array(
						lang('wordwrap', 'wordwrap'),
						form_dropdown('word_wrap', array('y' => lang('on'), 'n' => lang('off')), $template['word_wrap'])
						)
					);

		$this->table->add_row(
					array(
						'<strong>'.lang('enabled', 'enabled').'</strong>',
						form_hidden('enabled', 'n').
						form_checkbox('enabled', 'y', $template['enabled'] == 'y')
						)
					);

		echo $this->table->generate();
	?>
	<p></p><strong class="notice">*</strong> <?= lang('required_fields') ?>
	<div style="clear: left; text-align: right;">
		<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
	</div>
</div>
<div style="clear:both">
</div>
<?= form_close(); ?>