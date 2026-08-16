<?php


/**
 *  using proxy, add this variable in config.php
 *  $http_proxy  = '127.0.0.1:3128';
 *  if proxy using authentication, use this parameter
 *  $http_proxyauth = 'user:password';
 *
 *  Timeouts are in SECONDS. Historically this file used 3000 which is 50 minutes
 *  per call; that made any slow/unreachable notification endpoint freeze the
 *  whole request (e.g. Confirm Recharge would spin for minutes). Defaults are
 *  now sensible and tunable globally via $http_connect_timeout / $http_wait_timeout
 *  in config.php if a slower endpoint is needed.
 **/

class Http
{
    /**
     * Flag set for the duration of an outbound HTTP call so nested error
     * reporters (sendTelegram -> getData -> sendTelegram ...) cannot recurse
     * inside the same request and multiply latency.
     */
    private static $inCall = false;

    private static function resolveTimeouts($connect_timeout, $wait_timeout)
    {
        global $http_connect_timeout, $http_wait_timeout;
        // Historical default used to be 3000s which is a misconfiguration,
        // clamp anything that still passes it down so callers don't hang.
        if ($connect_timeout === null || $connect_timeout >= 120) {
            $connect_timeout = isset($http_connect_timeout) && $http_connect_timeout > 0
                ? (int) $http_connect_timeout
                : 5;
        }
        if ($wait_timeout === null || $wait_timeout >= 120) {
            $wait_timeout = isset($http_wait_timeout) && $http_wait_timeout > 0
                ? (int) $http_wait_timeout
                : 10;
        }
        return [max(1, (int) $connect_timeout), max(1, (int) $wait_timeout)];
    }

    private static function reportError($prefix, $url, $error_msg)
    {
        global $admin;
        if (!$admin || empty($error_msg) || self::$inCall) {
            return;
        }
        // Best-effort background log via Telegram; don't let this call inflate
        // the parent request's runtime if Telegram itself is unhappy.
        self::$inCall = true;
        try {
            Message::sendTelegram(
                "$prefix Error:\n" .
                    _get('_route') . "\n" .
                    "\n$url" .
                    "\n$error_msg"
            );
        } catch (\Throwable $e) {
            // swallow - reporting failure must never break the request
        }
        self::$inCall = false;
    }

    public static function getData($url, $headers = [], $connect_timeout = 5, $wait_timeout = 10)
    {
        global $http_proxy, $http_proxyauth;
        list($connect_timeout, $wait_timeout) = self::resolveTimeouts($connect_timeout, $wait_timeout);
        $error_msg = '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $wait_timeout);
        if (is_array($headers) && count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($http_proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $http_proxy);
            if (!empty($http_proxyauth)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $http_proxyauth);
            }
        }
        $server_output = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }
        curl_close($ch);
        self::reportError('Http::getData', $url, $error_msg);
        if (!empty($server_output)) {
            return $server_output;
        }
        return $error_msg;
    }

    public static function postJsonData($url, $array_post, $headers = [], $basic = null, $connect_timeout = 5, $wait_timeout = 10)
    {
        global $http_proxy, $http_proxyauth;
        list($connect_timeout, $wait_timeout) = self::resolveTimeouts($connect_timeout, $wait_timeout);
        $error_msg = '';
        $headers[] = 'Content-Type: application/json';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $wait_timeout);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        if (!empty($http_proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $http_proxy);
            if (!empty($http_proxyauth)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $http_proxyauth);
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($array_post));
        if (is_array($headers) && count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if (!empty($basic)) {
            curl_setopt($ch, CURLOPT_USERPWD, $basic);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }
        curl_close($ch);
        self::reportError('Http::postJsonData', $url, $error_msg);
        if (!empty($server_output)) {
            return $server_output;
        }
        return $error_msg;
    }


    public static function postData($url, $array_post, $headers = [], $basic = null, $connect_timeout = 5, $wait_timeout = 10)
    {
        global $http_proxy, $http_proxyauth;
        list($connect_timeout, $wait_timeout) = self::resolveTimeouts($connect_timeout, $wait_timeout);
        $error_msg = '';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $wait_timeout);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        if (!empty($http_proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $http_proxy);
            if (!empty($http_proxyauth)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $http_proxyauth);
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($array_post));
        if (is_array($headers) && count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if (!empty($basic)) {
            curl_setopt($ch, CURLOPT_USERPWD, $basic);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
        }
        curl_close($ch);
        self::reportError('Http::postData', $url, $error_msg);
        if (!empty($server_output)) {
            return $server_output;
        }
        return $error_msg;
    }
}
