<?php

declare(strict_types=1);


if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

// require_once __DIR__ . "/../GetRootStandalone.php";
require_once __DIR__ . "/../GetRelStandalone.php";

################################################
################################################
################################################




$head .= "\n<link rel='stylesheet' href='".skipCache(relPath(__DIR__).'Additions.css')."' />\n";
