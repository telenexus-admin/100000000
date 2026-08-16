{include file="sections/header.tpl"}

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">SMS Gateway Configuration</h3>
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{$_url}plugin/smsGateway_config">
                    <div class="form-group">
                        <label class="col-md-2 control-label">API Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="blessed_texts_api_key" name="blessed_texts_api_key" value="{$_c['blessed_texts_api_key']}">
                            <p class="help-block">Your Blessed Texts API Key</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Sender ID</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="blessed_texts_sender_id" name="blessed_texts_sender_id" value="{$_c['blessed_texts_sender_id']}">
                            <p class="help-block">Your assigned Sender ID (e.g., 23107)</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary" type="submit">Save Changes</button>
                            <a class="btn btn-default" href="{$_url}plugin/smsGateway">View SMS Logs / Balance</a>
                        </div>
                    </div>
                </form>
                <hr>
                <form class="form-horizontal" method="post" action="{$_url}plugin/smsGateway_test_send" onsubmit="return pamnetSmsTest(this);">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Test phone</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="phone" placeholder="07XXXXXXXX" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-success" type="submit">Send Test SMS</button>
                            <span id="pamnet_sms_test_result" style="margin-left:10px;"></span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">SMS Balance</h3>
            </div>
            <div class="panel-body">
                <p>Current Balance: <span id="sms_balance">Loading...</span></p>
                <button class="btn btn-info" onclick="checkBalance()">Check Balance</button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Last 10 SMS Messages</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Phone</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Message ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $sms_logs as $log}
                            <tr>
                                <td>{$log['created_at']}</td>
                                <td>{$log['phone']}</td>
                                <td>{$log['message']}</td>
                                <td>
                                    {if $log['status'] eq 'sent'}
                                        <span class="label label-success">Sent</span>
                                    {else}
                                        <span class="label label-danger" title="{$log['status_message']}">Failed</span>
                                    {/if}
                                </td>
                                <td>{$log['message_id']|default:'-'}</td>
                            </tr>
                            {foreachelse}
                            <tr>
                                <td colspan="5" class="text-center">No SMS messages found</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkBalance() {
    $('#sms_balance').text('Checking...');
    $('#debug_info').hide();
    $('#debug_output').text('');
    
    $.ajax({
        url: '{$_url}plugin/smsGateway_check_balance',
        type: 'POST',
        dataType: 'json',
        success: function(data) {
            if(data.success) {
                $('#sms_balance').text(data.balance + ' credits');
            } else {
                $('#sms_balance').text('Error: ' + data.message);
                console.error('Balance check failed:', data.message);
            }
        },
        error: function(xhr, status, error) {
            $('#sms_balance').text('Failed to fetch balance');
            console.error('AJAX error:', status, error);
            console.error('Response:', xhr.responseText);
        }
    });
}

// Check balance on page load
$(document).ready(function() {
    setTimeout(checkBalance, 1000); // Delay initial check by 1 second
});

function pamnetSmsTest(form) {
    var result = document.getElementById('pamnet_sms_test_result');
    if (result) { result.textContent = 'Sending…'; }
    $.ajax({
        url: '{$_url}plugin/smsGateway_test_send',
        type: 'POST',
        dataType: 'json',
        data: $(form).serialize(),
        success: function(data) {
            if (result) {
                result.textContent = data.message || (data.success ? 'Sent' : 'Failed');
                result.style.color = data.success ? 'green' : 'red';
            }
            if (data.success) {
                setTimeout(function(){ window.location.reload(); }, 1200);
            }
        },
        error: function(xhr) {
            if (result) {
                result.textContent = 'Request failed';
                result.style.color = 'red';
            }
            console.error(xhr.responseText);
        }
    });
    return false;
}
</script>

{include file="sections/footer.tpl"}
