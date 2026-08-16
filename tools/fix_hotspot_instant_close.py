#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import shutil

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime('%Y%m%d-%H%M%S')
BACKUP = Path('/var/backups') / f'prm-hotspot-instant-connect-{STAMP}'


def fail(msg):
    raise RuntimeError(msg)


def backup(rel):
    src = ROOT / rel
    dst = BACKUP / rel
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(src, dst)


def replace_between(text, start, end, replacement, label):
    i = text.find(start)
    if i < 0:
        if replacement.strip() in text:
            return text
        fail(f'{label}: start marker not found')
    j = text.find(end, i)
    if j < 0:
        fail(f'{label}: end marker not found')
    return text[:i] + replacement + text[j:]


SUCCESS_DEST_JS = r'''    function resolveHotspotSuccessDestination() {
        var dst = '';
        try {
            var form = document.getElementById('loginForm');
            var input = form ? form.querySelector('input[name="dst"]') : null;
            if (input) { dst = String(input.value || '').trim(); }
        } catch (e0) {}
        try {
            if ((!dst || isMikroTikVar(dst)) && typeof pamnetQueryParam === 'function') {
                dst = String(
                    pamnetQueryParam('dst') ||
                    pamnetQueryParam('link-orig') ||
                    pamnetQueryParam('link_orig') ||
                    ''
                ).trim();
            }
        } catch (e1) {}
        // The original captive-check URL is ideal: Android/iOS/Windows then see
        // successful internet access and dismiss the "Sign in to network" window.
        // Never force /status here because that keeps the captive browser open.
        if (!dst || isMikroTikVar(dst) || /\/status(?:[?#]|$)/i.test(dst)) {
            dst = 'http://connectivitycheck.gstatic.com/generate_204';
        }
        return dst;
    }

'''

POST_LOGIN_JS = r'''    function postToHotspotLogin(loginUrl, username, password) {
        var pass = password || '1234';
        var base = String(loginUrl || '').replace(/\/+$/, '');
        if (!base) {
            try {
                var host = window.location.hostname || '';
                if (/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(host)) {
                    base = window.location.protocol + '//' + window.location.host + '/login';
                }
            } catch (eB) {}
        }
        if (!base) { base = 'http://10.0.0.1/login'; }
        if (!/\/login$/i.test(base)) { base = base + '/login'; }
        var f = document.createElement('form');
        f.method = 'post';
        f.action = base;
        f.style.display = 'none';
        function add(n, v) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = n; i.value = v;
            f.appendChild(i);
        }
        add('username', username);
        add('password', pass);
        add('dst', resolveHotspotSuccessDestination());
        add('popup', 'true');
        document.body.appendChild(f);
        try { f.submit(); return; } catch (ePost) {}
        var dst = resolveHotspotSuccessDestination();
        var getUrl = base + '?username=' + encodeURIComponent(username)
            + '&password=' + encodeURIComponent(pass)
            + '&dst=' + encodeURIComponent(dst)
            + '&popup=true';
        try { window.location.replace(getUrl); } catch (eNav) { window.location.href = getUrl; }
    }

'''

SUBMIT_LOGIN_JS = r'''    // Submit through MikroTik's native login form whenever possible. This preserves
    // CHAP handling and the original captive-check destination, so the OS can
    // dismiss its captive portal immediately after Access-Accept.
    function submitHotspotLogin() {
        if (window.__pamnetWifiOnline) { return; }
        if (window.__pamnetLoginLock) { return; }
        window.__pamnetLoginLock = true;
        setTimeout(function() { window.__pamnetLoginLock = false; }, 8000);

        var uIn = document.getElementById('usernameInput');
        var pIn = document.getElementById('passwordInput');
        var username = uIn ? String(uIn.value || '').trim() : '';
        var password = pIn ? String(pIn.value || '1234') : '1234';
        if (!username) {
            window.__pamnetLoginLock = false;
            return;
        }

        var nativeForm = document.getElementById('loginForm');
        var nativeAction = nativeForm ? String(nativeForm.getAttribute('action') || '') : '';
        var dst = resolveHotspotSuccessDestination();

        if (nativeForm && !isMikroTikVar(nativeAction)) {
            try {
                var nativeDst = nativeForm.querySelector('input[name="dst"]');
                if (nativeDst) { nativeDst.value = dst; }
                if (document.sendin) {
                    var chapDst = document.sendin.querySelector('input[name="dst"]');
                    if (chapDst) { chapDst.value = dst; }
                }
            } catch (eDst) {}

            // requestSubmit fires the normal onsubmit handler. When CHAP is active,
            // doLogin() hashes the password and submits the hidden sendin form.
            try {
                if (typeof nativeForm.requestSubmit === 'function') {
                    nativeForm.requestSubmit();
                    return;
                }
            } catch (eReq) {}
            try {
                var ev = document.createEvent('Event');
                ev.initEvent('submit', true, true);
                if (nativeForm.dispatchEvent(ev)) { nativeForm.submit(); }
                return;
            } catch (eEvt) {}
        }

        // Fallback for old captive WebViews / preview-derived pages.
        postToHotspotLogin(resolveHotspotLoginUrl(), username, password);
    }

'''

REDIRECT_JS = r'''    function redirectAfterPayment(paidUser, pass) {
        if (window.__pamnetPayRedirecting) { return; }
        window.__pamnetPayRedirecting = true;
        stopPaymentWait();
        paidUser = (paidUser || '').trim();
        pass = pass || '1234';
        if (paidUser) {
            setCookie('account_number', paidUser, 365);
            var uIn = document.getElementById('usernameInput');
            if (uIn) { uIn.value = paidUser; }
        }
        var pIn = document.getElementById('passwordInput');
        if (pIn) { pIn.value = pass; }
        try { sessionStorage.removeItem('pamnet_login_fails'); } catch (e3) {}
        try { if (typeof Swal !== 'undefined') { Swal.close(); } } catch (e4) {}
        window.__pamnetConnectStarted = true;
        window.__pamnetConnecting = true;
        showWifiConnecting('Payment confirmed', 'Connecting to the internet…');

        // verify(Resultcode=3) now means the Radius package is provisioned.
        // Do not call the RouterOS API and do not retry eight times: submit the
        // native MikroTik login once so it sends the RADIUS Access-Request.
        setTimeout(function() {
            try {
                submitHotspotLogin();
            } catch (eLogin) {
                window.__pamnetPayRedirecting = false;
                showWifiConnecting('Payment confirmed', 'Tap Connect if this page does not close automatically.');
            }
        }, 80);
    }

'''


def patch_plain():
    rel = 'hotspot_login.html'
    p = ROOT / rel
    s = p.read_text()

    if 'function resolveHotspotSuccessDestination()' not in s:
        marker = '    function postToHotspotLogin(loginUrl, username, password) {\n'
        if marker not in s:
            fail('hotspot_login.html: postToHotspotLogin marker not found')
        s = s.replace(marker, SUCCESS_DEST_JS + marker, 1)

    s = replace_between(
        s,
        '    function postToHotspotLogin(loginUrl, username, password) {\n',
        '    // PAP connects the device after payment',
        POST_LOGIN_JS,
        'hotspot_login.html postToHotspotLogin',
    )
    s = replace_between(
        s,
        '    // PAP connects the device after payment',
        '    function connectToWifi(account, password, message) {\n',
        SUBMIT_LOGIN_JS,
        'hotspot_login.html submitHotspotLogin',
    )
    s = replace_between(
        s,
        '    function redirectAfterPayment(paidUser, pass) {\n',
        '    function pollPaymentOnce() {\n',
        REDIRECT_JS,
        'hotspot_login.html redirectAfterPayment',
    )
    p.write_text(s)


def php_emit(js):
    out = []
    for line in js.splitlines():
        escaped = line.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$')
        out.append('$htmlContent .= "' + escaped + '\\n";')
    return '\n'.join(out) + '\n'


def patch_download():
    rel = 'download.php'
    p = ROOT / rel
    s = p.read_text()

    if 'resolveHotspotSuccessDestination()' not in s:
        marker = '$htmlContent .= "    function postToHotspotLogin(loginUrl, username, password) {\\n";\n'
        if marker not in s:
            fail('download.php: postToHotspotLogin marker not found')
        s = s.replace(marker, php_emit(SUCCESS_DEST_JS.rstrip()) + marker, 1)

    s = replace_between(
        s,
        '$htmlContent .= "    function postToHotspotLogin(loginUrl, username, password) {\\n";\n',
        '$htmlContent .= "    // PAP connects the device after payment',
        php_emit(POST_LOGIN_JS.rstrip()),
        'download.php postToHotspotLogin',
    )
    s = replace_between(
        s,
        '$htmlContent .= "    // PAP connects the device after payment',
        '$htmlContent .= "    function connectToWifi(account, password, message) {\\n";\n',
        php_emit(SUBMIT_LOGIN_JS.rstrip()),
        'download.php submitHotspotLogin',
    )
    s = replace_between(
        s,
        '$htmlContent .= "    function redirectAfterPayment(paidUser, pass) {\\n";\n',
        '$htmlContent .= "    function pollPaymentOnce() {\\n";\n',
        php_emit(REDIRECT_JS.rstrip()),
        'download.php redirectAfterPayment',
    )
    p.write_text(s)


def main():
    BACKUP.mkdir(parents=True, exist_ok=False)
    for rel in ('hotspot_login.html', 'download.php'):
        backup(rel)
    patch_plain()
    patch_download()
    print('SUCCESS: payment success now submits native MikroTik login immediately and preserves captive-check destination.')
    print('Backups:', BACKUP)


if __name__ == '__main__':
    main()
