<?php
require __DIR__ . '/../init.php';
$rows = ORM::for_table('tbl_plans')
    ->where('type', 'Hotspot')
    ->where('enabled', 1)
    ->order_by_asc('price')
    ->find_many();
foreach ($rows as $p) {
    echo $p['id'] . '|' . $p['name_plan'] . '|shared=' . $p['shared_users'] . '|price=' . $p['price'] . PHP_EOL;
}
