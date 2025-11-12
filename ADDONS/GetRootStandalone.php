<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################



function rtrim_word(string $str, string $word): string {
    // Если слово не пустое и строка заканчивается этим словом
    if ($word !== '' && substr($str, -strlen($word)) === $word) {
        return substr($str, 0, -strlen($word));
    }
    return $str;
}








$docRoot = realpath($_SERVER['DOCUMENT_ROOT']); // например: /var/www/html

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']); // например: /my/ADDONS/test.php
$baseDir = trim(dirname($scriptName), '/'); // например: "my/ADDONS"

$parts = explode('/', $baseDir);

// Возьмём только первый сегмент (имя подкаталога сайта) или пустую строку
$siteSubdir = $parts[0] ?? '';

// Соберём путь к корню сайта с учётом подкаталога
$siteRoot = $docRoot . ($siteSubdir ? DIRECTORY_SEPARATOR . $siteSubdir : '');

$siteRoot = rtrim_word($siteRoot, "/ADDONS");

// Установим рабочую директорию в корень сайта
chdir($siteRoot);
