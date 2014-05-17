<?php

/**
 * H3_MGMT_Race class.
 *
 * An instance of this class holds all information on a single race
 *
 * @package Hitchhiking Hub Management
 * @since 1.1
 */

if ( ! class_exists( 'H3_MGMT_Race' ) ) :

class H3_MGMT_Race {

	/**
	 * Class Properties
	 *
	 * @since 1.1
	 */
	private $default_args = array(
		'minimalistic' => false
	);
	private $args = array();

	public $id = 0;
	public $ID = 0;

	public $name = 'Tramprennen';
	public $title = 'Tramprennen';

	public $start_time = 0;
	public $end_time = 0;

	public $routes = array();
	public $stages = array();
	public $max_teams_per_route = 12;

	public $teams = array();
	public $teams_by_route = array();
	public $teams_by_pts = array();

	/**
	 * PHP4 style constructor
	 *
	 * @param int $id
	 * @param array $args
	 *
	 * @return void
	 *
	 * @since 1.1
	 * @access public
	 */
	public function H3_MGMT_Race_Activity( $id, $args = array() ) {
		$this->__construct( $id, $args = array() );
	}

	/**
	 * PHP5 style constructor
	 *
	 * @param int $id
	 * @param array $args
	 *
	 * @return void
	 *
	 * @since 1.1
	 * @access public
	 */
	public function __construct( $id, $args = array() ) {
		$this->args = wp_parse_args( $args, $this->default_args );
		$this->id = intval( $id );
		$this->ID = $this->id;

	}

	/**
	 * Populates class properties
	 *
	 * @return void
	 *
	 * @since 1.1
	 * @access public
	 */
	public function gather_meta() {


		return false;
	}

} // class

endif; // class exists

?>