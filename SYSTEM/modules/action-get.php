<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

function permalink() {
    global $idcache, $sitemaptxt, $errmsg;

    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $articleHash = explode("&", $queryString)[0];
    $articleHash = mb_substr($articleHash, 0, 40);
    $pageMenuNum = array_search($articleHash, $idcache, true);

    if(!is_int($pageMenuNum)) {
        $errmsg = pnotfound();
    } else {
        header('Location: '.$sitemaptxt[$pageMenuNum], true, 301);
        exit();
    }
}

function delimg() {
    global $safeGet, $errmsg, $checkpermission;

    if($checkpermission < 3) {
        $errmsg = pforbidden();
        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";
    } else {
        $filetodel = filter_filename($safeGet["delimg"] ?? '');

        if(is_file("DATABASE/gallery/".$filetodel)) {
            // rename("DATABASE/gallery/".$filetodel, "DATABASE/gallery/img.del");
            @unlink("DATABASE/gallery/".$filetodel);
            mylog("<em style='color:DarkOrange'>Изображение ".$filetodel." успешно удалено. (".$_SESSION["username"].").</em>");
        }

        refreshhandle(0, "?gallery=-1", false);
    }
}

function delfile() {
    global $safeGet, $errmsg, $checkpermission;

    if($checkpermission < 3) {
        $errmsg = pforbidden();
        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";
    } else {
        $filetodel = filter_filename($safeGet["delfile"] ?? '');

        if(is_file("DATABASE/fupload/".$filetodel)) {
            // rename("DATABASE/fupload/".$filetodel, "DATABASE/fupload/file.del");
            @unlink("DATABASE/fupload/".$filetodel);
            mylog("<em style='color:DarkOrange'>Файл ".$filetodel." успешно удалён. (".$_SESSION["username"].").</em>");
        }

        refreshhandle(0, "?dlfiles=-1", false);
    }
}

function viewLog() {
    global $safeGet, $content, $mainPageTitle, $checkpermission, $errmsg;

    if($checkpermission < 2) {
        $errmsg = pforbidden();
        $errmsg .= "<p class='big'>Обычные пользователи не могут читать лог.</p>";
    } else {
        $limit = 8;
        $logpg = (int)$safeGet["log"];
        $content = "<span class='big'><strong>Бортовой журнал.</strong></span><br />";

        if(!is_file("DATABASE/DB/sys.log")) {
            $content .= "<p><em>Лог пуст или ещё не создан.</em></p>";
            $mainPageTitle = "Системный Лог";
            return;
        }

        $fsize = filesize("DATABASE/DB/sys.log");
        $pcount = ceil($fsize / ($limit * 1024)) - 1;

        // Обеспечиваем, что всегда есть хотя бы одна страница (индекс 0)
        $pcount = max(0, $pcount);

        if($logpg < 0 || $logpg > $pcount) {

            $logpg = $pcount;
        }

        $pager = "<nav class='pager'>";

        for ($i = 0; $i <= $pcount; $i++) {
            if($logpg == $i) {
                $pager .= " <strong>".$i."</strong> ";
            } else {
                $pager .= " <a rel='nofollow' href='?log=$i'>".$i."</a> ";
            }
        }
        $pager .= "</nav>";

        $content .= $pager;
        $offset = $limit * 1024 * $logpg;

        $content .= "<br /><div>";

        $file = fopenOrDie("DATABASE/DB/sys.log", "rb");
        flock($file, LOCK_SH);
        fseekOrDie($file, $offset);
        $logTxt = freadOrDie($file, $limit * 1024).fgetsOrDie($file);
        flock($file, LOCK_UN);
        fclose($file);

        ensure_html_purifier_loaded();

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.1');

        $purifier = new HTMLPurifier($config);
        $content .= $purifier->purify($logTxt);

        $content .= "</div>";
        $content .= $pager;
        $content .= "<p><a href='?purgelog=1' onclick=\"return confirm('Вы уверены?');\">ОЧИСТИТЬ ЛОГ</a></p>";
        $mainPageTitle = "Системный Лог";
    }
}

function purgelog() {
    global $errmsg, $checkpermission;

    if($checkpermission < 4) {
        $errmsg = pforbidden();
        $errmsg .= "<p class='big'>Только <strong>администраторы</strong> это могут делать.</p>";
    } else {
        // dbprep("DATABASE/DB/sys.log");
        // if(!dbdone("DATABASE/DB/sys.log", "ЛОГ БЫЛ ИЗМЕНЁН ИЛИ ЗАБЛОКИРОВАН ВНЕШНИМ ПРОЦЕССОМ")) return false;

        if(is_file("DATABASE/DB/sys.log")) {
            @unlink("DATABASE/DB/sys.log");
        }

        mylog("<strong style='color:DarkRed'>Лог был очищен. (".$_SESSION["username"].").</strong>");

        $errmsg = "<h1>Лог был очищен.</h1><p class='big'><strong>Подождите момент.</strong></p>";

        refreshhandle(3, "?log=-1", false);
    }
}

function pageload() {

    global $safeGet, $safePost, $content, $query, $txtnamebuf, $errmsg, $commmsg, $txtpath, $tplcomments, $mainPageTitle, $ispageexist, $checkpermission, $patternYT, $replacementYT, $patternVimeo, $replacementVimeo, $patternDM, $replacementDM, $commRecov, $metaDescription, $url, $idcache, $head, $patternDLCNT, $replacementDLCNT;

    // require_once "SYSTEM/cred.php";

    if(!$ispageexist) {

        $errmsg = pnotfound();

    } else {

        ############################################
        ############################################
        ############################################

        // $numgen = (int)explode("/", $_SERVER['QUERY_STRING'])[0];
        // $numgen = seoLinkDecode($numgen);

        $numgen = seoNumGet();

        $txtpath = $txtnamebuf[$numgen - 1];

        $permalinkButton = $idcache[$numgen - 1];
        $permalinkButton = $url."?".$permalinkButton."&amp;permalink=1";
        $permalinkButton = "<button onclick='copyToClipboard(\"$permalinkButton\");' title='Копировать Постоянную Ссылку'>🔗</button>";

        array_shift($txtpath);

        $txtpath = join("</em> <em>", $txtpath);

        $txtpath = "<nav id='txtpath'><em>".$txtpath."</em> $permalinkButton</nav>";

        ############################################

        $metaDescription  = $txtnamebuf[$numgen - 1];

        array_shift($metaDescription);

        $metaDescription = join(" &rarr; ", $metaDescription);

        ############################################
        ############################################
        ############################################

        if($checkpermission) {
            $visitor3 = "readonly='readonly' value='".$_SESSION["username"]."'";
        } elseif(isset($_SESSION["visitor"]) /* && !array_key_exists($_SESSION["visitor"], $cred) && filterUsername($_SESSION["visitor"]) */ ) {
            $visitor3 = "value='".$_SESSION["visitor"]."'";
        } else {
            $visitor3 = "value='Аноним'";
        }

        if(isset($safeGet["commpage"])) {
            $commpage = (int)$safeGet["commpage"];
        } elseif(isset($safePost["commpage"])) {
            $commpage = (int)$safePost["commpage"];
        } else {
            $commpage = "";
        }

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $bytepos = $query['?'.explode("&", $queryString)[0]] ?? 0;

        $file = fopenOrDie("DATABASE/DB/data.html", "rb");
        fseekOrDie($file, $bytepos);

        $commaddr = freadOrDie($file, 40);
        $line = fgetsOrDie($file);
        $dbMtime = filemtimeMy("DATABASE/comments/".$commaddr);

        fclose($file);

        $line = str_replace("<br!>", "\n", $line);

        $ptitle = substr($line, 0, strpos($line, "\n"));
        preg_match('#\A<head([1-6])>(.+?)</head\1>#u', $ptitle, $ptitle);

        $article = stripFirstLine($line);

        $tochandl  = substr($article, 0, strpos($article, "\n"));
        if(str_contains($tochandl, '__TOC__')) {
            $article = stripFirstLine($article);
            $pgtoc = true;
        } else {
            $pgtoc = false;
        }

        // ЭТО ДОЛЖНО ИДТИ ВПЕРЕДИ
        // $ptitle[2] = htmlspecialchars($ptitle[2], ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);

        $mainPageTitle = $ptitle[2];

        /* $ptitle[2] = str_ireplace(
            [
                // Именованные сущности
                '&lt;', '&gt;', '&quot;', '&#039;', '&apos;', '&amp;',
                
                // Десятичные числовые
                '&#60;',  // <
                '&#62;',  // >
                '&#34;',  // "
                '&#39;',  // '
                '&#38;',  // &
                
                // Шестнадцатеричные числовые
                '&#x3C;', '&#x3c;', // <
                '&#x3E;', '&#x3e;', // >
                '&#x22;',           // "
                '&#x27;',           // '
                '&#x26;'            // &
            ],
            [
                '&@lt;', '&@gt;', '&@quot;', '&@apos;', '&@apos;', '&@amp;',
                
                '&@lt;',
                '&@gt;',
                '&@quot;',
                '&@apos;',
                '&@amp;',
                
                '&@lt;', '&@lt;',
                '&@gt;', '&@gt;',
                '&@quot;',
                '&@apos;',
                '&@amp;'
            ],
            $ptitle[2]
        ); */

        $ptitle[2] = escape_amp_txtarea($ptitle[2]);

        /* $article = str_ireplace(
            [
                // Именованные сущности
                '&lt;', '&gt;', '&quot;', '&#039;', '&apos;', '&amp;',
                
                // Десятичные числовые
                '&#60;',  // <
                '&#62;',  // >
                '&#34;',  // "
                '&#39;',  // '
                '&#38;',  // &
                
                // Шестнадцатеричные числовые
                '&#x3C;', '&#x3c;', // <
                '&#x3E;', '&#x3e;', // >
                '&#x22;',           // "
                '&#x27;',           // '
                '&#x26;'            // &
            ],
            [
                '&@lt;', '&@gt;', '&@quot;', '&@apos;', '&@apos;', '&@amp;',
                
                '&@lt;',
                '&@gt;',
                '&@quot;',
                '&@apos;',
                '&@amp;',
                
                '&@lt;', '&@lt;',
                '&@gt;', '&@gt;',
                '&@quot;',
                '&@apos;',
                '&@amp;'
            ],
            $article
        ); */

        $article = escape_amp_txtarea($article);

        // $permalinkA = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

        // $permalinkA = seoLinkDecode($permalinkA);

        $permalinkA = seoNumGet();

        $permalinkA = $idcache[$permalinkA - 1];
        $permalinkA = $url."?".$permalinkA."&amp;permalink=1";
        $permalinkA = "<a href='$permalinkA' rel='bookmark' itemprop='url' aria-label='Постоянная ссылка к этому заголовку' title='Постоянная ссылка к этому заголовку'>#</a>";

        if(!$pgtoc) {
            
            $content .= "<article itemscope='itemscope' itemtype='https://schema.org/Article'><h1>".$ptitle[2]." $permalinkA</h1>\n<section>".$article."</section></article>";

            $html = str_get_html($content, false, true, "UTF-8", false) or die("XSS?.. Пустой или битый HTML.");

        } else {

            // TOC
            $content .= "<article itemscope='itemscope' itemtype='https://schema.org/Article'><h1>".$ptitle[2]." $permalinkA</h1>\n<section><nav aria-label='Содержание' id='TOC'></nav>\n".$article."</section></article>";

            $html = str_get_html($content, false, true, "UTF-8", false) or die("XSS?.. Пустой или битый HTML.");

            $toc = '';
            $last_level = 0;
            $iID = 0;

            foreach($html->find('h1,h2,h3,h4,h5,h6') as $h) {

                $innerTEXT = strip_tags($h->innertext);

                /// $innerTEXT = str_replace("&@", "&", $innerTEXT);
                
                $innerTEXT = normalize_entities_my($innerTEXT);

                $innerTEXT = mb_superTrim($innerTEXT);

                $id = $iID.'-'.urlPrep2($innerTEXT);

                // $h->id= $id; // add id attribute so we can jump to this element
                $h->setAttribute('id', $id); // add id attribute so we can jump to this element
                $level = intval($h->tag[1]);

                if($level > $last_level)
                    $toc .= "<ol>";
                else{
                    $toc .= str_repeat('</li></ol>', $last_level - $level);
                    $toc .= '</li>';
                }

                $toc .= "<li><a href='#{$id}'>{$innerTEXT}</a>";

                $last_level = $level;

                $iID++;
            }

            $toc .= str_repeat('</li></ol>', $last_level);

            /** @var simple_html_dom_node|null $tocnav */
            $tocnav = $html->find('nav#TOC', 0) ?: null;

            $tocnav->innertext = $toc;

        }

        // Генератор Обёртки из Alt
        $html = wrap_images_with_figure($html);

        $html = replaceParagraphs($html);

        $html = convert_infoboxes_to_aside($html);

        $html = replaceSemanticSpans($html);

        // Шаблон ЦИТАТА
        $html = convertQuotBlocks($html);

        $html = parseSpoilers($html);

        $content = $html->save();

        $html->clear();  // чистим объект
        unset($html);    // удаляем переменную

        /* $content = str_ireplace(
            ['&@lt;', '&@gt;', '&@quot;', '&@apos;', '&@amp;' ],
            ['&lt;',  '&gt;',  '&quot;',  '&#039;', '&amp;'],
            $content
        ); */

        // Замена шаблона youtube на iframe
        $content = preg_replace_callback($patternYT, $replacementYT, $content);

        // Замена шаблона vimeo на iframe
        $content = preg_replace_callback($patternVimeo, $replacementVimeo, $content);

        // Замена шаблона DailyMotion на iframe
        $content = preg_replace_callback($patternDM, $replacementDM, $content);

        // Регулярное выражение для замены шаблона счётчика загрузок
        $content = preg_replace_callback($patternDLCNT, $replacementDLCNT, $content);

        $content = str_replace("{{lambda}}", "<div style='font-family:monospace; white-space:nowrap; font-size:6rem; text-align:center'>&nbsp;<span class='a4'>&lambda;</span>++<br /><span class='a3'>&lambda;</span>&nbsp;<span class='a2'>&lambda;</span>&nbsp;</div>", $content);

        /*
        $content = str_replace(
            ["<p><figure", "<p><div", "<p><aside", "<p><details", "<p><table"],
            ["<figure", "<div", "<aside", "<details", "<table"],
            $content
        );
        */

        /// $content = unwrapParagraphsBefore($content);

        /// $content = unwrapParagraphsAfter($content);

        /// $content = str_replace("<p>{{clear}}</p>", "<br style='clear: both;' />", $content);

        $content = str_replace("{{clear}}", "<br style='clear: both;' />", $content);

        $content = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '&amp;', $content);

        foreach(['<h2', '<h3', '<h4', '<h5', '<h6'] as $heading) {

            $content = str_replace($heading, '</section><section>'.$heading, $content);

        }

        $content = ru_nbsp_typograf($content);

        $content = tpl_nobr($content);

        

        if((string)$commRecov !== '') {

            $head .= "
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            setTimeout(function() {
                                var el = document.querySelector('#R');
                                if (el) {
                                    el.scrollIntoView({ behavior: 'smooth' });
                                }
                            }, 1000); // задержка в мс (2000 = 2 секунды)
                        });
                        </script>";

            $commmsg = $errmsg;

            $errmsg = "";
        }

        

        $tplcomments .= "<p id='comm-title'><strong class='big'>КОММЕНТАРИИ</strong></p>";

        if(!isset($safeGet["creply"]) && !isset($safePost["pgcommnum"])) {

            $tplcomments .= "<br style='clear: both;' id='R' />".
            $commmsg."<br style='clear: both;' /><form method='post'>
            
            <input type='hidden' name='dbtimestamp' value='$dbMtime' />

            <fieldset><legend>Ваш комментарий:</legend><p><em>Интервал отправки = <strong>3 Минуты.</strong></em></p><label for='visitor'>Ваше имя:</label><input type='text' id='visitor' name='visitor' ".$visitor3." /><textarea rows='9' maxlength='2500' name='commpost' id='commpost' onkeyup='countChars(this);' onfocus='countChars(this);'>".$commRecov."</textarea><input type='hidden' name='commaddr' value='".$commaddr."' /><div class='el-in-line'><input type='submit' value='💾 Отправить' /><span id='symcount'>2500 Осталось.</span><br style='clear: both;' /><input type='text' name='captcha' placeholder='код' /><img loading='lazy' src='SYSTEM/modules/captcha.php?time=".time()."' alt='CAPTCHA' id='captcha_image' /><a href='javascript: refreshCaptcha();' title='Обновить картинку' class='refresh-captcha'>🔄</a></div></fieldset></form>";
        }

        if(is_file("DATABASE/comments/".$commaddr)) {

            /// $tplcomments .= "";

            $commlimit = 8;

            $total_commpages = calcTotPages($commaddr, $commlimit);

            if(!is_int($commpage) || $commpage > $total_commpages || $commpage < 0) {

                $commpage = $total_commpages;
            }

            

            $filesource = openFileOrDie("DATABASE/comments/".$commaddr, "rb");

            $queryString = $_SERVER['QUERY_STRING'] ?? '';
            $queryBase = explode("&", $queryString)[0];

            $pager = "<nav class='pager'><a href='?" . $queryString . "&commpgcntrecalc=1' rel='nofollow' title='Пересчитать Количество Страниц!!!' onclick=\"return confirm('Это тяжёлая операция. Продолжить?');\">🪄</a>";
            for($i = 0; $i <= $total_commpages; $i++) {

                if($commpage === $i) {

                    $pgNumLink = " <strong>$i</strong> ";

                } else {

                    $pgNumLink = " <a rel='nofollow' href='?".$queryBase."&commpage=$i#comm-section'>$i</a> ";

                }

                
                $pager  .= $pgNumLink;
            }
            $pager  .= "</nav>";

            $tplcomments .= $pager;

            $commstart = $commlimit * $commpage;

            $commentschunk = "";

            $i = $commstart;

            $filesource->seekOrDie($i);

            while($line = $filesource->fgetsOrDie()) {

                if($i < $commstart+$commlimit) {

                    $line = preg_replace("/<[0-9a-f]{40}>/", "", $line);
                    $line = preg_replace("/<[0-9a-f]{40} \/>/", "", $line);

                    $line = str_replace("<br!>", "\n", $line);
                    $line = str_replace("%QUERYSTRING%", $queryBase."&amp;commpage=".$commpage, $line);

                    /// $line = preg_replace('/&(?!\w+;|#\d+;|#x[0-9a-fA-F]+;)/', '&amp;', $line);
                    
                    $line = str_replace("<b>", "<strong>", $line);
                    $line = str_replace("</b>", "</strong>", $line);

                    $line = str_replace("<d>", "<div>", $line);
                    $line = str_replace("</d>", "</div>", $line);

                    $line = str_replace("<id>", $commaddr."-", $line);

                    $commentschunk .= $line;

                } else {

                    break;
                }

                $i++;
            }
            $filesource = null;

            /// $commentschunk = mb_softTrim($commentschunk);

            $tplcomments .= $commentschunk ? "<div id='comm-section'><ul>".$commentschunk."</ul></div>" : "";

            $tplcomments .= $pager;

            if(isset($safeGet["creply"])) {

                $commreplyact = $safeGet["creply"];
                $commreplyact = substr($commreplyact, 0, 92);
                $commreplyact = filter_filename($commreplyact);
                $commreplyactarr = explode("-", $commreplyact);

                if(isset($commreplyactarr[0], $commreplyactarr[1], $commreplyactarr[2])) { 

                    $tplcomments .= "<br style='clear: both;' id='R' />".
                    $commmsg."<br style='clear: both;' /><form method='post'>
                    
                    <input type='hidden' name='dbtimestamp' value='$dbMtime' />
                    
                    <fieldset><legend>Ответ на комментарий:</legend><p><em>Интервал отправки = <strong>3 Минуты.</strong></em> <a href='?".$queryBase."&commpage=".$commpage."' onclick=\"return confirm('Вы действительно хотите покинуть редактор? Несохранённые данные БУДУТ УТЕРЯНЫ!');\">Отменить ответ ⬅️</a></p><label for='visitor'>Ваше имя:</label><input type='text' id='visitor' name='visitor' ".$visitor3." /><input type='hidden' name='commaddr' value='".$commreplyactarr[0]."' /><input type='hidden' name='pgcommnum' value='".$commreplyactarr[1]."' /><input type='hidden' name='repcommid' value='".$commreplyactarr[2]."' /><input type='hidden' name='commpage' value='".$commpage."' /><textarea rows='9' maxlength='2500' name='commpost' id='commpost' onkeyup='countChars(this);' onfocus='countChars(this);'>".$commRecov."</textarea><div class='el-in-line'><input type='submit' value='💾 Отправить' /> <span id='symcount'>2500 Осталось.</span><br style='clear: both;' /><input type='text' name='captcha' placeholder='код' /> <img loading='lazy' src='SYSTEM/modules/captcha.php?time=".time()."' alt='CAPTCHA' id='captcha_image' /><a href='javascript: refreshCaptcha();' title='Обновить картинку' class='refresh-captcha'>🔄</a></div></fieldset></form>";
                }

            } elseif(isset($safePost["pgcommnum"])) {

                $tplcomments .= "<br style='clear: both;' id='R' />".
                $commmsg."<br style='clear: both;' /><form method='post'>
                
                <input type='hidden' name='dbtimestamp' value='$dbMtime' />
                
                <fieldset><legend>Ответ на комментарий:</legend><p><em>Интервал отправки = <strong>3 Минуты.</strong></em> <a href='?".$queryBase."&commpage=".$safePost["commpage"]."' onclick=\"return confirm('Вы действительно хотите покинуть редактор? Несохранённые данные БУДУТ УТЕРЯНЫ!');\">Отменить ответ ⬅️</a></p><label for='visitor'>Ваше имя:</label><input type='text' id='visitor' name='visitor' ".$visitor3." /><input type='hidden' name='commaddr' value='".$safePost["commaddr"]."' /><input type='hidden' name='pgcommnum' value='".$safePost["pgcommnum"]."' /><input type='hidden' name='repcommid' value='".$safePost["repcommid"]."' /><input type='hidden' name='commpage' value='".$safePost["commpage"]."' /><textarea rows='9' maxlength='2500' name='commpost' id='commpost' onkeyup='countChars(this);' onfocus='countChars(this);'>".$commRecov."</textarea><div class='el-in-line'><input type='submit' value='💾 Отправить' /> <span id='symcount'>2500 Осталось.</span><br style='clear: both;' /><input type='text' name='captcha' placeholder='код' /> <img loading='lazy' src='SYSTEM/modules/captcha.php?time=".time()."' alt='CAPTCHA' id='captcha_image' /><a href='javascript: refreshCaptcha();' title='Обновить картинку' class='refresh-captcha'>🔄</a></div></fieldset></form>";

            }
        }
    }
}

function commentRemove() {

    global $safeGet, $idcache, $errmsg, $checkpermission;

    if( $checkpermission == 3 || $checkpermission < 2 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>модераторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } else {

        $remcommaddr = $safeGet["cmove"];
        $remcommaddr = mb_substr($remcommaddr, 0, 92);
        $remcommaddr = filter_filename($remcommaddr);

        $commaddr  = explode("-", $remcommaddr)[0] ?? 0;
        $pgcommnum = (int)(explode("-", $remcommaddr)[1] ?? 0);
        $commid    = explode("-", $remcommaddr)[2] ?? 0;

        $pgcommnum = abs($pgcommnum);

        if(in_array($commaddr, $idcache, true)) {

            $commdataline = "";

            ///$commpage = ceil(($pgcommnum + 1) / 8) - 1; // Используем номер строки для вычисления страницы

            /// То-же самое, но проще.
            $commpage = intdiv($pgcommnum, 8); // при 0-based индексе

            dbprepApnd("DATABASE/comments/".$commaddr);

            $filesource = openFileOrDie("DATABASE/comments/".$commaddr . ".src." . getmypid(), 'rb');

            // Переходим к нужной строке
            $filesource->seekOrDie($pgcommnum);

            /*
            if($filesource->eof()) {
                die();
            }
            */

            // Получаем текущую строку и её номер
            $commdataline = $filesource->current(); // Читаем строку

            $commdatarr = (array)explode("<$commid>",$commdataline);
            $commdatarr[2] = str_replace("<$commid />", "", $commdatarr[2] ?? "");

            
            $commdataline = $commdatarr[0]."DEL".$commdatarr[2];

            $commdataline = str_replace("\r", "", $commdataline);
            $commdataline = str_replace("\n", "", $commdataline);

            $commdataline = str_replace("</li>DEL", "</li>", $commdataline);

            $filesource->seekOrDie($pgcommnum);

            $firstChunkEnd = $filesource->ftell();

            $file = fopenOrDie("DATABASE/comments/".$commaddr.".new." . getmypid(), "r+b");
            ftruncateOrDie($file,$firstChunkEnd);
            fclose($file);

            $filesource->fgetsOrDie();

            $filedest = openFileOrDie("DATABASE/comments/".$commaddr.".new." . getmypid(), 'ab');

            $filedest->fwriteOrDie($commdataline."\n");

            while($line = $filesource->freadOrDie(256*1024)) {

                $filedest->fwriteOrDie($line);
            }

            $filedest = null;
            
            $filesource = null;
            

            if(!dbdone("DATABASE/comments/".$commaddr, "")) return false;

            // mylog("<em style='color:DarkOrange'>Комментарий удалён. (".$_SESSION["username"].").</em>");

            $queryString = $_SERVER['QUERY_STRING'] ?? '';
            refreshhandle(0, "?".explode('&', $queryString)[0]."&ts=".microtime(true)."&commpage=".$commpage."#comm-section", false);

        } else {

            $errmsg = pnotfound();

        }
    }
}

function pageEdit() {

    global $content, $query, $errmsg, $head, $body, /* $apiKeyTinyMCE,*/ $mainPageTitle, $url, $checkpermission, $ispageexist;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } elseif(isDbLocked() && !isLockedBy($_SESSION['username'])) {

        $errmsg = plocked();

    } elseif(!$ispageexist) {

        $errmsg = pnotfound();

    } else {

        $head .= "<script src='SYSTEM/JSLIB/ckeditor/ckeditor.js'></script>";
        $body .= "
                <script>

                    ready(function () {

                        CKEDITOR.editorConfig = function( config ) {
                            config.toolbarGroups = [
                                { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
                                { name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing' ] },
                                { name: 'links', groups: [ 'links' ] },
                                { name: 'insert', groups: [ 'insert' ] },
                                { name: 'tools', groups: [ 'tools' ] },
                                { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
                                { name: 'others', groups: [ 'others' ] },
                                '/',
                                { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                                { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
                                { name: 'styles', groups: [ 'styles' ] },
                                { name: 'colors', groups: [ 'colors' ] },
                                { name: 'about', groups: [ 'about' ] }
                            ];

                            config.protectedSource.push(/&amp;[a-zA-Z][a-zA-Z0-9]*;/g);
                            config.protectedSource.push(/&amp;#\d+;/g);
                            config.protectedSource.push(/&amp;#x[0-9a-fA-F]+;/g);

                        };

                        CKEDITOR.config.fontSize_sizes = '0.7em;0.8em;0.9em;1em;1.1em;1.2em;1.3em;1.4em;1.5em;1.6em;1.7em;1.8em;1.9em;2em;2.1em;2.2em;2.3em;2.4em';

                        CKEDITOR.config.disallowedContent = 'h1';

                        CKEDITOR.config.contentsCss = \"$url"."SYSTEM/modules/my-ck-ed.css\";

                        CKEDITOR.config.versionCheck = false;

                        CKEDITOR.config.height = 500;     // 500 pixels wide.
                        CKEDITOR.config.width = '62vw';   // CSS unit (percent).

                        CKEDITOR.stylesSet.add( 'my_styles', [

                            { name: 'Warning Box', element: 'div', attributes:  { 'class': 'infobox' } },
                            { name: 'Ibox Red',    element: 'div', attributes:  { 'class': 'ibox-red' } },
                            { name: 'Ibox Green',  element: 'div', attributes:  { 'class': 'ibox-green' } },
                            { name: 'Ibox Blue',   element: 'div', attributes:  { 'class': 'ibox-blue' } },
                            { name: 'Spoiler',     element: 'div', attributes:  { 'class': 'spoiler-blk' } },
                            { name: 'Quotation',   element: 'div', attributes:  { 'class': 'my-quot' } },
                            { name: 'Quot-Author', element: 'span', attributes: { 'class': 'my-quot-author' } },
                            { name: 'Big Span',    element: 'span', attributes: { 'class': 'big' } },
                        ]);

                        CKEDITOR.config.stylesSet = 'my_styles';

                        CKEDITOR.config.extraPlugins = 'codesnippet';

                        CKEDITOR.config.codeSnippet_languages = {
                            '1c': '1C',
                            'abnf': 'ABNF',
                            'accesslog': 'AccessLog',
                            'actionscript': 'ActionScript',
                            'ada': 'Ada',
                            'angelscript': 'AngelScript',
                            'apache': 'Apache',
                            'applescript': 'AppleScript',
                            'arcade': 'Arcade',
                            'arduino': 'Arduino',
                            'armasm': 'ARM Assembly',
                            'asciidoc': 'AsciiDoc',
                            'aspectj': 'AspectJ',
                            'autohotkey': 'AutoHotkey',
                            'autoit': 'AutoIt',
                            'avrasm': 'AVR Assembly',
                            'awk': 'AWK',
                            'axapta': 'Axapta',
                            'bash': 'Bash',
                            'basic': 'Basic',
                            'bnf': 'BNF',
                            'brainfuck': 'Brainfuck',
                            'cal': 'CAL',
                            'capnproto': 'Cap’n Proto',
                            'ceylon': 'Ceylon',
                            'clean': 'Clean',
                            'clojure': 'Clojure',
                            'clojure-repl': 'Clojure REPL',
                            'cmake': 'CMake',
                            'c': 'C',
                            'coffeescript': 'CoffeeScript',
                            'coq': 'Coq',
                            'cos': 'Cos',
                            'cpp': 'C++',
                            'crmsh': 'CRMsh',
                            'crystal': 'Crystal',
                            'csharp': 'C#',
                            'csp': 'CSP',
                            'css': 'CSS',
                            'dart': 'Dart',
                            'delphi': 'Delphi',
                            'diff': 'Diff',
                            'django': 'Django',
                            'd': 'D',
                            'dns': 'DNS',
                            'dockerfile': 'Dockerfile',
                            'dos': 'DOS',
                            'dsconfig': 'DSConfig',
                            'dts': 'DTS',
                            'dust': 'Dust',
                            'ebnf': 'EBNF',
                            'elixir': 'Elixir',
                            'elm': 'Elm',
                            'erb': 'ERB',
                            'erlang': 'Erlang',
                            'erlang-repl': 'Erlang REPL',
                            'excel': 'Excel',
                            'fix': 'FIX',
                            'flix': 'Flix',
                            'fortran': 'Fortran',
                            'fsharp': 'F#',
                            'gams': 'GAMS',
                            'gauss': 'Gauss',
                            'gcode': 'GCode',
                            'gherkin': 'Gherkin',
                            'glsl': 'GLSL',
                            'gml': 'GML',
                            'golo': 'Golo',
                            'go': 'Go',
                            'gradle': 'Gradle',
                            'graphql': 'GraphQL',
                            'groovy': 'Groovy',
                            'haml': 'HAML',
                            'handlebars': 'Handlebars',
                            'haskell': 'Haskell',
                            'haxe': 'Haxe',
                            'hsp': 'HSP',
                            'http': 'HTTP',
                            'hy': 'Hy',
                            'inform7': 'Inform 7',
                            'ini': 'INI',
                            'irpf90': 'IRPF90',
                            'isbl': 'ISBL',
                            'java': 'Java',
                            'javascript': 'JavaScript',
                            'jboss-cli': 'JBoss CLI',
                            'json': 'JSON',
                            'julia': 'Julia',
                            'julia-repl': 'Julia REPL',
                            'kotlin': 'Kotlin',
                            'lasso': 'Lasso',
                            'latex': 'LaTeX',
                            'ldif': 'LDIF',
                            'leaf': 'Leaf',
                            'less': 'LESS',
                            'lisp': 'Lisp',
                            'livecodeserver': 'LiveCode Server',
                            'livescript': 'LiveScript',
                            'llvm': 'LLVM',
                            'lsl': 'LSL',
                            'lua': 'Lua',
                            'makefile': 'Makefile',
                            'markdown': 'Markdown',
                            'mathematica': 'Mathematica',
                            'matlab': 'MATLAB',
                            'maxima': 'Maxima',
                            'mel': 'MEL',
                            'mercury': 'Mercury',
                            'mipsasm': 'MIPS Assembly',
                            'mizar': 'Mizar',
                            'mojolicious': 'Mojolicious',
                            'monkey': 'Monkey',
                            'moonscript': 'MoonScript',
                            'n1ql': 'N1QL',
                            'nestedtext': 'NestedText',
                            'nginx': 'Nginx',
                            'nim': 'Nim',
                            'nix': 'Nix',
                            'node-repl': 'Node REPL',
                            'nsis': 'NSIS',
                            'objectivec': 'Objective-C',
                            'ocaml': 'OCaml',
                            'openscad': 'OpenSCAD',
                            'oxygene': 'Oxygene',
                            'parser3': 'Parser3',
                            'perl': 'Perl',
                            'pf': 'PF',
                            'pgsql': 'PGSQL',
                            'php': 'PHP',
                            'php-template': 'PHP Template',
                            'plaintext': 'PlainText',
                            'pony': 'Pony',
                            'powershell': 'PowerShell',
                            'processing': 'Processing',
                            'profile': 'Profile',
                            'prolog': 'Prolog',
                            'properties': 'Properties',
                            'protobuf': 'Protocol Buffers',
                            'puppet': 'Puppet',
                            'purebasic': 'PureBasic',
                            'python': 'Python',
                            'python-repl': 'Python REPL',
                            'q': 'Q',
                            'qml': 'QML',
                            'reasonml': 'ReasonML',
                            'rib': 'RIB',
                            'r': 'R',
                            'roboconf': 'RoboConf',
                            'routeros': 'RouterOS',
                            'rsl': 'RSL',
                            'ruby': 'Ruby',
                            'ruleslanguage': 'RulesLanguage',
                            'rust': 'Rust',
                            'sas': 'SAS',
                            'scala': 'Scala',
                            'scheme': 'Scheme',
                            'scilab': 'Scilab',
                            'scss': 'SCSS',
                            'shell': 'Shell',
                            'smali': 'Smali',
                            'smalltalk': 'Smalltalk',
                            'sml': 'SML',
                            'sqf': 'SQF',
                            'sql': 'SQL',
                            'stan': 'Stan',
                            'stata': 'Stata',
                            'step21': 'Step21',
                            'stylus': 'Stylus',
                            'subunit': 'Subunit',
                            'swift': 'Swift',
                            'taggerscript': 'TaggerScript',
                            'tap': 'TAP',
                            'tcl': 'TCL',
                            'thrift': 'Thrift',
                            'tp': 'TP',
                            'twig': 'Twig',
                            'typescript': 'TypeScript',
                            'vala': 'Vala',
                            'vbnet': 'VB.NET',
                            'vbscript-html': 'VBScript HTML',
                            'vbscript': 'VBScript',
                            'verilog': 'Verilog',
                            'vhdl': 'VHDL',
                            'vim': 'Vim',
                            'wasm': 'WASM',
                            'wren': 'Wren',
                            'x86asm': 'x86 Assembly',
                            'xl': 'XL',
                            'xml': 'XML',
                            'xquery': 'XQuery',
                            'yaml': 'YAML',
                            'zephir': 'Zephir'
                        };

                        CKEDITOR.replace( 'textedit', {
                            removePlugins: 'forms,exportpdf,iframe',
                            disallowedContent: 'iframe; frame; frameset; object; embed',
                            format_tags: 'p;h2;h3;h4;h5;h6;pre;address;div'
                        } );

                        document.querySelectorAll('#sitemenu a, #prevnextslider a, header a, a.addpglast').forEach(function(link) {

                            let href = link.getAttribute('href');

                            // пропускаем якоря и пустые ссылки
                            if (link.getAttribute('href').startsWith('#')) return;

                            if (href.includes('?')) {
                                href += '&leaveedit=1';
                            } else {
                                href += '?leaveedit=1';
                            }

                            link.setAttribute('href', href);

                            /*
                            link.addEventListener('click', event => {
                                // показываем диалог
                                if (!confirm('Вы действительно хотите покинуть редактор? Несохранённые данные БУДУТ УТЕРЯНЫ!')) {
                                // если 'Нет' → блокируем переход
                                event.preventDefault();
                                }
                                // если 'Да' → переход произойдет автоматически
                            });
                            */

                        });

                        

                        let editorDirty = true; // изначально считаем, что есть изменения

                        window.addEventListener('beforeunload', function (event) {
                            if (editorDirty) {
                                event.preventDefault();
                                event.returnValue = '';
                            }
                        });

                        // перебор всех форм на странице
                        document.querySelectorAll('form').forEach(form => {
                            form.addEventListener('submit', function () {
                                editorDirty = false;
                            });
                        });

                        document.getElementById('pagedelbutton').addEventListener('click', function (event) {
                            let ok = confirm('Удалить страницу? Вы уверены?');
                            if (!ok) {
                                event.preventDefault(); // остаёмся на странице
                            } else {
                                editorDirty = false; // при подтверждении сбрасываем флаг
                            }
                        });

                    });

                </script>";

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $bytepos = $query['?'.explode("&", $queryString)[0]] ?? 0;

        $dbMtime = filemtimeMy("DATABASE/DB/data.html");

        $content .= "<form method='post'>
        
        <input type='hidden' name='dbtimestamp' value='$dbMtime' />
        
        <fieldset><legend>Редактирование страницы:</legend>
        <p>Для включения <em>Содержания</em> используйте <em>Директиву</em> <strong>__TOC__</strong> вначале кода, на первой строке.</p>
        <p>Для Вставки <em>ВИДЕО YouTube</em> используйте шаблон <strong>{{youtube|VIDID|Ширина}}</strong> ; (Ширина является необязательным аттрибутом, и указывается в Процентах, без указания <strong>%</strong>).<br />Также поддерживаются <strong>{{dailymotion|VIDID|Ширина}}</strong> и <strong>{{vimeo|VIDID|Ширина}}</strong>.</p><p>Для вставки <em>Спойлера</em>, <em>Цитаты</em> или <em>Инфобокса</em> используйте шаблон из меню редактора <strong>\"Стили\"</strong>.</p>
        <p>Для добавления <strong>рамки</strong> изображению &mdash; укажите его <strong>Alt</strong></p>
        <p>Доступны также Шаблоны <strong>{{clear}}</strong> и <strong>{{nobr|ТЕКСТ}}</strong></p>
        <p><strong>{{download|DATABASE/fupload/Example.zip}}</strong>&nbsp;&mdash; Используйте это для вставки URL загрузок.</p>
        <p>Для вставки Тире используйте \" -- \" (без кавычек, с пробелами по краям)</p>
        <p><strong>{{lambda}} FROG!!!</strong></p>";

        $file = fopenOrDie("DATABASE/DB/data.html", "rb");
        
        fseekOrDie($file, $bytepos+40);
            
        $line = fgetsOrDie($file);
        // $line = preg_replace("/(<\/head[1-6]>)/", "$1\n", $line);
        
        fclose($file);

        $line = str_replace("<!2!>", "<!2!>\n", $line);

        $line = str_replace("<br!>", "\n", $line);

        $line2 = mb_substr($line, 0, 260); // 300 - 40

        preg_match('#\A<head([1-6])>(.+?)<\/head\1>#u', $line2, $pmatch);

        $htag   = $pmatch[1];
        $ptitle = $pmatch[2];

        $mainPageTitle = "Редактирование: ".$pmatch[2];

        $line = stripFirstLine($line);

        $hsel = "<select name='h'>
        <option value='1'>Уровень 1</option>
        <option value='2'>Уровень 2</option>
        <option value='3'>Уровень 3</option>
        <option value='4'>Уровень 4</option>
        <option value='5'>Уровень 5</option>
        <option value='6'>Уровень 6</option>
        </select>";

        $hsel = str_replace("<option value='".$htag."'>", "<option selected='selected' value='".$htag."'>", $hsel);

        // $line = protect_amp_entities_for_textarea($line);

        $line = escape_amp_txtarea($line);
        

        $content .= "

                    <input id='edpagetitle' type='text' name='title' value='".$ptitle."' />".$hsel.
                    "<textarea rows='9' name='textedit' id='textedit'>".$line."</textarea>";

        

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $queryBase = explode('&', $queryString)[0];

        $content .= "<div class='el-in-line'><input type='submit' value='💾 Отправить' />

            <a href='?".$queryBase."&amp;leaveedit=1'>Отменить ⬅️</a>
            <a href='?".$queryBase."&amp;pagedel=1' id='pagedelbutton'>❌ Удалить страницу</a>

            </div></fieldset></form>";

            // mylog("<em style='color:DarkOrange'>Открыто редактирование страницы. (".$_SESSION["username"].").</em>");
    }
}

function loginPage() {

    global $content, $mainPageTitle;

    $content = "<br /><br /><br /><form method='post' id='site-login-f'><fieldset>

    

    <legend>Данные входа:</legend><input type='text' name='username' id='username' value='' /><label for='username'>Имя пользователя</label><br />
    <input type='password' name='password' id='password' value='' /><label for='password'>Пароль</label><br />
    <input type='submit' value='☑️ Войти' /></fieldset></form><hr />
    <p><strong class='big'><a href='?registerg=1'>РЕГИСТРАЦИЯ</a></strong></p>";

    $mainPageTitle = "Страница входа";

}

function registerg() {

    global $content, $mainPageTitle;

    $content .= "<br /><br /><br /><form method='post' id='site-reg-f'><fieldset>

    

    <legend>Данные входа:</legend><input type='text' name='rusername' id='rusername' value='' /><label for='rusername'>Имя пользователя</label><br />
    <input type='password' name='rpassword1' id='rpassword1' value='' /><label for='rpassword1'>Пароль</label><br />
    <input type='password' name='rpassword2' id='rpassword2' value='' /><label for='rpassword2'>Проверка</label><br />
    <input type='hidden' name='registerp' value='1' />
    <input type='submit' value='☑️ Регистрация' /></fieldset></form>";

    $mainPageTitle = "Страница регистрации";

}

function addPage() {

    global $safeGet, $errmsg, $checkpermission, $idcache, $seoNumEncode, $ispageexist;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } elseif(isDbLocked() && !isLockedBy($_SESSION['username'])) {

        $errmsg = plocked();

    } elseif(!$ispageexist) {

        $errmsg = pnotfound();

        $errmsg .= "<p class='big'><strong>Опорная Страница</strong> не существует.</p>";

    } else {

        $seoPgIndex = 1;

        while(array_key_exists($seoPgIndex, $seoNumEncode)) {

            $seoPgIndex++;
        }

        $idKey = bin2hex(random_bytes(20)); // sha1(microtime().$ip.$userAgent);

        while(array_search($idKey, $idcache, true) !== false) {

            usleep(2000);
            $idKey = bin2hex(random_bytes(20)); // sha1(microtime().$ip.$userAgent);
        }

        $time = time();

        if($safeGet["addpage"] == "last") {

            $line = $idKey."<head1>New</head1>$seoPgIndex<!1!>".$time."<!2!><br!><br!><p>Чтобы показать заказчику эскизы сайта, нужно где-то найти тексты и картинки. Как правило, ни того, ни другого в момент показа эскизов у дизайнера нету.</p><br!><br!><p>Что же делает дизайнер? Он делает рыбу.</p><br!><br!><p>Рыбу можно вставлять, использовать, вешать, заливать, показывать, запихивать... Словом, с ней можно делать что угодно, лишь бы эскиз был максимально похож на готовую работу.</p><br!><br!><p>Если в качестве рыбных картинок использовать цветные прямоугольники, а вместо текста слова «тут будет текст тут будет текст тут будет текст тут будет текст», эскиз будет выглядеть неестественно.</p><br!><br!><p>Очень часто рыба становится частью готового сайта — так она нравится клиенту.</p><br!><br!><p>Рыба — это креатив чистой воды®</p><br!><br!><br!><p><img src='DATABASE/DB/ryba.jpg' alt='Рыба на подносе'></p><span class='big'><strong>Из жизни рыбы:</strong></span><br!><br!><p>Во время создания серии сайтов для Яндекса было решено придумать каждому проекту по слогану. Для проекта Почта.Яндекса был придуман рыбный слоган «Посылают все!», который успешно использовался и после открытия сайта.</p><br!><br!><p>На сайте компании «Арсеналъ» был сделан рыбный текст для графического заголовка: «Лучшее российское программное обеспечение». Эта фраза висела на сайте компании несколько лет и осталась даже при смене дизайна.</p><br!><br!><p>Для презентационного сайта о пластиковых карточках «VISA-Альфамобиль» вместо рубрик было написано: «во-первых», «во-вторых», «и, конечно», «а еще», «кроме того», «да что там». Все эти надписи сохранились в готовом сайте и висят там до сих пор.</p><br!><br!><p>А уж как часто рыбные картинки остаются жить в открытых сайтах… вы даже не представляете!</p>\n";

            dbprepApnd("DATABASE/DB/data.html");

            $filedest = openFileOrDie("DATABASE/DB/data.html.new." . getmypid(), 'ab');

            $filedest->fwriteOrDie($line);

            $filedest = null;
            

            if(!dbdone("DATABASE/DB/data.html", "БАЗА ДАННЫХ БЫЛА ИЗМЕНЕНА ИЛИ ЗАБЛОКИРОВАНА ВНЕШНИМ ПРОЦЕССОМ")) return false;

            mylog("<em style='color:DarkGreen'>В конец добавлена страница. (".$_SESSION["username"].").</em>");

            // $newpageaddr = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

            // $newpageaddr = seoLinkDecode($newpageaddr) + 1;

            $newpageaddr = seoNumGet() + 1;
        
            /// unlockByName($_SESSION['username'] ?? ""); ///

            /*
            $newpageaddr = $newpageaddr[0]."/New";

            refreshhandle(0, "?".$newpageaddr);
            */

            refreshhandle(0, "?nredir=".$newpageaddr);

        } else {

            $numlvl = mb_substr($safeGet["addpage"], 0, 21);

            $numlvl = explode("-", $numlvl);

            #$numlvl = array_slice($numlvl, 0, 2);

            $numlvl[0] = (int)($numlvl[0] ?? 0);

            $numlvl[1] = (int)($numlvl[1] ?? 0);

            $line = $idKey."<head".$numlvl[1].">New</head".$numlvl[1].">$seoPgIndex<!1!>$time<!2!><br!><br!><p>Чтобы показать заказчику эскизы сайта, нужно где-то найти тексты и картинки. Как правило, ни того, ни другого в момент показа эскизов у дизайнера нету.</p><br!><br!><p>Что же делает дизайнер? Он делает рыбу.</p><br!><br!><p>Рыбу можно вставлять, использовать, вешать, заливать, показывать, запихивать... Словом, с ней можно делать что угодно, лишь бы эскиз был максимально похож на готовую работу.</p><br!><br!><p>Если в качестве рыбных картинок использовать цветные прямоугольники, а вместо текста слова «тут будет текст тут будет текст тут будет текст тут будет текст», эскиз будет выглядеть неестественно.</p><br!><br!><p>Очень часто рыба становится частью готового сайта — так она нравится клиенту.</p><br!><br!><p>Рыба — это креатив чистой воды®</p><br!><br!><br!><p><img src='DATABASE/DB/ryba.jpg' alt='Рыба на подносе'></p><span class='big'><strong>Из жизни рыбы:</strong></span><br!><br!><p>Во время создания серии сайтов для Яндекса было решено придумать каждому проекту по слогану. Для проекта Почта.Яндекса был придуман рыбный слоган «Посылают все!», который успешно использовался и после открытия сайта.</p><br!><br!><p>На сайте компании «Арсеналъ» был сделан рыбный текст для графического заголовка: «Лучшее российское программное обеспечение». Эта фраза висела на сайте компании несколько лет и осталась даже при смене дизайна.</p><br!><br!><p>Для презентационного сайта о пластиковых карточках «VISA-Альфамобиль» вместо рубрик было написано: «во-первых», «во-вторых», «и, конечно», «а еще», «кроме того», «да что там». Все эти надписи сохранились в готовом сайте и висят там до сих пор.</p><br!><br!><p>А уж как часто рыбные картинки остаются жить в открытых сайтах… вы даже не представляете!</p>\n";

            if(sanCheckAdd($numlvl[0], $numlvl[1])) {

                dbprepApnd("DATABASE/DB/data.html");

                $filesource = openFileOrDie("DATABASE/DB/data.html.src." . getmypid(), 'rb');

                $filesource->seekOrDie($numlvl[0]);

                $firstChunkEnd = $filesource->ftell();

                $file = fopenOrDie("DATABASE/DB/data.html.new." . getmypid(), "r+b");
                ftruncateOrDie($file,$firstChunkEnd);
                fclose($file);

                $filedest = openFileOrDie("DATABASE/DB/data.html.new." . getmypid(), 'ab');

                $filedest->fwriteOrDie($line);

                while($line = $filesource->freadOrDie(256*1024)) {

                    $filedest->fwriteOrDie($line);
                }
                $filedest = null;
                    
                $filesource = null;
                

                if(!dbdone("DATABASE/DB/data.html", "БАЗА ДАННЫХ БЫЛА ИЗМЕНЕНА ИЛИ ЗАБЛОКИРОВАНА ВНЕШНИМ ПРОЦЕССОМ")) return false;

                mylog("<em style='color:DarkGreen'>В середине меню добавлена страница. (".$_SESSION["username"].").</em>");

                // $newpageaddr = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

                // $newpageaddr = seoLinkDecode($newpageaddr) + 1;

                $newpageaddr = seoNumGet() + 1;
        
                /// unlockByName($_SESSION['username'] ?? ""); ///

                /*
                if(isset($safeGet["newlevel"])) {
                    $newpageaddr[] = "New";
                } else {
                    $newpageaddr[array_key_last($newpageaddr)]  = "New";
                }

                $newpageaddr = join("/", $newpageaddr);

                refreshhandle(0, "?".$newpageaddr);
                */

                refreshhandle(0, "?nredir=".$newpageaddr);
            }
        }
    }
        
    unlockByName($_SESSION['username'] ?? "dummy");
}

function logout() {

    unlockByName($_SESSION['username'] ?? "dummy");

    if(!empty($_SESSION["username"])) {

        mylog("<strong style='color:DarkBlue'>Пользователь ".$_SESSION["username"]." вышел из системы.</strong>");
    }

    // 1. Очищаем данные сессии
    $_SESSION = [];

    // 2. Удаляем только сессионную cookie — строго с теми же параметрами, что были при создании
    if(ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '', time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }

    // 3. Уничтожаем саму сессию
    session_destroy();

    refreshhandle(0, "?", false);
}

function movePageDown() {

    global $safeGet, $errmsg, $checkpermission;
    
    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } elseif(isDbLocked() && !isLockedBy($_SESSION['username'])) {

        $errmsg = plocked();

    } else {

        $pg2mv = (int)$safeGet["pgmovedown"] - 1;

        if(sanCheckDown()) {

            dbprepApnd("DATABASE/DB/data.html");

            $filesource = openFileOrDie("DATABASE/DB/data.html.src." . getmypid(), 'rb');

            $filesource->seekOrDie($pg2mv);

            $firstChunkEnd = $filesource->ftell();

            $file = fopenOrDie("DATABASE/DB/data.html.new." . getmypid(), "r+b");
            ftruncateOrDie($file,$firstChunkEnd);
            fclose($file);

            $filedest = openFileOrDie("DATABASE/DB/data.html.new." . getmypid(), 'ab');

            $prevline = $filesource->fgetsOrDie();
            $nextline = $filesource->fgetsOrDie();

            $filedest->fwriteOrDie($nextline.$prevline);

            while($line = $filesource->freadOrDie(256*1024)) {

                $filedest->fwriteOrDie($line);
            }
            $filedest = null;
                
            $filesource = null;
            

            if(!dbdone("DATABASE/DB/data.html", "БАЗА ДАННЫХ БЫЛА ИЗМЕНЕНА ИЛИ ЗАБЛОКИРОВАНА ВНЕШНИМ ПРОЦЕССОМ")) return false;
        
            /// unlockByName($_SESSION['username'] ?? ""); ///

            mylog("<em style='color:DarkMagenta'>Страница опущена вниз. (".$_SESSION["username"].").</em>");

            refreshhandle(0, "?nredir=".((int)$safeGet["pgmovedown"] + 1));

        } else {

            refreshhandle(4, "?nredir=".(int)$safeGet["pgmovedown"], false);
        }
    }
        
    unlockByName($_SESSION['username'] ?? "dummy");
}

function movePageUp() {
    
    global $safeGet, $errmsg, $checkpermission;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } elseif(isDbLocked() && !isLockedBy($_SESSION['username'])) {

        $errmsg = plocked();

    } else {

        $pg2mv = (int)$safeGet["pgmoveup"] - 2;

        if(sanCheckUp()) {

            dbprepApnd("DATABASE/DB/data.html");

            $filesource = openFileOrDie("DATABASE/DB/data.html.src." . getmypid(), 'rb');

            $filesource->seekOrDie($pg2mv);

            $firstChunkEnd = $filesource->ftell();

            $file = fopenOrDie("DATABASE/DB/data.html.new." . getmypid(), "r+b");
            ftruncateOrDie($file,$firstChunkEnd);
            fclose($file);

            $filedest = openFileOrDie("DATABASE/DB/data.html.new." . getmypid(), 'ab');

            $prevline = $filesource->fgetsOrDie();
            $nextline = $filesource->fgetsOrDie();

            $filedest->fwriteOrDie($nextline.$prevline);

            while($line = $filesource->freadOrDie(256*1024)) {

                $filedest->fwriteOrDie($line);
            }
            $filedest = null;
                
            $filesource = null;
            

            if(!dbdone("DATABASE/DB/data.html", "БАЗА ДАННЫХ БЫЛА ИЗМЕНЕНА ИЛИ ЗАБЛОКИРОВАНА ВНЕШНИМ ПРОЦЕССОМ")) return false;
        
            /// unlockByName($_SESSION['username'] ?? ""); ///

            mylog("<em style='color:DarkMagenta'>Страница поднята вверх. (".$_SESSION["username"].").</em>");

            refreshhandle(0, "?nredir=".((int)$safeGet["pgmoveup"] - 1));

        } else {

            refreshhandle(4, "?nredir=".(int)$safeGet["pgmoveup"], false);
        }
    }
        
    unlockByName($_SESSION['username'] ?? "dummy");
}

function gallery() {

    global $content, $safeGet, $mainPageTitle, $sMobile, $head;

    $limit = 12;

    $files = glob('DATABASE/gallery/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];

    usort($files, fn($a, $b) => ((int)@filemtime($a)) <=> ((int)@filemtime($b)));

    $totimg = count($files);

    $galpg = (int)$safeGet["gallery"];

    // Рассчитываем количество страниц (с нумерацией с нуля)
    $pcount = ceil($totimg / $limit) - 1; // Поскольку нумерация с нуля, последняя страница будет pcount - 1

    // Обеспечиваем, что всегда есть хотя бы одна страница (индекс 0)
    $pcount = max(0, $pcount);

    // Ограничиваем галерею допустимыми значениями страниц
    if($galpg < 0 || $galpg > $pcount) {
        $galpg = $pcount; // максимальная страница
    }

    $content .= '<form method="post" enctype="multipart/form-data">
    
    <!-- <input type="hidden" name="fpgnum" value="-1" /> -->
    <input type="hidden" name="imgup" value="1" />
    <div class="el-in-line">
    <label for="fileToUpload"><em>ИЗОБРАЖЕНИЕ:</em></label>
    <input type="file" name="fileToUpload" id="fileToUpload" />
    <input type="submit" value="Загрузить изображение" name="submit" />
    </div>
    </form>';

    $content .= "<p class='big'><strong>Разрешённые Форматы:</strong> .gif, .png, .webp, .jpg, .jpeg</p><p>&nbsp;</p>";

    // Пагинация
    $pager = "<nav class='pager'>";

    // Генерируем ссылки на страницы
    for ($i = 0; $i <= $pcount; $i++) {
        if($galpg == $i) {
            $pager .= " <strong>".$i."</strong> "; // Текущая страница
        } else {
            $pager .= " <a rel='nofollow' href='?gallery=$i'>".$i."</a> "; // Ссылка на другую страницу
        }
    }

    $pager .= "</nav>";

    $content .= $pager;

    // Рассчитываем сдвиг для выбранной страницы
    $offset = $galpg * $limit;

    // Отбираем изображения для текущей страницы
    $selectedimg = array_slice($files, $offset, $limit);

    /*
    foreach ($selectedimg as $file) {
        $delimg = explode("/", $file)[2];
        $content .= "<p class='gallery-img'><img loading='lazy' src=\"".$file."\" alt='Картинка из галереи' /><br />".$file." <button onclick='copyToClipboard(\"".$file."\");'>🔗</button> <a rel='nofollow' href='?delimg=$delimg' class='imgdellink' onclick=\"return confirm('Вы уверены?');\">УДАЛИТЬ</a></p><hr />";
    }
    */

    ####################################################
    ####################################################
    ####################################################

    $cols = ($sMobile !== '') ? 1 : 3;

    $head .= "<style>
    
    table.gallery  { width: 100%; table-layout: fixed; border: none; border-spacing: 1rem; border-collapse: separate;}

    td.gallery-img, td.gallery-fill { position: relative; width: calc(100% / $cols); background: #CCC; border: none; margin: 0; padding: 0; }

    td.gallery-img {padding-bottom: 1.25em;}

    td.gallery-img button { display: inline-block; max-width: calc(100% - 2em); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; position: absolute; left: 0; bottom: 0;}

    td.gallery-img a {display: inline-block; position: absolute; right: 0; bottom: 0;}

    td.gallery-fill {text-align: center; font-size: 10em; color: #999;}

    </style>";

    if (!empty($selectedimg)) {
        $content .= "<table class='gallery'><tr>";

        $i = 0;
        $total = count($selectedimg);

        foreach ($selectedimg as $file) {
            $i++;
            $delimg = basename(str_replace('\\', '/', $file));

            $content .= "<td class='gallery-img'><img loading='lazy' src=\"".$file."\" alt='Картинка из галереи' title='$delimg' /><button onclick='copyToClipboard(\"".$file."\");'>🔗".$delimg."</button> <a rel='nofollow' href='?delimg=$delimg' class='imgdellink' onclick=\"return confirm('Вы уверены?');\">Уд.</a></td>";

            // открывать новую строку только если это НЕ последний элемент
            if ($i % $cols === 0 && $i < $total) {
                $content .= "</tr><tr>";
            }
        }

        // добить последнюю строку пустыми ячейками только если нужно
        if ($i % $cols !== 0) {
            $repeatCount = $cols - ($i % $cols);
            $content .= str_repeat("<td class='gallery-fill'><span>✕</span></td>", $repeatCount);
        }

        $content .= "</tr></table>";
    }

    ####################################################
    ####################################################
    ####################################################

    $mainPageTitle = "Галерея";

    $content .= $pager;

}

function dlFiles() {

    global $content, $safeGet, $mainPageTitle;

    $limit = 25;

    $exts = [
        // Текстовые и данные
        'txt', 'csv', 'tsv', 'json', 'xml', 'md', 'log', 'ini', 'yaml', 'yml',

        // Дополнительные безопасные текстовые форматы
        'conf', 'cfg', 'toml', 'properties',
        'rst', 'adoc', 'org',
        'diff', 'patch', 'nfo',
        'tex', 'bib',

        // Документы и офисные файлы
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'ods', 'odp', 'rtf',

        // Электронные книги
        'epub', 'mobi', 'azw', 'azw3',

        // Архивы и образы
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'lz', 'lzma', 'iso',

        // Игровые и мод-ресурсы
        'esp', 'esm',

        // Субтитры и прочее медиасопровождение (без видео и изображений)
        'srt', 'vtt', 'ass', 'ssa', 'sub',

        // Шрифты (если нужны для фронта, но не исполняемы)
        'ttf', 'otf', 'woff', 'woff2',

        // Базы данных/дампы
        'sql', 'sqlite', 'db', 'db3',
    ];
    
    $pattern = 'DATABASE/fupload/*.{'.implode(',', $exts).'}';
    $files = glob($pattern, GLOB_BRACE) ?: [];

    usort($files, fn($a, $b) => ((int)@filemtime($a)) <=> ((int)@filemtime($b)));

    $totimg = count($files);

    $galpg = (int)$safeGet["dlfiles"];

    // Рассчитываем количество страниц
    $pcount = ceil($totimg / $limit) - 1; // Общее количество страниц (с учетом 0 как первой страницы)

    // Обеспечиваем, что всегда есть хотя бы одна страница (индекс 0)
    $pcount = max(0, $pcount);

    // Ограничиваем текущую страницу допустимыми значениями
    if($galpg < 0 || $galpg > $pcount) {
        $galpg = $pcount;
    }

    $content .= '<form method="post" enctype="multipart/form-data">
        
        <input type="hidden" name="fuptrigger" value="1" />
        <!-- <input type="hidden" name="fpgnum" value="-1" /> -->
        <div class="el-in-line">
            <label for="upfiledl"><em>ФАЙЛ:</em></label>
            <input type="file" name="upfiledl" />
            <input type="submit" name="submit" value="Upload" />
        </div>
    </form>';

    // Пагинация
    $pager = "<nav class='pager'>";

    // Генерация ссылок на страницы
    for ($i = 0; $i <= $pcount; $i++) {
        if($galpg == $i) {
            $pager .= " <strong>".$i."</strong> "; // Текущая страница
        } else {
            $pager .= " <a rel='nofollow' href='?dlfiles=$i'>".$i."</a> "; // Ссылка на другую страницу
        }
    }

    $pager .= "</nav>";

    $content .= $pager;

    // Рассчитываем смещение для выбранной страницы
    $offset = $limit * $galpg;

    // Отбираем файлы для текущей страницы
    $selectedfile = array_slice($files, $offset, $limit);

    foreach($selectedfile as $file) {
        $delfile = explode("/", $file)[2];
        $content .= "<p class='gallery-img'>".$file." <button onclick='copyToClipboard(\"".$file."\");'>🔗</button> <a rel='nofollow' href='?delfile=$delfile' class='imgdellink' onclick=\"return confirm('Вы уверены?');\">УДАЛИТЬ</a></p><hr />";
    }

    $mainPageTitle = "Список файлов";

    $content .= $pager;

    $content .= "<p>&nbsp;</p><p class='big'><strong>Разрешённые Форматы:</strong></p><p>&nbsp;</p><pre>
    <strong>Текстовые и данные</strong>
    txt, csv, tsv, json, xml,
    md, log, ini, yaml, yml,

    <strong>Другие текстовые форматы</strong>
    conf, cfg, toml, properties,
    rst, adoc, org,
    diff, patch, nfo,
    tex, bib,

    <strong>Документы и офисные файлы</strong>
    pdf, doc, docx, ppt, pptx,
    xls, xlsx, odt, ods, odp, rtf,

    <strong>Электронные книги</strong>
    epub, mobi, azw, azw3,

    <strong>Архивы и образы</strong>
    zip, rar, 7z, tar, gz,
    bz2, xz, lz, lzma, iso,

    <strong>Игровые и мод-ресурсы</strong>
    esp, esm,

    <strong>Субтитры и прочее (без визуала)</strong>
    srt, vtt, ass, ssa, sub,

    <strong>Шрифты</strong>
    ttf, otf, woff, woff2,

    <strong>Базы данных/дампы</strong>
    sql, sqlite, db, db3</pre>";
}

function deletePage() {

    global $errmsg, $numcache, $checkpermission, $ispageexist;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } elseif(isDbLocked() && !isLockedBy($_SESSION['username'])) {

        $errmsg = plocked();

    } else {

        // $pagedel = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

        // $pagedel = seoLinkDecode($pagedel);

        if(!$ispageexist) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Неправильный адрес для удаления.</strong></p>";
            mylog("<em style='color:DarkBlue'>Неправильный адрес для удаления. (".$_SESSION["username"].").</em>");

        } else {

            $pagedel = seoNumGet();

            $numcache[] = 0;

            if($pagedel < 3) {

                $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Нельзя удалять корневую страницу и примыкающую к ней!</strong></p>";
                mylog("<em style='color:DarkBlue'>Нельзя удалять корневую страницу и примыкающую к ней! (".$_SESSION["username"].").</em>");

                refreshhandle(4, "?nredir=".$pagedel, false);

            } elseif($numcache[$pagedel-1] < $numcache[$pagedel] && $numcache[$pagedel-1] != $numcache[$pagedel-2] && $numcache[$pagedel] - $numcache[$pagedel-2] > 1) {

                $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>У <em>ВЛОЖЕННОЙ</em> страницы есть потомки.</strong></p>";
                mylog("<em style='color:DarkBlue'>У ВЛОЖЕННОЙ страницы есть потомки. (".$_SESSION["username"].").</em>");

                refreshhandle(4, "?nredir=".$pagedel, false);

            } else {

                dbprepApnd("DATABASE/DB/data.html");

                $filesource = openFileOrDie("DATABASE/DB/data.html.src." . getmypid(), 'rb');

                $filesource->seekOrDie($pagedel - 1);

                $firstChunkEnd = $filesource->ftell();

                $file = fopenOrDie("DATABASE/DB/data.html.new." . getmypid(), "r+b");
                ftruncateOrDie($file,$firstChunkEnd);
                fclose($file);

                $pageid = $filesource->freadOrDie(40);

                $filesource->fgetsOrDie();

                $filedest = openFileOrDie("DATABASE/DB/data.html.new." . getmypid(), 'ab');

                while($line = $filesource->freadOrDie(256*1024)) {

                    $filedest->fwriteOrDie($line);
                }

                $filedest = null;
                    
                $filesource = null;
                

                if(!dbdone("DATABASE/DB/data.html", "БАЗА ДАННЫХ БЫЛА ИЗМЕНЕНА ИЛИ ЗАБЛОКИРОВАНА ВНЕШНИМ ПРОЦЕССОМ")) return false;

                if(is_file("DATABASE/comments/".$pageid)) {

                    @unlink("DATABASE/comments/".$pageid);
                }

                if(is_file("DATABASE/comments/".$pageid.".bak")) {

                    @unlink("DATABASE/comments/".$pageid.".bak");
                }

                if(is_file("DATABASE/comments/".$pageid.".time")) {

                    @unlink("DATABASE/comments/".$pageid.".time");
                }

                if(is_file("DATABASE/comments/".$pageid.".pages-cache")) {

                    @unlink("DATABASE/comments/".$pageid.".pages-cache");
                }

                if(is_file("DATABASE/comments/".$pageid.".lock")) {

                    @unlink("DATABASE/comments/".$pageid.".lock");
                }

                //////////////////////

                if(is_file("DATABASE/comments/".$pageid.".count")) {

                    @unlink("DATABASE/comments/".$pageid.".count");
                }

                /*
                if(is_file("DATABASE/comm.count/".$pageid.".bak")) {

                    unlink("DATABASE/comm.count/".$pageid.".bak");
                }

                if(is_file("DATABASE/comm.count/".$pageid.".time")) {

                    unlink("DATABASE/comm.count/".$pageid.".time");
                }
                */
        
                /// unlockByName($_SESSION['username'] ?? ""); ///

                mylog("<strong style='color:DarkOrange'>Удалена страница ".$pagedel." (".$_SESSION["username"].").</strong>");

                refreshhandle(0, "?nredir=".($pagedel - 1));
            }
        }
    }
        
    unlockByName($_SESSION['username'] ?? "dummy");
}

function nredir() {

    global $pgaddrcache, $safeGet;

    $pageMenuNum2 = (int)$safeGet["nredir"];
    if( /* !is_int($pageMenuNum) || */ $pageMenuNum2 < 1 || $pageMenuNum2 > count($pgaddrcache)) {
        $pageMenuNum2 = (int)1;
    }

    refreshhandle(0, $pgaddrcache[$pageMenuNum2 - 1], false);
}

function commPgCntRecalc() {

    global $checkpermission, $idcache, $errmsg;

    /// $idcache[$numgen - 1];

    $numgen = seoNumGet();

    if($checkpermission < 2) {

        $errmsg = pforbidden();

    } elseif(!is_file("DATABASE/comments/" . $idcache[$numgen - 1] . ".pages-cache")) {

        $errmsg = pnotfound();

    } else {

        $pagesCache = "DATABASE/comments/" . $idcache[$numgen - 1] . ".pages-cache";
        if(is_file($pagesCache)) {
            @unlink($pagesCache);
        }

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $link = "?".explode("&", $queryString)[0] . "#comm-section";

        refreshhandle(0, $link, false);
    }
}

function gobyava() {

    global $content, $checkpermission, $errmsg;

    if($checkpermission < 3) {

        $errmsg = pforbidden();

    } else {

        $text = (string)@file_get_contents("DATABASE/obyava.txt");

        $text = str_ireplace("<textarea", "&lt;textarea", $text);
        $text = str_ireplace("</textarea", "&lt;/textarea", $text);
        $text = str_ireplace("textarea>", "textarea&gt;", $text);

        $text = escape_amp_txtarea($text);

        $content = "<form method='post'>
        <textarea name='pobyava' rows='15' maxlength='3000'>$text</textarea>
        <input type='submit' value='Сохранить' />
        </form>";
    }
}
