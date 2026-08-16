{include file="sections/header.tpl"}

<!-- Load jQuery explicitly -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Add Font Awesome 6 for better icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .online-panel .panel-heading {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        font-weight: 600;
        border: none;
    }
    
    .online-panel .panel-heading i {
        margin-right: 8px;
    }
    
    .online-panel .panel-body {
        padding: 20px;
        background: #fff;
    }
    
    .online-badge {
        background: #5cb85c;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        margin-left: 8px;
    }
    
    .refresh-spin {
        animation: spin 0.5s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .online-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .online-table th, .online-table td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: left;
        vertical-align: middle;
    }
    
    .online-table th {
        background-color: #f5f5f5;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
    }
    
    .online-table th:hover {
        background-color: #e8e8e8;
    }
    
    .online-table tr:hover {
        background-color: #f9f9f9;
    }
    
    .no-users-message {
        text-align: center;
        padding: 50px;
        color: #999;
    }
    
    .no-users-message i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #ddd;
    }
    
    .label-info {
        background-color: #5bc0de;
        padding: 3px 8px;
        border-radius: 3px;
        color: white;
        font-size: 11px;
        display: inline-block;
    }
    
    .text-success { color: #5cb85c; }
    .text-danger { color: #d9534f; }
    .text-muted { color: #999; }
    
    code {
        background-color: #f5f5f5;
        padding: 2px 4px;
        border-radius: 3px;
        font-size: 12px;
    }
    
    .btn-disconnect {
        background-color: #d9534f;
        color: white;
        padding: 4px 10px;
        border-radius: 3px;
        font-size: 11px;
        cursor: pointer;
        border: none;
    }
    
    .btn-disconnect:hover {
        background-color: #c9302c;
    }
    
    .btn-disconnect:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 8px 15px;
        color: white;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .btn-primary:hover {
        opacity: 0.9;
    }
    
    .table-controls {
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .entries-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .entries-control select {
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    
    .search-control input {
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 3px;
        width: 200px;
    }
    
    .pagination {
        margin-top: 15px;
        display: flex;
        justify-content: center;
        gap: 5px;
    }
    
    .pagination button {
        padding: 5px 10px;
        border: 1px solid #ddd;
        background: white;
        cursor: pointer;
        border-radius: 3px;
    }
    
    .pagination button:hover:not(:disabled) {
        background: #f0f0f0;
    }
    
    .pagination button.active {
        background: #5bc0de;
        color: white;
        border-color: #5bc0de;
    }
    
    .pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .table-info {
        margin-top: 10px;
        text-align: center;
        color: #666;
        font-size: 12px;
    }
    
    .sort-icon {
        margin-left: 5px;
        font-size: 10px;
    }
    
    .form-control {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 6px 10px;
    }

    /* Safe visual polish layer (no logic changes) */
    .online-panel {
        border: 0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
    }
    .online-panel .panel-heading {
        background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 55%, #4f46e5 100%);
        padding: 16px 18px;
    }
    .online-panel .panel-body {
        background: #ffffff;
    }
    .table-controls {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 12px;
    }
    .entries-control,
    .search-control {
        color: #475569;
        font-size: 12px;
    }
    .entries-control select,
    .search-control input,
    #router_select,
    .form-control {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        box-shadow: none;
    }
    .search-control input {
        width: 240px;
    }
    .online-table th {
        background-color: #f8fafc;
        color: #334155;
    }
    .online-table th,
    .online-table td {
        border-color: #e5e7eb;
    }
    .online-table tr:hover {
        background-color: #f8fafc;
    }
    .no-users-message {
        color: #64748b;
    }
    .btn-primary {
        border-radius: 8px;
        background: linear-gradient(135deg, #1d4ed8 0%, #4338ca 100%);
    }
    .btn-disconnect {
        border-radius: 8px;
        background-color: #dc2626;
    }
    .btn-disconnect:hover {
        background-color: #b91c1c;
    }
    .pagination button {
        border-radius: 8px;
        border-color: #d1d5db;
    }
    .pagination button.active {
        background: #2563eb;
        border-color: #2563eb;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default online-panel">
            <div class="panel-heading">
                <i class="fas fa-wifi"></i> Hotspot Online Users
                <span id="user_count" class="online-badge">0</span>
                <span id="router_status" style="float: right; font-size: 12px;"></span>
            </div>
            <div class="panel-body">
                <div style="margin-bottom: 12px; color: #64748b; font-size: 12px;">
                    Monitor active hotspot sessions and disconnect users when needed.
                </div>
                <div class="row" style="margin-bottom:15px;">
                    <div class="col-md-4">
                        <label style="font-size: 12px; color: #475569; margin-bottom: 6px;">Router Filter</label>
                        <select id="router_select" class="form-control">
                            <option value="">All Routers</option>
                            {foreach $onlineusers_routers as $r}
                                <option value="{$r['id']}">{$r['name']}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label style="font-size: 12px; color: #475569; margin-bottom: 6px;">Actions</label>
                        <button id="refresh_btn" class="btn-primary form-control">
                            <i class="fas fa-sync-alt" id="refresh_icon"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="table-controls">
                    <div class="entries-control">
                        <span>Show</span>
                        <select id="entries_per_page">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>entries</span>
                    </div>
                    <div class="search-control">
                        <span>Search:</span>
                        <input type="text" id="search_input" placeholder="Username, IP, MAC...">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <div id="users_table_container">
                        <div class="no-users-message">
                            <i class="fas fa-spinner fa-spin"></i><br>
                            Loading hotspot users...
                        </div>
                    </div>
                </div>
                
                <div class="table-info" id="table_info">
                    Showing 0 to 0 of 0 entries
                </div>
                <div id="pagination_container" class="pagination"></div>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
} else {
    $(document).ready(function() {
        var csrf_token = '{$csrf_token}';
        var refreshInterval;
        var isLoading = false;
        var allUsers = [];
        var currentPage = 1;
        var entriesPerPage = 10;
        var currentSort = { column: 'username', direction: 'asc' };
        var searchTerm = '';
        var currentAbort = null;
        
        function formatUptime(uptime) {
            if (!uptime || uptime === '0s') return '<span class="text-muted">0s</span>';
            return '<span title="' + uptime + '">' + uptime + '</span>';
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function sortUsers(users, column, direction) {
            return users.sort(function(a, b) {
                var valA = (a[column] || '').toLowerCase();
                var valB = (b[column] || '').toLowerCase();
                if (valA < valB) return direction === 'asc' ? -1 : 1;
                if (valA > valB) return direction === 'asc' ? 1 : -1;
                return 0;
            });
        }
        
        function filterUsers(users, term) {
            if (!term) return users;
            term = term.toLowerCase();
            return users.filter(function(user) {
                return (user.username && user.username.toLowerCase().indexOf(term) !== -1) ||
                       (user.ip_address && user.ip_address.toLowerCase().indexOf(term) !== -1) ||
                       (user.mac_address && user.mac_address.toLowerCase().indexOf(term) !== -1) ||
                       (user.router_name && user.router_name.toLowerCase().indexOf(term) !== -1);
            });
        }
        
        function renderTable() {
            var filteredUsers = filterUsers(allUsers, searchTerm);
            var sortedUsers = sortUsers(filteredUsers, currentSort.column, currentSort.direction);
            var totalUsers = sortedUsers.length;
            var totalPages = Math.ceil(totalUsers / entriesPerPage);
            
            if (currentPage > totalPages) currentPage = 1;
            var start = (currentPage - 1) * entriesPerPage;
            var end = start + entriesPerPage;
            var pageUsers = sortedUsers.slice(start, end);
            
            var html = '';
            
            if (pageUsers.length === 0) {
                html = '<div class="no-users-message">' +
                       '<i class="fas fa-users"></i><br>' +
                       'No online users found' +
                       '</div>';
            } else {
                html = '<table class="online-table" id="users_table">' +
                       '<thead>' +
                       '<tr>' +
                       '<th data-sort="username">Username <span class="sort-icon">' + (currentSort.column === 'username' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="ip_address">IP Address <span class="sort-icon">' + (currentSort.column === 'ip_address' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="mac_address">MAC Address <span class="sort-icon">' + (currentSort.column === 'mac_address' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="router_name">Router <span class="sort-icon">' + (currentSort.column === 'router_name' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="uptime">Uptime <span class="sort-icon">' + (currentSort.column === 'uptime' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="bytes_out">Download <span class="sort-icon">' + (currentSort.column === 'bytes_out' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="bytes_in">Upload <span class="sort-icon">' + (currentSort.column === 'bytes_in' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th>Action</th>' +
                       '</tr>' +
                       '</thead>' +
                       '<tbody>';
                
                $.each(pageUsers, function(i, user) {
                    html += '<tr>' +
                            '<td><strong>' + escapeHtml(user.username) + '</strong></td>' +
                            '<td><code>' + escapeHtml(user.ip_address) + '</code></td>' +
                            '<td><small>' + escapeHtml(user.mac_address) + '</small></td>' +
                            '<td><span class="label-info">' + escapeHtml(user.router_name) + '</span></td>' +
                            '<td>' + formatUptime(user.uptime) + '</td>' +
                            '<td><span class="text-success">' + escapeHtml(user.bytes_out) + '</span></td>' +
                            '<td><span class="text-danger">' + escapeHtml(user.bytes_in) + '</span></td>' +
                            '<td><button class="btn-disconnect" onclick="disconnectUser(\'' + escapeHtml(user.router_name) + '\',\'' + escapeHtml(user.username) + '\')"><i class="fas fa-power-off"></i> Disconnect</button></td>' +
                            '</tr>';
                });
                
                html += '</tbody></table>';
            }
            
            $('#users_table_container').html(html);
            
            var startNum = totalUsers === 0 ? 0 : start + 1;
            var endNum = Math.min(end, totalUsers);
            $('#table_info').text('Showing ' + startNum + ' to ' + endNum + ' of ' + totalUsers + ' entries');
            
            renderPagination(totalPages);
            $('#user_count').text(totalUsers);
            
            if (totalUsers > 0) {
                $('#router_status').html('<span class="online-badge"><i class="fas fa-users"></i> ' + totalUsers + ' active</span>');
            } else {
                $('#router_status').html('');
            }
        }
        
        function renderPagination(totalPages) {
            if (totalPages <= 1) {
                $('#pagination_container').html('');
                return;
            }
            
            var html = '';
            html += '<button onclick="changePage(1)" ' + (currentPage === 1 ? 'disabled' : '') + '>&laquo; First</button>';
            html += '<button onclick="changePage(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + '>&lsaquo; Prev</button>';
            
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);
            
            for (var i = startPage; i <= endPage; i++) {
                html += '<button onclick="changePage(' + i + ')" class="' + (i === currentPage ? 'active' : '') + '">' + i + '</button>';
            }
            
            html += '<button onclick="changePage(' + (currentPage + 1) + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '>Next &rsaquo;</button>';
            html += '<button onclick="changePage(' + totalPages + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '>Last &raquo;</button>';
            
            $('#pagination_container').html(html);
        }
        
        window.changePage = function(page) {
            currentPage = page;
            renderTable();
        };
        
        function loadUsers(opts) {
            opts = opts || {};
            // Cancel any in-flight request so a router change is always honored.
            if (currentAbort) {
                try { currentAbort.abort(); } catch (e) {}
                currentAbort = null;
            }
            isLoading = true;

            var routerId = $('#router_select').val();
            var url = window.location.pathname + '?_route=onlineusers/hotspot_data';
            if (routerId) {
                url += '&router_id=' + routerId;
            }
            url += (url.indexOf('?') === -1 ? '?' : '&') + '_ts=' + Date.now();

            if (opts.showSpinner !== false) {
                $('#users_table_container').html('<div class="no-users-message"><i class="fas fa-spinner fa-spin"></i><br>Loading hotspot users...</div>');
                $('#user_count').text('0');
                $('#router_status').html('');
                $('#table_info').text('Showing 0 to 0 of 0 entries');
                $('#pagination_container').html('');
            }

            var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            currentAbort = controller;
            // Hard timeout so a slow RouterOS socket never leaves the UI stuck.
            var timeoutId = setTimeout(function () {
                if (controller) { try { controller.abort(); } catch (e) {} }
            }, 20000);

            fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined,
                cache: 'no-store'
            })
            .then(function(response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                clearTimeout(timeoutId);
                currentAbort = null;
                isLoading = false;
                allUsers = data || [];
                currentPage = 1;
                renderTable();
            })
            .catch(function(error) {
                clearTimeout(timeoutId);
                // An abort from a newer change should NOT overwrite the UI
                // that the newer call is about to render.
                if (error && error.name === 'AbortError') { isLoading = false; return; }
                currentAbort = null;
                isLoading = false;
                console.error('Load error:', error);
                $('#users_table_container').html('<div class="no-users-message"><i class="fas fa-exclamation-triangle"></i><br>Error loading users</div>');
                $('#user_count').text('0');
                $('#router_status').html('');
                $('#table_info').text('Showing 0 to 0 of 0 entries');
                $('#pagination_container').html('');
            });
        }
        
        window.disconnectUser = function(routerName, username) {
            if (!confirm('Disconnect ' + username + '?')) return;
            
            var formData = new FormData();
            formData.append('csrf_token', csrf_token);
            formData.append('router', routerName);
            formData.append('username', username);
            formData.append('userType', 'hotspot');
            
            var btn = event.target.closest('button');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            fetch(window.location.pathname + '?_route=onlineusers/disconnect', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(resp) {
                loadUsers();
                alert(resp && resp.message ? resp.message : (resp.status === 'success' ? 'User disconnected' : 'Failed to disconnect'));
            })
            .catch(function(error) {
                console.error('Disconnect error:', error);
                loadUsers();
                alert('Server error');
            })
            .finally(function() {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        };
        
        $(document).on('click', '.online-table th', function() {
            var sortColumn = $(this).data('sort');
            if (sortColumn) {
                if (currentSort.column === sortColumn) {
                    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.column = sortColumn;
                    currentSort.direction = 'asc';
                }
                renderTable();
            }
        });
        
        $('#entries_per_page').on('change', function() {
            entriesPerPage = parseInt($(this).val());
            currentPage = 1;
            renderTable();
        });
        
        var searchTimeout;
        $('#search_input').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchTerm = $('#search_input').val();
                currentPage = 1;
                renderTable();
            }, 300);
        });
        
        $('#router_select').on('change', function() {
            // Reset UI state so old router's data doesn't linger and user sees
            // immediate feedback that their selection was received.
            allUsers = [];
            currentPage = 1;
            searchTerm = '';
            $('#search_input').val('');
            loadUsers({ showSpinner: true });
        });
        
        $('#refresh_btn').on('click', function() {
            $('#refresh_icon').addClass('refresh-spin');
            loadUsers();
            setTimeout(function() { $('#refresh_icon').removeClass('refresh-spin'); }, 500);
        });
        
        loadUsers();
        refreshInterval = setInterval(loadUsers, 30000);
        
        $(window).on('beforeunload', function() {
            if (refreshInterval) clearInterval(refreshInterval);
        });
    });
}
</script>

{include file="sections/footer.tpl"}
