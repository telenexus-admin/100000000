<?php
include __DIR__ . "/../init.php";

echo "=== MONITORING ROUTER FOR USER 45699 ===\n";
echo "This will run for 60 seconds and show if user gets re-added\n\n";

$router = ORM::for_table('tbl_routers')->where('enabled', '1')->find_one();

if (!$router) {
    echo "No router found\n";
    exit;
}

try {
    $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);
    
    // First, completely remove the user
    echo "Step 1: Completely removing user from router...\n";
    
    // Remove from hotspot users
    $find = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print');
    $find->setArgument('?name', '45699');
    $result = $client->sendSync($find);
    
    foreach ($result as $user) {
        $remove = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/remove');
        $remove->setArgument('.id', $user->getProperty('.id'));
        $client->sendSync($remove);
        echo "  ✓ Removed from hotspot users\n";
    }
    
    // Disconnect active sessions
    $active = new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print');
    $active->setArgument('?user', '45699');
    $active_result = $client->sendSync($active);
    
    foreach ($active_result as $session) {
        $disconnect = new PEAR2\Net\RouterOS\Request('/ip/hotspot/active/remove');
        $disconnect->setArgument('.id', $session->getProperty('.id'));
        $client->sendSync($disconnect);
        echo "  ✓ Disconnected active session\n";
    }
    
    echo "\nStep 2: Monitoring for 60 seconds...\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Monitor every 10 seconds
    for ($i = 1; $i <= 6; $i++) {
        sleep(10);
        
        $check = new PEAR2\Net\RouterOS\Request('/ip/hotspot/user/print');
        $check->setArgument('?name', '45699');
        $check_result = $client->sendSync($check);
        
        if (count($check_result) > 0) {
            echo "[$i] ⚠️ User FOUND in router at " . date('Y-m-d H:i:s') . "!\n";
            foreach ($check_result as $user) {
                echo "    - ID: {$user->getProperty('.id')}\n";
                echo "    - Profile: {$user->getProperty('profile')}\n";
                echo "    - Disabled: {$user->getProperty('disabled')}\n";
            }
        } else {
            echo "[$i] ✓ User NOT found at " . date('Y-m-d H:i:s') . "\n";
        }
    }
    
    echo "\nMonitoring complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

