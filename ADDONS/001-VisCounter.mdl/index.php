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



$menubar .= "<iframe src='".relPath(__DIR__)."visitors.php' title='Счётчик посетителей Онлайн.' style='background: transparent;'></iframe>";
