<?php
/**
 * Customer Router Access
 * ----------------------
 * Lets the admin reach a customer's CPE (the home router at the far
 * end of a PPPoE session) directly from the billing dashboard:
 *
 *   - Open Webfig / HTTPS in a new tab
 *   - Launch Winbox (winbox:// scheme)
 *   - Ping the CPE server-side
 *   - When the CPE is MikroTik and RouterOS API credentials are on
 *     file, show identity, list Wi-Fi interfaces, change the Wi-Fi
 *     password and reboot the CPE.
 *
 * Credentials are saved per PPPoE username in tbl_cpe_credentials,
 * which is created lazily on first hit.
 */

use PEAR2\Net\RouterOS\Request as RosRequest;
use PEAR2\Net\RouterOS\Query as RosQuery;

$action = $routes['1'] ?? '';

// ---------------------------------------------------------------
// Lazy schema: create tbl_cpe_credentials the first time any
// route runs so the feature works even on upgraded installs.
// ---------------------------------------------------------------
function cpe_ensure_schema()
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = ORM::get_db();
        $exists = $db->query("SHOW TABLES LIKE 'tbl_cpe_credentials'")->fetch();
        if (!$exists) {
            $db->exec("CREATE TABLE `tbl_cpe_credentials` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `username` varchar(64) NOT NULL COMMENT 'PPPoE secret name / customer username',
                `host` varchar(64) DEFAULT NULL COMMENT 'Override CPE IP; empty = use live session IP',
                `api_user` varchar(64) DEFAULT '' COMMENT 'RouterOS API username (MikroTik CPE)',
                `api_pass` varchar(128) DEFAULT '' COMMENT 'RouterOS API password',
                `api_port` int(11) DEFAULT 8728,
                `http_port` int(11) DEFAULT 80,
                `https_port` int(11) DEFAULT 443,
                `winbox_port` int(11) DEFAULT 8291,
                `prefer_https` tinyint(1) DEFAULT 0,
                `brand` varchar(32) DEFAULT 'mikrotik' COMMENT 'mikrotik/huawei/zte/tplink/other',
                `notes` text,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            if (function_exists('_log')) {
                _log('Created table: tbl_cpe_credentials', 'System', 1);
            }
        }
    } catch (Exception $e) {
        if (function_exists('_log')) {
            _log('CPE schema error: ' . $e->getMessage(), 'System', 3);
        }
    }
}

function cpe_json($payload, $httpCode = 200)
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function cpe_require_admin()
{
    global $admin;
    if (!_admin(false)) {
        cpe_json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }
    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
        cpe_json(['status' => 'error', 'message' => 'Permission denied'], 403);
    }
}

function cpe_validate_ip($ip)
{
    $ip = trim((string) $ip);
    // Allow "ip:port"; strip the port for validation.
    if (strpos($ip, ':') !== false) {
        list($ip, ) = explode(':', $ip, 2);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/**
 * Resolve the effective host to contact for a given PPPoE username:
 * explicit override in tbl_cpe_credentials.host takes priority, else
 * we use the current /ppp/active session address on the chosen router.
 */
function cpe_resolve_host($username, $router)
{
    $creds = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
    if ($creds && !empty($creds->host)) {
        return (string) $creds->host;
    }
    if (!$router) return '';
    try {
        $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);
        $req = new RosRequest('/ppp/active/print');
        $req->setArgument('.proplist', 'address');
        $req->setQuery(RosQuery::where('name', $username));
        $resp = $client->sendSync($req);
        foreach ($resp as $row) {
            $addr = $row->getProperty('address');
            if (!empty($addr)) return (string) $addr;
        }
        if (method_exists($client, 'disconnect')) {
            $client->disconnect();
        }
    } catch (Exception $_) {
    }
    return '';
}

function cpe_router_from_name($routerName, $routerId = '')
{
    $q = ORM::for_table('tbl_routers')->where('enabled', '1');
    if ($routerId !== '' && ctype_digit((string) $routerId)) {
        $q->where('id', (int) $routerId);
    } elseif ($routerName !== '') {
        $q->where('name', $routerName);
    }
    return $q->find_one() ?: ORM::for_table('tbl_routers')->where('enabled', '1')->find_one();
}

function cpe_ros_client_for($username)
{
    $creds = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
    if (!$creds || empty($creds->host) || empty($creds->api_user)) {
        throw new RuntimeException('CPE credentials not configured for this user');
    }
    $host = (string) $creds->host;
    $port = (int) ($creds->api_port ?: 8728);
    if (strpos($host, ':') === false) {
        $host .= ':' . $port;
    }
    return Mikrotik::getClient($host, $creds->api_user, $creds->api_pass);
}

// ---------------------------------------------------------------
// Route dispatch
// ---------------------------------------------------------------

// All JSON endpoints first.
$jsonActions = ['ping', 'creds_get', 'creds_save', 'creds_delete',
    'identity', 'wifi_list', 'wifi_set_password', 'reboot', 'list'];

if (in_array($action, $jsonActions, true)) {
    cpe_ensure_schema();
    cpe_require_admin();

    // -----------------------------------------------------------
    // ping - TCP check against http/https/winbox so admin knows
    // if the CPE is reachable from the billing server right now.
    // -----------------------------------------------------------
    if ($action === 'ping') {
        $ip   = cpe_validate_ip(_get('ip'));
        $ports = [];
        foreach ((array) _get('ports', '80,443,8291,8728') as $spec) {
            foreach (explode(',', (string) $spec) as $p) {
                $p = (int) trim($p);
                if ($p > 0 && $p < 65536) $ports[] = $p;
            }
        }
        $ports = array_values(array_unique($ports));
        if ($ip === '') cpe_json(['status' => 'error', 'message' => 'Invalid IP']);
        $result = ['ip' => $ip, 'ports' => []];
        foreach ($ports as $p) {
            $t0 = microtime(true);
            $errno = 0; $errstr = '';
            $fp = @fsockopen($ip, $p, $errno, $errstr, 2.0);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if ($fp) {
                @fclose($fp);
                $result['ports'][] = ['port' => $p, 'open' => true,  'ms' => $ms];
            } else {
                $result['ports'][] = ['port' => $p, 'open' => false, 'ms' => $ms, 'error' => $errstr];
            }
        }
        cpe_json(['status' => 'success', 'data' => $result]);
    }

    // -----------------------------------------------------------
    // creds_get / creds_save / creds_delete
    // -----------------------------------------------------------
    if ($action === 'creds_get') {
        $username = trim((string) _get('username'));
        if ($username === '') cpe_json(['status' => 'error', 'message' => 'username required']);
        $row = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
        $data = $row ? $row->as_array() : [
            'username' => $username, 'host' => '', 'api_user' => '',
            'api_pass' => '', 'api_port' => 8728, 'http_port' => 80,
            'https_port' => 443, 'winbox_port' => 8291, 'prefer_https' => 0,
            'brand' => 'mikrotik', 'notes' => '',
        ];
        // Mask password in response; admins re-enter to change it.
        if (!empty($data['api_pass'])) {
            $data['api_pass_mask'] = '••••••';
            unset($data['api_pass']);
        } else {
            $data['api_pass_mask'] = '';
        }
        cpe_json(['status' => 'success', 'data' => $data]);
    }

    if ($action === 'creds_save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            cpe_json(['status' => 'error', 'message' => 'POST required'], 405);
        }
        $csrf = _post('csrf_token');
        if (!Csrf::check($csrf)) {
            cpe_json(['status' => 'error', 'message' => 'Invalid or Expired CSRF Token'], 403);
        }
        $username = trim((string) _post('username'));
        if ($username === '') cpe_json(['status' => 'error', 'message' => 'username required']);

        $row = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
        if (!$row) {
            $row = ORM::for_table('tbl_cpe_credentials')->create();
            $row->username = $username;
        }
        $row->host        = trim((string) _post('host'));
        $row->api_user    = trim((string) _post('api_user'));
        $newPass          = (string) _post('api_pass');
        if ($newPass !== '') { $row->api_pass = $newPass; }
        $row->api_port    = max(1, (int) (_post('api_port')    ?: 8728));
        $row->http_port   = max(1, (int) (_post('http_port')   ?: 80));
        $row->https_port  = max(1, (int) (_post('https_port')  ?: 443));
        $row->winbox_port = max(1, (int) (_post('winbox_port') ?: 8291));
        $row->prefer_https = _post('prefer_https') ? 1 : 0;
        $row->brand       = trim((string) _post('brand', 'mikrotik'));
        $row->notes       = (string) _post('notes');
        $row->save();
        cpe_json(['status' => 'success', 'message' => 'Credentials saved']);
    }

    if ($action === 'creds_delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            cpe_json(['status' => 'error', 'message' => 'POST required'], 405);
        }
        $csrf = _post('csrf_token');
        if (!Csrf::check($csrf)) {
            cpe_json(['status' => 'error', 'message' => 'Invalid or Expired CSRF Token'], 403);
        }
        $username = trim((string) _post('username'));
        if ($username === '') cpe_json(['status' => 'error', 'message' => 'username required']);
        $row = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
        if ($row) $row->delete();
        cpe_json(['status' => 'success', 'message' => 'Credentials removed']);
    }

    // -----------------------------------------------------------
    // list - for the CPE Manager page (all customers with known
    // PPPoE usernames, joined with saved creds).
    // -----------------------------------------------------------
    if ($action === 'list') {
        $rows = ORM::for_table('tbl_cpe_credentials')
            ->order_by_asc('username')
            ->find_array();
        // Redact passwords before sending to client.
        foreach ($rows as &$r) {
            $r['has_password'] = !empty($r['api_pass']);
            unset($r['api_pass']);
        }
        cpe_json(['status' => 'success', 'data' => $rows]);
    }

    // -----------------------------------------------------------
    // identity - board info from the CPE (MikroTik only)
    // -----------------------------------------------------------
    if ($action === 'identity') {
        $username = trim((string) _get('username'));
        if ($username === '') cpe_json(['status' => 'error', 'message' => 'username required']);
        try {
            $client = cpe_ros_client_for($username);
            $out = [];
            try {
                $res = $client->sendSync(new RosRequest('/system/identity/print'));
                foreach ($res as $row) {
                    $out['identity'] = $row->getProperty('name');
                }
            } catch (Exception $_) {}
            try {
                $res = $client->sendSync(new RosRequest('/system/resource/print'));
                foreach ($res as $row) {
                    $out['board']   = $row->getProperty('board-name');
                    $out['version'] = $row->getProperty('version');
                    $out['uptime']  = $row->getProperty('uptime');
                    $out['cpu']     = $row->getProperty('cpu-load');
                }
            } catch (Exception $_) {}
            try {
                $res = $client->sendSync(new RosRequest('/system/routerboard/print'));
                foreach ($res as $row) {
                    $out['model'] = $row->getProperty('model');
                    $out['serial'] = $row->getProperty('serial-number');
                }
            } catch (Exception $_) {}
            if (method_exists($client, 'disconnect')) $client->disconnect();
            cpe_json(['status' => 'success', 'data' => $out]);
        } catch (Exception $e) {
            cpe_json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------
    // wifi_list - list wireless/wifi interfaces with SSID + security
    // -----------------------------------------------------------
    if ($action === 'wifi_list') {
        $username = trim((string) _get('username'));
        if ($username === '') cpe_json(['status' => 'error', 'message' => 'username required']);
        try {
            $client = cpe_ros_client_for($username);
            $wifis = [];

            // Try the new /interface/wifi stack first (RouterOS 7.13+ WifiWave2).
            try {
                $res = $client->sendSync(new RosRequest('/interface/wifi/print'));
                foreach ($res as $row) {
                    $wifis[] = [
                        'stack'    => 'wifi',
                        'name'     => $row->getProperty('name'),
                        'ssid'     => $row->getProperty('configuration.ssid') ?: $row->getProperty('ssid'),
                        'disabled' => $row->getProperty('disabled'),
                    ];
                }
            } catch (Exception $_) {}

            // Classic /interface/wireless (CAPsMAN / older hardware).
            try {
                $res = $client->sendSync(new RosRequest('/interface/wireless/print'));
                foreach ($res as $row) {
                    $wifis[] = [
                        'stack'         => 'wireless',
                        'name'          => $row->getProperty('name'),
                        'ssid'          => $row->getProperty('ssid'),
                        'disabled'      => $row->getProperty('disabled'),
                        'security'      => $row->getProperty('security-profile'),
                        'radio-name'    => $row->getProperty('radio-name'),
                    ];
                }
            } catch (Exception $_) {}

            if (method_exists($client, 'disconnect')) $client->disconnect();
            cpe_json(['status' => 'success', 'data' => $wifis]);
        } catch (Exception $e) {
            cpe_json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------
    // wifi_set_password - updates either the security-profile
    // (classic /interface/wireless) or the wifi configuration
    // (modern /interface/wifi) on the CPE.
    // -----------------------------------------------------------
    if ($action === 'wifi_set_password') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            cpe_json(['status' => 'error', 'message' => 'POST required'], 405);
        }
        $csrf = _post('csrf_token');
        if (!Csrf::check($csrf)) {
            cpe_json(['status' => 'error', 'message' => 'Invalid or Expired CSRF Token'], 403);
        }
        $username = trim((string) _post('username'));
        $iface    = trim((string) _post('interface'));
        $stack    = trim((string) _post('stack', 'wireless'));
        $newPass  = (string) _post('password');
        if ($username === '' || $iface === '' || $newPass === '') {
            cpe_json(['status' => 'error', 'message' => 'username, interface and password are required']);
        }
        if (strlen($newPass) < 8) {
            cpe_json(['status' => 'error', 'message' => 'WPA2 password must be at least 8 characters']);
        }
        try {
            $client = cpe_ros_client_for($username);

            if ($stack === 'wifi') {
                // Modern wifi (WifiWave2) - set passphrase on the interface.
                $set = new RosRequest('/interface/wifi/set');
                $set->setArgument('numbers', $iface);
                $set->setArgument('security.passphrase', $newPass);
                $set->setArgument('security.authentication-types', 'wpa2-psk,wpa-psk');
                $client->sendSync($set);
            } else {
                // Classic wireless - update the bound security-profile.
                $printSec = new RosRequest('/interface/wireless/print');
                $printSec->setArgument('.proplist', 'name,security-profile');
                $printSec->setQuery(RosQuery::where('name', $iface));
                $res = $client->sendSync($printSec);
                $profile = '';
                foreach ($res as $r) {
                    $profile = $r->getProperty('security-profile') ?: 'default';
                }
                if ($profile === '') $profile = 'default';

                $setSec = new RosRequest('/interface/wireless/security-profiles/set');
                $setSec->setArgument('numbers', $profile);
                $setSec->setArgument('wpa2-pre-shared-key', $newPass);
                $setSec->setArgument('wpa-pre-shared-key', $newPass);
                $setSec->setArgument('authentication-types', 'wpa2-psk,wpa-psk');
                $client->sendSync($setSec);
            }

            if (method_exists($client, 'disconnect')) $client->disconnect();
            cpe_json(['status' => 'success', 'message' => 'Wi-Fi password updated']);
        } catch (Exception $e) {
            cpe_json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------
    // reboot - issues /system/reboot on the CPE
    // -----------------------------------------------------------
    if ($action === 'reboot') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            cpe_json(['status' => 'error', 'message' => 'POST required'], 405);
        }
        $csrf = _post('csrf_token');
        if (!Csrf::check($csrf)) {
            cpe_json(['status' => 'error', 'message' => 'Invalid or Expired CSRF Token'], 403);
        }
        $username = trim((string) _post('username'));
        if ($username === '') cpe_json(['status' => 'error', 'message' => 'username required']);
        try {
            $client = cpe_ros_client_for($username);
            $client->sendSync(new RosRequest('/system/reboot'));
            if (method_exists($client, 'disconnect')) $client->disconnect();
            cpe_json(['status' => 'success', 'message' => 'Reboot command sent']);
        } catch (Exception $e) {
            cpe_json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

// ---------------------------------------------------------------
// HTML page(s)
// ---------------------------------------------------------------
cpe_ensure_schema();
_admin();
if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
    _alert(Lang::T('You do not have permission to access this page'), 'danger', 'dashboard');
}

$ui->assign('_admin', $admin);
$ui->assign('_system_menu', 'customer_router');
$ui->assign('csrf_token', Csrf::generateAndStoreToken());

// Manager page - default landing when no sub-route is given.
$ui->assign('_title', Lang::T('CPE Manager'));
$ui->display('admin/customer_router/manager.tpl');
