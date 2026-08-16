<?php
require dirname(__DIR__) . '/init.php';
echo 'APP_URL=' . APP_URL . "\n";
echo 'host=' . parse_url(APP_URL, PHP_URL_HOST) . "\n";
echo 'path=' . parse_url(APP_URL, PHP_URL_PATH) . "\n";
