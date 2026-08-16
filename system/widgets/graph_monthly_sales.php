<?php

class graph_monthly_sales
{
    public function getWidget()
    {
        global $CACHE_PATH, $ui;

        $cacheMSfile = $CACHE_PATH . File::pathFixer('/monthlySales.temp');

        // Cache for 1 hour
        if (file_exists($cacheMSfile) && time() - filemtime($cacheMSfile) < 3600) {
            $salesData = json_decode(file_get_contents($cacheMSfile), true);
            // validate structure
            if (!is_array($salesData) || !isset($salesData['monthly'])) {
                $salesData = $this->generateSalesData();
                file_put_contents($cacheMSfile, json_encode($salesData));
            }
        } else {
            $salesData = $this->generateSalesData();
            // ensure cache directory exists
            $cacheDir = dirname($cacheMSfile);
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            file_put_contents($cacheMSfile, json_encode($salesData));
        }

        // Ensure keys exist to avoid template errors
        if (!isset($salesData['monthly']) || !is_array($salesData['monthly'])) {
            $salesData['monthly'] = array();
        }
        if (!isset($salesData['summary']) || !is_array($salesData['summary'])) {
            $salesData['summary'] = [
                'today' => ['total' => 0, 'transactions' => 0],
                'thisWeek' => ['total' => 0, 'transactions' => 0],
                'thisMonth' => ['total' => 0, 'transactions' => 0],
                'currency' => $_SESSION['currency_code'] ?? 'KES'
            ];
        }

        // Provide backward-compatible variable `monthlySales` expected by older tpl
        $monthlySales = array();
        if (!empty($salesData['monthly']) && is_array($salesData['monthly'])) {
            foreach ($salesData['monthly'] as $m) {
                $monthlySales[] = [
                    'month' => isset($m['month']) ? (int)$m['month'] : 0,
                    'totalSales' => isset($m['total']) ? (float)$m['total'] : 0
                ];
            }
        }

        $ui->assign('salesData', $salesData);
        $ui->assign('monthlySales', $monthlySales);
        return $ui->fetch('widget/graph_monthly_sales.tpl');
    }

    private function generateSalesData()
    {
        // Monthly totals for current year
        $currentYear = date('Y');
        
        $results = ORM::for_table('tbl_transactions')
            ->select_expr('MONTH(recharged_on)', 'month')
            ->select_expr('SUM(price)', 'total')
            ->select_expr('COUNT(*)', 'transactions')
            ->where_raw("YEAR(recharged_on) = ?", [$currentYear])
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->group_by_expr('MONTH(recharged_on)')
            ->find_many();

        $monthly = array();
        // Initialize all months with zero values
        for ($m = 1; $m <= 12; $m++) {
            $monthly[$m] = [
                'month' => $m, 
                'total' => 0.0, 
                'transactions' => 0
            ];
        }

        // Fill in actual data
        foreach ($results as $row) {
            if (isset($row->month)) {
                $m = (int)$row->month;
                $monthly[$m] = [
                    'month' => $m,
                    'total' => (float)$row->total,
                    'transactions' => (int)$row->transactions
                ];
            }
        }

        // Reindex as zero-based array for JSON usage
        $monthlyArr = array_values($monthly);

        return [
            'monthly' => $monthlyArr,
            'summary' => [
                'today' => $this->getTodaySales(),
                'thisWeek' => $this->getThisWeekSales(),
                'thisMonth' => $this->getThisMonthSales(),
                'currency' => $_SESSION['currency_code'] ?? 'KES'
            ]
        ];
    }

    private function getTodaySales()
    {
        $today = date('Y-m-d');
        $result = ORM::for_table('tbl_transactions')
            ->select_expr('SUM(price)', 'total')
            ->select_expr('COUNT(*)', 'transactions')
            ->where_raw('DATE(recharged_on) = ?', [$today])
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->find_one();

        return [
            'total' => $result ? (float)$result->total : 0,
            'transactions' => $result ? (int)$result->transactions : 0
        ];
    }

    private function getThisWeekSales()
    {
        $start = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
        $end = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';

        $result = ORM::for_table('tbl_transactions')
            ->select_expr('SUM(price)', 'total')
            ->select_expr('COUNT(*)', 'transactions')
            ->where_gte('recharged_on', $start)
            ->where_lte('recharged_on', $end)
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->find_one();

        return [
            'total' => $result ? (float)$result->total : 0,
            'transactions' => $result ? (int)$result->transactions : 0
        ];
    }

    private function getThisMonthSales()
    {
        $month = date('n');
        $year = date('Y');
        $result = ORM::for_table('tbl_transactions')
            ->select_expr('SUM(price)', 'total')
            ->select_expr('COUNT(*)', 'transactions')
            ->where_raw('MONTH(recharged_on) = ? AND YEAR(recharged_on) = ?', [$month, $year])
            ->where_not_equal('method', 'Customer - Balance')
            ->where_not_equal('method', 'Recharge Balance - Administrator')
            ->find_one();

        return [
            'total' => $result ? (float)$result->total : 0,
            'transactions' => $result ? (int)$result->transactions : 0
        ];
    }
}