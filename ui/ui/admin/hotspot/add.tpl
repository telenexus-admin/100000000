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
        padding: 15px 20px;
        font-size: 16px;
    }
    
    .panel-heading i {
        margin-right: 8px;
    }
    
    .panel-body {
        padding: 30px;
        background: white;
    }
    
    .form-group {
        margin-bottom: 25px;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
    }
    
    .control-label {
        font-weight: 600;
        color: #1e293b;
        padding-top: 10px;
        text-align: right;
        font-size: 14px;
    }
    
    .col-md-2 {
        width: 16.666%;
        float: left;
        padding-right: 15px;
    }
    
    .col-md-4, .col-md-6, .col-md-10 {
        float: left;
        padding-left: 15px;
    }
    
    .col-md-4 {
        width: 33.333%;
    }
    
    .col-md-6 {
        width: 50%;
    }
    
    .col-md-10 {
        width: 83.333%;
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
        padding-right: 40px;
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
        padding-left: 15px;
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
        padding-top: 8px;
    }
    
    .radio-group label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        color: #1e293b;
        cursor: pointer;
        margin-right: 20px;
    }
    
    input[type="radio"] {
        accent-color: var(--primary);
        width: 18px;
        height: 18px;
        margin: 0;
        cursor: pointer;
    }
    
    input[type="checkbox"] {
        accent-color: var(--primary);
        width: 18px;
        height: 18px;
        margin-right: 8px;
        cursor: pointer;
    }
    
    /* Hidden sections */
    #Type, #TimeLimit, #DataLimit {
        transition: all 0.3s ease;
        padding-left: 20px;
        border-left: 3px solid var(--primary-light);
        margin-left: 20px;
        margin-top: 15px;
        margin-bottom: 15px;
    }
    
    .hidden {
        display: none !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 992px) {
        .control-label {
            text-align: left;
            margin-bottom: 8px;
        }
        
        .col-md-2, .col-md-4, .col-md-6, .col-md-10 {
            width: 100%;
            padding: 0 15px;
        }
        
        .col-md-offset-2 {
            margin-left: 0;
        }
        
        #Type, #TimeLimit, #DataLimit {
            margin-left: 0;
        }
    }
    
    /* Badge styling */
    .label-primary {
        background: var(--primary);
    }
    
    /* Clear floats */
    .clearfix:after {
        content: "";
        display: table;
        clear: both;
    }
    
    /* Select2 styling */
    .select2-container--default .select2-selection--single {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        height: 44px;
        padding: 8px 12px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 8px;
    }
    
    .select2-container--default .select2-selection--single:focus {
        border-color: var(--primary);
    }
    
    /* Popover styling */
    .btn-link.btn-xs i {
        color: var(--primary);
        font-size: 16px;
    }
    
    /* Checkbox styling */
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 8px;
    }
    
    .checkbox-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-right: 0;
    }
    
    /* Validity dropdown styling */
    #validity_unit {
        background-color: white;
    }
</style>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-plus"></i>
                {Lang::T('Add New Service Package')}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{Text::url('services/add-post')}">
                    
                    <!-- Basic Settings Section -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: var(--primary-dark); margin-bottom: 20px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px;">
                            <i class="glyphicon glyphicon-cog"></i> Basic Settings
                        </h4>
                        
                        <!-- Status -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">
                                {Lang::T('Status')}
                                <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                    data-trigger="focus" data-container="body"
                                    data-content="{Lang::T('Customer cannot buy disabled packages. Admin can still recharge them.')}">
                                    <i class="fa fa-question-circle"></i>
                                </a>
                            </label>
                            <div class="col-md-10">
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="enabled" value="1" checked>
                                        <span class="label label-success" style="background: #22c55e;">{Lang::T('Active')}</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="enabled" value="0">
                                        <span class="label label-danger">{Lang::T('Not Active')}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Type (Prepaid/Postpaid) -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">
                                {Lang::T('Billing Type')}
                                <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                    data-trigger="focus" data-container="body"
                                    data-content="{Lang::T('Postpaid packages expire on a fixed date each month')}">
                                    <i class="fa fa-question-circle"></i>
                                </a>
                            </label>
                            <div class="col-md-10">
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="prepaid" onclick="prePaid()" value="yes" checked>
                                        {Lang::T('Prepaid')}
                                    </label>
                                    <label>
                                        <input type="radio" name="prepaid" onclick="postPaid()" value="no">
                                        {Lang::T('Postpaid')}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Package Type (Personal/Business) -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">
                                {Lang::T('Package Type')}
                                <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                    data-trigger="focus" data-container="body"
                                    data-content="{Lang::T('Personal: shows to personal customers. Business: shows to business customers.')}">
                                    <i class="fa fa-question-circle"></i>
                                </a>
                            </label>
                            <div class="col-md-10">
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="plan_type" value="Personal" checked>
                                        {Lang::T('Personal')}
                                    </label>
                                    <label>
                                        <input type="radio" name="plan_type" value="Business">
                                        {Lang::T('Business')}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Package Name -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">{Lang::T('Package Name')}</label>
                            <div class="col-md-6">
                                <input type="text" class="form-control" id="name" name="name" maxlength="40" 
                                    placeholder="Enter package name" required>
                            </div>
                        </div>
                    </div>

                    <!-- Device Configuration -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: var(--primary-dark); margin-bottom: 20px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px;">
                            <i class="glyphicon glyphicon-hdd"></i> Device Configuration
                        </h4>
                        
                        {if $_c['radius_enable']}
                            <!-- Radius Option -->
                            <div class="form-group clearfix">
                                <label class="col-md-2 control-label">{Lang::T('Radius')}</label>
                                <div class="col-md-6">
                                    <div class="checkbox-group">
                                        <label>
                                            <input type="checkbox" name="radius" onclick="isRadius(this)" value="1">
                                            {Lang::T('Enable Radius Package')}
                                        </label>
                                    </div>
                                    <p class="help-block">
                                        <i class="glyphicon glyphicon-info-sign"></i>
                                        {Lang::T('Cannot be changed after saving')}
                                    </p>
                                </div>
                            </div>
                        {/if}

                        {* Device is hard-wired to MikrotikHotspot for Hotspot plans to avoid user mistakes. *}
                        <input type="hidden" name="device" id="device" value="MikrotikHotspot">

                        <!-- Router Name -->
                        <span id="routerChoose">
                            <div class="form-group clearfix">
                                <label class="col-md-2 control-label">
                                    <a href="{Text::url('routers/add')}" target="_blank">{Lang::T('Router')}</a>
                                </label>
                                <div class="col-md-6">
                                    <select id="routers" name="routers" required class="form-control select2">
                                        <option value=''>{Lang::T('Select Router')}...</option>
                                        {foreach $r as $rs}
                                            <option value="{$rs['name']}">{$rs['name']}</option>
                                        {/foreach}
                                    </select>
                                    <p class="help-block">
                                        <i class="glyphicon glyphicon-info-sign"></i>
                                        {Lang::T('Cannot be changed after saving')}
                                    </p>
                                </div>
                            </div>
                        </span>
                    </div>

                    <!-- Package Limits -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: var(--primary-dark); margin-bottom: 20px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px;">
                            <i class="glyphicon glyphicon-dashboard"></i> Package Limits
                        </h4>
                        
                        <!-- Package Type (Unlimited/Limited) -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">{Lang::T('Package Type')}</label>
                            <div class="col-md-10">
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" id="Unlimited" name="typebp" value="Unlimited" checked>
                                        {Lang::T('Unlimited')}
                                    </label>
                                    <label>
                                        <input type="radio" id="Limited" name="typebp" value="Limited">
                                        {Lang::T('Limited')}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Limit Type (shows when Limited is selected) -->
                        <div style="display:none;" id="Type">
                            <div class="form-group clearfix">
                                <label class="col-md-2 control-label">{Lang::T('Limit Type')}</label>
                                <div class="col-md-10">
                                    <div class="radio-group">
                                        <label>
                                            <input type="radio" id="Time_Limit" name="limit_type" value="Time_Limit" checked>
                                            {Lang::T('Time Limit')}
                                        </label>
                                        <label>
                                            <input type="radio" id="Data_Limit" name="limit_type" value="Data_Limit">
                                            {Lang::T('Data Limit')}
                                        </label>
                                        <label>
                                            <input type="radio" id="Both_Limit" name="limit_type" value="Both_Limit">
                                            {Lang::T('Both Limit')}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Limit -->
                            <div style="display:none;" id="TimeLimit">
                                <div class="form-group clearfix">
                                    <label class="col-md-2 control-label">{Lang::T('Time Limit')}</label>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" id="time_limit" name="time_limit" 
                                            value="0" placeholder="Enter value">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" id="time_unit" name="time_unit">
                                            <option value="Hrs">{Lang::T('Hours')}</option>
                                            <option value="Mins">{Lang::T('Minutes')}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Data Limit -->
                            <div style="display:none;" id="DataLimit">
                                <div class="form-group clearfix">
                                    <label class="col-md-2 control-label">{Lang::T('Data Limit')}</label>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" id="data_limit" name="data_limit" 
                                            value="0" placeholder="Enter value">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" id="data_unit" name="data_unit">
                                            <option value="MB">MB</option>
                                            <option value="GB">GB</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shared Users -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">
                                {Lang::T('Shared Users')}
                                <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                    data-trigger="focus" data-container="body"
                                    data-content="{Lang::T('Maximum devices that can connect simultaneously per account')}">
                                    <i class="fa fa-question-circle"></i>
                                </a>
                            </label>
                            <div class="col-md-3">
                                <input type="number" class="form-control" id="sharedusers" name="sharedusers" 
                                    value="1" min="1" placeholder="e.g., 5">
                            </div>
                        </div>
                    </div>

                    <!-- Bandwidth & Pricing -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: var(--primary-dark); margin-bottom: 20px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px;">
                            <i class="glyphicon glyphicon-signal"></i> Bandwidth & Pricing
                        </h4>
                        
                        <!-- Bandwidth -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">
                                <a href="{Text::url('bandwidth/add')}" target="_blank">{Lang::T('Bandwidth')}</a>
                            </label>
                            <div class="col-md-6">
                                <select id="id_bw" name="id_bw" class="form-control select2" required>
                                    <option value="">{Lang::T('Select Bandwidth')}...</option>
                                    {foreach $d as $ds}
                                        <option value="{$ds['id']}">{$ds['name_bw']}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>

                        <!-- Package Price -->
                        <div class="form-group clearfix has-success">
                            <label class="col-md-2 control-label">{Lang::T('Package Price')}</label>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-addon">{$_c['currency_code']}</span>
                                    <input type="number" class="form-control" name="price" required step="0.01" min="0" 
                                        placeholder="0.00">
                                </div>
                            </div>
                            {if $_c['enable_tax'] == 'yes'}
                                <div class="col-md-4">
                                    <p class="help-block" style="margin-top: 0;">
                                        <i class="glyphicon glyphicon-info-sign"></i>
                                        {if $_c['tax_rate'] == 'custom'}
                                            {number_format($_c['custom_tax_rate'], 2)}% {Lang::T('Tax will be added')}
                                        {else}
                                            {number_format($_c['tax_rate'] * 100, 2)}% {Lang::T('Tax will be added')}
                                        {/if}
                                    </p>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Validity Section -->
                    <div style="margin-bottom: 30px;">
                        <h4 style="color: var(--primary-dark); margin-bottom: 20px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px;">
                            <i class="glyphicon glyphicon-time"></i> Validity Settings
                        </h4>
                        
                        <!-- Package Validity -->
                        <div class="form-group clearfix">
                            <label class="col-md-2 control-label">{Lang::T('Validity')}</label>
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="validity" name="validity" 
                                    placeholder="Enter value" required>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="validity_unit" name="validity_unit">
                                    <!-- Options will be populated by JavaScript -->
                                </select>
                            </div>
                           
                        </div>

                        <!-- Expired Date (for Postpaid) -->
                        <div class="form-group clearfix hidden" id="expired_date">
                            <label class="col-md-2 control-label">
                                {Lang::T('Expiry Day')}
                                <a tabindex="0" class="btn btn-link btn-xs" role="button" data-toggle="popover"
                                    data-trigger="focus" data-container="body"
                                    data-content="{Lang::T('Day of month when package expires (1-28)')}">
                                    <i class="fa fa-question-circle"></i>
                                </a>
                            </label>
                            <div class="col-md-3">
                                <input type="number" class="form-control" name="expired_date" value="20"
                                    min="1" max="28" step="1" placeholder="Day (1-28)">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-group clearfix">
                        <div class="col-md-offset-2 col-md-10">
                            <button type="submit" class="btn btn-success"
                                onclick="return ask(this, '{Lang::T('Create this new package?')}')">
                                <i class="glyphicon glyphicon-save"></i> {Lang::T('Create Package')}
                            </button>
                            <a href="{Text::url('services/hotspot')}" class="btn-link">
                                <i class="glyphicon glyphicon-remove"></i> {Lang::T('Cancel')}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Define the options for prepaid and postpaid
    var preOpt = '<option value="Mins">{Lang::T("Minutes")}</option>' +
                 '<option value="Hrs">{Lang::T("Hours")}</option>' +
                 '<option value="Days">{Lang::T("Days")}</option>' +
                 '<option value="Months">{Lang::T("Months")}</option>';
    
    var postOpt = '<option value="Period">{Lang::T("Period")}</option>';
    
    // Function for Prepaid selection
    function prePaid() {
        console.log('Prepaid selected');
        document.getElementById('validity_unit').innerHTML = preOpt;
        document.getElementById('expired_date').classList.add('hidden');
    }

    // Function for Postpaid selection
    function postPaid() {
        console.log('Postpaid selected');
        document.getElementById('validity_unit').innerHTML = postOpt;
        document.getElementById('expired_date').classList.remove('hidden');
    }
    
    // Wait for document to be ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded - initializing validity dropdown');
        
        // Initialize with Prepaid selected (default)
        prePaid();
        
        // Make sure jQuery is available
        if (typeof jQuery !== 'undefined') {
            // Handle Unlimited/Limited toggle
            $('input[name="typebp"]').on('change', function() {
                if ($(this).val() === 'Limited') {
                    $('#Type').slideDown(300);
                } else {
                    $('#Type').slideUp(300);
                    $('#TimeLimit, #DataLimit').slideUp(300);
                }
            });
            
            // Handle limit type changes
            $('input[name="limit_type"]').on('change', function() {
                $('#TimeLimit, #DataLimit').slideUp(300);
                
                switch($(this).val()) {
                    case 'Time_Limit':
                        $('#TimeLimit').slideDown(300);
                        break;
                    case 'Data_Limit':
                        $('#DataLimit').slideDown(300);
                        break;
                    case 'Both_Limit':
                        $('#TimeLimit, #DataLimit').slideDown(300);
                        break;
                }
            });
        }
    });
</script>

{if $_c['radius_enable']}
    <script>
        function isRadius(cek) {
            if (cek.checked) {
                $("#routerChoose").fadeOut(300, function() {
                    $(this).addClass('hidden');
                });
                document.getElementById("routers").required = false;
                document.getElementById("Limited").disabled = true;
            } else {
                $("#routerChoose").removeClass('hidden').fadeIn(300);
                document.getElementById("routers").required = true;
                document.getElementById("Limited").disabled = false;
            }
        }
    </script>
{/if}

{include file="sections/footer.tpl"}