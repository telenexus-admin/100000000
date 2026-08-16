{include file="sections/header.tpl"}

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<style>
    .small-box {
        border-radius: 8px;
        margin-bottom: 20px;
        background: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .small-box > .inner {
        padding: 15px;
        position: relative;
        z-index: 2;
    }

    .small-box .icon {
        transition: all 0.3s linear;
        position: absolute;
        top: -10px;
        right: 10px;
        z-index: 1;
        font-size: 50px;
        color: rgba(255,255,255,0.15);
    }

    .small-box h3 {
        font-size: 1.8rem;
        font-weight: bold;
        margin: 0 0 10px 0;
        white-space: nowrap;
        padding: 0;
        color: white;
    }

    .small-box p {
        font-size: 14px;
        color: rgba(255,255,255,0.9);
        margin: 0;
    }

    .small-box-footer {
        position: relative;
        text-align: center;
        padding: 6px 0;
        color: rgba(255,255,255,0.8);
        display: block;
        z-index: 10;
        background: rgba(0,0,0,0.1);
        text-decoration: none;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
        transition: all 0.3s ease;
    }

    .small-box-footer:hover {
        color: white;
        background: rgba(0,0,0,0.2);
        text-decoration: none;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        min-height: 300px;
        margin-bottom: 1rem;
    }
    
    .card {
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        transition: all 0.3s cubic-bezier(.25,.8,.25,1);
        margin-bottom: 20px;
        background: white;
    }

    .card:hover {
        box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
    }
    
    .card-header {
        background: linear-gradient(135deg, #2c5aa0, #1e4080);
        color: white;
        border: none;
        border-radius: 8px 8px 0 0;
        padding: 15px 20px;
        font-weight: 600;
    }

    .card-title {
        margin: 0;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-card {
        display: flex;
        align-items: center;
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 0.35rem;
        background-color: white;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    }
    
    .info-card-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        margin-right: 1rem;
        color: white;
    }
    
    .info-card-content {
        flex: 1;
    }
    
    .info-card-title {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    
    .info-card-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .status-card {
        border-radius: 8px;
        color: white;
        padding: 15px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #2c5aa0, #1e4080);
        transition: all 0.3s ease;
    }

    .status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .status-card .icon {
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 50px;
        opacity: 0.15;
        color: white;
    }
    
    .status-card .value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: white;
    }
    
    .status-card .label {
        font-size: 14px;
        opacity: 0.9;
        color: white;
    }
    
    .bg-success { background: linear-gradient(135deg, #28a745, #20923a) !important; }
    .bg-danger { background: linear-gradient(135deg, #dc3545, #c82333) !important; }
    .bg-info { background: linear-gradient(135deg, #17a2b8, #138496) !important; }
    .bg-warning { background: linear-gradient(135deg, #ffc107, #e0a800) !important; }
    .bg-primary { background: linear-gradient(135deg, #007bff, #0056b3) !important; }
    
    .alert-modern {
        border-left: 4px solid;
        border-radius: 0.25rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .alert-info {
        background-color: rgba(54, 185, 204, 0.1);
        border-left-color: #17a2b8;
        color: #2c3e50;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.3rem 0.6rem;
        border: 1px solid #ddd;
        margin-left: 0.2rem;
        border-radius: 0.25rem;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #2c5aa0;
        color: white !important;
        border-color: #2c5aa0;
    }
    
    .btn-action {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 0.2rem;
    }
    
    .form-control {
        border-radius: 4px;
        border: 1px solid #ddd;
        padding: 8px 12px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus {
        border-color: #2c5aa0;
        box-shadow: 0 0 0 2px rgba(44, 90, 160, 0.2);
    }

    .btn {
        border-radius: 4px;
        padding: 8px 16px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2c5aa0, #1e4080);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1e4080, #1a3670);
        transform: translateY(-1px);
    }

    .btn-info {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
    }

    .btn-outline-secondary {
        border: 1px solid #ddd;
        color: #333;
        background: white;
    }

    .btn-outline-secondary:hover {
        background: #f8f9fa;
        border-color: #2c5aa0;
        color: #2c5aa0;
    }

    .alert {
        border-radius: 6px;
        border: none;
        padding: 12px 16px;
        margin-bottom: 20px;
    }

    .alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border-left: 4px solid #17a2b8;
    }

    .table {
        background: white;
        margin-bottom: 0;
    }

    .table thead th {
        background: #f8f9fa;
        border-bottom: 2px solid #ddd;
        padding: 12px;
        font-weight: 600;
        color: #333;
    }

    .table td {
        padding: 12px;
        border-top: 1px solid #eee;
        vertical-align: middle;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,.075);
    }
    
    /* Icon fixes for Font Awesome 4 */
    .fa {
        display: inline-block !important;
        font: normal normal normal 14px/1 FontAwesome !important;
        font-size: inherit !important;
        text-rendering: auto !important;
        -webkit-font-smoothing: antialiased !important;
        -moz-osx-font-smoothing: grayscale !important;
    }

    /* Ensure icons are visible */
    .small-box .icon i,
    .info-card-icon i,
    .btn i {
        opacity: 1 !important;
        visibility: visible !important;
    }

    /* Fix for missing data display */
    .small-box h3:empty:before {
        content: "0";
        color: white;
    }

    .info-card-value:empty:before {
        content: "N/A";
        color: #6c757d;
    }

    @media (max-width: 768px) {
        .chart-container {
            height: 250px;
        }
        
        .status-card .icon, .small-box .icon {
            font-size: 35px;
        }

        .small-box h3 {
            font-size: 1.5rem;
        }
    }
</style>

<!-- Filter Section -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">{Lang::T('Filter Data Usages')}</h6>
                <button type="button" class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="fa fa-minus"></i>
                </button>
            </div>
            <div class="card-body collapse show" id="filterCollapse">
                <form action="{$_url}plugin/radius_data_usage" method="post" class="filter-form">
                    <div class="mb-3">
                        <label for="start_date" class="form-label">{Lang::T('Start Date')}:</label>
                        <input class="form-control" value="{$start_date}" type="date" id="start_date" name="start_date" max="{$smarty.now|date_format:'%Y-%m-%d'}">
                    </div>
                    <div class="mb-3">
                        <label for="end_date" class="form-label">{Lang::T('End Date')}:</label>
                        <input class="form-control" value="{$end_date}" type="date" id="end_date" name="end_date" max="{$smarty.now|date_format:'%Y-%m-%d'}">
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa fa-filter"></i> {Lang::T('Filter')}
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetDates()">
                        <i class="fa fa-calendar"></i> {Lang::T('Last 30 Days')}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Status Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-xs-6">
        <div class="small-box" style="background: linear-gradient(135deg, #2c5aa0, #1e4080); color: white;">
            <div class="inner">
                <h3 style="color: white;">{if isset($totalCount)}{$totalCount}{else}0{/if}</h3>
                <p style="color: white;">Online Radius Users</p>
            </div>
            <div class="icon">
                <i class="fa fa-users" style="color: rgba(255,255,255,0.15);"></i>
            </div>
            <a href="{$_url}plugin/radon_users" class="small-box-footer" style="background: rgba(0,0,0,0.1); color: white;">View Details</a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
            <div class="inner">
                <h3 style="color: white;">{if $failedLogin}{$failedLogin}{else}0{/if}</h3>
                <p style="color: white;">Failed Login</p>
            </div>
            <div class="icon">
                <i class="fa fa-lock" style="color: rgba(255,255,255,0.15);"></i>
            </div>
            <a href="{$_url}logs/message" class="small-box-footer" style="background: rgba(0,0,0,0.1); color: white;">More Info</a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box" style="background: linear-gradient(135deg, {if $radiusStatus == 'online'}#28a745, #20923a{else}#dc3545, #c82333{/if}); color: white;">
            <div class="inner">
                <h3 style="color: white;">{if isset($radiusStatus)}{Lang::T(ucfirst($radiusStatus))}{else}Offline{/if}</h3>
                <p style="color: white;">Radius Status</p>
            </div>
            <div class="icon">
                <i class="fa fa-power-off" style="color: rgba(255,255,255,0.15);"></i>
            </div>
            <a href="{$_url}plugin/radius_data_usage/status" class="small-box-footer" style="background: rgba(0,0,0,0.1); color: white;">View Details</a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box" style="background: linear-gradient(135deg, #ffc107, #e0a800); color: white;">
            <div class="inner">
                <h3 style="color: white;">{if $radiusAcct}{$radiusAcct}{else}0{/if}</h3>
                <p style="color: white;">Radius Accounts</p>
            </div>
            <div class="icon">
                <i class="fa fa-user-friends" style="color: rgba(255,255,255,0.15);"></i>
            </div>
            <a href="{$_url}plugin/radius_data_usage/accounts" class="small-box-footer" style="background: rgba(0,0,0,0.1); color: white;">View Details</a>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mb-4">
    <div class="col-lg-12">
        <!-- Daily Usage Chart -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">{Lang::T('Daily Data Usage')} ({Lang::T('From')} {$start_date} {Lang::T('To')} {$end_date})</h6>
                <div>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-link text-dark dropdown-toggle" type="button" id="dailyChartMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dailyChartMenu">
                            <li><a class="dropdown-item reset-zoom" href="#" data-chart="dailyChart">{Lang::T('Reset Zoom')}</a></li>
                            <li><a class="dropdown-item export-chart" href="#" data-chart="dailyChart">{Lang::T('Export as Image')}</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#dailyChartCollapse">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body collapse show" id="dailyChartCollapse">
                <div class="alert alert-modern alert-info">
                    <i class="fa fa-info-circle"></i> {Lang::T('Data for dates older than 30 days comes from historical records.')}
                </div>
                {if $daily_chart_data}
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    <button class="btn btn-sm btn-outline-secondary me-2 reset-zoom" data-chart="dailyChart">
                        <i class="fa fa-search"></i> {Lang::T('Reset Zoom')}
                    </button>
                    <button class="btn btn-sm btn-outline-secondary export-chart" data-chart="dailyChart">
                        <i class="fa fa-download"></i> {Lang::T('Export')}
                    </button>
                </div>
                {else}
                <div class="alert alert-modern alert-info">
                    {Lang::T('No daily data available for the selected period.')}
                </div>
                {/if}
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <!-- Weekly Usage Chart -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">{Lang::T('Weekly Data Usage')}</h6>
                <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#weeklyChartCollapse">
                    <i class="fa fa-minus"></i>
                </button>
            </div>
            <div class="card-body collapse show" id="weeklyChartCollapse">
                {if $weeklyData}
                <div class="chart-container">
                    <canvas id="weeklyChart"></canvas>
                </div>
                {else}
                <div class="alert alert-modern alert-info">
                    {Lang::T('No weekly data available for the selected period.')}
                </div>
                {/if}
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <!-- Monthly Usage Chart -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">{Lang::T('Monthly Data Usage')}</h6>
                <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#monthlyChartCollapse">
                    <i class="fa fa-minus"></i>
                </button>
            </div>
            <div class="card-body collapse show" id="monthlyChartCollapse">
                {if $monthlyData}
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
                {else}
                <div class="alert alert-modern alert-info">
                    {Lang::T('No monthly data available for the selected period.')}
                </div>
                {/if}
            </div>
        </div>
    </div>
</div>

<!-- System Info Cards -->
<div class="row mb-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">{Lang::T('System Information')}</h6>
                <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#systemInfoCollapse">
                    <i class="fa fa-minus"></i>
                </button>
            </div>
            <div class="card-body collapse show" id="systemInfoCollapse">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-card-icon bg-primary">
                                <i class="fa fa-dashboard"></i>
                            </div>
                            <div class="info-card-content">
                                <div class="info-card-title">{Lang::T('Total Bandwidth Used')}</div>
                                <div class="info-card-value">
                                    {if $totalUsage}
                                        {radius_data_usage_formatBytes($totalUsage->total_bytes)}
                                    {else}
                                        0 B
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-card-icon bg-success">
                                <i class="fa fa-calendar"></i>
                            </div>
                            <div class="info-card-content">
                                <div class="info-card-title">{Lang::T('Data Last Updated')}</div>
                                <div class="info-card-value">
                                    {if $lastUpdated}
                                        {$lastUpdated|date_format:"%Y-%m-%d %H:%M:%S"}
                                    {else}
                                        N/A
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">{Lang::T('Radius Users Data History')}</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="historyTable">
                        <thead class="table-light">
                            <tr>
                                <th>{Lang::T('Username')}</th>
                                <th>{Lang::T('NAS')}</th>
                                <th>{Lang::T('Type')}</th>
                                <th>{Lang::T('IP Address')}</th>
                                <th>{Lang::T('MAC Address')}</th>
                                <th>{Lang::T('Uptime')}</th>
                                <th>{Lang::T('Upload')}</th>
                                <th>{Lang::T('Download')}</th>
                                <th class="text-end">{Lang::T('Manage')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if isset($radiusUsers) && $radiusUsers}
                                {foreach $radiusUsers as $radiusUser}
                                    <tr>
                                        <td><a href="{$_url}customers/viewu/{$radiusUser['username']|escape}" class="text-primary">{$radiusUser['username']|escape}</a></td>
                                        <td>{$radiusUser['nasipaddress']|escape}</td>
                                        <td>{$radiusUser['calledstationid']|escape}</td>
                                        <td>{$radiusUser['framedipaddress']|escape}</td>
                                        <td>{$radiusUser['callingstationid']|escape}</td>
                                        <td>{radius_data_usage_secondsToTime($radiusUser['acctsessiontime'])}</td>
                                        <td>{radius_data_usage_formatBytes($radiusUser['acctinputoctets'])}</td>
                                        <td>{radius_data_usage_formatBytes($radiusUser['acctoutputoctets'])}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{$_url}plugin/radius_data_usage/{$radiusUser['username']|escape}" class="btn-action btn btn-sm btn-info" title="{Lang::T('View Data Usage')}">
                                                    <i class="fa fa-area-chart"></i>
                                                </a>
                                                <a href="{$_url}customers/viewu/{$radiusUser['username']|escape}" class="btn-action btn btn-sm btn-success" title="{Lang::T('View User Details')}">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <a href="{$_url}plugin/radius_data_usage/disconnect/{$radiusUser['username']|escape}" class="btn-action btn btn-sm btn-danger" title="{Lang::T('Disconnect User')}" onclick="return confirm('{Lang::T('Disconnect User?')}')">
                                                    <i class="fa fa-power-off"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="9" class="text-center py-4">{Lang::T('No active Radius users found')}</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
// Simple reset dates function
function resetDates() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    document.getElementById('start_date').value = thirtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('end_date').value = today.toISOString().split('T')[0];
}

// Initialize charts and table
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with basic styling - check if jQuery DataTable is available
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        try {
            $('#historyTable').DataTable({
                responsive: true,
                order: [[0, 'asc']],
                language: {
                    emptyTable: "No data available in table",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    lengthMenu: "Show _MENU_ entries",
                    search: "Search:",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        } catch(e) {
            console.log('DataTable initialization failed:', e);
        }
    }

    // Simple chart initialization - check if Chart.js is available
    function initChart(chartId, type, data, options) {
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not loaded, skipping chart initialization');
            // Add fallback message
            const ctx = document.getElementById(chartId);
            if (ctx) {
                const parent = ctx.parentElement;
                parent.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> Chart.js library not loaded. Charts are not available.</div>';
            }
            return null;
        }

        const ctx = document.getElementById(chartId);
        if (!ctx) return null;
        
        try {
            return new Chart(ctx, {
                type: type,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        } catch(e) {
            console.error('Chart error:', e);
            const parent = ctx.parentElement;
            parent.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Chart initialization failed: ' + e.message + '</div>';
            return null;
        }
    }

    // Daily Chart - with error handling
    try {
        {if $daily_chart_data}
        const dailyChartData = {$daily_chart_data};
        if(dailyChartData && dailyChartData.labels && dailyChartData.labels.length > 0) {
            initChart('dailyChart', 'line', dailyChartData);
        } else {
            const ctx = document.getElementById('dailyChart');
            if (ctx) {
                ctx.parentElement.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> No daily usage data available for the selected period.</div>';
            }
        }
        {else}
        const ctx = document.getElementById('dailyChart');
        if (ctx) {
            ctx.parentElement.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> No daily usage data available for the selected period.</div>';
        }
        {/if}
    } catch(e) {
        console.error('Daily chart data error:', e);
        const ctx = document.getElementById('dailyChart');
        if (ctx) {
            ctx.parentElement.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Error loading daily chart data.</div>';
        }
    }

    // Weekly Chart
    {if $weeklyData}
    const weeklyChartData = {
        labels: [{foreach $weeklyData as $data}"{$data.WeekLabel|escape:'javascript'}"{if !$data@last},{/if}{/foreach}],
        datasets: [
            {
                label: 'Download (MB)',
                data: [{foreach $weeklyData as $data}{$data.TotalDownloadMB}{if !$data@last},{/if}{/foreach}],
                backgroundColor: '#36a2eb'
            },
            {
                label: 'Upload (MB)',
                data: [{foreach $weeklyData as $data}{$data.TotalUploadMB}{if !$data@last},{/if}{/foreach}],
                backgroundColor: '#ff6384'
            }
        ]
    };
    if(weeklyChartData.labels.length > 0) {
        initChart('weeklyChart', 'bar', weeklyChartData);
    }
    {/if}

    // Monthly Chart
    {if $monthlyData}
    const monthlyChartData = {
        labels: [
            {foreach $monthlyData as $data}
                "{$data.Month|escape:'javascript'} {$data.Year|escape:'javascript'}"{if !$data@last},{/if}
            {/foreach}
        ],
        datasets: [
            {
                label: 'Download (MB)',
                data: [{foreach $monthlyData as $data}{$data.TotalDownloadMB}{if !$data@last},{/if}{/foreach}],
                backgroundColor: '#36a2eb'
            },
            {
                label: 'Upload (MB)',
                data: [{foreach $monthlyData as $data}{$data.TotalUploadMB}{if !$data@last},{/if}{/foreach}],
                backgroundColor: '#ff6384'
            }
        ]
    };
    if(monthlyChartData.labels.length > 0) {
        initChart('monthlyChart', 'bar', monthlyChartData);
    }
    {/if}
});
</script>

{include file="sections/footer.tpl"}