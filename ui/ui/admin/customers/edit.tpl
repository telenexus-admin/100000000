{include file="sections/header.tpl"}

<style>
    :root {
        --primary: #f97316;
        --primary-dark: #ea580c;
        --primary-light: #fed7aa;
        --primary-soft: #fff7ed;
        --primary-hover: #fb923c;
    }

    /* Panel styling */
    .panel-primary {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
    }
    
    .panel-primary > .panel-heading {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: white;
        border-color: var(--primary-dark);
        font-weight: 600;
        padding: 12px 15px;
    }
    
    .panel-danger {
        border-color: #ef4444;
    }
    
    .panel-danger > .panel-heading {
        background: linear-gradient(145deg, #ef4444, #dc2626);
        color: white;
        border-color: #dc2626;
        font-weight: 600;
    }
    
    .panel {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transition: all 0.3s;
    }
    
    .panel:hover {
        box-shadow: 0 8px 24px rgba(249, 115, 22, 0.15);
    }
    
    .panel-body {
        padding: 20px;
        background: white;
    }
    
    .panel-footer {
        background: var(--primary-soft);
        border-top: 1px solid var(--primary-light);
        padding: 15px 20px;
    }

    /* Box styling */
    .box-primary {
        border-color: var(--primary);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
    }
    
    .box-primary .box-header {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: white;
        border-bottom: 1px solid var(--primary-dark);
        padding: 12px 15px;
    }
    
    .box-primary .box-title {
        font-weight: 600;
    }
    
    .box-primary .box-tools .btn-box-tool {
        color: white;
    }
    
    .box-primary .box-body {
        background: white;
        padding: 20px;
    }

    /* Form controls */
    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 12px;
        transition: all 0.2s;
        box-shadow: none;
        height: 40px;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        outline: none;
    }
    
    textarea.form-control {
        height: auto;
        min-height: 80px;
    }
    
    .input-group {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .input-group-addon {
        background: var(--primary-soft);
        border: 2px solid #e2e8f0;
        border-right: none;
        color: var(--primary);
        font-weight: 500;
        padding: 8px 12px;
    }
    
    .input-group .form-control {
        border-left: none;
    }
    
    /* Labels */
    .control-label {
        font-weight: 600;
        color: #1e293b;
        padding-top: 8px;
    }
    
    .label-danger {
        background: #ef4444;
        color: white;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
    
    .label-warning {
        background: var(--primary);
        color: white;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .help-block {
        color: #64748b;
        font-size: 12px;
        margin-top: 5px;
        margin-bottom: 0;
    }

    /* Buttons */
    .btn-primary {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        color: white;
        font-weight: 500;
        border-radius: 30px;
        padding: 10px 30px;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(249, 115, 22, 0.4);
    }
    
    .btn-success {
        background: var(--primary);
        border: none;
        color: white;
        font-weight: 500;
        border-radius: 30px;
        padding: 8px 20px;
        transition: all 0.2s;
    }
    
    .btn-success:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
    }
    
    .btn-danger {
        background: #ef4444;
        border: none;
        color: white;
        font-weight: 500;
        border-radius: 30px;
        padding: 6px 12px;
        transition: all 0.2s;
    }
    
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }
    
    .btn-link {
        color: var(--primary);
        font-weight: 500;
    }
    
    .btn-link:hover {
        color: var(--primary-dark);
        text-decoration: none;
    }
    
    .btn-box-tool {
        background: transparent;
        border: none;
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
    }
    
    .btn-box-tool:hover {
        background: rgba(255,255,255,0.2);
    }

    /* Image styling */
    .img-circle {
        border: 4px solid var(--primary-light);
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .img-circle:hover {
        transform: scale(1.02);
        border-color: var(--primary);
        box-shadow: 0 8px 24px rgba(249, 115, 22, 0.3);
    }

    /* Map styling */
    #map {
        border-radius: 12px;
        border: 2px solid var(--primary-light);
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
    }
    
    /* Select dropdowns */
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23f97316' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 40px;
    }

    /* Custom fields container */
    #custom-fields-container .form-group {
        background: var(--primary-soft);
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 15px;
        border: 1px solid var(--primary-light);
    }
    
    #custom-fields-container .form-group:last-child {
        margin-bottom: 0;
    }
    
    /* Checkbox styling */
    input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary);
        border-radius: 4px;
        margin-right: 5px;
        vertical-align: middle;
    }
    
    /* Additional Information section */
    .box-header .fa-plus {
        transition: transform 0.3s;
    }
    
    .box-header .collapsed-box .fa-plus {
        transform: rotate(0deg);
    }
    
    .box-header:not(.collapsed-box) .fa-plus {
        transform: rotate(45deg);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .control-label {
            margin-bottom: 5px;
        }
        
        .panel-body {
            padding: 15px;
        }
        
        .btn-primary {
            width: 100%;
        }
    }

    /* Loading animation */
    .loading {
        position: relative;
        pointer-events: none;
        opacity: 0.7;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 30px;
        height: 30px;
        margin: -15px 0 0 -15px;
        border: 3px solid rgba(249, 115, 22, 0.2);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: var(--primary-soft);
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-dark);
    }

    /* Form validation */
    .has-error .form-control {
        border-color: #ef4444;
    }
    
    .has-error .form-control:focus {
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    
    .has-success .form-control {
        border-color: #22c55e;
    }
    
    .has-success .form-control:focus {
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
    }
</style>

<form class="form-horizontal" enctype="multipart/form-data" method="post" role="form" action="{Text::url('customers/edit-post')}">
    <input type="hidden" name="csrf_token" value="{$csrf_token}">
    <div class="row">
        <div class="col-md-6">
            <div
                class="panel panel-{if $d['status']=='Active'}primary{else}danger{/if} panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <i class="glyphicon glyphicon-user" style="margin-right: 8px;"></i>
                    {Lang::T('Edit Contact')}
                </div>
                <div class="panel-body">
                    <center>
                        <img src="{$app_url}/{$UPLOAD_PATH}{$d['photo']}.thumb.jpg" width="200"
                            onerror="this.src='{$app_url}/{$UPLOAD_PATH}/user.default.jpg'" class="img-circle img-responsive"
                            alt="Photo" onclick="return deletePhoto({$d['id']})">
                    </center><br>
                    <input type="hidden" name="id" value="{$d['id']}">
                    <div class="form-group">
                        <label class="col-md-3 col-xs-12 control-label">{Lang::T('Photo')}</label>
                        <div class="col-md-6 col-xs-8">
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>
                        <div class="form-group col-md-3 col-xs-4" title="Not always Working">
                            <label class=""><input type="checkbox" checked name="faceDetect" value="yes"> {Lang::T("Face Detection")}</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Usernames')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                {if $_c['country_code_phone']!= ''}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-phone-alt"></i></span>
                                {else}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-user"></i></span>
                                {/if}
                                <input type="text" class="form-control" name="username" value="{$d['username']}"
                                    required
                                    placeholder="{if $_c['country_code_phone']!= ''}{$_c['country_code_phone']} {Lang::T('Phone Number')}{else}{Lang::T('Usernames')}{/if}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Full Name')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="fullname" name="fullname"
                                value="{$d['fullname']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Email')}</label>
                        <div class="col-md-9">
                            <input type="email" class="form-control" id="email" name="email" value="{$d['email']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Phone Number')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                {if $_c['country_code_phone']!= ''}
                                    <span class="input-group-addon" id="basic-addon1">+</span>
                                {else}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-phone-alt"></i></span>
                                {/if}
                                <input type="text" class="form-control" name="phonenumber" value="{$d['phonenumber']}"
                                    placeholder="{if $_c['country_code_phone']!= ''}{$_c['country_code_phone']}{/if} {Lang::T('Phone Number')}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Password')}</label>
                        <div class="col-md-9">
                            <input type="password" autocomplete="off" class="form-control" id="password" name="password"
                                onmouseleave="this.type = 'password'" onmouseenter="this.type = 'text'"
                                value="{$d['password']}">
                            <span class="help-block"><i class="glyphicon glyphicon-info-sign" style="color: var(--primary);"></i> {Lang::T('Keep Blank to do not change Password')}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Home Address')}</label>
                        <div class="col-md-9">
                            <textarea name="address" id="address" class="form-control">{$d['address']}</textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Service Type')}</label>
                        <div class="col-md-9">
                            <select class="form-control" id="service_type" name="service_type">
                                <option value="Hotspot" {if $d['service_type'] eq 'Hotspot' }selected{/if}>Hotspot
                                </option>
                                <option value="PPPoE" {if $d['service_type'] eq 'PPPoE' }selected{/if}>PPPoE</option>
                                                           </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Account Type')}</label>
                        <div class="col-md-9">
                            <select class="form-control" id="account_type" name="account_type">
                                <option value="Personal" {if $d['account_type'] eq 'Personal' }selected{/if}>{Lang::T("Personal")}
                                </option>
                                <option value="Business" {if $d['account_type'] eq 'Business' }selected{/if}>{Lang::T("Business")}
                                </option>
                            </select>
                        </div>
                    </div>
                                       <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Status')}</label>
                        <div class="col-md-9">
                            <select class="form-control" id="status" name="status">
                                {foreach $statuses as $status}
                                    <option value="{$status}" {if $d['status'] eq $status }selected{/if}>{Lang::T($status)}
                                    </option>
                                {/foreach}
                            </select>
                            <span class="help-block">
                                <i class="glyphicon glyphicon-info-sign" style="color: var(--primary);"></i>
                                {Lang::T('Banned')}: {Lang::T('Customer cannot login again')}.<br>
                                {Lang::T('Disabled')}:
                                {Lang::T('Customer can login but cannot buy internet package, Admin cannot recharge customer')}.<br>
                                {Lang::T("Don't forget to deactivate all active package too")}.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="panel-heading">
                    <i class="glyphicon glyphicon-signal" style="margin-right: 8px;"></i>
                    PPPoE
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Usernames')} <span class="label label-danger"
                                id="warning_username"></span></label>
                        <div class="col-md-9">
                            <input type="username" class="form-control" id="pppoe_username" name="pppoe_username"
                                onkeyup="checkUsername(this, {$d['id']})" value="{$d['pppoe_username']}">
                                                   </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Password')}</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" id="pppoe_password" name="pppoe_password"
                                value="{$d['pppoe_password']}" onmouseleave="this.type = 'password'"
                                onmouseenter="this.type = 'text'">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Remote IP <span class="label label-danger"
                                id="warning_ip"></span></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="pppoe_ip" name="pppoe_ip"
                                onkeyup="checkIP(this, {$d['id']})" value="{$d['pppoe_ip']}">
                                                   </div>
                    </div>
                   
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <i class="glyphicon glyphicon-tags" style="margin-right: 8px;"></i>
                    {Lang::T('Attributes')}
                </div>
                <div class="panel-body">
                    <!--Customers Attributes edit start -->
                    {if $customFields}
                        {foreach $customFields as $customField}
                            <div class="form-group">
                                <label class="col-md-4 control-label"
                                    for="{$customField.field_name}">{$customField.field_name}</label>
                                <div class="col-md-6">
                                    <input class="form-control" type="text" name="custom_fields[{$customField.field_name}]"
                                        id="{$customField.field_name}" value="{$customField.field_value}">
                                </div>
                                <label class="col-md-2">
                                    <input type="checkbox" name="delete_custom_fields[]" value="{$customField.field_name}">
                                    {Lang::T('Delete')}
                                </label>
                            </div>
                        {/foreach}
                    {/if}
                    <!--Customers Attributes edit end -->
                    <!-- Customers Attributes add start -->
                    <div id="custom-fields-container">
                    </div>
                    <!-- Customers Attributes add end -->
                </div>
                <div class="panel-footer">
                    <button class="btn btn-success btn-block" type="button"
                        id="add-custom-field">
                        <i class="glyphicon glyphicon-plus" style="margin-right: 5px;"></i>
                        {Lang::T('Add Attribute')}
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="glyphicon glyphicon-info-sign" style="margin-right: 8px;"></i>
                        {Lang::T('Additional Information')}
                    </h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body" style="display: none;">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('City')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="city" name="city" value="{$d['city']}">
                            <small class="form-text text-muted"><i class="glyphicon glyphicon-map-marker" style="color: var(--primary);"></i> {Lang::T('City of Resident')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('District')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="district" name="district"
                                value="{$d['district']}">
                            <small class="form-text text-muted"><i class="glyphicon glyphicon-map-marker" style="color: var(--primary);"></i> {Lang::T('District')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('State')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="state" name="state" value="{$d['state']}">
                            <small class="form-text text-muted"><i class="glyphicon glyphicon-map-marker" style="color: var(--primary);"></i> {Lang::T('State of Resident')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Zip Code')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="zip" name="zip" value="{$d['zip']}">
                            <small class="form-text text-muted"><i class="glyphicon glyphicon-envelope" style="color: var(--primary);"></i> {Lang::T('Zip Code')}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <center style="margin: 30px 0;">
        <button class="btn btn-primary" onclick="return ask(this, '{Lang::T("Continue the Customer Data change process?")}')"
            type="submit">
            <i class="glyphicon glyphicon-save" style="margin-right: 8px;"></i>
            {Lang::T('Save Changes')}
        </button>
        <br><a href="{Text::url('')}customers/list" class="btn btn-link">
            <i class="glyphicon glyphicon-remove" style="margin-right: 5px;"></i>
            {Lang::T('Cancel')}
        </a>
    </center>
</form>

{literal}
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            var customFieldsContainer = document.getElementById('custom-fields-container');
            var addCustomFieldButton = document.getElementById('add-custom-field');

            addCustomFieldButton.addEventListener('click', function() {
                var fieldIndex = customFieldsContainer.children.length;
                var newField = document.createElement('div');
                newField.className = 'form-group';
                newField.innerHTML = `
                <div class="col-md-4">
                    <input type="text" class="form-control" name="custom_field_name[]" placeholder="Attribute Name">
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="custom_field_value[]" placeholder="Attribute Value">
                </div>
                <div class="col-md-2">
                    <button type="button" class="remove-custom-field btn btn-danger btn-sm">
                        <i class="glyphicon glyphicon-trash"></i>
                    </button>
                </div>
            `;
                customFieldsContainer.appendChild(newField);
            });

            customFieldsContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-custom-field') || event.target.parentElement.classList.contains('remove-custom-field')) {
                    var button = event.target.classList.contains('remove-custom-field') ? event.target : event.target.parentElement;
                    var fieldContainer = button.parentNode.parentNode;
                    fieldContainer.parentNode.removeChild(fieldContainer);
                }
            });
        });
    </script>

    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script>
        function getLocation() {
            if (window.location.protocol == "https:" && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition);
            } else {
                setupMap(51.505, -0.09);
            }
        }

        function showPosition(position) {
            setupMap(position.coords.latitude, position.coords.longitude);
        }

        function setupMap(lat, lon) {
            var map = L.map('map').setView([lat, lon], 13);
            L.tileLayer('https://{s}.google.com/vt/lyrs=m&hl=en&x={x}&y={y}&z={z}&s=Ga', {
                subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
                maxZoom: 20
            }).addTo(map);
            
            var marker = L.marker([lat, lon]).addTo(map);
            
            map.on('click', function(e) {
                var coord = e.latlng;
                var lat = coord.lat;
                var lng = coord.lng;
                var newLatLng = new L.LatLng(lat, lng);
                marker.setLatLng(newLatLng);
                $('#coordinates').val(lat.toFixed(6) + ',' + lng.toFixed(6));
                
                // Add animation to marker
                marker.setOpacity(0.5);
                setTimeout(function() {
                    marker.setOpacity(1);
                }, 200);
            });
        }
        
        window.onload = function() {
        {/literal}
        {if $d['coordinates']}
            setupMap({$d['coordinates']});
        {else}
            getLocation();
        {/if}
        {literal}
        }
    </script>
{/literal}

<script>
    function deletePhoto(id) {
        if (confirm('Delete photo?')) {
            if (confirm('Are you sure to delete photo?')) {
                window.location.href = '{Text::url('')}customers/edit/'+id+'/deletePhoto';
            }
        }
    }
    
    function checkUsername(element, id) {
        var username = element.value;
        if (username.length > 3) {
            $.ajax({
                url: '{Text::url('')}autoload/check_pppoe_username',
                type: 'POST',
                data: { username: username, id: id },
                success: function(data) {
                    if (data == 'exists') {
                        $('#warning_username').text('Username already exists!');
                        $('#warning_username').addClass('label-danger').removeClass('label-success');
                    } else {
                        $('#warning_username').text('Available');
                        $('#warning_username').addClass('label-success').removeClass('label-danger');
                    }
                }
            });
        }
    }
    
    function checkIP(element, id) {
        var ip = element.value;
        if (ip.length > 7) {
            $.ajax({
                url: '{Text::url('')}autoload/check_pppoe_ip',
                type: 'POST',
                data: { ip: ip, id: id },
                success: function(data) {
                    if (data == 'exists') {
                        $('#warning_ip').text('IP already in use!');
                        $('#warning_ip').addClass('label-danger').removeClass('label-success');
                    } else {
                        $('#warning_ip').text('Available');
                        $('#warning_ip').addClass('label-success').removeClass('label-danger');
                    }
                }
            });
        }
    }
</script>

{include file="sections/footer.tpl"}