<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'pollmonitor_validate' ) ) {
    echo '<p>' . esc_html__( 'You do not have permission to view this admin dashboard.', 'pollmonitor' ) . '</p>';
    return;
}

// Reuse observer-dashboard layout (which adapts to capabilities) for admin frontend.
require_once __DIR__ . '/observer-dashboard.php';
