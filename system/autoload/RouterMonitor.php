<?php

/**
 * Router reachability checks and admin notifications.
 * Intended to run from system/cron.php only — not on dashboard requests.
 */
class RouterMonitor
{
    public static function run()
    {
        global $config;

        if (empty($config['router_check'])) {
            return;
        }

        $routers = ORM::for_table('tbl_routers')
            ->where('enabled', '1')
            ->find_many();

        if (!$routers || count($routers) === 0) {
            echo "No enabled routers found in the database.\n";

            return;
        }

        $offlineRouters = [];
        $onlineRouters = [];

        foreach ($routers as $router) {
            $previous_status = $router->status;
            if (php_sapi_name() === 'cli') {
                echo "Checking router {$router->name} ({$router->ip_address})... ";
            }
            $current_status = self::checkRouterStatus($router);
            if (php_sapi_name() === 'cli') {
                echo "{$current_status}\n";
            }
            $current_time = date('Y-m-d H:i:s');

            if ($previous_status != $current_status) {
                if ($current_status == 'Offline') {
                    $router->offline_time = $current_time;
                    $router->save();
                    $offlineRouters[] = $router;
                    self::logEvent("Router {$router->name} went OFFLINE at {$current_time}");
                } elseif ($current_status == 'Online') {
                    $downtime = self::calculateDowntime($router->offline_time, $current_time);
                    $onlineRouters[] = [
                        'router' => $router,
                        'downtime' => $downtime,
                        'offline_start' => $router->offline_time,
                    ];
                    self::logEvent("Router {$router->name} came ONLINE at {$current_time} Downtime: {$downtime}");
                    $router->offline_time = null;
                    $router->save();
                }

                $router->status = $current_status;
                if ($current_status == 'Online') {
                    $router->last_seen = $current_time;
                }
                $router->save();
            } elseif ($current_status == 'Online') {
                $router->last_seen = $current_time;
                $router->save();
            } elseif ($current_status == 'Offline') {
                if (empty($router->offline_time)) {
                    $router->offline_time = $current_time;
                    $router->save();
                    self::logEvent("Router {$router->name} is OFFLINE (recorded offline time)");
                }
            }
        }

        self::sendStatusNotifications($offlineRouters, $onlineRouters);
    }

    private static function calculateDowntime($offline_time, $online_time)
    {
        if (empty($offline_time)) {
            return '0s (immediate recovery)';
        }

        $offline = strtotime($offline_time);
        $online = strtotime($online_time);
        $diff = $online - $offline;

        if ($diff <= 0) {
            return '0s (immediate recovery)';
        }

        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($seconds > 0) {
            $parts[] = "{$seconds}s";
        }

        return empty($parts) ? '0s' : implode(', ', $parts);
    }

    private static function checkRouterStatus($router)
    {
        $ip_address = $router->ip_address;
        $port = 8728;

        if (strpos($ip_address, ':') !== false) {
            list($ip_address, $port) = explode(':', $ip_address, 2);
        }

        $timeout = 3;

        try {
            if (function_exists('fsockopen') && false === stripos(ini_get('disable_functions'), 'fsockopen')) {
                $fsock = @fsockopen($ip_address, (int) $port, $errno, $errstr, $timeout);
                if ($fsock) {
                    fclose($fsock);

                    return 'Online';
                }
            }

            if (function_exists('stream_socket_client') && false === stripos(ini_get('disable_functions'), 'stream_socket_client')) {
                $connection = @stream_socket_client(
                    "{$ip_address}:{$port}",
                    $errno,
                    $errstr,
                    $timeout
                );
                if ($connection) {
                    fclose($connection);

                    return 'Online';
                }
            }

            $isWin = (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows')
                || (defined('PHP_OS') && strncasecmp(PHP_OS, 'WIN', 3) === 0);
            if ($isWin) {
                @exec('ping -n 1 -w 1000 ' . escapeshellarg($ip_address) . ' 2>&1', $output, $return_var);
            } else {
                @exec('ping -c 1 -W 1 ' . escapeshellarg($ip_address) . ' 2>&1', $output, $return_var);
            }
            if (isset($return_var) && $return_var === 0) {
                return 'Online';
            }
        } catch (Throwable $e) {
            self::logEvent("Error checking router {$router->name}: " . $e->getMessage());
        }

        return 'Offline';
    }

    private static function sendStatusNotifications($offlineRouters, $onlineRouters)
    {
        if (!empty($offlineRouters)) {
            foreach ($offlineRouters as $router) {
                $timestamp = date('Y-m-d H:i:s');
                $message = "[{$router->name}] Router {$router->name} ({$router->ip_address}) is OFFLINE at {$timestamp}.";
                self::notifyAdmins($message, 'Offline');
                if (php_sapi_name() === 'cli') {
                    echo "{$message}\n";
                }
            }
        }

        if (!empty($onlineRouters)) {
            foreach ($onlineRouters as $data) {
                $router = $data['router'];
                $downtime = $data['downtime'];
                $timestamp = date('Y-m-d H:i:s');
                $message = "[{$router->name}] Router {$router->name} ({$router->ip_address}) is ONLINE at {$timestamp}. Downtime: {$downtime}.";
                self::notifyAdmins($message, 'Online');
                if (php_sapi_name() === 'cli') {
                    echo "{$message}\n";
                }
            }
        }
    }

    private static function notifyAdmins($message, $statusLabel)
    {
        self::logMessage('Router Alert', $message, $statusLabel);

        $phones = function_exists('getAdminPhoneNumbers') ? getAdminPhoneNumbers() : self::getAdminPhoneNumbers();
        if (empty($phones)) {
            self::logEvent('No admin phone numbers found for router SMS notification');

            return;
        }

        foreach ($phones as $phone) {
            $phone = trim((string) $phone);
            if ($phone === '') {
                continue;
            }
            try {
                if (function_exists('sendSms')) {
                    sendSms($phone, $message);
                } else {
                    Message::sendSMS($phone, $message);
                }
            } catch (Throwable $e) {
                self::logEvent("Error sending router SMS to {$phone}: " . $e->getMessage());
            }
        }
    }

    private static function getAdminPhoneNumbers()
    {
        $phones = [];

        $admins = ORM::for_table('tbl_users')
            ->where('user_type', 'SuperAdmin')
            ->where('status', 'Active')
            ->find_many();

        foreach ($admins as $admin) {
            if (!empty($admin->phone)) {
                $phones[] = trim($admin->phone);
            }
        }

        $adminPhone = ORM::for_table('tbl_appconfig')
            ->where('setting', 'admin_phone')
            ->find_one();

        if ($adminPhone && !empty($adminPhone->value)) {
            $phones[] = trim($adminPhone->value);
        }

        return array_values(array_unique(array_filter($phones)));
    }

    private static function logMessage($type, $message, $status = 'Info')
    {
        try {
            $log = ORM::for_table('tbl_message_logs')->create();
            $log->message_type = $type;
            $log->recipient = 'System';
            $log->message_content = $message;
            $log->status = $status;
            $log->save();
        } catch (Throwable $e) {
            // ignore
        }
    }

    private static function logEvent($message)
    {
        try {
            $log = ORM::for_table('tbl_logs')->create();
            $log->date = date('Y-m-d H:i:s');
            $log->type = 'Router Monitor';
            $log->description = $message;
            $log->userid = 0;
            $log->ip = php_sapi_name() === 'cli' ? 'CLI' : 'HTTP';
            $log->save();
        } catch (Throwable $e) {
            if (php_sapi_name() === 'cli') {
                echo date('Y-m-d H:i:s') . " - {$message}\n";
            }
        }
    }
}
