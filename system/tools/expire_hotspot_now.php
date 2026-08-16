<?php
/**
 * Fast Hotspot expiry kick — run every minute.
 *
 * When a package expires, remove MikroTik Hotspot user/sessions/cookies
 * and mark recharge off so the phone returns to the captive sign-in page
 * within ~1 minute (not waiting for the heavy every-5-minute cron.php job).
 *
 * Safe: re-checks expiry before kick; skips users who just renewed;
 * does not delete payments/customers.
 *
 * Logging: routine successful expiry kicks go to CLI stdout only.
 * Admin tbl_logs gets failures / fatals only (keeps Logs page useful).
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
        try {
            $d = ORM::for_table('tbl_logs')->create();
            $d->date = date('Y-m-d H:i:s');
            $d->type = $type !== '' ? $type : 'System';
            $d->description = $description;
            $d->userid = $userid;
            $d->ip = '127.0.0.1';
            $d->save();
        } catch (Throwable $e) {
        }
    }
}
if (!function_exists('getUrl')) {
    function getUrl($url)
    {
        return $url;
    }
}

$limit = 40;
$dry = false;
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) {
        $limit = max(1, min(200, (int) $m[1]));
    }
    if ($a === '--dry-run') {
        $dry = true;
    }
}

$lockPath = $root . '/system/cache/expire_hotspot_now.lock';
if (!is_dir(dirname($lockPath))) {
    @mkdir(dirname($lockPath), 0755, true);
}
// Stale lock (>90s) — clear so minute cron never stalls
if (is_file($lockPath) && (time() - (int) @filemtime($lockPath)) > 90) {
    @unlink($lockPath);
}
$lockFp = @fopen($lockPath, 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo date('c') . " expire_hotspot_now already_running\n";
    exit(0);
}
@ftruncate($lockFp, 0);
@fwrite($lockFp, (string) getmypid() . "\n" . date('c') . "\n");
@fflush($lockFp);
@touch($lockPath);

$started = microtime(true);
$now = date('Y-m-d H:i:s');
$expired = 0;
$skipped = 0;
$failed = 0;

try {
    require_once $DEVICE_PATH . '/MikrotikHotspot.php';
    $hsDev = new MikrotikHotspot();
    $clients = [];

    $getClient = function ($routerName) use ($hsDev, &$clients) {
        $routerName = trim((string) $routerName);
        if ($routerName === '' || $routerName === '0') {
            $routerName = 'PMNINTERNET';
        }
        if (array_key_exists($routerName, $clients)) {
            return $clients[$routerName];
        }
        $mk = $hsDev->info($routerName);
        $cli = $hsDev->getClient($mk['ip_address'], $mk['username'], $mk['password']);
        $clients[$routerName] = $cli;
        return $cli;
    };

    // Candidates: active Hotspot packages whose expiry datetime is in the past
    $rows = ORM::for_table('tbl_user_recharges')
        ->where('status', 'on')
        ->where('type', 'Hotspot')
        ->where_raw("TIMESTAMP(`expiration`, IFNULL(NULLIF(`time`, ''), '23:59:59')) <= ?", [$now])
        ->order_by_asc('expiration')
        ->order_by_asc('time')
        ->limit($limit)
        ->find_many();

    echo date('c') . " expire_hotspot_now candidates=" . count($rows) . " now={$now}\n";

    foreach ($rows as $ds) {
        $id = (int) $ds['id'];
        $username = trim((string) $ds['username']);
        try {
            // Re-load — payment may have renewed during this run
            $u = ORM::for_table('tbl_user_recharges')->find_one($id);
            if (!$u || (string) $u['status'] !== 'on') {
                $skipped++;
                continue;
            }
            $expTs = strtotime(trim(($u['expiration'] ?? '') . ' ' . (($u['time'] ?? '') !== '' ? $u['time'] : '23:59:59')));
            if ($expTs === false || $expTs > time()) {
                $skipped++;
                continue;
            }

            // Another active non-expired package for same username? keep access
            $stillValid = ORM::for_table('tbl_user_recharges')
                ->where('username', $username)
                ->where('status', 'on')
                ->where_not_equal('id', $id)
                ->where_raw("TIMESTAMP(`expiration`, IFNULL(NULLIF(`time`, ''), '23:59:59')) > ?", [date('Y-m-d H:i:s')])
                ->find_one();
            if ($stillValid) {
                // Just mark this expired row off; do not kick router
                if (!$dry) {
                    $u->status = 'off';
                    $u->save();
                }
                echo "  off-only (other plan active): {$username}\n";
                $expired++;
                continue;
            }

            $c = ORM::for_table('tbl_customers')->where('id', $u['customer_id'])->find_one();
            if (!$c) {
                $c = [
                    'id' => $u['customer_id'],
                    'username' => $username,
                    'password' => '1234',
                ];
            }
            $p = ORM::for_table('tbl_plans')->where('id', $u['plan_id'])->find_one();
            $routerName = (string) ($u['routers'] ?? ($p['routers'] ?? 'PMNINTERNET'));

            if ($dry) {
                echo "  DRY expire {$username} exp={$u['expiration']} {$u['time']} router={$routerName}\n";
                $expired++;
                continue;
            }

            // Kick to captive portal (sign-in / buy page)
            $mikrotikOk = true;
            $mikrotikErr = '';
            try {
                if (strtolower((string) ($_app_stage ?? '')) !== 'demo') {
                    if ($p && method_exists($hsDev, 'remove_customer')) {
                        // Prefer plan routers when set
                        if (empty($p['routers']) && $routerName !== '') {
                            $p['routers'] = $routerName;
                        }
                        $hsDev->remove_customer($c, $p);
                    } else {
                        $cli = $getClient($routerName);
                        $hsDev->forceCaptivePortal($cli, $username);
                        $hsDev->removeHotspotUser($cli, $username);
                        $hsDev->forceCaptivePortal($cli, $username);
                    }
                }
            } catch (Throwable $e) {
                // Still mark off so billing is correct; retry kick next minute via off-cleanup
                $mikrotikOk = false;
                $mikrotikErr = $e->getMessage();
                echo "  mikrotik fail {$username}: " . $mikrotikErr . "\n";
                $failed++;
            }

            $u->status = 'off';
            $u->save();

            // Clear live usage so admin Online status matches
            try {
                ORM::for_table('tbl_usage_sessions')->where('username', $username)->delete_many();
            } catch (Throwable $e) {
            }

            // Admin Logs: only record problems (not every routine expiry)
            if (!$mikrotikOk) {
                _log(
                    "Hotspot expire kick FAILED: {$username} (plan {$u['namebp']}, until {$u['expiration']} {$u['time']}): {$mikrotikErr}",
                    'System',
                    (string) ($u['customer_id'] ?? 0)
                );
            }
            // CLI/cron stdout only — keeps tbl_logs clean for troubleshooting
            echo "  expired+kicked: {$username} @ {$routerName}\n";
            $expired++;
        } catch (Throwable $e) {
            $failed++;
            echo "  error id={$id} {$username}: " . $e->getMessage() . "\n";
            _log(
                "Hotspot expire error: {$username} id={$id}: " . $e->getMessage(),
                'System',
                '0'
            );
        }
    }

    // Re-kick only recently expired users who still appear online (stuck session)
    $stuck = [];
    try {
        $sessions = ORM::for_table('tbl_usage_sessions')
            ->where_gte('last_seen', date('Y-m-d H:i:s', time() - 600))
            ->find_many();
        foreach ($sessions as $s) {
            $u = trim((string) ($s['username'] ?? ''));
            if ($u !== '') {
                $stuck[$u] = true;
            }
        }
    } catch (Throwable $e) {
        $stuck = [];
    }

    $kickedOff = 0;
    foreach (array_keys($stuck) as $uName) {
        $stillOn = ORM::for_table('tbl_user_recharges')
            ->where('username', $uName)
            ->where('status', 'on')
            ->where_raw("TIMESTAMP(`expiration`, IFNULL(NULLIF(`time`, ''), '23:59:59')) > ?", [date('Y-m-d H:i:s')])
            ->find_one();
        if ($stillOn) {
            continue;
        }
        $offRow = ORM::for_table('tbl_user_recharges')
            ->where('username', $uName)
            ->where('status', 'off')
            ->where('type', 'Hotspot')
            ->order_by_desc('id')
            ->find_one();
        if (!$offRow) {
            continue;
        }
        $rowExp = strtotime(trim(($offRow['expiration'] ?? '') . ' ' . (($offRow['time'] ?? '') !== '' ? $offRow['time'] : '23:59:59')));
        if ($rowExp === false || $rowExp > time()) {
            continue;
        }
        if ($dry) {
            continue;
        }
        try {
            $rName = trim((string) ($offRow['routers'] ?? 'PMNINTERNET'));
            $cli = $getClient($rName !== '' ? $rName : 'PMNINTERNET');
            $hsDev->forceCaptivePortal($cli, $uName);
            $hsDev->removeHotspotUser($cli, $uName);
            $hsDev->forceCaptivePortal($cli, $uName);
            try {
                ORM::for_table('tbl_usage_sessions')->where('username', $uName)->delete_many();
            } catch (Throwable $e) {
            }
            $kickedOff++;
            echo "  stuck-session kicked: {$uName}\n";
        } catch (Throwable $e) {
            echo "  stuck-session fail {$uName}: " . $e->getMessage() . "\n";
            _log("Hotspot stuck-session kick FAILED: {$uName}: " . $e->getMessage(), 'System', '0');
        }
    }

    $ms = (int) round((microtime(true) - $started) * 1000);
    echo date('c') . " expire_hotspot_now done expired={$expired} skipped={$skipped} failed={$failed} portal_retry={$kickedOff} ms={$ms}\n";
} catch (Throwable $e) {
    echo date('c') . " expire_hotspot_now FATAL: " . $e->getMessage() . "\n";
    _log('Hotspot expire FATAL: ' . $e->getMessage(), 'System', '0');
} finally {
    if (isset($lockFp) && is_resource($lockFp)) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
    @unlink($lockPath);
}
