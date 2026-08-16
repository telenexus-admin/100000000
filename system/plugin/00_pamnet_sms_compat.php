<?php
/**
 * PamNet SMS Gateway compatibility core (Blessed Texts).
 *
 * Loaded early via init.php plugin glob (00_* prefix).
 * Device/gateway detection never blocks other SMS providers; this only
 * ensures Blessed Texts credentials are read from the database reliably
 * so Message::sendSMS never falsely reports "Configuration missing".
 */

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

    return [
        'api_key' => $apiKey,
        'sender_id' => $senderId,
        'missing' => $missing,
    ];
}

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

function smsGateway_isConfigured()
{
    $cfg = smsGateway_loadConfig(true);
    return empty($cfg['missing']);
}

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

/**
 * One-shot: ensure Message.php includes Blessed Texts sendSMS integration.
 * Does not overwrite the whole file — only applies the known patch if missing.
 */
(function () {
    $marker = 'PAMNET_SMS_BLESSEDTEXTS_V1';
    $system = dirname(__DIR__);
    $stamp = $system . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pamnet_sms_blessed.stamp';
    if (is_file($stamp)) {
        $cur = @file_get_contents($stamp);
        if (is_string($cur) && strpos($cur, $marker) !== false) {
            return;
        }
    }

    $msgFile = $system . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Message.php';
    if (!is_file($msgFile) || !is_writable($msgFile)) {
        return;
    }
    $src = @file_get_contents($msgFile);
    if (!is_string($src) || $src === '') {
        return;
    }
    if (strpos($src, 'Blessed Texts / send_sms hook') !== false) {
        @mkdir(dirname($stamp), 0755, true);
        @file_put_contents($stamp, $marker . "\n" . date('c') . "\n");
        return;
    }

    // Only patch the classic unpatched sendSMS opener
    $old = "    public static function sendSMS(\$phone, \$txt)\n    {\n        global \$config;\n        if (empty(\$txt)) {\n            return \"\";\n        }\n        run_hook('send_sms', [\$phone, \$txt]); #HOOK";
    $new = "    public static function sendSMS(\$phone, \$txt)\n    {\n        global \$config;\n        if (empty(\$txt)) {\n            return \"\";\n        }\n\n        // {$marker}\n        // Blessed Texts (plugin) — always attempt via hook when configured.\n        \$hookResult = run_hook('send_sms', [\$phone, \$txt]); #HOOK\n        if (\$hookResult === true) {\n            self::logMessage('SMS', \$phone, \$txt, 'Success', 'Blessed Texts / send_sms hook');\n            return 'OK';\n        }";

    if (strpos($src, $old) === false) {
        return;
    }
    $src = str_replace($old, $new, $src);

    $oldType = "        // Check SMS gateway type\n        \$gateway_type = isset(\$config['sms_gateway_type']) ? \$config['sms_gateway_type'] : 'url';\n        \n        try {\n            if (\$gateway_type === 'africastalking') {";
    $newType = "        // Check SMS gateway type\n        \$gateway_type = isset(\$config['sms_gateway_type']) ? strtolower(trim((string) \$config['sms_gateway_type'])) : 'url';\n\n        if (\$gateway_type === 'blessedtexts' || \$gateway_type === 'blessed' || \$gateway_type === 'blessed_texts') {\n            if (function_exists('smsGateway_isConfigured') && smsGateway_isConfigured() && function_exists('smsGateway_hook_send_sms')) {\n                \$ok = smsGateway_hook_send_sms([\$phone, \$txt]);\n                if (\$ok) {\n                    self::logMessage('SMS', \$phone, \$txt, 'Success', 'Blessed Texts');\n                    return 'OK';\n                }\n                return 'Error: Blessed Texts send failed';\n            }\n            return 'Error: Blessed Texts configuration missing';\n        }\n        \n        try {\n            if (\$gateway_type === 'africastalking') {";
    if (strpos($src, $oldType) !== false) {
        $src = str_replace($oldType, $newType, $src);
    }

    $oldEnd = "        return 'No SMS gateway configured';\n    }";
    $newEnd = "        \$btKey = trim((string) (\$config['blessed_texts_api_key'] ?? ''));\n        \$btSender = trim((string) (\$config['blessed_texts_sender_id'] ?? ''));\n        if (\$btKey !== '' && \$btSender !== '' && function_exists('smsGateway_hook_send_sms')) {\n            \$ok = smsGateway_hook_send_sms([\$phone, \$txt]);\n            if (\$ok) { return 'OK'; }\n            return 'Error: Blessed Texts send failed — see SMS logs';\n        }\n        \n        return 'No SMS gateway configured';\n    }";
    if (strpos($src, $oldEnd) !== false && strpos($src, 'Blessed Texts send failed — see SMS logs') === false) {
        $src = str_replace($oldEnd, $newEnd, $src);
    }

    if (@file_put_contents($msgFile, $src) !== false) {
        @mkdir(dirname($stamp), 0755, true);
        @file_put_contents($stamp, $marker . "\n" . date('c') . "\npatched\n");
    }
})();
