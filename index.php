<?php

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$server_ip = gethostbyname($serverName . ".");

if(!in_array($server_ip, ['127.0.0.1'], true)) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    // error_reporting(0);

    ini_set('log_errors', '1');
    // ini_set('error_log', '/var/log/php_errors.log'); // укажи актуальный путь
}



# DO NOT DELETE!
$cred['dummy'] = "0<!!!>6f2d8413fc885ef73fcd4166ee768d8664b74e539701b1a7033493517543abf625cc807bbe6dfea3a818785fece241ac3d0d168bd03c570bb6acffd56f66ae7c";
$mySalt = 0;



function safeRequestUri(?string $uri): string
{
    $fallback = '/';

    if($uri === null || $uri === '') {
        return $fallback;
    }

    if(!mb_check_encoding($uri, 'UTF-8')) {
        return $fallback;
    }

    // Разрешаем:
    // /page
    // /?page
    // ?page
    if($uri[0] !== '/' && $uri[0] !== '?') {
        return $fallback;
    }

    // Запрещаем protocol-relative URL: //evil.example/path
    if(
        $uri[0] === '/' &&
        isset($uri[1]) &&
        $uri[1] === '/'
    ) {
        return $fallback;
    }

    // NUL, TAB, CR, LF, DEL и остальные управляющие символы
    if(preg_match('/[\x00-\x1F\x7F]/', $uri)) {
        return $fallback;
    }

    // Браузеры могут интерпретировать обратный слэш в пути как обычный.
    $path = substr($uri, 0, strcspn($uri, '?#'));
    if(str_contains($path, '\\')) {
        return $fallback;
    }

    // Ограничение длины в байтах
    if(strlen($uri) > 8192) {
        return $fallback;
    }

    return $uri;
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

$waitFile = "";

// $errmsg .= $server_ip;

mb_internal_encoding("UTF-8");

ob_start();



$is_https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);

if(!$is_https) {
    $httpHost = $_SERVER['HTTP_HOST'] ?? $serverName;
    $requestUri = safeRequestUri($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $httpHost . $requestUri, true, 301);
    exit;
}

session_name('__Secure-PHPSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    // 'domain' => $serverName,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict' // Helps mitigate CSRF attacks
]);
if(!@session_start(['use_strict_mode' => 1])) {
    http_response_code(503);
    exit('Session unavailable');
}

header('Cache-Control: no-store');

$csrf = $_SESSION['csrf'] ?? random_int(0, 0xFFFFFFFFFFFFFFF);


# if( !defined( __DIR__ ) ) define( __DIR__, dirname(__FILE__) );

require_once "SYSTEM/modules/file-state.php";
$credentialsPath = __DIR__ . '/SYSTEM/cred.php';
$credentialsVersion = false;
$credentialsLock = isset($_GET['editusers'])
    ? lockStateFile('DATABASE/lock/credentials.lock', LOCK_SH)
    : false;
try {
    if($credentialsLock !== false) {
        $credentialsVersion = @hash_file('sha256', $credentialsPath);
    }
    // Пустой фильтр читает cred.php с диска даже при включённом файловом OPcache.
    require_once 'php://filter/read=/resource=' . $credentialsPath;
} finally {
    if($credentialsLock !== false) {
        fclose($credentialsLock);
    }
}
require_once "SYSTEM/salt.php";

require_once "SYSTEM/modules/mobile-detect.php";
require_once "SYSTEM/modules/post-get-filter.php";
require_once "SYSTEM/api-keys.php";
require_once "SYSTEM/modules/init-libs.php";
require_once "SYSTEM/modules/functions.php";
require_once "SYSTEM/modules/locker.php";

$navigationCacheLock = lockNavigationCaches();
try {
    $chTimeDB = filemtimeMy("DATABASE/DB/data.html");
    require_once "SYSTEM/modules/kore-kontrol.php";
} finally {
    if(is_resource($navigationCacheLock)) {
        fclose($navigationCacheLock);
    }
}
require_once "SYSTEM/modules/post-get-processor.php";
require_once "SYSTEM/modules/css-script-includes.php";
require_once "SYSTEM/modules/addons.php";

$suffixTpl = $printpgvar ?? $sMobile;

require_once "TEMPLATES/".loadTplSess()."/tpl".$suffixTpl.".php";

require_once "SYSTEM/modules/salt.gen.php";

ob_end_flush();
