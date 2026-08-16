<?php

/**
 * Public/privileged boundary for the automatic MikroTik WireGuard control plane.
 *
 * PHP reads only the public WireGuard settings in /etc/nuxhost/wireguard.ini.
 * The server private key remains inside /etc/wireguard and is touched only by
 * the root-owned helper installed by deploy/configure-radius-wireguard.sh.
 */
final class WireguardControlPlane
{
    private const DEFAULT_CONFIG = '/etc/nuxhost/wireguard.ini';
    private const DEFAULT_INTERFACE = 'wg-nuxhost';
    private const DEFAULT_SERVER_IP = '10.77.0.1';
    private const DEFAULT_CIDR = '10.77.0.0/24';
    private const DEFAULT_ENDPOINT_PORT = 51821;
    private const DEFAULT_AUTH_PORT = 1812;
    private const DEFAULT_ACCT_PORT = 1813;
    private const DEFAULT_COA_PORT = 3799;

    /** @return array<string,mixed> */
    public static function config(): array
    {
        $path = trim((string)(getenv('NUXHOST_WIREGUARD_CONFIG') ?: self::DEFAULT_CONFIG));
        $file = [];
        if ($path !== '' && is_file($path) && is_readable($path)) {
            $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $file = $parsed;
            }
        }

        $interface = self::value('NUXHOST_WG_INTERFACE', $file, 'interface', self::DEFAULT_INTERFACE);
        $serverIp = self::value('NUXHOST_WG_SERVER_IP', $file, 'server_ip', self::DEFAULT_SERVER_IP);
        $cidr = self::value('NUXHOST_WG_CIDR', $file, 'cidr', self::DEFAULT_CIDR);
        $endpoint = self::value('NUXHOST_WG_ENDPOINT', $file, 'endpoint', '');
        $publicKey = self::value('NUXHOST_WG_PUBLIC_KEY', $file, 'public_key', '');
        $endpointPort = self::validatedPort(self::value(
            'NUXHOST_WG_ENDPOINT_PORT', $file, 'endpoint_port', (string)self::DEFAULT_ENDPOINT_PORT
        ), 'WireGuard endpoint');
        $radiusHost = self::value('NUXHOST_RADIUS_HOST', $file, 'radius_host', $serverIp);
        $authPort = self::validatedPort(self::value(
            'NUXHOST_RADIUS_AUTH_PORT', $file, 'radius_auth_port', (string)self::DEFAULT_AUTH_PORT
        ), 'RADIUS authentication');
        $acctPort = self::validatedPort(self::value(
            'NUXHOST_RADIUS_ACCT_PORT', $file, 'radius_accounting_port', (string)self::DEFAULT_ACCT_PORT
        ), 'RADIUS accounting');
        $coaPort = self::validatedPort(self::value(
            'NUXHOST_RADIUS_COA_PORT', $file, 'radius_coa_port', (string)self::DEFAULT_COA_PORT
        ), 'RADIUS CoA');

        if (!preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $interface)) {
            throw new RuntimeException('The WireGuard interface name is invalid.');
        }
        if (!filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('The WireGuard server IPv4 address is invalid.');
        }
        if (!self::validIpv4Cidr($cidr) || !self::ipv4InCidr($serverIp, $cidr)) {
            throw new RuntimeException('The WireGuard management CIDR is invalid.');
        }
        if ((int)explode('/', $cidr, 2)[1] !== 24) {
            throw new RuntimeException('This proven RouterOS onboarding profile requires a /24 WireGuard management subnet.');
        }
        if ($endpoint === '' || mb_strlen($endpoint) > 255 || !preg_match('/^[A-Za-z0-9.:-]+$/', $endpoint)) {
            throw new RuntimeException('The WireGuard public endpoint is not configured.');
        }
        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)) {
            throw new RuntimeException('The WireGuard server public key is not configured.');
        }
        if (!filter_var($radiusHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || !self::ipv4InCidr($radiusHost, $cidr)) {
            throw new RuntimeException('The private RADIUS host must be inside the WireGuard management subnet.');
        }

        return [
            'interface' => $interface,
            'server_ip' => $serverIp,
            'cidr' => $cidr,
            'endpoint' => $endpoint,
            'endpoint_port' => $endpointPort,
            'public_key' => $publicKey,
            'radius_host' => $radiusHost,
            'radius_auth_port' => $authPort,
            'radius_accounting_port' => $acctPort,
            'radius_coa_port' => $coaPort,
        ];
    }

    /** @return array<string,mixed> */
    public static function publicConfig(): array
    {
        return self::config();
    }

    /**
     * Return currently assigned /32 addresses already present on the live
     * WireGuard interface. This prevents a second billing instance sharing the
     * same interface from allocating an address that already belongs to a peer.
     *
     * @return string[]
     */
    public static function allocatedIps(): array
    {
        $helper = '/usr/local/bin/nuxhost-wireguard-manage';
        if (!is_file($helper) || !is_executable($helper)) {
            throw new RuntimeException('The privileged WireGuard helper is not installed.');
        }

        $output = [];
        $code = 1;
        exec('sudo -n '.escapeshellarg($helper).' allocated 2>&1', $output, $code);
        if ($code !== 0) {
            $message = trim(implode("\n", $output));
            throw new RuntimeException($message !== '' ? $message : 'Could not inspect WireGuard allocations.');
        }

        $ips = [];
        foreach ($output as $line) {
            $line = trim((string)$line);
            if (str_contains($line, '/')) {
                $line = explode('/', $line, 2)[0];
            }
            if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[$line] = true;
            }
        }
        return array_keys($ips);
    }

    /** @return array{success:bool,message:string} */
    public static function activatePeer(string $publicKey, string $tunnelIp): array
    {
        $config = self::config();
        $publicKey = trim($publicKey);
        $tunnelIp = trim($tunnelIp);

        if (!preg_match('/^[A-Za-z0-9+\/]{43}=$/', $publicKey)) {
            throw new RuntimeException('The MikroTik WireGuard public key is invalid.');
        }
        if (!filter_var($tunnelIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || !self::ipv4InCidr($tunnelIp, $config['cidr'])
            || $tunnelIp === $config['server_ip']) {
            throw new RuntimeException('The MikroTik WireGuard address is outside the management subnet.');
        }

        $helper = '/usr/local/bin/nuxhost-wireguard-manage';
        if (!is_file($helper) || !is_executable($helper)) {
            throw new RuntimeException('The privileged WireGuard helper is not installed.');
        }

        $command = 'sudo -n '.escapeshellarg($helper)
            .' activate '.escapeshellarg($publicKey)
            .' '.escapeshellarg($tunnelIp).' 2>&1';
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

    public static function reloadRadiusClients(): void
    {
        $helper = '/usr/local/bin/nuxhost-radius-manage';
        if (!is_file($helper) || !is_executable($helper)) {
            throw new RuntimeException('The FreeRADIUS management helper is not installed.');
        }
        $output = [];
        $code = 1;
        exec('sudo -n '.escapeshellarg($helper).' reload 2>&1', $output, $code);
        if ($code !== 0) {
            $message = trim(implode("\n", $output));
            throw new RuntimeException($message !== '' ? $message : 'FreeRADIUS could not reload SQL NAS clients.');
        }
    }

    /** @param array<string,mixed> $file */
    private static function value(string $environment, array $file, string $key, string $default): string
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

    private static function validatedPort(string $raw, string $label): int
    {
        $port = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new RuntimeException('The '.$label.' port is invalid.');
        }
        return (int)$port;
    }

    private static function validIpv4Cidr(string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }
        [$network, $prefix] = explode('/', $cidr, 2);
        return filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($prefix, FILTER_VALIDATE_INT, ['options' => ['min_range' => 8, 'max_range' => 30]]) !== false;
    }

    public static function ipv4InCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$network, $prefix] = explode('/', $cidr, 2);
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
