<?php

_admin();
$ui->assign('_title', Lang::T('Customer'));
$ui->assign('_system_menu', 'customers');

$action = $routes['1'];
$ui->assign('_admin', $admin);

if (empty($action)) {
    $action = 'list';
}

$leafletpickerHeader = <<<EOT
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
EOT;

// Function to get user session data from LOCAL DATABASE ONLY (no external API calls)
function getUserLiveSession($username) {
    $userSession = [
        'online' => false,
        'type' => '',
        'ip' => '',
        'mac' => '',
        'uptime' => '',
        'session_time_left' => '',
        'upload' => '0 B',
        'download' => '0 B', 
        'total' => '0 B',
        'router' => '',
        'error' => false
    ];
    
    try {
        // First check for active session (online user)
        $active_session = ORM::for_table('tbl_usage_sessions')
            ->where('username', $username)
            ->where_gte('last_seen', date('Y-m-d H:i:s', strtotime('-' . PamnetCustomerStatus::onlineWindowSeconds() . ' seconds')))
            ->order_by_desc('start_time')
            ->find_one();
        
        // If no active session, get the most recent session (for historical data)
        $session = $active_session ?: ORM::for_table('tbl_usage_sessions')
            ->where('username', $username)
            ->order_by_desc('last_seen')
            ->find_one();
        
        if ($session) {
            $is_online = ($active_session !== null);
            $router = ORM::for_table('tbl_routers')->find_one($session->router_id);
            
            // Calculate uptime (session duration)
            if ($is_online) {
                // For online users: time since session started
                $start_time = strtotime($session->start_time);
                $current_time = time();
                $uptime_seconds = $current_time - $start_time;
            } else {
                // For offline users: show last session duration
                $start_time = strtotime($session->start_time);
                $end_time = strtotime($session->last_seen);
                $uptime_seconds = $end_time - $start_time;
            }
            
            // Format uptime properly
            if ($uptime_seconds <= 0) {
                $uptime = '< 1m';
            } else if ($uptime_seconds < 60) {
                $uptime = $uptime_seconds . 's';
            } else {
                $days = floor($uptime_seconds / 86400);
                $hours = floor(($uptime_seconds % 86400) / 3600);
                $minutes = floor(($uptime_seconds % 3600) / 60);
                $uptime_parts = [];
                if ($days > 0) $uptime_parts[] = $days . 'd';
                if ($hours > 0) $uptime_parts[] = $hours . 'h';
                if ($minutes > 0) $uptime_parts[] = $minutes . 'm';
                $uptime = empty($uptime_parts) ? '< 1m' : implode(' ', $uptime_parts);
            }
            
            // Calculate time remaining from active plan expiration (like recharge history)
            $session_time_left = 'Unlimited';
            try {
                $active_plan = ORM::for_table('tbl_user_recharges')
                    ->where('username', $username)
                    ->where('status', 'on')
                    ->order_by_desc('recharged_on')
                    ->find_one();
                    
                if ($active_plan) {
                    $expiry_string = trim($active_plan['expiration'] . ' ' . $active_plan['time']);
                    $expiry_ts = strtotime($expiry_string);
                    $now = time();
                    $seconds_left = $expiry_ts - $now;
                    
                    // Debug logging
                    error_log("DEBUG SESSION: User $username - Expiry String: '$expiry_string', Expiry TS: $expiry_ts, Now: $now, Seconds Left: $seconds_left");
                    
                    if ($expiry_ts === false || $seconds_left <= 0) {
                        $session_time_left = 'Expired';
                    } else {
                        $days = floor($seconds_left / 86400);
                        $hours = floor(($seconds_left % 86400) / 3600);
                        $minutes = floor(($seconds_left % 3600) / 60);
                        
                        $time_parts = array();
                        if ($days > 0) $time_parts[] = $days . 'd';
                        if ($hours > 0) $time_parts[] = $hours . 'h';  
                        if ($minutes > 0) $time_parts[] = $minutes . 'm';
                        
                        $session_time_left = empty($time_parts) ? '< 1m' : implode(' ', $time_parts);
                    }
                } else {
                    $session_time_left = 'No Active Plan';
                }
            } catch (Exception $e) {
                $session_time_left = 'Unknown';
            }
            
            if ($is_online) {
                // Online users: show current session data
                $userSession = [
                    'online' => true,
                    'type' => ucfirst($session->interface), // 'hotspot' or 'pppoe'
                    'ip' => $session->ip_address ?: 'Unknown',
                    'mac' => $session->mac_address ?: 'Unknown', 
                    'uptime' => $uptime,
                    'session_time_left' => $session_time_left,
                    'download' => formatBytes($session->session_rx ?: 0),
                    'upload' => formatBytes($session->session_tx ?: 0),
                    'total' => formatBytes(($session->session_rx ?: 0) + ($session->session_tx ?: 0)),
                    'router' => $router ? $router->name : 'Unknown',
                    'error' => false
                ];
            } else {
                // Offline users: show total lifetime data from tbl_usage_records
                $total_upload = ORM::for_table('tbl_usage_records')
                    ->where('username', $username)
                    ->sum('tx_bytes') ?: 0;
                $total_download = ORM::for_table('tbl_usage_records')
                    ->where('username', $username)
                    ->sum('rx_bytes') ?: 0;
                
                // Debug logging to check if data exists
                error_log("DEBUG: User $username (offline) - Total Upload: $total_upload, Total Download: $total_download");
                
                $userSession = [
                    'online' => false,
                    'type' => 'User Offline',
                    'ip' => '', // Blank for offline users
                    'mac' => '', // Blank for offline users
                    'uptime' => $uptime, // Show last session uptime
                    'session_time_left' => $session_time_left,
                    'download' => formatBytes($total_download), // Total lifetime download
                    'upload' => formatBytes($total_upload), // Total lifetime upload  
                    'total' => formatBytes($total_upload + $total_download), // Total lifetime usage
                    'router' => 'Total Usage',
                    'error' => false
                ];
            }
        } else {
            // No session data found - check historical records
            $total_upload = ORM::for_table('tbl_usage_records')
                ->where('username', $username)
                ->sum('tx_bytes') ?: 0;
            $total_download = ORM::for_table('tbl_usage_records')
                ->where('username', $username)
                ->sum('rx_bytes') ?: 0;
            
            // Debug logging to check if data exists
            error_log("DEBUG: User $username (no sessions) - Total Upload: $total_upload, Total Download: $total_download");
            
            $userSession = [
                'online' => false,
                'type' => 'No Session',
                'ip' => '',  // Blank for expired users
                'mac' => '', // Blank for expired users
                'uptime' => 'No Session',
                'session_time_left' => 'No Active Plan',
                'download' => formatBytes($total_download),
                'upload' => formatBytes($total_upload),
                'total' => formatBytes($total_upload + $total_download),
                'router' => 'Historical Data',
                'error' => false
            ];
        }
        
    } catch (Exception $e) {
        // Return offline status on any error
        $userSession['error'] = false;
    }
    
    return $userSession;
}

// Function to format bytes (fallback if not available)
if (!function_exists('formatBytes')) {
    function formatBytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }
}

switch ($action) {
    case 'csv':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers'), 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }

        $cs = ORM::for_table('tbl_customers')
            ->select('tbl_customers.id', 'id')
            ->select('tbl_customers.username', 'username')
            ->select('fullname')
            ->select('address')
            ->select('phonenumber')
            ->select('email')
            ->select('balance')
            ->select('service_type')
            ->order_by_asc('tbl_customers.id')
            ->find_array();

        $h = false;
        set_time_limit(-1);
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header("Content-type: text/csv");
        header('Content-Disposition: attachment;filename="phpnuxbill_customers_' . date('Y-m-d_H_i') . '.csv"');
        header('Content-Transfer-Encoding: binary');

        $headers = [
            'id',
            'username',
            'fullname',
            'address',
            'phonenumber',
            'email',
            'balance',
            'service_type',
        ];

        if (!$h) {
            echo '"' . implode('","', $headers) . "\"\n";
            $h = true;
        }

        foreach ($cs as $c) {
            $row = [
                $c['id'],
                $c['username'],
                $c['fullname'],
                $c['address'],
                $c['phonenumber'],
                $c['email'],
                $c['balance'],
                $c['service_type'],
            ];
            echo '"' . implode('","', $row) . "\"\n";
        }
        break;
        //case csv-prepaid can be moved later to (plan.php)  php file dealing with prepaid users
    case 'csv-prepaid':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        $cs = ORM::for_table('tbl_customers')
            ->select('tbl_customers.id', 'id')
            ->select('tbl_customers.username', 'username')
            ->select('fullname')
            ->select('address')
            ->select('phonenumber')
            ->select('email')
            ->select('balance')
            ->select('service_type')
            ->select('namebp')
            ->select('routers')
            ->select('status')
            ->select('method', 'Payment')
            ->left_outer_join('tbl_user_recharges', array('tbl_customers.id', '=', 'tbl_user_recharges.customer_id'))
            ->order_by_asc('tbl_customers.id')
            ->find_array();

        $h = false;
        set_time_limit(-1);
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header("Content-type: text/csv");
        header('Content-Disposition: attachment;filename="phpnuxbill_prepaid_users' . date('Y-m-d_H_i') . '.csv"');
        header('Content-Transfer-Encoding: binary');

        $headers = [
            'id',
            'username',
            'fullname',
            'address',
            'phonenumber',
            'email',
            'balance',
            'service_type',
            'namebp',
            'routers',
            'status',
            'Payment'
        ];

        if (!$h) {
            echo '"' . implode('","', $headers) . "\"\n";
            $h = true;
        }

        foreach ($cs as $c) {
            $row = [
                $c['id'],
                $c['username'],
                $c['fullname'],
                $c['address'],
                $c['phonenumber'],
                $c['email'],
                $c['balance'],
                $c['service_type'],
                $c['namebp'],
                $c['routers'],
                $c['status'],
                $c['Payment']
            ];
            echo '"' . implode('","', $row) . "\"\n";
        }
        break;
    case 'add':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('xheader', $leafletpickerHeader);
        run_hook('view_add_customer'); #HOOK
        $ui->assign('csrf_token',  Csrf::generateAndStoreToken());
        $ui->display('admin/customers/add.tpl');
        break;
    case 'recharge':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_customer = isset($routes['2']) ? intval($routes['2']) : 0;
        $plan_id = isset($routes['3']) ? intval($routes['3']) : 0;
        if ($id_customer <= 0 || $plan_id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $b = ORM::for_table('tbl_user_recharges')->where('customer_id', $id_customer)->where('plan_id', $plan_id)->find_one();
        if ($b) {
            $gateway = 'Recharge';
            $channel = $admin['fullname'];
            $cust = User::_info($id_customer);
            $plan = ORM::for_table('tbl_plans')->find_one($b['plan_id']);
            $add_inv = User::getAttribute("Invoice", $id_customer);
            if (!empty($add_inv)) {
                $plan['price'] = $add_inv;
            }
            $tax_enable = isset($config['enable_tax']) ? $config['enable_tax'] : 'no';
            $tax_rate_setting = isset($config['tax_rate']) ? $config['tax_rate'] : null;
            $custom_tax_rate = isset($config['custom_tax_rate']) ? (float)$config['custom_tax_rate'] : null;
            if ($tax_rate_setting === 'custom') {
                $tax_rate = $custom_tax_rate;
            } else {
                $tax_rate = $tax_rate_setting;
            }
            if ($tax_enable === 'yes') {
                $tax = Package::tax($plan['price'], $tax_rate);
            } else {
                $tax = 0;
            }
            list($bills, $add_cost) = User::getBills($id_customer);
            if ($using == 'balance' && $config['enable_balance'] == 'yes') {
                if (!$cust) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('Customer not found'));
                }
                if (!$plan) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('Plan not found'));
                }
                if ($cust['balance'] < ($plan['price'] + $add_cost + $tax)) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('insufficient balance'));
                }
                $gateway = 'Recharge Balance';
            }
            if ($using == 'zero') {
                $zero = 1;
                $gateway = 'Recharge Zero';
            }
            $usings = explode(',', $config['payment_usings']);
            $usings = array_filter(array_unique($usings));
            if (count($usings) == 0) {
                $usings[] = Lang::T('Cash');
            }
            $abills = User::getAttributes("Bill");
            if ($tax_enable === 'yes') {
                $ui->assign('tax', $tax);
            }
            $ui->assign('usings', $usings);
            $ui->assign('abills', $abills);
            $ui->assign('bills', $bills);
            $ui->assign('add_cost', $add_cost);
            $ui->assign('cust', $cust);
            $ui->assign('gateway', $gateway);
            $ui->assign('channel', $channel);
            $ui->assign('server', $b['routers']);
            $ui->assign('plan', $plan);
            $ui->assign('add_inv', $add_inv);
            $ui->assign('csrf_token',  Csrf::generateAndStoreToken());
            $ui->display('admin/plan/recharge-confirm.tpl');
        } else {
            r2(getUrl('customers/view/') . $id_customer, 'e', 'Cannot find active plan');
        }
        break;
    case 'deactivate':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_customer = isset($routes['2']) ? intval($routes['2']) : 0;
        $plan_id = isset($routes['3']) ? intval($routes['3']) : 0;
        if ($id_customer <= 0 || $plan_id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $b = ORM::for_table('tbl_user_recharges')->where('customer_id', $id_customer)->where('plan_id', $plan_id)->find_one();
        if ($b) {
            $p = ORM::for_table('tbl_plans')->where('id', $b['plan_id'])->find_one();
            if ($p) {
                $p = ORM::for_table('tbl_plans')->where('id', $b['plan_id'])->find_one();
                $c = User::_info($id_customer);
                $dvc = Package::getDevice($p);
                if ($_app_stage != 'demo') {
                    if (file_exists($dvc)) {
                        require_once $dvc;
                        (new $p['device'])->remove_customer($c, $p);
                    } else {
                        throw new Exception(Lang::T("Devices Not Found"));
                    }
                }
                $b->status = 'off';
                $b->expiration = date('Y-m-d');
                $b->time = date('H:i:s');
                $b->save();
                _log('Admin ' . $admin['username'] . ' Deactivate ' . $b['namebp'] . ' for ' . $b['username'], 'User', $b['customer_id']);
                Message::sendTelegram('Admin ' . $admin['username'] . ' Deactivate ' . $b['namebp'] . ' for u' . $b['username']);
                r2(getUrl('customers/view/') . $id_customer, 's', 'Success deactivate customer to Mikrotik');
            }
        }
        r2(getUrl('customers/view/') . $id_customer, 'e', 'Cannot find active plan');
        break;
    case 'disable':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_customer = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id_customer <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $c = ORM::for_table('tbl_customers')->find_one($id_customer);
        if (!$c) {
            r2(getUrl('customers/list'), 'e', Lang::T('Account Not Found'));
        }
        if ($c['status'] == 'Disabled') {
            $c->status = 'Active';
            $c->save();
            _log('Admin ' . $admin['username'] . ' Enabled customer ' . $c['username'], 'User', $id_customer);
            r2(getUrl('customers/view/') . $id_customer, 's', Lang::T('Customer enabled'));
        }
        // Disable account and deactivate active packages
        $activePlans = ORM::for_table('tbl_user_recharges')
            ->where('customer_id', $id_customer)
            ->where('status', 'on')
            ->find_many();
        $customerInfo = User::_info($id_customer);
        foreach ($activePlans as $b) {
            $p = ORM::for_table('tbl_plans')->where('id', $b['plan_id'])->find_one();
            if ($p && $_app_stage != 'demo') {
                $dvc = Package::getDevice($p);
                if (file_exists($dvc)) {
                    require_once $dvc;
                    (new $p['device'])->remove_customer($customerInfo, $p);
                }
            }
            $b->status = 'off';
            $b->expiration = date('Y-m-d');
            $b->time = date('H:i:s');
            $b->save();
        }
        $c->status = 'Disabled';
        $c->save();
        _log('Admin ' . $admin['username'] . ' Disabled customer ' . $c['username'], 'User', $id_customer);
        Message::sendTelegram('Admin ' . $admin['username'] . ' Disabled customer u' . $c['username']);
        r2(getUrl('customers/view/') . $id_customer, 's', Lang::T('Customer disabled'));
        break;
    case 'extend':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_customer = isset($routes['2']) ? intval($routes['2']) : 0;
        $recharge_id = isset($routes['3']) ? intval($routes['3']) : 0;
        $days = isset($routes['4']) ? intval($routes['4']) : 0;
        if ($id_customer <= 0 || $recharge_id <= 0 || $days < 1) {
            r2(getUrl('customers/view/') . max($id_customer, 1), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $b = ORM::for_table('tbl_user_recharges')
            ->where('id', $recharge_id)
            ->where('customer_id', $id_customer)
            ->find_one();
        if (!$b) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Cannot find active plan'));
        }
        $c = ORM::for_table('tbl_customers')->find_one($id_customer);
        $p = ORM::for_table('tbl_plans')->where('id', $b['plan_id'])->find_one();
        if (!$c || !$p) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Data Not Found'));
        }
        $expiryTs = strtotime(trim($b['expiration'] . ' ' . $b['time']));
        if ($expiryTs !== false && $expiryTs > time()) {
            $expiration = date('Y-m-d', strtotime($b['expiration'] . " +{$days} day"));
            $expTime = $b['time'];
        } else {
            $expiration = date('Y-m-d', strtotime("+{$days} day"));
            $expTime = date('H:i:s');
        }
        if ($_app_stage != 'demo') {
            $dvc = Package::getDevice($p);
            if (file_exists($dvc)) {
                require_once $dvc;
                global $isChangePlan;
                $isChangePlan = true;
                (new $p['device'])->add_customer($c, $p);
            } else {
                throw new Exception(Lang::T("Devices Not Found"));
            }
        }
        $b->expiration = $expiration;
        $b->time = $expTime;
        $b->status = 'on';
        $b->save();
        if ($c['status'] != 'Active') {
            $c->status = 'Active';
            $c->save();
        }
        _log("Admin {$admin['username']} extend Customer {$c['username']} #{$id_customer} for {$days} days until {$expiration}", 'User', $id_customer);
        Message::sendTelegram("#u{$b['username']} #id{$id_customer} #extend by {$admin['fullname']} #{$p['type']}\n{$p['name_plan']}\nNew Expired: " . Lang::dateAndTimeFormat($expiration, $expTime));
        r2(getUrl('customers/view/') . $id_customer, 's', Lang::T('Extended until') . ' ' . Lang::dateAndTimeFormat($expiration, $expTime));
        break;
    case 'sync':
        $id_customer = isset($routes['2']) ? (is_numeric($routes['2']) ? intval($routes['2']) : $routes['2']) : 0;
        if (empty($id_customer)) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        
        // Convert username to customer ID if needed (minimal fix for sync issue)
        if (!is_numeric($id_customer)) {
            $customer_lookup = ORM::for_table('tbl_customers')->where('username', $id_customer)->find_one();
            if ($customer_lookup) {
                $actual_customer_id = $customer_lookup->id;
            } else {
                r2(getUrl('customers/view/') . $id_customer, 'e', 'Customer not found');
                break;
            }
        } else {
            $actual_customer_id = $id_customer;
        }
        
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id_customer, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        
        // Use original PHPNuxBill sync approach: sync ALL plans with status 'on'
        $bs = ORM::for_table('tbl_user_recharges')->where('customer_id', $actual_customer_id)->where('status', 'on')->findMany();
        if ($bs) {
            $routers = [];
            foreach ($bs as $b) {
                $c = ORM::for_table('tbl_customers')->find_one($actual_customer_id);
                $p = ORM::for_table('tbl_plans')->where('id', $b['plan_id'])->find_one();
                if ($p) {
                    $routers[] = $b['routers'];
                    $dvc = Package::getDevice($p);
                    if ($_app_stage != 'demo') {
                        if (file_exists($dvc)) {
                            require_once $dvc;
                            // Try sync_customer method first, then fallback to add_customer (original behavior)
                            if (method_exists($dvc, 'sync_customer')) {
                                (new $p['device'])->sync_customer($c, $p);
                            } else {
                                (new $p['device'])->add_customer($c, $p);
                            }
                        } else {
                            throw new Exception(Lang::T("Devices Not Found"));
                        }
                    }
                }
            }
            r2(getUrl('customers/view/') . $id_customer, 's', 'Sync success to ' . implode(", ", $routers));
        }
        r2(getUrl('customers/view/') . $id_customer, 'e', 'Cannot find active plan');
        break;
    case 'login':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $customer = ORM::for_table('tbl_customers')->find_one($id);
        if ($customer) {
            $_SESSION['uid'] = $id;
            User::setCookie($id);
            _alert("You are logging in as $customer[fullname],<br>don't logout just close tab.", 'info', "home", 10);
        }
        _alert(Lang::T('Customer not found'), 'danger', "customers");
        break;
    case 'viewu':
        // View customer by username - redirect to view by ID
        $username = isset($routes['2']) ? trim($routes['2']) : '';
        if (empty($username)) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if (!$customer) {
            r2(getUrl('customers/list'), 'e', Lang::T('Account Not Found'));
        }
        // Redirect to regular view with customer ID
        $section = isset($routes['3']) ? $routes['3'] : 'activation';
        r2(getUrl('customers/view/' . $customer['id'] . '/' . $section), 's', '');
        break;
        
    case 'view':
        // Load customer first
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $customer_obj = ORM::for_table('tbl_customers')->find_one($id);
        
        if (!$customer_obj) {
            r2(getUrl('customers/list'), 'e', Lang::T('Account Not Found'));
        }
        
        // Convert ORM object to array for easier manipulation
        $customer = $customer_obj->as_array();
        
        run_hook('view_customer'); #HOOK
        
        // --- Active Package Status & Time Info ---
        $active_package = null;
        $active_since = '';
        $time_remaining = '';
        $package_status = 'offline';
        $now = time();
        // Find latest active package (status 'on', not expired)
        $packages = User::_billing($customer['id']);
            foreach ($packages as $pkg) {
                // Check if package is active (status 'on' and not expired)
                // Handle both date+time and datetime formats with proper timezone
                $expiry_string = trim($pkg['expiration'] . ' ' . $pkg['time']);
                
                // Use DateTime for better timezone handling
                try {
                    $expiry_dt = new DateTime($expiry_string);
                    $now_dt = new DateTime();
                    $expiry_ts = $expiry_dt->getTimestamp();
                    $now_ts = $now_dt->getTimestamp();
                } catch (Exception $e) {
                    // Fallback to strtotime if DateTime fails
                    $expiry_ts = strtotime($expiry_string);
                    $now_ts = time();
                }
                
                // Debug logging for troubleshooting
                error_log("DEBUG: Package {$pkg['namebp']} - Status: {$pkg['status']}, Expiry: '$expiry_string', ExpTS: $expiry_ts, NowTS: $now_ts, Diff: " . ($expiry_ts - $now_ts) . "s");
                
                if ($pkg['status'] == 'on' && $expiry_ts !== false && $expiry_ts > $now_ts) {
                    $active_package = $pkg;
                    break;
                }
            }
            if ($active_package) {
                // Active since = recharged_on + recharged_time
                $active_since_ts = strtotime($active_package['recharged_on'] . ' ' . $active_package['recharged_time']);
                $active_since = date('d M Y H:i', $active_since_ts);
                // Time remaining = expiration + time - now
                $expiry_ts = strtotime($active_package['expiration'] . ' ' . $active_package['time']);
                $seconds_left = $expiry_ts - $now;
                if ($seconds_left > 0) {
                    $days = floor($seconds_left / 86400);
                    $hours = floor(($seconds_left % 86400) / 3600);
                    $minutes = floor(($seconds_left % 3600) / 60);
                    $time_remaining = ($days > 0 ? $days.'d ' : '') . ($hours > 0 ? $hours.'h ' : '') . $minutes.'m';
                } else {
                    $time_remaining = 'Expired';
                }
            } else {
                if (!empty($packages) && isset($packages[0])) {
                    $recent_pkg = $packages[0];
                    $active_package = $recent_pkg;
                    $active_since_ts = strtotime($recent_pkg['recharged_on'] . ' ' . $recent_pkg['recharged_time']);
                    $active_since = date('d M Y H:i', $active_since_ts);
                    $time_remaining = 'Expired';
                }
            }
            $liveConn = PamnetCustomerStatus::forCustomer((int) $customer['id'], $customer['username']);
            $package_status = $liveConn['status'];
            // Keep package display when live helper found a plan name
            if (!$active_package && !empty($packages) && isset($packages[0])) {
                $active_package = $packages[0];
            }
        $customer['active_package'] = $active_package;
        $customer['active_since'] = $active_since;
        $customer['time_remaining'] = $time_remaining;
        $customer['package_status'] = $package_status;
        
        // Get real-time user session data
        $userSession = getUserLiveSession($customer['username']);
        
        // Fetch the Customers Attributes values from the tbl_customers_fields table
        $customFields = ORM::for_table('tbl_customers_fields')
            ->where('customer_id', $customer['id'])
            ->find_many();
        
        $v = isset($routes['3']) ? $routes['3'] : 'activation';
        
        // Initialize both variables to empty arrays to prevent undefined variable errors
        $activation = [];
        $order = [];
        
        switch ($v) {
            case 'order':
                $v = 'order';
                $query = ORM::for_table('tbl_payment_gateway')->where('user_id', $customer['id'])->order_by_desc('id');
                $order = Paginator::findMany($query);
                if (empty($order) || count($order) < 5) {
                    $query = ORM::for_table('tbl_payment_gateway')->where('username', $customer['username'])->order_by_desc('id');
                    $order = Paginator::findMany($query);
                }
                break;
            case 'activation':
            default:
                $query = ORM::for_table('tbl_transactions')->where('user_id', $customer['id'])->order_by_desc('id');
                $activation = Paginator::findMany($query);
                if (empty($activation) || count($activation) < 5) {
                    $query = ORM::for_table('tbl_transactions')->where('username', $customer['username'])->order_by_desc('id');
                    $activation = Paginator::findMany($query);
                }
                break;
        }
        
        // Assign both variables to the template
        $ui->assign('activation', $activation);
        $ui->assign('order', $order);
        
        // --- Data Usage Section (like customer_usage.php) ---
        $username = $customer['username'];
        // Current session: get latest from tbl_usage_sessions
        $session = ORM::for_table('tbl_usage_sessions')
            ->where('username', $username)
            ->order_by_desc('start_time')
            ->find_one();
        if ($session) {
            $customer['current_session_upload'] = isset($session->session_tx) ? (int)$session->session_tx : 0;
            $customer['current_session_download'] = isset($session->session_rx) ? (int)$session->session_rx : 0;
        } else {
            $customer['current_session_upload'] = 0;
            $customer['current_session_download'] = 0;
        }
        
        // Total data used: sum all tx_bytes and rx_bytes from tbl_usage_records
        $total_upload = ORM::for_table('tbl_usage_records')
            ->where('username', $username)
            ->sum('tx_bytes');
        $total_download = ORM::for_table('tbl_usage_records')
            ->where('username', $username)
            ->sum('rx_bytes');
        $customer['total_data_used'] = ($total_upload ? (int)$total_upload : 0) + ($total_download ? (int)$total_download : 0);
        
        // Format for display (reuse formatBytes from plugin if available)
        if (function_exists('formatBytes')) {
            $customer['current_session_upload_formatted'] = formatBytes($customer['current_session_upload']);
            $customer['current_session_download_formatted'] = formatBytes($customer['current_session_download']);
            $customer['total_data_used_formatted'] = formatBytes($customer['total_data_used']);
        } else {
            $customer['current_session_upload_formatted'] = $customer['current_session_upload'];
            $customer['current_session_download_formatted'] = $customer['current_session_download'];
            $customer['total_data_used_formatted'] = $customer['total_data_used'];
        }
        
        // --- End Data Usage Section ---
        $ui->assign('packages', User::_billing($customer['id']));
        $ui->assign('userSession', $userSession);
        $ui->assign('v', $v);
        $ui->assign('d', $customer);
        $ui->assign('customFields', $customFields);
        $ui->assign('xheader', $leafletpickerHeader);
        $ui->assign('csrf_token',  Csrf::generateAndStoreToken());
        $ui->display('admin/customers/view.tpl');
        break;
    case 'edit':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        run_hook('edit_customer'); #HOOK
        $d = ORM::for_table('tbl_customers')->find_one($id);
        // Fetch the Customers Attributes values from the tbl_customers_fields table
        $customFields = ORM::for_table('tbl_customers_fields')
            ->where('customer_id', $id)
            ->find_many();
        if ($d) {
            if (isset($routes['3']) && $routes['3'] == 'deletePhoto') {
                if ($d['photo'] != '' && strpos($d['photo'], 'default') === false) {
                    if (file_exists($UPLOAD_PATH . $d['photo']) && strpos($d['photo'], 'default') === false) {
                        unlink($UPLOAD_PATH . $d['photo']);
                        if (file_exists($UPLOAD_PATH . $d['photo'] . '.thumb.jpg')) {
                            unlink($UPLOAD_PATH . $d['photo'] . '.thumb.jpg');
                        }
                    }
                    $d->photo = '/user.default.jpg';
                    $d->save();
                    $ui->assign('notify_t', 's');
                    $ui->assign('notify', 'You have successfully deleted the photo');
                } else {
                    $ui->assign('notify_t', 'e');
                    $ui->assign('notify', 'No photo found to delete');
                }
            }
            $ui->assign('d', $d);
            $ui->assign('statuses', ORM::for_table('tbl_customers')->getEnum("status"));
            $ui->assign('customFields', $customFields);
            $ui->assign('xheader', $leafletpickerHeader);
            $ui->assign('csrf_token',  Csrf::generateAndStoreToken());
            $ui->display('admin/customers/edit.tpl');
        } else {
            r2(getUrl('customers/list'), 'e', Lang::T('Account Not Found'));
        }
        break;

    case 'delete':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/view/') . $id, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        run_hook('delete_customer'); #HOOK
        $c = ORM::for_table('tbl_customers')->find_one($id);
        if ($c) {
            // Delete the associated Customers Attributes records from tbl_customer_custom_fields table
            ORM::for_table('tbl_customers_fields')->where('customer_id', $id)->delete_many();
            //Delete active package
            $turs = ORM::for_table('tbl_user_recharges')->where('username', $c['username'])->find_many();
            foreach ($turs as $tur) {
                $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
                if ($p) {
                    $dvc = Package::getDevice($p);
                    if ($_app_stage != 'demo') {
                        if (file_exists($dvc)) {
                            require_once $dvc;
                            $p['plan_expired'] = 0;
                            (new $p['device'])->remove_customer($c, $p);
                        } else {
                            throw new Exception(Lang::T("Devices Not Found"));
                        }
                    }
                }
                try {
                    $tur->delete();
                } catch (Exception $e) {
                    // Log the error but continue
                    _log('Error deleting user recharge: ' . $e->getMessage(), 'Admin', $admin['id']);
                }
            }
            try {
                $c->delete();
                r2(getUrl('customers/list'), 's', Lang::T('User deleted Successfully'));
            } catch (Exception $e) {
                _log('Error deleting customer: ' . $e->getMessage(), 'Admin', $admin['id']);
                r2(getUrl('customers/list'), 'e', Lang::T('Error deleting customer') . ': ' . $e->getMessage());
            }
        } else {
            r2(getUrl('customers/list'), 'e', Lang::T('Customer not found'));
        }
        break;
    
    case 'multi-delete':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        
        $csrf_token = _post('csrf_token');
        if (!Csrf::check($csrf_token)) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('Invalid or Expired CSRF Token')]);
            exit;
        }
        
        $customer_ids = json_decode(_post('customer_ids'), true);
        
        if (empty($customer_ids) || !is_array($customer_ids)) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('No customers selected')]);
            exit;
        }
        
        $deleted_count = 0;
        $failed_count = 0;
        
        foreach ($customer_ids as $id) {
            $c = ORM::for_table('tbl_customers')->find_one($id);
            if ($c) {
                $username = $c['username'];
                try {
                    // Delete associated records
                    ORM::for_table('tbl_customers_fields')->where('customer_id', $id)->delete_many();
                    
                    // Delete active packages
                    $turs = ORM::for_table('tbl_user_recharges')->where('username', $username)->find_many();
                    foreach ($turs as $tur) {
                        $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
                        if ($p) {
                            $dvc = Package::getDevice($p);
                            if ($_app_stage != 'demo') {
                                if (file_exists($dvc)) {
                                    require_once $dvc;
                                    $p['plan_expired'] = 0;
                                    (new $p['device'])->remove_customer($c, $p);
                                }
                            }
                        }
                        $tur->delete();
                    }
                    
                    $c->delete();
                    $deleted_count++;
                    _log('Customer deleted (multi-delete): ' . $username, 'Admin', $admin['id']);
                } catch (Exception $e) {
                    $failed_count++;
                    _log('Error deleting customer (multi-delete) ID ' . $id . ': ' . $e->getMessage(), 'Admin', $admin['id']);
                }
            } else {
                $failed_count++;
            }
        }
        
        $message = "$deleted_count " . Lang::T('customers deleted successfully');
        if ($failed_count > 0) {
            $message .= ", $failed_count " . Lang::T('failed');
        }
        
        echo json_encode(['status' => 'success', 'message' => $message, 'deleted' => $deleted_count, 'failed' => $failed_count]);
        exit;
        break;
    
    case 'send-sms':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        
        $csrf_token = _post('csrf_token');
        if (!Csrf::check($csrf_token)) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('Invalid or Expired CSRF Token')]);
            exit;
        }
        
        $customer_ids = json_decode(_post('customer_ids'), true);
        $message = _post('sms_message');
        
        if (empty($customer_ids) || !is_array($customer_ids)) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('No customers selected')]);
            exit;
        }
        
        if (empty($message)) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('SMS message cannot be empty')]);
            exit;
        }
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($customer_ids as $id) {
            $c = ORM::for_table('tbl_customers')->find_one($id);
            if ($c && !empty($c['phonenumber'])) {
                try {
                    // Send SMS using the Message class
                    $result = Message::sendSMS($c['phonenumber'], $message);
                    
                    if ($result) {
                        $sent_count++;
                        _log('SMS sent to ' . $c['username'] . ' (' . $c['phonenumber'] . ')', 'Admin', $admin['id']);
                    } else {
                        $failed_count++;
                        _log('Failed to send SMS to ' . $c['username'] . ' (' . $c['phonenumber'] . ')', 'Admin', $admin['id']);
                    }
                } catch (Exception $e) {
                    $failed_count++;
                    _log('Error sending SMS to ' . $c['username'] . ': ' . $e->getMessage(), 'Admin', $admin['id']);
                }
            } else {
                $failed_count++;
            }
        }
        
        $message_text = "$sent_count " . Lang::T('SMS sent successfully');
        if ($failed_count > 0) {
            $message_text .= ", $failed_count " . Lang::T('failed');
        }
        
        echo json_encode(['status' => 'success', 'message' => $message_text, 'sent' => $sent_count, 'failed' => $failed_count]);
        exit;
        break;
    
    case 'revert-activation':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        
        $activation_id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($activation_id <= 0) {
            r2(getUrl('customers/list'), 'e', Lang::T('Invalid request'));
        }
        $csrf_token = _req('token');
        
        if (!Csrf::check($csrf_token)) {
            r2(U . 'customers/list', 'e', Lang::T('Invalid or Expired CSRF Token'));
        }
        
        $activation = ORM::for_table('tbl_user_recharges')->find_one($activation_id);
        
        if (!$activation) {
            r2(U . 'customers/list', 'e', Lang::T('Activation not found'));
        }
        
        try {
            $customer = ORM::for_table('tbl_customers')->where('username', $activation['username'])->find_one();
            
            if (!$customer) {
                r2(U . 'customers/list', 'e', Lang::T('Customer not found'));
            }
            
            // Get the plan details
            $plan = ORM::for_table('tbl_plans')->find_one($activation['plan_id']);
            
            if ($plan) {
                // Remove from router
                $dvc = Package::getDevice($plan);
                if ($_app_stage != 'demo') {
                    if (file_exists($dvc)) {
                        require_once $dvc;
                        $plan['plan_expired'] = 0;
                        (new $plan['device'])->remove_customer($customer, $plan);
                    }
                }
            }
            
            // Refund if paid
            if ($activation['price'] > 0) {
                $customer['balance'] = $customer['balance'] + $activation['price'];
                $customer->save();
            }
            
            // Delete the activation record
            $activation->delete();
            
            // Log the revert action
            _log('Activation reverted for ' . $customer['username'] . ' - Plan: ' . $activation['plan_name'], 'Admin', $admin['id']);
            
            r2(U . 'customers/view/' . $customer['id'], 's', Lang::T('Activation reverted successfully. Balance refunded if applicable.'));
            
        } catch (Exception $e) {
            _log('Error reverting activation: ' . $e->getMessage(), 'Admin', $admin['id']);
            r2(U . 'customers/list', 'e', Lang::T('Error reverting activation') . ': ' . $e->getMessage());
        }
        break;

    case 'add-post':

        $csrf_token = _post('csrf_token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/add'), 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $username = alphanumeric(_post('username'), ":+_.@-");
        $fullname = _post('fullname');
        $password = trim(_post('password'));
        $pppoe_username = trim(_post('pppoe_username'));
        $pppoe_password = trim(_post('pppoe_password'));
        $pppoe_ip = trim(_post('pppoe_ip'));
        $email = _post('email');
        $address = _post('address');
        $phonenumber = _post('phonenumber');
        $service_type = _post('service_type');
        $account_type = _post('account_type');
        //post Customers Attributes
        $custom_field_names = isset($_POST['custom_field_name']) ? (array) $_POST['custom_field_name'] : [];
        $custom_field_values = isset($_POST['custom_field_value']) ? (array) $_POST['custom_field_value'] : [];
        //additional information
        $city = _post('city');
        $district = _post('district');
        $state = _post('state');
        $zip = _post('zip');

        run_hook('add_customer'); #HOOK
        $msg = '';
        if (Validator::Length($username, 55, 2) == false) {
            $msg .= 'Username should be between 3 to 54 characters' . '<br>';
        }
        if (Validator::Length($fullname, 36, 1) == false) {
            $msg .= 'Full Name should be between 2 to 25 characters' . '<br>';
        }
        if (!Validator::Length($password, 36, 2)) {
            $msg .= 'Password should be between 3 to 35 characters' . '<br>';
        }

        $d = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if ($d) {
            $msg .= Lang::T('Account already axist') . '<br>';
        }
        if ($msg == '') {
            $d = ORM::for_table('tbl_customers')->create();
            $d->username = $username;
            $d->password = $password;
            $d->pppoe_username = $pppoe_username;
            $d->pppoe_password = $pppoe_password;
            $d->pppoe_ip = $pppoe_ip;
            $d->email = $email;
            $d->account_type = $account_type;
            $d->fullname = $fullname;
            $d->address = $address;
            $d->created_by = $admin['id'];
            $d->phonenumber = Lang::phoneFormat($phonenumber);
            $d->service_type = $service_type;
            $d->city = $city;
            $d->district = $district;
            $d->state = $state;
            $d->zip = $zip;
            $d->save();

            // Retrieve the customer ID of the newly created customer
            $customerId = $d->id();
            // Save Customers Attributes details
            if (!empty($custom_field_names) && !empty($custom_field_values)) {
                $totalFields = min(count($custom_field_names), count($custom_field_values));
                for ($i = 0; $i < $totalFields; $i++) {
                    $name = $custom_field_names[$i];
                    $value = $custom_field_values[$i];

                    if (!empty($name)) {
                        $customField = ORM::for_table('tbl_customers_fields')->create();
                        $customField->customer_id = $customerId;
                        $customField->field_name = $name;
                        $customField->field_value = $value;
                        $customField->save();
                    }
                }
            }

            // Send welcome message
            if (isset($_POST['send_welcome_message']) && $_POST['send_welcome_message'] == true) {
                $serviceType = $d['service_type'] ?? ($_POST['service_type'] ?? '');
                $welcomeMessage = '';
                if (strtoupper((string) $serviceType) === 'PPPOE') {
                    $welcomeMessage = Lang::getNotifText('welcome_message_pppoe');
                }
                if ($welcomeMessage === '' || $welcomeMessage === null) {
                    $welcomeMessage = Lang::getNotifText('welcome_message');
                }
                $welcomeMessage = Message::getMessageType(
                    (strtoupper((string) $serviceType) === 'PPPOE') ? 'PPPOE' : 'Hotspot',
                    (string) $welcomeMessage
                );
                $welcomeMessage = str_replace('[[company]]', $config['CompanyName'], $welcomeMessage);
                $welcomeMessage = str_replace('[[company_name]]', $config['CompanyName'], $welcomeMessage);
                $welcomeMessage = str_replace('[[name]]', $d['fullname'], $welcomeMessage);
                $welcomeMessage = str_replace('[[username]]', $d['username'], $welcomeMessage);
                $welcomeMessage = str_replace('[[Username]]', $d['username'], $welcomeMessage);
                $welcomeMessage = str_replace('[[password]]', $d['password'], $welcomeMessage);
                $welcomeMessage = str_replace('[[Password]]', $d['password'], $welcomeMessage);
                $welcomeMessage = str_replace('[[url]]', APP_URL . '/?_route=login', $welcomeMessage);
                $welcomeMessage = str_replace('[ACCOUNT NUMBER]', $d['username'], $welcomeMessage);

                $emailSubject = "Welcome to " . $config['CompanyName'];

                $channels = [
                    'sms' => [
                        'enabled' => isset($_POST['sms']),
                        'method' => 'sendSMS',
                        'args' => [$d['phonenumber'], $welcomeMessage]
                    ],
                    'whatsapp' => [
                        'enabled' => isset($_POST['wa']),
                        'method' => 'sendWhatsapp',
                        'args' => [$d['phonenumber'], $welcomeMessage]
                    ],
                    'email' => [
                        'enabled' => isset($_POST['mail']),
                        'method' => 'Message::sendEmail',
                        'args' => [$d['email'], $emailSubject, $welcomeMessage, $d['email']]
                    ]
                ];

                foreach ($channels as $channel => $message) {
                    if ($message['enabled']) {
                        try {
                            call_user_func_array($message['method'], $message['args']);
                        } catch (Exception $e) {
                            // Log the error and handle the failure
                            _log("Failed to send welcome message via $channel: " . $e->getMessage());
                        }
                    }
                }
            }
            r2(getUrl('customers/list'), 's', Lang::T('Account Created Successfully'));
        } else {
            r2(getUrl('customers/add'), 'e', $msg);
        }
        break;

    case 'edit-post':
        $id = _post('id');
        $csrf_token = _post('csrf_token');
        if (!Csrf::check($csrf_token)) {
            r2(getUrl('customers/edit/') . $id, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        }
        $username = alphanumeric(_post('username'), ":+_.@-");
        $fullname = _post('fullname');
        $account_type = _post('account_type');
        $password = trim(_post('password'));
        $pppoe_username = trim(_post('pppoe_username'));
        $pppoe_password = trim(_post('pppoe_password'));
        $pppoe_ip = trim(_post('pppoe_ip'));
        $email = _post('email');
        $address = _post('address');
        $phonenumber = Lang::phoneFormat(_post('phonenumber'));
        $service_type = _post('service_type');
        $status = _post('status');
        //additional information
        $city = _post('city');
        $district = _post('district');
        $state = _post('state');
        $zip = _post('zip');
        run_hook('edit_customer'); #HOOK
        $msg = '';
        if (Validator::Length($username, 55, 2) == false) {
            $msg .= 'Username should be between 3 to 54 characters' . '<br>';
        }
        if (Validator::Length($fullname, 36, 1) == false) {
            $msg .= 'Full Name should be between 2 to 25 characters' . '<br>';
        }

        $c = ORM::for_table('tbl_customers')->find_one($id);

        if (!$c) {
            $msg .= Lang::T('Data Not Found') . '<br>';
        }

        //lets find user Customers Attributes using id
        $customFields = ORM::for_table('tbl_customers_fields')
            ->where('customer_id', $id)
            ->find_many();

        $oldusername = $c['username'];
        $oldPppoeUsername = $c['pppoe_username'];
        $oldPppoePassword = $c['pppoe_password'];
        $oldPppoeIp = $c['pppoe_ip'];
        $oldPassPassword = $c['password'];
        $userDiff = false;
        $pppoeDiff = false;
        $passDiff = false;
        $pppoeIpDiff = false;
        if ($oldusername != $username) {
            if (ORM::for_table('tbl_customers')->where('username', $username)->find_one()) {
                $msg .= Lang::T('Username already used by another customer') . '<br>';
            }
            if (ORM::for_table('tbl_customers')->where('pppoe_username', $username)->find_one()) {
                $msg .= Lang::T('Username already used by another pppoe username customer') . '<br>';
            }
            $userDiff = true;
        }
        if ($oldPppoeUsername != $pppoe_username) {
            // if(!empty($pppoe_username)){
            //     if(ORM::for_table('tbl_customers')->where('pppoe_username', $pppoe_username)->find_one()){
            //         $msg.= Lang::T('PPPoE Username already used by another customer') . '<br>';
            //     }
            //     if(ORM::for_table('tbl_customers')->where('username', $pppoe_username)->find_one()){
            //         $msg.= Lang::T('PPPoE Username already used by another customer') . '<br>';
            //     }
            // }
            $pppoeDiff = true;
        }

        if ($oldPppoeIp != $pppoe_ip) {
            $pppoeIpDiff = true;
        }
        if ($password != '' && $oldPassPassword != $password) {
            $passDiff = true;
        }

        if ($msg == '') {
            if (!empty($_FILES['photo']['name']) && file_exists($_FILES['photo']['tmp_name'])) {
                if (function_exists('imagecreatetruecolor')) {
                    $hash = md5_file($_FILES['photo']['tmp_name']);
                    $subfolder = substr($hash, 0, 2);
                    $folder = $UPLOAD_PATH . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR;
                    if (!file_exists($folder)) {
                        mkdir($folder);
                    }
                    $folder = $UPLOAD_PATH . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR;
                    if (!file_exists($folder)) {
                        mkdir($folder);
                    }
                    $imgPath = $folder . $hash . '.jpg';
                    if (!file_exists($imgPath)) {
                        File::resizeCropImage($_FILES['photo']['tmp_name'], $imgPath, 1600, 1600, 100);
                    }
                    if (!file_exists($imgPath . '.thumb.jpg')) {
                        if (_post('faceDetect') == 'yes') {
                            try {
                                $detector = new svay\FaceDetector();
                                $detector->setTimeout(5000);
                                $detector->faceDetect($imgPath);
                                $detector->cropFaceToJpeg($imgPath . '.thumb.jpg', false);
                            } catch (Exception $e) {
                                File::makeThumb($imgPath, $imgPath . '.thumb.jpg', 200);
                            } catch (Throwable $e) {
                                File::makeThumb($imgPath, $imgPath . '.thumb.jpg', 200);
                            }
                        } else {
                            File::makeThumb($imgPath, $imgPath . '.thumb.jpg', 200);
                        }
                    }
                    if (file_exists($imgPath)) {
                        if ($c['photo'] != '' && strpos($c['photo'], 'default') === false) {
                            if (file_exists($UPLOAD_PATH . $c['photo'])) {
                                unlink($UPLOAD_PATH . $c['photo']);
                                if (file_exists($UPLOAD_PATH . $c['photo'] . '.thumb.jpg')) {
                                    unlink($UPLOAD_PATH . $c['photo'] . '.thumb.jpg');
                                }
                            }
                        }
                        $c->photo = '/photos/' . $subfolder . '/' . $hash . '.jpg';
                    }
                    if (file_exists($_FILES['photo']['tmp_name'])) unlink($_FILES['photo']['tmp_name']);
                } else {
                    r2(getUrl('settings/app'), 'e', 'PHP GD is not installed');
                }
            }
            if ($userDiff) {
                $c->username = $username;
            }
            if ($password != '') {
                $c->password = $password;
            }
            $c->pppoe_username = $pppoe_username;
            $c->pppoe_password = $pppoe_password;
            $c->pppoe_ip = $pppoe_ip;
            $c->fullname = $fullname;
            $c->email = $email;
            $c->account_type = $account_type;
            $c->address = $address;
            $c->status = $status;
            $c->phonenumber = $phonenumber;
            $c->service_type = $service_type;
            $c->city = $city;
            $c->district = $district;
            $c->state = $state;
            $c->zip = $zip;
            $c->save();


            // Update Customers Attributes values in tbl_customers_fields table
            foreach ($customFields as $customField) {
                $fieldName = $customField['field_name'];
                if (isset($_POST['custom_fields'][$fieldName])) {
                    $customFieldValue = _post('custom_fields')[$fieldName] ?? '';
                    $customField->set('field_value', $customFieldValue);
                    $customField->save();
                }
            }

            // Custom fields functionality removed

            if ($userDiff || $pppoeDiff || $pppoeIpDiff || $passDiff) {
                $turs = ORM::for_table('tbl_user_recharges')->where('customer_id', $c['id'])->findMany();
                foreach ($turs as $tur) {
                    $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
                    $dvc = Package::getDevice($p);
                    if ($_app_stage != 'demo') {
                        // if has active package
                        if ($tur['status'] == 'on') {
                            if (file_exists($dvc)) {
                                require_once $dvc;
                                if ($userDiff) {
                                    (new $p['device'])->change_username($p, $oldusername, $username);
                                }
                                if ($pppoeDiff && $tur['type'] == 'PPPOE') {
                                    if (empty($oldPppoeUsername) && !empty($pppoe_username)) {
                                        // admin just add pppoe username
                                        (new $p['device'])->change_username($p, $username, $pppoe_username);
                                    } else if (empty($pppoe_username) && !empty($oldPppoeUsername)) {
                                        // admin want to use customer username
                                        (new $p['device'])->change_username($p, $oldPppoeUsername, $username);
                                    } else {
                                        // regular change pppoe username
                                        (new $p['device'])->change_username($p, $oldPppoeUsername, $pppoe_username);
                                    }
                                }
                                (new $p['device'])->add_customer($c, $p);
                            } else {
                                throw new Exception(Lang::T("Devices Not Found"));
                            }
                        }
                    }
                    $tur->username = $username;
                    $tur->save();
                }
            }
            r2(getUrl('customers/view/') . $id, 's', 'User Updated Successfully');
        } else {
            r2(getUrl('customers/edit/') . $id, 'e', $msg);
        }
        break;

    default:
        run_hook('list_customers'); #HOOK
        $search = _req('search');
        $order = _req('order', 'username');
        $filter = _req('filter', 'Active');
        $orderby = _req('orderby', 'asc');
        $order_pos = [
            'username' => 0,
            'created_at' => 8,
            'balance' => 3,
            'status' => 7
        ];

        $append_url = "&order=" . urlencode($order) . "&filter=" . urlencode($filter) . "&orderby=" . urlencode($orderby);

        if ($search != '') {
            $query = ORM::for_table('tbl_customers')
                ->whereRaw("(username LIKE '%$search%' OR fullname LIKE '%$search%' OR address LIKE '%$search%' " .
                    "OR phonenumber LIKE '%$search%' OR email LIKE '%$search%') AND status='$filter'");
        } else {
            $query = ORM::for_table('tbl_customers');
            $query->where("status", $filter);
        }
        if ($order == 'lastname') {
            $query->order_by_expr("SUBSTR(fullname, INSTR(fullname, ' ')) $orderby");
        } else {
            if ($orderby == 'asc') {
                $query->order_by_asc($order);
            } else {
                $query->order_by_desc($order);
            }
        }
        if (_post('export', '') == 'csv') {
            $csrf_token = _post('csrf_token');
            if (!Csrf::check($csrf_token)) {
                r2(getUrl('customers'), 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
            }
            $d = $query->findMany();
            $h = false;
            set_time_limit(-1);
            header('Pragma: public');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header("Content-type: text/csv");
            header('Content-Disposition: attachment;filename="phpnuxbill_customers_' . $filter . '_' . date('Y-m-d_H_i') . '.csv"');
            header('Content-Transfer-Encoding: binary');

            $headers = [
                'id',
                'username',
                'fullname',
                'address',
                'phonenumber',
                'email',
                'balance',
                'service_type',
            ];
            $fp = fopen('php://output', 'wb');
            if (!$h) {
                fputcsv($fp, $headers, ";");
                $h = true;
            }
            foreach ($d as $c) {
                $row = [
                    $c['id'],
                    $c['username'],
                    $c['fullname'],
                    str_replace("\n", " ", $c['address']),
                    $c['phonenumber'],
                    $c['email'],
                    $c['balance'],
                    $c['service_type'],
                ];
                fputcsv($fp, $row, ";");
            }
            fclose($fp);
            die();
        }
        $d = Paginator::findMany($query, ['search' => $search], 30, $append_url);
        $connStatuses = [];
        try {
            $rows = [];
            foreach ($d as $row) {
                $rows[] = ['id' => $row['id'], 'username' => $row['username']];
            }
            $connStatuses = PamnetCustomerStatus::forCustomers($rows);
        } catch (Throwable $e) {
            $connStatuses = [];
        }
        $ui->assign('d', $d);
        $ui->assign('conn_statuses', $connStatuses);
        $ui->assign('statuses', ORM::for_table('tbl_customers')->getEnum("status"));
        $ui->assign('filter', $filter);
        $ui->assign('search', $search);
        $ui->assign('order', $order);
        $ui->assign('order_pos', $order_pos[$order]);
        $ui->assign('orderby', $orderby);
        $ui->assign('csrf_token',  Csrf::generateAndStoreToken());
        $ui->display('admin/customers/list.tpl');
        break;
}