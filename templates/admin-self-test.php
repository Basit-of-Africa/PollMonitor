<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions' );
}

// Non-destructive self-test for PollMonitor frontend and API
$results = array();

// Shortcodes
global $shortcode_tags;
$results['shortcodes'] = array(
    'form' => isset( $shortcode_tags['pollmonitor_incident_form'] ),
    'list' => isset( $shortcode_tags['pollmonitor_incident_list'] ),
);

// Templates
$results['templates'] = array(
    'form' => file_exists( POLLMONITOR_PLUGIN_DIR . 'templates/form-incident.php' ),
    'list' => file_exists( POLLMONITOR_PLUGIN_DIR . 'templates/incidents-list.php' ),
    'admin_dashboard' => file_exists( POLLMONITOR_PLUGIN_DIR . 'templates/admin-dashboard.php' ),
);

// REST routes
$results['rest'] = array();
if ( function_exists( 'rest_get_server' ) ) {
    $routes = rest_get_server()->get_routes();
    $namespace = 'pollmonitor/v1';
    $results['rest']['has_namespace'] = isset( $routes["/$namespace"] ) || isset( $routes[$namespace] );
    // Check for specific endpoints roughly
    $results['rest']['stations'] = false;
    $results['rest']['incidents'] = false;
    $results['rest']['results'] = false;
    foreach ( $routes as $route => $handlers ) {
        if ( strpos( $route, "/$namespace/stations" ) !== false ) $results['rest']['stations'] = true;
        if ( strpos( $route, "/$namespace/incidents" ) !== false ) $results['rest']['incidents'] = true;
        if ( strpos( $route, "/$namespace/results" ) !== false ) $results['rest']['results'] = true;
    }
} else {
    $results['rest']['error'] = 'REST server not available';
}

// Uploads writable
$uploads = wp_upload_dir();
$results['uploads_writable'] = is_writable( $uploads['basedir'] );

// DB tables
global $wpdb;
$table = $wpdb->prefix . 'pollmonitor_results';
$results['db_table_exists'] = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );

// API class checks
$results['api_class'] = class_exists( 'PollMonitor_API' );
if ( $results['api_class'] ) {
    $api = new PollMonitor_API();
    $results['api_methods'] = array(
        'permissions_check_read' => method_exists( $api, 'permissions_check_read' ),
        'permissions_check_submit' => method_exists( $api, 'permissions_check_submit' ),
        'get_stations' => method_exists( $api, 'get_stations' ),
        'create_incident' => method_exists( $api, 'create_incident' ),
        'rate_limit_helper' => method_exists( $api, 'rate_limit_exceeded' ),
    );

    // Try calling get_stations() (non-destructive)
    try {
        $req = new WP_REST_Request( 'GET', '/pollmonitor/v1/stations' );
        $resp = $api->get_stations( $req );
        if ( $resp instanceof WP_REST_Response ) {
            $results['get_stations_status'] = 200;
            $results['get_stations_body_sample'] = array_slice( (array) $resp->get_data(), 0, 3 );
        } else if ( $resp instanceof WP_Error ) {
            $results['get_stations_status'] = 'error';
            $results['get_stations_error'] = $resp->get_error_message();
        } else {
            $results['get_stations_status'] = 'unknown';
        }
    } catch ( Exception $e ) {
        $results['get_stations_status'] = 'exception: ' . $e->getMessage();
    }
}

// Frontend class checks
$results['frontend_class'] = class_exists( 'PollMonitor_Frontend' );
if ( $results['frontend_class'] ) {
    $fe = new PollMonitor_Frontend();
    $results['render_form_ok'] = true;
    ob_start();
    try {
        echo $fe->render_incident_form();
        $buf = ob_get_clean();
        $results['render_form_output_len'] = strlen( $buf );
    } catch ( Exception $e ) {
        ob_end_clean();
        $results['render_form_ok'] = false;
        $results['render_form_error'] = $e->getMessage();
    }
    // incidents list render
    ob_start();
    try {
        echo $fe->render_incident_list();
        $buf2 = ob_get_clean();
        $results['render_list_output_len'] = strlen( $buf2 );
    } catch ( Exception $e ) {
        ob_end_clean();
        $results['render_list_ok'] = false;
        $results['render_list_error'] = $e->getMessage();
    }
}

// Permission checks
if ( $results['api_class'] ) {
    $results['perm_read_current'] = $api->permissions_check_read();
    $results['perm_submit_current'] = $api->permissions_check_submit();
}

// Present results
?>
<div class="wrap">
    <h1>PollMonitor Self Test</h1>
    <p>Non-destructive checks for shortcodes, templates, REST, uploads, DB, and helpers.</p>

    <h2>Shortcodes</h2>
    <ul>
        <li>Form shortcode: <?php echo $results['shortcodes']['form'] ? 'OK' : '<strong style="color:#a00">MISSING</strong>'; ?></li>
        <li>List shortcode: <?php echo $results['shortcodes']['list'] ? 'OK' : '<strong style="color:#a00">MISSING</strong>'; ?></li>
    </ul>

    <h2>Templates</h2>
    <ul>
        <li>Form template: <?php echo $results['templates']['form'] ? 'OK' : '<strong style="color:#a00">MISSING</strong>'; ?></li>
        <li>List template: <?php echo $results['templates']['list'] ? 'OK' : '<strong style="color:#a00">MISSING</strong>'; ?></li>
        <li>Admin dashboard template: <?php echo $results['templates']['admin_dashboard'] ? 'OK' : '<strong style="color:#a00">MISSING</strong>'; ?></li>
    </ul>

    <h2>REST</h2>
    <ul>
        <li>Namespace present: <?php echo ! empty( $results['rest']['has_namespace'] ) ? 'OK' : '<strong style="color:#a00">Missing or not registered</strong>'; ?></li>
        <li>/stations: <?php echo ! empty( $results['rest']['stations'] ) ? 'OK' : 'Missing'; ?></li>
        <li>/incidents: <?php echo ! empty( $results['rest']['incidents'] ) ? 'OK' : 'Missing'; ?></li>
        <li>/results: <?php echo ! empty( $results['rest']['results'] ) ? 'OK' : 'Missing'; ?></li>
        <?php if ( isset( $results['rest']['error'] ) ) : ?>
            <li>Error: <?php echo esc_html( $results['rest']['error'] ); ?></li>
        <?php endif; ?>
    </ul>

    <h2>Uploads & Files</h2>
    <ul>
        <li>Uploads directory writable: <?php echo $results['uploads_writable'] ? 'OK' : '<strong style="color:#a00">Not writable</strong>'; ?></li>
    </ul>

    <h2>Database</h2>
    <ul>
        <li>Results table exists: <?php echo $results['db_table_exists'] ? 'OK' : '<strong style="color:#a00">Missing</strong>'; ?></li>
    </ul>

    <h2>API Class</h2>
    <ul>
        <li>API class present: <?php echo $results['api_class'] ? 'OK' : '<strong style="color:#a00">Missing</strong>'; ?></li>
        <?php if ( ! empty( $results['api_methods'] ) ) : ?>
            <li>Permissions read: <?php echo $results['api_methods']['permissions_check_read'] ? 'OK' : 'Missing'; ?></li>
            <li>Permissions submit: <?php echo $results['api_methods']['permissions_check_submit'] ? 'OK' : 'Missing'; ?></li>
            <li>get_stations method: <?php echo $results['api_methods']['get_stations'] ? 'OK' : 'Missing'; ?></li>
            <li>create_incident method: <?php echo $results['api_methods']['create_incident'] ? 'OK' : 'Missing'; ?></li>
            <li>rate limit helper: <?php echo $results['api_methods']['rate_limit_helper'] ? 'OK' : 'Missing'; ?></li>
        <?php endif; ?>
    </ul>

    <h2>API Call Samples</h2>
    <ul>
        <li>get_stations status: <?php echo isset( $results['get_stations_status'] ) ? esc_html( $results['get_stations_status'] ) : 'n/a'; ?></li>
        <?php if ( ! empty( $results['get_stations_body_sample'] ) ) : ?>
            <li>Sample stations: <pre><?php echo esc_html( wp_json_encode( $results['get_stations_body_sample'] ) ); ?></pre></li>
        <?php endif; ?>
    </ul>

    <h2>Frontend Render</h2>
    <ul>
        <li>Render form output bytes: <?php echo isset( $results['render_form_output_len'] ) ? intval( $results['render_form_output_len'] ) : 'n/a'; ?></li>
        <li>Render list output bytes: <?php echo isset( $results['render_list_output_len'] ) ? intval( $results['render_list_output_len'] ) : 'n/a'; ?></li>
    </ul>

    <h2>Permissions (current user)</h2>
    <ul>
        <li>Can read API endpoints: <?php echo isset( $results['perm_read_current'] ) ? ( $results['perm_read_current'] ? 'Yes' : 'No' ) : 'n/a'; ?></li>
        <li>Can submit results/incidents: <?php echo isset( $results['perm_submit_current'] ) ? ( $results['perm_submit_current'] ? 'Yes' : 'No' ) : 'n/a'; ?></li>
    </ul>

</div>
