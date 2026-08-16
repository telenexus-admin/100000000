{include file="sections/header.tpl"}

<style>
    .cpe-panel { border: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 22px rgba(0,0,0,.08); }
    .cpe-panel .panel-heading {
        background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 55%, #4f46e5 100%);
        color: #fff; padding: 16px 18px; font-weight: 600;
    }
    .cpe-panel .panel-body { background: #fff; }
    .cpe-table { width: 100%; border-collapse: collapse; }
    .cpe-table th, .cpe-table td { padding: 10px; border: 1px solid #e5e7eb; text-align: left; font-size: 13px; }
    .cpe-table th { background: #f8fafc; color: #334155; font-weight: 600; }
    .cpe-table tr:hover td { background: #f8fafc; }
    .cpe-actions .btn { margin-right: 4px; margin-bottom: 4px; }
    .cpe-badge {
        display: inline-block; padding: 2px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 600;
    }
    .cpe-badge.ok { background: #dcfce7; color: #166534; }
    .cpe-badge.no { background: #fee2e2; color: #991b1b; }
    .cpe-modal-backdrop {
        position: fixed; inset: 0; background: rgba(15,23,42,.45);
        display: none; align-items: center; justify-content: center; z-index: 9999;
    }
    .cpe-modal {
        background: #fff; border-radius: 12px; width: 720px; max-width: 94vw;
        max-height: 92vh; overflow: auto; box-shadow: 0 30px 60px rgba(0,0,0,.25);
    }
    .cpe-modal-head {
        background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
        color: #fff; padding: 14px 18px; border-radius: 12px 12px 0 0;
        display: flex; align-items: center; justify-content: space-between;
    }
    .cpe-modal-head h4 { margin: 0; font-size: 16px; }
    .cpe-modal-body { padding: 18px; }
    .cpe-tabbar { display: flex; gap: 4px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; }
    .cpe-tab { padding: 8px 14px; cursor: pointer; border-bottom: 2px solid transparent; color: #64748b; font-size: 13px; }
    .cpe-tab.active { color: #2563eb; border-color: #2563eb; font-weight: 600; }
    .cpe-tabpane { display: none; }
    .cpe-tabpane.active { display: block; }
    .cpe-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .cpe-grid .full { grid-column: 1 / -1; }
    .cpe-grid label { font-size: 12px; color: #475569; display: block; margin-bottom: 4px; }
    .cpe-grid input, .cpe-grid select, .cpe-grid textarea {
        width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;
    }
    .cpe-btn-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
    .cpe-btn {
        padding: 8px 14px; border: none; border-radius: 8px; font-size: 13px;
        cursor: pointer; font-weight: 600;
    }
    .cpe-btn.primary  { background: #2563eb; color: #fff; }
    .cpe-btn.primary:hover { background: #1d4ed8; }
    .cpe-btn.success  { background: #16a34a; color: #fff; }
    .cpe-btn.success:hover { background: #15803d; }
    .cpe-btn.warn     { background: #f59e0b; color: #fff; }
    .cpe-btn.warn:hover { background: #d97706; }
    .cpe-btn.danger   { background: #dc2626; color: #fff; }
    .cpe-btn.danger:hover { background: #b91c1c; }
    .cpe-btn.ghost    { background: #f1f5f9; color: #334155; }
    .cpe-btn.ghost:hover { background: #e2e8f0; }
    .cpe-alert { padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 10px; }
    .cpe-alert.info  { background: #dbeafe; color: #1e3a8a; }
    .cpe-alert.ok    { background: #dcfce7; color: #166534; }
    .cpe-alert.err   { background: #fee2e2; color: #991b1b; }
    .cpe-kv { display: grid; grid-template-columns: 140px 1fr; gap: 6px 12px; font-size: 13px; }
    .cpe-kv > div:nth-child(odd) { color: #64748b; }
    code.cpe-code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="panel cpe-panel">
            <div class="panel-heading">
                <i class="fas fa-network-wired"></i> {Lang::T('CPE Manager')}
                <span style="float:right; font-weight:400; font-size:12px;">
                    {Lang::T('Access and manage customer routers from the dashboard')}
                </span>
            </div>
            <div class="panel-body">
                <div class="cpe-alert info">
                    <i class="fas fa-info-circle"></i>
                    {Lang::T('Save credentials per PPPoE username, then use Webfig / Winbox / Ping / Identity / Wi-Fi / Reboot from the row actions. Credentials never leave this server.')}
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div>
                        <button class="cpe-btn primary" id="cpe_add_btn">
                            <i class="fas fa-plus"></i> {Lang::T('Add CPE')}
                        </button>
                        <button class="cpe-btn ghost" id="cpe_reload_btn">
                            <i class="fas fa-sync"></i> {Lang::T('Reload')}
                        </button>
                    </div>
                    <input type="text" id="cpe_search" placeholder="{Lang::T('Search username, host, brand')}"
                        style="padding:6px 10px; border:1px solid #d1d5db; border-radius:8px; width:260px;">
                </div>

                <div id="cpe_list_container">
                    <div style="text-align:center; padding:40px; color:#94a3b8;">
                        <i class="fas fa-spinner fa-spin"></i> {Lang::T('Loading...')}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{include file="admin/customer_router/_modal.tpl"}

<script>
(function(){
    var CSRF = '{$csrf_token}';
    var URL_BASE = '{$_url}customer_router';
    var L = {
        empty:      '{Lang::T('No CPEs saved yet. Click Add CPE to start.')}',
        username:   '{Lang::T('Username')}',
        host:       '{Lang::T('Host')}',
        brand:      '{Lang::T('Brand')}',
        api:        '{Lang::T('API')}',
        notes:      '{Lang::T('Notes')}',
        actions:    '{Lang::T('Actions')}',
        configured: '{Lang::T('Configured')}',
        noPassword: '{Lang::T('No password')}',
        open:       '{Lang::T('Open')}',
        loadFail:   '{Lang::T('Failed to load CPE list')}',
        confirmDel: '{Lang::T('Remove saved CPE for')}'
    };
{literal}
    var allRows = [];
    var $list = document.getElementById('cpe_list_container');

    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function render(){
        var term = (document.getElementById('cpe_search').value || '').toLowerCase();
        var rows = allRows.filter(function(r){
            if (!term) return true;
            return (r.username||'').toLowerCase().indexOf(term) !== -1
                || (r.host||'').toLowerCase().indexOf(term) !== -1
                || (r.brand||'').toLowerCase().indexOf(term) !== -1;
        });
        if (!rows.length) {
            $list.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8;">' +
                '<i class="fas fa-inbox"></i> ' + esc(L.empty) + '</div>';
            return;
        }
        var html = '<table class="cpe-table"><thead><tr>' +
            '<th>' + esc(L.username) + '</th>' +
            '<th>' + esc(L.host) + '</th>' +
            '<th>' + esc(L.brand) + '</th>' +
            '<th>' + esc(L.api) + '</th>' +
            '<th>' + esc(L.notes) + '</th>' +
            '<th style="width:340px;">' + esc(L.actions) + '</th>' +
            '</tr></thead><tbody>';
        rows.forEach(function(r){
            var host = r.host || '-';
            html += '<tr>' +
                '<td><strong>' + esc(r.username) + '</strong></td>' +
                '<td><code class="cpe-code">' + esc(host) + '</code></td>' +
                '<td>' + esc(r.brand || 'mikrotik') + '</td>' +
                '<td>' + (r.has_password
                    ? '<span class="cpe-badge ok">' + esc(L.configured) + '</span>'
                    : '<span class="cpe-badge no">' + esc(L.noPassword) + '</span>') + '</td>' +
                '<td><small>' + esc(r.notes || '') + '</small></td>' +
                '<td class="cpe-actions">' +
                    '<button class="cpe-btn primary" onclick="openCpeModal(\'' + esc(r.username) + '\')"><i class="fas fa-external-link-alt"></i> ' + esc(L.open) + '</button>' +
                    '<button class="cpe-btn danger" onclick="deleteCpe(\'' + esc(r.username) + '\')"><i class="fas fa-trash"></i></button>' +
                '</td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $list.innerHTML = html;
    }

    function loadList(){
        fetch(URL_BASE + '/list', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                allRows = (res && res.status === 'success' && Array.isArray(res.data)) ? res.data : [];
                render();
            })
            .catch(function(){
                $list.innerHTML = '<div class="cpe-alert err">' + esc(L.loadFail) + '</div>';
            });
    }

    window.deleteCpe = function(username){
        if (!confirm(L.confirmDel + ' ' + username + '?')) return;
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('username', username);
        fetch(URL_BASE + '/creds_delete', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.status === 'success') loadList();
                else alert(res && res.message ? res.message : 'Error');
            });
    };

    document.getElementById('cpe_add_btn').addEventListener('click', function(){
        openCpeModal('', { host: '' });
    });
    document.getElementById('cpe_reload_btn').addEventListener('click', loadList);
    document.getElementById('cpe_search').addEventListener('input', render);

    window.addEventListener('cpe:saved', loadList);
    window.addEventListener('cpe:deleted', loadList);

    loadList();
})();
{/literal}
</script>

{include file="sections/footer.tpl"}
