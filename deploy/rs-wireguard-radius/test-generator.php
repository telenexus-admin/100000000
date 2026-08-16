<?php
if (PHP_SAPI !== 'cli') { exit(1); }
if (!function_exists('register_menu')) {
    function register_menu(...$args) {}
}
$root = realpath(__DIR__ . '/../..');
require $root . '/system/autoload/RSWireguardControlPlane.php';
require $root . '/system/plugin/rs_radius_wireguard_onboarding.php';

$wg = [
    'interface' => 'wg-rs',
    'server_ip' => '10.78.0.1',
    'cidr' => '10.78.0.0/24',
    'public_key' => str_repeat('A', 43) . '=',
    'endpoint' => '203.0.113.10',
    'endpoint_port' => 51822,
];
$radius = ['host' => '10.78.0.1', 'auth_port' => 1812, 'accounting_port' => 1813, 'coa_port' => 3799];
$script = rs_wg_build_routeros_script(
    'TEST', '10.78.0.2', 'rswg_test', str_repeat('b', 32), str_repeat('T', 48),
    'https://example.test/?_route=plugin/rs_radius_wireguard_activate',
    $wg, $radius, str_repeat('S', 43)
);
$required = [
    '# RS WireGuard + RouterOS API + FreeRADIUS onboarding v6',
    'address="10.78.0.2/24"',
    'output=user as-value check-certificate=no',
    'current-endpoint-address',
    'service=hotspot,ppp',
    'authentication-port=1812 accounting-port=1813',
    '/radius incoming set accept=yes port=3799',
    'RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE',
];
foreach ($required as $needle) {
    if (strpos($script, $needle) === false) {
        fwrite(STDERR, "Generated script missing invariant: {$needle}\n");
        exit(2);
    }
}
if (strpos($script, '/certificate/settings') !== false || preg_match('/\/ip\/firewall\/filter\s+move/', $script)) {
    fwrite(STDERR, "Generated script contains a known RouterOS 7.18 failure pattern.\n");
    exit(3);
}
echo "Generated RouterOS v6 script: PASS\n";
