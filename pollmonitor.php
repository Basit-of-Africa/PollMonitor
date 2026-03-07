<?php
/**
 * Plugin Name: PollMonitor
 * Description: An election monitoring system for real-time data collection and result aggregation.
 * Version:     1.0.0
 * Author:      Antigravity
 * License:     GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'POLLMONITOR_VERSION', '1.0.0' );
define( 'POLLMONITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'POLLMONITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include core classes
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-cpt.php';
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-api.php';
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-admin.php';
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-frontend.php';
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-db.php';
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-roles.php';
require_once POLLMONITOR_PLUGIN_DIR . 'includes/class-pollmonitor-validator.php';

// Initialize the plugin
function pollmonitor_init() {
	$cpt = new PollMonitor_CPT();
	$cpt->init();

	$api = new PollMonitor_API();
	$api->init();

    if ( is_admin() ) {
        $admin = new PollMonitor_Admin();
        $admin->init();
        
        $validator = new PollMonitor_Validator();
        $validator->init();
    } else {
        $frontend = new PollMonitor_Frontend();
        $frontend->init();
    }
}
add_action( 'plugins_loaded', 'pollmonitor_init' );

// Activation hook
function pollmonitor_activate() {
	$cpt = new PollMonitor_CPT();
	$cpt->register_post_types();
	$cpt->register_taxonomies();
    
    // Create Custom Tables
    PollMonitor_DB::init();
    
    // Register Roles and Capabilities
    PollMonitor_Roles::init();
    
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pollmonitor_activate' );

// Deactivation hook
function pollmonitor_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pollmonitor_deactivate' );
