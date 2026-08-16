{include file="sections/header.tpl"}

<style>
:root {
    --primary: #f97316;
    --primary-dark: #ea580c;
    --primary-light: #fed7aa;
    --primary-soft: #fff7ed;
}

.bg-orange { background-color: var(--primary) !important; }
.text-orange { color: var(--primary) !important; }
.border-orange { border-color: var(--primary) !important; }

.panel.panel-primary {
    border-color: var(--primary) !important;
}

.panel.panel-primary > .panel-heading {
    background: linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary-dark) !important;
    color: white !important;
}

.btn-primary {
    background: linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary-dark) !important;
    color: white !important;
}

.btn-primary:hover {
    background: linear-gradient(145deg, var(--primary-dark), #c2410c) !important;
    border-color: #c2410c !important;
}

.btn-danger {
    background: linear-gradient(145deg, #dc3545, #bd2130) !important;
    border-color: #bd2130 !important;
}

.btn-success {
    background: linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary-dark) !important;
    color: white !important;
}

.btn-success:hover {
    background: linear-gradient(145deg, var(--primary-dark), #c2410c) !important;
    border-color: #c2410c !important;
}

.btn-info {
    background: linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary-dark) !important;
    color: white !important;
}

.btn-info:hover {
    background: linear-gradient(145deg, var(--primary-dark), #c2410c) !important;
    border-color: #c2410c !important;
}

.form-control:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 5px rgba(249, 115, 22, 0.3) !important;
}

a {
    color: var(--primary);
}

a:hover {
    color: var(--primary-dark);
}

.table > thead > tr > th {
    color: var(--primary);
    border-bottom-color: var(--primary) !important;
}

.table > thead > tr > th[style*="background-color"] {
    background-color: var(--primary-soft) !important;
    color: var(--primary-dark) !important;
}

.label-primary {
    background-color: var(--primary) !important;
}

.pagination > .active > a,
.pagination > .active > a:hover,
.pagination > .active > a:focus,
.pagination > .active > span,
.pagination > .active > span:hover,
.pagination > .active > span:focus {
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
}

.bs-callout-info {
    border-left-color: var(--primary) !important;
}

.bs-callout-info h4 {
    color: var(--primary) !important;
}
</style>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                <div class="btn-group pull-right">
                    <a class="btn btn-primary btn-xs" title="save" href="{Text::url('')}services/sync/pppoe"
                        onclick="return ask(this, '{Lang::T('This will sync/send PPPOE plan to Mikrotik')}?')"><span
                            class="glyphicon glyphicon-refresh" aria-hidden="true"></span> sync</a>
                </div>{Lang::T('PPPOE Package')}
            </div>
            <form id="site-search" method="post" action="{Text::url('')}services/pppoe">
                <div class="panel-body">
                    {* Widths re-balanced after removing Device and Status filters; sums to 12 on desktop. *}
                    <div class="row row-no-gutters" style="padding: 5px">
                        <div class="col-lg-2 col-md-4 col-sm-12" style="margin-bottom: 6px;">
                            <div class="input-group">
                                <div class="input-group-btn">
                                    <a class="btn btn-danger" title="Clear Search Query"
                                        href="{Text::url('')}services/pppoe"><span
                                            class="glyphicon glyphicon-remove-circle"></span></a>
                                </div>
                                <input type="text" name="name" class="form-control"
                                    placeholder="{Lang::T('Search by Name')}...">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="type1" name="type1">
                                <option value="">{Lang::T('Prepaid')} &amp; {Lang::T('Postpaid')}</option>
                                <option value="yes" {if $type1 eq 'yes'}selected{/if}>{Lang::T('Prepaid')}</option>
                                <option value="no" {if $type1 eq 'no'}selected{/if}>{Lang::T('Postpaid')}</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="type2" name="type2">
                                <option value="">{Lang::T('Type')}</option>
                                {foreach $type2s as $t}
                                    <option value="{$t}" {if $type2 eq $t}selected{/if}>{Lang::T($t)}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="bandwidth" name="bandwidth">
                                <option value="">{Lang::T('Bandwidth')}</option>
                                {foreach $bws as $b}
                                    <option value="{$b['id']}" {if $bandwidth eq $b['id']}selected{/if}>{$b['name_bw']}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-2 col-xs-6" style="margin-bottom: 6px;">
                            <select class="form-control" id="type3" name="type3">
                                <option value="">{Lang::T('Category')}</option>
                                {foreach $type3s as $t}
                                    <option value="{$t}" {if $type3 eq $t}selected{/if}>{$t}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-2 col-xs-6" style="margin-bottom: 6px;">
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
                                <option value="radius" {if $router eq 'radius'}selected{/if}>Radius</option>
                            </select>
                        </div>

                        {* Device filter hidden: PPPoE plans are always MikrotikPppoe. *}
                        <input type="hidden" name="device" value="">
                        {* Status filter hidden: listed plans are visible to admin regardless of enabled flag. *}
                        <input type="hidden" name="status" value="-">

                        <div class="col-lg-1 col-md-6 col-xs-6" style="margin-bottom: 6px;">
                            <button class="btn btn-success btn-block" type="submit" title="{Lang::T('Search')}">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                        <div class="col-lg-12 col-md-6 col-xs-6" style="margin-top: 6px;">
                            <a href="{Text::url('')}services/pppoe-add" class="btn btn-primary"
                                title="{Lang::T('New Service Plan')}">
                                <i class="ion ion-android-add"></i> {Lang::T('Add Service Plan')}
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
                                <th colspan="4" class="text-center">{Lang::T('Internet Plan')}</th>
                                <th></th>
                                <th colspan="2" class="text-center" style="background-color: var(--primary-soft); color: var(--primary-dark);">
                                    {Lang::T('Expired')}</th>
                                <th colspan="4"></th>
                            </tr>
                            <tr>
                                <th>{Lang::T('Name')}</th>
                                <th>{Lang::T('Type')}</th>
                                <th><a href="{Text::url('')}bandwidth/list">{Lang::T('Bandwidth')}</a></th>
                                <th>{Lang::T('Price')}</th>
                                <th>{Lang::T('Validity')}</th>
                                <th><a href="{Text::url('')}pool/list">{Lang::T('IP Pool')}</a></th>
                                <th style="background-color: var(--primary-soft); color: var(--primary-dark);">{Lang::T('Internet Plan')}</th>
                                <th style="background-color: var(--primary-soft); color: var(--primary-dark);">{Lang::T('Date')}</th>
                                <th><a href="{Text::url('')}routers/list">{Lang::T('Location')}</a></th>
                                <th>{Lang::T('Device')}</th>
                                <th>{Lang::T('Manage')}</th>
                                <th>ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds}
                                <tr {if $ds['enabled'] !=1}class="danger" title="disabled" {/if}>
                                    <td>{$ds['name_plan']}</td>
                                    <td>{$ds['plan_type']} {if $ds['prepaid'] !=
                                        'yes'}<b>{Lang::T('Postpaid')}</b>
                                    {else}
                                        {Lang::T('Prepaid')}
                                    {/if}</td>
                                <td>{$ds['name_bw']}</td>
                                <td>{Lang::moneyFormat($ds['price'])}{if !empty($ds['price_old'])}
                                        <sup
                                            style="text-decoration: line-through; color: var(--primary)">{Lang::moneyFormat($ds['price_old'])}</sup>
                                    {/if}
                                </td>
                                <td>{$ds['validity']} {$ds['validity_unit']}</td>
                                <td>{$ds['pool']}</td>
                                <td>{if $ds['plan_expired']}<a
                                        href="{Text::url('')}services/pppoe-edit/{$ds['plan_expired']}">{Lang::T('Yes')}</a>{else}{Lang::T('No')}
                                    {/if}</td>
                                <td>{if $ds['prepaid'] == no}{$ds['expired_date']}{/if}</td>
                                <td>
                                    {if $ds['is_radius']}
                                        <span class="label label-primary">RADIUS</span>
                                    {else}
                                        {if $ds['routers']!=''}
                                            <a href="{Text::url('routers/edit/0&name=')}{$ds['routers']}">{$ds['routers']}</a>
                                        {/if}
                                    {/if}
                                </td>
                                <td>{$ds['device']}</td>
                                <td>
                                    <a href="{Text::url('')}services/pppoe-edit/{$ds['id']}"
                                        class="btn btn-info btn-xs">{Lang::T('Edit')}</a>
                                    <a href="{Text::url('')}services/pppoe-delete/{$ds['id']}"
                                        onclick="return ask(this, '{Lang::T('Delete')}?')" id="{$ds['id']}"
                                        class="btn btn-danger btn-xs"><i class="glyphicon glyphicon-trash"></i></a>
                                </td>
                                <td>{$ds['id']}</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel-footer">
                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}