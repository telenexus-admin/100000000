# M-Pesa C2B callback dispatcher (one Paybill → many backends)

Safaricom stores **one** Validation URL and **one** Confirmation URL per **ShortCode** (Paybill). To send traffic to different billing apps while keeping a single Paybill, run a **dispatcher** (hub) at the registered URLs. The hub receives M-Pesa’s POST, decides which project owns the payment (usually from **BillRefNumber** or a prefix), and **forwards** the same JSON body to that project’s existing C2B endpoints.

---

## 0. Built-in hub in this repo (implemented)

| Item | Location |
|------|-----------|
| Hub plugin | `system/plugin/c2b_hub.php` |
| Route map (you create) | `system/uploads/c2b_hub_routes.json` (copy from `c2b_hub_routes.sample.json`) |
| Register hub URLs with Daraja | Admin → Payment gateway → M-Pesa → **Register C2B hub URLs**, or open `…?_route=plugin/c2b_hub&kind=register` after saving gateway settings |
| Daraja endpoints (after register) | `{APP_URL}/?_route=plugin/c2b_hub&kind=validation` and `…&kind=confirmation` |
| Logs | `system/cache/c2b_hub.log` |
| Child sites | Keep using `plugin/c2b&kind=…` only on each child; **do not** register the same Paybill again on children if the hub owns it |

**Env (optional):** `C2B_HUB_OUTBOUND_SECRET` — if set, the hub sends header `X-C2B-Hub-Secret` to children; you can add a check in `c2b.php` on children to trust only the hub.

**Change in `c2b.php`:** the listener runs only when `_route` is exactly `plugin/c2b`, so it no longer reacts to `plugin/c2b_hub&kind=…` on the same installation.

---

## 1. Architecture

```text
M-Pesa  ──POST JSON──►  https://hub.example.com/c2b.php?kind=validation
                              │
                              ├── route by BillRefNumber / prefix / lookup table
                              │
                              ├──► https://site-a.example.com/.../c2b&kind=validation
                              └──► https://site-b.example.com/.../c2b&kind=validation
```

- **Register with Daraja only the hub URLs** (never register each child site if they share one Paybill).
- Each **child** keeps its normal `system/plugin/c2b.php` paths; only the **hub** is public to Safaricom.

---

## 2. Routing rules (choose one)

| Strategy | How | Example |
|----------|-----|---------|
| **Prefix** | First segment of `BillRefNumber` | `A_user123` → site A, `B_user123` → site B |
| **Numeric range** | Hash or MSISDN / amount | fragile; prefer prefix or DB |
| **Lookup table** | Hub DB maps `account_reference → base_url` | flexible, needs admin UI or SQL |
| **Separate paybills** | No dispatcher | simplest operationally |

Your current billing code uses **`BillRefNumber`** as the account key in `ConfirmationURL()` — design prefixes or a hub DB so each child still receives a `BillRefNumber` it understands.

---

## 3. What M-Pesa expects back

- **Validation URL** — must respond **quickly** with JSON, e.g. accept:  
  `{"ResultCode":"0","ResultDesc":"Accepted"}`  
  Reject (e.g. unknown account): non-zero `ResultCode` per Daraja docs.
- **Confirmation URL** — acknowledge receipt, typically:  
  `{"ResultCode":"0","ResultDesc":"Success"}`  

If the hub **forwards** validation to a child, the child must return the same shape (or the hub maps the child response to Daraja’s format).

**Confirmation** should not block for long. Prefer: return success to M-Pesa quickly, then forward async (queue/cron) if child is slow; or forward with a **short** HTTP timeout and still return success to M-Pesa after logging the payload for manual replay.

---

## 4. Minimal PHP hub (`c2b-hub.php`)

Deploy on the **same host** you register with Daraja (HTTPS). Edit `$routes` and optional `$defaultTarget`.

```php
<?php
/**
 * M-Pesa C2B hub — register these URLs with Daraja:
 *   Validation:    https://YOUR-HUB/c2b-hub.php?kind=validation
 *   Confirmation: https://YOUR-HUB/c2b-hub.php?kind=confirmation
 *
 * Child sites must expose the same JSON endpoints your billing stack uses
 * (e.g. .../plugin/c2b&kind=validation — use full URL per child).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$kind = $_GET['kind'] ?? '';
if (!in_array($kind, ['validation', 'confirmation'], true)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 'C2', 'ResultDesc' => 'Bad kind']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 'C2', 'ResultDesc' => 'Invalid JSON']);
    exit;
}

$billRef = (string) ($data['BillRefNumber'] ?? $data['AccountReference'] ?? '');

/** @var array<string, string> prefix (uppercase) => child base URL (no trailing slash) */
$routes = [
    'SITEA_' => 'https://billing-a.example.com/?_route=plugin/c2b',
    'SITEB_' => 'https://billing-b.example.com/?_route=plugin/c2b',
];

$target = null;
$prefix = strtoupper($billRef);
foreach ($routes as $pfx => $base) {
    if ($pfx !== '' && str_starts_with($prefix, strtoupper($pfx))) {
        $target = $base;
        break;
    }
}

// Optional: single default child if no prefix matched
$defaultTarget = null; // e.g. 'https://main.example.com/?_route=plugin/c2b';
if ($target === null) {
    $target = $defaultTarget;
}

if ($target === null) {
    echo json_encode(['ResultCode' => 'C2', 'ResultDesc' => 'Unknown account reference']);
    exit;
}

$url = $target . '&kind=' . urlencode($kind);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $raw,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Forwarded-From: mpesa-c2b-hub',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $kind === 'validation' ? 8 : 15,
]);

$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log for replay if child failed (implement file/DB queue as needed)
if ($resp === false || $code >= 500) {
    error_log('[c2b-hub] forward failed kind=' . $kind . ' bill=' . $billRef . ' http=' . $code);
}

// Validation: pass through child JSON if it looks valid; else accept/reject policy
if ($kind === 'validation') {
    $decoded = json_decode((string) $resp, true);
    if (is_array($decoded) && isset($decoded['ResultCode'])) {
        echo json_encode($decoded);
        exit;
    }
    echo json_encode(['ResultCode' => 'C2', 'ResultDesc' => 'Upstream validation error']);
    exit;
}

// Confirmation: M-Pesa needs a quick OK even if child is down (log + reconcile later)
echo json_encode(['ResultCode' => '0', 'ResultDesc' => 'Success']);
```

**Hardening (recommended):**

- Add **HMAC** or static **secret header** between hub and children; children verify before processing.
- Log **raw body + TransID** to DB for idempotency (M-Pesa may retry).
- Restrict **source IP** if your policy allows (Safaricom ranges change; many operators skip IP allowlisting).
- Use **HTTPS only** on hub and children.

---

## 5. Daraja registration

1. Deploy the hub under HTTPS.
2. Point **ConfirmationURL** and **ValidationURL** to the hub (query `kind=...` as in your `RegisterUrl()` pattern, or path-based routing).
3. Call **register URL** **once** from the hub context (or use Daraja portal) with the **same** Consumer Key/Secret as the Paybill app.

Child sites **do not** call Safaricom `registerurl` for that same ShortCode if the hub owns registration.

---

## 6. Aligning with this codebase

**Single site (no hub):** `system/plugin/c2b.php` → `RegisterUrl()` registers:

- `U . 'plugin/c2b&kind=confirmation'`
- `U . 'plugin/c2b&kind=validation'`

**Hub:** `c2b_hub.php` registers `plugin/c2b_hub&kind=…` with Daraja and forwards JSON to each child’s `…?_route=plugin/c2b` (plus `&kind=…`).

---

## 7. `BillRefNumber` design

Customers must enter an account reference the **correct child** understands, e.g. `SITEA_john` vs `SITEB_john`, or a global unique id resolved by the hub DB before forward.

---

*This hub is a template only — adapt timeouts, error handling, and validation JSON to match your Daraja API version and Safaricom’s current specification.*
