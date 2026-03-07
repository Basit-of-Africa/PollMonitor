<?php
/**
 * Handles Custom Post Types and Taxonomies for PollMonitor
 */

class PollMonitor_CPT {

	public function init() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	public function register_post_types() {
		// Register Poll Station CPT
		$labels_station = array(
			'name'                  => _x( 'Poll Stations', 'Post Type General Name', 'pollmonitor' ),
			'singular_name'         => _x( 'Poll Station', 'Post Type Singular Name', 'pollmonitor' ),
			'menu_name'             => __( 'Poll Stations', 'pollmonitor' ),
			'all_items'             => __( 'All Stations', 'pollmonitor' ),
			'add_new_item'          => __( 'Add New Station', 'pollmonitor' ),
			'edit_item'             => __( 'Edit Station', 'pollmonitor' ),
		);
		$args_station = array(
			'label'                 => __( 'Poll Station', 'pollmonitor' ),
			'labels'                => $labels_station,
			'supports'              => array( 'title', 'custom-fields' ),
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-location',
			'show_in_rest'          => true,
			'has_archive'           => true,
		);
		register_post_type( 'poll_station', $args_station );

		// Register Incident Report CPT
		$labels_incident = array(
			'name'                  => _x( 'Incident Reports', 'Post Type General Name', 'pollmonitor' ),
			'singular_name'         => _x( 'Incident Report', 'Post Type Singular Name', 'pollmonitor' ),
			'menu_name'             => __( 'Incidents', 'pollmonitor' ),
			'all_items'             => __( 'All Incidents', 'pollmonitor' ),
			'add_new_item'          => __( 'Add New Incident', 'pollmonitor' ),
			'edit_item'             => __( 'Edit Incident', 'pollmonitor' ),
		);
		$args_incident = array(
			'label'                 => __( 'Incident Report', 'pollmonitor' ),
			'labels'                => $labels_incident,
			'supports'              => array( 'title', 'editor', 'custom-fields' ),
			'public'                => false, // Internal tracking mainly
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 21,
			'menu_icon'             => 'dashicons-warning',
			'show_in_rest'          => true, // Expose to API
		);
		register_post_type( 'incident_report', $args_incident );
	}

	public function register_taxonomies() {
        // Taxonomies for Poll Stations
		register_taxonomy( 'state', array( 'poll_station' ), array(
			'hierarchical'      => true,
			'labels'            => array( 'name' => 'States', 'singular_name' => 'State' ),
			'show_ui'           => true,
			'show_in_rest'      => true,
		) );

		register_taxonomy( 'lga', array( 'poll_station' ), array(
			'hierarchical'      => true,
			'labels'            => array( 'name' => 'LGAs', 'singular_name' => 'LGA' ),
			'show_ui'           => true,
			'show_in_rest'      => true,
		) );

        register_taxonomy( 'ward', array( 'poll_station' ), array(
			'hierarchical'      => true,
			'labels'            => array( 'name' => 'Wards', 'singular_name' => 'Ward' ),
			'show_ui'           => true,
			'show_in_rest'      => true,
		) );

        // Taxonomies for Incidents
        register_taxonomy( 'incident_type', array( 'incident_report' ), array(
			'hierarchical'      => true,
			'labels'            => array( 'name' => 'Incident Types', 'singular_name' => 'Incident Type' ),
			'show_ui'           => true,
			'show_in_rest'      => true,
		) );
        
        register_taxonomy( 'severity', array( 'incident_report' ), array(
			'hierarchical'      => true,
			'labels'            => array( 'name' => 'Severities', 'singular_name' => 'Severity' ),
			'show_ui'           => true,
			'show_in_rest'      => true,
		) );
	}
}
