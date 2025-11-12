<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

// example.php contents

use Detection\Exception\MobileDetectException;
use Detection\MobileDetectStandalone;

require_once 'SYSTEM/PHPLIB/Mobile-Detect/standalone/autoloader.php';
require_once 'SYSTEM/PHPLIB/Mobile-Detect/src/MobileDetectStandalone.php';

$mobileDetection = new MobileDetectStandalone();

// $mobileDetection->setUserAgent('iPhone'); ///


try {

    if($mobileDetection->isMobile()) $sMobile = "-mobile";

} catch (MobileDetectException $e) {
    
}


try {

    if($mobileDetection->isTablet()) $sMobile = "";

} catch (MobileDetectException $e) {
    
}
