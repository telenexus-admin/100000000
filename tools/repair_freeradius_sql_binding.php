<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
error_reporting(E_ALL);

function rr_out(string $message): void
{
    fwrite(STDOUT, '[radius-sql-repair] ' . $message . PHP_EOL);
}

function rr_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, '[radius-sql-repair] FAILED ' . preg_replace('/[\r\n]+/', ' ', $message) . PHP_EOL);
    exit($code);
}

if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
    rr_fail('Run with sudo/root.');
}

$nasIp = trim((string)($argv[1] ?? ''));
if (!filter_var($nasIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    rr_fail('Usage: sudo php tools/repair_freeradius_sql_binding.php NAS_IPV4', 2);
}

$root = dirname(__DIR__);
$configPath = $root . '/config.php';
$sqlPath = '/etc/freeradius/3.0/mods-available/sql';
$enabledSqlPath = '/etc/freeradius/3.0/mods-enabled/sql';

if (!is_file($configPath)) {
    rr_fail('Application config.php not found.');
}
if (!is_file($sqlPath)) {
    rr_fail('FreeRADIUS SQL module not found at ' . $sqlPath);
}

require $configPath;

if (isset($db_password) && $db_password !== '' && (!isset($db_pass) || $db_pass === '')) {
    $db_pass = $db_password;
}
$dbPass = isset($db_pass) ? (string)$db_pass : '';

$radiusHost = !empty($radius_host) ? (string)$radius_host : (string)$db_host;
$radiusName = !empty($radius_name) ? (string)$radius_name : (string)$db_name;
$radiusUser = !empty($radius_user) ? (string)$radius_user : (string)$db_user;
if (!empty($radius_password)) {
    $radiusPass = (string)$radius_password;
} elseif (isset($radius_pass) && $radius_pass !== '') {
    $radiusPass = (string)$radius_pass;
} else {
    $radiusPass = $dbPass;
}

foreach ([
    'host' => $radiusHost,
    'database' => $radiusName,
    'user' => $radiusUser,
] as $label => $value) {
    if ($value === '' || preg_match('/[\r\n]/', $value)) {
        rr_fail('Invalid application RADIUS ' . $label . ' setting.');
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$radiusHost};dbname={$radiusName};charset=utf8mb4",
        $radiusUser,
        $radiusPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );
    $stmt = $pdo->prepare('SELECT id,nasname,shortname,LENGTH(secret) AS secret_len FROM nas WHERE nasname = ? LIMIT 1');
    $stmt->execute([$nasIp]);
    $nas = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$nas) {
        rr_fail('Application RADIUS database has no NAS row for ' . $nasIp . '.');
    }
    if ((int)($nas['secret_len'] ?? 0) < 16) {
        rr_fail('NAS row exists but its shared secret is missing/too short.');
    }
} catch (Throwable $e) {
    rr_fail('Could not verify application RADIUS database: ' . $e->getMessage());
}

rr_out('application_radius_db=' . $radiusName . ' host=' . $radiusHost . ' nas=' . $nasIp . ' nas_row=present secret=kept-private');

$original = file_get_contents($sqlPath);
if ($original === false || trim($original) === '') {
    rr_fail('Could not read FreeRADIUS SQL module.');
}

$quote = static function (string $value): string {
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
};

$replaceSetting = static function (string $text, string $name, string $value, bool $required = true): string {
    $pattern = '/(?m)^(\s*)#?\s*' . preg_quote($name, '/') . '\s*=.*$/';
    if (preg_match($pattern, $text)) {
        return preg_replace($pattern, '$1' . $name . ' = ' . $value, $text, 1);
    }
    if ($required) {
        throw new RuntimeException('FreeRADIUS SQL setting not found: ' . $name);
    }
    return $text;
};

$replaceDatabaseSetting = static function (string $text, string $value) use ($replaceSetting): string {
    if (preg_match('/(?m)^\s*#?\s*radius_db\s*=/', $text)) {
        return $replaceSetting($text, 'radius_db', $value);
    }
    if (preg_match('/(?m)^\s*#?\s*database\s*=/', $text)) {
        return $replaceSetting($text, 'database', $value);
    }
    throw new RuntimeException('FreeRADIUS SQL setting not found: radius_db/database');
};

try {
    $updated = $original;
    $updated = $replaceSetting($updated, 'driver', '"rlm_sql_mysql"');
    $updated = $replaceSetting($updated, 'dialect', '"mysql"', false);
    $updated = $replaceSetting($updated, 'server', $quote($radiusHost));
    $updated = $replaceSetting($updated, 'login', $quote($radiusUser));
    $updated = $replaceSetting($updated, 'password', $quote($radiusPass));
    $updated = $replaceDatabaseSetting($updated, $quote($radiusName));
    $updated = $replaceSetting($updated, 'read_clients', 'yes');
    $updated = $replaceSetting($updated, 'client_table', '"nas"', false);
} catch (Throwable $e) {
    rr_fail($e->getMessage());
}

$backup = '/root/freeradius-sql-before-binding-' . date('Ymd-His') . '.conf';
if (!copy($sqlPath, $backup)) {
    rr_fail('Could not create backup ' . $backup);
}
if (file_put_contents($sqlPath, $updated) === false) {
    rr_fail('Could not write FreeRADIUS SQL module.');
}
@chown($sqlPath, 'root');
@chgrp($sqlPath, 'freerad');
@chmod($sqlPath, 0640);

if (!is_link($enabledSqlPath) && !is_file($enabledSqlPath)) {
    if (!@symlink('../mods-available/sql', $enabledSqlPath)) {
        copy($backup, $sqlPath);
        rr_fail('SQL module is not enabled and could not be enabled.');
    }
}

$checkLog = '/tmp/radius-sql-binding-check.log';
exec('timeout 30s freeradius -XC > ' . escapeshellarg($checkLog) . ' 2>&1', $out, $code);
if ($code !== 0) {
    copy($backup, $sqlPath);
    rr_fail('FreeRADIUS validation failed; original SQL module restored. See ' . $checkLog);
}

exec('systemctl restart freeradius 2>&1', $restartOut, $restartCode);
if ($restartCode !== 0) {
    copy($backup, $sqlPath);
    exec('systemctl restart freeradius >/dev/null 2>&1');
    rr_fail('FreeRADIUS restart failed; original SQL module restored.');
}

exec('systemctl is-active freeradius 2>&1', $activeOut, $activeCode);
if ($activeCode !== 0 || trim(implode("\n", $activeOut)) !== 'active') {
    rr_fail('FreeRADIUS did not remain active after SQL binding repair.');
}

rr_out('freeradius_sql_db=' . $radiusName . ' read_clients=yes client_table=nas');
rr_out('backup=' . $backup);
rr_out('RESULT=FREERADIUS_SQL_BOUND_TO_APPLICATION_RADIUS_DB');
rr_out('NEXT=reset MikroTik RADIUS counters and trigger one login');
