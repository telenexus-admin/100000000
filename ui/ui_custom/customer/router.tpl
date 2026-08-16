{include file="customer/header.tpl"}

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-primary panel-hovered">
            <div class="panel-heading">
                <i class="fa fa-wifi"></i> Router/Modem Management
            </div>
            <div class="panel-body">
                
                <!-- Loading State -->
                <div id="loading-state" class="text-center" style="padding: 40px;">
                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                    <p style="margin-top: 15px;">Loading device information...</p>
                </div>

                <!-- Error State -->
                <div id="error-state" style="display: none;">
                    <div class="error-card text-center">
                        <div class="error-icon">
                            <i class="fa fa-wifi"></i>
                            <i class="fa fa-times error-x"></i>
                        </div>
                        <h3 class="error-title">Modem Not Found</h3>
                        <p class="error-desc">Sorry, we couldn't find a modem connected to your account.</p>
                        <div class="error-tips">
                            <p><strong>Possible causes:</strong></p>
                            <ul>
                                <li>Modem is not registered in the system</li>
                                <li>Modem is offline or restarting</li>
                                <li>Connection to server lost</li>
                            </ul>
                        </div>
                        <p class="error-contact">Please contact <strong>Customer Service</strong> if the problem persists.</p>
                        <button class="btn btn-primary btn-retry" onclick="loadDeviceInfo()">
                            <i class="fa fa-refresh"></i> Try Again
                        </button>
                    </div>
                </div>

                <!-- Device Content -->
                <div id="device-content" style="display: none;">
                    
                    <!-- Info Cards Row -->
                    <div class="row">
                        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                            <div class="info-card card-info">
                                <div class="card-icon"><i class="fa fa-hdd-o"></i></div>
                                <div class="card-label">Model</div>
                                <div class="card-value" id="device-model">-</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                            <div class="info-card card-success" id="status-card">
                                <div class="card-icon"><i class="fa fa-check-circle"></i></div>
                                <div class="card-label">Status</div>
                                <div class="card-value"><span id="device-status">-</span></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                            <div class="info-card card-warning">
                                <div class="card-icon"><i class="fa fa-fire"></i></div>
                                <div class="card-label">Temperature</div>
                                <div class="card-value" id="pon-temperature">-</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
                            <div class="info-card">
                                <div class="card-icon"><i class="fa fa-signal"></i></div>
                                <div class="card-label">RX Power</div>
                                <div class="card-value" id="pon-rx-power">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Uptime Card -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="wifi-card">
                                <div class="wifi-info">
                                    <span class="wifi-label"><i class="fa fa-clock-o"></i> Connection Uptime</span>
                                    <span class="wifi-value" id="device-uptime">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WiFi Cards Row -->
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="wifi-card">
                                <div class="wifi-header">
                                    <span class="wifi-title"><i class="fa fa-wifi"></i> WiFi 2.4GHz</span>
                                    <span class="wifi-badge">2.4G</span>
                                </div>
                                <div class="wifi-info">
                                    <span class="wifi-label">Network Name (SSID)</span>
                                    <span class="wifi-value" id="wifi-ssid-2g">-</span>
                                </div>
                                <div class="wifi-info">
                                    <span class="wifi-label">Connected Devices</span>
                                    <span class="wifi-value" id="wifi-connected-2g">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <div class="wifi-card">
                                <div class="wifi-header">
                                    <span class="wifi-title"><i class="fa fa-wifi"></i> WiFi 5GHz</span>
                                    <span class="wifi-badge badge-5g">5G</span>
                                </div>
                                <div class="wifi-info">
                                    <span class="wifi-label">Network Name (SSID)</span>
                                    <span class="wifi-value" id="wifi-ssid-5g">-</span>
                                </div>
                                <div class="wifi-info">
                                    <span class="wifi-label">Connected Devices</span>
                                    <span class="wifi-value" id="wifi-connected-5g">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WiFi Settings Form -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-card">
                                <div class="form-title">
                                    <i class="fa fa-edit"></i> Change WiFi Settings
                                </div>
                                <form id="wifi-form" onsubmit="return updateWiFi(event)">
                                    <div class="row">
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label><i class="fa fa-wifi"></i> WiFi Name (SSID)</label>
                                                <input type="text" id="new-ssid" class="form-control" placeholder="Enter new WiFi name" required minlength="1" maxlength="32">
                                                <small class="text-muted">5GHz will automatically add "-5G" suffix</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label><i class="fa fa-lock"></i> WiFi Password</label>
                                                <div class="input-group">
                                                    <input type="password" id="new-password" class="form-control" placeholder="Enter new password (min 8 chars)" minlength="8">
                                                    <span class="input-group-btn">
                                                        <button type="button" class="btn btn-default" onclick="togglePassword()" style="border-radius: 0 10px 10px 0;">
                                                            <i class="fa fa-eye" id="password-icon"></i>
                                                        </button>
                                                    </span>
                                                </div>
                                                <small class="text-muted">Leave empty to keep current password</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="checkbox" style="margin-bottom: 20px;">
                                                <label>
                                                    <input type="checkbox" id="force-security"> 
                                                    <strong>Force WPA2 Security</strong> - Enable if password change doesn't work
                                                </label>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-block" id="btn-update-wifi">
                                                <i class="fa fa-save"></i> Save WiFi Settings
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Connected Devices -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="devices-card">
                                <div class="devices-header">
                                    <span class="devices-title"><i class="fa fa-laptop"></i> Connected Devices</span>
                                    <span class="devices-count" id="total-connected">0</span>
                                </div>
                                
                                <!-- Loading -->
                                <div id="connected-devices-loading" class="text-center" style="padding: 30px;">
                                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                                    <p style="margin-top: 10px; color: #666;">Loading devices...</p>
                                </div>
                                
                                <!-- Empty State -->
                                <div id="connected-devices-empty" style="display: none; text-align: center; padding: 30px;">
                                    <i class="fa fa-wifi fa-3x" style="color: #ddd;"></i>
                                    <p style="margin-top: 10px; color: #999;">No devices connected</p>
                                </div>

                                <!-- Devices List -->
                                <div id="connected-devices-list" style="display: none;">
                                    <div id="devices-container"></div>
                                </div>

                                <button class="btn btn-default btn-block" onclick="loadConnectedDevices()" style="margin-top: 15px; border-radius: 10px;">
                                    <i class="fa fa-refresh"></i> Refresh Devices
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="action-card">
                                <div class="action-title"><i class="fa fa-cogs"></i> Device Control</div>
                                <div class="row">
                                    <div class="col-md-4 col-sm-12">
                                        <button class="action-btn btn-info btn-block" onclick="refreshDevice()">
                                            <i class="fa fa-refresh"></i> Refresh Data
                                        </button>
                                    </div>
                                    <div class="col-md-4 col-sm-12">
                                        <button class="action-btn btn-warning btn-block" onclick="summonDevice()">
                                            <i class="fa fa-bolt"></i> Summon Device
                                        </button>
                                    </div>
                                    <div class="col-md-4 col-sm-12">
                                        <button class="action-btn btn-danger btn-block" onclick="rebootDevice()">
                                            <i class="fa fa-power-off"></i> Reboot Modem
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- End Device Content -->

            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="{$app_url}/ui/ui/scripts/sweetalert2.all.min.js"></script>

<!-- Custom CSS for Beautiful Cards -->
<style>
/* =============== INFO CARDS =============== */
.info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
    margin-bottom: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.info-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.info-card.card-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.info-card.card-warning {
    background: linear-gradient(135deg, #F2994A 0%, #F2C94C 100%);
}

.info-card.card-info {
    background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
}

.info-card.card-danger {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
}

.info-card .card-icon {
    font-size: 2.5rem;
    opacity: 0.8;
    margin-bottom: 10px;
}

.info-card .card-label {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.info-card .card-value {
    font-size: 1.5rem;
    font-weight: 700;
}

/* =============== WIFI CARD =============== */
.wifi-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #eee;
}

.wifi-card .wifi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.wifi-card .wifi-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.wifi-card .wifi-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.wifi-card .wifi-badge.badge-5g {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.wifi-card .wifi-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.wifi-card .wifi-label {
    color: #666;
    font-size: 0.9rem;
}

.wifi-card .wifi-value {
    font-weight: 600;
    color: #333;
}

/* =============== FORM CARD =============== */
.form-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #eee;
}

.form-card .form-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.form-card .form-title i {
    margin-right: 10px;
    color: #667eea;
}

.form-card .form-control {
    border-radius: 10px;
    border: 2px solid #eee;
    padding: 12px 15px;
    transition: border-color 0.3s ease;
    background: white;
    color: #333;
}

.form-card .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-card label {
    color: #333;
}

.form-card .text-muted {
    color: #888 !important;
}

.form-card .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 10px;
    padding: 12px 25px;
    font-weight: 600;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.form-card .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

/* =============== ACTION BUTTONS =============== */
.action-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #eee;
}

.action-card .action-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
}

.action-btn {
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 600;
    margin-right: 10px;
    margin-bottom: 10px;
    transition: transform 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.action-btn.btn-info {
    background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
    border: none;
    color: white;
}

.action-btn.btn-warning {
    background: linear-gradient(135deg, #F2994A 0%, #F2C94C 100%);
    border: none;
    color: white;
}

.action-btn.btn-danger {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    border: none;
    color: white;
}

/* =============== CONNECTED DEVICES =============== */
.devices-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border: 1px solid #eee;
}

.devices-card .devices-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.devices-card .devices-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.devices-card .devices-count {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 5px 15px;
    border-radius: 15px;
    font-weight: 600;
}

.device-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 10px;
    margin-bottom: 10px;
}

.device-item .device-name {
    font-weight: 600;
    color: #333;
}

.device-item .device-ip {
    color: #666;
    font-size: 0.85rem;
}

.device-item .device-type {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
}

.device-item .device-type.type-2g {
    background: #e3f2fd;
    color: #1976d2;
}

.device-item .device-type.type-5g {
    background: #e8f5e9;
    color: #388e3c;
}

.device-item .device-type.type-lan {
    background: #fff3e0;
    color: #f57c00;
}

/* =============== DARK MODE SUPPORT =============== */
.dark-mode .wifi-card,
.dark-mode .form-card,
.dark-mode .action-card,
.dark-mode .devices-card {
    background: #1e1e2d;
    border-color: #2d2d3a;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.dark-mode .wifi-card .wifi-header {
    border-bottom-color: #2d2d3a;
}

.dark-mode .wifi-card .wifi-title,
.dark-mode .wifi-card .wifi-value,
.dark-mode .form-card .form-title,
.dark-mode .action-card .action-title,
.dark-mode .devices-card .devices-title,
.dark-mode .device-item .device-name {
    color: #ffffff;
}

.dark-mode .wifi-card .wifi-label,
.dark-mode .device-item .device-ip {
    color: #a0a0a0;
}

.dark-mode .form-card .form-title {
    border-bottom-color: #2d2d3a;
}

.dark-mode .form-card .form-title i {
    color: #667eea;
}

.dark-mode .form-card .form-control {
    background: #2d2d3a;
    border-color: #3d3d4a;
    color: #ffffff;
}

.dark-mode .form-card .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.dark-mode .form-card .form-control::placeholder {
    color: #666;
}

.dark-mode .form-card label {
    color: #ffffff;
}

.dark-mode .form-card .text-muted {
    color: #888 !important;
}

.dark-mode .form-card .checkbox label {
    color: #a0a0a0;
}

.dark-mode .device-item {
    background: #2d2d3a;
}

.dark-mode .device-item .device-type.type-2g {
    background: rgba(25, 118, 210, 0.2);
    color: #64b5f6;
}

.dark-mode .device-item .device-type.type-5g {
    background: rgba(56, 142, 60, 0.2);
    color: #81c784;
}

.dark-mode .device-item .device-type.type-lan {
    background: rgba(245, 124, 0, 0.2);
    color: #ffb74d;
}

.dark-mode .devices-card .btn-default {
    background: #2d2d3a;
    border-color: #3d3d4a;
    color: #ffffff;
}

.dark-mode .devices-card .btn-default:hover {
    background: #3d3d4a;
}

/* =============== RESPONSIVE =============== */
@media (max-width: 768px) {
    .info-card {
        padding: 15px;
    }
    
    .info-card .card-icon {
        font-size: 2rem;
    }
    
    .info-card .card-value {
        font-size: 1.2rem;
    }
    
    .wifi-card, .form-card, .action-card, .devices-card {
        padding: 15px;
    }
    
    .action-btn {
        width: 100%;
        margin-right: 0;
    }
    
    .device-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .device-item .device-type {
        align-self: flex-start;
    }
}
/* =============== ERROR CARD =============== */
.error-card {
    background: white;
    border-radius: 20px;
    padding: 40px 30px;
    max-width: 450px;
    margin: 20px auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.error-icon {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.error-icon > .fa-wifi {
    font-size: 2.5rem;
    color: white;
}

.error-icon .error-x {
    position: absolute;
    bottom: -5px;
    right: -5px;
    background: #fff;
    color: #ee5a5a;
    font-size: 1.2rem;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.error-title {
    color: #333;
    font-weight: 700;
    margin-bottom: 10px;
}

.error-desc {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 20px;
}

.error-tips {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px 20px;
    text-align: left;
    margin-bottom: 20px;
}

.error-tips p {
    color: #333;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.error-tips ul {
    margin: 0;
    padding-left: 20px;
    color: #666;
    font-size: 0.85rem;
}

.error-tips li {
    margin-bottom: 5px;
}

.error-contact {
    color: #888;
    font-size: 0.85rem;
    margin-bottom: 20px;
}

.btn-retry {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 25px;
    padding: 12px 30px;
    font-weight: 600;
    color: white;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.btn-retry:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Dark Mode Error Card */
.dark-mode .error-card {
    background: #1e1e2d;
}

.dark-mode .error-title {
    color: #ffffff;
}

.dark-mode .error-desc {
    color: #a0a0a0;
}

.dark-mode .error-tips {
    background: #2d2d3a;
}

.dark-mode .error-tips p {
    color: #ffffff;
}

.dark-mode .error-tips ul {
    color: #a0a0a0;
}

.dark-mode .error-contact {
    color: #888;
}

.dark-mode .error-icon .error-x {
    background: #1e1e2d;
}
</style>

<script>
var deviceId = null;

// Load device info on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDeviceInfo();
});

function loadDeviceInfo() {
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('error-state').style.display = 'none';
    document.getElementById('device-content').style.display = 'none';

    $.ajax({
        url: '{$_url}plugin/modemSettings_getDeviceInfo',
        type: 'GET',
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            document.getElementById('loading-state').style.display = 'none';
            
            if (response && response.success) {
                deviceId = response.device_id;
                displayDeviceInfo(response);
                document.getElementById('device-content').style.display = 'block';
                loadConnectedDevices();
            } else {
                document.getElementById('error-state').style.display = 'block';
            }
        },
        error: function(xhr, status, error) {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
        }
    });
}

function displayDeviceInfo(data) {
    var device = data.device_info || {};
    var wifi = data.wifi_info || {};

    // Device Info
    document.getElementById('device-model').textContent = device.model || device.product_class || 'N/A';
    document.getElementById('device-uptime').textContent = device.ppp_uptime || device.uptime || 'N/A';
    
    // Status with card color change
    var statusEl = document.getElementById('device-status');
    var statusCard = document.getElementById('status-card');
    var isOnline = false;
    
    if (device.status && device.status.toLowerCase() === 'online') {
        isOnline = true;
    } else if (device.is_online === true || device.is_online === 'true' || device.is_online === 1) {
        isOnline = true;
    }
    
    if (isOnline) {
        statusEl.innerHTML = 'Online';
        statusCard.className = 'info-card card-success';
        statusCard.querySelector('.card-icon').innerHTML = '<i class="fa fa-check-circle"></i>';
    } else {
        statusEl.innerHTML = 'Offline';
        statusCard.className = 'info-card card-danger';
        statusCard.querySelector('.card-icon').innerHTML = '<i class="fa fa-times-circle"></i>';
    }

    // PON Info
    document.getElementById('pon-rx-power').textContent = device.rx_power || wifi.rx_power || 'N/A';
    document.getElementById('pon-temperature').textContent = device.temperature || wifi.temperature || 'N/A';

    // WiFi Info
    document.getElementById('wifi-ssid-2g').textContent = wifi.ssid_2g || wifi.ssid || 'N/A';
    document.getElementById('wifi-ssid-5g').textContent = wifi.ssid_5g || 'N/A';
    document.getElementById('wifi-connected-2g').textContent = wifi.total_2g || 0;
    document.getElementById('wifi-connected-5g').textContent = wifi.total_5g || 0;

    // Pre-fill SSID
    var ssidInput = document.getElementById('new-ssid');
    if (wifi.ssid_2g || wifi.ssid) {
        ssidInput.value = wifi.ssid_2g || wifi.ssid;
    }

    // Pre-fill Password from database
    var passwordInput = document.getElementById('new-password');
    if (wifi.password && wifi.password !== 'N/A' && wifi.password !== '') {
        passwordInput.value = wifi.password;
    }
}

function togglePassword() {
    var passInput = document.getElementById('new-password');
    var icon = document.getElementById('password-icon');
    
    if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        passInput.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

function updateWiFi(event) {
    event.preventDefault();
    
    var ssid = document.getElementById('new-ssid').value.trim();
    var password = document.getElementById('new-password').value;
    var forceSecurity = document.getElementById('force-security').checked;
    var btnSubmit = document.getElementById('btn-update-wifi');

    if (!ssid) {
        Swal.fire('Error', 'SSID is required', 'error');
        return false;
    }

    if (password && password.length < 8) {
        Swal.fire('Error', 'Password must be at least 8 characters', 'error');
        return false;
    }

    Swal.fire({
        title: 'Update WiFi Settings?',
        text: 'Your modem will apply the new settings. This may take a moment.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Update',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            // Disable button & show loading on button
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Updating...';

            $.ajax({
                url: '{$_url}plugin/modemSettings_updateWifi',
                type: 'POST',
                data: {
                    ssid: ssid,
                    password: password,
                    force_security: forceSecurity ? '1' : '0'
                },
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message || 'WiFi settings updated successfully',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        // Reset button on error
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = '<i class="fa fa-save"></i> Save WiFi Settings';
                        Swal.fire('Error', response.error || 'Failed to update WiFi settings', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    // Reset button on error
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fa fa-save"></i> Save WiFi Settings';
                    Swal.fire('Error', 'Connection error: ' + error, 'error');
                }
            });
        }
    });

    return false;
}

function loadConnectedDevices() {
    document.getElementById('connected-devices-loading').style.display = 'block';
    document.getElementById('connected-devices-empty').style.display = 'none';
    document.getElementById('connected-devices-list').style.display = 'none';

    $.ajax({
        url: '{$_url}plugin/modemSettings_getConnectedDevices',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            document.getElementById('connected-devices-loading').style.display = 'none';
            
            if (response && response.success && response.devices && response.devices.length > 0) {
                displayConnectedDevices(response.devices);
                document.getElementById('total-connected').textContent = response.devices.length;
                document.getElementById('connected-devices-list').style.display = 'block';
            } else {
                document.getElementById('connected-devices-empty').style.display = 'block';
                document.getElementById('total-connected').textContent = '0';
            }
        },
        error: function() {
            document.getElementById('connected-devices-loading').style.display = 'none';
            document.getElementById('connected-devices-empty').style.display = 'block';
            document.getElementById('total-connected').textContent = '0';
        }
    });
}

function displayConnectedDevices(devices) {
    var container = document.getElementById('devices-container');
    container.innerHTML = '';

    devices.forEach(function(device) {
        var connType = 'Unknown';
        var typeClass = '';

        if (device.connection_type) {
            if (device.connection_type === 'WiFi 5GHz') {
                connType = '5GHz';
                typeClass = 'type-5g';
            } else if (device.connection_type === 'WiFi 2.4GHz') {
                connType = '2.4GHz';
                typeClass = 'type-2g';
            } else if (device.connection_type === 'Ethernet') {
                connType = 'LAN';
                typeClass = 'type-lan';
            } else {
                connType = device.connection_type;
                typeClass = 'type-2g';
            }
        } else {
            typeClass = 'type-2g';
        }

        var item = '<div class="device-item">' +
            '<div>' +
                '<div class="device-name">' + (device.hostname || 'Unknown Device') + '</div>' +
                '<div class="device-ip">' + (device.ip || 'N/A') + ' • ' + (device.mac || 'N/A') + '</div>' +
            '</div>' +
            '<span class="device-type ' + typeClass + '">' + connType + '</span>' +
            '</div>';
        
        container.innerHTML += item;
    });
}

function refreshDevice() {
    Swal.fire({
        title: 'Refresh Device?',
        text: 'This will refresh all device parameters from the modem.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Refresh'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Refreshing...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: '{$_url}plugin/modemSettings_refresh',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire('Success!', response.message, 'success').then(function() {
                            loadDeviceInfo();
                        });
                    } else {
                        Swal.fire('Error', response.error || 'Failed to refresh', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Connection error', 'error');
                }
            });
        }
    });
}

function summonDevice() {
    Swal.fire({
        title: 'Summon Device?',
        text: 'This will send a connection request to your modem.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Summon'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sending request...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: '{$_url}plugin/modemSettings_summon',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire('Success!', response.message, 'success');
                    } else {
                        Swal.fire('Error', response.error || 'Failed to summon', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Connection error', 'error');
                }
            });
        }
    });
}

function rebootDevice() {
    Swal.fire({
        title: 'Reboot Modem?',
        text: 'Your internet connection will be temporarily disconnected. Are you sure?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Reboot',
        confirmButtonColor: '#d33'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Rebooting...',
                text: 'Please wait, this may take a few minutes',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: '{$_url}plugin/modemSettings_reboot',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        Swal.fire('Success!', response.message, 'success');
                    } else {
                        Swal.fire('Error', response.error || 'Failed to reboot', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Connection error', 'error');
                }
            });
        }
    });
}
</script>

{include file="customer/footer.tpl"}