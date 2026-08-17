#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import shutil

ROOT = Path(__file__).resolve().parents[1]
REL = 'system/plugin/rs_radius_wireguard_onboarding.php'
TARGET = ROOT / REL
STAMP = datetime.now().strftime('%Y%m%d-%H%M%S')
BACKUP = Path('/var/backups') / f'prm-rs-onboarding-v8-{STAMP}' / REL


def fail(msg):
    raise RuntimeError(msg)


def replace_once(text, old, new, label):
    if new in text:
        return text
    count = text.count(old)
    if count != 1:
        fail(f'{label}: expected exactly 1 anchor, found {count}')
    return text.replace(old, new, 1)


def main():
    if not TARGET.exists():
        fail(f'Missing {REL}')

    BACKUP.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(TARGET, BACKUP)
    s = TARGET.read_text()

    s = replace_once(
        s,
        '$generatorVersion = 7;',
        '$generatorVersion = 8;',
        'generator version',
    )

    old_prepare = '''    $callbackUrl = rtrim((string)APP_URL, '/') . '/?_route=plugin/rs_radius_wireguard_activate';
    $script = rs_wg_build_routeros_script(
        (string)$router['name'],
        $tunnelIp,
        $apiUser,
        $apiPass,
        $activationToken,
        $callbackUrl,
        $wg,
        [
            'host' => $wg['server_ip'],
            'auth_port' => 1812,
            'accounting_port' => 1813,
            'coa_port' => 3799,
        ],
        $radiusSecret
    );
'''
    new_prepare = '''    $callbackUrl = rtrim((string)APP_URL, '/') . '/?_route=plugin/rs_radius_wireguard_activate';

    // Build once here as a validation step, but do NOT paste the full installer
    // into Winbox.  Large multi-line terminal pastes can crash/reconnect Winbox
    // and leave onboarding half-applied.  The router instead fetches this exact
    // installer through a one-time activation-token URL and runs it in a
    // background RouterOS job.
    rs_wg_build_routeros_script(
        (string)$router['name'],
        $tunnelIp,
        $apiUser,
        $apiPass,
        $activationToken,
        $callbackUrl,
        $wg,
        [
            'host' => $wg['server_ip'],
            'auth_port' => 1812,
            'accounting_port' => 1813,
            'coa_port' => 3799,
        ],
        $radiusSecret
    );

    $bootstrapUrl = rtrim((string)APP_URL, '/')
        . '/?_route=plugin/rs_radius_wireguard_bootstrap&token='
        . rawurlencode($activationToken);
    $script = rs_wg_build_routeros_bootstrap($bootstrapUrl);
'''
    s = replace_once(s, old_prepare, new_prepare, 'prepare router script')

    s = s.replace(
        "'message' => 'WireGuard, RouterOS API and FreeRADIUS are prepared. Paste the single script into the MikroTik.',",
        "'message' => 'WireGuard, RouterOS API and FreeRADIUS are prepared. Paste the short bootstrap into MikroTik; the full installer runs in the background.',",
    )

    bootstrap_fn = r'''function rs_wg_build_routeros_bootstrap($bootstrapUrl)
{
    $q = function ($value) {
        return str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '', ' '], (string)$value);
    };

    $bootstrapUrl = trim((string)$bootstrapUrl);
    if (!preg_match('#^https?://#i', $bootstrapUrl)) {
        throw new RuntimeException('The RouterOS bootstrap URL is invalid.');
    }

    $file = 'rs-radius-onboard.rsc';
    $lines = [
        '# RS safe WireGuard + RADIUS bootstrap v8',
        ':put "RS: downloading one-time installer...";',
        ':do { /file remove [find where name="' . $file . '"]; } on-error={};',
        ':do { /tool/fetch url="' . $q($bootstrapUrl) . '" dst-path="' . $file . '" keep-result=yes check-certificate=no; } on-error={ :error "RS stopped: installer download failed."; };',
        ':if ([:len [/file find where name="' . $file . '"]] = 0) do={ :error "RS stopped: installer file was not created."; };',
        ':put "RS: installer downloaded. Starting background onboarding...";',
        ':execute {/import file-name="' . $file . '"};',
        ':put "RS: onboarding started in background. You may close this terminal.";',
    ];
    return implode("\n", $lines);
}

'''
    marker = 'function rs_wg_build_routeros_script($routerName, $tunnelIp, $apiUser, $apiPass, $activationToken, $callbackUrl, array $wireguard, array $radius, $sharedSecret)\n{\n'
    if 'function rs_wg_build_routeros_bootstrap($bootstrapUrl)' not in s:
        if marker not in s:
            fail('bootstrap insertion marker not found')
        s = s.replace(marker, bootstrap_fn + marker, 1)

    s = s.replace(
        "'# RS WireGuard + RouterOS API + FreeRADIUS onboarding v7',",
        "'# RS WireGuard + RouterOS API + FreeRADIUS onboarding v8',",
    )

    cleanup_old = "        '    :log info \"RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE\";',\n"
    cleanup_new = "        '    :do { /file remove [find where name=\"rs-radius-onboard.rsc\"]; } on-error={};',\n        '    :log info \"RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE\";',\n"
    if cleanup_new not in s:
        if cleanup_old not in s:
            fail('installer cleanup marker not found')
        s = s.replace(cleanup_old, cleanup_new, 1)

    endpoint = r'''/**
 * Public one-time installer download used by the short RouterOS bootstrap.
 * The activation token is random, expires quickly, and is invalidated as soon
 * as the WireGuard peer activation callback succeeds.
 */
function rs_radius_wireguard_bootstrap()
{
    rs_wg_ensure_schema();
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="rs-radius-onboard.rsc"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo ':error "RS bootstrap requires GET";';
        exit;
    }

    try {
        $token = trim((string)($_GET['token'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $token)) {
            throw new RuntimeException('Invalid bootstrap token.');
        }

        $router = ORM::for_table('tbl_routers')
            ->where('wg_activation_token_hash', hash('sha256', $token))
            ->find_one();
        if (!$router) {
            throw new RuntimeException('Bootstrap token is invalid or expired.');
        }

        $expires = trim((string)($router['wg_activation_expires_at'] ?? ''));
        if ($expires === '' || strtotime($expires) === false || strtotime($expires) < time()) {
            throw new RuntimeException('Bootstrap token is invalid or expired.');
        }

        $tunnelIp = trim((string)($router['wg_tunnel_ip'] ?? ''));
        $apiUser = trim((string)($router['username'] ?? ''));
        $apiPass = (string)($router['password'] ?? '');
        if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $apiUser === '' || $apiPass === '') {
            throw new RuntimeException('Router bootstrap data is incomplete.');
        }

        $shortName = rs_wg_short_name((int)$router->id(), (string)$router['name']);
        $radiusDb = ORM::get_db('radius');
        $statement = $radiusDb->prepare('SELECT secret FROM nas WHERE shortname = ? AND nasname = ? LIMIT 1');
        $statement->execute([$shortName, $tunnelIp]);
        $radiusSecret = trim((string)$statement->fetchColumn());
        if (!preg_match('/^[A-Za-z0-9_-]{20,128}$/', $radiusSecret)) {
            throw new RuntimeException('RADIUS bootstrap secret is unavailable.');
        }

        $wg = RSWireguardControlPlane::publicConfig();
        $callbackUrl = rtrim((string)APP_URL, '/') . '/?_route=plugin/rs_radius_wireguard_activate';
        $script = rs_wg_build_routeros_script(
            (string)$router['name'],
            $tunnelIp,
            $apiUser,
            $apiPass,
            $token,
            $callbackUrl,
            $wg,
            [
                'host' => $wg['server_ip'],
                'auth_port' => 1812,
                'accounting_port' => 1813,
                'coa_port' => 3799,
            ],
            $radiusSecret
        );

        echo $script;
    } catch (Throwable $e) {
        error_log('RS WireGuard bootstrap download failed: ' . $e->getMessage());
        http_response_code(422);
        echo ':error "RS bootstrap unavailable or expired";';
    }
    exit;
}

'''
    endpoint_marker = '/** Public, one-time callback from the generated RouterOS script. */\nfunction rs_radius_wireguard_activate()\n'
    if 'function rs_radius_wireguard_bootstrap()' not in s:
        if endpoint_marker not in s:
            fail('bootstrap endpoint insertion marker not found')
        s = s.replace(endpoint_marker, endpoint + endpoint_marker, 1)

    TARGET.write_text(s)
    print('SUCCESS: billing now generates a short Winbox-safe bootstrap and serves the full installer one-time.')
    print('Backup:', BACKUP)
    print('Changed:', REL)


if __name__ == '__main__':
    main()
