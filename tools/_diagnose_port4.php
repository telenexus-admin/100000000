<?php
require dirname(__DIR__) . '/init.php';
use PEAR2\Net\RouterOS;
$r = ORM::for_table('nas')->find_one(8);
if (!$r) { fwrite(STDERR, "Router 8 not found\n"); exit(1); }
$c = Mikrotik::getClient($r);
$sets = [
  'BRIDGE' => ['/interface/bridge/print', ['name','running','protocol-mode','vlan-filtering','arp']],
  'BRIDGE_PORTS' => ['/interface/bridge/port/print', ['bridge','interface','disabled','pvid','ingress-filtering','frame-types']],
  'ETHER4' => ['/interface/ethernet/print', ['name','running','disabled','auto-negotiation','speed','full-duplex']],
  'DHCP_SERVER' => ['/ip/dhcp-server/print', ['name','interface','address-pool','disabled','authoritative']],
  'DHCP_NETWORK' => ['/ip/dhcp-server/network/print', ['address','gateway','dns-server','domain','comment']],
  'DHCP_LEASES' => ['/ip/dhcp-server/lease/print', ['address','mac-address','host-name','status','server','last-seen']],
  'DNS' => ['/ip/dns/print', ['servers','allow-remote-requests']],
  'HOTSPOT_SERVER' => ['/ip/hotspot/print', ['name','interface','address-pool','profile','disabled']],
  'HOTSPOT_PROFILE' => ['/ip/hotspot/profile/print', ['name','hotspot-address','dns-name','html-directory','login-by','use-radius']],
  'HOTSPOT_HOSTS' => ['/ip/hotspot/host/print', ['address','mac-address','server','authorized','bypassed','to-address']],
  'HOTSPOT_ACTIVE' => ['/ip/hotspot/active/print', ['user','address','mac-address','server','uptime']],
];
foreach ($sets as $label => [$path, $fields]) {
  echo "== $label ==\n";
  try {
    $n=0;
    foreach ($c->sendSync(new RouterOS\Request($path)) as $row) {
      if ($row->getType() !== RouterOS\Response::TYPE_DATA) continue;
      $vals=[];
      foreach ($fields as $f) { $v=$row->getProperty($f); if ($v !== null && $v !== '') $vals[]="$f=$v"; }
      echo implode(' ', $vals) . "\n";
      if (++$n >= 20) break;
    }
    if (!$n) echo "(none)\n";
  } catch (Throwable $e) { echo "ERROR: ".$e->getMessage()."\n"; }
}
PHP
sudo -u www-data php /var/www/prm-test/tools/_diagnose_port4.php
rm -f /var/www/prm-test/tools/_diagnose_port4.php
