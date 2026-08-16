{include file="sections/header.tpl"}

<style>
    :root {
        --primary: #f97316;
        --primary-dark: #ea580c;
        --primary-light: #fed7aa;
        --primary-soft: #fff7ed;
    }

    .panel-primary {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .panel-primary > .panel-heading {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: white;
        border-color: var(--primary-dark);
        font-weight: 600;
        padding: 12px 15px;
    }
    
    .panel-heading .btn-primary {
        background: white;
        color: var(--primary);
        border: none;
        border-radius: 20px;
        font-weight: 600;
        padding: 4px 12px;
        transition: all 0.2s;
    }
    
    .panel-heading .btn-primary:hover {
        background: var(--primary-soft);
        transform: translateY(-1px);
    }
    
    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 12px;
        transition: all 0.2s;
        height: 40px;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    
    .btn-danger {
        background: #ef4444;
        border: none;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    .btn-success {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .btn-success:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    }

    /* Online status must stay green (do not inherit orange .btn-success theme) */
    .online-column .btn-conn-status.btn-conn-online,
    .online-column .btn-conn-status[data-status="online"],
    .btn-conn-status.btn-conn-online,
    .btn-conn-status[data-status="online"] {
        background: #16a34a !important;
        border-color: #15803d !important;
        color: #fff !important;
        opacity: 1 !important;
    }
    .btn-conn-status.btn-conn-offline,
    .btn-conn-status[data-status="offline"] {
        background: #f59e0b !important;
        border-color: #d97706 !important;
        color: #fff !important;
        opacity: 1 !important;
    }
    .btn-conn-status.btn-conn-expired,
    .btn-conn-status[data-status="expired"] {
        background: #dc2626 !important;
        border-color: #b91c1c !important;
        color: #fff !important;
        opacity: 1 !important;
    }
    
    .btn-primary {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        border-radius: 12px;
        font-weight: 500;
    }
    
    .btn-primary:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
    }
    
    .btn-warning {
        background: var(--primary-light);
        border: 1px solid var(--primary);
        color: var(--primary-dark);
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .btn-warning:hover {
        background: var(--primary);
        color: white;
    }
    
    .btn-info {
        background: var(--primary-soft);
        border: 1px solid var(--primary);
        color: var(--primary-dark);
        border-radius: 8px;
    }
    
    .btn-info:hover {
        background: var(--primary-light);
    }
    
    .table {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .table thead tr {
        background: var(--primary-soft);
        color: var(--primary-dark);
        font-weight: 600;
    }
    
    .table thead th {
        border-bottom: 2px solid var(--primary-light);
    }
    
    .table tbody tr:hover {
        background: var(--primary-soft);
    }
    
    .table .danger {
        background: #fff1f0;
        border-left: 4px solid var(--primary);
    }
    
    .table .danger:hover {
        background: #ffe4e2;
    }
    
    .pagination > li > a,
    .pagination > li > span {
        border: 1px solid var(--primary-light);
        color: var(--primary);
        margin: 0 3px;
        border-radius: 8px !important;
    }
    
    .pagination > li > a:hover,
    .pagination > li > span:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .pagination > .active > a,
    .pagination > .active > span {
        background: var(--primary);
        border-color: var(--primary);
    }

    .row-num-column {
        width: 3.25em;
        text-align: center;
        font-weight: 600;
        color: #64748b;
    }

    .online-column {
        white-space: nowrap;
        text-align: center;
    }
    
    .phone-link {
        color: var(--primary);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }
    
    .phone-link:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .phone-link i {
        font-size: 12px;
    }
</style>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                    <div class="btn-group pull-right">
                        <a class="btn btn-primary btn-xs" title="save" href="{Text::url('')}plan/sync"
                            onclick="return ask(this, '{Lang::T("This will sync dan send Customer active package to Mikrotik")}?')">
                            <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> {Lang::T("Sync")}
                        </a>
                    </div>
                {/if}
                <i class="glyphicon glyphicon-user" style="margin-right: 8px;"></i>
                {Lang::T('Active Customers')}
            </div>
            <form id="site-search" method="post" action="{Text::url('plan/list')}">
                <div class="panel-body">
                    <div class="row row-no-gutters" style="padding: 5px">
                        <div class="col-lg-3 col-md-4 col-xs-12" style="margin-bottom:8px;">
                            <div class="input-group">
                                <div class="input-group-btn">
                                    <a class="btn btn-danger" title="Clear Search Query"
                                        href="{Text::url('plan/list&status=on')}">
                                        <span class="glyphicon glyphicon-remove-circle"></span>
                                    </a>
                                </div>
                                <input type="text" name="search" id="active-search-input" class="form-control"
                                    placeholder="{Lang::T("Search")} username, phone, name..."
                                    value="{$search|escape:'html'}" autocomplete="off" autofocus>
                            </div>
                        </div>
                        <div class="col-lg-2 col-xs-4" style="margin-bottom:8px;">
                            <select class="form-control" id="router" name="router" onchange="this.form.submit()">
                                <option value="">{Lang::T("Router")}</option>
                                {foreach $routers as $r}
                                    <option value="{$r|escape:'html'}" {if $router eq $r }selected{/if}>{$r}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2 col-xs-4" style="margin-bottom:8px;">
                            <select class="form-control" id="plan" name="plan" onchange="this.form.submit()">
                                <option value="">{Lang::T("Plan Name")}</option>
                                {foreach $plans as $p}
                                    <option value="{$p['id']}" {if $plan eq $p['id'] }selected{/if}>{$p['name_plan']}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2 col-xs-4" style="margin-bottom:8px;">
                            <select class="form-control" id="status" name="status" onchange="this.form.submit()">
                                <option value="-">{Lang::T("Status")}</option>
                                <option value="on" {if $status eq 'on' }selected{/if}>{Lang::T("Active")}</option>
                                <option value="off" {if $status eq 'off' }selected{/if}>{Lang::T("Expired")}</option>
                            </select>
                        </div>
                        <div class="col-md-1 col-xs-6" style="margin-bottom:8px;">
                            <button class="btn btn-success btn-block" type="submit" title="{Lang::T('Search')}">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                        <div class="col-md-2 col-xs-6" style="margin-bottom:8px;">
                            <a href="{Text::url('plan/recharge')}" class="btn btn-primary btn-block">
                                <i class="ion ion-android-add"></i> {Lang::T("Recharge Account")}
                            </a>
                        </div>
                    </div>
                    {if $search ne ''}
                        <div class="text-muted" style="padding:0 5px 8px;">
                            {Lang::T('Search')}: <strong>{$search|escape:'html'}</strong>
                        </div>
                    {/if}
                </div>
            </form>
            <div class="table-responsive">
                <div style="margin-left: 5px; margin-right: 5px;">&nbsp;
                    <table id="active-customers-table" class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th class="row-num-column">#</th>
                                <th>{Lang::T("Username")}</th>
                                <th>{Lang::T("Phone Number")}</th>
                                <th class="online-column">{Lang::T('Online')}</th>
                                <th>{Lang::T("Plan Name")}</th>
                                <th>{Lang::T("Type")}</th>
                                <th>{Lang::T("Created On")}</th>
                                <th>{Lang::T("Expires On")}</th>
                                <th>{Lang::T("Time Remaining")}</th>
                                <th>{Lang::T("Method")}</th>
                                <th><a href="{Text::url('routers/list')}" style="color: var(--primary-dark);">{Lang::T("Location")}</a></th>
                                <th>{Lang::T("Manage")}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds name=activerow}
                                <tr {if $ds['status']=='off' }class="danger" {/if}
                                    data-search="{$ds['username']|escape:'html'} {$ds['phonenumber']|escape:'html'} {$ds['customer_fullname']|escape:'html'} {$ds['namebp']|escape:'html'}">
                                    <td class="row-num-column">{if isset($paginator.startpoint)}{$paginator.startpoint+$smarty.foreach.activerow.iteration}{else}{$smarty.foreach.activerow.iteration}{/if}</td>
                                    <td>
                                        {if $ds['customer_id'] == '0' || $ds['customer_id'] == 0}
                                            <a href="{Text::url('plan/voucher/&search=')}{$ds['username']|escape:'url'}" style="color: var(--primary); font-weight: 600;">
                                                {$ds['username']|escape:'html'}
                                            </a>
                                        {else}
                                            <a href="{Text::url('customers/view/', $ds['customer_id'])}" style="color: var(--primary); font-weight: 600;">
                                                {$ds['username']|escape:'html'}
                                            </a>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $ds['phonenumber']}
                                            <a href="tel:{$ds['phonenumber']|escape:'html'}" class="phone-link" title="{$ds['customer_fullname']|escape:'html'}">
                                                <i class="glyphicon glyphicon-earphone"></i> {$ds['phonenumber']|escape:'html'}
                                            </a>
                                        {else}
                                            <span class="text-muted">—</span>
                                        {/if}
                                    </td>
                                    <td class="online-column" align="center">
                                        {if $ds['conn_html']}
                                            {$ds['conn_html'] nofilter}
                                        {else}
                                            <span class="label label-default">{$ds['conn_status']|default:'—'|escape:'html'}</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $ds['type'] == 'Hotspot'}
                                            <a href="{Text::url('')}services/edit/{$ds['plan_id']}" style="color: var(--primary);">
                                                {$ds['namebp']|escape:'html'}
                                            </a>
                                        {elseif $ds['type'] == 'PPPOE'}
                                            <a href="{Text::url('')}services/pppoe-edit/{$ds['plan_id']}" style="color: var(--primary);">
                                                {$ds['namebp']|escape:'html'}
                                            </a>
                                        {else}
                                            {$ds['namebp']|escape:'html'}
                                        {/if}
                                    </td>
                                    <td><span class="badge" style="background: var(--primary-light); color: var(--primary-dark);">{$ds['type']|escape:'html'}</span></td>
                                    <td>{Lang::dateAndTimeFormat($ds['recharged_on'],$ds['recharged_time'])}</td>
                                    <td class="text-danger">{Lang::dateAndTimeFormat($ds['display_expiration']|default:$ds['expiration'],$ds['display_time']|default:$ds['time'])}</td>
                                    <td>
                                        {if $ds['time_remaining'] && $ds['time_remaining'] != '-'}
                                            <span class="label {if $ds['status']=='on'}label-success{else}label-danger{/if}">{$ds['time_remaining']|escape:'html'}</span>
                                        {else}
                                            <span class="text-muted">—</span>
                                        {/if}
                                    </td>
                                    <td>{$ds['method']|escape:'html'}</td>
                                    <td><span class="label" style="background: var(--primary-soft); color: var(--primary-dark); border: 1px solid var(--primary-light);">{$ds['routers']|escape:'html'}</span></td>
                                    <td>
                                        {if $ds['customer_id'] != '0' && $ds['customer_id'] != 0}
                                            <a href="{Text::url('customers/view/', $ds['customer_id'])}" class="btn btn-primary btn-xs" title="{Lang::T('View Customer')}">
                                                <i class="glyphicon glyphicon-user"></i>
                                            </a>
                                        {/if}
                                        <a href="{Text::url('')}plan/edit/{$ds['id']}" class="btn btn-warning btn-xs">
                                            <i class="glyphicon glyphicon-pencil"></i> {Lang::T("Edit")}
                                        </a>
                                        {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                                            <a href="{Text::url('')}plan/delete/{$ds['id']}" id="{$ds['id']}"
                                                onclick="return ask(this, '{Lang::T("Delete")}?')"
                                                class="btn btn-danger btn-xs">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </a>
                                        {/if}
                                        {if $ds['status']=='off' && $_c['extend_expired']}
                                            <a href="{Text::url('')}plan/extend/{$ds['id']}"
                                                class="btn btn-info btn-xs">{Lang::T("Extend")}</a>
                                        {/if}
                                    </td>
                                </tr>
                            {foreachelse}
                                <tr>
                                    <td colspan="12" class="text-center text-muted" style="padding:24px">
                                        {if $search ne ''}
                                            {Lang::T('No customers found')} for "{$search|escape:'html'}"
                                        {else}
                                            {Lang::T('No customers found')}
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
            {include file="pagination.tpl"}
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('active-search-input');
    if (!input) return;
    // Keep focus at end of search text after submit
    try {
        var v = input.value;
        if (v) {
            input.focus();
            input.setSelectionRange(v.length, v.length);
        }
    } catch (e) {}
})();
function extend(id) {
    if (!id) return;
    window.location.href = '{Text::url("plan/extend/")}' + id;
}
</script>

{include file="sections/footer.tpl"}