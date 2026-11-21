<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

require_once __DIR__ . '/file-state.php';

function downloadFileExtensions(): array {
    return [
        'txt', 'csv', 'tsv', 'json', 'xml', 'md', 'log', 'ini', 'yaml', 'yml',
        'conf', 'cfg', 'toml', 'properties', 'rst', 'adoc', 'org', 'diff', 'patch', 'nfo', 'tex', 'bib',
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'ods', 'odp', 'rtf',
        'epub', 'mobi', 'azw', 'azw3',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'lz', 'lzma', 'iso',
        'esp', 'esm', 'srt', 'vtt', 'ass', 'ssa', 'sub',
        'ttf', 'otf', 'woff', 'woff2', 'sql', 'sqlite', 'db', 'db3',
    ];
}

function isStorageFilename(string $file): bool {
    return $file !== '' && $file[0] !== '.' && strlen($file) <= 255
        && mb_check_encoding($file, 'UTF-8')
        && !str_contains($file, '/') && !str_contains($file, '\\')
        && preg_match('/[\x00-\x1F\x7F]/', $file) === 0;
}

function isDownloadFilename(string $file): bool {
    return isStorageFilename($file)
        && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), downloadFileExtensions(), true);
}

/** Вызывается под блокировкой каталога после проверки имени назначения. */
function storeUploadedFile(string $source, string $destination): bool {
    $directory = realpath(dirname($destination));
    if($directory === false) {
        return false;
    }

    $temporary = @tempnam($directory, '.upload-');
    if($temporary === false) {
        return false;
    }

    try {
        return dirname($temporary) === $directory
            && @move_uploaded_file($source, $temporary)
            && @chmod($temporary, 0666 & ~umask())
            && @rename($temporary, $destination);
    } finally {
        if(is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

function deleteStoredFile(string $file, string $lockPath): bool {
    $lock = lockStateFile($lockPath, LOCK_EX, 'c');
    if($lock === false) {
        return false;
    }

    try {
        clearstatcache(true, $file);
        return (is_file($file) || is_link($file)) && @unlink($file);
    } finally {
        fclose($lock);
    }
}

function downloadCounterName(string $file): string {
    $name = $file . '.dlcnt';
    return strlen($name) <= 255 ? $name : '.sha256-' . hash('sha256', $file) . '.dlcnt';
}

function readDownloadCount(string $file): int {
    $name = downloadCounterName($file);
    $names = [$name];
    if($name !== $file . '.dlcnt') {
        // Старый ключ мог быть обрезан до 255 байт. Его история остаётся доступна.
        $legacy = filter_filename($file . '.dlcnt', failOnInvalid: false, trim: 'mb_superTrimLocal');
        $legacyOwner = substr($legacy, 0, -strlen('.dlcnt'));
        $ownerPath = 'DATABASE/fupload/' . $legacyOwner;
        $hasOwner = isDownloadFilename($legacyOwner) && is_file($ownerPath) && !is_link($ownerPath);
        if($legacy !== '' && !$hasOwner) {
            $names[] = $legacy;
        }
    }

    $count = 0;
    foreach($names as $name) {
        $path = 'DATABASE/dl.count/' . $name;
        $counter = is_file($path) && !is_link($path) ? lockStateFile($path, LOCK_SH, 'rb') : false;
        if($counter === false) {
            continue;
        }

        try {
            $value = trim((string)@stream_get_contents($counter, 32));
            if(ctype_digit($value)) {
                $count += min((int)$value, PHP_INT_MAX - $count);
            }
        } finally {
            fclose($counter);
        }
    }
    return $count;
}

function mb_superTrimLocal(string $text): string {
    return trim(preg_replace(
        '/(?:\x{1F3F4}[\x{E0030}-\x{E0039}\x{E0061}-\x{E007A}]+\x{E007F}|[\p{Cc}\p{Cf}\p{Z}])+/u',
        ' ',
        $text
    ) ?? '', ' ');
}

function filter_filename(string $filename, bool $failOnInvalid = true, ?callable $trim = null): string {

    if(!mb_check_encoding($filename, 'UTF-8')) {

        if($failOnInvalid) die("PANIC: Broken Encoding!");
        return '';
    }

    $filename = basename(str_replace('\\', '/', $filename));

    $filename = emojiToHtmlEntities($filename);
    $filename = remove_entities($filename);
    $filename = $trim === null ? mb_superTrim($filename) : $trim($filename);

    $filename = preg_replace('~
        [<>:"/\\\\|?*]      |  # reserved
        [\x00-\x1F]         |  # control
        [\x7F\xA0\xAD]      |  # DEL, NBSP, SHY
        [#\[\]@!$&\'()+,;=] |  # URI reserved
        [{}^\~`]               # URL-unsafe
    ~xu', '-', $filename);

    $filename = str_replace(' ', '_', $filename);
    $filename = preg_replace(['/-{2,}/', '/\.{2,}/', '/_{2,}/'], ['-', '.', '_'], $filename);
    $filename = trim($filename, ". \t\r\n\0\x0B-_");

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    /// $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    $limit = ($ext !== '') ? max(1, 255 - (strlen($ext) + 1)) : 255;
    $base  = mb_strcut($base, 0, $limit, 'UTF-8');

    if($base === '' || strlen($ext) > 10) {
        if($failOnInvalid) die("PANIC: Incorrect File Name!");
        return '';
    }

    return ($ext !== '') ? ($base . '.' . $ext) : $base;
}

function emojiToHtmlEntities(string $string): string {

    if(!mb_check_encoding($string, 'UTF-8')) {
        throw new InvalidArgumentException('Invalid UTF-8');
    }

    return preg_replace_callback('/\X/u', static function ($m) {

        $g = $m[0];

        // Белый список обычных символов:
        // Буквы, цифры, комбинируемые знаки,
        // пунктуация, пробелы, математические,
        // валютные и некоторые другие символы.
        $ordinary = '/\A[\p{L}\p{N}\p{M}\p{P}\p{Zs}\p{Sc}\p{Sm}\p{Sk}№°©®™→∞]+\z/u';

        // Обычные символы оставляем без изменений.
        // Исключение: U+20E3 (составные emoji-клавиши).
        if(
            preg_match($ordinary, $g) === 1 &&
            !str_contains($g, "\u{20E3}")
        ) {
            return $g;
        }

        // Всё остальное кодируем целым кластером.
        return mb_encode_numericentity(
            $g,
            [0, 0x10FFFF, 0, 0x10FFFF],
            'UTF-8'
        );

    }, $string);
}

function remove_entities(string $text): string {
    // 1) именованные (&nbsp;)  2) десятичные (&#160;)  3) шестн. (&#xA0;)
    $text = preg_replace('/&[a-z][a-z0-9]*;/i', '', $text);
    $text = preg_replace('/&#\d+;/', '', $text);
    $text = preg_replace('/&#x[0-9a-f]+;/i', '', $text);

    // 4) Только формы без ';' и не продолжаемые буквой/цифрой/подчёркиванием
    $text = preg_replace('/&(?:nbsp|thinsp|ensp|emsp|zwnj|zwj|lrm|rlm)(?!;)\b/i', '', $text);

    // 5) «голые» & → подчеркивание, чтобы слова не слипались
    $text = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '_', $text);

    return $text;
}
