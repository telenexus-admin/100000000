<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$selector = trim((string) ($argv[1] ?? ''));
if (!filter_var($selector, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    fwrite(STDERR, "Usage: php tools/revive_radius_by_tunnel.php TUNNEL_IPV4\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . '/config.php';

if (isset($db_password) && $db_password !== '' && (!isset($db_pass) || $db_pass === '')) {
    $db_pass = $db_password;
}
$db_pass = isset($db_pass) ? (string) $db_pass : '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );

    $stmt = $pdo->prepare(
        'SELECT id, name, ip_address, wg_tunnel_ip FROM tbl_routers WHERE wg_tunnel_ip = ? OR ip_address = ? ORDER BY id ASC'
    );
    $stmt->execute([$selector, $selector]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) === 0) {
        fwrite(STDERR, "[radius-revive] FAILED No router record matches tunnel {$selector}.\n");
        exit(1);
    }
    if (count($rows) > 1) {
        fwrite(STDERR, "[radius-revive] FAILED More than one router record matches tunnel {$selector}; refusing ambiguous repair.\n");
        foreach ($rows as $row) {
            fwrite(STDERR, '[radius-revive] candidate id=' . (int) $row['id'] . ' name=' . trim((string) $row['name']) . "\n");
        }
        exit(1);
    }

    $row = $rows[0];
    $resolvedId = (int) $row['id'];
    fwrite(STDOUT, '[radius-revive] resolved tunnel=' . $selector . ' router_id=' . $resolvedId . ' name=' . trim((string) $row['name']) . "\n");
    fflush(STDOUT);

    // Reuse the authoritative repair implementation after resolving the real DB id.
    $argv[1] = (string) $resolvedId;
    require __DIR__ . '/revive_radius_router.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[radius-revive] FAILED ' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()) . "\n");
    exit(1);
}
