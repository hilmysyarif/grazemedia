<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_upd_163
{
    public function up()
    {
        $this->EE =& get_instance();

        // enlarge cart field
        $this->EE->dbforge->modify_column('store_carts', array(
            'contents' => array('name' => 'contents', 'type' => 'mediumtext', 'null' => FALSE)
        ));
    }
}
