<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

function rs_publish_cli_line($message)
{
    $line = '[hotspot-publish-worker] ' . trim((string) $message) . PHP_EOL;
    @fwrite(STDOUT, $line);
    @fflush(STDOUT);
}

register_shutdown_function(function () {
    $last = error_get_last();
    if (is_array($last) && in_array((int) ($last['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $msg = preg_replace('/[\r\n]+/', ' ', (string) ($last['message'] ?? 'fatal error'));
        @fwrite(STDERR, '[hotspot-publish-worker] FATAL ' . $msg . PHP_EOL);
        @fflush(STDERR);
    }
});

$routerId = isset($argv[1]) ? (int) $argv[1] : 0;
$reason = isset($argv[2]) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $argv[2]) : 'manual';
if ($routerId <= 0) {
    fwrite(STDERR, "Usage: php tools/publish_hotspot_portal.php ROUTER_ID [reason]\n");
    exit(2);
}

$root = dirname(__DIR__);
rs_publish_cli_line('BOOT start router=' . $routerId);
rs_publish_cli_line('BOOT loading init.php');

// This application contains direct-execution guards which inspect
// $_SERVER['SCRIPT_FILENAME']. A normal standalone CLI script populates that
// value, while the proven stdin bootstrap (`php <<PHP`) does not. Temporarily
// remove it only while loading init.php so CLI boot behaves like the working
// stdin path; restore it immediately afterwards for normal diagnostics.
$hadScriptFilename = array_key_exists('SCRIPT_FILENAME', $_SERVER);
$originalScriptFilename = $hadScriptFilename ? $_SERVER['SCRIPT_FILENAME'] : null;
unset($_SERVER['SCRIPT_FILENAME']);
require $root . DIRECTORY_SEPARATOR . 'init.php';
if ($hadScriptFilename) {
    $_SERVER['SCRIPT_FILENAME'] = $originalScriptFilename;
}
rs_publish_cli_line('BOOT init.php loaded');

if (!function_exists('rs11_publish_router_portal')) {
    fwrite(STDERR, "Safe Hotspot publisher plugin is not loaded.\n");
    exit(3);
}

try {
    @set_time_limit(45);
    rs_publish_cli_line('RUN publisher');
    rs11_publish_router_portal($routerId, $reason ?: 'manual');
    rs_publish_cli_line('DONE router=' . $routerId);
    echo "HOTSPOT_PORTAL_PUBLISH_OK router={$routerId}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'HOTSPOT_PORTAL_PUBLISH_FAILED router=' . $routerId . ' error=' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()) . "\n");
    exit(1);
}
