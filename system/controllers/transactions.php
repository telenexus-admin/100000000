<?php

/**
 * Universal Payment Gateway Transactions Controller
 * Consolidates all payment gateway transactions in one view
 */

_admin();
$ui->assign('_system_menu', 'transactions');

$ui->assign('_admin', $admin);

// Get reset day configuration (same logic as dashboard)
$reset_day = $config['reset_day'];
if (empty($reset_day)) {
    $reset_day = 1;
}

// Get period parameter (current or previous)
$period = _req('period') ?: 'current';

// Calculate date ranges based on reset_day (following dashboard logic exactly)
if ($period === 'previous') {
    // Previous billing cycle
    if (date("d") >= $reset_day) {
        // We're past reset day in current month, so previous cycle is last month
        $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT), strtotime("-1 MONTH"));
        $period_end = date('Y-m-' . str_pad($reset_day - 1, 2, '0', STR_PAD_LEFT));
        if ($reset_day == 1) {
            $period_end = date('Y-m-t'); // Last day of current month
        }
    } else {
        // We're before reset day in current month, so previous cycle is 2 months ago
        $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT), strtotime("-2 MONTH"));
        $period_end = date('Y-m-' . str_pad($reset_day - 1, 2, '0', STR_PAD_LEFT), strtotime("-1 MONTH"));
        if ($reset_day == 1) {
            $period_end = date('Y-m-t', strtotime("-1 MONTH")); // Last day of previous month
        }
    }
} else {
    // Current billing cycle (same logic as dashboard controller)
    if (date("d") >= $reset_day) {
        $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT));
        $period_end = date('Y-m-d'); // Current date
    } else {
        $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT), strtotime("-1 MONTH"));
        $period_end = date('Y-m-d'); // Current date
    }
}

// Simple test endpoint
if (_req('test') == '1') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Test endpoint working']);
    exit;
}

// Handle AJAX requests
if (_req('ajax') == '1') {
    header('Content-Type: application/json');
    
    try {
    
    $q = alphanumeric(_req('q'), '-._ ');
    $gateway = alphanumeric(_req('gateway'));
    $status = _req('status');
    $date_from = _req('date_from');
    $date_to = _req('date_to');
    $period = _req('period') ?: 'current';
    
    // Calculate period dates for AJAX (reuse logic from above)
    $reset_day = $config['reset_day'];
    if (empty($reset_day)) {
        $reset_day = 1;
    }
    
    if ($period === 'previous') {
        // Previous billing cycle
        if (date("d") >= $reset_day) {
            // We're past reset day in current month, so previous cycle is last month
            $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT), strtotime("-1 MONTH"));
            $period_end = date('Y-m-' . str_pad($reset_day - 1, 2, '0', STR_PAD_LEFT));
            if ($reset_day == 1) {
                $period_end = date('Y-m-t'); // Last day of current month
            }
        } else {
            // We're before reset day in current month, so previous cycle is 2 months ago
            $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT), strtotime("-2 MONTH"));
            $period_end = date('Y-m-' . str_pad($reset_day - 1, 2, '0', STR_PAD_LEFT), strtotime("-1 MONTH"));
            if ($reset_day == 1) {
                $period_end = date('Y-m-t', strtotime("-1 MONTH")); // Last day of previous month
            }
        }
    } else {
        // Current billing cycle (same logic as dashboard controller)
        if (date("d") >= $reset_day) {
            $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT));
            $period_end = date('Y-m-d'); // Current date
        } else {
            $period_start = date('Y-m-' . str_pad($reset_day, 2, '0', STR_PAD_LEFT), strtotime("-1 MONTH"));
            $period_end = date('Y-m-d'); // Current date
        }
    }

    $query = ORM::for_table('tbl_payment_gateway')->order_by_desc("id");
    $query->select_many('id', 'username', 'gateway', 'gateway_trx_id', 'plan_id', 'plan_name', 'routers_id', 'routers', 'price', 'pg_url_payment', 'payment_method', 'payment_channel', 'expired_date', 'created_date', 'paid_date', 'trx_invoice', 'status');
    
    // Apply filters
    if (!empty($q)) {
        $query->whereRaw("(gateway_trx_id LIKE '%$q%' OR username LIKE '%$q%' OR routers LIKE '%$q%' OR plan_name LIKE '%$q%' OR payment_method LIKE '%$q%')");
    }
    
    if (!empty($gateway)) {
        $query->where('gateway', $gateway);
    }
    
    if ($status !== '' && is_numeric($status)) {
        $query->where('status', (int)$status);
    }
    
    if (!empty($date_from)) {
        $query->where_gte('created_date', $date_from . ' 00:00:00');
    }
    if (!empty($date_to)) {
        $query->where_lte('created_date', $date_to . ' 23:59:59');
    }
    
    // Apply period filtering if no custom dates are set
    if (empty($date_from) && empty($date_to)) {
        $query->where_gte('created_date', $period_start . ' 00:00:00');
        $query->where_lte('created_date', $period_end . ' 23:59:59');
    }

    // Limit to 100 results for performance
    $transactions = $query->limit(100)->find_many();
    
    $result = [];
    foreach ($transactions as $tx) {
        $result[] = [
            'id' => $tx->id,
            'username' => $tx->username,
            'gateway' => $tx->gateway,
            'gateway_trx_id' => $tx->gateway_trx_id,
            'plan_name' => $tx->plan_name,
            'routers' => $tx->routers,
            'price' => $tx->price,
            'payment_method' => $tx->payment_method,
            'payment_channel' => $tx->payment_channel,
            'created_date' => $tx->created_date,
            'paid_date' => $tx->paid_date,
            'status' => $tx->status,
            'trx_invoice' => $tx->trx_invoice
        ];
    }
    
    echo json_encode($result);
    exit;
    
    } catch (Exception $e) {
        error_log("AJAX Error: " . $e->getMessage());
        echo json_encode(['error' => 'Server error occurred']);
        exit;
    }
}

// Regular page load (non-AJAX)
$q = alphanumeric(_req('q'), '-._ ');
$gateway = alphanumeric(_req('gateway'));
$status = _req('status');
$date_from = _req('date_from');
$date_to = _req('date_to');

        $query = ORM::for_table('tbl_payment_gateway')->order_by_desc("id");
        $query->select_many('id', 'username', 'gateway', 'gateway_trx_id', 'plan_id', 'plan_name', 'routers_id', 'routers', 'price', 'pg_url_payment', 'payment_method', 'payment_channel', 'expired_date', 'created_date', 'paid_date', 'trx_invoice', 'status');
        
        $append_url = '&status=2';
        
        // Search filter
        if (!empty($q)) {
            $query->whereRaw("(gateway_trx_id LIKE '%$q%' OR username LIKE '%$q%' OR routers LIKE '%$q%' OR plan_name LIKE '%$q%' OR payment_method LIKE '%$q%')");
            $append_url .= '&q=' . urlencode($q);
        }
        
        // Gateway filter
        if (!empty($gateway)) {
            $query->where('gateway', $gateway);
            $append_url .= '&gateway=' . urlencode($gateway);
        }
        
        // Status filter - NOW ONLY SHOW PAID (status=2)
        $query->where('status', 2);
        
        // Date range filter
        if (!empty($date_from)) {
            $query->where_gte('created_date', $date_from . ' 00:00:00');
            $append_url .= '&date_from=' . urlencode($date_from);
        }
        if (!empty($date_to)) {
            $query->where_lte('created_date', $date_to . ' 23:59:59');
            $append_url .= '&date_to=' . urlencode($date_to);
        }
        
        // Apply period filtering if no custom dates are set
        if (empty($date_from) && empty($date_to)) {
            $query->where_gte('created_date', $period_start . ' 00:00:00');
            $query->where_lte('created_date', $period_end . ' 23:59:59');
        }
        
        // Add period to URL
        if ($period !== 'current') {
            $append_url .= '&period=' . urlencode($period);
        }

        $pgs = Paginator::findMany($query, [], 50, $append_url);

        // Get available gateways for filter dropdown
        $gateways = ORM::for_table('tbl_payment_gateway')
            ->select('gateway')
            ->group_by('gateway')
            ->find_many();

        // Get transaction statistics for current period - ONLY PAID (status=2)
        $stats = [];
        $stats_query_base = ORM::for_table('tbl_payment_gateway')
            ->where('status', 2)
            ->where_gte('created_date', $period_start . ' 00:00:00')
            ->where_lte('created_date', $period_end . ' 23:59:59');
            
        $stats['total'] = clone($stats_query_base);
        $stats['total'] = $stats['total']->count();
        
        // Paid transactions count (all are paid anyway, but keeping structure)
        $stats['paid'] = clone($stats_query_base);
        $stats['paid'] = $stats['paid']->count();
        
        // Zero out other statuses since we're only showing paid
        $stats['pending'] = 0;
        $stats['failed'] = 0;
        $stats['canceled'] = 0;

        // Calculate total amounts for payment gateway in current period - ONLY PAID
        $total_paid_amount = ORM::for_table('tbl_payment_gateway')
            ->where('status', 2)
            ->where_gte('created_date', $period_start . ' 00:00:00')
            ->where_lte('created_date', $period_end . ' 23:59:59')
            ->sum('price');
        $stats['total_amount'] = $total_paid_amount ?: 0;
        
        // Get manual cash recharge statistics from tbl_transactions
        // Exclude payment gateway transactions dynamically to avoid duplicates
        $cash_stats = [];
        
        // Get all invoice numbers from payment gateway transactions for current period (dynamic approach)
        $payment_gateway_invoices = ORM::for_table('tbl_payment_gateway')
            ->select('trx_invoice')
            ->where_not_equal('trx_invoice', '')
            ->where('status', 2)  // Only count paid invoices
            ->where_gte('created_date', $period_start . ' 00:00:00')
            ->where_lte('created_date', $period_end . ' 23:59:59')
            ->find_array();
        
        $pg_invoice_list = array_column($payment_gateway_invoices, 'trx_invoice');
        
        // Manual cash recharges only (exclude payment gateway invoices dynamically) for current period
        $manual_cash_query = ORM::for_table('tbl_transactions')
            ->whereRaw("method LIKE '%Cash%' OR method LIKE '%Administrator%'")
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        
        // Dynamically exclude payment gateway invoices if any exist
        if (!empty($pg_invoice_list)) {
            $manual_cash_query->where_not_in('invoice', $pg_invoice_list);
        }
        
        $cash_stats['total'] = $manual_cash_query->count();
        
        // Manual cash amount
        $cash_amount_query = ORM::for_table('tbl_transactions')
            ->whereRaw("method LIKE '%Cash%' OR method LIKE '%Administrator%'")
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        
        if (!empty($pg_invoice_list)) {
            $cash_amount_query->where_not_in('invoice', $pg_invoice_list);
        }
        
        $cash_amount = $cash_amount_query->sum('price');
        $cash_stats['total_amount'] = $cash_amount ?: 0;
        
        // Cash recharges by type (manual only)
        $hotspot_query = ORM::for_table('tbl_transactions')
            ->whereRaw("method LIKE '%Cash%' OR method LIKE '%Administrator%'")
            ->where('type', 'Hotspot')
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        if (!empty($pg_invoice_list)) {
            $hotspot_query->where_not_in('invoice', $pg_invoice_list);
        }
        $cash_stats['hotspot'] = $hotspot_query->count();
        
        $pppoe_query = ORM::for_table('tbl_transactions')
            ->whereRaw("method LIKE '%Cash%' OR method LIKE '%Administrator%'")
            ->where('type', 'PPPOE')
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        if (!empty($pg_invoice_list)) {
            $pppoe_query->where_not_in('invoice', $pg_invoice_list);
        }
        $cash_stats['pppoe'] = $pppoe_query->count();
        
        $balance_query = ORM::for_table('tbl_transactions')
            ->whereRaw("method LIKE '%Cash%' OR method LIKE '%Administrator%'")
            ->where('type', 'Balance')
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        if (!empty($pg_invoice_list)) {
            $balance_query->where_not_in('invoice', $pg_invoice_list);
        }
        $cash_stats['balance'] = $balance_query->count();
        
        // Manual recharge methods breakdown (excluding payment gateways dynamically)
        $cash_methods_query = ORM::for_table('tbl_transactions')
            ->select('method')
            ->selectExpr('COUNT(*) as count')
            ->selectExpr('SUM(price) as amount')
            ->group_by('method')
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        
        // Exclude payment gateway invoices and non-cash methods
        if (!empty($pg_invoice_list)) {
            $cash_methods_query->where_not_in('invoice', $pg_invoice_list);
        }
        
        // Only include manual cash/admin methods and vouchers
        $cash_methods_query->whereRaw("(method LIKE '%Cash%' OR method LIKE '%Administrator%' OR method LIKE '%Voucher%')");
        
        $cash_methods = $cash_methods_query->find_array();
        $cash_stats['methods'] = $cash_methods;
        
        // Voucher recharges (separate from cash, also exclude payment gateway duplicates)
        $voucher_stats = [];
        $voucher_query = ORM::for_table('tbl_transactions')
            ->whereRaw("method LIKE '%Voucher%'")
            ->select('method')
            ->selectExpr('COUNT(*) as count')
            ->selectExpr('SUM(price) as amount')
            ->group_by('method')
            ->where_gte('recharged_on', $period_start . ' 00:00:00')
            ->where_lte('recharged_on', $period_end . ' 23:59:59');
        
        if (!empty($pg_invoice_list)) {
            $voucher_query->where_not_in('invoice', $pg_invoice_list);
        }
        
        $voucher_transactions = $voucher_query->find_array();
        
        $voucher_stats['transactions'] = $voucher_transactions;
        $voucher_stats['total_count'] = array_sum(array_column($voucher_transactions, 'count'));
        $voucher_stats['total_amount'] = array_sum(array_column($voucher_transactions, 'amount'));
        
        // Cash (Administrator) recharges - now properly filtered
        $cash_stats['admin_cash_count'] = $cash_stats['total'];
        $cash_stats['admin_cash_amount'] = $cash_stats['total_amount'];

        $ui->assign('_title', 'Paid Payment Transactions');
        $ui->assign('pgs', $pgs);
        $ui->assign('gateways', $gateways);
        $ui->assign('stats', $stats);
        $ui->assign('cash_stats', $cash_stats);
        $ui->assign('voucher_stats', $voucher_stats);
        $ui->assign('q', $q);
        $ui->assign('gateway', $gateway);
        $ui->assign('status', 2); // Force status to 2 (paid)
        $ui->assign('date_from', $date_from);
        $ui->assign('date_to', $date_to);
        $ui->assign('period', $period);
        $ui->assign('period_start', $period_start);
        $ui->assign('period_end', $period_end);
        $ui->assign('reset_day', $reset_day);
        
        // Debug: Log the calculated dates for troubleshooting
        error_log("Transactions Controller Debug - Reset Day: $reset_day, Period: $period, Start: $period_start, End: $period_end, Current Date: " . date('Y-m-d'));
        
        $ui->display('admin/transactions/list.tpl');

