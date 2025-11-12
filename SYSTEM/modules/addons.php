<?php


if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################


$mods = glob('ADDONS/[0-9][0-9][0-9]-*.mdl/index.php');
natsort($mods);
foreach ($mods as $file) require_once $file;
