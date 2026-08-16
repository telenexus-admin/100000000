<?php
/**
 * API endpoint for getting online users from a specific router
 * Called by the dashboard widget for real-time data
 */

// Include required files
require_once dirname(__DIR__, 2) . '/init.php';

header('Content-Type: application/json');

// Security: Check for valid token
$router_id = $_GET['router_id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$router_id || !$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    // Get router details
    $router = ORM::for_table('tbl_routers')
        ->where('id', $router_id)
        ->where('enabled', 1)
        ->find_one();
    
    if (!$router) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Router not found']);
        exit;
    }
    
    // Verify token
    $expected_token = md5($router['password'] . $router['ip_address']);
    if ($token !== $expected_token) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    
    // Query MikroTik router
    $result = queryMikroTikRouter($router);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Query MikroTik router for online users
 */
function queryMikroTikRouter($router)
{
    $hotspot_users = [];
    $pppoe_users = [];
    
    try {
        // Connect to MikroTik with timeout
        $client = Mikrotik::getClient(
            $router['ip_address'],
            $router['username'],
            $router['password']
        );
        
        // Set socket timeout to 3 seconds
        if (method_exists($client, 'setTimeout')) {
            $client->setTimeout(3);
        }
        
        // Get Hotspot active users
        try {
            $request = new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print');
            $response = $client->sendSync($request);
            
            foreach ($response as $user) {
                $username = $user->getProperty('user');
                if (!empty($username)) {
                    $hotspot_users[] = $username;
                }
            }
        } catch (Exception $e) {
            // Hotspot might not be configured - log but don't fail
            error_log("Hotspot query failed for {$router['name']}: " . $e->getMessage());
        }
        
        // Get PPPoE active users
        try {
            $request = new PEAR2\Net\RouterOS\Request('/ppp/active/print');
            $response = $client->sendSync($request);
            
            foreach ($response as $user) {
                $username = $user->getProperty('name');
                if (!empty($username)) {
                    $pppoe_users[] = $username;
                }
            }
        } catch (Exception $e) {
            // PPPoE might not be configured - log but don't fail
            error_log("PPPoE query failed for {$router['name']}: " . $e->getMessage());
        }
        
        // Disconnect
        if (method_exists($client, 'disconnect')) {
            $client->disconnect();
        }
        
        return [
            'success' => true,
            'hotspot_users' => $hotspot_users,
            'pppoe_users' => $pppoe_users,
            'total' => count($hotspot_users) + count($pppoe_users),
            'router_name' => $router['name'],
            'router_ip' => $router['ip_address'],
            'timestamp' => time()
        ];
        
    } catch (Exception $e) {
        error_log("MikroTik connection error for {$router['name']}: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'router_name' => $router['name'],
            'router_ip' => $router['ip_address']
        ];
    }
}
