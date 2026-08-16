<?php 
 register_menu("Add Customer", true, "quickadd", 'AFTER_CUSTOMERS',  'ion ion-person-add');

 function quickadd()
{
	global $ui, $routes, $config;
	_admin();
    $ui->assign('_system_menu', 'quickadd');
    $admin = Admin::_info();
    $ui->assign('_admin', $admin);
    $ui->assign('routes', $routes);
	$ui->assign('_title', 'Add Customer');
    $pltype = 'Hotspot';
	if ($routes['2'] == 'pppoe') {
        $pltype = 'PPPOE';
	}
    $plans = ORM::for_table('tbl_plans')->where('type', $pltype)->where('enabled', 1)->find_many();
    $ui->assign('plans', $plans);
	if ($routes['2'] == 'add') {
        $rdrct = '';
		if ($routes['3'] == 'pppoe') {
            $rdrct = '/pppoe';
        }
        
        // --- BLOK CSRF TOKEN TELAH DIHAPUS ---
        // Baris berikut yang dihapus:
        // $csrf_token = _post('csrf_token');
        // if (!Csrf::check($csrf_token)) {
        //     r2(U . 'plugin/quickadd'.$rdrct, 'e', Lang::T('Invalid or Expired CSRF Token') . ".");
        // }
        // ------------------------------------
        
        $username = alphanumeric(_post('username'), ":+_.@-");
        $fullname = _post('fullname');
        $password = trim(_post('password'));
        
        // START MODIFIKASI: Menangkap data PPPoE fields
        $pppoe_username = trim(_post('pppoe_username'));
        $pppoe_password = trim(_post('pppoe_password'));
        $pppoe_ip = trim(_post('pppoe_ip')); // <-- Menangkap Remote IP
        // END MODIFIKASI
        
        $email = _post('email');
        $address = _post('address');
        $ppln = _post('ppln');
        $phonenumber = _post('phonenumber');
        $service_type = _post('service_type');
        $account_type = 'Personal';
        $city = _post('city');

        run_hook('add_customer'); #HOOK
        $msg = '';
        if (Validator::Length($username, 55, 2) == false) {
            $msg .= 'Username should be between 3 to 54 characters' . '<br>';
        }
        if ($ppln == '') {
            $msg .= 'Select Package first!' . '<br>';
        }
        if (Validator::Length($fullname, 36, 1) == false) {
            $msg .= 'Full Name should be between 2 to 25 characters' . '<br>';
        }
        if (!Validator::Length($password, 36, 2)) {
            $msg .= 'Password should be between 3 to 35 characters' . '<br>';
        }

        $d = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
        if ($d) {
            $msg .= Lang::T('Account already axist') . '<br>';
        }
        if ($msg == '') {
            $d = ORM::for_table('tbl_customers')->create();
            $d->username = $username;
            $d->password = $password;
            
            // START MODIFIKASI: Menyimpan data PPPoE fields ke database
            $d->pppoe_username = $pppoe_username;
            $d->pppoe_password = $pppoe_password;
            $d->pppoe_ip = $pppoe_ip; // <-- Menyimpan Remote IP
            // END MODIFIKASI
            
            $d->email = $email;
            $d->account_type = $account_type;
            $d->fullname = $fullname;
            $d->address = $address;
            $d->created_by = $admin['id'];
            $d->phonenumber = Lang::phoneFormat($phonenumber);
            $d->service_type = $service_type;
            $d->city = $city;
            $d->save();
            $plan = ORM::for_table('tbl_plans')->where('enabled', 1)->find_one($ppln);
            $server = $plan['routers'];
            if ($plan['is_radius'] == '1') {
                $server = 'Radius';
            }
            //recharge new customer
            Package::rechargeUser($d['id'], $server, $plan['id'], 'Cash', $admin['fullname']);
            // Send welcome message
            if (isset($_POST['send_welcome_message']) && $_POST['send_welcome_message'] == true) {
                $welcomeMessage = Lang::getNotifTextForPlan(
                    'welcome_message',
                    $plan ? $plan->as_array() : null,
                    $d ? $d->as_array() : null
                );
                $welcomeMessage = str_replace('[[company]]', $config['CompanyName'], $welcomeMessage);
                $welcomeMessage = str_replace('[[name]]', $d['fullname'], $welcomeMessage);
                $welcomeMessage = str_replace('[[username]]', $d['username'], $welcomeMessage);
                $welcomeMessage = str_replace('[[password]]', $d['password'], $welcomeMessage);
                $welcomeMessage = str_replace('[[url]]', APP_URL . '/?_route=login', $welcomeMessage);

                $emailSubject = "Welcome to " . $config['CompanyName'];

                $channels = [
                    'sms' => [
                        'enabled' => isset($_POST['sms']),
                        'callable' => ['Message', 'sendSMS'],
                        'args' => [$d['phonenumber'], $welcomeMessage]
                    ],
                    'whatsapp' => [
                        'enabled' => isset($_POST['wa']),
                        'callable' => ['Message', 'sendWhatsapp'],
                        'args' => [$d['phonenumber'], $welcomeMessage]
                    ],
                    'email' => [
                        'enabled' => isset($_POST['mail']),
                        'callable' => ['Message', 'sendEmail'],
                        'args' => [$d['email'], $emailSubject, $welcomeMessage]
                    ]
                ];

                foreach ($channels as $channel => $message) {
                    if ($message['enabled']) {
                        try {
                            if (isset($message['callable']) && is_callable($message['callable'])) {
                                call_user_func_array($message['callable'], (array) ($message['args'] ?? []));
                            } else {
                                _log("Skipped welcome message via $channel: invalid callback");
                            }
                        } catch (Throwable $e) {
                            _log("Failed to send welcome message via $channel: " . $e->getMessage());
                        }
                    }
                }
            }
            r2(U . 'customers/view/'.$d['id'], 's', Lang::T('Account Created Successfully'));
        } else {
            r2(U . "plugin/quickadd".$rdrct, 'e', $msg);
        }
	}
		
	$ui->display('quickadd.tpl');
} 
