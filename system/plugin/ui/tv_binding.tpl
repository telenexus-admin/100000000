{include file="sections/header.tpl"}
<style>
    /* Stats Box Styling */
    .stat-box {
        border-radius: 8px;
        color: white;
        padding: 20px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 90px;
    }

    .stat-box:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }

    .stat-box .stat-content h3 {
        margin: 0;
        font-size: 32px;
        font-weight: 700;
        line-height: 1;
    }

    .stat-box .stat-content p {
        margin: 5px 0 0 0;
        font-size: 13px;
        opacity: 0.9;
    }

    .stat-box .stat-icon {
        font-size: 48px;
        opacity: 0.2;
        flex-shrink: 0;
        margin-left: 10px;
    }

    /* Stat Box Colors - All same color */
    .stat-box { background: #2c5aa0; }
    .stat-box.total { background: #2c5aa0; }
    .stat-box.online { background: #2c5aa0; }
    .stat-box.offline { background: #2c5aa0; }
    .stat-box.active { background: #2c5aa0; }
    .stat-box.expired { background: #2c5aa0; }

    /* Table Styling */
    .table { width: 100%; margin-bottom: 1rem; background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .table th { vertical-align: middle; border-color: #dee2e6; background-color: #2c3e50; color: #fff; font-weight: 600; }
    .table td { vertical-align: middle; border-color: #dee2e6; }
    .table-striped tbody tr:nth-of-type(odd) { background-color: #f9f9f9; }
    .table-hover tbody tr:hover { background-color: #f0f0f0; }

    /* Badge Styling */
    .badge-online { background-color: #27ae60; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .badge-offline { background-color: #e74c3c; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .badge-active { background-color: #3498db; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .badge-expired { background-color: #95a5a6; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }

    /* Form Control Styling */
    .form-control { border-radius: 4px; border: 1px solid #ddd; }
    .form-control:focus { border-color: #2c5aa0; box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1); }

    .btn-sm { padding: 5px 10px; font-size: 12px; }
    .btn-delete { background-color: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; }
    .btn-delete:hover { background-color: #c0392b; }

    code { background-color: #f5f5f5; padding: 4px 8px; border-radius: 3px; }

    /* Modal Professional Styling - Clean & Simple */
    .modal-content { border: none; border-radius: 8px; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2); }
    .modal-header { border-bottom: 1px solid #e9ecef; padding: 20px; }
    .modal-title { font-size: 18px; font-weight: 600; color: #2c3e50; }
    .modal-body { padding: 20px 25px; }
    .modal-footer { border-top: 1px solid #e9ecef; padding: 15px 20px; }

    @media (max-width: 576px) {
        #addBindingForm .control-label { text-align: left; }
    }

    /* Cards Container */
    .stat-cards-row { display: flex; flex-wrap: wrap; gap: 15px; margin: 15px 0 25px 0; }
    .stat-card-wrapper { flex: 1 1 calc(20% - 12px); }

    @media screen and (max-width: 1200px) { 
        .stat-card-wrapper { flex: 1 1 calc(33.333% - 10px); min-width: calc(50% - 8px); }
        .stat-box { padding: 18px; min-height: 85px; }
        .stat-box .stat-content h3 { font-size: 28px; }
        .stat-box .stat-icon { font-size: 40px; }
    }

    @media screen and (max-width: 768px) { 
        .stat-card-wrapper { flex: 1 1 calc(50% - 8px); }
        .stat-box { padding: 15px; min-height: 80px; }
        .stat-box .stat-content h3 { font-size: 24px; }
        .stat-box .stat-content p { font-size: 12px; }
        .stat-box .stat-icon { font-size: 36px; }
    }

    @media screen and (max-width: 600px) { 
        .stat-card-wrapper { flex: 1 1 calc(50% - 8px); }
        .stat-box { padding: 12px; min-height: 75px; }
        .stat-box .stat-content h3 { font-size: 20px; }
        .stat-box .stat-content p { font-size: 11px; }
        .stat-box .stat-icon { font-size: 32px; margin-left: 5px; }
    }

    /* Filter Responsive */
    .filter-row { margin: 10px 0 15px 0; }
    .filter-col { margin-bottom: 10px; }
    @media screen and (max-width: 768px) {
        .filter-col { width: 100%; margin-bottom: 8px; }
    }
</style>

<div class="box-body table-responsive no-padding">
    <div class="col-sm-12 col-md-12">

        <!-- Summary Cards - Responsive 2 per row on mobile -->
        <div class="stat-cards-row">
            <div class="stat-card-wrapper">
                <div class="stat-box total">
                    <div class="stat-content">
                        <h3>{$stats.total}</h3>
                        <p>Total Bindings</p>
                    </div>
                    <div class="stat-icon"><i class="ion ion-monitor"></i></div>
                </div>
            </div>
            <div class="stat-card-wrapper">
                <div class="stat-box online">
                    <div class="stat-content">
                        <h3>{$stats.online}</h3>
                        <p>Online Now</p>
                    </div>
                    <div class="stat-icon"><i class="ion ion-checkmark-circled"></i></div>
                </div>
            </div>
            <div class="stat-card-wrapper">
                <div class="stat-box offline">
                    <div class="stat-content">
                        <h3>{$stats.offline}</h3>
                        <p>Offline</p>
                    </div>
                    <div class="stat-icon"><i class="ion ion-close-circled"></i></div>
                </div>
            </div>
            <div class="stat-card-wrapper">
                <div class="stat-box active">
                    <div class="stat-content">
                        <h3>{$stats.active}</h3>
                        <p>Active Plans</p>
                    </div>
                    <div class="stat-icon"><i class="ion ion-checkmark"></i></div>
                </div>
            </div>
            <div class="stat-card-wrapper">
                <div class="stat-box expired">
                    <div class="stat-content">
                        <h3>{$stats.expired}</h3>
                        <p>Expired Plans</p>
                    </div>
                    <div class="stat-icon"><i class="ion ion-clock"></i></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar - Responsive stacking on mobile -->
        <form method="get" action="{$_url}plugin/tv_binding" class="filter-row">
            <div class="row">
                <div class="col-xs-12 col-sm-6 col-md-2 filter-col">
                    <select name="status" class="form-control">
                        <option value="all" {if $status_filter=='all'}selected{/if}>All Status</option>
                        <option value="online" {if $status_filter=='online'}selected{/if}>Online Only</option>
                        <option value="offline" {if $status_filter=='offline'}selected{/if}>Offline Only</option>
                        <option value="active" {if $status_filter=='active'}selected{/if}>Active Plans</option>
                        <option value="expired" {if $status_filter=='expired'}selected{/if}>Expired Plans</option>
                    </select>
                </div>
                <div class="col-xs-12 col-sm-6 col-md-2 filter-col">
                    <select name="router" class="form-control">
                        <option value="">All Routers</option>
                        {foreach $routers as $r}
                            <option value="{$r['id']}" {if $router_filter==$r['id']}selected{/if}>
                                {$r['name']}
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-xs-12 col-sm-12 col-md-4 filter-col">
                    <input type="text" name="search" class="form-control" placeholder="Search by MAC address or plan name..." value="{$search}">
                </div>
                <div class="col-xs-6 col-sm-6 col-md-1 filter-col">
                    <select name="per_page" class="form-control">
                        <option value="10" {if $per_page==10}selected{/if}>10</option>
                        <option value="25" {if $per_page==25}selected{/if}>25</option>
                        <option value="50" {if $per_page==50}selected{/if}>50</option>
                        <option value="100" {if $per_page==100}selected{/if}>100</option>
                    </select>
                </div>
                <div class="col-xs-6 col-sm-6 col-md-2 filter-col">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-filter"></i> Filter</button>
                </div>
                <div class="col-xs-6 col-sm-6 col-md-1 filter-col">
                    <a href="{$_url}plugin/tv_binding" class="btn btn-default btn-block"><i class="fa fa-times"></i> Clear</a>
                </div>
            </div>
        </form>

        <!-- TV Bindings Table -->
        <div style="margin-bottom:10px;text-align:right;">
            <button class="btn btn-primary" onclick="openAddBindingModal()"><i class="fa fa-plus"></i> Add Binding</button>
        </div>
        <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>MAC Address</th>
                    <th>Plan</th>
                    <th>Router</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Bound Date</th>
                    <th>Expiry</th>
                    <th>Online</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                {if count($tv_bindings) > 0}
                    {foreach $tv_bindings as $binding}
                        <tr>
                            <td><code>{$binding['mac_address']}</code></td>
                            <td>
                                <strong>{$binding['plan_name']}</strong><br>
                                <small class="text-muted">{$_admin['currency']} {$binding['plan_price']}</small>
                            </td>
                            <td>{$binding['router_name']}</td>
                            <td>{$binding['phone_number']}</td>
                            <td>
                                {if isset($binding['binding_type']) && $binding['binding_type'] === 'voucher'}
                                    <span style="background:#8e44ad;color:white;padding:3px 8px;border-radius:12px;font-size:11px;">Voucher</span>
                                {elseif isset($binding['binding_type']) && $binding['binding_type'] === 'plan'}
                                    <span style="background:#2980b9;color:white;padding:3px 8px;border-radius:12px;font-size:11px;">STK Plan</span>
                                {else}
                                    <span style="background:#7f8c8d;color:white;padding:3px 8px;border-radius:12px;font-size:11px;">Admin</span>
                                {/if}
                            </td>
                            <td><small>{$binding['binding_date_display']}</small></td>
                            <td><small>{$binding['expiry_date_display']}</small></td>
                            <td>
                                {if $binding['online_status'] === 'online'}
                                    {if $binding['plan_status'] === 'expired' && isset($binding['access_status']) && $binding['access_status'] === 'blocked'}
                                        <span style="background:#f39c12;color:white;padding:5px 10px;border-radius:20px;font-size:12px;font-weight:500;">● Seen (Blocked)</span>
                                    {else}
                                        <span class="badge-online">● Online</span>
                                    {/if}
                                {elseif $binding['online_status'] === 'offline'}
                                    <span class="badge-offline">● Offline</span>
                                {else}
                                    <span style="background:#f39c12;color:white;padding:4px 8px;border-radius:4px;font-size:11px;">?</span>
                                {/if}
                            </td>
                            <td>
                                {if isset($binding['status']) && $binding['status'] === 'pending'}
                                    <span style="background:#e67e22;color:white;padding:4px 8px;border-radius:4px;font-size:11px;">Pending</span>
                                {elseif $binding['plan_status'] === 'active'}
                                    <span class="badge-active">Active</span>
                                {else}
                                    <span class="badge-expired">Expired</span>
                                {/if}
                            </td>
                            <td style="white-space:nowrap;">
                                {if isset($binding['status']) && $binding['status'] === 'pending'}
                                    <button class="btn btn-warning btn-sm" onclick="checkPayment({$binding['id']})" title="Check Payment">
                                        <i class="fa fa-refresh"></i> Check
                                    </button>
                                {/if}
                                <button class="btn btn-danger btn-sm" onclick="deleteBinding({$binding['id']})" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="10" class="text-center text-muted" style="padding:30px;">
                            No TV bindings found. <a href="javascript:openAddBindingModal()" style="color:#2c5aa0;">Create one now</a>
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>
        </div>

        {if $total_pages > 1}
        <div class="row" style="margin-top:10px;">
            <div class="col-xs-12 col-sm-6" style="padding-top:8px;">
                <small class="text-muted">Page {$page} of {$total_pages}</small>
            </div>
            <div class="col-xs-12 col-sm-6 text-right">
                <ul class="pagination" style="margin:0;">
                    <li class="{if $page <= 1}disabled{/if}">
                        <a href="{if $page <= 1}javascript:void(0){else}{$_url}plugin/tv_binding&status={$status_filter|escape:'url'}&router={$router_filter|escape:'url'}&search={$search|escape:'url'}&per_page={$per_page}&page={$page-1}{/if}">&laquo;</a>
                    </li>
                    {for $p=$pagination_start to $pagination_end}
                        <li class="{if $p == $page}active{/if}">
                            <a href="{$_url}plugin/tv_binding&status={$status_filter|escape:'url'}&router={$router_filter|escape:'url'}&search={$search|escape:'url'}&per_page={$per_page}&page={$p}">{$p}</a>
                        </li>
                    {/for}
                    <li class="{if $page >= $total_pages}disabled{/if}">
                        <a href="{if $page >= $total_pages}javascript:void(0){else}{$_url}plugin/tv_binding&status={$status_filter|escape:'url'}&router={$router_filter|escape:'url'}&search={$search|escape:'url'}&per_page={$per_page}&page={$page+1}{/if}">&raquo;</a>
                    </li>
                </ul>
            </div>
        </div>
        {/if}
    </div>
</div>

<!-- Add Binding Modal -->
<div id="addBindingModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="ion ion-monitor"></i> Add TV MAC Binding</h4>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addBindingForm" class="form-horizontal">
                    <input type="hidden" id="bindingType" name="binding_type" value="plan">

                    <div class="form-group">
                        <label class="col-md-3 control-label">Bind By</label>
                        <div class="col-md-8">
                            <select class="form-control" id="bindingTypeSelect" onchange="setBindingType(this.value)">
                                <option value="plan">Plan + STK Payment</option>
                                <option value="voucher">Voucher Code</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label">MAC Address <span style="color:red">*</span></label>
                        <div class="col-md-8">
                            <input type="text" class="form-control input-sm" id="macAddress" name="mac_address" placeholder="AA:BB:CC:DD:EE:FF" maxlength="17" autocomplete="off" style="font-family:monospace;font-size:13px;">
                            <span class="help-block" style="display:block;margin-top:3px;margin-bottom:0;font-size:11px;">Type hex digits only &mdash; colons are inserted automatically</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label">Router <span style="color:red">*</span></label>
                        <div class="col-md-8">
                            <select class="form-control" id="routerId" name="router_id">
                                <option value="">-- Select Router --</option>
                                {foreach $routers as $router}
                                    <option value="{$router['id']}">{$router['name']}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    <div id="planFields">
                        <div class="form-group">
                            <label class="col-md-3 control-label">Plan <span style="color:red">*</span></label>
                            <div class="col-md-8">
                                <select class="form-control" id="planId" name="plan_id">
                                    <option value="">-- Select Plan --</option>
                                    {foreach $plans as $plan}
                                        <option value="{$plan['id']}">{$plan['name_plan']} &mdash; {$_admin['currency']} {$plan['price']}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-3 control-label">Phone <span style="color:red">*</span></label>
                            <div class="col-md-8">
                                <input type="text" class="form-control" id="phoneNumber" name="phone_number" placeholder="0712345678">
                                <p class="help-block" style="margin-bottom:0;">M-Pesa STK push will be sent to this number</p>
                            </div>
                        </div>
                    </div>

                    <div id="voucherFields" style="display:none;">
                        <div class="form-group">
                            <label class="col-md-3 control-label">Voucher Code <span style="color:red">*</span></label>
                            <div class="col-md-8">
                                <input type="text" class="form-control" id="voucherCode" name="voucher_code" placeholder="Enter voucher code">
                                <p class="help-block" style="margin-bottom:0;">Device will be bound immediately using this voucher's plan</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="submitBindingBtn" class="btn btn-primary" onclick="submitAddBinding()">
                    <i class="fa fa-mobile"></i> Send STK &amp; Bind
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function showAddBindingModal() {
        if (window.jQuery) {
            window.jQuery('#addBindingModal').modal('show');
            return;
        }
        var modal = document.getElementById('addBindingModal');
        if (!modal) return;
        modal.style.display = 'block';
        modal.classList.add('in');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function hideAddBindingModal() {
        if (window.jQuery) {
            window.jQuery('#addBindingModal').modal('hide');
            return;
        }
        var modal = document.getElementById('addBindingModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('in');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function handleAddBindingModalHidden() {
        if (_adminTvPollInterval) { clearInterval(_adminTvPollInterval); _adminTvPollInterval = null; }
        _adminTvBindingId = null;
        document.querySelector('#addBindingModal .modal-footer').style.display = '';
        document.getElementById('addBindingForm').reset();
        setBindingType('plan');
    }

    // Auto-insert colons as user types; block manual colon entry
    document.addEventListener('DOMContentLoaded', function() {
        var macInput = document.getElementById('macAddress');
        if (macInput) {
            macInput.addEventListener('keydown', function(e) {
                // Block colon, hyphen, dot, space — user cannot type separators
                if (e.key === ':' || e.key === '-' || e.key === '.' || e.key === ' ') {
                    e.preventDefault();
                }
            });
            macInput.addEventListener('input', function() {
                var el = this, val = el.value;
                var pos = (el.selectionStart != null) ? el.selectionStart : val.length;
                setTimeout(function() {
                    var hexBefore = 0;
                    for (var i = 0; i < pos && i < val.length; i++) {
                        if (/[0-9a-fA-F]/i.test(val[i])) hexBefore++;
                    }
                    var raw = val.replace(/[^0-9a-fA-F]/gi, '').toUpperCase().slice(0, 12);
                    var parts = [];
                    for (var i = 0; i < raw.length; i += 2) parts.push(raw.slice(i, i + 2));
                    var fmt = parts.join(':');
                    el.value = fmt;
                    var newPos = fmt.length;
                    if (hexBefore === 0) {
                        newPos = 0;
                    } else {
                        var cnt = 0;
                        for (var i = 0; i < fmt.length; i++) {
                            if (/[0-9A-F]/.test(fmt[i])) {
                                cnt++;
                                if (cnt === hexBefore) { newPos = i + 1; break; }
                            }
                        }
                    }
                    try { el.setSelectionRange(newPos, newPos); } catch(ex) {}
                }, 0);
            });
        }

        var addBindingModal = document.getElementById('addBindingModal');
        if (addBindingModal) {
            addBindingModal.addEventListener('hidden.bs.modal', handleAddBindingModalHidden);
        }
    });

    function openAddBindingModal() {
        document.getElementById('addBindingForm').reset();
        setBindingType('plan');
        showAddBindingModal();
    }

    function setBindingType(type) {
        document.getElementById('bindingType').value = type;
        document.getElementById('bindingTypeSelect').value = type;
        if (type === 'plan') {
            document.getElementById('planFields').style.display = '';
            document.getElementById('voucherFields').style.display = 'none';
            document.getElementById('submitBindingBtn').innerHTML = '<i class="fa fa-mobile"></i> Send STK &amp; Bind';
        } else {
            document.getElementById('planFields').style.display = 'none';
            document.getElementById('voucherFields').style.display = '';
            document.getElementById('submitBindingBtn').innerHTML = '<i class="fa fa-ticket"></i> Apply Voucher &amp; Bind';
        }
    }

    var _adminTvBindingId = null, _adminTvPollInterval = null;

    function submitAddBinding() {
        var bindingType = document.getElementById('bindingType').value;

        // Voucher path — unchanged, no polling needed
        if (bindingType !== 'plan') {
            var formData = new FormData(document.getElementById('addBindingForm'));
            formData.append('action', 'add');
            var btn = document.getElementById('submitBindingBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            fetch('{$_url}plugin/tv_binding', {ldelim} method: 'POST', body: formData {rdelim})
            .then(function(r){ return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                setBindingType(bindingType);
                if (data.success) {
                    hideAddBindingModal();
                    Swal.fire({ icon: 'success', title: 'Success', text: data.message, confirmButtonColor: '#2c5aa0' })
                        .then(function(){ location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            .catch(function() {
                btn.disabled = false;
                setBindingType(bindingType);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Check your connection.' });
            });
            return;
        }

        // STK payment path — fire STK then auto-poll until done
        var formData = new FormData(document.getElementById('addBindingForm'));
        formData.append('action', 'add');
        var btn = document.getElementById('submitBindingBtn');
        var modalBody = document.querySelector('#addBindingModal .modal-body');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending STK...';

        fetch('{$_url}plugin/tv_binding', {ldelim} method: 'POST', body: formData {rdelim})
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.success) {
                btn.disabled = false;
                setBindingType('plan');
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                return;
            }

            // STK sent — replace modal body with waiting UI
            _adminTvBindingId = data.binding_id;
            modalBody.innerHTML =
                '<div id="tvWaitingBlock" style="text-align:center;padding:30px 20px;">' +
                    '<div style="font-size:48px;margin-bottom:16px;">📱</div>' +
                    '<h4 style="margin:0 0 8px;">Payment Request Sent</h4>' +
                    '<p style="color:#555;font-size:14px;margin:0 0 20px;">Check your phone and enter your M-Pesa PIN to complete the payment.</p>' +
                    '<div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:16px;">' +
                        '<div class="fa fa-spinner fa-spin" style="font-size:20px;color:#2c5aa0;"></div>' +
                        '<span id="tvWaitStatus" style="color:#2c5aa0;font-weight:600;">Waiting for payment...</span>' +
                    '</div>' +
                    '<div id="tvWaitTries" style="font-size:12px;color:#999;margin-bottom:20px;"></div>' +
                    '<button onclick="_adminCancelTvWait()" class="btn btn-default btn-sm">Cancel</button>' +
                '</div>';
            document.querySelector('#addBindingModal .modal-footer').style.display = 'none';

            var tries = 0, maxTries = 40; // 40 × 3s = 2 min
            _adminTvPollInterval = setInterval(function() {
                tries++;
                document.getElementById('tvWaitTries').textContent = 'Checking (' + tries + '/' + maxTries + ')...';

                if (tries > maxTries) {
                    clearInterval(_adminTvPollInterval); _adminTvPollInterval = null;
                    document.getElementById('tvWaitStatus').textContent = 'Timed out waiting for payment.';
                    document.getElementById('tvWaitTries').textContent = 'If you paid, please refresh the page to check status.';
                    document.querySelector('#addBindingModal .modal-footer').style.display = '';
                    return;
                }

                var fd = new FormData();
                fd.append('action', 'check_payment');
                fd.append('binding_id', _adminTvBindingId);
                fetch('{$_url}plugin/tv_binding', {ldelim} method: 'POST', body: fd {rdelim})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        clearInterval(_adminTvPollInterval); _adminTvPollInterval = null;
                        hideAddBindingModal();
                        Swal.fire({ icon: 'success', title: 'TV Activated!', text: res.message, confirmButtonColor: '#2c5aa0' })
                            .then(function(){ location.reload(); });
                    } else if (res.status === 'cancelled' || res.status === 'failed') {
                        clearInterval(_adminTvPollInterval); _adminTvPollInterval = null;
                        hideAddBindingModal();
                        Swal.fire({ icon: res.status === 'cancelled' ? 'warning' : 'error', title: res.status === 'cancelled' ? 'Cancelled' : 'Payment Failed', text: res.message });
                    }
                    // status==='pending' → keep polling
                })
                .catch(function(){}); // network blip — keep polling
            }, 3000);
        })
        .catch(function() {
            btn.disabled = false;
            setBindingType('plan');
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Check your connection.' });
        });
    }

    // Legacy manual check for already-pending rows in the table
    function checkPayment(bindingId) {
        Swal.fire({ title: 'Checking payment...', allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); } });
        var fd = new FormData();
        fd.append('action', 'check_payment');
        fd.append('binding_id', bindingId);
        fetch('{$_url}plugin/tv_binding', {ldelim} method: 'POST', body: fd {rdelim})
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Activated!', text: data.message, confirmButtonColor: '#2c5aa0' })
                    .then(function(){ location.reload(); });
            } else if (data.status === 'pending') {
                Swal.fire({ icon: 'warning', title: 'Still Pending', text: data.message });
            } else {
                Swal.fire({ icon: 'error', title: data.status === 'cancelled' ? 'Cancelled' : 'Failed', text: data.message });
            }
        })
        .catch(function(){ Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
    }

    function _adminCancelTvWait() {
        if (_adminTvPollInterval) { clearInterval(_adminTvPollInterval); _adminTvPollInterval = null; }
        _adminTvBindingId = null;
        hideAddBindingModal();
    }

    function deleteBinding(bindingId) {
        Swal.fire({
            title: 'Delete Binding?', text: 'This will remove the device from Mikrotik as well.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('binding_id', bindingId);
                fetch('{$_url}plugin/tv_binding', {ldelim} method: 'POST', body: formData {rdelim})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted', text: data.message, confirmButtonColor: '#2c5aa0' })
                            .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                });
            }
        });
    }
</script>

{include file="sections/footer.tpl"}

