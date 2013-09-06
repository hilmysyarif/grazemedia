<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_162
{
    public function up()
    {
        $this->EE =& get_instance();

        // allow longer SKUs
        $this->EE->dbforge->modify_column('store_order_items', array(
            'sku' => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
        ));
        $this->EE->dbforge->modify_column('store_stock', array(
            'sku' => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
        ));
        $this->EE->dbforge->modify_column('store_stock_options', array(
            'sku' => array('name' => 'sku', 'type' => 'varchar', 'constraint' => 40, 'null' => FALSE),
        ));

        // cart format has changed, empty carts table
        $this->EE->db->empty_table('store_carts');
    }
}
