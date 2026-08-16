<?php
/**
 * Remove Hotspot router access for customers whose package expired
 * 2+ days ago with no new purchase since.
 *
 * SAFE: does NOT delete/modify payments, transactions, customers,
 * or recharge history amounts — only clears MikroTik access + usage sessions.
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$root = dirname(__DIR__, 1);
if (!is_file($root . '/config.php')) {
    $root = '/var/www/html/pamnet';
}
chdir($root);

require $root . '/config.php';
require $root . '/system/orm.php';
require_once $root . '/system/autoload/PEAR2/Autoload.php';
require_once $root . '/system/autoload/Hookers.php';

spl_autoload_register(function ($class) use ($root) {
    $path = $root . '/system/autoload/' . str_replace(['_', '\\'], '/', $class) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

if (!function_exists('run_hook')) {
    function run_hook($name)
    {
        return [];
    }
}
if (!empty($db_password) && empty($db_pass)) {
    $db_pass = $db_password;
}
ORM::configure("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4");
ORM::configure('username', $db_user);
ORM::configure('password', $db_pass);
ORM::configure('return_result_sets', true);

$config = [];
foreach (ORM::for_table('tbl_appconfig')->find_many() as $row) {
    $config[$row['setting']] = $row['value'];
}
$_c = $config;
$_app_stage = $_app_stage ?? 'Live';
$DEVICE_PATH = $root . '/system/devices';
date_default_timezone_set($config['timezone'] ?? 'Africa/Nairobi');
try {
    ORM::raw_execute("SET time_zone = '+03:00'");
} catch (Throwable $e) {
}

if (!function_exists('r2')) {
    function r2($to, $ntype = 'e', $msg = '')
    {
        throw new RuntimeException("r2 blocked");
    }
}
if (!function_exists('_alert')) {
    function _alert($text, $type = 'success', $url = 'home', $time = 3)
    {
    }
}
if (!function_exists('_log')) {
    function _log($description, $type = '', $userid = '0')
    {
    }
}
if (!function_exists('getUrl')) {
    function getUrl($url)
    {
        return $url;
    }
}

$graceDays = 2;
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--days=(\d+)$/', $a, $m)) {
        $graceDays = max(1, (int) $m[1]);
    }
}
$dry = in_array('--dry-run', $argv ?? [], true);

// Prevent overlapping runs (keeps router API free)
$lockPath = $root . '/system/cache/stale_hotspot_cleanup.lock';
if (!is_dir(dirname($lockPath))) {
    @mkdir(dirname($lockPath), 0755, true);
}
// Clear stale lock if a previous run crashed (>20 minutes)
if (is_file($lockPath) && (time() - (int) @filemtime($lockPath)) > 1200) {
    @unlink($lockPath);
}
$lockFp = @fopen($lockPath, 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo date('c') . " stale_hotspot_cleanup already_running\n";
    exit(0);
}
@ftruncate($lockFp, 0);
@fwrite($lockFp, (string) getmypid() . "\n" . date('c') . "\n");
@fflush($lockFp);
@touch($lockPath);

$cutoffTs = time() - ($graceDays * 86400);
$cleared = 0;
$skipped = 0;
$failed = 0;

require_once $root . '/system/devices/MikrotikHotspot.php';
$hsDev = new MikrotikHotspot();
$clients = []; // routerName => client

$getClient = function ($routerName) use ($hsDev, &$clients) {
    if (isset($clients[$routerName])) {
        return $clients[$routerName];
    }
    $mk = $hsDev->info($routerName);
    $cli = $hsDev->getClient($mk['ip_address'], $mk['username'], $mk['password']);
    $clients[$routerName] = $cli;
    return $cli;
};

// Latest Hotspot recharge id per Hotspot customer
try {
    $latestRows = ORM::for_table('tbl_user_recharges')
        ->raw_query(
            "SELECT ur.* FROM tbl_user_recharges ur
             INNER JOIN (
                SELECT username, MAX(id) AS max_id
                FROM tbl_user_recharges
                GROUP BY username
             ) t ON ur.id = t.max_id
             INNER JOIN tbl_customers c ON c.username = ur.username AND c.service_type = 'Hotspot'"
        )
        ->find_many();
} catch (Throwable $e) {
    echo "query_error=" . $e->getMessage() . "\n";
    $latestRows = [];
}

foreach ($latestRows as $ur) {
    $username = trim((string) ($ur['username'] ?? ''));
    if ($username === '') {
        continue;
    }

    // Only Hotspot customers
    $cust = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
    if ($cust && strcasecmp((string) ($cust['service_type'] ?? ''), 'Hotspot') !== 0) {
        $skipped++;
        continue;
    }
    $type = strtoupper(trim((string) ($ur['type'] ?? '')));
    if ($type !== '' && $type !== 'HOTSPOT') {
        // If customer is Hotspot but recharge type empty, still allow
        if (!($cust && strcasecmp((string) $cust['service_type'], 'Hotspot') === 0)) {
            $skipped++;
            continue;
        }
    }

    $expTs = strtotime(trim(($ur['expiration'] ?? '') . ' ' . ($ur['time'] ?? '23:59:59')));
    if ($expTs === false) {
        $skipped++;
        continue;
    }

    // Still within package time
    if ($expTs > time()) {
        $skipped++;
        continue;
    }

    // Grace: only after N days past expiry
    if ($expTs > $cutoffTs) {
        $skipped++;
        continue;
    }

    // Still has a live package (any on + not expired row) — never strip access
    $live = false;
    try {
        $onRows = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->where('status', 'on')
            ->find_many();
        foreach ($onRows as $on) {
            $onExp = strtotime(trim(($on['expiration'] ?? '') . ' ' . ($on['time'] ?? '23:59:59')));
            if ($onExp !== false && $onExp > time()) {
                $live = true;
                break;
            }
        }
    } catch (Throwable $e) {
    }
    if ($live) {
        $skipped++;
        continue;
    }

    // Any newer purchase after this expiry? (another recharge row)
    $newer = ORM::for_table('tbl_user_recharges')
        ->where('username', $username)
        ->where_gt('id', (int) $ur['id'])
        ->order_by_desc('id')
        ->find_one();
    if ($newer) {
        $newerExp = strtotime(trim(($newer['expiration'] ?? '') . ' ' . ($newer['time'] ?? '23:59:59')));
        if ($newerExp !== false && $newerExp > time()) {
            $skipped++;
            continue;
        }
        // If newer also expired > grace, still clean based on newest
        $newerExpPast = ($newerExp !== false && $newerExp <= $cutoffTs);
        if (!$newerExpPast && (string) $newer['status'] === 'on') {
            $skipped++;
            continue;
        }
    }

    // Paid PG after expiry means they bought again (even if recharge lag)
    // READ-ONLY check — never modifies payment gateway rows
    $paidAfter = ORM::for_table('tbl_payment_gateway')
        ->where('username', $username)
        ->where('status', 2)
        ->where_raw('paid_date >= ?', [date('Y-m-d H:i:s', $expTs)])
        ->order_by_desc('id')
        ->find_one();
    if ($paidAfter) {
        // If they paid within the grace window, don't strip — healer/cron will activate
        $paidTs = strtotime((string) $paidAfter['paid_date']);
        if ($paidTs !== false && $paidTs >= $expTs && (time() - $paidTs) < ($graceDays * 86400)) {
            $skipped++;
            continue;
        }
        // Paid after expiry and package should be live — skip if healer may still catch it (last 6h)
        if ($paidTs !== false && (time() - $paidTs) < 21600) {
            $skipped++;
            continue;
        }
    }

    $routerName = trim((string) ($ur['routers'] ?? ''));
    if ($routerName === '' || $routerName === '0') {
        $routerName = 'PMNINTERNET';
    }

    echo ($dry ? 'DRY ' : '') . "CLEAR {$username} expired=" . date('Y-m-d H:i:s', $expTs) . " router={$routerName}\n";

    if ($dry) {
        $cleared++;
        continue;
    }

    try {
        // Mark recharge off if still wrongly on (does not touch payments)
        if ((string) $ur['status'] === 'on') {
            $ur->status = 'off';
            $ur->save();
        }

        if (strtolower((string) $_app_stage) !== 'demo') {
            $cli = $getClient($routerName);
            $hsDev->forceCaptivePortal($cli, $username);
            $hsDev->removeHotspotUser($cli, $username);
            $hsDev->forceCaptivePortal($cli, $username);
        }

        // Clear live usage session rows only (not payment/history/customer tables)
        try {
            ORM::raw_execute('DELETE FROM tbl_usage_sessions WHERE username = ?', [$username]);
        } catch (Throwable $e) {
            try {
                ORM::for_table('tbl_usage_sessions')->where('username', $username)->delete_many();
            } catch (Throwable $e2) {
            }
        }

        $cleared++;
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL {$username}: " . $e->getMessage() . "\n";
    }
}

$ts = date('c');
echo "{$ts} stale_hotspot_cleanup days={$graceDays} cleared={$cleared} skipped={$skipped} failed={$failed}\n";
if (isset($lockFp) && $lockFp) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}