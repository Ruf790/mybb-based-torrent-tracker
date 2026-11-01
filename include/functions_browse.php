<?php
/**
 * functions_browse.php — фильтры каталога раздач.
 *
 * Один и тот же разбор параметров и один и тот же WHERE для browse.php и
 * xmlhttp.php?action=select_all_filtered («Выбрать всё по фильтру» в
 * модерации) - чтобы выбор по фильтру всегда совпадал с тем, что видно в списке.
 */

declare(strict_types=1);

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/** Допустимые значения */
const BROWSE_SEARCH_TYPES = ['t_name', 't_description', 't_tags', 't_both', 't_uploader', 't_genre'];
const BROWSE_SPECIAL      = ['myreseeds', 'mybookmarks', 'mytorrents', 'weaktorrents'];

/**
 * Нормализация параметров фильтра (из $_GET, при необходимости + $_POST).
 * Все значения уже проверены/приведены - дальше их можно использовать как есть.
 */
function browse_filter_input(array $in): array
{
    $str = static fn(string $k): string => is_string($in[$k] ?? null) ? trim((string)$in[$k]) : '';
    $num = static function (string $k) use ($str): ?int {
        $v = $str($k);
        return ($v !== '' && ctype_digit($v) && (int)$v > 0) ? (int)$v : null;
    };
    $date = static function (string $k, bool $end) use ($str): array {
        $v = $str($k);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return ['', 0];
        }
        $ts = strtotime($v . ($end ? ' 23:59:59' : ' 00:00:00'));
        return $ts ? [$v, (int)$ts] : ['', 0];
    };

    $searchType = $str('search_type');
    $special    = $str('special_search');
    [$fromRaw, $fromTs] = $date('added_from', false);
    [$toRaw, $toTs]     = $date('added_to', true);

    return [
        'category'            => max(0, (int)$str('category')),
        'keywords'            => $str('keywords'),
        'search_type'         => in_array($searchType, BROWSE_SEARCH_TYPES, true) ? $searchType : '',
        'special_search'      => in_array($special, BROWSE_SPECIAL, true) ? $special : '',
        'include_dead'        => ($str('include_dead_torrents') === 'yes'),
        'size_min'            => $num('size_min'),
        'size_max'            => $num('size_max'),
        'min_seeders'         => $num('min_seeders'),
        'health'              => ($str('health') === 'seeded') ? 'seeded' : '',
        'freeleech_only'      => ($str('freeleech_only') === '1'),
        'no_seeders'          => ($str('no_seeders') === '1'),
        'seeders_gt_leechers' => ($str('seeders_gt_leechers') === '1'),
        'hide_downloaded'     => ($str('hide_downloaded') === '1'),
        'imdb_min'            => in_array($str('imdb_min'), ['7', '8', '9'], true) ? (int)$str('imdb_min') : 0,
        'daysprune'           => max(0, (int)$str('daysprune')),
        'added_from_raw'      => $fromRaw,
        'added_from'          => $fromTs,
        'added_to_raw'        => $toRaw,
        'added_to'            => $toTs,
        'smart_browse'        => ($str('smart_browse') !== '0'),
    ];
}

/**
 * WHERE для таблицы torrents (алиас t) по нормализованному фильтру.
 *
 * @return array{join:string, where:string, params:array, links:array}
 *         links - части query string для пагинации (уже urlencode'нутые)
 */
function browse_build_where(array $f, int $uid, bool $isMod): array
{
    global $db;

    $join   = '';
    $where  = ' WHERE' . ($f['include_dead'] ? '' : " t.visible = 'yes' AND") . " t.banned = 'no'";
    $params = [];
    $links  = ['include_dead_torrents=' . ($f['include_dead'] ? 'yes' : 'no')];

    switch ($f['special_search']) {
        case 'myreseeds':
            $where   .= ' AND t.seeders = 0 AND t.leechers > 0 AND t.owner = ?';
            $params[] = $uid;
            break;
        case 'mybookmarks':
            $join     = ' INNER JOIN bookmarks b ON (b.torrentid = t.id)';
            $where   .= ' AND b.userid = ?';
            $params[] = $uid;
            break;
        case 'mytorrents':
            $where   .= ' AND t.owner = ?';
            $params[] = $uid;
            break;
        case 'weaktorrents':
            $where .= " AND (t.visible = 'no' OR t.seeders = 0)";
            break;
    }
    if ($f['special_search'] !== '') {
        $links[] = 'special_search=' . $f['special_search'];
    }

    if ($f['keywords'] !== '') {
        $type    = $f['search_type'] !== '' ? $f['search_type'] : 't_both';
        $like    = '%' . $f['keywords'] . '%';
        $links[] = 'keywords=' . urlencode($f['keywords']);
        $links[] = 'search_type=' . $type;

        switch ($type) {
            case 't_name':
                $where   .= ' AND t.name LIKE ?';
                $params[] = $like;
                break;
            case 't_description':
                $where   .= ' AND t.descr LIKE ?';
                $params[] = $like;
                break;
            case 't_tags':
                $where   .= ' AND t.tags LIKE ?';
                $params[] = $like;
                break;
            case 't_genre':
                $where   .= ' AND t.t_link LIKE ?';
                $params[] = $like;
                break;
            case 't_uploader':
                $uq  = $db->sql_query_prepared('SELECT id FROM users WHERE username = ? LIMIT 1', [$f['keywords']]);
                $row = $uq ? $db->fetch_array($uq) : null;
                $where   .= ' AND t.owner = ?';
                $params[] = $row ? (int)$row['id'] : 0;
                if (!$isMod) {
                    $where .= " AND t.anonymous != 'yes'";
                }
                break;
            default: // t_both
                $where   .= ' AND (t.name LIKE ? OR t.descr LIKE ?)';
                $params[] = $like;
                $params[] = $like;
        }
    }

    if ($f['category']) {
        $ids = [$f['category']];
        $cq  = $db->sql_query_prepared("SELECT id FROM categories WHERE type = 's' AND pid = ?", [$f['category']]);
        while ($cq && ($crow = $db->fetch_array($cq))) {
            $ids[] = (int)$crow['id'];
        }
        $where  .= ' AND t.category IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params  = array_merge($params, $ids);
        $links[] = 'category=' . $f['category'];
    }
    if ($f['size_min']) {
        $where   .= ' AND t.size >= ?';
        $params[] = $f['size_min'];
        $links[]  = 'size_min=' . $f['size_min'];
    }
    if ($f['size_max']) {
        $where   .= ' AND t.size <= ?';
        $params[] = $f['size_max'];
        $links[]  = 'size_max=' . $f['size_max'];
    }
    if ($f['health'] === 'seeded') {
        $where  .= ' AND t.seeders > 0';
        $links[] = 'health=seeded';
    }
    if ($f['freeleech_only']) {
        $where  .= " AND t.free = 'yes'";
        $links[] = 'freeleech_only=1';
    }
    if ($f['no_seeders']) {
        $where  .= ' AND t.seeders = 0';
        $links[] = 'no_seeders=1';
    }
    if ($f['seeders_gt_leechers']) {
        $where  .= ' AND t.seeders > t.leechers';
        $links[] = 'seeders_gt_leechers=1';
    }
    if ($f['hide_downloaded']) {
        $where   .= " AND t.id NOT IN (SELECT torrentid FROM snatched WHERE userid = ? AND finished = 'yes')";
        $params[] = $uid;
        $links[]  = 'hide_downloaded=1';
    }
    if ($f['imdb_min'] > 0) {
        // t_link хранит HTML-блок IMDB целиком; imdb_min - только 7/8/9 (whitelist выше)
        $alts = array_map(static fn(int $d): string => $d . '\\.[0-9]', range($f['imdb_min'], 9));
        $alts[] = '10\\.0';
        $where   .= ' AND t.t_link REGEXP ?';
        $params[] = 'IMDb Rating:</strong>\\s*<span[^>]*>(' . implode('|', $alts) . ')/10';
        $links[]  = 'imdb_min=' . $f['imdb_min'];
    }
    if ($f['min_seeders']) {
        $where   .= ' AND t.seeders >= ?';
        $params[] = $f['min_seeders'];
        $links[]  = 'min_seeders=' . $f['min_seeders'];
    }
    if ($f['daysprune'] > 0) {
        $where   .= ' AND t.added >= ?';
        $params[] = TIMENOW - $f['daysprune'] * 86400;
        $links[]  = 'daysprune=' . $f['daysprune'];
    }
    if ($f['added_from']) {
        $where   .= ' AND t.added >= ?';
        $params[] = $f['added_from'];
        $links[]  = 'added_from=' . $f['added_from_raw'];
    }
    if ($f['added_to']) {
        $where   .= ' AND t.added <= ?';
        $params[] = $f['added_to'];
        $links[]  = 'added_to=' . $f['added_to_raw'];
    }
    if (!$f['smart_browse']) {
        $links[] = 'smart_browse=0';
    }

    return ['join' => $join, 'where' => $where, 'params' => $params, 'links' => $links];
}
