<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################



function relPath($cdir) { 
    // Получаем SCRIPT_NAME и базовую поддиректорию (если она есть)
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $baseUrl = dirname($scriptName);
    $baseUrl = ($baseUrl === '/' || $baseUrl === '\\') ? '' : rtrim($baseUrl, '/');

    // Получаем путь к текущей директории модуля
    $currentDir = realpath($cdir);

    // Получаем DOCUMENT_ROOT
    $docRoot = rtrim(realpath($_SERVER['DOCUMENT_ROOT']), DIRECTORY_SEPARATOR);

    // Получаем путь относительно DOCUMENT_ROOT
    if (strpos($currentDir, $docRoot) === 0) {
        $relativePath = substr($currentDir, strlen($docRoot));
    } else {
        $relativePath = '';
    }

    // Приводим к URL-формату и добавляем конечный слэш
    $relativePath = '/' . trim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/') . '/';

    // Убираем baseUrl, если он есть в начале
    if ($baseUrl !== '' && strpos($relativePath, $baseUrl) === 0) {
        $relativePath = substr($relativePath, strlen($baseUrl));
        $relativePath = ltrim($relativePath, '/');
    }

    return ltrim($relativePath, "/");
}
