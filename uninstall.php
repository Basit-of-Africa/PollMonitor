<?php
/**
 * Uninstall handler for PollMonitor plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_results = $wpdb->prefix . 'pollmonitor_results';
$table_audits  = $wpdb->prefix . 'pollmonitor_audits';

$wpdb->query( "DROP TABLE IF EXISTS {$table_results}" );
$wpdb->query( "DROP TABLE IF EXISTS {$table_audits}" );

// Remove transients used by the plugin
delete_transient( 'pollmonitor_total_stations' );
delete_transient( 'pollmonitor_total_incidents' );
delete_transient( 'pollmonitor_recent_incidents' );

// Remove roles
if ( function_exists( 'remove_role' ) ) {
    remove_role( 'pollmonitor_observer' );
    remove_role( 'pollmonitor_validator' );
}

// Optionally remove capabilities from administrators (best-effort)
$role = get_role( 'administrator' );
if ( $role ) {
    $role->remove_cap( 'pollmonitor_submit' );
    $role->remove_cap( 'pollmonitor_validate' );
    $role->remove_cap( 'pollmonitor_manage_all' );
}
