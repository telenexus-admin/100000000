<?php

/**
 * Public WireGuard control-plane facade for automatic MikroTik onboarding.
 *
 * Security boundary:
 * - PHP reads public WireGuard configuration only.
 * - The server private key is never exposed to PHP.
 * - Peer changes are delegated to a narrowly scoped root helper through sudo.
 */
class RSWireguardControlPlane
{
    const DEFAULT_CONFIG = '/etc/rs-radius/wireguard.ini';
    const DEFAULT_INTERFACE = 'wg-rs';
    const DEFAULT_SERVER_IP = '10.78.0.1';
    const DEFAULT_CIDR = '10.78.0.0/24';
    const DEFAULT_ENDPOINT_PORT = 51822;

    public static function config()
    {
        $path = trim((string)(getenv('RS_WIREGUARD_CONFIG') ?: self::DEFAULT_CONFIG));
        $file = [];
        if ($path !== '' && is_file($path) && is_readable($path)) {
            $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $file = $parsed;
            }
        }

        $interface = self::value('RS_WG_INTERFACE', $file, 'interface', self::DEFAULT_INTERFACE);
        $serverIp = self::value('RS_WG_SERVER_IP', $file, 'server_ip', self::DEFAULT_SERVER_IP);
        $cidr = self::value('RS_WG_CIDR', $file, 'cidr', self::DEFAULT_CIDR);
        $endpoint = self::value('RS_WG_ENDPOINT', $file, 'endpoint', '');
        $publicKey = self::value('RS_WG_PUBLIC_KEY', $file, 'public_key', '');
        $portRaw = self::value('RS_WG_ENDPOINT_PORT', $file, 'endpoint_port', (string)self::DEFAULT_ENDPOINT_PORT);

        if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $interface)) {
            throw new RuntimeException('The WireGuard interface name is invalid.');
        }
        if (!filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('The WireGuard server IPv4 address is invalid.');
        }
        if (!self::validIpv4Cidr($cidr) || !self::ipv4InCidr($serverIp, $cidr)) {
            throw new RuntimeException('The WireGuard management CIDR is invalid.');
        }
        if ($endpoint === '' || strlen($endpoint) > 255 || !preg_match('/^[A-Za-z0-9.:-]+$/', $endpoint)) {
            throw new RuntimeException('The WireGuard public endpoint is not configured.');
        }
        $port = filter_var($portRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new RuntimeException('The WireGuard endpoint port is invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)) {
            throw new RuntimeException('The WireGuard server public key is not configured.');
        }

        return [
            'interface' => $interface,
            'server_ip' => $serverIp,
            'cidr' => $cidr,
            'endpoint' => $endpoint,
            'endpoint_port' => (int)$port,
            'public_key' => $publicKey,
        ];
    }

    public static function publicConfig()
    {
        return self::config();
    }

    public static function activatePeer($publicKey, $tunnelIp)
    {
        $config = self::config();
        $publicKey = trim((string)$publicKey);
        $tunnelIp = trim((string)$tunnelIp);

        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)) {
            throw new RuntimeException('The MikroTik WireGuard public key is invalid.');
        }
        if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || !self::ipv4InCidr($tunnelIp, $config['cidr'])
            || $tunnelIp === $config['server_ip']) {
            throw new RuntimeException('The MikroTik WireGuard address is outside the management subnet.');
        }

        $helper = '/usr/local/bin/rs-wireguard-manage';
        if (!is_file($helper) || !is_executable($helper)) {
            throw new RuntimeException('The privileged WireGuard helper is not installed.');
        }

        $command = 'sudo -n ' . escapeshellarg($helper)
            . ' activate ' . escapeshellarg($publicKey)
            . ' ' . escapeshellarg($tunnelIp) . ' 2>&1';
        $output = [];
        $code = 1;
        exec($command, $output, $code);
        $message = trim(implode("\n", $output));

        if ($code !== 0) {
            throw new RuntimeException($message !== '' ? $message : 'The WireGuard peer activation helper failed.');
        }

        return [
            'success' => true,
            'message' => $message !== '' ? $message : 'WireGuard peer activated.',
        ];
    }

    private static function value($environment, array $file, $key, $default)
    {
        $value = getenv($environment);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
        if (isset($file[$key]) && trim((string)$file[$key]) !== '') {
            return trim((string)$file[$key]);
        }
        return $default;
    }

    public static function validIpv4Cidr($cidr)
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }
        list($network, $prefix) = explode('/', $cidr, 2);
        return filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($prefix, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 32]]) !== false;
    }

    public static function ipv4InCidr($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        list($network, $prefix) = explode('/', $cidr, 2);
        $prefixLength = (int)$prefix;
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        if ($ipLong === false || $networkLong === false) {
            return false;
        }
        $mask = $prefixLength === 0 ? 0 : (-1 << (32 - $prefixLength));
        return (($ipLong & $mask) === ($networkLong & $mask));
    }
}
