<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?= $report_title ?></title>
	<style type="text/css">
		div.report { font-size: 66%; }
		table { width: 100%; border-collapse: collapse; }
		table td, table th { text-align: left; border: 1px solid #ccc; padding: 0.5em; margin: 0px; }
		table tr.even { background-color: #EBF0F2; }
		table tr.odd { background-color: #F4F6F6; }
	</style>
</head>
<body>
	<div class="report">
		<h1><?= $report_title ?></h1>
		<?php
			$this->table->clear();
			$this->table->set_template(array(
				'table_open' => '<table>',
				'row_start' => '<tr class="even">',
				'row_alt_start' => '<tr class="odd">'));
			$this->table->set_heading($table_head);
			echo $this->table->generate($table_data);
		?>
	</div>
</body>
</html>
