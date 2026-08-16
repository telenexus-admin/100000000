<?php

session_start();

if(isset($_GET['nux-mac']) && !empty($_GET['nux-mac'])){
    $_SESSION['nux-mac'] = $_GET['nux-mac'];
}

if(isset($_GET['nux-ip']) && !empty($_GET['nux-ip'])){
    $_SESSION['nux-ip'] = $_GET['nux-ip'];
}

if(isset($_GET['nux-router']) && !empty($_GET['nux-router'])){
    $_SESSION['nux-router'] = $_GET['nux-router'];
}

//get chap id and chap challenge
if(isset($_GET['nux-key']) && !empty($_GET['nux-key'])){
    $_SESSION['nux-key'] = $_GET['nux-key'];
}
//get mikrotik hostname
if(isset($_GET['nux-hostname']) && !empty($_GET['nux-hostname'])){
    $_SESSION['nux-hostname'] = $_GET['nux-hostname'];
}

// Normal admin navigation is read-only. Closing the session here keeps the
// authenticated data available while preventing one slow tab from blocking the
// next one. Mutations, authentication, settings, and hotspot callbacks retain
// normal session-write behavior.
$route = isset($_GET['_route']) ? (string) $_GET['_route'] : 'dashboard';
$section = strtolower((string) strtok($route, '/'));
$readOnlyAdminSections = ['dashboard', 'routers', 'customers', 'services', 'plan', 'paymentgateway', 'reports', 'invoices'];
$hasHotspotContext = isset($_GET['nux-mac']) || isset($_GET['nux-ip']) || isset($_GET['nux-router']) || isset($_GET['nux-key']) || isset($_GET['nux-hostname']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$hasHotspotContext && in_array($section, $readOnlyAdminSections, true)) {
    session_write_close();
}

require_once 'system/vendor/autoload.php';
require_once 'system/boot.php';
App::_run();
