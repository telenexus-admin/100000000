<?php
/**
 * Test AJAX handler
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/../init.php';

header('Content-Type: application/json');

// Check authentication
if (!_admin(false)) {
    echo json_encode(['error' => 'Not authenticated', 'admin' => isset($admin) ? 'isset' : 'not set']);
    exit;
}

if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
    echo json_encode(['error' => 'Permission denied', 'user_type' => $admin['user_type']]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'hotspot_data') {
    $rows = [];
    $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
    
    foreach ($routers as $router) {
        try {
            $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
            $hotspot = $client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print'));
            
            foreach ($hotspot as $user) {
                $username = trim($user->getProperty('user'));
                if (empty($username)) continue;
                
                $rows[] = [
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
            // Add error to response for debugging
            $rows[] = ['error' => $router['name'] . ': ' . $e->getMessage()];
        }
    }
    
    echo json_encode($rows);
    exit;
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

echo json_encode(['error' => 'Invalid action', 'action' => $action]);
?>
