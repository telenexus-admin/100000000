<?php
/**
 * NuxHost Suspension Lock Plugin
 *
 * Loaded by init.php (glob) on EVERY request — UI and API alike. Zero core edits.
 *
 * When NuxHost writes nbg_suspension_alert into tbl_appconfig this plugin
 * enforces the suspension policy:
 *
 *   - API calls (Mikrotik, hotspot, etc.) → JSON error + exit()
 *   - Admin NOT logged in                 → allow (login page works normally)
 *   - Admin logged in on exempt route     → allow (logout, subscription_manager, impersonate)
 *   - Admin logged in on any other route  → redirect to plugin/subscription_manager
 */

_nhsl_run_suspension_check();

function _nhsl_run_suspension_check(): void
{
    global $isApi;

    // Treat CreateHotspotUser calls via index.php as API
    $route = isset($_GET['_route']) ? trim($_GET['_route']) : '';
    $tempIsApi = !empty($isApi) || (strpos($route, 'plugin/CreateHotspotuser') === 0);

    // ── 1. Read suspension alert — fail open if DB not ready ──────────────────
    try {
        $alert = ORM::for_table('tbl_appconfig')
            ->where('setting', 'nbg_suspension_alert')
            ->find_one();
    } catch (Throwable $e) {
        return;
    }

    if (!$alert || empty($alert->value)) {
        return; // No suspension active
    }

    // ── 2. API calls → always hard-block with JSON ────────────────────────────
    if ($tempIsApi) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Service suspended. Please settle the outstanding invoice to restore access.',
        ]);
        exit();
    }

    // ── 3. UI request — check current route ───────────────────────────────────
    $route  = isset($_GET['_route']) ? trim($_GET['_route']) : '';

    // These routes must always work regardless of suspension
    $exempt = ['logout', 'login', 'plugin/subscription_manager', 'nuxhost_impersonate', 'nh_admin_bridge'];
    foreach ($exempt as $r) {
        if ($route === $r || strpos($route, $r) === 0) {
            return;
        }
    }

    // ── 3b. Captive-portal / hotspot API routes → always hard-block ──────────
    // MikroTik captive portals call ?_route=plugin/CreateHotspotuser&type=...
    // These are not flagged as $isApi but must be blocked during suspension.
    if (strpos($route, 'plugin/CreateHotspotuser') !== false
        || strpos($route, 'plugin/createhotspotuser') !== false) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Service suspended. Please settle the outstanding invoice to restore access.',
        ]);
        exit();
    }

    // ── 4. Not logged in → show login page normally ───────────────────────────
    if (empty($_SESSION['aid'])) {
        return;
    }

    // ── 5. Logged-in admin on any non-exempt page → redirect to subscription manager ──
    if (ob_get_level()) ob_end_clean();
    header('Location: ' . APP_URL . '/?_route=plugin/subscription_manager');
    exit();
}