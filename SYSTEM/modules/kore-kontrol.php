<?php

declare(strict_types=1);

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

$admpgctl = "";
$addlasth1 = "";
$numcache = $query = $idcache = $sitemaptxt = $txtnamebuf = $pgaddrcache = [];
$seoNumEncode = $mydump = [];

#$count = 1;
#$mydump2 = "";

$chTimeTOC       = filemtimeMy("DATABASE/DB/DB-TOC-Cache.txt");

$chTimeSEO       = filemtimeMy("DATABASE/DB/SEO-Cache.txt");

$chTimeSitemap1  = filemtimeMy("sitemap.txt");

$chTimeSitemap2  = filemtimeMy("sitemap.xml");

$canWriteNavigationCache = is_resource($navigationCacheLock ?? null);
$cacheIsCurrent = $chTimeTOC === $chTimeDB && $chTimeSEO === $chTimeDB
    && $chTimeSitemap1 === $chTimeDB && $chTimeSitemap2 === $chTimeDB;
if(!$cacheIsCurrent && $canWriteNavigationCache) {

    refreshCaches();
}

$menuCacheFile = 'DATABASE/DB/MenuCache.txt';
$menuCacheVersion = '<!-- MenuCache v2 -->';
$chTimeMenu = filemtimeMy($menuCacheFile);
$menuMtime = @filemtime($menuCacheFile);
$menuIsCurrent = $chTimeMenu >= $chTimeDB && $menuMtime !== false
    && time() - $menuMtime <= 10799
    && @file_get_contents($menuCacheFile, false, null, 0, strlen($menuCacheVersion)) === $menuCacheVersion;

if(!$menuIsCurrent && $canWriteNavigationCache && $menuMtime !== false) {
    @unlink($menuCacheFile);
}

/*
if (file_exists("DATABASE/DB/data.html.lock")) {

    $querySafe = rawurlencode($_SERVER['QUERY_STRING']);
    header("Refresh: 1; url=?$querySafe");
    exit;
}
*/

$menuCache = false;

function isValidNavigationCache(mixed $toc, mixed $seo): bool {
    if(!is_array($toc) || !array_is_list($toc) || count($toc) % 6 !== 0 || !is_array($seo)) {
        return false;
    }

    $expectedSeo = [];
    for($offset = 0, $page = 1, $size = count($toc); $offset < $size; $offset += 6, $page++) {
        $level = $toc[$offset];
        $position = $toc[$offset + 1];
        $id = $toc[$offset + 2];
        $address = $toc[$offset + 3];
        $path = $toc[$offset + 4];
        if((!is_int($level) && !is_string($level)) || !in_array((string)$level, ['1', '2', '3', '4', '5', '6'], true)
            || !is_int($position) || $position < 0
            || !is_string($id) || preg_match('/\A[a-f0-9]{40}\z/', $id) !== 1
            || !is_string($address) || strlen($address) < 2 || $address[0] !== '?'
            || !is_array($path) || !array_is_list($path) || count($path) < 2
            || !is_string($toc[$offset + 5])) {
            return false;
        }

        $seoNumber = $path[0];
        if((!is_int($seoNumber) && !is_string($seoNumber)) || !ctype_digit((string)$seoNumber)) {
            return false;
        }
        $expectedSeo[$seoNumber] = $page;
        foreach($path as $part) {
            if(!is_string($part) && !is_int($part)) {
                return false;
            }
        }
    }

    return $seo === $expectedSeo;
}

if($checkpermission < 2 && $cacheIsCurrent && $menuIsCurrent
    && is_file("DATABASE/DB/DB-TOC-Cache.txt") && is_file("DATABASE/DB/SEO-Cache.txt")) {
    $mydump2 = @unserialize((string)@file_get_contents('DATABASE/DB/DB-TOC-Cache.txt'), ['allowed_classes' => false]);
    $cachedSeo = @unserialize((string)@file_get_contents('DATABASE/DB/SEO-Cache.txt'), ['allowed_classes' => false]);

    if(isValidNavigationCache($mydump2, $cachedSeo)) {
        $menuCache = @file_get_contents($menuCacheFile);
    }

    if($menuCache === false || !str_starts_with($menuCache, $menuCacheVersion)) {
        $menuCache = false;
        if($canWriteNavigationCache) {
            refreshCaches();
        }
    }
}

if($menuCache !== false) {

    $i = 0;
    $seoNumEncode = $cachedSeo;

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

        $numcache[]    = (int)$mydump2[0 + $i];

        $query[$addr]  = $mydump2[1 + $i];

        $idcache[]     = $mydump2[2 + $i]; #done

        $sitemaptxt[]  = $url.$mydump2[3 + $i]; #done

        $txtnamebuf[]  = $mydump2[4 + $i];

        $pgaddrcache[] = $mydump2[3 + $i];

        $i = $i + 6;
    }

    $out = $menuCache;

} else {

    $file = openFileOrDie("DATABASE/DB/data.html", "rb");

    $file->setFlags(SplFileObject::READ_AHEAD); // Для лучшей производительности, позволяет "заглядывать" в файл

    $mCACHE = $out = "<ul id='sitemenu'>
    <li><a href='search.php'><strong>ПОИСК ПО САЙТУ 🔎…</strong></a></li>";

    // while(!$file->eof()) {

    foreach($file as $line) {

        // $line = $file->fgets();
        $header = mb_substr($line, 0, 300);
        if(preg_match('#\A([a-f0-9]{40})<head([1-6])>(.+?)</head\2>(\d+)<!1!>(\d+)<!2!>#u', $header, $line2) === 1) {

            $articleSize = mb_strlen($line, '8bit');
            $byteId = $file->ftell() - $articleSize;
            $prev = $num;
            $num = (int)$line2[2];

            $numcache[] = $num;

            $numdump    = $num;

            $iddump    = $idcache[] = $line2[1];

            $seoPgNum = $line2[4];

            $seoPagesTimes[] = (int)$line2[5];

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

                    $addpageafter = "<a href='$addr&amp;addpage=$count-$num&amp;pageid=$iddump&amp;csrf=$csrf' title='Создать текущ. уровень'>+</a> ";

                    /// if($num < 6) {$addpageafter .= "<a href='$addr&addpage=$count-".($num+1)."&newlevel=1' title='Создать след. уровень'>N</a> ";}

                    if($num < 6) {$addpageafter .= "<a href='$addr&amp;addpage=$count-".($num+1)."&amp;pageid=$iddump&amp;csrf=$csrf' title='Создать след. уровень'>N</a> ";}

                    $pagemover = "<a href='?pgmovedown=$iddump&amp;csrf=$csrf' title='Переместить вниз'>D</a> <a href='?pgmoveup=$iddump&amp;csrf=$csrf' title='Переместить вверх'>U</a> ";

                    $admpgctl = "<span class='admpgctl'>".$pagemover.$addpageafter."</span>";

                    $addlasth1 = " <a class='menu-icon big addpglast' style='clear: both' href='$addr&amp;addpage=last&amp;pageid=$iddump&amp;csrf=$csrf' title='Добавить раздел'>+</a>";
                }

                $gCC = getCommCount($iddump);

                if($num == 1) {
                    $out .= "\n<li>".$admpgctl."<a href='".$addr."' itemprop='url'><span itemprop='name'>".removeKwd($line2).extractKwd2($line2)."</span></a>".$gCC;

                    $mCACHE .= "\n<li><a href='".$addr."' itemprop='url'><span itemprop='name'>".removeKwd($line2).extractKwd2($line2)."</span></a>".$gCC;
                } else {
                    $out .= "\n<ul><li>".$admpgctl."<a href='".$addr."' itemprop='url'><span itemprop='name'>".removeKwd($line2).extractKwd2($line2)."</span></a>".$gCC;

                    $mCACHE .= "\n<ul><li><a href='".$addr."' itemprop='url'><span itemprop='name'>".removeKwd($line2).extractKwd2($line2)."</span></a>".$gCC;
                }

            } else {

                #array_splice($txtarray, $num, $prev);
                array_splice($txtarray, $num, $prev+1);
                $txtarray[] = $line2;
                $addr = '?'.join("/",$txtarray);
                $addr = urlPrep($addr);

                $addr = seoMoveNum2End($addr);

                $query[$addr] = $byteId;

                if($checkpermission) {

                    $addpageafter = "<a href='$addr&amp;addpage=$count-$num&amp;pageid=$iddump&amp;csrf=$csrf' title='Создать текущ. уровень'>+</a> ";

                    /// if($num < 6) {$addpageafter .= "<a href='$addr&addpage=$count-".($num+1)."&newlevel=1' title='Создать след. уровень'>N</a> ";}

                    if($num < 6) {$addpageafter .= "<a href='$addr&amp;addpage=$count-".($num+1)."&amp;pageid=$iddump&amp;csrf=$csrf' title='Создать след. уровень'>N</a> ";}

                    $pagemover = "<a href='?pgmovedown=$iddump&amp;csrf=$csrf' title='Переместить вниз'>D</a> <a href='?pgmoveup=$iddump&amp;csrf=$csrf' title='Переместить вверх'>U</a> ";

                    $admpgctl = "<span class='admpgctl'>".$pagemover. $addpageafter."</span>";

                    $addlasth1 = " <a class='menu-icon big addpglast' style='clear: both' href='$addr&amp;addpage=last&amp;pageid=$iddump&amp;csrf=$csrf' title='Добавить раздел'>+</a>";
                }

                $closingTags = str_repeat("</li></ul>", $prev - $num)."</li>";
                $out .= $closingTags;
                $mCACHE .= $closingTags;

                $gCC = getCommCount($iddump);

                $out .= "\n<li>".$admpgctl."<a href='".$addr."' itemprop='url'><span itemprop='name'>".removeKwd($line2).extractKwd2($line2)."</span></a>".$gCC;

                $mCACHE .= "\n<li><a href='".$addr."' itemprop='url'><span itemprop='name'>".removeKwd($line2).extractKwd2($line2)."</span></a>".$gCC;
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

    $closingTags = $num > 0 ? str_repeat("</li></ul>", $num) : "</ul>";
    $out .= $closingTags;
    $mCACHE .= $closingTags;

    ########################################
    ########################################
    ########################################

    if($canWriteNavigationCache && !is_file("DATABASE/DB/DB-TOC-Cache.txt")) {
        writeNavigationCache("DATABASE/DB/DB-TOC-Cache.txt", serialize($mydump), $chTimeDB);
    }

    if($canWriteNavigationCache && !is_file("DATABASE/DB/SEO-Cache.txt")) {
        writeNavigationCache("DATABASE/DB/SEO-Cache.txt", serialize($seoNumEncode), $chTimeDB);
    }

    if($canWriteNavigationCache && !is_file($menuCacheFile)) {

        $mCACHE = "<nav itemscope='itemscope' itemtype='https://schema.org/SiteNavigationElement'>".$mCACHE."</nav>";

        writeNavigationCache($menuCacheFile, $menuCacheVersion."\n".$mCACHE);
    }

    ########################################
    ########################################
    ########################################
    ########################################

    $out = "<nav itemscope='itemscope' itemtype='https://schema.org/SiteNavigationElement'>".$out.$addlasth1."</nav>";

}

$requestParts = explode('&', $_SERVER['QUERY_STRING'] ?? '', 2);
$pageAddress = '?' . $requestParts[0];

if(!isset($query[$pageAddress])) {
    $canonicalAddress = $pgaddrcache[seoNumGet() - 1] ?? null;
    // Адресная строка браузера может декодировать часть символов заголовка.
    if($canonicalAddress !== null
        && array_map('rawurldecode', explode('/', $pageAddress))
            === array_map('rawurldecode', explode('/', $canonicalAddress))) {
        $requestParts[0] = substr($canonicalAddress, 1);
        $_SERVER['QUERY_STRING'] = implode('&', $requestParts);
        $pageAddress = $canonicalAddress;
    }
}

$ispageexist = isset($query[$pageAddress]);
$menubar = $ispageexist
    ? str_replace("<a href='$pageAddress'", "<a href='$pageAddress' class='active' aria-current='page'", $out)
    : $out;

function sitemapflush() {

    global $sitemaptxt, $chTimeDB;

    $sitemaptxtvar = join("\n", $sitemaptxt) . "\n";

    return writeNavigationCache("sitemap.txt", $sitemaptxtvar, $chTimeDB);
}

function sitemapflushXml() {

    global $sitemaptxt, $seoPagesTimes, $chTimeDB;

    if($sitemaptxt !== [] && count($seoPagesTimes) !== count($sitemaptxt)) return false;

    // Начало XML 
    $sitemapxmlvar  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $sitemapxmlvar .= "<!-- <?xml-stylesheet type=\"text/xsl\" href=\"SYSTEM/modules/sitemap.xsl\"?> -->\n";
    $sitemapxmlvar .= "<?xml-stylesheet type=\"text/css\" href=\"SYSTEM/modules/sitemap.css\"?>\n";
    $sitemapxmlvar .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    // Перебор по индексам
    $count = count($sitemaptxt);
    for ($i = 0; $i < $count; $i++) {

        $url     = htmlspecialchars($sitemaptxt[$i], ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        $lastmod = date('c', $seoPagesTimes[$i]);

        $sitemapxmlvar .= "  <url>\n";
        $sitemapxmlvar .= "    <loc>$url</loc>\n";
        $sitemapxmlvar .= "    <lastmod>$lastmod</lastmod>\n";
        $sitemapxmlvar .= "  </url>\n";
    }

    $sitemapxmlvar .= "</urlset>\n";

    return writeNavigationCache("sitemap.xml", $sitemapxmlvar, $chTimeDB);
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

$queryString = $_SERVER['QUERY_STRING'] ?? '';
if(isset($sitemaptxt[0]) && (explode("&", $queryString)[0] === "" || $queryString === "leaveedit=1&csrf=$csrf")) {
    $mainlink = $sitemaptxt[0];

    header("Location: ".$mainlink,true,301);
    exit;

}

if($canWriteNavigationCache && !is_file("sitemap.txt")) {

    sitemapflush();
}

if($canWriteNavigationCache && !is_file("sitemap.xml")) {

    sitemapflushXml();
}
