<?php

function initiatekopokopo()
{
  global $config; // get the global config]
  $accses_type = "";
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accses_type = "api";
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $username = isset($input['username']) ? $input['username'] : null;
    $phone = isset($input['phone']) ? $input['phone'] : null;
  } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accses_type = "web";
    $user = User::_info();
    if ($user) {
      // Check if the user info is available
      $username = $user->username ?? null;
      $phone = $user->phonenumber ?? null;
    } else {
      r2(getUrl('home'), 'e', 'Please login to continue');
      die();
    }
  } else {
    echo json_encode(['error' => 'Unsupported request method " ' . $_SERVER['REQUEST_METHOD'] . '"']);
    exit;
  }


  if (empty($username) || empty($phone)) {
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "Please fill all fields"]);
      exit();
    } else {
      r2(getUrl('home'), 'e', 'Please fill all fields');
      die();
    }
  }

  // Format phone number
  $phone = (substr($phone, 0, 1) == '+') ? str_replace('+', '', $phone) : $phone;
  $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^0/', '254', $phone) : $phone;
  $phone = (substr($phone, 0, 1) == '7') ? preg_replace('/^7/', '2547', $phone) : $phone;
  $phone = (substr($phone, 0, 1) == '1') ? preg_replace('/^1/', '2541', $phone) : $phone;
  $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^01/', '2541', $phone) : $phone;
  $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^07/', '2547', $phone) : $phone;

  // Get the M-Pesa Environment
  $kopokopo_client_id = ORM::for_table('tbl_appconfig')
    ->where('setting', 'kopokopo_client_id')
    ->find_one();
  $kopokopo_client_id = $kopokopo_client_id['value'] ?? null;

  $kopokopo_client_secret = ORM::for_table('tbl_appconfig')
    ->where('setting', 'kopokopo_client_secret')
    ->find_one();
  $kopokopo_client_secret = $kopokopo_client_secret['value'] ?? null;

  $kopokopo_stk_till_number_id = ORM::for_table('tbl_appconfig')
    ->where('setting', 'kopokopo_stk_till_number_id')
    ->find_one();
  $kopokopo_stk_till_number_id = $kopokopo_stk_till_number_id['value'] ?? null;

  if (!$kopokopo_client_id || !$kopokopo_client_secret || !$kopokopo_stk_till_number_id) {
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "Kopokopo credentials not set correctly"]);
      exit();
    } else {
      r2(getUrl('home'), 'e', 'Kopokopo credentials not set correctly');
      die();
    }
  }

  // Fetch user and payment gateway record
  $CheckId = ORM::for_table('tbl_customers')
    ->where('username', $username)
    ->order_by_desc('id')
    ->find_one();

  // Fetch user record
  $CheckUser = ORM::for_table('tbl_customers')
    ->where('phonenumber', $phone)
    ->find_many();

  if (!$CheckId) {
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "User not found"]);
      exit();
    } else {
      r2(getUrl('home'), 'e', 'User not found');
      die();
    }
  }

  $UserId = $CheckId->id;

  if (!empty($CheckUser)) {
    ORM::for_table('tbl_customers')
      ->where('phonenumber', $phone)
      ->where_not_equal('id', $UserId)
      ->delete_many();
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
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "Payment gateway record not found"]);
      exit();
    } else {
      r2(getUrl('home'), 'e', 'Payment gateway record not found');
      die();
    }
  }

  // Update user details
  $CheckId->phonenumber = $phone;
  $CheckId->username = $username;
  $CheckId->save();

  $amount = $PaymentGatewayRecord->price;
  $callback_url =  U . 'callback/kopokopo';
  $reference = $username . "-" . rand(100000, 999999);
  $get_kopo_kopo_access_token = get_kopo_kopo_access_token($kopokopo_client_id, $kopokopo_client_secret);
  if (!$get_kopo_kopo_access_token) {
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "Unable to get access token"]);
      exit();
    } else {
      r2(getUrl('home'), 'e', 'Unable to get access token');
      die();
    }
  }

  $payload = [
    "payment_channel" => "M-PESA",
    "till_number" => $kopokopo_stk_till_number_id,
    "subscriber" => [
      "first_name" => "",
      "last_name" => "",
      "phone_number" => "+'$phone'",
      "email" => ""
    ],
    "amount" => [
      "currency" => "KES",
      "value" => $amount
    ],
    "metadata" => [
      "customer_id" => "",
      "reference" => $reference,
      "notes" => "Payment for invoice " . $reference
    ],
    "_links" => [
      "callback_url" => $callback_url,
    ]
  ];
  $url = "https://api.kopokopo.com/api/v1/incoming_payments";
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      "Content-Type: application/json",
      "Accept: application/json",
      "User-Agent: <product>/<product-version> <comment>",  
      "Authorization: Bearer $get_kopo_kopo_access_token",
    ],
  ]);
  echo $response = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  $data = json_decode($response);
  if ($httpCode == 201) {
    $PaymentGatewayRecord->pg_paid_response = "MPESA Payment Initiated";
    $PaymentGatewayRecord->pg_request = $reference;
    $PaymentGatewayRecord->username = $username;
    $PaymentGatewayRecord->payment_method = 'Kopokopo';
    $PaymentGatewayRecord->payment_channel = 'Kopokopo';
    $PaymentGatewayRecord->save();
    // Send success response
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "success", "message" => "Enter Mpesa Pin to complete"]);
      exit();
    } else {
      r2(getUrl('home'), 's', 'Enter Mpesa Pin to complete');
      die();
    }
  } else {
    if (isset($data->error_message)) {
      sendTelegram("Kopokopo Error: " . $data->error_message);
    } else {
      sendTelegram("Kopokopo Error: " . $response);
    }
    if ($accses_type == "api") {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["status" => "error", "message" => "Payment initiation failed"]);
      exit();
    } else {
      r2(getUrl('home'), 'e', 'Payment initiation failed');
      die();
    }
  }
}

function get_kopo_kopo_access_token($clientId, $clientSecret)
{
  $url = "https://api.kopokopo.com/oauth/token";
  $postData = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'grant_type' => 'client_credentials'
  ]);
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/x-www-form-urlencoded',
      'User-Agent: <product>/<product-version> <comment>'
    ],
  ]);
  $response = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  $access_token = json_decode($response)->access_token;
  return $access_token;
}
