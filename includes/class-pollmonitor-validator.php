<?php
/**
 * Handles the Verification Workflow for Regional Validators
 */

class PollMonitor_Validator {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_validator_page' ) );
        add_action( 'admin_post_pollmonitor_approve_incident', array( $this, 'handle_approval_action' ) );
        add_action( 'admin_post_pollmonitor_reject_incident', array( $this, 'handle_rejection_action' ) );
	}

	public function add_validator_page() {
		add_submenu_page(
			'edit.php?post_type=poll_station',
			__( 'Verification Queue', 'pollmonitor' ),
			__( 'Verification Queue', 'pollmonitor' ),
			'pollmonitor_validate', // Only for Validators/Admins
			'pollmonitor-verification',
			array( $this, 'render_verification_page' )
		);
	}

	public function render_verification_page() {
        if ( ! current_user_can( 'pollmonitor_validate' ) ) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>Verification Queue</h1>';

        // Fetch pending incidents
        $pending_incidents = get_posts( array(
            'post_type'      => 'incident_report',
            'post_status'    => 'pending',
            'posts_per_page' => 20,
        ) );

        if ( empty( $pending_incidents ) ) {
            echo '<p>No pending incidents to review!</p>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Title</th><th>Station ID</th><th>Evidence</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ( $pending_incidents as $incident ) {
            $station_id = get_post_meta( $incident->ID, 'pollmonitor_station_id', true );
            $thumbnail = get_the_post_thumbnail( $incident->ID, array(50, 50) );
            
            echo '<tr>';
            echo '<td><strong>' . esc_html( $incident->post_title ) . '</strong><br><small>' . esc_html( wp_trim_words( $incident->post_content, 10 ) ) . '</small></td>';
            echo '<td>' . esc_html( $station_id ) . '</td>';
            echo '<td>' . ( $thumbnail ? $thumbnail : 'None' ) . '</td>';
            
            // Action links
            $approve_url = wp_nonce_url( admin_url( 'admin-post.php?action=pollmonitor_approve_incident&id=' . $incident->ID ), 'approve_incident_' . $incident->ID );
            $reject_url = wp_nonce_url( admin_url( 'admin-post.php?action=pollmonitor_reject_incident&id=' . $incident->ID ), 'reject_incident_' . $incident->ID );
            
            echo '<td>';
            echo '<a href="' . esc_url( $approve_url ) . '" class="button button-primary" style="margin-right: 5px;">Approve</a>';
            echo '<a href="' . esc_url( $reject_url ) . '" class="button button-link-delete" style="color: #d63638;">Reject</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
	}

    public function handle_approval_action() {
        if ( ! isset( $_GET['id'] ) || ! current_user_can( 'pollmonitor_validate' ) ) {
            wp_die( 'Unauthorized' );
        }

        $incident_id = intval( $_GET['id'] );
        check_admin_referer( 'approve_incident_' . $incident_id );

        // Update post status to publish
        wp_update_post( array(
            'ID'          => $incident_id,
            'post_status' => 'publish'
        ) );

        // Log Action
        if ( class_exists('PollMonitor_DB') ) {
            PollMonitor_DB::log_action( 'incident_approved', get_current_user_id(), $incident_id, 'Incident reviewed and approved by Validator.' );
        }

        wp_redirect( admin_url( 'edit.php?post_type=poll_station&page=pollmonitor-verification&approved=1' ) );
        exit;
    }

    public function handle_rejection_action() {
        if ( ! isset( $_GET['id'] ) || ! current_user_can( 'pollmonitor_validate' ) ) {
            wp_die( 'Unauthorized' );
        }

        $incident_id = intval( $_GET['id'] );
        check_admin_referer( 'reject_incident_' . $incident_id );

        // Update post status (e.g., trash or a custom 'rejected' status)
        wp_update_post( array(
            'ID'          => $incident_id,
            'post_status' => 'trash'
        ) );

        // Log Action
        if ( class_exists('PollMonitor_DB') ) {
            PollMonitor_DB::log_action( 'incident_rejected', get_current_user_id(), $incident_id, 'Incident reviewed and rejected by Validator.' );
        }

        wp_redirect( admin_url( 'edit.php?post_type=poll_station&page=pollmonitor-verification&rejected=1' ) );
        exit;
    }
}
