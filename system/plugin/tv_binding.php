<?php

/**
 * TV MAC Address Binding Plugin
 * Admin interface to manage TV devices bound to internet plans
 * Shows online/offline status, expiry status, and allows add/delete bindings
 */

use PEAR2\Net\RouterOS;

// Ensure database table exists
tv_binding_ensure_tables();

register_menu("TV Binding", true, "tv_binding", 'SERVICES', 'ion ion-monitor', '', '', ['SuperAdmin', 'Admin']);
// Enforce expiry on every cron run
register_hook('cronjob', 'tv_binding_expire_cron');

/**
 * Auto-create database table if it doesn't exist
 */
function tv_binding_ensure_tables()
{
    try {
        $db = ORM::get_db();

        // Run only once per installation — flag stored in tbl_appconfig
        $done = $db->query("SELECT value FROM tbl_appconfig WHERE setting = 'tv_binding_schema_v1' LIMIT 1")->fetch();
        if ($done) return;

        $db->exec("CREATE TABLE IF NOT EXISTS `tbl_tv_bindings` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `mac_address` VARCHAR(17) NOT NULL UNIQUE,
            `router_id` INT NOT NULL,
            `plan_id` INT NOT NULL,
            `phone_number` VARCHAR(20) NOT NULL,
            `customer_id` INT DEFAULT NULL,
            `binding_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `expiry_date` DATETIME DEFAULT NULL,
            `status` ENUM('active', 'expired', 'inactive') DEFAULT 'active',
            `payment_gateway_id` INT DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_mac` (`mac_address`),
            KEY `idx_router` (`router_id`),
            KEY `idx_plan` (`plan_id`),
            KEY `idx_status` (`status`),
            KEY `idx_expiry` (`expiry_date`),
            KEY `idx_phone` (`phone_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add new columns if not exist
        $new_cols = [
            "ALTER TABLE `tbl_tv_bindings` ADD COLUMN `binding_type` VARCHAR(20) DEFAULT 'plan'",
            "ALTER TABLE `tbl_tv_bindings` ADD COLUMN `voucher_code` VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE `tbl_tv_bindings` ADD COLUMN `checkout_request_id` VARCHAR(100) DEFAULT NULL"
        ];
        foreach ($new_cols as $sql) {
            try { $db->exec($sql); } catch (Exception $e) { /* column already exists */ }
        }
        // Allow pending status for STK
        try {
            $db->exec("ALTER TABLE `tbl_tv_bindings` MODIFY COLUMN `status` ENUM('active','expired','inactive','pending') DEFAULT 'active'");
        } catch (Exception $e) { /* ignore */ }

        // Ensure tbl_payment_gateway has the columns the STK push writes to
        try {
            $db->exec("ALTER TABLE `tbl_payment_gateway` ADD COLUMN `routers_id` INT DEFAULT NULL");
        } catch (Exception $e) { /* already exists */ }
        try {
            $db->exec("ALTER TABLE `tbl_payment_gateway` ADD COLUMN `mac_address` VARCHAR(50) DEFAULT NULL");
        } catch (Exception $e) { /* already exists */ }

        // Mark migration done
        $db->exec("INSERT INTO tbl_appconfig (setting, value) VALUES ('tv_binding_schema_v1', '1') ON DUPLICATE KEY UPDATE value='1'");

    } catch (Exception $e) {
        error_log('TV_BINDING ensure_tables error: ' . $e->getMessage());
    }
}

/**
 * Clean up old TV binding cache files (runs periodically)
 */
function tv_binding_cleanup_cache()
{
    global $CACHE_PATH;
    
    $cache_dir = rtrim($CACHE_PATH, '/\\');
    if (!is_dir($cache_dir)) {
        return;
    }
    
    // Clean cache files older than 10 minutes
    $max_age = 600; // 10 minutes
    $current_time = time();
    
    $pattern = $cache_dir . '/tv_binding_*';
    foreach (glob($pattern) as $cache_file) {
        if (is_file($cache_file) && ($current_time - filemtime($cache_file)) > $max_age) {
            @unlink($cache_file);
        }
    }
}

function tv_binding()
{
    global $ui;

    // Safety net: enforce expiry even when cron is delayed.
    tv_binding_run_expiry_guard();
    
    // Periodically clean old cache files (every 5 minutes)
    static $last_cleanup = 0;
    if ((time() - $last_cleanup) > 300) {
        tv_binding_cleanup_cache();
        $last_cleanup = time();
    }

    // Public frontend actions — no admin auth required
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pub_action = _req('action', '');
        if ($pub_action === 'public_bind') {
            handlePlanStkBinding();
            return;
        }
        if ($pub_action === 'public_check') {
            handleCheckPayment();
            return;
        }
    }

    _admin();
    
    $admin = Admin::_info();
    // Only allow SuperAdmin and Admin users
    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
        die('Access Denied: Only Admin and SuperAdmin users can access this feature.');
    }
    $ui->assign('_admin', $admin);
    $ui->assign('_title', 'TV MAC Address Binding');
    $ui->assign('_system_menu', 'tv_binding');

    // Get action parameter — action is sent in POST body by the JS fetch calls
    $action = _req('action', '');

    // Handle actions
    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        handleAddBinding();
        return;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        handleDeleteBinding();
        return;
    }

    if ($action === 'check_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        handleCheckPayment();
        return;
    }

    // Get filter parameters
    $status_filter = _get('status', 'all');   // all | active | expired | online | offline
    $router_filter = _get('router', '');
    $search        = _get('search', '');
    $page          = max(1, (int)_get('page', 1));
    $per_page      = (int)_get('per_page', 25);
    if (!in_array($per_page, [10, 25, 50, 100], true)) {
        $per_page = 25;
    }

    // Build count query
    $count_query = tv_binding_build_list_query($router_filter, $search);

    // Apply fast SQL status filters first where possible.
    if ($status_filter === 'active') {
        $count_query = $count_query->where_not_equal('tb.status', 'pending')
            ->where_raw('(tb.expiry_date IS NULL OR tb.expiry_date >= NOW())');
    } elseif ($status_filter === 'expired') {
        $count_query = $count_query->where_not_equal('tb.status', 'pending')
            ->where_raw('(tb.expiry_date IS NOT NULL AND tb.expiry_date < NOW())');
    } elseif ($status_filter === 'pending') {
        $count_query = $count_query->where('tb.status', 'pending');
    }

    $total_bindings = (int)$count_query->count();
    $total_pages = max(1, (int)ceil($total_bindings / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;

    // Fetch only visible rows for this page.
    $data_query = tv_binding_build_list_query($router_filter, $search);
    if ($status_filter === 'active') {
        $data_query = $data_query->where_not_equal('tb.status', 'pending')
            ->where_raw('(tb.expiry_date IS NULL OR tb.expiry_date >= NOW())');
    } elseif ($status_filter === 'expired') {
        $data_query = $data_query->where_not_equal('tb.status', 'pending')
            ->where_raw('(tb.expiry_date IS NOT NULL AND tb.expiry_date < NOW())');
    } elseif ($status_filter === 'pending') {
        $data_query = $data_query->where('tb.status', 'pending');
    }

    $page_bindings = $data_query->order_by_desc('tb.binding_date')->offset($offset)->limit($per_page)->find_array();

    // Build online/offline status only for visible rows; cached snapshots avoid refresh spikes.
    // Increased cache TTL to 120 seconds (2 minutes) to reduce MikroTik API calls
    $online_status_map = getTVOnlineStatusMap($page_bindings, 120);
    $bypass_status_map = getTVBypassStatusMap($page_bindings, 120);

    $tv_bindings = [];
    $stats = [
        'total'   => $total_bindings,
        'active'  => 0,
        'expired' => 0,
        'online'  => 0,
        'offline' => 0,
    ];

    foreach ($page_bindings as $binding) {

        // Check expiry status
        $expiry_date = strtotime($binding['expiry_date']);
        $now = time();

        if (($binding['status'] ?? '') === 'pending') {
            $binding['plan_status'] = 'pending';
            // don't increment active/expired stats for pending
        } elseif ($expiry_date < $now) {
            $binding['plan_status'] = 'expired';
            $stats['expired']++;
        } else {
            $binding['plan_status'] = 'active';
            $stats['active']++;
        }

        // Read online/offline status from precomputed map
        $binding['online_status'] = $online_status_map[$binding['mac_address']] ?? 'unknown';
        $binding['access_status'] = !empty($bypass_status_map[$binding['mac_address']]) ? 'allowed' : 'blocked';
        
        if ($binding['online_status'] === 'online') {
            $stats['online']++;
        } else {
            $stats['offline']++;
        }

        // Format dates for display
        $binding['binding_date_display'] = date('Y-m-d H:i', strtotime($binding['binding_date']));
        $binding['expiry_date_display'] = !empty($binding['expiry_date']) ? date('Y-m-d H:i', $expiry_date) : '-';

        // Apply online/offline status filters after status-map resolution.
        if ($status_filter === 'online' && $binding['online_status'] !== 'online') continue;
        if ($status_filter === 'offline' && $binding['online_status'] !== 'offline') continue;

        $tv_bindings[] = $binding;
    }

    // Keep summary cards meaningful for fast SQL-checkable states.
    $active_query = tv_binding_build_list_query($router_filter, $search)
        ->where_not_equal('tb.status', 'pending')
        ->where_raw('(tb.expiry_date IS NULL OR tb.expiry_date >= NOW())');
    $expired_query = tv_binding_build_list_query($router_filter, $search)
        ->where_not_equal('tb.status', 'pending')
        ->where_raw('(tb.expiry_date IS NOT NULL AND tb.expiry_date < NOW())');
    $stats['active'] = (int)$active_query->count();
    $stats['expired'] = (int)$expired_query->count();

    $pagination_window = 2;
    $pagination_start = max(1, $page - $pagination_window);
    $pagination_end = min($total_pages, $page + $pagination_window);

    // Get routers for filter dropdown
    $routers = ORM::for_table('tbl_routers')->find_array();

    // Get plans for add binding form
    $plans = ORM::for_table('tbl_plans')->where('type', 'Hotspot')->where('enabled', 1)->find_array();

    // Assign to template
    $ui->assign('tv_bindings', $tv_bindings);
    $ui->assign('routers', $routers);
    $ui->assign('plans', $plans);
    $ui->assign('status_filter', $status_filter);
    $ui->assign('router_filter', $router_filter);
    $ui->assign('search', $search);
    $ui->assign('page', $page);
    $ui->assign('per_page', $per_page);
    $ui->assign('total_pages', $total_pages);
    $ui->assign('pagination_start', $pagination_start);
    $ui->assign('pagination_end', $pagination_end);
    $ui->assign('stats', $stats);

    $ui->display('tv_binding.tpl');
}

/**
 * Run expiry enforcement no more than once per minute from web requests.
 */
function tv_binding_run_expiry_guard($interval_seconds = 60)
{
    global $CACHE_PATH;

    $marker = rtrim($CACHE_PATH, '/\\') . '/tv_binding_expiry_guard.marker';
    if (is_file($marker) && (time() - filemtime($marker)) < $interval_seconds) {
        return;
    }

    tv_binding_expire_cron();
    @file_put_contents($marker, (string)time());
}

/**
 * Shared list query builder for TV bindings.
 */
function tv_binding_build_list_query($router_filter, $search)
{
    $query = ORM::for_table('tbl_tv_bindings')
        ->table_alias('tb')
        ->select('tb.*')
        ->select('p.name_plan', 'plan_name')
        ->select('p.price', 'plan_price')
        ->select('p.validity', 'plan_validity')
        ->select('p.validity_unit', 'plan_validity_unit')
        ->select('r.name', 'router_name')
        ->select('r.ip_address', 'router_ip')
        ->left_outer_join('tbl_plans', ['tb.plan_id', '=', 'p.id'], 'p')
        ->left_outer_join('tbl_routers', ['tb.router_id', '=', 'r.id'], 'r');

    if ($router_filter) {
        $query = $query->where('tb.router_id', $router_filter);
    }
    if ($search) {
        $query = $query->where_raw('(tb.mac_address LIKE ? OR p.name_plan LIKE ?)', ["%$search%", "%$search%"]);
    }

    return $query;
}

/**
 * Check if TV (by MAC address) is currently online on Mikrotik
 * Returns: 'online', 'offline', or 'error'
 */
function checkTVOnlineStatus($mac_address, $router_ip)
{
    try {
        if (!$router_ip) return 'unknown';

        $router = ORM::for_table('tbl_routers')->where('ip_address', $router_ip)->find_one();
        if (!$router) return 'unknown';

        $client = Mikrotik::getClient($router_ip, $router->username, $router->password);
        $macUpper = strtoupper($mac_address);

        // 1. Hotspot active sessions (non-bypassed devices)
        try {
            $req = new RouterOS\Request('/ip/hotspot/active/print');
            foreach ($client->sendSync($req) as $s) {
                if (strtoupper((string)$s->getProperty('mac-address')) === $macUpper) return 'online';
            }
        } catch (Exception $e) { /* ignore */ }

        // 2. ARP table — most reliable for bypassed devices; 'reachable' means traffic seen recently
        try {
            $req = new RouterOS\Request('/ip/arp/print');
            foreach ($client->sendSync($req) as $arp) {
                if (strtoupper((string)$arp->getProperty('mac-address')) === $macUpper) {
                    $state = (string)$arp->getProperty('status');
                    // 'reachable' = recently active; 'permanent' = static; anything else = offline
                    return in_array($state, ['reachable', 'permanent', '']) ? 'online' : 'offline';
                }
            }
        } catch (Exception $e) { /* ignore */ }

        // 3. DHCP lease — 'bound' means the device currently holds a lease
        try {
            $req = new RouterOS\Request('/ip/dhcp-server/lease/print');
            foreach ($client->sendSync($req) as $lease) {
                if (strtoupper((string)$lease->getProperty('mac-address')) === $macUpper) {
                    return ((string)$lease->getProperty('status') === 'bound') ? 'online' : 'offline';
                }
            }
        } catch (Exception $e) { /* ignore */ }

        return 'offline';
    } catch (Exception $e) {
        error_log('TV_BINDING checkTVOnlineStatus error: ' . $e->getMessage());
        return 'error';
    }
}

/**
 * Resolve online status for all listed MAC bindings in batched calls per router.
 * This avoids the previous N+1 pattern where each row opened MikroTik sessions.
 */
function getTVOnlineStatusMap($bindings, $cache_ttl_seconds = 20)
{
    global $CACHE_PATH;

    $status_map = [];
    if (empty($bindings)) {
        return $status_map;
    }

    // Group requested MACs by router IP.
    $router_mac_groups = [];
    foreach ($bindings as $binding) {
        $mac = strtoupper((string)($binding['mac_address'] ?? ''));
        if ($mac === '') {
            continue;
        }
        $status_map[$binding['mac_address']] = 'unknown';
        $router_ip = (string)($binding['router_ip'] ?? '');
        if ($router_ip === '') {
            continue;
        }
        if (!isset($router_mac_groups[$router_ip])) {
            $router_mac_groups[$router_ip] = [];
        }
        $router_mac_groups[$router_ip][$mac] = true;
    }

    foreach ($router_mac_groups as $router_ip => $mac_set) {
        try {
            $cache_file = rtrim($CACHE_PATH, '/\\') . '/tv_binding_status_' . md5($router_ip) . '.json';
            $cached_router_result = [];
            if (is_file($cache_file) && (time() - filemtime($cache_file)) <= $cache_ttl_seconds) {
                $raw_cache = @file_get_contents($cache_file);
                if ($raw_cache !== false) {
                    $decoded_cache = json_decode($raw_cache, true);
                    if (is_array($decoded_cache)) {
                        $cached_router_result = $decoded_cache;
                    }
                }
            }

            $router_result = [];
            $missing_macs = [];
            foreach ($mac_set as $mac => $_) {
                if (isset($cached_router_result[$mac])) {
                    $router_result[$mac] = $cached_router_result[$mac];
                } else {
                    $missing_macs[$mac] = true;
                }
            }

            if (empty($missing_macs)) {
                foreach ($bindings as $binding) {
                    if (($binding['router_ip'] ?? '') !== $router_ip) {
                        continue;
                    }
                    $src_mac = strtoupper((string)$binding['mac_address']);
                    if (isset($router_result[$src_mac])) {
                        $status_map[$binding['mac_address']] = $router_result[$src_mac];
                    }
                }
                continue;
            }

            $router = ORM::for_table('tbl_routers')->where('ip_address', $router_ip)->find_one();
            if (!$router) {
                continue;
            }

            // PERFORMANCE FIX: Only fetch from MikroTik if we have uncached MACs
            $client = Mikrotik::getClient($router_ip, $router->username, $router->password);

            foreach ($missing_macs as $mac => $_) {
                $router_result[$mac] = 'offline';
            }

            // 1) Active sessions - Check only for our specific MACs
            try {
                $req = new RouterOS\Request('/ip/hotspot/active/print');
                $responses = $client->sendSync($req);
                foreach ($responses as $row) {
                    $mac = strtoupper((string)$row->getProperty('mac-address'));
                    if (isset($missing_macs[$mac])) {
                        $router_result[$mac] = 'online';
                    }
                }
            } catch (Exception $e) { 
                error_log('TV_BINDING hotspot/active error: ' . $e->getMessage());
            }

            // 2) For MACs still offline, check ARP (reachable status)
            // PERFORMANCE FIX: Only query if we have unresolved MACs
            $unresolved_macs = array_filter($missing_macs, function($_, $mac) use ($router_result) {
                return $router_result[$mac] !== 'online';
            }, ARRAY_FILTER_USE_BOTH);

            if (!empty($unresolved_macs) && count($unresolved_macs) <= 50) {
                try {
                    $req = new RouterOS\Request('/ip/arp/print');
                    $req->setQuery(RouterOS\Query::where('status', 'reachable'));
                    $responses = $client->sendSync($req);
                    foreach ($responses as $row) {
                        $mac = strtoupper((string)$row->getProperty('mac-address'));
                        if (isset($missing_macs[$mac]) && $router_result[$mac] !== 'online') {
                            $router_result[$mac] = 'online';
                        }
                    }
                } catch (Exception $e) {
                    error_log('TV_BINDING arp error: ' . $e->getMessage());
                }
            }

            // 3) DHCP lease status - check only bound leases
            // PERFORMANCE FIX: Only query DHCP if still have offline MACs and reasonable count
            $still_offline = array_filter($missing_macs, function($_, $mac) use ($router_result) {
                return $router_result[$mac] !== 'online';
            }, ARRAY_FILTER_USE_BOTH);

            if (!empty($still_offline) && count($still_offline) <= 50) {
                try {
                    $req = new RouterOS\Request('/ip/dhcp-server/lease/print');
                    $req->setQuery(RouterOS\Query::where('status', 'bound'));
                    $responses = $client->sendSync($req);
                    foreach ($responses as $row) {
                        $mac = strtoupper((string)$row->getProperty('mac-address'));
                        if (isset($missing_macs[$mac]) && $router_result[$mac] !== 'online') {
                            $router_result[$mac] = 'online';
                        }
                    }
                } catch (Exception $e) {
                    error_log('TV_BINDING dhcp error: ' . $e->getMessage());
                }
            }


            if (!empty($router_result)) {
                @file_put_contents($cache_file, json_encode($router_result));
            }

            foreach ($bindings as $binding) {
                if (($binding['router_ip'] ?? '') !== $router_ip) {
                    continue;
                }
                $src_mac = strtoupper((string)$binding['mac_address']);
                if (isset($router_result[$src_mac])) {
                    $status_map[$binding['mac_address']] = $router_result[$src_mac];
                }
            }
        } catch (Exception $e) {
            error_log('TV_BINDING getTVOnlineStatusMap error for ' . $router_ip . ': ' . $e->getMessage());
        }
    }

    return $status_map;
}

/**
 * Resolve whether each listed MAC still has an active hotspot bypass rule.
 */
function getTVBypassStatusMap($bindings, $cache_ttl_seconds = 20)
{
    global $CACHE_PATH;

    $map = [];
    if (empty($bindings)) {
        return $map;
    }

    $router_mac_groups = [];
    foreach ($bindings as $binding) {
        $mac = strtoupper((string)($binding['mac_address'] ?? ''));
        if ($mac === '') {
            continue;
        }
        $map[$binding['mac_address']] = false;
        $router_ip = (string)($binding['router_ip'] ?? '');
        if ($router_ip === '') {
            continue;
        }
        if (!isset($router_mac_groups[$router_ip])) {
            $router_mac_groups[$router_ip] = [];
        }
        $router_mac_groups[$router_ip][$mac] = true;
    }

    foreach ($router_mac_groups as $router_ip => $mac_set) {
        try {
            $cache_file = rtrim($CACHE_PATH, '/\\') . '/tv_binding_bypass_' . md5($router_ip) . '.json';
            $cached_router_result = [];
            if (is_file($cache_file) && (time() - filemtime($cache_file)) <= $cache_ttl_seconds) {
                $raw_cache = @file_get_contents($cache_file);
                if ($raw_cache !== false) {
                    $decoded_cache = json_decode($raw_cache, true);
                    if (is_array($decoded_cache)) {
                        $cached_router_result = $decoded_cache;
                    }
                }
            }

            $router_result = [];
            $missing_macs = [];
            foreach ($mac_set as $mac => $_) {
                if (isset($cached_router_result[$mac])) {
                    $router_result[$mac] = (bool)$cached_router_result[$mac];
                } else {
                    $router_result[$mac] = false;
                    $missing_macs[$mac] = true;
                }
            }

            if (!empty($missing_macs)) {
                $router = ORM::for_table('tbl_routers')->where('ip_address', $router_ip)->find_one();
                if ($router) {
                    // PERFORMANCE FIX: Only connect to MikroTik if we have uncached MACs
                    $client = Mikrotik::getClient($router_ip, $router->username, $router->password);
                    try {
                        $req = new RouterOS\Request('/ip/hotspot/ip-binding/print');
                        // PERFORMANCE FIX: Filter for bypassed type only
                        $req->setQuery(RouterOS\Query::where('type', 'bypassed'));
                        $responses = $client->sendSync($req);
                        foreach ($responses as $row) {
                            $mac = strtoupper((string)$row->getProperty('mac-address'));
                            if (!isset($missing_macs[$mac])) {
                                continue;
                            }
                            $disabled = strtolower((string)$row->getProperty('disabled'));
                            if ($disabled !== 'true' && $disabled !== 'yes') {
                                $router_result[$mac] = true;
                            }
                        }
                    } catch (Exception $e) {
                        error_log('TV_BINDING ip-binding error: ' . $e->getMessage());
                    }
                }
                @file_put_contents($cache_file, json_encode($router_result));
            }

            foreach ($bindings as $binding) {
                if (($binding['router_ip'] ?? '') !== $router_ip) {
                    continue;
                }
                $src_mac = strtoupper((string)$binding['mac_address']);
                if (isset($router_result[$src_mac])) {
                    $map[$binding['mac_address']] = (bool)$router_result[$src_mac];
                }
            }
        } catch (Exception $e) {
            error_log('TV_BINDING getTVBypassStatusMap error for ' . $router_ip . ': ' . $e->getMessage());
        }
    }

    return $map;
}

/**
 * Route add binding based on binding_type: 'voucher' or 'plan'
 */
function handleAddBinding()
{
    $binding_type = trim($_POST['binding_type'] ?? 'plan');
    if ($binding_type === 'voucher') {
        handleVoucherBinding();
    } else {
        handlePlanStkBinding();
    }
}

/**
 * Bind TV device using a voucher code — immediate, no payment
 */
function handleVoucherBinding()
{
    error_reporting(E_ERROR | E_PARSE);

    $mac_address  = trim($_POST['mac_address'] ?? '');
    $router_id    = (int)($_POST['router_id'] ?? 0);
    $voucher_code = trim($_POST['voucher_code'] ?? '');

    if (!validateMacAddress($mac_address)) { echo json_encode(['success' => false, 'message' => 'Invalid MAC address format']); exit; }
    if (!$router_id)                        { echo json_encode(['success' => false, 'message' => 'Please select a router']); exit; }
    if (empty($voucher_code))               { echo json_encode(['success' => false, 'message' => 'Please enter a voucher code']); exit; }

    $mac_address  = formatMacAddress($mac_address);
    $voucher_code = preg_replace('/\s+/', '', $voucher_code);

    $existing = ORM::for_table('tbl_tv_bindings')->where('mac_address', $mac_address)
        ->where_raw("expiry_date > NOW() AND status = 'active'")->find_one();
    if ($existing) { echo json_encode(['success' => false, 'message' => 'This MAC address already has an active binding']); exit; }

    $voucher = ORM::for_table('tbl_voucher')->where_raw('BINARY code = ?', [$voucher_code])->where('status', 0)->find_one();
    if (!$voucher) { echo json_encode(['success' => false, 'message' => 'Voucher code is invalid or already used']); exit; }

    $plan = ORM::for_table('tbl_plans')->find_one($voucher['id_plan']);
    if (!$plan) { echo json_encode(['success' => false, 'message' => 'Plan linked to voucher not found']); exit; }

    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if (!$router) { echo json_encode(['success' => false, 'message' => 'Router not found']); exit; }

    if ($voucher['routers'] !== $router->name) {
        echo json_encode(['success' => false, 'message' => 'Voucher is not valid for router "' . $router->name . '"']); exit;
    }

    $validity_unit = strtolower($plan->validity_unit ?? 'day');
    $expiry_date   = date('Y-m-d H:i:s', strtotime('+' . $plan->validity . ' ' . $validity_unit));

    $binding = ORM::for_table('tbl_tv_bindings')->create();
    $binding->mac_address  = $mac_address;
    $binding->plan_id      = $plan->id;
    $binding->router_id    = $router_id;
    $binding->phone_number = '';
    $binding->binding_date = date('Y-m-d H:i:s');
    $binding->expiry_date  = $expiry_date;
    $binding->status       = 'active';
    $binding->binding_type = 'voucher';
    $binding->voucher_code = $voucher_code;
    $binding->notes        = 'Bound via voucher ' . $voucher_code;

    if ($binding->save()) {
        $voucher->status = 1;
        $voucher->user   = 'TV_' . str_replace(':', '-', $mac_address);
        $voucher->save();
        $ok = applyTVBindingToMikrotik($mac_address, $router, $plan);
        echo json_encode(['success' => true, 'message' => 'TV device bound via voucher ' . $voucher_code . ($ok ? '' : ' (Warning: Mikrotik push failed — check router connection)')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save binding record']);
    }
    exit;
}

/**
 * Bind TV device via plan — sends M-Pesa STK push using the configured payment gateway
 * (same flow as CreateHotspotUser.php / InitiateStkpush)
 */
function handlePlanStkBinding()
{
    // Suppress PHP notices so they don't corrupt JSON output
    error_reporting(E_ERROR | E_PARSE);

    try {

    $mac_address  = trim($_POST['mac_address'] ?? '');
    $router_id    = (int)($_POST['router_id'] ?? 0);
    $plan_id      = (int)($_POST['plan_id'] ?? 0);
    $phone_number = trim($_POST['phone_number'] ?? '');

    if (!validateMacAddress($mac_address)) { echo json_encode(['success' => false, 'message' => 'Invalid MAC address format']); exit; }
    if (!$router_id || !$plan_id)          { echo json_encode(['success' => false, 'message' => 'Please select router and plan']); exit; }
    if (empty($phone_number))              { echo json_encode(['success' => false, 'message' => 'Phone number is required for STK payment']); exit; }

    $mac_address = formatMacAddress($mac_address);

    $existing = ORM::for_table('tbl_tv_bindings')->where('mac_address', $mac_address)
        ->where_raw("expiry_date > NOW() AND status = 'active'")->find_one();
    if ($existing) { echo json_encode(['success' => false, 'message' => 'This MAC address already has an active binding']); exit; }

    // Delete any stale pending binding for this MAC so UNIQUE constraint doesn't block re-try
    $stale_pending = ORM::for_table('tbl_tv_bindings')
        ->where('mac_address', $mac_address)->where('status', 'pending')->find_many();
    foreach ($stale_pending as $sp) { $sp->delete(); }

    $plan   = ORM::for_table('tbl_plans')->find_one($plan_id);
    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if (!$plan)   { echo json_encode(['success' => false, 'message' => 'Plan not found']); exit; }
    if (!$router) { echo json_encode(['success' => false, 'message' => 'Router not found']); exit; }

    // Determine configured payment gateway (same as InitiateStkpush)
    $gateway_cfg = ORM::for_table('tbl_appconfig')->where('setting', 'payment_gateway')->find_one();
    $gateway = ($gateway_cfg) ? $gateway_cfg->value : null;

    $gateway_urls = [
        'MpesatillStk'          => U . 'plugin/initiatetillstk',
        'BankStkPush'           => U . 'plugin/initiatebankstk',
        'MpesaPaybill'          => U . 'plugin/initiatePaybillStk',
        'mpesa'                 => U . 'plugin/initiatempesa',
        'campay'                => U . 'plugin/initiatecampay',
        'paybilltillsbankmpesa' => U . 'plugin/initiatepaybilltillsbankmpesa',
        'kopokopo'              => U . 'plugin/initiatekopokopo',
    ];

    if (!$gateway || !isset($gateway_urls[$gateway])) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured. Please configure it in App Settings.']);
        exit;
    }
    $url = $gateway_urls[$gateway];

    // Format phone to 254XXXXXXXXX (matching CreateHotspotUser.php)
    $phone = ltrim($phone_number, '+');
    if (substr($phone, 0, 1) === '0') $phone = '254' . substr($phone, 1);
    elseif (substr($phone, 0, 1) === '7') $phone = '2547' . substr($phone, 1);
    elseif (substr($phone, 0, 1) === '1') $phone = '2541' . substr($phone, 1);

    // Generate a unique account_number for this TV payment — stored in checkout_request_id
    $account_number = 'TV' . substr(str_replace(':', '', $mac_address), -8) . (time() % 100000);

    $validity_unit = strtolower($plan->validity_unit ?? 'day');
    $expiry_date   = date('Y-m-d H:i:s', strtotime('+' . $plan->validity . ' ' . $validity_unit));

    // Create pending binding
    $binding = ORM::for_table('tbl_tv_bindings')->create();
    $binding->mac_address         = $mac_address;
    $binding->plan_id             = $plan_id;
    $binding->router_id           = $router_id;
    $binding->phone_number        = $phone_number;
    $binding->binding_date        = date('Y-m-d H:i:s');
    $binding->expiry_date         = $expiry_date;
    $binding->status              = 'pending';
    $binding->binding_type        = 'plan';
    $binding->checkout_request_id = $account_number;  // reuse field to store account_number
    $binding->notes               = 'Pending STK payment from ' . $phone_number;

    if (!$binding->save()) { echo json_encode(['success' => false, 'message' => 'Failed to create pending binding']); exit; }

    // Clear old pending gateway records for this account
    $old = ORM::for_table('tbl_payment_gateway')->where('username', $account_number)->where('status', 1)->find_many();
    foreach ($old as $o) { $o->delete(); }

    // routers_id and mac_address columns confirmed present in schema

    // Create tbl_payment_gateway record (status=1 = pending STK)
    $d = ORM::for_table('tbl_payment_gateway')->create();
    $d->username        = $account_number;
    $d->gateway         = $gateway;
    $d->mac_address     = $mac_address;
    $d->plan_id         = $plan_id;
    $d->plan_name       = $plan->name_plan;
    $d->routers_id      = $router_id;
    $d->routers         = $router->name;
    $d->price           = $plan->price;
    $d->payment_method  = $gateway;
    $d->payment_channel = $gateway;
    $d->created_date    = date('Y-m-d H:i:s');
    $d->paid_date       = date('Y-m-d H:i:s');
    $d->expired_date    = date('Y-m-d H:i:s');
    $d->pg_url_payment  = $url;
    $d->status          = 1;
    $d->save();

    // Create / upsert a tbl_customers record for this account_number so gateway
    // plugins (initiatempesa.php etc.) can look it up — same pattern as CreateHotspotUser.php
    $tvCustomer = ORM::for_table('tbl_customers')
        ->where('username', $account_number)
        ->where('service_type', 'Hotspot')
        ->find_one();
    if ($tvCustomer) {
        $tvCustomer->phonenumber = $phone_number;
        $tvCustomer->router_id   = $router_id;
        $tvCustomer->save();
    } else {
        $tvCustomer = ORM::for_table('tbl_customers')->create();
        $tvCustomer->username     = $account_number;
        $tvCustomer->password     = '1234';
        $tvCustomer->fullname     = 'TV-' . $mac_address;
        $tvCustomer->phonenumber  = $phone_number;
        $tvCustomer->router_id    = $router_id;
        $tvCustomer->address      = 'TV Binding';
        $tvCustomer->email        = $account_number . '@tv.local';
        $tvCustomer->service_type = 'Hotspot';
        $tvCustomer->pppoe_password = '1234';
        $tvCustomer->save();
    }

    // Trigger STK push via the configured gateway URL
    $stk_result = tv_binding_call_gateway($phone, $account_number, $url);

    if (isset($stk_result['status']) && $stk_result['status'] === 'error') {
        $binding->delete();
        $d->delete();
        echo json_encode(['success' => false, 'message' => 'STK push failed: ' . ($stk_result['message'] ?? 'Gateway error')]);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'pending'    => true,
        'binding_id' => $binding->id,
        'message'    => 'STK push sent to ' . $phone_number . '. Ask customer to enter M-Pesa PIN, then click "Check Payment" to activate.'
    ]);
    exit;

    } catch (Exception $e) {
        error_log('TV_BINDING handlePlanStkBinding error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Call payment gateway URL to trigger STK push — returns decoded JSON array
 * (mirrors SendSTKcred from CreateHotspotUser.php but doesn't exit)
 */
function tv_binding_call_gateway($phone, $account_number, $url)
{
    $fields = json_encode(['username' => $account_number, 'phone' => $phone, 'channel' => 'Yes']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($fields)],
    ]);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($result === false) return ['status' => 'error', 'message' => $err ?: 'cURL connection failed'];
    $decoded = json_decode($result, true);
    return $decoded ?: ['status' => 'ok'];
}

/**
 * Check payment status for a pending binding via tbl_payment_gateway
 * (mirrors VerifyHotspot() from CreateHotspotUser.php)
 */
function handleCheckPayment()
{
    error_reporting(E_ERROR | E_PARSE);

    $binding_id = (int)($_POST['binding_id'] ?? 0);
    if (!$binding_id) { echo json_encode(['success' => false, 'message' => 'Invalid binding ID']); exit; }

    $binding = ORM::for_table('tbl_tv_bindings')->find_one($binding_id);
    if (!$binding) { echo json_encode(['success' => false, 'message' => 'Binding not found']); exit; }

    if ($binding->status === 'active') {
        echo json_encode(['success' => true, 'already_active' => true, 'message' => 'Binding is already active']); exit;
    }

    // checkout_request_id now stores the account_number used in tbl_payment_gateway
    $account_number = $binding->checkout_request_id ?? '';
    if (empty($account_number)) { echo json_encode(['success' => false, 'message' => 'No payment record found for this binding']); exit; }

    // Look up payment status — same logic as VerifyHotspot()
    $payment = ORM::for_table('tbl_payment_gateway')
        ->where('username', $account_number)
        ->order_by_desc('id')
        ->find_one();

    if (!$payment) {
        echo json_encode(['success' => false, 'status' => 'pending', 'message' => 'Payment record not found. The STK push may not have been processed yet.']);
        exit;
    }

    $status      = (int)$payment->status;
    $pg_response = $payment->pg_paid_response ?? '';

    if ($status === 2) {
        // Paid — activate binding and push to Mikrotik
        $plan   = ORM::for_table('tbl_plans')->find_one($binding->plan_id);
        $router = ORM::for_table('tbl_routers')->find_one($binding->router_id);
        $binding->status = 'active';
        $binding->notes  = 'Activated via STK from ' . $binding->phone_number;
        $binding->save();
        $ok = applyTVBindingToMikrotik($binding->mac_address, $router, $plan);
        echo json_encode(['success' => true, 'message' => 'Payment confirmed! TV device is now active.' . ($ok ? '' : ' (Warning: Mikrotik push failed — check router connection)')]);
    } elseif ($status === 4 || strpos($pg_response, 'Cancelled') !== false) {
        echo json_encode(['success' => false, 'status' => 'cancelled', 'message' => 'Customer cancelled the M-Pesa prompt']);
    } elseif ($pg_response === 'Not enough balance') {
        echo json_encode(['success' => false, 'status' => 'failed', 'message' => 'Insufficient M-Pesa balance for this transaction']);
    } elseif ($pg_response === 'Wrong Mpesa pin') {
        echo json_encode(['success' => false, 'status' => 'failed', 'message' => 'Wrong M-Pesa PIN entered. Please try again.']);
    } else {
        echo json_encode(['success' => false, 'status' => 'pending', 'message' => 'Still pending — ask customer to complete the M-Pesa payment and try again']);
    }
    exit;
}

/**
 * [OLD STUB - kept for reference, logic now in handleVoucherBinding / handlePlanStkBinding]
 */
function handleAddBinding_OLD()
{
    $response = ['success' => false, 'message' => ''];

    try {
        $mac_address = isset($_POST['mac_address']) ? trim($_POST['mac_address']) : '';
        $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
        $router_id = isset($_POST['router_id']) ? (int)$_POST['router_id'] : 0;
        $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';

        // Validate MAC address format
        if (!validateMacAddress($mac_address)) {
            $response['message'] = 'Invalid MAC address format';
            echo json_encode($response);
            exit;
        }

        // Format MAC address
        $mac_address = formatMacAddress($mac_address);

        // Check if MAC already exists
        $existing = ORM::for_table('tbl_tv_bindings')
            ->where('mac_address', $mac_address)
            ->where_raw("(expiry_date > NOW() OR status = 'active')")
            ->find_one();

        if ($existing) {
            $response['message'] = 'This MAC address is already bound to an active plan';
            echo json_encode($response);
            exit;
        }

        // Validate plan exists
        $plan = ORM::for_table('tbl_plans')->where('id', $plan_id)->find_one();
        if (!$plan) {
            $response['message'] = 'Selected plan does not exist';
            echo json_encode($response);
            exit;
        }

        // Validate router exists
        $router = ORM::for_table('tbl_routers')->where('id', $router_id)->find_one();
        if (!$router) {
            $response['message'] = 'Selected router does not exist';
            echo json_encode($response);
            exit;
        }

        // Calculate expiry date based on plan validity
        $validity_unit = strtolower($plan->validity_unit ?? 'day');
        $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $plan->validity . ' ' . $validity_unit));

        // Create binding record
        $binding = ORM::for_table('tbl_tv_bindings')->create();
        $binding->mac_address = $mac_address;
        $binding->plan_id = $plan_id;
        $binding->router_id = $router_id;
        $binding->phone_number = $phone_number;
        $binding->binding_date = date('Y-m-d H:i:s');
        $binding->expiry_date = $expiry_date;
        $binding->status = 'active';
        $binding->notes = 'Manually bound by ' . (_user() ? _user()->username : 'admin');

        if ($binding->save()) {
            // Apply binding to Mikrotik
            $mikrotik_result = applyTVBindingToMikrotik($mac_address, $router, $plan);

            // Create payment record for tracking
            $payment = ORM::for_table('tbl_payment_gateway')->create();
            $payment->username = 'TV_' . str_replace(':', '-', $mac_address);
            $payment->gateway = 'manual';
            $payment->mac_address = $mac_address;
            $payment->plan_id = $plan_id;
            $payment->plan_name = $plan->name_plan;
            $payment->routers_id = $router_id;
            $payment->routers = $router->name;
            $payment->price = $plan->price;
            $payment->payment_method = 'manual_binding';
            $payment->payment_channel = 'admin';
            $payment->created_date = date('Y-m-d H:i:s');
            $payment->paid_date = date('Y-m-d H:i:s');
            $payment->expired_date = $expiry_date;
            $payment->status = 2; // Paid
            $payment->save();

            $response['success'] = true;
            $response['message'] = 'TV binding created successfully' . ($mikrotik_result ? '' : ' (Warning: Mikrotik binding may have failed)');
        } else {
            $response['message'] = 'Failed to create binding record';
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
        error_log('TV_BINDING handleAddBinding error: ' . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}



function applyTVBindingToMikrotik($mac_address, $router, $plan)
{
    try {
        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);

        // Remove any existing ip-binding entry for this MAC (avoid duplicates)
        try {
            $pr = new RouterOS\Request('/ip/hotspot/ip-binding/print');
            $pr->setQuery(RouterOS\Query::where('mac-address', $mac_address));
            foreach ($client->sendSync($pr) as $entry) {
                $dr = new RouterOS\Request('/ip/hotspot/ip-binding/remove');
                $client->sendSync($dr->setArgument('numbers', $entry->getProperty('.id')));
            }
        } catch (Exception $e) { /* ignore — entry may not exist */ }

        // Add ip-binding bypass — device gets internet without seeing login page
        $addReq = new RouterOS\Request('/ip/hotspot/ip-binding/add');
        $client->sendSync(
            $addReq
                ->setArgument('mac-address', $mac_address)
                ->setArgument('type', 'bypassed')
                ->setArgument('comment', 'TV-Binding: ' . $plan->name_plan)
        );

        // Kick any existing active session for this MAC so the bypass takes effect immediately
        try {
            $ap = new RouterOS\Request('/ip/hotspot/active/print');
            $ap->setQuery(RouterOS\Query::where('mac-address', $mac_address));
            foreach ($client->sendSync($ap) as $session) {
                $rm = new RouterOS\Request('/ip/hotspot/active/remove');
                $client->sendSync($rm->setArgument('numbers', $session->getProperty('.id')));
            }
        } catch (Exception $e) { /* ignore */ }

        return true;
    } catch (Exception $e) {
        error_log('TV_BINDING applyTVBindingToMikrotik error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Handle deleting TV binding
 */
function handleDeleteBinding()
{
    $response = ['success' => false, 'message' => ''];

    try {
        $binding_id = isset($_POST['binding_id']) ? (int)$_POST['binding_id'] : 0;

        if (!$binding_id) {
            $response['message'] = 'Invalid binding ID';
            echo json_encode($response);
            exit;
        }

        $binding = ORM::for_table('tbl_tv_bindings')->find_one($binding_id);
        if (!$binding) {
            $response['message'] = 'Binding not found';
            echo json_encode($response);
            exit;
        }

        $mac_address = $binding->mac_address;
        $router_id = $binding->router_id;

        // Get router details
        $router = ORM::for_table('tbl_routers')->find_one($router_id);

        // Remove MAC binding from Mikrotik
        removeTVBindingFromMikrotik($mac_address, $router);

        if ($binding->delete()) {
            $response['success'] = true;
            $response['message'] = 'TV binding deleted successfully';
        } else {
            $response['message'] = 'Failed to delete binding';
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
        error_log('TV_BINDING handleDeleteBinding error: ' . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}

/**
 * Remove TV binding from MikroTik — deletes the ip-binding bypass entry.
 */
function removeTVBindingFromMikrotik($mac_address, $router)
{
    try {
        if (!$router) return false;

        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);

        $pr = new RouterOS\Request('/ip/hotspot/ip-binding/print');
        $pr->setQuery(RouterOS\Query::where('mac-address', $mac_address));
        foreach ($client->sendSync($pr) as $entry) {
            $dr = new RouterOS\Request('/ip/hotspot/ip-binding/remove');
            $client->sendSync($dr->setArgument('numbers', $entry->getProperty('.id')));
        }
        return true;
    } catch (Exception $e) {
        error_log('TV_BINDING removeTVBindingFromMikrotik error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cron hook — runs on every system cron cycle.
 * Finds expired active bindings, removes the ip-binding bypass from MikroTik
 * so the device immediately loses internet access, then marks the binding expired.
 */
function tv_binding_expire_cron()
{
    try {
        $expired = ORM::for_table('tbl_tv_bindings')
            ->where('status', 'active')
            ->where_raw('expiry_date IS NOT NULL AND expiry_date < NOW()')
            ->find_many();

        // Group expired bindings by router to avoid reconnecting per row.
        $by_router = [];
        foreach ($expired as $binding) {
            $rid = (int)$binding->router_id;
            if (!isset($by_router[$rid])) {
                $by_router[$rid] = [];
            }
            $by_router[$rid][] = $binding;
        }

        foreach ($by_router as $router_id => $bindings) {
            $router = ORM::for_table('tbl_routers')->find_one($router_id);
            if ($router) {
                try {
                    $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);

                    foreach ($bindings as $binding) {
                        // Remove ip-binding bypass — device will see login page immediately
                        try {
                            $pr = new RouterOS\Request('/ip/hotspot/ip-binding/print');
                            $pr->setQuery(RouterOS\Query::where('mac-address', $binding->mac_address));
                            foreach ($client->sendSync($pr) as $entry) {
                                $dr = new RouterOS\Request('/ip/hotspot/ip-binding/remove');
                                $client->sendSync($dr->setArgument('numbers', $entry->getProperty('.id')));
                            }

                            // Kick the active session so disconnect is instant
                            $ap = new RouterOS\Request('/ip/hotspot/active/print');
                            $ap->setQuery(RouterOS\Query::where('mac-address', $binding->mac_address));
                            foreach ($client->sendSync($ap) as $session) {
                                $rm = new RouterOS\Request('/ip/hotspot/active/remove');
                                $client->sendSync($rm->setArgument('numbers', $session->getProperty('.id')));
                            }
                        } catch (Exception $e) {
                            error_log('TV_BINDING expire_cron MikroTik error for ' . $binding->mac_address . ': ' . $e->getMessage());
                        }
                    }
                } catch (Exception $e) {
                    error_log('TV_BINDING expire_cron router connect error for router #' . $router_id . ': ' . $e->getMessage());
                }
            }

            foreach ($bindings as $binding) {
                $binding->status = 'expired';
                $binding->save();
            }
        }
    } catch (Exception $e) {
        error_log('TV_BINDING expire_cron error: ' . $e->getMessage());
    }
}

/**
 * Validate MAC address format
 * Accepts: AA:BB:CC:DD:EE:FF or AABBCCDDEEFF or AA-BB-CC-DD-EE-FF
 */
function validateMacAddress($mac)
{
    // Remove common separators
    $clean_mac = preg_replace('/[:\-\.]/', '', $mac);
    
    // Check if valid hex and 12 characters
    return preg_match('/^[0-9a-fA-F]{12}$/', $clean_mac) ? true : false;
}

/**
 * Format MAC address to standard XX:XX:XX:XX:XX:XX format
 */
function formatMacAddress($mac)
{
    // Remove separators
    $clean = preg_replace('/[:\-\.\s]/', '', $mac);
    
    // Format as XX:XX:XX:XX:XX:XX
    return strtoupper(implode(':', str_split($clean, 2)));
}
