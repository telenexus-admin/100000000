<?php

/**
 * AllxSys Internet Management System API
 */

$isApi = true;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    antiqua_api_cors_headers();
    header('HTTP/1.1 200 OK');
    die();
}

$ui = new class()
{
    public $assign = [];

    public function display($key)
    {
        global $req;
        showResult(true, $req, $this->getAll());
    }

    public function assign($key, $value)
    {
        $this->assign[$key] = $value;
    }

    public function get($key)
    {
        if (isset($this->assign[$key])) {
            return $this->assign[$key];
        }
        return '';
    }

    public function getTemplateVars($key)
    {
        if (isset($this->assign[$key])) {
            return $this->assign[$key];
        }
        return '';
    }

    public function getAll()
    {
        $result = [];
        foreach ($this->assign as $key => $value) {
            if ($value instanceof ORM) {
                $result[$key] = $value->as_array();
            } elseif ($value instanceof IdiormResultSet) {
                $count = count($value);
                for ($n = 0; $n < $count; $n++) {
                    foreach ($value[$n] as $k => $v) {
                        $result[$key][$n][$k] = $v;
                    }
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function fetch()
    {
        return '';
    }
};

$req = _get('r');
$token = _req('token');
$routes = explode('/', $req);
$handler = isset($routes[0]) ? $routes[0] : '';

/**
 * Strip secrets before returning profile JSON from /me.
 */
function antiqua_api_me_payload(array $row)
{
    foreach (['password', 'pppoe_password', 'otp', 'login_token'] as $k) {
        if (array_key_exists($k, $row)) {
            unset($row[$k]);
        }
    }
    return $row;
}

if (!empty($token)) {
    $apiKeyOk = !empty($config['api_key'])
        && is_string($config['api_key'])
        && hash_equals($config['api_key'], (string) $token);

    if ($apiKeyOk) {
        $admin = ORM::for_table('tbl_users')->where('user_type', 'SuperAdmin')->order_by_asc('id')->find_one();
        if (empty($admin)) {
            $admin = ORM::for_table('tbl_users')->where('user_type', 'Admin')->order_by_asc('id')->find_one();
        }
        if (empty($admin)) {
            showResult(false, Lang::T('Token is invalid'));
        }
        $_SESSION['aid'] = (int) $admin->id();
        unset($_SESSION['uid']);
    } else {
        $parts = explode('.', $token);
        if (count($parts) !== 4) {
            showResult(false, Lang::T('Token is invalid'));
        }
        list($tipe, $uid, $time, $sha1) = $parts;
        $secret = isset($api_secret) ? $api_secret : '';
        if (!hash_equals((string) $sha1, sha1($uid . '.' . $time . '.' . $secret))) {
            showResult(false, Lang::T('Token is invalid'));
        }

        if ($time != 0 && time() - (int) $time > 7776000) {
            showResult(false, Lang::T('Token Expired'), [], ['login' => true]);
        }

        if ($tipe === 'a') {
            $_SESSION['aid'] = (int) $uid;
            unset($_SESSION['uid']);
        } elseif ($tipe === 'c') {
            $_SESSION['uid'] = (int) $uid;
            unset($_SESSION['aid']);
        } else {
            showResult(false, Lang::T('Unknown Token'), [], ['login' => true]);
        }
    }

    if ($handler === '' || $handler === null) {
        showResult(true, Lang::T('Token is valid'));
    }

    if ($handler === 'isValid') {
        showResult(true, Lang::T('Token is valid'));
    }

    if ($handler === 'me') {
        if (!empty($_SESSION['aid'])) {
            $admin = Admin::_info();
            if ($admin && !empty($admin['id'])) {
                showResult(true, '', antiqua_api_me_payload($admin->as_array()));
            }
        } elseif (!empty($_SESSION['uid'])) {
            $user = ORM::for_table('tbl_customers')->find_one((int) $_SESSION['uid']);
            if ($user && !empty($user['id'])) {
                showResult(true, '', antiqua_api_me_payload($user->as_array()));
            }
        }
        showResult(false, Lang::T('Token is invalid'));
    }
} else {
    $_SESSION = [];
}

try {
    $sys_render = File::pathFixer($root_path . 'system/controllers/' . $handler . '.php');
    if ($handler !== '' && file_exists($sys_render)) {
        include $sys_render;
        showResult(true, $req, $ui->getAll());
    }
    showResult(false, Lang::T('Command not found'));
} catch (Throwable $e) {
    error_log('API ' . $handler . ': ' . $e->getMessage());
    global $_app_stage;
    $msg = (!empty($_app_stage) && strcasecmp((string) $_app_stage, 'Live') === 0)
        ? 'Server error'
        : $e->getMessage();
    showResult(false, $msg);
}
