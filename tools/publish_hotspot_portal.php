<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . DIRECTORY_SEPARATOR . 'init.php';

$routerId = isset($argv[1]) ? (int) $argv[1] : 0;
$reason = isset($argv[2]) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $argv[2]) : 'manual';
if ($routerId <= 0) {
    fwrite(STDERR, "Usage: php tools/publish_hotspot_portal.php ROUTER_ID [reason]\n");
    exit(2);
}
if (!function_exists('rs11_publish_router_portal')) {
    fwrite(STDERR, "Safe Hotspot publisher plugin is not loaded.\n");
    exit(3);
}

try {
    @set_time_limit(45);
    rs11_publish_router_portal($routerId, $reason ?: 'manual');
    echo "HOTSPOT_PORTAL_PUBLISH_OK router={$routerId}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HOTSPOT_PORTAL_PUBLISH_FAILED router=' . $routerId . ' error=' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()) . "\n");
    exit(1);
}
