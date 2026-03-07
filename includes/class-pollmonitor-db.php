<?php
/**
 * Handles Custom Database Tables for PollMonitor
 */

class PollMonitor_DB {

	public static function init() {
		// This will be called on plugin activation
		self::create_tables();
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// 1. Table for Election Results
		$table_results = $wpdb->prefix . 'pollmonitor_results';
		$sql_results = "CREATE TABLE $table_results (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			station_id bigint(20) NOT NULL,
			party_a int(11) DEFAULT 0 NOT NULL,
			party_b int(11) DEFAULT 0 NOT NULL,
			party_c int(11) DEFAULT 0 NOT NULL,
            party_d int(11) DEFAULT 0 NOT NULL,
			total_valid int(11) DEFAULT 0 NOT NULL,
            total_invalid int(11) DEFAULT 0 NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			created_by bigint(20) NOT NULL,
			PRIMARY KEY  (id),
            KEY station_id (station_id),
            KEY status (status)
		) $charset_collate;";

		dbDelta( $sql_results );

		// 2. Table for Audit Logs
		$table_audits = $wpdb->prefix . 'pollmonitor_audits';
		$sql_audits = "CREATE TABLE $table_audits (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			action varchar(100) NOT NULL,
			user_id bigint(20) NOT NULL,
			context_id bigint(20) NOT NULL,
            details text,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action)
		) $charset_collate;";

		dbDelta( $sql_audits );
	}

    /**
     * Helper to log actions to the audit table.
     */
    public static function log_action( $action, $user_id, $context_id, $details = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pollmonitor_audits';
        
        $wpdb->insert(
            $table,
            array(
                'action'     => $action,
                'user_id'    => $user_id,
                'context_id' => $context_id,
                'details'    => is_array($details) ? wp_json_encode($details) : $details,
            ),
            array( '%s', '%d', '%d', '%s' )
        );
    }
}
