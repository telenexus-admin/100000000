{include file="sections/header.tpl"}

<style>
    /* Custom Blue & Green Tab Styling */
    .nav-tabs.nav-justified > .active > a, 
    .nav-tabs.nav-justified > .active > a:focus, 
    .nav-tabs.nav-justified > .active > a:hover {
        background-color: #337ab7 !important;
        color: #ffffff !important;
        border: 1px solid #337ab7;
    }
    .nav-tabs.nav-justified > li > a {
        color: #337ab7;
        font-weight: bold;
        background: #f9f9f9;
        border: 1px solid #ddd;
    }
    .nav-tabs.nav-justified > li > a:hover {
        background-color: #dff0d8 !important;
        border-color: #d6e9c6;
    }
    .tab-content {
        border: 1px solid #ddd;
        border-top: transparent;
        padding: 20px;
        background: #fff;
        border-radius: 0 0 4px 4px;
    }
    
    /* Custom checkbox styling for notification sections */
    .notification-group {
        background: #f9f9f9;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    .notification-group:hover {
        border-color: #337ab7;
        box-shadow: 0 2px 8px rgba(51, 122, 183, 0.1);
    }
    .notification-group h4 {
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 2px solid;
        font-weight: 600;
    }
    .notification-group.hotspot h4 {
        color: #337ab7;
        border-bottom-color: #337ab7;
    }
    .notification-group.pppoe h4 {
        color: #5cb85c;
        border-bottom-color: #5cb85c;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .control-label {
        font-weight: 600;
        padding-top: 7px;
    }
    .alert-info {
        background-color: #d9edf7;
        border-color: #bce8f1;
        color: #31708f;
    }
    .alert-success {
        background-color: #dff0d8;
        border-color: #d6e9c6;
        color: #3c763d;
    }
    .btn-primary {
        background-color: #337ab7;
        border-color: #2e6da4;
    }
    .btn-primary:hover {
        background-color: #286090;
        border-color: #204d74;
    }
    .btn-lg {
        padding: 10px 16px;
        font-size: 18px;
        line-height: 1.3333333;
        border-radius: 6px;
    }
    .btn-block {
        display: block;
        width: 100%;
    }
    .input-group-btn .btn {
        height: 34px;
    }
    .text-muted {
        color: #777;
    }
    .mt-2 {
        margin-top: 10px;
    }
    .row {
        margin-left: -15px;
        margin-right: -15px;
    }
    .col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-7, .col-md-8, .col-md-10, .col-md-12 {
        position: relative;
        min-height: 1px;
        padding-left: 15px;
        padding-right: 15px;
    }
    .col-md-2 { width: 16.66666667%; float: left; }
    .col-md-3 { width: 25%; float: left; }
    .col-md-4 { width: 33.33333333%; float: left; }
    .col-md-6 { width: 50%; float: left; }
    .col-md-7 { width: 58.33333333%; float: left; }
    .col-md-8 { width: 66.66666667%; float: left; }
    .col-md-10 { width: 83.33333333%; float: left; }
    .col-md-12 { width: 100%; float: left; }
    
    @media (max-width: 768px) {
        .col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-7, .col-md-8, .col-md-10 {
            width: 100%;
            float: none;
            margin-bottom: 15px;
        }
        .control-label {
            padding-top: 0;
            margin-bottom: 5px;
        }
    }
    
    .form-control {
        display: block;
        width: 100%;
        height: 34px;
        padding: 6px 12px;
        font-size: 14px;
        line-height: 1.42857143;
        color: #555;
        background-color: #fff;
        background-image: none;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
        transition: border-color ease-in-out .15s, box-shadow ease-in-out .15s;
    }
    textarea.form-control {
        height: auto;
    }
    .form-control:focus {
        border-color: #66afe9;
        outline: 0;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(102,175,233,.6);
    }
    select.form-control {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23555' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 14px;
    }
    .input-group {
        position: relative;
        display: table;
        border-collapse: separate;
        width: 100%;
    }
    .input-group .form-control {
        position: relative;
        z-index: 2;
        float: left;
        width: 100%;
        margin-bottom: 0;
        display: table-cell;
    }
    .input-group-btn {
        position: relative;
        font-size: 0;
        white-space: nowrap;
        width: 1%;
        vertical-align: middle;
        display: table-cell;
    }
    .input-group-btn .btn {
        position: relative;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        margin-left: -1px;
        height: 34px;
    }
    .btn-success {
        color: #fff;
        background-color: #5cb85c;
        border-color: #4cae4c;
    }
    .btn-success:hover {
        background-color: #449d44;
        border-color: #398439;
    }
    .pull-right {
        float: right;
    }
    .clearfix:before,
    .clearfix:after {
        display: table;
        content: " ";
    }
    .clearfix:after {
        clear: both;
    }
    
    /* Loading animation for submit button */
    .loading {
        pointer-events: none;
        opacity: 0.7;
    }
    .loading::after {
        content: "";
        display: inline-block;
        width: 16px;
        height: 16px;
        vertical-align: middle;
        margin-left: 10px;
        border: 2px solid #fff;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.8s infinite linear;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="row">
    <div class="col-sm-12">
        <ul class="nav nav-tabs nav-justified" role="tablist">
            <li role="presentation" class="active">
                <a href="#general" aria-controls="general" role="tab" data-toggle="tab">
                    <i class="glyphicon glyphicon-cog"></i> General
                </a>
            </li>
            <li role="presentation">
                <a href="#gateways" aria-controls="gateways" role="tab" data-toggle="tab">
                    <i class="glyphicon glyphicon-transfer"></i> Gateways
                </a>
            </li>
            <li role="presentation">
                <a href="#notifications" aria-controls="notifications" role="tab" data-toggle="tab">
                    <i class="glyphicon glyphicon-bullhorn"></i> Notifications
                </a>
            </li>
        </ul>

        <form class="form-horizontal" method="post" role="form" action="{Text::url('settings/app-post')}" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            
            <div class="tab-content">
                
                <!-- ==================== TAB 1: GENERAL SETTINGS ==================== -->
                <div role="tabpanel" class="tab-pane active" id="general">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('App / Company Name')}</label>
                        <div class="col-md-7">
                            <input type="text" required class="form-control" name="CompanyName" value="{$_c['CompanyName']}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Company Logo')}</label>
                        <div class="col-md-7">
                            <input type="file" class="form-control" name="logo" accept="image/*">
                            {if $_c['logo']}
                                <div class="mt-2" style="margin-top: 10px;">
                                    <a href="./system/uploads/{$_c['logo']}" target="_blank">
                                        <img src="./system/uploads/{$_c['logo']}" style="max-height: 50px; border: 1px solid #ddd;" alt="Logo">
                                    </a>
                                </div>
                            {/if}
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Company Footer')}</label>
                        <div class="col-md-7">
                            <input type="text" class="form-control" name="CompanyFooter" value="{$_c['CompanyFooter']}">
                        </div>
                        <div class="col-md-2 help-block">{Lang::T('Shown below user pages')}</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Address')}</label>
                        <div class="col-md-7">
                            <textarea class="form-control" name="address" rows="2">{$_c['address']}</textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Phone Number')}</label>
                        <div class="col-md-7">
                            <input type="text" class="form-control" name="phone" value="{$_c['phone']}">
                        </div>
                    </div>
                    
                  
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Enable Session Timeout')}</label>
                        <div class="col-md-7">
                            <label class="switch" style="position: relative; display: inline-block; width: 50px; height: 26px;">
                                <input type="checkbox" name="enable_session_timeout" value="1" {if $_c['enable_session_timeout']==1}checked{/if} onchange="toggleTimeoutDuration()" style="opacity: 0; width: 0; height: 0;">
                                <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;"></span>
                            </label>
                        </div>
                        <div class="col-md-2 help-block">{Lang::T('Logout admin after inactivity')}</div>
                    </div>
                    
                    <div class="form-group" id="timeout_duration_row" style="display: {if $_c['enable_session_timeout']==1}block{else}none{/if};">
                        <label class="col-md-3 control-label">{Lang::T('Timeout Duration (minutes)')}</label>
                        <div class="col-md-7">
                            <input type="number" name="session_timeout_duration" value="{$_c['session_timeout_duration']}" class="form-control" min="1">
                        </div>
                    </div>
                </div>

                <!-- ==================== TAB 2: GATEWAYS (SMS & WhatsApp) ==================== -->
                <div role="tabpanel" class="tab-pane" id="gateways">
                    <div class="alert alert-info" style="background-color: #d9edf7; border-color: #bce8f1; color: #31708f;">
                        <i class="glyphicon glyphicon-info-sign"></i> {Lang::T('Configure your API endpoints here. Use')} <b>[number]</b> {Lang::T('and')} <b>[text]</b> {Lang::T('as variables.')}
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('SMS Gateway')}</label>
                        <div class="col-md-10">
                            <select name="sms_gateway_type" id="sms_gateway_type" class="form-control" onchange="toggleSmsGateway()">
                                <option value="url" {if !$_c['sms_gateway_type'] || $_c['sms_gateway_type']=='url'}selected{/if}>{Lang::T('SMS URL')}</option>
                                <option value="blessedtexts" {if $_c['sms_gateway_type']=='blessedtexts'}selected{/if}>Blessed Texts</option>
                                <option value="africastalking" {if $_c['sms_gateway_type']=='africastalking'}selected{/if}>{Lang::T('Africa\'s Talking')}</option>
                                <option value="talksasa" {if $_c['sms_gateway_type']=='talksasa'}selected{/if}>{Lang::T('TalkSasa')}</option>
                                <option value="umscomms" {if $_c['sms_gateway_type']=='umscomms'}selected{/if}>{Lang::T('UMS Comms')}</option>
                            </select>
                            <p class="help-block">Blessed Texts credentials are managed under <b>Settings → SMS Gateway</b> plugin page (API Key + Sender ID).</p>
                        </div>
                    </div>
                    
                    <div id="sms_url_section">
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('SMS URL')}</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sms_url" name="sms_url" value="{$_c['sms_url']}" placeholder="https://api.com/send?to=[number]&msg=[text]">
                                    <span class="input-group-btn">
                                        <button class="btn btn-success" type="button" onclick="testSms()">{Lang::T('Test')}</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="africastalking_section" style="display: none;">
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('Username')}</label>
                            <div class="col-md-10"><input type="text" class="form-control" name="africastalking_username" value="{$_c['africastalking_username']}"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('API Key')}</label>
                            <div class="col-md-10"><input type="password" class="form-control" name="africastalking_api_key" value="{$_c['africastalking_api_key']}"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('Sender ID')}</label>
                            <div class="col-md-10"><input type="text" class="form-control" name="africastalking_sender_id" value="{$_c['africastalking_sender_id']}"></div>
                        </div>
                    </div>
                    
                    <div id="talksasa_section" style="display: none;">
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('API Key')}</label>
                            <div class="col-md-10"><input type="password" class="form-control" name="talksasa_api_key" value="{$_c['talksasa_api_key']}"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('Sender ID')}</label>
                            <div class="col-md-10"><input type="text" class="form-control" name="talksasa_sender_id" value="{$_c['talksasa_sender_id']}"></div>
                        </div>
                    </div>
                    
                    <div id="umscomms_section" style="display: none;">
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('API Key')}</label>
                            <div class="col-md-10"><input type="password" class="form-control" name="umscomms_api_key" value="{$_c['umscomms_api_key']}"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('App ID')}</label>
                            <div class="col-md-10"><input type="text" class="form-control" name="umscomms_app_id" value="{$_c['umscomms_app_id']}"></div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-2 control-label">{Lang::T('Sender ID')}</label>
                            <div class="col-md-10"><input type="text" class="form-control" name="umscomms_sender_id" value="{$_c['umscomms_sender_id']}"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('WhatsApp URL')}</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="text" class="form-control" id="wa_url" name="wa_url" value="{$_c['wa_url']}" placeholder="https://wa.api.com/send?phone=[number]&text=[text]">
                                <span class="input-group-btn">
                                    <button class="btn btn-success" type="button" onclick="testWa()">{Lang::T('Test')}</button>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                                   
                    
                </div>

                <!-- ==================== TAB 3: NOTIFICATIONS (Hotspot & PPPoE) ==================== -->
                <div role="tabpanel" class="tab-pane" id="notifications">
                    <div class="row">
                        <!-- Hotspot Notifications -->
                        <div class="col-md-6">
                            <div class="notification-group hotspot">
                                <h4>
                                    <i class="glyphicon glyphicon-wifi"></i> {Lang::T('Hotspot Customers')}
                                </h4>
                                <div class="form-group">
                                    <label class="col-md-4 control-label">{Lang::T('Expired Notification')}</label>
                                    <div class="col-md-8">
                                        <select name="user_notification_expired_hotspot" class="form-control">
                                            <option value="none" {if $_c['user_notification_expired_hotspot']=='none'}selected{/if}>{Lang::T('None')}</option>
                                            <option value="wa" {if $_c['user_notification_expired_hotspot']=='wa'}selected{/if}>{Lang::T('WhatsApp')}</option>
                                            <option value="sms" {if $_c['user_notification_expired_hotspot']=='sms'}selected{/if}>{Lang::T('SMS')}</option>
                                            <option value="email" {if $_c['user_notification_expired_hotspot']=='email'}selected{/if}>{Lang::T('Email')}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label">{Lang::T('Payment Notification')}</label>
                                    <div class="col-md-8">
                                        <select name="user_notification_payment_hotspot" class="form-control">
                                            <option value="none" {if $_c['user_notification_payment_hotspot']=='none'}selected{/if}>{Lang::T('None')}</option>
                                            <option value="wa" {if $_c['user_notification_payment_hotspot']=='wa'}selected{/if}>{Lang::T('WhatsApp')}</option>
                                            <option value="sms" {if $_c['user_notification_payment_hotspot']=='sms'}selected{/if}>{Lang::T('SMS')}</option>
                                            <option value="email" {if $_c['user_notification_payment_hotspot']=='email'}selected{/if}>{Lang::T('Email')}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label">{Lang::T('Reminder Notification')}</label>
                                    <div class="col-md-8">
                                        <select name="user_notification_reminder_hotspot" class="form-control">
                                            <option value="none" {if $_c['user_notification_reminder_hotspot']=='none'}selected{/if}>{Lang::T('None')}</option>
                                            <option value="wa" {if $_c['user_notification_reminder_hotspot']=='wa'}selected{/if}>{Lang::T('WhatsApp')}</option>
                                            <option value="sms" {if $_c['user_notification_reminder_hotspot']=='sms'}selected{/if}>{Lang::T('SMS')}</option>
                                            <option value="email" {if $_c['user_notification_reminder_hotspot']=='email'}selected{/if}>{Lang::T('Email')}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- PPPoE Notifications -->
                        <div class="col-md-6">
                            <div class="notification-group pppoe">
                                <h4>
                                    <i class="glyphicon glyphicon-transfer"></i> {Lang::T('PPPoE Customers')}
                                </h4>
                                <div class="form-group">
                                    <label class="col-md-4 control-label">{Lang::T('Expired Notification')}</label>
                                    <div class="col-md-8">
                                        <select name="user_notification_expired_pppoe" class="form-control">
                                            <option value="none" {if $_c['user_notification_expired_pppoe']=='none'}selected{/if}>{Lang::T('None')}</option>
                                            <option value="wa" {if $_c['user_notification_expired_pppoe']=='wa'}selected{/if}>{Lang::T('WhatsApp')}</option>
                                            <option value="sms" {if $_c['user_notification_expired_pppoe']=='sms'}selected{/if}>{Lang::T('SMS')}</option>
                                            <option value="email" {if $_c['user_notification_expired_pppoe']=='email'}selected{/if}>{Lang::T('Email')}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label">{Lang::T('Payment Notification')}</label>
                                    <div class="col-md-8">
                                        <select name="user_notification_payment_pppoe" class="form-control">
                                            <option value="none" {if $_c['user_notification_payment_pppoe']=='none'}selected{/if}>{Lang::T('None')}</option>
                                            <option value="wa" {if $_c['user_notification_payment_pppoe']=='wa'}selected{/if}>{Lang::T('WhatsApp')}</option>
                                            <option value="sms" {if $_c['user_notification_payment_pppoe']=='sms'}selected{/if}>{Lang::T('SMS')}</option>
                                            <option value="email" {if $_c['user_notification_payment_pppoe']=='email'}selected{/if}>{Lang::T('Email')}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label">{Lang::T('Reminder Notification')}</label>
                                    <div class="col-md-8">
                                        <select name="user_notification_reminder_pppoe" class="form-control">
                                            <option value="none" {if $_c['user_notification_reminder_pppoe']=='none'}selected{/if}>{Lang::T('None')}</option>
                                            <option value="wa" {if $_c['user_notification_reminder_pppoe']=='wa'}selected{/if}>{Lang::T('WhatsApp')}</option>
                                            <option value="sms" {if $_c['user_notification_reminder_pppoe']=='sms'}selected{/if}>{Lang::T('SMS')}</option>
                                            <option value="email" {if $_c['user_notification_reminder_pppoe']=='email'}selected{/if}>{Lang::T('Email')}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="col-md-3 control-label">{Lang::T('Reminder Intervals')}</label>
                                <div class="col-md-9">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="notification_reminder_1day" value="yes" {if !isset($_c['notification_reminder_1day']) || $_c['notification_reminder_1day'] neq 'no'}checked{/if}> {Lang::T('1 Day Before')}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="notification_reminder_3days" value="yes" {if !isset($_c['notification_reminder_3days']) || $_c['notification_reminder_3days'] neq 'no'}checked{/if}> {Lang::T('3 Days Before')}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="notification_reminder_7days" value="yes" {if !isset($_c['notification_reminder_7days']) || $_c['notification_reminder_7days'] neq 'no'}checked{/if}> {Lang::T('7 Days Before')}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                                 </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <div class="col-md-12">
                    <button class="btn btn-primary btn-lg btn-block" type="submit">
                        <i class="glyphicon glyphicon-floppy-disk"></i> {Lang::T('Save All Settings')}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Toggle timeout duration input visibility
    function toggleTimeoutDuration() {
        var checkbox = document.querySelector('input[name="enable_session_timeout"]');
        var row = document.getElementById('timeout_duration_row');
        if (row) {
            row.style.display = (checkbox && checkbox.checked) ? 'block' : 'none';
        }
    }
    
    // Toggle SMS gateway sections
    function toggleSmsGateway() {
        var type = document.getElementById('sms_gateway_type').value;
        document.getElementById('sms_url_section').style.display = (type === 'url') ? 'block' : 'none';
        document.getElementById('africastalking_section').style.display = (type === 'africastalking') ? 'block' : 'none';
        document.getElementById('talksasa_section').style.display = (type === 'talksasa') ? 'block' : 'none';
        document.getElementById('umscomms_section').style.display = (type === 'umscomms') ? 'block' : 'none';
    }
    
    // Test WhatsApp function
    function testWa() {
        var target = prompt("{Lang::T('Enter Phone number (with country code)')}\n{Lang::T('Save settings first!')}", "");
        if (target != null && target != "") {
            window.location.href = '{Text::url('settings/app&testWa=')}' + encodeURIComponent(target);
        }
    }
    
    // Test SMS function
    function testSms() {
        var target = prompt("{Lang::T('Enter Phone number')}\n{Lang::T('Save settings first!')}", "");
        if (target != null && target != "") {
            window.location.href = '{Text::url('settings/app&testSms=')}' + encodeURIComponent(target);
        }
    }
    
    // Submit button loading animation
    document.querySelectorAll('button[type="submit"]').forEach(function(el) {
        el.addEventListener("click", function() {
            this.innerHTML = '<span class="loading"></span> ' + this.innerText;
        });
    });
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleTimeoutDuration();
        toggleSmsGateway();
        
        // Check URL parameters for test results
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('testSms') || urlParams.has('testWa')) {
            var success = urlParams.get('success');
            var type = urlParams.has('testSms') ? 'SMS' : 'WhatsApp';
            if (success === '1') {
                alert(type + ' test sent successfully!');
            } else if (success === '0') {
                alert(type + ' test failed. Please check your configuration.');
            }
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });
    
    // Custom switch styling
    var switches = document.querySelectorAll('.switch input');
    switches.forEach(function(switchInput) {
        var slider = switchInput.nextElementSibling;
        if (switchInput.checked) {
            slider.style.backgroundColor = '#5cb85c';
        } else {
            slider.style.backgroundColor = '#ccc';
        }
        switchInput.addEventListener('change', function() {
            if (this.checked) {
                slider.style.backgroundColor = '#5cb85c';
            } else {
                slider.style.backgroundColor = '#ccc';
            }
        });
    });
</script>

<style>
    /* Additional styles for switch */
    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .switch input:checked + .slider:before {
        transform: translateX(24px);
    }
    .checkbox-inline {
        margin-right: 15px;
        padding-left: 20px;
        font-weight: normal;
        cursor: pointer;
    }
    .checkbox-inline input {
        margin-left: -20px;
        margin-top: 2px;
    }
</style>

{include file="sections/footer.tpl"}