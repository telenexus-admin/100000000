<?php
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;
$r = ORM::for_table('tbl_routers')->find_one(1);
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);
$found = 0;
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $s) {
    $nm = (string) $s->getProperty('name');
    echo $nm . ' -> ' . $s->getProperty('address') . ' [' . $s->getProperty('comment') . "]\n";
    $found++;
}
echo "total=$found\n";
