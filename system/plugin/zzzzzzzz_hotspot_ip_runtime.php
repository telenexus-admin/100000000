<?php

/**
 * Final live Hotspot runtime for IP-based billing deployments.
 *
 * Fixes three production-only failures:
 *  - stale browser localStorage could override the baked billing IP with an old domain;
 *  - legacy walled-garden entries could point unauthenticated clients at an old billing domain;
 *  - the legacy package API used brittle router matching and an incorrectly indexed bandwidth map.
 *
 * RouterOS API is used only for configuration/file management. Customer auth remains RADIUS.
 */

use PEAR2\Net\RouterOS;

$rs8Route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
$rs8Type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';

// Take over only the public package-list request. Payment/verify/grant remain on
// CreateHotspotUser so the existing M-Pesa/RADIUS flow is untouched.
if ($rs8Route === 'plugin/CreateHotspotUser' && $rs8Type === 'hotspot_plans') {
    $_GET['_route'] = 'plugin/rs8_hotspot_plans';
}

// zzzzzzz_hotspot_live_portal_fix maps the configurator to rs7. This final
// layer owns the full live-portal publication step so the generated page can
// be made IP-authoritative before it is uploaded to RouterOS.
if ($rs8Route === 'plugin/rs7_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs8_mikrotik_configurator_config_process';
}

function rs8_json_response($payload, $status = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rs8_truthy($value)
{
    return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on', 'enabled'], true);
}

/**
 * Router-scoped package API used by the real captive page.
 * Response shape intentionally matches the legacy GetHotspotPlans() response.
 */
function rs8_hotspot_plans()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        rs8_json_response([], 204);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        rs8_json_response(['status' => 'error', 'message' => 'Invalid request method'], 405);
    }

    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        rs8_json_response(['status' => 'error', 'message' => 'Invalid JSON request'], 400);
    }

    $routerId = isset($input['router_id']) ? (int) $input['router_id'] : 0;
    if ($routerId <= 0) {
        rs8_json_response(['status' => 'error', 'message' => 'Missing router_id'], 400);
    }

    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        rs8_json_response(['status' => 'error', 'message' => 'Router not found'], 404);
    }

    $maintenance = ORM::for_table('tbl_appconfig')->where('setting', 'maintenance_mode')->find_one();
    if ($maintenance && rs8_truthy($maintenance['value'])) {
        rs8_json_response([
            'status' => 'error',
            'message' => 'Scheduled maintenance is currently in progress. Please check back soon.',
        ], 503);
    }

    $routerName = trim((string) $router['name']);
    if ($routerName === '') {
        rs8_json_response(['status' => 'error', 'message' => 'Router name is not configured'], 500);
    }

    // Keep tenant isolation: a plan must explicitly belong to this router (or
    // intentionally be marked all). Blank RADIUS router values are never exposed.
    $plans = ORM::for_table('tbl_plans')
        ->where('type', 'Hotspot')
        ->where('enabled', 1)
        ->where_raw(
            "(tbl_plans.routers = ? OR FIND_IN_SET(?, REPLACE(tbl_plans.routers, ' ', '')) > 0 OR tbl_plans.routers = 'all')",
            [$routerName, $routerName]
        )
        ->find_array();

    usort($plans, function ($a, $b) {
        $aOffer = stripos((string) ($a['name_plan'] ?? ''), 'offer') !== false;
        $bOffer = stripos((string) ($b['name_plan'] ?? ''), 'offer') !== false;
        if ($aOffer !== $bOffer) {
            return $aOffer ? -1 : 1;
        }
        $priceCmp = ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
        if ($priceCmp !== 0) {
            return $priceCmp;
        }
        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });

    $bwIds = [];
    foreach ($plans as $plan) {
        $id = (int) ($plan['id_bw'] ?? 0);
        if ($id > 0) {
            $bwIds[$id] = $id;
        }
    }

    $bandwidthMap = [];
    if ($bwIds) {
        $rows = ORM::for_table('tbl_bandwidth')->where_in('id', array_values($bwIds))->find_array();
        foreach ($rows as $row) {
            $bandwidthMap[(int) $row['id']] = $row;
        }
    }

    $currencyRow = ORM::for_table('tbl_appconfig')->where('setting', 'currency_code')->find_one();
    $currency = $currencyRow ? (string) $currencyRow['value'] : 'Ksh';
    $colorRow = ORM::for_table('tbl_appconfig')->where('setting', 'color_scheme')->find_one();
    $color = $colorRow ? (string) $colorRow['value'] : 'blue';
    $shapeRow = ORM::for_table('tbl_appconfig')->where('setting', 'shape_selector')->find_one();
    $shape = $shapeRow ? (string) $shapeRow['value'] : 'square';

    if ($shape === 'rectangle') {
        $shapeClass = 'w-80 h-48 rounded-lg';
    } elseif ($shape === 'circle') {
        $shapeClass = 'w-64 h-64 rounded-full';
    } elseif ($shape === 'oval') {
        $shapeClass = 'w-80 h-48 rounded-full';
    } elseif ($shape === 'square') {
        $shapeClass = 'w-64 h-64 rounded-lg';
    } else {
        $shapeClass = 'rounded-lg';
    }

    $items = [];
    foreach ($plans as $plan) {
        $bw = $bandwidthMap[(int) ($plan['id_bw'] ?? 0)] ?? [];
        $items[] = [
            'plantype' => (string) ($plan['type'] ?? 'Hotspot'),
            'planname' => (string) ($plan['name_plan'] ?? ''),
            'typebp' => (string) ($plan['typebp'] ?? ''),
            'currency' => $currency,
            'price' => $plan['price'] ?? 0,
            'validity' => $plan['validity'] ?? 0,
            'shared_users' => max(1, (int) ($plan['shared_users'] ?? 1)),
            'device' => (string) ($plan['device'] ?? ''),
            'datalimit' => $plan['data_limit'] ?? null,
            'timelimit' => $plan['validity_unit'] ?? null,
            'downlimit' => $bw['rate_down'] ?? null,
            'uplimit' => $bw['rate_up'] ?? null,
            'paymentlink' => '',
            'planId' => (int) ($plan['id'] ?? 0),
            'routerName' => $routerName,
            'routerId' => $routerId,
            'shape' => $shape,
            'shape_card_class_name' => $shapeClass,
            'color_scheme' => $color,
        ];
    }

    rs8_json_response([[
        'name' => $routerName,
        'router_id' => $routerId,
        'description' => (string) ($router['description'] ?? ''),
        'plans_hotspot' => $items,
    ]]);
}

function rs8_billing_url()
{
    $url = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    if ($url === '') {
        $row = ORM::for_table('tbl_appconfig')->where('setting', 'hotspot_billing_url')->find_one();
        $url = $row ? rtrim((string) $row['value'], '/') : '';
    }
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('A valid billing URL is required for the Hotspot portal.');
    }
    return $url;
}

function rs8_legacy_billing_hosts()
{
    return [
        'net.pamnetsolutions.co.ke',
        '*.net.pamnetsolutions.co.ke',
        'pamnetsolutions.co.ke',
        '*.pamnetsolutions.co.ke',
    ];
}

function rs8_remove_legacy_billing_walled_garden($client)
{
    $legacy = [];
    foreach (rs8_legacy_billing_hosts() as $host) {
        $legacy[strtolower(rtrim($host, '.'))] = true;
    }

    foreach (['/ip/hotspot/walled-garden/print' => '/ip/hotspot/walled-garden/remove',
              '/ip/hotspot/walled-garden/ip/print' => '/ip/hotspot/walled-garden/ip/remove'] as $printPath => $removePath) {
        try {
            foreach ($client->sendSync(new RouterOS\Request($printPath)) as $row) {
                $host = strtolower(rtrim(trim((string) $row->getProperty('dst-host')), '.'));
                $id = trim((string) $row->getProperty('.id'));
                if ($id === '' || $host === '' || !isset($legacy[$host])) {
                    continue;
                }
                try {
                    $remove = new RouterOS\Request($removePath);
                    $remove->setArgument('numbers', $id);
                    $client->sendSync($remove);
                } catch (Throwable $ignored) {
                }
            }
        } catch (Throwable $ignored) {
        }
    }
}

function rs8_ensure_billing_ip_walled_garden($client, $billingUrl)
{
    $parts = parse_url((string) $billingUrl);
    $host = is_array($parts) ? trim((string) ($parts['host'] ?? '')) : '';
    $scheme = is_array($parts) ? strtolower(trim((string) ($parts['scheme'] ?? 'http'))) : 'http';
    $port = is_array($parts) && isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    if ($host === '' || $port < 1 || $port > 65535) {
        throw new RuntimeException('Billing host/port is invalid.');
    }

    // Exact IP deployments get an exact IP/port bypass. This is the rule that
    // allows the unauthenticated captive page to call the package/payment API.
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $exists = false;
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $row) {
            $address = trim((string) $row->getProperty('dst-address'));
            $dstPort = trim((string) $row->getProperty('dst-port'));
            $protocol = strtolower(trim((string) $row->getProperty('protocol')));
            $action = strtolower(trim((string) $row->getProperty('action')));
            if (($address === $host || $address === $host . '/32')
                && ($dstPort === '' || $dstPort === (string) $port)
                && ($protocol === '' || $protocol === 'tcp' || $protocol === '6')
                && ($action === '' || $action === 'accept')) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $add = new RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
            $add->setArgument('action', 'accept')
                ->setArgument('protocol', 'tcp')
                ->setArgument('dst-address', $host)
                ->setArgument('dst-port', (string) $port)
                ->setArgument('disabled', 'no');
            $client->sendSync($add);
        }
    }

    // HTTP Host rule as a companion. For this installation the value itself is
    // the IP address, not a legacy domain.
    $hostExists = false;
    foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
        $dst = strtolower(rtrim(trim((string) $row->getProperty('dst-host')), '.'));
        $dstPort = trim((string) $row->getProperty('dst-port'));
        if ($dst === strtolower($host) && ($dstPort === '' || $dstPort === (string) $port)) {
            $hostExists = true;
            break;
        }
    }
    if (!$hostExists) {
        $addWeb = new RouterOS\Request('/ip/hotspot/walled-garden/add');
        $addWeb->setArgument('action', 'allow')
            ->setArgument('dst-host', $host)
            ->setArgument('dst-port', (string) $port)
            ->setArgument('disabled', 'no');
        $client->sendSync($addWeb);
    }
}

function rs8_set_portal_config($router, $billingUrl, $hotspotSubnet)
{
    $routerId = (int) ($router['id'] ?? 0);
    $routerName = trim((string) ($router['name'] ?? ''));
    if ($routerId <= 0 || $routerName === '') {
        throw new RuntimeException('Router identity is incomplete.');
    }

    $gateway = '10.0.0.1';
    if (function_exists('mikrotik_configurator_is_valid_cidr')
        && mikrotik_configurator_is_valid_cidr($hotspotSubnet)
        && function_exists('mikrotik_configurator_cidr_gateway')) {
        $cidr = mikrotik_configurator_cidr_gateway($hotspotSubnet);
        $candidate = explode('/', (string) $cidr, 2)[0];
        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $gateway = $candidate;
        }
    }

    foreach ([
        'router_id' => (string) $routerId,
        'router_name' => $routerName,
        'hotspot_login_url' => 'http://' . $gateway . '/login',
        'hotspot_billing_url' => $billingUrl,
    ] as $key => $value) {
        $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        if (!$row) {
            $row = ORM::for_table('tbl_appconfig')->create();
            $row->setting = $key;
        }
        $row->value = $value;
        $row->save();
    }
}

/** Force the generated portable page to use the server-selected IP/router. */
function rs8_patch_portal_html($html, $billingUrl, $routerId)
{
    $html = (string) $html;
    $billingJson = json_encode(rtrim((string) $billingUrl, '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $routerJson = json_encode((string) $routerId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $guard = <<<HTML
<script id="rs-authoritative-billing-runtime">
(function () {
  var authoritativeBase = {$billingJson};
  var authoritativeRouter = {$routerJson};
  try { localStorage.removeItem('pamnet_billing_base'); } catch (e) {}
  try {
    if (window.PAMNET_PORTAL) {
      window.PAMNET_PORTAL.apiBase = authoritativeBase;
      window.PAMNET_PORTAL.routerId = authoritativeRouter;
    }
    window.pamnetApi = function (type) {
      return authoritativeBase.replace(/\/+$/, '') + '/?_route=plugin/CreateHotspotUser&type=' + encodeURIComponent(type || '');
    };
  } catch (e2) {}
})();
</script>
HTML;

    // Remove an older copy if this file is patched repeatedly.
    $html = preg_replace('#<script id="rs-authoritative-billing-runtime">.*?</script>\s*#is', '', $html);
    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('#</head>#i', $guard . "\n</head>", $html, 1);
    } else {
        $html = $guard . "\n" . $html;
    }
    return $html;
}

/**
 * Final configurator handler. It reuses the proven RouterOS object creation
 * helpers, but owns portal publication and post-publication walled-garden state.
 */
function rs8_mikrotik_configurator_config_process()
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
    if (!$selectedPorts) {
        r2($back, 'e', 'Please select at least one MikroTik port.');
    }
    foreach ($selectedPorts as $port) {
        if (!preg_match('/^[A-Za-z0-9_.:@+\/-]{1,80}$/', $port)) {
            r2($back, 'e', 'One of the selected MikroTik port names is invalid.');
        }
    }

    $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
    $bridge = trim((string) ($_POST['bridge'] ?? ''));
    $bridgeHotspot = $sameBridge === 'yes' ? $bridge : trim((string) ($_POST['bridge_hotspot'] ?? ''));
    $bridgePppoe = $sameBridge === 'yes' ? $bridge : trim((string) ($_POST['bridge_pppoe'] ?? ''));
    if (in_array('hotspot', $services, true) && $bridgeHotspot === '') {
        r2($back, 'e', 'Hotspot bridge is required.');
    }
    if (in_array('pppoe', $services, true) && $bridgePppoe === '') {
        r2($back, 'e', 'PPPoE bridge is required.');
    }

    $hotspotSubnet = trim((string) ($_POST['subnet_hotspot'] ?? ''));
    $pppoeSubnet = trim((string) ($_POST['subnet_pppoe'] ?? ''));
    if (in_array('hotspot', $services, true) && (!function_exists('mikrotik_configurator_is_valid_cidr') || !mikrotik_configurator_is_valid_cidr($hotspotSubnet))) {
        r2($back, 'e', 'Invalid Hotspot subnet.');
    }
    if ($hotspotSubnet !== '' && $pppoeSubnet !== '' && $hotspotSubnet === $pppoeSubnet && function_exists('mikrotik_configurator_next_cidr')) {
        $pppoeSubnet = mikrotik_configurator_next_cidr($hotspotSubnet, 1);
    }
    if (in_array('pppoe', $services, true) && (!function_exists('mikrotik_configurator_is_valid_cidr') || !mikrotik_configurator_is_valid_cidr($pppoeSubnet))) {
        r2($back, 'e', 'Invalid PPPoE subnet.');
    }

    $hotspotRange = trim((string) ($_POST['hotspot_ip_range'] ?? ''));
    if (in_array('hotspot', $services, true) && $hotspotRange === '' && function_exists('mikrotik_configurator_default_range_from_subnet')) {
        $hotspotRange = mikrotik_configurator_default_range_from_subnet($hotspotSubnet);
    }
    $expiredPppoeSubnet = in_array('pppoe', $services, true) && function_exists('mikrotik_configurator_next_cidr')
        ? mikrotik_configurator_next_cidr($pppoeSubnet, 1)
        : '';

    $wireguard = strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
    $hotspotAuth = $wireguard ? 'radius' : strtolower(trim((string) ($_POST['hotspot_auth_type'] ?? 'api')));
    $pppoeAuth = $wireguard ? 'radius' : strtolower(trim((string) ($_POST['pppoe_auth_type'] ?? 'api')));
    $antiSharing = (($_POST['antiHotspotSharing'] ?? 'no') === 'yes') ? 'yes' : 'no';
    $dnsName = function_exists('rs2_hotspot_dns_name')
        ? rs2_hotspot_dns_name($_POST['hotspot_dns_name'] ?? '')
        : trim((string) ($_POST['hotspot_dns_name'] ?? 'hotspot.local'));
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
        $billingUrl = rs8_billing_url();
        rs8_set_portal_config($router, $billingUrl, $hotspotSubnet);

        $client = rs_mikrotik_configurator_client($router, 8);
        $client->sendSync(new RouterOS\Request('/system/identity/print'));

        if ($wireguard && in_array('hotspot', $services, true) && !rs_mikrotik_configurator_radius_client_ready($client, 'hotspot')) {
            throw new RuntimeException('Router has no enabled RADIUS client for Hotspot. Re-run Automatic Router Setup.');
        }
        if ($wireguard && in_array('pppoe', $services, true) && !rs_mikrotik_configurator_radius_client_ready($client, 'ppp')) {
            throw new RuntimeException('Router has no enabled RADIUS client for PPP. Re-run Automatic Router Setup.');
        }

        $stage = 'synchronizing router time';
        mikrotik_configurator_sync_timezone($client);

        $notes = [];
        if (in_array('hotspot', $services, true)) {
            // Pre-create the RouterOS-compatible profile so the legacy helper's
            // unsupported mac-auth-mode trap cannot prevent profile existence.
            $stage = 'preparing RouterOS Hotspot profile';
            if (function_exists('rs3_prepare_hotspot_profile')) {
                rs3_prepare_hotspot_profile($router);
            }

            $stage = 'creating Hotspot bridge DHCP pool profile and server';
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

            // Replace any legacy billing-domain rules immediately.
            $stage = 'configuring billing IP walled garden';
            rs8_remove_legacy_billing_walled_garden($client);
            rs8_ensure_billing_ip_walled_garden($client, $billingUrl);

            if (!function_exists('hotspot_settings_generate_login_html') || !function_exists('hotspot_settings_store_login_html')) {
                throw new RuntimeException('Hotspot file generator is not loaded.');
            }

            $stage = 'generating router-specific Hotspot login.html';
            $html = hotspot_settings_generate_login_html();
            $html = rs8_patch_portal_html($html, $billingUrl, $routerId);
            $stored = hotspot_settings_store_login_html($html, $billingUrl);

            $stage = 'uploading Hotspot login.html to RouterOS Files';
            $uploaded = rs_mikrotik_configurator_upload_login($client, $stored['url'], $htmlDirectory);

            // The older upload helper may re-add legacy defaults; make the final
            // RouterOS state IP-authoritative after the file is already present.
            $stage = 'finalizing billing IP walled garden';
            rs8_remove_legacy_billing_walled_garden($client);
            rs8_ensure_billing_ip_walled_garden($client, $billingUrl);

            $notes[] = 'Hotspot created; ' . $uploaded['path'] . ' uploaded; billing API bound to ' . parse_url($billingUrl, PHP_URL_HOST) . ':' . (parse_url($billingUrl, PHP_URL_PORT) ?: (parse_url($billingUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80)) . '.';
        }

        if (in_array('pppoe', $services, true)) {
            $stage = 'creating PPPoE bridge pools profile and server';
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
                rs_mikrotik_configurator_enforce_pppoe_radius($client);
            }
            $notes[] = 'PPPoE created and verified.';
        }

        $successUrl = $back . '&configured=' . rawurlencode(implode(',', $services));
        r2($successUrl, 's', 'Configuration applied successfully. ' . implode(' ', $notes));
    } catch (Throwable $error) {
        if (function_exists('rs_mikrotik_configurator_log_failure')) {
            rs_mikrotik_configurator_log_failure($routerId, $stage, $error);
        }
        $safe = function_exists('rs_mikrotik_configurator_safe_error')
            ? rs_mikrotik_configurator_safe_error($error->getMessage())
            : $error->getMessage();
        r2($back, 'e', 'Configuration stopped while ' . $stage . ': ' . $safe);
    }
}
