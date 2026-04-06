document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('pollmonitor-map');
    const stationsCountEl = document.getElementById('pollmonitor-total-stations');
    const incidentsCountEl = document.getElementById('pollmonitor-total-incidents');
    const recentBodyEl = document.getElementById('pollmonitor-recent-incidents-body');
    const alertEl = document.getElementById('pollmonitor-realtime-alert');
    let dashboardEventSource = null;
    let lastEventId = 0;

    if ( ! mapContainer ) {
        return;
    }

    const map = L.map('pollmonitor-map').setView([9.0765, 7.3986], 6);
    const stationLayer = L.layerGroup().addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    fetchStationsForMap();
    connectDashboardStream();

    function fetchStationsForMap() {
        fetch(pollmonitorApiSettings.root + 'pollmonitor/v1/stations', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': pollmonitorApiSettings.nonce
            }
        })
        .then(response => response.json())
        .then(data => {
            stationLayer.clearLayers();

            if (data && data.length) {
                const markers = [];
                data.forEach(station => {
                    if ( station.lat && station.lng ) {
                        const marker = L.marker([station.lat, station.lng]).addTo(stationLayer);
                        marker.bindPopup('<b>' + escapeHtml(station.title) + '</b><br>ID: ' + station.id);
                        markers.push([station.lat, station.lng]);
                    }
                });

                if (markers.length > 0) {
                    map.fitBounds(markers);
                }
            }
        })
        .catch(error => {
            console.error('Error fetching stations for map:', error);
        });
    }

    function connectDashboardStream() {
        if ( typeof window.EventSource === 'undefined' ) {
            return;
        }

        const streamUrl = pollmonitorApiSettings.ajaxUrl +
            '?action=pollmonitor_dashboard_stream&_ajax_nonce=' +
            encodeURIComponent(pollmonitorApiSettings.streamNonce) +
            '&last_event_id=' + encodeURIComponent(lastEventId);

        dashboardEventSource = new EventSource(streamUrl);

        dashboardEventSource.addEventListener('dashboard-update', function(event) {
            let payload = {};

            try {
                payload = JSON.parse(event.data || '{}');
            } catch (parseError) {
                console.error('Unable to parse dashboard event payload:', parseError);
                return;
            }

            if ( payload.id ) {
                lastEventId = payload.id;
            }

            showRealtimeAlert(payload.message || 'PollMonitor dashboard updated.');
            fetchDashboardSnapshot();
        });

        dashboardEventSource.onerror = function() {
            if ( dashboardEventSource ) {
                dashboardEventSource.close();
            }

            setTimeout(connectDashboardStream, 3000);
        };
    }

    function fetchDashboardSnapshot() {
        const snapshotUrl = pollmonitorApiSettings.ajaxUrl +
            '?action=pollmonitor_dashboard_snapshot&_ajax_nonce=' +
            encodeURIComponent(pollmonitorApiSettings.snapshotNonce);

        fetch(snapshotUrl, {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(payload => {
            if ( ! payload || ! payload.success || ! payload.data ) {
                throw new Error('Invalid dashboard snapshot response');
            }

            const snapshot = payload.data;
            updateCounts(snapshot.counts || {});
            updateRecentIncidents(snapshot.recent_incidents || []);
        })
        .catch(error => {
            console.error('Failed to refresh dashboard snapshot:', error);
        });
    }

    function updateCounts(counts) {
        if ( stationsCountEl && typeof counts.stations !== 'undefined' ) {
            stationsCountEl.textContent = counts.stations;
        }

        if ( incidentsCountEl && typeof counts.incidents !== 'undefined' ) {
            incidentsCountEl.textContent = counts.incidents;
        }
    }

    function updateRecentIncidents(items) {
        if ( ! recentBodyEl ) {
            return;
        }

        recentBodyEl.innerHTML = '';

        if ( ! items.length ) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="3">No recent incidents found.</td>';
            recentBodyEl.appendChild(emptyRow);
            return;
        }

        items.forEach(item => {
            const row = document.createElement('tr');
            const title = escapeHtml(item.title || 'Untitled');
            const editLink = item.edit_link ? String(item.edit_link) : '#';
            const date = escapeHtml(item.date || '');
            const status = escapeHtml(item.status || 'pending');

            row.innerHTML =
                '<td><strong><a href="' + editLink + '">' + title + '</a></strong></td>' +
                '<td>' + date + '</td>' +
                '<td><span class="status-' + status + '">' + status + '</span></td>';
            recentBodyEl.appendChild(row);
        });
    }

    function showRealtimeAlert(message) {
        if ( ! alertEl ) {
            return;
        }

        alertEl.style.display = 'block';
        alertEl.innerHTML = '<p>' + escapeHtml(message) + '</p>';

        window.clearTimeout(showRealtimeAlert.timeoutId);
        showRealtimeAlert.timeoutId = window.setTimeout(function() {
            alertEl.style.display = 'none';
        }, 8000);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function(character) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            })[character];
        });
    }
});
