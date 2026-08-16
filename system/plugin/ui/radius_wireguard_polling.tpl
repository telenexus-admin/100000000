{include file="sections/header.tpl"}

<div class="row">
  <div class="col-md-10 col-md-offset-1">
    <div class="panel panel-primary panel-hovered panel-stacked mb30">
      <div class="panel-heading">
        <i class="fa fa-lock"></i> {$router.name|escape:'html'} — WireGuard + RADIUS Automatic Setup
      </div>
      <div class="panel-body">
        {if $vpn_error}
          <div class="alert alert-danger">
            <strong>Automatic Setup Failed</strong><br>{$vpn_error|escape:'html'}
          </div>
          <a class="btn btn-warning" href="{$_url}plugin/radius_wireguard_prepare&router_id={$router.id}&fresh=1">
            <i class="fa fa-refresh"></i> Generate Fresh Setup
          </a>
        {else}
          <div class="alert alert-info">
            <strong>RouterOS 7 only.</strong> Keep the router's internet/WAN working, copy the complete script below, paste it once in WinBox Terminal, and leave this page open.
          </div>

          <div class="well well-sm">
            <strong>Private management IP:</strong> {$tunnel_ip|escape:'html'}<br>
            <strong>Management transport:</strong> WireGuard<br>
            <strong>Authentication:</strong> FreeRADIUS for Hotspot + PPPoE
          </div>

          <div style="position:relative;margin-bottom:15px;">
            <pre id="wgScript" style="max-height:460px;overflow:auto;white-space:pre-wrap;word-break:break-all;background:#111;color:#eee;padding:16px;border-radius:5px;padding-right:110px;">{$setup_script|escape:'html'}</pre>
            <button id="copyScriptBtn" type="button" class="btn btn-primary" onclick="copySetupScript()" style="position:absolute;top:10px;right:10px;">
              <i class="fa fa-copy"></i> Copy Script
            </button>
          </div>

          <div class="panel panel-default">
            <div class="panel-body">
              <div id="statusIdle">
                <i class="fa fa-info-circle"></i> Copy the script to begin automatic connection detection.
              </div>
              <div id="statusWaiting" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i>
                Waiting for authenticated RouterOS API over WireGuard...
                <span class="pull-right" id="elapsedTime">0s</span>
                <div class="progress" style="margin-top:10px;margin-bottom:5px;">
                  <div id="progressBar" class="progress-bar progress-bar-striped active" style="width:0%;"></div>
                </div>
                <small id="pollDebug" class="text-muted"></small>
              </div>
              <div id="statusSuccess" style="display:none;">
                <div class="alert alert-success" style="margin-bottom:0;">
                  <i class="fa fa-check-circle"></i> MikroTik Connected over WireGuard. Proceeding to port configuration...
                </div>
              </div>
              <div id="statusTimeout" style="display:none;">
                <div class="alert alert-warning">
                  Connection has not completed yet. Do not reset the router. You can retry detection or generate a fresh script if the previous activation token expired.
                </div>
                <button class="btn btn-warning" onclick="startPolling()"><i class="fa fa-refresh"></i> Retry Detection</button>
                <a class="btn btn-default" href="{$_url}plugin/radius_wireguard_prepare&router_id={$router.id}&fresh=1">Generate Fresh Script</a>
              </div>
            </div>
          </div>

          <div class="alert alert-default" style="border:1px solid #ddd;">
            <strong>Fresh router has no internet?</strong> Only then use this minimal WAN helper with the upstream cable on <code>ether1</code>:<br>
            <code>/interface ethernet set ether1 disabled=no</code><br>
            <code>/ip dhcp-client add interface=ether1 disabled=no add-default-route=yes use-peer-dns=yes</code>
          </div>
        {/if}
      </div>
    </div>
  </div>
</div>

{if !$vpn_error}
<script>
(function () {
  var routerId = {$router.id};
  var checkUrl = '{$_url}plugin/radius_wireguard_check_status';
  var proceedUrl = '{$_url}plugin/mikrotik_configurator_config_ui&router_id=' + routerId + '&wizard=1';
  var elapsed = 0;
  var maxSeconds = 300;
  var timer = null;

  window.copySetupScript = function () {
    var text = document.getElementById('wgScript').innerText;
    var button = document.getElementById('copyScriptBtn');
    var fallback = function () {
      var area = document.createElement('textarea');
      area.value = text;
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      document.body.removeChild(area);
      return Promise.resolve();
    };
    var copy = navigator.clipboard ? navigator.clipboard.writeText(text) : fallback();
    copy.then(function () {
      button.innerHTML = '<i class="fa fa-check"></i> Copied';
      setTimeout(function () { button.innerHTML = '<i class="fa fa-copy"></i> Copy Script'; }, 1800);
    });
    startPolling();
  };

  function pollOnce() {
    var sep = checkUrl.indexOf('?') >= 0 ? '&' : '?';
    fetch(checkUrl + sep + 'router_id=' + routerId + '&_=' + Date.now())
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function (data) {
        document.getElementById('pollDebug').textContent = data.target ? ('Checking ' + data.target) : '';
        if (data && data.online) {
          if (timer) clearInterval(timer);
          timer = null;
          document.getElementById('statusWaiting').style.display = 'none';
          document.getElementById('statusSuccess').style.display = 'block';
          setTimeout(function () { window.location = proceedUrl; }, 1000);
        }
      })
      .catch(function (error) {
        document.getElementById('pollDebug').textContent = 'Retrying... (' + error.message + ')';
      });
  }

  window.startPolling = function () {
    if (timer) return;
    document.getElementById('statusIdle').style.display = 'none';
    document.getElementById('statusTimeout').style.display = 'none';
    document.getElementById('statusWaiting').style.display = 'block';
    elapsed = 0;
    pollOnce();
    timer = setInterval(function () {
      elapsed += 5;
      document.getElementById('elapsedTime').textContent = elapsed + 's';
      document.getElementById('progressBar').style.width = Math.min(100, elapsed / maxSeconds * 100) + '%';
      if (elapsed >= maxSeconds) {
        clearInterval(timer);
        timer = null;
        document.getElementById('statusWaiting').style.display = 'none';
        document.getElementById('statusTimeout').style.display = 'block';
        return;
      }
      pollOnce();
    }, 5000);
  };
})();
</script>
{/if}

{include file="sections/footer.tpl"}
