<?php

declare(strict_types=1);

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

define('DBLOCK_FILE', 'DATABASE/DB/DBLOCK');


function rawCsrfEquals(mixed $token): bool {
    global $csrf;

    if(!is_int($token) && !is_string($token)) {
        return false;
    }

    return hash_equals((string)$csrf, (string)$token);
}


/**
 * DBLOCK можно заменить или удалить. Все операции синхронизируются через постоянный .wait-файл.
 */
function withEditorLock(int $mode, callable $operation): mixed {
    $wait = lockStateFile(DBLOCK_FILE . '.wait', $mode);
    if($wait === false) {
        return null;
    }

    try {
        clearstatcache(true, DBLOCK_FILE);
        return $operation();
    } finally {
        fclose($wait);
    }
}

function isDbLocked(): bool {
    return withEditorLock(LOCK_SH, static fn(): bool => is_file(DBLOCK_FILE)) ?? true;
}

function isLockedBy(string $username): bool {
    $owner = readDBLock();
    return $owner !== false && trim($owner) === $username;
}

function lockByName(string $username): bool {
    return withEditorLock(LOCK_EX, static function () use ($username): bool {
        if(is_file(DBLOCK_FILE)) {
            $owner = @file_get_contents(DBLOCK_FILE);
            return $owner !== false && trim($owner) === $username;
        }

        return replaceFileContents(DBLOCK_FILE, $username . "\n");
    }) ?? false;
}

function unlockByName(string $username): bool {
    return withEditorLock(LOCK_EX, static function () use ($username): bool {
        $owner = @file_get_contents(DBLOCK_FILE);
        return $owner !== false && trim($owner) === $username && @unlink(DBLOCK_FILE);
    }) ?? false;
}

function maintainEditorLock(?string $username): void {
    if(!is_file(DBLOCK_FILE)) {
        return;
    }

    withEditorLock(LOCK_EX, static function () use ($username): void {
        $mtime = @filemtime(DBLOCK_FILE);
        if($mtime === false) {
            return;
        }

        if(time() - $mtime > 5400) {
            @unlink(DBLOCK_FILE);
        } elseif($username !== null) {
            $owner = @file_get_contents(DBLOCK_FILE);
            if($owner !== false && trim($owner) === $username) {
                @touch(DBLOCK_FILE);
            }
        }
    });
}

$editor = $checkpermission >= 3 && is_string($_SESSION['username'] ?? null) ? $_SESSION['username'] : null;
maintainEditorLock($editor);

if($editor !== null && (rawCsrfEquals($_GET['csrf'] ?? null) || rawCsrfEquals($_POST['csrf'] ?? null))) {
    if(
        isset($_POST['title'], $_POST['h'], $_POST['textedit'], $_POST['dbtimestamp']) ||
        isset($_GET['edit']) ||
        isset($_GET['addpage']) ||
        isset($_GET['pagedel']) ||
        isset($_GET['pgmoveup']) ||
        isset($_GET['pgmovedown'])
    ) {
        lockByName($editor);
    } elseif(isset($_GET['leaveedit'])) {
        unlockByName($editor);
    }
}

function plocked() {

    global $mainPageTitle;

    http_response_code(403);

    $mainPageTitle = "403. БД была заблокирована.";

    return "<h1>403.</h1><p class='big'><strong>БД была заблокирована. (Редактируется другим пользователем).</strong></p>";
}






function readDBLock(): string|false {
    return withEditorLock(LOCK_SH, static fn(): string|false => @file_get_contents(DBLOCK_FILE)) ?? false;
}
