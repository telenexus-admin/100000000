<?php
$f = '/var/www/html/pamnet/system/tools/expire_hotspot_now.php';
$code = file_get_contents($f);
echo 'has_failed_log=' . (strpos($code, 'expire kick FAILED') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_fatal_log=' . (strpos($code, 'Hotspot expire FATAL') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_old_success_log=' . (strpos($code, '_log("Hotspot expired+kicked') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_cli_echo=' . (strpos($code, 'expired+kicked:') !== false ? 'yes' : 'no') . PHP_EOL;

require '/var/www/html/pamnet/config.php';
$m = new mysqli($db_host, $db_user, $db_pass, $db_name);
$spam = (int) $m->query("SELECT COUNT(*) c FROM tbl_logs WHERE description LIKE 'Hotspot expired+kicked:%'")->fetch_assoc()['c'];
echo "spam_left={$spam}\n";

// Ensure cron line exists
$cron = @file_get_contents('/etc/cron.d/pamnet') ?: '';
echo 'cron_expire=' . (strpos($cron, 'expire_hotspot_now') !== false ? 'yes' : 'no') . PHP_EOL;
