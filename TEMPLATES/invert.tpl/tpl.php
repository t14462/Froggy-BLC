<?php

if (!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################


// $cssTime1 = filemtime("TEMPLATES/invert.tpl/tpl.css");

// $cssTime2 = filemtime("TEMPLATES/invert.tpl/not-admin.css");


?><!DOCTYPE html>
<html lang="ru">
<head>
<link rel="stylesheet" href="<?=skipCache("TEMPLATES/invert.tpl/tpl.css")?>" />
<?php
    echo $head;

    if(!$checkpermission) {
        echo '<link rel="stylesheet" href="'.skipCache("TEMPLATES/invert.tpl/not-admin.css").'" />';
    }
?>
</head>
<body>
<?php echo $body; ?>
<aside>
<?php echo $menubar; ?>
</aside>
<main><?php echo $errmsg; ?>
<hr style='clear: both;' />
<?php echo $stoolbox;

    if($ispageexist) {

        echo $txtpath;
    } ?>


    <?php echo $content; ?>


    <?php if($ispageexist) {

        echo prevnextslider();
        echo $tplcomments;
    }

?></main>
<footer><?php echo $footer; ?></footer>
</body>
</html>
