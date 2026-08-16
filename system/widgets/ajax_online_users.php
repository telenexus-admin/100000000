<?php
/**
 * AJAX handler for online users
 * Returns JSON data for hotspot and PPPoE online users
 */

// Fix the path - go up two levels from widgets to mole root
$init_path = dirname(__DIR__, 2) . '/init.php';
if (!file_exists($init_path)) {
    // Try alternative path
    $init_path = __DIR__ . '/../init.php';
}
require_once $init_path;

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    die('Direct access not allowed');
}

// Check authentication
if (!_admin(false)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

header('Content-Type: application/json; charset=utf-8');

if ($action === 'hotspot_data') {
    $routerName = isset($_GET['router']) ? $_GET['router'] : '';
    $routerId = isset($_GET['router_id']) ? $_GET['router_id'] : '';
    $result = fetchHotspotOnlineUsers($routerName, $routerId);
    echo json_encode($result);
    exit;
}

if ($action === 'pppoe_data') {
    $routerName = isset($_GET['router']) ? $_GET['router'] : '';
    $routerId = isset($_GET['router_id']) ? $_GET['router_id'] : '';
    $result = fetchPppoeOnlineUsers($routerName, $routerId);
    echo json_encode($result);
    exit;
}

if ($action === 'disconnect') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }
    
    $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!Csrf::check($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $routerKey = isset($_POST['router']) ? trim($_POST['router']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $userType = isset($_POST['userType']) ? strtolower(trim($_POST['userType'])) : 'hotspot';
    
    if (empty($username) || empty($routerKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing router or username']);
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
        echo json_encode(['status' => 'error', 'message' => 'Router not found']);
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
        echo json_encode(['status' => 'success', 'message' => 'User disconnected successfully']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
exit;

// Helper functions
function fetchHotspotOnlineUsers($routerName, $routerId)
{
    $rows = [];
    
    $q = ORM::for_table('tbl_routers')->where('enabled', '1');
    $routerId = trim((string) $routerId);
    $routerName = trim((string) $routerName);
    
    if ($routerId !== '' && ctype_digit($routerId)) {
        $q->where('id', (int) $routerId);
    } elseif ($routerName !== '') {
        $q->where('name', $routerName);
    }
    
    $routers = $q->order_by_asc('name')->find_many();
    
    if (count($routers) === 0) {
        $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('name')->find_many();
    }
    
    foreach ($routers as $mik) {
        try {
            $client = Mikrotik::getClient($mik['ip_address'], $mik['username'], $mik['password']);
            $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print'));
            $hotspotActive = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
            
            foreach ($hotspotActive as $hotspot) {
                $u = trim($hotspot->getProperty('user'));
                if (empty($u)) continue;
                
                $rows[] = [
                    'username' => $u,
                    'ip_address' => $hotspot->getProperty('address') ?: '-',
                    'mac_address' => $hotspot->getProperty('mac-address') ?: '-',
                    'router_name' => $mik['name'],
                    'uptime' => $hotspot->getProperty('uptime') ?: '0s',
                    'bytes_in' => formatBytes((int) $hotspot->getProperty('bytes-in')),
                    'bytes_out' => formatBytes((int) $hotspot->getProperty('bytes-out')),
                ];
            }
            
            if (method_exists($client, 'disconnect')) {
                $client->disconnect();
            }
        } catch (Exception $e) {
            error_log("Error fetching hotspot users from {$mik['name']}: " . $e->getMessage());
            continue;
        }
    }
    
    return $rows;
}

function fetchPppoeOnlineUsers($routerName, $routerId)
{
    $rows = [];
    
    $q = ORM::for_table('tbl_routers')->where('enabled', '1');
    $routerId = trim((string) $routerId);
    $routerName = trim((string) $routerName);
    
    if ($routerId !== '' && ctype_digit($routerId)) {
        $q->where('id', (int) $routerId);
    } elseif ($routerName !== '') {
        $q->where('name', $routerName);
    }
    
    $routers = $q->order_by_asc('name')->find_many();
    
    if (count($routers) === 0) {
        $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('name')->find_many();
    }
    
    foreach ($routers as $mik) {
        try {
            $client = Mikrotik::getClient($mik['ip_address'], $mik['username'], $mik['password']);
            $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print'));
            $pppActive = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ppp/active/print'));
            
            // Get interface traffic
            $interfaceData = [];
            $ifacePrint = $client->sendSync(new PEAR2\Net\RouterOS\Request('/interface/print'));
            foreach ($ifacePrint as $interface) {
                $name = $interface->getProperty('name');
                if (!empty($name)) {
                    $interfaceData[$name] = [
                        'tx' => (int) $interface->getProperty('tx-byte'),
                        'rx' => (int) $interface->getProperty('rx-byte'),
                    ];
                }
            }
            
            foreach ($pppActive as $pppUser) {
                $username = trim($pppUser->getProperty('name'));
                if (empty($username)) continue;
                
                $interfaceName = '<pppoe-' . $username . '>';
                $tx = 0;
                $rx = 0;
                if (isset($interfaceData[$interfaceName])) {
                    $tx = $interfaceData[$interfaceName]['tx'];
                    $rx = $interfaceData[$interfaceName]['rx'];
                }
                
                $rows[] = [
                    'username' => $username,
                    'address' => $pppUser->getProperty('address') ?: '-',
                    'caller_id' => $pppUser->getProperty('caller-id') ?: '-',
                    'router_name' => $mik['name'],
                    'uptime' => $pppUser->getProperty('uptime') ?: '0s',
                    'rx' => formatBytes($rx),
                    'tx' => formatBytes($tx),
                    'total' => formatBytes($tx + $rx),
                ];
            }
            
            if (method_exists($client, 'disconnect')) {
                $client->disconnect();
            }
        } catch (Exception $e) {
            error_log("Error fetching PPPoE users from {$mik['name']}: " . $e->getMessage());
            continue;
        }
    }
    
    return $rows;
}

function formatBytes($bytes, $precision = 2)
{
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max((int) $bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
