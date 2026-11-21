<?php

#phpinfo();

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

if($mainPageTitle) {
    $mainPageTitle .= " — ";
}

$pageTitleHtml = htmlspecialchars($mainPageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
$metaDescriptionHtml = htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
$siteUrlHtml = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$head .= '
<meta charset="UTF-8" />

<!-- Настройка мобильной адаптивности -->
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>'.$pageTitleHtml.'Froggy-BLC</title>

<meta name="description" content="Авторский сайт с гайдами по программированию, скриптингу и моддингу. Туториалы по моддингу, скриптам и фиксам для популярных игр, включая Fallout New Vegas и Soul of Fallen Worlds (SFW). Ресурс о Linux, модах, а также CMS книжной системы Book-Like CMS. Здесь вы найдете полезную информацию, юмор и советы по созданию модов и скриптов. Также на сайте представлены мои авторские работы — готовые решения, скрипты и утилиты для различных задач, а также проекты, связанные с настройкой и оптимизацией систем. '
.$metaDescriptionHtml.'" />
<meta name="keywords" content="Авторский сайт, программирование, скриптинг, моддинг, модификации для игр, скриптинг для Linux, кодинг, PHP, Bash, xNVSE, готовые скрипты, утилиты, патчи, фиксы, Fallout New Vegas, Soul of Fallen Worlds, SFW, инструкции, How-To, гайды, туториалы, создание модов, оптимизация систем, настройка Linux, CMS, Book-Like CMS, моды для игр, советы по программированию, скрипты для игр, решения для программирования, модификации для старых игр, программирование на скриптовых языках, открытые решения, создание скриптов, '
.$metaDescriptionHtml.'" />';

if($ispageexist && isset($sitemaptxt[$pageMenuNum - 1])) {

    $canonical = htmlspecialchars($sitemaptxt[$pageMenuNum - 1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $head .= "\n".'<link rel="canonical" href="'.$canonical.'" />'."\n";

} else {

    $head .= "\n".'<meta name="robots" content="noindex, nofollow" />'."\n";
}

// $jstime1 = filemtime("SYSTEM/JSLIB/main-script.js");

$head .= '
<link rel="icon" type="image/x-icon" sizes="16x16 32x32 48x48" href="'.skipCache("favicon.ico").'" />
<link rel="icon" type="image/png" sizes="32x32" href="'.skipCache("favicon-32.png").'" />
<link rel="apple-touch-icon" sizes="180x180" href="'.skipCache("apple-touch-icon.png").'" />

<script src="'.skipCache("SYSTEM/JSLIB/main-script.js").'"></script>

<link rel="stylesheet" href="'.skipCache("SYSTEM/JSLIB/ckeditor/plugins/codesnippet/lib/highlight/styles/default.min.css").'" />

<script src="'.skipCache("SYSTEM/JSLIB/ckeditor/plugins/codesnippet/lib/highlight/highlight.min.js").'"></script>

<script>hljs.highlightAll();</script>
'.prevnextpage();

if(!empty($ProjIdRevisionMe)) {
    $body .= "
    <script>
    var __rm__config = {
    projectId: '$ProjIdRevisionMe',
    locale: 'ru',
    contextWidget: 1,
    embedBtn: 0,
    floatingBtn: 0,
    };
    </script>
    <script src=\"https://widget.revisionme.com/app.js\" defer=\"defer\" id=\"rm_app_script\"></script>";
}

$body .= "
<header aria-label='Froggy-BLC'><strong><a href='".$siteUrlHtml."' title='Book-Like CMS'>Froggy-BLC</a></strong><em>Книжная Система Сайта</em></header>";

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$queryBase = htmlspecialchars(explode("&", $queryString)[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$stoolbox = "<div id='system-links'><a target='_blank' href='?".$queryBase."&amp;print=1' rel='nofollow' title='Версия для печати'>🖨️</a> <a target='_blank' href='?gallery=-1' title='Галерея'>🖼️</a> <a target='_blank' href='?dlfiles=-1' title='Файлы'>💾</a> ".logInOutLink('🔐', '🔚')." <a href='?".$queryBase."&amp;edit=1&amp;csrf=$csrf' rel='nofollow' title='Редактировать эту страницу' style='margin-left: .75em;'>✏️</a> <a target='_blank' href='?log=-1&amp;csrf=$csrf' rel='nofollow' title='Бортовой Журнал'>📄</a></div>".obyava();

$menubar = '

<div id="menu-search-wrap">
  <input id="menuSearch" type="search" placeholder="🔎 Поиск по меню…" autocomplete="off" /><button
    type="button"
    aria-label="Очистить поиск"
    onclick="
        const input = document.getElementById(\'menuSearch\');

        if(input) {
            input.value = \'\';
            input.dispatchEvent(
                new Event(\'input\', {bubbles: true})
            );
        }
    ">X</button><ol id="menuSearchResults"></ol></div>
'.$menubar;

$footer = "\n<em>Автор: Тимофеев Святослав aka Paulter Gates, PhD.<br />
Система разработана при участии ChatGPT от OpenAI.<br />
Сайт создан при поддержке <strong>НИИ БАЦА</strong>.<br />
Web-Дизайнер: Артемев Лебедий.</em>
".genTplForm()
.' <a href="https://hostiq.ua/" target="_blank"><img src="https://hostiq.ua/images2019/design/logos/logo.svg" alt="HOSTiQ&nbsp;— найкращий хостинг в&nbsp;Україні" /></a>';

if($checkpermission) {

    $errmsg .= "<aside class='big infobox' style='margin-top: 0 !important'><strong>
    <u>Никому</u> не сообщайте свой <em>секретный CSRF-токен!</em><br />
    <u>Не публикуйте адрес страницы</u>, находясь в <em>Админ-панели!</em><br />
    Не делайте в Админке скриншоты <u>с видимой адресной строкой!</u>
    </strong></aside>";

    if(catTailAvailable()) {
        $footer .= "<span title='Exec + Utils: ЕСТЬ.'>🟢</span>";
    } else {
        $footer .= "<span title='Exec + Utils: НЕТУ.'>🔴</span>";
    }

    if(isDbLocked()) {

        $owner = htmlspecialchars((string)readDBLock(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        $footer .= "<strong title='Блокирующий Пользователь'>BLOCKER:<br />".$owner."</strong>";
    }
}
