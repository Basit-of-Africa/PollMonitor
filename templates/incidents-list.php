<div class="pollmonitor-incident-list-container">
    <h2>Reported Incidents</h2>

    <div class="pm-row pm-filters">
        <label for="pm-filter-category">Filter by Issue Category</label>
        <select id="pm-filter-category" aria-label="Filter incidents by category">
            <option value="">All categories</option>
        </select>
    </div>

    <div class="pm-row">
        <label>Incidents Map</label>
        <div id="pm-incidents-map" class="pm-map"></div>
    </div>

    <div id="pm-incident-list" class="pm-incident-list" aria-live="polite"></div>

    <div class="pm-pagination" id="pm-pagination" aria-hidden="true"></div>

    <div id="pm-incident-modal" class="pm-modal" aria-hidden="true">
        <div class="pm-modal-inner" role="dialog" aria-modal="true">
            <button class="pm-modal-close" aria-label="Close">×</button>
            <div id="pm-modal-content"></div>
        </div>
    </div>
</div>
