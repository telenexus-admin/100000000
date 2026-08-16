<?php
/**
 * Remove routine "Hotspot expired+kicked" spam from admin Logs.
 * Does not touch billing, customers, or other log types.
 */
require dirname(__DIR__) . '/config.php';
$m = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($m->connect_error) {
    fwrite(STDERR, "DB fail\n");
    exit(1);
}

$before = (int) $m->query("SELECT COUNT(*) c FROM tbl_logs WHERE description LIKE 'Hotspot expired+kicked:%'")->fetch_assoc()['c'];
$m->query("DELETE FROM tbl_logs WHERE description LIKE 'Hotspot expired+kicked:%'");
$deleted = $m->affected_rows;
$after = (int) $m->query("SELECT COUNT(*) c FROM tbl_logs WHERE description LIKE 'Hotspot expired+kicked:%'")->fetch_assoc()['c'];
echo "before={$before} deleted={$deleted} after={$after}\n";
