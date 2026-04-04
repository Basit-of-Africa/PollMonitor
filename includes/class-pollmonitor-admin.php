<?php
/**
 * Handles the Admin Dashboard for PollMonitor
 */

class PollMonitor_Admin {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'show_user_profile', array( $this, 'render_observer_assignment_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'render_observer_assignment_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_observer_assignment_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_observer_assignment_fields' ) );
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

		add_submenu_page(
			'edit.php?post_type=poll_station',
			__( 'Self Test', 'pollmonitor' ),
			__( 'Self Test', 'pollmonitor' ),
			'manage_options',
			'pollmonitor-self-test',
			array( $this, 'render_self_test_page' )
		);

        add_submenu_page(
            'edit.php?post_type=poll_station',
            __( 'Import Observers', 'pollmonitor' ),
            __( 'Import Observers', 'pollmonitor' ),
            'manage_options',
            'pollmonitor-import-observers',
            array( $this, 'render_import_observers_page' )
        );
	}

	public function render_dashboard_page() {
		// Include the template file for the dashboard
		require_once POLLMONITOR_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	public function render_self_test_page() {
		// Include the template for self-tests
		require_once POLLMONITOR_PLUGIN_DIR . 'templates/admin-self-test.php';
	}

    public function render_import_observers_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $import_result = null;
        $import_error = '';

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['pollmonitor_import_observers'] ) ) {
            check_admin_referer( 'pollmonitor_import_observers' );

            if ( empty( $_FILES['pollmonitor_observer_csv']['tmp_name'] ) ) {
                $import_error = 'Please choose a CSV file exported from Excel.';
            } else {
                $file = $_FILES['pollmonitor_observer_csv'];
                if ( ! empty( $file['error'] ) ) {
                    $import_error = 'The file upload failed. Please try again.';
                } else {
                $file_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
                $extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

                if ( 'csv' !== $extension ) {
                    $import_error = 'Only CSV files are supported. Export the Excel sheet as CSV and try again.';
                } else {
                    $import_result = PollMonitor_Observer_Importer::import_csv( $file['tmp_name'] );
                    if ( is_wp_error( $import_result ) ) {
                        $import_error = $import_result->get_error_message();
                        $import_result = null;
                    }
                }
                }
            }
        }

        require POLLMONITOR_PLUGIN_DIR . 'templates/admin-import-observers.php';
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

    public function render_observer_assignment_fields( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $assigned_station_ids = get_user_meta( $user->ID, 'pollmonitor_assigned_station_ids', true );
        if ( ! is_array( $assigned_station_ids ) ) {
            $assigned_station_ids = array();
        }

        $assigned_station_ids = array_filter( array_map( 'intval', $assigned_station_ids ) );

        $assigned_station_posts = array();
        if ( ! empty( $assigned_station_ids ) ) {
            $assigned_station_posts = get_posts(
                array(
                    'post_type'      => 'poll_station',
                    'post__in'       => $assigned_station_ids,
                    'posts_per_page' => count( $assigned_station_ids ),
                    'orderby'        => 'post__in',
                )
            );
        }
        ?>
        <h2><?php esc_html_e( 'PollMonitor Assignments', 'pollmonitor' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="pollmonitor_observer_id"><?php esc_html_e( 'Observer ID', 'pollmonitor' ); ?></label></th>
                <td>
                    <input
                        type="text"
                        name="pollmonitor_observer_id"
                        id="pollmonitor_observer_id"
                        class="regular-text"
                        value="<?php echo esc_attr( get_user_meta( $user->ID, 'pollmonitor_observer_id', true ) ); ?>"
                    >
                    <p class="description">
                        <?php esc_html_e( 'Unique field identifier for the observer. Leave blank to auto-generate one during import.', 'pollmonitor' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="pollmonitor_assigned_station_ids"><?php esc_html_e( 'Assigned Polling Unit IDs', 'pollmonitor' ); ?></label></th>
                <td>
                    <textarea
                        name="pollmonitor_assigned_station_ids"
                        id="pollmonitor_assigned_station_ids"
                        rows="4"
                        class="large-text"
                        placeholder="123, 456, 789"
                    ><?php echo esc_textarea( implode( ', ', $assigned_station_ids ) ); ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'For observers, only these poll station IDs can be used for report submission. Leave empty for validators/admins who can access all stations.', 'pollmonitor' ); ?>
                    </p>
                    <?php if ( ! empty( $assigned_station_posts ) ) : ?>
                        <p class="description">
                            <?php
                            $station_names = wp_list_pluck( $assigned_station_posts, 'post_title' );
                            echo esc_html( sprintf( 'Assigned stations: %s', implode( ', ', $station_names ) ) );
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_observer_assignment_fields( $user_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! isset( $_POST['pollmonitor_assigned_station_ids'] ) ) {
            return;
        }

        if ( isset( $_POST['pollmonitor_observer_id'] ) ) {
            $observer_id = sanitize_text_field( wp_unslash( $_POST['pollmonitor_observer_id'] ) );
            if ( '' !== $observer_id ) {
                update_user_meta( $user_id, 'pollmonitor_observer_id', $observer_id );
            } else {
                delete_user_meta( $user_id, 'pollmonitor_observer_id' );
            }
        }

        $raw_station_ids = wp_unslash( $_POST['pollmonitor_assigned_station_ids'] );
        $pieces = preg_split( '/[\s,]+/', $raw_station_ids );
        $station_ids = array();

        foreach ( $pieces as $piece ) {
            $station_id = intval( $piece );
            if ( $station_id > 0 && 'poll_station' === get_post_type( $station_id ) ) {
                $station_ids[] = $station_id;
            }
        }

        $station_ids = array_values( array_unique( $station_ids ) );
        update_user_meta( $user_id, 'pollmonitor_assigned_station_ids', $station_ids );
    }
}
