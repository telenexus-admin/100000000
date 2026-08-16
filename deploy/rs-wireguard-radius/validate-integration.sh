#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN="$ROOT/system/plugin/rs_radius_wireguard_onboarding.php"
CLASS="$ROOT/system/autoload/RSWireguardControlPlane.php"
CONFIGURATOR="$ROOT/system/plugin/mikrotik_configurator.php"
POLLING="$ROOT/system/plugin/ui/rs_radius_wireguard_polling.tpl"

for f in "$PLUGIN" "$CLASS" "$CONFIGURATOR" "$ROOT/init.php" "$ROOT/deploy/rs-wireguard-radius/check-app-radius.php" "$ROOT/deploy/rs-wireguard-radius/test-generator.php"; do
  php -l "$f" >/dev/null
  echo "PHP OK: ${f#$ROOT/}"
done
for f in "$ROOT/deploy/rs-wireguard-radius/rs-wireguard-manage" "$ROOT/deploy/rs-wireguard-radius/rs-radius-manage" "$ROOT/deploy/rs-wireguard-radius/install-wireguard-radius.sh" "$ROOT/deploy/rs-wireguard-radius/verify-live.sh"; do
  bash -n "$f"
  echo "Shell OK: ${f#$ROOT/}"
done

require() { grep -Fq -- "$2" "$1" || { echo "Missing invariant: $2" >&2; exit 1; }; }
for invariant in \
  '# RS WireGuard + RouterOS API + FreeRADIUS onboarding v6' \
  'output=user as-value check-certificate=no' \
  'current-endpoint-address' \
  'place-before=$rsDefaultInputDrop' \
  'service=hotspot,ppp' \
  '/radius incoming set accept=yes port=' \
  'RS-WIREGUARD-RADIUS-ONBOARDING-COMPLETE'; do
  require "$PLUGIN" "$invariant"
done
require "$PLUGIN" 'wg_activation_token_hash'
require "$PLUGIN" 'rs_wireguard_allocations'
require "$PLUGIN" 'rs_wg_upsert_nas'
require "$PLUGIN" 'management_transport'
require "$POLLING" 'mikrotik_configurator_config_ui'
require "$CONFIGURATOR" '$wireguardManaged ? '\''radius'\'''

if grep -Fq '/certificate/settings' "$PLUGIN"; then
  echo "Unsupported RouterOS CA-store command found." >&2; exit 1
fi
if grep -Eq '/ip/firewall/filter[[:space:]]+move' "$PLUGIN"; then
  echo "Unsafe RouterOS built-in firewall move found." >&2; exit 1
fi

php "$ROOT/deploy/rs-wireguard-radius/test-generator.php"
echo "Static onboarding invariants: OK"
echo "RS WireGuard/RADIUS clone validation: PASS"
