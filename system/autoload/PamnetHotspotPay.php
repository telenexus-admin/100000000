<?php

/**
 * Heals Hotspot payments when M-Pesa C2B confirmation arrives
 * but STK callback never marks tbl_payment_gateway paid / never recharges.
 */
class PamnetHotspotPay
{
    public static function parseBillRef($billRef)
    {
        $b = trim((string) $billRef);
        if ($b === '') {
            return '';
        }
        if (stripos($b, 'Hotspot-') === 0) {
            return substr($b, 8);
        }
        // Useless Till STK AccountReference — not a Hotspot code
        if (strcasecmp($b, 'Payment for Hotspot') === 0 || strcasecmp($b, 'Hotspot') === 0) {
            return '';
        }
        // Formats like "46704-2547..." or "46704-to-3353"
        if (preg_match('/^(\d{4,7})([-_].+)?$/', $b, $m)) {
            return $m[1];
        }
        return $b;
    }

    /** Normalize Kenyan MSISDN variants to digits for matching. */
    public static function normalizePhone($phone)
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === null || $d === '') {
            return '';
        }
        if (strpos($d, '254') === 0 && strlen($d) >= 12) {
            return $d;
        }
        if ($d[0] === '0' && strlen($d) >= 10) {
            return '254' . substr($d, 1);
        }
        if (strlen($d) === 9 && ($d[0] === '7' || $d[0] === '1')) {
            return '254' . $d;
        }
        return $d;
    }

    public static function phonesMatch($a, $b)
    {
        $na = self::normalizePhone($a);
        $nb = self::normalizePhone($b);
        if ($na === '' || $nb === '') {
            return false;
        }
        return $na === $nb
            || substr($na, -9) === substr($nb, -9);
    }

    public static function isTransUsed($transId)
    {
        $transId = trim((string) $transId);
        if ($transId === '') {
            return true;
        }
        try {
            // Only treat as used when payment gateway is fully paid (status=2)
            $pg = ORM::for_table('tbl_payment_gateway')->where('gateway_trx_id', $transId)->find_one();
            if ($pg && (int) $pg['status'] === 2) {
                return true;
            }
            if (ORM::for_table('tbl_transactions')->where('invoice', $transId)->find_one()) {
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    public static function planIdFromPrice($amount)
    {
        $amountInt = (string) (int) round((float) $amount);
        try {
            $plan = ORM::for_table('tbl_plans')
                ->where('type', 'Hotspot')
                ->where('enabled', '1')
                ->where('price', $amountInt)
                ->order_by_asc('id')
                ->find_one();
            if ($plan) {
                return (int) $plan['id'];
            }
            // Some installs store price as decimal string
            $plan = ORM::for_table('tbl_plans')
                ->where('type', 'Hotspot')
                ->where('enabled', '1')
                ->where_raw('CAST(price AS UNSIGNED) = ?', [(int) $amountInt])
                ->order_by_asc('id')
                ->find_one();
            if ($plan) {
                return (int) $plan['id'];
            }
        } catch (Throwable $e) {
        }
        return 0;
    }

    /**
     * Portal verify fast-path: mark matching C2B payment as paid in DB only.
     * Does NOT call Package::rechargeUser / MikroTik (that blocked verify for seconds).
     * Caller must finish recharge in the background after responding to the phone.
     *
     * @return array{ok:bool,needs_recharge?:bool,trans_id?:string,plan_id?:int,routers?:string,customer_id?:int,amount?:float,username?:string,message?:string}
     */
    public static function quickMarkPaidFromC2B($username, $hours = 6)
    {
        $username = trim((string) $username);
        $hours = max(1, min(48, (int) $hours));
        if ($username === '') {
            return ['ok' => false, 'message' => 'empty username'];
        }

        $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if (!$customer) {
            return ['ok' => false, 'message' => 'no customer'];
        }

        $custPhone = self::normalizePhone($customer['phonenumber'] ?? '');

        // Pending STK row for this code (used to match Till STK C2B with bad BillRef).
        $pendingPg = ORM::for_table('tbl_payment_gateway')
            ->where('username', $username)
            ->where('status', 1)
            ->order_by_desc('id')
            ->find_one();
        $pendingAmount = $pendingPg ? (int) round((float) $pendingPg['price']) : 0;

        try {
            $txs = [];
            $seen = [];
            $addTx = function ($row) use (&$txs, &$seen) {
                $id = (string) ($row['id'] ?? '');
                $tid = trim((string) ($row['TransID'] ?? ''));
                $key = $id !== '' ? $id : $tid;
                if ($key === '' || isset($seen[$key])) {
                    return;
                }
                $seen[$key] = true;
                $txs[] = $row;
            };

            foreach (ORM::for_table('tbl_mpesa_transactions')
                ->where_raw(
                    '(BillRefNumber = ? OR BillRefNumber = ? OR BillRefNumber = ? OR BillRefNumber LIKE ? OR BillRefNumber LIKE ?)',
                    [$username, 'Hotspot-' . $username, 'Payment for Hotspot', $username . '-%', $username . '_%']
                )
                ->where_raw('CreatedAt >= DATE_SUB(NOW(), INTERVAL ? HOUR)', [$hours])
                ->order_by_desc('id')
                ->limit(20)
                ->find_many() as $row) {
                $addTx($row);
            }

            // Till STK / odd BillRef: match recent C2B by phone to this Hotspot customer.
            if ($custPhone !== '') {
                foreach (ORM::for_table('tbl_mpesa_transactions')
                    ->where_raw('CreatedAt >= DATE_SUB(NOW(), INTERVAL ? HOUR)', [$hours])
                    ->order_by_desc('id')
                    ->limit(40)
                    ->find_many() as $row) {
                    if (self::phonesMatch($row['MSISDN'] ?? '', $custPhone)) {
                        $addTx($row);
                    }
                }
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'mpesa query failed'];
        }

        foreach ($txs as $tx) {
            $transId = trim((string) ($tx['TransID'] ?? ''));
            if ($transId === '') {
                continue;
            }

            $billRef = trim((string) ($tx['BillRefNumber'] ?? ''));
            $amount = (float) ($tx['TransAmount'] ?? 0);
            $amountInt = (int) round($amount);
            $msisdn = (string) ($tx['MSISDN'] ?? '');

            $billOk = (
                $billRef === $username
                || strcasecmp($billRef, 'Hotspot-' . $username) === 0
                || stripos($billRef, $username . '-') === 0
                || stripos($billRef, $username . '_') === 0
                || self::parseBillRef($billRef) === $username
            );
            $phoneOk = ($custPhone !== '' && self::phonesMatch($msisdn, $custPhone));
            $amountOk = ($pendingAmount <= 0 || $amountInt === $pendingAmount || abs($amountInt - $pendingAmount) <= 1);
            // Accept Till STK generic BillRef only when phone+amount match this pending purchase.
            $genericBill = (strcasecmp($billRef, 'Payment for Hotspot') === 0 || $billRef === '');
            if ($billOk) {
                // exact account match — always ok
            } elseif ($genericBill && $phoneOk && $amountOk) {
                // Till STK C2B heal
            } elseif ($phoneOk && $amountOk && $pendingPg) {
                // Phone paid same amount while this code has a pending STK
            } else {
                continue;
            }

            if (self::isTransUsed($transId)) {
                self::ensurePaidGateway($username, $transId, $amount);
                $needsRecharge = !self::usernameHasActivePlan($username);
                $pg = ORM::for_table('tbl_payment_gateway')
                    ->where('username', $username)
                    ->order_by_desc('id')
                    ->find_one();
                $planId = $pg ? (int) $pg['plan_id'] : self::planIdFromPrice($amount);
                $routers = $pg ? (string) $pg['routers'] : 'PMNINTERNET';
                if ($routers === '' || $routers === '0') {
                    $routers = 'PMNINTERNET';
                }
                return [
                    'ok' => true,
                    'needs_recharge' => $needsRecharge && $planId > 0,
                    'trans_id' => $transId,
                    'plan_id' => $planId,
                    'routers' => $routers,
                    'customer_id' => (int) $customer['id'],
                    'amount' => $amount,
                    'username' => $username,
                    'message' => $needsRecharge ? 'paid invoice — recharge needed' : 'already activated',
                ];
            }

            $pg = null;
            foreach (ORM::for_table('tbl_payment_gateway')
                ->where('username', $username)
                ->where('status', 1)
                ->order_by_desc('id')
                ->limit(10)
                ->find_many() as $cand) {
                if ((int) round((float) $cand['price']) === $amountInt) {
                    $pg = $cand;
                    break;
                }
            }
            if (!$pg) {
                $pg = ORM::for_table('tbl_payment_gateway')
                    ->where('username', $username)
                    ->where('status', 1)
                    ->order_by_desc('id')
                    ->find_one();
            }

            $planId = $pg ? (int) $pg['plan_id'] : self::planIdFromPrice($amount);
            $routers = $pg ? (string) $pg['routers'] : 'PMNINTERNET';
            if ($planId <= 0) {
                continue;
            }
            if ($routers === '' || $routers === '0') {
                $routers = 'PMNINTERNET';
            }

            $now = date('Y-m-d H:i:s');
            if ($pg) {
                $pg->status = 2;
                $pg->paid_date = $now;
                $pg->gateway_trx_id = $transId;
                $pg->pg_paid_response = 'Paid (quick C2B — recharge deferred)';
                $pg->save();
            } else {
                try {
                    $plan = ORM::for_table('tbl_plans')->where('id', $planId)->find_one();
                    $row = ORM::for_table('tbl_payment_gateway')->create();
                    $row->username = $username;
                    $row->gateway = 'mpesa';
                    $row->gateway_trx_id = $transId;
                    $row->plan_id = $planId;
                    $row->plan_name = $plan ? $plan['name_plan'] : 'Hotspot';
                    $row->routers_id = 1;
                    $row->routers = $routers;
                    $row->price = $amountInt;
                    $row->payment_method = 'Mpesa C2B Auto';
                    $row->payment_channel = 'Mpesa C2B Auto';
                    $row->pg_paid_response = 'Paid (quick C2B — recharge deferred)';
                    $row->status = 2;
                    $row->created_date = $now;
                    $row->paid_date = $now;
                    $row->save();
                } catch (Throwable $e) {
                }
            }

            return [
                'ok' => true,
                'needs_recharge' => true,
                'trans_id' => $transId,
                'plan_id' => $planId,
                'routers' => $routers,
                'customer_id' => (int) $customer['id'],
                'amount' => $amount,
                'username' => $username,
            ];
        }

        // Last resort: recent paid gateway / invoice already in DB for this code.
        try {
            $paid = ORM::for_table('tbl_payment_gateway')
                ->where('username', $username)
                ->where('status', 2)
                ->where_raw('paid_date >= DATE_SUB(NOW(), INTERVAL ? HOUR)', [$hours])
                ->order_by_desc('id')
                ->find_one();
            if ($paid) {
                return [
                    'ok' => true,
                    'needs_recharge' => !self::usernameHasActivePlan($username),
                    'trans_id' => (string) ($paid['gateway_trx_id'] ?? ''),
                    'plan_id' => (int) $paid['plan_id'],
                    'routers' => (string) ($paid['routers'] ?: 'PMNINTERNET'),
                    'customer_id' => (int) $customer['id'],
                    'amount' => (float) $paid['price'],
                    'username' => $username,
                    'message' => 'gateway already paid',
                ];
            }
            $inv = ORM::for_table('tbl_transactions')
                ->where('username', $username)
                ->where_raw("CONCAT(recharged_on, ' ', recharged_time) >= DATE_SUB(NOW(), INTERVAL ? HOUR)", [$hours])
                ->order_by_desc('id')
                ->find_one();
            if ($inv) {
                self::ensurePaidGateway($username, (string) $inv['invoice'], (float) $inv['price']);
                return [
                    'ok' => true,
                    'needs_recharge' => !self::usernameHasActivePlan($username),
                    'trans_id' => (string) $inv['invoice'],
                    'plan_id' => 0,
                    'routers' => (string) ($inv['routers'] ?: 'PMNINTERNET'),
                    'customer_id' => (int) $customer['id'],
                    'amount' => (float) $inv['price'],
                    'username' => $username,
                    'message' => 'invoice exists',
                ];
            }
        } catch (Throwable $e) {
        }

        return ['ok' => false, 'message' => 'no unused c2b'];
    }

    /**
     * Finish deferred recharge + router sync after portal already got Resultcode=3.
     */
    public static function completeDeferredRecharge(array $meta)
    {
        $username = trim((string) ($meta['username'] ?? ''));
        $customerId = (int) ($meta['customer_id'] ?? 0);
        $planId = (int) ($meta['plan_id'] ?? 0);
        $routers = trim((string) ($meta['routers'] ?? 'PMNINTERNET'));
        $transId = trim((string) ($meta['trans_id'] ?? ''));
        if ($username === '' || $customerId <= 0 || $planId <= 0) {
            if ($username !== '') {
                self::connectDeviceNow($username);
            }
            return;
        }
        if ($routers === '' || $routers === '0') {
            $routers = 'PMNINTERNET';
        }
        try {
            if (class_exists('Package') && $transId !== '') {
                Package::rechargeUser(
                    $customerId,
                    $routers,
                    $planId,
                    'mpesa',
                    'C2B-Auto',
                    'Auto-activate from C2B ' . $transId,
                    $transId
                );
            }
        } catch (Throwable $e) {
            if (function_exists('_log')) {
                _log('PamnetHotspotPay deferred recharge ' . $username . ': ' . $e->getMessage(), 'System', 1);
            }
        }
        // Critical: put the paying phone online immediately (do not wait for portal PAP).
        self::connectDeviceNow($username);
    }

    /**
     * Ensure Hotspot user exists on MikroTik and force-login the portal device.
     * Called from C2B/STK the moment money is confirmed — this is what removes the
     * multi-minute wait on the Sign-In page after M-Pesa PIN.
     */
    public static function connectDeviceNow($username)
    {
        $username = trim((string) $username);
        if ($username === '') {
            return ['ok' => false, 'message' => 'empty'];
        }
        $ok = self::ensureHotspotUser($username);
        $loggedIn = false;
        $msg = $ok ? 'user ensured' : 'ensure failed';
        $ip = '';
        $mac = '';

        if (function_exists('PamnetLoadPortalClient')) {
            list($ip, $mac) = PamnetLoadPortalClient($username);
        }
        // Grant stores MAC on payment_gateway — use it when portal cache is empty
        if ($mac === '' || $ip === '') {
            try {
                $pg = ORM::for_table('tbl_payment_gateway')
                    ->where('username', $username)
                    ->order_by_desc('id')
                    ->find_one();
                if ($pg) {
                    $pgMac = function_exists('PamnetNormalizeMac')
                        ? PamnetNormalizeMac($pg['mac_address'] ?? '')
                        : strtoupper(trim(str_replace('-', ':', (string) ($pg['mac_address'] ?? ''))));
                    if ($mac === '' && $pgMac !== '') {
                        $mac = $pgMac;
                    }
                }
            } catch (Throwable $ePg) {
            }
        }
        // Still missing — pick likely Hotspot host from MikroTik (unauthorized / 10.x)
        if (($ip === '' || $mac === '') && function_exists('PamnetHotspotAutologin')) {
            $guess = self::guessPortalIdentityFromRouter($username);
            if ($ip === '' && !empty($guess['ip'])) {
                $ip = $guess['ip'];
            }
            if ($mac === '' && !empty($guess['mac'])) {
                $mac = $guess['mac'];
            }
        }

        if (function_exists('PamnetHotspotAutologin') && ($ip !== '' || $mac !== '')) {
            try {
                if (function_exists('PamnetStorePortalClient') && ($ip !== '' || $mac !== '')) {
                    PamnetStorePortalClient($username, $ip, $mac);
                }
                $al = PamnetHotspotAutologin($username, $ip, $mac);
                $loggedIn = !empty($al['ok']) || !empty($al['logged_in']);
                $msg = (string) ($al['message'] ?? $msg);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
            }
        } elseif ($ip === '' && $mac === '') {
            $msg = 'no cached portal ip/mac yet';
        }

        if ($loggedIn && class_exists('PamnetCustomerStatus')) {
            PamnetCustomerStatus::touchUsageSession($username, 0, $ip, $mac);
        }

        if (function_exists('_log')) {
            _log('PamnetHotspotPay connectDeviceNow ' . $username . ' ensure=' . ($ok ? '1' : '0') . ' logged_in=' . ($loggedIn ? '1' : '0') . ' ip=' . $ip . ' mac=' . $mac . ' ' . $msg, 'System', 1);
        }
        return ['ok' => $ok || $loggedIn, 'logged_in' => $loggedIn, 'message' => $msg, 'ip' => $ip, 'mac' => $mac];
    }

    /**
     * Find a likely phone still on captive portal (unauthorized Hotspot host on LAN).
     * @return array{ip?:string,mac?:string}
     */
    public static function guessPortalIdentityFromRouter($username)
    {
        $username = trim((string) $username);
        $out = [];
        try {
            $recharge = ORM::for_table('tbl_user_recharges')
                ->where('username', $username)
                ->order_by_desc('id')
                ->find_one();
            $plan = $recharge
                ? ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one()
                : null;
            $routerName = trim((string) ($plan['routers'] ?? ($recharge['routers'] ?? '')));
            $router = null;
            if ($routerName !== '') {
                $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
            }
            if (!$router) {
                $router = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('id')->find_one();
            }
            if (!$router || !class_exists('Mikrotik')) {
                return $out;
            }
            $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
            $candidates = [];
            foreach ($client->sendSync(new \PEAR2\Net\RouterOS\Request('/ip/hotspot/host/print')) as $h) {
                $hIp = trim((string) $h->getProperty('address'));
                $hMac = function_exists('PamnetNormalizeMac')
                    ? PamnetNormalizeMac($h->getProperty('mac-address'))
                    : strtoupper(trim(str_replace('-', ':', (string) $h->getProperty('mac-address'))));
                $authorized = ((string) $h->getProperty('authorized') === 'true');
                $bypassed = ((string) $h->getProperty('bypassed') === 'true');
                if ($hMac === '' || $hIp === '') {
                    continue;
                }
                if (!preg_match('/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $hIp)) {
                    continue;
                }
                // Prefer unauthorized hosts waiting on captive portal
                $score = 0;
                if (!$authorized && !$bypassed) {
                    $score += 10;
                }
                if ($authorized) {
                    $score += 1;
                }
                $candidates[] = ['ip' => $hIp, 'mac' => $hMac, 'score' => $score];
            }
            if (!$candidates) {
                return $out;
            }
            usort($candidates, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            // If only one unauthorized host, that's almost certainly the payer
            $top = $candidates[0];
            $unauth = array_filter($candidates, function ($c) {
                return $c['score'] >= 10;
            });
            if (count($unauth) === 1) {
                $top = array_values($unauth)[0];
            } elseif (count($unauth) > 1) {
                // Ambiguous — still try top unauthorized (better than nothing for single-payer sites)
                $top = array_values($unauth)[0];
            }
            $out = ['ip' => $top['ip'], 'mac' => $top['mac']];
        } catch (Throwable $e) {
        }
        return $out;
    }

    /**
     * Activate one unused C2B payment for this Hotspot username.
     * @return array{ok:bool,trans_id?:string,username?:string,message?:string}
     */
    public static function activateFromC2B($username, $hours = 48)
    {
        // Prefer fast mark + deferred recharge (used by verify). Full path kept for cron/tools.
        $quick = self::quickMarkPaidFromC2B($username, min(48, (int) $hours));
        if (!empty($quick['ok']) && !empty($quick['needs_recharge'])) {
            self::completeDeferredRecharge($quick);
            return ['ok' => true, 'trans_id' => $quick['trans_id'] ?? '', 'username' => $username];
        }
        if (!empty($quick['ok'])) {
            // Already activated earlier — still force-login this phone now.
            self::connectDeviceNow($username);
            return ['ok' => true, 'username' => $username, 'message' => $quick['message'] ?? 'already activated'];
        }
        return ['ok' => false, 'message' => $quick['message'] ?? 'no unused c2b'];
    }

    /**
     * Mark matching payment_gateway row paid when invoice already exists.
     */
    public static function ensurePaidGateway($username, $transId, $amount = 0)
    {
        $username = trim((string) $username);
        $transId = trim((string) $transId);
        if ($username === '' || $transId === '') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $amountInt = (int) round((float) $amount);
        try {
            $pg = ORM::for_table('tbl_payment_gateway')
                ->where('gateway_trx_id', $transId)
                ->find_one();
            if (!$pg) {
                $pg = ORM::for_table('tbl_payment_gateway')
                    ->where('username', $username)
                    ->where('status', 1)
                    ->order_by_desc('id')
                    ->find_one();
                if ($pg && $amountInt > 0 && (int) round((float) $pg['price']) !== $amountInt) {
                    // Prefer exact amount match among pending
                    foreach (ORM::for_table('tbl_payment_gateway')
                        ->where('username', $username)
                        ->where('status', 1)
                        ->order_by_desc('id')
                        ->find_many() as $cand) {
                        if ((int) round((float) $cand['price']) === $amountInt) {
                            $pg = $cand;
                            break;
                        }
                    }
                }
            }
            if ($pg && (int) $pg['status'] !== 2) {
                $pg->status = 2;
                $pg->paid_date = $now;
                $pg->gateway_trx_id = $transId;
                if (empty($pg['pg_paid_response'])) {
                    $pg->pg_paid_response = 'Confirmed (idempotent)';
                }
                $pg->save();
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Ensure MikroTik Hotspot user exists for an active recharge.
     */
    public static function ensureHotspotUser($username)
    {
        $username = trim((string) $username);
        if ($username === '') {
            return false;
        }
        $ok = false;
        if (function_exists('PamnetEnsureHotspotOnRouter')) {
            $ok = (bool) PamnetEnsureHotspotOnRouter($username);
        } else {
            $ok = self::ensureHotspotUserFallback($username);
        }
        // Do not autologin here — that blocked payment verify/redirect for seconds.
        // Portal PAP + VerifyHotspot post-response autologin authorize the device.
        return $ok;
    }

    private static function ensureHotspotUserFallback($username)
    {
        global $_app_stage, $DEVICE_PATH;
        try {
            $recharge = ORM::for_table('tbl_user_recharges')
                ->where('username', $username)
                ->order_by_desc('id')
                ->find_one();
            if (!$recharge) {
                return false;
            }
            $exp = strtotime(trim(($recharge['expiration'] ?? '') . ' ' . ($recharge['time'] ?? '23:59:59')));
            if ($exp !== false && $exp > time() && (string) $recharge['status'] !== 'on') {
                $recharge->status = 'on';
                $recharge->save();
            }
            if ((string) $recharge['status'] !== 'on') {
                return false;
            }
            $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
            if (!$customer) {
                $customer = ORM::for_table('tbl_customers')->where('id', $recharge['customer_id'])->find_one();
            }
            $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
            if (!$customer || !$plan) {
                return false;
            }
            if (empty($plan['routers']) && !empty($recharge['routers'])) {
                $plan['routers'] = $recharge['routers'];
            }
            if (empty($customer['password']) || (string) $customer['password'] !== '1234') {
                $customer->password = '1234';
                $customer->save();
            }
            if (isset($_app_stage) && strtolower((string) $_app_stage) === 'demo') {
                return true;
            }
            $dvc = Package::getDevice($plan);
            if (!$dvc || !file_exists($dvc)) {
                return false;
            }
            require_once $dvc;
            $device = new $plan['device']();
            if (method_exists($device, 'add_customer')) {
                $device->add_customer($customer, $plan);
            } elseif (method_exists($device, 'sync_customer')) {
                $device->sync_customer($customer, $plan);
            }
            return true;
        } catch (Throwable $e) {
            if (function_exists('_log')) {
                _log('PamnetHotspotPay ensure ' . $username . ': ' . $e->getMessage(), 'System', 1);
            }
            return false;
        }
    }

    /**
     * Cron/CLI: activate all unused Hotspot-* C2B payments from the last N hours.
     */
    public static function healRecent($hours = 6)
    {
        $hours = max(1, (int) $hours);
        $done = [];
        try {
            $txs = ORM::for_table('tbl_mpesa_transactions')
                ->where_raw("BillRefNumber LIKE 'Hotspot-%'")
                ->where_raw('CreatedAt >= DATE_SUB(NOW(), INTERVAL ? HOUR)', [$hours])
                ->order_by_desc('id')
                ->find_many();
        } catch (Throwable $e) {
            return $done;
        }
        foreach ($txs as $tx) {
            $transId = trim((string) ($tx['TransID'] ?? ''));
            if ($transId === '' || self::isTransUsed($transId)) {
                continue;
            }
            $user = self::parseBillRef($tx['BillRefNumber'] ?? '');
            if ($user === '') {
                continue;
            }
            $res = self::activateFromC2B($user, $hours);
            if (!empty($res['ok'])) {
                $done[] = $user . ':' . ($res['trans_id'] ?? $transId);
            }
        }
        return $done;
    }

    /**
     * True when this Hotspot username/code still has an unexpired active plan.
     * Active codes must not receive another package until they expire.
     */
    public static function usernameHasActivePlan($username)
    {
        $username = trim((string) $username);
        if ($username === '') {
            return false;
        }
        $now = time();
        try {
            $rows = ORM::for_table('tbl_user_recharges')
                ->where('username', $username)
                ->find_many();
            foreach ($rows as $r) {
                $status = strtolower(trim((string) ($r['status'] ?? '')));
                if ($status !== 'on') {
                    continue;
                }
                $expDate = trim((string) ($r['expiration'] ?? ''));
                if ($expDate === '') {
                    continue;
                }
                $expTime = trim((string) ($r['time'] ?? ''));
                if ($expTime === '') {
                    $expTime = '23:59:59';
                }
                $exp = strtotime($expDate . ' ' . $expTime);
                if ($exp === false) {
                    $exp = strtotime($expDate);
                }
                if ($exp !== false && $exp > $now) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    /** True when username already exists as a customer or still has an active plan. */
    public static function usernameIsTaken($username)
    {
        $username = trim((string) $username);
        if ($username === '') {
            return true;
        }
        try {
            if (ORM::for_table('tbl_customers')->where('username', $username)->find_one()) {
                return true;
            }
        } catch (Throwable $e) {
            return true;
        }
        return self::usernameHasActivePlan($username);
    }

    /** Mint a unique 5-digit Hotspot username/code (portal style). */
    public static function mintUniqueUsername()
    {
        for ($i = 0; $i < 120; $i++) {
            $u = (string) random_int(10000, 99999);
            if (!self::usernameIsTaken($u)) {
                return $u;
            }
        }
        for ($i = 0; $i < 40; $i++) {
            $u = (string) random_int(100000, 999999);
            if (!self::usernameIsTaken($u)) {
                return $u;
            }
        }
        return (string) random_int(1000000, 9999999);
    }

    /**
     * Create a new Hotspot customer row (used when minting a code for early repurchase).
     * @return object|false
     */
    public static function createHotspotCustomer($username, $phone, $routerId)
    {
        $username = trim((string) $username);
        $phone = trim((string) $phone);
        $routerId = trim((string) $routerId);
        if ($username === '') {
            return false;
        }
        try {
            $table = ORM::for_table('tbl_customers')->raw_query('SHOW COLUMNS FROM tbl_customers LIKE "router_id"')->find_one();
            if (!$table) {
                ORM::for_table('tbl_customers')->raw_execute('ALTER TABLE tbl_customers ADD router_id VARCHAR(255) AFTER fullname');
            }
            $defpass = '1234';
            $createUser = ORM::for_table('tbl_customers')->create();
            $createUser->username = $username;
            $createUser->password = $defpass;
            $createUser->fullname = $phone !== '' ? $phone : $username;
            $createUser->router_id = $routerId;
            $createUser->phonenumber = $phone !== '' ? $phone : $username;
            $createUser->pppoe_password = $defpass;
            $createUser->address = 'Hotspot Address';
            $createUser->email = $username . '@gmail.com';
            $createUser->service_type = 'Hotspot';
            if (!$createUser->save()) {
                return false;
            }
            return $createUser;
        } catch (Throwable $e) {
            if (function_exists('_log')) {
                _log('PamnetHotspotPay createHotspotCustomer ' . $username . ': ' . $e->getMessage(), 'System', 1);
            }
            return false;
        }
    }

    /**
     * Pick the Hotspot code for a new purchase.
     * If the requested code still has an active subscription, mint a NEW unique code
     * so the buy never overwrites or shares the live package (including shared users).
     *
     * @return array{ok:bool,username?:string,replaced?:bool,previous?:string,customer?:object,error?:string}
     */
    public static function resolvePurchaseUsername($requested, $phone, $routerId)
    {
        $requested = trim((string) $requested);
        $phone = trim((string) $phone);
        $routerId = trim((string) $routerId);
        $previous = $requested;

        if ($requested !== '' && self::usernameHasActivePlan($requested)) {
            $newAccount = self::mintUniqueUsername();
            $created = self::createHotspotCustomer($newAccount, $phone, $routerId);
            if (!$created) {
                return [
                    'ok' => false,
                    'error' => 'Could not create a new Hotspot code. Please try again.',
                ];
            }
            return [
                'ok' => true,
                'username' => $newAccount,
                'replaced' => true,
                'previous' => $previous,
                'customer' => $created,
            ];
        }

        if ($requested === '') {
            $requested = self::mintUniqueUsername();
            $previous = $requested;
        }

        try {
            $Userexist = ORM::for_table('tbl_customers')
                ->where('username', $requested)
                ->where('service_type', 'Hotspot')
                ->find_one();
        } catch (Throwable $e) {
            $Userexist = false;
        }

        if ($Userexist) {
            $Userexist->router_id = $routerId;
            $Userexist->password = '1234';
            $Userexist->phonenumber = $phone;
            $Userexist->fullname = $phone;
            $Userexist->save();
            return [
                'ok' => true,
                'username' => $requested,
                'replaced' => false,
                'previous' => $previous,
                'customer' => $Userexist,
            ];
        }

        try {
            $UserexistAny = ORM::for_table('tbl_customers')->where('username', $requested)->find_one();
        } catch (Throwable $e) {
            $UserexistAny = false;
        }
        if ($UserexistAny && strcasecmp((string) $UserexistAny->service_type, 'Hotspot') !== 0) {
            return [
                'ok' => false,
                'error' => 'This account is registered as ' . $UserexistAny->service_type . ' service, cannot convert to Hotspot',
            ];
        }

        $created = self::createHotspotCustomer($requested, $phone, $routerId);
        if (!$created) {
            return [
                'ok' => false,
                'error' => 'There was a system error when registering user, please contact support',
            ];
        }
        return [
            'ok' => true,
            'username' => $requested,
            'replaced' => false,
            'previous' => $previous,
            'customer' => $created,
        ];
    }
}
