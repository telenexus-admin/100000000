<?php

 

_admin();
$ui->assign('_title', Lang::T('Recharge Account'));
$ui->assign('_system_menu', 'plan');

$action = $routes['1'];
$ui->assign('_admin', $admin);

$appUrl = APP_URL;

$select2_customer = <<<EOT
<script>
document.addEventListener("DOMContentLoaded", function(event) {
    $('#personSelect').select2({
        theme: "bootstrap",
        ajax: {
            url: function(params) {
                if(params.term != undefined){
                    return '{$appUrl}/?_route=autoload/customer_select2&s='+params.term;
                }else{
                    return '{$appUrl}/?_route=autoload/customer_select2';
                }
            }
        }
    });
});
</script>
EOT;
getUrl('docs');
switch ($action) {
    case 'sync':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        set_time_limit(-1);
        $turs = ORM::for_table('tbl_user_recharges')->where('status', 'on')->find_many();
        $log = '';
        $router = '';
        foreach ($turs as $tur) {
            $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
            if ($p) {
                $c = ORM::for_table('tbl_customers')->find_one($tur['customer_id']);
                if ($c) {
                    $dvc = Package::getDevice($p);
                    if ($_app_stage != 'demo') {
                        if (file_exists($dvc)) {
                            require_once $dvc;
                            if (method_exists($dvc, 'sync_customer')) {
                                (new $p['device'])->sync_customer($c, $p);
                            } else {
                                (new $p['device'])->add_customer($c, $p);
                            }
                        } else {
                            throw new Exception(Lang::T("Devices Not Found"));
                        }
                    }
                    $log .= "DONE : $tur[username], $ptur[namebp], $tur[type], $tur[routers]<br>";
                } else {
                    $log .= "Customer NOT FOUND : $tur[username], $tur[namebp], $tur[type], $tur[routers]<br>";
                }
            } else {
                $log .= "PLAN NOT FOUND : $tur[username], $tur[namebp], $tur[type], $tur[routers]<br>";
            }
        }
        r2(getUrl('plan/list'), 's', $log);
    case 'recharge':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('xfooter', $select2_customer);
        if (isset($routes['2']) && !empty($routes['2'])) {
            $ui->assign('cust', ORM::for_table('tbl_customers')->find_one($routes['2']));
        }
        $usings = explode(',', $config['payment_usings']);
        $usings = array_filter(array_unique($usings));
        if (count($usings) == 0) {
            $usings[] = Lang::T('Cash');
        }
        $ui->assign('usings', $usings);
        run_hook('view_recharge'); #HOOK
        $ui->display('admin/plan/recharge.tpl');
        break;

    case 'recharge-confirm':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_customer = _post('id_customer');
        $server = _post('server');
        $planId = _post('plan');
        $using = _post('using');

        $msg = '';
        if ($id_customer == '' or $server == '' or $planId == '' or $using == '') {
            $msg .= Lang::T('All field is required') . '<br>';
        }

        if ($msg == '') {
            $gateway = 'Recharge';
            $channel = $admin['fullname'];
            $cust = User::_info($id_customer);
            $plan = ORM::for_table('tbl_plans')->find_one($planId);
            list($bills, $add_cost) = User::getBills($id_customer);
            $add_inv = User::getAttribute("Invoice", $id_customer);
            if (!empty($add_inv)) {
                $plan['price'] = $add_inv;
            }

            // Tax calculation start
            $tax_enable = isset($config['enable_tax']) ? $config['enable_tax'] : 'no';
            $tax_rate_setting = isset($config['tax_rate']) ? $config['tax_rate'] : null;
            $custom_tax_rate = isset($config['custom_tax_rate']) ? (float) $config['custom_tax_rate'] : null;

            if ($tax_rate_setting === 'custom') {
                $tax_rate = $custom_tax_rate;
            } else {
                $tax_rate = $tax_rate_setting;
            }

            if ($tax_enable === 'yes') {
                $tax = Package::tax($plan['price'], $tax_rate);
            } else {
                $tax = 0;
            }
            // Tax calculation stop
            $total_cost = $plan['price'] + $add_cost + $tax;

            if ($using == 'balance' && $config['enable_balance'] == 'yes') {
                if (!$cust) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('Customer not found'));
                }
                if (!$plan) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('Plan not found'));
                }
                if ($cust['balance'] < $total_cost) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('insufficient balance'));
                }
                $gateway = 'Recharge Balance';
            }
            if ($using == 'zero') {
                $zero = 1;
                $gateway = 'Recharge Zero';
            }
            $usings = explode(',', $config['payment_usings']);
            $usings = array_filter(array_unique($usings));
            if (count($usings) == 0) {
                $usings[] = Lang::T('Cash');
            }
            if ($tax_enable === 'yes') {
                $ui->assign('tax', $tax);
            }
            $ui->assign('usings', $usings);
            $ui->assign('bills', $bills);
            $ui->assign('add_cost', $add_cost);
            $ui->assign('cust', $cust);
            $ui->assign('gateway', $gateway);
            $ui->assign('channel', $channel);
            $ui->assign('server', $server);
            $ui->assign('using', $using);
            $ui->assign('plan', $plan);
            $ui->assign('add_inv', $add_inv);
            $ui->display('admin/plan/recharge-confirm.tpl');
        } else {
            r2(getUrl('plan/recharge'), 'e', $msg);
        }
        break;

    case 'recharge-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_customer = _post('id_customer');
        $server = _post('server');
        $planId = _post('plan');
        $using = _post('using');
        $svoucher = _post('svoucher');

        $plan = ORM::for_table('tbl_plans')->find_one($planId);

        if (!empty(App::getVoucherValue($svoucher))) {
            $username = App::getVoucherValue($svoucher);
            $in = ORM::for_table('tbl_transactions')->where('username', $username)->order_by_desc('id')->find_one();
            Package::createInvoice($in);
            $ui->display('admin/plan/invoice.tpl');
            die();
        }

        $msg = '';
        if ($id_customer == '' or $server == '' or $planId == '' or $using == '') {
            $msg .= Lang::T('All field is required') . '<br>';
        }

        if ($msg == '') {
            $gateway = ucwords($using);
            $channel = $admin['fullname'];
            $cust = User::_info($id_customer);
            list($bills, $add_cost) = User::getBills($id_customer);

            // Tax calculation start
            $tax_enable = isset($config['enable_tax']) ? $config['enable_tax'] : 'no';
            $tax_rate_setting = isset($config['tax_rate']) ? $config['tax_rate'] : null;
            $custom_tax_rate = isset($config['custom_tax_rate']) ? (float) $config['custom_tax_rate'] : null;

            if ($tax_rate_setting === 'custom') {
                $tax_rate = $custom_tax_rate;
            } else {
                $tax_rate = $tax_rate_setting;
            }

            if ($tax_enable === 'yes') {
                $tax = Package::tax($plan['price'], $tax_rate);
            } else {
                $tax = 0;
            }
            // Tax calculation stop
            $total_cost = $plan['price'] + $add_cost + $tax;

            if ($using == 'balance' && $config['enable_balance'] == 'yes') {
                //$plan = ORM::for_table('tbl_plans')->find_one($planId);
                if (!$cust) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('Customer not found'));
                }
                if (!$plan) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('Plan not found'));
                }
                if ($cust['balance'] < $total_cost) {
                    r2(getUrl('plan/recharge'), 'e', Lang::T('insufficient balance'));
                }
                $gateway = 'Recharge Balance';
            }
            if ($using == 'zero') {
                $add_cost = 0;
                $zero = 1;
                $gateway = 'Recharge Zero';
            }
            if (Package::rechargeUser($id_customer, $server, $planId, $gateway, $channel)) {
                if ($using == 'balance') {
                    Balance::min($cust['id'], $total_cost);
                }
                $in = ORM::for_table('tbl_transactions')->where('username', $cust['username'])->order_by_desc('id')->find_one();
                Package::createInvoice($in);
                App::setVoucher($svoucher, $cust['username']);
                $ui->display('admin/plan/invoice.tpl');
                _log('[' . $admin['username'] . ']: ' . 'Recharge ' . $cust['username'] . ' [' . $in['plan_name'] . '][' . Lang::moneyFormat($in['price']) . ']', $admin['user_type'], $admin['id']);
            } else {
                r2(getUrl('plan/recharge'), 'e', "Failed to recharge account");
            }
        } else {
            r2(getUrl('plan/recharge'), 'e', $msg);
        }
        break;

    case 'view':
        $id = isset($routes['2']) ? $routes['2'] : 0;
        if ($id <= 0) {
            r2(getUrl('plan/list'), 'e', Lang::T('Invalid request'));
        }
        $in = ORM::for_table('tbl_transactions')->where('id', $id)->find_one();
        $ui->assign('in', $in);
        if (!empty($routes['3']) && $routes['3'] == 'send') {
            $c = ORM::for_table('tbl_customers')->where('username', $in['username'])->find_one();
            if ($c) {
                Message::sendInvoice($c, $in);
                r2(getUrl('plan/view/') . $id, 's', "Success send to customer");
            }
            r2(getUrl('plan/view/') . $id, 'd', "Customer not found");
        }
        Package::createInvoice($in);
        $UPLOAD_URL_PATH = str_replace($root_path, '', $UPLOAD_PATH);
        $logo = '';
        if (file_exists($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo.png')) {
            $logo = $UPLOAD_URL_PATH . DIRECTORY_SEPARATOR . 'logo.png';
            $imgsize = getimagesize($logo);
            if ($imgsize !== false && isset($imgsize[0]) && isset($imgsize[1])) {
                $width = $imgsize[0];
                $height = $imgsize[1];
            } else {
                $width = 100;
                $height = 100;
            }
            $ui->assign('wlogo', $width);
            $ui->assign('hlogo', $height);
        }

        $ui->assign('public_url', getUrl("voucher/invoice/$id/".md5($id. $db_pass)));
        $ui->assign('logo', $logo);
        $ui->assign('_title', 'View Invoice');
        $ui->display('admin/plan/invoice.tpl');
        break;


    case 'print':
        $content = isset($_POST['content']) ? _post('content') : '';
        if (!empty($content)) {
            if (isset($_POST['nux']) && _post('nux') == 'print') {
                //header("Location: nux://print?text=".urlencode($content));
                $ui->assign('nuxprint', "nux://print?text=" . urlencode($content));
            }
            $ui->assign('content', $content);
        } else {
            $id = _post('id');
            if (empty($id)) {
                $id = isset($routes['2']) ? intval($routes['2']) : 0;
            }
            if ($id <= 0) {
                r2(getUrl('plan/list'), 'e', Lang::T('Invalid request'));
            }
            $d = ORM::for_table('tbl_transactions')->where('id', $id)->find_one();
            $ui->assign('in', $d);
            $ui->assign('date', Lang::dateAndTimeFormat($d['recharged_on'], $d['recharged_time']));
        }
        run_hook('print_invoice'); #HOOK
        $ui->display('admin/plan/invoice-print.tpl');
        break;

    case 'edit':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('plan/list'), 'e', Lang::T('Invalid request'));
        }
        $d = ORM::for_table('tbl_user_recharges')->find_one($id);
        if ($d) {
            $ui->assign('d', $d);
            $p = ORM::for_table('tbl_plans')->find_one($d['plan_id']);
            if (in_array($admin['user_type'], array('SuperAdmin', 'Admin'))) {
                $ps = ORM::for_table('tbl_plans')
                    ->where('type', $p['type'])
                    ->where('is_radius', $p['is_radius'])
                    ->find_many();
            } else {
                $ps = ORM::for_table('tbl_plans')
                    ->where("enabled", 1)
                    ->where('is_radius', $p['is_radius'])
                    ->where('type', $p['type'])
                    ->find_many();
            }
            $ui->assign('p', $ps);
            run_hook('view_edit_customer_plan'); #HOOK
            $ui->assign('_title', 'Edit Plan');
            $ui->display('admin/plan/edit.tpl');
        } else {
            r2(getUrl('plan/list'), 'e', Lang::T('Account Not Found'));
        }
        break;

    case 'delete':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('plan/list'), 'e', Lang::T('Invalid request'));
        }
        $d = ORM::for_table('tbl_user_recharges')->find_one($id);
        if ($d) {
            run_hook('delete_customer_active_plan'); #HOOK
            $p = ORM::for_table('tbl_plans')->find_one($d['plan_id']);
            $c = User::_info($d['customer_id']);
            $dvc = Package::getDevice($p);
            if ($_app_stage != 'demo') {
                if (file_exists($dvc)) {
                    require_once $dvc;
                    (new $p['device'])->remove_customer($c, $p);
                } else {
                    throw new Exception(Lang::T("Devices Not Found"));
                }
            }
            $d->delete();
            _log('[' . $admin['username'] . ']: ' . 'Delete Plan for Customer ' . $c['username'] . '  [' . $in['plan_name'] . '][' . Lang::moneyFormat($in['price']) . ']', $admin['user_type'], $admin['id']);
            r2(getUrl('plan/list'), 's', Lang::T('Data Deleted Successfully'));
        }
        break;

    case 'edit-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id_plan = _post('id_plan');
        $recharged_on = _post('recharged_on');
        $expiration = _post('expiration');
        $time = _post('time');

        $id = _post('id');
        $d = ORM::for_table('tbl_user_recharges')->find_one($id);
        if ($d) {
        } else {
            $msg .= Lang::T('Data Not Found') . '<br>';
        }
        $oldPlanID = $d['plan_id'];
        $newPlan = ORM::for_table('tbl_plans')->where('id', $id_plan)->find_one();
        if ($newPlan) {
        } else {
            $msg .= ' Plan Not Found<br>';
        }
        if ($msg == '') {
            run_hook('edit_customer_plan'); #HOOK
            $d->expiration = $expiration;
            $d->time = $time;
            if ($d['status'] == 'off') {
                if (strtotime($expiration . ' ' . $time) > time()) {
                    $d->status = 'on';
                }
            }
            // plan different then do something
            if ($oldPlanID != $id_plan) {
                $d->plan_id = $newPlan['id'];
                $d->namebp = $newPlan['name_plan'];
                $customer = User::_info($d['customer_id']);
                //remove from old plan
                if ($d['status'] == 'on') {
                    $p = ORM::for_table('tbl_plans')->find_one($oldPlanID);
                    $dvc = Package::getDevice($p);
                    if ($_app_stage != 'demo') {
                        if (file_exists($dvc)) {
                            require_once $dvc;
                            $p['plan_expired'] = 0;
                            (new $p['device'])->remove_customer($customer, $p);
                        } else {
                            throw new Exception(Lang::T("Devices Not Found"));
                        }
                    }
                    //add new plan
                    $dvc = Package::getDevice($newPlan);
                    if ($_app_stage != 'demo') {
                        if (file_exists($dvc)) {
                            require_once $dvc;
                            (new $newPlan['device'])->add_customer($customer, $newPlan);
                        } else {
                            throw new Exception(Lang::T("Devices Not Found"));
                        }
                    }
                }
            }
            $d->save();
            _log('[' . $admin['username'] . ']: ' . 'Edit Plan for Customer ' . $d['username'] . ' to [' . $d['namebp'] . '][' . Lang::moneyFormat($p['price']) . ']', $admin['user_type'], $admin['id']);
            r2(getUrl('plan/list'), 's', Lang::T('Data Updated Successfully'));
        } else {
            r2(getUrl('plan/edit/') . $id, 'e', $msg);
        }
        break;

    case 'voucher':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Report', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('_title', Lang::T('Voucher Cards'));
        $search = _req('search');
        $router = _req('router');
        $customer = _req('customer');
        $plan = _req('plan');
        $status = _req('status');
        $ui->assign('router', $router);
        $ui->assign('customer', $customer);
        $ui->assign('status', $status);
        $ui->assign('plan', $plan);
        $ui->assign('_system_menu', 'cards');

        $query = ORM::for_table('tbl_plans')
            ->inner_join('tbl_voucher', ['tbl_plans.id', '=', 'tbl_voucher.id_plan']);

        if (!empty($router)) {
            $query->where('tbl_voucher.routers', $router);
        }

        if ($status == '1' || $status == '0') {
            $query->where('tbl_voucher.status', $status);
        }

        if (!empty($plan)) {
            $query->where('tbl_voucher.id_plan', $plan);
        }

        if (!empty($customer)) {
            $query->where('tbl_voucher.user', $customer);
        }

        $append_url = "&search=" . urlencode($search) . "&router=" . urlencode($router) . "&customer=" . urlencode($customer) . "&plan=" . urlencode($plan) . "&status=" . urlencode($status);

        // option customers
        $ui->assign('customers', ORM::for_table('tbl_voucher')->distinct()->select("user")->whereNotEqual("user", '0')->findArray());
        // option plans
        $plns = ORM::for_table('tbl_voucher')->distinct()->select("id_plan")->findArray();
        if (count($plns) > 0) {
            $ui->assign('plans', ORM::for_table('tbl_plans')->selects(["id", 'name_plan'])->where_in('id', array_column($plns, 'id_plan'))->findArray());
        } else {
            $ui->assign('plans', []);
        }
        $ui->assign('routers', array_column(ORM::for_table('tbl_voucher')->distinct()->select("routers")->findArray(), 'routers'));

        if ($search != '') {
            if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
                $query->where_like('tbl_voucher.code', '%' . $search . '%');
            } else if ($admin['user_type'] == 'Agent') {
                $sales = [];
                $sls = ORM::for_table('tbl_users')->select('id')->where('root', $admin['id'])->findArray();
                foreach ($sls as $s) {
                    $sales[] = $s['id'];
                }
                $sales[] = $admin['id'];
                $query->where_in('generated_by', $sales)
                    ->where_like('tbl_voucher.code', '%' . $search . '%');
            }
        } else {
            if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            } else if ($admin['user_type'] == 'Agent') {
                $sales = [];
                $sls = ORM::for_table('tbl_users')->select('id')->where('root', $admin['id'])->findArray();
                foreach ($sls as $s) {
                    $sales[] = $s['id'];
                }
                $sales[] = $admin['id'];
                $query->where_in('generated_by', $sales);
            }
        }
        $d = Paginator::findMany($query, ["search" => $search], 150, $append_url);
        // extract admin
        $admins = [];
        foreach ($d as $k) {
            if (!empty($k['generated_by'])) {
                $admins[] = $k['generated_by'];
            }
        }
        if (count($admins) > 0) {
            $adms = ORM::for_table('tbl_users')->where_in('id', $admins)->find_many();
            unset($admins);
            foreach ($adms as $adm) {
                $tipe = $adm['user_type'];
                if ($tipe == 'Sales') {
                    $tipe = ' [S]';
                } else if ($tipe == 'Agent') {
                    $tipe = ' [A]';
                } else {
                    $tipe == '';
                }
                $admins[$adm['id']] = $adm['fullname'] . $tipe;
            }
        }

        $ui->assign('admins', $admins);
        $ui->assign('d', $d);
        $ui->assign('search', $search);
        $ui->assign('page', $page);
        run_hook('view_list_voucher'); #HOOK
        $ui->display('admin/voucher/list.tpl');
        break;

    case 'add-voucher':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to create vouchers. Only Super Administrators and Administrators can create vouchers.'), 'danger', "dashboard");
        }
        $ui->assign('_title', Lang::T('Add Vouchers'));
        $c = ORM::for_table('tbl_customers')->find_many();
        $ui->assign('c', $c);
        $p = ORM::for_table('tbl_plans')->where('enabled', '1')->find_many();
        $ui->assign('p', $p);
        $r = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();
        $ui->assign('r', $r);
        run_hook('view_add_voucher'); #HOOK
        $ui->display('admin/voucher/add.tpl');
        break;

    case 'remove-voucher':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $time1month = strtotime('-1 month');
        $d = ORM::for_table('tbl_voucher')->where_equal('status', '1')
            ->where_raw("UNIX_TIMESTAMP(used_date) < $time1month")
            ->findMany();
        if ($d) {
            $jml = 0;
            foreach ($d as $v) {
                if (!ORM::for_table('tbl_user_recharges')->where_equal("method", 'Voucher - ' . $v['code'])->find_one()) {
                    $v->delete();
                    $jml++;
                }
            }
            r2(getUrl('plan/voucher'), 's', "$jml " . Lang::T('Data Deleted Successfully'));
        }
    case 'print-voucher':
        $from_id = _post('from_id');
        $planid = _post('planid');
        $pagebreak = _post('pagebreak');
        $limit = _post('limit');
        $vpl = _post('vpl');
        $selected_datetime = _post('selected_datetime');
        if (empty($vpl)) {
            $vpl = 3;
        }
        if ($pagebreak < 1)
            $pagebreak = 12;

        if ($limit < 1)
            $limit = $pagebreak * 2;
        if (empty($from_id)) {
            $from_id = 0;
        }

        if ($from_id > 0 && $planid > 0) {
            $v = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where('tbl_plans.id', $planid)
                ->where_gt('tbl_voucher.id', $from_id)
                ->limit($limit);
            $vc = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where('tbl_plans.id', $planid)
                ->where_gt('tbl_voucher.id', $from_id);
        } else if ($from_id == 0 && $planid > 0) {
            $v = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where('tbl_plans.id', $planid)
                ->limit($limit);
            $vc = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where('tbl_plans.id', $planid);
        } else if ($from_id > 0 && $planid == 0) {
            $v = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where_gt('tbl_voucher.id', $from_id)
                ->limit($limit);
            $vc = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where_gt('tbl_voucher.id', $from_id);
        } else if ($from_id > 0 && $planid == 0 && $selected_datetime != '') {
            $v = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where_raw("DATE(created_at) = ?", [$selected_datetime])
                ->where_gt('tbl_voucher.id', $from_id)
                ->limit($limit);
            $vc = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where_gt('tbl_voucher.id', $from_id);
        } else {
            $v = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->limit($limit);
            $vc = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0');
        }
        if (!empty($selected_datetime)) {
            $v = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0')
                ->where('tbl_voucher.created_at', $selected_datetime)
                ->limit($limit);
            $vc = ORM::for_table('tbl_plans')
                ->left_outer_join('tbl_voucher', array('tbl_plans.id', '=', 'tbl_voucher.id_plan'))
                ->where('tbl_voucher.status', '0');
        }
        if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            $v = $v->find_many();
            $vc = $vc->count();
        } else {
            $sales = [];
            $sls = ORM::for_table('tbl_users')->select('id')->where('root', $admin['id'])->findArray();
            foreach ($sls as $s) {
                $sales[] = $s['id'];
            }
            $sales[] = $admin['id'];
            $v = $v->where_in('generated_by', $sales)->find_many();
            $vc = $vc->where_in('generated_by', $sales)->count();
        }
        $template = file_get_contents("pages/Voucher.html");
        $template = str_replace('[[company_name]]', $config['CompanyName'], $template);

        $ui->assign('_title', Lang::T('Hotspot Voucher'));
        $ui->assign('from_id', $from_id);
        $ui->assign('vpl', $vpl);
        $ui->assign('pagebreak', $pagebreak);

        $plans = ORM::for_table('tbl_plans')->find_many();
        $ui->assign('plans', $plans);
        $ui->assign('limit', $limit);
        $ui->assign('planid', $planid);

        $createdate = ORM::for_table('tbl_voucher')
            ->select_expr(
                "CASE WHEN DATE(created_at) = CURDATE() THEN 'Today' ELSE DATE(created_at) END",
                'created_datetime'
            )
            ->where_not_equal('created_at', '0')
            ->select_expr('COUNT(*)', 'voucher_count')
            ->group_by('created_datetime')
            ->order_by_desc('created_datetime')
            ->find_array();

        $ui->assign('createdate', $createdate);

        $voucher = [];
        $n = 1;
        foreach ($v as $vs) {
            $temp = $template;
            $temp = str_replace('[[qrcode]]', '<img src="qrcode/?data=' . $vs['code'] . '">', $temp);
            $temp = str_replace('[[price]]', Lang::moneyFormat($vs['price']), $temp);
            $temp = str_replace('[[voucher_code]]', $vs['code'], $temp);
            $temp = str_replace('[[plan]]', $vs['name_plan'], $temp);
            $temp = str_replace('[[counter]]', $n, $temp);
            $voucher[] = $temp;
            $n++;
        }

        $ui->assign('voucher', $voucher);
        $ui->assign('vc', $vc);
        $ui->assign('selected_datetime', $selected_datetime);

        //for counting pagebreak
        $ui->assign('jml', 0);
        run_hook('view_print_voucher'); #HOOK
        $ui->display('admin/print/voucher.tpl');
        break;
    case 'voucher-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to create vouchers. Only Super Administrators and Administrators can create vouchers.'), 'danger', "dashboard");
        }
        if ($_app_stage == 'Demo') {
            r2(getUrl('plan/add-voucher/'), 'e', 'You cannot perform this action in Demo mode');
        }

        $type = _post('type');
        $plan = _post('plan');
        $voucher_format = 'rand'; // Always use random format
        $custom_name = _post('custom_name', '');
        $server = _post('server');
        $numbervoucher = _post('numbervoucher');
        $lengthcode = _post('lengthcode');

        // Handle multiple custom names separated by commas
        $custom_names = array();
        $use_multiple_names = false;
        
        if (!empty($custom_name)) {
            // Split by commas and clean each name
            $names = explode(',', $custom_name);
            foreach ($names as $name) {
                $cleaned = trim($name);
                $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', $cleaned); // Remove non-alphanumeric
                if (!empty($cleaned)) {
                    $custom_names[] = $cleaned;
                }
            }
            
            // If multiple names provided, override voucher count
            if (count($custom_names) > 1) {
                $numbervoucher = count($custom_names);
                $use_multiple_names = true;
            } elseif (count($custom_names) == 1) {
                $custom_names = array($custom_names[0]);
            }
        }
        
        // Handle SMS phone numbers for validation only (not voucher generation)
        $enable_sms = _post('enable_sms');
        $sms_phones = _post('sms_phones', '');
        $sms_voucher_type = _post('sms_voucher_type', 'random'); // 'random' or 'phone'
        $phones = array(); // Store phone numbers for SMS sending only
        
        if ($enable_sms && !empty($sms_phones)) {
            // Get country code from database
            $countryCodeConfig = ORM::for_table('tbl_appconfig')
                ->where('setting', 'country_code_phone')
                ->find_one();
            $countryCode = $countryCodeConfig ? $countryCodeConfig->value : '254';
            
            // Parse and normalize phone numbers for SMS sending
            $phoneLines = preg_split('/[,\r\n]+/', $sms_phones);
            foreach ($phoneLines as $phone) {
                $phone = trim($phone);
                if (empty($phone)) continue;
                
                // Clean phone number - remove all non-digit characters except +
                $cleaned = preg_replace('/[^0-9+]/', '', $phone);
                
                // Normalize different formats - support 01, 07, 2541, 2547, +254 prefixes
                if (preg_match('/^\+' . $countryCode . '[0-9]{9}$/', $cleaned)) {
                    // +254712345678 format
                    $phones[] = $cleaned;
                } elseif (preg_match('/^0[17][0-9]{8}$/', $cleaned)) {
                    // 0712345678, 0101234567 format - remove leading 0 and add country code
                    $phones[] = '+' . $countryCode . substr($cleaned, 1);
                } elseif (preg_match('/^[17][0-9]{8}$/', $cleaned)) {
                    // 712345678, 101234567 format - add country code
                    $phones[] = '+' . $countryCode . $cleaned;
                } elseif (preg_match('/^' . $countryCode . '[17][0-9]{8}$/', $cleaned)) {
                    // 254712345678, 254101234567 format - add +
                    $phones[] = '+' . $cleaned;
                }
            }
            
            // Remove duplicates
            $phones = array_unique($phones);
            $phoneCount = count($phones);
            
            // Validate phone count vs voucher count (error if more phones than vouchers)
            if ($phoneCount > 0 && $phoneCount > $numbervoucher) {
                $missing = $phoneCount - $numbervoucher;
                if ($missing == 1) {
                    $msg .= "Missing one voucher<br>";
                } else {
                    $msg .= "Missing $missing vouchers<br>";
                }
            }
        }

        // Smart validation - skip certain fields when they're disabled by frontend logic
        $skip_numbervoucher_validation = false;
        $skip_lengthcode_validation = false;
        
        // Check if we should skip validation based on multiple custom names only
        if ($use_multiple_names && count($custom_names) > 1) {
            $skip_numbervoucher_validation = true;
            $skip_lengthcode_validation = true;
        }
        
        $msg = '';
        // Core required fields (always validate)
        $missing_fields = [];
        if (empty($type)) $missing_fields[] = 'Type';
        if (empty($server)) $missing_fields[] = 'Routers';
        if (empty($plan)) $missing_fields[] = 'Service Plan';
        
        if (!empty($missing_fields)) {
            $msg .= 'Required fields missing: ' . implode(', ', $missing_fields) . '. Please select all required fields.<br>';
        }
        
        // Conditional validation for numbervoucher
        if (!$skip_numbervoucher_validation) {
            if (empty($numbervoucher)) {
                $msg .= Lang::T('Number of Vouchers is required') . '<br>';
            } elseif (!Validator::UnsignedNumber($numbervoucher)) {
                $msg .= 'The Number of Vouchers must be a number' . '<br>';
            }
        }
        
        // Conditional validation for lengthcode
        if (!$skip_lengthcode_validation) {
            if (empty($lengthcode)) {
                $msg .= Lang::T('Length Code is required') . '<br>';
            } elseif (!Validator::UnsignedNumber($lengthcode)) {
                $msg .= 'The Length Code must be a number' . '<br>';
            } elseif ($lengthcode < 2 || $lengthcode > 10) {
                $msg .= 'The Length Code must be between 2 and 10 characters' . '<br>';
            }
        }

        if ($msg == '') {
            run_hook('create_voucher'); // HOOK
            $vouchers = [];
            $newVoucherIds = [];

            // Generate voucher codes with custom name support
            if ($use_multiple_names && !empty($custom_names)) {
                // Use custom names for voucher generation
                for ($i = 0; $i < $numbervoucher; $i++) {
                    if (isset($custom_names[$i])) {
                        // Use the exact custom name without any modifications
                        $vouchers[] = $custom_names[$i];
                    } else {
                        // Generate random vouchers for remaining (when more vouchers than custom names)
                        $code = strtoupper(substr(md5(time() . rand(10000, 99999)), 0, $lengthcode));
                        if ($voucher_format == 'low') {
                            $code = strtolower($code);
                        } else if ($voucher_format == 'rand') {
                            $code = Lang::randomUpLowCase($code);
                        }
                        $vouchers[] = $code;
                    }
                }
            } elseif (!empty($custom_names) && count($custom_names) == 1 && isset($custom_names[0])) {
                // Single custom name - use original logic for backwards compatibility
                $single_custom = $custom_names[0];
                $customNameLength = strlen($single_custom);
                $remainingLength = $lengthcode - $customNameLength;
                
                if ($remainingLength <= 0) {
                    // If custom name is same or longer than desired length, use custom name only (truncated and formatted)
                    for ($i = 0; $i < $numbervoucher; $i++) {
                        $truncatedName = substr($single_custom, 0, $lengthcode);
                        
                        // Apply format to the truncated custom name
                        if ($voucher_format == 'low') {
                            $truncatedName = strtolower($truncatedName);
                        } else if ($voucher_format == 'up') {
                            $truncatedName = strtoupper($truncatedName);
                        } else if ($voucher_format == 'rand') {
                            $truncatedName = Lang::randomUpLowCase($truncatedName);
                        }
                        
                        $vouchers[] = $truncatedName;
                    }
                } else {
                    // Generate random characters to fill remaining length
                    for ($i = 0; $i < $numbervoucher; $i++) {
                        $randomChars = '';
                        for ($j = 0; $j < $remainingLength; $j++) {
                            if ($voucher_format == 'numbers') {
                                $randomChars .= rand(0, 9);
                            } else {
                                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                                $randomChar = $chars[rand(0, strlen($chars) - 1)];
                                
                                if ($voucher_format == 'low') {
                                    $randomChar = strtolower($randomChar);
                                } else if ($voucher_format == 'rand') {
                                    $randomChar = (rand(0, 1)) ? strtoupper($randomChar) : strtolower($randomChar);
                                }
                                $randomChars .= $randomChar;
                            }
                        }
                        
                        // Apply format to custom name as well
                        $formattedCustomName = $single_custom;
                        if ($voucher_format == 'low') {
                            $formattedCustomName = strtolower($single_custom);
                        } else if ($voucher_format == 'up') {
                            $formattedCustomName = strtoupper($single_custom);
                        } else if ($voucher_format == 'rand') {
                            $formattedCustomName = Lang::randomUpLowCase($single_custom);
                        }
                        
                        $vouchers[] = $formattedCustomName . $randomChars;
                    }
                }
            } else {
                // No custom name - use original logic
                if ($voucher_format == 'numbers') {
                    if ($lengthcode < 2) {
                        $msg .= 'The Length Code must be at least 2 for numbers' . '<br>';
                    }
                    $vouchers = generateUniqueNumericVouchers($numbervoucher, $lengthcode);
                } else {
                    for ($i = 0; $i < $numbervoucher; $i++) {
                        $code = strtoupper(substr(md5(time() . rand(10000, 99999)), 0, $lengthcode));
                        if ($voucher_format == 'low') {
                            $code = strtolower($code);
                        } else if ($voucher_format == 'rand') {
                            $code = Lang::randomUpLowCase($code);
                        }
                        $vouchers[] = $code;
                    }
                }
            }

            foreach ($vouchers as $code) {
                $d = ORM::for_table('tbl_voucher')->create();
                $d->type = $type;
                $d->routers = $server;
                $d->id_plan = $plan;
                $d->code = $code;  // No prefix added
                $d->user = '0';
                $d->status = '0';
                $d->generated_by = $admin['id'];
                $d->save();
                $newVoucherIds[] = $d->id();
            }

            if ($printNow == 'yes' && count($newVoucherIds) > 0) {
                $template = file_get_contents("pages/Voucher.html");
                $template = str_replace('[[company_name]]', $config['CompanyName'], $template);

                $vouchersToPrint = ORM::for_table('tbl_voucher')
                    ->left_outer_join('tbl_plans', ['tbl_plans.id', '=', 'tbl_voucher.id_plan'])
                    ->where_in('tbl_voucher.id', $newVoucherIds)
                    ->find_many();

                $voucherHtmls = [];
                $n = 1;

                foreach ($vouchersToPrint as $vs) {
                    $temp = $template;
                    $temp = str_replace('[[qrcode]]', '<img src="qrcode/?data=' . $vs['code'] . '">', $temp);
                    $temp = str_replace('[[price]]', Lang::moneyFormat($vs['price']), $temp);
                    $temp = str_replace('[[voucher_code]]', $vs['code'], $temp);
                    $temp = str_replace('[[plan]]', $vs['name_plan'], $temp);
                    $temp = str_replace('[[counter]]', $n, $temp);
                    $voucherHtmls[] = $temp;
                    $n++;
                }

                $vc = count($voucherHtmls);
                $ui->assign('voucher', $voucherHtmls);
                $ui->assign('vc', $vc);
                $ui->assign('jml', 0);
                $ui->assign('from_id', 0);
                $ui->assign('vpl', '3');
                $ui->assign('pagebreak', $voucherPerPage);
                $ui->display('admin/print/voucher.tpl');
            }

            // Handle SMS sending if enabled
            $enable_sms = _post('enable_sms');
            $sms_phones = _post('sms_phones', '');
            
            if ($enable_sms && !empty($sms_phones) && count($newVoucherIds) > 0) {
                // Get company name from database (country code already fetched above)
                $companyNameConfig = ORM::for_table('tbl_appconfig')
                    ->where('setting', 'CompanyName')
                    ->find_one();
                $companyName = $companyNameConfig ? $companyNameConfig->value : $config['CompanyName'];
                
                // Use the already parsed phones array from above
                if (isset($phones) && count($phones) > 0) {
                    // Get plan details
                    $planData = ORM::for_table('tbl_plans')->find_one($plan);
                    
                    // Create default SMS message with company name
                    $baseMessage = "Hi, Your voucher code is: [CODE]\nValid until used. Keep this code safe.\nRegards: " . $companyName;
                    
                    $smsSuccess = 0;
                    $smsError = 0;
                    
                    // Standard SMS sending - each phone gets a unique voucher (1:1 mapping)
                    $voucherIndex = 0;
                    foreach ($phones as $phone) {
                        if ($voucherIndex < count($newVoucherIds)) {
                            $voucherData = ORM::for_table('tbl_voucher')->find_one($newVoucherIds[$voucherIndex]);
                            
                            // Replace placeholders in message
                            $message = str_replace('[CODE]', $voucherData['code'], $baseMessage);
                            $message = str_replace('[PLAN]', $planData['name_plan'], $message);
                            
                            try {
                                Message::sendSMS($phone, $message);
                                $smsSuccess++;
                                
                                // Log successful SMS
                                $log = ORM::for_table('tbl_logs')->create();
                                $log->type = 'SMS';
                                $log->description = "Voucher SMS sent to $phone for voucher {$voucherData['code']} during creation";
                                $log->date = date('Y-m-d H:i:s');
                                $log->userid = $admin['id'];
                                $log->save();
                                
                            } catch (Exception $e) {
                                $smsError++;
                                
                                // Log SMS error
                                $log = ORM::for_table('tbl_logs')->create();
                                $log->type = 'SMS_ERROR';
                                $log->description = "Failed to send voucher SMS to $phone: " . $e->getMessage();
                                $log->date = date('Y-m-d H:i:s');
                                $log->userid = $admin['id'];
                                $log->save();
                            }
                            
                            $voucherIndex++;
                        }
                    }
                    
                    // Add SMS status to success message
                    $smsStatusMsg = '';
                    if ($smsSuccess > 0) {
                        $smsStatusMsg .= " | $smsSuccess SMS sent successfully";
                    }
                    if ($smsError > 0) {
                        $smsStatusMsg .= " | $smsError SMS failed";
                    }
                } else {
                    $smsStatusMsg = ' | No valid phone numbers provided';
                }
            }

            if ($numbervoucher == 1) {
                $successMsg = Lang::T('Create Vouchers Successfully') . (isset($smsStatusMsg) ? $smsStatusMsg : '');
                r2(getUrl('plan/voucher'), 's', $successMsg);
            }

            $successMsg = Lang::T('Create Vouchers Successfully');
            if (isset($phoneCount) && $phoneCount > 0) {
                $successMsg .= " ($numbervoucher vouchers generated for $phoneCount phone numbers)";
            }
            $successMsg .= (isset($smsStatusMsg) ? $smsStatusMsg : '');
            r2(getUrl('plan/voucher'), 's', $successMsg);
        } else {
            r2(getUrl('plan/add-voucher/') . $id, 'e', $msg);
        }
        break;

    case 'voucher-delete-many':
        header('Content-Type: application/json');

        $admin = Admin::_info();

        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('You do not have permission to access this page')]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $voucherIds = isset($_POST['voucherIds']) ? json_decode(_post('voucherIds'), true) : [];
            if (!is_array($voucherIds)) {
                $voucherIds = [];
            }

            if (is_array($voucherIds) && !empty($voucherIds)) {
                $voucherIds = array_map('intval', $voucherIds);

                try {
                    ORM::for_table('tbl_voucher')
                        ->where_in('id', $voucherIds)
                        ->delete_many();
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => Lang::T('Failed to delete vouchers.')]);
                    exit;
                }

                // Return success response
                echo json_encode(['status' => 'success', 'message' => Lang::T("Vouchers Deleted Successfully.")]);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => Lang::T("Invalid or missing voucher IDs.")]);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => Lang::T("Invalid request method.")]);
        }
        break;

    case 'voucher-view':
        $id = isset($routes[2]) ? $routes[2] : 0;
        if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            $voucher = ORM::for_table('tbl_voucher')->find_one($id);
        } else {
            $sales = [];
            $sls = ORM::for_table('tbl_users')->select('id')->where('root', $admin['id'])->findArray();
            foreach ($sls as $s) {
                $sales[] = $s['id'];
            }
            $sales[] = $admin['id'];
            $voucher = ORM::for_table('tbl_voucher')
                ->find_one($id);
            if (!in_array($voucher['generated_by'], $sales)) {
                r2(getUrl('plan/voucher/'), 'e', Lang::T('Voucher Not Found'));
            }
        }
        if (!$voucher) {
            r2(getUrl('plan/voucher/'), 'e', Lang::T('Voucher Not Found'));
        }
        $plan = ORM::for_table('tbl_plans')->find_one($voucher['id_plan']);
        if ($voucher && $plan) {
            $content = Lang::pad($config['CompanyName'], ' ', 2) . "\n";
            $content .= Lang::pad($config['address'], ' ', 2) . "\n";
            $content .= Lang::pad($config['phone'], ' ', 2) . "\n";
            $content .= Lang::pad("", '=') . "\n";
            $content .= Lang::pads('ID', $voucher['id'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Code'), $voucher['code'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Plan Name'), $plan['name_plan'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Type'), $voucher['type'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Plan Price'), Lang::moneyFormat($plan['price']), ' ') . "\n";
            $content .= Lang::pads(Lang::T('Sales'), $admin['fullname'] . ' #' . $admin['id'], ' ') . "\n";
            $content .= Lang::pad("", '=') . "\n";
            $content .= Lang::pad($config['note'], ' ', 2) . "\n";
            $ui->assign('print', $content);
            $config['printer_cols'] = 30;
            $content = Lang::pad($config['CompanyName'], ' ', 2) . "\n";
            $content .= Lang::pad($config['address'], ' ', 2) . "\n";
            $content .= Lang::pad($config['phone'], ' ', 2) . "\n";
            $content .= Lang::pad("", '=') . "\n";
            $content .= Lang::pads('ID', $voucher['id'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Code'), $voucher['code'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Plan Name'), $plan['name_plan'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Type'), $voucher['type'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Plan Price'), Lang::moneyFormat($plan['price']), ' ') . "\n";
            $content .= Lang::pads(Lang::T('Sales'), $admin['fullname'] . ' #' . $admin['id'], ' ') . "\n";
            $content .= Lang::pad("", '=') . "\n";
            $content .= Lang::pad($config['note'], ' ', 2) . "\n";
            $ui->assign('_title', Lang::T('View'));
            $ui->assign('whatsapp', urlencode("```$content```"));
            $ui->display('admin/voucher/view.tpl');
        } else {
            r2(getUrl('plan/voucher/'), 'e', Lang::T('Voucher Not Found'));
        }
        break;
    case 'voucher-details-ajax':
        header('Content-Type: application/json');
        $id = isset($routes[2]) ? $routes[2] : 0;
        
        if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            $voucher = ORM::for_table('tbl_voucher')->find_one($id);
        } else {
            $sales = [];
            $sls = ORM::for_table('tbl_users')->select('id')->where('root', $admin['id'])->findArray();
            foreach ($sls as $s) {
                $sales[] = $s['id'];
            }
            $sales[] = $admin['id'];
            $voucher = ORM::for_table('tbl_voucher')->find_one($id);
            if ($voucher && !in_array($voucher['generated_by'], $sales)) {
                echo json_encode(['status' => 'error', 'message' => Lang::T('Voucher Not Found')]);
                exit;
            }
        }
        
        if (!$voucher) {
            echo json_encode(['status' => 'error', 'message' => Lang::T('Voucher Not Found')]);
            exit;
        }
        
        $plan = ORM::for_table('tbl_plans')->find_one($voucher['id_plan']);
        if ($voucher && $plan) {
            // Get country code from database
            $countryCodeConfig = ORM::for_table('tbl_appconfig')
                ->where('setting', 'country_code_phone')
                ->find_one();
            $countryCode = $countryCodeConfig ? $countryCodeConfig->value : '254'; // Default to Kenya
            
            $content = Lang::pad($config['CompanyName'], ' ', 2) . "\n";
            $content .= Lang::pad($config['address'], ' ', 2) . "\n";
            $content .= Lang::pad($config['phone'], ' ', 2) . "\n";
            $content .= Lang::pad("", '=') . "\n";
            $content .= Lang::pads('ID', $voucher['id'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Code'), $voucher['code'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Plan Name'), $plan['name_plan'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Type'), $voucher['type'], ' ') . "\n";
            $content .= Lang::pads(Lang::T('Plan Price'), Lang::moneyFormat($plan['price']), ' ') . "\n";
            $content .= Lang::pads(Lang::T('Sales'), $admin['fullname'] . ' #' . $admin['id'], ' ') . "\n";
            $content .= Lang::pad("", '=') . "\n";
            $content .= Lang::pad($config['note'], ' ', 2) . "\n";
            
            echo json_encode([
                'status' => 'success',
                'content' => $content,
                'whatsapp' => urlencode("```$content```"),
                'voucher_id' => $voucher['id'],
                'country_code' => $countryCode
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => Lang::T('Voucher Not Found')]);
        }
        exit;
        break;
    case 'send-voucher-sms':
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
            exit;
        }
        
        $voucher_id = _post('voucher_id');
        $phone = _post('phone');
        $custom_message = _post('custom_message', '');
        
        // Validate inputs
        if (empty($voucher_id) || empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => 'Voucher ID and phone number are required']);
            exit;
        }
        
        // Clean phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format']);
            exit;
        }
        
        // Get voucher details
        if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            $voucher = ORM::for_table('tbl_voucher')->find_one($voucher_id);
        } else {
            $sales = [];
            $sls = ORM::for_table('tbl_users')->select('id')->where('root', $admin['id'])->findArray();
            foreach ($sls as $s) {
                $sales[] = $s['id'];
            }
            $sales[] = $admin['id'];
            $voucher = ORM::for_table('tbl_voucher')->find_one($voucher_id);
            if ($voucher && !in_array($voucher['generated_by'], $sales)) {
                echo json_encode(['status' => 'error', 'message' => 'Voucher not found or access denied']);
                exit;
            }
        }
        
        if (!$voucher) {
            echo json_encode(['status' => 'error', 'message' => 'Voucher not found']);
            exit;
        }
        
        $plan = ORM::for_table('tbl_plans')->find_one($voucher['id_plan']);
        if (!$plan) {
            echo json_encode(['status' => 'error', 'message' => 'Plan not found']);
            exit;
        }
        
        // Prepare SMS message
        if (!empty($custom_message)) {
            $message = str_replace('[CODE]', $voucher['code'], $custom_message);
            $message = str_replace('[PLAN]', $plan['name_plan'], $message);
        } else {
            // Get company name from database
            $companyNameConfig = ORM::for_table('tbl_appconfig')
                ->where('setting', 'CompanyName')
                ->find_one();
            $companyName = $companyNameConfig ? $companyNameConfig->value : $config['CompanyName'];
            
            $message = "Your voucher code: " . $voucher['code'] . "\n";
            $message .= "Plan: " . $plan['name_plan'] . "\n";
            $message .= "Valid until used. Keep this code safe.\n";
            $message .= "From: " . $companyName;
        }
        
        try {
            $result = Message::sendSMS($phone, $message);
            
            // Log the SMS sending attempt
            $log = ORM::for_table('tbl_logs')->create();
            $log->type = 'SMS';
            $log->description = "Voucher SMS sent to $phone for voucher {$voucher['code']}";
            $log->date = date('Y-m-d H:i:s');
            $log->userid = $admin['id'];
            $log->save();
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'SMS sent successfully to ' . $phone,
                'response' => $result
            ]);
            
        } catch (Exception $e) {
            // Log the error
            $log = ORM::for_table('tbl_logs')->create();
            $log->type = 'SMS_ERROR';
            $log->description = "Failed to send voucher SMS to $phone: " . $e->getMessage();
            $log->date = date('Y-m-d H:i:s');
            $log->userid = $admin['id'];
            $log->save();
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ]);
        }
        
        exit;
        break;
    case 'voucher-delete':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $id = isset($routes['2']) ? intval($routes['2']) : 0;
        if ($id <= 0) {
            r2(getUrl('plan/voucher'), 'e', Lang::T('Invalid request'));
        }
        run_hook('delete_voucher'); #HOOK
        $d = ORM::for_table('tbl_voucher')->find_one($id);
        if ($d) {
            $d->delete();
            r2(getUrl('plan/voucher'), 's', Lang::T('Data Deleted Successfully'));
        }
        break;

    case 'refill':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Report', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('xfooter', $select2_customer);
        $ui->assign('_title', Lang::T('Refill Account'));
        run_hook('view_refill'); #HOOK
        $ui->display('admin/plan/refill.tpl');

        break;

    case 'refill-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Report', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $code = Text::alphanumeric(_post('code'), "-_.,");
        $user = ORM::for_table('tbl_customers')->where('id', _post('id_customer'))->find_one();
        $code_escaped = ORM::for_table('tbl_voucher')->escape($code);
        $v1 = ORM::for_table('tbl_voucher')->where_raw("BINARY code = '$code_escaped'")->where('status', 0)->find_one();

        run_hook('refill_customer'); #HOOK
        if ($v1) {
            if (Package::rechargeUser($user['id'], $v1['routers'], $v1['id_plan'], "Voucher", $code)) {
                $v1->status = "1";
                $v1->user = $user['username'];
                $v1->save();
                $in = ORM::for_table('tbl_transactions')->where('username', $user['username'])->order_by_desc('id')->find_one();
                Package::createInvoice($in);
                $ui->display('admin/plan/invoice.tpl');
            } else {
                r2(getUrl('plan/refill'), 'e', "Failed to refill account");
            }
        } else {
            r2(getUrl('plan/refill'), 'e', Lang::T('Voucher Not Valid'));
        }
        break;
    case 'deposit':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $ui->assign('_title', Lang::T('Refill Balance'));
        $ui->assign('xfooter', $select2_customer);
        if (in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
            $ui->assign('p', ORM::for_table('tbl_plans')->where('type', 'Balance')->find_many());
        } else {
            $ui->assign('p', ORM::for_table('tbl_plans')->where('enabled', '1')->where('type', 'Balance')->find_many());
        }
        run_hook('view_deposit'); #HOOK
        $ui->display('admin/plan/deposit.tpl');
        break;
    case 'deposit-post':
        if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])) {
            _alert(Lang::T('You do not have permission to access this page'), 'danger', "dashboard");
        }
        $user = _post('id_customer');
        $amount = _post('amount');
        $plan = _post('id_plan');
        $note = _post('note');
        $svoucher = _req('svoucher');
        $c = ORM::for_table('tbl_customers')->find_one($user);
        if (App::getVoucherValue($svoucher)) {
            $in = ORM::for_table('tbl_transactions')->find_one(App::getVoucherValue($svoucher));
            Package::createInvoice($in);
            $ui->display('admin/plan/invoice.tpl');
            die();
        }

        run_hook('deposit_customer'); #HOOK
        if (!empty($user) && strlen($amount) > 0 && $amount != 0) {
            $plan = [];
            $plan['name_plan'] = Lang::T('Balance');
            $plan['price'] = $amount;
            $trxId = Package::rechargeBalance($c, $plan, "Deposit", $admin['fullname'], $note);
            if ($trxId > 0) {
                $in = ORM::for_table('tbl_transactions')->find_one($trxId);
                Package::createInvoice($in);
                if (!empty($svoucher)) {
                    App::setVoucher($svoucher, $trxId);
                }
                $ui->display('admin/plan/invoice.tpl');
            } else {
                r2(getUrl('plan/refill'), 'e', "Failed to refill account");
            }
        } else if (!empty($user) && !empty($plan)) {
            $p = ORM::for_table('tbl_plans')->find_one($plan);
            $trxId = Package::rechargeBalance($c, $p, "Deposit", $admin['fullname'], $note);
            if ($trxId > 0) {
                $in = ORM::for_table('tbl_transactions')->find_one($trxId);
                Package::createInvoice($in);
                if (!empty($svoucher)) {
                    App::setVoucher($svoucher, $trxId);
                }
                $ui->display('admin/plan/invoice.tpl');
            } else {
                r2(getUrl('plan/refill'), 'e', "Failed to refill account");
            }
        } else {
            r2(getUrl('plan/refill'), 'e', "All field is required");
        }
        break;
    case 'extend':
        $id = isset($routes[2]) ? $routes[2] : 0;
        $days = isset($routes[3]) ? $routes[3] : 0;
        $svoucher = isset($_GET['svoucher']) ? _get('svoucher') : '';
        if (App::getVoucherValue($svoucher)) {
            r2(getUrl('plan'), 's', "Extend already done");
        }
        $tur = ORM::for_table('tbl_user_recharges')->find_one($id);
        $status = $tur['status'];
        if ($status == 'off') {
            if (strtotime($tur['expiration'] . ' ' . $tur['time']) > time()) {
                // not expired
                $expiration = date('Y-m-d', strtotime($tur['expiration'] . " +$days day"));
            } else {
                //expired
                $expiration = date('Y-m-d', strtotime(" +$days day"));
            }
            App::setVoucher($svoucher, $id);
            $c = ORM::for_table('tbl_customers')->findOne($tur['customer_id']);
            if ($c) {
                $p = ORM::for_table('tbl_plans')->find_one($tur['plan_id']);
                if ($p) {
                    $dvc = Package::getDevice($p);
                    if ($_app_stage != 'demo') {
                        if (file_exists($dvc)) {
                            require_once $dvc;
                            global $isChangePlan;
                            $isChangePlan = true;
                            (new $p['device'])->add_customer($c, $p);
                        } else {
                            throw new Exception(Lang::T("Devices Not Found"));
                        }
                    }
                    $tur->expiration = $expiration;
                    $tur->status = "on";
                    $tur->save();
                } else {
                    r2(getUrl('plan'), 's', "Plan not found");
                }
            } else {
                r2(getUrl('plan'), 's', "Customer not found");
            }
            Message::sendTelegram("#u$tur[username] #id$tur[customer_id]  #extend by $admin[fullname] #" . $p['type'] . " \n" . $p['name_plan'] .
                "\nLocation: " . $p['routers'] .
                "\nCustomer: " . $c['fullname'] .
                "\nNew Expired: " . Lang::dateAndTimeFormat($expiration, $tur['time']));
            _log("$admin[fullname] extend Customer $tur[customer_id] $tur[username] #$tur[customer_id] for $days days", $admin['user_type'], $admin['id']);
            r2(getUrl('plan'), 's', "Extend until $expiration");
        } else {
            r2(getUrl('plan'), 's', "Customer is not expired yet");
        }
        break;
    default:
        // Repair drifted Hrs/Mins expiries (live plan/list calls this)
        try {
            if (is_callable(['Package', 'repairTimeBasedExpirations'])) {
                Package::repairTimeBasedExpirations();
            }
        } catch (Throwable $e) {
        }
        $ui->assign('_title', Lang::T('Active Customers'));
        $search = trim((string) _req('search'));
        $status = (string) _req('status');
        $router = (string) _req('router');
        $plan = (string) _req('plan');
        // Default to Active packages on first load
        if ($status === '' && !isset($_REQUEST['status'])) {
            $status = 'on';
        }
        $append_url = "&search=" . urlencode($search)
            . "&status=" . urlencode($status)
            . "&router=" . urlencode($router)
            . "&plan=" . urlencode($plan);
        $ui->assign('append_url', $append_url);
        $ui->assign('plan', $plan);
        $ui->assign('status', $status);
        $ui->assign('router', $router);
        $ui->assign('search', $search);
        $ui->assign('routers', array_column(ORM::for_table('tbl_user_recharges')->distinct()->select("routers")->whereNotEqual('routers', '')->findArray(), 'routers'));

        $plns = ORM::for_table('tbl_user_recharges')->distinct()->select("plan_id")->findArray();
        $ids = array_column($plns, 'plan_id');
        if (count($ids)) {
            $ui->assign('plans', ORM::for_table('tbl_plans')->select("id")->select('name_plan')->where_id_in($ids)->findArray());
        } else {
            $ui->assign('plans', []);
        }
        $query = ORM::for_table('tbl_user_recharges')->order_by_desc('id');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $phoneDigits = preg_replace('/\D+/', '', $search);
            $phoneLike = ($phoneDigits !== '' && $phoneDigits !== $search) ? ('%' . $phoneDigits . '%') : $like;
            $query->where_raw(
                "(username LIKE ?
                  OR namebp LIKE ?
                  OR method LIKE ?
                  OR customer_id IN (
                      SELECT id FROM tbl_customers
                      WHERE phonenumber LIKE ? OR phonenumber LIKE ? OR fullname LIKE ? OR username LIKE ?
                  ))",
                [$like, $like, $like, $like, $phoneLike, $like, $like]
            );
        }
        if (!empty($router)) {
            $query->where('routers', $router);
        }
        if (!empty($plan)) {
            $query->where('plan_id', $plan);
        }
        if (!empty($status) && $status != '-') {
            $query->where('status', $status);
        }
        // append_url already carries filters — do not duplicate in Paginator search args
        $d = Paginator::findMany($query, [], 25, $append_url);
        if (!$d) {
            $d = [];
        }
        run_hook('view_list_billing'); #HOOK

        // Enrich rows for Active Customers UI (no ORM / broken AJAX in template)
        $rows = [];
        $custIds = [];
        foreach ($d as $row) {
            $item = method_exists($row, 'as_array') ? $row->as_array() : (array) $row;
            $cid = (int) ($item['customer_id'] ?? 0);
            if ($cid > 0) {
                $custIds[$cid] = true;
            }
            $rows[] = $item;
        }
        $customers = [];
        if ($custIds) {
            foreach (ORM::for_table('tbl_customers')->where_in('id', array_keys($custIds))->find_array() as $c) {
                $customers[(int) $c['id']] = $c;
            }
        }
        $connCustomers = [];
        foreach ($customers as $cid => $c) {
            $connCustomers[] = ['id' => $cid, 'username' => $c['username'] ?? ''];
        }
        // Also include recharge usernames when customer_id missing
        foreach ($rows as $item) {
            $cid = (int) ($item['customer_id'] ?? 0);
            if ($cid > 0 && !isset($customers[$cid])) {
                $connCustomers[] = ['id' => $cid, 'username' => $item['username'] ?? ''];
            }
        }
        $connMap = [];
        try {
            if (class_exists('PamnetCustomerStatus')) {
                $connMap = PamnetCustomerStatus::forCustomers($connCustomers);
            }
        } catch (Throwable $e) {
            $connMap = [];
        }
        foreach ($rows as &$item) {
            $cid = (int) ($item['customer_id'] ?? 0);
            $cust = $customers[$cid] ?? null;
            $item['phonenumber'] = $cust['phonenumber'] ?? '';
            $item['customer_fullname'] = $cust['fullname'] ?? '';
            try {
                $resolved = Package::resolveRechargeExpiration($item);
                $item['display_expiration'] = $resolved['expiration'];
                $item['display_time'] = $resolved['time'];
                $item['time_remaining'] = Package::formatTimeRemaining($resolved['expiration'], $resolved['time']);
            } catch (Throwable $e) {
                $item['display_expiration'] = $item['expiration'] ?? '';
                $item['display_time'] = $item['time'] ?? '';
                $item['time_remaining'] = '-';
            }
            if ($cid <= 0) {
                $item['conn_status'] = 'expired';
                $item['conn_html'] = '<span class="label label-default" title="Voucher">—</span>';
            } else {
                $live = $connMap[$cid] ?? ['status' => 'expired', 'plan' => '', 'last_seen' => null];
                // If recharge is off, force Expired
                if ((string) ($item['status'] ?? '') !== 'on') {
                    $live['status'] = 'expired';
                }
                $item['conn_status'] = $live['status'];
                try {
                    $item['conn_html'] = class_exists('PamnetCustomerStatus')
                        ? PamnetCustomerStatus::buttonHtml(
                            $live['status'],
                            trim(($live['plan'] ?? $item['namebp'] ?? '') . ' · ' . ($live['last_seen'] ?? 'no live session'))
                        )
                        : '<span class="label label-default">' . htmlspecialchars((string) $live['status']) . '</span>';
                } catch (Throwable $e) {
                    $item['conn_html'] = '<span class="label label-default">' . htmlspecialchars((string) ($live['status'] ?? 'unknown')) . '</span>';
                }
            }
        }
        unset($item);

        $ui->assign('d', $rows);
        $ui->assign('search_count', count($rows));
        $ui->display('admin/plan/active.tpl');
        break;
}
