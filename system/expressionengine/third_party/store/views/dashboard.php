<?php if ($store_ext_enabled == FALSE): ?>
	<div class="store_dashboard_error">
		<a href="<?= $extensions_link ?>"><?= lang('store_ext_disabled') ?></a>
	</div>
<?php endif ?>

<?php if ($store_ft_enabled == FALSE): ?>
	<div class="store_dashboard_error">
		<a href="<?= $fieldtypes_link ?>"><?= lang('store_ft_disabled') ?></a>
	</div>
<?php endif ?>

<?= form_open($post_url, array('id' => 'dataForm')); ?>

<p style="text-align: center;"><strong>
	<?= lang('showing') ?>
	<?= form_dropdown('graph_period', $graph_period_options, $graph_period_selected).NBS ?>
	<?= lang('from') ?>
	<?= form_input($start_date).NBS ?>
	<?= lang('to') ?>
	<?= form_input($end_date).NBS ?>
	<?= form_submit(array('name' => 'action_submit', 'value' => lang('submit'), 'class' => 'submit')) ?>
</strong></p>

<div id="store_graph"></div>

<?= form_close(); ?>
<p>&nbsp;</p><p>&nbsp;</p>
<h3><?= lang('recent_orders') ?></h3>
<?php
	$this->table->clear();
	$this->table->set_template($cp_store_table_template);
	$this->table->set_heading('#', lang('member'), lang('order_total'), lang('paid?'), lang('order_status'), lang('details'));

	foreach ($orders as $order)
	{
		$order_status = '<span style="color:#'.$order['order_status_color'].'">'.lang($order['order_status']).
				'</span> ('.$this->store_config->human_time($order['order_status_updated']).
				') <a class="edit_status" href="#">'.lang('edit_status').'</a><a class="cancel_edit_status" href="#" style="display: none;">'.lang('cancel').'</a>'.
				'<div style="display: none;" class="edit_status_info">
					<br />'.
					    form_open($post_url).
						lang('order_status').': '.form_dropdown('status', $status_select_options ,set_value('status', $order['order_status'])).'<br /><br />'.
						lang('message').': '.form_input('message','','width=10%, style=width:50%').'<br /><br />'.
						form_hidden('order_id', (int)$order['order_id']).
						form_submit(array('name' => 'action_submit', 'value' => lang('submit'), 'class' => 'submit', 'id' => 'status_submit')).
						form_close().'
				</div>';

		$this->table->add_row(
				$order['order_id'],
				$order['screen_name'] == 'System' ? $order['screen_name'] : '<a href="'.$order['member_link'].'">'.$order['screen_name'].'</a>',
				$order['order_total'],
				$order['order_paid_str'],
				$order_status,
				'<a href="'.BASE.AMP.STORE_CP.AMP.'method=orders'.AMP.'order_id='.$order['order_id'].'">'.lang('details').'</a>'
				);
	}

	echo $this->table->generate();
?>