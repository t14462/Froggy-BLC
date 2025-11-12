<?php


if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

// require_once __DIR__ . "/../GetRootStandalone.php";
require_once __DIR__ . "/../GetRelStandalone.php";

################################################
################################################
################################################


// $cssTime1 = filemtime(relPath(__DIR__)."BgAnim.css");


$head .= "\n<link rel='stylesheet' href='".skipCache(relPath(__DIR__).'BgAnim.css')."' />\n";
