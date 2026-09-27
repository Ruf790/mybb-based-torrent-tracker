<?php


declare(strict_types=1);




function update_forum_counters(int|string $fid, array $changes = []): void
{
    global $db;

    $fid = (int)$fid;
	
	$counters = ['threads', 'unapprovedthreads', 'posts', 'unapprovedposts'];
    $query    = $db->sql_query_prepared(
        "SELECT " . implode(',', $counters) . " FROM forums WHERE fid = ?",
        [$fid]
    );
    $forum  = $query ? $db->fetch_array($query) : null;
    $update = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $forum[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $fid;

        $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
    }

    // Обновляем глобальную статистику
    $stat_map = [
        'threads'           => 'numthreads',
        'unapprovedthreads' => 'numunapprovedthreads',
        'posts'             => 'numposts',
        'unapprovedposts'   => 'numunapprovedposts',
    ];

    $new_stats = [];
    foreach ($stat_map as $counter => $stat) {
        if (!isset($update[$counter])) {
            continue;
        }
        $diff = $update[$counter] - $forum[$counter];
        $new_stats[$stat] = ($diff >= 0 ? '+' : '') . $diff;
    }

    if (!empty($new_stats)) {
        update_stats($new_stats);
    }
}



// ── update_forum_lastpost ─────────────────────────────────────────────────────
function update_forum_lastpost(int $fid): void
{
    global $db;

    $query = $db->sql_query_prepared("
        SELECT tid, lastpost, lastposter, lastposteruid, subject
        FROM threads
        WHERE fid = ? AND visible = '1' AND closed NOT LIKE 'moved|%'
        ORDER BY lastpost DESC LIMIT 1
    ", [$fid]);

    if ($query && $db->num_rows($query) > 0) {
        $last = $db->fetch_array($query);
        $updated = [
            'lastpost'        => (int)$last['lastpost'],
            'lastposter'      => $last['lastposter'],
            'lastposteruid'   => (int)$last['lastposteruid'],
            'lastposttid'     => (int)$last['tid'],
            'lastpostsubject' => $last['subject'],
        ];
    } else {
        $updated = [
            'lastpost' => 0, 'lastposter' => '', 'lastposteruid' => 0,
            'lastposttid' => 0, 'lastpostsubject' => '',
        ];
    }

    $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($updated)));
    $params = array_values($updated);
    $params[] = $fid;

    $db->sql_query_prepared("UPDATE forums SET {$set} WHERE fid = ?", $params);
}



// ── update_thread_counters ────────────────────────────────────────────────────
function update_thread_counters(int $tid, array $changes = []): void
{
    global $db;

    $counters = ['replies', 'unapprovedposts', 'attachmentcount'];
    $query    = $db->sql_query_prepared(
        "SELECT " . implode(',', $counters) . " FROM threads WHERE tid = ?",
        [$tid]
    );
    $thread = $query ? $db->fetch_array($query) : null;
    $update = [];

    foreach ($counters as $counter) {
        if (!array_key_exists($counter, $changes)) {
            continue;
        }

        $val = $changes[$counter];

        if (str_starts_with((string)$val, '+-')) {
            $val = substr((string)$val, 1);
        }

        $new = str_starts_with((string)$val, '+') || str_starts_with((string)$val, '-')
            ? $thread[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $tid;

        $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);
    }
}


// ── update_thread_data ────────────────────────────────────────────────────────
function update_thread_data(int $tid): void
{
    global $db;

    $thread = get_thread($tid);

    if ($thread && str_starts_with((string)$thread['closed'], 'moved|')) {
        return;
    }

    $last_query = $db->sql_query_prepared("
        SELECT u.id, u.username, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = ? AND p.visible = '1'
        ORDER BY p.dateline DESC, p.pid DESC LIMIT 1
    ", [$tid]);
    $last = $last_query ? $db->fetch_array($last_query) : null;

    $first_query = $db->sql_query_prepared("
        SELECT u.id, u.username, p.pid, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = ?
        ORDER BY p.dateline ASC, p.pid ASC LIMIT 1
    ", [$tid]);
    $first = $first_query ? $db->fetch_array($first_query) : null;

    $first['username'] = $first['username'] ?: $first['postusername'];
    $last['username']  = $last['username']  ?: $last['postusername'];

    if (empty($last['dateline'])) {
        $last['username'] = $first['username'];
        $last['id']       = $first['id'];
        $last['dateline'] = $first['dateline'];
    }

    $db->sql_query_prepared("
        UPDATE threads
        SET firstpost = ?, username = ?, uid = ?, dateline = ?,
            lastpost = ?, lastposter = ?, lastposteruid = ?
        WHERE tid = ?
    ", [
        (int)$first['pid'],
        $first['username'],
        (int)$first['id'],
        (int)$first['dateline'],
        (int)$last['dateline'],
        $last['username'],
        (int)$last['id'],
        $tid,
    ]);
}




// Disallow direct access to this file for security reasons
if (!defined("STAFF_PANEL")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure STAFF_PANEL is defined.");
}

require_once INC_PATH . '/functions_image_recode.php';


// Initialize missing inputs
foreach (['action', 'do', 'module'] as $input) {
    $mybb->input[$input] ??= '';
}


/**
 * Описание всех задач: используется и в меню, и на странице прогресса.
 * action => [section, icon, color-class, title, description, per-page input, default per page]
 */
function rr_tasks(): array
{
    return [
        'do_rebuildforumcounters'   => ['forums', 'fa-folder-tree',  'ic-blue',   'Forum counters',        'Post/thread counters and last post of every forum.',                         'forumcounters',  50],
        'do_rebuildthreadcounters'  => ['forums', 'fa-comments',     'ic-blue',   'Thread counters',       'Reply/view counters and last post of every thread.',                         'threadcounters', 500],
        'do_rebuildpollcounters'    => ['forums', 'fa-chart-pie',    'ic-blue',   'Poll counters',         'Vote counters and totals of every poll.',                                    'pollcounters',   500],
        'do_recountthreadratings'   => ['forums', 'fa-star',         'ic-amber',  'Thread ratings',        'Average rating and vote count cached on each thread.',                       'threadratings',  500],
        'do_recountuserposts'       => ['users',  'fa-file-lines',   'ic-green',  'User post counts',      'Post count of each user from the posts in the database.',                    'userposts',      500],
        'do_recountuserthreads'     => ['users',  'fa-clone',        'ic-green',  'User thread counts',    'Thread count of each user from the threads in the database.',                'userthreads',    500],
        'do_comments'               => ['users',  'fa-comment',      'ic-green',  'User comment counts',   'Torrent comment count of each user.',                                        'comments',       500],
        'do_recountprivatemessages' => ['users',  'fa-envelope',     'ic-green',  'Private messages',      'Private message counters of each user.',                                     'privatemessages',500],
        'do_rebuildattachmentthumbs'        => ['media', 'fa-image',  'ic-purple', 'Attachment thumbnails',         'Regenerate forum attachment thumbnails at the current size.', 'attachmentthumbs',        20],
        'do_rebuildcommentattachmentthumbs' => ['media', 'fa-images', 'ic-purple', 'Comment attachment thumbnails', 'Regenerate comment attachment thumbnails at the current size.', 'commentattachmentthumbs', 20],
        'do_recounttorrentcomments' => ['site',   'fa-magnet',       'ic-red',    'Torrent comment counts','Comment count cached on each torrent.',                                      'torrentcomments',500],
        'do_recountstats'           => ['site',   'fa-chart-column', 'ic-teal',   'Board statistics',      'Totals on the forum index and statistics pages. Runs in one step.',          '',               0],
    ];
}

function rr_styles(): void
{
    echo <<<'HTML'
<style>
.rr .rr-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color-translucent); border-radius: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.rr .rr-head { display: flex; flex-wrap: wrap; align-items: center; gap: .9rem; padding: 1.1rem 1.25rem; }
.rr .rr-head-icon, .rr .rr-ico, .rr .rr-sec-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rr .rr-head-icon { width: 48px; height: 48px; font-size: 1.35rem; border-radius: .85rem; }
.rr .rr-title { font-size: 1.4rem; font-weight: 700; margin: 0; }
.rr .rr-sub { color: var(--bs-secondary-color); font-size: .95rem; }
.rr .rr-muted { font-size: .86rem; color: var(--bs-secondary-color); }
.rr .ic-blue   { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.12); }
.rr .ic-green  { color: #16a34a; background: rgba(34,197,94,.12); }
.rr .ic-amber  { color: #d97706; background: rgba(245,158,11,.14); }
.rr .ic-purple { color: #7c3aed; background: rgba(124,58,237,.12); }
.rr .ic-red    { color: #dc2626; background: rgba(239,68,68,.12); }
.rr .ic-teal   { color: #0891b2; background: rgba(8,145,178,.12); }
.rr .btn { border-radius: 50rem; }

.rr .rr-sec { display: flex; align-items: center; gap: .6rem; margin: 1.5rem 0 .75rem; font-weight: 700; font-size: 1.05rem; }
.rr .rr-sec:first-of-type { margin-top: .25rem; }
.rr .rr-sec-icon { width: 32px; height: 32px; border-radius: .6rem; font-size: .9rem; }

/* Без transform на hover: раньше .card { transform } действовал на ВСЕ карточки сайта */
.rr .rr-task { display: flex; flex-direction: column; height: 100%; padding: 1rem 1.1rem; transition: border-color .15s ease, box-shadow .15s ease; }
.rr .rr-task:hover { border-color: rgba(var(--bs-primary-rgb), .35); box-shadow: 0 .4rem 1rem rgba(0,0,0,.06); }
.rr .rr-ico { width: 40px; height: 40px; border-radius: .75rem; font-size: 1rem; }
.rr .rr-task h3 { font-size: 1.02rem; font-weight: 700; margin: 0; }
.rr .rr-task p { font-size: .9rem; color: var(--bs-secondary-color); margin: .5rem 0 .9rem; flex: 1; }
.rr .rr-run { display: flex; align-items: center; gap: .5rem; }
.rr .rr-run .input-group { width: 150px; }
.rr .rr-run .form-control { border-radius: .6rem 0 0 .6rem; text-align: center; }
.rr .rr-run .input-group-text { border-radius: 0 .6rem .6rem 0; font-size: .78rem; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); }
.rr .rr-onestep { font-size: .82rem; color: var(--bs-secondary-color); display: inline-flex; align-items: center; gap: .3rem; }

.rr .rr-progress { max-width: 560px; margin: 2rem auto; padding: 2rem 1.5rem; text-align: center; }
.rr .rr-progress .rr-head-icon { margin: 0 auto 1rem; width: 64px; height: 64px; border-radius: 50%; font-size: 1.6rem; }
.rr .rr-bar { height: 12px; border-radius: 50rem; background: var(--bs-secondary-bg); overflow: hidden; margin: 1.25rem 0 .5rem; }
.rr .rr-bar > span { display: block; height: 100%; border-radius: 50rem; background: linear-gradient(90deg, #60a5fa, #3b82f6); transition: width .4s ease; }
</style>
HTML;
}

/**
 * Промежуточная страница пакетной обработки: прогресс-бар и автопродолжение.
 * Раньше: пустая страница с одной кнопкой «Automatically Redirecting…» без
 * указания, что за задача и сколько осталось.
 */
function check_proceed(
    int $current,
    int $finish,
    int $next_page,
    int $per_page,
    string $name,
    string $name2,
    ?string $message = null
): void {
    global $mybb, $_this_script_;

    $message ??= 'The recount has been completed successfully';

    if ($finish >= $current) {
        flash_message($message, 'success');
        admin_redirect("index.php?act=recount_rebuild");
    }

    $task  = rr_tasks()[$name2] ?? ['', 'fa-rotate', 'ic-blue', 'Recount', '', $name, $per_page];
    $done  = max(0, min($finish, $current));
    $pct   = $current > 0 ? (int)floor($done / $current * 100) : 0;
    $key   = htmlspecialchars((string)$mybb->post_code, ENT_QUOTES);
    $self  = htmlspecialchars((string)$_this_script_, ENT_QUOTES);
    $nameE = htmlspecialchars($name, ENT_QUOTES);
    $act   = htmlspecialchars($name2, ENT_QUOTES);

    stdhead($task[3] . ' — ' . $pct . '%');
    rr_styles();
    echo <<<HTML
<div class="container mt-3 mb-4 rr">
  <div class="rr-card rr-progress">
    <span class="rr-head-icon {$task[2]}"><i class="fa-solid {$task[1]}"></i></span>
    <h2 class="h4 fw-bold mb-1">{$task[3]}</h2>
    <div class="rr-muted">Processing batch {$next_page} · {$per_page} per step</div>
    <div class="rr-bar" role="progressbar" aria-valuenow="{$pct}" aria-valuemin="0" aria-valuemax="100"><span style="width:{$pct}%"></span></div>
    <div class="d-flex justify-content-between rr-muted"><span><strong class="text-body">{$done}</strong> / {$current}</span><span><strong class="text-body">{$pct}%</strong></span></div>

    <form action="{$self}" method="post" id="rrContinue" class="mt-4">
      <input type="hidden" name="my_post_key" value="{$key}">
      <input type="hidden" name="page" value="{$next_page}">
      <input type="hidden" name="{$nameE}" value="{$per_page}">
      <input type="hidden" name="{$act}" value="Go">
      <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="index.php?act=recount_rebuild" class="btn btn-outline-secondary px-3" id="rrStop"><i class="fa-solid fa-stop me-1"></i>Stop</a>
        <button type="submit" class="btn btn-primary px-4" id="rrGo"><span class="spinner-border spinner-border-sm me-2"></span>Continuing…</button>
      </div>
    </form>
    <div class="rr-muted mt-3"><i class="fa-solid fa-circle-info me-1"></i>Keep this tab open — the next batch starts automatically.</div>
  </div>
</div>
<script>
(function () {
    let stopped = false;
    document.getElementById('rrStop').addEventListener('click', () => { stopped = true; });
    setTimeout(() => { if (!stopped) document.getElementById('rrContinue').submit(); }, 400);
})();
</script>
HTML;
    stdfoot();
    exit;
}


$plugins->run_hooks("admin_tools_recount_rebuild");

/**
 * Rebuild forum counters
 */
function acp_rebuild_forum_counters(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_forum_counters");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_forums FROM forums");
    $num_forums = $query ? (int)$db->fetch_field($query, 'num_forums') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('forumcounters', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT fid FROM forums ORDER BY fid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($forum = $db->fetch_array($query))) {
        $update = ['parentlist' => make_parent_list((int)$forum['fid'])];
        $db->sql_query_prepared("UPDATE forums SET parentlist = ? WHERE fid = ?", [$update['parentlist'], (int)$forum['fid']]);
        rebuild_forum_counters((int)$forum['fid']);
    }

    $message = $lang->success_rebuilt_forum_counters ?? 'The forum counters have been rebuilt successfully';
    
    check_proceed(
        $num_forums, 
        $end, 
        ++$page, 
        $per_page, 
        "forumcounters", 
        "do_rebuildforumcounters",
        $message
    );
}

/**
 * Rebuild thread counters
 */
function acp_rebuild_thread_counters(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_thread_counters");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_threads FROM threads");
    $num_threads = $query ? (int)$db->fetch_field($query, 'num_threads') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('threadcounters', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT tid FROM threads ORDER BY tid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($thread = $db->fetch_array($query))) {
        rebuild_thread_counters((int)$thread['tid']);
    }

    $message = $lang->success_rebuilt_thread_counters ?? 'The thread counters have been rebuilt successfully';
    
    check_proceed(
        $num_threads, 
        $end, 
        ++$page, 
        $per_page, 
        "threadcounters", 
        "do_rebuildthreadcounters", 
        $message
    );
}

/**
 * Rebuild poll counters
 */
function acp_rebuild_poll_counters(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_poll_counters");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_polls FROM polls");
    $num_polls = $query ? (int)$db->fetch_field($query, 'num_polls') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('pollcounters', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT pid FROM polls ORDER BY pid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($poll = $db->fetch_array($query))) {
        rebuild_poll_counters((int)$poll['pid']);
    }

    $message = $lang->success_rebuilt_poll_counters ?? 'The poll counters have been rebuilt successfully';
    
    check_proceed(
        $num_polls, 
        $end, 
        ++$page, 
        $per_page, 
        "pollcounters", 
        "do_rebuildpollcounters", 
        $message
    );
}

/**
 * Recount thread ratings (numratings/totalratings cache columns)
 */
function acp_recount_thread_ratings(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_thread_ratings");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_threads FROM threads");
    $num_threads = $query ? (int)$db->fetch_field($query, 'num_threads') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('threadratings', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT tid FROM threads ORDER BY tid ASC LIMIT ?, ?",
        [$start, $per_page]
    );

    while ($query && ($thread = $db->fetch_array($query))) {
        $tid = (int)$thread['tid'];

        $r = $db->sql_query_prepared("SELECT ROUND(AVG(rating),1) AS avg, COUNT(id) AS cnt FROM threadratings WHERE tid = ?", [$tid]);
        $row = $r ? $db->fetch_array($r) : null;
        $avg   = (float)($row['avg'] ?? 0);
        $count = (int)($row['cnt'] ?? 0);

        $db->sql_query_prepared(
            "UPDATE threads SET numratings = ?, totalratings = ? WHERE tid = ?",
            [$count, (int)round($avg * $count), $tid]
        );
    }

    $message = $lang->success_rebuilt_thread_ratings ?? 'Thread ratings have been recounted successfully';

    check_proceed(
        $num_threads,
        $end,
        ++$page,
        $per_page,
        "threadratings",
        "do_recountthreadratings",
        $message
    );
}

/**
 * Recount torrent comment counts (torrents.comments cache column)
 */
function acp_recount_torrent_comments(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_torrent_comments");

    $query = $db->sql_query_prepared("SELECT COUNT(*) as num_torrents FROM torrents");
    $num_torrents = $query ? (int)$db->fetch_field($query, 'num_torrents') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('torrentcomments', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $query = $db->sql_query_prepared(
        "SELECT id FROM torrents ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );

    while ($query && ($torrent = $db->fetch_array($query))) {
        $tid = (int)$torrent['id'];

        $r = $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = ?", [$tid]);
        $row = $r ? $db->fetch_array($r) : null;
        $count = (int)($row['cnt'] ?? 0);

        $db->sql_query_prepared("UPDATE torrents SET comments = ? WHERE id = ?", [$count, $tid]);
    }

    $message = $lang->success_rebuilt_torrent_comments ?? 'Torrent comment counts have been recounted successfully';

    check_proceed(
        $num_torrents,
        $end,
        ++$page,
        $per_page,
        "torrentcomments",
        "do_recounttorrentcomments",
        $message
    );
}

/**
 * Recount user posts
 */
function acp_recount_user_posts(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_user_posts");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('userposts', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $fids = [];
    $query = $db->sql_query_prepared("SELECT fid FROM forums WHERE usepostcounts = 0");
    
    while ($query && ($forum = $db->fetch_array($query))) {
        $fids[] = (int)$forum['fid'];
    }
    
    $fidsCondition = '';
    if (!empty($fids)) {
        $fidsList = implode(',', $fids);
        $fidsCondition = " AND p.fid NOT IN({$fidsList})";
    }

    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        $query2 = $db->sql_query_prepared("
            SELECT COUNT(p.pid) AS post_count
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE p.uid = ? 
            AND t.visible > 0 
            AND p.visible > 0
            {$fidsCondition}
        ", [(int)$user['id']]);
        
        $num_posts = $query2 ? (int)$db->fetch_field($query2, "post_count") : 0;
        $db->sql_query_prepared("UPDATE users SET postnum = ? WHERE id = ?", [$num_posts, (int)$user['id']]);
    }

    $message = $lang->success_rebuilt_user_post_counters ?? 'The user posts count have been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "userposts", 
        "do_recountuserposts", 
        $message
    );
}

/**
 * Recount user threads
 */
function acp_recount_user_threads(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_rebuild_user_threads");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('userthreads', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    $fids = [];
    $query = $db->sql_query_prepared("SELECT fid FROM forums WHERE usethreadcounts = 0");
    
    while ($query && ($forum = $db->fetch_array($query))) {
        $fids[] = (int)$forum['fid'];
    }
    
    $fidsCondition = '';
    if (!empty($fids)) {
        $fidsList = implode(',', $fids);
        $fidsCondition = " AND t.fid NOT IN({$fidsList})";
    }

    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        $query2 = $db->sql_query_prepared("
            SELECT COUNT(t.tid) AS thread_count
            FROM threads t
            WHERE t.uid = ? 
            AND t.visible > 0 
            AND t.closed NOT LIKE 'moved|%'
            {$fidsCondition}
        ", [(int)$user['id']]);
        
        $num_threads = $query2 ? (int)$db->fetch_field($query2, "thread_count") : 0;
        $db->sql_query_prepared("UPDATE users SET threadnum = ? WHERE id = ?", [$num_threads, (int)$user['id']]);
    }

    $message = $lang->success_rebuilt_user_thread_counters ?? 'The user threads count have been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "userthreads", 
        "do_recountuserthreads", 
        $message
    );
}

/**
 * Recount private messages (total and unread) for users
 */
function acp_recount_private_messages(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_recount_private_messages");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('privatemessages', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;

    require_once INC_PATH . "/functions_user.php";

    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        update_pm_count((int)$user['id']);
    }

    $message = $lang->success_rebuilt_private_messages ?? 'The user private message count has been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "privatemessages", 
        "do_recountprivatemessages", 
        $message
    );
}

function acp_recount_user_comments(): void
{
    global $db, $mybb, $lang, $plugins;

    $plugins->run_hooks("admin_tools_recount_recount_comments");

    $query = $db->sql_query_prepared("SELECT COUNT(id) as num_users FROM users");
    $num_users = $query ? (int)$db->fetch_field($query, 'num_users') : 0;
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('comments', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;
    
    $query = $db->sql_query_prepared(
        "SELECT id FROM users ORDER BY id ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    while ($query && ($user = $db->fetch_array($query))) {
        $query2 = $db->sql_query_prepared("
            SELECT COUNT(c.id) AS post_count
            FROM comments c
            LEFT JOIN torrents t ON (t.id = c.torrent)
            WHERE c.user = ?
        ", [(int)$user['id']]);
        
        $num_posts = $query2 ? (int)$db->fetch_field($query2, "post_count") : 0;
        $db->sql_query_prepared("UPDATE users SET comms = ? WHERE id = ?", [$num_posts, (int)$user['id']]);
    }
    
    $message = $lang->success_rebuilt_private_messages ?? 'The user private message count has been recounted successfully';
    
    check_proceed(
        $num_users, 
        $end, 
        ++$page, 
        $per_page, 
        "comments", 
        "do_comments", 
        $message
    );
}

/**
 * Rebuild thumbnails for attachments
 */
function acp_rebuild_attachment_thumbnails(): void
{
    global $db, $mybb, $lang, $plugins, $uploadspath, $attachthumbh, $attachthumbw;

    $plugins->run_hooks("admin_tools_recount_rebuild_attachment_thumbs");

    $query = $db->sql_query_prepared("SELECT COUNT(aid) as num_attachments FROM attachments");
    $num_attachments = $query ? (int)$db->fetch_field($query, 'num_attachments') : 0;

    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('attachmentthumbs', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;
    $end = $start + $per_page;
    
	
	$uploadspath_abs = TSDIR . '/uploads';
	
  

    $query = $db->sql_query_prepared(
        "SELECT * FROM attachments ORDER BY aid ASC LIMIT ?, ?",
        [$start, $per_page]
    );
    
    $imageExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'];
    $extToMime = ['gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    
    while ($query && ($attachment = $db->fetch_array($query))) {
        $ext = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $imageExtensions, true)) {
            $thumbname = str_replace(".attach", "_thumb.{$ext}", $attachment['attachname']);

            // Реальная сигнатура create_thumbnail(): (src, dst-ФАЙЛ, maxW,
            // maxH, mime) — не совпадает со старой generate_thumbnail(file,
            // path-ПАПКА, filename, maxHeight, maxWidth). Раньше здесь был
            // вызов с аргументами от старой функции, просто с заменённым
            // именем — $thumbname передавался туда, где ждут число (ширину),
            // а $attachthumbw — туда, где ждут MIME-строку.
            $created = create_thumbnail(
                $uploadspath_abs . "/" . $attachment['attachname'],
                $uploadspath_abs . "/" . $thumbname,
                (int)$attachthumbw,
                (int)$attachthumbh,
                $extToMime[$ext] ?? ''
            );

            // create_thumbnail() возвращает bool, а не массив с 'code'/
            // 'filename' (это был контракт старой generate_thumbnail()) —
            // обращение к $thumbnail['code'] на булевом значении молча не
            // работало бы как задумано.
            $thumbnailFilename = $created ? $thumbname : '';

            $db->sql_query_prepared(
                "UPDATE attachments SET thumbnail = ? WHERE aid = ?",
                [$thumbnailFilename, (int)$attachment['aid']]
            );
        }
    }

    $message = $lang->success_rebuilt_attachment_thumbnails ?? 'The attachment thumbnails have been rebuilt successfully';
    
    check_proceed(
        $num_attachments, 
        $end, 
        ++$page, 
        $per_page, 
        "attachmentthumbs", 
        "do_rebuildattachmentthumbs", 
        $message
    );
}








function acp_rebuild_comment_attachment_thumbnails(): void
{
    global $db, $mybb, $lang, $plugins, $attachthumbh, $attachthumbw;

    $plugins->run_hooks('admin_tools_recount_rebuild_comment_attachment_thumbs');

    $query = $db->sql_query_prepared("SELECT COUNT(aid) as num_attachments FROM attachments WHERE comment_id > 0");
    $num_attachments = $query ? (int)$db->fetch_field($query, 'num_attachments') : 0;

    $page     = $mybb->get_input('page', MyBB::INPUT_INT);
    $per_page = $mybb->get_input('commentattachmentthumbs', MyBB::INPUT_INT);
    $start    = ($page - 1) * $per_page;
    $end      = $start + $per_page;

    $uploadspath_abs = TSDIR . '/uploads/attachments';

   

    $query = $db->sql_query_prepared(
        "SELECT * FROM attachments WHERE comment_id > 0 ORDER BY aid ASC LIMIT ?, ?",
        [$start, $per_page]
    );

    $imageExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'];
    $extToMime = ['gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    while ($query && ($attachment = $db->fetch_array($query))) {
        $ext = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));

        if (in_array($ext, $imageExtensions, true)) {
            $thumbname = 'thumb_' . $attachment['attachname'];

            // Та же проблема, что и в форумной функции выше — аргументы
            // соответствовали старой generate_thumbnail(), не реальной
            // сигнатуре create_thumbnail() (src, dst-ФАЙЛ, maxW, maxH, mime).
            $created = create_thumbnail(
                $uploadspath_abs . '/' . $attachment['attachname'],
                $uploadspath_abs . '/' . $thumbname,
                (int)$attachthumbw,
                (int)$attachthumbh,
                $extToMime[$ext] ?? ''
            );

            // create_thumbnail() возвращает bool, не массив.
            $thumbnailFilename = $created ? $thumbname : '';

            $db->sql_query_prepared(
                "UPDATE attachments SET thumbnail = ? WHERE aid = ?",
                [$thumbnailFilename, (int)$attachment['aid']]
            );
        }
    }

    $message = $lang->success_rebuilt_comment_attachment_thumbnails ?? 'The comment attachment thumbnails have been rebuilt successfully';

    check_proceed(
        $num_attachments,
        $end,
        ++$page,
        $per_page,
        'commentattachmentthumbs',
        'do_rebuildcommentattachmentthumbs',
        $message
    );
}













if (!$mybb->input['action']) {
    $plugins->run_hooks("admin_tools_recount_rebuild_start");

    if ($mybb->request_method == "post") {
        require_once INC_PATH . "/functions_rebuild.php";

        // CSRF-проверка - токен уже выводился в формах (my_post_key), но
        // нигде не проверялся. Одна проверка тут закрывает сразу все
        // 12 мутирующих действий этого файла (foreach ниже + do_recountstats).
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            stderr('Security Error', 'Invalid security token. Please refresh the page and try again.');
        }

        $mybb->input['page'] = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
        $plugins->run_hooks("admin_tools_do_recount_rebuild");

        $actions = [
            'do_rebuildforumcounters' => [
                'hook' => "admin_tools_recount_rebuild_forum_counters",
                'input' => 'forumcounters',
                'default' => 50,
                'function' => 'acp_rebuild_forum_counters'
            ],
            'do_rebuildthreadcounters' => [
                'hook' => "admin_tools_recount_rebuild_thread_counters",
                'input' => 'threadcounters',
                'default' => 500,
                'function' => 'acp_rebuild_thread_counters'
            ],
            'do_recountuserposts' => [
                'hook' => "admin_tools_recount_rebuild_user_posts",
                'input' => 'userposts',
                'default' => 500,
                'function' => 'acp_recount_user_posts'
            ],
            'do_recountuserthreads' => [
                'hook' => "admin_tools_recount_rebuild_user_threads",
                'input' => 'userthreads',
                'default' => 500,
                'function' => 'acp_recount_user_threads'
            ],
            'do_rebuildattachmentthumbs' => [
                'hook' => "admin_tools_recount_rebuild_attachment_thumbs",
                'input' => 'attachmentthumbs',
                'default' => 20,
                'function' => 'acp_rebuild_attachment_thumbnails'
            ],
			
			'do_rebuildcommentattachmentthumbs' => [
    'hook'     => 'admin_tools_recount_rebuild_comment_attachment_thumbs',
    'input'    => 'commentattachmentthumbs',
    'default'  => 20,
    'function' => 'acp_rebuild_comment_attachment_thumbnails'
],
			
            'do_recountprivatemessages' => [
                'hook' => "admin_tools_recount_recount_private_messages",
                'input' => 'privatemessages',
                'default' => 500,
                'function' => 'acp_recount_private_messages'
            ],
            'do_comments' => [
                'hook' => "admin_tools_recount_recount_comments",
                'input' => 'comments',
                'default' => 500,
                'function' => 'acp_recount_user_comments'
            ],
            'do_rebuildpollcounters' => [
                'hook' => "admin_tools_recount_rebuild_poll_counters",
                'input' => 'pollcounters',
                'default' => 500,
                'function' => 'acp_rebuild_poll_counters'
            ],
            'do_recountthreadratings' => [
                'hook' => "admin_tools_recount_rebuild_thread_ratings",
                'input' => 'threadratings',
                'default' => 500,
                'function' => 'acp_recount_thread_ratings'
            ],
            'do_recounttorrentcomments' => [
                'hook' => "admin_tools_recount_rebuild_torrent_comments",
                'input' => 'torrentcomments',
                'default' => 500,
                'function' => 'acp_recount_torrent_comments'
            ]
        ];

        foreach ($actions as $action => $config) {
            if (isset($mybb->input[$action])) {
                $plugins->run_hooks($config['hook']);

                if ($mybb->input['page'] == 1) {
                    // Log admin action if needed
                }

                $per_page = $mybb->get_input($config['input'], MyBB::INPUT_INT);
                if (!$per_page || $per_page <= 0) {
                    $mybb->input[$config['input']] = $config['default'];
                }

                $config['function']();
                break;
            }
        }

        // Handle stats recount
        if (isset($mybb->input['do_recountstats'])) {
            $plugins->run_hooks("admin_tools_recount_rebuild_stats");
            $cache->update_stats();
            
            // Log admin action
            write_log('User ' . $CURUSER['username'] . ' Recounted and rebuilt statistics');
            
            flash_message('The forum statistics have been rebuilt successfully', 'success');
            admin_redirect("index.php?act=recount_rebuild");
        }
    }

    stdhead('Recount & Rebuild');
    rr_styles();

    $sections = [
        'forums' => ['fa-comments',      'ic-blue',   'Forums & threads'],
        'users'  => ['fa-users',         'ic-green',  'Users'],
        'media'  => ['fa-photo-film',    'ic-purple', 'Attachments'],
        'site'   => ['fa-magnet',        'ic-red',    'Torrents & statistics'],
    ];
    $tasks = rr_tasks();
    $key   = htmlspecialchars((string)$mybb->post_code, ENT_QUOTES);
    $self  = htmlspecialchars((string)$_this_script_, ENT_QUOTES);
?>
<div class="container mt-3 mb-4 rr">

    <div class="rr-card mb-3"><div class="rr-head">
        <span class="rr-head-icon ic-teal"><i class="fa-solid fa-arrows-rotate"></i></span>
        <div>
            <h1 class="rr-title">Recount &amp; Rebuild</h1>
            <div class="rr-sub">Fix counters and caches that drifted out of sync. Big jobs run in batches with a progress bar.</div>
        </div>
        <span class="ms-auto rr-muted"><i class="fa-solid fa-layer-group me-1"></i><?= count($tasks) ?> tools</span>
    </div></div>

<?php foreach ($sections as $sec => [$sicon, $scls, $stitle]): ?>
    <div class="rr-sec"><span class="rr-sec-icon <?= $scls ?>"><i class="fa-solid <?= $sicon ?>"></i></span><?= $stitle ?></div>
    <div class="row g-3">
    <?php foreach ($tasks as $action => [$tsec, $icon, $cls, $title, $desc, $input, $default]):
        if ($tsec !== $sec) continue; ?>
        <div class="col-md-6 col-xl-4">
            <!-- Отдельная форма на каждую задачу: раньше все инструменты были в ОДНОЙ форме,
                 и Enter в любом поле количества запускал первую кнопку — «Forum counters» -->
            <form action="<?= $self ?>" method="post" class="rr-card rr-task">
                <input type="hidden" name="my_post_key" value="<?= $key ?>">
                <div class="d-flex align-items-center gap-3">
                    <span class="rr-ico <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <h3><?= htmlspecialchars($title) ?></h3>
                </div>
                <p><?= htmlspecialchars($desc) ?></p>
                <div class="rr-run">
                    <?php if ($input !== ''): ?>
                    <div class="input-group input-group-sm" title="Items per batch">
                        <input type="number" class="form-control" name="<?= $input ?>" value="<?= (int)$default ?>" min="1" aria-label="Items per batch">
                        <span class="input-group-text">/ step</span>
                    </div>
                    <?php else: ?>
                    <span class="rr-onestep"><i class="fa-solid fa-bolt"></i>single step</span>
                    <?php endif; ?>
                    <button type="submit" name="<?= $action ?>" value="Go" class="btn btn-sm btn-primary px-3 ms-auto rr-go">
                        <i class="fa-solid fa-play me-1"></i>Run
                    </button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
    </div>
<?php endforeach; ?>

    <div class="rr-muted text-center mt-4"><i class="fa-solid fa-circle-info me-1"></i>Smaller batches are slower but safer on a busy server; thumbnails are the heaviest job.</div>
</div>
<script>
document.querySelectorAll('.rr .rr-task').forEach(f => f.addEventListener('submit', function () {
    const b = f.querySelector('.rr-go');
    // name/value кнопки нужно сохранить — после disabled браузер его не отправит
    const h = document.createElement('input'); h.type = 'hidden'; h.name = b.name; h.value = b.value; f.appendChild(h);
    b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting…';
}));
</script>
<?php
    stdfoot();

}