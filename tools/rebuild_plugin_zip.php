<?php
$src = dirname(__DIR__) . '/system/plugin/pamnet_safe_reminders.php';
$dest = __DIR__ . '/pamnet_safe_reminders.zip';
if (file_exists($dest)) {
    unlink($dest);
}
$z = new ZipArchive();
$z->open($dest, ZipArchive::CREATE);
$z->addFile($src, 'plugin/pamnet_safe_reminders.php');
$z->close();
file_put_contents(__DIR__ . '/pamnet_safe_reminders.b64.txt', base64_encode(file_get_contents($dest)));
$php = file_get_contents($src);
echo 'zip=' . filesize($dest) . PHP_EOL;
echo 'V5c=' . (strpos($php, 'V5c') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'invoice=' . (strpos($php, 'PAMNET_PPPOE_ONLY_INVOICE') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'b64=' . strlen(file_get_contents(__DIR__ . '/pamnet_safe_reminders.b64.txt')) . PHP_EOL;
