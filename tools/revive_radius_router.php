<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@ini_set('default_socket_timeout', '8');
error_reporting(E_ALL);
@set_time_limit(90);

function rr_line($message)
{
    fwrite(STDOUT, '[radius-revive] ' . trim((string) $message) . PHP_EOL);
    fflush(STDOUT);
}

function rr_fail($message, $code = 1)
{
    fwrite(STDERR, '[radius-revive] FAILED ' . preg_replace('/[\r\n]+/', ' ', (string) $message) . PHP_EOL);
    fflush(STDERR);
    exit((int) $code);
}

$routerId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($routerId <= 0) {
    rr_fail('Usage: php tools/revive_radius_router.php ROUTER_ID', 2);
}

$root = dirname(__DIR__);

try {
    require $root . '/config.php';
    require_once $root . '/system/orm.php';
    require_once $root . '/system/autoload/PEAR2/Autoload.php';
    require_once $root . '/system/autoload/Mikrotik.php';
    require_once $root . '/system/autoload/RSWireguardControlPlane.php';

    if (isset($db_password) && $db_password !== '' && (!isset($db_pass) || $db_pass === '')) {
        $db_pass = $db_password;
    }
    $db_pass = isset($db_pass) ? (string) $db_pass : '';

    ORM::configure("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4");
    ORM::configure('username', $db_user);
    ORM::configure('password', $db_pass);
    ORM::configure('driver_options', [
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, sql_mode=""',
        PDO::ATTR_TIMEOUT => 10,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    ORM::configure('return_result_sets', true);

    $radiusHost = !empty($radius_host) ? (string) $radius_host : (string) $db_host;
    $radiusName = !empty($radius_name) ? (string) $radius_name : (string) $db_name;
    $radiusUser = !empty($radius_user) ? (string) $radius_user : (string) $db_user;
    if (!empty($radius_password)) {
        $radiusPass = (string) $radius_password;
    } elseif (isset($radius_pass) && $radius_pass !== '') {
        $radiusPass = (string) $radius_pass;
    } else {
        $radiusPass = $db_pass;
    }

    ORM::configure("mysql:host={$radiusHost};dbname={$radiusName};charset=utf8mb4", null, 'radius');
    ORM::configure('username', $radiusUser, 'radius');
    ORM::configure('password', $radiusPass, 'radius');
    ORM::configure('driver_options', [
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, sql_mode=""',
        PDO::ATTR_TIMEOUT => 10,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ], 'radius');
    ORM::configure('return_result_sets', true, 'radius');
} catch (Throwable $e) {
    rr_fail('Bootstrap failed: ' . $e->getMessage());
}

function rr_short_name($routerId, $routerName)
{
    $clean = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $routerName));
    $clean = trim($clean, '-_');
    if ($clean === '') {
        $clean = 'router';
    }
    return substr('rswg-' . (int) $routerId . '-' . $clean, 0, 30);
}

function rr_random_secret()
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function rr_reload_radius()
{
    $helper = '/usr/local/bin/rs-radius-manage';
    if (!is_file($helper) || !is_executable($helper)) {
        throw new RuntimeException('Missing /usr/local/bin/rs-radius-manage. Restore the v6 RADIUS runtime first.');
    }

    $prefix = function_exists('posix_geteuid') && posix_geteuid() === 0 ? '' : 'sudo -n ';
    $output = [];
    $code = 1;
    exec($prefix . escapeshellarg($helper) . ' reload 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException(trim(implode("\n", $output)) ?: 'FreeRADIUS reload failed.');
    }
}

try {
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        throw new RuntimeException('Router record not found.');
    }

    $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
    if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $tunnelIp = trim((string) ($router['ip_address'] ?? ''));
    }
    if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new RuntimeException('Router has no valid WireGuard tunnel IPv4 address.');
    }

    $apiUser = trim((string) ($router['username'] ?? ''));
    $apiPass = (string) ($router['password'] ?? '');
    if ($apiUser === '' || $apiPass === '') {
        throw new RuntimeException('Router API credentials are missing.');
    }

    $wg = RSWireguardControlPlane::publicConfig();
    $radiusAddress = trim((string) ($wg['server_ip'] ?? ''));
    if (!filter_var($radiusAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new RuntimeException('RADIUS/WireGuard server IPv4 address is invalid.');
    }

    rr_line('router=' . $routerId . ' name=' . trim((string) $router['name']) . ' tunnel=' . $tunnelIp);
    rr_line('stage=loading authoritative NAS record');

    $radiusDb = ORM::get_db('radius');
    $shortName = rr_short_name($routerId, (string) $router['name']);

    $byIpStmt = $radiusDb->prepare('SELECT * FROM nas WHERE nasname = ? LIMIT 1');
    $byIpStmt->execute([$tunnelIp]);
    $byIp = $byIpStmt->fetch(PDO::FETCH_ASSOC);

    $byNameStmt = $radiusDb->prepare('SELECT * FROM nas WHERE shortname = ? LIMIT 1');
    $byNameStmt->execute([$shortName]);
    $byName = $byNameStmt->fetch(PDO::FETCH_ASSOC);

    if ($byIp && $byName && (int) $byIp['id'] !== (int) $byName['id']) {
        throw new RuntimeException('Conflicting NAS rows exist for the router tunnel IP and shortname.');
    }

    $nas = $byIp ?: $byName;
    $secret = $nas ? trim((string) ($nas['secret'] ?? '')) : '';
    if ($secret === '') {
        $secret = rr_random_secret();
    }
    if (strlen($secret) < 16) {
        throw new RuntimeException('The authoritative NAS secret is unexpectedly short.');
    }

    $columnCheck = $radiusDb->query("SHOW COLUMNS FROM nas LIKE 'routers'");
    $hasRouters = (bool) $columnCheck->fetch(PDO::FETCH_ASSOC);
    $description = 'RS-WG automatic NAS for router ' . trim((string) $router['name']) . ' (#' . $routerId . ')';

    if ($nas) {
        if ($hasRouters) {
            $stmt = $radiusDb->prepare(
                "UPDATE nas SET nasname=?, shortname=?, type='other', ports=NULL, secret=?, server=NULL, community=NULL, description=?, routers=? WHERE id=?"
            );
            $stmt->execute([$tunnelIp, $shortName, $secret, $description, (string) $router['name'], (int) $nas['id']]);
        } else {
            $stmt = $radiusDb->prepare(
                "UPDATE nas SET nasname=?, shortname=?, type='other', ports=NULL, secret=?, server=NULL, community=NULL, description=? WHERE id=?"
            );
            $stmt->execute([$tunnelIp, $shortName, $secret, $description, (int) $nas['id']]);
        }
    } else {
        if ($hasRouters) {
            $stmt = $radiusDb->prepare(
                "INSERT INTO nas (nasname,shortname,type,ports,secret,server,community,description,routers) VALUES (?,?,'other',NULL,?,NULL,NULL,?,?)"
            );
            $stmt->execute([$tunnelIp, $shortName, $secret, $description, (string) $router['name']]);
        } else {
            $stmt = $radiusDb->prepare(
                "INSERT INTO nas (nasname,shortname,type,ports,secret,server,community,description) VALUES (?,?,'other',NULL,?,NULL,NULL,?)"
            );
            $stmt->execute([$tunnelIp, $shortName, $secret, $description]);
        }
    }
    rr_line('nas=ready secret=kept-private');

    rr_line('stage=connecting to RouterOS API over WireGuard');
    $client = Mikrotik::getClient($tunnelIp, $apiUser, $apiPass);
    $identity = '';
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print')) as $row) {
        $identity = trim((string) $row->getProperty('name'));
        break;
    }
    rr_line('router_api=ok identity=' . ($identity !== '' ? $identity : 'unknown'));

    $candidateIds = [];
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/radius/print')) as $row) {
        $id = trim((string) $row->getProperty('.id'));
        $address = trim((string) $row->getProperty('address'));
        $comment = trim((string) $row->getProperty('comment'));
        if ($id !== '' && ($address === $radiusAddress || in_array($comment, ['RS-WG-RADIUS', 'NUXHOST-RADIUS'], true))) {
            $candidateIds[] = $id;
        }
    }

    if ($candidateIds) {
        $radiusId = array_shift($candidateIds);
        $set = new PEAR2\Net\RouterOS\Request('/radius/set');
        $set->setArgument('numbers', $radiusId);
    } else {
        $radiusId = '';
        $set = new PEAR2\Net\RouterOS\Request('/radius/add');
    }

    $set->setArgument('address', $radiusAddress)
        ->setArgument('src-address', $tunnelIp)
        ->setArgument('secret', $secret)
        ->setArgument('service', 'hotspot,ppp')
        ->setArgument('authentication-port', '1812')
        ->setArgument('accounting-port', '1813')
        ->setArgument('timeout', '2s')
        ->setArgument('disabled', 'no')
        ->setArgument('comment', 'RS-WG-RADIUS');
    $client->sendSync($set);

    foreach ($candidateIds as $duplicateId) {
        $remove = new PEAR2\Net\RouterOS\Request('/radius/remove');
        $remove->setArgument('numbers', $duplicateId);
        $client->sendSync($remove);
    }

    $incoming = new PEAR2\Net\RouterOS\Request('/radius/incoming/set');
    $incoming->setArgument('accept', 'yes')->setArgument('port', '3799');
    $client->sendSync($incoming);

    $ppp = new PEAR2\Net\RouterOS\Request('/ppp/aaa/set');
    $ppp->setArgument('use-radius', 'yes')->setArgument('accounting', 'yes')->setArgument('interim-update', '5m');
    $client->sendSync($ppp);

    $usedProfiles = [];
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/print')) as $row) {
        $profile = trim((string) $row->getProperty('profile'));
        if ($profile !== '') {
            $usedProfiles[$profile] = true;
        }
    }
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $row) {
        $profile = trim((string) $row->getProperty('name'));
        $id = trim((string) $row->getProperty('.id'));
        if ($id === '' || $profile === '' || !isset($usedProfiles[$profile])) {
            continue;
        }
        $profileSet = new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/set');
        $profileSet->setArgument('numbers', $id)
            ->setArgument('use-radius', 'yes')
            ->setArgument('radius-accounting', 'yes')
            ->setArgument('radius-interim-update', 'received');
        $client->sendSync($profileSet);
    }
    rr_line('router_radius= synchronized address=' . $radiusAddress . ' source=' . $tunnelIp);

    rr_line('stage=reloading FreeRADIUS SQL clients');
    rr_reload_radius();
    rr_line('freeradius=reload_ok');

    $found = false;
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/radius/print')) as $row) {
        $address = trim((string) $row->getProperty('address'));
        $src = trim((string) $row->getProperty('src-address'));
        $disabled = strtolower(trim((string) $row->getProperty('disabled')));
        if ($address === $radiusAddress && $src === $tunnelIp && !in_array($disabled, ['yes', 'true'], true)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new RuntimeException('RouterOS did not retain the synchronized RADIUS client.');
    }

    rr_line('RESULT=RADIUS_CLIENT_AND_SERVER_SYNCHRONIZED');
    rr_line('NEXT=trigger one HotSpot login; Requests must produce Accepts or Rejects, not Timeouts');
} catch (Throwable $e) {
    rr_fail($e->getMessage());
}
