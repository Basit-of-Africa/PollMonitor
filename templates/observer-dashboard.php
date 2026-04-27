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

<!-- Full dashboard mockup integrated into observer dashboard -->
<div class="app">
    <div class="header">
        <div class="header-inner">
            <div class="coat-arms">🦅</div>
            <div>
                <div class="header-title">Nigeria Election Observation & Monitoring System</div>
                <div class="header-subtitle">Independent National Electoral Commission — Observer Network</div>
            </div>
            <div style="margin-left:auto; font-size:11px; opacity:0.8;" id="clock"></div>
        </div>
        <nav class="nav">
            <button class="nav-btn active" onclick="showTab('dashboard')">Live Dashboard</button>
            <button class="nav-btn" onclick="showTab('reports')">Field Reports</button>
            <button class="nav-btn" onclick="showTab('submit')">Submit Observation</button>
            <button class="nav-btn" onclick="showTab('observers')">Observer Registry</button>
            <?php if ( current_user_can( 'pollmonitor_validate' ) || current_user_can( 'manage_options' ) ) : ?>
                <button class="nav-btn" onclick="showTab('onboard')">Onboard Observer</button>
            <?php endif; ?>
        </nav>
    </div>

    <div class="content visible" id="tab-dashboard">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Reports Submitted</div>
                <div class="stat-value" id="total-reports">—</div>
                <div class="stat-sub"><span class="stat-dot" style="background:#27AE60"></span><span id="reports-change">—</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Observers</div>
                <div class="stat-value" id="active-obs">—</div>
                <div class="stat-sub"><span class="stat-dot" style="background:#2471A3"></span>of <span id="deployed-obs">—</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Incidents Flagged</div>
                <div class="stat-value" id="incidents-count" style="color:#C0392B">—</div>
                <div class="stat-sub"><span class="stat-dot" style="background:#E67E22"></span><span id="incidents-sub">—</span></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Polling Units Covered</div>
                <div class="stat-value" id="coverage-pct">—</div>
                <div class="stat-sub"><span class="stat-dot" style="background:#8E44AD"></span><span id="coverage-details">—</span></div>
            </div>
        </div>

        <div class="two-col">
            <div>
                <div class="section-title"><div class="live-dot"></div>Live Reports Feed</div>
                <div class="card" id="live-feed" style="max-height:260px; overflow-y:auto; padding:10px 14px;"></div>
                <div class="section-title" style="margin-top:12px;">Coverage by State</div>
                <div class="card"><div id="state-progress"></div></div>
            </div>
            <div>
                <div class="section-title">Incident Heatmap — Polling Units</div>
                <div class="card"><div class="map-grid" id="heatmap"></div>
                    <div style="display:flex; gap:16px; font-size:11px; color:var(--muted); margin-top:4px;">
                        <span><span style="display:inline-block;width:10px;height:10px;background:#E8F5ED;border-radius:2px;margin-right:4px;"></span>Normal</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#FFD700;border-radius:2px;margin-right:4px;"></span>Caution</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#E67E22;border-radius:2px;margin-right:4px;"></span>Incident</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#C0392B;border-radius:2px;margin-right:4px;"></span>Critical</span>
                    </div>
                </div>
                <div class="section-title" style="margin-top:12px;">Report Breakdown</div>
                <div class="card"><div class="donut-wrap"><div style="position:relative; width:110px; height:110px; flex-shrink:0;"><canvas id="donutChart" role="img" aria-label="Donut chart">No data</canvas></div><div id="donut-legend"></div></div></div>
            </div>
        </div>
    </div>

    <div class="content" id="tab-reports"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;"><div class="section-title" style="margin:0;"><div class="live-dot"></div>All Field Reports</div><div style="display:flex; gap:8px;"><select id="filter-type" style="font-size:12px; padding:5px 8px;" onchange="filterReports()"><option value="">All types</option><option value="normal">Normal</option><option value="incident">Incident</option><option value="warning">Warning</option><option value="info">Info</option></select><select id="filter-state" style="font-size:12px; padding:5px 8px;" onchange="filterReports()"><option value="">All states</option></select></div></div><div class="card" id="all-reports-list" style="padding: 8px 14px;"></div></div>

    <div class="content" id="tab-submit">
        <div class="card" style="max-width:700px; margin:0 auto;">
            <div class="section-title" style="margin-bottom:16px;">Submit Field Observation</div>
            <div class="form-group"><label class="form-label">Observer</label><div><?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $observer_id ); ?>)</div></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Polling Unit (search)</label><input id="f-station-search" placeholder="Start typing a station name" autocomplete="off" /><input id="f-station-id" type="hidden" /><ul id="f-station-suggestions" style="list-style:none;margin:6px 0;padding:6px;border:1px solid #eee;max-height:140px;overflow:auto"></ul></div>
            </div>
            <div class="form-group"><label class="form-label">Report Type</label><select id="f-type"><option value="normal">Normal — No issues</option><option value="info">Information Update</option><option value="warning">Warning — Potential Issue</option><option value="incident">Incident — Violation Observed</option></select></div>
            <div class="form-group"><label class="form-label">Observation Details</label><textarea id="f-details" placeholder="Describe what you observed"></textarea></div>
            <button class="btn-submit" id="pm-submit-btn">Submit Observation Report</button>
            <div id="submit-confirmation" style="margin-top:12px; font-size:12px; color:#27AE60; display:none; text-align:center;"></div>
        </div>
    </div>

    <div class="content" id="tab-observers">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;"><div class="section-title" style="margin:0;">Observer Registry</div><input type="text" placeholder="Search observers..." style="width:200px; font-size:12px; padding:5px 10px;" oninput="filterObservers(this.value)" /></div>
        <div class="card" style="padding:0; overflow:hidden;"><table id="observers-table"><thead><tr><th>ID</th><th>Name</th><th>State</th><th>LGA</th><th>Ward</th><th>Polling Unit</th><th>Reports</th><th>Status</th></tr></thead><tbody id="observers-body"></tbody></table></div>
    </div>

    <?php if ( current_user_can( 'pollmonitor_validate' ) || current_user_can( 'manage_options' ) ) : ?>
    <div class="content" id="tab-onboard">
        <div class="card" style="max-width:600px; margin:0 auto;">
            <?php echo do_shortcode( '[pollmonitor_onboard_form]' ); ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// Wire up some small JS hooks — the main logic lives in assets/js/dashboard.js
?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // attach station autocomplete for submit form
    if (window.pollmonitorApiSettings) {
        // reuse functions from dashboard.js by ensuring elements exist
    }
});
</script>
</div>
