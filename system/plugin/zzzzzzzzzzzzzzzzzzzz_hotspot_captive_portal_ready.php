<?php

/**
 * Final server-authoritative MikroTik service configurator.
 *
 * HotSpot success means more than "objects exist": the selected client ports
 * must feed the HotSpot bridge, DHCP/DNS must point clients at the gateway,
 * RouterOS must have a working login servlet, connectivity-check hosts must be
 * intercepted, and the HotSpot server must be enabled. Only then does the
 * dashboard return success. RADIUS remains the authentication authority for
 * WireGuard-managed routers.
 */

use PEAR2\Net\RouterOS;

$rs17Route = trim((string) ($_GET['_route'] ?? ''));
if (strpos($rs17Route, 'plugin/') === 0
    && strpos($rs17Route, 'mikrotik_configurator_config_process') !== false
    && $rs17Route !== 'plugin/rs17_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs17_mikrotik_configurator_config_process';
}

function rs17_services_from_post()
{
    $services = $_POST['serviceType'] ?? ($_POST['service_type'] ?? []);
    if (!is_array($services)) {
        $services = [$services];
    }
    return array_values(array_unique(array_intersect(
        ['hotspot', 'pppoe'],
        array_map('strtolower', array_map('trim', $services))
    )));
}

function rs17_ports_from_post()
{
    $ports = $_POST['selected_ports'] ?? [];
    if (!is_array($ports)) {
        $ports = [$ports];
    }
    $ports = array_values(array_unique(array_filter(array_map('trim', $ports))));
    foreach ($ports as $port) {
        if (!preg_match('/^[A-Za-z0-9_.:@+\/-]{1,80}$/', $port)) {
            throw new RuntimeException('One of the selected MikroTik port names is invalid.');
        }
    }
    return $ports;
}

function rs17_port_assignments(array $selectedPorts, array $services, $sameBridge)
{
    $hotspot = [];
    $pppoe = [];

    if ($sameBridge === 'yes' || count($services) < 2) {
        if (in_array('hotspot', $services, true)) {
            $hotspot = $selectedPorts;
        }
        if (in_array('pppoe', $services, true)) {
            $pppoe = $selectedPorts;
        }
        return [$hotspot, $pppoe];
    }

    foreach ($selectedPorts as $port) {
        $assignment = strtolower(trim((string) ($_POST['port_service_' . $port] ?? 'both')));
        if (($assignment === 'both' || $assignment === 'hotspot') && in_array('hotspot', $services, true)) {
            $hotspot[] = $port;
        }
        if (($assignment === 'both' || $assignment === 'pppoe') && in_array('pppoe', $services, true)) {
            $pppoe[] = $port;
        }
    }
    return [$hotspot, $pppoe];
}

function rs17_safe_hotspot_dns_name($routerId, $routerName, $requested = '')
{
    $requested = strtolower(rtrim(trim((string) $requested), '.'));
    $valid = $requested !== ''
        && strlen($requested) <= 253
        && strpos($requested, '.') !== false
        && substr($requested, -6) !== '.local';

    if ($valid) {
        foreach (explode('.', $requested) as $label) {
            if ($label === '' || strlen($label) > 63
                || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                $valid = false;
                break;
            }
        }
    }
    if ($valid) {
        return $requested;
    }

    // Avoid .local because phones treat it as mDNS. RouterOS maps this FQDN to
    // the HotSpot gateway locally, so public DNS is not required for the login.
    $slug = strtolower((string) preg_replace('/[^a-z0-9-]+/i', '-', trim((string) $routerName)));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'router-' . max(1, (int) $routerId);
    }
    return 'login.' . substr($slug, 0, 40) . '.hotspot';
}

function rs17_router_client($router)
{
    if (function_exists('rs_mikrotik_configurator_client')) {
        return rs_mikrotik_configurator_client($router, 8);
    }
    return Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
}

function rs17_find_id($client, $printPath, $property, $value)
{
    foreach ($client->sendSync(new RouterOS\Request($printPath)) as $row) {
        if ((string) $row->getProperty($property) === (string) $value) {
            return trim((string) $row->getProperty('.id'));
        }
    }
    return '';
}

function rs17_file_exists($client, $path)
{
    $wanted = trim((string) $path, "/\\");
    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $row) {
        if (trim((string) $row->getProperty('name'), "/\\") === $wanted) {
            return true;
        }
    }
    return false;
}

function rs17_write_small_file($client, $path, $contents)
{
    $wanted = trim((string) $path, "/\\");
    $id = '';
    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $row) {
        if (trim((string) $row->getProperty('name'), "/\\") === $wanted) {
            $id = trim((string) $row->getProperty('.id'));
            break;
        }
    }

    if ($id === '') {
        $add = new RouterOS\Request('/file/add');
        $add->setArgument('name', $wanted)->setArgument('type', 'file');
        $client->sendSync($add);
        foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $row) {
            if (trim((string) $row->getProperty('name'), "/\\") === $wanted) {
                $id = trim((string) $row->getProperty('.id'));
                break;
            }
        }
    }

    if ($id === '') {
        throw new RuntimeException('RouterOS did not create required HotSpot file ' . $wanted . '.');
    }

    $set = new RouterOS\Request('/file/set');
    $set->setArgument('numbers', $id)->setArgument('contents', (string) $contents);
    $client->sendSync($set);
}

function rs17_ensure_dns_static($client, $dnsName, $gatewayIp)
{
    $id = '';
    foreach ($client->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $row) {
        if (strtolower(rtrim(trim((string) $row->getProperty('name')), '.')) === strtolower($dnsName)) {
            $id = trim((string) $row->getProperty('.id'));
            break;
        }
    }

    $req = new RouterOS\Request($id === '' ? '/ip/dns/static/add' : '/ip/dns/static/set');
    if ($id !== '') {
        $req->setArgument('numbers', $id);
    }
    $req->setArgument('name', $dnsName)
        ->setArgument('address', $gatewayIp)
        ->setArgument('disabled', 'no')
        ->setArgument('comment', 'RS HotSpot captive portal DNS');
    $client->sendSync($req);
}

function rs17_purge_captive_probe_bypasses($client)
{
    if (!function_exists('pamnet_captive_probe_hosts')) {
        return;
    }

    $probe = [];
    foreach (pamnet_captive_probe_hosts() as $host) {
        $probe[strtolower(rtrim(trim((string) $host), '.'))] = true;
    }

    $menus = [
        ['/ip/hotspot/walled-garden/print', '/ip/hotspot/walled-garden/remove'],
        ['/ip/hotspot/walled-garden/ip/print', '/ip/hotspot/walled-garden/ip/remove'],
    ];
    foreach ($menus as [$printPath, $removePath]) {
        foreach ($client->sendSync(new RouterOS\Request($printPath)) as $row) {
            $host = strtolower(rtrim(trim((string) $row->getProperty('dst-host')), '.'));
            $id = trim((string) $row->getProperty('.id'));
            if ($id === '' || $host === '' || !isset($probe[$host])) {
                continue;
            }
            $remove = new RouterOS\Request($removePath);
            $remove->setArgument('numbers', $id);
            $client->sendSync($remove);
        }
    }
}

function rs17_finalize_captive_portal($client, $router, $bridgeName, $subnet, $dnsName, $htmlDirectory)
{
    $profileName = $bridgeName . '-Profile';
    $serverName = $bridgeName . '-Server';
    $poolName = $bridgeName . '-hotspot-pool';
    $dhcpName = $bridgeName . '-dhcp';
    $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
    $gatewayIp = explode('/', $gatewayCidr, 2)[0];
    $wireguard = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';

    $serverId = rs17_find_id($client, '/ip/hotspot/print', 'name', $serverName);
    $profileId = rs17_find_id($client, '/ip/hotspot/profile/print', 'name', $profileName);
    if ($serverId === '' || $profileId === '') {
        throw new RuntimeException('RouterOS did not create the required HotSpot server/profile.');
    }

    // DHCP/DNS are part of the captive portal contract, not optional extras.
    $dnsSet = new RouterOS\Request('/ip/dns/set');
    $dnsSet->setArgument('allow-remote-requests', 'yes');
    $client->sendSync($dnsSet);

    $dhcpId = rs17_find_id($client, '/ip/dhcp-server/print', 'name', $dhcpName);
    if ($dhcpId === '') {
        throw new RuntimeException('RouterOS did not create the HotSpot DHCP server.');
    }
    $dhcpSet = new RouterOS\Request('/ip/dhcp-server/set');
    $dhcpSet->setArgument('numbers', $dhcpId)
        ->setArgument('interface', $bridgeName)
        ->setArgument('address-pool', $poolName)
        ->setArgument('disabled', 'no');
    $client->sendSync($dhcpSet);

    $networkId = rs17_find_id($client, '/ip/dhcp-server/network/print', 'address', $subnet);
    if ($networkId === '') {
        $networkAdd = new RouterOS\Request('/ip/dhcp-server/network/add');
        $networkAdd->setArgument('address', $subnet)
            ->setArgument('gateway', $gatewayIp)
            ->setArgument('dns-server', $gatewayIp)
            ->setArgument('comment', 'Hotspot DHCP Network - ' . $bridgeName);
        $client->sendSync($networkAdd);
    } else {
        $networkSet = new RouterOS\Request('/ip/dhcp-server/network/set');
        $networkSet->setArgument('numbers', $networkId)
            ->setArgument('gateway', $gatewayIp)
            ->setArgument('dns-server', $gatewayIp)
            ->setArgument('comment', 'Hotspot DHCP Network - ' . $bridgeName);
        $client->sendSync($networkSet);
    }

    // No certificate is provisioned here, so avoid HTTPS interception/cert
    // mismatch. Android/iOS HTTP connectivity probes are still intercepted.
    $profileSet = new RouterOS\Request('/ip/hotspot/profile/set');
    $profileSet->setArgument('numbers', $profileId)
        ->setArgument('hotspot-address', $gatewayIp)
        ->setArgument('dns-name', $dnsName)
        ->setArgument('html-directory', $htmlDirectory)
        ->setArgument('login-by', 'http-pap,http-chap,cookie')
        ->setArgument('https-redirect', 'no')
        ->setArgument('use-radius', $wireguard ? 'yes' : 'no')
        ->setArgument('radius-accounting', $wireguard ? 'yes' : 'no')
        ->setArgument('radius-interim-update', 'received');
    $client->sendSync($profileSet);
    rs17_ensure_dns_static($client, $dnsName, $gatewayIp);

    $serverSet = new RouterOS\Request('/ip/hotspot/set');
    $serverSet->setArgument('numbers', $serverId)
        ->setArgument('interface', $bridgeName)
        ->setArgument('address-pool', $poolName)
        ->setArgument('profile', $profileName)
        ->setArgument('disabled', 'no');
    $client->sendSync($serverSet);

    $htmlDirectory = trim((string) $htmlDirectory, "/\\");
    $loginPath = $htmlDirectory . '/login.html';
    $apiPath = $htmlDirectory . '/api.json';

    // Manual /ip/hotspot add can leave a valid HotSpot with no servlet pages.
    // Restore RouterOS defaults only when login.html is absent, preserving an
    // existing customized portal on routers already in production.
    if (!rs17_file_exists($client, $loginPath)) {
        $client->sendSync(new RouterOS\Request('/ip/hotspot/reset-html'));
    }
    if (!rs17_file_exists($client, $loginPath)) {
        throw new RuntimeException('HotSpot login.html is missing even after Reset HTML.');
    }

    // RouterOS 7 captive detection expects api.json. Do not reset a customized
    // login page just because api.json is missing; create only the missing file.
    if (!rs17_file_exists($client, $apiPath)) {
        $apiJson = "{\n"
            . '  "captive": $(if logged-in == \'yes\')false$(else)true$(endif),' . "\n"
            . '  "user-portal-url": "$(link-login-only)",' . "\n"
            . '  "can-extend-session": true' . "\n"
            . "}\n";
        rs17_write_small_file($client, $apiPath, $apiJson);
    }

    // Probe hosts must reach HotSpot interception. Whitelisting them produces
    // the exact failure "Connected, no Internet" with no Sign-In popup.
    rs17_purge_captive_probe_bypasses($client);

    // Remove old billing-domain exceptions, then permit only the current billing
    // endpoint required by the branded portal while unauthenticated.
    if (function_exists('rs8_remove_legacy_billing_walled_garden')) {
        rs8_remove_legacy_billing_walled_garden($client);
    }
    if (function_exists('rs8_billing_url') && function_exists('rs8_ensure_billing_ip_walled_garden')) {
        rs8_ensure_billing_ip_walled_garden($client, rs8_billing_url());
    }
    if (function_exists('pamnet_ensure_hotspot_firewall_rules')) {
        pamnet_ensure_hotspot_firewall_rules($client, $bridgeName);
    }

    // Final object verification before the dashboard is allowed to say success.
    $verified = false;
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $row) {
        if ((string) $row->getProperty('name') !== $serverName) {
            continue;
        }
        $disabled = strtolower(trim((string) $row->getProperty('disabled')));
        $invalid = strtolower(trim((string) $row->getProperty('invalid')));
        $verified = (string) $row->getProperty('interface') === $bridgeName
            && !in_array($disabled, ['yes', 'true'], true)
            && !in_array($invalid, ['yes', 'true'], true);
        break;
    }
    if (!$verified) {
        throw new RuntimeException('HotSpot exists but RouterOS reports it disabled/invalid. Check RouterOS device-mode if necessary.');
    }

    error_log(
        '[hotspot-captive-ready] router=' . (int) $router->id()
        . ' bridge=' . $bridgeName
        . ' gateway=' . $gatewayIp
        . ' dns=' . $dnsName
        . ' login=' . $loginPath
        . ' api=' . $apiPath
        . ' status=ready'
    );

    return [
        'gateway' => $gatewayIp,
        'dns_name' => $dnsName,
        'login_path' => $loginPath,
        'api_path' => $apiPath,
    ];
}

function rs17_mikrotik_configurator_config_process()
{
    if (function_exists('rs_mikrotik_configurator_require_admin')) {
        rs_mikrotik_configurator_require_admin();
    } else {
        _admin();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Invalid configuration request.');
    }

    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found.');
    }
    $back = U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $routerId;

    try {
        $services = rs17_services_from_post();
        $selectedPorts = rs17_ports_from_post();
        if (!$services) {
            throw new RuntimeException('Please select at least one service type.');
        }
        if (!$selectedPorts) {
            throw new RuntimeException('Please select at least one MikroTik port.');
        }

        $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
        $bridge = trim((string) ($_POST['bridge'] ?? ''));
        $bridgeHotspot = $sameBridge === 'yes' ? $bridge : trim((string) ($_POST['bridge_hotspot'] ?? ''));
        $bridgePppoe = $sameBridge === 'yes' ? $bridge : trim((string) ($_POST['bridge_pppoe'] ?? ''));
        foreach ([$bridgeHotspot, $bridgePppoe] as $bridgeName) {
            if ($bridgeName !== '' && !preg_match('/^[A-Za-z0-9_.:+\/-]{1,80}$/', $bridgeName)) {
                throw new RuntimeException('Bridge name contains unsupported characters.');
            }
        }
        if (in_array('hotspot', $services, true) && $bridgeHotspot === '') {
            throw new RuntimeException('Hotspot bridge is required.');
        }
        if (in_array('pppoe', $services, true) && $bridgePppoe === '') {
            throw new RuntimeException('PPPoE bridge is required.');
        }

        $hotspotSubnet = trim((string) ($_POST['subnet_hotspot'] ?? ''));
        $pppoeSubnet = trim((string) ($_POST['subnet_pppoe'] ?? ''));
        if ($hotspotSubnet !== '' && $pppoeSubnet !== '' && $hotspotSubnet === $pppoeSubnet) {
            $pppoeSubnet = mikrotik_configurator_next_cidr($hotspotSubnet, 1);
        }
        if (in_array('hotspot', $services, true) && !mikrotik_configurator_is_valid_cidr($hotspotSubnet)) {
            throw new RuntimeException('Invalid Hotspot subnet. Use a /16 private CIDR such as 10.20.0.0/16.');
        }
        if (in_array('pppoe', $services, true) && !mikrotik_configurator_is_valid_cidr($pppoeSubnet)) {
            throw new RuntimeException('Invalid PPPoE subnet. Use a /16 private CIDR such as 10.30.0.0/16.');
        }

        $hotspotRange = trim((string) ($_POST['hotspot_ip_range'] ?? ''));
        if (in_array('hotspot', $services, true) && $hotspotRange === '') {
            $hotspotRange = mikrotik_configurator_default_range_from_subnet($hotspotSubnet);
        }
        $pppoeExpiredSubnet = in_array('pppoe', $services, true)
            ? mikrotik_configurator_next_cidr($pppoeSubnet, 1)
            : '';

        [$hotspotPorts, $pppoePorts] = rs17_port_assignments($selectedPorts, $services, $sameBridge);
        if (in_array('hotspot', $services, true) && !$hotspotPorts) {
            throw new RuntimeException('Hotspot is selected but no ports are assigned to Hotspot.');
        }
        if (in_array('pppoe', $services, true) && !$pppoePorts) {
            throw new RuntimeException('PPPoE is selected but no ports are assigned to PPPoE.');
        }

        $wireguard = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
        $hotspotAuth = $wireguard ? 'radius' : strtolower(trim((string) ($_POST['hotspot_auth_type'] ?? 'api')));
        $pppoeAuth = $wireguard ? 'radius' : strtolower(trim((string) ($_POST['pppoe_auth_type'] ?? 'api')));
        $antiSharing = (($_POST['antiHotspotSharing'] ?? 'no') === 'yes') ? 'yes' : 'no';
        $htmlDirectory = trim((string) ($_POST['hotspot_html_directory'] ?? 'hotspot'));
        if ($htmlDirectory === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $htmlDirectory) || strpos($htmlDirectory, '..') !== false) {
            throw new RuntimeException('Invalid Hotspot server directory.');
        }
        $dnsName = rs17_safe_hotspot_dns_name(
            $routerId,
            (string) $router['name'],
            $_POST['hotspot_dns_name'] ?? ''
        );
        $_POST['hotspot_dns_name'] = $dnsName;

        $stage = 'connecting to RouterOS API';
        $client = rs17_router_client($router);
        $client->sendSync(new RouterOS\Request('/system/identity/print'));

        if ($wireguard && in_array('hotspot', $services, true)
            && function_exists('rs_mikrotik_configurator_radius_client_ready')
            && !rs_mikrotik_configurator_radius_client_ready($client, 'hotspot')) {
            throw new RuntimeException('Router has no enabled RADIUS client for Hotspot. Re-run Automatic Router Setup.');
        }
        if ($wireguard && in_array('pppoe', $services, true)
            && function_exists('rs_mikrotik_configurator_radius_client_ready')
            && !rs_mikrotik_configurator_radius_client_ready($client, 'ppp')) {
            throw new RuntimeException('Router has no enabled RADIUS client for PPP. Re-run Automatic Router Setup.');
        }

        $stage = 'preparing selected wireless ports';
        if (function_exists('rs16_prepare_selected_wireless')) {
            rs16_prepare_selected_wireless($client, $router, $services, $selectedPorts);
        }

        $stage = 'synchronizing router time';
        mikrotik_configurator_sync_timezone($client);

        $notes = [];
        if (in_array('hotspot', $services, true)) {
            $stage = 'creating Hotspot bridge DHCP pool profile and server';
            if (function_exists('rs3_prepare_hotspot_profile')) {
                rs3_prepare_hotspot_profile($router);
            }
            mikrotik_configurator_apply_hotspot(
                $client,
                (string) $router['name'],
                $bridgeHotspot,
                $hotspotPorts,
                $hotspotSubnet,
                $hotspotRange,
                $dnsName,
                $hotspotAuth,
                $antiSharing,
                $htmlDirectory
            );

            if ($hotspotAuth === 'radius' && function_exists('rs_mikrotik_configurator_enforce_hotspot_radius')) {
                $stage = 'enforcing RADIUS Hotspot authentication';
                rs_mikrotik_configurator_enforce_hotspot_radius($client, $bridgeHotspot . '-Profile');
            }

            $stage = 'finalizing captive portal detection and login servlet';
            $ready = rs17_finalize_captive_portal(
                $client,
                $router,
                $bridgeHotspot,
                $hotspotSubnet,
                $dnsName,
                $htmlDirectory
            );

            if (function_exists('rs8_set_portal_config') && function_exists('rs8_billing_url')) {
                rs8_set_portal_config($router, rs8_billing_url(), $hotspotSubnet);
            }
            if (function_exists('rs12_queue_router_publish')) {
                rs12_queue_router_publish($routerId, 'router-config');
            }

            $notes[] = 'Hotspot captive portal ready on ' . $ready['gateway'] . ' (' . $ready['dns_name'] . '); branded portal publication queued.';
        }

        if (in_array('pppoe', $services, true)) {
            $stage = 'creating PPPoE bridge pools profile and server';
            mikrotik_configurator_apply_pppoe(
                $client,
                (string) $router['name'],
                $bridgePppoe,
                $pppoePorts,
                $pppoeSubnet,
                $pppoeExpiredSubnet,
                $pppoeAuth
            );
            if ($pppoeAuth === 'radius' && function_exists('rs_mikrotik_configurator_enforce_pppoe_radius')) {
                rs_mikrotik_configurator_enforce_pppoe_radius($client);
            }
            $notes[] = 'PPPoE created and verified.';
        }

        $successUrl = $back . '&configured=' . rawurlencode(implode(',', $services));
        r2($successUrl, 's', 'Configuration applied successfully. ' . implode(' ', $notes));
    } catch (Throwable $error) {
        $stage = isset($stage) ? $stage : 'validating configuration';
        if (function_exists('rs_mikrotik_configurator_log_failure')) {
            rs_mikrotik_configurator_log_failure($routerId, $stage, $error);
        }
        $message = function_exists('rs_mikrotik_configurator_safe_error')
            ? rs_mikrotik_configurator_safe_error($error->getMessage())
            : trim((string) $error->getMessage());
        r2($back, 'e', 'Configuration stopped while ' . $stage . ': ' . $message);
    }
}
