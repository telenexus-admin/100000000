<?php

class mikrotik_cron_monitor
{
    public function getWidget()
    {
        global $config, $ui;

        if (!empty($config['router_check'])) {
            $routeroffs = ORM::for_table('tbl_routers')
                ->select_many(['id', 'name', 'last_seen', 'status', 'ip_address'])
                ->where('status', 'Offline')
                ->where('enabled', '1')
                ->order_by_desc('name')
                ->find_array();

            $ui->assign('routeroffs', $routeroffs);
        }

        return $ui->fetch('widget/mikrotik_cron_monitor.tpl');
    }
}