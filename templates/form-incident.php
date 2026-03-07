<div class="pollmonitor-incident-form-container">
    <h2>Report an Incident</h2>

    <div id="pollmonitor-form-message" class="pm-message" role="status" aria-live="polite" style="display:none;"></div>

    <form id="pollmonitor-incident-form" class="pm-form" novalidate>
        <div class="pm-row">
            <label for="pm-title">Incident Title *</label>
            <input type="text" id="pm-title" name="pm-title" required placeholder="Short descriptive title">
        </div>

        <div class="pm-row">
            <label for="pm-content">Description *</label>
            <textarea id="pm-content" name="pm-content" rows="5" required placeholder="Describe what happened"></textarea>
        </div>

        <div class="pm-row">
            <label for="pm-station">Select Polling Station *</label>
            <select id="pm-station" name="pm-station" required>
                <option value="">Loading stations...</option>
            </select>
        </div>

        <div class="pm-row">
            <label>Interactive Station Map</label>
            <div id="pollmonitor-frontend-map" class="pm-map" aria-hidden="false"></div>
            <small class="pm-hint">Click a marker to select a station.</small>
        </div>

        <div class="pm-row">
            <label for="pm-evidence">Upload Photo Evidence</label>
            <input type="file" id="pm-evidence" name="pm-evidence" accept="image/png, image/jpeg, image/jpg">
            <small class="pm-hint">Optional. PNG/JPEG only. Max size: 2MB.</small>
            <div id="pm-preview" class="pm-preview" aria-hidden="true"></div>
        </div>

        <div class="pm-actions">
            <button type="submit" id="pm-submit-btn" class="pm-btn" disabled>Submit Report</button>
            <span id="pm-loading" class="pm-loading" aria-hidden="true">Submitting...</span>
        </div>
    </form>
</div>
