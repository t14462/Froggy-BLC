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



$ip = $_SERVER['REMOTE_ADDR'] ?? "0.0.0.0";

// Дополнительно можно добавить валидацию IP
if(!filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = "0.0.0.0"; // Неизвестный IP
}







$url = sprintf(
    "%s://%s%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['SERVER_NAME'],
    explode("?", $_SERVER['REQUEST_URI'])[0]
  );







if(isset($_SESSION["username"]) && isset($_SESSION["userhash"])) {
    $username = $_SESSION["username"];
    $userhash = $_SESSION["userhash"];

    if(array_key_exists($username, $cred) && $userhash === hash('sha512', explode("<!!!>", $cred[$username])[1].$ip.$userAgent)) {
        $checkpermission = (int)explode("<!!!>", $cred[$username])[0];
    } else {
        $checkpermission = 0;
    }
} else {
        $checkpermission = 0;
}






function is_arrays_equal(array $a, array $b, int $sortMethod = SORT_STRING): bool {
    $a_copy = $a;
    $b_copy = $b;
    ksort($a_copy, $sortMethod);
    ksort($b_copy, $sortMethod);
    return $a_copy === $b_copy;
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
    touch($file, (int)$timeToWrite);
    return file_put_contents($timeFile, sprintf('%.6f', $timeToWrite), LOCK_EX) !== false;
}


/**
 * Читает microtime из ПУТЬ_К_ФАЙЛУ.time
 * Если .time нет — берёт значение filemtime().
 * Если и целевого файла нет — возвращает 0.0 .
 */
function filemtimeMy(string $file): float {
    $timeFile = $file . '.time';

    if (is_file($timeFile)) {

        $tmp = fopenOrDie($timeFile, 'rb');
        @flock($tmp, LOCK_SH);

        $contents = stream_get_contents($tmp);

        /// $contents = file_get_contents($timeFile);

        @flock($tmp, LOCK_UN);
        fclose($tmp);

        return (float)trim($contents);

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
    public function fgetsOrDie(string $context = 'unknown'): string|false {

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
        $this->seek($line);
        if(!$this->valid()) {
            $context = $this->getContext();
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









function set_cookie($name, $value, $expires){
    $params = [
        'expires' => $expires,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    setcookie($name, $value, $params);
}









function mb_superTrim(string $text): string {

    // 1. Все пробельные сущности
    $text = str_ireplace(
        [
            // --------------------
            // Категория Zs (Separator, Space)
            // --------------------
            "&nbsp;", "&NonBreakingSpace;", "&#160;", "&#xA0;",   // NO-BREAK SPACE (U+00A0)
            "&#32;", "&#x20;",                                  // SPACE (U+0020) — именованной нет
            "&ensp;", "&#8194;", "&#x2002;",                    // EN SPACE (U+2002)
            "&emsp;", "&#8195;", "&#x2003;",                    // EM SPACE (U+2003)
            "&thinsp;", "&ThinSpace;", "&#8201;", "&#x2009;",   // THIN SPACE (U+2009)
            "&hairsp;", "&VeryThinSpace;", "&#8202;", "&#x200A;", // HAIR SPACE (U+200A)
            "&emsp13;", "&#8196;", "&#x2004;",                  // THREE-PER-EM SPACE (U+2004)
            "&emsp14;", "&#8197;", "&#x2005;",                  // FOUR-PER-EM SPACE (U+2005)
            "&#8198;", "&#x2006;",                              // SIX-PER-EM SPACE (U+2006) — имени нет
            "&numsp;", "&#8199;", "&#x2007;",                   // FIGURE SPACE (U+2007)
            "&puncsp;", "&#8200;", "&#x2008;",                  // PUNCTUATION SPACE (U+2008)
            // NBSP узкий: имени в HTML нет; &nbspn; ниже — несуществующая сущность, оставлена как у тебя
            "&nbspn;", "&#8239;", "&#x202F;",                   // NARROW NO-BREAK SPACE (U+202F) — имени нет
            "&MediumSpace;", "&#8287;", "&#x205F;",             // MEDIUM MATHEMATICAL SPACE (U+205F)
            "&ThickSpace;",                                     // THICK SPACE ≈ U+205F + U+200A (MMSP + HAIR) — алиас
            "&#12288;", "&#x3000;",                             // IDEOGRAPHIC SPACE (U+3000) — имени нет

            // --------------------
            // Категория Zl (Line Separator) и Zp (Paragraph Separator)
            // --------------------
            "&#8232;", "&#x2028;",                              // LINE SEPARATOR (U+2028)
            "&#8233;", "&#x2029;",                              // PARAGRAPH SEPARATOR (U+2029)

            // --------------------
            // Категория Cf (Format)
            // --------------------
            "&#65279;", "&#xFEFF;",                             // BOM / ZWNBSP (U+FEFF) — имени нет
            "&ZeroWidthSpace;", "&#8203;", "&#x200B;",          // ZERO WIDTH SPACE (U+200B)
            // «Негативные» пробелы — HTML сводит к ZWSP:
            "&NegativeVeryThinSpace;",                          // → U+200B (ZWSP)
            "&NegativeThinSpace;",                              // → U+200B (ZWSP)
            "&NegativeMediumSpace;",                            // → U+200B (ZWSP)
            "&NegativeThickSpace;",                             // → U+200B (ZWSP)

            "&NoBreak;", "&#8288;", "&#x2060;",                 // WORD JOINER (U+2060)
            "&#6158;", "&#x180E;",                              // MONGOLIAN VOWEL SEPARATOR (U+180E, deprecated) — имени нет
            "&zwnj;", "&ZeroWidthNonJoiner;", "&#8204;", "&#x200C;", // ZWNJ (U+200C)
            /// "&zwj;",  "&ZeroWidthJoiner;",  "&#8205;", "&#x200D;",   // ZWJ (U+200D)
            "&lrm;", "&LeftToRightMark;", "&#8206;", "&#x200E;",     // LRM (U+200E)
            "&rlm;", "&RightToLeftMark;", "&#8207;", "&#x200F;",     // RLM (U+200F)

            // Управляющие bidi/шейпинга (Cf), устаревшие; именованных нет:
            "&#8294;", "&#x206A;",  // INHIBIT SYMMETRIC SWAPPING (U+206A)
            "&#8295;", "&#x206B;",  // ACTIVATE SYMMETRIC SWAPPING (U+206B)
            "&#8296;", "&#x206C;",  // INHIBIT ARABIC FORM SHAPING (U+206C)
            "&#8297;", "&#x206D;",  // ACTIVATE ARABIC FORM SHAPING (U+206D)
            "&#8298;", "&#x206E;",  // NATIONAL DIGIT SHAPES (U+206E)
            "&#8299;", "&#x206F;",  // NOMINAL DIGIT SHAPES (U+206F)

            // --------------------
            // Управляющие ASCII (Cc)
            // --------------------
            "&Tab;", "&#9;",  "&#x9;",                          // CHARACTER TABULATION (U+0009)
            "&NewLine;", "&#10;", "&#xA;",                      // LINE FEED (U+000A)
            "&#13;", "&#xD;",                                   // CARRIAGE RETURN (U+000D) — имени нет
        ],
        " ",
        $text
    );


    
    // 2. Удаляем прочие невидимые символы, но оставляем ZWJ
    $text = preg_replace_callback('/[\p{C}]/u', function ($m) {
        $ch = $m[0];

        // ZWJ (U+200D) — нужен для эмоджи типа 👩‍💻
        if ($ch === "\xE2\x80\x8D") { // 0x200D в UTF-8
            return $ch;
        }

        // Остальное выкидываем
        return ' ';
    }, $text);

    // 3. Удаляем Unicode-пробелы по краям
    $text = preg_replace('/^[\p{Z}]+|[\p{Z}]+$/u', '', $text);

    // 4. Нормализуем все пробельные символы внутри
    return preg_replace('/[\p{Z}]+/u', ' ', $text);
}










function mb_softTrim(string $text): string {
    
    // Удаляем прочие невидимые символы, но оставляем \n + ZWJ
    $text = preg_replace_callback('/[\p{C}]/u', function ($m) {
        $ch = $m[0];

        // \n оставляем
        if ($ch === "\n") {
            return "\n";
        }

        // ZWJ (U+200D) — нужен для эмоджи типа 👩‍💻
        if ($ch === "\xE2\x80\x8D") { // 0x200D в UTF-8
            return $ch;
        }

        // Остальное выкидываем
        return '';
    }, $text);
    

    $text = str_ireplace(
        [
            // --------------------
            // Категория Cf (Format, входит в \p{C})
            // --------------------
            // BOM (U+FEFF) — имени нет
            "&#65279;", "&#xFEFF;",

            // ZERO WIDTH SPACE (U+200B)
            "&ZeroWidthSpace;", "&#8203;", "&#x200B;",

            // WORD JOINER (U+2060)
            "&NoBreak;", "&#8288;", "&#x2060;",

            // MONGOLIAN VOWEL SEPARATOR (U+180E) — имени нет
            "&#6158;", "&#x180E;",

            // ZERO WIDTH NON-JOINER (U+200C)
            "&zwnj;", "&ZeroWidthNonJoiner;", "&#8204;", "&#x200C;",

            // ZERO WIDTH JOINER (U+200D)
            /// "&zwj;", "&ZeroWidthJoiner;", "&#8205;", "&#x200D;",

            // LEFT-TO-RIGHT MARK (U+200E)
            "&lrm;", "&LeftToRightMark;", "&#8206;", "&#x200E;",

            // RIGHT-TO-LEFT MARK (U+200F)
            "&rlm;", "&RightToLeftMark;", "&#8207;", "&#x200F;",

            // 206A–206F — имён нет
            "&#8294;", "&#x206A;",  // INHIBIT SYMMETRIC SWAPPING
            "&#8295;", "&#x206B;",  // ACTIVATE SYMMETRIC SWAPPING
            "&#8296;", "&#x206C;",  // INHIBIT ARABIC FORM SHAPING
            "&#8297;", "&#x206D;",  // ACTIVATE ARABIC FORM SHAPING
            "&#8298;", "&#x206E;",  // NATIONAL DIGIT SHAPES
            "&#8299;", "&#x206F;",  // NOMINAL DIGIT SHAPES

            // --------------------
            // Управляющие ASCII (Cc) как сущности
            // --------------------
            // CHARACTER TABULATION (U+0009)
            "&Tab;", "&#9;", "&#x9;",
            // LINE FEED (U+000A)
            "&NewLine;", "&#10;", "&#xA;",
            // CARRIAGE RETURN (U+000D) — имени нет
            "&#13;", "&#xD;",
        ],
        " ",
        $text
    );


    
    // Удаляем все пробельные и "невидимые" символы по краям, включая все Unicode-переносы
    $text = preg_replace('/^[\p{Z}]+|[\p{Z}]+$/u', '', $text);

    return $text;
}








// Регулярное выражение для замены шаблона {{youtube|ID|width}}
$patternYT = '/\{\{youtube\|([a-zA-Z0-9_-]+)(?:\|(\d+))?\}\}/';
$replacementYT = function ($matches) {
    $videoId = $matches[1] ?? "";

    // Задаем ширину iframe, если она указана, иначе по умолчанию 100%
    $width = (int)($matches[2] ?? 0);

    // Если ширина задана
    if($width > 32 && $width < 66) {
        return "<div class='vid-wrapper' style='width: {$width}%; float: right; clear: right;'><iframe src='https://www.youtube.com/embed/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    } else {
        // Если ширина не в процентах, возвращаем адаптивный вариант
        return "<div class='vid-wrapper' style='clear: both;'><iframe src='https://www.youtube.com/embed/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    }
};






// Регулярное выражение для замены шаблона {{vimeo|ID|width}}
$patternVimeo = '/\{\{vimeo\|([0-9]+)(?:\|(\d+))?\}\}/'; // ID в Vimeo всегда числовое
$replacementVimeo = function ($matches) {
    $videoId = $matches[1] ?? "";

    // Задаем ширину iframe, если она указана, иначе по умолчанию 100%
    $width = (int)($matches[2] ?? 0);

    // Если ширина задана
    if($width > 32 && $width < 66) {
        return "<div class='vid-wrapper' style='width: {$width}%; float: right; clear: right;'><iframe src='https://player.vimeo.com/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    } else {
        // Если ширина не в процентах, возвращаем адаптивный вариант
        return "<div class='vid-wrapper' style='clear: both;'><iframe src='https://player.vimeo.com/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    }
};



// Регулярное выражение для замены шаблона {{dailymotion|ID|width}}
$patternDM = '/\{\{dailymotion\|([a-zA-Z0-9]+)(?:\|(\d+))?\}\}/';
$replacementDM = function ($matches) {
    $videoId = $matches[1] ?? "";

    // Задаем ширину iframe, если она указана, иначе по умолчанию 100%
    $width = (int)($matches[2] ?? 0);

    // Если ширина задана
    if($width > 32 && $width < 66) {
        return "<div class='vid-wrapper' style='width: {$width}%; float: right; clear: right;'><iframe src='https://www.dailymotion.com/embed/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    } else {
        // Если ширина не в процентах, возвращаем адаптивный вариант
        return "<div class='vid-wrapper' style='clear: both;'><iframe src='https://www.dailymotion.com/embed/video/$videoId' class='vid-iframe' allowfullscreen='allowfullscreen' title='Видео. Возможно музыка или, например, конференция.' loading='lazy'></iframe></div>";
    }
};




// {{download|FILE}}
$patternDLCNT = '/\{\{download\|([^\}\r\n]+?)\}\}/u';
$replacementDLCNT = function ($m) {

    $raw = trim($m[1] ?? '');
    // последний сегмент пути, без подкаталогов + ваша фильтрация
    $file = basename(str_replace('\\', '/', $raw));
    $file = filter_filename($file);

    if (empty($file)) {
        return "<div style='background:#F00; color:#FFF; font-size: 3rem;'>ERR: bad file</div>";
    }

    $pathFile = "DATABASE/fupload/$file";
    $pathCnt  = "DATABASE/dl.count/$file.dlcnt";

    if (is_file($pathFile)) {

        $dlSize = filesize($pathFile) / 1024;

        $dlSize = round($dlSize, 2);

        $dlcnt = 0;
        if (is_file($pathCnt) && is_readable($pathCnt)) {
            $val = trim((string)@file_get_contents($pathCnt));
            if ($val !== '' && ctype_digit($val)) {
                $dlcnt = (int)$val;
            }
        }

        $safeName = htmlspecialchars($file, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        $dlcntFmt = number_format($dlcnt, 0, ',', ' ');

        $targetIframe = urlPrep2($safeName);

        $href = 'SYSTEM/modules/download.php?file=' . rawurlencode($file);
        return "<table class='dl-tpl'><tbody>
        <tr><td colspan='2'><a href=\"{$href}\" target='auxFrame-$targetIframe'>Скачать <strong>{$safeName}</strong></a></td></tr>

        <tr><td class='a3'>Размер на диске: </td><td class='a4'>{$dlSize} КиБ</td></tr>

        <tr><td class='a3'>Всего загрузок: </td><td class='a4'>{$dlcntFmt}<iframe name='auxFrame-$targetIframe' width='15' height='15' style='background: transparent'></iframe></td></tr>

        </tbody></table>";

    } else {

        return "<div style='background:#F00; color:#FFF; font-size: 3rem;'>ERR: File 404</div>";
    }
};

// Применение:
// $html = preg_replace_callback($patternDLCNT, $replacementDLCNT, $html);









function emojiToHtmlEntities(string $string): string {
    return preg_replace_callback('/\X/u', function ($m) {
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
    // Генерируем исходный хэш пароля
    $passwordHash = hex2bin(hash('sha512', $password));
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
        $pos   = array_search($position, array_keys($array));
        $array = array_merge(
            array_slice($array, 0, $pos),
            $insert,
            array_slice($array, $pos)
        );
    }
}



function filterUsername($username) {

    $filteredUsername = emojiToHtmlEntities($username);

    $filteredUsername = remove_entities($filteredUsername);

    // Убираем лишние пробелы
    $filteredUsername = mb_superTrim($filteredUsername);


    // Удаляем все символы, которые не являются:
    // - буквами любых языков (\p{L})
    // - цифрами (0-9)
    // - пробелами
    // - знаками пунктуации (\p{P})
    // - символами (валюты, знаки) (\p{S})

    $filteredUsername = preg_replace('/[^\p{L}0-9 \p{P}\p{S}]+/u', '', $filteredUsername);

    $filteredUsername = htmlspecialchars($filteredUsername, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);

    /*
    // Проверяем, не пустой ли результат
    if(empty($username)) {
        return false;
    }
    */

    // Проверка длины имени пользователя
    if(mb_strlen($filteredUsername) < 3 || mb_strlen($filteredUsername) > 25) {
        return false;
    }

    // Если имя пользователя не изменилось, возвращаем true
    if($filteredUsername === $username) {
        return true;
    }

    return false;
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
        $sessionData = json_decode(getFileOrDie($sessionFile), true);

        // Проверка на совпадение с предыдущими вводами
        if(in_array($userInput, $sessionData['captchas'] ?? [])) {
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
        $lockData = json_decode(getFileOrDie($lockFile), true);
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








function refreshhandle($time, $link, $update=true) {

    global $head;


    if($update) {

        refreshCaches();

        unlockByName($_SESSION['username'] ?? "");
    }

    if($time > 0) {

        $head .= "\n<noscript><meta http-equiv='refresh' content='$time;url=$link' /></noscript>
        <script>
            function addrrefr() {
                setTimeout(function() {
                    window.location.replace('$link');
                }, $time * 1000);
            }
            window.addEventListener('DOMContentLoaded', addrrefr);
        </script>\n";
        
    } else {

        exit("<!DOCTYPE html><html><head><noscript><meta http-equiv='refresh' content='0;url=$link' /></noscript><script>document.addEventListener('DOMContentLoaded', function() { window.location.replace('$link'); });</script></head><body>&nbsp;</body></html>");
    }
}








function filter_filename(string $filename): string {
    $filename = emojiToHtmlEntities($filename);
    $filename = remove_entities($filename);
    $filename = mb_superTrim($filename);

    $filename = preg_replace('~
        [<>:"/\\\\|?*]      |  # reserved
        [\x00-\x1F]         |  # control
        [\x7F\xA0\xAD]      |  # DEL, NBSP, SHY
        [#\[\]@!$&\'()+,;=] |  # URI reserved
        [{}^\~`]               # URL-unsafe
    ~x', '-', $filename);

    $filename = str_replace(' ', '_', $filename);
    $filename = preg_replace(['/-{2,}/', '/\.{2,}/', '/_{2,}/'], ['-', '.', '_'], $filename);
    $filename = trim($filename, ". \t\r\n\0\x0B-_");

    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    $limit = ($ext !== '') ? max(1, 255 - (strlen($ext) + 1)) : 255;
    $base  = mb_strcut($base, 0, $limit, 'UTF-8');

    return ($ext !== '') ? ($base . '.' . $ext) : $base;
}





function dbprepApnd($filename) {

    if(function_exists('ignore_user_abort')) {
        ignore_user_abort(true); // Установить игнорирование разрыва соединения
    }

    // $changeTime = filemtimeMy($filename);

    // global $errmsg, $content;

    $lockFile = $filename . ".lock";
    $newFile = $filename . ".new." . getmypid();
    // $dummyFile = "SYSTEM/modules/dummy.txt";

    /*
    // Попытка получить значение max_execution_time, если не удалось, использовать 8
    $maxWaitTime = (ini_get('max_execution_time') !== false) ? ((int)ini_get('max_execution_time') - 5) : 8;

    $tmark = time();

    $recovery = str_ireplace("<textarea", "&lt;textarea", $recovery);
    $recovery = str_ireplace("</textarea", "&lt;/textarea", $recovery);
    $recovery = str_ireplace("textarea>", "textarea&gt;", $recovery);
    
    $recovery = str_ireplace("<br!>", "\n", $recovery);

    $recovery = escape_amp_txtarea($recovery);
    /// $recovery = str_ireplace("&", "&amp;", $recovery);
    /// $recovery = str_ireplace("&amp;amp;", "&amp;", $recovery);
    
    while(is_file($lockFile)) {
        ///usleep(200000);

        usleep(100000);

        if((time() - $tmark) > $maxWaitTime) {
            // rename($lockFile, $lockFile . ".del"); // Перемещение файла блокировки

            if(is_file($lockFile)) unlink($lockFile);

            die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>DEADLOCK RECOVERY</title></head><body style="height: 100%; overflow: hidden;"><h1>DEADLOCK RECOVERY</h1><textarea style="width: 95%; min-height: 90vh; padding: 2%; resize: none;" readonly="readonly">'.$recovery.'</textarea></body></html>');
        }

        usleep(100000);
    }


    if($changeTime !== filemtimeMy($filename)) {

        $errmsg = "<h1>RECOVERY.</h1><p class='big'><strong>База Данных была изменена внешним процессом.</strong></p>";

        $content = "<textarea style='width: 95%; min-height: 65vh; padding: 2%; resize: none;' readonly='readonly'>".$recovery."</textarea>";

        return false;
    }
    */

    // Создаем файл блокировки
    /*
    if(!copy($dummyFile, $lockFile)) {
        die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>Ошибка</title></head><body><h1>ОШИБКА.</h1><p><strong>Не удалось создать файл блокировки.</strong></p></body></html>');
    }
    */
    putFileOrDie($lockFile, getmypid(), LOCK_EX);

    // Копируем оригинальный файл в новый
    if(!copy($filename, $newFile)) {
        die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>Ошибка</title></head><body><h1>ОШИБКА.</h1><p><strong>Не удалось создать новый файл.</strong></p></body></html>');
    }

    return true;
}





function dbprepCache($filename) {

    if(function_exists('ignore_user_abort')) {
        ignore_user_abort(true); // Установить игнорирование разрыва соединения
    }

    
    // $changeTime = filemtimeMy($filename);
    

    // global $errmsg, $content;

    $lockFile = $filename . ".lock";
    // $newFile = $filename . ".new." . getmypid();
    // $dummyFile = "SYSTEM/modules/dummy.txt";

    /*
    // Попытка получить значение max_execution_time, если не удалось, использовать 8
    $maxWaitTime = (ini_get('max_execution_time') !== false) ? ((int)ini_get('max_execution_time') - 5) : 8;

    $tmark = time();

    // $recovery = str_ireplace("<textarea", "&lt;textarea", $recovery);
    // $recovery = str_ireplace("</textarea", "&lt;/textarea", $recovery);
    // $recovery = str_ireplace("textarea>", "textarea&gt;", $recovery);

    while(is_file($lockFile)) {
        ///usleep(200000);

        usleep(100000);

        if((time() - $tmark) > $maxWaitTime) {
            // rename($lockFile, $lockFile . ".del"); // Перемещение файла блокировки

            if(is_file($lockFile)) unlink($lockFile);

            ///die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>DEADLOCK RECOVERY</title></head><body><h1>DEADLOCK RECOVERY</h1></body></html>');
            return false;
        }

        usleep(100000);
    }


    
    if($changeTime !== filemtimeMy($filename)) {

        // $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>База Данных была изменена внешним процессом.</strong></p>";

        // $content = "<textarea style='width: 95%; min-height: 65vh; padding: 2%; resize: none;' readonly='readonly'>".$recovery."</textarea>";

        return false;
    }
    */


    // Создаем файл блокировки
    /*
    if(!copy($dummyFile, $lockFile)) {
        die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>Ошибка</title></head><body><h1>ОШИБКА.</h1><p><strong>Не удалось создать файл блокировки.</strong></p></body></html>');
    }
    */
    putFileOrDie($lockFile, getmypid(), LOCK_EX);

    // Создаем новый файл (dummy)
    /*
    if(!copy($dummyFile, $newFile)) {
        die('<!DOCTYPE html><html><head><meta charset="utf-8" /><title>Ошибка</title></head><body><h1>ОШИБКА.</h1><p><strong>Не удалось создать новый файл.</strong></p></body></html>');
    }
    */

    return true;
}


function dbdone($filename, $recovery) {

    global $errmsg, $content;

    $lockvar = (int)@file_get_contents($filename.".lock");

    if($lockvar === getmypid()) {

        if(is_file($filename)) rename($filename, $filename.".bak");
    
        rename($filename.".new." . getmypid(), $filename);

        touchMy($filename);

        /// unlink($filename.".lock");

        return true;

    } else {

        unlink($filename.".new." . getmypid());

        if(isset($safePost['title'], $safePost['h'], $safePost['textedit'])) {

            unlockByName($_SESSION['username'] ?? "");
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

    /*
    if(!is_file("DATABASE/DB/sys.log")) {

        copy("SYSTEM/modules/dummy.txt", "DATABASE/DB/sys.log");
    }

    
    dbprepApnd("DATABASE/DB/sys.log");

    $file = fopenOrDie("DATABASE/DB/sys.log.new." . getmypid(), "ab");
    fwriteOrDie($file, $mydate.": ".$line."<br />\n");
    fclose($file);
    

    if(!dbdone("DATABASE/DB/sys.log", "")) return false;
    */

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
        /* '~' => '.tilde.', */ // оставлен только если ты захочешь удалять его
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
        '&#039;' => '.apos.', */
        '&#39;'  => '-',
     /* '&#x27;' => '.apos.', */
        '&#'     => '.',         // Обработка всех &#123; сущностей
     /* '"'      => '.quot.',
        '<'      => '.lt.',
        '>'      => '.gt.',
        '&lt;'   => '.lt.',
        '&gt;'   => '.gt.',
        '&quot;' => '.quot.', */
        '&amp;'  => '.n.',
        '#' => '.sharp.',
     /* '&'      => '.',
        ';'      => '.',          
        ';.' => ';' */

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
        /* '~' => '.tilde.', */ // оставлен только если ты захочешь удалять его
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
        '&#039;' => '.apos.', */
        '&#39;'  => '-',
     /* '&#x27;' => '.apos.', */
        '&#'     => '.',         // Обработка всех &#123; сущностей
     /* '"'      => '.quot.',
        '<'      => '.lt.',
        '>'      => '.gt.',
        '&lt;'   => '.lt.',
        '&gt;'   => '.gt.',
        '&quot;' => '.quot.', */
        '&amp;'  => '.n.',
        '#' => '.sharp.',
     /* '&'      => '.',
        ';'      => '.',         
        ';.' => ';' */

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


/*
function checkMenuOrder($sanCheckTable) {
    global $errmsg;

    $i = 0;
    foreach ($sanCheckTable as $item) {
        if($i > 0) {
            if($sanCheckTable[$i] - $sanCheckTable[$i-1] >= 2) {
                $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
                mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. (".$_SESSION["username"].").</span>");
                return false;
            }
        }
        $i++;
    }
    return true;
}
*/



function checkMenuOrder($sanCheckTable) {
    global $errmsg;

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

    $sanCheckTable[$check] = $h;

    if(!is_int($check) || !is_int($h) || $check < 0 || $h < 1 || $h > 6 || !isset($sanCheckTable[$check])) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    } elseif($sanCheckTable[0] > 1) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. (".$_SESSION["username"].").</span>");
        return false;

    } else {

        return checkMenuOrder($sanCheckTable);
    }
}





function sanCheckAdd($check, $h) {

    global $numcache, $errmsg, $ispageexist;

    if(!$ispageexist || !is_int($check) || !is_int($h) || $check < 1 || $h < 1 || $h > 6 ) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    } elseif($check == 1 && ($h == 1 || $h == 2)) {

        return true;

    } else {

        $sanCheckTable = $numcache;


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



    if(!is_int($index) || $index < 0 || !isset($sanCheckTable[$index]) || !isset($sanCheckTable[$index + 1]) || $index >= (sizeof($sanCheckTable) - 1) || ($index == 0 && $sanCheckTable[0] != $sanCheckTable[1])) {

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



    if(!is_int($index) || $index < 1 || !isset($sanCheckTable[$index]) || !isset($sanCheckTable[$index - 1])) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Недопустимое значение Уровня Страницы.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Недопустимое значение Уровня Страницы. (".$_SESSION["username"].").</span>");
        return false;

    } elseif($index == 1 && $sanCheckTable[0] != $sanCheckTable[1]) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";
        mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. (".$_SESSION["username"].").</span>");
        return false;

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








function calcTotPages($commaddr,  $limit) {
    // Открываем файл с комментариями
    $file = openFileOrDie("DATABASE/comments/" . $commaddr, 'rb');
    $commcount = 0;
    $bufferSize = 128 * 1024; // 128 КБ

    // Считаем количество комментариев
    while($buffer = $file->freadOrDie($bufferSize)) {
         // Читаем 128 КБ
        $commcount += substr_count($buffer, "\n"); // Считаем количество \n в сегменте
    }

    $file = null; // Закрываем файл


    if ($commcount <= 0) {
        $totalPages = 0;      // или -1, если хочешь "нет страниц"
    } else {
        $totalPages = (int)ceil($commcount / (int)$limit) - 1;
    }


    return $totalPages;
}



function loadTplSess() {

    // Папка с шаблонами
    $templateDir = 'TEMPLATES/';

    // Получаем список директорий-шаблонов
    $templates = array_map('basename', glob($templateDir . '*.tpl', GLOB_ONLYDIR));

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
    $templates = array_map('basename', glob($templateDir . '*.tpl', GLOB_ONLYDIR));

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
        $quoteClean = mb_softTrim(strip_tags($quotBlock->innertext, $allowedTagsBlockquote));
        $authorClean = mb_superTrim(strip_tags($author, $allowedTagsFigcaption));

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

        $alt = mb_superTrim($img->getAttribute('alt') ?? '');
        if ($alt !== '') {
            $imgHtml = $img->outertext;
            // $altEscaped = mb_superTrim(htmlspecialchars(strip_tags($alt), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false));
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

        $currentClass = emojiToHtmlEntities($currentClass);

        $currentClass = remove_entities($currentClass);

        $node->setAttribute('class', mb_superTrim($currentClass . ' clearfix'));
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

        $existing = emojiToHtmlEntities($existing);

        $existing = remove_entities($existing);

        // В сет превратим, чтобы не было дублей
        $current = preg_split('/\s+/', mb_superTrim($existing)) ?: [];
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
        }

        if (stripos($style, 'underline') !== false) {
            $span->outertext = '<u>' . $span->innertext . '</u>';
        }
    }

    return $html;
}

// $html = replaceSemanticSpans($html);






function refreshCaches() { 

    if(is_file("DATABASE/DB/DB-TOC-Cache.txt")) {
        // rename("DATABASE/DB/DB-TOC-Cache.txt", "DATABASE/DB/DB-TOC-Cache.txt.del");

        unlink("DATABASE/DB/DB-TOC-Cache.txt");
    }

    if(is_file("DATABASE/DB/SEO-Cache.txt")) {
        // rename("DATABASE/DB/SEO-Cache.txt", "DATABASE/DB/SEO-Cache.txt.del");

        unlink("DATABASE/DB/SEO-Cache.txt");
    }

    if(is_file("DATABASE/DB/MenuCache.txt")) {
        // rename("DATABASE/DB/MenuCache.txt", "DATABASE/DB/MenuCache.txt.del");

        unlink("DATABASE/DB/MenuCache.txt");
    }

    if(is_file("sitemap.txt")) {
        // rename("sitemap.txt", "sitemap.txt.del");

        unlink("sitemap.txt");
    }

    if(is_file("sitemap.xml")) {
        // rename("sitemap.xml", "sitemap.xml.del");

        unlink("sitemap.xml");
    }
}







function getCommCount($commaddr) {

    if(is_file("DATABASE/comm.count/".$commaddr)) {

        $tmp = fopenOrDie("DATABASE/comm.count/".$commaddr, 'rb');
        @flock($tmp, LOCK_SH);
        $contents = stream_get_contents($tmp);
        @flock($tmp, LOCK_UN);
        fclose($tmp);

        return "<span class='pgCommCnt' title='Комментарии'>&nbsp;(".rtrim($contents).")</span>";

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


function unwrapParagraphsAfter($txt) {
    return preg_replace_callback(
        '#</(div|figure|aside|details|table)>(.*?)</p>#s',
        function ($matches) {
            // $matches[1] — 'div' или 'figure'
            // $matches[2] — содержимое между </div> или </figure> и </p>

            // Быстрое игнорирование <p — без копирования строки
            if(stripos($matches[2], '<p>') !== false ||
                stripos($matches[2], '<p ') !== false) {
                
                return $matches[0];
            }

            return '</' . $matches[1] . '>' . $matches[2];
        },
        $txt
    );
}



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

    $str = $_SERVER["QUERY_STRING"];

    $str = explode("&", $str)[0];

    $arr = explode("/", $str);

    $str = (int)end($arr);

    return seoLinkDecode($str);
}






function skipCache(string $filepath): string {
    $mTime = 0;

    if (is_file($filepath)) {
        $mTime = filemtime($filepath) ?? 0;
    }

    $separator = (strpos($filepath, '?') === false) ? '?' : '&';

    return $filepath . $separator . $mTime;
}






/*
function normalize_entities_my(string $text): string {

    // 1. Десятичные сущности с ведущими нулями → канонический вид + проверка на предел
    $text = preg_replace_callback('/&#0*(\d+);/', function ($matches) {
        $num = (int)$matches[1];
        if ($num > 0x10FFFF) $num = 0xFFFD; // REPLACEMENT CHARACTER
        return '&#' . $num . ';';
    }, $text);

    // 2. Hex сущности (регистр независим) → переводим в десятичный + проверка на предел
    $text = preg_replace_callback('/&#x0*([0-9a-f]+);/i', function ($matches) {
        $num = (int)hexdec($matches[1]);
        if ($num > 0x10FFFF) $num = 0xFFFD;
        return '&#' . $num . ';';
    }, $text);

    // 3. Остальные именованные сущности → просто в нижний регистр
    $text = preg_replace_callback('/&[a-z][a-z0-9]+;/i', function ($matches) {
        return strtolower($matches[0]);
    }, $text);

    // 4. Экранируем одиночные & не входящие в сущности
    $text = preg_replace('/&(?![a-z][a-z0-9]*;|#\d+;|#x[0-9a-f]+;)/i', '&amp;', $text);

    return $text;
}
*/


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
    $text = preg_replace_callback('/&#0*(\d+);/', function ($m) use ($toDecEnt) {
        return $toDecEnt((int)$m[1]);
    }, $text);

    // 2) Шестнадцатеричные: к десятичным + правим диапазоны
    $text = preg_replace_callback('/&#x0*([0-9a-f]+);/i', function ($m) use ($toDecEnt) {
        return $toDecEnt(hexdec($m[1]));
    }, $text);

    // 3) Именованные → нижний регистр (канон для HTML)
    $text = preg_replace_callback('/&[a-z][a-z0-9]*;/i', function ($m) {
        return strtolower($m[0]);
    }, $text);

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






/*

function dbCleanupAllNewFiles(string $filename): void {
    $pattern = $filename . '.new.*';

    foreach (glob($pattern) as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

*/



function atomicCounterIncrement($path) {

    $fp = @fopen($path, 'c+'); // создаст файл при отсутствии
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $val  = 0;
            rewind($fp);
            $data = stream_get_contents($fp);
            $data = trim((string)$data);
            if ($data !== '' && ctype_digit($data)) {
                $val = (int)$data;
            }
            $val++;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, (string)$val);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
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

        $pos = $close + strlen('</code>');
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
function ru_nbsp_typograf(string $text, bool $useHtmlNbsp = true): string
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








