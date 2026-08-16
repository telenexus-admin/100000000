<?php

use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Request;

// Register plugin menu (hidden - hanya untuk API)
register_menu("User Traffic API", false, "user_traffic_api", '');


function user_traffic_api()
{
    // Handle reset by customer ID (untuk recharge success)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_id']) && !empty($_POST['customer_id'])) {
        header('Content-Type: application/json');

        try {
            $customerId = intval($_POST['customer_id']);

            // ✅ TAMBAH LOG DEBUG
            error_log("=== PAYMENT RESET FLOW ===");
            error_log("Customer ID: $customerId");

            // CEK APAKAH INI EXTEND ATAU PEMBAYARAN NORMAL
            $isExtend = isset($_POST['is_extend']) && $_POST['is_extend'] === 'true';

            // Get customer data
            $customer = ORM::for_table('tbl_customers')->find_one($customerId);
            if (!$customer) {
                throw new Exception('Customer not found');
            }

            $userComment = trim($customer['fullname']);
            if (empty($userComment)) {
                throw new Exception('No customer fullname found');
            }

            error_log("User Comment: $userComment");

            if ($isExtend) {
                // EXTEND: TIDAK RESET USAGE
                echo json_encode([
                    'success' => true,
                    'message' => 'Extension successful - Usage data preserved',
                    'customer_id' => $customerId,
                    'user_comment' => $userComment,
                    'action' => 'extend',
                    'interface_reset' => false,
                    'files_reset' => false,
                    'usage_preserved' => true
                ]);
            } else {
                // PEMBAYARAN NORMAL ATAU ADMIN RECHARGE: RESET TOTAL
                error_log("About to call user_traffic_resetUsageHistory()");

                // STEP 1: Reset JSON history files
                user_traffic_resetUsageHistory($customerId);

                error_log("user_traffic_resetUsageHistory() completed");

                // STEP 2: Reset interface counter (TRY-CATCH untuk safety)
                $resetResult = ['interface_reset' => false, 'router_info' => 'N/A', 'error' => null];
                try {
                    $resetResult = user_traffic_resetInterfaceAfterRecharge($customerId);
                } catch (Exception $e) {
                    error_log("Interface reset error: " . $e->getMessage());
                    $resetResult['error'] = $e->getMessage();
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Payment successful - Complete usage reset',
                    'customer_id' => $customerId,
                    'user_comment' => $userComment,
                    'action' => 'payment',
                    'interface_reset' => $resetResult['interface_reset'],
                    'router_info' => $resetResult['router_info'],
                    'username' => $resetResult['username'],
                    'files_reset' => true,
                    'usage_preserved' => false,
                    'reset_error' => $resetResult['error']
                ]);
            }
        } catch (Exception $e) {
            // PROPER ERROR HANDLING
            error_log("Payment reset error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'customer_id' => isset($customerId) ? $customerId : null
            ]);
        }
        exit;
    }

    // Handle AJAX request untuk get traffic data (GET method)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_data') {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');

        try {
            // Get customer dari session
            if (!isset($_SESSION['uid'])) {
                throw new Exception('Not logged in');
            }

            $customer = ORM::for_table('tbl_customers')->find_one($_SESSION['uid']);
            if (!$customer) {
                throw new Exception('Customer not found');
            }

            $pppoeUsername = $customer['pppoe_username'];
            if (empty($pppoeUsername)) {
                throw new Exception('PPPoE username not configured for customer');
            }

            // ✅ Cek dulu di database apakah user online
            // Ini lebih cepat daripada langsung konek MikroTik
            $statusRecord = ORM::for_table('tbl_user_connection_status')
                ->where('customer_id', $_SESSION['uid'])
                ->find_one();

            // ✅ Find router where user is connected
            $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
            $userRouter = null;

            foreach ($routers as $router) {
                try {
                    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
                    $pppUsers = $client->sendSync(new RouterOS\Request('/ppp/active/print'));

                    foreach ($pppUsers as $pppUser) {
                        if ($pppUser->getProperty('name') === $pppoeUsername) {
                            $userRouter = [
                                'router' => $router,
                                'client' => $client
                            ];
                            break 2;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            if (!$userRouter) {
                throw new Exception('User not online or not found in any router: ' . $pppoeUsername);
            }

            $mikrotik = $userRouter['router'];
            $client   = $userRouter['client'];

            // ✅ Ambil interface traffic langsung dari MikroTik
            $interfaceName    = "<pppoe-$pppoeUsername>";
            $interfaceTraffic = $client->sendSync(new RouterOS\Request('/interface/print'));
            $currentTxBytes   = 0;
            $currentRxBytes   = 0;

            foreach ($interfaceTraffic as $interface) {
                if ($interface->getProperty('name') === $interfaceName) {
                    $currentTxBytes = intval($interface->getProperty('rx-byte')); // Upload user
                    $currentRxBytes = intval($interface->getProperty('tx-byte')); // Download user
                    break;
                }
            }

            // ✅ Ambil historical dari database
            $historicalTx = 0;
            $historicalRx = 0;

            $historyRecord = ORM::for_table('tbl_user_usage_history')
                ->where('customer_id', $_SESSION['uid'])
                ->find_one();

            if ($historyRecord) {
                $historicalTx = intval($historyRecord['total_upload']);
                $historicalRx = intval($historyRecord['total_download']);
            }

            // Total usage = Historical + Current
            $txBytes = $historicalTx + $currentTxBytes;
            $rxBytes = $historicalRx + $currentRxBytes;

            // ✅ Update connection status
            user_traffic_updateConnectionStatus($_SESSION['uid'], $pppoeUsername, $currentTxBytes, $currentRxBytes, $mikrotik['name'], intval($mikrotik['id']));

            // ✅ Ambil live speed dari Simple Queue
            $simpleQueues = $client->sendSync(new RouterOS\Request('/queue/simple/print'));
            $liveUpload   = 0;
            $liveDownload = 0;

            foreach ($simpleQueues as $queue) {
                if ($queue->getProperty('name') === $interfaceName) {
                    $rate = $queue->getProperty('rate');
                    if ($rate && $rate !== '0/0' && strpos($rate, '/') !== false) {
                        $rates        = explode('/', $rate);
                        $liveUpload   = intval($rates[0]);
                        $liveDownload = intval($rates[1]);
                    }
                    break;
                }
            }

            echo json_encode([
                'success'            => true,
                'username'           => $pppoeUsername,
                'comment'            => trim($customer['fullname']),
                'router_name'        => $mikrotik['name'],
                'router_ip'          => $mikrotik['ip_address'],
                'router_id'          => $mikrotik['id'],
                'live_upload'        => user_traffic_formatSpeed($liveUpload),
                'live_download'      => user_traffic_formatSpeed($liveDownload),
                'live_upload_bytes'  => $liveUpload,
                'live_download_bytes' => $liveDownload,
                'total_upload'       => user_traffic_formatBytes($txBytes),
                'total_download'     => user_traffic_formatBytes($rxBytes),
                'total_usage'        => user_traffic_formatBytes($txBytes + $rxBytes),
                'is_active'          => ($liveUpload > 0 || $liveDownload > 0),
                'timestamp'          => time(),
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
        exit;
    }

    // Default response (jika bukan AJAX)
    echo "User Traffic API Plugin";
}

function user_traffic_formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function user_traffic_formatSpeed($bytesPerSecond, $precision = 2)
{
    $units = array('B/s', 'KB/s', 'MB/s', 'GB/s');
    $bytes = max($bytesPerSecond, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}


function user_traffic_updateConnectionStatus($customerId, $username, $currentTx, $currentRx, $routerName = null, $routerId = 0)
{
    // Load current status from database
    $statusQuery = ORM::for_table('tbl_user_connection_status')
        ->where('customer_id', $customerId);
    if (intval($routerId) > 0) {
        $statusQuery->where('router_id', intval($routerId));
    } else {
        $statusQuery->where('router_id', 0);
    }
    $statusRecord = $statusQuery->find_one();

    // AUTO-INITIALIZE user baru
    if (!$statusRecord) {
        $statusRecord                    = ORM::for_table('tbl_user_connection_status')->create();
        $statusRecord->customer_id       = $customerId;
        $statusRecord->router_id         = intval($routerId);
        $statusRecord->username          = $username;
        $statusRecord->was_connected     = 1;
        $statusRecord->last_tx           = $currentTx;
        $statusRecord->last_rx           = $currentRx;
        $statusRecord->last_check        = date('Y-m-d H:i:s');
        $statusRecord->needs_fresh_start = 0;
        $statusRecord->save();
        return;
    }

    // DISCONNECT DETECTION
    $shouldSaveHistory = false;
    $prevConnected     = $statusRecord['was_connected'];
    $prevTx            = $statusRecord['last_tx'];
    $prevRx            = $statusRecord['last_rx'];

    // Grace period 5 menit setelah reset
    $timeSinceLastCheck = time() - strtotime($statusRecord['last_check']);
    $isRecentlyReset    = ($timeSinceLastCheck < 300);

    if (!$isRecentlyReset) {
        if ($prevConnected && ($prevTx > 0 || $prevRx > 0)) {
            $txDropped  = ($prevTx > 100000 && $currentTx < ($prevTx * 0.5));
            $rxDropped  = ($prevRx > 100000 && $currentRx < ($prevRx * 0.5));
            $totalReset = ($currentTx == 0 && $currentRx == 0);

            if ($totalReset || $txDropped || $rxDropped) {
                $shouldSaveHistory = true;
                error_log("TRAFFIC API: Disconnect detected for customer ID: $customerId");
            }
        }
    }

    // Save history jika disconnect detected
    if ($shouldSaveHistory) {
        user_traffic_saveUsageHistory(
            $customerId,
            $statusRecord['last_rx'],
            $statusRecord['last_tx']
        );
    }

    // Update current status
    $statusRecord->username      = $username;
    $statusRecord->was_connected = ($currentTx > 0 || $currentRx > 0) ? 1 : 0;
    $statusRecord->last_tx       = $currentTx;
    $statusRecord->last_rx       = $currentRx;
    $statusRecord->last_check    = date('Y-m-d H:i:s');
    if (intval($routerId) > 0) {
        $statusRecord->router_id = intval($routerId);
    }
    $statusRecord->save();
}

function user_traffic_saveUsageHistory($customerId, $txBytes, $rxBytes)
{
    $historyRecord = ORM::for_table('tbl_user_usage_history')
        ->where('customer_id', $customerId)
        ->find_one();

    if ($historyRecord) {
        $historyRecord->total_upload    = intval($historyRecord['total_upload']) + $txBytes;
        $historyRecord->total_download  = intval($historyRecord['total_download']) + $rxBytes;
        $historyRecord->last_updated    = date('Y-m-d H:i:s');
        $historyRecord->last_disconnect = date('Y-m-d H:i:s');
        $historyRecord->save();
    } else {
        $historyRecord                  = ORM::for_table('tbl_user_usage_history')->create();
        $historyRecord->customer_id     = $customerId;
        $historyRecord->username        = '';
        $historyRecord->total_upload    = $txBytes;
        $historyRecord->total_download  = $rxBytes;
        $historyRecord->last_updated    = date('Y-m-d H:i:s');
        $historyRecord->last_disconnect = date('Y-m-d H:i:s');
        $historyRecord->save();
    }

    error_log("TRAFFIC API: Usage history saved for customer ID: $customerId");
}

function user_traffic_resetUsageHistory($customerId)
{
    $customer    = ORM::for_table('tbl_customers')->find_one($customerId);
    $userComment = $customer ? $customer['fullname'] : 'Unknown';
    $pppoeUsername = $customer ? ($customer['pppoe_username'] ?? '') : '';

    error_log("PAYMENT RESET: Start for user: $userComment (ID: $customerId)");

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

    // Ambil current session dari connection_status (aggregate semua router)
    $statusRows = ORM::for_table('tbl_user_connection_status')
        ->where('customer_id', $customerId)
        ->find_many();

    $currentTx = 0;
    $currentRx = 0;
    foreach ($statusRows as $statusRow) {
        $currentTx += intval($statusRow['last_tx']);
        $currentRx += intval($statusRow['last_rx']);
    }

    // Reset history database
    $historyRecord = ORM::for_table('tbl_user_usage_history')
        ->where('customer_id', $customerId)
        ->find_one();

    if ($historyRecord) {
        // Gabungkan history + current session
        $totalUp   = intval($historyRecord['total_upload'])  + $currentTx;
        $totalDown = intval($historyRecord['total_download']) + $currentRx;

        // Simpan snapshot ke periode billing LAMA
        if ($totalUp > 0 || $totalDown > 0) {
            $existing = ORM::for_table('tbl_usage_history_monthly')
                ->where('customer_id', $customerId)
                ->where('period_year', $oldPeriodYear)
                ->where('period_month', $oldPeriodMonth)
                ->find_one();

            if ($existing) {
                $existing->total_upload   = max(intval($existing['total_upload']), $totalUp);
                $existing->total_download = max(intval($existing['total_download']), $totalDown);
                $existing->total_bytes    = $existing->total_upload + $existing->total_download;
                $existing->recorded_at    = date('Y-m-d H:i:s');
                $existing->save();
            } else {
                $snapshot               = ORM::for_table('tbl_usage_history_monthly')->create();
                $snapshot->customer_id  = $customerId;
                $snapshot->username     = $pppoeUsername;
                $snapshot->user_comment = $userComment;
                $snapshot->period_year  = $oldPeriodYear;
                $snapshot->period_month = $oldPeriodMonth;
                $snapshot->total_upload   = $totalUp;
                $snapshot->total_download = $totalDown;
                $snapshot->total_bytes    = $totalUp + $totalDown;
                $snapshot->recorded_at  = date('Y-m-d H:i:s');
                $snapshot->save();
            }

            error_log("PAYMENT RESET: Monthly snapshot saved for $userComment ({$oldPeriodYear}-{$oldPeriodMonth}) - UP: $totalUp, DOWN: $totalDown");
        }

        $historyRecord->delete();
        error_log("PAYMENT RESET: History cleared for customer ID: $customerId");
    } elseif ($currentTx > 0 || $currentRx > 0) {
        // Tidak ada history tapi ada current session
        $existing = ORM::for_table('tbl_usage_history_monthly')
            ->where('customer_id', $customerId)
            ->where('period_year', $oldPeriodYear)
            ->where('period_month', $oldPeriodMonth)
            ->find_one();

        if ($existing) {
            $existing->total_upload   = max(intval($existing['total_upload']), $currentTx);
            $existing->total_download = max(intval($existing['total_download']), $currentRx);
            $existing->total_bytes    = $existing->total_upload + $existing->total_download;
            $existing->recorded_at    = date('Y-m-d H:i:s');
            $existing->save();
        } else {
            $snapshot               = ORM::for_table('tbl_usage_history_monthly')->create();
            $snapshot->customer_id  = $customerId;
            $snapshot->username     = $pppoeUsername;
            $snapshot->user_comment = $userComment;
            $snapshot->period_year  = $oldPeriodYear;
            $snapshot->period_month = $oldPeriodMonth;
            $snapshot->total_upload   = $currentTx;
            $snapshot->total_download = $currentRx;
            $snapshot->total_bytes    = $currentTx + $currentRx;
            $snapshot->recorded_at  = date('Y-m-d H:i:s');
            $snapshot->save();
        }

        error_log("PAYMENT RESET: Monthly snapshot saved from session for $userComment ({$oldPeriodYear}-{$oldPeriodMonth})");
    }

    // Reset status database
    if (!empty($statusRows)) {
        ORM::for_table('tbl_user_connection_status')
            ->where('customer_id', $customerId)
            ->delete_many();
        error_log("PAYMENT RESET: Status cleared for customer ID: $customerId");
    }

    error_log("PAYMENT RESET: Completed for customer ID: $customerId");
}

// Function untuk reset interface counter setelah recharge (NEW VERSION - NO COMMENT)
function user_traffic_resetInterfaceAfterRecharge($customerId)
{
    $result = [
        'interface_reset' => false,
        'router_info' => null,
        'username' => null,
        'error' => null
    ];

    try {
        // Get customer data with pppoe_username
        $customer = ORM::for_table('tbl_customers')->find_one($customerId);
        if (!$customer || empty($customer['pppoe_username'])) {
            $result['error'] = "Customer not found or no PPPoE username configured";
            return $result;
        }

        $pppoeUsername = $customer['pppoe_username'];
        $fullname = $customer['fullname'];

        // Find user in all routers
        $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();

        foreach ($routers as $router) {
            try {
                $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

                // Check if user is active in this router
                $pppActive = $client->sendSync(new RouterOS\Request('/ppp/active/print'));
                
                foreach ($pppActive as $active) {
                    if ($active->getProperty('name') === $pppoeUsername) {
                        // User found, reset interface
                        $interfaceName = "<pppoe-$pppoeUsername>";

                        // Reset interface counter
                        $resetRequest = new RouterOS\Request('/interface/reset-counters');
                        $resetRequest->setArgument('numbers', $interfaceName);
                        $client->sendSync($resetRequest);

                        $result['interface_reset'] = true;
                        $result['router_info'] = $router['name'] . ' (' . $router['ip_address'] . ')';
                        $result['username'] = $pppoeUsername;

                        // Determine reset source from global context
                        $resetSource = isset($_POST['trigger']) && $_POST['trigger'] === 'admin_recharge' ? 'ADMIN_RECHARGE' : 'CLIENT_PAYMENT';
                        error_log("$resetSource RESET: Interface counter reset for customer ID: $customerId ($fullname) - username: $pppoeUsername in router: {$router['name']}");

                        return $result; // Found and reset, exit
                    }
                }
            } catch (Exception $e) {
                error_log("Error connecting to router {$router['name']}: " . $e->getMessage());
                continue; // Skip router yang error
            }
        }

        // User tidak ditemukan online di router manapun
        $result['error'] = "User $pppoeUsername not online in any router";
        error_log("Interface reset skipped - user not online: $pppoeUsername (Customer ID: $customerId)");
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        error_log("RECHARGE RESET ERROR: " . $e->getMessage());
    }

    return $result;
}
