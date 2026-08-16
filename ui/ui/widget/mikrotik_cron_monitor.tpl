{if $_c['router_check'] && count($routeroffs) > 0}
    <div class="panel panel-danger">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-exclamation-triangle"></i> {Lang::T('Routers Offline')}
                <span class="badge pull-right">{count($routeroffs)}</span>
            </h3>
        </div>
        
        <div class="table-responsive">
            <table class="table table-condensed table-hover">
                <tbody>
                    {foreach $routeroffs as $ros}
                        <tr>
                            <td>
                                <a href="{Text::url('routers/edit/', $ros['id'])}" class="text-bold text-red">
                                    <i class="fa fa-exclamation-circle text-red"></i> 
                                    {$ros['name']}
                                </a>
                                <br>
                                <small class="text-muted">{$ros['ip_address']}</small>
                            </td>
                            <td class="text-right text-red" data-toggle="tooltip" 
                                data-placement="left" title="{Lang::dateTimeFormat($ros['last_seen'])}">
                                <i class="fa fa-clock-o"></i> 
                                {if $ros['last_seen']}
                                    {Lang::timeElapsed($ros['last_seen'])}
                                {else}
                                    {Lang::T('Never')}
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <div class="panel-footer small text-muted">
                        <span class="pull-right">
                <a href="javascript:void(0)" onclick="refreshRouterStatus()" title="{Lang::T('Reload page')}">
                    <i class="fa fa-refresh"></i>
                </a>
            </span>
        </div>
    </div>
{/if}

<script>
function refreshRouterStatus() {
    location.reload();
}

$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
