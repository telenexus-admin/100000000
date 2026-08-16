<?php

/**
 * Bootstrap helpers for high-traffic deployments:
 * - Build PDO MySQL DSN (port / unix socket)
 * - Optional file cache for tbl_appconfig with lock to reduce stampede on expiry
 */

function antiqua_mysql_dsn($host, $dbname, $port = '', $unix_socket = '')
{
    if ($unix_socket !== null && $unix_socket !== '') {
        return 'mysql:unix_socket=' . $unix_socket . ';dbname=' . $dbname . ';charset=utf8mb4';
    }
    $dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4';
    if ($port !== null && $port !== '') {
        $dsn .= ';port=' . (int) $port;
    }
    return $dsn;
}

function antiqua_appconfig_runtime_cache_path($cache_path)
{
    return rtrim($cache_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'appconfig.runtime.php';
}

function antiqua_appconfig_cache_read($path, $ttl)
{
    if ($ttl < 1 || !is_readable($path)) {
        return null;
    }
    $mtime = filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttl) {
        return null;
    }
    $data = @include $path;
    return is_array($data) ? $data : null;
}

function antiqua_appconfig_cache_write($path, array $data)
{
    $tmp = $path . '.tmp.' . getmypid();
    $export = "<?php\nreturn " . var_export($data, true) . ";\n";
    if (file_put_contents($tmp, $export, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0660);
    return @rename($tmp, $path);
}

function antiqua_appconfig_load_from_db()
{
    $config = array();
    $result = ORM::for_table('tbl_appconfig')->find_many();
    foreach ($result as $value) {
        $config[$value['setting']] = $value['value'];
    }
    return $config;
}

/**
 * Load key/value app settings from cache or database.
 *
 * @param string $cache_path system/cache absolute path
 * @param int    $ttl        seconds; 0 disables cache (always hits DB)
 * @return array
 */
function antiqua_appconfig_bootstrap($cache_path, $ttl)
{
    $path = antiqua_appconfig_runtime_cache_path($cache_path);
    $data = antiqua_appconfig_cache_read($path, (int) $ttl);
    if ($data !== null) {
        return $data;
    }
    $lockPath = $path . '.lock';
    $lock = @fopen($lockPath, 'cb+');
    if ($lock !== false) {
        if (flock($lock, LOCK_EX)) {
            try {
                $data = antiqua_appconfig_cache_read($path, (int) $ttl);
                if ($data !== null) {
                    return $data;
                }
                $config = antiqua_appconfig_load_from_db();
                antiqua_appconfig_cache_write($path, $config);
                return $config;
            } finally {
                flock($lock, LOCK_UN);
            }
        }
        fclose($lock);
    }
    return antiqua_appconfig_load_from_db();
}

function antiqua_appconfig_cache_invalidate($cache_path)
{
    $path = antiqua_appconfig_runtime_cache_path($cache_path);
    @unlink($path);
    @unlink($path . '.lock');
}
