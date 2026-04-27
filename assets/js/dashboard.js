(function(){
    function el(id){return document.getElementById(id);}

    function renderReports(reports){
        var container = el('pm-recent-reports');
        if(!container) return;
        if(!reports || !reports.length){ container.innerHTML = '<p class="pm-empty">No recent reports.</p>'; return; }
        container.innerHTML = reports.map(function(r){
            return '<div class="pm-report">'<
                + '<h4>' + escapeHtml(r.title || ('Report #' + r.id)) + '</h4>'
                + '<p class="pm-meta">' + (r.date ? new Date(r.date).toLocaleString() : '') + ' — ' + escapeHtml(r.author || '') + '</p>'
                + '<p>' + escapeHtml(r.content) + '</p>'
                + '</div>';
        }).join('');
    }

    function escapeHtml(s){ if(!s) return ''; return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function fetchRecent(){
        var url = (typeof pollmonitorDashboardSettings !== 'undefined' ? pollmonitorDashboardSettings.root : pollmonitorApiSettings.root) + 'pollmonitor/v1/incidents/recent?per_page=12';
        fetch(url, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(data){ if(Array.isArray(data)) renderReports(data); })
            .catch(function(){ console.error('Failed to fetch recent reports'); });
    }

    // Station autocomplete: search stations endpoint and show suggestions
    function searchStations(query, onResults){
        if(!query || query.length < 2) { onResults([]); return; }
        var url = (typeof pollmonitorApiSettings !== 'undefined' ? pollmonitorApiSettings.root : (typeof pollmonitorDashboardSettings !== 'undefined' ? pollmonitorDashboardSettings.root : '')) + 'pollmonitor/v1/stations?search=' + encodeURIComponent(query) + '&per_page=10';
        fetch(url, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(data){ if(Array.isArray(data)) onResults(data); else onResults([]); })
            .catch(function(){ onResults([]); });
    }

    function attachStationAutocomplete(){
        var input = document.getElementById('pm-station-search');
        var hidden = document.getElementById('pm-station-id');
        var list = document.getElementById('pm-station-suggestions');
        if(!input || !hidden || !list) return;

        input.addEventListener('input', function(){
            var q = input.value.trim();
            hidden.value = '';
            searchStations(q, function(items){
                list.innerHTML = items.map(function(it){ return '<li data-id="'+it.id+'">'+escapeHtml(it.title) + ' (ID:'+it.id+')</li>'; }).join('');
            });
        });

        list.addEventListener('click', function(e){
            var li = e.target.closest('li');
            if(!li) return;
            var id = li.getAttribute('data-id');
            hidden.value = id;
            input.value = li.textContent;
            list.innerHTML = '';
        });
    }

    function submitReport(e){
        e.preventDefault();
        var form = el('pm-submit-form');
        if(!form) return;
        var title = el('pm-title').value.trim();
        var content = el('pm-content').value.trim();
        var station_id = parseInt(el('pm-station-id').value,10) || 0;
        if(!title || !content || !station_id){ alert('Please enter title, content and select a station.'); return; }

        var payload = { title: title, content: content, station_id: station_id };
        var url = (typeof pollmonitorDashboardSettings !== 'undefined' ? pollmonitorDashboardSettings.root : pollmonitorApiSettings.root) + 'pollmonitor/v1/incidents';

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (typeof pollmonitorDashboardSettings !== 'undefined' ? pollmonitorDashboardSettings.nonce : pollmonitorApiSettings.nonce)
            },
            body: JSON.stringify(payload)
        }).then(function(res){
            if(!res.ok) return res.json().then(function(j){ throw j; });
            return res.json();
        }).then(function(data){
            el('pm-submit-status').textContent = 'Report submitted — ref #' + (data.id || 'n/a');
            form.reset();
            fetchRecent();
        }).catch(function(err){
            var msg = (err && err.message) ? err.message : 'Submit failed';
            el('pm-submit-status').textContent = msg;
        });
    }

    function init(){
        if(!el('pm-dashboard-root')) return;
        fetchRecent();
        var f = el('pm-submit-form'); if(f) f.addEventListener('submit', submitReport);
        attachStationAutocomplete();
        // Polling to refresh feed
        setInterval(fetchRecent, 30000);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
