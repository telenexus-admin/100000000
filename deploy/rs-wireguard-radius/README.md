# RS WireGuard + FreeRADIUS automatic onboarding

This integration clones the proven RouterOS 7.18-compatible onboarding architecture used by the working NuxHost deployment while adapting it to this billing system's native `tbl_routers`, RADIUS `nas` table, and existing MikroTik Configurator.

## Admin flow

1. Routers → **Automatic Setup**.
2. Create a router name/location.
3. The billing system allocates a private WireGuard management IP, rotates a dedicated RouterOS API credential pair, creates a one-time activation token, creates/updates the RADIUS NAS and rotates its secret.
4. Copy the single RouterOS 7 script into WinBox Terminal.
5. The script establishes WireGuard, validates the activation response, confirms an authenticated WireGuard peer, secures RouterOS API to the WireGuard server, configures RADIUS auth/accounting/CoA, and prints the completion marker only after verification.
6. The billing UI polls RouterOS API over WireGuard and automatically opens the existing port configurator.
7. WireGuard-managed routers are forced to RADIUS authentication in the configurator. Manual routers keep the old API/RADIUS choices.

## Server install

Run from the billing application root as root:

```bash
bash deploy/rs-wireguard-radius/install-wireguard-radius.sh
```

Defaults intentionally use a dedicated management tunnel so this clone can coexist with another NuxHost WireGuard deployment on the same VPS:

- interface: `wg-rs`
- subnet: `10.78.0.0/24`
- server: `10.78.0.1`
- UDP endpoint port: `51822`

Override these with `RS_WG_INTERFACE`, `RS_WG_CIDR`, `RS_WG_SERVER_IP`, `RS_WG_ENDPOINT`, and `RS_WG_ENDPOINT_PORT` before running the installer.

The installer preserves existing FreeRADIUS SQL credentials by default. On a clean server, provide the FreeRADIUS SQL connection through environment variables before installation: `RS_RADIUS_DB_HOST`, `RS_RADIUS_DB_PORT`, `RS_RADIUS_DB_USER`, `RS_RADIUS_DB_PASSWORD`, `RS_RADIUS_DB_NAME`. The installer does not print those values.

The billing application itself reuses the normal named `radius` ORM connection. If no separate RADIUS credentials are supplied and the installation stores FreeRADIUS tables in the billing database, `init.php` now safely falls back to the main DB credentials for that named connection.

## Security boundaries

- MikroTik WireGuard private keys never leave RouterOS.
- PHP never reads the server WireGuard private key.
- Server peer changes are performed only by `/usr/local/bin/rs-wireguard-manage` via restricted sudo.
- RADIUS auth/accounting listeners bind to the WireGuard server IP rather than WAN/public addresses.
- RouterOS API is restricted to the WireGuard server `/32`.
- The RouterOS 7.18 HTTPS activation request uses a short-lived single-use token; successful activation is then proven by the peer's authenticated WireGuard endpoint state.
- Existing manual router onboarding remains intact.

## Validation

Run:

```bash
bash deploy/rs-wireguard-radius/validate-integration.sh
```

It checks PHP syntax, shell syntax, the v6 activation/handshake invariants, RADIUS/accounting/CoA configuration, firewall placement logic, and rejects the two RouterOS 7.18 failure patterns previously encountered (`/certificate/settings` and moving built-in firewall rules).

After deploying on the real server, run the live verification too:

```bash
bash deploy/rs-wireguard-radius/verify-live.sh
```

That confirms the WireGuard interface, private RADIUS sockets, restricted `www-data` sudo boundary, billing-system `nas` table access, and (when configured literally) that the billing RADIUS connection and FreeRADIUS SQL module point to the same database name. It does not print database passwords, RADIUS secrets, or WireGuard private keys.
