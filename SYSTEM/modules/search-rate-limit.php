<?php

declare(strict_types=1);

namespace Froggy\Search;

/**
 * Скользящее окно по IP. Возвращает 0 при разрешении или время ожидания в секундах.
 * Файл общий для PHP-процессов; чтение, проверка и запись выполняются под одной блокировкой.
 */
function consumeRateLimit(string $filename, string $ip, int $limit, int $window, ?int $now = null): int
{
    $address = @inet_pton($ip);
    if($address === false || $limit < 1 || $window < 1) {
        throw new \InvalidArgumentException('Invalid search rate-limit configuration.');
    }

    $now ??= time();
    $key = hash('sha256', $address);
    $maxClients = 1024;
    $maxBytes = 512 * 1024;
    $file = @fopen($filename, 'c+b');
    if($file === false) {
        throw new \RuntimeException('Cannot open search rate-limit storage.');
    }

    try {
        if(!@flock($file, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock search rate-limit storage.');
        }

        $contents = @stream_get_contents($file, $maxBytes + 1);
        if($contents === false || strlen($contents) > $maxBytes) {
            throw new \RuntimeException('Cannot read search rate-limit storage.');
        }

        $clients = $contents === '' ? [] : json_decode($contents, true);
        if(!is_array($clients) || count($clients) > $maxClients) {
            throw new \RuntimeException('Invalid search rate-limit storage.');
        }

        // Старые записи удаляются при следующем разрешённом поиске.
        foreach($clients as $clientKey => $requests) {
            if(!is_string($clientKey) || preg_match('/\A[a-f0-9]{64}\z/', $clientKey) !== 1
                || !is_array($requests) || !array_is_list($requests)) {
                throw new \RuntimeException('Invalid search rate-limit entry.');
            }

            $recent = [];
            foreach($requests as $timestamp) {
                if(!is_int($timestamp) || $timestamp < 0) {
                    throw new \RuntimeException('Invalid search rate-limit timestamp.');
                }
                if($timestamp > $now - $window) {
                    $recent[] = $timestamp;
                }
            }

            if($recent === []) {
                unset($clients[$clientKey]);
            } else {
                $clients[$clientKey] = $recent;
            }
        }

        $requests = $clients[$key] ?? [];
        if(count($requests) >= $limit) {
            // Отказы не продлевают ожидание.
            return max(1, min($requests) + $window - $now);
        }

        // Ограничиваем размер состояния и при большом числе разных IP.
        if($requests === [] && count($clients) >= $maxClients) {
            $retryAfter = $window;
            foreach($clients as $timestamps) {
                $retryAfter = min($retryAfter, max($timestamps) + $window - $now);
            }
            return max(1, $retryAfter);
        }

        $requests[] = $now;
        $clients[$key] = $requests;
        $encoded = json_encode($clients);
        if($encoded === false || strlen($encoded) > $maxBytes) {
            throw new \RuntimeException('Search rate-limit storage is too large.');
        }
        if(!@rewind($file)) {
            throw new \RuntimeException('Cannot rewind search rate-limit storage.');
        }

        $length = strlen($encoded);
        for($written = 0; $written < $length;) {
            $bytes = @fwrite($file, substr($encoded, $written));
            if($bytes === false || $bytes === 0) {
                throw new \RuntimeException('Cannot write search rate-limit storage.');
            }
            $written += $bytes;
        }
        if(!@ftruncate($file, $length) || !@fflush($file)) {
            throw new \RuntimeException('Cannot flush search rate-limit storage.');
        }
    } finally {
        fclose($file);
    }

    return 0;
}
