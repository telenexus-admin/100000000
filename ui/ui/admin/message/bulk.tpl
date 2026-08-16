{include file="sections/header.tpl"}
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
{literal}
    .bm-wrap { }
    .bm-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06), 0 1px 2px rgba(15, 23, 42, .04);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .bm-card-head {
        padding: 16px 20px;
        border-bottom: 1px solid #eef2f7;
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: #fff;
    }
    .bm-card-head.alt { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
    .bm-card-head .ico {
        width: 36px; height: 36px; border-radius: 10px;
        background: rgba(255,255,255,.18);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 15px;
    }
    .bm-card-head .title { font-size: 15px; font-weight: 600; letter-spacing: .2px; margin: 0; }
    .bm-card-head .sub { font-size: 12px; opacity: .8; margin-top: 2px; }
    .bm-card-body { padding: 16px 18px; }

    .bm-section-title {
        display: flex; align-items: center; gap: 8px;
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px;
        color: #6b7280; margin: 2px 0 10px;
    }
    .bm-section-title i { color: #6366f1; }

    .bm-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 10px 14px; }
    .bm-col-3 { grid-column: span 3; }
    .bm-col-4 { grid-column: span 4; }
    .bm-col-6 { grid-column: span 6; }
    .bm-col-8 { grid-column: span 8; }
    .bm-col-12 { grid-column: span 12; }
    @media (max-width: 992px) {
        .bm-col-3, .bm-col-4 { grid-column: span 6; }
        .bm-col-6 { grid-column: span 12; }
        .bm-col-8 { grid-column: span 12; }
    }
    @media (max-width: 576px) {
        .bm-col-3, .bm-col-4, .bm-col-6 { grid-column: span 12; }
    }

    .bm-field label {
        display: block;
        font-size: 11px; font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
    }
    .bm-field .form-control {
        height: 32px;
        padding: 4px 10px;
        font-size: 13px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        box-shadow: none;
        line-height: 1.3;
    }
    .bm-field select.form-control { padding-right: 28px; }
    .bm-field textarea.form-control { height: auto; min-height: 90px; padding: 8px 10px; }
    .bm-field .form-control:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    }
    .bm-field .hint { font-size: 11px; color: #9ca3af; margin-top: 4px; line-height: 1.35; }

    .bm-placeholders { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .bm-chip {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px;
        padding: 4px 9px;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #e0e7ff;
        cursor: pointer;
        user-select: none;
        transition: all .15s ease;
    }
    .bm-chip:hover { background: #4338ca; color: #fff; }
    .bm-chip small { opacity: .8; margin-left: 4px; font-family: inherit; }

    .bm-toolbar {
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
        padding: 14px 20px; background: #f9fafb; border-top: 1px solid #eef2f7;
    }
    .bm-toolbar .test-wrap {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 12px; background: #fffbeb; color: #92400e;
        border: 1px solid #fcd34d; border-radius: 8px; font-size: 12px;
        margin-left: auto;
    }
    .bm-btn {
        border-radius: 8px; font-weight: 600; padding: 9px 18px;
        display: inline-flex; align-items: center; gap: 8px;
        border: none;
    }
    .bm-btn.primary { background: #4f46e5; color: #fff; }
    .bm-btn.primary:hover { background: #4338ca; color: #fff; }
    .bm-btn.warn { background: #f59e0b; color: #fff; }
    .bm-btn.ok { background: #10b981; color: #fff; }
    .bm-btn.danger { background: #ef4444; color: #fff; }
    .bm-btn.ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .bm-btn.ghost:hover { background: #f3f4f6; color: #111827; }

    .bm-stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-top: 16px; }
    @media (max-width: 768px) { .bm-stat-row { grid-template-columns: repeat(2,1fr); } }
    .bm-stat {
        padding: 14px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #eef2f7;
        text-align: center;
    }
    .bm-stat .value { font-size: 26px; font-weight: 700; margin: 0; line-height: 1; }
    .bm-stat .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .6px; margin-top: 6px; }
    .bm-stat.sent .value { color: #10b981; }
    .bm-stat.failed .value { color: #ef4444; }
    .bm-stat.speed .value { color: #3b82f6; }
    .bm-stat.eta .value { color: #f59e0b; }

    .bm-progress-bar {
        height: 14px; background: #eef2f7; border-radius: 999px; overflow: hidden;
        margin-top: 6px;
    }
    .bm-progress-bar .fill {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        width: 0%;
        color: #fff; text-align: center; font-size: 10px; line-height: 14px; font-weight: 600;
        transition: width .35s ease;
    }

    #historyTable thead th {
        background: #f8fafc;
        color: #374151;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .4px;
        border-bottom: 2px solid #e5e7eb;
    }
    #historyTable tbody td { font-size: 13px; vertical-align: middle; }

    .label-danger { background-color: #ef4444; }
    .label-success { background-color: #10b981; }
{/literal}
</style>

<div class="bm-wrap">
    <div id="status"></div>

    <div class="bm-card {if $page>0 && $totalCustomers >0}hidden{/if}">
        <div class="bm-card-head">
            <span class="ico"><i class="fa fa-paper-plane"></i></span>
            <div>
                <div class="title">{Lang::T('Send Bulk Message')}</div>
                <div class="sub">{Lang::T('Reach your customers via SMS or WhatsApp in batches')}</div>
            </div>
        </div>
        <div class="bm-card-body">
            <form class="" method="get" role="form" id="bulkMessageForm" action="">
                <input type="hidden" name="page" value="{if $page>0 && $totalCustomers==0}-1{else}{$page}{/if}">

                <div class="bm-section-title"><i class="fa fa-users"></i> {Lang::T('Audience')}</div>
                <div class="bm-grid">
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Router')}</label>
                        <select class="form-control select2" name="router" id="router">
                            <option value="">{Lang::T('All Routers')}</option>
                            {if $_c['radius_enable']}
                                <option value="radius">{Lang::T('Radius')}</option>
                            {/if}
                            {foreach $routers as $router}
                                <option value="{$router['id']}">{$router['name']}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Service Type')}</label>
                        <select class="form-control" name="service" id="service">
                            <option value="all" {if $group=='all'}selected{/if}>{Lang::T('All')}</option>
                            <option value="PPPoE" {if $service=='PPPoE'}selected{/if}>{Lang::T('PPPoE')}</option>
                            <option value="Hotspot" {if $service=='Hotspot'}selected{/if}>{Lang::T('Hotspot')}</option>
                        </select>
                    </div>
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Group')}</label>
                        <select class="form-control" name="group" id="group">
                            <option value="all" {if $group=='all'}selected{/if}>{Lang::T('All Customers')}</option>
                            <option value="new" {if $group=='new'}selected{/if}>{Lang::T('New Customers')}</option>
                            <option value="expired" {if $group=='expired'}selected{/if}>{Lang::T('Expired Customers')}</option>
                            <option value="active" {if $group=='active'}selected{/if}>{Lang::T('Active Customers')}</option>
                        </select>
                    </div>
                </div>

                <div class="bm-section-title" style="margin-top:14px;"><i class="fa fa-sliders-h"></i> {Lang::T('Delivery Settings')}</div>
                <div class="bm-grid">
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Send Via')}</label>
                        <select class="form-control" name="via" id="via">
                            <option value="sms" {if $via=='sms'}selected{/if}>SMS</option>
                            <option value="wa" {if $via=='wa'}selected{/if}>WhatsApp</option>
                            <option value="both" {if $via=='both'}selected{/if}>SMS + WhatsApp</option>
                        </select>
                    </div>
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Messages per batch')}</label>
                        <select class="form-control" name="batch" id="batch">
                            <option value="25" {if $batch=='25'}selected{/if}>25 {Lang::T('Messages')}</option>
                            <option value="50" {if $batch=='50'}selected{/if}>50 {Lang::T('Messages')}</option>
                            <option value="75" {if $batch=='75'}selected{/if}>75 {Lang::T('Messages')}</option>
                            <option value="100" {if $batch=='100'}selected{/if}>100 {Lang::T('Messages')}</option>
                            <option value="150" {if $batch=='150'}selected{/if}>150 {Lang::T('Messages')}</option>
                            <option value="200" {if $batch=='200'}selected{/if}>200 {Lang::T('Messages')}</option>
                            <option value="auto" {if $batch=='auto'}selected{/if}>{Lang::T('Auto (Optimal Speed)')}</option>
                        </select>
                        <div class="hint">{Lang::T('Auto mode adjusts batch size based on SMS gateway for optimal performance')}</div>
                    </div>
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Processing Speed')}</label>
                        <select class="form-control" name="speed" id="speed">
                            <option value="fast">{Lang::T('Fast (Recommended)')}</option>
                            <option value="medium">{Lang::T('Medium (Balanced)')}</option>
                            <option value="slow">{Lang::T('Slow (Conservative)')}</option>
                        </select>
                        <div class="hint">{Lang::T('Fast: 0.5s delay, Medium: 1s delay, Slow: 2s delay between batches')}</div>
                    </div>
                </div>

                <div class="bm-section-title" style="margin-top:14px;"><i class="fa fa-pen"></i> {Lang::T('Compose')}</div>
                <div class="bm-grid">
                    <div class="bm-col-8 bm-field">
                        <label>{Lang::T('Message')}</label>
                        <textarea class="form-control" id="message" name="message" required placeholder="{Lang::T('Compose your message...')}" rows="4">{$message}</textarea>
                        <div class="bm-placeholders">
                            <span class="bm-chip" data-insert="[[name]]">[[name]]<small>{Lang::T('Customer Name')}</small></span>
                            <span class="bm-chip" data-insert="[[user_name]]">[[user_name]]<small>{Lang::T('Username')}</small></span>
                            <span class="bm-chip" data-insert="[[phone]]">[[phone]]<small>{Lang::T('Phone')}</small></span>
                            <span class="bm-chip" data-insert="[[company_name]]">[[company_name]]<small>{Lang::T('Company')}</small></span>
                        </div>
                    </div>
                    <div class="bm-col-4 bm-field">
                        <label>{Lang::T('Options')}</label>
                        <div style="padding:8px 10px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">
                            <label style="display:flex; align-items:flex-start; gap:8px; margin:0; cursor:pointer; font-size:12px;">
                                <input name="test" id="test" type="checkbox" style="margin-top:2px;">
                                <span style="font-weight:500; color:#374151;">
                                    {Lang::T('Test mode')}
                                    <div class="hint" style="margin-top:1px;">{Lang::T('If checked, no real message is sent')}</div>
                                </span>
                            </label>
                        </div>
                        <div id="estimateDisplay" style="margin-top:10px;"></div>
                    </div>
                </div>

                <!-- Progress Section -->
                <div id="progressSection" style="display:none; margin-top:22px;">
                    <div class="bm-section-title"><i class="fa fa-chart-line"></i> {Lang::T('Sending Progress')}</div>
                    <div class="bm-progress-bar">
                        <div id="progressBar" class="fill" style="width: 0%;"><span id="progressText">0%</span></div>
                    </div>
                    <div class="bm-stat-row">
                        <div class="bm-stat sent">
                            <p class="value" id="totalSentCount">0</p>
                            <div class="label">{Lang::T('Sent')}</div>
                        </div>
                        <div class="bm-stat failed">
                            <p class="value" id="totalFailedCount">0</p>
                            <div class="label">{Lang::T('Failed')}</div>
                        </div>
                        <div class="bm-stat speed">
                            <p class="value" id="sendingSpeed">0</p>
                            <div class="label">{Lang::T('SMS/min')}</div>
                        </div>
                        <div class="bm-stat eta">
                            <p class="value" id="estimatedTime">--</p>
                            <div class="label">{Lang::T('Est. Time Left')}</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="bm-toolbar">
            <button type="button" id="startBulk" class="bm-btn primary">
                <i class="fas fa-paper-plane"></i> {Lang::T('Start Bulk Messaging')}
            </button>
            <button type="button" id="pauseBulk" class="bm-btn warn" style="display:none;">
                <i class="fas fa-pause"></i> {Lang::T('Pause')}
            </button>
            <button type="button" id="resumeBulk" class="bm-btn ok" style="display:none;">
                <i class="fas fa-play"></i> {Lang::T('Resume')}
            </button>
            <button type="button" id="stopBulk" class="bm-btn danger" style="display:none;">
                <i class="fas fa-stop"></i> {Lang::T('Stop')}
            </button>
            <a href="{Text::url('dashboard')}" class="bm-btn ghost">{Lang::T('Cancel')}</a>
        </div>
    </div>

    <div class="bm-card">
        <div class="bm-card-head alt">
            <span class="ico"><i class="fa fa-history"></i></span>
            <div>
                <div class="title">{Lang::T('Message Sending History')}</div>
                <div class="sub">{Lang::T('Per-recipient status of the current session')}</div>
            </div>
        </div>
        <div class="bm-card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="historyTable" style="width:100%;">
                    <thead>
                        <tr>
                            <th>{Lang::T('Customer')}</th>
                            <th>{Lang::T('Phone')}</th>
                            <th>{Lang::T('Status')}</th>
                            <th>{Lang::T('Message')}</th>
                            <th>{Lang::T('Router')}</th>
                            <th>{Lang::T('Service Type')}</th>
                            <th>{Lang::T('Plan')}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
{literal}
<script>
    let page = 0;
    let totalSent = 0;
    let totalFailed = 0;
    let hasMore = true;
    let isPaused = false;
    let isStopped = false;
    let startTime = null;
    let batchDelays = { fast: 500, medium: 1000, slow: 2000 };
    let adaptiveBatchSize = 50;
    let retryAttempts = 0;
    let maxRetries = 3;

    // Initialize DataTable with better performance
    let historyTable = $('#historyTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        responsive: true,
        pageLength: 25,
        deferRender: true,
        processing: true,
        language: {
            processing: '<i class="fas fa-spinner fa-spin"></i> Processing...'
        }
    });

    // Auto-detect optimal batch size based on SMS gateway
    function getOptimalBatchSize() {
        const via = $('#via').val();
        const batch = $('#batch').val();
        
        if (batch !== 'auto') {
            return parseInt(batch);
        }
        
        // Adaptive batch sizes based on gateway capabilities
        switch(via) {
            case 'sms':
                return 100; // SMS gateways generally handle larger batches
            case 'wa':
                return 50;  // WhatsApp might be more rate-limited
            case 'both':
                return 30;  // Conservative for dual sending
            default:
                return 50;
        }
    }

    function updateProgress() {
        if (!startTime) return;
        
        const elapsed = (Date.now() - startTime) / 1000; // seconds
        const totalProcessed = totalSent + totalFailed;
        const speed = totalProcessed > 0 ? Math.round((totalProcessed / elapsed) * 60) : 0; // per minute
        
        $('#sendingSpeed').text(speed);
        
        // Estimate time remaining
        if (speed > 0 && hasMore) {
            const estimated = Math.round((totalProcessed * elapsed / totalSent) - elapsed);
            const mins = Math.floor(estimated / 60);
            const secs = estimated % 60;
            $('#estimatedTime').text(mins > 0 ? mins + 'm ' + secs + 's' : secs + 's');
        }
    }

    function updateUI(response) {
        $('#totalSentCount').text(totalSent.toLocaleString());
        $('#totalFailedCount').text(totalFailed.toLocaleString());
        
        // Update progress bar with actual percentage if we have total customers
        if (response && response.totalCustomers) {
            const processed = totalSent + totalFailed;
            const percentage = Math.round((processed / response.totalCustomers) * 100);
            $('#progressBar').css('width', percentage + '%');
            $('#progressText').text(`${processed.toLocaleString()} of ${response.totalCustomers.toLocaleString()} (${percentage}%)`);
        } else {
            const processed = totalSent + totalFailed;
            $('#progressText').text(processed.toLocaleString() + ' processed');
        }
        
        updateProgress();
    }

    function sendBatch() {
        if (!hasMore || isPaused || isStopped) return;

        const currentBatchSize = getOptimalBatchSize();
        const speed = $('#speed').val();
        const delay = batchDelays[speed] || 500;

        $.ajax({
            url: '?_route=message/send_bulk_ajax',
            method: 'POST',
            data: {
                group: $('#group').val(),
                message: $('#message').val(),
                via: $('#via').val(),
                batch: currentBatchSize,
                router: $('#router').val() || '',
                page: page,
                test: $('#test').is(':checked') ? 'on' : 'off',
                service: $('#service').val(),
            },
            dataType: 'json',
            timeout: 60000, // 60 second timeout
            beforeSend: function () {
                if (!startTime) {
                    startTime = Date.now();
                    $('#progressSection').show();
                }
                $('#status').html(`
                    <div class="alert alert-info">
                        <i class="fas fa-paper-plane fa-spin"></i> Sending batch ${page + 1} (${currentBatchSize} messages)...
                    </div>
                `);
            },
            success: function (response) {
                retryAttempts = 0; // Reset retry counter on success
                
                if (response && response.status === 'success') {
                    totalSent += response.totalSent || 0;
                    totalFailed += response.totalFailed || 0;
                    page = response.page || page + 1;
                    hasMore = response.hasMore !== false;

                    $('#status').html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Batch ${page} completed! 
                            <small>(Sent: ${response.totalSent || 0}, Failed: ${response.totalFailed || 0})</small>
                            ${response.debug ? '<br><small class="text-muted">Debug: ' + response.debug.query_filters + '</small>' : ''}
                        </div>
                    `);

                    // Add to history table in chunks to avoid UI freezing
                    if (response.batchStatus && response.batchStatus.length > 0) {
                        const batchData = response.batchStatus.map(msg => [
                            msg.name || 'Unknown',
                            msg.phone || 'Unknown',
                            msg.status && msg.status.includes('Failed') ? 
                                `<span class="label label-danger">${msg.status}</span>` : 
                                `<span class="label label-success">${msg.status || 'Sent'}</span>`,
                            (msg.message || 'No message').substring(0, 50) + '...',
                            msg.router || 'All Routers',
                            msg.service === 'all' ? 'All Services' : (msg.service || 'Unknown'),
                            msg.plan || 'No Plan'
                        ]);
                        
                        historyTable.rows.add(batchData).draw(false);
                    }

                    updateUI(response);

                    if (hasMore && !isPaused && !isStopped) {
                        // Use adaptive delay based on success rate
                        const successRate = totalSent / (totalSent + totalFailed);
                        const adaptiveDelay = successRate > 0.95 ? delay * 0.5 : delay;
                        
                        setTimeout(sendBatch, adaptiveDelay);
                    } else if (!hasMore) {
                        completeProcess();
                    }
                } else {
                    handleError('Unexpected response format', response);
                }
            },
            error: function (xhr, status, error) {
                handleError(`Request failed: ${error}`, { xhr, status, error });
            }
        });
    }

    function handleError(message, details) {
        retryAttempts++;
        console.error('Bulk SMS Error:', message, details);
        
        if (retryAttempts <= maxRetries && !isStopped) {
            $('#status').html(`
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> ${message} 
                    <br><small>Retrying... (Attempt ${retryAttempts}/${maxRetries})</small>
                </div>
            `);
            
            // Exponential backoff for retries
            const retryDelay = Math.pow(2, retryAttempts) * 1000;
            setTimeout(sendBatch, retryDelay);
        } else {
            $('#status').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed after ${maxRetries} attempts: ${message}
                    <br><button class="btn btn-sm btn-warning" onclick="resumeSending()">Try Again</button>
                </div>
            `);
            resetControls();
        }
    }

    function completeProcess() {
        const duration = ((Date.now() - startTime) / 1000 / 60).toFixed(1);
        const avgSpeed = Math.round((totalSent + totalFailed) / parseFloat(duration));
        
        $('#status').html(`
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <strong>Bulk messaging completed!</strong><br>
                Total Sent: <strong>${totalSent.toLocaleString()}</strong> | 
                Failed: <strong>${totalFailed.toLocaleString()}</strong><br>
                Duration: ${duration} minutes | Average Speed: ${avgSpeed} SMS/min
            </div>
        `);
        
        resetControls();
    }

    function resetControls() {
        $('#startBulk').show();
        $('#pauseBulk, #resumeBulk, #stopBulk').hide();
        isPaused = false;
        isStopped = false;
    }

    function resumeSending() {
        retryAttempts = 0;
        sendBatch();
    }

    // Control button handlers
    $('#startBulk').on('click', function () {
        const message = $('#message').val().trim();
        const group = $('#group').val();
        const service = $('#service').val();
        const via = $('#via').val();
        
        // Validation
        if (!message) {
            alert('Please enter a message');
            $('#message').focus();
            return;
        }
        
        if (message.length < 10) {
            alert('Message is too short. Please enter at least 10 characters.');
            $('#message').focus();
            return;
        }
        
        if (!group || !service || !via) {
            alert('Please select all required options (Group, Service Type, Send Via)');
            return;
        }
        
        // Confirmation for large batches
        const batch = getOptimalBatchSize();
        if (batch > 100 && !confirm(`You are about to send messages in batches of ${batch}. This may send to many customers. Are you sure you want to continue?`)) {
            return;
        }
        
        // Reset counters and state
        page = 0;
        totalSent = 0;
        totalFailed = 0;
        hasMore = true;
        isPaused = false;
        isStopped = false;
        startTime = null;
        retryAttempts = 0;
        
        // Update UI
        $('#startBulk').hide();
        $('#pauseBulk, #stopBulk').show();
        $('#status').html(`
            <div class="alert alert-info">
                <i class="fas fa-rocket"></i> Initializing bulk messaging...
                <br><small>Filters: Group=${group}, Service=${service}, Via=${via}</small>
            </div>
        `);
        
        // Clear history table
        historyTable.clear().draw();
        
        // Start sending
        sendBatch();
    });

    $('#pauseBulk').on('click', function () {
        isPaused = true;
        $(this).hide();
        $('#resumeBulk').show();
        $('#status').html('<div class="alert alert-warning"><i class="fas fa-pause"></i> Bulk messaging paused</div>');
    });

    $('#resumeBulk').on('click', function () {
        isPaused = false;
        $(this).hide();
        $('#pauseBulk').show();
        sendBatch();
    });

    $('#stopBulk').on('click', function () {
        if (confirm('Are you sure you want to stop bulk messaging?')) {
            isStopped = true;
            hasMore = false;
            completeProcess();
        }
    });

    // Auto-update batch size when gateway changes
    $('#via').on('change', function() {
        if ($('#batch').val() === 'auto') {
            const optimal = getOptimalBatchSize();
            const viaText = $(this).find('option:selected').text();
            $('#batch').next('.help-block').html(`Auto mode will use ${optimal} messages per batch for ${viaText}`);
        }
    });

    // Show estimated customer count when filters change
    $('#group, #service, #router').on('change', function() {
        const group = $('#group').val();
        const service = $('#service').val();
        const router = $('#router').val();
        
        if (group && service) {
            // Quick estimate request (without actually sending)
            $.ajax({
                url: '?_route=message/estimate_bulk_count',
                method: 'POST',
                data: {
                    group: group,
                    service: service,
                    router: router || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response && response.estimated_count !== undefined) {
                        const count = parseInt(response.estimated_count);
                        let message = `Estimated recipients: <strong>${count.toLocaleString()}</strong>`;
                        
                        if (count === 0) {
                            message = '<span class="text-warning">No customers found with current filters</span>';
                        } else if (count > 1000) {
                            message += ' <span class="text-warning">(Large batch - consider filtering further)</span>';
                        }
                        
                        $('#estimateDisplay').html(`
                            <div class="alert alert-info" style="margin-top: 10px;">
                                <i class="fas fa-info-circle"></i> ${message}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#estimateDisplay').html('');
                }
            });
        } else {
            $('#estimateDisplay').html('');
        }
    });

    // Initialize tooltips for better UX
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();

        if ($('#estimateDisplay').length === 0) {
            $('#bulkMessageForm').after('<div id="estimateDisplay"></div>');
        }

        // Placeholder chip insert
        $(document).on('click', '.bm-chip', function() {
            const tag = $(this).data('insert');
            if (!tag) return;
            const ta = document.getElementById('message');
            if (!ta) return;
            const start = ta.selectionStart || 0;
            const end = ta.selectionEnd || 0;
            const before = ta.value.substring(0, start);
            const after = ta.value.substring(end);
            ta.value = before + tag + after;
            const pos = start + tag.length;
            ta.focus();
            ta.setSelectionRange(pos, pos);
        });
    });
</script>
{/literal}

{include file="sections/footer.tpl"}