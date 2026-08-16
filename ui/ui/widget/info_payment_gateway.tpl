<div class="panel panel-success panel-hovered mb20 activities">
    <div class="panel-heading">{Lang::T('Active Payment Gateway')}: {str_replace(',',', ',$_c['payment_gateway'])}
    </div>
    <div class="panel-body" style="padding:10px 15px;">
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div><strong>Successful today:</strong> {$payments_today.successful|default:0}</div>
            <div><strong>Failed today:</strong> {$payments_today.failed|default:0}</div>
            <div><strong>Pending today:</strong> {$payments_today.pending|default:0}</div>
        </div>
    </div>
</div>