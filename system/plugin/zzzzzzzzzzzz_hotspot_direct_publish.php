<?php

/**
 * Route package/config publication through the v2 direct-destination worker.
 *
 * The previous worker relied on `/file set name=path/login.html` to promote a
 * temporary RouterOS file. Some RouterOS builds do not move a file into a
 * directory that way. This layer queues the v2 worker, which verifies a probe
 * download and then fetches directly to the final Hotspot login.html path.
 */

$rs12Route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($rs12Route === 'plugin/rs11_hotspot_plan_add_post') {
    $_GET['_route'] = 'plugin/rs12_hotspot_plan_add_post';
} elseif ($rs12Route === 'plugin/rs11_hotspot_plan_edit_post') {
    $_GET['_route'] = 'plugin/rs12_hotspot_plan_edit_post';
} elseif ($rs12Route === 'plugin/rs11_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs12_mikrotik_configurator_config_process';
}

function rs12_publish_worker_path()
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'publish_hotspot_portal_v2.php';
}

function rs12_queue_router_publish($routerId, $reason = 'sync')
{
    $routerId = (int) $routerId;
    if ($routerId <= 0) {
        return false;
    }

    $worker = rs12_publish_worker_path();
    if (!is_file($worker)) {
        error_log('[hotspot-publish-v2] worker missing router=' . $routerId);
        return false;
    }

    $cacheDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $log = $cacheDir . DIRECTORY_SEPARATOR . 'hotspot_publish_v2_' . $routerId . '.log';
    $php = PHP_BINARY ?: 'php';
    $safeReason = preg_replace('/[^a-z0-9_-]/i', '', (string) $reason) ?: 'sync';
    $cmd = 'nohup ' . escapeshellarg($php)
        . ' ' . escapeshellarg($worker)
        . ' ' . escapeshellarg((string) $routerId)
        . ' ' . escapeshellarg($safeReason)
        . ' >> ' . escapeshellarg($log) . ' 2>&1 < /dev/null &';
    @exec($cmd);
    return true;
}

function rs12_hotspot_plan_add_post()
{
    $rid = function_exists('rs10_post_router_id') ? rs10_post_router_id() : 0;
    if ($rid > 0) {
        register_shutdown_function('rs12_queue_router_publish', $rid, 'package-add');
    }
    rs4_hotspot_plan_add_post();
}

function rs12_hotspot_plan_edit_post()
{
    $rid = function_exists('rs10_post_router_id') ? rs10_post_router_id() : 0;
    if ($rid > 0) {
        register_shutdown_function('rs12_queue_router_publish', $rid, 'package-edit');
    }
    rs4_hotspot_plan_edit_post();
}

function rs12_mikrotik_configurator_config_process()
{
    $rid = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    if ($rid > 0) {
        register_shutdown_function('rs12_queue_router_publish', $rid, 'router-config');
    }
    rs8_mikrotik_configurator_config_process();
}
