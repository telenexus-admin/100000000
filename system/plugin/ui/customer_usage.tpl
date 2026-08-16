{include file="sections/header.tpl"}

<!-- Include required libraries -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<style>
    .chart-container {
        position: relative;
        height: 350px;
    }
    
    .loading-spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3498db;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-left: 10px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .card-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .card-hover {
        transition: all 0.2s ease;
    }
    
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }
    
    .status-online {
        background-color: #10b981;
    }
    
    .status-offline {
        background-color: #ef4444;
    }
</style>

<div class="min-h-screen" style="background: linear-gradient(135deg, #f9fafc 0%, #f1f5f9 100%); padding: 30px 20px;">
    <!-- Professional Header Section -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 20px 50px rgba(102, 126, 234, 0.3);">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
            <div class="flex items-center space-x-4">
                <div style="width: 64px; height: 64px; border-radius: 18px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);">
                    <i class="fas fa-chart-line text-3xl text-white"></i>
                </div>
                <div>
                    <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: white; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                        {Lang::T('Customer Usage Analytics')}
                    </h1>
                    <p style="margin: 8px 0 0; color: rgba(255, 255, 255, 0.9); font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-block; width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: pulse 2s infinite;"></span>
                        Real-time monitoring and comprehensive data analysis
                    </p>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <button onclick="exportData()" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; padding: 12px 24px; border-radius: 12px; display: flex; align-items: center; gap: 10px; font-weight: 600; border: none; cursor: pointer; box-shadow: 0 10px 25px rgba(34, 197, 94, 0.3); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 30px rgba(34, 197, 94, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 25px rgba(34, 197, 94, 0.3)'">
                    <i class="fas fa-download"></i>
                    <span>Export Data</span>
                </button>
                
                <button onclick="refreshData()" id="refreshBtn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 12px 24px; border-radius: 12px; display: flex; align-items: center; gap: 10px; font-weight: 600; border: none; cursor: pointer; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3); transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 30px rgba(59, 130, 246, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 25px rgba(59, 130, 246, 0.3)'">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Enhanced Stats Cards with Gradients -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Users Card -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 18px 40px rgba(102, 126, 234, 0.25); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 50px rgba(102, 126, 234, 0.35)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(102, 126, 234, 0.25)'">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-3" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="fas fa-users"></i>
                        <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;">Total Users</p>
                    </div>
                    <p style="font-size: 36px; font-weight: 700; margin: 0; color: white;">{$total_users|number_format}</p>
                    <p style="font-size: 12px; color: rgba(255, 255, 255, 0.8); margin-top: 8px;">
                        <i class="fas fa-chart-line mr-1"></i>
                        All registered users
                    </p>
                </div>
                <div style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); padding: 16px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);">
                    <i class="fas fa-user-friends text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Active Users Card -->
        <div style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 18px 40px rgba(34, 197, 94, 0.25); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 50px rgba(34, 197, 94, 0.35)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(34, 197, 94, 0.25)'">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-3" style="color: rgba(255, 255, 255, 0.9);">
                        <span style="display: inline-block; width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 2s infinite;"></span>
                        <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;">Active Now</p>
                    </div>
                    <p style="font-size: 36px; font-weight: 700; margin: 0; color: white;">{$active|count}</p>
                    <p style="font-size: 12px; color: rgba(255, 255, 255, 0.8); margin-top: 8px;">
                        <i class="fas fa-clock mr-1"></i>
                        Last 5 minutes
                    </p>
                </div>
                <div style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); padding: 16px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);">
                    <i class="fas fa-wifi text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Stations Card -->
        <div style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 18px 40px rgba(139, 92, 246, 0.25); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 50px rgba(139, 92, 246, 0.35)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(139, 92, 246, 0.25)'">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-3" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="fas fa-router"></i>
                        <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;">Active Stations</p>
                    </div>
                    <p style="font-size: 36px; font-weight: 700; margin: 0; color: white;">{$station_labels|count}</p>
                    <p style="font-size: 12px; color: rgba(255, 255, 255, 0.8); margin-top: 8px;">
                        <i class="fas fa-signal mr-1"></i>
                        Network locations
                    </p>
                </div>
                <div style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); padding: 16px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);">
                    <i class="fas fa-network-wired text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Pagination Info Card -->
        <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 16px; padding: 24px; color: white; box-shadow: 0 18px 40px rgba(59, 130, 246, 0.25); transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 50px rgba(59, 130, 246, 0.35)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(59, 130, 246, 0.25)'">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-3" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="fas fa-list-ol"></i>
                        <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;">Current View</p>
                    </div>
                    <p style="font-size: 28px; font-weight: 700; margin: 0; color: white;">Page {$current_page}/{$total_pages}</p>
                    <p style="font-size: 12px; color: rgba(255, 255, 255, 0.8); margin-top: 8px;">
                        <i class="fas fa-eye mr-1"></i>
                        Records {$start_record}-{$end_record}
                    </p>
                </div>
                <div style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); padding: 16px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);">
                    <i class="fas fa-list text-2xl text-white"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
        <!-- Station Distribution -->
        <div class="xl:col-span-1">
            <div class="bg-white rounded-lg p-6 shadow-md border border-gray-200">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                            <i class="fas fa-chart-pie text-blue-500"></i>
                            Station Distribution
                        </h3>
                        <p class="text-gray-600 text-sm mt-1">Active users by location</p>
                    </div>
                    <div class="flex items-center gap-2 bg-blue-50 px-3 py-1 rounded-lg">
                        <span class="status-indicator status-online"></span>
                        <span class="text-sm text-blue-600 font-medium">Live</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="stationChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Usage Trends -->
        <div class="xl:col-span-2">
            <div class="bg-white rounded-lg p-6 shadow-md border border-gray-200">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                            <i class="fas fa-chart-bar text-green-500"></i>
                            Top Data Consumers
                        </h3>
                        <p class="text-gray-600 text-sm mt-1">Highest upload usage (All time)</p>
                    </div>
                    <div class="flex items-center gap-2 bg-green-50 px-3 py-1 rounded-lg">
                        <span class="status-indicator bg-green-500"></span>
                        <span class="text-sm text-green-600 font-medium">Historical</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="topUsersChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Activity Monitor -->
    {if $active|count > 0}
    <div class="mb-8">
        <div style="background: white; border-radius: 16px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.07); border: 1px solid #e2e8f0; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 24px;">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-white flex items-center gap-3">
                            <i class="fas fa-satellite-dish"></i>
                            Real-time Activity Monitor
                        </h3>
                        <p class="text-green-100 text-sm mt-1">Users active in the last 5 minutes (real-time)</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 bg-white bg-opacity-20 px-3 py-1 rounded-lg">
                            <span class="status-indicator bg-white"></span>
                            <span class="text-white text-sm font-medium">{$active|count} Online</span>
                        </div>
                        <button onclick="toggleAutoRefresh()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                            <i class="fas fa-sync-alt mr-2"></i>Auto-refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-user mr-2"></i>User
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt mr-2"></i>Station
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-arrow-up mr-2 text-red-500"></i>Upload
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-arrow-down mr-2 text-green-500"></i>Download
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-database mr-2 text-blue-500"></i>Total
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-clock mr-2"></i>Uptime
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-history mr-2"></i>Last Seen
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-signal mr-2"></i>Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        {foreach $active as $user}
                        <tr style="transition: all 0.2s ease;" onmouseover="this.style.background='linear-gradient(90deg, #f8fbff 0%, #f1f5f9 100%)'; this.style.transform='translateX(2px)'" onmouseout="this.style.background='white'; this.style.transform='translateX(0)'">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                                        <span style="font-size: 14px; font-weight: 700; color: white;">{$user.username|substr:0:1|upper}</span>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{$user.username}</div>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                            <i class="fas fa-user-circle mr-1"></i>{$user.connection_type|default:'Hotspot'}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span style="display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; background: rgba(139, 92, 246, 0.12); color: #7c3aed; border: 1px solid rgba(139, 92, 246, 0.3);">
                                    <i class="fas fa-broadcast-tower mr-1"></i>
                                    {$user.station}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div style="font-family: 'Courier New', monospace; font-size: 13px; font-weight: 600; color: #dc2626; display: flex; align-items: center; justify-content: flex-end; gap: 6px; background: rgba(239, 68, 68, 0.1); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2);">
                                    <i class="fas fa-upload" style="font-size: 10px;"></i>
                                    {$user.tx_formatted}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div style="font-family: 'Courier New', monospace; font-size: 13px; font-weight: 600; color: #15803d; display: flex; align-items: center; justify-content: flex-end; gap: 6px; background: rgba(34, 197, 94, 0.1); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(34, 197, 94, 0.2);">
                                    <i class="fas fa-download" style="font-size: 10px;"></i>
                                    {$user.rx_formatted}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div style="font-family: 'Courier New', monospace; font-size: 13px; font-weight: 700; color: #4338ca; background: rgba(102, 126, 234, 0.15); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(102, 126, 234, 0.3);">
                                    {$user.total_formatted}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div style="font-size: 12px; color: #64748b; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <i class="fas fa-clock" style="font-size: 10px; color: #3b82f6;"></i>
                                    {$user.uptime|default:'-'}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div style="font-size: 12px; color: #64748b; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <i class="fas fa-history" style="font-size: 10px; color: #16a34a;"></i>
                                    {$user.last_seen_ago|default:'-'}
                                </div>
                            </td>
                            <td class="text-center">
                                <span style="display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; background: rgba(34, 197, 94, 0.12); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.3);">
                                    <span style="display: inline-block; width: 6px; height: 6px; background: #22c55e; border-radius: 50%; margin-right: 6px; animation: pulse 2s infinite;"></span>
                                    Online
                                </span>
                            </td>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {/if}

    <!-- Comprehensive Usage Statistics -->
    <div>
        <div style="background: white; border-radius: 16px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.07); border: 1px solid #e2e8f0; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px;">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-white flex items-center gap-3">
                            <i class="fas fa-analytics"></i>
                            Comprehensive Usage Statistics
                        </h3>
                        <p class="text-indigo-100 text-sm mt-1">Historical data and usage patterns</p>
                    </div>
                    
                    <!-- Controls -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 bg-white bg-opacity-20 px-3 py-2 rounded-lg">
                            <i class="fas fa-list text-white"></i>
                            <label class="text-white text-sm font-medium">Show:</label>
                            <select onchange="changeLimit(this.value)" class="bg-transparent text-white border-0 focus:ring-0 text-sm font-medium">
                                <option value="25" {if $limit == 25}selected{/if} class="text-gray-900">25</option>
                                <option value="50" {if $limit == 50}selected{/if} class="text-gray-900">50</option>
                                <option value="100" {if $limit == 100}selected{/if} class="text-gray-900">100</option>
                            </select>
                        </div>
                        
                        <button onclick="filterHeavyUsers()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                            <i class="fas fa-filter mr-2"></i>Heavy Users
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-trophy mr-2 text-yellow-500"></i>Rank
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-user mr-2"></i>Username
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-arrow-up mr-2 text-red-500"></i>Total Upload
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-arrow-down mr-2 text-green-500"></i>Total Download
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-database mr-2 text-blue-500"></i>Grand Total
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                <i class="fas fa-chart-line mr-2"></i>Usage Level
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        {foreach $all_time as $index => $user}
                        <tr style="transition: all 0.2s ease;" onmouseover="this.style.background='linear-gradient(90deg, #f8fbff 0%, #f1f5f9 100%)'; this.style.transform='translateX(2px)'" onmouseout="this.style.background='white'; this.style.transform='translateX(0)'">
                            <td class="px-6 py-4">
                                {assign var="rank" value=$start_record + $index}
                                <div class="flex items-center">
                                    {if $rank <= 3}
                                        <div style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                                            {if $rank == 1}background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white;{/if}
                                            {if $rank == 2}background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); color: white;{/if}
                                            {if $rank == 3}background: linear-gradient(135deg, #fb923c 0%, #f97316 100%); color: white;{/if}">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                        <span style="margin-left: 10px; font-weight: 700; color: #1e293b; font-size: 14px;">#{$rank}</span>
                                    {else}
                                        <div style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                            <span style="color: #64748b; font-weight: 600; font-size: 12px;">#{$rank}</span>
                                        </div>
                                    {/if}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
                                        <span style="font-size: 14px; font-weight: 700; color: white;">{$user.username|substr:0:1|upper}</span>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1e293b; font-size: 14px;">{$user.username}</div>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                            <i class="fas fa-calendar-alt mr-1"></i>Member
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div style="font-family: 'Courier New', monospace; font-size: 13px; font-weight: 700; color: #dc2626; background: rgba(239, 68, 68, 0.1); padding: 6px 12px; border-radius: 8px; display: inline-block; border: 1px solid rgba(239, 68, 68, 0.2);">
                                    <i class="fas fa-upload mr-1" style="font-size: 10px;"></i>
                                    {$user.tx_total_formatted}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div style="font-family: 'Courier New', monospace; font-size: 13px; font-weight: 700; color: #15803d; background: rgba(34, 197, 94, 0.1); padding: 6px 12px; border-radius: 8px; display: inline-block; border: 1px solid rgba(34, 197, 94, 0.2);">
                                    <i class="fas fa-download mr-1" style="font-size: 10px;"></i>
                                    {$user.rx_total_formatted}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div style="font-family: 'Courier New', monospace; font-size: 16px; font-weight: 700; color: #4338ca; background: rgba(102, 126, 234, 0.15); padding: 8px 16px; border-radius: 8px; display: inline-block; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.3);">
                                    {$user.total_formatted}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                {assign var="total_bytes" value=$user.tx_total + $user.rx_total}
                                {if $total_bytes > 10737418240}
                                    <span style="display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #b91c1c; border: 1px solid rgba(239, 68, 68, 0.3);">
                                        <i class="fas fa-fire mr-1"></i>Heavy User
                                    </span>
                                {elseif $total_bytes > 1073741824}
                                    <span style="display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; background: rgba(251, 146, 60, 0.12); color: #c2410c; border: 1px solid rgba(251, 146, 60, 0.3);">
                                        <i class="fas fa-chart-line mr-1"></i>Active User
                                    </span>
                                {elseif $total_bytes > 104857600}
                                    <span style="display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; background: rgba(59, 130, 246, 0.12); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.3);">
                                        <i class="fas fa-user mr-1"></i>Regular User
                                    </span>
                                {else}
                                    <span style="display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; background: rgba(107, 114, 128, 0.12); color: #4b5563; border: 1px solid rgba(107, 114, 128, 0.3);">
                                        <i class="fas fa-leaf mr-1"></i>Light User
                                    </span>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            {if $total_pages > 1}
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex flex-col lg:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600 flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        Showing <span class="font-bold text-indigo-600">{$start_record}</span> to 
                        <span class="font-bold text-indigo-600">{$end_record}</span> of 
                        <span class="font-bold text-indigo-600">{$total_users|number_format}</span> results
                    </div>
                    
                    <nav class="flex items-center gap-1">
                        {if $pagination.prev}
                            <a href="{$pagination.prev}" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-l-lg hover:bg-indigo-700 transition-colors duration-200 shadow-md flex items-center gap-2">
                                <i class="fas fa-chevron-left"></i>
                                Previous
                            </a>
                        {else}
                            <span class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-200 rounded-l-lg cursor-not-allowed flex items-center gap-2">
                                <i class="fas fa-chevron-left"></i>
                                Previous
                            </span>
                        {/if}
                        
                        {foreach $pagination.pages as $page}
                            {if $page.current}
                                <span class="px-4 py-2 text-sm font-bold text-white bg-blue-600 shadow-md flex items-center justify-center min-w-[40px] border border-blue-600">
                                    {$page.number}
                                </span>
                            {else}
                                <a href="{$page.url}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 hover:border-indigo-300 transition-colors duration-200 flex items-center justify-center min-w-[40px] hover:text-indigo-600">
                                    {$page.number}
                                </a>
                            {/if}
                        {/foreach}
                        
                        {if $pagination.next}
                            <a href="{$pagination.next}" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-r-lg hover:bg-indigo-700 transition-colors duration-200 shadow-md flex items-center gap-2">
                                Next
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        {else}
                            <span class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-200 rounded-r-lg cursor-not-allowed flex items-center gap-2">
                                Next
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        {/if}
                    </nav>
                </div>
            </div>
            {/if}
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="fixed bottom-6 right-6 z-50">
        <div class="flex flex-col gap-3">
            <button onclick="scrollToTop()" class="w-12 h-12 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg transition-colors duration-200 flex items-center justify-center">
                <i class="fas fa-arrow-up"></i>
            </button>
            <button onclick="showHelp()" class="w-12 h-12 bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg transition-colors duration-200 flex items-center justify-center">
                <i class="fas fa-question"></i>
            </button>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}

<script>
// Global variables
let autoRefreshInterval;
let isAutoRefreshActive = false;
let stationChart, topUsersChart;

// Chart.js configuration
Chart.register(ChartDataLabels);

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    setupEventListeners();
});

function initializeCharts() {
    // Station Distribution Chart
    const stationCtx = document.getElementById('stationChart').getContext('2d');
    stationChart = new Chart(stationCtx, {
        type: 'doughnut',
        data: {
            labels: {$station_labels|@json_encode},
            datasets: [{
                data: {$station_values|@json_encode},
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444',
                    '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#6b7280'
                ],
                borderColor: '#ffffff',
                borderWidth: 2,
                hoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return ' ' + context.label + ': ' + context.parsed + ' users (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Top Users Chart
    const topUsersCtx = document.getElementById('topUsersChart').getContext('2d');
    topUsersChart = new Chart(topUsersCtx, {
        type: 'bar',
        data: {
            labels: {$top5_labels|@json_encode},
            datasets: [{
                label: 'Upload Data',
                data: {$top5_values|@json_encode},
                backgroundColor: '#3b82f6',
                borderColor: '#1d4ed8',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const bytes = context.parsed.x;
                            const mb = bytes / (1024 * 1024);
                            const formatted = mb >= 1000 ? 
                                (mb / 1024).toFixed(2) + ' GB' : 
                                mb.toFixed(2) + ' MB';
                            return 'Upload: ' + formatted;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            const mb = value / (1024 * 1024);
                            return mb >= 1000 ? 
                                (mb / 1024).toFixed(0) + 'GB' : 
                                mb.toFixed(0) + 'MB';
                        }
                    }
                }
            }
        }
    });
}

function setupEventListeners() {
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshData();
        }
        if (e.key === 'Escape') {
            closeModals();
        }
    });
}

function refreshData() {
    const refreshBtn = document.getElementById('refreshBtn');
    const refreshIcon = document.getElementById('refreshIcon');
    
    refreshIcon.classList.add('fa-spin');
    refreshBtn.disabled = true;

    // Sync live MikroTik sessions in-system, then reload
    fetch('{$_url}plugin/customer_usage&sync=1', {ldelim} credentials: 'same-origin' {rdelim})
        .then(function() { location.reload(); })
        .catch(function() { location.reload(); });
}

function exportData() {
    const csvData = [];
    csvData.push(['Rank', 'Username', 'Upload', 'Download', 'Total', 'Usage Level']);
    
    {foreach $all_time as $index => $user}
    csvData.push([
        '{$start_record + $index}',
        '{$user.username}',
        '{$user.tx_total_formatted}',
        '{$user.rx_total_formatted}',
        '{$user.total_formatted}',
        'User Level'
    ]);
    {/foreach}
    
    const csv = csvData.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = 'customer_usage_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    
    showNotification('Data exported successfully!', 'success');
}

function changeLimit(limit) {
    const url = new URL(window.location);
    url.searchParams.set('limit', limit);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function toggleRealTime() {
    isAutoRefreshActive = !isAutoRefreshActive;
    const btn = document.getElementById('realTimeBtn');
    
    if (isAutoRefreshActive) {
        btn.innerHTML = '<i class="fas fa-pause mr-2"></i><span>Pause Live</span>';
        btn.className = btn.className.replace('bg-purple-600 hover:bg-purple-700', 'bg-red-600 hover:bg-red-700');
        startAutoRefresh();
        showNotification('Live mode activated', 'info');
    } else {
        btn.innerHTML = '<i class="fas fa-broadcast-tower mr-2"></i><span>Live Mode</span>';
        btn.className = btn.className.replace('bg-red-600 hover:bg-red-700', 'bg-purple-600 hover:bg-purple-700');
        stopAutoRefresh();
        showNotification('Live mode deactivated', 'info');
    }
}

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        if (!document.hidden) {
            location.reload();
        }
    }, 30000); // Refresh every 30 seconds
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

function filterHeavyUsers() {
    const rows = document.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const usageLevel = row.querySelector('td:last-child span');
        if (usageLevel && usageLevel.textContent.includes('Heavy User')) {
            row.style.display = '';
            row.classList.add('bg-red-50');
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    if (visibleCount === 0) {
        showNotification('No heavy users found', 'info');
    } else {
        showNotification('Showing ' + visibleCount + ' heavy users', 'info');
    }
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function showHelp() {
    const helpContent = `
        <div class="text-left space-y-4">
            <div>
                <h4 class="font-bold text-gray-800 mb-2">Keyboard Shortcuts:</h4>
                <ul class="text-sm space-y-1 text-gray-600">
                    <li><kbd class="bg-gray-200 px-2 py-1 rounded text-xs font-mono">Ctrl + R</kbd> - Refresh data</li>
                    <li><kbd class="bg-gray-200 px-2 py-1 rounded text-xs font-mono">Esc</kbd> - Close modals</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-gray-800 mb-2">Features:</h4>
                <ul class="text-sm space-y-1 text-gray-600">
                    <li>• Real-time activity monitoring</li>
                    <li>• Automatic data refresh</li>
                    <li>• Export to CSV</li>
                    <li>• Advanced filtering</li>
                    <li>• Usage analytics</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-gray-800 mb-2">Usage Levels:</h4>
                <ul class="text-sm space-y-1 text-gray-600">
                    <li><span class="inline-block w-3 h-3 bg-red-500 rounded mr-2"></span>Heavy User: >10GB total</li>
                    <li><span class="inline-block w-3 h-3 bg-orange-500 rounded mr-2"></span>Active User: >1GB total</li>
                    <li><span class="inline-block w-3 h-3 bg-blue-500 rounded mr-2"></span>Regular User: >100MB total</li>
                    <li><span class="inline-block w-3 h-3 bg-gray-500 rounded mr-2"></span>Light User: <100MB total</li>
                </ul>
            </div>
        </div>
    `;
    
    showModal('Help & Information', helpContent);
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = 'notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300';
    
    const colors = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        info: 'bg-blue-600 text-white',
        warning: 'bg-yellow-500 text-black'
    };
    
    notification.className += ' ' + colors[type];
    notification.innerHTML = 
        '<div class="flex items-center gap-2">' +
            '<i class="fas fa-' + (type === 'success' ? 'check' : type === 'error' ? 'times' : type === 'warning' ? 'exclamation' : 'info') + '-circle"></i>' +
            '<span>' + message + '</span>' +
        '</div>';
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

function showModal(title, content) {
    // Remove existing modals
    closeModals();
    
    const modal = document.createElement('div');
    modal.className = 'modal-overlay fixed inset-0 flex items-start justify-center p-4 pt-20';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    modal.style.zIndex = '9999';
    modal.innerHTML = 
        '<div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full max-h-96 overflow-y-auto transform transition-all duration-200 scale-100 mt-8">' +
            '<div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gray-50">' +
                '<h3 class="text-lg font-semibold text-gray-900">' + title + '</h3>' +
                '<button onclick="closeModals()" class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-full hover:bg-gray-200">' +
                    '<i class="fas fa-times text-lg"></i>' +
                '</button>' +
            '</div>' +
            '<div class="px-6 py-6">' +
                content +
            '</div>' +
            '<div class="px-6 py-4 border-t border-gray-200 bg-gray-50 text-right">' +
                '<button onclick="closeModals()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors font-medium">' +
                    'Close' +
                '</button>' +
            '</div>' +
        '</div>';
    
    // Click outside to close
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModals();
        }
    });
    
    // Add to body
    document.body.appendChild(modal);
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = 'notification fixed top-20 right-4 px-6 py-3 rounded-lg shadow-lg transition-all duration-300';
    notification.style.zIndex = '9999';
    
    const colors = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        info: 'bg-blue-600 text-white',
        warning: 'bg-yellow-500 text-black'
    };
    
    notification.className += ' ' + colors[type];
    notification.innerHTML = 
        '<div class="flex items-center gap-2">' +
            '<i class="fas fa-' + (type === 'success' ? 'check' : type === 'error' ? 'times' : type === 'warning' ? 'exclamation' : 'info') + '-circle"></i>' +
            '<span>' + message + '</span>' +
        '</div>';
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

function closeModals() {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => modal.remove());
    
    // Restore body scroll
    document.body.style.overflow = '';
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Page loaded - no notification needed
});

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.hidden && isAutoRefreshActive) {
        console.log('Page hidden, pausing auto-refresh');
    } else if (!document.hidden && isAutoRefreshActive) {
        console.log('Page visible, resuming auto-refresh');
    }
});
</script>