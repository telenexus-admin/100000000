<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
error_reporting(E_ALL);
@set_time_limit(60);

function rshp2_line($message)
{
    echo '[hotspot-pay-fix-v2] ' . trim((string) $message) . PHP_EOL;
}

function rshp2_fail($message, $code = 1)
{
    fwrite(STDERR, '[hotspot-pay-fix-v2] FAILED ' . preg_replace('/[\r\n]+/', ' ', (string) $message) . PHP_EOL);
    exit((int) $code);
}

$routerId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($routerId <= 0) {
    rshp2_fail('Usage: php tools/repair_hotspot_payment_click_v2.php ROUTER_ID', 2);
}

$root = dirname(__DIR__);

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
    rshp2_fail('Bootstrap failed: ' . $e->getMessage());
}

function rshp2_router_host($router)
{
    $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
    $wg = trim((string) ($router['wg_tunnel_ip'] ?? ''));
    if ($transport === 'wireguard' && filter_var($wg, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $wg;
    }
    return trim((string) ($router['ip_address'] ?? ''));
}

function rshp2_client($router)
{
    $host = rshp2_router_host($router);
    $user = trim((string) ($router['username'] ?? ''));
    $pass = (string) ($router['password'] ?? '');
    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Router management connection is incomplete.');
    }
    $client = Mikrotik::getClient($host, $user, $pass);
    if (!$client) {
        throw new RuntimeException('RouterOS API client is unavailable.');
    }
    return $client;
}

function rshp2_hotspot_directory($client)
{
    $profile = '';
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/print')) as $row) {
        $disabled = strtolower(trim((string) $row->getProperty('disabled')));
        if ($disabled === 'yes' || $disabled === 'true') continue;
        $profile = trim((string) $row->getProperty('profile'));
        if ($profile !== '') break;
    }

    $dir = 'hotspot';
    if ($profile !== '') {
        foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $row) {
            if ((string) $row->getProperty('name') !== $profile) continue;
            $candidate = trim((string) $row->getProperty('html-directory'));
            if ($candidate !== '') $dir = trim($candidate, "/\\");
            break;
        }
    }
    if ($dir === '' || strpos($dir, '..') !== false || !preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $dir)) {
        $dir = 'hotspot';
    }
    return $dir;
}

function rshp2_files($client, $name)
{
    $out = [];
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/file/print')) as $row) {
        if ((string) $row->getProperty('name') !== (string) $name) continue;
        $out[] = [
            'id' => trim((string) $row->getProperty('.id')),
            'size' => trim((string) $row->getProperty('size')),
        ];
    }
    return $out;
}

function rshp2_fetch($client, $url, $destination)
{
    $req = new PEAR2\Net\RouterOS\Request('/tool/fetch');
    $req->setArgument('url', (string) $url)
        ->setArgument('dst-path', (string) $destination)
        ->setArgument('keep-result', 'yes')
        ->setArgument('mode', stripos((string) $url, 'https://') === 0 ? 'https' : 'http');
    if (stripos((string) $url, 'https://') === 0) {
        $req->setArgument('check-certificate', 'no');
    }
    $client->sendSync($req);
    usleep(500000);
}

function rshp2_source_url()
{
    $port = 80;
    if (defined('APP_URL')) {
        $p = parse_url((string) APP_URL);
        if (is_array($p) && !empty($p['port'])) $port = (int) $p['port'];
    }
    if ($port < 1 || $port > 65535) $port = 80;

    $serverIp = '10.78.0.1';
    try {
        if (class_exists('RSWireguardControlPlane') && method_exists('RSWireguardControlPlane', 'publicConfig')) {
            $cfg = RSWireguardControlPlane::publicConfig();
            $candidate = trim((string) ($cfg['server_ip'] ?? ''));
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $serverIp = $candidate;
        }
    } catch (Throwable $ignored) {
    }
    return 'http://' . $serverIp . ':' . $port . '/hotspot_login.html?_payfixv2=' . time();
}

$patch = <<<'HTML'
<script id="rs-hotspot-pay-final-override-v2">
(function(){
  window.__RS_HOTSPOT_PAY_FINAL_V2=true;

  function normalizePhone(raw){
    var p=String(raw||'').replace(/\D+/g,'');
    if(p.indexOf('0')===0){p='254'+p.substring(1);}
    else if(p.indexOf('7')===0 || p.indexOf('1')===0){p='254'+p;}
    return p;
  }

  function accountCode(){
    var a='';
    try{if(typeof getCookie==='function'){a=String(getCookie('account_number')||'').trim();}}catch(e){}
    if(!a){try{if(typeof generateAccountNumber==='function'){a=String(generateAccountNumber()||'').trim();}}catch(e2){}}
    if(!a){a=String(Math.floor(10000+Math.random()*90000));}
    try{if(typeof setCookie==='function'){setCookie('account_number',a,365);}}catch(e3){}
    return a;
  }

  function fail(msg){try{window.alert(msg);}catch(e){}}

  window.handlePhoneNumberSubmission=function(planId,routerId,price){
    var raw=window.prompt('Enter Your M-Pesa Number\nExample: 0712345678 or 0112345678','');
    if(raw===null){return false;}

    var phone=normalizePhone(raw);
    if(!/^254(7|1)\d{8}$/.test(phone)){
      fail('Enter a valid Safaricom M-Pesa number.');
      return false;
    }
    if(typeof window.pamnetFetch!=='function'){
      fail('Payment API helper is missing from this Hotspot page.');
      return false;
    }

    var account=accountCode();
    var u=document.getElementById('usernameInput');
    if(u){u.value=account;}

    var identity={mac:'',ip:'',device:'UNKNOWN_DEVICE'};
    try{if(typeof getMikrotikClientIdentity==='function'){identity=getMikrotikClientIdentity()||identity;}}catch(eI){}

    var payload={
      phone_number:phone,
      plan_id:String(planId),
      router_id:String(routerId),
      account_number:account,
      mac:identity.mac||'',
      mac_address:identity.mac||'',
      ip:identity.ip||'',
      device:identity.device||'UNKNOWN_DEVICE'
    };

    window.pamnetFetch('grant',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(payload)
    }).then(function(r){
      if(!r || !r.ok){throw new Error('HTTP '+(r?r.status:0));}
      return r.text();
    }).then(function(text){
      var data;
      try{data=JSON.parse(text);}catch(e){throw new Error('Invalid billing response: '+String(text||'').substring(0,120));}
      if(!data || data.status==='error') throw new Error((data&&data.message)?String(data.message):'M-Pesa payment start failed');
      var code=String(data.username||data.account_number||account).trim();
      if(code){
        try{if(typeof setCookie==='function'){setCookie('account_number',code,365);}}catch(eC){}
        if(u){u.value=code;}
      }
      fail('M-Pesa request accepted. Check your phone for the PIN prompt.');
      try{if(typeof checkPaymentStatus==='function'){checkPaymentStatus(phone);}}catch(eP){}
    }).catch(function(err){
      fail('Could not start M-Pesa payment: '+((err&&err.message)?err.message:String(err||'Unknown error')));
    });
    return false;
  };
})();
</script>
HTML;

try {
    rshp2_line('loading router=' . $routerId);
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) throw new RuntimeException('Router not found.');

    $portalPath = $root . '/hotspot_login.html';
    if (!is_file($portalPath)) throw new RuntimeException('hotspot_login.html not found.');
    $html = (string) file_get_contents($portalPath);
    if (strlen($html) < 500) throw new RuntimeException('Existing hotspot_login.html is unexpectedly small.');

    $backup = $portalPath . '.before-payment-click-v2.' . date('YmdHis');
    if (!copy($portalPath, $backup)) throw new RuntimeException('Could not create portal backup.');

    // Remove earlier payment override(s), including v1 which was inserted before
    // later scripts and could itself be overwritten by the original handler.
    $html = preg_replace('#<script id="rs-hotspot-pay-native-fallback">.*?</script>\s*#is', '', $html);
    $html = preg_replace('#<script id="rs-hotspot-pay-final-override-v2">.*?</script>\s*#is', '', $html);

    // IMPORTANT: place this after every original payment script. download.php has
    // scripts after its first </body>, so inserting before </body> is too early.
    $pos = strripos($html, '</html>');
    if ($pos !== false) {
        $html = substr($html, 0, $pos) . $patch . "\n" . substr($html, $pos);
    } else {
        $html .= "\n" . $patch . "\n";
    }

    if (file_put_contents($portalPath, $html) === false) throw new RuntimeException('Could not write repaired hotspot_login.html.');
    $uploadCopy = $root . '/system/uploads/hotspot_login.html';
    if (is_dir(dirname($uploadCopy))) @file_put_contents($uploadCopy, $html);

    if (strpos($html, 'rs-hotspot-pay-final-override-v2') === false) throw new RuntimeException('Final payment override marker missing after write.');
    rshp2_line('local portal patched after all scripts bytes=' . strlen($html) . ' backup=' . basename($backup));

    $client = rshp2_client($router);
    $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print'));
    $dir = rshp2_hotspot_directory($client);
    $destination = $dir . '/login.html';
    $probe = $dir . '/rs-pay-v2-probe-' . substr(sha1((string) microtime(true)), 0, 10) . '.html';
    $source = rshp2_source_url();

    rshp2_line('probing source=' . preg_replace('#\?.*$#', '', $source));
    rshp2_fetch($client, $source, $probe);
    $probeFiles = rshp2_files($client, $probe);
    if (!$probeFiles || empty($probeFiles[0]['id']) || empty($probeFiles[0]['size']) || $probeFiles[0]['size'] === '0') {
        throw new RuntimeException('RouterOS could not download the v2 portal probe. Live login.html was not touched.');
    }
    rshp2_line('probe downloaded size=' . $probeFiles[0]['size']);

    foreach (rshp2_files($client, $destination) as $old) {
        if ($old['id'] === '') continue;
        $rm = new PEAR2\Net\RouterOS\Request('/file/remove');
        $rm->setArgument('numbers', $old['id']);
        $client->sendSync($rm);
    }
    rshp2_fetch($client, $source, $destination);
    $live = rshp2_files($client, $destination);
    if (!$live || empty($live[0]['size']) || $live[0]['size'] === '0') {
        throw new RuntimeException('RouterOS could not create final login.html.');
    }

    foreach (rshp2_files($client, $probe) as $tmp) {
        if ($tmp['id'] === '') continue;
        $rm = new PEAR2\Net\RouterOS\Request('/file/remove');
        $rm->setArgument('numbers', $tmp['id']);
        $client->sendSync($rm);
    }

    rshp2_line('RESULT=HOTSPOT_PAYMENT_FINAL_OVERRIDE_INSTALLED router=' . $routerId . ' path=' . $destination . ' size=' . $live[0]['size']);
    rshp2_line('NEXT=Forget/rejoin Wi-Fi. Buy Now must open the native browser prompt; submitting it must send POST type=grant.');
    exit(0);
} catch (Throwable $e) {
    rshp2_fail($e->getMessage(), 1);
}
