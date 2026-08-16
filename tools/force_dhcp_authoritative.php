<?php
/**
 * One-shot: force DHCP authoritative=yes on hotspot dhcp1 (RouterOS property can print blank).
 */
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$r = ORM::for_table('tbl_routers')->find_one(1);
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/print')) as $d) {
    if ((string) $d->getProperty('name') !== 'dhcp1') {
        continue;
    }
    $set = new RouterOS\Request('/ip/dhcp-server/set');
    $set->setArgument('numbers', $d->getProperty('.id'));
    $set->setArgument('authoritative', 'yes');
    $set->setArgument('add-arp', 'yes');
    $set->setArgument('conflict-detection', 'no');
    $c->sendSync($set);
    echo "set dhcp1 authoritative=yes\n";
}

// dump via export-style get
foreach ($c->sendSync(new RouterOS\Request('/ip/dhcp-server/print')) as $d) {
    if ((string) $d->getProperty('name') !== 'dhcp1') {
        continue;
    }
    echo 'name=' . $d->getProperty('name') . "\n";
    echo 'authoritative=[' . $d->getProperty('authoritative') . "]\n";
    echo 'add-arp=[' . $d->getProperty('add-arp') . "]\n";
    echo 'conflict-detection=[' . $d->getProperty('conflict-detection') . "]\n";
}
echo "DONE\n";
