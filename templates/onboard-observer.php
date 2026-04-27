<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'pollmonitor_validate' ) && ! current_user_can( 'manage_options' ) ) {
    echo '<p>' . esc_html__( 'You do not have permission to onboard observers.', 'pollmonitor' ) . '</p>';
    return;
}
?>
<div class="pm-onboard">
    <h2><?php echo esc_html__( 'Onboard New Observer', 'pollmonitor' ); ?></h2>
    <form id="pm-onboard-form">
        <label><?php echo esc_html__( 'First name', 'pollmonitor' ); ?></label>
        <input type="text" id="pm-onboard-first" required />

        <label><?php echo esc_html__( 'Last name', 'pollmonitor' ); ?></label>
        <input type="text" id="pm-onboard-last" />

        <label><?php echo esc_html__( 'Email', 'pollmonitor' ); ?></label>
        <input type="email" id="pm-onboard-email" required />

        <label><?php echo esc_html__( 'Phone (optional)', 'pollmonitor' ); ?></label>
        <input type="text" id="pm-onboard-phone" />

        <label><?php echo esc_html__( 'Assign Stations (IDs comma separated)', 'pollmonitor' ); ?></label>
        <input type="text" id="pm-onboard-stations" placeholder="e.g. 12,34,56" />

        <button type="submit"><?php echo esc_html__( 'Create Observer and Send Reset Email', 'pollmonitor' ); ?></button>
    </form>

    <div id="pm-onboard-status" role="status" aria-live="polite" style="margin-top:10px;color:green"></div>
</div>
