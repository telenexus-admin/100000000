<?php
/**
 * Full MikroTik captive-portal / DHCP / DNS / firewall audit.
 * Usage: php tools/audit_captive_portal.php
 */
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$r = ORM::for_table('tbl_routers')->find_one(1);
if (!$r) {
    fwrite(STDERR, "no router\n");
    exit(1);
}
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

echo "=== HOTSPOT PROFILE ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
    $n = (string) $p->getProperty('name');
    if ($n !== 'hsprof2' && $n !== 'default') {
        continue;
    }
    echo "name=$n\n";
    echo "  login-by=" . $p->getProperty('login-by') . "\n";
    echo "  hotspot-address=" . $p->getProperty('hotspot-address') . "\n";
    echo "  dns-name=" . $p->getProperty('dns-name') . "\n";
    echo "  http-cookie-lifetime=" . $p->getProperty('http-cookie-lifetime') . "\n";
    echo "  html-directory=" . $p->getProperty('html-directory') . "\n";
    echo "  rate-limit=" . $p->getProperty('rate-limit') . "\n";
    echo "  http-proxy=" . $p->getProperty('http-proxy') . "\n";
    echo "  use-radius=" . $p->getProperty('use-radius') . "\n";
}

echo "\n=== HOTSPOT ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $hs) {
    echo "name=" . $hs->getProperty('name')
        . " disabled=" . $hs->getProperty('disabled')
        . " interface=" . $hs->getProperty('interface')
        . " profile=" . $hs->getProperty('profile')
        . " keepalive=" . $hs->getProperty('keepalive-timeout')
        . " idle=" . $hs->getProperty('idle-timeout')
        . "\n";
}

echo "\n=== DHCP NETWORK ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/network/print')) as $n) {
    $addr = (string) $n->getProperty('address');
    if (strpos($addr, '10.0.') !== 0) {
        continue;
    }
    $dns = (string) $n->getProperty('dns-server');
    $ok = ($dns !== '' && $dns !== '0.0.0.0') ? 'OK' : 'MISSING_DNS';
    echo "$ok $addr gw=" . $n->getProperty('gateway') . " dns=$dns domain=" . $n->getProperty('domain') . "\n";
}

echo "\n=== DHCP SERVER ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/print')) as $d) {
    $nm = (string) $d->getProperty('name');
    if ($nm !== 'dhcp1') {
        continue;
    }
    echo "name=$nm auth=" . $d->getProperty('authoritative')
        . " arp=" . $d->getProperty('add-arp')
        . " conflict=" . $d->getProperty('conflict-detection')
        . " lease=" . $d->getProperty('lease-time') . "\n";
}

echo "\n=== DNS ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/print')) as $dns) {
    echo "servers=" . $dns->getProperty('servers')
        . " qst=" . $dns->getProperty('query-server-timeout')
        . " qtt=" . $dns->getProperty('query-total-timeout')
        . " remote=" . $dns->getProperty('allow-remote-requests') . "\n";
}

echo "\n=== WALLED GARDEN (all) ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $w) {
    echo "  " . $w->getProperty('dst-host') . "\n";
}

echo "\n=== WALLED GARDEN IP (first 20) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $w) {
    if (++$i > 20) {
        echo "  ...\n";
        break;
    }
    echo "  dst-address=" . $w->getProperty('dst-address')
        . " proto=" . $w->getProperty('protocol')
        . " dst-port=" . $w->getProperty('dst-port') . "\n";
}

echo "\n=== FIREWALL NAT (first 30) ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/firewall/nat/print')) as $f) {
    echo "  chain=" . $f->getProperty('chain')
        . " action=" . $f->getProperty('action')
        . " proto=" . $f->getProperty('protocol')
        . " dst-port=" . $f->getProperty('dst-port')
        . " to-ports=" . $f->getProperty('to-ports')
        . " in=" . $f->getProperty('in-interface')
        . " comment=" . $f->getProperty('comment') . "\n";
}

echo "\n=== FIREWALL FILTER INPUT (drop/reject rules on hotspot bridge, first 20) ===\n";
$i = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/firewall/filter/print')) as $f) {
    $action = (string) $f->getProperty('action');
    $chain = (string) $f->getProperty('chain');
    $iface = (string) $f->getProperty('in-interface');
    if ($action !== 'drop' && $action !== 'reject') {
        continue;
    }
    if (strpos($iface, 'hotspot') === false && strpos($iface, 'bridge') === false && $iface !== '') {
        continue;
    }
    echo "  chain=$chain action=$action iface=$iface proto=" . $f->getProperty('protocol')
        . " dst-port=" . $f->getProperty('dst-port')
        . " comment=" . $f->getProperty('comment') . "\n";
    if (++$i >= 20) {
        break;
    }
}

echo "\n=== DNS STATIC (captive probes) ===\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $s) {
    $cm = (string) $s->getProperty('comment');
    $nm = (string) $s->getProperty('name');
    if (strpos($cm, 'pamnet-captive') !== false) {
        echo "  $nm -> " . $s->getProperty('address') . " ttl=" . $s->getProperty('ttl') . "\n";
    }
}

echo "\n=== HOTSPOT ACTIVE (count) ===\n";
$active = iterator_to_array($c->sendSync(new RouterOS\Request('/ip/hotspot/active/print')));
echo "  active_sessions=" . count($active) . "\n";

echo "\n=== HOTSPOT COOKIE (count) ===\n";
$cookies = iterator_to_array($c->sendSync(new RouterOS\Request('/ip/hotspot/cookie/print')));
echo "  cookies=" . count($cookies) . "\n";

echo "\n=== HOTSPOT HOST (count) ===\n";
$hosts = iterator_to_array($c->sendSync(new RouterOS\Request('/ip/hotspot/host/print')));
echo "  hosts=" . count($hosts) . "\n";
$bypassed = 0;
foreach ($hosts as $h) {
    if ((string) $h->getProperty('bypassed') === 'true') {
        $bypassed++;
    }
}
echo "  bypassed=$bypassed\n";

echo "DONE\n";
