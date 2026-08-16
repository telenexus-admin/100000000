<?php

// Register the Cash Progress menu
register_menu(" Revenue", true, "cash_progress_menu", 'AFTER_SERVICES', 'ion ion-stats-bars', 'Stats', 'green');

function cash_progress_menu()
{
    global $ui;
    _admin();
    $ui->assign('_title', 'Revenue');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);

    // Prepare date ranges
    $today          = date('Y-m-d');
    $yesterday      = date('Y-m-d', strtotime('-1 day'));
    $thisWeekStart  = date('Y-m-d', strtotime('monday this week'));
    $lastWeekStart  = date('Y-m-d', strtotime('monday last week'));
    $lastWeekEnd    = date('Y-m-d', strtotime('sunday last week'));
    $thisMonthStart = date('Y-m-01');
    $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
    $lastMonthEnd   = date('Y-m-t', strtotime('last month'));

    // Earnings summary (Ksh totals from tbl_transactions.price)
    $earnings = [
        'today'      => (float) ORM::for_table('tbl_transactions')->where('recharged_on', $today)->sum('price'),
        'yesterday'  => (float) ORM::for_table('tbl_transactions')->where('recharged_on', $yesterday)->sum('price'),
        'this_week'  => (float) ORM::for_table('tbl_transactions')->where_gte('recharged_on', $thisWeekStart)->sum('price'),
        'last_week'  => (float) ORM::for_table('tbl_transactions')->where_gte('recharged_on', $lastWeekStart)->where_lte('recharged_on', $lastWeekEnd)->sum('price'),
        'this_month' => (float) ORM::for_table('tbl_transactions')->where_gte('recharged_on', $thisMonthStart)->sum('price'),
        'last_month' => (float) ORM::for_table('tbl_transactions')->where_gte('recharged_on', $lastMonthStart)->where_lte('recharged_on', $lastMonthEnd)->sum('price'),
    ];

    // ✅ Daily transactions count
    $transactions = [
        'today'     => ORM::for_table('tbl_transactions')->where('recharged_on', $today)->count(),
        'yesterday' => ORM::for_table('tbl_transactions')->where('recharged_on', $yesterday)->count(),
    ];

    // ✅ Weekly transactions count
    $transactions['this_week'] = ORM::for_table('tbl_transactions')->where_gte('recharged_on', $thisWeekStart)->count();
    $transactions['last_week'] = ORM::for_table('tbl_transactions')->where_gte('recharged_on', $lastWeekStart)->where_lte('recharged_on', $lastWeekEnd)->count();

    // ✅ Monthly transactions count
    $transactions['this_month'] = ORM::for_table('tbl_transactions')->where_gte('recharged_on', $thisMonthStart)->count();
    $transactions['last_month'] = ORM::for_table('tbl_transactions')->where_gte('recharged_on', $lastMonthStart)->where_lte('recharged_on', $lastMonthEnd)->count();

    // ✅ Transactions by Router (Current Month)
    $routerData = ORM::for_table('tbl_transactions')
        ->select('routers')
        ->select_expr('COUNT(*)', 'total')
        ->where_gte('recharged_on', $thisMonthStart)
        ->group_by('routers')
        ->find_array();

    // ✅ Revenue by Service Plan (Current Month)
    $planRevenue = ORM::for_table('tbl_transactions')
        ->select('plan_name')
        ->select_expr('SUM(price)', 'total')
        ->where_gte('recharged_on', $thisMonthStart)
        ->group_by('plan_name')
        ->find_array();

    // ✅ Transactions by Payment Method (Current Month)
    $paymentMethods = ORM::for_table('tbl_transactions')
        ->select('method')
        ->select_expr('COUNT(*)', 'total')
        ->where_gte('recharged_on', $thisMonthStart)
        ->group_by('method')
        ->find_array();

    // ✅ Top 10 Customers by Transactions
    $topCustomers = ORM::for_table('tbl_transactions')
        ->select('username')
        ->select_expr('COUNT(*)', 'total')
        ->group_by('username')
        ->order_by_desc('total')
        ->limit(10)
        ->find_array();

    // ✅ Average Transaction Value (Daily, current month)
    $avgDaily = ORM::for_table('tbl_transactions')
        ->select('recharged_on')
        ->select_expr('AVG(price)', 'avg_value')
        ->where_gte('recharged_on', $thisMonthStart)
        ->group_by('recharged_on')
        ->find_array();

    // ✅ Peak Transaction Hours (Today)
    $peakHours = ORM::for_table('tbl_transactions')
        ->select_expr("HOUR(recharged_time)", "hour")
        ->select_expr("SUM(price)", "total")
        ->where('recharged_on', $today)
        ->group_by_expr("HOUR(recharged_time)")
        ->order_by_asc("hour")
        ->find_array();

    // Assign data to template
    $ui->assign('earnings', $earnings);
    $ui->assign('transactions', $transactions);
    $ui->assign('routerData', $routerData);
    $ui->assign('planRevenue', $planRevenue);
    $ui->assign('paymentMethods', $paymentMethods);
    $ui->assign('topCustomers', $topCustomers);
    $ui->assign('avgDaily', $avgDaily);
    $ui->assign('peakHours', $peakHours);

    // Render template
    $ui->display('cash_progress.tpl');
}
