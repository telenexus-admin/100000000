<?php

class info_payment_gateway
{

    public function getWidget($data = null)
    {
        global $ui;
        // Use sargable time ranges so the status/date indexes remain usable.
        $todayStart = date('Y-m-d 00:00:00');
        $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
        try {
            $successful = ORM::for_table('tbl_payment_gateway')
                ->where('status', 2)
                ->where_gte('paid_date', $todayStart)
                ->where_lt('paid_date', $tomorrowStart)
                ->count();

            $failed = ORM::for_table('tbl_payment_gateway')
                ->where('status', 3)
                ->where_gte('paid_date', $todayStart)
                ->where_lt('paid_date', $tomorrowStart)
                ->count();

            $pending = ORM::for_table('tbl_payment_gateway')
                ->where('status', 1)
                ->where_gte('created_date', $todayStart)
                ->where_lt('created_date', $tomorrowStart)
                ->count();

            $payments_today = array(
                'successful' => (int)$successful,
                'failed' => (int)$failed,
                'pending' => (int)$pending,
            );
        } catch (Exception $e) {
            $payments_today = array('successful' => 0, 'failed' => 0, 'pending' => 0);
            error_log('info_payment_gateway: failed to fetch today\'s payment counts: ' . $e->getMessage());
        }

        $ui->assign('payments_today', $payments_today);

        return $ui->fetch('widget/info_payment_gateway.tpl');
    }
}
