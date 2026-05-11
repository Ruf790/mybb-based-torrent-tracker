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
    stderr($lang->stats['not_enough_info_stats']);
}

$plugins->run_hooks('stats_start');

$numposts   = (int)($stats['numposts']   ?? 0);
$numthreads = (int)($stats['numthreads'] ?? 0);
$numusers   = (int)($stats['numusers']   ?? 0);

$repliesperthread = ts_nf(round(($numposts - $numthreads) / max(1, $numthreads), 2));
$postspermember   = ts_nf(round($numposts   / max(1, $numusers), 2));
$threadspermember = ts_nf(round($numthreads / max(1, $numusers), 2));

$query  = $db->simple_select('users', 'added', '', ['order_by' => 'added', 'order_dir' => 'ASC', 'limit' => 1]);
$result = $db->fetch_array($query);
$days   = max((TIMENOW - (int)($result['added'] ?? TIMENOW)) / 86400, 1);

$postsperday   = ts_nf(round($numposts   / $days, 2));
$threadsperday = ts_nf(round($numthreads / $days, 2));
$membersperday = ts_nf(round($numusers   / $days, 2));

$unviewableforums = get_unviewable_forums(true);
$inactiveforums   = get_inactive_forums();

$unviewablefids        = $unviewableforums ? explode(',', $unviewableforums) : [];
$inactivefids          = $inactiveforums   ? explode(',', $inactiveforums)   : [];
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

// FIX: buildThreadRow() вызывается внутри цикла — переменные всегда определены
// FIX: ; после строки шаблона — устраняет "unexpected token if"
// FIX: $numbertype -> $number_type
function buildThreadRow(array $thread, string $number_type): string
{
    global $parser;
    $subject    = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
    $threadlink = htmlspecialchars(get_thread_link($thread['tid']), ENT_QUOTES, 'UTF-8');
    $numberbit  = ts_nf((int)($thread[$number_type] ?? 0));
    $typeLabel  = htmlspecialchars($number_type, ENT_QUOTES, 'UTF-8');

    return '<div class="stats-thread-item d-flex justify-content-between align-items-center py-2 border-bottom">'
        . '<div class="stats-thread-title flex-grow-1">'
            . '<i class="bi bi-chat-dots-fill me-2 text-primary"></i>'
            . '<a href="' . $threadlink . '" class="text-decoration-none">' . $subject . '</a>'
        . '</div>'
        . '<div class="stats-thread-count text-muted">'
            . '<span class="badge bg-primary rounded-pill">' . $numberbit . '</span> ' . $typeLabel
        . '</div>'
        . '</div>';
}

$most_replied = $cache->read('most_replied_threads') ?? [];
if (empty($most_replied)) {
    $cache->update_most_replied_threads();
    $most_replied = $cache->read('most_replied_threads') ?? [];
}

$mostreplies = '';
foreach ($most_replied as $thread) {
    if (in_array($thread['fid'], $unviewableforumsarray, true)) continue;
    $mostreplies .= buildThreadRow($thread, 'replies');
}

$most_viewed = $cache->read('most_viewed_threads') ?? [];
if (empty($most_viewed)) {
    $cache->update_most_viewed_threads();
    $most_viewed = $cache->read('most_viewed_threads') ?? [];
}

$mostviews = '';
foreach ($most_viewed as $thread) {
    if (in_array($thread['fid'], $unviewableforumsarray, true)) continue;
    $mostviews .= buildThreadRow($thread, 'views');
}

$statistics     = $cache->read('statistics') ?? [];
$statscachetime = (int)($mybb->settings['statscachetime'] ?? 24);
$interval       = max($statscachetime, 0) * 3600;

if (empty($statistics) || $interval === 0 || (TIMENOW - $interval) > ($statistics['time'] ?? 0)) {
    $cache->update_statistics();
    $statistics = $cache->read('statistics') ?? [];
}

$query = $db->simple_select(
    'forums', 'fid, name, threads, posts',
    "type='f' {$fidnot}",
    ['order_by' => 'posts', 'order_dir' => 'DESC', 'limit' => 1]
);
$forum = $db->fetch_array($query);

if (!$forum) {
    $topforum = 'None'; $topforumposts = '0'; $topforumthreads = '0';
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
        $lang->stats['top_referrer'] ?? 'Top referrer: %s (%s referrals)',
        $toprefuser,
        ts_nf((int)($statistics['top_referrer']['referrals'] ?? 0))
    );
}

$topposter      = 'Nobody';
$topposterposts = 0;
if (isset($statistics['top_poster']['uid'])) {
    $topPosterUid = (int)($statistics['top_poster']['uid'] ?? 0);
    $topposter    = $topPosterUid === 0
        ? 'Guest'
        : build_profile_link(
            htmlspecialchars_uni($statistics['top_poster']['username'] ?? ''),
            $topPosterUid
        );
    $topposterposts = ts_nf((int)($statistics['top_poster']['poststoday'] ?? 0));
}

$posters           = (int)($statistics['posters'] ?? 0);
$havepostedpercent = ts_nf(round(($posters / max(1, $numusers)) * 100, 2)) . '%';

$todays_top_poster = sprintf(
    $lang->stats['todays_top_poster'] ?? "Today's top poster: %s (%s posts)",
    $topposter, $topposterposts
);
$popular_forum = sprintf(
    $lang->stats['popular_forum'] ?? 'Most popular forum: %s (%s posts, %s threads)',
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

stdhead($lang->stats['board_stats'] ?? 'Board Statistics');
build_breadcrumb();
?>
<style>
:root { --stats-accent: #3b82f6; --stats-accent2: #2563eb; }
.stats-container { max-width:1140px; margin:0 auto; padding:20px; }
.stats-header {
    background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    border-radius:20px; padding:40px 30px; margin-bottom:30px;
    color:white; position:relative; overflow:hidden;
}
.stats-header::before {
    content:''; position:absolute; top:-50%; right:-20%;
    width:300px; height:300px; background:rgba(255,255,255,.1); border-radius:50%;
}
.stats-header::after {
    content:''; position:absolute; bottom:-30%; left:-10%;
    width:200px; height:200px; background:rgba(255,255,255,.05); border-radius:50%;
}
.stats-header h1 { font-size:2rem; font-weight:700; margin-bottom:10px; position:relative; z-index:1; }
.stats-header p  { opacity:.9; margin-bottom:0; position:relative; z-index:1; }
.stats-kpi-grid {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px; margin-bottom:30px;
}
.stats-kpi-card {
    background:var(--bs-body-bg); border:1px solid var(--bs-border-color);
    border-radius:16px; padding:20px; text-align:center;
    box-shadow:0 4px 12px rgba(0,0,0,.05); transition:transform .2s;
}
.stats-kpi-card:hover { transform:translateY(-3px); }
.stats-kpi-icon {
    width:50px; height:50px;
    background:linear-gradient(135deg,rgba(59,130,246,.1),rgba(118,75,162,.1));
    border-radius:12px; display:flex; align-items:center;
    justify-content:center; margin:0 auto 15px;
}
.stats-kpi-icon i  { font-size:24px; color:var(--stats-accent); }
.stats-kpi-value   { font-size:28px; font-weight:700; color:var(--stats-accent); margin-bottom:5px; }
.stats-kpi-label   { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--bs-secondary-color); }
.stats-card {
    background:var(--bs-body-bg); border:none; border-radius:16px;
    box-shadow:0 10px 25px -5px rgba(0,0,0,.05);
    transition:transform .2s,box-shadow .2s; overflow:hidden;
    margin-bottom:24px; animation:fadeInUp .4s ease-out both;
}
.stats-card:hover { transform:translateY(-2px); box-shadow:0 20px 30px -12px rgba(0,0,0,.1); }
.stats-card:nth-child(1) { animation-delay:.05s; }
.stats-card:nth-child(2) { animation-delay:.10s; }
.stats-card:nth-child(3) { animation-delay:.15s; }
.stats-card-header {
    background:linear-gradient(135deg,var(--stats-accent) 0%,var(--stats-accent2) 100%);
    color:white; padding:16px 20px; font-weight:600; font-size:1rem;
}
.stats-card-header i { margin-right:8px; }
.stats-card-body     { padding:20px; }
.stats-item {
    display:flex; justify-content:space-between; align-items:center;
    padding:12px 0; border-bottom:1px solid var(--bs-border-color);
}
.stats-item:last-child { border-bottom:none; }
.stats-label { display:flex; align-items:center; gap:10px; color:var(--bs-secondary-color); font-size:.9rem; }
.stats-label i { width:20px; font-size:1rem; }
.stats-value   { font-weight:700; font-size:1rem; color:var(--bs-body-color); text-align:right; }
.stats-thread-item { transition:background .2s; }
.stats-thread-item:hover { background:rgba(59,130,246,.05); padding-left:8px; border-radius:8px; }
.stats-thread-title a { color:var(--bs-body-color); transition:color .2s; }
.stats-thread-title a:hover { color:var(--stats-accent); }
@keyframes fadeInUp {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}
@media (max-width:768px) {
    .stats-container { padding:15px; }
    .stats-header    { padding:30px 20px; }
    .stats-header h1 { font-size:1.5rem; }
    .stats-kpi-value { font-size:22px; }
    .stats-thread-item { flex-direction:column; align-items:flex-start; gap:8px; }
}
[data-bs-theme="dark"] .stats-kpi-card { box-shadow:0 4px 12px rgba(0,0,0,.2); }
[data-bs-theme="dark"] .stats-card     { box-shadow:0 10px 25px -5px rgba(0,0,0,.2); }
</style>

<div class="stats-container">
    <div class="stats-header">
        <h1><i class="bi bi-graph-up me-2"></i><?= htmlspecialchars($lang->stats['board_stats'] ?? 'Forum Statistics', ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($lang->stats['board_stats_desc'] ?? 'Comprehensive statistics about your community activity', ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="stats-kpi-grid">
        <div class="stats-kpi-card">
            <div class="stats-kpi-icon"><i class="bi bi-chat-dots-fill"></i></div>
            <div class="stats-kpi-value"><?= $stats['numposts'] ?></div>
            <div class="stats-kpi-label">Total Posts</div>
        </div>
        <div class="stats-kpi-card">
            <div class="stats-kpi-icon"><i class="bi bi-card-text"></i></div>
            <div class="stats-kpi-value"><?= $stats['numthreads'] ?></div>
            <div class="stats-kpi-label">Total Threads</div>
        </div>
        <div class="stats-kpi-card">
            <div class="stats-kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="stats-kpi-value"><?= $stats['numusers'] ?></div>
            <div class="stats-kpi-label">Total Members</div>
        </div>
        <div class="stats-kpi-card">
            <div class="stats-kpi-icon"><i class="bi bi-person-plus-fill"></i></div>
            <div class="stats-kpi-value"><?= $membersperday ?></div>
            <div class="stats-kpi-label">New Members / Day</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="stats-card">
                <div class="stats-card-header">
                    <i class="bi bi-chat-right-text-fill"></i>
                    <?= htmlspecialchars($lang->stats['most_replied_threads'] ?? 'Most Replied Threads', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="stats-card-body">
                    <?php if ($mostreplies): ?>
                        <?= $mostreplies ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No threads found</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-card-header">
                    <i class="bi bi-eye-fill"></i>
                    <?= htmlspecialchars($lang->stats['most_viewed_threads'] ?? 'Most Viewed Threads', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="stats-card-body">
                    <?php if ($mostviews): ?>
                        <?= $mostviews ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No threads found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="stats-card">
                <div class="stats-card-header"><i class="bi bi-bar-chart-steps"></i><?= htmlspecialchars($lang->stats['totals'] ?? 'Totals', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="stats-card-body">
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-pencil-fill text-primary"></i>Posts</div><div class="stats-value"><?= $stats['numposts'] ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-card-text text-success"></i>Threads</div><div class="stats-value"><?= $stats['numthreads'] ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-people-fill text-info"></i>Members</div><div class="stats-value"><?= $stats['numusers'] ?></div></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-card-header"><i class="bi bi-calculator-fill"></i><?= htmlspecialchars($lang->stats['averages'] ?? 'Averages', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="stats-card-body">
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-pencil-fill text-primary"></i>Posts per day</div><div class="stats-value"><?= $postsperday ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-card-text text-success"></i>Threads per day</div><div class="stats-value"><?= $threadsperday ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-person-plus-fill text-warning"></i>Members per day</div><div class="stats-value"><?= $membersperday ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-chat-dots-fill text-info"></i>Posts per member</div><div class="stats-value"><?= $postspermember ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-journals text-secondary"></i>Threads per member</div><div class="stats-value"><?= $threadspermember ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-arrow-return-right text-danger"></i>Replies per thread</div><div class="stats-value"><?= $repliesperthread ?></div></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-card-header"><i class="bi bi-trophy-fill"></i><?= htmlspecialchars($lang->stats['information'] ?? 'Records', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="stats-card-body">
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-person-check-fill text-success"></i>Newest member</div><div class="stats-value"><?= $stats['newest_user'] ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-percent text-primary"></i>Members posted</div><div class="stats-value"><?= $havepostedpercent ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-star-fill text-warning"></i>Top poster today</div><div class="stats-value"><?= $todays_top_poster ?></div></div>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-fire text-danger"></i>Popular forum</div><div class="stats-value"><?= $popular_forum ?></div></div>
                    <?php if ($top_referrer): ?>
                    <div class="stats-item"><div class="stats-label"><i class="bi bi-person-heart text-info"></i>Top referrer</div><div class="stats-value"><?= $top_referrer ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
stdfoot();