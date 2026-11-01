<?php
declare(strict_types=1);

/*
 * IMDb/TMDB-карточка торрента.
 *
 * Вход:  $t_link — ссылка вида https://www.imdb.com/title/ttXXXXXXX/
 * Выход: $t_link — готовый HTML карточки (сохраняется в torrents.t_link).
 *
 * Важно для совместимости:
 *  - первая ссылка в HTML — <a href='IMDB_URL'> в одинарных кавычках:
 *    ajax_imdb.php (кнопка «Обновить») достаёт URL регуляркой @<a href='(.*)'@U;
 *  - рейтинг лежит в data-rating='7.8/10' на корневом элементе:
 *    member.php ищет его регуляркой (\d+\.?\d*)/10.
 *  - стилей внутри HTML нет: всё в include/templates/default/style/imdb_card.css,
 *    поэтому дизайн можно менять без пересохранения карточек в базе.
 */

// ── Извлечение IMDb ID ────────────────────────────────────────────────────────
if (!preg_match('#https?://www\.imdb\.com/title/(tt\d+)/#i', $t_link, $_id_)) {
    exit('Invalid IMDb URL');
}
$imdbId = $_id_[1];

include_once INC_PATH . '/IMDB.php';

// ── Хелперы ───────────────────────────────────────────────────────────────────

if (!function_exists('im_h')) {
    function im_h(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('im_str')) {
    /** Первое непустое строковое поле из массива */
    function im_str(array $data, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && (string)$data[$key] !== '') {
                return trim((string)$data[$key]);
            }
        }
        return '';
    }
}

if (!function_exists('im_list')) {
    /** Массив строк (без пустых) */
    function im_list(array $data, string $key): array
    {
        $v = $data[$key] ?? [];
        if (!is_array($v)) {
            $v = $v === '' || $v === null ? [] : [$v];
        }
        return array_values(array_filter(array_map(static fn($x) => trim((string)$x), $v), static fn($x) => $x !== ''));
    }
}

if (!function_exists('im_fact')) {
    /** Ячейка в сетке фактов */
    function im_fact(string $icon, string $label, string $valueHtml): string
    {
        if ($valueHtml === '') {
            return '';
        }
        return "<div class='im-fact'><div class='im-fact__label'><i class='fa-solid {$icon}'></i>" . im_h($label)
             . "</div><div class='im-fact__value'>{$valueHtml}</div></div>";
    }
}

if (!function_exists('im_people')) {
    /** Список людей со ссылками на TMDB */
    function im_people(array $people): string
    {
        $out = [];
        foreach ($people as $p) {
            $name = trim((string)($p['name'] ?? ''));
            if ($name === '') continue;
            $out[$name] = !empty($p['id'])
                ? "<a href='https://www.themoviedb.org/person/" . (int)$p['id'] . "' target='_blank' rel='noopener'>" . im_h($name) . '</a>'
                : im_h($name);
        }
        return implode(', ', $out);
    }
}

if (!function_exists('im_date')) {
    /** 2024-03-01 → 1 Mar 2024 */
    function im_date(string $raw): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            $ts = gmmktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
            return gmdate('j M Y', $ts);
        }
        return $raw;
    }
}

// ── Основная логика ───────────────────────────────────────────────────────────
try {
    $data = (new IMDB($t_link))->parse();

    $IMDBUrl   = "https://www.imdb.com/title/{$imdbId}/";
    $TMDBUrl   = im_str($data, 'tmdb_url');
    $isTv      = ($data['type'] ?? '') === 'tv';

    $Title     = im_str($data, 'title');
    $OrigTitle = im_str($data, 'original_title');
    if (mb_strtolower($OrigTitle) === mb_strtolower($Title)) {
        $OrigTitle = '';
    }
    $Year      = im_str($data, 'year');
    $Released  = im_date(im_str($data, 'release_date'));
    $Runtime   = im_str($data, 'runtime');
    $Rated     = im_str($data, 'content_rating');
    $Tagline   = im_str($data, 'tagline');
    $Plot      = im_str($data, 'plot');
    $Synopsis  = im_str($data['storyline'] ?? [], 'synopsis');
    if ($Synopsis === $Plot) {
        $Synopsis = '';
    }

    $Genres    = im_list($data, 'genres');
    $Countries = im_list($data, 'countries');
    $Languages = im_list($data, 'languages');
    $Studios   = im_list($data, 'production_companies');
    $Tags      = im_list($data, 'tags');
    $Aka       = im_list($data, 'also_known_as');
    $Official  = im_str($data, 'official_site');
    $FilmLoc   = im_str($data, 'filming_locations');

    $Rating    = im_str($data, 'rating');
    $Votes     = (int)($data['rating_count'] ?? 0);
    $RatingPct = $Rating !== '' ? max(0, min(100, (int)round((float)$Rating * 10))) : 0;
    $RatingCls = $RatingPct >= 70 ? 'is-good' : ($RatingPct >= 50 ? 'is-mid' : 'is-bad');

    $Directors = im_people($data['credits']['directors'] ?? []);
    $Writers   = im_people($data['credits']['writers'] ?? []);

    $box       = $data['box_office'] ?? [];
    $Budget    = im_str($box, 'budget');
    $Gross     = im_str($box, 'gross_worldwide');
    $GrossUS   = im_str($box, 'gross_us');
    $Opening   = im_str($box, 'opening_weekend');

    $tech      = $data['technical_specs'] ?? [];
    $Color     = im_str($tech, 'Color', 'color');
    $SoundMix  = im_str($tech, 'Sound Mix', 'Sound mix', 'sound_mix');
    $Aspect    = im_str($tech, 'Aspect Ratio', 'Aspect ratio', 'aspect_ratio');

    $Awards = '';
    if (is_array($data['awards'] ?? null)) {
        $Awards = sprintf('%d wins, %d nominations',
            (int)($data['awards']['total_wins'] ?? 0), (int)($data['awards']['total_nominations'] ?? 0));
    } else {
        $Awards = im_str($data, 'awards');
    }

    $Trailer   = im_str($data['media'] ?? [], 'trailer_url');
    $Poster    = im_str($data, 'poster');
    $Backdrop  = im_str($data, 'backdrop');

    // ── Шапка ─────────────────────────────────────────────────────────────────
    $posterHtml = $Poster !== ''
        ? "<img src='" . im_h($Poster) . "' alt='" . im_h($Title) . "' loading='lazy'>"
        : "<span class='im-poster__empty'><i class='fa-solid fa-film'></i></span>";

    $chips = '';
    if ($Rated !== '')    $chips .= "<span class='im-chip im-chip--rated'>" . im_h($Rated) . '</span>';
    $chips .= "<span class='im-chip'><i class='fa-solid " . ($isTv ? 'fa-tv' : 'fa-clapperboard') . "'></i>" . ($isTv ? 'TV Series' : 'Movie') . '</span>';
    if ($Runtime !== '')  $chips .= "<span class='im-chip'><i class='fa-regular fa-clock'></i>" . im_h($Runtime) . ($isTv ? ' / ep.' : '') . '</span>';
    if ($Released !== '') $chips .= "<span class='im-chip'><i class='fa-regular fa-calendar'></i>" . im_h($Released) . '</span>';

    $genresHtml = '';
    foreach ($Genres as $g) {
        $genresHtml .= "<span class='im-genre'>" . im_h($g) . '</span>';
    }

    $scoreHtml = '';
    if ($Rating !== '') {
        $scoreHtml = "<div class='im-score'>"
            . "<div class='im-ring {$RatingCls}' style='--p:{$RatingPct}'><span>" . im_h($Rating) . '</span></div>'
            . "<div class='im-score__txt'><b>User score</b><small>TMDB"
            . ($Votes > 0 ? ' · ' . number_format($Votes) . ' votes' : '') . '</small></div></div>';
    }

    $actions = "<a class='im-btn im-btn--imdb' href='" . im_h($IMDBUrl) . "' target='_blank' rel='noopener'><i class='fa-brands fa-imdb'></i>IMDb</a>";
    if ($TMDBUrl !== '') {
        $actions .= "<a class='im-btn' href='" . im_h($TMDBUrl) . "' target='_blank' rel='noopener'><i class='fa-solid fa-database'></i>TMDB</a>";
    }
    if ($Trailer !== '') {
        $actions .= "<a class='im-btn im-btn--trailer' href='" . im_h($Trailer) . "' target='_blank' rel='noopener'><i class='fa-brands fa-youtube'></i>Trailer</a>";
    }

    // ── Факты ─────────────────────────────────────────────────────────────────
    $facts = im_fact('fa-video', 'Director', $Directors)
           . im_fact('fa-pen-nib', 'Writers', $Writers)
           . im_fact('fa-earth-europe', 'Country', im_h(implode(', ', $Countries)))
           . im_fact('fa-language', 'Language', im_h(implode(', ', $Languages)))
           . im_fact('fa-building', 'Studio', im_h(implode(', ', array_slice($Studios, 0, 4))))
           . im_fact('fa-sack-dollar', 'Budget', im_h($Budget))
           . im_fact('fa-ticket', 'Box office', im_h($Gross));

    // ── Актёры ────────────────────────────────────────────────────────────────
    $castHtml = '';
    $cast = is_array($data['cast'] ?? null) ? $data['cast'] : [];
    foreach (array_slice($cast, 0, 12) as $a) {
        $name = trim((string)($a['actor'] ?? ''));
        if ($name === '') continue;
        $role  = trim((string)($a['character'] ?? ''));
        $photo = !empty($a['photo'])
            ? "<img src='" . im_h((string)$a['photo']) . "' alt='" . im_h($name) . "' loading='lazy'>"
            : "<span class='im-person__empty'><i class='fa-solid fa-user'></i></span>";
        $open  = !empty($a['url']) ? "<a class='im-person' href='" . im_h((string)$a['url']) . "' target='_blank' rel='noopener'>" : "<div class='im-person'>";
        $close = !empty($a['url']) ? '</a>' : '</div>';
        $castHtml .= $open . "<span class='im-person__photo'>{$photo}</span>"
                   . "<span class='im-person__name'>" . im_h($name) . '</span>'
                   . ($role !== '' ? "<span class='im-person__role'>" . im_h($role) . '</span>' : '')
                   . $close;
    }
    if ($castHtml !== '') {
        $more = count($cast) > 12 ? " <small>+" . (count($cast) - 12) . '</small>' : '';
        $castHtml = "<div class='im-sec'><i class='fa-solid fa-masks-theater'></i>Cast{$more}</div><div class='im-cast'>{$castHtml}</div>";
    }

    // ── Подробнее (свернуто) ──────────────────────────────────────────────────
    $tagsHtml = '';
    foreach (array_slice($Tags, 0, 20) as $tg) {
        $tagsHtml .= "<span class='im-tag'>#" . im_h($tg) . '</span>';
    }

    $moreInner = ($Synopsis !== '' ? "<p class='im-plot'>" . nl2br(im_h($Synopsis)) . '</p>' : '')
        . "<div class='im-facts'>"
        . im_fact('fa-building', 'All studios', count($Studios) > 4 ? im_h(implode(', ', $Studios)) : '')
        . im_fact('fa-flag-usa', 'Gross US', im_h($GrossUS))
        . im_fact('fa-chart-line', 'Opening weekend', im_h($Opening))
        . im_fact('fa-trophy', 'Awards', im_h($Awards))
        . im_fact('fa-location-dot', 'Filming locations', im_h($FilmLoc))
        . im_fact('fa-signature', 'Also known as', im_h(implode(' / ', $Aka)))
        . im_fact('fa-palette', 'Color', im_h($Color))
        . im_fact('fa-volume-high', 'Sound mix', im_h($SoundMix))
        . im_fact('fa-expand', 'Aspect ratio', im_h($Aspect))
        . im_fact('fa-globe', 'Official site', $Official !== ''
            ? "<a href='" . im_h($Official) . "' target='_blank' rel='noopener'>" . im_h(preg_replace('#^https?://(www\.)?#i', '', rtrim($Official, '/'))) . '</a>'
            : '')
        . '</div>'
        . ($tagsHtml !== '' ? "<div class='im-tags'>{$tagsHtml}</div>" : '');

    $hasMore = strip_tags(str_replace("<div class='im-facts'></div>", '', $moreInner)) !== '';
    $moreHtml = $hasMore
        ? "<details class='im-more'><summary><i class='fa-solid fa-chevron-down'></i>More details</summary>{$moreInner}</details>"
        : '';

    // ── Сборка ────────────────────────────────────────────────────────────────
    $heroBg = ($Backdrop !== '' ? $Backdrop : $Poster);
    $heroStyle = $heroBg !== '' ? " style=\"--im-bg:url('" . im_h($heroBg) . "')\"" : '';

    $t_link = "<div class='im-card'" . ($Rating !== '' ? " data-rating='" . im_h($Rating) . "/10'" : '') . ">"
        . "<div class='im-hero'{$heroStyle}>"
        .   "<a href='" . im_h($IMDBUrl) . "' class='im-poster' target='_blank' rel='noopener' title='" . im_h($Title) . "'>{$posterHtml}</a>"
        .   "<div class='im-head'>"
        .     "<h3 class='im-title'>" . im_h($Title) . ($Year !== '' ? " <span class='im-year'>(" . im_h($Year) . ')</span>' : '') . '</h3>'
        .     ($OrigTitle !== '' ? "<div class='im-orig'>" . im_h($OrigTitle) . '</div>' : '')
        .     "<div class='im-chips'>{$chips}</div>"
        .     ($genresHtml !== '' ? "<div class='im-genres'>{$genresHtml}</div>" : '')
        .     "<div class='im-row'>{$scoreHtml}<div class='im-actions'>{$actions}</div></div>"
        .     ($Tagline !== '' ? "<p class='im-tagline'>" . im_h($Tagline) . '</p>' : '')
        .   '</div>'
        . '</div>'
        . "<div class='im-body'>"
        .   ($Plot !== '' ? "<div class='im-sec'><i class='fa-solid fa-align-left'></i>Overview</div><p class='im-plot'>" . nl2br(im_h($Plot)) . '</p>' : '')
        .   ($facts !== '' ? "<div class='im-facts'>{$facts}</div>" : '')
        .   $castHtml
        .   $moreHtml
        . '</div>'
        . '</div>';

} catch (Throwable $e) {
    // Ссылка на IMDb остаётся первой, чтобы кнопка «Обновить» могла повторить запрос
    $t_link = "<div class='alert alert-danger'><a href='https://www.imdb.com/title/{$imdbId}/' target='_blank' rel='noopener'>IMDb {$imdbId}</a>: "
            . 'error fetching data: ' . im_h($e->getMessage()) . '</div>';
    error_log('IMDb Parser Error: ' . $e->getMessage());
}
