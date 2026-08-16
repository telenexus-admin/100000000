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
        margin-bottom: 20px;
    }
    
    .panel-primary > .panel-heading {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: white;
        border-color: var(--primary-dark);
        font-weight: 600;
        padding: 12px 18px;
        font-size: 16px;
    }
    
    .panel-heading i {
        margin-right: 8px;
    }
    
    .panel-body {
        padding: 25px;
        background: white;
    }
    
    .form-group {
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
    }
    
    .control-label {
        font-weight: 600;
        color: #1e293b;
        padding-top: 8px;
        text-align: right;
        font-size: 14px;
    }
    
    .col-md-3 {
        width: 25%;
        float: left;
        padding-right: 15px;
    }
    
    .col-md-9 {
        width: 75%;
        float: left;
        padding-left: 15px;
    }
    
    .col-md-offset-2 {
        margin-left: 16.666%;
    }
    
    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 14px;
        transition: all 0.2s;
        height: 44px;
        width: 100%;
        font-size: 14px;
        background-color: white;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        outline: none;
    }
    
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23f97316' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
    }
    
    .btn-success {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        color: white;
        font-weight: 600;
        border-radius: 30px;
        padding: 12px 30px;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        font-size: 15px;
        margin-right: 15px;
        border: none;
        cursor: pointer;
    }
    
    .btn-success:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(249, 115, 22, 0.4);
    }
    
    .btn-link {
        color: var(--primary);
        font-weight: 500;
        text-decoration: none;
        font-size: 15px;
        background: none;
        border: none;
        cursor: pointer;
    }
    
    .btn-link:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .btn-link.btn-xs {
        font-size: 14px;
        padding: 0 5px;
        margin-left: 5px;
    }
    
    a {
        color: var(--primary);
        font-weight: 500;
        text-decoration: none;
    }
    
    a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .input-group {
        display: flex;
        width: 100%;
    }
    
    .input-group-addon {
        background: var(--primary-soft);
        border: 2px solid #e2e8f0;
        border-right: none;
        border-radius: 12px 0 0 12px;
        color: var(--primary);
        font-weight: 600;
        padding: 10px 15px;
        font-size: 15px;
        white-space: nowrap;
    }
    
    .input-group .form-control {
        border-left: none;
        border-radius: 0 12px 12px 0;
    }
    
    .help-block {
        color: #64748b;
        font-size: 13px;
        margin-top: 8px;
        margin-bottom: 0;
        width: 100%;
        padding-left: 25%;
    }
    
    .help-block i {
        margin-right: 5px;
        color: var(--primary);
    }
    
    /* Radio button styling */
    .radio-group {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
        padding-top: 5px;
    }
    
    .radio-group label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        color: #1e293b;
        cursor: pointer;
        margin-right: 15px;
    }
    
    input[type="radio"] {
        accent-color: var(--primary);
        width: 18px;
        height: 18px;
        margin: 0;
        cursor: pointer;
    }
    
    /* Hidden sections */
    #expired_date {
        transition: all 0.3s ease;
    }
    
    .hidden {
        display: none !important;
    }
    
    /* Two-column layout */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -15px;
    }
    
    .col-md-6 {
        width: 50%;
        padding: 0 15px;
        box-sizing: border-box;
    }
    
    @media (max-width: 992px) {
        .col-md-6 {
            width: 100%;
        }
        
        .control-label {
            text-align: left;
            margin-bottom: 8px;
        }
        
        .col-md-3, .col-md-9 {
            width: 100%;
            padding: 0;
        }
        
        .help-block {
            padding-left: 0;
        }
        
        .col-md-offset-2 {
            margin-left: 0;
        }
    }
    
    /* Bottom buttons */
    .form-group:last-child {
        margin-top: 20px;
        margin-bottom: 0;
    }
    
    .col-md-offset-2 {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    /* Select2 styling */
    .select2-container--default .select2-selection--single {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        height: 44px;
        padding: 8px 12px;
    }
    
    .select2-container--default .select2-selection--single:focus {
        border-color: var(--primary);
    }
    
    /* Popover styling */
    .btn-link.btn-xs i {
        color: var(--primary);
        font-size: 16px;
    }
    
    /* Has-success/warning states */
    .has-success .form-control {
        border-color: #22c55e;
    }
    
    .has-warning .form-control {
        border-color: var(--primary);
    }
    
    /* Section headers */
    .panel-heading span {
        color: var(--primary-light);
        font-weight: 400;
        margin-left: 5px;
    }
    
    /* CodeMirror styling */
    .CodeMirror {
        border: 2px solid var(--primary-light);
        border-radius: 12px;
        height: auto;
        min-height: 300px;
        font-family: 'Courier New', monospace;
    }
    
    .CodeMirror-focused {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    
    /* Clear floats */
    .clearfix:after {
        content: "";
        display: table;
        clear: both;
    }
</style>

<form class="form-horizontal" method="post" role="form" action="{Text::url('services/edit-post')}">
    <input type="hidden" name="id" value="{$d['id']}">
    
    <div class="row">
        <!-- Left Column -->
        <div class="col-md-6">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <i class="glyphicon glyphicon-pencil"></i>
                    {Lang::T('Edit Service Package')} | <span>{$d['name_plan']}</span>
                </div>
                <div class="panel-body">
                    <!-- Status -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">
                            {Lang::T('Status')}
                            <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                data-trigger="focus" data-container="body"
                                data-content="{Lang::T('Enable or disable this package')}">
                                <i class="fa fa-question-circle"></i>
                            </a>
                        </label>
                        <div class="col-md-9">
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="enabled" value="1" {if $d['enabled'] == 1}checked{/if}>
                                    {Lang::T('Active')}
                                </label>
                                <label>
                                    <input type="radio" name="enabled" value="0" {if $d['enabled'] == 0}checked{/if}>
                                    {Lang::T('Not Active')}
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Type (Prepaid/Postpaid) -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">
                            {Lang::T('Type')}
                            <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                data-trigger="focus" data-container="body"
                                data-content="{Lang::T('Postpaid will have fixed expired date')}">
                                <i class="fa fa-question-circle"></i>
                            </a>
                        </label>
                        <div class="col-md-9">
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="prepaid" onclick="prePaid()" value="yes"
                                        {if $d['prepaid'] == 'yes'}checked{/if}>
                                    {Lang::T('Prepaid')}
                                </label>
                                <label>
                                    <input type="radio" name="prepaid" onclick="postPaid()" value="no"
                                        {if $d['prepaid'] == 'no'}checked{/if}>
                                    {Lang::T('Postpaid')}
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Package Type (Personal/Business) -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">
                            {Lang::T('Package Type')}
                            <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                data-trigger="focus" data-container="body"
                                data-content="{Lang::T('Personal: shows to personal customers only. Business: shows to business customers only')}">
                                <i class="fa fa-question-circle"></i>
                            </a>
                        </label>
                        <div class="col-md-9">
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="plan_type" value="Personal"
                                        {if $d['plan_type'] == 'Personal'}checked{/if}>
                                    {Lang::T('Personal')}
                                </label>
                                <label>
                                    <input type="radio" name="plan_type" value="Business"
                                        {if $d['plan_type'] == 'Business'}checked{/if}>
                                    {Lang::T('Business')}
                                </label>
                            </div>
                        </div>
                    </div>

                    {* Device is hard-wired to MikrotikHotspot for Hotspot plans. Preserve any legacy value. *}
                    <input type="hidden" name="device" id="device" value="{if !empty($d['device'])}{$d['device']}{else}MikrotikHotspot{/if}">

                    <!-- Package Name -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">{Lang::T('Package Name')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="name" name="name" maxlength="40"
                                value="{$d['name_plan']}" placeholder="Enter package name">
                        </div>
                    </div>

                    <!-- Package Type (Unlimited/Limited) -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">{Lang::T('Package Type')}</label>
                        <div class="col-md-9">
                            <div class="radio-group">
                                <label>
                                    <input type="radio" id="Unlimited" name="typebp" value="Unlimited"
                                        {if $d['typebp'] eq 'Unlimited'} checked {/if}>
                                    {Lang::T('Unlimited')}
                                </label>
                                <label>
                                    <input type="radio" id="Limited" name="typebp" value="Limited"
                                        {if $d['typebp'] eq 'Limited'} checked {/if}>
                                    {Lang::T('Limited')}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-6">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <i class="glyphicon glyphicon-cog"></i>
                    {Lang::T('Package Configuration')}
                </div>
                <div class="panel-body">
                    <!-- Limit Type (shows when Limited is selected) -->
                    <div {if $d['typebp'] eq 'Unlimited'} style="display:none;" {/if} id="Type">
                        <div class="form-group clearfix">
                            <label class="col-md-3 control-label">{Lang::T('Limit Type')}</label>
                            <div class="col-md-9">
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" id="Time_Limit" name="limit_type" value="Time_Limit"
                                            {if $d['limit_type'] eq 'Time_Limit'} checked {/if}>
                                        {Lang::T('Time Limit')}
                                    </label>
                                    <label>
                                        <input type="radio" id="Data_Limit" name="limit_type" value="Data_Limit"
                                            {if $d['limit_type'] eq 'Data_Limit'} checked {/if}>
                                        {Lang::T('Data Limit')}
                                    </label>
                                    <label>
                                        <input type="radio" id="Both_Limit" name="limit_type" value="Both_Limit"
                                            {if $d['limit_type'] eq 'Both_Limit'} checked {/if}>
                                        {Lang::T('Both Limit')}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Time Limit -->
                    <div {if $d['typebp'] eq 'Unlimited'} style="display:none;"
                    {elseif ($d['time_limit']) eq '0'} style="display:none;" {/if} id="TimeLimit">
                        <div class="form-group clearfix">
                            <label class="col-md-3 control-label">{Lang::T('Time Limit')}</label>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="time_limit" name="time_limit"
                                    value="{$d['time_limit']}" placeholder="Enter value">
                            </div>
                            <div class="col-md-5">
                                <select class="form-control" id="time_unit" name="time_unit">
                                    <option value="Hrs" {if $d['time_unit'] eq 'Hrs'} selected {/if}>{Lang::T('Hours')}</option>
                                    <option value="Mins" {if $d['time_unit'] eq 'Mins'} selected {/if}>{Lang::T('Minutes')}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Data Limit -->
                    <div {if $d['typebp'] eq 'Unlimited'} style="display:none;"
                    {elseif ($d['data_limit']) eq '0'} style="display:none;" {/if} id="DataLimit">
                        <div class="form-group clearfix">
                            <label class="col-md-3 control-label">{Lang::T('Data Limit')}</label>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="data_limit" name="data_limit"
                                    value="{$d['data_limit']}" placeholder="Enter value">
                            </div>
                            <div class="col-md-5">
                                <select class="form-control" id="data_unit" name="data_unit">
                                    <option value="MB" {if $d['data_unit'] eq 'MB'} selected {/if}>MB</option>
                                    <option value="GB" {if $d['data_unit'] eq 'GB'} selected {/if}>GB</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Bandwidth Name -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">
                            <a href="{Text::url('bandwidth/add')}" target="_blank">{Lang::T('Bandwidth')}</a>
                        </label>
                        <div class="col-md-9">
                            <select id="id_bw" name="id_bw" class="form-control select2">
                                {foreach $b as $bs}
                                    <option value="{$bs['id']}" {if $d['id_bw'] eq $bs['id']} selected {/if}>
                                        {$bs['name_bw']}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    <!-- Package Price -->
                    <div class="form-group clearfix has-success">
                        <label class="col-md-3 control-label">{Lang::T('Package Price')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-addon">{$_c['currency_code']}</span>
                                <input type="number" class="form-control" name="price" value="{$d['price']}" required step="0.01" min="0">
                            </div>
                        </div>
                        {if $_c['enable_tax'] == 'yes'}
                            <div class="help-block">
                                <i class="glyphicon glyphicon-info-sign"></i>
                                {if $_c['tax_rate'] == 'custom'}
                                    {number_format($_c['custom_tax_rate'], 2)}% {Lang::T('Tax will be added')}
                                {else}
                                    {number_format($_c['tax_rate'] * 100, 2)}% {Lang::T('Tax will be added')}
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <!-- Price Before Discount -->
                    <div class="form-group clearfix has-warning">
                        <label class="col-md-3 control-label">{Lang::T('Discount Price')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-addon">{$_c['currency_code']}</span>
                                <input type="number" class="form-control" name="price_old" value="{$d['price_old']}" step="0.01" min="0">
                            </div>
                            <div class="help-block">
                                <i class="glyphicon glyphicon-info-sign"></i>
                                {Lang::T('Original price before discount (must be higher than actual price)')}
                            </div>
                        </div>
                    </div>

                    <!-- Shared Users -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">
                            {Lang::T('Shared Users')}
                            <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                data-trigger="focus" data-container="body"
                                data-content="{Lang::T('Maximum devices that can connect simultaneously per account')}">
                                <i class="fa fa-question-circle"></i>
                            </a>
                        </label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" id="sharedusers" name="sharedusers"
                                value="{$d['shared_users']}" min="1" placeholder="e.g., 5">
                        </div>
                    </div>

                    <!-- Package Validity -->
                    <div class="form-group clearfix">
                        <label class="col-md-3 control-label">{Lang::T('Validity')}</label>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="validity" name="validity"
                                value="{$d['validity']}" placeholder="Enter value">
                        </div>
                        <div class="col-md-5">
                            <select class="form-control" id="validity_unit" name="validity_unit">
                                {if $d['prepaid'] == 'yes'}
                                    <option value="Mins" {if $d['validity_unit'] eq 'Mins'} selected {/if}>{Lang::T('Minutes')}</option>
                                    <option value="Hrs" {if $d['validity_unit'] eq 'Hrs'} selected {/if}>{Lang::T('Hours')}</option>
                                    <option value="Days" {if $d['validity_unit'] eq 'Days'} selected {/if}>{Lang::T('Days')}</option>
                                    <option value="Months" {if $d['validity_unit'] eq 'Months'} selected {/if}>{Lang::T('Months')}</option>
                                {else}
                                    <option value="Period" {if $d['validity_unit'] eq 'Period'} selected {/if}>{Lang::T('Period')}</option>
                                {/if}
                            </select>
                        </div>
                    </div>

                    <!-- Expired Date (for Postpaid) -->
                    <div class="form-group clearfix {if $d['prepaid'] == 'yes'}hidden{/if}" id="expired_date">
                        <label class="col-md-3 control-label">
                            {Lang::T('Expiry Day')}
                            <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                data-trigger="focus" data-container="body"
                                data-content="{Lang::T('Day of month when package expires (1-28)')}">
                                <i class="fa fa-question-circle"></i>
                            </a>
                        </label>
                        <div class="col-md-9">
                            <input type="number" class="form-control" name="expired_date" 
                                value="{if $d['expired_date']}{$d['expired_date']}{else}20{/if}" 
                                min="1" max="28" step="1" placeholder="Day of month (1-28)">
                        </div>
                    </div>

                    <!-- Router Name -->
                    <span id="routerChoose" class="{if $d['is_radius']}hidden{/if}">
                        <div class="form-group clearfix">
                            <label class="col-md-3 control-label">
                                <a href="{Text::url('routers/add')}" target="_blank">{Lang::T('Router')}</a>
                            </label>
                            <div class="col-md-9">
                                <input type="text" class="form-control" id="routers" name="routers"
                                    value="{$d['routers']}" readonly placeholder="Router name">
                            </div>
                        </div>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="row">
        <div class="col-md-12">
            <div class="form-group clearfix">
                <div class="col-md-offset-2 col-md-9">
                    <button type="submit" class="btn btn-success" 
                        onclick="return ask(this, '{Lang::T('Save changes to this package?')}')">
                        <i class="glyphicon glyphicon-save"></i> {Lang::T('Save Changes')}
                    </button>
                    <a href="{Text::url('services/hotspot')}" class="btn-link">
                        <i class="glyphicon glyphicon-remove"></i> {Lang::T('Cancel')}
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    var preOpt = `<option value="Mins">{Lang::T('Minutes')}</option>
    <option value="Hrs">{Lang::T('Hours')}</option>
    <option value="Days">{Lang::T('Days')}</option>
    <option value="Months">{Lang::T('Months')}</option>`;
    
    var postOpt = `<option value="Period">{Lang::T('Period')}</option>`;
    
    function prePaid() {
        $("#validity_unit").html(preOpt);
        $('#expired_date').addClass('hidden');
    }

    function postPaid() {
        $("#validity_unit").html(postOpt);
        $("#expired_date").removeClass('hidden');
    }

    // Show/hide limit sections based on package type
    $(document).ready(function() {
        $('input[name="typebp"]').on('change', function() {
            if ($(this).val() === 'Unlimited') {
                $('#Type, #TimeLimit, #DataLimit').hide();
            } else {
                $('#Type').show();
                // Check which limit type is selected and show appropriate fields
                var limitType = $('input[name="limit_type"]:checked').val();
                if (limitType === 'Time_Limit' || limitType === 'Both_Limit') {
                    $('#TimeLimit').show();
                }
                if (limitType === 'Data_Limit' || limitType === 'Both_Limit') {
                    $('#DataLimit').show();
                }
            }
        });

        $('input[name="limit_type"]').on('change', function() {
            var val = $(this).val();
            $('#TimeLimit, #DataLimit').hide();
            if (val === 'Time_Limit' || val === 'Both_Limit') {
                $('#TimeLimit').show();
            }
            if (val === 'Data_Limit' || val === 'Both_Limit') {
                $('#DataLimit').show();
            }
        });
    });
</script>

{if $_c['radius_enable'] && $d['is_radius']}
    <script>
        function isRadius(cek) {
            if (cek.checked) {
                $("#routerChoose").addClass('hidden');
                document.getElementById("routers").required = false;
                document.getElementById("Limited").disabled = true;
            } else {
                document.getElementById("Limited").disabled = false;
                document.getElementById("routers").required = true;
                $("#routerChoose").removeClass('hidden');
            }
        }
    </script>
{/if}

{include file="sections/footer.tpl"}