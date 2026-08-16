#!/usr/bin/env bash
set -Eeuo pipefail

FR_DIR="${FREERADIUS_DIR:-/etc/freeradius/3.0}"
DEFAULT_SITE="$FR_DIR/sites-available/default"
SQL_MODULE="$FR_DIR/mods-available/sql"
WG_INTERFACE="${NUXHOST_WG_INTERFACE:-wg-nuxhost}"
WG_SERVER_IP="${NUXHOST_WG_SERVER_IP:-10.77.0.1}"
WG_CIDR="${NUXHOST_WG_CIDR:-10.77.0.0/24}"
WG_ENDPOINT_PORT="${NUXHOST_WG_ENDPOINT_PORT:-51821}"
RADIUS_AUTH_PORT="${NUXHOST_RADIUS_AUTH_PORT:-1812}"
RADIUS_ACCT_PORT="${NUXHOST_RADIUS_ACCT_PORT:-1813}"
RADIUS_COA_PORT="${NUXHOST_RADIUS_COA_PORT:-3799}"
WG_CONF="/etc/wireguard/${WG_INTERFACE}.conf"
SAFE_CONFIG="${NUXHOST_WIREGUARD_CONFIG:-/etc/nuxhost/wireguard.ini}"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="/root/radius-wireguard-onboarding-backup-$(date +%F-%H%M%S)"
PEER_HELPER_SOURCE="$SCRIPT_DIR/nuxhost-wireguard-manage"
PEER_HELPER_TARGET="/usr/local/bin/nuxhost-wireguard-manage"
RADIUS_HELPER_SOURCE="$SCRIPT_DIR/nuxhost-radius-manage"
RADIUS_HELPER_TARGET="/usr/local/bin/nuxhost-radius-manage"

fail() { echo "ERROR: $*" >&2; exit 1; }
[[ "$(id -u)" -eq 0 ]] || fail "Run this installer as root."

for file in "$DEFAULT_SITE" "$SQL_MODULE" "$PEER_HELPER_SOURCE" "$RADIUS_HELPER_SOURCE"; do
    [[ -f "$file" ]] || fail "Required file is missing: $file"
done

if ! command -v wg >/dev/null 2>&1; then
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y wireguard-tools
fi
if ! dpkg-query -W -f='${Status}' freeradius-mysql 2>/dev/null | grep -q '^install ok installed$'; then
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y freeradius-mysql
fi

# Create the dedicated management tunnel only when it does not already exist.
# Existing peers/private keys are preserved and reused.
if [[ ! -f "$WG_CONF" ]]; then
    install -d -m 700 /etc/wireguard
    PRIVATE_KEY_FILE="/etc/wireguard/${WG_INTERFACE}-private.key"
    PUBLIC_KEY_FILE="/etc/wireguard/${WG_INTERFACE}-public.key"
    umask 077
    wg genkey >"$PRIVATE_KEY_FILE"
    wg pubkey <"$PRIVATE_KEY_FILE" >"$PUBLIC_KEY_FILE"
    cat >"$WG_CONF" <<EOF
[Interface]
Address = ${WG_SERVER_IP}/24
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
[[ "$WG_PUBLIC_KEY" =~ ^[A-Za-z0-9+/]{43}=$ ]] || fail "Could not read a valid WireGuard server public key."
WG_ENDPOINT="${NUXHOST_WG_ENDPOINT:-$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}')}"
[[ "$WG_ENDPOINT" =~ ^[A-Za-z0-9.:-]+$ ]] || fail "Unable to determine the public WireGuard endpoint. Set NUXHOST_WG_ENDPOINT explicitly."

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
cp -a "$DEFAULT_SITE" "$BACKUP_DIR/default"
cp -a "$SQL_MODULE" "$BACKUP_DIR/sql"
cp -a "$WG_CONF" "$BACKUP_DIR/${WG_INTERFACE}.conf"
echo "Backup created: $BACKUP_DIR"

rollback_radius() {
    echo "Restoring pre-deployment FreeRADIUS configuration..." >&2
    systemctl stop freeradius >/dev/null 2>&1 || true
    cp -a "$BACKUP_DIR/default" "$DEFAULT_SITE"
    cp -a "$BACKUP_DIR/sql" "$SQL_MODULE"
    chown root:freerad "$SQL_MODULE" 2>/dev/null || true
    chmod 640 "$SQL_MODULE" 2>/dev/null || true
    if freeradius -XC >/tmp/radius-wireguard-rollback.log 2>&1; then
        systemctl start freeradius >/dev/null 2>&1 || true
    fi
}

install -d -m 755 /etc/nuxhost
cat >"$SAFE_CONFIG" <<EOF
interface=${WG_INTERFACE}
server_ip=${WG_SERVER_IP}
cidr=${WG_CIDR}
endpoint=${WG_ENDPOINT}
endpoint_port=${WG_ENDPOINT_PORT}
public_key=${WG_PUBLIC_KEY}
radius_host=${WG_SERVER_IP}
radius_auth_port=${RADIUS_AUTH_PORT}
radius_accounting_port=${RADIUS_ACCT_PORT}
radius_coa_port=${RADIUS_COA_PORT}
EOF
chmod 644 "$SAFE_CONFIG"

install -o root -g www-data -m 750 "$PEER_HELPER_SOURCE" "$PEER_HELPER_TARGET"
install -o root -g www-data -m 750 "$RADIUS_HELPER_SOURCE" "$RADIUS_HELPER_TARGET"
cat >/etc/sudoers.d/nuxhost-network-control <<EOF
www-data ALL=(root) NOPASSWD: ${PEER_HELPER_TARGET} *
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} reload
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} restart
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} check
www-data ALL=(root) NOPASSWD: ${RADIUS_HELPER_TARGET} status
EOF
chmod 440 /etc/sudoers.d/nuxhost-network-control
visudo -cf /etc/sudoers.d/nuxhost-network-control >/dev/null

cat >/etc/sysctl.d/99-nuxhost-wireguard.conf <<EOF
net.ipv4.ip_forward=1
EOF
sysctl -p /etc/sysctl.d/99-nuxhost-wireguard.conf >/dev/null

# Reproduce the proven private-listener + MySQL dynamic-NAS configuration.
export DEFAULT_SITE SQL_MODULE WG_SERVER_IP RADIUS_AUTH_PORT RADIUS_ACCT_PORT
python3 <<'PY'
import os
import re
from pathlib import Path

site = Path(os.environ['DEFAULT_SITE'])
ip = os.environ['WG_SERVER_IP']
auth = os.environ['RADIUS_AUTH_PORT']
acct = os.environ['RADIUS_ACCT_PORT']
text = site.read_text()
start = '# NUXHOST_WIREGUARD_RADIUS_START'
end = '# NUXHOST_WIREGUARD_RADIUS_END'

text = re.sub(re.escape(start) + r'.*?' + re.escape(end) + r'[ \t]*(?:\r?\n)?', '', text, flags=re.S)

# Remove old simple managed/manual listeners for this exact private IP/ports so
# repeated installs cannot reproduce the duplicate-bind failure seen in early
# onboarding versions.
for port in (auth, acct):
    pattern = re.compile(
        r'(?ms)^[ \t]*listen[ \t]*\{'
        r'(?=[^{}]*^[ \t]*ipaddr[ \t]*=[ \t]*"?' + re.escape(ip) + r'"?[ \t]*$)'
        r'(?=[^{}]*^[ \t]*port[ \t]*=[ \t]*' + re.escape(port) + r'[ \t]*$)'
        r'[^{}]*^[ \t]*\}[ \t]*(?:\r?\n)?'
    )
    text = pattern.sub('', text)

managed = f'''{start}
listen {{
    type = auth
    ipaddr = {ip}
    port = {auth}
}}

listen {{
    type = acct
    ipaddr = {ip}
    port = {acct}
}}
{end}
'''
insert = re.search(r'(?m)^[ \t]*authorize[ \t]*\{', text)
if not insert:
    raise SystemExit('Unable to locate authorize{} in the FreeRADIUS default site.')
text = text[:insert.start()] + managed + '\n' + text[insert.start():]
site.write_text(text)

sql = Path(os.environ['SQL_MODULE'])
sql_text = sql.read_text()
sql_text, driver_count = re.subn(
    r'(?m)^([ \t]*)driver[ \t]*=[ \t]*"rlm_sql_[^"]+"[ \t]*$',
    lambda m: f'{m.group(1)}driver = "rlm_sql_mysql"',
    sql_text,
    count=1,
)
if driver_count != 1:
    raise SystemExit('Unable to locate FreeRADIUS SQL driver setting.')
sql_text, clients_count = re.subn(
    r'(?m)^([ \t]*)#?[ \t]*read_clients[ \t]*=[ \t]*(?:yes|no|true|false).*$' ,
    lambda m: f'{m.group(1)}read_clients = yes',
    sql_text,
    count=1,
)
if clients_count != 1:
    raise SystemExit('Unable to locate read_clients in the SQL module.')

# Ubuntu ships sample MySQL TLS file paths that break otherwise valid installs.
# Remove only that untouched sample block; preserve any custom TLS settings.
mysql_match = re.search(r'(?ms)^([ \t]*)mysql\s*\{(.*?)^\1\}', sql_text)
if mysql_match:
    full = mysql_match.group(0)
    body = mysql_match.group(2)
    if '/etc/ssl/certs/my_ca.crt' in body or '/etc/ssl/certs/private/client.crt' in body:
        tls_match = re.search(r'(?ms)^([ \t]*)tls\s*\{.*?^\1\}\s*', full)
        if tls_match:
            replacement = tls_match.group(1) + '# NUXHOST: unused sample MySQL TLS block removed\n'
            full = full[:tls_match.start()] + replacement + full[tls_match.end():]
            sql_text = sql_text[:mysql_match.start()] + full + sql_text[mysql_match.end():]
sql.write_text(sql_text)
PY

chown root:freerad "$SQL_MODULE"
chmod 640 "$SQL_MODULE"
runuser -u freerad -- test -r "$SQL_MODULE" || { rollback_radius; fail "FreeRADIUS cannot read its SQL module."; }

if ! freeradius -XC >/tmp/radius-wireguard-check.log 2>&1; then
    tail -n 120 /tmp/radius-wireguard-check.log >&2
    rollback_radius
    fail "FreeRADIUS configuration validation failed."
fi
grep -q 'Driver rlm_sql_mysql' /tmp/radius-wireguard-check.log || { rollback_radius; fail "rlm_sql_mysql was not loaded."; }
if grep -q 'rlm_sql_null.*CANNOT be used for SELECTS' /tmp/radius-wireguard-check.log; then
    rollback_radius
    fail "FreeRADIUS is still using rlm_sql_null."
fi

echo "FreeRADIUS syntax + SQL validation: OK"

systemctl enable freeradius >/dev/null
systemctl stop freeradius >/dev/null 2>&1 || true
if ! systemctl start freeradius; then
    journalctl -u freeradius -n 120 --no-pager >&2 || true
    rollback_radius
    fail "FreeRADIUS failed the real socket-binding startup test."
fi
sleep 2
systemctl is-active --quiet freeradius || { rollback_radius; fail "FreeRADIUS is not active after startup."; }

for port in "$RADIUS_AUTH_PORT" "$RADIUS_ACCT_PORT"; do
    count="$(ss -H -lunp 2>/dev/null | awk -v ep="${WG_SERVER_IP}:${port}" '$4 == ep {n++} END {print n+0}')"
    [[ "$count" == "1" ]] || { rollback_radius; fail "Expected exactly one live ${WG_SERVER_IP}:${port} socket, found $count."; }
done

echo "FreeRADIUS live socket binding: OK"

if command -v ufw >/dev/null 2>&1 && ufw status | grep -q '^Status: active'; then
    ufw allow "$WG_ENDPOINT_PORT/udp" comment 'NUXHOST WireGuard' >/dev/null || true
    ufw allow in on "$WG_INTERFACE" from "$WG_CIDR" to "$WG_SERVER_IP" port "$RADIUS_AUTH_PORT" proto udp comment 'NUXHOST RADIUS AUTH WG' >/dev/null || true
    ufw allow in on "$WG_INTERFACE" from "$WG_CIDR" to "$WG_SERVER_IP" port "$RADIUS_ACCT_PORT" proto udp comment 'NUXHOST RADIUS ACCT WG' >/dev/null || true
fi

"$PEER_HELPER_TARGET" check >/dev/null
"$RADIUS_HELPER_TARGET" check >/dev/null

echo
echo "WIREGUARD + FREERADIUS AUTOMATIC ONBOARDING READY"
echo "WireGuard interface: $WG_INTERFACE"
echo "Management subnet: $WG_CIDR"
echo "Private RADIUS endpoint: $WG_SERVER_IP"
echo "Auth/Accounting: UDP $RADIUS_AUTH_PORT/$RADIUS_ACCT_PORT"
echo "CoA destination on MikroTik: UDP $RADIUS_COA_PORT"
echo "FreeRADIUS SQL: rlm_sql_mysql + dynamic NAS clients"
echo "Live private listeners: verified"
echo "Backup: $BACKUP_DIR"
