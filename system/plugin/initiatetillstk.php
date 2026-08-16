<?php

function initiatetillstk()
{
    global $config;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit();
    }

    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);


    $username = isset($input['username']) ? $input['username'] : null;
    $phone = isset($input['phone']) ? $input['phone'] : null;


    if (empty($username) || empty($phone)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Please fill all fields"]);
        exit();
    }
    // Format phone number
    $phone = (substr($phone, 0, 1) == '+') ? str_replace('+', '', $phone) : $phone;
    $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^0/', '254', $phone) : $phone;
    $phone = (substr($phone, 0, 1) == '7') ? preg_replace('/^7/', '2547', $phone) : $phone;
    $phone = (substr($phone, 0, 1) == '1') ? preg_replace('/^1/', '2541', $phone) : $phone;
    $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^01/', '2541', $phone) : $phone;
    $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^07/', '2547', $phone) : $phone;


    // Get the M-Pesa Environment
    $mpesa_till_env = ORM::for_table('tbl_appconfig')
        ->where('setting', 'mpesa_till_env')
        ->find_one();
    $mpesa_till_env = $mpesa_till_env['value'] ?? null;

    // Get the M-Pesa consumer key
    $mpesa_till_consumer_key = ORM::for_table('tbl_appconfig')
        ->where('setting', 'mpesa_till_consumer_key')
        ->find_one();
    $mpesa_till_consumer_key = $mpesa_till_consumer_key['value'] ?? null;

    // Get the M-Pesa consumer secret
    $mpesa_till_consumer_secret = ORM::for_table('tbl_appconfig')
        ->where('setting', 'mpesa_till_consumer_secret')
        ->find_one();
    $mpesa_till_consumer_secret = $mpesa_till_consumer_secret['value'] ?? null;

    // Get the M-Pesa business code
    $mpesa_till_business_code = ORM::for_table('tbl_appconfig')
        ->where('setting', 'mpesa_till_business_code')
        ->find_one();
    $mpesa_till_business_code = $mpesa_till_business_code['value'] ?? null;

    // Get the M-Pesa pass key
    $mpesa_till_pass_key = ORM::for_table('tbl_appconfig')
        ->where('setting', 'mpesa_till_pass_key')
        ->find_one();
    $mpesa_till_pass_key = $mpesa_till_pass_key['value'] ?? null;

    // Get the M-Pesa party B
    $mpesa_till_partyb = ORM::for_table('tbl_appconfig')
        ->where('setting', 'mpesa_till_partyb')
        ->find_one();
    $mpesa_till_partyb = $mpesa_till_partyb['value'] ?? null;


    if (!$mpesa_till_env || !$mpesa_till_consumer_key || !$mpesa_till_consumer_secret || !$mpesa_till_business_code || !$mpesa_till_pass_key || !$mpesa_till_partyb) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "M-Pesa configuration not set"]);
        exit();
    }

    $Type_of_Transaction = 'CustomerBuyGoodsOnline';


    $CallBackURL = U . 'callback/MpesatillStk';
    $Time_Stamp = date("Ymdhis");
    $password = base64_encode($mpesa_till_business_code . $mpesa_till_pass_key . $Time_Stamp);

    // Fetch user record
    $CheckId = ORM::for_table('tbl_customers')
        ->where('username', $username)
        ->order_by_desc('id')
        ->find_one();

    if (!$CheckId) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "User " . $username . " not found"]);
        exit();
    }

    $UserId = $CheckId->id;


    ORM::for_table('tbl_customers')
        ->where('phonenumber', $phone)
        ->where_not_equal('id', $UserId)
        ->delete_many();



    // Fetch payment gateway record
    $PaymentGatewayRecord = ORM::for_table('tbl_payment_gateway')
        ->where('username', $username)
        ->where('status', 1)
        ->order_by_desc('id')
        ->find_one();

    if (!$PaymentGatewayRecord) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Unable to process payment, please reload the page"]);
        exit();
    }

    // Update user details
    $CheckId->phonenumber = $phone;
    $CheckId->username = $username;
    $CheckId->save();


    $amount = $PaymentGatewayRecord->price;

    if ($mpesa_till_env == "live") {
        $lipaOnlineUrl = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $tokenUrl = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    } elseif ($mpesa_till_env == "sandbox") {
        $lipaOnlineUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        $tokenUrl = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid application status"]);
        exit();
    }

    $curl = curl_init();
    if ($curl === false) {
        return null;
    }

    curl_setopt($curl, CURLOPT_URL, $tokenUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($mpesa_till_consumer_key . ':' . $mpesa_till_consumer_secret)]);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($curl);

    if ($response === false) {
        // Log cURL error if needed
        _log("cURL error: " . curl_error($curl));
    }

    curl_close($curl);

    $token = json_decode($response, true)['access_token'] ?? null;

    if (!$token) {
        sendTelegram("M-Pesa payment failed: " . json_encode($response));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Failed to generate token"]);
        exit();
    }

    // Process the STK Push
    $curl2 = curl_init();
    curl_setopt($curl2, CURLOPT_URL, $lipaOnlineUrl);
    curl_setopt($curl2, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization:Bearer ' . $token]);

    $curl2_post_data = [
        'BusinessShortCode' => $mpesa_till_business_code,
        'Password' => $password,
        'Timestamp' => $Time_Stamp,
        'TransactionType' => $Type_of_Transaction,
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $mpesa_till_partyb,
        'PhoneNumber' => $phone,
        'CallBackURL' => $CallBackURL,
        'AccountReference' => 'Hotspot-' . $username,
        'TransactionDesc' => 'Payment for Hotspot ' . $username,
    ];

    $data2_string = json_encode($curl2_post_data);

    curl_setopt($curl2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl2, CURLOPT_POST, true);
    curl_setopt($curl2, CURLOPT_POSTFIELDS, $data2_string);
    curl_setopt($curl2, CURLOPT_HEADER, false);
    curl_setopt($curl2, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl2, CURLOPT_SSL_VERIFYHOST, 0);
    $curl_response = curl_exec($curl2);

    if (!$curl_response) {
        sendTelegram("M-Pesa payment failed: " . json_encode($curl_response));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Failed to process STK Push"]);
        exit();
    }

    $mpesaResponse = json_decode($curl_response);
    $responseCode = $mpesaResponse->ResponseCode ?? null;
    $MerchantRequestID = $mpesaResponse->MerchantRequestID ?? null;
    $CheckoutRequestID = $mpesaResponse->CheckoutRequestID ?? null;
    $resultDesc = $mpesaResponse->CustomerMessage ?? 'No message';

    if ($responseCode == "0") {

        $PaymentGatewayRecord->pg_paid_response = $resultDesc;
        $PaymentGatewayRecord->pg_request = $CheckoutRequestID;
        $PaymentGatewayRecord->username = $username;
        $PaymentGatewayRecord->payment_method = 'Mpesa Till Stk Push';
        $PaymentGatewayRecord->payment_channel = 'Mpesa Till Stk Push';
        $PaymentGatewayRecord->save();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "success", "message" => "Enter Mpesa Pin to complete"]);
    } else {
        sendTelegram("M-Pesa payment failed: " . json_encode($curl_response));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "There is an issue with the transaction, please try again"]);
    }
}
