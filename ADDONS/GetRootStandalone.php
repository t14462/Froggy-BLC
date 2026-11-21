<?php

declare(strict_types=1);

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

// Корень сайта — родительский каталог ADDONS, где находится этот файл.
if(!chdir(dirname(__DIR__))) {
    throw new RuntimeException('Unable to change working directory to site root');
}
