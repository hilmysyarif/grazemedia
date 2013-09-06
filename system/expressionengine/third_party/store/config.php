<?php

if ( ! defined('STORE_NAME'))
{
	define('STORE_NAME', 'Store');
	define('STORE_CLASS', 'Store');
	define('STORE_VERSION', '1.6.4');
	define('STORE_DESCRIPTION', 'Fully featured e-commerce for ExpressionEngine');
	define('STORE_DOCS', 'http://exp-resso.com/store/docs');
	define('STORE_CP', 'C=addons_modules&amp;M=show_module_cp&amp;module=store');
}

$config['name'] = STORE_NAME;
$config['version'] = STORE_VERSION;
$config['nsm_addon_updater']['versions_xml'] = 'http://exp-resso.com/rss/store/versions.rss';
