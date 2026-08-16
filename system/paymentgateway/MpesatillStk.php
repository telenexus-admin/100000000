<?php


function MpesatillStk_validate_config()
{
    global $config;
    if (empty($config['mpesa_till_shortcode_code']) || empty($config['mpesa_till_consumer_key']) || empty($config['mpesa_till_consumer_secret']) || empty($config['mpesa_till_partyb'])) {
        sendTelegram("Mpesa Till Stk payment gateway not configured");
        r2(U . 'order/balance', 'w', Lang::T("Admin has not yet setup the payment gateway, please tell admin"));
    }
}

function MpesatillStk_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'M-Pesa - Payment Gateway (for till number only) - ' . $config['CompanyName']);
    $ui->display('mpesatill.tpl');
}


function MpesatillStk_save_config()
{
    global $admin;
    $mpesa_till_consumer_key = _post('mpesa_till_consumer_key');
    $mpesa_till_consumer_secret = _post('mpesa_till_consumer_secret');
    $mpesa_till_business_code = _post('mpesa_till_business_code');
    $mpesa_till_partyb = _post('mpesa_till_partyb');
    $mpesa_till_pass_key = _post('mpesa_till_pass_key');
    $mpesa_till_env = _post('mpesa_till_env');

    $settings = [
        'mpesa_till_consumer_key' => $mpesa_till_consumer_key,
        'mpesa_till_consumer_secret' => $mpesa_till_consumer_secret,
        'mpesa_till_business_code' => $mpesa_till_business_code,
        'mpesa_till_partyb' => $mpesa_till_partyb,
        'mpesa_till_pass_key' => $mpesa_till_pass_key,
        'mpesa_till_env' => $mpesa_till_env
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
    _log('[' . $admin['username'] . ']: M-Pesa Till' . Lang::T('Settings Saved Successfully') . json_encode($_POST['mpesa_channel']), 'Admin', $admin['id']);

    r2(U . 'paymentgateway/MpesatillStk', 's', Lang::T('Settings Saved Successfully'));
}


function MpesatillStk_create_transaction($trx, $user)
{
    $url = (U . "plugin/initiatetillstk");
    $d = ORM::for_table('tbl_payment_gateway')->where('username', $user['username'])->where('status', 1)->find_one();
    $d->gateway_trx_id = '';
    $d->payment_method = 'Mpesa till STK';
    $d->pg_url_payment = $url;
    $d->pg_request = '';
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+5 minutes"));
    $d->save();
    r2(U . "order/view/" . $d['id'], 's', Lang::T("Create Transaction Success, Please click pay now to process payment"));
    die();
}



function MpesatillStk_payment_notification()
{
    $captureLogs = file_get_contents("php://input");
    $analizzare = json_decode($captureLogs);
    file_put_contents('pages/hotspot-mpesa-webhook.html', $captureLogs, FILE_APPEND);

    // Ensure the parsed JSON object is not null
    if (is_null($analizzare)) {
        _log('Transaction Response Return Null Response: [ ' . $captureLogs . ' ]');
        exit();
    }

    $response_code   = $analizzare->Body->stkCallback->ResultCode ?? null;
    $resultDesc      = $analizzare->Body->stkCallback->ResultDesc ?? '';
    $merchant_req_id = $analizzare->Body->stkCallback->MerchantRequestID ?? '';
    $checkout_req_id = $analizzare->Body->stkCallback->CheckoutRequestID ?? '';
    $amount_paid     = $analizzare->Body->stkCallback->CallbackMetadata->Item[0]->Value ?? 0; //get the amount value
    $mpesa_code      = $analizzare->Body->stkCallback->CallbackMetadata->Item[1]->Value ?? ''; //mpesa transaction code
    $sender_phone    = $analizzare->Body->stkCallback->CallbackMetadata->Item[4]->Value ?? ''; //Telephone Number

    $PaymentGatewayRecord = ORM::for_table('tbl_payment_gateway')
        ->where('pg_request', $checkout_req_id)
        ->where('status', 1) // Add this line to filter by status
        ->order_by_desc('id')
        ->find_one();

    if (!$PaymentGatewayRecord) {
        // C2B may have already marked status=2 — still force-login the phone.
        $already = ORM::for_table('tbl_payment_gateway')
            ->where('pg_request', $checkout_req_id)
            ->order_by_desc('id')
            ->find_one();
        if ($already && (int) $already->status === 2) {
            if ($response_code == "0" && class_exists('PamnetHotspotPay')) {
                if (!empty($mpesa_code) && empty($already->gateway_trx_id)) {
                    $already->gateway_trx_id = $mpesa_code;
                    $already->save();
                }
                PamnetHotspotPay::connectDeviceNow($already->username);
            }
            exit();
        }
        _log('Transaction Record Not Found for this transaction [ ' . $checkout_req_id . ' ]');
        Message::sendTelegram("Mpesa Webook Notification:\n\n\n Transaction Record Not Found for this transaction [ " . $checkout_req_id . "]");
        exit();
    }

    $uname = $PaymentGatewayRecord->username;
    $userid = ORM::for_table('tbl_customers')
        ->where('username', $uname)
        ->order_by_desc('id')
        ->find_one();

    if (!$userid) {
        _log('Transaction Record Not Found for this Username [ ' . $uname . ' ]');
        Message::sendTelegram("Mpesa Webook Notification:\n\n\n Transaction Record Not Found for this Username [ " . $uname . "]");
        exit();
    }

    $userid->username = $uname;
    $userid->save();
    $UserId = $userid->id;

    if ($response_code == "1032") {
        $now = date('Y-m-d H:i:s');
        $PaymentGatewayRecord->paid_date = $now;
        $PaymentGatewayRecord->status = 4;
        $PaymentGatewayRecord->save();
        exit();
    }
    if ($response_code == "1037") {
        $PaymentGatewayRecord->status = 4;
        $PaymentGatewayRecord->paid_date = date('Y-m-d H:i:s');
        $PaymentGatewayRecord->pg_paid_response = 'User failed to enter pin';
        $PaymentGatewayRecord->save();
        exit();
    }
    if ($response_code == "1") {
        $PaymentGatewayRecord->status = 4;
        $PaymentGatewayRecord->paid_date = date('Y-m-d H:i:s');
        $PaymentGatewayRecord->pg_paid_response = 'Not enough balance';
        $PaymentGatewayRecord->save();
        exit();
    }

    if ($response_code == "2001") {
        $PaymentGatewayRecord->status = 4;
        $PaymentGatewayRecord->paid_date = date('Y-m-d H:i:s');
        $PaymentGatewayRecord->pg_paid_response = 'Wrong Mpesa pin';
        $PaymentGatewayRecord->save();
        exit();
    }

    if ($response_code == "0") {
        $now = date('Y-m-d H:i:s');
        $date = date('Y-m-d');
        $time = date('H:i:s');

        // Mark paid first so portal leaves wait modal within ~1s
        $PaymentGatewayRecord->status = 2;
        $PaymentGatewayRecord->paid_date = $now;
        $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
        $PaymentGatewayRecord->pg_paid_response = 'STK paid';
        $PaymentGatewayRecord->save();

        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
            }
            @flush();
        }
        ignore_user_abort(true);

        try {
            if (!Package::rechargeUser($UserId, $PaymentGatewayRecord->routers, $PaymentGatewayRecord->plan_id, $PaymentGatewayRecord->gateway, 'STK-Push', '', $mpesa_code)) {
                $existingTx = ORM::for_table('tbl_transactions')->where('invoice', $mpesa_code)->find_one();
                if (!$existingTx) {
                    $transaction = ORM::for_table('tbl_transactions')->create();
                    $transaction->invoice = $mpesa_code;
                    $transaction->username = $PaymentGatewayRecord->username;
                    $transaction->plan_name = $PaymentGatewayRecord->plan_name;
                    $transaction->price = $amount_paid;
                    $transaction->recharged_on = $date;
                    $transaction->recharged_time = $time;
                    $transaction->expiration = $now;
                    $transaction->time = $now;
                    $transaction->method = $PaymentGatewayRecord->payment_method;
                    $transaction->routers = 0;
                    $transaction->Type = 'Balance';
                    try {
                        $transaction->save();
                    } catch (Throwable $e) {
                    }
                }
            }
        } catch (Throwable $eRe) {
        }

        if (class_exists('PamnetHotspotPay')) {
            PamnetHotspotPay::ensureHotspotUser($PaymentGatewayRecord->username);
            PamnetHotspotPay::connectDeviceNow($PaymentGatewayRecord->username);
        }

        exit();
    }
}

