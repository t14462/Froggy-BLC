<?php
declare(strict_types=1);

// ─── Режим работы ──────────────────────────────────────────────────
// Продолжаем выполнение даже при обрыве соединения клиентом
ignore_user_abort(true);
// На всякий случай уберём лимит времени (долгие загрузки больших файлов)
@set_time_limit(0);

// ─── Утилиты санитизации имени файла ───────────────────────────────

function rusTranslitHelper($st) {
    $st = strtr($st, array(
        'а' => 'a', 'б' => 'b', 'в' => 'v',
        'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'yo', 'ж' => 'zh', 'з' => 'z',
        'и' => 'i', 'й' => 'j', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ь' => '', 'ы' => 'y', 'ъ' => '-',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

        'А' => 'A', 'Б' => 'B', 'В' => 'V',
        'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
        'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z',
        'И' => 'I', 'Й' => 'J', 'К' => 'K',
        'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U',
        'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
        'Ь' => '', 'Ы' => 'Y', 'Ъ' => '-',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
    ));
    return $st;
}

function emojiToHtmlEntities(string $string): string {
    return preg_replace_callback('/\X/u', function ($m) {
        $g = $m[0];
        // Кодовые точки, типичные для эмодзи/вариаций
        if (preg_match('/(?:\p{So}|\p{Sk}|\x{20E3}|\x{FE0F})/u', $g)) {
            // Кодируем весь кластер — затем удалим сущность ниже
            return mb_encode_numericentity($g, [0x0, 0x10FFFF, 0, 0x10FFFF], 'UTF-8');
        }
        return $g;
    }, $string);
}

function remove_entities(string $text): string {
    // 1) именованные (&nbsp;) 2) десятичные (&#160;) 3) шестнадц. (&#xA0;)
    $text = preg_replace('/&[a-z][a-z0-9]+;/i', '', $text);
    $text = preg_replace('/&#\d+;/', '', $text);
    $text = preg_replace('/&#x[0-9a-f]+;/i', '', $text);

    // 4) Только формы без ';' и не продолжаемые буквой/цифрой/подчёркиванием
    $text = preg_replace('/&(?:nbsp|thinsp|ensp|emsp|zwnj|zwj|lrm|rlm)(?!;)\b/i', '', $text);

    // 5) «Голые» & → подчёркивание, чтобы не склеивать слова
    $text = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '_', $text);
    
    return $text;
}

function mb_superTrimLocal(string $text): string {
    // Служебные/невидимые → пробел
    $text = preg_replace('/[\p{C}]/u', ' ', $text);
    // Убрать пробелы по краям
    $text = preg_replace('/^[\p{Z}]+|[\p{Z}]+$/u', '', $text);
    // Сжать внутренние пробелы
    return preg_replace('/[\p{Z}]+/u', ' ', $text);
}

function filter_filename(string $filename): string {
    $filename = emojiToHtmlEntities($filename);
    $filename = remove_entities($filename);
    $filename = mb_superTrimLocal($filename);

    // Запрещённые символы → '-'
    $filename = preg_replace('~
        [<>:"/\\\|?*]     |   # файловые зарезервированные
        [\x00-\x1F]       |   # управляющие
        [\x7F\xA0\xAD]    |   # DEL, NBSP, SHY
        [#\[\]@!$&\'()+,;=]|  # URI reserved
        [{}^\~`]              # URL-небезопасные
    ~x', '-', $filename);

    // Пробелы → подчёркивания
    $filename = str_replace(' ', '_', $filename);

    // Сжать повторы символов
    $filename = preg_replace([
        '/-{2,}/',   // 2+ дефисов → один
        '/\.{2,}/',  // 2+ точек   → одна
        '/_{2,}/',   // 2+ подчёркиваний → одно
    ], ['-', '.', '_'], $filename);

    // Убрать ведущие точки/дефисы
    /// $filename = ltrim($filename, '.-');

    // Финальная зачистка хвостовых точек/пробелов/дефисов (совместимость с Windows)
    $filename = trim($filename, ". \t\r\n\0\x0B-_");

    // Обрезка до 255 байт (UTF-8)
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    if ($ext !== '') {
        $extBytes = strlen($ext); // байты в UTF-8
        $limit = 255 - ($extBytes + 1); // +1 за точку
    } else {
        $limit = 255;
    }

    $limit = max(1, $limit);
    $base  = mb_strcut($base, 0, $limit, 'UTF-8');
    $filename = $ext !== '' ? ($base . '.' . $ext) : $base;


    return $filename;
}

// ─── Пути ───────────────────────────────────────────────────────────

$ROOT        = realpath(__DIR__ . '/../../');
$UPLOAD_DIR  = $ROOT . '/DATABASE/fupload';
$COUNT_DIR   = $ROOT . '/DATABASE/dl.count';
$UPLOAD_REAL = realpath($UPLOAD_DIR);

// ─── Имя файла из GET ───────────────────────────────────────────────

$file = (string)($_GET['file'] ?? '');
$file = str_replace('\\', '/', $file);
$file = basename($file);
$file = filter_filename($file);

if (empty($file)) {
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

// ─── Папка счётчиков ───────────────────────────────────────────────

if (!is_dir($COUNT_DIR)) {
    @mkdir($COUNT_DIR, 0775, true);
}
$cntFile = $COUNT_DIR . '/' . $file . '.dlcnt';

// ─── Атомарный инкремент счётчика ──────────────────────────────────

$fp = @fopen($cntFile, 'c+'); // создаст файл при отсутствии
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

// ─── Заголовки ответа ──────────────────────────────────────────────

// $downloadName = preg_replace('/[\r\n"]+/', '', $file); // защита от инъекций в заголовки

$downloadName = $file; // уже очищено filter_filename()


clearstatcache(true, $absFile);
$size = filesize($absFile);
if ($size === false) {
    http_response_code(500);
    exit('500: stat failed');
}

// ASCII-фолбэк для старых клиентов (транслитерация, если есть ext/intl)
$asciiFallback = $downloadName;

$asciiFallback = rusTranslitHelper($asciiFallback);

if (class_exists('Transliterator')) {
    $tr = \Transliterator::create('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC');
    if ($tr) {
        $asciiFallback = $tr->transliterate($asciiFallback);
    }
}
$asciiFallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $asciiFallback);
if (empty($asciiFallback)) {
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

// Сбрасываем все буферы перед потоковой отдачей
while (@ob_end_clean()) {}

// ─── Потоковая отдача чанками с проверкой обрыва ───────────────────

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
        break; // ошибка чтения
    }
    echo $buf;
    flush();
    if (connection_aborted()) {
        break; // клиент ушёл — прекращаем чтение
    }
}
fclose($fh);
exit;
