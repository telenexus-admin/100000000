<?php
function kopokopo_validate_config()
{
    global $config;
    if (empty($config['kopokopo_client_id']) || empty($config['kopokopo_client_secret']) || empty($config['kopokopo_till_number'])) {
        sendTelegram("kopokopo Payment Gateway: Client ID or Client Secret or Till Number ID is not set in the config file.");
        r2(U . 'order/balance', 'w', Lang::T("Admin has not yet setup the payment gateway, please tell admin"));
    }
}

function kopokopo_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'Kopokopo Payment Gateway Settings for ' . $config['CompanyName']);
    $ui->display('kopokopo.tpl');
}


function kopokopo_save_config()
{
    global $admin;
    $settings = [
        'kopokopo_client_id' => _post('kopokopo_client_id'),
        'kopokopo_client_secret' => _post('kopokopo_client_secret'),
        'kopokopo_till_number' => _post('kopokopo_till_number'),
        'kopokopo_channel_ofline_online' => _post('kopokopo_channel_ofline_online'),
        'kopokopo_stk_till_number_id' => _post('kopokopo_stk_till_number_id'),
    ];
    // Update or insert settings in the database
    foreach ($settings as $key => $value) {
        $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        if ($d) {
            $d->value = $value;
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $key;
            $d->value = $value;
            $d->save();
        }
    }
    _log('[' . $admin['username'] . ']: kopokopo ' . Lang::T('Settings Saved Successfully') . json_encode($_POST['mpesa_channel']), 'Admin', $admin['id']);

    r2(U . 'paymentgateway/kopokopo', 's', Lang::T('Settings Saved Successfully'));
}


function kopokopo_create_transaction($trx, $user)
{
    $url = (U . "plugin/initiatekopokopo");
    $d = ORM::for_table('tbl_payment_gateway')->where('username', $user['username'])->where('status', 1)->find_one();
    $d->gateway_trx_id = '';
    $d->payment_method = 'kopokopo';
    $d->pg_url_payment = $url;
    $d->pg_request = '';
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+5 minutes"));
    $d->save();
    r2(U . "order/view/" . $d['id'], 's', Lang::T("Create Transaction Success, Please click pay now to process payment"));
    die();
}


function kopokopo_payment_notification()
{
    $captureLogs = file_get_contents("php://input");
    $analizzare = json_decode($captureLogs);
    file_put_contents('pages/hotspot-kopokopo-webhook.html', $captureLogs, FILE_APPEND);
    $id   = $analizzare->data->id;
    $checkout_req_id = $analizzare->data->attributes->metadata->reference;
    $PaymentGatewayRecord = ORM::for_table('tbl_payment_gateway')
        ->where('pg_request', $checkout_req_id)
        ->order_by_desc('id')
        ->find_one();

    if (!$PaymentGatewayRecord) {
        Message::sendTelegram("Mpesa Webook Notification:\n\n\n Transaction Record Not Found for this transaction [ " . $checkout_req_id . "]");
        exit();
    }

    $uname = $PaymentGatewayRecord->username;
    $userid = ORM::for_table('tbl_customers')
        ->where('username', $uname)
        ->order_by_desc('id')
        ->find_one();

    if (!$userid) {
        Message::sendTelegram("Mpesa Webook Notification:\n\n\n Transaction Record Not Found for this Username [ " . $uname . "]");
        exit();
    }

    $userid->username = $uname;
    $userid->save();
    $UserId = $userid->id;

    $status = $analizzare->data->attributes->status;
    $now = date('Y-m-d H:i:s');
    $now = date('Y-m-d H:i:s');
    $date = date('Y-m-d');
    $time = date('H:i:s');
    if ($status == 'Success') {
        $mpesa_transaction_code = $analizzare->data->attributes->event->resource->reference;
        $amount_paid = $analizzare->data->attributes->event->resource->amount;
        $PaymentGatewayRecord->gateway_trx_id = $mpesa_transaction_code;
        $PaymentGatewayRecord->paid_date = $now;

        if (Package::completeStkPayment($PaymentGatewayRecord, $UserId, $mpesa_transaction_code)) {
            $PaymentGatewayRecord->status = 2;
            $PaymentGatewayRecord->save();
        } else {
            $PaymentGatewayRecord->status = 1;
            $PaymentGatewayRecord->pg_paid_response = 'PAID_PENDING_ACTIVATION';
            $PaymentGatewayRecord->save();
            Message::sendTelegram("STK Push PAID but activation failed for {$uname} [{$mpesa_transaction_code}] — will retry on verify");
        }
    } elseif ($status == 'Failed') {
        $errors = $analizzare->data->attributes->event->errors;
        $PaymentGatewayRecord->status = 1;
        $PaymentGatewayRecord->pg_paid_response =  $errors;
        $PaymentGatewayRecord->save();
    }
    exit();
}


function addHashedSenderPhoneColumnIfNotExists()
{
    $table = 'tbl_user_recharges';
    $column = 'hashed_sender_phone';
    // Check if the column exists
    $checkColumnSQL = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = ORM::get_db()->query($checkColumnSQL);

    if ($result->rowCount() === 0) {
        // Column doesn't exist, so add it
        $alterTableSQL = "ALTER TABLE `$table` 
                      ADD `$column` VARCHAR(255) DEFAULT NULL";
        ORM::raw_execute($alterTableSQL);
    }
}
