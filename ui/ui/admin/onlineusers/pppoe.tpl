{include file="sections/header.tpl"}

<!-- Load jQuery explicitly -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
    .online-panel .panel-heading { font-weight: 600; background-color: #f5f5f5; }
    .online-badge {
        background-color: #5cb85c;
        color: white;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        margin-left: 5px;
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
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .online-table th {
        background-color: #f9f9f9;
        font-weight: 600;
        cursor: pointer;
    }
    .online-table th:hover {
        background-color: #e9e9e9;
    }
    .online-table tr:hover {
        background-color: #f5f5f5;
    }
    .no-users-message {
        text-align: center;
        padding: 50px;
        color: #999;
        font-size: 16px;
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
    }
    .label-success {
        background-color: #5cb85c;
        padding: 3px 8px;
        border-radius: 3px;
        color: white;
        font-size: 11px;
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
    .btn-danger {
        background-color: #d9534f;
        border-color: #d43f3a;
        color: white;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        cursor: pointer;
        border: none;
    }
    .btn-danger:hover {
        background-color: #c9302c;
    }
    .btn-danger:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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
        background: white;
    }
    .search-control {
        display: flex;
        align-items: center;
        gap: 8px;
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
        flex-wrap: wrap;
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
    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 8px;
    }
    .status-online {
        background-color: #5cb85c;
        box-shadow: 0 0 3px #5cb85c;
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
        color: #fff;
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
    .btn-danger {
        border-radius: 8px;
        background-color: #dc2626;
    }
    .btn-danger:hover {
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
                <i class="fa fa-ethernet"></i> PPPoE Online Users
                <span id="user_count" class="badge" style="margin-left: 10px;">0</span>
                <span id="router_status" style="float: right; font-size: 12px;"></span>
            </div>
            <div class="panel-body">
                <div style="margin-bottom: 12px; color: #64748b; font-size: 12px;">
                    View active PPPoE sessions, usage and disconnect users if required.
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
                    <div class="col-md-3">
                        <label style="font-size: 12px; color: #475569; margin-bottom: 6px;">Actions</label>
                        <button id="refresh_btn" class="btn btn-primary form-control">
                            <i class="fa fa-refresh" id="refresh_icon"></i> Refresh
                        </button>
                    </div>
                    <div class="col-md-5">
                        <div id="connection_status" class="alert alert-info" style="margin-bottom:0; padding:8px; display:none;">
                            <i class="fa fa-info-circle"></i> <span id="status_message"></span>
                        </div>
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
                        <input type="text" id="search_input" placeholder="Search by username, IP, caller ID...">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <div id="users_table_container">
                        <div class="no-users-message">
                            <i class="fa fa-spinner fa-spin"></i><br>
                            Loading PPPoE users...
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
    document.write('<div class="alert alert-danger">jQuery failed to load. Please refresh the page.</div>');
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
        var lastLoadTime = 0;
        var cachedData = null;
        var currentXhr = null;
        
        function showStatus(message, isError) {
            var $status = $('#connection_status');
            var $message = $('#status_message');
            $message.html(message);
            $status.removeClass('alert-info alert-danger alert-warning');
            if (isError) {
                $status.addClass('alert-danger');
            } else {
                $status.addClass('alert-info');
            }
            $status.fadeIn();
            setTimeout(function() {
                $status.fadeOut();
            }, 3000);
        }
        
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
                       (user.address && user.address.toLowerCase().indexOf(term) !== -1) ||
                       (user.caller_id && user.caller_id.toLowerCase().indexOf(term) !== -1) ||
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
                       '<i class="fa fa-users"></i><br>' +
                       'No online PPPoE users found' +
                       '</div>';
            } else {
                html = '<table class="online-table" id="users_table">' +
                       '<thead>' +
                       '<tr>' +
                       '<th data-sort="username">Username <span class="sort-icon">' + (currentSort.column === 'username' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="address">IP Address <span class="sort-icon">' + (currentSort.column === 'address' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="uptime">Uptime <span class="sort-icon">' + (currentSort.column === 'uptime' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="service">Service <span class="sort-icon">' + (currentSort.column === 'service' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="caller_id">Caller ID <span class="sort-icon">' + (currentSort.column === 'caller_id' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="router_name">Router <span class="sort-icon">' + (currentSort.column === 'router_name' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="tx">Download <span class="sort-icon">' + (currentSort.column === 'tx' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="rx">Upload <span class="sort-icon">' + (currentSort.column === 'rx' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th data-sort="total">Total Usage <span class="sort-icon">' + (currentSort.column === 'total' ? (currentSort.direction === 'asc' ? '?' : '?') : '') + '</span></th>' +
                       '<th>Status</th>' +
                       '<th>Action</th>' +
                       '</tr>' +
                       '</thead>' +
                       '<tbody>';
                
                $.each(pageUsers, function(i, user) {
                    html += '<tr>' +
                            '<td><strong>' + escapeHtml(user.username) + '</strong></td>' +
                            '<td><code>' + escapeHtml(user.address) + '</code></td>' +
                            '<td>' + formatUptime(user.uptime) + '</td>' +
                            '<td><span class="label-info">' + escapeHtml(user.service || 'pppoe') + '</span></td>' +
                            '<td><small>' + escapeHtml(user.caller_id) + '</small></td>' +
                            '<td><span class="label-info">' + escapeHtml(user.router_name) + '</span></td>' +
                            '<td><span class="text-success">' + escapeHtml(user.tx) + '</span></td>' +
                            '<td><span class="text-danger">' + escapeHtml(user.rx) + '</span></td>' +
                            '<td><span class="text-primary">' + escapeHtml(user.total) + '</span></td>' +
                            '<td><span class="label-success">Connected</span></td>' +
                            '<td><button class="btn-danger" onclick="disconnectPppoeUser(\'' + escapeHtml(user.router_name) + '\',\'' + escapeHtml(user.username) + '\')"><i class="fa fa-power-off"></i> Disconnect</button></td>' +
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
                $('#router_status').html('<span class="online-badge"><i class="fa fa-users"></i> ' + totalUsers + ' active</span>');
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
            // Cancel any in-flight request so a router change always wins.
            if (currentXhr && currentXhr.readyState !== 4) {
                try { currentXhr.abort(); } catch (e) {}
            }
            isLoading = true;

            var routerId = $('#router_select').val();
            var url = '{$_url}onlineusers/pppoe_data';
            if (routerId) {
                url += '&router_id=' + routerId;
            }
            url += '&_ts=' + Date.now();

            if (opts.showSpinner === true || !cachedData) {
                $('#users_table_container').html('<div class="no-users-message"><i class="fa fa-spinner fa-spin"></i><br>Loading PPPoE users...</div>');
                $('#user_count').text('0');
                $('#router_status').html('');
                $('#table_info').text('Showing 0 to 0 of 0 entries');
                $('#pagination_container').html('');
            }

            currentXhr = $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                timeout: 20000,
                cache: false,
                success: function(data) {
                    isLoading = false;
                    currentXhr = null;
                    cachedData = data || [];
                    allUsers = cachedData;
                    lastLoadTime = Date.now();
                    currentPage = 1;
                    renderTable();
                    showStatus('Loaded ' + allUsers.length + ' PPPoE users', false);
                },
                error: function(xhr, error) {
                    // An abort from a newer change should not clobber UI.
                    if (error === 'abort') { isLoading = false; return; }
                    isLoading = false;
                    currentXhr = null;
                    console.error('Load error:', error);

                    if (cachedData && cachedData.length > 0 && !opts.showSpinner) {
                        allUsers = cachedData;
                        renderTable();
                        showStatus('Using cached data (last update: ' + new Date(lastLoadTime).toLocaleTimeString() + ')', true);
                    } else {
                        var errorMsg = 'Unable to load';
                        if (error === 'timeout') {
                            errorMsg = 'Request timed out. Router may be offline.';
                        } else if (xhr.status === 401) {
                            errorMsg = 'Session expired. Please refresh the page.';
                        } else if (xhr.status === 403) {
                            errorMsg = 'Permission denied';
                        }
                        $('#users_table_container').html('<div class="no-users-message"><i class="fa fa-exclamation-triangle"></i><br>' + errorMsg + '<br><small>Please check router connection</small></div>');
                        $('#user_count').text('0');
                        $('#router_status').html('');
                        $('#table_info').text('Showing 0 to 0 of 0 entries');
                        $('#pagination_container').html('');
                        showStatus(errorMsg, true);
                    }
                }
            });
        }
        
        window.disconnectPppoeUser = function(routerName, username) {
            if (!confirm('Are you sure you want to disconnect ' + username + '?')) return;
            
            $.ajax({
                url: '{$_url}onlineusers/disconnect',
                type: 'POST',
                data: {
                    csrf_token: csrf_token,
                    router: routerName,
                    username: username,
                    userType: 'pppoe'
                },
                dataType: 'json',
                timeout: 10000,
                success: function(resp) {
                    if (resp && resp.status === 'success') {
                        loadUsers();
                        showStatus('User ' + username + ' disconnected', false);
                    } else {
                        loadUsers();
                        showStatus(resp && resp.message ? resp.message : 'Failed to disconnect user', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Disconnect error:', error);
                    loadUsers();
                    showStatus('Server error: ' + error, true);
                }
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
        
        $('#search_input').on('keyup', function() {
            searchTerm = $(this).val();
            currentPage = 1;
            renderTable();
        });
        
        $('#router_select').on('change', function() {
            // Reset UI state so old router's data doesn't linger and the
            // user sees immediate feedback that the selection was received.
            cachedData = null;
            allUsers = [];
            currentPage = 1;
            searchTerm = '';
            $('#search_input').val('');
            loadUsers({ showSpinner: true });
        });
        
        $('#refresh_btn').on('click', function() {
            $('#refresh_icon').addClass('refresh-spin');
            loadUsers();
            setTimeout(function() {
                $('#refresh_icon').removeClass('refresh-spin');
            }, 500);
        });
        
        loadUsers();
        
        refreshInterval = setInterval(function() {
            loadUsers();
        }, 60000);
        
        $(window).on('beforeunload', function() {
            if (refreshInterval) clearInterval(refreshInterval);
        });
    });
}
</script>

{include file="sections/footer.tpl"}
