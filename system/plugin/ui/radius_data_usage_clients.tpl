{include file="user-ui/header.tpl"}

<!-- Clean Simple Styling -->
<style>
    .card {
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 20px;
        background: white;
    }

    .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #ddd;
        padding: 15px;
        font-weight: 600;
    }

    .card-body {
        padding: 20px;
    }

    .chart-container {
        height: 350px;
        padding: 10px;
    }

    .stats-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
        margin-bottom: 20px;
    }

    .stats-value {
        font-size: 24px;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .stats-label {
        color: #666;
        font-weight: 500;
        font-size: 14px;
    }

    .form-control {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px 12px;
    }

    .btn {
        border-radius: 4px;
        padding: 8px 16px;
        border: 1px solid #ddd;
        background: white;
        color: #333;
    }

    .btn-primary {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }

    .table {
        background: white;
        border: 1px solid #ddd;
    }

    .table thead th {
        background: #f8f9fa;
        border-bottom: 1px solid #ddd;
        padding: 12px;
        font-weight: 600;
    }

    .table td {
        padding: 12px;
        border-top: 1px solid #eee;
    }

    .alert {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 20px;
    }

    .alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
    }
</style>

    .small-box > .inner {
        padding: 15px;
    }

    .small-box .icon {
        transition: all 0.3s linear;
        position: absolute;
        top: -10px;
        right: 10px;
        z-index: 0;
        font-size: 60px;
        color: rgba(0,0,0,0.15);
    }

    .small-box h3 {
        font-size: 2rem;
        font-weight: bold;
        margin: 0 0 10px 0;
        white-space: nowrap;
        padding: 0;
        color: white;
    }

    .small-box p {
        font-size: 14px;
        color: white;
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

    .card {
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        transition: all 0.3s cubic-bezier(.25,.8,.25,1);
        margin-bottom: 20px;
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

    .card-body {
        padding: 20px;
    }

    .chart-container {
        position: relative;
        height: 350px;
        padding: 10px;
    }

    .stats-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        margin-bottom: 20px;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 24px;
        color: white;
    }

    .download-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); }
    .upload-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .total-icon { background: linear-gradient(135deg, #10b981, #059669); }
    .source-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

    .stats-value {
        font-size: 24px;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .stats-label {
        color: #666;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 12px;
    }

    .form-control, .form-select {
        border-radius: 4px;
        border: 1px solid #ddd;
        padding: 8px 12px;
        transition: border-color 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
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

    .btn-success {
        background: linear-gradient(135deg, #28a745, #20923a);
        color: white;
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

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    .table {
        background: white;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .table thead th {
        background: #f8f9fa;
        color: #333;
        border: none;
        padding: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 12px;
    }

    .table tbody tr {
        transition: background-color 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(44, 90, 160, 0.05);
    }

    .table td {
        padding: 12px;
        border-color: #f1f1f1;
        vertical-align: middle;
    }

    .package-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .package-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        margin-bottom: 8px;
        background: #f8f9fa;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .package-item:hover {
        background: #e9ecef;
        transform: translateX(2px);
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active {
        background: rgba(40, 167, 69, 0.2);
        color: #155724;
    }

    .status-inactive {
        background: rgba(220, 53, 69, 0.2);
        color: #721c24;
    }

    .chart-controls {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .loading-spinner {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 200px;
    }

    .spinner {
        width: 32px;
        height: 32px;
        border: 3px solid #f3f4f6;
        border-top: 3px solid #2c5aa0;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .chart-container {
            height: 250px;
        }
        
        .stats-card {
            padding: 15px;
        }
        
        .stats-icon {
            width: 40px;
            height: 40px;
        }
        
        .stats-value {
            font-size: 20px;
        }
    }
</style>

<div class="row">
    <!-- Package Details -->
    <div class="col-lg-6">
        <div class="card fade-in">
                        <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-box"></i>
                    Current Package Details
                </h3>
            </div>
            <div class="card-body">
                {foreach $packages as $package}
                <div class="mb-4">
                    <h6 class="text-center mb-3 text-primary">{$package['type']} - {$package['namebp']}</h6>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <span><i class="fa fa-clock text-info"></i> Validity</span>
                            <span class="badge badge-primary">{$package['validity']} {$package['validity_unit']}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <span><i class="fa fa-money text-success"></i> Price</span>
                            <span class="badge badge-success">{Lang::moneyFormat($package['price'])}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <span><i class="fa fa-download text-info"></i> Download Speed</span>
                            <span class="badge badge-info">{$package['speed_dl']} Kbps</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <span><i class="fa fa-upload text-warning"></i> Upload Speed</span>
                            <span class="badge badge-warning">{$package['speed_ul']} Kbps</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <span><i class="fa fa-database text-secondary"></i> Data Limit</span>
                            <span class="badge badge-secondary">{if $package['data_limit'] > 0}{$package['data_limit']} MB{else}Unlimited{/if}</span>
                        </div>
                    </div>
                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded">
                                <small class="text-muted">Type</small>
                                <div class="font-weight-bold">{$package['type']}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded">
                                <small class="text-muted">Pool</small>
                                <div class="font-weight-bold">{$package['pool_name']}</div>
                            </div>
                        </div>
                    </div>
                </div>
                {/foreach}
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="col-lg-6">
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-filter"></i>
                    Filter Data Usages
                </h3>
            </div>
            <div class="card-body">
                <form action="{$_url}plugin/radius_data_usage_clients" method="post">
                    <div class="form-group mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="{$start_date}" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="{$end_date}" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="realtime" id="realtime" class="form-check-input" {if $realtime}checked{/if}>
                        <label for="realtime" class="form-check-label">Include real-time data</label>
                    </div>
                    <div class="d-grid gap-2 d-md-flex">
                        <button class="btn btn-primary" type="submit">
                            <i class="fa fa-search"></i> Filter Data
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetDates()">
                            <i class="fa fa-refresh"></i> Reset to Last 30 Days
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Daily Usage Chart -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card fade-in">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-chart-line"></i>
                    Daily Data Usage ({$start_date} to {$end_date})
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    Data for dates older than 30 days comes from historical records.
                </div>
                {if $chart_data}
                <div class="chart-container">
                    <canvas id="dataChart"></canvas>
                </div>
                <div class="chart-controls">
                    <button class="btn btn-outline-secondary btn-sm reset-zoom" data-chart="dataChart">
                        <i class="fa fa-search-minus"></i> Reset Zoom
                    </button>
                    <button class="btn btn-outline-secondary btn-sm export-chart" data-chart="dataChart">
                        <i class="fa fa-download"></i> Export PNG
                    </button>
                </div>
                {else}
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    No data available for the selected date range.
                </div>
                {/if}
            </div>
        </div>
    </div>
</div>
                    </div>
                    <div class="chart-controls">
                        <button class="btn btn-outline-secondary btn-sm reset-zoom" data-chart="dataChart">
                            <i class="fas fa-search-minus"></i>
                            {Lang::T('Reset Zoom')}
                        </button>
                        <button class="btn btn-outline-secondary btn-sm export-chart" data-chart="dataChart">
                            <i class="fas fa-download"></i>
                            {Lang::T('Export')}
                        </button>
                    </div>
                    {else}
                    <div class="alert alert-info">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {Lang::T('No daily data available for the selected period.')}
                    </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Usage and Breakdown Charts -->
    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card fade-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar"></i>
                        {Lang::T('Weekly Data Usage')}
                    </h3>
                </div>
                <div class="card-body">
                    {if $weekly_chart_data}
                    <div class="chart-container">
                        <canvas id="weeklyUsageChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-icon download-icon">
                                        <i class="fas fa-download"></i>
                                    </div>
                                    <div class="stats-value">{$weekly_total_download|radius_data_usage_formatBytes}</div>
                                    <div class="stats-label">{Lang::T('Total Download')}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-icon upload-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="stats-value">{$weekly_total_upload|radius_data_usage_formatBytes}</div>
                                    <div class="stats-label">{Lang::T('Total Upload')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {else}
                    <div class="alert alert-info">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {Lang::T('No weekly data available for the selected period.')}
                    </div>
                    {/if}
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card fade-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        {Lang::T('Usage Breakdown')}
                    </h3>
                </div>
                <div class="card-body">
                    {if $totalDownload > 0 || $totalUpload > 0}
                    <div class="chart-container">
                        <canvas id="totalUsageChart"></canvas>
                    </div>
                    {else}
                    <div class="alert alert-info">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {Lang::T('No usage data available for the selected period.')}
                    </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Summary -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card fade-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-area"></i>
                        {Lang::T('Usage Summary')}
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon download-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="stats-value">{$total_download_formatted}</div>
                                <div class="stats-label">{Lang::T('Total Download')}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon upload-icon">
                                    <i class="fas fa-upload"></i>
                                </div>
                                <div class="stats-value">{$total_upload_formatted}</div>
                                <div class="stats-label">{Lang::T('Total Upload')}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon total-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="stats-value">{$total_usage_formatted}</div>
                                <div class="stats-label">{Lang::T('Total Usage')}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-icon source-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="stats-value">
                                    {if $use_history}
                                        <i class="fas fa-history"></i>
                                    {else}
                                        <i class="fas fa-broadcast-tower"></i>
                                    {/if}
                                </div>
                                <div class="stats-label">
                                    {if $use_history}{Lang::T('Historical Records')}{else}{Lang::T('Live Data')}{/if}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Connections Table -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card fade-in">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-network-wired"></i>
                        {Lang::T('Active Connections')}
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{Lang::T('Username')}</th>
                                    <th>{Lang::T('Address')}</th>
                                    <th>{Lang::T('Uptime')}</th>
                                    <th>{Lang::T('Service')}</th>
                                    <th>{Lang::T('Caller ID')}</th>
                                    <th>{Lang::T('Download')}</th>
                                    <th>{Lang::T('Upload')}</th>
                                    <th>{Lang::T('Total')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {if isset($userTable) && $userTable}
                                    {foreach $userTable as $user}
                                        <tr>
                                            <td><strong>{$user.username|escape}</strong></td>
                                            <td><code>{$user.address|escape}</code></td>
                                            <td>{$user.uptime|escape}</td>
                                            <td><span class="badge bg-primary">{$user.service|escape}</span></td>
                                            <td>{$user.caller_id|escape}</td>
                                            <td><span class="text-info fw-semibold">{$user.tx|escape}</span></td>
                                            <td><span class="text-danger fw-semibold">{$user.rx|escape}</span></td>
                                            <td><span class="text-success fw-bold">{$user.total|escape}</span></td>
                                        </tr>
                                    {/foreach}
                                {else}
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-exclamation-circle fa-2x text-muted mb-3"></i>
                                            <div class="text-muted">{Lang::T('No active connections found')}</div>
                                        </td>
                                    </tr>
                                {/if}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple chart configuration
    Chart.defaults.font.family = "'Arial', sans-serif";
    Chart.defaults.color = '#333';
    Chart.defaults.borderColor = '#ddd';

    // Reset dates function
    window.resetDates = function() {
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        document.getElementById('start_date').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('end_date').value = today.toISOString().split('T')[0];
    };

    // Simple chart initialization
    function initChart(chartId, type, data, options) {
        const canvas = document.getElementById(chartId);
        if (!canvas) return null;

        try {
            const chart = new Chart(canvas, {
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
            return chart;
        } catch(e) {
            console.error('Chart error:', e);
            return null;
        }
    }

    // Initialize charts with data
    const dailyChartData = JSON.parse('{$chart_data|escape:"javascript"}');
    if(dailyChartData) {
        initChart('dataChart', 'line', dailyChartData);
    }

    const weeklyChartData = JSON.parse('{$weekly_chart_data|escape:"javascript"}');
    if(weeklyChartData) {
        initChart('weeklyUsageChart', 'bar', weeklyChartData);
    }

    // Total usage pie chart
    const totalDownload = {$totalDownload};
    const totalUpload = {$totalUpload};
    if(totalDownload > 0 || totalUpload > 0) {
        const totalData = {
            labels: ['Download', 'Upload'],
            datasets: [{
                data: [totalDownload, totalUpload],
                backgroundColor: ['#36a2eb', '#ff6384']
            }]
        };
        initChart('totalUsageChart', 'pie', totalData);
    }
});
</script>
            const ctx = canvas.getContext('2d');
            const chart = new Chart(ctx, {
                type: type,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    weight: '600'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: colors.primary,
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: true,
                            ...options.plugins?.tooltip
                        }
                    },
                    ...options
                }
            });

            // Remove loading spinner
            container.removeChild(loadingSpinner);
            return chart;
        } catch(e) {
            console.error('Error initializing chart ' + chartId + ':', e);
            container.removeChild(loadingSpinner);
            container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error loading chart data</div>';
            return null;
        }
    }

    // Safe JSON parsing with error handling
    function safeParseJson(jsonString, chartId) {
        try {
            return JSON.parse(jsonString);
        } catch(e) {
            console.error('Error parsing JSON for ' + chartId + ':', e);
            const container = document.getElementById(chartId) && document.getElementById(chartId).closest('.card-body');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Invalid chart data format</div>';
            }
            return null;
        }
    }

    // Daily Usage Chart with modern styling
    const dailyChartData = safeParseJson('{$chart_data|escape:"javascript"}', 'dataChart');
    if(dailyChartData) {
        const dataChart = initChart(
            'dataChart',
            'line',
            dailyChartData,
            {
                plugins: {
                    zoom: {
                        zoom: {
                            wheel: { enabled: true },
                            pinch: { enabled: true },
                            mode: 'xy',
                        },
                        pan: {
                            enabled: true,
                            mode: 'xy',
                            threshold: 10
                        },
                        limits: {
                            x: { min: 'original', max: 'original' },
                            y: { min: 'original', max: 'original' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw.toFixed(2) + ' MB';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '{Lang::T('Data Usage (MB)')}',
                            font: { weight: '600' }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '{Lang::T('Day of Month')}',
                            font: { weight: '600' }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                },
                elements: {
                    line: {
                        tension: 0.4,
                        borderWidth: 3
                    },
                    point: {
                        radius: 4,
                        hoverRadius: 8,
                        borderWidth: 2
                    }
                }
            }
        );
        
        // Store chart reference for controls
        window.dataChart = dataChart;
    }

    // Weekly Usage Chart
    const weeklyChartData = safeParseJson('{$weekly_chart_data|escape:"javascript"}', 'weeklyUsageChart');
    if(weeklyChartData) {
        const weeklyChart = initChart(
            'weeklyUsageChart',
            'bar',
            weeklyChartData,
            {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw.toFixed(2) + ' MB';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '{Lang::T('Data Usage (MB)')}',
                            font: { weight: '600' }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: '{Lang::T('Week')}',
                            font: { weight: '600' }
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    bar: {
                        borderRadius: 8,
                        borderSkipped: false
                    }
                }
            }
        );
    }

    // Total Usage Pie Chart with modern design
    const totalDownload = {$totalDownload};
    const totalUpload = {$totalUpload};
    if(totalDownload > 0 || totalUpload > 0) {
        const totalUsageChart = initChart(
            'totalUsageChart',
            'doughnut',
            {
                labels: ['{Lang::T('Download')}', '{Lang::T('Upload')}'],
                datasets: [{
                    data: [totalDownload, totalUpload],
                    backgroundColor: [
                        'rgba(6, 182, 212, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        colors.info,
                        colors.danger
                    ],
                    borderWidth: 3,
                    hoverBorderWidth: 5,
                    hoverOffset: 10
                }]
            },
            {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + percentage + '%';
                            }
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 30,
                            font: { size: 14, weight: '600' }
                        }
                    }
                },
                cutout: '60%',
                elements: {
                    arc: {
                        borderWidth: 0
                    }
                }
            }
        );
    }

    // Chart control functions
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('reset-zoom') || e.target.closest('.reset-zoom')) {
            const button = e.target.classList.contains('reset-zoom') ? e.target : e.target.closest('.reset-zoom');
            const chartId = button.getAttribute('data-chart');
            if (chartId === 'dataChart' && window.dataChart) {
                window.dataChart.resetZoom();
            }
        }
        
        if (e.target.classList.contains('export-chart') || e.target.closest('.export-chart')) {
            const button = e.target.classList.contains('export-chart') ? e.target : e.target.closest('.export-chart');
            const chartId = button.getAttribute('data-chart');
            const canvas = document.getElementById(chartId);
            if(canvas) {
                canvas.toBlob(function(blob) {
                    saveAs(blob, chartId + '-export.png');
                });
            }
        }
    });

    // Responsive chart handling
    window.addEventListener('resize', function() {
        // Charts auto-resize with Chart.js 4, but we can add custom logic here if needed
        console.log('Window resized - charts will auto-adjust');
    });

    // Add smooth scrolling for better UX
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    console.log('Modern RADIUS dashboard initialized successfully');
});
</script>

{include file="user-ui/footer.tpl"}