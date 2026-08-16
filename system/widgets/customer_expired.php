<?php


class customer_expired
{


    public function getWidget()
    {
        global $ui, $current_date, $config;
        // Show recent recharges (default last 7 days) along with their latest transaction invoice
        $days = 7;
        $fromDate = date('Y-m-d', strtotime("-{$days} days"));

        $query = ORM::for_table('tbl_user_recharges')
            ->table_alias('tur')
            ->select('tur.id', 'recharge_id')
            ->selects([
                'c.id',
                'tur.username',
                'c.fullname',
                'c.phonenumber',
                'c.email',
                'tur.expiration',
                'tur.time',
                'tur.recharged_on',
                'tur.recharged_time',
                'tur.namebp',
                'tur.routers'
            ])
            ->innerJoin('tbl_customers', ['tur.customer_id', '=', 'c.id'], 'c')
            ->where('tur.status', 'off')
            ->where_lte('tur.expiration', $current_date)
            ->where_gte('tur.recharged_on', $fromDate)
            ->order_by_desc('tur.recharged_on')
            ->order_by_desc('tur.recharged_time');

        $expire = Paginator::findMany($query);

        // total count for paginator
        $totalCount = ORM::for_table('tbl_user_recharges')
            ->where_gte('recharged_on', $fromDate)
            ->count();

        $paginator['total_count'] = $totalCount;

        // Enrich each recharge row with latest transaction invoice (if available)
        $rows = [];
        foreach ($expire as $r) {
            $row = $r;
            $trxInvoice = '';
            try {
                // Prefer gateway transaction id from tbl_payment_gateway
                $pg = ORM::for_table('tbl_payment_gateway')
                    ->where('username', $r['username'])
                    ->where_raw('DATE(paid_date) = ?', [$r['recharged_on']])
                    ->where_not_equal('gateway_trx_id', '')
                    ->order_by_desc('id')
                    ->find_one();
                if ($pg && !empty($pg->gateway_trx_id)) {
                    $trxInvoice = $pg->gateway_trx_id;
                } else {
                    // fallback: try created_date
                    $pg2 = ORM::for_table('tbl_payment_gateway')
                        ->where('username', $r['username'])
                        ->where_raw('DATE(created_date) = ?', [$r['recharged_on']])
                        ->where_not_equal('gateway_trx_id', '')
                        ->order_by_desc('id')
                        ->find_one();
                    if ($pg2 && !empty($pg2->gateway_trx_id)) {
                        $trxInvoice = $pg2->gateway_trx_id;
                    } else {
                        // final fallback: invoice from tbl_transactions (if present)
                        $t = ORM::for_table('tbl_transactions')
                            ->where('username', $r['username'])
                            ->where_raw('DATE(recharged_on) = ?', [$r['recharged_on']])
                            ->order_by_desc('id')
                            ->find_one();
                        if ($t) {
                            $trxInvoice = $t->invoice ?: '';
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore and leave trxInvoice empty
            }
            $row['trx_invoice'] = $trxInvoice;
            $rows[] = $row;
        }

        $expire = $rows;

        if(!empty($_COOKIE['expdef']) && $_COOKIE['expdef'] != $config['customer_expired_expdef']) {
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'customer_expired_expdef')->find_one();
            if ($d) {
                $d->value = $_COOKIE['expdef'];
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'customer_expired_expdef';
                $d->value = $_COOKIE['expdef'];
                $d->save();
            }
        }
        if(!empty($config['customer_expired_expdef']) && empty($_COOKIE['expdef'])){
            $_COOKIE['expdef'] = $config['customer_expired_expdef'];
            setcookie('expdef', $config['customer_expired_expdef'], time() + (86400 * 30), "/");
        }

        // Assign the pagination HTML to the template variable
        $ui->assign('expire', $expire);
        $ui->assign('cookie', $_COOKIE);
        return $ui->fetch('widget/customer_expired.tpl');
    }
}
