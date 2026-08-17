<?php

/**
 * MikroTik configurator reliability layer.
 *
 * Loaded after the legacy configurator (zz_ prefix). It keeps the existing UI
 * and helper functions, but routes configuration through a server-authoritative
 * handler so a stale AJAX status check cannot silently cancel Configure Router.
 *
 * For WireGuard-managed routers, authentication remains RADIUS-authoritative.
 * RouterOS API is used only to configure RouterOS and upload the Hotspot page.
 */

use PEAR2\Net\RouterOS;

$route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($route === 'plugin/mikrotik_configurator_config_ui') {
    $_GET['_route'] = 'plugin/rs_mikrotik_configurator_config_ui';
} elseif ($route === 'plugin/mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs_mikrotik_configurator_config_process';
}

function rs_mikrotik_configurator_require_admin()
{
    _admin();
    $admin = Admin::_info();
    if (!in_array($admin['user_type'] ?? '', ['SuperAdmin', 'Admin'], true)) {
        r2(getUrl('dashboard'), 'e', Lang::T('You Do Not Have Access'));
    }
    return $admin;
}

function rs_mikrotik_configurator_router_host($router)
{
    $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
    $wireguardIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
    $storedIp = trim((string) ($router['ip_address'] ?? ''));

    if ($transport === 'wireguard' && filter_var($wireguardIp, FILTER_VALIDATE_IP)) {
        return $wireguardIp;
    }
    return $storedIp;
}

function rs_mikrotik_configurator_client($router, $timeout = 8)
{
    $host = rs_mikrotik_configurator_router_host($router);
    $username = trim((string) ($router['username'] ?? ''));
    $password = (string) ($router['password'] ?? '');

    if ($host === '' || $username === '' || $password === '') {
        throw new RuntimeException('Router management connection is incomplete. Re-run Automatic Router Setup.');
    }

    ini_set('default_socket_timeout', (string) max(2, (int) $timeout));
    $client = Mikrotik::getClient($host, $username, $password);
    if (!$client) {
        throw new RuntimeException('RouterOS API client is unavailable.');
    }
    return $client;
}

function rs_mikrotik_configurator_safe_error($message)
{
    $message = trim((string) $message);
    if ($message === '') {
        return 'Unknown RouterOS error.';
    }

    $message = preg_replace('/\b(password|pass|secret)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', $message);
    return mb_substr((string) $message, 0, 500);
}

function rs_mikrotik_configurator_log_failure($routerId, $stage, Throwable $error)
{
    error_log(
        '[mikrotik-configurator] router=' . (int) $routerId
        . ' stage=' . preg_replace('/[^a-z0-9 _.-]/i', '', (string) $stage)
        . ' error=' . rs_mikrotik_configurator_safe_error($error->getMessage())
    );
}

function rs_mikrotik_configurator_radius_client_ready($client, $service)
{
    $wanted = strtolower(trim((string) $service));
    foreach ($client->sendSync(new RouterOS\Request('/radius/print')) as $item) {
        $disabled = strtolower(trim((string) $item->getProperty('disabled')));
        if ($disabled === 'true' || $disabled === 'yes') {
            continue;
        }
        $services = strtolower((string) $item->getProperty('service'));
        $parts = preg_split('/\s*,\s*/', $services, -1, PREG_SPLIT_NO_EMPTY);
        if (in_array($wanted, $parts ?: [], true)) {
            return true;
        }
    }
    return false;
}

function rs_mikrotik_configurator_enforce_hotspot_radius($client, $profileName)
{
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $item) {
        if ((string) $item->getProperty('name') !== $profileName) {
            continue;
        }

        $set = new RouterOS\Request('/ip/hotspot/profile/set');
        $set->setArgument('numbers', (string) $item->getProperty('.id'))
            ->setArgument('use-radius', 'yes')
            ->setArgument('radius-accounting', 'yes')
            ->setArgument('radius-interim-update', 'received')
            ->setArgument('login-by', 'http-pap,http-chap,cookie');
        $client->sendSync($set);
        return;
    }

    throw new RuntimeException('Hotspot profile ' . $profileName . ' was not found after configuration.');
}

function rs_mikrotik_configurator_enforce_pppoe_radius($client)
{
    $set = new RouterOS\Request('/ppp/aaa/set');
    $set->setArgument('use-radius', 'yes')
        ->setArgument('accounting', 'yes');
    $client->sendSync($set);
}

function rs_mikrotik_configurator_upload_login($client, $publicUrl, $htmlDirectory)
{
    $directory = trim((string) $htmlDirectory, "/\\");
    if ($directory === '' || !preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $directory) || strpos($directory, '..') !== false) {
        throw new RuntimeException('Invalid Hotspot HTML directory.');
    }

    $destination = $directory . '/login.html';

    /*
     * Router configuration must not wait for /tool/fetch.  On some RouterOS
     * builds the fetch/file operation can take long enough for the browser to
     * sit on "Configuring Router..." indefinitely even though bridge, DHCP,
     * HotSpot and RADIUS configuration already succeeded.
     *
     * zzzzzzzzzzzz_hotspot_direct_publish.php queues the V2 publisher after
     * the configuration request.  Keep the current live login.html untouched
     * here and let that worker publish/verify the replacement independently.
     */
    $routeNow = isset($_GET['_route']) ? (string) $_GET['_route'] : '';
    if (strpos($routeNow, 'mikrotik_configurator_config_process') !== false) {
        $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
        error_log('[mikrotik-configurator] router=' . $routerId . ' stage=portal publication queued');
        return [
            'path' => $destination,
            'size' => 'queued',
            'queued' => true,
        ];
    }

    try {
        $print = new RouterOS\Request('/file/print');
        $print->setQuery(RouterOS\Query::where('name', $destination));
        foreach ($client->sendSync($print) as $file) {
            $id = trim((string) $file->getProperty('.id'));
            if ($id === '') {
                continue;
            }
            $remove = new RouterOS\Request('/file/remove');
            $remove->setArgument('.id', $id);
            $client->sendSync($remove);
        }
    } catch (Throwable $ignored) {
        // Fetch below is authoritative; verification catches a failed upload.
    }

    $fetch = new RouterOS\Request('/tool/fetch');
    $fetch->setArgument('url', (string) $publicUrl)
        ->setArgument('dst-path', $destination)
        ->setArgument('keep-result', 'yes');
    if (stripos((string) $publicUrl, 'https://') === 0) {
        $fetch->setArgument('mode', 'https');
    } else {
        $fetch->setArgument('mode', 'http');
    }
    $client->sendSync($fetch);

    usleep(1200000);

    $found = false;
    $size = '';
    foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $file) {
        if ((string) $file->getProperty('name') === $destination) {
            $found = true;
            $size = trim((string) $file->getProperty('size'));
            break;
        }
    }

    if (!$found) {
        throw new RuntimeException('RouterOS did not create ' . $destination . ' after file upload.');
    }

    if (function_exists('hotspot_settings_ensure_walled_garden')) {
        hotspot_settings_ensure_walled_garden($client, defined('APP_URL') ? APP_URL : $publicUrl);
    }

    return [
        'path' => $destination,
        'size' => $size,
    ];
}

function rs_mikrotik_configurator_config_ui()
{
    ob_start();
    mikrotik_configurator_config_ui();
    $html = (string) ob_get_clean();

    // The original page can cancel submit based on a stale/failed AJAX preflight.
    // Remove only that submit handler. The POST handler performs the real API
    // connection check and returns a visible success/failure message.
    $old = <<<'JS'
    $('#mikrotikConfiguratorForm').on('submit', function(event) {
      if ($(this).attr('data-router-api-ready') !== '1') {
        event.preventDefault();
        $('#routerApiNotice').show();
        $('html, body').animate({ scrollTop: $('#routerApiNotice').offset().top - 20 }, 200);
      }
    });
JS;
    $new = <<<'JS'
    $('#mikrotikConfiguratorForm').on('submit', function() {
      var $form = $(this);
      var $button = $form.find('button[type="submit"]');
      $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Configuring Router...');
      $('#routerApiNotice')
        .removeClass('alert-warning alert-danger')
        .addClass('alert-info')
        .show();
      $('#routerApiNoticeText').text('Sending network and RADIUS configuration to the MikroTik. Portal files will publish separately; this page will return a success or failure result.');
    });
JS;

    if (strpos($html, $old) !== false) {
        $html = str_replace($old, $new, $html);
    } else {
        // Future template revisions: remove existing jQuery submit handlers only
        // for this form, then install the non-blocking progress handler.
        $fallback = <<<'HTML'
<script>
$(function () {
  var $form = $('#mikrotikConfiguratorForm');
  $form.off('submit');
  $form.on('submit', function () {
    var $button = $form.find('button[type="submit"]');
    $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Configuring Router...');
    $('#routerApiNotice').removeClass('alert-warning alert-danger').addClass('alert-info').show();
    $('#routerApiNoticeText').text('Sending network and RADIUS configuration to the MikroTik. Portal files will publish separately; this page will return a success or failure result.');
  });
});
</script>
HTML;
        $html .= $fallback;
    }

    echo $html;
}

function rs_mikrotik_configurator_config_process()
{
    rs_mikrotik_configurator_require_admin();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Invalid configuration request.');
    }

    $routerId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found.');
    }

    $back = U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $routerId;

    $services = $_POST['serviceType'] ?? ($_POST['service_type'] ?? []);
    if (!is_array($services)) {
        $services = [$services];
    }
    $services = array_values(array_unique(array_intersect(
        ['hotspot', 'pppoe'],
        array_map('strtolower', array_map('trim', $services))
    )));
    if (!$services) {
        r2($back, 'e', 'Please select at least one service type.');
    }

    $selectedPorts = $_POST['selected_ports'] ?? [];
    if (!is_array($selectedPorts)) {
        $selectedPorts = [$selectedPorts];
    }
    $selectedPorts = array_values(array_unique(array_filter(array_map('trim', $selectedPorts))));
    foreach ($selectedPorts as $port) {
        if (!preg_match('/^[A-Za-z0-9_.:@+\/-]{1,80}$/', $port)) {
            r2($back, 'e', 'One of the selected MikroTik port names is invalid.');
        }
    }
    if (!$selectedPorts) {
        r2($back, 'e', 'Please select at least one MikroTik port.');
    }

    $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
    $bridge = trim((string) ($_POST['bridge'] ?? ''));
    $bridgeHotspot = $sameBridge === 'yes' ? $bridge : trim((string) ($_POST['bridge_hotspot'] ?? ''));
    $bridgePppoe = $sameBridge === 'yes' ? $bridge : trim((string) ($_POST['bridge_pppoe'] ?? ''));

    foreach ([$bridgeHotspot, $bridgePppoe] as $bridgeName) {
        if ($bridgeName !== '' && !preg_match('/^[A-Za-z0-9_.:+\/-]{1,80}$/', $bridgeName)) {
            r2($back, 'e', 'Bridge name contains unsupported characters.');
        }
    }
    if (in_array('hotspot', $services, true) && $bridgeHotspot === '') {
        r2($back, 'e', 'Hotspot bridge is required.');
    }
    if (in_array('pppoe', $services, true) && $bridgePppoe === '') {
        r2($back, 'e', 'PPPoE bridge is required.');
    }

    $hotspotSubnet = trim((string) ($_POST['subnet_hotspot'] ?? ''));
    $pppoeSubnet = trim((string) ($_POST['subnet_pppoe'] ?? ''));
    if ($hotspotSubnet !== '' && $pppoeSubnet !== '' && $hotspotSubnet === $pppoeSubnet) {
        $pppoeSubnet = mikrotik_configurator_next_cidr($hotspotSubnet, 1);
    }
    if (in_array('hotspot', $services, true) && !mikrotik_configurator_is_valid_cidr($hotspotSubnet)) {
        r2($back, 'e', 'Invalid Hotspot subnet. Use a /16 private CIDR such as 10.20.0.0/16.');
    }
    if (in_array('pppoe', $services, true) && !mikrotik_configurator_is_valid_cidr($pppoeSubnet)) {
        r2($back, 'e', 'Invalid PPPoE subnet. Use a /16 private CIDR such as 10.30.0.0/16.');
    }

    $hotspotRange = trim((string) ($_POST['hotspot_ip_range'] ?? ''));
    if (in_array('hotspot', $services, true) && $hotspotRange === '') {
        $hotspotRange = mikrotik_configurator_default_range_from_subnet($hotspotSubnet);
    }
    $expiredPppoeSubnet = in_array('pppoe', $services, true)
        ? mikrotik_configurator_next_cidr($pppoeSubnet, 1)
        : '';

    $wireguardManaged = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
    $hotspotAuth = $wireguardManaged ? 'radius' : strtolower(trim((string) ($_POST['hotspot_auth_type'] ?? 'api')));
    $pppoeAuth = $wireguardManaged ? 'radius' : strtolower(trim((string) ($_POST['pppoe_auth_type'] ?? 'api')));
    $antiSharing = (($_POST['antiHotspotSharing'] ?? 'no') === 'yes') ? 'yes' : 'no';
    $dnsName = trim((string) ($_POST['hotspot_dns_name'] ?? ''));
    $htmlDirectory = trim((string) ($_POST['hotspot_html_directory'] ?? 'hotspot'));
    if ($htmlDirectory === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $htmlDirectory) || strpos($htmlDirectory, '..') !== false) {
        r2($back, 'e', 'Invalid Hotspot server directory.');
    }

    $hotspotPorts = [];
    $pppoePorts = [];
    if ($sameBridge === 'yes') {
        $hotspotPorts = $selectedPorts;
        $pppoePorts = $selectedPorts;
    } else {
        foreach ($selectedPorts as $port) {
            $assignment = strtolower(trim((string) ($_POST['port_service_' . $port] ?? 'both')));
            if ($assignment === 'both' || $assignment === 'hotspot') {
                $hotspotPorts[] = $port;
            }
            if ($assignment === 'both' || $assignment === 'pppoe') {
                $pppoePorts[] = $port;
            }
        }
    }
    if (in_array('hotspot', $services, true) && !$hotspotPorts) {
        r2($back, 'e', 'Hotspot is selected but no ports are assigned to Hotspot.');
    }
    if (in_array('pppoe', $services, true) && !$pppoePorts) {
        r2($back, 'e', 'PPPoE is selected but no ports are assigned to PPPoE.');
    }

    $stage = 'connecting to RouterOS API';
    try {
        $client = rs_mikrotik_configurator_client($router, 8);
        $client->sendSync(new RouterOS\Request('/system/identity/print'));

        if ($wireguardManaged && in_array('hotspot', $services, true) && !rs_mikrotik_configurator_radius_client_ready($client, 'hotspot')) {
            throw new RuntimeException('WireGuard is reachable, but the router has no enabled RADIUS client for Hotspot. Re-run Automatic Router Setup.');
        }
        if ($wireguardManaged && in_array('pppoe', $services, true) && !rs_mikrotik_configurator_radius_client_ready($client, 'ppp')) {
            throw new RuntimeException('WireGuard is reachable, but the router has no enabled RADIUS client for PPP. Re-run Automatic Router Setup.');
        }

        $stage = 'synchronizing router time';
        mikrotik_configurator_sync_timezone($client);

        $notes = [];
        if (in_array('hotspot', $services, true)) {
            $stage = 'creating Hotspot bridge, DHCP, pool, profile and server';
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

            if ($hotspotAuth === 'radius') {
                $stage = 'enforcing RADIUS Hotspot authentication and accounting';
                rs_mikrotik_configurator_enforce_hotspot_radius($client, $bridgeHotspot . '-Profile');
            }

            if (!function_exists('hotspot_settings_generate_login_html') || !function_exists('hotspot_settings_store_login_html')) {
                throw new RuntimeException('Hotspot file generator is not loaded.');
            }

            $stage = 'generating Hotspot login.html';
            $html = hotspot_settings_generate_login_html();
            $stored = hotspot_settings_store_login_html($html, rtrim((string) APP_URL, '/'));

            $stage = 'queueing Hotspot login.html publication';
            $uploaded = rs_mikrotik_configurator_upload_login($client, $stored['url'], $htmlDirectory);
            $notes[] = !empty($uploaded['queued'])
                ? 'Hotspot network created; portal publication queued in background.'
                : 'Hotspot created; ' . $uploaded['path'] . ' uploaded and verified.';
        }

        if (in_array('pppoe', $services, true)) {
            $stage = 'creating PPPoE bridge, pools, profile and server';
            mikrotik_configurator_apply_pppoe(
                $client,
                (string) $router['name'],
                $bridgePppoe,
                $pppoePorts,
                $pppoeSubnet,
                $expiredPppoeSubnet,
                $pppoeAuth
            );
            if ($pppoeAuth === 'radius') {
                $stage = 'enforcing RADIUS PPP authentication and accounting';
                rs_mikrotik_configurator_enforce_pppoe_radius($client);
            }
            $notes[] = 'PPPoE created and verified.';
        }

        $successUrl = $back . '&configured=' . rawurlencode(implode(',', $services));
        r2($successUrl, 's', 'Configuration applied successfully. ' . implode(' ', $notes));
    } catch (Throwable $error) {
        rs_mikrotik_configurator_log_failure($routerId, $stage, $error);
        $message = 'Configuration stopped while ' . $stage . ': ' . rs_mikrotik_configurator_safe_error($error->getMessage());
        r2($back, 'e', $message);
    }
}
