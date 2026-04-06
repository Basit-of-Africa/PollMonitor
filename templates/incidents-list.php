<section class="pm-reports-shell">
    <div class="pm-reports-hero">
        <div>
            <p class="pm-eyebrow">PollMonitor Feed</p>
            <h2>Incident Reports</h2>
            <p class="pm-hero-copy">Browse verified and pending field reports with a live map, quick filters, and a cleaner detail view built for election monitoring.</p>
            <div class="pm-live-row">
                <span class="pm-live-pill"><span class="pm-live-dot"></span>Frontend monitor</span>
                <span id="pm-last-refresh" class="pm-live-meta">Waiting for first refresh...</span>
            </div>
        </div>
        <div class="pm-hero-stats" aria-live="polite">
            <article class="pm-stat-tile">
                <span class="pm-stat-label">Visible Reports</span>
                <strong id="pm-stat-visible">0</strong>
            </article>
            <article class="pm-stat-tile">
                <span class="pm-stat-label">Current Page</span>
                <strong id="pm-stat-page">1</strong>
            </article>
            <article class="pm-stat-tile">
                <span class="pm-stat-label">Applied Filter</span>
                <strong id="pm-stat-filter">All</strong>
            </article>
        </div>
    </div>

    <div class="pm-toolbar">
        <div class="pm-toolbar-group">
            <label for="pm-filter-category">Issue Category</label>
            <select id="pm-filter-category" aria-label="Filter incidents by category">
                <option value="">All categories</option>
            </select>
        </div>
        <div class="pm-toolbar-group">
            <label for="pm-filter-severity">Severity</label>
            <select id="pm-filter-severity" aria-label="Filter incidents by severity">
                <option value="">All severities</option>
            </select>
        </div>
        <div class="pm-toolbar-group pm-toolbar-search">
            <label for="pm-search">Search Reports</label>
            <input type="search" id="pm-search" placeholder="Search title or description">
        </div>
        <div class="pm-toolbar-actions">
            <label class="pm-live-toggle">
                <input type="checkbox" id="pm-live-toggle" checked>
                <span>Live mode</span>
            </label>
            <button type="button" id="pm-filter-reset" class="pm-secondary-btn">Reset</button>
        </div>
    </div>

    <div class="pm-status-bar" id="pm-status-bar" aria-label="Status filters">
        <button type="button" class="pm-status-chip is-active" data-status="">All statuses</button>
        <button type="button" class="pm-status-chip" data-status="pending">Pending</button>
        <button type="button" class="pm-status-chip" data-status="publish">Approved</button>
        <button type="button" class="pm-status-chip" data-status="draft">Draft</button>
    </div>

    <div class="pm-layout-grid">
        <div class="pm-map-panel">
            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Geography</p>
                    <h3>Incident Map</h3>
                </div>
                <span class="pm-panel-note">Select a marker or a report card to inspect details.</span>
            </div>
            <div id="pm-incidents-map" class="pm-map" aria-label="Incident map"></div>
            <div class="pm-map-legend" aria-label="Incident severity legend">
                <div class="pm-map-legend-head">
                    <strong>Severity legend</strong>
                    <span>Marker colors match incident urgency on the map.</span>
                </div>
                <div class="pm-map-legend-items">
                    <div class="pm-map-legend-item">
                        <span class="pm-map-legend-swatch pm-map-legend-swatch-danger" aria-hidden="true"></span>
                        <span>Critical / High</span>
                    </div>
                    <div class="pm-map-legend-item">
                        <span class="pm-map-legend-swatch pm-map-legend-swatch-warning" aria-hidden="true"></span>
                        <span>Medium / Moderate</span>
                    </div>
                    <div class="pm-map-legend-item">
                        <span class="pm-map-legend-swatch pm-map-legend-swatch-calm" aria-hidden="true"></span>
                        <span>Low</span>
                    </div>
                    <div class="pm-map-legend-item">
                        <span class="pm-map-legend-swatch pm-map-legend-swatch-neutral" aria-hidden="true"></span>
                        <span>Unclassified</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pm-feed-panel">
            <div class="pm-spotlight-panel" id="pm-spotlight-panel" aria-live="polite">
                <div class="pm-panel-head pm-panel-head-spotlight">
                    <div>
                        <p class="pm-panel-kicker">Spotlight</p>
                        <h3>Priority Incident</h3>
                    </div>
                    <span id="pm-spotlight-label" class="pm-panel-note">Evaluating reports...</span>
                </div>
                <div id="pm-spotlight-content" class="pm-spotlight-content"></div>
            </div>

            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Reports</p>
                    <h3>Latest Incident Feed</h3>
                </div>
                <span id="pm-results-summary" class="pm-panel-note">Loading reports...</span>
            </div>

            <div id="pm-incident-list" class="pm-incident-list" aria-live="polite"></div>

            <div class="pm-pagination" id="pm-pagination" aria-hidden="true"></div>
        </div>
    </div>

    <div id="pm-incident-modal" class="pm-modal" aria-hidden="true">
        <div class="pm-modal-inner" role="dialog" aria-modal="true" aria-labelledby="pm-modal-title">
            <button class="pm-modal-close" aria-label="Close">x</button>
            <div id="pm-modal-content"></div>
        </div>
    </div>
</section>
