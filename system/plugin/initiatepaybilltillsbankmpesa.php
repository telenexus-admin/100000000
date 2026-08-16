<?php

function initiatepaybilltillsbankmpesa()
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


  $mpesa_bank_paybill_till_type = ORM::for_table('tbl_appconfig')
    ->where('setting', 'mpesa_bank_paybill_till_type')
    ->find_one();
  $mpesa_bank_paybill_till_type = $mpesa_bank_paybill_till_type['value'] ?? null;
  if ($mpesa_bank_paybill_till_type == "paybill") {
    $TransactionType = 'CustomerPayBillOnline';
    // Hotspot-{code} so C2B confirmation can activate without waiting for STK callback
    $account_reference = 'Hotspot-' . $username;
    $mpesa_paybill_number = ORM::for_table('tbl_appconfig')
      ->where('setting', 'paybilltillsbankmpesa_paybill')
      ->find_one();
    $main_mpesa_paybill_number = $mpesa_paybill_number['value'] ?? null;
    if (empty($mpesa_paybill_number)) {
      echo json_encode(["status" => "error", "message" => "Paybill number is empty"]);
      exit();
    }
  } elseif ($mpesa_bank_paybill_till_type == "till") {
    $TransactionType = 'CustomerBuyGoodsOnline';
    $account_reference = 'Hotspot-' . $username;
    $mpesa_till_number = ORM::for_table('tbl_appconfig')
      ->where('setting', 'paybilltillsbankmpesa_till')
      ->find_one();
    $main_mpesa_paybill_number = $mpesa_till_number['value'] ?? null;
    if (empty($mpesa_till_number)) {
      echo json_encode(["status" => "error", "message" => "Till number is empty"]);
      exit();
    }
  } elseif ($mpesa_bank_paybill_till_type == "bank") {
    $TransactionType = 'CustomerPayBillOnline';
    $stk_bank_paybill_number = ORM::for_table('tbl_appconfig')
      ->where('setting', 'paybilltillsbankmpesa__bank_paybill_number')
      ->find_one();
    $main_mpesa_paybill_number = $stk_bank_paybill_number['value'] ?? null;
    $bank_account_number = ORM::for_table('tbl_appconfig')
      ->where('setting', 'paybilltillsbankmpesa__mpesa_bank_account_number')
      ->find_one();
    $bank_account_number = $bank_account_number['value'] ?? null;
    $account_reference = $bank_account_number;

    if (empty($bank_account_number) || empty($stk_bank_paybill_number)) {
      echo json_encode(["status" => "error", "message" => "Bank details are empty"]);
      exit();
    }
  } elseif ($mpesa_bank_paybill_till_type == "phone") {
    // Use CustomerBuyGoodsOnline with phone as till number (this works for direct phone transfers)
    $TransactionType = 'CustomerBuyGoodsOnline';
    $destination_phone = ORM::for_table('tbl_appconfig')
      ->where('setting', 'paybilltillsbankmpesa_phone')
      ->find_one();
    $destination_phone = $destination_phone['value'] ?? null;
    
    if (empty($destination_phone)) {
      echo json_encode(["status" => "error", "message" => "Destination phone number is empty"]);
      exit();
    }
    
    // Format destination phone number
    $destination_phone = (substr($destination_phone, 0, 1) == '+') ? str_replace('+', '', $destination_phone) : $destination_phone;
    $destination_phone = (substr($destination_phone, 0, 1) == '0') ? preg_replace('/^0/', '254', $destination_phone) : $destination_phone;
    $destination_phone = (substr($destination_phone, 0, 1) == '7') ? preg_replace('/^7/', '2547', $destination_phone) : $destination_phone;
    $destination_phone = (substr($destination_phone, 0, 1) == '1') ? preg_replace('/^1/', '2541', $destination_phone) : $destination_phone;
    
    // Treat phone number as a till - money goes directly to the phone number
    $main_mpesa_paybill_number = $destination_phone; // Phone acts as till number
    $account_reference = $username . '-to-' . substr($destination_phone, -4); // Reference
  } else {
    echo json_encode(["status" => "error", "message" => "Invalid payment type"]);
    exit();
  }

  // Fetch user and payment gateway record
  $CheckId = ORM::for_table('tbl_customers')
    ->where('username', $username)
    ->order_by_desc('id')
    ->find_one();


  if (!$CheckId) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit();
  }



  $cburl = U . 'callback/paybilltillsbankmpesa';
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
    // Log the actual error for debugging
    $error_details = json_decode($response, true);
    $error_message = $error_details['error_description'] ?? 'Unknown error';
    
    sendTelegram("M-Pesa OAuth failed: " . $error_message . " | Response: " . json_encode($response));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "Authentication failed: " . $error_message]);
    exit();
  }

  // Initiate Stk push
  $stk_url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
  $PartyA = $phone; // This is your phone number, 
  $TransactionDesc = 'Payment for ' . $username;
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
    'TransactionType' => $TransactionType,
    'Amount' => $Amount,
    'PartyA' => $PartyA,
    'PartyB' => $main_mpesa_paybill_number,
    'PhoneNumber' => $PartyA,
    'CallBackURL' => $CallBackURL,
    'AccountReference' => $account_reference,
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
  $CheckoutRequestID = $mpesaResponse->CheckoutRequestID ?? null;
  $resultDesc = $mpesaResponse->CustomerMessage ?? 'No message';
  if ($responseCode == "0") {

    $PaymentGatewayRecord->pg_paid_response = $resultDesc;
    $PaymentGatewayRecord->pg_request = $CheckoutRequestID;
    $PaymentGatewayRecord->username = $username;
    
    // Set payment method based on payment type
    if ($mpesa_bank_paybill_till_type == "phone") {
      $PaymentGatewayRecord->payment_method = 'Phone Number Stk Push';
      $PaymentGatewayRecord->payment_channel = 'Phone Number Stk Push';
    } else {
      $PaymentGatewayRecord->payment_method = 'Bank Paybill Tills Stk Push';
      $PaymentGatewayRecord->payment_channel = 'Bank Paybill Tills Stk Push';
    }
    
    $PaymentGatewayRecord->save();


    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "success", "message" => "Enter Mpesa Pin to complete"]);
  } else {
    sendTelegram("M-Pesa payment failed: " . json_encode($curl_response));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "There is an issue with the transaction, please try again"]);
  }
}

