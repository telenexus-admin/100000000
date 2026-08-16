<?php


class Package
{
    /**
     * @param int   $id_customer String user identifier
     * @param string $router_name router name for this package
     * @param int   $plan_id plan id for this package
     * @param string $gateway payment gateway name
     * @param string $channel channel payment gateway
     * @param string $note additional note
     * @param string $trx_id transaction ID from payment gateway (M-Pesa code, voucher code, etc.)
     * @return boolean
     */
    public static function rechargeUser($id_customer, $router_name, $plan_id, $gateway, $channel, $note = '', $trx_id = '')
    {
        global $config, $admin, $c, $p, $b, $t, $d, $zero, $trx, $_app_stage, $isChangePlan, $CACHE_PATH;
        // Always compute expiry in the billing timezone
        if (class_exists('MikrotikTimeSync')) {
            MikrotikTimeSync::applyPhpTimezone();
        }
        $date_only = date("Y-m-d");
        $time_only = date("H:i:s");
        $time = date("H:i:s");
        $inv = "";
        $isVoucher = false;
        $c = [];
        $trxLockFp = null;
        $trxLockPath = null;
        if ($trx && $trx['status'] == 2) {
            // if its already paid, return it
            return;
        }

        if ($id_customer == '' or $router_name == '' or $plan_id == '') {
            return false;
        }
        if (trim($gateway) == 'Voucher' && $id_customer == 0) {
            $isVoucher = true;
        }

        $p = ORM::for_table('tbl_plans')->where('id', $plan_id)->find_one();

        if (!$isVoucher) {
            $c = ORM::for_table('tbl_customers')->where('id', $id_customer)->find_one();
            if ($c['status'] != 'Active') {
                _alert(Lang::T('This account status') . ' : ' . Lang::T($c['status']), 'danger', "");
            }
        } else {
            $c = [
                'fullname' => $gateway,
                'email' => '',
                'username' => $channel,
                'password' => $channel,
            ];
        }

        // Idempotent M-Pesa / gateway codes: C2B + STK often arrive together.
        // Same invoice for same user = already processed — ensure access, do not re-extend.
        if (!empty($trx_id)) {
            // Serialize parallel C2B+STK for the same code (prevents double-extend race)
            $trxLockPath = (isset($CACHE_PATH) ? $CACHE_PATH : sys_get_temp_dir()) . '/pamnet_trx_' . preg_replace('/[^A-Za-z0-9_-]/', '', $trx_id) . '.lock';
            $trxLockFp = @fopen($trxLockPath, 'c');
            if ($trxLockFp) {
                $waitUntil = microtime(true) + 2;
                while (!flock($trxLockFp, LOCK_EX | LOCK_NB)) {
                    if (microtime(true) >= $waitUntil) {
                        break;
                    }
                    usleep(50000);
                }
            }

            $existingInv = ORM::for_table('tbl_transactions')
                ->where('invoice', $trx_id)
                ->find_one();
            if ($existingInv) {
                $existingUser = (string) ($existingInv['username'] ?? '');
                $currentUser = (string) ($c['username'] ?? '');
                if ($currentUser !== '' && $existingUser !== '' && strcasecmp($existingUser, $currentUser) !== 0) {
                    _log("Duplicate invoice rejected: $trx_id belongs to $existingUser, not $currentUser");
                    if (!empty($trxLockFp) && is_resource($trxLockFp)) {
                        @flock($trxLockFp, LOCK_UN);
                        @fclose($trxLockFp);
                    }
                    return false;
                }
                // Ensure package stays on and MikroTik user exists
                try {
                    $bOn = ORM::for_table('tbl_user_recharges')
                        ->where('username', $currentUser !== '' ? $currentUser : $existingUser)
                        ->order_by_desc('id')
                        ->find_one();
                    if ($bOn && (string) $bOn['status'] !== 'on') {
                        $expTs = strtotime(trim(($bOn['expiration'] ?? '') . ' ' . ($bOn['time'] ?? '23:59:59')));
                        if ($expTs === false || $expTs > time()) {
                            $bOn->status = 'on';
                            $bOn->save();
                        }
                    }
                } catch (Throwable $e) {
                }
                if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'ensureHotspotUser')) {
                    try {
                        PamnetHotspotPay::ensureHotspotUser($currentUser !== '' ? $currentUser : $existingUser);
                    } catch (Throwable $e) {
                    }
                }
                if ($trx) {
                    $trx->trx_invoice = $trx_id;
                }
                if (!empty($trxLockFp) && is_resource($trxLockFp)) {
                    @flock($trxLockFp, LOCK_UN);
                    @fclose($trxLockFp);
                    @unlink($trxLockPath);
                }
                return $trx_id;
            }
        }

        $add_cost = 0;
        $bills = [];
        // Zero cost recharge
        if (isset($zero) && $zero == 1) {
            $p['price'] = 0;
        } else {
            // Additional cost
            list($bills, $add_cost) = User::getBills($id_customer);
            if ($add_cost != 0 && $router_name != 'balance') {
                foreach ($bills as $k => $v) {
                    $note .= $k . " : " . Lang::moneyFormat($v) . "\n";
                }
                $note .= $p['name_plan'] . " : " . Lang::moneyFormat($p['price']) . "\n";
            }
        }


        if (!$p['enabled']) {
            // Allow voucher redemption even if plan is disabled
            if (strtolower($gateway) !== 'voucher') {
                // Non-voucher recharges require admin permission for disabled plans
                if (!isset($admin) || !isset($admin['id']) || empty($admin['id'])) {
                    r2(getUrl('home'), 'e', Lang::T('Plan Not found'));
                }
                if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
                    r2(getUrl('dashboard'), 'e', Lang::T('You do not have permission to access this page'));
                }
            }
            // Vouchers can always be redeemed regardless of plan status
        }

        if ($p['validity_unit'] == 'Period') {
            // if customer has attribute Expired Date use it
            $day_exp = User::getAttribute("Expired Date", $c['id']);
            if (!$day_exp) {
                // if customer no attribute Expired Date use plan expired date
                $day_exp = 20;
                if ($p['prepaid'] == 'no') {
                    $day_exp = $p['expired_date'];
                }
                if (empty($day_exp)) {
                    $day_exp = 20;
                }
            }
        }



        if ($router_name == 'balance') {
            return self::rechargeBalance($c, $p, $gateway, $channel);
        }

        if ($router_name == 'Custom Balance') {
            return self::rechargeCustomBalance($c, $p, $gateway, $channel);
        }

        /**
         * Hotspot system rule (portal / STK / C2B / voucher — not admin panel):
         * An active username/code must not receive another package until it expires.
         * Early repurchase must mint a new code at grant time; do not extend here.
         */
        $isHotspotPlan = strcasecmp((string) ($p['type'] ?? ''), 'Hotspot') === 0;
        $isAdminRecharge = isset($admin) && !empty($admin['id']);
        $hotspotUser = trim((string) ($c['username'] ?? ''));
        if (
            $isHotspotPlan
            && !$isAdminRecharge
            && $hotspotUser !== ''
            && class_exists('PamnetHotspotPay')
            && method_exists('PamnetHotspotPay', 'usernameHasActivePlan')
            && PamnetHotspotPay::usernameHasActivePlan($hotspotUser)
        ) {
            if (method_exists('PamnetHotspotPay', 'ensureHotspotUser')) {
                try {
                    PamnetHotspotPay::ensureHotspotUser($hotspotUser);
                } catch (Throwable $e) {
                }
            }
            if (function_exists('_log')) {
                _log("Package::rechargeUser blocked stack on active Hotspot $hotspotUser gateway=$gateway channel=$channel", 'System', 1);
            }
            if (!empty($trxLockFp) && is_resource($trxLockFp)) {
                @flock($trxLockFp, LOCK_UN);
                @fclose($trxLockFp);
                if (!empty($trxLockPath)) {
                    @unlink($trxLockPath);
                }
            }
            return $trx_id !== '' ? $trx_id : true;
        }

        /**
         * 1 Customer only can have 1 PPPOE and 1 Hotspot Plan, 1 prepaid and 1 postpaid
         */

        $query = ORM::for_table('tbl_user_recharges')
            ->select('tbl_user_recharges.id', 'id')
            ->select('customer_id')
            ->select('username')
            ->select('plan_id')
            ->select('namebp')
            ->select('recharged_on')
            ->select('recharged_time')
            ->select('expiration')
            ->select('time')
            ->select('status')
            ->select('method')
            ->select('tbl_user_recharges.routers', 'routers')
            ->select('tbl_user_recharges.type', 'type')
            ->select('admin_id')
            ->select('prepaid')
            ->where('tbl_user_recharges.routers', $router_name)
            ->where('tbl_user_recharges.Type', $p['type'])
            # PPPOE or Hotspot only can have 1 per customer prepaid or postpaid
            # because 1 customer can have 1 PPPOE and 1 Hotspot Plan in mikrotik
            //->where('prepaid', $p['prepaid'])
            ->left_outer_join('tbl_plans', array('tbl_plans.id', '=', 'tbl_user_recharges.plan_id'));
        if ($isVoucher) {
            $query->where('username', $c['username']);
        } else {
            $query->where('customer_id', $id_customer);
        }
        $b = $query->find_one();

        run_hook("recharge_user");

        if ($p['validity_unit'] == 'Months') {
            $date_exp = date("Y-m-d", strtotime('+' . $p['validity'] . ' month'));
            $time = '23:59:59';
        } else if ($p['validity_unit'] == 'Period') {
            $current_date = new DateTime($date_only);
            $exp_date = clone $current_date;
            $exp_date->modify('first day of next month');
            $exp_date->setDate($exp_date->format('Y'), $exp_date->format('m'), $day_exp);

            $min_days = 7 * $p['validity'];
            $max_days = 35 * $p['validity'];

            $days_until_exp = $exp_date->diff($current_date)->days;

            // If less than min_days away, move to the next period
            while ($days_until_exp < $min_days) {
                $exp_date->modify('+1 month');
                $days_until_exp = $exp_date->diff($current_date)->days;
            }

            // If more than max_days away, move to the previous period
            while ($days_until_exp > $max_days) {
                $exp_date->modify('-1 month');
                $days_until_exp = $exp_date->diff($current_date)->days;
            }

            // Final check to ensure we're not less than min_days or in the past
            if ($days_until_exp < $min_days || $exp_date <= $current_date) {
                $exp_date->modify('+1 month');
            }

            // Adjust for multiple periods
            if ($p['validity'] > 1) {
                $exp_date->modify('+' . ($p['validity'] - 1) . ' months');
            }

            $date_exp = $exp_date->format('Y-m-d');
            $time = "23:59:59";
        } else if ($p['validity_unit'] == 'Days') {
            $datetime = explode(' ', date("Y-m-d H:i:s", strtotime('+' . $p['validity'] . ' day')));
            $date_exp = $datetime[0];
            $time = $datetime[1];
        } else if ($p['validity_unit'] == 'Hrs' || $p['validity_unit'] == 'Hours') {
            $datetime = explode(' ', date("Y-m-d H:i:s", strtotime('+' . $p['validity'] . ' hour')));
            $date_exp = $datetime[0];
            $time = $datetime[1];
        } else if ($p['validity_unit'] == 'Mins' || $p['validity_unit'] == 'Minutes') {
            $datetime = explode(' ', date("Y-m-d H:i:s", strtotime('+' . $p['validity'] . ' minute')));
            $date_exp = $datetime[0];
            $time = $datetime[1];
        }

        // Prepare recharge record data
        $isChangePlan = false;
        $hadExistingRecharge = ($b != null); // Track if recharge record existed originally
        if ($b) {
            $lastExpired = Lang::dateAndTimeFormat($b['expiration'], $b['time']);
            if ($b['namebp'] == $p['name_plan'] && $b['status'] == 'on' && $config['extend_expiry'] == 'yes') {
                // Duplicate STK/C2B callbacks race: both see status=on before either
                // writes the invoice, and would double-extend. Skip extend when this
                // recharge was just written (same plan) within the last 10 minutes
                // and a payment trx_id is present.
                $skipExtend = false;
                if (!empty($trx_id)) {
                    $rechTs = strtotime(trim(($b['recharged_on'] ?? '') . ' ' . ($b['recharged_time'] ?? '')));
                    if ($rechTs !== false && (time() - $rechTs) >= 0 && (time() - $rechTs) < 600) {
                        $skipExtend = true;
                    }
                }
                if ($skipExtend) {
                    $date_exp = $b['expiration'];
                    $time = $b['time'];
                } else {
                // if it same internet plan, expired will extend
                switch ($p['validity_unit']) {
                    case 'Months':
                        $date_exp = date("Y-m-d", strtotime($b['expiration'] . ' +' . $p['validity'] . ' months'));
                        $time = '23:59:59';
                        break;
                    case 'Period':
                        $date_exp = date("Y-m-$day_exp", strtotime($b['expiration'] . ' +' . $p['validity'] . ' months'));
                        $time = date("23:59:00");
                        break;
                    case 'Days':
                        $datetime = explode(' ', date("Y-m-d H:i:s", strtotime($b['expiration'] . ' ' . $b['time'] . ' +' . $p['validity'] . ' days')));
                        $date_exp = $datetime[0];
                        $time = $datetime[1];
                        break;
                    case 'Hrs':
                    case 'Hours':
                        $datetime = explode(' ', date("Y-m-d H:i:s", strtotime($b['expiration'] . ' ' . $b['time'] . ' +' . $p['validity'] . ' hours')));
                        $date_exp = $datetime[0];
                        $time = $datetime[1];
                        break;
                    case 'Mins':
                    case 'Minutes':
                        $datetime = explode(' ', date("Y-m-d H:i:s", strtotime($b['expiration'] . ' ' . $b['time'] . ' +' . $p['validity'] . ' minutes')));
                        $date_exp = $datetime[0];
                        $time = $datetime[1];
                        break;
                }
                }
            } else {
                $isChangePlan = true;
            }
        }

        // Save recharge record BEFORE calling add_customer so getExpirationInfo() can find it
        // if contains 'mikrotik', 'hotspot', 'pppoe', 'radius' then recharge it
        if (Validator::containsKeyword($p['device'])) {
            if (!$b) {
                // Create new recharge record if it doesn't exist
                $b = ORM::for_table('tbl_user_recharges')->create();
            }
            $b->customer_id = $id_customer;
            $b->username = $c['username'];
            $b->plan_id = $plan_id;
            $b->namebp = $p['name_plan'];
            $b->recharged_on = $date_only;
            $b->recharged_time = $time_only;
            $b->expiration = $date_exp;
            $b->time = $time;
            $b->status = "on";
            $b->method = "$gateway - $channel";
            $b->routers = $router_name;
            $b->type = $p['type'];
            if ($admin) {
                $b->admin_id = ($admin['id']) ? $admin['id'] : '0';
            } else {
                $b->admin_id = '0';
            }
            $b->save();
        }

        // Now call add_customer after recharge record is saved (for devices that need recharging)
        //if ($b['status'] == 'on') {
        $dvc = Package::getDevice($p);
        if ($_app_stage != 'Demo') {
            try {
                if (file_exists($dvc)) {
                    require_once $dvc;
                    $device = new $p['device']();
                    try {
                        $device->add_customer($c, $p);
                    } catch (Throwable $eFirst) {
                        // One retry — transient API / router glitches after STK
                        usleep(50000);
                        $device->add_customer($c, $p);
                    }
                } else {
                    throw new Exception(Lang::T("Devices Not Found"));
                }
            } catch (Throwable $e) {
                Message::sendTelegram(
                    "System Error. When activate Package. You need to sync manually\n" .
                        "Router: $router_name\n" .
                        "Customer: u$c[username]\n" .
                        "Plan: p$p[name_plan]\n" .
                        $e->getMessage() . "\n" .
                        $e->getTraceAsString()
                );
            } catch (Exception $e) {
                Message::sendTelegram(
                    "System Error. When activate Package. You need to sync manually\n" .
                        "Router: $router_name\n" .
                        "Customer: u$c[username]\n" .
                        "Plan: p$p[name_plan]\n" .
                        $e->getMessage() . "\n" .
                        $e->getTraceAsString()
                );
            }
        }
        //}

        // insert table transactions
        $t = ORM::for_table('tbl_transactions')->create();
        // Use transaction ID if provided, otherwise generate INV number
        if (!empty($trx_id)) {
            $t->invoice = $inv = $trx_id;
        } else {
            $t->invoice = $inv = "INV-" . Package::_raid();
        }
        $t->username = $c['username'];
        $t->user_id = $id_customer;
        $t->plan_name = $p['name_plan'];
        // Always set price to 0 for voucher transactions and manual cash recharges
        if (strtolower($gateway) === 'voucher' || strtolower($gateway) === 'cash') {
            $t->price = 0;
        } else {
            if ($p['validity_unit'] == 'Period') {
                // Postpaid price from field
                $add_inv = User::getAttribute("Invoice", $id_customer);
                if (empty($add_inv) or $add_inv == 0) {
                    $t->price = $p['price'] + $add_cost;
                } else {
                    $t->price = $add_inv + $add_cost;
                }
            } else {
                $t->price = $p['price'] + $add_cost;
            }
        }
        $t->recharged_on = $date_only;
        $t->recharged_time = $time_only;
        $t->expiration = $date_exp;
        $t->time = $time;
        $t->method = "$gateway - $channel";
        $t->routers = $router_name;
        $t->note = $note;
        $t->type = $p['type'];
        if ($admin) {
            $t->admin_id = ($admin['id']) ? $admin['id'] : '0';
        } else {
            $t->admin_id = '0';
        }
        
        try {
            $t->save();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                // Race: another callback saved the same invoice first — treat as success
                _log("Payment already recorded (idempotent): $inv (User: {$c['username']}, Plan: {$p['name_plan']})");
                if (class_exists('PamnetHotspotPay') && method_exists('PamnetHotspotPay', 'ensureHotspotUser')) {
                    try {
                        PamnetHotspotPay::ensureHotspotUser($c['username']);
                    } catch (Throwable $ex) {
                    }
                }
            } else {
                _log("Transaction save error: " . $e->getMessage());
                if (!empty($trxLockFp) && is_resource($trxLockFp)) {
                    @flock($trxLockFp, LOCK_UN);
                    @fclose($trxLockFp);
                }
                return false;
            }
        }

        if ($p['validity_unit'] == 'Period') {
            // insert price to fields for invoice next month
            $fl = ORM::for_table('tbl_customers_fields')->where('field_name', 'Invoice')->where('customer_id', $c['id'])->find_one();
            if (!$fl) {
                $fl = ORM::for_table('tbl_customers_fields')->create();
                $fl->customer_id = $c['id'];
                $fl->field_name = 'Invoice';
                $fl->field_value = $p['price'];
                $fl->save();
            } else {
                $fl->customer_id = $c['id'];
                $fl->field_value = $p['price'];
                $fl->save();
            }
        }

        if ($hadExistingRecharge) {
            Message::sendTelegram("#u$c[username] $c[fullname] #recharge #$p[type] \n" . $p['name_plan'] .
                "\nRouter: " . $router_name .
                "\nGateway: " . $gateway .
                "\nChannel: " . $channel .
                "\nLast Expired: $lastExpired" .
                "\nNew Expired: " . Lang::dateAndTimeFormat($date_exp, $time) .
                "\nPrice: " . Lang::moneyFormat($p['price'] + $add_cost) .
                "\nNote:\n" . $note);
        } else {
            Message::sendTelegram("#u$c[username] $c[fullname] #buy #$p[type] \n" . $p['name_plan'] .
                "\nRouter: " . $router_name .
                "\nGateway: " . $gateway .
                "\nChannel: " . $channel .
                "\nExpired: " . Lang::dateAndTimeFormat($date_exp, $time) .
                "\nPrice: " . Lang::moneyFormat($p['price'] + $add_cost) .
                "\nNote:\n" . $note);
        }

        if (is_array($bills) && count($bills) > 0) {
            User::billsPaid($bills, $id_customer);
        }
        run_hook("recharge_user_finish");
        Message::sendInvoice($c, $t);
        if ($trx) {
            $trx->trx_invoice = $inv;
        }
        if (!empty($trxLockFp) && is_resource($trxLockFp)) {
            @flock($trxLockFp, LOCK_UN);
            @fclose($trxLockFp);
            if (!empty($trxLockPath)) {
                @unlink($trxLockPath);
            }
        }
        return $inv;
    }

    public static function rechargeBalance($customer, $plan, $gateway, $channel, $note = '')
    {
        global $admin, $config;
        // insert table transactions
        $t = ORM::for_table('tbl_transactions')->create();
        $t->invoice = $inv = "INV-" . Package::_raid();
        $t->username = $customer['username'];
        $t->user_id = $customer['id'];
        $t->plan_name = $plan['name_plan'];
        $t->price = $plan['price'];
        $t->recharged_on = date("Y-m-d");
        $t->recharged_time = date("H:i:s");
        $t->expiration = date("Y-m-d");
        $t->time = date("H:i:s");
        $t->method = "$gateway - $channel";
        $t->routers = 'balance';
        $t->type = "Balance";
        $t->note = $note;
        if ($admin) {
            $t->admin_id = ($admin['id']) ? $admin['id'] : '0';
        } else {
            $t->admin_id = '0';
        }
        $t->save();

        $balance_before = $customer['balance'];
        Balance::plus($customer['id'], $plan['price']);
        $balance = $customer['balance'] + $plan['price'];

        $textInvoice = Lang::getNotifText('invoice_balance');
        $textInvoice = str_replace('[[company_name]]', $config['CompanyName'], $textInvoice);
        $textInvoice = str_replace('[[address]]', $config['address'], $textInvoice);
        $textInvoice = str_replace('[[phone]]', $config['phone'], $textInvoice);
        $textInvoice = str_replace('[[invoice]]', $inv, $textInvoice);
        $textInvoice = str_replace('[[date]]', Lang::dateTimeFormat(date("Y-m-d H:i:s")), $textInvoice);
        $textInvoice = str_replace('[[trx_date]]', Lang::dateTimeFormat(date("Y-m-d H:i:s")), $textInvoice);
        $textInvoice = str_replace('[[payment_gateway]]', $gateway, $textInvoice);
        $textInvoice = str_replace('[[payment_channel]]', $channel, $textInvoice);
        $textInvoice = str_replace('[[type]]', 'Balance', $textInvoice);
        $textInvoice = str_replace('[[plan_name]]', $plan['name_plan'], $textInvoice);
        $textInvoice = str_replace('[[plan_price]]', Lang::moneyFormat($plan['price']), $textInvoice);
        $textInvoice = str_replace('[[name]]', $customer['fullname'], $textInvoice);
        $textInvoice = str_replace('[[user_name]]', $customer['username'], $textInvoice);
        $textInvoice = str_replace('[[user_password]]', $customer['password'], $textInvoice);
        $textInvoice = str_replace('[[footer]]', $config['note'], $textInvoice);
        $textInvoice = str_replace('[[balance_before]]', Lang::moneyFormat($balance_before), $textInvoice);
        $textInvoice = str_replace('[[balance]]', Lang::moneyFormat($balance), $textInvoice);

        // PAMNET_HOTSPOT_NO_BALANCE_SMS — respect channel; Hotspot/None never SMS
        $svcType = (string) ($customer['service_type'] ?? 'Hotspot');
        $balVia = Message::resolveNotificationVia($svcType, 'payment');
        if ($balVia === 'sms') {
            Message::sendSMS($customer['phonenumber'], $textInvoice);
        } else if ($balVia === 'wa') {
            Message::sendWhatsapp($customer['phonenumber'], $textInvoice);
        } else if ($balVia === 'email') {
            Message::sendEmail($customer['email'], '[' . $config['CompanyName'] . '] ' . Lang::T("Invoice") . ' ' . $inv, $textInvoice);
        }
        return $t->id();
    }

    public static function rechargeCustomBalance($customer, $plan, $gateway, $channel, $note = '')
    {
        global $admin, $config;
        $plan = ORM::for_table('tbl_payment_gateway')
            ->where('username', $customer['username'])
            ->where('routers', 'Custom Balance')
            ->where('status', '1')
            ->find_one();
        if (!$plan) {
            return false;
        }
        // insert table transactions
        $t = ORM::for_table('tbl_transactions')->create();
        $t->invoice = $inv = "INV-" . Package::_raid();
        $t->username = $customer['username'];
        $t->user_id = $customer['id'];
        $t->plan_name = 'Custom Balance';
        $t->price = $plan['price'];
        $t->recharged_on = date("Y-m-d");
        $t->recharged_time = date("H:i:s");
        $t->expiration = date("Y-m-d");
        $t->time = date("H:i:s");
        $t->method = "$gateway - $channel";
        $t->routers = 'balance';
        $t->type = "Balance";
        $t->note = $note;
        if ($admin) {
            $t->admin_id = ($admin['id']) ? $admin['id'] : '0';
        } else {
            $t->admin_id = '0';
        }
        $t->save();

        $balance_before = $customer['balance'];
        Balance::plus($customer['id'], $plan['price']);
        $balance = $customer['balance'] + $plan['price'];

        $textInvoice = Lang::getNotifText('invoice_balance');
        $textInvoice = str_replace('[[company_name]]', $config['CompanyName'], $textInvoice);
        $textInvoice = str_replace('[[address]]', $config['address'], $textInvoice);
        $textInvoice = str_replace('[[phone]]', $config['phone'], $textInvoice);
        $textInvoice = str_replace('[[invoice]]', $inv, $textInvoice);
        $textInvoice = str_replace('[[date]]', Lang::dateTimeFormat(date("Y-m-d H:i:s")), $textInvoice);
        $textInvoice = str_replace('[[trx_date]]', Lang::dateTimeFormat(date("Y-m-d H:i:s")), $textInvoice);
        $textInvoice = str_replace('[[payment_gateway]]', $gateway, $textInvoice);
        $textInvoice = str_replace('[[payment_channel]]', $channel, $textInvoice);
        $textInvoice = str_replace('[[type]]', 'Balance', $textInvoice);
        $textInvoice = str_replace('[[plan_name]]', $plan['name_plan'], $textInvoice);
        $textInvoice = str_replace('[[plan_price]]', Lang::moneyFormat($plan['price']), $textInvoice);
        $textInvoice = str_replace('[[name]]', $customer['fullname'], $textInvoice);
        $textInvoice = str_replace('[[user_name]]', $customer['username'], $textInvoice);
        $textInvoice = str_replace('[[user_password]]', $customer['password'], $textInvoice);
        $textInvoice = str_replace('[[footer]]', $config['note'], $textInvoice);
        $textInvoice = str_replace('[[balance_before]]', Lang::moneyFormat($balance_before), $textInvoice);
        $textInvoice = str_replace('[[balance]]', Lang::moneyFormat($balance), $textInvoice);

        // PAMNET_HOTSPOT_NO_BALANCE_SMS — respect channel; Hotspot/None never SMS
        $svcType = (string) ($customer['service_type'] ?? 'Hotspot');
        $balVia = Message::resolveNotificationVia($svcType, 'payment');
        if ($balVia === 'sms') {
            Message::sendSMS($customer['phonenumber'], $textInvoice);
        } else if ($balVia === 'wa') {
            Message::sendWhatsapp($customer['phonenumber'], $textInvoice);
        } else if ($balVia === 'email') {
            Message::sendEmail($customer['email'], '[' . $config['CompanyName'] . '] ' . Lang::T("Invoice") . ' ' . $inv, $textInvoice);
        }
        return $t->id();
    }

    public static function _raid()
    {
        return ORM::for_table('tbl_transactions')->max('id') + 1;
    }

    /**
     * @param in   tbl_transactions
     * @param string $router_name router name for this package
     * @param int   $plan_id plan id for this package
     * @param string $gateway payment gateway name
     * @param string $channel channel payment gateway
     * @return boolean
     */
    public static function createInvoice($in)
    {
        global $config, $admin, $ui;
        $date = Lang::dateAndTimeFormat($in['recharged_on'], $in['recharged_time']);
        if ($admin['id'] != $in['admin_id'] && $in['admin_id'] > 0) {
            $_admin = Admin::_info($in['admin_id']);
            // if admin not deleted
            if ($_admin) $admin = $_admin;
        } else {
            $admin['fullname'] = 'Customer';
        }
        $cust = ORM::for_table('tbl_customers')->where('username', $in['username'])->findOne();

        $note = '';
        //print
        $invoice = Lang::pad($config['CompanyName'], ' ', 2) . "\n";
        $invoice .= Lang::pad($config['address'], ' ', 2) . "\n";
        $invoice .= Lang::pad($config['phone'], ' ', 2) . "\n";
        $invoice .= Lang::pad("", '=') . "\n";
        $invoice .= Lang::pads("Invoice", $in['invoice'], ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Date'), $date, ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Sales'), $admin['fullname'], ' ') . "\n";
        $invoice .= Lang::pad("", '=') . "\n";
        $invoice .= Lang::pads(Lang::T('Type'), $in['type'], ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Plan Name'), $in['plan_name'], ' ') . "\n";
        if (!empty($in['note'])) {
            $in['note'] = str_replace("\r", "", $in['note']);
            $tmp = explode("\n", $in['note']);
            foreach ($tmp as $t) {
                if (strpos($t, " : ") === false) {
                    if (!empty($t)) {
                        $note .= "$t\n";
                    }
                } else {
                    $tmp2 = explode(" : ", $t);
                    $invoice .= Lang::pads($tmp2[0], $tmp2[1], ' ') . "\n";
                }
            }
        }
        $invoice .= Lang::pads(Lang::T('Total'), Lang::moneyFormat($in['price']), ' ') . "\n";
        $method = explode("-", $in['method']);
        $invoice .= Lang::pads($method[0], $method[1], ' ') . "\n";
        if (!empty($note)) {
            $invoice .= Lang::pad("", '=') . "\n";
            $invoice .= Lang::pad($note, ' ', 2) . "\n";
        }
        $invoice .= Lang::pad("", '=') . "\n";
        if ($cust) {
            $invoice .= Lang::pads(Lang::T('Full Name'), $cust['fullname'], ' ') . "\n";
        }
        $invoice .= Lang::pads(Lang::T('Username'), $in['username'], ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Password'), '**********', ' ') . "\n";
        if ($in['type'] != 'Balance') {
            $invoice .= Lang::pads(Lang::T('Created On'), Lang::dateAndTimeFormat($in['recharged_on'], $in['recharged_time']), ' ') . "\n";
            $invoice .= Lang::pads(Lang::T('Expires On'), Lang::dateAndTimeFormat($in['expiration'], $in['time']), ' ') . "\n";
        }
        $invoice .= Lang::pad("", '=') . "\n";
        $invoice .= Lang::pad($config['note'], ' ', 2) . "\n";
        $ui->assign('invoice', $invoice);
        $config['printer_cols'] = 30;
        //whatsapp
        $invoice = Lang::pad($config['CompanyName'], ' ', 2) . "\n";
        $invoice .= Lang::pad($config['address'], ' ', 2) . "\n";
        $invoice .= Lang::pad($config['phone'], ' ', 2) . "\n";
        $invoice .= Lang::pad("", '=') . "\n";
        $invoice .= Lang::pads("Invoice", $in['invoice'], ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Date'), $date, ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Sales'), $admin['fullname'], ' ') . "\n";
        $invoice .= Lang::pad("", '=') . "\n";
        $invoice .= Lang::pads(Lang::T('Type'), $in['type'], ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Plan Name'), $in['plan_name'], ' ') . "\n";
        if (!empty($in['note'])) {
            $invoice .= Lang::pad("", '=') . "\n";
            foreach ($tmp as $t) {
                if (strpos($t, " : ") === false) {
                    if (!empty($t)) {
                        $invoice .= Lang::pad($t, ' ', 2) . "\n";
                    }
                } else {
                    $tmp2 = explode(" : ", $t);
                    $invoice .= Lang::pads($tmp2[0], $tmp2[1], ' ') . "\n";
                }
            }
        }
        $invoice .= Lang::pads(Lang::T('Total'), Lang::moneyFormat($in['price']), ' ') . "\n";
        $invoice .= Lang::pads($method[0], $method[1], ' ') . "\n";
        if (!empty($note)) {
            $invoice .= Lang::pad("", '=') . "\n";
            $invoice .= Lang::pad($note, ' ', 2) . "\n";
        }
        $invoice .= Lang::pad("", '=') . "\n";
        if ($cust) {
            $invoice .= Lang::pads(Lang::T('Full Name'), $cust['fullname'], ' ') . "\n";
        }
        $invoice .= Lang::pads(Lang::T('Username'), $in['username'], ' ') . "\n";
        $invoice .= Lang::pads(Lang::T('Password'), '**********', ' ') . "\n";
        if ($in['type'] != 'Balance') {
            $invoice .= Lang::pads(Lang::T('Created On'), Lang::dateAndTimeFormat($in['recharged_on'], $in['recharged_time']), ' ') . "\n";
            $invoice .= Lang::pads(Lang::T('Expires On'), Lang::dateAndTimeFormat($in['expiration'], $in['time']), ' ') . "\n";
        }
        $invoice .= Lang::pad("", '=') . "\n";
        $invoice .= Lang::pad($config['note'], ' ', 2) . "\n";
        $ui->assign('whatsapp', urlencode("```$invoice```"));
        $ui->assign('in', $in);
    }
    public static function tax($price, $tax_rate = 1)
    {
        // Convert tax rate to decimal
        $tax_rate_decimal = $tax_rate / 100;
        $tax = $price * $tax_rate_decimal;
        return $tax;
    }

    public static function getDevice($plan)
    {
        global $DEVICE_PATH;
        if ($plan === false) {
            return "none";
        }
        if (!isset($plan['device'])) {
            return "none";
        }
        if (!empty($plan['device'])) {
            return $DEVICE_PATH . DIRECTORY_SEPARATOR . $plan['device'] . '.php';
        }
        if ($plan['is_radius'] == 1) {
            $plan->device = 'Radius';
            $plan->save();
            return $DEVICE_PATH . DIRECTORY_SEPARATOR . 'Radius' . '.php';
        }
        if ($plan['type'] == 'PPPOE') {
            $plan->device = 'MikrotikPppoe';
            $plan->save();
            return $DEVICE_PATH . DIRECTORY_SEPARATOR . 'MikrotikPppoe' . '.php';
        }
        $plan->device = 'MikrotikHotspot';
        $plan->save();
        return $DEVICE_PATH . DIRECTORY_SEPARATOR . 'MikrotikHotspot' . '.php';
    }

    /**
     * Repair active recharges whose expiry drifted from purchase time
     * (timezone skew, or C2B+STK double-extend race).
     * Safe: only rewrites clearly wrong Hrs/Mins/Days rows; never shortens
     * a legitimate extend (recharge older than 10 minutes with matching TX stack).
     *
     * @return array{fixed:int,checked:int,error?:string}
     */
    public static function repairTimeBasedExpirations()
    {
        global $CACHE_PATH;
        if (class_exists('MikrotikTimeSync')) {
            MikrotikTimeSync::applyPhpTimezone();
            MikrotikTimeSync::applyMysqlTimezone();
        }
        $fixed = 0;
        $checked = 0;
        try {
            $rows = ORM::for_table('tbl_user_recharges')
                ->where('status', 'on')
                ->order_by_desc('id')
                ->limit(800)
                ->find_many();
        } catch (Exception $e) {
            return ['fixed' => 0, 'checked' => 0, 'error' => $e->getMessage()];
        }

        foreach ($rows as $ur) {
            $checked++;
            $plan = ORM::for_table('tbl_plans')->where('id', $ur['plan_id'])->find_one();
            if (!$plan) {
                continue;
            }
            $unit = (string) ($plan['validity_unit'] ?? '');
            if (!in_array($unit, ['Hrs', 'Mins', 'Hours', 'Minutes', 'Days'], true)) {
                continue;
            }
            $startTs = strtotime(trim(($ur['recharged_on'] ?? '') . ' ' . ($ur['recharged_time'] ?? '')));
            if ($startTs === false) {
                continue;
            }
            $validity = (int) ($plan['validity'] ?? 0);
            if ($validity < 1) {
                continue;
            }
            if ($unit === 'Hrs' || $unit === 'Hours') {
                $expectTs = strtotime('+' . $validity . ' hour', $startTs);
                $tol = 120;
            } elseif ($unit === 'Mins' || $unit === 'Minutes') {
                $expectTs = strtotime('+' . $validity . ' minute', $startTs);
                $tol = 120;
            } else {
                $expectTs = strtotime('+' . $validity . ' day', $startTs);
                $tol = 300;
            }
            if ($expectTs === false) {
                continue;
            }
            $haveTs = strtotime(trim(($ur['expiration'] ?? '') . ' ' . ($ur['time'] ?? '')));
            if ($haveTs !== false && abs($haveTs - $expectTs) <= $tol) {
                continue;
            }

            $targetTs = $expectTs;

            // Prefer latest matching transaction expiry when it matches expect
            // (fixes RC double-extended by C2B/STK while TX kept correct value)
            try {
                $tx = ORM::for_table('tbl_transactions')
                    ->where('username', $ur['username'])
                    ->where('plan_name', $ur['namebp'])
                    ->order_by_desc('id')
                    ->find_one();
                if ($tx) {
                    $txTs = strtotime(trim(($tx['expiration'] ?? '') . ' ' . ($tx['time'] ?? '')));
                    if ($txTs !== false && abs($txTs - $expectTs) <= $tol) {
                        $targetTs = $txTs;
                    } elseif ($txTs !== false && $haveTs !== false && $haveTs > $expectTs + $tol && $txTs <= $haveTs && abs($txTs - $expectTs) < abs($haveTs - $expectTs)) {
                        // RC longer than TX+expect → double-extend; use TX/expect
                        $targetTs = (abs($txTs - $expectTs) <= abs($expectTs - $expectTs)) ? $txTs : $expectTs;
                        if (abs($txTs - $expectTs) <= 86400) {
                            $targetTs = $txTs;
                        }
                    }
                }
            } catch (Exception $e) {
            }

            // Do not shorten a legitimate multi-extend: if stacked purchases are
            // confirmed by the latest transaction expiry, leave RC alone.
            if ($haveTs !== false && (time() - $startTs) > 900 && in_array($unit, ['Days'], true)) {
                $span = $haveTs - $startTs;
                $one = max(1, $expectTs - $startTs);
                $n = round($span / $one);
                if ($n >= 2 && abs($span - ($n * $one)) <= $tol) {
                    $txAgree = false;
                    try {
                        $tx2 = ORM::for_table('tbl_transactions')
                            ->where('username', $ur['username'])
                            ->where('plan_name', $ur['namebp'])
                            ->order_by_desc('id')
                            ->find_one();
                        if ($tx2) {
                            $tx2Ts = strtotime(trim(($tx2['expiration'] ?? '') . ' ' . ($tx2['time'] ?? '')));
                            if ($tx2Ts !== false && abs($tx2Ts - $haveTs) <= $tol) {
                                $txAgree = true;
                            }
                        }
                    } catch (Exception $e) {
                    }
                    if ($txAgree) {
                        continue;
                    }
                }
            }

            // Double-extend within recent recharge window: correct to expect/TX
            $recentPurchase = (time() - $startTs) < 86400 * 14;
            if (!$recentPurchase && $haveTs !== false && $haveTs > $expectTs + $tol) {
                continue;
            }

            if ($haveTs !== false && abs($haveTs - $targetTs) <= $tol) {
                continue;
            }

            $ur->expiration = date('Y-m-d', $targetTs);
            $ur->time = date('H:i:s', $targetTs);
            try {
                $ur->save();
                $fixed++;
            } catch (Exception $e) {
            }
        }

        return ['fixed' => $fixed, 'checked' => $checked];
    }

    /**
     * Resolve display/expiry date+time for a recharge row.
     * For Hrs/Mins plans, recompute from recharged_on + plan validity when stored
     * expiry is missing or clearly wrong (timezone / clock skew).
     *
     * @param mixed $recharge ORM row / array from tbl_user_recharges (or billing package)
     * @return array{expiration:string,time:string}
     */
    public static function resolveRechargeExpiration($recharge)
    {
        if (class_exists('MikrotikTimeSync')) {
            MikrotikTimeSync::applyPhpTimezone();
        }

        $row = is_object($recharge) ? (array) $recharge : (array) $recharge;
        $expiration = trim((string) ($row['expiration'] ?? ''));
        $time = trim((string) ($row['time'] ?? ''));
        if ($time === '') {
            $time = '23:59:59';
        }

        $planId = (int) ($row['plan_id'] ?? 0);
        $plan = null;
        if ($planId > 0) {
            try {
                $plan = ORM::for_table('tbl_plans')->where('id', $planId)->find_one();
            } catch (Exception $e) {
                $plan = null;
            }
        }

        $unit = $plan ? (string) ($plan['validity_unit'] ?? '') : '';
        if (in_array($unit, ['Hrs', 'Mins', 'Hours', 'Minutes'], true)) {
            $startTs = strtotime(trim(($row['recharged_on'] ?? '') . ' ' . ($row['recharged_time'] ?? '')));
            $validity = (int) ($plan['validity'] ?? 0);
            if ($startTs !== false && $validity > 0) {
                if ($unit === 'Hrs' || $unit === 'Hours') {
                    $expectTs = strtotime('+' . $validity . ' hour', $startTs);
                } else {
                    $expectTs = strtotime('+' . $validity . ' minute', $startTs);
                }
                if ($expectTs !== false) {
                    $haveTs = strtotime(trim($expiration . ' ' . $time));
                    // Prefer recomputed expiry when stored value is missing or >2 minutes off
                    if ($expiration === '' || $haveTs === false || abs($haveTs - $expectTs) > 120) {
                        $expiration = date('Y-m-d', $expectTs);
                        $time = date('H:i:s', $expectTs);
                    }
                }
            }
        }

        if ($expiration === '') {
            $expiration = date('Y-m-d');
        }

        return [
            'expiration' => $expiration,
            'time' => $time,
        ];
    }

    /**
     * Format remaining package time for UI (Customer Usage, etc.).
     *
     * Accepts seconds left, an expiry unix timestamp, "Y-m-d" + optional "H:i:s",
     * or a recharge row with expiration/time keys.
     *
     * @param mixed       $expirationOrSeconds
     * @param string|null $time
     * @param int|null    $nowOverride optional unix timestamp (customers/view passes this)
     * @return string e.g. "2d 3h 15m", "Expired", "< 1m"
     */
    public static function formatTimeRemaining($expirationOrSeconds, $time = null, $nowOverride = null)
    {
        if (class_exists('MikrotikTimeSync')) {
            MikrotikTimeSync::applyPhpTimezone();
        }

        $now = ($nowOverride !== null && is_numeric($nowOverride)) ? (int) $nowOverride : time();
        $seconds_left = null;

        if (is_array($expirationOrSeconds) || (is_object($expirationOrSeconds) && !($expirationOrSeconds instanceof \DateTimeInterface))) {
            $row = (array) $expirationOrSeconds;
            $date = isset($row['expiration']) ? $row['expiration'] : (isset($row['expiry']) ? $row['expiry'] : '');
            $t = isset($row['time']) ? $row['time'] : (isset($row['expiration_time']) ? $row['expiration_time'] : '23:59:59');
            if ($date === '' || $date === null) {
                return 'Unknown';
            }
            $expiry_ts = strtotime(trim($date . ' ' . $t));
            if ($expiry_ts === false) {
                return 'Unknown';
            }
            $seconds_left = $expiry_ts - $now;
        } elseif ($time !== null && $time !== '') {
            $expiry_ts = strtotime(trim((string) $expirationOrSeconds . ' ' . (string) $time));
            if ($expiry_ts === false) {
                return 'Unknown';
            }
            $seconds_left = $expiry_ts - $now;
        } elseif ($expirationOrSeconds instanceof \DateTimeInterface) {
            $seconds_left = $expirationOrSeconds->getTimestamp() - $now;
        } elseif (is_numeric($expirationOrSeconds)) {
            $n = (int) $expirationOrSeconds;
            // Absolute unix timestamp vs remaining seconds
            if ($n > 1000000000) {
                $seconds_left = $n - $now;
            } else {
                $seconds_left = $n;
            }
        } elseif (is_string($expirationOrSeconds) && trim($expirationOrSeconds) !== '') {
            $expiry_ts = strtotime(trim($expirationOrSeconds));
            if ($expiry_ts === false) {
                return 'Unknown';
            }
            $seconds_left = $expiry_ts - $now;
        } else {
            return 'Unknown';
        }

        if ($seconds_left <= 0) {
            return 'Expired';
        }

        $days = (int) floor($seconds_left / 86400);
        $hours = (int) floor(($seconds_left % 86400) / 3600);
        $minutes = (int) floor(($seconds_left % 3600) / 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }

        return empty($parts) ? '< 1m' : implode(' ', $parts);
    }
}

