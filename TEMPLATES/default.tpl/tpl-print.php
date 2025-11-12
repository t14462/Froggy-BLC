<?php

if (!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################


// $cssTime1 = filemtime("TEMPLATES/default.tpl/tpl-print.css");


?><!DOCTYPE html>
<html lang="ru">
<head>
<link rel="stylesheet" href="<?=skipCache("TEMPLATES/default.tpl/tpl-print.css")?>" />
<?php echo $head; ?>
<style>
@media print {
    .noPrint, nav#TOC, .vid-wrapper, #txtpath > button {
        display: none;
    }
}

@media screen {
    .noPrint {
        text-align: center;
        margin: 2em;
        font-size: 2em;
    }
    .noPrint * {
        display: inline-block;
        font-size: 2em;
    }
}
</style>
<script>
  window.addEventListener("beforeprint", () => {
    document.querySelectorAll("details").forEach((d) => d.open = true);
  });
</script>
</head>
<body>
<main>
<div class="noPrint"><button onclick="window.print();">🖨️ НАПЕЧАТАТЬ</button></div>
<?php

    echo $txtpath;

    echo $content;
?>
</main>
</body>
</html>
