<?php
/**
 * Customer connection status: Online / Offline / Expired
 * Uses tbl_usage_sessions (synced from MikroTik) + live Hotspot active fallback
 * + tbl_user_recharges.
 */
class PamnetCustomerStatus
{
    /** Match Customer Usage "Active Now" window so list does not flicker Offline. */
    public static function onlineWindowSeconds(): int
    {
        return 300; // 5 minutes
    }

    /**
     * @return array{status:string,label:string,plan:string,last_seen:?string,expires:?string}
     */
    public static function forCustomer($customerId, $username = null)
    {
        $customerId = (int) $customerId;
        if ($username === null || $username === '') {
            $c = ORM::for_table('tbl_customers')->select('username')->find_one($customerId);
            $username = $c ? (string) $c['username'] : '';
        }
        $map = self::forCustomers([['id' => $customerId, 'username' => $username]]);
        return $map[$customerId] ?? [
            'status' => 'expired',
            'label' => 'Expired',
            'plan' => '',
            'last_seen' => null,
            'expires' => null,
        ];
    }

    /**
     * @param list<array{id:int|string,username?:string}|object> $customers
     * @return array<int, array{status:string,label:string,plan:string,last_seen:?string,expires:?string}>
     */
    public static function forCustomers(array $customers)
    {
        $out = [];
        $ids = [];
        $usernames = [];
        $idToUser = [];

        foreach ($customers as $c) {
            $id = (int) (is_array($c) ? ($c['id'] ?? 0) : ($c->id ?? 0));
            if ($id <= 0) {
                continue;
            }
            $user = (string) (is_array($c) ? ($c['username'] ?? '') : ($c->username ?? ''));
            $ids[] = $id;
            $idToUser[$id] = $user;
            if ($user !== '') {
                $usernames[$user] = true;
            }
            $out[$id] = [
                'status' => 'expired',
                'label' => 'Expired',
                'plan' => '',
                'last_seen' => null,
                'expires' => null,
            ];
        }
        if (!$ids) {
            return $out;
        }

        $now = time();
        $cutoff = date('Y-m-d H:i:s', $now - self::onlineWindowSeconds());

        // Active (or most recent) recharge per customer
        $recharges = ORM::for_table('tbl_user_recharges')
            ->where_in('customer_id', $ids)
            ->order_by_desc('id')
            ->find_array();
        $best = [];
        foreach ($recharges as $r) {
            $cid = (int) $r['customer_id'];
            if (isset($best[$cid]) && !empty($best[$cid]['_active'])) {
                continue;
            }
            $expTs = strtotime(trim(($r['expiration'] ?? '') . ' ' . ($r['time'] ?? '23:59:59')));
            if ($expTs === false) {
                $expTs = 0;
            }
            $active = ((string) ($r['status'] ?? '') === 'on') && $expTs > $now;
            if (!isset($best[$cid]) || ($active && empty($best[$cid]['_active']))) {
                $best[$cid] = [
                    '_active' => $active,
                    'plan' => (string) ($r['namebp'] ?? ''),
                    'expires' => $expTs > 0 ? date('Y-m-d H:i:s', $expTs) : null,
                    'routers' => (string) ($r['routers'] ?? ''),
                    'username' => (string) ($r['username'] ?? ''),
                ];
            }
            // Prefer recharge username (Hotspot code) over customer.username when present
            if ($active && !empty($r['username'])) {
                $idToUser[$cid] = (string) $r['username'];
                $usernames[(string) $r['username']] = true;
            }
        }

        // Online sessions from DB sync
        $onlineUsers = [];
        if ($usernames) {
            try {
                $sessions = ORM::for_table('tbl_usage_sessions')
                    ->where_in('username', array_keys($usernames))
                    ->where_gte('last_seen', $cutoff)
                    ->find_array();
                foreach ($sessions as $s) {
                    $u = (string) $s['username'];
                    $ls = (string) ($s['last_seen'] ?? '');
                    if (!isset($onlineUsers[$u]) || $ls > $onlineUsers[$u]) {
                        $onlineUsers[$u] = $ls;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        // Live MikroTik fallback for active plans still missing a session row
        $needLive = [];
        foreach ($idToUser as $id => $user) {
            if ($user === '' || isset($onlineUsers[$user])) {
                continue;
            }
            if (!empty($best[$id]['_active'])) {
                $needLive[$user] = $best[$id]['routers'] ?? '';
            }
        }
        if ($needLive) {
            foreach (self::liveHotspotOnlineUsernames(array_keys($needLive)) as $u => $seen) {
                $onlineUsers[$u] = $seen;
            }
        }

        foreach ($idToUser as $id => $user) {
            $planActive = !empty($best[$id]['_active']);
            $isOnline = ($user !== '' && isset($onlineUsers[$user]));
            $lastSeen = $isOnline ? $onlineUsers[$user] : null;

            if ($planActive && $isOnline) {
                $status = 'online';
            } elseif ($planActive) {
                $status = 'offline';
            } else {
                $status = 'expired';
            }

            $labels = ['online' => 'Online', 'offline' => 'Offline', 'expired' => 'Expired'];
            $out[$id] = [
                'status' => $status,
                'label' => $labels[$status],
                'plan' => $best[$id]['plan'] ?? '',
                'last_seen' => $lastSeen,
                'expires' => $best[$id]['expires'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Quick live check against MikroTik /ip/hotspot/active for given usernames.
     * @param list<string> $usernames
     * @return array<string,string> username => last_seen datetime
     */
    public static function liveHotspotOnlineUsernames(array $usernames)
    {
        $want = [];
        foreach ($usernames as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $want[$u] = true;
            }
        }
        if (!$want) {
            return [];
        }
        $found = [];
        $now = date('Y-m-d H:i:s');
        try {
            $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
        } catch (Throwable $e) {
            return [];
        }
        foreach ($routers as $router) {
            try {
                if (!class_exists('Mikrotik')) {
                    break;
                }
                $client = Mikrotik::getClient($router->ip_address, $router->username, $router->password);
                $req = new \PEAR2\Net\RouterOS\Request('/ip/hotspot/active/print');
                $req->setArgument('.proplist', 'user');
                foreach ($client->sendSync($req) as $row) {
                    $u = trim((string) $row->getProperty('user'));
                    if ($u !== '' && isset($want[$u])) {
                        $found[$u] = $now;
                        // Opportunistically refresh usage session so next page load is fast
                        self::touchUsageSession($u, (int) $router->id);
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
            // Stop early if all found
            if (count($found) >= count($want)) {
                break;
            }
        }
        return $found;
    }

    /** Upsert a lightweight usage session so Online stays green after force-login. */
    public static function touchUsageSession($username, $routerId = 0, $ip = '', $mac = '')
    {
        $username = trim((string) $username);
        if ($username === '') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        try {
            if ($routerId <= 0) {
                $r = ORM::for_table('tbl_routers')->where('enabled', '1')->order_by_asc('id')->find_one();
                $routerId = $r ? (int) $r['id'] : 1;
            }
            $session = ORM::for_table('tbl_usage_sessions')
                ->where('username', $username)
                ->where('connection_type', 'hotspot')
                ->order_by_desc('id')
                ->find_one();
            if (!$session) {
                $session = ORM::for_table('tbl_usage_sessions')->create();
                $session->router_id = $routerId;
                $session->username = $username;
                $session->interface = 'hotspot';
                $session->session_id = 'live-' . $username . '-' . time();
                $session->start_time = $now;
                $session->connection_type = 'hotspot';
                $session->last_rx = 0;
                $session->last_tx = 0;
                $session->session_rx = 0;
                $session->session_tx = 0;
            }
            $session->last_seen = $now;
            $session->connection_type = 'hotspot';
            if ($ip !== '') {
                $session->ip_address = $ip;
            }
            if ($mac !== '') {
                $session->mac_address = strtoupper($mac);
            }
            $session->save();
        } catch (Throwable $e) {
        }
    }

    public static function buttonHtml($status, $extraTitle = '')
    {
        $status = strtolower((string) $status);
        $map = [
            'online' => ['Online', 'btn-success btn-conn-online', 'fa-wifi', 'background:#16a34a!important;border-color:#15803d!important;color:#fff!important;'],
            'offline' => ['Offline', 'btn-warning btn-conn-offline', 'fa-unlink', 'background:#f59e0b!important;border-color:#d97706!important;color:#fff!important;'],
            'expired' => ['Expired', 'btn-danger btn-conn-expired', 'fa-clock-o', 'background:#dc2626!important;border-color:#b91c1c!important;color:#fff!important;'],
        ];
        if (!isset($map[$status])) {
            $status = 'expired';
        }
        [$label, $cls, $icon, $style] = $map[$status];
        $title = htmlspecialchars($extraTitle !== '' ? $extraTitle : $label, ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="btn ' . $cls . ' btn-xs btn-conn-status" disabled '
            . 'data-status="' . $status . '" title="' . $title . '" style="' . $style . 'opacity:1!important;">'
            . '<i class="fa ' . $icon . '"></i> ' . $label . '</button>';
    }
}
