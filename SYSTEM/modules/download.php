<?php
declare(strict_types=1);

define('SECURE_ACCESS', true);
require_once __DIR__ . '/download-files.php';

ignore_user_abort(true);

$requested = $_GET['file'] ?? null;
if(!is_string($requested) || strlen($requested) > 8192 || !mb_check_encoding($requested, 'UTF-8')
    || preg_match('/[\x00-\x1F\x7F]/', $requested) !== 0) {
    http_response_code(400);
    exit('Bad request: bad file parameter');
}

// Старые ссылки могли передавать путь из шаблона {{download|...}}.
$name = preg_replace('~\A(?:\.?/)?DATABASE/fupload/~i', '', str_replace('\\', '/', $requested), 1);
if($name === null || str_contains($name, '/')) {
    http_response_code(400);
    exit('Bad request: bad file parameter');
}

$names = array_unique(array_filter([$name, filter_filename($name, failOnInvalid: false, trim: 'mb_superTrimLocal')], 'isDownloadFilename'));
if($names === []) {
    http_response_code(400);
    exit('Bad request: bad file parameter');
}

$root = dirname(__DIR__, 2);
$uploadDir = realpath($root . '/DATABASE/fupload');
if($uploadDir === false) {
    http_response_code(404);
    exit('404: file not found');
}

$file = null;
foreach($names as $candidate) {
    $path = $uploadDir . '/' . $candidate;
    $realPath = realpath($path);
    if($realPath !== false && dirname($realPath) === $uploadDir
        && !is_link($path) && is_file($path) && is_readable($path)) {
        $file = $candidate;
        break;
    }
}
if($file === null) {
    http_response_code(404);
    exit('404: file not found');
}

if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // Недоступная статистика не мешает скачать файл.
    $countDir = $root . '/DATABASE/dl.count';
    if(!is_dir($countDir)) {
        @mkdir($countDir, 0775, true);
    }

    $countPath = $countDir . '/' . downloadCounterName($file);
    if(!is_link($countPath)) {
        atomicCounterIncrement($countPath, logFailure: false);
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ../../DATABASE/fupload/' . rawurlencode($file), true, 302);
