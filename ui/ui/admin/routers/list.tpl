{include file="sections/header.tpl"}

<style>
:root {
    --primary: #ff9900;
    --primary-dark: #e68a00;
    --primary-light: #ffb84d;
    --primary-soft: #fff4e0;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --dark: #1e293b;
    --light: #f8fafc;
    --border: #e2e8f0;
    --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --radius: 12px;
    --radius-sm: 8px;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    padding: 24px;
    border-radius: var(--radius);
    margin-bottom: 24px;
    color: white;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.page-header h4 {
    margin: 0;
    font-weight: 600;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header h4 i {
    font-size: 1.8rem;
    background: rgba(255,255,255,0.2);
    padding: 10px;
    border-radius: 50%;
}

/* Section Headers */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 3px solid var(--primary-light);
}

.section-header h2 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header h2 i {
    color: var(--primary);
    font-size: 1.6rem;
}

.section-header .badge {
    background: var(--primary-soft);
    color: var(--primary-dark);
    padding: 6px 15px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.9rem;
    border: 1px solid var(--primary-light);
}

/* Search Section */
.search-section {
    background: white;
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--primary-light);
}

.search-form {
    display: flex;
    gap: 12px;
    align-items: center;
}

.search-input-group {
    flex: 1;
    position: relative;
}

.search-input-group i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary);
    font-size: 16px;
}

.search-input-group input {
    width: 100%;
    padding: 12px 12px 12px 42px;
    border: 2px solid var(--primary-light);
    border-radius: var(--radius-sm);
    font-size: 14px;
    transition: all 0.3s;
}

.search-input-group input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(255,153,0,0.1);
}

.btn-search {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-search:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,153,0,0.3);
}

.btn-add {
    background: white;
    color: var(--primary);
    border: 2px solid var(--primary);
    padding: 12px 25px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-add:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,153,0,0.2);
    text-decoration: none;
}

/* Router Card */
.router-card {
    background: white;
    border-radius: var(--radius);
    margin-bottom: 12px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: all 0.3s;
    overflow: hidden;
}

.router-card:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.router-card.disabled {
    background: #fef2f2;
    opacity: 0.8;
}

.router-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    background: linear-gradient(to right, transparent, rgba(255,153,0,0.02));
}

.router-info {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    flex-wrap: wrap;
}

.router-name {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 180px;
}

.router-name i {
    color: var(--primary);
    font-size: 20px;
}

.router-name strong {
    font-size: 16px;
    color: var(--dark);
}

.router-location {
    color: var(--primary);
    text-decoration: none;
    padding: 4px 8px;
    border-radius: 20px;
    background: var(--primary-soft);
    transition: all 0.2s;
}

.router-location:hover {
    background: var(--primary);
    color: white;
}

.router-ip {
    font-family: 'Courier New', monospace;
    background: var(--light);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    color: var(--dark);
    border: 1px solid var(--border);
    min-width: 140px;
}

.router-username {
    font-family: 'Courier New', monospace;
    color: var(--dark);
    font-size: 13px;
    min-width: 120px;
}

.router-status {
    display: flex;
    align-items: center;
    gap: 20px;
    min-width: 300px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    min-width: 90px;
}

.status-badge.online {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.status-badge.offline {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.status-badge.warning {
    background: #fed7aa;
    color: #9b4d00;
    border: 1px solid #f59e0b;
}

.status-badge i {
    font-size: 14px;
}

.last-seen {
    color: var(--dark);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.last-seen i {
    color: var(--primary);
}

.router-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    color: white;
    font-size: 16px;
}

.action-btn.edit { background: var(--info); }
.action-btn.test { background: var(--success); }
.action-btn.reboot { background: var(--warning); }
.action-btn.delete { background: var(--danger); }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Router Details */
.router-details {
    padding: 20px;
    border-top: 1px solid var(--border);
    background: var(--light);
    display: none;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 12px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 14px;
    color: var(--dark);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-value i {
    color: var(--primary);
}

/* Pagination */
.pagination {
    margin: 30px 0 10px;
    display: flex;
    justify-content: center;
    gap: 8px;
}

.page-item {
    list-style: none;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 8px;
    border: 2px solid var(--border);
    border-radius: 8px;
    color: var(--dark);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.page-link:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(255,153,0,0.1);
}

.page-item.active .page-link {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Loading Spinner */
.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--primary-soft);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-content p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    background: white;
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.quick-actions h3 {
    margin: 0 0 15px 0;
    font-size: 1.1rem;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.quick-actions h3 i {
    color: var(--primary);
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.quick-action-btn {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--dark);
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
}

.quick-action-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,153,0,0.2);
    border-color: var(--primary);
}

.quick-action-btn:hover i {
    color: white;
}

/* Responsive */
@media (max-width: 1200px) {
    .router-info {
        gap: 10px;
    }
    
    .router-status {
        min-width: auto;
    }
}

@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
    }
    
    .search-input-group,
    .btn-search,
    .btn-add {
        width: 100%;
    }
    
    .router-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .router-info {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }
    
    .router-name {
        min-width: auto;
    }
    
    .router-status {
        width: 100%;
        justify-content: space-between;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .action-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Main Page Header -->
<div class="page-header">
    <h4>
        <i class="fa fa-globe"></i>
        Network Router Management
    </h4>
    <span class="badge" style="background: rgba(255,255,255,0.2); padding: 8px 16px;">
        <i class="fa fa-hdd-o"></i> Total Routers: {$d|count}
    </span>
</div>

<!-- Statistics Cards -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa fa-server"></i>
        </div>
        <div class="stat-content">
            <h3>{$d|count}</h3>
            <p>Total Routers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa fa-check-circle" style="color: var(--success);"></i>
        </div>
        <div class="stat-content">
            {assign var="online_count" value=0}
            {foreach $d as $ds}
                {if $ds['status'] == 'Online'}
                    {assign var="online_count" value=$online_count+1}
                {/if}
            {/foreach}
            <h3>{$online_count}</h3>
            <p>Online Routers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa fa-times-circle" style="color: var(--danger);"></i>
        </div>
        <div class="stat-content">
            {assign var="offline_count" value=0}
            {foreach $d as $ds}
                {if $ds['status'] == 'Offline'}
                    {assign var="offline_count" value=$offline_count+1}
                {/if}
            {/foreach}
            <h3>{$offline_count}</h3>
            <p>Offline Routers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa fa-clock-o" style="color: var(--warning);"></i>
        </div>
        <div class="stat-content">
            <h3>{$paginator->total_pages}</h3>
            <p>Total Pages</p>
        </div>
    </div>
</div>

<!-- Search Section Header -->
<div class="section-header">
    <h2>
        <i class="fa fa-search"></i>
        Search Routers
    </h2>
    <span class="badge">Quick Search</span>
</div>

<div class="search-section">
    <form method="post" action="{Text::url('')}routers/list/" class="search-form">
        <div class="search-input-group">
            <i class="fa fa-search"></i>
            <input type="text" name="name" placeholder="Search by router name, IP, or username...">
        </div>
        <button type="submit" class="btn-search">
            <i class="fa fa-search"></i> Search
        </button>
        <a href="{Text::url('')}plugin/rs_radius_wireguard_setup" class="btn-add" style="margin-right:8px;background:#16a34a;">
            <i class="fa fa-shield"></i> Automatic Setup
        </a>
        <a href="{Text::url('')}routers/add" class="btn-add">
            <i class="fa fa-plus"></i> + New Router
        </a>
    </form>
</div>

<!-- Quick Actions Section -->
<div class="quick-actions">
    <h3>
        <i class="fa fa-bolt"></i>
        Quick Actions
    </h3>
    <div class="action-grid">
        <a href="{Text::url('')}routers/add" class="quick-action-btn">
            <i class="fa fa-plus-circle"></i>
            Add Router
        </a>
        <a href="{Text::url('')}routers/list" class="quick-action-btn">
            <i class="fa fa-refresh"></i>
            Refresh List
        </a>
        <button onclick="checkAllStatus()" class="quick-action-btn">
            <i class="fa fa-plug"></i>
            Check All Status
        </button>
    </div>
</div>

<!-- Routers List Header -->
<div class="section-header">
    <h2>
        <i class="fa fa-list"></i>
        Router List
    </h2>
    <span class="badge">Showing {$d|count} of {$paginator->total_items} routers</span>
</div>

<div class="router-list">
    {foreach $d as $ds}
    <div class="router-card {if $ds['enabled'] !=1}disabled{/if}" id="router-{$ds['id']}">
        <div class="router-header" onclick="toggleDetails({$ds['id']})">
            <div class="router-info">
                <div class="router-name">
                    {if $ds['coordinates']}
                    <a href="https://www.google.com/maps/dir//{$ds['coordinates']}/" target="_blank" 
                       class="router-location" title="View on Google Maps" onclick="event.stopPropagation()">
                        <i class="fa fa-map-marker"></i>
                    </a>
                    {/if}
                    <i class="fa fa-server"></i>
                    <strong>{$ds['name']}</strong>
                </div>
                
                <div class="router-ip">
                    <i class="fa fa-globe"></i> {$ds['ip_address']}
                </div>
                
                <div class="router-username">
                    <i class="fa fa-user"></i> {$ds['username']}
                </div>
                
                <div class="router-status">
                    <span id="status-{$ds['id']}" class="status-badge {if $ds['status'] == 'Online'}online{elseif $ds['status'] == 'Rebooting'}warning{else}offline{/if}">
                        {if $ds['status'] == 'Online'}
                            <i class="fa fa-check-circle"></i> Online
                        {elseif $ds['status'] == 'Rebooting'}
                            <i class="fa fa-spinner spinner"></i> Rebooting
                        {else}
                            <i class="fa fa-times-circle"></i> Offline
                        {/if}
                    </span>
                    
                    <span id="lastseen-{$ds['id']}" class="last-seen">
                        <i class="fa fa-clock-o"></i>
                        {if $ds['last_seen']}
                            {$ds['last_seen']|date_format:"%H:%M:%S"}
                        {else}
                            Never
                        {/if}
                    </span>
                </div>
            </div>
            
            <div class="router-actions" onclick="event.stopPropagation()">
                <a href="{Text::url('')}plugin/mikrotik_configurator_config_ui&router_id={$ds['id']}" class="action-btn test" title="Configure MikroTik">
                    <i class="fa fa-cog"></i>
                </a>
                <a href="{Text::url('')}routers/edit/{$ds['id']}" class="action-btn edit" title="Edit Router">
                    <i class="fa fa-pencil"></i>
                </a>
                <button onclick="testConnection({$ds['id']})" class="action-btn test" title="Test Connection">
                    <i class="fa fa-plug"></i>
                </button>
                <button onclick="rebootRouter({$ds['id']}, '{$ds['name']}')" class="action-btn reboot" title="Reboot Router">
                    <i class="fa fa-power-off"></i>
                </button>
                <button onclick="deleteRouter({$ds['id']}, '{$ds['name']}')" class="action-btn delete" title="Delete Router">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
        
        <div class="router-details" id="details-{$ds['id']}">
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Description</span>
                    <span class="detail-value">
                        <i class="fa fa-info-circle"></i>
                        {if $ds['description']}
                            {$ds['description']}
                        {else}
                            No description
                        {/if}
                    </span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Uptime</span>
                    <span class="detail-value" id="uptime-{$ds['id']}">
                        <i class="fa fa-dashboard"></i>
                        {if $ds['status'] == 'Online'}
                            <i class="fa fa-spinner spinner"></i> Loading...
                        {else}
                            -
                        {/if}
                    </span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Version</span>
                    <span class="detail-value" id="version-{$ds['id']}">
                        <i class="fa fa-code-fork"></i>
                        {if $ds['status'] == 'Online'}
                            <i class="fa fa-spinner spinner"></i> Loading...
                        {else}
                            -
                        {/if}
                    </span>
                </div>
                
                <div class="detail-item">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <i class="fa {if $ds['enabled'] == 1}fa-check-circle text-success{else}fa-ban text-danger{/if}"></i>
                        {if $ds['enabled'] == 1}Enabled{else}Disabled{/if}
                    </span>
                </div>
            </div>
        </div>
    </div>
    {foreachelse}
    <div class="text-center py-5">
        <i class="fa fa-server" style="font-size: 48px; color: #ccc;"></i>
        <h4 class="text-muted mt-3">No routers found</h4>
        <p>Click the "+ New Router" button to add your first router.</p>
    </div>
    {/foreach}
</div>

<!-- Pagination Section -->
{if $paginator->total_pages > 1}
<div class="section-header" style="margin-top: 20px;">
    <h2>
        <i class="fa fa-arrows-h"></i>
        Pagination
    </h2>
    <span class="badge">Page {$paginator->current_page} of {$paginator->total_pages}</span>
</div>

<ul class="pagination">
    <li class="page-item {if $paginator->current_page == 1}disabled{/if}">
        <a class="page-link" href="{Text::url('')}routers/list/{$paginator->prev_page}">
            <i class="fa fa-chevron-left"></i>
        </a>
    </li>
    
    {for $i=1 to $paginator->total_pages}
        {if $i >= $paginator->current_page-2 && $i <= $paginator->current_page+2}
        <li class="page-item {if $i == $paginator->current_page}active{/if}">
            <a class="page-link" href="{Text::url('')}routers/list/{$i}">{$i}</a>
        </li>
        {/if}
    {/for}
    
    <li class="page-item {if $paginator->current_page == $paginator->total_pages}disabled{/if}">
        <a class="page-link" href="{Text::url('')}routers/list/{$paginator->next_page}">
            <i class="fa fa-chevron-right"></i>
        </a>
    </li>
</ul>
{/if}

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: var(--radius); border: 2px solid var(--primary);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none;">
                <h5 class="modal-title">
                    <i class="fa fa-trash"></i> Confirm Delete
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color: white;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <p>Enter security code <strong>"antiqua"</strong> to confirm deletion:</p>
                <input type="text" id="security_code" class="form-control" placeholder="Enter antiqua" 
                       style="border: 2px solid var(--primary-light); border-radius: var(--radius-sm); padding: 12px;">
                <input type="hidden" id="delete_router_id" value="">
                <input type="hidden" id="delete_router_name" value="">
                <small class="text-muted" style="margin-top: 10px; display: block;">
                    <i class="fa fa-info-circle"></i> This action cannot be undone.
                </small>
            </div>
            <div class="modal-footer" style="border-top: 2px solid var(--primary-light);">
                <button type="button" class="btn btn-default" data-dismiss="modal" 
                        style="padding: 10px 25px; border-radius: var(--radius-sm);">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()" 
                        style="padding: 10px 25px; border-radius: var(--radius-sm); background: var(--danger); border: none;">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Get the correct base URL
var baseUrl = window.location.protocol + "//" + window.location.host + window.location.pathname.split('index.php')[0];
var fullBaseUrl = baseUrl + 'index.php?_route=';

console.log('Base URL:', baseUrl);
console.log('Full Base URL:', fullBaseUrl);

$(document).ready(function() {
    // Status is rendered from the persisted monitor state. Do not create a
    // RouterOS request for every card when this page opens.
    console.log('Document ready - using cached router status');
});

function toggleDetails(routerId) {
    $('#details-' + routerId).slideToggle(300);
}

function checkAllStatus() {
    {foreach $d as $ds}
        checkRouterStatus({$ds['id']});
    {/foreach}
}

function checkRouterStatus(routerId) {
    var statusUrl = fullBaseUrl + 'routers/status/' + routerId;
    console.log('Checking status for router ' + routerId + ' at:', statusUrl);
    
    $.ajax({
        url: statusUrl,
        type: 'GET',
        dataType: 'json',
        timeout: 3000,
        success: function(response) {
            console.log('Status response for router ' + routerId + ':', response);
            
            if (response.status == 'online') {
                $('#status-' + routerId).removeClass('offline warning').addClass('online')
                    .html('<i class="fa fa-check-circle"></i> Online');
                $('#lastseen-' + routerId).html('<i class="fa fa-clock-o"></i> ' + 
                    new Date().toLocaleTimeString());
                
                if (response.uptime) {
                    $('#uptime-' + routerId).html('<i class="fa fa-dashboard"></i> ' + response.uptime);
                }
                if (response.version) {
                    $('#version-' + routerId).html('<i class="fa fa-code-fork"></i> ' + response.version);
                }
            } else if (response.status == 'offline') {
                $('#status-' + routerId).removeClass('online warning').addClass('offline')
                    .html('<i class="fa fa-times-circle"></i> Offline');
                $('#uptime-' + routerId).html('<i class="fa fa-dashboard"></i> -');
                $('#version-' + routerId).html('<i class="fa fa-code-fork"></i> -');
            }
        },
        error: function(xhr, status, error) {
            console.error('Status check error for router ' + routerId + ':', status, error);
            console.log('Response text:', xhr.responseText);
            $('#status-' + routerId).removeClass('online warning').addClass('offline')
                .html('<i class="fa fa-times-circle"></i> Offline');
        }
    });
}

function testConnection(routerId) {
    var btn = event.currentTarget;
    var originalHtml = $(btn).html();
    
    $(btn).html('<i class="fa fa-spinner spinner"></i>').prop('disabled', true);
    
    var testUrl = fullBaseUrl + 'routers/test-connection/' + routerId;
    console.log('Testing connection for router ' + routerId + ' at:', testUrl);
    
    $.ajax({
        url: testUrl,
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            console.log('Test connection response:', response);
            
            if (response.status == 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Connection Successful!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                checkRouterStatus(routerId);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Failed',
                    text: response.message
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Test connection error:', status, error);
            console.log('Response text:', xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not test connection: ' + (error || 'Timeout')
            });
        },
        complete: function() {
            $(btn).html(originalHtml).prop('disabled', false);
        }
    });
}

function rebootRouter(routerId, routerName) {
    Swal.fire({
        title: 'Reboot Router?',
        html: 'Are you sure you want to reboot <strong>' + routerName + '</strong>?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff9900',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-power-off"></i> Yes, reboot it!',
        cancelButtonText: '<i class="fa fa-times"></i> Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Rebooting...',
                html: ' command Initiated to <strong>' + routerName + '</strong><br>Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            var rebootUrl = fullBaseUrl + 'routers/reboot/' + routerId;
            console.log('Sending reboot request to:', rebootUrl);
            
            $.ajax({
                url: rebootUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    console.log('Reboot response:', response);
                    
                    if (response.status == 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reboot Initiated!',
                            html: response.message + '<br><small>The router will be back online in a few moments.</small>',
                            timer: 5000,
                            showConfirmButton: true,
                            confirmButtonText: '<i class="fa fa-check"></i> OK'
                        }).then(() => {
                            $('#status-' + routerId).removeClass('online offline').addClass('warning')
                                .html('<i class="fa fa-spinner spinner"></i> Rebooting...');
                            
                            let checkCount = 0;
                            let checkInterval = setInterval(function() {
                                checkCount++;
                                checkRouterStatus(routerId);
                                
                                if (checkCount >= 12) {
                                    clearInterval(checkInterval);
                                }
                            }, 10000);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Reboot Failed',
                            text: response.message || 'Unknown error occurred',
                            confirmButtonText: '<i class="fa fa-check"></i> OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reboot error:', status, error);
                    console.log('Response text:', xhr.responseText);
                    
                    let errorMsg = 'Failed to send reboot command';
                    
                    if (status === 'timeout') {
                        errorMsg = 'Request timeout. Router may be unreachable.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'API endpoint not found. Attempted URL: ' + rebootUrl;
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error. Please check PHP error logs.';
                    } else if (xhr.responseText && xhr.responseText.indexOf('<!DOCTYPE') !== -1) {
                        errorMsg = 'Server returned HTML instead of JSON. The endpoint may not exist.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        html: errorMsg + '<br><small>Check console for details.</small>',
                        confirmButtonText: '<i class="fa fa-check"></i> OK'
                    });
                }
            });
        }
    });
}

function deleteRouter(routerId, routerName) {
    $('#delete_router_id').val(routerId);
    $('#delete_router_name').val(routerName);
    $('#security_code').val('');
    $('#deleteModal').modal('show');
}

function confirmDelete() {
    var routerId = $('#delete_router_id').val();
    var routerName = $('#delete_router_name').val();
    var securityCode = $('#security_code').val();
    
    if (!securityCode) {
        Swal.fire('Error', 'Please enter security code', 'error');
        return;
    }
    
    if (securityCode.toLowerCase() != 'antiqua') {
        Swal.fire('Error', 'Invalid security code', 'error');
        return;
    }
    
    $('#deleteModal').modal('hide');
    
    Swal.fire({
        title: 'Deleting...',
        text: 'Please wait',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });
    
    $.ajax({
        url: fullBaseUrl + 'routers/delete/' + routerId,
        type: 'POST',
        data: {
            confirm_delete: true,
            security_code: securityCode
        },
        dataType: 'json',
        success: function(response) {
            if (response.status == 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: response.message,
                    timer: 2000
                }).then(function() {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Delete error:', status, error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to delete router: ' + (error || 'Unknown error')
            });
        }
    });
}
</script>
{include file="sections/footer.tpl"}
