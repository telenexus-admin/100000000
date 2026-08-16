{include file="sections/header.tpl"}

<style>
    :root {
        --primary: #f97316;
        --primary-dark: #ea580c;
        --primary-light: #fed7aa;
        --primary-soft: #fff7ed;
    }

    /* Stat cards */
    .tx-stat {
        position: relative;
        border-radius: 16px;
        padding: 20px 22px;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        margin-bottom: 20px;
        min-height: 140px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .tx-stat .tx-stat-value {
        font-size: 28px;
        font-weight: 700;
        line-height: 1.1;
        margin: 0;
    }
    .tx-stat .tx-stat-label {
        font-size: 13px;
        font-weight: 500;
        opacity: 0.9;
        margin: 4px 0 0;
        letter-spacing: 0.3px;
    }
    .tx-stat .tx-stat-icon {
        position: absolute;
        right: 14px;
        top: 14px;
        font-size: 44px;
        opacity: 0.25;
        line-height: 1;
    }
    .tx-stat .tx-stat-footer {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.92);
        text-decoration: none;
        background: rgba(0, 0, 0, 0.15);
        padding: 6px 10px;
        border-radius: 8px;
        align-self: flex-start;
        margin-top: 12px;
        transition: background 0.15s;
    }
    .tx-stat .tx-stat-footer:hover {
        background: rgba(0, 0, 0, 0.3);
        color: #fff;
        text-decoration: none;
    }
    .tx-stat-green  { background: linear-gradient(135deg, #10b981, #059669); }
    .tx-stat-blue   { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .tx-stat-orange { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
    .tx-stat-purple { background: linear-gradient(135deg, #a855f7, #7c3aed); }

    /* Panel */
    .tx-panel {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        background: #fff;
        overflow: hidden;
    }
    .tx-panel-head {
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, var(--primary-soft), #fff);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    }
    .tx-panel-head h3 {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        color: #0f172a;
    }
    .tx-panel-head .tx-period-pill {
        font-size: 12px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary-dark);
        border: 1px solid var(--primary-light);
    }
    .tx-panel-head .tx-total {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
    }
    .tx-pill {
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 999px;
        display: inline-block;
    }
    .tx-pill-success { background: #dcfce7; color: #166534; }
    .tx-pill-info    { background: #dbeafe; color: #1e40af; }
    .tx-pill-total   { background: var(--primary); color: #fff; font-size: 13px; padding: 6px 14px; }

    /* Period selector */
    .tx-period-row {
        padding: 14px 22px 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .tx-period-group { display: inline-flex; gap: 0; }
    .tx-period-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
    }
    .tx-period-btn:first-child { border-radius: 10px 0 0 10px; }
    .tx-period-btn:last-child  { border-radius: 0 10px 10px 0; border-left: 0; }
    .tx-period-btn:hover { background: var(--primary-soft); color: var(--primary-dark); text-decoration: none; }
    .tx-period-btn.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-color: var(--primary-dark);
        color: #fff;
    }
    .tx-reset-hint {
        margin-left: auto;
        font-size: 12px;
        color: #64748b;
    }

    /* Filters */
    .tx-filters {
        padding: 18px 22px;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        border-bottom: 1px solid #e2e8f0;
    }
    @media (max-width: 1100px) { .tx-filters { grid-template-columns: 1fr 1fr 1fr; } }
    @media (max-width: 600px)  { .tx-filters { grid-template-columns: 1fr 1fr; } }
    .tx-filter label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .tx-filter .form-control {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        height: 40px;
        font-size: 14px;
        padding: 6px 12px;
        background: #fff;
        width: 100%;
    }
    .tx-filter .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        outline: none;
    }
    .tx-clear-btn {
        height: 40px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        padding: 0 16px;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .tx-clear-btn:hover {
        background: #fee2e2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    /* Table */
    .tx-table-wrap { padding: 6px 22px 18px; }
    .tx-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13.5px;
    }
    .tx-table thead th {
        background: #f8fafc;
        color: #475569;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.6px;
        padding: 12px 10px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        white-space: nowrap;
    }
    .tx-table tbody td {
        padding: 12px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        white-space: nowrap;
    }
    .tx-table tbody tr:hover td { background: #fff7ed; }
    .tx-table .tx-id {
        font-weight: 700;
        color: var(--primary-dark);
    }
    .tx-table a.tx-user {
        color: #0f172a;
        font-weight: 600;
        text-decoration: none;
    }
    .tx-table a.tx-user:hover { color: var(--primary-dark); text-decoration: underline; }
    .tx-amount { font-weight: 700; color: #0f172a; }
    .tx-muted  { color: #64748b; font-size: 12.5px; }

    /* Status badge */
    .tx-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.4px;
    }
    .tx-badge-paid     { background: #dcfce7; color: #166534; }
    .tx-badge-pending  { background: #fef3c7; color: #92400e; }
    .tx-badge-failed   { background: #fee2e2; color: #991b1b; }
    .tx-badge-cancel   { background: #e2e8f0; color: #475569; }
    .tx-badge-gateway  { background: #dbeafe; color: #1e40af; font-weight: 600; text-transform: capitalize; }

    /* Empty state */
    .tx-empty {
        text-align: center;
        padding: 48px 20px;
        color: #94a3b8;
    }
    .tx-empty i {
        font-size: 56px;
        color: var(--primary-light);
        margin-bottom: 12px;
    }
    .tx-empty h4 {
        color: #334155;
        font-weight: 600;
        margin: 0 0 6px;
    }
    .tx-empty p { margin: 0; font-size: 14px; }

    @media (max-width: 768px) {
        .tx-table-wrap { padding: 0 10px 14px; overflow-x: auto; }
        .tx-table { min-width: 900px; }
        .tx-panel-head { padding: 14px 16px; }
        .tx-period-row, .tx-filters { padding-left: 16px; padding-right: 16px; }
    }
</style>

<!-- Stat cards -->
<div class="row">
    {if in_array($_admin['user_type'],['SuperAdmin'])}
        <div class="col-lg-3 col-sm-6">
            <div class="tx-stat tx-stat-green">
                <i class="ion ion-card tx-stat-icon"></i>
                <div>
                    <h3 class="tx-stat-value">{Lang::moneyFormat($stats.total_amount)}</h3>
                    <p class="tx-stat-label">Online Payments Revenue</p>
                </div>
                <a href="{Text::url('reports/by-period')}" class="tx-stat-footer">
                    <i class="fa fa-arrow-right"></i> View Details
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="tx-stat tx-stat-blue">
                <i class="ion ion-checkmark-circled tx-stat-icon"></i>
                <div>
                    <h3 class="tx-stat-value">{$stats.paid}</h3>
                    <p class="tx-stat-label">Paid Online Transactions</p>
                </div>
                <span class="tx-stat-footer">
                    <i class="fa fa-info-circle"></i> Successful payments
                </span>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="tx-stat tx-stat-orange">
                <i class="ion ion-cash tx-stat-icon"></i>
                <div>
                    <h3 class="tx-stat-value">{Lang::moneyFormat($cash_stats.total_amount)}</h3>
                    <p class="tx-stat-label">Manual Cash Revenue</p>
                </div>
                <a href="{Text::url('reports/by-period')}" class="tx-stat-footer">
                    <i class="fa fa-arrow-right"></i> View Details
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="tx-stat tx-stat-purple">
                <i class="ion ion-pricetag tx-stat-icon"></i>
                <div>
                    <h3 class="tx-stat-value">{$voucher_stats.total_count}</h3>
                    <p class="tx-stat-label">Voucher Recharges</p>
                </div>
                <span class="tx-stat-footer">
                    <i class="fa fa-info-circle"></i> Redeemed vouchers
                </span>
            </div>
        </div>
    {else}
        <div class="col-lg-12">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> Payment statistics are only visible to Super Administrators.
            </div>
        </div>
    {/if}
</div>

<!-- Transactions Panel -->
<div class="row">
    <div class="col-md-12">
        <div class="tx-panel">
            <div class="tx-panel-head">
                <h3><i class="fa fa-exchange" style="color: var(--primary);"></i> All Payment Gateway Transactions</h3>
                <span class="tx-period-pill">
                    <i class="fa fa-calendar"></i>
                    {if $period == 'previous'}Previous Period{else}Current Period{/if}:
                    {$period_start|date_format:"%d %b %Y"} - {$period_end|date_format:"%d %b %Y"}
                </span>
                <span class="tx-total">
                    <span class="tx-pill tx-pill-success">Online</span>
                    <span class="tx-muted">+</span>
                    <span class="tx-pill tx-pill-info">Cash</span>
                    <span class="tx-muted">=</span>
                    <span class="tx-pill tx-pill-total">Total: {Lang::moneyFormat($stats.total_amount + $cash_stats.total_amount)}</span>
                </span>
            </div>

            <!-- Period selector -->
            <div class="tx-period-row">
                <div class="tx-period-group">
                    <a href="?_route=transactions&period=current" class="tx-period-btn {if $period == 'current'}active{/if}">
                        <i class="fa fa-calendar"></i>
                        Current <small>({$period_start|date_format:"%d %b"} - {$period_end|date_format:"%d %b"})</small>
                    </a>
                    <a href="?_route=transactions&period=previous" class="tx-period-btn {if $period == 'previous'}active{/if}">
                        <i class="fa fa-history"></i> Previous
                    </a>
                </div>
                <span class="tx-reset-hint">
                    <i class="fa fa-info-circle"></i>
                    Billing cycle resets on day {$reset_day} of each month
                </span>
            </div>

            <!-- Filters -->
            <div class="tx-filters" id="filterForm">
                <div class="tx-filter">
                    <label for="q">Search</label>
                    <input type="text" name="q" id="q" class="form-control"
                           placeholder="Username, transaction ID, plan..." value="{$q}">
                </div>
                <div class="tx-filter">
                    <label for="gateway">Gateway</label>
                    <select name="gateway" id="gateway" class="form-control">
                        <option value="">All Gateways</option>
                        {foreach $gateways as $gw}
                            <option value="{$gw.gateway}" {if $gateway == $gw.gateway}selected{/if}>{ucwords($gw.gateway)}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="tx-filter">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="1" {if $status == '1'}selected{/if}>Pending</option>
                        <option value="2" {if $status == '2'}selected{/if}>Paid</option>
                        <option value="3" {if $status == '3'}selected{/if}>Failed</option>
                        <option value="4" {if $status == '4'}selected{/if}>Canceled</option>
                    </select>
                </div>
                <div class="tx-filter">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{$date_from}">
                </div>
                <div class="tx-filter">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{$date_to}">
                </div>
                <div class="tx-filter">
                    <label>&nbsp;</label>
                    <button type="button" id="clearFilters" class="tx-clear-btn">
                        <i class="fa fa-refresh"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="tx-table-wrap">
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Gateway</th>
                            <th>Transaction ID</th>
                            <th>Username</th>
                            <th>Plan</th>
                            <th>Router</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Channel</th>
                            <th>Created</th>
                            <th>Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsTableBody">
                        {foreach $pgs as $pg}
                            <tr>
                                <td class="tx-id">#{$pg['id']}</td>
                                <td><span class="tx-badge tx-badge-gateway">{ucwords($pg['gateway'])}</span></td>
                                <td class="tx-muted">{if $pg['gateway_trx_id']}{$pg['gateway_trx_id']}{else}&mdash;{/if}</td>
                                <td>
                                    <a href="{$_url}customers/viewu/{$pg['username']}" class="tx-user">{$pg['username']}</a>
                                </td>
                                <td>{$pg['plan_name']}</td>
                                <td class="tx-muted">{$pg['routers']}</td>
                                <td class="tx-amount">{Lang::moneyFormat($pg['price'])}</td>
                                <td>{$pg['payment_method']}</td>
                                <td class="tx-muted">{$pg['payment_channel']}</td>
                                <td class="tx-muted">{if $pg['created_date']}{Lang::dateTimeFormat($pg['created_date'])}{else}&mdash;{/if}</td>
                                <td class="tx-muted">{if $pg['paid_date']}{Lang::dateTimeFormat($pg['paid_date'])}{else}&mdash;{/if}</td>
                                <td>
                                    {if $pg['status'] == 1}
                                        <span class="tx-badge tx-badge-pending">PENDING</span>
                                    {elseif $pg['status'] == 2}
                                        <span class="tx-badge tx-badge-paid">PAID</span>
                                    {elseif $pg['status'] == 3}
                                        <span class="tx-badge tx-badge-failed">FAILED</span>
                                    {else}
                                        <span class="tx-badge tx-badge-cancel">CANCELED</span>
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>

                {if !$pgs}
                    <div class="tx-empty">
                        <i class="fa fa-inbox"></i>
                        <h4>No transactions found</h4>
                        <p>Try adjusting your filters or choose a different period.</p>
                    </div>
                {/if}
            </div>

            <div style="padding: 8px 22px 18px;">
                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
</div>

<!-- Ensure jQuery is loaded -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Wait for jQuery to load and then execute our code
(function() {
    function initializeTransactionsPage() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initializeTransactionsPage, 100);
            return;
        }
        
        var $ = jQuery; // Use jQuery explicitly
        
        $(document).ready(function() {
            let filterTimeout;
            
            // Function to perform AJAX search
            function performSearch() {
                const formData = {
                    ajax: '1',
                    q: $('#q').val(),
                    gateway: $('#gateway').val(),
                    status: $('#status').val(),
                    date_from: $('#date_from').val(),
                    date_to: $('#date_to').val(),
                    period: '{$period}'
                };
                
                $('#transactionsTableBody').html('<tr><td colspan="12" style="text-align:center; padding:28px; color:#64748b;"><i class="fa fa-spinner fa-spin" style="color: var(--primary);"></i> Loading transactions...</td></tr>');
                
                // Use the current page URL with parameters
                const ajaxUrl = window.location.href.split('?')[0] + '?_route=transactions';
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    data: formData,
                    dataType: 'json',
                    success: function(data) {
                        updateTransactionsTable(data);
                    },
                    error: function(xhr, status, error) {
                        $('#transactionsTableBody').html('<tr><td colspan="12" style="text-align:center; padding:28px; color:#b91c1c;"><i class="fa fa-exclamation-triangle"></i> Error loading transactions: ' + error + '</td></tr>');
                    }
                });
            }
            
            // Function to update the transactions table
            function updateTransactionsTable(transactions) {
        let html = '';
        
        if (transactions.length === 0) {
            html = '<tr><td colspan="12"><div class="tx-empty"><i class="fa fa-inbox"></i><h4>No transactions found</h4><p>Try adjusting your filters or choose a different period.</p></div></td></tr>';
        } else {
            transactions.forEach(function(pg) {
                let statusLabel = '';
                let statusBadge = '';

                switch(pg.status.toString()) {
                    case '1':
                        statusLabel = 'PENDING';
                        statusBadge = 'tx-badge-pending';
                        break;
                    case '2':
                        statusLabel = 'PAID';
                        statusBadge = 'tx-badge-paid';
                        break;
                    case '3':
                        statusLabel = 'FAILED';
                        statusBadge = 'tx-badge-failed';
                        break;
                    default:
                        statusLabel = 'CANCELED';
                        statusBadge = 'tx-badge-cancel';
                }

                const createdDate = pg.created_date ? new Date(pg.created_date).toLocaleString() : '\u2014';
                const paidDate    = pg.paid_date    ? new Date(pg.paid_date).toLocaleString()    : '\u2014';
                const gatewayName = pg.gateway ? pg.gateway.charAt(0).toUpperCase() + pg.gateway.slice(1) : '';

                html +=
                    '<tr>' +
                        '<td class="tx-id">#' + pg.id + '</td>' +
                        '<td><span class="tx-badge tx-badge-gateway">' + gatewayName + '</span></td>' +
                        '<td class="tx-muted">' + (pg.gateway_trx_id || '\u2014') + '</td>' +
                        '<td><a href="{$_url}customers/viewu/' + pg.username + '" class="tx-user">' + pg.username + '</a></td>' +
                        '<td>' + (pg.plan_name || '') + '</td>' +
                        '<td class="tx-muted">' + (pg.routers || '') + '</td>' +
                        '<td class="tx-amount">{$_c.currency_code} ' + parseFloat(pg.price).toFixed(2) + '</td>' +
                        '<td>' + (pg.payment_method || '') + '</td>' +
                        '<td class="tx-muted">' + (pg.payment_channel || '') + '</td>' +
                        '<td class="tx-muted">' + createdDate + '</td>' +
                        '<td class="tx-muted">' + paidDate + '</td>' +
                        '<td><span class="tx-badge ' + statusBadge + '">' + statusLabel + '</span></td>' +
                    '</tr>';
            });
        }
        
                $('#transactionsTableBody').html(html);
            }
            
            // Instant search on text input
            $('#q').on('input', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(performSearch, 300); // 300ms delay for performance
            });
            
            // Instant filter on dropdown changes
            $('#gateway, #status').on('change', performSearch);
            
            // Date filter
            $('#date_from, #date_to').on('change', performSearch);
            
            // Clear filters
            $('#clearFilters').on('click', function() {
                $('#q').val('');
                $('#gateway').val('');
                $('#status').val('');
                $('#date_from').val('');
                $('#date_to').val('');
                performSearch();
            });
            
        }); // End of $(document).ready()
        
    } // End of initializeTransactionsPage()
    
    // Start the initialization
    initializeTransactionsPage();
})(); // End of self-executing function
</script>
{include file="sections/footer.tpl"}