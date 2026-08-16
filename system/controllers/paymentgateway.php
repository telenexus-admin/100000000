<?php

_admin();
$ui->assign('_system_menu', 'paymentgateway');

// Check for payment gateway access password
session_start();

// Get admin phone number from tbl_users table
$admin_id = $admin['id']; // Get current admin ID
$admin_data = ORM::for_table('tbl_users')->find_one($admin_id);
$adminPhone = trim($admin_data['phone']); // Get phone number from tbl_users

// Handle AJAX session check
if (isset($_GET['check_session'])) {
    header('Content-Type: application/json');
    if (isset($_SESSION['pg_access_verified']) && $_SESSION['pg_access_verified'] === true) {
        if (isset($_SESSION['pg_access_time']) && (time() - $_SESSION['pg_access_time']) < 180) { // 3 minutes = 180 seconds
            echo json_encode(['active' => true]);
            exit;
        }
    }
    echo json_encode(['active' => false]);
    exit;
}

// Handle session extension
if (isset($_POST['extend_session'])) {
    header('Content-Type: application/json');
    if (isset($_SESSION['pg_access_verified']) && $_SESSION['pg_access_verified'] === true) {
        $_SESSION['pg_access_time'] = time(); // Reset the timer
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle password verification
if (isset($_POST['verify_pg_access'])) {
    $entered_password = trim(_post('pg_password'));
    
    // Compare exactly as is
    if ($entered_password === $adminPhone) {
        $_SESSION['pg_access_verified'] = true;
        $_SESSION['pg_access_time'] = time();
        r2(getUrl('paymentgateway'));
    } else {
        $ui->assign('error', Lang::T('Invalid password. Please enter your admin phone number correctly.'));
    }
}

// Check if access is verified (valid for 3 minutes)
$access_verified = false;
$remaining_time = 0;
if (isset($_SESSION['pg_access_verified']) && $_SESSION['pg_access_verified'] === true) {
    // Check if session is not expired (3 minutes)
    $elapsed = time() - $_SESSION['pg_access_time'];
    if ($elapsed < 180) { // 3 minutes = 180 seconds
        $access_verified = true;
        $remaining_time = 180 - $elapsed;
    } else {
        // Session expired
        unset($_SESSION['pg_access_verified']);
        unset($_SESSION['pg_access_time']);
    }
}

// If not verified, show password form
if (!$access_verified) {
    $ui->assign('_title', 'Payment Gateway Access Verification');
    
    // Mask the phone number for display (show only last 4 digits)
    $masked_phone = '';
    if (!empty($adminPhone)) {
        $phone_length = strlen($adminPhone);
        if ($phone_length > 4) {
            $masked_phone = str_repeat('*', $phone_length - 4) . substr($adminPhone, -4);
        } else {
            $masked_phone = str_repeat('*', $phone_length);
        }
    }
    
    $ui->assign('admin_phone_masked', $masked_phone);
    $ui->assign('remaining_time', $remaining_time);
    $ui->display('admin/paymentgateway/verify.tpl');
    exit;
}

// If verified, continue to payment gateway page
$action = alphanumeric($routes[1]);
$ui->assign('_admin', $admin);

// Add session timer display in header for authenticated pages
$ui->assign('session_remaining', $remaining_time);

switch ($action) {
    case 'delete':
        $pg = alphanumeric($routes[2]);
        if (file_exists($PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . $pg . '.php')) {
            deleteFile($PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR, $pg);
        }
        r2(getUrl('paymentgateway'), 's', Lang::T('Payment Gateway Deleted'));

    case 'audit':
        $pg = alphanumeric($routes[2]);
        $q = alphanumeric(_req('q'), '-._ ');
        $query = ORM::for_table('tbl_payment_gateway')->order_by_desc("id");
        $query->selects('id', 'username', 'gateway', 'gateway_trx_id', 'plan_id', 'plan_name', 'routers_id', 'routers', 'price', 'pg_url_payment', 'payment_method', 'payment_channel', 'expired_date', 'created_date', 'paid_date', 'trx_invoice', 'status');
        $query->where('gateway', $pg);
        if (!empty($q)) {
            $query->whereRaw("(gateway_trx_id LIKE '%$q%' OR username LIKE '%$q%' OR routers LIKE '%$q%' OR plan_name LIKE '%$q%')");
            $append_url = 'q=' . urlencode($q);
        }
        $pgs = Paginator::findMany($query, ["search" => $search], 50, $append_url);

        $ui->assign('_title', 'Payment Gateway Audit');
        $ui->assign('pgs', $pgs);
        $ui->assign('pg', $pg);
        $ui->assign('q', $q);
        $ui->display('admin/paymentgateway/audit.tpl');
        break;
    case 'auditview':
        $pg = alphanumeric($routes[2]);
        $d = ORM::for_table('tbl_payment_gateway')->find_one($pg);
        $d['pg_request'] = (!empty($d['pg_request']))? Text::jsonArray21Array(json_decode($d['pg_request'], true)) : [];
        $d['pg_paid_response'] = (!empty($d['pg_paid_response']))? Text::jsonArray21Array(json_decode($d['pg_paid_response'], true)) : [];
        $ui->assign('_title', 'Payment Gateway Audit View');
        $ui->assign('pg', $d);
        $ui->display('admin/paymentgateway/audit-view.tpl');
        break;
    default:
        if (_post('save') == 'actives') {
            $pgs = '';
            if (is_array($_POST['pgs'])) {
                $pgs = implode(',', $_POST['pgs']);
            }
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'payment_gateway')->find_one();
            if ($d) {
                $d->value = $pgs;
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'payment_gateway';
                $d->value = $pgs;
                $d->save();
            }
            r2(getUrl('paymentgateway'), 's', Lang::T('Payment Gateway saved successfully'));
        }

        if (file_exists($PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . $action . '.php')) {
            include $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . $action . '.php';
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (function_exists($action . '_save_config')) {
                    call_user_func($action . '_save_config');
                } else {
                    $ui->display('admin/404.tpl');
                }
            } else {
                if (function_exists($action . '_show_config')) {
                    call_user_func($action . '_show_config');
                } else {
                    $ui->display('admin/404.tpl');
                }
            }
        } else {
            if (!empty($action)) {
                r2(getUrl('paymentgateway'), 'w', Lang::T('Payment Gateway Not Found'));
            } else {
                $files = scandir($PAYMENTGATEWAY_PATH);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                        $pgs[] = str_replace('.php', '', $file);
                    }
                }
                $ui->assign('_title', 'Payment Gateway Settings');
                $ui->assign('pgs', $pgs);
                $ui->assign('actives', explode(',', $config['payment_gateway']));
                $ui->display('admin/paymentgateway/list.tpl');
            }
        }
}


function deleteFile($path, $name)
{
    $files = scandir($path);
    foreach ($files as $file) {
        if (is_file($path . $file) && strpos($file, $name) !== false) {
            unlink($path . $file);
        } else if (is_dir($path . $file) && !in_array($file, ['.', '..'])) {
            deleteFile($path . $file . DIRECTORY_SEPARATOR, $name);
        }
    }
}