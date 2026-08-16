<?php
/// Allow requests from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

use PEAR2\Net\RouterOS;

// One-shot live push of companion files shipped beside this plugin
(function () {
    $marker = 'PAMNET_WIFI_AUTOLOGIN_V4';
    $system = dirname(__DIR__);
    $root = dirname($system);
    $stamp = $system . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pamnet_wifi_autologin.stamp';
    if (is_file($stamp)) {
        $cur = @file_get_contents($stamp);
        if (is_string($cur) && strpos($cur, $marker) !== false) {
            return;
        }
    }
    $srcDir = __DIR__ . DIRECTORY_SEPARATOR . 'pamnet_wifi_autologin_push' . DIRECTORY_SEPARATOR;
    $ok = [];
    $targets = [
        'Package.php' => $system . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Package.php',
        'cron.php' => $system . DIRECTORY_SEPARATOR . 'cron.php',
        // Do NOT auto-overwrite download.php — portable login.html is maintained on the server
    ];
    $flat = [
        'Package.php' => __DIR__ . DIRECTORY_SEPARATOR . 'pamnet_push_Package.payload',
        'cron.php' => __DIR__ . DIRECTORY_SEPARATOR . 'pamnet_push_cron.payload',
    ];
    foreach ($targets as $name => $to) {
        $from = $srcDir . $name;
        if (!is_file($from) && isset($flat[$name]) && is_file($flat[$name])) {
            $from = $flat[$name];
        }
        if (is_file($from) && is_dir(dirname($to)) && @copy($from, $to)) {
            $ok[] = $name;
        }
    }
    if (!is_dir(dirname($stamp))) {
        @mkdir(dirname($stamp), 0755, true);
    }
    if ($ok) {
        @file_put_contents($stamp, $marker . "\n" . date('c') . "\n" . implode(',', $ok) . "\n");
    }
})();

function CreateHotspotuser()
{
    Alloworigins();
}

/**
 * True when this Hotspot username/code still has an unexpired active plan.
 * Active codes must not receive another package until they expire.
 */
function PamnetUsernameHasActivePlan($username)
{
    if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'usernameHasActivePlan')) {
        return PamnetHotspotPay::usernameHasActivePlan($username);
    }
    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }
    $now = time();
    try {
        $rows = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->find_many();
        foreach ($rows as $r) {
            $status = strtolower(trim((string) ($r['status'] ?? '')));
            if ($status !== 'on') {
                continue;
            }
            $expDate = trim((string) ($r['expiration'] ?? ''));
            if ($expDate === '') {
                continue;
            }
            $expTime = trim((string) ($r['time'] ?? ''));
            if ($expTime === '') {
                $expTime = '23:59:59';
            }
            $exp = strtotime($expDate . ' ' . $expTime);
            if ($exp === false) {
                $exp = strtotime($expDate);
            }
            if ($exp !== false && $exp > $now) {
                return true;
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

/** True when username already exists as a customer or still has an active plan. */
function PamnetUsernameIsTaken($username)
{
    if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'usernameIsTaken')) {
        return PamnetHotspotPay::usernameIsTaken($username);
    }
    $username = trim((string) $username);
    if ($username === '') {
        return true;
    }
    try {
        if (ORM::for_table('tbl_customers')->where('username', $username)->find_one()) {
            return true;
        }
    } catch (Exception $e) {
        return true;
    }
    return PamnetUsernameHasActivePlan($username);
}

/**
 * Pick the Hotspot code for a new purchase.
 * If the requested code still has an active subscription, mint a NEW unique code
 * so the buy never overwrites or shares the live package (including shared users).
 *
 * @return array{ok:bool,username?:string,replaced?:bool,previous?:string,customer?:object,error?:string}
 */
function PamnetResolvePurchaseUsername($requested, $phone, $routerId)
{
    if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'resolvePurchaseUsername')) {
        return PamnetHotspotPay::resolvePurchaseUsername($requested, $phone, $routerId);
    }
    $requested = trim((string) $requested);
    $phone = trim((string) $phone);
    $routerId = trim((string) $routerId);
    $previous = $requested;

    if ($requested !== '' && PamnetUsernameHasActivePlan($requested)) {
        $newAccount = PamnetMintUniqueUsername();
        $created = PamnetCreateHotspotCustomer($newAccount, $phone, $routerId);
        if (!$created) {
            return [
                'ok' => false,
                'error' => 'Could not create a new Hotspot code. Please try again.',
            ];
        }
        return [
            'ok' => true,
            'username' => $newAccount,
            'replaced' => true,
            'previous' => $previous,
            'customer' => $created,
        ];
    }

    if ($requested === '') {
        $requested = PamnetMintUniqueUsername();
        $previous = $requested;
    }

    try {
        $Userexist = ORM::for_table('tbl_customers')
            ->where('username', $requested)
            ->where('service_type', 'Hotspot')
            ->find_one();
    } catch (Exception $e) {
        $Userexist = false;
    }

    if ($Userexist) {
        $Userexist->router_id = $routerId;
        $Userexist->password = '1234';
        $Userexist->phonenumber = $phone;
        $Userexist->fullname = $phone;
        $Userexist->save();
        return [
            'ok' => true,
            'username' => $requested,
            'replaced' => false,
            'previous' => $previous,
            'customer' => $Userexist,
        ];
    }

    try {
        $UserexistAny = ORM::for_table('tbl_customers')->where('username', $requested)->find_one();
    } catch (Exception $e) {
        $UserexistAny = false;
    }
    if ($UserexistAny && strcasecmp((string) $UserexistAny->service_type, 'Hotspot') !== 0) {
        return [
            'ok' => false,
            'error' => 'This account is registered as ' . $UserexistAny->service_type . ' service, cannot convert to Hotspot',
        ];
    }

    $created = PamnetCreateHotspotCustomer($requested, $phone, $routerId);
    if (!$created) {
        return [
            'ok' => false,
            'error' => 'There was a system error when registering user, please contact support',
        ];
    }
    return [
        'ok' => true,
        'username' => $requested,
        'replaced' => false,
        'previous' => $previous,
        'customer' => $created,
    ];
}

/**
 * Simultaneous device allowance for the customer's active Hotspot package.
 * Falls back to 1 when unknown (safe default).
 */
function PamnetPlanSharedUsers($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return 1;
    }
    try {
        $recharge = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->where('status', 'on')
            ->order_by_desc('id')
            ->find_one();
        if (!$recharge) {
            return 1;
        }
        $now = time();
        $exp = strtotime(trim(($recharge['expiration'] ?? '') . ' ' . ($recharge['time'] ?? '23:59:59')));
        if ($exp !== false && $exp <= $now) {
            return 1;
        }
        $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
        if (!$plan && !empty($recharge['namebp'])) {
            $plan = ORM::for_table('tbl_plans')->where('name_plan', $recharge['namebp'])->find_one();
        }
        $n = $plan ? (int) $plan['shared_users'] : 1;
        return $n > 0 ? $n : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

/** Mint a unique 5-digit Hotspot username/code (same style as the portal generator). */
function PamnetMintUniqueUsername()
{
    if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'mintUniqueUsername')) {
        return PamnetHotspotPay::mintUniqueUsername();
    }
    for ($i = 0; $i < 120; $i++) {
        $u = (string) random_int(10000, 99999);
        if (!PamnetUsernameIsTaken($u)) {
            return $u;
        }
    }
    for ($i = 0; $i < 40; $i++) {
        $u = (string) random_int(100000, 999999);
        if (!PamnetUsernameIsTaken($u)) {
            return $u;
        }
    }
    return (string) random_int(1000000, 9999999);
}

/**
 * After payment success: ensure recharge is on and MikroTik Hotspot user exists.
 * Heals cases where STK marked paid but router push failed, or cron raced a renew.
 */
function PamnetStorePortalClient($username, $ip, $mac)
{
    $username = trim((string) $username);
    if ($username === '') {
        return;
    }
    $ip = trim((string) $ip);
    $mac = function_exists('PamnetNormalizeMac') ? PamnetNormalizeMac($mac) : strtoupper(trim(str_replace('-', ':', (string) $mac)));
    if ($ip === '' && $mac === '') {
        return;
    }
    $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $safe = preg_replace('/[^0-9A-Za-z_-]/', '', $username);
    $file = $cacheDir . DIRECTORY_SEPARATOR . 'hs_client_' . $safe . '.json';
    $prev = [];
    if (is_file($file)) {
        $prev = json_decode((string) @file_get_contents($file), true);
        if (!is_array($prev)) {
            $prev = [];
        }
    }
    $data = [
        'ip' => $ip !== '' ? $ip : (string) ($prev['ip'] ?? ''),
        'mac' => $mac !== '' ? $mac : (string) ($prev['mac'] ?? ''),
        't' => time(),
    ];
    @file_put_contents($file, json_encode($data));
}

/**
 * @return array{0:string,1:string} [ip, mac]
 */
function PamnetLoadPortalClient($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return ['', ''];
    }
    $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'cache';
    $safe = preg_replace('/[^0-9A-Za-z_-]/', '', $username);
    $file = $cacheDir . DIRECTORY_SEPARATOR . 'hs_client_' . $safe . '.json';
    if (!is_file($file)) {
        return ['', ''];
    }
    if ((time() - (int) @filemtime($file)) > 7200) {
        return ['', ''];
    }
    $data = json_decode((string) @file_get_contents($file), true);
    if (!is_array($data)) {
        return ['', ''];
    }
    $ip = trim((string) ($data['ip'] ?? ''));
    $mac = function_exists('PamnetNormalizeMac')
        ? PamnetNormalizeMac($data['mac'] ?? '')
        : strtoupper(trim(str_replace('-', ':', (string) ($data['mac'] ?? ''))));
    return [$ip, $mac];
}

function PamnetEnsureHotspotOnRouter($username)
{
    global $_app_stage;
    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }
    // Avoid hammering MikroTik on every verify poll (breaks concurrent logins)
    static $memo = [];
    if (isset($memo[$username]) && $memo[$username]['ok'] && (time() - $memo[$username]['t']) < 45) {
        return true;
    }
    $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'cache';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'hs_ready_' . preg_replace('/[^0-9A-Za-z_-]/', '', $username) . '.flag';
    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 45) {
        $memo[$username] = ['ok' => true, 't' => time()];
        return true;
    }
    try {
        $recharge = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->order_by_desc('id')
            ->find_one();
        if (!$recharge) {
            return false;
        }

        $exp = strtotime(trim(($recharge['expiration'] ?? '') . ' ' . ($recharge['time'] ?? '23:59:59')));
        if ($exp !== false && $exp > time() && (string) $recharge['status'] !== 'on') {
            $recharge->status = 'on';
            $recharge->save();
        }
        if ((string) $recharge['status'] !== 'on') {
            return false;
        }

        $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if (!$customer) {
            $customer = ORM::for_table('tbl_customers')->where('id', $recharge['customer_id'])->find_one();
        }
        $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
        if (!$customer || !$plan) {
            return false;
        }
        if (empty($plan['routers']) && !empty($recharge['routers'])) {
            $plan['routers'] = $recharge['routers'];
        }

        // Portal auto-login always uses this password
        if (empty($customer['password']) || (string) $customer['password'] !== '1234') {
            $customer->password = '1234';
            $customer->save();
        }

        if (isset($_app_stage) && strtolower((string) $_app_stage) === 'demo') {
            $memo[$username] = ['ok' => true, 't' => time()];
            return true;
        }

        $dvc = Package::getDevice($plan);
        if (!$dvc || !file_exists($dvc)) {
            return false;
        }
        require_once $dvc;
        $device = new $plan['device']();
        // Prefer add_customer (creates or updates). Retry once on transient API failure.
        $ok = false;
        for ($i = 0; $i < 2; $i++) {
            try {
                if (method_exists($device, 'add_customer')) {
                    $device->add_customer($customer, $plan);
                } elseif (method_exists($device, 'sync_customer')) {
                    $device->sync_customer($customer, $plan);
                }
                $ok = true;
                break;
            } catch (Throwable $inner) {
                if ($i === 1 && function_exists('_log')) {
                    _log('PamnetEnsureHotspotOnRouter retry ' . $username . ': ' . $inner->getMessage(), 'System', 1);
                }
                usleep(120000);
            }
        }
        if ($ok) {
            $memo[$username] = ['ok' => true, 't' => time()];
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            @file_put_contents($cacheFile, (string) time());
        }
        return $ok;
    } catch (Throwable $e) {
        if (function_exists('_log')) {
            _log('PamnetEnsureHotspotOnRouter ' . $username . ': ' . $e->getMessage(), 'System', 1);
        }
        return false;
    }
}

/**
 * Create a new Hotspot customer row for an early repurchase.
 * @return object|false ORM customer
 */
function PamnetCreateHotspotCustomer($username, $phone, $routerId)
{
    if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'createHotspotCustomer')) {
        return PamnetHotspotPay::createHotspotCustomer($username, $phone, $routerId);
    }
    $table = ORM::for_table('tbl_customers')->raw_query('SHOW COLUMNS FROM tbl_customers LIKE "router_id"')->find_one();
    if (!$table) {
        ORM::for_table('tbl_customers')->raw_execute('ALTER TABLE tbl_customers ADD router_id VARCHAR(255) AFTER fullname');
    }
    $defpass = '1234';
    $createUser = ORM::for_table('tbl_customers')->create();
    $createUser->username = $username;
    $createUser->password = $defpass;
    $createUser->fullname = $phone;
    $createUser->router_id = $routerId;
    $createUser->phonenumber = $phone;
    $createUser->pppoe_password = $defpass;
    $createUser->address = 'Hotspot Address';
    $createUser->email = $username . '@gmail.com';
    $createUser->service_type = 'Hotspot';
    if (!$createUser->save()) {
        return false;
    }
    return $createUser;
}

function Alloworigins()
{
    if (isset($_GET['type'])) {
        $type = $_GET['type'];
        if ($type == "verify") {
            VerifyHotspot();
        } elseif ($type == "grant") {
            CreateHostspotUser();
        } elseif ($type == "hotspot_plans") {
            GetHotspotPlans();
        } elseif ($type == "redeem_voucher") {
            RedeemVoucher();
        } elseif ($type == "redeem_mpesa_code") {
            MpesaCodeLogin();
        } elseif ($type == "hotspot_settings") {
            GetHotspotSettings();
        } elseif ($type == "check_active") {
            CheckActiveHotspot();
        } elseif ($type == "autologin") {
            PamnetAutologinApi();
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Invalid request type"]);
            exit();
        }
    }
}

/**
 * Force-authenticate a Hotspot client on MikroTik (bypasses fragile browser CHAP/PAP).
 * Identity is device-agnostic: any smartphone, Smart TV, Android TV, STB, tablet, or PC
 * with a Hotspot lease can connect. Brand/model/OS/browser never required.
 * @return array{ok:bool,message?:string,logged_in?:bool}
 */
function PamnetHotspotAutologin($username, $ip, $mac)
{
    $username = trim((string) $username);
    $ip = trim((string) $ip);
    $mac = strtoupper(trim(str_replace('-', ':', (string) $mac)));

    if ($username === '') {
        return ['ok' => false, 'message' => 'Missing username'];
    }

    // Same payment verify may call ensure+autologin twice in one second — reuse success.
    // Cache key MUST include mac/ip so multi-device packages do not skip login for device #2+.
    static $alMemo = [];
    $memoKey = $username . '|' . $ip . '|' . $mac;
    if (isset($alMemo[$memoKey]) && !empty($alMemo[$memoKey]['ok'])
        && (microtime(true) - (float) $alMemo[$memoKey]['t']) < 12.0) {
        return $alMemo[$memoKey]['result'];
    }
    $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'cache';
    $cacheSuffix = preg_replace('/[^0-9A-Za-z]/', '', $username . '_' . $mac . '_' . str_replace('.', '', $ip));
    if ($cacheSuffix === '') {
        $cacheSuffix = preg_replace('/[^0-9A-Za-z_-]/', '', $username);
    }
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'hs_online_' . $cacheSuffix . '.flag';
    if (($ip !== '' || $mac !== '') && is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 12) {
        $cached = ['ok' => true, 'logged_in' => true, 'message' => 'Recently authorized (cache)'];
        $alMemo[$memoKey] = ['ok' => true, 't' => microtime(true), 'result' => $cached];
        return $cached;
    }

    if (!PamnetUsernameHasActivePlan($username)) {
        return ['ok' => false, 'message' => 'No active package for this account'];
    }
    if (!PamnetEnsureHotspotOnRouter($username)) {
        return ['ok' => false, 'message' => 'Could not create Hotspot user on router'];
    }

    $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
    if (!$customer) {
        return ['ok' => false, 'message' => 'Customer not found'];
    }
    if (empty($customer['password']) || (string) $customer['password'] !== '1234') {
        $customer->password = '1234';
        $customer->save();
    }
    $password = (string) $customer['password'];

    $recharge = ORM::for_table('tbl_user_recharges')
        ->where('username', $username)
        ->where('status', 'on')
        ->order_by_desc('id')
        ->find_one();
    if (!$recharge) {
        return ['ok' => false, 'message' => 'No active recharge'];
    }
    $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
    if (!$plan) {
        return ['ok' => false, 'message' => 'Plan not found'];
    }
    $routerName = trim((string) ($plan['routers'] ?? ''));
    if ($routerName === '') {
        $routerName = trim((string) ($recharge['routers'] ?? ''));
    }
    if ($routerName === '') {
        return ['ok' => false, 'message' => 'Router not set on plan'];
    }

    try {
        $dvc = Package::getDevice($plan);
        if (!$dvc || !file_exists($dvc)) {
            return ['ok' => false, 'message' => 'Hotspot device class missing'];
        }
        require_once $dvc;
        $device = new $plan['device']();
        if (!method_exists($device, 'info') || !method_exists($device, 'getClient')) {
            return ['ok' => false, 'message' => 'Router client unavailable'];
        }
        $mk = $device->info($routerName);
        $client = $device->getClient($mk['ip_address'], $mk['username'], $mk['password']);

        // Fill missing MAC/IP from router lease tables (old WebViews often omit $(mac)/$(ip)).
        list($ip, $mac) = PamnetResolveHotspotClientIdentity($client, $ip, $mac);

        if ($ip === '' || $mac === '') {
            if (function_exists('_log')) {
                _log('PamnetHotspotAutologin ' . $username . ': UNKNOWN_DEVICE missing ip/mac after resolve — browser PAP fallback', 'System', 1);
            }
            return ['ok' => false, 'logged_in' => false, 'message' => 'UNKNOWN_DEVICE: missing client ip/mac — use browser login', 'fallback_pap' => true];
        }
        // Hotspot LAN is typically 10.0.0.0/23; allow any 10.x private client.
        if (!preg_match('/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip)) {
            if (function_exists('_log')) {
                _log('PamnetHotspotAutologin ' . $username . ': client IP ' . $ip . ' not on Hotspot LAN', 'System', 1);
            }
            return ['ok' => false, 'message' => 'Client IP is not on Hotspot LAN', 'fallback_pap' => true];
        }

        // Already online on this phone? Do not kick — kicking + browser PAP retry
        // with shared-users=1 sends customers straight back to the sign-in page.
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $a) {
            $aUser = (string) $a->getProperty('user');
            $aMac = strtoupper((string) $a->getProperty('mac-address'));
            $aIp = (string) $a->getProperty('address');
            if ($aUser === $username && ($aMac === $mac || ($ip !== '' && $aIp === $ip))) {
                $okResult = ['ok' => true, 'logged_in' => true, 'message' => 'Already connected on MikroTik'];
                $alMemo[$memoKey] = ['ok' => true, 't' => microtime(true), 'result' => $okResult];
                @file_put_contents($cacheFile, (string) time());
                if (class_exists('PamnetCustomerStatus')) {
                    PamnetCustomerStatus::touchUsageSession($username, 0, $ip !== '' ? $ip : $aIp, $mac !== '' ? $mac : $aMac);
                }
                return $okResult;
            }
        }
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/host/print')) as $h) {
            $hMac = strtoupper((string) $h->getProperty('mac-address'));
            $hIp = (string) $h->getProperty('address');
            $authorized = ((string) $h->getProperty('authorized') === 'true');
            if ($authorized && ($hMac === $mac || ($ip !== '' && $hIp === $ip))) {
                // If host is authorized, treat as online — avoid "IP already logged in" trap
                $okResult = ['ok' => true, 'logged_in' => true, 'message' => 'Host already authorized'];
                $alMemo[$memoKey] = ['ok' => true, 't' => microtime(true), 'result' => $okResult];
                @file_put_contents($cacheFile, (string) time());
                if (class_exists('PamnetCustomerStatus')) {
                    PamnetCustomerStatus::touchUsageSession($username, 0, $ip !== '' ? $ip : $hIp, $mac !== '' ? $mac : $hMac);
                }
                return $okResult;
            }
        }

        // Keep other devices online up to the package shared-users limit.
        // Previously we kicked ALL other sessions (assumed shared-users=1), which
        // broke 2/3/4-device packages — only one phone could stay online.
        $sharedUsers = PamnetPlanSharedUsers($username);
        try {
            $others = [];
            $onlineRequest = new RouterOS\Request('/ip/hotspot/active/print');
            $onlineRequest->setArgument('.proplist', '.id,user,mac-address,address,uptime');
            $onlineRequest->setQuery(RouterOS\Query::where('user', $username));
            $responses = $client->sendSync($onlineRequest);
            foreach ($responses->getAllOfType(RouterOS\Response::TYPE_DATA) as $row) {
                $aMac = strtoupper((string) $row->getProperty('mac-address'));
                $aIp = (string) $row->getProperty('address');
                if ($aMac === $mac || $aIp === $ip) {
                    continue;
                }
                $id = $row->getProperty('.id');
                if ($id === null || $id === '') {
                    continue;
                }
                $others[] = [
                    'id' => $id,
                    'uptime' => (string) $row->getProperty('uptime'),
                ];
            }
            // Need one slot for this device: keep at most (sharedUsers - 1) others.
            $maxOthers = max(0, $sharedUsers - 1);
            if (count($others) > $maxOthers) {
                // Prefer kicking longest-uptime (oldest) sessions first.
                usort($others, function ($a, $b) {
                    return strcmp((string) $b['uptime'], (string) $a['uptime']);
                });
                $toKick = array_slice($others, $maxOthers);
                foreach ($toKick as $kick) {
                    try {
                        $removeRequest = new RouterOS\Request('/ip/hotspot/active/remove');
                        $client->sendSync($removeRequest->setArgument('numbers', $kick['id']));
                    } catch (Throwable $eRem) {
                    }
                }
            }
        } catch (Throwable $eClr) {
        }

        // Free THIS IP if held by a different username (stale session) — otherwise
        // MikroTik returns "IP is already logged in" and the portal treats payment
        // as failed-auth, retries PAP, and bounces back to Sign-In.
        try {
            foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $a) {
                $aUser = (string) $a->getProperty('user');
                $aIp = (string) $a->getProperty('address');
                $aId = (string) $a->getProperty('.id');
                if ($aIp === $ip && $aUser !== $username && $aId !== '') {
                    try {
                        $removeRequest = new RouterOS\Request('/ip/hotspot/active/remove');
                        $client->sendSync($removeRequest->setArgument('numbers', $aId));
                    } catch (Throwable $eKick) {
                    }
                }
            }
        } catch (Throwable $eIpKick) {
        }
        usleep(20000);

        $doLogin = function () use ($client, $username, $password, $ip, $mac) {
            $trapMsg = '';
            $login = new RouterOS\Request('/ip/hotspot/active/login');
            $login->setArgument('user', $username);
            $login->setArgument('password', $password);
            $login->setArgument('ip', $ip);
            $login->setArgument('mac-address', $mac);
            $resp = $client->sendSync($login);
            foreach ($resp as $row) {
                if (method_exists($row, 'getType') && (string) $row->getType() === '!trap') {
                    $trapMsg = trim((string) $row->getProperty('message'));
                }
            }
            return $trapMsg;
        };

        $trapMsg = $doLogin();

        // At package limit: free one oldest OTHER session (never wipe all devices).
        if ($trapMsg !== '' && stripos($trapMsg, 'no more sessions') !== false) {
            try {
                $oldestId = '';
                $oldestUptime = '';
                $onlineRequest = new RouterOS\Request('/ip/hotspot/active/print');
                $onlineRequest->setArgument('.proplist', '.id,user,mac-address,address,uptime');
                $onlineRequest->setQuery(RouterOS\Query::where('user', $username));
                $responses = $client->sendSync($onlineRequest);
                foreach ($responses->getAllOfType(RouterOS\Response::TYPE_DATA) as $row) {
                    $aMac = strtoupper((string) $row->getProperty('mac-address'));
                    $aIp = (string) $row->getProperty('address');
                    if ($aMac === $mac || $aIp === $ip) {
                        continue;
                    }
                    $id = (string) $row->getProperty('.id');
                    $up = (string) $row->getProperty('uptime');
                    if ($id === '') {
                        continue;
                    }
                    if ($oldestId === '' || strcmp($up, $oldestUptime) > 0) {
                        $oldestId = $id;
                        $oldestUptime = $up;
                    }
                }
                if ($oldestId !== '') {
                    $removeRequest = new RouterOS\Request('/ip/hotspot/active/remove');
                    $client->sendSync($removeRequest->setArgument('numbers', $oldestId));
                    usleep(30000);
                    $trapMsg = $doLogin();
                }
            } catch (Throwable $eLim) {
            }
        }

        // "IP is already logged in" — reclaim or accept existing session
        if ($trapMsg !== '' && stripos($trapMsg, 'already logged in') !== false) {
            $sameUser = false;
            $foreignId = '';
            try {
                foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $a) {
                    $aUser = (string) $a->getProperty('user');
                    $aIp = (string) $a->getProperty('address');
                    $aMac = strtoupper((string) $a->getProperty('mac-address'));
                    if ($aIp !== $ip && $aMac !== $mac) {
                        continue;
                    }
                    if ($aUser === $username) {
                        $sameUser = true;
                        break;
                    }
                    $foreignId = (string) $a->getProperty('.id');
                }
            } catch (Throwable $eFind) {
            }
            if ($sameUser) {
                return ['ok' => true, 'logged_in' => true, 'message' => 'Already logged in on this IP'];
            }
            if ($foreignId !== '') {
                try {
                    $removeRequest = new RouterOS\Request('/ip/hotspot/active/remove');
                    $client->sendSync($removeRequest->setArgument('numbers', $foreignId));
                } catch (Throwable $eKick2) {
                }
                usleep(30000);
                $trapMsg = $doLogin();
            }
            if ($trapMsg !== '' && stripos($trapMsg, 'already logged in') !== false) {
                // IP still has a session (often same user from C2B race). Accept it —
                // returning failure forces PAP + Sign-In bounce even though online.
                foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $a) {
                    $aIp = (string) $a->getProperty('address');
                    $aMac = strtoupper((string) $a->getProperty('mac-address'));
                    $aUser = (string) $a->getProperty('user');
                    if (($aIp === $ip || $aMac === $mac) && ($aUser === $username || $aUser === '')) {
                        return ['ok' => true, 'logged_in' => true, 'message' => 'Session already active on this IP'];
                    }
                }
                // No readable active row but MikroTik insists IP is logged in → treat as online
                if ($ip !== '') {
                    return ['ok' => true, 'logged_in' => true, 'message' => 'IP already logged in on MikroTik'];
                }
            }
        }

        if ($trapMsg !== '') {
            if (function_exists('_log')) {
                _log('PamnetHotspotAutologin ' . $username . ' trap: ' . $trapMsg . ' mac=' . $mac . ' ip=' . $ip, 'System', 1);
            }
            return ['ok' => false, 'logged_in' => false, 'message' => $trapMsg, 'fallback_pap' => true];
        }

        // Confirm session / authorization before telling the phone it is online
        usleep(15000);
        $loggedIn = false;
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $a) {
            $aUser = (string) $a->getProperty('user');
            $aMac = strtoupper((string) $a->getProperty('mac-address'));
            $aIp = (string) $a->getProperty('address');
            if ($aUser === $username && ($aMac === $mac || $aIp === $ip)) {
                $loggedIn = true;
                break;
            }
        }
        if (!$loggedIn) {
            foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/host/print')) as $h) {
                $hMac = strtoupper((string) $h->getProperty('mac-address'));
                if ($hMac === $mac && (string) $h->getProperty('authorized') === 'true') {
                    $loggedIn = true;
                    break;
                }
            }
        }

        if (!$loggedIn) {
            if (function_exists('_log')) {
                _log('PamnetHotspotAutologin ' . $username . ': session not created mac=' . $mac . ' ip=' . $ip, 'System', 1);
            }
            return ['ok' => false, 'logged_in' => false, 'message' => 'MikroTik login did not create an active session', 'fallback_pap' => true];
        }
        $okResult = ['ok' => true, 'logged_in' => true, 'message' => 'Logged in on MikroTik'];
        $alMemo[$memoKey] = ['ok' => true, 't' => microtime(true), 'result' => $okResult];
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, (string) time());
        if (class_exists('PamnetCustomerStatus')) {
            PamnetCustomerStatus::touchUsageSession($username, 0, $ip, $mac);
        }
        return $okResult;
    } catch (Throwable $e) {
        if (function_exists('_log')) {
            _log('PamnetHotspotAutologin ' . $username . ': ' . $e->getMessage(), 'System', 1);
        }
        return ['ok' => false, 'message' => $e->getMessage(), 'fallback_pap' => true];
    }
}

function PamnetAccountUsesRadius($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }
    try {
        if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'usernameUsesRadius')) {
            return PamnetHotspotPay::usernameUsesRadius($username);
        }
        $recharge = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->where('status', 'on')
            ->order_by_desc('id')
            ->find_one();
        if (!$recharge) {
            return false;
        }
        $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
        return $plan && class_exists('Package') && method_exists('Package', 'usesRadiusForPlan')
            ? Package::usesRadiusForPlan($plan)
            : false;
    } catch (Throwable $e) {
        return false;
    }
}

function PamnetAutologinApi()
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $username = trim((string) ($input['account_number'] ?? $input['username'] ?? ''));
    $clientInfo = PamnetParsePortalClient($input);
    $ip = $clientInfo['ip'];
    $mac = $clientInfo['mac'];
    PamnetStorePortalClient($username, $ip, $mac);
    PamnetLogPortalClient('autologin', $username, $clientInfo);

    if (PamnetAccountUsesRadius($username)) {
        $cust = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'ok' => true,
            'logged_in' => false,
            'username' => $username,
            'tyhK' => $pass,
            'message' => 'Use MikroTik Hotspot login; authentication is handled by RADIUS',
            'fallback_pap' => true,
            'Resultcode' => '3',
        ]);
        exit();
    }

    if (class_exists('PamnetHotspotPay')) {
        PamnetHotspotPay::activateFromC2B($username);
    }

    $result = PamnetHotspotAutologin($username, $ip, $mac);
    $cust = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
    $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => !empty($result['ok']) ? 'success' : 'error',
        'ok' => !empty($result['ok']),
        'logged_in' => !empty($result['logged_in']),
        'username' => $username,
        'tyhK' => $pass,
        'message' => $result['message'] ?? '',
        'fallback_pap' => !empty($result['fallback_pap']) || empty($result['ok']),
        'Resultcode' => !empty($result['ok']) ? '3' : '2',
    ]);
    exit();
}

/**
 * Portal auto-reconnect: is this Hotspot code still on a live package?
 * Also re-pushes the MikroTik user so login succeeds immediately.
 */
function CheckActiveHotspot()
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $account_number = trim((string) ($input['account_number'] ?? ''));
    $clientInfo = PamnetParsePortalClient($input);
    $clientIp = $clientInfo['ip'];
    $clientMac = $clientInfo['mac'];
    PamnetStorePortalClient($account_number, $clientIp, $clientMac);
    PamnetLogPortalClient('check_active', $account_number, $clientInfo);
    header('Content-Type: application/json; charset=utf-8');
    if ($account_number === '') {
        echo json_encode(["status" => "error", "active" => false, "message" => "Missing account number"]);
        exit();
    }

    // Heal missed STK callbacks using C2B Paybill confirmation
    if (class_exists('PamnetHotspotPay')) {
        PamnetHotspotPay::activateFromC2B($account_number);
    }

    if ($clientIp === '' && $clientMac === '') {
        list($clientIp, $clientMac) = PamnetLoadPortalClient($account_number);
    }

    $active = PamnetUsernameHasActivePlan($account_number);
    if ($active) {
        $radiusAuth = PamnetAccountUsesRadius($account_number);
        $ready = true;
        $loggedIn = false;
        $authMsg = $radiusAuth ? 'Active RADIUS package found' : 'Active package found';
        if (!$radiusAuth) {
            $ready = PamnetEnsureHotspotOnRouter($account_number);
            // Legacy/manual routers may still use API-assisted login.
            if ($clientIp !== '' || $clientMac !== '') {
                $al = PamnetHotspotAutologin($account_number, $clientIp, $clientMac);
                $loggedIn = !empty($al['ok']) || !empty($al['logged_in']);
                if (!$loggedIn && !empty($al['message'])) {
                    $authMsg = (string) $al['message'];
                    if (function_exists('_log')) {
                        _log('check_active auth fail ' . $account_number . ': ' . $authMsg, 'System', 1);
                    }
                }
            }
        }
        $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
        $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
        echo json_encode([
            "status" => "success",
            "active" => true,
            "ready" => (bool) $ready,
            "logged_in" => $loggedIn,
            "username" => $account_number,
            "tyhK" => $pass,
            "message" => $loggedIn ? "Connected to Wi-Fi" : $authMsg,
            "fallback_pap" => !$loggedIn,
        ]);
        exit();
    }
    echo json_encode([
        "status" => "success",
        "active" => false,
        "ready" => false,
        "logged_in" => false,
        "username" => $account_number,
        "message" => "No active package",
    ]);
    exit();
}

/**
 * Ask Safaricom STK Query for a pending CheckoutRequestID and update tbl_payment_gateway.
 * Fast path when STK callback is delayed/missed — keeps portal wait under a few seconds.
 * Rate-limited (max once / 3s per checkout) so verify polls stay snappy.
 * @return object|null refreshed payment gateway row
 */
function PamnetStkQueryAndUpdate($paymentRow)
{
    if (!$paymentRow) {
        return $paymentRow;
    }
    if ((string) ($paymentRow['status'] ?? '') !== '1') {
        return $paymentRow;
    }
    $checkoutId = trim((string) ($paymentRow['pg_request'] ?? ''));
    if ($checkoutId === '' || stripos($checkoutId, 'ws_CO_') !== 0) {
        return $paymentRow;
    }

    // Rate limit: do not hammer Safaricom on every 200ms portal poll
    $cacheDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $rlFile = $cacheDir . DIRECTORY_SEPARATOR . 'stkq_' . preg_replace('/[^A-Za-z0-9_-]/', '', $checkoutId) . '.ts';
    if (is_file($rlFile) && (time() - (int) @filemtime($rlFile)) < 3) {
        return $paymentRow;
    }
    @file_put_contents($rlFile, (string) time());

    $gatewayName = strtolower(trim((string) ($paymentRow['gateway'] ?? '')));
    $useTill = (strpos($gatewayName, 'till') !== false);

    if ($useTill) {
        $consumerKey = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_till_consumer_key')->find_one();
        $consumerSecret = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_till_consumer_secret')->find_one();
        $businessCode = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_till_business_code')->find_one();
        $passKey = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_till_pass_key')->find_one();
        $env = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_till_env')->find_one();
    } else {
        $consumerKey = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_key')->find_one();
        $consumerSecret = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_secret')->find_one();
        $businessCode = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_business_code')->find_one();
        $passKey = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_pass_key')->find_one();
        $env = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_env')->find_one();
        // paybilltillsbankmpesa often reuses classic mpesa keys; fall back already above
    }
    $consumerKey = $consumerKey ? $consumerKey->value : '';
    $consumerSecret = $consumerSecret ? $consumerSecret->value : '';
    $businessCode = $businessCode ? $businessCode->value : '';
    $passKey = $passKey ? $passKey->value : '';
    $env = $env ? $env->value : 'live';
    if ($consumerKey === '' || $consumerSecret === '' || $businessCode === '' || $passKey === '') {
        return $paymentRow;
    }

    $tokenUrl = ($env === 'sandbox')
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $queryUrl = ($env === 'sandbox')
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $tokenResp = curl_exec($ch);
    curl_close($ch);
    $accessToken = json_decode((string) $tokenResp, true)['access_token'] ?? '';
    if ($accessToken === '') {
        return $paymentRow;
    }

    $timestamp = date('YmdHis');
    $password = base64_encode($businessCode . $passKey . $timestamp);
    $payload = json_encode([
        'BusinessShortCode' => $businessCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkoutId,
    ]);

    $ch2 = curl_init($queryUrl);
    curl_setopt_array($ch2, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $queryRaw = curl_exec($ch2);
    curl_close($ch2);
    $q = json_decode((string) $queryRaw, true);
    if (!is_array($q)) {
        return $paymentRow;
    }

    $resultCode = (string) ($q['ResultCode'] ?? '');
    $resultDesc = trim((string) ($q['ResultDesc'] ?? $q['ResponseDescription'] ?? ''));
    $now = date('Y-m-d H:i:s');

    // Still processing on Safaricom side
    if ($resultCode === '' || $resultCode === '4999' || stripos($resultDesc, 'being processed') !== false) {
        return $paymentRow;
    }

    if ($resultCode === '0') {
        // Paid — mark immediately so portal leaves wait modal (callback may still be delayed).
        $mpesaCode = '';
        try {
            $uname = (string) ($paymentRow['username'] ?? '');
            $amountInt = (int) round((float) ($paymentRow['price'] ?? 0));
            $cust = $uname !== '' ? ORM::for_table('tbl_customers')->where('username', $uname)->find_one() : null;
            $phone = $cust ? (string) ($cust['phonenumber'] ?? '') : '';
            if (class_exists('PamnetHotspotPay') && $phone !== '') {
                $txs = ORM::for_table('tbl_mpesa_transactions')
                    ->where_raw('CreatedAt >= DATE_SUB(NOW(), INTERVAL 2 HOUR)')
                    ->order_by_desc('id')
                    ->limit(25)
                    ->find_many();
                foreach ($txs as $tx) {
                    if (!PamnetHotspotPay::phonesMatch($tx['MSISDN'] ?? '', $phone)) {
                        continue;
                    }
                    if ($amountInt > 0 && abs((int) round((float) ($tx['TransAmount'] ?? 0)) - $amountInt) > 1) {
                        continue;
                    }
                    $mpesaCode = trim((string) ($tx['TransID'] ?? ''));
                    if ($mpesaCode !== '') {
                        break;
                    }
                }
            }
        } catch (Throwable $eCode) {
        }
        if ($mpesaCode === '') {
            $mpesaCode = 'STKQ-' . substr(sha1($checkoutId . $now), 0, 10);
        }
        $paymentRow->status = 2;
        $paymentRow->paid_date = $now;
        $paymentRow->gateway_trx_id = $mpesaCode;
        $paymentRow->pg_paid_response = 'STK Query confirmed — activating now';
        $paymentRow->save();
        return ORM::for_table('tbl_payment_gateway')->find_one($paymentRow->id());
    }

    // Map common STK failure codes so portal exits Processing…
    if ($resultCode === '1032') {
        $paymentRow->status = 4;
        $paymentRow->paid_date = $now;
        $paymentRow->pg_paid_response = 'Request canceled by user';
        $paymentRow->save();
    } elseif ($resultCode === '1037') {
        $paymentRow->status = 4;
        $paymentRow->paid_date = $now;
        $paymentRow->pg_paid_response = 'User failed to enter pin';
        $paymentRow->save();
    } elseif ($resultCode === '2001') {
        $paymentRow->status = 4;
        $paymentRow->paid_date = $now;
        $paymentRow->pg_paid_response = 'Wrong Mpesa pin';
        $paymentRow->save();
    } elseif ($resultCode === '1') {
        $paymentRow->status = 4;
        $paymentRow->paid_date = $now;
        $paymentRow->pg_paid_response = 'Not enough balance';
        $paymentRow->save();
    } else {
        $paymentRow->status = 4;
        $paymentRow->paid_date = $now;
        $paymentRow->pg_paid_response = $resultDesc !== '' ? $resultDesc : ('STK failed code ' . $resultCode);
        $paymentRow->save();
    }

    return ORM::for_table('tbl_payment_gateway')->find_one($paymentRow->id());
}

function VerifyHotspot()
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $account_number = isset($input['account_number']) ? trim((string) $input['account_number']) : '';
    $clientInfo = PamnetParsePortalClient($input);
    $clientIp = $clientInfo['ip'];
    $clientMac = $clientInfo['mac'];
    // Store identity for later autologin — skip verbose logging (was slowing every poll).
    PamnetStorePortalClient($account_number, $clientIp, $clientMac);
    if ($account_number === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Missing required parameters when verifying account number"]);
        exit();
    }

    if ($clientIp === '' && $clientMac === '') {
        list($clientIp, $clientMac) = PamnetLoadPortalClient($account_number);
    }

    $user = ORM::for_table('tbl_payment_gateway')
        ->where('username', $account_number)
        ->order_by_desc('id')
        ->find_one();

    $deferMeta = null;

    $respondPaid = function ($pass, $mpesacode) use ($account_number, $clientIp, $clientMac, &$deferMeta) {
        $radiusAuth = false;
        try {
            if (class_exists('PamnetHotspotPay')) {
                $radiusAuth = PamnetHotspotPay::usernameUsesRadius($account_number);
                if (!$radiusAuth && is_array($deferMeta)) {
                    $radiusAuth = PamnetHotspotPay::isRadiusRouterName($deferMeta['routers'] ?? '');
                }
                // RADIUS must be provisioned before the browser submits /login;
                // otherwise the first Access-Request can arrive before radcheck exists.
                if ($radiusAuth && !PamnetUsernameHasActivePlan($account_number)) {
                    if (!is_array($deferMeta) || empty($deferMeta['needs_recharge'])) {
                        $pg = ORM::for_table('tbl_payment_gateway')
                            ->where('username', $account_number)
                            ->where('status', 2)
                            ->order_by_desc('id')
                            ->find_one();
                        $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
                        if ($pg && $cust && (int) ($pg['plan_id'] ?? 0) > 0 && trim((string) ($pg['gateway_trx_id'] ?? '')) !== '') {
                            $deferMeta = [
                                'ok' => true,
                                'needs_recharge' => true,
                                'trans_id' => (string) $pg['gateway_trx_id'],
                                'plan_id' => (int) $pg['plan_id'],
                                'routers' => (string) ($pg['routers'] ?? ''),
                                'customer_id' => (int) $cust['id'],
                                'username' => $account_number,
                            ];
                        }
                    }
                    if (is_array($deferMeta) && !empty($deferMeta['needs_recharge'])) {
                        PamnetHotspotPay::completeDeferredRecharge($deferMeta);
                        $deferMeta['needs_recharge'] = false;
                    }
                }
            }
        } catch (Throwable $eRadius) {
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Connection: close');
        $payload = json_encode([
            "Resultcode" => "3",
            "username" => $account_number,
            "tyhK" => $pass,
            "ready" => true,
            "logged_in" => false,
            "fallback_pap" => true,
            "connect_now" => true,
            "Message" => "Payment confirmed. Connecting you to Wi-Fi…",
            "Status" => "success",
            "gateway_trx_id" => $mpesacode,
        ]);
        header('Content-Length: ' . strlen($payload));
        echo $payload;
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
            }
            @flush();
        }
        if ($radiusAuth) {
            // Browser will now POST credentials to MikroTik /login. MikroTik then
            // sends the Access-Request to FreeRADIUS; no RouterOS API login here.
            exit();
        }
        ignore_user_abort(true);
        try {
            // Connect this phone NOW. Do not run full Package::rechargeUser here —
            // that blocked PHP-FPM workers for minutes and delayed every verify poll.
            if (class_exists('PamnetHotspotPay')) {
                if (is_array($deferMeta) && !empty($deferMeta['needs_recharge'])) {
                    // Recharge row + router user + autologin
                    PamnetHotspotPay::completeDeferredRecharge($deferMeta);
                } else {
                    PamnetHotspotPay::connectDeviceNow($account_number);
                }
            } else {
                PamnetEnsureHotspotOnRouter($account_number);
                if ($clientIp !== '' || $clientMac !== '') {
                    PamnetHotspotAutologin($account_number, $clientIp, $clientMac);
                }
            }
        } catch (Throwable $eBg) {
        }
        exit();
    };

    // Already paid/active — answer instantly (no C2B, no Safaricom, no MikroTik before JSON).
    if (PamnetUsernameHasActivePlan($account_number) || ($user && (int) $user->status === 2)) {
        $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
        $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
        $respondPaid($pass, $user ? (string) $user->gateway_trx_id : '');
    }

    // Fast DB-only C2B mark (no Package::rechargeUser / MikroTik in request path).
    if (class_exists('PamnetHotspotPay')) {
        $quick = PamnetHotspotPay::quickMarkPaidFromC2B($account_number, 6);
        if (!empty($quick['ok'])) {
            $deferMeta = $quick;
            $user = ORM::for_table('tbl_payment_gateway')
                ->where('username', $account_number)
                ->order_by_desc('id')
                ->find_one();
            // Payment already in DB — leave wait modal even if status row lagged.
            if (!$user || (int) $user->status !== 2) {
                $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
                $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
                $respondPaid($pass, (string) ($quick['trans_id'] ?? ''));
            }
        }
    }

    if (PamnetUsernameHasActivePlan($account_number) || ($user && (int) $user->status === 2)) {
        $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
        $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
        $respondPaid($pass, $user ? (string) $user->gateway_trx_id : '');
    }

    // Invoice already recorded for this Hotspot code — continue connect (no re-login).
    try {
        $recentInv = ORM::for_table('tbl_transactions')
            ->where('username', $account_number)
            ->where_raw("CONCAT(recharged_on, ' ', recharged_time) >= DATE_SUB(NOW(), INTERVAL 2 HOUR)")
            ->order_by_desc('id')
            ->find_one();
        if ($recentInv) {
            if (class_exists('PamnetHotspotPay')) {
                PamnetHotspotPay::ensurePaidGateway(
                    $account_number,
                    (string) $recentInv['invoice'],
                    (float) $recentInv['price']
                );
            }
            $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
            $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
            $pgPaid = ORM::for_table('tbl_payment_gateway')
                ->where('username', $account_number)
                ->order_by_desc('id')
                ->find_one();
            $planId = $pgPaid ? (int) $pgPaid['plan_id'] : 0;
            $routers = $pgPaid ? (string) $pgPaid['routers'] : (string) ($recentInv['routers'] ?: 'PMNINTERNET');
            $needs = !PamnetUsernameHasActivePlan($account_number);
            $deferMeta = [
                'ok' => true,
                'needs_recharge' => $needs && $planId > 0 && $cust,
                'trans_id' => (string) $recentInv['invoice'],
                'plan_id' => $planId,
                'routers' => $routers !== '' ? $routers : 'PMNINTERNET',
                'customer_id' => $cust ? (int) $cust['id'] : 0,
                'username' => $account_number,
            ];
            $respondPaid($pass, (string) $recentInv['invoice']);
        }
    } catch (Throwable $eInv) {
    }

    // Callback delayed? Ask Safaricom STK Query (rate-limited, ~3s) then activate immediately.
    if ($user && (int) $user->status === 1) {
        $createdTs = strtotime((string) ($user['created_date'] ?? ''));
        if ($createdTs === false || (time() - $createdTs) >= 2) {
            $user = PamnetStkQueryAndUpdate($user);
            if ($user && (int) $user->status === 2) {
                $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();
                $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
                $deferMeta = [
                    'ok' => true,
                    'needs_recharge' => !PamnetUsernameHasActivePlan($account_number),
                    'trans_id' => (string) ($user->gateway_trx_id ?? ''),
                    'plan_id' => (int) ($user->plan_id ?? 0),
                    'routers' => (string) (($user->routers ?? '') ?: 'PMNINTERNET'),
                    'customer_id' => $cust ? (int) $cust['id'] : 0,
                    'username' => $account_number,
                ];
                $respondPaid($pass, (string) ($user->gateway_trx_id ?? ''));
            }
        }
    }

    // Still pending — never block verify on slow Safaricom timeouts (query is rate-limited above).
    if ($user) {
        $status = $user->status;
        $res = $user->pg_paid_response;
        // Failures wrongly left as status=1 (legacy Till STK) — surface them.
        $resLower = strtolower(trim((string) $res));
        $softFail = (
            $resLower === 'user failed to enter pin'
            || $resLower === 'wrong mpesa pin'
            || $resLower === 'not enough balance'
            || strpos($resLower, 'timeout') !== false
            || strpos($resLower, 'cancel') !== false
        );
        if ($status == 4 || ($status == 1 && $softFail)) {
            if ((int) $status !== 4) {
                try {
                    $user->status = 4;
                    $user->save();
                } catch (Throwable $eSt) {
                }
            }
            $failMsg = 'Payment was cancelled or timed out. Tap Buy Now again to receive a new M-Pesa PIN prompt.';
            if ($resLower === 'user failed to enter pin' || strpos($resLower, 'timeout') !== false || strpos($resLower, 'cannot be reached') !== false) {
                $failMsg = 'M-Pesa could not reach your phone for the PIN prompt (timeout). Check the number is correct, unlock the phone, then tap Buy Now again.';
            } elseif ($resLower === 'wrong mpesa pin') {
                $failMsg = 'Wrong M-Pesa PIN. Tap Buy Now again and enter the correct PIN.';
            } elseif ($resLower === 'not enough balance') {
                $failMsg = 'Insufficient M-Pesa balance. Top up, then tap Buy Now again.';
            } elseif (stripos((string) $res, 'cancel') !== false) {
                $failMsg = 'You cancelled the M-Pesa prompt. Tap Buy Now again to retry.';
            }
            $data = [
                "Resultcode" => "2",
                "Message" => $failMsg,
                "Status" => "danger",
                "can_retry" => true,
                "Redirect" => "Transaction Cancelled"
            ];
        } elseif ($res == "Wrong Mpesa pin" || $res == "Not enough balance" || $res == "User failed to enter pin") {
            $failMsg = ($res == "Wrong Mpesa pin")
                ? 'Wrong M-Pesa PIN. Tap Buy Now again and enter the correct PIN.'
                : (($res == "Not enough balance")
                    ? 'Insufficient M-Pesa balance. Top up, then tap Buy Now again.'
                    : 'M-Pesa PIN prompt timed out or could not reach your phone. Tap Buy Now again.');
            $data = [
                "Resultcode" => "2",
                "Message" => $failMsg,
                "Status" => "danger",
                "can_retry" => true
            ];
        } else {
            $data = [
                "Resultcode" => "1",
                "Message" => "A payment pop up has been sent, Please enter pin to continue (Please do not leave or reload the page until redirected)",
                "Status" => "primary",
                "waiting" => true,
                "account" => $account_number,
            ];
        }
    } else {
        $data = ["status" => "error", "message" => "Account " . $account_number . " not found"];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

function CreateHostspotUser()
{
    $result = ORM::for_table('tbl_appconfig')->find_many();
    foreach ($result as $value) {
        $config[$value['setting']] = $value['value'];
    }
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit();
    }
    if ($config['maintenance_mode']) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Scheduled maintenance is currently in progress. Please check back soon. We apologize for any inconvenience']);
        exit();
    }
    try {
        // Parse JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Extract data from JSON input
        $phone = isset($input['phone_number']) ? $input['phone_number'] : '';
        $planId = isset($input['plan_id']) ? $input['plan_id'] : '';
        $routerId = isset($input['router_id']) ? $input['router_id'] : '';
        $user_account = isset($input['account_number']) ? $input['account_number'] : '';
        $clientInfo = PamnetParsePortalClient($input);
        $mac_address = $clientInfo['mac'];
        PamnetStorePortalClient((string) $user_account, $clientInfo['ip'], $mac_address);
        PamnetLogPortalClient('grant', (string) $user_account, $clientInfo);

        $missingParams = [];
        if (empty($phone)) $missingParams[] = 'phone_number';
        if (empty($planId)) $missingParams[] = 'plan_id';
        if (empty($routerId)) $missingParams[] = 'router_id';
        if (empty($user_account)) $missingParams[] = 'account_number';
        
        if (!empty($missingParams)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Missing required parameters: " . implode(', ', $missingParams)]);
            exit();
        }

        if (PamnetIsBlockedMac($mac_address)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'This device has been blocked from accessing this service, please contact service provider']);
            exit();
        }

        $phone = (substr($phone, 0, 1) == '+') ? str_replace('+', '', $phone) : $phone;
        $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^0/', '254', $phone) : $phone;
        $phone = (substr($phone, 0, 1) == '7') ? preg_replace('/^7/', '2547', $phone) : $phone; //cater for phone number prefix 2547XXXX
        $phone = (substr($phone, 0, 1) == '1') ? preg_replace('/^1/', '2541', $phone) : $phone; //cater for phone number prefix 2541XXXX
        $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^01/', '2541', $phone) : $phone;
        $phone = (substr($phone, 0, 1) == '0') ? preg_replace('/^07/', '2547', $phone) : $phone;
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if (!preg_match('/^254(7|1)\d{8}$/', $phone)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Enter a valid Safaricom M-Pesa number (07… or 01…)."]);
            exit();
        }

        $PlanExist = ORM::for_table('tbl_plans')->where('id', $planId)->where('enabled', 1)->count() > 0;
        $RouterExist = ORM::for_table('tbl_routers')->where('id', $routerId)->count() > 0;

        if (!$PlanExist || !$RouterExist) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Unable to process your request, please refresh the page"]);
            exit();
        }

        // MULTI-USER PER PHONE: resolve by username/code only.
        // Active codes never get another package — a new unique code is minted instead.
        $resolved = PamnetResolvePurchaseUsername($user_account, $phone, $routerId);
        if (empty($resolved['ok'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => $resolved['error'] ?? 'Unable to prepare Hotspot account',
            ]);
            exit();
        }
        $user_account = (string) $resolved['username'];
        $stkMeta = [
            'code_replaced' => !empty($resolved['replaced']),
            'previous_code' => (string) ($resolved['previous'] ?? ''),
        ];
        PamnetStorePortalClient($user_account, $clientInfo['ip'], $mac_address);
        InitiateStkpush($phone, $planId, $routerId, $user_account, $mac_address, $stkMeta);
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit();
    }
}


function GetHotspotPlans()
{

    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit();
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $router_id = isset($input['router_id']) ? $input['router_id'] : '';
    if (empty($router_id)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Missing required parameters router_id : " . $router_id]);
        exit();
    }


    //GET ROUTER NAME
    $routerName = ORM::for_table('tbl_routers')
        ->where('id', $router_id)
        ->find_one();
    $routerName = $routerName->name;
    $result = ORM::for_table('tbl_appconfig')->find_many();
    foreach ($result as $value) {
        $config[$value['setting']] = $value['value'];
    }
    if ($config['maintenance_mode']) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Scheduled maintenance is currently in progress. Please check back soon. We apologize for any inconvenience']);
        exit();
    }
    $routers = ORM::for_table('tbl_routers')->find_array();
    $plans_hotspot = ORM::for_table('tbl_plans')->where('type', 'Hotspot')->where('enabled', 1)->find_array();
    $bandwidth_map = ORM::for_table('tbl_bandwidth')->find_array();

    $color_scheme = ORM::for_table('tbl_appconfig')->where('setting', 'color_scheme')->find_one();
    $color_scheme = $color_scheme ? $color_scheme->value : 'blue';


    $shape = ORM::for_table('tbl_appconfig')->where('setting', 'shape_selector')->find_one();
    $shape = $shape ? $shape->value : 'square';
    if ($shape == 'square') {
        $shape_card_class_name = 'w-64 h-64 rounded-lg';
    } elseif ($shape == 'rectangle') {
        $shape_card_class_name = 'w-80 h-48 rounded-lg';
    } elseif ($shape == 'circle') {
        $shape_card_class_name = 'w-64 h-64 rounded-full';
    } elseif ($shape == 'oval') {
        $shape_card_class_name = 'w-80 h-48 rounded-full';
    } else {
        $shape_card_class_name = 'rounded-lg';
    }

    $currency_config = ORM::for_table('tbl_appconfig')->where('setting', 'currency_code')->find_one();
    $currency = $currency_config ? $currency_config->value : 'Ksh';
    $data = [];
    foreach ($routers as $router) {
        if ($router['name'] === $routerName) {
            $routerData = [
                'name' => $router['name'],
                'router_id' => $router['id'],
                'description' => $router['description'],
                'plans_hotspot' => [],
            ];
            foreach ($plans_hotspot as $plan) {
                if ($router['name'] == $plan['routers']) {
                    $plan_id = $plan['id'];
                    $bandwidth_data = isset($bandwidth_map[$plan_id]) ? $bandwidth_map[$plan_id] : [];
                    $paymentlink = "";
                    $routerData['plans_hotspot'][] = [
                        'plantype' => $plan['type'],
                        'planname' => $plan['name_plan'],
                        'typebp' => $plan['typebp'],
                        'currency' => $currency,
                        'price' => $plan['price'],
                        'validity' => $plan['validity'],
                        'shared_users' => $plan['shared_users'],
                        'device' => $plan['shared_users'],
                        'datalimit' => $plan['data_limit'],
                        'timelimit' => $plan['validity_unit'] ?? null,
                        'downlimit' => $bandwidth_data['rate_down'] ?? null,
                        'uplimit' => $bandwidth_data['rate_up'] ?? null,
                        'paymentlink' => $paymentlink,
                        'planId' => $plan['id'],
                        'routerName' => $router['name'],
                        'routerId' => $router['id'],
                        'shape' => $shape,
                        'shape_card_class_name' => $shape_card_class_name,
                        'color_scheme' => $color_scheme,
                    ];
                }
            }
            $data[] = $routerData;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function InitiateStkpush($phone, $planId, $routerId, $user_Account, $mac_address, $meta = [])
{
    try {
        $file_path = 'system/removeuser.php';
        //  include_once $file_path;

        $gateway = ORM::for_table('tbl_appconfig')
            ->where('setting', 'payment_gateway')
            ->find_one();
        $gateway = ($gateway) ? $gateway->value : null;

        if ($gateway == "MpesatillStk") {
            $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatetillstk';
        } elseif ($gateway == "BankStkPush") {
            $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatebankstk';
        } elseif ($gateway == "MpesaPaybill") {
            $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatePaybillStk';
        } elseif ($gateway == "mpesa") {
            $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatempesa';
        } elseif ($gateway == "paybilltillsbankmpesa") {
            $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatepaybilltillsbankmpesa';
        } elseif ($gateway == "kopokopo") {
            $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatekopokopo';
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["status" => "error", "message" => "Payment gateway not configured"]);
            exit();
        }
        // Prefer live APP_URL when it is a real public host
        if (defined('APP_URL')) {
            $base = rtrim((string) APP_URL, '/');
            $base = preg_replace('#^(https?://[^/]+)\.(/|$)#', '$1$2', $base);
            if ($base !== '' && stripos($base, 'localhost') === false) {
                $path = parse_url($url, PHP_URL_QUERY);
                if (is_string($path) && $path !== '') {
                    $url = $base . '/?' . $path;
                }
            }
        }

        $Planname = ORM::for_table('tbl_plans')
            ->where('id', $planId)
            ->order_by_desc('id')
            ->find_one();
        $Findrouter = ORM::for_table('tbl_routers')
            ->where('id', $routerId)
            ->order_by_desc('id')
            ->find_one();

        $rname = $Findrouter->name;
        $price = $Planname->price;
        $Planname = $Planname->name_plan;

        $Checkorders = ORM::for_table('tbl_payment_gateway')
            ->where('username', $user_Account)
            ->where('status', 1)
            ->order_by_desc('id')
            ->find_many();

        if ($Checkorders) {
            foreach ($Checkorders as $Dorder) {
                $Dorder->delete();
            }
        }

        //check first if routers_id column is available in the table if not add it
        $table = ORM::for_table('tbl_payment_gateway')->raw_query('SHOW COLUMNS FROM tbl_payment_gateway LIKE "routers_id"')->find_one();
        if (!$table) {
            $sql = "ALTER TABLE tbl_payment_gateway ADD routers_id VARCHAR(255) AFTER plan_name";
            ORM::for_table('tbl_payment_gateway')->raw_execute($sql);
        }

        //check first if mac_address column is available in the table if not add it
        $table = ORM::for_table('tbl_payment_gateway')->raw_query('SHOW COLUMNS FROM tbl_payment_gateway LIKE "mac_address"')->find_one();
        if (!$table) {
            $sql = "ALTER TABLE tbl_payment_gateway ADD mac_address VARCHAR(255) AFTER gateway";
            ORM::for_table('tbl_payment_gateway')->raw_execute($sql);
        }

        $d = ORM::for_table('tbl_payment_gateway')->create();
        $d->username = $user_Account;
        $d->gateway = $gateway;
        $d->mac_address = $mac_address;
        $d->plan_id = $planId;
        $d->plan_name = $Planname;
        $d->routers_id = $routerId;
        $d->routers = $rname;
        $d->price = $price;
        $d->payment_method = $gateway;
        $d->payment_channel = $gateway;
        $d->created_date = date('Y-m-d H:i:s');
        $d->paid_date = date('Y-m-d H:i:s');
        $d->expired_date = date('Y-m-d H:i:s');
        $d->pg_url_payment = $url;
        $d->status = 1;
        $d->save();
        //echo json_encode(["status" => "success", "phone" => $phone, "message" => "Registration complete,Please enter Mpesa Pin to activate the package"]);
        SendSTKcred($phone, $user_Account, $url, $meta);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit();
    }
}

function SendSTKcred($phone, $user_Account, $url, $meta = [])
{
    // Never call localhost / broken APP_URL hosts for STK
    $url = trim((string) $url);
    if ($url === '' || stripos($url, 'localhost') !== false || !preg_match('#^https?://#i', $url)) {
        $url = 'https://net.pamnetsolutions.co.ke/?_route=plugin/initiatempesa';
    }
    // Normalize trailing-dot hostnames (FQDN form breaks some curl/DNS paths)
    $url = preg_replace('#^(https?://[^/]+)\.(/|\?|$)#', '$1$2', $url);

    $fields = [
        'username' => $user_Account,
        'phone' => $phone,
        'channel' => 'Yes',
    ];
    $postvars = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postvars),
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        $err = curl_error($ch);
        curl_close($ch);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Could not start M-Pesa STK: " . $err]);
        exit();
    }
    curl_close($ch);

    $decoded = json_decode($result, true);
    if (!is_array($decoded)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid M-Pesa gateway response. Please try again."]);
        exit();
    }

    $status = strtolower(trim((string) ($decoded['status'] ?? '')));
    $msg = trim((string) ($decoded['message'] ?? $decoded['Message'] ?? ''));
    $codeReplaced = !empty($meta['code_replaced']);
    $previousCode = trim((string) ($meta['previous_code'] ?? ''));
    if ($status !== 'success' && $status !== 'ok') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "status" => "error",
            "message" => $msg !== '' ? $msg : "M-Pesa STK Push failed. Please try again.",
            "username" => $user_Account,
            "account_number" => $user_Account,
            "code_replaced" => $codeReplaced,
            "previous_code" => $previousCode,
        ]);
        exit();
    }

    $decoded['status'] = 'success';
    $decoded['username'] = $user_Account;
    $decoded['account_number'] = $user_Account;
    $decoded['code_replaced'] = $codeReplaced;
    $decoded['previous_code'] = $previousCode;
    if ($codeReplaced) {
        $decoded['message'] = 'Your current code'
            . ($previousCode !== '' ? ' (' . $previousCode . ')' : '')
            . ' is still active. A new Hotspot code was created for this purchase: '
            . $user_Account;
    } elseif ($msg !== '') {
        $decoded['message'] = $msg;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($decoded);
    exit();
}

function RedeemVoucher()
{
    error_reporting(E_ERROR | E_PARSE);
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(["status" => "error", "message" => "Invalid request data format"]);
        exit();
    }

    $voucher_code = preg_replace('/\s+/', '', (string) ($input['voucher_code'] ?? ''));
    $old_account_number = trim((string) ($input['account_number'] ?? ''));
    $routerId = trim((string) ($input['router_id'] ?? ''));

    if ($voucher_code === '' || $routerId === '') {
        echo json_encode(["status" => "error", "message" => "Missing required parameters"]);
        exit();
    }
    if ($old_account_number === '') {
        $old_account_number = (string) random_int(10000, 99999);
    }
    if (strlen($voucher_code) < 2) {
        echo json_encode(["status" => "error", "message" => "Voucher code must be at least 2 characters long"]);
        exit();
    }
    if (!preg_match('/^[a-zA-Z0-9]+$/', $voucher_code)) {
        echo json_encode(["status" => "error", "message" => "Invalid voucher code. Only letters and numbers are allowed"]);
        exit();
    }

    $findVoucher = function ($code, $status) {
        $row = ORM::for_table('tbl_voucher')
            ->where_raw('BINARY code = ?', [$code])
            ->where('status', $status)
            ->order_by_desc('id')
            ->find_one();
        if (!$row) {
            $row = ORM::for_table('tbl_voucher')
                ->where('code', $code)
                ->where('status', $status)
                ->order_by_desc('id')
                ->find_one();
        }
        return $row;
    };

    $voucher_code_data = $findVoucher($voucher_code, 0);

    // Already used: reconnect only if package is still active
    if (!$voucher_code_data) {
        $used = $findVoucher($voucher_code, 1);
        if (!$used) {
            echo json_encode(["status" => "error", "message" => "Invalid voucher code"]);
            exit();
        }
        $loginUser = trim((string) ($used['user'] ?: $used['code']));
        if ($loginUser === '') {
            $loginUser = $voucher_code;
        }
        if (!PamnetUsernameHasActivePlan($loginUser)) {
            echo json_encode([
                "status" => "error",
                "message" => "This voucher was already used and has expired. Please buy a new package.",
            ]);
            exit();
        }
        $ready = PamnetEnsureHotspotOnRouter($loginUser);
        if (!$ready) {
            usleep(400000);
            $ready = PamnetEnsureHotspotOnRouter($loginUser);
        }
        $cust = ORM::for_table('tbl_customers')->where('username', $loginUser)->find_one();
        $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';
        echo json_encode([
            "status" => "used",
            "message" => "Voucher already used. Connecting you to Wi-Fi…",
            "username" => $loginUser,
            "voucher" => $used['code'],
            "tyhK" => $pass,
            "ready" => (bool) $ready,
            "active" => true,
        ]);
        exit();
    }

    // Router check (null-safe). Allow when voucher router blank or only one router exists.
    $router = ORM::for_table('tbl_routers')->where('id', $routerId)->find_one();
    if (!$router) {
        $router = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('id')->find_one();
    }
    $voucherRouter = trim((string) ($voucher_code_data['routers'] ?? ''));
    if ($router && $voucherRouter !== '' && strcasecmp($voucherRouter, (string) $router['name']) !== 0) {
        echo json_encode(["status" => "error", "message" => "Voucher is not valid for this router"]);
        exit();
    }
    $routerName = $voucherRouter !== '' ? $voucherRouter : ($router ? (string) $router['name'] : 'PMNINTERNET');
    $planId = (int) $voucher_code_data['id_plan'];
    if ($planId <= 0) {
        echo json_encode(["status" => "error", "message" => "Voucher plan is invalid"]);
        exit();
    }

    // Username = voucher code (Hotspot account) unless that code is already active
    $user_account = $voucher_code;
    $phone = '254' . substr(preg_replace('/[^0-9]/', '', md5($user_account)), 0, 9);
    if (strlen($phone) < 12) {
        $phone = '2547' . substr((string) abs(crc32($user_account)), 0, 8);
    }
    // Unique invoice so duplicate voucher codes can still recharge
    $invoiceId = 'VCH-' . (int) $voucher_code_data['id'] . '-' . $voucher_code;

    try {
        $resolved = PamnetResolvePurchaseUsername($user_account, $phone, $routerId);
        if (empty($resolved['ok'])) {
            echo json_encode([
                'status' => 'error',
                'message' => $resolved['error'] ?? 'Could not create a Hotspot account for this voucher.',
            ]);
            exit();
        }
        $user_account = (string) $resolved['username'];
        $customerId = (int) ($resolved['customer']->id ?? 0);
        $codeReplaced = !empty($resolved['replaced']);
        $previousCode = (string) ($resolved['previous'] ?? '');
        if ($customerId <= 0) {
            echo json_encode(["status" => "error", "message" => "User creation failed"]);
            exit();
        }

        $rechargeStatus = false;
        try {
            $rechargeStatus = Package::rechargeUser(
                $customerId,
                $routerName,
                $planId,
                'Voucher',
                $voucher_code,
                '',
                $invoiceId
            );
        } catch (Throwable $e) {
            if (function_exists('_log')) {
                _log('Voucher recharge ' . $voucher_code . ': ' . $e->getMessage(), 'System', 1);
            }
        }

        // Package::rechargeUser may return null/void even when recharge succeeded
        $ok = (bool) $rechargeStatus || PamnetUsernameHasActivePlan($user_account);
        if (!$ok) {
            // One retry with a fresh invoice id
            try {
                $rechargeStatus = Package::rechargeUser(
                    $customerId,
                    $routerName,
                    $planId,
                    'Voucher',
                    $voucher_code,
                    '',
                    $invoiceId . '-' . time()
                );
            } catch (Throwable $e) {
            }
            $ok = (bool) $rechargeStatus || PamnetUsernameHasActivePlan($user_account);
        }

        if (!$ok) {
            echo json_encode(["status" => "error", "message" => "Failed to activate voucher package. Please try again."]);
            exit();
        }

        $voucher_code_data->status = 1;
        $voucher_code_data->used_date = date('Y-m-d H:i:s');
        $voucher_code_data->user = $user_account;
        $voucher_code_data->save();

        $ready = PamnetEnsureHotspotOnRouter($user_account);
        if (!$ready) {
            usleep(500000);
            $ready = PamnetEnsureHotspotOnRouter($user_account);
        }

        $cust = ORM::for_table('tbl_customers')->where('username', $user_account)->find_one();
        $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';

        $msg = 'Voucher redeemed successfully. Connecting you to Wi-Fi…';
        if ($codeReplaced) {
            $msg = 'Your previous code'
                . ($previousCode !== '' ? ' (' . $previousCode . ')' : '')
                . ' is still active. New Hotspot code ' . $user_account . ' was activated. Connecting you to Wi-Fi…';
        }

        echo json_encode([
            "status" => "success",
            "message" => $msg,
            "username" => $user_account,
            "voucher" => $voucher_code,
            "tyhK" => $pass,
            "ready" => (bool) $ready,
            "active" => true,
            "code_replaced" => $codeReplaced,
            "previous_code" => $previousCode,
        ]);
        exit();
    } catch (Throwable $e) {
        echo json_encode(["status" => "error", "message" => "System error: " . $e->getMessage()]);
        exit();
    }
}


function MpesaCodeLogin()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonMpesaCodeResponse("error", "Invalid request method");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        sendJsonMpesaCodeResponse("error", "Invalid request data");
    }

    $raw = trim((string) ($input['mpesa_code'] ?? ''));
    if ($raw === '') {
        sendJsonMpesaCodeResponse("error", "Please enter your M-Pesa transaction code");
    }

    // Extract code from full SMS or pasted text (do not trust first 10 chars of a sentence)
    $mpesa_code = PamnetExtractMpesaCode($raw);
    if ($mpesa_code === '' || strlen($mpesa_code) < 8) {
        sendJsonMpesaCodeResponse("error", "Could not find a valid M-Pesa code. Enter the 10-character code (e.g. UHA2A34BPR).");
    }

    $codeUpper = strtoupper($mpesa_code);

    // READ-ONLY lookups — never rewrite / alter payment rows here
    $username = '';
    $pg = ORM::for_table('tbl_payment_gateway')
        ->where_raw('UPPER(gateway_trx_id) = ?', [$codeUpper])
        ->order_by_desc('id')
        ->find_one();

    $alreadyUsed = false;
    if ($pg) {
        $username = trim((string) $pg['username']);
        $status = (int) $pg['status'];

        if ($status === 4) {
            sendJsonMpesaCodeResponse("error", "That transaction was cancelled. Please pay again or use a successful M-Pesa code.", [
                "Resultcode" => "2",
                "Redirect" => "Transaction Cancelled",
                "already_used" => false,
            ]);
        }
        if ($status === 2) {
            // Paid + activated — this code is already consumed
            $alreadyUsed = true;
        }
        if ($status !== 2 && $status !== 4) {
            // Pending STK — may still match C2B / invoice below
            $username = $username ?: '';
        }
    }

    $tx = ORM::for_table('tbl_transactions')
        ->where_raw('UPPER(invoice) = ?', [$codeUpper])
        ->order_by_desc('id')
        ->find_one();
    if ($tx) {
        $alreadyUsed = true;
        if ($username === '') {
            $username = trim((string) $tx['username']);
        }
    }

    $mp = ORM::for_table('tbl_mpesa_transactions')
        ->where_raw('UPPER(TransID) = ?', [$codeUpper])
        ->order_by_desc('id')
        ->find_one();
    if ($mp && $username === '') {
        if (class_exists('PamnetHotspotPay')) {
            $username = PamnetHotspotPay::parseBillRef($mp['BillRefNumber'] ?? '');
        } else {
            $b = trim((string) ($mp['BillRefNumber'] ?? ''));
            $username = (stripos($b, 'Hotspot-') === 0) ? substr($b, 8) : $b;
        }
    }

    // Extra safety: shared helper (PG status=2 or invoice row)
    if (!$alreadyUsed && class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'isTransUsed')) {
        if (PamnetHotspotPay::isTransUsed($mpesa_code)) {
            $alreadyUsed = true;
        }
    }

    if ($username === '' && !$mp && !$tx && !$pg) {
        sendJsonMpesaCodeResponse("error", "M-Pesa code $codeUpper not found. Check the code and try again.", [
            "already_used" => false,
        ]);
    }

    if ($username === '' && $mp) {
        if (class_exists('PamnetHotspotPay')) {
            $username = PamnetHotspotPay::parseBillRef($mp['BillRefNumber'] ?? '');
        }
    }

    if ($username === '') {
        sendJsonMpesaCodeResponse("error", "M-Pesa code $codeUpper not found. Check the code and try again.", [
            "already_used" => false,
        ]);
    }

    // Already used → never report as valid/successful payment
    if ($alreadyUsed) {
        $active = PamnetUsernameHasActivePlan($username);
        $msg = "This M-Pesa code ($codeUpper) has already been used and cannot be used again.";
        if ($active) {
            $msg .= " Your package is still active — please Sign In with username $username.";
        } else {
            $msg .= " Please buy a new package.";
        }
        sendJsonMpesaCodeResponse("error", $msg, [
            "Resultcode" => "2",
            "already_used" => true,
            "username" => $username,
            "active" => (bool) $active,
            "mpesa_code" => $codeUpper,
        ]);
    }

    // First-time use only: C2B money received but not yet activated
    if (!PamnetUsernameHasActivePlan($username) && class_exists('PamnetHotspotPay') && $mp) {
        PamnetHotspotPay::activateFromC2B($username, 168);
    }

    if (!PamnetUsernameHasActivePlan($username)) {
        // Re-check whether activation just created the used invoice
        $nowUsed = (bool) ORM::for_table('tbl_transactions')
            ->where_raw('UPPER(invoice) = ?', [$codeUpper])
            ->find_one();
        if ($nowUsed) {
            sendJsonMpesaCodeResponse("error", "This M-Pesa code ($codeUpper) has already been used and cannot be used again. Please buy a new package.", [
                "Resultcode" => "2",
                "already_used" => true,
                "username" => $username,
                "mpesa_code" => $codeUpper,
            ]);
        }
        sendJsonMpesaCodeResponse("error", "Payment $codeUpper was found but the package has expired. Please buy a new package.", [
            "Resultcode" => "2",
            "already_used" => false,
            "username" => $username,
        ]);
    }

    $ready = PamnetEnsureHotspotOnRouter($username);
    if (!$ready) {
        usleep(400000);
        $ready = PamnetEnsureHotspotOnRouter($username);
    }

    $cust = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
    $pass = ($cust && !empty($cust['password'])) ? (string) $cust['password'] : '1234';

    sendJsonMpesaCodeResponse("success", "Payment $codeUpper confirmed. Connecting you to Wi-Fi…", [
        "Resultcode" => "3",
        "username" => $username,
        "tyhK" => $pass,
        "ready" => (bool) $ready,
        "active" => true,
        "already_used" => false,
        "mpesa_code" => $codeUpper,
    ]);
}

/**
 * Pull a Safaricom M-Pesa receipt code from raw input / full SMS.
 */
function PamnetExtractMpesaCode($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    // Common: code at start, or "Confirmed. ..." after code
    if (preg_match('/\b([A-Z][A-Z0-9]{9})\b/i', $raw, $m)) {
        return strtoupper($m[1]);
    }
    $clean = preg_replace('/[^A-Za-z0-9]/', '', $raw);
    if (strlen($clean) >= 10) {
        return strtoupper(substr($clean, 0, 10));
    }
    return strtoupper($clean);
}

function GetHotspotSettings()
{
    // Check if the request method is GET (for settings)
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit();
    }

    // Get settings from database
    $settings = [];
    $settingsToFetch = ['phone', 'hotspot_title', 'CompanyName', 'faq1', 'faq2', 'faq3'];
    
    foreach ($settingsToFetch as $setting) {
        $result = ORM::for_table('tbl_appconfig')
            ->where('setting', $setting)
            ->find_one();
        $settings[$setting] = $result ? $result->value : '';
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "status" => "success",
        "data" => $settings
    ]);
    exit();
}

/**
 * Helper function to send JSON response
 */
function sendJsonMpesaCodeResponse($status, $message, $data = [])
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(["status" => $status, "message" => $message], $data));
    exit();
}
