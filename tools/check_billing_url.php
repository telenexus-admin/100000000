<?php
require dirname(__DIR__) . '/init.php';
echo 'APP_URL=' . APP_URL . "\n";
echo 'APP_URL_host=' . parse_url(APP_URL, PHP_URL_HOST) . "\n";

$rows = ORM::for_table('tbl_appconfig')
    ->where_raw("setting LIKE '%billing%' OR setting LIKE '%url%' OR setting = 'hotspot_billing_url'")
    ->find_many();
foreach ($rows as $row) {
    echo $row['setting'] . '=' . $row['value'] . "\n";
}
