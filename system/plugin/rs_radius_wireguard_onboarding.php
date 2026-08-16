<?php

/**
 * RS automatic MikroTik onboarding over WireGuard + FreeRADIUS.
 *
 * This is intentionally isolated from the legacy/manual router workflow.
 * The existing tbl_routers fields remain the compatibility boundary used by
 * the rest of the billing system: after onboarding ip_address/username/password
 * point to the private WireGuard management path and generated RouterOS API user.
 */

use PEAR2\Net\RouterOS;

register_menu('Automatic Router Setup', true, 'rs_radius_wireguard_setup', 'AFTER_NETWORKS', 'fa fa-shield', '', 'success', ['SuperAdmin', 'Admin']);

function rs_wg_require_admin($json = false)
{
    if ($json) {
        if (!_admin(false)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
            exit;
        }
    } else {
        _admin();
    }

    $admin = Admin::_info();
    if (!in_array($admin['user_type'] ?? '', ['SuperAdmin', 'Admin'], true)) {
        if ($json) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
            exit;
        }
        r2(getUrl('dashboard'), 'e', 'You do not have permission to access this page.');
    }
    return $admin;
}

function rs_wg_table_has_column(PDO $db, $table, $column)
{
    // MariaDB does not accept a bound placeholder in SHOW COLUMNS ... LIKE.
    $tableName = str_replace(chr(96), '', $table);
    $columnName = $db->quote((string)$column);
    $statement = $db->query('SHOW COLUMNS FROM ' . chr(96) . $tableName . chr(96) . ' LIKE ' . $columnName);
    return (bool)$statement->fetch(PDO::FETCH_ASSOC);
}

function rs_wg_ensure_schema()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $db = ORM::get_db();
    $columns = [
        'wg_tunnel_ip' => 'VARCHAR(64) DEFAULT NULL',
        'wg_interface' => 'VARCHAR(80) DEFAULT NULL',
        'management_transport' => "VARCHAR(30) NOT NULL DEFAULT 'manual'",
        'wg_activation_token_hash' => 'CHAR(64) DEFAULT NULL',
        'wg_activation_expires_at' => 'DATETIME DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!rs_wg_table_has_column($db, 'tbl_routers', $column)) {
            $db->exec('ALTER TABLE `tbl_routers` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS `rs_wireguard_allocations` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `router_id` BIGINT UNSIGNED NOT NULL,
            `tunnel_ip` VARCHAR(64) NOT NULL,
            `public_key` VARCHAR(80) DEFAULT NULL,
            `activated_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_rs_wg_router` (`router_id`),
            UNIQUE KEY `uq_rs_wg_ip` (`tunnel_ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function rs_wg_network_details($cidr)
{
    if (strpos($cidr, '/') === false) {
        throw new RuntimeException('The WireGuard management CIDR is invalid.');
    }
    list($network, $prefix) = explode('/', $cidr, 2);
    $networkLong = ip2long($network);
    $prefixLength = filter_var($prefix, FILTER_VALIDATE_INT, ['options' => ['min_range' => 8, 'max_range' => 30]]);
    if ($networkLong === false || $prefixLength === false) {
        throw new RuntimeException('The WireGuard management CIDR is invalid.');
    }
    $hostBits = 32 - (int)$prefixLength;
    $mask = (-1 << $hostBits);
    $base = $networkLong & $mask;
    $broadcast = $base | ((1 << $hostBits) - 1);
    return ['first' => $base + 1, 'last' => $broadcast - 1];
}

function rs_wg_allocate_ip($routerId, array $wg)
{
    $db = ORM::get_db();
    $db->beginTransaction();
    try {
        $statement = $db->prepare('SELECT tunnel_ip FROM rs_wireguard_allocations WHERE router_id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([(int)$routerId]);
        $existing = $statement->fetchColumn();
        if ($existing !== false && filter_var($existing, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $db->commit();
            return (string)$existing;
        }

        $network = rs_wg_network_details($wg['cidr']);
        $serverLong = ip2long($wg['server_ip']);
        if ($serverLong === false) {
            throw new RuntimeException('The WireGuard server address is invalid.');
        }

        // Reserve the first few host addresses for infrastructure/future growth.
        $start = max($network['first'], ($serverLong + 1));
        for ($candidate = $start; $candidate <= $network['last']; $candidate++) {
            if ($candidate === $serverLong) {
                continue;
            }
            $ip = long2ip($candidate);
            if ($ip === false) {
                continue;
            }
            try {
                $statement = $db->prepare('INSERT INTO rs_wireguard_allocations (router_id, tunnel_ip) VALUES (?, ?)');
                $statement->execute([(int)$routerId, $ip]);
                $db->commit();
                return $ip;
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    continue;
                }
                throw $e;
            }
        }

        throw new RuntimeException('No free WireGuard management addresses remain.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function rs_wg_short_name($routerId, $routerName)
{
    $clean = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)$routerName));
    $clean = trim($clean, '-_');
    if ($clean === '') {
        $clean = 'router';
    }
    return substr('rswg-' . (int)$routerId . '-' . $clean, 0, 30);
}

function rs_wg_reload_radius_clients()
{
    $helper = '/usr/local/bin/rs-radius-manage';
    if (!is_file($helper) || !is_executable($helper)) {
        throw new RuntimeException('The FreeRADIUS management helper is not installed. Run the RS WireGuard/RADIUS server installer first.');
    }
    $output = [];
    $code = 1;
    exec('sudo -n ' . escapeshellarg($helper) . ' reload 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException(trim(implode("\n", $output)) ?: 'FreeRADIUS reload failed.');
    }
}

function rs_wg_upsert_nas($router, $tunnelIp, $sharedSecret)
{
    try {
        $radiusDb = ORM::get_db('radius');
    } catch (Throwable $e) {
        throw new RuntimeException('The billing system RADIUS database connection is not configured. Enable/configure RADIUS first.');
    }

    $routerId = (int)$router->id();
    $shortName = rs_wg_short_name($routerId, $router['name']);
    $description = 'RS-WG automatic NAS for router ' . $router['name'] . ' (#' . $routerId . ')';

    $statement = $radiusDb->prepare('SELECT id, nasname FROM nas WHERE shortname = ? LIMIT 1');
    $statement->execute([$shortName]);
    $existing = $statement->fetch(PDO::FETCH_ASSOC);

    $collision = $radiusDb->prepare('SELECT id, shortname FROM nas WHERE nasname = ? LIMIT 1');
    $collision->execute([$tunnelIp]);
    $sameIp = $collision->fetch(PDO::FETCH_ASSOC);
    if ($sameIp && (!$existing || (int)$sameIp['id'] !== (int)$existing['id'])) {
        throw new RuntimeException('The allocated WireGuard IP is already registered to another RADIUS NAS.');
    }

    $columnCheck = $radiusDb->query("SHOW COLUMNS FROM nas LIKE 'routers'");
    $hasRoutersColumn = (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($hasRoutersColumn) {
            $statement = $radiusDb->prepare(
                "UPDATE nas
                 SET nasname = ?, type = 'other', ports = NULL, secret = ?, server = NULL,
                     community = NULL, description = ?, routers = ?
                 WHERE id = ?"
            );
            $statement->execute([$tunnelIp, $sharedSecret, $description, (string)$router['name'], (int)$existing['id']]);
        } else {
            $statement = $radiusDb->prepare(
                "UPDATE nas
                 SET nasname = ?, type = 'other', ports = NULL, secret = ?, server = NULL,
                     community = NULL, description = ?
                 WHERE id = ?"
            );
            $statement->execute([$tunnelIp, $sharedSecret, $description, (int)$existing['id']]);
        }
    } else {
        if ($hasRoutersColumn) {
            $statement = $radiusDb->prepare(
                "INSERT INTO nas (nasname, shortname, type, ports, secret, server, community, description, routers)
                 VALUES (?, ?, 'other', NULL, ?, NULL, NULL, ?, ?)"
            );
            $statement->execute([$tunnelIp, $shortName, $sharedSecret, $description, (string)$router['name']]);
        } else {
            $statement = $radiusDb->prepare(
                "INSERT INTO nas (nasname, shortname, type, ports, secret, server, community, description)
                 VALUES (?, ?, 'other', NULL, ?, NULL, NULL, ?)"
            );
            $statement->execute([$tunnelIp, $shortName, $sharedSecret, $description]);
        }
    }

    rs_wg_reload_radius_clients();
}

function rs_wg_probe_router_api($router)
{
    $targetIp = trim((string)($router['wg_tunnel_ip'] ?? ''));
    $username = trim((string)($router['username'] ?? ''));
    $password = trim((string)($router['password'] ?? ''));
    if (!filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $username === '' || $password === '') {
        return false;
    }

    try {
        ini_set('default_socket_timeout', 1);
        $client = Mikrotik::getClient($targetIp, $username, $password);
        $identityName = '';
        foreach ($client->sendSync(new RouterOS\Request('/system/identity/print')) as $item) {
            $identityName = trim((string)$item->getProperty('name'));
            break;
        }
        return ['client' => $client, 'identity' => $identityName, 'target' => $targetIp];
    } catch (Throwable $e) {
        return false;
    }
}

function rs_wg_prepare_router($router)
{
    rs_wg_ensure_schema();
    $generatorVersion = 7;
    $routerId = (int)$router->id();
    if ($routerId <= 0) {
        throw new RuntimeException('The router record is incomplete.');
    }

    $currentTokenHash = trim((string)($router['wg_activation_token_hash'] ?? ''));
    $cached = $_SESSION['rs_wg_setup'][(string)$routerId] ?? null;
    $allocationActivated = false;
    try {
        $statement = ORM::get_db()->prepare(
            'SELECT activated_at FROM rs_wireguard_allocations WHERE router_id = ? LIMIT 1'
        );
        $statement->execute([$routerId]);
        $activatedAt = $statement->fetchColumn();
        $allocationActivated = $activatedAt && strtotime((string)$activatedAt) >= time() - 1200;
    } catch (Throwable $ignored) {
    }
    $cacheTokenMatches = $currentTokenHash !== ''
        && is_array($cached)
        && hash_equals($currentTokenHash, (string)($cached['activation_hash'] ?? ''));
    if (is_array($cached)
        && (int)($cached['generator_version'] ?? 0) === $generatorVersion
        && (int)($cached['created_at'] ?? 0) >= time() - 1200
        && ($cacheTokenMatches || ($currentTokenHash === '' && $allocationActivated))
        && trim((string)($cached['script'] ?? '')) !== '') {
        return $cached;
    }

    $wg = RSWireguardControlPlane::publicConfig();
    $tunnelIp = rs_wg_allocate_ip($routerId, $wg);
    $apiUser = 'rswg_' . substr(bin2hex(random_bytes(6)), 0, 12);
    $apiPass = bin2hex(random_bytes(16));
    $radiusSecret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $activationToken = rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
    $expiresAt = time() + 1200;
    $activationHash = hash('sha256', $activationToken);

    $router->set([
        'ip_address' => $tunnelIp,
        'username' => $apiUser,
        'password' => $apiPass,
        'enabled' => 1,
        'status' => 'Offline',
        'wg_tunnel_ip' => $tunnelIp,
        'wg_interface' => $wg['interface'],
        'management_transport' => 'wireguard',
        'wg_activation_token_hash' => $activationHash,
        'wg_activation_expires_at' => date('Y-m-d H:i:s', $expiresAt),
    ])->save();

    rs_wg_upsert_nas($router, $tunnelIp, $radiusSecret);

    $callbackUrl = rtrim((string)APP_URL, '/') . '/?_route=plugin/rs_radius_wireguard_activate';
    $script = rs_wg_build_routeros_script(
        (string)$router['name'],
        $tunnelIp,
        $apiUser,
        $apiPass,
        $activationToken,
        $callbackUrl,
        $wg,
        [
            'host' => $wg['server_ip'],
            'auth_port' => 1812,
            'accounting_port' => 1813,
            'coa_port' => 3799,
        ],
        $radiusSecret
    );

    $result = [
        'generator_version' => $generatorVersion,
        'created_at' => time(),
        'activation_hash' => $activationHash,
        'tunnel_ip' => $tunnelIp,
        'script' => $script,
        'message' => 'WireGuard, RouterOS API and FreeRADIUS are prepared. Paste the single script into the MikroTik.',
    ];
    $_SESSION['rs_wg_setup'][(string)$routerId] = $result;
    return $result;
}

function rs_wg_build_routeros_script($routerName, $tunnelIp, $apiUser, $apiPass, $activationToken, $callbackUrl, array $wireguard, array $radius, $sharedSecret)
{
    $q = function ($value) {
        return str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '', ' '], (string)$value);
    };

    $wgInterface = trim((string)($wireguard['interface'] ?? 'wg-rs'));
    $wgServerIp = trim((string)($wireguard['server_ip'] ?? ''));
    $wgPublicKey = trim((string)($wireguard['public_key'] ?? ''));
    $wgEndpoint = trim((string)($wireguard['endpoint'] ?? ''));
    $wgEndpointPort = (int)($wireguard['endpoint_port'] ?? 51822);
    $wgCidr = trim((string)($wireguard['cidr'] ?? '10.78.0.0/24'));
    $radiusAddress = trim((string)($radius['host'] ?? $wgServerIp));
    $authPort = (int)($radius['auth_port'] ?? 1812);
    $accountingPort = (int)($radius['accounting_port'] ?? 1813);
    $coaPort = (int)($radius['coa_port'] ?? 3799);
    $bootstrapDate = gmdate('Y-m-d');
    $bootstrapTime = gmdate('H:i:s');

    $wgPrefix = (strpos($wgCidr, '/') !== false) ? (int)explode('/', $wgCidr, 2)[1] : -1;
    if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $wgInterface)
        || !filter_var($wgServerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !RSWireguardControlPlane::validIpv4Cidr($wgCidr)
        || $wgPrefix < 8 || $wgPrefix > 30
        || !filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !filter_var($radiusAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !preg_match('/^[A-Za-z0-9+\/]{43}=$/', $wgPublicKey)
        || $wgEndpoint === ''
        || $wgEndpointPort < 1 || $wgEndpointPort > 65535
        || $authPort < 1 || $accountingPort < 1 || $coaPort < 1) {
        throw new RuntimeException('The WireGuard/RADIUS plan contains invalid network settings.');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{20,128}$/', $sharedSecret)) {
        throw new RuntimeException('The generated RADIUS shared secret is invalid.');
    }

    $safeRouterName = preg_replace('/[\r\n#]+/', ' ', (string)$routerName) ?: 'MikroTik';
    $wgAddress = $tunnelIp . '/' . $wgPrefix;
    $serverCidr = $wgServerIp . '/32';

    $lines = [
        '# RS WireGuard + RouterOS API + FreeRADIUS onboarding v7',
        '# Router: ' . $safeRouterName,
        '# RouterOS 7.18-compatible bootstrap. Hotspot HTML is not replaced.',
        '/export file=rs-before-wireguard-radius;',
        '{',
        '    :put "RS: synchronizing router clock...";',
        '    /system/clock set date="' . $bootstrapDate . '" time="' . $bootstrapTime . '";',
        '    :do { /system/ntp/client set enabled=yes mode=unicast; } on-error={};',
        '    :do {',
        '        :if ([:len [/system/ntp/client/servers find where address="time.cloudflare.com"]] = 0) do={ /system/ntp/client/servers add address=time.cloudflare.com; };',
        '        :if ([:len [/system/ntp/client/servers find where address="time.google.com"]] = 0) do={ /system/ntp/client/servers add address=time.google.com; };',
        '    } on-error={};',
        '    :delay 2s;',
        '',
        '    :put "RS: preparing WireGuard management tunnel...";',
        '    :if ([:len [/interface/wireguard find where name="' . $q($wgInterface) . '"]] = 0) do={',
        '        /interface/wireguard add name="' . $q($wgInterface) . '" mtu=1420 comment="RS-WG";',
        '    };',
        '    /interface/wireguard set [find where name="' . $q($wgInterface) . '"] mtu=1420 disabled=no;',
        '    :if ([:len [/ip/address find where comment="RS-WG-IP"]] > 0) do={ /ip/address remove [find where comment="RS-WG-IP"]; };',
        '    /ip/address add address="' . $q($wgAddress) . '" interface="' . $q($wgInterface) . '" comment="RS-WG-IP";',
        '    :if ([:len [/interface/wireguard/peers find where comment="RS-WG-SERVER"]] > 0) do={ /interface/wireguard/peers remove [find where comment="RS-WG-SERVER"]; };',
        '    /interface/wireguard/peers add interface="' . $q($wgInterface) . '" public-key="' . $q($wgPublicKey) . '" endpoint-address="' . $q($wgEndpoint) . '" endpoint-port=' . $wgEndpointPort . ' allowed-address="' . $q($serverCidr) . '" persistent-keepalive=25s comment="RS-WG-SERVER";',
        '    :delay 1s;',
        '',
        '    :local rsWgPub [/interface/wireguard get [find where name="' . $q($wgInterface) . '"] public-key];',
        '    :if ([:len $rsWgPub] < 40) do={ :error "RS stopped: WireGuard public key was not generated."; };',
        '    :local rsPayload ("{\"token\":\"' . $q($activationToken) . '\",\"public_key\":\"" . $rsWgPub . "\"}");',
        '',
        '    :put "RS: activating server WireGuard peer...";',
        '    :local rsActivationResult;',
        '    :do {',
        '        :set rsActivationResult [/tool/fetch url="' . $q($callbackUrl) . '" http-method=post http-header-field="Content-Type: application/json" http-data=$rsPayload output=user as-value check-certificate=no];',
        '    } on-error={ :error "RS stopped: server peer activation request failed."; };',
        '    :if (($rsActivationResult->"status") != "finished") do={ :error "RS stopped: server peer activation did not finish."; };',
        '    :local rsActivationBody ($rsActivationResult->"data");',
        '    :if ([:find $rsActivationBody "\"status\":\"ok\""] = nil) do={ :error "RS stopped: server rejected WireGuard peer activation."; };',
        '',
        '    :local rsPeer [/interface/wireguard/peers find where comment="RS-WG-SERVER"];',
        '    :if ([:len $rsPeer] = 0) do={ :error "RS stopped: WireGuard server peer is missing."; };',
        '    :local rsHandshakeReady false;',
        '    :local rsTry 0;',
        '    :while (($rsTry < 15) && ($rsHandshakeReady = false)) do={',
        '        :do { /ping address="' . $q($wgServerIp) . '" count=1 interval=500ms; } on-error={};',
        '        :delay 1s;',
        '        :local rsCurrentEndpoint [/interface/wireguard/peers get $rsPeer current-endpoint-address];',
        '        :if ([:len $rsCurrentEndpoint] > 0) do={ :set rsHandshakeReady true; };',
        '        :set rsTry ($rsTry + 1);',
        '    };',
        '    :if ($rsHandshakeReady = false) do={ :error "RS stopped: WireGuard handshake was not established."; };',
        '    :put "RS: WireGuard handshake confirmed.";',
        '',
        '    :put "RS: WireGuard connected. Securing RouterOS API...";',
        '    :if ([:len [/user find where comment="RS Router API User"]] > 0) do={ /user remove [find where comment="RS Router API User"]; };',
        '    /user add name="' . $q($apiUser) . '" password="' . $q($apiPass) . '" group=full comment="RS Router API User";',
        '    /ip/service set [find where name="api"] disabled=no port=8728 address="' . $q($serverCidr) . '";',
        '    :if ([:len [/ip/firewall/filter find where comment="RS-WG-API"]] > 0) do={ /ip/firewall/filter remove [find where comment="RS-WG-API"]; };',
        '    :local rsDefaultInputDrop [/ip/firewall/filter find where comment="defconf: drop all not coming from LAN"];',
        '    :if ([:len $rsDefaultInputDrop] > 0) do={',
        '        /ip/firewall/filter add chain=input action=accept protocol=tcp src-address="' . $q($wgServerIp) . '" dst-port=8728 place-before=$rsDefaultInputDrop comment="RS-WG-API";',
        '    } else={',
        '        /ip/firewall/filter add chain=input action=accept protocol=tcp src-address="' . $q($wgServerIp) . '" dst-port=8728 comment="RS-WG-API";',
        '    };',
        '',
        '    :put "RS: configuring RADIUS over WireGuard...";',
        '    :if ([:len [/radius find where comment="RS-WG-RADIUS"]] > 0) do={ /radius remove [find where comment="RS-WG-RADIUS"]; };',
        '    /radius add address="' . $q($radiusAddress) . '" src-address="' . $q($tunnelIp) . '" secret="' . $q($sharedSecret) . '" service=hotspot,ppp authentication-port=' . $authPort . ' accounting-port=' . $accountingPort . ' timeout=2s disabled=no comment="RS-WG-RADIUS";',
        '    /radius incoming set accept=yes port=' . $coaPort . ';',
        '    :if ([:len [/ip/firewall/filter find where comment="RS-WG-RADIUS-COA"]] > 0) do={ /ip/firewall/filter remove [find where comment="RS-WG-RADIUS-COA"]; };',
        '    :if ([:len $rsDefaultInputDrop] > 0) do={',
        '        /ip/firewall/filter add chain=input action=accept protocol=udp src-address="' . $q($radiusAddress) . '" dst-port=' . $coaPort . ' place-before=$rsDefaultInputDrop comment="RS-WG-RADIUS-COA";',
        '    } else={',
        '        /ip/firewall/filter add chain=input action=accept protocol=udp src-address="' . $q($radiusAddress) . '" dst-port=' . $coaPort . ' comment="RS-WG-RADIUS-COA";',
        '    };',
        '    /ppp aaa set use-radius=yes accounting=yes interim-update=5m;',
        '    :if ([:len [/ip/hotspot/profile find]] > 0) do={ /ip/hotspot/profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received login-by=http-pap,http-chap,cookie; };',
        '',
        '    :if ([:len [/radius find where comment="RS-WG-RADIUS"]] = 0) do={ :error "RS stopped: RADIUS entry was not created."; };',
        '    :if ([:len [/user find where comment="RS Router API User"]] = 0) do={ :error "RS stopped: Router API user was not created."; };',
        '    :if ([:len [/ip/firewall/filter find where comment="RS-WG-API"]] = 0) do={ :error "RS stopped: API firewall rule was not created."; };',
        '    :if ([:len [/ip/firewall/filter find where comment="RS-WG-RADIUS-COA"]] = 0) do={ :error "RS stopped: CoA firewall rule was not created."; };',
        '    :log info "RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE";',
        '    :put "RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE";',
        '}',
    ];

    return implode("\n", $lines);
}

function rs_radius_wireguard_setup()
{
    global $ui;
    $admin = rs_wg_require_admin(false);
    rs_wg_ensure_schema();

    $ui->assign('_admin', $admin);
    $ui->assign('_title', 'Automatic WireGuard + RADIUS Setup');
    $ui->assign('_system_menu', 'network');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
        $name = trim((string)_post('name'));
        $description = trim((string)_post('description', ''));
        if (!Validator::Length($name, 30, 1)) {
            r2(getUrl('plugin/rs_radius_wireguard_setup'), 'e', 'Router name should be between 1 and 30 characters.');
        }
        if (strtolower($name) === 'radius') {
            r2(getUrl('plugin/rs_radius_wireguard_setup'), 'e', 'Radius is a reserved router name.');
        }
        $existing = ORM::for_table('tbl_routers')->where('name', $name)->find_one();
        if ($existing) {
            r2(getUrl('plugin/rs_radius_wireguard_setup&router_id=' . $existing->id()), 'e', 'A router with this name already exists. Opened the existing router instead.');
        }

        $router = ORM::for_table('tbl_routers')->create();
        $router->set([
            'name' => $name,
            'ip_address' => '0.0.0.0',
            'username' => '',
            'password' => '',
            'description' => $description,
            'enabled' => 1,
            'status' => 'Offline',
            'management_transport' => 'wireguard',
        ])->save();
        _log('[' . $admin['username'] . ']: Created router ' . $name . ' for automatic WireGuard/RADIUS onboarding', 'SuperAdmin');
        r2(getUrl('plugin/rs_radius_wireguard_setup&router_id=' . $router->id()));
    }

    $routerId = (int)_get('router_id', 0);
    if ($routerId <= 0) {
        $ui->display('rs_radius_wireguard_setup.tpl');
        return;
    }

    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(getUrl('routers/list'), 'e', 'Router not found.');
    }

    try {
    // Release the admin session before router network work. This prevents a slow
    // RouterOS probe from blocking every other dashboard tab for this admin.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

        // A refresh after successful onboarding must never rotate credentials
        // away from the values already installed on RouterOS. If the current
        // private API path is authenticated, continue directly to configuration.
        $probe = rs_wg_probe_router_api($router);
        if ($probe !== false && (($router['management_transport'] ?? '') === 'wireguard')) {
            $router->status = 'Online';
            $router->last_seen = date('Y-m-d H:i:s');
            $router->save();
            r2(getUrl('plugin/mikrotik_configurator_config_ui&router_id=' . $routerId . '&auto_radius=1'));
        }

        $plan = rs_wg_prepare_router($router);
        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        $ui->assign('router', $router);
        $ui->assign('tunnel_ip', $plan['tunnel_ip']);
        $ui->assign('setup_script', $plan['script']);
        $ui->assign('setup_error', null);
    } catch (Throwable $e) {
        error_log('RS WireGuard onboarding preparation failed: ' . $e->getMessage());
        $ui->assign('router', $router);
        $ui->assign('tunnel_ip', '');
        $ui->assign('setup_script', '');
        $ui->assign('setup_error', $e->getMessage());
    }

    $ui->display('rs_radius_wireguard_polling.tpl');
}

/** Public, one-time callback from the generated RouterOS script. */
function rs_radius_wireguard_activate()
{
    rs_wg_ensure_schema();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'POST required.']);
        exit;
    }

    try {
        $payload = json_decode((string)file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
        $token = is_array($payload) ? trim((string)($payload['token'] ?? '')) : '';
        $publicKey = is_array($payload) ? trim((string)($payload['public_key'] ?? '')) : '';
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $token)) {
            throw new RuntimeException('Invalid activation token.');
        }
        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)) {
            throw new RuntimeException('Invalid WireGuard public key.');
        }

        $db = ORM::get_db();
        $statement = $db->prepare(
            'SELECT id, wg_tunnel_ip FROM tbl_routers
             WHERE wg_activation_token_hash = ?
               AND wg_activation_expires_at IS NOT NULL
               AND wg_activation_expires_at >= NOW()
             LIMIT 1'
        );
        $statement->execute([hash('sha256', $token)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || !filter_var($row['wg_tunnel_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('Activation token is invalid or expired.');
        }

        $result = RSWireguardControlPlane::activatePeer($publicKey, (string)$row['wg_tunnel_ip']);

        $db->beginTransaction();
        $statement = $db->prepare(
            'UPDATE rs_wireguard_allocations
             SET public_key = ?, activated_at = NOW(), updated_at = NOW()
             WHERE router_id = ?'
        );
        $statement->execute([$publicKey, (int)$row['id']]);
        $statement = $db->prepare(
            'UPDATE tbl_routers
             SET wg_activation_token_hash = NULL, wg_activation_expires_at = NULL
             WHERE id = ?'
        );
        $statement->execute([(int)$row['id']]);
        $db->commit();

        echo json_encode([
            'status' => 'ok',
            'message' => $result['message'],
            'tunnel_ip' => (string)$row['wg_tunnel_ip'],
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('RS WireGuard activation callback failed: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

function rs_radius_wireguard_status()
{
    rs_wg_require_admin(true);
    rs_wg_ensure_schema();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    // The polling request can wait on a router API socket. Release the shared
    // PHP session first so it cannot delay dashboard navigation in other tabs.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $routerId = (int)_get('router_id', 0);
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        echo json_encode(['status' => 'error', 'online' => false, 'message' => 'Router not found.']);
        exit;
    }

    $targetIp = trim((string)($router['wg_tunnel_ip'] ?? ''));
    if (!filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        echo json_encode(['status' => 'error', 'online' => false, 'message' => 'WireGuard IP is not allocated.']);
        exit;
    }

    try {
        ini_set('default_socket_timeout', 2);
        $client = Mikrotik::getClient($targetIp, (string)$router['username'], (string)$router['password']);
        $identity = '';
        foreach ($client->sendSync(new RouterOS\Request('/system/identity/print')) as $item) {
            $identity = trim((string)$item->getProperty('name'));
            break;
        }
        $version = '';
        $uptime = '';
        $model = '';
        foreach ($client->sendSync(new RouterOS\Request('/system/resource/print')) as $item) {
            $version = trim((string)$item->getProperty('version'));
            $uptime = trim((string)$item->getProperty('uptime'));
            $model = trim((string)$item->getProperty('board-name'));
            break;
        }

        $router->status = 'Online';
        $router->last_seen = date('Y-m-d H:i:s');
        $router->ip_address = $targetIp;
        $router->management_transport = 'wireguard';
        $router->save();

        unset($_SESSION['rs_wg_setup'][(string)$routerId]);
        echo json_encode([
            'status' => 'success',
            'online' => true,
            'message' => 'MikroTik Connected',
            'target' => $targetIp . ':8728',
            'info' => [
                'identity' => $identity,
                'model' => $model,
                'version' => $version,
                'uptime' => $uptime,
                'transport' => 'wireguard',
            ],
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'waiting',
            'online' => false,
            'target' => $targetIp . ':8728',
            'message' => 'Waiting for authenticated RouterOS API over WireGuard.',
        ], JSON_UNESCAPED_SLASHES);
    }
    exit;
}
