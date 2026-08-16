<?php
/**
 * Pamnet Safe Reminders — PPPoE-only SMS + null-safe 7/3/1 reminders.
 * Also supports: https://yoursite/?pamnet_reminders=1
 */

if (!isset($root_path) && !defined('ROOTPATH')) {
    return;
}

// Quick status: ?pamnet_check_sms=1
if (isset($_GET['pamnet_check_sms'])) {
    $__msgFile = (isset($root_path) ? $root_path : '') . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Message.php';
    if (!is_file($__msgFile)) {
        $__msgFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Message.php';
    }
    header('Content-Type: text/plain; charset=utf-8');
    $src = is_file($__msgFile) ? (string) @file_get_contents($__msgFile) : '';
    echo "Message.php: " . ($src !== '' ? 'found' : 'missing') . "\n";
    echo "VIA patch: " . (strpos($src, 'PAMNET_PPPOE_ONLY_VIA') !== false ? 'yes' : 'no') . "\n";
    echo "INVOICE patch: " . (strpos($src, 'PAMNET_PPPOE_ONLY_INVOICE') !== false ? 'yes' : 'no') . "\n";
    echo "Hotspot payment channel would be: none (PPPoE only)\n";
    exit;
}

// Deploy PPPoE-only SMS into Message.php (expired/payment/invoice — Hotspot blocked)
if (empty($GLOBALS['PAMNET_PPPOE_ONLY_VIA_PATCHED'])) {
    $GLOBALS['PAMNET_PPPOE_ONLY_VIA_PATCHED'] = true;
    $__msgFile = (isset($root_path) ? $root_path : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR)
        . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Message.php';
    if (!is_file($__msgFile)) {
        $__msgFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Message.php';
    }
    if (is_file($__msgFile) && is_writable($__msgFile)) {
        $__src = @file_get_contents($__msgFile);
        if (is_string($__src)) {
            $__changed = false;

            if (strpos($__src, 'PAMNET_PPPOE_ONLY_VIA') === false) {
                $__newMethod = <<<'PHP'
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
PHP;
                $__patched = preg_replace(
                    '/public static function resolveNotificationVia\(\$type,\s*\$kind\s*=\s*\'reminder\'\)\s*\{.*?^\s{4}\}/ms',
                    rtrim($__newMethod),
                    $__src,
                    1,
                    $__count
                );
                if (is_string($__patched) && !empty($__count)) {
                    $__src = $__patched;
                    $__changed = true;
                }
            }

            // Block Hotspot payment invoice SMS (M-Pesa STK etc.)
            if (strpos($__src, 'PAMNET_PPPOE_ONLY_INVOICE') === false
                && strpos($__src, 'function sendInvoice') !== false) {
                $__needle = "public static function sendInvoice(\$cust, \$trx)\n    {\n        global \$config, \$db_pass;";
                $__repl = "public static function sendInvoice(\$cust, \$trx)\n    {\n        // PAMNET_PPPOE_ONLY_INVOICE — Hotspot payment invoices never SMS; PPPoE only\n        global \$config, \$db_pass;\n        \$__payVia = self::resolveNotificationVia((string) (\$trx['type'] ?? ''), 'payment');\n        if (\$__payVia === '' || \$__payVia === 'none') {\n            return;\n        }";
                if (strpos($__src, $__needle) !== false) {
                    $__src = str_replace($__needle, $__repl, $__src);
                    $__src = str_replace(
                        "if (\$config['user_notification_payment'] == 'sms') {\n            Message::sendSMS(\$cust['phonenumber'], \$textInvoice);\n        } else if (\$config['user_notification_payment'] == 'email') {\n            self::sendEmail(\$cust['email'], '[' . \$config['CompanyName'] . '] ' . Lang::T(\"Invoice\") . ' #' . \$trx['invoice'], \$textInvoice);\n        } else if (\$config['user_notification_payment'] == 'wa') {\n            Message::sendWhatsapp(\$cust['phonenumber'], \$textInvoice);\n        }",
                        "if (\$__payVia == 'sms') {\n            Message::sendSMS(\$cust['phonenumber'], \$textInvoice);\n        } else if (\$__payVia == 'email') {\n            self::sendEmail(\$cust['email'], '[' . \$config['CompanyName'] . '] ' . Lang::T(\"Invoice\") . ' #' . \$trx['invoice'], \$textInvoice);\n        } else if (\$__payVia == 'wa') {\n            Message::sendWhatsapp(\$cust['phonenumber'], \$textInvoice);\n        }",
                        $__src
                    );
                    $__changed = true;
                }
            }

            if ($__changed) {
                @file_put_contents($__msgFile, $__src);
            }
        }
    }
}

$__script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
$__run = isset($_GET['pamnet_reminders']) || $__script === 'cron_reminder.php';
if (!$__run) {
    return;
}

// Prevent double-run if init is included more than once
if (!empty($GLOBALS['PAMNET_SAFE_REMINDER_RAN'])) {
    if ($__script === 'cron_reminder.php') {
        exit;
    }
    return;
}
$GLOBALS['PAMNET_SAFE_REMINDER_RAN'] = true;

if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "PAMNET_SAFE_REMINDER_V5c\n";
echo "Mode: PPPoE only (Hotspot skipped)\n";
echo "PHP Time\t" . date('Y-m-d H:i:s') . "\n";
try {
    ORM::raw_execute('SELECT NOW() AS WAKTU;');
    $statement = ORM::get_last_statement();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        echo "MYSQL Time\t" . $row['WAKTU'] . "\n";
    }
} catch (Throwable $e) {
    echo "MYSQL Time\terror: " . $e->getMessage() . "\n";
}

$day7 = date('Y-m-d', strtotime('+7 day'));
$day3 = date('Y-m-d', strtotime('+3 day'));
$day1 = date('Y-m-d', strtotime('+1 day'));
echo "Windows: 1d={$day1} 3d={$day3} 7d={$day7}\n";

$enabled7 = (($config['notification_reminder_7days'] ?? $config['notification_reminder_7day'] ?? 'yes') !== 'no');
$enabled3 = (($config['notification_reminder_3days'] ?? $config['notification_reminder_3day'] ?? 'yes') !== 'no');
// Day-1 defaults OFF (unchecked checkbox does not POST, so missing must not mean yes)
$enabled1 = (($config['notification_reminder_1day'] ?? 'no') === 'yes');
echo "Enabled: 7d=" . ($enabled7 ? 'yes' : 'no') . " 3d=" . ($enabled3 ? 'yes' : 'no') . " 1d=" . ($enabled1 ? 'yes' : 'no') . "\n";
$dryRun = isset($_GET['dry']) || isset($_GET['dry_run']);
if ($dryRun) {
    echo "DRY RUN — no SMS will be sent\n";
}

$resolveVia = function ($type, $kind) use ($config) {
    // Hotspot must never receive package SMS
    if (strtoupper((string) $type) !== 'PPPOE') {
        return 'none';
    }
    $kind = strtolower((string) $kind);
    foreach (["user_notification_{$kind}_pppoe", "user_notification_{$kind}"] as $key) {
        $via = strtolower(trim((string) ($config[$key] ?? '')));
        if ($via !== '' && $via !== 'none') {
            return $via;
        }
    }
    return 'sms';
};

$resolveText = function ($type, $baseKey) {
    $isPppoe = strtoupper((string) $type) === 'PPPOE';
    if ($isPppoe) {
        $t = Lang::getNotifText($baseKey . '_pppoe');
        if (is_string($t) && trim($t) !== '') {
            return $t;
        }
    }
    $raw = Lang::getNotifText($baseKey);
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    if (strpos($raw, '<divider>') === false) {
        return $raw;
    }
    $parts = explode('<divider>', $raw);
    $hot = trim((string) ($parts[0] ?? ''));
    $pp = trim((string) ($parts[1] ?? ''));
    return $isPppoe ? ($pp !== '' ? $pp : $hot) : ($hot !== '' ? $hot : $pp);
};

$sent = 0;
$skipped = 0;
$errors = 0;

try {
    $d = ORM::for_table('tbl_user_recharges')->where('status', 'on')->whereNotEqual('customer_id', '0')->find_many();
} catch (Throwable $e) {
    echo "ERROR loading recharges: " . $e->getMessage() . "\n";
    if ($__script === 'cron_reminder.php' || isset($_GET['pamnet_reminders'])) {
        exit;
    }
    return;
}

foreach ($d as $ds) {
    $exp = (string) ($ds['expiration'] ?? '');
    if (!in_array($exp, [$day1, $day3, $day7], true)) {
        continue;
    }
    $uname = (string) ($ds['username'] ?? ('id:' . ($ds['id'] ?? '?')));
    try {
        $u = ORM::for_table('tbl_user_recharges')->where('id', $ds['id'])->find_one();
        if (!$u) {
            echo "SKIP {$uname} — recharge missing\n";
            $skipped++;
            continue;
        }
        $p = ORM::for_table('tbl_plans')->where('id', $u['plan_id'])->find_one();
        if (!$p) {
            echo "SKIP {$uname} — plan missing\n";
            $skipped++;
            continue;
        }
        $c = ORM::for_table('tbl_customers')->where('id', $ds['customer_id'])->find_one();
        if (!$c) {
            echo "SKIP {$uname} — customer missing\n";
            $skipped++;
            continue;
        }
        $phone = trim((string) ($c['phonenumber'] ?? ''));
        if ($phone === '') {
            echo "SKIP {$c['username']} — no phone\n";
            $skipped++;
            continue;
        }

        if (($p['validity_unit'] ?? '') == 'Period') {
            $add_inv = User::getAttribute('Invoice', $ds['customer_id']);
            $price = (empty($add_inv) || $add_inv == 0) ? $p['price'] : $add_inv;
        } else {
            $price = $p['price'];
        }

        $planType = $p['type'] ?? 'Hotspot';
        if (strtoupper((string) $planType) !== 'PPPOE') {
            echo "SKIP {$c['username']} — Hotspot (PPPoE only)\n";
            $skipped++;
            continue;
        }
        $via = $resolveVia($planType, 'reminder');
        if ($via === '' || $via === 'none') {
            echo "SKIP {$c['username']} — channel none\n";
            $skipped++;
            continue;
        }

        $which = null;
        $key = null;
        if ($exp === $day7 && $enabled7) {
            $which = '7d';
            $key = 'reminder_7_day';
        } elseif ($exp === $day3 && $enabled3) {
            $which = '3d';
            $key = 'reminder_3_day';
        } elseif ($exp === $day1 && $enabled1) {
            $which = '1d';
            $key = 'reminder_1_day';
        } else {
            continue;
        }

        $text = $resolveText($planType, $key);
        if (trim((string) $text) === '') {
            echo "SKIP {$c['username']} — empty template {$key}\n";
            $skipped++;
            continue;
        }

        if ($dryRun) {
            echo "DRY [{$which}] {$c['username']} → {$phone} via={$via} plan={$planType}\n";
            $sent++;
            continue;
        }
        $out = Message::sendPackageNotification($c, $p['name_plan'], $price, $text, $via);
        echo $out . " [{$which}] {$c['username']} → {$phone}\n";
        $sent++;
    } catch (Throwable $e) {
        $errors++;
        echo "ERROR {$uname}: " . $e->getMessage() . "\n";
    }
}

echo "Reminder cron done. sent={$sent} skipped={$skipped} errors={$errors}\n";

// Stop old/broken cron_reminder.php body from running after init
if ($__script === 'cron_reminder.php' || isset($_GET['pamnet_reminders'])) {
    exit;
}
