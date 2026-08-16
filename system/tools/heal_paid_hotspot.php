<?php
/**
 * Auto-heal: activate Hotspot users whose M-Pesa C2B payment landed
 * but STK callback never recharged / never pushed MikroTik.
 * Safe to run every minute via cron.
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
        throw new RuntimeException("r2 blocked: $to $msg");
    }
}
if (!function_exists('_alert')) {
    function _alert($text, $type = 'success', $url = 'home', $time = 3)
    {
        throw new RuntimeException('_alert: ' . $text);
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

$hours = 12;
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--hours=(\d+)$/', $a, $m)) {
        $hours = (int) $m[1];
    }
}

$done = PamnetHotspotPay::healRecent($hours);
$ts = date('c');
if ($done) {
    echo $ts . ' healed=' . count($done) . ' ' . implode(',', $done) . "\n";
} else {
    echo $ts . " healed=0\n";
}
