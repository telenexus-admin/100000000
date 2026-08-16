<?php

// Safe CLI-only health probe: reports database identity and table availability,
// never credentials or NAS secrets.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = realpath(__DIR__ . '/../..');
if (!$root) {
    fwrite(STDERR, "Application root not found.\n");
    exit(1);
}
chdir($root);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

try {
    require $root . '/init.php';
    $db = ORM::get_db('radius');
    $identity = $db->query('SELECT DATABASE() AS db_name, @@hostname AS db_host')->fetch(PDO::FETCH_ASSOC);
    $count = (int)$db->query('SELECT COUNT(*) FROM nas')->fetchColumn();
    echo json_encode([
        'ok' => true,
        'database' => (string)($identity['db_name'] ?? ''),
        'database_host' => (string)($identity['db_host'] ?? ''),
        'nas_table' => true,
        'nas_count' => $count,
        'radius_enable' => !empty($config['radius_enable']),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
