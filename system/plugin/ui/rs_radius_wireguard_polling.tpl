{include file="sections/header.tpl"}

<div class="row">
  <div class="col-md-10 col-md-offset-1">
    <div class="panel panel-primary panel-hovered panel-stacked mb30">
      <div class="panel-heading">
        <i class="fa fa-shield"></i> {$router.name} — Automatic WireGuard + RADIUS Setup
      </div>
      <div class="panel-body">
        {if $setup_error}
          <div class="alert alert-danger">
            <strong>Setup preparation failed.</strong><br>
            {$setup_error|escape:'html'}
          </div>
          <a href="{$_url}plugin/rs_radius_wireguard_setup&router_id={$router.id}" class="btn btn-warning">
            <i class="fa fa-refresh"></i> Retry Preparation
          </a>
          <a href="{$_url}routers/list" class="btn btn-default">Back to Routers</a>
        {else}
          <div class="row" style="margin-bottom:15px;">
            <div class="col-sm-4"><strong>Transport:</strong> WireGuard</div>
            <div class="col-sm-4"><strong>Management IP:</strong> <code>{$tunnel_ip}</code></div>
            <div class="col-sm-4"><strong>RouterOS:</strong> 7.x required</div>
          </div>

          <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            Connect the MikroTik to the internet first. Copy the complete script below, paste it once into WinBox Terminal, and leave this page open. The MikroTik private WireGuard key never leaves the router.
          </div>

          <div class="alert alert-warning" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div>
              <strong><i class="fa fa-plug"></i> Factory-reset router has no internet?</strong><br>
              <small>Use the optional ether1 WAN helper only when ether1 should be the upstream DHCP/internet port.</small>
            </div>
            <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#rsInternetHelperModal">Show Internet Helper</button>
          </div>

          <div class="panel panel-default">
            <div class="panel-heading"><strong><i class="fa fa-terminal"></i> Run Once in MikroTik Terminal</strong></div>
            <div class="panel-body" style="position:relative;">
              <button id="copySetupScript" type="button" class="btn btn-primary btn-sm" style="position:absolute;right:25px;top:15px;z-index:2;">
                <i class="fa fa-copy"></i> Copy Script
              </button>
              <pre id="rsSetupScript" style="max-height:460px;overflow:auto;white-space:pre-wrap;word-break:break-word;padding-right:110px;">{$setup_script|escape:'html'}</pre>
            </div>
          </div>

          <div class="panel panel-default">
            <div class="panel-heading"><strong>Connection Detection</strong></div>
            <div class="panel-body">
              <div id="rsStatusWaiting">
                <p><i class="fa fa-spinner fa-spin"></i> <span id="rsStatusText">Waiting for authenticated RouterOS API over WireGuard...</span></p>
                <div class="progress" style="height:8px;">
                  <div id="rsProgress" class="progress-bar progress-bar-striped active" style="width:1%;"></div>
                </div>
                <small class="text-muted" id="rsStatusDebug">Checking {$tunnel_ip}:8728</small>
              </div>

              <div id="rsStatusSuccess" style="display:none;">
                <div class="alert alert-success" style="margin-bottom:0;">
                  <strong><i class="fa fa-check-circle"></i> MikroTik Connected over WireGuard.</strong>
                  Proceeding to port configuration...
                </div>
              </div>

              <div id="rsStatusTimeout" style="display:none;">
                <div class="alert alert-warning">
                  Connection detection timed out. The generated setup remains available; retry detection after checking the MikroTik terminal output.
                </div>
                <button type="button" class="btn btn-warning" id="rsRetryPoll"><i class="fa fa-refresh"></i> Retry Detection</button>
              </div>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="rsInternetHelperModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-plug"></i> Internet First Helper — ether1 WAN only</h4>
      </div>
      <div class="modal-body">
        <p>Run this only if <strong>ether1 is your upstream internet port</strong> and the router itself cannot ping the internet after reset.</p>
        <pre style="white-space:pre-wrap;">/interface ethernet set ether1 disabled=no
:foreach p in=[/interface bridge port find where interface=ether1] do={ /interface bridge port remove $p }
/ip dhcp-client remove [find where interface=ether1]
/ip dhcp-client add interface=ether1 disabled=no add-default-route=yes use-peer-dns=yes use-peer-ntp=yes
:delay 8s
/interface ethernet monitor ether1 once
/ip dhcp-client print detail where interface=ether1
/ping 8.8.8.8 count=4</pre>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

{if !$setup_error}
<script>
(function () {
  var routerId = {$router.id};
  var statusUrl = '{$_url}plugin/rs_radius_wireguard_status';
  var proceedUrl = '{$_url}plugin/mikrotik_configurator_config_ui&router_id=' + routerId + '&auto_radius=1';
  var startedAt = Date.now();
  var stopped = false;
  var maxMs = 300000;

  function setProgress() {
    var elapsed = Math.max(0, Date.now() - startedAt);
    var pct = Math.min(100, Math.max(1, (elapsed / maxMs) * 100));
    document.getElementById('rsProgress').style.width = pct + '%';
  }

  function success() {
    stopped = true;
    document.getElementById('rsStatusWaiting').style.display = 'none';
    document.getElementById('rsStatusTimeout').style.display = 'none';
    document.getElementById('rsStatusSuccess').style.display = 'block';
    setTimeout(function () { window.location.href = proceedUrl; }, 900);
  }

  function poll() {
    if (stopped) return;
    if ((Date.now() - startedAt) >= maxMs) {
      stopped = true;
      document.getElementById('rsStatusWaiting').style.display = 'none';
      document.getElementById('rsStatusTimeout').style.display = 'block';
      return;
    }

    setProgress();
    var url = statusUrl + '&router_id=' + encodeURIComponent(routerId) + '&_=' + Date.now();
    fetch(url)
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function (data) {
        if (data && data.online) {
          success();
          return;
        }
        var debug = document.getElementById('rsStatusDebug');
        if (data && data.target) debug.textContent = 'Checking ' + data.target;
        setTimeout(poll, 5000);
      })
      .catch(function (error) {
        document.getElementById('rsStatusDebug').textContent = 'Retrying connection detection (' + error.message + ')';
        setTimeout(poll, 5000);
      });
  }

  document.getElementById('copySetupScript').addEventListener('click', function () {
    var button = this;
    var text = document.getElementById('rsSetupScript').innerText;
    var done = function () {
      var old = button.innerHTML;
      button.innerHTML = '<i class="fa fa-check"></i> Copied';
      setTimeout(function () { button.innerHTML = old; }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var area = document.createElement('textarea');
      area.value = text;
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      document.body.removeChild(area);
      done();
    }
  });

  document.getElementById('rsRetryPoll').addEventListener('click', function () {
    startedAt = Date.now();
    stopped = false;
    document.getElementById('rsStatusTimeout').style.display = 'none';
    document.getElementById('rsStatusWaiting').style.display = 'block';
    poll();
  });

  poll();
})();
</script>
{/if}

{include file="sections/footer.tpl"}
