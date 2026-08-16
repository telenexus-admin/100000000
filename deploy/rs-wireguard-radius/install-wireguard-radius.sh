#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
FR_DIR="${RS_FREERADIUS_DIR:-/etc/freeradius/3.0}"
DEFAULT_SITE="$FR_DIR/sites-available/default"
SQL_MODULE="$FR_DIR/mods-available/sql"
WG_INTERFACE="${RS_WG_INTERFACE:-wg-rs}"
WG_SERVER_IP="${RS_WG_SERVER_IP:-10.78.0.1}"
WG_CIDR="${RS_WG_CIDR:-10.78.0.0/24}"
WG_ENDPOINT_PORT="${RS_WG_ENDPOINT_PORT:-51822}"
WG_CONF="/etc/wireguard/${WG_INTERFACE}.conf"
SAFE_DIR="/etc/rs-radius"
SAFE_CONFIG="${RS_WIREGUARD_CONFIG:-${SAFE_DIR}/wireguard.ini}"
PEER_HELPER_TARGET="/usr/local/bin/rs-wireguard-manage"
RADIUS_HELPER_TARGET="/usr/local/bin/rs-radius-manage"
BACKUP_DIR="/root/rs-wireguard-radius-backup-$(date +%F-%H%M%S)"

fail() { echo "ERROR: $*" >&2; exit 1; }
[[ "$(id -u)" -eq 0 ]] || fail "Run this installer as root."
[[ "$WG_INTERFACE" =~ ^[A-Za-z0-9_.-]{1,80}$ ]] || fail "Invalid WireGuard interface name."
[[ "$WG_SERVER_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "Invalid WireGuard server IP."
[[ "$WG_CIDR" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/[0-9]+$ ]] || fail "Invalid WireGuard CIDR."
[[ "$WG_ENDPOINT_PORT" =~ ^[0-9]+$ ]] || fail "Invalid WireGuard UDP port."

for source in "$SCRIPT_DIR/rs-wireguard-manage" "$SCRIPT_DIR/rs-radius-manage"; do
  [[ -f "$source" ]] || fail "Required helper missing: $source"
done

if ! command -v wg >/dev/null 2>&1; then
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y wireguard-tools
fi
if ! command -v freeradius >/dev/null 2>&1; then
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y freeradius freeradius-mysql
elif ! dpkg-query -W -f='${Status}' freeradius-mysql 2>/dev/null | grep -q '^install ok installed$'; then
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y freeradius-mysql
fi

[[ -f "$DEFAULT_SITE" ]] || fail "FreeRADIUS default site not found: $DEFAULT_SITE"
[[ -f "$SQL_MODULE" ]] || fail "FreeRADIUS SQL module not found: $SQL_MODULE"

install -d -m 700 "$BACKUP_DIR"
cp -a "$DEFAULT_SITE" "$BACKUP_DIR/default"
cp -a "$SQL_MODULE" "$BACKUP_DIR/sql"
[[ ! -f "$WG_CONF" ]] || cp -a "$WG_CONF" "$BACKUP_DIR/${WG_INTERFACE}.conf"
echo "Backup created: $BACKUP_DIR"

install -d -m 700 /etc/wireguard
if [[ ! -f "$WG_CONF" ]]; then
  PRIVATE_KEY_FILE="/etc/wireguard/${WG_INTERFACE}-private.key"
  PUBLIC_KEY_FILE="/etc/wireguard/${WG_INTERFACE}-public.key"
  umask 077
  wg genkey >"$PRIVATE_KEY_FILE"
  wg pubkey <"$PRIVATE_KEY_FILE" >"$PUBLIC_KEY_FILE"
  WG_PREFIX="${WG_CIDR#*/}"
  cat >"$WG_CONF" <<EOF
[Interface]
Address = ${WG_SERVER_IP}/${WG_PREFIX}
ListenPort = ${WG_ENDPOINT_PORT}
PrivateKey = $(cat "$PRIVATE_KEY_FILE")
SaveConfig = false
EOF
  chmod 600 "$WG_CONF" "$PRIVATE_KEY_FILE" "$PUBLIC_KEY_FILE"
fi

systemctl enable "wg-quick@${WG_INTERFACE}" >/dev/null
if ! systemctl is-active --quiet "wg-quick@${WG_INTERFACE}"; then
  systemctl start "wg-quick@${WG_INTERFACE}"
fi
ip -o -4 addr show dev "$WG_INTERFACE" | grep -q " ${WG_SERVER_IP}/" || fail "$WG_INTERFACE does not own $WG_SERVER_IP."

WG_PUBLIC_KEY="$(wg show "$WG_INTERFACE" public-key)"
[[ "$WG_PUBLIC_KEY" =~ ^[A-Za-z0-9+/]{43}=$ ]] || fail "Could not read WireGuard public key."
WG_ENDPOINT="${RS_WG_ENDPOINT:-$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}')}"
[[ "$WG_ENDPOINT" =~ ^[A-Za-z0-9.:-]+$ ]] || fail "Unable to determine public WireGuard endpoint. Set RS_WG_ENDPOINT."

install -d -m 755 "$SAFE_DIR"
cat >"$SAFE_CONFIG" <<EOF
interface=${WG_INTERFACE}
server_ip=${WG_SERVER_IP}
cidr=${WG_CIDR}
endpoint=${WG_ENDPOINT}
endpoint_port=${WG_ENDPOINT_PORT}
public_key=${WG_PUBLIC_KEY}
EOF
chmod 644 "$SAFE_CONFIG"

install -o root -g www-data -m 750 "$SCRIPT_DIR/rs-wireguard-manage" "$PEER_HELPER_TARGET"
install -o root -g www-data -m 750 "$SCRIPT_DIR/rs-radius-manage" "$RADIUS_HELPER_TARGET"
cat >/etc/sudoers.d/rs-network-control <<EOF
www-data ALL=(root) NOPASSWD: ${PEER_HELPER_TARGET} *
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} reload
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} restart
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} check
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} status
EOF
chmod 440 /etc/sudoers.d/rs-network-control
visudo -cf /etc/sudoers.d/rs-network-control >/dev/null

# Keep the target management tunnel isolated, and never expose RADIUS on WAN.
sysctl -w net.ipv4.ip_forward=1 >/dev/null
cat >/etc/sysctl.d/99-rs-wireguard.conf <<EOF
net.ipv4.ip_forward=1
EOF

export DEFAULT_SITE SQL_MODULE WG_SERVER_IP
python3 <<'PY'
import os, re
from pathlib import Path
site = Path(os.environ['DEFAULT_SITE'])
ip = os.environ['WG_SERVER_IP']
text = site.read_text()
start = '# RS_WIREGUARD_RADIUS_START'
end = '# RS_WIREGUARD_RADIUS_END'
text = re.sub(re.escape(start) + r'.*?' + re.escape(end) + r'[ \t]*(?:\r?\n)?', '', text, flags=re.S)
# Remove older simple listeners for this exact IP/ports only; do not touch other tunnels.
for port in ('1812', '1813'):
    pattern = re.compile(
        r'(?ms)^[ \t]*listen[ \t]*\{'
        r'(?=[^{}]*^[ \t]*ipaddr[ \t]*=[ \t]*"?' + re.escape(ip) + r'"?[ \t]*$)'
        r'(?=[^{}]*^[ \t]*port[ \t]*=[ \t]*' + port + r'[ \t]*$)'
        r'[^{}]*^[ \t]*\}[ \t]*(?:\r?\n)?'
    )
    text = pattern.sub('', text)
managed = f'''{start}
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
{end}
'''
insert = re.search(r'(?m)^[ \t]*authorize[ \t]*\{', text)
if not insert:
    raise SystemExit('Unable to locate authorize{} in FreeRADIUS default site.')
text = text[:insert.start()] + managed + '\n' + text[insert.start():]
site.write_text(text)

sql = Path(os.environ['SQL_MODULE'])
sql_text = sql.read_text()
sql_text, count = re.subn(
    r'(?m)^([ \t]*)driver[ \t]*=[ \t]*"rlm_sql_[^"]+"[ \t]*$',
    lambda m: f'{m.group(1)}driver = "rlm_sql_mysql"', sql_text, count=1)
if count != 1:
    raise SystemExit('Unable to locate FreeRADIUS SQL driver setting.')
sql_text, count = re.subn(
    r'(?m)^([ \t]*)#?[ \t]*read_clients[ \t]*=[ \t]*(?:yes|no|true|false).*$' ,
    lambda m: f'{m.group(1)}read_clients = yes', sql_text, count=1)
if count != 1:
    raise SystemExit('Unable to locate FreeRADIUS read_clients setting.')

# Optional SQL credentials for a clean server. Values are taken from environment
# and are never printed. If omitted, preserve the existing working SQL settings.
values = {
    'server': os.environ.get('RS_RADIUS_DB_HOST'),
    'port': os.environ.get('RS_RADIUS_DB_PORT'),
    'login': os.environ.get('RS_RADIUS_DB_USER'),
    'password': os.environ.get('RS_RADIUS_DB_PASSWORD'),
    'database': os.environ.get('RS_RADIUS_DB_NAME'),
}
for key, value in values.items():
    if not value:
        continue
    quoted = value if key == 'port' else '"' + value.replace('\\', '\\\\').replace('"', '\\"') + '"'
    pattern = re.compile(r'(?m)^([ \t]*)' + re.escape(key) + r'[ \t]*=[ \t]*.*$')
    sql_text, n = pattern.subn(lambda m: f'{m.group(1)}{key} = {quoted}', sql_text, count=1)
    if n != 1:
        raise SystemExit(f'Unable to locate FreeRADIUS SQL {key} setting.')
sql.write_text(sql_text)
PY

chown root:freerad "$SQL_MODULE" 2>/dev/null || true
chmod 640 "$SQL_MODULE"
runuser -u freerad -- test -r "$SQL_MODULE" || fail "FreeRADIUS cannot read SQL module."

if ! freeradius -XC >/tmp/rs-radius-install-check.log 2>&1; then
  tail -n 120 /tmp/rs-radius-install-check.log >&2
  fail "FreeRADIUS configuration validation failed. Restore from $BACKUP_DIR if needed."
fi
grep -q 'Driver rlm_sql_mysql' /tmp/rs-radius-install-check.log || fail "FreeRADIUS MySQL driver was not loaded."
echo "FreeRADIUS syntax + SQL validation: OK"

systemctl enable freeradius >/dev/null
systemctl restart freeradius
sleep 2
systemctl is-active --quiet freeradius || { journalctl -u freeradius -n 120 --no-pager >&2; fail "FreeRADIUS did not start."; }
for port in 1812 1813; do
  count="$(ss -H -lunp 2>/dev/null | awk -v ep="${WG_SERVER_IP}:${port}" '$4 == ep {n++} END {print n+0}')"
  [[ "$count" == "1" ]] || fail "Expected exactly one live ${WG_SERVER_IP}:${port} socket, found $count."
done
if ss -H -lunp 2>/dev/null | awk '$4 ~ /^(0\.0\.0\.0|\*|\[::\]):(1812|1813)$/ {found=1} END {exit found?0:1}'; then
  fail "Public RADIUS listener detected. Refusing unsafe deployment."
fi
echo "FreeRADIUS live private socket binding: OK"

if command -v ufw >/dev/null 2>&1 && ufw status | grep -q '^Status: active'; then
  # Remove legacy public RADIUS rules, if any. RADIUS is reachable only inside WireGuard.
  while ufw status numbered | grep -Eq '[[:space:]]1812/udp[[:space:]].*ALLOW IN.*Anywhere'; do
    number="$(ufw status numbered | awk '/1812\/udp/ && /ALLOW IN/ && /Anywhere/ {gsub(/[][]/,"",$1); print $1; exit}')"
    [[ -n "$number" ]] || break
    yes | ufw delete "$number" >/dev/null 2>&1 || break
  done
  while ufw status numbered | grep -Eq '[[:space:]]1813/udp[[:space:]].*ALLOW IN.*Anywhere'; do
    number="$(ufw status numbered | awk '/1813\/udp/ && /ALLOW IN/ && /Anywhere/ {gsub(/[][]/,"",$1); print $1; exit}')"
    [[ -n "$number" ]] || break
    yes | ufw delete "$number" >/dev/null 2>&1 || break
  done
  ufw allow "${WG_ENDPOINT_PORT}/udp" comment 'RS WireGuard' >/dev/null
  ufw allow in on "$WG_INTERFACE" from "$WG_CIDR" to "$WG_SERVER_IP" port 1812 proto udp comment 'RS RADIUS AUTH WG' >/dev/null
  ufw allow in on "$WG_INTERFACE" from "$WG_CIDR" to "$WG_SERVER_IP" port 1813 proto udp comment 'RS RADIUS ACCT WG' >/dev/null
fi

"$PEER_HELPER_TARGET" check >/dev/null
"$RADIUS_HELPER_TARGET" check >/dev/null

echo
echo "RS WIREGUARD + FREERADIUS READY"
echo "WireGuard interface: $WG_INTERFACE"
echo "Management subnet: $WG_CIDR"
echo "RADIUS endpoint: $WG_SERVER_IP"
echo "WireGuard UDP port: $WG_ENDPOINT_PORT"
echo "RADIUS auth/accounting: UDP 1812/1813 over WireGuard only"
echo "Dynamic SQL NAS clients: enabled"
