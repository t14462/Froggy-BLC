<?php

declare(strict_types=1);

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

const LOGIN_LOCKOUT_SECONDS = 600;

################################################
################################################
################################################

function savePage() {

    global $safePost, $numcache, $head, $body, $content, $errmsg, /* $apiKeyTinyMCE, */ $mainPageTitle, $checkpermission, $ispageexist, $url, $chTimeDB, $csrf, $idcache;

    /*
    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } else {

    */


        if(!hash_equals((string)$csrf, (string)($safePost["csrf"] ?? ""))) {

            $recovery = substr($safePost["textedit"], 0, (int)(128.125 * 1024));

            $recovery = str_ireplace("<textarea", "&lt;textarea", $recovery);
            $recovery = str_ireplace("</textarea", "&lt;/textarea", $recovery);
            $recovery = str_ireplace("textarea>", "textarea&gt;", $recovery);

            $recovery = escape_amp_txtarea($recovery);

            $recovery = "<!DOCTYPE html>
            <html lang='ru'>
            <head>
                <meta charset='UTF-8' />
                <meta name='viewport' content='width=device-width, initial-scale=1.0' />
                <title>Страница Восстановления Данных.</title>
            </head>
            
            <body>
                <h1>Страница Восстановления Данных.</h1>
                <textarea style='width: 95%; min-height: 80vh; padding: 2%; resize: none;' readonly='readonly'>$recovery</textarea>
            </body>
            </html>\n";

            http_response_code(200);
            exit((string)$recovery);
        }



        
        $textedit = $safePost["textedit"];
        $pgtitle  = $safePost["title"];
        $htag     = (int)$safePost["h"];

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

        $textedit = substr($textedit, 0, (int)(128.125 * 1024));

        // $textedit = escape_amp_txtarea($textedit);

        $pgtitle = normalize_entities_my($pgtitle);

        $pgtitle = mb_superTrim($pgtitle);

        // $pgtitle = htmlspecialchars($pgtitle, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);

        // $pgtitle = preg_replace("/&amp;(#?\w+);/i", "&$1;", $pgtitle);

        $herror = 0;
        $database = 'DATABASE/DB/data.html';
        $pageIndex = seoNumGet() - 1;
        $pageId = $idcache[$pageIndex] ?? '';
        $formPageId = $safePost['pageid'] ?? $pageId;
        if(preg_match('/\A[a-f0-9]{40}\z/', $formPageId) !== 1) {
            $formPageId = '';
        }

        // $checknum = (int)explode("/", $_SERVER['QUERY_STRING'])[0];

        // $checknum = seoLinkDecode($checknum) - 1;

        // $checknum = seoNumGet() - 1;

        if($safePost['dbtimestamp'] !== filemtimeMy($database) || $formPageId !== $pageId) {

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

        } elseif(mb_strlen(shrinkNameLenght(removeKwd($pgtitle))) < 3) {

            $herror = 2;

        } elseif(str_contains(html_entity_decode($pgtitle, ENT_QUOTES | ENT_HTML401, 'UTF-8'), '/')) {

            $herror = 3;

        } elseif($htag < 1 || $htag > 6) {

            $herror = 1;

        } elseif(!sanCheckHor($pageIndex, $htag)) {

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


        /// $textedit = remEmptyLi($textedit);


        if($herror === 0) {
            $checkHTML = escape_amp_txtarea($textedit);
            $checkHTML = @str_get_html($checkHTML, false, true, "UTF-8", false) or ($herror = 11);
            unset($checkHTML);
        }


        /*
        $textedit = preg_replace('~(?:[\p{Zs}\s]*<br />[\p{Zs}\s]*)+(<ul\b|<ol\b|</li>)~ui', '$1', $textedit);
        $textedit = preg_replace('~(?:[\p{Zs}\s])+(<ul\b|<ol\b|</li>)~ui', '$1', $textedit);
        
        $textedit = str_ireplace('<li></li>', '', $textedit);
        */

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

                                config.protectedSource.push(/&amp;[a-zA-Z][a-zA-Z0-9]*;/gi);
                                config.protectedSource.push(/&amp;#\d+;/g);
                                config.protectedSource.push(/&amp;#x[0-9a-fA-F]+;/gi);

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

                                let href = link.getAttribute('href')?.trim();

                                // Пропускаем пустые ссылки и якоря.
                                if(!href || href.startsWith('#')) return;

                                // URL используем только для проверки, без пересборки ссылки.
                                try {
                                    const url = new URL(href, document.baseURI);

                                    if(url.origin !== window.location.origin || !/^https?:$/.test(url.protocol)) return;
                                } catch {
                                    return;
                                }

                                // Временно отделяем якорь.
                                const hashIndex = href.indexOf('#');
                                const hash = hashIndex === -1 ? '' : href.slice(hashIndex);

                                if(hashIndex !== -1) href = href.slice(0, hashIndex);

                                // Разделяем адрес и содержимое после первого «?».
                                const queryIndex = href.indexOf('?');
                                const path = queryIndex === -1 ? href : href.slice(0, queryIndex);
                                const query = queryIndex === -1 ? '' : href.slice(queryIndex + 1);

                                // Удаляем старые csrf и leaveedit, не перекодируя остальное.
                                const parts = query.split('&').filter(function(part) {
                                    return part !== '' && !/^(?:csrf|leaveedit)=/.test(part);
                                });

                                parts.push('leaveedit=1', 'csrf=$csrf');

                                link.setAttribute('href', path + '?' + parts.join('&') + hash);

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

        if($herror === 0) {
            if($safePost['dbtimestamp'] !== $chTimeDB) {
                $herror = 5;
            } elseif(!dbprepApnd($database, $chTimeDB, failureStatus: $failureStatus)) {
                if($failureStatus === 500) {
                    return reportDatabaseWriteFailure($textedit3);
                }
                $herror = 5;
            } else {
                $source = @fopen($database . '.src.' . getmypid(), 'rb');
                if($source === false) {
                    cancelDatabaseWrite($database);
                    return reportDatabaseWriteFailure($textedit3);
                }
                $page = pageInSnapshot($source, $pageIndex);
                if($page === null) {
                    fclose($source);
                    cancelDatabaseWrite($database);
                    $herror = 5;
                }
            }
        }
        $dbMtime = sprintf('%.4F', $safePost['dbtimestamp']);

        // $textedit2 = protect_amp_entities_for_textarea($textedit);

        $textedit2 = escape_amp_txtarea($textedit);


        // На самом деле, эта строка не нужна, так-как в базу уже сохраняются
        // специальные HTML-символы в виде сущностей, с помощью post-get-filter.php
        // $pgtitle2 = escape_amp_txtarea($pgtitle);

        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $queryBase = explode('&', $queryString)[0];

        $dumpEdit = "<form method='post'>

            <input type='hidden' name='dbtimestamp' value='$dbMtime' />
            <input type='hidden' name='pageid' value='$formPageId' />

            <input type='hidden' name='csrf' value='$csrf' />

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

            <a href='?".$queryBase."&amp;leaveedit=1&amp;csrf=$csrf'>Отменить ⬅️</a>
            <a href='?".$queryBase."&amp;pagedel=1&amp;pageid=$formPageId&amp;csrf=$csrf' id='pagedelbutton'>❌ Удалить страницу</a>

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

                $replacement = $page['id'].'<head'.$htag.'>'.$pgtitle.'</head'.$htag.'>'.$page['seo']
                    .'<!1!>'.time().'<!2!><br!>'.$textedit."\n";
                $written = rewritePageSnapshot($source, $page['start'], $page['end'],
                    static fn($target): bool => @fwrite($target, $replacement) === strlen($replacement));
                fclose($source);
                if(!$written) {
                    cancelDatabaseWrite($database);
                    reportPageWriteFailure(500);
                    $head .= $headEd;
                    $body .= $bodyEd;
                    $content = $dumpEdit;
                    return false;
                }

                if(!dbdone($database, $textedit3)) return false;

                $queryString = $_SERVER['QUERY_STRING'] ?? '';
                mylog("<em style='color:DarkOrange'>Страница сохранена. (" . explode("&", $queryString)[0] . " - " . $_SESSION["username"].").</em>");

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

                http_response_code(409);
                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 5.</h1><p class='big'><strong>База Данных была изменена внешним процессом.</strong></p>";
                $errmsg .= "<p class='big'>Скопируйте свою правку из формы и откройте редактор статьи заново.</p>";

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

                $errmsg .= "<p class='big'><em><u>Ваш IP-адрес изменился — сессия недействительна!</u><br />В форме ниже представлены несохранённые данные, которые вы можете скопировать.</em></p>";

                $content = $dumpEdit;

                // mylog("<em style='color:DarkMagenta'>БД была заблокирована. (Отредактирована другим пользователем).</em>");

                break;

            case 11:

                $head .= $headEd;
                $body .= $bodyEd;

                $errmsg = "<h1>ОШИБКА 11.</h1><p class='big'>Не удалось обработать HTML статьи.</p>";

                /// $errmsg .= "<p class='big'><em><u>Ваш IP-адрес изменился — сессия недействительна!</u><br />В форме ниже представлены несохранённые данные, которые вы можете скопировать.</em></p>";

                $content = $dumpEdit;

                // mylog("<em style='color:DarkMagenta'>БД была заблокирована. (Отредактирована другим пользователем).</em>");

                break;

        }
    /* } */

    /// unlockByName($_SESSION['username'] ?? "");
}

function renderCommentDraft(string $draft, string $message, ?int $status = null): void {
    global $errmsg, $commRecov, $content;

    $errmsg = $message;
    // Первый перенос после открывающего тега textarea браузер пропускает.
    $commRecov = "\n" . htmlspecialchars($draft, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if($status !== null) {
        http_response_code($status);
    }
    $commentFormRendered = pageload();

    if($status === 503 && !$commentFormRendered) {
        $errmsg = $message;
        $content .= "<textarea rows='9' name='commpost'>" . $commRecov . "</textarea>";
        http_response_code($status);
    }
}

function reportCommentVersionConflict(string $draft): void {
    renderCommentDraft($draft,
        "<h1>ПРЕДУПРЕЖДЕНИЕ.</h1><p class='big'><strong>Во время написания комментария БД изменилась.</strong></p>",
        409
    );
}

function reportCommentCheckFailure(Throwable $error): bool {
    global $safePost;

    error_log('Froggy-BLC: ошибка проверки комментария. ' . $error->getMessage());
    renderCommentDraft(mb_substr($safePost['commpost'], 0, 2550),
        "<h1>ОШИБКА.</h1><p class='big'><strong>Проверка комментария временно недоступна. Ваш текст сохранён.</strong></p>",
        503
    );
    return false;
}

function checkCommentRate(string $ip, string $draft): bool {
    try {
        $allowed = canProceed($ip);
    } catch(JsonException|RuntimeException $error) {
        return reportCommentCheckFailure($error);
    }

    if(!$allowed) {
        renderCommentDraft($draft,
            "<h1>ОШИБКА.</h1><p class='big'><strong>Временной интервал между комментариями Три Минуты.</strong></p>"
        );
    }
    return $allowed;
}

function checkCommentCaptcha(string $captcha, string $expectedHash, string $draft): bool {
    $savedHash = $_SESSION['captcha'] ?? '';
    $matches = $savedHash && hash_equals($savedHash, $expectedHash);
    try {
        $missingOrUsed = !$savedHash || ($matches && !repeatCaptcha($savedHash));
    } catch(JsonException|RuntimeException $error) {
        return reportCommentCheckFailure($error);
    }

    if($missingOrUsed) {
        $message = 'Повторный ввод CAPTCHA, или её отключение недопустимы.';
    } elseif(empty($captcha) || !$matches) {
        $message = 'Вы не ввели код подтверждения.';
    } else {
        return true;
    }

    renderCommentDraft($draft, "<h1>ОШИБКА.</h1><p class='big'><strong>$message</strong></p>");
    return false;
}

function normalizeCommentDraft(string $text): string {
    $text = normalize_entities_my(mb_substr($text, 0, 2550));
    return mb_softTrim(str_ireplace('<br!>', "\n", $text));
}

function commentAuthorMarkup(string $visitor, int $permission, array $credentials): string {
    if($permission) {
        return "👤<strong class='a$permission'> " . $_SESSION['username'] . "</strong>";
    }

    $visitor = validateVisitorName($visitor, $credentials, truncate: true);
    if($visitor !== null) {
        set_cookie('visitor', $visitor, time() + 365 * 24 * 60 * 60);
        $_COOKIE['visitor'] = $visitor;
    }

    return "👤<b> ".($visitor ?? 'Аноним')."</b>";
}

function checkCommentLength(string $draft): bool {
    if(mb_strlen($draft) > 2500) {
        $message = 'Ваш комментарий слишком большой (больше 2500 символов).';
    } elseif(mb_strlen(mb_superTrim(normalize_entities_my($draft))) < 5) {
        $message = 'Ваш комментарий слишком мал.';
    } else {
        return true;
    }

    renderCommentDraft($draft, "<h1>ОШИБКА.</h1><p class='big'><strong>$message</strong></p>");
    return false;
}

function reportMissingCommentReply(string $draft): void {
    renderCommentDraft($draft,
        "<h1>ОШИБКА.</h1><p class='big'><strong>Комментарий для ответа не найден. Ваш текст сохранён в форме.</strong></p>"
    );
}

function formatCommentForStorage(string $draft, bool $allowHtml): string {
    global $patternYT, $replacementYT, $patternVimeo, $replacementVimeo, $patternDM, $replacementDM;

    $text = str_replace('%QUERYSTRING%', '', $draft);
    if(!$allowHtml) {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);
        return str_replace("\n", '<br!>', $text);
    }

    ensure_html_purifier_loaded();
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Core.Encoding', 'UTF-8');
    $config->set('HTML.Doctype', 'XHTML 1.1');
    $config->set('HTML.TidyLevel', 'heavy');
    $config->set('HTML.TidyRemove', 'br@clear');
    $config->set('HTML.ForbiddenElements', 'h1,h2,h3,h4,h5,h6,b');

    $purifier = new HTMLPurifier($config);
    $text = escape_amp_txtarea($purifier->purify($text));
    $html = parseContentHtml($text) or die('XSS?.. Пустой или битый HTML.');
    $html = replaceParagraphs($html);
    $html = addClassToAllUl($html, 'ul-fix');
    $html = convert_infoboxes_to_aside($html);
    $html = replaceBr($html);
    $html = replaceSemanticSpans($html);
    $html = convertQuotBlocks($html);
    $html = wrap_images_with_figure($html);
    $html = parseSpoilers($html);
    $text = $html->save();
    $html->clear();

    $text = preg_replace_callback($patternYT, $replacementYT, $text);
    $text = preg_replace_callback($patternVimeo, $replacementVimeo, $text);
    $text = preg_replace_callback($patternDM, $replacementDM, $text);
    $text = str_replace("{{lambda}}", "<div style='font-family:monospace; white-space:nowrap; font-size:6rem; text-align:center'>&nbsp;<span class='a4'>&lambda;</span>++<br /><span class='a3'>&lambda;</span>&nbsp;<span class='a2'>&lambda;</span>&nbsp;<div style='font-size:1rem; font-weight:bold;'>NERD REVENGE</div></div>", $text);
    $text = str_replace('/>', ' />', $text);
    $text = str_replace('  />', ' />', $text);
    $text = str_replace('{{clear}}', "<br style='clear: both;' />", $text);

    return str_replace("\n", '<br!>', $text);
}

function formatCommentEntry(string $id, int $lineNumber, string $author, string $text): string {
    $time = date('Y-m-d H:i');
    return "<li><$id>$author<d>$text<br!><br!>$time</d>"
        . "<a href='?<QS>&amp;creply=<id>{$lineNumber}-$id#R'>rep.</a> "
        . "<a href='?<QS>&amp;cmove=<id>{$lineNumber}-$id'>del.</a><$id><$id /></li>";
}

function commentReply() {
    global $safePost, $errmsg, $idcache, $ip, $checkpermission, $userAgent, $cred, $mySalt;

    $pageId = $safePost['commaddr'] ?? '';
    $parentId = $safePost['repcommid'] ?? '';
    validateHex40($pageId);
    validateHex40($parentId);

    $filename = 'DATABASE/comments/' . $pageId;
    if(!in_array($pageId, $idcache, true) || !is_file($filename)) {
        $errmsg = pnotfound();
        return;
    }

    $draft = normalizeCommentDraft($safePost['commpost']);
    $captcha = substr($safePost['captcha'], 0, 6);
    $author = commentAuthorMarkup($safePost['visitor'], $checkpermission, $cred);
    $lineNumber = (int)($safePost['pgcommnum'] ?? 0);
    $lineNumber = (int)substr((string)$lineNumber, 0, 10);

    $thread = '';
    if($lineNumber >= 0) {
        try {
            $file = new SafeSplFileObject($filename, 'rb');
            $file->seek($lineNumber);
            $thread = (string)$file->current();
        } catch(RuntimeException $e) {
            // Строка могла исчезнуть после открытия формы.
        } finally {
            $file = null;
        }
    }

    $today = date('Y-m-d');
    if(!$checkpermission && !checkCommentRate($ip, $draft)) {
        return;
    }
    if($safePost['dbtimestamp'] !== filemtimeMy($filename)) {
        reportCommentVersionConflict($draft);
        return;
    }
    $parentMarker = "<$parentId />";
    if(substr_count($thread, $parentMarker) !== 1) {
        reportMissingCommentReply($draft);
        return;
    }
    if(!$checkpermission && !checkCommentCaptcha($captcha, hash('sha256', $captcha.$mySalt.$ip.$userAgent.$today), $draft)) {
        return;
    }
    if(!checkCommentLength($draft)) {
        return;
    }
    if(strlen($thread) >= 100 * 1024) {
        renderCommentDraft($draft,
            "<h1>ОШИБКА.</h1><p class='big'><strong>Превышен лимит вложенных комментариев в ветке дискуссии.</strong></p>"
        );
        return;
    }

    $text = formatCommentForStorage($draft, (bool)$checkpermission);
    if(!prepareCommentWrite($pageId, failureStatus: $failureStatus, expectedVersion: $safePost['dbtimestamp'])) {
        if($failureStatus === 500) {
            return reportDatabaseWriteFailure($draft);
        }
        if($failureStatus === 409) {
            reportCommentVersionConflict($draft);
        } else {
            reportMissingCommentReply($draft);
        }
        return;
    }

    $pid = getmypid();
    $stagedPath = $filename . '.new.' . $pid;
    $sourcePath = $filename . '.src.' . $pid;
    $file = openFileOrDie($stagedPath, 'rb');
    $file->seekOrDie($lineNumber);
    $thread = rtrim($file->current());
    $file = null;

    do {
        $commentId = bin2hex(random_bytes(20));
    } while(str_contains($thread, '<' . $commentId));

    $reply = formatCommentEntry($commentId, $lineNumber, $author, $text);
    $thread = str_replace($parentMarker, "<ul>$reply</ul>" . $parentMarker, $thread, $replacements);
    if($replacements !== 1) {
        cancelDatabaseWrite($filename);
        reportMissingCommentReply($draft);
        return;
    }
    $thread = str_replace('</ul><ul>', '', $thread);

    $source = openFileOrDie($sourcePath, 'rb');
    $source->seekOrDie($lineNumber);
    $threadOffset = $source->ftell();
    $source->fgetsOrDie();
    $tailOffset = $source->ftell();
    if($threadOffset === false || $tailOffset === false) {
        $source = null;
        cancelDatabaseWrite($filename);
        return reportDatabaseWriteFailure($draft);
    }

    $file = fopenOrDie($stagedPath, 'r+b');
    ftruncateOrDie($file, $threadOffset);
    fclose($file);

    $destination = openFileOrDie($stagedPath, 'ab');
    $destination->fwriteOrDie($thread . "\n");
    if(catTailAvailable()) {
        $destination->fflushOrDie();
        $destination = null;
        $source = null;
        concater($stagedPath, $sourcePath, $tailOffset);
    } else {
        while($chunk = $source->freadOrDie(128 * 1024)) {
            $destination->fwriteOrDie($chunk);
        }
        $destination->fflushOrDie();
        $destination = null;
        $source = null;
    }

    if(!publishComment($pageId, $commentId, $draft)) {
        return false;
    }

    $currentPage = intdiv($lineNumber, 8);
    $lastPage = calcTotPages($pageId, 8);
    $requestedPage = $safePost['commpage'] ?? null;
    if(!is_int($requestedPage)) {
        $commentPage = $currentPage;
    } elseif($requestedPage > $lastPage || $requestedPage < 0) {
        $commentPage = $lastPage;
    } else {
        $commentPage = $requestedPage;
    }

    $queryBase = explode('&', $_SERVER['QUERY_STRING'] ?? '', 2)[0];
    refreshhandle(0, '?' . $queryBase . '&ts=' . microtime(true) . '&commpage=' . $commentPage . '#comm-section', false);
    unset($_SESSION['captcha']);
}

function postComment() {
    global $safePost, $errmsg, $idcache, $ip, $checkpermission, $userAgent, $cred, $mySalt;

    $pageId = $safePost['commaddr'];
    validateHex40($pageId);
    if(!in_array($pageId, $idcache, true)) {
        $errmsg = pnotfound();
        return;
    }

    $filename = 'DATABASE/comments/' . $pageId;
    $draft = normalizeCommentDraft($safePost['commpost']);
    $captcha = substr($safePost['captcha'], 0, 6);
    $author = commentAuthorMarkup($safePost['visitor'], $checkpermission, $cred);
    $today = date('Y-m-d');

    if(!$checkpermission && !checkCommentRate($ip, $draft)) {
        return;
    }
    if($safePost['dbtimestamp'] !== filemtimeMy($filename)) {
        reportCommentVersionConflict($draft);
        return;
    }
    if(!$checkpermission && !checkCommentCaptcha($captcha, hash('sha256', $captcha.$mySalt.$ip.$userAgent.$today), $draft)) {
        return;
    }
    if(!checkCommentLength($draft)) {
        return;
    }

    $text = formatCommentForStorage($draft, (bool)$checkpermission);
    if(!prepareCommentWrite($pageId, create: true, failureStatus: $failureStatus, expectedVersion: $safePost['dbtimestamp'])) {
        if($failureStatus === 500) {
            return reportDatabaseWriteFailure($draft);
        }
        if($failureStatus === 409) {
            reportCommentVersionConflict($draft);
            return;
        }
        $errmsg = pnotfound();
        return;
    }

    $stagedPath = $filename . '.new.' . getmypid();
    $lineNumber = 0;
    $file = openFileOrDie($stagedPath, 'rb');
    while($chunk = $file->freadOrDie(128 * 1024)) {
        $lineNumber += substr_count($chunk, "\n");
    }
    $file = null;

    $commentId = bin2hex(random_bytes(20));
    $entry = formatCommentEntry($commentId, $lineNumber, $author, $text);
    $file = fopenOrDie($stagedPath, 'ab');
    fwriteOrDie($file, $entry . "\n");
    fflushOrDie($file);
    fclose($file);

    $commentPage = 0;
    $updatePages = static function () use ($lineNumber, $pageId, &$commentPage): void {
        $commentPage = calcTotPages2($lineNumber + 1, $pageId, 8, true);
    };
    if(!publishComment($pageId, $commentId, $draft, $updatePages)) {
        return false;
    }

    $queryBase = explode('&', $_SERVER['QUERY_STRING'] ?? '', 2)[0];
    refreshhandle(0, '?' . $queryBase . '&ts=' . microtime(true) . '&commpage=' . $commentPage . '#comm-section', false);
    unset($_SESSION['captcha']);
}

function loginPost() {

    global $safePost, $errmsg, $ip, $userAgent, $cred, $mySalt;

    $username = $safePost['username'] ?? null;
    $password = $safePost['password'] ?? null;

    if(!is_string($username) || !is_string($password)) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Неверные данные формы.</p>";
        return;
    }

    if(!filterUsername($username)) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Имя содержит недопустимые символы или пустое.</p>";
        return;
    }

    $passwordLength = strlen($password);
    if($passwordLength < 8 || $passwordLength > 40) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Размер пароля должен быть от 8 до 40 байт.</strong></p>";
        return;
    }

    $stateFile = "DATABASE/lock/".hash('sha512', $ip);
    $lock = lockStateFile($stateFile . '.lock', LOCK_EX);
    if($lock === false) {
        throw new RuntimeException('Не удалось заблокировать файл попыток входа.');
    }

    try {
        clearstatcache(true, $stateFile);
        $lockContents = file_exists($stateFile) ? @file_get_contents($stateFile) : '';
        if($lockContents === false) {
            throw new RuntimeException('Не удалось прочитать время последней попытки входа.');
        }

        $lasttime = (int)$lockContents;
        $now = time();
        $userhash = hash('sha512', $username."@".$password."@".generateSalt($username, $password).$mySalt);
        $credParts = array_key_exists($username, $cred)
            ? explode("<!!!>", $cred[$username], 2)
            : [];

        $loginAllowed = ($now - $lasttime) > LOGIN_LOCKOUT_SECONDS
            && isset($credParts[1])
            && hash_equals($credParts[1], $userhash);

        if(!$loginAllowed) {
            // Каждая отклонённая попытка намеренно продлевает блокировку.
            if(!replaceFileContents($stateFile, (string)$now)) {
                throw new RuntimeException('Не удалось полностью сохранить время попытки входа.');
            }
        }
    } finally {
        fclose($lock);
    }

    if(!$loginAllowed) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Неправильное сочетание логина и пароля. Ждите 10 минут.</strong></p>";
        mylog("<strong style='color:DarkRed'>Попытка авторизации. ".$ip."</strong>");
        return;
    }

    if(!session_regenerate_id(true)) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Не удалось обновить идентификатор сессии. Повторите вход.</p>";
        return;
    }

    $_SESSION["username"] = $username;
    $_SESSION["userhash"] = hash('sha512', $userhash.$ip.$userAgent);
    $_SESSION['csrf'] = random_int(0, 0xFFFFFFFFFFFFFFF);

    mylog("<strong style='color:DarkGreen'>Вход в систему (".$_SESSION["username"].").</strong>");
    refreshhandle(0, "?", false);
}

function imageupload() {

    global $errmsg, $content, $checkpermission;

    if($checkpermission < 3) {
        $errmsg = pforbidden();
        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";
        return;
    }

    $file = $_FILES['fileToUpload'] ?? null;
    $error = is_array($file)
        ? ($file['error'] ?? UPLOAD_ERR_NO_FILE)
        : UPLOAD_ERR_NO_FILE;

    $message = match ($error) {
        UPLOAD_ERR_OK => null,
        UPLOAD_ERR_NO_FILE => 'Файл не был передан.',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл превышает допустимый размер.',
        UPLOAD_ERR_PARTIAL => 'Файл загрузился не полностью.',
        default => 'Ошибка при загрузке файла.',
    };

    if($message === null) {
        $name = $file['name'] ?? null;
        $filename = is_string($name) ? filter_filename($name, failOnInvalid: false) : '';
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $tmpName = $file['tmp_name'] ?? null;
        $fileSize = (int)($file['size'] ?? 0);
        $imageTypes = [
            'webp' => 'image/webp',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];

        if($filename === '') {
            $message = 'Некорректное имя файла.';
        } elseif(!isset($imageTypes[$extension])) {
            $message = 'Разрешены только файлы WEBP, JPG, JPEG, PNG и GIF.';
        } elseif($fileSize <= 0) {
            $message = 'Файл пустой.';
        } elseif($fileSize > (3.5 * 1024 * 1024)) {
            $message = 'Файл больше 3.5 МиБ.';
        } elseif(!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            $message = 'Временный файл загрузки недоступен.';
        } elseif(!class_exists(finfo::class)) {
            $message = 'Проверка формата изображения недоступна.';
        } elseif((new finfo(FILEINFO_MIME_TYPE))->file($tmpName) !== $imageTypes[$extension]) {
            $message = 'Содержимое файла не соответствует расширению.';
        } elseif(@getimagesize($tmpName) === false) {
            $message = 'Не удалось прочитать изображение.';
        } else {
            $targetFile = 'DATABASE/gallery/' . $filename;
            $lock = lockStateFile('DATABASE/lock/gallery-upload.lock', LOCK_EX, 'c');

            if($lock === false) {
                $message = 'Не удалось получить доступ к галерее для записи.';
            } else {
                try {
                    if(file_exists($targetFile) || is_link($targetFile)) {
                        $message = 'Файл с таким именем уже существует.';
                    } elseif(!storeUploadedFile($tmpName, $targetFile)) {
                        $message = 'Не удалось сохранить изображение.';
                    }
                } finally {
                    fclose($lock);
                }
            }
        }
    }

    $success = $message === null;
    if($success) {
        $nameHtml = htmlspecialchars($filename, ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);
        $message = "Файл " . $nameHtml . " был загружен.";
    }

    $color = $success ? 'DarkGreen' : 'DarkRed';
    $errmsg = "<h1>Info.</h1><ol class='big'><li>" . $message . "</li></ol>";
    mylog("<strong style='color:" . $color . "'>" . $message . " (" . $_SESSION["username"] . ").</strong>");
    $content = "";
    refreshhandle(4, "?gallery=-1", false);
}

function fileDlUpload() {

    global $errmsg, $content, $checkpermission;

    if( $checkpermission < 3 ) {

        $errmsg = pforbidden();

        $errmsg .= "<p class='big'>Только <strong>редакторы</strong> и <strong>администраторы</strong> это могут делать.</p>";

    } else {

        $errmsg = "";

        $file = $_FILES['upfiledl'] ?? null;
        $error = is_array($file)
            ? ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            : UPLOAD_ERR_NO_FILE;

        $message = match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_NO_FILE => 'Файл не был передан.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл превышает допустимый размер.',
            UPLOAD_ERR_PARTIAL => 'Файл загрузился не полностью.',
            default => 'Ошибка при загрузке файла.',
        };

        if($message === null && (!is_string($file['name'] ?? null) || $file['name'] === '')) {
            $message = 'Некорректное имя файла.';
        }

        if($message !== null) {
            $errmsg = "<h1>Info.</h1><p class='big'>" . $message . "</p>";
            mylog("<strong style='color:DarkRed'>" . $message . " (" . $_SESSION["username"] . ").</strong>");
            $content = "";
            refreshhandle(4, "?dlfiles=-1", false);
            return;
        }

        $srcFileName = filter_filename($file['name'], failOnInvalid: false);
        $newFilePath = 'DATABASE/fupload/' . $srcFileName;

        if($srcFileName === '') {

            $errmsg = 'Некорректное имя файла.';
            mylog("<em style='color:DarkOrange'>Некорректное имя файла. (".$_SESSION["username"].").</em>");

        } elseif(!isDownloadFilename($srcFileName)) {

            $errmsg = 'Загрузка файлов с таким расширением запрещена!';
            mylog("<strong style='color:DarkRed'>Загрузка файлов с таким расширением запрещена! (".$_SESSION["username"].").</strong>");

        } else {

            $tmpName = $file['tmp_name'] ?? null;
            $fileSize = (int)($file['size'] ?? 0);

            if(!is_string($tmpName) || !is_uploaded_file($tmpName)) {

                $errmsg = 'Временный файл загрузки недоступен.';
                mylog("<strong style='color:DarkRed'>Временный файл загрузки недоступен. (".$_SESSION["username"].").</strong>");

            } elseif($fileSize === 0) {

                $errmsg = 'Файл пустой.';
                mylog("<em style='color:DarkMagenta'>Файл пустой. (".$_SESSION["username"].").</em>");

            } elseif($fileSize > (3.5 * 1024 * 1024)) {

                $errmsg = 'Файл больше 3.5 МиБ.';
                mylog("<em style='color:DarkMagenta'>Файл больше 3.5 МиБ. (".$_SESSION["username"].").</em>");

            } else {

                $lock = lockStateFile('DATABASE/lock/file-upload.lock', LOCK_EX, 'c');
                $logColor = 'DarkRed';

                if($lock === false) {
                    $errmsg = 'Не удалось получить доступ к каталогу загрузок для записи.';
                } else {
                    try {
                        if(file_exists($newFilePath) || is_link($newFilePath)) {
                            $errmsg = 'Файл с таким именем уже существует.';
                            $logColor = 'DarkOrange';
                        } elseif(!storeUploadedFile($tmpName, $newFilePath)) {
                            $errmsg = 'Ошибка при перемещении файла.';
                        } else {
                            $errmsg = 'Успешно загружено.';
                            $logColor = 'DarkGreen';
                        }
                    } finally {
                        fclose($lock);
                    }
                }

                mylog("<strong style='color:" . $logColor . "'>" . $errmsg . " (" . $_SESSION["username"] . ").</strong>");
            }
        }

        $errmsg = "<h1>Info.</h1><p class='big'>".$errmsg."</p>";

        $content = "";

        refreshhandle(4, "?dlfiles=-1", false);
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
    $uppercase    = preg_match('@\p{Lu}@u', $password1);
    $lowercase    = preg_match('@\p{Ll}@u', $password1);
    $number       = preg_match('@[0-9]@', $password1);
    $specialChars = preg_match('@[^\w]@', $password1);

    if(!filterUsername($username)) {
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Имя содержит недопустимые символы или пустое.</strong></p><p class='big'><strong>Минимальная Длина Имени = 3<br />Максимальная Длина Имени = 50</strong></p>";
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

        /// $username = str_replace("&", "&amp;", $username);

        $username = escape_amp_txtarea($username);

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
        <p class='big'>ЭТО СЕКРЕТНАЯ СТРОКА!<br />ДЛЯ ЕЁ ДОБАВЛЕНИЯ СВЯЖИТЕСЬ С АДМИНОМ,<br />И БОЛЬШЕ НИКОМУ ЕЁ НЕ СООБЩАЙТЕ!</p>
        ";
    }

    // Независимо от результата, вывести форму регистрации
    registerg();
}

// Сохраняет выбранный шаблон в cookie.
function saveTplSess() {

    global $safePost;

    // Папка с шаблонами
    $templateDir = 'TEMPLATES/';

    // Получаем список директорий-шаблонов
    $templates = array_map('basename', glob($templateDir . '*.tpl', GLOB_ONLYDIR) ?: []);

    // Если шаблон отправлен через POST и он существует в списке шаблонов
    if(isset($safePost['selected_template']) && in_array($safePost['selected_template'], $templates, true)) {
        set_cookie("selected_template", $safePost['selected_template'], time() + (10 * 365 * 24 * 60 * 60));
    }

    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    refreshhandle(0, "?".$queryString, false);
}

function pobyava() {

    global $safePost, $checkpermission, $errmsg;

    if($checkpermission < 3) {

        $errmsg = pforbidden();

    } else {

        $obstring = $safePost['pobyava'];

        // Ограничение формы нужно проверять и на сервере, до разбора HTML.
        if(mb_strlen($obstring) > 3000) {
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Объявление слишком большое (больше 3000 символов, включая HTML-разметку).</strong></p>";
            return;
        }

        ensure_html_purifier_loaded();

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'XHTML 1.1');
        $config->set('HTML.TidyLevel', 'heavy'); // all changes, minus...
        $config->set('HTML.TidyRemove', 'br@clear');

        $config->set('HTML.ForbiddenElements', 'h1,h2,h3,h4,h5,h6');

        $purifier = new HTMLPurifier($config);
        $obstring = $purifier->purify($obstring);

        if(!empty($obstring)) {
            $parsedObstring = false;

            // Проверяем тот же HTML, который будет разбираться при выводе.
            $checkObstring = escape_amp_txtarea(normalize_entities_my($obstring));

            // Предупреждения парсера обрабатываем так же, как исключения.
            set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });

            try {
                $html = parseContentHtml($checkObstring);

                if($html !== false) {
                    $html = replaceSemanticSpans($html);
                    $parsedObstring = $html->save();
                }
            } catch(Throwable $e) {
                error_log("pobyava(): ошибка Simple-HTML-DOM: " . $e->getMessage());
            } finally {
                restore_error_handler();
            }

            if($parsedObstring === false) {
                $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Не удалось обработать HTML объявления. Объявление не сохранено.</strong></p>";
                return;
            }
        }

        if(!replaceFileContents("DATABASE/obyava.txt", $obstring)) {
            http_response_code(500);
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Не удалось сохранить объявление. Прежнее объявление доступно на сайте.</strong></p>";
            return;
        }

        mylog("<strong style='color:DarkRed'>Объявление изменено. (".$_SESSION["username"].").</strong>");

        refreshhandle(0, "?", false);
    }
}


function saveUsers() {

    global $safePost, $checkpermission, $errmsg, $csrf;

    if($checkpermission < 4) {
        $errmsg = pforbidden();
        return;
    }

    $payload = $safePost['saveusers'] ?? null;
    $entries = is_string($payload) ? json_decode($payload, true) : null;
    if(!is_array($entries) || !array_is_list($entries)) {
        http_response_code(400);
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Не удалось прочитать список пользователей. Обновите форму и повторите сохранение.</p>";
        return;
    }

    $users = [];
    $hasAdmin = false;
    foreach($entries as $entry) {
        $rawName = $entry['name'] ?? null;
        $level = $entry['level'] ?? null;
        $hash = $entry['hash'] ?? null;
        $name = is_string($rawName)
            ? htmlspecialchars(trim($rawName), ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false)
            : '';

        if($name === 'dummy' || !filterUsername($name) || isset($users[$name])
            || !is_int($level) || $level < 0 || $level > 4
            || !is_string($hash) || preg_match('/\A[a-f0-9]{128}\z/', $hash) !== 1) {
            http_response_code(400);
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'>В списке есть некорректная запись или повторяющееся имя.</p>";
            return;
        }

        $users[$name] = $level . '<!!!>' . $hash;
        $hasAdmin = $hasAdmin || $level === 4;
    }

    if(!$hasAdmin) {
        http_response_code(400);
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>В списке должен остаться хотя бы один администратор.</p>";
        return;
    }

    $php = "<?php\n\ndeclare(strict_types=1);\n\nif(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }\n\n";
    foreach($users as $name => $value) {
        $php .= '$cred[' . var_export((string)$name, true) . '] = ' . var_export($value, true) . ";\n";
    }

    $expected = $safePost['usersversion'] ?? null;
    $lock = lockStateFile('DATABASE/lock/credentials.lock', LOCK_EX);
    if($lock === false) {
        http_response_code(503);
        $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Список пользователей временно недоступен для записи.</p>";
        return;
    }

    $path = dirname(__DIR__) . '/cred.php';
    try {
        $current = @hash_file('sha256', $path);
        if($current === false) {
            http_response_code(503);
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Не удалось прочитать текущий список пользователей.</p>";
            return;
        }
        if(!is_string($expected) || !hash_equals($current, $expected)) {
            http_response_code(409);
            $errmsg = "<h1>КОНФЛИКТ.</h1><p class='big'>Список пользователей изменился. <a href='?editusers=1&amp;csrf=$csrf'>Откройте актуальный список</a> и повторите правку.</p>";
            return;
        }
        if(!replaceFileContents($path, $php)) {
            http_response_code(500);
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'>Не удалось сохранить список пользователей.</p>";
            return;
        }
        if(function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    } finally {
        fclose($lock);
    }

    mylog("<strong style='color:DarkRed'>Cred.php изменён. (".$_SESSION["username"].").</strong>");
    refreshhandle(0, "?editusers=1&csrf=$csrf", false);
}


function userHashCalcp() {

    global $safePost, $mySalt, $checkpermission;

    // require_once "SYSTEM/salt.php";
    // require_once "SYSTEM/cred.php";

    if($checkpermission < 4) {

        http_response_code(403);
        exit();

    } else {

        $username  = $safePost['uhusername'];
        $password1 = $safePost['uhpassword1'];
        $password2 = $safePost['uhpassword2'];

        $content = "<!DOCTYPE html>
        <html lang='ru'>
        <head>
            <meta charset='UTF-8' />
            <meta name='viewport' content='width=device-width, initial-scale=1.0' />
            <title>Калькулятор Юзер-Хэша</title>
            <style>

                .big {
                    font-size: large;
                }

            </style>
        </head>
        
        <body>";
        $errmsg = "";
        $result = "";

        // Validate password strength
        $uppercase    = preg_match('@\p{Lu}@u', $password1);
        $lowercase    = preg_match('@\p{Ll}@u', $password1);
        $number       = preg_match('@[0-9]@', $password1);
        $specialChars = preg_match('@[^\w]@', $password1);

        if(!filterUsername($username)) {
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Имя содержит недопустимые символы или пустое.</strong></p><p class='big'><strong>Минимальная Длина Имени = 3<br />Максимальная Длина Имени = 50</strong></p>";
        } elseif($password1 !== $password2) {
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Пароль и проверка не совпадают!</strong></p>";
        } elseif($password1 === $username) {
            $errmsg = "<h1>ОШИБКА.</h1><p class='big'><strong>Имя и пароль не должны совпадать!</strong></p>";
        } elseif(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password1) < 8 || strlen($password1) > 40) {
            $errmsg = '<h1>ОШИБКА.</h1><p class="big"><strong>Пароль должен содержать 8-40 символов, включая заглавную букву, цифру и специальный символ.</strong></p>';
        } else {

            // Генерация безопасного хэша пароля

            $userhash = hash('sha512', $username."@".$password1."@".generateSalt($username, $password1).$mySalt);

            /// $username = str_replace("&", "&amp;", $username);

            $username = escape_amp_txtarea($username);

            $result .= "<p class='big'><strong>$username</strong></p>
            <p><code style='display: inline-block; width: 100%; padding: .75rem; font-size: 1.4rem; text-wrap: nowrap; overflow-x: scroll;'>$userhash</code></p>";
        }

        $content .= $errmsg.$result."<p><a href='?uhcalc=1' class='big'>🔙 НАЗАД К ФОРМЕ.</a></p></body></html>\n";

        http_response_code(200);
        exit((string)$content);
    }
}
