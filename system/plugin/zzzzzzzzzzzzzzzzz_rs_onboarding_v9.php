<?php

/**
 * RS authoritative MikroTik onboarding v10.
 *
 * This supersedes the earlier v8/v9 delivery wrappers.  The important rule is
 * that retrying or refreshing onboarding MUST NOT rotate RouterOS API
 * credentials or the per-router RADIUS shared secret.  A partially-onboarded
 * router can therefore be repaired simply by running the newly displayed
 * command again.
 *
 * Flow:
 *   dashboard -> stable DB/API/RADIUS plan -> one RouterOS paste
 *   -> fetch full installer -> WireGuard peer activation
 *   -> API + RADIUS client configuration -> server-side completion verification
 *   -> only then mark the router Online and invalidate the activation token.
 */

use PEAR2\Net\RouterOS;

$rs10Route = trim((string) ($_GET['_route'] ?? ''));
if (in_array($rs10Route, [
    'plugin/rs_radius_wireguard_setup',
    'plugin/rs9_radius_wireguard_setup',
], true)) {
    $_GET['_route'] = 'plugin/rs10_radius_wireguard_setup';
}

// Keep one visible Automatic Router Setup workflow.
if (isset($menu_registered) && is_array($menu_registered)) {
    $menu_registered = array_values(array_filter($menu_registered, static function ($item) {
        $fn = is_array($item) ? (string) ($item['function'] ?? '') : '';
        return !in_array($fn, [
            'radius_wireguard_setup',
            'rs_radius_wireguard_setup',
            'rs9_radius_wireguard_setup',
            'rs10_radius_wireguard_setup',
        ], true);
    }));
}
register_menu(
    'Automatic Router Setup',
    true,
    'rs10_radius_wireguard_setup',
    'AFTER_NETWORKS',
    'fa fa-shield',
    '',
    'success',
    ['SuperAdmin', 'Admin']
);

function rs10_q($value)
{
    return str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '', ' '], (string) $value);
}

function rs10_valid_api_username($value)
{
    return (bool) preg_match('/^rswg_[a-f0-9]{12}$/', trim((string) $value));
}

function rs10_valid_api_password($value)
{
    return (bool) preg_match('/^[a-f0-9]{32}$/', trim((string) $value));
}

function rs10_valid_radius_secret($value)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{20,128}$/', trim((string) $value));
}

function rs10_radius_row($router, $tunnelIp)
{
    $db = ORM::get_db('radius');
    $shortName = rs_wg_short_name((int) $router->id(), (string) $router['name']);
    $stmt = $db->prepare(
        'SELECT id, nasname, shortname, secret FROM nas WHERE shortname = ? OR nasname = ? ORDER BY (shortname = ?) DESC LIMIT 1'
    );
    $stmt->execute([$shortName, $tunnelIp, $shortName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Return one stable API credential set and one stable RADIUS secret.
 * Existing values are reused; they are never silently rotated by a refresh.
 */
function rs10_ensure_stable_router_identity($router, $tunnelIp)
{
    $apiUser = trim((string) ($router['username'] ?? ''));
    $apiPass = trim((string) ($router['password'] ?? ''));
    $changedRouter = false;

    if (!rs10_valid_api_username($apiUser)) {
        $apiUser = 'rswg_' . substr(bin2hex(random_bytes(6)), 0, 12);
        $router->username = $apiUser;
        $changedRouter = true;
    }
    if (!rs10_valid_api_password($apiPass)) {
        $apiPass = bin2hex(random_bytes(16));
        $router->password = $apiPass;
        $changedRouter = true;
    }

    $router->ip_address = $tunnelIp;
    $router->wg_tunnel_ip = $tunnelIp;
    $router->management_transport = 'wireguard';
    $router->enabled = 1;
    if ($changedRouter || (string) $router['ip_address'] !== $tunnelIp) {
        $router->save();
    } else {
        // Persist transport/IP normalization even when credentials were already stable.
        $router->save();
    }

    $row = rs10_radius_row($router, $tunnelIp);
    $radiusSecret = $row ? trim((string) ($row['secret'] ?? '')) : '';
    if (!rs10_valid_radius_secret($radiusSecret)) {
        $radiusSecret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    $shortName = rs_wg_short_name((int) $router->id(), (string) $router['name']);
    $nasNeedsUpdate = !$row
        || trim((string) ($row['nasname'] ?? '')) !== $tunnelIp
        || trim((string) ($row['shortname'] ?? '')) !== $shortName
        || trim((string) ($row['secret'] ?? '')) !== $radiusSecret;

    if ($nasNeedsUpdate) {
        // This now reloads the dedicated /etc/freeradius-rs runtime.
        rs_wg_upsert_nas($router, $tunnelIp, $radiusSecret);
    }

    return [
        'api_user' => $apiUser,
        'api_pass' => $apiPass,
        'radius_secret' => $radiusSecret,
    ];
}

function rs10_new_token()
{
    return rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
}

function rs10_prepare_plan($router)
{
    rs_wg_ensure_schema();
    $routerId = (int) $router->id();
    if ($routerId <= 0) {
        throw new RuntimeException('Router record is incomplete.');
    }

    $wg = RSWireguardControlPlane::publicConfig();
    $tunnelIp = rs_wg_allocate_ip($routerId, $wg);
    $identity = rs10_ensure_stable_router_identity($router, $tunnelIp);

    // Unlike the old implementation, the session is still open at this point.
    // Reuse the same plaintext token/command on refresh while it is valid.
    $sessionKey = 'router_' . $routerId;
    $cached = $_SESSION['rs10_onboarding'][$sessionKey] ?? null;
    $currentHash = trim((string) ($router['wg_activation_token_hash'] ?? ''));
    $token = '';
    $expiresAt = 0;

    if (is_array($cached)) {
        $candidate = trim((string) ($cached['token'] ?? ''));
        $candidateExpiry = (int) ($cached['expires_at'] ?? 0);
        if (preg_match('/^[A-Za-z0-9_-]{32,128}$/', $candidate)
            && $candidateExpiry > time() + 30
            && $currentHash !== ''
            && hash_equals($currentHash, hash('sha256', $candidate))) {
            $token = $candidate;
            $expiresAt = $candidateExpiry;
        }
    }

    if ($token === '') {
        $token = rs10_new_token();
        $expiresAt = time() + 1800;
        $router->wg_activation_token_hash = hash('sha256', $token);
        $router->wg_activation_expires_at = date('Y-m-d H:i:s', $expiresAt);
        $router->status = 'Offline';
        $router->save();
    }

    $bootstrapUrl = rtrim((string) APP_URL, '/')
        . '/?_route=plugin/rs10_radius_wireguard_bootstrap&token='
        . rawurlencode($token);

    $script = rs10_build_single_command($bootstrapUrl);
    $_SESSION['rs10_onboarding'][$sessionKey] = [
        'token' => $token,
        'expires_at' => $expiresAt,
        'script' => $script,
        'tunnel_ip' => $tunnelIp,
    ];

    return [
        'generator_version' => 10,
        'tunnel_ip' => $tunnelIp,
        'script' => $script,
        'api_user' => $identity['api_user'],
        'message' => 'Paste the one command into MikroTik. Retries reuse the same API and RADIUS credentials.',
    ];
}

function rs10_build_single_command($bootstrapUrl)
{
    if (!preg_match('#^https?://#i', trim((string) $bootstrapUrl))) {
        throw new RuntimeException('Invalid onboarding bootstrap URL.');
    }
    $url = rs10_q($bootstrapUrl);
    $file = 'rs-radius-onboard-v10.rsc';

    return ':do { '
        . ':put "RS: downloading authoritative onboarding v10..."; '
        . ':do { /file remove [find where name="' . $file . '"]; } on-error={}; '
        . '/tool/fetch url="' . $url . '" dst-path="' . $file . '" keep-result=yes check-certificate=no; '
        . ':if ([:len [/file find where name="' . $file . '"]] = 0) do={ :error "installer download failed"; }; '
        . ':put "RS: applying onboarding..."; '
        . '/import file-name="' . $file . '"; '
        . '} on-error={ :put "RS-ONBOARDING-FAILED"; };';
}

function rs10_find_router_by_token($token)
{
    if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', trim((string) $token))) {
        throw new RuntimeException('Invalid onboarding token.');
    }
    $router = ORM::for_table('tbl_routers')
        ->where('wg_activation_token_hash', hash('sha256', trim((string) $token)))
        ->find_one();
    if (!$router) {
        throw new RuntimeException('Onboarding token is invalid.');
    }
    $expires = trim((string) ($router['wg_activation_expires_at'] ?? ''));
    if ($expires === '' || strtotime($expires) === false || strtotime($expires) < time()) {
        throw new RuntimeException('Onboarding token has expired. Refresh Automatic Router Setup and use the new command.');
    }
    return $router;
}

function rs10_existing_radius_secret($router, $tunnelIp)
{
    $row = rs10_radius_row($router, $tunnelIp);
    $secret = $row ? trim((string) ($row['secret'] ?? '')) : '';
    if (!rs10_valid_radius_secret($secret)) {
        throw new RuntimeException('The stable RADIUS NAS secret is missing. Refresh Automatic Router Setup first.');
    }
    return $secret;
}

function rs10_build_full_installer($router, $token)
{
    $wg = RSWireguardControlPlane::publicConfig();
    $routerId = (int) $router->id();
    $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
    $apiUser = trim((string) ($router['username'] ?? ''));
    $apiPass = trim((string) ($router['password'] ?? ''));
    if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !rs10_valid_api_username($apiUser)
        || !rs10_valid_api_password($apiPass)) {
        throw new RuntimeException('Stable RouterOS management credentials are incomplete.');
    }

    $radiusSecret = rs10_existing_radius_secret($router, $tunnelIp);
    $wgInterface = trim((string) $wg['interface']);
    $serverIp = trim((string) $wg['server_ip']);
    $serverCidr = $serverIp . '/32';
    $wgCidr = trim((string) $wg['cidr']);
    $prefix = (int) explode('/', $wgCidr, 2)[1];
    $wgAddress = $tunnelIp . '/' . $prefix;
    $publicKey = trim((string) $wg['public_key']);
    $endpoint = trim((string) $wg['endpoint']);
    $endpointPort = (int) $wg['endpoint_port'];

    $activateUrl = rtrim((string) APP_URL, '/') . '/?_route=plugin/rs10_radius_wireguard_activate';
    $completeUrl = rtrim((string) APP_URL, '/') . '/?_route=plugin/rs10_radius_wireguard_complete';

    foreach ([$serverIp, $tunnelIp] as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('Invalid WireGuard address in onboarding plan.');
        }
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $wgInterface)
        || !preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)
        || $endpoint === '' || $endpointPort < 1 || $endpointPort > 65535) {
        throw new RuntimeException('Invalid WireGuard server configuration.');
    }

    $q = 'rs10_q';
    $lines = [
        '# RS authoritative WireGuard + RouterOS API + RADIUS onboarding v10',
        '# RouterOS 7.12+ compatible. This script does not create HotSpot/PPPoE customer services.',
        '# HotSpot/PPPoE bridges, ports and captive portal are configured later from the dashboard.',
        ':put "RS v10: starting...";',
        ':do { /export file=rs-before-wireguard-radius; } on-error={};',
        '',
        ':put "RS v10: preparing WireGuard...";',
        ':if ([:len [/interface/wireguard find where name="' . $q($wgInterface) . '"]] = 0) do={ /interface/wireguard add name="' . $q($wgInterface) . '" mtu=1420 comment="RS-WG"; };',
        '/interface/wireguard set [find where name="' . $q($wgInterface) . '"] mtu=1420 disabled=no;',
        ':do { /ip/address remove [find where comment="RS-WG-IP"]; } on-error={};',
        '/ip/address add address="' . $q($wgAddress) . '" interface="' . $q($wgInterface) . '" comment="RS-WG-IP";',
        ':do { /interface/wireguard/peers remove [find where comment="RS-WG-SERVER"]; } on-error={};',
        '/interface/wireguard/peers add interface="' . $q($wgInterface) . '" public-key="' . $q($publicKey) . '" endpoint-address="' . $q($endpoint) . '" endpoint-port=' . $endpointPort . ' allowed-address="' . $q($serverCidr) . '" persistent-keepalive=25s comment="RS-WG-SERVER";',
        ':local rsWgPub [/interface/wireguard get [find where name="' . $q($wgInterface) . '"] public-key];',
        ':if ([:len $rsWgPub] < 40) do={ :error "RS stopped: WireGuard key was not generated"; };',
        '',
        ':put "RS v10: activating server peer...";',
        ':local rsActivatePayload ("{\"token\":\"' . $q($token) . '\",\"public_key\":\"" . $rsWgPub . "\"}");',
        ':local rsActivate;',
        ':do { :set rsActivate [/tool/fetch url="' . $q($activateUrl) . '" http-method=post http-header-field="Content-Type: application/json" http-data=$rsActivatePayload output=user as-value check-certificate=no]; } on-error={ :error "RS stopped: peer activation request failed"; };',
        ':if (($rsActivate->"status") != "finished") do={ :error "RS stopped: peer activation did not finish"; };',
        ':if ([:find ($rsActivate->"data") "\"status\":\"ok\""] = nil) do={ :error "RS stopped: server rejected peer activation"; };',
        '',
        ':put "RS v10: waiting for authenticated WireGuard handshake...";',
        ':local rsPeer [/interface/wireguard/peers find where comment="RS-WG-SERVER"];',
        ':local rsReady false;',
        ':local rsTry 0;',
        ':while (($rsTry < 20) && ($rsReady = false)) do={',
        '    :do { /ping address="' . $q($serverIp) . '" count=1 interval=300ms; } on-error={};',
        '    :delay 500ms;',
        '    :local rsEndpoint [/interface/wireguard/peers get $rsPeer current-endpoint-address];',
        '    :if ([:len $rsEndpoint] > 0) do={ :set rsReady true; };',
        '    :set rsTry ($rsTry + 1);',
        '};',
        ':if ($rsReady = false) do={ :error "RS stopped: authenticated WireGuard handshake not established"; };',
        ':put "RS v10: WireGuard authenticated.";',
        '',
        ':put "RS v10: installing stable RouterOS API credentials...";',
        ':do { /user remove [find where comment="RS Router API User"]; } on-error={};',
        '/user add name="' . $q($apiUser) . '" password="' . $q($apiPass) . '" group=full comment="RS Router API User";',
        '/ip/service set [find where name="api"] disabled=no port=8728 address="' . $q($serverCidr) . '";',
        ':do { /ip/firewall/filter remove [find where comment="RS-WG-API"]; } on-error={};',
        '/ip/firewall/filter add chain=input action=accept protocol=tcp src-address="' . $q($serverIp) . '" dst-port=8728 comment="RS-WG-API";',
        ':do { /ip/firewall/filter move [find where comment="RS-WG-API"] 0; } on-error={};',
        ':do { /ip/firewall/filter remove [find where comment="RS-WG-MGMT-ICMP"]; } on-error={};',
        '/ip/firewall/filter add chain=input action=accept protocol=icmp src-address="' . $q($serverIp) . '" comment="RS-WG-MGMT-ICMP";',
        ':do { /ip/firewall/filter move [find where comment="RS-WG-MGMT-ICMP"] 0; } on-error={};',
        '',
        ':put "RS v10: configuring stable RADIUS client...";',
        ':do { /radius remove [find where comment="RS-WG-RADIUS"]; } on-error={};',
        '/radius add address="' . $q($serverIp) . '" src-address="' . $q($tunnelIp) . '" secret="' . $q($radiusSecret) . '" service=hotspot,ppp authentication-port=1812 accounting-port=1813 timeout=2s disabled=no comment="RS-WG-RADIUS";',
        '/radius incoming set accept=yes port=3799;',
        ':do { /ip/firewall/filter remove [find where comment="RS-WG-RADIUS-COA"]; } on-error={};',
        '/ip/firewall/filter add chain=input action=accept protocol=udp src-address="' . $q($serverIp) . '" dst-port=3799 comment="RS-WG-RADIUS-COA";',
        ':do { /ip/firewall/filter move [find where comment="RS-WG-RADIUS-COA"] 0; } on-error={};',
        '/ppp aaa set use-radius=yes accounting=yes interim-update=5m;',
        '',
        '# Do not modify arbitrary HotSpot profiles here. The dashboard creates the selected service later.',
        ':if ([:len [/radius find where comment="RS-WG-RADIUS"]] = 0) do={ :error "RS stopped: RADIUS client missing"; };',
        ':if ([:len [/user find where comment="RS Router API User"]] = 0) do={ :error "RS stopped: API user missing"; };',
        ':if ([:len [/ip/firewall/filter find where comment="RS-WG-API"]] = 0) do={ :error "RS stopped: API firewall rule missing"; };',
        '',
        ':put "RS v10: asking server to verify API + RADIUS runtime...";',
        ':local rsCompletePayload "{\"token\":\"' . $q($token) . '\"}";',
        ':local rsComplete;',
        ':do { :set rsComplete [/tool/fetch url="' . $q($completeUrl) . '" http-method=post http-header-field="Content-Type: application/json" http-data=$rsCompletePayload output=user as-value check-certificate=no]; } on-error={ :error "RS stopped: completion verification request failed"; };',
        ':if (($rsComplete->"status") != "finished") do={ :error "RS stopped: completion verification did not finish"; };',
        ':if ([:find ($rsComplete->"data") "\"status\":\"ok\""] = nil) do={ :error "RS stopped: server could not verify management path"; };',
        '',
        ':log info "RS-WIREGUARD-RADIUS-ONBOARDING-V10-COMPLETE";',
        ':put "RS-WIREGUARD-RADIUS-ONBOARDING-V10-COMPLETE";',
    ];

    return implode("\n", $lines) . "\n";
}

/** Token-protected full installer download. */
function rs10_radius_wireguard_bootstrap()
{
    rs_wg_ensure_schema();
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            throw new RuntimeException('GET required.');
        }
        $token = trim((string) ($_GET['token'] ?? ''));
        $router = rs10_find_router_by_token($token);
        echo rs10_build_full_installer($router, $token);
    } catch (Throwable $e) {
        error_log('RS v10 bootstrap failed: ' . $e->getMessage());
        http_response_code(422);
        echo ':error "RS bootstrap unavailable; refresh Automatic Router Setup";' . "\n";
    }
    exit;
}

/** Phase 1: activate/update the server WireGuard peer, but keep token valid. */
function rs10_radius_wireguard_activate()
{
    rs_wg_ensure_schema();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new RuntimeException('POST required.');
        }
        $payload = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        $token = is_array($payload) ? trim((string) ($payload['token'] ?? '')) : '';
        $publicKey = is_array($payload) ? trim((string) ($payload['public_key'] ?? '')) : '';
        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)) {
            throw new RuntimeException('Invalid WireGuard public key.');
        }
        $router = rs10_find_router_by_token($token);
        $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
        if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('Router WireGuard IP is invalid.');
        }

        $result = RSWireguardControlPlane::activatePeer($publicKey, $tunnelIp);
        $db = ORM::get_db();
        $stmt = $db->prepare(
            'UPDATE rs_wireguard_allocations SET public_key = ?, activated_at = NOW(), updated_at = NOW() WHERE router_id = ?'
        );
        $stmt->execute([$publicKey, (int) $router->id()]);

        // Intentionally DO NOT invalidate the token here. If API/RADIUS setup
        // fails after the handshake, the same installer can be run again.
        echo json_encode([
            'status' => 'ok',
            'message' => $result['message'] ?? 'WireGuard peer activated.',
            'tunnel_ip' => $tunnelIp,
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        error_log('RS v10 activation failed: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

/** Phase 2: verify server can authenticate to RouterOS, reload RADIUS, then commit. */
function rs10_radius_wireguard_complete()
{
    rs_wg_ensure_schema();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new RuntimeException('POST required.');
        }
        $payload = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
        $token = is_array($payload) ? trim((string) ($payload['token'] ?? '')) : '';
        $router = rs10_find_router_by_token($token);

        // Ensure the dedicated RS RADIUS process has the current SQL NAS row.
        rs_wg_reload_radius_clients();

        $probe = false;
        for ($i = 0; $i < 8; $i++) {
            $router = ORM::for_table('tbl_routers')->find_one((int) $router->id());
            $probe = rs_wg_probe_router_api($router);
            if ($probe !== false) {
                break;
            }
            usleep(500000);
        }
        if ($probe === false) {
            throw new RuntimeException('Server cannot authenticate to RouterOS API over WireGuard. Token remains valid for retry.');
        }

        $router->status = 'Online';
        $router->last_seen = date('Y-m-d H:i:s');
        $router->ip_address = (string) $router['wg_tunnel_ip'];
        $router->management_transport = 'wireguard';
        $router->wg_activation_token_hash = null;
        $router->wg_activation_expires_at = null;
        $router->save();

        echo json_encode([
            'status' => 'ok',
            'message' => 'WireGuard, authenticated RouterOS API, and dedicated RADIUS runtime verified.',
            'target' => (string) $router['wg_tunnel_ip'] . ':8728',
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        error_log('RS v10 completion failed: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

function rs10_radius_wireguard_setup()
{
    global $ui;
    $admin = rs_wg_require_admin(false);
    rs_wg_ensure_schema();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['name'])) {
        $name = trim((string) _post('name'));
        $description = trim((string) _post('description', ''));
        if (!Validator::Length($name, 30, 1)) {
            r2(getUrl('plugin/rs10_radius_wireguard_setup'), 'e', 'Router name should be between 1 and 30 characters.');
        }
        if (strtolower($name) === 'radius') {
            r2(getUrl('plugin/rs10_radius_wireguard_setup'), 'e', 'Radius is a reserved router name.');
        }
        $existing = ORM::for_table('tbl_routers')->where('name', $name)->find_one();
        if ($existing) {
            r2(getUrl('plugin/rs10_radius_wireguard_setup&router_id=' . $existing->id()), 'e', 'Router already exists; opened its stable onboarding plan.');
        }

        $router = ORM::for_table('tbl_routers')->create();
        $router->set([
            'name' => $name,
            'ip_address' => '0.0.0.0',
            'username' => 'pending',
            'password' => 'pending',
            'description' => $description,
            'enabled' => 1,
            'status' => 'Offline',
            'management_transport' => 'wireguard',
        ])->save();
        _log('[' . ($admin['username'] ?? 'admin') . ']: Created router ' . $name . ' for authoritative v10 onboarding', 'SuperAdmin');
        r2(getUrl('plugin/rs10_radius_wireguard_setup&router_id=' . $router->id()));
    }

    $ui->assign('_admin', $admin);
    $ui->assign('_title', 'Automatic WireGuard + RADIUS Setup');
    $ui->assign('_system_menu', 'network');

    $routerId = (int) _get('router_id', 0);
    if ($routerId <= 0) {
        $ui->display('rs_radius_wireguard_setup.tpl');
        return;
    }

    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(getUrl('routers/list'), 'e', 'Router not found.');
    }

    try {
        // First check whether the stable credentials already work. A successful
        // router is never regenerated or rotated on refresh.
        $probe = rs_wg_probe_router_api($router);
        if ($probe !== false && strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard') {
            $router->status = 'Online';
            $router->last_seen = date('Y-m-d H:i:s');
            $router->save();
            r2(getUrl('plugin/mikrotik_configurator_config_ui&router_id=' . $routerId . '&auto_radius=1'));
        }

        // IMPORTANT: prepare while PHP session is OPEN so the token/command is
        // actually persisted. This is the bug that the old workflow violated.
        $plan = rs10_prepare_plan($router);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        $ui->assign('router', $router);
        $ui->assign('tunnel_ip', $plan['tunnel_ip']);
        $ui->assign('setup_script', $plan['script']);
        $ui->assign('setup_error', null);
    } catch (Throwable $e) {
        error_log('RS v10 onboarding preparation failed: ' . $e->getMessage());
        $ui->assign('router', $router);
        $ui->assign('tunnel_ip', '');
        $ui->assign('setup_script', '');
        $ui->assign('setup_error', $e->getMessage());
    }

    $ui->display('rs_radius_wireguard_polling.tpl');
}
