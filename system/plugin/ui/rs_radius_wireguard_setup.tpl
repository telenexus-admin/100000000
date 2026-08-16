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
          Create the router record here, then copy one generated RouterOS 7 script into the MikroTik terminal. The billing system will automatically establish WireGuard, register the RADIUS NAS, secure RouterOS API access, and continue to port configuration.
        </div>

        <form id="rsScriptGenerationForm" class="form-horizontal" method="post" action="{$_url}plugin/rs_radius_wireguard_setup">
          <div class="form-group">
            <label class="col-md-3 control-label">Router Name / Location</label>
            <div class="col-md-7">
              <input type="text" class="form-control" name="name" maxlength="30" required placeholder="e.g. Main Office MikroTik">
            </div>
          </div>
          <div class="form-group">
            <label class="col-md-3 control-label">Description</label>
            <div class="col-md-7">
              <textarea class="form-control" name="description" rows="3" placeholder="Optional coverage/location notes"></textarea>
            </div>
          </div>
          <div class="form-group">
            <div class="col-md-offset-3 col-md-7">
              <button id="rsGenerateScriptButton" type="submit" class="btn btn-primary btn-lg">
                <i class="fa fa-magic"></i> Create &amp; Generate Setup Script
              </button>
              <span id="rsGenerateScriptProgress" class="text-muted" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> Preparing WireGuard, RADIUS and your RouterOS script…
              </span>
              <a href="{$_url}routers/list" class="btn btn-default btn-lg">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var form = document.getElementById('rsScriptGenerationForm');
    if (!form) return;
    form.addEventListener('submit', function () {
      var button = document.getElementById('rsGenerateScriptButton');
      var progress = document.getElementById('rsGenerateScriptProgress');
      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';
      }
      if (progress) progress.style.display = 'inline';
    });
  }());
</script>

{include file="sections/footer.tpl"}
