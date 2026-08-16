{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/paybilltillsbankmpesa">
  <div class="row">
    <div class="col-sm-12 col-md-10 col-md-offset-1">
      <div class="panel panel-primary mb30">
        <div class="panel-heading">
          <h4 class="panel-title">Complete Your Payment</h4>
        </div>
        <div class="panel-body">

          <!-- Payment Type Selection -->
          <div class="form-group">
            <label class="col-md-3 control-label">Choose Payment Type</label>
            <div class="col-md-7">
              <select class="form-control" name="mpesa_bank_paybill_till_type" id="paymenttype">
                <option value="none" {if $_c['mpesa_bank_paybill_till_type'] == 'none'}selected{/if}>-- Select Payment Type --</option>
                <option value="paybill" {if $_c['mpesa_bank_paybill_till_type'] == 'paybill'}selected{/if}>Paybill Number</option>
                <option value="till" {if $_c['mpesa_bank_paybill_till_type'] == 'till'}selected{/if}>Buy Goods Till Number</option>
                <option value="bank" {if $_c['mpesa_bank_paybill_till_type'] == 'bank'}selected{/if}>Bank Account</option>
                <option value="phone" {if $_c['mpesa_bank_paybill_till_type'] == 'phone'}selected{/if}>Phone Number</option>
              </select>
            </div>
          </div>

          <!-- Paybill Input -->
          <div class="form-group" id="paybill-group" style="display:none;">
            <label class="col-md-3 control-label">Paybill Number</label>
            <div class="col-md-7">
              <input type="text" class="form-control" name="paybilltillsbankmpesa_paybill" placeholder="e.g. 123456"
                value="{$_c['paybilltillsbankmpesa_paybill']}">
            </div>
          </div>

          <!-- Till Input -->
          <div class="form-group" id="till-group" style="display:none;">
            <label class="col-md-3 control-label">Till Number</label>
            <div class="col-md-7">
              <input type="text" class="form-control" name="paybilltillsbankmpesa_till" placeholder="e.g. 567890"
                value="{$_c['paybilltillsbankmpesa_till']}">
            </div>
          </div>

          <!-- Bank Account Details -->
          <div id="bank-group" style="display:none;">
            <div class="form-group">
              <label class="col-md-3 control-label">Bank Name</label>
              <div class="col-md-7">
                <select class="form-control" name="paybilltillsbankmpesa__bank_paybill_number">
                  <option value="">-- Select Bank --</option>
                  {foreach from=$banks item=bank}
                    <option value="{$bank.paybill}" {if $_c['paybilltillsbankmpesa__bank_paybill_number'] == $bank.paybill }selected{/if}>
                      {$bank.name} Bank
                    </option>
                  {/foreach}
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="col-md-3 control-label">Bank Account Number</label>
              <div class="col-md-7">
                <input type="text" class="form-control" name="paybilltillsbankmpesa__mpesa_bank_account_number"
                  placeholder="Enter Bank Account Number" value="{$_c['paybilltillsbankmpesa__mpesa_bank_account_number']}">
              </div>
            </div>
          </div>

          <!-- Phone Number Input -->
          <div class="form-group" id="phone-group" style="display:none;">
            <label class="col-md-3 control-label">Destination Phone Number</label>
            <div class="col-md-7">
              <input type="text" class="form-control" name="paybilltillsbankmpesa_phone" placeholder="e.g. 0712345678"
                value="{$_c['paybilltillsbankmpesa_phone']}">
              <small class="help-block">Phone number where all collected payments will be sent</small>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="form-group">
            <div class="col-md-offset-3 col-md-7">
              <button class="btn btn-success btn-block" type="submit">
                <i class="fa fa-save"></i> Save Payment Details
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</form>

<!-- Include JavaScript for Dynamic Field Handling -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const paymentType = document.getElementById('paymenttype');
    const paybillGroup = document.getElementById('paybill-group');
    const tillGroup = document.getElementById('till-group');
    const bankGroup = document.getElementById('bank-group');
    const phoneGroup = document.getElementById('phone-group');

    function updateFields() {
      const selected = paymentType.value;
      paybillGroup.style.display = selected === 'paybill' ? 'block' : 'none';
      tillGroup.style.display = selected === 'till' ? 'block' : 'none';
      bankGroup.style.display = selected === 'bank' ? 'block' : 'none';
      phoneGroup.style.display = selected === 'phone' ? 'block' : 'none';
    }

    paymentType.addEventListener('change', updateFields);
    updateFields(); // Show relevant fields on load
  });
</script>

{include file="sections/footer.tpl"}
