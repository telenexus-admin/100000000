{* Shared CPE Access modal.
   Exposes a single function window.openCpeModal(username, hints)
   that other pages (Online Clients PPPoE, CPE Manager, ...) can call.
   Emits `cpe:saved` / `cpe:deleted` CustomEvents on window when the
   admin mutates credentials so host pages can refresh. *}

<div id="cpeModalBackdrop" class="cpe-modal-backdrop">
    <div class="cpe-modal">
        <div class="cpe-modal-head">
            <h4>
                <i class="fas fa-network-wired"></i>
                <span id="cpeModalTitle">{Lang::T('Access Customer Router')}</span>
            </h4>
            <button class="cpe-btn ghost" onclick="closeCpeModal()" style="background:transparent;color:#fff;padding:4px 8px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cpe-modal-body">
            <div id="cpeAlertBox"></div>

            <div class="cpe-tabbar">
                <div class="cpe-tab active" data-tab="quick"><i class="fas fa-bolt"></i> {Lang::T('Quick Access')}</div>
                <div class="cpe-tab"        data-tab="creds"><i class="fas fa-key"></i> {Lang::T('Credentials')}</div>
                <div class="cpe-tab"        data-tab="info"><i class="fas fa-microchip"></i> {Lang::T('Identity')}</div>
                <div class="cpe-tab"        data-tab="wifi"><i class="fas fa-wifi"></i> {Lang::T('Wi-Fi')}</div>
                <div class="cpe-tab"        data-tab="power"><i class="fas fa-power-off"></i> {Lang::T('Power')}</div>
            </div>

            <!-- Quick access: Webfig / HTTPS / Winbox / Ping -->
            <div class="cpe-tabpane active" data-pane="quick">
                <div class="cpe-grid">
                    <div>
                        <label>{Lang::T('PPPoE Username')}</label>
                        <input type="text" id="cpeUsername" placeholder="e.g. ACC61484">
                    </div>
                    <div>
                        <label>{Lang::T('Host / IP (override)')}</label>
                        <input type="text" id="cpeHost" placeholder="{Lang::T('blank = use live PPPoE IP')}">
                    </div>
                    <div>
                        <label>{Lang::T('HTTP Port')}</label>
                        <input type="number" id="cpeHttpPort" value="80">
                    </div>
                    <div>
                        <label>{Lang::T('HTTPS Port')}</label>
                        <input type="number" id="cpeHttpsPort" value="443">
                    </div>
                    <div>
                        <label>{Lang::T('Winbox Port')}</label>
                        <input type="number" id="cpeWinboxPort" value="8291">
                    </div>
                    <div>
                        <label>{Lang::T('API Port')}</label>
                        <input type="number" id="cpeApiPort" value="8728">
                    </div>
                </div>

                <div class="cpe-btn-row">
                    <button class="cpe-btn primary" onclick="cpeOpen('http')">
                        <i class="fas fa-globe"></i> {Lang::T('Webfig (HTTP)')}
                    </button>
                    <button class="cpe-btn primary" onclick="cpeOpen('https')">
                        <i class="fas fa-lock"></i> {Lang::T('Webfig (HTTPS)')}
                    </button>
                    <button class="cpe-btn success" onclick="cpeOpen('winbox')">
                        <i class="fas fa-desktop"></i> {Lang::T('Launch Winbox')}
                    </button>
                    <button class="cpe-btn warn" onclick="cpePing()">
                        <i class="fas fa-satellite-dish"></i> {Lang::T('Reachability Check')}
                    </button>
                </div>
                <div id="cpePingResult" style="margin-top:10px;"></div>
            </div>

            <!-- Credentials tab -->
            <div class="cpe-tabpane" data-pane="creds">
                <div class="cpe-grid">
                    <div>
                        <label>{Lang::T('API Username')}</label>
                        <input type="text" id="cpeApiUser" placeholder="admin">
                    </div>
                    <div>
                        <label>{Lang::T('API Password')}</label>
                        <input type="password" id="cpeApiPass" placeholder="••••••" autocomplete="new-password">
                    </div>
                    <div>
                        <label>{Lang::T('Brand')}</label>
                        <select id="cpeBrand">
                            <option value="mikrotik">MikroTik</option>
                            <option value="huawei">Huawei</option>
                            <option value="zte">ZTE</option>
                            <option value="tplink">TP-Link</option>
                            <option value="other">{Lang::T('Other')}</option>
                        </select>
                    </div>
                    <div>
                        <label>{Lang::T('Prefer HTTPS')}</label>
                        <select id="cpePreferHttps">
                            <option value="0">{Lang::T('No')}</option>
                            <option value="1">{Lang::T('Yes')}</option>
                        </select>
                    </div>
                    <div class="full">
                        <label>{Lang::T('Notes')}</label>
                        <textarea id="cpeNotes" rows="2" placeholder="{Lang::T('Internal notes, e.g. installed model / install date')}"></textarea>
                    </div>
                </div>
                <div class="cpe-btn-row">
                    <button class="cpe-btn success" onclick="cpeSaveCreds()">
                        <i class="fas fa-save"></i> {Lang::T('Save Credentials')}
                    </button>
                    <button class="cpe-btn danger" onclick="cpeDeleteCreds()">
                        <i class="fas fa-trash"></i> {Lang::T('Remove')}
                    </button>
                </div>
                <p style="margin-top:10px; color:#64748b; font-size:12px;">
                    <i class="fas fa-info-circle"></i>
                    {Lang::T('Leave the password blank when saving to keep the existing one.')}
                </p>
            </div>

            <!-- Identity tab -->
            <div class="cpe-tabpane" data-pane="info">
                <div class="cpe-btn-row" style="margin-bottom:10px;">
                    <button class="cpe-btn primary" onclick="cpeFetchIdentity()">
                        <i class="fas fa-sync"></i> {Lang::T('Fetch CPE Identity')}
                    </button>
                </div>
                <div id="cpeIdentityOut" class="cpe-kv"></div>
            </div>

            <!-- Wi-Fi tab -->
            <div class="cpe-tabpane" data-pane="wifi">
                <div class="cpe-btn-row" style="margin-bottom:10px;">
                    <button class="cpe-btn primary" onclick="cpeFetchWifi()">
                        <i class="fas fa-sync"></i> {Lang::T('Fetch Wi-Fi Interfaces')}
                    </button>
                </div>
                <div id="cpeWifiList"></div>
            </div>

            <!-- Power / reboot tab -->
            <div class="cpe-tabpane" data-pane="power">
                <p style="color:#475569; font-size:13px;">
                    {Lang::T('Reboots the CPE over the RouterOS API. The active PPPoE session will drop and re-dial after a few seconds.')}
                </p>
                <div class="cpe-btn-row">
                    <button class="cpe-btn danger" onclick="cpeReboot()">
                        <i class="fas fa-power-off"></i> {Lang::T('Reboot CPE')}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    window.CPE_CSRF     = window.CPE_CSRF     || (typeof csrf_token !== 'undefined' ? csrf_token : '{$csrf_token}');
    window.CPE_URL_BASE = window.CPE_URL_BASE || ('{$_url}customer_router');

    var L = {
        accessRouter:        '{Lang::T('Access Router')}',
        accessCustomer:      '{Lang::T('Access Customer Router')}',
        enterHost:           '{Lang::T('Enter the CPE host / IP first')}',
        checking:            '{Lang::T('Checking...')}',
        portLbl:             '{Lang::T('Port')}',
        statusLbl:           '{Lang::T('Status')}',
        latencyLbl:          '{Lang::T('Latency')}',
        userRequired:        '{Lang::T('Username is required')}',
        saved:               '{Lang::T('Saved')}',
        confirmRemove:       '{Lang::T('Remove saved CPE for')}',
        removed:             '{Lang::T('Removed')}',
        usernameReq:         '{Lang::T('Username required')}',
        contacting:          '{Lang::T('Contacting CPE...')}',
        identity:            '{Lang::T('Identity')}',
        board:               '{Lang::T('Board')}',
        model:               '{Lang::T('Model')}',
        serial:              '{Lang::T('Serial')}',
        version:             '{Lang::T('Version')}',
        cpuLoad:             '{Lang::T('CPU Load')}',
        uptime:              '{Lang::T('Uptime')}',
        noWireless:          '{Lang::T('No wireless interfaces reported by CPE.')}',
        stackLbl:            '{Lang::T('Stack')}',
        nameLbl:             '{Lang::T('Name')}',
        ssidLbl:             '{Lang::T('SSID')}',
        newPasswordLbl:      '{Lang::T('New Password')}',
        actionLbl:           '{Lang::T('Action')}',
        applyLbl:            '{Lang::T('Apply')}',
        passwordShort:       '{Lang::T('Password must be at least 8 characters.')}',
        confirmReboot:       '{Lang::T('Reboot the CPE now?')}',
        rebooting:           '{Lang::T('Rebooting')}'
    };
{literal}
    var $root = document.getElementById('cpeModalBackdrop');
    if (!$root) return;

    var $tabs  = $root.querySelectorAll('.cpe-tab');
    var $panes = $root.querySelectorAll('.cpe-tabpane');

    $tabs.forEach(function(t){
        t.addEventListener('click', function(){
            $tabs.forEach(function(x){ x.classList.remove('active'); });
            $panes.forEach(function(x){ x.classList.remove('active'); });
            t.classList.add('active');
            var pane = t.getAttribute('data-tab');
            $root.querySelector('.cpe-tabpane[data-pane="' + pane + '"]').classList.add('active');
        });
    });

    function escHtml(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function alertBox(kind, msg){
        var box = document.getElementById('cpeAlertBox');
        box.innerHTML = '<div class="cpe-alert ' + kind + '">' + escHtml(msg) + '</div>';
        if (kind !== 'err') {
            setTimeout(function(){ if (box.firstChild) box.firstChild.remove(); }, 4000);
        }
    }

    function val(id){ var el = document.getElementById(id); return el ? (el.value || '').trim() : ''; }
    function setVal(id, v){ var el = document.getElementById(id); if (el) el.value = (v == null ? '' : v); }

    function getHost(){
        var h = val('cpeHost');
        if (h.indexOf(':') !== -1) h = h.split(':')[0];
        return h;
    }

    window.openCpeModal = function(username, hints){
        hints = hints || {};
        setVal('cpeUsername', username || '');
        setVal('cpeHost', hints.host || '');
        setVal('cpeApiUser', '');
        setVal('cpeApiPass', '');
        setVal('cpeNotes', '');
        setVal('cpeBrand', 'mikrotik');
        setVal('cpePreferHttps', '0');
        setVal('cpeApiPort', 8728);
        setVal('cpeHttpPort', 80);
        setVal('cpeHttpsPort', 443);
        setVal('cpeWinboxPort', 8291);
        document.getElementById('cpePingResult').innerHTML = '';
        document.getElementById('cpeIdentityOut').innerHTML = '';
        document.getElementById('cpeWifiList').innerHTML = '';
        document.getElementById('cpeAlertBox').innerHTML = '';

        $tabs.forEach(function(t){ t.classList.remove('active'); });
        $panes.forEach(function(x){ x.classList.remove('active'); });
        $root.querySelector('.cpe-tab[data-tab="quick"]').classList.add('active');
        $root.querySelector('.cpe-tabpane[data-pane="quick"]').classList.add('active');

        document.getElementById('cpeModalTitle').textContent =
            username ? L.accessRouter + ' - ' + username : L.accessCustomer;

        $root.style.display = 'flex';

        if (username) {
            fetch(CPE_URL_BASE + '/creds_get&username=' + encodeURIComponent(username), { credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res || res.status !== 'success') return;
                    var d = res.data || {};
                    if (!val('cpeHost')) setVal('cpeHost', d.host || '');
                    setVal('cpeApiUser', d.api_user || '');
                    if (d.api_pass_mask) {
                        document.getElementById('cpeApiPass').setAttribute('placeholder', d.api_pass_mask);
                    }
                    setVal('cpeApiPort', d.api_port || 8728);
                    setVal('cpeHttpPort', d.http_port || 80);
                    setVal('cpeHttpsPort', d.https_port || 443);
                    setVal('cpeWinboxPort', d.winbox_port || 8291);
                    setVal('cpePreferHttps', d.prefer_https ? '1' : '0');
                    setVal('cpeBrand', d.brand || 'mikrotik');
                    setVal('cpeNotes', d.notes || '');
                });
        }
    };

    window.closeCpeModal = function(){ $root.style.display = 'none'; };
    $root.addEventListener('click', function(e){ if (e.target === $root) closeCpeModal(); });

    window.cpeOpen = function(kind){
        var host = getHost();
        if (!host) { alertBox('err', L.enterHost); return; }
        var url = '';
        if (kind === 'http')   url = 'http://'  + host + ':' + (val('cpeHttpPort')  || 80)  + '/';
        if (kind === 'https')  url = 'https://' + host + ':' + (val('cpeHttpsPort') || 443) + '/';
        if (kind === 'winbox') url = 'winbox://' + host + ':' + (val('cpeWinboxPort') || 8291);
        if (kind === 'winbox') {
            window.location.href = url;
        } else {
            window.open(url, '_blank', 'noopener');
        }
    };

    window.cpePing = function(){
        var host = getHost();
        if (!host) { alertBox('err', L.enterHost); return; }
        var ports = [val('cpeHttpPort')||80, val('cpeHttpsPort')||443, val('cpeWinboxPort')||8291, val('cpeApiPort')||8728].join(',');
        document.getElementById('cpePingResult').innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> ' + escHtml(L.checking);
        fetch(CPE_URL_BASE + '/ping&ip=' + encodeURIComponent(host) + '&ports=' + encodeURIComponent(ports), { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res || res.status !== 'success') {
                    document.getElementById('cpePingResult').innerHTML =
                        '<div class="cpe-alert err">' + escHtml(res && res.message ? res.message : 'Error') + '</div>';
                    return;
                }
                var html = '<table class="cpe-table"><thead><tr><th>' + escHtml(L.portLbl) + '</th><th>' + escHtml(L.statusLbl) + '</th><th>' + escHtml(L.latencyLbl) + '</th></tr></thead><tbody>';
                res.data.ports.forEach(function(p){
                    html += '<tr><td>' + p.port + '</td>' +
                            '<td>' + (p.open ? '<span class="cpe-badge ok">open</span>' : '<span class="cpe-badge no">closed</span>') + '</td>' +
                            '<td>' + p.ms + ' ms</td></tr>';
                });
                html += '</tbody></table>';
                document.getElementById('cpePingResult').innerHTML = html;
            });
    };

    window.cpeSaveCreds = function(){
        var username = val('cpeUsername');
        if (!username) { alertBox('err', L.userRequired); return; }
        var fd = new FormData();
        fd.append('csrf_token', CPE_CSRF);
        fd.append('username',     username);
        fd.append('host',         getHost());
        fd.append('api_user',     val('cpeApiUser'));
        fd.append('api_pass',     val('cpeApiPass'));
        fd.append('api_port',     val('cpeApiPort')    || 8728);
        fd.append('http_port',    val('cpeHttpPort')   || 80);
        fd.append('https_port',   val('cpeHttpsPort')  || 443);
        fd.append('winbox_port',  val('cpeWinboxPort') || 8291);
        fd.append('prefer_https', val('cpePreferHttps') || '0');
        fd.append('brand',        val('cpeBrand')      || 'mikrotik');
        fd.append('notes',        val('cpeNotes'));
        fetch(CPE_URL_BASE + '/creds_save', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.status === 'success') {
                    alertBox('ok', res.message || L.saved);
                    window.dispatchEvent(new CustomEvent('cpe:saved', { detail: { username: username } }));
                } else {
                    alertBox('err', res && res.message ? res.message : 'Error');
                }
            });
    };

    window.cpeDeleteCreds = function(){
        var username = val('cpeUsername');
        if (!username) return;
        if (!confirm(L.confirmRemove + ' ' + username + '?')) return;
        var fd = new FormData();
        fd.append('csrf_token', CPE_CSRF);
        fd.append('username', username);
        fetch(CPE_URL_BASE + '/creds_delete', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.status === 'success') {
                    alertBox('ok', res.message || L.removed);
                    window.dispatchEvent(new CustomEvent('cpe:deleted', { detail: { username: username } }));
                    closeCpeModal();
                } else {
                    alertBox('err', res && res.message ? res.message : 'Error');
                }
            });
    };

    window.cpeFetchIdentity = function(){
        var username = val('cpeUsername');
        if (!username) { alertBox('err', L.usernameReq); return; }
        var out = document.getElementById('cpeIdentityOut');
        out.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + escHtml(L.contacting);
        fetch(CPE_URL_BASE + '/identity&username=' + encodeURIComponent(username), { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res || res.status !== 'success') {
                    out.innerHTML = '<div class="cpe-alert err">' + escHtml(res && res.message ? res.message : 'Error') + '</div>';
                    return;
                }
                var d = res.data || {};
                var rows = [
                    [L.identity, d.identity || '-'],
                    [L.board,    d.board    || '-'],
                    [L.model,    d.model    || '-'],
                    [L.serial,   d.serial   || '-'],
                    [L.version,  d.version  || '-'],
                    [L.cpuLoad,  (d.cpu != null ? d.cpu + '%' : '-')],
                    [L.uptime,   d.uptime   || '-']
                ];
                var html = '';
                rows.forEach(function(r){ html += '<div>' + escHtml(r[0]) + '</div><div>' + escHtml(r[1]) + '</div>'; });
                out.innerHTML = html;
            });
    };

    window.cpeFetchWifi = function(){
        var username = val('cpeUsername');
        if (!username) { alertBox('err', L.usernameReq); return; }
        var out = document.getElementById('cpeWifiList');
        out.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + escHtml(L.contacting);
        fetch(CPE_URL_BASE + '/wifi_list&username=' + encodeURIComponent(username), { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res || res.status !== 'success') {
                    out.innerHTML = '<div class="cpe-alert err">' + escHtml(res && res.message ? res.message : 'Error') + '</div>';
                    return;
                }
                var list = res.data || [];
                if (!list.length) {
                    out.innerHTML = '<div class="cpe-alert info">' + escHtml(L.noWireless) + '</div>';
                    return;
                }
                var html = '<table class="cpe-table"><thead><tr><th>' + escHtml(L.stackLbl) + '</th><th>' + escHtml(L.nameLbl) + '</th><th>' + escHtml(L.ssidLbl) + '</th><th>' + escHtml(L.newPasswordLbl) + '</th><th>' + escHtml(L.actionLbl) + '</th></tr></thead><tbody>';
                list.forEach(function(w, i){
                    var pid = 'wpw_' + i;
                    html += '<tr>' +
                        '<td>' + escHtml(w.stack) + '</td>' +
                        '<td><code class="cpe-code">' + escHtml(w.name) + '</code></td>' +
                        '<td>' + escHtml(w.ssid || '-') + '</td>' +
                        '<td><input type="text" id="' + pid + '" placeholder="min 8 chars" style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;width:160px;"></td>' +
                        '<td><button class="cpe-btn success" onclick="cpeSetWifiPassword(\'' + escHtml(w.stack) + '\',\'' + escHtml(w.name) + '\',\'' + pid + '\')"><i class="fas fa-key"></i> ' + escHtml(L.applyLbl) + '</button></td>' +
                    '</tr>';
                });
                html += '</tbody></table>';
                out.innerHTML = html;
            });
    };

    window.cpeSetWifiPassword = function(stack, iface, inputId){
        var username = val('cpeUsername');
        var pwd = (document.getElementById(inputId).value || '').trim();
        if (!username) { alertBox('err', L.usernameReq); return; }
        if (pwd.length < 8) { alertBox('err', L.passwordShort); return; }
        var fd = new FormData();
        fd.append('csrf_token', CPE_CSRF);
        fd.append('username',  username);
        fd.append('interface', iface);
        fd.append('stack',     stack);
        fd.append('password',  pwd);
        fetch(CPE_URL_BASE + '/wifi_set_password', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.status === 'success') alertBox('ok', res.message || 'OK');
                else alertBox('err', res && res.message ? res.message : 'Error');
            });
    };

    window.cpeReboot = function(){
        var username = val('cpeUsername');
        if (!username) { alertBox('err', L.usernameReq); return; }
        if (!confirm(L.confirmReboot)) return;
        var fd = new FormData();
        fd.append('csrf_token', CPE_CSRF);
        fd.append('username', username);
        fetch(CPE_URL_BASE + '/reboot', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.status === 'success') alertBox('ok', res.message || L.rebooting);
                else alertBox('err', res && res.message ? res.message : 'Error');
            });
    };
})();
{/literal}
</script>
