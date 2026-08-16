<?php
/**
 * Full fix for "Internet may not be available" on PAMNET SOLUTIONS hotspot.
 *
 * Root causes addressed:
 * 1. Blank/duplicate walled-garden dst-host entries matching everything
 * 2. Hundreds of duplicate hs-unauth NAT return rules for CDN hosts
 * 3. hs-auth wildcard redirect (blank dst-port) — breaks authenticated Internet
 * 4. pmninternet.net missing from DNS static (captive portal DNS-name resolution)
 * 5. CDN hosts in walled garden causing probes to see "Internet available"
 *
 * Usage: php tools/fix_captive_portal_full.php
 */
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;

$r = ORM::for_table('tbl_routers')->find_one(1);
if (!$r) {
    fwrite(STDERR, "no router\n");
    exit(1);
}
$c = Mikrotik::getClient($r['ip_address'], $r['username'], $r['password']);

// APP_URL can be mangled in CLI context — override with the correct billing host
// from the database settings, falling back to a hardcoded known-good value.
$_billingUrlDb = ORM::for_table('tbl_appconfig')->where('setting', 'hotspot_billing_url')->find_one();
$_billingUrlStr = $_billingUrlDb ? (string) $_billingUrlDb['value'] : '';
if ($_billingUrlStr === '') {
    $_billingUrlStr = defined('APP_URL') ? APP_URL : '';
}
// Extract and validate hostname
$_billingHost = (string) parse_url($_billingUrlStr, PHP_URL_HOST);
if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/', $_billingHost)
    || strpos($_billingHost, '..') !== false
) {
    $_billingHost = 'net.pamnetsolutions.co.ke';
}
define('PAMNET_BILLING_HOST_OVERRIDE', $_billingHost);
echo "billing_host=$_billingHost\n";

$removed = 0;
$kept = 0;
$fixed = 0;

// ─────────────────────────────────────────────────────────────────────
// 1. WALLED GARDEN: remove blank entries + deduplicate
// ─────────────────────────────────────────────────────────────────────
echo "=== 1. Walled Garden cleanup ===\n";

// Hosts that should NOT be in walled garden (OS captive probes — must be intercepted
// so devices detect the portal instead of thinking they have Internet).
$noWall = [
    'connectivitycheck.gstatic.com',
    'connectivitycheck.android.com',
    'clients3.google.com',
    'clients1.google.com',
    'captive.apple.com',
    'www.apple.com',
    'apple.com',
    'www.msftconnecttest.com',
    'msftconnecttest.com',
    'www.msftncsi.com',
    'detectportal.firefox.com',
    'neverssl.com',
    'www.google.com',
    'google.com',
    'gstatic.com',
    'www.gstatic.com',
];

// Hosts that SHOULD be in walled garden (billing portal only — no CDN)
$bh = defined('PAMNET_BILLING_HOST_OVERRIDE') ? PAMNET_BILLING_HOST_OVERRIDE : 'net.pamnetsolutions.co.ke';
$keepHosts = array_values(array_unique(array_filter([
    $bh,
    '*.' . $bh,
    'pamnetsolutions.co.ke',
    '*.pamnetsolutions.co.ke',
])));

// Keep track of what we've already seen so we can deduplicate
$seenWall = [];
$toRemoveWall = [];

foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $w) {
    $id = (string) $w->getProperty('.id');
    $host = strtolower(trim((string) $w->getProperty('dst-host')));

    // Remove blank entries
    if ($host === '') {
        $toRemoveWall[] = $id;
        echo "  REMOVE blank id=$id\n";
        continue;
    }

    // Remove captive probe hosts
    foreach ($noWall as $bad) {
        if ($host === strtolower($bad) || $host === '*.' . strtolower($bad)) {
            $toRemoveWall[] = $id;
            echo "  REMOVE probe-host $host\n";
            continue 2;
        }
    }

    // Remove garbage entries (mangled host names: path components leaked into host)
    if (preg_match('/co\.ke[a-z]/', $host) || strlen($host) > 253 || strpos($host, ' ') !== false
        || strpos($host, '/') !== false) {
        $toRemoveWall[] = $id;
        echo "  REMOVE garbage $host\n";
        continue;
    }

    // Deduplicate
    if (isset($seenWall[$host])) {
        $toRemoveWall[] = $id;
        echo "  REMOVE dup $host\n";
        continue;
    }
    $seenWall[$host] = $id;
}

foreach ($toRemoveWall as $id) {
    try {
        $rm = new RouterOS\Request('/ip/hotspot/walled-garden/remove');
        $rm->setArgument('numbers', $id);
        $c->sendSync($rm);
        $removed++;
    } catch (Throwable $e) {
        echo "  WARN remove walled-garden $id: " . $e->getMessage() . "\n";
    }
}
echo "  Removed $removed walled-garden entries\n";

// Add essential missing entries
foreach ($keepHosts as $host) {
    $h = strtolower($host);
    if (!isset($seenWall[$h])) {
        try {
            $add = new RouterOS\Request('/ip/hotspot/walled-garden/add');
            $add->setArgument('dst-host', $host);
            $add->setArgument('comment', 'pamnet-portal');
            $c->sendSync($add);
            echo "  ADD $host\n";
            $fixed++;
        } catch (Throwable $e) {
            echo "  WARN add $host: " . $e->getMessage() . "\n";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// 2. FIREWALL NAT: deduplicate hs-unauth return rules + fix hs-auth wildcard
// ─────────────────────────────────────────────────────────────────────
echo "\n=== 2. Firewall NAT cleanup ===\n";

$seenHsUnauth = [];   // comment => first id
$toRemoveNat = [];
$hsAuthWildcardRedirect = []; // ids of hs-auth redirect with blank dst-port
$hotspotPort443 = [];         // ids of hs-unauth redirect 443 → 64875

foreach ($c->sendSync(new RouterOS\Request('/ip/firewall/nat/print')) as $f) {
    $id = (string) $f->getProperty('.id');
    $chain = (string) $f->getProperty('chain');
    $action = (string) $f->getProperty('action');
    $comment = trim((string) $f->getProperty('comment'));
    $dstPort = trim((string) $f->getProperty('dst-port'));
    $toPorts = trim((string) $f->getProperty('to-ports'));
    $protocol = trim((string) $f->getProperty('protocol'));

    // hs-unauth duplicate return rules (added per-CDN host, accumulated over many runs)
    if ($chain === 'hs-unauth' && $action === 'return' && $comment !== '') {
        $key = $chain . ':' . $action . ':' . $comment;
        if (isset($seenHsUnauth[$key])) {
            $toRemoveNat[] = $id;
            echo "  REMOVE dup hs-unauth return comment=$comment id=$id\n";
        } else {
            $seenHsUnauth[$key] = $id;
        }
        continue;
    }

    // hs-auth wildcard redirect (blank dst-port redirect to 64874) — this incorrectly
    // intercepts all authenticated traffic through the hotspot HTTP proxy, causing
    // "Internet may not be available" for paying customers.
    if ($chain === 'hs-auth' && $action === 'redirect' && $dstPort === '' && $toPorts === '64874') {
        $hsAuthWildcardRedirect[] = $id;
        echo "  FOUND hs-auth wildcard redirect (breaks Internet for paid users) id=$id\n";
    }
}

// Remove duplicate hs-unauth return rules
foreach ($toRemoveNat as $id) {
    try {
        $rm = new RouterOS\Request('/ip/firewall/nat/remove');
        $rm->setArgument('numbers', $id);
        $c->sendSync($rm);
        $removed++;
    } catch (Throwable $e) {
        echo "  WARN remove nat $id: " . $e->getMessage() . "\n";
    }
}
echo "  Removed " . count($toRemoveNat) . " duplicate hs-unauth return rules\n";

// Remove hs-auth wildcard redirect — MikroTik hotspot handles its own auth chain;
// a blank dst-port redirect here intercepts everything and breaks paid Internet.
foreach ($hsAuthWildcardRedirect as $id) {
    try {
        $rm = new RouterOS\Request('/ip/firewall/nat/remove');
        $rm->setArgument('numbers', $id);
        $c->sendSync($rm);
        $fixed++;
        echo "  REMOVED hs-auth wildcard redirect id=$id\n";
    } catch (Throwable $e) {
        echo "  WARN remove hs-auth wildcard $id: " . $e->getMessage() . "\n";
    }
}

// ─────────────────────────────────────────────────────────────────────
// 3. DNS STATIC: ensure pmninternet.net points to hotspot gateway
//    (required for MikroTik captive-portal dns-name resolution)
// ─────────────────────────────────────────────────────────────────────
echo "\n=== 3. DNS static ===\n";

$hotspotGw = '10.0.0.1';
// Get actual hotspot-address from profile
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
    if ((string) $p->getProperty('name') !== 'hsprof2') {
        continue;
    }
    $ha = trim((string) $p->getProperty('hotspot-address'));
    $dn = trim((string) $p->getProperty('dns-name'));
    if ($ha !== '' && $ha !== '0.0.0.0') {
        $hotspotGw = $ha;
    }
    break;
}

// Captive probe hosts that should resolve to hotspot gateway
$captiveProbeHosts = [
    'connectivitycheck.gstatic.com',
    'clients3.google.com',
    'captive.apple.com',
    'www.msftconnecttest.com',
    'msftconnecttest.com',
    'www.msftncsi.com',
    'detectportal.firefox.com',
    'neverssl.com',
    'pmninternet.net',  // hotspot dns-name MUST resolve to hotspot gateway
];

// Hosts that should NOT be overridden (breaks paid-user Internet)
$noDnsOverride = [
    'www.apple.com', 'apple.com',
    'www.google.com', 'google.com',
    'gstatic.com', 'www.gstatic.com',
    'clients1.google.com',
];

$existingDnsStatic = [];
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $s) {
    $nm = strtolower(trim((string) $s->getProperty('name')));
    $id = (string) $s->getProperty('.id');
    $addr = (string) $s->getProperty('address');
    $comment = (string) $s->getProperty('comment');

    if ($nm === '') {
        continue;
    }

    // Remove any broad overrides we shouldn't have
    if (in_array($nm, $noDnsOverride, true) && $addr === $hotspotGw) {
        try {
            $rm = new RouterOS\Request('/ip/dns/static/remove');
            $rm->setArgument('numbers', $id);
            $c->sendSync($rm);
            echo "  REMOVED broad DNS override $nm\n";
            $fixed++;
        } catch (Throwable $e) {
        }
        continue;
    }
    $existingDnsStatic[$nm] = $id;
}

foreach ($captiveProbeHosts as $host) {
    $h = strtolower($host);
    if (in_array($h, $noDnsOverride, true)) {
        continue;
    }
    if (isset($existingDnsStatic[$h])) {
        // Ensure address is correct
        try {
            $set = new RouterOS\Request('/ip/dns/static/set');
            $set->setArgument('numbers', $existingDnsStatic[$h]);
            $set->setArgument('address', $hotspotGw);
            $set->setArgument('ttl', '5m');
            $set->setArgument('comment', 'pamnet-captive-fast');
            $c->sendSync($set);
            echo "  OK dns-static $h -> $hotspotGw\n";
        } catch (Throwable $e) {
        }
    } else {
        try {
            $add = new RouterOS\Request('/ip/dns/static/add');
            $add->setArgument('name', $host);
            $add->setArgument('address', $hotspotGw);
            $add->setArgument('ttl', '5m');
            $add->setArgument('comment', 'pamnet-captive-fast');
            $c->sendSync($add);
            echo "  ADD dns-static $h -> $hotspotGw\n";
            $fixed++;
        } catch (Throwable $e) {
            echo "  WARN add dns-static $h: " . $e->getMessage() . "\n";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// 4. DHCP + DNS speed (idempotent — run again in case stamp was cleared)
// ─────────────────────────────────────────────────────────────────────
echo "\n=== 4. DHCP / DNS speed ===\n";
if (function_exists('pamnet_optimize_hotspot_connect_speed')) {
    $res = pamnet_optimize_hotspot_connect_speed($c);
    echo '  ok=' . ($res['ok'] ? '1' : '0') . "\n";
    foreach (($res['changes'] ?? []) as $ch) {
        echo "  $ch\n";
    }
} else {
    echo "  pamnet_optimize_hotspot_connect_speed not available\n";
}

// ─────────────────────────────────────────────────────────────────────
// 5. Final verification
// ─────────────────────────────────────────────────────────────────────
echo "\n=== VERIFY ===\n";
echo "Walled garden remaining:\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $w) {
    $h = trim((string) $w->getProperty('dst-host'));
    if ($h !== '') {
        echo "  $h\n";
    }
}
echo "DNS static (captive):\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $s) {
    if (strpos((string) $s->getProperty('comment'), 'pamnet-captive') !== false) {
        echo "  " . $s->getProperty('name') . " -> " . $s->getProperty('address') . "\n";
    }
}
echo "hs-auth NAT rules:\n";
foreach ($c->sendSync(new RouterOS\Request('/ip/firewall/nat/print')) as $f) {
    if ((string) $f->getProperty('chain') === 'hs-auth') {
        echo "  chain=hs-auth action=" . $f->getProperty('action')
            . " proto=" . $f->getProperty('protocol')
            . " dst-port=" . $f->getProperty('dst-port')
            . " to-ports=" . $f->getProperty('to-ports') . "\n";
    }
}
echo "\nSummary: removed=$removed fixed=$fixed\n";
echo "DONE\n";
