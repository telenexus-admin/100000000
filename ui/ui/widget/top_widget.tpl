<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title>Mikrotik Monitor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #a7f3d0;
            --accent-soft: #f0fdf4;
            --card-border: rgba(16, 185, 129, 0.12);
            --bg-gradient-start: #0f172a;
            --bg-gradient-end: #1e293b;
            --card-bg: rgba(255, 255, 255, 0.95);
            --text-primary: #1e293b;
            --text-secondary: #475569;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0b1120 0%, #1a2639 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 0.75rem;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(16, 185, 129, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(16, 185, 129, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 90% 30%, rgba(16, 185, 129, 0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(16, 185, 129, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: 0;
        }
        
        .dashboard-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .router-filter-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 22px;
            padding: 1rem 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .router-select-custom {
            border: 2px solid rgba(16, 185, 129, 0.2);
            border-radius: 14px;
            padding: 0.6rem 1rem;
            background: rgba(255, 255, 255, 0.9);
            color: #1e293b;
            font-weight: 500;
            outline: none;
            min-width: 260px;
            backdrop-filter: blur(4px);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .router-select-custom:hover {
            border-color: var(--primary);
            background: white;
        }
        .router-select-custom:focus { 
            border-color: var(--primary); 
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .status-online {
            color: #10b981;
        }
        
        .status-offline {
            color: #ef4444;
        }
        
        .sales-gradient {
            background: linear-gradient(145deg, #10b981 0%, #047857 100%);
            box-shadow: 0 16px 24px -10px rgba(4,120,87,0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .insight-icon-base {
            width: 44px; height: 44px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.3rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .masked-amount { letter-spacing: 6px; font-family: 'SF Mono', 'Fira Code', monospace; font-weight: 600; }
        .real-value { display: none; }
        
        .eye-icon {
            width: 38px; height: 38px; background: rgba(255,255,255,0.2);
            border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.15s; cursor: pointer; color: white;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .eye-icon:hover { background: rgba(255,255,255,0.35); transform: scale(0.96); }
        
        .insight-item-custom {
            background: rgba(240, 253, 244, 0.8);
            backdrop-filter: blur(4px);
            border-radius: 20px;
            padding: 1rem 1.2rem;
            border: 1px solid rgba(16, 185, 129, 0.15);
            transition: all 0.15s;
        }
        .insight-item-custom:hover {
            background: white;
            border-color: var(--primary-light);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }
        a.insight-item-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        a.insight-item-link:hover {
            text-decoration: none;
            color: inherit;
        }
        .insight-item-expired {
            background: rgba(254, 242, 242, 0.9);
            border-color: rgba(239, 68, 68, 0.2);
        }
        .insight-item-expired:hover {
            background: white;
            border-color: #fca5a5;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }
        
        .stat-block-mod {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.2rem 0.8rem;
            border-left: 5px solid var(--primary);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(145deg, #10b981, #059669);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(145deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.4);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
            color: #1e293b;
            padding: 0.6rem 1.2rem;
            border-radius: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(16, 185, 129, 0.2);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background: white;
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 640px) {
            .stat-value-large { font-size: 28px; }
            .router-filter-card { padding: 1rem; }
            .router-select-custom { min-width: 200px; }
        }
        
        .loading { opacity: 0.6; pointer-events: none; position: relative; }
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 24px;
            height: 24px;
            margin-left: -12px;
            margin-top: -12px;
            border: 3px solid rgba(16, 185, 129, 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            z-index: 10;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .pulse-animation {
            animation: quickPulse 0.5s ease-in-out;
        }
        
        @keyframes quickPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .user-insight-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(16, 185, 129, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .source-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary-dark);
            display: inline-block;
        }
        
        .selected-router-badge {
            background: rgba(16, 185, 129, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .router-status-pill {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.4rem 0.85rem;
            border-radius: 9999px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .router-status-pill .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .router-status-pill.is-all {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .router-status-pill.is-all .status-dot { background: #94a3b8; }
        .router-status-pill.is-online {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .router-status-pill.is-online .status-dot { background: #10b981; }
        .router-status-pill.is-offline {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .router-status-pill.is-offline .status-dot { background: #ef4444; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <div id="alert-container" class="mb-3"></div>

    <!-- ========== ROUTER FILTER - AUTO UPDATE ON SELECT ========== -->
    <div class="router-filter-card mb-5">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <h2 class="font-semibold text-gray-800">Router</h2>
                <p class="text-xs text-gray-500">Select router to filter data</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <select id="router-select" class="router-select-custom w-full sm:w-80 text-sm">
                    <option value="all" data-status="">All Routers</option>
                    {if isset($routers) && $routers|count > 0}
                        {foreach $routers as $router}
                            <option value="{$router.id}" data-status="{$router.status|escape}" data-name="{$router.name|escape}">
                                {if $router.status == 'Online'}Online{else}Offline{/if} - {$router.name|escape}
                            </option>
                        {/foreach}
                    {else}
                        <option value="" disabled="disabled">No routers configured</option>
                    {/if}
                </select>
                <span id="router-status-badge" class="router-status-pill is-all" aria-live="polite">
                    <span class="status-dot" aria-hidden="true"></span>
                    <span id="router-status-label">All Routers</span>
                </span>
                <button id="refresh-btn" class="btn-secondary" type="button">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- ========== MAIN GRID ========== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- LEFT COLUMN: sales + online stats -->
        <div class="lg:col-span-2 space-y-6">
            <!-- TODAY & MONTHLY revenue cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="sales-gradient rounded-2xl p-5 text-white relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-xs font-medium uppercase tracking-wider opacity-90 mb-2">
                            <i class="fas fa-calendar-day mr-1"></i> TODAY'S REVENUE
                        </p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-medium">KES</span>
                                <span class="text-4xl font-bold" id="today-sales-display">
                                    <span class="masked-amount" id="today-sales-mask">*****</span>
                                    <span class="real-value" id="today-sales-real">0</span>
                                </span>
                            </div>
                            <button onclick="toggleVisibility('today')" class="eye-icon">
                                <i class="fas fa-eye" id="today-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sales-gradient rounded-2xl p-5 text-white relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-xs font-medium uppercase tracking-wider opacity-90 mb-2">
                            <i class="fas fa-calendar-alt mr-1"></i> MONTHLY INCOME
                        </p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-medium">KES</span>
                                <span class="text-4xl font-bold" id="month-sales-display">
                                    <span class="masked-amount" id="month-sales-mask">*****</span>
                                    <span class="real-value" id="month-sales-real">0</span>
                                </span>
                            </div>
                            <button onclick="toggleVisibility('month')" class="eye-icon">
                                <i class="fas fa-eye" id="month-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- three stat cards (total online, hotspot, pppoe) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="stat-block-mod text-center">
                    <div class="stat-value-large text-3xl md:text-4xl font-bold text-green-600" id="total-online-users">
                        <i class="fas fa-spinner fa-spin text-sm"></i>
                    </div>
                    <div class="stat-label text-xs font-semibold text-green-600 uppercase mt-1">
                        <i class="fas fa-users mr-1"></i> TOTAL ONLINE
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">Active connections</div>
                </div>
                
                <div class="stat-block-mod text-center" style="border-left-color: #eab308;">
                    <div class="stat-value-large text-3xl md:text-4xl font-bold text-yellow-600" id="online-hotspot-users">
                        <i class="fas fa-spinner fa-spin text-sm"></i>
                    </div>
                    <div class="stat-label text-xs font-semibold text-yellow-600 uppercase mt-1">
                        <i class="fas fa-wifi mr-1"></i> HOTSPOT
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">WiFi users</div>
                </div>
                
                <div class="stat-block-mod text-center" style="border-left-color: #a855f7;">
                    <div class="stat-value-large text-3xl md:text-4xl font-bold text-purple-600" id="online-pppoe-users">
                        <i class="fas fa-spinner fa-spin text-sm"></i>
                    </div>
                    <div class="stat-label text-xs font-semibold text-purple-600 uppercase mt-1">
                        <i class="fas fa-network-wired mr-1"></i> PPPOE
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">Connections</div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: USER INSIGHT card -->
        <div class="user-insight-card rounded-3xl p-5 shadow-lg">
            <div class="flex items-center gap-3 mb-5">
                <div class="p-2.5 bg-green-100 rounded-xl">
                    <i class="fas fa-chart-pie text-[var(--primary)] text-lg"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">User Insight</h3>
            </div>

            <div class="space-y-3">
                <div class="insight-item-custom flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="insight-icon-base" style="background: linear-gradient(145deg, #10b981, #059669);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Active Accounts</p>
                            <p class="text-xs text-gray-500">With active package</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-gray-800" id="active-accounts">{$active_accounts|default:0}</span>
                </div>

                <a id="expired-pppoe-link" href="{Text::url('plan/list&status=off&type=PPPOE')}" class="insight-item-custom insight-item-link insight-item-expired" title="View expired PPPoE clients">
                    <div class="flex items-center gap-3">
                        <div class="insight-icon-base" style="background: linear-gradient(145deg, #ef4444, #dc2626);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Expired PPPoE</p>
                            <p class="text-xs text-gray-500">Expired clients · click to view</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-red-600" id="expired-pppoe">{$expired_pppoe|default:0}</span>
                </a>
                
                <div class="insight-item-custom flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="insight-icon-base" style="background: linear-gradient(145deg, #34d399, #10b981);">
                            <i class="fas fa-database"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Total Users</p>
                            <p class="text-xs text-gray-500">Registered customers</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-gray-800" id="total-users">{$c_all|default:0}</span>
                </div>
                
                <div class="insight-item-custom flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="insight-icon-base" style="background: linear-gradient(145deg, #6ee7b7, #34d399);">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Online Routers</p>
                            <p class="text-xs text-gray-500">Connected to system</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-gray-800" id="online-routers">{$online_routers|default:0}/{$routers|count|default:0}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Format number with commas
function formatNumber(num) {
    if (num === undefined || num === null) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Eye toggle functionality
function toggleVisibility(type) {
    const mask = document.getElementById(type + '-sales-mask');
    const real = document.getElementById(type + '-sales-real');
    const eye = document.getElementById(type + '-eye');
    
    if (mask.style.display !== 'none') {
        mask.style.display = 'none';
        real.style.display = 'inline';
        eye.classList.remove('fa-eye');
        eye.classList.add('fa-eye-slash');
    } else {
        mask.style.display = 'inline';
        real.style.display = 'none';
        eye.classList.remove('fa-eye-slash');
        eye.classList.add('fa-eye');
    }
}

// Show loading state
function showLoading() {
    $('.stat-block-mod, .insight-item-custom, .sales-gradient').addClass('loading');
}

// Hide loading state
function hideLoading() {
    $('.stat-block-mod, .insight-item-custom, .sales-gradient').removeClass('loading');
}

// Pulse animation on update
function pulseElement(elementId) {
    $('#' + elementId).addClass('pulse-animation');
    setTimeout(function() { 
        $('#' + elementId).removeClass('pulse-animation'); 
    }, 500);
}

let currentRouterId = 'all';
const expiredPppoeBaseUrl = "{Text::url('plan/list&status=off&type=PPPOE')}";

function updateExpiredPppoeLink(routerName) {
    var url = expiredPppoeBaseUrl;
    if (routerName) {
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        url += sep + 'router=' + encodeURIComponent(routerName);
    }
    $('#expired-pppoe-link').attr('href', url);
}

function getSelectedRouterName() {
    var val = $('#router-select').val();
    if (!val || val === 'all') {
        return null;
    }
    return $('#router-select option:selected').attr('data-name') || null;
}

// Update router status badge
function updateRouterStatusBadge(apiStatus) {
    var $b = $('#router-status-badge');
    var $lbl = $('#router-status-label');
    var val = $('#router-select').val();
    
    $b.removeClass('is-all is-online is-offline');
    
    if (!val || val === 'all') {
        $b.addClass('is-all');
        $lbl.text('All Routers');
        return;
    }
    
    var st;
    if (apiStatus === 'Online' || apiStatus === 'Offline') {
        st = apiStatus;
    } else {
        st = $('#router-select option:selected').attr('data-status');
    }
    
    if (st === 'Online') {
        $b.addClass('is-online');
        $lbl.text('Online');
    } else {
        $b.addClass('is-offline');
        $lbl.text('Offline');
    }
}

// Load online users data asynchronously
function loadOnlineUsersData(routerId = 'all') {
    console.log('Loading online users data for router:', routerId);
    
    $('#total-online-users').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#online-hotspot-users').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#online-pppoe-users').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#active-accounts').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#expired-pppoe').html('<i class="fas fa-spinner fa-spin"></i>');
    
    var baseUrl = window.location.href.split('?')[0];
    var url = baseUrl + '?_route=dashboard&ajax=online_users';
    if (routerId && routerId !== 'all') {
        url += '&router_id=' + encodeURIComponent(routerId);
    }
    
    console.log('Online users API URL:', url);
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        timeout: 20000,
        success: function(response) {
            console.log('Online users data received:', response);
            
            if (response && response.success && response.data) {
                var totalOnline = response.data.online || 0;
                var hotspotOnline = response.data.hotspot_online || 0;
                var pppoeOnline = response.data.pppoe_online || 0;
                var totalActive = response.data.total_active_accounts || 0;
                var expiredPppoe = response.data.expired_pppoe || 0;
                
                $('#total-online-users').text(totalOnline);
                $('#online-hotspot-users').text(hotspotOnline);
                $('#online-pppoe-users').text(pppoeOnline);
                
                $('#active-accounts').text(totalActive);
                $('#expired-pppoe').text(expiredPppoe);
                updateExpiredPppoeLink(response.data.selected_router_name || getSelectedRouterName());
                
                if (response.online_routers !== undefined && response.total_routers !== undefined) {
                    $('#online-routers').text(response.online_routers + '/' + response.total_routers);
                }
                
                if (response.data.selected_router_status) {
                    updateRouterStatusBadge(response.data.selected_router_status);
                }
                
                pulseElement('total-online-users');
                pulseElement('online-hotspot-users');
                pulseElement('online-pppoe-users');
                pulseElement('expired-pppoe');
            } else {
                console.error('Invalid response format:', response);
                $('#total-online-users').text('0');
                $('#online-hotspot-users').text('0');
                $('#online-pppoe-users').text('0');
                $('#active-accounts').text('0');
                $('#expired-pppoe').text('0');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading online users:', error);
            $('#total-online-users').text('0');
            $('#online-hotspot-users').text('0');
            $('#online-pppoe-users').text('0');
            $('#active-accounts').text('0');
            $('#expired-pppoe').text('0');
        }
    });
}

// Load sales data
function loadSalesData(routerId = 'all', showLoader = true) {
    if (showLoader) showLoading();
    
    var filterRouter = routerId || $('#router-select').val();
    currentRouterId = filterRouter;
    console.log('Loading sales data for router:', filterRouter);
    
    var baseUrl = window.location.href.split('?')[0];
    var url = baseUrl + '?_route=dashboard&router_id=' + encodeURIComponent(filterRouter);
    
    console.log('Sales API URL:', url);
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        timeout: 10000,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            console.log('Sales data received:', response);
            
            if (response && response.success) {
                if (response.today_sales !== undefined) {
                    var todayAmount = Math.round(parseFloat(response.today_sales));
                    var formatted = formatNumber(todayAmount);
                    $('#today-sales-real').text(formatted);
                    $('#today-sales-mask').text('*****');
                    pulseElement('today-sales-real');
                } else {
                    $('#today-sales-real').text('0');
                }
                
                if (response.monthly_sales !== undefined) {
                    var monthlyAmount = Math.round(parseFloat(response.monthly_sales));
                    var formatted = formatNumber(monthlyAmount);
                    $('#month-sales-real').text(formatted);
                    $('#month-sales-mask').text('*****');
                    pulseElement('month-sales-real');
                } else {
                    $('#month-sales-real').text('0');
                }
                
                if (response.total_users !== undefined) {
                    $('#total-users').text(response.total_users);
                }

                if (response.expired_pppoe !== undefined) {
                    $('#expired-pppoe').text(response.expired_pppoe);
                    updateExpiredPppoeLink(response.selected_router_name || getSelectedRouterName());
                    pulseElement('expired-pppoe');
                }
                
                if (response.online_routers !== undefined && response.total_routers !== undefined) {
                    $('#online-routers').text(response.online_routers + '/' + response.total_routers);
                }
                
                updateRouterStatusBadge(response.selected_router_status);
            } else {
                console.error('Invalid response format:', response);
                updateRouterStatusBadge();
            }
            
            if (showLoader) hideLoading();
        },
        error: function(xhr, status, error) {
            console.error('Error loading sales data:', error);
            updateRouterStatusBadge();
            if (showLoader) hideLoading();
        }
    });
}

// Load all data
function loadAllData(routerId = 'all', showLoader = true) {
    loadSalesData(routerId, showLoader);
    setTimeout(function() {
        loadOnlineUsersData(routerId);
    }, 500);
}

// Document ready
$(document).ready(function() {
    console.log('Dashboard ready - Manual refresh only');
    
    $('#today-sales-mask').show();
    $('#today-sales-real').hide();
    $('#month-sales-mask').show();
    $('#month-sales-real').hide();
    
    updateRouterStatusBadge();
    updateExpiredPppoeLink(getSelectedRouterName());
    
    // Load data once on page load
    setTimeout(function() {
        console.log('Loading initial data...');
        var initialRouter = $('#router-select').val();
        loadAllData(initialRouter, true);
    }, 500);
    
    // Router change handler
    $('#router-select').on('change', function() {
        var selectedRouter = $(this).val();
        console.log('Router changed to:', selectedRouter);
        updateRouterStatusBadge();
        updateExpiredPppoeLink(getSelectedRouterName());
        loadAllData(selectedRouter, true);
        
        $('#refresh-btn i').addClass('fa-spin');
        setTimeout(function() {
            $('#refresh-btn i').removeClass('fa-spin');
        }, 500);
    });
    
    // Refresh button click - only manual refresh now
    $('#refresh-btn').on('click', function() {
        console.log('Manual refresh clicked');
        var selectedRouter = $('#router-select').val();
        loadAllData(selectedRouter, true);
        $(this).find('i').addClass('fa-spin');
        setTimeout(function() {
            $('#refresh-btn i').removeClass('fa-spin');
        }, 800);
    });
});
</script>
</body>
</html>