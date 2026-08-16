#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
SAFE_CONFIG="${RS_WIREGUARD_CONFIG:-/etc/rs-radius/wireguard.ini}"
FR_DIR="${RS_FREERADIUS_DIR:-/etc/freeradius/3.0}"
SQL_MODULE="$FR_DIR/mods-available/sql"

[[ -r "$SAFE_CONFIG" ]] || { echo "Missing WireGuard public config: $SAFE_CONFIG" >&2; exit 1; }
read_value() { awk -F= -v k="$1" '$1==k {sub(/^[^=]*=/, ""); gsub(/^[ \t]+|[ \t]+$/, ""); print; exit}' "$SAFE_CONFIG"; }
WG_INTERFACE="$(read_value interface)"
WG_SERVER_IP="$(read_value server_ip)"
WG_CIDR="$(read_value cidr)"

bash "$ROOT/deploy/rs-wireguard-radius/validate-integration.sh"

echo "--- Live server checks ---"
wg show "$WG_INTERFACE" >/dev/null
echo "WireGuard interface: active ($WG_INTERFACE)"
/usr/local/bin/rs-wireguard-manage check >/dev/null
echo "WireGuard helper: OK"
/usr/local/bin/rs-radius-manage check >/dev/null
echo "FreeRADIUS helper/config: OK"

for port in 1812 1813; do
  count="$(ss -H -lunp 2>/dev/null | awk -v ep="${WG_SERVER_IP}:${port}" '$4==ep {n++} END{print n+0}')"
  [[ "$count" == "1" ]] || { echo "Expected one ${WG_SERVER_IP}:${port} listener, found $count" >&2; exit 1; }
done
echo "Private RADIUS listeners: OK"

if ss -H -lunp 2>/dev/null | awk '$4 ~ /^(0\.0\.0\.0|\*|\[::\]):(1812|1813)$/ {f=1} END{exit f?0:1}'; then
  echo "Unsafe public RADIUS listener detected." >&2
  exit 1
fi
echo "Public RADIUS listeners: none"

APP_JSON="$(php "$ROOT/deploy/rs-wireguard-radius/check-app-radius.php")"
export APP_JSON
python3 <<'PY'
import json, os, sys
obj=json.loads(os.environ['APP_JSON'])
if not obj.get('ok'):
    raise SystemExit('Billing RADIUS database check failed: ' + str(obj.get('error','unknown error')))
if not obj.get('nas_table'):
    raise SystemExit('Billing RADIUS NAS table is unavailable.')
print('Billing RADIUS DB: OK (' + str(obj.get('database','')) + ')')
print('Billing NAS table: OK')
PY

# Compare database names when the FreeRADIUS sql module uses a literal database value.
APP_DB="$(python3 -c 'import json,os; print(json.loads(os.environ["APP_JSON"]).get("database",""))')"
FR_DB="$(awk -F= '/^[[:space:]]*database[[:space:]]*=/{v=$2; gsub(/^[[:space:]\"]+|[[:space:]\"]+$/,"",v); print v; exit}' "$SQL_MODULE")"
if [[ -n "$FR_DB" && "$FR_DB" != *'${'* && -n "$APP_DB" ]]; then
  [[ "$FR_DB" == "$APP_DB" ]] || {
    echo "Database mismatch: billing RADIUS connection and FreeRADIUS SQL module point to different database names." >&2
    exit 1
  }
  echo "Billing/FreeRADIUS SQL database identity: MATCH"
else
  echo "Billing/FreeRADIUS SQL database identity: dynamic setting; manual name comparison skipped"
fi

if sudo -u www-data sudo -n /usr/local/bin/rs-wireguard-manage check >/dev/null 2>&1; then
  echo "www-data WireGuard sudo boundary: OK"
else
  echo "www-data cannot invoke the WireGuard management helper through restricted sudo." >&2
  exit 1
fi
if sudo -u www-data sudo -n /usr/local/bin/rs-radius-manage check >/dev/null 2>&1; then
  echo "www-data RADIUS sudo boundary: OK"
else
  echo "www-data cannot invoke the RADIUS management helper through restricted sudo." >&2
  exit 1
fi

echo "RS LIVE WIREGUARD/RADIUS CONTROL PLANE: PASS"
