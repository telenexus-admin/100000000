# FreeRADIUS documentation

This document covers FreeRADIUS concepts, typical deployment steps, troubleshooting, and how this billing stack integrates via the REST module and `radius.php`.

---

## 1. What FreeRADIUS is

**FreeRADIUS** is an open-source RADIUS server. Network devices (NAS: routers, APs, BNGs) send **Access-Request** and **Accounting-Request** packets; the server applies policy (SQL, LDAP, REST, files, etc.) and returns **Accept/Reject** plus **reply attributes** (rates, pools, VLANs, session limits).

Typical packet types:

| Stage        | RADIUS meaning                         | Common use                          |
|-------------|-----------------------------------------|-------------------------------------|
| Authorize   | Decide if user may connect + profile   | Check subscription, return limits   |
| Authenticate | Verify password / EAP / token       | PAP/CHAP/MS-CHAP validation         |
| Accounting  | Start / Interim-Update / Stop          | Usage, session time, disconnect     |

---

## 2. Installation (Linux reference)

**Debian / Ubuntu**

```bash
sudo apt update
sudo apt install freeradius freeradius-utils freeradius-mysql   # or freeradius-rest for REST
sudo systemctl enable --now freeradius
```

**RHEL / Alma / Rocky**

```bash
sudo dnf install freeradius freeradius-utils
sudo systemctl enable --now radiusd
```

Optional modules (package names vary by distro):

- `freeradius-rest` — `rlm_rest` (HTTP backend; used with `radius.php` in this project)
- `freeradius-mysql` / `freeradius-postgresql` — SQL-backed users and accounting
- `freeradius-ldap` — directory authentication

---

## 3. Main configuration files

| Path (typical) | Role |
|----------------|------|
| `/etc/freeradius/3.0/radiusd.conf` | Global server: logging, threads, includes |
| `/etc/freeradius/3.0/clients.conf` | NAS definitions (IP/secret, shortname) |
| `/etc/freeradius/3.0/mods-enabled/` | Enabled modules (symlinks from `mods-available/`) |
| `/etc/freeradius/3.0/sites-enabled/` | Virtual servers (`default`, `inner-tunnel`, etc.) |
| `/etc/freeradius/3.0/policy.d/` | Unlang policies |

After edits:

```bash
sudo freeradius -XC    # config test (no daemon)
sudo systemctl restart freeradius   # or radiusd
```

---

## 4. NAS (clients) configuration

Each router/AP that talks RADIUS must be a **client** with a **shared secret** matching the NAS config.

Example (`clients.conf`):

```
client mikrotik_nas {
    ipaddr          = 192.0.2.1
    secret          = use_a_long_random_secret
    nas_type        = other
    shortname       = BRAS-1
}
```

**Security:** restrict `ipaddr` to real NAS IPs; use strong secrets; firewall UDP **1812** (auth) and **1813** (acct) to known sources only.

---

## 5. Debugging

```bash
# Foreground debug (very verbose)
sudo freeradius -X

# Filtered log (depends on distro / journal)
sudo journalctl -u freeradius -f
```

Common issues:

- **No reply / timeout** — firewall, wrong NAS IP, wrong secret, or REST/SQL backend down.
- **Reject without reason** — enable debug; check `post-auth` / `authorize` policies.
- **Accounting not stored** — ensure `accounting` section runs and SQL/REST writes succeed.

---

## 6. SQL vs REST in this project

Many deployments use **rlm_sql** with FreeRADIUS schema (`radcheck`, `radreply`, `radacct`, …).

**This codebase** can use **rlm_rest** so FreeRADIUS delegates **authorize**, **authenticate**, and **accounting** logic to the web app’s `radius.php` endpoint. The PHP script validates customers/vouchers, applies plan bandwidth and limits, and returns JSON attributes for the REST module to map into RADIUS reply/control pairs.

---

## 7. Integration: `radius.php` and FreeRADIUS REST

### 7.1 Endpoint

Point `rlm_rest` at the URL where `radius.php` is reachable from the FreeRADIUS host (HTTPS recommended in production).

### 7.2 Section header

The application reads the section from the HTTP header that PHP exposes as `$_SERVER['HTTP_X_FREERADIUS_SECTION']` — i.e. send:

```http
X-FreeRADIUS-Section: authorize
```

Valid section values used in code: **`authenticate`**, **`authorize`**, **`accounting`**.

Alternatively, the script accepts a query parameter **`action`** with the same values if the header is empty.

### 7.3 Behaviour summary

| Section        | Purpose (in this app) |
|----------------|------------------------|
| `authenticate` | Password / CHAP check; **204 No Content** on success, **401** + JSON on failure |
| `authorize`    | Subscription / voucher activation; returns Accept + reply attributes (rates, limits, pool, etc.) or Reject |
| `accounting`   | Updates `rad_acct` usage; may return extra attributes on session start for data-capped plans |

### 7.4 Response format

Success and failure responses are JSON. HTTP status is **200**, **204**, or **401** depending on branch (see `show_radius_result()` in `radius.php`).

Reply attributes often use keys such as:

- `control:Auth-Type` — e.g. `Accept` / `Reject`
- `reply:Reply-Message`, `reply:Mikrotik-Rate-Limit`, `reply:Framed-Pool`, WISPr / Ascend rate attributes, session limits, etc.

### 7.5 Example `rlm_rest` sketch (not copy-paste complete)

Exact syntax depends on FreeRADIUS version. Conceptually:

```
rest billing {
    connect_uri = "https://billing.example.com/radius.php"
    authenticate {
        uri = "${..connect_uri}"
        method = post
        body = json
        tls { ... }
    }
    authorize {
        uri = "${..connect_uri}"
        method = post
    }
    accounting {
        uri = "${..connect_uri}"
        method = post
    }
}
```

You must configure how attributes are **encoded** into the POST body and how JSON is **mapped** back to RADIUS (see official `mods-available/rest` comments for your installed version).

Add a request header for the section, for example via module-specific options or `unlang` + custom headers if your version requires it:

```
X-FreeRADIUS-Section: authorize
```

Ensure the NAS and RADIUS **timeout** values allow for HTTP latency to PHP.

### 7.6 Database / app notes

- Active customers and **`tbl_user_recharges`** drive authorization; vouchers may activate when `username == password` (voucher flow).
- Accounting aggregates into **`rad_acct`** (fields such as `acctOutputOctets`, `acctInputOctets`, `nasid`, `macaddr`, etc.).
- Plans with **`routers = 'radius'`** align with RADIUS-backed service.

---

## 8. Official references

- [FreeRADIUS documentation](https://freeradius.org/documentation/)
- [FreeRADIUS Wiki](https://wiki.freeradius.org/)
- Module reference in `mods-available/` on the server (always matches your installed version)

---

## 9. Checklist for a new NAS

1. Add **client** entry (IP + secret).
2. Confirm **1812/1813** reach the RADIUS host from the NAS.
3. Align **auth/acct** ports and **COA** if you use disconnect.
4. Test with `radtest` from the RADIUS server:

   ```bash
   radtest user password 127.0.0.1 0 testing123
   ```

   Use the real client IP/secret as configured.

5. Run `freeradius -X` once and complete a full login + accounting cycle from the NAS.

---

*This file is project-local documentation. Adjust paths, package names, and `rlm_rest` snippets to match your OS and FreeRADIUS major version.*
