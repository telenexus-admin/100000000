{include file="sections/header.tpl"}

<div class="row">
  <div class="col-md-12">
    <div class="panel panel-primary panel-hovered panel-stacked mb30">
      <div class="panel-heading">
        <i class="fa fa-database"></i> Manage Pools &mdash; {$router.name}
        <a href="{$_url}plugin/mikrotik_configurator" class="btn btn-default btn-xs pull-right">
          <i class="fa fa-arrow-left"></i> Back
        </a>
      </div>
      <div class="panel-body">

        <div class="alert alert-info">
          <i class="fa fa-info-circle"></i>
          <strong>Pool Regeneration Rules:</strong>
          <ul class="mb0" style="margin-top:6px;">
            <li><strong>Active / Hotspot pools</strong> &mdash; regenerate resets the range to the full /16 (gateway+1 &rarr; broadcast-1). The gateway IP and local DB entry are preserved.</li>
            <li><strong>Expired PPPoE pool</strong> &mdash; automatically placed in the <em>next /16 block</em> after the active pool, so there is never an overlap. Updates both MikroTik and the local DB.</li>
          </ul>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="poolsTable">
            <thead>
              <tr>
                <th>#</th>
                <th><i class="fa fa-tag"></i> Pool Name</th>
                <th><i class="fa fa-network-wired"></i> Current Range</th>
                <th><i class="fa fa-map-marker"></i> Gateway (local-address)</th>
                <th><i class="fa fa-tag"></i> Type</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {if $pools && $pools|@count > 0}
                {assign var="idx" value=1}
                {foreach $pools as $pool}
                  <tr id="pool-row-{$pool.id}">
                    <td>{$idx}</td>
                    <td><strong>{$pool.pool_name}</strong></td>
                    <td>
                      <code id="range-{$pool.id}">{$pool.range_ip}</code>
                    </td>
                    <td>
                      {if $pool.local_ip}
                        <span class="label label-info">{$pool.local_ip}</span>
                      {else}
                        <span class="text-muted">—</span>
                      {/if}
                    </td>
                    <td>
                      {if stristr($pool.pool_name, 'pppoe-expired') || stristr($pool.pool_name, 'expired')}
                        <span class="label label-warning"><i class="fa fa-clock-o"></i> PPPoE Expired Pool</span>
                      {elseif stristr($pool.pool_name, 'pppoe')}
                        <span class="label label-primary"><i class="fa fa-ethernet"></i> PPPoE Active Pool</span>
                      {elseif stristr($pool.pool_name, 'hotspot')}
                        <span class="label label-success"><i class="fa fa-wifi"></i> Hotspot Pool</span>
                      {else}
                        <span class="label label-default"><i class="fa fa-tag"></i> Other Pool</span>
                      {/if}
                    </td>
                    <td class="text-center">
                      <button type="button"
                        class="btn btn-warning btn-sm btn-regenerate"
                        data-pool-id="{$pool.id}"
                        data-pool-name="{$pool.pool_name}"
                        data-router-id="{$router.id}">
                        <i class="fa fa-refresh"></i> Regenerate
                      </button>
                    </td>
                  </tr>
                  {assign var="idx" value=$idx+1}
                {/foreach}
              {else}
                <tr>
                  <td colspan="6" class="text-center text-muted">
                    <p class="mt20 mb20">
                      <i class="fa fa-inbox fa-3x"></i><br><br>
                      No pools found for this router. Configure the router first to create pools.
                    </p>
                  </td>
                </tr>
              {/if}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
{literal}
  $(document).on('click', '.btn-regenerate', function () {
    var btn       = $(this);
    var poolId    = btn.data('pool-id');
    var poolName  = btn.data('pool-name');
    var routerId  = btn.data('router-id');

    if (!confirm('Regenerate pool "' + poolName + '"?\n\nFor expired pools the range will be recalculated after the active pool.\nFor other pools the full /16 range will be restored.\n\nThis will update MikroTik and the local database.')) {
      return;
    }

    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Regenerating...');

    $.ajax({
      url: '{/literal}{$_url}{literal}plugin/mikrotik_configurator_do_regenerate_pool',
      method: 'POST',
      data: { router_id: routerId, pool_id: poolId },
      dataType: 'json',
      success: function (data) {
        if (data.status === 'success') {
          $('#range-' + poolId).text(data.new_range);
          btn.prop('disabled', false).html('<i class="fa fa-check"></i> Done');
          setTimeout(function () {
            btn.html('<i class="fa fa-refresh"></i> Regenerate');
          }, 2500);
        } else {
          alert('Error: ' + (data.message || 'Unknown error'));
          btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Regenerate');
        }
      },
      error: function () {
        alert('Request failed. Check server logs.');
        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Regenerate');
      }
    });
  });
{/literal}
</script>

{include file="sections/footer.tpl"}
