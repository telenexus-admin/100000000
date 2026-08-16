<?php
/**
 * CLI: apply fast Wi-Fi association / DHCP / captive-DNS on MikroTik.
 * Usage: php tools/optimize_hotspot_connect_speed.php
 */
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$r = ORM::for_table('tbl_routers')->find_one(1);
if (!$r) {
    fwrite(STDERR, "no router\n");
    exit(1);
}
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

if (!function_exists('pamnet_optimize_hotspot_connect_speed')) {
    require_once dirname(__DIR__) . '/system/plugin/00_pamnet_hotspot_compat.php';
}

@unlink(dirname(__DIR__) . '/system/cache/pamnet_captive_portal.stamp');
$result = pamnet_apply_captive_portal_policy($c);
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

echo "VERIFY dhcp-network dns-server:\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/network/print')) as $n) {
    $addr = (string) $n->getProperty('address');
    if (strpos($addr, '10.0.0.') !== 0) {
        continue;
    }
    $dns = trim((string) $n->getProperty('dns-server'));
    $ok = ($dns !== '' && $dns !== '0.0.0.0');
    echo ($ok ? 'OK' : 'FAIL') . " $addr dns=$dns gw=" . $n->getProperty('gateway') . "\n";
}
echo "DONE\n";
