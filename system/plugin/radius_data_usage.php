<?php

// Check if tables exist, create if not
$db = ORM::get_db();

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('radius_daily_history', $tables)) {
    $db->exec("
        CREATE TABLE `radius_daily_history` (
            `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(64) NOT NULL,
            `date` DATE NOT NULL,
            `download_bytes` BIGINT(20) NOT NULL DEFAULT 0,
            `upload_bytes` BIGINT(20) NOT NULL DEFAULT 0,
            `total_bytes` BIGINT(20) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_date` (`username`, `date`),
            INDEX `idx_username` (`username`),
            INDEX `idx_date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
}

if (!in_array('radius_monthly_history', $tables)) {
    $db->exec("
        CREATE TABLE `radius_monthly_history` (
            `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(64) NOT NULL,
            `year` INT(4) NOT NULL,
            `month` INT(2) NOT NULL,
            `download_bytes` BIGINT(20) NOT NULL DEFAULT 0,
            `upload_bytes` BIGINT(20) NOT NULL DEFAULT 0,
            `total_bytes` BIGINT(20) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_month` (`username`, `year`, `month`),
            INDEX `idx_username` (`username`),
            INDEX `idx_year_month` (`year`, `month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
}

// Register menu items
register_menu("Data Usages Statistics", true, "radius_data_usage", 'RADIUS', '');
register_menu("Data Usage", false, "radius_data_usage_clients", 'AFTER_HISTORY', 'fa fa-pie-chart');

// CLI handler for archiving
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    switch ($argv[1]) {
        case 'archive_daily':
            radius_archive_daily_data();
            break;
        case 'archive_monthly':
            radius_archive_monthly_data();
            break;
    }
    exit;
}

function radius_data_usage() {
    global $ui, $routes;
    _admin();
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $username = $routes['2'];

    // Check user type for access
    if ($admin['user_type'] != 'SuperAdmin' && $admin['user_type'] != 'Admin' && $admin['user_type'] != 'Sales') {
        r2(U . "dashboard", 'e', Lang::T("You Do Not Have Access"));
    }

    if (!empty($username)) {
        // Check if a specific date range is provided for filtering
        if (isset($_POST['start_date']) && isset($_POST['end_date'])) {
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $use_history = false; // Use live data for custom date ranges
        } else {
            // If no date range is provided, use historical data if available
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            $use_history = true;
        }

        $historical_data = [];
        $chartData = ['labels' => [], 'datasets' => []];
        // Try to get data from history first if appropriate
        if ($use_history) {
            $historical_data = radius_get_historical_data($username, 'daily', 31);
            
            if (!empty($historical_data)) {
                $totalDownload = 0;
                $totalUpload = 0;
                $days = [];
                $downloads = [];
                $uploads = [];
                
                foreach ($historical_data as $row) {
                    $day = date('d', strtotime($row['date']));
                    $days[] = $day;
                    $downloads[] = (int)($row['download_bytes'] / 1024 / 1024);
                    $uploads[] = (int)($row['upload_bytes'] / 1024 / 1024);
                    $totalDownload += $row['download_bytes'];
                    $totalUpload += $row['upload_bytes'];
                }
                
                $chartData = [
                    'labels' => $days,
                    'datasets' => [
                        [
                            'label' => 'Download (MB)',
                            'data' => $downloads,
                            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                            'borderColor' => 'rgba(54, 162, 235, 1)',
                            'borderWidth' => 1,
                            'tension' => 0.1
                        ],
                        [
                            'label' => 'Upload (MB)',
                            'data' => $uploads,
                            'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                            'borderColor' => 'rgba(255, 99, 132, 1)',
                            'borderWidth' => 1,
                            'tension' => 0.1
                        ]
                    ]
                ];
            }
        }

        // If no historical data available or custom date range, use live data
        if (empty($historical_data) || !$use_history) {
            $orm_data = ORM::for_table('radacct', 'radius')
                ->select_expr('DATE(acctstarttime)', 'usage_date')
                ->select_expr('SUM(acctoutputoctets)', 'Download')
                ->select_expr('SUM(acctinputoctets)', 'Upload')
                ->where_raw('DATE(acctstarttime) BETWEEN ? AND ?', [$start_date, $end_date])
                ->where('username', $username)
                ->group_by_expr('DATE(acctstarttime)')
                ->order_by_expr('DATE(acctstarttime) ASC')
                ->find_many();

            $byDate = [];
            foreach ($orm_data as $row) {
                $key = $row['usage_date'];
                $byDate[$key] = [
                    'dl' => (int) $row['Download'],
                    'ul' => (int) $row['Upload'],
                ];
            }

            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $endExclusive = clone $end;
            $endExclusive->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $endExclusive);
            $days = [];
            $downloads = [];
            $uploads = [];
            $totalDownload = 0;
            $totalUpload = 0;

            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $days[] = $date->format('d');
                if (isset($byDate[$key])) {
                    $totalDownload += $byDate[$key]['dl'];
                    $totalUpload += $byDate[$key]['ul'];
                    $downloads[] = (int) ($byDate[$key]['dl'] / 1024 / 1024);
                    $uploads[] = (int) ($byDate[$key]['ul'] / 1024 / 1024);
                } else {
                    $downloads[] = 0;
                    $uploads[] = 0;
                }
            }

            $chartData = [
                'labels' => $days,
                'datasets' => [
                    [
                        'label' => 'Download (MB)',
                        'data' => $downloads,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1,
                        'tension' => 0.1
                    ],
                    [
                        'label' => 'Upload (MB)',
                        'data' => $uploads,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'borderWidth' => 1,
                        'tension' => 0.1
                    ]
                ]
            ];
        }

        $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();

        // Assign data to Smarty variable
        $ui->assign('packages', User::_billing($customer['id']));
        $ui->assign('chart_data', json_encode($chartData));
        $ui->assign('d', $customer);
        $ui->assign('start_date', $start_date);
        $ui->assign('end_date', $end_date);
        $ui->assign('totalDownload', $totalDownload);
        $ui->assign('totalUpload', $totalUpload);
        $ui->assign('username', $username);
        $ui->assign('_title', 'Internet Data Usages: [' . $username . ']');
        $ui->assign('_system_menu', 'radius');
        $ui->assign('xheader', '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">');
        $ui->display('radius_data_usage_view.tpl');
    } else {
        // Admin overview page
        // Handle date range filter
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

        // Count online users (active sessions)
        $totalCount = ORM::for_table('radacct', 'radius')
            ->where_raw("acctstoptime IS NULL")
            ->count();

        // Count total RADIUS accounts from tbl_user_recharges where routers = 'radius'
        $radiusAcct = ORM::for_table('tbl_user_recharges')
            ->where('routers', 'radius')
            ->count();

        // Check RADIUS service status
        exec('systemctl is-active freeradius', $output, $returnCode);
        $ui->assign('radiusStatus', ($returnCode === 0 && trim($output[0]) === 'active') ? 'online' : 'offline');

        // Get data with date range
        $dailyData = radius_get_daily_consumption($start_date, $end_date);
        $weeklyData = radius_get_weekly_consumption($start_date, $end_date);
        $monthlyData = radius_get_monthly_consumption($start_date, $end_date);

        // Prepare chart data
        $dailyChartData = [
            'labels' => array_column($dailyData, 'Date'),
            'datasets' => [
                [
                    'label' => 'Download (MB)',
                    'data' => array_map(function($d) { return $d['TotalDownloadBytes']/1024/1024; }, $dailyData),
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                    'tension' => 0.1,
                    'fill' => true
                ],
                [
                    'label' => 'Upload (MB)',
                    'data' => array_map(function($d) { return $d['TotalUploadBytes']/1024/1024; }, $dailyData),
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                    'tension' => 0.1,
                    'fill' => true
                ]
            ]
        ];
        $ui->assign('daily_chart_data', json_encode($dailyChartData));

        $radiusUsers = ORM::for_table('radacct', 'radius')
            ->order_by_asc('acctsessiontime')
            ->where_raw("acctstoptime IS NULL")
            ->find_many();

        $ui->assign('xheader', '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">');
        $ui->assign('totalCount', $totalCount);
        $ui->assign('radiusAcct', $radiusAcct);
        $ui->assign('radiusUsers', $radiusUsers);
        $ui->assign('dailyData', $dailyData);
        $ui->assign('weeklyData', $weeklyData);
        $ui->assign('monthlyData', $monthlyData);
        $ui->assign('start_date', $start_date);
        $ui->assign('end_date', $end_date);
        $ui->assign('_title', 'Radius Data Usages Statistics');
        $ui->assign('_system_menu', 'radius');
        $ui->display('radius_data_usage.tpl');
    }
}

function radius_data_usage_clients() {
    global $ui;
    _auth();
    $ui->assign('_title', 'Data Usage Statistics');
    $ui->assign('_system_menu', '');
    $user = User::_info();
    $ui->assign('_user', $user);
    $username = $user['username'];

    // Check if a specific date range is provided for filtering
    if (isset($_POST['start_date']) && isset($_POST['end_date'])) {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $use_history = !isset($_POST['force_live']) || $_POST['force_live'] != '1';
    } else {
        $start_date = date('Y-m-d', strtotime('-30 days')); // Default to last 30 days
        $end_date = date('Y-m-d');
        $use_history = true;
    }

    $historical_data = [];
    $chartData = ['labels' => [], 'datasets' => []];
    // Try to get historical data first
    if ($use_history) {
        $historical_data = radius_get_historical_data($username, 'daily', 31);
        
        if (!empty($historical_data)) {
            $totalDownload = 0;
            $totalUpload = 0;
            $days = [];
            $downloads = [];
            $uploads = [];
            
            foreach ($historical_data as $row) {
                $day = date('d', strtotime($row['date']));
                $days[] = $day;
                $downloads[] = (int)($row['download_bytes'] / 1024 / 1024);
                $uploads[] = (int)($row['upload_bytes'] / 1024 / 1024);
                $totalDownload += $row['download_bytes'];
                $totalUpload += $row['upload_bytes'];
            }
            
            $chartData = [
                'labels' => $days,
                'datasets' => [
                    [
                        'label' => 'Download (MB)',
                        'data' => $downloads,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1,
                        'tension' => 0.1
                    ],
                    [
                        'label' => 'Upload (MB)',
                        'data' => $uploads,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'borderWidth' => 1,
                        'tension' => 0.1
                    ]
                ]
            ];
        }
    }

    // Fall back to live data if needed
    if (empty($historical_data) || !$use_history) {
        $orm_data = ORM::for_table('radacct', 'radius')
            ->select_expr('DATE(acctstarttime)', 'usage_date')
            ->select_expr('SUM(acctoutputoctets)', 'Download')
            ->select_expr('SUM(acctinputoctets)', 'Upload')
            ->where_raw('DATE(acctstarttime) BETWEEN ? AND ?', [$start_date, $end_date])
            ->where('username', $username)
            ->group_by_expr('DATE(acctstarttime)')
            ->order_by_expr('DATE(acctstarttime) ASC')
            ->find_many();

        $byDate = [];
        foreach ($orm_data as $row) {
            $key = $row['usage_date'];
            $byDate[$key] = [
                'dl' => (int) $row['Download'],
                'ul' => (int) $row['Upload'],
            ];
        }

        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $endExclusive = clone $end;
        $endExclusive->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $endExclusive);
        $days = [];
        $downloads = [];
        $uploads = [];
        $totalDownload = 0;
        $totalUpload = 0;

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $days[] = $date->format('d');
            if (isset($byDate[$key])) {
                $totalDownload += $byDate[$key]['dl'];
                $totalUpload += $byDate[$key]['ul'];
                $downloads[] = (int) ($byDate[$key]['dl'] / 1024 / 1024);
                $uploads[] = (int) ($byDate[$key]['ul'] / 1024 / 1024);
            } else {
                $downloads[] = 0;
                $uploads[] = 0;
            }
        }

        $chartData = [
            'labels' => $days,
            'datasets' => [
                [
                    'label' => 'Download (MB)',
                    'data' => $downloads,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'borderWidth' => 1,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Upload (MB)',
                    'data' => $uploads,
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 1,
                    'tension' => 0.1
                ]
            ]
        ];
    }

    // Compute weekly data
    $weekly_data = [];
    $weekly_total_download = 0;
    $weekly_total_upload = 0;

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include end date
    $total_days = $start->diff($end)->days;
    $total_weeks = ceil($total_days / 7);
    $weekly_data_temp = [];

    // Initialize all weeks with zero usage
    for ($i = 1; $i <= $total_weeks; $i++) {
        $week_start_date = (clone $start)->modify('+' . (($i - 1) * 7) . ' days');
        $week_end_date = (clone $week_start_date)->modify('+6 days');
        $week_label = 'Week ' . $i . ' (' . $week_start_date->format('M j') . ' - ' . $week_end_date->format('M j') . ')';
        $weekly_data_temp[$i] = [
            'WeekNumber' => $i,
            'WeekLabel' => $week_label,
            'TotalDownloadBytes' => 0,
            'TotalUploadBytes' => 0,
        ];
    }

    if ($use_history) {
        $weekly_history = ORM::for_table('radius_daily_history')
            ->select('date')
            ->select_expr('SUM(download_bytes)', 'TotalDownloadBytes')
            ->select_expr('SUM(upload_bytes)', 'TotalUploadBytes')
            ->where('username', $username)
            ->where_raw('date >= ? AND date <= ?', [$start_date, $end_date])
            ->group_by('date')
            ->order_by_asc('date')
            ->find_array();

        foreach ($weekly_history as $row) {
            $current_date = new DateTime($row['date']);
            $days_diff = $start->diff($current_date)->days;
            $week_index = (int) (floor($days_diff / 7) + 1);

            if (isset($weekly_data_temp[$week_index])) {
                $weekly_data_temp[$week_index]['TotalDownloadBytes'] += (int) round((float) $row['TotalDownloadBytes']);
                $weekly_data_temp[$week_index]['TotalUploadBytes'] += (int) round((float) $row['TotalUploadBytes']);
            }
        }
    } else {
        $weekly_live = ORM::for_table('radacct', 'radius')
            ->select_expr('DATE(acctstarttime)', 'date')
            ->select_expr('SUM(acctoutputoctets)', 'TotalDownloadBytes')
            ->select_expr('SUM(acctinputoctets)', 'TotalUploadBytes')
            ->where('username', $username)
            ->where_raw('DATE(acctstarttime) BETWEEN ? AND ?', [$start_date, $end_date])
            ->group_by_expr('DATE(acctstarttime)')
            ->order_by_asc('date')
            ->find_array();

        foreach ($weekly_live as $row) {
            $current_date = new DateTime($row['date']);
            $days_diff = $start->diff($current_date)->days;
            $week_index = (int) (floor($days_diff / 7) + 1);

            if (isset($weekly_data_temp[$week_index])) {
                $weekly_data_temp[$week_index]['TotalDownloadBytes'] += (int) round((float) $row['TotalDownloadBytes']);
                $weekly_data_temp[$week_index]['TotalUploadBytes'] += (int) round((float) $row['TotalUploadBytes']);
            }
        }
    }

    foreach ($weekly_data_temp as $week) {
        $weekly_data[] = [
            'WeekNumber' => $week['WeekNumber'],
            'WeekLabel' => $week['WeekLabel'],
            'downloads' => (int)($week['TotalDownloadBytes'] / 1024 / 1024),
            'uploads' => (int)($week['TotalUploadBytes'] / 1024 / 1024),
        ];
        $weekly_total_download += $week['TotalDownloadBytes'];
        $weekly_total_upload += $week['TotalUploadBytes'];
    }

    usort($weekly_data, function ($a, $b) {
        return $a['WeekNumber'] - $b['WeekNumber'];
    });

    // Prepare weekly chart data
    $weeklyChartData = [
        'labels' => array_column($weekly_data, 'WeekLabel'),
        'datasets' => [
            [
                'label' => 'Download (MB)',
                'data' => array_column($weekly_data, 'downloads'),
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderColor' => 'rgba(54, 162, 235, 1)',
                'borderWidth' => 1
            ],
            [
                'label' => 'Upload (MB)',
                'data' => array_column($weekly_data, 'uploads'),
                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                'borderColor' => 'rgba(255, 99, 132, 1)',
                'borderWidth' => 1
            ]
        ]
    ];

    // Fetch user session data for connection table
    $userTable = ORM::for_table('radacct', 'radius')
        ->select('username')
        ->select('framedipaddress', 'address')
        ->select('acctsessiontime', 'uptime')
        ->select('calledstationid', 'service')
        ->select('callingstationid', 'caller_id')
        ->select_expr('acctoutputoctets', 'tx')
        ->select_expr('acctinputoctets', 'rx')
        ->select_expr('(acctoutputoctets + acctinputoctets)', 'total')
        ->where('username', $username)
        ->where_raw("acctstoptime IS NULL")
        ->find_array();

    // Format userTable data
    foreach ($userTable as &$user) {
        $user['uptime'] = radius_data_usage_secondsToTime($user['uptime']);
        $user['tx'] = radius_data_usage_formatBytes($user['tx']);
        $user['rx'] = radius_data_usage_formatBytes($user['rx']);
        $user['total'] = radius_data_usage_formatBytes($user['total']);
    }

    $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();

    // Assign data to UI
    $ui->assign('packages', User::_billing($customer['id']));
    $ui->assign('d', $customer);
    $ui->assign('chart_data', json_encode($chartData));
    $ui->assign('weekly_chart_data', json_encode($weeklyChartData));
    $ui->assign('start_date', $start_date);
    $ui->assign('end_date', $end_date);
    $ui->assign('totalDownload', $totalDownload);
    $ui->assign('totalUpload', $totalUpload);
    $ui->assign('weekly_total_download', $weekly_total_download);
    $ui->assign('weekly_total_upload', $weekly_total_upload);
    $ui->assign('total_download_formatted', radius_data_usage_formatBytes($totalDownload));
    $ui->assign('total_upload_formatted', radius_data_usage_formatBytes($totalUpload));
    $ui->assign('total_usage_formatted', radius_data_usage_formatBytes($totalDownload + $totalUpload));
    $ui->assign('userTable', $userTable);
    $ui->assign('username', $username);
    $ui->assign('use_history', $use_history);
    $ui->assign('_title', 'Internet Data Usages: [' . $username . ']');
    $ui->assign('_system_menu', 'radius');
    $ui->assign('xheader', '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">');
    $ui->display('radius_data_usage_clients.tpl');
}

// Historical Data Functions
function radius_archive_daily_data() {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $users = ORM::for_table('radacct', 'radius')
        ->select('username')
        ->where_raw('DATE(acctstarttime) = ?', [$yesterday])
        ->group_by('username')
        ->find_many();
    
    foreach ($users as $user) {
        $usage = ORM::for_table('radacct', 'radius')
            ->select_expr('SUM(acctoutputoctets)', 'download')
            ->select_expr('SUM(acctinputoctets)', 'upload')
            ->where_raw('DATE(acctstarttime) = ?', [$yesterday])
            ->where('username', $user->username)
            ->find_one();
        
        if ($usage && ($usage->download > 0 || $usage->upload > 0)) {
            $dl = (int) $usage->download;
            $ul = (int) $usage->upload;
            $tot = $dl + $ul;
            ORM::raw_execute(
                'INSERT INTO `radius_daily_history` (`username`, `date`, `download_bytes`, `upload_bytes`, `total_bytes`) VALUES (?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE `download_bytes` = VALUES(`download_bytes`), `upload_bytes` = VALUES(`upload_bytes`), `total_bytes` = VALUES(`total_bytes`)',
                [$user->username, $yesterday, $dl, $ul, $tot]
            );
        }
    }
}

function radius_archive_monthly_data() {
    $last_month = date('Y-m', strtotime('first day of last month'));
    list($year, $month) = explode('-', $last_month);
    
    $users = ORM::for_table('radacct', 'radius')
        ->select('username')
        ->where_raw('YEAR(acctstarttime) = ? AND MONTH(acctstarttime) = ?', [$year, $month])
        ->group_by('username')
        ->find_many();
    
    foreach ($users as $user) {
        $usage = ORM::for_table('radacct', 'radius')
            ->select_expr('SUM(acctoutputoctets)', 'download')
            ->select_expr('SUM(acctinputoctets)', 'upload')
            ->where_raw('YEAR(acctstarttime) = ? AND MONTH(acctstarttime) = ?', [$year, $month])
            ->where('username', $user->username)
            ->find_one();
        
        if ($usage && ($usage->download > 0 || $usage->upload > 0)) {
            $dl = (int) $usage->download;
            $ul = (int) $usage->upload;
            $tot = $dl + $ul;
            $yr = (int) $year;
            $mo = (int) $month;
            ORM::raw_execute(
                'INSERT INTO `radius_monthly_history` (`username`, `year`, `month`, `download_bytes`, `upload_bytes`, `total_bytes`) VALUES (?, ?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE `download_bytes` = VALUES(`download_bytes`), `upload_bytes` = VALUES(`upload_bytes`), `total_bytes` = VALUES(`total_bytes`)',
                [$user->username, $yr, $mo, $dl, $ul, $tot]
            );
        }
    }
}

function radius_get_historical_data($username, $period = 'monthly', $limit = 12) {
    if ($period === 'daily') {
        $days = max(1, (int) $limit);
        return ORM::for_table('radius_daily_history')
            ->where('username', $username)
            ->where_raw('date >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)')
            ->order_by_asc('date')
            ->find_array();
    } else {
        return ORM::for_table('radius_monthly_history')
            ->where('username', $username)
            ->order_by_desc('year')
            ->order_by_desc('month')
            ->limit($limit)
            ->find_array();
    }
}

function radius_get_daily_consumption($start_date = null, $end_date = null) {
    if (!$start_date) $start_date = date('Y-m-d', strtotime('-30 days'));
    if (!$end_date) $end_date = date('Y-m-d');

    // Try historical data first
    $history = ORM::for_table('radius_daily_history')
        ->select_expr('date', 'Date')
        ->select_expr('SUM(download_bytes)', 'TotalDownloadBytes')
        ->select_expr('SUM(upload_bytes)', 'TotalUploadBytes')
        ->where_raw('date >= ? AND date <= ?', [$start_date, $end_date])
        ->group_by('date')
        ->order_by_asc('date')
        ->find_array();
    
    if (!empty($history)) {
        return $history;
    }
    
    // Fall back to live data
    return ORM::for_table('radacct', 'radius')
        ->select_expr('DATE(acctstarttime)', 'Date')
        ->select_expr('SUM(acctinputoctets)', 'TotalUploadBytes')
        ->select_expr('SUM(acctoutputoctets)', 'TotalDownloadBytes')
        ->where_raw('DATE(acctstarttime) >= ? AND DATE(acctstarttime) <= ?', [$start_date, $end_date])
        ->group_by_expr('DATE(acctstarttime)')
        ->order_by_asc('Date')
        ->find_array();
}

function radius_get_weekly_consumption($start_date = null, $end_date = null) {
    if (!$start_date) $start_date = date('Y-m-d', strtotime('-30 days'));
    if (!$end_date) $end_date = date('Y-m-d');
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include end date

    // Initialize weekly data structure
    $weekly_data = [];
    $weekly_data_temp = [];
    $total_days = $start->diff($end)->days;
    $total_weeks = ceil($total_days / 7);

    // Initialize all weeks with zero usage
    for ($i = 1; $i <= $total_weeks; $i++) {
        $week_start_date = (clone $start)->modify('+' . (($i - 1) * 7) . ' days');
        $week_end_date = (clone $week_start_date)->modify('+6 days');
        $week_label = 'Week ' . $i . ' (' . $week_start_date->format('M j') . ' - ' . $week_end_date->format('M j') . ')';
        $weekly_data_temp[$i] = [
            'WeekNumber' => $i,
            'WeekLabel' => $week_label,
            'TotalDownloadBytes' => 0,
            'TotalUploadBytes' => 0,
        ];
    }

    // Try historical data first
    $history = ORM::for_table('radius_daily_history')
        ->select('date')
        ->select_expr('SUM(download_bytes)', 'TotalDownloadBytes')
        ->select_expr('SUM(upload_bytes)', 'TotalUploadBytes')
        ->where_raw('date >= ? AND date <= ?', [$start_date, $end_date])
        ->group_by('date')
        ->order_by_asc('date')
        ->find_array();

    if (!empty($history)) {
        // Aggregate data by week
        foreach ($history as $row) {
            $current_date = new DateTime($row['date']);
            $days_diff = $start->diff($current_date)->days;
            $week_index = (int) (floor($days_diff / 7) + 1);

            if (isset($weekly_data_temp[$week_index])) {
                $weekly_data_temp[$week_index]['TotalDownloadBytes'] += (int) round((float) $row['TotalDownloadBytes']);
                $weekly_data_temp[$week_index]['TotalUploadBytes'] += (int) round((float) $row['TotalUploadBytes']);
            }
        }
    } else {
        // Fall back to live data
        $results = ORM::for_table('radacct', 'radius')
            ->select_expr('DATE(acctstarttime)', 'date')
            ->select_expr('SUM(acctinputoctets)', 'TotalUploadBytes')
            ->select_expr('SUM(acctoutputoctets)', 'TotalDownloadBytes')
            ->where_raw('DATE(acctstarttime) >= ? AND DATE(acctstarttime) <= ?', [$start_date, $end_date])
            ->group_by_expr('DATE(acctstarttime)')
            ->order_by_asc('date')
            ->find_array();

        // Aggregate data by week
        foreach ($results as $row) {
            $current_date = new DateTime($row['date']);
            $days_diff = $start->diff($current_date)->days;
            $week_index = (int) (floor($days_diff / 7) + 1);

            if (isset($weekly_data_temp[$week_index])) {
                $weekly_data_temp[$week_index]['TotalDownloadBytes'] += (int) round((float) $row['TotalDownloadBytes']);
                $weekly_data_temp[$week_index]['TotalUploadBytes'] += (int) round((float) $row['TotalUploadBytes']);
            }
        }
    }

    // Convert temporary array to final weekly data
    foreach ($weekly_data_temp as $week) {
        $weekly_data[] = [
            'WeekNumber' => $week['WeekNumber'],
            'WeekLabel' => $week['WeekLabel'],
            'TotalDownloadBytes' => $week['TotalDownloadBytes'],
            'TotalUploadBytes' => $week['TotalUploadBytes'],
        ];
    }

    // Sort by WeekNumber
    usort($weekly_data, function ($a, $b) {
        return $a['WeekNumber'] - $b['WeekNumber'];
    });

    return $weekly_data;
}

function radius_get_monthly_consumption($start_date = null, $end_date = null) {
    if (!$start_date) $start_date = date('Y-m-d', strtotime('-12 months'));
    if (!$end_date) $end_date = date('Y-m-d');

    // Try historical data first
    $history = ORM::for_table('radius_monthly_history')
        ->select('year', 'Year')
        ->select('month', 'Month')
        ->select_expr('SUM(download_bytes)', 'TotalDownloadBytes')
        ->select_expr('SUM(upload_bytes)', 'TotalUploadBytes')
        ->where_raw('CONCAT(year, "-", LPAD(month, 2, "0"), "-01") >= ? AND CONCAT(year, "-", LPAD(month, 2, "0"), "-01") <= ?', [$start_date, $end_date])
        ->group_by('year')
        ->group_by('month')
        ->order_by_asc('year')
        ->order_by_asc('month')
        ->find_array();
    
    if (!empty($history)) {
        $monthlyData = [];
        foreach ($history as $result) {
            $monthlyData[] = [
                'Year' => $result['Year'],
                'Month' => date('F', mktime(0, 0, 0, $result['Month'], 1)),
                'MonthNumber' => $result['Month'],
                'TotalDownloadBytes' => $result['TotalDownloadBytes'],
                'TotalUploadBytes' => $result['TotalUploadBytes'],
            ];
        }
        return $monthlyData;
    }
    
    // Fall back to live data
    $results = ORM::for_table('radacct', 'radius')
        ->select_expr('YEAR(acctstarttime)', 'Year')
        ->select_expr('MONTH(acctstarttime)', 'Month')
        ->select_expr('SUM(acctinputoctets)', 'TotalUploadBytes')
        ->select_expr('SUM(acctoutputoctets)', 'TotalDownloadBytes')
        ->where_raw('DATE(acctstarttime) >= ? AND DATE(acctstarttime) <= ?', [$start_date, $end_date])
        ->group_by('Year')
        ->group_by('Month')
        ->order_by_asc('Year')
        ->order_by_asc('Month')
        ->find_many();

    $monthlyData = [];
    foreach ($results as $result) {
        $monthlyData[] = [
            'Year' => $result->Year,
            'Month' => date('F', mktime(0, 0, 0, $result->Month, 1)),
            'MonthNumber' => $result->Month,
            'TotalDownloadBytes' => $result->TotalDownloadBytes,
            'TotalUploadBytes' => $result->TotalUploadBytes,
        ];
    }

    return $monthlyData;
}

function radius_data_usage_formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function radius_data_usage_secondsToTime($inputSeconds) {
    $secondsInAMinute = 60;
    $secondsInAnHour = 60 * $secondsInAMinute;
    $secondsInADay = 24 * $secondsInAnHour;

    $days = floor($inputSeconds / $secondsInADay);
    $hourSeconds = $inputSeconds % $secondsInADay;
    $hours = floor($hourSeconds / $secondsInAnHour);
    $minuteSeconds = $hourSeconds % $secondsInAnHour;
    $minutes = floor($minuteSeconds / $secondsInAMinute);
    $remainingSeconds = $minuteSeconds % $secondsInAMinute;
    $seconds = ceil($remainingSeconds);

    $timeParts = [];
    $sections = [
        'day' => (int)$days,
        'hour' => (int)$hours,
        'minute' => (int)$minutes,
        'second' => (int)$seconds,
    ];

    foreach ($sections as $name => $value) {
        if ($value > 0) {
            $timeParts[] = $value . ' ' . $name . ($value == 1 ? '' : 's');
        }
    }

    return implode(', ', $timeParts);
}
?>