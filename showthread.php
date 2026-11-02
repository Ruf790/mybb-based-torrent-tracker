<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'showthread.php');
define("SCRIPTNAME", "showthread.php");
define('IN_FORUM', true);

require_once 'global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_indicators.php';
require_once INC_PATH . '/functions_forum_jump.php';


require_once INC_PATH . '/editor.php';
require_once 'cache/smilies.php';

$lang->load("showthread");

// ─── Resolve pid → tid ────────────────────────────────────────────────────────
// get_post() кеширует результат — ниже при переходе к посту он не уйдёт в БД повторно

if (!empty($mybb->input['pid']) && empty($mybb->input['tid'])) {
    $post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));
    if (empty($post)) {
        stderr($lang->showthread['invalid_post']);
    }
    $mybb->input['tid'] = $post['tid'];
}

// ─── Load thread ──────────────────────────────────────────────────────────────

$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
if (!$thread || str_starts_with((string)$thread['closed'], "moved|")) {
	stderr($lang->showthread['error_invalidthread'], '', 404, 'thread');
}

$reply_subject = $parser->parse_badwords($thread['subject']);
$thread['subject'] = htmlspecialchars_uni($reply_subject);
if (my_strlen($reply_subject) > 85) {
    $reply_subject = my_substr($reply_subject, 0, 82) . '...';
}
$reply_subject = htmlspecialchars_uni($reply_subject);

$tid = (int)$thread['tid'];
$fid = $thread['fid'];
$thread['username'] = htmlspecialchars_uni($thread['username'] ?: 'guest');
$forumpermissions = forum_permissions($thread['fid']);

// ─── Visible states ───────────────────────────────────────────────────────────

$usergroups = $mybb->usergroup ?? [];
$is_mod     = is_mod($usergroups);
$cur_uid    = (int)($CURUSER['id'] ?? 0);

// Мягкого удаления нет — посты удаляются из БД, visible = -1 не бывает
$visible_states = ["1"];
if ($is_mod) {
    $visible_states[] = "0";
}
$visible_condition = "visible IN (" . implode(',', $visible_states) . ")";

if ($cur_uid && $showownunapproved) {
    $own_unapproved  = ' AND (%1$s' . $visible_condition . ' OR (%1$svisible=0 AND %1$suid=' . $cur_uid . '))';
    $visibleonly     = sprintf($own_unapproved, null);
    $visibleonly_p   = sprintf($own_unapproved, 'p.');
} else {
    $visibleonly     = " AND " . $visible_condition;
    $visibleonly_p   = " AND p." . $visible_condition;
}

// ─── Visibility check ─────────────────────────────────────────────────────────

if (!$is_mod) {
    $ownUnapprovedAllowed = $thread['visible'] === 0
        && $cur_uid
        && !empty($showownunapproved)
        && $thread['uid'] === $cur_uid;
    if ($thread['visible'] != 1 && !$ownUnapprovedAllowed) {
        stderr($lang->showthread['error_invalidthread']);
    }
}

// ─── Permissions ──────────────────────────────────────────────────────────────

if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1) {
    print_no_permission();
}
if (
    isset($forumpermissions['canonlyviewownthreads']) &&
    $forumpermissions['canonlyviewownthreads'] == 1 &&
    $thread['uid'] !== $CURUSER['id']
) {
    print_no_permission();
}

$forum = get_forum($fid);
if (!$forum || $forum['type'] !== "f") {
    error('error_invalidforum');
}

$threadnoteslink = '';
check_forum_password($forum['fid']);

if (!$mybb->get_input('action')) {
    $mybb->input['action'] = "thread";
}

// ─── Action: newpost ──────────────────────────────────────────────────────────

if ($mybb->input['action'] === "newpost") {
    $lastread = $cutoff = 0;
    $query = $db->sql_query_prepared(
        "SELECT dateline FROM threadsread WHERE uid = ? AND tid = ?",
        [$cur_uid, $tid]
    );
    $thread_read = $db->fetch_field($query, "dateline");

    if ($threadreadcut > 0 && $cur_uid) {
        $query = $db->sql_query_prepared(
            "SELECT dateline FROM forumsread WHERE fid = ? AND uid = ?",
            [(int)$fid, $cur_uid]
        );
        $forum_read = $db->fetch_field($query, "dateline");
        $read_cutoff = TIMENOW - $threadreadcut * 86400;
        $forum_read = (!$forum_read || $forum_read < $read_cutoff) ? $read_cutoff : $forum_read;
    } else {
        $forum_read = (int)my_get_array_cookie("forumread", (string)$fid);
    }

    if ($threadreadcut > 0 && $cur_uid && $thread['lastpost'] > $forum_read) {
        $cutoff   = TIMENOW - $threadreadcut * 86400;
        $lastread = ($thread['lastpost'] > $cutoff) ? ($thread_read ?: 0) : 0;
    }

    if (!$lastread) {
        $readcookie = (int)my_get_array_cookie("threadread", (string)$tid);
        $lastread   = ($readcookie > $forum_read) ? $readcookie : $forum_read;
    }
    if ($cutoff && $lastread < $cutoff) {
        $lastread = $cutoff;
    }

    $lastread = (int)$lastread;
    $query = $db->sql_query_prepared(
        "SELECT pid FROM posts WHERE tid = ? AND dateline > ? {$visibleonly} ORDER BY dateline, pid LIMIT 1",
        [$tid, $lastread]
    );
    $newpost = $db->fetch_array($query);

    if ($newpost && $lastread) {
        $highlight = '';
        if ($mybb->get_input('highlight')) {
            $separator = $mybb->seo_support ? "?" : "&";
            $highlight = $separator . "highlight=" . $mybb->get_input('highlight');
        }
        header("Location: " . htmlspecialchars_decode(get_post_link($newpost['pid'], $tid)) . $highlight . "#pid{$newpost['pid']}");
        exit;
    } else {
        $mybb->input['action'] = "lastpost";
    }
}

// ─── Action: lastpost ─────────────────────────────────────────────────────────
// Перенесённые темы (moved|) отсекаются при загрузке, отдельная ветка для них не нужна

if ($mybb->input['action'] === "lastpost") {
    $query = $db->sql_query_prepared(
        "SELECT pid FROM posts WHERE tid = ? {$visibleonly} ORDER BY dateline DESC, pid DESC LIMIT 1",
        [$tid]
    );
    $pid = $db->fetch_field($query, "pid");
    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $tid)) . "#pid{$pid}");
    exit;
}

// ─── Action: nextnewest / nextoldest ──────────────────────────────────────────
// Тема и её последний пост — одним запросом

if ($mybb->input['action'] === "nextnewest" || $mybb->input['action'] === "nextoldest") {
    $newest = $mybb->input['action'] === "nextnewest";
    $cmp    = $newest ? '>' : '<';
    $dir    = $newest ? 'ASC' : 'DESC';

    $query = $db->sql_query_prepared(
        "SELECT tid,
                (SELECT p.pid FROM posts p WHERE p.tid = threads.tid
                 ORDER BY p.dateline DESC, p.pid DESC LIMIT 1) AS lastpid
         FROM threads
         WHERE fid = ? AND lastpost {$cmp} ? {$visibleonly} AND closed NOT LIKE 'moved|%'
         ORDER BY lastpost {$dir} LIMIT 1",
        [(int)$thread['fid'], (int)$thread['lastpost']]
    );
    $nextthread = $db->fetch_array($query);
    if (!$nextthread) {
        stderr($lang->showthread[$newest ? 'error_nonextnewest' : 'error_nonextoldest']);
    }
    $pid = $nextthread['lastpid'];
    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $nextthread['tid'])) . "#pid{$pid}");
    exit;
}

// ─── Breadcrumb ───────────────────────────────────────────────────────────────

$pid = $mybb->input['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
$breadcrumb_multipage = [];

if ($showforumpagesbreadcrumb) {
    $f_threadsperpage = (int)$f_threadsperpage > 0 ? (int)$f_threadsperpage : 20;

    // Кеш форумов счётчиков не хранит — если 'threads' нет, ниже сработает COUNT
    $threadcount = (int)($forum['threads'] ?? 0);

    $uid_only = '';
    if (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1) {
        $uid_only    = " AND uid = ?";
        $query       = $db->sql_query_prepared(
            "SELECT COUNT(tid) AS threads FROM threads WHERE fid = ? $visibleonly $uid_only",
            [(int)$fid, $cur_uid]
        );
        $threadcount = (int)$db->fetch_field($query, "threads");
    } elseif ($threadcount === 0) {
        $query       = $db->sql_query_prepared(
            "SELECT COUNT(tid) AS threads FROM threads WHERE fid = ? $visibleonly",
            [(int)$fid]
        );
        $threadcount = (int)$db->fetch_field($query, "threads");
    }

    $stickybit       = $thread['sticky'] == 1 ? " AND sticky=1" : " OR sticky=1";
    $position_params = $uid_only ? [(int)$fid, (int)$thread['lastpost'], $cur_uid] : [(int)$fid, (int)$thread['lastpost']];
    $query = $db->sql_query_prepared(
        "SELECT COUNT(tid) AS threads FROM threads
         WHERE fid = ? AND (lastpost >= ? {$stickybit}) {$visibleonly} {$uid_only}",
        $position_params
    );

    $thread_position = (int)$db->fetch_field($query, "threads");
    $thread_page     = (int)ceil($thread_position / $f_threadsperpage);
    $breadcrumb_multipage = ["num_threads" => $threadcount, "current_page" => $thread_page];
}

build_forum_breadcrumb($fid, $breadcrumb_multipage);
add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
$plugins->run_hooks("showthread_start");

// ─── Main thread view ─────────────────────────────────────────────────────────

if ($mybb->input['action'] !== "thread") {
    exit;
}

if ($thread['firstpost'] == 0 || $thread['dateline'] == 0) {
    update_first_post($tid);
}

// ─── Poll ─────────────────────────────────────────────────────────────────────

$pollbox = "";

if ($thread['poll']) {
    $query = $db->sql_query_prepared(
        "SELECT * FROM polls WHERE pid = ? LIMIT 1",
        [(int)$thread['poll']]
    );
    $poll  = $db->fetch_array($query);

    $poll['timeout'] = $poll['timeout'] * 86400;
    $expiretime      = $poll['dateline'] + $poll['timeout'];
    $now             = TIMENOW;

    $showresults = (
        $poll['closed'] == 1 ||
        $thread['closed'] == 1 ||
        ($expiretime < $now && $poll['timeout'] > 0)
    ) ? 1 : 0;

    if ($cur_uid) {
        $user_check        = "uid = ?";
        $user_check_params = [$cur_uid];
    } else {
        $user_check        = "uid = 0 AND ipaddress = ?";
        $user_check_params = [$session->packedip];
    }

    $alreadyvoted = 0;
    $votedfor     = [];
    $query = $db->sql_query_prepared(
        "SELECT voteoption FROM pollvotes WHERE {$user_check} AND pid = ?",
        array_merge($user_check_params, [(int)$poll['pid']])
    );
    while ($votecheck = $db->fetch_array($query)) {
        $alreadyvoted = 1;
        $votedfor[$votecheck['voteoption']] = 1;
    }

    $optionsarray = explode("||~|~||", $poll['options']);
    $votesarray   = explode("||~|~||", $poll['votes']);
    $poll['question'] = htmlspecialchars_uni($poll['question']);
    $polloptions  = '';
    $totalvotes   = 0;
    $poll['totvotes'] = 0;
    for ($i = 1; $i <= $poll['numoptions']; ++$i) {
        $poll['totvotes'] += (int)($votesarray[$i - 1] ?? 0);
    }

    $max_votes = $poll['totvotes'] > 0 ? max(array_map('intval', $votesarray)) : 0;

    $parser_options = [
        "allow_html" => 0, "allow_mycode" => 1, "allow_smilies" => 1,
        "allow_imgcode" => 1, "allow_videocode" => 1, "filter_badwords" => 1,
    ];

    for ($i = 1; $i <= $poll['numoptions']; ++$i) {
        $option     = $parser->parse_message($optionsarray[$i - 1], $parser_options);
        $votes      = (int)($votesarray[$i - 1] ?? 0);
        $totalvotes += $votes;
        $number     = $i;
        $votestar   = !empty($votedfor[$number]) ? ' <span class="poll-voted-star">✓</span>' : '';

        if ($alreadyvoted || $showresults) {
            $percent        = $poll['totvotes'] > 0 ? number_format($votes / $poll['totvotes'] * 100, 2) : "0";
            $is_leading     = ($votes > 0 && $votes === $max_votes);
            $bar_class      = $is_leading ? 'poll-bar-leading' : 'poll-bar-normal';
            $pct_label      = (float)$percent > 8 ? $percent . '%' : '';

            $polloptions .= <<<HTML
            <div class="poll-option">
                <div class="poll-option-header">
                    <span class="poll-option-label">{$option}{$votestar}</span>
                    <span class="poll-option-count">{$votes} <span class="poll-option-pct">({$percent}%)</span></span>
                </div>
                <div class="poll-bar-wrap">
                    <div class="poll-bar {$bar_class}" style="width:{$percent}%" role="progressbar"
                         aria-valuenow="{$percent}" aria-valuemin="0" aria-valuemax="100">
                        <span class="poll-bar-label">{$pct_label}</span>
                    </div>
                </div>
            </div>
            HTML;
        } else {
            $input_type = $poll['multiple'] == 1 ? 'checkbox' : 'radio';
            $input_name = $poll['multiple'] == 1 ? "option[{$number}]" : "option";
            $input_val  = $poll['multiple'] == 1 ? "1" : "{$number}";

            $polloptions .= <<<HTML
            <div class="poll-option poll-option-vote">
                <label class="poll-vote-label">
                    <input type="{$input_type}" class="form-check-input poll-input"
                           name="{$input_name}" id="option_{$number}" value="{$input_val}" />
                    <span>{$option}</span>
                </label>
            </div>
            HTML;
        }
    }

    $totpct  = $poll['totvotes'] ? "100%" : "0%";
    $edit_poll = '';
    if ($is_mod) {
        $edit_poll = '<a href="polls.php?action=editpoll&amp;pid=' . $poll['pid'] . '" class="poll-edit-link"><i class="fa-solid fa-pencil"></i> Edit poll</a>';
    }

    $showresults_link = '<a href="polls.php?action=showresults&amp;pid=' . $poll['pid'] . '" class="poll-results-link"><i class="fa-solid fa-square-poll-horizontal"></i> Show Results</a>';

    if ($alreadyvoted || $showresults) {
        $pollstatus = $alreadyvoted ? '<span class="poll-status-voted"><i class="fa-solid fa-check"></i> You have already voted</span>' : '';
        $undovote   = $alreadyvoted
            ? '<a href="polls.php?action=do_undovote&amp;pid=' . $poll['pid'] . '&amp;my_post_key=' . $mybb->post_code . '" class="poll-undo-link"><i class="fa-solid fa-rotate-left"></i> Undo vote</a>'
            : '';
        $total_votes = $totalvotes . ' vote' . ($totalvotes != 1 ? 's' : '');

        $pollbox = render_poll_results_box($poll, $polloptions, $pollstatus, $undovote, $total_votes, $edit_poll, $showresults_link);
        $plugins->run_hooks("showthread_poll_results");
    } else {
        $closeon    = '';
        $publicnote = '';
        if ($poll['timeout'] != 0) {
            $closeon = '<span class="poll-closes"><i class="fa-regular fa-clock"></i> Closes: ' . my_datee($dateformat, $expiretime) . '</span>';
        }
        if ($poll['public'] == 1) {
            $publicnote = '<span class="poll-public-note"><i class="fa-solid fa-eye"></i> Public poll — your vote will be visible</span>';
        }

        $pollbox = render_poll_vote_box($poll, $polloptions, $publicnote, $closeon, $edit_poll, $showresults_link, $mybb->post_code);
        $plugins->run_hooks("showthread_poll");
    }
}

// ─── Navigation ───────────────────────────────────────────────────────────────

$next_oldest_link = get_thread_link($tid, 0, "nextoldest");
$next_newest_link = get_thread_link($tid, 0, "nextnewest");

mark_thread_read($tid, (int)$fid);

// ─── New thread / reply buttons ───────────────────────────────────────────────

$newthread = $newreply = '';
if ($forum['open'] != 0 && $forum['type'] === "f") {
    if ($forumpermissions['canpostthreads'] != 0) {
        $newthread = '<a href="newthread.php?fid=' . $fid . '" class="btn btn-outline-primary st-pill"><i class="fa-solid fa-plus me-1"></i>' . $lang->showthread['post_thread'] . '</a>';
    }
    $canReply = $forumpermissions['canpostreplys'] != 0
        && ($thread['closed'] != 1 || $is_mod)
        && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']));

    if ($canReply) {
        $newreply = '<a href="newreply.php?tid=' . $tid . '" class="btn btn-primary st-pill"><i class="fa-solid fa-reply me-1"></i>' . $lang->showthread['post_reply'] . '</a>';
    } elseif ($thread['closed'] == 1) {
        $closed_txt = $lang->showthread['thread_closed'];
        $newreply = '<span class="d-inline-block" tabindex="0" data-bs-toggle="popover" title="' . $thread['subject'] . '" data-bs-content="' . $closed_txt . '"><button class="btn btn-secondary st-pill" type="button" disabled><i class="fa-solid fa-lock me-1"></i>' . $closed_txt . '</button></span>';
    }
}

// ─── Moderation tools ─────────────────────────────────────────────────────────

$closeoption = $inlinemod = $moderationoptions = '';
$inlinecount = "0";

if ($is_mod) {
    $closelinkch = $thread['closed'] == 1 ? ' checked="checked"' : '';
    $stickch     = $thread['sticky']  ? ' checked="checked"' : '';
    $closeoption  = '<label class="st-check"><input type="checkbox" class="form-check-input" name="modoptions[closethread]" value="1"' . $closelinkch . ' /> <span><i class="fa-solid fa-lock me-1"></i>' . $lang->showthread['close_thread'] . '</span></label>';
    $closeoption .= '<label class="st-check"><input type="checkbox" class="form-check-input" name="modoptions[stickthread]" value="1"' . $stickch . ' /> <span><i class="fa-solid fa-thumbtack me-1"></i>' . $lang->showthread['stick_thread'] . '</span></label>';
    $inlinecookie = "inlinemod_thread" . $tid;
    $plugins->run_hooks("showthread_ismod");
} else {
    $modoptions = "&nbsp;";
}

// ─── Thread views ─────────────────────────────────────────────────────────────

$threadviews_countthreadauthor = "1";
$threadviews_countspiders      = "0";
$threadviews_countguests       = "1";

$shouldCount = match (true) {
    $cur_uid == 0 && $session->is_spider && $threadviews_countspiders == 1 => true,
    $cur_uid == 0 && !$session->is_spider && $threadviews_countguests == 1 => true,
    $cur_uid != 0 && ($threadviews_countthreadauthor == 1 || $CURUSER['id'] !== $thread['uid']) => true,
    default => false,
};
if ($shouldCount) {
    if ($delayedthreadviews == 1) {
        $db->sql_query_prepared("INSERT INTO threadviews (tid) VALUES(?)", [$tid]);
    } else {
        $db->sql_query_prepared("UPDATE threads SET views = views + 1 WHERE tid = ?", [$tid]);
    }
    ++$thread['views'];
}

// ─── Search thread ────────────────────────────────────────────────────────────

$search_thread = '';
if ($forumpermissions['cansearch'] != 0) {
    $search_thread = <<<HTML
    <form action="search.php" method="post" class="th-search" role="search">
        <input type="hidden" name="action" value="thread" />
        <input type="hidden" name="tid" value="{$thread['tid']}" />
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="keywords" class="form-control" size="25" placeholder="{$lang->showthread['search_thread']}" aria-label="{$lang->showthread['search_thread']}" />
            <button type="submit" class="btn btn-primary">{$lang->showthread['search_thread']}</button>
        </div>
    </form>
    HTML;
}

// ─── Ignored users ────────────────────────────────────────────────────────────

$ignored_users = [];
if ($cur_uid > 0 && !empty($CURUSER['ignorelist'])) {
    foreach (explode(',', $CURUSER['ignorelist']) as $uid) {
        $ignored_users[(int)$uid] = 1;
    }
}

// ─── Pagination ───────────────────────────────────────────────────────────────

$defaultmode    = 'linear';
$mybb->input['mode'] ??= $defaultmode;
$f_postsperpage = (int)$f_postsperpage > 0 ? (int)$f_postsperpage : 20;
$page    = 1;
$perpage = $f_postsperpage;

if ($mybb->get_input('page', MyBB::INPUT_INT) && $mybb->get_input('page') !== "last") {
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
}

$invalid_post_notice = '';
if (!empty($mybb->input['pid'])) {
    $post = get_post($mybb->input['pid']);
    $postVisible = !empty($post) && (int)$post['tid'] === $tid && !(
        $post['visible'] == 0 && !($is_mod || ($cur_uid && $post['uid'] == $cur_uid && $showownunapproved))
    );
    if (!$postVisible) {
        $invalid_post_notice = (string)$lang->global['error_invalidpost'];
    } else {
        // Позиция поста с учётом сортировки dateline, pid (индекс tid_dateline_pid)
        $query  = $db->sql_query_prepared(
            "SELECT COUNT(*) AS count FROM posts p
             WHERE p.tid = ? AND (p.dateline < ? OR (p.dateline = ? AND p.pid <= ?)) {$visibleonly_p}",
            [$tid, (int)$post['dateline'], (int)$post['dateline'], (int)$post['pid']]
        );
        $result = (int)$db->fetch_field($query, "count");
        $page   = max(1, (int)ceil($result / $perpage));
    }
}

// ─── Счётчик постов из кешированных колонок threads ───────────────────────────
// Раньше здесь был COUNT(*) на каждый просмотр, а у модераторов — ещё и
// rebuild_thread_counters() при любом неодобренном посте.

$replies = (int)$thread['replies'];
if ($is_mod) {
    $replies += (int)$thread['unapprovedposts'];
} elseif ($cur_uid && $showownunapproved && (int)$thread['unapprovedposts'] > 0) {
    // Единственный случай, который счётчики не покрывают: свои неодобренные посты
    $query   = $db->sql_query_prepared(
        "SELECT COUNT(*) AS c FROM posts p WHERE p.tid = ? {$visibleonly_p}",
        [$tid]
    );
    $replies = (int)$db->fetch_field($query, 'c') - 1;
}
$thread['replies'] = max(0, $replies);

$postcount = $thread['replies'] + 1;
$pages     = max(1, (int)ceil($postcount / $perpage));
if ($mybb->get_input('page') === "last") {
    $page = $pages;
}
$page  = ($page > $pages || $page <= 0) ? 1 : $page;
$start = ($page - 1) * $perpage;

$highlight  = "";
if ($mybb->seo_support) {
    if ($mybb->get_input('highlight')) {
        $highlight = "?highlight=" . urlencode($mybb->get_input('highlight'));
    }
} else {
    if (!empty($mybb->input['highlight'])) {
        if (is_array($mybb->input['highlight'])) {
            foreach ($mybb->input['highlight'] as $word) {
                $highlight .= "&amp;highlight[]=" . urlencode($word);
            }
        } else {
            $highlight = "&amp;highlight=" . urlencode($mybb->get_input('highlight'));
        }
    }
}

$multipage = multipage($postcount, $perpage, $page, str_replace("{tid}", (string)$tid, THREAD_URL_PAGED . $highlight));

// ─── Fetch & build posts ──────────────────────────────────────────────────────
// Один запрос вместо двух (pid-список + IN): индекс tid_dateline_pid отдаёт
// строки сразу в нужном порядке и обрывается на LIMIT.

$post_rows = [];
$pid_list  = [];
$query = $db->sql_query_prepared(
    "SELECT u.*, u.username AS userusername, p.*, eu.username AS editusername
     FROM posts p
     LEFT JOIN users u  ON (u.id = p.uid)
     LEFT JOIN users eu ON (eu.id = p.edituid)
     WHERE p.tid = ? {$visibleonly_p}
     ORDER BY p.dateline, p.pid
     LIMIT {$start}, {$perpage}",
    [$tid]
);
while ($row = $db->fetch_array($query)) {
    $post_rows[] = $row;
    $pid_list[]  = (int)$row['pid'];
}
if (!$pid_list) {
    stderr($lang->showthread['error_invalidthread']);
}
if (empty($pid)) {
    $pid = $pid_list[0];
}

$attachcache = [];
if ($thread['attachmentcount'] > 0) {
    $pid_placeholders = implode(',', array_fill(0, count($pid_list), '?'));
    $query = $db->sql_query_prepared(
        "SELECT * FROM attachments WHERE pid IN ({$pid_placeholders})",
        $pid_list
    );
    while ($attachment = $db->fetch_array($query)) {
        $attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
    }
}

// Пакетная предзагрузка [torrent=ID] по всем постам страницы разом — тот же
// паттерн, что уже применён в commenttable.php для комментариев. Без этого
// каждый пост со своим уникальным торрентом бьёт в БД отдельным запросом.
if (isset($parser) && method_exists($parser, 'primeTorrentEmbedCache')) {
    $torrentEmbedIds = [];
    foreach ($post_rows as $row) {
        // И сообщение, и подпись проходят через parse_message()
        $embedText = ($row['message'] ?? '') . "\n" . ($row['signature'] ?? '');
        if (preg_match_all('#\[torrent=(\d+)\]#i', $embedText, $m)) {
            foreach ($m[1] as $foundTorrentId) {
                $torrentEmbedIds[] = (int)$foundTorrentId;
            }
        }
    }
    if (!empty($torrentEmbedIds)) {
        $parser->primeTorrentEmbedCache(array_values(array_unique($torrentEmbedIds)));
    }
}

$posts = '';
foreach ($post_rows as $post) {
    if ($thread['firstpost'] == $post['pid'] && $thread['visible'] == 0) {
        $post['visible'] = 0;
    }
    $posts .= build_postbit($post);
    $post   = '';
}
unset($post_rows);

$plugins->run_hooks("showthread_linear");

// ─── Quick reply ──────────────────────────────────────────────────────────────

$quickreply    = '';
$canQuickReply = $forumpermissions['canpostreplys'] != 0
    && ($thread['closed'] != 1 || $is_mod)
    && $forum['open'] != 0
    && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']));

if ($canQuickReply) {
    // На последней странице последний pid уже есть в $pid_list
    if ($page === $pages) {
        $last_pid = max($pid_list);
    } else {
        $query    = $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ? ORDER BY pid DESC LIMIT 1", [$tid]);
        $last_pid = (int)$db->fetch_field($query, "pid");
    }
    $posthash = md5($CURUSER['id'] . random_str());

    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_notice = '<div class="red_alert">' . $lang->showthread['moderation_user_posts'] . '</div>';
    }

    $quickreply = render_quick_reply($tid, $reply_subject, (int)$last_pid, $posthash, $page, $CURUSER, $mybb->post_code, $closeoption, $moderation_notice, $lang, $BASEURL, $smilies ?? []);
}

// ─── Moderation options ───────────────────────────────────────────────────────

$threadnotesbox = '';
$customthreadtools = $customposttools = '';

if ((int)($usergroups['canstaffpanel'] ?? 0) === 1) {
    $inlinemoddelete  = '<option value="multideleteposts">'.$lang->showthread['inline_delete_posts'].'</option>';
    $inlinemodmanage  = '<option value="multimergeposts">' . $lang->showthread["mergeposts"] . '</option>'
        . '<option value="multisplitposts">' . $lang->showthread['inline_split_posts'] . '</option>'
        . '<option value="multimoveposts">' . $lang->showthread['inline_move_posts'] . '</option>';
    $inlinemodapprove = '<option value="multiapproveposts">' . $lang->showthread['inline_approve_posts'] . '</option>'
        . '<option value="multiunapproveposts">' . $lang->showthread['inline_unapprove_posts'] . '</option>';

    $standardposttools = '<optgroup label="Standard Tools">' . $inlinemoddelete . $inlinemodmanage . $inlinemodapprove . '</optgroup>';

    $openclosethread        = '<option class="option_mirage" value="openclosethread">'.$lang->showthread['open_close_thread'].'</option>';
    $stickunstickthread     = '<option class="option_mirage" value="stick">'.$lang->showthread['stick_unstick_thread'].'</option>';
    $deletethread           = '<option value="deletethread">'.$lang->showthread['delete_thread'].'</option>';
    $managethread           = '<option class="option_mirage" value="move">'.$lang->showthread['move_thread'].'</option><option class="option_mirage" value="merge">'.$lang->showthread['merge_threads'].'</option><option class="option_mirage" value="split">'.$lang->showthread['split_thread'].'</option>';
    $adminpolloptions       = '';
    $softdeletethread       = '';

    if ($pollbox && $is_mod) {
        $adminpolloptions = '<option class="option_mirage" value="deletepoll">' . $lang->showthread['delete_poll'] . '</option>';
    }
    $approveunapprovethread = $thread['visible'] == 0
        ? '<option class="option_mirage" value="approvethread">' . $lang->showthread['approve_thread'] . '</option>'
        : '<option class="option_mirage" value="unapprovethread">' . $lang->showthread['unapprove_thread'] . '</option>';

    $standardthreadtools = '<optgroup label="' . $lang->showthread['standard_mod_tools'] . '">'
        . $openclosethread . $softdeletethread . $deletethread
        . $adminpolloptions . $stickunstickthread . $managethread . $approveunapprovethread
        . '</optgroup>';

    // Список форумов нужен только в модалке переноса — строим только для стаффа
    $forumjump = build_forum_jump("", '', 1, '', 0, true, '', "moveto");

    $inlinemod         = render_inline_mod($tid, $standardposttools, $customposttools, (string)$inlinecount, $mybb->post_code, $lang, $BASEURL, $thread['replies'] + 1);
    $moderationoptions = render_moderation_options($tid, $standardthreadtools, $customthreadtools, $inlinemod, $mybb->post_code, $lang, $thread, $forumjump, $SITENAME);
}

// ─── Extra links ──────────────────────────────────────────────────────────────

$printthread = '<a href="printthread.php?tid=' . $tid . '" class="st-icon-btn" title="' . $lang->showthread['view_printable'] . '"><i class="fa-solid fa-print"></i></a>';
$sendthread  = ($usergroups['cansendemail'] == 1)
    ? '<a href="sendthread.php?tid=' . $tid . '" class="st-icon-btn" title="' . $lang->showthread['send_thread'] . '"><i class="fa-solid fa-paper-plane"></i></a>'
    : '';

$addpoll       = '';
$polltimelimit = "12";
if (
    !$thread['poll'] &&
    ($thread['uid'] == $CURUSER['id'] || $is_mod) &&
    $forum['open'] != 0 &&
    $thread['closed'] != 1 &&
    ($thread['dateline'] > (TIMENOW - ((int)$polltimelimit * 3600)) || $polltimelimit == 0)
) {
    $addpoll = '<a href="polls.php?action=newpoll&amp;tid=' . $tid . '" class="st-icon-btn" title="' . $lang->showthread['add_poll_to_thread'] . '"><i class="fa-solid fa-square-poll-vertical"></i></a>';
}

// ─── Состояние пользователя: подписка + его оценка — одним запросом ───────────

$user_state = ['subscribed' => 0, 'rating' => 0];
if ($cur_uid) {
    $query = $db->sql_query_prepared(
        "SELECT
            EXISTS(SELECT 1 FROM threadsubscriptions WHERE tid = ? AND uid = ?) AS subscribed,
            COALESCE((SELECT rating FROM threadratings WHERE tid = ? AND user_id = ? LIMIT 1), 0) AS rating",
        [$tid, $cur_uid, $tid, $cur_uid]
    );
    $user_state = $db->fetch_array($query) ?: $user_state;
}

// ─── Subscription ─────────────────────────────────────────────────────────────

$addremovesubscription = '';
if ($cur_uid) {
    $is_subscribed = (int)$user_state['subscribed'] === 1;

    $add_remove_subscription      = $is_subscribed ? 'remove' : 'add';
    $add_remove_subscription_text = $is_subscribed
        ? $lang->showthread['unsubscribe_thread']
        : $lang->showthread['subscribe_thread'];

    $addremovesubscription = '<a class="btn ' . ($is_subscribed ? 'btn-outline-secondary' : 'btn-outline-primary') . ' st-pill" href="usercp.php?action=' . $add_remove_subscription . 'subscription&amp;tid=' . $tid . '&amp;my_post_key=' . $mybb->post_code . '">'
        . '<i class="fa-' . ($is_subscribed ? 'solid fa-bell-slash' : 'regular fa-bell') . ' me-1"></i>' . $add_remove_subscription_text . '</a>';
}


// ─── Users browsing ───────────────────────────────────────────────────────────
$usersbrowsing = '';
if ($browsingthisthread != 0) {
    $timecut     = TIMENOW - $wolcutoffmins;
    $guestcount  = 0;
    $membercount = 0;
    $inviscount  = 0;
    $onlinemembers = '';
    $doneusers   = [];
    $guest_ips   = [];

    // Один запрос вместо двух — гости и члены вместе
    $query = $db->sql_query_prepared("
        SELECT s.ip, s.uid, s.time, u.username, u.invisible, u.usergroup
        FROM sessions s
        LEFT JOIN users u ON (s.uid = u.id)
        WHERE s.time > ? AND s.location2 = ? AND s.nopermission != 1
        ORDER BY u.username ASC, s.time DESC
    ", [(int)$timecut, $tid]);
    while ($user = $db->fetch_array($query)) {
        if ($user['uid'] == 0) {
            // Гость — считаем уникальные IP
            $guest_ips[$user['ip']] = true;
            continue;
        }
        if (empty($doneusers[$user['uid']]) || $doneusers[$user['uid']] < $user['time']) {
            ++$membercount;
            $doneusers[$user['uid']] = $user['time'];
            $invisiblemark = '';
            if ($user['invisible'] == 1) {
                $invisiblemark = "*";
                ++$inviscount;
            }
            if ($user['invisible'] != 1 || $usergroups['canviewwolinvis'] == 1 || $user['uid'] == $CURUSER['id']) {
                $user['profilelink'] = get_profile_link($user['uid']);
                $user['username'] = format_name(htmlspecialchars_uni($user['username']), (int)($user['usergroup'] ?? 1));
                $user['reading']     = my_datee($timeformat, $user['time']);
                $onlinemembers      .= '<a class="th-user" href="' . $user['profilelink'] . '" title="' . $lang->showthread['users_browsing_thread_reading'] . ' (' . $user['reading'] . ')">' . $user['username'] . ($invisiblemark !== '' ? '<span class="th-user__inv">*</span>' : '') . '</a>';
            }
        }
    }
    $guestcount = count($guest_ips); // DISTINCT ip в PHP

    $guestsonline = $guestcount ? sprintf($lang->showthread['users_browsing_thread_guests'], $guestcount) : '';
    $invisonline  = '';
    if ($CURUSER['invisible'] == 1) {
        --$inviscount;
    }
    if ($inviscount && $usergroups['canviewwolinvis'] != 1) {
        $invisonline = sprintf($lang->showthread['users_browsing_thread_invis'], $inviscount);
    }
    $browsing_extra = implode(', ', array_filter([$invisonline, $guestsonline]));
    $browsing_extra = $browsing_extra !== '' ? '<span class="th-browse__extra">' . $browsing_extra . '</span>' : '';
    $usersbrowsing = <<<HTML
    <div class="st-card th-browse">
        <div class="st-card__head">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-eye"></i></span>
            <span>{$lang->showthread['users_browsing_thread']}</span>
        </div>
        <div class="st-card__body th-browse__list">{$onlinemembers}{$browsing_extra}</div>
    </div>
    HTML;
}


$plugins->run_hooks("showthread_end");

$test           = get_forum_link($fid);

// ─── Render ───────────────────────────────────────────────────────────────────

stdhead($thread['subject']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=4">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/showthread.css?ver=41">';
echo '<script src="' . $BASEURL . '/scripts/ignor.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/showthread.js?ver=8"></script>';


$ags_js_lang = [];
foreach ($lang->showthread as $ags_key => $ags_val) {
    if (str_starts_with((string)$ags_key, 'js_')) {
        $ags_js_lang[substr((string)$ags_key, 3)] = (string)$ags_val;
    }
}
?>
<script>
const AGS_LANG = <?= json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/thread.js?ver=231"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js?ver=233"></script>';

echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/edit_delete_post.js?ver=7"></script>';

echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/report_post.js"></script>';

?>
<script>
var quickdelete_confirm  = "Are you sure you want to delete this post?";
var allowEditReason      = "1";
var visible_replies      = "<?= $thread['replies'] ?>";
var forumBaseUrl         = "<?= $test ?>";
lang.save_changes              = "Save Changes";
lang.cancel_edit               = "Cancel Edit";
lang.quick_edit_update_error   = "There was an error editing your reply:";
lang.quick_reply_post_error    = "There was an error posting your reply:";
lang.quick_delete_error        = "There was an error deleting your reply:";
lang.quick_delete_success      = "The post was deleted successfully.";
lang.quick_delete_thread_success = "The thread was deleted successfully.";
lang.editreason                = "Edit Reason";
lang.post_deleted_error        = "You can not perform this action to a deleted post.";
</script>
<?php
if ($invalid_post_notice !== '') {
    // Раньше уходило в $footer + $.jGrowl — ни то, ни другое на сайте не выводится/не подключено
    echo '<script>document.addEventListener("DOMContentLoaded",function(){if(typeof showToast==="function"){showToast('
        . json_encode($invalid_post_notice, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ',"error");}});</script>';
}

build_breadcrumb();

// ── Rating block ─────────────────────────────────────────────────────────────

$rating_data = [
    'avg'         => $thread['numratings'] > 0
                     ? round($thread['totalratings'] / $thread['numratings'], 1)
                     : 0,
    'count'       => (int)($thread['numratings'] ?? 0),
    'user_rating' => (int)$user_state['rating'],   // уже получено в запросе состояния пользователя
];

// Avg stars
$avg_stars_html = '';
for ($i = 1; $i <= 10; $i++) {
    $filled = $rating_data['avg'] >= $i;
    $half   = !$filled && $rating_data['avg'] >= $i - 0.5;
    if ($filled)       $avg_stars_html .= '<i class="bi bi-star-fill rating-star-filled"></i>';
    elseif ($half)     $avg_stars_html .= '<i class="bi bi-star-half rating-star-filled"></i>';
    else               $avg_stars_html .= '<i class="bi bi-star rating-star-empty"></i>';
}

// User stars (rating.js: .user-star, #user-stars, #rating-hint, .rating-score, #rating-display)
if ($cur_uid) {
    $user_stars_html = '';
    for ($i = 1; $i <= 10; $i++) {
        $active           = $rating_data['user_rating'] >= $i ? ' active' : '';
        $user_stars_html .= '<i class="bi bi-star-fill user-star' . $active . '" data-value="' . $i . '" onclick="rateThread(' . $i . ')"></i>';
    }
    $user_section = '
        <div class="th-rate__mine">
            <span class="th-rate__lbl">' . rtrim($lang->showthread['rate_thread'], ': ') . '</span>
            <div class="user-stars" id="user-stars">' . $user_stars_html . '</div>
            <span class="st-badge st-soft-muted" id="rating-hint">'
                . ($rating_data['user_rating'] ? $rating_data['user_rating'] . '/10' : 'rate')
            . '</span>
        </div>';
} else {
    $user_section = '<a href="' . $BASEURL . '/member.php?action=login" class="btn btn-sm btn-outline-primary st-pill"><i class="fa-solid fa-right-to-bracket me-1"></i>Login to rate</a>';
}

$rating_html = '
<div class="st-card th-rate">
    <span class="st-ico st-soft-warning th-rate__ico"><i class="fa-solid fa-star"></i></span>
    <div class="th-rate__avg">
        <div class="th-rate__score"><span class="rating-score">' . ($rating_data['count'] > 0 ? $rating_data['avg'] : '—') . '</span><small>/10</small></div>
        <div class="th-rate__votes">' . number_format($rating_data['count']) . ' votes</div>
    </div>
    <div class="rating-stars th-rate__stars" id="rating-display">' . $avg_stars_html . '</div>
    ' . $user_section . '
</div>
<script src="' . $BASEURL . '/scripts/rating.js?ver=31"></script>
<script>ratingInit(' . (int)$rating_data['user_rating'] . ', ' . $tid . ', "' . $BASEURL . '", "thread", "' . htmlspecialchars((string)$mybb->post_code, ENT_QUOTES) . '");</script>';


// ── Thread header ────────────────────────────────────────────────────────────

$thread_head = render_thread_head($thread, $forum, $test, $lang);

echo render_showthread($thread, $thread_head, $multipage, $addremovesubscription, $newreply, $pollbox, $rating_html, $posts, $quickreply, $search_thread, $next_oldest_link, $next_newest_link, $addpoll, $printthread, $sendthread, $moderationoptions, $usersbrowsing, $lang, $BASEURL, $tid, render_report_modal($cur_uid));
stdfoot();

// ─── Template functions ───────────────────────────────────────────────────────

/** Шапка темы: иконка по состоянию, заголовок, бейджи, мета (автор, форум, дата, ответы, просмотры) */
function render_thread_head(array $thread, array $forum, string $forum_link, object $lang): string
{
    $closed = (int)$thread['closed'] === 1;
    $sticky = (int)$thread['sticky'] === 1;
    $unappr = (int)$thread['visible'] === 0;

    [$soft, $icon] = match (true) {
        $closed => ['st-soft-danger', 'fa-lock'],
        $sticky => ['st-soft-warning', 'fa-thumbtack'],
        default => ['st-soft-primary', 'fa-comments'],
    };

    $badges = '';
    if ($sticky) {
        $badges .= '<span class="st-badge st-soft-warning"><i class="fa-solid fa-thumbtack"></i>' . ($lang->showthread['th_sticky'] ?? 'Sticky') . '</span>';
    }
    if ($closed) {
        $badges .= '<span class="st-badge st-soft-danger"><i class="fa-solid fa-lock"></i>' . $lang->showthread['thread_closed'] . '</span>';
    }
    if ($unappr) {
        $badges .= '<span class="st-badge st-soft-warning"><i class="fa-solid fa-hourglass-half"></i>' . $lang->global['postbit_thread_unapproved'] . '</span>';
    }

    $author = (int)$thread['uid'] > 0
        ? '<a href="' . get_profile_link((int)$thread['uid']) . '">' . $thread['username'] . '</a>'
        : $thread['username'];

    $meta = th_meta('fa-user', $author)
          . th_meta('fa-folder-open', '<a href="' . $forum_link . '">' . htmlspecialchars_uni((string)$forum['name']) . '</a>')
          . th_meta('fa-clock', my_datee('relative', (int)$thread['dateline']))
          . th_meta('fa-comment', ts_nf((int)$thread['replies']) . ' ' . $lang->showthread['replies'])
          . th_meta('fa-eye', ts_nf((int)$thread['views']) . ' ' . $lang->showthread['views']);

    return <<<HTML
    <div class="st-card st-head th-head">
        <span class="st-head__icon {$soft}"><i class="fa-solid {$icon}"></i></span>
        <div class="min-w-0 flex-grow-1">
            <div class="th-head__top">
                <h1 class="st-title">{$thread['subject']}</h1>
                {$badges}
            </div>
            <div class="st-head__meta">{$meta}</div>
        </div>
    </div>
    HTML;
}

function th_meta(string $icon, string $html): string
{
    return '<span><i class="fa-solid ' . $icon . '"></i>' . $html . '</span>';
}

/** Общая карточка опроса (результаты и голосование) */
function render_poll_card(array $poll, string $meta, string $body, string $foot_left, string $foot_right): string
{
    $meta = trim($meta) !== '' ? '<div class="poll-meta">' . $meta . '</div>' : '';

    return <<<HTML
    <div class="poll-box st-card th-poll">
        <div class="st-card__head poll-header">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-square-poll-horizontal"></i></span>
            <span class="poll-question min-w-0">{$poll['question']}</span>
            <span class="st-badge st-soft-info ms-auto poll-badge">Poll</span>
        </div>
        <div class="st-card__body">
            {$meta}
            {$body}
        </div>
        <div class="poll-footer th-poll__foot">
            <div class="poll-footer-left">{$foot_left}</div>
            <div class="poll-footer-right">{$foot_right}</div>
        </div>
    </div>
    HTML;
}

function render_poll_results_box(array $poll, string $polloptions, string $pollstatus, string $undovote, string $total_votes, string $edit_poll, string $showresults_link): string
{
    return render_poll_card(
        $poll,
        $pollstatus . ' ' . $undovote,
        $polloptions . '<div class="poll-total">' . $total_votes . '</div>',
        $edit_poll,
        $showresults_link
    );
}

function render_poll_vote_box(array $poll, string $polloptions, string $publicnote, string $closeon, string $edit_poll, string $showresults_link, string $post_code): string
{
    $pid  = $poll['pid'];
    $card = render_poll_card(
        $poll,
        $publicnote . ' ' . $closeon,
        $polloptions,
        '<button type="submit" class="btn btn-primary btn-sm st-pill"><i class="fa-solid fa-check-to-slot me-1"></i>Vote!</button> ' . $edit_poll,
        $showresults_link
    );

    return <<<HTML
    <form action="polls.php" method="post" class="th-poll-form">
        <input type="hidden" name="my_post_key" value="{$post_code}" />
        <input type="hidden" name="action" value="vote" />
        <input type="hidden" name="pid" value="{$pid}" />
        {$card}
    </form>
    HTML;
}

function render_quick_reply(
    int $tid,
    string $reply_subject,
    int $last_pid,
    string $posthash,
    int $page,
    array $curuser,
    string $post_code,
    string $closeoption,
    string $moderation_notice,
    object $lang,
    string $baseurl,
    array $smilies = []
): string {
    // Тулбар идёт внутри формы (над textarea), модалки — после закрытия формы
    $editor = insert_bbcode_editor($smilies, $baseurl, 'message');

    // Ник и аватар текущего пользователя — экранируем, аватар через format_avatar() (пустой → заглушка)
    $me_name = htmlspecialchars_uni((string)($curuser['username'] ?? ''));
    $me_av   = format_avatar($curuser['avatar'] ?? '', $curuser['avatardimensions'] ?? '');
    $me_img  = empty($me_av['is_html'])
        ? '<img src="' . $me_av['image'] . '" alt="" class="th-reply__av">'
        : '<span class="st-ico st-soft-muted th-reply__av"><i class="fa-solid fa-user"></i></span>';

    $title   = $lang->showthread['quick_reply'];
    $options = $closeoption !== ''
        ? '<a class="st-toggle" data-bs-toggle="collapse" href="#collapse-reply" role="button" aria-expanded="false" aria-controls="collapse-reply"><i class="fa-solid fa-gear"></i>' . rtrim($lang->showthread['moderation_options'], ': ') . '</a>'
        : '';
    $optbody = $closeoption !== ''
        ? '<div id="collapse-reply" class="collapse"><div class="th-reply__opts">' . $closeoption . '</div></div>'
        : '';
    $moderation_notice = $moderation_notice !== ''
        ? '<div class="th-reply__notice"><span class="st-badge st-soft-warning"><i class="fa-solid fa-hourglass-half"></i>' . strip_tags($moderation_notice) . '</span></div>'
        : '';

    return <<<HTML
    <div id="quickreply_spinner" class="showthread_spinner" style="display:none">
        <i class="fa-solid fa-circle-notch fa-spin"></i>
    </div>
    <form method="post" action="newreply.php?tid={$tid}&amp;processed=1"
          name="quick_reply_form" id="quick_reply_form" class="th-reply-form">
        <input type="hidden" name="my_post_key"  value="{$post_code}" />
        <input type="hidden" name="subject"       value="RE: {$reply_subject}" />
        <input type="hidden" name="action"        value="do_newreply" />
        <input type="hidden" name="posthash"      value="{$posthash}" id="posthash" />
        <input type="hidden" name="quoted_ids"    value="" id="quoted_ids" />
        <input type="hidden" name="lastpid"       value="{$last_pid}" id="lastpid" />
        <input type="hidden" name="from_page"     value="{$page}" />
        <input type="hidden" name="tid"           value="{$tid}" />
        <input type="hidden" name="method"        value="quickreply" />
        <div id="fileIdsContainer"></div>

        <div class="st-card th-reply" id="quickreply">
            <div class="st-card__head">
                <span class="st-ico st-soft-primary"><i class="fa-solid fa-reply"></i></span>
                <span>{$title}</span>
                <span class="ms-auto th-reply__who">{$me_img}<span>{$me_name}</span></span>
            </div>
            {$moderation_notice}
            <div class="th-reply__mq" id="quickreply_multiquote" style="display:none;">
                <i class="fa-solid fa-quote-left"></i>
                <span>
                    {$lang->showthread['quickreply_multiquote_selected']}
                    <a href="./newreply.php?tid={$tid}&amp;load_all_quotes=1" onclick="return Thread.loadMultiQuoted();">{$lang->showthread['quickreply_multiquote_now']}</a>
                    {$lang->showthread['or']}
                    <a href="javascript:void(0)" onclick="Thread.clearMultiQuoted(); return false;">{$lang->showthread['quickreply_multiquote_deselect']}</a>.
                </span>
            </div>
            <div class="st-compose-body">
                {$editor['toolbar']}
                <textarea class="form-control st-editor-text th-reply__text"
                          name="message" id="message" tabindex="1"
                          placeholder="Write a reply to this message..."></textarea>
                {$optbody}
            </div>
            <div class="st-optbar">
                {$options}
                <span class="ms-auto"></span>
                <button type="submit" class="btn btn-outline-secondary st-pill" name="previewpost" tabindex="3">
                    <i class="fa-solid fa-eye me-1"></i>{$lang->showthread['preview_post']}
                </button>
                <button type="submit" class="btn btn-primary st-pill" tabindex="2" accesskey="s" id="quick_reply_submit">
                    <i class="fa-solid fa-paper-plane me-1"></i>{$lang->showthread['post_reply']}
                </button>
            </div>
        </div>
    </form>
    {$editor['modal']}
    HTML;
}

function render_inline_mod(int $tid, string $standardposttools, string $customposttools, string $inlinecount, string $post_code, object $lang, string $baseurl, int $threadcount): string
{
    $go_text    = addslashes($lang->showthread['inline_go']);
    $clear_text = $lang->showthread['clear'];
    $go_value   = $lang->showthread['go'];
    $label      = rtrim($lang->showthread['inline_post_moderation'], ': ');

    return <<<HTML
    <div class="th-mod__part">
        <script type="text/javascript">
            var go_text = "{$go_text}";
            var all_text = "{$threadcount}";
            var inlineType = "thread";
            var inlineId = {$tid};
        </script>
        <script src="{$baseurl}/scripts/inline_moderation.js?ver=1821"></script>

        <div class="th-mod__lbl"><i class="fa-solid fa-list-check"></i>{$label}</div>
        <form action="moderation.php" method="post" id="inlinemoderation_options" class="th-mod__form">
            <input type="hidden" name="my_post_key" value="{$post_code}" />
            <input type="hidden" name="tid" value="{$tid}" />
            <input type="hidden" name="modtype" value="inlinepost" />
            <select name="action" id="inlinemoderation_options_selector" class="form-select form-select-sm">
                {$standardposttools}
                {$customposttools}
            </select>
            <input type="submit" class="btn btn-sm btn-primary st-pill" name="go"
                   value="{$go_value} ({$inlinecount})" id="inline_go" />
            <input type="button" class="btn btn-sm btn-outline-secondary st-pill"
                   onclick="inlineModeration.clearChecked();"
                   value="{$clear_text}" />
        </form>
    </div>
HTML;
}

function render_moderation_options(int $tid, string $standardthreadtools, string $customthreadtools, string $inlinemod, string $post_code, object $lang, array $thread, string $forumjump, string $sitename): string
{
    // subject/username в $thread уже экранированы в начале showthread.php — второй проход давал &amp;amp;
    $subject  = $thread['subject'];
    $username = $thread['username'];
    $mod_lbl  = rtrim($lang->showthread['moderation_options'], ': ');
    $tt_lbl   = $lang->showthread['standard_mod_tools'];

    return <<<HTML
    <div class="st-card th-mod">
        <div class="st-card__head">
            <span class="st-ico st-soft-danger"><i class="fa-solid fa-shield-halved"></i></span>
            <span>{$mod_lbl}</span>
        </div>
        <div class="th-mod__body">
            <div class="th-mod__part">
                <div class="th-mod__lbl"><i class="fa-solid fa-sliders"></i>{$tt_lbl}</div>
                <form action="moderation.php" method="post" id="moderator_options" class="th-mod__form">
                    <input type="hidden" name="modtype"     value="thread" />
                    <input type="hidden" name="tid"         value="{$tid}" />
                    <input type="hidden" name="my_post_key" value="{$post_code}" />
                    <select name="action" id="moderator_options_selector" class="form-select form-select-sm">
                        <option value="delayedmoderation">{$lang->showthread['delayed_moderation']}</option>
                        {$standardthreadtools}
                        {$customthreadtools}
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary st-pill"><i class="fa-solid fa-shuffle me-1"></i>{$lang->showthread['mod_go']}</button>
                </form>
            </div>
            {$inlinemod}
        </div>
    </div>

    <!-- Delete posts modal -->
    <div class="modal fade" id="deletePostsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                        <div>
                            <h5 class="modal-title mb-0">{$lang->showthread['mod_dp_title']}</h5>
                            <small class="opacity-75">{$lang->showthread['mod_dp_subtitle']}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="px-4 pt-4 pb-3">
                        <div class="alert alert-danger border-start border-danger border-4 bg-danger bg-opacity-10 mb-0">
                            <div class="d-flex gap-3 align-items-center">
                                <i class="fas fa-radiation-alt fa-lg text-danger"></i>
                                <div>
                                    <p class="mb-2 small">{$lang->showthread['mod_dp_warning']}</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-secondary">{$lang->showthread['mod_thread_id']} <strong>{$tid}</strong></span>
                                        <span class="badge bg-danger">{$lang->showthread['mod_dp_posts']} <strong id="modal_post_count">0</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 pb-3">
                        <h6 class="text-muted mb-2"><i class="fas fa-eye me-1"></i>{$lang->showthread['mod_dp_preview']}</h6>
                        <div id="modal_posts_preview" style="max-height:380px;overflow-y:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>{$lang->showthread['mod_cancel']}</button>
                    <button type="button" class="btn btn-danger btn-sm px-4" id="confirmDeleteBtn"><i class="fas fa-trash-alt me-1"></i>{$lang->showthread['mod_delete_permanently']}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete thread modal -->
    <div class="modal fade" id="deleteThreadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 py-4 text-white" style="background:linear-gradient(135deg,#f56565,#e53e3e)">
                    <h4 class="modal-title w-100 text-center mb-0"><i class="fas fa-trash-alt me-2"></i>{$lang->showthread['mod_dt_title']}</h4>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="dt-thread-preview mb-4">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-file-alt text-danger fs-4 mt-1 me-3"></i>
                            <div>
                                <h5 class="mb-1" style="overflow-wrap:break-word">{$subject}</h5>
                                <div class="text-muted small">{$lang->showthread['mod_tid']} <strong>{$tid}</strong> &nbsp;&nbsp; <i class="fas fa-user me-1"></i>{$username}</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger border-danger mb-4">
                        <h5 class="alert-heading mb-2">{$lang->showthread['mod_dt_warning_title']}</h5>
                        <p class="mb-0 small">{$lang->showthread['mod_dt_warning_text']}</p>
                    </div>
                    <div class="p-3 bg-body-tertiary rounded mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="dt_confirm1">
                            <label class="form-check-label fw-bold text-danger" for="dt_confirm1">{$lang->showthread['mod_dt_confirm1']}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dt_confirm2">
                            <label class="form-check-label text-muted" for="dt_confirm2">{$lang->showthread['mod_dt_confirm2']}</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-arrow-left me-1"></i>{$lang->showthread['mod_cancel']}</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteThreadBtn" disabled><i class="fas fa-trash-alt me-2"></i>{$lang->showthread['mod_delete_permanently']}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Merge thread modal -->
    <div class="modal fade" id="mergeThreadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-code-branch fa-lg"></i>
                        <div><h5 class="modal-title mb-0">{$lang->showthread['mod_mg_title']}</h5><small class="opacity-75">{$lang->showthread['mod_mg_subtitle']}</small></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="moderation.php" method="post" id="mergeThreadForm">
                        <input type="hidden" name="my_post_key" value="{$post_code}" />
                        <input type="hidden" name="action"      value="do_merge" />
                        <input type="hidden" name="tid"         value="{$tid}" />
                        <div class="p-3 bg-body-tertiary rounded mb-4 border-start border-primary border-4">
                            <small class="text-muted d-block mb-1">{$lang->showthread['mod_mg_current']}</small>
                            <strong>{$subject}</strong>
                            <small class="text-muted d-block mt-1">{$lang->showthread['mod_thread_id']} {$tid}</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">{$lang->showthread['mod_mg_new_subject']}</label>
                            <input type="text" class="form-control" name="subject" value="{$subject}" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">{$lang->showthread['mod_mg_url']}</label>
                            <input type="text" class="form-control" name="threadurl" placeholder="https://example.com/thread-123" required>
                            <div class="mt-3 p-3 bg-body-tertiary rounded small">
                                {$lang->showthread['mod_mg_note']}
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{$lang->showthread['mod_cancel']}</button>
                    <button type="submit" form="mergeThreadForm" class="btn btn-primary btn-sm px-4"><i class="fas fa-code-branch me-1"></i>{$lang->showthread['mod_mg_button']}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Move thread modal -->
    <div class="modal fade" id="moveThreadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exchange-alt fa-lg"></i>
                        <div><h5 class="modal-title mb-0">{$lang->showthread['mod_mv_title']}</h5><small class="opacity-75">{$lang->showthread['mod_mv_subtitle']}</small></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="moderation.php" method="post" id="moveThreadForm">
                        <input type="hidden" name="my_post_key" value="{$post_code}" />
                        <input type="hidden" name="action"      value="do_move" />
                        <input type="hidden" name="tid"         value="{$tid}" />
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-folder me-2 text-primary"></i>{$lang->showthread['mod_mv_destination']}</label>
                            {$forumjump}
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">{$lang->showthread['mod_mv_method']}</label>
                            <div class="mt-method-option selected" onclick="mtSelectMethod('redirect', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-link fa-lg me-3 text-primary"></i>
                                    <div class="flex-grow-1"><div class="fw-bold small">{$lang->showthread['mod_mv_redirect']}</div><small class="text-muted">{$lang->showthread['mod_mv_redirect_desc']}</small></div>
                                </div>
                                <input type="number" name="redirect_expire" class="form-control form-control-sm mt-2" placeholder="{$lang->showthread['mod_mv_redirect_days']}" min="1" max="365" />
                                <input type="radio" name="method" value="redirect" checked class="d-none" />
                            </div>
                            <div class="mt-method-option" onclick="mtSelectMethod('move', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-arrow-right fa-lg me-3 text-primary"></i>
                                    <div class="flex-grow-1"><div class="fw-bold small">{$lang->showthread['mod_mv_move']}</div><small class="text-muted">{$lang->showthread['mod_mv_move_desc']}</small></div>
                                </div>
                                <input type="radio" name="method" value="move" class="d-none" />
                            </div>
                            <div class="mt-method-option" onclick="mtSelectMethod('copy', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-copy fa-lg me-3 text-primary"></i>
                                    <div class="flex-grow-1"><div class="fw-bold small">{$lang->showthread['mod_mv_copy']}</div><small class="text-muted">{$lang->showthread['mod_mv_copy_desc']}</small></div>
                                </div>
                                <input type="radio" name="method" value="copy" class="d-none" />
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{$lang->showthread['mod_cancel']}</button>
                    <button type="submit" form="moveThreadForm" class="btn btn-primary btn-sm px-4"><i class="fas fa-exchange-alt me-1"></i>{$lang->showthread['mod_mv_process']}</button>
                </div>
            </div>
        </div>
    </div>
    HTML;
}

/** Панель «пагинация слева, кнопки справа» — сверху и снизу постов */
function th_toolbar(string $multipage, string $buttons, string $mod = ''): string
{
    $pager   = trim($multipage) !== '' ? '<div class="st-pager">' . $multipage . '</div>' : '<span></span>';
    $buttons = trim($buttons) !== '' ? '<div class="th-actions">' . $buttons . '</div>' : '';

    if ($pager === '<span></span>' && $buttons === '') {
        return '';
    }

    return '<div class="st-card st-toolbar th-bar' . ($mod !== '' ? ' th-bar--' . $mod : '') . '">' . $pager . $buttons . '</div>';
}

function render_showthread(array $thread, string $thread_head, string $multipage, string $addremovesubscription, string $newreply, string $pollbox, string $rating_html, string $posts, string $quickreply, string $search_thread, string $next_oldest_link, string $next_newest_link, string $addpoll, string $printthread, string $sendthread, string $moderationoptions, string $usersbrowsing, object $lang, string $baseurl, int $tid, string $report_modal): string
{
    $bar_top    = th_toolbar($multipage, $addremovesubscription . ' ' . $newreply, 'top');
    $bar_bottom = th_toolbar($multipage, $newreply, 'bottom');
    $icons      = trim($addpoll . $printthread . $sendthread);
    $icons      = $icons !== '' ? '<div class="th-icons">' . $icons . '</div>' : '';

    return <<<HTML
    <div class="container-md st-page th-page">

        {$thread_head}
        {$bar_top}
        {$rating_html}
        {$pollbox}

        <div id="posts" class="th-posts">{$posts}</div>

        {$bar_bottom}
        {$quickreply}

        <div class="st-card th-foot">
            <div class="th-foot__search">{$search_thread}</div>
            <div class="th-foot__nav">
                <a href="{$next_oldest_link}" class="btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-angles-left me-1"></i>{$lang->showthread['next_oldest']}</a>
                <a href="{$next_newest_link}" class="btn btn-sm btn-outline-secondary st-pill">{$lang->showthread['next_newest']}<i class="fa-solid fa-angles-right ms-1"></i></a>
                {$icons}
            </div>
        </div>

        {$moderationoptions}
        {$usersbrowsing}
        {$report_modal}
    </div>

    HTML;
}

/** Строка формы в модалке жалобы: подпись с иконкой + поле */
function th_report_field(string $for, string $icon, string $label, string $control): string
{
    return '<div class="mb-3">'
         . '<label for="' . $for . '" class="form-label fw-semibold"><i class="bi ' . $icon . ' me-1"></i>' . $label . '</label>'
         . $control
         . '</div>';
}

function render_report_modal(int $user_id): string
{
    global $mybb, $lang;
    $postKey = htmlspecialchars($mybb->post_code);
    $L = $lang->showthread;

    $reasons = '<option value="" selected disabled>' . $L['report_select_reason'] . '</option>';
    $groups  = [
        'report_group_content' => ['spam', 'offensive', 'harassment', 'hate_speech', 'explicit', 'illegal'],
        'report_group_rules'   => ['off_topic', 'double_post', 'flame', 'personal_attack', 'spoiler'],
        'report_group_other'   => ['copyright', 'personal_info', 'malware', 'scam', 'other'],
    ];
    foreach ($groups as $glabel => $vals) {
        $reasons .= '<optgroup label="' . $L[$glabel] . '">';
        foreach ($vals as $v) {
            $reasons .= '<option value="' . $v . '">' . $L['report_r_' . $v] . '</option>';
        }
        $reasons .= '</optgroup>';
    }

    $rules = '<option value="" selected>' . $L['report_rule_none'] . '</option>';
    for ($i = 1; $i <= 8; $i++) {
        $rules .= '<option value="rule_' . $i . '">' . $L['report_rule_' . $i] . '</option>';
    }

    $f_reason = th_report_field('forumPostReportReason', 'bi-exclamation-triangle', $L['report_reason'],
        '<select class="form-select" id="forumPostReportReason" name="reason" required>' . $reasons . '</select>');
    $f_details = th_report_field('forumPostReportDetails', 'bi-chat-text', $L['report_details'],
        '<textarea class="form-control" id="forumPostReportDetails" name="description" rows="4" placeholder="' . $L['report_details_placeholder'] . '" maxlength="2000"></textarea>'
        . '<div class="form-text d-flex justify-content-between mt-1"><span>' . $L['report_details_hint'] . '</span><span id="forumPostCharCount">0/2000</span></div>');
    $f_rule = th_report_field('forumPostRuleViolation', 'bi-journal-text', $L['report_rule_violation'],
        '<select class="form-select" id="forumPostRuleViolation" name="rule_violation">' . $rules . '</select>');
    $f_email = th_report_field('forumPostReportEmail', 'bi-envelope', $L['report_email'],
        '<input type="email" class="form-control" id="forumPostReportEmail" name="email" placeholder="' . $L['report_email_placeholder'] . '">');

    return <<<HTML
    <div class="modal fade th-modal" id="reportForumPostModal" tabindex="-1" aria-labelledby="reportForumPostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="st-ico st-soft-warning"><i class="fa-solid fa-flag"></i></span>
                    <h5 class="modal-title" id="reportForumPostModalLabel">{$L['report_title']}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$L['report_close']}"></button>
                </div>
                <form id="reportForumPostForm" action="takereport.php" method="POST" class="th-contents">
                    <div class="modal-body">
                        <input type="hidden" name="my_post_key"      value="{$postKey}">
                        <input type="hidden" name="type"             id="forumPostReportType"     value="forumpost">
                        <input type="hidden" name="reported_id"      id="forumPostReportedId"     value="">
                        <input type="hidden" name="addedby"          id="forumPostAddedBy"        value="{$user_id}">
                        <input type="hidden" name="reported_user_id" id="forumPostReportedUserId" value="">
                        <input type="hidden" name="forum_id"         id="forumPostForumId"        value="">
                        <input type="hidden" name="thread_id"        id="forumPostThreadId"       value="">

                        <div class="th-note th-note--info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            {$L['report_reporting']} <strong id="reportingForumPost">{$L['report_forum_post']}</strong>
                        </div>

                        <div class="th-rp-preview mb-4">
                            <div class="th-modal__lbl mt-0"><i class="bi bi-chat-left-text"></i>{$L['report_post_preview']}</div>
                            <div class="th-rp-preview__row">
                                <span class="st-ico st-soft-success"><i class="bi bi-person"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                        <div>
                                            <span class="fw-semibold" id="forumPostAuthorPreview">{$L['report_user']}</span>
                                            <span class="th-modal__sub ms-2" id="forumPostDatePreview"></span>
                                        </div>
                                        <span class="st-badge st-soft-success">{$L['report_forum_post']}</span>
                                    </div>
                                    <div class="fw-semibold text-primary-emphasis mb-2" id="forumPostSubjectPreview">{$L['report_post_subject']}</div>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="st-badge st-soft-muted"><i class="bi bi-grid"></i>{$L['report_forum']} <span id="forumPostForumPreview">{$L['report_forum_default']}</span></span>
                                        <span class="st-badge st-soft-muted"><i class="bi bi-chat-dots"></i>{$L['report_thread']} <span id="forumPostThreadPreview">{$L['report_thread_default']}</span></span>
                                    </div>
                                    <p class="mb-0 text-body-secondary small" id="forumPostPreviewText">{$L['report_content_default']}</p>
                                </div>
                            </div>
                        </div>

                        {$f_reason}
                        {$f_details}
                        {$f_rule}
                        {$f_email}

                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <label class="form-label fw-semibold mb-0" for="forumPostCaptchaInput">
                                    <i class="bi bi-shield-check me-1"></i>{$L['report_security']}
                                </label>
                                <button type="button" class="st-icon-btn border-0 ms-auto" id="forumPostRefreshCaptcha" title="{$L['report_captcha_title']}">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-6">
                                    <img src="report_captcha.php" alt="{$L['report_captcha_alt']}" class="th-captcha"
                                         id="forumPostCaptchaDisplay" title="{$L['report_captcha_title']}">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control"
                                           id="forumPostCaptchaInput" name="captcha_response" placeholder="{$L['report_captcha_placeholder']}" autocomplete="off">
                                </div>
                            </div>
                        </div>

                        <div class="th-note th-note--warn small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <div>
                                <strong>{$L['report_important']}</strong> {$L['report_rules_pre']}
                                <a href="/forum/rules.php">{$L['report_rules_link']}</a>.
                                {$L['report_rules_post']}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-outline-secondary st-pill" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>{$L['report_cancel']}
                        </button>
                        <button type="submit" class="btn btn-warning st-pill" id="submitForumPostReport">
                            <i class="bi bi-send me-1"></i>{$L['report_submit']}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    HTML;
}
