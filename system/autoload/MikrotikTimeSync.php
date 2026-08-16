<?php

/**
 * Soft MikroTik time alignment with Pamnet billing.
 * Safe mode: timezone + NTP only. Never force-sets the router clock on connect
 * (forcing clock was taking routers offline).
 */

use PEAR2\Net\RouterOS;

class MikrotikTimeSync
{
    private static $synced = [];

    public static function billingTimezone()
    {
        global $config;
        $tz = trim((string) ($config['timezone'] ?? ''));
        if ($tz === '') {
            $tz = 'Africa/Nairobi';
        }
        try {
            new DateTimeZone($tz);
        } catch (Exception $e) {
            $tz = 'Africa/Nairobi';
        }
        return $tz;
    }

    public static function applyPhpTimezone()
    {
        $tz = self::billingTimezone();
        @date_default_timezone_set($tz);
        return $tz;
    }

    public static function applyMysqlTimezone()
    {
        try {
            $offset = (new DateTime('now'))->format('P');
            ORM::raw_execute("SET time_zone = '" . str_replace("'", '', $offset) . "'");
        } catch (Exception $e) {
        }
    }

    /**
     * Soft sync: set timezone-name + enable NTP. Does NOT rewrite router date/time.
     * @param RouterOS\Client $client
     * @param string $routerKey
     * @param bool $forceClock reserved — ignored (unsafe on live routers)
     */
    public static function syncClient($client, $routerKey = 'default', $forceClock = false)
    {
        global $_app_stage;
        if ($_app_stage == 'Demo' || $_app_stage == 'demo' || !$client) {
            return ['ok' => true, 'message' => 'demo skip'];
        }
        if (!empty(self::$synced[$routerKey])) {
            return self::$synced[$routerKey];
        }

        $tz = self::applyPhpTimezone();
        $msg = [];
        $ok = true;

        // Timezone only (no date/time write)
        try {
            $setTz = new RouterOS\Request('/system/clock/set');
            $setTz->setArgument('time-zone-name', $tz);
            $client->sendSync($setTz);
            $msg[] = "tz={$tz}";
        } catch (Throwable $e) {
            $msg[] = 'tz-skip: ' . $e->getMessage();
        }

        // Prefer NTP so the router corrects itself safely
        try {
            $ntp = new RouterOS\Request('/system/ntp/client/set');
            $ntp->setArgument('enabled', 'yes');
            $ntp->setArgument('servers', 'ke.pool.ntp.org,0.africa.pool.ntp.org,pool.ntp.org');
            $client->sendSync($ntp);
            $msg[] = 'ntp=on';
        } catch (Throwable $e) {
            try {
                $ntp = new RouterOS\Request('/system/ntp/client/set');
                $ntp->setArgument('enabled', 'yes');
                $ntp->setArgument('primary-ntp', 'ke.pool.ntp.org');
                $ntp->setArgument('secondary-ntp', '0.africa.pool.ntp.org');
                $client->sendSync($ntp);
                $msg[] = 'ntp=on-ros6';
            } catch (Throwable $e2) {
                $msg[] = 'ntp-skip: ' . $e2->getMessage();
            }
        }

        // Read-only drift report (never force clock — that took routers offline)
        $drift = null;
        try {
            $print = new RouterOS\Request('/system/clock/print');
            $print->setArgument('.proplist', 'date,time,time-zone-name');
            $clock = $client->sendSync($print);
            $routerDt = self::parseRouterClock(
                (string) $clock->getProperty('date'),
                (string) $clock->getProperty('time'),
                $tz
            );
            if ($routerDt instanceof DateTime) {
                $billingDt = new DateTime('now', new DateTimeZone($tz));
                $drift = $billingDt->getTimestamp() - $routerDt->getTimestamp();
                $msg[] = "drift={$drift}s";
            }
        } catch (Throwable $e) {
            $msg[] = 'read-skip: ' . $e->getMessage();
        }

        $result = [
            'ok' => $ok,
            'message' => implode('; ', $msg),
            'drift' => $drift,
            'timezone' => $tz,
        ];
        self::$synced[$routerKey] = $result;
        return $result;
    }

    public static function parseRouterClock($date, $time, $tzName)
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '' || $time === '') {
            return null;
        }
        // Normalize time to H:i:s
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Exception $e) {
            $tz = new DateTimeZone('Africa/Nairobi');
        }

        // RouterOS 7 often uses ISO date: 2026-08-10
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'M/j/Y H:i:s',
            'M/d/Y H:i:s',
            'M/j/Y H:i',
            'd/m/Y H:i:s',
            'd-m-Y H:i:s',
        ];
        $datetime = $date . ' ' . $time;
        // Legacy RouterOS: aug/10/2026
        $legacy = ucfirst(strtolower($date)) . ' ' . $time;
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $datetime, $tz);
            if ($dt instanceof DateTime) {
                return $dt;
            }
            $dt = DateTime::createFromFormat($fmt, $legacy, $tz);
            if ($dt instanceof DateTime) {
                return $dt;
            }
        }
        try {
            return new DateTime($datetime, $tz);
        } catch (Exception $e) {
            return null;
        }
    }

    public static function expiryComment($expirationDate, $expirationTime)
    {
        $tz = self::billingTimezone();
        $raw = trim($expirationDate . ' ' . $expirationTime);
        $ts = strtotime($raw);
        if ($ts === false) {
            return ' | Expires: ' . $raw;
        }
        $dt = new DateTime('@' . $ts);
        $dt->setTimezone(new DateTimeZone($tz));
        return ' | Expires: ' . $dt->format('Y-m-d H:i:s') . ' ' . $tz;
    }

    public static function remainingSecondsUntilExpiry($username, $routerName = null)
    {
        $q = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->where('status', 'on')
            ->order_by_desc('id');
        if ($routerName) {
            $q->where('routers', $routerName);
        }
        $recharge = $q->find_one();
        if (!$recharge) {
            return null;
        }
        $ts = strtotime(trim($recharge['expiration'] . ' ' . $recharge['time']));
        if ($ts === false) {
            return null;
        }
        return $ts - time();
    }

    public static function formatLimitUptime($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds < 1) {
            $seconds = 1;
        }
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $h = intdiv($seconds, 3600);
        $seconds %= 3600;
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        $hms = sprintf('%02d:%02d:%02d', $h, $m, $s);
        if ($days > 0) {
            return $days . 'd' . $hms;
        }
        return $hms;
    }

    /** Soft sync all routers from cron (timezone + NTP only). */
    public static function syncAllRouters()
    {
        $out = [];
        try {
            $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
        } catch (Exception $e) {
            return ['error' => ['ok' => false, 'message' => $e->getMessage()]];
        }
        foreach ($routers as $r) {
            $name = (string) $r['name'];
            try {
                // Use raw client WITHOUT recursive sync-on-connect
                $iport = explode(':', (string) $r['ip_address']);
                $client = new RouterOS\Client(
                    $iport[0],
                    (string) $r['username'],
                    (string) $r['password'],
                    isset($iport[1]) ? $iport[1] : null
                );
                $out[$name] = self::syncClient($client, $name);
            } catch (Throwable $e) {
                $out[$name] = ['ok' => false, 'message' => $e->getMessage()];
            }
        }
        return $out;
    }
}
