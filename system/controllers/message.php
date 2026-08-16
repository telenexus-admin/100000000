<?php

 

_admin();
$ui->assign('_title', Lang::T('Send Message'));
$ui->assign('_system_menu', 'message');

$action = $routes['1'];
$ui->assign('_admin', $admin);

if (empty($action)) {
    $action = 'send';
}

switch ($action) {
    case 'send':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        $appUrl = APP_URL;

        $select2_customer = <<<EOT
<script>
document.addEventListener("DOMContentLoaded", function(event) {
    $('#personSelect').select2({
        theme: "bootstrap",
        ajax: {
            url: function(params) {
                if(params.term != undefined){
                    return '{$appUrl}/?_route=autoload/customer_select2&s='+params.term;
                }else{
                    return '{$appUrl}/?_route=autoload/customer_select2';
                }
            }
        }
    });
});
</script>
EOT;
        if (isset($routes['2']) && !empty($routes['2'])) {
            $ui->assign('cust', ORM::for_table('tbl_customers')->find_one($routes['2']));
        }
        $id = $routes['2'];
        $ui->assign('id', $id);
        $ui->assign('xfooter', $select2_customer);
        $ui->display('admin/message/single.tpl');
        break;

    case 'send-post':
        // Check user permissions
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        // Get form data
        $id_customer = $_POST['id_customer'];
        $message = $_POST['message'];
        $via = $_POST['via'];

        // Check if fields are empty
        if ($id_customer == '' or $message == '' or $via == '') {
            r2(getUrl('message/send'), 'e', Lang::T('All field is required'));
        } else {
            // Get customer details from the database
            $c = ORM::for_table('tbl_customers')->find_one($id_customer);
            
            // Get customer's active package/recharge info
            $recharge = ORM::for_table('tbl_user_recharges')
                ->where('customer_id', $id_customer)
                ->where('status', 'on')
                ->find_one();
            
            // Get invoice info - check which table exists
            $invoice = null;
            $invoice_number = '';
            $date = date('Y-m-d H:i:s');
            $payment_gateway = '';
            $payment_channel = '';
            
            // Try to find invoice from possible tables
            try {
                // Try tbl_invoices first
                if (ORM::for_table('tbl_invoices')->raw_query("SHOW TABLES LIKE 'tbl_invoices'")->find_one()) {
                    $invoice = ORM::for_table('tbl_invoices')
                        ->where('customer_id', $id_customer)
                        ->order_by_desc('id')
                        ->find_one();
                }
                // Try tbl_invoice (singular)
                elseif (ORM::for_table('tbl_invoice')->raw_query("SHOW TABLES LIKE 'tbl_invoice'")->find_one()) {
                    $invoice = ORM::for_table('tbl_invoice')
                        ->where('customer_id', $id_customer)
                        ->order_by_desc('id')
                        ->find_one();
                }
                // Try tbl_transactions
                elseif (ORM::for_table('tbl_transactions')->raw_query("SHOW TABLES LIKE 'tbl_transactions'")->find_one()) {
                    $invoice = ORM::for_table('tbl_transactions')
                        ->where('customer_id', $id_customer)
                        ->order_by_desc('id')
                        ->find_one();
                }
            } catch (Exception $e) {
                // Table doesn't exist, ignore
            }
            
            $package = '';
            $pppoe_username = '';
            $expired_date = '';
            $price = '';
            $bills = '';
            $plan_name = '';
            $plan_price = '';
            
            if ($recharge) {
                $package = $recharge['namebp'] ?? '';
                $price = $recharge['price'] ?? '';
                $bills = $recharge['billing_cycle'] ?? '';
                $expired_date = $recharge['expiration'] ?? '';
                $plan_name = $recharge['namebp'] ?? '';
                $plan_price = $recharge['price'] ?? '';
                // For PPPoE, the username might be stored differently
                $pppoe_username = $c['username'] ?? '';
            }
            
            if ($invoice) {
                $invoice_number = $invoice['invoice_number'] ?? $invoice['id'] ?? '';
                $date = $invoice['date'] ?? $invoice['created_at'] ?? $invoice['transaction_date'] ?? date('Y-m-d H:i:s');
                $payment_gateway = $invoice['payment_gateway'] ?? $invoice['gateway'] ?? '';
                $payment_channel = $invoice['payment_channel'] ?? $invoice['channel'] ?? '';
            }
            
            // Get company address and phone from settings
            $company_address = $config['CompanyAddress'] ?? '';
            $company_phone = $config['CompanyPhone'] ?? '';
            $footer = $config['CompanyName'] . ' - ' . Lang::T('Thank you for your business');

            // Replace all placeholders in the message with actual values
            $message = str_replace(
                [
                    '[[name]]', 
                    '[[username]]',
                    '[[user_name]]', 
                    '[[phone]]', 
                    '[[company_name]]',
                    '[[company]]',
                    '[[package]]',
                    '[[pppoe_username]]',
                    '[[expired_date]]',
                    '[[price]]',
                    '[[bills]]',
                    '[[address]]',
                    '[[invoice]]',
                    '[[date]]',
                    '[[payment_gateway]]',
                    '[[payment_channel]]',
                    '[[plan_name]]',
                    '[[plan_price]]',
                    '[[footer]]'
                ],
                [
                    $c['fullname'],
                    $c['username'],
                    $c['username'],
                    $c['phonenumber'],
                    $config['CompanyName'],
                    $config['CompanyName'],
                    $package,
                    $pppoe_username,
                    $expired_date,
                    $price,
                    $bills,
                    $company_address,
                    $invoice_number,
                    $date,
                    $payment_gateway,
                    $payment_channel,
                    $plan_name,
                    $plan_price,
                    $footer
                ],
                $message
            );
            
            if (strpos($message, '[[payment_link]]') !== false) {
                // token only valid for 1 day, for security reason
                $token = User::generateToken($c['id'], 1);
                if (!empty($token['token'])) {
                    $tur = ORM::for_table('tbl_user_recharges')
                        ->where('customer_id', $c['id'])
                        ->where('status', 'off')
                        ->find_one();
                    if ($tur) {
                        $url = APP_URL . '/?_route=home&recharge=' . $tur['id'] . '&uid=' . urlencode($token['token']);
                        $message = str_replace('[[payment_link]]', $url, $message);
                    } else {
                        $message = str_replace('[[payment_link]]', '', $message);
                    }
                } else {
                    $message = str_replace('[[payment_link]]', '', $message);
                }
            }

            //Send the message
            $smsSent = false;
            $waSent = false;
            
            if ($via == 'sms' || $via == 'both') {
                $smsSent = Message::sendSMS($c['phonenumber'], $message);
            }

            if ($via == 'wa' || $via == 'both') {
                $waSent = Message::sendWhatsapp($c['phonenumber'], $message);
            }

            if ($smsSent || $waSent) {
                r2(getUrl('message/send'), 's', Lang::T('Message Sent Successfully'));
            } else {
                r2(getUrl('message/send'), 'e', Lang::T('Failed to send message'));
            }
        }
        break;

    case 'send_bulk':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }

        $ui->assign('routers', ORM::forTable('tbl_routers')->where('enabled', '1')->find_many());
        $ui->display('admin/message/bulk.tpl');
        break;

    case 'send_bulk_ajax':
        // Check user permissions
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            die(json_encode(['status' => 'error', 'message' => 'Permission denied']));
        }

        set_time_limit(0);

        // Get request parameters
        $group = $_REQUEST['group'] ?? '';
        $message = $_REQUEST['message'] ?? '';
        $via = $_REQUEST['via'] ?? '';
        $batch = $_REQUEST['batch'] ?? 100;
        $page = $_REQUEST['page'] ?? 0;
        $router = $_REQUEST['router'] ?? null;
        $test = isset($_REQUEST['test']) && $_REQUEST['test'] === 'on' ? true : false;
        $service = $_REQUEST['service'] ?? '';

        if (empty($group) || empty($message) || empty($via) || empty($service)) {
            die(json_encode(['status' => 'error', 'message' => 'All fields are required']));
        }

        // Get batch of customers based on group
        $startpoint = $page * $batch;
        $customers = [];
        $totalCustomers = 0;
        $routerName = '';

        if (isset($router) && !empty($router) && $router != 'all') {
            switch ($router) {
                case 'radius':
                    $routerName = 'Radius';
                    break;
                default:
                    $routerObj = ORM::for_table('tbl_routers')->find_one($router);
                    if (!$routerObj) {
                        die(json_encode(['status' => 'error', 'message' => 'Invalid router']));
                    }
                    $routerName = $routerObj->name;
                    break;
            }
        }

        if (isset($router) && !empty($router) && $router != 'all') {
            $query = ORM::for_table('tbl_user_recharges')
                ->left_outer_join('tbl_customers', 'tbl_user_recharges.customer_id = tbl_customers.id')
                ->where('tbl_user_recharges.routers', $routerName);

            switch ($service) {
                case 'all':
                    break;
                default:
                    $validServices = ['PPPoE', 'Hotspot', 'VPN'];
                    if (in_array($service, $validServices)) {
                        $query->where('type', $service);
                    }
                    break;
            }

            $totalCustomers = $query->count();

            $query->offset($startpoint)
                ->limit($batch);

            switch ($group) {
                case 'all':
                    break;
                case 'new':
                    $query->where_raw("DATE(recharged_on) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                    break;
                case 'expired':
                    $query->where('tbl_user_recharges.status', 'off');
                    break;
                case 'active':
                    $query->where('tbl_user_recharges.status', 'on');
                    break;
            }

            // Fetch the customers with all needed fields
            $query->selects([
                ['tbl_customers.id', 'customer_id'],
                ['tbl_customers.fullname', 'fullname'],
                ['tbl_customers.username', 'username'],
                ['tbl_customers.phonenumber', 'phonenumber'],
                ['tbl_customers.email', 'email'],
                ['tbl_user_recharges.namebp', 'package'],
                ['tbl_user_recharges.type', 'service_type'],
                ['tbl_user_recharges.status', 'recharge_status'],
                ['tbl_user_recharges.price', 'price'],
                ['tbl_user_recharges.billing_cycle', 'bills'],
                ['tbl_user_recharges.expiration', 'expired_date']
            ]);
            $customers = $query->find_array();
        } else {
            // No router selected, get customers directly
            $totalCustomersQuery = ORM::for_table('tbl_customers');
            
            // Apply service filter if specified
            if ($service != 'all') {
                $validServices = ['PPPoE', 'Hotspot', 'VPN'];
                if (in_array($service, $validServices)) {
                    $totalCustomersQuery->where('service_type', $service);
                }
            }
            
            switch ($group) {
                case 'all':
                    break;
                case 'new':
                    $totalCustomersQuery->where_raw("DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                    break;
                case 'expired':
                    // For expired, we need to join with recharges
                    $totalCustomersQuery = ORM::for_table('tbl_user_recharges')
                        ->left_outer_join('tbl_customers', 'tbl_user_recharges.customer_id = tbl_customers.id')
                        ->where('tbl_user_recharges.status', 'off');
                    
                    if ($service != 'all') {
                        $totalCustomersQuery->where('tbl_user_recharges.type', $service);
                    }
                    break;
                case 'active':
                    // For active, we need to join with recharges
                    $totalCustomersQuery = ORM::for_table('tbl_user_recharges')
                        ->left_outer_join('tbl_customers', 'tbl_user_recharges.customer_id = tbl_customers.id')
                        ->where('tbl_user_recharges.status', 'on');
                    
                    if ($service != 'all') {
                        $totalCustomersQuery->where('tbl_user_recharges.type', $service);
                    }
                    break;
            }
            
            $totalCustomers = $totalCustomersQuery->count();
            
            // Fetch customers with proper fields
            if ($group == 'expired' || $group == 'active') {
                $customers = $totalCustomersQuery
                    ->selects([
                        ['tbl_customers.id', 'customer_id'],
                        ['tbl_customers.fullname', 'fullname'],
                        ['tbl_customers.username', 'username'],
                        ['tbl_customers.phonenumber', 'phonenumber'],
                        ['tbl_customers.email', 'email'],
                        ['tbl_user_recharges.namebp', 'package'],
                        ['tbl_user_recharges.type', 'service_type'],
                        ['tbl_user_recharges.status', 'recharge_status'],
                        ['tbl_user_recharges.price', 'price'],
                        ['tbl_user_recharges.billing_cycle', 'bills'],
                        ['tbl_user_recharges.expiration', 'expired_date']
                    ])
                    ->offset($startpoint)
                    ->limit($batch)
                    ->find_array();
            } else {
                $customers = $totalCustomersQuery
                    ->selects([
                        'id',
                        'fullname',
                        'username',
                        'phonenumber',
                        'email',
                        'service_type'
                    ])
                    ->offset($startpoint)
                    ->limit($batch)
                    ->find_array();
                
                // Add package info for these customers
                foreach ($customers as &$customer) {
                    $recharge = ORM::for_table('tbl_user_recharges')
                        ->where('customer_id', $customer['id'])
                        ->where('status', 'on')
                        ->find_one();
                    
                    $customer['package'] = $recharge['namebp'] ?? '';
                    $customer['price'] = $recharge['price'] ?? '';
                    $customer['bills'] = $recharge['billing_cycle'] ?? '';
                    $customer['expired_date'] = $recharge['expiration'] ?? '';
                    $customer['recharge_status'] = $recharge['status'] ?? '';
                }
            }
        }

        // Ensure $customers is always an array
        if (!$customers) {
            $customers = [];
        }

        // Get company settings for all customers
        $company_address = $config['CompanyAddress'] ?? '';
        $company_phone = $config['CompanyPhone'] ?? '';
        $footer = $config['CompanyName'] . ' - ' . Lang::T('Thank you for your business');

        // Send messages
        $totalSMSSent = 0;
        $totalSMSFailed = 0;
        $totalWhatsappSent = 0;
        $totalWhatsappFailed = 0;
        $batchStatus = [];

        foreach ($customers as $customer) {
            // Get invoice info for this customer - check which table exists
            $invoice = null;
            $invoice_number = '';
            $invoice_date = date('Y-m-d H:i:s');
            $payment_gateway = '';
            $payment_channel = '';
            
            try {
                // Try tbl_invoices first
                if (ORM::for_table('tbl_invoices')->raw_query("SHOW TABLES LIKE 'tbl_invoices'")->find_one()) {
                    $invoice = ORM::for_table('tbl_invoices')
                        ->where('customer_id', $customer['customer_id'] ?? $customer['id'])
                        ->order_by_desc('id')
                        ->find_one();
                }
                // Try tbl_invoice (singular)
                elseif (ORM::for_table('tbl_invoice')->raw_query("SHOW TABLES LIKE 'tbl_invoice'")->find_one()) {
                    $invoice = ORM::for_table('tbl_invoice')
                        ->where('customer_id', $customer['customer_id'] ?? $customer['id'])
                        ->order_by_desc('id')
                        ->find_one();
                }
                // Try tbl_transactions
                elseif (ORM::for_table('tbl_transactions')->raw_query("SHOW TABLES LIKE 'tbl_transactions'")->find_one()) {
                    $invoice = ORM::for_table('tbl_transactions')
                        ->where('customer_id', $customer['customer_id'] ?? $customer['id'])
                        ->order_by_desc('id')
                        ->find_one();
                }
            } catch (Exception $e) {
                // Table doesn't exist, ignore
            }
            
            if ($invoice) {
                $invoice_number = $invoice['invoice_number'] ?? $invoice['id'] ?? '';
                $invoice_date = $invoice['date'] ?? $invoice['created_at'] ?? $invoice['transaction_date'] ?? date('Y-m-d H:i:s');
                $payment_gateway = $invoice['payment_gateway'] ?? $invoice['gateway'] ?? '';
                $payment_channel = $invoice['payment_channel'] ?? $invoice['channel'] ?? '';
            }
            
            // Prepare all replacement variables
            $replacements = [
                '[[name]]' => $customer['fullname'] ?? '',
                '[[username]]' => $customer['username'] ?? '',
                '[[user_name]]' => $customer['username'] ?? '',
                '[[phone]]' => $customer['phonenumber'] ?? '',
                '[[company_name]]' => $config['CompanyName'] ?? '',
                '[[company]]' => $config['CompanyName'] ?? '',
                '[[package]]' => $customer['package'] ?? '',
                '[[pppoe_username]]' => $customer['username'] ?? '',
                '[[expired_date]]' => $customer['expired_date'] ?? '',
                '[[price]]' => $customer['price'] ?? '',
                '[[bills]]' => $customer['bills'] ?? '',
                '[[address]]' => $company_address,
                '[[invoice]]' => $invoice_number,
                '[[date]]' => $invoice_date,
                '[[payment_gateway]]' => $payment_gateway,
                '[[payment_channel]]' => $payment_channel,
                '[[plan_name]]' => $customer['package'] ?? '',
                '[[plan_price]]' => $customer['price'] ?? '',
                '[[footer]]' => $footer
            ];
            
            // Replace payment link if present
            $currentMessage = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $message
            );
            
            // Handle payment link separately if needed
            if (strpos($currentMessage, '[[payment_link]]') !== false) {
                $token = User::generateToken($customer['customer_id'] ?? $customer['id'], 1);
                if (!empty($token['token'])) {
                    $tur = ORM::for_table('tbl_user_recharges')
                        ->where('customer_id', $customer['customer_id'] ?? $customer['id'])
                        ->where('status', 'off')
                        ->find_one();
                    if ($tur) {
                        $url = APP_URL . '/?_route=home&recharge=' . $tur['id'] . '&uid=' . urlencode($token['token']);
                        $currentMessage = str_replace('[[payment_link]]', $url, $currentMessage);
                    } else {
                        $currentMessage = str_replace('[[payment_link]]', '', $currentMessage);
                    }
                } else {
                    $currentMessage = str_replace('[[payment_link]]', '', $currentMessage);
                }
            }

            $phoneNumber = preg_replace('/\D/', '', $customer['phonenumber'] ?? '');

            if (empty($phoneNumber)) {
                $batchStatus[] = [
                    'name' => $customer['fullname'] ?? 'Unknown',
                    'phone' => '',
                    'status' => 'No Phone Number'
                ];
                continue;
            }

            if ($test) {
                $batchStatus[] = [
                    'name' => $customer['fullname'] ?? 'Unknown',
                    'phone' => $customer['phonenumber'] ?? '',
                    'status' => 'Test Mode',
                    'message' => $currentMessage,
                    'service' => $service,
                    'router' => $routerName,
                    'package' => $customer['package'] ?? '',
                    'username' => $customer['username'] ?? '',
                    'invoice' => $invoice_number
                ];
            } else {
                if ($via == 'sms' || $via == 'both') {
                    if (Message::sendSMS($customer['phonenumber'], $currentMessage)) {
                        $totalSMSSent++;
                        $batchStatus[] = [
                            'name' => $customer['fullname'] ?? 'Unknown', 
                            'phone' => $customer['phonenumber'] ?? '', 
                            'status' => 'SMS Sent', 
                            'message' => $currentMessage
                        ];
                    } else {
                        $totalSMSFailed++;
                        $batchStatus[] = [
                            'name' => $customer['fullname'] ?? 'Unknown', 
                            'phone' => $customer['phonenumber'] ?? '', 
                            'status' => 'SMS Failed', 
                            'message' => $currentMessage
                        ];
                    }
                }

                if ($via == 'wa' || $via == 'both') {
                    if (Message::sendWhatsapp($customer['phonenumber'], $currentMessage)) {
                        $totalWhatsappSent++;
                        $batchStatus[] = [
                            'name' => $customer['fullname'] ?? 'Unknown', 
                            'phone' => $customer['phonenumber'] ?? '', 
                            'status' => 'WhatsApp Sent', 
                            'message' => $currentMessage
                        ];
                    } else {
                        $totalWhatsappFailed++;
                        $batchStatus[] = [
                            'name' => $customer['fullname'] ?? 'Unknown', 
                            'phone' => $customer['phonenumber'] ?? '', 
                            'status' => 'WhatsApp Failed', 
                            'message' => $currentMessage
                        ];
                    }
                }
            }
        }

        // Calculate if there are more customers to process
        $hasMore = ($startpoint + $batch) < $totalCustomers;

        // Return JSON response
        echo json_encode([
            'status' => 'success',
            'page' => $page + 1,
            'batchStatus' => $batchStatus,
            'totalSent' => $totalSMSSent + $totalWhatsappSent,
            'totalFailed' => $totalSMSFailed + $totalWhatsappFailed,
            'hasMore' => $hasMore,
            'service' => $service,
            'router' => $routerName,
            'processedCount' => count($customers),
            'totalCustomers' => $totalCustomers
        ]);
        break;

    case 'send_bulk_selected':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Set headers
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');

            // Get the posted data
            $customerIds = $_POST['customer_ids'] ?? [];
            $via = $_POST['message_type'] ?? '';
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            
            if (empty($customerIds) || empty($message) || empty($via)) {
                echo json_encode(['status' => 'error', 'message' => Lang::T('Invalid customer IDs, Message, or Message Type.')]);
                exit;
            }

            // Get company settings
            $company_address = $config['CompanyAddress'] ?? '';
            $company_phone = $config['CompanyPhone'] ?? '';
            $footer = $config['CompanyName'] . ' - ' . Lang::T('Thank you for your business');

            // Prepare to send messages
            $sentCount = 0;
            $failedCount = 0;
            $subject = Lang::T('Notification Message');
            $form = 'Admin';
            $details = [];

            foreach ($customerIds as $customerId) {
                $customer = ORM::for_table('tbl_customers')->where('id', $customerId)->find_one();
                if ($customer) {
                    // Get customer's package info
                    $recharge = ORM::for_table('tbl_user_recharges')
                        ->where('customer_id', $customerId)
                        ->where('status', 'on')
                        ->find_one();
                    
                    // Get invoice info - check which table exists
                    $invoice = null;
                    $invoice_number = '';
                    $invoice_date = date('Y-m-d H:i:s');
                    $payment_gateway = '';
                    $payment_channel = '';
                    
                    try {
                        // Try tbl_invoices first
                        if (ORM::for_table('tbl_invoices')->raw_query("SHOW TABLES LIKE 'tbl_invoices'")->find_one()) {
                            $invoice = ORM::for_table('tbl_invoices')
                                ->where('customer_id', $customerId)
                                ->order_by_desc('id')
                                ->find_one();
                        }
                        // Try tbl_invoice (singular)
                        elseif (ORM::for_table('tbl_invoice')->raw_query("SHOW TABLES LIKE 'tbl_invoice'")->find_one()) {
                            $invoice = ORM::for_table('tbl_invoice')
                                ->where('customer_id', $customerId)
                                ->order_by_desc('id')
                                ->find_one();
                        }
                        // Try tbl_transactions
                        elseif (ORM::for_table('tbl_transactions')->raw_query("SHOW TABLES LIKE 'tbl_transactions'")->find_one()) {
                            $invoice = ORM::for_table('tbl_transactions')
                                ->where('customer_id', $customerId)
                                ->order_by_desc('id')
                                ->find_one();
                        }
                    } catch (Exception $e) {
                        // Table doesn't exist, ignore
                    }
                    
                    if ($invoice) {
                        $invoice_number = $invoice['invoice_number'] ?? $invoice['id'] ?? '';
                        $invoice_date = $invoice['date'] ?? $invoice['created_at'] ?? $invoice['transaction_date'] ?? date('Y-m-d H:i:s');
                        $payment_gateway = $invoice['payment_gateway'] ?? $invoice['gateway'] ?? '';
                        $payment_channel = $invoice['payment_channel'] ?? $invoice['channel'] ?? '';
                    }
                    
                    $package = $recharge['namebp'] ?? '';
                    $expired_date = $recharge['expiration'] ?? '';
                    $price = $recharge['price'] ?? '';
                    $bills = $recharge['billing_cycle'] ?? '';
                    
                    // Replace variables in message
                    $processedMessage = str_replace(
                        [
                            '[[name]]',
                            '[[username]]',
                            '[[user_name]]',
                            '[[phone]]',
                            '[[company_name]]',
                            '[[company]]',
                            '[[package]]',
                            '[[pppoe_username]]',
                            '[[expired_date]]',
                            '[[price]]',
                            '[[bills]]',
                            '[[address]]',
                            '[[invoice]]',
                            '[[date]]',
                            '[[payment_gateway]]',
                            '[[payment_channel]]',
                            '[[plan_name]]',
                            '[[plan_price]]',
                            '[[footer]]'
                        ],
                        [
                            $customer['fullname'],
                            $customer['username'],
                            $customer['username'],
                            $customer['phonenumber'],
                            $config['CompanyName'],
                            $config['CompanyName'],
                            $package,
                            $customer['username'],
                            $expired_date,
                            $price,
                            $bills,
                            $company_address,
                            $invoice_number,
                            $invoice_date,
                            $payment_gateway,
                            $payment_channel,
                            $package,
                            $price,
                            $footer
                        ],
                        $message
                    );
                    
                    $messageSent = false;

                    // Check the message type and send accordingly
                    try {
                        if ($via === 'sms' || $via === 'all') {
                            $messageSent = Message::sendSMS($customer['phonenumber'], $processedMessage);
                        }
                        if (!$messageSent && ($via === 'wa' || $via === 'all')) {
                            $messageSent = Message::sendWhatsapp($customer['phonenumber'], $processedMessage);
                        }
                        if (!$messageSent && ($via === 'inbox' || $via === 'all')) {
                            Message::addToInbox($customer['id'], $subject, $processedMessage, $form);
                            $messageSent = true;
                        }
                        if (!$messageSent && ($via === 'email' || $via === 'all')) {
                            $messageSent = Message::sendEmail($customer['email'], $subject, $processedMessage);
                        }
                    } catch (Throwable $e) {
                        $messageSent = false;
                        $failedCount++;
                        sendTelegram('Failed to send message to ' . $e->getMessage());
                        _log('Failed to send message to ' . $customer['fullname'] . ': ' . $e->getMessage());
                        $details[] = [
                            'id' => $customerId,
                            'name' => $customer['fullname'],
                            'status' => 'Failed',
                            'error' => $e->getMessage()
                        ];
                        continue;
                    }

                    if ($messageSent) {
                        $sentCount++;
                        $details[] = [
                            'id' => $customerId,
                            'name' => $customer['fullname'],
                            'status' => 'Sent',
                            'message' => $processedMessage
                        ];
                    } else {
                        $failedCount++;
                        $details[] = [
                            'id' => $customerId,
                            'name' => $customer['fullname'],
                            'status' => 'Failed',
                            'error' => 'Unknown error'
                        ];
                    }
                } else {
                    $failedCount++;
                    $details[] = [
                        'id' => $customerId,
                        'name' => 'Unknown',
                        'status' => 'Failed',
                        'error' => 'Customer not found'
                    ];
                }
            }

            // Prepare the response
            echo json_encode([
                'status' => 'success',
                'totalSent' => $sentCount,
                'totalFailed' => $failedCount,
                'details' => $details
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => Lang::T('Invalid request method.')]);
        }
        break;
        
    default:
        r2(getUrl('message/send_sms'), 'e', 'action not defined');
}