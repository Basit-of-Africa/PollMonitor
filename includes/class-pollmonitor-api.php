<?php
/**
 * Handles REST API Endpoints for PollMonitor
 */

class PollMonitor_API {

    public static function get_user_assigned_station_ids( $user_id ) {
        $assigned_station_ids = get_user_meta( $user_id, 'pollmonitor_assigned_station_ids', true );

        if ( ! is_array( $assigned_station_ids ) ) {
            return array();
        }

        $assigned_station_ids = array_values( array_unique( array_filter( array_map( 'intval', $assigned_station_ids ) ) ) );

        if ( empty( $assigned_station_ids ) ) {
            return array();
        }

        return array_values(
            array_filter(
                $assigned_station_ids,
                function ( $station_id ) {
                    return 'poll_station' === get_post_type( $station_id );
                }
            )
        );
    }

    public static function current_user_requires_station_assignment( $user_id = 0 ) {
        $user_id = $user_id ? intval( $user_id ) : get_current_user_id();

        if ( ! $user_id ) {
            return false;
        }

        if ( user_can( $user_id, 'pollmonitor_manage_all' ) || user_can( $user_id, 'pollmonitor_validate' ) ) {
            return false;
        }

        return user_can( $user_id, 'pollmonitor_submit' );
    }

    public static function get_station_access_context( $user_id = 0 ) {
        $user_id = $user_id ? intval( $user_id ) : get_current_user_id();
        $assigned_station_ids = self::get_user_assigned_station_ids( $user_id );
        $requires_assignment = self::current_user_requires_station_assignment( $user_id );

        return array(
            'assigned_station_ids'   => $assigned_station_ids,
            'has_assignments'        => ! empty( $assigned_station_ids ),
            'requires_assignment'    => $requires_assignment,
            'can_access_all_stations'=> ! $requires_assignment,
        );
    }

    protected function current_user_can_access_station( $station_id, $user_id = 0 ) {
        $user_id = $user_id ? intval( $user_id ) : get_current_user_id();
        $station_id = intval( $station_id );

        if ( $station_id <= 0 || 'poll_station' !== get_post_type( $station_id ) ) {
            return false;
        }

        if ( ! self::current_user_requires_station_assignment( $user_id ) ) {
            return true;
        }

        $assigned_station_ids = self::get_user_assigned_station_ids( $user_id );
        return in_array( $station_id, $assigned_station_ids, true );
    }

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

        register_rest_route( $namespace, '/incidents/recent', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_recent_incidents' ),
            'permission_callback' => array( $this, 'permissions_check_read' ),
            'args' => array(
                'per_page' => array( 'required' => false, 'default' => 10 ),
            ),
        ) );
        
        register_rest_route( $namespace, '/results', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_result' ),
			'permission_callback' => array( $this, 'permissions_check_submit' ), 
		) );

        // Locations and stations by location
        register_rest_route( $namespace, '/locations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_locations' ),
            'permission_callback' => array( $this, 'permissions_check_read' ),
            'args' => array(
                'type' => array( 'required' => false, 'default' => 'state' ), // state|lga|ward
                'parent' => array( 'required' => false ),
            ),
        ) );

        register_rest_route( $namespace, '/stations-by-location', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_stations_by_location' ),
            'permission_callback' => array( $this, 'permissions_check_read' ),
            'args' => array(
                'taxonomy' => array( 'required' => true ), // state|lga|ward
                'term_id'  => array( 'required' => true ),
            ),
        ) );

        // Create observer (onboarding) - restricted to validators/admins
        register_rest_route( $namespace, '/observers', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_observer' ),
                'permission_callback' => array( $this, 'permissions_check_onboard' ),
            ),
        ) );
	}

    public function permissions_check_read() {
        return current_user_can( 'read' ); // Any logged-in Observer/Validator
    }

    public function permissions_check_submit() {
        return current_user_can( 'pollmonitor_submit' ); // Must be Observer or Validator
    }

	public function get_stations( WP_REST_Request $request ) {
        $context = self::get_station_access_context();
        $assigned_only = rest_sanitize_boolean( $request->get_param( 'assigned_only' ) );
        $taxonomy = sanitize_key( $request->get_param( 'taxonomy' ) );
        $term_id = intval( $request->get_param( 'term_id' ) );
        $search = sanitize_text_field( $request->get_param( 'search' ) );
        $per_page = intval( $request->get_param( 'per_page' ) );

        if ( $per_page <= 0 ) {
            $per_page = ! empty( $context['has_assignments'] ) ? count( $context['assigned_station_ids'] ) : 50;
        }

        $per_page = min( max( $per_page, 1 ), 200 );

		$args = array(
			'post_type'      => 'poll_station',
			'posts_per_page' => $per_page,
		);

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        if ( $assigned_only || ! empty( $context['requires_assignment'] ) ) {
            if ( empty( $context['assigned_station_ids'] ) ) {
                return new WP_REST_Response( array(), 200 );
            }

            $args['post__in'] = $context['assigned_station_ids'];
            $args['orderby'] = 'post__in';
            $args['posts_per_page'] = count( $context['assigned_station_ids'] );
        }

        if ( $taxonomy && $term_id > 0 ) {
            if ( ! in_array( $taxonomy, array( 'state', 'lga', 'ward' ), true ) ) {
                return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy filter', array( 'status' => 400 ) );
            }

            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            );
        }

		$stations = get_posts( $args );

        $data = array();
        foreach ( $stations as $station ) {
            $lat = get_post_meta( $station->ID, 'pollmonitor_lat', true );
            $lng = get_post_meta( $station->ID, 'pollmonitor_lng', true );

            $data[] = array(
                'id'    => $station->ID,
                'title' => $station->post_title,
                'lat'   => '' !== $lat && null !== $lat ? (float) $lat : null,
                'lng'   => '' !== $lng && null !== $lng ? (float) $lng : null,
            );
        }

		return new WP_REST_Response( $data, 200 );
	}

	public function create_incident( WP_REST_Request $request ) {
		// Handle multipart/form-data parameters
		$params = $request->get_params();

        // Verify REST nonce for logged-in requests
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'bad_nonce', 'Invalid or missing REST nonce', array( 'status' => 403 ) );
        }

        // Rate limiting: max 10 incident submissions per hour per user/ip
        if ( $this->rate_limit_exceeded( $request, 'incident_create', 10, HOUR_IN_SECONDS ) ) {
            return new WP_Error( 'rate_limited', 'Submission rate limit exceeded. Try again later.', array( 'status' => 429 ) );
        }

        if ( empty( $params['title'] ) || empty( $params['content'] ) || empty( $params['station_id'] ) ) {
            return new WP_Error( 'missing_data', 'Missing required fields (title, content, station_id)', array( 'status' => 400 ) );
        }

        $station_id = intval( $params['station_id'] );
        $station = get_post( $station_id );
        if ( ! $station || 'poll_station' !== $station->post_type ) {
            return new WP_Error( 'invalid_station', 'Station ID is invalid', array( 'status' => 400 ) );
        }

        if ( ! $this->current_user_can_access_station( $station_id ) ) {
            return new WP_Error( 'forbidden_station', 'You are not assigned to submit reports for this polling unit.', array( 'status' => 403 ) );
        }

        $evidence_file = null;
        if ( ! empty( $_FILES['evidence'] ) ) {
            $max_size = 2 * 1024 * 1024; // 2MB
            $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/jpg' );

            $evidence_file = $_FILES['evidence'];

            if ( isset( $evidence_file['size'] ) && $evidence_file['size'] > $max_size ) {
                return new WP_Error( 'file_too_large', 'Evidence file exceeds maximum allowed size of 2MB', array( 'status' => 400 ) );
            }

            $check = wp_check_filetype_and_ext( $evidence_file['tmp_name'], $evidence_file['name'] );
            $mime = isset( $check['type'] ) ? $check['type'] : '';

            if ( ! in_array( $mime, $allowed_mimes, true ) ) {
                return new WP_Error( 'invalid_file_type', 'Evidence file must be a PNG or JPEG image', array( 'status' => 400 ) );
            }
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
        update_post_meta( $post_id, 'pollmonitor_station_id', $station_id );

        // Handle File Upload (Evidence) with server-side validation
        if ( ! empty( $evidence_file ) ) {
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

            if ( is_wp_error( $attachment_id ) ) {
                wp_delete_post( $post_id, true );
                return new WP_Error( 'upload_failed', 'Evidence upload failed. The report was not saved.', array( 'status' => 500 ) );
            }

            set_post_thumbnail( $post_id, $attachment_id );
        }

        // Log this action
        if ( class_exists('PollMonitor_DB') ) {
            PollMonitor_DB::log_action( 'incident_created', get_current_user_id(), $post_id, 'Incident created with evidence via API' );
            PollMonitor_DB::clear_dashboard_cache();
        }

		return new WP_REST_Response( array( 'message' => 'Incident created successfully', 'id' => $post_id ), 201 );
	}

    public function create_result( WP_REST_Request $request ) {
        global $wpdb;
        $params = $request->get_json_params();

        // Verify REST nonce for logged-in requests
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'bad_nonce', 'Invalid or missing REST nonce', array( 'status' => 403 ) );
        }

        // Permission check
        if ( ! current_user_can( 'pollmonitor_submit' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to submit results', array( 'status' => 403 ) );
        }

        // Rate limiting: max 20 result submissions per hour per user/ip
        if ( $this->rate_limit_exceeded( $request, 'result_create', 20, HOUR_IN_SECONDS ) ) {
            return new WP_Error( 'rate_limited', 'Submission rate limit exceeded. Try again later.', array( 'status' => 429 ) );
        }

        // Basic validation
        if ( empty( $params['station_id'] ) ) {
            return new WP_Error( 'missing_data', 'Missing station ID', array( 'status' => 400 ) );
        }

        $station_id = intval( $params['station_id'] );
        $station = get_post( $station_id );
        if ( ! $station || 'poll_station' !== $station->post_type ) {
            return new WP_Error( 'invalid_station', 'Station ID is invalid', array( 'status' => 400 ) );
        }

        if ( ! $this->current_user_can_access_station( $station_id ) ) {
            return new WP_Error( 'forbidden_station', 'You are not assigned to submit results for this polling unit.', array( 'status' => 403 ) );
        }

        // Coerce numeric values and validate ranges
        $party_a = isset( $params['party_a'] ) ? intval( $params['party_a'] ) : 0;
        $party_b = isset( $params['party_b'] ) ? intval( $params['party_b'] ) : 0;
        $party_c = isset( $params['party_c'] ) ? intval( $params['party_c'] ) : 0;
        $party_d = isset( $params['party_d'] ) ? intval( $params['party_d'] ) : 0;
        $total_valid = isset( $params['total_valid'] ) ? intval( $params['total_valid'] ) : 0;
        $total_invalid = isset( $params['total_invalid'] ) ? intval( $params['total_invalid'] ) : 0;

        $values = array( $party_a, $party_b, $party_c, $party_d, $total_valid, $total_invalid );
        foreach ( $values as $v ) {
            if ( $v < 0 ) {
                return new WP_Error( 'invalid_value', 'Vote counts must be non-negative integers', array( 'status' => 400 ) );
            }
        }

        $sum_parties = $party_a + $party_b + $party_c + $party_d;
        if ( $total_valid < $sum_parties ) {
            return new WP_Error( 'invalid_totals', 'Total valid votes cannot be less than sum of party votes', array( 'status' => 400 ) );
        }

        $table = $wpdb->prefix . 'pollmonitor_results';

        $inserted = $wpdb->insert(
            $table,
            array(
                'station_id'    => $station_id,
                'party_a'       => $party_a,
                'party_b'       => $party_b,
                'party_c'       => $party_c,
                'party_d'       => $party_d,
                'total_valid'   => $total_valid,
                'total_invalid' => $total_invalid,
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

    /**
     * Simple rate limit tracker using options (per user or IP).
     * Returns true if limit exceeded, false otherwise.
     */
    protected function rate_limit_exceeded( WP_REST_Request $request, $action = 'generic', $limit = 10, $period = HOUR_IN_SECONDS ) {
        $user_id = get_current_user_id();

        if ( $user_id && $user_id > 0 ) {
            $ident = 'user_' . intval( $user_id );
        } else {
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
            $ident = 'ip_' . preg_replace( '/[^0-9a-fA-F:\.]/', '', $ip );
        }

        $name = 'pollmonitor_rl_' . md5( $action . '_' . $ident );

        $record = get_option( $name );
        $now = time();

        if ( false === $record || ! is_array( $record ) ) {
            $record = array( 'count' => 1, 'expires' => $now + $period );
            add_option( $name, $record, '', 'no' );
            return false;
        }

        // Reset window if expired
        if ( isset( $record['expires'] ) && $now > intval( $record['expires'] ) ) {
            $record = array( 'count' => 1, 'expires' => $now + $period );
            update_option( $name, $record );
            return false;
        }

        // Increment and check
        $record['count'] = isset( $record['count'] ) ? intval( $record['count'] ) + 1 : 1;
        update_option( $name, $record );

        if ( $record['count'] > intval( $limit ) ) {
            if ( class_exists( 'PollMonitor_DB' ) ) {
                PollMonitor_DB::log_action( 'rate_limit_exceeded', get_current_user_id(), 0, sprintf( 'Action:%s ident:%s count:%d limit:%d', $action, $ident, $record['count'], $limit ) );
            }
            return true;
        }

        return false;
    }


    /**
     * Get hierarchical locations (states, lgas, wards)
     * Params: type (state|lga|ward), parent (term id)
     */
    public function get_locations( WP_REST_Request $request ) {
        $type = $request->get_param( 'type' );
        $parent = $request->get_param( 'parent' );

        $allowed = array( 'state', 'lga', 'ward' );
        if ( ! in_array( $type, $allowed, true ) ) {
            return new WP_Error( 'invalid_type', 'Invalid location type', array( 'status' => 400 ) );
        }

        $args = array( 'hide_empty' => false );
        if ( ! empty( $parent ) ) {
            $args['parent'] = intval( $parent );
        }

        $terms = get_terms( array_merge( array( 'taxonomy' => $type ), $args ) );

        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $data = array();
        foreach ( $terms as $t ) {
            $data[] = array(
                'id' => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
                'parent' => $t->parent,
            );
        }

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Get poll stations by taxonomy term (state|lga|ward)
     * Params: taxonomy, term_id
     */
    public function get_stations_by_location( WP_REST_Request $request ) {
        $taxonomy = $request->get_param( 'taxonomy' );
        $term_id = intval( $request->get_param( 'term_id' ) );
        $context = self::get_station_access_context();

        $allowed = array( 'state', 'lga', 'ward' );
        if ( ! in_array( $taxonomy, $allowed, true ) ) {
            return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy', array( 'status' => 400 ) );
        }

        // Query stations with term
        $args = array(
            'post_type' => 'poll_station',
            'posts_per_page' => 100,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_id,
                ),
            ),
        );

        if ( ! empty( $context['requires_assignment'] ) ) {
            if ( empty( $context['assigned_station_ids'] ) ) {
                return new WP_REST_Response( array(), 200 );
            }

            $args['post__in'] = $context['assigned_station_ids'];
        }

        $posts = get_posts( $args );

        $data = array();
        foreach ( $posts as $p ) {
            $lat = get_post_meta( $p->ID, 'pollmonitor_lat', true );
            $lng = get_post_meta( $p->ID, 'pollmonitor_lng', true );
            $data[] = array(
                'id' => $p->ID,
                'title' => $p->post_title,
                'lat' => $lat ? (float) $lat : null,
                'lng' => $lng ? (float) $lng : null,
            );
        }

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Return recent incident reports (for dashboard).
     */
    public function get_recent_incidents( WP_REST_Request $request ) {
        $per_page = intval( $request->get_param( 'per_page' ) );
        $per_page = $per_page > 0 ? min( $per_page, 100 ) : 10;

        $context = self::get_station_access_context();

        $args = array(
            'post_type'      => 'incident_report',
            'posts_per_page' => $per_page,
            'post_status'    => array( 'publish', 'pending' ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( ! empty( $context['requires_assignment'] ) ) {
            // If user must be assigned, only return incidents for assigned stations
            $assigned = $context['assigned_station_ids'];
            if ( empty( $assigned ) ) {
                return new WP_REST_Response( array(), 200 );
            }

            // Join via meta query
            $args['meta_query'] = array(
                array(
                    'key'     => 'pollmonitor_station_id',
                    'value'   => $assigned,
                    'compare' => 'IN',
                ),
            );
        }

        $posts = get_posts( $args );
        $data = array();
        foreach ( $posts as $p ) {
            $station_id = intval( get_post_meta( $p->ID, 'pollmonitor_station_id', true ) );
            $data[] = array(
                'id'        => $p->ID,
                'title'     => get_the_title( $p ),
                'content'   => wp_strip_all_tags( $p->post_content ),
                'status'    => $p->post_status,
                'date'      => get_the_date( 'c', $p ),
                'author'    => get_the_author_meta( 'display_name', $p->post_author ),
                'station_id'=> $station_id,
            );
        }

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Permission callback for onboarding observers.
     */
    public function permissions_check_onboard() {
        // Only validators or admins can onboard observers
        if ( current_user_can( 'pollmonitor_validate' ) || current_user_can( 'pollmonitor_manage_all' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error( 'forbidden', 'You do not have permission to onboard observers', array( 'status' => 403 ) );
    }

    /**
     * Create an observer user and trigger password reset email.
     * Expects: first_name, last_name, email, username (optional), assigned_station_ids (array), observer_id (optional)
     */
    public function create_observer( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        $email = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
        $first = isset( $params['first_name'] ) ? sanitize_text_field( $params['first_name'] ) : '';
        $last  = isset( $params['last_name'] ) ? sanitize_text_field( $params['last_name'] ) : '';
        $username = isset( $params['username'] ) ? sanitize_user( $params['username'], true ) : '';
        $assigned = isset( $params['assigned_station_ids'] ) && is_array( $params['assigned_station_ids'] ) ? array_map( 'intval', $params['assigned_station_ids'] ) : array();
        $observer_id = isset( $params['observer_id'] ) ? sanitize_text_field( $params['observer_id'] ) : '';

        if ( empty( $email ) || ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'A valid email is required', array( 'status' => 400 ) );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', 'A user with that email already exists', array( 'status' => 409 ) );
        }

        if ( empty( $username ) ) {
            $base = sanitize_user( current( explode( '@', $email ) ), true );
            if ( empty( $base ) ) {
                $base = 'observer';
            }
            // Ensure unique username
            $candidate = $base; $suffix = 1;
            while ( username_exists( $candidate ) ) { $candidate = $base . $suffix; $suffix++; }
            $username = $candidate;
        } else {
            if ( username_exists( $username ) ) {
                return new WP_Error( 'username_exists', 'Username already taken', array( 'status' => 409 ) );
            }
        }

        // Generate a random password but we will force user to reset
        $password = wp_generate_password( 16, false, false );

        $display = trim( $first . ' ' . $last );
        if ( empty( $display ) ) { $display = $username; }

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'first_name'   => $first,
            'last_name'    => $last,
            'display_name' => $display,
            'role'         => 'pollmonitor_observer',
        ) );

        if ( is_wp_error( $user_id ) ) {
            return new WP_Error( 'create_failed', $user_id->get_error_message(), array( 'status' => 500 ) );
        }

        if ( ! empty( $observer_id ) ) {
            update_user_meta( $user_id, 'pollmonitor_observer_id', sanitize_text_field( $observer_id ) );
        }

        if ( ! empty( $assigned ) ) {
            update_user_meta( $user_id, 'pollmonitor_assigned_station_ids', $assigned );
        }

        if ( ! empty( $params['phone'] ) ) {
            update_user_meta( $user_id, 'pollmonitor_phone', sanitize_text_field( $params['phone'] ) );
        }

        // Trigger password reset email so observer chooses their own password
        if ( function_exists( 'retrieve_password' ) ) {
            $user = get_user_by( 'id', $user_id );
            if ( $user ) {
                retrieve_password( $user->user_login );
            }
        }

        if ( class_exists( 'PollMonitor_DB' ) ) {
            PollMonitor_DB::log_action( 'observer_onboarded', get_current_user_id(), $user_id, array( 'email' => $email ) );
        }

        return new WP_REST_Response( array( 'id' => $user_id, 'username' => $username ), 201 );
    }
}
