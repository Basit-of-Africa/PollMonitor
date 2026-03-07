document.addEventListener('DOMContentLoaded', function() {
    const listEl = document.getElementById('pm-incident-list');
    const paginationEl = document.getElementById('pm-pagination');
    const modal = document.getElementById('pm-incident-modal');
    const modalContent = document.getElementById('pm-modal-content');

    if (!listEl) return;

    let page = 1;
    const perPage = 6;

    // Load stations first for mapping, then load incidents
    let stationMap = {};
    function loadStations() {
        return fetch(pollmonitorApiSettings.root + 'pollmonitor/v1/stations')
            .then(r => r.json())
            .then(data => {
                if ( Array.isArray(data) ) {
                    data.forEach(s => {
                        stationMap[s.id] = s; // {id,title,lat,lng}
                    });
                }
            })
            .catch(err => {
                console.error('Error loading stations for incidents list:', err);
            });
    }

    // Load issue categories for filter
    const categorySelect = document.getElementById('pm-filter-category');
    let selectedCategory = '';
    function loadCategories() {
        if ( ! categorySelect ) return Promise.resolve();
        return fetch(pollmonitorApiSettings.root + 'wp/v2/issue_category?per_page=100')
            .then(r => r.json())
            .then(list => {
                if ( Array.isArray(list) ) {
                    list.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = t.name;
                        categorySelect.appendChild(opt);
                    });
                }
                categorySelect.addEventListener('change', function() {
                    selectedCategory = this.value;
                    loadPage(1);
                });
            })
            .catch(err => console.error('Failed to load categories', err));
    }

    function loadPage(p) {
        page = p || 1;
        // show skeleton placeholders while loading
        listEl.innerHTML = '';
        for (let i=0;i<Math.min(perPage,4);i++){
            const sk = document.createElement('div');
            sk.className = 'pm-skeleton-card';
            listEl.appendChild(sk);
        }

        // Ensure stations are loaded first
        loadStations().then(() => {
            // apply category filter if selected
            let url = pollmonitorApiSettings.root + 'wp/v2/incident_report?per_page=' + perPage + '&page=' + page + '&_embed';
            if ( selectedCategory ) {
                url += '&issue_category=' + encodeURIComponent( selectedCategory );
            }
            fetch(url)
            .then(r => r.json().then(body => ({ status: r.status, body, headers: r.headers }) ) )
            .then(({ status, body, headers }) => {
                if ( status >= 200 && status < 300 ) {
                    // handle total pages from header
                    try { totalPages = parseInt( headers.get('X-WP-TotalPages') || 0, 10 ); } catch(e) { totalPages = 0; }
                    renderList(body);
                    renderPagination();
                    addIncidentsToMap(body);
                } else {
                    listEl.innerHTML = '<p>Unable to load incidents.</p>';
                }
            })
            .catch(err => {
                console.error('Error loading incidents', err);
                listEl.innerHTML = '<p>Error loading incidents.</p>';
            });
        });
    }

    function renderList(items) {
        if (!items || items.length === 0) {
            listEl.innerHTML = '<p>No incidents found.</p>';
            return;
        }
        listEl.innerHTML = '';
        items.forEach(item => {
            const card = document.createElement('article');
            card.className = 'pm-incident-card pm-animate';

            // Thumbnail if available via _embedded
            let thumbUrl = null;
            if ( item._embedded && item._embedded['wp:featuredmedia'] && item._embedded['wp:featuredmedia'][0] && item._embedded['wp:featuredmedia'][0].source_url ) {
                thumbUrl = item._embedded['wp:featuredmedia'][0].source_url;
            }

            if ( thumbUrl ) {
                const img = document.createElement('img');
                img.className = 'pm-incident-thumb';
                img.src = thumbUrl;
                img.alt = item.title && item.title.rendered ? item.title.rendered : 'Incident image';
                card.appendChild(img);
            }

            const title = document.createElement('h3');
            title.textContent = item.title && item.title.rendered ? item.title.rendered : 'Untitled';

            const meta = document.createElement('div');
            meta.className = 'pm-meta';
            meta.textContent = (new Date(item.date)).toLocaleString();

            const excerpt = document.createElement('div');
            excerpt.className = 'pm-excerpt';
            excerpt.innerHTML = item.excerpt && item.excerpt.rendered ? item.excerpt.rendered : '';

            const actions = document.createElement('div');
            actions.className = 'pm-actions';
            const viewBtn = document.createElement('button');
            viewBtn.className = 'pm-btn-small';
            viewBtn.textContent = 'View';
            viewBtn.addEventListener('click', function() {
                openModal(item);
            });

            actions.appendChild(viewBtn);

            card.appendChild(title);
            card.appendChild(meta);
            card.appendChild(excerpt);
            card.appendChild(actions);

            listEl.appendChild(card);
            // trigger enter animation
            requestAnimationFrame(() => {
                card.classList.add('pm-enter');
                setTimeout(()=>{ card.classList.remove('pm-animate'); card.classList.remove('pm-enter'); }, 320);
            });
        });
    }

    let totalPages = 0;
    function renderPagination() {
        paginationEl.innerHTML = '';
        const prev = document.createElement('button');
        prev.textContent = 'Previous';
        prev.disabled = page <= 1;
        prev.addEventListener('click', function() { if (page > 1) loadPage(page - 1); });

        const next = document.createElement('button');
        next.textContent = 'Next';
        next.disabled = totalPages > 0 ? page >= totalPages : false;
        next.addEventListener('click', function() { if (!next.disabled) loadPage(page + 1); });

        paginationEl.appendChild(prev);
        paginationEl.appendChild(next);
    }

    function openModal(item) {
        modalContent.innerHTML = '';
        const title = document.createElement('h2');
        title.innerHTML = item.title && item.title.rendered ? item.title.rendered : '';
        const date = document.createElement('div');
        date.className = 'pm-meta';
        date.textContent = (new Date(item.date)).toLocaleString();
        const content = document.createElement('div');
        content.innerHTML = item.content && item.content.rendered ? item.content.rendered : '';

        // show station info if available in meta
        let stationInfo = null;
        if ( item.meta && item.meta.pollmonitor_station_id ) {
            stationInfo = stationMap[ item.meta.pollmonitor_station_id ];
        }

        modalContent.appendChild(title);
        modalContent.appendChild(date);
        if ( stationInfo ) {
            const st = document.createElement('div');
            st.className = 'pm-meta';
            st.textContent = 'Station: ' + stationInfo.title + ' (ID: ' + stationInfo.id + ')';
            modalContent.appendChild(st);
        }
        modalContent.appendChild(content);

        modal.setAttribute('aria-hidden','false');
        modal.querySelector('.pm-modal-close').focus();
    }

    // Close handlers
    if ( modal ) {
        modal.addEventListener('click', function(e) {
            if ( e.target === modal || e.target.classList.contains('pm-modal-close') ) {
                modal.setAttribute('aria-hidden','true');
            }
        });
    }
    // Initialize a frontend map for incidents list
    function initListMap() {
        const mapEl = document.getElementById('pm-incidents-map');
        if ( ! mapEl || typeof L === 'undefined' ) return null;
        if ( window.pollmonitorIncidentsMap ) return window.pollmonitorIncidentsMap;
        window.pollmonitorIncidentsMap = L.map( 'pm-incidents-map' ).setView([9.0765, 7.3986], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap contributors'
        }).addTo(window.pollmonitorIncidentsMap);
        window.pollmonitorIncidentsLayer = L.layerGroup().addTo(window.pollmonitorIncidentsMap);
        return window.pollmonitorIncidentsMap;
    }

    function addIncidentsToMap( items ) {
        const map = initListMap();
        if ( ! map ) return;
        const layer = window.pollmonitorIncidentsLayer;
        layer.clearLayers();
        const bounds = [];

        items.forEach(item => {
            let station_id = null;
            if ( item.meta && item.meta.pollmonitor_station_id ) {
                station_id = item.meta.pollmonitor_station_id;
            }
            if ( station_id && stationMap[station_id] ) {
                const s = stationMap[station_id];
                const marker = L.marker([s.lat, s.lng]).addTo(layer);
                const popupHtml = '<strong>' + (item.title && item.title.rendered ? item.title.rendered : '') + '</strong><br/>' + (s.title || '') + '<br/><button data-id="' + item.id + '" class="pm-view-incident">View Incident</button>';
                marker.bindPopup(popupHtml);
                bounds.push([s.lat, s.lng]);
            }
        });

        if ( bounds.length > 0 ) {
            map.fitBounds(bounds, { padding: [40,40] });
        }

        // Delegate popup button clicks
        map.on('popupopen', function(e) {
            const popupNode = e.popup.getElement();
            if ( popupNode ) {
                popupNode.querySelectorAll('.pm-view-incident').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = parseInt(this.getAttribute('data-id'), 10);
                        // fetch detail and open modal
                        fetch(pollmonitorApiSettings.root + 'wp/v2/incident_report/' + id + '?_embed')
                            .then(r => r.json())
                            .then(data => openModal(data))
                            .catch(err => console.error('Failed to load incident', err));
                    });
                });
            }
        });
    }

    // Start: load categories first so filter is ready, then load page
    Promise.all([ loadCategories(), loadStations() ]).then(() => loadPage(1));
});
