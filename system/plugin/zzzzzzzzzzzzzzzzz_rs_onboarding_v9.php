<?php

/**
 * RS onboarding v9 delivery guard.
 *
 * The v8 bootstrap was short, but it was still multi-line. RouterOS /tool/fetch
 * can print progress while WinBox is pasting the remaining lines, which can
 * corrupt the next command and leave onboarding only partially applied.
 *
 * This late-loaded route wrapper keeps the existing RS control plane, token,
 * NAS registration and installer builder, but presents one RouterOS command.
 * That command downloads and imports the full server-generated installer
 * synchronously and reports failure instead of continuing after a broken paste.
 */

$rs9Route = trim((string)($_GET['_route'] ?? ''));
if ($rs9Route === 'plugin/rs_radius_wireguard_setup') {
    $_GET['_route'] = 'plugin/rs9_radius_wireguard_setup';
}

// Keep one Automatic Router Setup menu entry. The older NuxHost clone and the
// v8 RS menu are implementation history, not separate workflows administrators
// should have to choose between.
if (isset($menu_registered) && is_array($menu_registered)) {
    $menu_registered = array_values(array_filter($menu_registered, static function ($item) {
        $fn = is_array($item) ? (string)($item['function'] ?? '') : '';
        return !in_array($fn, ['radius_wireguard_setup', 'rs_radius_wireguard_setup'], true);
    }));
}
register_menu(
    'Automatic Router Setup',
    true,
    'rs9_radius_wireguard_setup',
    'AFTER_NETWORKS',
    'fa fa-shield',
    '',
    'success',
    ['SuperAdmin', 'Admin']
);

function rs9_routeros_quote($value)
{
    return str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '', ' '], (string)$value);
}

function rs9_bootstrap_url_from_plan(array $plan)
{
    $script = (string)($plan['script'] ?? '');
    if ($script === '') {
        throw new RuntimeException('The onboarding plan did not contain a bootstrap command.');
    }

    if (!preg_match('#url="([^"]*?_route=plugin/rs_radius_wireguard_bootstrap[^\"]*)"#', $script, $m)) {
        throw new RuntimeException('The onboarding bootstrap URL could not be extracted. Regenerate the router plan.');
    }

    $url = html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('The onboarding bootstrap URL is invalid.');
    }
    return $url;
}

function rs9_build_single_command_bootstrap($bootstrapUrl)
{
    $url = rs9_routeros_quote($bootstrapUrl);
    $file = 'rs-radius-onboard.rsc';

    // One paste only. /tool/fetch finishes before /import runs, so fetch progress
    // cannot eat or corrupt a second pasted command in the WinBox terminal.
    return ':do { '
        . ':put "RS: downloading and applying installer..."; '
        . ':do { /file remove [find where name="' . $file . '"]; } on-error={}; '
        . '/tool/fetch url="' . $url . '" dst-path="' . $file . '" keep-result=yes check-certificate=no; '
        . ':if ([:len [/file find where name="' . $file . '"]] = 0) do={ :error "installer file missing"; }; '
        . '/import file-name="' . $file . '"; '
        . ':put "RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE"; '
        . '} on-error={ :put "RS-ONBOARDING-FAILED"; };';
}

function rs9_radius_wireguard_setup()
{
    global $ui;

    $admin = rs_wg_require_admin(false);
    rs_wg_ensure_schema();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['name'])) {
        $name = trim((string)_post('name'));
        $description = trim((string)_post('description', ''));

        if (!Validator::Length($name, 30, 1)) {
            r2(getUrl('plugin/rs_radius_wireguard_setup'), 'e', 'Router name should be between 1 and 30 characters.');
        }
        if (strtolower($name) === 'radius') {
            r2(getUrl('plugin/rs_radius_wireguard_setup'), 'e', 'Radius is a reserved router name.');
        }
        if (ORM::for_table('tbl_routers')->where('name', $name)->find_one()) {
            r2(getUrl('plugin/rs_radius_wireguard_setup'), 'e', 'A router with that name already exists.');
        }

        $router = ORM::for_table('tbl_routers')->create();
        $router->set([
            'name' => $name,
            'ip_address' => '0.0.0.0',
            'username' => 'pending',
            'password' => 'pending',
            'description' => $description,
            'enabled' => 1,
            'status' => 'Offline',
            'management_transport' => 'wireguard',
        ])->save();

        _log('[' . ($admin['username'] ?? 'admin') . ']: Created router ' . $name . ' for RS v9 WireGuard/RADIUS onboarding', 'SuperAdmin');
        r2(getUrl('plugin/rs_radius_wireguard_setup&router_id=' . $router->id()));
    }

    $ui->assign('_admin', $admin);
    $ui->assign('_title', 'Automatic WireGuard + RADIUS Setup');
    $ui->assign('_system_menu', 'network');

    $routerId = (int)_get('router_id', 0);
    if ($routerId <= 0) {
        $ui->display('rs_radius_wireguard_setup.tpl');
        return;
    }

    $router = ORM::for_table('tbl_routers')->find_one($routerId);
    if (!$router) {
        r2(getUrl('routers/list'), 'e', 'Router not found.');
    }

    try {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $probe = rs_wg_probe_router_api($router);
        if ($probe !== false && strtolower(trim((string)($router['management_transport'] ?? ''))) === 'wireguard') {
            $router->status = 'Online';
            $router->last_seen = date('Y-m-d H:i:s');
            $router->save();
            r2(getUrl('plugin/mikrotik_configurator_config_ui&router_id=' . $routerId . '&auto_radius=1'));
        }

        // rs_wg_prepare_router() performs SQL NAS upsert and calls
        // rs-radius-manage reload. With the repaired helper this reloads the
        // dedicated /etc/freeradius-rs instance before the router is told to
        // send RADIUS traffic.
        $plan = rs_wg_prepare_router($router);
        $bootstrapUrl = rs9_bootstrap_url_from_plan($plan);
        $plan['script'] = rs9_build_single_command_bootstrap($bootstrapUrl);

        $router = ORM::for_table('tbl_routers')->find_one($routerId);
        $ui->assign('router', $router);
        $ui->assign('tunnel_ip', $plan['tunnel_ip']);
        $ui->assign('setup_script', $plan['script']);
        $ui->assign('setup_error', null);
    } catch (Throwable $e) {
        error_log('RS v9 WireGuard onboarding preparation failed: ' . $e->getMessage());
        $ui->assign('router', $router);
        $ui->assign('tunnel_ip', '');
        $ui->assign('setup_script', '');
        $ui->assign('setup_error', $e->getMessage());
    }

    $ui->display('rs_radius_wireguard_polling.tpl');
}
