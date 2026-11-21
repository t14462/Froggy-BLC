<?php

declare(strict_types=1);

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

function ensure_html_purifier_loaded() {

    require_once "SYSTEM/PHPLIB/htmlpurifier/library/HTMLPurifier.auto.php";
}

require_once "SYSTEM/PHPLIB/simplehtmldom/simple_html_dom.php";
