<?php

/**
 * AllxSys Internet Management System
 *
 *
 * Payment Gateway M-Pesa https://developer.safaricom.co.ke/
 * 
 *
 **/


function mpesa_validate_config()
{
  global $config;
  if (empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret'])) {
    sendTelegram("M-Pesa payment gateway not configured");
    r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup M-Pesa payment gateway, please tell admin"));
  }
}

function mpesa_show_config()
{
  global $ui, $config;
  $ui->assign('env', json_decode(file_get_contents('system/paymentgateway/mpesa_env.json'), true));
  $ui->assign('_title', 'M-Pesa - Payment Gateway - ' . $config['CompanyName']);
  $ui->display('mpesa.tpl');
}

function mpesa_save_config()
{
  global $admin, $_L;
  $mpesa_consumer_key = _post('mpesa_consumer_key');
  $mpesa_consumer_secret = _post('mpesa_consumer_secret');
  $mpesa_shortcode_type = _post('mpesa_shortcode_type');
  $mpesa_business_code = _post('mpesa_business_code');
  $mpesa_pass_key = _post('mpesa_pass_key');
  $mpesa_env = _post('mpesa_env');
  $mpesa_channel_ofline_online = _post('mpesa_channel_ofline_online');
  $mpesa_api_version = _post('mpesa_api_version');


  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_shortcode_type')->find_one();
  if ($d) {
    $d->value = $mpesa_shortcode_type;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_shortcode_type';
    $d->value = $mpesa_shortcode_type;
    $d->save();
  }

  if ($mpesa_shortcode_type == 'BuyGoods') {
    $mpesa_buygoods_till_number = _post('mpesa_buygoods_till_number');
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_buygoods_till_number')->find_one();
    if ($d) {
      $d->value = $mpesa_buygoods_till_number;
      $d->save();
    } else {
      $d = ORM::for_table('tbl_appconfig')->create();
      $d->setting = 'mpesa_buygoods_till_number';
      $d->value = $mpesa_buygoods_till_number;
      $d->save();
    }
  }

  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_api_version')->find_one();
  if ($d) {
    $d->value = $mpesa_api_version;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_api_version';
    $d->value = $mpesa_api_version;
    $d->save();
  }

  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_channel_ofline_online')->find_one();
  if ($d) {
    $d->value = $mpesa_channel_ofline_online;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_channel_ofline_online';
    $d->value = $mpesa_channel_ofline_online;
    $d->save();
  }



  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_key')->find_one();
  if ($d) {
    $d->value = $mpesa_consumer_key;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_consumer_key';
    $d->value = $mpesa_consumer_key;
    $d->save();
  }


  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_secret')->find_one();
  if ($d) {
    $d->value = $mpesa_consumer_secret;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_consumer_secret';
    $d->value = $mpesa_consumer_secret;
    $d->save();
  }

  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_business_code')->find_one();
  if ($d) {
    $d->value = $mpesa_business_code;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_business_code';
    $d->value = $mpesa_business_code;
    $d->save();
  }


  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_pass_key')->find_one();
  if ($d) {
    $d->value = $mpesa_pass_key;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_pass_key';
    $d->value = $mpesa_pass_key;
    $d->save();
  }


  $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_env')->find_one();
  if ($d) {
    $d->value = $mpesa_env;
    $d->save();
  } else {
    $d = ORM::for_table('tbl_appconfig')->create();
    $d->setting = 'mpesa_env';
    $d->value = $mpesa_env;
    $d->save();
  }


  _log('[' . $admin['username'] . ']: M-Pesa ' . $_L['Settings_Saved_Successfully'] . json_encode($_POST['mpesa_channel']), 'Admin', $admin['id']);

  r2(U . 'paymentgateway/mpesa', 's', $_L['Settings_Saved_Successfully']);
}


function mpesa_create_transaction($trx, $user)
{
  $url = (U . "plugin/initiatempesa");
  $d = ORM::for_table('tbl_payment_gateway')->where('username', $user['username'])->where('status', 1)->find_one();
  $d->gateway_trx_id = '';
  $d->payment_method = 'Mpesa Stk Push';
  $d->pg_url_payment = $url;
  $d->pg_request = '';
  $d->expired_date = date('Y-m-d H:i:s', strtotime("+5 minutes"));
  $d->save();
  r2(U . "order/view/" . $d['id'], 's', Lang::T("Create Transaction Success, Please click pay now to process payment"));
  die();
}


function mpesa_payment_notification()
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
  // 1037 = DS timeout / phone unreachable — stop "Processing…" and let customer retry
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

    // Mark paid FIRST so the portal can leave "Waiting for M-Pesa…" within ~1s.
    $PaymentGatewayRecord->status = 2;
    $PaymentGatewayRecord->paid_date = $now;
    $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
    $PaymentGatewayRecord->pg_paid_response = 'STK paid';
    $PaymentGatewayRecord->save();

    // ACK Safaricom immediately — do NOT hold this worker on Package/MikroTik.
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

    // Background: activate package + force-login phone (no cron).
    $alreadyLive = (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'usernameHasActivePlan') && PamnetHotspotPay::usernameHasActivePlan($PaymentGatewayRecord->username))
      || (function_exists('PamnetUsernameHasActivePlan') && PamnetUsernameHasActivePlan($PaymentGatewayRecord->username));
    if (!$alreadyLive) {
      try {
        $recharged = Package::rechargeUser($UserId, $PaymentGatewayRecord->routers, $PaymentGatewayRecord->plan_id, $PaymentGatewayRecord->gateway, 'STK-Push', '', $mpesa_code);
        if (!$recharged) {
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
        if (function_exists('_log')) {
          _log('mpesa STK recharge ' . $PaymentGatewayRecord->username . ': ' . $eRe->getMessage(), 'System', 1);
        }
      }
    }

    if (class_exists('PamnetHotspotPay')) {
      PamnetHotspotPay::connectDeviceNow($PaymentGatewayRecord->username);
    }

    exit();
  }
}


function mpesa_get_status($trx, $user)
{
  global $config, $routes;

  if ($trx->status == 2) {
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been completed."));
    die();
  } elseif ($trx->status == 1) {
    $environment = $config['mpesa_env'];
    $consumer_key = $config['mpesa_consumer_key'];
    $consumer_secret = $config['mpesa_consumer_secret'];
    $Business_Code = $config['mpesa_business_code'];
    $Passkey = $config['mpesa_pass_key'];
    //Timestamp that we save earlier in pg_url_payment database
    $Time_Stamp = $trx['pg_url_payment'];
    $password = base64_encode($Business_Code . $Passkey . $Time_Stamp);
    if ($environment == "live") {
      $OnlinePayment = 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';
      $Token_URL = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    } elseif ($environment == "sandbox") {
      $OnlinePayment = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
      $Token_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    } else {
      return json_encode(["Message" => "invalid application status"]);
    };
    $curl_Tranfer = curl_init();
    curl_setopt($curl_Tranfer, CURLOPT_URL, $Token_URL);
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    curl_setopt($curl_Tranfer, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl_Tranfer, CURLOPT_HEADER, false);
    curl_setopt($curl_Tranfer, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_Tranfer, CURLOPT_SSL_VERIFYPEER, false);
    $curl_Tranfer_response = curl_exec($curl_Tranfer);
    $token = json_decode($curl_Tranfer_response)->access_token;
    // die(json_encode($curl_Tranfer2_post_data,JSON_PRETTY_PRINT));
    $curl_Tranfer2 = curl_init();
    curl_setopt($curl_Tranfer2, CURLOPT_URL, $OnlinePayment);
    curl_setopt($curl_Tranfer2, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $token));
    //lest verify the transaction by sending data to mpesa transaction query portal from nuxbil
    $curl_Tranfer2_post_data = [
      'BusinessShortCode' => $Business_Code,
      'Password' => $password,
      'Timestamp' => $Time_Stamp,
      'CheckoutRequestID' => $trx['gateway_trx_id']
    ];
    //die(json_encode($curl_Tranfer2_post_data,JSON_PRETTY_PRINT));
    $data2_string = json_encode($curl_Tranfer2_post_data);
    curl_setopt($curl_Tranfer2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_Tranfer2, CURLOPT_POST, true);
    curl_setopt($curl_Tranfer2, CURLOPT_POSTFIELDS, $data2_string);
    curl_setopt($curl_Tranfer2, CURLOPT_HEADER, false);
    curl_setopt($curl_Tranfer2, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl_Tranfer2, CURLOPT_SSL_VERIFYHOST, 0);
    $curl_Tranfer2_response = json_decode(curl_exec($curl_Tranfer2));
    $callbackJSONData = file_get_contents('php://input');
    $callbackData = json_decode($callbackJSONData);
    $responseCode = $callbackData->ResponseCode;
    $responseDescription = $callbackData->ResponseDescription;
    $merchantRequestID = $callbackData->MerchantRequestID;
    $checkoutRequestID = $callbackData->CheckoutRequestID;
    $resultCode = $callbackData->ResultCode;
    $resultDesc = $callbackData->ResultDesc;
    //if responce is Failed
    if ($responseDescription === "The service request has failed" || $resultDesc === "Request canceled by the user" ||  $responseCode === 1) {
      r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
      //if responce is Successfull, activate the plan or balance
    } elseif (($responseDescription === "The service request has been accepted successfully." || $resultDesc == "The service request is processed successfully"  || $responseCode === 0) && $trx['status'] != 2) {
      if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'],  'M-Pesa')) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
      }
      _log('[' . $checkoutRequestID . ']: M-Pesa ' . "Payment Successfull" . json_encode($callbackData));
      $trx->pg_paid_response = json_encode($callbackData);
      $trx->payment_method = 'M-Pesa';
      $trx->payment_channel = 'M-Pesa StkPush';
      $trx->paid_date = date('Y-m-d H:i:s');
      $trx->status = 2;
      $trx->save();
      r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } else if ($trx['status'] == 2) {
      r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid.."));
    }
  }
}

