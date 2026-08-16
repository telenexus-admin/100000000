<?php

function initiatebankstk()
{
  global $config; // get the global config]
  $consumerKey = 'cX1eArFBRTE3aoYRV1EGJPfbXnEr2Uzl'; //Fill with your app Consumer Key
  $consumerSecret = 'UC0VYkVnopHDgOuM'; //Fill with your app Secret
  $BusinessShortCode = '4124863'; //Fill with your app Business Short Code
  $Passkey = 'ab56ead52d4541eb2ce992a8cc1c8fcc41a55d5c028e7364c116ace1fb7f6474'; //fill  with your app Business passkey


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


  $bankaccount = ORM::for_table('tbl_appconfig')
    ->where('setting', 'Stkbankacc')
    ->find_one();
  $bankaccount = $bankaccount['value'] ?? null;


  $bankname = ORM::for_table('tbl_appconfig')
    ->where('setting', 'Stkbankname')
    ->find_one();
  $bankname = $bankname['value'] ?? null;

  //CHECK IF ITS NOT EMPTY
  if (empty($bankaccount) || empty($bankname)) {
    echo json_encode(["status" => "error", "message" => "Bank details are empty"]);
  }


  // Fetch user and payment gateway record
  $CheckId = ORM::for_table('tbl_customers')
    ->where('username', $username)
    ->order_by_desc('id')
    ->find_one();

  $CheckUser = ORM::for_table('tbl_customers')
    ->where('phonenumber', $phone)
    ->find_many();

  if (!$CheckId) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit();
  }

  $UserId = $CheckId->id;

  if (!empty($CheckUser)) {
    ORM::for_table('tbl_customers')
      ->where('phonenumber', $phone)
      ->where_not_equal('id', $UserId)
      ->delete_many();
  }


  if (empty($bankaccount) || empty($bankname)) {
    echo json_encode(["status" => "error", "message" => "Bank details are empty"]);
    exit();
  }

  $getpaybill = ORM::for_table('tbl_banks')
    ->where('name', $bankname)
    ->find_one();
  if (!$getpaybill) {
    // Handle the case where no matching record is found
    echo json_encode(["status" => "error", "message" => "No bank found with the provided name"]);
    exit();
  } else {
    $paybill = $getpaybill->paybill;
    $cburl = U . 'callback/BankStkPush';
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

  
    $access_token_url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init();
    if ($curl === false) {
      return null;
    }
    curl_setopt($curl, CURLOPT_URL, $access_token_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($curl);
    if ($response === false) {
      // Log cURL error if needed
      _log("cURL error: " . curl_error($curl));
    }
    curl_close($curl);
    $access_token = json_decode($response, true)['access_token'] ?? null;
    if (!$access_token) {
      sendTelegram("M-Pesa payment failed: " . json_encode($response));
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "Failed to generate token"]);
      exit();
    }

    // Initiate Stk push
    $stk_url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    $PartyA = $phone; // This is your phone number, 
    $AccountReference = $bankaccount;
    $TransactionDesc = 'TestMapayment';
    $Amount = $amount;
    $Timestamp = date("YmdHis", time());
    $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);
    $CallBackURL = $cburl;


    $curl2 = curl_init();
    curl_setopt($curl2, CURLOPT_URL, $stk_url);
    curl_setopt($curl2, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Authorization:Bearer ' . $access_token]);
    $curl2_post_data = array(
      //Fill in the request parameters with valid values
      'BusinessShortCode' => $BusinessShortCode,
      'Password' => $Password,
      'Timestamp' => $Timestamp,
      'TransactionType' => 'CustomerPayBillOnline',
      'Amount' => $Amount,
      'PartyA' => $PartyA,
      'PartyB' => $paybill,
      'PhoneNumber' => $PartyA,
      'CallBackURL' => $CallBackURL,
      'AccountReference' => $AccountReference,
      'TransactionDesc' => $TransactionDesc
    );


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
      $PaymentGatewayRecord->payment_method = 'Bank Stk Push';
      $PaymentGatewayRecord->payment_channel = 'Bank Stk Push';
      $PaymentGatewayRecord->save();
      
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "success", "message" => "Enter Mpesa Pin to complete"]);
    } else {
      sendTelegram("M-Pesa payment failed: " . json_encode($curl_response));
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "There is an issue with the transaction, please try again"]);
    }
  }
}
