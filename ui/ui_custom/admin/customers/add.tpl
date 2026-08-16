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

<form class="form-horizontal" method="post" role="form" action="{Text::url('customers/add-post')}" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="{$csrf_token}">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">{Lang::T('Add New Contact')}</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Username')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                {if $_c['country_code_phone'] != ''}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-phone-alt"></i></span>
                                {else}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-user"></i></span>
                                {/if}
                                <input type="text" class="form-control" name="username" required
                                    placeholder="{if $_c['country_code_phone']!= ''}{$_c['country_code_phone']} {Lang::T('Phone Number')}{else}{Lang::T('Usernames')}{/if}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Full Name')}</label>
                        <div class="col-md-9">
                            <input type="text" required class="form-control" id="fullname" name="fullname">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Email')}</label>
                        <div class="col-md-9">
                            <input type="email" class="form-control" id="email" name="email">
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
                                <input type="text" class="form-control" name="phonenumber"
                                    placeholder="{if $_c['country_code_phone']!= ''}{$_c['country_code_phone']}{/if} {Lang::T('Phone Number')}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Password')}</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" autocomplete="off" required id="password"
                                value="{rand(000000,999999)}" name="password" onmouseleave="this.type = 'password'"
                                onmouseenter="this.type = 'text'">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Home Address')}</label>
                        <div class="col-md-9">
                            <textarea name="address" id="address" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Service Type')}</label>
                        <div class="col-md-9">
                            <select class="form-control" id="service_type" name="service_type">
                                <option value="Hotspot">Hotspot
                                </option>
                                <option value="PPPoE">PPPoE</option>
                                <option value="VPN">VPN</option>
                                <option value="Others">{Lang::T('Other')}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Account Type')}</label>
                        <div class="col-md-9">
                            <select class="form-control" id="account_type" name="account_type">
                                <option value="Personal">{Lang::T('Personal')}
                                </option>
                                <option value="Business">{Lang::T('Business')}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Coordinates')}</label>
                        <div class="col-md-9">
                            <input name="coordinates" id="coordinates" class="form-control" value=""
                                placeholder="-6.465422, 3.406448">
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
                                    <option value="{$odp.id}">{$odp.name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Foto Lokasi</label>
                        <div class="col-md-9">
                            <div class="foto-lokasi-container">
                                <input type="file" id="foto_lokasi" name="foto_lokasi" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewFotoLokasiAdd(this)" style="display: none;">
                                <div class="foto-lokasi-wrapper">
                                    <div class="foto-lokasi-placeholder" id="fotoLokasiPlaceholder" onclick="document.getElementById('foto_lokasi').click()">
                                        <i class="glyphicon glyphicon-camera"></i>
                                        <span>Klik untuk upload foto lokasi</span>
                                    </div>
                                    <div class="foto-lokasi-preview" id="fotoLokasiPreview" style="display: none;">
                                        <img id="fotoLokasiImg" src="" alt="Preview">
                                        <div class="foto-lokasi-actions">
                                            <button type="button" class="btn btn-xs btn-info" onclick="document.getElementById('foto_lokasi').click()" title="Ganti Foto">
                                                <i class="glyphicon glyphicon-refresh"></i>
                                            </button>
                                            <button type="button" class="btn btn-xs btn-danger" onclick="hapusFotoLokasiAddPreview()" title="Hapus Foto">
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
                </div>
                <div class="panel-heading">PPPoE</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Usernames')} <span class="label label-danger"
                                id="warning_username"></span></label>
                        <div class="col-md-9">
                            <input type="username" class="form-control" id="pppoe_username" name="pppoe_username"
                                onkeyup="checkUsername(this, '0')">
                            <span class="help-block">{Lang::T('Not Working for freeradius')}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Password')}</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" id="pppoe_password" name="pppoe_password"
                                onmouseleave="this.type = 'password'" onmouseenter="this.type = 'text'">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Remote IP <span class="label label-danger"
                                id="warning_ip"></span></label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="pppoe_ip" name="pppoe_ip"
                                onkeyup="checkIP(this, '0')">
                            <span class="help-block">{Lang::T('Also Working for freeradius')}</span>
                        </div>
                    </div>
                    <span class="help-block">
                        {Lang::T('User Cannot change this, only admin. if it Empty it will use Customer Credentials')}
                    </span>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Send welcome message')}</label>
                        <div class="col-md-9">
                            <label class="switch">
                                <input type="checkbox" id="send_welcome_message" value="1" name="send_welcome_message">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="method" style="display: none;">
                        <label class="col-md-3 control-label">{Lang::T('Notification via')}</label>
                        <label class="col-md-1 control-label"><input type="checkbox" name="sms" value="1">
                            {Lang::T('SMS')}</label>
                        <label class="col-md-1 control-label"><input type="checkbox" name="wa" value="1">
                            {Lang::T('WA')}</label>
                        <label class="col-md-1 control-label"><input type="checkbox" name="mail" value="1">
                            {Lang::T('Email')}</label>
                        <label class="col-md-1 control-label"><input type="checkbox" name="inbox" value="1">
                            {Lang::T('Inbox')}</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">{Lang::T('Attributes')}</div>
                <div class="panel-body">
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
                        <label class="col-md-3 control-label">{Lang::T('Zip')}</label>
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
        <button class="btn btn-primary"
            onclick="return ask(this, '{Lang::T("Continue the process of adding Customer Data?")}')" type="submit">
            {Lang::T('Save Changes')}
        </button>
        <br><a href="{Text::url('customers/list')}" class="btn btn-link">{Lang::T('Cancel')}</a>
    </center>
</form>
{literal}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var sendWelcomeCheckbox = document.getElementById('send_welcome_message');
            var methodSection = document.getElementById('method');

            function toggleMethodSection() {
                if (sendWelcomeCheckbox.checked) {
                    methodSection.style.display = 'block';
                } else {
                    methodSection.style.display = 'none';
                }
            }

            toggleMethodSection();

            sendWelcomeCheckbox.addEventListener('change', toggleMethodSection);
            document.querySelector('form').addEventListener('submit', function(event) {
                if (sendWelcomeCheckbox.checked) {
                    var methodCheckboxes = methodSection.querySelectorAll('input[type="checkbox"]');
                    var oneChecked = Array.from(methodCheckboxes).some(function(checkbox) {
                        return checkbox.checked;
                    });

                    if (!oneChecked) {
                        event.preventDefault();
                        alert('Please choose at least one method notification.');
                        methodSection.focus();
                    }
                }
            });
        });
    </script>
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
            getLocation();
        }
        
        // Preview foto lokasi untuk Add
        function previewFotoLokasiAdd(input) {
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
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Hapus preview foto lokasi untuk Add
        function hapusFotoLokasiAddPreview() {
            var input = document.getElementById('foto_lokasi');
            var placeholder = document.getElementById('fotoLokasiPlaceholder');
            var preview = document.getElementById('fotoLokasiPreview');
            var previewImg = document.getElementById('fotoLokasiImg');
            
            input.value = '';
            previewImg.src = '';
            preview.style.display = 'none';
            placeholder.style.display = 'flex';
        }
    </script>
{/literal}

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
{/if}

{include file="sections/footer.tpl"}