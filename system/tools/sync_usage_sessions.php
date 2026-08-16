<?php
/**
 * Lightweight live Hotspot/PPPoE session sync for Customer Usage "Active Now".
 * Safe to run every minute. Does not use the full cron lock.
 * Usage: php system/tools/sync_usage_sessions.php
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$root = dirname(dirname(__DIR__));
chdir($root);
require $root . '/config.php';
require $root . '/system/orm.php';
require_once $root . '/system/autoload/PEAR2/Autoload.php';
spl_autoload_register(function ($c) use ($root) {
    $p = $root . '/system/autoload/' . str_replace(['_', '\\'], '/', $c) . '.php';
    if (is_file($p)) {
        require_once $p;
    }
});
if (!empty($db_password) && empty($db_pass)) {
    $db_pass = $db_password;
}
ORM::configure("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4");
ORM::configure('username', $db_user);
ORM::configure('password', $db_pass);
ORM::configure('return_result_sets', true);
ORM::configure('driver_options', [
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+03:00'",
]);
$config = [];
foreach (ORM::for_table('tbl_appconfig')->find_many() as $r) {
    $config[$r['setting']] = $r['value'];
}
date_default_timezone_set($config['timezone'] ?? 'Africa/Nairobi');
// Align MySQL session timezone with billing timezone
try {
    ORM::raw_execute("SET time_zone = '+03:00'");
} catch (Throwable $e) {
}

// Prevent overlapping syncs (protects MikroTik API from pile-ups)
$lockFile = $root . '/system/cache/usage_sync.lock';
if (!is_dir(dirname($lockFile))) {
    @mkdir(dirname($lockFile), 0755, true);
}
$lockFp = @fopen($lockFile, 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo date('c') . " usage_sync already_running\n";
    exit(0);
}

$nowRow = ORM::for_table('tbl_appconfig')->raw_query('SELECT NOW() AS n')->find_one();
$now = $nowRow && !empty($nowRow['n']) ? $nowRow['n'] : date('Y-m-d H:i:s');
$updated = 0;
$routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
foreach ($routers as $router) {
    try {
        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);
        // Brief success => keep router Online without waiting for full cron check
        if ((string) $router['status'] !== 'Online') {
            $router->status = 'Online';
            $router->last_seen = $now;
            $router->save();
        } else {
            $router->last_seen = $now;
            $router->save();
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'router fail ' . $e->getMessage() . "\n");
        continue;
    }
    try {
        $hotspotActive = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
        foreach ($hotspotActive as $hotspot) {
            $username = $hotspot->getProperty('user');
            $session_id = $hotspot->getProperty('.id');
            if (!$username || !$session_id) {
                continue;
            }
            $rx = intval($hotspot->getProperty('bytes-in'));
            $tx = intval($hotspot->getProperty('bytes-out'));
            $ip = $hotspot->getProperty('address');
            $mac = $hotspot->getProperty('mac-address');
            $session = ORM::for_table('tbl_usage_sessions')
                ->where('router_id', $router->id)
                ->where('username', $username)
                ->where('session_id', $session_id)
                ->find_one();
            if (!$session) {
                $session = ORM::for_table('tbl_usage_sessions')->create();
                $session->router_id = $router->id;
                $session->username = $username;
                $session->interface = 'hotspot';
                $session->session_id = $session_id;
                $session->start_time = $now;
                $session->connection_type = 'hotspot';
            }
            $session->last_rx = $rx;
            $session->last_tx = $tx;
            $session->session_rx = $rx;
            $session->session_tx = $tx;
            $session->last_seen = $now;
            if ($ip) {
                $session->ip_address = $ip;
            }
            if ($mac) {
                $session->mac_address = strtoupper($mac);
            }
            $session->connection_type = 'hotspot';
            $session->save();
            $updated++;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'hs err ' . $e->getMessage() . "\n");
    }
    try {
        $ppp = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ppp/active/print'));
        foreach ($ppp as $row) {
            $username = $row->getProperty('name');
            $session_id = $row->getProperty('.id');
            if (!$username || !$session_id) {
                continue;
            }
            $ip = $row->getProperty('address');
            $session = ORM::for_table('tbl_usage_sessions')
                ->where('router_id', $router->id)
                ->where('username', $username)
                ->where('session_id', $session_id)
                ->find_one();
            if (!$session) {
                $session = ORM::for_table('tbl_usage_sessions')->create();
                $session->router_id = $router->id;
                $session->username = $username;
                $session->interface = 'pppoe';
                $session->session_id = $session_id;
                $session->start_time = $now;
                $session->connection_type = 'pppoe';
                $session->last_rx = 0;
                $session->last_tx = 0;
                $session->session_rx = 0;
                $session->session_tx = 0;
            }
            $session->last_seen = $now;
            if ($ip) {
                $session->ip_address = $ip;
            }
            $session->connection_type = 'pppoe';
            $session->save();
            $updated++;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'ppp err ' . $e->getMessage() . "\n");
    }
}
echo date('c') . " synced=$updated\n";
if (isset($lockFp) && $lockFp) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}