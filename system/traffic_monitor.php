<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

// Set default values for CLI mode
if (!isset($_SERVER['SERVER_PORT'])) {
    $_SERVER['SERVER_PORT'] = 80;
}
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

// Load .env untuk CLI mode
if (php_sapi_name() === 'cli' || !getenv('DB_USERNAME')) {
    $env_file = realpath(__DIR__ . '/../') . '/.env';
    if (file_exists($env_file)) {
        $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($env_lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                putenv($line);
            }
        }
    }
}

// Include files
require_once dirname(__DIR__) . '/init.php';

// RouterOS namespace
use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;

// Silent mode untuk cron
$silent = isset($_GET['silent']) && $_GET['silent'] == '1';

// ✅ CEK STOP FILE - JANGAN JALAN JIKA ADA
$stopFile = dirname(__DIR__) . '/system/plugin/data/cron_stop.txt';
if (file_exists($stopFile)) {
    if (!$silent) echo "CRON STOPPED - Payment reset in progress<br>";
    exit("STOPPED");
}

if (!$silent) {
    echo "<h3>Production Traffic Monitor</h3>";
    echo "Time: " . date('Y-m-d H:i:s') . "<br><br>";
}

function trafficMonitorNormalizeUsername($username)
{
    return strtolower(trim((string) $username));
}

function trafficMonitorBuildCustomerLookup()
{
    static $lookup = null;

    if ($lookup !== null) {
        return $lookup;
    }

    $lookup = array();
    $customerNames = array();

    $customers = ORM::for_table('tbl_customers')->find_many();
    foreach ($customers as $customer) {
        $customerId = intval($customer['id']);
        $customerNames[$customerId] = $customer['fullname'] ?: '';

        $username = trafficMonitorNormalizeUsername($customer['pppoe_username'] ?? '');
        if ($username !== '') {
            $lookup[$username] = [
                'id' => $customerId,
                'fullname' => $customer['fullname'] ?: ($customer['pppoe_username'] ?: ''),
            ];
        }
    }

    $recharges = ORM::for_table('tbl_user_recharges')->find_many();
    foreach ($recharges as $recharge) {
        $username = trafficMonitorNormalizeUsername($recharge['username'] ?? '');
        $customerId = intval($recharge['customer_id'] ?? 0);
        if ($username === '' || $customerId <= 0 || isset($lookup[$username])) {
            continue;
        }

        $lookup[$username] = [
            'id' => $customerId,
            'fullname' => !empty($customerNames[$customerId]) ? $customerNames[$customerId] : ($recharge['username'] ?: ''),
        ];
    }

    return $lookup;
}

function updateUserStatus($userComment, $username, $currentTx, $currentRx, $silent = false,
    $ipAddress = null, $uptime = null, $routerName = null, $routerId = 0)
{
    $lookup = trafficMonitorBuildCustomerLookup();
    $normalized = trafficMonitorNormalizeUsername($username);
    $matchedCustomer = isset($lookup[$normalized]) ? $lookup[$normalized] : null;
    $customerId = $matchedCustomer ? intval($matchedCustomer['id']) : null;

    if (!$customerId) {
        error_log("TRAFFIC MONITOR: customer_id not found for username: $username - skipping");
        return;
    }

    // ✅ LOAD EXISTING DATA FROM DATABASE
    $statusQuery = ORM::for_table('tbl_user_connection_status')
        ->where('customer_id', $customerId);
    if (intval($routerId) > 0) {
        $statusQuery->where('router_id', intval($routerId));
    } elseif (!empty($routerName)) {
        $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
        if ($router) {
            $statusQuery->where('router_id', intval($router['id']));
            $routerId = intval($router['id']);
        }
    }
    $statusRecord = $statusQuery->find_one();

    // ✅ HANDLE RECONNECT AFTER DISCONNECT
    if ($statusRecord && $statusRecord['needs_fresh_start'] == 1 && ($currentTx > 0 || $currentRx > 0)) {
        if (!$silent) echo "&nbsp;&nbsp;User $userComment reconnected - starting fresh monitoring<br>";
        error_log("TRAFFIC MONITOR: User $userComment reconnected - fresh start");

        // Remove fresh start flag dan mulai monitoring normal
        $statusRecord->needs_fresh_start = 0;
        $statusRecord->was_connected = 1;
        $statusRecord->last_tx = 0; // Mulai dari 0 untuk detect disconnect berikutnya
        $statusRecord->last_rx = 0; // Mulai dari 0 untuk detect disconnect berikutnya
        $statusRecord->save();
    }

    // ✅ IMPROVED DISCONNECT DETECTION - SEMI REALTIME
    $shouldSaveHistory = false;
    if ($statusRecord) {
        $prevConnected = $statusRecord['was_connected'];
        $prevTx = intval($statusRecord['last_tx']);
        $prevRx = intval($statusRecord['last_rx']);

        // Detect disconnect: cukup currentTx < prevTx = pasti ada disconnect/reconnect
        // Tidak pakai threshold % agar dynamic dan responsif
        if ($prevTx > 0 || $prevRx > 0) {

            $totalReset = ($currentTx == 0 && $currentRx == 0);
            $reconnectDrop = (
                ($currentTx < $prevTx) ||
                ($currentRx < $prevRx)
            );

            if ($totalReset || $reconnectDrop) {
                $shouldSaveHistory = true;
                if (!$silent) echo "&nbsp;&nbsp;Disconnect/Reconnect detected for $userComment (TX: $prevTx->$currentTx, RX: $prevRx->$currentRx)<br>";

                // ✅ SAVE PREVTX & PREVRX KE HISTORY DULU
                $historyRecord = ORM::for_table('tbl_user_usage_history')
                    ->where('customer_id', $customerId)
                    ->find_one();

                // Ambil periode billing aktif
                $billingPeriod = getBillingPeriod($customerId);

                if ($historyRecord) {
                    // Tambahkan prevTx/prevRx ke history yang sudah ada
                    $historyRecord->total_upload    = intval($historyRecord->total_upload) + $prevTx;
                    $historyRecord->total_download  = intval($historyRecord->total_download) + $prevRx;
                    $historyRecord->last_updated    = date('Y-m-d H:i:s');
                    $historyRecord->last_disconnect = date('Y-m-d H:i:s');
                    $historyRecord->save();
                    // Simpan snapshot ke periode billing aktif
                    saveMonthlySnapshot(
                        $customerId,
                        $username,
                        $userComment,
                        intval($historyRecord->total_upload),
                        intval($historyRecord->total_download),
                        $billingPeriod['year'],
                        $billingPeriod['month']
                    );
                } else {
                    // Buat baru kalau belum ada
                    $historyRecord                  = ORM::for_table('tbl_user_usage_history')->create();
                    $historyRecord->customer_id     = $customerId;
                    $historyRecord->username        = $username;
                    $historyRecord->total_upload    = $prevTx;
                    $historyRecord->total_download  = $prevRx;
                    $historyRecord->last_updated    = date('Y-m-d H:i:s');
                    $historyRecord->last_disconnect = date('Y-m-d H:i:s');
                    $historyRecord->save();
                    // Simpan snapshot ke periode billing aktif
                    saveMonthlySnapshot(
                        $customerId,
                        $username,
                        $userComment,
                        $prevTx,
                        $prevRx,
                        $billingPeriod['year'],
                        $billingPeriod['month']
                    );
                }

                if (!$silent) echo "&nbsp;&nbsp;Saved to history: TX=$prevTx, RX=$prevRx<br>";

                error_log("TRAFFIC MONITOR: Disconnect/Reconnect detected for $userComment - Saved TX: $prevTx, RX: $prevRx");

                // ✅ RESET last_tx & last_rx ke currentTx sekarang
                // Agar sesi baru dihitung dari titik ini
                $statusRecord->username          = $username;
                $statusRecord->was_connected     = 1;
                $statusRecord->last_tx           = $currentTx;
                $statusRecord->last_rx           = $currentRx;
                $statusRecord->last_check        = date('Y-m-d H:i:s');
                $statusRecord->needs_fresh_start = 0;
                if (intval($routerId) > 0) {
                    $statusRecord->router_id = intval($routerId);
                }
                $statusRecord->save();

                // Early return agar tidak overwrite status di bawah
                return;
            }
        }
    }

    // ✅ UPDATE CURRENT STATUS + LIVE DATA (hanya jika tidak disconnect)
    if (!$statusRecord) {
        // Create new status record
        $statusRecord = ORM::for_table('tbl_user_connection_status')->create();
        $statusRecord->customer_id = $customerId;
        $statusRecord->router_id   = intval($routerId);
    }

    $statusRecord->username      = $username;
    $statusRecord->user_comment  = $userComment;
    $statusRecord->ip_address    = $ipAddress;
    $statusRecord->uptime        = $uptime;
    $statusRecord->was_connected = ($currentTx > 0 || $currentRx > 0) ? 1 : 0;
    $statusRecord->last_tx       = $currentTx;
    $statusRecord->last_rx       = $currentRx;
    $statusRecord->last_check    = date('Y-m-d H:i:s');
    if (intval($routerId) > 0) {
        $statusRecord->router_id = intval($routerId);
    }
    $statusRecord->save();

    error_log("Status database update completed for $userComment");
}

// Ensure table/index migration is applied before processing starts.
createTrafficMonitorTables();

// Definisikan isHeavyRun — heavy tasks hanya jalan di detik 0-14 (run pertama per menit)
$currentSecond = intval(date('s'));
$isHeavyRun    = ($currentSecond < 15);

try {
    // ✅ GET ALL ENABLED ROUTERS
    $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();

    if (!$silent) echo "Found " . count($routers) . " router(s)<br><br>";

    // ✅ CHECK ADMIN RECHARGE — hanya tiap 60 detik
    if ($isHeavyRun) {
        $adminResetCount = checkAdminRecharge($silent);
        if (!$silent && $adminResetCount > 0) {
            echo "Admin recharge resets executed: $adminResetCount<br><br>";
        }
    }

    // ✅ BUILD CUSTOMER ID MAP DARI DATABASE
    $customerIdMap = trafficMonitorBuildCustomerLookup();

    foreach ($routers as $router) {
        if (!$silent) echo "Processing router: {$router['name']} ({$router['ip_address']})<br>";

        try {
            $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

            if (!$silent) echo "Connected!<br>";

            // ✅ BATCH API CALLS - Ambil semua data sekaligus
            $hotspotUsers = $client->sendSync(new RouterOS\Request('/ip/hotspot/active/print'));
            $hotspotSecrets = $client->sendSync(new RouterOS\Request('/ip/hotspot/user/print'));
            $interfaces = $client->sendSync(new RouterOS\Request('/interface/print'));

            if (!$silent) echo "Found " . count($hotspotUsers) . " active users<br>";

            // ✅ BUILD LOOKUP ARRAYS
            $secretComments = array();
            foreach ($hotspotSecrets as $secret) {
                $secretName    = $secret->getProperty('name');
                $secretComment = $secret->getProperty('comment');
                if (!empty($secretName) && !empty($secretComment)) {
                    $commentParts             = explode('|', $secretComment);
                    $secretComments[$secretName] = trim($commentParts[0]);
                }
            }

            $interfaceData = array();
            foreach ($interfaces as $interface) {
                $name = $interface->getProperty('name');
                if (!empty($name)) {
                    $interfaceData[$name] = array(
                        'tx' => intval($interface->getProperty('rx-byte')),
                        'rx' => intval($interface->getProperty('tx-byte')),
                    );
                }
            }

            // ✅ AMBIL SYSTEM RESOURCES ROUTER
            try {
                $sysResources = $client->sendSync(new RouterOS\Request('/system/resource/print'));
                $cpuLoad      = 0;
                $freeMem      = 0;
                $totalMem     = 0;
                $routerUptime = '0s';
                $boardName    = 'Unknown';
                $version      = 'Unknown';

                foreach ($sysResources as $res) {
                    $cpuLoad      = floatval($res->getProperty('cpu-load') ?: 0);
                    $freeMem      = intval($res->getProperty('free-memory') ?: 0);
                    $totalMem     = intval($res->getProperty('total-memory') ?: 0);
                    $routerUptime = $res->getProperty('uptime') ?: '0s';
                    $boardName    = $res->getProperty('board-name') ?: 'Unknown';
                    $version      = $res->getProperty('version') ?: 'Unknown';
                    break;
                }

                $memoryUsed    = $totalMem - $freeMem;
                $memoryPercent = $totalMem > 0 ? round(($memoryUsed / $totalMem) * 100, 1) : 0;

                // ✅ AMBIL BANDWIDTH ROUTER — hanya tiap 60 detik (run pertama per menit)
                $totalRxRate = 0;
                $totalTxRate = 0;
                if ($isHeavyRun) {
                    foreach ($interfaces as $interface) {
                        $ifName  = $interface->getProperty('name');
                        $ifType  = $interface->getProperty('type');
                        $running = $interface->getProperty('running');
                        if (
                            !in_array($ifType, ['bridge', 'loopback', 'pppoe-out', 'pppoe-in', 'vlan', 'gre', 'ipip']) &&
                            $running === 'true' &&
                            (strpos($ifName, 'ether') !== false || strpos($ifName, 'sfp') !== false)
                        ) {
                            try {
                                $trafficReq = new RouterOS\Request('/interface/monitor-traffic');
                                $trafficReq->setArgument('interface', $ifName);
                                $trafficReq->setArgument('once', '');
                                $trafficResult = $client->sendSync($trafficReq);
                                foreach ($trafficResult as $traffic) {
                                    $totalRxRate += intval($traffic->getProperty('rx-bits-per-second'));
                                    $totalTxRate += intval($traffic->getProperty('tx-bits-per-second'));
                                    break;
                                }
                            } catch (Exception $bwErr) {
                                // Skip interface error
                            }
                        }
                    }
                } else {
                    // Ambil nilai terakhir dari database agar tidak null
                    $lastStatus = ORM::for_table('tbl_router_status')
                        ->where('router_id', $router['id'])
                        ->find_one();
                    if ($lastStatus) {
                        $totalRxRate = 0; // Tidak perlu update, skip simpan bandwidth
                        $totalTxRate = 0;
                    }
                }

                // ✅ HITUNG TOTAL ONLINE & REGISTERED
                $totalOnline     = 0;
                $totalRegistered = 0;
                foreach ($hotspotUsers as $u) {
                    $un = $u->getProperty('user');
                    if (!empty($un) && $un !== 'null') $totalOnline++;
                }
                foreach ($hotspotSecrets as $s) {
                    $sn = $s->getProperty('name');
                    if (!empty($sn) && $sn !== 'null') $totalRegistered++;
                }

                // ✅ SIMPAN ROUTER STATUS KE DATABASE
                $routerStatus = ORM::for_table('tbl_router_status')
                    ->where('router_id', $router['id'])
                    ->find_one();

                if (!$routerStatus) {
                    $routerStatus            = ORM::for_table('tbl_router_status')->create();
                    $routerStatus->router_id = $router['id'];
                }

                $routerStatus->router_name       = $router['name'];
                $routerStatus->cpu_load          = $cpuLoad;
                $routerStatus->memory_percent    = $memoryPercent;
                $routerStatus->memory_used       = formatBytesTraffic($memoryUsed);
                $routerStatus->memory_total      = formatBytesTraffic($totalMem);
                $routerStatus->uptime            = $routerUptime;
                $routerStatus->board_name        = $boardName;
                $routerStatus->version           = $version;
                $routerStatus->total_online      = $totalOnline;
                $routerStatus->total_registered  = $totalRegistered;
                $routerStatus->last_updated      = date('Y-m-d H:i:s');

                // ✅ HANYA UPDATE BANDWIDTH SAAT isHeavyRun — jangan timpa nilai lama dengan 0
                if ($isHeavyRun) {
                    $estimatedMax     = 1000000000;
                    $currentTotalRate = $totalRxRate + $totalTxRate;
                    $bandwidthPercent = round(($currentTotalRate / $estimatedMax) * 100, 1);

                    $routerStatus->bandwidth_percent = $bandwidthPercent;
                    $routerStatus->current_upload    = formatBitsPerSecond($totalTxRate);
                    $routerStatus->current_download  = formatBitsPerSecond($totalRxRate);
                }

                $routerStatus->save();

                if (!$silent) echo "Router status saved to database<br>";

            } catch (Exception $sysErr) {
                error_log("TRAFFIC MONITOR: System resource error: " . $sysErr->getMessage());
            }

            // ✅ PROCESS SEMUA USER
            $processedUsers = 0;
            foreach ($hotspotUsers as $hotspotUser) {
                $username = $hotspotUser->getProperty('user');
                if (empty($username) || $username === 'null' || $username === 'NULL') continue;

                // Ambil customer data
                $customerId  = null;
                $userComment = '-';
                $normalizedUsername = trafficMonitorNormalizeUsername($username);
                if (isset($customerIdMap[$normalizedUsername])) {
                    $customerId  = $customerIdMap[$normalizedUsername]['id'];
                    $userComment = $customerIdMap[$normalizedUsername]['fullname'];
                }

                if (!$customerId) {
                    error_log("TRAFFIC MONITOR: customer_id not found for username: $username - skipping");
                    continue;
                }

                // Override comment dari secret jika ada
                if (isset($secretComments[$username])) {
                    $userComment = $secretComments[$username];
                }

                // Ambil IP dan uptime dari Hotspot active
                $ipAddress = $hotspotUser->getProperty('address') ?: '-';
                $uptime    = $hotspotUser->getProperty('uptime') ?: '-';
                
                // Ambil bytes dari Hotspot active
                $currentTx = intval($hotspotUser->getProperty('bytes-in')); // Router perspective: in = upload
                $currentRx = intval($hotspotUser->getProperty('bytes-out')); // out = download

                updateUserStatus($userComment, $username, $currentTx, $currentRx, $silent, $ipAddress, $uptime, $router['name'], intval($router['id']));
                $processedUsers++;
            }

            // ✅ TANDAI USER OFFLINE (tidak ada di hotspot/active)
            // SCOPED to current router only to avoid cross-router interference
            $activeUsernames = array();
            foreach ($hotspotUsers as $hotspotUser) {
                $un = $hotspotUser->getProperty('user');
                if (!empty($un) && $un !== 'null') $activeUsernames[] = $un;
            }

            $allStatus = ORM::for_table('tbl_user_connection_status')
                ->where('was_connected', 1)
                ->where('router_id', intval($router['id']))
                ->find_many();

            foreach ($allStatus as $status) {
                if (!in_array($status['username'], $activeUsernames)) {

                    $lastTx = intval($status['last_tx']);
                    $lastRx = intval($status['last_rx']);

                    // Ambil history record dulu
                    $historyRecord = ORM::for_table('tbl_user_usage_history')
                        ->where('customer_id', $status['customer_id'])
                        ->find_one();

                    // SIMPAN KE HISTORY DULU sebelum reset!
                    if ($lastTx > 0 || $lastRx > 0) {

                        // Ambil periode billing aktif customer
                        $billingPeriod = getBillingPeriod($status['customer_id']);

                        if ($historyRecord) {
                            // Tambahkan ke history yang sudah ada
                            $historyRecord->total_upload    = intval($historyRecord->total_upload) + $lastTx;
                            $historyRecord->total_download  = intval($historyRecord->total_download) + $lastRx;
                            $historyRecord->last_updated    = date('Y-m-d H:i:s');
                            $historyRecord->last_disconnect = date('Y-m-d H:i:s');
                            $historyRecord->save();

                            // Simpan snapshot ke periode billing aktif
                            saveMonthlySnapshot(
                                $status['customer_id'],
                                $status['username'],
                                $status['user_comment'],
                                intval($historyRecord->total_upload),
                                intval($historyRecord->total_download),
                                $billingPeriod['year'],
                                $billingPeriod['month']
                            );
                        } else {
                            // Buat baru kalau belum ada
                            $historyRecord                   = ORM::for_table('tbl_user_usage_history')->create();
                            $historyRecord->customer_id      = $status['customer_id'];
                            $historyRecord->username         = $status['username'];
                            $historyRecord->total_upload     = $lastTx;
                            $historyRecord->total_download   = $lastRx;
                            $historyRecord->last_updated     = date('Y-m-d H:i:s');
                            $historyRecord->last_disconnect  = date('Y-m-d H:i:s');
                            $historyRecord->save();

                            // Simpan snapshot ke periode billing aktif
                            saveMonthlySnapshot(
                                $status['customer_id'],
                                $status['username'],
                                $status['user_comment'],
                                $lastTx,
                                $lastRx,
                                $billingPeriod['year'],
                                $billingPeriod['month']
                            );
                        }

                        error_log("OFFLINE DETECTED: Saved usage for {$status['username']} - TX: $lastTx, RX: $lastRx (period: {$billingPeriod['year']}-{$billingPeriod['month']})");

                    } elseif ($historyRecord && (intval($historyRecord['total_upload']) > 0 || intval($historyRecord['total_download']) > 0)) {
                        // last_tx/last_rx = 0 tapi ada data di usage_history
                        $billingPeriod = getBillingPeriod($status['customer_id']);
                        saveMonthlySnapshot(
                            $status['customer_id'],
                            $status['username'],
                            $status['user_comment'],
                            intval($historyRecord['total_upload']),
                            intval($historyRecord['total_download']),
                            $billingPeriod['year'],
                            $billingPeriod['month']
                        );
                        error_log("OFFLINE DETECTED (from history): Saved snapshot for {$status['username']} period {$billingPeriod['year']}-{$billingPeriod['month']}");
                    }

                    // BARU set offline dan reset counter
                    $status->was_connected     = 0;
                    $status->last_tx           = 0;
                    $status->last_rx           = 0;
                    $status->needs_fresh_start = 1;
                    $status->save();
                }
            }

            if (!$silent) {
                echo "<br>Completed! Processed $processedUsers users.<br><br>";
            } else {
                error_log("TRAFFIC MONITOR: Successfully processed $processedUsers users at " . date('Y-m-d H:i:s'));
            }

        } catch (Exception $routerErr) {
            error_log("TRAFFIC MONITOR: Router {$router['name']} error: " . $routerErr->getMessage());
            if (!$silent) echo "Error on router {$router['name']}: " . $routerErr->getMessage() . "<br>";
        }
    }

} catch (Exception $e) {
    $errorMsg = "TRAFFIC MONITOR ERROR: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine();

    if (!$silent) {
        echo "ERROR: " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    }

    // Log error untuk monitoring
    error_log($errorMsg);
}

// Output untuk cron success check
if ($silent) {
    echo "OK";
}

// ✅ AUTO CLEANUP OLD MONTHLY HISTORY (> 3 bulan)
if ($isHeavyRun) {
    try {
        // Ambil semua customer yang punya data di history monthly
        $allHistory = ORM::for_table('tbl_usage_history_monthly')->find_many();

        $deletedCount = 0;
        foreach ($allHistory as $record) {
            $customerId = $record['customer_id'];

            // Ambil expiration aktif customer
            $recharge = ORM::for_table('tbl_user_recharges')
                ->where('customer_id', $customerId)
                ->where('status', 'on')
                ->find_one();

            if (!$recharge || empty($recharge['expiration'])) continue;

            // Hitung bulan aktif billing customer
            $activeYear  = intval(date('Y', strtotime($recharge['expiration'])));
            $activeMonth = intval(date('m', strtotime($recharge['expiration'])));

            // Hitung selisih bulan antara record dan bulan aktif
            $recordYear  = intval($record['period_year']);
            $recordMonth = intval($record['period_month']);

            $monthsDiff = ($activeYear - $recordYear) * 12 + ($activeMonth - $recordMonth);

            // Hapus jika lebih dari 3 bulan yang lalu
            if ($monthsDiff > 3) {
                ORM::for_table('tbl_usage_history_monthly')
                    ->where('id', $record['id'])
                    ->find_one()
                    ->delete();

                $deletedCount++;
                error_log("HISTORY CLEANUP: Deleted old record for {$record['username']} period {$recordYear}-{$recordMonth} (active: {$activeYear}-{$activeMonth})");
            }
        }

        if ($deletedCount > 0) {
            error_log("HISTORY CLEANUP: Deleted {$deletedCount} old monthly history records");
        }

    } catch (Exception $e) {
        error_log("HISTORY CLEANUP ERROR: " . $e->getMessage());
    }
}

// ✅ HELPER FUNCTIONS
function formatBytesTraffic($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function formatBitsPerSecond($bps) {
    $units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    $bps   = max($bps, 0);
    $pow   = floor(($bps ? log($bps) : 0) / log(1000));
    $pow   = min($pow, count($units) - 1);
    $bps  /= pow(1000, $pow);
    return round($bps, 2) . ' ' . $units[$pow];
}


// =============== EXPIRATION TRACKING FUNCTIONS ===============

function loadExpirationTracking()
{
    // Load from database instead of JSON
    $trackingData = ORM::for_table('tbl_expiration_tracking')->find_many();

    $result = [
        'customers' => [],
        'last_scan' => date('Y-m-d H:i:s'),
        'stats' => [
            'total_checks' => 0,
            'total_resets' => 0
        ]
    ];

    foreach ($trackingData as $tracking) {
        $result['customers'][$tracking['customer_id']] = [
            'last_expiration' => $tracking['last_expiration'],
            'username' => $tracking['username'],
            'last_check' => $tracking['last_check'],
            'last_reset' => $tracking['last_reset'],
            'reset_count' => $tracking['reset_count']
        ];
        $result['stats']['total_resets'] += $tracking['reset_count'];
    }

    return $result;
}

function saveExpirationTracking($data)
{
    // Save to database instead of JSON
    foreach ($data['customers'] as $customerId => $customerData) {
        $existing = ORM::for_table('tbl_expiration_tracking')
            ->where('customer_id', $customerId)
            ->find_one();

        if ($existing) {
            $existing->last_expiration = $customerData['last_expiration'];
            $existing->username = $customerData['username'];
            $existing->last_check = $customerData['last_check'];
            if (isset($customerData['last_reset'])) {
                $existing->last_reset = $customerData['last_reset'];
            }
            if (isset($customerData['reset_count'])) {
                $existing->reset_count = $customerData['reset_count'];
            }
            $existing->save();
        } else {
            $new = ORM::for_table('tbl_expiration_tracking')->create();
            $new->customer_id = $customerId;
            $new->username = $customerData['username'];
            $new->last_expiration = $customerData['last_expiration'];
            $new->last_check = $customerData['last_check'];
            $new->last_reset = $customerData['last_reset'] ?? null;
            $new->reset_count = $customerData['reset_count'] ?? 0;
            $new->save();
        }
    }
}

function checkAdminRecharge($silent = false)
{
    if (!$silent) echo "Checking for expiration changes...<br>";

    $tracking = loadExpirationTracking();
    $recharges = ORM::for_table('tbl_user_recharges')
        ->where('status', 'on')
        ->find_many();

    $resetCount = 0;

    foreach ($recharges as $recharge) {
        $customerId = $recharge['customer_id'];
        $currentExpiration = $recharge['expiration'];
        $username = $recharge['username'];

        // Get last known expiration
        $lastKnownExpiration = isset($tracking['customers'][$customerId])
            ? $tracking['customers'][$customerId]['last_expiration']
            : null;

        // Detect changes
        if ($lastKnownExpiration && $currentExpiration !== $lastKnownExpiration) {
            // Hitung selisih hari antara expiration lama dan baru
            $daysDiff = (strtotime($currentExpiration) - strtotime($lastKnownExpiration)) / (24 * 60 * 60);

            // Logika:
            // daysDiff <= 0  = tidak ada perubahan nyata / sama → skip
            // daysDiff 1-7   = extend (tambah beberapa hari) → TIDAK reset
            // daysDiff > 7   = recharge bulan baru → RESET usage
            if ($daysDiff <= 0) {
                // Tidak ada perubahan nyata — skip
                if (!$silent) echo "No change detected for $username - skipping<br>";
                error_log("NO CHANGE: $username - daysDiff={$daysDiff}, skipping");
            } elseif ($daysDiff <= 7) {
                // INI EXTEND - JANGAN RESET USAGE
                if (!$silent) echo "Extend detected for $username - usage preserved (${daysDiff} days added)<br>";
                error_log("EXTEND DETECTED: $username - {$daysDiff} days added, usage preserved");
            } else {
                // INI RECHARGE BIASA - RESET USAGE
                $oldMonth = date('Y-m', strtotime($lastKnownExpiration));
                $newMonth = date('Y-m', strtotime($currentExpiration));

                if (!$silent) echo "Recharge detected for $username ($oldMonth -> $newMonth, ${daysDiff} days)<br>";
                error_log("RECHARGE DETECTED: $username - {$daysDiff} days added, resetting usage");

                // Get customer for reset
                $customer = ORM::for_table('tbl_customers')->find_one($customerId);
                if ($customer) {
                    $userComment = trim($customer['fullname']);

                    // Reset usage
                    resetUsageForAdminRecharge($userComment, $silent);
                    $resetCount++;

                    // Update tracking
                    $tracking['customers'][$customerId]['last_reset'] = date('Y-m-d H:i:s');
                    $tracking['customers'][$customerId]['reset_count'] =
                        ($tracking['customers'][$customerId]['reset_count'] ?? 0) + 1;
                }
            }
        }

        // Update tracking
        $tracking['customers'][$customerId] = array_merge(
            $tracking['customers'][$customerId] ?? [],
            [
                'last_expiration' => $currentExpiration,
                'username' => $username,
                'last_check' => date('Y-m-d H:i:s')
            ]
        );
    }

    // Update stats
    $tracking['stats']['total_checks'] = ($tracking['stats']['total_checks'] ?? 0) + 1;
    $tracking['stats']['total_resets'] = ($tracking['stats']['total_resets'] ?? 0) + $resetCount;

    saveExpirationTracking($tracking);

    if (!$silent) echo "Expiration check completed. Resets executed: $resetCount<br>";

    return $resetCount;
}

function resetUsageForAdminRecharge($userComment, $silent = false)
{
    if (!$silent) echo "&nbsp;&nbsp;Resetting usage for: $userComment<br>";

    // ✅ LOOKUP customer_id dari tbl_customers berdasarkan fullname
    $customerObj = ORM::for_table('tbl_customers')
        ->where('fullname', $userComment)
        ->find_one();
    $customerId = $customerObj ? intval($customerObj['id']) : null;

    if (!$customerId) {
        error_log("ADMIN RECHARGE RESET: customer_id not found for userComment: $userComment - skipping");
        if (!$silent) echo "&nbsp;&nbsp;Customer not found for: $userComment<br>";
        return;
    }

    // BUAT STOP FILE
    $stopFile = dirname(__DIR__) . '/system/plugin/data/cron_stop.txt';
    file_put_contents($stopFile, "Admin recharge reset in progress for: $userComment\nTime: " . date('Y-m-d H:i:s'));

    sleep(2);

    // RESET HISTORY DATABASE
    $historyRecord = ORM::for_table('tbl_user_usage_history')
        ->where('customer_id', $customerId)
        ->find_one();

    // Ambil periode billing LAMA dari expiration tracking
    $tracking = ORM::for_table('tbl_expiration_tracking')
        ->where('customer_id', $customerId)
        ->find_one();

    $oldPeriodYear  = intval(date('Y'));
    $oldPeriodMonth = intval(date('m'));

    if ($tracking && !empty($tracking['last_expiration'])) {
        $oldPeriodYear  = intval(date('Y', strtotime($tracking['last_expiration'])));
        $oldPeriodMonth = intval(date('m', strtotime($tracking['last_expiration'])));
    }

    // Ambil juga data dari tbl_user_connection_status (last_tx/last_rx)
    $statusRows = ORM::for_table('tbl_user_connection_status')
        ->where('customer_id', $customerId)
        ->find_many();

    $currentTx = 0;
    $currentRx = 0;
    foreach ($statusRows as $row) {
        $currentTx += intval($row['last_tx']);
        $currentRx += intval($row['last_rx']);
    }

    if ($historyRecord) {
        // Gabungkan history + current session
        $totalUp   = intval($historyRecord['total_upload'])   + $currentTx;
        $totalDown = intval($historyRecord['total_download'])  + $currentRx;

        // Simpan snapshot ke periode billing LAMA sebelum dihapus
        saveMonthlySnapshot(
            $customerId,
            $customerObj['pppoe_username'] ?? '',
            $userComment,
            $totalUp,
            $totalDown,
            $oldPeriodYear,
            $oldPeriodMonth
        );
        $historyRecord->delete();
        if (!$silent) echo "&nbsp;&nbsp;History cleared from database<br>";
    } elseif ($currentTx > 0 || $currentRx > 0) {
        // Tidak ada history tapi ada current session — simpan dari connection_status
        saveMonthlySnapshot(
            $customerId,
            $customerObj['pppoe_username'] ?? '',
            $userComment,
            $currentTx,
            $currentRx,
            $oldPeriodYear,
            $oldPeriodMonth
        );
        if (!$silent) echo "&nbsp;&nbsp;Snapshot from current session saved<br>";
    }

    // RESET STATUS DATABASE
    if (!empty($statusRows)) {
        ORM::for_table('tbl_user_connection_status')
            ->where('customer_id', $customerId)
            ->delete_many();
        if (!$silent) echo "&nbsp;&nbsp;Status cleared from database<br>";
    }

    sleep(2);

    // ✅ RESET INTERFACE PPPOE
    try {
        // Get router
        $router = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one();
        if ($router) {
            $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

            // ✅ Gunakan pppoe_username langsung dari tbl_customers
            $pppoeUsername = $customerObj['pppoe_username'];
            if (!empty($pppoeUsername)) {
                $interfaceName = "<pppoe-$pppoeUsername>";

                // Reset interface counter
                $resetRequest = new RouterOS\Request('/interface/reset-counters');
                $resetRequest->setArgument('numbers', $interfaceName);
                $client->sendSync($resetRequest);

                if (!$silent) echo "&nbsp;&nbsp;Interface reset: $interfaceName<br>";
                error_log("ADMIN RECHARGE: Interface counter reset for $pppoeUsername ($userComment)");
            } else {
                error_log("ADMIN RECHARGE: No pppoe_username found for customer: $userComment");
                if (!$silent) echo "&nbsp;&nbsp;No PPPoE username found for: $userComment<br>";
            }
        }
    } catch (Exception $e) {
        error_log("ADMIN RECHARGE: Interface reset error: " . $e->getMessage());
        if (!$silent) echo "&nbsp;&nbsp;Interface reset failed: " . $e->getMessage() . "<br>";
    }

    // HAPUS STOP FILE
    if (file_exists($stopFile)) {
        unlink($stopFile);
    }

    error_log("ADMIN RECHARGE RESET: Complete reset for user: $userComment");
}

// =============== MONTHLY SNAPSHOT FUNCTION ===============

function saveMonthlySnapshot($customerId, $username, $userComment, $totalUpload, $totalDownload, $periodYear = null, $periodMonth = null)
{
    if ($totalUpload <= 0 && $totalDownload <= 0) return;

    // Gunakan periode yang diberikan, fallback ke bulan kalender jika tidak ada
    $year  = $periodYear  ? intval($periodYear)  : intval(date('Y'));
    $month = $periodMonth ? intval($periodMonth) : intval(date('m'));

    // Cek apakah sudah ada record bulan ini
    $existing = ORM::for_table('tbl_usage_history_monthly')
        ->where('customer_id', $customerId)
        ->where('period_year', $year)
        ->where('period_month', $month)
        ->find_one();

    if ($existing) {
        // Update jika usage sekarang lebih besar
        $newUpload   = max(intval($existing['total_upload']), $totalUpload);
        $newDownload = max(intval($existing['total_download']), $totalDownload);
        $existing->total_upload   = $newUpload;
        $existing->total_download = $newDownload;
        $existing->total_bytes    = $newUpload + $newDownload;
        $existing->recorded_at    = date('Y-m-d H:i:s');
        $existing->save();
    } else {
        // Buat record baru
        $record               = ORM::for_table('tbl_usage_history_monthly')->create();
        $record->customer_id  = $customerId;
        $record->username     = $username;
        $record->user_comment = $userComment;
        $record->period_year  = $year;
        $record->period_month = $month;
        $record->total_upload   = $totalUpload;
        $record->total_download = $totalDownload;
        $record->total_bytes    = $totalUpload + $totalDownload;
        $record->recorded_at  = date('Y-m-d H:i:s');
        $record->save();
    }

    error_log("MONTHLY SNAPSHOT: Saved for $username ($year-$month) - UP: $totalUpload, DOWN: $totalDownload");
}

// Helper: ambil periode billing aktif customer dari expiration
function getBillingPeriod($customerId)
{
    $recharge = ORM::for_table('tbl_user_recharges')
        ->where('customer_id', $customerId)
        ->where('status', 'on')
        ->find_one();

    if ($recharge && !empty($recharge['expiration'])) {
        // Periode billing = bulan dari expiration aktif
        // Contoh: expiration 2026-04-20 → periode April
        return [
            'year'  => intval(date('Y', strtotime($recharge['expiration']))),
            'month' => intval(date('m', strtotime($recharge['expiration'])))
        ];
    }

    // Fallback ke bulan kalender
    return [
        'year'  => intval(date('Y')),
        'month' => intval(date('m'))
    ];
}

// Function to create required database tables
function createTrafficMonitorTables() {
    try {
        // Check if ORM is available
        if (!class_exists('ORM')) {
            return false;
        }

        // Check if tables exist, if not create them
        $db = ORM::get_db();

        // Create tbl_user_connection_status table
        $connectionTableExists = $db->query("SHOW TABLES LIKE 'tbl_user_connection_status'")->fetch();
        if (!$connectionTableExists) {
            $connectionTableSQL = "CREATE TABLE `tbl_user_connection_status` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `customer_id` int(11) DEFAULT NULL,
              `router_id` int(11) NOT NULL DEFAULT 0,
              `username` varchar(100) DEFAULT NULL,
              `user_comment` varchar(255) DEFAULT NULL,
              `ip_address` varchar(50) DEFAULT NULL,
              `uptime` varchar(50) DEFAULT NULL,
              `was_connected` tinyint(1) DEFAULT 0,
              `last_tx` bigint(20) DEFAULT 0,
              `last_rx` bigint(20) DEFAULT 0,
              `last_check` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `needs_fresh_start` tinyint(1) DEFAULT 0,
              `created_at` datetime DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_customer_router` (`customer_id`,`router_id`),
              KEY `idx_username` (`username`),
              KEY `idx_router_id` (`router_id`),
              KEY `idx_was_connected` (`was_connected`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $db->exec($connectionTableSQL);
            if (function_exists('_log')) {
                _log('Created table: tbl_user_connection_status', 'System', 1);
            }
        }

        // Ensure router-scoped status rows to prevent cross-router overwrite.
        $routerIdColumn = $db->query("SHOW COLUMNS FROM `tbl_user_connection_status` LIKE 'router_id'")->fetch();
        if (!$routerIdColumn) {
            $db->exec("ALTER TABLE `tbl_user_connection_status` ADD COLUMN `router_id` int(11) NOT NULL DEFAULT 0 AFTER `customer_id`");
        } else {
            $db->exec("UPDATE `tbl_user_connection_status` SET `router_id` = 0 WHERE `router_id` IS NULL");
            $db->exec("ALTER TABLE `tbl_user_connection_status` MODIFY COLUMN `router_id` int(11) NOT NULL DEFAULT 0");
        }

        // Backfill router_id for legacy rows that only had router_name.
        $routerNameColumn = $db->query("SHOW COLUMNS FROM `tbl_user_connection_status` LIKE 'router_name'")->fetch();
        if ($routerNameColumn) {
            $db->exec("UPDATE `tbl_user_connection_status` s
                INNER JOIN `tbl_routers` r ON r.`name` = s.`router_name`
                SET s.`router_id` = r.`id`
                WHERE s.`router_id` = 0 AND s.`router_name` IS NOT NULL AND s.`router_name` <> ''");
        }

        $legacyUnique = $db->query("SHOW INDEX FROM `tbl_user_connection_status` WHERE Key_name='unique_customer'")->fetch();
        if ($legacyUnique) {
            $db->exec("ALTER TABLE `tbl_user_connection_status` DROP INDEX `unique_customer`");
        }

        $scopedUnique = $db->query("SHOW INDEX FROM `tbl_user_connection_status` WHERE Key_name='unique_customer_router'")->fetch();
        if (!$scopedUnique) {
            $db->exec("ALTER TABLE `tbl_user_connection_status` ADD UNIQUE KEY `unique_customer_router` (`customer_id`,`router_id`)");
        }

        $routerIdx = $db->query("SHOW INDEX FROM `tbl_user_connection_status` WHERE Key_name='idx_router_id'")->fetch();
        if (!$routerIdx) {
            $db->exec("ALTER TABLE `tbl_user_connection_status` ADD KEY `idx_router_id` (`router_id`)");
        }

        // Create tbl_user_usage_history table
        $usageTableExists = $db->query("SHOW TABLES LIKE 'tbl_user_usage_history'")->fetch();
        if (!$usageTableExists) {
            $usageTableSQL = "CREATE TABLE `tbl_user_usage_history` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `customer_id` int(11) DEFAULT NULL,
              `username` varchar(100) DEFAULT NULL,
              `total_upload` bigint(20) DEFAULT 0,
              `total_download` bigint(20) DEFAULT 0,
              `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `last_disconnect` datetime DEFAULT NULL,
              `created_at` datetime DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_customer` (`customer_id`),
              KEY `idx_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $db->exec($usageTableSQL);
            if (function_exists('_log')) {
                _log('Created table: tbl_user_usage_history', 'System', 1);
            }
        }

        // Create tbl_router_status table
        $routerTableExists = $db->query("SHOW TABLES LIKE 'tbl_router_status'")->fetch();
        if (!$routerTableExists) {
            $routerTableSQL = "CREATE TABLE `tbl_router_status` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `router_id` int(11) DEFAULT NULL,
              `router_name` varchar(100) DEFAULT NULL,
              `cpu_load` int(11) DEFAULT NULL,
              `memory_usage` int(11) DEFAULT NULL,
              `memory_percent` decimal(5,2) DEFAULT NULL,
              `memory_used` varchar(50) DEFAULT NULL,
              `memory_total` varchar(50) DEFAULT NULL,
              `bandwidth` varchar(50) DEFAULT NULL,
              `bandwidth_percent` decimal(5,2) DEFAULT NULL,
              `current_upload` varchar(50) DEFAULT NULL,
              `current_download` varchar(50) DEFAULT NULL,
              `uptime` varchar(50) DEFAULT NULL,
              `board_name` varchar(100) DEFAULT NULL,
              `version` varchar(50) DEFAULT NULL,
              `total_online` int(11) DEFAULT 0,
              `total_registered` int(11) DEFAULT 0,
              `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_router_id` (`router_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $db->exec($routerTableSQL);
            if (function_exists('_log')) {
                _log('Created table: tbl_router_status', 'System', 1);
            }
        }

        // Create tbl_usage_history_monthly table
        $monthlyTableExists = $db->query("SHOW TABLES LIKE 'tbl_usage_history_monthly'")->fetch();
        if (!$monthlyTableExists) {
            $monthlyTableSQL = "CREATE TABLE `tbl_usage_history_monthly` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `customer_id` int(11) DEFAULT NULL,
              `username` varchar(100) DEFAULT NULL,
              `period_month` int(2) DEFAULT NULL,
              `period_year` int(4) DEFAULT NULL,
              `total_upload` bigint(20) DEFAULT 0,
              `total_download` bigint(20) DEFAULT 0,
              `total_bytes` bigint(20) DEFAULT 0,
              `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `created_at` datetime DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_user_month_year` (`customer_id`,`period_month`,`period_year`),
              KEY `idx_username` (`username`),
              KEY `idx_month_year` (`period_month`,`period_year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            $db->exec($monthlyTableSQL);
            if (function_exists('_log')) {
                _log('Created table: tbl_usage_history_monthly', 'System', 1);
            }
        }

        $monthlyUserComment = $db->query("SHOW COLUMNS FROM `tbl_usage_history_monthly` LIKE 'user_comment'")->fetch();
        if (!$monthlyUserComment) {
            $db->exec("ALTER TABLE `tbl_usage_history_monthly` ADD COLUMN `user_comment` varchar(255) DEFAULT NULL AFTER `username`");
        }

        $monthlyRecordedAt = $db->query("SHOW COLUMNS FROM `tbl_usage_history_monthly` LIKE 'recorded_at'")->fetch();
        if (!$monthlyRecordedAt) {
            $db->exec("ALTER TABLE `tbl_usage_history_monthly` ADD COLUMN `recorded_at` datetime DEFAULT current_timestamp() AFTER `total_bytes`");
        }

        $userHistoryUsername = $db->query("SHOW COLUMNS FROM `tbl_user_usage_history` LIKE 'username'")->fetch();
        if (!$userHistoryUsername) {
            $db->exec("ALTER TABLE `tbl_user_usage_history` ADD COLUMN `username` varchar(100) DEFAULT NULL AFTER `customer_id`");
        }

        $userHistoryLastUpdated = $db->query("SHOW COLUMNS FROM `tbl_user_usage_history` LIKE 'last_updated'")->fetch();
        if (!$userHistoryLastUpdated) {
            $db->exec("ALTER TABLE `tbl_user_usage_history` ADD COLUMN `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `total_download`");
        }

        $userHistoryLastDisconnect = $db->query("SHOW COLUMNS FROM `tbl_user_usage_history` LIKE 'last_disconnect'")->fetch();
        if (!$userHistoryLastDisconnect) {
            $db->exec("ALTER TABLE `tbl_user_usage_history` ADD COLUMN `last_disconnect` datetime DEFAULT NULL AFTER `last_updated`");
        }

        // Create tbl_expiration_tracking table
        $expTableExists = $db->query("SHOW TABLES LIKE 'tbl_expiration_tracking'")->fetch();
        if (!$expTableExists) {
            $expTableSQL = "CREATE TABLE `tbl_expiration_tracking` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `customer_id` int(11) NOT NULL,
              `username` varchar(100) DEFAULT NULL,
              `last_expiration` datetime DEFAULT NULL,
              `last_check` datetime DEFAULT NULL,
              `last_reset` datetime DEFAULT NULL,
              `reset_count` int(11) DEFAULT 0,
              `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_customer` (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $db->exec($expTableSQL);
        }

        return true;
    } catch (Exception $e) {
        if (function_exists('_log')) {
            _log('Error creating traffic monitor tables: ' . $e->getMessage(), 'System', 3);
        }
        error_log('Traffic Monitor Tables Error: ' . $e->getMessage());
        return false;
    }
}

