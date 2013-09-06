<div style='float: right;'>
	<a href="<?= $export_link.AMP.'pdf=1' ?>" class="submit"><?= lang('export_pdf') ?></a>&nbsp;
	<a href="<?= $export_link.AMP.'csv=1' ?>" class="submit"><?= lang('export_csv')?></a>
</div>

<h3><?= $report_title ?></h3>

<div class="store_report_html">
<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading($table_head);
	echo $this->table->generate($table_data);
?>
</div>