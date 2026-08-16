<?php

_admin();
$ui->assign('_title', Lang::T('Network'));
$ui->assign('_system_menu', 'network');

$action = isset($routes['1']) ? $routes['1'] : '';
$ui->assign('_admin', $admin);

require_once $DEVICE_PATH . DIRECTORY_SEPARATOR . "MikrotikHotspot.php";

if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
    _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
}

// Get base URL for AJAX calls
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$base_url .= str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);
$ui->assign('base_url', $base_url);

// Start session for security code verification (only for delete)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$leafletpickerHeader = <<<EOT
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
EOT;

/**
 * Create a RouterOS client with a bounded connection/login timeout.
 * Explicit admin actions retain their behavior, but an unreachable router must
 * not consume an Apache worker indefinitely.
 */
function createRouterClient($address, $username, $password, $timeout = 5) {
    $host = $address;
    $port = 8728;
    if (strpos($address, ':') !== false) {
        [$host, $port] = explode(':', $address, 2);
    }
    return new \PEAR2\Net\RouterOS\Client($host, $username, $password, (int) $port, false, (int) $timeout);
}

// Helper function to check router connection
function checkRouterConnection($ip, $username, $password) {
    try {
        $client = createRouterClient($ip, $username, $password);
        $response = $client->sendSync(new \PEAR2\Net\RouterOS\Request('/system/resource/print'));
        
        if ($response->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
            return ['connected' => true, 'response' => $response];
        }
        return ['connected' => false, 'error' => 'No data received'];
    } catch (Exception $e) {
        return ['connected' => false, 'error' => $e->getMessage()];
    }
}

function rebootRouterDevice($ip, $username, $password) {
    try {
        $client = createRouterClient($ip, $username, $password);
        $request = new \PEAR2\Net\RouterOS\Request('/system/reboot');
        $client->sendSync($request);
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================== ALL API ENDPOINTS (MUST BE FIRST) ====================

// Debug endpoint - Test if API is working
if ($action == 'debug') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'API endpoint is working',
        'action' => $action,
        'router_id' => isset($routes['2']) ? $routes['2'] : null,
        'php_version' => phpversion(),
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

// Handle test connection
if ($action == 'test-connection') {
    header('Content-Type: application/json');
    
    $router_id = isset($routes['2']) ? (int)$routes['2'] : 0;
    
    if ($router_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid router ID']);
        exit;
    }
    
    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    
    if (!$router) {
        echo json_encode(['status' => 'error', 'message' => 'Router not found']);
        exit;
    }
    
    $start_time = microtime(true);
    
    try {
        $client = createRouterClient($router['ip_address'], $router['username'], $router['password']);
        
        $connection_time = round((microtime(true) - $start_time) * 1000);
        
        if ($client) {
            $request = new \PEAR2\Net\RouterOS\Request('/system/resource/print');
            $response = $client->sendSync($request);
            
            $version = '';
            $uptime = '';
            
            if ($response->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                foreach ($response as $entry) {
                    $version = $entry->getProperty('version');
                    $uptime = $entry->getProperty('uptime');
                    break;
                }
            }
            
            $router->last_seen = date('Y-m-d H:i:s');
            $router->status = 'Online';
            $router->version = $version;
            $router->save();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Connection successful (' . $connection_time . 'ms)',
                'version' => $version ?: 'RouterOS',
                'uptime' => $uptime ?: 'N/A',
                'connection_time' => $connection_time
            ]);
        } else {
            if ($router->status != 'Offline') {
                $router->status = 'Offline';
                $router->save();
            }
            
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to connect to router',
                'connection_time' => $connection_time
            ]);
        }
    } catch (Exception $e) {
        $connection_time = round((microtime(true) - $start_time) * 1000);
        
        if ($router->status == 'Online') {
            $router->status = 'Offline';
            $router->save();
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Connection failed: ' . $e->getMessage(),
            'connection_time' => $connection_time
        ]);
    }
    exit;
}

// Handle reboot action
if ($action == 'reboot') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST.']);
        exit;
    }
    
    $router_id = isset($routes['2']) ? (int)$routes['2'] : 0;
    
    if ($router_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid router ID']);
        exit;
    }
    
    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if (!$router) {
        echo json_encode(['status' => 'error', 'message' => 'Router not found']);
        exit;
    }
    
    try {
        $client = createRouterClient($router['ip_address'], $router['username'], $router['password']);
        
        if ($client) {
            // Send reboot command
            $request = new \PEAR2\Net\RouterOS\Request('/system/reboot');
            $client->sendSync($request);
            
            // Update router status
            $router->status = 'Rebooting';
            $router->last_seen = date('Y-m-d H:i:s');
            $router->save();
            
            // Log the reboot action
            _log('[' . $admin['username'] . ']: Rebooted router ' . $router['name'] . ' [' . $router['ip_address'] . ']', 'SuperAdmin');
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Router reboot initiated successfully. The router will be back online in a few moments.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to connect to router. Please check if the router is reachable.'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Reboot failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle status check
if ($action == 'status') {
    header('Content-Type: application/json');

    $router_id = isset($routes['2']) ? (int)$routes['2'] : 0;
    if ($router_id == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid router ID']);
        exit;
    }

    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if (!$router) {
        echo json_encode(['status' => 'error', 'message' => 'Router not found']);
        exit;
    }

    // Page rendering must never wait on RouterOS. The scheduled monitor updates
    // this persisted state; the explicit "Test connection" action still performs
    // a live check on demand.
    $lastSeen = !empty($router['last_seen']) ? strtotime($router['last_seen']) : 0;
    echo json_encode([
        'status' => strtolower($router['status'] ?: 'Offline'),
        'cached' => true,
        'stale' => !$lastSeen || (time() - $lastSeen) > 300,
        'last_seen' => $router['last_seen'] ?: null,
        'uptime' => isset($router['uptime']) ? $router['uptime'] : 'N/A',
        'version' => isset($router['version']) ? $router['version'] : 'N/A'
    ]);
    exit;
}
// Handle delete action (AJAX)
if ($action == 'delete' && isset($routes['2']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $router_id = (int)$routes['2'];
    $security_code = trim(_post('security_code'));
    
    if (strtolower($security_code) !== 'antiqua') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security code']);
        exit;
    }
    
    $d = ORM::for_table('tbl_routers')->find_one($router_id);
    if ($d) {
        _log('[' . $admin['username'] . ']: Deleted router ' . $d['name'] . ' [' . $d['ip_address'] . ']', 'SuperAdmin');
        $d->delete();
        echo json_encode(['status' => 'success', 'message' => 'Router deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Router not found']);
    }
    exit;
}

// ==================== PAGE VIEWS (SWITCH STATEMENT) ====================

switch ($action) {
    case 'add':
        run_hook('view_add_routers');
        $ui->display('admin/routers/add.tpl');
        break;

    case 'edit':
        $id = isset($routes['2']) ? (int)$routes['2'] : 0;
        
        $d = ORM::for_table('tbl_routers')->find_one($id);
        if (!$d) {
            $d = ORM::for_table('tbl_routers')->where_equal('name', _get('name'))->find_one();
        }
        $ui->assign('xheader', $leafletpickerHeader);
        if ($d) {
            $ui->assign('d', $d);
            run_hook('view_router_edit');
            $ui->display('admin/routers/edit.tpl');
        } else {
            r2(getUrl('routers/list'), 'e', 'Account Not Found');
        }
        break;

    case 'add-post':
        $name = _post('name');
        $ip_address = _post('ip_address');
        $username = _post('username');
        $password = _post('password');
        $description = _post('description');
        $enabled = _post('enabled');

        $msg = '';
        if (Validator::Length($name, 30, 1) == false) {
            $msg .= 'Name should be between 1 to 30 characters' . '<br>';
        }
        if($enabled || _post("testIt")){
            if ($ip_address == '' or $username == '') {
                $msg .= 'All field is required' . '<br>';
            }

            $d = ORM::for_table('tbl_routers')->where('ip_address', $ip_address)->find_one();
            if ($d) {
                $msg .= 'IP Router Already Exist' . '<br>';
            }
        }
        if (strtolower($name) == 'radius') {
            $msg .= '<b>Radius</b> name is reserved<br>';
        }

        if ($msg == '') {
            run_hook('add_router');
            if (_post("testIt")) {
                try {
                    $client = createRouterClient($ip_address, $username, $password);
                } catch (Exception $e) {
                    // Connection failed but we still create the router
                }
            }
            $d = ORM::for_table('tbl_routers')->create();
            $d->name = $name;
            $d->ip_address = $ip_address;
            $d->username = $username;
            $d->password = $password;
            $d->description = $description;
            $d->enabled = $enabled;
            $d->status = 'Offline';
            $d->save();

            _log('[' . $admin['username'] . ']: Added new router ' . $name . ' [' . $ip_address . ']', 'SuperAdmin');
            
            r2(getUrl('routers/edit/') . $d->id(), 's', 'Data Created Successfully');
        } else {
            r2(getUrl('routers/add'), 'e', $msg);
        }
        break;

    case 'edit-post':
        $name = _post('name');
        $ip_address = _post('ip_address');
        $username = _post('username');
        $password = _post('password');
        $description = _post('description');
        $coordinates = _post('coordinates');
        $coverage = _post('coverage');
        $enabled = $_POST['enabled'];
        $msg = '';
        
        if (Validator::Length($name, 30, 4) == false) {
            $msg .= 'Name should be between 5 to 30 characters' . '<br>';
        }
        if($enabled || _post("testIt")){
            if ($ip_address == '' or $username == '') {
                $msg .= 'All field is required' . '<br>';
            }
        }

        $id = _post('id');
        $d = ORM::for_table('tbl_routers')->find_one($id);
        if (!$d) {
            $msg .= 'Data Not Found' . '<br>';
        }

        if ($d['name'] != $name) {
            $c = ORM::for_table('tbl_routers')->where('name', $name)->where_not_equal('id', $id)->find_one();
            if ($c) {
                $msg .= 'Name Already Exists<br>';
            }
        }
        $oldname = $d['name'];

        if($enabled || _post("testIt")){
            if ($d['ip_address'] != $ip_address) {
                $c = ORM::for_table('tbl_routers')->where('ip_address', $ip_address)->where_not_equal('id', $id)->find_one();
                if ($c) {
                    $msg .= 'IP Already Exists<br>';
                }
            }
        }

        if (strtolower($name) == 'radius') {
            $msg .= '<b>Radius</b> name is reserved<br>';
        }

        if ($msg == '') {
            run_hook('router_edit');
            if (_post("testIt")) {
                try {
                    $client = createRouterClient($ip_address, $username, $password);
                    
                    $d->status = 'Online';
                    $d->last_seen = date('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // Connection failed, keep existing status
                }
            }
            
            $d->name = $name;
            $d->ip_address = $ip_address;
            $d->username = $username;
            $d->password = $password;
            $d->description = $description;
            $d->coordinates = $coordinates;
            $d->coverage = $coverage;
            $d->enabled = $enabled;
            $d->save();
            
            _log('[' . $admin['username'] . ']: Edited router ' . $name . ' [' . $ip_address . ']', 'SuperAdmin');
            
            if ($name != $oldname) {
                $p = ORM::for_table('tbl_plans')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_payment_gateway')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_pool')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_transactions')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_user_recharges')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
                $p = ORM::for_table('tbl_voucher')->where('routers', $oldname)->find_result_set();
                $p->set('routers', $name);
                $p->save();
            }
            r2(getUrl('routers/list'), 's', 'Data Updated Successfully');
        } else {
            r2(getUrl('routers/edit/') . $id, 'e', $msg);
        }
        break;

    default:
        $name = _post('name');
        $query = ORM::for_table('tbl_routers')->order_by_desc('id');
        if ($name != '') {
            $query->where_like('name', '%' . $name . '%');
        }
        $d = Paginator::findMany($query, ['name' => $name]);
        $ui->assign('d', $d);
        run_hook('view_list_routers');
        $ui->display('admin/routers/list.tpl');
        break;
}
