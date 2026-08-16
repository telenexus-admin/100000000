{if $w_data.show}
    {if $w_data.type eq 'expiring'}
        <div class="panel panel-cron-warning panel-hovered mb20 activities">
            <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: center;">
                <span>
                    <i class="fa fa-clock-o"></i> &nbsp; {Lang::T('Subscription Expires in')}: 
                    <b>{$w_data.days_until_expiry} {Lang::T('Days')}</b>
                </span>
                <a href="{$app_url}/?_route=plugin/subscription_manager" class="label label-warning" style="font-size: 11px; padding: 4px 8px;">
                    {Lang::T('MANAGE')} <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    {elseif $w_data.type eq 'invoice_pending'}
        <div class="panel panel-cron-danger panel-hovered mb20 activities">
            <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: center;">
                <span>
                    <i class="fa fa-warning"></i> &nbsp; <b>{Lang::T('PAYMENT OVERDUE')}</b>: {Lang::T('Please pay your invoice to avoid disconnection.')}
                </span>
                <a href="{$app_url}/?_route=plugin/subscription_manager" class="label label-danger" style="font-size: 11px; padding: 4px 8px; background-color: #dd4b39;">
                    {Lang::T('PAY NOW')} <i class="fa fa-credit-card"></i>
                </a>
            </div>
        </div>
    {/if}
{/if}