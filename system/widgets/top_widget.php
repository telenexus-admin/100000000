<?php
/**
 * top_widget.php - Dashboard widget with async loading for better performance
 * Page loads instantly, online users data loads via AJAX after page render
 */

class top_widget
{
    /**
     * Billing period start (uses reset_day from config).
     */
    public static function billingStartDate($resetDay = null)
    {
        global $config;

        $reset_day = (int) ($resetDay ?? ($config['reset_day'] ?? 1));
        if ($reset_day < 1) {
            $reset_day = 1;
        }
        if ($reset_day > 28) {
            $reset_day = 28;
        }

        $dayPadded = str_pad((string) $reset_day, 2, '0', STR_PAD_LEFT);
        if ((int) date('d') >= $reset_day) {
            return date('Y-m-' . $dayPadded);
        }

        return date('Y-m-' . $dayPadded, strtotime('-1 MONTH'));
    }

    /**
     * Base query for collected revenue (single source: tbl_transactions).
     */
    public static function revenueBaseQuery($fromDate, $toDate, $routerName = null)
    {
        $query = ORM::for_table('tbl_transactions')
            ->where_gte('recharged_on', $fromDate)
            ->where_lte('recharged_on', $toDate)
            ->where_raw("method NOT LIKE 'Voucher%'")
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator');

        if ($routerName !== null && $routerName !== '') {
            $query->where_raw('LOWER(TRIM(routers)) = LOWER(?)', [trim($routerName)]);
        }

        return $query;
    }

    /**
     * Collected revenue from tbl_transactions (excludes vouchers and balance transfers).
     */
    public static function sumCollectedRevenue($fromDate, $toDate, $routerName = null)
    {
        return (float) (self::revenueBaseQuery($fromDate, $toDate, $routerName)->sum('price') ?: 0);
    }

    /**
     * Transaction count using the same filters as sumCollectedRevenue().
     */
    public static function countCollectedTransactions($fromDate, $toDate, $routerName = null)
    {
        return (int) self::revenueBaseQuery($fromDate, $toDate, $routerName)->count();
    }

    /**
     * Collected revenue query with optional service type filter (Hotspot, PPPOE, etc.).
     */
    public static function periodCollectedQuery($fromDate, $toDate, $serviceType = '', $routerName = null)
    {
        $query = self::revenueBaseQuery($fromDate, $toDate, $routerName);
        if ($serviceType !== '') {
            $query->where('type', $serviceType);
        }

        return $query;
    }

    /**
     * Count distinct expired PPPoE clients (unique usernames with no active PPPoE).
     */
    public static function countExpiredPppoe($routerName = null)
    {
        $params = [];
        $routerExpired = '';
        $routerActive = '';
        if ($routerName !== null && $routerName !== '') {
            $routerExpired = ' AND LOWER(TRIM(e.routers)) = LOWER(?)';
            $routerActive = ' AND LOWER(TRIM(a.routers)) = LOWER(?)';
            $params[] = trim($routerName);
            $params[] = trim($routerName);
        }

        $sql = "SELECT COUNT(DISTINCT e.username) AS cnt
                FROM tbl_user_recharges e
                LEFT JOIN tbl_user_recharges a
                    ON a.username = e.username
                   AND a.status = 'on'
                   AND UPPER(TRIM(a.type)) = 'PPPOE'
                   {$routerActive}
                WHERE e.status = 'off'
                  AND UPPER(TRIM(e.type)) = 'PPPOE'
                  {$routerExpired}
                  AND a.id IS NULL";

        $row = ORM::for_table('tbl_user_recharges')
            ->raw_query($sql, $params)
            ->find_one();

        if (!$row) {
            return 0;
        }

        return (int) (isset($row['cnt']) ? $row['cnt'] : (isset($row->cnt) ? $row->cnt : 0));
    }

    /**
     * Main widget display method - loads instantly without router queries
     * @return string Rendered HTML
     */
    public function getWidget()
    {
        global $ui, $current_date, $start_date, $config;

        $iday = self::sumCollectedRevenue($current_date, $current_date);
        $ui->assign('iday', number_format($iday, 2, '.', ''));

        $imonth = self::sumCollectedRevenue($start_date, $current_date);
        $ui->assign('imonth', number_format($imonth, 2, '.', ''));

        // Set default/placeholder values (will be updated by AJAX)
        $ui->assign('u_act', '--');           // Total online users - placeholder
        $u_all = ORM::for_table('tbl_user_recharges')->where('status', 'on')->count();
        $ui->assign('u_all', $u_all);
        $ui->assign('active_accounts', $u_all);
        $ui->assign('expired_pppoe', self::countExpiredPppoe());
        $ui->assign('hotspot_online', '--');   // Hotspot placeholder
        $ui->assign('pppoe_online', '--');     // PPPoE placeholder
        $ui->assign('active_but_not_online', []);
        
        // Status information
        $ui->assign('online_source', 'Loading...');
        $ui->assign('online_status', 'loading');
        $ui->assign('last_update', time());
        
        // Get total customers count (fast DB query)
        $c_all = ORM::for_table('tbl_customers')->count();
        if (empty($c_all)) {
            $c_all = '0';
        }
        $ui->assign('c_all', $c_all);
        
        // Get routers list for filter dropdown (fast DB query)
        $routers = ORM::for_table('tbl_routers')
            ->where('enabled', 1)
            ->order_by_asc('name')
            ->find_many();
        $ui->assign('routers', $routers);
        
        // Get online routers count (fast DB query from status field)
        $online_routers = ORM::for_table('tbl_routers')
            ->where('status', 'Online')
            ->where('enabled', 1)
            ->count();
        $ui->assign('online_routers', $online_routers);
        
        // Get current timestamp
        $ui->assign('now', time());
        
        return $ui->fetch('widget/top_widget.tpl');
    }

    /**
     * Get online users directly from MikroTik routers (called via AJAX only)
     * This is the heavy function that queries routers
     * 
     * @param int|null $router_id Filter by specific router
     * @return array Statistics array
     */
    public function getOnlineUsersFromRouters($router_id = null)
    {
        try {
            // Get routers based on filter
            $routers = [];
            $selected_router = null;
            
            if ($router_id && $router_id !== 'all' && $router_id !== '') {
                $selected_router = ORM::for_table('tbl_routers')
                    ->where('enabled', '1')
                    ->where('id', $router_id)
                    ->find_one();
                
                if (!$selected_router) {
                    return $this->getFallbackData('Router not found');
                }
                $routers = [$selected_router];
            } else {
                $routers = ORM::for_table('tbl_routers')
                    ->where('enabled', '1')
                    ->order_by_asc('name')
                    ->find_many();
            }
            
            if (count($routers) === 0) {
                return $this->getFallbackData('No routers configured');
            }
            
            $total_hotspot = 0;
            $total_pppoe = 0;
            $successful_routers = 0;
            $router_errors = [];
            
            // Fetch from each router
            foreach ($routers as $router) {
                try {
                    // Connect to MikroTik with short timeout
                    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
                    
                    // Test connection
                    $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print'));
                    
                    // Get Hotspot active users count
                    try {
                        $hotspotRequest = new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print');
                        $hotspotActive = $client->sendSync($hotspotRequest);
                        foreach ($hotspotActive as $hotspot) {
                            $user = trim($hotspot->getProperty('user'));
                            if (!empty($user)) {
                                $total_hotspot++;
                            }
                        }
                    } catch (Exception $e) {
                        // Hotspot might not be configured
                    }
                    
                    // Get PPPoE active users count
                    try {
                        $pppRequest = new PEAR2\Net\RouterOS\Request('/ppp/active/print');
                        $pppActive = $client->sendSync($pppRequest);
                        foreach ($pppActive as $ppp) {
                            $user = trim($ppp->getProperty('name'));
                            if (!empty($user)) {
                                $total_pppoe++;
                            }
                        }
                    } catch (Exception $e) {
                        // PPPoE might not be configured
                    }
                    
                    $successful_routers++;
                    
                    // Disconnect
                    if (method_exists($client, 'disconnect')) {
                        $client->disconnect();
                    }
                    
                } catch (Exception $e) {
                    $router_errors[] = $router['name'] . ': ' . $e->getMessage();
                    error_log("Error fetching from router {$router['name']}: " . $e->getMessage());
                    continue;
                }
            }
            
            $total_online = $total_hotspot + $total_pppoe;
            
            // Get total active accounts from database
            $total_active_accounts = ORM::for_table('tbl_user_recharges')
                ->where('status', 'on')
                ->count();
            
            $expired_pppoe = self::countExpiredPppoe();

            // If filtering by a specific router, get active accounts for that router only
            if ($selected_router) {
                $router_active = ORM::for_table('tbl_user_recharges')
                    ->where('status', 'on')
                    ->where('routers', $selected_router['name'])
                    ->count();
                if ($router_active > 0) {
                    $total_active_accounts = $router_active;
                }
                $expired_pppoe = self::countExpiredPppoe($selected_router['name']);
            }

            return [
                'online' => $total_online,
                'hotspot_online' => $total_hotspot,
                'pppoe_online' => $total_pppoe,
                'total_active_accounts' => $total_active_accounts,
                'expired_pppoe' => $expired_pppoe,
                'success' => true,
                'status' => $successful_routers > 0 ? 'live' : 'error',
                'source' => $successful_routers . '/' . count($routers) . ' routers connected',
                'router_errors' => $router_errors,
                'routers_successful' => $successful_routers,
                'routers_total' => count($routers),
                'selected_router_status' => $selected_router ? $selected_router['status'] : null,
                'selected_router_name' => $selected_router ? $selected_router['name'] : null
            ];
            
        } catch (Exception $e) {
            error_log("Error in getOnlineUsersFromRouters: " . $e->getMessage());
            return $this->getFallbackData($e->getMessage());
        }
    }
    
    /**
     * Return last-known online counts from the local usage monitor cache.
     * Dashboard reads must not fan out to every RouterOS API connection.
     */
    public function getOnlineUsersFromCache($router_id = null)
    {
        $selected_router = null;
        $query = ORM::for_table('tbl_usage_sessions')
            ->select_expr("SUM(CASE WHEN LOWER(interface) = 'hotspot' THEN 1 ELSE 0 END)", 'hotspot_online')
            ->select_expr("SUM(CASE WHEN LOWER(interface) IN ('pppoe', 'ppp') THEN 1 ELSE 0 END)", 'pppoe_online')
            ->where_gte('last_seen', date('Y-m-d H:i:s', time() - 600));

        if ($router_id && $router_id !== 'all' && $router_id !== '') {
            $selected_router = ORM::for_table('tbl_routers')
                ->where('enabled', '1')
                ->where('id', (int) $router_id)
                ->find_one();
            if (!$selected_router) {
                return $this->getFallbackData('Router not found');
            }
            $query->where('router_id', (int) $selected_router['id']);
        }

        $counts = $query->find_one();
        $hotspot = $counts ? (int) $counts->hotspot_online : 0;
        $pppoe = $counts ? (int) $counts->pppoe_online : 0;

        $activeAccounts = ORM::for_table('tbl_user_recharges')->where('status', 'on');
        if ($selected_router) {
            $activeAccounts->where('routers', $selected_router['name']);
        }

        return [
            'online' => $hotspot + $pppoe,
            'hotspot_online' => $hotspot,
            'pppoe_online' => $pppoe,
            'total_active_accounts' => (int) $activeAccounts->count(),
            'expired_pppoe' => self::countExpiredPppoe($selected_router ? $selected_router['name'] : null),
            'success' => true,
            'status' => 'cached',
            'source' => 'Last-known usage cache (up to 10 minutes old)',
            'router_errors' => [],
            'selected_router_status' => $selected_router ? $selected_router['status'] : null,
            'selected_router_name' => $selected_router ? $selected_router['name'] : null
        ];
    }

    /**
     * Fallback data when routers are unreachable
     */
    private function getFallbackData($error_msg = '')
    {
        $total_active_accounts = ORM::for_table('tbl_user_recharges')
            ->where('status', 'on')
            ->count();
            
        return [
            'online' => 0,
            'hotspot_online' => 0,
            'pppoe_online' => 0,
            'total_active_accounts' => $total_active_accounts,
            'expired_pppoe' => self::countExpiredPppoe(),
            'success' => false,
            'status' => 'error',
            'source' => 'Unable to connect to routers' . ($error_msg ? ': ' . $error_msg : ''),
            'router_errors' => []
        ];
    }
    
    /**
     * Get filtered data for a specific router (AJAX endpoint for sales data)
     * 
     * @param int $router_id Router ID to filter by
     * @return array Filtered statistics including sales
     */
    public static function ajaxGetFilteredData($router_id)
    {
        global $current_date, $start_date;

        $response = [];

        try {
            global $config;

            $current_date = date('Y-m-d');
            $start_date = self::billingStartDate($config['reset_day'] ?? 1);

            $router_id = is_string($router_id) ? trim($router_id) : $router_id;

            $router_name = null;
            $router = null;

            if ($router_id !== null && $router_id !== '' && strcasecmp((string) $router_id, 'all') !== 0) {
                $router = ORM::for_table('tbl_routers')->find_one($router_id);
                if ($router) {
                    $router_name = $router->name;
                } else {
                    $response['success'] = false;
                    $response['error'] = 'Router not found';
                    return $response;
                }
            }

            $today_sales = self::sumCollectedRevenue($current_date, $current_date, $router_name);
            $monthly_sales = self::sumCollectedRevenue($start_date, $current_date, $router_name);

            $response['today_sales'] = $today_sales;
            $response['monthly_sales'] = $monthly_sales;

            // Get active accounts count
            if ($router_name !== null) {
                $active_accounts = (int) ORM::for_table('tbl_user_recharges')
                    ->where('status', 'on')
                    ->where('routers', $router_name)
                    ->count();
                if ($active_accounts === 0) {
                    $active_accounts = (int) ORM::for_table('tbl_user_recharges')
                        ->where('status', 'on')
                        ->where_raw('LOWER(TRIM(routers)) = LOWER(?)', [$router_name])
                        ->count();
                }
                $response['active_accounts'] = $active_accounts;
            } else {
                $response['active_accounts'] = (int) ORM::for_table('tbl_user_recharges')
                    ->where('status', 'on')
                    ->count();
            }
            
            // Get router counts
            $response['online_routers'] = (int) ORM::for_table('tbl_routers')
                ->where('status', 'Online')
                ->where('enabled', 1)
                ->count();
            
            $response['total_routers'] = (int) ORM::for_table('tbl_routers')
                ->where('enabled', 1)
                ->count();
            
            $response['total_users'] = (int) ORM::for_table('tbl_customers')->count();
            $response['expired_pppoe'] = self::countExpiredPppoe($router_name);
            $response['selected_router_status'] = ($router) ? $router->status : null;
            $response['selected_router_name'] = ($router) ? $router->name : null;
            $response['success'] = true;
            
        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
        }
        
        return $response;
    }
    
    /**
     * AJAX endpoint to get online users data
     * This is called by the dashboard JavaScript after page loads
     */
    public static function ajaxGetOnlineUsers()
    {
        header('Content-Type: application/json');
        
        $router_id = isset($_GET['router_id']) ? $_GET['router_id'] : null;
        
        $widget = new self();
        $data = $widget->getOnlineUsersFromCache($router_id);
        
        // Get router counts
        $online_routers = ORM::for_table('tbl_routers')
            ->where('status', 'Online')
            ->where('enabled', 1)
            ->count();
        
        $total_routers = ORM::for_table('tbl_routers')
            ->where('enabled', 1)
            ->count();
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'online_routers' => $online_routers,
            'total_routers' => $total_routers
        ]);
        exit;
    }
}

// =========================================
// AJAX REQUEST HANDLERS
// =========================================

// Handle AJAX request for sales/filtered data
if (isset($_GET['_route']) && $_GET['_route'] == 'dashboard' && isset($_GET['router_id']) && !isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $router_id = $_GET['router_id'];
        $result = top_widget::ajaxGetFilteredData($router_id);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX request for online users data
if (isset($_GET['_route']) && $_GET['_route'] == 'dashboard' && isset($_GET['ajax']) && $_GET['ajax'] == 'online_users') {
    top_widget::ajaxGetOnlineUsers();
    exit;
}
?>
