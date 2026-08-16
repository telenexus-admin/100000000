<?php
/**
 * Bounded background reachability poller for the Routers page.
 * It updates only the existing last-known status fields; it does not perform
 * RouterOS commands, send notifications, or alter subscriber/billing records.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require dirname(__DIR__, 2) . '/init.php';
if (!empty($config['timezone'])) {
    @date_default_timezone_set($config['timezone']);
}

$routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
foreach ($routers as $router) {
    $address = (string) $router->ip_address;
    $host = $address;
    $port = 8728;
    if (strpos($address, ':') !== false) {
        [$host, $port] = explode(':', $address, 2);
    }

    $socket = @fsockopen($host, (int) $port, $errorNumber, $errorMessage, 3);
    $online = $socket !== false;
    if ($socket) {
        fclose($socket);
    }

    $nextStatus = $online ? 'Online' : 'Offline';
    if ($router->status !== $nextStatus) {
        $router->status = $nextStatus;
    }
    if ($online) {
        $router->last_seen = date('Y-m-d H:i:s');
    }
    $router->save();
    echo $router->name . ': ' . $nextStatus . PHP_EOL;
}
