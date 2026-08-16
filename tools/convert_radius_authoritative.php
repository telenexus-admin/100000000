<?php
/**
 * Convert RS/WireGuard-onboarded MikroTik Hotspot routers to RADIUS-authoritative
 * subscriber provisioning/authentication while preserving legacy/manual routers.
 *
 * Run from the application root:
 *   php tools/convert_radius_authoritative.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$root = realpath(__DIR__ . '/..');
if ($root === false || !is_file($root . '/init.php')) {
    fwrite(STDERR, "Application root not found. Place this file in tools/.\n");
    exit(2);
}

$changed = [];
$backups = [];

function writeAtomic($path, $content)
{
    $tmp = $path . '.radius-auth.tmp';
    if (file_put_contents($tmp, $content) === false) {
        throw new RuntimeException("Could not write temporary file: $tmp");
    }
    @chmod($tmp, fileperms($path) & 0777);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Could not replace file: $path");
    }
}

function patchFile($root, $relative, callable $transform, array &$changed, array &$backups)
{
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: $relative");
    }
    $old = file_get_contents($path);
    if ($old === false) {
        throw new RuntimeException("Could not read: $relative");
    }
    $new = $transform($old);
    if (!is_string($new) || $new === '') {
        throw new RuntimeException("Patch generated invalid content for: $relative");
    }
    if ($new === $old) {
        echo "UNCHANGED $relative\n";
        return;
    }

    $backup = $path . '.radius-auth.bak';
    if (!is_file($backup)) {
        if (!copy($path, $backup)) {
            throw new RuntimeException("Could not create backup: $backup");
        }
        $backups[$relative] = $backup;
    }
    writeAtomic($path, $new);
    $changed[] = $relative;
    echo "PATCHED   $relative\n";
}

function injectGuard(&$src, $pattern, $guard, $label)
{
    if (strpos($src, "RS_RADIUS_AUTHORITATIVE:$label") !== false) {
        return;
    }
    $count = 0;
    $src = preg_replace_callback($pattern, function ($m) use ($guard, $label) {
        return $m[0] . "\n        // RS_RADIUS_AUTHORITATIVE:$label\n" . $guard;
    }, $src, 1, $count);
    if ($count !== 1) {
        throw new RuntimeException("Could not patch method: $label");
    }
}

try {
    patchFile($root, 'system/devices/MikrotikHotspot.php', function ($src) {
        if (strpos($src, 'RS_RADIUS_AUTHORITATIVE:helpers') === false) {
            $needle = "class MikrotikHotspot\n{\n";
            if (strpos($src, $needle) === false) {
                throw new RuntimeException('MikrotikHotspot class marker not found');
            }
            $helpers = <<<'CODE'

    // RS_RADIUS_AUTHORITATIVE:helpers
    /**
     * Automatic RS/WireGuard routers use FreeRADIUS as the subscriber authority.
     * Legacy/manual routers keep the original local MikroTik Hotspot behaviour.
     */
    private function rsRadiusAuthoritative($routerName)
    {
        $routerName = trim((string) $routerName);
        if ($routerName === '') {
            return false;
        }
        try {
            $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
            if (!$router) {
                return false;
            }
            $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
            $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
            if ($transport !== 'wireguard' || $tunnelIp === '') {
                return false;
            }
            global $config;
            if (isset($config['radius_enable'])) {
                $enabled = strtolower(trim((string) $config['radius_enable']));
                if (in_array($enabled, ['0', 'no', 'false', 'off', 'disabled'], true)) {
                    return false;
                }
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function rsRadiusDevice()
    {
        if (!class_exists('Radius')) {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'Radius.php';
            if (!is_file($file)) {
                throw new RuntimeException('Radius device file is missing');
            }
            require_once $file;
        }
        if (!class_exists('Radius')) {
            throw new RuntimeException('Radius device class is unavailable');
        }
        return new Radius();
    }
CODE;
            $src = str_replace($needle, $needle . $helpers . "\n", $src, $count);
            if ($count !== 1) {
                throw new RuntimeException('Could not insert RADIUS helper methods');
            }
        }

        injectGuard(
            $src,
            '/function\s+add_customer\s*\(\s*\$customer\s*,\s*\$plan\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->add_customer(\$customer, \$plan);\n        }\n",
            'add_customer'
        );
        injectGuard(
            $src,
            '/function\s+sync_customer\s*\(\s*\$customer\s*,\s*\$plan\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->sync_customer(\$customer, \$plan);\n        }\n",
            'sync_customer'
        );
        injectGuard(
            $src,
            '/function\s+remove_customer\s*\(\s*\$customer\s*,\s*\$plan\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->remove_customer(\$customer, \$plan);\n        }\n",
            'remove_customer'
        );
        injectGuard(
            $src,
            '/(?:public\s+)?function\s+change_username\s*\(\s*\$plan\s*,\s*\$from\s*,\s*\$to\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->change_username(\$plan, \$from, \$to);\n        }\n",
            'change_username'
        );
        injectGuard(
            $src,
            '/function\s+add_plan\s*\(\s*\$plan\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->add_plan(\$plan);\n        }\n",
            'add_plan'
        );
        injectGuard(
            $src,
            '/function\s+update_plan\s*\(\s*\$old_plan\s*,\s*\$new_plan\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$new_plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->update_plan(\$old_plan, \$new_plan);\n        }\n",
            'update_plan'
        );
        injectGuard(
            $src,
            '/function\s+remove_plan\s*\(\s*\$plan\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$plan['routers'] ?? '')) {\n            return \$this->rsRadiusDevice()->remove_plan(\$plan);\n        }\n",
            'remove_plan'
        );
        injectGuard(
            $src,
            '/function\s+connect_customer\s*\(\s*\$customer\s*,\s*\$ip\s*,\s*\$mac_address\s*,\s*\$router_name\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$router_name)) {\n            // Never bypass RADIUS with /ip/hotspot/active/login.\n            return false;\n        }\n",
            'connect_customer'
        );
        injectGuard(
            $src,
            '/function\s+disconnect_customer\s*\(\s*\$customer\s*,\s*\$router_name\s*\)\s*\{/',
            "        if (\$this->rsRadiusAuthoritative(\$router_name)) {\n            return \$this->rsRadiusDevice()->disconnect_customer(\$customer, \$router_name);\n        }\n",
            'disconnect_customer'
        );

        return $src;
    }, $changed, $backups);

    patchFile($root, 'system/plugin/CreateHotspotUser.php', function ($src) {
        if (strpos($src, 'RS_RADIUS_AUTHORITATIVE:username-helper') === false) {
            $needle = "function CreateHotspotuser()\n{\n    Alloworigins();\n}\n";
            if (strpos($src, $needle) === false) {
                throw new RuntimeException('CreateHotspotuser marker not found');
            }
            $helper = <<<'CODE'

// RS_RADIUS_AUTHORITATIVE:username-helper
/**
 * True when this subscriber belongs to a router onboarded by the RS
 * WireGuard/RADIUS workflow. These subscribers must authenticate through
 * MikroTik -> FreeRADIUS, never through RouterOS API active/login.
 */
function PamnetRadiusAuthoritativeUsername($username)
{
    $username = trim((string) $username);
    if ($username === '') {
        return false;
    }
    try {
        $routerName = '';

        $recharge = ORM::for_table('tbl_user_recharges')
            ->where('username', $username)
            ->order_by_desc('id')
            ->find_one();
        if ($recharge) {
            $routerName = trim((string) ($recharge['routers'] ?? ''));
            if ($routerName === '' && !empty($recharge['plan_id'])) {
                $plan = ORM::for_table('tbl_plans')->where('id', $recharge['plan_id'])->find_one();
                if ($plan) {
                    $routerName = trim((string) ($plan['routers'] ?? ''));
                }
            }
        }

        if ($routerName === '') {
            $pg = ORM::for_table('tbl_payment_gateway')
                ->where('username', $username)
                ->order_by_desc('id')
                ->find_one();
            if ($pg) {
                $routerName = trim((string) ($pg['routers'] ?? ''));
            }
        }

        $router = null;
        if ($routerName !== '') {
            $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
        }
        if (!$router) {
            $customer = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
            $routerId = $customer ? (int) ($customer['router_id'] ?? 0) : 0;
            if ($routerId > 0) {
                $router = ORM::for_table('tbl_routers')->find_one($routerId);
            }
        }
        if (!$router) {
            return false;
        }

        $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
        $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
        return $transport === 'wireguard' && $tunnelIp !== '';
    } catch (Throwable $e) {
        return false;
    }
}
CODE;
            $src = str_replace($needle, $needle . $helper . "\n", $src, $count);
            if ($count !== 1) {
                throw new RuntimeException('Could not insert RADIUS username helper');
            }
        }

        if (strpos($src, 'RS_RADIUS_AUTHORITATIVE:browser-radius-login') === false) {
            $needle = "    \$username = trim((string) \$username);\n    \$ip = trim((string) \$ip);\n    \$mac = strtoupper(trim(str_replace('-', ':', (string) \$mac)));\n";
            $pos = strpos($src, $needle, strpos($src, 'function PamnetHotspotAutologin'));
            if ($pos === false) {
                throw new RuntimeException('PamnetHotspotAutologin normalization block not found');
            }
            $guard = <<<'CODE'

    // RS_RADIUS_AUTHORITATIVE:browser-radius-login
    // RS/WireGuard routers must authenticate the browser through the normal
    // MikroTik Hotspot login page, which sends Access-Request to FreeRADIUS.
    // Do not use RouterOS API /ip/hotspot/active/login for these subscribers.
    if (PamnetRadiusAuthoritativeUsername($username)) {
        return [
            'ok' => false,
            'logged_in' => false,
            'message' => 'RADIUS authentication required via MikroTik Hotspot login',
            'fallback_pap' => true,
            'auth_mode' => 'radius',
        ];
    }
CODE;
            $insertAt = $pos + strlen($needle);
            $src = substr($src, 0, $insertAt) . $guard . "\n" . substr($src, $insertAt);
        }

        return $src;
    }, $changed, $backups);

    // Validate PHP syntax before touching the database.
    foreach ($changed as $relative) {
        $path = $root . '/' . $relative;
        $out = [];
        $code = 1;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        echo implode("\n", $out) . "\n";
        if ($code !== 0) {
            throw new RuntimeException("PHP syntax validation failed for $relative");
        }
    }

    // Load the app only after source validation. This makes plan metadata explicit
    // for already-onboarded RS/WireGuard routers; runtime guards still protect any
    // new plans that are later created with device=MikrotikHotspot.
    require_once $root . '/init.php';

    // Hard safety gate: only make plan.device=Radius when the billing app can
    // actually use its named RADIUS database connection.
    $radiusFlag = strtolower(trim((string) ($config['radius_enable'] ?? '')));
    if (in_array($radiusFlag, ['', '0', 'no', 'false', 'off', 'disabled'], true)) {
        throw new RuntimeException('RADIUS is not enabled in billing configuration (radius_enable). No plans were migrated.');
    }
    try {
        $radiusDb = ORM::get_db('radius');
        $probe = $radiusDb->query('SELECT 1 FROM radcheck LIMIT 1');
        if ($probe === false) {
            throw new RuntimeException('radcheck query failed');
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Billing RADIUS database connection is not ready: ' . $e->getMessage());
    }

    $convertedPlans = 0;
    try {
        $routers = ORM::for_table('tbl_routers')->find_many();
        foreach ($routers as $router) {
            $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
            $tunnelIp = trim((string) ($router['wg_tunnel_ip'] ?? ''));
            $name = trim((string) ($router['name'] ?? ''));
            if ($transport !== 'wireguard' || $tunnelIp === '' || $name === '') {
                continue;
            }
            $plans = ORM::for_table('tbl_plans')
                ->where('routers', $name)
                ->where('type', 'Hotspot')
                ->find_many();
            foreach ($plans as $plan) {
                if (strcasecmp(trim((string) ($plan['device'] ?? '')), 'Radius') !== 0) {
                    $plan->device = 'Radius';
                    $plan->save();
                    $convertedPlans++;
                    echo "PLAN      #" . $plan['id'] . " " . $plan['name_plan'] . " -> Radius (router $name)\n";
                }
            }
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Source patched, but plan migration failed: ' . $e->getMessage());
    }

    echo "\nRADIUS_AUTHORITATIVE_CONVERSION_OK\n";
    echo "files_changed=" . count($changed) . "\n";
    echo "plans_converted=" . $convertedPlans . "\n";
    echo "Legacy/manual routers remain on their original MikroTik Hotspot path.\n";
    echo "RS/WireGuard routers now provision subscribers in RADIUS and browser login goes through MikroTik -> FreeRADIUS.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    // Roll back source files modified during this run.
    foreach (array_reverse($changed) as $relative) {
        $backup = $root . '/' . $relative . '.radius-auth.bak';
        if (is_file($backup)) {
            @copy($backup, $root . '/' . $relative);
            fwrite(STDERR, "ROLLED BACK $relative\n");
        }
    }
    exit(1);
}
