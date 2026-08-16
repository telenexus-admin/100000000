<?php
/**
 * Restore email_invoice HTML into notifications.json without changing SMS templates.
 */
$path = dirname(__DIR__) . '/system/uploads/notifications.json';
$defPath = dirname(__DIR__) . '/system/uploads/notifications.default.json';
$cur = json_decode(file_get_contents($path), true);
$def = json_decode(file_get_contents($defPath), true);
if (!is_array($cur) || !is_array($def)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}
if (!empty($def['email_invoice']) && ($cur['email_invoice'] ?? '') === '') {
    $cur['email_invoice'] = $def['email_invoice'];
    file_put_contents($path, json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Restored email_invoice\n";
} else {
    echo "email_invoice already set or missing in default\n";
}
