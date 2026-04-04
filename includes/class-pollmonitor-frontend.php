<?php
/**
 * Handles Frontend Forms and Display for PollMonitor
 */

class PollMonitor_Frontend {

	public function init() {
        add_shortcode( 'pollmonitor_incident_form', array( $this, 'render_incident_form' ) );
        add_shortcode( 'pollmonitor_incident_list', array( $this, 'render_incident_list' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

    public function render_incident_list() {
        ob_start();
        require POLLMONITOR_PLUGIN_DIR . 'templates/incidents-list.php';
        return ob_get_clean();
    }

	public function render_incident_form() {
		// Output buffering to capture the template inclusion
		ob_start();
		require POLLMONITOR_PLUGIN_DIR . 'templates/form-incident.php';
		return ob_get_clean();
	}

    public function enqueue_frontend_assets() {
        global $post;

        // Only load script if our shortcode is on the page
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'pollmonitor_incident_form' ) || has_shortcode( $post->post_content, 'pollmonitor_incident_list' ) ) ) {
            wp_enqueue_style( 'pollmonitor-frontend-css', POLLMONITOR_PLUGIN_URL . 'assets/css/frontend.css', array(), POLLMONITOR_VERSION );

            // Leaflet for interactive maps on the frontend
            wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
            wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

            // Tailwind via CDN for modern UI (fast iteration)
            wp_enqueue_script( 'tailwind-cdn', 'https://cdn.tailwindcss.com', array(), null, false );
            // Small overrides to complement Tailwind utilities
            wp_enqueue_style( 'pollmonitor-tailwind-overrides', POLLMONITOR_PLUGIN_URL . 'assets/css/tailwind-overrides.css', array(), POLLMONITOR_VERSION );

            // Enqueue our frontend JS and depend on leaflet
            wp_enqueue_script( 'pollmonitor-frontend-js', POLLMONITOR_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery', 'leaflet-js' ), POLLMONITOR_VERSION, true );

            // Incidents list assets (only if list shortcode present)
            if ( has_shortcode( $post->post_content, 'pollmonitor_incident_list' ) ) {
                wp_enqueue_style( 'pollmonitor-list-css', POLLMONITOR_PLUGIN_URL . 'assets/css/list.css', array(), POLLMONITOR_VERSION );
                wp_enqueue_script( 'pollmonitor-list-js', POLLMONITOR_PLUGIN_URL . 'assets/js/list.js', array( 'jquery' ), POLLMONITOR_VERSION, true );
            }
            
            // Pass API URL to frontend script
            $station_access = PollMonitor_API::get_station_access_context( get_current_user_id() );
            wp_localize_script( 'pollmonitor-frontend-js', 'pollmonitorApiSettings', array(
                'root'          => esc_url_raw( rest_url() ),
                'nonce'         => wp_create_nonce( 'wp_rest' ),
                'stationAccess' => $station_access,
            ) );
        }
    }
}
