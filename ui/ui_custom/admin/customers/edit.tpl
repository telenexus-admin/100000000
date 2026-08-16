{include file="sections/header.tpl"}

<!-- jQuery (jika belum ada) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.select2-container .select2-selection--single {
    height: 34px;
    padding: 6px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 20px;
    padding-left: 0;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 32px;
}
/* Dark Mode Support untuk Select2 */
body.dark-mode .select2-container--default .select2-selection--single,
.dark-mode .select2-container--default .select2-selection--single,
[data-theme="dark"] .select2-container--default .select2-selection--single {
    background-color: #3a4459;
    border-color: #5a6a8a;
    color: #f0f0f0;
}
body.dark-mode .select2-container--default .select2-selection--single .select2-selection__rendered,
.dark-mode .select2-container--default .select2-selection--single .select2-selection__rendered,
[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #f0f0f0;
}
body.dark-mode .select2-container--default .select2-selection--single .select2-selection__arrow b,
.dark-mode .select2-container--default .select2-selection--single .select2-selection__arrow b,
[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #f0f0f0 transparent transparent transparent;
}
body.dark-mode .select2-dropdown,
.dark-mode .select2-dropdown,
[data-theme="dark"] .select2-dropdown {
    background-color: #3a4459;
    border-color: #5a6a8a;
}
body.dark-mode .select2-container--default .select2-search--dropdown .select2-search__field,
.dark-mode .select2-container--default .select2-search--dropdown .select2-search__field,
[data-theme="dark"] .select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: #4a5568;
    border-color: #5a6a8a;
    color: #f0f0f0;
}
body.dark-mode .select2-container--default .select2-results__option,
.dark-mode .select2-container--default .select2-results__option,
[data-theme="dark"] .select2-container--default .select2-results__option {
    background-color: #3a4459;
    color: #f0f0f0;
}
body.dark-mode .select2-container--default .select2-results__option--highlighted[aria-selected],
.dark-mode .select2-container--default .select2-results__option--highlighted[aria-selected],
[data-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #5a9cf8;
    color: #ffffff;
}
body.dark-mode .select2-container--default .select2-results__option[aria-selected=true],
.dark-mode .select2-container--default .select2-results__option[aria-selected=true],
[data-theme="dark"] .select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #4a5568;
    color: #f0f0f0;
}
/* Foto Lokasi Styles */
.foto-lokasi-container {
    width: 100%;
}
.foto-lokasi-wrapper {
    width: 200px;
    height: 150px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}
.foto-lokasi-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: #f9f9f9;
    transition: all 0.3s;
}
.foto-lokasi-placeholder:hover {
    background: #f0f0f0;
    border-color: #999;
}
.foto-lokasi-placeholder i {
    font-size: 32px;
    color: #999;
    margin-bottom: 8px;
}
.foto-lokasi-placeholder span {
    font-size: 12px;
    color: #666;
    text-align: center;
    padding: 0 10px;
}
.foto-lokasi-preview {
    width: 100%;
    height: 100%;
    position: relative;
}
.foto-lokasi-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
}
.foto-lokasi-actions {
    position: absolute;
    bottom: 5px;
    right: 5px;
    display: flex;
    gap: 5px;
}
/* Fullscreen Modal */
.foto-fullscreen-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}
.foto-fullscreen-overlay.active {
    display: flex;
}
.foto-fullscreen-header {
    width: 100%;
    padding: 15px 20px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.foto-fullscreen-title {
    font-size: 16px;
    font-weight: 600;
}
.foto-fullscreen-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
}
.foto-fullscreen-body {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    overflow: auto;
}
.foto-fullscreen-body img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
/* Dark Mode */
body.dark-mode .foto-lokasi-wrapper,
.dark-mode .foto-lokasi-wrapper,
[data-theme="dark"] .foto-lokasi-wrapper {
    border-color: #5a6a8a;
}
body.dark-mode .foto-lokasi-placeholder,
.dark-mode .foto-lokasi-placeholder,
[data-theme="dark"] .foto-lokasi-placeholder {
    background: #3a4459;
}
body.dark-mode .foto-lokasi-placeholder:hover,
.dark-mode .foto-lokasi-placeholder:hover,
[data-theme="dark"] .foto-lokasi-placeholder:hover {
    background: #4a5568;
}
body.dark-mode .foto-lokasi-placeholder i,
.dark-mode .foto-lokasi-placeholder i,
[data-theme="dark"] .foto-lokasi-placeholder i {
    color: #9ca3af;
}
body.dark-mode .foto-lokasi-placeholder span,
.dark-mode .foto-lokasi-placeholder span,
[data-theme="dark"] .foto-lokasi-placeholder span {
    color: #9ca3af;
}
</style>

<form class="form-horizontal" enctype="multipart/form-data" method="post" role="form" action="{Text::url('customers/edit-post')}">
    <input type="hidden" name="csrf_token" value="{$csrf_token}">
    <div class="row">
        <div class="col-md-6">
            <div
                class="panel panel-{if $d['status']=='Active'}primary{else}danger{/if} panel-hovered panel-stacked mb30">
                <div class="panel-heading">{Lang::T('Edit Contact')}</div>
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
                            <span class="help-block">{Lang::T('Keep Blank to do not change Password')}</span>
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
                                <option value="VPN" {if $d['service_type'] eq 'VPN' }selected{/if}>VPN</option>
                                <option value="Others" {if $d['service_type'] eq 'Others' }selected{/if}>{Lang::T("Other")}</option>
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
                        <label class="col-md-3 control-label">{Lang::T('Coordinates')}</label>
                        <div class="col-md-9">
                            <input name="coordinates" id="coordinates" class="form-control" value="{$d['coordinates']}"
                                placeholder="6.465422, 3.406448">
                            <div id="map" style="width: '100%'; height: 200px; min-height: 150px;"></div>
                        </div>
                    </div>
                    {if file_exists('system/plugin/network_mapping.php')}
                    <div class="form-group">
                        <label class="col-md-3 control-label">ODP</label>
                        <div class="col-md-9">
                            <select class="form-control select2-odp" id="odp_id" name="odp_id" style="width: 100%;">
                                <option value="">-- Pilih ODP --</option>
                                {foreach $odp_list as $odp}
                                    <option value="{$odp.id}" {if $d['odp_id'] eq $odp.id}selected{/if}>{$odp.name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Foto Lokasi</label>
                        <div class="col-md-9">
                            <input type="hidden" id="hapus_foto_lokasi" name="hapus_foto_lokasi" value="0">
                            <div class="foto-lokasi-container">
                                <input type="file" id="foto_lokasi" name="foto_lokasi" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewFotoLokasi(this)" style="display: none;">
                                <div class="foto-lokasi-wrapper">
                                    <div class="foto-lokasi-placeholder" id="fotoLokasiPlaceholder" onclick="document.getElementById('foto_lokasi').click()" {if $foto_lokasi_url}style="display: none;"{/if}>
                                        <i class="glyphicon glyphicon-camera"></i>
                                        <span>Klik untuk upload foto lokasi</span>
                                    </div>
                                    <div class="foto-lokasi-preview" id="fotoLokasiPreview" {if !$foto_lokasi_url}style="display: none;"{/if}>
                                        <img id="fotoLokasiImg" src="{if $foto_lokasi_url}{$foto_lokasi_url}{/if}" alt="Preview" onclick="lihatFotoLokasiFullscreen()">
                                        <div class="foto-lokasi-actions">
                                            <button type="button" class="btn btn-xs btn-info" onclick="document.getElementById('foto_lokasi').click()" title="Ganti Foto">
                                                <i class="glyphicon glyphicon-refresh"></i>
                                            </button>
                                            <button type="button" class="btn btn-xs btn-danger" onclick="hapusFotoLokasiPreview()" title="Hapus Foto">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <small class="help-block">Max 5MB, format: JPG, PNG, WebP. Foto lokasi/rumah customer.</small>
                        </div>
                    </div>
                    {/if}
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
                                {Lang::T('Banned')}: {Lang::T('Customer cannot login again')}.<br>
                                {Lang::T('Disabled')}:
                                {Lang::T('Customer can login but cannot buy internet package, Admin cannot recharge customer')}.<br>
                                {Lang::T("Don't forget to deactivate all active package too")}.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="panel-heading">PPPoE</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Usernames')} <span class="label label-danger"
                                id="warning_username"></span></label>
                        <div class="col-md-9">
                            <input type="username" class="form-control" id="pppoe_username" name="pppoe_username"
                                onkeyup="checkUsername(this, {$d['id']})" value="{$d['pppoe_username']}">
                            <span class="help-block">{Lang::T('Not Working with Freeradius Mysql')}</span>
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
                            <span class="help-block">{Lang::T('Not Working with Freeradius Mysql')}</span>
                        </div>
                    </div>
                    <span class="help-block">
                        {Lang::T('User Cannot change this, only admin. if it Empty it will use Customer Credentials')}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">{Lang::T('Attributes')}</div>
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
                        id="add-custom-field">{Lang::T('Add')}</button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-primary box-solid collapsed-box">
                <div class="box-header with-border">
                    <h3 class="box-title">{Lang::T('Additional Information')}</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body" style="display: none;">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('City')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="city" name="city" value="{$d['city']}">
                            <small class="form-text text-muted">{Lang::T('City of Resident')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('District')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="district" name="district"
                                value="{$d['district']}">
                            <small class="form-text text-muted">{Lang::T('District')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('State')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="state" name="state" value="{$d['state']}">
                            <small class="form-text text-muted">{Lang::T('State of Resident')}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Zip Code')}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="zip" name="zip" value="{$d['zip']}">
                            <small class="form-text text-muted">{Lang::T('Zip Code')}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <center>
        <button class="btn btn-primary" onclick="return ask(this, '{Lang::T("Continue the Customer Data change process?")}')"
            type="submit">
            {Lang::T('Save Changes')}
        </button>
        <br><a href="{Text::url('')}customers/list" class="btn btn-link">{Lang::T('Cancel')}</a>
    </center>
</form>

<!-- Fullscreen Modal untuk Foto Lokasi -->
<div class="foto-fullscreen-overlay" id="fotoFullscreenOverlay" onclick="tutupFotoFullscreen(event)">
    <div class="foto-fullscreen-header">
        <span class="foto-fullscreen-title" id="fotoFullscreenTitle">Foto Lokasi</span>
        <button class="foto-fullscreen-close" onclick="tutupFotoFullscreen(event)">&times;</button>
    </div>
    <div class="foto-fullscreen-body" onclick="event.stopPropagation()">
        <img id="fotoFullscreenImg" src="" alt="Fullscreen">
    </div>
</div>

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
                    <input type="text" class="form-control" name="custom_field_name[]" placeholder="Name">
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="custom_field_value[]" placeholder="Value">
                </div>
                <div class="col-md-2">
                    <button type="button" class="remove-custom-field btn btn-danger btn-sm">-</button>
                </div>
            `;
                customFieldsContainer.appendChild(newField);
            });

            customFieldsContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-custom-field')) {
                    var fieldContainer = event.target.parentNode.parentNode;
                    fieldContainer.parentNode.removeChild(fieldContainer);
                }
            });
        });
    </script>

    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
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
            $('#coordinates').val(lat + ',' + lng);
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
                window.location.href = '{Text::url('')}customers/edit/'+id+'/deletePhoto'
            }
        }
    }
</script>

{if file_exists('system/plugin/network_mapping.php')}
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('.select2-odp').select2({
            placeholder: '-- Pilih ODP --',
            allowClear: true
        });
    });
</script>

{literal}
<script>
    // Preview foto lokasi
    function previewFotoLokasi(input) {
        var placeholder = document.getElementById('fotoLokasiPlaceholder');
        var preview = document.getElementById('fotoLokasiPreview');
        var previewImg = document.getElementById('fotoLokasiImg');
        
        if (input.files && input.files[0]) {
            // Validasi ukuran (max 5MB)
            if (input.files[0].size > 5 * 1024 * 1024) {
                alert('Ukuran foto maksimal 5MB');
                input.value = '';
                return;
            }
            
            // Validasi tipe
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(input.files[0].type)) {
                alert('Format foto harus JPG, PNG, atau WebP');
                input.value = '';
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                placeholder.style.display = 'none';
                preview.style.display = 'block';
                document.getElementById('hapus_foto_lokasi').value = '0';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Hapus preview foto lokasi
    function hapusFotoLokasiPreview() {
        var input = document.getElementById('foto_lokasi');
        var placeholder = document.getElementById('fotoLokasiPlaceholder');
        var preview = document.getElementById('fotoLokasiPreview');
        var previewImg = document.getElementById('fotoLokasiImg');
        
        input.value = '';
        previewImg.src = '';
        preview.style.display = 'none';
        placeholder.style.display = 'flex';
        document.getElementById('hapus_foto_lokasi').value = '1';
    }
    
    // Lihat foto lokasi fullscreen
    function lihatFotoLokasiFullscreen() {
        var previewImg = document.getElementById('fotoLokasiImg');
        if (previewImg.src) {
            document.getElementById('fotoFullscreenImg').src = previewImg.src;
            document.getElementById('fotoFullscreenTitle').textContent = 'Foto Lokasi Customer';
            document.getElementById('fotoFullscreenOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    // Tutup fullscreen
    function tutupFotoFullscreen(event) {
        if (event.target === document.getElementById('fotoFullscreenOverlay') || 
            event.target.classList.contains('foto-fullscreen-close')) {
            document.getElementById('fotoFullscreenOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    // Tutup dengan ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            tutupFotoFullscreen({target: document.getElementById('fotoFullscreenOverlay')});
        }
    });
</script>
{/literal}
{/if}

{include file="sections/footer.tpl"}
