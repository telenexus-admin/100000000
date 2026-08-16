#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import shutil

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime('%Y%m%d-%H%M%S')
BACKUP = Path('/var/backups') / f'prm-radius-authoritative-finish-{STAMP}'


def fail(msg):
    raise RuntimeError(msg)


def backup(rel):
    src = ROOT / rel
    if not src.exists():
        fail(f'Missing required file: {rel}')
    dst = BACKUP / rel
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(src, dst)


def patch_download():
    rel = 'download.php'
    p = ROOT / rel
    s = p.read_text()

    start = '$htmlContent .= "    function apiAutologinThenBrowse(account, password) {\\n";\n'
    end = '$htmlContent .= "    function isMikroTikVar(v) {\\n";\n'
    marker = 'RS/WireGuard Hotspot authentication is RADIUS-authoritative.'

    if marker in s:
        print('download.php: already converted')
        return

    i = s.find(start)
    if i < 0:
        fail('download.php: generated apiAutologinThenBrowse start marker not found')
    j = s.find(end, i)
    if j < 0:
        fail('download.php: generated isMikroTikVar end marker not found')

    replacement = (
        '$htmlContent .= "    function apiAutologinThenBrowse(account, password) {\\n";\n'
        '$htmlContent .= "        // RS/WireGuard Hotspot authentication is RADIUS-authoritative.\\n";\n'
        '$htmlContent .= "        // Use the normal MikroTik /login form so MikroTik sends the Access-Request.\\n";\n'
        '$htmlContent .= "        return Promise.resolve(false);\\n";\n'
        '$htmlContent .= "    }\\n";\n'
        '$htmlContent .= "\\n";\n'
    )
    s = s[:i] + replacement + s[j:]
    p.write_text(s)
    print('download.php: converted to browser Hotspot login / RADIUS authentication')


def patch_onboarding():
    rel = 'system/plugin/rs_radius_wireguard_onboarding.php'
    p = ROOT / rel
    s = p.read_text()

    if '$generatorVersion = 6;' in s:
        s = s.replace('$generatorVersion = 6;', '$generatorVersion = 7;', 1)
    elif '$generatorVersion = 7;' not in s:
        fail('onboarding: generatorVersion anchor not found')

    if '# RS WireGuard + RouterOS API + FreeRADIUS onboarding v6' in s:
        s = s.replace(
            '# RS WireGuard + RouterOS API + FreeRADIUS onboarding v6',
            '# RS WireGuard + RouterOS API + FreeRADIUS onboarding v7',
            1,
        )

    old = "        '    :if ([:len [/ip/hotspot/profile find]] > 0) do={ /ip/hotspot/profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received; };',"
    new = "        '    :if ([:len [/ip/hotspot/profile find]] > 0) do={ /ip/hotspot/profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received login-by=http-pap,http-chap,cookie; };',"

    if new not in s:
        if old not in s:
            fail('onboarding: Hotspot RADIUS profile line not found')
        s = s.replace(old, new, 1)

    p.write_text(s)
    print('rs_radius_wireguard_onboarding.php: future routers set to RADIUS Hotspot authentication')


def main():
    BACKUP.mkdir(parents=True, exist_ok=False)
    for rel in ['download.php', 'system/plugin/rs_radius_wireguard_onboarding.php']:
        backup(rel)

    patch_download()
    patch_onboarding()

    print('SUCCESS: finished the RADIUS-authoritative conversion.')
    print(f'Backups: {BACKUP}')


if __name__ == '__main__':
    main()
