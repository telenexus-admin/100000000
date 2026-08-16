#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import shutil

ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / 'system/devices/Radius.php'
STAMP = datetime.now().strftime('%Y%m%d-%H%M%S')
BACKUP = Path('/var/backups') / f'Radius.php.before-nas-binding-{STAMP}'

if not TARGET.exists():
    raise SystemExit(f'Missing {TARGET}')

text = TARGET.read_text()

helper_marker = '''    private function delAtribute($table, $attribute, $key, $value)\n    {\n'''
helper = r'''    /**
     * Bind SQL-backed Hotspot credentials to the RS/WireGuard NAS that owns
     * the plan.  This is a FreeRADIUS radcheck comparison item, not a reply
     * attribute.  A username bought on router A must not authenticate on B.
     */
    public function syncHotspotNasBinding($username, $plan)
    {
        $username = trim((string) $username);
        if ($username === '' || !$plan || strcasecmp((string) ($plan['type'] ?? ''), 'Hotspot') !== 0) {
            return;
        }

        $routerName = trim((string) ($plan['routers'] ?? ''));
        $tunnelIp = '';
        if ($routerName !== '') {
            try {
                $router = ORM::for_table('tbl_routers')->where('name', $routerName)->find_one();
                if ($router) {
                    $transport = strtolower(trim((string) ($router['management_transport'] ?? '')));
                    $candidate = trim((string) ($router['wg_tunnel_ip'] ?? ''));
                    if ($transport === 'wireguard' && filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $tunnelIp = $candidate;
                    }
                }
            } catch (Throwable $e) {
                $tunnelIp = '';
            }
        }

        if ($tunnelIp !== '') {
            $this->upsertCustomer($username, 'NAS-IP-Address', $tunnelIp, '==');
            return;
        }

        // If a Hotspot account is deliberately moved back to a legacy/manual
        // Radius plan, do not leave an old RS NAS restriction behind.
        $this->delAtribute($this->getTableCustomer(), 'NAS-IP-Address', 'username', $username);
    }

'''

call_old = '''        $this->upsertCustomer($customer['username'], 'Mikrotik-Wireless-Comment', $customer['fullname']);\n        return true;\n'''
call_new = '''        $this->upsertCustomer($customer['username'], 'Mikrotik-Wireless-Comment', $customer['fullname']);\n        $this->syncHotspotNasBinding($customer['username'], $plan);\n        return true;\n'''

already = 'public function syncHotspotNasBinding($username, $plan)' in text
if not already:
    if helper_marker not in text:
        raise SystemExit('Radius.php helper insertion marker not found; no changes made')
    if call_old not in text:
        raise SystemExit('Radius.php customerUpsert call marker not found; no changes made')

    BACKUP.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(TARGET, BACKUP)
    text = text.replace(helper_marker, helper + helper_marker, 1)
    text = text.replace(call_old, call_new, 1)
    TARGET.write_text(text)
    print('UPDATED: system/devices/Radius.php')
    print(f'Backup: {BACKUP}')
else:
    print('Radius.php: NAS binding already installed')

print('Expected future radcheck item: NAS-IP-Address == <router wg_tunnel_ip>')
