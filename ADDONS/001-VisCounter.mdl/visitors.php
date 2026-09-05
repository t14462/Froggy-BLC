<?php

define('SECURE_ACCESS', true);

require_once "../GetRootStandalone.php";


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

    // NUL, TAB, CR, LF, DEL и остальные управляющие символы.
    if(preg_match('/[\x00-\x1F\x7F]/', $uri)) {
        return $fallback;
    }

    // Браузеры могут интерпретировать backslash как обычный slash.
    if(str_contains($uri, '\\')) {
        return $fallback;
    }

    // Ограничение длины в байтах.
    if(strlen($uri) > 8192) {
        return $fallback;
    }

    return $uri;
}


if(function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}


################################################
################################################
################################################


require_once getcwd() . "/SYSTEM/modules/functions.php";

$dbfile = getcwd() . "/DATABASE/VisitorsOnline/visitors.db";
$expire = 300;

if(!is_file($dbfile)) {
    putFileOrDie($dbfile, serialize([]));
}


/**
 * Список известных поисковых роботов.
 *
 * Ключ массива — короткая метка, показываемая
 * после количества посетителей.
 *
 * Значение — фрагменты User-Agent.
 */
function searchBotSignatures(): array
{
    static $bots = [

        /*
         * Google
         */
        'g' => [
            'Googlebot',
            'Google-InspectionTool',
            'Storebot-Google',
        ],

        /*
         * Microsoft Bing
         */
        'bing' => [
            'bingbot',
        ],

        /*
         * Яндекс
         */
        'ya' => [
            'YandexBot',
            'YandexImages',
            'YandexVideo',
            'YandexMedia',
            'YandexMobileBot',
            'YandexAccessibilityBot',
            'YandexRenderResourcesBot',
            'YandexNews',
        ],

        /*
         * DuckDuckGo
         */
        'ddg' => [
            'DuckDuckBot',
            'DuckAssistBot',
        ],

        /*
         * Baidu
         */
        'baidu' => [
            'Baiduspider',
        ],

        /*
         * Yahoo
         */
        'yahoo' => [
            'Slurp',
        ],

        /*
         * Apple Search, Spotlight, Siri
         */
        'apple' => [
            'Applebot/',
        ],

        /*
         * Qwant
         */
        'qwant' => [
            'Qwantbot',
        ],

        /*
         * Mojeek
         */
        'mojeek' => [
            'MojeekBot',
        ],

        /*
         * Seznam
         */
        'seznam' => [
            'SeznamBot',
        ],

        /*
         * Naver
         */
        'naver' => [
            'Yeti/',
            'NaverBot',
        ],

        /*
         * Huawei Petal Search
         */
        'petal' => [
            'PetalBot',
        ],

        /*
         * Yep
         */
        'yep' => [
            'YepBot',
        ],

        /*
         * Sogou
         */
        'sogou' => [
            'Sogou web spider',
            'Sogou inst spider',
            'Sogou spider',
        ],

        /*
         * 360 Search / Haosou
         */
        '360' => [
            '360Spider',
            'HaosouSpider',
        ],

        /*
         * Cốc Cốc
         */
        'coccoc' => [
            'CocCocBot',
        ],

        /*
         * Daum
         */
        'daum' => [
            'Daumoa',
        ],

        /*
         * Поиск ChatGPT
         */
        'chatgpt' => [
            'OAI-SearchBot',
        ],

        /*
         * Perplexity
         */
        'pplx' => [
            'PerplexityBot',
        ],

        /*
         * Поиск Claude
         */
        'claude' => [
            'Claude-SearchBot',
        ],
    ];

    return $bots;
}


/**
 * Определяет поискового робота по User-Agent.
 *
 * Возвращает короткую метку поисковика
 * либо null для обычного посетителя.
 */
function detectSearchBot(string $userAgent): ?string
{
    if($userAgent === '') {
        return null;
    }

    foreach(searchBotSignatures() as $bot => $signatures) {

        foreach($signatures as $signature) {

            if(stripos($userAgent, $signature) !== false) {
                return $bot;
            }
        }
    }

    return null;
}


/**
 * Создаёт идентификатор посетителя.
 *
 * Поисковики получают дополнительный префикс:
 *
 * [BOT:g]
 * [BOT:bing]
 * [BOT:ya]
 * ...
 *
 * Идентификаторы обычных посетителей сохраняют
 * прежний формат.
 */
function getVisitorID(): string
{
    global $userAgent;

    $ip = (string)(
        $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0'
    );

    // Берём первый IP из цепочки.
    $ip = explode(',', $ip)[0];
    $ip = mb_superTrim($ip);

    if(!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }

    $bot = detectSearchBot($userAgent);

    $botPrefix = ($bot === null)
        ? ''
        : '[BOT:' . $bot . ']';

    return $botPrefix
        . $ip
        . ' + '
        . hash('sha512', $userAgent);
}


/**
 * Подсчитывает посетителей и добавляет
 * метки активных поисковых роботов.
 */
function CountVisitors(): string
{
    global $dbfile, $expire;

    $now = time();
    $visitorID = getVisitorID();

    $fVisCnt = fopenOrDie($dbfile, 'rb');

    if(!flock($fVisCnt, LOCK_SH)) {
        fclose($fVisCnt);
        return '0000';
    }

    $data = @unserialize(
        (string)stream_get_contents($fVisCnt),
        ['allowed_classes' => false]
    );

    flock($fVisCnt, LOCK_UN);
    fclose($fVisCnt);

    if(!is_array($data)) {
        $data = [];
    }

    $databaseChanged = false;

    /*
     * Удаляем протухшие или повреждённые записи.
     */
    foreach($data as $id => $time) {

        if(is_int($time)) {
            $visitorTime = $time;

        } elseif(is_string($time) && ctype_digit($time)) {
            $visitorTime = (int)$time;

        } else {
            unset($data[$id]);
            $databaseChanged = true;
            continue;
        }

        if(($visitorTime + $expire) < $now) {
            unset($data[$id]);
            $databaseChanged = true;
        }
    }

    /*
     * Добавляем текущего посетителя,
     * если его ещё нет в базе.
     */
    if(!isset($data[$visitorID])) {
        $data[$visitorID] = $now;
        $databaseChanged = true;
    }

    /*
     * Записываем изменения:
     *
     * - нового посетителя;
     * - удаление протухших записей;
     * - удаление повреждённых записей.
     */
    if($databaseChanged) {
        putFileOrDie(
            $dbfile,
            serialize($data),
            LOCK_EX
        );
    }

    /*
     * Собираем уникальные метки поисковиков,
     * находящихся онлайн.
     */
    $onlineBots = [];
    $knownBots = searchBotSignatures();

    foreach(array_keys($data) as $id) {

        if(!is_string($id)) {
            continue;
        }

        if(!preg_match(
            '/^\[BOT:([a-z0-9_-]{1,20})\]/',
            $id,
            $matches
        )) {
            continue;
        }

        $bot = $matches[1];

        // Не показываем неизвестные или повреждённые метки.
        if(isset($knownBots[$bot])) {
            $onlineBots[$bot] = true;
        }
    }

    $result = str_pad(
        (string)count($data),
        4,
        '0',
        STR_PAD_LEFT
    );

    /*
     * Порядок вывода соответствует порядку
     * поисковиков в searchBotSignatures().
     */
    foreach(array_keys($knownBots) as $bot) {

        if(isset($onlineBots[$bot])) {
            $result .= ' +' . $bot;
        }
    }

    return $result;
}


/*
 * Получаем User-Agent текущего посетителя.
 */
$userAgent = (string)(
    $_SERVER['HTTP_USER_AGENT']
    ?? 'Unknown'
);

// Ограничиваем User-Agent 512 байтами.
$userAgent = substr($userAgent, 0, 512);

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
