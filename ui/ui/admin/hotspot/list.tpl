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
    
    .panel-body {
        background: white;
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
        color: white;
    }
    
    .btn-success:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    }
    
    .btn-primary {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        border-radius: 12px;
        font-weight: 500;
        color: white;
    }
    
    .btn-primary:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
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
    
    .input-group-addon {
        background: var(--primary-soft);
        border: 2px solid #e2e8f0;
        border-right: none;
        color: var(--primary);
        font-weight: 500;
    }
    
    /* Table styling */
    .table {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .table thead tr:first-child th {
        background: var(--primary-soft);
        color: var(--primary-dark);
        font-weight: 600;
        border-bottom: 2px solid var(--primary-light);
    }
    
    .table thead tr:first-child th[colspan] {
        background: var(--primary-soft);
        color: var(--primary-dark);
    }
    
    .table thead tr:last-child th {
        background: white;
        color: #1e293b;
        font-weight: 600;
        border-bottom: 2px solid var(--primary-light);
    }
    
    .table thead th[style*="background-color: rgb(246, 244, 244)"] {
        background: var(--primary-soft) !important;
        color: var(--primary-dark) !important;
    }
    
    .table thead th[style*="background-color: rgb(243, 241, 172)"] {
        background: #fef9c3 !important;
        color: #854d0e !important;
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
    
    .table .warning {
        background: #fef9c3;
        border-left: 4px solid #eab308;
    }
    
    .table .warning:hover {
        background: #fef08a;
    }
    
    .table td .label-primary {
        background: var(--primary);
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
    }
    
    .table td a {
        color: var(--primary);
        font-weight: 500;
    }
    
    .table td a:hover {
        color: var(--primary-dark);
    }
    
    .headcol {
        font-weight: 600;
        color: var(--primary-dark);
    }
    
    sup {
        color: #ef4444;
        font-size: 10px;
    }
    
    /* Pagination */
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
    
    /* Callout styling */
    .bs-callout-info {
        background: var(--primary-soft);
        border-left: 4px solid var(--primary);
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
    }
    
    .bs-callout-info h4 {
        color: var(--primary-dark);
        font-weight: 600;
        margin-top: 0;
        margin-bottom: 10px;
    }
    
    .bs-callout-info p {
        color: #1e293b;
        margin-bottom: 0;
    }
    
    /* Panel footer */
    .panel-footer {
        background: white;
        border-top: 2px solid var(--primary-light);
        padding: 15px;
    }
</style>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                <div class="btn-group pull-right">
                    <a class="btn btn-primary btn-xs" title="save" href="{Text::url('services/sync/hotspot')}"
                        onclick="return ask(this, 'This will sync/send hotspot package to Mikrotik?')">
                        <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> {Lang::T('sync')}
                    </a>
                </div>
                <i class="glyphicon glyphicon-tags" style="margin-right: 8px;"></i>
                {Lang::T('Hotspot Plans')}
            </div>
            <form id="site-search" method="post" action="{Text::url('services/hotspot/')}">
                <div class="panel-body">
                    {* Widths re-balanced after removing Device and Status filters; totals to 12 cols. *}
                    <div class="row row-no-gutters" style="padding: 5px">
                        <div class="col-lg-3 col-md-4 col-sm-12" style="margin-bottom: 6px;">
                            <div class="input-group">
                                <div class="input-group-btn">
                                    <a class="btn btn-danger" title="Clear Search Query"
                                        href="{Text::url('services/hotspot/')}">
                                        <span class="glyphicon glyphicon-remove-circle"></span>
                                    </a>
                                </div>
                                <input type="text" name="name" class="form-control"
                                    placeholder="{Lang::T('Search by Name')}...">
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="bandwidth" name="bandwidth">
                                <option value="">{Lang::T('Bandwidth')}</option>
                                {foreach $bws as $b}
                                    <option value="{$b['id']}" {if $bandwidth eq $b['id']}selected{/if}>{$b['name_bw']}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="type3" name="type3">
                                <option value="">{Lang::T('Category')}</option>
                                {foreach $type3s as $t}
                                    <option value="{$t}" {if $type3 eq $t}selected{/if}>{$t}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="valid" name="valid">
                                <option value="">{Lang::T('Validity')}</option>
                                {foreach $valids as $v}
                                    <option value="{$v}" {if $valid eq $v}selected{/if}>{$v}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="router" name="router">
                                <option value="">{Lang::T('Location')}</option>
                                {foreach $routers as $r}
                                    <option value="{$r}" {if $router eq $r}selected{/if}>{$r}</option>
                                {/foreach}
                            </select>
                        </div>

                        {* Device filter hidden: Hotspot plans are always MikrotikHotspot. *}
                        <input type="hidden" name="device" value="">
                        {* Status filter hidden: listed plans are visible to admin regardless of enabled flag. *}
                        <input type="hidden" name="status" value="-">

                        <div class="col-lg-1 col-md-6 col-xs-6" style="margin-bottom: 6px;">
                            <button class="btn btn-success btn-block" type="submit" title="{Lang::T('Search')}">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                        <div class="col-lg-12 col-md-6 col-xs-6" style="margin-top: 6px;">
                            <a href="{Text::url('services/add')}" class="btn btn-primary"
                                title="{Lang::T('New Service Package')}">
                                <i class="ion ion-android-add"></i> {Lang::T('Add New Package')}
                            </a>
                        </div>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <div style="margin-left: 5px; margin-right: 5px;">
                    <table class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th></th>
                                <th colspan="5" class="text-center">{Lang::T('Internet Package')}</th>
                                <th colspan="2" class="text-center" style="background-color: var(--primary-soft);">
                                    {Lang::T('Limit')}</th>
                                <th colspan="2"></th>
                                <th colspan="2" class="text-center" style="background-color: #fef9c3;">
                                    {Lang::T('Expired')}</th>
                                <th colspan="3"></th>
                            </tr>
                            <tr>
                                <th>{Lang::T('Name')}</th>
                                <th>{Lang::T('Type')}</th>
                                <th><a href="{Text::url('bandwidth/list')}">{Lang::T('Bandwidth')}</a></th>
                                <th>{Lang::T('Category')}</th>
                                <th>{Lang::T('Price')}</th>
                                <th>{Lang::T('Validity')}</th>
                                <th style="background-color: var(--primary-soft);">{Lang::T('Time')}</th>
                                <th style="background-color: var(--primary-soft);">{Lang::T('Data')}</th>
                                <th><a href="{Text::url('routers/list')}">{Lang::T('Router')}</a></th>
                                                                <th style="background-color: #fef9c3;">{Lang::T('Internet Package')}</th>
                                <th style="background-color: #fef9c3;">{Lang::T('Date')}</th>
                                <th>{Lang::T('ID')}</th>
                                <th>{Lang::T('Manage')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds}
                                <tr {if $ds['enabled'] !=1}class="danger" title="disabled" {elseif $ds['prepaid'] !='yes'}class="warning" title="Postpaid" {/if}>
                                    <td class="headcol">{$ds['name_plan']}</td>
                                    <td>
                                        {if $ds['prepaid'] == no}
                                            <span class="badge" style="background: #fef9c3; color: #854d0e;">Postpaid</span>
                                        {else}
                                            <span class="badge" style="background: var(--primary-soft); color: var(--primary-dark);">Prepaid</span>
                                        {/if}
                                        {$ds['plan_type']}
                                    </td>
                                    <td>{$ds['name_bw']}</td>
                                    <td>{$ds['typebp']}</td>
                                    <td>
                                        <span style="color: var(--primary-dark); font-weight: 600;">{Lang::moneyFormat($ds['price'])}</span>
                                        {if !empty($ds['price_old'])}
                                            <sup style="text-decoration: line-through; color: #ef4444">{Lang::moneyFormat($ds['price_old'])}</sup>
                                        {/if}
                                    </td>
                                    <td>{$ds['validity']} {$ds['validity_unit']}</td>
                                    <td>{$ds['time_limit']} {$ds['time_unit']}</td>
                                    <td>{$ds['data_limit']} {$ds['data_unit']}</td>
                                    <td>
                                        {if $ds['is_radius']}
                                           
                                        {else}
                                            {if $ds['routers']!=''}
                                                <a href="{Text::url('routers/edit/0&name=')}{$ds['routers']}">{$ds['routers']}</a>
                                            {/if}
                                        {/if}
                                    </td>
                                    
                                    <td>
                                        {if $ds['plan_expired']}
                                            <a href="{Text::url('services/edit/')}{$ds['plan_expired']}" class="text-success">
                                                <i class="glyphicon glyphicon-ok" style="color: var(--primary);"></i> {Lang::T('Yes')}
                                            </a>
                                        {else}
                                            <span class="text-muted">{Lang::T('No')}</span>
                                        {/if}
                                    </td>
                                    <td>{if $ds['prepaid'] == no}{$ds['expired_date']}{/if}</td>
                                    <td><span class="badge" style="background: var(--primary-soft); color: var(--primary-dark);">{$ds['id']}</span></td>
                                    <td>
                                        <a href="{Text::url('services/edit/')}{$ds['id']}" class="btn btn-info btn-xs">
                                            <i class="glyphicon glyphicon-pencil"></i> {Lang::T('Edit')}
                                        </a>
                                        <a href="{Text::url('services/delete/')}{$ds['id']}" id="{$ds['id']}"
                                            onclick="return ask(this, '{Lang::T('Delete')}?')"
                                            class="btn btn-danger btn-xs">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
           
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}
