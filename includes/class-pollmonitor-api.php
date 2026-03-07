<?php
/**
 * Handles REST API Endpoints for PollMonitor
 */

class PollMonitor_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$namespace = 'pollmonitor/v1';

		register_rest_route( $namespace, '/stations', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stations' ),
			'permission_callback' => array( $this, 'permissions_check_read' ), 
		) );

		register_rest_route( $namespace, '/incidents', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_incident' ),
			'permission_callback' => array( $this, 'permissions_check_submit' ), 
		) );
        
        register_rest_route( $namespace, '/results', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_result' ),
			'permission_callback' => array( $this, 'permissions_check_submit' ), 
		) );
	}

    public function permissions_check_read() {
        return current_user_can( 'read' ); // Any logged-in Observer/Validator
    }

    public function permissions_check_submit() {
        return current_user_can( 'pollmonitor_submit' ); // Must be Observer or Validator
    }

	public function get_stations( WP_REST_Request $request ) {
		// Placeholder for getting stations based on query params (state, lga)
		$args = array(
			'post_type'      => 'poll_station',
			'posts_per_page' => 50,
		);

		$stations = get_posts( $args );

		if ( empty( $stations ) ) {
			return new WP_Error( 'no_stations', 'No polling stations found', array( 'status' => 404 ) );
		}

        $data = array();
        foreach ( $stations as $station ) {
            $lat = get_post_meta( $station->ID, 'pollmonitor_lat', true );
            $lng = get_post_meta( $station->ID, 'pollmonitor_lng', true );
            
            // Fallback generic coordinates around Abuja, Nigeria if not set
            if ( empty($lat) || empty($lng) ) {
                $lat = 9.0765 + (rand(-100, 100) / 1000);
                $lng = 7.3986 + (rand(-100, 100) / 1000);
            }

            $data[] = array(
                'id'    => $station->ID,
                'title' => $station->post_title,
                'lat'   => (float) $lat,
                'lng'   => (float) $lng
            );
        }

		return new WP_REST_Response( $data, 200 );
	}

	public function create_incident( WP_REST_Request $request ) {
		// Handle multipart/form-data parameters
		$params = $request->get_params();

        if ( empty( $params['title'] ) || empty( $params['content'] ) || empty( $params['station_id'] ) ) {
            return new WP_Error( 'missing_data', 'Missing required fields (title, content, station_id)', array( 'status' => 400 ) );
        }

		$post_data = array(
			'post_title'    => sanitize_text_field( $params['title'] ),
			'post_content'  => sanitize_textarea_field( $params['content'] ),
			'post_status'   => 'pending', // Pending verification from validators
			'post_type'     => 'incident_report',
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'insert_failed', 'Failed to insert incident report', array( 'status' => 500 ) );
		}

        // Save Station ID as post meta
        update_post_meta( $post_id, 'pollmonitor_station_id', intval( $params['station_id'] ) );

        // Handle File Upload (Evidence) with server-side validation
        if ( ! empty( $_FILES['evidence'] ) ) {
            // Basic server-side validation: file size and mime-type
            $max_size = 2 * 1024 * 1024; // 2MB
            $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/jpg' );

            $file = $_FILES['evidence'];

            if ( isset( $file['size'] ) && $file['size'] > $max_size ) {
                return new WP_Error( 'file_too_large', 'Evidence file exceeds maximum allowed size of 2MB', array( 'status' => 400 ) );
            }

            // Use WordPress filetype checker for mime type
            $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
            $mime = isset( $check['type'] ) ? $check['type'] : '';

            if ( ! in_array( $mime, $allowed_mimes, true ) ) {
                return new WP_Error( 'invalid_file_type', 'Evidence file must be a PNG or JPEG image', array( 'status' => 400 ) );
            }

            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            
            // Shared Hosting Optimization: Disable all thumbnail generation for this specific upload
            // to save crucial disk space and inodes, as we only need the original full-size evidence photo.
            add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 10, 0 );

            // Upload and attach to the incident post
            $attachment_id = media_handle_upload( 'evidence', $post_id );
            
            // Re-enable thumbnail generation for normal WordPress usage
            remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 10 );

            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }

        // Log this action
        if ( class_exists('PollMonitor_DB') ) {
            PollMonitor_DB::log_action( 'incident_created', get_current_user_id(), $post_id, 'Incident created with evidence via API' );
        }

		return new WP_REST_Response( array( 'message' => 'Incident created successfully', 'id' => $post_id ), 201 );
	}

    public function create_result( WP_REST_Request $request ) {
        global $wpdb;
        $params = $request->get_json_params();

        // Basic validation
        if ( empty( $params['station_id'] ) ) {
            return new WP_Error( 'missing_data', 'Missing station ID', array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'pollmonitor_results';
        
        $inserted = $wpdb->insert(
            $table,
            array(
                'station_id'    => intval( $params['station_id'] ),
                'party_a'       => isset( $params['party_a'] ) ? intval( $params['party_a'] ) : 0,
                'party_b'       => isset( $params['party_b'] ) ? intval( $params['party_b'] ) : 0,
                'party_c'       => isset( $params['party_c'] ) ? intval( $params['party_c'] ) : 0,
                'party_d'       => isset( $params['party_d'] ) ? intval( $params['party_d'] ) : 0,
                'total_valid'   => isset( $params['total_valid'] ) ? intval( $params['total_valid'] ) : 0,
                'total_invalid' => isset( $params['total_invalid'] ) ? intval( $params['total_invalid'] ) : 0,
                'created_by'    => get_current_user_id(),
                'status'        => 'pending' // Requires Validator approval
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
        );

        if ( ! $inserted ) {
            return new WP_Error( 'insert_failed', 'Failed to insert results', array( 'status' => 500 ) );
        }

        $result_id = $wpdb->insert_id;

        // Log action
        if ( class_exists('PollMonitor_DB') ) {
            PollMonitor_DB::log_action( 'results_submitted', get_current_user_id(), $result_id, 'Results submitted via API' );
        }

        return new WP_REST_Response( array( 'message' => 'Results submitted successfully', 'id' => $result_id ), 201 );
    }
}
