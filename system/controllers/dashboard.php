<?php

global $admin, $config, $WIDGET_PATH;

_admin();
$ui->assign('_title', Lang::T('Dashboard'));
$ui->assign('_admin', $admin);

// The authenticated admin has already been resolved. Release the PHP session
// lock before any widget/AJAX work so another tab for this admin is not blocked.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Billing period dates (required by top_widget::ajaxGetFilteredData via globals — must run before JSON exit)
$reset_day = $config['reset_day'];
if (empty($reset_day)) {
    $reset_day = 1;
}
if (date('d') >= $reset_day) {
    $start_date = date('Y-m-' . $reset_day);
} else {
    $start_date = date('Y-m-' . $reset_day, strtotime('-1 MONTH'));
}
$current_date = date('Y-m-d');

// ===== AJAX ENDPOINT FOR FILTERED DATA =====
if (isset($_GET['_route']) && $_GET['_route'] == 'dashboard' && isset($_GET['router_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    require_once $WIDGET_PATH . DIRECTORY_SEPARATOR . 'top_widget.php';

    $router_id = $_GET['router_id'];
    $data = top_widget::ajaxGetFilteredData($router_id);

    echo json_encode($data);
    exit;
}
// ===== END AJAX ENDPOINT =====

if (isset($_GET['refresh'])) {
    r2(getUrl('dashboard'), 's', 'Dashboard Refreshed');
}

$tipeUser = _req("user");
if (empty($tipeUser)) {
    $tipeUser = 'Admin';
}
$ui->assign('tipeUser', $tipeUser);

$ui->assign('start_date', $start_date);
$ui->assign('current_date', $current_date);

$tipeUser = $admin['user_type'];
if (in_array($tipeUser, ['SuperAdmin', 'Admin'])) {
    $tipeUser = 'Admin';
}

$widgets = ORM::for_table('tbl_widgets')->where("enabled", 1)->where('user', $tipeUser)->order_by_asc("orders")->findArray();
$count = count($widgets);
for ($i = 0; $i < $count; $i++) {
    try{
        if(file_exists($WIDGET_PATH . DIRECTORY_SEPARATOR . $widgets[$i]['widget'].".php")){
            require_once $WIDGET_PATH . DIRECTORY_SEPARATOR . $widgets[$i]['widget'].".php";
            $widgets[$i]['content'] = (new $widgets[$i]['widget'])->getWidget($widgets[$i]);
        }else{
            // Keep dashboard clean if a widget entry exists but file is missing.
            $widgets[$i]['content'] = "";
        }
    } catch (Throwable $e) {
        $widgets[$i]['content'] = $e->getMessage();
    }
}

$ui->assign('widgets', $widgets);
run_hook('view_dashboard'); #HOOK
$ui->display('admin/dashboard.tpl');
