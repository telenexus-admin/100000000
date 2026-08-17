<?php

/**
 * Heal paid/active RADIUS Hotspot accounts before the portal asks MikroTik to
 * authenticate them.  A recharge row can exist even when the device driver's
 * add_customer() failed, because Package::rechargeUser() records billing after
 * catching the device error.  For RADIUS-authoritative plans that leaves the
 * customer paid but absent/stale in radcheck.
 *
 * This late plugin wraps only verify/check_active/autologin.  It never performs
 * RouterOS API login for RADIUS users; it only ensures billing/RADIUS SQL state
 * exists, then the normal MikroTik /login request performs authentication.
 */

$rs13Route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
$rs13Type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';

if ($rs13Route === 'plugin/CreateHotspotUser') {
    if ($rs13Type === 'verify') {
        $_GET['_route'] = 'plugin/rs13_radius_verify';
    } elseif ($rs13Type === 'check_active') {
        $_GET['_route'] = 'plugin/rs13_radius_check_active';
    } elseif ($rs13Type === 'autologin') {
        $_GET['_route'] = 'plugin/rs13_radius_autologin';
    }
}

function rs13_radius_input_username()
{
    $raw = (string) file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        return '';
    }
    return trim((string) ($input['account_number'] ?? $input['username'] ?? ''));
}

function rs13_radius_heal($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }

    try {
        if (!function_exists('PamnetAccountUsesRadius') || !PamnetAccountUsesRadius($username)) {
            return true;
        }

        // Paid gateway but recharge missing: complete the idempotent recharge first.
        if (function_exists('PamnetUsernameHasActivePlan') && !PamnetUsernameHasActivePlan($username)) {
            $pg = ORM::for_table('tbl_payment_gateway')
                ->where('username', $username)
                ->where('status', 2)
                ->order_by_desc('id')
                ->find_one();
            $cust = ORM::for_table('tbl_customers')->where('username', $username)->find_one();

            if ($pg && $cust && (int) ($pg['plan_id'] ?? 0) > 0
                && trim((string) ($pg['gateway_trx_id'] ?? '')) !== ''
                && class_exists('PamnetHotspotPay')) {
                PamnetHotspotPay::completeDeferredRecharge([
                    'ok' => true,
                    'needs_recharge' => true,
                    'trans_id' => (string) $pg['gateway_trx_id'],
                    'plan_id' => (int) $pg['plan_id'],
                    'routers' => (string) ($pg['routers'] ?? ''),
                    'customer_id' => (int) $cust['id'],
                    'username' => $username,
                ]);
            }
        }

        // Active RADIUS package: always re-sync credentials/group/NAS binding.
        // This is deliberately SQL provisioning only. MikroTik /login remains
        // the authenticator and generates the RADIUS Access-Request.
        if (function_exists('PamnetUsernameHasActivePlan')
            && PamnetUsernameHasActivePlan($username)
            && function_exists('PamnetEnsureHotspotOnRouter')) {
            $ok = (bool) PamnetEnsureHotspotOnRouter($username);
            if (!$ok) {
                error_log('[radius-active-heal] username=' . preg_replace('/[^A-Za-z0-9_-]/', '', $username) . ' sync=failed');
            }
            return $ok;
        }
    } catch (Throwable $e) {
        error_log(
            '[radius-active-heal] username=' . preg_replace('/[^A-Za-z0-9_-]/', '', $username)
            . ' error=' . preg_replace('/[\r\n]+/', ' ', $e->getMessage())
        );
        return false;
    }

    return true;
}

function rs13_radius_verify()
{
    rs13_radius_heal(rs13_radius_input_username());
    VerifyHotspot();
}

function rs13_radius_check_active()
{
    rs13_radius_heal(rs13_radius_input_username());
    CheckActiveHotspot();
}

function rs13_radius_autologin()
{
    rs13_radius_heal(rs13_radius_input_username());
    PamnetAutologinApi();
}
