<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Notification Settings | Orange Theme</title>
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    {include file="sections/header.tpl"}
</head>
<body>

<style>
    /* ----- ORANGE THEME ROOT VARIABLES (extended) ----- */
    :root {
        --orange-primary: #fd7e14;
        --orange-light: #ffa94d;
        --orange-lighter: #ffd8a8;
        --orange-lightest: #fff4e6;
        --orange-dark: #e8590c;
        --orange-darker: #d9480f;
        --orange-gradient: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
        --gray-50: #f8f9fa;
        --gray-100: #f1f3f5;
        --gray-200: #e9ecef;
        --gray-300: #dee2e6;
        --gray-400: #ced4da;
        --gray-500: #adb5bd;
        --gray-600: #868e96;
        --gray-700: #495057;
        --gray-800: #343a40;
        --gray-900: #212529;
        --success-color: #40c057;
        --success-light: #d3f9d8;
        --danger-color: #fa5252;
        --danger-light: #ffe3e3;
        --warning-color: #fab005;
        --warning-light: #fff3bf;
        --info-color: #228be6;
        --info-light: #d0ebff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --radius-sm: 0.375rem;
        --radius: 0.5rem;
        --radius-md: 0.75rem;
        --radius-lg: 1rem;
    }

    body {
        background: linear-gradient(135deg, var(--orange-lightest) 0%, #fff4e6 100%);
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        line-height: 1.5;
        color: var(--gray-800);
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    .content-wrapper, .content {
        background: transparent !important;
        padding: 2rem !important;
    }

    /* Container */
    .form-container {
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Page Header */
    .page-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--orange-lighter);
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--orange-darker);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .page-title i {
        color: var(--orange-primary);
        font-size: 1.75rem;
        background: var(--orange-gradient);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .page-subtitle {
        color: var(--gray-600);
        font-size: 1rem;
        padding-left: 2.5rem;
    }

    /* Two-column master layout: HOTSPOT (left) + PPPoE (right) */
    .dual-column-layout {
        display: flex;
        flex-wrap: wrap;
        gap: 2rem;
        margin-top: 1rem;
    }

    .hotspot-column, .pppoe-column {
        flex: 1;
        min-width: 320px;
        background: transparent;
    }

    /* Unified panel style */
    .orange-panel {
        background: white;
        border-radius: var(--radius-md);
        border: 1px solid var(--orange-lighter);
        box-shadow: var(--shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        margin-bottom: 1.5rem;
        height: fit-content;
    }

    .orange-panel:hover {
        box-shadow: var(--shadow-lg);
        border-color: var(--orange-light);
        transform: translateY(-2px);
    }

    .panel-header {
        background: var(--orange-gradient);
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .panel-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }

    .panel-title i {
        color: white;
        font-size: 1.25rem;
    }

    .panel-badge {
        background: rgba(255,255,255,0.2);
        border-radius: 30px;
        padding: 0.25rem 0.75rem;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.5px;
    }

    .panel-body {
        padding: 1.5rem;
        background: white;
    }

    /* Section inside each column */
    .notification-section {
        margin-bottom: 2rem;
        padding: 1rem;
        border-radius: var(--radius);
        background: var(--gray-50);
        border-left: 4px solid var(--orange-primary);
        transition: all 0.2s;
    }

    .notification-section:hover {
        background: var(--orange-lightest);
    }

    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--orange-dark);
        margin-bottom: 1.2rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        border-bottom: 1px dashed var(--orange-lighter);
        padding-bottom: 0.5rem;
    }

    .control-label-modern {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--orange-darker);
        margin-bottom: 0.75rem;
        letter-spacing: -0.2px;
    }

    .textarea-modern {
        width: 100%;
        padding: 0.85rem 1rem;
        font-size: 0.9rem;
        line-height: 1.5;
        color: var(--gray-900);
        background-color: white;
        border: 1px solid var(--orange-lighter);
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        resize: vertical;
    }

    .textarea-modern:focus {
        outline: none;
        border-color: var(--orange-primary);
        box-shadow: 0 0 0 3px rgba(253, 126, 20, 0.15);
        background: var(--orange-lightest);
    }

    .help-block-modern {
        margin-top: 0.75rem;
        font-size: 0.75rem;
        line-height: 1.5;
        color: var(--gray-600);
        background: white;
        padding: 0.75rem;
        border-radius: var(--radius-sm);
        border-left: 3px solid var(--orange-primary);
    }

    .help-block-modern b {
        color: var(--orange-dark);
        font-family: monospace;
        background: var(--orange-lightest);
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        cursor: pointer;
    }

    .char-counter {
        font-size: 0.7rem;
        text-align: right;
        margin-top: 0.3rem;
        color: var(--gray-500);
    }

    /* Button orange */
    .btn-orange {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 2rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        background: var(--orange-gradient);
        border: none;
        border-radius: var(--radius);
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 12px rgba(253, 126, 20, 0.2);
    }

    .btn-orange:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(253, 126, 20, 0.3);
    }

    .btn-sm {
        padding: 0.5rem 1.2rem;
        font-size: 0.8rem;
    }

    .save-bar {
        position: sticky;
        bottom: 1.5rem;
        background: white;
        padding: 1rem 2rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--orange-lighter);
        box-shadow: var(--shadow-lg);
        text-align: center;
        margin-top: 2rem;
        z-index: 100;
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .preview-link {
        margin-left: 0.5rem;
        font-size: 0.7rem;
        cursor: pointer;
        color: var(--orange-primary);
        text-decoration: underline;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    @media (max-width: 900px) {
        .dual-column-layout {
            flex-direction: column;
        }
        .content-wrapper, .content {
            padding: 1rem !important;
        }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px);}
        to { opacity: 1; transform: translateY(0);}
    }
    .orange-panel {
        animation: fadeInUp 0.3s ease;
    }
    .highlight {
        animation: highlightFlash 1s ease;
    }
    @keyframes highlightFlash {
        0% { background-color: var(--orange-lightest);}
        100% { background-color: transparent;}
    }
</style>

<div class="form-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-bell"></i> {Lang::T('Notification Settings')}
        </h1>
        <p class="page-subtitle">{Lang::T('Configure automated messages — separate columns for Hotspot & PPPoE')}</p>
    </div>

    <form method="post" action="{Text::url('settings/notifications-post')}">
        <input type="hidden" name="csrf_token" value="{$csrf_token}">
        
        <!-- TWO COLUMN LAYOUT: LEFT = HOTSPOT (standard), RIGHT = PPPoE specific templates -->
        <div class="dual-column-layout">
            
            <!-- ********** LEFT COLUMN: HOTSPOT (standard notification templates) ********** -->
            <div class="hotspot-column">
                <div class="orange-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-wifi"></i> {Lang::T('Hotspot / Default Templates')}
                            <span class="panel-badge">{Lang::T('Standard customers')}</span>
                        </div>
                    </div>
                    <div class="panel-body">
                        
                        <!-- Expired -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-calendar-times"></i> {Lang::T('Expired Notification')}</div>
                            <textarea class="textarea-modern" id="expired" name="expired" rows="3" oninput="updateCounter(this)">{if $_json['expired']!=''}{Lang::htmlspecialchars($_json['expired'])}{else}{Lang::T('Hello')} [[name]], {Lang::T('your internet package')} [[package]] {Lang::T('has been expired')}.{/if}</textarea>
                            <div class="char-counter" id="expired-counter"></div>
                            <div class="help-block-modern">{Lang::T('Variables:')} <b>[[name]]</b> <b>[[username]]</b> <b>[[package]]</b> <b>[[price]]</b> <b>[[bills]]</b> <b>[[payment_link]]</b></div>
                        </div>

                        <!-- Reminders group -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-clock"></i> {Lang::T('Expiration Reminders')}</div>
                            <label class="control-label-modern">{Lang::T('7 days before')}</label>
                            <textarea class="textarea-modern" id="reminder_7_day" name="reminder_7_day" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['reminder_7_day'])}</textarea>
                            <div class="char-counter" id="reminder_7_day-counter"></div>
                            
                            <label class="control-label-modern" style="margin-top: 1rem;">{Lang::T('3 days before')}</label>
                            <textarea class="textarea-modern" id="reminder_3_day" name="reminder_3_day" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['reminder_3_day'])}</textarea>
                            <div class="char-counter" id="reminder_3_day-counter"></div>
                            
                            <label class="control-label-modern" style="margin-top: 1rem;">{Lang::T('1 day before')}</label>
                            <textarea class="textarea-modern" id="reminder_1_day" name="reminder_1_day" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['reminder_1_day'])}</textarea>
                            <div class="char-counter" id="reminder_1_day-counter"></div>
                            <div class="help-block-modern"><b>[[expired_date]]</b> + all standard variables</div>
                        </div>

                        <!-- Invoice & Welcome -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-file-invoice"></i> {Lang::T('Invoice (Paid)')}</div>
                            <textarea class="textarea-modern" id="invoice_paid" name="invoice_paid" rows="6" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['invoice_paid'])}</textarea>
                            <div class="char-counter" id="invoice_paid-counter"></div>
                            <div class="help-block-modern">{Lang::T('Full invoice variables')}: <b>[[company_name]]</b> <b>[[invoice]]</b> <b>[[plan_name]]</b> <b>[[expired_date]]</b> etc.</div>
                        </div>

                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-user-plus"></i> {Lang::T('Welcome Message')}</div>
                            <textarea class="textarea-modern" id="welcome_message" name="welcome_message" rows="3" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['welcome_message'])}</textarea>
                            <div class="char-counter" id="welcome_message-counter"></div>
                            <div class="help-block-modern"><b>[[name]]</b> <b>[[username]]</b> <b>[[password]]</b> <b>[[url]]</b> <b>[[company]]</b></div>
                        </div>

                        {if $_c['enable_balance'] == 'yes'}
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-money-bill-wave"></i> {Lang::T('Balance Notifications')}</div>
                            <label class="control-label-modern">{Lang::T('Send Balance')}</label>
                            <textarea class="textarea-modern" id="balance_send" name="balance_send" rows="2" oninput="updateCounter(this)">{if $_json['balance_send']}{Lang::htmlspecialchars($_json['balance_send'])}{else}{Lang::htmlspecialchars($_default['balance_send'])}{/if}</textarea>
                            <div class="char-counter" id="balance_send-counter"></div>
                            <label class="control-label-modern" style="margin-top:1rem">{Lang::T('Received Balance')}</label>
                            <textarea class="textarea-modern" id="balance_received" name="balance_received" rows="2" oninput="updateCounter(this)">{if $_json['balance_received']}{Lang::htmlspecialchars($_json['balance_received'])}{else}{Lang::htmlspecialchars($_default['balance_received'])}{/if}</textarea>
                            <div class="char-counter" id="balance_received-counter"></div>
                            <div class="help-block-modern"><b>[[balance]]</b> <b>[[current_balance]]</b></div>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- ********** RIGHT COLUMN: PPPoE SPECIFIC TEMPLATES (separate) ********** -->
            <div class="pppoe-column">
                <div class="orange-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fas fa-ethernet"></i> {Lang::T('PPPoE Customer Templates')}
                            <span class="panel-badge">{Lang::T('Dial-up / PPPoE users')}</span>
                        </div>
                        <div class="panel-actions">
                            <span class="help-block-modern" style="background:transparent; padding:0; margin:0; color:white; font-size:0.7rem;">{Lang::T('Override defaults for PPPoE')}</span>
                        </div>
                    </div>
                    <div class="panel-body">
                        <p class="help-block-modern" style="margin-bottom: 1.2rem;">
                            <i class="fas fa-info-circle"></i> {Lang::T('These messages are used when the active package type is PPPoE or customer service = PPPoE. Leave empty to fallback to Hotspot/default templates.')}
                        </p>

                        <!-- Expired PPPoE -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-calendar-times"></i> {Lang::T('Expired (PPPoE)')}</div>
                            <textarea class="textarea-modern" id="expired_pppoe" name="expired_pppoe" rows="3" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['expired_pppoe'])}</textarea>
                            <div class="char-counter" id="expired_pppoe-counter"></div>
                            <div class="help-block-modern">{Lang::T('Extra variable:')} <b>[[pppoe_username]]</b> {Lang::T('+ all standard variables')}</div>
                        </div>

                        <!-- Reminders PPPoE (7,3,1) -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-hourglass-half"></i> {Lang::T('Reminders (PPPoE)')}</div>
                            <label class="control-label-modern">{Lang::T('7 days before')}</label>
                            <textarea class="textarea-modern" id="reminder_7_day_pppoe" name="reminder_7_day_pppoe" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['reminder_7_day_pppoe'])}</textarea>
                            <div class="char-counter" id="reminder_7_day_pppoe-counter"></div>
                            
                            <label class="control-label-modern" style="margin-top: 1rem;">{Lang::T('3 days before')}</label>
                            <textarea class="textarea-modern" id="reminder_3_day_pppoe" name="reminder_3_day_pppoe" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['reminder_3_day_pppoe'])}</textarea>
                            <div class="char-counter" id="reminder_3_day_pppoe-counter"></div>
                            
                            <label class="control-label-modern" style="margin-top: 1rem;">{Lang::T('1 day before')}</label>
                            <textarea class="textarea-modern" id="reminder_1_day_pppoe" name="reminder_1_day_pppoe" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['reminder_1_day_pppoe'])}</textarea>
                            <div class="char-counter" id="reminder_1_day_pppoe-counter"></div>
                            <div class="help-block-modern"><b>[[expired_date]]</b> <b>[[pppoe_username]]</b></div>
                        </div>

                        <!-- Invoice Paid PPPoE -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-receipt"></i> {Lang::T('Invoice payment (PPPoE)')}</div>
                            <textarea class="textarea-modern" id="invoice_paid_pppoe" name="invoice_paid_pppoe" rows="6" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['invoice_paid_pppoe'])}</textarea>
                            <div class="char-counter" id="invoice_paid_pppoe-counter"></div>
                            <div class="help-block-modern">{Lang::T('Same invoice variables +')} <b>[[pppoe_username]]</b></div>
                        </div>

                        <!-- Balance invoice PPPoE (if balance enabled) -->
                        {if $_c['enable_balance'] == 'yes'}
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-hand-holding-usd"></i> {Lang::T('Balance Invoice (PPPoE)')}</div>
                            <textarea class="textarea-modern" id="invoice_balance_pppoe" name="invoice_balance_pppoe" rows="4" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['invoice_balance_pppoe'])}</textarea>
                            <div class="char-counter" id="invoice_balance_pppoe-counter"></div>
                        </div>
                        {/if}

                        <!-- Welcome PPPoE -->
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-hand-peace"></i> {Lang::T('Welcome (PPPoE)')}</div>
                            <textarea class="textarea-modern" id="welcome_message_pppoe" name="welcome_message_pppoe" rows="3" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['welcome_message_pppoe'])}</textarea>
                            <div class="char-counter" id="welcome_message_pppoe-counter"></div>
                            <div class="help-block-modern">{Lang::T('Includes')} <b>[[pppoe_username]]</b> + standard welcome vars</div>
                        </div>

                        {if $_c['enable_balance'] == 'yes'}
                        <div class="notification-section">
                            <div class="section-title"><i class="fas fa-exchange-alt"></i> {Lang::T('Balance Transfer (PPPoE)')}</div>
                            <label class="control-label-modern">{Lang::T('Send Balance')}</label>
                            <textarea class="textarea-modern" id="balance_send_pppoe" name="balance_send_pppoe" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['balance_send_pppoe'])}</textarea>
                            <div class="char-counter" id="balance_send_pppoe-counter"></div>
                            <label class="control-label-modern" style="margin-top:1rem">{Lang::T('Received Balance')}</label>
                            <textarea class="textarea-modern" id="balance_received_pppoe" name="balance_received_pppoe" rows="2" oninput="updateCounter(this)">{Lang::htmlspecialchars($_json['balance_received_pppoe'])}</textarea>
                            <div class="char-counter" id="balance_received_pppoe-counter"></div>
                        </div>
                        {/if}

                        <div class="help-block-modern" style="margin-top: 0.5rem;">
                            <i class="fas fa-lightbulb"></i> {Lang::T('PPPoE templates override default messages when customer uses PPPoE service. Use [[pppoe_username]] for dial-in credentials.')}
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- end dual-column-layout -->

        <!-- global save bar -->
        <div class="save-bar">
            <button class="btn-orange" type="submit"><i class="fas fa-save"></i> {Lang::T('Save All Templates (Both Columns)')}</button>
            <button class="btn-orange btn-sm" type="button" onclick="window.location.reload();"><i class="fas fa-undo-alt"></i> {Lang::T('Reset View')}</button>
        </div>
    </form>
</div>

<script>
    // Helper: character counter
    function updateCounter(textarea) {
        var id = textarea.id;
        var counterSpan = document.getElementById(id + '-counter');
        if (counterSpan) {
            var len = textarea.value.length;
            counterSpan.textContent = len + ' chars';
            counterSpan.style.color = len > 800 ? 'var(--warning-color)' : (len > 2000 ? 'var(--danger-color)' : 'var(--gray-500)');
        }
    }

    // Initialize all textareas with counters
    document.querySelectorAll('textarea').forEach(function(ta) {
        updateCounter(ta);
        ta.addEventListener('input', function() { updateCounter(this); });
    });

    // Preview function - fixed to avoid Smarty conflict with template literals
    function previewTemplateContent(content, title) {
        if (!content || !content.trim()) {
            alert('No content to preview.');
            return;
        }
        var modal = document.createElement('div');
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:10000;';
        var inner = document.createElement('div');
        inner.style.cssText = 'background:white; max-width:700px; width:90%; border-radius:1rem; padding:1.5rem; box-shadow:0 20px 30px rgba(0,0,0,0.3); max-height:80vh; overflow:auto;';
        
        // Build HTML using string concatenation instead of template literals to avoid {} conflict
        var headerHtml = '<div style="display:flex; justify-content:space-between; border-bottom:2px solid var(--orange-lighter); margin-bottom:1rem; padding-bottom:0.5rem;">' +
            '<h3 style="color:var(--orange-darker);"><i class="fas fa-eye"></i> ' + escapeHtml(title) + '</h3>' +
            '<button id="closePreviewBtn" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>' +
            '</div>';
        
        var contentHtml = '<div style="background:var(--gray-50); padding:1rem; border-radius:0.5rem; font-family:monospace; white-space:pre-wrap; line-height:1.5;">' + escapeHtml(content) + '</div>';
        
        var footerHtml = '<div style="margin-top:1.5rem; text-align:center;"><button id="closePreviewBtn2" class="btn-orange btn-sm">Close</button></div>';
        
        inner.innerHTML = headerHtml + contentHtml + footerHtml;
        modal.appendChild(inner);
        document.body.appendChild(modal);
        
        var closeModal = function() { modal.remove(); };
        var closeBtn = document.getElementById('closePreviewBtn');
        var closeBtn2 = document.getElementById('closePreviewBtn2');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (closeBtn2) closeBtn2.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) { if(e.target === modal) closeModal(); });
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if(m === '&') return '&amp;';
            if(m === '<') return '&lt;';
            if(m === '>') return '&gt;';
            return m;
        });
    }

    // Add preview links dynamically near each textarea
    document.querySelectorAll('.notification-section').forEach(function(section) {
        var textareas = section.querySelectorAll('textarea');
        textareas.forEach(function(ta) {
            if(!ta.id) return;
            var parent = ta.parentNode;
            var previewSpan = document.createElement('span');
            previewSpan.className = 'preview-link';
            previewSpan.innerHTML = '<i class="fas fa-eye"></i> preview';
            previewSpan.onclick = function(e) {
                e.preventDefault();
                previewTemplateContent(ta.value, 'Preview: ' + ta.id);
            };
            var label = parent.querySelector('.control-label-modern, .section-title');
            if(label) {
                label.appendChild(previewSpan);
            } else {
                ta.insertAdjacentElement('afterend', previewSpan);
            }
        });
    });

    // Variable insertion: click on help-block b tags
    document.querySelectorAll('.help-block-modern b').forEach(function(b) {
        b.style.cursor = 'pointer';
        b.addEventListener('click', function() {
            var varName = this.innerText.trim();
            if(varName.indexOf('[[') !== 0) varName = '[[' + varName.replace(/[\[\]]/g,'') + ']]';
            var parentSection = this.closest('.notification-section');
            if(parentSection) {
                var textarea = parentSection.querySelector('textarea');
                if(textarea) {
                    var start = textarea.selectionStart;
                    var end = textarea.selectionEnd;
                    textarea.value = textarea.value.substring(0, start) + varName + textarea.value.substring(end);
                    textarea.focus();
                    textarea.setSelectionRange(start + varName.length, start + varName.length);
                    updateCounter(textarea);
                    textarea.classList.add('highlight');
                    setTimeout(function() { textarea.classList.remove('highlight'); }, 800);
                }
            }
        });
    });
    
    // Dirty flag warning before unload
    var formDirty = false;
    document.querySelectorAll('textarea, input').forEach(function(field) {
        field.addEventListener('input', function() { formDirty = true; });
    });
    window.addEventListener('beforeunload', function(e) {
        if(formDirty) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes.';
        }
    });
    var formElement = document.querySelector('form');
    if(formElement) {
        formElement.addEventListener('submit', function() { formDirty = false; });
    }
</script>

{include file="sections/footer.tpl"}
</body>
</html>