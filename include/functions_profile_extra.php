<?php

declare(strict_types=1);

/* =============================================================================
   member.php?action=profile — extra blocks (prefix pfx_):
     - group colour for the cover band
     - activity heatmap (uploads, comments, forum posts) for the last 12 months
     - row of 6 latest uploads as posters
     - feed of the latest actions
   Privacy: anonymous uploads and unviewable forums are hidden from other people
   (owner and staff still see them).
   ============================================================================= */

if (!defined('IN_TRACKER')) {
    die("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}

/** Lang string from $lang->member with an English fallback */
function pfx_l(string $key, string $fallback): string
{
    global $lang;
    return (string)($lang->member[$key] ?? $fallback);
}

/** Colour of the user's group, taken from usergroups.namestyle (style="color:...") */
function pfx_group_color(string $namestyle): string
{
    if (preg_match('/color\s*:\s*(#[0-9a-f]{3,8}|rgba?\([0-9.,%\s]+\)|[a-z]{3,20})\b/i', $namestyle, $m)) {
        $c = strtolower(trim($m[1]));
        if (!in_array($c, ['inherit', 'initial', 'unset', 'transparent', 'currentcolor'], true)) {
            return $c;
        }
    }
    return 'var(--bs-primary)';
}

/** Forum ids the viewer can't see, as a list of ints */
function pfx_hidden_fids(): array
{
    static $fids = null;
    if ($fids === null) {
        $fids = function_exists('get_unviewable_forums')
            ? array_values(array_filter(array_map('intval', explode(',', get_unviewable_forums(true)))))
            : [];
    }
    return $fids;
}

/** "AND t.fid NOT IN (1,2)" or "" */
function pfx_fid_sql(string $col): string
{
    $f = pfx_hidden_fids();
    return $f ? ' AND ' . $col . ' NOT IN (' . implode(',', $f) . ')' : '';
}

/** SQL for "torrent is public" (+ not anonymous for other people) */
function pfx_torrent_sql(string $a, bool $show_anon): string
{
    return " AND ($a.visible = 'yes') AND ($a.banned = 'no')"
         . ($show_anon ? '' : " AND $a.anonymous <> 'yes'");
}

/* ─────────────────────────────────────────────────────────────────────────────
   Heatmap
   ───────────────────────────────────────────────────────────────────────────── */

/**
 * Per-day counts since $from (unix, start of a day).
 * @return array<int, array{t:int,c:int,p:int}>  key = day number (floor((ts + tz) / 86400))
 */
function pfx_activity_days(int $uid, int $from, bool $show_anon): array
{
    global $db;

    $tz   = (int)date('Z', $from);
    $days = [];
    $put  = static function ($res, string $k) use (&$days, $db): void {
        while ($res && ($r = $db->fetch_array($res))) {
            $d = (int)$r['d'];
            $days[$d] ??= ['t' => 0, 'c' => 0, 'p' => 0];
            $days[$d][$k] += (int)$r['n'];
        }
    };

    $put($db->sql_query_prepared(
        "SELECT FLOOR((t.added + ?) / 86400) AS d, COUNT(*) AS n
           FROM torrents t
          WHERE t.owner = ? AND t.added >= ?" . pfx_torrent_sql('t', $show_anon) . "
          GROUP BY d",
        [$tz, $uid, $from]
    ), 't');

    $put($db->sql_query_prepared(
        "SELECT FLOOR((c.dateline + ?) / 86400) AS d, COUNT(*) AS n
           FROM comments c
          WHERE c.user = ? AND c.dateline >= ?
          GROUP BY d",
        [$tz, $uid, $from]
    ), 'c');

    $put($db->sql_query_prepared(
        "SELECT FLOOR((p.dateline + ?) / 86400) AS d, COUNT(*) AS n
           FROM posts p
          WHERE p.uid = ? AND p.dateline >= ? AND p.visible = 1" . pfx_fid_sql('p.fid') . "
          GROUP BY d",
        [$tz, $uid, $from]
    ), 'p');

    return $days;
}

/** GitHub-style 53×7 grid for the last 12 months */
function pfx_heatmap_html(int $uid, bool $show_anon): string
{
    $tz     = (int)date('Z');
    $today  = intdiv(TIMENOW + $tz, 86400);              // local day number
    $dow    = (int)date('N', TIMENOW);                    // 1 = Mon … 7 = Sun
    $start  = $today - ($dow - 1) - 52 * 7;               // Monday, 52 weeks back
    $fromTs = $start * 86400 - $tz;

    $days = pfx_activity_days($uid, $fromTs, $show_anon);

    $max = 0;
    $sum = ['t' => 0, 'c' => 0, 'p' => 0];
    $active = 0;
    foreach ($days as $d => $v) {
        if ($d < $start || $d > $today) {
            continue;
        }
        $n = $v['t'] + $v['c'] + $v['p'];
        $max = max($max, $n);
        $active++;
        foreach ($sum as $k => $_) {
            $sum[$k] += $v[$k];
        }
    }
    $total = $sum['t'] + $sum['c'] + $sum['p'];

    // Longest streak and current streak
    $best = $run = 0;
    for ($d = $start; $d <= $today; $d++) {
        $run  = isset($days[$d]) ? $run + 1 : 0;
        $best = max($best, $run);
    }

    $months = array_map('trim', explode(',', pfx_l('hm_months', 'Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec')));
    if (count($months) !== 12) {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }
    $wdays = array_map('trim', explode(',', pfx_l('hm_wdays', 'Mon,,Wed,,Fri,,')));

    // Column 0: weekday labels; then one column per week: month label + 7 days
    $cells = '<span></span>';
    for ($i = 0; $i < 7; $i++) {
        $cells .= '<span class="pfx-hm__wd">' . htmlspecialchars($wdays[$i] ?? '') . '</span>';
    }
    $prevM = -1;
    $weeks  = intdiv($today - $start, 7) + 1;
    for ($w = 0; $w < $weeks; $w++) {
        $m = (int)gmdate('n', ($start + $w * 7) * 86400) - 1;
        $ml = '';
        if ($m !== $prevM) {
            // No label for a month cut off at the left edge or starting in the last 2 weeks
            if (($w > 0 || (int)gmdate('j', $start * 86400) <= 7) && $w < $weeks - 2) {
                $ml = htmlspecialchars($months[$m]);
            }
            $prevM = $m;
        }
        $cells .= '<span class="pfx-hm__m">' . $ml . '</span>';
        for ($i = 0; $i < 7; $i++) {
            $d = $start + $w * 7 + $i;
            if ($d > $today) {
                $cells .= '<i class="is-void"></i>';
                continue;
            }
            $v   = $days[$d] ?? ['t' => 0, 'c' => 0, 'p' => 0];
            $n   = $v['t'] + $v['c'] + $v['p'];
            $lvl = $n === 0 ? 0 : max(1, min(4, (int)ceil(4 * $n / max($max, 1))));
            $tip = my_datee('d M Y', $d * 86400 - $tz + 43200) . ' — ';
            $tip .= $n === 0
                ? pfx_l('hm_tip_none', 'no activity')
                : implode(', ', array_filter([
                    $v['t'] ? ags_fmt(pfx_l('hm_tip_t', 'uploads: {1}'), $v['t']) : '',
                    $v['c'] ? ags_fmt(pfx_l('hm_tip_c', 'comments: {1}'), $v['c']) : '',
                    $v['p'] ? ags_fmt(pfx_l('hm_tip_p', 'posts: {1}'), $v['p']) : '',
                ]));
            $cells .= '<i class="l' . $lvl . ($d === $today ? ' is-today' : '') . '" title="' . htmlspecialchars($tip, ENT_QUOTES) . '"></i>';
        }
    }

    $legend = '';
    for ($l = 0; $l <= 4; $l++) {
        $legend .= '<i class="l' . $l . '"></i>';
    }

    $stat = static fn(string $ico, string $tone, int $n, string $lbl): string =>
        '<span class="pfx-hm__s"><i class="fa-solid ' . $ico . ' text-' . $tone . '"></i><b>' . ts_nf($n) . '</b> ' . htmlspecialchars($lbl) . '</span>';

    return '<section class="st-card mb-3 pfx-hm">
  <div class="st-card__head"><i class="fa-solid fa-chart-simple"></i>' . htmlspecialchars(pfx_l('sec_activity_map', 'Activity')) . '
    <span class="st-badge st-soft-primary ms-auto">' . htmlspecialchars(ags_fmt(pfx_l('hm_total', '{1} actions in a year'), ts_nf($total))) . '</span></div>
  <div class="st-card__body">
    <div class="pfx-hm__scroll">
      <div class="pfx-hm__grid" style="--w:' . $weeks . '">' . $cells . '</div>
    </div>
    <div class="pfx-hm__foot">
      ' . $stat('fa-cloud-arrow-up', 'success', $sum['t'], pfx_l('hm_s_t', 'uploads')) . '
      ' . $stat('fa-comment', 'primary', $sum['c'], pfx_l('hm_s_c', 'comments')) . '
      ' . $stat('fa-message', 'warning', $sum['p'], pfx_l('hm_s_p', 'posts')) . '
      ' . $stat('fa-fire', 'danger', $best, pfx_l('hm_s_streak', 'days best streak')) . '
      <span class="pfx-hm__legend ms-auto">' . htmlspecialchars(pfx_l('hm_less', 'Less')) . $legend . htmlspecialchars(pfx_l('hm_more', 'More')) . '</span>
    </div>
  </div>
</section>';
}

/* ─────────────────────────────────────────────────────────────────────────────
   Posters
   ───────────────────────────────────────────────────────────────────────────── */

function pfx_posters_html(int $uid, bool $show_anon, int $limit = 6): string
{
    global $db, $BASEURL;

    $res = $db->sql_query_prepared(
        "SELECT t.id, t.name, t.t_image, t.seeders, t.leechers, t.size, t.anonymous
           FROM torrents t
          WHERE t.owner = ? AND t.t_image <> ''" . pfx_torrent_sql('t', $show_anon) . "
          ORDER BY t.added DESC
          LIMIT " . max(1, $limit),
        [$uid]
    );
    $items = '';
    while ($res && ($r = $db->fetch_array($res))) {
        $name = (string)$r['name'];
        $link = $BASEURL . '/' . get_torrent_link((int)$r['id']);
        $seed = (int)$r['seeders'];
        $items .= '<a class="pfx-poster" href="' . $link . '" title="' . htmlspecialchars($name, ENT_QUOTES) . '">'
            . '<span class="pfx-poster__img"><img src="' . htmlspecialchars((string)$r['t_image'], ENT_QUOTES) . '" alt="" loading="lazy" onerror="this.remove()">'
            . '<i class="fa-solid fa-film pfx-poster__ph"></i>'
            . ($r['anonymous'] === 'yes' ? '<span class="pfx-poster__anon" title="' . htmlspecialchars(pfx_l('lbl_anon_upload', 'Anonymous upload'), ENT_QUOTES) . '"><i class="fa-solid fa-user-secret"></i></span>' : '')
            . '<span class="pfx-poster__seed ' . ($seed > 0 ? 'is-on' : 'is-off') . '"><i class="fa-solid fa-arrow-up"></i>' . ts_nf($seed) . '</span>'
            . '</span>'
            . '<span class="pfx-poster__name">' . htmlspecialchars_uni($name) . '</span>'
            . '<span class="pfx-poster__meta">' . mksize((float)$r['size']) . '</span>'
            . '</a>';
    }
    if ($items === '') {
        return '';
    }

    return '<section class="st-card mb-3">
  <div class="st-card__head"><i class="fa-solid fa-photo-film"></i>' . htmlspecialchars(pfx_l('sec_latest_posters', 'Latest uploads'))
        . '<a class="ms-auto small fw-semibold text-decoration-none pfx-more" href="#pf-torrents" data-pf-tab="#pf-torrents">' . htmlspecialchars(pfx_l('lbl_all_uploads', 'All uploads')) . ' <i class="fa-solid fa-angle-right"></i></a></div>
  <div class="st-card__body"><div class="pfx-posters">' . $items . '</div></div>
</section>';
}

/* ─────────────────────────────────────────────────────────────────────────────
   Feed
   ───────────────────────────────────────────────────────────────────────────── */

function pfx_ago(int $ts): string
{
    global $dateformat;
    $s = max(0, TIMENOW - $ts);
    return match (true) {
        $s < 60     => pfx_l('ago_now', 'just now'),
        $s < 3600   => ags_fmt(pfx_l('ago_min', '{1} min ago'), intdiv($s, 60)),
        $s < 86400  => ags_fmt(pfx_l('ago_hour', '{1} h ago'), intdiv($s, 3600)),
        $s < 172800 => pfx_l('ago_yesterday', 'yesterday'),
        $s < 2592000 => ags_fmt(pfx_l('ago_day', '{1} d ago'), intdiv($s, 86400)),
        default     => my_datee($dateformat, $ts),
    };
}

function pfx_feed_html(int $uid, bool $show_anon, int $limit = 8): string
{
    global $db, $BASEURL, $dateformat, $timeformat;

    $e     = static fn(string $s): string => htmlspecialchars_uni($s);
    $items = [];
    $lim   = ' LIMIT ' . max(1, $limit);

    // Uploads
    $res = $db->sql_query_prepared(
        "SELECT t.id, t.name, t.added FROM torrents t
          WHERE t.owner = ?" . pfx_torrent_sql('t', $show_anon) . " ORDER BY t.added DESC" . $lim,
        [$uid]
    );
    while ($res && ($r = $db->fetch_array($res))) {
        $items[] = [(int)$r['added'], 'fa-cloud-arrow-up', 'success', pfx_l('feed_upload', 'Uploaded a torrent'),
            $BASEURL . '/' . get_torrent_link((int)$r['id']), (string)$r['name']];
    }

    // Comments (the torrent may be anonymous — the name is public anyway, the uploader isn't shown)
    $res = $db->sql_query_prepared(
        "SELECT c.id, c.torrent, c.dateline, t.name FROM comments c
           JOIN torrents t ON t.id = c.torrent
          WHERE c.user = ? AND t.visible = 'yes' AND t.banned = 'no'
          ORDER BY c.dateline DESC" . $lim,
        [$uid]
    );
    while ($res && ($r = $db->fetch_array($res))) {
        $items[] = [(int)$r['dateline'], 'fa-comment', 'primary', pfx_l('feed_comment', 'Commented on'),
            $BASEURL . '/' . get_comment_link((int)$r['id'], (int)$r['torrent']) . '#pid' . (int)$r['id'], (string)$r['name']];
    }

    // New threads
    $res = $db->sql_query_prepared(
        "SELECT t.tid, t.subject, t.dateline FROM threads t
          WHERE t.uid = ? AND t.visible = 1" . pfx_fid_sql('t.fid') . "
          ORDER BY t.dateline DESC" . $lim,
        [$uid]
    );
    while ($res && ($r = $db->fetch_array($res))) {
        $items[] = [(int)$r['dateline'], 'fa-square-plus', 'warning', pfx_l('feed_thread', 'Started a thread'),
            $BASEURL . '/' . get_thread_link((int)$r['tid']), (string)$r['subject']];
    }

    // Replies (first posts are already "started a thread")
    $res = $db->sql_query_prepared(
        "SELECT p.pid, p.tid, p.dateline, t.subject FROM posts p
           JOIN threads t ON t.tid = p.tid
          WHERE p.uid = ? AND p.visible = 1 AND t.visible = 1 AND p.pid <> t.firstpost" . pfx_fid_sql('p.fid') . "
          ORDER BY p.dateline DESC" . $lim,
        [$uid]
    );
    while ($res && ($r = $db->fetch_array($res))) {
        $items[] = [(int)$r['dateline'], 'fa-reply', 'info', pfx_l('feed_post', 'Replied in'),
            $BASEURL . '/' . get_post_link((int)$r['pid'], (int)$r['tid']) . '#pid' . (int)$r['pid'], (string)$r['subject']];
    }

    usort($items, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
    $items = array_slice($items, 0, $limit);

    if (!$items) {
        return '<div class="st-empty"><div class="st-ico st-soft-muted"><i class="fa-solid fa-wind"></i></div>'
             . htmlspecialchars(pfx_l('feed_empty', 'Nothing here yet')) . '</div>';
    }

    $html = '';
    foreach ($items as [$ts, $ico, $tone, $verb, $url, $title]) {
        $html .= '<div class="pfx-feed__i">'
            . '<span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $ico . '"></i></span>'
            . '<div class="min-w-0 flex-grow-1"><span class="pfx-feed__verb">' . $e($verb) . '</span> '
            . '<a href="' . $url . '" class="pfx-feed__a">' . $e($title) . '</a></div>'
            . '<time class="pfx-feed__t" datetime="' . date('c', $ts) . '" title="' . $e(my_datee($dateformat, $ts) . ' ' . my_datee($timeformat, $ts)) . '">' . $e(pfx_ago($ts)) . '</time>'
            . '</div>';
    }
    return '<div class="pfx-feed">' . $html . '</div>';
}
