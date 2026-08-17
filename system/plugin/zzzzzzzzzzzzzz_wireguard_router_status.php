<?php

/**
 * WireGuard router liveness correction.
 *
 * The legacy router monitor treats RouterOS API TCP/8728 availability as the
 * definition of router liveness. For WireGuard-managed routers that produces
 * false Offline/Unreachable states when the tunnel/router is reachable but the
 * API service is busy, restricted or temporarily unavailable.
 *
 * This layer only heals false-negative states. It never marks a router Offline.
 * A WireGuard router that answers ICMP on its assigned tunnel IP is considered
 * network-reachable even when API/8728 is unavailable.
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
 * Ping validated WireGuard IPs concurrently, so several genuinely-offline
 * routers do not add one second each to a dashboard/cron run.
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

        // On dashboard requests only stale Offline/Unreachable rows need a
        // liveness probe. After cron, check every WireGuard router because the
        // legacy API monitor may have just incremented its 8728 failure counter
        // while leaving the current status Online for the first two failures.
        $checkAll = ((string) $source === 'cron-shutdown');
        $candidates = [];
        $models = [];
        foreach ($rows as $router) {
            $currentlyOnline = strcasecmp(trim((string) $router['status']), 'Online') === 0;
            if (!$checkAll && $currentlyOnline) {
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
            $previousStatus = trim((string) $router['status']);
            $router->status = 'Online';
            $router->last_seen = $lastSeen;
            $router->save();

            $failFile = '';
            $hadApiFailureCounter = false;
            if (!empty($CACHE_PATH)) {
                $failFile = rtrim((string) $CACHE_PATH, '/\\')
                    . DIRECTORY_SEPARATOR . 'router_fail'
                    . DIRECTORY_SEPARATOR . 'r' . (int) $id . '.count';
                $hadApiFailureCounter = is_file($failFile);
                @unlink($failFile);
            }

            if (strcasecmp($previousStatus, 'Online') !== 0 || $hadApiFailureCounter) {
                error_log(
                    '[wireguard-router-status] router=' . (int) $id
                    . ' ip=' . $candidates[$id]
                    . ' state=Online source=' . preg_replace('/[^a-z0-9_.-]/i', '', (string) $source)
                    . ' reason=wireguard-tunnel-reachable'
                );
            }
        }
    } catch (Throwable $e) {
        error_log('[wireguard-router-status] heal failed: ' . $e->getMessage());
    }
}

// Correct a stale false-negative status before dashboard widgets read tbl_routers.
$rs14Route = trim((string) ($_GET['_route'] ?? ''));
if (PHP_SAPI !== 'cli' && $rs14Route === 'dashboard') {
    rs14_heal_wireguard_router_statuses('dashboard');
}

// system/cron.php includes init.php, which auto-loads plugins. The legacy
// monitor may fail API/8728 even when the WireGuard tunnel is healthy. Probe
// tunnel reachability after cron and clear that API-only failure counter so it
// cannot accumulate into a false Offline state/alert.
$rs14Script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
if (PHP_SAPI === 'cli' && $rs14Script === 'cron.php') {
    register_shutdown_function('rs14_heal_wireguard_router_statuses', 'cron-shutdown');
}
