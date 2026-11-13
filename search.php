<?php
    // Настройки поиска.
    $searchMinLength = 3;
    $searchMaxLength = 200;
    $perPage = 15;
    $context = 300; // Запас контекста с каждой стороны делится между фрагментами.
    $snippetMaxFragments = 4;
    $searchRequestLimit = 15;
    $searchRequestWindow = 60;

    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $searchValue = is_string($_GET['search'] ?? null) ? $_GET['search'] : '';
    $search = trim($searchValue);
    $validUtf8 = mb_check_encoding($searchValue, 'UTF-8');
    $searchTooLong = $validUtf8 && mb_strlen($searchValue, 'UTF-8') > $searchMaxLength;
    $segments = [];
    $searchQuotesError = false;
    if($validUtf8 && !$searchTooLong) {
        // Вне кавычек — отдельные слова, внутри парных кавычек — цельная фраза.
        preg_match_all(
            '~"(?<phrase>[^"]*)"|«(?<angle>[^»]*)»|“(?<smart>[^”]*)”|(?<word>[^\s\p{Z}"«»“”]+)|(?<unclosed>["«»“”])~u',
            $search,
            $tokens,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
        );
        foreach($tokens as $token) {
            if(($token['unclosed'] ?? null) !== null) {
                $searchQuotesError = true;
                break;
            }
            $segment = $token['phrase'] ?? $token['angle'] ?? $token['smart'] ?? $token['word'];
            $segment = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $segment) ?? '');
            if($segment !== '') $segments[] = $segment;
        }
    }
    $searchCanRun = !$searchTooLong && !$searchQuotesError && $validUtf8 && $segments !== []
        && mb_strlen(implode(' ', $segments), 'UTF-8') >= $searchMinLength
        && (!isset($_GET['pgnum']) || is_string($_GET['pgnum']));

    $view = $searchTooLong ? 'too-long' : ($searchQuotesError ? 'quotes-error' : 'start');
    $results = [];
    $pgcount = $pageCount = $pgnum = $searchRetryAfter = 0;

    if($searchCanRun) {
        require_once __DIR__ . '/SYSTEM/modules/search-rate-limit.php';
        $searchClientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if(!is_string($searchClientIp) || filter_var($searchClientIp, FILTER_VALIDATE_IP) === false) {
            $searchClientIp = '0.0.0.0';
        }

        try {
            $searchRetryAfter = \Froggy\Search\consumeRateLimit(
                __DIR__ . '/DATABASE/lock/search-rate-limit.json',
                $searchClientIp,
                $searchRequestLimit,
                $searchRequestWindow
            );
            if($searchRetryAfter > 0) {
                $view = 'limited';
                http_response_code(429);
            } else {
                // Одни правила совпадений для отбора статей, фрагментов и подсветки.
                usort($segments, static fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
                $expressions = array_values(array_unique(array_map(
                    static fn($segment) => strtr(preg_quote($segment, '~'), [
                        'е' => '[её]', 'ё' => '[её]', 'Е' => '[её]', 'Ё' => '[её]',
                    ]),
                    $segments
                )));
                $patterns = array_map(static fn($expression) => '~'.$expression.'~iu', $expressions);
                $pattern = '~(' . implode('|', $expressions) . ')~iu';

                $plainText = static function(string $html): string {
                    $html = str_replace("<br!>", " ", $html);
                    $html = preg_replace('~<[^>]*>~', ' ', $html) ?? $html;
                    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? '');
                };

                $parseArticle = static function(string $line) use ($plainText): ?array {
                    $parts = explode("<!2!>", $line, 2);
                    if(count($parts) !== 2 || !mb_check_encoding($line, 'UTF-8')
                        || preg_match('~\A([a-f0-9]{40})<head([1-6])>(.*?)</head\2>~i', $parts[0], $header) !== 1) {
                        return null;
                    }
                    return [$plainText($header[3]), $plainText($parts[1]), $header[1]];
                };

                $highlight = static function(string $text) use ($pattern, $escape): string {
                    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $html = '';
                    foreach($parts ?: [$text] as $index => $part) {
                        $part = $escape($part);
                        $html .= $index % 2 ? '<mark>'.$part.'</mark>' : $part;
                    }
                    return $html;
                };

                $renderSnippet = static function(string $body) use ($patterns, $context, $snippetMaxFragments, $highlight): string {
                    $matches = [];
                    $minContext = max(1, intdiv($context, $snippetMaxFragments));
                    foreach($patterns as $segmentPattern) {
                        if(preg_match($segmentPattern, $body, $match, PREG_OFFSET_CAPTURE) !== 1) continue;
                        // PCRE возвращает байты; для обрезки переводим позицию в символы.
                        $start = mb_strlen(substr($body, 0, $match[0][1]), 'UTF-8');
                        $end = $start + mb_strlen($match[0][0], 'UTF-8');
                        foreach($matches as [$left, $right]) {
                            if($start >= $left - $minContext && $end <= $right + $minContext) continue 2;
                        }
                        $matches[] = [$start, $end];
                        if(count($matches) >= $snippetMaxFragments) break;
                    }

                    // Если совпадения есть только в заголовке, показываем начало статьи.
                    $bodyLength = mb_strlen($body, 'UTF-8');
                    if($matches === []) $matches[] = [0, min($bodyLength, $context)];
                    $radius = max(1, intdiv($context, count($matches)));
                    $ranges = [];
                    foreach($matches as [$start, $end]) {
                        $start = max(0, $start - $radius);
                        $end = min($bodyLength, $end + $radius);
                        // До 32 символов на целое слово; длинные строки без пробелов остаются ограниченными.
                        preg_match('/\S*\z/u', mb_substr($body, max(0, $start - 32), min(32, $start), 'UTF-8'), $edge);
                        $start -= mb_strlen($edge[0], 'UTF-8');
                        preg_match('/\A\S*/u', mb_substr($body, $end, 32, 'UTF-8'), $edge);
                        $end += mb_strlen($edge[0], 'UTF-8');
                        $ranges[] = [$start, $end];
                    }
                    usort($ranges, static fn($a, $b) => $a[0] <=> $b[0]);

                    // Соседние совпадения показываем одним непрерывным фрагментом.
                    $merged = [];
                    foreach($ranges as $range) {
                        $last = count($merged) - 1;
                        if($last >= 0 && $range[0] <= $merged[$last][1]) {
                            $merged[$last][1] = max($merged[$last][1], $range[1]);
                        } else {
                            $merged[] = $range;
                        }
                    }

                    $fragments = [];
                    foreach($merged as [$start, $end]) {
                        // Подсвечиваем только исходный текст: многоточия не участвуют в поиске.
                        $fragments[] = $highlight(trim(mb_substr($body, $start, $end - $start, 'UTF-8')));
                    }

                    return ($merged[0][0] > 0 ? '…' : '') . implode(' … ', $fragments)
                        . ($merged[count($merged) - 1][1] < $bodyLength ? '…' : '');
                };

                $requestedPage = abs((int)($_GET['pgnum'] ?? '0'));
                $file = new SplFileObject(__DIR__ . '/DATABASE/DB/data.html', 'rb');
                $file->setFlags(SplFileObject::READ_AHEAD);
                foreach($file as $line) {
                    $article = $parseArticle($line);
                    if($article === null) continue;
                    [$title, $body, $articleId] = $article;
                    // Фраза не должна склеиваться из конца заголовка и начала текста.
                    $text = $title . "\n" . $body;
                    // Если хотя бы один сегмент отсутствует, переходим к следующей статье.
                    foreach($patterns as $segmentPattern) {
                        if(preg_match($segmentPattern, $text) !== 1) continue 2;
                    }

                    // В памяти тексты одной страницы; выдержки строятся только при выводе.
                    // При слишком большом pgnum останется последняя страница.
                    $resultPage = intdiv($pgcount, $perPage);
                    if($resultPage <= $requestedPage) {
                        if($resultPage !== $pgnum) {
                            $results = [];
                            $pgnum = $resultPage;
                        }
                        $results[] = ['title' => $title, 'body' => $body, 'id' => $articleId];
                    }
                    $pgcount++;
                }
                unset($file);
                $pageCount = $pgcount > 0 ? intdiv($pgcount - 1, $perPage) + 1 : 0;
                $view = $pgcount > 0 ? 'results' : 'empty';
            }
        } catch(RuntimeException $exception) {
            $view = 'unavailable';
            $searchRetryAfter = 60;
            http_response_code(503);
            error_log('Search: ' . $exception->getMessage());
        }
    }

    if($searchRetryAfter > 0) {
        header('Retry-After: ' . $searchRetryAfter);
        header('Cache-Control: no-store');
    }

    $notices = [
        'start' => ['⌕', 'Найдите нужную главу', 'Введите слово или несколько слов — от '.$searchMinLength.' до '.$searchMaxLength.' символов. Фразу целиком заключите в кавычки.'],
        'too-long' => ['!', 'Слишком длинный запрос', 'Введите не более '.$searchMaxLength.' символов, включая пробелы.'],
        'quotes-error' => ['!', 'Проверьте кавычки', 'Закройте кавычки вокруг каждой фразы.'],
        'limited' => ['!', 'Слишком частые запросы', 'Повторите поиск через '.$searchRetryAfter.' сек.'],
        'unavailable' => ['!', 'Поиск временно недоступен', 'Попробуйте ещё раз немного позже.'],
        'empty' => ['∅', 'Ничего не нашлось', 'Попробуйте меньше слов или другую формулировку. Все слова и фразы запроса должны встречаться в одной статье.'],
    ];
    $pageBase = 'search.php?search=' . rawurlencode($search) . '&pgnum=';
    $start = $pgnum * $perPage;
    $end = min($start + $perPage, $pgcount);
    $cssVersion = @filemtime(__DIR__ . '/TEMPLATES/search.css') ?: 0;
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark" />
    <title>Поиск по сайту</title>
    <link rel="icon" href="favicon.ico" />
    <link rel="stylesheet" href="TEMPLATES/search.css?<?= $cssVersion ?>" />
</head>
<body>
<a class="skip-link" href="#search-results">К результатам поиска</a>
<header class="site-header">
    <a class="brand" href="./" aria-label="Froggy-BLC — главная">
        <span class="brand-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 6.5C9 4.5 5.5 4.5 3 5.5v13c2.5-1 6-1 9 1 3-2 6.5-2 9-1v-13c-2.5-1-6-1-9 1Zm0 0v13" /></svg>
        </span>
        <span>Froggy<span class="brand-suffix">—BLC</span></span>
    </a>
    <a class="home-link" href="./">На главную <svg class="arrow-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 19 19 5M5 5h14v14" /></svg></a>
</header>
<main class="search-layout">
    <section class="search-hero" aria-labelledby="search-title">
        <p class="eyebrow"><span aria-hidden="true"></span> Навигация по страницам</p>
        <h1 id="search-title">Поиск по <em>сайту.</em></h1>
        <p class="hero-description">Нужная глава ближе, чем кажется. Начните с нескольких слов.</p>
    </section>

    <form class="search-form" action="search.php" method="GET" role="search">
        <input type="hidden" name="pgnum" value="0" />
        <label class="visually-hidden" for="search-query">Поисковый запрос</label>
        <div class="search-field">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></svg>
            <input type="search" id="search-query" name="search"
                   value="<?= $escape($searchValue) ?>"
                   placeholder="Какие страницы вы ищете?" minlength="<?= $searchMinLength ?>" maxlength="<?= $searchMaxLength ?>" required
                   aria-describedby="search-hint" />
            <button type="submit">Найти <svg class="arrow-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12h16m-6-6 6 6-6 6" /></svg></button>
        </div>
        <p class="search-hint" id="search-hint"><span>От <?= $searchMinLength ?> до <?= $searchMaxLength ?> символов</span><span>Слова — в любом порядке ··· В кавычках — точная фраза</span></p>
    </form>

    <section class="search-results" id="search-results" aria-label="Результаты поиска" tabindex="-1">
        <?php if($view === 'results'): ?>
            <div class="results-toolbar">
                <h2>Найдено страниц <span class="results-count"><?= $pgcount ?></span></h2>
                <p class="results-range"><?= $start + 1 ?>–<?= $end ?> из <?= $pgcount ?></p>
            </div>
            <?php foreach($results as $index => $result): ?>
                <article class="search-result">
                    <span class="result-number" aria-hidden="true"><?= str_pad((string)($start + $index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="result-content">
                        <h3><a href="./?<?= $result['id'] ?>&amp;permalink=1"><span><?= $highlight($result['title']) ?></span> <svg class="arrow-icon result-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 19 19 5M5 5h14v14" /></svg></a></h3>
                        <p><?= $renderSnippet($result['body']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if($pageCount > 1): ?>
                <nav class="pagination" aria-label="Страницы результатов">
                    <?php if($pgnum > 0): ?>
                        <a class="page-link page-prev" href="<?= $escape($pageBase . ($pgnum - 1)) ?>" rel="prev"><svg class="arrow-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 12H4m6-6-6 6 6 6" /></svg> Назад</a>
                    <?php else: ?>
                        <span class="pagination-spacer" aria-hidden="true"></span>
                    <?php endif; ?>
                    <p class="page-position">Страница <strong><?= $pgnum + 1 ?></strong> из <?= $pageCount ?></p>
                    <?php if($end < $pgcount): ?>
                        <a class="page-link page-next" href="<?= $escape($pageBase . ($pgnum + 1)) ?>" rel="next">Далее <svg class="arrow-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12h16m-6-6 6 6-6 6" /></svg></a>
                    <?php else: ?>
                        <span class="pagination-spacer" aria-hidden="true"></span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <?php [$symbol, $title, $text] = $notices[$view]; ?>
            <div class="empty-state"<?= in_array($view, ['too-long', 'quotes-error', 'limited', 'unavailable'], true) ? ' role="alert"' : '' ?>>
                <span class="empty-symbol" aria-hidden="true"><?= $escape($symbol) ?></span>
                <h2><?= $escape($title) ?></h2>
                <p><?= $escape($text) ?></p>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="site-footer">
    <span>Froggy-BLC</span>
    <span>Книжная система сайта</span>
</footer>
</body>
</html>

