{include file="sections/header.tpl"}

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="fa fa-server"></i> MikroTik Router Configurator
                <span class="label label-success pull-right" style="margin-left: 10px;">
                    <i class="fa fa-file-code"></i> Auto Script Generation
                </span>
            </div>
            <div class="panel-body">

                <!-- Search and Filter -->
                <div class="row mb20">
                    <div class="col-md-6">
                        <form method="get" class="form-inline">
                            <input type="hidden" name="_route" value="plugin/mikrotik_configurator">
                            <div class="form-group">
                                <input type="text" name="search" class="form-control" placeholder="Search routers..." value="{$search|default:''}">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Search
                            </button>
                            {if $search}
                                <a href="{$_url}plugin/mikrotik_configurator" class="btn btn-default">
                                    <i class="fa fa-refresh"></i> Reset
                                </a>
                            {/if}
                        </form>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="{$_url}plugin/radius_wireguard_setup" class="btn btn-success" style="margin-right:10px;">
                            <i class="fa fa-magic"></i> Automatic WireGuard Setup
                        </a>
                        <span class="text-muted">Total Routers: <strong>{$total_routers|default:$routers|@count}</strong></span>
                    </div>
                </div>

                <!-- Routers Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="25%"><i class="fa fa-server"></i> Router Name</th>
                                <th width="20%"><i class="fa fa-network-wired"></i> IP Address</th>
                                <th width="15%"><i class="fa fa-user"></i> Username</th>
                                <th width="15%">Status</th>
                                <th width="20%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $routers && $routers|@count > 0}
                                {assign var="counter" value=1}
                                {foreach $routers as $router}
                                    <tr>
                                        <td>{$counter}</td>
                                        <td>
                                            <strong>{$router.name}</strong>
                                        </td>
                                        <td>
                                            <span class="label label-info">{$router.ip_address}</span>
                                        </td>
                                        <td>{$router.username|default:'-'}</td>
                                        <td>
                                            <span class="label label-success">
                                                <i class="fa fa-check-circle"></i> Active
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="{$_url}plugin/mikrotik_configurator_config_ui&router_id={$router.id}"
                                               class="btn btn-primary btn-sm"
                                               title="Configure {$router.name}">
                                                <i class="fa fa-cog"></i> Configure
                                            </a>
                                            <a href="{$_url}plugin/mikrotik_configurator_manage_pools&router_id={$router.id}"
                                               class="btn btn-success btn-sm"
                                               title="Manage Pools">
                                                <i class="fa fa-database"></i> Pools
                                            </a>
                                            <a href="{$_url}routers/edit/{$router.id}"
                                               class="btn btn-info btn-sm"
                                               title="Edit Router">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    {assign var="counter" value=$counter+1}
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        <p class="mt20 mb20">
                                            <i class="fa fa-inbox fa-3x"></i><br><br>
                                            No routers found. Please add a router first.
                                        </p>
                                    </td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                {if $total_pages && $total_pages > 1}
                    <nav class="text-center">
                        <ul class="pagination">
                            {if $page > 1}
                                <li>
                                    <a href="{$_url}plugin/mikrotik_configurator&page={$page-1}{if $search}&search={$search|escape:'url'}{/if}">
                                        &laquo; Previous
                                    </a>
                                </li>
                            {else}
                                <li class="disabled"><span>&laquo; Previous</span></li>
                            {/if}

                            {for $i=1 to $total_pages}
                                {if $i == $page}
                                    <li class="active"><span>{$i}</span></li>
                                {else}
                                    <li>
                                        <a href="{$_url}plugin/mikrotik_configurator&page={$i}{if $search}&search={$search|escape:'url'}{/if}">
                                            {$i}
                                        </a>
                                    </li>
                                {/if}
                            {/for}

                            {if $page < $total_pages}
                                <li>
                                    <a href="{$_url}plugin/mikrotik_configurator&page={$page+1}{if $search}&search={$search|escape:'url'}{/if}">
                                        Next &raquo;
                                    </a>
                                </li>
                            {else}
                                <li class="disabled"><span>Next &raquo;</span></li>
                            {/if}
                        </ul>
                    </nav>
                    <div class="text-center text-muted mb20">
                        <small>Page {$page|default:1} of {$total_pages|default:1}</small>
                    </div>
                {/if}

            </div>
        </div>
    </div>
</div>

<style>
    .table > tbody > tr > td {
        vertical-align: middle;
    }
    .label {
        font-size: 95%;
        padding: 4px 8px;
    }
</style>

{include file="sections/footer.tpl"}
