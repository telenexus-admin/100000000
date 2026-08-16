<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../system/autoload/PEAR2/Autoload.php';

$r = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one();
if (!$r) {
    fwrite(STDERR, "no router\n");
    exit(1);
}
$ip = (string) $r['ip_address'];
$port = 8728;
if (preg_match('/^(.+):(\d+)$/', $ip, $m)) {
    $ip = $m[1];
    $port = (int) $m[2];
}
$c = new PEAR2\Net\RouterOS\Client($ip, $r['username'], $r['password'], $port);
foreach ($c->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
    if ((string) $p->getProperty('name') === 'hsprof2') {
        echo 'login-by=' . $p->getProperty('login-by') . "\n";
        echo 'cookie=' . $p->getProperty('http-cookie-lifetime') . "\n";
        echo 'html-directory=' . $p->getProperty('html-directory') . "\n";
    }
}
$found = false;
foreach ($c->sendSync(new PEAR2\Net\RouterOS\Request('/file/print')) as $f) {
    $n = (string) $f->getProperty('name');
    if ($n === 'RAYPROTECH4/login.html') {
        echo 'login.html size=' . $f->getProperty('size') . "\n";
        $found = true;
    }
}
if (!$found) {
    echo "login.html missing\n";
}
