<?php

/**
 * Final RouterOS HotSpot creation-order compatibility layer.
 *
 * The previous shim tried to create/verify the HotSpot HTML directory before
 * the HotSpot profile existed. RouterOS normally creates the default HotSpot
 * servlet directory when the profile is created, so that check could stop a
 * valid configuration too early. This layer creates/repairs the profile first,
 * then lets the normal configurator create the server and upload login.html.
 */

use PEAR2\Net\RouterOS;

$route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($route === 'plugin/rs2_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs3_mikrotik_configurator_config_process';
}

function rs3_prepare_hotspot_profile($router)
{
    $services = $_POST['serviceType'] ?? ($_POST['service_type'] ?? []);
    if (!is_array($services)) {
        $services = [$services];
    }
    $services = array_map('strtolower', array_map('trim', $services));
    if (!in_array('hotspot', $services, true)) {
        return;
    }

    $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
    $bridge = trim((string) ($_POST['bridge'] ?? ''));
    $bridgeHotspot = $sameBridge === 'yes'
        ? $bridge
        : trim((string) ($_POST['bridge_hotspot'] ?? ''));
    if ($bridgeHotspot === '') {
        return;
    }

    $subnet = trim((string) ($_POST['subnet_hotspot'] ?? ''));
    if (!function_exists('mikrotik_configurator_is_valid_cidr') || !mikrotik_configurator_is_valid_cidr($subnet)) {
        return;
    }

    $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
    $gatewayIp = explode('/', $gatewayCidr, 2)[0];
    $profileName = $bridgeHotspot . '-Profile';
    $directory = trim((string) ($_POST['hotspot_html_directory'] ?? 'hotspot'));
    if ($directory === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $directory) || strpos($directory, '..') !== false) {
        throw new RuntimeException('Invalid Hotspot HTML directory.');
    }

    $dnsName = function_exists('rs2_hotspot_dns_name')
        ? rs2_hotspot_dns_name($_POST['hotspot_dns_name'] ?? '')
        : 'hotspot.local';

    $wireguardManaged = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
    $auth = $wireguardManaged ? 'radius' : strtolower(trim((string) ($_POST['hotspot_auth_type'] ?? 'api')));

    $client = rs_mikrotik_configurator_client($router, 8);
    $client->sendSync(new RouterOS\Request('/system/identity/print'));

    $existingId = '';
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $item) {
        if ((string) $item->getProperty('name') === $profileName) {
            $existingId = (string) $item->getProperty('.id');
            break;
        }
    }

    $request = new RouterOS\Request($existingId === '' ? '/ip/hotspot/profile/add' : '/ip/hotspot/profile/set');
    if ($existingId !== '') {
        $request->setArgument('numbers', $existingId);
    } else {
        $request->setArgument('name', $profileName);
    }

    // Only properties documented for /ip/hotspot/profile are used here.
    // In particular, do not send the unsupported legacy `mac-auth-mode` field.
    $request->setArgument('hotspot-address', $gatewayIp)
        ->setArgument('dns-name', $dnsName)
        ->setArgument('login-by', 'http-pap,http-chap,cookie')
        ->setArgument('http-cookie-lifetime', '3d')
        ->setArgument('html-directory', $directory)
        ->setArgument('use-radius', $auth === 'radius' ? 'yes' : 'no')
        ->setArgument('radius-accounting', $auth === 'radius' ? 'yes' : 'no')
        ->setArgument('radius-interim-update', 'received');
    $client->sendSync($request);

    $verified = false;
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $item) {
        if ((string) $item->getProperty('name') === $profileName) {
            $verified = true;
            break;
        }
    }
    if (!$verified) {
        throw new RuntimeException('RouterOS rejected Hotspot profile ' . $profileName . '.');
    }

    // Do not fail here if the HTML folder has not appeared yet. RouterOS creates
    // the default HotSpot servlet files as part of HotSpot profile/server setup;
    // the later upload stage is the authoritative verification for login.html.
}

function rs3_mikrotik_configurator_config_process()
{
    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found.');
    }

    $back = U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $routerId;

    try {
        rs3_prepare_hotspot_profile($router);
    } catch (Throwable $error) {
        if (function_exists('rs_mikrotik_configurator_log_failure')) {
            rs_mikrotik_configurator_log_failure($routerId, 'creating RouterOS Hotspot profile', $error);
        }
        $message = function_exists('rs_mikrotik_configurator_safe_error')
            ? rs_mikrotik_configurator_safe_error($error->getMessage())
            : $error->getMessage();
        r2($back, 'e', 'Configuration stopped while creating RouterOS Hotspot profile: ' . $message);
    }

    // Bypass the older rs2 pre-check (which required the directory too early)
    // and continue through the hardened server-side configurator.
    rs_mikrotik_configurator_config_process();
}
