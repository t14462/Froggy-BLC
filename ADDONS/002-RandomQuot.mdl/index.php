<?php


if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

// require_once __DIR__ . "/../GetRootStandalone.php";
// require_once __DIR__ . "/../GetRelStandalone.php";

################################################
################################################
################################################



$filename = "DATABASE/RandomQuot.txt";



function getRandomLineFromLargeFile(string $filename) {

    $handle = openFileOrDie($filename, 'rb');

    $handle->setFlags(
        SplFileObject::READ_AHEAD // Для лучшей производительности, позволяет "заглядывать" в файл
        | SplFileObject::SKIP_EMPTY 
        | SplFileObject::DROP_NEW_LINE
    );

    $result = null;
    $count = 0;


    foreach ($handle as $line) {
        $count++;
        if (rand(1, $count) === 1) {
            $result = $line;
        }
    }

    $handle = null;


    return $result;
}






$randQuot = getRandomLineFromLargeFile($filename);

$randQuot = explode("<!!!>", $randQuot);

$randQuot[0] = mb_superTrim($randQuot[0]);

$menubar .= "<figure class='my-blockquote' style='margin-left: 1.6rem;'><blockquote>{$randQuot[0]}</blockquote>";

if(!empty($randQuot[1])) $menubar .= "<figcaption>{$randQuot[1]}</figcaption>";

$menubar .= "</figure>";
