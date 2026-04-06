<?php
/**
 * Handles Custom Database Tables for PollMonitor
 */

class PollMonitor_DB {

	public static function init() {
		// This will be called on plugin activation
		self::create_tables();
	}

    public static function register_hooks() {
        add_action( 'save_post_poll_station', array( __CLASS__, 'clear_dashboard_cache' ) );
        add_action( 'save_post_incident_report', array( __CLASS__, 'clear_dashboard_cache' ) );
        add_action( 'trashed_post', array( __CLASS__, 'maybe_clear_dashboard_cache_for_post' ) );
        add_action( 'untrashed_post', array( __CLASS__, 'maybe_clear_dashboard_cache_for_post' ) );
        add_action( 'deleted_post', array( __CLASS__, 'maybe_clear_dashboard_cache_for_post' ) );
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

        self::maybe_publish_realtime_event( $action, $context_id, $details );
    }

    public static function clear_dashboard_cache() {
        delete_transient( 'pollmonitor_total_stations' );
        delete_transient( 'pollmonitor_total_incidents' );
        delete_transient( 'pollmonitor_recent_incidents' );
    }

    public static function maybe_clear_dashboard_cache_for_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        if ( in_array( $post->post_type, array( 'poll_station', 'incident_report' ), true ) ) {
            self::clear_dashboard_cache();
        }
    }

    public static function maybe_publish_realtime_event( $action, $context_id, $details = '' ) {
        $realtime_actions = array(
            'incident_created',
            'incident_approved',
            'incident_rejected',
            'results_submitted',
        );

        if ( ! in_array( $action, $realtime_actions, true ) ) {
            return;
        }

        $sequence = (int) get_option( 'pollmonitor_realtime_event_sequence', 0 ) + 1;
        update_option( 'pollmonitor_realtime_event_sequence', $sequence, false );

        $payload = array(
            'id'         => $sequence,
            'action'     => $action,
            'context_id' => (int) $context_id,
            'time'       => current_time( 'mysql', true ),
            'message'    => self::build_realtime_message( $action, $context_id ),
        );

        if ( is_array( $details ) ) {
            $payload['details'] = $details;
        }

        update_option( 'pollmonitor_realtime_event_payload', $payload, false );
    }

    protected static function build_realtime_message( $action, $context_id ) {
        switch ( $action ) {
            case 'incident_created':
                $title = get_the_title( $context_id );
                return $title ? sprintf( 'New incident submitted: %s', $title ) : 'New incident submitted.';
            case 'incident_approved':
                return 'An incident was approved.';
            case 'incident_rejected':
                return 'An incident was rejected.';
            case 'results_submitted':
                return 'New polling unit results were submitted.';
            default:
                return 'PollMonitor dashboard update available.';
        }
    }
}
