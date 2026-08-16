{include file="user-ui/header.tpl"}

<style>
    .myr-card {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 6px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .myr-card-head {
        padding: 14px 18px;
        border-bottom: 1px solid #eef0f5;
        font-weight: 600;
        color: #3a3b45;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .myr-card-body { padding: 18px; }
    .myr-stat { padding: 14px 12px; text-align: center; }
    .myr-stat .value { font-size: 18px; font-weight: 700; color: #2c3e50; word-break: break-all; }
    .myr-stat .label { font-size: 12px; color: #7a7c89; text-transform: uppercase; letter-spacing: .4px; margin-top: 4px; }
    .myr-wifi-row {
        border: 1px solid #eef0f5;
        border-radius: 6px;
        padding: 14px;
        margin-bottom: 10px;
        background: #fafbfc;
    }
    .myr-wifi-row.disabled { opacity: .55; }
    .myr-wifi-title { font-weight: 600; color: #2c3e50; margin-bottom: 6px; }
    .myr-wifi-meta { font-size: 12px; color: #7a7c89; }
    .myr-btn-ghost {
        background: #fff;
        border: 1px solid #d6d9e1;
        color: #333;
        border-radius: 4px;
        padding: 6px 12px;
        font-size: 13px;
        cursor: pointer;
    }
    .myr-btn-ghost:hover { background: #f5f6f8; }
    .myr-alert {
        border-radius: 6px;
        padding: 14px 16px;
        margin-bottom: 18px;
        font-size: 14px;
    }
    .myr-alert-warn { background: #fff5e6; border: 1px solid #ffd591; color: #8a4f00; }
    .myr-alert-success { background: #e7f7ec; border: 1px solid #b7e4c0; color: #1c6b2d; }
    .myr-alert-error { background: #fdeaea; border: 1px solid #f2b5b5; color: #a22121; }
    .myr-muted { color: #7a7c89; font-size: 12px; }
    .myr-spinner {
        display: inline-block;
        width: 16px; height: 16px;
        border: 2px solid #d6d9e1;
        border-top-color: #4e73df;
        border-radius: 50%;
        animation: myr-spin .8s linear infinite;
        vertical-align: middle;
    }
    @keyframes myr-spin { to { transform: rotate(360deg); } }
    .myr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; }
    .myr-hide { display: none !important; }
    .myr-btn-danger {
        background: #e74a3b; color: #fff; border: 1px solid #e74a3b;
        border-radius: 4px; padding: 7px 14px; font-size: 13px; cursor: pointer;
    }
    .myr-btn-danger:hover { background: #c73327; }
    .myr-form-row { margin-bottom: 12px; }
    .myr-form-row label { display:block; font-size: 12px; color:#5a5c6b; margin-bottom:4px; font-weight:600; }
    .myr-form-row input, .myr-form-row select {
        width: 100%; border: 1px solid #d6d9e1; border-radius: 4px;
        padding: 8px 10px; font-size: 14px; background: #fff;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <h4 style="margin-top:0;"><i class="fa fa-wifi"></i> My Router</h4>
        <p class="myr-muted">View your home router status and change your Wi-Fi password.</p>

        <div id="myrAlerts"></div>

        {if !$router_configured}
            <div class="myr-alert myr-alert-warn">
                <strong>Router access is not set up yet.</strong><br>
                Your ISP hasn't enabled remote management for your router.
                Please contact support if you'd like self-service Wi-Fi control.
            </div>
        {else}

        <!-- =============== STATUS =============== -->
        <div class="myr-card">
            <div class="myr-card-head">
                <i class="fa fa-info-circle"></i> Router Status
                <button class="myr-btn-ghost" onclick="myrLoadInfo()" style="margin-left:auto;">
                    <i class="fa fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="myr-card-body">
                <div id="myrInfoLoading" class="myr-muted"><span class="myr-spinner"></span> Loading...</div>
                <div id="myrInfoGrid" class="myr-grid myr-hide"></div>
            </div>
        </div>

        <!-- =============== WI-FI =============== -->
        <div class="myr-card">
            <div class="myr-card-head">
                <i class="fa fa-broadcast-tower"></i> Wi-Fi Networks
                <button class="myr-btn-ghost" onclick="myrLoadWifi()" style="margin-left:auto;">
                    <i class="fa fa-sync-alt"></i> Reload
                </button>
            </div>
            <div class="myr-card-body">
                <div id="myrWifiLoading" class="myr-muted"><span class="myr-spinner"></span> Loading Wi-Fi...</div>
                <div id="myrWifiList"></div>
            </div>
        </div>

        <!-- =============== POWER =============== -->
        <div class="myr-card">
            <div class="myr-card-head">
                <i class="fa fa-power-off"></i> Power
            </div>
            <div class="myr-card-body">
                <p class="myr-muted" style="margin-top:0;">
                    Reboot will briefly disconnect all devices in your home.
                    The router usually comes back within 2 minutes.
                </p>
                <button class="myr-btn-danger" onclick="myrReboot()">
                    <i class="fa fa-redo"></i> Reboot My Router
                </button>
            </div>
        </div>

        {/if}
    </div>
</div>

<!-- =============== WIFI PASSWORD MODAL =============== -->
<div id="myrPwdModal" class="myr-hide" style="position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:8px; width:92%; max-width:420px; padding:22px; box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <h4 style="margin:0 0 12px 0;"><i class="fa fa-key"></i> Change Wi-Fi Password</h4>
        <p class="myr-muted">Network: <strong id="myrPwdIface">-</strong></p>
        <div class="myr-form-row">
            <label>New Wi-Fi Password</label>
            <input type="text" id="myrPwdInput" placeholder="Minimum 8 characters" minlength="8" autocomplete="off">
            <div class="myr-muted" style="margin-top:4px;">Tip: Use 12+ characters mixing letters, numbers and symbols.</div>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px;">
            <button class="myr-btn-ghost" onclick="myrClosePwdModal()">Cancel</button>
            <button class="myr-btn-danger" style="background:#4e73df;border-color:#4e73df;" onclick="myrSavePassword()">
                <i class="fa fa-save"></i> Save Password
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    var BASE = "{$_url}plugin/my_router";
    var CSRF = "{$csrf_token}";
    var _currentIface = null;
    var _currentStack = null;

    window.myrShowAlert = function(kind, msg){
        var el = document.createElement('div');
        el.className = 'myr-alert myr-alert-' + kind;
        el.innerHTML = msg;
        var host = document.getElementById('myrAlerts');
        host.innerHTML = '';
        host.appendChild(el);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (kind === 'success') {
            setTimeout(function(){ if (el.parentNode) el.parentNode.removeChild(el); }, 4500);
        }
    };

    function fmtBytes(n){
        n = Number(n || 0);
        if (!n) return '-';
        var units = ['B','KB','MB','GB','TB'];
        var i = 0;
        while (n >= 1024 && i < units.length-1) { n /= 1024; i++; }
        return n.toFixed(1) + ' ' + units[i];
    }

    function esc(s){
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    window.myrLoadInfo = function(){
        var grid = document.getElementById('myrInfoGrid');
        var load = document.getElementById('myrInfoLoading');
        if (!grid) return;
        grid.classList.add('myr-hide');
        load.classList.remove('myr-hide');
        fetch(BASE + '&type=info', {ldelim} credentials: 'same-origin' {rdelim})
            .then(function(r){ return r.json(); })
            .then(function(res){
                load.classList.add('myr-hide');
                if (res.status !== 'success') {
                    myrShowAlert('error', 'Could not load router info: ' + esc(res.message || 'unknown error'));
                    return;
                }
                var d = res.data || {};
                var memPct = '';
                if (d.memory_total && d.memory_free) {
                    memPct = Math.round((1 - (d.memory_free / d.memory_total)) * 100) + '%';
                }
                var cells = [
                    { label: 'Identity', value: d.identity || '-' },
                    { label: 'Model',    value: d.model || d.board || '-' },
                    { label: 'RouterOS', value: d.version || '-' },
                    { label: 'Uptime',   value: d.uptime || '-' },
                    { label: 'CPU Load', value: (d.cpu != null ? d.cpu + '%' : '-') },
                    { label: 'Memory',   value: memPct || fmtBytes(d.memory_free) },
                    { label: 'Serial',   value: d.serial || '-' },
                ];
                grid.innerHTML = cells.map(function(c){
                    return '<div class="myr-stat"><div class="value">' + esc(c.value) + '</div><div class="label">' + esc(c.label) + '</div></div>';
                }).join('');
                grid.classList.remove('myr-hide');
            })
            .catch(function(e){
                load.classList.add('myr-hide');
                myrShowAlert('error', 'Network error loading router info.');
            });
    };

    window.myrLoadWifi = function(){
        var host = document.getElementById('myrWifiList');
        var load = document.getElementById('myrWifiLoading');
        if (!host) return;
        host.innerHTML = '';
        load.classList.remove('myr-hide');
        fetch(BASE + '&type=wifi_list', {ldelim} credentials: 'same-origin' {rdelim})
            .then(function(r){ return r.json(); })
            .then(function(res){
                load.classList.add('myr-hide');
                if (res.status !== 'success') {
                    myrShowAlert('error', 'Could not list Wi-Fi: ' + esc(res.message || 'unknown error'));
                    return;
                }
                var list = res.data || [];
                if (list.length === 0) {
                    host.innerHTML = '<div class="myr-muted">No wireless interfaces found on your router.</div>';
                    return;
                }
                host.innerHTML = list.map(function(w){
                    var isOff = (w.disabled === 'true' || w.disabled === true);
                    var ssid  = w.ssid ? w.ssid : '(no SSID)';
                    var iface = w.name || '';
                    var stack = w.stack || 'wireless';
                    return ''
                        + '<div class="myr-wifi-row' + (isOff ? ' disabled' : '') + '">'
                        +   '<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">'
                        +     '<div style="flex:1; min-width:200px;">'
                        +       '<div class="myr-wifi-title"><i class="fa fa-wifi"></i> ' + esc(ssid) + '</div>'
                        +       '<div class="myr-wifi-meta">'
                        +         'Interface: <strong>' + esc(iface) + '</strong>'
                        +         (stack === 'wifi' ? ' &middot; WifiWave2' : ' &middot; Wireless')
                        +         (isOff ? ' &middot; <span style="color:#a22;">disabled</span>' : '')
                        +       '</div>'
                        +     '</div>'
                        +     '<button class="myr-btn-ghost" onclick="myrOpenPwdModal(\'' + esc(iface) + '\',\'' + esc(stack) + '\')">'
                        +       '<i class="fa fa-key"></i> Change Password'
                        +     '</button>'
                        +   '</div>'
                        + '</div>';
                }).join('');
            })
            .catch(function(e){
                load.classList.add('myr-hide');
                myrShowAlert('error', 'Network error loading Wi-Fi list.');
            });
    };

    window.myrOpenPwdModal = function(iface, stack){
        _currentIface = iface;
        _currentStack = stack || 'wireless';
        document.getElementById('myrPwdIface').textContent = iface;
        document.getElementById('myrPwdInput').value = '';
        var m = document.getElementById('myrPwdModal');
        m.style.display = 'flex';
        m.classList.remove('myr-hide');
        setTimeout(function(){ document.getElementById('myrPwdInput').focus(); }, 50);
    };

    window.myrClosePwdModal = function(){
        var m = document.getElementById('myrPwdModal');
        m.style.display = 'none';
        m.classList.add('myr-hide');
    };

    window.myrSavePassword = function(){
        var pwd = document.getElementById('myrPwdInput').value || '';
        if (pwd.length < 8) {
            alert('Password must be at least 8 characters.');
            return;
        }
        var form = new FormData();
        form.append('csrf_token', CSRF);
        form.append('interface', _currentIface || '');
        form.append('stack', _currentStack || 'wireless');
        form.append('password', pwd);
        fetch(BASE + '&type=wifi_change', {ldelim} method: 'POST', body: form, credentials: 'same-origin' {rdelim})
            .then(function(r){ return r.json(); })
            .then(function(res){
                myrClosePwdModal();
                if (res.status === 'success') {
                    myrShowAlert('success', '<i class="fa fa-check-circle"></i> ' + esc(res.message || 'Password updated.') + ' All devices will need to reconnect using the new password.');
                    myrLoadWifi();
                } else {
                    myrShowAlert('error', '<i class="fa fa-exclamation-triangle"></i> ' + esc(res.message || 'Failed to update password.'));
                }
            })
            .catch(function(e){
                myrClosePwdModal();
                myrShowAlert('error', 'Network error updating password.');
            });
    };

    window.myrReboot = function(){
        if (!confirm('Reboot your router now? All devices will briefly lose internet.')) return;
        var form = new FormData();
        form.append('csrf_token', CSRF);
        fetch(BASE + '&type=reboot', {ldelim} method: 'POST', body: form, credentials: 'same-origin' {rdelim})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.status === 'success') {
                    myrShowAlert('success', '<i class="fa fa-check-circle"></i> Reboot command sent. Your router should be back online in about 2 minutes.');
                } else {
                    myrShowAlert('error', '<i class="fa fa-exclamation-triangle"></i> ' + esc(res.message || 'Failed to reboot.'));
                }
            })
            .catch(function(){ myrShowAlert('error', 'Network error sending reboot command.'); });
    };

    document.addEventListener('DOMContentLoaded', function(){
        {if $router_configured}
            myrLoadInfo();
            myrLoadWifi();
        {/if}
        var modal = document.getElementById('myrPwdModal');
        if (modal) {
            modal.addEventListener('click', function(e){ if (e.target === modal) myrClosePwdModal(); });
        }
    });
})();
</script>

{include file="user-ui/footer.tpl"}
