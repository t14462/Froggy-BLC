<?php

define('SECURE_ACCESS', true);

require_once "../GetRootStandalone.php";


################################################
################################################
################################################



// exit( getcwd() );

// require_once getcwd() . "/SYSTEM/salt.php";

require_once getcwd() . "/SYSTEM/modules/functions.php";

$dbfile = getcwd() . "/DATABASE/VisitorsOnline/visitors.db";
$expire = 300;

if (!is_file($dbfile)) {
    putFileOrDie($dbfile, serialize([])); // создаём пустой
}

if (!is_writable($dbfile)) {
    die("Error: Data file $dbfile is NOT writable. CHMOD 666.");
}

function getVisitorID() {
    global $userAgent;
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip = explode(',', $ip)[0]; // Берём первый IP из цепочки
    $ip = mb_superTrim($ip);
    return (filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0') . ' + ' . $userAgent;
}

function CountVisitors() {
    global $dbfile, $expire;

    $now = time();
    $visitorID = getVisitorID();

    $data = @unserialize(getFileOrDie($dbfile));
    if (!is_array($data)) $data = [];

    // Чистим протухших
    foreach ($data as $id => $time) {
        if (($time + $expire) < $now) {
            unset($data[$id]);
        }
    }

    // ksort($data, SORT_STRING);

    // Добавляем нового, если его ещё нет
    if (!isset($data[$visitorID])) {
        $data[$visitorID] = $now;

        /*
        dbprepCache($dbfile) or die('<!DOCTYPE html><html><head><meta http-equiv="refresh" content="1" /></head><body style="background: transparent;">&nbsp;</body></html>
        ');

        putFileOrDie($dbfile.".new", serialize($data));

        dbdone($dbfile);
        */

        file_put_contents($dbfile, serialize($data), LOCK_EX) or die('<!DOCTYPE html><html><head><meta http-equiv="refresh" content="1" /></head><body style="background: transparent;">&nbsp;</body></html>
        ');

    }

    return str_pad(count($data), 4, '0', STR_PAD_LEFT);
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$visitors_online = CountVisitors();
?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="refresh" content="12" />
    <title>Visitors</title>
    <style>
        body {
            background: transparent;
            font-family: monospace;
            text-align: center;
            font-size: 300%;
            color: Green;
        }
    </style>
</head>
<body title="Онлайн"><?=$visitors_online;?></body>
</html>
