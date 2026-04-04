document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pollmonitor-incident-form');
    const messageBox = document.getElementById('pollmonitor-form-message');
    const submitBtn = document.getElementById('pm-submit-btn');
    const loadingSpan = document.getElementById('pm-loading');
    const stationSelect = document.getElementById('pm-station');
    const stateSelect = document.getElementById('pm-state');
    const lgaSelect = document.getElementById('pm-lga');
    const wardSelect = document.getElementById('pm-ward');
    const locationFilters = document.getElementById('pm-location-filters');
    const assignmentMessage = document.getElementById('pm-assignment-message');
    const stationAccess = pollmonitorApiSettings.stationAccess || {};
    // inject spinner inside submit button for better visual
    if ( submitBtn && ! submitBtn.querySelector('.pm-spinner') ) {
        const spinner = document.createElement('span');
        spinner.className = 'pm-spinner';
        spinner.style.display = 'none';
        submitBtn.appendChild(spinner);
    }
    const fileInput = document.getElementById('pm-evidence');
    const previewContainer = document.getElementById('pm-preview');
    if (!form) return;

    // Load Stations on Init
    loadStations();
    bindFilePreview();
    bindLocationFilters();

    function loadStations() {
        // Keep submit disabled until stations load
        if ( submitBtn ) submitBtn.disabled = true;
        resetStationSelect('Loading stations...');

        if ( stationAccess.has_assignments ) {
            if ( assignmentMessage ) {
                assignmentMessage.style.display = 'block';
                assignmentMessage.textContent = 'Your account is restricted to assigned polling units.';
            }
            fetchStations('pollmonitor/v1/stations?assigned_only=1');
            return;
        }

        if ( stationAccess.requires_assignment ) {
            if ( assignmentMessage ) {
                assignmentMessage.style.display = 'block';
                assignmentMessage.textContent = 'No polling units have been assigned to your account yet. Contact an administrator.';
            }
            resetStationSelect('No assigned polling units available');
            return;
        }

        if ( locationFilters ) {
            locationFilters.style.display = 'block';
        }
        if ( assignmentMessage ) {
            assignmentMessage.style.display = 'block';
            assignmentMessage.textContent = 'Select a state, LGA, and ward to load polling units.';
        }
        loadLocationOptions('state', null, stateSelect, 'Select state');
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

        if ( window.pollmonitorFrontendMarkers ) {
            window.pollmonitorFrontendMarkers.clearLayers();
        } else {
            window.pollmonitorFrontendMarkers = L.layerGroup().addTo(map);
        }

        markers.forEach(s => {
            if ( s.lat === null || s.lng === null ) {
                return;
            }

            const marker = L.marker([s.lat, s.lng]).addTo(window.pollmonitorFrontendMarkers);
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

    function bindLocationFilters() {
        if ( ! stateSelect || ! lgaSelect || ! wardSelect ) return;

        stateSelect.addEventListener('change', function() {
            resetSelect(lgaSelect, 'Select LGA');
            resetSelect(wardSelect, 'Select ward');
            resetStationSelect('Select a ward to load polling units');

            if ( ! this.value ) {
                lgaSelect.disabled = true;
                wardSelect.disabled = true;
                return;
            }

            loadLocationOptions('lga', this.value, lgaSelect, 'Select LGA');
        });

        lgaSelect.addEventListener('change', function() {
            resetSelect(wardSelect, 'Select ward');
            resetStationSelect('Select a ward to load polling units');

            if ( ! this.value ) {
                wardSelect.disabled = true;
                return;
            }

            loadLocationOptions('ward', this.value, wardSelect, 'Select ward');
        });

        wardSelect.addEventListener('change', function() {
            if ( ! this.value ) {
                resetStationSelect('Select a ward to load polling units');
                return;
            }

            fetchStations('pollmonitor/v1/stations-by-location?taxonomy=ward&term_id=' + encodeURIComponent(this.value));
        });
    }

    function loadLocationOptions(type, parentId, selectEl, placeholder) {
        if ( ! selectEl ) return;

        selectEl.disabled = true;
        resetSelect(selectEl, 'Loading...');

        let url = 'pollmonitor/v1/locations?type=' + encodeURIComponent(type);
        if ( parentId ) {
            url += '&parent=' + encodeURIComponent(parentId);
        }

        fetchJson(url)
            .then(data => {
                resetSelect(selectEl, placeholder);
                if ( Array.isArray(data) ) {
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        selectEl.appendChild(option);
                    });
                }
                selectEl.disabled = false;
            })
            .catch(error => {
                console.error('Error loading locations:', error);
                resetSelect(selectEl, 'Error loading options');
                selectEl.disabled = false;
            });
    }

    function fetchStations(path) {
        fetchJson(path)
            .then(data => {
                populateStations(Array.isArray(data) ? data : []);
            })
            .catch(error => {
                console.error('Error loading stations:', error);
                resetStationSelect('Error loading stations');
            });
    }

    function fetchJson(path) {
        return fetch(pollmonitorApiSettings.root + path, {
            headers: {
                'X-WP-Nonce': pollmonitorApiSettings.nonce
            }
        }).then(response => {
            if ( ! response.ok ) {
                return response.json().catch(() => ({})).then(body => {
                    const message = body && body.message ? body.message : 'Request failed';
                    throw new Error(message);
                });
            }
            return response.json();
        });
    }

    function populateStations(data) {
        resetStationSelect('-- Select a Station --');
        const markers = [];

        if ( data.length === 0 ) {
            resetStationSelect('No polling units found');
            return;
        }

        data.forEach(station => {
            const option = document.createElement('option');
            option.value = station.id;
            option.textContent = station.title;
            stationSelect.appendChild(option);

            if ( station.lat !== null && station.lng !== null ) {
                markers.push({ id: station.id, title: station.title, lat: station.lat, lng: station.lng });
            }
        });

        if ( submitBtn ) submitBtn.disabled = false;

        if ( typeof L !== 'undefined' ) {
            initFrontendMap( markers );
        }
    }

    function resetSelect(selectEl, placeholder) {
        if ( ! selectEl ) return;
        selectEl.innerHTML = '<option value="">' + placeholder + '</option>';
    }

    function resetStationSelect(placeholder) {
        resetSelect(stationSelect, placeholder);
        if ( submitBtn ) submitBtn.disabled = true;
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

        // Show loading state (button spinner + loading label)
        submitBtn.disabled = true;
        const btnSpinner = submitBtn.querySelector('.pm-spinner');
        if ( btnSpinner ) btnSpinner.style.display = 'inline-block';
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
                // subtle success animation
                form.classList.add('pm-fade');
                setTimeout(() => form.classList.remove('pm-fade'), 800);
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
            const btnSpinner = submitBtn.querySelector('.pm-spinner');
            if ( btnSpinner ) btnSpinner.style.display = 'none';
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
