<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

define('DBLOCK_FILE', 'DATABASE/DB/DBLOCK');

/**
 * Проверяет, заблокирована ли база кем-либо
 * @return bool
 */
function isDbLocked(): bool {
    return is_file(DBLOCK_FILE);
}

/**
 * Проверяет, заблокировал ли текущий пользователь
 * @param string $username
 * @return bool
 */
function isLockedBy(string $username): bool {
    if (!isDbLocked()) return false;


    $locktmp = fopen(DBLOCK_FILE, 'rb');
    flock($locktmp, LOCK_SH);

    $lockedBy = trim(stream_get_contents($locktmp));

    flock($locktmp, LOCK_UN);
    fclose($locktmp);


    /// $lockedBy = trim(@file_get_contents(DBLOCK_FILE));
    return $lockedBy === $username;
}

/**
 * Блокирует базу для пользователя, если она не заблокирована
 * @param string $username
 * @return bool true, если успешно заблокировано, false — если уже заблокирована другим
 */
function lockByName(string $username): bool {
    if (isDbLocked()) {
        if (isLockedBy($username)) {
            return true; // Уже заблокирована этим же пользователем
        }
        return false; // Заблокирована другим
    }
    return file_put_contents(DBLOCK_FILE, $username . "\n", LOCK_EX) !== false;
}

/**
 * Снимает блокировку, если она принадлежит текущему пользователю
 * @param string $username
 * @return bool
 */
function unlockByName(string $username): bool {
    if (isLockedBy($username)) {
        return @unlink(DBLOCK_FILE);
    }
    return false;
}





if(isLockedBy($_SESSION['username'] ?? "")) {

    touch(DBLOCK_FILE);
}





$last_executed3 = @filemtime(DBLOCK_FILE);

$diff3 = time() - (int)$last_executed3; // $last_executed is the value from the server

if($last_executed3 && $diff3 > 5400) { // 1.5 hours

    unlink(DBLOCK_FILE);
}





if( isset($_SESSION['username']) && (
    isset($_POST['title'], $_POST['h'], $_POST['textedit']) ||

    isset($_GET['edit'])     ||

    isset($_GET['addpage'])  ||
    isset($_GET['pagedel'])  ||
    isset($_GET['pgmoveup']) ||
    isset($_GET['pgmovedown'])
        )

    ) {

        $lockPermission = $cred[$_SESSION['username']];
        $lockPermission = (int)explode("<!!!>", $lockPermission)[0];

        if(!isDbLocked() && $lockPermission > 2) {

            lockByName($_SESSION['username']);
        }

} elseif(isset($_GET['leaveedit'])) {

    unlockByName($_SESSION['username'] ?? "");
}







function plocked() {

    global $mainPageTitle;

    http_response_code(403);

    $mainPageTitle = "403. БД была заблокирована.";

    return "<h1>403.</h1><p class='big'><strong>БД была заблокирована. (Редактируется другим пользователем).</strong></p>";
}