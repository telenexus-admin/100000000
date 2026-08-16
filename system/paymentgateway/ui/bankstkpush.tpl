{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/BankStkPush">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">Fill the details below to complete the bank stk Push</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Enter Bank account number</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="account"
                                placeholder="Enter Bank account number" value="{$_c['Stkbankacc']}" required>

                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Bank Name</label>
                        <div class="col-md-6">
                            <select class="form-control" name="bankname" id="bankstk">
                                <option value="">Select Bank</option>
                                {foreach from=$banks item=bank}
                                    <option value="{$bank.name}" {if $_c['Stkbankname'] == $bank.name }selected{/if}>
                                        {$bank.name } Bank</option>
                                {/foreach}
                            </select>

                        </div>
                    </div>
                    <pre>After aplying these changes, the funds shall be going to the saved bank account, please make sure the bank name and account matches</pre>

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">Save</button>
                        </div>
                    </div>

                </div>

            </div>
        </div>
</form>
{include file="sections/footer.tpl"}