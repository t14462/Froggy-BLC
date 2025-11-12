<?php


if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################



if($mySalt === 0) {

    $saltGen = bin2hex(random_bytes(64));

    $saltGen = "\$mySalt = \"$saltGen\";\n";

    $file = fopenOrDie("SYSTEM/salt.php", "ab");
    fwriteOrDie($file, $saltGen);
    fclose($file);
}
