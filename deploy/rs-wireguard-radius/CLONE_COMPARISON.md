# Clone comparison against the proven onboarding

| Proven behavior | RS clone |
|---|---|
| RouterOS 7.18-compatible single script | Yes — generator v6 |
| Router clock bootstrap + NTP | Yes |
| Dedicated WireGuard management interface | Yes — configurable, isolated default `wg-rs` |
| Per-router private management IP allocation | Yes — `rs_wireguard_allocations` with unique router/IP constraints |
| Router private key remains on MikroTik | Yes |
| One-time activation token stored as SHA-256 | Yes |
| Activation HTTP response explicitly checked | Yes |
| WireGuard readiness based on authenticated peer state, not ICMP success | Yes |
| Dedicated generated RouterOS API credentials | Yes |
| RouterOS API restricted to WireGuard server | Yes |
| Firewall accept rule inserted before normal default input drop without moving built-ins | Yes |
| RADIUS auth + accounting over WireGuard | Yes — 1812/1813 |
| CoA enabled | Yes — 3799 |
| Dynamic SQL NAS registration | Yes — native existing `nas` table |
| RADIUS secret rotation per fresh generation | Yes |
| FreeRADIUS dynamic clients reloaded after NAS change | Yes |
| Hotspot and PPPoE RADIUS enabled | Yes |
| Poll authenticated RouterOS API before declaring connected | Yes |
| Continue automatically to port configurator | Yes |
| WAN excluded from customer port selection | Yes — active bound DHCP WAN detection plus ether1 safety exclusion |
| WireGuard interface excluded from customer port selection | Yes |
| Auto-onboarded router forces RADIUS in configurator | Yes |
| Manual router flow preserved | Yes |
| Server private WireGuard key unavailable to PHP | Yes |
| Private-only RADIUS listener safety check | Yes |
