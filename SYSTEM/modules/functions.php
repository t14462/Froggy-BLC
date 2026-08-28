<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

# require_once "SYSTEM/PHPLIB/htmlpurifier/library/HTMLPurifier.auto.php";

# require_once "SYSTEM/PHPLIB/simplehtmldom/simple_html_dom.php";

// require_once "SYSTEM/cred.php";

if(!is_file('SYSTEM/modules/null.txt') || !is_writable('SYSTEM/modules/null.txt')) {
    die("Файл SYSTEM/modules/null.txt не существует или недоступен для записи. Проверьте права доступа и владельца файла.");
}

if(!is_file('SYSTEM/modules/dummy.txt') || !is_writable('SYSTEM/modules/dummy.txt')) {
    die("Файл SYSTEM/modules/dummy.txt не существует или недоступен для записи. Проверьте права доступа и владельца файла.");
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$userAgent = substr($userAgent, 0, 512);

$ip = $_SERVER['REMOTE_ADDR'] ?? "0.0.0.0";

// Дополнительно можно добавить валидацию IP
if(!filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = "0.0.0.0"; // Неизвестный IP
}

$url = sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['SERVER_NAME'] ?? 'localhost',
    explode("?", safeRequestUri($_SERVER['REQUEST_URI'] ?? '/'))[0]
);

if(isset($_SESSION["username"]) && isset($_SESSION["userhash"])) {
    $username = $_SESSION["username"];
    $userhash = $_SESSION["userhash"];

    if(array_key_exists($username, $cred)) {
        $credParts = explode("<!!!>", $cred[$username], 2);
    }

    if(array_key_exists($username, $cred) && isset($credParts[1]) && hash_equals($userhash, hash('sha512', $credParts[1].$ip.$userAgent))) {
        $checkpermission = (int)$credParts[0];
    } else {
        $checkpermission = 0;
    }
} else {
    $checkpermission = 0;
}



/**
 * Сохраняет текущее microtime(true) или переданное значение в ПУТЬ_К_ФАЙЛУ.time
 *
 * @param string $file Путь к целевому файлу
 * @param float|null $customTime Если указан — используется он вместо текущего времени
 * @return bool true, если файл успешно записан
 */
function touchMy(string $file, ?float $customTime = null): bool {
    $timeFile = $file . '.time';
    $timeToWrite = $customTime ?? microtime(true);
    $touched = touch($file, (int)$timeToWrite);
    $written = file_put_contents($timeFile, sprintf('%.4f', $timeToWrite), LOCK_EX) !== false;

    return $touched && $written;
}

/**
 * Читает microtime из ПУТЬ_К_ФАЙЛУ.time
 * Если .time нет — берёт значение filemtime().
 * Если и целевого файла нет — возвращает 0.0 .
 */
function filemtimeMy(string $file): float {
    $timeFile = $file . '.time';

    if (is_file($timeFile)) {

        $locktmp = fopenOrDie($timeFile, 'rb');
        flock($locktmp, LOCK_SH);

        $contents = (string)stream_get_contents($locktmp);

        /// $contents = file_get_contents($timeFile);

        /// flock($locktmp, LOCK_UN);
        fclose($locktmp);

        return (float)sprintf('%.4f', $contents);

        /// return (float) trim(file_get_contents($timeFile));
    }

    return (float)@filemtime($file);
}

# DO NOT DELETE
$chTimeDB = filemtimeMy("DATABASE/DB/data.html");

class SafeSplFileObject extends SplFileObject {

    protected function getContext(): string {
        return $this->getRealPath() ?: 'неизвестный файл';
    }

    public function fwriteOrDie(string $data): void {

        $context = $this->getContext();

        $written = $this->fwrite($data);
        if($written === false || $written < strlen($data)) {
            die("fwriteOrDie: ошибка записи в поток ($context)");
        }
    }

    public function freadOrDie(int $length): string|false {

        if($this->eof()) {
            return false; // EOF — не ошибка
        }

        $context = $this->getContext();

        $data = $this->fread($length);
        if($data === false) {
            die("freadOrDie: ошибка чтения из потока ($context)");
        }

        return $data;
    }

    // ✅ безопасная обёртка, возвращает false на EOF
    public function fgetsOrDie(/* string $context = 'unknown' */): string|false {

        if($this->eof()) {
            return false;
        }

        $context = $this->getContext();

        $line = $this->fgets();
        if($line === false) {
            die("fgetsOrDie: ошибка чтения строки ($context)");
        }
        return $line;
    }

    public function seekOrDie(int $line): void {

        if($line < 0) {

            $context = $this->getContext();

            unlockByName($_SESSION['username'] ?? 'dummy');

            die("seekOrDie: отрицательный номер строки $line ($context)");
        }


        $this->seek($line);

        if(!$this->valid()) {

            $context = $this->getContext();

            unlockByName($_SESSION['username'] ?? 'dummy');

            die("seekOrDie: строка $line выходит за пределы файла ($context)");
        }
    }
}

function openFileOrDie(string $filename, string $mode = 'r'): SafeSplFileObject {
    try {
        return new SafeSplFileObject($filename, $mode);
    } catch (Throwable $e) {
        die("Ошибка при открытии файла '$filename': " . $e->getMessage() . ", проверьте права доступа к файлам и их владельца.");
    }
}

function fopenOrDie(string $filename, string $mode = 'r') {
    $handle = fopen($filename, $mode);
    if($handle === false) {
        die("Не удалось открыть файл: $filename, проверьте права доступа к файлам и их владельца.");
    }
    return $handle;
}

function getFileOrDie(string $filename): string {
    $content = file_get_contents($filename);
    if($content === false) {
        die("Не удалось прочитать файл: $filename, проверьте права доступа к файлам и их владельца.");
    }
    return $content;
}

function putFileOrDie(string $filename, string $data, int $flags = 0): int {
    $result = file_put_contents($filename, $data, $flags);
    if($result === false) {
        die("Не удалось записать в файл: $filename, проверьте права доступа к файлам и их владельца.");
    }
    return $result;
}

function fwriteOrDie($handle, string $data) {

    $meta = stream_get_meta_data($handle);
    $context = $meta['uri'] ?? 'unknown stream'; // /path/to/file.txt

    $written = fwrite($handle, $data);
    if($written === false || $written < strlen($data)) {
        die("fwriteOrDie: ошибка записи в поток ($context)");
    }
}

function freadOrDie($handle, int $length): string|false {

    $meta = stream_get_meta_data($handle);
    $context = $meta['uri'] ?? 'unknown stream'; // /path/to/file.txt

    if(feof($handle)) {
        return false;
    }

    $data = fread($handle, $length);
    if($data === false) {
        die("freadOrDie: ошибка чтения данных из потока ($context)");
    }

    return $data;
}

function fgetsOrDie($handle): string|false {

    $meta = stream_get_meta_data($handle);
    $context = $meta['uri'] ?? 'unknown stream'; // /path/to/file.txt

    if(feof($handle)) {
        return false;
    }

    $line = fgets($handle);
    if($line === false) {
        die("fgetsOrDie: ошибка чтения строки из потока ($context)");
    }

    return $line;
}

function ftruncateOrDie($handle, int $size) {

    $meta = stream_get_meta_data($handle);
    $context = $meta['uri'] ?? 'unknown stream'; // /path/to/file.txt

    if(!ftruncate($handle, $size)) {
        die("ftruncateOrDie: ошибка усечения файла ($context)");
    }
}

function fseekOrDie($handle, int $offset, int $whence = SEEK_SET): void {
    
    $meta = stream_get_meta_data($handle);
    $context = $meta['uri'] ?? 'unknown stream';

    if(fseek($handle, $offset, $whence) !== 0) {
        die("fseekOrDie: ошибка смещения курсора ($context, offset: $offset)");
    }
}

function set_cookie(string $name, string $value, int $expires): bool
{
    return setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}




/*
 * Вызывать после normalize_entities_my().
 * Заменён только шаг 1 исходной mb_superTrim(). Шаги 2–4 сохранены.
 *
 * Явный WhiteList печатных именованных сущностей HTML 4.01.
 * Источник: https://www.w3.org/TR/html401/sgml/entities.html
 * Из 252 имён исключены 8 пробельных/форматирующих:
 * nbsp, shy, ensp, emsp, thinsp, zwnj, lrm, rlm.
 *
 * Числовые записи: только кодовые точки разрешённых HTML4-знаков
 * и печатный ASCII (33–126), перечисленные в массиве буквально.
 * Прочие записи, включая сущности смайликов, удаляются.
 * HTML5-имена не разрешены: например, &heartsuit; и &apos;.
 * Апостроф допустим как &#39; или как настоящий символ Unicode/ASCII.
 * Настоящие Unicode-эмодзи обрабатываются исходными шагами 2–4.
 */
function mb_superTrim(string $text): string {

    // 1. WhiteList: разрешено только то, что явно перечислено.
    static $whiteList = [

        // Печатные именованные сущности HTML 4.01. Регистр значим.
        '&Aacute;' => true, '&aacute;' => true, '&Acirc;' => true, '&acirc;' => true, '&acute;' => true,
        '&AElig;' => true, '&aelig;' => true, '&Agrave;' => true, '&agrave;' => true, '&alefsym;' => true,
        '&Alpha;' => true, '&alpha;' => true, '&amp;' => true, '&and;' => true, '&ang;' => true,
        '&Aring;' => true, '&aring;' => true, '&asymp;' => true, '&Atilde;' => true, '&atilde;' => true,
        '&Auml;' => true, '&auml;' => true, '&bdquo;' => true, '&Beta;' => true, '&beta;' => true,
        '&brvbar;' => true, '&bull;' => true, '&cap;' => true, '&Ccedil;' => true, '&ccedil;' => true,
        '&cedil;' => true, '&cent;' => true, '&Chi;' => true, '&chi;' => true, '&circ;' => true,
        '&clubs;' => true, '&cong;' => true, '&copy;' => true, '&crarr;' => true, '&cup;' => true,
        '&curren;' => true, '&Dagger;' => true, '&dagger;' => true, '&dArr;' => true, '&darr;' => true,
        '&deg;' => true, '&Delta;' => true, '&delta;' => true, '&diams;' => true, '&divide;' => true,
        '&Eacute;' => true, '&eacute;' => true, '&Ecirc;' => true, '&ecirc;' => true, '&Egrave;' => true,
        '&egrave;' => true, '&empty;' => true, '&Epsilon;' => true, '&epsilon;' => true, '&equiv;' => true,
        '&Eta;' => true, '&eta;' => true, '&ETH;' => true, '&eth;' => true, '&Euml;' => true, '&euml;' => true,
        '&euro;' => true, '&exist;' => true, '&fnof;' => true, '&forall;' => true, '&frac12;' => true,
        '&frac14;' => true, '&frac34;' => true, '&frasl;' => true, '&Gamma;' => true, '&gamma;' => true,
        '&ge;' => true, '&gt;' => true, '&hArr;' => true, '&harr;' => true, '&hearts;' => true,
        '&hellip;' => true, '&Iacute;' => true, '&iacute;' => true, '&Icirc;' => true, '&icirc;' => true,
        '&iexcl;' => true, '&Igrave;' => true, '&igrave;' => true, '&image;' => true, '&infin;' => true,
        '&int;' => true, '&Iota;' => true, '&iota;' => true, '&iquest;' => true, '&isin;' => true,
        '&Iuml;' => true, '&iuml;' => true, '&Kappa;' => true, '&kappa;' => true, '&Lambda;' => true,
        '&lambda;' => true, '&lang;' => true, '&laquo;' => true, '&lArr;' => true, '&larr;' => true,
        '&lceil;' => true, '&ldquo;' => true, '&le;' => true, '&lfloor;' => true, '&lowast;' => true,
        '&loz;' => true, '&lsaquo;' => true, '&lsquo;' => true, '&lt;' => true, '&macr;' => true,
        '&mdash;' => true, '&micro;' => true, '&middot;' => true, '&minus;' => true, '&Mu;' => true,
        '&mu;' => true, '&nabla;' => true, '&ndash;' => true, '&ne;' => true, '&ni;' => true, '&not;' => true,
        '&notin;' => true, '&nsub;' => true, '&Ntilde;' => true, '&ntilde;' => true, '&Nu;' => true,
        '&nu;' => true, '&Oacute;' => true, '&oacute;' => true, '&Ocirc;' => true, '&ocirc;' => true,
        '&OElig;' => true, '&oelig;' => true, '&Ograve;' => true, '&ograve;' => true, '&oline;' => true,
        '&Omega;' => true, '&omega;' => true, '&Omicron;' => true, '&omicron;' => true, '&oplus;' => true,
        '&or;' => true, '&ordf;' => true, '&ordm;' => true, '&Oslash;' => true, '&oslash;' => true,
        '&Otilde;' => true, '&otilde;' => true, '&otimes;' => true, '&Ouml;' => true, '&ouml;' => true,
        '&para;' => true, '&part;' => true, '&permil;' => true, '&perp;' => true, '&Phi;' => true,
        '&phi;' => true, '&Pi;' => true, '&pi;' => true, '&piv;' => true, '&plusmn;' => true,
        '&pound;' => true, '&Prime;' => true, '&prime;' => true, '&prod;' => true, '&prop;' => true,
        '&Psi;' => true, '&psi;' => true, '&quot;' => true, '&radic;' => true, '&rang;' => true,
        '&raquo;' => true, '&rArr;' => true, '&rarr;' => true, '&rceil;' => true, '&rdquo;' => true,
        '&real;' => true, '&reg;' => true, '&rfloor;' => true, '&Rho;' => true, '&rho;' => true,
        '&rsaquo;' => true, '&rsquo;' => true, '&sbquo;' => true, '&Scaron;' => true, '&scaron;' => true,
        '&sdot;' => true, '&sect;' => true, '&Sigma;' => true, '&sigma;' => true, '&sigmaf;' => true,
        '&sim;' => true, '&spades;' => true, '&sub;' => true, '&sube;' => true, '&sum;' => true,
        '&sup1;' => true, '&sup2;' => true, '&sup3;' => true, '&sup;' => true, '&supe;' => true,
        '&szlig;' => true, '&Tau;' => true, '&tau;' => true, '&there4;' => true, '&Theta;' => true,
        '&theta;' => true, '&thetasym;' => true, '&THORN;' => true, '&thorn;' => true, '&tilde;' => true,
        '&times;' => true, '&trade;' => true, '&Uacute;' => true, '&uacute;' => true, '&uArr;' => true,
        '&uarr;' => true, '&Ucirc;' => true, '&ucirc;' => true, '&Ugrave;' => true, '&ugrave;' => true,
        '&uml;' => true, '&upsih;' => true, '&Upsilon;' => true, '&upsilon;' => true, '&Uuml;' => true,
        '&uuml;' => true, '&weierp;' => true, '&Xi;' => true, '&xi;' => true, '&Yacute;' => true,
        '&yacute;' => true, '&yen;' => true, '&Yuml;' => true, '&yuml;' => true, '&Zeta;' => true,
        '&zeta;' => true, '&zwj;' => true,

        // Десятичные записи этих же символов и печатного ASCII.
        '&#33;' => true, '&#34;' => true, '&#35;' => true, '&#36;' => true, '&#37;' => true, '&#38;' => true,
        '&#39;' => true, '&#40;' => true, '&#41;' => true, '&#42;' => true, '&#43;' => true, '&#44;' => true,
        '&#45;' => true, '&#46;' => true, '&#47;' => true, '&#48;' => true, '&#49;' => true, '&#50;' => true,
        '&#51;' => true, '&#52;' => true, '&#53;' => true, '&#54;' => true, '&#55;' => true, '&#56;' => true,
        '&#57;' => true, '&#58;' => true, '&#59;' => true, '&#60;' => true, '&#61;' => true, '&#62;' => true,
        '&#63;' => true, '&#64;' => true, '&#65;' => true, '&#66;' => true, '&#67;' => true, '&#68;' => true,
        '&#69;' => true, '&#70;' => true, '&#71;' => true, '&#72;' => true, '&#73;' => true, '&#74;' => true,
        '&#75;' => true, '&#76;' => true, '&#77;' => true, '&#78;' => true, '&#79;' => true, '&#80;' => true,
        '&#81;' => true, '&#82;' => true, '&#83;' => true, '&#84;' => true, '&#85;' => true, '&#86;' => true,
        '&#87;' => true, '&#88;' => true, '&#89;' => true, '&#90;' => true, '&#91;' => true, '&#92;' => true,
        '&#93;' => true, '&#94;' => true, '&#95;' => true, '&#96;' => true, '&#97;' => true, '&#98;' => true,
        '&#99;' => true, '&#100;' => true, '&#101;' => true, '&#102;' => true, '&#103;' => true,
        '&#104;' => true, '&#105;' => true, '&#106;' => true, '&#107;' => true, '&#108;' => true,
        '&#109;' => true, '&#110;' => true, '&#111;' => true, '&#112;' => true, '&#113;' => true,
        '&#114;' => true, '&#115;' => true, '&#116;' => true, '&#117;' => true, '&#118;' => true,
        '&#119;' => true, '&#120;' => true, '&#121;' => true, '&#122;' => true, '&#123;' => true,
        '&#124;' => true, '&#125;' => true, '&#126;' => true, '&#161;' => true, '&#162;' => true,
        '&#163;' => true, '&#164;' => true, '&#165;' => true, '&#166;' => true, '&#167;' => true,
        '&#168;' => true, '&#169;' => true, '&#170;' => true, '&#171;' => true, '&#172;' => true,
        '&#174;' => true, '&#175;' => true, '&#176;' => true, '&#177;' => true, '&#178;' => true,
        '&#179;' => true, '&#180;' => true, '&#181;' => true, '&#182;' => true, '&#183;' => true,
        '&#184;' => true, '&#185;' => true, '&#186;' => true, '&#187;' => true, '&#188;' => true,
        '&#189;' => true, '&#190;' => true, '&#191;' => true, '&#192;' => true, '&#193;' => true,
        '&#194;' => true, '&#195;' => true, '&#196;' => true, '&#197;' => true, '&#198;' => true,
        '&#199;' => true, '&#200;' => true, '&#201;' => true, '&#202;' => true, '&#203;' => true,
        '&#204;' => true, '&#205;' => true, '&#206;' => true, '&#207;' => true, '&#208;' => true,
        '&#209;' => true, '&#210;' => true, '&#211;' => true, '&#212;' => true, '&#213;' => true,
        '&#214;' => true, '&#215;' => true, '&#216;' => true, '&#217;' => true, '&#218;' => true,
        '&#219;' => true, '&#220;' => true, '&#221;' => true, '&#222;' => true, '&#223;' => true,
        '&#224;' => true, '&#225;' => true, '&#226;' => true, '&#227;' => true, '&#228;' => true,
        '&#229;' => true, '&#230;' => true, '&#231;' => true, '&#232;' => true, '&#233;' => true,
        '&#234;' => true, '&#235;' => true, '&#236;' => true, '&#237;' => true, '&#238;' => true,
        '&#239;' => true, '&#240;' => true, '&#241;' => true, '&#242;' => true, '&#243;' => true,
        '&#244;' => true, '&#245;' => true, '&#246;' => true, '&#247;' => true, '&#248;' => true,
        '&#249;' => true, '&#250;' => true, '&#251;' => true, '&#252;' => true, '&#253;' => true,
        '&#254;' => true, '&#255;' => true, '&#338;' => true, '&#339;' => true, '&#352;' => true,
        '&#353;' => true, '&#376;' => true, '&#402;' => true, '&#710;' => true, '&#732;' => true,
        '&#913;' => true, '&#914;' => true, '&#915;' => true, '&#916;' => true, '&#917;' => true,
        '&#918;' => true, '&#919;' => true, '&#920;' => true, '&#921;' => true, '&#922;' => true,
        '&#923;' => true, '&#924;' => true, '&#925;' => true, '&#926;' => true, '&#927;' => true,
        '&#928;' => true, '&#929;' => true, '&#931;' => true, '&#932;' => true, '&#933;' => true,
        '&#934;' => true, '&#935;' => true, '&#936;' => true, '&#937;' => true, '&#945;' => true,
        '&#946;' => true, '&#947;' => true, '&#948;' => true, '&#949;' => true, '&#950;' => true,
        '&#951;' => true, '&#952;' => true, '&#953;' => true, '&#954;' => true, '&#955;' => true,
        '&#956;' => true, '&#957;' => true, '&#958;' => true, '&#959;' => true, '&#960;' => true,
        '&#961;' => true, '&#962;' => true, '&#963;' => true, '&#964;' => true, '&#965;' => true,
        '&#966;' => true, '&#967;' => true, '&#968;' => true, '&#969;' => true, '&#977;' => true,
        '&#978;' => true, '&#982;' => true, '&#8211;' => true, '&#8212;' => true, '&#8216;' => true,
        '&#8217;' => true, '&#8218;' => true, '&#8220;' => true, '&#8221;' => true, '&#8222;' => true,
        '&#8224;' => true, '&#8225;' => true, '&#8226;' => true, '&#8230;' => true, '&#8240;' => true,
        '&#8242;' => true, '&#8243;' => true, '&#8249;' => true, '&#8250;' => true, '&#8254;' => true,
        '&#8260;' => true, '&#8364;' => true, '&#8465;' => true, '&#8472;' => true, '&#8476;' => true,
        '&#8482;' => true, '&#8501;' => true, '&#8592;' => true, '&#8593;' => true, '&#8594;' => true,
        '&#8595;' => true, '&#8596;' => true, '&#8629;' => true, '&#8656;' => true, '&#8657;' => true,
        '&#8658;' => true, '&#8659;' => true, '&#8660;' => true, '&#8704;' => true, '&#8706;' => true,
        '&#8707;' => true, '&#8709;' => true, '&#8711;' => true, '&#8712;' => true, '&#8713;' => true,
        '&#8715;' => true, '&#8719;' => true, '&#8721;' => true, '&#8722;' => true, '&#8727;' => true,
        '&#8730;' => true, '&#8733;' => true, '&#8734;' => true, '&#8736;' => true, '&#8743;' => true,
        '&#8744;' => true, '&#8745;' => true, '&#8746;' => true, '&#8747;' => true, '&#8756;' => true,
        '&#8764;' => true, '&#8773;' => true, '&#8776;' => true, '&#8800;' => true, '&#8801;' => true,
        '&#8804;' => true, '&#8805;' => true, '&#8834;' => true, '&#8835;' => true, '&#8836;' => true,
        '&#8838;' => true, '&#8839;' => true, '&#8853;' => true, '&#8855;' => true, '&#8869;' => true,
        '&#8901;' => true, '&#8968;' => true, '&#8969;' => true, '&#8970;' => true, '&#8971;' => true,
        '&#9001;' => true, '&#9002;' => true, '&#9674;' => true, '&#9824;' => true, '&#9827;' => true,
        '&#9829;' => true, '&#9830;' => true, '&#8205;' => true,
    ];

    $text = preg_replace_callback(
        '/&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);/i',
        static function ($m) use ($whiteList) {
            return isset($whiteList[$m[0]]) ? $m[0] : '';
        },
        $text
    );

    // 2. Удаляем управляющие и форматирующие символы, но оставляем ZWJ
    $text = preg_replace_callback('/[\p{Cc}\p{Cf}]/u', static function ($m) {
        $ch = $m[0];

        // ZWJ (U+200D) и другие — нужны для эмоджи типа 👩‍💻
        if ($ch === "\u{200D}" || $ch === "\u{FE0F}" || $ch === "\u{FE0E}") {
            return $ch;
        }

        // Остальное выкидываем
        return '';
    }, $text);

    // 3. Удаляем Unicode-пробелы по краям
    $text = preg_replace('/^[\p{Z}]+|[\p{Z}]+$/u', '', $text);

    // 4. Нормализуем все пробельные символы внутри
    return preg_replace('/[\p{Z}]+/u', ' ', $text);
}




/*
 * Вызывать после normalize_entities_my().
 * Заменён только шаг 1 исходной mb_superTrim(). Шаги 2–4 сохранены.
 *
 * Явный WhiteList печатных именованных сущностей HTML 4.01.
 * Источник: https://www.w3.org/TR/html401/sgml/entities.html
 * Из 252 имён исключены 7 пробельных/форматирующих:
 * shy, ensp, emsp, thinsp, zwnj, lrm, rlm.
 *
 * Числовые записи: только кодовые точки разрешённых HTML4-знаков
 * и печатный ASCII (33–126), перечисленные в массиве буквально.
 * Прочие записи, включая сущности смайликов, удаляются.
 * HTML5-имена не разрешены: например, &heartsuit; и &apos;.
 * Апостроф допустим как &#39; или как настоящий символ Unicode/ASCII.
 * Настоящие Unicode-эмодзи обрабатываются исходными шагами 2–4.
 */
function mb_softTrim(string $text): string {
    
    // Удаляем управляющие и форматирующие символы, но оставляем \n + ZWJ
    $text = preg_replace_callback('/[\p{Cc}\p{Cf}]/u', static function ($m) {
        $ch = $m[0];

        // \n оставляем
        if ($ch === "\n") {
            return "\n";
        }

        // ZWJ (U+200D) и другие — нужны для эмоджи типа 👩‍💻
        if ($ch === "\u{200D}" || $ch === "\u{FE0F}" || $ch === "\u{FE0E}") {
            return $ch;
        }

        // Остальное выкидываем
        return '';
    }, $text);
    

    // WhiteList: разрешено только то, что явно перечислено.
    static $whiteList = [

        // Печатные именованные сущности HTML 4.01. Регистр значим.
        '&Aacute;' => true, '&aacute;' => true, '&Acirc;' => true, '&acirc;' => true, '&acute;' => true,
        '&AElig;' => true, '&aelig;' => true, '&Agrave;' => true, '&agrave;' => true, '&alefsym;' => true,
        '&Alpha;' => true, '&alpha;' => true, '&amp;' => true, '&and;' => true, '&ang;' => true,
        '&Aring;' => true, '&aring;' => true, '&asymp;' => true, '&Atilde;' => true, '&atilde;' => true,
        '&Auml;' => true, '&auml;' => true, '&bdquo;' => true, '&Beta;' => true, '&beta;' => true,
        '&brvbar;' => true, '&bull;' => true, '&cap;' => true, '&Ccedil;' => true, '&ccedil;' => true,
        '&cedil;' => true, '&cent;' => true, '&Chi;' => true, '&chi;' => true, '&circ;' => true,
        '&clubs;' => true, '&cong;' => true, '&copy;' => true, '&crarr;' => true, '&cup;' => true,
        '&curren;' => true, '&Dagger;' => true, '&dagger;' => true, '&dArr;' => true, '&darr;' => true,
        '&deg;' => true, '&Delta;' => true, '&delta;' => true, '&diams;' => true, '&divide;' => true,
        '&Eacute;' => true, '&eacute;' => true, '&Ecirc;' => true, '&ecirc;' => true, '&Egrave;' => true,
        '&egrave;' => true, '&empty;' => true, '&Epsilon;' => true, '&epsilon;' => true, '&equiv;' => true,
        '&Eta;' => true, '&eta;' => true, '&ETH;' => true, '&eth;' => true, '&Euml;' => true, '&euml;' => true,
        '&euro;' => true, '&exist;' => true, '&fnof;' => true, '&forall;' => true, '&frac12;' => true,
        '&frac14;' => true, '&frac34;' => true, '&frasl;' => true, '&Gamma;' => true, '&gamma;' => true,
        '&ge;' => true, '&gt;' => true, '&hArr;' => true, '&harr;' => true, '&hearts;' => true,
        '&hellip;' => true, '&Iacute;' => true, '&iacute;' => true, '&Icirc;' => true, '&icirc;' => true,
        '&iexcl;' => true, '&Igrave;' => true, '&igrave;' => true, '&image;' => true, '&infin;' => true,
        '&int;' => true, '&Iota;' => true, '&iota;' => true, '&iquest;' => true, '&isin;' => true,
        '&Iuml;' => true, '&iuml;' => true, '&Kappa;' => true, '&kappa;' => true, '&Lambda;' => true,
        '&lambda;' => true, '&lang;' => true, '&laquo;' => true, '&lArr;' => true, '&larr;' => true,
        '&lceil;' => true, '&ldquo;' => true, '&le;' => true, '&lfloor;' => true, '&lowast;' => true,
        '&loz;' => true, '&lsaquo;' => true, '&lsquo;' => true, '&lt;' => true, '&macr;' => true,
        '&mdash;' => true, '&micro;' => true, '&middot;' => true, '&minus;' => true, '&Mu;' => true,
        '&mu;' => true, '&nabla;' => true, '&ndash;' => true, '&ne;' => true, '&ni;' => true, '&not;' => true,
        '&notin;' => true, '&nsub;' => true, '&Ntilde;' => true, '&ntilde;' => true, '&Nu;' => true,
        '&nu;' => true, '&Oacute;' => true, '&oacute;' => true, '&Ocirc;' => true, '&ocirc;' => true,
        '&OElig;' => true, '&oelig;' => true, '&Ograve;' => true, '&ograve;' => true, '&oline;' => true,
        '&Omega;' => true, '&omega;' => true, '&Omicron;' => true, '&omicron;' => true, '&oplus;' => true,
        '&or;' => true, '&ordf;' => true, '&ordm;' => true, '&Oslash;' => true, '&oslash;' => true,
        '&Otilde;' => true, '&otilde;' => true, '&otimes;' => true, '&Ouml;' => true, '&ouml;' => true,
        '&para;' => true, '&part;' => true, '&permil;' => true, '&perp;' => true, '&Phi;' => true,
        '&phi;' => true, '&Pi;' => true, '&pi;' => true, '&piv;' => true, '&plusmn;' => true,
        '&pound;' => true, '&Prime;' => true, '&prime;' => true, '&prod;' => true, '&prop;' => true,
        '&Psi;' => true, '&psi;' => true, '&quot;' => true, '&radic;' => true, '&rang;' => true,
        '&raquo;' => true, '&rArr;' => true, '&rarr;' => true, '&rceil;' => true, '&rdquo;' => true,
        '&real;' => true, '&reg;' => true, '&rfloor;' => true, '&Rho;' => true, '&rho;' => true,
        '&rsaquo;' => true, '&rsquo;' => true, '&sbquo;' => true, '&Scaron;' => true, '&scaron;' => true,
        '&sdot;' => true, '&sect;' => true, '&Sigma;' => true, '&sigma;' => true, '&sigmaf;' => true,
        '&sim;' => true, '&spades;' => true, '&sub;' => true, '&sube;' => true, '&sum;' => true,
        '&sup1;' => true, '&sup2;' => true, '&sup3;' => true, '&sup;' => true, '&supe;' => true,
        '&szlig;' => true, '&Tau;' => true, '&tau;' => true, '&there4;' => true, '&Theta;' => true,
        '&theta;' => true, '&thetasym;' => true, '&THORN;' => true, '&thorn;' => true, '&tilde;' => true,
        '&times;' => true, '&trade;' => true, '&Uacute;' => true, '&uacute;' => true, '&uArr;' => true,
        '&uarr;' => true, '&Ucirc;' => true, '&ucirc;' => true, '&Ugrave;' => true, '&ugrave;' => true,
        '&uml;' => true, '&upsih;' => true, '&Upsilon;' => true, '&upsilon;' => true, '&Uuml;' => true,
        '&uuml;' => true, '&weierp;' => true, '&Xi;' => true, '&xi;' => true, '&Yacute;' => true,
        '&yacute;' => true, '&yen;' => true, '&Yuml;' => true, '&yuml;' => true, '&Zeta;' => true,
        '&zeta;' => true, '&nbsp;' => true, '&zwj;' => true,

        // Десятичные записи этих же символов и печатного ASCII.
        '&#33;' => true, '&#34;' => true, '&#35;' => true, '&#36;' => true, '&#37;' => true, '&#38;' => true,
        '&#39;' => true, '&#40;' => true, '&#41;' => true, '&#42;' => true, '&#43;' => true, '&#44;' => true,
        '&#45;' => true, '&#46;' => true, '&#47;' => true, '&#48;' => true, '&#49;' => true, '&#50;' => true,
        '&#51;' => true, '&#52;' => true, '&#53;' => true, '&#54;' => true, '&#55;' => true, '&#56;' => true,
        '&#57;' => true, '&#58;' => true, '&#59;' => true, '&#60;' => true, '&#61;' => true, '&#62;' => true,
        '&#63;' => true, '&#64;' => true, '&#65;' => true, '&#66;' => true, '&#67;' => true, '&#68;' => true,
        '&#69;' => true, '&#70;' => true, '&#71;' => true, '&#72;' => true, '&#73;' => true, '&#74;' => true,
        '&#75;' => true, '&#76;' => true, '&#77;' => true, '&#78;' => true, '&#79;' => true, '&#80;' => true,
        '&#81;' => true, '&#82;' => true, '&#83;' => true, '&#84;' => true, '&#85;' => true, '&#86;' => true,
        '&#87;' => true, '&#88;' => true, '&#89;' => true, '&#90;' => true, '&#91;' => true, '&#92;' => true,
        '&#93;' => true, '&#94;' => true, '&#95;' => true, '&#96;' => true, '&#97;' => true, '&#98;' => true,
        '&#99;' => true, '&#100;' => true, '&#101;' => true, '&#102;' => true, '&#103;' => true,
        '&#104;' => true, '&#105;' => true, '&#106;' => true, '&#107;' => true, '&#108;' => true,
        '&#109;' => true, '&#110;' => true, '&#111;' => true, '&#112;' => true, '&#113;' => true,
        '&#114;' => true, '&#115;' => true, '&#116;' => true, '&#117;' => true, '&#118;' => true,
        '&#119;' => true, '&#120;' => true, '&#121;' => true, '&#122;' => true, '&#123;' => true,
        '&#124;' => true, '&#125;' => true, '&#126;' => true, '&#161;' => true, '&#162;' => true,
        '&#163;' => true, '&#164;' => true, '&#165;' => true, '&#166;' => true, '&#167;' => true,
        '&#168;' => true, '&#169;' => true, '&#170;' => true, '&#171;' => true, '&#172;' => true,
        '&#174;' => true, '&#175;' => true, '&#176;' => true, '&#177;' => true, '&#178;' => true,
        '&#179;' => true, '&#180;' => true, '&#181;' => true, '&#182;' => true, '&#183;' => true,
        '&#184;' => true, '&#185;' => true, '&#186;' => true, '&#187;' => true, '&#188;' => true,
        '&#189;' => true, '&#190;' => true, '&#191;' => true, '&#192;' => true, '&#193;' => true,
        '&#194;' => true, '&#195;' => true, '&#196;' => true, '&#197;' => true, '&#198;' => true,
        '&#199;' => true, '&#200;' => true, '&#201;' => true, '&#202;' => true, '&#203;' => true,
        '&#204;' => true, '&#205;' => true, '&#206;' => true, '&#207;' => true, '&#208;' => true,
        '&#209;' => true, '&#210;' => true, '&#211;' => true, '&#212;' => true, '&#213;' => true,
        '&#214;' => true, '&#215;' => true, '&#216;' => true, '&#217;' => true, '&#218;' => true,
        '&#219;' => true, '&#220;' => true, '&#221;' => true, '&#222;' => true, '&#223;' => true,
        '&#224;' => true, '&#225;' => true, '&#226;' => true, '&#227;' => true, '&#228;' => true,
        '&#229;' => true, '&#230;' => true, '&#231;' => true, '&#232;' => true, '&#233;' => true,
        '&#234;' => true, '&#235;' => true, '&#236;' => true, '&#237;' => true, '&#238;' => true,
        '&#239;' => true, '&#240;' => true, '&#241;' => true, '&#242;' => true, '&#243;' => true,
        '&#244;' => true, '&#245;' => true, '&#246;' => true, '&#247;' => true, '&#248;' => true,
        '&#249;' => true, '&#250;' => true, '&#251;' => true, '&#252;' => true, '&#253;' => true,
        '&#254;' => true, '&#255;' => true, '&#338;' => true, '&#339;' => true, '&#352;' => true,
        '&#353;' => true, '&#376;' => true, '&#402;' => true, '&#710;' => true, '&#732;' => true,
        '&#913;' => true, '&#914;' => true, '&#915;' => true, '&#916;' => true, '&#917;' => true,
        '&#918;' => true, '&#919;' => true, '&#920;' => true, '&#921;' => true, '&#922;' => true,
        '&#923;' => true, '&#924;' => true, '&#925;' => true, '&#926;' => true, '&#927;' => true,
        '&#928;' => true, '&#929;' => true, '&#931;' => true, '&#932;' => true, '&#933;' => true,
        '&#934;' => true, '&#935;' => true, '&#936;' => true, '&#937;' => true, '&#945;' => true,
        '&#946;' => true, '&#947;' => true, '&#948;' => true, '&#949;' => true, '&#950;' => true,
        '&#951;' => true, '&#952;' => true, '&#953;' => true, '&#954;' => true, '&#955;' => true,
        '&#956;' => true, '&#957;' => true, '&#958;' => true, '&#959;' => true, '&#960;' => true,
        '&#961;' => true, '&#962;' => true, '&#963;' => true, '&#964;' => true, '&#965;' => true,
        '&#966;' => true, '&#967;' => true, '&#968;' => true, '&#969;' => true, '&#977;' => true,
        '&#978;' => true, '&#982;' => true, '&#8211;' => true, '&#8212;' => true, '&#8216;' => true,
        '&#8217;' => true, '&#8218;' => true, '&#8220;' => true, '&#8221;' => true, '&#8222;' => true,
        '&#8224;' => true, '&#8225;' => true, '&#8226;' => true, '&#8230;' => true, '&#8240;' => true,
        '&#8242;' => true, '&#8243;' => true, '&#8249;' => true, '&#8250;' => true, '&#8254;' => true,
        '&#8260;' => true, '&#8364;' => true, '&#8465;' => true, '&#8472;' => true, '&#8476;' => true,
        '&#8482;' => true, '&#8501;' => true, '&#8592;' => true, '&#8593;' => true, '&#8594;' => true,
        '&#8595;' => true, '&#8596;' => true, '&#8629;' => true, '&#8656;' => true, '&#8657;' => true,
        '&#8658;' => true, '&#8659;' => true, '&#8660;' => true, '&#8704;' => true, '&#8706;' => true,
        '&#8707;' => true, '&#8709;' => true, '&#8711;' => true, '&#8712;' => true, '&#8713;' => true,
        '&#8715;' => true, '&#8719;' => true, '&#8721;' => true, '&#8722;' => true, '&#8727;' => true,
        '&#8730;' => true, '&#8733;' => true, '&#8734;' => true, '&#8736;' => true, '&#8743;' => true,
        '&#8744;' => true, '&#8745;' => true, '&#8746;' => true, '&#8747;' => true, '&#8756;' => true,
        '&#8764;' => true, '&#8773;' => true, '&#8776;' => true, '&#8800;' => true, '&#8801;' => true,
        '&#8804;' => true, '&#8805;' => true, '&#8834;' => true, '&#8835;' => true, '&#8836;' => true,
        '&#8838;' => true, '&#8839;' => true, '&#8853;' => true, '&#8855;' => true, '&#8869;' => true,
        '&#8901;' => true, '&#8968;' => true, '&#8969;' => true, '&#8970;' => true, '&#8971;' => true,
        '&#9001;' => true, '&#9002;' => true, '&#9674;' => true, '&#9824;' => true, '&#9827;' => true,
        '&#9829;' => true, '&#9830;' => true, '&#160;' => true, '&#8205;' => true,
    ];

    $text = preg_replace_callback(
        '/&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);/i',
        static function ($m) use ($whiteList) {
            return isset($whiteList[$m[0]]) ? $m[0] : '';
        },
        $text
    );

    
    // Удаляем все пробельные и "невидимые" символы по краям, включая все Unicode-переносы
    $text = preg_replace('/^(?:\p{Z}|\R)+|(?:\p{Z}|\R)+$/u', '', $text);

    return $text;
}

// Регулярное выражение для замены шаблона {{youtube|ID|width}}
$patternYT = '/\{\{youtube\|([a-zA-Z0-9_-]+)(?:\|(\d+))?\}\}/';
$replacementYT = static function ($matches) {

    global $sMobile;

    $videoId = $matches[1] ?? "";

    // Задаем ширину iframe, если она указана, иначе по умолчанию 100%
    $width = (int)($matches[2] ?? 0);

    // Если ширина задана
    if($width > 32 && $width < 66 && $sMobile !== "-mobile") {
        return "<div class='vid-wrapper' style='width: {$width}%; float: right; clear: right;'><iframe src='https://www.youtube.com/embed/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    } else {
        // Если ширина не в процентах, возвращаем адаптивный вариант
        return "<div class='vid-wrapper' style='clear: both;'><iframe src='https://www.youtube.com/embed/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    }
};

// Регулярное выражение для замены шаблона {{vimeo|ID|width}}
$patternVimeo = '/\{\{vimeo\|([0-9]+)(?:\|(\d+))?\}\}/'; // ID в Vimeo всегда числовое
$replacementVimeo = static function ($matches) {

    global $sMobile;

    $videoId = $matches[1] ?? "";

    // Задаем ширину iframe, если она указана, иначе по умолчанию 100%
    $width = (int)($matches[2] ?? 0);

    // Если ширина задана
    if($width > 32 && $width < 66 && $sMobile !== "-mobile") {
        return "<div class='vid-wrapper' style='width: {$width}%; float: right; clear: right;'><iframe src='https://player.vimeo.com/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    } else {
        // Если ширина не в процентах, возвращаем адаптивный вариант
        return "<div class='vid-wrapper' style='clear: both;'><iframe src='https://player.vimeo.com/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    }
};

// Регулярное выражение для замены шаблона {{dailymotion|ID|width}}
$patternDM = '/\{\{dailymotion\|([a-zA-Z0-9]+)(?:\|(\d+))?\}\}/';
$replacementDM = static function ($matches) {

    global $sMobile;

    $videoId = $matches[1] ?? "";

    // Задаем ширину iframe, если она указана, иначе по умолчанию 100%
    $width = (int)($matches[2] ?? 0);

    // Если ширина задана
    if($width > 32 && $width < 66 && $sMobile !== "-mobile") {
        return "<div class='vid-wrapper' style='width: {$width}%; float: right; clear: right;'><iframe src='https://www.dailymotion.com/embed/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    } else {
        // Если ширина не в процентах, возвращаем адаптивный вариант
        return "<div class='vid-wrapper' style='clear: both;'><iframe src='https://www.dailymotion.com/embed/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    }
};

// {{download|FILE}}
$patternDLCNT = '/\{\{download\|([^\}\r\n]+?)\}\}/u';
$replacementDLCNT = static function ($m) {

    $raw = trim($m[1] ?? '');
    // последний сегмент пути, без подкаталогов + ваша фильтрация
    /// $file = basename(str_replace('\\', '/', $raw));
    $file = filter_filename($raw);

    if (empty($file)) {
        return "<div style='background:#F00; color:#FFF; font-size: 3rem;'>ERR: bad file</div>";
    }

    $cntTmp = filter_filename($file . ".dlcnt");

    $pathFile = "DATABASE/fupload/$file";
    $pathCnt  = "DATABASE/dl.count/$cntTmp";

    if (is_file($pathFile)) {

        $dlSize = ((int)@filesize($pathFile)) / 1024;

        $dlSize = round($dlSize, 2);

        $dlcnt = 0;
        if (is_file($pathCnt) && is_readable($pathCnt)) {

            $ftmp = fopenOrDie($pathCnt, 'rb');
            flock($ftmp, LOCK_SH);

            $dlcnt = (int)(string)stream_get_contents($ftmp);

            /// flock($ftmp, LOCK_UN);
            fclose($ftmp);

            /*
            if ($val !== '' && ctype_digit($val)) {
                $dlcnt = (int)$val;
            }
            */
        }
        $dlcntFmt = number_format($dlcnt, 0, ',', ' ');

        $targetIframe = urlPrep2($file);

        $safeName = htmlspecialchars($file, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8');

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $inlineExt = [
        'adoc'       => 1,
        'ass'        => 1,
        'bib'        => 1,
        'cfg'        => 1,
        'conf'       => 1,
        'csv'        => 1,
        'diff'       => 1,
        'ini'        => 1,
        'json'       => 1,
        'log'        => 1,
        'md'         => 1,
        'nfo'        => 1,
        'org'        => 1,
        'patch'      => 1,
        'pdf'        => 1,
        'properties' => 1,
        'rst'        => 1,
        'srt'        => 1,
        'ssa'        => 1,
        'sub'        => 1,
        'tex'        => 1,
        'toml'       => 1,
        'tsv'        => 1,
        'txt'        => 1,
        'vtt'        => 1,
        'xml'        => 1,
        'yaml'       => 1,
        'yml'        => 1,
        ];

        $href = 'SYSTEM/modules/download.php?file=' . rawurlencode($file);
        $dlTable = "<table class='dl-tpl'><tbody>
        <tr><td colspan='2'>";
        
        if(isset($inlineExt[$ext])) {

            $dlTable .= "<a href=\"{$href}\" onclick=\"DlIncrement('DLINC-$targetIframe')\" target='_blank'>Скачать <strong>{$safeName}</strong></a>";

        } else {

            $dlTable .= "<a href=\"{$href}\" onclick=\"DlIncrement('DLINC-$targetIframe')\" target='auxFrame-$targetIframe'>Скачать <strong>{$safeName}</strong></a>";
        }
        
        $dlTable .= "</td></tr>

        <tr><td class='a3'>Размер на диске: </td><td class='a4'>{$dlSize} КиБ</td></tr>

        <tr><td class='a3'>Всего загрузок: </td><td class='a4'><span id='DLINC-$targetIframe'>{$dlcntFmt}</span><iframe name='auxFrame-$targetIframe' width='15' height='15' style='background: transparent'></iframe></td></tr>

        </tbody></table>";

        return $dlTable;
    } else {

        return "<div style='background:#F00; color:#FFF; font-size: 3rem;'>ERR: File 404</div>";
    }
};

// Применение:
// $html = preg_replace_callback($patternDLCNT, $replacementDLCNT, $html);

function emojiToHtmlEntities(string $string): string {
    return preg_replace_callback('/\X/u', static function ($m) {
        $g = $m[0];
        if (preg_match('/(?:\p{So}|\p{Sk}|\x{20E3}|\x{FE0F})/u', $g)) {
            // кодируем весь кластер целиком
            return mb_encode_numericentity($g, [0x0, 0x10FFFF, 0, 0x10FFFF], 'UTF-8');
        }
        return $g;
    }, $string);
}

function generateSalt($username, $password) {
    // Генерируем битовую маску соли на основе хэша имени пользователя
    $saltMask = hex2bin(hash('sha256', $username));
    $saltMask = shuffleString($saltMask, sha512FoldXor($password));
    // Генерируем исходный хэш пароля
    $passwordHash = hex2bin(hash('sha512', $password));
    $passwordHash = shuffleString($passwordHash, sha512FoldXor($password));
    // Искажаем хэш пароля с помощью XOR и соли
    $result = '';
    for ($i = 0; $i < strlen($passwordHash); $i++) {
        $result .= $passwordHash[$i] ^ $saltMask[$i % strlen($saltMask)];
    }
    // Преобразуем результат обратно в HEX и возвращаем
    return bin2hex($result);
}

/**
 * @param array      $array
 * @param int|string $position
 * @param mixed      $insert
 */
function array_insert_m(&$array, $position, $insert) {
    if(is_int($position)) {
        array_splice($array, $position, 0, $insert);
    } else {
        $pos   = array_search($position, array_keys($array), true);
        $array = array_merge(
            array_slice($array, 0, $pos),
            $insert,
            array_slice($array, $pos)
        );
    }
}

function filterUsername(string $username): bool {

    $username = normalize_entities_my($username);

    // Невалидный UTF-8
    if(!mb_check_encoding($username, 'UTF-8')) {
        return false;
    }

    // Пустое имя или имя, состоящее только из пробельных символов
    if($username === '' || preg_match('/^[\p{Z}\s]*$/u', $username)) {
        return false;
    }

    // Управляющие символы:
    // NUL, TAB, CR, LF, DEL, C1 controls и т. д.
    if(preg_match('/\p{Cc}/u', $username)) {
        return false;
    }

    // Unicode-переводы строк, которые не входят в \p{Cc}
    // U+2028 LINE SEPARATOR
    // U+2029 PARAGRAPH SEPARATOR
    if(preg_match('/[\x{2028}\x{2029}]/u', $username)) {
        return false;
    }

    if(str_contains($username, '\\')) {
        return false;
    }

    if(mb_strlen($username) < 3 || mb_strlen($username) > 25) {
        return false;
    }

    $normalizedUsername = mb_superTrim($username);

    return ($username === $normalizedUsername);

    /// return true;
}

function repeatCaptcha($userInput) {
    global $ip;

    // Путь к директории для хранения файлов сессий
    $sessionDir = 'DATABASE/comments/capt/';

    // Генерация имени файла на основе IP-адреса
    $sessionFile = $sessionDir . sha1($ip) . '.json';

    // Максимальное количество сохраненных значений
    $maxEntries = 32;

    // Проверка, существует ли файл сессии
    if(is_file($sessionFile)) {
        // Чтение данных из файла

        $locktmp = fopenOrDie($sessionFile, 'rb');
        flock($locktmp, LOCK_SH);

        $sessionData = json_decode((string)stream_get_contents($locktmp), true);

        /// flock($locktmp, LOCK_UN);
        fclose($locktmp);

        /// $sessionData = json_decode(getFileOrDie($sessionFile), true);

        if(!is_array($sessionData)) {
            $sessionData = ['captchas' => []];
        }

        // Проверка на совпадение с предыдущими вводами
        if(in_array($userInput, $sessionData['captchas'] ?? [], true)) {
            return false; // Совпадение найдено, возвращаем false
        }
    } else {
        // Если файл не существует, инициализируем новый массив
        $sessionData = ['captchas' => []];
    }

    // Добавляем новое значение в массив
    $sessionData['captchas'][] = $userInput;

    // Если количество записей превышает максимальное, удаляем старейшую
    if(count($sessionData['captchas']) > $maxEntries) {
        array_shift($sessionData['captchas']); // Удаляем первое (старейшее) значение
    }

    // Сохраняем обновленные данные обратно в файл
    putFileOrDie($sessionFile, json_encode($sessionData), LOCK_EX);

    return true; // Ввод уникален, возвращаем true
}

function canProceed($datip) {
    // Путь к директории для хранения временных меток
    $lockDir = 'DATABASE/comments/lock/';
    $lockFile = $lockDir . sha1($datip) . '.json';

    // Проверяем, существует ли файл
    if(is_file($lockFile)) {
        // Читаем данные из файла

        $locktmp = fopenOrDie($lockFile, 'rb');
        flock($locktmp, LOCK_SH);

        $lockData = json_decode((string)stream_get_contents($locktmp), true);

        /// flock($locktmp, LOCK_UN);
        fclose($locktmp);

        /// $lockData = json_decode(getFileOrDie($lockFile), true);
        if(!is_array($lockData)) {
            $lockData = [];
        }

        $lastCall = (int)($lockData['last_call'] ?? 0);
        $currentTime = time();

        // Проверяем, прошло ли меньше 180 секунд с последнего вызова
        if(($currentTime - $lastCall) < 180) {
            return false; // Время еще не прошло
        }
    }

    // Обновляем временную метку
    $lockData = ['last_call' => time()];
    putFileOrDie($lockFile, json_encode($lockData), LOCK_EX);

    return true; // Можно продолжать
}

function refreshhandle($time, $link, $update = true) {

    global $head;

    if ($update) {
        refreshCaches();
        unlockByName($_SESSION['username'] ?? 'dummy');
    }

    $time = (int)$time;

    $linkHtml = htmlspecialchars($link, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);
    $linkJs   = json_encode($link, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    if ($time > 0) {
        $head .= "\n<noscript><meta http-equiv='refresh' content='{$time};url={$linkHtml}' /></noscript>
        <script>
            function addrrefr() {
                setTimeout(function() {
                    window.location.replace({$linkJs});
                }, {$time} * 1000);
            }
            window.addEventListener('DOMContentLoaded', addrrefr);
        </script>\n";
    } else {
        exit(
            "<!DOCTYPE html><html><head><meta charset='utf-8' />" .
            "<noscript><meta http-equiv='refresh' content='0;url={$linkHtml}' /></noscript>" .
            "<script>location.replace({$linkJs});</script>" .
            "</head><body>&nbsp;</body></html>"
        );
    }
}

function filter_filename(string $filename): string {

    if(!mb_check_encoding($filename, 'UTF-8')) {

        die("PANIC: Broken Encoding!");
    }

    $filename = basename(str_replace('\\', '/', $filename));

    $filename = emojiToHtmlEntities($filename);
    $filename = remove_entities($filename);
    $filename = mb_superTrim($filename);

    $filename = preg_replace('~
        [<>:"/\\\\|?*]      |  # reserved
        [\x00-\x1F]         |  # control
        [\x7F\xA0\xAD]      |  # DEL, NBSP, SHY
        [#\[\]@!$&\'()+,;=] |  # URI reserved
        [{}^\~`]               # URL-unsafe
    ~xu', '-', $filename);

    $filename = str_replace(' ', '_', $filename);
    $filename = preg_replace(['/-{2,}/', '/\.{2,}/', '/_{2,}/'], ['-', '.', '_'], $filename);
    $filename = trim($filename, ". \t\r\n\0\x0B-_");

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    /// $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    $limit = ($ext !== '') ? max(1, 255 - (strlen($ext) + 1)) : 255;
    $base  = mb_strcut($base, 0, $limit, 'UTF-8');

    if($base === '') die();

    return ($ext !== '') ? ($base . '.' . $ext) : $base;
}

function dbprepApnd($filename) {

    if(function_exists('ignore_user_abort')) {
        ignore_user_abort(true); // Установить игнорирование разрыва соединения
    }

    $lockFile = $filename . ".lock";
    $newFile = $filename . ".new." . getmypid();
    $newFileSrc = $filename . ".src." . getmypid();

    putFileOrDie($lockFile, getmypid(), LOCK_EX);

    // Копируем оригинальный файл в новый dest
    if(!copy($filename, $newFile)) {
        die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>Ошибка</title></head><body><h1>ОШИБКА.</h1><p><strong>Не удалось создать новый файл.</strong></p></body></html>');
    }

    // Копируем оригинальный файл в новый src
    if(!copy($filename, $newFileSrc)) {
        die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>Ошибка</title></head><body><h1>ОШИБКА.</h1><p><strong>Не удалось создать новый файл.</strong></p></body></html>');
    }

    return true;
}

function dbprepCache($filename) {

    if(function_exists('ignore_user_abort')) {
        ignore_user_abort(true); // Установить игнорирование разрыва соединения
    }

    $lockFile = $filename . ".lock";

    putFileOrDie($lockFile, getmypid(), LOCK_EX);

    return true;
}

function dbdone($filename, $recovery) {

    global $errmsg, $content;

    $locktmp = fopenOrDie($filename.".lock", 'rb');
    flock($locktmp, LOCK_SH);

    $lockvar = (int)(string)stream_get_contents($locktmp);

    /// flock($locktmp, LOCK_UN);
    fclose($locktmp);

    if(is_file($filename.".src." . getmypid())) {
        @unlink($filename.".src." . getmypid());
    }

    /// $lockvar = (int)@file_get_contents($filename.".lock");

    if($lockvar === getmypid()) {

        if(is_file($filename)) rename($filename, $filename.".bak");
    
        rename($filename.".new." . getmypid(), $filename);

        touchMy($filename);

        /// unlink($filename.".lock");

        return true;

    } else {

        if(is_file($filename.".new." . getmypid())) {
            @unlink($filename.".new." . getmypid());
        }

        if(!isset($safePost['commpost']) && !isset($safeGet["cmove"])) {

            unlockByName($_SESSION['username'] ?? "dummy");
        }

        $recovery = $recovery ?: "ДАННЫЕ НЕ СОХРАНЕНЫ!";

        $recovery = str_ireplace("<textarea", "&lt;textarea", $recovery);
        $recovery = str_ireplace("</textarea", "&lt;/textarea", $recovery);
        $recovery = str_ireplace("textarea>", "textarea&gt;", $recovery);

        $recovery = escape_amp_txtarea($recovery);

        $errmsg = "<h1>RECOVERY.</h1><p class='big'><strong>База Данных была изменена внешним процессом.</strong></p>";

        $content = "<textarea style='width: 95%; min-height: 65vh; padding: 2%; resize: none;' readonly='readonly'>".$recovery."</textarea>";

        /// return false;

        return false;
    }
}

function mylog($line) {

    $datetime = new DateTime();
    $mydate = $datetime->format('Y-m-d H:i:s');

    putFileOrDie("DATABASE/DB/sys.log", $mydate.": ".$line."<br />\n", FILE_APPEND | LOCK_EX);
}

function stripFirstLine($text) {
    return substr($text, strpos($text, "\n") + 1);
}

function rusTranslitHelper($st) {
    $st = strtr($st, array(
        'а' => 'a', 'б' => 'b', 'в' => 'v',
        'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'yo', 'ж' => 'zh', 'з' => 'z',
        'и' => 'i', 'й' => 'j', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ь' => '', 'ы' => 'y', 'ъ' => '-',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

        'А' => 'A', 'Б' => 'B', 'В' => 'V',
        'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
        'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z',
        'И' => 'I', 'Й' => 'J', 'К' => 'K',
        'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R',
        'С' => 'S', 'Т' => 'T', 'У' => 'U',
        'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
        'Ь' => '', 'Ы' => 'Y', 'Ъ' => '-',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
    ));
    return $st;
}


/*
function urlPrep($st) {

    $st = ltrim($st, '?');

    $st = normalize_entities_my($st);

    $st = mb_superTrim($st);
    
    $st = strtr($st, [

        // Валюты (популярные и редкие)
        '¤' => '.currency.',   // универсальный символ валюты
        '£' => '.pound.',      // британский фунт
        '¥' => '.yen.',        // японская и китайская валюта
        '€' => '.euro.',       // евро
        '₹' => '.inr.',        // индийская рупия
        '₨' => '.rp.',        // рупия (универсальная)
        '₩' => '.won.',        // южнокорейская вона
        '₪' => '.ils.',        // израильский шекель
        '₡' => '.crc.',        // коста-риканский колон
        '₢' => '.cruzeiro.',   // бразильский крузейро (устар.)
        '₣' => '.franc.',      // французский франк
        '₤' => '.lira.',       // итальянская лира
        '₥' => '.mill.',       // милль (1/1000 доллара)
        '₦' => '.ngn.',        // найра (Нигерия)
        '₧' => '.peseta.',     // песета (Испания, устар.)
        '₫' => '.vnd.',        // донг (Вьетнам)
        '₭' => '.kip.',        // кип (Лаос)
        '₮' => '.tugrik.',     // тугрик (Монголия)
        '₯' => '.drachma.',    // драхма (Греция, устар.)
        '₰' => '.pfennig.',    // пфенниг (Германия, истор.)
        '₱' => '.peso.',       // песо (Филиппины)
        '₲' => '.pyg.',        // гуарани (Парагвай)
        '₳' => '.austral.',    // аустраль (Аргентина, устар.)
        '₴' => '.uah.',        // гривна (Украина)
        '₵' => '.cedi.',       // седи (Гана)
        '₶' => '.livre.',      // ливр (Франция, истор.)
        '₷' => '.spesmilo.',  // спесмило (эсперанто)
        '₸' => '.tenge.',      // тенге (Казахстан)
        '₺' => '.try.',        // турецкая лира
        '฿' => '.thb.',        // бат (Таиланд)
        '₽' => '.rub.',        // российский рубль
        '₿' => '.btc.',        // биткоин
        '$' => '.doll.',       // доллар
    
        // Единицы измерения
        '′' => '.prime.',
        '″' => '.dprime.',
        'µ' => '.micro.',
        'Ω' => '.ohm.',
        '‰' => '.permil.',
        '‱' => '.permyriad.',
        '℉' => '.fah.',
        '℃' => '.cels.',
        'ℓ' => '.liter.',
        '℮' => '.estimated.',
        '㎏' => '.kg.',
        '㎜' => '.mm.',
        '㎝' => '.cm.',
        '㎞' => '.km.',
        '㎡' => '.m2.',
        '㎖' => '.ml.',
        '㎍' => '.mcg.',
        '㏄' => '.cc.',
    
        '@' => '.at.',          // Удаляется
        '%' => '.proc.',        // Удаляется
        '°' => '.deg.',         // Удаляется
        '{' => '.lfig.',        // Удаляется
        '}' => '.rfig.',        // Удаляется
        '=' => '.eq.',          // Удаляется
        '[' => '.lbrac.',       // Удаляется
        ']' => '.rbrac.',       // Удаляется
        '№' => '.Num.',          // Удаляется
        '|' => '.I.',           // Удаляется
        '\\' => '.bsl.',        // Удаляется
        ':' => '.col.',         // Удаляется
        '?' => '.qst.',         // Удаляется
        '*' => '.star.',        // Удаляется
    
        // Дополнительные, которых нет в whitelist
        '^' => '.pow.',
        /* '~' => '.tilde.', * / // оставлен только если ты захочешь удалять его
        '`' => '.bqt.',
        '¬' => '.not.',
        '©' => '.copy.',
        '®' => '.reg.',
        '™' => '.tm.',
        '§' => '.sect.',
        '×' => '.mult.',
        '÷' => '.div.',
        '‽' => '.interrobang.',
        '…' => '.dots.',
        '–' => '.ndash.',
        '—' => '.mdash.',
        '•' => '.bullet.',
        '«' => '.lq.',
        '»' => '.rq.'

    ]);
    

    /// $st = emojiToHtmlEntities($st);

    $st = strtr($st, [
        '&laquo;'=> '.lq.',
        '&raquo;'=> '.rq.',
     /* '\''     => '.apos.',
        '&#039;' => '.apos.', * /
        '&#39;'  => '-',
     /* '&#x27;' => '.apos.', * /
        '&#'     => '.',         // Обработка всех &#123; сущностей
     /* '"'      => '.quot.',
        '<'      => '.lt.',
        '>'      => '.gt.',
        '&lt;'   => '.lt.',
        '&gt;'   => '.gt.',
        '&quot;' => '.quot.', * /
        '&amp;'  => '.n.',
        '#' => '.sharp.',
     /* '&'      => '.',
        ';'      => '.',          
        ';.' => ';' * /

        '&'      => '.',
        ';'      => '.'
    ]);

    /// $st = remove_entities($st);

    $st = rusTranslitHelper($st);

    // Транслитерация символов
    $translit = "Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC;";
    $st = transliterator_transliterate($translit, $st);

    $st = str_replace(" ", "_", $st);

    $st = preg_replace('/[^A-Za-z0-9\-\_\.\~\+\,\!\(\)\/]/', '', $st);
    
    $st = preg_replace([
        '/_{2,}/',   // 2+ подчёркиваний → _
        '/-{2,}/',   // 2+ дефисов       → -
        '/\.{2,}/',  // 2+ точек         → .
    ], ['_', '-', '.'], $st);

    // $st = str_replace(";.", ";", $st);

    // $st = preg_replace('/[.,!?;:)\]\}\'"…]+$/u', '', $st);

    return '?'.$st;
}

*/




function urlPrep(string $st): string
{
    // Снять только первый ведущий ?
    if (isset($st[0]) && $st[0] === '?') {
        $st = substr($st, 1);
    }

    // 1) Декодировать сущности
    $st = html_entity_decode($st, ENT_QUOTES | ENT_HTML401, 'UTF-8');

    // 2) Супер-трим
    /// $st = mb_superTrim($st);

    // 3) Транслитерация
    $st = rusTranslitHelper($st);

    $tmp = transliterator_transliterate(
        'Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC;',
        $st
    );
    if ($tmp !== false) {
        $st = $tmp;
    }

    // 4) Сегменты пути
    $parts = explode('/', $st);

    foreach ($parts as &$part) {
        $part = mb_superTrim($part);

        // Пробелы -> _
        $part = preg_replace('/\s+/u', '_', $part);

        // Оставить только RFC3986 unreserved:
        // A-Z a-z 0-9 - _ . ~
        /// $part = preg_replace('/[^A-Za-z0-9._~-]+/', '', $part);

        // Схлопнуть повторы
        $part = preg_replace('/_{2,}/', '_', $part);
        $part = preg_replace('/-{2,}/', '-', $part);
        $part = preg_replace('/\.{2,}/', '.', $part);

        // Подчистить края
        $part = trim($part, '._-');

        // Финальный safeguard
        /// $part = rawurlencode($part);


        $part = preg_replace_callback(
            '/[^A-Za-z0-9._~!(),+:;@\\\\$-]+/u',
            static function ($m) {
                return rawurlencode($m[0]);
            },
            $part
        );
    }
    unset($part);

    // Убрать пустые сегменты
    $parts = array_values(array_filter(
        $parts,
        static fn(string $p): bool => $p !== ''
    ));

    return '?' . implode('/', $parts);
}



/*
function urlPrep2($st) {

    // $st = ltrim($st, '?');

    // $st = mb_superTrim($st);
    
    $st = strtr($st, [

        // Валюты (популярные и редкие)
        '¤' => '.currency.',   // универсальный символ валюты
        '£' => '.pound.',      // британский фунт
        '¥' => '.yen.',        // японская и китайская валюта
        '€' => '.euro.',       // евро
        '₹' => '.inr.',        // индийская рупия
        '₨' => '.rp.',        // рупия (универсальная)
        '₩' => '.won.',        // южнокорейская вона
        '₪' => '.ils.',        // израильский шекель
        '₡' => '.crc.',        // коста-риканский колон
        '₢' => '.cruzeiro.',   // бразильский крузейро (устар.)
        '₣' => '.franc.',      // французский франк
        '₤' => '.lira.',       // итальянская лира
        '₥' => '.mill.',       // милль (1/1000 доллара)
        '₦' => '.ngn.',        // найра (Нигерия)
        '₧' => '.peseta.',     // песета (Испания, устар.)
        '₫' => '.vnd.',        // донг (Вьетнам)
        '₭' => '.kip.',        // кип (Лаос)
        '₮' => '.tugrik.',     // тугрик (Монголия)
        '₯' => '.drachma.',    // драхма (Греция, устар.)
        '₰' => '.pfennig.',    // пфенниг (Германия, истор.)
        '₱' => '.peso.',       // песо (Филиппины)
        '₲' => '.pyg.',        // гуарани (Парагвай)
        '₳' => '.austral.',    // аустраль (Аргентина, устар.)
        '₴' => '.uah.',        // гривна (Украина)
        '₵' => '.cedi.',       // седи (Гана)
        '₶' => '.livre.',      // ливр (Франция, истор.)
        '₷' => '.spesmilo.',  // спесмило (эсперанто)
        '₸' => '.tenge.',      // тенге (Казахстан)
        '₺' => '.try.',        // турецкая лира
        '฿' => '.thb.',        // бат (Таиланд)
        '₽' => '.rub.',        // российский рубль
        '₿' => '.btc.',        // биткоин
        '$' => '.doll.',       // доллар
    
        // Единицы измерения
        '′' => '.prime.',
        '″' => '.dprime.',
        'µ' => '.micro.',
        'Ω' => '.ohm.',
        '‰' => '.permil.',
        '‱' => '.permyriad.',
        '℉' => '.fah.',
        '℃' => '.cels.',
        'ℓ' => '.liter.',
        '℮' => '.estimated.',
        '㎏' => '.kg.',
        '㎜' => '.mm.',
        '㎝' => '.cm.',
        '㎞' => '.km.',
        '㎡' => '.m2.',
        '㎖' => '.ml.',
        '㎍' => '.mcg.',
        '㏄' => '.cc.',
    
        // '&@' => '&',            // Удаляется

        '@' => '.at.',          // Удаляется
        '%' => '.proc.',        // Удаляется
        '°' => '.deg.',         // Удаляется
        '{' => '.lfig.',        // Удаляется
        '}' => '.rfig.',        // Удаляется
        '=' => '.eq.',          // Удаляется
        '[' => '.lbrac.',       // Удаляется
        ']' => '.rbrac.',       // Удаляется
        '№' => '.Num.',          // Удаляется
        '|' => '.I.',           // Удаляется
        '\\' => '.bsl.',        // Удаляется
        ':' => '.col.',         // Удаляется
        '?' => '.qst.',         // Удаляется
        '*' => '.star.',        // Удаляется
    
        // Дополнительные, которых нет в whitelist
        '^' => '.pow.',
        /* '~' => '.tilde.', * / // оставлен только если ты захочешь удалять его
        '`' => '.bqt.',
        '¬' => '.not.',
        '©' => '.copy.',
        '®' => '.reg.',
        '™' => '.tm.',
        '§' => '.sect.',
        '×' => '.mult.',
        '÷' => '.div.',
        '‽' => '.interrobang.',
        '…' => '.dots.',
        '–' => '.ndash.',
        '—' => '.mdash.',
        '•' => '.bullet.',
        '«' => '.lq.',
        '»' => '.rq.'
        
    ]);
    

    /// $st = emojiToHtmlEntities($st);

    $st = strtr($st, [
        '&laquo;'=> '.lq.',
        '&raquo;'=> '.rq.',
     /* '\''     => '.apos.',
        '&#039;' => '.apos.', * /
        '&#39;'  => '-',
     /* '&#x27;' => '.apos.', * /
        '&#'     => '.',         // Обработка всех &#123; сущностей
     /* '"'      => '.quot.',
        '<'      => '.lt.',
        '>'      => '.gt.',
        '&lt;'   => '.lt.',
        '&gt;'   => '.gt.',
        '&quot;' => '.quot.', * /
        '&amp;'  => '.n.',
        '#' => '.sharp.',
     /* '&'      => '.',
        ';'      => '.',         
        ';.' => ';' * /

        '&'      => '.',
        ';'      => '.'
    ]);

    /// $st = remove_entities($st);

    $st = rusTranslitHelper($st);

    // Транслитерация символов
    $translit = "Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC;";
    $st = transliterator_transliterate($translit, $st);

    // Убираем лишние пробелы, знаки и символы
    # $st = preg_replace('/[\s\xA0\x00]+/u', '-', $st);

    $st = str_replace(" ", "_", $st);

    // $st = preg_replace('/[^\x00-\x7F]/', '', $st);

    $st = preg_replace('/[^A-Za-z0-9\-\_\.\~\+\,\!\(\)\/]/', '', $st);

    $st = preg_replace([
        '/_{2,}/',   // 2+ подчёркиваний → _
        '/-{2,}/',   // 2+ дефисов       → -
        '/\.{2,}/',  // 2+ точек         → .
    ], ['_', '-', '.'], $st);

    // $st = str_replace(";.", ";", $st);

    // $st = preg_replace('/[.,!?;:)\]\}\'"…]+$/u', '', $st);

    return $st;
}
*/




function urlPrep2(string $st): string
{
    // Снять только первый ведущий ?
    /* if (isset($st[0]) && $st[0] === '?') {
        $st = substr($st, 1);
    } */

    // 1) Декодировать сущности
    $st = html_entity_decode($st, ENT_QUOTES | ENT_HTML401, 'UTF-8');

    // 2) Супер-трим
    /// $st = mb_superTrim($st);

    // 3) Транслитерация
    $st = rusTranslitHelper($st);

    $tmp = transliterator_transliterate(
        'Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC;',
        $st
    );
    if ($tmp !== false) {
        $st = $tmp;
    }

    // 4) Сегменты пути
    $parts = explode('/', $st);

    foreach ($parts as &$part) {
        $part = mb_superTrim($part);

        // Пробелы -> _
        $part = preg_replace('/\s+/u', '_', $part);

        // Оставить только RFC3986 unreserved:
        // A-Z a-z 0-9 - _ . ~
        /// $part = preg_replace('/[^A-Za-z0-9._~-]+/', '', $part);

        // Схлопнуть повторы
        $part = preg_replace('/_{2,}/', '_', $part);
        $part = preg_replace('/-{2,}/', '-', $part);
        $part = preg_replace('/\.{2,}/', '.', $part);

        // Подчистить края
        $part = trim($part, '._-');

        // Финальный safeguard
        /// $part = rawurlencode($part);

        
        $part = preg_replace_callback(
            '/[^A-Za-z0-9_.:-]+/u',
            static function ($m) {
                return rawurlencode($m[0]);
            },
            $part
        );
    }
    unset($part);

    // Убрать пустые сегменты
    $parts = array_values(array_filter(
        $parts,
        static fn(string $p): bool => $p !== ''
    ));

    /// return '?' . implode('/', $parts);
    return implode('/', $parts);
}




function urlPrep3($st) {

    // 1) вычищаем HTML-сущности (&nbsp; &#160; &#xA0; и т.п.)
    $st = remove_entities($st);

    // 2) сначала твой кастомный транслит для русского
    $st = rusTranslitHelper($st);

    // 3) потом общий ICU-транслит:
    //    - Any-Latin      — всё в латиницу
    //    - Latin-ASCII    — латиница → максимально "плоский" ASCII (é → e, ß → ss и т.п.)
    //    - [:Nonspacing Mark:] Remove — убирает диакритику, если осталась
    //    - NFC            — нормализует
    $translit = "Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC;";
    $st = transliterator_transliterate($translit, $st);

    // 4) оставляем только A–Z, a–z, 0–9
    $st = preg_replace('/[^a-z0-9]/i', '', $st);

    return $st;
}

function logInOutLink($logintxt, $logouttxt) {

    global $checkpermission;

    if($checkpermission) {

        return "<a href='?logout=1' rel='nofollow' title='Выход из системы'>".$logouttxt."</a>";

    } else {

        return "<a href='?login=1' rel='nofollow' title='Вход в систему'>".$logintxt."</a>";

    }

}

function checkMenuOrder($sanCheckTable) {
    global $errmsg;


    if((int)$sanCheckTable[0] !== 1) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. ({$_SESSION["username"]}).</span>");
        return false;
    }


    $count = count($sanCheckTable);

    for ($i = 1; $i < $count; $i++) {
        if ($sanCheckTable[$i] - $sanCheckTable[$i - 1] >= 2) {
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
            mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. ({$_SESSION["username"]}).</span>");
            return false;
        }
    }

    return true;
}

function sanCheckHor($check, $h) {

    global $numcache, $errmsg;
    #global $safeGet;

    #$index = $safeGet["pgmovedown"] - 1;

    $sanCheckTable = $numcache;

    /*
    if(!isset($sanCheckTable[$check])) {
        
        die();
    }
    */

    if(!is_int($check) || !is_int($h) /* || $check < 0 || $check >= sizeof($sanCheckTable) */ || $h < 1 || $h > 6 || !isset($sanCheckTable[$check])) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    /*
    } elseif($sanCheckTable[0] > 1) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. (".$_SESSION["username"].").</span>");
        return false; */

    } else {

        $sanCheckTable[$check] = $h;

        return checkMenuOrder($sanCheckTable);
    }
}

function sanCheckAdd($check, $h) {

    global $numcache, $errmsg, $ispageexist;

    $sanCheckTable = $numcache;

    if(!$ispageexist || !is_int($check) || !is_int($h) || /* $check < 1 || $check > sizeof($numcache) || */ $h < 1 || $h > 6 || !isset($sanCheckTable[$check-1])) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    /*
    } elseif($check == 1 && ($h == 1 || $h == 2)) {

        return true; */

    } else {

        array_insert_m(
            $sanCheckTable,
            $check,
            $h
        );

        return checkMenuOrder($sanCheckTable);
    }
}

function sanCheckDown() {

    global $numcache, $safeGet, $errmsg;

    $index = $safeGet["pgmovedown"] ? ((int)$safeGet["pgmovedown"] - 1) : "";

    $sanCheckTable = $numcache;

    if(!is_int($index) /* || $index < 0 */ || !isset($sanCheckTable[$index], $sanCheckTable[$index + 1]) /* || $index >= (sizeof($sanCheckTable) - 1) || ($index == 0 && $sanCheckTable[0] != $sanCheckTable[1]) */ ) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    } else {

        $item = $sanCheckTable[ $index ];
        $sanCheckTable[ $index ] = $sanCheckTable[ $index + 1 ];
        $sanCheckTable[ $index + 1 ] = $item;

        return checkMenuOrder($sanCheckTable);
    }
}

function sanCheckUp() {

    global $numcache, $safeGet, $errmsg;

    $sanCheckTable = $numcache;

    $index = $safeGet["pgmoveup"] ? ((int)$safeGet["pgmoveup"] - 1) : "";

    if(!is_int($index) /* || $index < 1 */ || !isset($sanCheckTable[$index], $sanCheckTable[$index - 1])) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    /*
    } elseif($index == 1 && $sanCheckTable[0] != $sanCheckTable[1]) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. (".$_SESSION["username"].").</span>");
        return false; */

    } else {

        $item = $sanCheckTable[ $index ];
        $sanCheckTable[ $index ] = $sanCheckTable[ $index - 1 ];
        $sanCheckTable[ $index - 1 ] = $item;

        return checkMenuOrder($sanCheckTable);
    }
}

function pnotfound() {

    global $mainPageTitle;

    http_response_code(404);

    $mainPageTitle = "404. Страница не найдена.";

    return "<h1>404.</h1><p class='big'><strong>Страница не найдена.</strong></p>";
}

function pforbidden() {

    global $mainPageTitle;

    http_response_code(403);

    $mainPageTitle = "403. Доступ запрещён.";

    return "<h1>403.</h1><p class='big'><strong>Доступ запрещён.</strong></p>";
}

function calcTotPages(string $commaddr, int $limit, bool $update = false): int
{
    if ($limit <= 0) {
        die('PANIC: bad $limit');
    }

    $commentsFile = "DATABASE/comments/" . $commaddr;
    $cacheFile    = $commentsFile . ".pages-cache";

    // ── 1) Если update=false — пытаемся прочитать кэш ─────────────────────────
    if ($update === false && is_file($cacheFile)) {
        $fh = @fopen($cacheFile, 'rb');
        if ($fh) {
            @flock($fh, LOCK_SH);
            $raw = (int)(string)stream_get_contents($fh);
            /// @flock($fh, LOCK_UN);
            fclose($fh);

            return $raw;

            /*
            $raw = trim((string)$raw);

            // строго: только 0..N
            if ($raw !== '' && ctype_digit($raw)) {
                return (int)$raw;
            }
            */
            // кэш битый — пересчитаем ниже
        }
    }

    // ── 2) Считаем количество строк комментариев (по \n) ───────────────────────────
    if (!is_file($commentsFile)) {
        /// $totalPages = 0;
        /// return $totalPages;
        return 0;

    } else {

        $commcount  = 0;
        $bufferSize = 128 * 1024;
        
        $file = openFileOrDie($commentsFile, 'rb');
        /// $file->flock(LOCK_SH);

        while ($buffer = $file->freadOrDie($bufferSize)) {
            $commcount += substr_count($buffer, "\n");
        }

        /// $file->flock(LOCK_UN);
        $file = null;

        $totalPages = ($commcount <= 0)
            ? 0
            : ((int)ceil($commcount / $limit) - 1);
    }

    // ── 3) Обновляем кэш ─────────────────────────────────────────────────────
    $dir = dirname($cacheFile);
    if (is_dir($dir) && is_writable($dir)) {

        /*
        $tmp = $cacheFile . ".tmp";
        if (@file_put_contents($tmp, (string)$totalPages, LOCK_EX) !== false) {
            @rename($tmp, $cacheFile);
        } else {
            @unlink($tmp);
        }
        */

        @file_put_contents($cacheFile, (string)$totalPages, LOCK_EX);
    }

    return $totalPages;
}

function calcTotPages2(int $commcount, string $commaddr, int $limit, bool $update = false): int
{
    if ($limit <= 0) {
        die('PANIC: bad $limit');
    }

    $commentsFile = "DATABASE/comments/" . $commaddr;
    $cacheFile    = $commentsFile . ".pages-cache";

    // ── 1) Если update=false — пытаемся прочитать кэш ─────────────────────────
    if ($update === false && is_file($cacheFile)) {
        $fh = @fopen($cacheFile, 'rb');
        if ($fh) {
            @flock($fh, LOCK_SH);
            $raw = (int)(string)stream_get_contents($fh);
            /// @flock($fh, LOCK_UN);
            fclose($fh);

            return $raw;

            /*
            $raw = trim((string)$raw);

            // строго: только 0..N
            if ($raw !== '' && ctype_digit($raw)) {
                return (int)$raw;
            }
            */
            // кэш битый — пересчитаем ниже
        }
    }

    // ── 2) Считаем количество строк комментариев (по \n) ───────────────────────────
    if (!is_file($commentsFile)) {
        /// $totalPages = 0;
        /// return $totalPages;
        return 0;

    } else {

        /*

        $commcount  = 0;
        $bufferSize = 128 * 1024;
        
        $file = openFileOrDie($commentsFile, 'rb');
        /// $file->flock(LOCK_SH);

        while ($buffer = $file->freadOrDie($bufferSize)) {
            $commcount += substr_count($buffer, "\n");
        }

        /// $file->flock(LOCK_UN);
        $file = null;

        */

        $totalPages = ($commcount <= 0)
            ? 0
            : ((int)ceil($commcount / $limit) - 1);
    }

    // ── 3) Обновляем кэш ─────────────────────────────────────────────────────
    $dir = dirname($cacheFile);
    if (is_dir($dir) && is_writable($dir)) {

        /*
        $tmp = $cacheFile . ".tmp";
        if (@file_put_contents($tmp, (string)$totalPages, LOCK_EX) !== false) {
            @rename($tmp, $cacheFile);
        } else {
            @unlink($tmp);
        }
        */

        @file_put_contents($cacheFile, (string)$totalPages, LOCK_EX);
    }

    return $totalPages;
}

function loadTplSess() {

    // Папка с шаблонами
    $templateDir = 'TEMPLATES/';

    // Получаем список директорий-шаблонов
    $templates = array_map('basename', glob($templateDir . '*.tpl', GLOB_ONLYDIR) ?: []);

    if (isset($_COOKIE['selected_template']) &&
        is_string($_COOKIE['selected_template']) &&
        in_array($_COOKIE['selected_template'], $templates, true))
    {

        return (string)$_COOKIE['selected_template'];
    }

    // Возвращаем шаблон по умолчанию, если ничего не выбрано
    return 'default.tpl';
}

// Функция для генерации формы выбора шаблона
function genTplForm() {

    $selectedTemplate = loadTplSess();

    // Папка с шаблонами
    $templateDir = 'TEMPLATES/';

    // Получаем список директорий-шаблонов
    $templates = array_map('basename', glob($templateDir . '*.tpl', GLOB_ONLYDIR) ?: []);

    // Генерируем HTML формы
    $html  = '<form method="post" id="templateForm">';
    $html .= '<label for="templateSelect">Выберите шаблон:</label>';
    $html .= '<select id="templateSelect" name="selected_template" onchange="document.getElementById(\'templateForm\').submit();">';

    // Генерируем опции для выпадающего списка
    foreach ($templates as $template) {
        $selected = ($template === $selectedTemplate) ? ' selected="selected"' : '';
        $html .= "<option value=\"$template\"$selected>" . ucfirst($template) . "</option>";
    }

    $html .= '</select><input type="submit" value="Да!" class="not-js" /></form>';

    // Выводим текущий выбранный шаблон
    return $html;
}

function convertQuotBlocks(simple_html_dom $html): simple_html_dom {
    // $html = str_get_html($htmlString);

    // ✅ Только контентные теги, без layout, media, JS и пр.
    $allowedTagsBlockquote = '<p><br><hr><strong><em><small><mark><del><ins><sub><sup>' .
                            '<span><code><pre><kbd><samp><var><dfn><abbr><time><q><cite>' .
                            '<ul><ol><li><dl><dt><dd>' .
                            '<table><thead><tbody><tfoot><tr><td><th>' .
                            '<a><bdi><bdo><wbr><img>';

    $allowedTagsFigcaption = '<strong><em><small><mark><del><ins><sub><sup>' .
                            '<span><code><kbd><samp><var><dfn><abbr><time><q><cite>' .
                            '<a><bdi><bdo><wbr><br>';

    foreach (iterator_to_array($html->find('div.my-quot'), false) as $quotBlock) {
    // foreach ($html->find('div.my-quot') as $quotBlock) {

        // Удалить вложенные .my-quot, собрав их в массив перед удалением
        foreach (iterator_to_array($quotBlock->find('div.my-quot'), false) as $nested) {
            $nested->outertext = '';
        }

        // Найти первого автора
        $author = '';
        $authorSpan = $quotBlock->find('span.my-quot-author', 0);
        if($authorSpan) {
            $author = $authorSpan->innertext;
            $authorSpan->outertext = '';
        }

        // Удалить остальных авторов
        foreach (iterator_to_array($quotBlock->find('span.my-quot-author'), false) as $extraAuthor) {
            $extraAuthor->outertext = '';
        }

        // Очистка содержимого от лишних тегов
        # $quote = mb_softTrim($quotBlock->innertext);
        $quoteClean = mb_softTrim(normalize_entities_my(strip_tags($quotBlock->innertext, $allowedTagsBlockquote)));
        $authorClean = mb_superTrim(normalize_entities_my(strip_tags($author, $allowedTagsFigcaption)));

        // Сборка итогового HTML
        $figureHtml = "<figure class='my-blockquote clearfix'><blockquote>$quoteClean</blockquote>";
        if($authorClean) {
            $figureHtml .= "<figcaption>$authorClean</figcaption>";
        }
        $figureHtml .= "</figure>";

        // Замена цитатного блока
        $quotBlock->outertext = $figureHtml;
    }

    // Удалить всех .my-quot-author вне цитат
    foreach (iterator_to_array($html->find('span.my-quot-author'), false) as $orphanAuthor) {
        $orphanAuthor->outertext = '';
    }

    // return $html->save();

    return $html;
}

function parseSpoilers(simple_html_dom $html): simple_html_dom {
    // Сохраняем все спойлеры в массив и переворачиваем порядок
    $spoilers = array_reverse(iterator_to_array($html->find('div.spoiler-blk'), false));

    foreach ($spoilers as $spoiler) {
        $spoilerContent = $spoiler->innertext;

        $newContent = '<summary class="spoiler-tgl">Спойлер</summary>' . $spoilerContent;

        $spoiler->outertext = '<details class="spoiler-blk clearfix">' . $newContent . '</details>';
    }

    return $html;
}

function wrap_images_with_figure(simple_html_dom $html): simple_html_dom {
    // Безопасный снимок массива элементов
    $images = iterator_to_array($html->find('img'), false);

    foreach ($images as $img) {
        $img->setAttribute('loading', 'lazy');

        $fname = $img->getAttribute('src');
        $fname = basename(str_replace('\\', '/', $fname));

        $alt = mb_superTrim(normalize_entities_my($img->getAttribute('alt') ?? ''));
        if ($alt !== '' && rawurlencode($alt) !== $fname) {
            $imgHtml = $img->outertext;
            // $altEscaped = mb_superTrim(htmlspecialchars(strip_tags($alt), ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false));
            // $altEscaped = str_ireplace('&amp;@', '&', $altEscaped);
            // $figureHtml = '<figure class="fig-img clearfix">' . $imgHtml . '<figcaption>' . $altEscaped . '</figcaption></figure>';
            $figureHtml = '<figure class="fig-img clearfix">' . $imgHtml . '<figcaption>' . $alt . '</figcaption></figure>';

            $img->outertext = $figureHtml;
        }
    }

    return $html;
}

function convert_infoboxes_to_aside(simple_html_dom $html): simple_html_dom {

    // Меняем каждый элемент, не влияя на итерацию
    foreach ($html->find('div.infobox, div.ibox-red, div.ibox-blue, div.ibox-green') as $node) {
        $node->tag = 'aside';
        $node->setAttribute('aria-label', 'Инфобокс');
        
        $currentClass = $node->getAttribute('class');

        /*    ПОЧИЩЕНО ПУРИФАЕРОМ, оставлено как страховка */

        $currentClass = preg_replace('/[^a-z0-9 _-]+/i', ' ', $currentClass); // всё лишнее → пробел
        $currentClass = trim(preg_replace('/\s+/', ' ', $currentClass));      // сжать пробелы
        

        /// $currentClass = emojiToHtmlEntities($currentClass);

        /// $currentClass = remove_entities($currentClass);

        $node->setAttribute('class', $currentClass . ' clearfix');
    }

    return $html;
}

function ulFix(simple_html_dom $html): simple_html_dom {

    // Найдём все <ul class="ul-fix">
    foreach ($html->find('ul, ol') as $ul) {
        // Безопасная копия узлов для итерации
        $nodes = array_reverse(iterator_to_array($ul->nodes));

        foreach ($nodes as $node) {
            if ($node instanceof simple_html_dom_node && $node->tag !== 'li') {
                $node->outertext = ""; // Удаляем не затрагивая вложенные <li>
            }
        }
    }

    return $html;
}

/**
 * Добавляет CSS-класс(ы) ко всем <ul>.
 *
 * @param simple_html_dom $html   DOM-объект из Simple HTML DOM
 * @param string $classes        "my-class" или "a b c" (несколько через пробел)
 * @param callable|null $filter  function(simple_html_dom_node $ul): bool — вернуть true, чтобы добавить класс к конкретному <ul>
 * @return simple_html_dom       Тот же DOM (модифицируется на месте)
 */
function addClassToAllUl(simple_html_dom $html, string $classes, ?callable $filter = null): simple_html_dom {
    // Разобьём классы по пробелам и уберём пустые
    $toAdd = preg_split('/\s+/', trim($classes)) ?: [];
    $toAdd = array_values(array_filter($toAdd, fn($c) => $c !== ''));

    if (!$toAdd) return $html;

    foreach ($html->find('ul') as $ul) {
        if ($filter && !$filter($ul)) {
            continue;
        }
        // Достаём уже существующие классы (поддержка разных форков Simple HTML DOM)
        $existing = '';
        if (property_exists($ul, 'class')) {
            $existing = (string)$ul->class;
        } elseif (method_exists($ul, 'getAttribute')) {
            $existing = (string)$ul->getAttribute('class');
        }

        /*    ПОЧИЩЕНО ПУРИФАЕРОМ, оставлено как страховка */

        $existing = preg_replace('/[^a-z0-9 _-]+/i', ' ', $existing); // всё лишнее → пробел
        /// $existing = trim(preg_replace('/\s+/', ' ', $existing));      // сжать пробелы
        

        /// $existing = emojiToHtmlEntities($existing);

        /// $existing = remove_entities($existing);

        // В сет превратим, чтобы не было дублей
        $current = preg_split('/\s+/', trim($existing)) ?: [];
        $set = [];
        foreach ($current as $c) { if ($c !== '') $set[$c] = true; }

        foreach ($toAdd as $c) {
            if (!isset($set[$c])) $set[$c] = true;
        }

        $new = implode(' ', array_keys($set));

        // Записываем обратно (с учётом разных API)
        if (method_exists($ul, 'setAttribute')) {
            $ul->setAttribute('class', $new);
        } else {
            $ul->class = $new;
        }
    }
    return $html;
}

/**
 * Заменяет все <span> на семантику
 *
 * @param simple_html_dom $html
 * @return simple_html_dom
 */
function replaceSemanticSpans(simple_html_dom $html): simple_html_dom {
    // Сохраняем найденные узлы в отдельный массив
    $spans = $html->find('span[style]');
    
    $spans = array_reverse($spans);

    foreach ($spans as $span) {

        $style = $span->getAttribute('style');

        if (stripos($style, 'line-through') !== false) {
            $span->outertext = '<del>' . $span->innertext . '</del>';
            continue;
        }

        if (stripos($style, 'underline') !== false) {
            $span->outertext = '<u>' . $span->innertext . '</u>';
            continue;
        }
    }

    return $html;
}

// $html = replaceSemanticSpans($html);


function replaceBr(simple_html_dom $html): simple_html_dom
{
    // Снимок элементов, чтобы безопасно менять DOM во время обхода
    $brs = iterator_to_array($html->find('br'), false);

    foreach ($brs as $br) {
        $br->outertext = "\n";
    }

    return $html;
}



function replaceParagraphs(simple_html_dom $html): simple_html_dom
{
    // Безопасный снимок массива элементов
    $paragraphs = iterator_to_array($html->find('p'), false);

    foreach ($paragraphs as $p) {
        $p->tag = 'rich-paragraph';

        if (!isset($p->attr['role'])) {
            $p->attr['role'] = 'paragraph';
        }
    }

    return $html;
}



function refreshCaches() {

    if(is_file("DATABASE/DB/DB-TOC-Cache.txt")) {
        // rename("DATABASE/DB/DB-TOC-Cache.txt", "DATABASE/DB/DB-TOC-Cache.txt.del");

        @unlink("DATABASE/DB/DB-TOC-Cache.txt");
    }

    if(is_file("DATABASE/DB/SEO-Cache.txt")) {
        // rename("DATABASE/DB/SEO-Cache.txt", "DATABASE/DB/SEO-Cache.txt.del");

        @unlink("DATABASE/DB/SEO-Cache.txt");
    }

    if(is_file("DATABASE/DB/MenuCache.txt")) {
        // rename("DATABASE/DB/MenuCache.txt", "DATABASE/DB/MenuCache.txt.del");

        @unlink("DATABASE/DB/MenuCache.txt");
    }

    if(is_file("sitemap.txt")) {
        // rename("sitemap.txt", "sitemap.txt.del");

        @unlink("sitemap.txt");
    }

    if(is_file("sitemap.xml")) {
        // rename("sitemap.xml", "sitemap.xml.del");

        @unlink("sitemap.xml");
    }
}

function getCommCount($commaddr) {

    if(is_file("DATABASE/comments/".$commaddr.".count")) {

        $locktmp = fopenOrDie("DATABASE/comments/".$commaddr.".count", 'rb');
        flock($locktmp, LOCK_SH);
        $contents = (string)stream_get_contents($locktmp);
        /// flock($locktmp, LOCK_UN);
        fclose($locktmp);

        return "<span class='pgCommCnt' title='Комментарии'>&nbsp;(".(int)$contents.")</span>";

    } else {

        return "";
    }
}

/*
function unwrapParagraphsAfterDiv($html) {
    return preg_replace_callback(
        '#</div>(.*?)</p>#is',
        function ($matches) {
            // Быстрое игнорирование <p — без копирования строки
            if(stripos($matches[1], '<p>') !== false || stripos($matches[1], '<p ') !== false) {
                return $matches[0];
            }

            return '</div>' . $matches[1];
        },
        $html
    );
}
*/





function seoLinkDecode(int $num) {
    global $seoNumEncode;

    // Проверка диапазона и значений
    if(array_key_exists($num, $seoNumEncode)) {
        return (int)$seoNumEncode[$num];
    }

    return (int)1;
}

function seoMoveNum2End($addr) {

    $addr = ltrim($addr, "?");

    $linkArr = explode("/", $addr);

    // Удаляем первый элемент и сохраняем его
    $first = array_shift($linkArr);

    // Добавляем его в конец
    array_push($linkArr, $first);

    return "?".implode("/", $linkArr);
}

function seoNumGet() {

    $str = $_SERVER["QUERY_STRING"] ?? '';

    $str = explode("&", $str)[0];

    $arr = explode("/", $str);

    $str = (int)end($arr);

    return seoLinkDecode($str);
}

function skipCache(string $filepath): string {
    $mTime = 0;

    if (is_file($filepath)) {
        $mTime = (int)@filemtime($filepath);
    }

    $separator = (strpos($filepath, '?') === false) ? '?' : '&';

    return $filepath . $separator . $mTime;
}

function normalize_entities_my(string $text): string {
    // HTML5: маппинг C1 (0x80–0x9F) → Unicode
    static $cp1252 = [
        0x80=>0x20AC, 0x82=>0x201A, 0x83=>0x0192, 0x84=>0x201E,
        0x85=>0x2026, 0x86=>0x2020, 0x87=>0x2021, 0x88=>0x02C6,
        0x89=>0x2030, 0x8A=>0x0160, 0x8B=>0x2039, 0x8C=>0x0152,
        0x8E=>0x017D, 0x91=>0x2018, 0x92=>0x2019, 0x93=>0x201C,
        0x94=>0x201D, 0x95=>0x2022, 0x96=>0x2013, 0x97=>0x2014,
        0x98=>0x02DC, 0x99=>0x2122, 0x9A=>0x0161, 0x9B=>0x203A,
        0x9C=>0x0153, 0x9E=>0x017E, 0x9F=>0x0178
    ];
    $fixNum = static function(int $num) use ($cp1252): int {
        if ($num === 0 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF)) {
            return 0xFFFD; // замена: ноль, вне диапазона, суррогаты
        }
        if ($num >= 0x80 && $num <= 0x9F && isset($cp1252[$num])) {
            return $cp1252[$num]; // HTML5 правило для C1
        }
        return $num;
    };
    $toDecEnt = static function(int $num) use ($fixNum): string {
        return '&#' . $fixNum($num) . ';';
    };

    // 1) Десятичные: убираем лидирующие нули + правим диапазоны
    $text = preg_replace_callback('/&#0*(\d+);/', static function ($m) use ($toDecEnt) {
        return $toDecEnt((int)$m[1]);
    }, $text);

    // 2) Шестнадцатеричные: к десятичным + правим диапазоны
    $text = preg_replace_callback('/&#x0*([0-9a-f]+);/i', static function ($m) use ($toDecEnt) {
        return $toDecEnt(hexdec($m[1]));
    }, $text);

    // 3) Именованные → нижний регистр (канон для HTML)
    /*
    $text = preg_replace_callback('/&[a-z][a-z0-9]*;/i', static function ($m) {
        return strtolower($m[0]);
    }, $text);
    */

    // 4) Экранируем «голые» &
    $text = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '&amp;', $text);

    return $text;
}

function escape_amp_txtarea(string $text): string
{
    return str_replace("&", "&amp;", $text);
}

function remove_entities(string $text): string {
    // 1) именованные (&nbsp;)  2) десятичные (&#160;)  3) шестн. (&#xA0;)
    $text = preg_replace('/&[a-z][a-z0-9]*;/i', '', $text);
    $text = preg_replace('/&#\d+;/', '', $text);
    $text = preg_replace('/&#x[0-9a-f]+;/i', '', $text);

    // 4) Только формы без ';' и не продолжаемые буквой/цифрой/подчёркиванием
    $text = preg_replace('/&(?:nbsp|thinsp|ensp|emsp|zwnj|zwj|lrm|rlm)(?!;)\b/i', '', $text);

    // 5) «голые» & → подчеркивание, чтобы слова не слипались
    $text = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '_', $text);

    return $text;
}

function atomicCounterIncrement($path) {

    if(is_file($path)) {
        if(!is_writable($path)) {
            die('counter file is not writable');
        }
    } else {
        if(!is_writable(dirname($path))) {
            die('counter directory is not writable');
        }
    }

    $fp = fopenOrDie($path, 'c+b'); // создаст файл при отсутствии
    
    if(flock($fp, LOCK_EX)) {
        $val  = 0;
        rewind($fp);
        $data = (string)stream_get_contents($fp);
        $data = trim((string)$data);
        if($data !== '' && ctype_digit($data)) {
            $val = (int)$data;
        }
        $val++;
        rewind($fp);
        if(ftruncate($fp, 0) !== false && fwrite($fp, (string)$val) !== false) {
            fflush($fp);
        }
        /// flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// Небольшой генератор ASCII-токена (на случай старых PHP без random_bytes)
function _typo_token($prefix)
{
    if (function_exists('random_bytes')) {
        return '[[' . $prefix . '_' . bin2hex(random_bytes(8)) . ']]';
    }
    $u = uniqid($prefix . '_', true);
    $u = str_replace(array('.', ' '), '', $u);
    return '[[' . $u . ']]';
}

/**
 * 1) Перед типографом: прячет " -- " только внутри <code ...>...</code>
 *    Возвращает новый HTML, а уникальный токен кладёт в $ctx['ddash_token'].
 */
function protect_code_double_hyphen($html, &$ctx)
{
    // Уникальный токен на прогон (ASCII-only, чтобы типограф точно не "улучшил")
    if (empty($ctx['ddash_token'])) {
        /// $ctx['ddash_token'] = '[[DDASH_' . bin2hex(random_bytes(8)) . ']]';
        $ctx['ddash_token'] =  _typo_token('DDASH');
    }
    $token = $ctx['ddash_token'];

    $out = '';
    $pos = 0;

    while (true) {
        $start = stripos($html, '<code', $pos);
        if ($start === false) {
            $out .= substr($html, $pos);
            break;
        }

        // Всё до <code...
        $out .= substr($html, $pos, $start - $pos);

        // Конец открывающего тега <code ...>
        $tagEnd = strpos($html, '>', $start);
        if ($tagEnd === false) {
            // битый HTML — дописываем остаток как есть
            $out .= substr($html, $start);
            break;
        }

        $openTag = substr($html, $start, $tagEnd - $start + 1);
        $out .= $openTag;

        $contentStart = $tagEnd + 1;

        // Ищем закрывающий </code>
        $close = stripos($html, '</code>', $contentStart);
        if ($close === false) {
            // нет закрывающего тега — дописываем остаток как есть
            $out .= substr($html, $contentStart);
            break;
        }

        // Содержимое code (как сырой HTML/текст)
        $content = substr($html, $contentStart, $close - $contentStart);

        // Прячем только точное " -- " (пробелы по краям)
        $content = str_replace(' -- ', $token, $content);

        $out .= $content;
        $out .= '</code>';

        $pos = $close + 7; /// strlen('</code>');
    }

    return $out;
}

/**
 * 2) После типографа: возвращает плейсхолдер обратно в " -- "
 */
function restore_code_double_hyphen($html, $ctx)
{
    $token = isset($ctx['ddash_token']) ? $ctx['ddash_token'] : '';
    if ($token === '') {
        return $html;
    }
    return str_replace($token, ' -- ', $html);
}

/**
 * Быстрый кавычкер «ёлочки» со скоупом по HTML-тегам.
 * Оптимизация: пишет в выход кусками.
 * Важно: распознаёт только "&quot;" (нижний регистр), без "&QUOT;".
 */
function typograph_guillemets($html)
{
    $len = strlen($html);
    $chunks = array();

    $stack = array();
    $stack[] = array('tag' => null, 'open' => false, 'lastOpenIdx' => null, 'lastOpenTok' => null);

    static $void = null;
    if ($void === null) {
        $void = array(
            'area'=>1,'base'=>1,'br'=>1,'col'=>1,'embed'=>1,'hr'=>1,'img'=>1,'input'=>1,'link'=>1,
            'meta'=>1,'param'=>1,'source'=>1,'track'=>1,'wbr'=>1
        );
    }

    $i = 0;
    while ($i < $len) {

        // Быстро скидываем обычный текст куском до ближайшего спец-символа (<, ", &)
        $span = strcspn($html, '<"&', $i);
        if ($span > 0) {
            $chunks[] = substr($html, $i, $span);
            $i += $span;
            continue;
        }

        $ch = $html[$i];

        // ── 1) ТЕГ / КОММЕНТ / DOCTYPE / PI ─────────────────────────────
        if ($ch === '<') {

            // HTML comment <!-- ... -->
            if ($i + 4 <= $len && substr($html, $i, 4) === '<!--') {
                $end = strpos($html, '-->', $i + 4);
                if ($end === false) {
                    $chunks[] = substr($html, $i);
                    break;
                }
                $chunks[] = substr($html, $i, $end - $i + 3);
                $i = $end + 3;
                continue;
            }

            // Ищем '>' с учётом кавычек в атрибутах
            $j = $i + 1;
            $q = null;
            while ($j < $len) {
                $c = $html[$j];
                if ($q !== null) {
                    if ($c === $q) $q = null;
                } else {
                    if ($c === '"' || $c === "'") $q = $c;
                    else if ($c === '>') break;
                }
                $j++;
            }

            if ($j >= $len) {
                $chunks[] = substr($html, $i);
                break;
            }

            $tag = substr($html, $i, $j - $i + 1);
            $chunks[] = $tag;

            // Декларации пропускаем
            $tag2 = ltrim($tag);
            if (isset($tag2[1]) && ($tag2[1] === '!' || $tag2[1] === '?')) {
                $i = $j + 1;
                continue;
            }

            $isClose = (isset($tag2[1]) && $tag2[1] === '/');
            $trimTag = rtrim($tag);
            $isSelf  = (substr($trimTag, -2) === '/>');

            // Имя тега
            $name = '';
            $tlen = strlen($tag2);

            if ($isClose) {
                $k = 2;
                while ($k < $tlen && $tag2[$k] === ' ') $k++;
                while ($k < $tlen) {
                    $c = $tag2[$k];
                    if ($c === '>' || $c === ' ' || $c === "\t" || $c === "\r" || $c === "\n" || $c === '/') break;
                    $name .= $c;
                    $k++;
                }
            } else {
                $k = 1;
                while ($k < $tlen && $tag2[$k] === ' ') $k++;
                while ($k < $tlen) {
                    $c = $tag2[$k];
                    if ($c === '>' || $c === ' ' || $c === "\t" || $c === "\r" || $c === "\n" || $c === '/') break;
                    $name .= $c;
                    $k++;
                }
            }

            $nameLower = strtolower($name);

            if ($isClose) {
                $match = -1;
                for ($s = count($stack) - 1; $s > 0; $s--) {
                    if ($stack[$s]['tag'] === $nameLower) { $match = $s; break; }
                }

                if ($match !== -1) {
                    for ($s = count($stack) - 1; $s >= $match; $s--) {
                        if ($stack[$s]['open'] && $stack[$s]['lastOpenIdx'] !== null) {
                            $idx = $stack[$s]['lastOpenIdx'];
                            $tok = $stack[$s]['lastOpenTok'];
                            $chunks[$idx] = ($tok !== null) ? $tok : '"';
                        }
                    }
                    while (count($stack) > $match) {
                        array_pop($stack);
                    }
                }

            } else {
                if (!$isSelf && $nameLower !== '' && !isset($void[$nameLower])) {
                    $stack[] = array('tag' => $nameLower, 'open' => false, 'lastOpenIdx' => null, 'lastOpenTok' => null);
                }
            }

            $i = $j + 1;
            continue;
        }

        // ── 2) ТЕКСТ: " и &quot; ─────────────────────────────────────────
        $top = count($stack) - 1;

        // Только &quot; (строго нижний регистр)
        if ($ch === '&' && $i + 6 <= $len) {
            $cand = substr($html, $i, 6);
            if ($cand === '&quot;') {
                if (!$stack[$top]['open']) {
                    $chunks[] = '«';
                    $stack[$top]['open'] = true;
                    $stack[$top]['lastOpenIdx'] = count($chunks) - 1;
                    $stack[$top]['lastOpenTok'] = '&quot;'; // фиксировано
                } else {
                    $chunks[] = '»';
                    $stack[$top]['open'] = false;
                    $stack[$top]['lastOpenIdx'] = null;
                    $stack[$top]['lastOpenTok'] = null;
                }
                $i += 6;
                continue;
            }

            $chunks[] = '&';
            $i++;
            continue;
        }

        // обычная "
        if ($ch === '"') {
            if (!$stack[$top]['open']) {
                $chunks[] = '«';
                $stack[$top]['open'] = true;
                $stack[$top]['lastOpenIdx'] = count($chunks) - 1;
                $stack[$top]['lastOpenTok'] = '"';
            } else {
                $chunks[] = '»';
                $stack[$top]['open'] = false;
                $stack[$top]['lastOpenIdx'] = null;
                $stack[$top]['lastOpenTok'] = null;
            }
            $i++;
            continue;
        }

        $chunks[] = $ch;
        $i++;
    }

    // Финальный откат всех уровней
    for ($s = count($stack) - 1; $s >= 0; $s--) {
        if ($stack[$s]['open'] && $stack[$s]['lastOpenIdx'] !== null) {
            $idx = $stack[$s]['lastOpenIdx'];
            $tok = $stack[$s]['lastOpenTok'];
            $chunks[$idx] = ($tok !== null) ? $tok : '"';
        }
    }

    return implode('', $chunks);
}

/**
 * Перед типографом: прячет кавычки внутри <code>:
 *   1) "      -> DQUOTE token
 *   2) &quot;  -> EQUOT token (ТОЛЬКО нижний регистр, без &QUOT;)
 *
 * Хранит токены в $ctx['dquote_token'], $ctx['equot_token']
 */
function protect_code_quotes($html, &$ctx)
{
    if (empty($ctx['dquote_token'])) $ctx['dquote_token'] = _typo_token('DQUOTE');
    if (empty($ctx['equot_token']))  $ctx['equot_token']  = _typo_token('EQUOTE');

    $dq = $ctx['dquote_token'];
    $eq = $ctx['equot_token'];

    $out = '';
    $pos = 0;

    while (true) {
        $start = stripos($html, '<code', $pos);
        if ($start === false) {
            $out .= substr($html, $pos);
            break;
        }

        $out .= substr($html, $pos, $start - $pos);

        $tagEnd = strpos($html, '>', $start);
        if ($tagEnd === false) {
            $out .= substr($html, $start);
            break;
        }

        $out .= substr($html, $start, $tagEnd - $start + 1);

        $contentStart = $tagEnd + 1;

        $close = stripos($html, '</code>', $contentStart);
        if ($close === false) {
            $out .= substr($html, $contentStart);
            break;
        }

        $content = substr($html, $contentStart, $close - $contentStart);

        // 1) Прячем обычные "
        $content = str_replace('"', $dq, $content);

        // 2) Прячем только &quot; (нижний регистр)
        $content = str_replace('&quot;', $eq, $content);

        $out .= $content;
        $out .= '</code>';

        $pos = $close + 7; // strlen('</code>') == 7
    }

    return $out;
}

/**
 * После типографа: возвращает кавычки внутри <code> обратно.
 */
function restore_code_quotes($html, $ctx)
{
    $dq = isset($ctx['dquote_token']) ? $ctx['dquote_token'] : '';
    $eq = isset($ctx['equot_token'])  ? $ctx['equot_token']  : '';

    if ($dq !== '') {
        $html = str_replace($dq, '"', $html);
    }

    if ($eq !== '') {
        $html = str_replace($eq, '&quot;', $html);
    }

    return $html;
}

/**
 * Добавляет неразрывные пробелы к русским предлогам, союзам, сокращениям и частицам.
 *
 * @param string $text          Входной текст
 * @param bool   $useHtmlNbsp   true  = использовать "&nbsp;"
 *                              false = использовать U+00A0 (символ NBSP)
 *
 * @return string
 */
function ru_nbsp_typograf(string $text, bool $useHtmlNbsp = false): string
{

    $ctx = array();

    $text = protect_code_double_hyphen($text, $ctx);

    $text = protect_code_quotes($text, $ctx);   // новая: " -> токен

    // Неразрывный пробел и тире
    $nbsp  = $useHtmlNbsp ? '&nbsp;' : "\u{00A0}";
    $mdash = $useHtmlNbsp ? '&mdash;' : '—';

    // ── 1) Устойчивые обороты, сокращения, тире ────────────────────
    //    (без предлогов/союзов — их обрабатываем отдельно ниже)
    $search = [
        // 1) Устойчивые обороты (строчные)
        ' т. е.',
        ' т.е.',
        ' т. к.',
        ' т.к.',
        ' т. о.',
        ' т.о.',
        ' и т. д.',
        ' и т.д.',
        ' и т. п.',
        ' и т.п.',
        ' в т. ч.',
        ' в т.ч.',

        // 3A) Сокращения, прилипают к ПРЕДЫДУЩЕМУ слову/числу (NBSP до сокращения)
        ' г.',
        ' гг.',

        // 3B) Сокращения, прилипают к СЛЕДУЮЩЕМУ слову/числу (NBSP после сокращения)
        ' ул. ',
        ' просп. ',
        ' пр-т ',
        ' д. ',
        ' кв. ',
        ' рис. ',
        ' табл. ',
        ' ст. ',
        ' стр. ',
        ' гл. ',
        ' им. ',
        ' № ',

        // 4) &mdash; (типографское тире)
        ' -- ',
        // '&amp;@amp;',
    ];

    $replace = [
        // 1) Устойчивые обороты: NBSP между частями
        ' т.' . $nbsp . 'е.',
        ' т.' . $nbsp . 'е.',
        ' т.' . $nbsp . 'к.',
        ' т.' . $nbsp . 'к.',
        ' т.' . $nbsp . 'о.',
        ' т.' . $nbsp . 'о.',
        ' и' . $nbsp . 'т.' . $nbsp . 'д.',
        ' и' . $nbsp . 'т.' . $nbsp . 'д.',
        ' и' . $nbsp . 'т.' . $nbsp . 'п.',
        ' и' . $nbsp . 'т.' . $nbsp . 'п.',
        ' в' . $nbsp . 'т.' . $nbsp . 'ч.',
        ' в' . $nbsp . 'т.' . $nbsp . 'ч.',

        // 3A) Липнут к предыдущему: 2025&nbsp;г., 1941–1945&nbsp;гг.
        $nbsp . 'г.',
        $nbsp . 'гг.',

        // 3B) Липнут к следующему: ул.&nbsp;Ленина, рис.&nbsp;5, №&nbsp;7
        ' ул.'   . $nbsp,
        ' просп.'. $nbsp,
        ' пр-т'  . $nbsp,
        ' д.'    . $nbsp,
        ' кв.'   . $nbsp,
        ' рис.'  . $nbsp,
        ' табл.' . $nbsp,
        ' ст.'   . $nbsp,
        ' стр.'  . $nbsp,
        ' гл.'   . $nbsp,
        ' им.'   . $nbsp,
        ' №'     . $nbsp,

        // 4) тире: неразрывный пробел + тире + обычный пробел
        $nbsp . $mdash . ' ',
        // '&amp;',
    ];

    // Сначала фиксируем устойчивые конструкции, чтобы потом не разломать их предлогами
    $text = str_replace($search, $replace, $text);

    // ── 2) Короткие союзы/предлоги ─────────────────────────────────
    //    - ' в '      → ' в&nbsp;'
    //    - 'В '       → 'В&nbsp;'
    //    - '&nbsp;в ' → '&nbsp;в&nbsp;'
    //    - '&nbsp;В ' → '&nbsp;В&nbsp;'
    //
    //    Цепочки "но с ним", "и в то же время" → "но&nbsp;с&nbsp;ним", "и&nbsp;в&nbsp;то&nbsp;же&nbsp;время".

    $shortSpecs = [
        ['а',   'А'],
        ['и',   'И'],
        ['но',  'Но'],
        ['или', 'Или'],
        ['да',  'Да'],
        ['в',   'В'],
        ['к',   'К'],
        ['с',   'С'],
        ['у',   'У'],
        ['о',   'О'],
        ['об',  'Об'],
        ['от',  'От'],
        ['по',  'По'],
        ['из',  'Из'],
        ['за',  'За'],
        ['над', 'Над'],
        ['под', 'Под'],
        ['при','При'],
        ['для','Для'],
        ['без','Без'],
        ['на', 'На'],
        ['ни', 'Ни'],
        ['не', 'Не'],
        ['во', 'Во'],
        ['со',  'Со'],
        ['ко',  'Ко'],
        ['обо', 'Обо'],
        ['безо','Безо'],
    ];

    $searchWords  = [];
    $replaceWords = [];

    foreach ($shortSpecs as [$lower, $upper]) {
        // 2A) Строчная внутри текста: ' в ' → ' в&nbsp;'
        $searchWords[]  = ' ' . $lower . ' ';
        $replaceWords[] = ' ' . $lower . $nbsp;

        // 2B) Заглавная в начале предложения: 'В ' → 'В&nbsp;'
        $searchWords[]  = $upper . ' ';
        $replaceWords[] = $upper . $nbsp;

        // 2C) Уже склеенное слева слово: '&nbsp;в ' → '&nbsp;в&nbsp;'
        $searchWords[]  = $nbsp . $lower . ' ';
        $replaceWords[] = $nbsp . $lower . $nbsp;

        // 2D) То же для заглавной: '&nbsp;В ' → '&nbsp;В&nbsp;'
        $searchWords[]  = $nbsp . $upper . ' ';
        $replaceWords[] = $nbsp . $upper . $nbsp;
    }

    $text = str_replace($searchWords, $replaceWords, $text);

    // ── 3) Частицы "же", "ли", "бы", "б" ───────────────────────────
    // NBSP СЛЕВА: "как же выйти" → "как&nbsp;же выйти"

    $text = preg_replace(
        "/ (же\b|ли\b|бы\b|б\b|&mdash;|—|&ndash;|–)/u",
        $nbsp . '$1',
        $text
    );

    $text = typograph_guillemets($text);             // «ёлочки»

    $text = restore_code_double_hyphen($text, $ctx);

    $text = restore_code_quotes($text, $ctx);

    return $text;
}

/**
 * Шаблон: {{nobr|ТЕКСТ}}
 * Вывод:  <span class="nobr">ТЕКСТ</span>
 *
 * Можно прогонять по HTML статьи перед выводом.
 */
function tpl_nobr(string $text): string
{
    return preg_replace_callback(
        '/\{\{nobr\|(.+?)\}\}/us',
        static function ($m) {
            return '<span class="nobr">' . $m[1] . '</span>';
        },
        $text
    );
}

function obyava() {

    global $checkpermission, $csrf;

    $obfile = "DATABASE/obyava.txt";

    $br = "<br /><br />";
    $obstring = "";

    if (is_file($obfile) && filesize($obfile) > 4) {

        $obstring = getFileOrDie($obfile);

        $obstring = normalize_entities_my($obstring);

        /* $obstring = str_ireplace(
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
            $obstring
        ); */

        $obstring = escape_amp_txtarea($obstring);

        $html = str_get_html($obstring, false, true, "UTF-8", false) or die("XSS?.. Пустой или битый HTML.");

        $html = replaceSemanticSpans($html);

        $obstring = $html->save();

        /* $obstring = str_ireplace(
            ['&@lt;', '&@gt;', '&@quot;', '&@apos;', '&@amp;'],
            ['&lt;',  '&gt;',  '&quot;',  '&#039;',   '&amp;'],
            $obstring
        ); */

        // $obstring = typograph_guillemets($obstring);
        $obstring = ru_nbsp_typograf($obstring);

        $obstring = "<aside id='obyava' class='clearfix' aria-label='Объявление'>$obstring</aside>";

    } else {

        $br = "";
        $obstring = "<hr />";
    }

    if ($checkpermission > 2) {

        $obstring .= "<div id='obyavadiv'><a href='?gobyava=1&amp;csrf=$csrf'>редактировать объявление</a></div>";

        // $obstring .= ($br ? "" : "<br /><br />");

        $br = "<br /><br />";
    }

    $obstring .= $br;

    return $obstring;
}












function remEmptyLi($textedit) {

    // Удаляем <br /> и пробелы перед списками и закрытием li
    $textedit = preg_replace(
        '~(?:[\p{Zs}\s]*<br\s*/?>[\p{Zs}\s]*)+(<ul\b|<ol\b|</li>)~ui',
                             '$1',
                             $textedit
    );

    $textedit = preg_replace(
        '~(?:[\p{Zs}\s])+(<ul\b|<ol\b|</li>)~ui',
                             '$1',
                             $textedit
    );

    // Удаляем пустые li
    $textedit = preg_replace(
        '~<li\b[^>]*>(?:[\p{Zs}\s]|<br\s*/?>)*</li>~ui',
                             '',
                             $textedit
    );

    // Удаляем пустые ul/ol
    $textedit = preg_replace(
        '~<(ul|ol)\b[^>]*>(?:[\p{Zs}\s]|<br\s*/?>)*</\1>~ui',
                             '',
                             $textedit
    );

    return $textedit;
}












function validateHex40(string $hex): void {

    if(strlen($hex) !== 40 || preg_match('/[^a-f0-9]/', $hex)) {
        die('PANIC: invalid hex');
    }
}










function concater(string $fname1, string $fname2, int $pos): void
{
    if(trim($fname1) === '' || trim($fname2) === '') {
        die('<h1>CONCATER PANIC :: empty filename</h1>');
    }

    $root = getcwd();

    if($root === false) {
        die('<h1>CONCATER PANIC :: getcwd failed</h1>');
    }

    $root = rtrim($root, '/');

    $new    = $root . '/' . ltrim($fname1, '/') . '.tmp';
    $fname1 = $root . '/' . ltrim($fname1, '/');
    $fname2 = $root . '/' . ltrim($fname2, '/');

    $cleanup = static function() use ($new, $fname1, $fname2): void {
        @unlink($new);    // промежуточный .tmp
        @unlink($fname1); // временный файл-приёмник
        @unlink($fname2); // временный файл-источник хвоста
    };

    $panic = static function(string $msg) use ($cleanup): never {
        $cleanup();
        die('<h1>CONCATER PANIC :: ' . htmlspecialchars($msg, ENT_QUOTES | ENT_HTML401, 'UTF-8') . '</h1>');
    };

    /*
    if(!function_exists('exec')) {
        $panic('exec disabled');
    }
    */

    if($pos < 0) {
        $panic('bad byte position');
    }

    if(!is_file($fname1) || !is_readable($fname1)) {
        $panic('fname1 is not readable');
    }

    if(!is_file($fname2) || !is_readable($fname2)) {
        $panic('fname2 is not readable');
    }

    if(!is_writable(dirname($fname1))) {
        $panic('target directory is not writable');
    }

    if(is_file($new)) {
        @unlink($new);
    }

    /*
     * $pos — 0-based байтовая позиция.
     *
     * $pos = 0              => взять $fname2 с самого начала
     * $pos = 100            => взять $fname2 со 101-го байта
     * $pos >= filesize(...) => tail даст пустой хвост, и это нормально
     *
     * GNU tail -c +N считает с 1, поэтому +1.
     */
    $tailStart = $pos + 1;

    $cmd =
    '{ /usr/bin/cat -- ' . escapeshellarg($fname1) .
    ' && /usr/bin/tail -c +' . (int)$tailStart . ' -- ' . escapeshellarg($fname2) .
    '; } > ' . escapeshellarg($new);

    $shellOutput = [];

    exec($cmd, $shellOutput, $exitCode);

    if($exitCode !== 0 || !is_file($new)) {
        $panic('cat/tail failed');
    }

    if(!@rename($new, $fname1)) {
        $panic('rename failed');
    }
}

















function gnuCatTailAvailable(): bool
{
    static $cached = null;

    if($cached !== null) {
        return $cached;
    }

    if(!function_exists('exec')) {
        return $cached = false;
    }

    if(PHP_OS_FAMILY === 'Windows') {
        return $cached = false;
    }

    $shellOutput = [];
    $exitCode = 1;

    exec(
        '/usr/bin/cat --version >/dev/null 2>&1 && /usr/bin/tail --version >/dev/null 2>&1',
        $shellOutput,
        $exitCode
    );

    return $cached = ($exitCode === 0);
}







function sha512FoldXor(string $text): string
{
    // SHA-512: 128 шестнадцатеричных символов
    $hash = hash('sha512', $text);
    
    // Две половины по 64 символа — по 256 бит
    $firstPart  = substr($hash, 0, 64);
    $secondPart = substr($hash, 64, 64);
    
    // Переворачиваем вторую половину как строку
    $secondPart = strrev($secondPart);
    
    // Переводим обе половины в бинарный вид и выполняем XOR
    $result = hex2bin($firstPart) ^ hex2bin($secondPart);
    
    // Бинарный результат преобразуем обратно в hex
    $result = bin2hex($result);

    // Возвращаем 256-битный результат как 78 dec-символа
    return str_pad(
        gmp_strval(gmp_init($result, 16), 10),
                   78,
                   '0',
                   STR_PAD_LEFT
    );
}







function shuffleString(string $text, string $seed): string
{
    require_once 'SYSTEM/PHPLIB/fisher-yates-seeded-shuffle/seeded.shuffle.class.php';
    
    if($seed === '' || preg_match('/\A[0-9]+\z/', $seed) !== 1) {
        throw new InvalidArgumentException(
            'Seed должен быть непустой строкой из цифр.'
        );
    }
    
    if($text === '') {
        return '';
    }
    
    return seeded_shuffle::shuffle($text, $seed);
}

