<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@ini_set('default_socket_timeout', '5');
error_reporting(E_ALL);
@set_time_limit(45);

while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

function rs_cli_line($message)
{
    $line = '[hotspot-publish-worker] ' . trim((string) $message) . PHP_EOL;
    @fwrite(STDOUT, $line);
    @fflush(STDOUT);
}

function rs_cli_fail($message, $code = 1)
{
    @fwrite(STDERR, '[hotspot-publish-worker] FAILED ' . preg_replace('/[\r\n]+/', ' ', (string) $message) . PHP_EOL);
    @fflush(STDERR);
    exit((int) $code);
}

register_shutdown_function(function () {
    $last = error_get_last();
    if (is_array($last) && in_array((int) ($last['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $msg = preg_replace('/[\r\n]+/', ' ', (string) ($last['message'] ?? 'fatal error'));
        @fwrite(STDERR, '[hotspot-publish-worker] FATAL ' . $msg . PHP_EOL);
        @fflush(STDERR);
    }
});

$routerId = isset($argv[1]) ? (int) $argv[1] : 0;
$reason = isset($argv[2]) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $argv[2]) : 'manual';
if ($routerId <= 0) {
    rs_cli_fail('Usage: php tools/publish_hotspot_portal.php ROUTER_ID [reason]', 2);
}

$root = dirname(__DIR__);
rs_cli_line('BOOT minimal bootstrap router=' . $routerId);

try {
    require $root . DIRECTORY_SEPARATOR . 'config.php';
    require_once $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'orm.php';
    require_once $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'PEAR2' . DIRECTORY_SEPARATOR . 'Autoload.php';
    require_once $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'Mikrotik.php';
    require_once $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . 'RSWireguardControlPlane.php';

    if (!isset($db_pass) || $db_pass === '') {
        $db_pass = isset($db_password) ? $db_password : '';
    }
    ORM::configure("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4");
    ORM::configure('username', $db_user);
    ORM::configure('password', $db_pass);
    ORM::configure('driver_options', [
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, sql_mode=""',
        PDO::ATTR_TIMEOUT => 10,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    ORM::configure('return_result_sets', true);
} catch (Throwable $e) {
    rs_cli_fail('Minimal bootstrap failed: ' . $e->getMessage());
}

function rs_cfg_get($key, $default = '')
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', (string) $key)->find_one();
    return $row ? (string) $row['value'] : (string) $default;
}

function rs_cfg_set($key, $value)
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', (string) $key)->find_one();
    if (!$row) {
        $row = ORM::for_table('tbl_appconfig')->create();
        $row->setting = (string) $key;
    }
    $row->value = (string) $value;
    $row->save();
}

function rs_billing_url()
{
    $url = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    if ($url === '') {
        $url = rtrim(rs_cfg_get('hotspot_billing_url', ''), '/');
    }
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('A valid APP_URL/hotspot_billing_url is required.');
    }
    return $url;
}

function rs_router_host($router)
{
    $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
    $wg = trim((string) ($router['wg_tunnel_ip'] ?? ''));
    $stored = trim((string) ($router['ip_address'] ?? ''));
    if ($transport === 'wireguard' && filter_var($wg, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $wg;
    }
    return $stored;
}

function rs_router_client($router)
{
    $host = rs_router_host($router);
    $user = trim((string) ($router['username'] ?? ''));
    $pass = (string) ($router['password'] ?? '');
    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Router management connection is incomplete.');
    }
    @ini_set('default_socket_timeout', '5');
    $client = Mikrotik::getClient($host, $user, $pass);
    if (!$client) {
        throw new RuntimeException('RouterOS API client is unavailable.');
    }
    return $client;
}

function rs_hotspot_meta($client)
{
    $profileName = '';
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/print')) as $row) {
        $disabled = strtolower(trim((string) $row->getProperty('disabled')));
        if ($disabled === 'yes' || $disabled === 'true') {
            continue;
        }
        $profileName = trim((string) $row->getProperty('profile'));
        if ($profileName !== '') {
            break;
        }
    }

    $dir = 'hotspot';
    $loginIp = '';
    if ($profileName !== '') {
        foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $row) {
            if ((string) $row->getProperty('name') !== $profileName) {
                continue;
            }
            $candidateDir = trim((string) $row->getProperty('html-directory'));
            if ($candidateDir !== '') {
                $dir = trim($candidateDir, "/\\");
            }
            $candidateIp = trim((string) $row->getProperty('hotspot-address'));
            if (filter_var($candidateIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $loginIp = $candidateIp;
            }
            break;
        }
    }
    if ($dir === '' || strpos($dir, '..') !== false || !preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $dir)) {
        $dir = 'hotspot';
    }
    return ['directory' => $dir, 'login_ip' => $loginIp, 'profile' => $profileName];
}

function rs_plan_payload($router)
{
    $routerId = (int) $router['id'];
    $routerName = trim((string) $router['name']);
    $currency = rs_cfg_get('currency_code', 'Ksh.');
    $shape = rs_cfg_get('shape_selector', 'square');
    $color = rs_cfg_get('color_scheme', 'green');

    $plans = ORM::for_table('tbl_plans')
        ->where('type', 'Hotspot')
        ->where('enabled', 1)
        ->where_raw("(tbl_plans.routers = ? OR FIND_IN_SET(?, REPLACE(tbl_plans.routers, ' ', '')) > 0 OR tbl_plans.routers = 'all')", [$routerName, $routerName])
        ->find_array();

    usort($plans, function ($a, $b) {
        $ao = stripos((string) ($a['name_plan'] ?? ''), 'offer') !== false;
        $bo = stripos((string) ($b['name_plan'] ?? ''), 'offer') !== false;
        if ($ao !== $bo) {
            return $ao ? -1 : 1;
        }
        $price = ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
        return $price !== 0 ? $price : ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });

    $items = [];
    foreach ($plans as $plan) {
        $items[] = [
            'plantype' => 'Hotspot',
            'planname' => (string) ($plan['name_plan'] ?? ''),
            'typebp' => (string) ($plan['typebp'] ?? ''),
            'currency' => $currency,
            'price' => $plan['price'] ?? 0,
            'validity' => $plan['validity'] ?? 0,
            'shared_users' => max(1, (int) ($plan['shared_users'] ?? 1)),
            'device' => (string) ($plan['device'] ?? ''),
            'datalimit' => $plan['data_limit'] ?? 0,
            'timelimit' => $plan['validity_unit'] ?? '',
            'paymentlink' => '',
            'planId' => (int) ($plan['id'] ?? 0),
            'routerName' => $routerName,
            'routerId' => $routerId,
            'shape' => $shape,
            'color_scheme' => $color,
        ];
    }

    return [[
        'name' => $routerName,
        'router_id' => $routerId,
        'description' => (string) ($router['description'] ?? ''),
        'plans_hotspot' => $items,
    ]];
}

function rs_generate_login_html($root, $billingUrl)
{
    $parts = parse_url($billingUrl);
    $port = isset($parts['port']) ? (int) $parts['port'] : ((strtolower((string) ($parts['scheme'] ?? 'http')) === 'https') ? 443 : 80);
    $host = (string) ($parts['host'] ?? '127.0.0.1');
    $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
    $loopScheme = ($scheme === 'https' && $port === 443) ? 'https' : 'http';
    $loop = $loopScheme . '://127.0.0.1' . (($port === 80 && $loopScheme === 'http') || ($port === 443 && $loopScheme === 'https') ? '' : ':' . $port)
        . '/download.php?download=1&_rscli=' . time();
    $hostHeader = $host . ((($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) ? ':' . $port : '');

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "Host: {$hostHeader}\r\nAccept: text/html\r\nConnection: close\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $html = (string) @file_get_contents($loop, false, $ctx);
    if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) {
        $curl = 'curl -fsS --max-time 15 -H ' . escapeshellarg('Host: ' . $hostHeader) . ' ' . escapeshellarg($loop) . ' 2>/dev/null';
        $html = (string) @shell_exec($curl);
    }
    if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) {
        throw new RuntimeException('Could not generate login.html from the local billing server.');
    }
    return $html;
}

function rs_patch_login_html($html, $billingUrl, $routerId, array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $billingJson = json_encode(rtrim($billingUrl, '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $routerJson = json_encode((string) $routerId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $html = preg_replace('#<script id="rs-baked-hotspot-plans">.*?</script>\s*#is', '', (string) $html);
    $boot = '<script id="rs-baked-hotspot-plans">window.RS_BAKED_PLAN_RESPONSE=' . $json . ';</script>';
    $authority = '<script id="rs-authoritative-hotspot-runtime">(function(){try{localStorage.removeItem("pamnet_billing_base");}catch(e){};var b=' . $billingJson . ';var r=' . $routerJson . ';if(window.PAMNET_PORTAL){window.PAMNET_PORTAL.apiBase=b;window.PAMNET_PORTAL.routerId=r;}window.RS_AUTHORITATIVE_BILLING=b;window.RS_AUTHORITATIVE_ROUTER=r;})();</script>';
    $html = preg_replace('#</head>#i', $boot . "\n" . $authority . "\n</head>", $html, 1);

    $loader = <<<'JS'
(function(){
  var baked=Array.isArray(window.RS_BAKED_PLAN_RESPONSE)?window.RS_BAKED_PLAN_RESPONSE:[];
  function render(p){
    if(!Array.isArray(p)||!p.length||typeof populateCards!=='function')return false;
    var has=false;
    for(var i=0;i<p.length;i++){
      if(p[i]&&Array.isArray(p[i].plans_hotspot)&&p[i].plans_hotspot.length){has=true;break;}
    }
    if(!has)return false;
    populateCards({data:p});
    return true;
  }
  render(baked);
  if(typeof pamnetFetch!=='function')return;
  var rid=(window.PAMNET_PORTAL&&PAMNET_PORTAL.routerId)?String(PAMNET_PORTAL.routerId):'';
  if(!rid)return;
  pamnetFetch('hotspot_plans',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({router_id:rid})})
    .then(function(r){if(!r||!r.ok)throw new Error('HTTP '+(r?r.status:0));return r.json();})
    .then(function(d){var p=Array.isArray(d)?d:((d&&Array.isArray(d.data))?d.data:[]);render(p);})
    .catch(function(e){try{console.log('Using embedded Hotspot packages',e);}catch(x){} render(baked);});
})();
JS;

    $pattern = '#fetchData\(\);\s*</script>#i';
    if (preg_match($pattern, $html)) {
        $html = preg_replace($pattern, $loader . "\n</script>", $html, 1);
    } else {
        $html = preg_replace('#</body>#i', '<script>' . $loader . '</script></body>', $html, 1);
    }
    return $html;
}

function rs_store_login_html($root, $html)
{
    $uploads = $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploads) && !@mkdir($uploads, 0755, true) && !is_dir($uploads)) {
        throw new RuntimeException('Could not create system/uploads.');
    }
    $rootFile = $root . DIRECTORY_SEPARATOR . 'hotspot_login.html';
    $uploadFile = $uploads . DIRECTORY_SEPARATOR . 'hotspot_login.html';
    if (@file_put_contents($rootFile, $html) === false || @file_put_contents($uploadFile, $html) === false) {
        throw new RuntimeException('Could not write generated hotspot_login.html.');
    }
    return $rootFile;
}

function rs_remove_legacy_walled_garden($client)
{
    $legacy = [
        'net.pamnetsolutions.co.ke' => true,
        '*.net.pamnetsolutions.co.ke' => true,
        'pamnetsolutions.co.ke' => true,
        '*.pamnetsolutions.co.ke' => true,
    ];
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
        $host = strtolower(rtrim(trim((string) $row->getProperty('dst-host')), '.'));
        $id = trim((string) $row->getProperty('.id'));
        if ($id === '' || !isset($legacy[$host])) {
            continue;
        }
        $remove = new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/remove');
        $remove->setArgument('numbers', $id);
        $client->sendSync($remove);
    }
}

function rs_ensure_billing_walled_garden($client, $billingUrl)
{
    $parts = parse_url($billingUrl);
    $host = trim((string) ($parts['host'] ?? ''));
    $scheme = strtolower(trim((string) ($parts['scheme'] ?? 'http')));
    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new RuntimeException('This deployment expects an IPv4 billing host for the captive portal.');
    }

    $exists = false;
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $row) {
        $address = trim((string) $row->getProperty('dst-address'));
        $dstPort = trim((string) $row->getProperty('dst-port'));
        $protocol = strtolower(trim((string) $row->getProperty('protocol')));
        if (($address === $host || $address === $host . '/32')
            && ($dstPort === '' || $dstPort === (string) $port)
            && ($protocol === '' || $protocol === 'tcp' || $protocol === '6')) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $add = new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
        $add->setArgument('action', 'accept')
            ->setArgument('protocol', 'tcp')
            ->setArgument('dst-address', $host)
            ->setArgument('dst-port', (string) $port)
            ->setArgument('disabled', 'no');
        $client->sendSync($add);
    }
}

function rs_router_source_url($billingUrl)
{
    $parts = parse_url($billingUrl);
    $port = isset($parts['port']) ? (int) $parts['port'] : 80;
    try {
        $wg = RSWireguardControlPlane::publicConfig();
        $serverIp = trim((string) ($wg['server_ip'] ?? ''));
        if (filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'http://' . $serverIp . ':' . $port . '/hotspot_login.html?_plans=' . time();
        }
    } catch (Throwable $ignored) {
    }
    return rtrim($billingUrl, '/') . '/hotspot_login.html?_plans=' . time();
}

function rs_find_router_file($client, $name)
{
    $found = [];
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/file/print')) as $row) {
        if ((string) $row->getProperty('name') !== (string) $name) {
            continue;
        }
        $found[] = [
            'id' => trim((string) $row->getProperty('.id')),
            'size' => trim((string) $row->getProperty('size')),
        ];
    }
    return $found;
}

function rs_safe_upload($client, $sourceUrl, $directory)
{
    $directory = trim((string) $directory, "/\\") ?: 'hotspot';
    if (!preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $directory) || strpos($directory, '..') !== false) {
        throw new RuntimeException('Invalid Hotspot HTML directory.');
    }
    $destination = $directory . '/login.html';
    $temporary = $directory . '/rs-login-' . substr(sha1($sourceUrl . microtime(true)), 0, 10) . '.html';

    $fetch = new PEAR2\Net\RouterOS\Request('/tool/fetch');
    $fetch->setArgument('url', $sourceUrl)
        ->setArgument('dst-path', $temporary)
        ->setArgument('keep-result', 'yes');
    if (stripos($sourceUrl, 'https://') === 0) {
        $fetch->setArgument('mode', 'https')->setArgument('check-certificate', 'no');
    } else {
        $fetch->setArgument('mode', 'http');
    }
    $client->sendSync($fetch);
    usleep(500000);

    $tmp = rs_find_router_file($client, $temporary);
    if (!$tmp || empty($tmp[0]['id']) || empty($tmp[0]['size']) || $tmp[0]['size'] === '0') {
        throw new RuntimeException('RouterOS did not download a valid temporary portal file.');
    }

    foreach (rs_find_router_file($client, $destination) as $old) {
        if ($old['id'] === '') {
            continue;
        }
        $remove = new PEAR2\Net\RouterOS\Request('/file/remove');
        $remove->setArgument('numbers', $old['id']);
        $client->sendSync($remove);
    }

    $rename = new PEAR2\Net\RouterOS\Request('/file/set');
    $rename->setArgument('numbers', $tmp[0]['id'])->setArgument('name', $destination);
    $client->sendSync($rename);
    usleep(200000);

    $live = rs_find_router_file($client, $destination);
    if (!$live) {
        throw new RuntimeException('RouterOS downloaded the portal but could not promote it to login.html.');
    }
    return $live[0]['size'] ?: $tmp[0]['size'];
}

try {
    rs_cli_line('STAGE loading router');
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        throw new RuntimeException('Router not found.');
    }
    $routerName = trim((string) $router['name']);
    if ($routerName === '') {
        throw new RuntimeException('Router name is empty.');
    }

    rs_cli_line('STAGE connecting RouterOS API');
    $client = rs_router_client($router);
    $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print'));
    $meta = rs_hotspot_meta($client);

    rs_cli_line('STAGE preparing router-specific portal data');
    $billingUrl = rs_billing_url();
    rs_cfg_set('router_id', (string) $routerId);
    rs_cfg_set('router_name', $routerName);
    rs_cfg_set('hotspot_billing_url', $billingUrl);
    if ($meta['login_ip'] !== '') {
        rs_cfg_set('hotspot_login_url', 'http://' . $meta['login_ip'] . '/login');
    }
    $payload = rs_plan_payload($router);
    $packageCount = isset($payload[0]['plans_hotspot']) ? count($payload[0]['plans_hotspot']) : 0;
    rs_cli_line('STAGE packages prepared count=' . $packageCount);

    rs_cli_line('STAGE generating login.html');
    $html = rs_generate_login_html($root, $billingUrl);
    $html = rs_patch_login_html($html, $billingUrl, $routerId, $payload);
    $path = rs_store_login_html($root, $html);
    rs_cli_line('STAGE generated bytes=' . strlen($html));

    rs_cli_line('STAGE updating billing walled garden');
    rs_remove_legacy_walled_garden($client);
    rs_ensure_billing_walled_garden($client, $billingUrl);

    $sourceUrl = rs_router_source_url($billingUrl);
    $displaySource = preg_replace('#\?.*$#', '', $sourceUrl);
    rs_cli_line('STAGE uploading portal source=' . $displaySource);
    $size = rs_safe_upload($client, $sourceUrl, $meta['directory']);

    rs_cli_line('STAGE final verification');
    rs_remove_legacy_walled_garden($client);
    rs_ensure_billing_walled_garden($client, $billingUrl);

    rs_cli_line('DONE router=' . $routerId . ' packages=' . $packageCount . ' size=' . $size . ' reason=' . ($reason ?: 'manual'));
    echo 'HOTSPOT_PORTAL_PUBLISH_OK router=' . $routerId . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    rs_cli_fail($e->getMessage(), 1);
}
