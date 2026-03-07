document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pollmonitor-incident-form');
    const messageBox = document.getElementById('pollmonitor-form-message');
    const submitBtn = document.getElementById('pm-submit-btn');
    const loadingSpan = document.getElementById('pm-loading');
    if (!form) return;

    // Load Stations on Init
    loadStations();

    function loadStations() {
        const stationSelect = document.getElementById('pm-station');
        fetch(pollmonitorApiSettings.root + 'pollmonitor/v1/stations')
            .then(response => response.json())
            .then(data => {
                stationSelect.innerHTML = '<option value="">-- Select a Station --</option>';
                if(data && data.length) {
                    data.forEach(station => {
                        const option = document.createElement('option');
                        option.value = station.id;
                        option.textContent = station.title;
                        stationSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading stations:', error);
                stationSelect.innerHTML = '<option value="">Error loading stations</option>';
            });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Basic validation
        const title = document.getElementById('pm-title').value;
        const content = document.getElementById('pm-content').value;
        const station = document.getElementById('pm-station').value;

        if (!title || !content || !station) {
            showMessage('Please fill in all required fields.', 'error');
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        loadingSpan.style.display = 'inline';
        messageBox.style.display = 'none';

        // Prepare data as FormData to handle files
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        formData.append('station_id', station);

        const fileInput = document.getElementById('pm-evidence');
        if (fileInput.files.length > 0) {
            formData.append('evidence', fileInput.files[0]);
        }

        // Make API request (using settings localized from PHP)
        fetch(pollmonitorApiSettings.root + 'pollmonitor/v1/incidents', {
            method: 'POST',
            headers: {
                // Do NOT set Content-Type header when sending FormData; 
                // fetch will automatically set it to multipart/form-data with the correct boundary
                'X-WP-Nonce': pollmonitorApiSettings.nonce
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            showMessage('Incident reported successfully! ID: ' + data.id, 'success');
            form.reset();
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('There was an error submitting your report. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            loadingSpan.style.display = 'none';
        });
    });

    function showMessage(msg, type) {
        messageBox.textContent = msg;
        messageBox.style.display = 'block';
        if (type === 'error') {
            messageBox.style.background = '#f8d7da';
            messageBox.style.color = '#721c24';
            messageBox.style.border = '1px solid #f5c6cb';
        } else {
            messageBox.style.background = '#d4edda';
            messageBox.style.color = '#155724';
            messageBox.style.border = '1px solid #c3e6cb';
        }
    }
});
