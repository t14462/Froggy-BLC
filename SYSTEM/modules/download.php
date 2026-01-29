<?php
declare(strict_types=1);

// ─── Режим работы ──────────────────────────────────────────────────
ignore_user_abort(true);
@set_time_limit(0);

$conAborted = false;

// ─── Утилиты санитизации имени файла ───────────────────────────────

function rusTranslitHelper(string $st): string {
    return strtr($st, [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ь'=>'','ы'=>'y','ъ'=>'-','э'=>'e','ю'=>'yu','я'=>'ya',

        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh','З'=>'Z',
        'И'=>'I','Й'=>'J','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R',
        'С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch',
        'Ь'=>'','Ы'=>'Y','Ъ'=>'-','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
    ]);
}

function emojiToHtmlEntities(string $string): string {
    return preg_replace_callback('/\X/u', function ($m) {
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
    $filename = emojiToHtmlEntities($filename);
    $filename = remove_entities($filename);
    $filename = mb_superTrimLocal($filename);

    $filename = preg_replace('~
        [<>:"/\\\|?*]     |   # reserved
        [\x00-\x1F]       |   # control
        [\x7F\xA0\xAD]    |   # DEL, NBSP, SHY
        [#\[\]@!$&\'()+,;=]|  # URI reserved
        [{}^\~`]              # URL-unsafe
    ~x', '-', $filename);

    $filename = str_replace(' ', '_', $filename);

    $filename = preg_replace(
        ['/-{2,}/', '/\.{2,}/', '/_{2,}/'],
        ['-', '.', '_'],
        $filename
    );

    $filename = trim($filename, ". \t\r\n\0\x0B-_");

    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    if ($ext !== '') {
        $extBytes = strlen($ext);
        $limit = 255 - ($extBytes + 1);
    } else {
        $limit = 255;
    }

    $limit = max(1, $limit);
    $base  = mb_strcut($base, 0, $limit, 'UTF-8');
    $filename = $ext !== '' ? ($base . '.' . $ext) : $base;

    return $filename;
}

// ─── Debouncer (анти-двойной засчёт) ───────────────────────────────

function is_prefetch_like_request(): bool {
    $p1 = strtolower((string)($_SERVER['HTTP_PURPOSE'] ?? ''));
    $p2 = strtolower((string)($_SERVER['HTTP_SEC_PURPOSE'] ?? ''));
    $p  = $p1 . ' ' . $p2;
    return (strpos($p, 'prefetch') !== false) || (strpos($p, 'preview') !== false);
}

/**
 * true = можно засчитывать (не было этого же клиента недавно)
 * false = не засчитываем (дубль в пределах TTL или похоже на prefetch)
 */
function debounce_allow(string $file, string $debounceDir, int $ttlSeconds): bool {
    if ($ttlSeconds <= 0) return true;
    if (is_prefetch_like_request()) return false;

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $al = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

    $sig = hash('sha256', $file . "\n" . $ip . "\n" . $ua . "\n" . $al);

    $sub = $debounceDir . '/' . substr($sig, 0, 2);
    if (!is_dir($sub)) {
        @mkdir($sub, 0775, true);
    }

    $path = $sub . '/' . $sig . '.ts';
    $now  = time();

    $fp = @fopen($path, 'c+');
    if (!$fp) return true; // fail-open

    $allow = true;

    if (flock($fp, LOCK_EX)) {
        $prev = trim((string)stream_get_contents($fp));
        $prevTs = (ctype_digit($prev) ? (int)$prev : 0);

        if ($prevTs > 0 && ($now - $prevTs) < $ttlSeconds) {
            $allow = false;
        } else {
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, (string)$now);
            fflush($fp);
        }

        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $allow;
}

function debounce_gc(string $debounceDir, int $maxAgeSeconds): void {
    if ($maxAgeSeconds <= 0) return;
    if (!is_dir($debounceDir)) return;

    $now = time();
    foreach (glob($debounceDir . '/*/*.ts', GLOB_NOSORT) ?: [] as $f) {
        $mt = @filemtime($f);
        if ($mt !== false && ($now - $mt) > $maxAgeSeconds) {
            @unlink($f);
        }
    }
}

// ─── Пути ───────────────────────────────────────────────────────────

$ROOT        = realpath(__DIR__ . '/../../');
$UPLOAD_DIR  = $ROOT . '/DATABASE/fupload';
$COUNT_DIR   = $ROOT . '/DATABASE/dl.count';
$DEBOUNCE_DIR = $ROOT . '/DATABASE/dl.debounce';
$DEBOUNCE_TTL = 45; // секунды: в этом окне повторный засчёт не делаем

$UPLOAD_REAL = realpath($UPLOAD_DIR);

// ─── Имя файла из GET ───────────────────────────────────────────────

$file = (string)($_GET['file'] ?? '');
$file = str_replace('\\', '/', $file);
$file = basename($file);
$file = filter_filename($file);

if ($file === '') {
    http_response_code(400);
    exit('Bad request: no file');
}

// ─── Проверка пути/доступа ─────────────────────────────────────────

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

// ─── Папки счётчиков / debounce ────────────────────────────────────

if (!is_dir($COUNT_DIR)) {
    @mkdir($COUNT_DIR, 0775, true);
}
if (!is_dir($DEBOUNCE_DIR)) {
    @mkdir($DEBOUNCE_DIR, 0775, true);
}
$cntFile = $COUNT_DIR . '/' . $file . '.dlcnt';

// ─── Заголовки ответа ──────────────────────────────────────────────

$downloadName = $file;

clearstatcache(true, $absFile);
$size = filesize($absFile);
if ($size === false) {
    http_response_code(500);
    exit('500: stat failed');
}

$asciiFallback = rusTranslitHelper($downloadName);

if (class_exists('Transliterator')) {
    $tr = \Transliterator::create('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC');
    if ($tr) {
        $asciiFallback = $tr->transliterate($asciiFallback);
    }
}
$asciiFallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $asciiFallback);
if ($asciiFallback === '' || $asciiFallback === null) {
    $asciiFallback = 'download';
}

header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: none');
header('Content-Length: ' . $size);
header(
    'Content-Disposition: attachment; ' .
    'filename="' . $asciiFallback . '"; ' .
    'filename*=UTF-8\'\'' . rawurlencode($downloadName)
);

// Сбрасываем буферы перед потоковой отдачей
while (@ob_end_clean()) {}

// ─── Потоковая отдача ──────────────────────────────────────────────

$fh = @fopen($absFile, 'rb');
if (!$fh) {
    error_log('download.php: cannot open for reading: ' . $absFile);
    http_response_code(500);
    exit('500: cannot open file');
}

$chunk = 32768;
while (!feof($fh)) {
    $buf = fread($fh, $chunk);
    if ($buf === false) {
        $conAborted = true;
        break;
    }
    echo $buf;
    flush();

    if (connection_aborted()) {
        $conAborted = true;
        break;
    }
}
fclose($fh);

// ─── Засчёт (только если реально докачали) ──────────────────────────

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$range  = $_SERVER['HTTP_RANGE'] ?? '';

if (
    !$conAborted &&
    strcasecmp($method, 'GET') === 0 &&
    $range === '' &&
    debounce_allow($file, $DEBOUNCE_DIR, $DEBOUNCE_TTL)
) {
    // редкая уборка debounce (чтобы не разрасталось)
    if (mt_rand(1, 300) === 1) {
        debounce_gc($DEBOUNCE_DIR, 86400); // старше 1 суток
    }

    // ─── Атомарный инкремент счётчика ───────────────────────────────
    $fp = @fopen($cntFile, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $val  = 0;
            $data = stream_get_contents($fp);
            $data = trim((string)$data);
            if ($data !== '' && ctype_digit($data)) {
                $val = (int)$data;
            }
            $val++;

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, (string)$val);
            fflush($fp);

            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

exit;
