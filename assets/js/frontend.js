document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pollmonitor-incident-form');
    const messageBox = document.getElementById('pollmonitor-form-message');
    const submitBtn = document.getElementById('pm-submit-btn');
    const loadingSpan = document.getElementById('pm-loading');
    const fileInput = document.getElementById('pm-evidence');
    const previewContainer = document.getElementById('pm-preview');
    if (!form) return;

    // Load Stations on Init
    loadStations();
    bindFilePreview();

    function loadStations() {
        const stationSelect = document.getElementById('pm-station');
        // Keep submit disabled until stations load
        if ( submitBtn ) submitBtn.disabled = true;
        fetch(pollmonitorApiSettings.root + 'pollmonitor/v1/stations')
            .then(response => response.json())
            .then(data => {
                stationSelect.innerHTML = '<option value="">-- Select a Station --</option>';
                if(data && data.length) {
                    // Populate select and map markers
                    const markers = [];
                    data.forEach(station => {
                        const option = document.createElement('option');
                        option.value = station.id;
                        option.textContent = station.title;
                        stationSelect.appendChild(option);

                        if ( station.lat && station.lng ) {
                            markers.push({ id: station.id, title: station.title, lat: station.lat, lng: station.lng });
                        }
                    });

                    if ( submitBtn ) submitBtn.disabled = false;

                    // Initialize frontend map if available
                    if ( typeof L !== 'undefined' && markers.length > 0 ) {
                        initFrontendMap( markers );
                    }
                }
            })
            .catch(error => {
                console.error('Error loading stations:', error);
                stationSelect.innerHTML = '<option value="">Error loading stations</option>';
            });
    }

    // Initialize Leaflet map on the frontend and add markers
    function initFrontendMap( markers ) {
        const mapEl = document.getElementById('pollmonitor-frontend-map');
        if ( ! mapEl ) return;

        // Create map if not already created
        if ( ! window.pollmonitorFrontendMap ) {
            window.pollmonitorFrontendMap = L.map( 'pollmonitor-frontend-map' ).setView([9.0765, 7.3986], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '© OpenStreetMap contributors'
            }).addTo(window.pollmonitorFrontendMap);
        }

        const map = window.pollmonitorFrontendMap;
        const group = [];

        markers.forEach(s => {
            const marker = L.marker([s.lat, s.lng]).addTo(map);
            marker.bindPopup('<strong>' + escapeHtml(s.title) + '</strong><br>ID: ' + s.id + '<br><button data-station="' + s.id + '" class="pm-select-station">Select</button>');
            group.push([s.lat, s.lng]);
        });

        if ( group.length > 0 ) {
            map.fitBounds(group, { padding: [40, 40] });
        }

        // Delegate click on select buttons inside popups
        map.on('popupopen', function(e) {
            const popupNode = e.popup.getElement();
            if ( popupNode ) {
                popupNode.querySelectorAll('.pm-select-station').forEach(btn => {
                    btn.addEventListener('click', function(ev) {
                        const id = this.getAttribute('data-station');
                        const select = document.getElementById('pm-station');
                        if ( select ) {
                            select.value = id;
                            select.dispatchEvent(new Event('change'));
                            // scroll into view for accessibility
                            select.focus();
                        }
                    });
                });
            }
        });
    }

    function escapeHtml(unsafe) {
        return unsafe.replace(/[&<"'`=\/]/g, function (s) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            })[s];
        });
    }

    function bindFilePreview() {
        if ( ! fileInput ) return;
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            previewContainer.innerHTML = '';
            if ( ! file ) return;

            // Basic client-side validation
            const maxSize = 2 * 1024 * 1024; // 2MB
            const allowed = [ 'image/jpeg', 'image/png', 'image/jpg' ];
            if ( file.size > maxSize ) {
                showMessage('Image too large (max 2MB).', 'error');
                this.value = '';
                return;
            }
            if ( allowed.indexOf( file.type ) === -1 ) {
                showMessage('Invalid image type. Use PNG or JPEG.', 'error');
                this.value = '';
                return;
            }

            // Preview
            const img = document.createElement('img');
            img.alt = 'Evidence preview';
            img.src = URL.createObjectURL( file );
            img.onload = function() { URL.revokeObjectURL(this.src); };
            previewContainer.appendChild( img );
            previewContainer.setAttribute('aria-hidden','false');
            // clear previous messages
            hideMessage();
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Basic validation
        const title = document.getElementById('pm-title').value.trim();
        const content = document.getElementById('pm-content').value.trim();
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

        if ( fileInput && fileInput.files.length > 0 ) {
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
        .then(response => response.json().catch(() => ({})).then(body => ({ status: response.status, body }) ) )
        .then(({ status, body }) => {
            if ( status >= 200 && status < 300 ) {
                showMessage('Incident reported successfully! ID: ' + (body.id || ''), 'success');
                form.reset();
                if ( previewContainer ) previewContainer.innerHTML = '';
            } else {
                const msg = (body && body.message) ? body.message : 'There was an error submitting your report. Please try again.';
                showMessage(msg, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
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
        messageBox.classList.remove('success','error');
        messageBox.style.display = 'block';
        if (type === 'error') {
            messageBox.classList.add('error');
        } else {
            messageBox.classList.add('success');
        }
    }

    function hideMessage() {
        messageBox.style.display = 'none';
        messageBox.textContent = '';
        messageBox.classList.remove('success','error');
    }
});
