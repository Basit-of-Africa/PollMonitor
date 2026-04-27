<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Public viewer dashboard: only Live Dashboard (read-only, no login required)
?>
<div class="app">
  <div class="header">
    <div class="header-inner">
      <div class="coat-arms">🦅</div>
      <div>
        <div class="header-title">PollMonitor — Live Dashboard</div>
        <div class="header-subtitle">Live field reports and incident heatmap</div>
      </div>
      <div style="margin-left:auto; font-size:11px; opacity:0.8;" id="clock"></div>
    </div>
  </div>

  <div class="content visible" id="tab-dashboard">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Reports Submitted</div>
        <div class="stat-value" id="total-reports">—</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Observers</div>
        <div class="stat-value" id="active-obs">—</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Incidents Flagged</div>
        <div class="stat-value" id="incidents-count">—</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Polling Units Covered</div>
        <div class="stat-value" id="coverage-pct">—</div>
      </div>
    </div>

    <div class="two-col">
      <div>
        <div class="section-title"><div class="live-dot"></div>Live Reports Feed</div>
        <div class="card" id="live-feed" style="max-height:360px; overflow-y:auto; padding:10px 14px;"></div>
      </div>
      <div>
        <div class="section-title">Incident Heatmap — Polling Units</div>
        <div class="card"><div class="map-grid" id="heatmap"></div></div>
        <div class="section-title" style="margin-top:12px;">Report Breakdown</div>
        <div class="card"><div class="donut-wrap"><div style="position:relative; width:110px; height:110px; flex-shrink:0;"><canvas id="donutChart" role="img" aria-label="Donut chart">No data</canvas></div><div id="donut-legend"></div></div></div>
      </div>
    </div>
  </div>
</div>
