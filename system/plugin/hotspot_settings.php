<?php

/**
 * Hotspot Settings — save branding, generate portable login.html, upload to MikroTik.
 */

use PEAR2\Net\RouterOS;

register_menu("Hotspot Settings", true, "hotspot_settings", 'AFTER_SETTINGS', 'ion ion-earth');

function hotspot_settings_db()
{
    global $db_host, $db_name, $db_user, $db_pass, $db_password;
    $pass = !empty($db_pass) ? $db_pass : ($db_password ?? '');
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function hotspot_settings_get($conn, $key, $default = '')
{
    $stmt = $conn->prepare("SELECT value FROM tbl_appconfig WHERE setting = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return ($row && array_key_exists('value', $row)) ? (string) $row['value'] : $default;
}

function hotspot_settings_set($conn, $key, $value)
{
    $check = $conn->prepare("SELECT COUNT(*) FROM tbl_appconfig WHERE setting = ?");
    $check->execute([$key]);
    if ((int) $check->fetchColumn() > 0) {
        $upd = $conn->prepare("UPDATE tbl_appconfig SET value = ? WHERE setting = ?");
        $upd->execute([(string) $value, $key]);
    } else {
        $ins = $conn->prepare("INSERT INTO tbl_appconfig (setting, value) VALUES (?, ?)");
        $ins->execute([$key, (string) $value]);
    }
}

function hotspot_settings_main_domain($url)
{
    $host = parse_url((string) $url, PHP_URL_HOST);
    if (!$host) {
        return 'net.pamnetsolutions.co.ke';
    }
    $parts = explode('.', $host);
    $count = count($parts);
    if ($count >= 3) {
        return implode('.', array_slice($parts, -3));
    }
    if ($count >= 2) {
        return implode('.', array_slice($parts, -2));
    }
    return $host;
}

/**
 * Build portable login.html using download.php (same output as Preview/Download).
 */
function hotspot_settings_generate_login_html()
{
    $base = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'net.pamnetsolutions.co.ke';
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    // IMPORTANT: use download=1 (router build). Never preview=1 — preview injects
    // PAMNET_PREVIEW and strips MikroTik tags, which breaks customer Wi-Fi connect.
    $url = $base . '/download.php?download=1&_ts=' . time();
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 30,
            'header' => "Accept: text/html\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $html = (string) @file_get_contents($url, false, $ctx);
    if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) {
        $cmd = 'curl -fsSL --max-time 30 ' . escapeshellarg($url) . ' 2>/dev/null';
        $html = (string) shell_exec($cmd);
    }
    if (strlen($html) < 500 || stripos($html, 'PAMNET_PORTAL') === false) {
        throw new Exception('Could not generate portable login.html from ' . $url);
    }
    // Real preview only: injected assignment / sticky banner — not JS copy like "Preview mode".
    $isPreviewBuild = (stripos($html, 'window.PAMNET_PREVIEW=true') !== false)
        || (stripos($html, 'PREVIEW MODE —') !== false)
        || (stripos($html, 'PREVIEW MODE - this is how') !== false);
    if ($isPreviewBuild || stripos($html, 'chap-id') === false) {
        throw new Exception('Refusing to publish a Preview/broken build to the router');
    }
    return $html;
}

/**
 * Save login.html on the billing server (download + MikroTik fetch source).
 * @return array{path:string,url:string,bytes:int}
 */
function hotspot_settings_store_login_html($html, $billingUrl)
{
    $root = dirname(__DIR__, 2);
    $uploads = $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploads)) {
        @mkdir($uploads, 0755, true);
    }
    $pathUploads = $uploads . DIRECTORY_SEPARATOR . 'hotspot_login.html';
    $pathRoot = $root . DIRECTORY_SEPARATOR . 'hotspot_login.html';
    if (@file_put_contents($pathUploads, $html) === false || @file_put_contents($pathRoot, $html) === false) {
        throw new Exception('Could not write hotspot_login.html on server');
    }
    $url = rtrim((string) $billingUrl, '/') . '/hotspot_login.html';
    return ['path' => $pathRoot, 'url' => $url, 'bytes' => strlen($html)];
}

/**
 * Resolve active Hotspot HTML directory on the router (e.g. RAYPROTECH4 / hotspot).
 */
function hotspot_settings_html_directory($client)
{
    $dir = 'hotspot';
    try {
        $serverProfile = '';
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/print')) as $hs) {
            $disabled = (string) $hs->getProperty('disabled');
            if ($disabled === 'true' || $disabled === 'yes') {
                continue;
            }
            $name = (string) $hs->getProperty('name');
            if ($name === '') {
                continue;
            }
            $serverProfile = (string) $hs->getProperty('profile');
            if ($serverProfile !== '') {
                break;
            }
        }
        if ($serverProfile !== '') {
            foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/profile/print')) as $p) {
                if ((string) $p->getProperty('name') === $serverProfile) {
                    $htmlDir = trim((string) $p->getProperty('html-directory'));
                    if ($htmlDir !== '') {
                        $dir = $htmlDir;
                    }
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        // keep default
    }
    return trim($dir, "/\\") ?: 'hotspot';
}

/**
 * Ensure billing + CDN hosts are in walled garden so captive portal can load the page.
 */
function hotspot_settings_ensure_walled_garden($client, $billingUrl)
{
    $host = parse_url((string) $billingUrl, PHP_URL_HOST);
    if (function_exists('pamnet_ensure_walled_garden_hosts')) {
        pamnet_ensure_walled_garden_hosts($client, (string) $host);
        // Also ensure DoT is blocked and DNS is accepted
        if (function_exists('pamnet_ensure_hotspot_firewall_rules')) {
            pamnet_ensure_hotspot_firewall_rules($client);
        }
        return;
    }

    // Fallback if compat plugin not loaded
    $main = hotspot_settings_main_domain($billingUrl);
    $hosts = array_values(array_unique(array_filter([
        $host,
        $host ? '*.' . $host : '',
        $main,
        $main ? '*.' . $main : '',
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'cdn.tailwindcss.com',
        'ajax.googleapis.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'unpkg.com',
        'code.jquery.com',
        'sweetalert2.github.io',
    ])));

    $existing = [];
    try {
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
            $dst = strtolower((string) $row->getProperty('dst-host'));
            if ($dst !== '') {
                $existing[$dst] = true;
            }
        }
    } catch (Throwable $e) {
        $existing = [];
    }

    foreach ($hosts as $h) {
        $key = strtolower($h);
        if (isset($existing[$key])) {
            continue;
        }
        try {
            $add = new RouterOS\Request('/ip/hotspot/walled-garden/add');
            $add->setArgument('dst-host', $h);
            $client->sendSync($add);
            $existing[$key] = true;
        } catch (Throwable $e) {
            // ignore duplicates / permission issues per host
        }
    }

    // IP walled garden accept for billing host
    if ($host) {
        try {
            $addIp = new RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
            $addIp->setArgument('action', 'accept');
            $addIp->setArgument('dst-host', $host);
            $client->sendSync($addIp);
        } catch (Throwable $e) {
        }
    }
}

/**
 * Upload login.html to MikroTik HTML directory via /tool/fetch.
 * @return array{ok:bool,directory:string,path:string,message:string}
 */
function hotspot_settings_upload_to_router($routerId, $publicUrl)
{
    $router = ORM::for_table('tbl_routers')->find_one((int) $routerId);
    if (!$router) {
        return ['ok' => false, 'directory' => '', 'path' => '', 'message' => 'Router not found'];
    }

    try {
        $client = Mikrotik::getClient($router['ip_address'], $router['username'], $router['password']);
        if (!$client) {
            return ['ok' => false, 'directory' => '', 'path' => '', 'message' => 'MikroTik client unavailable (demo mode?)'];
        }

        $dir = hotspot_settings_html_directory($client);
        $dst = $dir . '/login.html';

        // Remove previous file if present (ignore errors)
        try {
            $print = new RouterOS\Request('/file/print');
            $print->setQuery(RouterOS\Query::where('name', $dst));
            foreach ($client->sendSync($print) as $f) {
                $id = $f->getProperty('.id');
                if ($id) {
                    $rm = new RouterOS\Request('/file/remove');
                    $rm->setArgument('.id', $id);
                    $client->sendSync($rm);
                }
            }
        } catch (Throwable $e) {
        }

        $fetch = new RouterOS\Request('/tool/fetch');
        $fetch->setArgument('url', $publicUrl);
        $fetch->setArgument('dst-path', $dst);
        // https URLs
        if (stripos($publicUrl, 'https://') === 0) {
            $fetch->setArgument('mode', 'https');
        } else {
            $fetch->setArgument('mode', 'http');
        }
        $client->sendSync($fetch);

        // Give RouterOS a moment to finish writing
        usleep(1500000);

        $billing = hotspot_settings_get(hotspot_settings_db(), 'hotspot_billing_url', defined('APP_URL') ? APP_URL : '');
        hotspot_settings_ensure_walled_garden($client, $billing ?: $publicUrl);

        // Verify file exists
        $size = '';
        foreach ($client->sendSync(new RouterOS\Request('/file/print')) as $f) {
            if ((string) $f->getProperty('name') === $dst) {
                $size = (string) $f->getProperty('size');
                break;
            }
        }
        if ($size === '') {
            return [
                'ok' => false,
                'directory' => $dir,
                'path' => $dst,
                'message' => 'Fetch ran but ' . $dst . ' was not found on router',
            ];
        }

        return [
            'ok' => true,
            'directory' => $dir,
            'path' => $dst,
            'message' => 'Uploaded to ' . $dst . ' (' . $size . ' bytes) and walled garden updated',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'directory' => '',
            'path' => '',
            'message' => 'MikroTik upload failed: ' . $e->getMessage(),
        ];
    }
}

function hotspot_settings()
{
    global $ui;
    _admin();
    $ui->assign('_title', 'Hotspot Settings');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);

    if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'], true)) {
        r2(U . "dashboard", 'e', Lang::T("You Do Not Have Access"));
    }

    try {
        $conn = hotspot_settings_db();
    } catch (Throwable $e) {
        r2(U . "dashboard", 'e', 'Database connection failed for Hotspot Settings');
    }

    // Save settings + publish login.html to router
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $conn->beginTransaction();

            $hotspotTitle = trim((string) (_post('hotspot_title') ?: hotspot_settings_get($conn, 'hotspot_title', 'PAMNET SOLUTIONS')));
            $phone = trim((string) (_post('phone') ?: ''));
            $faq1 = trim((string) (_post('faq1') ?: ''));
            $faq2 = trim((string) (_post('faq2') ?: ''));
            $faq3 = trim((string) (_post('faq3') ?: ''));
            $colorScheme = trim((string) (_post('color_scheme') ?: hotspot_settings_get($conn, 'color_scheme', 'green')));
            $routerId = trim((string) (_post('router_id') ?: ''));
            $autoManual = trim((string) (_post('auto_manual_display') ?: 'auto'));
            $bgUrl = trim((string) (_post('background_image_url') ?: ''));
            $loginUrl = trim((string) (_post('hotspot_login_url') ?: 'http://10.0.0.1/login'));
            if ($loginUrl === '') {
                $loginUrl = 'http://10.0.0.1/login';
            }
            $billingUrl = trim((string) (_post('hotspot_billing_url') ?: ''));
            if ($billingUrl === '') {
                $billingUrl = defined('APP_URL') ? APP_URL : 'https://net.pamnetsolutions.co.ke';
            }
            $billingUrl = rtrim($billingUrl, '/');
            if (!preg_match('#^https?://#i', $billingUrl)) {
                $billingUrl = 'https://' . ltrim($billingUrl, '/');
            }

            $settings = [
                'hotspot_title' => $hotspotTitle,
                'phone' => $phone,
                'faq1' => $faq1,
                'faq2' => $faq2,
                'faq3' => $faq3,
                'color_scheme' => $colorScheme,
                'auto_manual_display' => $autoManual,
                'background_image_url' => $bgUrl,
                'hotspot_login_url' => $loginUrl,
                'hotspot_billing_url' => $billingUrl,
            ];

            if ($routerId !== '') {
                $settings['router_id'] = $routerId;
                $rst = $conn->prepare("SELECT name FROM tbl_routers WHERE id = ? LIMIT 1");
                $rst->execute([(int) $routerId]);
                $router = $rst->fetch();
                if ($router && !empty($router['name'])) {
                    $settings['router_name'] = $router['name'];
                } else {
                    throw new Exception('Selected router was not found.');
                }
            }

            foreach ($settings as $key => $value) {
                hotspot_settings_set($conn, $key, $value);
            }

            $conn->commit();

            // Generate + store + push to MikroTik
            $msg = 'Hotspot settings saved.';
            try {
                $html = hotspot_settings_generate_login_html();
                $stored = hotspot_settings_store_login_html($html, $billingUrl);
                $msg .= ' login.html ready (' . $stored['bytes'] . ' bytes).';

                if ($routerId !== '') {
                    $up = hotspot_settings_upload_to_router((int) $routerId, $stored['url']);
                    if ($up['ok']) {
                        $msg .= ' ' . $up['message'] . '.';
                        r2(U . 'plugin/hotspot_settings', 's', $msg);
                    } else {
                        r2(U . 'plugin/hotspot_settings', 'e', $msg . ' Router upload failed: ' . $up['message'] . ' — use Download login.html as backup.');
                    }
                } else {
                    r2(U . 'plugin/hotspot_settings', 's', $msg . ' Select a router to auto-upload.');
                }
            } catch (Throwable $genErr) {
                r2(U . 'plugin/hotspot_settings', 'e', $msg . ' Generate/upload error: ' . $genErr->getMessage());
            }
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            r2(U . 'plugin/hotspot_settings', 'e', 'Could not save settings: ' . $e->getMessage());
        }
    }

    // Display values
    $hotspotTitle = hotspot_settings_get($conn, 'hotspot_title', 'PAMNET SOLUTIONS');
    $phone = hotspot_settings_get($conn, 'phone', '');
    $faq1 = hotspot_settings_get($conn, 'faq1', '');
    $faq2 = hotspot_settings_get($conn, 'faq2', '');
    $faq3 = hotspot_settings_get($conn, 'faq3', '');
    $selectedColorScheme = hotspot_settings_get($conn, 'color_scheme', 'green');
    $hotspotLoginUrl = hotspot_settings_get($conn, 'hotspot_login_url', 'http://10.0.0.1/login');
    $billingUrl = hotspot_settings_get($conn, 'hotspot_billing_url', '');
    $selectedRouterId = hotspot_settings_get($conn, 'router_id', '');
    $selectedAutoManual = hotspot_settings_get($conn, 'auto_manual_display', 'auto');
    $selectedShape = hotspot_settings_get($conn, 'shape_selector', '');
    $backgroundImageUrl = hotspot_settings_get($conn, 'background_image_url', '');

    $routers = [];
    try {
        $routers = $conn->query("SELECT id, name FROM tbl_routers ORDER BY name ASC")->fetchAll();
    } catch (Throwable $e) {
        $routers = [];
    }

    $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://net.pamnetsolutions.co.ke';
    if ($billingUrl === '') {
        $billingUrl = $appUrl;
    }
    $billingUrl = rtrim($billingUrl, '/');
    $host = parse_url($billingUrl, PHP_URL_HOST) ?: (parse_url($appUrl, PHP_URL_HOST) ?: 'net.pamnetsolutions.co.ke');
    $mainDomain = hotspot_settings_main_domain($billingUrl !== '' ? $billingUrl : $appUrl);

    $ui->assign('hotspot_title', $hotspotTitle);
    $ui->assign('phone', $phone);
    $ui->assign('faq1', $faq1);
    $ui->assign('faq2', $faq2);
    $ui->assign('faq3', $faq3);
    $ui->assign('hotspot_login_url', $hotspotLoginUrl);
    $ui->assign('hotspot_billing_url', $billingUrl);
    $ui->assign('routers', $routers);
    $ui->assign('selected_router_id', $selectedRouterId);
    $ui->assign('selected_color_scheme', $selectedColorScheme);
    $ui->assign('selected_auto_manual_display', $selectedAutoManual);
    $ui->assign('selected_shape_selector', $selectedShape);
    $ui->assign('background_image_url', $backgroundImageUrl);
    $ui->assign('app_url', $appUrl);
    $ui->assign('_domain', $host);
    $ui->assign('main_domain', $mainDomain);

    $ui->display('hotspot_settings.tpl');
}
