{include file="sections/header.tpl"}

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">RADIUS Accounts</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="accountsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Plan Name</th>
                                <th>Recharged On</th>
                                <th>Expires On</th>
                                <th>Status</th>
                                <th>Router</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $radius_accounts}
                                {foreach $radius_accounts as $account}
                                    <tr>
                                        <td><strong>{$account['username']|escape}</strong></td>
                                        <td>{$account['namebp']|escape}</td>
                                        <td>{$account['recharged_on']|date_format:"%Y-%m-%d %H:%M"}</td>
                                        <td>{$account['expiration']|date_format:"%Y-%m-%d %H:%M"}</td>
                                        <td>
                                            {if strtotime($account['expiration']) > time()}
                                                <span class="badge badge-success">Active</span>
                                            {else}
                                                <span class="badge badge-danger">Expired</span>
                                            {/if}
                                        </td>
                                        <td>{$account['routers']|escape}</td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{$_url}plugin/radius_data_usage/{$account['username']|escape}" class="btn btn-sm btn-info" title="View Data Usage">
                                                    <i class="fa fa-area-chart"></i>
                                                </a>
                                                <a href="{$_url}customers/viewu/{$account['username']|escape}" class="btn btn-sm btn-success" title="View Customer">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="7" class="text-center py-4">No RADIUS accounts found</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="{$_url}plugin/radius_data_usage" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Data Usage
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        try {
            $('#accountsTable').DataTable({
                responsive: true,
                order: [[2, 'desc']],
                pageLength: 25
            });
        } catch(e) {
            console.log('DataTable initialization failed:', e);
        }
    }
});
</script>

{include file="sections/footer.tpl"}