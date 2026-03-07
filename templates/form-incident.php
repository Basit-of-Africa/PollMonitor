<div class="pollmonitor-incident-form-container" style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; background: #fafafa; border-radius: 5px;">
    <h2>Report an Incident</h2>
    <div id="pollmonitor-form-message" style="display: none; padding: 10px; margin-bottom: 20px; border-radius: 4px;"></div>

    <form id="pollmonitor-incident-form">
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="pm-title" style="display: block; margin-bottom: 5px; font-weight: bold;">Incident Title *</label>
            <input type="text" id="pm-title" name="pm-title" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label for="pm-content" style="display: block; margin-bottom: 5px; font-weight: bold;">Description *</label>
            <textarea id="pm-content" name="pm-content" rows="5" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
        </div>

        <!-- Station Selection (To be populated by JS) -->
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="pm-station" style="display: block; margin-bottom: 5px; font-weight: bold;">Select Polling Station *</label>
            <select id="pm-station" name="pm-station" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">Loading stations...</option>
            </select>
        </div>

        <!-- File Upload for Evidence -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="pm-evidence" style="display: block; margin-bottom: 5px; font-weight: bold;">Upload Photo Evidence</label>
            <input type="file" id="pm-evidence" name="pm-evidence" accept="image/png, image/jpeg, image/jpg" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; background: #fff;">
            <small style="color: #666; display: block; margin-top: 5px;">Optional. Max size: 2MB.</small>
        </div>
        <div class="form-group">
            <button type="submit" id="pm-submit-btn" style="background: #0073aa; color: #fff; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">Submit Report</button>
            <span id="pm-loading" style="display:none; margin-left:10px;">Submitting...</span>
        </div>
    </form>
</div>
