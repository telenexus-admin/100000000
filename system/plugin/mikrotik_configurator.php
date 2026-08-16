<?php

/**
 * MikroTik Router Configurator Plugin
 * Provides functionality to configure MikroTik routers including hosts and PPPoE settings
 */


use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;

// Register plugin menu
register_menu("MikroTik Config", true, "mikrotik_configurator", 'AFTER_NETWORKS', 'ion ion-gear-a', '', '', ['SuperAdmin', 'Admin']);

function mikrotik_configurator_require_allowed_admin($json = false)
{
  _admin();
  $admin = Admin::_info();
  if (!in_array($admin['user_type'] ?? '', ['SuperAdmin', 'Admin'], true)) {
    if ($json) {
      if (ob_get_level()) {
        ob_clean();
      }
      header('Content-Type: application/json');
      echo json_encode([
        'status' => 'error',
        'message' => 'Access denied. Only Admin and SuperAdmin users can access this feature.',
      ], JSON_PRETTY_PRINT);
      exit;
    }

    r2(U . 'dashboard', 'e', Lang::T('You Do Not Have Access'));
  }

  // Release the session before RouterOS work. API calls can take seconds and must not
  // hold every other tab for this administrator.
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }

  return $admin;
}

function mikrotik_configurator_sync_timezone($client)
{
  $timezone = trim((string)date_default_timezone_get());
  if ($timezone === '') {
    return;
  }

  $request = new RouterOS\Request('/system/clock/set');
  $request->setArgument('time-zone-name', $timezone);
  $client->sendSync($request);
}

function mikrotik_configurator()
{
  global $ui, $routes;
  $admin = mikrotik_configurator_require_allowed_admin();
  $ui->assign('_title', 'MikroTik Router Configurator');
  $ui->assign('_system_menu', 'MikroTik Config');
  $ui->assign('_admin', $admin);
  $search = trim(_get('search', ''));
  $page = max(1, (int)_get('page', 1));
  $per_page = 20;

  $baseQuery = ORM::for_table('tbl_routers')->where('enabled', '1');
  if ($search !== '') {
    $baseQuery = $baseQuery->where_raw('(name LIKE ? OR ip_address LIKE ? OR username LIKE ?)', ["%$search%", "%$search%", "%$search%"]);
  }

  $total_routers = (int)$baseQuery->count();
  $total_pages = max(1, (int)ceil($total_routers / $per_page));
  if ($page > $total_pages) {
    $page = $total_pages;
  }
  $offset = ($page - 1) * $per_page;

  $dataQuery = ORM::for_table('tbl_routers')->where('enabled', '1');
  if ($search !== '') {
    $dataQuery = $dataQuery->where_raw('(name LIKE ? OR ip_address LIKE ? OR username LIKE ?)', ["%$search%", "%$search%", "%$search%"]);
  }

  $routers = $dataQuery->order_by_desc('id')->offset($offset)->limit($per_page)->find_many();

  $ui->assign('routers', $routers);
  $ui->assign('search', $search);
  $ui->assign('page', $page);
  $ui->assign('total_pages', $total_pages);
  $ui->assign('total_routers', $total_routers);
  $ui->display('mikrotik_configurator.tpl');
}




function mikrotik_configurator_random_subnet()
{
  $r = rand(0, 2);
  if ($r === 0) {
    return '10.' . rand(0, 255) . '.0.0/16';
  } elseif ($r === 1) {
    return '172.' . rand(16, 31) . '.0.0/16';
  } else {
    return '192.168.0.0/16';
  }
}

function mikrotik_configurator_config_ui()
{
  global $ui, $routes;
  $admin = mikrotik_configurator_require_allowed_admin();

  $router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;
  $router = ORM::for_table('tbl_routers')->find_one($router_id);
  if (!$router) {
    r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found');
  }

  // Generate two guaranteed-distinct /16 subnets server-side.
  // Hotspot gets one random range; PPPoE gets the next /16 after it.
  $hotspot_subnet = mikrotik_configurator_random_subnet();
  $pppoe_subnet   = mikrotik_configurator_next_cidr($hotspot_subnet, 1);

  $ui->assign('_admin', $admin);
  $ui->assign('router', $router);
  $ui->assign('hotspot_subnet', $hotspot_subnet);
  $ui->assign('pppoe_subnet', $pppoe_subnet);
  $ui->assign('auto_radius', (($router['management_transport'] ?? '') === 'wireguard') || _get('auto_radius', '') === '1');
  $configuredServices = array_values(array_intersect(['hotspot', 'pppoe'], explode(',', (string)_get('configured', ''))));
  $ui->assign('configured_services', $configuredServices);

  $ui->display('mikrotik_configurator_config_ui.tpl');
}

function mikrotik_configurator_config_process()
{
  mikrotik_configurator_require_allowed_admin();

  $router_id = isset($_POST['router_id']) ? intval($_POST['router_id']) : 0;
  $router = ORM::for_table('tbl_routers')->find_one($router_id);
  if (!$router) {
    r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found');
  }

  $serviceType = $_POST['serviceType'] ?? ($_POST['service_type'] ?? []);
  if (!is_array($serviceType)) {
    $serviceType = [$serviceType];
  }
  $serviceType = array_values(array_unique(array_filter(array_map('trim', $serviceType))));
  if (empty($serviceType)) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Please select at least one service type');
  }

  $selected_ports = $_POST['selected_ports'] ?? [];
  if (!is_array($selected_ports)) {
    $selected_ports = [$selected_ports];
  }
  $selected_ports = array_values(array_unique(array_filter(array_map('trim', $selected_ports))));
  if (empty($selected_ports)) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Please select at least one port');
  }

  $sameBridge = (($_POST['sameBridge'] ?? 'yes') === 'no') ? 'no' : 'yes';
  $bridge = trim($_POST['bridge'] ?? '');

  $bridge_pppoe = '';
  $bridge_hotspot = '';
  if ($sameBridge === 'yes') {
    $bridge_pppoe = $bridge;
    $bridge_hotspot = $bridge;
  } else {
    $bridge_pppoe = trim($_POST['bridge_pppoe'] ?? '');
    $bridge_hotspot = trim($_POST['bridge_hotspot'] ?? '');
  }

  if (in_array('hotspot', $serviceType, true) && $bridge_hotspot === '') {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Hotspot bridge is required');
  }
  if (in_array('pppoe', $serviceType, true) && $bridge_pppoe === '') {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'PPPoE bridge is required');
  }

  $subnet_hotspot = trim($_POST['subnet_hotspot'] ?? '');
  $subnet_pppoe   = trim($_POST['subnet_pppoe'] ?? '');

  // When same bridge is used, the form still posts both subnet_hotspot and
  // subnet_pppoe independently — no fallback to a shared subnet_bridge.
  $hotspot_subnet = $subnet_hotspot;
  $pppoe_subnet   = $subnet_pppoe;

  // If for any reason they are identical, bump pppoe to next /16.
  if ($hotspot_subnet !== '' && $pppoe_subnet !== '' && $hotspot_subnet === $pppoe_subnet) {
    $pppoe_subnet = mikrotik_configurator_next_cidr($hotspot_subnet, 1);
  }
  $pppoe_expired_subnet = mikrotik_configurator_next_cidr($pppoe_subnet, 1);

  if (in_array('hotspot', $serviceType, true) && !mikrotik_configurator_is_valid_cidr($hotspot_subnet)) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Invalid Hotspot subnet. Must be a /16 CIDR like 10.20.0.0/16');
  }
  if (in_array('pppoe', $serviceType, true) && !mikrotik_configurator_is_valid_cidr($pppoe_subnet)) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Invalid PPPoE subnet. Must be a /16 CIDR like 10.30.0.0/16');
  }

  $hotspot_ip_range = trim($_POST['hotspot_ip_range'] ?? '');
  if (in_array('hotspot', $serviceType, true) && $hotspot_ip_range === '') {
    $hotspot_ip_range = mikrotik_configurator_default_range_from_subnet($hotspot_subnet);
  }

  // Routers provisioned through automatic WireGuard onboarding always use
  // FreeRADIUS. Manual/API routers retain the legacy selectable behavior.
  $wireguardManaged = (($router['management_transport'] ?? '') === 'wireguard');
  $hotspot_auth_type = $wireguardManaged ? 'radius' : ($_POST['hotspot_auth_type'] ?? 'api');
  $pppoe_auth_type = $wireguardManaged ? 'radius' : ($_POST['pppoe_auth_type'] ?? 'api');
  $antiHotspotSharing = ($_POST['antiHotspotSharing'] ?? 'no') === 'yes' ? 'yes' : 'no';
  $hotspot_dns_name = trim($_POST['hotspot_dns_name'] ?? '');
  $hotspot_html_directory = trim($_POST['hotspot_html_directory'] ?? 'hotspot');
  if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $hotspot_html_directory) || strpos($hotspot_html_directory, '..') !== false) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Invalid Hotspot server directory.');
  }

  $hotspot_ports = [];
  $pppoe_ports = [];
  if ($sameBridge === 'yes') {
    $hotspot_ports = $selected_ports;
    $pppoe_ports = $selected_ports;
  } else {
    foreach ($selected_ports as $port) {
      $key = 'port_service_' . $port;
      $assign = $_POST[$key] ?? 'both';
      if ($assign === 'both' || $assign === 'hotspot') {
        $hotspot_ports[] = $port;
      }
      if ($assign === 'both' || $assign === 'pppoe') {
        $pppoe_ports[] = $port;
      }
    }
  }

  if (in_array('hotspot', $serviceType, true) && empty($hotspot_ports)) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Hotspot selected but no ports assigned to Hotspot');
  }
  if (in_array('pppoe', $serviceType, true) && empty($pppoe_ports)) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'PPPoE selected but no ports assigned to PPPoE');
  }

  ini_set('default_socket_timeout', 15);
  $postApplyNote = '';
  try {
    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
    mikrotik_configurator_sync_timezone($client);

    if (in_array('hotspot', $serviceType, true)) {
      mikrotik_configurator_apply_hotspot(
        $client,
        $router['name'],
        $bridge_hotspot,
        $hotspot_ports,
        $hotspot_subnet,
        $hotspot_ip_range,
        $hotspot_dns_name,
        $hotspot_auth_type,
        $antiHotspotSharing,
        $hotspot_html_directory
      );
    }
    if (function_exists('hotspot_settings_generate_login_html') && function_exists('hotspot_settings_store_login_html') && function_exists('hotspot_settings_upload_to_router')) {
      try {
        $html = hotspot_settings_generate_login_html();
        $stored = hotspot_settings_store_login_html($html, rtrim((string)APP_URL, '/'));
        $upload = hotspot_settings_upload_to_router($router_id, $stored['url']);
        $postApplyNote = $upload['ok'] ? ' Hotspot files: ' . $upload['message'] : ' Hotspot network configured, but file upload needs attention: ' . $upload['message'];
      } catch (Throwable $uploadError) {
        $postApplyNote = ' Hotspot network configured, but login.html could not be generated/uploaded: ' . $uploadError->getMessage();
      }
    }

    if (in_array('pppoe', $serviceType, true)) {
      mikrotik_configurator_apply_pppoe(
        $client,
        $router['name'],
        $bridge_pppoe,
        $pppoe_ports,
        $pppoe_subnet,
        $pppoe_expired_subnet,
        $pppoe_auth_type
      );
    }

    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id . '&configured=' . rawurlencode(implode(',', $serviceType)), 's', 'Configuration applied successfully.' . $postApplyNote);
  } catch (Throwable $e) {
    r2(U . 'plugin/mikrotik_configurator_config_ui&router_id=' . $router_id, 'e', 'Configuration failed: ' . $e->getMessage());
  }
}

function mikrotik_configurator_is_valid_cidr($cidr)
{
  if (!is_string($cidr) || strpos($cidr, '/') === false) {
    return false;
  }
  [$ip, $mask] = explode('/', $cidr, 2);
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    return false;
  }
  // Only /16 is supported — consistent subnets across hotspot and PPPoE pools
  return (int)$mask === 16;
}

function mikrotik_configurator_default_range_from_subnet($cidr)
{
  [$network, $mask] = explode('/', $cidr, 2);
  $mask = (int)$mask;
  $netLong = ip2long($network);
  if ($netLong === false) {
    return '10.10.0.10-10.10.255.254';
  }

  $maskBits = $mask === 0 ? 0 : ((0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF);
  $networkLong = $netLong & $maskBits;
  $broadcastLong = $networkLong | (~$maskBits & 0xFFFFFFFF);

  $startLong = $networkLong + 10;
  $endLong = $broadcastLong - 1;

  if ($startLong >= $endLong) {
    $startLong = $networkLong + 1;
    $endLong = $broadcastLong - 1;
  }

  return long2ip($startLong) . '-' . long2ip($endLong);
}

/**
 * Returns a PPPoE pool range starting at gateway+1 (network.2) to broadcast-1.
 * For 10.0.0.0/16 → gateway is 10.0.0.1, pool is 10.0.0.2-10.0.255.254
 */
function mikrotik_configurator_pppoe_range_from_subnet($cidr)
{
  [$network, $mask] = explode('/', $cidr, 2);
  $mask = (int)$mask;
  $netLong = ip2long($network);
  if ($netLong === false) {
    return '10.10.0.2-10.10.255.254';
  }

  $maskBits = $mask === 0 ? 0 : ((0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF);
  $networkLong = $netLong & $maskBits;
  $broadcastLong = $networkLong | (~$maskBits & 0xFFFFFFFF);

  // Start at network+2 (network+1 is gateway, so pool starts at +2)
  $startLong = $networkLong + 2;
  $endLong = $broadcastLong - 1;

  if ($startLong >= $endLong) {
    $startLong = $networkLong + 1;
    $endLong = $broadcastLong - 1;
  }

  return long2ip($startLong) . '-' . long2ip($endLong);
}

function mikrotik_configurator_next_cidr($cidr, $steps = 1)
{
  [$network, $mask] = explode('/', $cidr, 2);
  $mask = (int)$mask;
  $netLong = ip2long($network);
  if ($netLong === false) {
    return $cidr;
  }

  $blockSize = 1 << (32 - $mask);
  $nextLong = $netLong + ($blockSize * max(1, (int)$steps));

  return long2ip($nextLong) . '/' . $mask;
}

function mikrotik_configurator_cidr_gateway($cidr)
{
  [$network, $mask] = explode('/', $cidr, 2);
  $mask = (int)$mask;
  $netLong = ip2long($network);
  $maskBits = $mask === 0 ? 0 : ((0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF);
  $networkLong = $netLong & $maskBits;
  return long2ip($networkLong + 1) . '/' . $mask;
}

function mikrotik_configurator_ensure_bridge($client, $bridgeName)
{
  $req = new RouterOS\Request('/interface/bridge/print');
  foreach ($client->sendSync($req) as $item) {
    if ((string)$item->getProperty('name') === $bridgeName) {
      return;
    }
  }
  $add = new RouterOS\Request('/interface/bridge/add');
  $add->setArgument('name', $bridgeName);
  $client->sendSync($add);
}

function mikrotik_configurator_ensure_bridge_port($client, $bridgeName, $port)
{
  $req = new RouterOS\Request('/interface/bridge/port/print');
  foreach ($client->sendSync($req) as $item) {
    if ((string)$item->getProperty('interface') === $port) {
      if ((string)$item->getProperty('bridge') === $bridgeName) {
        return; // already in the correct bridge
      }
      // Port is enslaved to a different bridge — remove it first so we can
      // add it to the intended bridge without a router error.
      $remove = new RouterOS\Request('/interface/bridge/port/remove');
      $remove->setArgument('numbers', (string)$item->getProperty('.id'));
      $client->sendSync($remove);
      break;
    }
  }

  $add = new RouterOS\Request('/interface/bridge/port/add');
  $add->setArgument('bridge', $bridgeName)->setArgument('interface', $port);
  $client->sendSync($add);
}

function mikrotik_configurator_ensure_ip_address($client, $addressCidr, $interface, $comment = '')
{
  $req = new RouterOS\Request('/ip/address/print');
  foreach ($client->sendSync($req) as $item) {
    if ((string)$item->getProperty('address') === $addressCidr && (string)$item->getProperty('interface') === $interface) {
      if ($comment !== '' && (string)$item->getProperty('comment') !== $comment) {
        $set = new RouterOS\Request('/ip/address/set');
        $set->setArgument('numbers', (string)$item->getProperty('.id'))
          ->setArgument('comment', $comment);
        $client->sendSync($set);
      }
      return;
    }
  }
  $add = new RouterOS\Request('/ip/address/add');
  $add->setArgument('address', $addressCidr)->setArgument('interface', $interface);
  if ($comment !== '') {
    $add->setArgument('comment', $comment);
  }
  $client->sendSync($add);
}

function mikrotik_configurator_ensure_pool($client, $poolName, $ranges, $comment = '')
{
  $req = new RouterOS\Request('/ip/pool/print');
  foreach ($client->sendSync($req) as $item) {
    if ((string)$item->getProperty('name') === $poolName) {
      if ((string)$item->getProperty('ranges') !== $ranges || ($comment !== '' && (string)$item->getProperty('comment') !== $comment)) {
        $set = new RouterOS\Request('/ip/pool/set');
        $set->setArgument('numbers', (string)$item->getProperty('.id'))
          ->setArgument('ranges', $ranges);
        if ($comment !== '') {
          $set->setArgument('comment', $comment);
        }
        $client->sendSync($set);
      }
      return;
    }
  }
  $add = new RouterOS\Request('/ip/pool/add');
  $add->setArgument('name', $poolName)->setArgument('ranges', $ranges);
  if ($comment !== '') {
    $add->setArgument('comment', $comment);
  }
  $client->sendSync($add);
}

function mikrotik_configurator_upsert_system_pool($routerName, $poolName, $ranges, $localIp = '', array $legacyNames = [])
{
  $pool = ORM::for_table('tbl_pool')
    ->where('pool_name', $poolName)
    ->where('routers', $routerName)
    ->find_one();

  if (!$pool) {
    foreach ($legacyNames as $legacyName) {
      $pool = ORM::for_table('tbl_pool')
        ->where('pool_name', $legacyName)
        ->where('routers', $routerName)
        ->find_one();
      if ($pool) {
        break;
      }
    }
  }

  if (!$pool) {
    $pool = ORM::for_table('tbl_pool')->create();
    $pool->routers = $routerName;
  }

  $pool->pool_name = $poolName;
  $pool->range_ip = $ranges;
  $pool->local_ip = $localIp;
  $pool->save();
}

function mikrotik_configurator_ensure_ppp_profile($client, $profileName, array $arguments)
{
  $profilePrint = new RouterOS\Request('/ppp/profile/print');
  foreach ($client->sendSync($profilePrint) as $item) {
    if ((string)$item->getProperty('name') === $profileName) {
      $set = new RouterOS\Request('/ppp/profile/set');
      $set->setArgument('numbers', (string)$item->getProperty('.id'));
      foreach ($arguments as $key => $value) {
        $set->setArgument($key, $value);
      }
      $client->sendSync($set);
      return;
    }
  }

  $add = new RouterOS\Request('/ppp/profile/add');
  $add->setArgument('name', $profileName);
  foreach ($arguments as $key => $value) {
    $add->setArgument($key, $value);
  }
  $client->sendSync($add);
}

function mikrotik_configurator_remove_ppp_profile($client, $profileName)
{
  $profilePrint = new RouterOS\Request('/ppp/profile/print');
  foreach ($client->sendSync($profilePrint) as $item) {
    if ((string)$item->getProperty('name') === $profileName) {
      $remove = new RouterOS\Request('/ppp/profile/remove');
      $remove->setArgument('numbers', (string)$item->getProperty('.id'));
      $client->sendSync($remove);
      return;
    }
  }
}

/**
 * Ensures a srcnat masquerade rule exists for the given src-address.
 * This is required for PPPoE clients to reach the internet via the router's WAN IP.
 */
function mikrotik_configurator_ensure_nat_masquerade($client, $srcAddress, $comment = '')
{
  $req = new RouterOS\Request('/ip/firewall/nat/print');
  foreach ($client->sendSync($req) as $item) {
    if ((string)$item->getProperty('chain') === 'srcnat' &&
      (string)$item->getProperty('src-address') === $srcAddress &&
      (string)$item->getProperty('action') === 'masquerade') {
      return; // Rule already exists
    }
  }
  $add = new RouterOS\Request('/ip/firewall/nat/add');
  $add->setArgument('chain', 'srcnat')
    ->setArgument('src-address', $srcAddress)
    ->setArgument('action', 'masquerade');
  if ($comment !== '') {
    $add->setArgument('comment', $comment);
  }
  $client->sendSync($add);
}

function mikrotik_configurator_ensure_antisharing_rule($client, $interfaceName, $comment)
{
  $print = new RouterOS\Request('/ip/firewall/mangle/print');
  foreach ($client->sendSync($print) as $item) {
    $sameComment = (string)$item->getProperty('comment') === $comment;
    $sameRule = (string)$item->getProperty('chain') === 'postrouting'
      && (string)$item->getProperty('action') === 'change-ttl'
      && (string)$item->getProperty('out-interface') === $interfaceName;
    if (!$sameComment && !$sameRule) {
      continue;
    }

    $set = new RouterOS\Request('/ip/firewall/mangle/set');
    $set->setArgument('numbers', (string)$item->getProperty('.id'))
      ->setArgument('chain', 'postrouting')
      ->setArgument('action', 'change-ttl')
      ->setArgument('new-ttl', 'set:64')
      ->setArgument('out-interface', $interfaceName)
      ->setArgument('passthrough', 'yes')
      ->setArgument('comment', $comment)
      ->setArgument('disabled', 'no');
    $client->sendSync($set);
    return;
  }

  $add = new RouterOS\Request('/ip/firewall/mangle/add');
  $add->setArgument('chain', 'postrouting')
    ->setArgument('action', 'change-ttl')
    ->setArgument('new-ttl', 'set:64')
    ->setArgument('out-interface', $interfaceName)
    ->setArgument('passthrough', 'yes')
    ->setArgument('comment', $comment)
    ->setArgument('disabled', 'no');
  $client->sendSync($add);
}

function mikrotik_configurator_remove_antisharing_rule($client, $interfaceName, $comment)
{
  $print = new RouterOS\Request('/ip/firewall/mangle/print');
  foreach ($client->sendSync($print) as $item) {
    $sameComment = (string)$item->getProperty('comment') === $comment;
    $sameRule = (string)$item->getProperty('chain') === 'postrouting'
      && (string)$item->getProperty('action') === 'change-ttl'
      && (string)$item->getProperty('out-interface') === $interfaceName;
    if (!$sameComment && !$sameRule) {
      continue;
    }

    $remove = new RouterOS\Request('/ip/firewall/mangle/remove');
    $remove->setArgument('numbers', (string)$item->getProperty('.id'));
    $client->sendSync($remove);
  }
}

function mikrotik_configurator_remove_hotspot_user_profile($client, $profileName)
{
  $print = new RouterOS\Request('/ip/hotspot/user/profile/print');
  foreach ($client->sendSync($print) as $item) {
    if ((string)$item->getProperty('name') === $profileName) {
      $remove = new RouterOS\Request('/ip/hotspot/user/profile/remove');
      $remove->setArgument('numbers', (string)$item->getProperty('.id'));
      $client->sendSync($remove);
      return;
    }
  }
}

function mikrotik_configurator_remove_hotspot_profile($client, $profileName)
{
  $print = new RouterOS\Request('/ip/hotspot/profile/print');
  foreach ($client->sendSync($print) as $item) {
    if ((string)$item->getProperty('name') === $profileName) {
      $remove = new RouterOS\Request('/ip/hotspot/profile/remove');
      $remove->setArgument('numbers', (string)$item->getProperty('.id'));
      $client->sendSync($remove);
      return;
    }
  }
}

function mikrotik_configurator_ensure_walled_garden_hosts($client, array $hosts)
{
  $hosts = array_values(array_filter(array_unique(array_map('trim', $hosts))));
  if (empty($hosts)) {
    return;
  }

  $existing = [];
  $print = new RouterOS\Request('/ip/hotspot/walled-garden/print');
  foreach ($client->sendSync($print) as $item) {
    $existing[] = trim((string)$item->getProperty('dst-host'));
  }

  foreach ($hosts as $host) {
    if ($host === '' || in_array($host, $existing, true)) {
      continue;
    }

    $add = new RouterOS\Request('/ip/hotspot/walled-garden/add');
    $add->setArgument('action', 'allow')
      ->setArgument('dst-host', $host)
      ->setArgument('disabled', 'no');
    $client->sendSync($add);
  }

  $existingIp = [];
  $ipPrint = new RouterOS\Request('/ip/hotspot/walled-garden/ip/print');
  foreach ($client->sendSync($ipPrint) as $item) {
    $existingIp[] = trim((string)$item->getProperty('dst-host'));
  }

  foreach ($hosts as $host) {
    if ($host === '' || in_array($host, $existingIp, true)) {
      continue;
    }

    $add = new RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
    $add->setArgument('action', 'accept')
      ->setArgument('dst-host', $host)
      ->setArgument('disabled', 'no');
    $client->sendSync($add);
  }
}

function mikrotik_configurator_get_main_domain($host)
{
  $host = strtolower(trim((string)$host));
  $host = preg_replace('/^www\./i', '', $host);
  if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
    return $host;
  }

  $parts = array_values(array_filter(explode('.', $host), 'strlen'));
  $count = count($parts);
  if ($count <= 2) {
    return $host;
  }

  $secondLevelMarkers = ['co', 'com', 'org', 'net', 'gov', 'ac', 'or'];
  $last = $parts[$count - 1];
  $penultimate = $parts[$count - 2];
  $thirdFromEnd = $parts[$count - 3];

  if (strlen($last) === 2 && strlen($penultimate) <= 3 && in_array($penultimate, $secondLevelMarkers, true) && $count >= 3) {
    return $thirdFromEnd . '.' . $penultimate . '.' . $last;
  }

  return $penultimate . '.' . $last;
}

function mikrotik_configurator_apply_hotspot($client, $routerName, $bridgeName, $ports, $subnet, $ipRange, $dnsName, $authType, $antiSharing, $htmlDirectory = 'hotspot')
{
  mikrotik_configurator_ensure_bridge($client, $bridgeName);
  foreach ($ports as $port) {
    mikrotik_configurator_ensure_bridge_port($client, $bridgeName, $port);
  }

  $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
  $gatewayIp = explode('/', $gatewayCidr, 2)[0];
  $poolName = $bridgeName . '-hotspot-pool';
  $profileName = $bridgeName . '-Profile';
  $serverName = $bridgeName . '-Server';
  $dhcpName = $bridgeName . '-dhcp';
  $userProfile = $bridgeName . '-UserProfile';
  $addressComment = 'Hotspot Gateway - ' . $bridgeName;
  $poolComment = 'Hotspot Pool - ' . $bridgeName;
  $dhcpComment = 'Hotspot DHCP - ' . $bridgeName;
  $profileComment = 'Hotspot Profile - ' . $bridgeName;
  $serverComment = 'Hotspot Server - ' . $bridgeName;
  // Do NOT include 'trial' (grants free internet without authentication) or
  // 'mac'/'mac-cookie' (silently re-auth devices that no longer have active packages).
  // cookie lifetime 3d: active users are never interrupted mid-session.
  // Expired users are kicked promptly by expire_hotspot_now.php (runs every minute)
  // which calls forceCaptivePortal() to explicitly delete the cookie.
  $profileLoginBy = 'http-pap,http-chap,cookie';
  $cookieLifetime = '3d';
  $addressesPerMac = $antiSharing === 'yes' ? '1' : '2';
  $antiSharingComment = 'Hotspot Anti-Sharing - ' . $bridgeName;
  $billingHost = parse_url(APP_URL, PHP_URL_HOST);
  $billingHost = $billingHost ?: ($_SERVER['HTTP_HOST'] ?? '');
  $billingHost = preg_replace('/^www\./i', '', trim((string)$billingHost));
  $mainDomain = mikrotik_configurator_get_main_domain($billingHost);
  $walledGardenHosts = [];
  if ($billingHost !== '') {
    $walledGardenHosts[] = $billingHost;
  }
  if ($mainDomain !== '') {
    $walledGardenHosts[] = '*.' . $mainDomain;
  }
  if (function_exists('pamnet_walled_garden_hosts')) {
    $walledGardenHosts = array_values(array_unique(array_merge(
      $walledGardenHosts,
      pamnet_walled_garden_hosts($billingHost)
    )));
  }

  mikrotik_configurator_ensure_ip_address($client, $gatewayCidr, $bridgeName, $addressComment);
  mikrotik_configurator_ensure_pool($client, $poolName, $ipRange, $poolComment);
  // Save hotspot pool to the system pool table so the billing app can reference it
  mikrotik_configurator_upsert_system_pool($routerName, $poolName, $ipRange, $gatewayIp, [$bridgeName . '-Pool']);

  // DNS allow remote requests
  $dnsSet = new RouterOS\Request('/ip/dns/set');
  $dnsSet->setArgument('allow-remote-requests', 'yes');
  $client->sendSync($dnsSet);

  // DHCP server
  $dhcpPrint = new RouterOS\Request('/ip/dhcp-server/print');
  $hasDhcp = false;
  foreach ($client->sendSync($dhcpPrint) as $item) {
    if ((string)$item->getProperty('name') === $dhcpName) {
      $hasDhcp = true;
      break;
    }
  }
  if (!$hasDhcp) {
    $dhcpAdd = new RouterOS\Request('/ip/dhcp-server/add');
    $dhcpAdd->setArgument('name', $dhcpName)
      ->setArgument('interface', $bridgeName)
      ->setArgument('address-pool', $poolName)
      ->setArgument('comment', $dhcpComment)
      ->setArgument('disabled', 'no');
    $client->sendSync($dhcpAdd);
  }

  // DHCP network
  $dhcpNetPrint = new RouterOS\Request('/ip/dhcp-server/network/print');
  $hasDhcpNet = false;
  foreach ($client->sendSync($dhcpNetPrint) as $item) {
    if ((string)$item->getProperty('address') === $subnet) {
      $hasDhcpNet = true;
      break;
    }
  }
  if (!$hasDhcpNet) {
    $dhcpNetAdd = new RouterOS\Request('/ip/dhcp-server/network/add');
    $dhcpNetAdd->setArgument('address', $subnet)
      ->setArgument('gateway', $gatewayIp)
      ->setArgument('dns-server', $gatewayIp)
      ->setArgument('comment', 'Hotspot DHCP Network - ' . $bridgeName);
    $client->sendSync($dhcpNetAdd);
  }

  // Dedicated hotspot profile for this hotspot server.
  $profilePrint = new RouterOS\Request('/ip/hotspot/profile/print');
  $hasProfile = false;
  foreach ($client->sendSync($profilePrint) as $item) {
    if ((string)$item->getProperty('name') === $profileName) {
      $hasProfile = true;
      $profileSet = new RouterOS\Request('/ip/hotspot/profile/set');
      $profileSet->setArgument('numbers', (string)$item->getProperty('.id'))
        ->setArgument('hotspot-address', $gatewayIp)
        ->setArgument('dns-name', $dnsName !== '' ? $dnsName : 'hotspot.local')
        ->setArgument('login-by', $profileLoginBy)
        ->setArgument('http-cookie-lifetime', $cookieLifetime)
        ->setArgument('mac-auth-mode', 'mac-as-username')
        ->setArgument('html-directory', $htmlDirectory)
        ->setArgument('use-radius', $authType === 'radius' ? 'yes' : 'no');
      $client->sendSync($profileSet);
      break;
    }
  }
  if (!$hasProfile) {
    $profileAdd = new RouterOS\Request('/ip/hotspot/profile/add');
    $profileAdd->setArgument('name', $profileName)
      ->setArgument('hotspot-address', $gatewayIp)
      ->setArgument('dns-name', $dnsName !== '' ? $dnsName : 'hotspot.local')
      ->setArgument('login-by', $profileLoginBy)
      ->setArgument('http-cookie-lifetime', $cookieLifetime)
      ->setArgument('mac-auth-mode', 'mac-as-username')
      ->setArgument('html-directory', $htmlDirectory)
      ->setArgument('use-radius', $authType === 'radius' ? 'yes' : 'no');
    $client->sendSync($profileAdd);
  }

  // Hotspot server
  $serverPrint = new RouterOS\Request('/ip/hotspot/print');
  $hasServer = false;
  foreach ($client->sendSync($serverPrint) as $item) {
    if ((string)$item->getProperty('name') === $serverName) {
      $hasServer = true;
      $serverSet = new RouterOS\Request('/ip/hotspot/set');
      $serverSet->setArgument('numbers', (string)$item->getProperty('.id'))
        ->setArgument('interface', $bridgeName)
        ->setArgument('address-pool', $poolName)
        ->setArgument('profile', $profileName)
        ->setArgument('addresses-per-mac', $addressesPerMac)
        ->setArgument('disabled', 'no');
      $client->sendSync($serverSet);
      break;
    }
  }
  if (!$hasServer) {
    $serverAdd = new RouterOS\Request('/ip/hotspot/add');
    $serverAdd->setArgument('name', $serverName)
      ->setArgument('interface', $bridgeName)
      ->setArgument('address-pool', $poolName)
      ->setArgument('profile', $profileName)
      ->setArgument('addresses-per-mac', $addressesPerMac)
      ->setArgument('disabled', 'no');
    $client->sendSync($serverAdd);
  }

  // Remove previously created configurator hotspot user profiles. They were not
  // attached to hotspot users or plans, so anti-sharing never actually applied.
  mikrotik_configurator_remove_hotspot_user_profile($client, $userProfile);

  // NAT masquerade so hotspot clients can reach the internet
  mikrotik_configurator_ensure_nat_masquerade($client, $subnet, 'Hotspot-Masquerade');
  mikrotik_configurator_ensure_walled_garden_hosts($client, $walledGardenHosts);
  // Reject DNS-over-TLS (port 853) and ensure port-53 is accepted so Android
  // correctly detects the captive portal instead of showing "Private DNS error".
  if (function_exists('pamnet_ensure_hotspot_firewall_rules')) {
    pamnet_ensure_hotspot_firewall_rules($client, $bridgeName);
  }

  if ($antiSharing === 'yes') {
    mikrotik_configurator_ensure_antisharing_rule($client, $bridgeName, $antiSharingComment);
  } else {
    mikrotik_configurator_remove_antisharing_rule($client, $bridgeName, $antiSharingComment);
  }

  // RouterOS can return a command trap as a response object without throwing.
  // Do not allow the UI to claim success until the two core Hotspot objects
  // are visible on the router.
  $profileVerified = false;
  foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $item) {
    if ((string)$item->getProperty('name') === $profileName) {
      $profileVerified = true;
      break;
    }
  }
  if (!$profileVerified) {
    throw new RuntimeException('RouterOS did not create Hotspot profile ' . $profileName . '.');
  }

  $serverVerified = false;
  foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $item) {
    if ((string)$item->getProperty('name') === $serverName
      && (string)$item->getProperty('interface') === $bridgeName) {
      $serverVerified = true;
      break;
    }
  }
  if (!$serverVerified) {
    throw new RuntimeException('RouterOS did not create Hotspot server ' . $serverName . '.');
  }
}

function mikrotik_configurator_apply_pppoe($client, $routerName, $bridgeName, $ports, $subnet, $expiredSubnet, $authType)
{
  mikrotik_configurator_ensure_bridge($client, $bridgeName);
  foreach ($ports as $port) {
    mikrotik_configurator_ensure_bridge_port($client, $bridgeName, $port);
  }

  $gatewayCidr = mikrotik_configurator_cidr_gateway($subnet);
  $gatewayIp = explode('/', $gatewayCidr, 2)[0];
  $poolName = $bridgeName . '-pppoe-active-pool';
  $expiredPoolName = $bridgeName . '-pppoe-expired-pool';
  $profileName = $bridgeName . '-pppoe-profile';
  $expiredProfileName = $bridgeName . '-pppoe-expired';
  $serverName = $bridgeName . '-pppoe-server';
  $addressComment = 'PPPoE Gateway - ' . $bridgeName;
  $activePoolComment = 'PPPoE Active Pool - ' . $bridgeName;
  $expiredPoolComment = 'PPPoE Expired Pool - ' . $bridgeName;
  $serverAuthentication = $authType === 'radius' ? 'pap,chap,mschap1,mschap2' : 'pap';

  // PPPoE pool ranges start at gateway+1 (network.2), not network+10
  $poolRange = mikrotik_configurator_pppoe_range_from_subnet($subnet);
  $expiredPoolRange = mikrotik_configurator_pppoe_range_from_subnet($expiredSubnet);

  mikrotik_configurator_ensure_ip_address($client, $gatewayCidr, $bridgeName, $addressComment);
  mikrotik_configurator_ensure_pool($client, $poolName, $poolRange, $activePoolComment);
  mikrotik_configurator_ensure_pool($client, $expiredPoolName, $expiredPoolRange, $expiredPoolComment);
  mikrotik_configurator_upsert_system_pool($routerName, $poolName, $poolRange, $gatewayIp);
  mikrotik_configurator_upsert_system_pool($routerName, $expiredPoolName, $expiredPoolRange, $gatewayIp);

  mikrotik_configurator_remove_ppp_profile($client, $profileName);
  // Expired profile: throttled, same local-address so all PPPoE sessions share
  // the same gateway regardless of active/expired status.
  mikrotik_configurator_ensure_ppp_profile($client, $expiredProfileName, [
    'comment' => 'PPPoE Expired Profile - ' . $bridgeName,
    'local-address' => $gatewayIp,
    'remote-address' => $expiredPoolName,
    'only-one' => 'yes',
    'rate-limit' => '512k/512k',
  ]);

  // NAT masquerade so PPPoE clients can reach the internet.
  // Separate rules for active and expired subnets allow per-subnet firewall policy.
  mikrotik_configurator_ensure_nat_masquerade($client, $subnet, 'PPPoE-Active-Masquerade');
  mikrotik_configurator_ensure_nat_masquerade($client, $expiredSubnet, 'PPPoE-Expired-Masquerade');

  // PPPoE server
  $serverPrint = new RouterOS\Request('/interface/pppoe-server/server/print');
  $hasServer = false;
  foreach ($client->sendSync($serverPrint) as $item) {
    if ((string)$item->getProperty('service-name') === $serverName || (string)$item->getProperty('interface') === $bridgeName) {
      $hasServer = true;
      $existingDefaultProfile = trim((string)$item->getProperty('default-profile'));
      if ($existingDefaultProfile === '' || $existingDefaultProfile === $profileName) {
        $existingDefaultProfile = 'default';
      }
      $serverSet = new RouterOS\Request('/interface/pppoe-server/server/set');
      $serverSet->setArgument('numbers', (string)$item->getProperty('.id'))
        ->setArgument('service-name', $serverName)
        ->setArgument('interface', $bridgeName)
        ->setArgument('default-profile', $existingDefaultProfile)
        ->setArgument('authentication', $serverAuthentication)
        ->setArgument('one-session-per-host', 'yes')
        ->setArgument('disabled', 'no');
      $client->sendSync($serverSet);
      break;
    }
  }
  if (!$hasServer) {
    $serverAdd = new RouterOS\Request('/interface/pppoe-server/server/add');
    $serverAdd->setArgument('service-name', $serverName)
      ->setArgument('interface', $bridgeName)
      ->setArgument('default-profile', 'default')
      ->setArgument('authentication', $serverAuthentication)
      ->setArgument('one-session-per-host', 'yes')
      ->setArgument('disabled', 'no');
    $client->sendSync($serverAdd);
  }
}




function mikrotik_configurator_manage_pools()
{
  global $ui;
  $admin = mikrotik_configurator_require_allowed_admin();

  $router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;
  $router = ORM::for_table('tbl_routers')->find_one($router_id);
  if (!$router) {
    r2(U . 'plugin/mikrotik_configurator', 'e', 'Router not found');
  }

  $pools = ORM::for_table('tbl_pool')
    ->where('routers', $router['name'])
    ->order_by_asc('pool_name')
    ->find_many();

  $ui->assign('_title', 'Manage Pools — ' . $router['name']);
  $ui->assign('_system_menu', 'MikroTik Config');
  $ui->assign('_admin', $admin);
  $ui->assign('router', $router);
  $ui->assign('pools', $pools);
  $ui->display('mikrotik_configurator_pools.tpl');
}

/**
 * AJAX: Regenerate a pool's IP range on MikroTik and update the local DB.
 *
 * - For expired pools (name contains 'expired'): auto-calculates the next /16 block
 *   after the sibling active pool so there is never an overlap.
 * - For all other pools: recalculates the full range from the pool's current /16
 *   subnet (gateway stays the same, range just gets reset to gateway+1 → broadcast-1).
 */
function mikrotik_configurator_do_regenerate_pool()
{
  mikrotik_configurator_require_allowed_admin(true);
  if (ob_get_level()) ob_clean();
  header('Content-Type: application/json');

  $router_id = isset($_POST['router_id']) ? intval($_POST['router_id']) : 0;
  $pool_id   = isset($_POST['pool_id'])   ? intval($_POST['pool_id'])   : 0;

  $router = ORM::for_table('tbl_routers')->find_one($router_id);
  if (!$router) {
    echo json_encode(['status' => 'error', 'message' => 'Router not found']);
    exit;
  }

  $pool = ORM::for_table('tbl_pool')->find_one($pool_id);
  if (!$pool || $pool['routers'] !== $router['name']) {
    echo json_encode(['status' => 'error', 'message' => 'Pool not found for this router']);
    exit;
  }

  try {
    $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);

    $poolName = $pool['pool_name'];
    $isExpired = stripos($poolName, 'expired') !== false;

    if ($isExpired) {
      // Find the active PPPoE sibling pool for this router
      $activePool = ORM::for_table('tbl_pool')
        ->where('routers', $router['name'])
        ->where_raw('pool_name NOT LIKE ?', ['%expired%'])
        ->where_raw('pool_name LIKE ?', ['%pppoe%'])
        ->find_one();

      $activeStart = '10.0.0.2'; // fallback
      if ($activePool) {
        $parts = explode('-', (string)$activePool['range_ip']);
        $activeStart = $parts[0] ?? $activeStart;
      }

      // Expired pool = next /16 after active pool
      $ipParts = explode('.', $activeStart);
      $newSubnet = ($ipParts[0] ?? '10') . '.' . (((int)($ipParts[1] ?? 0)) + 1) . '.0.0/16';
      $newRange  = mikrotik_configurator_pppoe_range_from_subnet($newSubnet);
    } else {
      // Active/hotspot pools: rebuild range from existing subnet
      $currentStart = explode('-', (string)$pool['range_ip'])[0] ?? '10.0.0.2';
      $ipParts  = explode('.', $currentStart);
      $subnet   = ($ipParts[0] ?? '10') . '.' . ($ipParts[1] ?? '0') . '.0.0/16';
      $isHotspot = stripos($poolName, 'pppoe') === false;
      $newRange  = $isHotspot
        ? mikrotik_configurator_default_range_from_subnet($subnet)
        : mikrotik_configurator_pppoe_range_from_subnet($subnet);
    }

    mikrotik_configurator_ensure_pool($client, $poolName, $newRange);
    $pool->range_ip = $newRange;
    $pool->save();

    echo json_encode([
      'status'    => 'success',
      'message'   => 'Pool regenerated successfully',
      'new_range' => $newRange,
    ]);
  } catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
  }
  exit;
}

function mikrotik_configurator_connection($router_id)
{
  $router = ORM::for_table('tbl_routers')->find_one($router_id);
  if ($router) {
    try {
      $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
      $identity = $client->sendSync(new Request('/system/identity/print'));
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }
  return false;
}



function mikrotik_configurator_get_mikrotik_port()
{
  mikrotik_configurator_require_allowed_admin(true);

  // Clean any previous output
  if (ob_get_level()) ob_clean();
  
  header('Content-Type: application/json');
  
  try {
    // Check authentication for API endpoint
  $router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : null;
  if (!$router_id) {
    echo json_encode([
      'status'  => 'error',
      'message' => 'Invalid or missing router ID.'
    ], JSON_PRETTY_PRINT);
      exit;
    }

    // Fetch router info from DB
    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if (!$router) {
      echo json_encode([
        'status'  => 'error',
        'message' => 'Router not found.'
      ], JSON_PRETTY_PRINT);
      exit;
    }

    // Connect to the MikroTik router (8s timeout so we don't hang Apache)
    ini_set('default_socket_timeout', 8);
    $client = Mikrotik::getClient(
      $router['ip_address'],
      $router['username'],
      $router['password']
    );

    // Detect the active WAN from bound DHCP clients so automatic onboarding
    // never offers the internet uplink as a Hotspot/PPPoE customer port.
    $wanInterfaces = [];
    try {
      $dhcpResponse = $client->sendSync(new RouterOS\Request('/ip/dhcp-client/print'));
      foreach ($dhcpResponse as $dhcp) {
        $iface = trim((string)$dhcp->getProperty('interface'));
        $status = trim((string)$dhcp->getProperty('status'));
        if ($iface !== '' && $status === 'bound') {
          $wanInterfaces[$iface] = true;
        }
      }
    } catch (Throwable $ignored) {
    }

    // Fetch all interfaces
    $response = $client->sendSync(new RouterOS\Request('/interface/print'));

    // Format the data nicely
    $interfaces = [];
    foreach ($response as $item) {
      $name = trim((string)$item->getProperty('name'));
      if ($name === '') {
        continue;
      }
      $interfaces[] = [
        'name'       => $name,
        'type'       => $item->getProperty('type'),
        'mac_address' => $item->getProperty('mac-address'),
        'running'    => $item->getProperty('running') === 'true',
        'disabled'   => $item->getProperty('disabled') === 'true',
        'comment'    => $item->getProperty('comment') ?: null,
        'wan'        => isset($wanInterfaces[$name]),
        'management' => in_array((string)$item->getProperty('type'), ['wg', 'wireguard', 'ovpn-out'], true)
      ];
    }

    echo json_encode([
      'status' => 'success',
      'count'  => count($interfaces),
      'data'   => $interfaces
    ], JSON_PRETTY_PRINT);
  } catch (Throwable $e) {
    echo json_encode([
      'status'  => 'error',
      'message' => 'Connection failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
  } catch (Throwable $e) {
    echo json_encode([
      'status'  => 'error',
      'message' => 'System error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
  }
  exit;
}


function mikrotik_configurator_wireguard_peer_is_online($tunnelIp)
{
  $tunnelIp = trim((string)$tunnelIp);
  if (!preg_match('/^10\\.78\\.0\\.(?:[2-9]|[1-9][0-9]|[12][0-9][0-9])$/', $tunnelIp)) {
    return false;
  }

  $output = [];
  $code = 1;
  exec('sudo -n /usr/local/bin/rs-wireguard-peer-status ' . escapeshellarg($tunnelIp) . ' 2>/dev/null', $output, $code);
  return $code === 0;
}
function mikrotik_configurator_check_status()
{
  mikrotik_configurator_require_allowed_admin(true);

  // Clean any previous output
  if (ob_get_level()) ob_clean();
  
  header('Content-Type: application/json');
  
  try {
    // Check authentication for API endpoint
  $router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : null;
  if (!$router_id) {
    echo json_encode([
      'status'  => 'error',
      'message' => 'Invalid or missing router ID.',
      'online'  => false
    ], JSON_PRETTY_PRINT);
      exit;
    }

    // Fetch router info from DB
    $router = ORM::for_table('tbl_routers')->find_one($router_id);
    if (!$router) {
      echo json_encode([
        'status'  => 'error',
        'message' => 'Router not found.',
        'online'  => false
      ], JSON_PRETTY_PRINT);
      exit;
    }

    // Connect to the MikroTik router - lightweight check (8s timeout)
    ini_set('default_socket_timeout', 8);
    $client = Mikrotik::getClient(
      $router['ip_address'],
      $router['username'],
      $router['password']
    );

    // Get router identity and basic system info (fast operation)
    $identity = $client->sendSync(new RouterOS\Request('/system/identity/print'));
    $resource = $client->sendSync(new RouterOS\Request('/system/resource/print'));

    $routerName = '';
    $version = '';
    $uptime = '';
    $model = '';

    foreach ($identity as $item) {
      $routerName = $item->getProperty('name');
    }

    foreach ($resource as $item) {
      $version = $item->getProperty('version');
      $uptime = $item->getProperty('uptime');
      $model = $item->getProperty('board-name');
    }

    echo json_encode([
      'status'  => 'success',
      'online'  => true,
      'message' => 'Router is online',
      'info'    => [
        'name'       => $routerName,
        'ip'         => $router['ip_address'],
        'version'    => $version,
        'uptime'     => $uptime,
        'model'      => $model
      ]
    ], JSON_PRETTY_PRINT);
  } catch (Throwable $e) {
    $isWireGuardRouter = (($router['management_transport'] ?? '') === 'wireguard');
    $tunnelIp = (string)($router['wg_tunnel_ip'] ?? $router['ip_address']);
    if ($isWireGuardRouter && mikrotik_configurator_wireguard_peer_is_online($tunnelIp)) {
      echo json_encode([
        'status'  => 'warning',
        'message' => 'WireGuard online — RouterOS API (port 8728) is unavailable. Re-run the generated onboarding script on the router to enable its API and firewall rule.',
        'online'  => false,
        'transport_online' => true
      ], JSON_PRETTY_PRINT);
    } else {
      echo json_encode([
        'status'  => 'error',
        'message' => 'RouterOS API is unavailable: ' . $e->getMessage(),
        'online'  => false
      ], JSON_PRETTY_PRINT);
    }
  }
  exit;
}


/**
 * AJAX endpoint: reads current configuration from a live MikroTik router.
 * Returns JSON with existing bridges, IP addresses, pools, hotspot servers,
 * PPPoE servers and system identity so the config form can be pre-filled.
 */
function mikrotik_configurator_get_current_config()
{
  mikrotik_configurator_require_allowed_admin(true);

  if (ob_get_level()) ob_clean();
  header('Content-Type: application/json');

  $router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;
  if (!$router_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing router_id.']);
    exit;
  }

  $router = ORM::for_table('tbl_routers')->find_one($router_id);
  if (!$router) {
    echo json_encode(['status' => 'error', 'message' => 'Router not found.']);
    exit;
  }

  try {
    ini_set('default_socket_timeout', 8);
    $client = Mikrotik::getClient(
      $router['ip_address'],
      $router['username'],
      $router['password']
    );

    // ── System identity ───────────────────────────────────────────────────
    $identity_resp = $client->sendSync(new RouterOS\Request('/system/identity/print'));
    $routerIdentity = '';
    foreach ($identity_resp as $item) {
      $routerIdentity = (string) $item->getProperty('name');
    }

    // ── Bridges ───────────────────────────────────────────────────────────
    $bridge_resp = $client->sendSync(new RouterOS\Request('/interface/bridge/print'));
    $bridges = [];
    foreach ($bridge_resp as $item) {
      $name = trim((string) $item->getProperty('name'));
      if ($name === '') {
        continue;
      }
      $bridges[] = $name;
    }

    // ── IP addresses (on bridges only) ────────────────────────────────────
    $addr_resp = $client->sendSync(new RouterOS\Request('/ip/address/print'));
    $addresses = [];
    foreach ($addr_resp as $item) {
      $iface   = trim((string) $item->getProperty('interface'));
      $address = trim((string) $item->getProperty('address')); // e.g. 10.5.0.1/16
      if ($iface === '' || $address === '') {
        continue;
      }
      if (in_array($iface, $bridges, true)) {
        $addresses[$iface] = $address;
      }
    }

    // ── IP pools ──────────────────────────────────────────────────────────
    $pool_resp = $client->sendSync(new RouterOS\Request('/ip/pool/print'));
    $pools = [];
    foreach ($pool_resp as $item) {
      $name = trim((string)$item->getProperty('name'));
      $ranges = trim((string)$item->getProperty('ranges'));
      if ($name === '' && $ranges === '') {
        continue;
      }
      $pools[] = [
        'name'   => $name,
        'ranges' => $ranges,
      ];
    }

    // ── Hotspot servers ───────────────────────────────────────────────────
    $hs_resp = $client->sendSync(new RouterOS\Request('/ip/hotspot/print'));
    $hotspots = [];
    foreach ($hs_resp as $item) {
      $name = trim((string)$item->getProperty('name'));
      $interface = trim((string)$item->getProperty('interface'));
      $addressPool = trim((string)$item->getProperty('address-pool'));
      if ($name === '' && $interface === '' && $addressPool === '') {
        continue;
      }
      $hotspots[] = [
        'name'       => $name,
        'interface'  => $interface,
        'address_pool' => $addressPool,
      ];
    }

    // ── Hotspot server profiles (DNS name lives here) ─────────────────────
    $hsp_resp = $client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print'));
    $hotspot_profiles = [];
    foreach ($hsp_resp as $item) {
      $name = trim((string)$item->getProperty('name'));
      $dnsName = trim((string)$item->getProperty('dns-name'));
      if ($name === '' && $dnsName === '') {
        continue;
      }
      $hotspot_profiles[] = [
        'name'     => $name,
        'dns_name' => $dnsName,
      ];
    }

    // ── PPPoE servers ─────────────────────────────────────────────────────
    $pppoe_resp = $client->sendSync(new RouterOS\Request('/interface/pppoe-server/server/print'));
    $pppoe_servers = [];
    foreach ($pppoe_resp as $item) {
      $name = trim((string)$item->getProperty('service-name'));
      $interface = trim((string)$item->getProperty('interface'));
      $defaultProfile = trim((string)$item->getProperty('default-profile'));
      if ($name === '' && $interface === '' && $defaultProfile === '') {
        continue;
      }
      $pppoe_servers[] = [
        'name'          => $name,
        'interface'     => $interface,
        'default_profile' => $defaultProfile,
      ];
    }

    // ── PPP profiles ──────────────────────────────────────────────────────
    $ppp_resp = $client->sendSync(new RouterOS\Request('/ppp/profile/print'));
    $ppp_profiles = [];
    foreach ($ppp_resp as $item) {
      $name = trim((string) $item->getProperty('name'));
      if ($name === '' || $name === 'default' || $name === 'default-encryption') continue;
      $ppp_profiles[] = [
        'name'         => $name,
        'local_address'  => (string) $item->getProperty('local-address'),
        'remote_address' => (string) $item->getProperty('remote-address'),
      ];
    }

    // ── Determine detected service types ─────────────────────────────────
    $has_hotspot = !empty($hotspots);
    $has_pppoe   = !empty($pppoe_servers);

    // Best-guess hotspot bridge and subnet
    $hotspot_bridge = '';
    $hotspot_subnet = '';
    $hotspot_dns    = '';
    if ($has_hotspot) {
      $hotspot_bridge = $hotspots[0]['interface'] ?? '';
      if (isset($addresses[$hotspot_bridge])) {
        // Convert gateway CIDR (10.5.0.1/16) → subnet CIDR (10.5.0.0/16)
        $gw = $addresses[$hotspot_bridge];
        list($ip, $prefix) = explode('/', $gw);
        $parts = explode('.', $ip);
        if ($prefix == 16) {
          $hotspot_subnet = $parts[0] . '.' . $parts[1] . '.0.0/16';
        } else {
          $hotspot_subnet = $gw; // keep as-is for non-/16
        }
      }
      // DNS from first hotspot profile that has one
      foreach ($hotspot_profiles as $prof) {
        if (!empty($prof['dns_name'])) {
          $hotspot_dns = $prof['dns_name'];
          break;
        }
      }
    }

    // Best-guess PPPoE bridge and subnet
    $pppoe_bridge = '';
    $pppoe_subnet = '';
    if ($has_pppoe) {
      $pppoe_bridge = $pppoe_servers[0]['interface'] ?? '';
      if (isset($addresses[$pppoe_bridge])) {
        $gw = $addresses[$pppoe_bridge];
        list($ip, $prefix) = explode('/', $gw);
        $parts = explode('.', $ip);
        if ($prefix == 16) {
          $pppoe_subnet = $parts[0] . '.' . $parts[1] . '.0.0/16';
        } else {
          $pppoe_subnet = $gw;
        }
      } elseif (!empty($ppp_profiles)) {
        // Derive from PPP pool
        foreach ($ppp_profiles as $prof) {
          $remotePool = $prof['remote_address'] ?? '';
          foreach ($pools as $pool) {
            if ($pool['name'] === $remotePool && !empty($pool['ranges'])) {
              // e.g. ranges = "10.10.0.1-10.10.255.254"
              $first = explode('-', $pool['ranges'])[0];
              $p = explode('.', $first);
              if (count($p) === 4) {
                $pppoe_subnet = $p[0] . '.' . $p[1] . '.0.0/16';
              }
              break 2;
            }
          }
        }
      }
    }

    echo json_encode([
      'status'          => 'success',
      'router_identity' => $routerIdentity,
      'bridges'         => $bridges,
      'addresses'       => $addresses,
      'pools'           => $pools,
      'hotspots'        => $hotspots,
      'hotspot_profiles' => $hotspot_profiles,
      'pppoe_servers'   => $pppoe_servers,
      'ppp_profiles'    => $ppp_profiles,
      'detected' => [
        'has_hotspot'    => $has_hotspot,
        'has_pppoe'      => $has_pppoe,
        'hotspot_bridge' => $hotspot_bridge,
        'hotspot_subnet' => $hotspot_subnet,
        'hotspot_dns'    => $hotspot_dns,
        'pppoe_bridge'   => $pppoe_bridge,
        'pppoe_subnet'   => $pppoe_subnet,
      ],
    ], JSON_PRETTY_PRINT);

  } catch (Throwable $e) {
    echo json_encode([
      'status'  => 'error',
      'message' => 'Cannot reach router: ' . $e->getMessage(),
    ]);
  } catch (Throwable $e) {
    echo json_encode([
      'status'  => 'error',
      'message' => 'System error: ' . $e->getMessage(),
    ]);
  }
  exit;
}
