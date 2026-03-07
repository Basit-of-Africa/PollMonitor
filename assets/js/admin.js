document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('pollmonitor-map');
    
    if ( ! mapContainer ) {
        return;
    }

    // Initialize Leaflet Map centered on Nigeria (Abuja broadly)
    const map = L.map('pollmonitor-map').setView([9.0765, 7.3986], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Fetch stations and drop pins
    fetch(pollmonitorApiSettings.root + 'pollmonitor/v1/stations', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': pollmonitorApiSettings.nonce
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.length) {
            const markers = [];
            data.forEach(station => {
                if ( station.lat && station.lng ) {
                    const marker = L.marker([station.lat, station.lng]).addTo(map);
                    marker.bindPopup(`<b>${station.title}</b><br>ID: ${station.id}`);
                    markers.push([station.lat, station.lng]);
                }
            });
            
            // Auto-fit bounds if we have markers
            if (markers.length > 0) {
                map.fitBounds(markers);
            }
        }
    })
    .catch(error => {
        console.error('Error fetching stations for map:', error);
    });
});
