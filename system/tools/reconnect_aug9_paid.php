<?php
/**
 * One-shot: activate Aug 9 paid Hotspot customers stuck after STK callback outage.
 * Usage: php system/tools/reconnect_aug9_paid.php [--dry-run]
 */
$logFile = '/tmp/reconnect_aug9.log';
@file_put_contents($logFile, date('c') . " start\n");
$root = dirname(dirname(__DIR__));
chdir($root);
require $root . '/init.php';
@file_put_contents($logFile, date('c') . " init_ok\n", FILE_APPEND);

$dry = in_array('--dry-run', $argv ?? [], true);
@file_put_contents($logFile, date('c') . " dry=" . ($dry ? '1' : '0') . "\n", FILE_APPEND);

function rlog($msg)
{
    global $logFile;
    echo $msg . "\n";
    @file_put_contents($logFile, $msg . "\n", FILE_APPEND);
}

$transIds = [
    'UH9B520GPJ', 'UH9692AZ1L', 'UH9CQ2KF3H', 'UH9F12INV8', 'UH9CQ2KGCU',
    'UH9L7250F3', 'UH9BW28MU3', 'UH9AD2GWRS', 'UH93O351LD', 'UH9BM2HRSH',
    'UH9122CDVX', 'UH9BM2HNG5', 'UH9AY23NMQ', 'UH93G2JQD5', 'UH93G2JY7H',
    'UH98Z28MDR', 'UH9AD2GQVH', 'UH9122CHW1', 'UH91G2HSVF', 'UH9L7251NR',
    'UH9CZ21HCX', 'UH91E20Y9N', 'UH98O2MKS3', 'UH9582PT4B', 'UH98O2MJ9M',
    'UH9AH2912P', 'UH9EL2FVB7', 'UH9EL2FWVU', 'UH9KA2GW44', 'UH9MI1W1RT',
    'UH9J61ZP0T', 'UH9FR1Z615', 'UH9EL2FWTK', 'UH9MA2773R', 'UH9MA2772W',
    'UH9MI1W33Q', 'UH9FR1Z3DE', 'UH9GT30A1X', 'UH9ID2F1K8', 'UH99V1ST6O',
    'UH9I429I36', 'UH9GT309WX', 'UH97I257FS', 'UH9KN2N3WY', 'UH93K30VK5',
    'UH99V1SU9G', 'UH94K2TUTB', 'UH93K30XK5', 'UH9OZ2KBAY', 'UH90W1X0G2',
    'UH9L12N8VJ', 'UH9692AQ4R', 'UH99V1SPVX', 'UH9BH2CMNU', 'UH9B520BLA',
    'UH9692AR3W', 'UH9L12N5VK',
];

$priceToPlan = [];
foreach (ORM::for_table('tbl_plans')->where('type', 'Hotspot')->find_many() as $plan) {
    $price = (string) (int) $plan['price'];
    if (!isset($priceToPlan[$price])) {
        $priceToPlan[$price] = (int) $plan['id'];
    }
}

function pamnet_parse_hotspot_user($billRef)
{
    $billRef = trim((string) $billRef);
    if (stripos($billRef, 'Hotspot-') === 0) {
        return substr($billRef, strlen('Hotspot-'));
    }
    return $billRef;
}

function pamnet_ensure_hotspot($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }
    $plugin = dirname(__DIR__) . '/plugin/CreateHotspotUser.php';
    if (is_file($plugin)) {
        require_once $plugin;
        if (function_exists('PamnetEnsureHotspotOnRouter')) {
            return PamnetEnsureHotspotOnRouter($username);
        }
    }
    return false;
}

$ok = 0;
$skip = 0;
$fail = 0;

foreach ($transIds as $transId) {
    $mpesa = ORM::for_table('tbl_mpesa_transactions')->where('TransID', $transId)->find_one();
    if (!$mpesa) {
        rlog("FAIL $transId MISSING_MPESA");
        $fail++;
        continue;
    }

    $alreadyPg = ORM::for_table('tbl_payment_gateway')->where('gateway_trx_id', $transId)->find_one();
    $alreadyTx = ORM::for_table('tbl_transactions')->where('invoice', $transId)->find_one();
    if ($alreadyPg || $alreadyTx) {
        $user = pamnet_parse_hotspot_user($mpesa['BillRefNumber']);
        if (!$dry) {
            pamnet_ensure_hotspot($user);
        }
        rlog("SKIP $transId ALREADY user=$user");
        $skip++;
        continue;
    }

    $username = pamnet_parse_hotspot_user($mpesa['BillRefNumber']);
    $amount = (float) $mpesa['TransAmount'];
    $amountInt = (string) (int) $amount;

    $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
    if (!$customer) {
        rlog("FAIL $transId NO_CUSTOMER user=$username amount=$amount");
        $fail++;
        continue;
    }

    $pg = ORM::for_table('tbl_payment_gateway')
        ->where('username', $username)
        ->where('status', 1)
        ->where('price', $amount)
        ->order_by_desc('id')
        ->find_one();
    if (!$pg) {
        $pg = ORM::for_table('tbl_payment_gateway')
            ->where('username', $username)
            ->where('status', 1)
            ->order_by_desc('id')
            ->find_one();
    }

    $planId = $pg ? (int) $pg['plan_id'] : ($priceToPlan[$amountInt] ?? 0);
    $routers = $pg ? (string) $pg['routers'] : 'PMNINTERNET';
    $gateway = $pg ? (string) $pg['gateway'] : 'mpesa';

    if ($planId <= 0) {
        rlog("FAIL $transId NO_PLAN user=$username amount=$amount");
        $fail++;
        continue;
    }

    rlog(($dry ? 'DRY ' : '') . "ACT $transId user=$username amount=$amount plan=$planId routers=$routers pg=" . ($pg ? $pg['id'] : 'none'));

    if ($dry) {
        $ok++;
        continue;
    }

    try {
        $recharged = Package::rechargeUser(
            $customer['id'],
            $routers,
            $planId,
            $gateway,
            'Reconnect-Aug9',
            'Paid offline reconnect ' . $transId,
            $transId
        );

        $now = date('Y-m-d H:i:s');
        if ($pg) {
            $pg->status = 2;
            $pg->paid_date = $now;
            $pg->gateway_trx_id = $transId;
            $pg->pg_paid_response = 'manual reconnect after outage';
            $pg->save();
        } else {
            $pgNew = ORM::for_table('tbl_payment_gateway')->create();
            $pgNew->username = $username;
            $pgNew->gateway = $gateway;
            $pgNew->gateway_trx_id = $transId;
            $pgNew->plan_id = $planId;
            $pgNew->price = $amount;
            $pgNew->routers = $routers;
            $pgNew->payment_method = 'Paybill';
            $pgNew->payment_channel = 'Reconnect-Aug9';
            $pgNew->status = 2;
            $pgNew->created_date = $now;
            $pgNew->paid_date = $now;
            $pgNew->pg_paid_response = 'manual reconnect after outage (no pending STK)';
            $plan = ORM::for_table('tbl_plans')->where('id', $planId)->find_one();
            if ($plan) {
                $pgNew->plan_name = $plan['name_plan'];
            }
            $pgNew->save();
        }

        $synced = pamnet_ensure_hotspot($username);
        $rc = ORM::for_table('tbl_user_recharges')->where('username', $username)->order_by_desc('id')->find_one();
        $exp = $rc ? ($rc['expiration'] . ' ' . $rc['time']) : 'n/a';
        $st = $rc ? $rc['status'] : 'n/a';
        rlog("OK $transId recharge=" . ($recharged ? 'yes' : 'no') . " sync=" . ($synced ? 'yes' : 'no') . " status=$st exp=$exp");
        $ok++;
    } catch (Throwable $e) {
        rlog("FAIL $transId EX " . $e->getMessage());
        $fail++;
    }
}

rlog("DONE ok=$ok skip=$skip fail=$fail dry=" . ($dry ? '1' : '0'));
