<p><?= sprintf(lang('store_not_yet_installed'), "<b>$site_name</b>") ?></p>

<?php if ($is_super_admin): ?>

	<?= form_open($post_url) ?>
		<p>
			<?= lang('duplicate_settings_from') ?>
			<?= form_dropdown('duplicate_site', $duplicate_options) ?>
		</p>
		<p>
			<?= form_checkbox('install_example_templates', '1', TRUE, 'id="install_example_templates"') ?>
			<?= form_label(lang('install_example_templates'), 'install_example_templates') ?>
		</p>
		<p><?= form_submit(array('name' => 'submit', 'value' => lang('install_now'), 'class' => 'submit')) ?></p>
	<?= form_close() ?>

<?php else: ?>

	<p><?= lang('install_store_super_admin') ?></p>

<?php endif ?>