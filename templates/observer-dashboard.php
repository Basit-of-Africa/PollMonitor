<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . esc_url( $_SERVER['REQUEST_URI'] );

if ( ! is_user_logged_in() ) :
    // Show WP login form and lost password link
    $args = array(
        'redirect' => $current_url,
        'label_username' => __( 'Email or Username', 'pollmonitor' ),
    );
    echo '<div class="pollmonitor-observer-login">';
    wp_login_form( $args );
    echo '<p>' . sprintf( '<a href="%s">%s</a>', esc_url( wp_lostpassword_url( $current_url ) ), esc_html__( 'Lost your password?', 'pollmonitor' ) ) . '</p>';
    echo '</div>';
    return;
endif;

$user = wp_get_current_user();
$roles = (array) $user->roles;

if ( ! in_array( 'pollmonitor_observer', $roles, true ) && ! user_can( $user->ID, 'pollmonitor_validate' ) && ! user_can( $user->ID, 'manage_options' ) ) {
    echo '<div class="pollmonitor-observer-not-authorized">';
    echo '<p>' . esc_html__( 'Your account does not have observer access.', 'pollmonitor' ) . '</p>';
    echo '</div>';
    return;
}

$observer_id = get_user_meta( $user->ID, 'pollmonitor_observer_id', true );
$assigned_station_ids = get_user_meta( $user->ID, 'pollmonitor_assigned_station_ids', true );
if ( ! is_array( $assigned_station_ids ) ) {
    $assigned_station_ids = array();
}

?>
<div class="pollmonitor-observer-dashboard">
    <h2><?php echo esc_html__( 'Observer Account', 'pollmonitor' ); ?></h2>

    <ul class="pm-observer-profile">
        <li><strong><?php echo esc_html__( 'Name', 'pollmonitor' ); ?>:</strong> <?php echo esc_html( $user->display_name ); ?></li>
        <li><strong><?php echo esc_html__( 'Email', 'pollmonitor' ); ?>:</strong> <?php echo esc_html( $user->user_email ); ?></li>
        <li><strong><?php echo esc_html__( 'Observer ID', 'pollmonitor' ); ?>:</strong> <?php echo esc_html( $observer_id ); ?></li>
    </ul>

    <h3><?php echo esc_html__( 'Assigned Stations', 'pollmonitor' ); ?></h3>
    <?php if ( empty( $assigned_station_ids ) ) : ?>
        <p><?php echo esc_html__( 'No stations assigned.', 'pollmonitor' ); ?></p>
    <?php else : ?>
        <ul class="pm-assigned-stations">
            <?php foreach ( $assigned_station_ids as $station_id ) :
                $station_post = get_post( $station_id );
                if ( $station_post ) : ?>
                    <li>
                        <a href="<?php echo esc_url( get_permalink( $station_id ) ); ?>"><?php echo esc_html( get_the_title( $station_id ) ); ?></a>
                        (ID: <?php echo intval( $station_id ); ?>)
                    </li>
                <?php else : ?>
                    <li><?php echo esc_html__( 'Station not found', 'pollmonitor' ); ?> (ID: <?php echo intval( $station_id ); ?>)</li>
                <?php endif;
            endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>
        <a class="button" href="<?php echo esc_url( get_permalink() ); ?>?action=submit_incident"><?php echo esc_html__( 'Submit Incident', 'pollmonitor' ); ?></a>
        <a class="button" href="<?php echo esc_url( wp_logout_url( $current_url ) ); ?>"><?php echo esc_html__( 'Log out', 'pollmonitor' ); ?></a>
    </p>
</div>

<div id="pm-dashboard-root" class="pm-dashboard">
    <h2><?php echo esc_html__( 'Recent Reports', 'pollmonitor' ); ?></h2>
    <div id="pm-recent-reports"><?php echo esc_html__( 'Loading...', 'pollmonitor' ); ?></div>

    <div class="pm-form" aria-live="polite">
        <h3><?php echo esc_html__( 'Submit New Report', 'pollmonitor' ); ?></h3>
        <form id="pm-submit-form">
            <label for="pm-title"><?php echo esc_html__( 'Title', 'pollmonitor' ); ?></label>
            <input id="pm-title" type="text" />

            <label for="pm-content"><?php echo esc_html__( 'Details', 'pollmonitor' ); ?></label>
            <textarea id="pm-content" rows="4"></textarea>

            <label for="pm-station-search"><?php echo esc_html__( 'Polling Unit (search by name)', 'pollmonitor' ); ?></label>
            <input id="pm-station-search" type="text" placeholder="Start typing a station name" autocomplete="off" />
            <input id="pm-station-id" type="hidden" />
            <ul id="pm-station-suggestions" style="list-style:none;margin:6px 0;padding:6px;border:1px solid #eee;max-height:140px;overflow:auto"></ul>

            <button type="submit"><?php echo esc_html__( 'Submit Report', 'pollmonitor' ); ?></button>
            <div id="pm-submit-status" class="pm-status" role="status" aria-live="polite"></div>
        </form>
    </div>
</div>
