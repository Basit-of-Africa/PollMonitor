<div class="pollmonitor-incident-list-container max-w-5xl mx-auto p-6">
    <h2 class="text-2xl font-semibold mb-4">Reported Incidents</h2>

    <div class="pm-row pm-filters mb-4">
        <label for="pm-filter-category" class="block text-sm font-medium text-gray-700">Filter by Issue Category</label>
        <select id="pm-filter-category" aria-label="Filter incidents by category" class="mt-1 block w-60 border-gray-300 rounded-md">
            <option value="">All categories</option>
        </select>
    </div>

    <div class="pm-row mb-6">
        <label class="block text-sm font-medium text-gray-700">Incidents Map</label>
        <div id="pm-incidents-map" class="pm-map mt-2 rounded-md shadow-sm"></div>
    </div>

    <div id="pm-incident-list" class="pm-incident-list grid grid-cols-1 md:grid-cols-2 gap-4" aria-live="polite"></div>

    <div class="pm-pagination mt-6 flex items-center justify-center" id="pm-pagination" aria-hidden="true"></div>

    <div id="pm-incident-modal" class="pm-modal" aria-hidden="true">
        <div class="pm-modal-inner" role="dialog" aria-modal="true">
            <button class="pm-modal-close float-right text-xl" aria-label="Close">×</button>
            <div id="pm-modal-content"></div>
        </div>
    </div>
</div>
