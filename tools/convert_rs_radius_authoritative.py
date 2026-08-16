#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime('%Y%m%d-%H%M%S')
BACKUP = Path('/var/backups') / f'prm-radius-authoritative-{STAMP}'

FILES = [
    'system/autoload/Package.php',
    'system/autoload/PamnetHotspotPay.php',
    'system/plugin/CreateHotspotUser.php',
    'radius.php',
    'hotspot_login.html',
    'download.php',
    'system/plugin/rs_radius_wireguard_setup.php',
]


def fail(msg):
    raise RuntimeError(msg)


def read(rel):
    p = ROOT / rel
    if not p.exists():
        fail(f'Missing required file: {rel}')
    return p.read_text()


def write(rel, text):
    p = ROOT / rel
    p.write_text(text)


def replace_once(text, old, new, label):
    if new in text:
        return text
    count = text.count(old)
    if count != 1:
        fail(f'{label}: expected exactly 1 anchor, found {count}')
    return text.replace(old, new, 1)


def replace_between(text, start_marker, end_marker, replacement, label):
    start = text.find(start_marker)
    if start < 0:
        if replacement.strip() in text:
            return text
        fail(f'{label}: start marker not found')
    end = text.find(end_marker, start)
    if end < 0:
        fail(f'{label}: end marker not found')
    return text[:start] + replacement + text[end:]


def backup_all():
    BACKUP.mkdir(parents=True, exist_ok=False)
    for rel in FILES:
        src = ROOT / rel
        if src.exists():
            dst = BACKUP / rel
            dst.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dst)


def patch_package():
    rel = 'system/autoload/Package.php'
    s = read(rel)

    if 'public static function usesRadiusForPlan($plan)' not in s:
        marker = '    public static function getDevice($plan)\n    {\n'
        method = r'''    /**
     * RS/WireGuard routers are RADIUS-authoritative. Legacy/manual routers keep
     * their configured device driver. This lets old plan rows migrate safely
     * without requiring an API login or a local /ip hotspot user.
     */
    public static function usesRadiusForPlan($plan)
    {
        if (!$plan) {
            return false;
        }
        $device = trim((string) ($plan['device'] ?? ''));
        $routerName = trim((string) ($plan['routers'] ?? ''));
        if (strcasecmp($device, 'Radius') === 0 || (int) ($plan['is_radius'] ?? 0) === 1 || strcasecmp($routerName, 'radius') === 0) {
            return true;
        }
        if ($routerName === '') {
            return false;
        }
        try {
            $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
            if (!$router) {
                return false;
            }
            $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
            $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
            return $transport === 'wireguard' && $tunnelIp !== '';
        } catch (Throwable $e) {
            return false;
        }
    }

'''
        if marker not in s:
            fail('Package.php: getDevice marker not found')
        s = s.replace(marker, method + marker, 1)

    old = '''        if ($plan === false) {
            return "none";
        }
        if (!isset($plan['device'])) {'''
    new = '''        if ($plan === false) {
            return "none";
        }
        if (self::usesRadiusForPlan($plan)) {
            // Persist the migration when this is an ORM row so subsequent UI and
            // jobs also see the authoritative RADIUS device.
            try {
                if (is_object($plan)) {
                    $plan->device = 'Radius';
                    $plan->is_radius = 1;
                    $plan->save();
                }
            } catch (Throwable $e) {
            }
            return $DEVICE_PATH . DIRECTORY_SEPARATOR . 'Radius.php';
        }
        if (!isset($plan['device'])) {'''
    if new not in s:
        if old not in s:
            fail('Package.php: getDevice body anchor not found')
        s = s.replace(old, new, 1)

    old_guard = "if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'ensureHotspotUser')) {"
    new_guard = "if (!self::usesRadiusForPlan($p) && class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'ensureHotspotUser')) {"
    if old_guard in s:
        s = s.replace(old_guard, new_guard)

    write(rel, s)


def patch_hotspot_pay():
    rel = 'system/autoload/PamnetHotspotPay.php'
    s = read(rel)

    if 'public static function isRadiusRouterName($routerName)' not in s:
        marker = '''    /**
     * Finish deferred recharge + router sync after portal already got Resultcode=3.
     */
'''
        helpers = r'''    /** Return true for routers onboarded through the RS WireGuard/RADIUS control plane. */
    public static function isRadiusRouterName($routerName)
    {
        $routerName = trim((string) $routerName);
        if ($routerName === '' || $routerName === '0') {
            return false;
        }
        if (strcasecmp($routerName, 'radius') === 0) {
            return true;
        }
        try {
            $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
            if (!$router) {
                return false;
            }
            return strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard'
                && trim((string) ($router['wg_tunnel_ip'] ?? '')) !== '';
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Check active recharge or latest payment metadata for RADIUS authority. */
    public static function usernameUsesRadius($username)
    {
        $username = trim((string) $username);
        if ($username === '') {
            return false;
        }
        try {
            $recharge = ORM::for_table('tbl_user_recharges')
                ->where('username', $username)
                ->where('status', 'on')
                ->order_by_desc('id')
                ->find_one();
            if ($recharge) {
                $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
                if ($plan && class_exists('Package') && method_exists('Package', 'usesRadiusForPlan') && Package::usesRadiusForPlan($plan)) {
                    return true;
                }
                if (self::isRadiusRouterName($recharge['routers'] ?? '')) {
                    return true;
                }
            }
            $pg = ORM::for_table('tbl_payment_gateway')
                ->where('username', $username)
                ->order_by_desc('id')
                ->find_one();
            if ($pg) {
                $plan = ((int) ($pg['plan_id'] ?? 0) > 0)
                    ? ORM::for_table('tbl_plans')->where('id', (int) $pg['plan_id'])->find_one()
                    : null;
                if ($plan && class_exists('Package') && method_exists('Package', 'usesRadiusForPlan') && Package::usesRadiusForPlan($plan)) {
                    return true;
                }
                if (self::isRadiusRouterName($pg['routers'] ?? '')) {
                    return true;
                }
            }
        } catch (Throwable $e) {
        }
        return false;
    }

'''
        if marker not in s:
            fail('PamnetHotspotPay.php: deferred recharge marker not found')
        s = s.replace(marker, helpers + marker, 1)

    old = '''        $transId = trim((string) ($meta['trans_id'] ?? ''));
        if ($username === '' || $customerId <= 0 || $planId <= 0) {'''
    new = '''        $transId = trim((string) ($meta['trans_id'] ?? ''));
        $radiusAuth = self::isRadiusRouterName($routers);
        try {
            if (!$radiusAuth && $planId > 0 && class_exists('Package') && method_exists('Package', 'usesRadiusForPlan')) {
                $plan = ORM::for_table('tbl_plans')->where('id', $planId)->find_one();
                $radiusAuth = $plan ? Package::usesRadiusForPlan($plan) : false;
            }
        } catch (Throwable $eRadius) {
        }
        if ($username === '' || $customerId <= 0 || $planId <= 0) {'''
    if new not in s:
        if old not in s:
            fail('PamnetHotspotPay.php: completeDeferredRecharge vars anchor not found')
        s = s.replace(old, new, 1)

    old = '''            if ($username !== '') {
                self::connectDeviceNow($username);
            }
            return;'''
    new = '''            if ($username !== '' && !self::usernameUsesRadius($username)) {
                self::connectDeviceNow($username);
            }
            return;'''
    if new not in s:
        if old not in s:
            fail('PamnetHotspotPay.php: invalid-meta fallback anchor not found')
        s = s.replace(old, new, 1)

    old = '''        // Critical: put the paying phone online immediately (do not wait for portal PAP).
        self::connectDeviceNow($username);'''
    new = '''        // RADIUS-authoritative routers must authenticate through the normal
        // MikroTik Hotspot /login request, which triggers Access-Request.
        if ($radiusAuth || self::usernameUsesRadius($username)) {
            return;
        }
        // Legacy/manual routers retain the old API-assisted path.
        self::connectDeviceNow($username);'''
    if new not in s:
        if old not in s:
            fail('PamnetHotspotPay.php: final connectDeviceNow anchor not found')
        s = s.replace(old, new, 1)

    old = '''        if ($username === '') {
            return ['ok' => false, 'message' => 'empty'];
        }
        $ok = self::ensureHotspotUser($username);'''
    new = '''        if ($username === '') {
            return ['ok' => false, 'message' => 'empty'];
        }
        if (self::usernameUsesRadius($username)) {
            return [
                'ok' => true,
                'logged_in' => false,
                'message' => 'RADIUS authentication delegated to MikroTik Hotspot login',
            ];
        }
        $ok = self::ensureHotspotUser($username);'''
    if new not in s:
        if old not in s:
            fail('PamnetHotspotPay.php: connectDeviceNow start anchor not found')
        s = s.replace(old, new, 1)

    write(rel, s)


def patch_create_hotspot():
    rel = 'system/plugin/CreateHotspotUser.php'
    s = read(rel)

    if 'function PamnetAccountUsesRadius($username)' not in s:
        marker = 'function PamnetAutologinApi()\n{\n'
        helper = r'''function PamnetAccountUsesRadius($username)
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

'''
        if marker not in s:
            fail('CreateHotspotUser.php: PamnetAutologinApi marker not found')
        s = s.replace(marker, helper + marker, 1)

    # Old/cached portals may still call type=autologin. Do not perform RouterOS
    # active/login for RADIUS accounts; tell the browser to use PAP fallback.
    anchor = "    PamnetLogPortalClient('autologin', $username, $clientInfo);\n\n"
    block = r'''    PamnetLogPortalClient('autologin', $username, $clientInfo);

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

'''
    if block not in s:
        if anchor not in s:
            fail('CreateHotspotUser.php: autologin log anchor not found')
        s = s.replace(anchor, block, 1)

    start = '''    $active = PamnetUsernameHasActivePlan($account_number);
    if ($active) {
        $ready = PamnetEnsureHotspotOnRouter($account_number);
        $loggedIn = false;
        $authMsg = 'Active package found';
'''
    end = "        $cust = ORM::for_table('tbl_customers')->where('username', $account_number)->find_one();\n"
    if start in s:
        i = s.find(start)
        j = s.find(end, i)
        if j < 0:
            fail('CreateHotspotUser.php: CheckActive end anchor not found')
        replacement = r'''    $active = PamnetUsernameHasActivePlan($account_number);
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
'''
        s = s[:i] + replacement + s[j:]

    closure = '''    $respondPaid = function ($pass, $mpesacode) use ($account_number, $clientIp, $clientMac, &$deferMeta) {
        header('Content-Type: application/json; charset=utf-8');
'''
    closure_new = r'''    $respondPaid = function ($pass, $mpesacode) use ($account_number, $clientIp, $clientMac, &$deferMeta) {
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
'''
    if closure_new not in s:
        if closure not in s:
            fail('CreateHotspotUser.php: respondPaid closure anchor not found')
        s = s.replace(closure, closure_new, 1)

    old = '''        ignore_user_abort(true);
        try {
            // Connect this phone NOW. Do not run full Package::rechargeUser here —'''
    new = '''        if ($radiusAuth) {
            // Browser will now POST credentials to MikroTik /login. MikroTik then
            // sends the Access-Request to FreeRADIUS; no RouterOS API login here.
            exit();
        }
        ignore_user_abort(true);
        try {
            // Connect this phone NOW. Do not run full Package::rechargeUser here —'''
    if new not in s:
        if old not in s:
            fail('CreateHotspotUser.php: respondPaid background anchor not found')
        s = s.replace(old, new, 1)

    write(rel, s)


def patch_radius_endpoint():
    rel = 'radius.php'
    s = read(rel)

    if 'function rs_radius_find_active_recharge($username)' not in s:
        marker = '$code = 200;\n\n'
        helpers = r'''$code = 200;

/** Resolve the billing router name from the NAS tunnel address registered at onboarding. */
function rs_radius_request_router_name()
{
    $nasIp = trim((string) _post('nasIpAddress'));
    if ($nasIp === '') {
        $nasIp = trim((string) _req('nasIpAddress'));
    }
    if ($nasIp === '') {
        return '';
    }
    try {
        $nas = ORM::for_table('nas', 'radius')->where('nasname', $nasIp)->find_one();
        return $nas ? trim((string) ($nas['routers'] ?? '')) : '';
    } catch (Throwable $e) {
        return '';
    }
}

/** Only return an unexpired active recharge that belongs to this RADIUS NAS/router. */
function rs_radius_find_active_recharge($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return null;
    }
    $routerName = rs_radius_request_router_name();
    try {
        $q = ORM::for_table('tbl_user_recharges')
            ->where_raw('BINARY username = ?', [$username])
            ->where('status', 'on')
            ->order_by_desc('id');
        if ($routerName !== '') {
            $q->where('routers', $routerName);
        }
        foreach ($q->find_many() as $row) {
            $expiry = strtotime(trim((string) ($row['expiration'] ?? '') . ' ' . (string) ($row['time'] ?? '23:59:59')));
            if ($expiry !== false && $expiry <= time()) {
                continue;
            }
            $plan = ORM::for_table('tbl_plans')->where('id', $row['plan_id'])->find_one();
            if (!$plan) {
                continue;
            }
            if (class_exists('Package') && method_exists('Package', 'usesRadiusForPlan') && Package::usesRadiusForPlan($plan)) {
                return $row;
            }
        }
    } catch (Throwable $e) {
    }
    return null;
}

'''
        if marker not in s:
            fail('radius.php: code marker not found')
        s = s.replace(marker, helpers, 1)

    old1 = '$tur = ORM::for_table(\'tbl_user_recharges\')->whereRaw("BINARY username = \'$username\'")->find_one();'
    if old1 in s:
        s = s.replace(old1, '$tur = rs_radius_find_active_recharge($username);')

    old2 = '$tur = ORM::for_table(\'tbl_user_recharges\')->whereRaw("BINARY username = \'$username\' AND `status` = \'on\' AND `routers` = \'radius\'")->find_one();'
    if old2 in s:
        s = s.replace(old2, '$tur = rs_radius_find_active_recharge($username);', 1)

    write(rel, s)


def patch_portal_file(rel):
    s = read(rel)
    start = '    function apiAutologinThenBrowse(account, password) {\n'
    end = '    function isMikroTikVar(v) {\n'
    replacement = r'''    function apiAutologinThenBrowse(account, password) {
        // RS/WireGuard Hotspot authentication is RADIUS-authoritative.
        // Returning false sends credentials through the router's normal /login
        // form, which causes MikroTik to issue the RADIUS Access-Request.
        return Promise.resolve(false);
    }

'''
    if start in s:
        i = s.find(start)
        j = s.find(end, i)
        if j < 0:
            fail(f'{rel}: isMikroTikVar marker not found')
        s = s[:i] + replacement + s[j:]
    elif replacement.strip() not in s:
        fail(f'{rel}: apiAutologinThenBrowse marker not found')
    write(rel, s)


def patch_onboarding():
    rel = 'system/plugin/rs_radius_wireguard_setup.php'
    s = read(rel)
    s = s.replace('$generatorVersion = 6;', '$generatorVersion = 7;')
    old = '''        '    :if ([:len [/ip/hotspot/profile find]] > 0) do={ /ip/hotspot/profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received; };','''
    new = '''        '    :if ([:len [/ip/hotspot/profile find]] > 0) do={ /ip/hotspot/profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received login-by=http-pap,http-chap,cookie; };','''
    if new not in s:
        if old not in s:
            fail('rs_radius_wireguard_setup.php: Hotspot profile RADIUS line not found')
        s = s.replace(old, new, 1)
    write(rel, s)


def main():
    if ROOT.name == 'tools':
        fail('Run this tool from the repository, not from a copied tools-only folder')
    backup_all()
    try:
        patch_package()
        patch_hotspot_pay()
        patch_create_hotspot()
        patch_radius_endpoint()
        patch_portal_file('hotspot_login.html')
        patch_portal_file('download.php')
        patch_onboarding()
    except Exception:
        print(f'ERROR: conversion failed. Backups are in {BACKUP}', file=sys.stderr)
        raise

    print('SUCCESS: RS/WireGuard Hotspot is now RADIUS-authoritative in the source tree.')
    print(f'Backups: {BACKUP}')
    print('Next: run PHP syntax checks, inspect git diff, then reload Apache/FreeRADIUS.')


if __name__ == '__main__':
    main()
