<?php
/**
 * PamNet Hotspot compatibility core (phones, Smart TVs, STBs, PCs).
 *
 * Device detection is OPTIONAL — used for logging/UI only.
 * Authentication and Internet access never depend on brand, model, OS, or User-Agent.
 *
 * Loaded early via init.php plugin glob (00_* prefix).
 */

use PEAR2\Net\RouterOS;

/**
 * Hosts unauthenticated clients may reach BEFORE login.
 *
 * IMPORTANT: Do NOT include captive-portal detection URLs here
 * (connectivitycheck.gstatic.com, captive.apple.com, msftconnecttest, etc.).
 * Allowing those makes phones think the Wi-Fi has Internet while other traffic
 * is still blocked → "Connected without Internet" and no Sign-In popup.
 * MikroTik must intercept those probes and redirect to the Hotspot login page.
 *
 * @return string[]
 */
function pamnet_walled_garden_hosts($billingHost = '')
{
    $hosts = [
        'net.pamnetsolutions.co.ke',
        '*.net.pamnetsolutions.co.ke',
        'pamnetsolutions.co.ke',
        '*.pamnetsolutions.co.ke',
        // Portal UI assets only (not Google/Apple connectivity probes)
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'cdn.tailwindcss.com',
        'ajax.googleapis.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'unpkg.com',
        'code.jquery.com',
        'sweetalert2.github.io',
    ];

    $billingHost = trim((string) $billingHost);
    if ($billingHost !== '') {
        array_unshift($hosts, $billingHost, '*.' . $billingHost);
    }

    return array_values(array_unique(array_filter($hosts)));
}

/**
 * Captive-portal probe hosts that must NOT be in the walled garden.
 * @return string[]
 */
function pamnet_captive_probe_hosts()
{
    return [
        'connectivitycheck.gstatic.com',
        'connectivitycheck.android.com',
        'clients3.google.com',
        'www.google.com',
        'google.com',
        'www.msftconnecttest.com',
        'msftconnecttest.com',
        'www.msftncsi.com',
        'msftncsi.com',
        'captive.apple.com',
        'www.apple.com',
        'apple.com',
        'detectportal.firefox.com',
        'neverssl.com',
        'gstatic.com',
        '*.gstatic.com',
        'www.gstatic.com',
    ];
}

/**
 * @param \PEAR2\Net\RouterOS\Client $client
 */
function pamnet_ensure_walled_garden_hosts($client, $billingHost = '')
{
    // --- 1. Purge empty dst-host entries (they match ALL traffic = full internet bypass) ---
    try {
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
            $dst = trim((string) $row->getProperty('dst-host'));
            $id  = (string) $row->getProperty('.id');
            if ($dst === '' && $id !== '') {
                try {
                    $rem = new RouterOS\Request('/ip/hotspot/walled-garden/remove');
                    $rem->setArgument('.id', $id);
                    $client->sendSync($rem);
                } catch (Throwable $eRem) {
                }
            }
        }
    } catch (Throwable $e) {
    }

    // --- 1b. Remove captive-portal probe hosts (must be intercepted by Hotspot) ---
    $probeHosts = [];
    foreach (pamnet_captive_probe_hosts() as $ph) {
        $probeHosts[strtolower(trim($ph))] = true;
    }
    try {
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
            $dst = strtolower(trim((string) $row->getProperty('dst-host')));
            $id = (string) $row->getProperty('.id');
            if ($dst === '' || $id === '' || !isset($probeHosts[$dst])) {
                continue;
            }
            try {
                $rem = new RouterOS\Request('/ip/hotspot/walled-garden/remove');
                $rem->setArgument('.id', $id);
                $client->sendSync($rem);
            } catch (Throwable $eRem) {
            }
        }
    } catch (Throwable $e) {
    }

    // --- 2. Build list of known-good hosts ---
    $hosts = pamnet_walled_garden_hosts($billingHost);

    $existing = [];
    try {
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/print')) as $row) {
            $dst = strtolower(trim((string) $row->getProperty('dst-host')));
            if ($dst !== '') {
                $existing[$dst] = true;
            }
        }
    } catch (Throwable $e) {
        $existing = [];
    }

    $added = 0;
    foreach ($hosts as $h) {
        $h = trim((string) $h);
        if ($h === '') {
            continue; // never add blank hosts
        }
        $key = strtolower($h);
        if (isset($existing[$key])) {
            continue;
        }
        try {
            $add = new RouterOS\Request('/ip/hotspot/walled-garden/add');
            $add->setArgument('dst-host', $h);
            $client->sendSync($add);
            $existing[$key] = true;
            $added++;
        } catch (Throwable $e) {
        }
    }

    // --- 3. Walled-garden IP: add billing host entry only if not already present ---
    $billingHost = trim((string) $billingHost);
    if ($billingHost !== '') {
        $existingIp = [];
        try {
            foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/walled-garden/ip/print')) as $row) {
                $dst = strtolower(trim((string) $row->getProperty('dst-host')));
                $id  = (string) $row->getProperty('.id');
                // Also remove malformed entries (empty dst-host in ip table)
                if ($dst === '' && $id !== '') {
                    try {
                        $rem = new RouterOS\Request('/ip/hotspot/walled-garden/ip/remove');
                        $rem->setArgument('.id', $id);
                        $client->sendSync($rem);
                    } catch (Throwable $eRem) {
                    }
                } elseif ($dst !== '') {
                    $existingIp[$dst] = true;
                }
            }
        } catch (Throwable $e) {
        }

        if (!isset($existingIp[strtolower($billingHost)])) {
            try {
                $addIp = new RouterOS\Request('/ip/hotspot/walled-garden/ip/add');
                $addIp->setArgument('action', 'accept');
                $addIp->setArgument('dst-host', $billingHost);
                $client->sendSync($addIp);
            } catch (Throwable $e) {
            }
        }
    }

    return $added;
}

/**
 * Ensure firewall rules are in place for correct hotspot behaviour:
 *  - Reject port 853 (DNS-over-TLS/DoT) on the hotspot bridge so Android
 *    falls back to plain port-53 DNS through MikroTik and correctly detects
 *    the captive portal instead of showing "Private DNS cannot be accessed".
 *  - Accept port 53 (DNS) from hotspot clients so MikroTik resolves DNS.
 *
 * Safe to call repeatedly — checks for existing rules by comment tag.
 *
 * @param \PEAR2\Net\RouterOS\Client $client
 * @param string $hotspotBridge  e.g. "hotspot_bridge"
 */
function pamnet_ensure_hotspot_firewall_rules($client, $hotspotBridge = 'hotspot_bridge')
{
    $hotspotBridge = trim((string) $hotspotBridge);
    if ($hotspotBridge === '') {
        return;
    }

    // Read existing filter comments (all chains)
    $existingComments = [];
    try {
        foreach ($client->sendSync(new RouterOS\Request('/ip/firewall/filter/print')) as $f) {
            $existingComments[] = (string) $f->getProperty('comment');
        }
    } catch (Throwable $e) {
        return;
    }

    $hasComment = function (string $tag) use (&$existingComments): bool {
        foreach ($existingComments as $c) {
            if (strpos($c, $tag) !== false) {
                return true;
            }
        }
        return false;
    };

    $rules = [
        // Allow hotspot login redirect ports from clients (before any drop)
        [
            'tag'          => 'PAMNET-HS-HTTP-redir',
            'chain'        => 'input',
            'protocol'     => 'tcp',
            'dst-port'     => '64873',
            'in-interface' => $hotspotBridge,
            'action'       => 'accept',
            'comment'      => 'PAMNET-HS-HTTP-redir: allow MikroTik hotspot redirect/login ports',
        ],
        [
            'tag'          => 'PAMNET-HS-HTTPS-redir',
            'chain'        => 'input',
            'protocol'     => 'tcp',
            'dst-port'     => '64875',
            'in-interface' => $hotspotBridge,
            'action'       => 'accept',
            'comment'      => 'PAMNET-HS-HTTPS-redir: allow MikroTik hotspot redirect/login ports',
        ],
        [
            'tag'          => 'PAMNET-HS-DNS-redir',
            'chain'        => 'input',
            'protocol'     => 'udp',
            'dst-port'     => '64872',
            'in-interface' => $hotspotBridge,
            'action'       => 'accept',
            'comment'      => 'PAMNET-HS-DNS-redir: allow MikroTik hotspot redirect/login ports',
        ],
        [
            'tag'          => 'PAMNET-HS-DNS-redir-tcp',
            'chain'        => 'input',
            'protocol'     => 'tcp',
            'dst-port'     => '64872',
            'in-interface' => $hotspotBridge,
            'action'       => 'accept',
            'comment'      => 'PAMNET-HS-DNS-redir-tcp: allow MikroTik hotspot redirect/login ports',
        ],
        // Reject TCP/853 (DoT) on INPUT — Android falls back to port-53
        [
            'tag'          => 'PAMNET-DoT-reject',
            'chain'        => 'input',
            'protocol'     => 'tcp',
            'dst-port'     => '853',
            'in-interface' => $hotspotBridge,
            'action'       => 'reject',
            'reject-with'  => 'tcp-reset',
            'comment'      => 'PAMNET-DoT-reject: block DNS-over-TLS on hotspot so Android falls back to port-53 and shows captive portal',
        ],
        // Reject UDP/853 (DoQ/DoD)
        [
            'tag'          => 'PAMNET-DoT-udp-reject',
            'chain'        => 'input',
            'protocol'     => 'udp',
            'dst-port'     => '853',
            'in-interface' => $hotspotBridge,
            'action'       => 'reject',
            'reject-with'  => 'icmp-port-unreachable',
            'comment'      => 'PAMNET-DoT-udp-reject: block DoT/DoQ on hotspot so Android falls back to port-53',
        ],
        // Accept UDP/53 (DNS) from hotspot clients
        [
            'tag'          => 'PAMNET-DNS-allow',
            'chain'        => 'input',
            'protocol'     => 'udp',
            'dst-port'     => '53',
            'in-interface' => $hotspotBridge,
            'action'       => 'accept',
            'comment'      => 'PAMNET-DNS-allow: allow DNS queries from hotspot clients',
        ],
        // Accept TCP/53 (DNS-over-TCP) from hotspot clients
        [
            'tag'          => 'PAMNET-DNS-tcp-allow',
            'chain'        => 'input',
            'protocol'     => 'tcp',
            'dst-port'     => '53',
            'in-interface' => $hotspotBridge,
            'action'       => 'accept',
            'comment'      => 'PAMNET-DNS-tcp-allow: allow DNS/TCP from hotspot clients',
        ],
        // FORWARD: reject DoT to external resolvers (Private DNS) so Android falls back fast
        [
            'tag'          => 'PAMNET-DoT-fwd-reject',
            'chain'        => 'forward',
            'protocol'     => 'tcp',
            'dst-port'     => '853',
            'in-interface' => $hotspotBridge,
            'action'       => 'reject',
            'reject-with'  => 'tcp-reset',
            'comment'      => 'PAMNET-DoT-fwd-reject: force Android Private DNS fallback to port-53',
        ],
        [
            'tag'          => 'PAMNET-DoT-fwd-udp-reject',
            'chain'        => 'forward',
            'protocol'     => 'udp',
            'dst-port'     => '853',
            'in-interface' => $hotspotBridge,
            'action'       => 'reject',
            'reject-with'  => 'icmp-port-unreachable',
            'comment'      => 'PAMNET-DoT-fwd-udp-reject: force Android Private DNS fallback to port-53',
        ],
    ];

    foreach ($rules as $rule) {
        if ($hasComment($rule['tag'])) {
            continue;
        }
        try {
            $add = new RouterOS\Request('/ip/firewall/filter/add');
            foreach ($rule as $k => $v) {
                if ($k === 'tag') {
                    continue;
                }
                $add->setArgument($k, $v);
            }
            $client->sendSync($add);
        } catch (Throwable $e) {
        }
    }

    // Remove DNS static hijacks for captive probes (keep hotspot dns-name only)
    if (function_exists('pamnet_captive_probe_hosts')) {
        $probeDns = [];
        foreach (pamnet_captive_probe_hosts() as $ph) {
            $probeDns[strtolower(trim($ph))] = true;
        }
        try {
            foreach ($client->sendSync(new RouterOS\Request('/ip/dns/static/print')) as $row) {
                $name = strtolower(trim((string) $row->getProperty('name')));
                $id = (string) $row->getProperty('.id');
                if ($name === '' || $id === '' || !isset($probeDns[$name])) {
                    continue;
                }
                try {
                    $rem = new RouterOS\Request('/ip/dns/static/remove');
                    $rem->setArgument('.id', $id);
                    $client->sendSync($rem);
                } catch (Throwable $eRem) {
                }
            }
        } catch (Throwable $e) {
        }
    }
}

function PamnetNormalizeMac($mac)
{
    $mac = strtoupper(str_replace('-', ':', trim((string) $mac)));
    if ($mac !== '' && !preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
        return '';
    }
    return $mac;
}

/**
 * Optional server-side device hint from User-Agent (analytics only — never gates access).
 */
function PamnetClassifyDeviceFromUserAgent($userAgent)
{
    $ua = trim((string) $userAgent);
    if ($ua === '') {
        return 'UNKNOWN_DEVICE';
    }
    if (preg_match('/SmartTV|Smart-TV|Smart TV|GoogleTV|Google TV|Android TV|BRAVIA|Tizen|webOS|Web0S|NetCast|VIDAA|HbbTV|Opera TV|AFT[A-Z]|Roku|CrKey|MiTV|MiBox|Shield|SetTopBox|Set-Top|DTV|TV/i', $ua)) {
        return 'TV_CLIENT';
    }
    return 'CLIENT';
}

/**
 * Normalize portal JSON client identity. Unknown TV/phone → UNKNOWN_TV / UNKNOWN_DEVICE — never rejected.
 *
 * @return array{mac:string,ip:string,device:string}
 */
function PamnetParsePortalClient(array $input)
{
    $mac = PamnetNormalizeMac($input['mac'] ?? $input['mac_address'] ?? '');
    $ip = trim((string) ($input['ip'] ?? ''));
    if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip = '';
    }

    $device = trim((string) ($input['device'] ?? ''));
    if ($device === '') {
        $device = PamnetClassifyDeviceFromUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');
    }
    if ($device === 'CLIENT' && $mac === '' && $ip === '') {
        $device = 'UNKNOWN_DEVICE';
    }

    return [
        'mac' => $mac,
        'ip' => $ip,
        'device' => $device,
    ];
}

function PamnetIsBlockedMac($mac)
{
    $mac = PamnetNormalizeMac($mac);
    if ($mac === '') {
        return false;
    }
    $blockedMacs = ['22:12:59:0C:45:58'];
    return in_array($mac, $blockedMacs, true);
}

function PamnetLogPortalClient($context, $username, array $client)
{
    if (!function_exists('_log')) {
        return;
    }
    $device = (string) ($client['device'] ?? 'UNKNOWN_DEVICE');
    $mac = (string) ($client['mac'] ?? '');
    $ip = (string) ($client['ip'] ?? '');
    _log(
        $context . ' user=' . $username
        . ' device=' . $device
        . ' mac=' . ($mac !== '' ? $mac : '-')
        . ' ip=' . ($ip !== '' ? $ip : '-'),
        'Hotspot',
        0
    );
}

/**
 * Resolve missing Hotspot client IP/MAC from MikroTik host/active tables.
 *
 * @return array{0:string,1:string} [ip, mac]
 */
function PamnetResolveHotspotClientIdentity($client, $ip, $mac)
{
    $ip = trim((string) $ip);
    $mac = PamnetNormalizeMac($mac);

    if ($ip !== '' && $mac !== '') {
        return [$ip, $mac];
    }

    $fillFromRow = function ($rowIp, $rowMac) use (&$ip, &$mac) {
        $rowIp = trim((string) $rowIp);
        $rowMac = PamnetNormalizeMac($rowMac);
        if ($ip === '' && $rowIp !== '' && filter_var($rowIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = $rowIp;
        }
        if ($mac === '' && $rowMac !== '') {
            $mac = $rowMac;
        }
    };

    try {
        foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/host/print')) as $h) {
            $hMac = strtoupper((string) $h->getProperty('mac-address'));
            $hIp = (string) $h->getProperty('address');
            if ($mac !== '' && $hMac === $mac) {
                $fillFromRow($hIp, $hMac);
                break;
            }
            if ($ip !== '' && $hIp === $ip) {
                $fillFromRow($hIp, $hMac);
                break;
            }
        }
    } catch (Throwable $eHost) {
    }

    if ($ip === '' || $mac === '') {
        try {
            foreach ($client->sendSync(new RouterOS\Request('/ip/hotspot/active/print')) as $a) {
                $aMac = strtoupper((string) $a->getProperty('mac-address'));
                $aIp = (string) $a->getProperty('address');
                if ($mac !== '' && $aMac === $mac) {
                    $fillFromRow($aIp, $aMac);
                    break;
                }
                if ($ip !== '' && $aIp === $ip) {
                    $fillFromRow($aIp, $aMac);
                    break;
                }
            }
        } catch (Throwable $eAct) {
        }
    }

    return [$ip, $mac];
}
