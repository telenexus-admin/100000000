<?php
/**
 * Repair package expiry drift + soft-sync MikroTik timezone/NTP.
 * Safe to run from cron (every few minutes).
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
$CACHE_PATH = $root . '/system/cache';

MikrotikTimeSync::applyPhpTimezone();
MikrotikTimeSync::applyMysqlTimezone();

if (!function_exists('_log')) {
    function _log($description, $type = '', $userid = '0')
    {
    }
}
if (!function_exists('r2')) {
    function r2($to, $ntype = 'e', $msg = '')
    {
        throw new RuntimeException('r2 blocked');
    }
}
if (!function_exists('_alert')) {
    function _alert($text, $type = 'success', $url = 'home', $time = 3)
    {
    }
}
if (!function_exists('getUrl')) {
    function getUrl($url)
    {
        return $url;
    }
}

echo date('c') . " sync_package_times start tz=" . date_default_timezone_get() . " now=" . date('Y-m-d H:i:s') . "\n";

$rep = Package::repairTimeBasedExpirations();
echo date('c') . " repair fixed=" . ($rep['fixed'] ?? 0) . " checked=" . ($rep['checked'] ?? 0) . "\n";

$sync = MikrotikTimeSync::syncAllRouters();
foreach ($sync as $name => $r) {
    $drift = $r['drift'] ?? null;
    $driftS = ($drift === null) ? '?' : ($drift . 's');
    echo date('c') . " router {$name} ok=" . (!empty($r['ok']) ? '1' : '0') . " drift={$driftS} msg=" . ($r['message'] ?? '') . "\n";
}

echo date('c') . " sync_package_times done\n";
