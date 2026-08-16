<?php
/**
 * Dump MikroTik Hotspot user-profile shared-users vs billing plans.
 */
require dirname(__DIR__) . '/init.php';

$plans = ORM::for_table('tbl_plans')->where('type', 'Hotspot')->where('enabled', 1)->find_many();
$byName = [];
foreach ($plans as $p) {
    $byName[(string) $p['name_plan']] = (int) $p['shared_users'];
}

$router = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('id')->find_one();
if (!$router) {
    echo "NO_ROUTER\n";
    exit(1);
}

require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . 'MikrotikHotspot.php';
$device = new MikrotikHotspot();
$client = $device->getClient($router['ip_address'], $router['username'], $router['password']);

echo "router=" . $router['name'] . "\n";
foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/profile/print')) as $row) {
    if (!method_exists($row, 'getProperty')) {
        continue;
    }
    $name = (string) $row->getProperty('name');
    if ($name === '' || $name === 'default' || $name === 'default-encryption') {
        continue;
    }
    $shared = (string) $row->getProperty('shared-users');
    $bill = isset($byName[$name]) ? (string) $byName[$name] : '-';
    $mismatch = ($bill !== '-' && (int) $shared !== (int) $bill) ? ' MISMATCH' : '';
    echo "profile=$name mikrotik_shared=$shared bill_shared=$bill$mismatch\n";
}
