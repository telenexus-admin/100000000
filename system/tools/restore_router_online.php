<?php
/**
 * Restore router Online status if API port is reachable.
 * Also prints soft time-sync status (no forced clock write).
 * Usage: php system/tools/restore_router_online.php
 */
require dirname(__DIR__, 2) . '/init.php';

$routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
foreach ($routers as $router) {
    if (strpos($router->ip_address, ':') === false) {
        $ip = $router->ip_address;
        $port = 8728;
    } else {
        [$ip, $port] = explode(':', $router->ip_address);
    }
    echo "{$router->name}\t{$ip}:{$port}\twas={$router->status}\t";
    $fsock = @fsockopen($ip, (int) $port, $errno, $errstr, 5);
    if ($fsock) {
        fclose($fsock);
        $router->status = 'Online';
        $router->last_seen = date('Y-m-d H:i:s');
        $router->save();
        echo "Online OK\n";
        try {
            $iport = explode(':', (string) $router->ip_address);
            $client = new PEAR2\Net\RouterOS\Client(
                $iport[0],
                (string) $router->username,
                (string) $router->password,
                isset($iport[1]) ? $iport[1] : null
            );
            $r = MikrotikTimeSync::syncClient($client, (string) $router->name);
            echo "  soft-sync: " . ($r['message'] ?? '') . "\n";
        } catch (Throwable $e) {
            echo "  api-login: " . $e->getMessage() . "\n";
        }
    } else {
        echo "UNREACHABLE ($errstr)\n";
        echo "  Fix on router (Winbox):\n";
        echo "  /system ntp client set enabled=yes servers=ke.pool.ntp.org\n";
        echo "  /system clock set time-zone-name=Africa/Nairobi\n";
        echo "  /ip service enable api\n";
    }
}
echo "DONE\n";
