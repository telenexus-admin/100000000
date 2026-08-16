<?php

 
$step = _req('step', 0);
$otpPath = $CACHE_PATH . File::pathFixer('/forgot/');

if ($step == '-1') {
    $_COOKIE['forgot_username'] = '';
    setcookie('forgot_username', '', time() - 3600, '/');
    $step = 0;
}

if (!empty($_COOKIE['forgot_username']) && in_array($step, [0, 1])) {
    $step = 1;
    $_POST['username'] = $_COOKIE['forgot_username'];
}

if ($step == 1) {
    $username = _post('username');
    if (!empty($username)) {
        $ui->assign('username', $username);
        if (!file_exists($otpPath)) {
            mkdir($otpPath);
        }
        setcookie('forgot_username', $username, time() + 3600, '/');
        $user = ORM::for_table('tbl_customers')->selects(['phonenumber', 'email'])->where('username', $username)->find_one();
        if ($user) {
            $otpPath .= sha1($username . $db_pass) . ".txt";
            if (file_exists($otpPath) && time() - filemtime($otpPath) < 600) {
                $sec = time() - filemtime($otpPath);
                $ui->assign('notify_t', 's');
                $ui->assign('notify', Lang::T("Verification Code already Sent to Your Phone/Email/Whatsapp, please wait")." $sec seconds.");
            } else {
                // Pick the best available channel for sending the OTP.
                // "user_notification_reminder" may be set to none/email/sms/whatsapp.
                // We fall back to whatever gateway is actually configured so the
                // user is never left without a code.
                $via = strtolower((string) ($config['user_notification_reminder'] ?? ''));
                $hasSms = !empty($config['sms_url']) || !empty($config['sms_gateway_type']);
                $hasWa  = !empty($config['wa_url']);
                $hasMail = !empty($config['smtp_host']) && !empty($user['email']);

                if ($via === '' || $via === 'none') {
                    if ($hasSms) $via = 'sms';
                    elseif ($hasWa) $via = 'whatsapp';
                    elseif ($hasMail) $via = 'email';
                    else $via = 'sms';
                } elseif ($via === 'email' && !$hasMail) {
                    $via = $hasSms ? 'sms' : ($hasWa ? 'whatsapp' : 'sms');
                } elseif ($via === 'whatsapp' && !$hasWa) {
                    $via = $hasSms ? 'sms' : 'email';
                } elseif ($via === 'sms' && !$hasSms) {
                    $via = $hasWa ? 'whatsapp' : 'email';
                }

                $otp = mt_rand(100000, 999999);
                file_put_contents($otpPath, $otp);
                $codeText = $config['CompanyName'] . " Code: $otp";
                $sent = false;

                if ($via === 'sms' && !empty($user['phonenumber'])) {
                    $sent = (bool) Message::sendSMS($user['phonenumber'], $codeText) || $hasSms;
                } elseif ($via === 'whatsapp' && !empty($user['phonenumber'])) {
                    Message::sendWhatsapp($user['phonenumber'], $codeText);
                    $sent = $hasWa;
                }

                // Always additionally email the code when SMTP is actually set up.
                if ($hasMail) {
                    Message::sendEmail(
                        $user['email'],
                        $config['CompanyName'] . ' - ' . Lang::T("Your Verification Code"),
                        Lang::T("Your Verification Code") . ' : <b>' . $otp . '</b>'
                    );
                    $sent = true;
                }

                // If nothing worked, fall back to trying SMS anyway (so the gateway
                // logs at least get an attempt) and surface a generic message.
                if (!$sent && !empty($user['phonenumber'])) {
                    Message::sendSMS($user['phonenumber'], $codeText);
                }

                $ui->assign('notify_t', 's');
                $ui->assign('notify', Lang::T("If your Username is found, Verification Code has been Sent to Your Phone/Email/Whatsapp"));
            }
        } else {
            // Username not found
            $ui->assign('notify_t', 's');
            $ui->assign('notify', Lang::T("If your Username is found, Verification Code has been Sent to Your Phone/Email/Whatsapp") . ".");
        }
    } else {
        $step = 0;
    }
} else if ($step == 2) {
    $username = trim((string) _post('username'));
    $otp_code = trim((string) _post('otp_code'));
    if (!empty($username) && !empty($otp_code)) {
        $otpPath .= sha1($username . $db_pass) . ".txt";
        if (file_exists($otpPath) && time() - filemtime($otpPath) <= 600) {
            $otp = trim((string) file_get_contents($otpPath));
            if ($otp !== '' && $otp === $otp_code) {
                $pass = (string) mt_rand(10000, 99999);
                $user = ORM::for_table('tbl_customers')->where('username', $username)->find_one();
                if (!$user) {
                    // Username somehow no longer exists (was deleted between
                    // step 1 and step 2). Clean up and bail.
                    if (file_exists($otpPath)) unlink($otpPath);
                    r2(getUrl('forgot&step=1'), 'e', Lang::T('Invalid Username or Verification Code'));
                }

                // Save via the ORM first.
                $user->password = $pass;
                $saved = false;
                try {
                    $saved = (bool) $user->save();
                } catch (Throwable $e) {
                    _log('Forgot password ORM save failed for ' . $username . ': ' . $e->getMessage(), 'User');
                    $saved = false;
                }

                // Verify the write landed and, if not, fall back to a raw
                // UPDATE so the user is never left with an unchanged password.
                $verify = ORM::for_table('tbl_customers')
                    ->select('password')
                    ->where('username', $username)
                    ->find_one();
                if (!$verify || (string) $verify['password'] !== $pass) {
                    try {
                        ORM::raw_execute(
                            'UPDATE tbl_customers SET password = ? WHERE username = ?',
                            [$pass, $username]
                        );
                        $verify = ORM::for_table('tbl_customers')
                            ->select('password')
                            ->where('username', $username)
                            ->find_one();
                    } catch (Throwable $e) {
                        _log('Forgot password raw UPDATE failed for ' . $username . ': ' . $e->getMessage(), 'User');
                    }
                }

                if ($verify && (string) $verify['password'] === $pass) {
                    _log($username . ' ' . Lang::T('Password reset via Verification Code'), 'User');
                    $ui->assign('username', $username);
                    $ui->assign('passsword', $pass);
                    $ui->assign('notify_t', 's');
                    $ui->assign('notify', Lang::T("Verification Code Valid"));
                    if (file_exists($otpPath)) {
                        unlink($otpPath);
                    }
                    setcookie('forgot_username', '', time() - 3600, '/');
                } else {
                    _log('Forgot password update did not persist for ' . $username, 'User');
                    r2(getUrl('forgot&step=1'), 'e', Lang::T('Failed to reset password, please try again'));
                }
            } else {
                r2(getUrl('forgot&step=1'), 'e', Lang::T('Invalid Username or Verification Code'));
            }
        } else {
            if (file_exists($otpPath)) {
                unlink($otpPath);
            }
            r2(getUrl('forgot&step=1'), 'e', Lang::T('Invalid Username or Verification Code'));
        }
    } else {
        r2(getUrl('forgot&step=1'), 'e', Lang::T('Invalid Username or Verification Code'));
    }
} else if ($step == 7) {
    $find = _post('find');
    $step = 6;
    if (!empty($find)) {
        // Pick a channel that is actually configured.
        $via = strtolower((string) ($config['user_notification_reminder'] ?? ''));
        $hasSms = !empty($config['sms_url']) || !empty($config['sms_gateway_type']);
        $hasWa  = !empty($config['wa_url']);
        if ($via === '' || $via === 'none' || $via === 'email') {
            $via = $hasSms ? 'sms' : ($hasWa ? 'whatsapp' : 'sms');
        } elseif ($via === 'whatsapp' && !$hasWa) {
            $via = 'sms';
        } elseif ($via === 'sms' && !$hasSms) {
            $via = $hasWa ? 'whatsapp' : 'sms';
        }
        if (!file_exists($otpPath)) {
            mkdir($otpPath);
        }
        $otpPath .= sha1($find . $db_pass) . ".txt";
        $users = ORM::for_table('tbl_customers')->selects(['username', 'phonenumber', 'email'])->where('phonenumber', $find)->find_array();
        if ($users) {
            // prevent flooding only can request every 10 minutes
            if (!file_exists($otpPath) || (file_exists($otpPath) && time() - filemtime($otpPath) >= 600)) {
                $usernames = implode(", ", array_column($users, 'username'));
                if ($via == 'sms') {
                    Message::sendSMS($find, Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames);
                } else {
                    Message::sendWhatsapp($find, Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames);
                }
                file_put_contents($otpPath, time());
            }
            $ui->assign('notify_t', 's');
            $ui->assign('notify', Lang::T("Usernames have been sent to your phone/Whatsapp") . " $find");
            $step = 0;
        } else {
            $users = ORM::for_table('tbl_customers')->selects(['username', 'phonenumber', 'email'])->where('email', $find)->find_array();
            if ($users) {
                // prevent flooding only can request every 10 minutes
                if (!file_exists($otpPath) || (file_exists($otpPath) && time() - filemtime($otpPath) >= 600)) {
                    $usernames = implode(", ", array_column($users, 'username'));
                    $phones = [];
                    foreach ($users as $user) {
                        if (!in_array($user['phonenumber'], $phones)) {
                            if ($via == 'sms') {
                                Message::sendSMS($user['phonenumber'], Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames);
                            } else {
                                Message::sendWhatsapp($user['phonenumber'], Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames);
                            }
                            $phones[] = $user['phonenumber'];
                        }
                    }
                    Message::sendEmail(
                        $user['email'],
                        Lang::T("Your username for") . ' ' . $config['CompanyName'],
                        Lang::T("Your username for") . ' ' . $config['CompanyName'] . "\n" . $usernames
                    );
                    file_put_contents($otpPath, time());
                }
                $ui->assign('notify_t', 's');
                $ui->assign('notify', Lang::T("Usernames have been sent to your phone/Whatsapp/Email"));
                $step = 0;
            } else {
                $ui->assign('notify_t', 'e');
                $ui->assign('notify', Lang::T("No data found"));
            }
        }
    }
}

// delete old files
$pth = $CACHE_PATH . File::pathFixer('/forgot/');
$fs = scandir($pth);
foreach ($fs as $file) {
    if(is_file($pth.$file) && time() - filemtime($pth.$file) > 3600) {
        unlink($pth.$file);
    }
}

$ui->assign('step', $step);
$ui->assign('_title', Lang::T('Forgot Password'));
$ui->display('customer/forgot.tpl');
