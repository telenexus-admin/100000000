<?php

if (! function_exists('_nuxhost_curl_tls_verify')) {
    function _nuxhost_curl_tls_verify(): bool
    {
        $v = getenv('NUXHOST_CURL_INSECURE');
        if ($v === false || $v === '') {
            return true;
        }

        return ! in_array(strtolower((string) $v), ['1', 'true', 'yes'], true);
    }
}

/** Host only, no port — matches NuxHost IspApi domain checks (Laragon :8080, etc.). */
if (! function_exists('_sm_normalize_api_domain')) {
    function _sm_normalize_api_domain(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }
        if ($host[0] === '[') {
            $end = strpos($host, ']');
            if ($end !== false && isset($host[$end + 1]) && $host[$end + 1] === ':') {
                return substr($host, 1, $end - 1);
            }

            return $host;
        }
        $pos = strrpos($host, ':');
        if ($pos !== false) {
            $tail = substr($host, $pos + 1);
            if ($tail !== '' && ctype_digit($tail)) {
                return substr($host, 0, $pos);
            }
        }

        return $host;
    }
}

// Suspension enforcement is handled exclusively by nuxhost_suspension_lock.php
// which runs earlier in the plugin load order and hard-exits when suspended.
// _sm_check_isp_suspension() is kept defined below for reference but not called.

if (isset($_GET['type'])) {
    switch ($_GET['type']) {
        case 'billing_report':
            _sm_api_billing_report();
        exit;
        case 'initiate_payment':
            _sm_api_initiate_payment();
        exit;
        case 'check_payment':
            _sm_api_check_payment();
        exit;
        case 'sync_subscription':
            _sm_api_sync_subscription();
        exit;
    }
}

// Auto-register subscription status widget if not exists
try {
    $sm_widget = ORM::for_table('tbl_widgets')->where('widget', 'subscription_status')->find_one();
    if (!$sm_widget) {
        $sm_widget = ORM::for_table('tbl_widgets')->create();
        $sm_widget->orders = 3;
        $sm_widget->position = 1;
        $sm_widget->user = 'Admin';
        $sm_widget->enabled = 1;
        $sm_widget->title = 'Subscription Status';
        $sm_widget->widget = 'subscription_status';
        $sm_widget->content = '';
        $sm_widget->save();
    }
} catch (Exception $e) {
    // Silent fail if table not exists yet
}

// Register menu: prefer AFTER_COMMUNITY when present, otherwise fall back to AFTER_LOGS
$sm_anchor = 'AFTER_COMMUNITY';
try {
    $found = false;
    try {
        $menuRows = ORM::for_table('tbl_appmenu')->find_many();
        foreach ($menuRows as $mr) {
            $arr = method_exists($mr, 'as_array') ? $mr->as_array() : (array) $mr;
            if (stripos(json_encode($arr), 'AFTER_COMMUNITY') !== false) {
                $found = true;
                break;
            }
        }
    } catch (Exception $_e) {
        $found = false;
    }
    if (!$found) {
        $sm_anchor = 'AFTER_LOGS';
    }
} catch (Exception $e) {
    $sm_anchor = 'AFTER_LOGS';
}

register_menu(
    'Subscription Manager',
    true,
    'subscription_manager',
    $sm_anchor,
    'ion ion-card',
    '',
    '',
    ['Admin', 'SuperAdmin']
);

// If subscription manager route is opened without an admin session,
// redirect to login instead of falling through to the public homepage.
$sm_current_route = isset($_GET['_route']) ? trim($_GET['_route']) : '';
if (empty($_GET['type']) && $sm_current_route === 'plugin/subscription_manager' && empty($_SESSION['aid'])) {
    header('Location: ' . APP_URL . '/?_route=login');
    exit;
}

// Grace / suspension UI vs hard block: 00_nuxhost_suspension_lock.php (loads first).
// Impersonation routes (nh_admin_bridge, nuxhost_impersonate) are exempt there.

// ─── Helper: tbl_appconfig get/set ──────────────────────────────────────────

function _sm_get_config($key, $default = '')
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    return $row ? (string) $row->value : $default;
}

function _sm_set_config($key, $value)
{
    $row = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    if ($row) {
        $row->value = $value;
        $row->save();
    } else {
        $row = ORM::for_table('tbl_appconfig')->create();
        $row->setting = $key;
        $row->value   = $value;
        $row->save();
    }
}

function _sm_delete_config($key)
{
    $rows = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_many();
    foreach ($rows as $row) {
        $row->delete();
    }
}

// ─── Billing data queries ────────────────────────────────────────────────────

function _sm_pppoe_active_count()
{
    $db  = ORM::get_db();
    $sql = "SELECT COUNT(*) AS cnt FROM tbl_user_recharges WHERE status = 'on' AND type = 'PPPOE'";
    $row = $db->query($sql)->fetch(\PDO::FETCH_ASSOC);
    return (int) ($row['cnt'] ?? 0);
}

function _sm_hotspot_revenue_30d()
{
    $db  = ORM::get_db();
    $sql = "SELECT COALESCE(SUM(CAST(pg.price AS DECIMAL(12,2))), 0) AS revenue
            FROM tbl_payment_gateway pg
            JOIN tbl_plans p ON p.id = pg.plan_id
            WHERE pg.status = 2
              AND p.type = 'Hotspot'
              AND pg.paid_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $row = $db->query($sql)->fetch(\PDO::FETCH_ASSOC);
    return (float) ($row['revenue'] ?? 0);
}

// ─── NuxHost URL (written into config.php by NuxHost at provisioning time) ──

function _sm_nuxhost_url(): string
{
    return defined('NUXHOST_URL') ? rtrim(NUXHOST_URL, '/') : '';
}

function _sm_json_response(array $payload, int $code = 200): void
{
    if (ob_get_level()) {
        @ob_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload);
}

// ─── Widget data: check if invoice pending and days until expiry ───────────

function _sm_widget_data()
{
    $subscript_expires = _sm_get_config('nuxhost_subscription_expires', '');
    $account_status    = _sm_get_config('nuxhost_account_status', '');
    $widget = [
        'show' => false,
        'type' => 'expiring',      // 'expiring' or 'invoice_pending'
        'days_until_expiry' => null,
        'days_until_invoice_gen' => null,
        'pending_invoice' => null,
    ];

    if (empty($subscript_expires)) {
        return $widget;
    }

    $expire_ts = strtotime($subscript_expires);
    if ($account_status === 'Paid') {
        if ($expire_ts !== false && $expire_ts >= time()) {
            $days_until = (int) floor(($expire_ts - time()) / 86400);
            if ($days_until <= 3) {
                $widget['show'] = true;
                $widget['type'] = 'expiring';
                $widget['days_until_expiry'] = max(0, $days_until);
            }
        }
        return $widget;
    }

    if ($expire_ts === false || $expire_ts >= time()) {
        $days_until = (int) floor(($expire_ts - time()) / 86400);
        if ($days_until <= 3) {
            $widget['show'] = true;
            $widget['type'] = 'expiring';
            $widget['days_until_expiry'] = max(0, $days_until);
        }
        return $widget;
    }

    // Subscription expired — we are in grace period.
    // 00_nuxhost_suspension_lock.php handles actual blocking when nbg_suspension_alert is set.
    // Here we only show the dashboard widget so the ISP admin can pay without losing access.
    try {
        $suspended = ORM::for_table('tbl_appconfig')
            ->where('setting', 'nbg_suspension_alert')
            ->find_one();
        if (!$suspended || empty($suspended->value)) {
            // Grace period — prompt payment on dashboard
            $widget['show'] = true;
            $widget['type'] = 'invoice_pending';
        }
        // If nbg_suspension_alert is set: suspension_lock blocks everything — widget is moot
    } catch (Exception $e) {
        // DB unavailable — show widget as safe default
        $widget['show'] = true;
        $widget['type'] = 'invoice_pending';
    }

    return $widget;
}

// ─── NuxHost calls ──────────────────────────────────────────────────────────

function _sm_curl_get($url)
{
    $verify = _nuxhost_curl_tls_verify();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    return [$httpCode, $response, $curlError];
}

function _sm_curl_post($url, $payload)
{
    $verify = _nuxhost_curl_tls_verify();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => $verify,
        CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    return [$httpCode, $response, $curlError];
}

function _sm_fetch_pricing($domain)
{
    $nuxhost_url = _sm_nuxhost_url();
    if (empty($nuxhost_url)) {
        return null;
    }
    $url = $nuxhost_url . '/?app_route=isp_api_pricing&domain=' . urlencode($domain);
    [$httpCode, $response] = _sm_curl_get($url);
    if ($httpCode !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    if (!$data || ($data['status'] ?? '') !== 'ok') {
        return null;
    }
    // Cache pricing locally
    _sm_set_config('nuxhost_pppoe_rate', $data['pppoe_rate']);
    _sm_set_config('nuxhost_hotspot_rate', $data['hotspot_commission']);
    _sm_set_config('nuxhost_minimum_pay', $data['minimum_pay']);
    _sm_set_config('nuxhost_currency', $data['currency']);
    _sm_set_config('nuxhost_last_sync', date('Y-m-d H:i:s'));
    _sm_set_config('nuxhost_company', $data['nuxhost_company'] ?? '');
    _sm_set_config('nuxhost_email', $data['nuxhost_email'] ?? '');
    _sm_set_config('tenant_company', $data['tenant_company'] ?? '');
    _sm_set_config('tenant_email', $data['tenant_email'] ?? '');
    _sm_set_config('tenant_phone', $data['tenant_phone'] ?? '');
    if (!empty($data['gateways'])) {
        _sm_set_config('nuxhost_gateways', json_encode($data['gateways']));
    }

    return $data;
}

function _sm_fetch_invoice_history($domain)
{
    $nuxhost_url = _sm_nuxhost_url();
    if (empty($nuxhost_url)) return [];
    
    $url = $nuxhost_url . '/?app_route=isp_api_invoice_history&domain=' . urlencode($domain);
    [$httpCode, $response] = _sm_curl_get($url);
    if ($httpCode !== 200) return [];
    
    $data = json_decode($response, true);
    if (!$data || ($data['status'] ?? '') !== 'ok') return [];
    
    return $data['invoices'] ?? [];
}

function _sm_sync_subscription_expiry($domain)
{
    $nuxhost_url = _sm_nuxhost_url();
    if (empty($nuxhost_url)) {
        return null;
    }
    $url = $nuxhost_url . '/?app_route=isp_api_subscription_status&domain=' . urlencode($domain);
    [$httpCode, $response] = _sm_curl_get($url);
    if ($httpCode !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    if (!$data || ($data['status'] ?? '') !== 'ok') {
        return null;
    }
    if (!empty($data['subscription_end'])) {
        _sm_set_config('nuxhost_subscription_expires', $data['subscription_end']);
    }
    _sm_set_config('nuxhost_account_status', $data['account_status'] ?? '');
    if (!empty($data['grace_end_date'])) {
        _sm_set_config('nuxhost_grace_end_date', $data['grace_end_date']);
    } else {
        _sm_delete_config('nuxhost_grace_end_date');
    }
    if (!empty($data['is_active'])) {
        _sm_delete_config('nbg_suspension_alert');
        _sm_delete_config('nuxhost_invoice_amount');
    }
    return $data;
}

// ─── Public endpoint: billing_report ────────────────────────────────────────

function _sm_api_billing_report()
{
    try {
        _sm_json_response([
            'status'              => 'ok',
            'domain'              => _sm_normalize_api_domain((string) ($_SERVER['HTTP_HOST'] ?? '')),
            'pppoe_active_users'  => _sm_pppoe_active_count(),
            'hotspot_revenue_30d' => _sm_hotspot_revenue_30d(),
            'currency'            => _sm_get_config('nuxhost_currency', 'KES'),
            'generated_at'        => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        _sm_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

// ─── Public endpoint: initiate_payment (AJAX proxy → NuxHost) ───────────────

function _sm_api_initiate_payment()
{
    try {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone  = trim((string) ($input['phone'] ?? $_POST['phone'] ?? ''));
    // Normalize Kenyan phone: 07XXXXXXXX → 2547XXXXXXXX
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 10 && $phone[0] === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (strlen($phone) === 9 && ($phone[0] === '7' || $phone[0] === '1')) {
        $phone = '254' . $phone;
    }
    $amount = (float) ($input['amount'] ?? $_POST['amount'] ?? 0);

    if (empty($phone) || $amount <= 0) {
        _sm_json_response(['status' => 'error', 'message' => 'Phone and amount are required.']);
        return;
    }

    $nuxhost_url = _sm_nuxhost_url();
    $domain      = _sm_normalize_api_domain((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (empty($nuxhost_url)) {
        _sm_json_response(['status' => 'error', 'message' => 'NUXHOST_URL is not set in config.php. NuxHost must provision this tenant with the billing panel URL.']);
        return;
    }

    $url = $nuxhost_url . '/?app_route=isp_api_initiate_payment';
    [$httpCode, $response, $curlErr] = _sm_curl_post($url, json_encode([
        'domain' => $domain,
        'phone'  => $phone,
        'amount' => $amount,
    ]));

    if ($curlErr) {
        _sm_json_response(['status' => 'error', 'message' => 'Could not reach NuxHost: ' . $curlErr]);
        return;
    }

    $data = json_decode($response, true);
    if (! is_array($data)) {
        $snippet = substr(preg_replace('/\s+/', ' ', (string) $response), 0, 400);
        _sm_json_response([
            'status'  => 'error',
            'message' => 'Billing server returned a non-JSON response (HTTP '.$httpCode.'). Check NUXHOST_URL, SSL, and that the panel is reachable from this server.',
            'detail'  => $snippet,
        ]);
        return;
    }

    // Always HTTP 200 so jQuery $.ajax(..., dataType: "json") hits success() and can read resp.message.
    // NuxHost may return 400 for M-Pesa errors; forwarding that status triggers the ajax error path and hides the real reason.
    if ($httpCode >= 400) {
        $data['status'] = 'error';
        if (empty($data['message'])) {
            $data['message'] = 'Payment request failed (billing HTTP '.$httpCode.').';
        }
    }
    _sm_json_response($data);
    } catch (Throwable $e) {
        _sm_json_response(['status' => 'error', 'message' => 'Payment bridge error: '.$e->getMessage()], 500);
    }
}

// ─── Public endpoint: check_payment (AJAX proxy → NuxHost) ──────────────────

function _sm_api_check_payment()
{
    try {
    $payment_id  = trim($_GET['payment_id'] ?? '');
    $nuxhost_url = _sm_nuxhost_url();
    $domain      = _sm_normalize_api_domain((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (empty($payment_id)) {
        _sm_json_response(['status' => 'error', 'message' => 'payment_id is required.']);
        return;
    }

    $url = $nuxhost_url . '/?app_route=isp_api_payment_status&payment_id=' . urlencode($payment_id) . '&domain=' . urlencode($domain);
    [$httpCode, $response, $curlErr] = _sm_curl_get($url);

    if ($curlErr) {
        _sm_json_response(['status' => 'error', 'message' => 'Could not reach billing system: ' . $curlErr, 'payment_status' => 'pending']);
        return;
    }

    $data = json_decode($response, true);
    if (! is_array($data)) {
        _sm_json_response([
            'status'          => 'error',
            'message'         => 'Invalid response from billing server (HTTP '.$httpCode.').',
            'payment_status'  => 'pending',
            'detail'          => substr(preg_replace('/\s+/', ' ', (string) $response), 0, 400),
        ]);
        return;
    }

    // On payment completion → clear local suspension alert + sync subscription
    if (($data['payment_status'] ?? '') === 'completed') {
        // Clear suspension lock directly in local DB (reliable, no external credentials needed)
        try {
            _sm_delete_config('nbg_suspension_alert');
            _sm_delete_config('nuxhost_invoice_amount');
            _sm_delete_config('nuxhost_grace_end_date');
        } catch (Exception $_e) { /* non-fatal */ }
        _sm_sync_subscription_expiry($domain);
    }

    if ($httpCode >= 400) {
        $data['status'] = $data['status'] ?? 'error';
        if (empty($data['message'])) {
            $data['message'] = 'Billing server error (HTTP '.$httpCode.').';
        }
    }
    _sm_json_response($data);
    } catch (Throwable $e) {
        _sm_json_response(['status' => 'error', 'message' => 'Status bridge error: '.$e->getMessage(), 'payment_status' => 'pending'], 500);
    }
}

// ─── Public endpoint: sync_subscription ─────────────────────────────────────

function _sm_api_sync_subscription()
{
    $domain = _sm_normalize_api_domain((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $result = _sm_sync_subscription_expiry($domain);

    if ($result) {
        _sm_json_response(['status' => 'ok', 'subscription_end' => $result['subscription_end'] ?? '', 'is_active' => $result['is_active'] ?? false]);
    } else {
        _sm_json_response(['status' => 'error', 'message' => 'Could not sync subscription status.']);
    }
}

// ─── Admin page ──────────────────────────────────────────────────────────────

function subscription_manager()
{
    _admin();
    global $ui, $admin;
    $sm_build = '2026-04-22-stkfix-1';

    $domain   = _sm_normalize_api_domain((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $msg      = '';
    $msg_type = 'success';

    // Auto-sync pricing on load
    _sm_fetch_pricing($domain);

    // ── Handle POSTs ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['sync_pricing'])) {
            $result = _sm_fetch_pricing($domain);
            if ($result) {
                $msg = 'Pricing synced successfully.';
            } else {
                $msg      = 'Pricing sync failed. Check your network connection.';
                $msg_type = 'danger';
            }
        } elseif (isset($_POST['sync_subscription'])) {
            $result = _sm_sync_subscription_expiry($domain);
            if ($result) {
                $msg = 'Subscription status synced. Expires: ' . ($result['subscription_end'] ?? 'N/A');
            } else {
                $msg      = 'Subscription sync failed.';
                $msg_type = 'danger';
            }
        }
    }

    // ── Billing stats ──
    try {
        $pppoe_count     = _sm_pppoe_active_count();
        $hotspot_revenue = _sm_hotspot_revenue_30d();
    } catch (Exception $e) {
        $pppoe_count     = 0;
        $hotspot_revenue = 0;
    }

    $invoices = _sm_fetch_invoice_history($domain);
    $pending_invoice = null;
    foreach ($invoices as $invoice) {
        if (($invoice['status'] ?? '') === 'pending') {
            $pending_invoice = $invoice;
            break;
        }
    }

    // ── Invoice calculation ──
    $pppoe_rate    = (float) _sm_get_config('nuxhost_pppoe_rate', 0);
    $hotspot_rate  = (float) _sm_get_config('nuxhost_hotspot_rate', 0);
    $minimum_pay   = (float) _sm_get_config('nuxhost_minimum_pay', 0);
    $currency      = $pending_invoice['currency'] ?? _sm_get_config('nuxhost_currency', 'KES');

    $pppoe_amount    = $pppoe_count * $pppoe_rate;
    $hotspot_amount  = $hotspot_revenue * ($hotspot_rate / 100); // rate is stored as % (e.g. 2.5 = 2.5%)
    $calculated_total = (int) ceil($pppoe_amount + $hotspot_amount); // round UP to nearest whole number
    $amount_due      = (int) ceil(max($calculated_total, $minimum_pay));

    // If NuxHost cron pushed the authoritative invoice amount to tbl_appconfig, use that
    // (it matches NuxHost's invoices table exactly — ensures admin and tenant see the same number)
    $pushed_invoice_amount = (int) _sm_get_config('nuxhost_invoice_amount', 0);
    if ($pushed_invoice_amount > 0) {
        $amount_due = $pushed_invoice_amount;
    }
    if (!empty($pending_invoice)) {
        $amount_due = (int) ceil((float) ($pending_invoice['amount'] ?? $amount_due));
    }

    // ── Subscription status ──
    $subscription_expires = _sm_get_config('nuxhost_subscription_expires', '');
    $account_status       = _sm_get_config('nuxhost_account_status', '');
    $is_expired           = $account_status !== 'Paid'
                            && !empty($subscription_expires)
                            && strtotime($subscription_expires) < time();
    $days_remaining       = !empty($subscription_expires)
                            ? max(0, (int) ceil((strtotime($subscription_expires) - time()) / 86400))
                            : null;

    // ── Assign Smarty variables ──
    $ui->assign('_title', 'Subscription Manager');
    $ui->assign('_admin', $admin);
    $ui->assign('pppoe_rate', $pppoe_rate);
    $ui->assign('hotspot_rate', $hotspot_rate);
    $ui->assign('minimum_pay', $minimum_pay);
    $ui->assign('currency', $currency);
    $ui->assign('last_sync', _sm_get_config('nuxhost_last_sync', ''));
    $ui->assign('nuxhost_company', _sm_get_config('nuxhost_company', ''));
    $ui->assign('nuxhost_email', _sm_get_config('nuxhost_email', ''));
    $ui->assign('tenant_company', _sm_get_config('tenant_company', ''));
    $ui->assign('tenant_email', _sm_get_config('tenant_email', ''));
    $ui->assign('pppoe_count', $pppoe_count);
    $ui->assign('hotspot_revenue', $hotspot_revenue);
    $ui->assign('pppoe_amount', $pppoe_amount);
    $ui->assign('hotspot_amount', $hotspot_amount);
    $ui->assign('calculated_total', $calculated_total);
    $ui->assign('amount_due', $amount_due);
    $ui->assign('subscription_expires', $subscription_expires);
    $ui->assign('is_expired', $is_expired);
    $ui->assign('days_remaining', $days_remaining);
    $ui->assign('invoices', $invoices);
    $ui->assign('pending_invoice', $pending_invoice);
    $ui->assign('domain', $domain);
    $ui->assign('tenant_phone', _sm_get_config('tenant_phone', ''));
    $gateways_json = _sm_get_config('nuxhost_gateways', '[]');
    $ui->assign('gateways', json_decode($gateways_json, true) ?: []);
    $ui->assign('msg', $msg);
    $ui->assign('msg_type', $msg_type);
    $ui->assign('sm_build', $sm_build);
    
    // ── Widget data for subscription status indicator ──
    $widget_data = _sm_widget_data();
    $ui->assign('widget_data', $widget_data);
    
    $ui->display('subscription_manager.tpl');
}
