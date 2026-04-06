document.addEventListener('DOMContentLoaded', function() {
    const listEl = document.getElementById('pm-incident-list');
    const paginationEl = document.getElementById('pm-pagination');
    const modal = document.getElementById('pm-incident-modal');
    const modalContent = document.getElementById('pm-modal-content');
    const categorySelect = document.getElementById('pm-filter-category');
    const severitySelect = document.getElementById('pm-filter-severity');
    const searchInput = document.getElementById('pm-search');
    const resetBtn = document.getElementById('pm-filter-reset');
    const liveToggle = document.getElementById('pm-live-toggle');
    const statusBar = document.getElementById('pm-status-bar');
    const lastRefreshEl = document.getElementById('pm-last-refresh');
    const spotlightContentEl = document.getElementById('pm-spotlight-content');
    const spotlightLabelEl = document.getElementById('pm-spotlight-label');
    const statVisibleEl = document.getElementById('pm-stat-visible');
    const statPageEl = document.getElementById('pm-stat-page');
    const statFilterEl = document.getElementById('pm-stat-filter');
    const resultsSummaryEl = document.getElementById('pm-results-summary');

    if (!listEl) {
        return;
    }

    let page = 1;
    let totalPages = 0;
    const perPage = 6;
    let stationMap = {};
    let categoryMap = {};
    let severityMap = {};
    let selectedCategory = '';
    let selectedSeverity = '';
    let selectedStatus = '';
    let currentSearch = '';
    let currentItems = [];
    let filteredItems = [];
    let activeIncidentId = null;
    let liveRefreshHandle = null;

    const mapState = {
        map: null,
        layer: null,
        clusterGroup: null,
        markersByIncident: {},
    };

    loadInitialData();

    function loadInitialData() {
        Promise.all([loadCategories(), loadSeverities(), loadStations()]).then(function() {
            bindControls();
            loadPage(1);
        });
    }

    function bindControls() {
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                selectedCategory = this.value;
                loadPage(1);
            });
        }

        if (severitySelect) {
            severitySelect.addEventListener('change', function() {
                selectedSeverity = this.value;
                loadPage(1);
            });
        }

        if (searchInput) {
            let debounceHandle = null;
            searchInput.addEventListener('input', function() {
                window.clearTimeout(debounceHandle);
                debounceHandle = window.setTimeout(function() {
                    currentSearch = searchInput.value.trim();
                    loadPage(1);
                }, 250);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                selectedCategory = '';
                selectedSeverity = '';
                selectedStatus = '';
                currentSearch = '';
                if (categorySelect) {
                    categorySelect.value = '';
                }
                if (severitySelect) {
                    severitySelect.value = '';
                }
                if (searchInput) {
                    searchInput.value = '';
                }
                updateStatusChips();
                loadPage(1);
            });
        }

        if (statusBar) {
            statusBar.querySelectorAll('.pm-status-chip').forEach(function(button) {
                button.addEventListener('click', function() {
                    selectedStatus = this.getAttribute('data-status') || '';
                    updateStatusChips();
                    loadPage(1);
                });
            });
        }

        if (liveToggle) {
            liveToggle.addEventListener('change', syncLiveMode);
        }
        syncLiveMode();

        if (modal) {
            modal.addEventListener('click', function(event) {
                if (event.target === modal || event.target.classList.contains('pm-modal-close')) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal && modal.getAttribute('aria-hidden') === 'false') {
                closeModal();
            }
        });
    }

    function syncLiveMode() {
        if (liveRefreshHandle) {
            window.clearInterval(liveRefreshHandle);
            liveRefreshHandle = null;
        }

        if (liveToggle && liveToggle.checked) {
            liveRefreshHandle = window.setInterval(function() {
                loadPage(page);
            }, 45000);
        }
    }

    function loadStations() {
        return fetchJson('pollmonitor/v1/stations?per_page=200')
            .then(function(data) {
                stationMap = {};
                if (Array.isArray(data)) {
                    data.forEach(function(station) {
                        stationMap[String(station.id)] = station;
                    });
                }
            })
            .catch(function(error) {
                console.error('Error loading stations for incidents list:', error);
            });
    }

    function loadCategories() {
        if (!categorySelect) {
            return Promise.resolve();
        }

        return fetchJson('wp/v2/issue_category?per_page=100')
            .then(function(list) {
                categoryMap = {};
                if (Array.isArray(list)) {
                    list.forEach(function(term) {
                        categoryMap[String(term.id)] = term.name;
                        const option = document.createElement('option');
                        option.value = term.id;
                        option.textContent = term.name;
                        categorySelect.appendChild(option);
                    });
                }
            })
            .catch(function(error) {
                console.error('Failed to load categories', error);
            });
    }

    function loadSeverities() {
        if (!severitySelect) {
            return Promise.resolve();
        }

        return fetchJson('wp/v2/severity?per_page=100')
            .then(function(list) {
                severityMap = {};
                if (Array.isArray(list)) {
                    list.forEach(function(term) {
                        severityMap[String(term.id)] = term.name;
                        const option = document.createElement('option');
                        option.value = term.id;
                        option.textContent = term.name;
                        severitySelect.appendChild(option);
                    });
                }
            })
            .catch(function(error) {
                console.error('Failed to load severities', error);
            });
    }

    function loadPage(nextPage) {
        page = nextPage || 1;
        showSkeletonCards();
        updateStats(0);

        let url = 'wp/v2/incident_report?per_page=' + perPage + '&page=' + page + '&_embed';
        if (selectedCategory) {
            url += '&issue_category=' + encodeURIComponent(selectedCategory);
        }
        if (selectedSeverity) {
            url += '&severity=' + encodeURIComponent(selectedSeverity);
        }
        if (currentSearch) {
            url += '&search=' + encodeURIComponent(currentSearch);
        }

        fetch(pollmonitorApiSettings.root + url)
            .then(function(response) {
                return response.json().then(function(body) {
                    return {
                        status: response.status,
                        body: body,
                        headers: response.headers,
                    };
                });
            })
            .then(function(result) {
                if (result.status >= 200 && result.status < 300) {
                    currentItems = Array.isArray(result.body) ? result.body : [];
                    filteredItems = applyClientFilters(currentItems);
                    totalPages = parseInt(result.headers.get('X-WP-TotalPages') || 0, 10) || 0;
                    renderList(filteredItems);
                    renderSpotlight(filteredItems);
                    renderPagination();
                    addIncidentsToMap(filteredItems);
                    updateStats(filteredItems.length);
                    updateRefreshStamp();
                } else {
                    renderEmptyState('Unable to load reports.', 'Try again in a moment.');
                }
            })
            .catch(function(error) {
                console.error('Error loading incidents', error);
                renderEmptyState('Error loading reports.', 'Please refresh the page and try again.');
            });
    }

    function renderList(items) {
        if (!items || items.length === 0) {
            renderEmptyState('No reports match your filters.', 'Try broadening the search or clearing the category filter.');
            return;
        }

        listEl.innerHTML = '';
        items.forEach(function(item) {
            const card = document.createElement('article');
            card.className = 'pm-incident-card pm-animate';
            card.dataset.incidentId = item.id;

            const stationInfo = getStationInfo(item);
            const categoryName = getCategoryLabel(item);
            const severityName = getSeverityLabel(item);
            const thumbnailUrl = getThumbnailUrl(item);
            const title = getRenderedText(item.title, 'Untitled Report');
            const excerptHtml = getRenderedHtml(item.excerpt, '<p>No summary available yet.</p>');
            const status = item.status || 'pending';
            const timeAgo = formatRelativeTime(item.date);
            const fullDate = formatAbsoluteDate(item.date);

            const cover = document.createElement('div');
            cover.className = 'pm-card-cover';
            if (thumbnailUrl) {
                const img = document.createElement('img');
                img.className = 'pm-incident-thumb';
                img.src = thumbnailUrl;
                img.alt = title;
                cover.appendChild(img);
            }

            const statusBadge = document.createElement('span');
            statusBadge.className = 'pm-card-status is-' + sanitizeToken(status);
            statusBadge.textContent = formatStatus(status);
            cover.appendChild(statusBadge);

            const body = document.createElement('div');
            body.className = 'pm-card-body';

            const categoryChip = document.createElement('span');
            categoryChip.className = 'pm-card-category';
            categoryChip.textContent = categoryName;

            const severityChip = document.createElement('span');
            severityChip.className = 'pm-card-category pm-card-category-severity';
            severityChip.textContent = severityName;

            const chipRow = document.createElement('div');
            chipRow.className = 'pm-chip-row';
            chipRow.appendChild(categoryChip);
            chipRow.appendChild(severityChip);

            const heading = document.createElement('h3');
            heading.className = 'pm-card-title';
            heading.textContent = title;

            const meta = document.createElement('div');
            meta.className = 'pm-card-meta';
            meta.innerHTML =
                '<div><strong>Reported:</strong> ' + escapeHtml(timeAgo) + '</div>' +
                '<div><strong>Timestamp:</strong> ' + escapeHtml(fullDate) + '</div>' +
                '<div><strong>Polling Unit:</strong> ' + escapeHtml(stationInfo.title) + '</div>' +
                '<div><strong>Severity:</strong> ' + escapeHtml(severityName) + '</div>';

            const excerpt = document.createElement('div');
            excerpt.className = 'pm-card-excerpt';
            excerpt.innerHTML = excerptHtml;

            const actions = document.createElement('div');
            actions.className = 'pm-card-actions';

            const viewBtn = document.createElement('button');
            viewBtn.type = 'button';
            viewBtn.className = 'pm-primary-btn';
            viewBtn.textContent = 'Open Report';
            viewBtn.addEventListener('click', function() {
                openModal(item);
            });

            const mapBtn = document.createElement('button');
            mapBtn.type = 'button';
            mapBtn.className = 'pm-secondary-btn';
            mapBtn.textContent = 'Locate on Map';
            mapBtn.addEventListener('click', function() {
                focusIncidentOnMap(item.id);
            });

            actions.appendChild(viewBtn);
            actions.appendChild(mapBtn);

            body.appendChild(chipRow);
            body.appendChild(heading);
            body.appendChild(meta);
            body.appendChild(excerpt);
            body.appendChild(actions);

            card.appendChild(cover);
            card.appendChild(body);

            card.addEventListener('click', function(event) {
                if (event.target.closest('button')) {
                    return;
                }
                openModal(item);
            });

            listEl.appendChild(card);
            requestAnimationFrame(function() {
                card.classList.add('pm-enter');
                window.setTimeout(function() {
                    card.classList.remove('pm-animate');
                    card.classList.remove('pm-enter');
                }, 320);
            });
        });

        highlightActiveCard();
    }

    function renderPagination() {
        paginationEl.innerHTML = '';

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.textContent = 'Previous';
        prev.disabled = page <= 1;
        prev.addEventListener('click', function() {
            if (page > 1) {
                loadPage(page - 1);
            }
        });

        const indicator = document.createElement('span');
        indicator.className = 'pm-page-indicator';
        indicator.textContent = 'Page ' + page + (totalPages ? ' of ' + totalPages : '');

        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = 'Next';
        next.disabled = totalPages > 0 ? page >= totalPages : false;
        next.addEventListener('click', function() {
            if (!next.disabled) {
                loadPage(page + 1);
            }
        });

        paginationEl.appendChild(prev);
        paginationEl.appendChild(indicator);
        paginationEl.appendChild(next);
    }

    function renderSpotlight(items) {
        if (!spotlightContentEl || !spotlightLabelEl) {
            return;
        }

        if (!items || !items.length) {
            spotlightLabelEl.textContent = 'No incidents in view';
            spotlightContentEl.innerHTML =
                '<div class="pm-spotlight-empty"><strong>No priority incident to highlight.</strong><span>Adjust the active filters or wait for new reports.</span></div>';
            return;
        }

        const spotlightItem = chooseSpotlightIncident(items);
        const stationInfo = getStationInfo(spotlightItem);
        const title = getRenderedText(spotlightItem.title, 'Untitled Report');
        const excerpt = getRenderedText(spotlightItem.excerpt, 'No summary available yet.');
        const severityName = getSeverityLabel(spotlightItem);
        const categoryName = getCategoryLabel(spotlightItem);
        const thumbnailUrl = getThumbnailUrl(spotlightItem);
        const spotlightTone = getSeverityTone(severityName);
        const spotlightReason = getSpotlightReason(spotlightItem, severityName);

        spotlightLabelEl.textContent = spotlightReason;
        spotlightContentEl.innerHTML =
            '<article class="pm-spotlight-card pm-tone-' + escapeHtml(spotlightTone) + '">' +
                '<div class="pm-spotlight-media">' +
                    (thumbnailUrl ? '<img src="' + escapeHtml(thumbnailUrl) + '" alt="' + escapeHtml(title) + '" class="pm-spotlight-image">' : '<div class="pm-spotlight-image pm-spotlight-placeholder"></div>') +
                    '<span class="pm-card-status is-' + sanitizeToken(spotlightItem.status || 'pending') + '">' + escapeHtml(formatStatus(spotlightItem.status || 'pending')) + '</span>' +
                '</div>' +
                '<div class="pm-spotlight-body">' +
                    '<div class="pm-chip-row">' +
                        '<span class="pm-card-category">' + escapeHtml(categoryName) + '</span>' +
                        '<span class="pm-card-category pm-card-category-severity">' + escapeHtml(severityName) + '</span>' +
                    '</div>' +
                    '<h4 class="pm-spotlight-title">' + escapeHtml(title) + '</h4>' +
                    '<p class="pm-spotlight-copy">' + escapeHtml(excerpt) + '</p>' +
                    '<div class="pm-spotlight-meta">' +
                        '<span><strong>Polling Unit:</strong> ' + escapeHtml(stationInfo.title) + '</span>' +
                        '<span><strong>Reported:</strong> ' + escapeHtml(formatAbsoluteDate(spotlightItem.date)) + '</span>' +
                    '</div>' +
                    '<div class="pm-card-actions">' +
                        '<button type="button" class="pm-primary-btn" id="pm-spotlight-open">Open Spotlight</button>' +
                        '<button type="button" class="pm-secondary-btn" id="pm-spotlight-locate">Locate on Map</button>' +
                    '</div>' +
                '</div>' +
            '</article>';

        const openBtn = document.getElementById('pm-spotlight-open');
        if (openBtn) {
            openBtn.addEventListener('click', function() {
                openModal(spotlightItem);
            });
        }

        const locateBtn = document.getElementById('pm-spotlight-locate');
        if (locateBtn) {
            locateBtn.addEventListener('click', function() {
                focusIncidentOnMap(spotlightItem.id);
            });
        }
    }

    function openModal(item) {
        const title = getRenderedText(item.title, 'Untitled Report');
        const contentHtml = getRenderedHtml(item.content, '<p>No details available.</p>');
        const categoryName = getCategoryLabel(item);
        const severityName = getSeverityLabel(item);
        const status = formatStatus(item.status || 'pending');
        const stationInfo = getStationInfo(item);
        const imageUrl = getThumbnailUrl(item);

        modalContent.innerHTML = '';

        const layout = document.createElement('div');
        layout.className = 'pm-modal-layout';

        const main = document.createElement('div');
        main.className = 'pm-modal-main';

        const sidebar = document.createElement('aside');
        sidebar.className = 'pm-modal-sidebar';

        const eyebrow = document.createElement('p');
        eyebrow.className = 'pm-eyebrow';
        eyebrow.textContent = categoryName;

        const modalTitle = document.createElement('h2');
        modalTitle.className = 'pm-modal-title';
        modalTitle.id = 'pm-modal-title';
        modalTitle.textContent = title;

        main.appendChild(eyebrow);
        main.appendChild(modalTitle);

        if (imageUrl) {
            const imageWrap = document.createElement('div');
            imageWrap.className = 'pm-modal-image-wrap';
            const img = document.createElement('img');
            img.className = 'pm-modal-image';
            img.src = imageUrl;
            img.alt = title;
            imageWrap.appendChild(img);
            main.appendChild(imageWrap);
        }

        const copy = document.createElement('div');
        copy.className = 'pm-modal-copy';
        copy.innerHTML = contentHtml;
        main.appendChild(copy);

        sidebar.innerHTML =
            buildMetaCard('Status', status) +
            buildMetaCard('Severity', severityName) +
            buildMetaCard('Reported', formatAbsoluteDate(item.date)) +
            buildMetaCard('Polling Unit', stationInfo.title) +
            buildMetaCard('Station ID', stationInfo.id ? String(stationInfo.id) : 'Unknown');

        layout.appendChild(main);
        layout.appendChild(sidebar);
        modalContent.appendChild(layout);

        modal.setAttribute('aria-hidden', 'false');
        activeIncidentId = item.id;
        highlightActiveCard();
        focusIncidentOnMap(item.id, false);

        const closeButton = modal.querySelector('.pm-modal-close');
        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        activeIncidentId = null;
        highlightActiveCard();
    }

    function showSkeletonCards() {
        listEl.innerHTML = '';
        for (let index = 0; index < Math.min(perPage, 4); index++) {
            const card = document.createElement('div');
            card.className = 'pm-skeleton-card';
            listEl.appendChild(card);
        }
        if (resultsSummaryEl) {
            resultsSummaryEl.textContent = 'Loading reports...';
        }
    }

    function renderEmptyState(title, message) {
        listEl.innerHTML = '';
        const state = document.createElement('div');
        state.className = 'pm-empty-state';
        state.innerHTML = '<strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(message) + '</span>';
        listEl.appendChild(state);
        paginationEl.innerHTML = '';
        renderSpotlight([]);
        addIncidentsToMap([]);
        updateStats(0);
    }

    function updateStats(count) {
        if (statVisibleEl) {
            statVisibleEl.textContent = String(count);
        }
        if (statPageEl) {
            statPageEl.textContent = String(page);
        }
        if (statFilterEl) {
            const filterParts = [];
            if (selectedCategory) {
                filterParts.push(categoryMap[String(selectedCategory)] || 'Category');
            }
            if (selectedSeverity) {
                filterParts.push(severityMap[String(selectedSeverity)] || 'Severity');
            }
            if (selectedStatus) {
                filterParts.push(formatStatus(selectedStatus));
            }
            statFilterEl.textContent = filterParts.length ? filterParts.join(' / ') : 'All';
        }
        if (resultsSummaryEl) {
            const searchLabel = currentSearch ? ' matching "' + currentSearch + '"' : '';
            const liveLabel = liveToggle && liveToggle.checked ? ' Live mode on.' : '';
            resultsSummaryEl.textContent = count + ' report' + (count === 1 ? '' : 's') + ' on this page' + searchLabel + '.' + liveLabel;
        }
    }

    function updateRefreshStamp() {
        if (!lastRefreshEl) {
            return;
        }

        lastRefreshEl.textContent = 'Last refreshed ' + new Date().toLocaleTimeString();
    }

    function initListMap() {
        const mapEl = document.getElementById('pm-incidents-map');
        if (!mapEl || typeof L === 'undefined') {
            return null;
        }

        if (mapState.map) {
            return mapState.map;
        }

        mapState.map = L.map('pm-incidents-map').setView([9.0765, 7.3986], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap contributors'
        }).addTo(mapState.map);

        mapState.layer = L.layerGroup().addTo(mapState.map);
        if (typeof L.markerClusterGroup === 'function') {
            mapState.clusterGroup = L.markerClusterGroup({
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                maxClusterRadius: 48,
                iconCreateFunction: function(cluster) {
                    const children = cluster.getAllChildMarkers();
                    const tone = getClusterTone(children);
                    const count = cluster.getChildCount();
                    return L.divIcon({
                        html: '<span>' + count + '</span>',
                        className: 'pm-cluster-marker pm-cluster-' + tone,
                        iconSize: [42, 42],
                    });
                },
            });
            mapState.map.addLayer(mapState.clusterGroup);
        }
        return mapState.map;
    }

    function addIncidentsToMap(items) {
        const map = initListMap();
        if (!map || !mapState.layer) {
            return;
        }

        mapState.layer.clearLayers();
        if (mapState.clusterGroup) {
            mapState.clusterGroup.clearLayers();
        }
        mapState.markersByIncident = {};

        const bounds = [];
        items.forEach(function(item) {
            const stationInfo = getStationInfo(item);
            if (!stationInfo.id || stationInfo.lat === null || stationInfo.lng === null) {
                return;
            }

            const marker = L.marker([stationInfo.lat, stationInfo.lng], {
                icon: createIncidentMarkerIcon(item),
            });
            marker.bindPopup(
                '<strong>' + escapeHtml(getRenderedText(item.title, 'Untitled Report')) + '</strong>' +
                '<br>' + escapeHtml(stationInfo.title) +
                '<br><button type="button" class="pm-view-incident" data-id="' + item.id + '">Open Report</button>'
            );

            marker.on('click', function() {
                activeIncidentId = item.id;
                highlightActiveCard();
            });

            mapState.markersByIncident[String(item.id)] = marker;
            if (mapState.clusterGroup) {
                mapState.clusterGroup.addLayer(marker);
            } else {
                marker.addTo(mapState.layer);
            }
            bounds.push([stationInfo.lat, stationInfo.lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [32, 32] });
        }

        map.off('popupopen');
        map.on('popupopen', function(event) {
            const popupNode = event.popup.getElement();
            if (!popupNode) {
                return;
            }

            popupNode.querySelectorAll('.pm-view-incident').forEach(function(button) {
                button.addEventListener('click', function() {
                    const incidentId = parseInt(this.getAttribute('data-id'), 10);
                    const incident = currentItems.find(function(item) {
                        return item.id === incidentId;
                    });
                    if (incident) {
                        openModal(incident);
                    }
                });
            });
        });

        highlightActiveCard();
    }

    function focusIncidentOnMap(incidentId, openPopup = true) {
        const marker = mapState.markersByIncident[String(incidentId)];
        if (!marker || !mapState.map) {
            return;
        }

        activeIncidentId = incidentId;
        highlightActiveCard();
        if (mapState.clusterGroup && typeof mapState.clusterGroup.zoomToShowLayer === 'function') {
            mapState.clusterGroup.zoomToShowLayer(marker, function() {
                mapState.map.flyTo(marker.getLatLng(), Math.max(mapState.map.getZoom(), 11), {
                    duration: 0.6,
                });
                if (openPopup) {
                    marker.openPopup();
                }
            });
            return;
        }

        mapState.map.flyTo(marker.getLatLng(), Math.max(mapState.map.getZoom(), 11), {
            duration: 0.6,
        });

        if (openPopup) {
            marker.openPopup();
        }
    }

    function highlightActiveCard() {
        listEl.querySelectorAll('.pm-incident-card').forEach(function(card) {
            card.classList.toggle('is-active', Number(card.dataset.incidentId) === Number(activeIncidentId));
        });
    }

    function getStationInfo(item) {
        let stationId = '';
        if (item.meta && item.meta.pollmonitor_station_id) {
            stationId = String(item.meta.pollmonitor_station_id);
        }

        const station = stationMap[stationId];
        if (station) {
            return {
                id: station.id,
                title: station.title,
                lat: station.lat,
                lng: station.lng,
            };
        }

        return {
            id: stationId,
            title: stationId ? 'Polling Unit #' + stationId : 'Unassigned polling unit',
            lat: null,
            lng: null,
        };
    }

    function getCategoryLabel(item) {
        if (Array.isArray(item.issue_category) && item.issue_category.length) {
            const firstCategory = String(item.issue_category[0]);
            if (categoryMap[firstCategory]) {
                return categoryMap[firstCategory];
            }
        }
        return 'Field Report';
    }

    function chooseSpotlightIncident(items) {
        const ranked = items.slice().sort(function(left, right) {
            const scoreDifference = getIncidentPriorityScore(right) - getIncidentPriorityScore(left);
            if (scoreDifference !== 0) {
                return scoreDifference;
            }
            return new Date(right.date).getTime() - new Date(left.date).getTime();
        });

        return ranked[0];
    }

    function getIncidentPriorityScore(item) {
        const severityName = getSeverityLabel(item).toLowerCase();
        const status = String(item.status || '').toLowerCase();
        let score = 0;

        if (status === 'pending') {
            score += 30;
        } else if (status === 'publish') {
            score += 18;
        } else {
            score += 8;
        }

        score += getSeverityWeight(severityName);
        score += Math.max(0, 10 - ageInHours(item.date));

        return score;
    }

    function getSeverityWeight(severityName) {
        if (severityName.indexOf('critical') !== -1) {
            return 50;
        }
        if (severityName.indexOf('high') !== -1) {
            return 40;
        }
        if (severityName.indexOf('medium') !== -1 || severityName.indexOf('moderate') !== -1) {
            return 24;
        }
        if (severityName.indexOf('low') !== -1) {
            return 12;
        }
        if (severityName.indexOf('unclassified') !== -1) {
            return 10;
        }
        return 18;
    }

    function ageInHours(dateString) {
        const timestamp = new Date(dateString).getTime();
        if (isNaN(timestamp)) {
            return 999;
        }
        return Math.abs(Date.now() - timestamp) / 3600000;
    }

    function getSpotlightReason(item, severityName) {
        const status = String(item.status || 'pending').toLowerCase();
        if (severityName.toLowerCase().indexOf('critical') !== -1 || severityName.toLowerCase().indexOf('high') !== -1) {
            return 'Highest priority by severity';
        }
        if (status === 'pending') {
            return 'Newest pending report';
        }
        return 'Most recent incident in view';
    }

    function getSeverityTone(severityName) {
        const name = String(severityName || '').toLowerCase();
        if (name.indexOf('critical') !== -1 || name.indexOf('high') !== -1) {
            return 'danger';
        }
        if (name.indexOf('medium') !== -1 || name.indexOf('moderate') !== -1) {
            return 'warning';
        }
        if (name.indexOf('low') !== -1) {
            return 'calm';
        }
        return 'neutral';
    }

    function createIncidentMarkerIcon(item) {
        const severityTone = getSeverityTone(getSeverityLabel(item));
        const status = sanitizeToken(item.status || 'pending');
        return L.divIcon({
            html: '<span class="pm-marker-core"></span>',
            className: 'pm-incident-marker pm-marker-' + severityTone + ' pm-marker-status-' + status,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
            popupAnchor: [0, -12],
        });
    }

    function getClusterTone(markers) {
        let hasDanger = false;
        let hasWarning = false;

        markers.forEach(function(marker) {
            const className = marker.options.icon && marker.options.icon.options ? marker.options.icon.options.className : '';
            if (className.indexOf('pm-marker-danger') !== -1) {
                hasDanger = true;
            } else if (className.indexOf('pm-marker-warning') !== -1) {
                hasWarning = true;
            }
        });

        if (hasDanger) {
            return 'danger';
        }
        if (hasWarning) {
            return 'warning';
        }
        return 'calm';
    }

    function getSeverityLabel(item) {
        if (Array.isArray(item.severity) && item.severity.length) {
            const firstSeverity = String(item.severity[0]);
            if (severityMap[firstSeverity]) {
                return severityMap[firstSeverity];
            }
        }
        return 'Unclassified';
    }

    function getThumbnailUrl(item) {
        if (
            item._embedded &&
            item._embedded['wp:featuredmedia'] &&
            item._embedded['wp:featuredmedia'][0] &&
            item._embedded['wp:featuredmedia'][0].source_url
        ) {
            return item._embedded['wp:featuredmedia'][0].source_url;
        }

        return '';
    }

    function getRenderedText(field, fallback) {
        if (field && typeof field.rendered === 'string' && field.rendered.trim()) {
            const parser = document.createElement('div');
            parser.innerHTML = field.rendered;
            return parser.textContent.trim() || fallback;
        }
        return fallback;
    }

    function getRenderedHtml(field, fallback) {
        if (field && typeof field.rendered === 'string' && field.rendered.trim()) {
            return field.rendered;
        }
        return fallback;
    }

    function buildMetaCard(label, value) {
        return '<div class="pm-modal-meta-item"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
    }

    function formatStatus(status) {
        const clean = String(status || 'pending').replace(/[_-]+/g, ' ');
        return clean.charAt(0).toUpperCase() + clean.slice(1);
    }

    function applyClientFilters(items) {
        return (items || []).filter(function(item) {
            if (selectedStatus && String(item.status || '') !== selectedStatus) {
                return false;
            }
            return true;
        });
    }

    function updateStatusChips() {
        if (!statusBar) {
            return;
        }

        statusBar.querySelectorAll('.pm-status-chip').forEach(function(button) {
            const isActive = (button.getAttribute('data-status') || '') === selectedStatus;
            button.classList.toggle('is-active', isActive);
        });
    }

    function formatAbsoluteDate(dateString) {
        const date = new Date(dateString);
        return isNaN(date.getTime()) ? 'Unknown time' : date.toLocaleString();
    }

    function formatRelativeTime(dateString) {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return 'Unknown time';
        }

        const seconds = Math.round((Date.now() - date.getTime()) / 1000);
        const intervals = [
            { limit: 60, label: 'second' },
            { limit: 3600, label: 'minute', divisor: 60 },
            { limit: 86400, label: 'hour', divisor: 3600 },
            { limit: 604800, label: 'day', divisor: 86400 },
        ];

        for (let index = 0; index < intervals.length; index++) {
            const interval = intervals[index];
            if (seconds < interval.limit) {
                const value = interval.divisor ? Math.max(1, Math.floor(seconds / interval.divisor)) : Math.max(1, seconds);
                return value + ' ' + interval.label + (value === 1 ? '' : 's') + ' ago';
            }
        }

        return date.toLocaleDateString();
    }

    function sanitizeToken(value) {
        return String(value || '').replace(/[^a-z0-9_-]/gi, '').toLowerCase();
    }

    function fetchJson(path) {
        return fetch(pollmonitorApiSettings.root + path)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function(character) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[character];
        });
    }
});
