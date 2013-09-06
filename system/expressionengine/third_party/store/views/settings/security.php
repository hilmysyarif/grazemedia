<?= form_open($post_url) ?>
<?php
	$groups = array('');

	foreach($member_groups as $key => $mem_group)
	{
		$groups[] = '<div style="text-align: center">'.lang($mem_group['group_title']).'</div>';
	}

	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading($groups);

	foreach($security as $privilege => $values)
	{
		$row = array(lang($privilege));
		foreach($member_groups as $key => $group)
		{
			if ($group['group_id'] == 1)
			{
				$row[] = '<div style="text-align: center">'.form_checkbox(array('checked' => TRUE, 'disabled' => 'disabled')).'</div>';
			}
			else
			{
				$row[] = '<div style="text-align: center">'.form_checkbox('security['.$privilege.'][]', $group['group_id'], in_array($group['group_id'], $values)).'</div>';
			}
		}
		$this->table->add_row($row);
	}

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>
<?= form_close() ?>