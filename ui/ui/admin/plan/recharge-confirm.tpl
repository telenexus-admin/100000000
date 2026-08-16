{include file="sections/header.tpl"}

<style>
    :root {
        --primary: #f97316;
        --primary-dark: #ea580c;
        --primary-light: #fed7aa;
        --primary-soft: #fff7ed;
        --ink: #1e293b;
        --ink-soft: #475569;
    }

    * { box-sizing: border-box; }

    .container-fluid { padding-left: 8px; padding-right: 8px; }

    /* Card */
    .panel-primary {
        border: 1px solid var(--primary-light);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.08);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    .panel-primary > .panel-heading {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: #fff;
        border-color: var(--primary-dark);
        font-weight: 600;
        padding: 8px 12px;
        font-size: 14px;
    }
    .panel-heading i { margin-right: 6px; }
    .panel-body { padding: 10px 12px; background: #fff; }

    /* Grid */
    .row { margin-left: -5px; margin-right: -5px; }
    .col-md-6 { padding-left: 5px; padding-right: 5px; }
    @media (min-width: 992px) {
        .col-md-offset-3 { margin-left: 25%; }
        .col-md-6 { width: 50%; }
    }
    @media (max-width: 991px) {
        .col-md-6 { width: 100%; float: none; margin-left: 0; }
    }

    /* Section headings */
    .rc-section {
        color: var(--primary-dark);
        font-size: 13px;
        font-weight: 600;
        margin: 6px 0 4px;
        text-align: center;
        line-height: 1.2;
    }
    .rc-section i { margin-right: 5px; }
    center { margin: 4px 0 2px; }
    center b {
        color: var(--primary-dark);
        font-size: 13px;
        display: block;
        line-height: 1.2;
    }
    center b i { margin-right: 5px; }

    /* List */
    .list-group {
        margin-bottom: 8px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid var(--primary-light);
    }
    .list-group-unbordered { border: 1px solid var(--primary-light); }

    .list-group-item {
        border: none;
        border-bottom: 1px solid var(--primary-light);
        padding: 6px 10px;
        background: #fff;
        color: var(--ink);
        font-size: 12.5px;
        line-height: 1.3;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .list-group-item:last-child { border-bottom: none; }

    .list-group-item b {
        color: var(--primary-dark);
        font-weight: 600;
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .list-group-item .pull-right {
        font-weight: 500;
        color: var(--ink);
        margin-left: auto;
        text-align: right;
        min-width: 0;
        flex: 1 1 auto;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    /* Stack label/value on very small screens */
    @media (max-width: 420px) {
        .list-group-item {
            flex-direction: column;
            align-items: stretch;
            gap: 2px;
            padding: 6px 10px;
        }
        .list-group-item b { white-space: normal; }
        .list-group-item .pull-right {
            text-align: left;
            margin-left: 0;
            font-size: 13px;
        }
    }

    /* Payment summary text */
    .list-group-item small {
        font-size: 11px;
        color: var(--ink-soft);
        display: block;
        margin-top: 2px;
    }

    .total-amount {
        font-size: 15px;
        font-weight: 700;
        color: var(--primary);
    }

    /* Select inside Payment via row */
    select[name="using"] {
        border: 1.5px solid var(--primary-light);
        border-radius: 16px;
        padding: 4px 28px 4px 10px;
        background: #fff;
        color: var(--primary-dark);
        font-weight: 600;
        outline: none;
        font-size: 12.5px;
        max-width: 100%;
        width: auto;
        min-width: 140px;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path fill='%23ea580c' d='M0 0l5 6 5-6z'/></svg>");
        background-repeat: no-repeat;
        background-position: right 10px center;
    }
    select[name="using"]:focus { border-color: var(--primary); }

    /* Buttons */
    .btn-success {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        color: #fff;
        font-weight: 600;
        border-radius: 24px;
        padding: 8px 20px;
        font-size: 13px;
        transition: all 0.2s;
        box-shadow: 0 4px 10px rgba(249, 115, 22, 0.25);
        min-width: 160px;
        margin-bottom: 4px;
    }
    .btn-success:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(249, 115, 22, 0.35);
    }
    .btn-link {
        color: var(--primary);
        font-weight: 500;
        font-size: 12.5px;
        padding: 4px 10px;
        display: inline-block;
    }
    .btn-link:hover { color: var(--primary-dark); }

    form > center {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        margin-top: 6px;
    }

    .mb30 { margin-bottom: 15px; }
</style>

<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-check"></i>
                {Lang::T('Confirm Recharge')}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}plan/recharge-post">
                    <center><b><i class="glyphicon glyphicon-user"></i> {Lang::T('Customer Information')}</b></center>
                    <ul class="list-group list-group-unbordered">
                        <li class="list-group-item">
                            <b>{Lang::T('Username')}</b> <span class="pull-right">{$cust['username']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Name')}</b> <span class="pull-right">{$cust['fullname']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Phone Number')}</b> <span class="pull-right">{$cust['phonenumber']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Email')}</b> <span class="pull-right">{$cust['email']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Address')}</b> <span class="pull-right">{$cust['address']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Balance')}</b> <span class="pull-right" style="color: var(--primary); font-weight: 700;">{Lang::moneyFormat($cust['balance'])}</span>
                        </li>
                    </ul>
                    
                    <center><b><i class="glyphicon glyphicon-gift"></i> {Lang::T('Plan Details')}</b></center>
                    <ul class="list-group list-group-unbordered">
                        <li class="list-group-item">
                            <b>{Lang::T('Plan Name')}</b> <span class="pull-right">{$plan['name_plan']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Location')}</b> <span class="pull-right">{if $plan['is_radius']}Radius{else}{$plan['routers']}{/if}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Type')}</b> <span class="pull-right">{if $plan['prepaid'] eq 'yes'}Prepaid{else}Postpaid{/if} {$plan['type']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Bandwidth')}</b> <span class="pull-right" api-get-text="{Text::url('')}autoload/bw_name/{$plan['id_bw']}"></span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Plan Price')}</b> <span class="pull-right">{if $using eq 'zero'}{Lang::moneyFormat(0)}{else}{Lang::moneyFormat($plan['price'])}{/if}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Plan Validity')}</b> <span class="pull-right">{$plan['validity']} {$plan['validity_unit']}</span>
                        </li>
                        <li class="list-group-item">
                            <b>{Lang::T('Payment via')}</b> <span class="pull-right">
                                <select name="using">
                                    {foreach $usings as $us}
                                        <option value="{trim($us)}" {if $using eq trim($us)}selected{/if}>{trim(ucWords($us))}</option>
                                    {/foreach}
                                    {if $_c['enable_balance'] eq 'yes'}
                                        <option value="balance" {if $using eq 'balance'}selected{/if}>{Lang::T('Customer Balance')}</option>
                                    {/if}
                                    {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                                        <option value="zero" {if $using eq 'zero'}selected{/if}>{$_c['currency_code']} 0</option>
                                    {/if}
                                </select>
                            </span>
                        </li>
                    </ul>
                    
                    <center><b><i class="glyphicon glyphicon-usd"></i> {Lang::T('Payment Summary')}</b></center>
                    <ul class="list-group list-group-unbordered">
                        {if $tax}
                            <li class="list-group-item">
                                <b>{Lang::T('Tax')}</b> <span class="pull-right">{Lang::moneyFormat($tax)}</span>
                            </li>
                            {if $using neq 'zero' and $add_cost != 0}
                                {foreach $abills as $k => $v}
                                    {if strpos($v, ':') === false}
                                        <li class="list-group-item">
                                            <b>{$k}</b> <span class="pull-right">
                                                {Lang::moneyFormat($v)}
                                                <sup title="recurring" style="color: var(--primary);">8</sup>
                                                {assign var="total" value=$v+$total}
                                            </span>
                                        </li>
                                    {else}
                                        {assign var="exp" value=explode(':',$v)}
                                        {if $exp[1]>0}
                                            <li class="list-group-item">
                                                <b>{$k}</b> <span class="pull-right">
                                                    <sup title="{$exp[1]} more times" style="color: var(--primary);">({$exp[1]}x) </sup>
                                                    {Lang::moneyFormat($exp[0])}
                                                </span>
                                            </li>
                                        {/if}
                                    {/if}
                                {/foreach}
                                <li class="list-group-item">
                                    <b>{Lang::T('Additional Cost')}</b> <span class="pull-right"><b>{Lang::moneyFormat($add_cost)}</b></span>
                                </li>
                                <li class="list-group-item">
                                    <b>{$plan['name_plan']}</b> <span class="pull-right">{if $using eq 'zero'}{Lang::moneyFormat(0)}{else}{Lang::moneyFormat($plan['price'])}{/if}</span>
                                </li>
                                <li class="list-group-item" style="background: var(--primary-soft);">
                                    <b style="font-size: 16px;">{Lang::T('Total')}</b> 
                                    <small>({Lang::T('Plan Price')} + {Lang::T('Additional Cost')})</small>
                                    <span class="pull-right total-amount">{Lang::moneyFormat($plan['price']+$add_cost+$tax)}</span>
                                </li>
                            {else}
                                <li class="list-group-item" style="background: var(--primary-soft);">
                                    <b style="font-size: 16px;">{Lang::T('Total')}</b> 
                                    <small>({Lang::T('Plan Price')} + {Lang::T('Tax')})</small>
                                    <span class="pull-right total-amount">{if $using eq 'zero'}{Lang::moneyFormat(0)}{else}{Lang::moneyFormat($plan['price']+$tax)}{/if}</span>
                                </li>
                            {/if}
                        {else}
                            {if $using neq 'zero' and $add_cost != 0}
                                {foreach $abills as $k => $v}
                                    {if strpos($v, ':') === false}
                                        <li class="list-group-item">
                                            <b>{$k}</b> <span class="pull-right">
                                                {Lang::moneyFormat($v)}
                                                <sup title="recurring" style="color: var(--primary);">8</sup>
                                                {assign var="total" value=$v+$total}
                                            </span>
                                        </li>
                                    {else}
                                        {assign var="exp" value=explode(':',$v)}
                                        {if $exp[1]>0}
                                            <li class="list-group-item">
                                                <b>{$k}</b> <span class="pull-right">
                                                    <sup title="{$exp[1]} more times" style="color: var(--primary);">({$exp[1]}x) </sup>
                                                    {Lang::moneyFormat($exp[0])}
                                                </span>
                                            </li>
                                        {/if}
                                    {/if}
                                {/foreach}
                                <li class="list-group-item">
                                    <b>{Lang::T('Additional Cost')}</b> <span class="pull-right"><b>{Lang::moneyFormat($add_cost)}</b></span>
                                </li>
                                <li class="list-group-item">
                                    <b>{$plan['name_plan']}</b> <span class="pull-right">{if $using eq 'zero'}{Lang::moneyFormat(0)}{else}{Lang::moneyFormat($plan['price'])}{/if}</span>
                                </li>
                                <li class="list-group-item" style="background: var(--primary-soft);">
                                    <b style="font-size: 16px;">{Lang::T('Total')}</b> 
                                    <small>({Lang::T('Plan Price')} + {Lang::T('Additional Cost')})</small>
                                    <span class="pull-right total-amount">{Lang::moneyFormat($plan['price']+$add_cost)}</span>
                                </li>
                            {else}
                                <li class="list-group-item" style="background: var(--primary-soft);">
                                    <b style="font-size: 16px;">{Lang::T('Total')}</b> 
                                    <span class="pull-right total-amount">{if $using eq 'zero'}{Lang::moneyFormat(0)}{else}{Lang::moneyFormat($plan['price'])}{/if}</span>
                                </li>
                            {/if}
                        {/if}
                    </ul>
                    
                    <input type="hidden" name="id_customer" value="{$cust['id']}">
                    <input type="hidden" name="plan" value="{$plan['id']}">
                    <input type="hidden" name="server" value="{$server}">
                    <input type="hidden" name="stoken" value="{App::getToken()}">
                    
                    <center>
                        <button class="btn btn-success" type="submit">
                            <i class="glyphicon glyphicon-ok"></i> {Lang::T('Confirm Recharge')}
                        </button>
                        <a class="btn btn-link" href="{Text::url('')}plan/recharge">
                            <i class="glyphicon glyphicon-remove"></i> {Lang::T('Cancel')}
                        </a>
                    </center>
                </form>
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}