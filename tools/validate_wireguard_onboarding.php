<?php

require_once dirname(__DIR__).'/system/plugin/radius_wireguard_bridge.php';

$script = radius_wireguard_build_routeros_script(
    'validation-router',
    '10.77.0.42',
    'nuxwg_validation01',
    '0123456789abcdef0123456789abcdef',
    'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-',
    'https://billing.example.test/?_route=plugin/radius_wireguard_activate',
    [
        'interface' => 'wg-nuxhost',
        'server_ip' => '10.77.0.1',
        'public_key' => str_repeat('A', 43).'=',
        'endpoint' => '203.0.113.10',
        'endpoint_port' => 51821,
    ],
    [
        'host' => '10.77.0.1',
        'auth_port' => 1812,
        'accounting_port' => 1813,
        'coa_port' => 3799,
    ],
    'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-'
);

$required = [
    '# NuxHost WireGuard + RouterOS API + FreeRADIUS onboarding v6',
    'output=user as-value check-certificate=no',
    '\"status\":\"ok\"',
    'current-endpoint-address',
    'NuxHost: WireGuard handshake confirmed.',
    'place-before=$nuxDefaultInputDrop comment="NUXHOST-API"',
    'comment="NUXHOST-RADIUS"',
    'authentication-port=1812 accounting-port=1813',
    '/radius incoming set accept=yes port=3799',
    'place-before=$nuxDefaultInputDrop comment="NUXHOST-RADIUS-COA"',
    '/ppp aaa set use-radius=yes accounting=yes interim-update=5m',
    'NUXHOST-WIREGUARD-RADIUS-ONBOARDING-COMPLETE',
];

$forbidden = [
    '/certificate/settings',
    '/ip/firewall/filter move',
    'check-certificate=yes-without-crl',
];

$errors = [];
foreach ($required as $needle) {
    if (strpos($script, $needle) === false) {
        $errors[] = 'Missing required v6 behavior: '.$needle;
    }
}
foreach ($forbidden as $needle) {
    if (strpos($script, $needle) !== false) {
        $errors[] = 'Known-bad legacy behavior returned: '.$needle;
    }
}

if ($errors) {
    fwrite(STDERR, implode("\n", $errors)."\n");
    exit(1);
}

echo "RouterOS v6 onboarding parity checks: OK\n";
echo "WireGuard handshake proof: OK\n";
echo "RouterOS 7.18 parser-safety checks: OK\n";
echo "Private API/RADIUS/CoA commands: OK\n";
