<?php


function BankStkPush_validate_config()
{
    global $config;
    if (empty($config['Stkbankacc']) || empty($config['Stkbankname'])) {
        sendTelegram("Bank Stk payment gateway not configured");
        r2(U . 'order/balance', 'w', Lang::T("Admin has not yet setup the payment gateway, please tell admin"));
    }
}

function BankStkPush_show_config()
{ 
    global $ui, $config;
    // Call the function to create the table if it does not exist
    setupBanksTable();
    //get all banks
    $banks = ORM::for_table('tbl_banks')->find_many();
    $ui->assign('banks', $banks);
    $ui->assign('_title', 'Bank Stk Push - ' . $config['CompanyName']);
    $ui->display('bankstkpush.tpl');
}

function BankStkPush_save_config()
{
    global $admin, $_L;
    $bankacc = _post('account');
    $bankname = _post('bankname');
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'Stkbankacc')->find_one();
    if ($d) {
        $d->value = $bankacc;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'Stkbankacc';
        $d->value = $bankacc;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'Stkbankname')->find_one();
    if ($d) {
        $d->value = $bankname;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'Stkbankname';
        $d->value = $bankname;
        $d->save();
    }

    _log('[' . $admin['username'] . ']: Stk Bank details ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);

    r2(U . 'paymentgateway/BankStkPush', 's', $_L['Settings_Saved_Successfully']);
}


function BankStkPush_create_transaction($trx, $user)
{
    $url = (U . "plugin/initiatebankstk");

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = '';
    $d->payment_method = 'Bank Stk Push';
    $d->pg_url_payment = $url;
    $d->pg_request = '';
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+5 minutes"));
    $d->save();

    r2(U . "order/view/" . $d['id'], 's', Lang::T("Create Transaction Success, Please click pay now to process payment"));

    die();
}


function setupBanksTable($tableName = 'tbl_banks')
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


function BankStkPush_payment_notification()
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

        // Idempotent: C2B often activates before STK callback — mark paid + ensure Wi‑Fi
        $existingTransaction = ORM::for_table('tbl_transactions')
            ->where('invoice', $mpesa_code)
            ->find_one();

        if ($existingTransaction) {
            $PaymentGatewayRecord->status = 2;
            $PaymentGatewayRecord->paid_date = $now;
            $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
            $PaymentGatewayRecord->pg_paid_response = 'Already activated (idempotent)';
            $PaymentGatewayRecord->save();
            if (class_exists('PamnetHotspotPay')) {
                PamnetHotspotPay::ensureHotspotUser($PaymentGatewayRecord->username);
            }
            exit();
        }

        // Another PG row already completed this M-Pesa code — still mark this row paid
        $existingGatewayTransaction = ORM::for_table('tbl_payment_gateway')
            ->where('gateway_trx_id', $mpesa_code)
            ->where('status', 2)
            ->where_not_equal('id', $PaymentGatewayRecord->id)
            ->find_one();

        if ($existingGatewayTransaction) {
            $PaymentGatewayRecord->status = 2;
            $PaymentGatewayRecord->paid_date = $now;
            $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
            $PaymentGatewayRecord->pg_paid_response = 'Already paid on another record (idempotent)';
            $PaymentGatewayRecord->save();
            if (class_exists('PamnetHotspotPay')) {
                PamnetHotspotPay::ensureHotspotUser($PaymentGatewayRecord->username);
            }
            exit();
        }

        if (!Package::rechargeUser($UserId, $PaymentGatewayRecord->routers, $PaymentGatewayRecord->plan_id, $PaymentGatewayRecord->gateway, 'STK-Push', '', $mpesa_code)) {
            $PaymentGatewayRecord->status = 2;
            $PaymentGatewayRecord->paid_date = $now;
            $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
            $PaymentGatewayRecord->save();

            // Save transaction data to tbl_transactions
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
            $transaction->save();
        } else {
            // Update tbl_recharges if needed
            $PaymentGatewayRecord->status = 2;
            $PaymentGatewayRecord->paid_date = $now;
            $PaymentGatewayRecord->gateway_trx_id = $mpesa_code;
            $PaymentGatewayRecord->save();
        }

        $user = ORM::for_table('tbl_customers')->where('username', $PaymentGatewayRecord->username)->find_one();
        if ($user) {
            $currentBalance = $user->balance;
            $user->balance = $currentBalance + $amount_paid;
            $user->save();
        }
        exit();
    }
}

