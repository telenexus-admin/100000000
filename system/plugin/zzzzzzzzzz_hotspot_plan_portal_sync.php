<?php

/**
 * Final Hotspot package -> live portal synchronizer.
 *
 * The generated login.html used a raw XMLHttpRequest for hotspot_plans and
 * replaced the package grid with "Network error" whenever an unauthenticated
 * captive browser could not reach billing. This layer embeds the router's
 * current package list into login.html, renders it immediately, then performs
 * a quiet live refresh. Package create/edit also republishes the affected
 * router's login.html automatically.
 */

use PEAR2\Net\RouterOS;

$rs10Route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($rs10Route === 'plugin/rs8_mikrotik_configurator_config_process') {
    $_GET['_route'] = 'plugin/rs10_mikrotik_configurator_config_process';
} elseif ($rs10Route === 'plugin/rs4_hotspot_plan_add_post') {
    $_GET['_route'] = 'plugin/rs10_hotspot_plan_add_post';
} elseif ($rs10Route === 'plugin/rs4_hotspot_plan_edit_post') {
    $_GET['_route'] = 'plugin/rs10_hotspot_plan_edit_post';
}

if ($rs10Route === 'plugin/hotspot_settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $rid = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    if ($rid > 0) {
        register_shutdown_function('rs10_publish_router_portal', $rid, 'settings');
    }
}

function rs10_appconfig_set($key, $value)
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', (string) $key)->find_one();
    if (!$row) {
        $row = ORM::for_table('tbl_appconfig')->create();
        $row->setting = (string) $key;
    }
    $row->value = (string) $value;
    $row->save();
}

function rs10_router_package_payload($routerId)
{
    $router = ORM::for_table('tbl_routers')->find_one((int) $routerId);
    if (!$router) {
        throw new RuntimeException('Router not found while preparing Hotspot packages.');
    }
    $routerName = trim((string) ($router['name'] ?? ''));
    if ($routerName === '') {
        throw new RuntimeException('Router name is empty.');
    }

    $currencyRow = ORM::for_table('tbl_appconfig')->where('setting', 'currency_code')->find_one();
    $currency = $currencyRow ? (string) $currencyRow['value'] : 'Ksh';

    $plans = ORM::for_table('tbl_plans')
        ->where('type', 'Hotspot')
        ->where('enabled', 1)
        ->where_raw(
            "(tbl_plans.routers = ? OR FIND_IN_SET(?, REPLACE(tbl_plans.routers, ' ', '')) > 0 OR tbl_plans.routers = 'all')",
            [$routerName, $routerName]
        )
        ->find_array();

    usort($plans, function ($a, $b) {
        $ao = stripos((string) ($a['name_plan'] ?? ''), 'offer') !== false;
        $bo = stripos((string) ($b['name_plan'] ?? ''), 'offer') !== false;
        if ($ao !== $bo) return $ao ? -1 : 1;
        return ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));
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
            'timelimit' => $plan['validity_unit'] ?? null,
            'paymentlink' => '',
            'planId' => (int) ($plan['id'] ?? 0),
            'routerName' => $routerName,
            'routerId' => (int) $routerId,
        ];
    }

    return [[
        'name' => $routerName,
        'router_id' => (int) $routerId,
        'description' => (string) ($router['description'] ?? ''),
        'plans_hotspot' => $items,
    ]];
}

function rs10_embed_packages($html, $routerId)
{
    $html = (string) $html;
    $payload = rs10_router_package_payload((int) $routerId);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json) || $json === '') {
        throw new RuntimeException('Could not encode Hotspot packages.');
    }

    $boot = '<script id="rs-baked-hotspot-plans">window.RS_BAKED_PLAN_RESPONSE=' . $json . ';</script>';
    $html = preg_replace('#<script id="rs-baked-hotspot-plans">.*?</script>\s*#is', '', $html);
    $html = preg_replace('#</head>#i', $boot . "\n</head>", $html, 1);

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

    // Keep the old fetchData function for compatibility, but replace its single
    // invocation so its raw XHR error handler cannot overwrite valid cards.
    $pattern = '#fetchData\(\);\s*</script>#i';
    if (preg_match($pattern, $html)) {
        $html = preg_replace($pattern, $loader . "\n</script>", $html, 1);
    } else {
        $html = preg_replace('#</body>#i', '<script>' . $loader . '</script></body>', $html, 1);
    }
    return $html;
}

function rs10_router_login_url($client)
{
    try {
        $profileName = '';
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $hs) {
            $disabled = strtolower(trim((string) $hs->getProperty('disabled')));
            if ($disabled === 'true' || $disabled === 'yes') continue;
            $profileName = trim((string) $hs->getProperty('profile'));
            if ($profileName !== '') break;
        }
        if ($profileName !== '') {
            foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
                if ((string) $p->getProperty('name') !== $profileName) continue;
                $ip = trim((string) $p->getProperty('hotspot-address'));
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return 'http://' . $ip . '/login';
                }
            }
        }
    } catch (Throwable $ignored) {
    }
    return '';
}

function rs10_publish_router_portal($routerId, $reason = 'sync')
{
    $routerId = (int) $routerId;
    if ($routerId <= 0) return;

    try {
        if (!function_exists('hotspot_settings_generate_login_html')
            || !function_exists('hotspot_settings_store_login_html')
            || !function_exists('rs_mikrotik_configurator_client')
            || !function_exists('rs_mikrotik_configurator_upload_login')) {
            return;
        }

        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        if (!$router) return;
        $routerName = trim((string) ($router['name'] ?? ''));
        if ($routerName === '') return;

        $billing = function_exists('rs8_billing_url')
            ? rs8_billing_url()
            : (defined('APP_URL') ? rtrim((string) APP_URL, '/') : '');
        if ($billing === '') return;

        $client = rs_mikrotik_configurator_client($router, 8);
        $client->sendSync(new RouterOS\Request('/system/identity/print'));

        rs10_appconfig_set('router_id', (string) $routerId);
        rs10_appconfig_set('router_name', $routerName);
        rs10_appconfig_set('hotspot_billing_url', $billing);
        $loginUrl = rs10_router_login_url($client);
        if ($loginUrl !== '') rs10_appconfig_set('hotspot_login_url', $loginUrl);

        $html = hotspot_settings_generate_login_html();
        if (function_exists('rs8_patch_portal_html')) {
            $html = rs8_patch_portal_html($html, $billing, $routerId);
        }
        $html = rs10_embed_packages($html, $routerId);
        $stored = hotspot_settings_store_login_html($html, $billing);

        $dir = function_exists('hotspot_settings_html_directory') ? hotspot_settings_html_directory($client) : 'hotspot';
        rs_mikrotik_configurator_upload_login($client, $stored['url'] . '?_plans=' . time(), $dir);

        if (function_exists('rs8_remove_legacy_billing_walled_garden')) {
            rs8_remove_legacy_billing_walled_garden($client);
        }
        if (function_exists('rs8_ensure_billing_ip_walled_garden')) {
            rs8_ensure_billing_ip_walled_garden($client, $billing);
        }

        error_log('[hotspot-plan-portal-sync] router=' . $routerId . ' reason=' . preg_replace('/[^a-z0-9_-]/i', '', (string) $reason) . ' published=1');
    } catch (Throwable $e) {
        error_log('[hotspot-plan-portal-sync] router=' . $routerId . ' error=' . $e->getMessage());
    }
}

function rs10_post_router_id()
{
    $name = function_exists('rs4_hotspot_plan_post_router') ? rs4_hotspot_plan_post_router() : '';
    if ($name === '') {
        $name = $_POST['routers'] ?? '';
        if (is_array($name)) $name = reset($name);
        $name = trim((string) $name);
    }
    if ($name === '') return 0;
    $router = ORM::for_table('tbl_routers')->where('name', $name)->find_one();
    return $router ? (int) $router['id'] : 0;
}

function rs10_hotspot_plan_add_post()
{
    $rid = rs10_post_router_id();
    if ($rid > 0) register_shutdown_function('rs10_publish_router_portal', $rid, 'package-add');
    rs4_hotspot_plan_add_post();
}

function rs10_hotspot_plan_edit_post()
{
    $rid = rs10_post_router_id();
    if ($rid > 0) register_shutdown_function('rs10_publish_router_portal', $rid, 'package-edit');
    rs4_hotspot_plan_edit_post();
}

function rs10_mikrotik_configurator_config_process()
{
    $rid = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    if ($rid > 0) register_shutdown_function('rs10_publish_router_portal', $rid, 'router-config');
    rs8_mikrotik_configurator_config_process();
}
