<?php
/**
 * Audit Wi-Fi connect chain: hotspot profile, DHCP, DNS, walled garden,
 * firewall NAT, and captive-probe DNS static entries.
 */
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$r = ORM::for_table('tbl_routers')->find_one(1);
if (!$r) { fwrite(STDERR, "no router\n"); exit(1); }
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

echo "=== HOTSPOT PROFILE ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
    $name = (string)$p->getProperty('name');
    if (!in_array($name, ['hsprof2','default'], true)) continue;
    echo "[$name]\n";
    foreach (['login-by','http-cookie-lifetime','hotspot-address','dns-name','html-directory','rate-limit','use-radius'] as $k) {
        echo "  $k=" . $p->getProperty($k) . "\n";
    }
}

echo "\n=== HOTSPOT INSTANCES ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $h) {
    echo $h->getProperty('name') . " iface=" . $h->getProperty('interface')
        . " profile=" . $h->getProperty('profile')
        . " disabled=" . $h->getProperty('disabled')
        . " keepalive=" . $h->getProperty('keepalive-timeout')
        . " apm=" . $h->getProperty('addresses-per-mac') . "\n";
}

echo "\n=== DHCP SERVER ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/print')) as $d) {
    $iface = (string)$d->getProperty('interface');
    if (strpos($iface, 'hotspot') === false && (string)$d->getProperty('name') !== 'dhcp1') continue;
    echo $d->getProperty('name') . " iface=" . $iface
        . " auth=" . $d->getProperty('authoritative')
        . " arp=" . $d->getProperty('add-arp')
        . " conflict=" . $d->getProperty('conflict-detection')
        . " lease=" . $d->getProperty('lease-time') . "\n";
}

echo "\n=== DHCP NETWORK ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/network/print')) as $n) {
    $addr = (string)$n->getProperty('address');
    if (strpos($addr, '10.0.0.') !== 0) continue;
    echo "address=$addr gw=" . $n->getProperty('gateway')
        . " dns=" . $n->getProperty('dns-server')
        . " domain=" . $n->getProperty('domain') . "\n";
}

echo "\n=== DNS ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/print')) as $dns) {
    echo "servers=" . $dns->getProperty('servers')
        . " allow-remote=" . $dns->getProperty('allow-remote-requests')
        . " qst=" . $dns->getProperty('query-server-timeout')
        . " qtt=" . $dns->getProperty('query-total-timeout') . "\n";
}

echo "\n=== DNS STATIC (captive probes) ===\n";
$probeCheck = ['connectivitycheck.gstatic.com','captive.apple.com','www.msftconnecttest.com','detectportal.firefox.com','neverssl.com'];
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $s) {
    $nm = strtolower((string)$s->getProperty('name'));
    if (in_array($nm, $probeCheck, true) || strpos((string)$s->getProperty('comment'), 'pamnet-captive') !== false) {
        echo $nm . " -> " . $s->getProperty('address') . " ttl=" . $s->getProperty('ttl') . "\n";
    }
}

echo "\n=== WALLED GARDEN (first 30) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $w) {
    if (++$i > 30) { echo "...(more)\n"; break; }
    echo $w->getProperty('dst-host') . " action=" . $w->getProperty('action') . "\n";
}

echo "\n=== WALLED GARDEN IP (first 20) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $w) {
    if (++$i > 20) { echo "...(more)\n"; break; }
    echo "dst=" . $w->getProperty('dst-address') . " proto=" . $w->getProperty('protocol') . " action=" . $w->getProperty('action') . "\n";
}

echo "\n=== NAT MASQUERADE ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/firewall/nat/print')) as $fw) {
    if ((string)$fw->getProperty('action') === 'masquerade') {
        echo "chain=" . $fw->getProperty('chain')
            . " src=" . $fw->getProperty('src-address')
            . " out=" . $fw->getProperty('out-interface')
            . " comment=" . $fw->getProperty('comment') . "\n";
    }
}

echo "\n=== FIREWALL FILTER (INPUT/FORWARD drop/reject rules first 20) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/firewall/filter/print')) as $fw) {
    $action = (string)$fw->getProperty('action');
    if (!in_array($action, ['drop','reject'], true)) continue;
    if (++$i > 20) { echo "...(more)\n"; break; }
    echo "chain=" . $fw->getProperty('chain')
        . " src=" . $fw->getProperty('src-address')
        . " dst=" . $fw->getProperty('dst-address')
        . " proto=" . $fw->getProperty('protocol')
        . " in=" . $fw->getProperty('in-interface')
        . " action=$action"
        . " comment=" . $fw->getProperty('comment') . "\n";
}

echo "\n=== HOTSPOT ACTIVE USERS (first 5) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $u) {
    if (++$i > 5) { echo "...\n"; break; }
    echo "user=" . $u->getProperty('user') . " mac=" . $u->getProperty('mac-address')
        . " ip=" . $u->getProperty('address') . " uptime=" . $u->getProperty('uptime') . "\n";
}

echo "\n=== HOTSPOT HOST TABLE (first 5) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/host/print')) as $h) {
    if (++$i > 5) { echo "...\n"; break; }
    echo "mac=" . $h->getProperty('mac-address')
        . " ip=" . $h->getProperty('address')
        . " to-address=" . $h->getProperty('to-address')
        . " status=" . $h->getProperty('status')
        . " authorized=" . $h->getProperty('authorized') . "\n";
}

echo "\n=== IP ADDRESS hotspot_bridge ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/address/print')) as $a) {
    if (strpos((string)$a->getProperty('interface'), 'hotspot') !== false || (string)$a->getProperty('address') === '10.0.0.1/23') {
        echo $a->getProperty('address') . " iface=" . $a->getProperty('interface') . "\n";
    }
}

echo "AUDIT_DONE\n";
