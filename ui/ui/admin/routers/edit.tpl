{include file="sections/header.tpl"}
<!-- routers-edit -->

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
        font-size: 16px;
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
        box-shadow: none;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        outline: none;
    }
    
    textarea.form-control {
        height: auto;
        min-height: 80px;
        resize: vertical;
    }
    
    .control-label {
        font-weight: 600;
        color: #1e293b;
        padding-top: 8px;
    }
    
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
    
    .btn-warning {
        background: #fef9c3;
        border: 1px solid #eab308;
        color: #854d0e;
        border-radius: 8px;
        padding: 8px 15px;
        transition: all 0.2s;
    }
    
    .btn-warning:hover {
        background: #eab308;
        color: white;
    }
    
    .btn-danger {
        background: #ef4444;
        border: none;
        color: white;
        border-radius: 8px;
        padding: 8px 15px;
        transition: all 0.2s;
    }
    
    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    a {
        color: var(--primary);
        font-weight: 500;
        transition: all 0.2s;
    }
    
    a:hover {
        color: var(--primary-dark);
        text-decoration: none;
    }
    
    .help-block {
        color: #64748b;
        font-size: 12px;
        margin-top: 5px;
    }
    
    .help-block i {
        color: var(--primary);
        margin-right: 5px;
    }
    
    .radio-inline {
        margin-right: 20px;
        font-weight: 500;
        color: #1e293b;
    }
    
    input[type="radio"] {
        accent-color: var(--primary);
        width: 16px;
        height: 16px;
        margin-right: 5px;
    }
    
    .input-group {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .input-group-addon {
        background: var(--primary-soft);
        border: 2px solid #e2e8f0;
        border-left: none;
        color: var(--primary);
        font-weight: 500;
        padding: 8px 12px;
    }
    
    .input-group .form-control {
        border-right: none;
    }
    
    #map {
        border-radius: 12px;
        border: 2px solid var(--primary-light);
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
        z-index: 1;
        height: 250px;
        width: 100%;
    }
    
    .leaflet-interactive {
        stroke: var(--primary) !important;
        fill: var(--primary-light) !important;
        fill-opacity: 0.2 !important;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
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
        
        #map {
            height: 200px;
        }
    }
    
    .router-status-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .status-online {
        background: #22c55e;
        color: white;
    }
    
    .status-offline {
        background: #ef4444;
        color: white;
    }
    
    .coordinates-group {
        position: relative;
    }
    
    .coordinates-group .form-control {
        padding-right: 40px;
    }
    
    .coordinates-group .glyphicon-map-marker {
        position: absolute;
        right: 10px;
        top: 10px;
        color: var(--primary);
        cursor: pointer;
        z-index: 2;
    }
    
    .coordinates-group .glyphicon-map-marker:hover {
        color: var(--primary-dark);
    }
    
    .spinning {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .alert {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 12px;
        border-left: 4px solid;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .alert-success {
        background: #f0fdf4;
        border-left-color: #22c55e;
    }
    
    .alert-danger {
        background: #fef2f2;
        border-left-color: #ef4444;
    }
    
    .valid-input {
        border-color: #22c55e !important;
        background-color: #f0fff4 !important;
    }
    
    .invalid-input {
        border-color: #ef4444 !important;
        background-color: #fff5f5 !important;
    }
</style>

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-pencil" style="margin-right: 8px;"></i>
                {Lang::T('Edit Router')}
                {if isset($d['status'])}
                    <span class="router-status-badge {if $d['status'] == 'Online'}status-online{else}status-offline{/if}">
                        <i class="glyphicon glyphicon-{if $d['status'] == 'Online'}ok{else}remove{/if}" style="margin-right: 3px;"></i>
                        {if $d['status'] == 'Online'}{Lang::T('Online')}{else}{Lang::T('Offline')}{/if}
                    </span>
                {/if}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}routers/edit-post">
                    <input type="hidden" name="id" value="{$d['id']}">
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Status')}</label>
                        <div class="col-md-10">
                            <label class="radio-inline">
                                <input type="radio" {if $d['enabled'] == 1}checked{/if} name="enabled" value="1"> 
                                <span style="color: #22c55e;">{Lang::T('Enable')}</span>
                            </label>
                            <label class="radio-inline">
                                <input type="radio" {if $d['enabled'] == 0}checked{/if} name="enabled" value="0">
                                <span style="color: #ef4444;">{Lang::T('Disable')}</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Router Name / Location')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="name" name="name" maxlength="32"
                                value="{$d['name']}" placeholder="e.g., Downtown Tower">
                            <p class="help-block"><i class="glyphicon glyphicon-info-sign"></i> {Lang::T('Name of Area that router operated')}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('IP Address')}</label>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-cloud"></i></span>
                                <input type="text" placeholder="192.168.88.1:8728" class="form-control" id="ip_address"
                                    name="ip_address" value="{$d['ip_address']}">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Username')}</label>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="{$d['username']}" placeholder="admin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Router Secret')}</label>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password"
                                    value="{$d['password']}" onmouseleave="this.type = 'password'"
                                    onmouseenter="this.type = 'text'" placeholder="••••••••">
                            </div>
                            <p class="help-block"><i class="glyphicon glyphicon-eye-open"></i> {Lang::T('Hover to reveal password')}</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Description')}</label>
                        <div class="col-md-6">
                            <textarea class="form-control" id="description" name="description" 
                                placeholder="Describe the coverage area, location details, etc." rows="3">{$d['description']}</textarea>
                            <p class="help-block"><i class="glyphicon glyphicon-info-sign"></i> {Lang::T('Explain Coverage of router')}</p>
                        </div>
                    </div>
         
                                       
                            <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Router Actions')}</label>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-warning" onclick="testConnection()" id="test-connection-btn" style="margin-right: 5px;">
                                <i class="glyphicon glyphicon-signal"></i> {Lang::T('Test Connection')}
                            </button>
                            <button type="button" class="btn btn-danger" onclick="rebootRouter()" id="reboot-btn">
                                <i class="glyphicon glyphicon-refresh"></i> {Lang::T('Reboot Router')}
                            </button>
                            <div id="connection-result" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                    
                    <hr style="border-top: 2px solid var(--primary-light); margin: 25px 0;">
                    
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary" onclick="return ask(this, '{Lang::T("Continue the process of changing Routers?")}')" type="submit">
                                <i class="glyphicon glyphicon-save"></i> {Lang::T('Save Changes')}
                            </button>
                            {Lang::T('Or')} <a href="{Text::url('')}routers/list" class="btn-link" style="margin-left: 10px;">
                                <i class="glyphicon glyphicon-remove"></i> {Lang::T('Cancel')}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reboot Confirmation Modal -->
<div class="modal fade" id="rebootModal" tabindex="-1" role="dialog" aria-labelledby="rebootModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="rebootModalLabel">
                    <i class="glyphicon glyphicon-refresh" style="color: var(--primary); margin-right: 8px;"></i>
                    {Lang::T('Reboot Router')}: {$d['name']}
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" style="background: #fef9c3; border-left: 4px solid #eab308; border-radius: 8px;">
                    <i class="glyphicon glyphicon-exclamation-sign" style="color: #eab308; margin-right: 8px;"></i>
                    <strong>{Lang::T('Warning!')}</strong> {Lang::T('This will reboot the router and disconnect all users.')}
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label for="reboot-confirm" style="font-weight: 600; color: var(--primary-dark);">
                        <i class="glyphicon glyphicon-check" style="margin-right: 5px;"></i>
                        {Lang::T('Type "REBOOT" to confirm')}
                    </label>
                    <input type="text" class="form-control" id="reboot-confirm" placeholder="REBOOT">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="glyphicon glyphicon-remove"></i> {Lang::T('Cancel')}
                </button>
                <button type="button" class="btn btn-warning" id="confirm-reboot-btn" disabled>
                    <i class="glyphicon glyphicon-refresh"></i> {Lang::T('Reboot Router')}
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />

<script>
{literal}
    var circle;
    var map;
    var marker;
    var routerId = {$d['id']};
    
    function getLocation() {
        if (window.location.protocol == "https:" && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(showPosition, function(error) {
                console.error("Geolocation error:", error);
                setupMap(51.505, -0.09);
            });
        } else {
            setupMap(51.505, -0.09);
        }
    }

    function showPosition(position) {
        setupMap(position.coords.latitude, position.coords.longitude);
    }

    function updateCoverage() {
        if (circle) {
            var coverage = parseInt($("#coverage").val()) || 100;
            circle.setRadius(coverage);
            if (marker) {
                marker.bindTooltip("Coverage: " + coverage + "m").openTooltip();
            }
        }
    }

    function setupMap(lat, lon) {
        if (!map) {
            map = L.map('map').setView([lat, lon], 13);
            
            // Fix for Smarty template - use OpenStreetMap with standard URL
            var tileUrl = 'https://tile.openstreetmap.org/';
            tileUrl += '{z}/{x}/{y}.png';
            
            L.tileLayer(tileUrl, {
                maxZoom: 19,
                attribution: 'OpenStreetMap'
            }).addTo(map);
        } else {
            map.setView([lat, lon], 13);
        }
        
        if (circle) map.removeLayer(circle);
        if (marker) map.removeLayer(marker);
        
        var coverage = parseInt($("#coverage").val()) || 100;
        circle = L.circle([lat, lon], {
            radius: coverage,
            color: '#f97316',
            fillColor: '#fed7aa',
            fillOpacity: 0.2,
            weight: 2
        }).addTo(map);
        
        marker = L.marker([lat, lon], {
            draggable: true,
            autoPan: true
        }).addTo(map);
        
        marker.bindTooltip("Coverage: " + coverage + "m").openTooltip();
        
        marker.on('dragend', function(e) {
            var position = e.target.getLatLng();
            circle.setLatLng(position);
            $('#coordinates').val(position.lat + ',' + position.lng);
            updateCoverage();
        });
        
        map.on('click', function(e) {
            var coord = e.latlng;
            marker.setLatLng(coord);
            circle.setLatLng(coord);
            $('#coordinates').val(coord.lat + ',' + coord.lng);
            updateCoverage();
        });
    }
    
    $(document).ready(function() {
        if ("{$d['coordinates']}") {
            var coords = "{$d['coordinates']}".split(',');
            setupMap(parseFloat(coords[0]), parseFloat(coords[1]));
        } else {
            getLocation();
        }
        
        $("#coverage").on('input', function() {
            updateCoverage();
        });
    });
    
    function testConnection() {
        var btn = $('#test-connection-btn');
        var resultDiv = $('#connection-result');
        
        btn.prop('disabled', true);
        btn.html('<i class="glyphicon glyphicon-refresh spinning"></i> Testing...');
        
        if (!$('#spin-animation').length) {
            $('head').append('<style id="spin-animation">.spinning { animation: spin 1s linear infinite; } @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>');
        }
        
        resultDiv.html('');
        
        $.ajax({
            url: '{/literal}{Text::url('')}{literal}routers/test-connection/' + routerId,
            method: 'GET',
            timeout: 5000,
            success: function(response) {
                if (response.status === 'success') {
                    resultDiv.html('<div class="alert alert-success" style="margin-top: 10px; padding: 10px; border-radius: 8px;"><i class="glyphicon glyphicon-ok"></i> ' + response.message + '</div>');
                    $('.router-status-badge').removeClass('status-offline').addClass('status-online')
                        .html('<i class="glyphicon glyphicon-ok"></i> Online');
                } else {
                    resultDiv.html('<div class="alert alert-danger" style="margin-top: 10px; padding: 10px; border-radius: 8px;"><i class="glyphicon glyphicon-remove"></i> ' + response.message + '</div>');
                    $('.router-status-badge').removeClass('status-online').addClass('status-offline')
                        .html('<i class="glyphicon glyphicon-remove"></i> Offline');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Connection failed';
                if (status === 'timeout') {
                    errorMsg = 'Connection timeout';
                }
                resultDiv.html('<div class="alert alert-danger" style="margin-top: 10px; padding: 10px; border-radius: 8px;"><i class="glyphicon glyphicon-remove"></i> ' + errorMsg + '</div>');
                $('.router-status-badge').removeClass('status-online').addClass('status-offline')
                    .html('<i class="glyphicon glyphicon-remove"></i> Offline');
            },
            complete: function() {
                btn.prop('disabled', false);
                btn.html('<i class="glyphicon glyphicon-signal"></i> Test Connection');
            }
        });
    }
    
    function rebootRouter() {
        $('#rebootModal').modal('show');
        $('#reboot-confirm').val('').removeClass('valid-input invalid-input');
        $('#confirm-reboot-btn').prop('disabled', true);
    }
    
    $('#reboot-confirm').on('input', function() {
        var confirmBtn = $('#confirm-reboot-btn');
        if ($(this).val() === 'REBOOT') {
            confirmBtn.prop('disabled', false);
            $(this).removeClass('invalid-input').addClass('valid-input');
        } else {
            confirmBtn.prop('disabled', true);
            $(this).removeClass('valid-input');
            if ($(this).val().length > 0) {
                $(this).addClass('invalid-input');
            }
        }
    });
    
    $('#confirm-reboot-btn').on('click', function() {
        var button = $(this);
        var modal = $('#rebootModal');
        var rebootBtn = $('#reboot-btn');
        
        button.prop('disabled', true);
        var originalHtml = button.html();
        button.html('<i class="glyphicon glyphicon-refresh spinning"></i> Rebooting...');
        rebootBtn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh spinning"></i> Rebooting...');
        
        $.ajax({
            url: '{/literal}{Text::url('')}{literal}routers/reboot/' + routerId,
            method: 'POST',
            dataType: 'json',
            timeout: 8000,
            success: function(response) {
                modal.modal('hide');
                if (response.status === 'success') {
                    showNotification('success', response.message || 'Router reboot initiated');
                    $('.router-status-badge').removeClass('status-online').addClass('status-offline')
                        .html('<i class="glyphicon glyphicon-refresh spinning"></i> Rebooting...');
                } else {
                    showNotification('error', response.message || 'Failed to reboot router');
                }
            },
            error: function() {
                modal.modal('hide');
                showNotification('error', 'Network error');
            },
            complete: function() {
                button.prop('disabled', false).html(originalHtml);
                rebootBtn.prop('disabled', false).html('<i class="glyphicon glyphicon-refresh"></i> Reboot Router');
            }
        });
    });
    
    function showNotification(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var iconClass = type === 'success' ? 'glyphicon-ok' : 'glyphicon-exclamation-sign';
        
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 12px; border-left: 4px solid ' + (type === 'success' ? '#22c55e' : '#ef4444') + ';">' +
                       '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>' +
                       '<i class="glyphicon ' + iconClass + '" style="margin-right: 10px;"></i> ' + message +
                       '</div>';
        
        $('body').append(alertHtml);
        setTimeout(function() { $('.alert').fadeOut(function() { $(this).remove(); }); }, 5000);
    }
{/literal}
</script>

{include file="sections/footer.tpl"}