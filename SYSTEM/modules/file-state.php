<?php

/** @return resource|false */
function lockStateFile(string $path, int $mode, string $openMode = 'c+b') {
    $lock = @fopen($path, $openMode);
    if($lock === false) {
        return false;
    }
    if(!@flock($lock, $mode)) {
        fclose($lock);
        return false;
    }
    return $lock;
}

function atomicCounterIncrement(string $path, bool $logFailure = true): void {
    $lockPath = dirname($path) . '/.' . hash('sha256', basename($path)) . '.count.lock';
    $lock = lockStateFile($lockPath, LOCK_EX);
    $saved = false;
    if($lock !== false) {
        try {
            clearstatcache(true, $path);
            $contents = file_exists($path) ? @file_get_contents($path, false, null, 0, 32) : '';
            if($contents !== false) {
                $contents = trim($contents);
                $count = ctype_digit($contents) ? (int)$contents : 0;
                if($count === PHP_INT_MAX) {
                    return;
                }
                $saved = replaceFileContents($path, (string)($count + 1));
            }
        } finally {
            fclose($lock);
        }
    }

    if(!$saved && $logFailure) {
        error_log('Не удалось обновить счётчик ' . $path);
    }
}

function replaceFileContents(string $filename, string $data): bool {
    $dir = realpath(dirname($filename));
    if($dir === false) {
        return false;
    }

    $temporary = @tempnam($dir, '.' . basename($filename) . '-');
    if($temporary === false) {
        return false;
    }

    try {
        clearstatcache(true, $filename);
        $permissions = @fileperms($filename);
        return dirname($temporary) === $dir
            && @file_put_contents($temporary, $data) === strlen($data)
            && ($permissions === false || @chmod($temporary, $permissions & 0777))
            && @rename($temporary, $filename);
    } finally {
        if(is_file($temporary)) {
            @unlink($temporary);
        }
    }
}
