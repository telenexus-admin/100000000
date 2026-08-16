<?php

/**
 * Automatic MikroTik onboarding over WireGuard + FreeRADIUS.
 *
 * Flow:
 *   create router -> allocate WG /32 + rotate NAS secret -> one RouterOS v6
 *   script -> public one-time peer activation -> authenticated RouterOS API
 *   proof -> existing MikroTik configurator/port-selection screen.
 */

use PEAR2\Net\RouterOS;

require_once __DIR__.'/radius_wireguard_bridge.php';
require_once dirname(__DIR__).'/autoload/WireguardControlPlane.php';

register_menu(
    'Automatic Router Setup',
    true,
    'radius_wireguard_setup',
    'AFTER_NETWORKS',
    'ion ion-network',
    '',
    '',
    ['SuperAdmin', 'Admin']
);

function _rw_require_admin(bool $json = false)
{
    _admin();
    $admin = Admin::_info();
    if (!in_array($admin['user_type'] ?? '', ['SuperAdmin', 'Admin'], true)) {
        if ($json) {
            if (ob_get_level()) {
                ob_clean();
            }
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['online' => false, 'error' => 'access_denied']);
            exit;
        }
        r2(U.'dashboard', 'e', Lang::T('You Do Not Have Access'));
    }
    return $admin;
}

function _rw_ensure_schema(): void
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
        $check = $db->query('SHOW COLUMNS FROM tbl_routers LIKE ' . $db->quote((string)$column));
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            $db->exec('ALTER TABLE tbl_routers ADD COLUMN `'.$column.'` '.$definition);
        }
    }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS radius_wireguard_allocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            router_id INT NOT NULL,
            tunnel_ip VARCHAR(64) NOT NULL,
            public_key VARCHAR(80) NULL,
            activated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rw_router (router_id),
            UNIQUE KEY uq_rw_tunnel_ip (tunnel_ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function _rw_random_b64url(int $bytes): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function _rw_slug(string $value): string
{
    $value = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
    $value = trim($value, '-');
    return $value !== '' ? $value : 'router';
}

/** @return array{first:int,last:int} */
function _rw_network_details(string $cidr): array
{
    if (!str_contains($cidr, '/')) {
        throw new RuntimeException('The WireGuard management CIDR is invalid.');
    }
    [$network, $prefix] = explode('/', $cidr, 2);
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

function _rw_allocate_tunnel_ip(int $routerId, array $wg): string
{
    $db = ORM::get_db();
    $liveUsed = array_fill_keys(WireguardControlPlane::allocatedIps(), true);

    $db->beginTransaction();
    try {
        $statement = $db->prepare('SELECT tunnel_ip FROM radius_wireguard_allocations WHERE router_id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$routerId]);
        $existing = $statement->fetchColumn();
        if ($existing !== false && filter_var($existing, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $db->commit();
            return (string)$existing;
        }

        $used = [];
        foreach ($db->query('SELECT tunnel_ip FROM radius_wireguard_allocations')->fetchAll(PDO::FETCH_COLUMN) as $ip) {
            $used[(string)$ip] = true;
        }
        foreach ($liveUsed as $ip => $_) {
            $used[$ip] = true;
        }

        $network = _rw_network_details((string)$wg['cidr']);
        $serverLong = ip2long((string)$wg['server_ip']);
        if ($serverLong === false) {
            throw new RuntimeException('The WireGuard server address is invalid.');
        }

        for ($candidate = $network['first']; $candidate <= $network['last']; $candidate++) {
            if ($candidate === $serverLong) {
                continue;
            }
            $ip = long2ip($candidate);
            if ($ip === false || isset($used[$ip])) {
                continue;
            }
            try {
                $insert = $db->prepare('INSERT INTO radius_wireguard_allocations (router_id, tunnel_ip) VALUES (?, ?)');
                $insert->execute([$routerId, $ip]);
                $db->commit();
                return $ip;
            } catch (PDOException $exception) {
                if ((string)$exception->getCode() === '23000') {
                    continue;
                }
                throw $exception;
            }
        }
        throw new RuntimeException('No free WireGuard management addresses remain.');
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function _rw_upsert_radius_nas($router, string $tunnelIp, string $secret): void
{
    global $config;
    if (empty($config['radius_enable'])) {
        throw new RuntimeException('RADIUS is disabled in this billing system. Enable RADIUS before automatic onboarding.');
    }

    try {
        $routerId = (int)$router->id();
        $marker = 'NUXHOST-WG-RTR:'.$routerId;
        $nas = ORM::for_table('nas', 'radius')
            ->where_raw('(nasname = ? OR description LIKE ?)', [$tunnelIp, $marker.'%'])
            ->find_one();
        if (!$nas) {
            $nas = ORM::for_table('nas', 'radius')->create();
        }

        $shortName = substr('nuxwg-'.$routerId.'-'._rw_slug((string)$router['name']), 0, 30);
        $nas->nasname = $tunnelIp;
        $nas->shortname = $shortName;
        $nas->type = 'other';
        $nas->ports = null;
        $nas->secret = $secret;
        $nas->server = null;
        $nas->community = null;
        $nas->description = $marker.' '.trim((string)$router['name']).' via WireGuard';
        $nas->routers = (string)$router['name'];
        $nas->save();
    } catch (Throwable $exception) {
        throw new RuntimeException('Could not synchronize the FreeRADIUS NAS record: '.$exception->getMessage(), 0, $exception);
    }

    WireguardControlPlane::reloadRadiusClients();
}

/** @return array{script:string,tunnel_ip:string} */
function _rw_prepare_plan($router, bool $fresh = false): array
{
    _rw_ensure_schema();
    $routerId = (string)$router->id();
    $generatorVersion = 6;

    if ($fresh) {
        unset($_SESSION['radius_wg_setup'][$routerId]);
    }

    $cached = $_SESSION['radius_wg_setup'][$routerId] ?? null;
    $storedHash = trim((string)($router['wg_activation_token_hash'] ?? ''));
    if (is_array($cached)
        && (int)($cached['generator_version'] ?? 0) === $generatorVersion
        && (int)($cached['expires_at'] ?? 0) > time() + 60
        && $storedHash !== ''
        && hash_equals($storedHash, (string)($cached['activation_hash'] ?? ''))
        && trim((string)($cached['script'] ?? '')) !== '') {
        return [
            'script' => (string)$cached['script'],
            'tunnel_ip' => (string)$cached['tunnel_ip'],
        ];
    }

    $wg = WireguardControlPlane::publicConfig();
    $tunnelIp = _rw_allocate_tunnel_ip((int)$router->id(), $wg);
    $apiUser = 'nuxwg_'.substr(bin2hex(random_bytes(6)), 0, 12);
    $apiPass = bin2hex(random_bytes(16));
    $activationToken = _rw_random_b64url(36);
    $radiusSecret = _rw_random_b64url(32);
    $expiresAt = time() + 1200;
    $activationHash = hash('sha256', $activationToken);

    _rw_upsert_radius_nas($router, $tunnelIp, $radiusSecret);

    $router->set([
        'ip_address' => $tunnelIp,
        'username' => $apiUser,
        'password' => $apiPass,
        'enabled' => 1,
        'status' => 'Offline',
        'wg_tunnel_ip' => $tunnelIp,
        'wg_interface' => (string)$wg['interface'],
        'management_transport' => 'wireguard',
        'wg_activation_token_hash' => $activationHash,
        'wg_activation_expires_at' => date('Y-m-d H:i:s', $expiresAt),
    ])->save();

    $callbackUrl = rtrim((string)APP_URL, '/').'/?_route=plugin/radius_wireguard_activate';
    $script = radius_wireguard_build_routeros_script(
        (string)$router['name'],
        $tunnelIp,
        $apiUser,
        $apiPass,
        $activationToken,
        $callbackUrl,
        [
            'interface' => $wg['interface'],
            'server_ip' => $wg['server_ip'],
            'public_key' => $wg['public_key'],
            'endpoint' => $wg['endpoint'],
            'endpoint_port' => $wg['endpoint_port'],
        ],
        [
            'host' => $wg['radius_host'],
            'auth_port' => $wg['radius_auth_port'],
            'accounting_port' => $wg['radius_accounting_port'],
            'coa_port' => $wg['radius_coa_port'],
        ],
        $radiusSecret
    );

    $_SESSION['radius_wg_setup'][$routerId] = [
        'generator_version' => $generatorVersion,
        'expires_at' => $expiresAt,
        'activation_hash' => $activationHash,
        'tunnel_ip' => $tunnelIp,
        'script' => $script,
    ];

    return ['script' => $script, 'tunnel_ip' => $tunnelIp];
}

function radius_wireguard_setup()
{
    global $ui;
    $admin = _rw_require_admin();
    _rw_ensure_schema();

    $ready = true;
    $error = '';
    $wg = null;
    try {
        $wg = WireguardControlPlane::publicConfig();
        global $config;
        if (empty($config['radius_enable'])) {
            throw new RuntimeException('RADIUS is disabled. Enable it in the billing settings first.');
        }
        ORM::for_table('nas', 'radius')->limit(1)->find_one();
    } catch (Throwable $exception) {
        $ready = false;
        $error = $exception->getMessage();
    }

    $ui->assign('_admin', $admin);
    $ui->assign('_title', 'Automatic Router Setup');
    $ui->assign('_system_menu', 'network');
    $ui->assign('wireguard_ready', $ready);
    $ui->assign('wireguard_error', $error);
    $ui->assign('wireguard', $wg);
    $ui->display('radius_wireguard_setup.tpl');
}

function radius_wireguard_create()
{
    _rw_require_admin();
    _rw_ensure_schema();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        r2(U.'plugin/radius_wireguard_setup', 'e', 'POST required.');
    }

    $name = trim((string)_post('name'));
    $description = trim((string)_post('description', ''));
    if ($name === '' || mb_strlen($name) > 30) {
        r2(U.'plugin/radius_wireguard_setup', 'e', 'Router name must be between 1 and 30 characters.');
    }
    if (strtolower($name) === 'radius') {
        r2(U.'plugin/radius_wireguard_setup', 'e', 'Radius is a reserved router name.');
    }
    if (ORM::for_table('tbl_routers')->where('name', $name)->find_one()) {
        r2(U.'plugin/radius_wireguard_setup', 'e', 'A router with that name already exists.');
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

    _log('Automatic WireGuard onboarding created router '.$name, 'SuperAdmin');
    r2(U.'plugin/radius_wireguard_prepare&router_id='.$router->id());
}

function radius_wireguard_prepare()
{
    global $ui;
    $admin = _rw_require_admin();
    _rw_ensure_schema();

    $routerId = (int)_get('router_id', 0);
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(U.'plugin/radius_wireguard_setup', 'e', 'Router not found.');
    }

    try {
        $plan = _rw_prepare_plan($router, _get('fresh', '') === '1');
        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        $ui->assign('vpn_error', null);
        $ui->assign('setup_script', $plan['script']);
        $ui->assign('tunnel_ip', $plan['tunnel_ip']);
    } catch (Throwable $exception) {
        error_log('Automatic WireGuard preparation failed for router '.$routerId.': '.$exception->getMessage());
        $ui->assign('vpn_error', $exception->getMessage());
        $ui->assign('setup_script', '');
        $ui->assign('tunnel_ip', '');
    }

    $ui->assign('_admin', $admin);
    $ui->assign('_title', 'WireGuard + RADIUS Setup');
    $ui->assign('_system_menu', 'network');
    $ui->assign('router', $router);
    $ui->display('radius_wireguard_polling.tpl');
}

/** Public, one-time RouterOS callback. Do not add _admin() here. */
function radius_wireguard_activate()
{
    _rw_ensure_schema();
    if (ob_get_level()) {
        ob_clean();
    }
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
        if (!$row) {
            throw new RuntimeException('Activation token is invalid or expired.');
        }

        $routerId = (int)$row['id'];
        $tunnelIp = trim((string)$row['wg_tunnel_ip']);
        $allocation = $db->prepare('SELECT tunnel_ip FROM radius_wireguard_allocations WHERE router_id = ? LIMIT 1');
        $allocation->execute([$routerId]);
        $allocatedIp = trim((string)$allocation->fetchColumn());
        if ($allocatedIp === '' || !hash_equals($allocatedIp, $tunnelIp)) {
            throw new RuntimeException('WireGuard allocation does not match this router.');
        }

        $result = WireguardControlPlane::activatePeer($publicKey, $tunnelIp);

        $db->beginTransaction();
        $update = $db->prepare(
            'UPDATE radius_wireguard_allocations
             SET public_key = ?, activated_at = NOW(), updated_at = NOW()
             WHERE router_id = ?'
        );
        $update->execute([$publicKey, $routerId]);
        $consume = $db->prepare(
            'UPDATE tbl_routers
             SET wg_activation_token_hash = NULL, wg_activation_expires_at = NULL
             WHERE id = ?'
        );
        $consume->execute([$routerId]);
        $db->commit();

        echo json_encode([
            'status' => 'ok',
            'message' => (string)($result['message'] ?? 'WireGuard peer activated.'),
            'tunnel_ip' => $tunnelIp,
        ], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $exception) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('WireGuard activation callback failed: '.$exception->getMessage());
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
    }
    exit;
}

function radius_wireguard_check_status()
{
    _rw_require_admin(true);
    _rw_ensure_schema();
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json');

    $routerId = (int)_get('router_id', 0);
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        echo json_encode(['online' => false, 'error' => 'missing_router']);
        exit;
    }

    $targetIp = trim((string)($router['wg_tunnel_ip'] ?? ''));
    if (!filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        echo json_encode(['online' => false, 'error' => 'missing_wireguard_ip']);
        exit;
    }

    try {
        ini_set('default_socket_timeout', '5');
        $client = Mikrotik::getClient($targetIp, (string)$router['username'], (string)$router['password']);

        $identityName = '';
        foreach ($client->sendSync(new RouterOS\Request('/system/identity/print')) as $item) {
            $identityName = trim((string)$item->getProperty('name'));
            break;
        }

        $version = '';
        $model = '';
        $uptime = '';
        foreach ($client->sendSync(new RouterOS\Request('/system/resource/print')) as $item) {
            $version = trim((string)$item->getProperty('version'));
            $model = trim((string)$item->getProperty('board-name'));
            $uptime = trim((string)$item->getProperty('uptime'));
            break;
        }

        $router->status = 'Online';
        $router->last_seen = date('Y-m-d H:i:s');
        $router->ip_address = $targetIp;
        $router->management_transport = 'wireguard';
        if (isset($router->version)) {
            $router->version = $version;
        }
        $router->save();

        unset($_SESSION['radius_wg_setup'][(string)$routerId]);

        echo json_encode([
            'online' => true,
            'target' => $targetIp.':8728',
            'message' => 'MikroTik Connected',
            'info' => [
                'identity' => $identityName,
                'model' => $model,
                'version' => $version,
                'uptime' => $uptime,
                'vpn_ip' => $targetIp,
                'transport' => 'wireguard',
            ],
        ]);
    } catch (Throwable $exception) {
        echo json_encode([
            'online' => false,
            'target' => $targetIp.':8728',
            'error' => 'router_api_not_ready',
        ]);
    }
    exit;
}
