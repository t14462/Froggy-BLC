<?php
declare(strict_types=1);


if(function_exists('ignore_user_abort')) {
    ignore_user_abort(true); // Установить игнорирование разрыва соединения
}


function emojiToHtmlEntities(string $string): string {
    return preg_replace_callback('/\X/u', static function ($m) {
        $g = $m[0];
        if (preg_match('/(?:\p{So}|\p{Sk}|\x{20E3}|\x{FE0F})/u', $g)) {
            return mb_encode_numericentity($g, [0x0, 0x10FFFF, 0, 0x10FFFF], 'UTF-8');
        }
        return $g;
    }, $string);
}

function remove_entities(string $text): string {
    $text = preg_replace('/&[a-z][a-z0-9]*;/i', '', $text);
    $text = preg_replace('/&#\d+;/', '', $text);
    $text = preg_replace('/&#x[0-9a-f]+;/i', '', $text);
    $text = preg_replace('/&(?:nbsp|thinsp|ensp|emsp|zwnj|zwj|lrm|rlm)(?!;)\b/i', '', $text);
    $text = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '_', $text);
    return $text;
}

function mb_superTrimLocal(string $text): string {
    $text = preg_replace('/[\p{C}]/u', ' ', $text);
    $text = preg_replace('/^[\p{Z}]+|[\p{Z}]+$/u', '', $text);
    return preg_replace('/[\p{Z}]+/u', ' ', $text);
}

function filter_filename(string $filename): string {

    $filename = basename(str_replace('\\', '/', $filename));
    
    $filename = emojiToHtmlEntities($filename);
    $filename = remove_entities($filename);
    $filename = mb_superTrimLocal($filename);

    $filename = preg_replace('~
        [<>:"/\\\\|?*]      |  # reserved
        [\x00-\x1F]         |  # control
        [\x7F\xA0\xAD]      |  # DEL, NBSP, SHY
        [#\[\]@!$&\'()+,;=] |  # URI reserved
        [{}^\~`]               # URL-unsafe
    ~x', '-', $filename);

    $filename = str_replace(' ', '_', $filename);
    $filename = preg_replace(['/-{2,}/', '/\.{2,}/', '/_{2,}/'], ['-', '.', '_'], $filename);
    $filename = trim($filename, ". \t\r\n\0\x0B-_");

    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    $limit = ($ext !== '') ? max(1, 255 - (strlen($ext) + 1)) : 255;
    $base  = mb_strcut($base, 0, $limit, 'UTF-8');

    return ($ext !== '') ? ($base . '.' . $ext) : $base;
}

// ─── Пути ───────────────────────────────────────────────────────────
$ROOT       = realpath(__DIR__ . '/../../');
$UPLOAD_DIR = $ROOT . '/DATABASE/fupload';
$COUNT_DIR  = $ROOT . '/DATABASE/dl.count';

$UPLOAD_REAL = realpath($UPLOAD_DIR);
$PUBLIC_BASE_URL = '../../DATABASE/fupload'; // ведущий / обязателен

// ─── Имя файла ─────────────────────────────────────────────────────
$file = (string)($_GET['file'] ?? '');
$file = str_replace('\\', '/', $file);
$file = basename($file);
$file = filter_filename($file);

if ($file === '') {
    http_response_code(400);
    exit('Bad request: no file');
}

// ─── Проверка файла ────────────────────────────────────────────────
$absFile = realpath($UPLOAD_DIR . '/' . $file);
if (
    !$absFile ||
    !$UPLOAD_REAL ||
    strpos($absFile, $UPLOAD_REAL . DIRECTORY_SEPARATOR) !== 0 ||
    is_link($absFile) ||
    !is_file($absFile) ||
    !is_readable($absFile)
) {
    http_response_code(404);
    exit('404: file not found');
}

// ─── Счётчик: считаем факт запроса ─────────────────────────────────
if (!is_dir($COUNT_DIR)) {
    @mkdir($COUNT_DIR, 0775, true);
}
$cntFile = $COUNT_DIR . '/' . $file . '.dlcnt';

$fp = @fopen($cntFile, 'c+b');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        $val = 0;
        rewind($fp);
        $data = trim((string)stream_get_contents($fp));
        if ($data !== '' && ctype_digit($data)) $val = (int)$data;
        $val++;

        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, (string)$val);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// ─── Редирект ──────────────────────────────────────────────────────
$base = rtrim($PUBLIC_BASE_URL, '/');
$redirUrl = $base . '/' . rawurlencode($file);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . $redirUrl, true, 302);
exit;
