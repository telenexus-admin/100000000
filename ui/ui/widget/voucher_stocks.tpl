<div class="panel panel-info panel-hovered mb20 table-responsive">
    <div class="panel-heading">{Lang::T('Voucher Stocks')}</div>
    <div class="panel-body" style="padding: 10px 15px;">
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div><strong>{Lang::T('Unused')}:</strong> {$stocks.unused|default:0}</div>
            <div><strong>{Lang::T('Used')}:</strong> {$stocks.used|default:0}</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-condensed">
            <thead>
                <tr>
                    <th>{Lang::T('Package')}</th>
                    <th>{Lang::T('Unused')}</th>
                    <th>{Lang::T('Used')}</th>
                </tr>
            </thead>
            <tbody>
                {if isset($plans) && $plans|@count > 0}
                    {foreach $plans as $plan}
                        <tr>
                            <td>{$plan.name_plan|default:'-'}</td>
                            <td>{$plan.unused|default:0}</td>
                            <td>{$plan.used|default:0}</td>
                        </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="3" class="text-center">{Lang::T('No data available')}</td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>
