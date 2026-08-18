#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
FREERADIUS_BIN="$(command -v freeradius || command -v radiusd || true)"
RS_CONFIG_DIR="${RS_FREERADIUS_DIR:-/etc/freeradius-rs}"
RS_SERVICE="${RS_FREERADIUS_SERVICE:-freeradius-rs}"
RS_IP="${RS_RADIUS_IP:-10.78.0.1}"
WG_INTERFACE="${RS_WG_INTERFACE:-wg-rs}"
HELPER_SOURCE="$SCRIPT_DIR/rs-radius-manage"
HELPER_TARGET="/usr/local/bin/rs-radius-manage"
UNIT_PATH="/etc/systemd/system/${RS_SERVICE}.service"
BACKUP_DIR="/root/rs-radius-runtime-repair-$(date +%F-%H%M%S)"

fail() { echo "ERROR: $*" >&2; exit 1; }

[[ "$(id -u)" -eq 0 ]] || fail "Run this repair as root."
[[ -n "$FREERADIUS_BIN" ]] || fail "FreeRADIUS is not installed."
[[ -d "$RS_CONFIG_DIR" ]] || fail "Dedicated RS FreeRADIUS config not found: $RS_CONFIG_DIR"
[[ -f "$RS_CONFIG_DIR/radiusd.conf" ]] || fail "Missing $RS_CONFIG_DIR/radiusd.conf"
[[ -f "$HELPER_SOURCE" ]] || fail "Missing helper source: $HELPER_SOURCE"
ip link show "$WG_INTERFACE" >/dev/null 2>&1 || fail "WireGuard interface $WG_INTERFACE is missing."
ip -o -4 addr show dev "$WG_INTERFACE" | grep -q " ${RS_IP}/" || fail "$WG_INTERFACE does not own $RS_IP."

install -d -m 700 "$BACKUP_DIR"
[[ ! -f "$HELPER_TARGET" ]] || cp -a "$HELPER_TARGET" "$BACKUP_DIR/rs-radius-manage.before"
[[ ! -f "$UNIT_PATH" ]] || cp -a "$UNIT_PATH" "$BACKUP_DIR/${RS_SERVICE}.service.before"

echo "Backup: $BACKUP_DIR"

echo "Validating dedicated RS FreeRADIUS configuration..."
if ! "$FREERADIUS_BIN" -XC -d "$RS_CONFIG_DIR" >"$BACKUP_DIR/config-check.log" 2>&1; then
  tail -n 160 "$BACKUP_DIR/config-check.log" >&2
  fail "Dedicated FreeRADIUS configuration validation failed; nothing was stopped."
fi
echo "Configuration validation: OK"

install -o root -g www-data -m 750 "$HELPER_SOURCE" "$HELPER_TARGET"

cat >"$UNIT_PATH" <<EOF
[Unit]
Description=RS WireGuard FreeRADIUS instance
After=network-online.target wg-quick@${WG_INTERFACE}.service mariadb.service mysql.service
Wants=network-online.target wg-quick@${WG_INTERFACE}.service

[Service]
Type=simple
ExecStart=${FREERADIUS_BIN} -f -d ${RS_CONFIG_DIR}
Restart=on-failure
RestartSec=2
TimeoutStopSec=15

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload

# Stop a previously-created unit first, then terminate only orphaned processes
# that explicitly use the dedicated RS config directory.  The normal system
# FreeRADIUS instance is intentionally left untouched.
systemctl stop "$RS_SERVICE" 2>/dev/null || true

mapfile -t stray_pids < <(
  ps -eo pid=,args= | awk -v dir="$RS_CONFIG_DIR" '
    index($0, "freeradius") && index($0, "-d " dir) {print $1}
  '
)
if ((${#stray_pids[@]})); then
  echo "Stopping orphaned dedicated RS FreeRADIUS PID(s): ${stray_pids[*]}"
  kill -TERM "${stray_pids[@]}" 2>/dev/null || true
  for _ in {1..30}; do
    alive=0
    for pid in "${stray_pids[@]}"; do
      kill -0 "$pid" 2>/dev/null && alive=1 || true
    done
    [[ "$alive" -eq 0 ]] && break
    sleep 0.2
  done
  for pid in "${stray_pids[@]}"; do
    if kill -0 "$pid" 2>/dev/null; then
      echo "PID $pid did not stop after SIGTERM; sending SIGKILL."
      kill -KILL "$pid" 2>/dev/null || true
    fi
  done
fi

install -d -o freerad -g freerad -m 755 /run/freeradius 2>/dev/null || true
systemctl enable "$RS_SERVICE" >/dev/null
systemctl start "$RS_SERVICE"
sleep 2

if ! systemctl is-active --quiet "$RS_SERVICE"; then
  journalctl -u "$RS_SERVICE" -n 160 --no-pager >&2 || true
  fail "$RS_SERVICE did not start."
fi

for port in 1812 1813; do
  count="$(ss -H -lunp 2>/dev/null | awk -v ep="${RS_IP}:${port}" '$4 == ep {n++} END {print n+0}')"
  [[ "$count" == "1" ]] || {
    ss -lunp | grep -E ':1812|:1813' >&2 || true
    fail "Expected exactly one ${RS_IP}:${port} listener, found $count."
  }
done

if ss -H -lunp 2>/dev/null | awk '$4 ~ /^(0\.0\.0\.0|\*|\[::\]):(1812|1813)$/ {found=1} END {exit found?0:1}'; then
  fail "Public RADIUS listener detected."
fi

"$HELPER_TARGET" check

echo
echo "RS RADIUS RUNTIME REPAIRED"
echo "service=${RS_SERVICE}"
echo "config=${RS_CONFIG_DIR}"
echo "auth=${RS_IP}:1812/udp"
echo "acct=${RS_IP}:1813/udp"
echo "helper=${HELPER_TARGET}"
echo "New SQL NAS rows will now reload this dedicated RS instance."
