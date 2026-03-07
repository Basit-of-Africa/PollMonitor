<?php
/**
 * Handles the Admin Dashboard for PollMonitor
 */

class PollMonitor_Admin {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_admin_pages() {
		add_submenu_page(
			'edit.php?post_type=poll_station',
			__( 'PollMonitor Dashboard', 'pollmonitor' ),
			__( 'Dashboard', 'pollmonitor' ),
			'manage_options',
			'pollmonitor-dashboard',
			array( $this, 'render_dashboard_page' )
		);
	}

	public function render_dashboard_page() {
		// Include the template file for the dashboard
		require_once POLLMONITOR_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		// Only load assets on our specific dashboard page
		if ( 'poll_station_page_pollmonitor-dashboard' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'pollmonitor-admin-css', POLLMONITOR_PLUGIN_URL . 'assets/css/admin.css', array(), POLLMONITOR_VERSION );
        
        // Leaflet CSS & JS
        wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
        wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

		// Enqueue our custom admin JS and depend on leaflet
		wp_enqueue_script( 'pollmonitor-admin-js', POLLMONITOR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'leaflet-js' ), POLLMONITOR_VERSION, true );
        
        // Pass data to JS via localize
        wp_localize_script( 'pollmonitor-admin-js', 'pollmonitorApiSettings', array(
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' )
		) );
	}
}
