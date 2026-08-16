<?php

// Block if no token provided
$token = trim($_GET['auth'] ?? ($_GET['token'] ?? ''));
if (empty($token)) {
    r2(getUrl('login'));
}

// Already admin — just go to dashboard
if (Admin::getID()) {
    r2(getUrl('dashboard'));
}

try {
    // Validate the one-time token with NuxHost
    $result = _nhImpersonateValidate($token);
    error_log('[NuxHost Impersonate] Validation result: ' . json_encode($result));
    if (!$result['success']) {
        throw new \Exception($result['message'] ?? 'Token validation failed.');
    }

    $adminUsername = $result['username'] ?? 'admin';
    $sourceAdmin   = $result['source_admin'] ?? 'system_provider';
    $sourceIp      = $result['source_ip'] ?? 'unknown';

    // Ensure admin user exists in tbl_users
    $adminId = _nhImpersonateEnsureUser($adminUsername);
    error_log('[NuxHost Impersonate] Admin ID resolved: ' . var_export($adminId, true));
    if (!$adminId) {
        throw new \Exception('Failed to prepare admin account.');
    }

    // Set session and cookie — identical to what admin.php does on normal login
    $_SESSION['aid']                    = $adminId;
    $_SESSION['impersonated']           = true;
    $_SESSION['impersonate_source']     = $sourceAdmin;
    $_SESSION['impersonate_source_ip']  = $sourceIp;
    $_SESSION['impersonate_start_time'] = time();
    $_SESSION['impersonate_admin_name'] = $adminUsername;

    // Build and set the auth cookie safely (mirrors Admin::setCookie but with null-safe token upsert)
    global $db_pass, $config;
    $time     = time();
    $aidToken = $adminId . '.' . $time . '.' . sha1("$adminId.$time.$db_pass");
    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('aid', $aidToken, [
        'expires'  => $time + 86400 * 7,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Persist login token — use UPDATE safely; upsertToken() crashes if user not found
    $tokenRow = ORM::for_table('tbl_users')->find_one($adminId);
    if ($tokenRow) {
        $tokenRow->login_token = sha1($aidToken);
        $tokenRow->save();
    }
    error_log('[NuxHost Impersonate] Cookie set for admin_id=' . $adminId);

    _log(
        "NuxHost impersonation login: $adminUsername (initiated by $sourceAdmin)",
        'SuperAdmin',
        $adminId
    );

    // _alert() renders an HTML auto-redirect page (exactly like real admin login does),
    // ensuring the browser persists the Set-Cookie before the next request.
    _alert(
        'Logged in via NuxHost Support. You are viewing as <strong>' . htmlspecialchars($adminUsername) . '</strong>.',
        'success',
        'dashboard'
    );

} catch (\Throwable $e) {
    error_log('[NuxHost Impersonate] ERROR: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('[NuxHost Impersonate] Trace: ' . $e->getTraceAsString());
    // Show a plain error page without relying on $ui (avoids secondary errors)
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Impersonation Failed</title>'
       . '<link rel="stylesheet" href="' . APP_URL . '/ui/ui/styles/bootstrap.min.css"></head>'
       . '<body style="padding:40px">'
       . '<div class="alert alert-danger"><strong>Impersonation Failed</strong><br>'
       . htmlspecialchars($e->getMessage()) . '</div>'
       . '<a href="' . APP_URL . '/?_route=admin" class="btn btn-default">Back to Login</a>'
       . '</body></html>';
    exit;
}

/* ─────────────────────────────────────────────────────────────── helpers ── */

/**
 * Match Host header to NuxHost FQDN (strip :port so e.g. client.test:8080 validates as client.test).
 */
function _nhNormalizeHttpHost(string $host): string
{
    $host = trim($host);
    if ($host === '') {
        return '';
    }
    if ($host[0] === '[') {
        $end = strpos($host, ']');
        if ($end !== false && isset($host[$end + 1]) && $host[$end + 1] === ':') {
            return substr($host, 1, $end - 1);
        }

        return $host;
    }
    $pos = strrpos($host, ':');
    if ($pos !== false) {
        $tail = substr($host, $pos + 1);
        if ($tail !== '' && ctype_digit($tail)) {
            return substr($host, 0, $pos);
        }
    }

    return $host;
}

/**
 * Validate the one-time token by calling the NuxHost API.
 */
function _nhImpersonateValidate($token)
{
    $nuxhostUrl = defined('NUXHOST_URL') ? NUXHOST_URL : null;
    if (!$nuxhostUrl) {
        return ['success' => false, 'message' => 'NUXHOST_URL not configured in config.php.'];
    }

    $domain = _nhNormalizeHttpHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $url    = rtrim($nuxhostUrl, '/') . '/?app_route=isp_api_validate_impersonate&'
            . http_build_query([
                'token'     => $token,
                'domain'    => $domain,
                'timestamp' => time(),
            ]);

    $verify = getenv('NUXHOST_CURL_INSECURE');
    $tlsOk  = ($verify === false || $verify === '') || ! in_array(strtolower((string) $verify), ['1', 'true', 'yes'], true);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $tlsOk);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $tlsOk ? 2 : 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: phpnuxbill-impersonate/2.0']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'message' => 'cURL error: ' . $curlErr];
    }
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => "NuxHost API returned HTTP $httpCode"];
    }

    $data = json_decode($response, true);
    if (empty($data) || $data['status'] !== 'ok') {
        return ['success' => false, 'message' => $data['message'] ?? 'Invalid API response.'];
    }

    return [
        'success'      => true,
        'username'     => $data['username']     ?? 'admin',
        'source_admin' => $data['source_admin'] ?? 'system_provider',
        'source_ip'    => $data['source_ip']    ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ];
}

/**
 * Return the ID of an active SuperAdmin/Admin user in this tenant.
 *
 * We intentionally use the tenant's own first SuperAdmin rather than
 * creating a new account — impersonation gives NuxHost support the
 * ISP owner's existing admin access.
 */
function _nhImpersonateEnsureUser($username)
{
    try {
        // 1. Try exact username match (works if NuxHost username == tenant admin username)
        $user = ORM::for_table('tbl_users')
            ->where('username', $username)
            ->where('status', 'Active')
            ->find_one();

        if ($user) {
            return (int) $user->id;
        }

        // 2. Fall back to the first active SuperAdmin in this tenant
        $user = ORM::for_table('tbl_users')
            ->where('user_type', 'SuperAdmin')
            ->where('status', 'Active')
            ->find_one();

        if ($user) {
            return (int) $user->id;
        }

        // 3. Any active admin
        $user = ORM::for_table('tbl_users')
            ->where('status', 'Active')
            ->find_one();

        return $user ? (int) $user->id : null;

    } catch (\Throwable $e) {
        error_log('_nhImpersonateEnsureUser error: ' . $e->getMessage());
        return null;
    }
}
