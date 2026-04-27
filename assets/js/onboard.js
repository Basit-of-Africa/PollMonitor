(function(){
    function el(id){return document.getElementById(id);} 
    function qs(sel){return document.querySelector(sel);} 

    function submitOnboard(e){
        e.preventDefault();
        var first = el('pm-onboard-first').value.trim();
        var last = el('pm-onboard-last').value.trim();
        var email = el('pm-onboard-email').value.trim();
        var phone = el('pm-onboard-phone').value.trim();
        var stations = el('pm-onboard-stations').value.trim();
        if(!first || !email){ alert('First name and email required'); return; }
        var assigned = stations ? stations.split(',').map(function(s){return parseInt(s,10)||0;}).filter(Boolean) : [];
        var payload = { first_name:first, last_name:last, email:email, phone:phone, assigned_station_ids:assigned };
        var url = (typeof pollmonitorOnboardSettings !== 'undefined' ? pollmonitorOnboardSettings.root : '') + 'pollmonitor/v1/observers';
        fetch(url, {
            method:'POST',
            credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-WP-Nonce': (typeof pollmonitorOnboardSettings !== 'undefined' ? pollmonitorOnboardSettings.nonce : '')},
            body: JSON.stringify(payload)
        }).then(function(r){
            return r.json().then(function(j){ if(!r.ok) throw j; return j; });
        }).then(function(data){
            el('pm-onboard-status').textContent = 'Observer created — username: ' + (data.username||data.id) + '. Password reset email sent.';
            el('pm-onboard-form').reset();
        }).catch(function(err){
            var msg = (err && err.message) ? err.message : (err && err.data && err.data.message) ? err.data.message : 'Create failed';
            el('pm-onboard-status').textContent = msg; el('pm-onboard-status').style.color='red';
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        var f = el('pm-onboard-form'); if(f) f.addEventListener('submit', submitOnboard);
    });
})();
