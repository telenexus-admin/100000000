<?php

/**
 * Cached Safaricom OAuth + short curl timeouts for STK initiate plugins.
 */
class PamnetMpesaAuth
{
    /**
     * @return array{ok:bool,token?:string,error?:string,curl_error?:string}
     */
    public static function getAccessToken($consumerKey, $consumerSecret, $env, $cacheKey = 'mpesa')
    {
        $consumerKey = trim((string) $consumerKey);
        $consumerSecret = trim((string) $consumerSecret);
        $env = strtolower(trim((string) $env));
        if ($consumerKey === '' || $consumerSecret === '' || $consumerKey === 'null' || $consumerSecret === 'null') {
            return ['ok' => false, 'error' => 'M-Pesa API keys are empty. Check Admin → Payment Gateway settings.'];
        }

        global $CACHE_PATH;
        $cacheDir = !empty($CACHE_PATH) ? $CACHE_PATH : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $cacheKey);
        $file = $cacheDir . DIRECTORY_SEPARATOR . 'mpesa_oauth_' . $safe . '.json';

        if (is_file($file)) {
            $cached = json_decode((string) @file_get_contents($file), true);
            if (is_array($cached)
                && !empty($cached['token'])
                && !empty($cached['exp'])
                && (int) $cached['exp'] > (time() + 90)
            ) {
                return ['ok' => true, 'token' => (string) $cached['token']];
            }
        }

        $tokenUrl = ($env === 'sandbox')
            ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $lastErr = '';
        $raw = '';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $ch = curl_init($tokenUrl);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'Could not start HTTPS request to Safaricom.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 10,
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $lastErr = curl_error($ch);
                curl_close($ch);
                usleep(200000);
                continue;
            }
            curl_close($ch);
            $decoded = json_decode((string) $raw, true);
            $token = is_array($decoded) ? trim((string) ($decoded['access_token'] ?? '')) : '';
            if ($token !== '') {
                @file_put_contents($file, json_encode([
                    'token' => $token,
                    'exp' => time() + 3300,
                ]));
                return ['ok' => true, 'token' => $token];
            }
            $lastErr = is_array($decoded)
                ? trim((string) ($decoded['error_description'] ?? $decoded['errorMessage'] ?? $decoded['error'] ?? $raw))
                : (string) $raw;
            usleep(200000);
        }

        $hint = $lastErr !== '' ? $lastErr : 'empty response from Safaricom';
        return [
            'ok' => false,
            'error' => 'Failed to generate token (' . $hint . '). Check M-Pesa consumer key/secret and server internet.',
            'curl_error' => $lastErr,
        ];
    }

    /** Safaricom requires 24-hour YmdHis (not 12-hour his). */
    public static function timestamp()
    {
        return date('YmdHis');
    }
}
