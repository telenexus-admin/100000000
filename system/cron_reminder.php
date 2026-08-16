<?php
/** PAMNET_SAFE_REMINDER_V3 — 7/3/1 day reminders, null-safe, PPPoE channel aware */

include "../init.php";

if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "PHP Time\t" . date('Y-m-d H:i:s') . "\n";
try {
    $res = ORM::raw_execute('SELECT NOW() AS WAKTU;');
    $statement = ORM::get_last_statement();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        echo "MYSQL Time\t" . $row['WAKTU'] . "\n";
    }
} catch (Throwable $e) {
    echo "MYSQL Time\terror: " . $e->getMessage() . "\n";
}

run_hook('cronjob_reminder');

$day7 = date('Y-m-d', strtotime('+7 day'));
$day3 = date('Y-m-d', strtotime('+3 day'));
$day1 = date('Y-m-d', strtotime('+1 day'));
echo "Windows: 1d={$day1} 3d={$day3} 7d={$day7}\n";

$enabled7 = (($config['notification_reminder_7days'] ?? $config['notification_reminder_7day'] ?? 'yes') !== 'no');
$enabled3 = (($config['notification_reminder_3days'] ?? $config['notification_reminder_3day'] ?? 'yes') !== 'no');
$enabled1 = (($config['notification_reminder_1day'] ?? 'yes') !== 'no');
echo "Enabled: 7d=" . ($enabled7 ? 'yes' : 'no') . " 3d=" . ($enabled3 ? 'yes' : 'no') . " 1d=" . ($enabled1 ? 'yes' : 'no') . "\n";

$resolveVia = function ($type, $kind) use ($config) {
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
    if ($isPppoe) {
        return $pp !== '' ? $pp : $hot;
    }
    return $hot !== '' ? $hot : $pp;
};

try {
    $d = ORM::for_table('tbl_user_recharges')->where('status', 'on')->whereNotEqual('customer_id', '0')->find_many();
} catch (Throwable $e) {
    echo "ERROR loading recharges: " . $e->getMessage() . "\n";
    exit;
}

$sent = 0;
$skipped = 0;
$errors = 0;

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

        $out = Message::sendPackageNotification($c, $p['name_plan'], $price, $text, $via);
        echo $out . " [{$which}] {$c['username']} → {$phone}\n";
        $sent++;
    } catch (Throwable $e) {
        $errors++;
        echo "ERROR {$uname}: " . $e->getMessage() . "\n";
        try {
            sendTelegram('Cron Reminder failed for ' . $uname . ' Error: ' . $e->getMessage());
        } catch (Throwable $ignore) {
        }
    }
}

echo "Reminder cron done. sent={$sent} skipped={$skipped} errors={$errors}\n";
