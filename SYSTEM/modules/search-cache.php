<?php

declare(strict_types=1);

namespace Froggy\Search;

// При изменении формата или правил очистки текста увеличьте версию кэша.
const SEARCH_CACHE_VERSION = 1;
const SEARCH_CACHE_HEADER_BYTES = 256;

/** @param resource|null $stream */
function searchSourceStamp(string $filename, $stream = null): string
{
    clearstatcache(true, $filename);
    $stat = $stream === null ? @stat($filename) : @fstat($stream);
    if($stat === false) {
        throw new \RuntimeException('Cannot stat search database.');
    }

    // Существующая метка CMS различает сохранения в пределах одной секунды.
    $time = @file_get_contents($filename . '.time', false, null, 0, 128);
    return hash('sha256', implode(':', [
        $stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime'], $time === false ? '' : $time,
    ]));
}

/** @param resource $stream */
function searchCacheIsCurrent($stream, string $sourceStamp): bool
{
    if(!@rewind($stream)) return false;
    $header = @fread($stream, SEARCH_CACHE_HEADER_BYTES);
    $stat = @fstat($stream);
    if($header === false || strlen($header) !== SEARCH_CACHE_HEADER_BYTES || $stat === false) return false;
    $metadata = json_decode(trim($header), true);
    return is_array($metadata)
        && ($metadata['version'] ?? null) === SEARCH_CACHE_VERSION
        && ($metadata['source'] ?? null) === $sourceStamp
        && ($metadata['bytes'] ?? null) === $stat['size'];
}

/** @param resource $stream */
function writeSearchCacheBytes($stream, string $bytes): void
{
    $length = strlen($bytes);
    for($written = 0; $written < $length;) {
        $count = @fwrite($stream, substr($bytes, $written));
        if($count === false || $count === 0) {
            throw new \RuntimeException('Cannot write search text cache.');
        }
        $written += $count;
    }
}

/** @param resource $cache */
function rebuildSearchCache($cache, string $database, callable $parseArticle): void
{
    $source = @fopen($database, 'rb');
    if($source === false) {
        throw new \RuntimeException('Cannot open search database.');
    }

    try {
        $sourceStat = fstat($source);
        $cacheStat = fstat($cache);
        if($sourceStat['dev'] === $cacheStat['dev'] && $sourceStat['ino'] === $cacheStat['ino']) {
            throw new \RuntimeException('Search cache must be separate from its database.');
        }
        $stamp = searchSourceStamp($database, $source);
        if(!@rewind($cache)) {
            throw new \RuntimeException('Cannot rewind search text cache.');
        }
        // Пока запись не завершена, заголовок недействителен. Дополнительные файлы не нужны.
        writeSearchCacheBytes($cache, str_repeat(' ', SEARCH_CACHE_HEADER_BYTES - 1) . "\n");
        if(!@fflush($cache) || !@ftruncate($cache, SEARCH_CACHE_HEADER_BYTES)) {
            throw new \RuntimeException('Cannot reset search text cache.');
        }

        while(($line = @fgets($source)) !== false) {
            $article = $parseArticle($line);
            if($article === null) continue;
            foreach($article as $part) {
                if(strpbrk($part, "\t\r\n") !== false) {
                    throw new \RuntimeException('Invalid normalized search text.');
                }
            }
            writeSearchCacheBytes($cache, implode("\t", $article) . "\n");
        }
        if(!feof($source)) {
            throw new \RuntimeException('Cannot read search database.');
        }
        if(searchSourceStamp($database, $source) !== $stamp || searchSourceStamp($database) !== $stamp) {
            throw new \RuntimeException('Search database changed while building its cache.');
        }
        if(!@fflush($cache) || ($stat = @fstat($cache)) === false) {
            throw new \RuntimeException('Cannot flush search text cache.');
        }

        // Публикуем заголовок последним: оборванная запись будет пересоздана следующим запросом.
        $header = json_encode(['version' => SEARCH_CACHE_VERSION, 'source' => $stamp, 'bytes' => $stat['size']]);
        if($header === false || strlen($header) >= SEARCH_CACHE_HEADER_BYTES || !@rewind($cache)) {
            throw new \RuntimeException('Cannot prepare search cache header.');
        }
        writeSearchCacheBytes($cache, str_pad($header, SEARCH_CACHE_HEADER_BYTES - 1) . "\n");
        if(!@fflush($cache)) {
            throw new \RuntimeException('Cannot commit search text cache.');
        }
    } finally {
        fclose($source);
    }
}

/**
 * Один файл с заголовком и строками «заголовок TAB текст TAB ID».
 * Блокировка удерживается до окончания итерации, включая досрочный break.
 * Если кэш недоступен, статьи читаются из исходной базы.
 *
 * @return \Generator<int, array{string, string, string}>
 */
function cachedArticles(string $database, string $filename, callable $parseArticle): \Generator
{
    $cache = @fopen($filename, 'c+b');
    $writable = $cache !== false;
    if($cache === false) $cache = @fopen($filename, 'rb');

    if($cache !== false) {
        try {
            if(!@flock($cache, LOCK_SH)) {
                throw new \RuntimeException('Cannot lock search text cache.');
            }
            if(!searchCacheIsCurrent($cache, searchSourceStamp($database))) {
                if(!$writable || !@flock($cache, LOCK_UN) || !@flock($cache, LOCK_EX)) {
                    throw new \RuntimeException('Cannot lock search text cache for rebuilding.');
                }
                // Другой процесс мог уже построить кэш, пока мы ожидали блокировку.
                if(!searchCacheIsCurrent($cache, searchSourceStamp($database))) {
                    rebuildSearchCache($cache, $database, $parseArticle);
                }
                if(!@flock($cache, LOCK_SH)) {
                    throw new \RuntimeException('Cannot lock rebuilt search text cache.');
                }
            }
            if(@fseek($cache, SEARCH_CACHE_HEADER_BYTES) !== 0) {
                throw new \RuntimeException('Cannot seek search text cache.');
            }
        } catch(\RuntimeException $exception) {
            error_log('Search cache: ' . $exception->getMessage());
            fclose($cache);
            $cache = false;
        }
    }

    if($cache !== false) {
        try {
            while(($line = @fgets($cache)) !== false) {
                $article = explode("\t", rtrim($line, "\r\n"), 3);
                if(count($article) !== 3 || !mb_check_encoding($line, 'UTF-8')
                    || preg_match('~\A[a-f0-9]{40}\z~i', $article[2]) !== 1) {
                    throw new \RuntimeException('Invalid search text cache record.');
                }
                yield $article;
            }
            if(!feof($cache)) {
                throw new \RuntimeException('Cannot read search text cache.');
            }
        } finally {
            fclose($cache);
        }
        return;
    }

    $source = new \SplFileObject($database, 'rb');
    $source->setFlags(\SplFileObject::READ_AHEAD);
    foreach($source as $line) {
        $article = $parseArticle($line);
        if($article !== null) yield $article;
    }
}
