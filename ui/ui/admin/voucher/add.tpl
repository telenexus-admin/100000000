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
        outline: none;
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
    
    .btn-primary {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        border-radius: 8px;
        color: white;
        padding: 8px 15px;
    }
    
    .btn-primary:hover {
        background: linear-gradient(145deg, var(--primary-dark), #c2410c);
    }
    
    .alert-danger {
        background: #fff1f0;
        border-left: 4px solid #ef4444;
        color: #1e293b;
        border-radius: 12px;
        padding: 20px;
    }
    
    .alert-danger h4 {
        color: #ef4444;
        font-weight: 600;
    }
    
    .help-block {
        color: #64748b;
        font-size: 12px;
        margin-top: 5px;
    }
    
    input[type="radio"] {
        accent-color: var(--primary);
        width: 16px;
        height: 16px;
        margin-right: 5px;
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
    
    /* SMS Options Panel */
    .panel-info {
        border-color: var(--primary-light);
        border-radius: 12px;
    }
    
    .panel-info .panel-body {
        background: var(--primary-soft);
        border-radius: 12px;
    }
    
    .panel-info .checkbox label {
        color: var(--primary-dark);
        font-weight: 600;
    }
    
    .badge-info {
        background: var(--primary);
        color: white;
        padding: 3px 8px;
        border-radius: 20px;
    }
    
    #voucher-count-info {
        background: var(--primary-soft);
        border-left: 4px solid var(--primary);
        border-radius: 8px;
        padding: 10px;
    }
    
    #voucher_preview_group .form-control-static {
        background: var(--primary-soft) !important;
        border: 2px solid var(--primary-light);
        border-radius: 12px;
        color: var(--primary-dark);
        font-weight: 600;
    }
    
    /* Modal Styling */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 40px rgba(249, 115, 22, 0.2);
    }
    
    .modal-header {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 15px 20px;
    }
    
    .modal-header .close {
        color: white;
        opacity: 0.8;
    }
    
    .modal-header .close:hover {
        opacity: 1;
    }
    
    .modal-title {
        font-weight: 600;
    }
    
    .modal-footer {
        border-top: 2px solid var(--primary-light);
        padding: 15px 20px;
    }
    
    .modal-footer .btn-primary {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border-radius: 30px;
        padding: 8px 20px;
    }
    
    .modal-footer .btn-success {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border-radius: 30px;
    }
    
    .modal-footer .btn-default {
        background: var(--primary-soft);
        border: 1px solid var(--primary-light);
        color: var(--primary-dark);
        border-radius: 30px;
    }
    
    #voucher-print-content {
        background: var(--primary-soft) !important;
        border: 2px solid var(--primary-light) !important;
        border-radius: 12px !important;
        color: #1e293b !important;
    }
    
    .field-disabled {
        background-color: var(--primary-soft) !important;
        border-color: var(--primary-light) !important;
        color: var(--primary-dark) !important;
        cursor: not-allowed;
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

<!-- voucher-add -->

{if !in_array($_admin['user_type'],['SuperAdmin','Admin'])}
<div class="row">
    <div class="col-sm-12">
        <div class="alert alert-danger">
            <h4><i class="fa fa-ban"></i> {Lang::T('Access Denied!')}</h4>
            {Lang::T('You do not have permission to create vouchers. Only Super Administrators and Administrators can create vouchers.')}
            <br><br>
            <strong>{Lang::T('Your Role')}:</strong> {$_admin['user_type']}<br>
            <strong>{Lang::T('Available Actions')}:</strong> {Lang::T('View vouchers, Distribute vouchers, Print vouchers')}
            <br><br>
            <a href="{Text::url('')}plan/voucher" class="btn btn-primary">
                <i class="fa fa-list"></i> {Lang::T('View Vouchers')}
            </a>
            <a href="{Text::url('')}plan/refill" class="btn btn-success">
                <i class="fa fa-share"></i> {Lang::T('Distribute Vouchers')}
            </a>
        </div>
    </div>
</div>
{else}

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-plus" style="margin-right: 8px;"></i>
                {Lang::T('Add Vouchers')} - <span style="color: var(--primary-light);">{$_admin['user_type']} {Lang::T('Access')}</span>
            </div>
            <div class="panel-body">

                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}plan/voucher-post">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Type')}</label>
                        <div class="col-md-6">
                            <input type="radio" id="Hot" name="type" value="Hotspot" checked>
                            <label for="Hot">{Lang::T('Hotspot Plans')}</label>
                            <p class="help-block" style="margin: 6px 0 0;">{Lang::T('Vouchers are Hotspot only')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Routers')}</label>
                        <div class="col-md-6">
                            <select id="server" name="server" class="form-control select2">
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
                        <label class="col-md-2 control-label">{Lang::T('Number of Vouchers')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="numbervoucher" id="numbervoucher" value="1">
                            <small class="help-block" id="numbervoucher-help" style="display: none; color: #888;">
                                <i class="fa fa-info-circle" style="color: var(--primary);"></i> {Lang::T('Field disabled - voucher count determined by custom names or phone numbers')}
                            </small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Custom Voucher Name(s)')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="custom_name" id="custom_name" 
                                    oninput="updateVoucherPreview()">
                        </div>
                       
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Length Code')}</label>
                        <div class="col-md-6">
                            <select class="form-control" name="lengthcode" id="lengthcode" required onchange="updateVoucherPreview()">
                                <option value="2">2 </option>
                                <option value="3">3 </option>
                                <option value="4">4 </option>
                                <option value="5">5 </option>
                                <option value="6">6 </option>
                                <option value="7">7 </option>
                                <option value="8">8 </option>
                                <option value="9">9</option>
                                <option value="10" selected>10 </option>
                            </select>
                          
                        </div>
                      
                    </div>

             
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-success"
                                onclick="return validateAndConfirm(this)"
                                type="submit">
                                <i class="glyphicon glyphicon-flash"></i> {Lang::T('Generate')}
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- Voucher Details Modal -->
<div class="modal fade" id="voucherDetailsModal" tabindex="-1" role="dialog" aria-labelledby="voucherDetailsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="voucherDetailsModalLabel">
                    <i class="fa fa-ticket" style="margin-right: 8px;"></i> {Lang::T('Voucher Created Successfully')}
                </h4>
            </div>
            <div class="modal-body">
                <div id="voucher-loading" class="text-center" style="padding: 30px;">
                    <i class="fa fa-spinner fa-spin fa-2x" style="color: var(--primary);"></i>
                    <p class="text-muted">{Lang::T('Loading voucher details...')}</p>
                </div>
                <div id="voucher-content" style="display: none;">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="panel panel-default">
                                <div class="panel-heading" style="background: var(--primary-soft); border-bottom: 2px solid var(--primary-light);">
                                    <h5 class="panel-title" style="color: var(--primary-dark);"><i class="fa fa-ticket"></i> {Lang::T('Voucher Details')}</h5>
                                </div>
                                <div class="panel-body" style="padding: 20px;">
                                    <pre id="voucher-print-content" style="background: var(--primary-soft); border: 2px solid var(--primary-light); padding: 20px; border-radius: 12px; font-size: 13px; line-height: 1.4; max-height: 400px; overflow-y: auto; font-family: 'Courier New', monospace;"></pre>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="panel panel-success" style="border-color: var(--primary);">
                                <div class="panel-heading" style="background: linear-gradient(145deg, var(--primary), var(--primary-dark)); border-color: var(--primary-dark);">
                                    <h5 class="panel-title" style="color: white;">
                                        <i class="fa fa-mobile"></i> {Lang::T('Send Additional SMS')}
                                    </h5>
                                </div>
                                <div class="panel-body" style="padding: 20px;">
                                    <div class="form-group">
                                        <label for="sms-phone-add" style="font-weight: 600; margin-bottom: 10px; color: var(--primary-dark);">
                                            <i class="fa fa-phone"></i> {Lang::T('Phone Number')}
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-addon" id="country-code-display-add" style="background: var(--primary-soft); border: 2px solid var(--primary-light); border-right: none; color: var(--primary-dark);">
                                                <i class="fa fa-flag"></i> +254
                                            </span>
                                            <input type="tel" class="form-control" id="sms-phone-add" 
                                                   placeholder="712345678" 
                                                   maxlength="9"
                                                   pattern="^[71][0-9]{8}$"
                                                   style="padding: 12px; font-size: 16px; font-weight: 500; border: 2px solid var(--primary-light); border-left: none;">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-success btn-lg" id="send-sms-btn-add" style="background: linear-gradient(145deg, var(--primary), var(--primary-dark)); border: none; padding: 12px 20px;">
                                                    <i class="fa fa-paper-plane"></i> {Lang::T('Send')}
                                                </button>
                                            </span>
                                        </div>
                                        <small class="help-block" style="margin-top: 10px; padding: 8px; background: var(--primary-soft); border-radius: 8px; border-left: 4px solid var(--primary);">
                                            <i class="fa fa-info-circle" style="color: var(--primary);"></i> 
                                            {Lang::T('Enter phone number')}: <strong>712345678</strong> {Lang::T('or')} <strong>101234567</strong>
                                        </small>
                                    </div>
                                    
                                    <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0; font-size: 13px; padding: 15px; background: var(--primary-soft); border-left: 4px solid var(--primary); color: #1e293b;">
                                        <i class="fa fa-check-circle" style="color: var(--primary);"></i> 
                                        <strong>{Lang::T('Voucher Ready!')}</strong><br>
                                        {Lang::T('Send to additional recipients or copy the voucher details above.')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="window.location.href='{Text::url('')}plan/voucher'">
                    <i class="fa fa-list"></i> {Lang::T('View All Vouchers')}
                </button>
                <button type="button" class="btn btn-success" onclick="window.location.href='{Text::url('')}plan/add-voucher'">
                    <i class="fa fa-plus"></i> {Lang::T('Create Another Voucher')}
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> {Lang::T('Close')}
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- /voucher-add -->

<script>
    // Vouchers are Hotspot only ? load routers and Hotspot plans on page open
    (function () {
        function loadVoucherHotspotData() {
            if (typeof appUrl === 'undefined' || typeof $ === 'undefined') {
                return false;
            }
            if (!$('#server').length || !$('#plan').length) {
                return true;
            }

            $('#Hot').prop('checked', true);

            $.ajax({
                type: 'POST',
                dataType: 'html',
                url: appUrl + '/?_route=autoload/server',
                success: function (msg) {
                    $('#server').html(msg);
                    if ($('#server').hasClass('select2-hidden-accessible')) {
                        $('#server').trigger('change.select2');
                    }
                }
            });

            $('#server').off('change.voucherHotspot').on('change.voucherHotspot', function () {
                var server = $('#server').val();
                $.ajax({
                    type: 'POST',
                    dataType: 'html',
                    url: appUrl + '/?_route=autoload/plan',
                    data: 'jenis=Hotspot&server=' + encodeURIComponent(server || ''),
                    success: function (msg) {
                        $('#plan').html(msg);
                        if ($('#plan').hasClass('select2-hidden-accessible')) {
                            $('#plan').trigger('change.select2');
                        }
                    }
                });
            });

            return true;
        }

        function boot() {
            if (!loadVoucherHotspotData()) {
                setTimeout(boot, 50);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
        // custom.js loads in footer after this script ? retry once it is ready
        window.addEventListener('load', boot);
    })();
</script>

<!-- Keep existing JavaScript functions but they will inherit the orange theme from CSS -->
<script>
    // Initialize voucher preview when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the preview with default values
        updateVoucherPreview();
    });

    // SMS options handling with automatic voucher counting
    document.addEventListener('DOMContentLoaded', function() {
        var enableSmsCheckbox = document.getElementById('enable_sms');
        var smsOptions = document.getElementById('sms_options');
        var smsPhones = document.getElementById('sms_phones');
        var numberVoucherInput = document.querySelector('input[name="numbervoucher"]');
        
        if (enableSmsCheckbox) {
            enableSmsCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    smsOptions.style.display = 'block';
                    updatePhoneCount(); // Update count when enabled
                } else {
                    smsOptions.style.display = 'none';
                    // Reset voucher count to 1 when disabled
                    numberVoucherInput.value = '1';
                    hideVoucherCountInfo();
                }
            });
        }
        
        // Real-time phone number counting and voucher updating
        if (smsPhones) {
            smsPhones.addEventListener('input', function() {
                updatePhoneCount();
            });
            
            smsPhones.addEventListener('paste', function() {
                // Give paste operation time to complete
                setTimeout(function() {
                    updatePhoneCount();
                }, 100);
            });
        }
        
        // Also update phone count when custom names change (for smart matching display)
        var customNameInput = document.getElementById('custom_name');
        if (customNameInput) {
            customNameInput.addEventListener('input', function() {
                updatePhoneCount(); // Update SMS info when prefixes change
            });
        }
        

        
        function updatePhoneCount() {
            if (!enableSmsCheckbox.checked) return;
            
            var phoneText = smsPhones.value.trim();
            var phones = parsePhoneNumbers(phoneText);
            var phoneCount = phones.length;
            
            // Check if there are custom prefixes
            var customNameInput = document.getElementById('custom_name').value.trim();
            var customPrefixes = [];
            if (customNameInput) {
                var names = customNameInput.split(',');
                for (var i = 0; i < names.length; i++) {
                    var cleaned = names[i].trim().replace(/[^a-zA-Z0-9]/g, '');
                    if (cleaned.length > 0) {
                        customPrefixes.push(cleaned);
                    }
                }
            }
            
            // Update badge and count display
            var phoneBadge = document.getElementById('phone-count-badge');
            var phoneCountText = document.getElementById('phone-count-text');
            var voucherCountText = document.getElementById('voucher-count-text');
            var voucherCountInfo = document.getElementById('voucher-count-info');
            
            if (phoneCount > 0) {
                phoneBadge.style.display = 'inline';
                phoneBadge.textContent = phoneCount + ' number' + (phoneCount !== 1 ? 's' : '');
                
                // Check voucher count vs phone count for validation
                var currentVoucherCount = parseInt(numberVoucherInput.value) || 1;
                var matchingInfo = '';
                var badgeClass = 'badge badge-success';
                
                if (phoneCount !== currentVoucherCount) {
                    matchingInfo = ' (?? mismatch: need ' + currentVoucherCount + ' vouchers)';
                    badgeClass = 'badge badge-warning';
                } else {
                    matchingInfo = ' (? matches voucher count)';
                }
                
                phoneCountText.textContent = phoneCount + ' phone number' + (phoneCount !== 1 ? 's' : '');
                voucherCountText.textContent = phoneCount + ' SMS recipient' + (phoneCount !== 1 ? 's' : '') + matchingInfo;
                voucherCountInfo.style.display = 'block';
                
                // Add visual feedback based on validation
                phoneBadge.className = badgeClass;
            } else {
                hideVoucherCountInfo();
                // Don't reset if there are custom prefixes without SMS
                if (customPrefixes.length === 0) {
                    numberVoucherInput.value = '1'; // Reset to default only if no prefixes
                }
            }
        }
        
        function hideVoucherCountInfo() {
            var phoneBadge = document.getElementById('phone-count-badge');
            var voucherCountInfo = document.getElementById('voucher-count-info');
            
            phoneBadge.style.display = 'none';
            voucherCountInfo.style.display = 'none';
            
            // Check if we should re-enable fields when SMS is disabled
            var customNameInput = document.getElementById('custom_name').value.trim();
            var customNames = [];
            if (customNameInput) {
                var names = customNameInput.split(',');
                for (var i = 0; i < names.length; i++) {
                    var cleaned = names[i].trim().replace(/[^a-zA-Z0-9]/g, '');
                    if (cleaned.length > 0) {
                        customNames.push(cleaned);
                    }
                }
            }
            // Only re-enable if no multiple custom names
            if (customNames.length <= 1) {
                enableAllFields();
            }
        }
        
        function parsePhoneNumbers(text) {
            if (!text) return [];
            
            // Split by commas and newlines, but prioritize commas
            var numbers = text.split(/[,\n\r]+/);
            var validNumbers = [];
            
            numbers.forEach(function(num) {
                num = num.trim();
                if (num) {
                    // Clean the number - remove all non-digit characters except +
                    var cleaned = num.replace(/[^0-9+]/g, '');
                    
                    // Parse different formats - support 01, 07, 2541, 2547, +254 prefixes
                    if (cleaned.match(/^\+254[0-9]{9}$/)) {
                        // +254712345678 format
                        validNumbers.push(cleaned);
                    } else if (cleaned.match(/^0[17][0-9]{8}$/)) {
                        // 0712345678, 0101234567 format - remove leading 0 and add +254
                        validNumbers.push('+254' + cleaned.substring(1));
                    } else if (cleaned.match(/^[17][0-9]{8}$/)) {
                        // 712345678, 101234567 format - add +254
                        validNumbers.push('+254' + cleaned);
                    } else if (cleaned.match(/^254[17][0-9]{8}$/)) {
                        // 254712345678, 254101234567 format - add +
                        validNumbers.push('+' + cleaned);
                    }
                }
            });
            
            // Remove duplicates
            return [...new Set(validNumbers)];
        }
    });

    function updateVoucherPreview() {
        var customNameInput = document.getElementById('custom_name').value;
        var lengthCode = parseInt(document.getElementById('lengthcode').value) || 7;
        var previewGroup = document.getElementById('voucher_preview_group');
        var previewText = document.getElementById('voucher_preview');
        var numberVoucherInput = document.querySelector('input[name="numbervoucher"]');
        
        // Check for multiple phone numbers as well
        var enableSmsCheckbox = document.getElementById('enable_sms');
        var smsPhones = document.getElementById('sms_phones');
        var phoneCount = 0;
        
        if (enableSmsCheckbox && enableSmsCheckbox.checked && smsPhones) {
            var phones = parsePhoneNumbers(smsPhones.value.trim());
            phoneCount = phones.length;
        }
        
        if (customNameInput.trim().length > 0) {
            // Check if multiple names are provided (contains comma)
            var names = customNameInput.split(',');
            var cleanedNames = [];
            
            for (var i = 0; i < names.length; i++) {
                var cleaned = names[i].trim().replace(/[^a-zA-Z0-9]/g, '');
                if (cleaned.length > 0) {
                    cleanedNames.push(cleaned);
                }
            }
            
            if (cleanedNames.length > 1) {
                // Multiple names - show maximum 2 in preview
                var previewNames = cleanedNames.slice(0, 2);
                var moreCount = cleanedNames.length - 2;
                var preview = previewNames.join(', ');
                if (moreCount > 0) {
                    preview += ' (+' + moreCount + ' more)';
                }
                preview += ' (exact names, ' + cleanedNames.length + ' vouchers)';
                previewText.textContent = preview;
                
                // Disable fields when multiple names provided
                disableFieldsForMultipleItems('custom_names');
                
                // Only auto-update voucher count if SMS is not enabled (SMS takes priority)
                if (!enableSmsCheckbox || !enableSmsCheckbox.checked) {
                    numberVoucherInput.value = cleanedNames.length;
                } else {
                    // If SMS is enabled, trigger update to show smart matching
                    updatePhoneCount();
                }
                
            } else if (cleanedNames.length == 1) {
                // Single name - use original logic
                var customName = cleanedNames[0];
                var finalVoucher = '';
                var description = '';
                
                if (customName.length >= lengthCode) {
                    finalVoucher = customName.substring(0, lengthCode);
                    description = ' (custom name only)';
                } else {
                    var remaining = lengthCode - customName.length;
                    var randomChars = '';
                    
                    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                    for (var i = 0; i < remaining; i++) {
                        randomChars += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    
                    finalVoucher = customName + randomChars;
                    description = ' (custom + ' + remaining + ' random)';
                }
                
                previewText.textContent = finalVoucher + description;
                
                // Re-enable fields for single name
                if (phoneCount <= 1) {
                    enableAllFields();
                }
            }
            
            previewGroup.style.display = 'block';
        } else {
            // No custom name - show default preview for chosen length
            var randomExample = '';
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            for (var i = 0; i < lengthCode; i++) {
                randomExample += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            previewText.textContent = randomExample + ' (all random)';
            previewGroup.style.display = 'block';
            
            // Re-enable all fields (no custom names)
            enableAllFields();
        }
    }
    
    function disableFieldsForMultipleItems(type) {
        var numberVoucherInput = document.getElementById('numbervoucher');
        var lengthCodeSelect = document.getElementById('lengthcode');
        var numberVoucherHelp = document.getElementById('numbervoucher-help');
        var lengthCodeHelp = document.getElementById('lengthcode-help');
        var lengthCodeDesc = document.getElementById('lengthcode-desc');
        
        // Disable number of vouchers field
        numberVoucherInput.classList.add('field-disabled');
        numberVoucherInput.disabled = true;
        numberVoucherHelp.style.display = 'block';
        
        // Disable length code field
        lengthCodeSelect.classList.add('field-disabled');
        lengthCodeSelect.disabled = true;
        lengthCodeHelp.style.display = 'block';
        lengthCodeDesc.style.display = 'none';
    }
    
    function enableAllFields() {
        var numberVoucherInput = document.getElementById('numbervoucher');
        var lengthCodeSelect = document.getElementById('lengthcode');
        var numberVoucherHelp = document.getElementById('numbervoucher-help');
        var lengthCodeHelp = document.getElementById('lengthcode-help');
        var lengthCodeDesc = document.getElementById('lengthcode-desc');
        
        // Enable number of vouchers field
        numberVoucherInput.classList.remove('field-disabled');
        numberVoucherInput.disabled = false;
        numberVoucherHelp.style.display = 'none';
        
        // Enable length code field
        lengthCodeSelect.classList.remove('field-disabled');
        lengthCodeSelect.disabled = false;
        lengthCodeHelp.style.display = 'none';
        lengthCodeDesc.style.display = 'block';
    }

    // Voucher details modal functionality (similar to list page)
    function viewVoucherDetails(voucherId) {
        // Show modal and loading state
        $('#voucherDetailsModal').modal('show');
        $('#voucher-loading').show();
        $('#voucher-content').hide();
        
        // Make AJAX request to get voucher details
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '{Text::url('')}plan/voucher-details-ajax/' + voucherId, true);
        xhr.onload = function() {
            $('#voucher-loading').hide();
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if (response.status === 'success') {
                        // Populate modal with voucher data
                        document.getElementById('voucher-print-content').textContent = response.content;
                        
                        // Update country code display if provided
                        if (response.country_code) {
                            document.getElementById('country-code-display-add').innerHTML = '<i class="fa fa-phone"></i> +' + response.country_code;
                            window.currentCountryCode = response.country_code;
                        } else {
                            window.currentCountryCode = '254'; // Default to Kenya
                        }
                        
                        // Store voucher data for SMS sending
                        window.currentVoucherData = {
                            id: response.voucher_id,
                            content: response.content
                        };
                        
                        $('#voucher-content').show();
                    } else {
                        showErrorInModal(response.message || 'Failed to load voucher details');
                    }
                } catch (e) {
                    showErrorInModal('Invalid response from server');
                }
            } else {
                showErrorInModal('Failed to load voucher details. Please try again.');
            }
        };
        
        xhr.onerror = function() {
            $('#voucher-loading').hide();
            showErrorInModal('Network error. Please check your connection and try again.');
        };
        
        xhr.send();
    }
    
    function showErrorInModal(message) {
        $('#voucher-content').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle" style="color: #ef4444;"></i> ' + message + '</div>').show();
    }
    
    // SMS and modal event listeners for add page
    $(document).ready(function() {
        // SMS sending functionality
        $('#send-sms-btn-add').on('click', function() {
            var phone = $('#sms-phone-add').val().trim();
            var button = $(this);
            
            if (!phone) {
                showSMSAlert('error', 'Please enter a phone number');
                $('#sms-phone-add').focus();
                return;
            }
            
            // Validate phone number format (must start with 7 or 1 and be 9 digits)
            if (!/^[71][0-9]{8}$/.test(phone)) {
                showSMSAlert('error', 'Phone number must be 9 digits starting with 7 or 1 (e.g., 712345678)');
                $('#sms-phone-add').focus();
                return;
            }
            
            if (!window.currentVoucherData) {
                showSMSAlert('error', 'No voucher data available');
                return;
            }
            
            // Construct full phone number with country code
            var countryCode = window.currentCountryCode || '254';
            var fullPhoneNumber = '+' + countryCode + phone;
            
            // Disable button and show loading state
            button.prop('disabled', true);
            var originalHtml = button.html();
            button.html('<i class="fa fa-spinner fa-spin"></i> Sending SMS...');
            
            // Send AJAX request
            $.ajax({
                url: '{Text::url('')}plan/send-voucher-sms',
                method: 'POST',
                data: {
                    voucher_id: window.currentVoucherData.id,
                    phone: fullPhoneNumber,
                    custom_message: '' // Using default message
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showSMSAlert('success', 'SMS sent successfully to +' + countryCode + phone);
                        $('#sms-phone-add').val(''); // Clear phone input
                    } else {
                        showSMSAlert('error', response.message || 'Failed to send SMS');
                    }
                },
                error: function(xhr, status, error) {
                    showSMSAlert('error', 'Network error: Unable to send SMS');
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false);
                    button.html(originalHtml);
                }
            });
        });
        
        // Phone number input validation and formatting for add page
        $('#sms-phone-add').on('input', function() {
            var value = this.value;
            
            // Remove any non-digit characters
            value = value.replace(/[^0-9]/g, '');
            
            // Limit to 9 digits
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            
            // Ensure it starts with 7 or 1 if user enters something
            if (value.length > 0 && value.charAt(0) !== '7' && value.charAt(0) !== '1') {
                // If doesn't start with 7 or 1, try to correct it
                if (value.charAt(0) === '0') {
                    // Remove leading 0 and add 7 if it makes sense
                    value = '7' + value.substring(1);
                } else {
                    // Default to 7 prefix
                    value = '7' + value.substring(1);
                }
            }
            
            this.value = value;
            
            // Visual feedback for validation
            if (value.length === 9 && /^[71][0-9]{8}$/.test(value)) {
                $(this).removeClass('invalid-input').addClass('valid-input');
            } else if (value.length > 0) {
                $(this).removeClass('valid-input').addClass('invalid-input');
            } else {
                $(this).removeClass('valid-input invalid-input');
            }
        });
        
        // Allow Enter key to send SMS
        $('#sms-phone-add').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $('#send-sms-btn-add').click();
            }
        });
        
        // Check URL parameters for auto-showing voucher details
        var urlParams = new URLSearchParams(window.location.search);
        var voucherId = urlParams.get('voucher_id');
        var showPopup = urlParams.get('show_popup');
        
        if (voucherId && showPopup === 'true') {
            // Auto-show the voucher details popup
            setTimeout(function() {
                viewVoucherDetails(voucherId);
            }, 500);
        }
    });
    
    // Function to validate form before submission
    function validateAndConfirm(button) {
        // Check required fields
        var type = document.querySelector('input[name="type"]:checked');
        var server = document.getElementById('server').value;
        var plan = document.getElementById('plan').value;
        
        var missingFields = [];
        if (!type) missingFields.push('Type (Hotspot)');
        if (!server) missingFields.push('Routers');
        if (!plan) missingFields.push('Service Plan');
        
        if (missingFields.length > 0) {
            alert('Please select the following required fields:\n\n? ' + missingFields.join('\n? '));
            return false;
        }
        
        // If validation passes, show confirmation
        return ask(button, '{Lang::T("Continue the Voucher creation process?")}');
    }
    
    // Function to show SMS alerts (add page version)
    function showSMSAlert(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
                       '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                       '<span aria-hidden="true">&times;</span>' +
                       '</button>' +
                       '<i class="fa ' + iconClass + '" style="color: ' + (type === 'success' ? '#22c55e' : '#ef4444') + ';"></i> ' + message +
                       '</div>';
        
        // Insert alert at the top of modal body
        $('.modal-body').prepend(alertHtml);
        
        // Auto-remove alert after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
</script>

{/if}

{include file="sections/footer.tpl"}