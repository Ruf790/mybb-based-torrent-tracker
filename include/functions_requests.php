<?php
declare(strict_types=1);

/**
 * Запросы: автосвязка с раздачами и закрытие запроса.
 *
 * req_find_matches()  открытые запросы, которые может закрыть торрент (IMDb ID или название)
 * req_fill()          закрыть запрос раздачей: награда, ЛС автору, колокольчик голосовавшим
 * req_imdb_id()       tt-номер из ссылки или строки
 *
 * Используется в requests.php (action=fill) и details.php (блок «Эта раздача может закрыть запросы»).
 */

if (!defined('IN_TRACKER') && !defined('IN_CRON') && !defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

/** Порог совпадения по названию (0–100), ниже — не показываем */
const REQ_MATCH_MIN_SCORE = 70;

/** tt1234567 из ссылки/строки или null */
function req_imdb_id(string $s): ?string
{
    return preg_match('/\b(tt\d{6,10})\b/i', $s, $m) ? strtolower($m[1]) : null;
}

/** Нормализация названия: регистр, ё→е, только буквы/цифры, без ведущего артикля */
function req_norm(string $s): string
{
    $s = mb_strtolower(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
    $s = str_replace(['ё', '&'], ['е', ' and '], $s);
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    $s = preg_replace('/^(the|a|an) /u', '', $s) ?? $s;
    return $s;
}

/**
 * Название и год из имени релиза.
 *   «The.Odyssey.2026.2160p.WEB-DL»           → ['odyssey', 2026]
 *   «Blade Runner 2049 (2017) BDRip»          → ['blade runner 2049', 2017]
 *   «Одиссея / The Odyssey (2026) WEB-DL»     → варианты для обоих названий
 *
 * @return array{titles: string[], year: ?int}
 */
function req_parse_release(string $name): array
{
    $maxYear = (int)gmdate('Y') + 1;
    $year    = null;

    // Год релиза — последний «годный» 19xx/20xx (2049 в «Blade Runner 2049» отсеется)
    if (preg_match_all('/(?<![\p{L}\p{N}])((?:19|20)\d{2})(?![\p{L}\p{N}])/u', $name, $mm, PREG_OFFSET_CAPTURE)) {
        foreach (array_reverse($mm[1]) as [$y, $off]) {
            if ((int)$y >= 1900 && (int)$y <= $maxYear) {
                $year = (int)$y;
                $name = substr($name, 0, (int)$off); // всё после года — техническая часть
                break;
            }
        }
    }

    // Обрезать по первому техническому тегу
    $name = preg_replace(
        '/(?<![\p{L}\p{N}])(2160p|1080[pi]|720p|576p|480p|4k|uhd|hdr|bdrip|brrip|bluray|blu ray|web[ .-]?dl|webrip|web|hdtv|hdrip|dvdrip|dvd|remux|x264|x265|h ?264|h ?265|hevc|avc|s\d{1,2}(e\d{1,3})?|season \d+|сезон \d+)(?![\p{L}\p{N}]).*$/iu',
        '',
        str_replace(['.', '_'], ' ', $name)
    ) ?? $name;

    // Убрать скобки (часто «[Rip by X]», «(Director's Cut)»)
    $name = preg_replace('/\[[^\]]*\]|\([^)]*\)/u', ' ', $name) ?? $name;

    $titles = [];
    foreach (preg_split('/\s[\/|]\s|\s\/|\/\s/u', $name) ?: [] as $part) {
        $n = req_norm($part);
        if ($n !== '') $titles[$n] = $n;
    }

    return ['titles' => array_values($titles), 'year' => $year];
}

/** Название и год из сохранённой IMDb-карточки (новой и старой разметки) */
function req_parse_imdb_card(string $html): array
{
    $titles = [];
    $year   = null;
    // новая карточка: <h3 class='im-title'>Title <span class='im-year'>(2026)</span>
    if (preg_match("#<h3 class='im-title'>(.*?)(?:<span class='im-year'>\((\d{4})\)</span>)?</h3>#su", $html, $m)) {
        $titles[] = req_norm(strip_tags($m[1]));
        if (!empty($m[2])) $year = (int)$m[2];
    // старая карточка: <h3 class='movie-title'> <a ...>Title (2026)</a>
    } elseif (preg_match("#<h3 class='movie-title'>\s*<a[^>]*>(.*?)\s*\((\d{4})\)</a>#su", $html, $m)) {
        $titles[] = req_norm(strip_tags($m[1]));
        $year = (int)$m[2];
    }
    if (preg_match("#<div class='im-orig'>(.*?)</div>#su", $html, $m)) {
        $titles[] = req_norm(strip_tags($m[1]));
    }
    return ['titles' => array_values(array_unique(array_filter($titles))), 'year' => $year];
}

/** Похожесть двух нормализованных названий, 0–100 */
function req_title_score(string $a, string $b): int
{
    if ($a === '' || $b === '') return 0;
    if ($a === $b) return 95;

    // Одно целиком входит в другое по словам («odyssey» ⊂ «odyssey extended cut»)
    [$short, $long] = mb_strlen($a) <= mb_strlen($b) ? [$a, $b] : [$b, $a];
    if (mb_strlen($short) >= 5 && str_contains(" {$long} ", " {$short} ")) {
        $words = substr_count($short, ' ') + 1;
        return $words >= 2 ? 85 : 75;
    }

    if (mb_strlen($short) < 4) return 0;
    similar_text($a, $b, $pct);
    return (int)round($pct * 0.9);
}

/**
 * Открытые запросы, которые может закрыть этот торрент.
 *
 * @param array $torrent строка torrents (нужны id, name, category, t_link)
 * @return array<int, array> запросы + match_score, match_by ('imdb'|'title')
 */
function req_find_matches(array $torrent, int $limit = 5): array
{
    global $db;

    $tImdb  = req_imdb_id((string)($torrent['t_link'] ?? ''));
    $rel    = req_parse_release((string)($torrent['name'] ?? ''));
    $card   = req_parse_imdb_card((string)($torrent['t_link'] ?? ''));
    $tTitles = array_values(array_unique(array_merge($rel['titles'], $card['titles'])));
    $tYear   = $card['year'] ?? $rel['year'];
    $tCat    = (int)($torrent['category'] ?? 0);

    if (!$tImdb && !$tTitles) return [];

    $q = $db->sql_query_prepared(
        "SELECT r.id, r.user_id, r.title, r.category_id, r.year, r.imdb_id, r.votes, r.bounty, r.created_at,
                c.name AS category_name
         FROM requests r
         LEFT JOIN categories c ON c.id = r.category_id
         WHERE r.status = 'open'
         ORDER BY r.id DESC
         LIMIT 2000"
    );

    $out = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $score = 0;
        $by    = 'title';

        if ($tImdb && !empty($r['imdb_id']) && strtolower((string)$r['imdb_id']) === $tImdb) {
            $score = 100;
            $by    = 'imdb';
        } else {
            // У запроса есть свой IMDb, и он другой — это точно не тот фильм
            if ($tImdb && !empty($r['imdb_id'])) continue;

            $rel2 = req_parse_release((string)$r['title']);
            foreach ($rel2['titles'] as $rt) {
                foreach ($tTitles as $tt) {
                    $score = max($score, req_title_score($rt, $tt));
                }
            }
            if ($score === 0) continue;

            $rYear = !empty($r['year']) ? (int)$r['year'] : $rel2['year'];
            if ($rYear && $tYear) {
                if (abs($rYear - $tYear) > 1) continue; // ремейк / другой фильм
                $score += $rYear === $tYear ? 5 : 0;
            }
            $rCat = (int)$r['category_id'];
            if ($rCat > 0 && $tCat > 0) {
                $score += $rCat === $tCat ? 5 : -10;
            }
            $score = min(99, $score); // 100 — только совпадение по IMDb
        }

        if ($score >= REQ_MATCH_MIN_SCORE) {
            $r['match_score'] = $score;
            $r['match_by']    = $by;
            $out[] = $r;
        }
    }

    usort($out, static fn($a, $b) => [$b['match_score'], (float)$b['bounty'], (int)$b['votes']]
                                  <=> [$a['match_score'], (float)$a['bounty'], (int)$a['votes']]);
    return array_slice($out, 0, $limit);
}

/**
 * Закрыть запрос раздачей.
 * Закрыть может владелец торрента или стафф; награда идёт владельцу торрента.
 *
 * @return array{ok: bool, error?: string, args?: array}  error — ключ из requests.lang.php
 */
function req_fill(int $rid, int $torrentId, int $actorUid, bool $actorIsMod): array
{
    global $db, $BASEURL;

    require_once INC_PATH . '/functions_bonuslog.php';

    $req = $db->fetch_array($db->sql_query_prepared("SELECT * FROM requests WHERE id = ?", [$rid]));
    if (!$req || $req['status'] !== 'open') {
        return ['ok' => false, 'error' => 'err_invalid_request'];
    }

    $tq = $torrentId > 0 ? $db->sql_query_prepared("SELECT id, owner FROM torrents WHERE id = ?", [$torrentId]) : null;
    $t  = $tq ? $db->fetch_array($tq) : null;
    if (!$t) {
        return ['ok' => false, 'error' => 'flash_torrent_missing', 'args' => [$torrentId]];
    }

    $owner = (int)$t['owner'];
    if ($owner !== $actorUid && !$actorIsMod) {
        return ['ok' => false, 'error' => 'err_fill_not_owner'];
    }
    $filler = $owner > 0 ? $owner : $actorUid;

    // Атомарно: двойной клик / два человека одновременно не получат награду дважды
    $db->sql_query_prepared(
        "UPDATE requests SET status = 'filled', filled_by = ?, torrent_id = ?, filled_at = ?, updated_at = ?
         WHERE id = ? AND status = 'open'",
        [$filler, $torrentId, TIMENOW, TIMENOW, $rid]
    );
    if ($db->affected_rows() < 1) {
        return ['ok' => false, 'error' => 'err_invalid_request'];
    }

    if ((float)$req['bounty'] > 0) {
        $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?", [$req['bounty'], $filler]);
        bonus_log($filler, (float)$req['bounty'], 'request', "Bounty for filling request #{$rid}", $rid,
            $filler !== $actorUid ? $actorUid : null);
    }

    if ((int)$req['user_id'] !== $filler) {
        $requester = $db->fetch_array($db->sql_query_prepared("SELECT id, language FROM users WHERE id = ?", [(int)$req['user_id']]));
        if ($requester) {
            require_once INC_PATH . '/functions_pm.php';
            // Скобки в названии ломают [b]...[/b] / [url=...]...[/url]
            $request_title = str_replace(['[', ']'], ['(', ')'], (string)$req['title']);
            send_pm([
                'subject'       => ['pm_filled_subject'],
                'message'       => ['pm_filled_message', $request_title, $BASEURL . '/details.php?id=' . $torrentId],
                'touid'         => (int)$requester['id'],
                'language'      => (string)($requester['language'] ?? ''),
                'language_file' => 'requests',
                'sender'        => ['uid' => -1],
            ], -1, true);
        }
    }

    // Колокольчик: автор запроса + все голосовавшие (notify() пропускает actor == recipient)
    try {
        require_once INC_PATH . '/functions_notify.php';
        $nLink  = 'details.php?id=' . $torrentId;
        $nTitle = [(string)$req['title']];
        notify((int)$req['user_id'], 'request_filled', $filler, $rid, $nTitle, $nLink);

        $vq = $db->sql_query_prepared(
            'SELECT DISTINCT user_id FROM request_votes WHERE request_id = ? AND user_id <> ?',
            [$rid, (int)$req['user_id']]
        );
        while ($vq && ($v = $db->fetch_array($vq))) {
            notify((int)$v['user_id'], 'request_vote_filled', $filler, $rid, $nTitle, $nLink);
        }
    } catch (\Throwable $e) {
        error_log('notify (request filled): ' . $e->getMessage());
    }

    return ['ok' => true];
}
