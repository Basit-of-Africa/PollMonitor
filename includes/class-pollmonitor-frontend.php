<?php
/**
 * Handles Frontend Forms and Display for PollMonitor
 */

class PollMonitor_Frontend {

	public function init() {
		add_shortcode( 'pollmonitor_incident_form', array( $this, 'render_incident_form' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
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
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'pollmonitor_incident_form' ) ) {
            wp_enqueue_style( 'pollmonitor-frontend-css', POLLMONITOR_PLUGIN_URL . 'assets/css/frontend.css', array(), POLLMONITOR_VERSION );
            wp_enqueue_script( 'pollmonitor-frontend-js', POLLMONITOR_PLUGIN_URL . 'assets/js/frontend.js', array(), POLLMONITOR_VERSION, true );
            
            // Pass API URL to frontend script
            wp_localize_script( 'pollmonitor-frontend-js', 'pollmonitorApiSettings', array(
                'root'  => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' )
            ) );
        }
    }
}
