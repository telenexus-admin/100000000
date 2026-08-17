<?php

/**
 * WireGuard router liveness correction.
 *
 * The legacy router monitor treats RouterOS API TCP/8728 availability as the
 * definition of router liveness. For WireGuard-managed routers that produces
 * false Offline/Unreachable states when the tunnel/router is reachable but the
 * API service is busy, restricted or temporarily unavailable.
 *
 * This layer only heals a false-negative state. It never marks a router
 * Offline. A WireGuard router that answers ICMP on its assigned tunnel IP is
 * considered network-reachable and may remain Online even when API/8728 is not.
 */

function rs14_exec_available()
{
    if (!function_exists('exec')) {
        return false;
    }

    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    return !in_array('exec', $disabled, true);
}

function rs14_ping_binary()
{
    foreach (['/usr/bin/ping', '/bin/ping'] as $binary) {
        if (is_file($binary) && is_executable($binary)) {
            return $binary;
        }
    }
    return '';
}

/**
 * Ping validated WireGuard IPs concurrently, so a dashboard with several
 * genuinely-offline routers does not wait one second per router.
 *
 * @return array<int,bool> router id => reachable
 */
function rs14_ping_wireguard_routers(array $routers)
{
    $reachable = [];
    if (!$routers || !rs14_exec_available()) {
        return $reachable;
    }

    $ping = rs14_ping_binary();
    if ($ping === '') {
        return $reachable;
    }

    // Small batches avoid creating an excessive number of processes on a
    // large ISP installation while keeping total wait time low.
    foreach (array_chunk($routers, 32, true) as $batch) {
        $commands = [];
        foreach ($batch as $routerId => $ip) {
            $routerId = (int) $routerId;
            $ip = trim((string) $ip);
            if ($routerId <= 0 || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }

            $marker = 'RS14_' . $routerId;
            $commands[] = '( ' . escapeshellarg($ping)
                . ' -n -c 1 -W 1 ' . escapeshellarg($ip)
                . ' >/dev/null 2>&1 && printf ' . escapeshellarg("%s\n") . ' ' . escapeshellarg($marker)
                . ' ) &';
        }

        if (!$commands) {
            continue;
        }

        $output = [];
        $status = 1;
        @exec(implode(' ', $commands) . ' wait', $output, $status);

        foreach ($output as $line) {
            if (preg_match('/^RS14_(\d+)$/', trim((string) $line), $m)) {
                $reachable[(int) $m[1]] = true;
            }
        }
    }

    return $reachable;
}

function rs14_heal_wireguard_router_statuses($source = 'runtime')
{
    global $CACHE_PATH;

    try {
        $rows = ORM::for_table('tbl_routers')
            ->where('enabled', '1')
            ->where('management_transport', 'wireguard')
            ->find_many();

        $candidates = [];
        $models = [];
        foreach ($rows as $router) {
            if (strcasecmp(trim((string) $router['status']), 'Online') === 0) {
                continue;
            }

            $id = (int) $router['id'];
            $ip = trim((string) ($router['wg_tunnel_ip'] ?? ''));
            if ($id <= 0 || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }

            $candidates[$id] = $ip;
            $models[$id] = $router;
        }

        if (!$candidates) {
            return;
        }

        $reachable = rs14_ping_wireguard_routers($candidates);
        if (!$reachable) {
            return;
        }

        $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $lastSeen = $now->format('Y-m-d H:i:s');

        foreach ($reachable as $id => $yes) {
            if (!$yes || !isset($models[$id])) {
                continue;
            }

            $router = $models[$id];
            $router->status = 'Online';
            $router->last_seen = $lastSeen;
            $router->save();

            if (!empty($CACHE_PATH)) {
                $failFile = rtrim((string) $CACHE_PATH, '/\\')
                    . DIRECTORY_SEPARATOR . 'router_fail'
                    . DIRECTORY_SEPARATOR . 'r' . (int) $id . '.count';
                @unlink($failFile);
            }

            error_log(
                '[wireguard-router-status] router=' . (int) $id
                . ' ip=' . $candidates[$id]
                . ' state=Online source=' . preg_replace('/[^a-z0-9_.-]/i', '', (string) $source)
                . ' reason=wireguard-tunnel-reachable'
            );
        }
    } catch (Throwable $e) {
        error_log('[wireguard-router-status] heal failed: ' . $e->getMessage());
    }
}

// Correct stale false-negative status before dashboard widgets read tbl_routers.
$rs14Route = trim((string) ($_GET['_route'] ?? ''));
if (PHP_SAPI !== 'cli' && $rs14Route === 'dashboard') {
    rs14_heal_wireguard_router_statuses('dashboard');
}

// system/cron.php includes init.php (and therefore this plugin). The legacy
// monitor may mark a tunnel-reachable router Offline after API/8728 failures;
// correct that false negative after the cron run completes.
$rs14Script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
if (PHP_SAPI === 'cli' && $rs14Script === 'cron.php') {
    register_shutdown_function('rs14_heal_wireguard_router_statuses', 'cron-shutdown');
}
