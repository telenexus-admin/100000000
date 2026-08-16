{include file="sections/header.tpl"}

<style>
  .panel-title {
    font-size: 16px;
    font-weight: 600;
  }

  #scriptContent {
    background-color: #f8f9fa;
    border: 2px solid #dee2e6;
    color: #212529;
  }

  .btn-block {
    margin-bottom: 10px;
  }

  .mt20 {
    margin-top: 20px;
  }

  .mb0 {
    margin-bottom: 0;
  }

  code {
    background-color: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    color: #c7254e;
  }

  /* Disabled Select Styling */
  select.form-control:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
    opacity: 0.7;
  }

  /* Port Service Assignment Select */
  .port-service-select {
    font-size: 12px;
    font-weight: 600;
    border: 1px solid #ccc;
    transition: border-color 0.2s ease;
  }

  .port-service-select:focus {
    border-color: #999;
    box-shadow: none;
    outline: none;
  }

  /* Responsive design */
  @media (max-width: 768px) {
    .port-service-select {
      font-size: 11px;
    }
  }
</style>
<div class="row">
  <div class="col-md-12">
    <div class="panel panel-default panel-hovered panel-stacked mb30">
      <div class="panel-heading">
        <div class="btn-group pull-right">
          <a class="btn btn-default btn-xs" href="{$_url}plugin/mikrotik_configurator">
            <i class="fa fa-arrow-left"></i> Back to Routers
          </a>
        </div>
        <i class="fa fa-cog"></i> Configure MikroTik Router
      </div>
      <div class="panel-body">

        <form id="mikrotikConfiguratorForm" data-router-api-ready="0" method="post" action="{$_url}plugin/mikrotik_configurator_config_process">
          <!-- Router Details -->
          <input type="hidden" name="router_id" value="{$router.id}">
          <input type="hidden" id="routerName" name="router_name" value="{$router.name}">
          <input type="hidden" id="routerIP" name="router_ip" value="{$router.ip_address}">
          <p class="text-muted" style="margin-bottom:15px;">
            <i class="fa fa-server"></i> <strong>{$router.name}</strong>
            &nbsp;&mdash;&nbsp;
            <span class="label label-info">{$router.ip_address}</span>
            &nbsp;
            <span id="routerStatusBadge" class="label label-default">
              <i class="fa fa-spinner fa-spin"></i> Checking...
            </span>
            {if $auto_radius}
              &nbsp;<span class="label label-success"><i class="fa fa-shield"></i> WireGuard + RADIUS managed</span>
            {/if}
          </p>
          <div id="routerApiNotice" class="alert alert-warning" style="display:none; margin-top:10px;">
            <strong><i class="fa fa-exclamation-triangle"></i> Configuration has not been applied.</strong>
            <span id="routerApiNoticeText">Waiting for a RouterOS API connection.</span>
            <a id="routerApiRecoveryLink" class="btn btn-warning btn-xs pull-right" href="#"><i class="fa fa-wrench"></i> Open Automatic Setup</a>
          </div>
          {if $configured_services}
          <div class="alert alert-success" style="margin-top:10px;">
            <strong><i class="fa fa-check-circle"></i> Configuration completed.</strong>
            {if in_array('hotspot', $configured_services)} Hotspot settings and the selected port assignments were applied; the configured Hotspot HTML directory received its login file. {/if}
            {if in_array('pppoe', $configured_services)} PPPoE settings and the selected port assignments were applied. {/if}
            Verify the result under <strong>IP → Hotspot</strong> and <strong>Files</strong> in MikroTik.
          </div>
          {/if}

          <!-- Service Configuration -->
          <div class="panel panel-default">
            <div class="panel-heading">
              <h4 class="panel-title"><i class="fa fa-list-check"></i> Choose Service to Configure</h4>
            </div>
            <div class="panel-body">
              <div class="checkbox">
                <label>
                  <input type="checkbox" id="pppoeCheck" name="serviceType[]" value="pppoe"
                    onclick="toggleServiceOptions()">
                  <strong>PPPoE</strong> - Point-to-Point Protocol over Ethernet
                </label>
              </div>
              <div class="checkbox">
                <label>
                  <input type="checkbox" id="hotspotCheck" name="serviceType[]" value="hotspot"
                    onclick="toggleServiceOptions()">
                  <strong>Hotspot</strong> - WiFi Hotspot Service
                </label>
              </div>
            </div>
          </div>

          <!-- MikroTik Ports -->
          <div class="panel panel-default">
            <div class="panel-heading">
              <h4 class="panel-title"><i class="fa fa-ethernet"></i> MikroTik Ports Selection</h4>
            </div>
            <div class="panel-body">
              <div id="mikrotikPorts" style="min-height:40px;">
                <p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading ports...</p>
              </div>
            </div>
          </div>

          <!-- Bridge Configuration -->
          <div class="panel panel-default" id="bridgeConfigPanel" style="display:none;">
            <div class="panel-heading">
              <h4 class="panel-title"><i class="fa fa-sitemap"></i> Bridge Configuration</h4>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label>Run PPPoE and Hotspot on the same bridge?</label>
                <select class="form-control" id="sameBridge" name="sameBridge" disabled>
                  <option value="yes">Yes - Use same bridge</option>
                  <option value="no">No - Use separate bridges</option>
                </select>
                <small class="help-block">
                  <i class="fa fa-info-circle"></i> Only available when both PPPoE and Hotspot are selected.
                </small>
              </div>
              <div id="bridgeFields">
                <!-- Bridge name field(s) injected here by JS -->
              </div>

              <!-- Subnet fields: always two, always separate, pre-filled by PHP -->
              <div class="row">
                <div class="col-md-6" id="subnetHotspotRow" style="display:none;">
                  <div class="form-group">
                    <label><i class="fa fa-wifi text-success"></i> Hotspot Subnet <small class="text-muted">(/16)</small></label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="subnet_hotspot" name="subnet_hotspot"
                        value="{$hotspot_subnet}" placeholder="e.g. 10.20.0.0/16">
                      <span class="input-group-btn">
                        <button type="button" class="btn btn-info" onclick="regenSubnet('subnet_hotspot')">
                          <i class="fa fa-refresh"></i>
                        </button>
                      </span>
                    </div>
                    <small class="help-block">Used for hotspot DHCP &amp; bridge IP</small>
                  </div>
                </div>
                <div class="col-md-6" id="subnetPppoeRow" style="display:none;">
                  <div class="form-group">
                    <label><i class="fa fa-ethernet text-warning"></i> PPPoE Subnet <small class="text-muted">(/16)</small></label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="subnet_pppoe" name="subnet_pppoe"
                        value="{$pppoe_subnet}" placeholder="e.g. 10.30.0.0/16">
                      <span class="input-group-btn">
                        <button type="button" class="btn btn-info" onclick="regenSubnet('subnet_pppoe')">
                          <i class="fa fa-refresh"></i>
                        </button>
                      </span>
                    </div>
                    <small class="help-block">Used for PPPoE active pool &amp; gateway IP</small>
                  </div>
                </div>
              </div>
              <p class="text-muted" style="font-size:12px;">
                <i class="fa fa-info-circle"></i>
                Hotspot and PPPoE always get separate subnets — even on the same bridge MikroTik supports multiple IPs per interface.
                The expired PPPoE pool will automatically use the next /16 after PPPoE subnet.
              </p>
            </div>
          </div>


          <!-- Hotspot Options -->
          <div id="hotspotOptions" style="display:none;" class="config-dependent-panel">
            <div class="panel panel-info">
              <div class="panel-heading">
                <h4 class="panel-title"><i class="fa fa-wifi"></i> Hotspot Configuration Options</h4>
              </div>
              <div class="panel-body">
                <div class="form-group">
                  <label><i class="fa fa-network-wired"></i> Hotspot IP Range</label>
                  <input type="text" class="form-control" id="hotspot_ip_range" name="hotspot_ip_range"
                    placeholder="Auto-generated from subnet" readonly>
                  <small class="help-block"><i class="fa fa-info-circle text-info"></i> Automatically derived from the bridge subnet — no manual input needed.</small>
                </div>

                <div class="form-group">
                  <label>Enable Anti Hotspot Sharing?</label>
                  <select class="form-control" name="antiHotspotSharing">
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                  </select>
                  <small class="help-block">Prevent multiple users from sharing same hotspot login</small>
                </div>

                <div class="form-group">
                  <label for="hotspot_dns_name">
                    <i class="fa fa-globe"></i> Hotspot DNS Name
                  </label>
                  <input type="text" class="form-control" id="hotspot_dns_name" name="hotspot_dns_name"
                    value="{$companyName|lower|regex_replace:'/[^a-z0-9]/':''}.net"
                    placeholder="e.g., hotspot.yourdomain.com">
                  <small class="help-block">
                    <i class="fa fa-info-circle text-info"></i> <strong>Domain name users see during hotspot login.</strong>
                    <br>• Leave empty to use current hostname automatically
                    <br>• Examples: <code>hotspot.yourdomain.com</code>, <code>login.mycompany.net</code>, <code>wifi.portal</code>
                    <br>• Walled Garden will be auto-configured to match this domain
                  </small>
                </div>

                <div class="form-group">
                  <label><i class="fa fa-folder-open"></i> Hotspot Server Directory</label>
                  <input type="text" class="form-control" name="hotspot_html_directory" value="hotspot" maxlength="64" pattern="[A-Za-z0-9._-]+" required>
                  <small class="help-block">RouterOS directory containing <code>login.html</code> and other hotspot files.</small>
                </div>
                <div class="form-group">
                  <a class="btn btn-default" href="{$_url}plugin/hotspot_settings&router_id={$router.id}"><i class="fa fa-files-o"></i> Manage Hotspot Files</a>
                </div>

                <div class="form-group">
                  <label>Choose Authentication Type</label>
                  {if $auto_radius}
                    <input type="hidden" name="hotspot_auth_type" value="radius">
                    <p class="form-control-static"><span class="label label-success">RADIUS Authentication</span> <small class="text-muted">Locked by WireGuard onboarding</small></p>
                  {else}
                    <select class="form-control" name="hotspot_auth_type">
                      <option value="api">API Authentication</option>
                      <option value="radius">RADIUS Authentication</option>
                    </select>
                  {/if}
                </div>
              </div>
            </div>
          </div>

          <!-- PPPoE Options -->
          <div id="pppoeOptions" style="display:none;" class="config-dependent-panel">
            <div class="panel panel-warning">
              <div class="panel-heading">
                <h4 class="panel-title"><i class="fa fa-ethernet"></i> PPPoE Configuration Options</h4>
              </div>
              <div class="panel-body">
                <div class="form-group">
                  <label>PPPoE Authentication Type</label>
                  {if $auto_radius}
                    <input type="hidden" name="pppoe_auth_type" value="radius">
                    <p class="form-control-static"><span class="label label-success">RADIUS Authentication</span> <small class="text-muted">Locked by WireGuard onboarding</small></p>
                  {else}
                    <select class="form-control" name="pppoe_auth_type">
                      <option value="api">API Authentication</option>
                      <option value="radius">RADIUS Authentication</option>
                    </select>
                  {/if}
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="panel panel-success" id="actionsPanel" style="display:none;">
            <div class="panel-body">
              <button type="submit" class="btn btn-success btn-lg btn-block">
                <i class="fa fa-cog"></i> Configure Router
              </button>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<script src="/ui/ui/scripts/jquery.min.js"></script>
<script>
  {literal}
    // Check router status first (separate API)
    checkRouterStatus();

    // Derive and set hotspot IP range from whichever subnet field is active.
    // Derive hotspot pool range from /16 subnet.
    // A /16 covers x.y.0.0 - x.y.255.255. Gateway is x.y.0.1, DHCP starts at x.y.0.10.
    function updateHotspotRangeFromSubnet() {
      var subnetField = document.getElementById('subnet_hotspot') || document.getElementById('subnet_bridge');
      if (!subnetField || !subnetField.value) return;
      var parts = subnetField.value.split('/')[0].split('.');
      // parts[0].parts[1] is the /16 prefix; pool: .0.10 to .255.254
      var range = parts[0] + '.' + parts[1] + '.0.10-' + parts[0] + '.' + parts[1] + '.255.254';
      var rangeField = document.getElementById('hotspot_ip_range');
      if (rangeField) rangeField.value = range;
    }

    // Function to hide/show configuration panels
    function toggleConfigPanels(show) {
      var panels = ['bridgeConfigPanel', 'actionsPanel'];
      panels.forEach(function(panelId) {
        var panel = document.getElementById(panelId);
        if (panel) {
          panel.style.display = show ? 'block' : 'none';
        }
      });

      // Also hide service option panels
      var serviceOptions = document.querySelectorAll('.config-dependent-panel');
      serviceOptions.forEach(function(element) {
        element.style.display = 'none';
      });
    }

    function setRouterApiReady(ready, message) {
      var form = $('#mikrotikConfiguratorForm');
      var notice = $('#routerApiNotice');
      form.attr('data-router-api-ready', ready ? '1' : '0');
      if (ready) {
        notice.hide();
        return;
      }
      $('#routerApiNoticeText').text(message || 'RouterOS API connection is required before configuration can be applied.');
      var routerId = $('input[name="router_id"]').val();
      $('#routerApiRecoveryLink').attr('href', window.location.origin + '/?_route=plugin/rs_radius_wireguard_setup&router_id=' + encodeURIComponent(routerId));
      notice.show();
    }
    // Function to update router status display
    function updateRouterStatus(state, message, routerInfo) {
      var badge = $('#routerStatusBadge');
      if (state === true) {
        badge.removeClass('label-default label-danger label-warning').addClass('label-success');
        badge.html('<i class="fa fa-check-circle"></i> Online');
      } else if (state === 'wireguard') {
        badge.removeClass('label-default label-danger label-success').addClass('label-warning');
        badge.html('<i class="fa fa-shield"></i> WireGuard online — API unavailable');
        badge.attr('title', message || 'RouterOS API is unavailable');
      } else {
        badge.removeClass('label-default label-success label-warning').addClass('label-danger');
        badge.html('<i class="fa fa-times-circle"></i> ' + (message || 'Offline'));
      }
    }

    // Separate function to check router connection status
    // Separate function to check router connection status
    function checkRouterStatus() {

      var routerId = $('input[name="router_id"]').val();
      var badge = $('#routerStatusBadge');

      badge.removeClass('label-success label-danger').addClass('label-default');
      badge.html('<i class="fa fa-spinner fa-spin"></i> Checking...');

      // IMPORTANT: include PhpNuxBill path
      const url = window.location.origin + '/?_route=plugin/mikrotik_configurator_check_status';

      $.ajax({
        url: url,
        method: 'GET',
        data: { router_id: routerId },
        dataType: 'json',
        timeout: 10000,
        success: function(data) {
          if (data.status === "success" && data.online) {
            updateRouterStatus(true, 'Connected', data.info || '');
            setRouterApiReady(true);
          } else if (data.status === 'warning' && data.transport_online) {
            updateRouterStatus('wireguard', data.message || 'WireGuard online — RouterOS API unavailable');
            setRouterApiReady(false, 'WireGuard is online, but RouterOS API port 8728 is unavailable. Re-run the generated onboarding script before configuring Hotspot or uploading files.');
          } else {
            updateRouterStatus(false, data.message || 'Connection Failed');
            setRouterApiReady(false, data.message || 'RouterOS API connection failed. No configuration or hotspot files were applied.');
          }
        },
        error: function() {
          updateRouterStatus(false, 'Unreachable');
          setRouterApiReady(false, 'The status check could not reach the RouterOS API. No configuration or hotspot files were applied.');
        }
      });
    }

    $('#mikrotikConfiguratorForm').on('submit', function(event) {
      if ($(this).attr('data-router-api-ready') !== '1') {
        event.preventDefault();
        $('#routerApiNotice').show();
        $('html, body').animate({ scrollTop: $('#routerApiNotice').offset().top - 20 }, 200);
      }
    });

    function loadMikrotikPorts() {

      var routerId = $('input[name="router_id"]').val();

      var portsDiv = $('#mikrotikPorts');
      portsDiv.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Scanning router ports...</p>');

      const url = window.location.origin + '/?_route=plugin/mikrotik_configurator_get_mikrotik_port';

      $.ajax({
        url: url,
        method: 'GET',
        data: { router_id: routerId },
        dataType: 'json',
        timeout: 12000,
        success: function(data) {

          if (data.status === "success" && Array.isArray(data.data) && data.data.length > 0) {

            let bridges = data.data.filter(function(port) {
              return !port.wan && !port.management &&
                port.type !== 'ovpn-out' &&
                port.type !== 'wg' &&
                port.type !== 'wireguard' &&
                port.type !== 'bridge' &&
                port.name !== 'ether1' &&
                port.name !== null;
            });

            if (bridges.length === 0) {
              showPortsOffline('Router is online but returned no usable ports (ether1 and bridges are excluded).');
              return;
            }

            toggleConfigPanels(true);
            window.mikrotikPortsData = bridges;
            renderPortsTable();

          } else {
            showPortsOffline(data.message || 'Router API returned an error.');
          }
        },
        error: function(xhr, status) {
          var msg = status === 'timeout'
            ? 'Connection timed out. Router did not respond within 12 seconds.'
            : 'Could not reach the router API endpoint.';
          showPortsOffline(msg);
        }
      });
    }

    function showPortsOffline(reason) {
      toggleConfigPanels(false);
      $('#mikrotikPorts').html(
        '<div class="alert alert-danger">' +
          '<strong><i class="fa fa-times-circle"></i> Port scan failed:</strong> ' + reason +
          '<br><br>' +
          '<button type="button" class="btn btn-sm btn-warning" onclick="loadMikrotikPorts()">' +
            '<i class="fa fa-refresh"></i> Retry Scan' +
          '</button>' +
          '&nbsp;&nbsp;' +
          '<button type="button" class="btn btn-sm btn-default" onclick="showManualPortEntry()">' +
            '<i class="fa fa-keyboard-o"></i> Enter ports manually' +
          '</button>' +
        '</div>'
      );
    }

    function showManualPortEntry() {
      toggleConfigPanels(true);
      $('#mikrotikPorts').html(
        '<div class="alert alert-warning" style="margin-bottom:10px;">' +
          '<i class="fa fa-exclamation-triangle"></i> <strong>Manual mode &mdash; router offline.</strong> ' +
          'Enter the exact port names as they appear on your MikroTik (e.g. <code>ether2</code>, <code>ether3</code>).' +
        '</div>' +
        '<div id="manualPortList"></div>' +
        '<button type="button" class="btn btn-sm btn-success" onclick="addManualPortRow()">' +
          '<i class="fa fa-plus"></i> Add Port' +
        '</button>' +
        '&nbsp;' +
        '<button type="button" class="btn btn-sm btn-primary" onclick="applyManualPorts()">' +
          '<i class="fa fa-check"></i> Apply' +
        '</button>'
      );
      addManualPortRow();
    }

    function addManualPortRow() {
      var idx = $('#manualPortList .manual-port-row').length;
      $('#manualPortList').append(
        '<div class="manual-port-row input-group" style="margin-bottom:5px;max-width:300px;">' +
          '<input type="text" class="form-control manual-port-name" placeholder="e.g. ether' + (idx + 2) + '">' +
          '<span class="input-group-btn">' +
            '<button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest(\'.manual-port-row\').remove()">' +
              '<i class="fa fa-trash"></i>' +
            '</button>' +
          '</span>' +
        '</div>'
      );
    }

    function applyManualPorts() {
      var ports = [];
      $('.manual-port-name').each(function() {
        var v = $(this).val().trim();
        if (v) ports.push({ name: v, type: 'ether', running: false, disabled: false });
      });
      if (ports.length === 0) {
        alert('Add at least one port name.');
        return;
      }
      window.mikrotikPortsData = ports;
      renderPortsTable();
      // Show a small note above the table
      $('#mikrotikPorts').prepend(
        '<div class="alert alert-warning" style="margin-bottom:8px;">' +
          '<i class="fa fa-exclamation-triangle"></i> Manual port entry (router offline). Verify these match your device.' +
        '</div>'
      );
    }

    // Function to render ports table based on current configuration
    function renderPortsTable() {
      if (!window.mikrotikPortsData) return;

      var bridges = window.mikrotikPortsData;
      var portsDiv = $('#mikrotikPorts');
      var pppoeChecked = $('#pppoeCheck').is(':checked');
      var hotspotChecked = $('#hotspotCheck').is(':checked');
      var sameBridge = $('#sameBridge').val();
      var useSeparateBridges = (sameBridge === 'no' && pppoeChecked && hotspotChecked);

      let html = '';

      // Add info alert for separate bridges mode
      if (useSeparateBridges) {
        html += '<div style="margin-bottom:15px;padding:10px 14px;border-left:3px solid #aaa;background:#f9f9f9;border-radius:3px;font-size:13px;color:#444;">';
        html += '<i class="fa fa-info-circle"></i> <strong>Separate Bridges Mode:</strong> ';
        html += 'Assign each selected port to serve Hotspot, PPPoE, or Both services using the dropdown in the "Assign to Service" column.';
        html += '</div>';
      }

      html += '<div class="table-responsive">';
      html += '<table class="table table-striped table-bordered table-hover" id="portsTable">';
      html += '<thead style="background:#f5f5f5;color:#333;border-bottom:2px solid #ddd;"><tr>';
      html += '<th width="8%"><input type="checkbox" id="selectAll"> Select</th>';
      html += '<th width="15%">Port Name</th>';
      html += '<th width="20%">MAC Address</th>';
      html += '<th width="12%">Status</th>';

      if (useSeparateBridges) {
        html += '<th width="20%">Assign to Service</th>';
        html += '<th width="25%">Comment</th>';
      } else {
        html += '<th width="45%">Comment</th>';
      }

      html += '</tr></thead><tbody>';

      bridges.forEach(function(port) {
        var statusBadge = port.running ?
          '<span class="badge rounded-pill bg-success"><i class="ti ti-circle-check me-1"></i>Running</span>' :
          '<span class="badge rounded-pill bg-danger"><i class="ti ti-x me-1"></i>Stopped</span>';

        html += '<tr>';
        html += '<td><input type="checkbox" name="selected_ports[]" value="' + port.name +
          '" class="port-checkbox"></td>';
        html += '<td><strong>' + port.name + '</strong></td>';
        html += '<td><code>' + (port.mac_address || '-') + '</code></td>';
        html += '<td>' + statusBadge + '</td>';

        if (useSeparateBridges) {
          html += '<td>';
          html += '<select class="form-control input-sm port-service-select" data-port="' + port.name +
            '" name="port_service_' + port.name + '">';
          html += '<option value="both">Both Services</option>';
          html += '<option value="hotspot">Hotspot Only</option>';
          html += '<option value="pppoe">PPPoE Only</option>';
          html += '</select>';
          html += '</td>';
        }

        html += '<td>' + (port.comment || '-') + '</td>';
        html += '</tr>';
      });

      html += '</tbody></table></div>';

      portsDiv.html(html);

      $('#selectAll').on('click', function() {
        $('.port-checkbox').prop('checked', this.checked);
      });

    }



    // ── Pre-fill form from live MikroTik current config ───────────────────
    function prefillFromCurrentConfig() {
      var routerId = $('input[name="router_id"]').val();
      if (!routerId) return;

      const url = window.location.origin + '/?_route=plugin/mikrotik_configurator_get_current_config';

      $.ajax({
        url: url,
        method: 'GET',
        data: { router_id: routerId },
        dataType: 'json',
        timeout: 12000,
        success: function(data) {
          if (data.status !== 'success') return; // router offline or error — leave PHP defaults in place

          var d = data.detected || {};

          // ── Service checkboxes ────────────────────────────────────────────
          if (d.has_hotspot) {
            $('#hotspotCheck').prop('checked', true);
          }
          if (d.has_pppoe) {
            $('#pppoeCheck').prop('checked', true);
          }

          // Refresh UI panels now that checkboxes are set
          if (typeof toggleServiceOptions === 'function') toggleServiceOptions();

          // ── Subnets ───────────────────────────────────────────────────────
          if (d.hotspot_subnet) {
            $('#subnet_hotspot').val(d.hotspot_subnet);
          }
          if (d.pppoe_subnet) {
            $('#subnet_pppoe').val(d.pppoe_subnet);
          }
          updateHotspotRangeFromSubnet();

          // ── DNS name ─────────────────────────────────────────────────────
          if (d.hotspot_dns) {
            $('#hotspot_dns_name').val(d.hotspot_dns);
          }

          // ── Bridge names ──────────────────────────────────────────────────
          // After setBridgeFields() generates the inputs, overwrite with detected values
          setTimeout(function() {
            if (d.hotspot_bridge && d.pppoe_bridge && d.hotspot_bridge !== d.pppoe_bridge) {
              // Separate bridges detected
              $('#sameBridge').val('no').trigger('change');
              setTimeout(function() {
                if ($('#bridge_hotspot').length) $('#bridge_hotspot').val(d.hotspot_bridge);
                if ($('#bridge_pppoe').length)   $('#bridge_pppoe').val(d.pppoe_bridge);
              }, 50);
            } else {
              var bname = d.hotspot_bridge || d.pppoe_bridge;
              if (bname && $('#bridge').length) {
                $('#bridge').val(bname);
              }
            }
          }, 100);

          // ── Show a subtle info banner ─────────────────────────────────────
          var $banner = $('<div style="margin-bottom:14px;padding:10px 14px;border-left:3px solid #aaa;background:#f9f9f9;border-radius:3px;font-size:13px;color:#444;position:relative;">' +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="position:absolute;top:6px;right:10px;font-size:16px;background:none;border:none;color:#888;cursor:pointer;">&times;</button>' +
            '<i class="fa fa-info-circle"></i> <strong>Existing configuration detected.</strong> ' +
            'Form fields have been pre-filled from the live router. Change only what you need and click <strong>Configure Router</strong>.' +
          '</div>');
          $('form[method="post"]').prepend($banner);
        },
        error: function() {
          // Silently ignore — router may be offline; PHP defaults stand
        }
      });
    }

    $(document).ready(function() {
      loadMikrotikPorts();
      prefillFromCurrentConfig();
      updateHotspotRangeFromSubnet();
    });
  {/literal}
</script>

<script>
  {literal}
    function toggleServiceOptions() {
      var pppoeChecked = document.getElementById('pppoeCheck').checked;
      var hotspotChecked = document.getElementById('hotspotCheck').checked;
      var sameBridgeSelect = document.getElementById('sameBridge');

      document.getElementById('pppoeOptions').style.display = pppoeChecked ? 'block' : 'none';
      document.getElementById('hotspotOptions').style.display = hotspotChecked ? 'block' : 'none';

      // Show/hide subnet fields based on selected services
      var subnetHotspotRow = document.getElementById('subnetHotspotRow');
      var subnetPppoeRow = document.getElementById('subnetPppoeRow');
      if (subnetHotspotRow) subnetHotspotRow.style.display = hotspotChecked ? '' : 'none';
      if (subnetPppoeRow)   subnetPppoeRow.style.display   = pppoeChecked   ? '' : 'none';

      // Enable "separate bridges" option only when BOTH services are selected
      if (pppoeChecked && hotspotChecked) {
        sameBridgeSelect.disabled = false;
      } else {
        sameBridgeSelect.disabled = true;
        sameBridgeSelect.value = 'yes';
      }
    }

    // Auto-generate bridge name(s) on page load and when options change.
    // Subnets are pre-filled by PHP; this function only manages bridge NAME fields.
    document.addEventListener('DOMContentLoaded', function() {
      function setBridgeFields() {
        var routerName = document.getElementById('routerName').value.replace(/\s+/g, '_');
        var pppoeChecked = document.getElementById('pppoeCheck').checked;
        var hotspotChecked = document.getElementById('hotspotCheck').checked;
        var sameBridge = document.getElementById('sameBridge').value;
        var html = '';

        if (sameBridge === 'yes' || (pppoeChecked && !hotspotChecked) || (!pppoeChecked && hotspotChecked)) {
          var selected = [];
          if (pppoeChecked) selected.push('pppoe');
          if (hotspotChecked) selected.push('hotspot');
          var serviceStr = selected.length ? ('_' + selected.join('_')) : '';
          html += '<div class="form-group">';
          html += '<label><i class="fa fa-sitemap"></i> Bridge Name</label>';
          html += '<input type="text" class="form-control" id="bridge" name="bridge" value="' + routerName + '_bridge' + serviceStr + '">';
          html += '</div>';
        } else if (sameBridge === 'no' && pppoeChecked && hotspotChecked) {
          html += '<div class="row">';
          html += '<div class="col-md-6"><div class="form-group">';
          html += '<label><i class="fa fa-ethernet"></i> PPPoE Bridge Name</label>';
          html += '<input type="text" class="form-control" id="bridge_pppoe" name="bridge_pppoe" value="' + routerName + '_bridge_pppoe">';
          html += '</div></div>';
          html += '<div class="col-md-6"><div class="form-group">';
          html += '<label><i class="fa fa-wifi"></i> Hotspot Bridge Name</label>';
          html += '<input type="text" class="form-control" id="bridge_hotspot" name="bridge_hotspot" value="' + routerName + '_bridge_hotspot">';
          html += '</div></div>';
          html += '</div>';
        }
        document.getElementById('bridgeFields').innerHTML = html;
      }

      // Initial set on page load
      setBridgeFields();
      toggleServiceOptions();
      if (typeof loadMikrotikPorts === 'function') loadMikrotikPorts();

      document.getElementById('pppoeCheck').addEventListener('change', function() {
        setBridgeFields();
        toggleServiceOptions();
        if (typeof renderPortsTable === 'function') renderPortsTable();
      });
      document.getElementById('hotspotCheck').addEventListener('change', function() {
        setBridgeFields();
        toggleServiceOptions();
        if (typeof renderPortsTable === 'function') renderPortsTable();
      });
      document.getElementById('sameBridge').addEventListener('change', function() {
        setBridgeFields();
        if (typeof renderPortsTable === 'function') renderPortsTable();
      });
    });
  {/literal}
</script>

<script>
  {literal}
    // Re-generate one subnet field from any of the 3 private /16 ranges.
    // Subnets are pre-filled by PHP on page load; this is just the Refresh button.
    function regenSubnet(fieldId) {
      var subnet;
      var r = Math.floor(Math.random() * 3);
      if (r === 0) {
        subnet = '10.' + Math.floor(Math.random() * 256) + '.0.0/16';
      } else if (r === 1) {
        subnet = '172.' + (Math.floor(Math.random() * 16) + 16) + '.0.0/16';
      } else {
        subnet = '192.168.0.0/16';
      }
      document.getElementById(fieldId).value = subnet;
      if (fieldId === 'subnet_hotspot') {
        updateHotspotRangeFromSubnet();
      }
    }
  {/literal}
</script>

{include file="sections/footer.tpl"}