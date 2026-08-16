{include file="sections/header.tpl"}
<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                <div class="pull-right" style="margin-top: -2px;">
                    <span class="label label-default" title="{Lang::T('Mpesa_Transactions')}">{$txn_count}</span>
                </div>
                {Lang::T('Mpesa_Transactions')}
            </div>
            <div class="panel-body">
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-8">
                        <form method="get" action="{Text::url('plugin/mpesa_transactions')}" class="form-inline" id="mpesa-search-form">
                            <div class="input-group" style="width: 100%; max-width: 480px;">
                                <div class="input-group-addon"><span class="fa fa-search"></span></div>
                                <input type="text" name="search" id="live-search" class="form-control"
                                    value="{$search|escape:'html':'UTF-8'}"
                                    placeholder="{Lang::T('Search')}…"
                                    autocomplete="off">
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="submit">{Lang::T('Search')}</button>
                                </span>
                            </div>
                        </form>
                    </div>
                </div>

                <form method="post" action="{Text::url('plugin/mpesa_transactions_multi_delete')}" id="mpesa-multi-delete-form">
                    <input type="hidden" name="return_search" id="mpesa-return-search" value="{$search|escape:'html':'UTF-8'}">

                    <div class="clearfix" style="margin-bottom: 12px;">
                        <div class="pull-left" style="margin-bottom: 8px;">
                            <button type="submit" class="btn btn-danger btn-sm" id="mpesa-delete-selected" disabled
                                onclick="document.getElementById('mpesa-return-search').value = document.getElementById('live-search').value; return ask(this, '{Lang::T('Delete_selected_M_Pesa_transactions')}?');">
                                <i class="glyphicon glyphicon-trash"></i> {Lang::T('Delete_Selected')}
                            </button>
                            <button type="button" class="btn btn-default btn-sm" id="mpesa-clear-selection" disabled style="margin-left: 6px;">
                                {Lang::T('Clear_selection')}
                            </button>
                        </div>
                        <div class="pull-right text-muted small" id="mpesa-selection-hint" style="padding-top: 6px;">
                            <span id="mpesa-selected-count">0</span> {Lang::T('rows_selected')}
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed table-hover" id="mpesa-txn-table">
                            <thead>
                                <tr>
                                    <th style="width: 42px;" class="text-center">
                                        <input type="checkbox" id="mpesa-select-all" title="{Lang::T('Select_All')}">
                                    </th>
                                    <th>#</th>
                                    <th>{Lang::T('First_Name')}</th>
                                    <th>{Lang::T('Phone')}</th>
                                    <th>{Lang::T('Amount')}</th>
                                    <th>{Lang::T('Account_No')}</th>
                                    <th>{Lang::T('Org_Account_Balance')}</th>
                                    <th>{Lang::T('Transaction_ID')}</th>
                                    <th>{Lang::T('Transaction_Type')}</th>
                                    <th>{Lang::T('Transaction_Time')}</th>
                                    <th>{Lang::T('Business_Short_Code')}</th>
                                </tr>
                            </thead>
                            <tbody id="transaction-table-body">
                                {foreach $t as $key => $ts}
                                    <tr class="transaction-row">
                                        <td class="text-center">
                                            <input type="checkbox" name="mpesa_txn_ids[]" value="{$ts.id}" class="mpesa-row-cb">
                                        </td>
                                        <td>{$key + 1}</td>
                                        <td class="search-target">{$ts.FirstName|escape:'html':'UTF-8'}</td>
                                        <td class="search-target">{if $ts.MSISDN}{$ts.MSISDN|truncate:20:"..."}{else}{Lang::T('No_MSISDN_available')}{/if}</td>
                                        <td class="search-target">{$ts.TransAmount}</td>
                                        <td class="search-target">{$ts.BillRefNumber|escape:'html':'UTF-8'}</td>
                                        <td>{$ts.OrgAccountBalance}</td>
                                        <td class="search-target">{$ts.TransID|escape:'html':'UTF-8'}</td>
                                        <td>{$ts.TransactionType|escape:'html':'UTF-8'}</td>
                                        <td>{$ts.TransTime|date_format:"%B %e, %Y, %I:%M %p"}</td>
                                        <td>{$ts.BusinessShortCode|escape:'html':'UTF-8'}</td>
                                    </tr>
                                {foreachelse}
                                    <tr>
                                        <td colspan="11" class="text-center text-muted" style="padding: 24px;">
                                            <strong>{Lang::T('No_M_Pesa_transactions_to_display')}</strong>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </form>

                <div id="no-results" class="alert alert-warning" style="display: none;">
                    <strong>{Lang::T('No_matching_M_Pesa_transactions')}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
{include file="sections/footer.tpl"}

{literal}
<script>
(function ($) {
    function updateToolbar() {
        var checked = $('.mpesa-row-cb:checked').length;
        var $del = $('#mpesa-delete-selected');
        var $clr = $('#mpesa-clear-selection');
        $('#mpesa-selected-count').text(checked);
        $del.prop('disabled', checked === 0);
        $clr.prop('disabled', checked === 0);
        var vis = $('.transaction-row:visible .mpesa-row-cb');
        var visChecked = $('.transaction-row:visible .mpesa-row-cb:checked');
        var $all = $('#mpesa-select-all');
        if (vis.length === 0) {
            $all.prop('checked', false).prop('indeterminate', false);
            return;
        }
        if (visChecked.length === 0) {
            $all.prop('checked', false).prop('indeterminate', false);
        } else if (visChecked.length === vis.length) {
            $all.prop('checked', true).prop('indeterminate', false);
        } else {
            $all.prop('checked', false).prop('indeterminate', true);
        }
    }
    $(document).ready(function () {
        $('#live-search').on('keyup input', function () {
            var q = $(this).val().toLowerCase();
            var hasResults = false;
            var total = $('.transaction-row').length;
            $('.transaction-row').each(function () {
                var show = $(this).text().toLowerCase().indexOf(q) !== -1;
                $(this).toggle(show);
                if (show) hasResults = true;
            });
            $('.mpesa-row-cb').prop('checked', false);
            $('#mpesa-select-all').prop('checked', false).prop('indeterminate', false);
            $('#no-results').toggle(total > 0 && !hasResults);
            updateToolbar();
        });

        $('#mpesa-select-all').on('change', function () {
            var on = $(this).prop('checked');
            $('.transaction-row:visible .mpesa-row-cb').prop('checked', on);
            updateToolbar();
        });

        $(document).on('change', '.mpesa-row-cb', updateToolbar);

        $('#mpesa-clear-selection').on('click', function () {
            $('.mpesa-row-cb').prop('checked', false);
            $('#mpesa-select-all').prop('checked', false).prop('indeterminate', false);
            updateToolbar();
        });

        $('#mpesa-multi-delete-form').on('submit', function () {
            $('#mpesa-return-search').val($('#live-search').val());
            if ($('.mpesa-row-cb:checked').length === 0) {
                return false;
            }
        });

        updateToolbar();
    });
})(jQuery);
</script>
{/literal}
