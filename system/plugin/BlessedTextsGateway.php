<?php

/**
 * Blessed Texts SMS Gateway Plugin for PHPNuxBill / pamnet
 *
 * Config is stored in tbl_appconfig:
 *   - blessed_texts_api_key
 *   - blessed_texts_sender_id
 *
 * Always reloads from DB when sending so cron / long-running processes
 * never report "Configuration missing" while valid settings exist.
 */

register_menu("SMS Gateway", true, "smsGateway", 'AFTER_SETTINGS', 'glyphicon glyphicon-envelope', '', '', ['Admin', 'SuperAdmin']);
register_hook('send_sms', 'smsGateway_hook_send_sms');

createSmsLogsTable();

function createSmsLogsTable()
{
    try {
        $db = ORM::get_db();
        $tableExists = $db->query("SHOW TABLES LIKE 'tbl_sms_logs'")->fetch();
        if (!$tableExists) {
            $db->exec("CREATE TABLE IF NOT EXISTS `tbl_sms_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `phone` varchar(32) NOT NULL,
                `message` text NOT NULL,
                `message_id` varchar(128) DEFAULT NULL,
                `status` varchar(50) NOT NULL,
                `status_message` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_phone` (`phone`),
                KEY `idx_status` (`status`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        }
        return true;
    } catch (Exception $e) {
        if (function_exists('_log')) {
            _log('Error creating tbl_sms_logs table: ' . $e->getMessage(), 'System', 1);
        }
        return false;
    }
}

// Core helpers live in 00_pamnet_sms_compat.php (loaded first). Keep fallbacks if that file is absent.
if (!function_exists('smsGateway_loadConfig')) {
    /**
     * @return array{api_key:string,sender_id:string,missing:string[]}
     */
    function smsGateway_loadConfig($forceDb = true)
    {
        global $config, $_c;
        $apiKey = '';
        $senderId = '';
        if (!$forceDb && is_array($config)) {
            $apiKey = trim((string) ($config['blessed_texts_api_key'] ?? ''));
            $senderId = trim((string) ($config['blessed_texts_sender_id'] ?? ''));
        }
        if ($forceDb || $apiKey === '' || $senderId === '') {
            try {
                $keyRow = ORM::for_table('tbl_appconfig')->where('setting', 'blessed_texts_api_key')->find_one();
                $senderRow = ORM::for_table('tbl_appconfig')->where('setting', 'blessed_texts_sender_id')->find_one();
                if ($keyRow) {
                    $apiKey = trim((string) $keyRow->value);
                }
                if ($senderRow) {
                    $senderId = trim((string) $senderRow->value);
                }
            } catch (Throwable $e) {
            }
        }
        if (!is_array($config)) {
            $config = [];
        }
        $config['blessed_texts_api_key'] = $apiKey;
        $config['blessed_texts_sender_id'] = $senderId;
        if (isset($_c) && is_array($_c)) {
            $_c['blessed_texts_api_key'] = $apiKey;
            $_c['blessed_texts_sender_id'] = $senderId;
        }
        $missing = [];
        if ($apiKey === '') {
            $missing[] = 'API Key (blessed_texts_api_key)';
        }
        if ($senderId === '') {
            $missing[] = 'Sender ID (blessed_texts_sender_id)';
        }
        return ['api_key' => $apiKey, 'sender_id' => $senderId, 'missing' => $missing];
    }
}

if (!function_exists('smsGateway_saveSetting')) {
    function smsGateway_saveSetting($setting, $value)
    {
        $value = trim((string) $value);
        $d = ORM::for_table('tbl_appconfig')->where('setting', $setting)->find_one();
        if ($d) {
            $d->value = $value;
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $setting;
            $d->value = $value;
            $d->save();
        }
    }
}

if (!function_exists('smsGateway_isConfigured')) {
    function smsGateway_isConfigured()
    {
        $cfg = smsGateway_loadConfig(true);
        return empty($cfg['missing']);
    }
}

if (!function_exists('smsGateway_phoneFormat')) {
    function smsGateway_phoneFormat($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($phone === '') {
            return '';
        }
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        return $phone;
    }
}

function smsGateway()
{
    global $ui, $admin;
    _admin();

    $cfg = smsGateway_loadConfig(true);
    if (!empty($cfg['missing'])) {
        r2(U . 'plugin/smsGateway_config', 'e', 'Please configure SMS gateway first. Missing: ' . implode(', ', $cfg['missing']));
    }

    $logs = ORM::for_table('tbl_sms_logs')
        ->order_by_desc('created_at')
        ->limit(10)
        ->find_many();

    $ui->assign('sms_logs', $logs);
    $ui->assign('sms_cfg', $cfg);
    $ui->assign('_title', 'SMS Gateway');
    $ui->assign('_system_menu', 'plugin/smsGateway');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $ui->assign('menu', 'home');
    $ui->display('smsGateway.tpl');
}

function smsGateway_config()
{
    global $ui, $config, $_c;
    _admin();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $apiKey = trim((string) _post('blessed_texts_api_key'));
        $senderId = trim((string) _post('blessed_texts_sender_id'));

        $missing = [];
        if ($apiKey === '') {
            $missing[] = 'API Key';
        }
        if ($senderId === '') {
            $missing[] = 'Sender ID';
        }
        if (!empty($missing)) {
            r2(U . 'plugin/smsGateway_config', 'e', 'Cannot save — missing required field(s): ' . implode(', ', $missing));
        }

        smsGateway_saveSetting('blessed_texts_api_key', $apiKey);
        smsGateway_saveSetting('blessed_texts_sender_id', $senderId);

        // Keep in-memory config in sync for this request / redirects
        if (!is_array($config)) {
            $config = [];
        }
        $config['blessed_texts_api_key'] = $apiKey;
        $config['blessed_texts_sender_id'] = $senderId;
        if (isset($_c) && is_array($_c)) {
            $_c['blessed_texts_api_key'] = $apiKey;
            $_c['blessed_texts_sender_id'] = $senderId;
        }

        // When Blessed Texts is the active provider and SMS URL is empty,
        // mark gateway type so Settings does not look "unconfigured".
        $smsUrl = trim((string) ($config['sms_url'] ?? ''));
        $gwType = strtolower(trim((string) ($config['sms_gateway_type'] ?? 'url')));
        if ($smsUrl === '' && ($gwType === '' || $gwType === 'url')) {
            smsGateway_saveSetting('sms_gateway_type', 'blessedtexts');
            $config['sms_gateway_type'] = 'blessedtexts';
            if (isset($_c) && is_array($_c)) {
                $_c['sms_gateway_type'] = 'blessedtexts';
            }
        }

        r2(U . 'plugin/smsGateway', 's', 'Configuration saved successfully');
    }

    smsGateway_loadConfig(true);
    $ui->assign('_title', 'SMS Gateway Configuration');
    $ui->assign('_system_menu', 'plugin/smsGateway');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $ui->assign('menu', 'config');
    $ui->display('smsGateway.tpl');
}

/**
 * Hook called by Message::sendSMS via run_hook('send_sms', [$phone, $txt])
 * @return bool
 */
function smsGateway_hook_send_sms($data = [])
{
    if (!is_array($data) || count($data) < 2) {
        if (function_exists('_log')) {
            _log('SMS Gateway: Invalid data format', 'SMS', 0);
        }
        return false;
    }

    list($phone, $message) = $data;
    $phone = trim((string) $phone);
    $message = trim((string) $message);

    if ($phone === '' || $message === '') {
        if (function_exists('_log')) {
            _log('SMS Gateway: Phone or message is empty', 'SMS', 0);
        }
        smsGateway_log($phone, $message, null, 'failed', 'Phone or message is empty');
        return false;
    }

    $cfg = smsGateway_loadConfig(true);
    if (!empty($cfg['missing'])) {
        $detail = 'Configuration missing: ' . implode(', ', $cfg['missing']);
        if (function_exists('_log')) {
            _log('SMS Gateway: ' . $detail, 'SMS', 0);
        }
        smsGateway_log($phone, $message, null, 'failed', $detail);
        return false;
    }

    return smsGateway_sendRaw($phone, $message, $cfg['api_key'], $cfg['sender_id']);
}

/**
 * @return bool
 */
function smsGateway_sendRaw($phone, $message, $apiKey, $senderId)
{
    $phone = smsGateway_phoneFormat($phone);
    $url = 'https://sms.blessedtexts.com/api/sms/v1/sendsms';
    $postData = [
        'api_key' => $apiKey,
        'sender_id' => $senderId,
        'message' => $message,
        'phone' => $phone,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        if (function_exists('_log')) {
            _log('SMS Gateway CURL Error: ' . $error, 'SMS', 0);
        }
        smsGateway_log($phone, $message, null, 'failed', 'CURL Error: ' . $error);
        return false;
    }

    $result = json_decode((string) $response, true);

    // Blessed Texts may return a list or a single object
    $row = null;
    if (is_array($result)) {
        if (isset($result[0]) && is_array($result[0])) {
            $row = $result[0];
        } elseif (isset($result['status_code'])) {
            $row = $result;
        }
    }

    $statusCode = is_array($row) ? (string) ($row['status_code'] ?? '') : '';
    if ($httpCode === 200 && ($statusCode === '1000' || $statusCode === '0')) {
        $messageId = is_array($row) ? (string) ($row['message_id'] ?? ($row['msgid'] ?? '')) : '';
        if (function_exists('_log')) {
            _log('SMS sent successfully to ' . $phone . '. Message ID: ' . $messageId, 'SMS', 1);
        }
        smsGateway_log($phone, $message, $messageId !== '' ? $messageId : null, 'sent', 'Message sent successfully');
        return true;
    }

    $err = 'Unknown error occurred';
    if (is_array($row) && !empty($row['status_desc'])) {
        $err = (string) $row['status_desc'];
    } elseif (is_array($result) && !empty($result['status_desc'])) {
        $err = (string) $result['status_desc'];
    } elseif ($response) {
        $err = 'HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 200);
    } else {
        $err = 'HTTP ' . $httpCode;
    }

    if (function_exists('_log')) {
        _log('SMS Gateway Error: ' . $err, 'SMS', 0);
    }
    smsGateway_log($phone, $message, null, 'failed', $err);
    return false;
}

function smsGateway_log($phone, $message, $message_id, $status, $status_message)
{
    try {
        $log = ORM::for_table('tbl_sms_logs')->create();
        $log->phone = (string) $phone;
        $log->message = (string) $message;
        $log->message_id = $message_id;
        $log->status = (string) $status;
        $log->status_message = (string) $status_message;
        $log->save();
    } catch (Throwable $e) {
        // never break SMS flow because of logging
    }
}

function smsGateway_check_balance()
{
    header('Content-Type: application/json');
    _admin();

    $cfg = smsGateway_loadConfig(true);
    if ($cfg['api_key'] === '') {
        echo json_encode([
            'success' => false,
            'message' => 'API Key (blessed_texts_api_key) is not configured',
        ]);
        exit;
    }

    $url = 'https://sms.blessedtexts.com/api/sms/v1/credit-balance';
    $postData = ['api_key' => $cfg['api_key']];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo json_encode(['success' => false, 'message' => 'CURL Error: ' . $error]);
        exit;
    }

    $result = json_decode((string) $response, true);
    if ($httpCode === 200 && is_array($result) && (string) ($result['status_code'] ?? '') === '1000') {
        echo json_encode([
            'success' => true,
            'balance' => $result['balance'] ?? 0,
        ]);
    } else {
        $msg = is_array($result) && !empty($result['status_desc'])
            ? (string) $result['status_desc']
            : ('HTTP ' . $httpCode);
        echo json_encode(['success' => false, 'message' => $msg]);
    }
    exit;
}

function smsGateway_test_send()
{
    header('Content-Type: application/json');
    _admin();

    $phone = trim((string) (_post('phone') ?: (_get('phone') ?: '')));
    $message = trim((string) (_post('message') ?: (_get('message') ?: 'PamNet SMS Gateway test message')));

    if ($phone === '') {
        echo json_encode(['success' => false, 'message' => 'Phone number is required']);
        exit;
    }

    $ok = smsGateway_hook_send_sms([$phone, $message]);
    echo json_encode([
        'success' => (bool) $ok,
        'message' => $ok ? 'Test SMS sent successfully' : 'Failed to send test SMS — check SMS logs for details',
    ]);
    exit;
}

// smsGateway_phoneFormat() provided by 00_pamnet_sms_compat.php (with fallback above)
