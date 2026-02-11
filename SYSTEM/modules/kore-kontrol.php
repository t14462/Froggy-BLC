<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################



$admpgctl = "";

#$idcache    = Array();
#$sitemaptxt = Array();
#$count = 1;
#$mydump = Array();
#$mydump2 = "";










$chTimeTOC       = filemtimeMy("DATABASE/DB/DB-TOC-Cache.txt");

$chTimeSEO       = filemtimeMy("DATABASE/DB/SEO-Cache.txt"   );

$chTimeSitemap1  = filemtimeMy("sitemap.txt");

$chTimeSitemap2  = filemtimeMy("sitemap.xml");

if( $chTimeTOC      !== $chTimeDB ||
    $chTimeSEO      !== $chTimeDB ||
    $chTimeSitemap1 !== $chTimeDB ||
    $chTimeSitemap2 !== $chTimeDB
    ) {

    refreshCaches();
}


$chTimeMenu = filemtimeMy("DATABASE/DB/MenuCache.txt");

if( $chTimeMenu < $chTimeDB && is_file("DATABASE/DB/MenuCache.txt")) {

    // rename("DATABASE/DB/MenuCache.txt", "DATABASE/DB/MenuCache.txt.del");

    unlink("DATABASE/DB/MenuCache.txt");
}





/*
if (file_exists("DATABASE/DB/data.html.lock")) {

    $querySafe = rawurlencode($_SERVER['QUERY_STRING']);
    header("Refresh: 1; url=?$querySafe");
    exit;
}
*/





if(    is_file("DATABASE/DB/DB-TOC-Cache.txt")
    /// && !is_file("DATABASE/DB/DB-TOC-Cache.txt.lock")
    && is_file("DATABASE/DB/MenuCache.txt")
    /// && !is_file("DATABASE/DB/MenuCache.txt.lock")
    && is_file("DATABASE/DB/SEO-Cache.txt")
    /// && !is_file("DATABASE/DB/SEO-Cache.txt.lock")
    && !$checkpermission /* && getFileOrDie('DATABASE/fingerprint.txt') == $url."\n".PHP_OS."\n".$servIP */) {

    $i = 0;

    $mydump2 = getFileOrDie('DATABASE/DB/DB-TOC-Cache.txt');

    $mydump2 = unserialize($mydump2, ['allowed_classes' => false]);

    $seoNumEncode = getFileOrDie('DATABASE/DB/SEO-Cache.txt');

    $seoNumEncode = unserialize($seoNumEncode, ['allowed_classes' => false]);



    while($i < count($mydump2)) {

        $addr = $mydump2[3 + $i];

    //  if($addr == '?'.explode("&", $_SERVER['QUERY_STRING'])[0] ) {

            /*
            $mydump[] = $numdump;    # 0

            $mydump[] = $query;      # 1 Arr

            $mydump[] = $byteId;     # 2

            $mydump[] = $iddump;     # 3

            $mydump[] = url().$addr; # 4

            $mydump[] = $txtarray;   # 5 Arr

            $mydump[] = $addr;       # 6

            $mydump[] = $linedump;   # 7

            $mydump[] = $count;      # 8

            */
            ##################################


            #$num      = $mydump2[0 + $i];


            #$byteId   = $mydump2[2 + $i]; #done

            #$txtarray = $mydump2[5 + $i];

            #$addr     = $mydump2[6 + $i];

    //      $line     = $mydump2[5 + $i];

            #$count    = $mydump2[8 + $i];

    //  }

        $numcache[]    = $mydump2[0 + $i]; #done

        $query[$addr]  = $mydump2[1 + $i];

        $idcache[]     = $mydump2[2 + $i]; #done

        $sitemaptxt[]  = $url.$mydump2[3 + $i]; #done

        $txtnamebuf[]  = $mydump2[4 + $i];

        $pgaddrcache[] = $mydump2[3 + $i];




        $i = $i + 6;
    }




    if(in_array('?'.explode("&", $_SERVER['QUERY_STRING'])[0], $pgaddrcache, true)) {
        $ispageexist = true;
    } else {
        $ispageexist = false;
    }




    $out = getFileOrDie('DATABASE/DB/MenuCache.txt');

    if($ispageexist) {

        $tmp = explode("&", $_SERVER['QUERY_STRING'])[0];

        $out = str_replace("<a href='?$tmp'", "<a href='?$tmp' class='active' aria-current='page'", $out);

        unset($tmp);

        // $out = preg_replace("/<a>(.+?)<\/a>/u", "<strong>$1</strong>", $out);
    }

    $menubar = $out;






} else {

    $file = openFileOrDie("DATABASE/DB/data.html", "rb");

    $file->setFlags(SplFileObject::READ_AHEAD); // Для лучшей производительности, позволяет "заглядывать" в файл

    $mCACHE = $out = "<ul id='sitemenu'>";


    // while(!$file->eof()) {

    foreach ($file as $line) {

        // $line = $file->fgets();
        $line2 = mb_substr($line, 0, 300);
        if(preg_grep('/\A[a-f0-9]{40}<head[1-6]/ui', explode("\n", $line2))) {


            $articleSize = mb_strlen($line, '8bit');
            $byteId = $file->ftell() - $articleSize;
            $prev = $num;
            $num = mb_substr($line2, 45, 1);

            $numcache[] = $num;

            $numdump    = $num;

            preg_match('#\A([a-f0-9]{40})<head([1-6])>(.+?)</head\2>(\d+)<!1!>(\d+)<!2!>#u', $line2, $line2);


            $iddump    = $idcache[] = $line2[1];

            $seoPgNum = $line2[4];

            $seoPagesTimes[] = $line2[5];

            // $seoPageTime = $line2[5];

            $line2     = $linedump = $linecache[] = $line2[3];

            #$linedump  = $line[3];

            $count++;

            $seoNumEncode[$seoPgNum] = $count;

            // $txtarray[0] = $count;
            $txtarray[0] = $seoPgNum;
            $txtarray[] = $line2;
            $addr = '?'.join("/",$txtarray);

            $addr = urlPrep($addr);

            $addr = seoMoveNum2End($addr);

            $query[$addr] = $byteId;

            $addpageafter = "";
            $pagemover = "";
            $addlasth1 = "";

            if($num > $prev) {





                if($checkpermission) {

                    $addpageafter = "<a href='$addr&addpage=$count-$num' title='Создать текущ. уровень'>+</a> ";

                    /// if($num < 6) {$addpageafter .= "<a href='$addr&addpage=$count-".($num+1)."&newlevel=1' title='Создать след. уровень'>N</a> ";}

                    if($num < 6) {$addpageafter .= "<a href='$addr&addpage=$count-".($num+1)."' title='Создать след. уровень'>N</a> ";}

                    $pagemover = "<a href='?pgmovedown=$count' title='Переместить вниз'>D</a> <a href='?pgmoveup=$count' title='Переместить вверх'>U</a> ";

                    $admpgctl = "<span class='admpgctl'>".$pagemover.$addpageafter."</span>";

                    $addlasth1 = " <a class='menu-icon big addpglast' style='clear: both' href='$addr&addpage=last' title='Добавить раздел'>+</a>";
                }



                $gCC = getCommCount($iddump);

                if($num == 1) {
                    $out .= "\n<li>".$admpgctl."<a href='".$addr."' itemprop='name'>".$line2."</a>".$gCC;

                    $mCACHE .= "\n<li><a href='".$addr."' itemprop='name'>".$line2."</a>".$gCC;
                } else {
                    $out .= "\n<ul><li>".$admpgctl."<a href='".$addr."' itemprop='name'>".$line2."</a>".$gCC;

                    $mCACHE .= "\n<ul><li><a href='".$addr."' itemprop='name'>".$line2."</a>".$gCC;
                }

            } else {

                $i = $prev - $num;

                #array_splice($txtarray, $num, $prev);
                array_splice($txtarray, $num, $prev+1);
                $txtarray[] = $line2;
                $addr = '?'.join("/",$txtarray);
                $addr = urlPrep($addr);

                $addr = seoMoveNum2End($addr);

                $query[$addr] = $byteId;






                if($checkpermission) {

                    $addpageafter = "<a href='$addr&addpage=$count-$num' title='Создать текущ. уровень'>+</a> ";

                    /// if($num < 6) {$addpageafter .= "<a href='$addr&addpage=$count-".($num+1)."&newlevel=1' title='Создать след. уровень'>N</a> ";}

                    if($num < 6) {$addpageafter .= "<a href='$addr&addpage=$count-".($num+1)."' title='Создать след. уровень'>N</a> ";}

                    $pagemover = "<a href='?pgmovedown=$count' title='Переместить вниз'>D</a> <a href='?pgmoveup=$count' title='Переместить вверх'>U</a> ";

                    $admpgctl = "<span class='admpgctl'>".$pagemover. $addpageafter."</span>";

                    $addlasth1 = " <a class='menu-icon big addpglast' style='clear: both' href='$addr&addpage=last' title='Добавить раздел'>+</a>";
                }






                switch ($i) {
                    case 0:
                        $out .= "</li>";
                        $mCACHE .= "</li>";
                        break;
                    case 1:
                        $out .= "</li></ul>";
                        $mCACHE .= "</li></ul>";
                        break;
                    case 2:
                        $out .= "</li></ul></li></ul>";
                        $mCACHE .= "</li></ul></li></ul>";
                        break;
                    case 3:
                        $out .= "</li></ul></li></ul></li></ul>";
                        $mCACHE .= "</li></ul></li></ul></li></ul>";
                        break;
                    case 4:
                        $out .= "</li></ul></li></ul></li></ul></li></ul>";
                        $mCACHE .= "</li></ul></li></ul></li></ul></li></ul>";
                        break;
                    case 5:
                        $out .= "</li></ul></li></ul></li></ul></li></ul></li></ul>";
                        $mCACHE .= "</li></ul></li></ul></li></ul></li></ul></li></ul>";
                        break;
                }


                $gCC = getCommCount($iddump);

                $out .= "\n<li>".$admpgctl."<a href='".$addr."' itemprop='name'>".$line2."</a>".$gCC;

                $mCACHE .= "\n<li><a href='".$addr."' itemprop='name'>".$line2."</a>".$gCC;
            }





            $sitemaptxt[] = $url.$addr;

            $txtnamebuf[] = $txtarray;

            $pgaddrcache[] = $addr;



            ########################################
            ########################################
            ########################################


            $mydump[] = $numdump;    # 0 done

            $mydump[] = $byteId;     # 1 #done

            $mydump[] = $iddump;     # 2 #done

            $mydump[] = $addr; # 3 #done

            $mydump[] = $txtarray;   # 4 Arr #done

            #$mydump[] = $addr;       # 5 #done

            $mydump[] = $linedump;   # 6 #done

            # $mydump[] = $count;      # 7 #done


            ########################################
            ########################################
            ########################################

        }
    }
    $file = null;

    $out .= str_repeat("</li></ul>", $num);

    $mCACHE .= str_repeat("</li></ul>", $num);


    ########################################
    ########################################
    ########################################




    if(!is_file("DATABASE/DB/DB-TOC-Cache.txt")) {

        $mydumptxt = serialize($mydump);

        dbprepCache("DATABASE/DB/DB-TOC-Cache.txt");

        putFileOrDie("DATABASE/DB/DB-TOC-Cache.txt.new." . getmypid(), $mydumptxt);

        if(dbdone("DATABASE/DB/DB-TOC-Cache.txt", "")) {

            touchMy("DATABASE/DB/DB-TOC-Cache.txt", $chTimeDB);
        }
    }




    if(!is_file("DATABASE/DB/SEO-Cache.txt")) {

        $seodumptxt = serialize($seoNumEncode);

        dbprepCache("DATABASE/DB/SEO-Cache.txt");

        putFileOrDie("DATABASE/DB/SEO-Cache.txt.new." . getmypid(), $seodumptxt);

        if(dbdone("DATABASE/DB/SEO-Cache.txt", "")) {

            touchMy("DATABASE/DB/SEO-Cache.txt", $chTimeDB);
        }
    }




    if(!is_file("DATABASE/DB/MenuCache.txt")) {

        $mCACHE = "<nav itemscope='itemscope' itemtype='https://schema.org/SiteNavigationElement'>".$mCACHE."</nav>";

        dbprepCache("DATABASE/DB/MenuCache.txt");

        putFileOrDie("DATABASE/DB/MenuCache.txt.new." . getmypid(), $mCACHE);

        dbdone("DATABASE/DB/MenuCache.txt", "");

            /// touchMy("DATABASE/DB/MenuCache.txt", $chTimeDB);
    }



    ########################################
    ########################################
    ########################################
    ########################################




    if(in_array('?'.explode("&", $_SERVER['QUERY_STRING'])[0], $pgaddrcache, true)) {
        $ispageexist = true;
    } else {
        $ispageexist = false;
    }




    if($ispageexist) {

        $tmp = explode("&", $_SERVER['QUERY_STRING'])[0];

        $out = str_replace("<a href='?$tmp'", "<a href='?$tmp' class='active' aria-current='page'", $out);

        unset($tmp);

        // $out = preg_replace("/<a>(.+?)<\/a>/u", "<strong>$1</strong>", $out);
    }

    $out = "<nav itemscope='itemscope' itemtype='https://schema.org/SiteNavigationElement'>".$out.$addlasth1."</nav>";

    $menubar = $out;

}



/*
function sitemapflush() {

    global $idcache, $url;

    $sitemaptxtvar = "";

    foreach($idcache as $line) {

        $sitemaptxtvar .= $url."?".$line."&permalink=1\n";
    }



    if(!dbprep("sitemap.txt", "")) return false;

    $filedest = fopenOrDie("sitemap.txt.new." . getmypid(), "r+");
        fwrite($filedest, $sitemaptxtvar);
        fclose($filedest);
    

    dbdone("sitemap.txt");

    mylog("<strong style='color:DarkMagenta'>Карта сайта пересоздана.</strong>");
}
*/







function sitemapflush() {

    global $sitemaptxt, $chTimeDB;

    $sitemaptxtvar = join("\n", $sitemaptxt);

    dbprepCache("sitemap.txt");

    /*
    $filedest = fopenOrDie("sitemap.txt.new." . getmypid(), "r+");
    fwriteOrDie($filedest, $sitemaptxtvar);
    fclose($filedest);
    */

    putFileOrDie("sitemap.txt.new." . getmypid(), $sitemaptxtvar);

    if(!dbdone("sitemap.txt", "")) return false;

    touchMy("sitemap.txt", $chTimeDB);

    // mylog("<strong style='color:DarkMagenta'>Карта сайта пересоздана.</strong>");
}







function sitemapflushXml() {

    global $sitemaptxt, $seoPagesTimes, $chTimeDB;

    if(empty($seoPagesTimes)) return false;

    // Начало XML 
    $sitemapxmlvar  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $sitemapxmlvar .= "<!-- <?xml-stylesheet type=\"text/xsl\" href=\"SYSTEM/modules/sitemap.xsl\"?> -->\n";
    $sitemapxmlvar .= "<?xml-stylesheet type=\"text/css\" href=\"SYSTEM/modules/sitemap.css\"?>\n";
    $sitemapxmlvar .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    // Перебор по индексам
    $count = count($sitemaptxt);
    for ($i = 0; $i < $count; $i++) {

        $url     = $sitemaptxt[$i];
        $lastmod = date('c', $seoPagesTimes[$i]);

        $sitemapxmlvar .= "  <url>\n";
        $sitemapxmlvar .= "    <loc>$url</loc>\n";
        $sitemapxmlvar .= "    <lastmod>$lastmod</lastmod>\n";
        $sitemapxmlvar .= "  </url>\n";
    }

    $sitemapxmlvar .= "</urlset>";


    // $sitemaptxtvar = join("\n", $sitemaptxt);

    dbprepCache("sitemap.xml");

    /*
    $filedest = fopenOrDie("sitemap.xml.new." . getmypid(), "r+");
    fwriteOrDie($filedest, $sitemapxmlvar);
    fclose($filedest);
    */
    
    putFileOrDie("sitemap.xml.new." . getmypid(), $sitemapxmlvar);

    if(!dbdone("sitemap.xml", "")) return false;

    touchMy("sitemap.xml", $chTimeDB);

    // mylog("<strong style='color:DarkMagenta'>Карта сайта XML пересоздана.</strong>");
}








// $pageMenuNum = (int)explode("/",$_SERVER['QUERY_STRING'])[0];

// $pageMenuNum = seoLinkDecode($pageMenuNum);

$pageMenuNum = seoNumGet();

if( /* !is_int($pageMenuNum) || */ $pageMenuNum < 1 || $pageMenuNum > count($sitemaptxt)) {
    $pageMenuNum = (int)1;
}



function prevnextpage() {

    global $sitemaptxt, $pageMenuNum, $ispageexist;

    $linkrelout = "";

    if(isset($sitemaptxt[$pageMenuNum-2]) && $ispageexist) {

        $linkrelout .= "<link rel='prev' href='".$sitemaptxt[$pageMenuNum-2]."' />\n";
    }

    if(isset($sitemaptxt[$pageMenuNum]) && $ispageexist) {

        $linkrelout .= "<link rel='next' href='".$sitemaptxt[$pageMenuNum]."' />\n";
    }

    return $linkrelout;
}



function prevnextslider() {

    global $pgaddrcache, $pageMenuNum;

    $linkrelout = "";

    $linkrelout .= "<nav aria-label='Переход между страницами Сайта' id='prevnextslider'>";

    if(isset($pgaddrcache[$pageMenuNum-2])) {

        $linkrelout .= "<a rel='prev' title='Предыдущая страница' href='".$pgaddrcache[$pageMenuNum-2]."'>⏪</a>\n";
    }

    if(isset($pgaddrcache[$pageMenuNum])) {

        $linkrelout .= "<a rel='next' title='Следующая страница' href='".$pgaddrcache[$pageMenuNum]."'>⏩</a>\n";
    }

    $linkrelout .= "</nav>";

    return $linkrelout;
}








##############################################################
##############################################################
##############################################################
##############################################################
##############################################################






if(explode("&", $_SERVER['QUERY_STRING'])[0] == "" || $_SERVER['QUERY_STRING'] == "leaveedit=1") {
    $mainlink = $sitemaptxt[0];

    header("Location: ".$mainlink,true,301);
    exit;

}










if(!is_file("sitemap.txt")) {

    sitemapflush();
}


if(!is_file("sitemap.xml")) {

    sitemapflushXml();
}







