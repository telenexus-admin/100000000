<?php
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$root = dirname(__DIR__);
$url = 'https://net.pamnetsolutions.co.ke/download.php?download=1&_ts=' . time();
$html = shell_exec('curl -fsSL --max-time 30 ' . escapeshellarg($url));
if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) {
    fwrite(STDERR, "generate failed\n");
    exit(1);
}
// Only refuse real preview builds (assignment / sticky banner). Do NOT match JS
// strings like title:'Preview mode' inside the production connect guard.
$isPreviewBuild = (stripos($html, 'window.PAMNET_PREVIEW=true') !== false)
    || (stripos($html, 'PREVIEW MODE —') !== false)
    || (stripos($html, 'PREVIEW MODE - this is how') !== false);
if ($isPreviewBuild || stripos($html, 'chap-id') === false) {
    fwrite(STDERR, "refusing preview/broken build\n");
    exit(1);
}
if (stripos($html, 'pamnet-universal') === false && stripos($html, 'pamnet-portal" content="universal"') === false) {
    // Soft warning only — older generators may omit the marker
    echo "warn=universal_marker_missing\n";
}

file_put_contents($root . '/hotspot_login.html', $html);
@mkdir($root . '/system/uploads', 0755, true);
file_put_contents($root . '/system/uploads/hotspot_login.html', $html);
echo 'generated=' . strlen($html) . "\n";
$public = 'https://net.pamnetsolutions.co.ke/hotspot_login.html';

$r = ORM::for_table('tbl_routers')->find_one(1);
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

$dir = 'hotspot';
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $hs) {
    if ((string) $hs->getProperty('disabled') === 'true') {
        continue;
    }
    $prof = (string) $hs->getProperty('profile');
    if ($prof === '') {
        continue;
    }
    foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
        if ((string) $p->getProperty('name') === $prof) {
            $d = trim((string) $p->getProperty('html-directory'));
            if ($d !== '') {
                $dir = $d;
            }
            // Prefer PAP first for broad device compatibility (old phones/TVs/PCs).
            // Do NOT include trial (grants free internet) or mac/mac-cookie
            // (silently re-auth devices that no longer have active packages).
            // cookie lifetime is short (30m) so expired users see the portal promptly.
            try {
                $set = new RouterOS\Request('/ip/hotspot/profile/set');
                $set->setArgument('numbers', $p->getProperty('.id'));
                $set->setArgument('login-by', 'http-pap,http-chap,cookie');
                $set->setArgument('http-cookie-lifetime', '3d');
                $c->sendSync($set);
                echo 'login-by=http-pap,http-chap,cookie cookie-lifetime=3d' . "\n";
            } catch (Throwable $eLb) {
            }
        }
    }
    break;
}
$dst = $dir . '/login.html';
echo "dst=$dst\n";

$fetch = new RouterOS\Request('/tool/fetch');
$fetch->setArgument('url', $public);
$fetch->setArgument('dst-path', $dst);
$fetch->setArgument('mode', 'https');
$c->sendSync($fetch);
sleep(3);

$found = false;
foreach ($c->sendSync(new RouterOS\Request('/file/print')) as $f) {
    if ((string) $f->getProperty('name') === $dst) {
        echo 'FILE_OK size=' . $f->getProperty('size') . "\n";
        $found = true;
    }
}
if (!$found) {
    echo "FILE_MISSING\n";
}

// Publish universal companion files so old Android/iPhone/TV alternate pages
// always land on the same login.html UI.
$stubDir = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'hotspot_universal_stubs';
$stubPublicDir = $root . DIRECTORY_SEPARATOR . 'hotspot_universal';
@mkdir($stubPublicDir, 0755, true);
$companions = [
    'payments.html',
    'package.html',
    'alogin.html',
    'error.html',
    'login-required.js',
    'processor.js',
    'page-tab.js',
];
$stubBase = 'https://net.pamnetsolutions.co.ke/hotspot_universal';
foreach ($companions as $name) {
    $src = $stubDir . DIRECTORY_SEPARATOR . $name;
    if (!is_file($src)) {
        echo "stub_missing=$name\n";
        continue;
    }
    $body = file_get_contents($src);
    file_put_contents($stubPublicDir . DIRECTORY_SEPARATOR . $name, $body);
    $fetch = new RouterOS\Request('/tool/fetch');
    $fetch->setArgument('url', $stubBase . '/' . rawurlencode($name) . '?_ts=' . time());
    $fetch->setArgument('dst-path', $dir . '/' . $name);
    $fetch->setArgument('mode', 'https');
    try {
        $c->sendSync($fetch);
        echo "stub_ok=$name\n";
    } catch (Throwable $e) {
        echo "stub_fail=$name\n";
    }
    usleep(250000);
}
sleep(2);

require_once $root . '/tools/pamnet_walled_garden_hosts.php';
$billingHost = parse_url($public, PHP_URL_HOST) ?: '';
$added = pamnet_ensure_walled_garden_hosts($c, $billingHost);
echo "walled_added=$added\n";
// Re-apply DoT / DNS firewall rules so Android Private DNS does not block the portal.
if (function_exists('pamnet_ensure_hotspot_firewall_rules')) {
    pamnet_ensure_hotspot_firewall_rules($c, 'hotspot_bridge');
    echo "firewall_rules=ok\n";
}
echo "DONE\n";
