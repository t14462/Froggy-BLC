<?php

#phpinfo();

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################


if($mainPageTitle) {
    $mainPageTitle = $mainPageTitle." — ";
}




$head .= '
<meta charset="UTF-8" />

<!-- Настройка мобильной адаптивности -->
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>'.$mainPageTitle.'Froggy-BLC</title>

<meta name="description" content="Авторский сайт с гайдами по программированию, скриптингу и моддингу. Туториалы по моддингу, скриптам и фиксам для популярных игр, включая Fallout New Vegas и Soul of Fallen Worlds (SFW). Ресурс о Linux, модах, а также CMS книжной системы Book-Like CMS. Здесь вы найдете полезную информацию, юмор и советы по созданию модов и скриптов. Также на сайте представлены мои авторские работы — готовые решения, скрипты и утилиты для различных задач, а также проекты, связанные с настройкой и оптимизацией систем. '
.$metaDescription.'" />
<meta name="keywords" content="Авторский сайт, программирование, скриптинг, моддинг, модификации для игр, скриптинг для Linux, кодинг, PHP, Bash, xNVSE, готовые скрипты, утилиты, патчи, фиксы, Fallout New Vegas, Soul of Fallen Worlds, SFW, инструкции, How-To, гайды, туториалы, создание модов, оптимизация систем, настройка Linux, CMS, Book-Like CMS, моды для игр, советы по программированию, скрипты для игр, решения для программирования, модификации для старых игр, программирование на скриптовых языках, открытые решения, создание скриптов, '
.$metaDescription.'" />';

if($ispageexist) {

    $head .= "\n".'<link rel="canonical" href="'.$sitemaptxt[$pageMenuNum-1].'" />'."\n";

} else {

    $head .= "\n".'<meta name="robots" content="noindex, nofollow" />'."\n";
}




// $jstime1 = filemtime("SYSTEM/JSLIB/main-script.js");




$head .= '
<link href="'.skipCache("favicon.ico").'" rel="icon" type="image/x-icon" />

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
<header aria-label='Froggy-BLC'><strong><a href='".$url."' title='Book-Like CMS'>Froggy-BLC</a></strong><em>Книжная Система Сайта</em></header>";



$stoolbox = "<div id='system-links'><a target='_blank' href='?".explode("&", $_SERVER['QUERY_STRING'])[0]."&amp;print=1' rel='nofollow' title='Версия для печати'>🖨️</a> <a target='_blank' href='?gallery=-1' title='Галерея'>🖼️</a> <a target='_blank' href='?dlfiles=-1' title='Файлы'>💾</a> ".logInOutLink('🔐', '🔚')." <a href='?".explode("&", $_SERVER['QUERY_STRING'])[0]."&amp;edit=1' rel='nofollow' title='Редактировать эту страницу' style='margin-left: .75em;'>✏️</a> <a target='_blank' href='?log=-1' rel='nofollow' title='Бортовой Журнал'>📄</a></div><hr />";


$menubar = '

<div id="menu-search-wrap">
  <input id="menuSearch" type="search" placeholder="🔎 Поиск по меню…" autocomplete="off" />
  <ol id="menuSearchResults"></ol>
</div>
'.$menubar;


$footer = "\n<em>Автор: Тимофеев Святослав aka Paulter Gates.<br />
Система разработана при участии ChatGPT от OpenAI.<br />
Сайт создан при поддержке <strong>НИИ БАЦА</strong>.</em>
".genTplForm();
