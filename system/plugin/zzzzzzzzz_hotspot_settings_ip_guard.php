<?php

/**
 * Guard the legacy Hotspot Settings POST path.
 *
 * hotspot_settings.php can publish login.html independently of the MikroTik
 * configurator. After it finishes, repatch the generated file with the
 * authoritative APP_URL/router id, republish it, remove legacy billing-domain
 * walled-garden entries, and restore the exact billing IP/port allow rule.
 */

$rs9Route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($rs9Route === 'plugin/hotspot_settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $rs9RouterId = isset($_POST['router_id']) ? (int) $_POST['router_id'] : 0;
    if ($rs9RouterId > 0) {
        register_shutdown_function('rs9_hotspot_settings_finalize', $rs9RouterId);
    }
}

function rs9_hotspot_settings_finalize($routerId)
{
    try {
        if (!function_exists('rs8_billing_url')
            || !function_exists('rs8_patch_portal_html')
            || !function_exists('rs8_remove_legacy_billing_walled_garden')
            || !function_exists('rs8_ensure_billing_ip_walled_garden')) {
            return;
        }

        $router = ORM::for_table('tbl_routers')->find_one((int) $routerId);
        if (!$router) {
            return;
        }

        $billingUrl = rs8_billing_url();
        $root = dirname(__DIR__, 2);
        $rootFile = $root . DIRECTORY_SEPARATOR . 'hotspot_login.html';
        $uploadFile = $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'hotspot_login.html';

        $source = '';
        if (is_file($rootFile)) {
            $source = (string) @file_get_contents($rootFile);
        } elseif (is_file($uploadFile)) {
            $source = (string) @file_get_contents($uploadFile);
        }

        if ($source !== '' && stripos($source, 'PAMNET_PORTAL') !== false) {
            $patched = rs8_patch_portal_html($source, $billingUrl, (int) $routerId);
            @file_put_contents($rootFile, $patched);
            if (is_dir(dirname($uploadFile))) {
                @file_put_contents($uploadFile, $patched);
            }
        }

        if (!function_exists('rs_mikrotik_configurator_client')) {
            return;
        }
        $client = rs_mikrotik_configurator_client($router, 8);

        if ($source !== '' && function_exists('hotspot_settings_html_directory') && function_exists('rs_mikrotik_configurator_upload_login')) {
            $dir = hotspot_settings_html_directory($client);
            $publicUrl = rtrim($billingUrl, '/') . '/hotspot_login.html?_rs=' . time();
            rs_mikrotik_configurator_upload_login($client, $publicUrl, $dir);
        }

        // Upload helper/legacy settings code may have re-added old defaults.
        // Final state must point to this installation's actual IP/port.
        rs8_remove_legacy_billing_walled_garden($client);
        rs8_ensure_billing_ip_walled_garden($client, $billingUrl);
    } catch (Throwable $e) {
        error_log('[hotspot-settings-ip-guard] router=' . (int) $routerId . ' error=' . $e->getMessage());
    }
}
