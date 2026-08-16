<?php
include 'config.php';
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Function to get a setting value
function getSettingValue($mysqli, $setting)
{
    $query = $mysqli->prepare("SELECT value FROM tbl_appconfig WHERE setting = ?");
    $query->bind_param("s", $setting);
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['value'];
    }
    return '';
}

/**
 * Normalize a public billing base URL for login.html (works on any host / subdirectory).
 */
function normalizeBillingBase($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    return rtrim($url, '/');
}

/**
 * Detect current site base even behind Cloudflare / reverse proxies / subfolders.
 */
function detectSiteBaseUrl()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_CF_VISITOR']) && stripos((string) $_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
    $protocol = $https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $baseDir = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') : '';
    if ($baseDir === '/' || $baseDir === '\\' || $baseDir === '.') {
        $baseDir = '';
    }
    return rtrim($protocol . $host . $baseDir, '/');
}

// Fetch hotspot title and description from tbl_appconfig
$hotspotTitle = getSettingValue($mysqli, 'hotspot_title');
if ($hotspotTitle === '') {
    $hotspotTitle = 'Hotspot Portal';
}
$company = getSettingValue($mysqli, 'CompanyName');
$faq1 = getSettingValue($mysqli, 'faq1');
$faq2 = getSettingValue($mysqli, 'faq2');
$faq3 = getSettingValue($mysqli, 'faq3');
$phone = getSettingValue($mysqli, 'phone');
$color_scheme = getSettingValue($mysqli, 'color_scheme');
if ($color_scheme === '') {
    $color_scheme = 'green';
}
$color_scheme = preg_replace('/[^a-z0-9_-]/i', '', $color_scheme) ?: 'green';

$supportLinkColorClass = "text-{$color_scheme}-400 hover:text-{$color_scheme}-300";
$buttonClass = "bg-{$color_scheme}-700 hover:bg-{$color_scheme}-800";
$buttonTextColor = "text-white";
$priceClass = "text-{$color_scheme}-400";

// Fetch router name and router ID from tbl_appconfig
$routerName = getSettingValue($mysqli, 'router_name');
$routerId = getSettingValue($mysqli, 'router_id');
if ($routerName === '' || $routerId === '') {
    // Soft fail so preview/download still works on fresh installs
    $routerName = $routerName !== '' ? $routerName : 'default';
    $routerId = $routerId !== '' ? $routerId : '1';
}

// Billing API base baked into login.html — portable across hosts
$billingBase = normalizeBillingBase(getSettingValue($mysqli, 'hotspot_billing_url'));
if ($billingBase === '') {
    $billingBase = normalizeBillingBase(defined('APP_URL') ? APP_URL : '');
}
if ($billingBase === '') {
    $billingBase = detectSiteBaseUrl();
}
// Optional override when downloading for another host: ?billing_url=https://other.example.com
if (!empty($_GET['billing_url'])) {
    $billingBase = normalizeBillingBase($_GET['billing_url']);
}
$billingBaseJs = json_encode($billingBase, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

// Embed MikroTik CHAP helper so login works even if router md5.js path fails
$md5Inline = '';
$md5File = __DIR__ . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'md5.js';
if (is_file($md5File)) {
    $md5Inline = (string) file_get_contents($md5File);
}

// Fallback MikroTik Hotspot login URL (LAN gateway) when $(link-login-only) is not substituted
$hotspotLoginUrl = getSettingValue($mysqli, 'hotspot_login_url');
if ($hotspotLoginUrl === '') {
    $hotspotLoginUrl = 'http://10.0.0.1/login';
}
$hotspotLoginUrlJs = json_encode($hotspotLoginUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
$routerIdJs = json_encode((string) $routerId, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

// Fetch available plans with offer plans prioritized
$planQuery = "SELECT id, name_plan, price, validity, validity_unit, shared_users FROM tbl_plans WHERE routers = ? AND type = 'Hotspot' AND enabled = 1 ORDER BY CASE WHEN LOWER(name_plan) LIKE '%offer%' THEN 0 ELSE 1 END, CAST(price AS DECIMAL(10,2)) ASC";
$currency_code = getSettingValue($mysqli, 'currency_code');
if ($currency_code === '') {
    $currency_code = 'KES';
}
$planStmt = $mysqli->prepare($planQuery);
$planStmt->bind_param("s", $routerName);
$planStmt->execute();
$planResult = $planStmt->get_result();

// Initialize HTML content variable
$htmlContent = "<!DOCTYPE html>\n";
$htmlContent .= "<html lang=\"en\">\n";
$htmlContent .= "<head>\n";
$htmlContent .= "    <meta charset=\"UTF-8\">\n";
$htmlContent .= "    <meta http-equiv=\"Cache-Control\" content=\"no-cache, no-store, must-revalidate\">\n";
$htmlContent .= "    <meta http-equiv=\"Pragma\" content=\"no-cache\">\n";
$htmlContent .= "    <meta name=\"pamnet-build\" content=\"universal-tv-" . date('YmdHis') . "\">\n";
$htmlContent .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover\">\n";
$htmlContent .= "    <meta name=\"pamnet-portal\" content=\"universal\">\n";
$htmlContent .= "    <meta name=\"pamnet-device-policy\" content=\"agnostic\">\n";
$htmlContent .= "    <meta name=\"pamnet-tv-policy\" content=\"agnostic\">\n";
$htmlContent .= "    <meta name=\"pamnet-billing-base\" content=\"" . htmlspecialchars($billingBase, ENT_QUOTES, 'UTF-8') . "\">\n";
$htmlContent .= "    <title>" . htmlspecialchars($hotspotTitle) . "</title>\n";
// Portable runtime config + API helper (works when moved to another billing host)
// Progressive enhancement: XHR fallback for old Android WebViews without fetch/URLSearchParams.
$htmlContent .= "    <script>\n";
$htmlContent .= "    (function () {\n";
$htmlContent .= "      // Minimal Promise shim for very old captive WebViews\n";
$htmlContent .= "      if (typeof window.Promise === 'undefined') {\n";
$htmlContent .= "        window.Promise = function (fn) {\n";
$htmlContent .= "          var self = this; self._ok = []; self._err = []; self._s = 0; self._v = null;\n";
$htmlContent .= "          function res(v) { if (self._s) return; self._s = 1; self._v = v; for (var i=0;i<self._ok.length;i++) try{self._ok[i](v);}catch(e){} }\n";
$htmlContent .= "          function rej(v) { if (self._s) return; self._s = 2; self._v = v; for (var j=0;j<self._err.length;j++) try{self._err[j](v);}catch(e){} }\n";
$htmlContent .= "          this.then = function (onFulfilled, onRejected) {\n";
$htmlContent .= "            var next = new Promise(function (resolve, reject) {\n";
$htmlContent .= "              function handle(fn, settle) {\n";
$htmlContent .= "                return function (v) {\n";
$htmlContent .= "                  if (typeof fn !== 'function') { settle(v); return; }\n";
$htmlContent .= "                  try {\n";
$htmlContent .= "                    var r = fn(v);\n";
$htmlContent .= "                    if (r && typeof r.then === 'function') { r.then(resolve, reject); }\n";
$htmlContent .= "                    else { resolve(r); }\n";
$htmlContent .= "                  } catch (e) { reject(e); }\n";
$htmlContent .= "                };\n";
$htmlContent .= "              }\n";
$htmlContent .= "              if (self._s === 1) { setTimeout(function(){ handle(onFulfilled, resolve)(self._v); }, 0); }\n";
$htmlContent .= "              else if (self._s === 2) { setTimeout(function(){ handle(onRejected, reject)(self._v); }, 0); }\n";
$htmlContent .= "              else { self._ok.push(handle(onFulfilled, resolve)); self._err.push(handle(onRejected, reject)); }\n";
$htmlContent .= "            });\n";
$htmlContent .= "            return next;\n";
$htmlContent .= "          };\n";
$htmlContent .= "          this.catch = function (f) { return this.then(null, f); };\n";
$htmlContent .= "          try { fn(res, rej); } catch (e) { rej(e); }\n";
$htmlContent .= "        };\n";
$htmlContent .= "        window.Promise.resolve = function (v) { return new Promise(function (r) { r(v); }); };\n";
$htmlContent .= "        window.Promise.reject = function (v) { return new Promise(function (a, r) { r(v); }); };\n";
$htmlContent .= "      }\n";
$htmlContent .= "      window.pamnetQueryParam = function (name) {\n";
$htmlContent .= "        try {\n";
$htmlContent .= "          var q = String(window.location.search || '');\n";
$htmlContent .= "          if (q.charAt(0) === '?') { q = q.substring(1); }\n";
$htmlContent .= "          var parts = q.split('&');\n";
$htmlContent .= "          for (var i = 0; i < parts.length; i++) {\n";
$htmlContent .= "            var kv = parts[i].split('=');\n";
$htmlContent .= "            var k = decodeURIComponent((kv[0] || '').replace(/\\+/g, ' '));\n";
$htmlContent .= "            if (k === name) {\n";
$htmlContent .= "              return decodeURIComponent((kv.slice(1).join('=') || '').replace(/\\+/g, ' '));\n";
$htmlContent .= "            }\n";
$htmlContent .= "          }\n";
$htmlContent .= "        } catch (eQ) {}\n";
$htmlContent .= "        return '';\n";
$htmlContent .= "      };\n";
$htmlContent .= "      // Optional analytics/UI hint only — NEVER used to deny Wi-Fi or payment.\n";
$htmlContent .= "      window.pamnetClassifyClient = function () {\n";
$htmlContent .= "        var ua = '';\n";
$htmlContent .= "        try { ua = String(navigator.userAgent || ''); } catch (eUa) {}\n";
$htmlContent .= "        var tvHint = /SmartTV|Smart-TV|Smart TV|GoogleTV|Google TV|Android TV|BRAVIA|Tizen|webOS|Web0S|NetCast|VIDAA|HbbTV|Opera TV|AFT[A-Z]|Roku|CrKey|MiTV|MiBox|Shield|SetTopBox|Set-Top|DTV|TV/i.test(ua);\n";
$htmlContent .= "        var largeScreen = false;\n";
$htmlContent .= "        try {\n";
$htmlContent .= "          var sw = (window.screen && window.screen.width) ? window.screen.width : 0;\n";
$htmlContent .= "          var sh = (window.screen && window.screen.height) ? window.screen.height : 0;\n";
$htmlContent .= "          largeScreen = Math.max(sw, sh) >= 960;\n";
$htmlContent .= "        } catch (eSc) {}\n";
$htmlContent .= "        var mobileHint = /Mobile|iPhone|iPod|Android.*Mobile|Phone/i.test(ua);\n";
$htmlContent .= "        if (tvHint) {\n";
$htmlContent .= "          try { document.documentElement.className += ' pamnet-tv-hint'; } catch (eTv) {}\n";
$htmlContent .= "          return 'TV_CLIENT';\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (largeScreen && !mobileHint) {\n";
$htmlContent .= "          try { document.documentElement.className += ' pamnet-tv-hint'; } catch (eTv2) {}\n";
$htmlContent .= "          return 'UNKNOWN_TV';\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (!ua) { return 'UNKNOWN_DEVICE'; }\n";
$htmlContent .= "        return 'CLIENT';\n";
$htmlContent .= "      };\n";
$htmlContent .= "      var baked = " . $billingBaseJs . ";\n";
$htmlContent .= "      var base = baked;\n";
$htmlContent .= "      try {\n";
$htmlContent .= "        var override = pamnetQueryParam('billing') || pamnetQueryParam('billing_url') || pamnetQueryParam('api') || '';\n";
$htmlContent .= "        if (override) {\n";
$htmlContent .= "          base = String(override).replace(/\\/+$/, '');\n";
$htmlContent .= "          try { localStorage.setItem('pamnet_billing_base', base); } catch (e0) {}\n";
$htmlContent .= "        } else {\n";
$htmlContent .= "          var saved = localStorage.getItem('pamnet_billing_base') || '';\n";
$htmlContent .= "          if (saved) { base = String(saved).replace(/\\/+$/, ''); }\n";
$htmlContent .= "        }\n";
$htmlContent .= "      } catch (e1) {}\n";
$htmlContent .= "      if (!base) { base = baked; }\n";
$htmlContent .= "      window.PAMNET_PORTAL = {\n";
$htmlContent .= "        apiBase: String(base || '').replace(/\\/+$/, ''),\n";
$htmlContent .= "        routerId: " . $routerIdJs . ",\n";
$htmlContent .= "        hotspotLoginUrl: " . $hotspotLoginUrlJs . ",\n";
$htmlContent .= "        currency: " . json_encode($currency_code) . ",\n";
$htmlContent .= "        devicePolicy: 'agnostic'\n";
$htmlContent .= "      };\n";
// MikroTik substitutes these when serving from the router (not in admin Preview)
$htmlContent .= "      window.PAMNET_MK = {\n";
$htmlContent .= "        mac: '\$(mac)',\n";
$htmlContent .= "        ip: '\$(ip)',\n";
$htmlContent .= "        linkOrig: '\$(link-orig)',\n";
$htmlContent .= "        linkLogin: '\$(link-login-only)',\n";
$htmlContent .= "        chapId: '\$(chap-id)'\n";
$htmlContent .= "      };\n";
$htmlContent .= "      window.pamnetApi = function (type) {\n";
$htmlContent .= "        var b = (window.PAMNET_PORTAL && PAMNET_PORTAL.apiBase) ? PAMNET_PORTAL.apiBase : '';\n";
$htmlContent .= "        b = String(b || '').replace(/\\/+$/, '');\n";
$htmlContent .= "        // Use /?_route= (not /plugin/...) — /plugin/... returns Apache 404 on this host\n";
$htmlContent .= "        return b + '/?_route=plugin/CreateHotspotUser&type=' + encodeURIComponent(type || '');\n";
$htmlContent .= "      };\n";
$htmlContent .= "      window.pamnetXhr = function (url, opts) {\n";
$htmlContent .= "        opts = opts || {};\n";
$htmlContent .= "        return new Promise(function (resolve, reject) {\n";
$htmlContent .= "          try {\n";
$htmlContent .= "            var xhr = new XMLHttpRequest();\n";
$htmlContent .= "            xhr.open(String(opts.method || 'GET'), url, true);\n";
$htmlContent .= "            var headers = opts.headers || {};\n";
$htmlContent .= "            for (var hk in headers) {\n";
$htmlContent .= "              if (headers.hasOwnProperty(hk)) {\n";
$htmlContent .= "                try { xhr.setRequestHeader(hk, headers[hk]); } catch (eH) {}\n";
$htmlContent .= "              }\n";
$htmlContent .= "            }\n";
$htmlContent .= "            xhr.onreadystatechange = function () {\n";
$htmlContent .= "              if (xhr.readyState !== 4) { return; }\n";
$htmlContent .= "              var status = xhr.status || 0;\n";
$htmlContent .= "              var body = xhr.responseText || '';\n";
$htmlContent .= "              resolve({\n";
$htmlContent .= "                ok: status >= 200 && status < 300,\n";
$htmlContent .= "                status: status,\n";
$htmlContent .= "                text: function () { return Promise.resolve(body); },\n";
$htmlContent .= "                json: function () {\n";
$htmlContent .= "                  return new Promise(function (resJ, rejJ) {\n";
$htmlContent .= "                    try { resJ(JSON.parse(body || '{}')); } catch (eJ) { rejJ(eJ); }\n";
$htmlContent .= "                  });\n";
$htmlContent .= "                }\n";
$htmlContent .= "              });\n";
$htmlContent .= "            };\n";
$htmlContent .= "            xhr.onerror = function () { reject(new Error('Network error')); };\n";
$htmlContent .= "            xhr.send(opts.body != null ? opts.body : null);\n";
$htmlContent .= "          } catch (eX) { reject(eX); }\n";
$htmlContent .= "        });\n";
$htmlContent .= "      };\n";
$htmlContent .= "      window.pamnetFetch = function (type, options) {\n";
$htmlContent .= "        var opts = options || {};\n";
$htmlContent .= "        var url = pamnetApi(type);\n";
$htmlContent .= "        var run = function (u) {\n";
$htmlContent .= "          if (typeof window.fetch === 'function') {\n";
$htmlContent .= "            return fetch(u, opts).then(function (r) {\n";
$htmlContent .= "              return {\n";
$htmlContent .= "                ok: r.ok,\n";
$htmlContent .= "                status: r.status,\n";
$htmlContent .= "                text: function () { return r.text(); },\n";
$htmlContent .= "                json: function () { return r.json(); }\n";
$htmlContent .= "              };\n";
$htmlContent .= "            });\n";
$htmlContent .= "          }\n";
$htmlContent .= "          return pamnetXhr(u, opts);\n";
$htmlContent .= "        };\n";
$htmlContent .= "        return run(url).then(function (r) {\n";
$htmlContent .= "          if (r.status !== 404) { return r; }\n";
$htmlContent .= "          var b = (window.PAMNET_PORTAL && PAMNET_PORTAL.apiBase) ? String(PAMNET_PORTAL.apiBase).replace(/\\/+$/, '') : '';\n";
$htmlContent .= "          var alt = b + '/index.php?_route=plugin/CreateHotspotUser&type=' + encodeURIComponent(type || '');\n";
$htmlContent .= "          return run(alt);\n";
$htmlContent .= "        });\n";
$htmlContent .= "      };\n";
$htmlContent .= "      // Soft SweetAlert fallback if CDN is blocked on another host\n";
$htmlContent .= "      window.ensureSwal = function () {\n";
$htmlContent .= "        if (typeof window.Swal !== 'undefined') { return window.Swal; }\n";
$htmlContent .= "        window.Swal = {\n";
$htmlContent .= "          fire: function (opts) {\n";
$htmlContent .= "            opts = opts || {};\n";
$htmlContent .= "            var parts = [];\n";
$htmlContent .= "            if (opts.title) { parts.push(String(opts.title)); }\n";
$htmlContent .= "            if (opts.text) { parts.push(String(opts.text)); }\n";
$htmlContent .= "            else if (opts.html) { parts.push(String(opts.html).replace(/<[^>]+>/g, ' ')); }\n";
$htmlContent .= "            var msg = parts.join('\\n');\n";
$htmlContent .= "            if (opts.input) {\n";
$htmlContent .= "              var v = window.prompt(msg || opts.inputPlaceholder || 'Enter value', '');\n";
$htmlContent .= "              return Promise.resolve({ isConfirmed: v !== null, value: v });\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (msg) { window.alert(msg); }\n";
$htmlContent .= "            return Promise.resolve({ isConfirmed: true, value: true });\n";
$htmlContent .= "          },\n";
$htmlContent .= "          close: function () {},\n";
$htmlContent .= "          isVisible: function () { return false; },\n";
$htmlContent .= "          showLoading: function () {}\n";
$htmlContent .= "        };\n";
$htmlContent .= "        return window.Swal;\n";
$htmlContent .= "      };\n";
$htmlContent .= "    })();\n";
$htmlContent .= "    </script>\n";
$htmlContent .= "    <script src=\"https://cdn.tailwindcss.com\" onerror=\"this.remove()\"></script>\n";
$htmlContent .= "    <script src=\"https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js\" onerror=\"this.remove()\"></script>\n";
$htmlContent .= "    <script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11\" onerror=\"window.ensureSwal && ensureSwal()\"></script>\n";
$htmlContent .= "    <script>try{ensureSwal();}catch(e){}</script>\n";
$htmlContent .= "    <style>\n";
$htmlContent .= "        /* Fallback layout if Tailwind CDN blocked on another host */\n";
$htmlContent .= "        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif; background:#111827; color:#111827; }\n";
$htmlContent .= "        .mx-auto { margin-left:auto; margin-right:auto; }\n";
$htmlContent .= "        .max-w-screen-xl { max-width:1280px; }\n";
$htmlContent .= "        .max-w-md { max-width:28rem; }\n";
$htmlContent .= "        .px-2 { padding-left:.5rem; padding-right:.5rem; }\n";
$htmlContent .= "        .p-3 { padding:.75rem; }\n";
$htmlContent .= "        .py-8 { padding-top:2rem; padding-bottom:2rem; }\n";
$htmlContent .= "        .mb-3 { margin-bottom:.75rem; }\n";
$htmlContent .= "        .mb-4 { margin-bottom:1rem; }\n";
$htmlContent .= "        .mb-6 { margin-bottom:1.5rem; }\n";
$htmlContent .= "        .mt-4 { margin-top:1rem; }\n";
$htmlContent .= "        .text-center { text-align:center; }\n";
$htmlContent .= "        .text-white { color:#fff; }\n";
$htmlContent .= "        .text-gray-800 { color:#1f2937; }\n";
$htmlContent .= "        .text-gray-700 { color:#374151; }\n";
$htmlContent .= "        .bg-green-50 { background:#f0fdf4; }\n";
$htmlContent .= "        .bg-gray-900 { background:#111827; }\n";
$htmlContent .= "        .rounded-lg { border-radius:.5rem; }\n";
$htmlContent .= "        .shadow-md { box-shadow:0 4px 6px -1px rgba(0,0,0,.1); }\n";
$htmlContent .= "        .font-bold { font-weight:700; }\n";
$htmlContent .= "        .grid { display:grid; gap:1rem; }\n";
$htmlContent .= "        .plan-button, button { cursor:pointer; }\n";
$htmlContent .= "        a { color:#2563eb; }\n";
$htmlContent .= "        /* Enhanced Device Compatibility & Cross-browser fixes */\n";
$htmlContent .= "        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }\n";
$htmlContent .= "        body { margin: 0; padding: 0; overflow-x: hidden; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }\n";
$htmlContent .= "        input, button { -webkit-appearance: none; -moz-appearance: none; appearance: none; }\n";
$htmlContent .= "        button { touch-action: manipulation; }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Enhanced SweetAlert Custom Styling - Light Theme */\n";
$htmlContent .= "        .swal2-popup-custom {\n";
$htmlContent .= "            border-radius: 12px !important;\n";
$htmlContent .= "            padding: 24px !important;\n";
$htmlContent .= "            backdrop-filter: blur(10px) !important;\n";
$htmlContent .= "            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12) !important;\n";
$htmlContent .= "            border: 1px solid rgba(0, 0, 0, 0.08) !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Mobile-specific SweetAlert styling for popups */\n";
$htmlContent .= "        .swal2-popup-mobile {\n";
$htmlContent .= "            width: 90% !important;\n";
$htmlContent .= "            max-width: 400px !important;\n";
$htmlContent .= "            min-width: 280px !important;\n";
$htmlContent .= "            margin: 0 auto !important;\n";
$htmlContent .= "            border-radius: 12px !important;\n";
$htmlContent .= "            padding: 1.5em !important;\n";
$htmlContent .= "            box-sizing: border-box !important;\n";
$htmlContent .= "            transform: none !important;\n";
$htmlContent .= "            position: relative !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .swal2-title-mobile {\n";
$htmlContent .= "            font-size: 1.2em !important;\n";
$htmlContent .= "            margin-bottom: 1em !important;\n";
$htmlContent .= "            line-height: 1.3 !important;\n";
$htmlContent .= "            word-wrap: break-word !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .swal2-html-mobile {\n";
$htmlContent .= "            margin: 1em 0 !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .swal2-html-mobile .swal2-input {\n";
$htmlContent .= "            width: 100% !important;\n";
$htmlContent .= "            max-width: 300px !important;\n";
$htmlContent .= "            margin: 0 auto !important;\n";
$htmlContent .= "            padding: 12px !important;\n";
$htmlContent .= "            font-size: 16px !important;\n";
$htmlContent .= "            border: 2px solid #e2e8f0 !important;\n";
$htmlContent .= "            border-radius: 8px !important;\n";
$htmlContent .= "            box-sizing: border-box !important;\n";
$htmlContent .= "            -webkit-appearance: none !important;\n";
$htmlContent .= "            -moz-appearance: none !important;\n";
$htmlContent .= "            appearance: none !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .swal2-confirm-mobile, .swal2-cancel-mobile {\n";
$htmlContent .= "            padding: 10px 20px !important;\n";
$htmlContent .= "            margin: 0 5px !important;\n";
$htmlContent .= "            font-size: 14px !important;\n";
$htmlContent .= "            font-weight: 600 !important;\n";
$htmlContent .= "            border-radius: 6px !important;\n";
$htmlContent .= "            border: none !important;\n";
$htmlContent .= "            min-width: 80px !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        @media (max-width: 480px) {\n";
$htmlContent .= "            .swal2-popup-mobile {\n";
$htmlContent .= "                width: 95% !important;\n";
$htmlContent .= "                margin: 0 !important;\n";
$htmlContent .= "                padding: 1.2em !important;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            \n";
$htmlContent .= "            .swal2-title-mobile {\n";
$htmlContent .= "                font-size: 1.1em !important;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            \n";
$htmlContent .= "            .swal2-html-mobile .swal2-input {\n";
$htmlContent .= "                font-size: 16px !important;\n";
$htmlContent .= "                padding: 10px !important;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            \n";
$htmlContent .= "            .swal2-confirm-mobile, .swal2-cancel-mobile {\n";
$htmlContent .= "                padding: 8px 16px !important;\n";
$htmlContent .= "                font-size: 13px !important;\n";
$htmlContent .= "                min-width: 70px !important;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Performance optimizations */\n";
$htmlContent .= "        .fade-in { animation: fadeIn 0.3s ease-in; }\n";
$htmlContent .= "        @-webkit-keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }\n";
$htmlContent .= "        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Cards Grid System - Enhanced Layout */\n";
$htmlContent .= "        #cards-container { \n";
$htmlContent .= "            display: grid; \n";
$htmlContent .= "            grid-template-columns: repeat(2, 1fr); \n";
$htmlContent .= "            gap: 0.75rem; \n";
$htmlContent .= "            padding: 0.5rem;\n";
$htmlContent .= "            max-width: 100%;\n";
$htmlContent .= "            margin: 0 auto;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Small Tablets and Landscape Mobile */\n";
$htmlContent .= "        @media (min-width: 480px) {\n";
$htmlContent .= "            #cards-container { \n";
$htmlContent .= "                grid-template-columns: repeat(3, 1fr); \n";
$htmlContent .= "                gap: 1rem; \n";
$htmlContent .= "                max-width: 720px;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Large Tablets and Desktop */\n";
$htmlContent .= "        @media (min-width: 768px) {\n";
$htmlContent .= "            #cards-container { \n";
$htmlContent .= "                grid-template-columns: repeat(4, 1fr); \n";
$htmlContent .= "                gap: 1.25rem; \n";
$htmlContent .= "                max-width: 1200px;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Card Base Styles */\n";
$htmlContent .= "        .plan-card {\n";
$htmlContent .= "            width: 100%;\n";
$htmlContent .= "            min-height: 180px;\n";
$htmlContent .= "            display: flex;\n";
$htmlContent .= "            flex-direction: column;\n";
$htmlContent .= "            transition: all 0.3s ease;\n";
$htmlContent .= "            transform-origin: center;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card:hover {\n";
$htmlContent .= "            transform: translateY(-4px);\n";
$htmlContent .= "            box-shadow: 0 12px 30px rgba(0,0,0,0.15);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card:active {\n";
$htmlContent .= "            transform: translateY(-1px);\n";
$htmlContent .= "            box-shadow: 0 6px 20px rgba(0,0,0,0.1);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Enhanced card interactions */\n";
$htmlContent .= "        .plan-card {\n";
$htmlContent .= "            cursor: pointer;\n";
$htmlContent .= "            user-select: none;\n";
$htmlContent .= "            -webkit-user-select: none;\n";
$htmlContent .= "            -moz-user-select: none;\n";
$htmlContent .= "            -ms-user-select: none;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card:focus {\n";
$htmlContent .= "            outline: 2px solid #3b82f6;\n";
$htmlContent .= "            outline-offset: 2px;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Offer Plan Styles - Orange/Amber Theme for Blue Background */\n";
$htmlContent .= "        .plan-card.offer-plan {\n";
$htmlContent .= "            background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);\n";
$htmlContent .= "            border: 2px solid #f97316;\n";
$htmlContent .= "            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25);\n";
$htmlContent .= "            position: relative;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card.offer-plan:hover {\n";
$htmlContent .= "            transform: translateY(-6px);\n";
$htmlContent .= "            box-shadow: 0 16px 40px rgba(249, 115, 22, 0.35);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card.offer-plan::before {\n";
$htmlContent .= "            content: 'SPECIAL OFFER';\n";
$htmlContent .= "            position: absolute;\n";
$htmlContent .= "            top: -2px;\n";
$htmlContent .= "            right: -2px;\n";
$htmlContent .= "            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);\n";
$htmlContent .= "            color: white;\n";
$htmlContent .= "            padding: 4px 8px;\n";
$htmlContent .= "            font-size: 0.6rem;\n";
$htmlContent .= "            font-weight: bold;\n";
$htmlContent .= "            border-radius: 0 8px 0 8px;\n";
$htmlContent .= "            z-index: 10;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card.offer-plan .bg-green-500 {\n";
$htmlContent .= "            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card.offer-plan .plan-title {\n";
$htmlContent .= "            color: white !important;\n";
$htmlContent .= "            font-weight: 700;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card.offer-plan .plan-price {\n";
$htmlContent .= "            color: #c2410c;\n";
$htmlContent .= "            font-weight: 800;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        .plan-card.offer-plan .plan-currency {\n";
$htmlContent .= "            color: #9a3412;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Mobile responsive for offer badge */\n";
$htmlContent .= "        @media (max-width: 480px) {\n";
$htmlContent .= "            .plan-card.offer-plan::before {\n";
$htmlContent .= "                content: 'OFFER';\n";
$htmlContent .= "                font-size: 0.55rem;\n";
$htmlContent .= "                padding: 3px 6px;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }\n";
$htmlContent .= "        \n";
$htmlContent .= "        /* Responsive Text Sizing - Enhanced for better visibility on small screens */\n";
$htmlContent .= "        .plan-title { font-size: clamp(0.8rem, 2.2vw, 1.0rem); font-weight: 600; }\n";
$htmlContent .= "        .plan-price { font-size: clamp(1.3rem, 4.5vw, 1.9rem); font-weight: 800; }\n";
$htmlContent .= "        .plan-currency { font-size: clamp(0.75rem, 2.2vw, 0.95rem); font-weight: 500; }\n";
$htmlContent .= "        .plan-validity { font-size: clamp(0.7rem, 2.2vw, 0.85rem); font-weight: 500; }\n";
$htmlContent .= "        .plan-button { font-size: clamp(0.75rem, 2.2vw, 0.9rem); font-weight: 600; }
        
        /* Additional mobile-specific enhancements for very small screens */
        @media (max-width: 480px) {
            .plan-card {
                min-height: 180px;
                padding: 0.5rem;
            }
            .plan-title {
                font-size: 0.95rem !important;
                line-height: 1.3;
                padding: 0.5rem;
            }
            .plan-price {
                font-size: 1.6rem !important;
                margin-bottom: 0.5rem;
            }
            .plan-currency {
                font-size: 0.9rem !important;
            }
            .plan-validity {
                font-size: 0.85rem !important;
                margin-bottom: 1rem;
            }
            .plan-button {
                font-size: 0.9rem !important;
                padding: 0.75rem 1rem;
                min-height: 44px; /* Better touch target */
            }
        }
        
        /* Extra small devices (iPhone SE, very small Android) */
        @media (max-width: 375px) {
            .plan-title {
                font-size: 0.9rem !important;
            }
            .plan-price {
                font-size: 1.5rem !important;
            }
            .plan-currency {
                font-size: 0.85rem !important;
            }
            .plan-validity {
                font-size: 0.8rem !important;
            }
            .plan-button {
                font-size: 0.85rem !important;
            }
        }

        /* TV / large screen — remote-friendly (all brands, device-agnostic) */
        @media (min-width: 960px) {
            #cards-container {
                grid-template-columns: repeat(3, 1fr);
                gap: 1.25rem;
                max-width: 1400px;
            }
            .plan-card {
                min-height: 220px;
            }
            .plan-button, #submitBtn {
                min-height: 52px;
                font-size: 1.05rem !important;
            }
            .input-field, #usernameInput {
                min-height: 48px;
                font-size: 1.05rem;
            }
        }
        @media (min-width: 1280px) {
            #cards-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        html.pamnet-tv-hint .plan-card:focus,
        body.pamnet-universal .plan-card:focus,
        .plan-card.pamnet-focused {
            outline: 3px solid #2563eb;
            outline-offset: 4px;
        }
        html.pamnet-tv-hint #submitBtn:focus,
        body.pamnet-universal #submitBtn:focus {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }\n";
$htmlContent .= "    </style>\n";
$htmlContent .= "    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css\" onerror=\"this.remove()\">\n";
$htmlContent .= "    <link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/glider-js@1.7.7/glider.min.css\" onerror=\"this.remove()\" />\n";
$htmlContent .= "    <script src=\"https://cdn.jsdelivr.net/npm/glider-js@1.7.7/glider.min.js\" onerror=\"this.remove()\"></script>\n";
$htmlContent .= "    <link rel=\"preconnect\" href=\"https://cdn.jsdelivr.net\">\n";
$htmlContent .= "    <link rel=\"preconnect\" href=\"https://cdnjs.cloudflare.com\" crossorigin>\n";
$htmlContent .= "    <!-- System fonts only (no third-party font host required) -->\n";
$htmlContent .= "</head>\n";
$htmlContent .= "<body class=\"font-sans antialiased text-gray-900 bg-gray-900\">\n";
$htmlContent .= "<noscript>\n";
$htmlContent .= "  <div style=\"background:#1f2937;color:#fff;padding:16px;text-align:center;font-family:system-ui,sans-serif;\">\n";
$htmlContent .= "    <p><strong>JavaScript is off.</strong> Use the Sign In form below with your Hotspot code, or open this page on your phone to pay via M-Pesa.</p>\n";
$htmlContent .= "    <p style=\"margin-top:8px;font-size:0.95rem;\">Portal: <a href=\"login.html\" style=\"color:#93c5fd;\">login.html</a></p>\n";
$htmlContent .= "  </div>\n";
$htmlContent .= "</noscript>\n";
// Do NOT auto-redirect to $(link-redirect) on login page load.
// Captive-portal probes (Android/iOS) open login with link-redirect set to
// connectivitycheck.gstatic.com / captive.apple.com — redirecting there causes
// a login↔probe loop and "Connected without Internet".
// After successful auth, alogin/status handles the destination.
$htmlContent .= "<script>(function(){try{var u='\$(link-redirect)';if(u&&u.indexOf('\$(')===-1&&u.length>3){window.PAMNET_LINK_REDIRECT=u;}}catch(e){}})();</script>\n";
$htmlContent .= "<script>(function(){try{var n=parseInt(sessionStorage.getItem('pamnet_login_fails')||'0',10)||0;var err='\$(error)';if(err&&err.indexOf('\$(')===-1&&err.length){sessionStorage.setItem('pamnet_login_fails',String(n+1));}}catch(e){}})();</script>\n";
$htmlContent .= "<div id=\"wifiConnectingOverlay\" style=\"display:none;position:fixed;inset:0;z-index:99999;background:rgba(17,24,39,0.92);color:#fff;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:24px;\">\n";
$htmlContent .= "  <div style=\"font-size:1.35rem;font-weight:700;margin-bottom:8px;\" id=\"wifiConnectingTitle\">Connecting to Wi-Fi…</div>\n";
$htmlContent .= "  <div style=\"opacity:0.85;\" id=\"wifiConnectingText\">Please wait, do not close this page.</div>\n";
$htmlContent .= "</div>\n";

// Enhanced header section with modern design and device compatibility
$htmlContent .= "    <!-- Main Content -->\n";
$htmlContent .= "    <div class=\"mx-auto max-w-screen-xl px-2 sm:px-4 md:px-6\">\n";
$htmlContent .= "        <div class=\"relative mx-auto mt-4 flex max-w-md sm:max-w-lg flex-1 items-center justify-center overflow-hidden rounded-lg bg-green-50 shadow-md ring-1 ring-green-100\">\n";
$htmlContent .= "            <!-- Text Content -->\n";
$htmlContent .= "            <div class=\"relative w-full p-3 sm:p-5\">\n";
$htmlContent .= "                <!-- Title -->\n";
$htmlContent .= "                <div class=\"mb-3 text-center\">\n";
$htmlContent .= "                    <p class=\"text-lg sm:text-xl md:text-2xl font-bold text-gray-800 sm:text-2xl\">" . htmlspecialchars($hotspotTitle) . "</p>\n";
$htmlContent .= "                    <div class=\"mx-auto mt-1 h-0.5 w-12 sm:w-16 bg-green-400 rounded-full\"></div>\n";
$htmlContent .= "                </div>\n";
$htmlContent .= "                <!-- How to Purchase -->\n";
$htmlContent .= "                <div class=\"mb-4\">\n";
$htmlContent .= "                    <h3 class=\"text-base sm:text-md font-medium text-gray-700 mb-2 flex items-center\">\n";
$htmlContent .= "                        <svg class=\"w-4 h-4 mr-1.5 text-green-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">\n";
$htmlContent .= "                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2\"></path>\n";
$htmlContent .= "                        </svg>\n";
$htmlContent .= "                        How to Purchase:\n";
$htmlContent .= "                    </h3>\n";
$htmlContent .= "                    <ol class=\"space-y-1.5 text-sm sm:text-base text-gray-700 pl-1\">\n";
$htmlContent .= "                        <li class=\"flex items-start\">\n";
$htmlContent .= "                            <span class=\"flex items-center justify-center w-5 h-5 bg-green-100 text-green-800 rounded-full mr-2 flex-shrink-0 text-xs font-medium\">1</span>\n";
if (!empty($faq1)) {
    $htmlContent .= "                            <span>" . htmlspecialchars($faq1) . "</span>\n";
} else {
    $htmlContent .= "                            <span>Click on your preferred package Buy</span>\n";
}
$htmlContent .= "                        </li>\n";
$htmlContent .= "                        <li class=\"flex items-start\">\n";
$htmlContent .= "                            <span class=\"flex items-center justify-center w-5 h-5 bg-green-100 text-green-800 rounded-full mr-2 flex-shrink-0 text-xs font-medium\">2</span>\n";
if (!empty($faq2)) {
    $htmlContent .= "                            <span>" . htmlspecialchars($faq2) . "</span>\n";
} else {
    $htmlContent .= "                            <span>Enter Your Mpesa No.</span>\n";
}
$htmlContent .= "                        </li>\n";
$htmlContent .= "                        <li class=\"flex items-start\">\n";
$htmlContent .= "                            <span class=\"flex items-center justify-center w-5 h-5 bg-green-100 text-green-800 rounded-full mr-2 flex-shrink-0 text-xs font-medium\">3</span>\n";
if (!empty($faq3)) {
    $htmlContent .= "                            <span>" . htmlspecialchars($faq3) . "</span>\n";
} else {
    $htmlContent .= "                            <span>Enter pin and wait for 30sec to be connected</span>\n";
}
$htmlContent .= "                        </li>\n";
$htmlContent .= "                    </ol>\n";
$htmlContent .= "                </div>\n";

// Dynamic Customer Care Section
$htmlContent .= "                <!-- Dynamic Customer Care -->\n";
$htmlContent .= "                <div id=\"customer-care-section\" class=\"text-center\" style=\"display: none;\">\n";
$htmlContent .= "                    <p class=\"text-sm font-medium text-gray-700 inline-flex items-center bg-green-100/80 px-3 py-1.5 rounded-md\">\n";
$htmlContent .= "                        <svg class=\"w-4 h-4 mr-1.5 text-green-600\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">\n";
$htmlContent .= "                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z\"></path>\n";
$htmlContent .= "                        </svg>\n";
$htmlContent .= "                        CUSTOMER CARE: \n";
$htmlContent .= "                        <a id=\"phone-link\" href=\"#\" class=\"text-blue-600 underline hover:text-blue-800 transition\">\n";
$htmlContent .= "                            <span id=\"phone-number\" class=\"text-green-700 ml-1\"></span>\n";
$htmlContent .= "                        </a>\n";
$htmlContent .= "                    </p>\n";
$htmlContent .= "                </div>\n";
$htmlContent .= "            </div>\n";
$htmlContent .= "        </div>\n";
$htmlContent .= "    </div>\n";


// Add simple popup redemption buttons - always side by side with text truncation
$htmlContent .= "    <div class=\"text-center py-8\">\n";
$htmlContent .= "        <h3 class=\"text-xl font-bold text-white mb-6\">Already Have a Code?</h3>\n";
$htmlContent .= "        <div class=\"flex gap-2 sm:gap-4 max-w-lg mx-auto px-2 sm:px-4\">\n";
$htmlContent .= "            <button onclick=\"showVoucherPopup()\" class=\"flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-2 sm:px-6 rounded-lg transition duration-200 shadow-lg min-w-0\">\n";
$htmlContent .= "                <svg class=\"w-4 h-4 sm:w-5 sm:h-5 inline-block mr-1 sm:mr-2 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">\n";
$htmlContent .= "                    <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a1 1 0 001 1h1a1 1 0 001-1V7a2 2 0 00-2-2H5zM5 14a2 2 0 00-2 2v3a1 1 0 001 1h1a1 1 0 001-1v-3a2 2 0 00-2-2H5z\"></path>\n";
$htmlContent .= "                </svg>\n";
$htmlContent .= "                <span class=\"truncate text-xs sm:text-base\">Redeem Voucher</span>\n";
$htmlContent .= "            </button>\n";
$htmlContent .= "            <button onclick=\"showMpesaPopup()\" class=\"flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-2 sm:px-6 rounded-lg transition duration-200 shadow-lg min-w-0\">\n";
$htmlContent .= "                <svg class=\"w-4 h-4 sm:w-5 sm:h-5 inline-block mr-1 sm:mr-2 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">\n";
$htmlContent .= "                    <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z\"></path>\n";
$htmlContent .= "                </svg>\n";
$htmlContent .= "                <span class=\"truncate text-xs sm:text-base\">M-Pesa Code</span>\n";
$htmlContent .= "            </button>\n";
$htmlContent .= "        </div>\n";
$htmlContent .= "    </div>\n\n";


// Plans container section with modern design
$htmlContent .= "    <div class=\"py-4 sm:py-6 lg:py-8\">\n";
$htmlContent .= "        <div class=\"mx-auto max-w-screen-xl px-4 md:px-6\">\n";
$htmlContent .= "            <div class=\"text-center mb-6\">\n";
$htmlContent .= "                <h2 class=\"text-2xl font-bold text-white mb-2\">Available Internet Plans</h2>\n";
$htmlContent .= "            </div>\n";
$htmlContent .= "            <div id=\"cards-container\">\n";
$htmlContent .= "                <!-- Cards will be populated here -->\n";
$htmlContent .= "            </div>\n";
$htmlContent .= "        </div>\n";
$htmlContent .= "    </div>\n";


// Modern Login Form Design
$htmlContent .= "    <div id=\"signInSection\" class=\"max-w-md mx-auto bg-white rounded-2xl overflow-hidden shadow-xl md:max-w-lg form-container my-8\">\n";
$htmlContent .= "        <div class=\"md:flex\">\n";
$htmlContent .= "            <div class=\"w-full p-6 md:p-8\">\n";
$htmlContent .= "                <div class=\"text-center mb-6\">\n";
$htmlContent .= "                    <h3 class=\"text-2xl sm:text-3xl font-bold text-gray-900 bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent\">Already Have an Active Package?</h3>\n";
$htmlContent .= "                    <p class=\"mt-2 text-gray-500\">Sign in for access</p>\n";
$htmlContent .= "                </div>\n";
$htmlContent .= "                <form id=\"loginForm\" class=\"form\" name=\"login\" action=\"\$(link-login-only)\" method=\"post\" \$(if chap-id)onSubmit=\"return doLogin()\" \$(endif)>\n";
$htmlContent .= "                    <input type=\"hidden\" name=\"dst\" value=\"\$(link-orig)\" />\n";
$htmlContent .= "                    <input type=\"hidden\" name=\"popup\" value=\"true\" />\n";
$htmlContent .= "                    <div class=\"mb-4\">\n";
$htmlContent .= "                        <label class=\"block text-gray-700 text-sm font-bold mb-2\" for=\"username\">Username</label>\n";
$htmlContent .= "                        <input id=\"usernameInput\" class=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline\" name=\"username\" type=\"text\" value=\"\" placeholder=\"Username\">\n";
$htmlContent .= "                    </div>\n";
$htmlContent .= "                    <div class=\"mb-6\" style=\"display: none;\">\n";
$htmlContent .= "                        <label class=\"block text-gray-700 text-sm font-bold mb-2\" for=\"password\">Password</label>\n";
$htmlContent .= "                        <input id=\"passwordInput\" class=\"shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline\" name=\"password\" type=\"password\" value=\"1234\" placeholder=\"******************\">\n";
$htmlContent .= "                    </div>\n";
$htmlContent .= "                    <div class=\"flex items-center justify-center\">\n";
$htmlContent .= "                        <button id=\"submitBtn\" class=\"bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline\" type=\"button\">\n";
$htmlContent .= "                            Click Here To Connect\n";
$htmlContent .= "                        </button>\n";
$htmlContent .= "                    </div>\n";
$htmlContent .= "                </form>\n";
$htmlContent .= "                \$(if chap-id)\n";
$htmlContent .= "                <form name=\"sendin\" action=\"\$(link-login-only)\" method=\"post\" style=\"display:none\">\n";
$htmlContent .= "                    <input type=\"hidden\" name=\"username\" />\n";
$htmlContent .= "                    <input type=\"hidden\" name=\"password\" />\n";
$htmlContent .= "                    <input type=\"hidden\" name=\"dst\" value=\"\$(link-orig)\" />\n";
$htmlContent .= "                    <input type=\"hidden\" name=\"popup\" value=\"true\" />\n";
$htmlContent .= "                </form>\n";
$htmlContent .= "                <script type=\"text/javascript\">\n";
$htmlContent .= "                (function(){try{var p='\$(link-scripts)/md5.js';if(p.indexOf('\$(')===-1){var s=document.createElement('script');s.src=p;document.head.appendChild(s);}}catch(e){}})();\n";
if ($md5Inline !== '') {
    $htmlContent .= $md5Inline . "\n";
}
$htmlContent .= "                function doLogin() {\n";
$htmlContent .= "                    try {\n";
$htmlContent .= "                        if (typeof hexMD5 !== 'function' || !document.sendin || !document.login) { return true; }\n";
$htmlContent .= "                        document.sendin.username.value = document.login.username.value;\n";
$htmlContent .= "                        document.sendin.password.value = hexMD5('\$(chap-id)' + document.login.password.value + '\$(chap-challenge)');\n";
$htmlContent .= "                        document.sendin.submit();\n";
$htmlContent .= "                        return false;\n";
$htmlContent .= "                    } catch (e) { return true; }\n";
$htmlContent .= "                }\n";
$htmlContent .= "                </script>\n";
$htmlContent .= "                \$(endif)\n";
$htmlContent .= "            </div>\n";
$htmlContent .= "        </div>\n";
$htmlContent .= "    </div>\n";

// Hidden form elements for popup redemption
$htmlContent .= "    <div style=\"display: none;\">\n";
$htmlContent .= "        <input type=\"text\" id=\"voucher_code\" />\n";
$htmlContent .= "        <input type=\"text\" id=\"mpesa_code\" />\n";
$htmlContent .= "    </div>\n\n";

// Modern Footer Section
$htmlContent .= "    <div class=\"mx-auto max-w-screen-2xl px-4 md:px-8\">\n";
$htmlContent .= "        <div class=\"mx-auto max-w-lg\">\n";
$htmlContent .= "            <div class=\"border-t border-gray-700/50 py-4\">\n";
$htmlContent .= "                <p class=\"text-xs text-center font-medium text-gray-400\">\n";
$htmlContent .= "                    &copy; <span id=\"currentYear\"></span> All rights reserved. \n";
$htmlContent .= "                    <span class=\"text-blue-400\"> . $company .  </span>\n";
$htmlContent .= "                </p>\n";
$htmlContent .= "            </div>\n";
$htmlContent .= "        </div>\n";
$htmlContent .= "    </div>\n";
$htmlContent .= "</body>\n";

// Add current year script
$htmlContent .= "<script>\n";
$htmlContent .= "document.addEventListener('DOMContentLoaded', function() {\n";
$htmlContent .= "    var currentYearElement = document.getElementById('currentYear');\n";
$htmlContent .= "    if (currentYearElement) {\n";
$htmlContent .= "        currentYearElement.textContent = new Date().getFullYear();\n";
$htmlContent .= "    }\n";
$htmlContent .= "});\n";
$htmlContent .= "</script>\n";


// Enhanced auto-login with exact logic from reference
$htmlContent .= "<script>\n";
$htmlContent .= "    // Utility functions (defined first to avoid reference errors)\n";
$htmlContent .= "    function setCookie(name, value, days) {\n";
$htmlContent .= "        var expires = \"\";\n";
$htmlContent .= "        if (days) {\n";
$htmlContent .= "            var date = new Date();\n";
$htmlContent .= "            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));\n";
$htmlContent .= "            expires = \"; expires=\" + date.toUTCString();\n";
$htmlContent .= "        }\n";
$htmlContent .= "        document.cookie = name + \"=\" + (value || \"\") + expires + \"; path=/\";\n";
$htmlContent .= "        // Also store in localStorage as backup\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            localStorage.setItem(name, value);\n";
$htmlContent .= "        } catch (e) {\n";
$htmlContent .= "        }\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    function getCookie(name) {\n";
$htmlContent .= "        var nameEQ = name + \"=\";\n";
$htmlContent .= "        var ca = document.cookie.split(';');\n";
$htmlContent .= "        for (var i = 0; i < ca.length; i++) {\n";
$htmlContent .= "            var c = ca[i];\n";
$htmlContent .= "            while (c.charAt(0) == ' ') c = c.substring(1, c.length);\n";
$htmlContent .= "            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        // Try getting from localStorage if cookie not found\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var localValue = localStorage.getItem(name);\n";
$htmlContent .= "            if (localValue) {\n";
$htmlContent .= "                // Restore cookie from localStorage\n";
$htmlContent .= "                setCookie(name, localValue, 100);\n";
$htmlContent .= "                return localValue;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (e) {\n";
$htmlContent .= "        }\n";
$htmlContent .= "        return null;\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    function generateAccountNumber() {\n";
$htmlContent .= "        return '' + Math.floor(10000 + Math.random() * 90000); // Generate a random number between 10000 and 99999\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    function persistAccountNumber() {\n";
$htmlContent .= "        var accountNumber = getCookie('account_number');\n";
$htmlContent .= "        if (!accountNumber) {\n";
$htmlContent .= "            accountNumber = generateAccountNumber();\n";
$htmlContent .= "            setCookie('account_number', accountNumber, 365); // Store for 1 year\n";
$htmlContent .= "        }\n";
$htmlContent .= "        return accountNumber;\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    // Simple auto-trigger after 2 minutes (like login.html)\n";
$htmlContent .= "    let triggerCount = 0;\n";
$htmlContent .= "    const maxTriggers = 1; // Only trigger once\n\n";
$htmlContent .= "    function triggerReconnectButton() {\n";
$htmlContent .= "        if (triggerCount < maxTriggers) {\n";
$htmlContent .= "            triggerCount++;\n";
$htmlContent .= "            document.getElementById('submitBtn').click();\n";
$htmlContent .= "        } else {\n";
$htmlContent .= "            clearInterval(reconnectInterval);\n";
$htmlContent .= "        }\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    // Auto-trigger reconnect after 2 minutes\n";
$htmlContent .= "    const reconnectInterval = setInterval(triggerReconnectButton, 120000);\n\n";

$htmlContent .= "    // Simple popup functions for voucher and MPESA redemption (based on payments.html)\n";
$htmlContent .= "    function showVoucherPopup() {\n";
$htmlContent .= "        Swal.fire({\n";
$htmlContent .= "            title: 'Redeem Voucher',\n";
$htmlContent .= "            input: 'text',\n";
$htmlContent .= "            inputPlaceholder: 'Enter voucher code (alphanumeric only)',\n";
$htmlContent .= "            inputValidator: function(value) {\n";
$htmlContent .= "                if (!value) {\n";
$htmlContent .= "                    return 'You need to enter a voucher code!';\n";
$htmlContent .= "                }\n";
$htmlContent .= "                // Remove whitespace\n";
$htmlContent .= "                var cleanedValue = value.trim().replace(/\\s+/g, '');\n";
$htmlContent .= "                // Check minimum length\n";
$htmlContent .= "                if (cleanedValue.length < 2) {\n";
$htmlContent .= "                    return 'Voucher code must be at least 2 characters long';\n";
$htmlContent .= "                }\n";
$htmlContent .= "                // Check for invalid characters (only alphanumeric allowed)\n";
$htmlContent .= "                if (!/^[a-zA-Z0-9]+$/.test(cleanedValue)) {\n";
$htmlContent .= "                    return 'Voucher code can only contain letters and numbers (no special characters like #, @, etc.)';\n";
$htmlContent .= "                }\n";
$htmlContent .= "            },\n";
$htmlContent .= "            confirmButtonColor: '#3085d6',\n";
$htmlContent .= "            cancelButtonColor: '#d33',\n";
$htmlContent .= "            confirmButtonText: 'Redeem',\n";
$htmlContent .= "            showLoaderOnConfirm: true,\n";
$htmlContent .= "            preConfirm: (voucherCode) => {\n";
$htmlContent .= "                voucherCode = String(voucherCode || '').trim().replace(/\\s+/g, '');\n";
$htmlContent .= "                var accountNumber = getCookie('account_number');\n";
$htmlContent .= "                if (!accountNumber) {\n";
$htmlContent .= "                    accountNumber = generateAccountNumber();\n";
$htmlContent .= "                    setCookie('account_number', accountNumber, 365);\n";
$htmlContent .= "                }\n";
$htmlContent .= "                return pamnetFetch('redeem_voucher', {\n";
$htmlContent .= "                    method: 'POST',\n";
$htmlContent .= "                    headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "                    body: JSON.stringify({voucher_code: voucherCode, account_number: accountNumber, router_id: " . $routerId . "}),\n";
$htmlContent .= "                })\n";
$htmlContent .= "                .then(response => {\n";
$htmlContent .= "                    if (!response.ok) {\n";
$htmlContent .= "                        throw new Error('Server error: ' + response.status);\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                    return response.text().then(text => {\n";
$htmlContent .= "                        try {\n";
$htmlContent .= "                            return JSON.parse(text);\n";
$htmlContent .= "                        } catch (e) {\n";
$htmlContent .= "                            console.error('Invalid JSON response:', text);\n";
$htmlContent .= "                            throw new Error('Server returned invalid response. Please try again.');\n";
$htmlContent .= "                        }\n";
$htmlContent .= "                    });\n";
$htmlContent .= "                })\n";
$htmlContent .= "                .then(data => {\n";
$htmlContent .= "                    if (!data || data.status === 'error') throw new Error((data && data.message) || 'Voucher redemption failed');\n";
$htmlContent .= "                    return data;\n";
$htmlContent .= "                })\n";
$htmlContent .= "                .catch(error => {\n";
$htmlContent .= "                    console.error('Voucher error:', error);\n";
$htmlContent .= "                    throw error;\n";
$htmlContent .= "                });\n";
$htmlContent .= "            },\n";
$htmlContent .= "            allowOutsideClick: () => !Swal.isLoading()\n";
$htmlContent .= "        }).then((result) => {\n";
$htmlContent .= "            if (result.isConfirmed && result.value) {\n";
$htmlContent .= "                var username = (result.value.username || '').trim();\n";
$htmlContent .= "                var pass = result.value.tyhK || '1234';\n";
$htmlContent .= "                if (!username) {\n";
$htmlContent .= "                    Swal.fire({ icon: 'error', title: 'Redemption Failed', text: 'No username returned for this voucher.' });\n";
$htmlContent .= "                    return;\n";
$htmlContent .= "                }\n";
$htmlContent .= "                try { setCookie('account_number', username, 365); } catch (eC) {}\n";
$htmlContent .= "                var usernameInput = document.getElementById('usernameInput') || document.querySelector('input[name=\"username\"]');\n";
$htmlContent .= "                var passwordInput = document.getElementById('passwordInput');\n";
$htmlContent .= "                if (usernameInput) { usernameInput.value = username; }\n";
$htmlContent .= "                if (passwordInput) { passwordInput.value = pass; }\n";
$htmlContent .= "                try { if (typeof Swal !== 'undefined') { Swal.close(); } } catch (eS) {}\n";
$htmlContent .= "                showWifiConnecting('Voucher accepted', 'Connecting you to Wi-Fi now…');\n";
$htmlContent .= "                if (typeof waitReadyThenConnect === 'function') {\n";
$htmlContent .= "                    waitReadyThenConnect(username, pass, 8);\n";
$htmlContent .= "                } else if (typeof connectToWifiWithRetries === 'function') {\n";
$htmlContent .= "                    connectToWifiWithRetries(username, pass, 6);\n";
$htmlContent .= "                } else if (typeof submitHotspotLogin === 'function') {\n";
$htmlContent .= "                    submitHotspotLogin();\n";
$htmlContent .= "                }\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }).catch(error => {\n";
$htmlContent .= "            Swal.fire({\n";
$htmlContent .= "                icon: 'error',\n";
$htmlContent .= "                title: 'Redemption Failed',\n";
$htmlContent .= "                text: error.message || 'An error occurred. Please try again.',\n";
$htmlContent .= "                confirmButtonColor: '#d33'\n";
$htmlContent .= "            });\n";
$htmlContent .= "        });\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    function showMpesaPopup() {\n";
$htmlContent .= "        Swal.fire({\n";
$htmlContent .= "            title: 'Reconnect with MPesa',\n";
$htmlContent .= "            input: 'text',\n";
$htmlContent .= "            inputPlaceholder: 'Enter MPesa Transaction Code or Full Message',\n";
$htmlContent .= "            inputValidator: function(value) {\n";
$htmlContent .= "                if (!value) {\n";
$htmlContent .= "                    return 'You need to enter an MPesa code!';\n";
$htmlContent .= "                }\n";
$htmlContent .= "                // Accept any input - backend will extract first 10 characters\n";
$htmlContent .= "                if (value.length < 10) {\n";
$htmlContent .= "                    return 'MPesa code must be at least 10 characters';\n";
$htmlContent .= "                }\n";
$htmlContent .= "            },\n";
$htmlContent .= "            confirmButtonColor: '#3085d6',\n";
$htmlContent .= "            cancelButtonColor: '#d33',\n";
$htmlContent .= "            confirmButtonText: 'Reconnect',\n";
$htmlContent .= "            showLoaderOnConfirm: true,\n";
$htmlContent .= "            preConfirm: (mpesaCode) => {\n";
$htmlContent .= "                return pamnetFetch('redeem_mpesa_code', {\n";
$htmlContent .= "                    method: 'POST',\n";
$htmlContent .= "                    headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "                    body: JSON.stringify({mpesa_code: String(mpesaCode || '').trim()}),\n";
$htmlContent .= "                })\n";
$htmlContent .= "                .then(response => {\n";
$htmlContent .= "                    if (!response.ok) {\n";
$htmlContent .= "                        throw new Error('Network response was not ok');\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                    return response.json();\n";
$htmlContent .= "                })\n";
$htmlContent .= "                .then(data => {\n";
$htmlContent .= "                    if (!data || data.status !== 'success' || data.already_used) {\n";
$htmlContent .= "                        var msg = (data && data.message) || 'Invalid M-Pesa code';\n";
$htmlContent .= "                        if (data && data.already_used) {\n";
$htmlContent .= "                            msg = data.message || 'This M-Pesa code has already been used and cannot be used again.';\n";
$htmlContent .= "                        }\n";
$htmlContent .= "                        throw new Error(msg);\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                    return data;\n";
$htmlContent .= "                })\n";
$htmlContent .= "                .catch(error => {\n";
$htmlContent .= "                    console.error('M-Pesa validation error:', error);\n";
$htmlContent .= "                    throw error;\n";
$htmlContent .= "                });\n";
$htmlContent .= "            },\n";
$htmlContent .= "            allowOutsideClick: () => !Swal.isLoading()\n";
$htmlContent .= "        }).then((result) => {\n";
$htmlContent .= "            if (result.isConfirmed && result.value) {\n";
$htmlContent .= "                var data = result.value;\n";
$htmlContent .= "                var username = (data.username || '').trim();\n";
$htmlContent .= "                var pass = data.tyhK || '1234';\n";
$htmlContent .= "                if (!username) {\n";
$htmlContent .= "                    Swal.fire({ icon: 'error', title: 'Oops...', text: 'No account found for this M-Pesa code.' });\n";
$htmlContent .= "                    return;\n";
$htmlContent .= "                }\n";
$htmlContent .= "                try { setCookie('account_number', username, 365); } catch (eC) {}\n";
$htmlContent .= "                var usernameInput = document.getElementById('usernameInput') || document.querySelector('input[name=\"username\"]');\n";
$htmlContent .= "                var passwordInput = document.getElementById('passwordInput');\n";
$htmlContent .= "                if (usernameInput) { usernameInput.value = username; }\n";
$htmlContent .= "                if (passwordInput) { passwordInput.value = pass; }\n";
$htmlContent .= "                try { if (typeof Swal !== 'undefined') { Swal.close(); } } catch (eS) {}\n";
$htmlContent .= "                showWifiConnecting('Payment found', 'Connecting you to Wi-Fi now…');\n";
$htmlContent .= "                if (typeof waitReadyThenConnect === 'function') {\n";
$htmlContent .= "                    waitReadyThenConnect(username, pass, 8);\n";
$htmlContent .= "                } else if (typeof connectToWifiWithRetries === 'function') {\n";
$htmlContent .= "                    connectToWifiWithRetries(username, pass, 6);\n";
$htmlContent .= "                } else if (typeof submitHotspotLogin === 'function') {\n";
$htmlContent .= "                    submitHotspotLogin();\n";
$htmlContent .= "                }\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }).catch(error => {\n";
$htmlContent .= "            var errText = (error && error.message) ? error.message : 'An error occurred. Please try again.';\n";
$htmlContent .= "            var errTitle = (String(errText).toLowerCase().indexOf('already been used') !== -1) ? 'M-Pesa code already used' : 'Reconnect Failed';\n";
$htmlContent .= "            Swal.fire({\n";
$htmlContent .= "                icon: 'error',\n";
$htmlContent .= "                title: errTitle,\n";
$htmlContent .= "                text: errText,\n";
$htmlContent .= "                confirmButtonColor: '#d33'\n";
$htmlContent .= "            });\n";
$htmlContent .= "        });\n";
$htmlContent .= "    }\n\n";

$htmlContent .= "    // Tab switching functionality\n";
$htmlContent .= "    function switchTab(event, tabId) {\n";
$htmlContent .= "        event.preventDefault();\n";
$htmlContent .= "        \n";
$htmlContent .= "        // Remove active class from all tabs and content\n";
$htmlContent .= "        var tabLinks = document.querySelectorAll('.nav-link');\n";
$htmlContent .= "        var tabPanes = document.querySelectorAll('.tab-pane');\n";
$htmlContent .= "        \n";
$htmlContent .= "        tabLinks.forEach(function(link) {\n";
$htmlContent .= "            link.classList.remove('active');\n";
$htmlContent .= "        });\n";
$htmlContent .= "        \n";
$htmlContent .= "        tabPanes.forEach(function(pane) {\n";
$htmlContent .= "            pane.classList.remove('active');\n";
$htmlContent .= "        });\n";
$htmlContent .= "        \n";
$htmlContent .= "        // Add active class to clicked tab and corresponding content\n";
$htmlContent .= "        event.target.classList.add('active');\n";
$htmlContent .= "        document.getElementById(tabId).classList.add('active');\n";
$htmlContent .= "        \n";
$htmlContent .= "        // Clear any previous messages when switching tabs\n";
$htmlContent .= "        document.getElementById('message').innerHTML = '';\n";
$htmlContent .= "        document.getElementById('mpesaMessage').innerHTML = '';\n";
$htmlContent .= "    }\n\n";

$htmlContent .= "    // Show fullscreen connecting UI (avoids feeling stuck on sign-in)\n";
$htmlContent .= "    function showWifiConnecting(title, text) {\n";
$htmlContent .= "        try { if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) { Swal.close(); } } catch (e0) {}\n";
$htmlContent .= "        var ov = document.getElementById('wifiConnectingOverlay');\n";
$htmlContent .= "        var t = document.getElementById('wifiConnectingTitle');\n";
$htmlContent .= "        var x = document.getElementById('wifiConnectingText');\n";
$htmlContent .= "        if (t) t.textContent = title || 'Connecting to Wi-Fi…';\n";
$htmlContent .= "        if (x) x.textContent = text || 'Please wait, do not close this page.';\n";
$htmlContent .= "        if (ov) { ov.style.display = 'flex'; }\n";
$htmlContent .= "        var signIn = document.getElementById('signInSection');\n";
$htmlContent .= "        if (signIn) { signIn.style.display = 'none'; }\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function resolveHotspotAccount(preferred) {\n";
$htmlContent .= "        var fromInput = '';\n";
$htmlContent .= "        var uIn = document.getElementById('usernameInput');\n";
$htmlContent .= "        if (uIn && uIn.value) { fromInput = String(uIn.value).trim(); }\n";
$htmlContent .= "        var fromCookie = '';\n";
$htmlContent .= "        try { fromCookie = String(getCookie('account_number') || '').trim(); } catch (e) {}\n";
$htmlContent .= "        return String(preferred || fromInput || fromCookie || '').trim();\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function getMikrotikClientIdentity() {\n";
$htmlContent .= "        var mk = window.PAMNET_MK || {};\n";
$htmlContent .= "        var mac = String(mk.mac || '').trim();\n";
$htmlContent .= "        var ip = String(mk.ip || '').trim();\n";
$htmlContent .= "        // Unsubstituted MikroTik tokens → treat as unknown (do not block access)\n";
$htmlContent .= "        if (!mac || mac.indexOf('$(') !== -1) { mac = ''; }\n";
$htmlContent .= "        if (!ip || ip.indexOf('$(') !== -1) { ip = ''; }\n";
$htmlContent .= "        // Some captive browsers keep mac/ip in the query string only\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            if (!mac) { mac = String(pamnetQueryParam('mac') || pamnetQueryParam('mac-address') || '').trim(); }\n";
$htmlContent .= "            if (!ip) { ip = String(pamnetQueryParam('ip') || pamnetQueryParam('address') || '').trim(); }\n";
$htmlContent .= "        } catch (eQ) {}\n";
$htmlContent .= "        if (mac && mac.indexOf('$(') !== -1) { mac = ''; }\n";
$htmlContent .= "        if (ip && ip.indexOf('$(') !== -1) { ip = ''; }\n";
$htmlContent .= "        var classified = (typeof pamnetClassifyClient === 'function') ? pamnetClassifyClient() : 'UNKNOWN_DEVICE';\n";
$htmlContent .= "        window.__pamnetDeviceId = (mac || ip) ? 'KNOWN_LEASE' : classified;\n";
$htmlContent .= "        return { mac: mac, ip: ip, device: window.__pamnetDeviceId };\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function pamnetAccountPayload(account) {\n";
$htmlContent .= "        var id = getMikrotikClientIdentity();\n";
$htmlContent .= "        return {\n";
$htmlContent .= "            account_number: resolveHotspotAccount(account),\n";
$htmlContent .= "            mac: id.mac,\n";
$htmlContent .= "            mac_address: id.mac,\n";
$htmlContent .= "            ip: id.ip,\n";
$htmlContent .= "            device: id.device || 'UNKNOWN_DEVICE'\n";
$htmlContent .= "        };\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function finishWifiConnected(message) {\n";
$htmlContent .= "        try { sessionStorage.removeItem('pamnet_login_fails'); } catch (e0) {}\n";
$htmlContent .= "        try { localStorage.removeItem('pamnet_pending_login'); } catch (e1) {}\n";
$htmlContent .= "        window.__pamnetConnectStarted = true;\n";
$htmlContent .= "        window.__pamnetConnecting = true;\n";
$htmlContent .= "        window.__pamnetWifiOnline = true;\n";
$htmlContent .= "        showWifiConnecting(message || 'Connected', 'You are online. Opening internet…');\n";
$htmlContent .= "        // Always router /status — never external link-redirect (connectivitycheck / captive.apple)\n";
$htmlContent .= "        // which leaves a blank page that forces customers to press Back.\n";
$htmlContent .= "        var dst = 'http://10.0.0.1/status';\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var host = window.location.hostname || '';\n";
$htmlContent .= "            if (host && String(host).indexOf('$(') === -1) {\n";
$htmlContent .= "                dst = window.location.protocol + '//' + window.location.host + '/status';\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (eHost) {}\n";
$htmlContent .= "        setTimeout(function() {\n";
$htmlContent .= "            try { window.location.replace(dst); } catch (e2) { window.location.href = dst; }\n";
$htmlContent .= "        }, 120);\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function apiAutologinThenBrowse(account, password) {\n";
$htmlContent .= "        if (window.__pamnetWifiOnline) { return Promise.resolve(true); }\n";
$htmlContent .= "        var user = resolveHotspotAccount(account);\n";
$htmlContent .= "        var pass = password || '1234';\n";
$htmlContent .= "        var id = getMikrotikClientIdentity();\n";
$htmlContent .= "        if (!user) { return Promise.resolve(false); }\n";
$htmlContent .= "        // Always attempt server login when we have any lease hint (mac OR ip).\n";
$htmlContent .= "        // Server resolves the missing half from MikroTik host table.\n";
$htmlContent .= "        // If both missing (UNKNOWN_DEVICE), skip API and use browser PAP — still allowed.\n";
$htmlContent .= "        if (!id.mac && !id.ip) {\n";
$htmlContent .= "            try { console.log('pamnet: no lease id (' + (id.device || 'UNKNOWN') + ') — browser Hotspot login'); } catch (eL) {}\n";
$htmlContent .= "            return Promise.resolve(false);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        showWifiConnecting('Connecting…', 'Authorizing your device on Wi-Fi…');\n";
$htmlContent .= "        return pamnetFetch('autologin', {\n";
$htmlContent .= "            method: 'POST',\n";
$htmlContent .= "            headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "            body: JSON.stringify({account_number: user, mac: id.mac, ip: id.ip, password: pass, device: id.device})\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .then(function(r) { return r.json(); })\n";
$htmlContent .= "        .then(function(data) {\n";
$htmlContent .= "            if (data && data.logged_in === true) {\n";
$htmlContent .= "                return true;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            try {\n";
$htmlContent .= "                console.log('pamnet: autologin not online', (data && data.message) || '', '→ PAP fallback');\n";
$htmlContent .= "            } catch (eM) {}\n";
$htmlContent .= "            return false;\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .catch(function(err) {\n";
$htmlContent .= "            try { console.log('pamnet: autologin error', err); } catch (eC) {}\n";
$htmlContent .= "            return false;\n";
$htmlContent .= "        });\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function isMikroTikVar(v) {\n";
$htmlContent .= "        v = String(v || '');\n";
$htmlContent .= "        // Unsubstituted MikroTik tokens only (do not match real URLs)\n";
$htmlContent .= "        return !v || v.indexOf('$(') !== -1;\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function resolveHotspotLoginUrl() {\n";
$htmlContent .= "        var form = document.getElementById('loginForm');\n";
$htmlContent .= "        var action = form ? String(form.getAttribute('action') || '') : '';\n";
$htmlContent .= "        if (!isMikroTikVar(action)) {\n";
$htmlContent .= "            try { localStorage.setItem('pamnet_link_login', action); } catch (e) {}\n";
$htmlContent .= "            return action;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var q = pamnetQueryParam('link-login') || pamnetQueryParam('link_login') || '';\n";
$htmlContent .= "            if (q && !isMikroTikVar(q)) {\n";
$htmlContent .= "                try { localStorage.setItem('pamnet_link_login', q); } catch (e2) {}\n";
$htmlContent .= "                return q;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (e3) {}\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var saved = localStorage.getItem('pamnet_link_login') || '';\n";
$htmlContent .= "            if (saved && !isMikroTikVar(saved)) { return saved; }\n";
$htmlContent .= "        } catch (e4) {}\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            if (document.referrer) {\n";
$htmlContent .= "                var m = String(document.referrer).match(/^(https?:)\\/\\/([^\\/]+)/i);\n";
$htmlContent .= "                if (m) {\n";
$htmlContent .= "                    var h = m[2].split(':')[0] || '';\n";
$htmlContent .= "                    if (/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2\\d|3[01])\\.)/.test(h) || h === 'hotspot' || /\\.lan$/i.test(h)) {\n";
$htmlContent .= "                        return m[1] + '//' + m[2] + '/login';\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                }\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (e5) {}\n";
$htmlContent .= "        // Same-origin captive portal (router IP or Hotspot DNS name)\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var host = window.location.hostname || '';\n";
$htmlContent .= "            if (/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2\\d|3[01])\\.)/.test(host) || /pmninternet\\.net$/i.test(host) || host === 'hotspot') {\n";
$htmlContent .= "                return window.location.protocol + '//' + window.location.host + '/login';\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (e6) {}\n";
$htmlContent .= "        // Configured Hotspot gateway (from billing server)\n";
$htmlContent .= "        var configured = '" . addslashes($hotspotLoginUrl) . "';\n";
$htmlContent .= "        if (configured && !isMikroTikVar(configured)) { return configured; }\n";
$htmlContent .= "        return '';\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function postToHotspotLogin(loginUrl, username, password) {\n";
$htmlContent .= "        var pass = password || '1234';\n";
$htmlContent .= "        var base = String(loginUrl || '').replace(/\\/+$/, '');\n";
$htmlContent .= "        if (!base) {\n";
$htmlContent .= "            try {\n";
$htmlContent .= "                var host = window.location.hostname || '';\n";
$htmlContent .= "                if (/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2\\d|3[01])\\.)/.test(host)) {\n";
$htmlContent .= "                    base = window.location.protocol + '//' + window.location.host + '/login';\n";
$htmlContent .= "                }\n";
$htmlContent .= "            } catch (eB) {}\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (!base) { base = 'http://10.0.0.1/login'; }\n";
$htmlContent .= "        if (!/\\/login$/i.test(base)) { base = base + '/login'; }\n";
$htmlContent .= "        // Prefer POST http-pap. Parallel CHAP retries reuse a one-time challenge and fail.\n";
$htmlContent .= "        var f = document.createElement('form');\n";
$htmlContent .= "        f.method = 'post';\n";
$htmlContent .= "        f.action = base;\n";
$htmlContent .= "        f.style.display = 'none';\n";
$htmlContent .= "        function add(n, v) {\n";
$htmlContent .= "            var i = document.createElement('input');\n";
$htmlContent .= "            i.type = 'hidden'; i.name = n; i.value = v;\n";
$htmlContent .= "            f.appendChild(i);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        add('username', username);\n";
$htmlContent .= "        add('password', pass);\n";
$htmlContent .= "        var dst = 'http://10.0.0.1/status';\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var h = window.location.hostname || '';\n";
$htmlContent .= "            if (h && String(h).indexOf('$(') === -1) {\n";
$htmlContent .= "                dst = window.location.protocol + '//' + window.location.host + '/status';\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (eD) {}\n";
$htmlContent .= "        add('dst', dst);\n";
$htmlContent .= "        add('popup', 'true');\n";
$htmlContent .= "        document.body.appendChild(f);\n";
$htmlContent .= "        try { f.submit(); return; } catch (ePost) {}\n";
$htmlContent .= "        var getUrl = base + '?username=' + encodeURIComponent(username)\n";
$htmlContent .= "            + '&password=' + encodeURIComponent(pass)\n";
$htmlContent .= "            + '&dst=' + encodeURIComponent(dst)\n";
$htmlContent .= "            + '&popup=true';\n";
$htmlContent .= "        try { window.location.replace(getUrl); } catch (eNav) { window.location.href = getUrl; }\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    // PAP connects the device after payment (do not wait for a second API round-trip).\n";
$htmlContent .= "    function submitHotspotLogin() {\n";
$htmlContent .= "        if (window.__pamnetWifiOnline) { return; }\n";
$htmlContent .= "        if (window.__pamnetLoginLock) { return; }\n";
$htmlContent .= "        window.__pamnetLoginLock = true;\n";
$htmlContent .= "        var uIn = document.getElementById('usernameInput');\n";
$htmlContent .= "        var pIn = document.getElementById('passwordInput');\n";
$htmlContent .= "        var username = uIn ? String(uIn.value || '').trim() : '';\n";
$htmlContent .= "        var password = pIn ? String(pIn.value || '1234') : '1234';\n";
$htmlContent .= "        if (!username) {\n";
$htmlContent .= "            window.__pamnetLoginLock = false;\n";
$htmlContent .= "            return;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        var loginUrl = resolveHotspotLoginUrl();\n";
$htmlContent .= "        postToHotspotLogin(loginUrl, username, password);\n";
$htmlContent .= "        setTimeout(function() { window.__pamnetLoginLock = false; }, 8000);\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function connectToWifi(account, password, message) {\n";
$htmlContent .= "        if (window.PAMNET_PREVIEW === true) {\n";
$htmlContent .= "            try { ensureSwal().fire({icon:'info',title:'Admin preview only',text:'Wi-Fi connect runs only on the MikroTik Hotspot page, not in admin Preview.'}); } catch (eP) { alert('Admin preview: connect works on the router login page.'); }\n";
$htmlContent .= "            return false;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (window.__pamnetWifiOnline) { return true; }\n";
$htmlContent .= "        var username = resolveHotspotAccount(account);\n";
$htmlContent .= "        var pass = password || '1234';\n";
$htmlContent .= "        if (!username) { return false; }\n";
$htmlContent .= "        var uIn = document.getElementById('usernameInput');\n";
$htmlContent .= "        var pIn = document.getElementById('passwordInput');\n";
$htmlContent .= "        if (uIn) { uIn.value = username; }\n";
$htmlContent .= "        if (pIn) { pIn.value = pass; }\n";
$htmlContent .= "        try { setCookie('account_number', username, 365); } catch (e) {}\n";
$htmlContent .= "        try { localStorage.setItem('pamnet_pending_login', JSON.stringify({u: username, p: pass, t: Date.now()})); } catch (eL) {}\n";
$htmlContent .= "        showWifiConnecting(message || 'Connecting to Wi-Fi…', 'Authenticating your package on this network…');\n";
$htmlContent .= "        window.__pamnetConnecting = true;\n";
$htmlContent .= "        window.__pamnetConnectStarted = true;\n";
$htmlContent .= "        // 1) Server-side MikroTik login. 2) Browser PAP only if API failed.\n";
$htmlContent .= "        apiAutologinThenBrowse(username, pass).then(function(ok) {\n";
$htmlContent .= "            if (window.__pamnetWifiOnline) { return; }\n";
$htmlContent .= "            if (ok) { finishWifiConnected('Connected'); return; }\n";
$htmlContent .= "            submitHotspotLogin();\n";
$htmlContent .= "        });\n";
$htmlContent .= "        return true;\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    function connectToWifiWithRetries(account, password, attempts) {\n";
$htmlContent .= "        // One attempt per page load. Failed login reloads login.html; tryAutoReconnect retries.\n";
$htmlContent .= "        // Parallel retries reuse CHAP/session and cause \"invalid username or password\".\n";
$htmlContent .= "        connectToWifi(account, password || '1234', 'Connecting to Wi-Fi…');\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    // After payment: connect immediately (verify already pushes user + may autologin)\n";
$htmlContent .= "    function waitReadyThenConnect(username, password, triesLeft) {\n";
$htmlContent .= "        var left = (typeof triesLeft === 'number') ? triesLeft : 8;\n";
$htmlContent .= "        var user = resolveHotspotAccount(username);\n";
$htmlContent .= "        var pass = password || '1234';\n";
$htmlContent .= "        if (!user) { return; }\n";
$htmlContent .= "        if (window.__pamnetConnectStarted && left < 8) {\n";
$htmlContent .= "            // Already connecting from payment success — avoid duplicate UI flashes\n";
$htmlContent .= "        }\n";
$htmlContent .= "        showWifiConnecting('Payment successful', 'Connecting you to Wi-Fi now…');\n";
$htmlContent .= "        pamnetFetch('verify', {\n";
$htmlContent .= "            method: 'POST',\n";
$htmlContent .= "            headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "            body: JSON.stringify(pamnetAccountPayload(user))\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .then(function(r) { return r.json(); })\n";
$htmlContent .= "        .then(function(vdata) {\n";
$htmlContent .= "            var code = (vdata && vdata.Resultcode != null) ? String(vdata.Resultcode) : '';\n";
$htmlContent .= "            if (code === '3') {\n";
$htmlContent .= "                try { sessionStorage.removeItem('pamnet_login_fails'); } catch (eV) {}\n";
$htmlContent .= "                var p = vdata.tyhK || pass;\n";
$htmlContent .= "                if (vdata.logged_in) {\n";
$htmlContent .= "                    window.__pamnetConnectStarted = true;\n";
$htmlContent .= "                    finishWifiConnected('Connected');\n";
$htmlContent .= "                    return null;\n";
$htmlContent .= "                }\n";
$htmlContent .= "                if (!window.__pamnetConnectStarted) {\n";
$htmlContent .= "                    window.__pamnetConnectStarted = true;\n";
$htmlContent .= "                    connectToWifi(vdata.username || user, p, 'Connecting to Wi-Fi…');\n";
$htmlContent .= "                } else {\n";
$htmlContent .= "                    // Retry API autologin immediately if first attempt did not stick\n";
$htmlContent .= "                    apiAutologinThenBrowse(vdata.username || user, p).then(function(ok) {\n";
$htmlContent .= "                        if (window.__pamnetWifiOnline) { return; }\n";
$htmlContent .= "                        if (ok) { finishWifiConnected('Connected'); return; }\n";
$htmlContent .= "                        submitHotspotLogin();\n";
$htmlContent .= "                    });\n";
$htmlContent .= "                }\n";
$htmlContent .= "                return null;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            return pamnetFetch('check_active', {\n";
$htmlContent .= "                method: 'POST',\n";
$htmlContent .= "                headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "                body: JSON.stringify(pamnetAccountPayload(user))\n";
$htmlContent .= "            }).then(function(r2) { return r2.json(); });\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .then(function(data) {\n";
$htmlContent .= "            if (data === null || typeof data === 'undefined') { return; }\n";
$htmlContent .= "            if (data && data.active) {\n";
$htmlContent .= "                try { sessionStorage.removeItem('pamnet_login_fails'); } catch (e8) {}\n";
$htmlContent .= "                var p = data.tyhK || pass;\n";
$htmlContent .= "                if (data.logged_in) {\n";
$htmlContent .= "                    window.__pamnetConnectStarted = true;\n";
$htmlContent .= "                    finishWifiConnected('Connected');\n";
$htmlContent .= "                    return;\n";
$htmlContent .= "                }\n";
$htmlContent .= "                if (!window.__pamnetConnectStarted) {\n";
$htmlContent .= "                    window.__pamnetConnectStarted = true;\n";
$htmlContent .= "                    connectToWifi(data.username || user, p, 'Connecting to Wi-Fi…');\n";
$htmlContent .= "                }\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (left > 0) {\n";
$htmlContent .= "                setTimeout(function() { waitReadyThenConnect(user, pass, left - 1); }, 400);\n";
$htmlContent .= "            } else if (!window.__pamnetConnectStarted) {\n";
$htmlContent .= "                window.__pamnetConnectStarted = true;\n";
$htmlContent .= "                connectToWifi(user, pass, 'Connecting to Wi-Fi…');\n";
$htmlContent .= "            }\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .catch(function() {\n";
$htmlContent .= "            if (left > 0) {\n";
$htmlContent .= "                setTimeout(function() { waitReadyThenConnect(user, pass, left - 1); }, 400);\n";
$htmlContent .= "            } else if (!window.__pamnetConnectStarted) {\n";
$htmlContent .= "                window.__pamnetConnectStarted = true;\n";
$htmlContent .= "                connectToWifi(user, pass, 'Connecting to Wi-Fi…');\n";
$htmlContent .= "            }\n";
$htmlContent .= "        });\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    // Silent check on page load — reconnect active packages without asking to Sign In\n";
$htmlContent .= "    function tryAutoReconnect() {\n";
$htmlContent .= "        if (window.PAMNET_PREVIEW) { return; }\n";
$htmlContent .= "        if (window.__pamnetLoginLock || window.__pamnetConnecting || window.__pamnetConnectStarted) { return; }\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) { return; }\n";
$htmlContent .= "        } catch (e0) {}\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var fails = parseInt(sessionStorage.getItem('pamnet_login_fails') || '0', 10) || 0;\n";
$htmlContent .= "            if (fails >= 12) { return; }\n";
$htmlContent .= "        } catch (e1) {}\n";
$htmlContent .= "        var account = '';\n";
$htmlContent .= "        var pass = '1234';\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var pending = JSON.parse(localStorage.getItem('pamnet_pending_login') || 'null');\n";
$htmlContent .= "            if (pending && pending.u && pending.t && (Date.now() - pending.t) < 24 * 60 * 60 * 1000) {\n";
$htmlContent .= "                account = String(pending.u).trim();\n";
$htmlContent .= "                pass = String(pending.p || '1234');\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (eP) {}\n";
$htmlContent .= "        if (!account) {\n";
$htmlContent .= "            try { account = String(getCookie('account_number') || '').trim(); } catch (e) {}\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (!account) { return; }\n";
$htmlContent .= "        var uIn = document.getElementById('usernameInput');\n";
$htmlContent .= "        if (uIn) { uIn.value = account; }\n";
$htmlContent .= "        // Silent: do NOT show overlay / \"Restoring package\" on the purchase page\n";
$htmlContent .= "        pamnetFetch('check_active', {\n";
$htmlContent .= "            method: 'POST',\n";
$htmlContent .= "            headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "            body: JSON.stringify(pamnetAccountPayload(account))\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .then(function(r) { return r.json(); })\n";
$htmlContent .= "        .then(function(data) {\n";
$htmlContent .= "            if (!(data && data.active)) {\n";
$htmlContent .= "                // Package expired / inactive — keep Sign-In / buy UI\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            // Still active — never leave the customer stuck on Sign-In\n";
$htmlContent .= "            try {\n";
$htmlContent .= "                var signIn = document.getElementById('signInSection');\n";
$htmlContent .= "                if (signIn) { signIn.style.display = 'none'; }\n";
$htmlContent .= "            } catch (eS) {}\n";
$htmlContent .= "            if (data.logged_in) {\n";
$htmlContent .= "                finishWifiConnected('Connected');\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            try { sessionStorage.setItem('pamnet_login_fails', String((parseInt(sessionStorage.getItem('pamnet_login_fails')||'0',10)||0)+1)); } catch (eF) {}\n";
$htmlContent .= "            connectToWifi(data.username || account, data.tyhK || pass, 'Restoring your active package…');\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .catch(function() { /* silent failure — keep purchase page */ });\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    // Simple submit function (handled by event listener below)\n";
$htmlContent .= "    function submitLogin() {\n";
$htmlContent .= "        document.getElementById('submitBtn').click();\n";
$htmlContent .= "    }\n";
$htmlContent .= "\n";
$htmlContent .= "    // Main click handler for submit button (like reference payments.html)\n";
$htmlContent .= "    document.addEventListener('DOMContentLoaded', function() {\n";
$htmlContent .= "        var submitBtn = document.getElementById('submitBtn');\n";
$htmlContent .= "        \n";
$htmlContent .= "        submitBtn.addEventListener('click', function(event) {\n";
$htmlContent .= "            event.preventDefault(); // Prevent the default button action\n";
$htmlContent .= "            \n";
$htmlContent .= "            var accountNumber = resolveHotspotAccount();\n";
$htmlContent .= "            \n";
$htmlContent .= "            if (accountNumber) {\n";
$htmlContent .= "                connectToWifi(accountNumber, '1234', 'Connecting to Wi-Fi…');\n";
$htmlContent .= "            } else {\n";
$htmlContent .= "                event.preventDefault();\n";
$htmlContent .= "                Swal.fire({\n";
$htmlContent .= "                    icon: 'warning',\n";
$htmlContent .= "                    title: 'Missing Account Number',\n";
$htmlContent .= "                    text: 'Please enter your account number.',\n";
$htmlContent .= "                });\n";
$htmlContent .= "                return false;\n";
$htmlContent .= "            }\n";
$htmlContent .= "        });\n";
$htmlContent .= "    });\n";
$htmlContent .= "\n";
$htmlContent .= "</script>\n";

// Add fetchData function with enhanced card design and features
$htmlContent .= "<script>\n";
$htmlContent .= "// --- Plans Fetch and Display ---\n";
$htmlContent .= "function fetchData() {\n";
$htmlContent .= "    var siteUrl = pamnetApi('hotspot_plans');\n";
$htmlContent .= "    var request = new XMLHttpRequest();\n";
$htmlContent .= "    var routerId = (window.PAMNET_PORTAL && PAMNET_PORTAL.routerId) ? String(PAMNET_PORTAL.routerId) : '" . addslashes((string) $routerId) . "';\n";
$htmlContent .= "    var requestData = JSON.stringify({router_id: routerId});\n";
$htmlContent .= "    \n";

$htmlContent .= "    \n";
$htmlContent .= "    request.open(\"POST\", siteUrl, true);\n";
$htmlContent .= "    request.setRequestHeader('Content-Type', 'application/json');\n";
$htmlContent .= "    request.onload = function() {\n";
$htmlContent .= "        if (request.readyState === XMLHttpRequest.DONE) {\n";
$htmlContent .= "            if (request.status === 200) {\n";
$htmlContent .= "                try {\n";
$htmlContent .= "                    var fetchedData = JSON.parse(request.responseText);\n";

$htmlContent .= "                    \n";
$htmlContent .= "                    if (fetchedData.status === 'error') {\n";
$htmlContent .= "                        console.error('API Error:', fetchedData.message);\n";
$htmlContent .= "                        document.getElementById('cards-container').innerHTML = '<p class=\"text-center text-red-500 py-8\">Error: ' + fetchedData.message + '</p>';\n";
$htmlContent .= "                        return;\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                    \n";
$htmlContent .= "                    if (Array.isArray(fetchedData) && fetchedData.length > 0) {\n";
$htmlContent .= "                        populateCards({data: fetchedData});\n";
$htmlContent .= "                    } else if (fetchedData.data && Array.isArray(fetchedData.data) && fetchedData.data.length > 0) {\n";
$htmlContent .= "                        populateCards(fetchedData);\n";
$htmlContent .= "                    } else {\n";

$htmlContent .= "                        document.getElementById('cards-container').innerHTML = '<p class=\"text-center text-gray-500 py-8\">No plans available at the moment.</p>';\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                } catch (e) {\n";

$htmlContent .= "                    document.getElementById('cards-container').innerHTML = '<p class=\"text-center text-red-500 py-8\">Error parsing response. Please try again later.</p>';\n";
$htmlContent .= "                }\n";
$htmlContent .= "            } else {\n";

$htmlContent .= "                document.getElementById('cards-container').innerHTML = '<p class=\"text-center text-red-500 py-8\">Network error (' + request.status + '). Please try again later.</p>';\n";
$htmlContent .= "            }\n";
$htmlContent .= "        }\n";
$htmlContent .= "    };\n";
$htmlContent .= "    request.onerror = function() {\n";
$htmlContent .= "        console.error(\"Network error\");\n";
$htmlContent .= "        document.getElementById('cards-container').innerHTML = '<p class=\"text-center text-red-500 py-8\">Network error. Please check your connection.</p>';\n";
$htmlContent .= "    };\n";
$htmlContent .= "    request.send(requestData);\n";
$htmlContent .= "}\n\n";

$htmlContent .= "function populateCards(data) {\n";
$htmlContent .= "    var cardsContainer = document.getElementById('cards-container');\n";
$htmlContent .= "    cardsContainer.innerHTML = ''; // Clear existing content\n";
$htmlContent .= "    \n";
$htmlContent .= "    data.data.forEach(function(router) {\n";
$htmlContent .= "        var plans = router.plans_hotspot || [];\n";
$htmlContent .= "        \n";
$htmlContent .= "        // Sort plans: offer plans first, then by price (lowest to highest)\n";
$htmlContent .= "        plans.sort(function(a, b) {\n";
$htmlContent .= "            var planNameA = (a.planname || a.name_plan || '').toLowerCase();\n";
$htmlContent .= "            var planNameB = (b.planname || b.name_plan || '').toLowerCase();\n";
$htmlContent .= "            \n";
$htmlContent .= "            // Check if plans are offer plans\n";
$htmlContent .= "            var isOfferA = planNameA.indexOf('offer') !== -1;\n";
$htmlContent .= "            var isOfferB = planNameB.indexOf('offer') !== -1;\n";
$htmlContent .= "            \n";
$htmlContent .= "            // If one is offer and other is not, offer goes first\n";
$htmlContent .= "            if (isOfferA && !isOfferB) return -1;\n";
$htmlContent .= "            if (!isOfferA && isOfferB) return 1;\n";
$htmlContent .= "            \n";
$htmlContent .= "            // If both are offers or both are regular plans, sort by price\n";
$htmlContent .= "            var priceA = parseFloat(a.price || 0);\n";
$htmlContent .= "            var priceB = parseFloat(b.price || 0);\n";
$htmlContent .= "            return priceA - priceB;\n";
$htmlContent .= "        });\n";
$htmlContent .= "        \n";
$htmlContent .= "        plans.forEach(function(item) {\n";
$htmlContent .= "            // Map different field name formats from API\n";
$htmlContent .= "            var planName = item.planname || item.name_plan || 'Unknown Plan';\n";
$htmlContent .= "            var planPrice = item.price || '0';\n";
$htmlContent .= "            var planValidity = item.validity || '1';\n";
$htmlContent .= "            var planUnit = item.timelimit || item.validity_unit || 'day';\n";
$htmlContent .= "            var planId = item.planId || item.id;\n";
$htmlContent .= "            var currency = item.currency || '" . $currency_code . "';\n";
$htmlContent .= "            var routerId = item.routerId || '" . $routerId . "';\n";
$htmlContent .= "            var sharedUsers = item.shared_users || '1';\n";
$htmlContent .= "            var deviceText = sharedUsers == '1' ? '1 device' : sharedUsers + ' devices';\n";
$htmlContent .= "            \n";
$htmlContent .= "            var cardDiv = document.createElement('div');\n";
$htmlContent .= "            \n";
$htmlContent .= "            // Check if plan is an offer plan (case insensitive)\n";
$htmlContent .= "            var isOfferPlan = planName.toLowerCase().indexOf('offer') !== -1;\n";
$htmlContent .= "            cardDiv.className = isOfferPlan ? 'plan-card offer-plan bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden cursor-pointer fade-in' : 'plan-card bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden cursor-pointer fade-in';\n";
$htmlContent .= "            cardDiv.setAttribute('role', 'button');\n";
$htmlContent .= "            cardDiv.setAttribute('aria-label', 'Buy ' + planName);\n";
$htmlContent .= "            \n";
$htmlContent .= "            // Make the entire card clickable - exact signature from reference\n";
$htmlContent .= "            cardDiv.onclick = function(event) { \n";
$htmlContent .= "                handlePhoneNumberSubmission(planId, routerId, planPrice);\n";
$htmlContent .= "                return false;\n";
$htmlContent .= "            };\n";
$htmlContent .= "            cardDiv.innerHTML = \n";
$htmlContent .= "                '<div class=\"bg-green-500 text-white w-full py-1.5 px-2\">' +\n";
$htmlContent .= "                    '<h2 class=\"plan-title font-medium uppercase text-center truncate\">' + planName + '</h2>' +\n";
$htmlContent .= "                '</div>' +\n";
$htmlContent .= "                '<div class=\"px-2 py-3 flex-grow text-center\">' +\n";
$htmlContent .= "                    '<p class=\"plan-price font-bold text-green-600 mb-1\">' +\n";
$htmlContent .= "                        '<span class=\"plan-currency font-medium text-black\">' + currency + '</span> ' +\n";
$htmlContent .= "                        planPrice +\n";
$htmlContent .= "                    '</p>' +\n";
$htmlContent .= "                    '<p class=\"plan-validity text-gray-600 mb-2\">' +\n";
$htmlContent .= "                        'Valid for ' + planValidity + ' ' + planUnit + (planValidity > 1 ? '(s)' : '') +\n";
$htmlContent .= "                    '</p>' +\n";
$htmlContent .= "                    '<p class=\"device-limit text-sm text-gray-700 font-semibold mb-2\">' +\n";
$htmlContent .= "                        deviceText +\n";
$htmlContent .= "                    '</p>' +\n";
$htmlContent .= "                '</div>' +\n";
$htmlContent .= "                '<div class=\"px-2 pb-2\">' +\n";
$htmlContent .= "                    '<button class=\"plan-button w-full bg-gray-900 text-white hover:bg-blue-600 font-semibold py-1.5 px-3 rounded-lg transition duration-300\"' +\n";
$htmlContent .= "                        ' onclick=\"handlePhoneNumberSubmission(\\'' + planId + '\\', \\'' + routerId + '\\', \\'' + planPrice + '\\'); event.stopPropagation(); return false;\">' +\n";
$htmlContent .= "                            'Buy Now' +\n";
$htmlContent .= "                    '</button>' +\n";
$htmlContent .= "                '</div>';\n";
$htmlContent .= "            \n";
$htmlContent .= "            cardsContainer.appendChild(cardDiv);\n";
$htmlContent .= "        });\n";
$htmlContent .= "    });\n";
$htmlContent .= "    enhanceCardInteractions();\n";
$htmlContent .= "    adjustCardSizes();\n";
$htmlContent .= "}\n";
$htmlContent .= "\n";
$htmlContent .= "function enhanceCardInteractions() {\n";
$htmlContent .= "    var cards = document.querySelectorAll('.plan-card');\n";
$htmlContent .= "    for (var ci = 0; ci < cards.length; ci++) {\n";
$htmlContent .= "        (function(card) {\n";
$htmlContent .= "        card.addEventListener('touchstart', function() {\n";
$htmlContent .= "            this.style.transform = 'translateY(-2px)';\n";
$htmlContent .= "            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.2)';\n";
$htmlContent .= "        });\n";
$htmlContent .= "        card.addEventListener('touchend', function() {\n";
$htmlContent .= "            var self = this;\n";
$htmlContent .= "            setTimeout(function() {\n";
$htmlContent .= "                self.style.transform = '';\n";
$htmlContent .= "                self.style.boxShadow = '';\n";
$htmlContent .= "            }, 150);\n";
$htmlContent .= "        });\n";
$htmlContent .= "        card.setAttribute('tabindex', '0');\n";
$htmlContent .= "        card.addEventListener('keydown', function(e) {\n";
$htmlContent .= "            var code = e.keyCode || e.which || 0;\n";
$htmlContent .= "            var key = e.key || '';\n";
$htmlContent .= "            if (key === 'Enter' || key === ' ' || key === 'Select' || code === 13 || code === 32) {\n";
$htmlContent .= "                e.preventDefault();\n";
$htmlContent .= "                this.click();\n";
$htmlContent .= "            }\n";
$htmlContent .= "        });\n";
$htmlContent .= "        card.addEventListener('focus', function() { this.classList.add('pamnet-focused'); });\n";
$htmlContent .= "        card.addEventListener('blur', function() { this.classList.remove('pamnet-focused'); });\n";
$htmlContent .= "        card.classList.add('fade-in');\n";
$htmlContent .= "        })(cards[ci]);\n";
$htmlContent .= "    }\n";
$htmlContent .= "}\n";
$htmlContent .= "\n";
$htmlContent .= "function adjustCardSizes() {\n";
$htmlContent .= "    var cards = document.querySelectorAll('.plan-card');\n";
$htmlContent .= "    var container = document.getElementById('cards-container');\n";
$htmlContent .= "    if (!container) return;\n";
$htmlContent .= "    var containerWidth = container.offsetWidth;\n";
$htmlContent .= "    var screenWidth = window.innerWidth || (window.screen && window.screen.width) || 480;\n";
$htmlContent .= "    var columns = 2;\n";
$htmlContent .= "    if (screenWidth >= 1280) columns = 4;\n";
$htmlContent .= "    else if (screenWidth >= 960) columns = 3;\n";
$htmlContent .= "    else if (screenWidth >= 480) columns = 3;\n";
$htmlContent .= "    var cardWidth = Math.floor((containerWidth - (columns + 1) * 16) / columns);\n";
$htmlContent .= "    for (var ai = 0; ai < cards.length; ai++) {\n";
$htmlContent .= "        cards[ai].style.minWidth = cardWidth + 'px';\n";
$htmlContent .= "    }\n";
$htmlContent .= "}\n";
$htmlContent .= "\n";
$htmlContent .= "// Add resize listener for dynamic optimization\n";
$htmlContent .= "window.addEventListener('resize', adjustCardSizes);\n";
$htmlContent .= "window.addEventListener('orientationchange', function() {\n";
$htmlContent .= "    setTimeout(adjustCardSizes, 100);\n";
$htmlContent .= "});\n";
$htmlContent .= "\n";
$htmlContent .= "fetchData();\n";
$htmlContent .= "</script>\n";


// SweetAlert already loaded in <head> (with fallback)
$htmlContent .= "<script>try{ensureSwal();}catch(e){}</script>\n";

// Add utility functions matching payments.html
$htmlContent .= "<script>\n";
$htmlContent .= "    function formatPhoneNumber(phoneNumber) {\n";
$htmlContent .= "        if (phoneNumber.startsWith('+')) {\n";
$htmlContent .= "            phoneNumber = phoneNumber.substring(1);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (phoneNumber.startsWith('0')) {\n";
$htmlContent .= "            phoneNumber = '254' + phoneNumber.substring(1);\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (phoneNumber.match(/^(7|1)/)) {\n";
$htmlContent .= "            phoneNumber = '254' + phoneNumber;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        return phoneNumber;\n";
$htmlContent .= "    }\n\n";





$htmlContent .= "    // Dynamic settings loading\n";
$htmlContent .= "    function loadDynamicSettings() {\n";
$htmlContent .= "        pamnetFetch('hotspot_settings')\n";
$htmlContent .= "            .then(function(response) { return response.json(); })\n";
$htmlContent .= "            .then(function(data) {\n";
$htmlContent .= "                if (data.status === 'success') {\n";
$htmlContent .= "                    updateDynamicContent(data.data);\n";
$htmlContent .= "                }\n";
$htmlContent .= "            })\n";
$htmlContent .= "            .catch(function(error) {\n";
$htmlContent .= "                console.log('Settings fetch failed:', error);\n";
$htmlContent .= "            });\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    function updateDynamicContent(settings) {\n";
$htmlContent .= "        // Update phone number dynamically\n";
$htmlContent .= "        var customerCareSection = document.getElementById('customer-care-section');\n";
$htmlContent .= "        var phoneLink = document.getElementById('phone-link');\n";
$htmlContent .= "        var phoneNumber = document.getElementById('phone-number');\n";
$htmlContent .= "        \n";
$htmlContent .= "        if (settings.phone && settings.phone.trim() !== '') {\n";
$htmlContent .= "            customerCareSection.style.display = 'block';\n";
$htmlContent .= "            phoneLink.href = 'tel:' + settings.phone;\n";
$htmlContent .= "            phoneNumber.textContent = settings.phone;\n";
$htmlContent .= "        } else {\n";
$htmlContent .= "            customerCareSection.style.display = 'none';\n";
$htmlContent .= "        }\n";
$htmlContent .= "    }\n\n";
$htmlContent .= "    // Auto populate username; silent reconnect only if package already active\n";
$htmlContent .= "    document.addEventListener('DOMContentLoaded', function() {\n";
$htmlContent .= "        loadDynamicSettings();\n";
$htmlContent .= "        var accountNumber = persistAccountNumber();\n";
$htmlContent .= "        var usernameInput = document.getElementById('usernameInput');\n";
$htmlContent .= "        if (usernameInput) {\n";
$htmlContent .= "            usernameInput.value = accountNumber;\n";
$htmlContent .= "        }\n";
$htmlContent .= "        if (typeof tryAutoReconnect === 'function') { tryAutoReconnect(); }\n";
$htmlContent .= "    });\n\n";


$htmlContent .= "var loginTimeout; // Variable to store the timeout ID\n";
$htmlContent .= "var paymentCheckTimedOut = false;\n";

$htmlContent .= "function handlePhoneNumberSubmission(planId, routerId, price) {\n";
$htmlContent .= "    Swal.fire({\n";
$htmlContent .= "        title: 'Enter Your Mpesa Number',\n";
$htmlContent .= "        input: 'number',\n";
$htmlContent .= "        inputPlaceholder: '0712345678/0112345678',\n";
$htmlContent .= "        inputAttributes: {\n";
$htmlContent .= "            required: 'true'\n";
$htmlContent .= "        },\n";
$htmlContent .= "        inputValidator: function(value) {\n";
$htmlContent .= "            if (value === '') {\n";
$htmlContent .= "                return 'You need to write your phonenumber!';\n";
$htmlContent .= "            }\n";
$htmlContent .= "        },\n";
$htmlContent .= "        showCancelButton: true,\n";
$htmlContent .= "        confirmButtonColor: '#3085d6',\n";
$htmlContent .= "        cancelButtonColor: '#d33',\n";
$htmlContent .= "        confirmButtonText: 'Pay Now',\n";
$htmlContent .= "        reverseButtons: true,\n";
$htmlContent .= "        showLoaderOnConfirm: true,\n";
$htmlContent .= "        preConfirm: function(phoneNumber) {\n";
$htmlContent .= "            var formattedPhoneNumber = formatPhoneNumber(phoneNumber);\n";
$htmlContent .= "            var accountNumber = getCookie('account_number');\n";
$htmlContent .= "            if (!accountNumber) {\n";
$htmlContent .= "                accountNumber = generateAccountNumber();\n";
$htmlContent .= "                setCookie('account_number', accountNumber, 365);\n";
$htmlContent .= "            }\n";
$htmlContent .= "            document.getElementById('usernameInput').value = accountNumber;\n";
$htmlContent .= "            \n";
$htmlContent .= "            var id = getMikrotikClientIdentity();\n";
$htmlContent .= "            return pamnetFetch('grant', {\n";
$htmlContent .= "                method: 'POST',\n";
$htmlContent .= "                headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "                body: JSON.stringify({phone_number: formattedPhoneNumber, plan_id: planId, router_id: routerId, account_number: accountNumber, mac: id.mac, mac_address: id.mac, ip: id.ip, device: id.device}),\n";
$htmlContent .= "            })\n";
$htmlContent .= "            .then(function(response) {\n";
$htmlContent .= "                if (!response.ok) throw new Error('Payment start failed (HTTP ' + response.status + '). Please try again.');\n";
$htmlContent .= "                return response.json();\n";
$htmlContent .= "            })\n";
$htmlContent .= "            .then(function(data) {\n";
$htmlContent .= "                if (data.status === 'error') throw new Error(data.message);\n";
$htmlContent .= "                var newCode = data.username || data.account_number || '';\n";
$htmlContent .= "                var previousCode = data.previous_code || accountNumber || '';\n";
$htmlContent .= "                var codeReplaced = !!data.code_replaced || (newCode && previousCode && String(newCode) !== String(previousCode));\n";
$htmlContent .= "                if (newCode) {\n";
$htmlContent .= "                    setCookie('account_number', newCode, 365);\n";
$htmlContent .= "                    var uIn = document.getElementById('usernameInput');\n";
$htmlContent .= "                    if (uIn) { uIn.value = newCode; }\n";
$htmlContent .= "                }\n";
$htmlContent .= "                var codeHtml = '';\n";
$htmlContent .= "                if (codeReplaced && newCode) {\n";
$htmlContent .= "                    codeHtml = 'Code <b>' + previousCode + '</b> is still active.<br>'\n";
$htmlContent .= "                        + 'New Hotspot code for this purchase: <b>' + newCode + '</b><br>'\n";
$htmlContent .= "                        + 'Save this new code — it will not affect your current package.<br>';\n";
$htmlContent .= "                } else if (newCode) {\n";
$htmlContent .= "                    codeHtml = 'Your Hotspot code: <b>' + newCode + '</b><br>';\n";
$htmlContent .= "                }\n";
$htmlContent .= "                Swal.fire({\n";
$htmlContent .= "                    icon: 'info',\n";
$htmlContent .= "                    title: 'Enter M-Pesa PIN on your phone',\n";
$htmlContent .= "                    html: codeHtml\n";
$htmlContent .= "                        + 'PIN prompt sent to <b>' + formattedPhoneNumber + '</b>.<br>'\n";
$htmlContent .= "                        + 'After you enter PIN, this page connects automatically.',\n";
$htmlContent .= "                    showConfirmButton: false,\n";
$htmlContent .= "                    allowOutsideClick: false,\n";
$htmlContent .= "                    didOpen: function() {\n";
$htmlContent .= "                        Swal.showLoading();\n";
$htmlContent .= "                        checkPaymentStatus(formattedPhoneNumber);\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                });\n";
$htmlContent .= "                return formattedPhoneNumber;\n";
$htmlContent .= "            })\n";
$htmlContent .= "            .catch(function(error) {\n";
$htmlContent .= "                Swal.fire({\n";
$htmlContent .= "                    icon: 'error',\n";
$htmlContent .= "                    title: 'Oops...',\n";
$htmlContent .= "                    text: error.message,\n";
$htmlContent .= "                });\n";
$htmlContent .= "            });\n";
$htmlContent .= "        },\n";
$htmlContent .= "        allowOutsideClick: function() { return !Swal.isLoading(); }\n";
$htmlContent .= "    });\n";
$htmlContent .= "}\n\n";

$htmlContent .= "function checkPaymentStatus(phoneNumber) {\n";
$htmlContent .= "    paymentCheckTimedOut = false;\n";
$htmlContent .= "    var verifying = false;\n";
$htmlContent .= "    var checkInterval = null;\n";
$htmlContent .= "    var waitTimer = null;\n";
$htmlContent .= "    function stopPaymentWait() {\n";
$htmlContent .= "        paymentCheckTimedOut = true;\n";
$htmlContent .= "        if (checkInterval) { clearInterval(checkInterval); checkInterval = null; }\n";
$htmlContent .= "        if (waitTimer) { clearTimeout(waitTimer); waitTimer = null; }\n";
$htmlContent .= "        if (loginTimeout) { clearTimeout(loginTimeout); loginTimeout = null; }\n";
$htmlContent .= "    }\n";
$htmlContent .= "    function redirectAfterPayment(paidUser, pass) {\n";
$htmlContent .= "        if (window.__pamnetPayRedirecting) { return; }\n";
$htmlContent .= "        window.__pamnetPayRedirecting = true;\n";
$htmlContent .= "        stopPaymentWait();\n";
$htmlContent .= "        paidUser = (paidUser || '').trim();\n";
$htmlContent .= "        pass = pass || '1234';\n";
$htmlContent .= "        if (paidUser) {\n";
$htmlContent .= "            setCookie('account_number', paidUser, 365);\n";
$htmlContent .= "            var uIn = document.getElementById('usernameInput');\n";
$htmlContent .= "            if (uIn) { uIn.value = paidUser; }\n";
$htmlContent .= "        }\n";
$htmlContent .= "        var pIn = document.getElementById('passwordInput');\n";
$htmlContent .= "        if (pIn) { pIn.value = pass; }\n";
$htmlContent .= "        try { sessionStorage.removeItem('pamnet_login_fails'); } catch (e3) {}\n";
$htmlContent .= "        try { if (typeof Swal !== 'undefined') { Swal.close(); } } catch (e4) {}\n";
$htmlContent .= "        window.__pamnetConnectStarted = true;\n";
$htmlContent .= "        window.__pamnetConnecting = true;\n";
$htmlContent .= "        showWifiConnecting('Payment successful', 'Authorizing Wi-Fi now…');\n";
$htmlContent .= "        // Wait for MikroTik auth, then open /status. Never leave early (Back bug).\n";
$htmlContent .= "        // PAP only if API cannot authorize (no MAC/IP or force-login failed).\n";
$htmlContent .= "        var tries = 0;\n";
$htmlContent .= "        var papStarted = false;\n";
$htmlContent .= "        function startPap() {\n";
$htmlContent .= "            if (papStarted || window.__pamnetWifiOnline) { return; }\n";
$htmlContent .= "            papStarted = true;\n";
$htmlContent .= "            try { submitHotspotLogin(); } catch (ePap) {}\n";
$htmlContent .= "        }\n";
$htmlContent .= "        function tryConnectNow() {\n";
$htmlContent .= "            if (window.__pamnetWifiOnline) { return; }\n";
$htmlContent .= "            tries++;\n";
$htmlContent .= "            apiAutologinThenBrowse(paidUser, pass).then(function(ok) {\n";
$htmlContent .= "                if (window.__pamnetWifiOnline) { return; }\n";
$htmlContent .= "                if (ok) {\n";
$htmlContent .= "                    finishWifiConnected('Connected');\n";
$htmlContent .= "                    return;\n";
$htmlContent .= "                }\n";
$htmlContent .= "                if (tries >= 2) { startPap(); }\n";
$htmlContent .= "                if (tries < 8) {\n";
$htmlContent .= "                    setTimeout(tryConnectNow, 280);\n";
$htmlContent .= "                } else {\n";
$htmlContent .= "                    startPap();\n";
$htmlContent .= "                    setTimeout(function() {\n";
$htmlContent .= "                        if (!window.__pamnetWifiOnline) { finishWifiConnected('Connected'); }\n";
$htmlContent .= "                    }, 1800);\n";
$htmlContent .= "                }\n";
$htmlContent .= "            });\n";
$htmlContent .= "        }\n";
$htmlContent .= "        tryConnectNow();\n";
$htmlContent .= "        // If no lease id, API skips — start PAP quickly so captive browser auths itself\n";
$htmlContent .= "        setTimeout(function() {\n";
$htmlContent .= "            if (!window.__pamnetWifiOnline) { startPap(); }\n";
$htmlContent .= "        }, 900);\n";
$htmlContent .= "    }\n";
$htmlContent .= "    function pollPaymentOnce() {\n";
$htmlContent .= "        if (paymentCheckTimedOut || verifying || window.__pamnetWifiOnline || window.__pamnetConnectStarted) { return; }\n";
$htmlContent .= "        var account = resolveHotspotAccount();\n";
$htmlContent .= "        if (!account) { return; }\n";
$htmlContent .= "        verifying = true;\n";
$htmlContent .= "        var ctrl = null;\n";
$htmlContent .= "        try { if (typeof AbortController !== 'undefined') { ctrl = new AbortController(); } } catch (eA) {}\n";
$htmlContent .= "        var abortTimer = setTimeout(function() {\n";
$htmlContent .= "            try { if (ctrl) ctrl.abort(); } catch (eAb) {}\n";
$htmlContent .= "            verifying = false;\n";
$htmlContent .= "        }, 4000);\n";
$htmlContent .= "        var opts = {\n";
$htmlContent .= "            method: 'POST',\n";
$htmlContent .= "            headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "            body: JSON.stringify(pamnetAccountPayload(account))\n";
$htmlContent .= "        };\n";
$htmlContent .= "        if (ctrl) { opts.signal = ctrl.signal; }\n";
$htmlContent .= "        pamnetFetch('verify', opts)\n";
$htmlContent .= "        .then(function(response) { return response.json(); })\n";
$htmlContent .= "        .then(function(data) {\n";
$htmlContent .= "            console.log('Raw Response:', data);\n";
$htmlContent .= "            var code = (data && data.Resultcode != null) ? String(data.Resultcode) : '';\n";
$htmlContent .= "            if (code === '3') { // Success — redirect immediately\n";
$htmlContent .= "                redirectAfterPayment(data.username || account, data.tyhK || '1234');\n";
$htmlContent .= "            } else if (code === '2') { // Cancel / timeout / wrong PIN / no balance\n";
$htmlContent .= "                stopPaymentWait();\n";
$htmlContent .= "                var failMsg = (data && data.Message) ? String(data.Message) : 'Payment was not completed. Please try again.';\n";
$htmlContent .= "                // One quick C2B heal in case money already left the phone\n";
$htmlContent .= "                pamnetFetch('verify', {\n";
$htmlContent .= "                    method: 'POST',\n";
$htmlContent .= "                    headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "                    body: JSON.stringify(pamnetAccountPayload(account))\n";
$htmlContent .= "                }).then(function(r){ return r.json(); }).then(function(again){\n";
$htmlContent .= "                    var c2 = (again && again.Resultcode != null) ? String(again.Resultcode) : '';\n";
$htmlContent .= "                    if (c2 === '3') {\n";
$htmlContent .= "                        redirectAfterPayment(again.username || account, again.tyhK || '1234');\n";
$htmlContent .= "                        return;\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                    Swal.fire({\n";
$htmlContent .= "                        icon: 'warning',\n";
$htmlContent .= "                        title: 'M-Pesa PIN not completed',\n";
$htmlContent .= "                        text: failMsg,\n";
$htmlContent .= "                        confirmButtonText: 'Try Again'\n";
$htmlContent .= "                    });\n";
$htmlContent .= "                }).catch(function(){\n";
$htmlContent .= "                    Swal.fire({\n";
$htmlContent .= "                        icon: 'warning',\n";
$htmlContent .= "                        title: 'M-Pesa PIN not completed',\n";
$htmlContent .= "                        text: failMsg,\n";
$htmlContent .= "                        confirmButtonText: 'Try Again'\n";
$htmlContent .= "                    });\n";
$htmlContent .= "                });\n";
$htmlContent .= "            } else if (code === '1') { // Waiting for PIN\n";
$htmlContent .= "                try {\n";
$htmlContent .= "                    if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) {\n";
$htmlContent .= "                        var tEl = Swal.getTitle && Swal.getTitle();\n";
$htmlContent .= "                        if (tEl) { tEl.textContent = 'Waiting for M-Pesa confirmation…'; }\n";
$htmlContent .= "                    }\n";
$htmlContent .= "                } catch (eWait) {}\n";
$htmlContent .= "            }\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .catch(function(error) {\n";
$htmlContent .= "            console.log('Error: ' + error);\n";
$htmlContent .= "        })\n";
$htmlContent .= "        .then(function() { clearTimeout(abortTimer); verifying = false; });\n";
$htmlContent .= "    }\n";
$htmlContent .= "    pollPaymentOnce();\n";
$htmlContent .= "    checkInterval = setInterval(pollPaymentOnce, 300);\n";
$htmlContent .= "\n";
$htmlContent .= "    // Aggressive second poll at 1.2s / 2.5s in case first requests raced the webhook\n";
$htmlContent .= "    setTimeout(function(){ try { pollPaymentOnce(); } catch(e){} }, 1200);\n";
$htmlContent .= "    setTimeout(function(){ try { pollPaymentOnce(); } catch(e){} }, 2500);\n";
$htmlContent .= "    setTimeout(function(){ try { pollPaymentOnce(); } catch(e){} }, 5000);\n";
$htmlContent .= "    waitTimer = setTimeout(function() {\n";
$htmlContent .= "        if (window.__pamnetConnecting || window.__pamnetWifiOnline || window.__pamnetConnectStarted) { return; }\n";
$htmlContent .= "        var account = resolveHotspotAccount();\n";
$htmlContent .= "        // Final heal before giving up — payment may already be in DB.\n";
$htmlContent .= "        pamnetFetch('verify', {\n";
$htmlContent .= "            method: 'POST',\n";
$htmlContent .= "            headers: {'Content-Type': 'application/json'},\n";
$htmlContent .= "            body: JSON.stringify(pamnetAccountPayload(account))\n";
$htmlContent .= "        }).then(function(r){ return r.json(); }).then(function(finalData){\n";
$htmlContent .= "            var fc = (finalData && finalData.Resultcode != null) ? String(finalData.Resultcode) : '';\n";
$htmlContent .= "            if (fc === '3') {\n";
$htmlContent .= "                redirectAfterPayment(finalData.username || account, finalData.tyhK || '1234');\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (fc === '2') {\n";
$htmlContent .= "                stopPaymentWait();\n";
$htmlContent .= "                Swal.fire({\n";
$htmlContent .= "                    icon: 'warning',\n";
$htmlContent .= "                    title: 'M-Pesa PIN not completed',\n";
$htmlContent .= "                    text: (finalData && finalData.Message) ? String(finalData.Message) : 'Payment was not completed. Please try again.',\n";
$htmlContent .= "                    confirmButtonText: 'Try Again'\n";
$htmlContent .= "                });\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            stopPaymentWait();\n";
$htmlContent .= "            Swal.fire({\n";
$htmlContent .= "                icon: 'warning',\n";
$htmlContent .= "                title: 'Still waiting for M-Pesa',\n";
$htmlContent .= "                html: 'No confirmation yet for code <b>' + (account || '') + '</b>.<br>If you already entered PIN and paid, tap <b>Check again</b>.',\n";
$htmlContent .= "                showCancelButton: true,\n";
$htmlContent .= "                confirmButtonText: 'Check again',\n";
$htmlContent .= "                cancelButtonText: 'Try Buy Now again'\n";
$htmlContent .= "            }).then(function(res){\n";
$htmlContent .= "                if (res && res.isConfirmed) {\n";
$htmlContent .= "                    checkPaymentStatus(phoneNumber);\n";
$htmlContent .= "                }\n";
$htmlContent .= "            });\n";
$htmlContent .= "        }).catch(function(){\n";
$htmlContent .= "            stopPaymentWait();\n";
$htmlContent .= "            Swal.fire({\n";
$htmlContent .= "                icon: 'warning',\n";
$htmlContent .= "                title: 'Still waiting for M-Pesa',\n";
$htmlContent .= "                text: 'Could not confirm payment yet. If you already paid, tap Check again.',\n";
$htmlContent .= "                showCancelButton: true,\n";
$htmlContent .= "                confirmButtonText: 'Check again',\n";
$htmlContent .= "                cancelButtonText: 'Close'\n";
$htmlContent .= "            }).then(function(res){\n";
$htmlContent .= "                if (res && res.isConfirmed) { checkPaymentStatus(phoneNumber); }\n";
$htmlContent .= "            });\n";
$htmlContent .= "        });\n";
$htmlContent .= "    }, 120000);\n";
$htmlContent .= "    loginTimeout = waitTimer;\n";
$htmlContent .= "}\n\n";

$htmlContent .= "</script>\n";

// Simple and clean CSS for cards
$htmlContent .= "<style>\n";
$htmlContent .= "/* Device Compatibility Fixes */\n";
$htmlContent .= "* { box-sizing: border-box; }\n";
$htmlContent .= "body { margin: 0; padding: 0; overflow-x: hidden; }\n";
$htmlContent .= "</style>\n";

// Modern Form Styling
$htmlContent .= "<style>\n";
$htmlContent .= "/* Modern Form Styling */\n";
$htmlContent .= ".form-container {\n";
$htmlContent .= "    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\n";
$htmlContent .= "    background-size: 400% 400%;\n";
$htmlContent .= "    animation: gradientShift 15s ease infinite;\n";
$htmlContent .= "    padding: 2px;\n";
$htmlContent .= "}\n";
$htmlContent .= ".form-container > div {\n";
$htmlContent .= "    background: white;\n";
$htmlContent .= "    border-radius: 1rem;\n";
$htmlContent .= "}\n";
$htmlContent .= "@keyframes gradientShift {\n";
$htmlContent .= "    0% { background-position: 0% 50%; }\n";
$htmlContent .= "    50% { background-position: 100% 50%; }\n";
$htmlContent .= "    100% { background-position: 0% 50%; }\n";
$htmlContent .= "}\n";
$htmlContent .= ".input-field {\n";
$htmlContent .= "    transition: all 0.3s ease;\n";
$htmlContent .= "    border: 2px solid #e5e7eb;\n";
$htmlContent .= "}\n";
$htmlContent .= ".input-field:focus {\n";
$htmlContent .= "    border-color: #3b82f6;\n";
$htmlContent .= "    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);\n";
$htmlContent .= "    transform: translateY(-1px);\n";
$htmlContent .= "}\n";
$htmlContent .= ".submit-btn {\n";
$htmlContent .= "    transition: all 0.3s ease;\n";
$htmlContent .= "    position: relative;\n";
$htmlContent .= "    overflow: hidden;\n";
$htmlContent .= "}\n";
$htmlContent .= ".submit-btn:hover {\n";
$htmlContent .= "    transform: translateY(-2px);\n";
$htmlContent .= "    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);\n";
$htmlContent .= "}\n";
$htmlContent .= ".submit-btn:active {\n";
$htmlContent .= "    transform: translateY(0);\n";
$htmlContent .= "}\n";
$htmlContent .= ".submit-btn::before {\n";
$htmlContent .= "    content: '';\n";
$htmlContent .= "    position: absolute;\n";
$htmlContent .= "    top: 0;\n";
$htmlContent .= "    left: -100%;\n";
$htmlContent .= "    width: 100%;\n";
$htmlContent .= "    height: 100%;\n";
$htmlContent .= "    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);\n";
$htmlContent .= "    transition: left 0.5s;\n";
$htmlContent .= "}\n";
$htmlContent .= ".submit-btn:hover::before {\n";
$htmlContent .= "    left: 100%;\n";
$htmlContent .= "}\n";
$htmlContent .= "</style>\n";

// jQuery already loaded in <head> when available

// Universal portal: same page/behavior on phones, tablets, TVs, STBs, and PCs.
// Do NOT branch UI or access by TV/phone user-agent — detection is optional analytics only.
$htmlContent .= "<script>\n";
$htmlContent .= "(function() {\n";
$htmlContent .= "    function pamnetFocusSiblingCard(current, dir) {\n";
$htmlContent .= "        var cards = document.querySelectorAll('.plan-card');\n";
$htmlContent .= "        if (!cards || !cards.length) return;\n";
$htmlContent .= "        var idx = -1;\n";
$htmlContent .= "        for (var i = 0; i < cards.length; i++) { if (cards[i] === current) { idx = i; break; } }\n";
$htmlContent .= "        if (idx < 0) { cards[0].focus(); return; }\n";
$htmlContent .= "        var next = idx + dir;\n";
$htmlContent .= "        if (next < 0) next = cards.length - 1;\n";
$htmlContent .= "        if (next >= cards.length) next = 0;\n";
$htmlContent .= "        cards[next].focus();\n";
$htmlContent .= "    }\n";
$htmlContent .= "    document.addEventListener('DOMContentLoaded', function() {\n";
$htmlContent .= "        try { document.body.classList.add('pamnet-universal'); } catch (e0) {}\n";
$htmlContent .= "        try { if (typeof pamnetClassifyClient === 'function') { pamnetClassifyClient(); } } catch (eC) {}\n";
$htmlContent .= "        try {\n";
$htmlContent .= "            var viewport = document.querySelector('meta[name=viewport]');\n";
$htmlContent .= "            if (viewport) {\n";
$htmlContent .= "                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover');\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } catch (e1) {}\n";
$htmlContent .= "        document.addEventListener('keydown', function(e) {\n";
$htmlContent .= "            var code = e.keyCode || e.which || 0;\n";
$htmlContent .= "            var key = e.key || '';\n";
$htmlContent .= "            var t = e.target;\n";
$htmlContent .= "            if (!t) return;\n";
$htmlContent .= "            if (key === 'ArrowRight' || code === 39) {\n";
$htmlContent .= "                if (t.classList && t.classList.contains('plan-card')) { e.preventDefault(); pamnetFocusSiblingCard(t, 1); }\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (key === 'ArrowLeft' || code === 37) {\n";
$htmlContent .= "                if (t.classList && t.classList.contains('plan-card')) { e.preventDefault(); pamnetFocusSiblingCard(t, -1); }\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (key === 'ArrowDown' || code === 40) {\n";
$htmlContent .= "                if (t.classList && t.classList.contains('plan-card')) { e.preventDefault(); pamnetFocusSiblingCard(t, 1); }\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (key === 'ArrowUp' || code === 38) {\n";
$htmlContent .= "                if (t.classList && t.classList.contains('plan-card')) { e.preventDefault(); pamnetFocusSiblingCard(t, -1); }\n";
$htmlContent .= "                return;\n";
$htmlContent .= "            }\n";
$htmlContent .= "            if (key === 'Enter' || key === 'Select' || key === 'Accept' || code === 13) {\n";
$htmlContent .= "                if (t.classList && (t.classList.contains('cursor-pointer') || t.classList.contains('plan-card'))) {\n";
$htmlContent .= "                    e.preventDefault();\n";
$htmlContent .= "                    t.click();\n";
$htmlContent .= "                }\n";
$htmlContent .= "            }\n";
$htmlContent .= "        });\n";
$htmlContent .= "        var submitBtn = document.getElementById('submitBtn');\n";
$htmlContent .= "        if (submitBtn) { submitBtn.setAttribute('tabindex', '0'); }\n";
$htmlContent .= "    });\n";
$htmlContent .= "})();\n";
$htmlContent .= "</script>\n";

// Add button click handlers and voucher/mpesa functions
// Button click handler is now consolidated above in the main DOMContentLoaded listener

// Add voucher redemption function - exact copy from reference
$htmlContent .= "<script>\n";
$htmlContent .= "function redeemVoucher(router_id) {\n";
$htmlContent .= "    const voucherCode = document.getElementById('voucher_code').value;\n";
$htmlContent .= "    if (!voucherCode) {\n";
$htmlContent .= "        document.getElementById('message').innerText = 'Please enter a valid voucher code.';\n";
$htmlContent .= "        return;\n";
$htmlContent .= "    }\n\n";

$htmlContent .= "    pamnetFetch('redeem_voucher', {\n";
$htmlContent .= "        method: 'POST',\n";
$htmlContent .= "        headers: { 'Content-Type': 'application/json' },\n";
$htmlContent .= "        body: JSON.stringify({ voucher_code: voucherCode, account_number: generateAccountNumber(), router_id: router_id })\n";
$htmlContent .= "    })\n";
$htmlContent .= "    .then(response => {\n";
$htmlContent .= "        if (!response.ok) throw new Error('Network response was not ok');\n";
$htmlContent .= "        return response.json();\n";
$htmlContent .= "    })\n";
$htmlContent .= "    .then(data => {\n";
$htmlContent .= "        if (data.status === 'error') throw new Error(data.message);\n";

$htmlContent .= "        if (data && (data.status === 'success' || data.status === 'used')) {\n";
$htmlContent .= "            document.getElementById('message').innerText = data.message || 'Voucher redeemed successfully.';\n";
$htmlContent .= "            document.getElementById('usernameInput').value = data.username;\n";
$htmlContent .= "            document.getElementById('passwordInput').value = data.tyhK || '1234';\n";
$htmlContent .= "            setCookie('account_number', data.username, 365);\n\n";
$htmlContent .= "            if (typeof waitReadyThenConnect === 'function') {\n";
$htmlContent .= "                waitReadyThenConnect(data.username, data.tyhK || '1234', 8);\n";
$htmlContent .= "            } else if (typeof connectToWifiWithRetries === 'function') {\n";
$htmlContent .= "                connectToWifiWithRetries(data.username, data.tyhK || '1234', 6);\n";
$htmlContent .= "            } else {\n";
$htmlContent .= "                document.getElementById('submitBtn').click();\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } else {\n";
$htmlContent .= "            document.getElementById('message').innerText = (data && data.message) ? data.message : 'An error occurred. Please try again.';\n";
$htmlContent .= "        }\n";
$htmlContent .= "    })\n";
$htmlContent .= "    .catch(error => {\n";
$htmlContent .= "        console.error('Error redeeming voucher:', error);\n";
$htmlContent .= "        document.getElementById('message').innerText = error.message || 'An error occurred. Please try again.';\n";
$htmlContent .= "    });\n";
$htmlContent .= "}\n";
$htmlContent .= "</script>\n";

// Add MPesa reconnection function - exact copy from reference  
$htmlContent .= "<script>\n";
$htmlContent .= "function redeemMpesa() {\n";
$htmlContent .= "    const mpesaCode = document.getElementById('mpesa_code').value.trim();\n";
$htmlContent .= "    if (!mpesaCode) {\n";
$htmlContent .= "        document.getElementById('mpesaMessage').innerText = 'Please enter a valid MPESA code or full message.';\n";
$htmlContent .= "        return;\n";
$htmlContent .= "    }\n";
$htmlContent .= "    if (mpesaCode.length < 10) {\n";
$htmlContent .= "        document.getElementById('mpesaMessage').innerText = 'MPESA code must be at least 10 characters.';\n";
$htmlContent .= "        return;\n";
$htmlContent .= "    }\n\n";

$htmlContent .= "    pamnetFetch('redeem_mpesa_code', {\n";
$htmlContent .= "        method: 'POST',\n";
$htmlContent .= "        headers: { 'Content-Type': 'application/json' },\n";
$htmlContent .= "        body: JSON.stringify({ mpesa_code: mpesaCode })\n";
$htmlContent .= "    })\n";
$htmlContent .= "    .then(response => {\n";
$htmlContent .= "        if (!response.ok) throw new Error('Network response was not ok');\n";
$htmlContent .= "        return response.json();\n";
$htmlContent .= "    })\n";
$htmlContent .= "    .then(data => {\n";
$htmlContent .= "        if (!data || data.status === 'error' || data.status === 'danger' || data.already_used) {\n";
$htmlContent .= "            throw new Error((data && data.message) || 'This M-Pesa code has already been used and cannot be used again.');\n";
$htmlContent .= "        }\n\n";
$htmlContent .= "        if (data && (data.status === 'success')) {\n";
$htmlContent .= "            document.getElementById('mpesaMessage').innerText = data.message || 'MPESA code redeemed successfully.';\n";
$htmlContent .= "            document.getElementById('usernameInput').value = data.username;\n";
$htmlContent .= "            document.getElementById('passwordInput').value = data.tyhK || '1234';\n";
$htmlContent .= "            setCookie('account_number', data.username, 365);\n";
$htmlContent .= "            if (typeof waitReadyThenConnect === 'function') {\n";
$htmlContent .= "                waitReadyThenConnect(data.username, data.tyhK || '1234', 8);\n";
$htmlContent .= "            } else if (typeof connectToWifiWithRetries === 'function') {\n";
$htmlContent .= "                connectToWifiWithRetries(data.username, data.tyhK || '1234', 6);\n";
$htmlContent .= "            } else {\n";
$htmlContent .= "                document.getElementById('submitBtn').click();\n";
$htmlContent .= "            }\n";
$htmlContent .= "        } else {\n";
$htmlContent .= "            document.getElementById('mpesaMessage').innerText = (data && data.message) ? data.message : 'An error occurred. Please try again.';\n";
$htmlContent .= "        }\n";
$htmlContent .= "    })\n";
$htmlContent .= "    .catch(error => {\n";
$htmlContent .= "        console.error('Error redeeming MPESA code:', error);\n";
$htmlContent .= "        document.getElementById('mpesaMessage').innerText = error.message || 'An error occurred. Please try again.';\n";
$htmlContent .= "    });\n";
$htmlContent .= "}\n";
$htmlContent .= "</script>\n";

// Close all the HTML tags properly
$htmlContent .= "</html>\n";



$planStmt->close();
$mysqli->close();

$isDownload = isset($_GET['download']) && (string) $_GET['download'] === '1';
$isPreview = isset($_GET['preview']) && (string) $_GET['preview'] === '1';

// Preview: show banner + never auto-redirect away from billing host
if ($isPreview) {
    $banner = '<div style="position:sticky;top:0;z-index:100000;background:#065f46;color:#fff;padding:10px 14px;font:600 14px/1.4 system-ui,sans-serif;text-align:center;">'
        . 'PREVIEW MODE — this is how customers see the Hotspot login page. MikroTik auto-login redirects are disabled here.'
        . '</div>';
    $htmlContent = preg_replace('/<body([^>]*)>/i', '<body$1>' . $banner, $htmlContent, 1);
    // Hide unprocessed MikroTik template tokens so they don't appear as page text
    $htmlContent = preg_replace('/\$\((?:if|else|elif|endif)[^)]*\)/i', '', $htmlContent);
    $htmlContent = preg_replace('/\$\((?:link-[a-z0-9_-]+|chap-[a-z0-9_-]+|error|username|mac|ip|trial)\)/i', '', $htmlContent);
    // Hard-stop any residual redirects to unsubstituted MikroTik vars during preview
    $guard = "<script>window.PAMNET_PREVIEW=true;(function(){try{var h=location.hostname||'';if(h.indexOf('pamnet')!==-1||h.indexOf('localhost')!==-1||h==='127.0.0.1'){var _r=window.location.replace.bind(window.location);window.location.replace=function(u){u=String(u||'');if(u.indexOf('\$(')!==-1||u==='/\$%28link-redirect%29'||/link-redirect/i.test(u)){return;}return _r(u);};}}catch(e){}})();</script>";
    $htmlContent = preg_replace('/<head([^>]*)>/i', '<head$1>' . $guard, $htmlContent, 1);
}

if ($isDownload) {
    $filename = 'login.html';
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($htmlContent));
    echo $htmlContent;
    exit;
}

if ($isPreview) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $htmlContent;
    exit;
}

// Default: same as download so opening /download.php alone still works
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="login.html"');
header('Content-Length: ' . strlen($htmlContent));
echo $htmlContent;
exit;

