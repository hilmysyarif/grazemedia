<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

class Store_pdf
{
	public function __construct()
	{
		$this->EE =& get_instance();

		// disable dompdf log file
		defined('DOMPDF_LOG_OUTPUT_FILE') OR define('DOMPDF_LOG_OUTPUT_FILE', FALSE);

		require_once(PATH_THIRD.'store/libraries/dompdf/dompdf_config.inc.php');
	}

	public function output($html, $filename)
	{
		$paper = $this->EE->store_config->item('export_pdf_page_format');
		$orientation = $this->EE->store_config->item('export_pdf_orientation') == 'L' ? 'landscape' : 'portrait';

		$dompdf = new DOMPDF();
		$dompdf->set_paper($paper, $orientation);
		$dompdf->load_html($html);
		$dompdf->render();
		$dompdf->stream($filename);
	}
}

/* End of file ./libraries/store_pdf.php */