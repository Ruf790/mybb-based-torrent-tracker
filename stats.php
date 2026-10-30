<?php
declare(strict_types=1);

define('IN_MYBB',    1);
define('THIS_SCRIPT','stats.php');
define('IN_FORUM',   true);

require_once 'global.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/class_parser.php';

$parser = new postParser;
$lang->load('stats');
add_breadcrumb($lang->stats['nav_stats']);



$stats = $cache->read('stats') ?? [];
if (($stats['numthreads'] ?? 0) < 1 || ($stats['numusers'] ?? 0) < 1) {
    stderr($lang->stats['err_not_enough_info']);
}

$plugins->run_hooks('stats_start');

$numposts   = (int)($stats['numposts']   ?? 0);
$numthreads = (int)($stats['numthreads'] ?? 0);
$numusers   = (int)($stats['numusers']   ?? 0);

$repliesperthread = ts_nf(round(($numposts - $numthreads) / max(1, $numthreads), 2));
$postspermember   = ts_nf(round($numposts   / max(1, $numusers), 2));
$threadspermember = ts_nf(round($numthreads / max(1, $numusers), 2));

$query  = $db->sql_query_prepared("SELECT added FROM users ORDER BY added ASC LIMIT 1");
$result = $db->fetch_array($query);
$days   = max((TIMENOW - (int)($result['added'] ?? TIMENOW)) / 86400, 1);

$postsperday   = ts_nf(round($numposts   / $days, 2));
$threadsperday = ts_nf(round($numthreads / $days, 2));
$membersperday = ts_nf(round($numusers   / $days, 2));

$unviewableforums = get_unviewable_forums(true);
$inactiveforums   = get_inactive_forums();

// get_unviewable_forums() может вернуть "'3','5'" — нормализуем в int[]
$toFids = static fn(string $csv): array => $csv === ''
    ? []
    : array_values(array_filter(array_map(static fn($v) => (int)trim($v, " '\""), explode(',', $csv))));

$unviewablefids        = $toFids((string)$unviewableforums);
$inactivefids          = $toFids((string)$inactiveforums);
$unviewableforumsarray = array_merge($unviewablefids, $inactivefids);

$fidnot = '';
if ($unviewableforums) $fidnot .= " AND fid NOT IN ($unviewableforums)";
if ($inactiveforums)   $fidnot .= " AND fid NOT IN ($inactiveforums)";

$group_permissions = forum_permissions();
$onlyusfids = [];
foreach ($group_permissions as $gpfid => $fp) {
    if ((int)($fp['canonlyviewownthreads'] ?? 0) === 1) {
        $onlyusfids[] = (int)$gpfid;
    }
}

// Форумы "только свои темы" тоже скрываем из топов и из популярного форума
$hiddenfids = array_merge($unviewableforumsarray, $onlyusfids);
if ($onlyusfids) {
    $fidnot .= ' AND fid NOT IN (' . implode(',', $onlyusfids) . ')';
}

function buildThreadRow(array $thread, string $number_type, int $rank, int $max): string
{
    global $parser, $lang;
    $subject    = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
    $threadlink = htmlspecialchars(get_thread_link($thread['tid']), ENT_QUOTES, 'UTF-8');
    $count      = (int)($thread[$number_type] ?? 0);
    $width      = $max > 0 ? round($count / $max * 100, 1) : 0;
    $icon       = $number_type === 'views' ? 'fa-eye' : 'fa-comments';
    $rankClass  = $rank <= 3 ? ' ag-rank-' . $rank : '';
    $countTip   = htmlspecialchars(
        sprintf($lang->stats[$number_type === 'views' ? 'tip_count_views' : 'tip_count_replies'], ts_nf($count)),
        ENT_QUOTES, 'UTF-8'
    );

    return '<li class="ag-rank-row">'
        . '<span class="ag-rank' . $rankClass . '">' . $rank . '</span>'
        . '<div class="ag-rank-main">'
            . '<a href="' . $threadlink . '" class="ag-rank-title" title="' . $subject . '">' . $subject . '</a>'
            . '<div class="ag-rank-bar" aria-hidden="true"><span style="--w:' . $width . '%"></span></div>'
        . '</div>'
        . '<span class="ag-rank-count" title="' . $countTip . '">'
            . '<i class="fa-solid ' . $icon . '"></i>' . ts_nf($count)
        . '</span>'
        . '</li>';
}

function buildThreadList(array $threads, string $number_type, array $hiddenfids): string
{
    $visible = array_values(array_filter(
        $threads,
        static fn($t) => is_array($t) && !in_array((int)($t['fid'] ?? 0), $hiddenfids, true)
    ));
    if (!$visible) return '';

    $max = max(array_map(static fn($t) => (int)($t[$number_type] ?? 0), $visible));
    $out = '';
    foreach ($visible as $i => $thread) {
        $out .= buildThreadRow($thread, $number_type, $i + 1, $max);
    }
    return '<ol class="ag-rank-list">' . $out . '</ol>';
}

$most_replied = $cache->read('most_replied_threads') ?? [];
if (empty($most_replied)) {
    $cache->update_most_replied_threads();
    $most_replied = $cache->read('most_replied_threads') ?? [];
}
$mostreplies = buildThreadList((array)$most_replied, 'replies', $hiddenfids);

$most_viewed = $cache->read('most_viewed_threads') ?? [];
if (empty($most_viewed)) {
    $cache->update_most_viewed_threads();
    $most_viewed = $cache->read('most_viewed_threads') ?? [];
}
$mostviews = buildThreadList((array)$most_viewed, 'views', $hiddenfids);

$statistics     = $cache->read('statistics') ?? [];
$statscachetime = (int)($mybb->settings['statscachetime'] ?? 24);
$interval       = max($statscachetime, 0) * 3600;

if (empty($statistics) || $interval === 0 || (TIMENOW - $interval) > ($statistics['time'] ?? 0)) {
    $cache->update_statistics();
    $statistics = $cache->read('statistics') ?? [];
}

$query = $db->sql_query_prepared(
    "SELECT fid, name, threads, posts FROM forums WHERE type = 'f' {$fidnot} ORDER BY posts DESC LIMIT 1"
);
$forum = $db->fetch_array($query);

if (!$forum) {
    $topforum = htmlspecialchars_uni($lang->stats['lbl_none']); $topforumposts = '0'; $topforumthreads = '0';
} else {
    $forum['name']   = htmlspecialchars_uni(strip_tags($forum['name']));
    $topforum        = '<a href="' . htmlspecialchars(get_forum_link($forum['fid']), ENT_QUOTES, 'UTF-8')
                       . '" class="text-decoration-none fw-semibold">' . $forum['name'] . '</a>';
    $topforumposts   = ts_nf((int)$forum['posts']);
    $topforumthreads = ts_nf((int)$forum['threads']);
}

$top_referrer = '';
if (($mybb->settings['statstopreferrer'] ?? 0) == 1
    && ($statistics['top_referrer']['referrals'] ?? 0) > 0
) {
    $toprefuser   = build_profile_link(
        htmlspecialchars_uni($statistics['top_referrer']['username'] ?? ''),
        (int)($statistics['top_referrer']['uid'] ?? 0)
    );
    $top_referrer = sprintf(
        $lang->stats['txt_top_referrer'],
        $toprefuser,
        ts_nf((int)($statistics['top_referrer']['referrals'] ?? 0))
    );
}

$topposter      = htmlspecialchars_uni($lang->stats['lbl_nobody']);
$topposterposts = 0;
if (isset($statistics['top_poster']['uid'])) {
    $topPosterUid = (int)($statistics['top_poster']['uid'] ?? 0);
    $topposter    = $topPosterUid === 0
        ? htmlspecialchars_uni($lang->stats['lbl_guest'])
        : build_profile_link(
            htmlspecialchars_uni($statistics['top_poster']['username'] ?? ''),
            $topPosterUid
        );
    $topposterposts = ts_nf((int)($statistics['top_poster']['poststoday'] ?? 0));
}

$posters           = (int)($statistics['posters'] ?? 0);
$havepostedpercent = ts_nf(round(($posters / max(1, $numusers)) * 100, 2)) . '%';

$todays_top_poster = sprintf(
    $lang->stats['txt_todays_top_poster'],
    $topposter, $topposterposts
);
$popular_forum = sprintf(
    $lang->stats['txt_popular_forum'],
    $topforum, $topforumposts, $topforumthreads
);

$stats['numposts']    = ts_nf($numposts);
$stats['numthreads']  = ts_nf($numthreads);
$stats['numusers']    = ts_nf($numusers);
$stats['newest_user'] = build_profile_link(
    htmlspecialchars_uni($stats['lastusername'] ?? ''),
    (int)($stats['lastuid'] ?? 0)
);

$plugins->run_hooks('stats_end');

$e = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$communityDays = ts_nf((int)floor($days));
$hasTopPoster  = isset($statistics['top_poster']['uid']);

$L = $lang->stats;

$kpis = [
    ['tone' => 'primary', 'icon' => 'fa-comment-dots', 'value' => $stats['numposts'],   'label' => $L['kpi_posts'],       'sub' => sprintf($L['kpi_sub_per_day'], $postsperday)],
    ['tone' => 'success', 'icon' => 'fa-layer-group',  'value' => $stats['numthreads'], 'label' => $L['kpi_threads'],     'sub' => sprintf($L['kpi_sub_per_day'], $threadsperday)],
    ['tone' => 'info',    'icon' => 'fa-users',        'value' => $stats['numusers'],   'label' => $L['kpi_members'],     'sub' => sprintf($L['kpi_sub_posted'], $havepostedpercent)],
    ['tone' => 'warning', 'icon' => 'fa-user-plus',    'value' => $membersperday,       'label' => $L['kpi_new_members'], 'sub' => sprintf($L['kpi_sub_over_days'], $communityDays)],
];

$averages = [
    ['tone' => 'primary',   'icon' => 'fa-pen',            'value' => $postsperday,      'label' => $L['avg_ppd']],
    ['tone' => 'success',   'icon' => 'fa-file-lines',     'value' => $threadsperday,    'label' => $L['avg_tpd']],
    ['tone' => 'warning',   'icon' => 'fa-user-plus',      'value' => $membersperday,    'label' => $L['avg_mpd']],
    ['tone' => 'info',      'icon' => 'fa-user-pen',       'value' => $postspermember,   'label' => $L['avg_ppm']],
    ['tone' => 'secondary', 'icon' => 'fa-folder-open',    'value' => $threadspermember, 'label' => $L['avg_tpm']],
    ['tone' => 'danger',    'icon' => 'fa-reply-all',      'value' => $repliesperthread, 'label' => $L['avg_rpt']],
];

stdhead($L['pane_title']);
build_breadcrumb();
?>
<link rel="stylesheet" href="<?= $e($BASEURL ?? '') ?>/include/templates/default/style/stats.css?ver=8">

<div class="ag-stats">

    <header class="ag-head">
        <div class="ag-head-icon"><i class="fa-solid fa-chart-line"></i></div>
        <div class="ag-head-text">
            <h1><?= $e($L['pane_title']) ?></h1>
            <p><?= $e($L['pane_desc']) ?></p>
        </div>
        <div class="ag-head-meta">
            <span class="ag-chip"><i class="fa-solid fa-hourglass-half"></i><?= $e(sprintf($L['chip_days_online'], $communityDays)) ?></span>
            <span class="ag-chip"><i class="fa-solid fa-user-check"></i><?= $e($L['chip_newest']) ?> <?= $stats['newest_user'] ?></span>
        </div>
    </header>

    <div class="ag-kpis">
        <?php foreach ($kpis as $k): ?>
        <div class="ag-kpi ag-tone-<?= $k['tone'] ?>">
            <div class="ag-kpi-icon"><i class="fa-solid <?= $k['icon'] ?>"></i></div>
            <div>
                <div class="ag-kpi-value"><?= $k['value'] ?></div>
                <div class="ag-kpi-label"><?= $e($k['label']) ?></div>
                <div class="ag-kpi-sub"><?= $e($k['sub']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="ag-grid">
        <div class="ag-col">
            <section class="ag-panel">
                <h2 class="ag-panel-head">
                    <span class="ag-panel-icon ag-tone-primary"><i class="fa-solid fa-comments"></i></span>
                    <?= $e($L['sec_most_replied']) ?>
                </h2>
                <?= $mostreplies ?: '<div class="ag-empty"><i class="fa-solid fa-inbox"></i>' . $e($L['empty_replied']) . '</div>' ?>
            </section>

            <section class="ag-panel">
                <h2 class="ag-panel-head">
                    <span class="ag-panel-icon ag-tone-info"><i class="fa-solid fa-eye"></i></span>
                    <?= $e($L['sec_most_viewed']) ?>
                </h2>
                <?= $mostviews ?: '<div class="ag-empty"><i class="fa-solid fa-inbox"></i>' . $e($L['empty_viewed']) . '</div>' ?>
            </section>
        </div>

        <div class="ag-col">
            <section class="ag-panel">
                <h2 class="ag-panel-head">
                    <span class="ag-panel-icon ag-tone-success"><i class="fa-solid fa-calculator"></i></span>
                    <?= $e($L['sec_averages']) ?>
                </h2>
                <div class="ag-avg-grid">
                    <?php foreach ($averages as $a): ?>
                    <div class="ag-avg ag-tone-<?= $a['tone'] ?>">
                        <i class="fa-solid <?= $a['icon'] ?>"></i>
                        <div class="ag-avg-value"><?= $a['value'] ?></div>
                        <div class="ag-avg-label"><?= $e($a['label']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ag-panel">
                <h2 class="ag-panel-head">
                    <span class="ag-panel-icon ag-tone-warning"><i class="fa-solid fa-trophy"></i></span>
                    <?= $e($L['sec_records']) ?>
                </h2>
                <ul class="ag-records">
                    <li>
                        <span class="ag-rec-icon ag-tone-warning"><i class="fa-solid fa-crown"></i></span>
                        <div class="ag-rec-body">
                            <div class="ag-rec-label"><?= $e($L['rec_top_poster']) ?></div>
                            <div class="ag-rec-value"><?= $topposter ?></div>
                        </div>
                        <?php if ($hasTopPoster): ?>
                        <span class="ag-rec-pill"><?= $e(sprintf($L['pill_posts'], $topposterposts)) ?></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span class="ag-rec-icon ag-tone-danger"><i class="fa-solid fa-fire"></i></span>
                        <div class="ag-rec-body">
                            <div class="ag-rec-label"><?= $e($L['rec_popular_forum']) ?></div>
                            <div class="ag-rec-value"><?= $topforum ?></div>
                        </div>
                        <span class="ag-rec-pill" title="<?= $e($L['tip_posts_threads']) ?>">
                            <i class="fa-solid fa-comment"></i><?= $topforumposts ?>
                            <i class="fa-solid fa-layer-group"></i><?= $topforumthreads ?>
                        </span>
                    </li>
                    <li>
                        <span class="ag-rec-icon ag-tone-success"><i class="fa-solid fa-user-check"></i></span>
                        <div class="ag-rec-body">
                            <div class="ag-rec-label"><?= $e($L['rec_newest_member']) ?></div>
                            <div class="ag-rec-value"><?= $stats['newest_user'] ?></div>
                        </div>
                    </li>
                    <li>
                        <span class="ag-rec-icon ag-tone-primary"><i class="fa-solid fa-percent"></i></span>
                        <div class="ag-rec-body">
                            <div class="ag-rec-label"><?= $e($L['rec_members_posted']) ?></div>
                            <div class="ag-rec-value"><?= $havepostedpercent ?></div>
                        </div>
                        <span class="ag-rec-pill"><?= $e(sprintf($L['pill_of'], ts_nf($posters), $stats['numusers'])) ?></span>
                    </li>
                    <?php if ($top_referrer): ?>
                    <li>
                        <span class="ag-rec-icon ag-tone-info"><i class="fa-solid fa-handshake"></i></span>
                        <div class="ag-rec-body">
                            <div class="ag-rec-label"><?= $e($L['rec_top_referrer']) ?></div>
                            <div class="ag-rec-value"><?= $toprefuser ?></div>
                        </div>
                        <span class="ag-rec-pill"><?= $e(sprintf($L['pill_invited'], ts_nf((int)($statistics['top_referrer']['referrals'] ?? 0)))) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </section>
        </div>
    </div>
</div>

<?php
stdfoot();