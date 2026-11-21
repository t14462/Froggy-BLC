<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

function prepareCommentWrite(string $pageId, bool $create = false, ?int &$failureStatus = null, ?float $expectedVersion = null): bool {
    global $idcache, $pgaddrcache, $query;

    $index = array_search($pageId, $idcache, true);
    if($index === false) {
        $failureStatus = 404;
        return false;
    }

    $offset = $query[$pgaddrcache[$index] ?? ''] ?? -1;
    $filename = 'DATABASE/comments/' . $pageId;
    try {
        return dbprepApnd($filename, $expectedVersion, beforeCopy: static function () use ($pageId, $offset, $filename, $create): bool {
            // Проверяем наличие страницы под той же .wait, что и при удалении комментариев.
            if(readPageRecord($pageId, $offset) === false) {
                return false;
            }
            if(!recoverCommentCount($filename)) {
                error_log('Froggy-BLC: не удалось восстановить счётчик ' . $filename);
                throw new RuntimeException('Не удалось восстановить счётчик комментариев.');
            }
            clearCommentSnapshots($filename);

            if(!$create) {
                clearstatcache(true, $filename);
                return is_file($filename);
            }
            $file = @fopen($filename, 'c+b');
            if($file === false || !fclose($file)) {
                throw new RuntimeException('Не удалось открыть файл комментариев.');
            }
            return true;
        }, failureStatus: $failureStatus);
    } catch(RuntimeException) {
        $failureStatus = 500;
        return false;
    }
}

/** Вызывается после подготовки снимка под блокировкой .wait. */
function publishComment(string $pageId, string $commentId, string $draft, ?callable $beforeUnlock = null): bool {
    $filename = 'DATABASE/comments/' . $pageId;
    $count = readStoredCommentCount($filename);
    if($count === false) {
        cancelDatabaseWrite($filename);
        return reportDatabaseWriteFailure($draft);
    }

    $count ??= 0;
    $nextCount = $count < PHP_INT_MAX ? $count + 1 : $count;
    $pending = json_encode(['id' => $commentId, 'count' => $nextCount]);
    if($pending === false || !replaceFileContents($filename . '.count.pending', $pending)) {
        cancelDatabaseWrite($filename);
        return reportDatabaseWriteFailure($draft);
    }

    return dbdone($filename, $draft, static function () use ($filename, $nextCount, $beforeUnlock): void {
        if(!completeCommentCount($filename, $nextCount)) {
            error_log('Froggy-BLC: не удалось обновить счётчик ' . $filename);
        }
        if($beforeUnlock !== null) {
            $beforeUnlock();
        }
    });
}

function loadCommentCount(string $pageId): ?int {
    $filename = 'DATABASE/comments/' . $pageId;
    $pendingPath = $filename . '.count.pending';
    clearstatcache(true, $pendingPath);
    if(is_file($pendingPath)) {
        $wait = lockStateFile($filename . '.wait', LOCK_EX);
        if($wait !== false) {
            try {
                if(!recoverCommentCount($filename)) {
                    error_log('Froggy-BLC: не удалось восстановить счётчик ' . $filename);
                }
            } finally {
                fclose($wait);
            }
        }
    }

    $count = readStoredCommentCount($filename);
    return $count === false ? null : $count;
}

function clearPageComments(string $pageId): bool {
    $filename = 'DATABASE/comments/' . $pageId;
    $wait = lockStateFile($filename . '.wait', LOCK_EX);
    if($wait === false) {
        return false;
    }

    try {
        clearstatcache();
        $ok = true;
        foreach(['', '.bak', '.time', '.pages-cache', '.lock', '.count', '.count.pending'] as $suffix) {
            $file = $filename . $suffix;
            if((is_file($file) || is_link($file)) && !@unlink($file)) {
                $ok = false;
            }
        }
        clearCommentSnapshots($filename);
        return $ok;
    } finally {
        // .wait остаётся на месте: ожидающий процесс уже мог открыть этот файл.
        fclose($wait);
    }
}

/** Возвращает false при ошибке чтения и null при отсутствии числового счётчика. */
function readStoredCommentCount(string $filename): int|false|null {
    $path = $filename . '.count';
    clearstatcache(true, $path);
    if(!file_exists($path)) {
        return null;
    }

    $file = is_file($path) ? lockStateFile($path, LOCK_SH, 'rb') : false;
    if($file === false) {
        return false;
    }

    try {
        $contents = @stream_get_contents($file, 32);
        if($contents === false) {
            return false;
        }
        $contents = trim($contents);
        return ctype_digit($contents) ? (int)$contents : null;
    } finally {
        fclose($file);
    }
}

/** Вызывается под исключительной блокировкой .wait файла комментариев. */
function recoverCommentCount(string $filename): bool {
    $pendingPath = $filename . '.count.pending';
    clearstatcache(true, $pendingPath);
    if(!file_exists($pendingPath)) {
        return true;
    }

    $raw = @file_get_contents($pendingPath, false, null, 0, 256);
    $pending = $raw === false || strlen($raw) === 256 ? null : json_decode($raw, true);
    if(!is_array($pending) || !is_string($pending['id'] ?? null)
        || preg_match('/\A[a-f0-9]{40}\z/', $pending['id']) !== 1
        || !is_int($pending['count'] ?? null) || $pending['count'] < 1) {
        return false;
    }

    $source = is_file($filename) ? @fopen($filename, 'rb') : false;
    if($source === false) {
        return false;
    }

    try {
        $stat = @fstat($source);
        if($stat === false) {
            return false;
        }

        // .time обновляется перед rename(). Публикацию подтверждает ID в основном файле.
        $marker = '<' . $pending['id'] . ' />';
        while(($line = @fgets($source)) !== false) {
            if(str_contains($line, $marker)) {
                return completeCommentCount($filename, $pending['count']);
            }
        }
        return feof($source) && @ftell($source) === $stat['size'] && @unlink($pendingPath);
    } finally {
        fclose($source);
    }
}

/** Обновляет счётчик под .wait и удаляет отметку только после успешной записи. */
function completeCommentCount(string $filename, int $count): bool {
    return replaceFileContents($filename . '.count', (string)$count)
        && @unlink($filename . '.count.pending');
}

/** Удаляет снимки предыдущих писателей после захвата .wait. */
function clearCommentSnapshots(string $filename): void {
    foreach(glob($filename . '.{new,src}.*', GLOB_BRACE | GLOB_NOSORT) ?: [] as $path) {
        if(preg_match('/\A\.(?:new|src)\.[0-9]+(?:\.tmp)?\z/', substr($path, strlen($filename))) === 1) {
            @unlink($path);
        }
    }
}
