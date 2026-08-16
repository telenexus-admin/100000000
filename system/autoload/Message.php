<?php

/**
 * Message handling with SMS, WhatsApp, Email, and Telegram
 * Includes Africa's Talking SMS integration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use PEAR2\Net\RouterOS;

require $root_path . 'system/autoload/mail/Exception.php';
require $root_path . 'system/autoload/mail/PHPMailer.php';
require $root_path . 'system/autoload/mail/SMTP.php';

// Include Africa's Talking SMS class
if (file_exists($root_path . 'system/vendor/AfricasTalkingSMS.php')) {
    require_once $root_path . 'system/vendor/AfricasTalkingSMS.php';
}

class Message
{

    public static function sendTelegram($txt, $chat_id = null, $topik = '')
    {
        global $config;
        run_hook('send_telegram', [$txt, $chat_id, $topik]); #HOOK
        if (!empty($config['telegram_bot'])) {
            if (empty($chat_id)) {
                $chat_id = $config['telegram_target_id'];
            }
            if (!empty($topik)) {
                $topik = "message_thread_id=$topik&";
            }
            return Http::getData('https://api.telegram.org/bot' . $config['telegram_bot'] . '/sendMessage?' . $topik . 'chat_id=' . $chat_id . '&text=' . urlencode($txt));
        }
    }


    public static function sendSMS($phone, $txt)
    {
        global $config;
        if (empty($txt)) {
            return "";
        }

        // PAMNET_SMS_BLESSEDTEXTS_V1
        // Blessed Texts (plugin) — always attempt via hook when configured.
        // Hook reloads credentials from DB so cron/API never falsely report "Configuration missing".
        $hookResult = run_hook('send_sms', [$phone, $txt]); #HOOK
        if ($hookResult === true) {
            self::logMessage('SMS', $phone, $txt, 'Success', 'Blessed Texts / send_sms hook');
            return 'OK';
        }

        // Check SMS gateway type
        $gateway_type = isset($config['sms_gateway_type']) ? strtolower(trim((string) $config['sms_gateway_type'])) : 'url';

        // Blessed Texts selected as primary gateway (plugin settings page)
        if ($gateway_type === 'blessedtexts' || $gateway_type === 'blessed' || $gateway_type === 'blessed_texts') {
            if (function_exists('smsGateway_isConfigured') && smsGateway_isConfigured()) {
                if (function_exists('smsGateway_hook_send_sms')) {
                    $ok = smsGateway_hook_send_sms([$phone, $txt]);
                    if ($ok) {
                        self::logMessage('SMS', $phone, $txt, 'Success', 'Blessed Texts');
                        return 'OK';
                    }
                    self::logMessage('SMS', $phone, $txt, 'Error', 'Blessed Texts send failed');
                    return 'Error: Blessed Texts send failed';
                }
            }
            $missing = [];
            if (empty(trim((string) ($config['blessed_texts_api_key'] ?? '')))) {
                $missing[] = 'API Key';
            }
            if (empty(trim((string) ($config['blessed_texts_sender_id'] ?? '')))) {
                $missing[] = 'Sender ID';
            }
            $msg = empty($missing)
                ? 'Blessed Texts gateway send failed'
                : ('Blessed Texts configuration missing: ' . implode(', ', $missing));
            self::logMessage('SMS', $phone, $txt, 'Error', $msg);
            return 'Error: ' . $msg;
        }
        
        try {
            if ($gateway_type === 'africastalking') {
                return self::sendAfricasTalkingSMS($phone, $txt);
            } elseif ($gateway_type === 'talksasa') {
                return self::sendTalkSasaSMS($phone, $txt);
            } elseif ($gateway_type === 'umscomms') {
                return self::sendUMSCommsSMS($phone, $txt);
            }
            
            // Default to URL-based SMS or Mikrotik
            if (!empty($config['sms_url'])) {
                if (strlen($config['sms_url']) > 4 && substr($config['sms_url'], 0, 4) != "http") {
                    // Handle long messages by splitting
                    if (strlen($txt) > 160) {
                        $txts = str_split($txt, 160);
                        $results = [];
                        foreach ($txts as $txtPart) {
                            $result = self::MikrotikSendSMS($config['sms_url'], $phone, $txtPart);
                            $results[] = $result;
                            self::logMessage('MikroTikSMS Split', $phone, $txtPart, 'Success');
                            // Small delay between parts to avoid overwhelming
                            usleep(100000); // 0.1 second
                        }
                        return implode(' | ', $results);
                    } else {
                        $result = self::MikrotikSendSMS($config['sms_url'], $phone, $txt);
                        self::logMessage('MikroTikSMS', $phone, $txt, 'Success');
                        return $result;
                    }
                } else {
                    // HTTP SMS Gateway
                    $smsurl = str_replace('[number]', urlencode($phone), $config['sms_url']);
                    $smsurl = str_replace('[text]', urlencode($txt), $smsurl);
                    
                    $response = self::sendHTTPRequest($smsurl);
                    self::logMessage('SMS HTTP Response', $phone, $txt, 'Success', $response);
                    return $response;
                }
            }
        } catch (Throwable $e) {
            self::logMessage('SMS Error', $phone, $txt, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }

        // Do not claim "not configured" when Blessed Texts credentials exist
        $btKey = trim((string) ($config['blessed_texts_api_key'] ?? ''));
        $btSender = trim((string) ($config['blessed_texts_sender_id'] ?? ''));
        if ($btKey !== '' && $btSender !== '') {
            if ($hookResult === false) {
                self::logMessage('SMS', $phone, $txt, 'Error', 'Blessed Texts hook failed');
                return 'Error: Blessed Texts send failed — see SMS logs';
            }
            // Hook may have been skipped; try once more
            if (function_exists('smsGateway_hook_send_sms')) {
                $ok = smsGateway_hook_send_sms([$phone, $txt]);
                if ($ok) {
                    return 'OK';
                }
            }
            return 'Error: Blessed Texts send failed — see SMS logs';
        }
        
        return 'No SMS gateway configured';
    }

    /**
     * Optimized HTTP request handler with timeout and retry logic
     */
    private static function sendHTTPRequest($url, $timeout = 10, $retries = 2)
    {
        $attempt = 0;
        $lastError = '';
        
        while ($attempt <= $retries) {
            try {
                // Use cURL for better performance and control
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'PHPNuxBill SMS Gateway');
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    throw new Exception("cURL Error: " . $error);
                }
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    return $response;
                }
                
                throw new Exception("HTTP Error: " . $httpCode . " - " . $response);
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $attempt++;
                
                if ($attempt <= $retries) {
                    // Exponential backoff: wait 1s, then 2s
                    sleep($attempt);
                }
            }
        }
        
        throw new Exception("Failed after {$retries} retries: " . $lastError);
    }

    public static function MikrotikSendSMS($router_name, $to, $message)
    {
        global $_app_stage, $client_m, $config;
        if ($_app_stage == 'demo') {
            return null;
        }
        if (!isset($client_m)) {
            $mikrotik = ORM::for_table('tbl_routers')->where('name', $router_name)->find_one();
            $iport = explode(":", $mikrotik['ip_address']);
            $client_m = new RouterOS\Client($iport[0], $mikrotik['username'], $mikrotik['password'], ($iport[1]) ? $iport[1] : null);
        }
        if (empty($config['mikrotik_sms_command'])) {
            $config['mikrotik_sms_command'] = "/tool sms send";
        }
        $smsRequest = new RouterOS\Request($config['mikrotik_sms_command']);
        $smsRequest
            ->setArgument('phone-number', $to)
            ->setArgument('message', $message);
        $client_m->sendSync($smsRequest);
    }

    public static function sendAfricasTalkingSMS($phone, $message)
    {
        global $config, $_app_stage;
        
        if ($_app_stage == 'demo') {
            return 'Demo mode - SMS not sent';
        }
        
        try {
            // Validate required settings
            if (empty($config['africastalking_username']) || empty($config['africastalking_api_key'])) {
                self::logMessage('AfricasTalking SMS', $phone, $message, 'Error', 'Missing username or API key');
                return 'Error: Missing Africa\'s Talking credentials';
            }
            
            // Initialize Africa's Talking SMS
            $sms = new AfricasTalkingSMS($config['africastalking_username'], $config['africastalking_api_key']);
            
            // Format phone number (ensure it has country code)
            $formattedPhone = self::formatPhoneForAfricasTalking($phone);
            
            // Prepare SMS options
            $options = [
                'to' => $formattedPhone,
                'message' => $message
            ];
            
            // Add sender ID if configured
            if (!empty($config['africastalking_sender_id'])) {
                $options['from'] = $config['africastalking_sender_id'];
            }
            
            // Send SMS
            $result = $sms->send($options);
            
            // Log success
            self::logMessage('AfricasTalking SMS', $phone, $message, 'Success', json_encode($result));
            
            return $result;
            
        } catch (Exception $e) {
            self::logMessage('AfricasTalking SMS', $phone, $message, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        } catch (Throwable $e) {
            self::logMessage('AfricasTalking SMS', $phone, $message, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }
    
    private static function formatPhoneForAfricasTalking($phone)
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^+0-9]/', '', $phone);
        
        // If phone starts with +, return as is
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }
        
        // If phone starts with 254 (Kenya), add +
        if (substr($phone, 0, 3) === '254') {
            return '+' . $phone;
        }
        
        // If phone starts with 07 or 01 (Kenya local), convert to +254
        if (substr($phone, 0, 2) === '07' || substr($phone, 0, 2) === '01') {
            return '+254' . substr($phone, 1);
        }
        
        // If phone starts with 7 or 1 (Kenya without leading 0), convert to +254
        if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1') {
            return '+254' . $phone;
        }
        
        // Default: assume Kenya and add +254
        return '+254' . $phone;
    }

    public static function sendTalkSasaSMS($phone, $message)
    {
        global $config, $_app_stage;
        
        if ($_app_stage == 'demo') {
            return 'Demo mode - SMS not sent';
        }
        
        try {
            // Validate required settings
            if (empty($config['talksasa_api_key'])) {
                self::logMessage('TalkSasa SMS', $phone, $message, 'Error', 'Missing API key');
                return 'Error: Missing TalkSasa API key';
            }
            
            // Format phone number for Kenya (remove + and country code if present)
            $formattedPhone = self::formatPhoneForTalkSasa($phone);
            
            // Get sender ID
            $senderID = !empty($config['talksasa_sender_id']) ? $config['talksasa_sender_id'] : 'TALKSASA';
            
            // Build TalkSasa API URL
            $url = 'https://bulksms.talksasa.com/api/v3/sms/send';
            $url .= '?senderID=' . urlencode($senderID);
            $url .= '&message=' . urlencode($message);
            $url .= '&phone=' . urlencode($formattedPhone);
            $url .= '&api_key=' . urlencode($config['talksasa_api_key']);
            
            // Send HTTP request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($error) {
                self::logMessage('TalkSasa SMS', $phone, $message, 'Error', 'cURL Error: ' . $error);
                return 'Error: ' . $error;
            }
            
            // Handle HTTP errors
            if ($httpCode !== 200) {
                self::logMessage('TalkSasa SMS', $phone, $message, 'Error', 'HTTP Error: ' . $httpCode . ' - ' . $response);
                return 'Error: HTTP ' . $httpCode;
            }
            
            // Log success
            self::logMessage('TalkSasa SMS', $phone, $message, 'Success', $response);
            
            return $response;
            
        } catch (Exception $e) {
            self::logMessage('TalkSasa SMS', $phone, $message, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        } catch (Throwable $e) {
            self::logMessage('TalkSasa SMS', $phone, $message, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }
    
    private static function formatPhoneForTalkSasa($phone)
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If phone starts with 254 (Kenya country code), remove it
        if (substr($phone, 0, 3) === '254') {
            $phone = substr($phone, 3);
        }
        
        // If phone starts with 0, remove it
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }
        
        // TalkSasa expects format like 712345678 (without country code or leading 0)
        return $phone;
    }

    public static function sendUMSCommsSMS($phone, $message)
    {
        global $config, $_app_stage;
        
        if ($_app_stage == 'demo') {
            return 'Demo mode - SMS not sent';
        }
        
        try {
            // Validate required settings
            if (empty($config['umscomms_api_key'])) {
                self::logMessage('UMS Comms SMS', $phone, $message, 'Error', 'Missing API key');
                return 'Error: Missing UMS Comms API key';
            }
            
            // Format phone number for UMS Comms
            $formattedPhone = self::formatPhoneForUMSComms($phone);
            
            // Get configuration values with defaults
            $apiKey = $config['umscomms_api_key'];
            $appId = !empty($config['umscomms_app_id']) ? $config['umscomms_app_id'] : 'UMSC131190';
            $senderId = !empty($config['umscomms_sender_id']) ? $config['umscomms_sender_id'] : 'UMS_TX';
            
            // Build UMS Comms API URL
            $url = 'https://comms.umeskiasoftwares.com/api/v1/sms/send/ums';
            $url .= '?api_key=' . urlencode($apiKey);
            $url .= '&app_id=' . urlencode($appId);
            $url .= '&sender_id=' . urlencode($senderId);
            $url .= '&message=' . urlencode($message);
            $url .= '&phone=' . urlencode($formattedPhone);
            
            // Send HTTP request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PHPNuxBill UMS Comms Gateway');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($error) {
                self::logMessage('UMS Comms SMS', $phone, $message, 'Error', 'cURL Error: ' . $error);
                return 'Error: ' . $error;
            }
            
            // Handle HTTP errors
            if ($httpCode !== 200) {
                self::logMessage('UMS Comms SMS', $phone, $message, 'Error', 'HTTP Error: ' . $httpCode . ' - ' . $response);
                return 'Error: HTTP ' . $httpCode;
            }
            
            // Log success
            self::logMessage('UMS Comms SMS', $phone, $message, 'Success', $response);
            
            return $response;
            
        } catch (Exception $e) {
            self::logMessage('UMS Comms SMS', $phone, $message, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        } catch (Throwable $e) {
            self::logMessage('UMS Comms SMS', $phone, $message, 'Error', $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }
    
    private static function formatPhoneForUMSComms($phone)
    {
        // Remove any non-numeric characters except +
        $phone = preg_replace('/[^+0-9]/', '', $phone);
        
        // If phone starts with +, return as is
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }
        
        // If phone starts with 254 (Kenya), add +
        if (substr($phone, 0, 3) === '254') {
            return '+' . $phone;
        }
        
        // If phone starts with 07 or 01 (Kenya local), convert to +254
        if (substr($phone, 0, 2) === '07' || substr($phone, 0, 2) === '01') {
            return '+254' . substr($phone, 1);
        }
        
        // If phone starts with 7 or 1 (Kenya without leading 0), convert to +254
        if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1') {
            return '+254' . $phone;
        }
        
        // Default: assume Kenya and add +254
        return '+254' . $phone;
    }

    public static function sendWhatsapp($phone, $txt)
    {
        global $config;
        if (empty($txt)) {
            return "kosong";
        }

        run_hook('send_whatsapp', [$phone, $txt]); // HOOK

        if (!empty($config['wa_url'])) {
            $waurl = str_replace('[number]', urlencode(Lang::phoneFormat($phone)), $config['wa_url']);
            $waurl = str_replace('[text]', urlencode($txt), $waurl);

            try {
                $response = Http::getData($waurl);
                self::logMessage('WhatsApp HTTP Response', $phone, $txt, 'Success', $response);
                return $response;
            } catch (Throwable $e) {
                self::logMessage('WhatsApp HTTP Request', $phone, $txt, 'Error', $e->getMessage());
            }
        }
    }

    public static function sendEmail($to, $subject, $body, $attachmentPath = null)
    {
        global $config, $PAGES_PATH, $debug_mail;
        if (empty($body)) {
            return "";
        }
        if (empty($to)) {
            return "";
        }
        run_hook('send_email', [$to, $subject, $body]); #HOOK
        if (empty($config['smtp_host'])) {
            $attr = "";
            if (!empty($config['mail_from'])) {
                $attr .= "From: " . $config['mail_from'] . "\r\n";
            }
            if (!empty($config['mail_reply_to'])) {
                $attr .= "Reply-To: " . $config['mail_reply_to'] . "\r\n";
            }
            mail($to, $subject, $body, $attr);
            self::logMessage('Email', $to, $body, 'Success');
        } else {
            $mail = new PHPMailer();
            $mail->isSMTP();
            if (isset($debug_mail) && $debug_mail == 'Dev') {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->SMTPSecure = $config['smtp_ssltls'];
            $mail->Port = $config['smtp_port'];
            if (!empty($config['mail_from'])) {
                $mail->setFrom($config['mail_from']);
            }
            if (!empty($config['mail_reply_to'])) {
                $mail->addReplyTo($config['mail_reply_to']);
            }

            $mail->addAddress($to);
            $mail->Subject = $subject;
            // Attachments
            if (!empty($attachmentPath)) {
                $mail->addAttachment($attachmentPath);
            }

            if (!file_exists($PAGES_PATH . DIRECTORY_SEPARATOR . 'Email.html')) {
                if (!copy($PAGES_PATH . '_template' . DIRECTORY_SEPARATOR . 'Email.html', $PAGES_PATH . DIRECTORY_SEPARATOR . 'Email.html')) {
                    file_put_contents($PAGES_PATH . DIRECTORY_SEPARATOR . 'Email.html', Http::getData('https://raw.githubusercontent.com/allxsys/templates/master/pages/Email.html'));
                }
            }

            if (file_exists($PAGES_PATH . DIRECTORY_SEPARATOR . 'Email.html')) {
                $html = file_get_contents($PAGES_PATH . DIRECTORY_SEPARATOR . 'Email.html');
                $html = str_replace('[[Subject]]', $subject, $html);
                $html = str_replace('[[Company_Address]]', nl2br($config['address']), $html);
                $html = str_replace('[[Company_Name]]', nl2br($config['CompanyName']), $html);
                $html = str_replace('[[Body]]', nl2br($body), $html);
                $mail->isHTML(true);
                $mail->Body = $html;
                $mail->Body = $html;
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }
            if (!$mail->send()) {
                $errorMessage = Lang::T("Email not sent, Mailer Error: ") . $mail->ErrorInfo;
                self::logMessage('Email', $to, $body, 'Error', $errorMessage);
            } else {
                self::logMessage('Email', $to, $body, 'Success');
            }

            //<p style="font-family: Helvetica, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">
        }
    }

    public static function sendPackageNotification($customer, $package, $price, $message, $via)
    {
        global $ds, $config;
        try {
            if (empty($message)) {
                return "";
            }
            if (!is_array($customer) && !is_object($customer)) {
                return "error: invalid customer";
            }
            $via = strtolower(trim((string) $via));
            if ($via === '' || $via === 'none') {
                return "none: skipped";
            }
            $username = (string) ($customer['username'] ?? '');
            $pay = self::getRenewalPayInfo($customer);
            $msg = str_replace('[[name]]', $customer['fullname'] ?? '', $message);
            $msg = str_replace('[[username]]', $username, $msg);
            $msg = str_replace('[[user_name]]', $username, $msg);
            $msg = str_replace('[[account]]', $pay['account'], $msg);
            $msg = str_replace('[[paybill]]', $pay['paybill'] !== '' ? $pay['paybill'] : '4052137', $msg);
            $msg = str_replace('[[shortcode]]', $pay['paybill'] !== '' ? $pay['paybill'] : '4052137', $msg);
            $msg = str_replace('[[company_name]]', $config['CompanyName'] ?? 'PAMNET SOLUTIONS', $msg);
            $msg = str_replace('[[company]]', $config['CompanyName'] ?? 'PAMNET SOLUTIONS', $msg);
            $msg = str_replace('[[plan]]', $package, $msg);
            $msg = str_replace('[[package]]', $package, $msg);
            $msg = str_replace('[[price]]', Lang::moneyFormat($price), $msg);
            // Calculate bills and additional costs
            list($bills, $add_cost) = User::getBills($customer['id'] ?? 0);

            // Initialize note and total variables
            $note = "";
            $total = $price;

            // Add bills to the note if there are any additional costs
            if ($add_cost != 0) {
                foreach ($bills as $k => $v) {
                    $note .= $k . " : " . Lang::moneyFormat($v) . "\n";
                }
                $total += $add_cost;
            }

            // Calculate tax
            $tax = 0;
            $tax_enable = isset($config['enable_tax']) ? $config['enable_tax'] : 'no';
            if ($tax_enable === 'yes') {
                $tax_rate_setting = isset($config['tax_rate']) ? $config['tax_rate'] : null;
                $custom_tax_rate = isset($config['custom_tax_rate']) ? (float) $config['custom_tax_rate'] : null;

                $tax_rate = ($tax_rate_setting === 'custom') ? $custom_tax_rate : $tax_rate_setting;
                $tax = Package::tax($price, $tax_rate);

                if ($tax != 0) {
                    $note .= "Tax : " . Lang::moneyFormat($tax) . "\n";
                    $total += $tax;
                }
            }

            // Add total to the note
            $note .= "Total : " . Lang::moneyFormat($total) . "\n";

            // Replace placeholders in the message
            $msg = str_replace('[[bills]]', $note, $msg);

            if ($ds) {
                $msg = str_replace('[[expired_date]]', Lang::dateAndTimeFormat($ds['expiration'], $ds['time']), $msg);
            } else {
                $msg = str_replace('[[expired_date]]', "", $msg);
            }

            if (strpos($msg, '[[payment_link]]') !== false) {
                // token only valid for 1 day, for security reason
                $token = User::generateToken($customer['id'], 1);
                if (!empty($token['token'])) {
                    $tur = ORM::for_table('tbl_user_recharges')
                        ->where('customer_id', $customer['id'])
                        ->where('namebp', $package)
                        ->find_one();
                    if ($tur) {
                        $url = '?_route=home&recharge=' . $tur['id'] . '&uid=' . urlencode($token['token']);
                        $msg = str_replace('[[payment_link]]', $url, $msg);
                    }
                } else {
                    $msg = str_replace('[[payment_link]]', '', $msg);
                }
            }

            $phone = trim((string) ($customer['phonenumber'] ?? ''));
            if ($phone !== '' && strlen($phone) > 5 && in_array($via, ['sms', 'wa', 'email'], true)) {
                if ($via == 'sms') {
                    Message::sendSMS($phone, $msg);
                } else if ($via == 'email') {
                    self::sendEmail($customer['email'] ?? '', '[' . ($config['CompanyName'] ?? '') . '] ' . Lang::T("Internet Plan Reminder"), $msg);
                } else if ($via == 'wa') {
                    Message::sendWhatsapp($phone, $msg);
                }
            }
            return "$via: $msg";
        } catch (Throwable $e) {
            $uname = is_array($customer) ? ($customer['username'] ?? '') : '';
            try {
                self::logMessage('Package', $uname, (string) $message, 'Error', $e->getMessage());
            } catch (Throwable $ignore) {
            }
            return "error: " . $e->getMessage();
        }
    }

    /**
     * Paybill + Account for renewal / expiry SMS.
     * Account is always the customer's real username (PPPoE/Hotspot login).
     *
     * @return array{paybill:string,account:string}
     */
    public static function getRenewalPayInfo($customer = null)
    {
        global $config;
        $username = '';
        if (is_array($customer)) {
            $username = trim((string) ($customer['username'] ?? ''));
        } elseif (is_object($customer) && isset($customer['username'])) {
            $username = trim((string) $customer['username']);
        }

        $paybill = '';
        // Classic M-Pesa Paybill / Business Shortcode
        if (!empty($config['mpesa_business_code'])) {
            $paybill = preg_replace('/\D+/', '', (string) $config['mpesa_business_code']) ?: '';
        }
        // Paybill Tills Bank Mpesa plugin settings
        $type = $config['mpesa_bank_paybill_till_type'] ?? '';
        if ($type === 'paybill' && !empty($config['paybilltillsbankmpesa_paybill'])) {
            $paybill = preg_replace('/\D+/', '', (string) $config['paybilltillsbankmpesa_paybill']) ?: $paybill;
        } elseif ($type === 'till' && !empty($config['paybilltillsbankmpesa_till'])) {
            $paybill = preg_replace('/\D+/', '', (string) $config['paybilltillsbankmpesa_till']) ?: $paybill;
        } elseif ($type === 'bank' && !empty($config['paybilltillsbankmpesa__bank_paybill_number'])) {
            $paybill = preg_replace('/\D+/', '', (string) $config['paybilltillsbankmpesa__bank_paybill_number']) ?: $paybill;
        }
        // Fallbacks from DB if $config cache missed
        if ($paybill === '') {
            try {
                foreach (['mpesa_business_code', 'paybilltillsbankmpesa_paybill', 'paybilltillsbankmpesa__bank_paybill_number'] as $key) {
                    $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
                    if ($row && trim((string) $row['value']) !== '') {
                        $paybill = preg_replace('/\D+/', '', (string) $row['value']) ?: '';
                        if ($paybill !== '') {
                            break;
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        return [
            'paybill' => $paybill,
            'account' => $username,
        ];
    }

    public static function sendBalanceNotification($cust, $target, $balance, $balance_now, $message, $via)
    {
        global $config;
        $msg = str_replace('[[name]]', $target['fullname'] . ' (' . $target['username'] . ')', $message);
        $msg = str_replace('[[current_balance]]', Lang::moneyFormat($balance_now), $msg);
        $msg = str_replace('[[balance]]', Lang::moneyFormat($balance), $msg);
        $phone = $cust['phonenumber'];
        if (
            !empty($phone) && strlen($phone) > 5
            && !empty($message) && in_array($via, ['sms', 'wa', 'email'])
        ) {
            if ($via == 'sms') {
                Message::sendSMS($phone, $msg);
            } else if ($via == 'email') {
                self::sendEmail($cust['email'], '[' . $config['CompanyName'] . '] ' . Lang::T("Balance Notification"), $msg);
            } else if ($via == 'wa') {
                Message::sendWhatsapp($phone, $msg);
            }
            self::addToInbox($cust['id'], Lang::T('Balance Notification'), $msg);
        }
        return "$via: $msg";
    }

    public static function sendInvoice($cust, $trx)
    {
        // PAMNET_PPPOE_ONLY_INVOICE — Hotspot payment invoices never SMS; PPPoE only
        global $config, $db_pass;
        $planType = (string) ($trx['type'] ?? '');
        $via = self::resolveNotificationVia($planType, 'payment');
        if ($via === '' || $via === 'none') {
            return;
        }
        $textInvoice = self::resolveNotifTemplate($planType, 'invoice_paid');
        if (!is_string($textInvoice) || trim($textInvoice) === '') {
            $textInvoice = Lang::getNotifText('invoice_paid');
        }
        $textInvoice = str_replace('[[company_name]]', $config['CompanyName'], $textInvoice);
        $textInvoice = str_replace('[[address]]', $config['address'], $textInvoice);
        $textInvoice = str_replace('[[phone]]', $config['phone'], $textInvoice);
        $textInvoice = str_replace('[[invoice]]', $trx['invoice'], $textInvoice);
        $textInvoice = str_replace('[[date]]', Lang::dateAndTimeFormat($trx['recharged_on'], $trx['recharged_time']), $textInvoice);
        $textInvoice = str_replace('[[trx_date]]', Lang::dateAndTimeFormat($trx['recharged_on'], $trx['recharged_time']), $textInvoice);
        if (!empty($trx['note'])) {
            $textInvoice = str_replace('[[note]]', $trx['note'], $textInvoice);
        }
        $gc = explode("-", $trx['method']);
        $textInvoice = str_replace('[[payment_gateway]]', trim($gc[0]), $textInvoice);
        $textInvoice = str_replace('[[payment_channel]]', trim($gc[1]), $textInvoice);
        $textInvoice = str_replace('[[type]]', $trx['type'], $textInvoice);
        $textInvoice = str_replace('[[plan_name]]', $trx['plan_name'], $textInvoice);
        $textInvoice = str_replace('[[plan_price]]', Lang::moneyFormat($trx['price']), $textInvoice);
        $textInvoice = str_replace('[[name]]', $cust['fullname'], $textInvoice);
        $textInvoice = str_replace('[[note]]', $cust['note'], $textInvoice);
        $textInvoice = str_replace('[[user_name]]', $trx['username'], $textInvoice);
        $textInvoice = str_replace('[[user_password]]', $cust['password'], $textInvoice);
        $textInvoice = str_replace('[[username]]', $trx['username'], $textInvoice);
        $textInvoice = str_replace('[[password]]', $cust['password'], $textInvoice);
        $textInvoice = str_replace('[[expired_date]]', Lang::dateAndTimeFormat($trx['expiration'], $trx['time']), $textInvoice);
        $textInvoice = str_replace('[[footer]]', $config['note'], $textInvoice);

        $inv_url = "?_route=voucher/invoice/$trx[id]/" . md5($trx['id'] . $db_pass);
        $textInvoice = str_replace('[[invoice_link]]', $inv_url, $textInvoice);

        // Calculate bills and additional costs
        list($bills, $add_cost) = User::getBills($cust['id']);

        // Initialize note and total variables
        $note = "";
        $total = $trx['price'];

        // Add bills to the note if there are any additional costs
        if ($add_cost != 0) {
            foreach ($bills as $k => $v) {
                $note .= $k . " : " . Lang::moneyFormat($v) . "\n";
            }
            $total += $add_cost;
        }

        // Calculate tax
        $tax = 0;
        $tax_enable = isset($config['enable_tax']) ? $config['enable_tax'] : 'no';
        if ($tax_enable === 'yes') {
            $tax_rate_setting = isset($config['tax_rate']) ? $config['tax_rate'] : null;
            $custom_tax_rate = isset($config['custom_tax_rate']) ? (float) $config['custom_tax_rate'] : null;

            $tax_rate = ($tax_rate_setting === 'custom') ? $custom_tax_rate : $tax_rate_setting;
            $tax = Package::tax($trx['price'], $tax_rate);

            if ($tax != 0) {
                $note .= "Tax : " . Lang::moneyFormat($tax) . "\n";
                $total += $tax;
            }
        }

        // Add total to the note
        $note .= "Total : " . Lang::moneyFormat($total) . "\n";

        // Replace placeholders in the message
        $textInvoice = str_replace('[[bills]]', $note, $textInvoice);

        if ($via == 'sms') {
            Message::sendSMS($cust['phonenumber'], $textInvoice);
        } else if ($via == 'email') {
            self::sendEmail($cust['email'], '[' . $config['CompanyName'] . '] ' . Lang::T("Invoice") . ' #' . $trx['invoice'], $textInvoice);
        } else if ($via == 'wa') {
            Message::sendWhatsapp($cust['phonenumber'], $textInvoice);
        }
    }


    public static function addToInbox($to_customer_id, $subject, $body, $from = 'System')
    {
        $user = User::find($to_customer_id);
        try {
            $v = ORM::for_table('tbl_customers_inbox')->create();
            $v->from = $from;
            $v->customer_id = $to_customer_id;
            $v->subject = $subject;
            $v->date_created = date('Y-m-d H:i:s');
            $v->body = nl2br($body);
            $v->save();
            self::logMessage("Inbox", $user->username, $body, "Success");
        } catch (Throwable $e) {
            $errorMessage = Lang::T("Error adding message to inbox: " . $e->getMessage());
            self::logMessage('Inbox', $user->username, $body, 'Error', $errorMessage);
        }
    }

    public static function getMessageType($type, $message)
    {
        $message = (string) $message;
        if ($message === '' || strpos($message, '<divider>') === false) {
            return $message;
        }
        $msgs = explode('<divider>', $message);
        $hotspot = trim((string) ($msgs[0] ?? ''));
        $pppoe = trim((string) ($msgs[1] ?? ''));
        if (strtoupper((string) $type) === 'PPPOE') {
            return $pppoe !== '' ? $pppoe : $hotspot;
        }
        return $hotspot !== '' ? $hotspot : $pppoe;
    }

    /**
     * Resolve SMS/WA/email channel for Hotspot vs PPPoE (Orange Theme + legacy keys).
     */
    public static function resolveNotificationVia($type, $kind = 'reminder')
    {
        // PAMNET_PPPOE_ONLY_VIA — Hotspot never gets package SMS; PPPoE only
        global $config;
        $kind = strtolower((string) $kind);
        if (!in_array($kind, ['reminder', 'expired', 'payment'], true)) {
            $kind = 'reminder';
        }
        $isPppoe = strtoupper((string) $type) === 'PPPOE';
        if (!$isPppoe) {
            return 'none';
        }
        foreach (["user_notification_{$kind}_pppoe", "user_notification_{$kind}"] as $key) {
            $via = strtolower(trim((string) ($config[$key] ?? '')));
            if ($via !== '' && $via !== 'none') {
                return $via;
            }
        }
        return 'sms';
    }

    /**
     * Load notification template for plan type (supports *_pppoe keys + <divider>).
     */
    public static function resolveNotifTemplate($type, $baseKey)
    {
        $isPppoe = strtoupper((string) $type) === 'PPPOE';
        if ($isPppoe) {
            $pppoeKey = $baseKey . '_pppoe';
            $pppoeText = Lang::getNotifText($pppoeKey);
            if (is_string($pppoeText) && trim($pppoeText) !== '') {
                return $pppoeText;
            }
        } else {
            $hotKey = $baseKey . '_hotspot';
            $hotText = Lang::getNotifText($hotKey);
            if (is_string($hotText) && trim($hotText) !== '') {
                return $hotText;
            }
        }
        return self::getMessageType($type, Lang::getNotifText($baseKey));
    }

    public static function logMessage($messageType, $recipient, $messageContent, $status, $errorMessage = null)
    {
        $log = ORM::for_table('tbl_message_logs')->create();
        $log->message_type = $messageType;
        $log->recipient = $recipient;
        $log->message_content = $messageContent;
        $log->status = $status;
        $log->error_message = $errorMessage;
        $log->save();
    }
}
