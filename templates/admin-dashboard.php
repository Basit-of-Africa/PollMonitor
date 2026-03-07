<div class="wrap pollmonitor-dashboard">
    <h1 class="wp-heading-inline">PollMonitor Dashboard</h1>
    <hr class="wp-header-end">

    <div class="pollmonitor-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
        
        <!-- Total Stations -->
        <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #50575e;">Total Registered Stations</h3>
            <?php
            // Cache the total stations count for 5 minutes
            $cached_stations = get_transient( 'pollmonitor_total_stations' );
            if ( false === $cached_stations ) {
                $stations_count = wp_count_posts('poll_station');
                $cached_stations = isset($stations_count->publish) ? $stations_count->publish : 0;
                set_transient( 'pollmonitor_total_stations', $cached_stations, 300 );
            }
            echo '<h2 style="font-size: 2em; margin: 0; color: #0073aa;">' . esc_html( $cached_stations ) . '</h2>';
            ?>
        </div>

        <!-- Total Incidents -->
        <div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #50575e;">Total Incidents Reported</h3>
            <?php
            // Cache the total incidents count for 5 minutes
            $cached_incidents = get_transient( 'pollmonitor_total_incidents' );
            if ( false === $cached_incidents ) {
                $incidents_count = wp_count_posts('incident_report');
                $cached_incidents = (isset($incidents_count->pending) ? $incidents_count->pending : 0) + (isset($incidents_count->publish) ? $incidents_count->publish : 0);
                set_transient( 'pollmonitor_total_incidents', $cached_incidents, 300 );
            }
            echo '<h2 style="font-size: 2em; margin: 0; color: #d63638;">' . esc_html( $cached_incidents ) . '</h2>';
            ?>
        </div>
    </div>

    <!-- Recent Incidents Table -->
    <div class="pollmonitor-recent" style="margin-top: 40px;">
        <h2>Recent Incidents</h2>
        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date Reported</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Cache the recent incidents query for 5 minutes
                $recent_incidents = get_transient( 'pollmonitor_recent_incidents' );
                if ( false === $recent_incidents ) {
                    $recent_incidents = get_posts( array(
                        'post_type'      => 'incident_report',
                        'posts_per_page' => 10,
                        'post_status'    => array('publish', 'pending'),
                        'orderby'        => 'date',
                        'order'          => 'DESC'
                    ) );
                    set_transient( 'pollmonitor_recent_incidents', $recent_incidents, 300 );
                }
                if ( $recent_incidents ) {
                    foreach ( $recent_incidents as $post ) {
                        echo '<tr>';
                        echo '<td><strong><a href="' . get_edit_post_link( $post->ID ) . '">' . esc_html( $post->post_title ) . '</a></strong></td>';
                        echo '<td>' . get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) . '</td>';
                        echo '<td><span class="status-' . esc_attr($post->post_status) . '">' . esc_html( $post->post_status ) . '</span></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">No recent incidents found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 40px;">
        <h2>Interactive Incident Map</h2>
        <div id="pollmonitor-map" style="height: 400px; width: 100%; border: 1px solid #ccc; border-radius: 4px; background: #eee;"></div>
    </div>
</div>
