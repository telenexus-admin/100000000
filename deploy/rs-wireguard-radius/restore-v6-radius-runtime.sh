#!/usr/bin/env bash
set -Eeuo pipefail

FREERADIUS_BIN="$(command -v freeradius || command -v radiusd || true)"
FR_DIR="${RS_FREERADIUS_DIR:-/etc/freeradius/3.0}"
DEFAULT_SITE="$FR_DIR/sites-available/default"
SQL_MODULE="$FR_DIR/mods-available/sql"
RS_IP="${RS_RADIUS_IP:-10.78.0.1}"
RS_WG_CIDR="${RS_WG_CIDR:-10.78.0.0/24}"
HELPER_SOURCE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/rs-radius-manage"
HELPER_TARGET="/usr/local/bin/rs-radius-manage"
DEDICATED_SERVICE="freeradius-rs"
BACKUP_DIR="/root/rs-v6-radius-restore-$(date +%F-%H%M%S)"

fail() { echo "ERROR: $*" >&2; exit 1; }
step() { echo; echo "===== $* ====="; }

[[ "$(id -u)" -eq 0 ]] || fail "Run as root."
[[ -n "$FREERADIUS_BIN" ]] || fail "FreeRADIUS is not installed."
[[ -f "$DEFAULT_SITE" ]] || fail "Missing $DEFAULT_SITE"
[[ -f "$SQL_MODULE" ]] || fail "Missing $SQL_MODULE"
[[ -f "$HELPER_SOURCE" ]] || fail "Missing $HELPER_SOURCE"
ip -o -4 addr show dev wg-rs | grep -q " ${RS_IP}/" || fail "wg-rs does not own $RS_IP"

install -d -m 700 "$BACKUP_DIR"
cp -a "$DEFAULT_SITE" "$BACKUP_DIR/default.before"
cp -a "$SQL_MODULE" "$BACKUP_DIR/sql.before"
[[ ! -f "$HELPER_TARGET" ]] || cp -a "$HELPER_TARGET" "$BACKUP_DIR/rs-radius-manage.before"
[[ ! -f "/etc/systemd/system/${DEDICATED_SERVICE}.service" ]] || cp -a "/etc/systemd/system/${DEDICATED_SERVICE}.service" "$BACKUP_DIR/${DEDICATED_SERVICE}.service.before"
DEDICATED_WAS_ACTIVE=0
systemctl is-active --quiet "$DEDICATED_SERVICE" 2>/dev/null && DEDICATED_WAS_ACTIVE=1 || true

echo "Backup: $BACKUP_DIR"

step "RESTORE V6 LISTENERS + SQL DYNAMIC NAS"
export DEFAULT_SITE SQL_MODULE RS_IP
python3 <<'PY'
import os, re
from pathlib import Path
site = Path(os.environ['DEFAULT_SITE'])
ip = os.environ['RS_IP']
text = site.read_text()

# Remove only RS-managed listener blocks and duplicate simple listeners for the
# same 10.78.0.1 auth/accounting sockets. Preserve all other tunnels/listeners.
for start, end in [
    ('# RS_WIREGUARD_RADIUS_START', '# RS_WIREGUARD_RADIUS_END'),
    ('# RS_V6_RADIUS_START', '# RS_V6_RADIUS_END'),
]:
    text = re.sub(re.escape(start) + r'.*?' + re.escape(end) + r'[ \t]*(?:\r?\n)?', '', text, flags=re.S)

for port in ('1812','1813'):
    pat = re.compile(
        r'(?ms)^[ \t]*listen[ \t]*\{'
        r'(?=[^{}]*^[ \t]*ipaddr[ \t]*=[ \t]*"?' + re.escape(ip) + r'"?[ \t]*$)'
        r'(?=[^{}]*^[ \t]*port[ \t]*=[ \t]*' + port + r'[ \t]*$)'
        r'[^{}]*^[ \t]*\}[ \t]*(?:\r?\n)?'
    )
    text = pat.sub('', text)

managed = f'''# RS_V6_RADIUS_START
listen {{
    type = auth
    ipaddr = {ip}
    port = 1812
}}

listen {{
    type = acct
    ipaddr = {ip}
    port = 1813
}}
# RS_V6_RADIUS_END
'''
insert = re.search(r'(?m)^[ \t]*authorize[ \t]*\{', text)
if not insert:
    raise SystemExit('Unable to locate authorize{} in default site')
text = text[:insert.start()] + managed + '\n' + text[insert.start():]
site.write_text(text)

sql = Path(os.environ['SQL_MODULE'])
sql_text = sql.read_text()
sql_text, n1 = re.subn(
    r'(?m)^([ \t]*)driver[ \t]*=[ \t]*"rlm_sql_[^"]+"[ \t]*$',
    lambda m: f'{m.group(1)}driver = "rlm_sql_mysql"', sql_text, count=1)
sql_text, n2 = re.subn(
    r'(?m)^([ \t]*)#?[ \t]*read_clients[ \t]*=[ \t]*(?:yes|no|true|false).*$' ,
    lambda m: f'{m.group(1)}read_clients = yes', sql_text, count=1)
if n1 != 1:
    raise SystemExit('Unable to locate SQL driver setting')
if n2 != 1:
    raise SystemExit('Unable to locate read_clients setting')
sql.write_text(sql_text)
PY

chown root:freerad "$SQL_MODULE" 2>/dev/null || true
chmod 640 "$SQL_MODULE"
runuser -u freerad -- test -r "$SQL_MODULE" || fail "freerad cannot read SQL module"

echo "v6 listener + SQL dynamic NAS configuration prepared."

step "VALIDATE DEFAULT FREERADIUS BEFORE CUTOVER"
if ! timeout 30s "$FREERADIUS_BIN" -XC >"$BACKUP_DIR/default-config-check.log" 2>&1; then
  tail -n 180 "$BACKUP_DIR/default-config-check.log" >&2 || true
  cp -a "$BACKUP_DIR/default.before" "$DEFAULT_SITE"
  cp -a "$BACKUP_DIR/sql.before" "$SQL_MODULE"
  fail "Default FreeRADIUS validation failed; original files restored and no service was stopped."
fi
grep -q 'Driver rlm_sql_mysql' "$BACKUP_DIR/default-config-check.log" || fail "MySQL RADIUS driver was not loaded."
echo "Default FreeRADIUS validation: OK"

step "INSTALL V6-COMPATIBLE MANAGEMENT HELPER"
install -o root -g www-data -m 750 "$HELPER_SOURCE" "$HELPER_TARGET"
echo "Installed: $HELPER_TARGET"

step "CUT OVER FROM DEDICATED DRIFT TO SYSTEM FREERADIUS"
timeout 12s systemctl stop "$DEDICATED_SERVICE" 2>/dev/null || true
systemctl disable "$DEDICATED_SERVICE" >/dev/null 2>&1 || true

# Kill only an orphan explicitly launched with /etc/freeradius-rs. Never kill
# the normal /etc/freeradius/3.0 process.
mapfile -t stray_pids < <(ps -eo pid=,args= | awk 'index($0,"freeradius") && index($0,"-d /etc/freeradius-rs") {print $1}')
if ((${#stray_pids[@]})); then
  kill -TERM "${stray_pids[@]}" 2>/dev/null || true
  sleep 1
fi

systemctl enable freeradius >/dev/null
if ! timeout 25s systemctl restart freeradius; then
  echo "System FreeRADIUS failed to start; rolling back runtime." >&2
  cp -a "$BACKUP_DIR/default.before" "$DEFAULT_SITE"
  cp -a "$BACKUP_DIR/sql.before" "$SQL_MODULE"
  systemctl restart freeradius >/dev/null 2>&1 || true
  if [[ "$DEDICATED_WAS_ACTIVE" -eq 1 ]]; then
    systemctl enable "$DEDICATED_SERVICE" >/dev/null 2>&1 || true
    systemctl start "$DEDICATED_SERVICE" >/dev/null 2>&1 || true
  fi
  journalctl -u freeradius -n 160 --no-pager >&2 || true
  fail "v6 runtime cutover failed and rollback was attempted."
fi
sleep 2
systemctl is-active --quiet freeradius || fail "freeradius did not remain active"

step "VERIFY V6 PRIVATE RADIUS SOCKETS"
for port in 1812 1813; do
  count="$(ss -H -lunp 2>/dev/null | awk -v ep="${RS_IP}:${port}" '$4 == ep {n++} END {print n+0}')"
  [[ "$count" == "1" ]] || {
    ss -lunp | grep -E ':1812|:1813' >&2 || true
    fail "Expected exactly one ${RS_IP}:${port} listener, found $count"
  }
done
if ss -H -lunp 2>/dev/null | awk '$4 ~ /^(0\.0\.0\.0|\*|\[::\]):(1812|1813)$/ {bad=1} END {exit bad?0:1}'; then
  fail "Public RADIUS listener detected"
fi

echo "10.78.0.1:1812/1813 are owned by the v6-compatible system FreeRADIUS runtime."

step "FINAL HEALTH CHECK"
timeout 30s "$HELPER_TARGET" check

echo
echo "RS V6 RADIUS RUNTIME RESTORED"
echo "service=freeradius"
echo "config=/etc/freeradius/3.0"
echo "dynamic_nas=SQL read_clients=yes"
echo "auth=${RS_IP}:1812/udp"
echo "acct=${RS_IP}:1813/udp"
echo "dedicated_freeradius_rs=disabled"
echo "backup=$BACKUP_DIR"
