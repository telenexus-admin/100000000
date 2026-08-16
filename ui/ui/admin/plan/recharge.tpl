{include file="sections/header.tpl"}

<style>
    :root {
        --primary: #f97316;
        --primary-dark: #ea580c;
        --primary-light: #fed7aa;
        --primary-soft: #fff7ed;
    }

    .panel-primary {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .panel-primary > .panel-heading {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: white;
        border-color: var(--primary-dark);
        font-weight: 600;
        padding: 12px 15px;
    }
    
    .panel-body {
        padding: 25px;
        background: white;
    }
    
    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 12px;
        transition: all 0.2s;
        height: 40px;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    
    .control-label {
        font-weight: 600;
        color: #1e293b;
        padding-top: 8px;
    }
    
    .btn-success {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        color: white;
        font-weight: 500;
        border-radius: 30px;
        padding: 8px 25px;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    }
    
    .btn-success:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(249, 115, 22, 0.4);
    }
    
    a {
        color: var(--primary);
        font-weight: 500;
    }
    
    a:hover {
        color: var(--primary-dark);
    }
    
    .help-block {
        color: #64748b;
        font-size: 12px;
        margin-top: 5px;
        padding-left: 10px;
    }
    
    input[type="radio"] {
        accent-color: var(--primary);
        width: 16px;
        height: 16px;
        margin-right: 5px;
        margin-left: 15px;
    }
    
    input[type="radio"]:first-of-type {
        margin-left: 0;
    }
    
    label {
        margin-right: 20px;
        font-weight: 500;
        color: #1e293b;
    }
    
    .select2-container--default .select2-selection--single {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        height: 40px;
        padding: 5px;
    }
    
    .select2-container--default .select2-selection--single:focus {
        border-color: var(--primary);
    }
</style>

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-flash" style="margin-right: 8px;"></i>
                {Lang::T('Recharge Account')}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}plan/recharge-confirm">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Select Account')}</label>
                        <div class="col-md-6">
                            <select {if $cust}{else}id="personSelect" {/if} class="form-control select2"
                                name="id_customer" style="width: 100%"
                                data-placeholder="{Lang::T('Select a customer')}...">
                                {if $cust}
                                    <option value="{$cust['id']}">{$cust['username']} &bull; {$cust['fullname']} &bull; {$cust['email']}</option>
                                {/if}
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Type')}</label>
                        <div class="col-md-6">
                            <label><input type="radio" id="Hot" name="type" value="Hotspot"> {Lang::T('Hotspot Plans')}</label>
                            <label><input type="radio" id="POE" name="type" value="PPPOE"> {Lang::T('PPPOE Plans')}</label>
                 
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Routers')}</label>
                        <div class="col-md-6">
                            <select id="server" data-type="server" name="server" class="form-control select2">
                                <option value=''>{Lang::T('Select Routers')}</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Service Plan')}</label>
                        <div class="col-md-6">
                            <select id="plan" name="plan" class="form-control select2">
                                <option value=''>{Lang::T('Select Plans')}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Using')}</label>
                        <div class="col-md-6">
                            <select name="using" class="form-control">
                                {foreach $usings as $using}
                                    <option value="{trim($using)}">{trim(ucWords($using))}</option>
                                {/foreach}
                                {if $_c['enable_balance'] eq 'yes'}
                                    <option value="balance">{Lang::T('Customer Balance')}</option>
                                {/if}
                                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                                    <option value="zero">{$_c['currency_code']} 0</option>
                                {/if}
                            </select>
                        </div>
                      
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-success"
                                onclick="return ask(this, '{Lang::T('Continue the Recharge process')}?')"
                                type="submit">
                                <i class="glyphicon glyphicon-ok"></i> {Lang::T('Recharge')}
                            </button>
                            {Lang::T('Or')} <a href="{Text::url('')}customers/list">
                                <i class="glyphicon glyphicon-remove"></i> {Lang::T('Cancel')}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}