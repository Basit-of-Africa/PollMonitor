<div class="pollmonitor-incident-form-container max-w-3xl mx-auto p-6 bg-white rounded-lg shadow">
    <h2 class="text-2xl font-semibold mb-4">Report an Incident</h2>

    <div id="pollmonitor-form-message" class="pm-message" role="status" aria-live="polite" style="display:none;"></div>

    <form id="pollmonitor-incident-form" class="pm-form space-y-4" novalidate>
        <div class="pm-row">
            <label for="pm-title" class="block text-sm font-medium text-gray-700">Incident Title *</label>
            <input type="text" id="pm-title" name="pm-title" required placeholder="Short descriptive title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="pm-row">
            <label for="pm-content" class="block text-sm font-medium text-gray-700">Description *</label>
            <textarea id="pm-content" name="pm-content" rows="5" required placeholder="Describe what happened" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>

        <div class="pm-row">
            <label for="pm-station" class="block text-sm font-medium text-gray-700">Select Polling Station *</label>
            <select id="pm-station" name="pm-station" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="">Loading stations...</option>
            </select>
        </div>

        <div class="pm-row">
            <label class="block text-sm font-medium text-gray-700">Interactive Station Map</label>
            <div id="pollmonitor-frontend-map" class="pm-map mt-2 rounded-md" aria-hidden="false"></div>
            <small class="pm-hint text-sm text-gray-500">Click a marker to select a station.</small>
        </div>

        <div class="pm-row">
            <label for="pm-evidence" class="block text-sm font-medium text-gray-700">Upload Photo Evidence</label>
            <input type="file" id="pm-evidence" name="pm-evidence" accept="image/png, image/jpeg, image/jpg" class="mt-1">
            <small class="pm-hint text-sm text-gray-500">Optional. PNG/JPEG only. Max size: 2MB.</small>
            <div id="pm-preview" class="pm-preview mt-2" aria-hidden="true"></div>
        </div>

        <div class="pm-actions flex items-center gap-3">
            <button type="submit" id="pm-submit-btn" class="pm-btn" disabled>Submit Report</button>
            <span id="pm-loading" class="pm-loading text-sm text-gray-600" aria-hidden="true">Submitting...</span>
        </div>
    </form>
</div>
