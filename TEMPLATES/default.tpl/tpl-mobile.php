<?php

if (!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################


// $cssTime1 = filemtime("TEMPLATES/default.tpl/tpl-mobile.css");

// $cssTime2 = filemtime("TEMPLATES/default.tpl/not-admin.css");


?><!DOCTYPE html>
<html lang="ru">
<head>
<link rel="stylesheet" href="<?=skipCache("TEMPLATES/default.tpl/tpl-mobile.css")?>" />
<?php
    echo $head;

    if(!$checkpermission) {
        echo '<link rel="stylesheet" href="'.skipCache("TEMPLATES/default.tpl/not-admin.css").'" />';
    }
?>
</head>
<body>
<?php echo $body; ?>
<main>
<!-- <p class="mscroll"><a href="#sitemenu">Меню</a>&nbsp;&nbsp;<a href="#comm-title">Коммент</a></p> -->
<?php echo $errmsg; ?>
<hr style='clear: both;' />
<?php echo $stoolbox;

    if($ispageexist) {

        echo $txtpath;
    } ?>


    <?php echo $content; ?>


    <?php if($ispageexist) {

        echo prevnextslider();
        // echo "<p class=\"mscroll\"><a href=\"#system-links\">ВВЕРХ!</a>&nbsp;&nbsp;<a href=\"#sitemenu\">Меню</a></p>";
        echo $tplcomments;
    }

?></main>
<aside id="to-menu-bar">
<!-- <p class="mscroll">&nbsp;<a href="#system-links">ВВЕРХ!</a>&nbsp;&nbsp;<a href="#comm-title">Коммент</a></p> -->
<?php echo $menubar; ?> 
<!-- <p class="mscroll">&nbsp;<a href="#system-links">ВВЕРХ!</a>&nbsp;&nbsp;<a href="#comm-title">Коммент</a>&nbsp;&nbsp;<a href="#sitemenu">Меню</a></p> -->
</aside>
<footer><?php echo $footer; ?></footer>
<div class="mscroll"><a href="#system-links">ВВЕРХ!</a>&nbsp;<a href="#comm-title">Коммент</a>&nbsp;<a href="#to-menu-bar">Меню</a></div>
</body>
</html>
