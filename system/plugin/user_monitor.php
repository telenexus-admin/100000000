<?php

use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;


// ===================================================
// PERFORMANCE OPTIMIZATION CACHE SYSTEM
// ===================================================
class UserMonitorCache
{
    private static $cache = array();
    private static $cacheTimeout = 30; // 30 seconds cache

    public static function get($key)
    {
        if (
            isset(self::$cache[$key]) &&
            (time() - self::$cache[$key]['timestamp']) < self::$cacheTimeout
        ) {
            return self::$cache[$key]['data'];
        }
        return null;
    }

    public static function set($key, $data)
    {
        self::$cache[$key] = array(
            'data' => $data,
            'timestamp' => time()
        );
    }

    public static function clear($key = null)
    {
        if ($key) {
            unset(self::$cache[$key]);
        } else {
            self::$cache = array();
        }
    }
}

function user_monitor_ensure_tables()
{
    try {
        $db = ORM::get_db();

        $db->exec("CREATE TABLE IF NOT EXISTS `tbl_usage_history_monthly` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `customer_id` INT NOT NULL,
            `username` VARCHAR(128) DEFAULT NULL,
            `user_comment` VARCHAR(255) DEFAULT NULL,
            `period_year` INT NOT NULL,
            `period_month` INT NOT NULL,
            `total_upload` BIGINT NOT NULL DEFAULT 0,
            `total_download` BIGINT NOT NULL DEFAULT 0,
            `total_bytes` BIGINT NOT NULL DEFAULT 0,
            `recorded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_customer_period` (`customer_id`, `period_year`, `period_month`),
            KEY `idx_period` (`period_year`, `period_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `tbl_user_usage_history` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `customer_id` INT NOT NULL,
            `total_upload` BIGINT NOT NULL DEFAULT 0,
            `total_download` BIGINT NOT NULL DEFAULT 0,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_customer` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `tbl_user_connection_status` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `customer_id` INT NOT NULL,
            `username` VARCHAR(128) DEFAULT NULL,
            `last_tx` BIGINT NOT NULL DEFAULT 0,
            `last_rx` BIGINT NOT NULL DEFAULT 0,
            `is_online` TINYINT(1) NOT NULL DEFAULT 0,
            `last_seen` DATETIME DEFAULT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_customer` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `tbl_router_status` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `router_id` INT NOT NULL,
            `cpu_load` VARCHAR(32) DEFAULT NULL,
            `memory_percent` VARCHAR(32) DEFAULT NULL,
            `memory_used` VARCHAR(64) DEFAULT NULL,
            `memory_total` VARCHAR(64) DEFAULT NULL,
            `uptime` VARCHAR(64) DEFAULT NULL,
            `board_name` VARCHAR(128) DEFAULT NULL,
            `version` VARCHAR(64) DEFAULT NULL,
            `bandwidth_percent` VARCHAR(32) DEFAULT NULL,
            `current_upload` VARCHAR(64) DEFAULT NULL,
            `current_download` VARCHAR(64) DEFAULT NULL,
            `total_online` INT NOT NULL DEFAULT 0,
            `total_registered` INT NOT NULL DEFAULT 0,
            `raw_json` MEDIUMTEXT,
            `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_router` (`router_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log('USER_MONITOR ensure_tables error: ' . $e->getMessage());
    }
}

/**
 * Live usage totals per customer (history + session counters from MikroTik sync).
 *
 * @return array<int, array{customer_id:int,username:string,user_comment:string,total_upload:int,total_download:int,total_bytes:int}>
 */
function user_monitor_fetch_live_usage_summary_rows()
{
    user_monitor_ensure_tables();
    try {
        $db = ORM::get_db();
        $sql = "SELECT c.id AS customer_id,
                TRIM(COALESCE(NULLIF(c.username, ''), c.pppoe_username, '')) AS username,
                TRIM(COALESCE(c.fullname, '')) AS user_comment,
                (COALESCE(h.total_upload, 0) + COALESCE(sess.up, 0) + COALESCE(st.last_tx, 0)) AS total_upload,
                (COALESCE(h.total_download, 0) + COALESCE(sess.down, 0) + COALESCE(st.last_rx, 0)) AS total_download
            FROM tbl_customers c
            LEFT JOIN tbl_user_usage_history h ON h.customer_id = c.id
            LEFT JOIN tbl_user_connection_status st ON st.customer_id = c.id
            LEFT JOIN (
                SELECT username,
                    SUM(COALESCE(session_tx, last_tx, 0)) AS up,
                    SUM(COALESCE(session_rx, last_rx, 0)) AS down
                FROM tbl_usage_sessions
                GROUP BY username
            ) sess ON (
                sess.username = c.username
                OR (c.pppoe_username IS NOT NULL AND c.pppoe_username != '' AND sess.username = c.pppoe_username)
            )
            WHERE (COALESCE(h.total_upload, 0) + COALESCE(sess.up, 0) + COALESCE(st.last_tx, 0)
                + COALESCE(h.total_download, 0) + COALESCE(sess.down, 0) + COALESCE(st.last_rx, 0)) > 0";
        $stmt = $db->query($sql);
        if (!$stmt) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $up = intval($row['total_upload']);
            $down = intval($row['total_download']);
            $uname = (string) $row['username'];
            if ($uname === '') {
                continue;
            }
            $out[] = [
                'customer_id' => intval($row['customer_id']),
                'username' => $uname,
                'user_comment' => (string) $row['user_comment'],
                'total_upload' => $up,
                'total_download' => $down,
                'total_bytes' => $up + $down,
            ];
        }
        return $out;
    } catch (Exception $e) {
        error_log('USER_MONITOR fetch_live_usage_summary_rows: ' . $e->getMessage());
        return [];
    }
}

/**
 * Persist current-month usage snapshots so Usage History is never empty.
 * @return int number of customers upserted
 */
function user_monitor_sync_usage_snapshots()
{
    user_monitor_ensure_tables();
    $rows = user_monitor_fetch_live_usage_summary_rows();
    if (!$rows) {
        return 0;
    }
    $year = intval(date('Y'));
    $month = intval(date('n'));
    $now = date('Y-m-d H:i:s');
    $n = 0;
    foreach ($rows as $row) {
        $cid = intval($row['customer_id']);
        if ($cid <= 0) {
            continue;
        }
        $up = intval($row['total_upload']);
        $down = intval($row['total_download']);
        if (($up + $down) <= 0) {
            continue;
        }

        $hist = ORM::for_table('tbl_user_usage_history')->where('customer_id', $cid)->find_one();
        if (!$hist) {
            $hist = ORM::for_table('tbl_user_usage_history')->create();
            $hist->customer_id = $cid;
        }
        $hist->total_upload = max(intval($hist['total_upload'] ?? 0), $up);
        $hist->total_download = max(intval($hist['total_download'] ?? 0), $down);
        $hist->updated_at = $now;
        $hist->save();

        $snap = ORM::for_table('tbl_usage_history_monthly')
            ->where('customer_id', $cid)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->find_one();
        if (!$snap) {
            $snap = ORM::for_table('tbl_usage_history_monthly')->create();
            $snap->customer_id = $cid;
            $snap->period_year = $year;
            $snap->period_month = $month;
        }
        $snap->username = $row['username'];
        $snap->user_comment = $row['user_comment'];
        $snap->total_upload = max(intval($snap['total_upload'] ?? 0), $up);
        $snap->total_download = max(intval($snap['total_download'] ?? 0), $down);
        $snap->total_bytes = $snap->total_upload + $snap->total_download;
        $snap->recorded_at = $now;
        $snap->save();
        $n++;
    }
    return $n;
}

function user_monitor_get_router_id_from_routes()
{
    global $routes;
    $router = isset($routes['2']) ? intval($routes['2']) : 0;
    if ($router > 0) {
        return $router;
    }

    $routerFromGet = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;
    if ($routerFromGet > 0) {
        return $routerFromGet;
    }

    $defaultRouter = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one();
    return $defaultRouter ? intval($defaultRouter['id']) : 0;
}

function user_monitor_is_pppoe_entry($entry)
{
    if (!$entry) {
        return false;
    }

    $service = '';
    try {
        $service = strtolower(trim((string) $entry->getProperty('service')));
    } catch (Exception $e) {
        $service = '';
    }

    if ($service !== '') {
        return $service === 'pppoe';
    }

    return true;
}

function user_monitor_get_active_presence($routerId, $username, $service)
{
    $service = strtolower(trim((string) $service));
    if (!in_array($service, ['hotspot', 'pppoe'], true)) {
        $service = 'hotspot';
    }

    $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($routerId);
    if (!$mikrotik) {
        throw new Exception('Router not found');
    }

    $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

    if ($service === 'hotspot') {
        $activeSessions = $client->sendSync(new RouterOS\Request('/ip/hotspot/active/print'));
        foreach ($activeSessions as $session) {
            if (trim((string) $session->getProperty('user')) === $username) {
                return true;
            }
        }
        return false;
    }

    $pppActive = $client->sendSync(new RouterOS\Request('/ppp/active/print'));
    foreach ($pppActive as $session) {
        if (!user_monitor_is_pppoe_entry($session)) {
            continue;
        }
        if (trim((string) $session->getProperty('name')) === $username) {
            return true;
        }
    }

    return false;
}

// Register plugin menu
register_menu("Data Usage", true, "user_monitor_ui", 'AFTER_PLANS', 'ion ion-ios-toggle');

function user_monitor_ui()
{
    global $ui, $routes;
    _admin();

    // Self-healing schema: ensure required usage tables exist before any query.
    user_monitor_ensure_tables();
    try {
        user_monitor_sync_usage_snapshots();
    } catch (Throwable $e) {
        error_log('USER_MONITOR ui sync: ' . $e->getMessage());
    }

    // AUTO-REDIRECT DYNAMIC LOGIC (COPY DARI MIKROTIK MONITOR)
    $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
    $router = isset($routes['2']) ? $routes['2'] : null;

    // CHECK IF NO ROUTERS REGISTERED
    if (count($routers) == 0) {
        // No routers found, show setup message
        $ui->assign('_title', 'Data Usage - Setup Required');
        $ui->assign('_system_menu', 'Data Usage');
        $admin = Admin::_info();
        $ui->assign('_admin', $admin);
        $ui->assign('no_router', true);
        $ui->assign('routers', []);
        $ui->assign('router', null);

        // Display template with no router message
        $ui->display('user_monitor.tpl');
        return;
    }

    // JIKA TIDAK ADA ROUTER ID DI URL, AUTO-REDIRECT
    if (empty($router)) {
        $defaultRouter = $routers[0]['id']; // Ambil router pertama
        $currentUri = $_SERVER['REQUEST_URI'];

        // Pastikan tidak ada trailing slash ganda
        $currentUri = rtrim($currentUri, '/');
        $redirectUrl = $currentUri . '/' . $defaultRouter;

        header("Location: $redirectUrl");
        exit();
    }

    // VERIFY ROUTER EXISTS DAN ENABLED
    $selectedRouter = null;
    foreach ($routers as $r) {
        if ($r['id'] == $router) {
            $selectedRouter = $r;
            break;
        }
    }

    // JIKA ROUTER ID TIDAK VALID, REDIRECT KE ROUTER PERTAMA  
    if (!$selectedRouter) {
        $defaultRouter = $routers[0]['id'];

        // Parse current URL dan replace router ID
        $currentUri = $_SERVER['REQUEST_URI'];
        $pathParts = explode('/', trim($currentUri, '/'));

        // Remove invalid router ID (last part)
        array_pop($pathParts);

        // Add valid router ID
        $redirectUrl = '/' . implode('/', $pathParts) . '/' . $defaultRouter;

        header("Location: $redirectUrl");
        exit();
    }
    // HANDLER RESET - PERBAIKAN (EXISTING CODE)
    if (isset($_POST['reset_action']) && $_POST['reset_action'] === 'reset_interface') {
        header('Content-Type: application/json');

        try {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';

            if (empty($username)) {
                throw new Exception('Username required');
            }

            // GUNAKAN ROUTER ID DARI URL (BUKAN FALLBACK)
            $routerId = $router; // Sudah validated di atas

            if (!$routerId) {
                throw new Exception('No router found');
            }

            $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($routerId);

            if (!$mikrotik) {
                throw new Exception('Router not found');
            }

            $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);
            $interfaceName = "<pppoe-$username>";

            // PERBAIKAN: RouterOS Request yang benar
            $resetRequest = new RouterOS\Request('/interface/reset-counters');
            $resetRequest->setArgument('numbers', $interfaceName);
            $client->sendSync($resetRequest);

            // Reset usage history untuk user ini
            // Cari customer_id langsung dari tbl_customers berdasarkan pppoe_username
            $customerObj = ORM::for_table('tbl_customers')
                ->where('pppoe_username', $username)
                ->find_one();

            if ($customerObj) {
                $customerId = intval($customerObj['id']);
                user_monitor_resetUsageHistory($customerId);
            } else {
                error_log("ADMIN RESET: Customer not found for pppoe_username: $username");
            }
            // CLEAR CACHE setelah reset
            UserMonitorCache::clear("pppoe_users_$router");
            UserMonitorCache::clear("live_traffic_$router");

            echo json_encode(array(
                'success' => true,
                'message' => 'Reset successful (including history)',
                'username' => $username
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'error' => $e->getMessage()
            ));
        }

        exit();
    }

    // HANDLER CHECK UPDATE
    if (isset($_POST['action']) && $_POST['action'] === 'check_update') {
        mikromon_check_update();
        exit();
    }

    // HANDLER RUN UPDATE
    if (isset($_POST['action']) && $_POST['action'] === 'run_update') {
        mikromon_run_update();
        exit();
    }

    // HANDLER UNINSTALL
    if (isset($_POST['action']) && $_POST['action'] === 'uninstall') {
        mikromon_run_uninstall();
        exit();
    }

    // HANDLER REMOVE - TAMBAHAN BARU
    if (isset($_POST['remove_action']) && $_POST['remove_action'] === 'remove_interface') {
        user_monitor_remove_interface();
        exit();
    }

    // HANDLER SYSTEM MONITORING - TAMBAHAN BARU
    if (isset($_GET['action']) && $_GET['action'] === 'system_resources') {
        user_monitor_get_system_resources();
        exit();
    }
    // HANDLER USER COUNTS
    if (isset($_GET['action']) && $_GET['action'] === 'get_user_counts') {
        header('Content-Type: application/json');

        try {
            global $routes;
            $routerId = $routes['2'];

            $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($routerId);
            if ($mikrotik) {
                try {
                    $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);
                    
                    // Hotspot Active & Registered
                    $hotspotActive = $client->sendSync(new \PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
                    $hotspotUsers  = $client->sendSync(new \PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print'));
                    
                    // PPPoE Active & Registered
                    $pppActive  = $client->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp/active/print'));
                    $pppSecrets = $client->sendSync(new \PEAR2\Net\RouterOS\Request('/ppp/secret/print'));

                    $activePppoeCount = 0;
                    foreach ($pppActive as $pppSession) {
                        if (user_monitor_is_pppoe_entry($pppSession)) {
                            $activePppoeCount++;
                        }
                    }

                    $registeredPppoeCount = 0;
                    foreach ($pppSecrets as $pppSecret) {
                        if (user_monitor_is_pppoe_entry($pppSecret)) {
                            $registeredPppoeCount++;
                        }
                    }

                    echo json_encode([
                        'success'            => true,
                        'total_hotspot'      => count($hotspotActive),
                        'reg_hotspot'        => count($hotspotUsers),
                        'total_pppoe'        => $activePppoeCount,
                        'reg_pppoe'          => $registeredPppoeCount,
                        'is_live'            => true
                    ]);
                    exit();
                } catch (Exception $apiErr) {
                    // Fallback to DB
                }
            }

            echo json_encode([
                'success' => false,
                'error' => 'Live router counts unavailable'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }

    if (isset($_GET['action']) && $_GET['action'] === 'get_pppoe_users') {
        user_monitor_get_pppoe_users();
        exit();
    }

        if (isset($_GET['action']) && $_GET['action'] === 'get_user_presence') {
            header('Content-Type: application/json');
            try {
                $routerId = user_monitor_get_router_id_from_routes();
                $username = isset($_GET['username']) ? trim($_GET['username']) : '';
                $service = isset($_GET['service']) ? trim($_GET['service']) : 'hotspot';

                if ($routerId <= 0) {
                    throw new Exception('No router selected');
                }
                if ($username === '') {
                    throw new Exception('Username required');
                }

                echo json_encode([
                    'success' => true,
                    'online' => user_monitor_get_active_presence($routerId, $username, $service),
                    'username' => $username,
                    'service' => strtolower($service),
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
            }
            exit();
        }


    // TAMBAH INI DI DALAM FUNCTION user_monitor_ui() SETELAH HANDLER YANG ADA

    // HANDLER INTERFACE MONITORING - TAMBAHAN BARU
    if (isset($_GET['action']) && $_GET['action'] === 'get_interfaces') {
        user_monitor_get_interfaces();
        exit();
    }

    // ✅ HANDLER REALTIME TRAFFIC PER USER (QUEUE SIMPLE)
    if (isset($_GET['action']) && $_GET['action'] === 'get_user_realtime_traffic') {
        user_monitor_get_user_realtime_traffic();
        exit();
    }

    if (isset($_GET['action']) && $_GET['action'] === 'get_interface_traffic') {
        user_monitor_get_interface_traffic();
        exit();
    }

    if (isset($_GET['action']) && $_GET['action'] === 'api_diagnostics') {
        user_monitor_api_diagnostics();
        exit();
    }

    // HANDLER GET USAGE HISTORY
    // HANDLER GET USAGE HISTORY
    if (isset($_GET['action']) && $_GET['action'] === 'get_usage_history') {
        header('Content-Type: application/json');
        try {
            // Keep monthly/history tables filled from live MikroTik session sync
            try {
                user_monitor_sync_usage_snapshots();
            } catch (Throwable $e) {
                error_log('USER_MONITOR sync snapshots: ' . $e->getMessage());
            }
            $filterType  = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : 'month';
            $year        = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));
            $month       = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
            $dateFrom    = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
            $dateTo      = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
            $search      = isset($_GET['search'])    ? trim($_GET['search'])    : '';
            $page        = isset($_GET['page'])      ? max(1, intval($_GET['page'])) : 1;
            $perPage     = isset($_GET['per_page'])  ? intval($_GET['per_page'])     : 10;
            if (!in_array($perPage, [10, 25, 50, 100])) $perPage = 10;
            $offset      = ($page - 1) * $perPage;

            if ($filterType === 'month') {
                $isCurrentCalendarMonth = ($year === intval(date('Y')) && $month === intval(date('n')));

                if ($isCurrentCalendarMonth) {
                    // Monthly snapshots are only written on reset/recharge; merge live DB totals
                    // so "this month" shows data users already have in tbl_user_usage_history + session.
                    $byCustomer = [];

                    $monthlyAll = ORM::for_table('tbl_usage_history_monthly')
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->find_many();

                    foreach ($monthlyAll as $rec) {
                        $cid = intval($rec['customer_id']);
                        $byCustomer[$cid] = [
                            'customer_id'    => $cid,
                            'username'       => trim((string) ($rec['username'] ?? '')),
                            'user_comment'   => trim((string) ($rec['user_comment'] ?? '')),
                            'total_upload'   => intval($rec['total_upload']),
                            'total_download' => intval($rec['total_download']),
                            'total_bytes'    => intval($rec['total_bytes']),
                        ];
                    }

                    foreach (user_monitor_fetch_live_usage_summary_rows() as $live) {
                        $cid = intval($live['customer_id']);
                        if (intval($live['total_bytes']) > 0) {
                            $byCustomer[$cid] = $live;
                        }
                    }

                    $merged = array_values($byCustomer);

                    if ($search !== '') {
                        $needle = strtolower($search);
                        $merged = array_values(array_filter($merged, function ($r) use ($needle) {
                            $hay = strtolower(
                                ($r['username'] ?? '') . ' ' . ($r['user_comment'] ?? '')
                            );
                            return strpos($hay, $needle) !== false;
                        }));
                    }

                    usort($merged, function ($a, $b) {
                        return intval($b['total_bytes']) - intval($a['total_bytes']);
                    });

                    $totalCount = count($merged);
                    $paged      = array_slice($merged, $offset, $perPage);

                    $result = [];
                    foreach ($paged as $row) {
                        $up   = intval($row['total_upload']);
                        $down = intval($row['total_download']);
                        $tot  = intval($row['total_bytes']);
                        $result[] = [
                            'customer_id'    => intval($row['customer_id']),
                            'username'       => ($row['username'] !== '') ? $row['username'] : '-',
                            'user_comment'   => ($row['user_comment'] !== '') ? $row['user_comment'] : '-',
                            'total_upload'   => user_monitor_formatBytes($up),
                            'total_download' => user_monitor_formatBytes($down),
                            'total_usage'    => user_monitor_formatBytes($tot),
                            'total_bytes'    => $tot,
                        ];
                    }
                } else {
                    $baseQuery = ORM::for_table('tbl_usage_history_monthly')
                        ->where('period_year', $year)
                        ->where('period_month', $month);

                    if (!empty($search)) {
                        $baseQuery = $baseQuery->where_raw(
                            '(username LIKE ? OR user_comment LIKE ?)',
                            ['%' . $search . '%', '%' . $search . '%']
                        );
                    }

                    $totalCount = $baseQuery->count();
                    $records    = $baseQuery->order_by_desc('total_bytes')
                        ->limit($perPage)->offset($offset)->find_many();

                    $result = [];
                    foreach ($records as $rec) {
                        $result[] = [
                            'customer_id'    => $rec['customer_id'],
                            'username'       => $rec['username'],
                            'user_comment'   => $rec['user_comment'],
                            'total_upload'   => user_monitor_formatBytes(intval($rec['total_upload'])),
                            'total_download' => user_monitor_formatBytes(intval($rec['total_download'])),
                            'total_usage'    => user_monitor_formatBytes(intval($rec['total_bytes'])),
                            'total_bytes'    => intval($rec['total_bytes']),
                        ];
                    }
                }

            } else {
                if (empty($dateFrom) || empty($dateTo)) {
                    throw new Exception('Date range required');
                }
                $fromYear  = intval(date('Y', strtotime($dateFrom)));
                $fromMonth = intval(date('m', strtotime($dateFrom)));
                $toYear    = intval(date('Y', strtotime($dateTo)));
                $toMonth   = intval(date('m', strtotime($dateTo)));

                $baseQuery = ORM::for_table('tbl_usage_history_monthly')
                    ->where_raw(
                        '(period_year > ? OR (period_year = ? AND period_month >= ?))
                         AND (period_year < ? OR (period_year = ? AND period_month <= ?))',
                        [$fromYear, $fromYear, $fromMonth, $toYear, $toYear, $toMonth]
                    );

                if (!empty($search)) {
                    $baseQuery = $baseQuery->where_raw(
                        '(username LIKE ? OR user_comment LIKE ?)',
                        ['%' . $search . '%', '%' . $search . '%']
                    );
                }

                $allRecords = $baseQuery->find_many();

                // Merge per customer
                $merged = [];
                foreach ($allRecords as $rec) {
                    $cid = $rec['customer_id'];
                    if (!isset($merged[$cid])) {
                        $merged[$cid] = [
                            'customer_id'    => $cid,
                            'username'       => $rec['username'],
                            'user_comment'   => $rec['user_comment'],
                            'total_upload'   => 0,
                            'total_download' => 0,
                            'total_bytes'    => 0,
                        ];
                    }
                    $merged[$cid]['total_upload']   += intval($rec['total_upload']);
                    $merged[$cid]['total_download']  += intval($rec['total_download']);
                    $merged[$cid]['total_bytes']     += intval($rec['total_bytes']);
                }

                usort($merged, function($a, $b) { return $b['total_bytes'] - $a['total_bytes']; });

                $totalCount = count($merged);
                $paged      = array_slice($merged, $offset, $perPage);

                $result = [];
                foreach ($paged as $row) {
                    $result[] = [
                        'customer_id'    => $row['customer_id'],
                        'username'       => $row['username'],
                        'user_comment'   => $row['user_comment'],
                        'total_upload'   => user_monitor_formatBytes($row['total_upload']),
                        'total_download' => user_monitor_formatBytes($row['total_download']),
                        'total_usage'    => user_monitor_formatBytes($row['total_bytes']),
                        'total_bytes'    => $row['total_bytes'],
                    ];
                }
            }

            echo json_encode([
                'success'     => true,
                'data'        => $result,
                'total_count' => $totalCount,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }

    $ui->assign('_system_menu', 'Data Usage');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);

    $ui->assign('xheader', '
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>');

    $ui->assign('routers', $routers);
    $ui->assign('router', $router);
    $ui->display('user_monitor.tpl');
}

// Function untuk get system resources MikroTik - DIPINDAH KE LUAR
function user_monitor_get_system_resources()
{
    try {
        global $routes;
        $routerId = $routes['2'];

        // CACHE DARI DATABASE
        $routerStatus = ORM::for_table('tbl_router_status')
            ->where('router_id', $routerId)
            ->find_one();

        $isDataStale = true;
        if ($routerStatus) {
            $ts = $routerStatus['last_updated'] ?? $routerStatus['updated_at'] ?? null;
            if ($ts && (time() - strtotime($ts)) < 300) {
                $isDataStale = false;
            }
        }

        // IF STALE OR MISSING: Try to fetch LIVE from MikroTik
        if ($isDataStale) {
            $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($routerId);
            if ($mikrotik) {
                try {
                    $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);
                    $sysResources = $client->sendSync(new \PEAR2\Net\RouterOS\Request('/system/resource/print'));
                    
                    $cpuLoad = 0; $freeMem = 0; $totalMem = 0; $uptime = '0s'; $board = 'Unknown'; $ver = 'Unknown';
                    foreach ($sysResources as $res) {
                        $cpuLoad = floatval($res->getProperty('cpu-load') ?: 0);
                        $freeMem = intval($res->getProperty('free-memory') ?: 0);
                        $totalMem = intval($res->getProperty('total-memory') ?: 0);
                        $uptime = $res->getProperty('uptime') ?: '0s';
                        $board = $res->getProperty('board-name') ?: 'Unknown';
                        $ver = $res->getProperty('version') ?: 'Unknown';
                        break;
                    }

                    $memoryUsed = $totalMem - $freeMem;
                    $memoryPercent = $totalMem > 0 ? round(($memoryUsed / $totalMem) * 100, 1) : 0;

                    // Cache for next poll
                    try {
                        if (!$routerStatus) {
                            $routerStatus = ORM::for_table('tbl_router_status')->create();
                            $routerStatus->router_id = intval($routerId);
                        }
                        $routerStatus->cpu_load = (string) $cpuLoad;
                        $routerStatus->memory_percent = (string) $memoryPercent;
                        $routerStatus->memory_used = user_monitor_formatBytes($memoryUsed);
                        $routerStatus->memory_total = user_monitor_formatBytes($totalMem);
                        $routerStatus->uptime = $uptime;
                        $routerStatus->board_name = $board;
                        $routerStatus->version = $ver;
                        $routerStatus->current_upload = 'Live';
                        $routerStatus->current_download = 'Live';
                        $routerStatus->last_updated = date('Y-m-d H:i:s');
                        $routerStatus->save();
                    } catch (Throwable $cacheErr) {
                    }

                    $result = array(
                        'cpu_load'          => $cpuLoad,
                        'memory_percent'    => $memoryPercent,
                        'memory_used'       => user_monitor_formatBytes($memoryUsed),
                        'memory_total'      => user_monitor_formatBytes($totalMem),
                        'uptime'            => $uptime,
                        'board_name'        => $board,
                        'version'           => $ver,
                        'health'            => array(),
                        'bandwidth_percent' => 0,
                        'current_upload'    => 'Live',
                        'current_download'  => 'Live',
                        'total_online'      => 0,
                        'total_registered'  => 0,
                        'last_updated'      => 'Live Now',
                        'timestamp'         => time(),
                        'is_live'           => true
                    );

                    header('Content-Type: application/json');
                    echo json_encode($result);
                    return;
                } catch (Exception $apiErr) {
                    // Fallback to database
                }
            }
        }

        if (!$routerStatus) {
            header('Content-Type: application/json');
            echo json_encode([
                'cpu_load' => 0,
                'memory_percent' => 0,
                'memory_used' => '—',
                'memory_total' => '—',
                'uptime' => '—',
                'board_name' => 'PMNINTERNET',
                'version' => '—',
                'health' => [],
                'bandwidth_percent' => 0,
                'current_upload' => '—',
                'current_download' => '—',
                'total_online' => 0,
                'total_registered' => 0,
                'last_updated' => date('Y-m-d H:i:s'),
                'timestamp' => time(),
                'is_live' => false,
                'message' => 'Waiting for router sync',
            ]);
            return;
        }

        $result = array(
            'cpu_load'          => floatval($routerStatus['cpu_load']),
            'memory_percent'    => floatval($routerStatus['memory_percent']),
            'memory_used'       => $routerStatus['memory_used'],
            'memory_total'      => $routerStatus['memory_total'],
            'uptime'            => $routerStatus['uptime'],
            'board_name'        => $routerStatus['board_name'],
            'version'           => $routerStatus['version'],
            'health'            => array(),
            'bandwidth_percent' => floatval($routerStatus['bandwidth_percent']),
            'current_upload'    => $routerStatus['current_upload'],
            'current_download'  => $routerStatus['current_download'],
            'total_online'      => intval($routerStatus['total_online']),
            'total_registered'  => intval($routerStatus['total_registered']),
            'last_updated'      => $routerStatus['last_updated'],
            'timestamp'         => time(),
            'is_live'           => false
        );

        header('Content-Type: application/json');
        echo json_encode($result);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(array('error' => $e->getMessage()));
        error_log("System monitoring error: " . $e->getMessage());
    }
}

function user_monitor_get_pppoe_users()
{
    try {
        global $routes;
        $router = isset($routes['2']) ? $routes['2'] : null;
        $service = isset($_GET['service']) ? strtolower(trim($_GET['service'])) : 'hotspot';
        if (!in_array($service, ['hotspot', 'pppoe', 'all'], true)) {
            $service = 'hotspot';
        }

        $userList = user_monitor_fetch_active_users($router, $service);

        // Default sort by usage desc
        usort($userList, function ($a, $b) {
            return user_monitor_usageToBytes($b['total_usage']) - user_monitor_usageToBytes($a['total_usage']);
        });

        header('Content-Type: application/json');
        echo json_encode($userList);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(array('error' => $e->getMessage()));
    }
}


// ===================================================
// REALTIME TRAFFIC PER USER (QUEUE SIMPLE)
// ===================================================
function user_monitor_get_user_realtime_traffic()
{
    header('Content-Type: application/json');

    try {
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';
        if (empty($username)) {
            throw new Exception('Username required');
        }

        global $routes;
        $router = isset($routes['2']) ? $routes['2'] : null;
        $service = isset($_GET['service']) ? strtolower(trim($_GET['service'])) : 'hotspot';
        if (!$router) {
            throw new Exception('No router selected');
        }

        $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($router);
        if (!$mikrotik) {
            throw new Exception('Router not found');
        }

        $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if ($service !== 'pppoe') {
            $activeSessions = $client->sendSync(new RouterOS\Request('/ip/hotspot/active/print'));
            $activeSession = null;
            foreach ($activeSessions as $session) {
                if (trim((string) $session->getProperty('user')) === $username) {
                    $activeSession = $session;
                    break;
                }
            }

            if ($activeSession) {
                $sessionKey = 'user_monitor_hotspot_rate_' . md5($router . '|hotspot|' . $username);
                $currentUploadBytes = intval($activeSession->getProperty('bytes-in') ?: 0);
                $currentDownloadBytes = intval($activeSession->getProperty('bytes-out') ?: 0);
                $currentTime = microtime(true);
                $uploadBps = 0;
                $downloadBps = 0;

                if (!empty($_SESSION[$sessionKey])) {
                    $previous = $_SESSION[$sessionKey];
                    $elapsed = max(0.1, $currentTime - floatval($previous['time']));
                    $uploadDelta = max(0, $currentUploadBytes - intval($previous['upload_bytes']));
                    $downloadDelta = max(0, $currentDownloadBytes - intval($previous['download_bytes']));
                    $uploadBps = intval(($uploadDelta * 8) / $elapsed);
                    $downloadBps = intval(($downloadDelta * 8) / $elapsed);
                }

                $_SESSION[$sessionKey] = [
                    'time' => $currentTime,
                    'upload_bytes' => $currentUploadBytes,
                    'download_bytes' => $currentDownloadBytes,
                ];

                echo json_encode([
                    'success' => true,
                    'queue_found' => true,
                    'traffic_source' => 'hotspot-active',
                    'upload_bps' => $uploadBps,
                    'download_bps' => $downloadBps,
                    'upload' => user_monitor_formatMonitorBps($uploadBps),
                    'download' => user_monitor_formatMonitorBps($downloadBps),
                    'max_upload' => '',
                    'max_download' => '',
                    'max_limit' => '',
                    'username' => $username,
                    'timestamp' => date('H:i:s'),
                ]);
                return;
            }
        }

        if ($service !== 'hotspot') {
            $pppActive = $client->sendSync(new RouterOS\Request('/ppp/active/print'));
            $pppFound = false;
            foreach ($pppActive as $session) {
                if (trim((string) $session->getProperty('name')) === $username) {
                    $pppFound = true;
                    break;
                }
            }

            if ($pppFound) {
                $interfaceName = '<pppoe-' . $username . '>';
                $interfaces = $client->sendSync(new RouterOS\Request('/interface/print'));
                $sessionKey = 'user_monitor_pppoe_rate_' . md5($router . '|pppoe|' . $username);
                $currentUploadBytes = 0;
                $currentDownloadBytes = 0;

                foreach ($interfaces as $interface) {
                    if ((string) $interface->getProperty('name') === $interfaceName) {
                        $currentUploadBytes = intval($interface->getProperty('tx-byte') ?: 0);
                        $currentDownloadBytes = intval($interface->getProperty('rx-byte') ?: 0);
                        break;
                    }
                }

                $currentTime = microtime(true);
                $uploadBps = 0;
                $downloadBps = 0;

                if (!empty($_SESSION[$sessionKey])) {
                    $previous = $_SESSION[$sessionKey];
                    $elapsed = max(0.1, $currentTime - floatval($previous['time']));
                    $uploadDelta = max(0, $currentUploadBytes - intval($previous['upload_bytes']));
                    $downloadDelta = max(0, $currentDownloadBytes - intval($previous['download_bytes']));
                    $uploadBps = intval(($uploadDelta * 8) / $elapsed);
                    $downloadBps = intval(($downloadDelta * 8) / $elapsed);
                }

                $_SESSION[$sessionKey] = [
                    'time' => $currentTime,
                    'upload_bytes' => $currentUploadBytes,
                    'download_bytes' => $currentDownloadBytes,
                ];

                echo json_encode([
                    'success' => true,
                    'queue_found' => true,
                    'traffic_source' => 'pppoe-interface',
                    'upload_bps' => $uploadBps,
                    'download_bps' => $downloadBps,
                    'upload' => user_monitor_formatMonitorBps($uploadBps),
                    'download' => user_monitor_formatMonitorBps($downloadBps),
                    'max_upload' => '',
                    'max_download' => '',
                    'max_limit' => '',
                    'username' => $username,
                    'timestamp' => date('H:i:s'),
                ]);
                return;
            }
        }

        // Ambil queue simple untuk user ini saja
        $targetQueueName = "<pppoe-$username>";
        $queues = $client->sendSync(new RouterOS\Request('/queue/simple/print'));

        $uploadBps   = 0;
        $downloadBps = 0;
        $maxLimit    = '';
        $queueFound  = false;

        foreach ($queues as $queue) {
            $queueName = $queue->getProperty('name');
            if ($queueName === $targetQueueName) {
                $rate     = $queue->getProperty('rate');
                $maxLimit = $queue->getProperty('max-limit') ?: '';

                if ($rate && $rate !== '0/0' && strpos($rate, '/') !== false) {
                    $parts       = explode('/', $rate);
                    $uploadBps   = intval($parts[0]);
                    $downloadBps = intval($parts[1]);
                }
                $queueFound = true;
                break;
            }
        }

        if (!$queueFound) {
            echo json_encode([
                'success'      => true,
                'queue_found'  => false,
                'upload_bps'   => 0,
                'download_bps' => 0,
                'upload'       => '0 bps',
                'download'     => '0 bps',
                'max_limit'    => '',
                'username'     => $username,
                'message'      => 'User is offline or no live source is available'
            ]);
            return;
        }

        // Parse max-limit untuk ditampilkan (format: upload/download)
        $maxUpload   = '';
        $maxDownload = '';
        if (!empty($maxLimit) && strpos($maxLimit, '/') !== false) {
            $limitParts  = explode('/', $maxLimit);
            $maxUpload   = user_monitor_formatMonitorBps(intval($limitParts[0]));
            $maxDownload = user_monitor_formatMonitorBps(intval($limitParts[1]));
        }

        echo json_encode([
            'success'      => true,
            'queue_found'  => true,
            'traffic_source' => 'queue-simple',
            'upload_bps'   => $uploadBps,
            'download_bps' => $downloadBps,
            'upload'       => user_monitor_formatMonitorBps($uploadBps),
            'download'     => user_monitor_formatMonitorBps($downloadBps),
            'max_upload'   => $maxUpload,
            'max_download' => $maxDownload,
            'max_limit'    => $maxLimit,
            'username'     => $username,
            'timestamp'    => date('H:i:s'),
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage()
        ]);
    }
}

// Function untuk get total registered PPPoE users dari secrets
function user_monitor_get_total_registered_users()
{
    try {
        global $routes;
        $router = $routes['2'];

        $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($router);
        if (!$mikrotik) {
            return 0;
        }

        $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

        // Get ALL secrets (registered users)
        $pppSecrets = $client->sendSync(new RouterOS\Request('/ppp/secret/print'));

        $totalRegistered = 0;
        foreach ($pppSecrets as $secret) {
            $username = $secret->getProperty('name');
            if (!empty($username) && $username !== 'null' && $username !== 'NULL') {
                $totalRegistered++;
            }
        }

        return $totalRegistered;
    } catch (Exception $e) {
        error_log("Error getting total registered users: " . $e->getMessage());
        return 0;
    }
}

function user_monitor_formatMonitorBps($bps)
{
    if ($bps <= 0) return '0 bps';
    $units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    $pow = floor(log(max($bps, 1)) / log(1000));
    $pow = min($pow, count($units) - 1);
    return round($bps / pow(1000, $pow), 2) . ' ' . $units[$pow];
}

function user_monitor_normalize_username($username)
{
    return strtolower(trim((string) $username));
}

function user_monitor_resolve_customer_identity($username)
{
    $normalized = user_monitor_normalize_username($username);
    if ($normalized === '') {
        return null;
    }

    $customer = ORM::for_table('tbl_customers')
        ->where_raw('LOWER(TRIM(pppoe_username)) = ?', [$normalized])
        ->find_one();

    if ($customer) {
        return [
            'customer_id' => intval($customer['id']),
            'fullname' => $customer['fullname'] ?: $username,
        ];
    }

    $recharge = ORM::for_table('tbl_user_recharges')
        ->where_raw('LOWER(TRIM(username)) = ?', [$normalized])
        ->order_by_desc('id')
        ->find_one();

    if ($recharge && !empty($recharge['customer_id'])) {
        $rechargeCustomer = ORM::for_table('tbl_customers')->find_one(intval($recharge['customer_id']));
        return [
            'customer_id' => intval($recharge['customer_id']),
            'fullname' => $rechargeCustomer ? ($rechargeCustomer['fullname'] ?: $username) : $username,
        ];
    }

    return null;
}

function user_monitor_fetch_active_users($router, $service = 'hotspot')
{
    $cacheKey = 'active_users_' . $router . '_' . $service;
    $cachedUsers = UserMonitorCache::get($cacheKey);
    if ($cachedUsers !== null) {
        return $cachedUsers;
    }

    $historyByCustomer = array();
    $historyResults = ORM::for_table('tbl_user_usage_history')->find_many();
    foreach ($historyResults as $history) {
        $historyByCustomer[intval($history['customer_id'])] = [
            'up' => intval($history['total_upload']),
            'down' => intval($history['total_download']),
        ];
    }

    $userList = array();
    $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($router);
    if ($mikrotik) {
        try {
            $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);
            if ($service === 'hotspot' || $service === 'all') {
                $activeSessions = $client->sendSync(new RouterOS\Request('/ip/hotspot/active/print'));
                $registeredUsers = $client->sendSync(new RouterOS\Request('/ip/hotspot/user/print'));

                $commentMap = array();
                foreach ($registeredUsers as $registeredUser) {
                    $registeredName = user_monitor_normalize_username($registeredUser->getProperty('name'));
                    if ($registeredName !== '') {
                        $commentMap[$registeredName] = trim((string) ($registeredUser->getProperty('comment') ?: ''));
                    }
                }

                foreach ($activeSessions as $session) {
                    $username = trim((string) $session->getProperty('user'));
                    if ($username === '' || strtolower($username) === 'null') {
                        continue;
                    }

                    $identity = user_monitor_resolve_customer_identity($username);
                    $customerId = $identity ? intval($identity['customer_id']) : 0;
                    $history = isset($historyByCustomer[$customerId]) ? $historyByCustomer[$customerId] : ['up' => 0, 'down' => 0];
                    $sessionUpload = intval($session->getProperty('bytes-in') ?: 0);
                    $sessionDownload = intval($session->getProperty('bytes-out') ?: 0);
                    $totalAll = $history['up'] + $history['down'] + $sessionUpload + $sessionDownload;
                    $normalized = user_monitor_normalize_username($username);
                    $comment = '-';

                    if ($identity && !empty($identity['fullname'])) {
                        $comment = $identity['fullname'];
                    } elseif (!empty($commentMap[$normalized])) {
                        $comment = $commentMap[$normalized];
                    }

                    $userList[] = array(
                        'username' => $username,
                        'user_comment' => $comment,
                        'address' => $session->getProperty('address') ?: '-',
                        'uptime' => $session->getProperty('uptime') ?: '-',
                        'total_usage' => user_monitor_formatBytes($totalAll),
                        'total_bytes' => $totalAll,
                        'status' => 'Online',
                        'customer_id' => $customerId,
                        'service' => 'hotspot',
                    );
                }
            }

            if ($service === 'pppoe' || $service === 'all') {
                $pppSessions = $client->sendSync(new RouterOS\Request('/ppp/active/print'));
                $pppSecrets = $client->sendSync(new RouterOS\Request('/ppp/secret/print'));
                $interfaces = $client->sendSync(new RouterOS\Request('/interface/print'));

                $pppCommentMap = array();
                foreach ($pppSecrets as $secret) {
                    $secretName = user_monitor_normalize_username($secret->getProperty('name'));
                    if ($secretName !== '') {
                        $pppCommentMap[$secretName] = trim((string) ($secret->getProperty('comment') ?: ''));
                    }
                }

                $interfaceByteMap = array();
                foreach ($interfaces as $interface) {
                    $name = trim((string) $interface->getProperty('name'));
                    if ($name !== '') {
                        $interfaceByteMap[$name] = [
                            'tx' => intval($interface->getProperty('tx-byte') ?: 0),
                            'rx' => intval($interface->getProperty('rx-byte') ?: 0),
                        ];
                    }
                }

                foreach ($pppSessions as $session) {
                    if (!user_monitor_is_pppoe_entry($session)) {
                        continue;
                    }

                    $username = trim((string) $session->getProperty('name'));
                    if ($username === '' || strtolower($username) === 'null') {
                        continue;
                    }

                    $identity = user_monitor_resolve_customer_identity($username);
                    $customerId = $identity ? intval($identity['customer_id']) : 0;
                    $history = isset($historyByCustomer[$customerId]) ? $historyByCustomer[$customerId] : ['up' => 0, 'down' => 0];
                    $interfaceName = '<pppoe-' . $username . '>';
                    $interfaceTraffic = isset($interfaceByteMap[$interfaceName]) ? $interfaceByteMap[$interfaceName] : ['tx' => 0, 'rx' => 0];
                    $totalAll = $history['up'] + $history['down'] + $interfaceTraffic['tx'] + $interfaceTraffic['rx'];
                    $normalized = user_monitor_normalize_username($username);
                    $comment = '-';

                    if ($identity && !empty($identity['fullname'])) {
                        $comment = $identity['fullname'];
                    } elseif (!empty($pppCommentMap[$normalized])) {
                        $comment = $pppCommentMap[$normalized];
                    }

                    $userList[] = array(
                        'username' => $username,
                        'user_comment' => $comment,
                        'address' => $session->getProperty('address') ?: '-',
                        'uptime' => $session->getProperty('uptime') ?: '-',
                        'total_usage' => user_monitor_formatBytes($totalAll),
                        'total_bytes' => $totalAll,
                        'status' => 'Online',
                        'customer_id' => $customerId,
                        'service' => 'pppoe',
                    );
                }
            }

            UserMonitorCache::set($cacheKey, $userList);
            return $userList;
        } catch (Exception $e) {
            error_log('USER_MONITOR active fetch fallback: ' . $e->getMessage());
        }
    }

    UserMonitorCache::set($cacheKey, $userList);
    return $userList;
}

function user_monitor_get_live_traffic()
{
    try {
        global $routes;
        $router = $routes['2'];

        $liveTraffic = user_monitor_fetch_active_users($router);
        foreach ($liveTraffic as &$user) {
            $user['timestamp'] = time();
        }

        header('Content-Type: application/json');
        echo json_encode($liveTraffic);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(array('error' => $e->getMessage()));
    }
}

function user_monitor_formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function user_monitor_formatSpeed($bytesPerSecond, $precision = 2)
{
    $units = array('B/s', 'KB/s', 'MB/s', 'GB/s');
    $bytes = max($bytesPerSecond, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function user_monitor_usageToBytes($usageStr)
{
    if (!$usageStr || $usageStr === '-' || $usageStr === '' || $usageStr === 'N/A') return 0;

    // Handle exact "0 B" case
    if (trim($usageStr) === '0 B' || trim($usageStr) === '0') return 0;

    // Parse value dan unit dengan regex yang lebih robust
    $cleanStr = str_replace(',', '', trim($usageStr));
    if (preg_match('/([0-9.]+)\s*([KMGT]?B)/i', $cleanStr, $matches)) {
        $value = floatval($matches[1]);
        $unit = strtoupper($matches[2]);

        // Jika nilai adalah 0 atau invalid, return 0
        if ($value === 0.0 || !is_numeric($value)) return 0;

        $multiplier = array(
            'B' => 1,
            'KB' => 1024,
            'MB' => 1024 * 1024,
            'GB' => 1024 * 1024 * 1024,
            'TB' => 1024 * 1024 * 1024 * 1024
        );

        $mult = isset($multiplier[$unit]) ? $multiplier[$unit] : 1;
        $result = floatval($value * $mult);
        return $result;
    }

    return 0;
}

// Function untuk remove/disconnect PPPoE interface
function user_monitor_remove_interface()
{
    header('Content-Type: application/json');

    try {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';

        if (empty($username)) {
            throw new Exception('Username required');
        }

        // Validasi username
        if ($username === 'null' || $username === 'NULL' || $username === null) {
            throw new Exception('Invalid username: ' . $username);
        }

        global $routes;
        $router = $routes['2'] ?? null;

        if (!$router) {
            $routerObj = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one();
            $router = $routerObj ? $routerObj['id'] : null;
        }

        if (!$router) {
            throw new Exception('No router found');
        }

        $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($router);

        if (!$mikrotik) {
            throw new Exception('Router not found');
        }

        $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);
        $service = isset($_POST['service']) ? strtolower(trim($_POST['service'])) : 'pppoe';

        if ($service === 'hotspot') {
            $hotspotActive = $client->sendSync(new RouterOS\Request('/ip/hotspot/active/print'));
            $sessionId = null;

            foreach ($hotspotActive as $session) {
                if ($session->getProperty('user') === $username) {
                    $sessionId = $session->getProperty('.id');
                    break;
                }
            }

            if (!$sessionId) {
                throw new Exception('Active Hotspot session not found for user: ' . $username);
            }

            $removeRequest = new RouterOS\Request('/ip/hotspot/active/remove');
            $removeRequest->setArgument('numbers', $sessionId);
            $client->sendSync($removeRequest);

            UserMonitorCache::clear();

            echo json_encode(array(
                'success' => true,
                'message' => 'Hotspot user disconnected successfully',
                'username' => $username,
                'session_id' => $sessionId,
                'action' => 'disconnect'
            ));
            return;
        }

        // Step 1: Find active PPPoE session ID by username
        $pppActive = $client->sendSync(new RouterOS\Request('/ppp/active/print'));
        $sessionId = null;

        foreach ($pppActive as $session) {
            if ($session->getProperty('name') === $username) {
                $sessionId = $session->getProperty('.id');
                break;
            }
        }

        if (!$sessionId) {
            throw new Exception('Active PPPoE session not found for user: ' . $username);
        }

        // Step 2: Remove/disconnect the active session
        $removeRequest = new RouterOS\Request('/ppp/active/remove');
        $removeRequest->setArgument('numbers', $sessionId);
        $client->sendSync($removeRequest);

        // CLEAR CACHE setelah remove user
        UserMonitorCache::clear();

        error_log("SUCCESS: PPPoE session removed for user: $username (Session ID: $sessionId)");

        echo json_encode(array(
            'success' => true,
            'message' => 'User disconnected successfully',
            'username' => $username,
            'session_id' => $sessionId,
            'action' => 'disconnect'
        ));
    } catch (Exception $e) {
        error_log("ERROR: Failed to remove PPPoE session: " . $e->getMessage());

        echo json_encode(array(
            'success' => false,
            'error' => $e->getMessage()
        ));
    }
}


// Function untuk reset usage history from database
function user_monitor_resetUsageHistory($customerId)
{
    // Get customer data
    $customer = ORM::for_table('tbl_customers')->find_one($customerId);
    if (!$customer) {
        error_log("ADMIN RESET: Customer not found: $customerId");
        return;
    }

    $username    = $customer['pppoe_username'] ?? '';
    $userComment = $customer['fullname'] ?? '';

    // Ambil periode billing aktif dari expiration
    $recharge = ORM::for_table('tbl_user_recharges')
        ->where('customer_id', $customerId)
        ->where('status', 'on')
        ->find_one();

    $year  = intval(date('Y'));
    $month = intval(date('m'));
    if ($recharge && !empty($recharge['expiration'])) {
        $year  = intval(date('Y', strtotime($recharge['expiration'])));
        $month = intval(date('m', strtotime($recharge['expiration'])));
    }

    // Ambil usage dari history + current session
    $historyRecord = ORM::for_table('tbl_user_usage_history')
        ->where('customer_id', $customerId)
        ->find_one();

    $statusRecord = ORM::for_table('tbl_user_connection_status')
        ->where('customer_id', $customerId)
        ->find_one();

    $currentTx = $statusRecord ? intval($statusRecord['last_tx']) : 0;
    $currentRx = $statusRecord ? intval($statusRecord['last_rx']) : 0;

    $totalUpload   = ($historyRecord ? intval($historyRecord['total_upload'])   : 0) + $currentTx;
    $totalDownload = ($historyRecord ? intval($historyRecord['total_download'])  : 0) + $currentRx;

    // Simpan snapshot jika ada usage
    if ($totalUpload > 0 || $totalDownload > 0) {
        $existing = ORM::for_table('tbl_usage_history_monthly')
            ->where('customer_id', $customerId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->find_one();

        if ($existing) {
            $existing->total_upload   = max(intval($existing['total_upload']), $totalUpload);
            $existing->total_download = max(intval($existing['total_download']), $totalDownload);
            $existing->total_bytes    = $existing->total_upload + $existing->total_download;
            $existing->recorded_at    = date('Y-m-d H:i:s');
            $existing->save();
        } else {
            $snapshot               = ORM::for_table('tbl_usage_history_monthly')->create();
            $snapshot->customer_id  = $customerId;
            $snapshot->username     = $username;
            $snapshot->user_comment = $userComment;
            $snapshot->period_year  = $year;
            $snapshot->period_month = $month;
            $snapshot->total_upload   = $totalUpload;
            $snapshot->total_download = $totalDownload;
            $snapshot->total_bytes    = $totalUpload + $totalDownload;
            $snapshot->recorded_at  = date('Y-m-d H:i:s');
            $snapshot->save();
        }

        error_log("ADMIN RESET: Monthly snapshot saved for $userComment ($year-$month) - UP: $totalUpload, DOWN: $totalDownload");
    }

    // Hapus history
    if ($historyRecord) {
        $historyRecord->delete();
        error_log("ADMIN RESET: Usage history cleared for customer ID: $customerId ({$customer['fullname']})");
    }

    // Hapus connection status
    if ($statusRecord) {
        $statusRecord->delete();
        error_log("ADMIN RESET: Connection status cleared for customer ID: $customerId ({$customer['fullname']})");
    }
}

// Function untuk format speed dalam Mbps (Megabits per second)
function user_monitor_formatSpeedMbps($bitsPerSecond, $precision = 2)
{
    $units = array('bps', 'Kbps', 'Mbps', 'Gbps');
    $bits = max($bitsPerSecond, 0);
    $pow = floor(($bits ? log($bits) : 0) / log(1000)); // Network speed menggunakan 1000
    $pow = min($pow, count($units) - 1);
    $bits /= pow(1000, $pow);
    return round($bits, $precision) . ' ' . $units[$pow];
}


// INTERFACE MONITORING: Get ethernet interfaces dari MikroTik
function user_monitor_get_interfaces()
{
    header('Content-Type: application/json');

    try {
        global $routes;
        $router = $routes['2'];

        $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($router);
        if (!$mikrotik) {
            throw new Exception('Router not found');
        }

        $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

        // Get all interfaces
        $interfaces = $client->sendSync(new RouterOS\Request('/interface/print'));

        $etherInterfaces = [];

        foreach ($interfaces as $interface) {
            $name = $interface->getProperty('name');
            $type = $interface->getProperty('type');
            $running = $interface->getProperty('running');
            $disabled = $interface->getProperty('disabled');
            $comment = $interface->getProperty('comment');

            // Filter hanya ethernet interfaces
            if (strpos($type, 'ether') !== false || $type === 'ethernet') {
                $status = 'disabled';
                if ($disabled !== 'true') {
                    $status = ($running === 'true') ? 'running' : 'stopped';
                }

                $etherInterfaces[] = [
                    'name' => $name,
                    'type' => $type,
                    'status' => $status,
                    'description' => $comment ?: 'Ethernet Interface',
                    'running' => ($running === 'true'),
                    'disabled' => ($disabled === 'true')
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'interfaces' => $etherInterfaces,
            'total' => count($etherInterfaces)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// INTERFACE TRAFFIC: Get real-time traffic untuk interface tertentu
function user_monitor_get_interface_traffic()
{
    header('Content-Type: application/json');

    try {
        $interfaceName = isset($_GET['interface']) ? $_GET['interface'] : '';

        if (empty($interfaceName)) {
            throw new Exception('Interface name required');
        }

        global $routes;
        $router = $routes['2'];

        $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($router);
        if (!$mikrotik) {
            throw new Exception('Router not found');
        }

        $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

        // Get interface statistics
        $interfaceStats = $client->sendSync(new RouterOS\Request('/interface/print'));

        $interfaceData = null;
        foreach ($interfaceStats as $interface) {
            if ($interface->getProperty('name') === $interfaceName) {
                $interfaceData = [
                    'name' => $interface->getProperty('name'),
                    'type' => $interface->getProperty('type'),
                    'mtu' => intval($interface->getProperty('mtu') ?: 1500),
                    'running' => ($interface->getProperty('running') === 'true'),
                    'rx_byte' => intval($interface->getProperty('rx-byte') ?: 0),
                    'tx_byte' => intval($interface->getProperty('tx-byte') ?: 0),
                    'rx_packet' => intval($interface->getProperty('rx-packet') ?: 0),
                    'tx_packet' => intval($interface->getProperty('tx-packet') ?: 0)
                ];
                break;
            }
        }

        if (!$interfaceData) {
            throw new Exception('Interface not found');
        }

        // Get real-time traffic rate
        $currentRxRate = 0;
        $currentTxRate = 0;
        $maxRate = 1000; // Default 1000 Mbps

        try {
            $trafficRequest = new RouterOS\Request('/interface/monitor-traffic');
            $trafficRequest->setArgument('interface', $interfaceName);
            $trafficRequest->setArgument('once', '');

            $trafficResult = $client->sendSync($trafficRequest);

            foreach ($trafficResult as $traffic) {
                // BUG FIX: Attempt to get bits-per-second, fallback to standard byte counter if needed
                $rxBits = intval($traffic->getProperty('rx-bits-per-second') ?: 0);
                $txBits = intval($traffic->getProperty('tx-bits-per-second') ?: 0);
                
                // If bits per second is 0, check if we can get from alternative or raw
                if ($rxBits === 0) $rxBits = intval($traffic->getProperty('receive-bits-per-second') ?: 0);
                if ($txBits === 0) $txBits = intval($traffic->getProperty('transmit-bits-per-second') ?: 0);

                $currentRxRate = round($rxBits / 1000000, 2);
                $currentTxRate = round($txBits / 1000000, 2);
                break;
            }
        } catch (Exception $trafficException) {
            error_log("Traffic monitoring error for $interfaceName: " . $trafficException->getMessage());
        }

        // Format response
        $response = [
            'success' => true,
            'interface' => $interfaceName,
            'data' => [
                'rx_rate' => $currentRxRate,
                'tx_rate' => $currentTxRate,
                'max_rate' => $maxRate,
                'rx_total' => user_monitor_formatBytes($interfaceData['rx_byte']),
                'tx_total' => user_monitor_formatBytes($interfaceData['tx_byte']),
                'rx_packets' => number_format($interfaceData['rx_packet']),
                'tx_packets' => number_format($interfaceData['tx_packet']),
                'mtu' => $interfaceData['mtu'],
                'link_speed' => '1 Gbps',
                'status' => $interfaceData['running'] ? 'running' : 'stopped',
                'rx_percentage' => round(($currentRxRate / $maxRate) * 100, 2),
                'tx_percentage' => round(($currentTxRate / $maxRate) * 100, 2)
            ],
            'timestamp' => time()
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
function user_monitor_api_diagnostics()
{
    header('Content-Type: application/json');

    try {
        $routerId = user_monitor_get_router_id_from_routes();
        if (!$routerId) {
            throw new Exception('No router selected');
        }

        $mikrotik = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one($routerId);
        if (!$mikrotik) {
            throw new Exception('Router not found');
        }

        $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

        $checks = [];

        $commands = [
            'system_resource' => '/system/resource/print',
            'ppp_active'      => '/ppp/active/print',
            'ppp_secret'      => '/ppp/secret/print',
            'queue_simple'    => '/queue/simple/print',
            'interface'       => '/interface/print',
            'nat'             => '/ip/firewall/nat/print'
        ];

        foreach ($commands as $label => $command) {
            try {
                $response = $client->sendSync(new RouterOS\Request($command));
                $count = 0;
                foreach ($response as $row) {
                    $count++;
                    if ($count >= 3) {
                        break;
                    }
                }

                $checks[$label] = [
                    'ok'      => true,
                    'command' => $command,
                    'sample'  => $count,
                    'error'   => null
                ];
            } catch (Exception $inner) {
                $checks[$label] = [
                    'ok'      => false,
                    'command' => $command,
                    'sample'  => 0,
                    'error'   => $inner->getMessage()
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'router'  => [
                'id'         => intval($mikrotik['id']),
                'name'       => $mikrotik['name'],
                'ip_address' => $mikrotik['ip_address']
            ],
            'checks'  => $checks,
            'note'    => 'ok=true means API command executed successfully; sample is up to 3 rows read for quick check.'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error'   => $e->getMessage()
        ]);
    }

    exit();
}

// ===================================================
// CHECK UPDATE
// ===================================================
function mikromon_check_update()
{
    header('Content-Type: application/json');

    try {
        $github_token = ORM::for_table('tbl_appconfig')->where('setting', 'github_token')->find_one();
        if (!$github_token || empty($github_token->value)) {
            throw new Exception('GitHub token not configured');
        }

        $current_version = '1.0.0';
        $config = ORM::for_table('tbl_appconfig')->where('setting', 'mikromon_version')->find_one();
        if ($config) {
            $current_version = $config->value;
        }

        $api_url = 'https://api.github.com/repos/ExodiaForb-Plugin/Mikromon/contents/version.json?ref=main';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $github_token->value,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: PHPNuxBill-DATA USAGE'
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to fetch version info (HTTP ' . $http_code . ')');
        }

        $api_data = json_decode($response, true);
        if (!isset($api_data['content'])) {
            throw new Exception('Invalid version response');
        }

        $version_data   = json_decode(base64_decode($api_data['content']), true);
        $latest_version = $version_data['version'] ?? '1.0.0';

        echo json_encode([
            'success'         => true,
            'current_version' => $current_version,
            'latest_version'  => $latest_version,
            'has_update'      => version_compare($latest_version, $current_version, '>')
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ===================================================
// RUN UPDATE
// ===================================================
function mikromon_run_update()
{
    header('Content-Type: application/json');

    $result = ['success' => false, 'message' => '', 'details' => []];

    try {
        $github_token = ORM::for_table('tbl_appconfig')->where('setting', 'github_token')->find_one();
        if (!$github_token || empty($github_token->value)) {
            throw new Exception('GitHub token not configured');
        }

        $plugin_dir = __DIR__;
        $system_dir = realpath($plugin_dir . '/../');
        $root_dir   = realpath($plugin_dir . '/../../');
        $ui_dir     = $root_dir . '/ui';
        $temp_dir   = $plugin_dir . '/_update_temp';

        // ========== STEP 1: Fetch version info ==========
        $api_url = 'https://api.github.com/repos/ExodiaForb-Plugin/Mikromon/contents/version.json?ref=main';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $github_token->value,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: PHPNuxBill-DATA USAGE'
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Failed to fetch version info (HTTP ' . $http_code . ')');
        }

        $version_data = json_decode(base64_decode(json_decode($response, true)['content']), true);
        $new_version  = $version_data['version'];
        $result['details'][] = "✓ Fetched update info (v{$new_version})";

        // ========== STEP 2: Download ZIP ==========
        $zip_file    = $plugin_dir . '/_update.zip';
        $download_url = 'https://api.github.com/repos/ExodiaForb-Plugin/Mikromon/zipball/main';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $download_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $github_token->value,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: PHPNuxBill-DATA USAGE'
        ]);

        $zip_content = curl_exec($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || empty($zip_content)) {
            throw new Exception('Failed to download update package (HTTP ' . $http_code . ')');
        }

        file_put_contents($zip_file, $zip_content);
        $result['details'][] = "✓ Downloaded update package";

        // ========== STEP 3: Extract ZIP ==========
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            throw new Exception('Failed to open update package');
        }

        if (!is_dir($temp_dir)) mkdir($temp_dir, 0755, true);
        $zip->extractTo($temp_dir);
        $zip->close();

        $extracted_folders = glob($temp_dir . '/*', GLOB_ONLYDIR);
        if (empty($extracted_folders)) {
            throw new Exception('Invalid update package structure');
        }
        $extracted_dir = $extracted_folders[0];
        $result['details'][] = "✓ Extracted update package";

        // ========== STEP 4: Update PHP files ==========
        $php_source = $extracted_dir . '/_mikromon_php';
        if (is_dir($php_source)) {
            $php_files = glob($php_source . '/*.php');
            foreach ($php_files as $file) {
                copy($file, $plugin_dir . '/' . basename($file));
            }
            $result['details'][] = "✓ Updated PHP modules (" . count($php_files) . " files)";
        }

        // ========== STEP 5: Update TPL files ==========
        $tpl_source = $extracted_dir . '/_mikromon_ui';
        if (is_dir($tpl_source)) {
            $tpl_files = glob($tpl_source . '/*.tpl');
            foreach ($tpl_files as $file) {
                copy($file, $plugin_dir . '/ui/' . basename($file));
            }
            $result['details'][] = "✓ Updated UI templates (" . count($tpl_files) . " files)";
        }

        // ========== STEP 6: Update Cron files ==========
        $cron_source = $extracted_dir . '/_mikromon_cron';
        if (is_dir($cron_source)) {
            $cron_files = glob($cron_source . '/*.php');
            foreach ($cron_files as $file) {
                copy($file, $system_dir . '/' . basename($file));
            }
            $result['details'][] = "✓ Updated cron files (" . count($cron_files) . " files)";
        }

        // ========== STEP 7: Update UI Custom ==========
        $ui_custom_source = $extracted_dir . '/_mikromon_ui_custom';
        if (is_dir($ui_custom_source)) {
            mikromon_update_copy_directory($ui_custom_source, $ui_dir . '/ui_custom');
            $result['details'][] = "✓ Updated custom UI components";
        }

        // ========== STEP 8: Update version in database ==========
        $config = ORM::for_table('tbl_appconfig')->where('setting', 'mikromon_version')->find_one();
        if (!$config) {
            $config          = ORM::for_table('tbl_appconfig')->create();
            $config->setting = 'mikromon_version';
        }
        $config->value = $new_version;
        $config->save();

        $config_date = ORM::for_table('tbl_appconfig')->where('setting', 'mikromon_updated_at')->find_one();
        if (!$config_date) {
            $config_date          = ORM::for_table('tbl_appconfig')->create();
            $config_date->setting = 'mikromon_updated_at';
        }
        $config_date->value = date('Y-m-d H:i:s');
        $config_date->save();

        $result['details'][] = "✓ Updated version to v{$new_version}";

        // ========== STEP 9: Cleanup ==========
        @unlink($zip_file);
        mikromon_delete_directory($temp_dir);
        $result['details'][] = "✓ Cleaned up temporary files";

        $result['success']     = true;
        $result['message']     = "Successfully updated to v{$new_version}";
        $result['new_version'] = $new_version;

    } catch (Exception $e) {
        if (isset($zip_file) && file_exists($zip_file)) @unlink($zip_file);
        if (isset($temp_dir) && is_dir($temp_dir)) mikromon_delete_directory($temp_dir);
        $result['success'] = false;
        $result['message'] = 'Update failed: ' . $e->getMessage();
    }

    echo json_encode($result);
    exit();
}

// ===================================================
// RUN UNINSTALL
// ===================================================
function mikromon_run_uninstall()
{
    header('Content-Type: application/json');

    $result = ['success' => false, 'message' => '', 'details' => []];

    try {
        $plugin_dir = __DIR__;
        $system_dir = realpath($plugin_dir . '/../');
        $root_dir   = realpath($plugin_dir . '/../../');
        $ui_dir     = $root_dir . '/ui';

        // ========== Drop tables ==========
        $tables = [
            'tbl_user_connection_status',
            'tbl_user_usage_history',
            'tbl_router_status',
            'tbl_expiration_tracking',
            'tbl_usage_history_monthly'
        ];
        foreach ($tables as $table) {
            try {
                ORM::raw_execute("DROP TABLE IF EXISTS `{$table}`");
                $result['details'][] = "✓ Dropped table: {$table}";
            } catch (Exception $e) {
                $result['details'][] = "⚠ Could not drop {$table}: " . $e->getMessage();
            }
        }

        // ========== Delete appconfig entries ==========
        ORM::for_table('tbl_appconfig')->where('setting', 'mikromon_version')->delete_many();
        ORM::for_table('tbl_appconfig')->where('setting', 'mikromon_installed_at')->delete_many();
        ORM::for_table('tbl_appconfig')->where('setting', 'mikromon_updated_at')->delete_many();
        $result['details'][] = "✓ Removed configuration entries";

        // ========== Delete PHP files ==========
        $php_files = ['user_monitor.php', 'user_traffic_api.php', 'traffic_hooks.php', 'ui_datausage_menu.php'];
        foreach ($php_files as $f) {
            @unlink($plugin_dir . '/' . $f);
        }
        $result['details'][] = "✓ Removed plugin PHP files";

        // ========== Delete TPL files ==========
        @unlink($plugin_dir . '/ui/user_monitor.tpl');
        @unlink($ui_dir . '/ui_custom/customer/datausage.tpl');
        $result['details'][] = "✓ Removed template files";

        // ========== Delete cron files ==========
        @unlink($system_dir . '/traffic_monitor.php');
        @unlink($system_dir . '/cron_check_payment.php');
        $result['details'][] = "✓ Removed cron files";

        $result['success'] = true;
        $result['message'] = 'Data Usage uninstalled successfully';

    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = $e->getMessage();
    }

    echo json_encode($result);
    exit();
}

// ===================================================
// HELPER: Recursive delete directory
// ===================================================
function mikromon_delete_directory($dir)
{
    if (!is_dir($dir)) return true;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? mikromon_delete_directory($path) : unlink($path);
    }
    return rmdir($dir);
}

// ===================================================
// HELPER: Recursive copy directory (for update)
// ===================================================
function mikromon_update_copy_directory($source, $dest)
{
    if (!is_dir($dest)) mkdir($dest, 0755, true);

    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $src_path = $source . '/' . $file;
        $dst_path = $dest . '/' . $file;
        if (is_dir($src_path)) {
            mikromon_update_copy_directory($src_path, $dst_path);
        } else {
            copy($src_path, $dst_path);
        }
    }
    closedir($dir);
}

// Handler for manual cleanup trigger
