# Hotspot: connected but no internet

This guide explains why customers can **purchase a plan**, **authenticate to the hotspot**, and still have **no usable internet**, and how to isolate the cause. It applies to typical MikroTik-based hotspot deployments integrated with this billing stack.

---

## 1. What “connected” means

| Symptom | Meaning |
|--------|---------|
| Wi‑Fi icon shows connected | Layer 2 only: associated to the AP. |
| Hotspot login succeeds | NAS accepted username/password; session may appear under **Hotspot → Active**. |
| Websites/apps work | End-to-end path to the internet (routing, NAT, DNS, firewall). |

Billing software usually **creates or updates hotspot users** on the router and assigns a **user profile**. It does **not** replace correct **WAN, NAT, routing, DNS, or firewall** configuration on the gateway.

In this codebase, hotspot users are pushed to MikroTik with a `profile` matching the plan’s `name_plan` (see `system/devices/MikrotikHotspot.php`). That profile must exist on the **same** router referenced by the plan.

---

## 2. Quick isolation (do this first)

From a **customer device** after successful login:

1. **Ping an IP address** (bypasses DNS), e.g. `8.8.8.8` or your router’s LAN IP.
2. **Ping a hostname**, e.g. `google.com`.

| Ping IP | Ping name | Likely area |
|---------|-----------|-------------|
| Fails | Fails | Routing/NAT/firewall or WAN; not DNS-only. |
| Works | Fails | DNS (resolver, firewall blocking DNS, or wrong DNS on clients). |
| Works | Works | Problem may be app-specific, proxy, or intermittent—keep testing. |

On the **MikroTik** (Winbox/SSH/Terminal):

```text
/ping 8.8.8.8 count=3
/ip route print where dst-address=0.0.0.0/0
/ip firewall nat print where chain=srcnat
/ip hotspot active print
```

- If the router **cannot** ping `8.8.8.8`, fix **uplink/WAN/default route** before chasing hotspot rules.
- If the router can ping but **clients cannot**, check **NAT (masquerade)**, **forward chain firewall**, and **hotspot interface/pool** alignment.

---

## 3. Router-side causes (most common)

### 3.1 No or broken path to the internet

- Default route inactive or pointing wrong.
- WAN interface down, PPPoE not connected, or upstream modem issue.
- Double-router setup: inner router has no route/NAT to outer network.

**Check:** `/ip route print` — `0.0.0.0/0` should be **active** and reachable.

### 3.2 Missing or incorrect NAT

Hotspot client traffic leaving toward the internet must be **source-NATed** (masquerade or `src-nat`) on the correct outbound interface (or address list).

**Check:** `/ip firewall nat print` — typical pattern is `chain=srcnat`, `action=masquerade`, with `out-interface` or `src-address` covering the hotspot subnet.

### 3.3 Firewall blocking forward

Rules on `chain=forward` may drop traffic from the hotspot bridge/interface to the WAN.

**Check:** `/ip firewall filter print` — look for drops affecting hotspot source or interface; use logging or counters to confirm.

### 3.4 IP pool / gateway mismatch

- Hotspot server uses a pool that **does not exist** or **overlaps** the gateway incorrectly.
- Clients get addresses outside what NAT/firewall expects.

**Check:** `/ip pool print`, `/ip hotspot server print`, hotspot profile **address-pool** (if used).

### 3.5 DNS

Symptoms: “no internet” in browsers, but **ping to IP works**.

- Clients have no DNS servers, or servers are unreachable.
- Firewall blocks UDP/TCP 53 from hotspot subnet.
- Hotspot profile / DHCP not handing out working resolvers.

**Mitigation:** Use known resolvers (e.g. router as DNS relay with allow rules, or public DNS if policy allows) and ensure **forward** allows DNS to those targets.

---

## 4. Billing and NAS alignment

### 4.1 Profile name must match the router

The plan’s **`name_plan`** in billing must match the MikroTik **Hotspot → User profile** name on the router that serves those customers. A typo or renamed profile on only one side causes wrong limits, pool, or broken profile reference.

### 4.2 Correct router / NAS per plan

The plan must reference the **router** where the hotspot actually runs. If the user is created on **Router A** but customers use **Router B**, they may use old credentials, wrong profile, or no user at all on the active NAS.

### 4.3 Bandwidth / rate-limit

In this project, if upload or download rate is set to `0` in the bandwidth definition, the integration sends an **empty** `rate-limit` when creating the profile on MikroTik (see `MikrotikHotspot::add_plan`). That is usually acceptable; if problems persist, verify the resulting profile on the router is not manually broken or incomplete.

---

## 5. Captive portal / walled garden

Strict **walled garden** entries control what unauthenticated users can reach. After full login, users should not be stuck in “login-only” paths. If authenticated users still behave like captive-only traffic:

- Confirm **active session** and **profile** in `/ip hotspot active print detail`.
- Review **IP → Hotspot → Server Profiles** and **Walled Garden** for unintended restrictions.

---

## 6. Optional: FreeRADIUS / RADIUS path

If authentication or policy is delegated to **FreeRADIUS** (see `docs/FREERADIUS.md`), mismatches between NAS config, RADIUS replies (Framed-IP-Address, routes, rate attributes), and local hotspot behavior can still produce “session up, no usable path.” Use RADIUS logs and packet capture on **1812/1813** when that architecture is in use.

---

## 7. Suggested checklist for support staff

1. Confirm **WAN** on gateway (ping from router).
2. Confirm **default route** active.
3. Confirm **NAT masquerade** for hotspot/LAN → WAN.
4. Confirm **no forward drops** for hotspot traffic.
5. From client: **ping IP** then **ping hostname** (DNS split).
6. On MikroTik: **active hotspot user** and **profile name** match billing **`name_plan`**.
7. Confirm plan **router** matches the NAS customers use.

---

## 8. Related files in this repository

| Area | Path |
|------|------|
| MikroTik hotspot user/profile integration | `system/devices/MikrotikHotspot.php` |
| Shared MikroTik helpers (plans, users) | `system/autoload/Mikrotik.php` |
| FreeRADIUS overview | `docs/FREERADIUS.md` |

---

*Last updated for troubleshooting workflows; router OS builds and exact menu paths may vary by MikroTik RouterOS version.*
