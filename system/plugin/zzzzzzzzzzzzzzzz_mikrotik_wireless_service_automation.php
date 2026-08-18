<?php

/**
 * Automatic wireless preparation for MikroTik service configuration.
 *
 * The main configurator already creates bridges, pools, DHCP, HotSpot/PPPoE,
 * NAT and RADIUS.  A selected legacy wireless interface, however, may still be
 * in station mode.  Bridging a station-mode wlan into a HotSpot bridge does not
 * turn the router into an access point, forcing administrators to repair wlan1
 * manually after using the server configurator.
 *
 * This late-loaded route wrapper prepares selected RouterOS wireless interfaces
 * before handing the request back to the authoritative configurator.
 */

use PEAR2\Net\RouterOS;

$rs16Route = trim((string) ($_GET['_route'] ?? ''));
if ($rs16Route === 'plugin/rs_mikrotik_configurator_config_process'
    || $rs16Route === 'plugin/mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs16_mikrotik_configurator_config_process';
}

function rs16_service_list_from_post()
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

function rs16_selected_ports_from_post()
{
    $ports = $_POST['selected_ports'] ?? [];
    if (!is_array($ports)) {
        $ports = [$ports];
    }
    $ports = array_values(array_unique(array_filter(array_map('trim', $ports))));
    return array_values(array_filter($ports, function ($port) {
        return (bool) preg_match('/^[A-Za-z0-9_.:@+\/-]{1,80}$/', (string) $port);
    }));
}

function rs16_port_services(array $selectedPorts, array $services)
{
    $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
    $map = [];

    foreach ($selectedPorts as $port) {
        if ($sameBridge === 'yes' || count($services) < 2) {
            $map[$port] = $services;
            continue;
        }

        $assignment = strtolower(trim((string) ($_POST['port_service_' . $port] ?? 'both')));
        $assigned = [];
        if (($assignment === 'both' || $assignment === 'hotspot') && in_array('hotspot', $services, true)) {
            $assigned[] = 'hotspot';
        }
        if (($assignment === 'both' || $assignment === 'pppoe') && in_array('pppoe', $services, true)) {
            $assigned[] = 'pppoe';
        }
        $map[$port] = $assigned;
    }

    return $map;
}

function rs16_safe_ssid($routerName, array $services)
{
    $base = preg_replace('/[^A-Za-z0-9_.-]+/', '-', trim((string) $routerName));
    $base = trim((string) $base, '-_.');
    if ($base === '') {
        $base = 'MikroTik';
    }

    $suffix = in_array('hotspot', $services, true)
        ? (in_array('pppoe', $services, true) ? '-ACCESS' : '-HOTSPOT')
        : '-PPPOE';

    return substr($base . $suffix, 0, 32);
}

/**
 * Return names of legacy /interface wireless radios.
 * Devices without the legacy wireless package simply return an empty set.
 */
function rs16_legacy_wireless_names($client)
{
    $names = [];
    try {
        foreach ($client->sendSync(new RouterOS\Request('/interface/wireless/print')) as $item) {
            $name = trim((string) $item->getProperty('name'));
            if ($name !== '') {
                $names[$name] = true;
            }
        }
    } catch (Throwable $ignored) {
        // Not every RouterOS build has the legacy wireless menu.
    }
    return $names;
}

function rs16_prepare_selected_wireless($client, $router, array $services, array $selectedPorts)
{
    if (!$services || !$selectedPorts) {
        return [];
    }

    $wireless = rs16_legacy_wireless_names($client);
    if (!$wireless) {
        return [];
    }

    $assignments = rs16_port_services($selectedPorts, $services);
    $prepared = [];

    foreach ($assignments as $port => $portServices) {
        if (!isset($wireless[$port]) || !$portServices) {
            continue;
        }

        $ssid = rs16_safe_ssid((string) ($router['name'] ?? 'MikroTik'), $portServices);
        $matchedId = '';
        foreach ($client->sendSync(new RouterOS\Request('/interface/wireless/print')) as $item) {
            if ((string) $item->getProperty('name') === $port) {
                $matchedId = trim((string) $item->getProperty('.id'));
                break;
            }
        }
        if ($matchedId === '') {
            throw new RuntimeException('Selected wireless interface ' . $port . ' disappeared before configuration.');
        }

        $set = new RouterOS\Request('/interface/wireless/set');
        $set->setArgument('numbers', $matchedId)
            ->setArgument('mode', 'ap-bridge')
            ->setArgument('ssid', $ssid)
            ->setArgument('disabled', 'no')
            ->setArgument('default-authentication', 'yes')
            ->setArgument('default-forwarding', 'yes');
        $client->sendSync($set);

        // Verify that RouterOS accepted the AP settings instead of returning a
        // command trap that the API library did not throw as an exception.
        $verified = false;
        foreach ($client->sendSync(new RouterOS\Request('/interface/wireless/print')) as $item) {
            if ((string) $item->getProperty('name') !== $port) {
                continue;
            }
            $mode = strtolower(trim((string) $item->getProperty('mode')));
            $disabled = strtolower(trim((string) $item->getProperty('disabled')));
            $actualSsid = trim((string) $item->getProperty('ssid'));
            $verified = ($mode === 'ap-bridge')
                && !in_array($disabled, ['true', 'yes'], true)
                && ($actualSsid === $ssid);
            break;
        }

        if (!$verified) {
            throw new RuntimeException('RouterOS did not switch ' . $port . ' into access-point mode.');
        }

        $prepared[$port] = $ssid;
    }

    return $prepared;
}

function rs16_mikrotik_configurator_config_process()
{
    if (function_exists('rs_mikrotik_configurator_require_admin')) {
        rs_mikrotik_configurator_require_admin();
    } else {
        _admin();
    }

    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $services = rs16_service_list_from_post();
    $selectedPorts = rs16_selected_ports_from_post();

    if ($routerId > 0 && $services && $selectedPorts) {
        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        if ($router) {
            try {
                $client = function_exists('rs_mikrotik_configurator_client')
                    ? rs_mikrotik_configurator_client($router, 8)
                    : Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

                $prepared = rs16_prepare_selected_wireless($client, $router, $services, $selectedPorts);
                if ($prepared) {
                    error_log(
                        '[mikrotik-service-wireless] router=' . $routerId
                        . ' prepared=' . implode(',', array_keys($prepared))
                        . ' ssid=' . implode(',', array_values($prepared))
                    );
                }
            } catch (Throwable $e) {
                $back = U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $routerId;
                $message = function_exists('rs_mikrotik_configurator_safe_error')
                    ? rs_mikrotik_configurator_safe_error($e->getMessage())
                    : trim((string) $e->getMessage());
                error_log(
                    '[mikrotik-service-wireless] router=' . $routerId
                    . ' error=' . $message
                );
                r2($back, 'e', 'Configuration stopped while preparing selected wireless ports: ' . $message);
            }
        }
    }

    if (function_exists('rs_mikrotik_configurator_config_process')) {
        rs_mikrotik_configurator_config_process();
        return;
    }

    mikrotik_configurator_config_process();
}
