<?php


if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################



function savePage() {

    global $safePost, $numcache, $head, $body, $content, $errmsg, /* $apiKeyTinyMCE, */ $mainPageTitle, $checkpermission, $ispageexist, $url, $chTimeDB;

    /*
    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } else {

    */

        $textedit = $safePost["textedit"];
        $pgtitle  = $safePost["title"];
        $htag     = (int)$safePost["h"];


        if( $checkpermission < 3 ) {

            $textedit = mb_strcut($textedit, 0, 129 * 1024, 'UTF-8');
        }

        /* $pgtitle  = str_ireplace("&nbsp;", " ", $pgtitle); */


        /*
        $pgtitle = str_ireplace(
            ["&nbsp;", "&#160;", "&#xA0;", " "],
            " ",
            $pgtitle
        );
        */


        // $pgtitle  = str_ireplace("<br!>", " ", $pgtitle);
        
        // $pgtitle  = str_replace("\r", "", $pgtitle);
        // $pgtitle  = str_replace("\n", "", $pgtitle);

        // $pgtitle  = str_replace("<!1!>", " ", $pgtitle);
        // $pgtitle  = str_replace("<!2!>", " ", $pgtitle);

        $textedit = normalize_entities_my($textedit);
        // $textedit = str_replace("&", "&amp;", $textedit);
        // $textedit = str_ireplace("&amp;amp;", "&amp;", $textedit);
        
        $textedit = mb_softTrim($textedit);

        // $textedit = escape_amp_txtarea($textedit);

        $pgtitle = normalize_entities_my($pgtitle);

        $pgtitle = mb_superTrim($pgtitle);

        // $pgtitle = htmlspecialchars($pgtitle, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);

        // $pgtitle = preg_replace("/&amp;(#?\w+);/i", "&$1;", $pgtitle);



        $herror = 0;

        // $checknum = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

        // $checknum = seoLinkDecode($checknum) - 1;

        // $checknum = seoNumGet() - 1;


        if($safePost['dbtimestamp'] !== filemtimeMy("DATABASE/DB/data.html")) {

            $herror = 5;

        } elseif( $checkpermission < 3 ) {

            $herror = 10;

        } elseif(!$ispageexist) {

            $herror = 6;

        } elseif(isDbLocked() && !isLockedBy($_SESSION['username'])) {

            $herror = 9;

        } elseif(strlen($textedit) > (128 * 1024)) {

            $herror = 8;

        } elseif(mb_strlen($pgtitle) > 140) {

            $herror = 7;

        } elseif(mb_strlen(urlPrep3($pgtitle)) < 3) {

            $herror = 2;

        } elseif(strstr($pgtitle, '/') !== false) {

            $herror = 3;

        } elseif($htag < 1 || $htag > 6) {

            $herror = 1;

        } elseif(!sanCheckHor((seoNumGet() - 1), $htag)) {

            $herror = 4;

        }






        $textedit3 = $textedit;






        ##############################################
        ##############################################
        ##############################################

        ensure_html_purifier_loaded();

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.1');
        $config->set('HTML.TidyLevel', 'heavy'); // all changes, minus...
        $config->set('HTML.TidyRemove', 'br@clear');

        $config->set('HTML.ForbiddenElements', 'h1');

        $purifier = new HTMLPurifier($config);
        $textedit = $purifier->purify($textedit);


        ##############################################

        




        /*
        $textedit = str_ireplace(
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
            $textedit
        );





        $html = str_get_html($textedit, false, true, "UTF-8", false) or die("XSS?.. Пустой или битый HTML.");

        $html = ulFix($html);

        $textedit = $html->save();

        $html->clear();  // чистим объект
        unset($html);    // удаляем переменную


        $textedit = str_ireplace(
            ['&@lt;', '&@gt;', '&@quot;', '&@apos;',  '&@amp;'],
            ['&lt;',  '&gt;',  '&quot;',  '&#039;',   '&amp;'],
            $textedit
        );
        */




        $textedit = str_replace("/>", " />", $textedit);
        $textedit = str_replace("  />", " />", $textedit);

        $textedit = preg_replace('~(?:[\p{Zs}\s]*<br />[\p{Zs}\s]*)+(<ul\b|<ol\b|</li>)~ui', '$1', $textedit);
        $textedit = preg_replace('~(?:[\p{Zs}\s])+(<ul\b|<ol\b|</li>)~ui', '$1', $textedit);
        
        $textedit = str_ireplace('<li></li>', '', $textedit);



        ##############################################
        ##############################################
        ##############################################








            $headEd = "<script src='SYSTEM/JSLIB/ckeditor/ckeditor.js'></script>";
            $bodyEd = "
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




        $hsel = "<select name='h'>
        <option value='1'>Уровень 1</option>
        <option value='2'>Уровень 2</option>
        <option value='3'>Уровень 3</option>
        <option value='4'>Уровень 4</option>
        <option value='5'>Уровень 5</option>
        <option value='6'>Уровень 6</option>
        </select>";

        $hsel = str_replace("<option value='".$htag."'>", "<option selected='selected' value='".$htag."'>", $hsel);


        $mainPageTitle = "Редактирование: ".$pgtitle;

        $dbMtime = filemtimeMy("DATABASE/DB/data.html");

        // $textedit2 = protect_amp_entities_for_textarea($textedit);

        $textedit2 = escape_amp_txtarea($textedit);

        $dumpEdit = "<form method='post'>

            <input type='hidden' name='dbtimestamp' value='$dbMtime' />

            <fieldset><legend>Редактирование страницы:</legend>
            <p>Для включения <em>Содержания</em> используйте <em>Директиву</em> <strong>__TOC__</strong> вначале кода, на первой строке.</p>
            <p>Для Вставки <em>ВИДЕО YouTube</em> используйте шаблон <strong>{{youtube|VIDID|Ширина}}</strong> ; (Ширина является необязательным аттрибутом, и указывается в Процентах, без указания <strong>%</strong>).<br />Также поддерживаются <strong>{{dailymotion|VIDID|Ширина}}</strong> и <strong>{{vimeo|VIDID|Ширина}}</strong>.</p><p>Для вставки <em>Спойлера</em>, <em>Цитаты</em> или <em>Инфобокса</em> используйте шаблон из меню редактора <strong>\"Стили\"</strong>.</p>
            <p>Для добавления <strong>рамки</strong> изображению &mdash; укажите его <strong>Alt</strong></p>
            <p>Доступны также Шаблоны <strong>{{clear}}</strong> и <strong>{{nobr|ТЕКСТ}}</strong></p>
            <p><strong>{{download|DATABASE/fupload/Example.zip}}</strong>&nbsp;&mdash; Используйте это для вставки URL загрузок.</p>
            <p>Для вставки Тире используйте \" -- \" (без кавычек, с пробелами по краям)</p>
            <p><strong>{{lambda}} FROG!!!</strong></p>
            

            <input id='edpagetitle' type='text' name='title' value='".$pgtitle."' />".$hsel.
            "<textarea rows='9' name='textedit' id='textedit'>"
            .$textedit2."</textarea><div class='el-in-line'> <input type='submit' value='💾 Отправить' />

            <a href='?".explode('&', $_SERVER['QUERY_STRING'])[0]."&amp;leaveedit=1'>Отменить ⬅️</a>
            <a href='?".explode("&", $_SERVER['QUERY_STRING'])[0]."&amp;pagedel=1' id='pagedelbutton'>❌ Удалить страницу</a>


            </div></fieldset></form>";


        switch ($herror) {

            case 0:

                // $pagenum = (int)explode('/', $_SERVER['QUERY_STRING'])[0];

                // $pagenum = seoLinkDecode($pagenum) - 1;
                $textedit = str_replace("\r", "", $textedit);
                /// $textedit = str_replace("<br />\n", "<br!>", $textedit);
                /// $textedit = str_replace("\n<br />", "<br!>", $textedit);
                /// $textedit = str_replace("<br />"  , "<br!>", $textedit);
                $textedit = str_replace("\n", "<br!>", $textedit);


        
                // $textedit = str_replace("&", "&amp;", $textedit);
                // $textedit = str_replace("&amp;amp;", "&amp;", $textedit);
        
                // $textedit = escape_amp_txtarea($textedit);

                // $textedit = protect_amp_entities_for_textarea($textedit);


                // moved
                // $textedit = '<head'.$htag.'>'.$pgtitle.'</head'.$htag.'><br!>'.$textedit;

                $pagenum = seoNumGet() - 1;

                dbprepApnd("DATABASE/DB/data.html");

                $filesource = openFileOrDie("DATABASE/DB/data.html.src." . getmypid(), 'rb');

                $filesource->seekOrDie($pagenum);

                $firstChunkEnd = $filesource->ftell();

                $file = fopenOrDie("DATABASE/DB/data.html.new." . getmypid(), "r+b");
                ftruncateOrDie($file,$firstChunkEnd);
                fclose($file);

                $pageid = $filesource->freadOrDie(40);


                $tmpLine = mb_substr($filesource->fgetsOrDie(), 0, 300);

                preg_match('~</head[1-6]>(\d+)<!1!>~', $tmpLine, $matches);
                $seoPgNum = $matches[1];

                $textedit = '<head'.$htag.'>'.$pgtitle.'</head'.$htag.'>'.$seoPgNum.'<!1!>'.time().'<!2!><br!>'.$textedit;

                $filedest = openFileOrDie("DATABASE/DB/data.html.new." . getmypid(), 'ab');

                $filedest->fwriteOrDie($pageid.$textedit."\n");

                while($line = $filesource->freadOrDie(256*1024)) {

                    $filedest->fwriteOrDie($line);
                }

                $filedest = null;
                    
                $filesource = null;
                

                if(!dbdone("DATABASE/DB/data.html", $textedit3)) return false;

                mylog("<em style='color:DarkOrange'>Страница сохранена. (" . explode("&", $_SERVER['QUERY_STRING'])[0] . " - " . $_SESSION["username"].").</em>");

                // $chkpgnum = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

                // $chkpgnum = seoLinkDecode($chkpgnum);

                $chkpgnum = seoNumGet();

                if($numcache[$chkpgnum - 1] != $htag) {

                    $errmsg = "<h1>СТРАНИЦА ПЕРЕМЕЩЕНА</h1><p class='big'><strong>Подождите момент.</strong></p>";
                    $content = "";
                    refreshhandle(3, "?nredir=".$chkpgnum);

                } else {

                    /*
                    $nnewaddr = explode("/", $_SERVER['QUERY_STRING']);

                    $nnewaddr[array_key_last($nnewaddr)] = urlPrep2($pgtitle);

                    $nnewaddr = join("/", $nnewaddr);

                    refreshhandle(0 ,"?".$nnewaddr);
                    */

                    /// unlockByName($_SESSION['username'] ?? ""); ///

                    refreshhandle(0, "?nredir=".$chkpgnum);
                }

                break;

            case 1:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 1.</h1><p class='big'><strong>Уровень заголовка должен быть в пределах 1-6 и состоять из цифры!</strong></p>";

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>Уровень заголовка должен быть в пределах 1-6 и состоять из цифры! (".$_SESSION["username"].").</em>");

                break;

            case 2:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 2.</h1><p class='big'><strong>Вы не ввели заголовок страницы!</strong></p>";

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>Вы не ввели заголовок страницы! (".$_SESSION["username"].").</em>");

                break;

            case 3:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 3.</h1><p class='big'><strong>Заголовок не должен содержать слэши.<br />Вы можете использовать Обратный Слэш (\)</strong></p>";

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>Заголовок не должен содержать слэши. (".$_SESSION["username"].").</em>");

                break;

            case 4:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 4.</h1><p class='big'><strong>Порядок уровней меню не был соблюдён.</strong></p>";

                $content = $dumpEdit;

                mylog("<span style='color:DarkMagenta'>Порядок уровней меню не был соблюдён. (".$_SESSION["username"].").</span>");

                break;

            case 5:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 5.</h1><p class='big'><strong>База Данных была изменена внешним процессом.</strong></p>";

                /// $errmsg .= $safePost['dbtimestamp'];

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>База Данных была изменена внешним процессом. (".$_SESSION["username"].").</em>");

                break;

            case 6:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = pnotfound();
                $errmsg .= "<p class='big'><strong>Адрес редактируемой страницы изменился. Скопируйте данные из редактора и перейдите к редактированию по правильному адресу.</strong></p>";

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>Редактируемая страница не найдена. (".$_SESSION["username"].").</em>");

                break;

            case 7:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 7.</h1><p class='big'><strong>Заголовок слишком длинный (> 140).</strong></p>";

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>Заголовок слишком длинный (> 140). (".$_SESSION["username"].").</em>");

                break;

            case 8:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 8.</h1><p class='big'><strong>Введённый текст занимает больше 128 килобайт, разбейте его в разделе на под-страницы.</strong></p>";

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>Введённый текст занимает больше 128 килобайт. (".$_SESSION["username"].").</em>");

                break;

            case 9:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = plocked();

                $content = $dumpEdit;

                mylog("<em style='color:DarkMagenta'>БД была заблокирована. (Отредактирована другим пользователем).</em>");

                break;

            case 10:

                $head .= $headEd;
                $body .= $bodyEd;
                
                $errmsg = pforbidden();

                $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

                $errmsg .= "<p class='big'><em>Возможно, Сессия завершена по тайм-ауту, или сменился IP.<br />В форме ниже представлены несохранённые данные, которые вы можете скопировать.</em></p>";

                $content = $dumpEdit;

                // mylog("<em style='color:DarkMagenta'>БД была заблокирована. (Отредактирована другим пользователем).</em>");

                break;

        }
    /* } */

    /// unlockByName($_SESSION['username'] ?? "");
}








function commentReply() {

    global $safePost, $idcache, $errmsg, $ip, $checkpermission, $patternYT, $replacementYT, $patternVimeo, $replacementVimeo, $patternDM, $replacementDM, $commRecov, $userAgent, $cred, $mySalt;

    // require_once "SYSTEM/cred.php";


    $commaddr  = $safePost["commaddr" ] ?? 0; //[0];
    $pgcommnum = (int)($safePost["pgcommnum"] ?? 0); //[1];
    $repcommid = $safePost["repcommid"] ?? 0; //[2];
    $commpost  = $safePost["commpost" ];
    $visitor   = $safePost["visitor"  ];
    $captcha   = $safePost["captcha"  ];


    $commaddr = substr($commaddr, 0, 40);
    

    if(in_array($commaddr, $idcache, true) && is_file("DATABASE/comments/".$commaddr)) {

        
        $pgcommnum = abs($pgcommnum);


        $commpost = mb_substr($commpost, 0, 2550);

        $commpost = normalize_entities_my($commpost);

        $commpost = mb_softTrim($commpost);

        /// $commpost = str_replace("\r", "", $commpost);



        $pgcommnum = (int)substr($pgcommnum, 0, 10);
        
        $repcommid = substr($repcommid, 0, 40);
        $repcommid = filter_filename($repcommid);

        $captcha = substr($captcha, 0, 6);

        $visitor = mb_substr($visitor, 0, 50);

        //// $visitor = normalize_entities_my($visitor);

        
        // $visitor = str_ireplace('<br!>', ' ', $visitor);
        // $visitor = str_ireplace('&nbsp;', ' ', $visitor);

        //// $visitor = mb_superTrim($visitor);

        // $visitor = str_replace('...', '…', $visitor);


        if(filterUsername($visitor) && /* strtolower($visitor) != "аноним" && */ !array_key_exists($visitor, $cred)) {
            $_SESSION["visitor"] = $visitor;
        }

        
        if($checkpermission) {

            $visitor2 = "👤<strong class='a$checkpermission'> ".$_SESSION['username']."</strong>";

        } elseif(!filterUsername($visitor) || array_key_exists($visitor, $cred)) {
            
            $visitor2 = "👤<b> Аноним</b>";

        } else {

            $visitor2 = "👤<b> $visitor</b>";
        }






        $commdataline = "";

        $file = openFileOrDie("DATABASE/comments/".$commaddr, "rb");

        // Переходим к нужной строке
        $file->seekOrDie($pgcommnum);

        /*
        if($file->eof()) {
            die();
        }
        */

        // Получаем текущую строку и её номер
        $commdataline = $file->current(); // Читаем строку



        $file = null; // Закрываем файл




        ####################



        // require_once "SYSTEM/salt.php";

        $today = date('Y-m-d');


        if($safePost['dbtimestamp'] !== filemtimeMy("DATABASE/comments/".$commaddr)) {

            $errmsg = "<h1>ПРЕДУПРЕЖДЕНИЕ.</h1><p class='big'><strong>Во время написания комментария БД изменилась.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(!$checkpermission && !canProceed($ip)) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Временной интервал между комментариями Три Минуты.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(!isset($_SESSION['captcha']) || !$_SESSION['captcha'] || (!$checkpermission && hash_equals($_SESSION['captcha'], hash('sha256', $captcha.$mySalt.$ip.$userAgent.$today)) && !repeatCaptcha($_SESSION['captcha']))) {


            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Повторный ввод CAPTCHA, или её отключение недопустимы.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(!$checkpermission && (empty($captcha) || !hash_equals($_SESSION['captcha'], hash('sha256', $captcha.$mySalt.$ip.$userAgent.$today)))) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Вы не ввели код подтверждения.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(mb_strlen($commpost) > 2500) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Ваш комментарий слишком большой (больше 2500 символов).</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(mb_strlen(mb_superTrim(normalize_entities_my($commpost))) < 5) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Ваш комментарий слишком мал.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(strlen($commdataline) >= (192 * 1024)) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Превышен лимит вложенных комментариев в ветке дискуссии.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } else {

            
            $commpost2 = $commpost;

            // unset($_SESSION['captcha']);

            $commpost = str_replace("%QUERYSTRING%", "", $commpost);
            // $commpost = str_ireplace("<id>", "", $commpost);


            if($checkpermission) {

                $commpost  = str_ireplace("<br!>", " ", $commpost);

                /*
                $commpost = str_ireplace("<ul", "<ul class=\"ul-fix\"", $commpost);
                $commpost = str_ireplace("<ul class=\"ul-fix\" class=\"", "<ul class=\"ul-fix ", $commpost);
                $commpost = str_ireplace("<ul class=\"ul-fix\" class='", "<ul class='ul-fix ", $commpost);
                */

                //$commpost = str_replace("&", "&amp;", $commpost);
                //$commpost = str_replace("&amp;amp;", "&amp;", $commpost);

                ensure_html_purifier_loaded();

                $config = HTMLPurifier_Config::createDefault();
                $config->set('Core.Encoding', 'UTF-8');
                $config->set('HTML.Doctype', 'XHTML 1.1');
                $config->set('HTML.TidyLevel', 'heavy'); // all changes, minus...
                $config->set('HTML.TidyRemove', 'br@clear');

                $config->set('HTML.ForbiddenElements', 'h1,b');

                $purifier = new HTMLPurifier($config);
                $commpost = $purifier->purify($commpost);

                /// $commpost = str_replace("\n", "<br!>", $commpost);

                


                $commpost = str_ireplace(
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
                    $commpost
                );




                $html = str_get_html($commpost, false, true, "UTF-8", false) or die("XSS?.. Пустой или битый HTML.");

                /// $html = ulFix($html);
                $html = addClassToAllUl($html, 'ul-fix');

                $html = convert_infoboxes_to_aside($html);

                $html = replaceSemanticSpans($html);

                // Генератор Обёртки из Alt
                $html = wrap_images_with_figure($html);

                // Шаблон ЦИТАТА
                $html = convertQuotBlocks($html);

                $html = parseSpoilers($html);
                
                $commpost = $html->save();

                $html->clear();  // чистим объект
                unset($html);    // удаляем переменную


                $commpost = str_ireplace(
                    ['&@lt;', '&@gt;', '&@quot;', '&@apos;', '&@amp;'],
                    ['&lt;',  '&gt;',  '&quot;',  '&#039;',   '&amp;'],
                    $commpost
                );


                // Замена шаблона youtube на iframe
                $commpost = preg_replace_callback($patternYT, $replacementYT, $commpost);

                // Замена шаблона vimeo на iframe
                $commpost = preg_replace_callback($patternVimeo, $replacementVimeo, $commpost);

                // Замена шаблона DailyMotion на iframe
                $commpost = preg_replace_callback($patternDM, $replacementDM, $commpost);

                $commpost = str_replace("{{lambda}}", "<div style='font-family:monospace; white-space:nowrap; font-size:6rem; text-align:center'>&nbsp;<span class='a4'>&lambda;</span>++<br /><span class='a3'>&lambda;</span>&nbsp;<span class='a2'>&lambda;</span>&nbsp;</div>", $commpost);

                $commpost = str_replace(
                    ["<p><figure", "<p><div", "<p><aside", "<p><details", "<p><table"],
                    ["<figure", "<div", "<aside", "<details", "<table"],
                    $commpost
                );

                $commpost = unwrapParagraphsAfter($commpost);

                $commpost = str_replace("/>", " />", $commpost);
                $commpost = str_replace("  />", " />", $commpost);

                ///
                $commpost = preg_replace('~(?:[\p{Zs}\s]*<br />[\p{Zs}\s]*)+(<ul\b|<ol\b|</li>)~ui', '$1', $commpost);
                $commpost = preg_replace('~(?:[\p{Zs}\s])+(<ul\b|<ol\b|</li>)~ui', '$1', $commpost);
        
                $commpost = str_ireplace('<li></li>', '', $commpost);



                // $commpost = str_replace('<li><br /></li>', '', $commpost);
                // $commpost = str_replace('<br /></li>', '</li>', $commpost);
                ///

                $commpost = str_replace("<p>{{clear}}</p>", "<br style='clear: both;' />", $commpost);

                $commpost = str_replace("{{clear}}", "<br style='clear: both;' />", $commpost);

                /// $commpost = str_replace("<br />", "<br!>", $commpost);


                /// $commpost = preg_replace('/&(?!\w+;|#\d+;|#x[0-9a-fA-F]+;)/', '&amp;', $commpost);

            } else {
                
                $commpost = htmlspecialchars($commpost, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);

                // $commpost = str_ireplace("\n", "<br />\n", $commpost);
            }



            dbprepApnd("DATABASE/comments/".$commaddr);



            $file = openFileOrDie("DATABASE/comments/".$commaddr
            . ".new." . getmypid(), "rb");            
            $file->seekOrDie($pgcommnum); // Переходим к нужной строке
            $commdataline = $file->current(); // Читаем строку
            $file = null; // Закрываем файл



            /*
            $commpost = str_replace("<br />\n", "<br />", $commpost);
            $commpost = str_replace("\n<br />", "<br />", $commpost);
            */
            $commpost = str_replace("<br />"  , "<br!>", $commpost);
            $commpost = str_replace("\n", "<br!>", $commpost);

            $commdataline = rtrim($commdataline);


            while(true) {

                $commid = bin2hex(random_bytes(20)); // sha1(microtime().$ip.$userAgent);

                /// $commtmp = str_replace("<$repcommid />", "<ul><li><$commid>$visitor2<d>$commpost</d><a href='?%QUERYSTRING%&amp;creply=<id>{$pgcommnum}-$commid#R'>rep.</a> <a href='?%QUERYSTRING%&amp;cmove=<id>{$pgcommnum}-$commid'>del.</a><$commid><$commid /></li></ul><$repcommid />", $commdataline);

                /// $commarrtmp = explode("<$commid>", $commtmp);

                $commarrtmp = explode("<$commid>", $commdataline);

                if((string)$commid !== (string)$repcommid && count($commarrtmp) === 1) {

                    break;
                }
            }


            $commdataline = str_replace("<$repcommid />", "<ul><li><$commid>$visitor2<d>$commpost</d><a href='?%QUERYSTRING%&amp;creply=<id>{$pgcommnum}-$commid#R'>rep.</a> <a href='?%QUERYSTRING%&amp;cmove=<id>{$pgcommnum}-$commid'>del.</a><$commid><$commid /></li></ul><$repcommid />", $commdataline);


            


            $filesource = openFileOrDie("DATABASE/comments/".$commaddr . ".src." . getmypid(), 'rb');

            $filesource->seekOrDie($pgcommnum);

            $firstChunkEnd = $filesource->ftell();


            $file = fopenOrDie("DATABASE/comments/".$commaddr.".new." . getmypid(), "r+b");
            ftruncateOrDie($file,$firstChunkEnd);
            fclose($file);


            $filesource->fgetsOrDie();


            $filedest = openFileOrDie("DATABASE/comments/".$commaddr.".new." . getmypid(), 'ab');

            $filedest->fwriteOrDie($commdataline."\n");

            while($line = $filesource->freadOrDie(128*1024)) {

                $filedest->fwriteOrDie($line);
            }

            $filedest = null;
                
            $filesource = null;
            

            if(!dbdone("DATABASE/comments/".$commaddr, $commpost2)) return false;
























            ###############################################

            ///$currentPage = ceil(($pgcommnum + 1) / 8) - 1;

            /// То-же самое, но проще.
            $currentPage = intdiv($pgcommnum, 8); // при 0-based индексе

            $comTotalPages = calcTotPages($commaddr, 8);

            if(!isset($safePost["commpage"]) || !is_int($safePost["commpage"])) {

                $commpage = $currentPage;

            } elseif($safePost["commpage"] > $comTotalPages || $safePost["commpage"] < 0) {

                $commpage = $comTotalPages;

            } else {

                $commpage = $safePost["commpage"];
            }










            /*

            if(!is_file("DATABASE/comm.count/".$commaddr)) {

                copy("SYSTEM/modules/null.txt", "DATABASE/comm.count/".$commaddr);
            }


            dbprepCommCnt("DATABASE/comm.count/".$commaddr);

            $commcnt = (int)getFileOrDie("DATABASE/comm.count/".$commaddr);

            $commcnt++;

            putFileOrDie("DATABASE/comm.count/".$commaddr.".new." . getmypid(), $commcnt, LOCK_EX);

            dbdone("DATABASE/comm.count/".$commaddr);

            */

            ###############################################

            atomicCounterIncrement("DATABASE/comments/".$commaddr.".count");

            ###############################################



            // mylog("<em style='color:DarkGreen'>Добавлен ответ на комментарий <a href='".$url."?".explode("&", $_SERVER['QUERY_STRING'])[0]."&commpage=".$commpage."'>".$commaddr."</a> ".$ip."</em>");


            refreshhandle(0, "?".explode("&", $_SERVER['QUERY_STRING'])[0]."&ts=".microtime(true)."&commpage=".$commpage."#comm-section", false);

        }

        unset($_SESSION['captcha']);

    } else {

        $errmsg = pnotfound();

    }
}


function postComment() {

    global $safePost, $errmsg, $idcache, $ip, $checkpermission, $patternYT, $replacementYT, $patternVimeo, $replacementVimeo, $patternDM, $replacementDM, $commRecov, $userAgent, $cred, $mySalt;

    // require_once "SYSTEM/cred.php";


    $commpost = $safePost["commpost"];
    $commaddr = $safePost["commaddr"];
    $visitor  = $safePost["visitor" ];
    $captcha  = $safePost["captcha" ];


    $commaddr = substr($commaddr, 0, 40);
    /// $commaddr = filter_filename($commaddr);

    if(in_array($commaddr, $idcache, true)) {


        $commpost = mb_substr($commpost, 0, 2550);
        
        $commpost = normalize_entities_my($commpost);

        $commpost = mb_softTrim($commpost);

        /// $commpost = str_replace("\r", "", $commpost);



        $captcha = substr($captcha, 0, 6);

        $visitor = mb_substr($visitor, 0, 50);

        //// $visitor = normalize_entities_my($visitor);

        
        // $visitor = str_ireplace('<br!>', ' ', $visitor);
        // $visitor = str_ireplace('&nbsp;', ' ', $visitor);

        //// $visitor = mb_superTrim($visitor);

        // $visitor = str_replace('...', '…', $visitor);


        if(filterUsername($visitor) && /* strtolower($visitor) != "аноним" && */ !array_key_exists($visitor, $cred)) {
            $_SESSION["visitor"] = $visitor;
        }

        
        if($checkpermission) {

            $visitor2 = "👤<strong class='a$checkpermission'> ".$_SESSION['username']."</strong>";

        } elseif(!filterUsername($visitor) || array_key_exists($visitor, $cred)) {

            $visitor2 = "👤<b> Аноним</b>";

        } else {

            $visitor2 = "👤<b> $visitor</b>";
        }




        // require_once "SYSTEM/salt.php";

        $today = date('Y-m-d');


        if($safePost['dbtimestamp'] !== filemtimeMy("DATABASE/comments/".$commaddr)) {

            $errmsg = "<h1>ПРЕДУПРЕЖДЕНИЕ.</h1><p class='big'><strong>Во время написания комментария БД изменилась.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(!$checkpermission && !canProceed($ip)) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Временной интервал между комментариями Три Минуты.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(!isset($_SESSION['captcha']) || !$_SESSION['captcha'] || (!$checkpermission && hash_equals($_SESSION['captcha'], hash('sha256', $captcha.$mySalt.$ip.$userAgent.$today)) && !repeatCaptcha($_SESSION['captcha']))) {


            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Повторный ввод CAPTCHA, или её отключение недопустимы.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;


        } elseif(!$checkpermission && (empty($captcha) || !hash_equals($_SESSION['captcha'], hash('sha256', $captcha.$mySalt.$ip.$userAgent.$today)))) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Вы не ввели код подтверждения.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(mb_strlen($commpost) > 2500) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Ваш комментарий слишком большой (больше 2500 символов).</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } elseif(mb_strlen(mb_superTrim(normalize_entities_my($commpost))) < 5) {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Ваш комментарий слишком мал.</strong></p>";
            
            $commRecov = $commpost;
            $commRecov = str_ireplace("<textarea", "&lt;textarea", $commRecov);
            $commRecov = str_ireplace("</textarea", "&lt;/textarea", $commRecov);
            $commRecov = str_ireplace("textarea>", "textarea&gt;", $commRecov);
            // $commRecov = str_ireplace("&", "&amp;", $commRecov);
            // $commRecov = str_ireplace("&amp;amp;", "&amp;", $commRecov);
            $commRecov = escape_amp_txtarea($commRecov);
            $commRecov .= " ";
            pageload();
            return;

        } else {

            
            $commpost2 = $commpost;

            // unset($_SESSION['captcha']);

            $commpost = str_replace("%QUERYSTRING%", "", $commpost);
            // $commpost = str_ireplace("<id>", "", $commpost);


            if($checkpermission) {

                $commpost  = str_ireplace("<br!>", " ", $commpost);

                /*
                $commpost = str_ireplace("<ul", "<ul class=\"ul-fix\"", $commpost);
                $commpost = str_ireplace("<ul class=\"ul-fix\" class=\"", "<ul class=\"ul-fix ", $commpost);
                $commpost = str_ireplace("<ul class=\"ul-fix\" class='", "<ul class='ul-fix ", $commpost);
                */

                //$commpost = str_replace("&", "&amp;", $commpost);
                //$commpost = str_replace("&amp;amp;", "&amp;", $commpost);

                ensure_html_purifier_loaded();

                $config = HTMLPurifier_Config::createDefault();
                $config->set('Core.Encoding', 'UTF-8');
                $config->set('HTML.Doctype', 'XHTML 1.1');
                $config->set('HTML.TidyLevel', 'heavy'); // all changes, minus...
                $config->set('HTML.TidyRemove', 'br@clear');

                $config->set('HTML.ForbiddenElements', 'h1,b');

                $purifier = new HTMLPurifier($config);
                $commpost = $purifier->purify($commpost);

                /// $commpost = str_replace("\n", "<br!>", $commpost);
                


                $commpost = str_ireplace(
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
                    $commpost
                );




                $html = str_get_html($commpost, false, true, "UTF-8", false) or die("XSS?.. Пустой или битый HTML.");

                /// $html = ulFix($html);
                $html = addClassToAllUl($html, 'ul-fix');

                $html = convert_infoboxes_to_aside($html);

                $html = replaceSemanticSpans($html);

                // Генератор Обёртки из Alt
                $html = wrap_images_with_figure($html);

                // Шаблон ЦИТАТА
                $html = convertQuotBlocks($html);

                $html = parseSpoilers($html);

                $commpost = $html->save();

                $html->clear();  // чистим объект
                unset($html);    // удаляем переменную


                $commpost = str_ireplace(
                    ['&@lt;', '&@gt;', '&@quot;', '&@apos;', '&@amp;'],
                    ['&lt;',  '&gt;',  '&quot;',  '&#039;',   '&amp;'],
                    $commpost
                );
                

                // Замена шаблона youtube на iframe
                $commpost = preg_replace_callback($patternYT, $replacementYT, $commpost);

                // Замена шаблона vimeo на iframe
                $commpost = preg_replace_callback($patternVimeo, $replacementVimeo, $commpost);

                // Замена шаблона DailyMotion на iframe
                $commpost = preg_replace_callback($patternDM, $replacementDM, $commpost);

                $commpost = str_replace("{{lambda}}", "<div style='font-family:monospace; white-space:nowrap; font-size:6rem; text-align:center'>&nbsp;<span class='a4'>&lambda;</span>++<br /><span class='a3'>&lambda;</span>&nbsp;<span class='a2'>&lambda;</span>&nbsp;</div>", $commpost);

                $commpost = str_replace(
                    ["<p><figure", "<p><div", "<p><aside", "<p><details", "<p><table"],
                    ["<figure", "<div", "<aside", "<details", "<table"],
                    $commpost
                );

                $commpost = unwrapParagraphsAfter($commpost);

                $commpost = str_replace("/>", " />", $commpost);
                $commpost = str_replace("  />", " />", $commpost);

                ///
                $commpost = preg_replace('~(?:[\p{Zs}\s]*<br />[\p{Zs}\s]*)+(<ul\b|<ol\b|</li>)~ui', '$1', $commpost);
                $commpost = preg_replace('~(?:[\p{Zs}\s])+(<ul\b|<ol\b|</li>)~ui', '$1', $commpost);
        
                $commpost = str_ireplace('<li></li>', '', $commpost);



                // $commpost = str_replace('<li><br /></li>', '', $commpost);
                // $commpost = str_replace('<br /></li>', '</li>', $commpost);
                ///

                $commpost = str_replace("<p>{{clear}}</p>", "<br style='clear: both;' />", $commpost);

                $commpost = str_replace("{{clear}}", "<br style='clear: both;' />", $commpost);

                /// $commpost = str_replace("<br />", "<br!>", $commpost);


                /// $commpost = preg_replace('/&(?!\w+;|#\d+;|#x[0-9a-fA-F]+;)/', '&amp;', $commpost);
                
            } else {
                
                $commpost = htmlspecialchars($commpost, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false);

                // $commpost = str_ireplace("\n", "<br />\n", $commpost);
            }



            if(!is_file("DATABASE/comments/".$commaddr)) {
                copy("SYSTEM/modules/dummy.txt", "DATABASE/comments/".$commaddr);
            }




            dbprepApnd("DATABASE/comments/".$commaddr);


            $lastLineNumber = 0;
            $bufferSize = 128 * 1024; // 128 КБ

            $file = openFileOrDie("DATABASE/comments/".$commaddr
            . ".new." . getmypid(), "rb");


            while($data = $file->freadOrDie($bufferSize)) {

                // Читаем блок данных
                // Подсчитываем количество переносов строк в блоке
                $lastLineNumber += substr_count($data, "\n");
            }

            $file = null;

            /*
            $commpost = str_replace("<br />\n", "<br />", $commpost);
            $commpost = str_replace("\n<br />", "<br />", $commpost);
            */
            $commpost = str_replace("<br />"  , "<br!>", $commpost);
            $commpost = str_replace("\n", "<br!>", $commpost);


            $commid = bin2hex(random_bytes(20)); // sha1(microtime().$ip.$userAgent);


            $commpost = "<li><$commid>".$visitor2."<d>$commpost</d><a href='?%QUERYSTRING%&amp;creply=<id>{$lastLineNumber}-$commid#R'>rep.</a> <a href='?%QUERYSTRING%&amp;cmove=<id>{$lastLineNumber}-$commid'>del.</a><$commid><$commid /></li>";



            $file = fopenOrDie("DATABASE/comments/".$commaddr.".new." . getmypid(), "ab");
            fwriteOrDie($file, $commpost."\n");
            fclose($file);
            

            if(!dbdone("DATABASE/comments/".$commaddr, $commpost2)) return false;


            $comTotalPages = calcTotPages2($lastLineNumber + 1, $commaddr, 8, true);












            /*

            if(!is_file("DATABASE/comm.count/".$commaddr)) {

                copy("SYSTEM/modules/null.txt", "DATABASE/comm.count/".$commaddr);
            }


            dbprepCommCnt("DATABASE/comm.count/".$commaddr);

            $commcnt = (int)getFileOrDie("DATABASE/comm.count/".$commaddr);

            $commcnt++;

            putFileOrDie("DATABASE/comm.count/".$commaddr.".new." . getmypid(), $commcnt, LOCK_EX);

            dbdone("DATABASE/comm.count/".$commaddr);

            */

            ##########################

            atomicCounterIncrement("DATABASE/comments/".$commaddr.".count");

            ##########################
            // mylog("<em style='color:DarkGreen'>Добавлен комментарий <a href='".$url."?".explode("&", $_SERVER['QUERY_STRING'])[0]."&commpage=".$comTotalPages."'>".$commaddr."</a> ".$ip."</em>");


            refreshhandle(0, "?".explode("&", $_SERVER['QUERY_STRING'])[0]."&ts=".microtime(true)."&commpage=".$comTotalPages."#comm-section", false);




        }

        unset($_SESSION['captcha']);

    } else {

        $errmsg = pnotfound();
    }
}

function loginPost() {

    global $safePost, $errmsg, $ip, $userAgent, $cred, $mySalt;


    // require_once "SYSTEM/cred.php";


    if(!filterUsername($safePost["username"])) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Имя содержит недопустимые символы или пустое.</p>";

    } elseif(strlen($safePost["password"]) < 8 || strlen($safePost["password"]) > 40) {

        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Размер пароля должен быть от 8 до 40 символов.</strong></p>";

    } else {

        $hashh = hash('sha512', $ip);

        if(!is_file("DATABASE/lock/".$hashh)) {
            copy("SYSTEM/modules/null.txt", "DATABASE/lock/".$hashh);
        }

        /// $lasttime = (int)getFileOrDie("DATABASE/lock/".$hashh);


        $locktmp = fopenOrDie("DATABASE/lock/".$hashh, 'rb');
        flock($locktmp, LOCK_SH);

        $lasttime = (int)stream_get_contents($locktmp);

        flock($locktmp, LOCK_UN);
        fclose($locktmp);


        // require_once "SYSTEM/salt.php";

        $username = $safePost["username"];
        $password = $safePost["password"];
        $userhash = hash('sha512', $username."@".$password."@".generateSalt($username, $password).$mySalt);

        if((time() - $lasttime) > 600 && array_key_exists($username, $cred) && explode("<!!!>", $cred[$username])[1] === $userhash) {

            session_regenerate_id(true);

            $_SESSION["username"] = $username;
            $_SESSION["userhash"] = hash('sha512', $userhash.$ip.$userAgent);

            mylog("<strong style='color:DarkGreen'>Вход в систему (".$_SESSION["username"].").</strong>");



            refreshhandle(0, "?", false);

        } else {

            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Неправильное сочетание логина и пароля. Ждите 10 минут.</strong></p>";

            putFileOrDie("DATABASE/lock/".$hashh, time(), LOCK_EX);

            mylog("<strong style='color:DarkRed'>Попытка авторизации. ".$ip."</strong>");
        }
    }
}


function imageupload() {

    global $safePost, $errmsg, $content, $checkpermission;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } else {

        $errmsg = "<h1>Info.</h1><ol class='big'>";



        $target_dir = "DATABASE/gallery/";
        $target_file = basename($_FILES["fileToUpload"]["name"]);
        $uploadOk = 1;

        $target_file = filter_filename($target_file);
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        /// if($target_file == "") {

        if(empty($target_file)) {

            $errmsg .= "<li>Неправильное имя файла.</li>";
            mylog("<em style='color:DarkOrange'>Неправильное имя файла. (".$_SESSION["username"].").</em>");

        } else {

            $target_file = $target_dir.$target_file;

            // Check if image file is a actual image or fake image
            if(isset($safePost["imgup"])) {
                $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
                if($check !== false) {
                    $errmsg .= "<li>Файл является изображением - " . $check["mime"] . ".</li>";
                    mylog("<em style='color:DarkGreen'>Файл является изображением - " . $check["mime"] . ". (".$_SESSION["username"].").</em>");
                    $uploadOk = 1;
                } else {
                    $errmsg .= "<li>Файл не является изображением.</li>";
                    mylog("<em style='color:DarkRed'>Файл не является изображением. (".$_SESSION["username"].").</em>");
                    $uploadOk = 0;
                }

            } else {

                $uploadOk = 0;
            }


            // Check if file already exists
            if(is_file($target_file)) {
                $errmsg .= "<li>Извините, файл уже существует.</li>";
                mylog("<em style='color:DarkOrange'>Извините, файл уже существует. (".$_SESSION["username"].").</em>");
                $uploadOk = 0;
            }

            // Check file size
            if($_FILES["fileToUpload"]["size"] > 2048*1024) {
                $errmsg .= "<li>Извините, ваш файл слишком большой.</li>";
                mylog("<em style='color:DarkOrange'>Извините, ваш файл слишком большой. (".$_SESSION["username"].").</em>");
                $uploadOk = 0;
            }

            // Allow certain file formats
            if(
                $imageFileType !== "webp" &&
                $imageFileType !== "jpg" &&
                $imageFileType !== "jpeg" &&
                $imageFileType !== "png" &&
                $imageFileType !== "gif"
            ) {
                $errmsg .= "<li>Извините, только WEBP, JPG, JPEG, PNG и GIF файлы разрешены.</li>";
                mylog("<em style='color:DarkOrange'>Извините, только WEBP, JPG, JPEG, PNG и GIF файлы разрешены. (".$_SESSION["username"].").</em>");
                $uploadOk = 0;
            }

            // Check if $uploadOk is set to 0 by an error
            if($uploadOk == 0) {
                $errmsg .= "<li>Извините, ваш файл не загрузился.</li>";
                mylog("<em style='color:DarkOrange'>Извините, ваш файл не загрузился. (".$_SESSION["username"].").</em>");
                // if everything is ok, try to upload file
            } else {
                if(move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                    $errmsg .= "<li>Файл ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"]), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false). " был загружен.</li>";
                    mylog("<em style='color:DarkGreen'>Файл ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"]), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', false). " был загружен. (".$_SESSION["username"].").</em>");
                } else {
                    $errmsg .= "<li>Извините, произошла ошибка при загрузке файла.</li>";
                    mylog("<em style='color:DarkRed'>Извините, произошла ошибка при загрузке файла. (".$_SESSION["username"].").</em>");
                }
            }
        }

        $errmsg .= "</ol>";

        $content = "";
        
        refreshhandle(4, "?gallery=-1", false);

    }
}





function fileDlUpload() {

    global $errmsg, $content, $checkpermission;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } else {

        $errmsg = "";



        if(!empty($_FILES['upfiledl'])) {


            $file = $_FILES['upfiledl'];

            $srcFileName = $file['name'];
            
            $srcFileName = filter_filename($srcFileName);
            $newFilePath = 'DATABASE/fupload/' . $srcFileName;

            $allowedExtensions = [
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

            $extension = pathinfo($srcFileName, PATHINFO_EXTENSION);


            $fileSize = filesize($file['tmp_name']);

            /// if($srcFileName == "") {

            if(empty($srcFileName)) {
                $errmsg = 'Имя файла пустое!';
                mylog("<em style='color:DarkOrange'>Имя файла пустое! (".$_SESSION["username"].").</em>");

            } elseif(!in_array($extension, $allowedExtensions, true)) {
                $errmsg = 'Загрузка файлов с таким расширением запрещена!';
                mylog("<strong style='color:DarkRed'>Загрузка файлов с таким расширением запрещена! (".$_SESSION["username"].").</strong>");

            } elseif($file['error'] !== UPLOAD_ERR_OK) {
                $errmsg = 'Неизвестная ошибка при загрузке файла.';
                mylog("<strong style='color:DarkRed'>Неизвестная ошибка при загрузке файла. (".$_SESSION["username"].").</strong>");

            } elseif(is_file($newFilePath)) {
                $errmsg = 'Файл с таким именем уже существует.';
                mylog("<em style='color:DarkOrange'>Файл с таким именем уже существует. (".$_SESSION["username"].").</em>");

            } elseif($fileSize === 0) {
                $errmsg = 'Файл пустой.';
                mylog("<em style='color:DarkMagenta'>Файл пустой. (".$_SESSION["username"].").</em>");

            } elseif($fileSize > 3145728) {
                $errmsg = 'Файл больше 3-х мегабайт.';
                mylog("<em style='color:DarkMagenta'>Файл больше 3-х мегабайт. (".$_SESSION["username"].").</em>");

            } elseif(!move_uploaded_file($file['tmp_name'], $newFilePath)) {
                $errmsg = 'Ошибка при перемещении файла.';
                mylog("<strong style='color:DarkRed'>Ошибка при перемещении файла. (".$_SESSION["username"].").</strong>");

            } else {

                $errmsg = 'Успешно загружено.';
                mylog("<strong style='color:DarkGreen'>Успешно загружен файл. (".$_SESSION["username"].").</strong>");
            }

            $errmsg = "<h1>Info.</h1><p class='big'>".$errmsg."</p>";

            $content = "";

            refreshhandle(4, "?dlfiles=-1", false);
        }
    }
}







function registerp() {
    global $content, $safePost, $errmsg, $cred, $mySalt;
    // require_once "SYSTEM/salt.php";
    // require_once "SYSTEM/cred.php";

    $username  = $safePost['rusername'];
    $password1 = $safePost['rpassword1'];
    $password2 = $safePost['rpassword2'];

    $content = "";
    $errmsg = "";


    // Validate password strength
    $uppercase    = preg_match('@[A-Z]@', $password1);
    $lowercase    = preg_match('@[a-z]@', $password1);
    $number       = preg_match('@[0-9]@', $password1);
    $specialChars = preg_match('@[^\w]@', $password1);



    if(!filterUsername($username)) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Имя содержит недопустимые символы или пустое.</strong></p>";
    } elseif(array_key_exists($username, $cred)) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Такой пользователь уже существует!</strong></p>";
    } elseif($password1 !== $password2) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Пароль и проверка не совпадают!</strong></p>";
    } elseif($password1 === $username) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Имя и пароль не должны совпадать!</strong></p>";
    } elseif(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password1) < 8 || strlen($password1) > 40) {
        $errmsg = '<h1>ОШИБКА.</h1><p class="big"><strong>Пароль должен содержать 8-40 символов, включая заглавную букву, цифру и специальный символ.</strong></p>';
    } else {

        // Генерация безопасного хэша пароля

        $userhash = hash('sha512', $username."@".$password1."@".generateSalt($username, $password1).$mySalt);

        $username = str_replace("&", "&amp;", $username);

        $content .= "
        <p>Добавьте в <strong>cred.php</strong> строчку:</p>
        <p><code style='display: inline-block; width: 100%; padding: .75rem; font-size: 1.4rem; text-wrap: nowrap; overflow-x: scroll;'>\$cred['$username'] = \"<strong style='color: red;'>X</strong>&lt;!!!&gt;$userhash\";</code></p>
        <p>Где X:</p>
        <ul>
            <li><strong>1</strong> = <em>Обычные пользователи. Могут оставлять комментарии с HTML и не вводить капчу.</em></li>
            <li><strong>2</strong> = <em>Модераторы комментариев. Могут только удалять комменты. Могут оставлять комментарии с HTML и не вводить капчу.</em></li>
            <li><strong>3</strong> = <em>Редакторы - не могут сбрасывать лог и тереть комменты. (Редактируют статьи и перемещают). Могут оставлять комментарии с HTML и не вводить капчу.</em></li>
            <li><strong>4</strong> = <em>Администраторы - могут всё.</em></li>
        </ul>
        ";
    }

    // Независимо от результата, вывести форму регистрации
    registerg();
}



// Функция для обработки POST запроса и сохранения выбранного шаблона в сессии
function saveTplSess() {

    global $safePost;

    // Папка с шаблонами
    $templateDir = 'TEMPLATES/';

    // Получаем список директорий-шаблонов
    $templates = array_map('basename', glob($templateDir . '*.tpl', GLOB_ONLYDIR));

    // Если шаблон отправлен через POST и он существует в списке шаблонов
    if(isset($safePost['selected_template']) && in_array($safePost['selected_template'], $templates, true)) {
        // Сохраняем выбранный шаблон в сессии
        // $_SESSION['selected_template'] = $safePost['selected_template'];
        set_cookie("selected_template", $safePost['selected_template'], time() + (10 * 365 * 24 * 60 * 60));
        
    }

    refreshhandle(0, "?".$_SERVER['QUERY_STRING'], false);
}







function pobyava() {

    global $safePost, $checkpermission, $errmsg;

    if($checkpermission < 3) {

        $errmsg = pforbidden();

    } else {

        $obstring = $safePost['pobyava'];

        ensure_html_purifier_loaded();

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.1');
        $config->set('HTML.TidyLevel', 'heavy'); // all changes, minus...
        $config->set('HTML.TidyRemove', 'br@clear');

        $config->set('HTML.ForbiddenElements', 'h1,h2,h3,h4,h5,h6');

        $purifier = new HTMLPurifier($config);
        $obstring = $purifier->purify($obstring);

        putFileOrDie("DATABASE/obyava.txt", $obstring);

        refreshhandle(0, "?", false);
    }
}