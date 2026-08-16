<?php
function paybilltillsbankmpesa_validate_config()
{
  global $config;
  if (empty($config['mpesa_bank_paybill_till_type'])) {
    sendTelegram("Paybill Tills Bank Mpesa Payment Gateway is not configured. Please configure it in the admin panel.");
    r2(U . 'order/balance', 'w', Lang::T("Admin has not yet setup the payment gateway, please tell admin"));
  }
}

function paybilltillsbankmpesa_show_config()
{
  global $ui, $config;
  setuppaybilltillsbankmpesa_table();
  $banks = ORM::for_table('tbl_banks_paybills_tills')->find_many();
  $ui->assign('banks', $banks);
  $ui->assign('_title', 'Paybill Tills Bank Mpesa Payment Gateway - ' . $config['CompanyName']);
  $ui->display('paybilltillsbankmpesa.tpl');
}

function paybilltillsbankmpesa_save_config()
{
  global $admin, $_L;
  $paymenttype = _post('mpesa_bank_paybill_till_type');
  //CHECK IF THE PAYMENT TYPE IS BANK paybilltillsbankmpesa_ PUSH
  if ($paymenttype == "") {
    r2(U . 'paymentgateway/paybilltillsbankmpesa', 'e', 'Please select a payment type');
  }
  


  $settings = [
    'mpesa_bank_paybill_till_type' => _post('mpesa_bank_paybill_till_type'),
    'paybilltillsbankmpesa_paybill' => _post('paybilltillsbankmpesa_paybill'),
    'paybilltillsbankmpesa_till' => _post('paybilltillsbankmpesa_till'),
    'paybilltillsbankmpesa__bank_paybill_number' => _post('paybilltillsbankmpesa__bank_paybill_number'),
    'paybilltillsbankmpesa__mpesa_bank_account_number' => _post('paybilltillsbankmpesa__mpesa_bank_account_number'),
    'paybilltillsbankmpesa_phone' => _post('paybilltillsbankmpesa_phone'),

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
  _log('[' . $admin['username'] . ']: Mpesa Bank Paybill Tills for paybilltillsbankmpesa_ Push details ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);

  r2(U . 'paymentgateway/paybilltillsbankmpesa', 's', $_L['Settings_Saved_Successfully'] . ' for Mpesa Bank Paybill Tills.');
}


function paybilltillsbankmpesa_create_transaction($trx, $user)
{
  $url = (U . "plugin/initiatepaybilltillsbankmpesa");

  $d = ORM::for_table('tbl_payment_gateway')
    ->where('username', $user['username'])
    ->where('status', 1)
    ->find_one();
  $d->gateway_trx_id = '';
  // Set payment method based on payment type
  global $config;
  $payment_type = $config['mpesa_bank_paybill_till_type'] ?? 'paybill';
  if ($payment_type == 'phone') {
    $d->payment_method = 'Mpesa Phone Number';
  } else {
    $d->payment_method = 'Mpesa Bank Paybill Tills';
  }
  $d->pg_url_payment = $url;
  $d->pg_request = '';
  $d->expired_date = date('Y-m-d H:i:s', strtotime("+5 minutes"));
  $d->save();

  r2(U . "order/view/" . $d['id'], 's', Lang::T("Create Transaction Success, Please click pay now to process payment"));

  die();
}


function setuppaybilltillsbankmpesa_table($tableName = 'tbl_banks_paybills_tills')
{
  $db = ORM::get_db();

  // Check if the table exists
  $check = $db->query("SHOW TABLES LIKE '{$tableName}'")->fetch();

  if ($check === false) {
    // Create table
    $createTableSQL = "
            CREATE TABLE `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL UNIQUE,
                `paybill` VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
    $db->exec($createTableSQL);

    // Predefined banks
    $banks = [
      ['name' => 'Equity', 'paybill' => '247247'],
      ['name' => 'KCB', 'paybill' => '522522'],
      ['name' => 'Co-operative', 'paybill' => '400200'],
      ['name' => 'Absa', 'paybill' => '303030'],
      ['name' => 'Standard', 'paybill' => '329329'],
      ['name' => 'NCBA', 'paybill' => '880100'],
      ['name' => 'Stanbic', 'paybill' => '600100'],
      ['name' => 'Barclays', 'paybill' => '303030'],
      ['name' => 'CBA', 'paybill' => '880100'],
      ['name' => 'HF', 'paybill' => '100400'],
      ['name' => 'DTB', 'paybill' => '516600'],
      ['name' => 'NIC', 'paybill' => '488488'],
      ['name' => 'Family', 'paybill' => '222111'],
      ['name' => 'Credit', 'paybill' => '972700'],
      ['name' => 'Guardian', 'paybill' => '344500'],
      ['name' => 'Prime', 'paybill' => '982800'],
      ['name' => 'Jamii Bora', 'paybill' => '529901'],
      ['name' => 'I&M', 'paybill' => '542542'],
      ['name' => 'Bank of Africa', 'paybill' => '972900'],
      ['name' => 'Chase', 'paybill' => '552800'],
      ['name' => 'National', 'paybill' => '547700'],
      ['name' => 'Consolidated', 'paybill' => '508400']
    ];

    // Insert data
    $insertSQL = "INSERT INTO `{$tableName}` (`name`, `paybill`) VALUES (:name, :paybill)";
    $stmt = $db->prepare($insertSQL);

    foreach ($banks as $bankData) {
      $stmt->execute([':name' => $bankData['name'], ':paybill' => $bankData['paybill']]);
    }
  }
}


function paybilltillsbankmpesa_payment_notification()
{
  $captureLogs = file_get_contents("php://input");
  $analizzare = json_decode($captureLogs);
  file_put_contents('pages/bank-till-paybill-hotspot-mpesa-webhook.html', $captureLogs, FILE_APPEND);
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
    ->order_by_desc('id')
    ->find_one();
  if (!$PaymentGatewayRecord) {
    Message::sendTelegram("Mpesa Webook Notification:\n\n\n Transaction Record Not Found for this transaction [ " . $checkout_req_id . "]");
    exit();
  }

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
    $uname = $PaymentGatewayRecord->username;
    $userid = ORM::for_table('tbl_customers')
      ->where('username', $uname)
      ->order_by_desc('id')
      ->find_one();

    if (!$userid) {
      $phoneNumber = preg_replace('/\D+/', '', (string) $sender_phone);
      if ($phoneNumber === '') {
        $phoneNumber = '254' . substr(md5($uname), 0, 9);
      }
      $routerId = !empty($PaymentGatewayRecord->routers_id) ? (string) $PaymentGatewayRecord->routers_id : '';
      if ($routerId === '' && !empty($PaymentGatewayRecord->routers)) {
        $router = ORM::for_table('tbl_routers')->where('name', $PaymentGatewayRecord->routers)->find_one();
        if ($router) {
          $routerId = (string) $router->id;
        }
      }
      $userid = ORM::for_table('tbl_customers')->create();
      $userid->username = $uname;
      $userid->password = '1234';
      $userid->fullname = $phoneNumber;
      $userid->router_id = $routerId;
      $userid->phonenumber = $phoneNumber;
      $userid->pppoe_password = '1234';
      $userid->address = 'Hotspot Address';
      $userid->email = $uname . '@gmail.com';
      $userid->service_type = 'Hotspot';
      $userid->status = 'Active';
      $userid->save();
    }
    $UserId = $userid->id;

    $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
    $PaymentGatewayRecord->paid_date = $now;

    // Always mark paid first so portal leaves "Waiting for M-Pesa confirmation…"
    $PaymentGatewayRecord->status = 2;
    $PaymentGatewayRecord->pg_paid_response = $resultDesc !== '' ? $resultDesc : 'STK paid';
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
      if (!Package::completeStkPayment($PaymentGatewayRecord, $UserId, $mpesa_code)) {
        $PaymentGatewayRecord->pg_paid_response = 'PAID_PENDING_ACTIVATION';
        $PaymentGatewayRecord->save();
        Message::sendTelegram("STK Push PAID but activation failed for {$uname} [{$mpesa_code}] — will retry on verify");
      }
    } catch (Throwable $ePay) {
      Message::sendTelegram("STK Push activation error for {$uname}: " . $ePay->getMessage());
    }

    if (class_exists('PamnetHotspotPay')) {
      PamnetHotspotPay::connectDeviceNow($uname);
    }

    exit();
  }
}



