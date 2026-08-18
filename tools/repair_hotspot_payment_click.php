<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
@set_time_limit(60);

function rshp_line($message)
{
    echo '[hotspot-pay-fix] ' . trim((string) $message) . PHP_EOL;
}

function rshp_fail($message, $code = 1)
{
    fwrite(STDERR, '[hotspot-pay-fix] FAILED ' . preg_replace('/[\r\n]+/', ' ', (string) $message) . PHP_EOL);
    exit((int) $code);
}

$routerId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($routerId <= 0) {
    rshp_fail('Usage: php tools/repair_hotspot_payment_click.php ROUTER_ID', 2);
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
    rshp_fail('Bootstrap failed: ' . $e->getMessage());
}

function rshp_router_host($router)
{
    $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
    $wg = trim((string) ($router['wg_tunnel_ip'] ?? ''));
    if ($transport === 'wireguard' && filter_var($wg, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $wg;
    }
    return trim((string) ($router['ip_address'] ?? ''));
}

function rshp_client($router)
{
    $host = rshp_router_host($router);
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

function rshp_hotspot_directory($client)
{
    $profile = '';
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/print')) as $row) {
        $disabled = strtolower(trim((string) $row->getProperty('disabled')));
        if ($disabled === 'yes' || $disabled === 'true') {
            continue;
        }
        $profile = trim((string) $row->getProperty('profile'));
        if ($profile !== '') {
            break;
        }
    }
    $dir = 'hotspot';
    if ($profile !== '') {
        foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/ip/hotspot/profile/print')) as $row) {
            if ((string) $row->getProperty('name') !== $profile) {
                continue;
            }
            $candidate = trim((string) $row->getProperty('html-directory'));
            if ($candidate !== '') {
                $dir = trim($candidate, "/\\");
            }
            break;
        }
    }
    if ($dir === '' || strpos($dir, '..') !== false || !preg_match('/^[A-Za-z0-9._\/-]{1,96}$/', $dir)) {
        $dir = 'hotspot';
    }
    return $dir;
}

function rshp_files($client, $name)
{
    $out = [];
    foreach ($client->sendSync(new PEAR2\Net\RouterOS\Request('/file/print')) as $row) {
        if ((string) $row->getProperty('name') !== (string) $name) {
            continue;
        }
        $out[] = [
            'id' => trim((string) $row->getProperty('.id')),
            'size' => trim((string) $row->getProperty('size')),
        ];
    }
    return $out;
}

function rshp_fetch($client, $url, $destination)
{
    $req = new PEAR2\Net\RouterOS\Request('/tool/fetch');
    $req->setArgument('url', (string) $url)
        ->setArgument('dst-path', (string) $destination)
        ->setArgument('keep-result', 'yes');
    if (stripos((string) $url, 'https://') === 0) {
        $req->setArgument('mode', 'https')->setArgument('check-certificate', 'no');
    } else {
        $req->setArgument('mode', 'http');
    }
    $client->sendSync($req);
    usleep(500000);
}

function rshp_billing_source_url()
{
    $port = 80;
    if (defined('APP_URL')) {
        $p = parse_url((string) APP_URL);
        if (is_array($p) && !empty($p['port'])) {
            $port = (int) $p['port'];
        }
    }
    if ($port < 1 || $port > 65535) {
        $port = 80;
    }

    $serverIp = '10.78.0.1';
    try {
        if (class_exists('RSWireguardControlPlane') && method_exists('RSWireguardControlPlane', 'publicConfig')) {
            $cfg = RSWireguardControlPlane::publicConfig();
            $candidate = trim((string) ($cfg['server_ip'] ?? ''));
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $serverIp = $candidate;
            }
        }
    } catch (Throwable $ignored) {
    }

    return 'http://' . $serverIp . ':' . $port . '/hotspot_login.html?_payfix=' . time();
}

$patch = <<<'HTML'
<script id="rs-hotspot-pay-native-fallback">
(function(){
  if(window.__RS_HOTSPOT_PAY_FALLBACK_V1){return;}
  window.__RS_HOTSPOT_PAY_FALLBACK_V1=true;

  var original=window.handlePhoneNumberSubmission;

  function hasRealSwal(){
    try{
      return !!(window.Swal && typeof window.Swal.fire==='function' &&
        (typeof window.Swal.getPopup==='function' || typeof window.Swal.showValidationMessage==='function'));
    }catch(e){return false;}
  }

  function normalizePhone(raw){
    var p=String(raw||'').replace(/\D+/g,'');
    if(p.indexOf('0')===0){p='254'+p.substring(1);}
    else if(p.indexOf('7')===0 || p.indexOf('1')===0){p='254'+p;}
    return p;
  }

  function getOrCreateAccount(){
    var a='';
    try{if(typeof getCookie==='function'){a=String(getCookie('account_number')||'').trim();}}catch(e){}
    if(!a){
      try{if(typeof generateAccountNumber==='function'){a=String(generateAccountNumber()||'').trim();}}catch(e2){}
    }
    if(!a){a=String(Math.floor(10000+Math.random()*90000));}
    try{if(typeof setCookie==='function'){setCookie('account_number',a,365);}}catch(e3){}
    return a;
  }

  function startGrant(planId,routerId,rawPhone){
    if(typeof window.pamnetFetch!=='function'){
      window.alert('Payment service is unavailable on this page. Please reconnect to Wi-Fi and try again.');
      return Promise.resolve(false);
    }

    var phone=normalizePhone(rawPhone);
    if(!/^254(7|1)\d{8}$/.test(phone)){
      window.alert('Enter a valid Safaricom M-Pesa number, for example 0712345678 or 0112345678.');
      return Promise.resolve(false);
    }

    var account=getOrCreateAccount();
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

    return window.pamnetFetch('grant',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(payload)
    }).then(function(r){
      if(!r || !r.ok){throw new Error('Payment start failed (HTTP '+(r?r.status:0)+')');}
      return r.text().then(function(t){
        try{return JSON.parse(t);}catch(e){throw new Error('Billing server returned an invalid payment response');}
      });
    }).then(function(data){
      if(!data || data.status==='error'){
        throw new Error((data&&data.message)?String(data.message):'M-Pesa STK Push failed');
      }
      var newCode=String(data.username||data.account_number||account||'').trim();
      if(newCode){
        try{if(typeof setCookie==='function'){setCookie('account_number',newCode,365);}}catch(eC){}
        if(u){u.value=newCode;}
      }
      window.alert('M-Pesa PIN prompt sent to '+phone+'. Enter your PIN on the phone.');
      try{if(typeof checkPaymentStatus==='function'){checkPaymentStatus(phone);}}catch(eP){}
      return true;
    }).catch(function(err){
      window.alert('Could not start M-Pesa payment: '+((err&&err.message)?err.message:String(err||'Unknown error')));
      return false;
    });
  }

  window.handlePhoneNumberSubmission=function(planId,routerId,price){
    if(hasRealSwal() && typeof original==='function'){
      return original.apply(this,arguments);
    }
    var raw=window.prompt('Enter Your M-Pesa Number\nExample: 0712345678 or 0112345678','');
    if(raw===null){return false;}
    startGrant(planId,routerId,raw);
    return false;
  };
})();
</script>
HTML;

try {
    rshp_line('loading router=' . $routerId);
    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        throw new RuntimeException('Router not found.');
    }

    $portalPath = $root . '/hotspot_login.html';
    if (!is_file($portalPath)) {
        throw new RuntimeException('hotspot_login.html not found. Publish the portal once before applying this repair.');
    }

    $html = (string) file_get_contents($portalPath);
    if (strlen($html) < 500) {
        throw new RuntimeException('Existing hotspot_login.html is unexpectedly small.');
    }

    $marker = 'rs-hotspot-pay-native-fallback';
    if (strpos($html, $marker) === false) {
        $backup = $portalPath . '.before-payment-click-fix.' . date('YmdHis');
        if (!copy($portalPath, $backup)) {
            throw new RuntimeException('Could not create local portal backup.');
        }
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('#</body>#i', $patch . "\n</body>", $html, 1);
        } else {
            $html .= "\n" . $patch . "\n";
        }
        if (file_put_contents($portalPath, $html) === false) {
            throw new RuntimeException('Could not write repaired hotspot_login.html.');
        }
        $uploadCopy = $root . '/system/uploads/hotspot_login.html';
        if (is_dir(dirname($uploadCopy))) {
            @file_put_contents($uploadCopy, $html);
        }
        rshp_line('local portal patched backup=' . basename($backup) . ' bytes=' . strlen($html));
    } else {
        rshp_line('local portal already contains payment fallback marker');
    }

    rshp_line('connecting RouterOS API over ' . rshp_router_host($router));
    $client = rshp_client($router);
    $client->sendSync(new PEAR2\Net\RouterOS\Request('/system/identity/print'));
    $dir = rshp_hotspot_directory($client);
    $destination = $dir . '/login.html';
    $probe = $dir . '/rs-pay-probe-' . substr(sha1((string) microtime(true)), 0, 10) . '.html';
    $source = rshp_billing_source_url();

    rshp_line('probing portal source=' . preg_replace('#\?.*$#', '', $source));
    rshp_fetch($client, $source, $probe);
    $probeFiles = rshp_files($client, $probe);
    if (!$probeFiles || empty($probeFiles[0]['id']) || empty($probeFiles[0]['size']) || $probeFiles[0]['size'] === '0') {
        throw new RuntimeException('RouterOS could not download the repaired portal probe. Live login.html was not touched.');
    }
    rshp_line('probe downloaded size=' . $probeFiles[0]['size']);

    foreach (rshp_files($client, $destination) as $old) {
        if ($old['id'] === '') {
            continue;
        }
        $rm = new PEAR2\Net\RouterOS\Request('/file/remove');
        $rm->setArgument('numbers', $old['id']);
        $client->sendSync($rm);
    }

    rshp_fetch($client, $source, $destination);
    $live = rshp_files($client, $destination);
    if (!$live || empty($live[0]['size']) || $live[0]['size'] === '0') {
        throw new RuntimeException('RouterOS downloaded the probe but could not create the final login.html.');
    }

    foreach (rshp_files($client, $probe) as $tmp) {
        if ($tmp['id'] === '') {
            continue;
        }
        $rm = new PEAR2\Net\RouterOS\Request('/file/remove');
        $rm->setArgument('numbers', $tmp['id']);
        $client->sendSync($rm);
    }

    rshp_line('RESULT=HOTSPOT_PAYMENT_CLICK_REPAIRED router=' . $routerId . ' path=' . $destination . ' size=' . $live[0]['size']);
    rshp_line('NEXT=Reconnect captive portal, tap Buy Now, enter M-Pesa number, then verify that POST type=grant reaches port 8090.');
    exit(0);
} catch (Throwable $e) {
    rshp_fail($e->getMessage(), 1);
}
