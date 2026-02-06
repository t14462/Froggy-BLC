<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


$server_ip = gethostbyname($_SERVER['SERVER_NAME'].".");

if (!in_array($server_ip, ['127.0.0.1'])) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    // error_reporting(0);

    ini_set('log_errors', '1');
    // ini_set('error_log', '/var/log/php_errors.log'); // укажи актуальный путь
}


define('SECURE_ACCESS', true);





$num = 0;
$count = 0;
$htag = 0;
$herror = 0;
$commcount = 0;
$mainPageTitle = "";

$head = "";
$body = "";
$errmsg = "";
$commmsg = "";
$txtpath = "";
$content = "";
$tplcomments = "";

$printpgvar = null;
$sMobile = "";

$commRecov = "";

$metaDescription = "";

$seoPagesTimes = Array();






// $errmsg .= $server_ip;



/*
$currentDir = getcwd(); // Сохраняем текущую директорию
chdir($currentDir);
*/

mb_internal_encoding("UTF-8");

ob_start();




$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    $_SERVER['SERVER_PORT'] == 443
);

if (!$is_https) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}



/*
if (str_contains($_SERVER['SERVER_NAME'], $domainNam)) {

    $domainSuffix = explode($domainNam, $_SERVER['SERVER_NAME'])[1];
    $serverName = $domainNam . $domainSuffix;

} else {

    $serverName = $_SERVER['SERVER_NAME'];
}
*/







session_name('__Secure-PHPSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    // 'domain' => $serverName,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict' // Helps mitigate CSRF attacks
]);
session_start();

/*
if(!isset($_SESSION['captcha'])) {
  $_SESSION['captcha'] = 0;
}
*/








# if( !defined( __DIR__ ) ) define( __DIR__, dirname(__FILE__) );

require_once "SYSTEM/cred.php";
require_once "SYSTEM/salt.php";

require_once "SYSTEM/modules/locker.php";

require_once "SYSTEM/modules/mobile-detect.php";
require_once "SYSTEM/modules/post-get-filter.php";
require_once "SYSTEM/api-keys.php";
require_once "SYSTEM/modules/init-libs.php";
require_once "SYSTEM/modules/functions.php";


$last_executed2 = @filemtime("DATABASE/DB/MenuCache.txt");

$diff2 = time() - (int)$last_executed2; // $last_executed is the value from the server

if($last_executed2 && $diff2 > 10799) { // 3 hours

    // rename("DATABASE/DB/MenuCache.txt", "DATABASE/DB/MenuCache.txt.del");

    unlink("DATABASE/DB/MenuCache.txt");
}


require_once "SYSTEM/modules/kore-kontrol.php";
require_once "SYSTEM/modules/post-get-processor.php";
require_once "SYSTEM/modules/css-script-includes.php";
require_once "SYSTEM/modules/addons.php";


$suffixTpl = $printpgvar ?? $sMobile;


require_once "TEMPLATES/".loadTplSess()."/tpl".$suffixTpl.".php";

require_once "SYSTEM/modules/salt.gen.php";






ob_end_flush();
