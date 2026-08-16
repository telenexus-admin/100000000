<?php

use PEAR2\Net\RouterOS\Request;

/**
 * Dashboard summary JSON (no full page).
 */
$action = $routes['1'] ?? '';

// Handle API endpoints first (before any HTML output)
if (in_array($action, ['summary', 'hotspot_data', 'pppoe_data', 'disconnect'], true)) {
    // Set JSON header immediately
    header('Content-Type: application/json; charset=utf-8');
    
    if ($action === 'summary') {
        if (!_admin(false)) {
            http_response_code(401);
            echo json_encode([
                'network_status' => 'error',
                'status_message' => 'Unauthorized',
                'day_sales' => '0',
                'month_sales' => '0',
                'total_online' => 0,
                'hotspot_users' => 0,
                'pppoe_users' => 0,
                'active_accounts' => '0/0',
                'online_routers' => '0',
                'total_customers' => '0',
            ]);
            exit;
        }

        require_once $WIDGET_PATH . DIRECTORY_SEPARATOR . 'top_widget.php';

        $router = _get('router', 'all');
        try {
            $payload = top_widget::buildDashboardSummaryApi($router);
        } catch (Throwable $e) {
            $payload = [
                'network_status' => 'connection_error',
                'status_message' => $e->getMessage(),
                'day_sales' => '0',
                'month_sales' => '0',
                'total_online' => 0,
                'hotspot_users' => 0,
                'pppoe_users' => 0,
                'active_accounts' => '0/0',
                'online_routers' => '0',
                'total_customers' => (string) ORM::for_table('tbl_customers')->count(),
            ];
        }

        echo json_encode($payload);
        exit;
    }

    if (in_array($action, ['hotspot_data', 'pppoe_data', 'disconnect'], true)) {
        // Check authentication for these endpoints
        if (!_admin(false)) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Please login first']);
            exit;
        }
        
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => Lang::T('Permission denied')]);
            exit;
        }

        if ($action === 'hotspot_data') {
            $routerName = _get('router', '');
            $routerId = _get('router_id', '');
            
            // Debug logging
            $debug_log = '/tmp/hotspot_debug.log';
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request - RouterName: '{$routerName}', RouterId: '{$routerId}'\n", FILE_APPEND);
            
            $result = onlineusers_fetch_hotspot_rows($routerName, $routerId);
            
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Result count: " . count($result) . "\n", FILE_APPEND);
            
            // Ensure we always return an array
            if (!is_array($result)) {
                $result = [];
            }
            
            echo json_encode($result);
            exit;
        }

        if ($action === 'pppoe_data') {
            $routerName = _get('router', '');
            $routerId = _get('router_id', '');
            $result = onlineusers_fetch_pppoe_rows($routerName, $routerId);
            
            if (!is_array($result)) {
                $result = [];
            }
            
            echo json_encode($result);
            exit;
        }

        if ($action === 'disconnect') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                exit;
            }
            
            $csrf = _post('csrf_token');
            if (!Csrf::check($csrf)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => Lang::T('Invalid or Expired CSRF Token')]);
                exit;
            }

            $routerKey = trim((string) _post('router'));
            $username = trim((string) _post('username'));
            $userType = strtolower(trim((string) _post('userType', _post('user_type', 'hotspot'))));

            if ($username === '' || $routerKey === '') {
                echo json_encode(['status' => 'error', 'message' => Lang::T('Missing router or username')]);
                exit;
            }

            $r = ORM::for_table('tbl_routers')->where('enabled', '1');
            if (ctype_digit($routerKey)) {
                $r->where('id', (int) $routerKey);
            } else {
                $r->where('name', $routerKey);
            }
            $mik = $r->find_one();
            if (!$mik) {
                echo json_encode(['status' => 'error', 'message' => Lang::T('Router not found')]);
                exit;
            }

            try {
                $client = Mikrotik::getClient($mik['ip_address'], $mik['username'], $mik['password']);
                if ($userType === 'pppoe' || $userType === 'ppp') {
                    Mikrotik::removePpoeActive($client, $username);
                } else {
                    Mikrotik::removeHotspotActiveUser($client, $username);
                }
                if (method_exists($client, 'disconnect')) {
                    $client->disconnect();
                }
                echo json_encode(['status' => 'success', 'message' => Lang::T('User disconnected')]);
            } catch (Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }
    }
}

// If we get here, it's not an API request - proceed with normal page rendering
_admin();
if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
    _alert(Lang::T('You do not have permission to access this page'), 'danger', 'dashboard');
}

$ui->assign('_title', Lang::T('Online Clients'));
$ui->assign('_admin', $admin);
$ui->assign('_system_menu', 'onlineusers');

$routers = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('name')->find_array();
$ui->assign('onlineusers_routers', $routers);
$csrf_token = Csrf::generateAndStoreToken();
$ui->assign('csrf_token', $csrf_token);

if ($action === 'hotspot') {
    $ui->display('admin/onlineusers/hotspot.tpl');
    exit;
}

if ($action === 'pppoe') {
    $ui->display('admin/onlineusers/pppoe.tpl');
    exit;
}

r2(getUrl('dashboard'));

// --- helpers (same file; not autoloaded elsewhere) ---

function onlineusers_format_bytes($bytes, $precision = 2)
{
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $bytes = max((int) $bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

function onlineusers_router_query($routerName, $routerId)
{
    $routerId = trim((string) $routerId);
    $routerName = trim((string) $routerName);
    
    $debug_log = '/tmp/hotspot_debug.log';
    
    // If specific router ID provided
    if ($routerId !== '' && ctype_digit($routerId)) {
        file_put_contents($debug_log, "Looking for router by ID: {$routerId}\n", FILE_APPEND);
        $router = ORM::for_table('tbl_routers')
            ->where('enabled', '1')
            ->where('id', (int) $routerId)
            ->find_one();
        
        if ($router) {
            file_put_contents($debug_log, "Found router by ID: {$router['name']}\n", FILE_APPEND);
            return [$router];
        } else {
            file_put_contents($debug_log, "Router ID {$routerId} not found\n", FILE_APPEND);
            return [];
        }
    }
    
    // If specific router name provided - make it case-insensitive
    if ($routerName !== '') {
        file_put_contents($debug_log, "Looking for router by name: {$routerName}\n", FILE_APPEND);
        
        // First try exact match
        $router = ORM::for_table('tbl_routers')
            ->where('enabled', '1')
            ->where('name', $routerName)
            ->find_one();
        
        // If not found, try case-insensitive match
        if (!$router) {
            file_put_contents($debug_log, "Exact match not found, trying case-insensitive\n", FILE_APPEND);
            $allRouters = ORM::for_table('tbl_routers')
                ->where('enabled', '1')
                ->find_many();
            
            foreach ($allRouters as $r) {
                if (strtolower($r['name']) === strtolower($routerName)) {
                    $router = $r;
                    file_put_contents($debug_log, "Found case-insensitive match: {$r['name']}\n", FILE_APPEND);
                    break;
                }
            }
        }
        
        if ($router) {
            return [$router];
        } else {
            file_put_contents($debug_log, "Router name '{$routerName}' not found\n", FILE_APPEND);
            return [];
        }
    }
    
    // No specific router requested, return all enabled routers
    $allRouters = ORM::for_table('tbl_routers')
        ->where('enabled', '1')
        ->order_by_asc('name')
        ->find_many();
    
    file_put_contents($debug_log, "No filter, returning all " . count($allRouters) . " routers\n", FILE_APPEND);
    return $allRouters;
}

function onlineusers_fetch_hotspot_rows($routerName, $routerId)
{
    $rows = [];
    $routers = onlineusers_router_query($routerName, $routerId);
    
    $debug_log = '/tmp/hotspot_debug.log';
    file_put_contents($debug_log, "Fetching hotspot from " . count($routers) . " routers\n", FILE_APPEND);
    
    // If no routers found
    if (count($routers) === 0) {
        return [];
    }

    foreach ($routers as $mik) {
        $routerNameDisp = $mik['name'];
        $routerIp = $mik['ip_address'];
        
        file_put_contents($debug_log, "Checking router: {$routerNameDisp} ({$routerIp})\n", FILE_APPEND);
        
        try {
            $client = Mikrotik::getClient($mik['ip_address'], $mik['username'], $mik['password']);
            
            // Test connection
            $identity = $client->sendSync(new Request('/system/identity/print'));
            file_put_contents($debug_log, "Connected to {$routerNameDisp}\n", FILE_APPEND);
            
            $hotspotActive = $client->sendSync(new Request('/ip/hotspot/active/print'));
            
            file_put_contents($debug_log, "Found " . count($hotspotActive) . " hotspot entries\n", FILE_APPEND);
            
            if (count($hotspotActive) > 0) {
                foreach ($hotspotActive as $hotspot) {
                    $u = $hotspot->getProperty('user');
                    $address = $hotspot->getProperty('address');
                    $mac = $hotspot->getProperty('mac-address');
                    $uptime = $hotspot->getProperty('uptime');
                    $rxBytes = (int) $hotspot->getProperty('bytes-in');
                    $txBytes = (int) $hotspot->getProperty('bytes-out');

                    if (!empty($u)) {
                        $rows[] = [
                            'username' => $u,
                            'ip_address' => $address ?: '-',
                            'mac_address' => $mac ?: '-',
                            'router_name' => $routerNameDisp,
                            'uptime' => $uptime ?: '0s',
                            'bytes_out' => onlineusers_format_bytes($txBytes),
                            'bytes_in' => onlineusers_format_bytes($rxBytes),
                        ];
                        file_put_contents($debug_log, "Added user: {$u}\n", FILE_APPEND);
                    }
                }
            }
            
            if (method_exists($client, 'disconnect')) {
                $client->disconnect();
            }
        } catch (Throwable $e) {
            file_put_contents($debug_log, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
            continue;
        }
    }

    file_put_contents($debug_log, "Total users: " . count($rows) . "\n\n", FILE_APPEND);
    return $rows;
}

function onlineusers_fetch_pppoe_rows($routerName, $routerId)
{
    $rows = [];
    $routers = onlineusers_router_query($routerName, $routerId);
    
    // If no routers found
    if (count($routers) === 0) {
        return [];
    }

    foreach ($routers as $mik) {
        $routerNameDisp = $mik['name'];
        try {
            $client = Mikrotik::getClient($mik['ip_address'], $mik['username'], $mik['password']);
            
            // Test connection
            $client->sendSync(new Request('/system/identity/print'));
            
            // Get PPP active users
            $pppUsers = $client->sendSync(new Request('/ppp/active/print'));

            if (count($pppUsers) > 0) {
                // Get interface traffic data
                $interfaceData = [];
                $ifacePrint = $client->sendSync(new Request('/interface/print'));
                foreach ($ifacePrint as $interface) {
                    $name = $interface->getProperty('name');
                    if ($name === null || $name === '') {
                        continue;
                    }
                    $interfaceData[$name] = [
                        'txBytes' => (int) $interface->getProperty('tx-byte'),
                        'rxBytes' => (int) $interface->getProperty('rx-byte'),
                    ];
                }

                foreach ($pppUsers as $pppUser) {
                    $username = $pppUser->getProperty('name');
                    $sid = $pppUser->getProperty('.id');
                    $address = $pppUser->getProperty('address');
                    $uptime = $pppUser->getProperty('uptime');
                    $service = $pppUser->getProperty('service');
                    $callerid = $pppUser->getProperty('caller-id');

                    if (empty($username)) {
                        continue;
                    }

                    // Get traffic for this PPPoE interface
                    $interfaceName = '<pppoe-' . $username . '>';
                    if (isset($interfaceData[$interfaceName])) {
                        $txBytes = $interfaceData[$interfaceName]['txBytes'];
                        $rxBytes = $interfaceData[$interfaceName]['rxBytes'];
                    } else {
                        // Try alternative interface naming
                        $interfaceNameAlt = 'pppoe-' . $username;
                        if (isset($interfaceData[$interfaceNameAlt])) {
                            $txBytes = $interfaceData[$interfaceNameAlt]['txBytes'];
                            $rxBytes = $interfaceData[$interfaceNameAlt]['rxBytes'];
                        } else {
                            $txBytes = 0;
                            $rxBytes = 0;
                        }
                    }

                    $rows[] = [
                        'id' => $sid ?: $username,
                        'username' => $username,
                        'address' => $address ?: '-',
                        'uptime' => $uptime ?: '0s',
                        'service' => $service ?: 'pppoe',
                        'caller_id' => $callerid ?: '-',
                        'router_name' => $routerNameDisp,
                        'tx' => onlineusers_format_bytes($txBytes),
                        'rx' => onlineusers_format_bytes($rxBytes),
                        'total' => onlineusers_format_bytes($txBytes + $rxBytes),
                    ];
                }
            }
            
            if (method_exists($client, 'disconnect')) {
                $client->disconnect();
            }
        } catch (Throwable $e) {
            // Log error but continue with other routers
            error_log("Error fetching PPPoE users from {$routerNameDisp}: " . $e->getMessage());
            continue;
        }
    }

    return $rows;
}
?>