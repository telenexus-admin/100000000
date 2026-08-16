<?php

/**
 * Proven RouterOS 7.18-compatible WireGuard + API + FreeRADIUS onboarding
 * script builder. The command sequence mirrors the production v6 flow that
 * completed successfully on RouterOS 7.18.2.
 */
function radius_wireguard_build_routeros_script(
    string $routerName,
    string $tunnelIp,
    string $apiUser,
    string $apiPass,
    string $activationToken,
    string $callbackUrl,
    array $wireguard,
    array $radius,
    string $sharedSecret
): string {
    $q = static function (string $value): string {
        return str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', ' '],
            $value
        );
    };

    $wgInterface = trim((string)($wireguard['interface'] ?? 'wg-nuxhost'));
    $wgServerIp = trim((string)($wireguard['server_ip'] ?? ''));
    $wgPublicKey = trim((string)($wireguard['public_key'] ?? ''));
    $wgEndpoint = trim((string)($wireguard['endpoint'] ?? ''));
    $wgEndpointPort = (int)($wireguard['endpoint_port'] ?? 51821);
    $radiusAddress = trim((string)($radius['host'] ?? $wgServerIp));
    $authPort = (int)($radius['auth_port'] ?? 1812);
    $accountingPort = (int)($radius['accounting_port'] ?? 1813);
    $coaPort = (int)($radius['coa_port'] ?? 3799);
    $bootstrapDate = gmdate('Y-m-d');
    $bootstrapTime = gmdate('H:i:s');

    if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $wgInterface)
        || !filter_var($wgServerIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !filter_var($radiusAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || !preg_match('/^[A-Za-z0-9+\/]{43}=$/', $wgPublicKey)
        || $wgEndpoint === ''
        || $wgEndpointPort < 1 || $wgEndpointPort > 65535
        || $authPort < 1 || $authPort > 65535
        || $accountingPort < 1 || $accountingPort > 65535
        || $coaPort < 1 || $coaPort > 65535) {
        throw new RuntimeException('The WireGuard/RADIUS plan contains invalid network settings.');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{20,128}$/', $sharedSecret)) {
        throw new RuntimeException('The generated RADIUS shared secret is invalid.');
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{1,48}$/', $apiUser) || $apiPass === '') {
        throw new RuntimeException('The generated RouterOS API credentials are invalid.');
    }

    $safeRouterName = preg_replace('/[\r\n#]+/', ' ', $routerName) ?: 'MikroTik';
    $wgAddress = $tunnelIp.'/24';
    $serverCidr = $wgServerIp.'/32';

    $lines = [
        '# NuxHost WireGuard + RouterOS API + FreeRADIUS onboarding v6',
        '# Router: '.$safeRouterName,
        '# RouterOS 7.18-compatible bootstrap. Hotspot HTML is not replaced.',
        '/export file=nuxhost-before-wireguard-radius;',
        '{',
        '    :put "NuxHost: synchronizing router clock...";',
        '    /system/clock set date="'.$bootstrapDate.'" time="'.$bootstrapTime.'";',
        '    :do { /system/ntp/client set enabled=yes mode=unicast; } on-error={};',
        '    :do {',
        '        :if ([:len [/system/ntp/client/servers find where address="time.cloudflare.com"]] = 0) do={ /system/ntp/client/servers add address=time.cloudflare.com; };',
        '        :if ([:len [/system/ntp/client/servers find where address="time.google.com"]] = 0) do={ /system/ntp/client/servers add address=time.google.com; };',
        '    } on-error={};',
        '    :delay 2s;',
        '',
        '    :put "NuxHost: preparing WireGuard management tunnel...";',
        '    :if ([:len [/interface/wireguard find where name="'.$q($wgInterface).'"]] = 0) do={',
        '        /interface/wireguard add name="'.$q($wgInterface).'" mtu=1420 comment="NUXHOST-WG";',
        '    };',
        '    /interface/wireguard set [find where name="'.$q($wgInterface).'"] mtu=1420 disabled=no;',
        '    :if ([:len [/ip/address find where comment="NUXHOST-WG-IP"]] > 0) do={ /ip/address remove [find where comment="NUXHOST-WG-IP"]; };',
        '    /ip/address add address="'.$q($wgAddress).'" interface="'.$q($wgInterface).'" comment="NUXHOST-WG-IP";',
        '    :if ([:len [/interface/wireguard/peers find where comment="NUXHOST-WG-SERVER"]] > 0) do={ /interface/wireguard/peers remove [find where comment="NUXHOST-WG-SERVER"]; };',
        '    /interface/wireguard/peers add interface="'.$q($wgInterface).'" public-key="'.$q($wgPublicKey).'" endpoint-address="'.$q($wgEndpoint).'" endpoint-port='.$wgEndpointPort.' allowed-address="'.$q($serverCidr).'" persistent-keepalive=25s comment="NUXHOST-WG-SERVER";',
        '    :delay 1s;',
        '',
        '    :local nuxWgPub [/interface/wireguard get [find where name="'.$q($wgInterface).'"] public-key];',
        '    :if ([:len $nuxWgPub] < 40) do={ :error "NuxHost stopped: WireGuard public key was not generated."; };',
        '    :local nuxPayload ("{\"token\":\"'.$q($activationToken).'\",\"public_key\":\"" . $nuxWgPub . "\"}");',
        '',
        '    :put "NuxHost: activating server WireGuard peer...";',
        '    :local nuxActivationResult;',
        '    :do {',
        '        :set nuxActivationResult [/tool/fetch url="'.$q($callbackUrl).'" http-method=post http-header-field="Content-Type: application/json" http-data=$nuxPayload output=user as-value check-certificate=no];',
        '    } on-error={ :error "NuxHost stopped: server peer activation request failed."; };',
        '    :if (($nuxActivationResult->"status") != "finished") do={ :error "NuxHost stopped: server peer activation did not finish."; };',
        '    :local nuxActivationBody ($nuxActivationResult->"data");',
        '    :if ([:find $nuxActivationBody "\"status\":\"ok\""] = nil) do={ :error "NuxHost stopped: server rejected WireGuard peer activation."; };',
        '',
        '    # Generate encrypted traffic and confirm an authenticated WireGuard peer.',
        '    :local nuxPeer [/interface/wireguard/peers find where comment="NUXHOST-WG-SERVER"];',
        '    :if ([:len $nuxPeer] = 0) do={ :error "NuxHost stopped: WireGuard server peer is missing."; };',
        '    :local nuxHandshakeReady false;',
        '    :local nuxTry 0;',
        '    :while (($nuxTry < 15) && ($nuxHandshakeReady = false)) do={',
        '        :do { /ping address="'.$q($wgServerIp).'" count=1 interval=500ms; } on-error={};',
        '        :delay 1s;',
        '        :local nuxCurrentEndpoint [/interface/wireguard/peers get $nuxPeer current-endpoint-address];',
        '        :if ([:len $nuxCurrentEndpoint] > 0) do={ :set nuxHandshakeReady true; };',
        '        :set nuxTry ($nuxTry + 1);',
        '    };',
        '    :if ($nuxHandshakeReady = false) do={ :error "NuxHost stopped: WireGuard handshake was not established."; };',
        '    :put "NuxHost: WireGuard handshake confirmed.";',
        '',
        '    :put "NuxHost: WireGuard connected. Securing RouterOS API...";',
        '    :if ([:len [/user find where comment="Router API User"]] > 0) do={ /user remove [find where comment="Router API User"]; };',
        '    /user add name="'.$q($apiUser).'" password="'.$q($apiPass).'" group=full comment="Router API User";',
        '    /ip/service set [find where name="api"] disabled=no port=8728 address="'.$q($serverCidr).'";',
        '    :if ([:len [/ip/firewall/filter find where comment="NUXHOST-API"]] > 0) do={ /ip/firewall/filter remove [find where comment="NUXHOST-API"]; };',
        '    :local nuxDefaultInputDrop [/ip/firewall/filter find where comment="defconf: drop all not coming from LAN"];',
        '    :if ([:len $nuxDefaultInputDrop] > 0) do={',
        '        /ip/firewall/filter add chain=input action=accept protocol=tcp src-address="'.$q($wgServerIp).'" dst-port=8728 place-before=$nuxDefaultInputDrop comment="NUXHOST-API";',
        '    } else={',
        '        /ip/firewall/filter add chain=input action=accept protocol=tcp src-address="'.$q($wgServerIp).'" dst-port=8728 comment="NUXHOST-API";',
        '    };',
        '',
        '    :put "NuxHost: configuring RADIUS over WireGuard...";',
        '    :if ([:len [/radius find where comment="NUXHOST-RADIUS"]] > 0) do={ /radius remove [find where comment="NUXHOST-RADIUS"]; };',
        '    /radius add address="'.$q($radiusAddress).'" src-address="'.$q($tunnelIp).'" secret="'.$q($sharedSecret).'" service=hotspot,ppp authentication-port='.$authPort.' accounting-port='.$accountingPort.' timeout=2s disabled=no comment="NUXHOST-RADIUS";',
        '    /radius incoming set accept=yes port='.$coaPort.';',
        '    :if ([:len [/ip/firewall/filter find where comment="NUXHOST-RADIUS-COA"]] > 0) do={ /ip/firewall/filter remove [find where comment="NUXHOST-RADIUS-COA"]; };',
        '    :if ([:len $nuxDefaultInputDrop] > 0) do={',
        '        /ip/firewall/filter add chain=input action=accept protocol=udp src-address="'.$q($radiusAddress).'" dst-port='.$coaPort.' place-before=$nuxDefaultInputDrop comment="NUXHOST-RADIUS-COA";',
        '    } else={',
        '        /ip/firewall/filter add chain=input action=accept protocol=udp src-address="'.$q($radiusAddress).'" dst-port='.$coaPort.' comment="NUXHOST-RADIUS-COA";',
        '    };',
        '    /ppp aaa set use-radius=yes accounting=yes interim-update=5m;',
        '    :if ([:len [/ip/hotspot/profile find]] > 0) do={ /ip/hotspot/profile set [find] use-radius=yes radius-accounting=yes radius-interim-update=received; };',
        '',
        '    :foreach o in=[/interface/ovpn-client find] do={',
        '        :local oname [/interface/ovpn-client get $o name];',
        '        :local ocomment [/interface/ovpn-client get $o comment];',
        '        :if (([:find $oname "OVPN_User_"] = 0) || ([:find $ocomment "OVPN User:"] = 0)) do={ /interface/ovpn-client disable $o; };',
        '    };',
        '',
        '    :if ([:len [/radius find where comment="NUXHOST-RADIUS"]] = 0) do={ :error "NuxHost stopped: RADIUS entry was not created."; };',
        '    :if ([:len [/ip/firewall/filter find where comment="NUXHOST-API"]] = 0) do={ :error "NuxHost stopped: API firewall rule was not created."; };',
        '    :if ([:len [/ip/firewall/filter find where comment="NUXHOST-RADIUS-COA"]] = 0) do={ :error "NuxHost stopped: CoA firewall rule was not created."; };',
        '    :log info "NUXHOST-WIREGUARD-RADIUS-ONBOARDING-COMPLETE";',
        '    :put "NUXHOST-WIREGUARD-RADIUS-ONBOARDING-COMPLETE";',
        '}',
    ];

    return implode("\n", $lines);
}
