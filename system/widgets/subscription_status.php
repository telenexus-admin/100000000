<?php

class subscription_status
{
    public function getWidget()
    {
        global $ui;

        // Reuse the function from subscription_manager.php
        if (function_exists('_sm_widget_data')) {
            $widget_data = _sm_widget_data();
        } else {
            return ''; // Extension not loaded or function missing
        }

        if (!$widget_data['show']) {
            return '';
        }

        $ui->assign('w_data', $widget_data);
        return $ui->fetch('widget/subscription_status.tpl');
    }
}