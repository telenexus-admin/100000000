<?php

/**
 * RouterOS HotSpot profile compatibility shim.
 *
 * The legacy configurator sends `mac-auth-mode` to /ip/hotspot/profile.
 * Current RouterOS HotSpot profiles do not expose that property, so RouterOS
 * rejects the add/set command and the configurator later reports that the
 * profile was not created.  This shim pre-creates/repairs the profile with
 * supported properties before the reliability handler runs.
 */

use PEAR2\Net\RouterOS;

$route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($route === 'plugin/rs_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs2_mikrotik_configurator_config_process';
}

function rs2_hotspot_dns_name($value)
{
    $value = strtolower(trim((string) $value));
    $value = rtrim($value, '.');

    // RouterOS HotSpot expects a DNS-style name.  Reject malformed UI values
    // such as `.net` and fall back to a safe local portal name.
    if ($value === '' || strlen($value) > 253 || strpos($value, '.') === false) {
        return 'hotspot.local';
    }

    $labels = explode('.', $value);
    foreach ($labels as $label) {
        if ($label === '' || strlen($label) > 63 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
            return 'hotspot.local';
        }
    }

    return $value;
}

function rs2_ensure_hotspot_directory($client, $directory)
{
    $directory = trim((string) $directory, "/\\");
    if ($directory === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $directory) || strpos($directory, '..') !== false) {
        throw new RuntimeException('Invalid Hotspot HTML directory.');
    }

    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $item) {
        if (trim((string) $item->getProperty('name'), "/\\") === $directory) {
            return;
        }
    }

    $add = new RouterOS\Request('/file/add');
    $add->setArgument('name', $directory)
        ->setArgument('type', 'directory');
    $client->sendSync($add);

    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $item) {
        if (trim((string) $item->getProperty('name'), "/\\") === $directory) {
            return;
        }
    }

    throw new RuntimeException('RouterOS did not create Hotspot directory ' . $directory . '.');
}

function rs2_prepare_hotspot_profile($router)
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
        return; // main handler will produce the normal validation message.
    }

    $subnet = trim((string) ($_POST['subnet_hotspot'] ?? ''));
    if (!function_exists('mikrotik_configurator_is_valid_cidr') || !mikrotik_configurator_is_valid_cidr($subnet)) {
        return;
    }

    $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
    $gatewayIp = explode('/', $gatewayCidr, 2)[0];
    $profileName = $bridgeHotspot . '-Profile';
    $directory = trim((string) ($_POST['hotspot_html_directory'] ?? 'hotspot'));
    $dnsName = rs2_hotspot_dns_name($_POST['hotspot_dns_name'] ?? '');
    $wireguardManaged = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
    $auth = $wireguardManaged ? 'radius' : strtolower(trim((string) ($_POST['hotspot_auth_type'] ?? 'api')));

    $client = rs_mikrotik_configurator_client($router, 8);
    $client->sendSync(new RouterOS\Request('/system/identity/print'));

    // Manual HotSpot creation does not guarantee that the custom HTML folder
    // exists.  Create it explicitly before /tool/fetch later uploads login.html.
    rs2_ensure_hotspot_directory($client, $directory);

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

    // Deliberately no `mac-auth-mode`: it is not a HotSpot profile property.
    $request->setArgument('hotspot-address', $gatewayIp)
        ->setArgument('dns-name', $dnsName)
        ->setArgument('login-by', 'http-pap,http-chap,cookie')
        ->setArgument('http-cookie-lifetime', '3d')
        ->setArgument('html-directory', $directory)
        ->setArgument('use-radius', $auth === 'radius' ? 'yes' : 'no')
        ->setArgument('radius-accounting', $auth === 'radius' ? 'yes' : 'no')
        ->setArgument('radius-interim-update', 'received');
    $client->sendSync($request);

    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $item) {
        if ((string) $item->getProperty('name') === $profileName) {
            return;
        }
    }

    throw new RuntimeException('RouterOS rejected Hotspot profile ' . $profileName . '.');
}

function rs2_mikrotik_configurator_config_process()
{
    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found.');
    }

    $back = U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $routerId;
    try {
        rs2_prepare_hotspot_profile($router);
    } catch (Throwable $error) {
        if (function_exists('rs_mikrotik_configurator_log_failure')) {
            rs_mikrotik_configurator_log_failure($routerId, 'preparing RouterOS Hotspot profile', $error);
        }
        $message = function_exists('rs_mikrotik_configurator_safe_error')
            ? rs_mikrotik_configurator_safe_error($error->getMessage())
            : $error->getMessage();
        r2($back, 'e', 'Configuration stopped while preparing RouterOS Hotspot profile: ' . $message);
    }

    rs_mikrotik_configurator_config_process();
}
