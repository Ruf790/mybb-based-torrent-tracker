<?php

declare(strict_types=1);

/* =============================================================================
   Карточка IMDb на странице раздачи.
   В torrents.t_link лежит готовый HTML из include/imdb_parser.php. Формат хранения
   не меняем (ajax_imdb.php берёт из него ссылку): при выводе разбираем этот HTML
   на поля и строим новую карточку, поэтому она работает и для старых раздач.
   Если разобрать не удалось (ошибка парсера, просто ссылка) — выводим как есть.
   ============================================================================= */

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

/** Содержимое узла как безопасный HTML: только текст, <br>, <strong> и http(s)-ссылки */
function imdb_card_inner(DOMNode $node): string
{
    $out = '';
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $out .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES, 'UTF-8');
        } elseif ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                $out .= '<br>';
            } elseif ($tag === 'i') {
                continue;                     // иконки из старой разметки
            } elseif ($tag === 'strong' || $tag === 'b') {
                $out .= '<strong>' . imdb_card_inner($child) . '</strong>';
            } elseif ($tag === 'a' && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                $out .= '<a href="' . htmlspecialchars($child->getAttribute('href'), ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                      . imdb_card_inner($child) . '</a>';
            } else {
                $out .= imdb_card_inner($child);
            }
        }
    }
    return $out;
}

function imdb_card_text(?DOMNode $node): string
{
    return $node ? trim(preg_replace('/\s+/u', ' ', (string)$node->textContent)) : '';
}

/** Разбор сохранённого HTML. null — это не карточка IMDb */
function imdb_card_parse(string $html): ?array
{
    if (!class_exists('DOMDocument') || !str_contains($html, 'block-titled')) {
        return null;
    }

    $doc = new DOMDocument();
    $ok  = @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    if (!$ok) {
        return null;
    }

    $xp   = new DOMXPath($doc);
    $cls  = static fn(string $c): string => "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')";
    $root = $xp->query('//div[' . $cls('block-titled') . ']')->item(0);
    if (!$root) {
        return null;
    }

    $link = $xp->query('.//*[' . $cls('movie-title') . ']//a', $root)->item(0);
    if (!$link instanceof DOMElement || !preg_match('#^https?://#i', $link->getAttribute('href'))) {
        return null;
    }

    $d = [
        'url' => $link->getAttribute('href'), 'title' => imdb_card_text($link), 'year' => '', 'poster' => '',
        'certs' => [], 'tagline' => '', 'plot' => '', 'synopsis' => '',
        'rating' => '', 'votes' => '', 'genres' => [], 'rows' => [],
    ];

    if (preg_match('/^(.*)\s\((\d{4}[^)]*)\)$/su', $d['title'], $m)) {
        $d['title'] = trim($m[1]);
        $d['year']  = trim($m[2]);
    }

    $img = $xp->query('.//*[' . $cls('movie-poster') . ']//img', $root)->item(0);
    if ($img instanceof DOMElement && preg_match('#^https?://#i', $img->getAttribute('src'))) {
        $d['poster'] = $img->getAttribute('src');
    }

    foreach ($xp->query('.//*[' . $cls('movie-title') . ']/*[' . $cls('badge-extra') . ']', $root) as $b) {
        $t = imdb_card_text($b);
        if ($t !== '' && !in_array($t, $d['certs'], true)) {
            $d['certs'][] = $t;
        }
    }

    foreach ($xp->query('.//p[' . $cls('movie-plot') . ']', $root) as $p) {
        $first = $p->firstChild;
        while ($first instanceof DOMText && trim((string)$first->nodeValue) === '') {
            $first = $first->nextSibling;
        }
        if ($first instanceof DOMElement && strtolower($first->tagName) === 'strong') {
            $p->removeChild($first);
            $d['synopsis'] = trim(imdb_card_inner($p));
        } elseif ($first instanceof DOMElement && strtolower($first->tagName) === 'em') {
            $d['tagline'] = imdb_card_text($p);
        } elseif ($d['plot'] === '') {
            $d['plot'] = trim(imdb_card_inner($p));
        }
    }

    $info = $xp->query('.//div[' . $cls('movie-info') . ']', $root)->item(0);
    if ($info) {
        $section = '';
        foreach ($info->childNodes as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }
            if (str_contains(' ' . $row->getAttribute('class') . ' ', ' section-title ')) {
                $section = imdb_card_text($row);
                continue;
            }
            $strong = $xp->query('./strong', $row)->item(0);
            if (!$strong) {
                continue;
            }
            $label = rtrim(imdb_card_text($strong), ': ');
            $row->removeChild($strong);

            if ($label === 'IMDb Rating') {
                if (preg_match('#([\d.,]+)\s*/\s*10(?:\s*\(([\d.,\s]+)\s*votes?\))?#iu', imdb_card_text($row), $m)) {
                    $d['rating'] = $m[1];
                    $d['votes']  = trim($m[2] ?? '');
                }
                continue;
            }
            if ($label === 'Genres') {
                $d['genres'] = array_values(array_filter(array_map('trim', explode(',', imdb_card_text($row)))));
                continue;
            }

            $value = preg_replace('#^(\s|<br>)+#', '', imdb_card_inner($row));
            if (trim(strip_tags($value)) !== '') {
                $d['rows'][] = ['section' => $section, 'label' => $label, 'value' => trim($value)];
            }
        }
    }

    return $d;
}

/** Новая карточка */
function imdb_card_render(array $d): string
{
    global $lang;

    $e   = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $url = $e($d['url']);

    $main_labels = ['Director(s)', 'Main Cast', 'Countries', 'Languages', 'Runtime', 'Released'];
    $main = $extra = [];
    foreach ($d['rows'] as $r) {
        if (in_array($r['label'], $main_labels, true)) {
            $main[] = $r;
        } else {
            $extra[$r['section']][] = $r;
        }
    }

    $row_html = static fn(array $r): string =>
        '<div class="dt-imdb__fact"><span>' . htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') . '</span><div>' . $r['value'] . '</div></div>';

    // Теги/ключевые слова — не простынёй текста, а чипами: первые 8 видны, остальные по «+N»
    $tags_html = '';
    foreach ($extra as $section => $rows) {
        foreach ($rows as $i => $r) {
            if (!in_array(strtolower($r['label']), ['tags', 'keywords', 'plot keywords'], true)) {
                continue;
            }
            $list  = array_values(array_filter(array_map('trim', explode(',', html_entity_decode(strip_tags($r['value']), ENT_QUOTES, 'UTF-8')))));
            $shown = 8;
            $tags_html .= '<div class="dt-imdb__tags">';
            foreach ($list as $n => $t) {
                $tags_html .= '<span class="dt-imdb__tag' . ($n >= $shown ? ' d-none' : '') . '">' . $e($t) . '</span>';
            }
            if (count($list) > $shown) {
                $tags_html .= '<button type="button" class="dt-imdb__tag dt-imdb__tag--more" '
                            . 'onclick="this.parentNode.querySelectorAll(\'.d-none\').forEach(function(x){x.classList.remove(\'d-none\')});this.remove()">+'
                            . (count($list) - $shown) . '</button>';
            }
            $tags_html .= '</div>';
            unset($extra[$section][$i]);
            if (!$extra[$section]) {
                unset($extra[$section]);
            }
        }
    }

    $chips = '';
    foreach ($d['certs'] as $c) {
        $chips .= '<span class="dt-chip">' . $e($c) . '</span>';
    }
    foreach ($d['genres'] as $g) {
        $chips .= '<span class="dt-chip">' . $e($g) . '</span>';
    }

    // Синопсис часто совпадает с описанием сверху — второй раз не показываем
    $norm = static fn(string $h): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8'))));
    if ($d['plot'] === '' && $d['synopsis'] !== '') {
        $d['plot'] = $d['synopsis'];
    }
    if ($d['synopsis'] !== '' && $norm($d['synopsis']) === $norm($d['plot'])) {
        $d['synopsis'] = '';
    }

    $more = $tags_html !== '' ? '<div class="dt-imdb__sec">' . $tags_html . '</div>' : '';
    if ($d['synopsis'] !== '') {
        $more .= '<div class="dt-imdb__sec"><p class="mb-0">' . $d['synopsis'] . '</p></div>';
    }
    foreach ($extra as $section => $rows) {
        $more .= '<div class="dt-imdb__sec">'
               . ($section !== '' ? '<div class="dt-imdb__sec-title">' . $e((string)$section) . '</div>' : '')
               . '<div class="dt-imdb__facts">' . implode('', array_map($row_html, $rows)) . '</div>'
               . '</div>';
    }

    // Цвет оценки: 7.5+ зелёный, 6–7.5 жёлтый, ниже красный
    $score = (float)str_replace(',', '.', $d['rating']);
    $tone  = $score >= 7.5 ? 'hi' : ($score >= 6 ? 'mid' : 'lo');

    $more_label = htmlspecialchars($lang->details['btn_imdb_more'] ?? 'More details', ENT_QUOTES, 'UTF-8');

    return '<div class="dt-imdb">'
         . ($d['poster'] !== ''
            ? '<a class="dt-imdb__poster" href="' . $url . '" target="_blank" rel="noopener"><img src="' . $e($d['poster']) . '" alt="' . $e($d['title']) . '" loading="lazy" onerror="this.parentNode.remove()"></a>'
            : '')
         . '<div class="dt-imdb__main">'
         .   '<h3 class="dt-imdb__title"><a href="' . $url . '" target="_blank" rel="noopener">' . $e($d['title']) . '</a>'
         .     ($d['year'] !== '' ? ' <span>' . $e($d['year']) . '</span>' : '') . '</h3>'
         .   ($d['tagline'] !== '' ? '<div class="dt-imdb__tagline">' . $e($d['tagline']) . '</div>' : '')
         .   ($d['rating'] !== ''
              ? '<div class="dt-imdb__score"><span class="dt-imdb__num dt-imdb__num--' . $tone . '">' . $e($d['rating']) . '<small>/ 10</small></span>'
                . '<span class="dt-chip dt-chip--imdb">IMDb</span>'
                . ($d['votes'] !== '' ? '<span class="dt-imdb__votes"><i class="fa-solid fa-user-group"></i>' . $e($d['votes']) . '</span>' : '')
                . '</div>'
              : '')
         .   ($chips !== '' ? '<div class="dt-chips">' . $chips . '</div>' : '')
         .   ($d['plot'] !== '' ? '<p class="dt-imdb__plot">' . $d['plot'] . '</p>' : '')
         .   ($main ? '<div class="dt-imdb__facts">' . implode('', array_map($row_html, $main)) . '</div>' : '')
         .   ($more !== ''
              ? '<button type="button" class="btn btn-sm btn-outline-secondary st-pill dt-imdb__toggle" data-bs-toggle="collapse" data-bs-target="#dtImdbMore" aria-expanded="false" aria-controls="dtImdbMore"><i class="fa-solid fa-chevron-down me-1"></i>' . $more_label . '</button>'
              : '')
         . '</div>'
         . '</div>'
         . ($more !== '' ? '<div class="collapse" id="dtImdbMore">' . $more . '</div>' : '');
}

/** ID вида tt1234567 из ссылки IMDb */
function imdb_card_id(string $url): string
{
    return preg_match('#/title/(tt\d{5,10})#i', $url, $m) ? strtolower($m[1]) : '';
}

/**
 * Другие раздачи того же фильма/сериала (тот же IMDb ID в t_link).
 * Пустая строка — других нет.
 */
function imdb_card_others(string $imdbUrl, int $excludeId, int $limit = 10): string
{
    global $db, $lang;

    $tt = imdb_card_id($imdbUrl);
    if ($tt === '') {
        return '';
    }

    $res = $db->sql_query_prepared(
        "SELECT id, name, size, seeders, leechers, added, free, silver, doubleupload
           FROM torrents
          WHERE t_link LIKE ? AND id <> ? AND visible = 'yes' AND banned = 'no'
          ORDER BY seeders DESC, added DESC
          LIMIT " . max(1, $limit),
        ['%/title/' . $tt . '/%', $excludeId]
    );

    $e    = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $rows = '';
    $n    = 0;
    while ($res && ($t = $db->fetch_array($res))) {
        $n++;
        $promo = ($t['free'] ?? '') === 'yes' ? '<span class="dt-imdb__oth-promo is-free">FREE</span>'
               : (($t['silver'] ?? '') === 'yes' ? '<span class="dt-imdb__oth-promo is-silver">50%</span>'
               : (($t['doubleupload'] ?? '') === 'yes' ? '<span class="dt-imdb__oth-promo is-x2">x2</span>' : ''));
        $rows .= '<a class="dt-imdb__oth" href="' . $e(get_torrent_link((int)$t['id'])) . '">'
               .   '<span class="dt-imdb__oth-name">' . $e((string)$t['name']) . $promo . '</span>'
               .   '<span class="dt-imdb__oth-meta">'
               .     '<span><i class="fa-solid fa-hard-drive"></i>' . $e(mksize((float)$t['size'])) . '</span>'
               .     '<span class="is-up"><i class="fa-solid fa-arrow-up"></i>' . (int)$t['seeders'] . '</span>'
               .     '<span class="is-down"><i class="fa-solid fa-arrow-down"></i>' . (int)$t['leechers'] . '</span>'
               .   '</span>'
               . '</a>';
    }
    if ($n === 0) {
        return '';
    }

    $title = (string)($lang->details['lbl_imdb_others'] ?? 'Other releases of this title');
    return '<div class="dt-imdb__others">'
         .   '<div class="dt-imdb__sec-title"><i class="fa-solid fa-layer-group me-2"></i>' . $e($title) . ' <span class="dt-imdb__oth-count">' . $n . '</span></div>'
         .   '<div class="dt-imdb__oth-list">' . $rows . '</div>'
         . '</div>';
}

/** Готовый HTML для вывода: новая карточка или исходный текст */
function imdb_card_html(string $html): string
{
    $d = imdb_card_parse($html);
    return $d !== null ? imdb_card_render($d) : $html;
}
