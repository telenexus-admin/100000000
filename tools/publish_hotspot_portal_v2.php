<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@ini_set('default_socket_timeout', '6');
error_reporting(E_ALL);
@set_time_limit(55);
while (ob_get_level() > 0) { @ob_end_flush(); }
ob_implicit_flush(true);

function rsv2_line($message)
{
    $line = '[hotspot-publish-v2] ' . trim((string)$message) . PHP_EOL;
    fwrite(STDOUT, $line);
    fflush(STDOUT);
}

function rsv2_fail($message, $code = 1)
{
    fwrite(STDERR, '[hotspot-publish-v2] FAILED ' . preg_replace('/[\r\n]+/', ' ', (string)$message) . PHP_EOL);
    fflush(STDERR);
    exit((int)$code);
}

$routerId = isset($argv[1]) ? (int)$argv[1] : 0;
$reason = isset($argv[2]) ? preg_replace('/[^a-z0-9_-]/i', '', (string)$argv[2]) : 'manual';
if ($routerId <= 0) {
    rsv2_fail('Usage: php tools/publish_hotspot_portal_v2.php ROUTER_ID [reason]', 2);
}

$root = dirname(__DIR__);
rsv2_line('BOOT minimal bootstrap router=' . $routerId);

try {
    require $root . '/config.php';
    require_once $root . '/system/orm.php';
    require_once $root . '/system/autoload/PEAR2/Autoload.php';
    require_once $root . '/system/autoload/Mikrotik.php';
    require_once $root . '/system/autoload/RSWireguardControlPlane.php';

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
    rsv2_fail('Bootstrap failed: ' . $e->getMessage());
}

function rsv2_cfg_get($key, $default = '')
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', (string)$key)->find_one();
    return $row ? (string)$row['value'] : (string)$default;
}

function rsv2_cfg_set($key, $value)
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', (string)$key)->find_one();
    if (!$row) {
        $row = ORM::for_table('tbl_appconfig')->create();
        $row->setting = (string)$key;
    }
    $row->value = (string)$value;
    $row->save();
}

function rsv2_billing_url()
{
    $url = defined('APP_URL') ? rtrim((string)APP_URL, '/') : '';
    if ($url === '') $url = rtrim(rsv2_cfg_get('hotspot_billing_url', ''), '/');
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('A valid billing URL is required.');
    }
    return $url;
}

function rsv2_router_host($router)
{
    $transport = strtolower(trim((string)($router['management_transport'] ?? '')));
    $wg = trim((string)($router['wg_tunnel_ip'] ?? ''));
    $stored = trim((string)($router['ip_address'] ?? ''));
    if ($transport === 'wireguard' && filter_var($wg, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $wg;
    return $stored;
}

function rsv2_client($router)
{
    $host = rsv2_router_host($router);
    $user = trim((string)($router['username'] ?? ''));
    $pass = (string)($router['password'] ?? '');
    if ($host === '' || $user === '' || $pass === '') throw new RuntimeException('Router management connection is incomplete.');
    $client = Mikrotik::getClient($host, $user, $pass);
    if (!$client) throw new RuntimeException('RouterOS API client is unavailable.');
    return $client;
}

function rsv2_hotspot_meta($client)
{
    $profile = '';
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/print')) as $row) {
        $disabled = strtolower(trim((string)$row->getProperty('disabled')));
        if ($disabled === 'yes' || $disabled === 'true') continue;
        $profile = trim((string)$row->getProperty('profile'));
        if ($profile !== '') break;
    }
    $dir = 'hotspot';
    $loginIp = '';
    if ($profile !== '') {
        foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $row) {
            if ((string)$row->getProperty('name') !== $profile) continue;
            $d = trim((string)$row->getProperty('html-directory'));
            if ($d !== '') $dir = trim($d, "/\\");
            $ip = trim((string)$row->getProperty('hotspot-address'));
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $loginIp = $ip;
            break;
        }
    }
    if ($dir === '' || strpos($dir, '..') !== false || !preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $dir)) $dir = 'hotspot';
    return ['directory' => $dir, 'login_ip' => $loginIp, 'profile' => $profile];
}

function rsv2_plan_payload($router)
{
    $routerId = (int)$router['id'];
    $routerName = trim((string)$router['name']);
    $currency = rsv2_cfg_get('currency_code', 'Ksh.');
    $shape = rsv2_cfg_get('shape_selector', 'square');
    $color = rsv2_cfg_get('color_scheme', 'green');
    $plans = ORM::for_table('tbl_plans')
        ->where('type', 'Hotspot')->where('enabled', 1)
        ->where_raw("(tbl_plans.routers = ? OR FIND_IN_SET(?, REPLACE(tbl_plans.routers, ' ', '')) > 0 OR tbl_plans.routers = 'all')", [$routerName, $routerName])
        ->find_array();
    usort($plans, function ($a, $b) {
        $ao = stripos((string)($a['name_plan'] ?? ''), 'offer') !== false;
        $bo = stripos((string)($b['name_plan'] ?? ''), 'offer') !== false;
        if ($ao !== $bo) return $ao ? -1 : 1;
        $pc = ((float)($a['price'] ?? 0)) <=> ((float)($b['price'] ?? 0));
        return $pc !== 0 ? $pc : ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
    });
    $items = [];
    foreach ($plans as $p) {
        $items[] = [
            'plantype' => 'Hotspot', 'planname' => (string)($p['name_plan'] ?? ''),
            'typebp' => (string)($p['typebp'] ?? ''), 'currency' => $currency,
            'price' => $p['price'] ?? 0, 'validity' => $p['validity'] ?? 0,
            'shared_users' => max(1, (int)($p['shared_users'] ?? 1)),
            'device' => (string)($p['device'] ?? ''), 'datalimit' => $p['data_limit'] ?? 0,
            'timelimit' => $p['validity_unit'] ?? '', 'paymentlink' => '',
            'planId' => (int)($p['id'] ?? 0), 'routerName' => $routerName,
            'routerId' => $routerId, 'shape' => $shape, 'color_scheme' => $color,
        ];
    }
    return [[
        'name' => $routerName, 'router_id' => $routerId,
        'description' => (string)($router['description'] ?? ''), 'plans_hotspot' => $items,
    ]];
}

function rsv2_generate_html($billingUrl)
{
    $parts = parse_url($billingUrl);
    $port = isset($parts['port']) ? (int)$parts['port'] : 80;
    $host = (string)($parts['host'] ?? '127.0.0.1');
    $loop = 'http://127.0.0.1' . ($port === 80 ? '' : ':' . $port) . '/download.php?download=1&_rsv2=' . time();
    $hostHeader = $host . ($port === 80 ? '' : ':' . $port);
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true, 'header' => "Host: {$hostHeader}\r\nAccept: text/html\r\nConnection: close\r\n"]]);
    $html = (string)@file_get_contents($loop, false, $ctx);
    if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) {
        $html = (string)@shell_exec('curl -fsS --max-time 15 -H ' . escapeshellarg('Host: ' . $hostHeader) . ' ' . escapeshellarg($loop) . ' 2>/dev/null');
    }
    if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) throw new RuntimeException('Could not generate login.html from local billing server.');
    return $html;
}

function rsv2_patch_html($html, $billingUrl, $routerId, array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $billingJson = json_encode(rtrim($billingUrl, '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $routerJson = json_encode((string)$routerId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $html = preg_replace('#<script id="rs-baked-hotspot-plans">.*?</script>\s*#is', '', (string)$html);
    $boot = '<script id="rs-baked-hotspot-plans">window.RS_BAKED_PLAN_RESPONSE=' . $json . ';</script>';
    $auth = '<script id="rs-authoritative-hotspot-runtime">(function(){try{localStorage.removeItem("pamnet_billing_base");}catch(e){}var b=' . $billingJson . ',r=' . $routerJson . ';if(window.PAMNET_PORTAL){PAMNET_PORTAL.apiBase=b;PAMNET_PORTAL.routerId=r;}window.RS_AUTHORITATIVE_BILLING=b;window.RS_AUTHORITATIVE_ROUTER=r;})();</script>';
    $html = preg_replace('#</head>#i', $boot . "\n" . $auth . "\n</head>", $html, 1);
    $loader = <<<'JS'
(function(){var baked=Array.isArray(window.RS_BAKED_PLAN_RESPONSE)?window.RS_BAKED_PLAN_RESPONSE:[];function render(p){if(!Array.isArray(p)||!p.length||typeof populateCards!=='function')return false;var has=false;for(var i=0;i<p.length;i++){if(p[i]&&Array.isArray(p[i].plans_hotspot)&&p[i].plans_hotspot.length){has=true;break;}}if(!has)return false;populateCards({data:p});return true;}render(baked);if(typeof pamnetFetch!=='function')return;var rid=(window.PAMNET_PORTAL&&PAMNET_PORTAL.routerId)?String(PAMNET_PORTAL.routerId):'';if(!rid)return;pamnetFetch('hotspot_plans',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({router_id:rid})}).then(function(r){if(!r||!r.ok)throw new Error('HTTP '+(r?r.status:0));return r.json();}).then(function(d){render(Array.isArray(d)?d:((d&&Array.isArray(d.data))?d.data:[]));}).catch(function(){render(baked);});})();
JS;
    $pattern = '#fetchData\(\);\s*</script>#i';
    return preg_match($pattern, $html) ? preg_replace($pattern, $loader . "\n</script>", $html, 1) : preg_replace('#</body>#i', '<script>' . $loader . '</script></body>', $html, 1);
}

function rsv2_store_html($root, $html)
{
    $uploadDir = $root . '/system/uploads';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    foreach ([$root . '/hotspot_login.html', $uploadDir . '/hotspot_login.html'] as $path) {
        if (@file_put_contents($path, $html) === false) throw new RuntimeException('Could not write generated hotspot_login.html.');
    }
}

function rsv2_remove_legacy_wg($client)
{
    $legacy = ['net.pamnetsolutions.co.ke'=>true,'*.net.pamnetsolutions.co.ke'=>true,'pamnetsolutions.co.ke'=>true,'*.pamnetsolutions.co.ke'=>true];
    try {
        foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
            $h = strtolower(rtrim(trim((string)$row->getProperty('dst-host')), '.'));
            $id = trim((string)$row->getProperty('.id'));
            if ($id !== '' && isset($legacy[$h])) {
                $r = new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/remove'); $r->setArgument('numbers', $id); $client->sendSync($r);
            }
        }
    } catch (Throwable $ignored) {}
}

function rsv2_ensure_billing($client, $billingUrl)
{
    $p = parse_url($billingUrl); $host = trim((string)($p['host'] ?? '')); $port = isset($p['port']) ? (int)$p['port'] : 80;
    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw new RuntimeException('Billing host must be IPv4 for this deployment.');
    $exists = false;
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $row) {
        $a = trim((string)$row->getProperty('dst-address')); $dp = trim((string)$row->getProperty('dst-port')); $pr = strtolower(trim((string)$row->getProperty('protocol')));
        if (($a === $host || $a === $host.'/32') && ($dp === '' || $dp === (string)$port) && ($pr === '' || $pr === 'tcp' || $pr === '6')) { $exists = true; break; }
    }
    if (!$exists) {
        $a = new PEAR2\Net\RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
        $a->setArgument('action','accept')->setArgument('protocol','tcp')->setArgument('dst-address',$host)->setArgument('dst-port',(string)$port)->setArgument('disabled','no');
        $client->sendSync($a);
    }
}

function rsv2_source_url($billingUrl)
{
    $p = parse_url($billingUrl); $port = isset($p['port']) ? (int)$p['port'] : 80;
    try { $wg = RSWireguardControlPlane::publicConfig(); $ip = trim((string)($wg['server_ip'] ?? '')); if (filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) return 'http://'.$ip.':'.$port.'/hotspot_login.html?_rsv2='.time(); } catch (Throwable $ignored) {}
    return rtrim($billingUrl,'/').'/hotspot_login.html?_rsv2='.time();
}

function rsv2_files($client, $name)
{
    $out=[]; foreach($client->sendSync(new PEAR2\Net\RouterOS\Request('/file/print')) as $row){if((string)$row->getProperty('name')===(string)$name)$out[]=['id'=>trim((string)$row->getProperty('.id')),'size'=>trim((string)$row->getProperty('size'))];} return $out;
}

function rsv2_fetch_file($client, $sourceUrl, $destination)
{
    $f = new PEAR2\Net\RouterOS\Request('/tool/fetch');
    $f->setArgument('url',$sourceUrl)->setArgument('dst-path',$destination)->setArgument('keep-result','yes');
    if (stripos($sourceUrl,'https://')===0) $f->setArgument('mode','https')->setArgument('check-certificate','no'); else $f->setArgument('mode','http');
    $client->sendSync($f); usleep(500000);
}

function rsv2_publish_file($client, $sourceUrl, $directory)
{
    $directory = trim((string)$directory,"/\\") ?: 'hotspot';
    if (!preg_match('/^[A-Za-z0-9._\/-]{1,96}$/',$directory) || strpos($directory,'..')!==false) throw new RuntimeException('Invalid Hotspot HTML directory.');
    $destination=$directory.'/login.html'; $temporary=$directory.'/rs-probe-'.substr(sha1($sourceUrl.microtime(true)),0,10).'.html';

    // Prove the source is reachable without touching the current live page.
    rsv2_fetch_file($client,$sourceUrl,$temporary);
    $probe=rsv2_files($client,$temporary);
    if(!$probe || empty($probe[0]['id']) || empty($probe[0]['size']) || $probe[0]['size']==='0') throw new RuntimeException('RouterOS could not download the portal probe file.');
    rsv2_line('STAGE probe downloaded size='.$probe[0]['size']);

    // First try direct replacement. Some RouterOS releases replace dst-path in place.
    rsv2_fetch_file($client,$sourceUrl,$destination);
    $live=rsv2_files($client,$destination);
    if(!$live || empty($live[0]['size']) || $live[0]['size']==='0') {
        // If the existing destination prevented replacement, remove only that path
        // after the successful probe, then fetch directly to the final path.
        foreach(rsv2_files($client,$destination) as $old){if($old['id']==='')continue;$rm=new PEAR2\Net\RouterOS\Request('/file/remove');$rm->setArgument('numbers',$old['id']);$client->sendSync($rm);}
        rsv2_fetch_file($client,$sourceUrl,$destination);
        $live=rsv2_files($client,$destination);
    }
    if(!$live || empty($live[0]['size']) || $live[0]['size']==='0') throw new RuntimeException('RouterOS could not create the final login.html after a successful probe download.');

    // Clean probe and older failed temporary files only after the live page exists.
    foreach($client->sendSync(new PEAR2\Net\RouterOS\Request('/file/print')) as $row){$name=(string)$row->getProperty('name');$id=trim((string)$row->getProperty('.id'));if($id!=='' && (strpos($name,$directory.'/rs-probe-')===0 || strpos($name,$directory.'/rs-login-')===0)){$rm=new PEAR2\Net\RouterOS\Request('/file/remove');$rm->setArgument('numbers',$id);$client->sendSync($rm);}}
    return $live[0]['size'];
}

try {
    rsv2_line('STAGE loading router');
    $router=ORM::for_table('tbl_routers')->find_one($routerId); if(!$router)throw new RuntimeException('Router not found.');
    $routerName=trim((string)$router['name']); if($routerName==='')throw new RuntimeException('Router name is empty.');

    rsv2_line('STAGE connecting RouterOS API');
    $client=rsv2_client($router); $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print')); $meta=rsv2_hotspot_meta($client);

    rsv2_line('STAGE preparing router-specific portal data');
    $billing=rsv2_billing_url(); rsv2_cfg_set('router_id',(string)$routerId); rsv2_cfg_set('router_name',$routerName); rsv2_cfg_set('hotspot_billing_url',$billing); if($meta['login_ip']!=='')rsv2_cfg_set('hotspot_login_url','http://'.$meta['login_ip'].'/login');
    $payload=rsv2_plan_payload($router); $count=count($payload[0]['plans_hotspot'] ?? []); rsv2_line('STAGE packages prepared count='.$count);

    rsv2_line('STAGE generating login.html');
    $html=rsv2_patch_html(rsv2_generate_html($billing),$billing,$routerId,$payload); rsv2_store_html($root,$html); rsv2_line('STAGE generated bytes='.strlen($html));

    rsv2_line('STAGE updating billing walled garden'); rsv2_remove_legacy_wg($client); rsv2_ensure_billing($client,$billing);
    $source=rsv2_source_url($billing); rsv2_line('STAGE publishing portal source='.preg_replace('#\?.*$#','',$source));
    $size=rsv2_publish_file($client,$source,$meta['directory']);
    rsv2_line('STAGE final verification'); rsv2_remove_legacy_wg($client); rsv2_ensure_billing($client,$billing);
    rsv2_line('DONE router='.$routerId.' packages='.$count.' size='.$size.' reason='.($reason?:'manual'));
    echo 'HOTSPOT_PORTAL_PUBLISH_OK router='.$routerId.PHP_EOL; exit(0);
} catch(Throwable $e) { rsv2_fail($e->getMessage(),1); }
