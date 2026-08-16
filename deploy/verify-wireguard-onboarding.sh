#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="${1:-live}"

php -l "$APP_ROOT/system/autoload/WireguardControlPlane.php" >/dev/null
php -l "$APP_ROOT/system/plugin/radius_wireguard_bridge.php" >/dev/null
php -l "$APP_ROOT/system/plugin/radius_wireguard_onboarding.php" >/dev/null
php -l "$APP_ROOT/system/plugin/mikrotik_configurator.php" >/dev/null
php "$APP_ROOT/tools/validate_wireguard_onboarding.php"
bash -n "$APP_ROOT/deploy/configure-radius-wireguard.sh"
bash -n "$APP_ROOT/deploy/nuxhost-wireguard-manage"
bash -n "$APP_ROOT/deploy/nuxhost-radius-manage"

echo "Application/static validation: OK"

if [[ "$MODE" == "--code-only" || "$MODE" == "code-only" ]]; then
    exit 0
fi

[[ -r /etc/nuxhost/wireguard.ini ]] || { echo "Missing /etc/nuxhost/wireguard.ini" >&2; exit 1; }
[[ -x /usr/local/bin/nuxhost-wireguard-manage ]] || { echo "Missing WireGuard helper" >&2; exit 1; }
[[ -x /usr/local/bin/nuxhost-radius-manage ]] || { echo "Missing RADIUS helper" >&2; exit 1; }

sudo -n /usr/local/bin/nuxhost-wireguard-manage check
sudo -n /usr/local/bin/nuxhost-radius-manage check

echo "Live WireGuard + FreeRADIUS control-plane validation: OK"
