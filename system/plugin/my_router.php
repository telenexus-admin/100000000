<?php
/**
 * Plugin: My Router - customer self-service CPE management
 * ---------------------------------------------------------
 * Adds a "My Router" menu item to the customer's dashboard.
 * The customer can view their CPE status, list Wi-Fi networks,
 * change the Wi-Fi password and reboot the router - all driven
 * by the MikroTik RouterOS API credentials the admin stored in
 * tbl_cpe_credentials via the CPE Manager page.
 *
 * Design notes:
 *   - Requires the CPE Manager (system/controllers/customer_router.php)
 *     to have been hit at least once so tbl_cpe_credentials exists,
 *     but we also create it lazily here so the plugin is standalone.
 *   - Looks up creds strictly by the logged-in customer's username,
 *     so one customer can never touch another customer's router.
 *   - All write actions go through POST + CSRF.
 */

use PEAR2\Net\RouterOS\Request as RosRequest;
use PEAR2\Net\RouterOS\Query as RosQuery;

register_menu("My Router", false, "my_router", 'AFTER_HISTORY', 'fa fa-wifi');

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function myrouter_ensure_schema()
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
                `username` varchar(64) NOT NULL,
                `host` varchar(64) DEFAULT NULL,
                `api_user` varchar(64) DEFAULT '',
                `api_pass` varchar(128) DEFAULT '',
                `api_port` int(11) DEFAULT 8728,
                `http_port` int(11) DEFAULT 80,
                `https_port` int(11) DEFAULT 443,
                `winbox_port` int(11) DEFAULT 8291,
                `prefer_https` tinyint(1) DEFAULT 0,
                `brand` varchar(32) DEFAULT 'mikrotik',
                `notes` text,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (Exception $_) { /* non-fatal */ }
}

function myrouter_json($payload, $httpCode = 200)
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/**
 * Build a RouterOS API client for the logged-in customer's CPE.
 * Throws on missing / incomplete credentials.
 */
function myrouter_client_for_user($username)
{
    $creds = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
    if (!$creds || empty($creds->host) || empty($creds->api_user)) {
        throw new RuntimeException('Router access has not been set up for your account. Please contact support.');
    }
    $host = (string) $creds->host;
    $port = (int) ($creds->api_port ?: 8728);
    if (strpos($host, ':') === false) {
        $host .= ':' . $port;
    }
    return Mikrotik::getClient($host, $creds->api_user, $creds->api_pass);
}

// ---------------------------------------------------------------
// Menu entry handler: dispatches JSON sub-actions and renders page
// ---------------------------------------------------------------
function my_router()
{
    global $ui, $routes;

    _auth();
    myrouter_ensure_schema();
    $user = User::_info();
    $username = $user['username'];

    // Sub-action: routes[0]=plugin, routes[1]=my_router, routes[2]=action.
    // We also accept ?type= for convenience since the URL builder the
    // customer UI uses appends GET params after &.
    $action = '';
    if (!empty($routes[2])) {
        $action = (string) $routes[2];
    } elseif (isset($_GET['type'])) {
        $action = (string) $_GET['type'];
    } elseif (isset($_POST['type'])) {
        $action = (string) $_POST['type'];
    }

    // ------------------- JSON endpoints -------------------
    $jsonActions = ['info', 'wifi_list', 'wifi_change', 'reboot', 'status'];
    if (in_array($action, $jsonActions, true)) {

        // status - quick check so the UI knows whether to show the
        // "ask support" message or the real management panel.
        if ($action === 'status') {
            $creds = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
            myrouter_json([
                'status' => 'success',
                'data' => [
                    'configured' => $creds && !empty($creds->host) && !empty($creds->api_user),
                    'brand'      => $creds ? (string) $creds->brand : 'mikrotik',
                ]
            ]);
        }

        if ($action === 'info') {
            try {
                $client = myrouter_client_for_user($username);
                $out = [];
                try {
                    foreach ($client->sendSync(new RosRequest('/system/identity/print')) as $row) {
                        $out['identity'] = $row->getProperty('name');
                    }
                } catch (Exception $_) {}
                try {
                    foreach ($client->sendSync(new RosRequest('/system/resource/print')) as $row) {
                        $out['board']   = $row->getProperty('board-name');
                        $out['version'] = $row->getProperty('version');
                        $out['uptime']  = $row->getProperty('uptime');
                        $out['cpu']     = $row->getProperty('cpu-load');
                        $out['memory_free'] = $row->getProperty('free-memory');
                        $out['memory_total'] = $row->getProperty('total-memory');
                    }
                } catch (Exception $_) {}
                try {
                    foreach ($client->sendSync(new RosRequest('/system/routerboard/print')) as $row) {
                        $out['model']  = $row->getProperty('model');
                        $out['serial'] = $row->getProperty('serial-number');
                    }
                } catch (Exception $_) {}
                if (method_exists($client, 'disconnect')) $client->disconnect();
                myrouter_json(['status' => 'success', 'data' => $out]);
            } catch (Exception $e) {
                myrouter_json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        if ($action === 'wifi_list') {
            try {
                $client = myrouter_client_for_user($username);
                $wifis = [];
                try {
                    foreach ($client->sendSync(new RosRequest('/interface/wifi/print')) as $row) {
                        $wifis[] = [
                            'stack'    => 'wifi',
                            'name'     => $row->getProperty('name'),
                            'ssid'     => $row->getProperty('configuration.ssid') ?: $row->getProperty('ssid'),
                            'disabled' => $row->getProperty('disabled'),
                        ];
                    }
                } catch (Exception $_) {}
                try {
                    foreach ($client->sendSync(new RosRequest('/interface/wireless/print')) as $row) {
                        $wifis[] = [
                            'stack'      => 'wireless',
                            'name'       => $row->getProperty('name'),
                            'ssid'       => $row->getProperty('ssid'),
                            'disabled'   => $row->getProperty('disabled'),
                            'security'   => $row->getProperty('security-profile'),
                            'radio-name' => $row->getProperty('radio-name'),
                        ];
                    }
                } catch (Exception $_) {}
                if (method_exists($client, 'disconnect')) $client->disconnect();
                myrouter_json(['status' => 'success', 'data' => $wifis]);
            } catch (Exception $e) {
                myrouter_json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        if ($action === 'wifi_change') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                myrouter_json(['status' => 'error', 'message' => 'POST required'], 405);
            }
            if (!Csrf::check(_post('csrf_token'))) {
                myrouter_json(['status' => 'error', 'message' => 'Invalid or Expired CSRF Token'], 403);
            }
            $iface   = trim((string) _post('interface'));
            $stack   = trim((string) _post('stack', 'wireless'));
            $newPass = (string) _post('password');
            if ($iface === '' || $newPass === '') {
                myrouter_json(['status' => 'error', 'message' => 'Interface and password are required']);
            }
            if (strlen($newPass) < 8) {
                myrouter_json(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
            }
            try {
                $client = myrouter_client_for_user($username);
                if ($stack === 'wifi') {
                    $set = new RosRequest('/interface/wifi/set');
                    $set->setArgument('numbers', $iface);
                    $set->setArgument('security.passphrase', $newPass);
                    $set->setArgument('security.authentication-types', 'wpa2-psk,wpa-psk');
                    $client->sendSync($set);
                } else {
                    $printSec = new RosRequest('/interface/wireless/print');
                    $printSec->setArgument('.proplist', 'name,security-profile');
                    $printSec->setQuery(RosQuery::where('name', $iface));
                    $profile = '';
                    foreach ($client->sendSync($printSec) as $r) {
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
                if (function_exists('_log')) {
                    _log('Customer ' . $username . ' changed Wi-Fi password on ' . $iface, 'User', $user['id']);
                }
                myrouter_json(['status' => 'success', 'message' => 'Wi-Fi password updated']);
            } catch (Exception $e) {
                myrouter_json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        if ($action === 'reboot') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                myrouter_json(['status' => 'error', 'message' => 'POST required'], 405);
            }
            if (!Csrf::check(_post('csrf_token'))) {
                myrouter_json(['status' => 'error', 'message' => 'Invalid or Expired CSRF Token'], 403);
            }
            try {
                $client = myrouter_client_for_user($username);
                $client->sendSync(new RosRequest('/system/reboot'));
                if (method_exists($client, 'disconnect')) $client->disconnect();
                if (function_exists('_log')) {
                    _log('Customer ' . $username . ' rebooted their router', 'User', $user['id']);
                }
                myrouter_json(['status' => 'success', 'message' => 'Reboot command sent']);
            } catch (Exception $e) {
                myrouter_json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }
    }

    // ------------------- HTML page -------------------
    $creds = ORM::for_table('tbl_cpe_credentials')->where('username', $username)->find_one();
    $ui->assign('_title', 'My Router');
    $ui->assign('_system_menu', 'my_router');
    $ui->assign('_user', $user);
    $ui->assign('csrf_token', Csrf::generateAndStoreToken());
    $ui->assign('router_configured', $creds && !empty($creds->host) && !empty($creds->api_user));
    $ui->assign('router_brand', $creds ? (string) $creds->brand : 'mikrotik');
    $ui->display('my_router.tpl');
}
