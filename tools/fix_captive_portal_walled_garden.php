<?php
/**
 * CLI: re-apply captive-portal policy from system/plugin/00_pamnet_hotspot_compat.php
 * Usage: php tools/fix_captive_portal_walled_garden.php
 */
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$r = ORM::for_table('tbl_routers')->find_one(1);
if (!$r) {
    fwrite(STDERR, "no router\n");
    exit(1);
}
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

if (!function_exists('pamnet_apply_captive_portal_policy')) {
    require_once dirname(__DIR__) . '/system/plugin/00_pamnet_hotspot_compat.php';
}

@unlink(dirname(__DIR__) . '/system/cache/pamnet_captive_portal.stamp');
$result = pamnet_apply_captive_portal_policy($c);
echo 'ok=' . (!empty($result['ok']) ? '1' : '0') . "\n";
echo 'probes_removed=' . (int) ($result['removed'] ?? 0) . "\n";
if (!empty($result['speed']['changes']) && is_array($result['speed']['changes'])) {
    echo "speed_changes=\n";
    foreach ($result['speed']['changes'] as $ch) {
        echo "  $ch\n";
    }
}
if (!empty($result['speed']['message'])) {
    echo 'speed_message=' . $result['speed']['message'] . "\n";
}
if (!empty($result['message'])) {
    echo 'message=' . $result['message'] . "\n";
}

echo "dhcp_network=\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/network/print')) as $n) {
    echo '  ' . $n->getProperty('address')
        . ' gw=' . $n->getProperty('gateway')
        . ' dns=' . $n->getProperty('dns-server')
        . ' domain=' . $n->getProperty('domain') . "\n";
}
echo "dhcp_server=\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/print')) as $d) {
    echo '  ' . $d->getProperty('name')
        . ' iface=' . $d->getProperty('interface')
        . ' auth=' . $d->getProperty('authoritative')
        . ' arp=' . $d->getProperty('add-arp')
        . ' conflict=' . $d->getProperty('conflict-detection') . "\n";
}
echo "dns=\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/print')) as $dns) {
    echo '  servers=' . $dns->getProperty('servers')
        . ' qst=' . $dns->getProperty('query-server-timeout')
        . ' qtt=' . $dns->getProperty('query-total-timeout') . "\n";
}

echo "remaining_bad_hosts=\n";
$bad = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
    $h = strtolower((string) $row->getProperty('dst-host'));
    foreach (['connectivitycheck', 'captive.apple', 'msftconnecttest', 'msftncsi', 'detectportal.firefox', 'neverssl', 'clients3.google', 'clients1.google'] as $n) {
        if (strpos($h, $n) !== false) {
            echo "  STILL:$h\n";
            $bad++;
        }
    }
    if (in_array($h, ['www.google.com', 'google.com', 'gstatic.com', 'www.gstatic.com', 'www.apple.com', 'apple.com'], true)) {
        echo "  STILL:$h\n";
        $bad++;
    }
}
echo "bad_count=$bad\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
    if ((string) $p->getProperty('name') === 'hsprof2') {
        echo 'login-by=' . $p->getProperty('login-by') . ' cookie=' . $p->getProperty('http-cookie-lifetime') . "\n";
    }
}
echo "DONE\n";
