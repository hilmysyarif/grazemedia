<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include config file
include(PATH_THIRD.'eventbrite_ee/config.php');

/**
 * Low Likes Update class
 *
 * @package        eventbrite
 * @author         Joe Dixon <hello@grazemedia.com>
 * @link           https://wwww.grazemedia.com
 * @copyright      Copyright (c) 2013, Graze Media
 */
class Eventbrite_ee_upd {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * This version
	 *
	 * @access      public
	 * @var         string
	 */
	public $version = EVENTBRITE_VERSION;

	/**
	 * EE Superobject
	 *
	 * @access      private
	 * @var         object
	 */
	private $EE;

	/**
	 * Class name
	 *
	 * @access      private
	 * @var         array
	 */
	private $class_name;

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access     public
	 * @return     void
	 */
	public function __construct()
	{
		// --------------------------------------
		// Get global object
		// --------------------------------------

		$this->EE =& get_instance();

		// --------------------------------------
		// Set class name
		// --------------------------------------

		$this->class_name = ucfirst(EVENTBRITE_PACKAGE);
	}

	// --------------------------------------------------------------------

	/**
	 * Install the module
	 *
	 * @access      public
	 * @return      bool
	 */
	public function install()
	{
		// --------------------------------------
		// Install tables
		// --------------------------------------

		// Load DB Forge class
		$this->EE->load->dbforge();

		// Define fields to create
		$this->EE->dbforge->add_field(array(
			'app_key'  => array('type' => 'varchar', 'constraint' => '50'),
			'user_key' => array('type' => 'varchar', 'constraint' => '50'),
		));

		// Creates the table
		$this->EE->dbforge->create_table('eventbrite_settings');

		// --------------------------------------
		// Add row to modules table
		// --------------------------------------

		$this->EE->db->insert('modules', array(
			'module_name'    => $this->class_name,
			'module_version' => $this->version,
			'has_cp_backend' => 'y'
		));

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Uninstall the module
	 *
	 * @return	bool
	 */
	public function uninstall()
	{
		// --------------------------------------
		// get module id
		// --------------------------------------

		$query = $this->EE->db->select('module_id')
		       ->from('modules')
		       ->where('module_name', $this->class_name)
		       ->get();

		// --------------------------------------
		// remove references from module_member_groups
		// --------------------------------------

		$this->EE->db->where('module_id', $query->row('module_id'));
		$this->EE->db->delete('module_member_groups');

		// --------------------------------------
		// remove references from modules
		// --------------------------------------

		$this->EE->db->where('module_name', $this->class_name);
		$this->EE->db->delete('modules');

		// --------------------------------------
		// Uninstall tables
		// --------------------------------------

		$this->EE->load->dbforge();
		$this->EE->dbforge->drop_table('eventbrite_settings');

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Update the module
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function update($current = '')
	{
		// --------------------------------------
		// Same version? A-okay, daddy-o!
		// --------------------------------------

		if ($current == '' || version_compare($current, $this->version) === 0)
		{
			return FALSE;
		}

		// // Update to next version
		// if (version_compare($current, 'next-version', '<'))
		// {
		// 	// ...
		// }

		// Return TRUE to update version number in DB
		return TRUE;
	}

	// --------------------------------------------------------------------

} // End class

/* End of file upd.low_likes.php */