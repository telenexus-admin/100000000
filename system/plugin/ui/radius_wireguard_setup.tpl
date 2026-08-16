{include file="sections/header.tpl"}

<div class="row">
  <div class="col-md-10 col-md-offset-1">
    <div class="panel panel-primary panel-hovered panel-stacked mb30">
      <div class="panel-heading">
        <i class="fa fa-shield"></i> Automatic WireGuard + FreeRADIUS Router Setup
      </div>
      <div class="panel-body">
        <div class="alert alert-info">
          <strong>One-script onboarding.</strong>
          Create the router here, copy one RouterOS 7 script, and the billing system will automatically establish WireGuard, create the private RouterOS API account, register the RADIUS NAS, configure accounting/CoA, verify the tunnel, and then continue to port selection.
        </div>

        {if !$wireguard_ready}
          <div class="alert alert-danger">
            <strong>Server control plane is not ready.</strong><br>
            {$wireguard_error|escape:'html'}
            <hr style="margin:10px 0;">
            Run <code>deploy/configure-radius-wireguard.sh</code> on the billing/RADIUS VPS before onboarding a router.
          </div>
        {else}
          <div class="alert alert-success">
            <i class="fa fa-check-circle"></i>
            WireGuard control plane ready — private server {$wireguard.server_ip|escape:'html'} on {$wireguard.interface|escape:'html'}.
          </div>

          <form class="form-horizontal" method="post" action="{$_url}plugin/radius_wireguard_create">
            <div class="form-group">
              <label class="col-md-3 control-label">Router Name / Location</label>
              <div class="col-md-7">
                <input type="text" class="form-control" name="name" maxlength="30" required>
                <p class="help-block">Use the same descriptive router name you normally use in this billing system.</p>
              </div>
            </div>
            <div class="form-group">
              <label class="col-md-3 control-label">Description</label>
              <div class="col-md-7">
                <textarea class="form-control" name="description" rows="3"></textarea>
              </div>
            </div>
            <div class="form-group">
              <div class="col-md-offset-3 col-md-7">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fa fa-magic"></i> Create &amp; Generate Setup Script
                </button>
                <a href="{$_url}routers/add" class="btn btn-default btn-lg">Manual Setup</a>
              </div>
            </div>
          </form>
        {/if}
      </div>
    </div>
  </div>
</div>

{include file="sections/footer.tpl"}
