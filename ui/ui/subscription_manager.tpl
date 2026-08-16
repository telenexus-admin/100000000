{include file="sections/header.tpl"}

<style>
.sm-pay-btn {
    background: #0d6efd; color: #fff; border: none; border-radius: 6px;
    padding: 8px 18px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}
.sm-pay-btn:hover { background: #0b5ed7; }
.sm-pay-btn:disabled { opacity: .65; cursor: not-allowed; }
.sm-modal-backdrop {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 1050; display: none; align-items: center; justify-content: center;
}
.sm-modal {
    background: #fff; border-radius: 10px; width: 92%; max-width: 820px;
    max-height: 94vh; display: flex; flex-direction: column;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2); overflow: hidden;
}
.sm-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #dee2e6; padding: 14px 20px; flex-shrink: 0;
}
.sm-modal-title { font-size: 1.1rem; font-weight: 600; margin: 0; }
.sm-modal-body { flex: 1; overflow-y: auto; padding: 20px; }
.sm-pay-panel {
    flex-shrink: 0; border-top: 2px solid #0d6efd; background: #f0f5ff;
    padding: 14px 20px;
}
.sm-btn-close {
    background: none; border: none; font-size: 1.5rem; line-height: 1; color: #6c757d; cursor: pointer; padding: 0 4px;
}
.sm-warning-box {
    background: #fff3cd; border: 1px solid #ffe69c; color: #856404; padding: 14px; border-radius: 6px; margin-top: 16px;
}
.sm-warning-title { font-weight: bold; margin-bottom: 6px; }
.sm-invoice-info { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; font-size: .9rem; }
.sm-invoice-info .col-right { text-align: right; flex: 1; }
.sm-invoice-info > div:first-child { flex: 1; }
.sm-bill-to {
    width: 100%; display: flex; justify-content: space-between; align-items: flex-start;
    border-top: 2px solid #0d6efd; padding-top: 14px; margin-bottom: 20px; font-size: .9rem;
}
.sm-status-badge {
    background: #fff3cd; color: #856404; padding: 3px 12px; border-radius: 4px; font-weight: bold; font-size: .8rem;
}
.sm-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: .9rem; }
.sm-table th { background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #0d6efd; padding: 10px; text-align: left; }
.sm-table td { border-bottom: 1px solid #dee2e6; padding: 10px; }
.sm-summary { float: right; width: 40%; font-size: .9rem; }
.sm-summary-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #dee2e6; }
.sm-summary-total { font-weight: bold; font-size: 1rem; color: #0d6efd; border-bottom: 0; }
.clearfix::after { content: ""; clear: both; display: table; }
.sm-footer { text-align: center; margin-top: 28px; font-size: .82rem; color: #6c757d; }
.sm-footer h6 { color: #0d6efd; font-weight: bold; margin-bottom: 4px; }
/* Gateway selection cards */
.gw-card {
    cursor: pointer; border: 2px solid #dee2e6; border-radius: 8px;
    padding: 8px 18px; display: inline-flex; align-items: center; gap: 9px;
    margin-right: 10px; margin-bottom: 8px; background: #fff;
    transition: border-color .15s, background .15s; font-weight: 500; font-size: .88rem; user-select: none;
}
.gw-card.selected { border-color: #0d6efd; background: #dbeafe; color: #1d4ed8; }
.gw-card:hover { border-color: #93c5fd; }
.gw-icon { font-size: 1.2rem; }
</style>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">Subscription Manager</div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-8">
                        {if $is_expired}
                        <div class="alert alert-danger">
                            <strong>Subscription Expired</strong> - Please pay your invoice to restore access.
                        </div>
                        {/if}
                    </div>
                    <div class="col-md-4 text-right" style="text-align: right;">
                        <button type="button" class="sm-pay-btn" onclick="openInvoiceModal()">
                            <i class="fa fa-file-text-o"></i> View Billing Estimate &amp; Pay
                        </button>
                    </div>
                </div>

                <hr>

                <h5><strong>Invoice History</strong></h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="invoiceHistoryTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Generated</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Transaction ID</th>
                                <th>Status</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if $invoices}
                                {foreach $invoices as $inv}
                                <tr>
                                    <td><strong>{$inv.invoice_number}</strong></td>
                                    <td>{if $inv.issue_date}{$inv.issue_date|date_format:"%d %b %Y"}{else}—{/if}</td>
                                    <td>{if $inv.due_date}{$inv.due_date|date_format:"%d %b %Y"}{else}—{/if}</td>
                                    <td><strong>{$inv.currency} {$inv.amount|number_format:2}</strong></td>
                                    <td>{if $inv.gateway}{$inv.gateway|capitalize}{else}<span class="text-muted">N/A</span>{/if}</td>
                                    <td>
                                        {if $inv.transaction_id}
                                            <small style="font-family:monospace;">{$inv.transaction_id}</small>
                                        {else}
                                            <span class="text-muted">N/A</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $inv.status eq 'paid'}
                                            <span class="label label-success">Paid</span>
                                        {elseif $inv.status eq 'pending'}
                                            <span class="label label-warning">Pending</span>
                                        {elseif $inv.status eq 'overdue'}
                                            <span class="label label-danger">Overdue</span>
                                        {elseif $inv.status eq 'unpaid'}
                                            <span class="label label-danger">Unpaid</span>
                                        {elseif $inv.status eq 'cancelled'}
                                            <span class="label label-default">Cancelled</span>
                                        {else}
                                            <span class="label label-info">{$inv.status|capitalize}</span>
                                        {/if}
                                    </td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        {if $inv.status eq 'pending' || $inv.status eq 'overdue' || $inv.status eq 'unpaid'}
                                            <button type="button" class="btn btn-xs btn-primary" onclick='viewInvoice({$inv|json_encode})' title="View Invoice">
                                                <i class="fa fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-xs btn-success" onclick='viewInvoice({$inv|json_encode})' title="Pay Invoice">
                                                <i class="fa fa-credit-card"></i> Pay
                                            </button>
                                        {else}
                                            <button type="button" class="btn btn-xs btn-primary" onclick='viewInvoice({$inv|json_encode})' title="View Invoice">
                                                <i class="fa fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-xs btn-default" onclick='downloadInvoice({$inv|json_encode})' title="Download PDF">
                                                <i class="fa fa-download"></i> Download
                                            </button>
                                        {/if}
                                    </td>
                                </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="8" class="text-center">No invoices found.</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ─── INVOICE VIEW MODAL ─────────────────────────────────────────────────── -->
<div id="viewInvoiceModal" class="sm-modal-backdrop">
    <div class="sm-modal" id="viewInvoiceContent">
        <!-- Header -->
        <div class="sm-modal-header no-print">
            <h4 class="sm-modal-title"><i class="fa fa-file-text-o" style="color:#0d6efd;"></i> Invoice Details</h4>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" class="btn btn-sm btn-default" id="invPrintBtn" onclick="printCurrentInvoice()">
                    <i class="fa fa-print"></i> Print
                </button>
                <button class="sm-btn-close" onclick="closeViewInvoiceModal()">&times;</button>
            </div>
        </div>
        <!-- Invoice body (scrollable) -->
        <div class="sm-modal-body" id="viewInvoicePrintable">
            <!-- filled by JS -->
        </div>
        <!-- Pending invoice pay footer -->
        <div id="viewInvoicePayFooter" class="sm-pay-panel no-print" style="display:none;flex-direction:row;justify-content:space-between;align-items:center;">
            <span id="viewInvoicePayLabel" style="font-weight:600;font-size:.95rem;color:#1d4ed8;"></span>
            <button class="sm-pay-btn" style="padding:8px 24px;" onclick="openPaymentModalFromInvoice()">
                <i class="fa fa-credit-card"></i> Pay Now
            </button>
        </div>
    </div>
</div>

<!-- ─── INVOICE PRINT STYLES ─────────────────────────────────────────────────── -->
<style>
#invoiceHistoryTable th { white-space: nowrap; }
#viewInvoiceModal { z-index: 1060; }
.inv-card { font-family: Arial, sans-serif; color: #333; max-width: 760px; margin: 0 auto; padding: 24px; }
.inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; border-bottom: 3px solid #0d6efd; padding-bottom: 16px; }
.inv-header .left h2 { margin: 0 0 4px; color: #0d6efd; font-size: 1.5rem; }
.inv-header .right { text-align: right; }
.inv-header .right .inv-number { font-size: 1.3rem; font-weight: 700; color: #495057; }
.inv-header .right .inv-status { display: inline-block; margin-top: 8px; padding: 4px 14px; border-radius: 4px; font-size: .8rem; font-weight: 700; }
.inv-status-paid { background: #d1e7dd; color: #0f5132; }
.inv-status-pending { background: #fff3cd; color: #856404; }
.inv-status-overdue { background: #f8d7da; color: #842029; }
.inv-status-cancelled { background: #e2e3e5; color: #41464b; }
.inv-parties { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: .88rem; }
.inv-parties .col { flex: 1; }
.inv-parties .col:last-child { text-align: right; }
.inv-parties .label-hdr { text-transform: uppercase; font-size: .7rem; color: #6c757d; font-weight: 700; letter-spacing: 1px; margin-bottom: 4px; }
.inv-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; background: #f8f9fa; border-radius: 6px; padding: 16px; margin-bottom: 24px; font-size: .88rem; }
.inv-detail-row { display: flex; justify-content: space-between; }
.inv-detail-row .k { color: #6c757d; }
.inv-detail-row .v { font-weight: 600; text-align: right; }
.inv-line-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: .88rem; }
.inv-line-table th { background: #0d6efd; color: #fff; padding: 10px 12px; text-align: left; }
.inv-line-table td { padding: 10px 12px; border-bottom: 1px solid #dee2e6; }
.inv-total-box { float: right; width: 260px; font-size: .9rem; }
.inv-total-box .row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #dee2e6; }
.inv-total-box .row.grand { font-weight: 700; font-size: 1.1rem; color: #0d6efd; border-bottom: 0; }
.inv-footer { text-align: center; margin-top: 48px; font-size: .8rem; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 16px; }
@media print {
    body * { visibility: hidden !important; }
    #viewInvoiceModal, #viewInvoiceModal * { visibility: visible !important; }
    .no-print, .sm-modal-header { display: none !important; }
    #viewInvoiceModal { position: absolute; left: 0; top: 0; width: 100%; background: #fff; z-index: 99999; display: block !important; }
    .sm-modal { box-shadow: none; max-height: none; }
}
/* Compact invoice card inside modal (not for print) */
.sm-modal-body .inv-card { padding: 10px 16px; }
.sm-modal-body .inv-header { margin-bottom: 10px; padding-bottom: 8px; }
.sm-modal-body .inv-header .left h2 { font-size: 1.1rem; }
.sm-modal-body .inv-parties { margin-bottom: 10px; font-size: .82rem; }
.sm-modal-body .inv-details-grid { padding: 8px 10px; margin-bottom: 10px; gap: 2px 16px; font-size: .82rem; }
.sm-modal-body .inv-line-table { margin-bottom: 10px; }
.sm-modal-body .inv-line-table th, .sm-modal-body .inv-line-table td { padding: 6px 10px; font-size: .82rem; }
.sm-modal-body .inv-total-box .row { padding: 4px 0; }
.sm-modal-body .inv-footer { margin-top: 12px; padding-top: 8px; }
</style>

<!-- INVOICE MODAL (Expected Invoice Preview) -->
<div id="invoiceModal" class="sm-modal-backdrop">
    <div class="sm-modal">
        <div class="sm-modal-header">
            <h4 class="sm-modal-title"><i class="fa fa-file-text-o" style="color:#0d6efd;"></i> View Invoice &amp; Payment Details</h4>
            <button class="sm-btn-close" onclick="closeInvoiceModal()">&times;</button>
        </div>
        <div class="sm-modal-body">
            <div class="sm-invoice-info">
                <div>
                    <h4 style="color: #0d6efd; margin-top:0;">{$nuxhost_company}</h4>
                    <div style="color:#6c757d;font-size:.85rem;">{$nuxhost_email}</div>
                </div>
                <div class="col-right">
                    <div style="font-size:1.1rem;font-weight:700;color:#495057;">{if $pending_invoice}INVOICE READY{else}BILLING ESTIMATE{/if}</div>
                    <div style="font-size:.85rem;color:#6c757d;margin-top:4px;"><strong>Invoice #:</strong> {if $pending_invoice}{$pending_invoice.invoice_number|default:'Pending'}{else}Not generated yet{/if}</div>
                    <div style="font-size:.85rem;"><strong>Date:</strong> {if $pending_invoice.issue_date}{$pending_invoice.issue_date|date_format:"%b %d, %Y"}{elseif $subscription_expires}{$subscription_expires|date_format:"%b %d, %Y"}{else}TBD{/if}</div>
                    <div style="font-size:.85rem;"><strong>Due Date:</strong> {if $pending_invoice.due_date}{$pending_invoice.due_date|date_format:"%b %d, %Y"}{elseif $subscription_expires}{$subscription_expires|date_format:"%b %d, %Y"}{else}TBD{/if}</div>
                </div>
            </div>

        <div class="sm-bill-to">
            <div>
                <div style="color: #0d6efd; font-weight: bold; margin-bottom: 8px;">BILL TO</div>
                <strong>{$tenant_company}</strong><br>
                <div>{$tenant_email}</div>
            </div>
            <div>
                <div style="color: #0d6efd; font-weight: bold; margin-bottom: 8px; text-align:right;">STATUS</div>
                <div class="sm-status-badge">{if $pending_invoice}PENDING{else}ESTIMATE{/if}</div>
            </div>
        </div>

        <table class="sm-table">
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th>DETAIL</th>
                    <th style="text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                {if $pending_invoice}
                <tr>
                    <td>{if $pending_invoice.notes}{$pending_invoice.notes|escape:'html'|nl2br nofilter}{else}{$pending_invoice.description|default:'Subscription Renewal'}{/if}</td>
                    <td>Invoice {$pending_invoice.invoice_number|default:'N/A'} ({$pending_invoice.status|capitalize})</td>
                    <td style="text-align: right;">{$payable_currency} {$payable_amount|number_format:2}</td>
                </tr>
                {else}
                <tr>
                    <td>PPPoE Active Users</td>
                    <td>{$pppoe_count|default:0} users @ {$currency} {$pppoe_rate|number_format:2}</td>
                    <td style="text-align: right;">{$currency} {$pppoe_amount|number_format:2}</td>
                </tr>
                <tr>
                    <td>Hotspot Revenue Commission</td>
                    <td>{$hotspot_rate}% of {$currency} {$hotspot_revenue|default:0}</td>
                    <td style="text-align: right;">{$currency} {$hotspot_amount|number_format:2}</td>
                </tr>
                {/if}
            </tbody>
        </table>

        <div class="clearfix">
            <div class="sm-summary">
                <div class="sm-summary-row">
                    <span>Service Subtotal:</span>
                    {if $pending_invoice}
                    <strong>{$payable_currency} {$payable_amount|number_format:2}</strong>
                    {else}
                    <strong>{$currency} {$calculated_total|number_format:2}</strong>
                    {/if}
                </div>
                <div class="sm-summary-row" style="color: #6c757d; font-size: .8rem;">
                    <span>Minimum Floor Pay:</span>
                    {if $pending_invoice}
                    <span>Included in invoice</span>
                    {else}
                    <span>{$currency} {$minimum_pay|number_format:2}</span>
                    {/if}
                </div>
                <div class="sm-summary-row sm-summary-total mt-2">
                    <span>Total Due:</span>
                    {if $pending_invoice}
                    <span>{$payable_currency} {$payable_amount|number_format:2}</span>
                    {else}
                    <span>{$currency} {$amount_due|number_format:2}</span>
                    {/if}
                </div>
            </div>
        </div>

        {if $pending_invoice}
        <div class="sm-warning-box">
            <div class="sm-warning-title">Invoice Details</div>
            This is the generated invoice amount, including any queued next-invoice debt adjustment.
        </div>
        {else}
        <div class="sm-warning-box">
            <div class="sm-warning-title">Estimate Only</div>
            This preview is based on the tenant expiration date and the 30-day billing window ending on that date.
        </div>
        {/if}

        {if !$pending_invoice}
        <div class="sm-warning-box">
            <div class="sm-warning-title">Payment Not Available</div>
            Payment is enabled only after an invoice is generated. Your invoice has not been generated yet.
        </div>
        {else}
        <div style="text-align: center; margin-top: 20px;">
            <button class="sm-pay-btn" style="width: 100%; padding: 12px; font-size: 1rem; border-radius: 8px;" onclick="openPaymentModal(SM_AMOUNT_DUE, SM_CURRENCY)">
                <i class="fa fa-credit-card"></i> Pay {$payable_currency} {$payable_amount|number_format:2} Now
            </button>
        </div>
        {/if}

        <div class="sm-footer">
            <h6>Thank you for your business!</h6>
            <div>For billing inquiries, please contact {$nuxhost_email}</div>
            <div style="margin-top: 6px;">&copy; {$smarty.now|date_format:"%Y"} {$nuxhost_company} All rights reserved.</div>
        </div>

        </div><!-- end .sm-modal-body -->
    </div>
</div>

<!-- ─── PAYMENT MODAL ────────────────────────────────────────────────────────── -->
<div id="paymentModal" class="sm-modal-backdrop">
    <div class="sm-modal" style="max-width:680px;">
        <div class="sm-modal-header">
            <h4 class="sm-modal-title"><i class="fa fa-credit-card" style="color:#0d6efd;"></i> Make Payment</h4>
            <button class="sm-btn-close" onclick="closePaymentModal()">&times;</button>
        </div>
        <div class="sm-modal-body" style="padding:28px;">
            <!-- Amount badge -->
            <div style="text-align:center;margin-bottom:28px;">
                <div style="background:#e8f4fd;border-radius:12px;padding:20px 32px;display:inline-block;min-width:260px;">
                    <div style="font-size:.72rem;color:#6c757d;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:6px;">Amount Due</div>
                    <div id="pmAmountLabel" style="font-size:2.4rem;font-weight:700;color:#0d6efd;"></div>
                </div>
            </div>
            <!-- Section title -->
            <div style="font-size:.75rem;text-transform:uppercase;color:#6c757d;letter-spacing:1px;font-weight:700;margin-bottom:14px;">
                <i class="fa fa-credit-card"></i> Select Payment Method
            </div>
            <!-- Gateway cards - side by side -->
            <div id="pmGwCardsRow" style="margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
                {if $gateways}
                    {foreach $gateways as $gw}
                        {if $gw.gateway_id eq 'mpesa' || $gw.gateway_id eq 'kopokopo'}
                        <div class="gw-card" id="pmGwCard_{$gw.gateway_id}" onclick="selectPaymentGateway('{$gw.gateway_id}', '{$gw.name}')" style="flex:1;min-width:140px;justify-content:center;flex-direction:column;text-align:center;padding:12px 20px;">
                            {if $gw.gateway_id eq 'mpesa'}
                                <span class="gw-icon" style="color:#00a651;font-weight:700;font-size:1.4rem;margin-bottom:6px;">M</span>
                            {elseif $gw.gateway_id eq 'kopokopo'}
                                <span class="gw-icon" style="color:#e85d04;font-weight:700;font-size:1.4rem;margin-bottom:6px;">K</span>
                            {/if}
                            <span style="font-weight:600;font-size:.95rem;">{$gw.name}</span>
                        </div>
                        {/if}
                    {/foreach}
                {else}
                    <span class="text-muted" style="font-size:.85rem;">No payment gateways available. Please contact support.</span>
                {/if}
            </div>
            <!-- Phone input (shown after gateway selected) -->
            <div id="pmPhoneSection" style="display:none;">
                <div style="font-size:.75rem;text-transform:uppercase;color:#6c757d;letter-spacing:1px;font-weight:700;margin-bottom:8px;">
                    <i class="fa fa-mobile"></i> Phone Number &mdash; <span id="pmGwSelectedName" style="color:#1d4ed8;text-transform:none;font-weight:600;"></span>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <div style="flex:1;">
                        <input type="tel" id="pmPhoneInput" class="form-control"
                               placeholder="e.g. 0712345678" maxlength="13">
                    </div>
                    <button id="pmSendBtn" class="sm-pay-btn" onclick="sendPayment()">
                        <i class="fa fa-send"></i> Send STK Push
                    </button>
                </div>
            </div>
            <div id="pmPayStatus" style="margin-top:10px;"></div>
        </div>
    </div>
</div>

<script>
var SM_APP_URL       = '{$app_url}';
var SM_AMOUNT_DUE    = {$payable_amount|default:0};
var SM_CURRENCY      = '{$payable_currency|default:$currency}';
var _currentInvoice  = null;
var _pmSelectedGwId  = null;
var _pmPaymentAmount = 0;

function openInvoiceModal() {
    document.getElementById('invoiceModal').style.display = 'flex';
}
function closeInvoiceModal() {
    document.getElementById('invoiceModal').style.display = 'none';
}

// Close when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('invoiceModal');
    if (event.target == modal) { closeInvoiceModal(); }
    var vm = document.getElementById('viewInvoiceModal');
    if (event.target == vm) { closeViewInvoiceModal(); }
    var pm = document.getElementById('paymentModal');
    if (event.target == pm) { closePaymentModal(); }
}

// ─── Invoice View / Download ─────────────────────────────────────────────────

function _statusBadgeHtml(status) {
    var cls = { paid: 'inv-status-paid', pending: 'inv-status-pending', overdue: 'inv-status-overdue', unpaid: 'inv-status-overdue', cancelled: 'inv-status-cancelled' };
    var c = cls[status] || 'inv-status-pending';
    return '<span class="inv-status ' + c + '">' + (status || '').toUpperCase() + '</span>';
}

function _canPayInvoiceStatus(status) {
    status = (status || '').toLowerCase();
    return status === 'pending' || status === 'overdue' || status === 'unpaid';
}

function _fmtDate(d) {
    if (!d) return 'N/A';
    var dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function _buildInvoiceHtml(inv) {
    var nuxCo  = '{$nuxhost_company}';
    var nuxMail= '{$nuxhost_email}';
    var tenCo  = '{$tenant_company}';
    var tenMail= '{$tenant_email}';
    var currency = inv.currency || 'KES';
    var amount   = parseFloat(inv.amount || 0).toFixed(2);
    var gateway  = inv.gateway  ? inv.gateway.replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
    var txnId    = inv.transaction_id || 'N/A';

    return '<div class="inv-card">' +
        '<div class="inv-header">' +
            '<div class="left">' +
                '<h2>' + (nuxCo || 'NuxHost') + '</h2>' +
                '<div style="color:#6c757d;font-size:.85rem;">' + (nuxMail || '') + '</div>' +
            '</div>' +
            '<div class="right">' +
                '<div class="inv-number">INVOICE</div>' +
                '<div style="font-size:.9rem;color:#6c757d;margin-top:4px;">' + inv.invoice_number + '</div>' +
                _statusBadgeHtml(inv.status) +
            '</div>' +
        '</div>' +

        '<div class="inv-parties">' +
            '<div class="col">' +
                '<div class="label-hdr">Billed By</div>' +
                '<strong>' + (nuxCo || 'NuxHost') + '</strong><br>' +
                '<span style="color:#6c757d;">' + (nuxMail || '') + '</span>' +
            '</div>' +
            '<div class="col">' +
                '<div class="label-hdr">Billed To</div>' +
                '<strong>' + (tenCo || 'Your Company') + '</strong><br>' +
                '<span style="color:#6c757d;">' + (tenMail || '') + '</span>' +
            '</div>' +
        '</div>' +

        '<div class="inv-details-grid">' +
            '<div class="inv-detail-row"><span class="k">Invoice #</span><span class="v">' + inv.invoice_number + '</span></div>' +
            '<div class="inv-detail-row"><span class="k">Amount</span><span class="v">' + currency + ' ' + amount + '</span></div>' +
            '<div class="inv-detail-row"><span class="k">Generated</span><span class="v">' + _fmtDate(inv.issue_date) + '</span></div>' +
            '<div class="inv-detail-row"><span class="k">Due Date</span><span class="v">' + _fmtDate(inv.due_date) + '</span></div>' +
            '<div class="inv-detail-row"><span class="k">Payment Method</span><span class="v">' + gateway + '</span></div>' +
            '<div class="inv-detail-row"><span class="k">Transaction ID</span><span class="v" style="font-family:monospace;font-size:.8rem;">' + txnId + '</span></div>' +
            (inv.paid_date ? '<div class="inv-detail-row"><span class="k">Paid On</span><span class="v">' + _fmtDate(inv.paid_date) + '</span></div>' : '') +
            (inv.generated_by ? '<div class="inv-detail-row"><span class="k">Generated By</span><span class="v">' + inv.generated_by + '</span></div>' : '') +
        '</div>' +

        '<table class="inv-line-table">' +
            '<thead><tr><th>Description</th><th style="text-align:right;">Amount</th></tr></thead>' +
            '<tbody>' +
                '<tr><td>' + (inv.notes || inv.description || 'Subscription Renewal') + '</td><td style="text-align:right;">' + currency + ' ' + amount + '</td></tr>' +
            '</tbody>' +
        '</table>' +

        '<div style="overflow:hidden;">' +
            '<div class="inv-total-box">' +
                '<div class="row"><span>Subtotal</span><span>' + currency + ' ' + amount + '</span></div>' +
                '<div class="row grand"><span>Total Due</span><span>' + currency + ' ' + amount + '</span></div>' +
            '</div>' +
        '</div>' +
        '<div style="clear:both;"></div>' +

        '<div class="inv-footer">' +
            '<strong>Thank you for your business!</strong><br>' +
            'For billing inquiries contact ' + (nuxMail || '') +
        '</div>' +
    '</div>';
}

function viewInvoice(inv) {
    _currentInvoice = inv;
    document.getElementById('viewInvoicePrintable').innerHTML = _buildInvoiceHtml(inv);
    var printBtn = document.getElementById('invPrintBtn');
    if (printBtn) printBtn.style.display = _canPayInvoiceStatus(inv.status) ? 'none' : '';
    var footer = document.getElementById('viewInvoicePayFooter');
    if (footer) {
        var isPayable = _canPayInvoiceStatus(inv.status);
        footer.style.display = isPayable ? 'flex' : 'none';
        if (isPayable) {
            var lbl = document.getElementById('viewInvoicePayLabel');
            if (lbl) lbl.textContent = (inv.currency || SM_CURRENCY) + ' ' + parseFloat(inv.amount || 0).toFixed(2) + ' outstanding';
        }
    }
    document.getElementById('viewInvoiceModal').style.display = 'flex';
}

function closeViewInvoiceModal() {
    document.getElementById('viewInvoiceModal').style.display = 'none';
    _currentInvoice = null;
}

function printCurrentInvoice() {
    // Open print dialog focused on the invoice content
    window.print();
}

function openPaymentModal(amount, currency) {
    _pmSelectedGwId  = null;
    _pmPaymentAmount = parseFloat(amount) || 0;
    var lbl = document.getElementById('pmAmountLabel');
    if (lbl) lbl.textContent = (currency || SM_CURRENCY) + ' ' + _pmPaymentAmount.toFixed(2);
    document.querySelectorAll('[id^="pmGwCard_"]').forEach(function(c) { c.classList.remove('selected'); });
    var ps = document.getElementById('pmPhoneSection');
    if (ps) ps.style.display = 'none';
    var st = document.getElementById('pmPayStatus');
    if (st) { st.style.display = 'none'; st.innerHTML = ''; }
    var btn = document.getElementById('pmSendBtn');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-send"></i> Send STK Push'; }
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function selectPaymentGateway(gwId, gwName) {
    _pmSelectedGwId = gwId;
    document.querySelectorAll('[id^="pmGwCard_"]').forEach(function(c) { c.classList.remove('selected'); });
    var card = document.getElementById('pmGwCard_' + gwId);
    if (card) card.classList.add('selected');
    var nameEl = document.getElementById('pmGwSelectedName');
    if (nameEl) nameEl.textContent = gwName;
    var ps = document.getElementById('pmPhoneSection');
    if (ps) ps.style.display = '';
    var pi = document.getElementById('pmPhoneInput');
    if (pi) pi.focus();
}

function openPaymentModalFromInvoice() {
    var inv = _currentInvoice;
    closeViewInvoiceModal();
    openPaymentModal(
        inv ? parseFloat(inv.amount || 0) : SM_AMOUNT_DUE,
        inv ? (inv.currency || SM_CURRENCY) : SM_CURRENCY
    );
}

{literal}
/* ── Payment helpers (no Smarty vars here) ── */

function _normalizePhone(p) {
    p = p.replace(/\D/g, '');
    if (p.length === 10 && p.charAt(0) === '0') p = '254' + p.substring(1);
    return p;
}

function _gwPayStatus(elId, type, html, retryFnStr) {
    var el = document.getElementById(elId);
    if (!el) return;
    var bg = { success: '#d1e7dd', error: '#f8d7da', warning: '#fff3cd', info: '#cfe2ff' };
    var cl = { success: '#0f5132', error: '#842029', warning: '#856404', info: '#084298' };
    el.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;font-size:.87rem;margin-top:8px;background:'
        + (bg[type] || '#e2e3e5') + ';color:' + (cl[type] || '#41464b') + ';';
    el.innerHTML = html + (retryFnStr
        ? ' <button onclick="' + retryFnStr + '" class="btn btn-xs btn-warning" style="margin-left:6px;">\u21bb Retry</button>'
        : '');
}

function _translateMpesaError(msg) {
    if (!msg) return 'Payment failed. Please try again.';
    var m = msg.toLowerCase();
    if (m.indexOf('wrong pin') !== -1 || m.indexOf('invalid pin') !== -1 || m.indexOf('ds00000003') !== -1)
        return 'Incorrect M-Pesa PIN entered. Please try again.';
    if (m.indexOf('cancel') !== -1 || m.indexOf('ds00000004') !== -1)
        return 'Transaction was cancelled. Please try again.';
    if (m.indexOf('insufficient') !== -1 || m.indexOf('balance') !== -1 || m.indexOf('ds00000005') !== -1)
        return 'Insufficient M-Pesa balance. Please top up and try again.';
    if (m.indexOf('timeout') !== -1 || m.indexOf('timed out') !== -1 || m.indexOf('request cancelled') !== -1)
        return 'M-Pesa request timed out. Please try again.';
    if (m.indexOf('limit') !== -1)
        return 'Transaction limit exceeded. Please try a smaller amount or try later.';
    return msg;
}

function _pollPayment(paymentId, statusElId, btnEl, checkUrl) {
    var maxAttempts = 45; /* ~3.5 min at 5 s — M-Pesa + host can be slow without webhook */
    var attempts = 0;
    var retryStr = 'retryCheckPayment(\'' + paymentId + '\',\'' + statusElId + '\',\'' + checkUrl.replace(/'/g, "\\'") + '\')';
    var timer = setInterval(function () {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(timer);
            _gwPayStatus(statusElId, 'warning',
                '<i class="fa fa-clock-o"></i> No confirmation from the billing server yet. If M-Pesa deducted the amount, tap <b>Retry</b> — we re-check with M-Pesa and activate your account when confirmed.', retryStr);
            if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="fa fa-send"></i> Send STK Push'; }
            return;
        }
        $.ajax({
            url: checkUrl + '&payment_id=' + encodeURIComponent(paymentId),
            type: 'GET', dataType: 'json',
            success: function (r) {
                var ps = r.payment_status || r.status || '';
                if (ps === 'completed') {
                    clearInterval(timer);
                    _gwPayStatus(statusElId, 'success',
                        '<i class="fa fa-check-circle"></i> Payment confirmed! Restoring your access...');
                    if (btnEl) btnEl.innerHTML = '<i class="fa fa-check"></i> Paid!';
                    setTimeout(function () { location.reload(); }, 2000);
                } else if (ps === 'failed' || ps === 'cancelled') {
                    clearInterval(timer);
                    _gwPayStatus(statusElId, 'error',
                        '<i class="fa fa-times-circle"></i> ' + _translateMpesaError(r.message || ''), retryStr);
                    if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<i class="fa fa-send"></i> Send STK Push'; }
                }
                /* pending → keep polling */
            },
            error: function () { /* keep polling */ }
        });
    }, 5000);
}

function retryCheckPayment(paymentId, statusElId, checkUrl) {
    var retryStr = 'retryCheckPayment(\'' + paymentId + '\',\'' + statusElId + '\',\'' + checkUrl.replace(/'/g, "\\'") + '\')';
    _gwPayStatus(statusElId, 'info', '<i class="fa fa-spinner fa-spin"></i> Checking payment status...');
    $.ajax({
        url: checkUrl + '&payment_id=' + encodeURIComponent(paymentId),
        type: 'GET', dataType: 'json',
        success: function (r) {
            var ps = r.payment_status || r.status || '';
            if (ps === 'completed') {
                _gwPayStatus(statusElId, 'success',
                    '<i class="fa fa-check-circle"></i> Payment confirmed! Restoring your access...');
                setTimeout(function () { location.reload(); }, 2000);
            } else if (ps === 'failed' || ps === 'cancelled') {
                _gwPayStatus(statusElId, 'error',
                    '<i class="fa fa-times-circle"></i> ' + _translateMpesaError(r.message || ''), retryStr);
            } else {
                _gwPayStatus(statusElId, 'warning',
                    '<i class="fa fa-clock-o"></i> Payment still processing \u2014 check back shortly.', retryStr);
            }
        },
        error: function () {
            _gwPayStatus(statusElId, 'error',
                '<i class="fa fa-times-circle"></i> Could not reach server.', retryStr);
        }
    });
}

function _doSendPayment(gwId, phone, amount, statusElId, btnEl) {
    if (!gwId) {
        _gwPayStatus(statusElId, 'error', '<i class="fa fa-exclamation-circle"></i> Please select a payment gateway first.');
        return;
    }
    phone = _normalizePhone(phone);
    if (!phone || phone.length < 12) {
        _gwPayStatus(statusElId, 'error', '<i class="fa fa-times-circle"></i> Please enter a valid phone number (e.g. 0712345678).');
        return;
    }
    btnEl.disabled = true;
    btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
    _gwPayStatus(statusElId, 'info', '<i class="fa fa-spinner fa-spin"></i> Sending STK push to +' + phone + '...');

    var checkUrl = SM_APP_URL + '/?_route=plugin/subscription_manager&type=check_payment';
    $.ajax({
        url: SM_APP_URL + '/?_route=plugin/subscription_manager&type=initiate_payment',
        type: 'POST', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify({ phone: phone, amount: amount, gateway: gwId }),
        success: function (resp) {
            if (resp.status === 'ok') {
                btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Waiting for PIN...';
                _gwPayStatus(statusElId, 'info',
                    '<i class="fa fa-mobile"></i> Payment prompt sent! Approve it on your phone...');
                _pollPayment(resp.payment_id, statusElId, btnEl, checkUrl);
            } else {
                btnEl.disabled = false;
                btnEl.innerHTML = '<i class="fa fa-send"></i> Send STK Push';
                _gwPayStatus(statusElId, 'error',
                    '<i class="fa fa-times-circle"></i> ' + _translateMpesaError(resp.message || ''));
            }
        },
        error: function () {
            btnEl.disabled = false;
            btnEl.innerHTML = '<i class="fa fa-send"></i> Send STK Push';
            _gwPayStatus(statusElId, 'error',
                '<i class="fa fa-times-circle"></i> Could not reach payment server. Check your connection.');
        }
    });
}

function sendPayment() {
    var phone = document.getElementById('pmPhoneInput').value.trim();
    _doSendPayment(_pmSelectedGwId, phone, _pmPaymentAmount, 'pmPayStatus', document.getElementById('pmSendBtn'));
}

function downloadInvoice(inv) {
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
        '<title>Invoice ' + inv.invoice_number + '</title>' +
        '<style>' +
            'body{font-family:Arial,sans-serif;color:#333;margin:32px;}' +
            '@page{margin:0;}' +
            'body{margin:1.5cm;}' +
            '.inv-card{max-width:760px;margin:0 auto;}' +
            '.inv-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;border-bottom:3px solid #0d6efd;padding-bottom:16px;}' +
            '.inv-header .left h2{margin:0 0 4px;color:#0d6efd;font-size:1.5rem;}' +
            '.inv-header .right{text-align:right;}' +
            '.inv-number{font-size:1.3rem;font-weight:700;color:#495057;}' +
            '.inv-status{display:inline-block;margin-top:8px;padding:4px 14px;border-radius:4px;font-size:.8rem;font-weight:700;}' +
            '.inv-status-paid{background:#d1e7dd;color:#0f5132;}' +
            '.inv-status-pending{background:#fff3cd;color:#856404;}' +
            '.inv-status-overdue{background:#f8d7da;color:#842029;}' +
            '.inv-status-cancelled{background:#e2e3e5;color:#41464b;}' +
            '.inv-parties{display:flex;justify-content:space-between;margin-bottom:24px;font-size:.88rem;}' +
            '.inv-parties .col{flex:1;}' +
            '.inv-parties .col:last-child{text-align:right;}' +
            '.label-hdr{text-transform:uppercase;font-size:.7rem;color:#6c757d;font-weight:700;letter-spacing:1px;margin-bottom:4px;}' +
            '.inv-details-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;background:#f8f9fa;border-radius:6px;padding:16px;margin-bottom:24px;font-size:.88rem;}' +
            '.inv-detail-row{display:flex;justify-content:space-between;}' +
            '.inv-detail-row .k{color:#6c757d;}' +
            '.inv-detail-row .v{font-weight:600;text-align:right;}' +
            '.inv-line-table{width:100%;border-collapse:collapse;margin-bottom:24px;font-size:.88rem;}' +
            '.inv-line-table th{background:#0d6efd;color:#fff;padding:10px 12px;text-align:left;}' +
            '.inv-line-table td{padding:10px 12px;border-bottom:1px solid #dee2e6;}' +
            '.inv-total-box{float:right;width:260px;font-size:.9rem;}' +
            '.inv-total-box .row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #dee2e6;}' +
            '.inv-total-box .row.grand{font-weight:700;font-size:1.1rem;color:#0d6efd;border-bottom:0;}' +
            '.inv-footer{text-align:center;margin-top:48px;font-size:.8rem;color:#6c757d;border-top:1px solid #dee2e6;padding-top:16px;}' +
        '</style></head><body>' +
        _buildInvoiceHtml(inv) +
        '<script>window.onload=function(){window.print();}<\/script>' +
        '</body></html>';
    var w = window.open('', '_blank', 'width=900,height=700');
    if (w) { w.document.write(html); w.document.close(); }
}
{/literal}
</script>

{include file="sections/footer.tpl"}
