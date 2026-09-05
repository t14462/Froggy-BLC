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



$menubar .= "<iframe src='".relPath(__DIR__)."visitors.php' title='Счётчик посетителей Онлайн.' onload='PseudoAJAX(this, \"visCounterBar\")'></iframe><p id='visCounterBar' title='Онлайн' style='font-family: monospace; text-align: center; font-size: 2.5rem; color: Green;'></p>";
