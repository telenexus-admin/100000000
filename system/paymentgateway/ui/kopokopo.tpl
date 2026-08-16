{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/kopokopo" id="mtn_form"
  enctype="multipart/form-data">
  <div class="row">
    <div class="col-md-12">
      <div class="panel panel-primary panel-hovered panel-stacked mb30">
        <div class="panel-heading text-left font-weight-bold">Kopokopo Payment Gateway</div>
        <div class="panel-body">

          <!-- Kopokopo Client ID -->
          <div class="form-group">
            <label class="col-md-3 control-label">Kopokopo Client ID</label>
            <div class="col-md-6">
              <input type="text" class="form-control" name="kopokopo_client_id" placeholder="Enter Kopokopo Client ID"
                value="{$_c['kopokopo_client_id']}" required>
              <small class="text-muted d-block mt-1">
                Find your Kopokopo Client ID in the Kopokopo Developer Portal:
                <a href="https://app.kopokopo.com/oauth/applications" target="_blank">
                  https://app.kopokopo.com/oauth/applications
                </a>
              </small>
            </div>
          </div>

          <!-- Kopokopo Client Secret -->
          <div class="form-group">
            <label class="col-md-3 control-label">Kopokopo Client Secret</label>
            <div class="col-md-6">
              <input type="text" class="form-control" name="kopokopo_client_secret"
                placeholder="Enter Kopokopo Client Secret" value="{$_c['kopokopo_client_secret']}" required>
              <small class="text-muted d-block mt-1">
                This is found alongside your Client ID in the Kopokopo Developer Portal.
              </small>
            </div>
          </div>

          <!-- Kopokopo Till Number -->
          <div class="form-group">
            <label class="col-md-3 control-label">Kopokopo Till Number</label>
            <div class="col-md-6">
              <input type="text" class="form-control" name="kopokopo_till_number" placeholder="Enter Till Number"
                value="{$_c['kopokopo_till_number']}" required>
              <small class="text-muted d-block mt-1">
                This is the Till Number linked to your Kopokopo business account.
              </small>
            </div>
          </div>

          <div class="form-group">
            <label class="col-md-3 control-label">Kopokopo STK Till Number ID <span
                class="text-info">(K123456)</span></label>
            <div class="col-md-6">
              <input type="text" class="form-control" name="kopokopo_stk_till_number_id"
                placeholder="Enter STK Till Number ID" value="{$_c['kopokopo_stk_till_number_id']}" required>
              <small class="text-muted d-block mt-1">
                This is the STK Till Number ID linked to your Kopokopo business account.
              </small>
            </div>
          </div>

          <div class="form-group col-6">
            <label class="col-md-3 control-label">Support Offline Pay Methods</label>
            <div class="col-md-6">
              <select class="form-control" name="kopokopo_channel_ofline_online" id="kopokopo_channel_ofline_online">
                <option value="0" {if $_c['kopokopo_channel_ofline_online'] == 0}selected{/if}>No</option>
                <option value="1" {if $_c['kopokopo_channel_ofline_online'] == 1}selected{/if}>Yes</option>
              </select>
              <small class="form-text text-muted">Enable this if you want to support offline payment methods.</small>
            </div>
          </div>

          <div class="form-group col-6" id="offlinePayFields" style="display: none;">
            <label class="col-md-3 control-label">Register URL</label>
            <div class="col-md-9">
              <a href="{$_url}plugin/kopokopo_offline&kind_url=register" class="btn btn-success btn-sm styled-btn">
                <i class="fas fa-link"></i> Register Kopokopo URL (Offline Payment)
              </a>
              <br>
              <small class="form-text text-muted mt-2">
                Please save your changes before clicking the button above.
              </small>
            </div>
          </div>


          <!-- Submit Button -->
          <div class="form-group">
            <div class="col-md-offset-3 col-md-6">
              <button class="btn btn-primary btn-block waves-effect waves-light" type="submit">Save</button>
            </div>


            <!-- Walled Garden Configuration -->
            <hr>
            <div class="form-group">
              <label class="col-md-3 control-label">Mikrotik Walled Garden</label>
              <div class="col-md-6">
                <pre class="bg-light p-3 rounded" style="font-size: 13px;">
/ip hotspot walled-garden
add dst-host=kopokopo.com
add dst-host=*.kopokopo.com
              </pre>
                <small class="text-muted">Ensure these domains are accessible for payment to work correctly.</small>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
</form>
<!-- end of your HTML body -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(function() {
    function toggleOfflinePayFields() {
      console.log('toggleOfflinePayFields called');
      if ($('#kopokopo_channel_ofline_online').val() == '1') {
        console.log('Show fields');
        $('#offlinePayFields').show();
      } else {
        console.log('Hide fields');
        $('#offlinePayFields').hide();
      }
    }

    toggleOfflinePayFields();
    $('#kopokopo_channel_ofline_online').on('change', toggleOfflinePayFields);
  });
</script>
</body>


{include file="sections/footer.tpl"}