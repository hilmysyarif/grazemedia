<div class="contentMenu modify store_settings_nav">
	<ul>
		<?php foreach ($pages as $title => $url): ?>
			<li><a href="<?= $url ?>" <?= $title == $current_page ? 'class="active"' : '' ?> ><?= lang('settings_'.$title) ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>

<div class="store_settings_content">
	<?= $content ?>
</div>

<div style="clear: left;"></div>
