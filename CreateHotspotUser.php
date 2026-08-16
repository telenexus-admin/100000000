<?php
/**
 * Legacy root entry — canonical hotspot API lives in system/plugin/.
 */
require_once __DIR__ . '/init.php';

if (!function_exists('CreateHotspotuser')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Hotspot plugin not loaded']);
    exit;
}

CreateHotspotuser();
