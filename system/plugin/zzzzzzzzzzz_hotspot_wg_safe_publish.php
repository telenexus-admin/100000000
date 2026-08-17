<?php

/**
 * WireGuard-safe Hotspot portal publisher.
 *
 * - package/config saves queue publication instead of blocking the web request;
 * - RouterOS fetches login.html over the management WireGuard tunnel when possible;
 * - the existing login.html is kept until a complete replacement file is verified;
 * - each stage is logged so failures are diagnosable without exposing credentials.
 */

use PEAR2\Net\RouterOS;

$rs11Route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($rs11Route === 'plugin/rs10_hotspot_plan_add_post') {
    $_GET['_route'] = 'plugin/rs11_hotspot_plan_add_post';
} elseif ($rs11Route === 'plugin/rs10_hotspot_plan_edit_post') {
    $_GET['_route'] = 'plugin/rs11_hotspot_plan_edit_post';
} elseif ($rs11Route === 'plugin/rs10_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs11_mikrotik_configurator_config_process';
}

function rs11_publish_log($routerId, $stage, $message = '')
{
    $line = '[hotspot-safe-publish] router=' . (int) $routerId
        . ' stage=' . preg_replace('/[^a-z0-9 _.-]/i', '', (string) $stage);
    if ($message !== '') {
        $line .= ' message=' . preg_replace('/[\r\n]+/', ' ', (string) $message);
    }
    error_log($line);
    if (PHP_SAPI === 'cli') {
        echo $line . PHP_EOL;
    }
}

function rs11_router_fetch_url($router, $billingUrl, $path)
{
    $billingUrl = rtrim((string) $billingUrl, '/');
    $path = '/' . ltrim((string) $path, '/');
    $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));

    if ($transport === 'wireguard' && class_exists('RSWireguardControlPlane')
        && method_exists('RSWireguardControlPlane', 'publicConfig')) {
        try {
            $wg = RSWireguardControlPlane::publicConfig();
            $serverIp = trim((string) ($wg['server_ip'] ?? ''));
            if (filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = parse_url($billingUrl);
                $port = is_array($parts) && isset($parts['port']) ? (int) $parts['port'] : 80;
                if ($port < 1 || $port > 65535) {
                    $port = 80;
                }
                // Management publication is intentionally plain HTTP over the
                // encrypted WireGuard tunnel. Customer API traffic still uses APP_URL.
                return 'http://' . $serverIp . ':' . $port . $path;
            }
        } catch (Throwable $ignored) {
        }
    }

    return $billingUrl . $path;
}

function rs11_find_router_file($client, $name)
{
    $found = [];
    $request = new RouterOS\Request('/file/print');
    if (class_exists('PEAR2\\Net\\RouterOS\\Query')) {
        $request->setQuery(RouterOS\Query::where('name', (string) $name));
    }
    foreach ($client->sendSync($request) as $row) {
        if ((string) $row->getProperty('name') !== (string) $name) {
            continue;
        }
        $found[] = [
            'id' => trim((string) $row->getProperty('.id')),
            'size' => trim((string) $row->getProperty('size')),
        ];
    }
    return $found;
}

function rs11_safe_upload_login($client, $sourceUrl, $htmlDirectory)
{
    $directory = trim((string) $htmlDirectory, "/\\");
    if ($directory === '' || !preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $directory) || strpos($directory, '..') !== false) {
        throw new RuntimeException('Invalid Hotspot HTML directory.');
    }

    $destination = $directory . '/login.html';
    $temporary = $directory . '/rs-login-' . substr(sha1($sourceUrl . '|' . microtime(true)), 0, 12) . '.html';

    $fetch = new RouterOS\Request('/tool/fetch');
    $fetch->setArgument('url', (string) $sourceUrl)
        ->setArgument('dst-path', $temporary)
        ->setArgument('keep-result', 'yes');
    if (stripos((string) $sourceUrl, 'https://') === 0) {
        $fetch->setArgument('mode', 'https')
            ->setArgument('check-certificate', 'no');
    } else {
        $fetch->setArgument('mode', 'http');
    }
    $client->sendSync($fetch);
    usleep(350000);

    $tmpFiles = rs11_find_router_file($client, $temporary);
    if (!$tmpFiles || empty($tmpFiles[0]['id'])) {
        throw new RuntimeException('RouterOS fetch finished but the temporary portal file was not created.');
    }
    $tmpId = $tmpFiles[0]['id'];
    $tmpSize = $tmpFiles[0]['size'];
    if ($tmpSize === '0' || $tmpSize === '') {
        throw new RuntimeException('RouterOS downloaded an empty temporary portal file.');
    }

    // Keep the old live page until the replacement has been fully downloaded.
    foreach (rs11_find_router_file($client, $destination) as $old) {
        if ($old['id'] === '') continue;
        $remove = new RouterOS\Request('/file/remove');
        $remove->setArgument('numbers', $old['id']);
        $client->sendSync($remove);
    }

    $rename = new RouterOS\Request('/file/set');
    $rename->setArgument('numbers', $tmpId)
        ->setArgument('name', $destination);
    $client->sendSync($rename);
    usleep(150000);

    $live = rs11_find_router_file($client, $destination);
    if (!$live) {
        throw new RuntimeException('RouterOS downloaded the portal but could not promote it to login.html.');
    }

    return ['path' => $destination, 'size' => $live[0]['size'] ?: $tmpSize, 'source' => $sourceUrl];
}

function rs11_publish_router_portal($routerId, $reason = 'sync')
{
    $routerId = (int) $routerId;
    if ($routerId <= 0) {
        throw new InvalidArgumentException('Router id is required.');
    }

    $stage = 'loading router';
    try {
        rs11_publish_log($routerId, $stage);
        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        if (!$router) {
            throw new RuntimeException('Router not found.');
        }
        $routerName = trim((string) ($router['name'] ?? ''));
        if ($routerName === '') {
            throw new RuntimeException('Router name is empty.');
        }

        $stage = 'preparing portal configuration';
        rs11_publish_log($routerId, $stage);
        $billing = function_exists('rs8_billing_url')
            ? rs8_billing_url()
            : (defined('APP_URL') ? rtrim((string) APP_URL, '/') : '');
        if ($billing === '') {
            throw new RuntimeException('Billing URL is not configured.');
        }
        if (function_exists('rs10_appconfig_set')) {
            rs10_appconfig_set('router_id', (string) $routerId);
            rs10_appconfig_set('router_name', $routerName);
            rs10_appconfig_set('hotspot_billing_url', $billing);
        }

        $stage = 'generating login html';
        rs11_publish_log($routerId, $stage);
        if (!function_exists('hotspot_settings_generate_login_html')
            || !function_exists('hotspot_settings_store_login_html')) {
            throw new RuntimeException('Hotspot page generator is unavailable.');
        }
        $html = hotspot_settings_generate_login_html();
        if (function_exists('rs8_patch_portal_html')) {
            $html = rs8_patch_portal_html($html, $billing, $routerId);
        }
        if (function_exists('rs10_embed_packages')) {
            $html = rs10_embed_packages($html, $routerId);
        }
        $stored = hotspot_settings_store_login_html($html, $billing);

        $stage = 'connecting router api';
        rs11_publish_log($routerId, $stage);
        if (!function_exists('rs_mikrotik_configurator_client')) {
            throw new RuntimeException('RouterOS management client helper is unavailable.');
        }
        $client = rs_mikrotik_configurator_client($router, 4);
        $client->sendSync(new RouterOS\Request('/system/identity/print'));

        if (function_exists('rs10_router_login_url') && function_exists('rs10_appconfig_set')) {
            $loginUrl = rs10_router_login_url($client);
            if ($loginUrl !== '') {
                rs10_appconfig_set('hotspot_login_url', $loginUrl);
            }
        }

        $stage = 'preparing billing walled garden';
        rs11_publish_log($routerId, $stage);
        if (function_exists('rs8_remove_legacy_billing_walled_garden')) {
            rs8_remove_legacy_billing_walled_garden($client);
        }
        if (function_exists('rs8_ensure_billing_ip_walled_garden')) {
            rs8_ensure_billing_ip_walled_garden($client, $billing);
        }

        $directory = function_exists('hotspot_settings_html_directory')
            ? hotspot_settings_html_directory($client)
            : 'hotspot';
        $fetchUrl = rs11_router_fetch_url(
            $router,
            $billing,
            '/hotspot_login.html?_plans=' . rawurlencode((string) time())
        );

        $stage = 'uploading login html';
        rs11_publish_log($routerId, $stage, 'source=' . preg_replace('#\?.*$#', '', $fetchUrl));
        $uploaded = rs11_safe_upload_login($client, $fetchUrl, $directory);

        $stage = 'finalizing billing walled garden';
        rs11_publish_log($routerId, $stage);
        if (function_exists('rs8_remove_legacy_billing_walled_garden')) {
            rs8_remove_legacy_billing_walled_garden($client);
        }
        if (function_exists('rs8_ensure_billing_ip_walled_garden')) {
            rs8_ensure_billing_ip_walled_garden($client, $billing);
        }

        $stage = 'complete';
        rs11_publish_log($routerId, $stage, 'reason=' . $reason . ' path=' . $uploaded['path'] . ' size=' . $uploaded['size']);
        return $uploaded;
    } catch (Throwable $e) {
        rs11_publish_log($routerId, 'failed ' . $stage, $e->getMessage());
        throw $e;
    }
}

function rs11_publish_worker_path()
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'publish_hotspot_portal.php';
}

function rs11_queue_router_publish($routerId, $reason = 'sync')
{
    $routerId = (int) $routerId;
    if ($routerId <= 0) return false;
    $worker = rs11_publish_worker_path();
    if (!is_file($worker)) return false;

    $cacheDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $log = $cacheDir . DIRECTORY_SEPARATOR . 'hotspot_publish_' . $routerId . '.log';
    $php = PHP_BINARY ?: 'php';
    $cmd = 'nohup ' . escapeshellarg($php)
        . ' ' . escapeshellarg($worker)
        . ' ' . escapeshellarg((string) $routerId)
        . ' ' . escapeshellarg((string) $reason)
        . ' >> ' . escapeshellarg($log) . ' 2>&1 < /dev/null &';
    @exec($cmd);
    return true;
}

function rs11_hotspot_plan_add_post()
{
    $rid = function_exists('rs10_post_router_id') ? rs10_post_router_id() : 0;
    if ($rid > 0) {
        register_shutdown_function('rs11_queue_router_publish', $rid, 'package-add');
    }
    rs4_hotspot_plan_add_post();
}

function rs11_hotspot_plan_edit_post()
{
    $rid = function_exists('rs10_post_router_id') ? rs10_post_router_id() : 0;
    if ($rid > 0) {
        register_shutdown_function('rs11_queue_router_publish', $rid, 'package-edit');
    }
    rs4_hotspot_plan_edit_post();
}

function rs11_mikrotik_configurator_config_process()
{
    $rid = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    if ($rid > 0) {
        register_shutdown_function('rs11_queue_router_publish', $rid, 'router-config');
    }
    rs8_mikrotik_configurator_config_process();
}
