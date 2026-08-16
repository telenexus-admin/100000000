{include file="sections/header.tpl"}
<style>
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        display: inline-block;
        padding: 5px 10px;
        margin-right: 5px;
        border: 1px solid #ccc;
        background-color: #fff;
        color: #333;
        cursor: pointer;
    }
    .btn-conn-status {
        min-width: 88px;
        font-weight: 700;
        letter-spacing: .02em;
        pointer-events: none;
        opacity: 1 !important;
        cursor: default;
    }
    .btn-conn-status.btn-success { background: #00a65a; border-color: #008d4c; }
    .btn-conn-status.btn-warning { background: #f39c12; border-color: #e08e0b; color: #fff; }
    .btn-conn-status.btn-danger { background: #dd4b39; border-color: #d73925; }
    td.conn-status-cell { white-space: nowrap; text-align: center; vertical-align: middle !important; }
    .conn-legend .btn-conn-status { margin-right: 6px; }
    .conn-legend { margin: 0 0 10px; font-size: 12px; color: #666; }
</style>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                <div class="btn-group pull-right">
                    <a class="btn btn-primary btn-xs" title="save"
                        href="{Text::url('customers/csv&token=', $csrf_token)}"
                        onclick="return ask(this, '{Lang::T("This will export to CSV")}?')"><span
                            class="glyphicon glyphicon-download" aria-hidden="true"></span> CSV</a>
                </div>
                {/if}
                {Lang::T('Manage Contact')}
            </div>
            <div class="panel-body">
                <form id="site-search" method="post" action="{Text::url('customers')}">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <div class="md-whiteframe-z1 mb20 text-center" style="padding: 15px">
                        <div class="col-lg-4">
                            <div class="input-group">
                                <span class="input-group-addon">{Lang::T('Order ')}&nbsp;&nbsp;</span>
                                <div class="row row-no-gutters">
                                    <div class="col-xs-8">
                                        <select class="form-control" id="order" name="order">
                                            <option value="username" {if $order eq 'username' }selected{/if}>
                                                {Lang::T('Username')}</option>
                                            <option value="fullname" {if $order eq 'fullname' }selected{/if}>
                                                {Lang::T('First Name')}</option>
                                            <option value="lastname" {if $order eq 'lastname' }selected{/if}>
                                                {Lang::T('Last Name')}</option>
                                            <option value="created_at" {if $order eq 'created_at' }selected{/if}>
                                                {Lang::T('Created Date')}</option>
                                            <option value="balance" {if $order eq 'balance' }selected{/if}>
                                                {Lang::T('Balance')}</option>
                                            <option value="status" {if $order eq 'status' }selected{/if}>
                                                {Lang::T('Status')}</option>
                                        </select>
                                    </div>
                                    <div class="col-xs-4">
                                        <select class="form-control" id="orderby" name="orderby">
                                            <option value="asc" {if $orderby eq 'asc' }selected{/if}>
                                                {Lang::T('Ascending')}</option>
                                            <option value="desc" {if $orderby eq 'desc' }selected{/if}>
                                                {Lang::T('Descending')}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="input-group">
                                <span class="input-group-addon">{Lang::T('Status')}</span>
                                <select class="form-control" id="filter" name="filter">
                                    {foreach $statuses as $status}
                                    <option value="{$status}" {if $filter eq $status }selected{/if}>{Lang::T($status)}
                                    </option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control"
                                    placeholder="{Lang::T('Search')}..." value="{$search}">
                                <div class="input-group-btn">
                                    <button class="btn btn-primary" type="submit"><span class="fa fa-search"></span>
                                        {Lang::T('Search')}</button>
                                    <button class="btn btn-info" type="submit" name="export" value="csv">
                                        <span class="glyphicon glyphicon-download" aria-hidden="true"></span> CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-1">
                            <a href="{Text::url('customers/add')}" class="btn btn-success btn-block"
                                title="{Lang::T('Add Customer')}">
                                <i class="ion ion-android-add"></i> {Lang::T('Add')}
                            </a>
                        </div>
                    </div>
                </form>
                <br>&nbsp;
                <div class="conn-legend">
                    <button type="button" class="btn btn-success btn-xs btn-conn-status" disabled><i class="fa fa-wifi"></i> Online</button>
                    connected now &nbsp;
                    <button type="button" class="btn btn-warning btn-xs btn-conn-status" disabled><i class="fa fa-unlink"></i> Offline</button>
                    package active, not connected &nbsp;
                    <button type="button" class="btn btn-danger btn-xs btn-conn-status" disabled><i class="fa fa-clock-o"></i> Expired</button>
                    package ended &nbsp;
                    <span class="text-muted" id="connStatusUpdated"></span>
                </div>
                <div class="table-responsive table_mobile">
                    <table id="customerTable" class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>{Lang::T('Username')}</th>
                                <th>Photo</th>
                                <th>{Lang::T('Account Type')}</th>
                                <th>{Lang::T('Full Name')}</th>
                                <th>{Lang::T('Balance')}</th>
                                <th>{Lang::T('Contact')}</th>
                                <th>{Lang::T('Package')}</th>
                                <th>{Lang::T('Service Type')}</th>
                                <th>PPPOE</th>
                                <th>Connection</th>
                                <th>{Lang::T('Status')}</th>
                                <th>{Lang::T('Created On')}</th>
                                <th>{Lang::T('Manage')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds}
                            <tr {if $ds['status'] !='Active' }class="danger" {/if} data-customer-id="{$ds['id']}">
                                <td><input type="checkbox" name="customer_ids[]" value="{$ds['id']}"></td>
                                <td onclick="window.location.href = '{Text::url('customers/view/', $ds['id'])}'"
                                    style="cursor:pointer;">{$ds['username']}</td>
                                <td>
                                    <a href="{$app_url}/{$UPLOAD_PATH}{$ds['photo']}" target="photo">
                                        <img src="{$app_url}/{$UPLOAD_PATH}{$ds['photo']}.thumb.jpg" width="32" alt="">
                                    </a>
                                </td>
                                <td>{$ds['account_type']}</td>
                                <td onclick="window.location.href = '{Text::url('customers/view/', $ds['id'])}'"
                                    style="cursor: pointer;">{$ds['fullname']}</td>
                                <td>{Lang::moneyFormat($ds['balance'])}</td>
                                <td align="center">
                                    {if $ds['phonenumber']}
                                    <a href="tel:{$ds['phonenumber']}" class="btn btn-default btn-xs"
                                        title="{$ds['phonenumber']}"><i class="glyphicon glyphicon-earphone"></i></a>
                                    {/if}
                                    {if $ds['email']}
                                    <a href="mailto:{$ds['email']}" class="btn btn-default btn-xs"
                                        title="{$ds['email']}"><i class="glyphicon glyphicon-envelope"></i></a>
                                    {/if}
                                </td>
                                <td align="center" api-get-text="{Text::url('autoload/plan_is_active/')}{$ds['id']}">
                                    <span class="label label-default">&bull;</span>
                                </td>
                                <td>{$ds['service_type']}</td>
                                <td>
                                    {$ds['pppoe_username']}
                                    {if !empty($ds['pppoe_username']) && !empty($ds['pppoe_ip'])}:{/if}
                                    {$ds['pppoe_ip']}
                                </td>
                                <td class="conn-status-cell" data-conn-id="{$ds['id']}">
                                    {assign var=cid value=$ds.id}
                                    {if isset($conn_statuses[$cid]) && $conn_statuses[$cid].status == 'online'}
                                    <button type="button" class="btn btn-success btn-xs btn-conn-status" disabled data-status="online"><i class="fa fa-wifi"></i> Online</button>
                                    {elseif isset($conn_statuses[$cid]) && $conn_statuses[$cid].status == 'offline'}
                                    <button type="button" class="btn btn-warning btn-xs btn-conn-status" disabled data-status="offline"><i class="fa fa-unlink"></i> Offline</button>
                                    {else}
                                    <button type="button" class="btn btn-danger btn-xs btn-conn-status" disabled data-status="expired"><i class="fa fa-clock-o"></i> Expired</button>
                                    {/if}
                                </td>
                                <td>{Lang::T($ds['status'])}</td>
                                <td>{Lang::dateTimeFormat($ds['created_at'])}</td>
                                <td align="center">
                                    <a href="{Text::url('customers/view/')}{$ds['id']}" id="{$ds['id']}"
                                        style="margin: 0px; color:black"
                                        class="btn btn-success btn-xs">&nbsp;&nbsp;{Lang::T('View')}&nbsp;&nbsp;</a>
                                    <a href="{Text::url('customers/edit/', $ds['id'], '&token=', $csrf_token)}"
                                        id="{$ds['id']}" style="margin: 0px; color:black"
                                        class="btn btn-info btn-xs">&nbsp;&nbsp;{Lang::T('Edit')}&nbsp;&nbsp;</a>
                                    <a href="{Text::url('customers/sync/', $ds['id'], '&token=', $csrf_token)}"
                                        id="{$ds['id']}" style="margin: 5px; color:black"
                                        class="btn btn-success btn-xs">&nbsp;&nbsp;{Lang::T('Sync')}&nbsp;&nbsp;</a>
                                    <a href="{Text::url('plan/recharge/', $ds['id'], '&token=', $csrf_token)}"
                                        id="{$ds['id']}" style="margin: 0px;"
                                        class="btn btn-primary btn-xs">{Lang::T('Recharge')}</a>
                                </td>
                            </tr>
                            {foreachelse}
                            <tr>
                                <td colspan="14" class="text-center">
                                    <br>
                                    <h4>{Lang::T('No customers found with status')}: <strong>{$filter}</strong></h4>
                                    <p>{Lang::T('Try changing the status filter or search criteria')}</p>
                                    <a href="{Text::url('customers/add')}" class="btn btn-primary">
                                        <i class="ion ion-android-add"></i> {Lang::T('Add New Customer')}
                                    </a>
                                    <br><br>
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    <div class="row" style="padding: 5px">
                        <div class="col-lg-4 col-lg-offset-8">
                            <div class="btn-group btn-group-justified" role="group">
                                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                                <div class="btn-group" role="group">
                                    <button type="button" id="deleteSelectedCustomers" class="btn btn-danger">{Lang::T('Delete Selected')}</button>
                                </div>
                                {/if}
                                <div class="btn-group" role="group">
                                    <button type="button" id="sendMessageToSelected" class="btn btn-success">{Lang::T('Send Message')}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
</div>
<!-- Modal for Sending Messages -->
<div id="sendMessageModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="sendMessageModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendMessageModalLabel">{Lang::T('Send Message')}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <select id="messageType" class="form-control">
                    <option value="all">{Lang::T('All')}</option>
                    <option value="email">{Lang::T('Email')}</option>
                    <option value="inbox">{Lang::T('Inbox')}</option>
                    <option value="sms">{Lang::T('SMS')}</option>
                    <option value="wa">{Lang::T('WhatsApp')}</option>
                </select>
                <br>
                <textarea id="messageContent" class="form-control" rows="4"
                    placeholder="{Lang::T('Enter your message here...')}"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{Lang::T('Close')}</button>
                <button type="button" id="sendMessageButton" class="btn btn-primary">{Lang::T('Send Message')}</button>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Select or deselect all checkboxes
    document.getElementById('select-all').addEventListener('change', function () {
        var checkboxes = document.querySelectorAll('input[name="customer_ids[]"]');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });

    $(document).ready(function () {
        let selectedCustomerIds = [];
        var csrfToken = '{$csrf_token}';
        var multiDeleteUrl = "{Text::url('customers/multi-delete')}";

        function getSelectedCustomerIds() {
            return $('input[name="customer_ids[]"]:checked').map(function () {
                return $(this).val();
            }).get();
        }

        $('#deleteSelectedCustomers').on('click', function () {
            selectedCustomerIds = getSelectedCustomerIds();
            if (selectedCustomerIds.length === 0) {
                Swal.fire({
                    title: 'Error!',
                    text: "{Lang::T('Please select at least one customer to delete.')}",
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: "{Lang::T('Are you sure?')}",
                text: "{Lang::T('Delete')} " + selectedCustomerIds.length + " {Lang::T('selected customer(s)? This cannot be undone.')}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dd4b39',
                confirmButtonText: "{Lang::T('Yes, delete')}",
                cancelButtonText: "{Lang::T('Cancel')}"
            }).then(function (result) {
                if (!result.isConfirmed && !result.value) return;

                var $btn = $('#deleteSelectedCustomers');
                $btn.prop('disabled', true).text('{Lang::T('Deleting...')}');

                $.ajax({
                    url: multiDeleteUrl,
                    method: 'POST',
                    data: {
                        csrf_token: csrfToken,
                        customer_ids: JSON.stringify(selectedCustomerIds)
                    },
                    dataType: 'json'
                }).done(function (response) {
                    if (response && response.status === 'success') {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message || "{Lang::T('Customers deleted successfully.')}",
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(function () {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: (response && response.message) ? response.message : "{Lang::T('Failed to delete customers.')}",
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                }).fail(function () {
                    Swal.fire({
                        title: 'Error!',
                        text: "{Lang::T('Failed to delete customers. Please try again.')}",
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }).always(function () {
                    $btn.prop('disabled', false).text('{Lang::T('Delete Selected')}');
                });
            });
        });

        // Collect selected customer IDs when the button is clicked
        $('#sendMessageToSelected').on('click', function () {
            selectedCustomerIds = getSelectedCustomerIds();

            if (selectedCustomerIds.length === 0) {
                Swal.fire({
                    title: 'Error!',
                    text: "{Lang::T('Please select at least one customer to send a message.')}",
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Open the modal
            $('#sendMessageModal').modal('show');
        });

        // Handle sending the message
        $('#sendMessageButton').on('click', function () {
            const message = $('#messageContent').val().trim();
            const messageType = $('#messageType').val();

            if (!message) {
                Swal.fire({
                    title: 'Error!',
                    text: "{Lang::T('Please enter a message to send.')}",
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Disable the button and show loading text
            $(this).prop('disabled', true).text('{Lang::T('Sending...')}');

            $.ajax({
                url: '?_route=message/send_bulk_selected',
                method: 'POST',
                data: {
                    customer_ids: selectedCustomerIds,
                    message_type: messageType,
                    message: message
                },
                dataType: 'json',
                success: function (response) {
                    // Handle success response
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Success!',
                            text: "{Lang::T('Message sent successfully.')}",
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: "{Lang::T('Error sending message: ')}" + response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                    $('#sendMessageModal').modal('hide');
                    $('#messageContent').val(''); // Clear the message content
                },
                error: function () {
                    Swal.fire({
                        title: 'Error!',
                        text: "{Lang::T('Failed to send the message. Please try again.')}",
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                },
                complete: function () {
                    // Re-enable the button and reset text
                    $('#sendMessageButton').prop('disabled', false).text('{Lang::T('Send Message')}');
                }
            });
        });
    });

    $(document).ready(function () {
        $('#sendMessageModal').on('show.bs.modal', function () {
            $(this).attr('inert', 'true');
        });
        $('#sendMessageModal').on('shown.bs.modal', function () {
            $('#messageContent').focus();
            $(this).removeAttr('inert');
        });
        $('#sendMessageModal').on('hidden.bs.modal', function () {
            // $('#button').focus();
        });
    });

    (function () {
        var statusUrl = "{Text::url('autoload/customers_live_status')}";
        var pollMs = 15000;
        var timer = null;
        var busy = false;

        function collectIds() {
            var ids = [];
            document.querySelectorAll('[data-conn-id]').forEach(function (el) {
                var id = el.getAttribute('data-conn-id');
                if (id) ids.push(id);
            });
            return ids;
        }

        function refreshConnStatuses() {
            if (busy || document.hidden) return;
            var ids = collectIds();
            if (!ids.length) return;
            busy = true;
            $.ajax({
                url: statusUrl,
                data: { ids: ids.join(',') },
                dataType: 'json',
                timeout: 12000
            }).done(function (res) {
                if (!res || !res.ok || !res.statuses) return;
                Object.keys(res.statuses).forEach(function (cid) {
                    var cell = document.querySelector('[data-conn-id="' + cid + '"]');
                    if (!cell || !res.statuses[cid].html) return;
                    var next = res.statuses[cid].status;
                    var curBtn = cell.querySelector('[data-status]');
                    var cur = curBtn ? curBtn.getAttribute('data-status') : '';
                    if (cur !== next) {
                        cell.innerHTML = res.statuses[cid].html;
                    }
                });
                var stamp = document.getElementById('connStatusUpdated');
                if (stamp) {
                    var d = new Date();
                    stamp.textContent = 'Updated ' + d.toLocaleTimeString();
                }
            }).always(function () {
                busy = false;
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshConnStatuses();
        });
        refreshConnStatuses();
        timer = setInterval(refreshConnStatuses, pollMs);
    })();
</script>
{include file = "sections/footer.tpl" }