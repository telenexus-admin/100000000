# Automatic WireGuard + FreeRADIUS onboarding

This package adds the same production onboarding pattern proven on RouterOS 7.18.2:

1. Admin creates a router from **Automatic Router Setup**.
2. Billing allocates a unique private WireGuard management address.
3. Billing rotates a dedicated RouterOS API credential pair and RADIUS shared secret.
4. Billing upserts the router into the existing FreeRADIUS `nas` table and reloads dynamic SQL clients.
5. Admin copies one RouterOS 7 script into WinBox Terminal.
6. Router creates WireGuard and posts only its public key through a short-lived, one-time activation token.
7. The root helper adds/replaces only that peer on the server; PHP never reads the server private key.
8. Router validates the activation response and verifies an authenticated WireGuard handshake from `current-endpoint-address`.
9. Router enables API only from the WireGuard server /32, configures RADIUS auth/accounting/CoA, and prints the completion marker only after verification.
10. Billing proves the RouterOS API connection over WireGuard and automatically opens the existing MikroTik port configurator.
11. WireGuard-onboarded routers are forced to RADIUS subscriber authentication; `ether1` and the WireGuard management interface are protected from port selection.

## Server deployment

Run from the billing-system root on the target Ubuntu/FreeRADIUS VPS:

```bash
sudo bash deploy/configure-radius-wireguard.sh
```

Defaults mirror the proven deployment:

- interface: `wg-nuxhost`
- management subnet: `10.77.0.0/24`
- server management IP: `10.77.0.1`
- WireGuard UDP port: `51821`
- RADIUS auth/accounting: UDP `1812/1813`
- MikroTik CoA destination: UDP `3799`

If this VPS is behind NAT or the detected endpoint is not the public address, run with an explicit endpoint:

```bash
sudo NUXHOST_WG_ENDPOINT=YOUR.PUBLIC.IP.OR.DNS bash deploy/configure-radius-wireguard.sh
```

The installer is intentionally conservative: it backs up FreeRADIUS and WireGuard config, preserves an existing `wg-nuxhost` private key and unrelated peers, removes duplicate managed private RADIUS listeners, forces `rlm_sql_mysql` + `read_clients = yes`, starts the real daemon, verifies the actual `10.77.0.1:1812/1813` sockets, and installs restricted sudo helpers for `www-data`.

## Billing prerequisites

The billing system must already have its RADIUS connection enabled/configured in `config.php` / Settings. The automatic workflow uses the same existing `radius` ORM connection and existing `nas` table; no second RADIUS database is introduced.

## Validation

Before deployment:

```bash
php tools/validate_wireguard_onboarding.php
php -l system/plugin/radius_wireguard_onboarding.php
php -l system/plugin/radius_wireguard_bridge.php
php -l system/autoload/WireguardControlPlane.php
bash -n deploy/configure-radius-wireguard.sh
bash -n deploy/nuxhost-wireguard-manage
bash -n deploy/nuxhost-radius-manage
```

A successful RouterOS terminal run ends with:

```text
NuxHost: WireGuard handshake confirmed.
NuxHost: WireGuard connected. Securing RouterOS API...
NuxHost: configuring RADIUS over WireGuard...
NUXHOST-WIREGUARD-RADIUS-ONBOARDING-COMPLETE
```

After that the billing page should show **MikroTik Connected** and continue to the existing port-selection/configuration page.
