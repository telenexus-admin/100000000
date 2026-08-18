<?php

/**
 * Final HotSpot captive-portal readiness layer.
 *
 * Goal: when an administrator selects Hotspot + ports in the dashboard, the
 * resulting MikroTik network must immediately behave like a captive portal.
 * RADIUS authentication happens after the portal opens; this layer only makes
 * DHCP/DNS/HotSpot interception and the login servlet deterministic.
 */

use PEAR2\Net\RouterOS;

$rs17PreviousConfiguratorHandler = '';
$rs17Route = trim((string) ($_GET['_route'] ?? ''));
if (strpos($rs17Route, 'plugin/') === 0
    && strpos($rs17Route, 'mikrotik_configurator_config_process') !== false
    && $rs17Route !== 'plugin/rs17_mikrotik_configurator_config_process') {
    $rs17PreviousConfiguratorHandler = substr($rs17Route, strlen('plugin/'));
    $_GET['_route'] = 'plugin/rs17_mikrotik_configurator_config_process';
}

function rs17_hotspot_selected()
{
    $services = $_POST['serviceType'] ?? ($_POST['service_type'] ?? []);
    if (!is_array($services)) {
        $services = [$services];
    }
    $services = array_map('strtolower', array_map('trim', $services));
    return in_array('hotspot', $services, true);
}

function rs17_hotspot_bridge_from_post()
{
    $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
    return $sameBridge === 'yes'
        ? trim((string) ($_POST['bridge'] ?? ''))
        : trim((string) ($_POST['bridge_hotspot'] ?? ''));
}

function rs17_safe_hotspot_dns_name($routerId, $routerName, $requested = '')
{
    $requested = strtolower(rtrim(trim((string) $requested), '.'));

    // .local is mDNS-special on phones and is a poor default for an HTTP
    // captive portal. Keep a valid administrator-supplied FQDN, otherwise use
    // a RouterOS-local FQDN which the HotSpot profile maps to its gateway.
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

    $slug = strtolower((string) preg_replace('/[^a-z0-9-]+/i', '-', trim((string) $routerName)));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'router-' . max(1, (int) $routerId);
    }
    $slug = substr($slug, 0, 40);
    return 'login.' . $slug . '.hotspot';
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

function rs17_ensure_dns_static($client, $dnsName, $gatewayIp)
{
    $matched = '';
    foreach ($client->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $row) {
        if (strtolower(rtrim(trim((string) $row->getProperty('name')), '.')) === strtolower($dnsName)) {
            $matched = trim((string) $row->getProperty('.id'));
            break;
        }
    }

    $req = new RouterOS\Request($matched === '' ? '/ip/dns/static/add' : '/ip/dns/static/set');
    if ($matched !== '') {
        $req->setArgument('numbers', $matched);
    }
    $req->setArgument('name', $dnsName)
        ->setArgument('address', $gatewayIp)
        ->setArgument('disabled', 'no')
        ->setArgument('comment', 'RS HotSpot captive portal DNS');
    $client->sendSync($req);
}

function rs17_file_exists($client, $path)
{
    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $row) {
        if (trim((string) $row->getProperty('name'), "/\\") === trim((string) $path, "/\\")) {
            return true;
        }
    }
    return false;
}

function rs17_write_small_file($client, $path, $contents)
{
    $id = '';
    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $row) {
        if (trim((string) $row->getProperty('name'), "/\\") === trim((string) $path, "/\\")) {
            $id = trim((string) $row->getProperty('.id'));
            break;
        }
    }

    if ($id === '') {
        $add = new RouterOS\Request('/file/add');
        $add->setArgument('name', $path)->setArgument('type', 'file');
        $client->sendSync($add);
        foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $row) {
            if (trim((string) $row->getProperty('name'), "/\\") === trim((string) $path, "/\\")) {
                $id = trim((string) $row->getProperty('.id'));
                break;
            }
        }
    }

    if ($id === '') {
        throw new RuntimeException('RouterOS did not create required HotSpot file ' . $path . '.');
    }

    $set = new RouterOS\Request('/file/set');
    $set->setArgument('numbers', $id)->setArgument('contents', $contents);
    $client->sendSync($set);
}

function rs17_repair_captive_portal($routerId, $bridgeName, $subnet, $dnsName, $htmlDirectory)
{
    $router = ORM::for_table('tbl_routers')->find_one((int) $routerId);
    if (!$router || $bridgeName === '' || $subnet === '') {
        return;
    }
    if (!function_exists('mikrotik_configurator_is_valid_cidr')
        || !mikrotik_configurator_is_valid_cidr($subnet)) {
        return;
    }

    $client = rs17_router_client($router);
    $profileName = $bridgeName . '-Profile';
    $serverName = $bridgeName . '-Server';
    $poolName = $bridgeName . '-hotspot-pool';
    $dhcpName = $bridgeName . '-dhcp';
    $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
    $gatewayIp = explode('/', $gatewayCidr, 2)[0];
    $wireguard = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';

    // Only repair a HotSpot that the normal configurator actually created.
    $serverId = rs17_find_id($client, '/ip/hotspot/print', 'name', $serverName);
    if ($serverId === '') {
        return;
    }

    // DHCP must always hand phones the HotSpot gateway as both gateway and DNS.
    $dnsSet = new RouterOS\Request('/ip/dns/set');
    $dnsSet->setArgument('allow-remote-requests', 'yes');
    $client->sendSync($dnsSet);

    $dhcpId = rs17_find_id($client, '/ip/dhcp-server/print', 'name', $dhcpName);
    if ($dhcpId !== '') {
        $dhcpSet = new RouterOS\Request('/ip/dhcp-server/set');
        $dhcpSet->setArgument('numbers', $dhcpId)
            ->setArgument('interface', $bridgeName)
            ->setArgument('address-pool', $poolName)
            ->setArgument('disabled', 'no');
        $client->sendSync($dhcpSet);
    }

    $networkId = rs17_find_id($client, '/ip/dhcp-server/network/print', 'address', $subnet);
    if ($networkId !== '') {
        $networkSet = new RouterOS\Request('/ip/dhcp-server/network/set');
        $networkSet->setArgument('numbers', $networkId)
            ->setArgument('gateway', $gatewayIp)
            ->setArgument('dns-server', $gatewayIp)
            ->setArgument('comment', 'Hotspot DHCP Network - ' . $bridgeName);
        $client->sendSync($networkSet);
    } else {
        $networkAdd = new RouterOS\Request('/ip/dhcp-server/network/add');
        $networkAdd->setArgument('address', $subnet)
            ->setArgument('gateway', $gatewayIp)
            ->setArgument('dns-server', $gatewayIp)
            ->setArgument('comment', 'Hotspot DHCP Network - ' . $bridgeName);
        $client->sendSync($networkAdd);
    }

    // Force a real FQDN and avoid HTTPS interception without a certificate.
    $profileId = rs17_find_id($client, '/ip/hotspot/profile/print', 'name', $profileName);
    if ($profileId === '') {
        throw new RuntimeException('HotSpot profile disappeared before captive-portal finalization.');
    }
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

    // A manually-created HotSpot can exist without its standard servlet files.
    // If login.html is missing, Reset HTML restores login/redirect/api defaults.
    $htmlDirectory = trim((string) $htmlDirectory, "/\\");
    $loginPath = $htmlDirectory . '/login.html';
    $apiPath = $htmlDirectory . '/api.json';
    if (!rs17_file_exists($client, $loginPath)) {
        $client->sendSync(new RouterOS\Request('/ip/hotspot/reset-html'));
    }

    // Preserve a customized login.html. If only api.json is missing, create the
    // RFC captive status file directly instead of resetting custom HTML.
    if (!rs17_file_exists($client, $apiPath)) {
        $apiJson = "{\n"
            . '  \"captive\": $(if logged-in == \'yes\')false$(else)true$(endif),' . "\n"
            . '  \"user-portal-url\": \"$(link-login-only)\",' . "\n"
            . '  \"can-extend-session\": true' . "\n"
            . "}\n";
        rs17_write_small_file($client, $apiPath, $apiJson);
    }

    // Connectivity-check hosts must NOT be whitelisted, otherwise Android/iOS
    // can report the WLAN as connected without ever opening the login portal.
    $billingHost = parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST);
    if (function_exists('pamnet_ensure_walled_garden_hosts')) {
        pamnet_ensure_walled_garden_hosts($client, (string) $billingHost);
    }
    if (function_exists('pamnet_ensure_hotspot_firewall_rules')) {
        pamnet_ensure_hotspot_firewall_rules($client, $bridgeName);
    }

    if (!rs17_file_exists($client, $loginPath)) {
        throw new RuntimeException('HotSpot login.html is still missing after captive-portal finalization.');
    }

    error_log(
        '[hotspot-captive-ready] router=' . (int) $routerId
        . ' bridge=' . $bridgeName
        . ' gateway=' . $gatewayIp
        . ' dns=' . $dnsName
        . ' login=' . $loginPath
        . ' api=' . $apiPath
        . ' status=ready'
    );
}

function rs17_mikrotik_configurator_config_process()
{
    global $rs17PreviousConfiguratorHandler;

    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $hotspot = rs17_hotspot_selected();

    if ($hotspot && $routerId > 0) {
        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        if ($router) {
            $dnsName = rs17_safe_hotspot_dns_name(
                $routerId,
                (string) ($router['name'] ?? ''),
                $_POST['hotspot_dns_name'] ?? ''
            );
            $_POST['hotspot_dns_name'] = $dnsName;

            $bridgeName = rs17_hotspot_bridge_from_post();
            $subnet = trim((string) ($_POST['subnet_hotspot'] ?? ''));
            $htmlDirectory = trim((string) ($_POST['hotspot_html_directory'] ?? 'hotspot'));
            if ($htmlDirectory === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $htmlDirectory)) {
                $htmlDirectory = 'hotspot';
                $_POST['hotspot_html_directory'] = $htmlDirectory;
            }

            // r2() exits after the normal configurator redirects. Shutdown
            // functions still run, giving us a final deterministic RouterOS
            // captive-portal pass before PHP finishes the request.
            register_shutdown_function(static function () use ($routerId, $bridgeName, $subnet, $dnsName, $htmlDirectory) {
                try {
                    rs17_repair_captive_portal($routerId, $bridgeName, $subnet, $dnsName, $htmlDirectory);
                } catch (Throwable $e) {
                    error_log(
                        '[hotspot-captive-ready] router=' . (int) $routerId
                        . ' status=failed error=' . substr(preg_replace('/[\r\n]+/', ' ', $e->getMessage()), 0, 400)
                    );
                }
            });
        }
    }

    $handler = trim((string) $rs17PreviousConfiguratorHandler);
    if ($handler !== '' && $handler !== __FUNCTION__ && function_exists($handler)) {
        $handler();
        return;
    }

    if (function_exists('rs_mikrotik_configurator_config_process')) {
        rs_mikrotik_configurator_config_process();
        return;
    }

    mikrotik_configurator_config_process();
}
