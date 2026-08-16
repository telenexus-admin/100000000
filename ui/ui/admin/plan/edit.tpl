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
    
    .panel-title {
        font-size: 16px;
        font-weight: 600;
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
    
    .form-control[readonly] {
        background: var(--primary-soft);
        border-color: var(--primary-light);
        color: var(--primary-dark);
        font-weight: 600;
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
    <div class="col-sm-12">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-pencil" style="margin-right: 8px;"></i>
                <h3 class="panel-title" style="display: inline-block;">{Lang::T('Edit Plan')}</h3>
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}plan/edit-post">
                    <input type="hidden" name="id" value="{$d['id']}">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Username')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="username" name="username"
                                value="{$d['username']}" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Service Plan')}</label>
                        <div class="col-md-6">
                            <select id="id_plan" name="id_plan" class="form-control select2">
                                {foreach $p as $ps}
                                    <option value="{$ps['id']}" {if $d['plan_id'] eq $ps['id']} selected {/if}>
                                        {if $ps['enabled'] neq 1}
                                            <span style="color: #ef4444;">DISABLED PLAN &bull;</span>
                                        {/if}
                                        {$ps['name_plan']} &bull;
                                        {Lang::moneyFormat($ps['price'])}
                                        {if $ps['prepaid'] neq 'yes'} &bull; POSTPAID {/if}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Created On')}</label>
                        <div class="col-md-4">
                            <input type="date" class="form-control" readonly
                                value="{$d['recharged_on']}">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" placeholder="00:00:00" readonly
                                value="{$d['recharged_time']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Expires On')}</label>
                        <div class="col-md-4">
                            <input type="date" class="form-control" id="expiration" name="expiration"
                                value="{$d['expiration']}">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" id="time" name="time" placeholder="00:00:00"
                                value="{$d['time']}">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-success" onclick="return ask(this, '{Lang::T('Continue the package change process')}?')" type="submit">
                                <i class="glyphicon glyphicon-save"></i> {Lang::T('Edit')}
                            </button>
                            {Lang::T('Or')} <a href="{Text::url('')}plan/list">
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