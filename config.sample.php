<?php

// Optional: set full public base URL (e.g. https://billing.example.com/subdir) — avoids guessing under CLI/cron or proxies.
$_app_url_env = getenv('APP_URL');
if (is_string($_app_url_env) && $_app_url_env !== '') {
    define('APP_URL', rtrim($_app_url_env, "/\\"));
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strcasecmp((string) $_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0);
    $protocol = $isHttps ? 'https://' : 'http://';

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    $baseDir = '';
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
        $baseDir = rtrim(dirname((string) $_SERVER['SCRIPT_NAME']), '/\\');
        if ($baseDir !== '' && strpos($baseDir, ':') === false) {
            $baseNorm = trim(str_replace('\\', '/', $baseDir), '/');
            $baseDir = $baseNorm === '' ? '' : '/' . $baseNorm;
        } else {
            // Filesystem-style path (e.g. Windows CLI) — do not append to host
            $baseDir = '';
        }
    }

    define('APP_URL', $protocol . $host . $baseDir);
}


// Live = production. demo = demo installs (see app_is_demo_restricted() in init.php).
$_app_stage = 'Live';

$db_host    = "localhost"; # Database Host
$db_port    = "";   # Database port (e.g. 3306). Leave blank for default.
$db_unix_socket = ""; # e.g. /var/run/mysqld/mysqld.sock — use instead of host/port when DB is local
$db_user    = "root"; # Database Username
$db_pass    = ""; # Database Password
$db_name    = "allxsys"; # Database Name

# Cache tbl_appconfig in system/cache to cut database reads on every request (seconds). 0 = always load from DB.
$appconfig_cache_ttl = 120;

# API browser CORS: set to your exact frontend origin in production, e.g. https://portal.example.com
# Use * only for development (allows any site to call the JSON API from JavaScript).
$api_cors_allow_origin = '*';




//error reporting
if ($_app_stage != 'Live') {
    error_reporting(E_ERROR);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ERROR);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}
