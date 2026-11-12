<?php
    $searchValue = is_string($_GET['search'] ?? null) ? $_GET['search'] : '';
    $searchMaxLength = 200;
    $searchTooLong = mb_check_encoding($searchValue, 'UTF-8')
        && mb_strlen($searchValue, 'UTF-8') > $searchMaxLength;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark" />
    <title>Поиск по сайту</title>
    <link rel="icon" href="favicon.ico" />
    <link rel="stylesheet" href="TEMPLATES/search.css" />
</head>
<body>
<a class="skip-link" href="#search-results">К результатам поиска</a>
<header class="site-header">
    <a class="brand" href="index.php" aria-label="Froggy-BLC — главная">
        <span class="brand-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"><path d="M12 6.5C9 4.5 5.5 4.5 3 5.5v13c2.5-1 6-1 9 1 3-2 6.5-2 9-1v-13c-2.5-1-6-1-9 1Zm0 0v13" /></svg>
        </span>
        <span>Froggy<span class="brand-suffix">—BLC</span></span>
    </a>
    <a class="home-link" href="index.php">На главную <span aria-hidden="true">↗</span></a>
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
                   value="<?= htmlspecialchars($searchValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                   placeholder="Какие страницы вы ищете?" minlength="3" maxlength="<?= $searchMaxLength ?>" required
                   aria-describedby="search-hint" />
            <button type="submit">Найти <span aria-hidden="true">→</span></button>
        </div>
        <p class="search-hint" id="search-hint"><span>От 3 до <?= $searchMaxLength ?> символов</span><span>Все слова из запроса · В любом порядке</span></p>
    </form>

    <section class="search-results" id="search-results" aria-label="Результаты поиска" tabindex="-1">

<?php

    if($searchTooLong) {
        echo '<div class="empty-state" role="alert">';
        echo '<span class="empty-symbol" aria-hidden="true">!</span>';
        echo '<h2>Слишком длинный запрос</h2>';
        echo '<p>Введите не более '.$searchMaxLength.' символов, включая пробелы.</p></div>';
    } elseif(isset($_GET['search']) && is_string($_GET['search'])
        && mb_check_encoding($_GET['search'], 'UTF-8')
        && mb_strlen(trim($_GET['search']), 'UTF-8') >= 3
        && (!isset($_GET['pgnum']) || is_string($_GET['pgnum']))) {

        $pgnum = abs((int)($_GET['pgnum'] ?? '0'));

        $search = trim($_GET['search']);

        // Все сегменты обязательны, но их порядок и расстояние не важны.
        $segments = preg_split('/[\s\p{Z}]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $segments = array_values(array_unique($segments));

        // При пересечении сегментов сначала подсвечиваем более длинный.
        usort($segments, static fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        $pattern = '~(' . implode('|', array_map(
            static fn($segment) => preg_quote($segment, '~'),
            $segments
        )) . ')~iu';

        $plainText = static function(string $html): string {
            $html = str_replace("<br!>", " ", $html);
            $html = preg_replace('~<[^>]*>~', ' ', $html) ?? $html;
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $text) ?? '');
        };

        $parseArticle = static function(string $line) use ($plainText): ?array {
            $parts = explode("<!2!><br!>", $line, 2);
            if(count($parts) !== 2 || !mb_check_encoding($line, 'UTF-8')
                || preg_match('~\A[a-f0-9]{40}<head([1-6])>(.*?)</head\1>~i', $parts[0], $header) !== 1) {
                return null;
            }

            return [$plainText($header[2]), $plainText($parts[1])];
        };

        $highlight = static function(string $text) use ($pattern): string {
            $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $html = '';

            foreach($parts ?: [$text] as $index => $part) {
                $part = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= $index % 2 ? '<mark>'.$part.'</mark>' : $part;
            }

            return $html;
        };

        $pages = [];
        $file = new SplFileObject(__DIR__ . "/DATABASE/DB/data.html", "rb");
        $file->setFlags(SplFileObject::READ_AHEAD);

        foreach($file as $lineNumber => $line) {
            $article = $parseArticle($line);
            if($article === null) {
                continue;
            }

            // Слова могут находиться и в заголовке, и в тексте одной статьи.
            $text = $article[0] . ' ' . $article[1];
            $matches = $segments !== [];

            foreach($segments as $segment) {
                if(mb_stripos($text, $segment, 0, 'UTF-8') === false) {
                    $matches = false;
                    break;
                }
            }

            if($matches) {
                $pages[] = $lineNumber + 1;
            }
        }







        $pgcount = count($pages);

        $perPage = 8;
        $pageCount = (int)ceil($pgcount / $perPage);
        $pgnum = min($pgnum, max(0, $pageCount - 1));

        $start = $pgnum * $perPage;
        $end = min($start + $perPage, $pgcount);

        if($pgcount > 0) {
            echo '<div class="results-toolbar">';
            echo '<h2>Найдено страниц <span class="results-count">'.$pgcount.'</span></h2>';
            echo '<p class="results-range">'.($start + 1).'–'.$end.' из '.$pgcount.'</p>';
            echo '</div>';
        } else {
            echo '<div class="empty-state"><span class="empty-symbol" aria-hidden="true">∅</span>';
            echo '<h2>Ничего не нашлось</h2>';
            echo '<p>Попробуйте меньше слов или другую формулировку. В статье должны встречаться все слова запроса.</p></div>';
        }

        for($i = $start; $i < $end; $i++) {

            $file->seek($pages[$i] - 1);

            $line = $file->current();

            $article = $parseArticle($line);
            if($article === null) {
                continue;
            }
            [$title, $body] = $article;
            $head = $highlight($title);

            $context = 255;
            $pos = false;
            $matchLength = 0;

            // Фрагмент строится вокруг первого найденного сегмента в тексте.
            foreach($segments as $segment) {
                $segmentPos = mb_stripos($body, $segment, 0, 'UTF-8');
                if($segmentPos !== false && ($pos === false || $segmentPos < $pos)) {
                    $pos = $segmentPos;
                    $matchLength = mb_strlen($segment, 'UTF-8');
                }
            }

            $fragmentStart = $pos === false ? 0 : max(0, $pos - $context);
            $length = $pos === false
                ? $context * 2
                : ($pos - $fragmentStart) + $matchLength + $context;

            $fragment = mb_substr($body, $fragmentStart, $length, 'UTF-8');

            $fragment = ($fragmentStart > 0 ? '…' : '')
                . $fragment
                . ($fragmentStart + $length < mb_strlen($body, 'UTF-8') ? '…' : '');

            $body = $highlight($fragment);






            echo '<article class="search-result">';
            echo '<span class="result-number" aria-hidden="true">'.str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT).'</span>';
            echo '<div class="result-content"><h3><a href="index.php?nredir='.$pages[$i].'"><span>'.$head.'</span> <span class="result-arrow" aria-hidden="true">↗</span></a></h3>';
            echo '<p>'.$body.'</p></div></article>';
        }





        $url = 'search.php?search=' . rawurlencode($search) . '&amp;pgnum=';

        if($pageCount > 1) {
            echo '<nav class="pagination" aria-label="Страницы результатов">';

            if($pgnum > 0) {
                echo '<a class="page-link page-prev" href="'.$url.($pgnum - 1).'" rel="prev"><span aria-hidden="true">←</span> Назад</a>';
            } else {
                echo '<span class="pagination-spacer" aria-hidden="true"></span>';
            }

            echo '<p class="page-position">Страница <strong>'.($pgnum + 1).'</strong> из '.$pageCount.'</p>';

            if($end < $pgcount) {
                echo '<a class="page-link page-next" href="'.$url.($pgnum + 1).'" rel="next">Далее <span aria-hidden="true">→</span></a>';
            } else {
                echo '<span class="pagination-spacer" aria-hidden="true"></span>';
            }

            echo '</nav>';
        }
    } else {
        echo '<div class="empty-state">';
        echo '<span class="empty-symbol" aria-hidden="true">⌕</span>';
        echo '<h2>Найдите нужную главу</h2>';
        echo '<p>Введите слово или несколько слов — от 3 до '.$searchMaxLength.' символов. Здесь появятся страницы и короткие фрагменты с совпадениями.</p></div>';
    }
?>
    </section>
</main>
<footer class="site-footer">
    <span>Froggy-BLC</span>
    <span>Книжная система сайта</span>
</footer>
</body>
</html>
