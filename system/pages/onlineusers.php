<?php
/**
 * Online Users Controller
 */

$action = $routes['1'] ?? '';

// Check if we need to return JSON data
if (in_array($action, ['hotspot_data', 'pppoe_data', 'summary', 'disconnect'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!_admin(false)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    if ($action === 'hotspot_data') {
        $routerId = isset($_GET['router_id']) ? $_GET['router_id'] : '';
        echo json_encode(fetchHotspotUsers($routerId));
        exit;
    }
    
    if ($action === 'pppoe_data') {
        $routerId = isset($_GET['router_id']) ? $_GET['router_id'] : '';
        echo json_encode(fetchPppoeUsers($routerId));
        exit;
    }
    
    if ($action === 'summary') {
        require_once $WIDGET_PATH . '/top_widget.php';
        $payload = top_widget::buildDashboardSummaryApi('all');
        echo json_encode($payload);
        exit;
    }
    
    if ($action === 'disconnect') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            exit;
        }
        
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Csrf::check($csrf)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $routerName = trim($_POST['router'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $userType = strtolower(trim($_POST['userType'] ?? 'hotspot'));
        
        if (empty($username) || empty($routerName)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing router or username']);
            exit;
        }
        
        $router = ORM::for_table('tbl_routers')
            ->where('enabled', '1')
            ->where('name', $routerName)
            ->find_one();
        
        if (!$router) {
            echo json_encode(['status' => 'error', 'message' => 'Router not found']);
            exit;
        }
        
        try {
            $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
            if ($userType === 'pppoe') {
                Mikrotik::removePpoeActive($client, $username);
            } else {
                Mikrotik::removeHotspotActiveUser($client, $username);
            }
            echo json_encode(['status' => 'success', 'message' => 'User disconnected successfully']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Display the HTML page
_admin();
if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
    _alert(Lang::T('You do not have permission to access this page'), 'danger', 'dashboard');
}

$ui->assign('_title', Lang::T('Online Clients'));
$ui->assign('_admin', $admin);
$ui->assign('_system_menu', 'onlineusers');

$routers = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('name')->find_array();
$ui->assign('onlineusers_routers', $routers);
$ui->assign('csrf_token', Csrf::generateAndStoreToken());

if ($action === 'hotspot') {
    $ui->display('admin/onlineusers/hotspot.tpl');
    exit;
}

if ($action === 'pppoe') {
    $ui->display('admin/onlineusers/pppoe.tpl');
    exit;
}

r2(getUrl('dashboard'));

function fetchHotspotUsers($routerId = '') {
    $users = [];
    $routers = ORM::for_table('tbl_routers')->where('enabled', '1');
    
    if (!empty($routerId) && ctype_digit($routerId)) {
        $routers->where('id', (int) $routerId);
    }
    $routers = $routers->find_many();
    
    foreach ($routers as $router) {
        try {
            $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
            $hotspot = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
            
            foreach ($hotspot as $user) {
                $username = trim($user->getProperty('user'));
                if (empty($username)) continue;
                
                $users[] = [
                    'username' => $username,
                    'ip_address' => $user->getProperty('address') ?: '-',
                    'mac_address' => $user->getProperty('mac-address') ?: '-',
                    'router_name' => $router['name'],
                    'uptime' => $user->getProperty('uptime') ?: '0s',
                    'bytes_in' => formatBytes((int) $user->getProperty('bytes-in')),
                    'bytes_out' => formatBytes((int) $user->getProperty('bytes-out')),
                ];
            }
        } catch (Exception $e) {
            // Skip routers that can't be reached
            continue;
        }
    }
    
    return $users;
}

function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
