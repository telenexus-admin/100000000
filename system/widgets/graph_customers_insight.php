<?php

class graph_customers_insight
{
    public function getWidget()
    {
        global $CACHE_PATH,$ui;
        $u_act = ORM::for_table('tbl_user_recharges')->where('status', 'on')->count();
        if (empty($u_act)) {
            $u_act = '0';
        }
        $ui->assign('u_act', $u_act);

        $u_all = ORM::for_table('tbl_user_recharges')->count();
        if (empty($u_all)) {
            $u_all = '0';
        }
        $ui->assign('u_all', $u_all);

        $c_all = ORM::for_table('tbl_customers')->count();
        if (empty($c_all)) {
            $c_all = '0';
        }
        $ui->assign('c_all', $c_all);

        // One grouped query replaces one count query per plan.
        try {
            $plans = ORM::for_table('tbl_plans')->raw_query(
                'SELECT p.id, p.name_plan, p.price, COUNT(r.id) AS recharge_count
                 FROM tbl_plans p
                 LEFT JOIN tbl_user_recharges r ON r.plan_id = p.id
                 GROUP BY p.id, p.name_plan, p.price
                 ORDER BY recharge_count DESC
                 LIMIT 10'
            )->find_array();

            $perf = array();
            foreach ($plans as $plan) {
                $count = (int) $plan['recharge_count'];
                $perf[] = array(
                    'label' => $plan['name_plan'] ?: ('Plan ' . $plan['id']),
                    'count' => $count,
                    'revenue' => (is_numeric($plan['price']) ? (float) $plan['price'] : 0.0) * $count
                );
            }

            $ui->assign('plan_perf_labels', json_encode(array_column($perf, 'label')));
            $ui->assign('plan_perf_counts', json_encode(array_column($perf, 'count')));
            $ui->assign('plan_perf_revenues', json_encode(array_column($perf, 'revenue')));
        } catch (Exception $e) {
            $ui->assign('plan_perf_labels', json_encode(array()));
            $ui->assign('plan_perf_counts', json_encode(array()));
            $ui->assign('plan_perf_revenues', json_encode(array()));
            error_log('graph_customers_insight: failed to generate plan performance: ' . $e->getMessage());
        }
        return $ui->fetch('widget/graph_customers_insight.tpl');
    }
}
