<?php
/**
 * PamNet system cron — MUST only run as the main entry script (CLI or direct URL).
 * Never place this file in system/plugin/ — plugins are auto-included on every
 * dashboard/page request and would call exit() when the lock is held.
 */
$pamnetCronEntry = '';
if (PHP_SAPI === 'cli') {
    $pamnetCronEntry = realpath($_SERVER['argv'][0] ?? '') ?: '';
} else {
    $pamnetCronEntry = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
}
$pamnetCronSelf = realpath(__FILE__) ?: '';
if ($pamnetCronSelf === '' || $pamnetCronEntry === '' || $pamnetCronSelf !== $pamnetCronEntry) {
    // Included as a plugin / library — do nothing (prevents dashboard crash).
    return;
}

include __DIR__ . "/../init.php";
$lockFile = "$CACHE_PATH/router_monitor.lock";

if (!is_dir($CACHE_PATH)) {
    echo "Directory '$CACHE_PATH' does not exist. Exiting...\n";
    exit;
}

// Keep usage timestamps aligned with billing timezone (Active Now depends on this)
if (!empty($config['timezone'])) {
    @date_default_timezone_set($config['timezone']);
} else {
    @date_default_timezone_set('Africa/Nairobi');
}
try {
    ORM::raw_execute("SET time_zone = '+03:00'");
} catch (Exception $e) {
}

// Record system start date on first cron run
$systemStartConfig = ORM::for_table('tbl_appconfig')->where('setting', 'system_start_date')->find_one();
if (!$systemStartConfig) {
    $systemStartConfig = ORM::for_table('tbl_appconfig')->create();
    $systemStartConfig->setting = 'system_start_date';
    $systemStartConfig->value = date('Y-m-d H:i:s');
    $systemStartConfig->save();
    echo "System start date recorded: " . date('Y-m-d H:i:s') . "\n";
}

// Clear stale lock left by a killed/crashed previous run (keeps Active Now updating)
if (is_file($lockFile)) {
    $lockAge = time() - (int) @filemtime($lockFile);
    if ($lockAge > 600) { // older than 10 minutes
        @unlink($lockFile);
        echo "Removed stale cron lock (age {$lockAge}s)\n";
    }
}

$lock = fopen($lockFile, 'c');

if ($lock === false) {
    echo "Failed to open lock file. Exiting...\n";
    exit;
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // If lock is held but file is very old, force-break once more
    $lockAge = is_file($lockFile) ? (time() - (int) @filemtime($lockFile)) : 0;
    if ($lockAge > 600) {
        fclose($lock);
        @unlink($lockFile);
        $lock = fopen($lockFile, 'c');
        if ($lock !== false && flock($lock, LOCK_EX | LOCK_NB)) {
            echo "Broke stale cron lock and continued\n";
        } else {
            echo "Script is already running. Exiting...\n";
            if ($lock) {
                fclose($lock);
            }
            exit;
        }
    } else {
        echo "Script is already running. Exiting...\n";
        fclose($lock);
        exit;
    }
}
@touch($lockFile);


$isCli = true;
if (php_sapi_name() !== 'cli') {
    $isCli = false;
    echo "<pre>";
}
echo "PHP Time\t" . date('Y-m-d H:i:s') . "\tz=" . date_default_timezone_get() . "\n";
$res = ORM::raw_execute('SELECT NOW() AS WAKTU;');
$statement = ORM::get_last_statement();
$rows = [];
while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    echo "MYSQL Time\t" . $row['WAKTU'] . "\n";
}

// Soft MikroTik time sync (timezone + NTP only — never force clock).
// Failures must not mark the router offline or stop cron.
echo "=== MikroTik time sync (soft) ===\n";
try {
    if (!empty($config['mikrotik_time_sync']) && $config['mikrotik_time_sync'] === '0') {
        echo "skipped (mikrotik_time_sync=0)\n";
    } else {
        $syncResults = MikrotikTimeSync::syncAllRouters();
        foreach ($syncResults as $rName => $info) {
            $ok = !empty($info['ok']) ? 'OK' : 'WARN';
            echo "{$rName}\t{$ok}\t" . ($info['message'] ?? '') . "\n";
        }
    }
} catch (Throwable $e) {
    echo "time-sync skipped: " . $e->getMessage() . "\n";
}

// Re-check routers that were marked Offline — restore Online if API port is open
try {
    $offs = ORM::for_table('tbl_routers')->where('status', 'Offline')->where('enabled', '1')->find_many();
    foreach ($offs as $router) {
        if (strpos($router->ip_address, ':') === false) {
            $ip = $router->ip_address;
            $port = 8728;
        } else {
            [$ip, $port] = explode(':', $router->ip_address);
        }
        $ok = false;
        for ($i = 0; $i < 3; $i++) {
            $fsock = @fsockopen($ip, (int) $port, $errno, $errstr, 8);
            if ($fsock) {
                fclose($fsock);
                $ok = true;
                break;
            }
            usleep(300000);
        }
        if ($ok) {
            $router->status = 'Online';
            $router->last_seen = date('Y-m-d H:i:s');
            $router->save();
            $failFile = rtrim($CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'router_fail' . DIRECTORY_SEPARATOR . 'r' . (int) $router['id'] . '.count';
            @unlink($failFile);
            echo "restored Online: {$router->name} ({$ip}:{$port})\n";
        }
    }
} catch (Throwable $e) {
    echo "router restore skip: " . $e->getMessage() . "\n";
}

$_c = $config;


$textExpired = Lang::getNotifText('expired');

// Check for expired users - compare both date and time to ensure accuracy
// Note: This query checks date only for initial filtering, but final expiration check uses date+time
$d = ORM::for_table('tbl_user_recharges')->where('status', 'on')->where_lte('expiration', date("Y-m-d"))->find_many();
echo "Found " . count($d) . " user(s)\n";
run_hook('cronjob'); #HOOK

// === MIKROTIK USAGE MONITORING INTEGRATION ===
// Enhanced version: Uses proven MikroTik Monitor approach for data collection
// Saves live data to local database for customer usage analytics
echo "=== Starting MikroTik Usage Monitor ===\n";

// --- Create required database tables ---
function createUsageTablesIfNeeded() {
    try {
        $db = ORM::get_db();
        
        // Create tbl_usage_sessions table (tracks active sessions)
        $sessionTableExists = $db->query("SHOW TABLES LIKE 'tbl_usage_sessions'")->fetch();
        if (!$sessionTableExists) {
            $sessionTableSQL = "CREATE TABLE `tbl_usage_sessions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `router_id` int(11) NOT NULL,
              `username` varchar(64) NOT NULL,
              `interface` varchar(20) NOT NULL DEFAULT 'hotspot',
              `session_id` varchar(64) NOT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `mac_address` varchar(17) DEFAULT NULL,
              `last_rx` bigint(20) DEFAULT 0,
              `last_tx` bigint(20) DEFAULT 0,
              `session_rx` bigint(20) DEFAULT 0,
              `session_tx` bigint(20) DEFAULT 0,
              `start_time` datetime NOT NULL,
              `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_session` (`router_id`,`username`,`session_id`),
              KEY `idx_router_username` (`router_id`,`username`),
              KEY `idx_last_seen` (`last_seen`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $db->exec($sessionTableSQL);
            echo "Created table: tbl_usage_sessions\n";
        }
        
        // Create tbl_usage_records table (cumulative usage data)
        $recordsTableExists = $db->query("SHOW TABLES LIKE 'tbl_usage_records'")->fetch();
        if (!$recordsTableExists) {
            $recordsTableSQL = "CREATE TABLE `tbl_usage_records` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `router_id` int(11) NOT NULL,
              `username` varchar(64) NOT NULL,
              `interface` varchar(20) NOT NULL DEFAULT 'hotspot',
              `tx_bytes` bigint(20) DEFAULT 0,
              `rx_bytes` bigint(20) DEFAULT 0,
              `last_seen` datetime NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_user_router` (`router_id`,`username`,`interface`),
              KEY `idx_username` (`username`),
              KEY `idx_last_seen` (`last_seen`),
              KEY `idx_router` (`router_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $db->exec($recordsTableSQL);
            echo "Created table: tbl_usage_records\n";
        }
        
        return true;
    } catch (Exception $e) {
        echo "Error creating usage tables: " . $e->getMessage() . "\n";
        return false;
    }
}

// --- Format bytes function (from mikrotik_monitor.php) ---
function formatUsageBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// --- Function to process user sessions and update database ---
function processUserSession($router_id, $username, $interface, $session_id, $current_rx, $current_tx, $now, $ip_address = null, $mac_address = null) {
    try {
        // Find existing session record
        $session = ORM::for_table('tbl_usage_sessions')
            ->where('router_id', $router_id)
            ->where('username', $username)
            ->where('session_id', $session_id)
            ->find_one();

        $inc_rx = 0;
        $inc_tx = 0;

        if ($session) {
            // Since we clear all sessions first, this should rarely happen
            // But handle it for safety - calculate incremental usage
            $inc_rx = max(0, $current_rx - $session->last_rx);
            $inc_tx = max(0, $current_tx - $session->last_tx);

            // Update session counters
            $session->last_rx = $current_rx;
            $session->last_tx = $current_tx;
            $session->session_rx += $inc_rx;
            $session->session_tx += $inc_tx;
            $session->last_seen = $now;
            // Update IP and MAC if provided (validate and sanitize)
            if ($ip_address !== null && filter_var($ip_address, FILTER_VALIDATE_IP)) {
                $session->ip_address = $ip_address;
            }
            if ($mac_address !== null && preg_match('/^[0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}$/', $mac_address)) {
                $session->mac_address = strtoupper($mac_address);
            }
            $session->save();

            echo "    SESSION UPDATE: {$username} | +RX: " . formatUsageBytes($inc_rx) . " +TX: " . formatUsageBytes($inc_tx) . "\n";
        } else {
            // New session - create record
            // For new sessions, we treat the current MikroTik data as incremental (since it's a fresh session)
            $inc_rx = $current_rx;  // Full current data is new for this session
            $inc_tx = $current_tx;  // Full current data is new for this session
            
            $session = ORM::for_table('tbl_usage_sessions')->create();
            $session->router_id = $router_id;
            $session->username = $username;
            $session->interface = $interface;
            $session->session_id = $session_id;
            // Validate and store IP and MAC addresses
            if ($ip_address !== null && filter_var($ip_address, FILTER_VALIDATE_IP)) {
                $session->ip_address = $ip_address;
            }
            if ($mac_address !== null && preg_match('/^[0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}[:-][0-9A-Fa-f]{2}$/', $mac_address)) {
                $session->mac_address = strtoupper($mac_address);
            }
            $session->last_rx = $current_rx;
            $session->last_tx = $current_tx;
            // Store current MikroTik session data (shows what user has used in THIS session)
            $session->session_rx = $current_rx;  // Current session usage
            $session->session_tx = $current_tx;  // Current session usage
            $session->start_time = $now;
            $session->last_seen = $now;
            $session->save();

            echo "    NEW SESSION: {$username} | Session Data: RX=" . formatUsageBytes($current_rx) . " TX=" . formatUsageBytes($current_tx) . "\n";
        }

        // Update cumulative usage record (for customer usage analytics)
        $rec = ORM::for_table('tbl_usage_records')
            ->where('router_id', $router_id)
            ->where('username', $username)
            ->where('interface', $interface)
            ->find_one();

        if ($rec) {
            // Add incremental usage to lifetime totals
            if ($inc_tx > 0 || $inc_rx > 0) {
                $old_tx = $rec->tx_bytes;
                $old_rx = $rec->rx_bytes;
                $rec->tx_bytes += $inc_tx;
                $rec->rx_bytes += $inc_rx;
                $rec->last_seen = $now;
                $rec->save();
                
                echo "    USAGE RECORD: {$username} | Lifetime Total: RX=" . formatUsageBytes($rec->rx_bytes) . " TX=" . formatUsageBytes($rec->tx_bytes) . "\n";
            } else {
                // Update last_seen even if no new usage
                $rec->last_seen = $now;
                $rec->save();
            }
        } else {
            // Create new usage record
            $rec = ORM::for_table('tbl_usage_records')->create();
            $rec->router_id = $router_id;
            $rec->username = $username;
            $rec->interface = $interface;
            $rec->tx_bytes = $inc_tx;
            $rec->rx_bytes = $inc_rx;
            $rec->last_seen = $now;
            $rec->save();
            
            echo "    NEW USER RECORD: {$username} | Initial: RX=" . formatUsageBytes($inc_rx) . " TX=" . formatUsageBytes($inc_tx) . "\n";
        }

    } catch (Exception $e) {
        echo "    ERROR processing {$username}: " . $e->getMessage() . "\n";
    }
}

// Session cleanup is now handled by clearing all sessions per router at start of processing

// Ensure usage tables exist
if (!createUsageTablesIfNeeded()) {
    echo "Failed to create usage database tables - skipping usage monitoring\n";
} else {
    // --- Fetch all enabled routers for usage monitoring ---
    $usage_routers = ORM::for_table('tbl_routers')
        ->where('enabled', '1')
        ->find_many();
    echo "Found " . count($usage_routers) . " enabled router(s) for usage monitoring.\n";

    $now = date('Y-m-d H:i:s');
    
    // Get list of enabled router IDs for cleanup
    $enabled_router_ids = [];
    foreach ($usage_routers as $router) {
        $enabled_router_ids[] = $router->id;
    }

    foreach ($usage_routers as $router) {
        $rid = $router->id;
        echo "-- Usage Monitor Router {$rid} ({$router->ip_address}) --\n";
        
        try {
            $client = Mikrotik::getClient(
                $router->ip_address,
                $router->username,
                $router->password
            );
            echo "Connected to router successfully for usage monitoring\n";
        } catch (Exception $e) {
            echo "Usage monitoring connection failed: " . $e->getMessage() . "\n";
            continue;
        }

        // --- SMART CLEANUP: Mark sessions as inactive instead of deleting ---
        echo "Marking old sessions as inactive for router {$rid}...\n";
        // Mark sessions older than 2 minutes as potentially inactive
        $old_sessions = ORM::for_table('tbl_usage_sessions')
            ->where('router_id', $rid)
            ->where_lt('last_seen', date('Y-m-d H:i:s', strtotime('-2 minutes')))
            ->find_many();
        
        $inactive_count = count($old_sessions);
        foreach ($old_sessions as $session) {
            $session->delete(); // Remove truly old sessions
        }
        echo "Cleaned {$inactive_count} old sessions (older than 2 minutes)\n";
        
        // --- HOTSPOT USERS (Using MikroTik Monitor approach) ---
        try {
            echo "Fetching hotspot active users...\n";
            $hotspotActive = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
            $hotspotCount = count($hotspotActive);
            echo "Found {$hotspotCount} active hotspot users\n";

            foreach ($hotspotActive as $hotspot) {
                $username = $hotspot->getProperty('user');
                $address = $hotspot->getProperty('address');
                $uptime = $hotspot->getProperty('uptime');
                $server = $hotspot->getProperty('server');
                $mac = $hotspot->getProperty('mac-address');
                $session_id = $hotspot->getProperty('.id');  // Unique session ID
                
                // CRITICAL: Get usage data (like mikrotik_monitor.php)
                $rxBytes = intval($hotspot->getProperty('bytes-in'));    // Download
                $txBytes = intval($hotspot->getProperty('bytes-out'));   // Upload
                
                if ($username && $session_id) {
                    // Process session tracking with IP and MAC
                    processUserSession($rid, $username, 'hotspot', $session_id, $rxBytes, $txBytes, $now, $address, $mac);
                    echo "  HOTSPOT: {$username} ({$address}) [{$mac}] | RX: " . formatUsageBytes($rxBytes) . " TX: " . formatUsageBytes($txBytes) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Error fetching hotspot users: " . $e->getMessage() . "\n";
        }

        // --- PPPOE USERS (Using MikroTik Monitor approach) ---
        try {
            echo "Fetching PPPoE active users...\n";
            $pppUsers = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ppp/active/print'));
            $pppCount = count($pppUsers);
            echo "Found {$pppCount} active PPPoE users\n";

            // Get interface data for PPPoE usage (like mikrotik_monitor.php)
            $interfaceTraffic = $client->sendSync(new PEAR2\Net\RouterOS\Request('/interface/print'));
            $interfaceData = [];
            foreach ($interfaceTraffic as $interface) {
                $name = $interface->getProperty('name');
                if (!empty($name)) {
                    $interfaceData[$name] = [
                        'txBytes' => intval($interface->getProperty('tx-byte')),
                        'rxBytes' => intval($interface->getProperty('rx-byte')),
                    ];
                }
            }

            foreach ($pppUsers as $pppUser) {
                $username = $pppUser->getProperty('name');
                $address = $pppUser->getProperty('address');
                $uptime = $pppUser->getProperty('uptime');
                $service = $pppUser->getProperty('service');
                $session_id = $pppUser->getProperty('.id');  // Unique session ID

                // Get usage from interface (like mikrotik_monitor.php)
                $interfaceName = "<pppoe-$username>";
                if (isset($interfaceData[$interfaceName])) {
                    $trafficData = $interfaceData[$interfaceName];
                    $txBytes = $trafficData['txBytes'];
                    $rxBytes = $trafficData['rxBytes'];
                } else {
                    $txBytes = 0;
                    $rxBytes = 0;
                }

                if ($username && $session_id) {
                    // Process session tracking with IP (no MAC for PPPoE)
                    processUserSession($rid, $username, 'pppoe', $session_id, $rxBytes, $txBytes, $now, $address, null);
                    echo "  PPPOE: {$username} ({$address}) | RX: " . formatUsageBytes($rxBytes) . " TX: " . formatUsageBytes($txBytes) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Error fetching PPPoE users: " . $e->getMessage() . "\n";
        }

        echo "Finished processing usage for router {$rid}\n";
    }
}

// --- Display recent session data with IP/MAC for verification ---
try {
    $recentSessions = ORM::for_table('tbl_usage_sessions')
        ->where_gte('last_seen', date('Y-m-d H:i:s', time() - 300)) // Last 5 minutes
        ->limit(10)
        ->find_many();
    
    if (count($recentSessions) > 0) {
        echo "\n=== Recent Session Data (Last 5 minutes) ===\n";
        foreach ($recentSessions as $s) {
            $ip_info = $s->ip_address ? " IP: {$s->ip_address}" : " IP: N/A";
            $mac_info = $s->mac_address ? " MAC: {$s->mac_address}" : " MAC: N/A";
            echo "  {$s->username} ({$s->interface}){$ip_info}{$mac_info} | RX: " . formatUsageBytes($s->session_rx) . " TX: " . formatUsageBytes($s->session_tx) . "\n";
        }
    } else {
        echo "\n=== No recent sessions found in last 5 minutes ===\n";
    }
} catch (Exception $e) {
    echo "Error displaying session data: " . $e->getMessage() . "\n";
    }

// --- GLOBAL CLEANUP: Remove sessions from disabled routers ---
if (!empty($enabled_router_ids)) {
    try {
        $disabled_sessions = ORM::for_table('tbl_usage_sessions')
            ->where_not_in('router_id', $enabled_router_ids)
            ->find_many();        $disabled_count = count($disabled_sessions);
        if ($disabled_count > 0) {
            foreach ($disabled_sessions as $disabled_session) {
                $disabled_session->delete();
            }
            echo "Cleaned {$disabled_count} sessions from disabled routers\n";
        }
    } catch (Exception $e) {
        echo "Error cleaning disabled router sessions: " . $e->getMessage() . "\n";
    }
} else {
    echo "No enabled routers found for cleanup\n";
}

echo "=== MikroTik Usage Monitor Finished ===\n";

foreach ($d as $ds) {
    try {
        $date_now = strtotime(date("Y-m-d H:i:s"));
        // Always re-load before kick: usage monitor can take minutes, and a
        // just-paid renew may have extended expiration on this same row.
        $u = ORM::for_table('tbl_user_recharges')->where('id', $ds['id'])->find_one();
        if (!$u) {
            echo ($isCli ? $ds['username'] : Lang::maskText($ds['username'])) . " : SKIP (row gone)\r\n";
            continue;
        }
        if ((string) $u['status'] !== 'on') {
            echo ($isCli ? $u['username'] : Lang::maskText($u['username'])) . " : SKIP (already off)\r\n";
            continue;
        }

        $expiration = strtotime(trim($u['expiration'] . ' ' . $u['time']));
        echo $u['expiration'] . ' ' . $u['time'] . " : " . ($isCli ? $u['username'] : Lang::maskText($u['username']));

        if ($expiration === false) {
            echo " : SKIP (bad expiry)\r\n";
            continue;
        }

        if ($date_now >= $expiration) {
            echo " : EXPIRED \r\n";

            // Fetch customer details
            $c = ORM::for_table('tbl_customers')->where('id', $u['customer_id'])->find_one();
            if (!$c) {
                $c = $u;
            }

            // Fetch plan details
            $p = ORM::for_table('tbl_plans')->where('id', $u['plan_id'])->find_one();
            if (!$p) {
                throw new Exception("Plan not found for ID: " . $u['plan_id']);
            }

            $dvc = Package::getDevice($p);
            if ($_app_stage != 'demo') {
                if (file_exists($dvc)) {
                    require_once $dvc;
                    (new $p['device'])->remove_customer($c, $p);
                } else {
                    throw new Exception("Cron error: Devices " . $p['device'] . "not found, cannot disconnect ".$c['username']."\n");
                }
            }

            // Send notification (PPPoE only) and update user status
            try {
                if (strtoupper((string) ($p['type'] ?? '')) === 'PPPOE') {
                    echo Message::sendPackageNotification(
                        $c,
                        $u['namebp'],
                        $p['price'],
                        Message::resolveNotifTemplate($p['type'], 'expired'),
                        Message::resolveNotificationVia($p['type'], 'expired')
                    ) . "\n";
                } else {
                    echo "none: Hotspot skipped (PPPoE only)\n";
                }
                $u->status = 'off';
                $u->save();
            } catch (Throwable $e) {
                _log($e->getMessage());
                sendTelegram($e->getMessage());
                echo "Error: " . $e->getMessage() . "\n";
            }

            // Auto-renewal from deposit
            if ($config['enable_balance'] == 'yes' && $c['auto_renewal']) {
                [$bills, $add_cost] = User::getBills($u['customer_id']);
                if ($add_cost != 0) {
                    $p['price'] += $add_cost;
                }

                if ($p && $c['balance'] >= $p['price']) {
                    if (Package::rechargeUser($u['customer_id'], $u['routers'], $p['id'], 'Customer', 'Balance')) {
                        Balance::min($u['customer_id'], $p['price']);
                        echo "plan enabled: " . (string) $p['enabled'] . " | User balance: " . (string) $c['balance'] . " | price " . (string) $p['price'] . "\n";
                        echo "auto renewal Success\n";
                    } else {
                        echo "plan enabled: " . $p['enabled'] . " | User balance: " . $c['balance'] . " | price " . $p['price'] . "\n";
                        echo "auto renewal Failed\n";
                        Message::sendTelegram("FAILED RENEWAL #cron\n\n#u." . $c['username'] . " #buy #Hotspot \n" . $p['name_plan'] .
                            "\nRouter: " . $p['routers'] .
                            "\nPrice: " . $p['price']);
                    }
                } else {
                    echo "no renewal | plan enabled: " . (string) $p['enabled'] . " | User balance: " . (string) $c['balance'] . " | price " . (string) $p['price'] . "\n";
                }
            } else {
                echo "no renewal | balance" . $config['enable_balance'] . " auto_renewal " . $c['auto_renewal'] . "\n";
            }
        } else {
            echo " : ACTIVE \r\n";
        }
    } catch (Throwable $e) {
        // Catch any unexpected errors
        _log($e->getMessage());
        sendTelegram($e->getMessage());
        echo "Unexpected Error: " . $e->getMessage() . "\n";
    }
}

// Ensure expired Hotspot users are kicked back to the captive portal
// (fixes phones stuck on "Connected without Internet" after package ends)
echo "=== Hotspot captive-portal cleanup ===\n";
if (!class_exists('MikrotikHotspot') && is_file($DEVICE_PATH . '/MikrotikHotspot.php')) {
    require_once $DEVICE_PATH . '/MikrotikHotspot.php';
}
try {
    $recentOff = ORM::for_table('tbl_user_recharges')
        ->where('status', 'off')
        ->where('type', 'Hotspot')
        ->where_gte('expiration', date('Y-m-d', strtotime('-1 day')))
        ->find_many();
    $hsDev = new MikrotikHotspot();
    $doneKeys = [];
    foreach ($recentOff as $row) {
        $uName = (string) ($row['username'] ?? '');
        $rName = (string) ($row['routers'] ?? '');
        if ($uName === '' || $rName === '') {
            continue;
        }
        $key = $uName . '|' . $rName;
        if (isset($doneKeys[$key])) {
            continue;
        }
        $stillOn = ORM::for_table('tbl_user_recharges')
            ->where('username', $uName)
            ->where('status', 'on')
            ->find_one();
        if ($stillOn) {
            continue;
        }
        // Do not wipe MikroTik if this off-row still has future expiry
        // (stale cron kick / race with a just-paid renew).
        $rowExp = strtotime(trim(($row['expiration'] ?? '') . ' ' . ($row['time'] ?? '')));
        if ($rowExp !== false && $rowExp > time()) {
            echo "portal-reset skip (expiry still future): {$uName}\n";
            continue;
        }
        $doneKeys[$key] = true;
        try {
            if ($_app_stage != 'demo') {
                $mk = $hsDev->info($rName);
                $cli = $hsDev->getClient($mk['ip_address'], $mk['username'], $mk['password']);
                $hsDev->forceCaptivePortal($cli, $uName);
                $hsDev->removeHotspotUser($cli, $uName);
                $hsDev->forceCaptivePortal($cli, $uName);
            }
            echo "portal-reset: {$uName} @ {$rName}\n";
        } catch (Throwable $e) {
            echo "portal-reset fail {$uName}: " . $e->getMessage() . "\n";
        }
    }
    echo "captive-portal cleanup done (" . count($doneKeys) . ")\n";
} catch (Throwable $e) {
    echo "portal cleanup error: " . $e->getMessage() . "\n";
}

// Stale Hotspot access (expired 2+ days, no repurchase) is cleaned by
// system/tools/cleanup_stale_hotspot_access.php via /etc/cron.d/pamnet every 30m.

//Cek interim-update radiusrest
if ($config['frrest_interim_update'] != 0) {

    $r_a = ORM::for_table('rad_acct')
        ->whereRaw("BINARY acctstatustype = 'Start' OR acctstatustype = 'Interim-Update'")
        ->where_lte('dateAdded', date("Y-m-d H:i:s"))->find_many();

    foreach ($r_a as $ra) {
        $interval = $_c['frrest_interim_update'] * 60;
        $timeUpdate = strtotime($ra['dateAdded']) + $interval;
        $timeNow = strtotime(date("Y-m-d H:i:s"));
        if ($timeNow >= $timeUpdate) {
            $ra->acctstatustype = 'Stop';
            $ra->save();
        }
    }
}

if ($config['router_check']) {
    echo "Checking router status...\n";
    $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
    if (!$routers) {
        echo "No active routers found in the database.\n";
        flock($lock, LOCK_UN);
        fclose($lock);
        unlink($lockFile);
        exit;
    }

    $offlineRouters = [];
    $errors = [];
    $failDir = rtrim($CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'router_fail';
    if (!is_dir($failDir)) {
        @mkdir($failDir, 0755, true);
    }

    foreach ($routers as $router) {
        // check if custom port
        if (strpos($router->ip_address, ':') === false) {
            $ip = $router->ip_address;
            $port = 8728;
        } else {
            [$ip, $port] = explode(':', $router->ip_address);
        }
        $isOnline = false;
        $lastErr = '';

        // Retry — avoid false Offline when API is briefly busy
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $timeout = 10;
                if (is_callable('fsockopen') && false === stripos(ini_get('disable_functions'), 'fsockopen')) {
                    $fsock = @fsockopen($ip, (int) $port, $errno, $errstr, $timeout);
                    if ($fsock) {
                        fclose($fsock);
                        $isOnline = true;
                        break;
                    }
                    $lastErr = "fsockopen: $errstr ($errno)";
                } elseif (is_callable('stream_socket_client') && false === stripos(ini_get('disable_functions'), 'stream_socket_client')) {
                    $connection = @stream_socket_client("$ip:$port", $errno, $errstr, $timeout);
                    if ($connection) {
                        fclose($connection);
                        $isOnline = true;
                        break;
                    }
                    $lastErr = "stream_socket_client: $errstr ($errno)";
                } else {
                    $lastErr = "Neither fsockopen nor stream_socket_client are enabled";
                    break;
                }
            } catch (Exception $e) {
                $lastErr = $e->getMessage();
            }
            if ($attempt < 3) {
                usleep(400000);
            }
        }

        $failFile = $failDir . DIRECTORY_SEPARATOR . 'r' . (int) $router['id'] . '.count';
        if ($isOnline) {
            @unlink($failFile);
            $router->last_seen = date('Y-m-d H:i:s');
            $router->status = 'Online';
            echo "router {$router->name}: Online\n";
        } else {
            $fails = 1;
            if (is_file($failFile)) {
                $fails = max(1, ((int) @file_get_contents($failFile)) + 1);
            }
            @file_put_contents($failFile, (string) $fails);
            $errors[] = "Error with router $ip: $lastErr (fail $fails/3)";
            // Only mark Offline after 3 consecutive failed checks
            if ($fails >= 3) {
                $router->status = 'Offline';
                $offlineRouters[] = $router;
                echo "router {$router->name}: Offline (confirmed $fails fails)\n";
            } else {
                echo "router {$router->name}: transient fail $fails/3 — keeping status {$router->status}\n";
            }
            if (function_exists('_log')) {
                _log("Router check {$router->name}: $lastErr (fail $fails/3)", 'System', 1);
            }
        }

        $router->save();
    }
    
    if (!empty($offlineRouters)) {
        $message = "Dear Administrator,\n";
        $message .= "The following routers are offline:\n";
        foreach ($offlineRouters as $router) {
            $message .= "Name: {$router->name}, IP: {$router->ip_address}, Last Seen: {$router->last_seen}\n";
        }
        $message .= "\nPlease check the router's status and take appropriate action.\n\nBest regards,\nRouter Monitoring System";

        $adminEmail = $config['mail_from'];
        $subject = "Router Offline Alert";
        Message::SendEmail($adminEmail, $subject, $message);
        sendTelegram($message);
    }

    if (!empty($errors)) {
        echo "Router check notes:\n";
        foreach ($errors as $error) {
            echo "$error\n";
        }
        // Do not email on transient single failures — only confirmed offline above
    }
    echo "Router monitoring finished checking.\n";
}

// Clean old dashboard cache files (JSON files older than 10 minutes)
$cache_cleaned = 0;
if (is_dir($CACHE_PATH)) {
    $json_files = glob($CACHE_PATH . '/*.json');
    foreach ($json_files as $file) {
        if (file_exists($file) && (time() - filemtime($file)) > 600) { // 10 minutes
            if (unlink($file)) {
                $cache_cleaned++;
            }
        }
    }
    if ($cache_cleaned > 0) {
        echo "Cleaned $cache_cleaned old dashboard cache files.\n";
    }
}

flock($lock, LOCK_UN);
fclose($lock);
unlink($lockFile);

$timestampFile = "$UPLOAD_PATH/cron_last_run.txt";
file_put_contents($timestampFile, time());

run_hook('cronjob_end'); #HOOK
echo "Cron job finished and completed successfully.\n";