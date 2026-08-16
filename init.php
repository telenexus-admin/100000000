<?php

 

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden', TRUE, 403);
    header('location: ../');
    die();
}
$root_path = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
if (!isset($isApi)) {
    $isApi = false;
}
// on some server, it getting error because of slash is backwards
function _autoloader($class)
{
    global $root_path;
    if (strpos($class, '_') !== false) {
        $class = str_replace('_', DIRECTORY_SEPARATOR, $class);
        if (file_exists($root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php')) {
            include $root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php';
        } else {
            $class = str_replace("\\", DIRECTORY_SEPARATOR, $class);
            if (file_exists($root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php'))
                include $root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php';
        }
    } else {
        if (file_exists($root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php')) {
            include $root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php';
        } else {
            $class = str_replace("\\", DIRECTORY_SEPARATOR, $class);
            if (file_exists($root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php'))
                include $root_path . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR . $class . '.php';
        }
    }
}
spl_autoload_register('_autoloader');

if (!file_exists($root_path . 'config.php')) {
    $root_path .= '..' . DIRECTORY_SEPARATOR;
    if (!file_exists($root_path . 'config.php')) {
        r2('./install');
    }
}

if (!file_exists($root_path .  File::pathFixer('system/orm.php'))) {
    echo $root_path . "orm.php file not found";
    die();
}

$DEVICE_PATH = $root_path . File::pathFixer('system/devices');
$UPLOAD_PATH = $root_path . File::pathFixer('system/uploads');
$CACHE_PATH = $root_path . File::pathFixer('system/cache');
$PAGES_PATH = $root_path . File::pathFixer('pages');
$PLUGIN_PATH = $root_path . File::pathFixer('system/plugin');
$WIDGET_PATH = $root_path . File::pathFixer('system/widgets');
$PAYMENTGATEWAY_PATH = $root_path . File::pathFixer('system/paymentgateway');
$UI_PATH = 'ui';

if (!file_exists($UPLOAD_PATH . File::pathFixer('/notifications.default.json'))) {
    echo $UPLOAD_PATH . File::pathFixer("/notifications.default.json file not found");
    die();
}

require_once $root_path . 'config.php';
require_once $root_path . File::pathFixer('system/orm.php');
require_once $root_path . File::pathFixer('system/autoload/PEAR2/Autoload.php');
include $root_path . File::pathFixer('system/autoload/Hookers.php');

if ($db_password != null && ($db_pass == null || empty($db_pass))) {
    // compability for old version
    $db_pass = $db_password;
}
if ($db_pass != null) {
    // compability for old version
    $db_password = $db_pass;
}
ORM::configure("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4");
ORM::configure('username', $db_user);
ORM::configure('password', $db_pass);
ORM::configure('driver_options', array(
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, time_zone="+03:00", sql_mode="", wait_timeout=300, interactive_timeout=300',
    PDO::ATTR_TIMEOUT => 30,
    PDO::ATTR_PERSISTENT => false, // Disable persistent connections to prevent buildup
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_COMPRESS => true,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));
ORM::configure('return_result_sets', true);
if ($_app_stage != 'Live') {
    ORM::configure('logging', true);
}
if ($isApi) {
    define('U', APP_URL . '/system/api.php?r=');
} else {
    define('U', APP_URL . '/?_route=');
}

// notification message
$_notifmsg = null;
if (file_exists($UPLOAD_PATH . DIRECTORY_SEPARATOR . "notifications.json")) {
    $notif_content = file_get_contents($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'notifications.json');
    if ($notif_content !== false) {
        $_notifmsg = json_decode($notif_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $_notifmsg = null;
            error_log("Failed to decode notifications.json: " . json_last_error_msg());
        }
    }
}
$notif_default_content = file_get_contents($UPLOAD_PATH . DIRECTORY_SEPARATOR . 'notifications.default.json');
$_notifmsg_default = [];
if ($notif_default_content !== false) {
    $_notifmsg_default = json_decode($notif_default_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($_notifmsg_default)) {
        $_notifmsg_default = [];
        error_log("Failed to decode notifications.default.json: " . json_last_error_msg());
    }
}

//register all plugins (optimized with basic validation)
$plugin_files = glob(File::pathFixer($PLUGIN_PATH . DIRECTORY_SEPARATOR . '*.php'));
if ($plugin_files) {
    foreach ($plugin_files as $filename) {
        if (is_readable($filename) && filesize($filename) > 0) {
            try {
                include_once $filename; // Use include_once to prevent double loading
            } catch (Throwable $e) {
                //ignore plugin error - log in production
                error_log("Plugin error in $filename: " . $e->getMessage());
            } catch (Exception $e) {
                //ignore plugin error - log in production  
                error_log("Plugin error in $filename: " . $e->getMessage());
            }
        }
    }
}

$result = ORM::for_table('tbl_appconfig')->find_many();
foreach ($result as $value) {
    $config[$value['setting']] = $value['value'];
}

if(empty($config['dashboard_Admin'])){
    $config['dashboard_Admin'] = "12.7,5.12";
}

if(empty($config['dashboard_Agent'])){
    $config['dashboard_Agent'] = "12.7,5.12";
}

if(empty($config['dashboard_Sales'])){
    $config['dashboard_Sales'] = "12.7,5.12";
}

if(empty($config['dashboard_Customer'])){
    $config['dashboard_Customer'] = "6,6";
}


$_c =  $config;
if (empty($http_proxy) && !empty($config['http_proxy'])) {
    $http_proxy = $config['http_proxy'];
    if (empty($http_proxyauth) && !empty($config['http_proxyauth'])) {
        $http_proxyauth = $config['http_proxyauth'];
    }
}
if (empty($config['timezone'])) {
    $config['timezone'] = 'Africa/Nairobi';
    try {
        $tzRow = ORM::for_table('tbl_appconfig')->where('setting', 'timezone')->find_one();
        if ($tzRow) {
            $tzRow->value = 'Africa/Nairobi';
            $tzRow->save();
        } else {
            $tzRow = ORM::for_table('tbl_appconfig')->create();
            $tzRow->setting = 'timezone';
            $tzRow->value = 'Africa/Nairobi';
            $tzRow->save();
        }
    } catch (Throwable $e) {
    }
}
try {
    date_default_timezone_set($config['timezone']);
} catch (Throwable $e) {
    $config['timezone'] = 'Africa/Nairobi';
    date_default_timezone_set('Africa/Nairobi');
}
// Keep MySQL NOW() aligned with billing PHP timezone
try {
    if (class_exists('MikrotikTimeSync')) {
        MikrotikTimeSync::applyMysqlTimezone();
    } else {
        $offset = (new DateTime('now'))->format('P');
        ORM::raw_execute("SET time_zone = '" . str_replace("'", '', $offset) . "'");
    }
} catch (Throwable $e) {
}

// RADIUS can use dedicated credentials when supplied. If this installation
// keeps the FreeRADIUS tables in the same database as the billing system,
// automatically reuse the main DB connection instead of leaving the named
// `radius` ORM connection unconfigured.
if (empty($radius_host)) { $radius_host = $db_host; }
if (empty($radius_name)) { $radius_name = $db_name; }
if (empty($radius_user)) { $radius_user = $db_user; }
if (!isset($radius_pass) || $radius_pass === '') { $radius_pass = $db_pass; }

if ((!empty($radius_user) && !empty($config['radius_enable'])) || _post('radius_enable')) {
    if (!empty($radius_password)) {
        // compability for old version
        $radius_pass = $radius_password;
    }
    ORM::configure("mysql:host=$radius_host;dbname=$radius_name;charset=utf8mb4", null, 'radius');
    ORM::configure('username', $radius_user, 'radius');
    ORM::configure('password', $radius_pass, 'radius');
    ORM::configure('driver_options', array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4, sql_mode="", wait_timeout=300, interactive_timeout=300',
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_COMPRESS => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ), 'radius');
    ORM::configure('return_result_sets', true, 'radius');
}


// Check if the user has selected a language
if (!empty($_SESSION['user_language'])) {
    $config['language'] = $_SESSION['user_language'];
} else if (!empty($_COOKIE['user_language'])) {
    $config['language'] = $_COOKIE['user_language'];
} else if (User::getID() > 0) {
    $lang = User::getAttribute("Language");
    if (!empty($lang)) {
        $config['language'] = $lang;
    }
}

if (empty($config['language'])) {
    $config['language'] = 'english';
}
$lan_file = $root_path . File::pathFixer('system/lan/' . $config['language'] . '.json');
$_L = [];
if (file_exists($lan_file)) {
    $lang_content = file_get_contents($lan_file);
    if ($lang_content !== false) {
        $_L = json_decode($lang_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($_L)) {
            $_L = [];
            error_log("Failed to decode language file: " . json_last_error_msg());
        }
    }
}
if (empty($_L)) {
    $_L['author'] = 'Auto Generated by RayProTech System';
    $lang_dir = dirname($lan_file);
    if (!is_dir($lang_dir)) {
        @mkdir($lang_dir, 0755, true);
    }
    @file_put_contents($lan_file, json_encode($_L, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function safedata($value)
{
    $value = trim($value);
    return $value;
}

function _post($param, $defvalue = '')
{
    if (!isset($_POST[$param])) {
        return $defvalue;
    } else {
        return safedata($_POST[$param]);
    }
}

function _get($param, $defvalue = '')
{
    if (!isset($_GET[$param])) {
        return $defvalue;
    } else {
        return safedata($_GET[$param]);
    }
}

function _req($param, $defvalue = '')
{
    if (!isset($_REQUEST[$param])) {
        return $defvalue;
    } else {
        return safedata($_REQUEST[$param]);
    }
}


function _auth($login = true)
{
    if (User::getID()) {
        return true;
    } else {
        if ($login) {
            r2(getUrl('login'));
        } else {
            return false;
        }
    }
}

function _admin($login = true)
{
    if (Admin::getID()) {
        return true;
    } else {
        if ($login) {
            r2(getUrl('login'));
        } else {
            return false;
        }
    }
}


function _log($description, $type = '', $userid = '0')
{
    $d = ORM::for_table('tbl_logs')->create();
    $d->date = date('Y-m-d H:i:s');
    $d->type = $type;
    $d->description = $description;
    $d->userid = $userid;
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))   //to check ip is pass from cloudflare tunnel
    {
        $d->ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
        $d->ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP']))   //to check ip from share internet
    {
        $d->ip = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER["REMOTE_ADDR"])) {
        $d->ip = $_SERVER["REMOTE_ADDR"];
    } else if (php_sapi_name() == 'cli') {
        $d->ip = 'CLI';
    } else {
        $d->ip = 'Unknown';
    }
    $d->save();
}

function Lang($key)
{
    return Lang::T($key);
}

function alphanumeric($str, $tambahan = "")
{
    return Text::alphanumeric($str, $tambahan);
}

function showResult($success, $message = '', $result = [], $meta = [])
{
    header("Content-Type: Application/json");
    $json = json_encode(['success' => $success, 'message' => $message, 'result' => $result, 'meta' => $meta]);
    echo $json;
    die();
}

/**
 * make url canonical or standar
 */
function getUrl($url)
{
    return Text::url($url);
}

function generateUniqueNumericVouchers($totalVouchers, $length = 8)
{
    // Define characters allowed in the voucher code
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $vouchers = array();

    // Attempt to generate unique voucher codes
    for ($j = 0; $j < $totalVouchers; $j++) {
        do {
            $voucherCode = '';
            // Generate the voucher code
            for ($i = 0; $i < $length; $i++) {
                $voucherCode .= $characters[rand(0, $charactersLength - 1)];
            }
            // Check if the generated voucher code already exists in the array
            $isUnique = !in_array($voucherCode, $vouchers);
        } while (!$isUnique);

        $vouchers[] = $voucherCode;
    }

    return $vouchers;
}

function sendTelegram($txt)
{
    Message::sendTelegram($txt);
}

function sendSMS($phone, $txt)
{
    Message::sendSMS($phone, $txt);
}

function sendWhatsapp($phone, $txt)
{
    Message::sendWhatsapp($phone, $txt);
}

function r2($to, $ntype = 'e', $msg = '')
{
    global $isApi;
    if ($isApi) {
        showResult(
            ($ntype == 's') ? true : false,
            $msg
        );
    }
    if ($msg == '') {
        header("location: $to");
        exit;
    }
    $_SESSION['ntype'] = $ntype;
    $_SESSION['notify'] = $msg;
    header("location: $to");
    exit;
}

function _alert($text, $type = 'success', $url = "home", $time = 3)
{
    global $ui, $isApi;
    if ($isApi) {
        showResult(
            ($type == 'success') ? true : false,
            $text
        );
    }
    if (!isset($ui)) return;
    if (strlen($url) > 4) {
        if (substr($url, 0, 4) != "http") {
            $url = getUrl($url);
        }
    } else {
        $url = getUrl($url);
    }
    $ui->assign('text', $text);
    $ui->assign('type', $type);
    $ui->assign('time', $time);
    $ui->assign('url', $url);
    $ui->display('admin/alert.tpl');
    die();
}


if (!isset($api_secret)) {
    $api_secret = $db_pass;
}

function displayMaintenanceMessage(): void
{
    global $config, $ui;
    $date = $config['maintenance_date'];
    if ($date) {
        $ui->assign('date', $date);
    }
    http_response_code(503);
    $ui->assign('companyName', $config['CompanyName']);
    $ui->display('admin/maintenance.tpl');
    die();
}

function isTableExist($table)
{
    try {
        $record = ORM::forTable($table)->find_one();
        return $record !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * System-wide database connection cleanup to prevent connection pool exhaustion
 * This function is automatically called at the end of each request
 */
function allxsys_cleanup_connections() 
{
    try {
        // Force garbage collection to clean up PDO objects
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Optional: Clear ORM cache if available
        if (class_exists('ORM') && method_exists('ORM', 'reset_db')) {
            // Only reset in development mode to be safe
            global $_app_stage;
            if ($_app_stage !== 'Live') {
                ORM::reset_db();
            }
        }
    } catch (Exception $e) {
        // Silently handle cleanup errors to prevent breaking the application
        error_log("Database cleanup warning: " . $e->getMessage());
    }
}

// Register the cleanup function to run at the end of each request
register_shutdown_function('allxsys_cleanup_connections');
