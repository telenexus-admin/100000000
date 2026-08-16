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
    
    #customerBalance {
        background: var(--primary-soft);
        padding: 8px 15px;
        border-radius: 20px;
        display: inline-block;
        border: 1px solid var(--primary-light);
        color: var(--primary-dark);
        font-weight: 600;
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
    <div class="col-lg-6 col-lg-offset-3">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-plus-sign" style="margin-right: 8px;"></i>
                {Lang::T('Refill Balance')}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}plan/deposit-post">
                    <input type="hidden" name="stoken" value="{App::getToken()}">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Select Account')}</label>
                        <div class="col-md-9">
                            <select id="personSelect" class="form-control select2" onchange="getBalance(this)"
                                name="id_customer" style="width: 100%"
                                data-placeholder="{Lang::T('Select a customer')}...">
                            </select>
                            <span class="help-block" id="customerBalance">
                                <i class="glyphicon glyphicon-info-sign" style="color: var(--primary); margin-right: 5px;"></i>
                                {Lang::T('Select a customer to see balance')}
                            </span>
                        </div>
                    </div>
                    <span class="help-block" style="background: var(--primary-soft); padding: 10px; border-radius: 8px; border-left: 3px solid var(--primary); margin-bottom: 15px;">
                        <i class="glyphicon glyphicon-info-sign" style="color: var(--primary); margin-right: 5px;"></i>
                        {Lang::T('Select Balance Package or Custom Amount')}
                    </span>
                    <div class="form-group">
                        <label class="col-md-3 control-label">
                            <a href="{Text::url('')}services/balance" style="color: var(--primary);">
                                <i class="glyphicon glyphicon-gift"></i> {Lang::T('Balance Package')}
                            </a>
                        </label>
                        <div class="col-md-9">
                            <select id="planSelect" class="form-control select2" name="id_plan" style="width: 100%"
                                data-placeholder="{Lang::T('Select Plans')}...">
                                <option></option>
                                {foreach $p as $pl}
                                    <option value="{$pl['id']}">
                                        {if $pl['enabled'] neq 1}
                                            <span style="color: #ef4444;">DISABLED PLAN &bull;</span>
                                        {/if}
                                        {$pl['name_plan']} - {Lang::moneyFormat($pl['price'])}
                                    </option>
                                {/foreach}
                            </select>
                            <span class="help-block">
                                <i class="glyphicon glyphicon-hand-right" style="color: var(--primary);"></i>
                                {Lang::T('Or custom balance amount below')}
                            </span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Balance Amount')}</label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" name="amount" style="width: 100%" placeholder="0">
                            <span class="help-block">
                                <i class="glyphicon glyphicon-pencil" style="color: var(--primary);"></i>
                                {Lang::T('Input custom balance, will ignore plan above')}
                            </span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Note')}</label>
                        <div class="col-md-9">
                            <textarea class="form-control" name="note" style="width: 100%" rows="3" placeholder="{Lang::T('Optional notes...')}"></textarea>
                            <span class="help-block"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-9 col-md-offset-3">
                            <button class="btn btn-success"
                                onclick="return ask(this, '{Lang::T('Continue the Customer Balance top-up process')}?')"
                                type="submit">
                                <i class="glyphicon glyphicon-ok"></i> {Lang::T('Recharge')
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

<script>
    function getBalance(f) {
        $.get('{Text::url('')}autoload/balance/'+f.value+'/1', function(data) {
            document.getElementById('customerBalance').innerHTML = '<i class="glyphicon glyphicon-credit-card" style="color: var(--primary); margin-right: 5px;"></i> ' + data;
        });
    }
</script>

{include file="sections/footer.tpl"}