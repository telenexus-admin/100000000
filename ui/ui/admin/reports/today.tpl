{include file="sections/header.tpl"}
<!-- Today's Transactions Only -->

<div class="row">
    <div class="col-lg-12">
        <div class="box box-primary box-solid">
            <div class="box-header">
                <h3 class="box-title">
                    <i class="fa fa-calendar-day"></i> 
                    {Lang::T('Today\'s Transactions')} - {$today_date|date_format:"%d %b %Y"}
                </h3>
                <div class="box-tools pull-right">
                    <a href="{Text::url('export/print-by-date&sd=')}{$today_date}&ed={$today_date}" 
                       class="btn btn-default btn-sm" target="_blank">
                        <i class="ion ion-printer"></i> {Lang::T('Print')}
                    </a>
                    <a href="{Text::url('export/pdf-by-date&sd=')}{$today_date}&ed={$today_date}" 
                       class="btn btn-default btn-sm">
                        <i class="fa fa-file-pdf-o"></i> {Lang::T('PDF')}
                    </a>
                    <a href="{Text::url('reports/today')}" class="btn btn-success btn-sm">
                        <i class="fa fa-refresh"></i> {Lang::T('Refresh')}
                    </a>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3>{$today_count|default:0}</h3>
                                <p>{Lang::T('Total Transactions')}</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3>{Lang::moneyFormat($today_revenue|default:0)}</h3>
                                <p>{Lang::T('Total Revenue')}</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-money"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-blue">
                            <div class="inner">
                                <h3>{$today_hotspot|default:0}</h3>
                                <p>{Lang::T('Hotspot Transactions')}</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-wifi"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="small-box bg-purple">
                            <div class="inner">
                                <h3>{$today_pppoe|default:0}</h3>
                                <p>{Lang::T('PPPoE Transactions')}</p>
                            </div>
                            <div class="icon">
                                <i class="fa fa-network-wired"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <div class="table-responsive">
                <div style="margin: 10px;">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr class="bg-primary">
                                <th width="5%">#</th>
                                <th width="15%">{Lang::T('Username')}</th>
                                <th width="10%">{Lang::T('Type')}</th>
                                <th width="15%">{Lang::T('Plan Name')}</th>
                                <th width="10%" class="text-right">{Lang::T('Plan Price')}</th>
                                <th width="15%">{Lang::T('Transaction Time')}</th>
                                <th width="15%">{Lang::T('Expires On')}</th>
                                <th width="10%">{Lang::T('Method')}</th>
                                <th width="5%">{Lang::T('Router')}</th>
                              </tr>
                        </thead>
                        <tbody>
                            {if isset($today_transactions) && $today_transactions|count > 0}
                                {foreach $today_transactions as $index => $ds}
                                <tr>
                                    <td class="text-center">{$index + 1}</td>
                                    <td>
                                        <strong>{$ds.username}</strong>
                                        {if isset($ds.customer_name) && $ds.customer_name}
                                            <br><small class="text-muted">{$ds.customer_name}</small>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $ds.type == 'Hotspot'}
                                            <span class="label label-info"><i class="fa fa-wifi"></i> {$ds.type}</span>
                                        {elseif $ds.type == 'PPPOE'}
                                            <span class="label label-primary"><i class="fa fa-network-wired"></i> {$ds.type}</span>
                                        {else}
                                            <span class="label label-default">{$ds.type}</span>
                                        {/if}
                                    </td>
                                    <td>{$ds.plan_name}</td>
                                    <td class="text-right">
                                        <strong class="text-success">{Lang::moneyFormat($ds.price)}</strong>
                                    </td>
                                    <td>
                                        <i class="fa fa-clock-o"></i> 
                                        {if isset($ds.recharged_time)}
                                            {$ds.recharged_on|date_format:"%d %b %Y"} {$ds.recharged_time}
                                        {else}
                                            {$ds.recharged_on|date_format:"%d %b %Y %H:%M"}
                                        {/if}
                                    </td>
                                    <td>
                                        <i class="fa fa-calendar"></i> 
                                        {if isset($ds.time)}
                                            {$ds.expiration|date_format:"%d %b %Y"} {$ds.time}
                                        {else}
                                            {$ds.expiration|date_format:"%d %b %Y %H:%M"}
                                        {/if}
                                    </td>
                                    <td>
                                        <span class="label label-default">
                                            <i class="fa fa-credit-card"></i> 
                                            {assign var="method_parts" value=" - "|explode:$ds.method}
                                            {$method_parts[0]}
                                        </span>
                                    </td>
                                    <td>
                                        <small><i class="fa fa-server"></i> {$ds.routers}</small>
                                    </td>
                                </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="9" class="text-center text-muted" style="padding: 50px;">
                                        <i class="fa fa-info-circle fa-2x"></i><br>
                                        <strong>{Lang::T('No transactions found for today')}</strong><br>
                                        <small>{Lang::T('Check back later for new transactions')}</small>
                                    </td>
                                </tr>
                            {/if}
                        </tbody>
                        {if isset($today_transactions) && $today_transactions|count > 0}
                        <tfoot>
                            <tr class="bg-warning">
                                <th colspan="4" class="text-right">
                                    <strong>{Lang::T('Total Revenue')}:</strong>
                                </th>
                                <th class="text-right">
                                    <strong class="text-success" style="font-size: 16px;">
                                        {Lang::moneyFormat($today_revenue|default:0)}
                                    </strong>
                                </th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                        {/if}
                    </table>
                </div>
            </div>
            
            <div class="box-footer">
                <div class="row">
                    <div class="col-md-12 text-center">
                        <div class="small text-muted">
                            <i class="fa fa-calendar-check-o"></i> 
                            {Lang::T('Showing transactions for')} <strong>{$today_date|date_format:"%d %b %Y"}</strong>
                            {if $today_count > 0}
                                | {Lang::T('Total')}: <strong>{$today_count}</strong> {Lang::T('transactions')} 
                                | {Lang::T('Revenue')}: <strong class="text-success">{Lang::moneyFormat($today_revenue)}</strong>
                                | {Lang::T('Hotspot')}: <strong>{$today_hotspot}</strong>
                                | {Lang::T('PPPoE')}: <strong>{$today_pppoe}</strong>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
.small-box {
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    position: relative;
    display: block;
    margin-bottom: 20px;
}
.small-box .inner {
    padding: 10px;
}
.small-box h3 {
    font-size: 38px;
    font-weight: bold;
    margin: 0 0 10px 0;
    white-space: nowrap;
    padding: 0;
}
.small-box p {
    font-size: 15px;
}
.small-box .icon {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 0;
    font-size: 70px;
    color: rgba(0,0,0,0.15);
}
.bg-green {
    background-color: #00a65a !important;
    color: #fff;
}
.bg-yellow {
    background-color: #f39c12 !important;
    color: #fff;
}
.bg-blue {
    background-color: #0073b7 !important;
    color: #fff;
}
.bg-purple {
    background-color: #605ca8 !important;
    color: #fff;
}
.table-hover tbody tr:hover {
    background-color: #f5f5f5;
}
.label {
    display: inline-block;
    padding: 3px 7px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 3px;
}
.label-info {
    background-color: #00c0ef;
}
.label-primary {
    background-color: #3c8dbc;
}
.label-default {
    background-color: #d2d6de;
    color: #444;
}
.text-success {
    color: #00a65a;
}
.text-muted {
    color: #777;
}
.text-green {
    color: #00a65a;
}
.text-blue {
    color: #0073b7;
}
.text-purple {
    color: #605ca8;
}
</style>

{include file="sections/footer.tpl"}