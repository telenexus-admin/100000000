<?php

/**
 * Live Hotspot portal reliability fix.
 *
 * Two production issues are handled here before the hardened MikroTik
 * configurator runs:
 *  1) download.php reads router_id/router_name/login URL from tbl_appconfig,
 *     so make those settings router-specific for the router currently being
 *     configured before login.html is generated.
 *  2) the billing API runs on a non-standard HTTP port (for example :8090).
 *     Unauthorized HotSpot clients need an explicit /ip hotspot
 *     walled-garden ip allow for that destination/port or XHR reports
 *     "Network error" even though Preview works from the billing server.
 */

use PEAR2\Net\RouterOS;

$route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($route === 'plugin/rs3_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs7_mikrotik_configurator_config_process';
}

function rs7_appconfig_set($key, $value)
{
    $key = trim((string) $key);
    if ($key === '') {
        return;
    }

    $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    if (!$row) {
        $row = ORM::for_table('tbl_appconfig')->create();
        $row->setting = $key;
    }
    $row->value = (string) $value;
    $row->save();
}

function rs7_hotspot_gateway_login_url()
{
    $subnet = trim((string) (_post('subnet_hotspot') ?: ''));
    if ($subnet !== '' && function_exists('mikrotik_configurator_is_valid_cidr')
        && mikrotik_configurator_is_valid_cidr($subnet)
        && function_exists('mikrotik_configurator_cidr_gateway')) {
        $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
        $gateway = explode('/', (string) $gatewayCidr, 2)[0];
        if (filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'http://' . $gateway . '/login';
        }
    }

    return 'http://10.0.0.1/login';
}

function rs7_prepare_router_specific_portal_settings($router)
{
    $routerId = (int) ($router['id'] ?? 0);
    $routerName = trim((string) ($router['name'] ?? ''));
    if ($routerId <= 0 || $routerName === '') {
        throw new RuntimeException('Router identity is incomplete for Hotspot portal generation.');
    }

    $billingUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    if ($billingUrl === '') {
        $saved = ORM::for_table('tbl_appconfig')->where('setting', 'hotspot_billing_url')->find_one();
        $billingUrl = $saved ? rtrim((string) $saved['value'], '/') : '';
    }
    if ($billingUrl === '') {
        throw new RuntimeException('Billing URL is not configured for the Hotspot portal.');
    }

    rs7_appconfig_set('router_id', (string) $routerId);
    rs7_appconfig_set('router_name', $routerName);
    rs7_appconfig_set('hotspot_login_url', rs7_hotspot_gateway_login_url());
    rs7_appconfig_set('hotspot_billing_url', $billingUrl);

    return $billingUrl;
}

function rs7_rule_matches_billing_api($row, $host, $port)
{
    $dstAddress = trim((string) $row->getProperty('dst-address'));
    $dstHost = strtolower(trim((string) $row->getProperty('dst-host')));
    $dstPort = trim((string) $row->getProperty('dst-port'));
    $protocol = strtolower(trim((string) $row->getProperty('protocol')));
    $action = strtolower(trim((string) $row->getProperty('action')));

    $hostMatches = filter_var($host, FILTER_VALIDATE_IP)
        ? ($dstAddress === $host)
        : ($dstHost === strtolower($host));

    if (!$hostMatches) {
        return false;
    }
    if ($dstPort !== '' && $dstPort !== (string) $port) {
        return false;
    }
    if ($protocol !== '' && $protocol !== 'tcp' && $protocol !== '6') {
        return false;
    }
    return $action === '' || $action === 'accept' || $action === 'allow';
}

function rs7_ensure_billing_api_walled_garden($client, $billingUrl)
{
    $parts = parse_url((string) $billingUrl);
    if (!is_array($parts)) {
        throw new RuntimeException('Billing URL could not be parsed for Hotspot walled garden.');
    }

    $host = trim((string) ($parts['host'] ?? ''));
    $scheme = strtolower(trim((string) ($parts['scheme'] ?? 'http')));
    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    if ($host === '' || $port < 1 || $port > 65535) {
        throw new RuntimeException('Billing host/port is invalid for Hotspot walled garden.');
    }

    // Keep the existing host/CDN whitelist as well.
    if (function_exists('hotspot_settings_ensure_walled_garden')) {
        hotspot_settings_ensure_walled_garden($client, $billingUrl);
    }

    $exists = false;
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $row) {
        if (rs7_rule_matches_billing_api($row, $host, $port)) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $add = new RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
        $add->setArgument('action', 'accept')
            ->setArgument('protocol', 'tcp')
            ->setArgument('dst-port', (string) $port);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $add->setArgument('dst-address', $host);
        } else {
            $add->setArgument('dst-host', $host);
        }
        $client->sendSync($add);
    }

    // HTTP/HTTPS host rule as a companion rule.  This is especially useful
    // when the billing service later moves back to 80/443.
    $webExists = false;
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
        $dst = strtolower(trim((string) $row->getProperty('dst-host')));
        $dstPort = trim((string) $row->getProperty('dst-port'));
        if ($dst === strtolower($host) && ($dstPort === '' || $dstPort === (string) $port)) {
            $webExists = true;
            break;
        }
    }
    if (!$webExists) {
        $addWeb = new RouterOS\Request('/ip/hotspot/walled-garden/add');
        $addWeb->setArgument('action', 'allow')
            ->setArgument('dst-host', $host)
            ->setArgument('dst-port', (string) $port);
        $client->sendSync($addWeb);
    }
}

function rs7_mikrotik_configurator_config_process()
{
    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found.');
    }

    $back = U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $routerId;

    try {
        $billingUrl = rs7_prepare_router_specific_portal_settings($router);
        $client = rs_mikrotik_configurator_client($router, 8);
        $client->sendSync(new RouterOS\Request('/system/identity/print'));
        rs7_ensure_billing_api_walled_garden($client, $billingUrl);
    } catch (Throwable $error) {
        if (function_exists('rs_mikrotik_configurator_log_failure')) {
            rs_mikrotik_configurator_log_failure($routerId, 'preparing live Hotspot portal connectivity', $error);
        }
        $message = function_exists('rs_mikrotik_configurator_safe_error')
            ? rs_mikrotik_configurator_safe_error($error->getMessage())
            : $error->getMessage();
        r2($back, 'e', 'Configuration stopped while preparing live Hotspot portal connectivity: ' . $message);
    }

    // Continue through the existing profile/server/file-upload hardening stack.
    rs3_mikrotik_configurator_config_process();
}
