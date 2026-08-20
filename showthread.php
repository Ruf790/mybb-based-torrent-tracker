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

if (!empty($mybb->input['pid']) && empty($mybb->input['tid'])) {
    $query = $db->sql_query_prepared(
        "SELECT fid,tid,visible FROM posts WHERE pid = ? LIMIT 1",
        [$mybb->get_input('pid', MyBB::INPUT_INT)]
    );
    $post = $db->fetch_array($query);
    if (empty($post)) {
        stderr($lang->showthread['invalid_post']);
    }
    $mybb->input['tid'] = $post['tid'];
}

// ─── Load thread ──────────────────────────────────────────────────────────────

$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
if (!$thread || str_starts_with((string)$thread['closed'], "moved|")) {
    stderr($lang->global['error_invalidthread']);
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

$visible_states = ["1", "-1"];
$usergroups = $mybb->usergroup ?? [];
$is_mod = is_mod($usergroups);
if ($is_mod) {
    $visible_states[] = "0";
}
$visible_states = array_unique($visible_states);
$visible_condition = "visible IN (" . implode(',', $visible_states) . ")";

if ($CURUSER['id'] && $showownunapproved) {
    $own_unapproved  = ' AND (%1$s' . $visible_condition . ' OR (%1$svisible=0 AND %1$suid=' . (int)$CURUSER['id'] . '))';
    $visibleonly     = sprintf($own_unapproved, null);
    $visibleonly_p   = sprintf($own_unapproved, 'p.');
    $visibleonly_p_t = sprintf($own_unapproved, 'p.') . sprintf($own_unapproved, 't.');
} else {
    $visibleonly     = " AND " . $visible_condition;
    $visibleonly_p   = " AND p." . $visible_condition;
    $visibleonly_p_t = "AND p." . $visible_condition . " AND t." . $visible_condition;
}

// ─── Visibility check ─────────────────────────────────────────────────────────

$ismod = is_mod($usergroups);
if (!$ismod) {
    $ownUnapprovedAllowed = $thread['visible'] === 0
        && !empty($CURUSER['id'])
        && !empty($showownunapproved)
        && $thread['uid'] === $CURUSER['id'];
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
        [(int)$CURUSER['id'], (int)$thread['tid']]
    );
    $thread_read = $db->fetch_field($query, "dateline");

    if ($threadreadcut > 0 && $CURUSER['id']) {
        $query = $db->sql_query_prepared(
            "SELECT dateline FROM forumsread WHERE fid = ? AND uid = ?",
            [(int)$fid, (int)$CURUSER['id']]
        );
        $forum_read = $db->fetch_field($query, "dateline");
        $read_cutoff = TIMENOW - $threadreadcut * 86400;
        $forum_read = (!$forum_read || $forum_read < $read_cutoff) ? $read_cutoff : $forum_read;
    } else {
        $forum_read = (int)my_get_array_cookie("forumread", (string)$fid);
    }

    if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
        $cutoff   = TIMENOW - $threadreadcut * 86400;
        $lastread = ($thread['lastpost'] > $cutoff) ? ($thread_read ?: 0) : 0;
    }

    if (!$lastread) {
        $readcookie = $threadread = (int)my_get_array_cookie("threadread", (string)$thread['tid']);
        $lastread   = ($readcookie > $forum_read) ? $readcookie : $forum_read;
    }
    if ($cutoff && $lastread < $cutoff) {
        $lastread = $cutoff;
    }

    $lastread = (int)$lastread;
    $query = $db->sql_query_prepared(
        "SELECT pid FROM posts WHERE tid = ? AND dateline > ? {$visibleonly} ORDER BY dateline, pid LIMIT 0, 1",
        [(int)$tid, (int)$lastread]
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

if ($mybb->input['action'] === "lastpost") {
    if (str_contains((string)$thread['closed'], "moved|")) {
        $sql    = "SELECT p.pid FROM posts p LEFT JOIN threads t ON (p.tid = t.tid) WHERE t.fid = ? AND t.closed NOT LIKE 'moved|%' {$visibleonly_p_t} ORDER BY p.dateline DESC, p.pid DESC LIMIT 1";
        $params = [(int)$thread['fid']];
    } else {
        $sql    = "SELECT pid FROM posts WHERE tid = ? {$visibleonly} ORDER BY dateline DESC, pid DESC LIMIT 1";
        $params = [(int)$tid];
    }
    $query = $db->sql_query_prepared($sql, $params);
    $pid   = $db->fetch_field($query, "pid");
    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $tid)) . "#pid{$pid}");
    exit;
}

// ─── Action: nextnewest ───────────────────────────────────────────────────────

if ($mybb->input['action'] === "nextnewest") {
    $query = $db->sql_query_prepared(
        "SELECT * FROM threads WHERE fid = ? AND lastpost > ? {$visibleonly} AND closed NOT LIKE 'moved|%' ORDER BY lastpost ASC LIMIT 1",
        [(int)$thread['fid'], (int)$thread['lastpost']]
    );
    $nextthread = $db->fetch_array($query);
    if (!$nextthread) {
        stderr($lang->showthread['error_nonextnewest']);
    }
    $pid = $db->fetch_field(
        $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ? ORDER BY dateline DESC, pid DESC LIMIT 1", [(int)$nextthread['tid']]),
        "pid"
    );
    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $nextthread['tid'])) . "#pid{$pid}");
    exit;
}

// ─── Action: nextoldest ───────────────────────────────────────────────────────

if ($mybb->input['action'] === "nextoldest") {
    $query = $db->sql_query_prepared(
        "SELECT * FROM threads WHERE fid = ? AND lastpost < ? {$visibleonly} AND closed NOT LIKE 'moved|%' ORDER BY lastpost DESC LIMIT 1",
        [(int)$thread['fid'], (int)$thread['lastpost']]
    );
    $nextthread = $db->fetch_array($query);
    if (!$nextthread) {
        stderr($lang->showthread['error_nonextoldest']);
    }
    $pid = $db->fetch_field(
        $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ? ORDER BY dateline DESC, pid DESC LIMIT 1", [(int)$nextthread['tid']]),
        "pid"
    );
    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $nextthread['tid'])) . "#pid{$pid}");
    exit;
}

// ─── Breadcrumb ───────────────────────────────────────────────────────────────

$pid = $mybb->input['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
$breadcrumb_multipage = [];

if ($showforumpagesbreadcrumb) {
    $f_threadsperpage = (int)$f_threadsperpage > 0 ? (int)$f_threadsperpage : 20;
    $query = $db->sql_query_prepared(
        "SELECT threads, unapprovedthreads, pid FROM forums WHERE fid = ? LIMIT 1",
        [(int)$fid]
    );
    $forum_threads = $db->fetch_array($query);
    $threadcount   = $forum_threads['threads'];

    $uid_only = '';
    if (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1) {
        $uid_only    = " AND uid = ?";
        $query       = $db->sql_query_prepared(
            "SELECT COUNT(tid) AS threads FROM threads WHERE fid = ? $visibleonly $uid_only LIMIT 1",
            [(int)$fid, (int)$CURUSER['id']]
        );
        $threadcount = $db->fetch_field($query, "threads");
    }
    if ($threadcount === 0) {
        $params      = $uid_only ? [(int)$fid, (int)$CURUSER['id']] : [(int)$fid];
        $query       = $db->sql_query_prepared(
            "SELECT COUNT(tid) AS threads FROM threads WHERE fid = ? $visibleonly $uid_only LIMIT 1",
            $params
        );
        $threadcount = $db->fetch_field($query, "threads");
    }

    $stickybit = $thread['sticky'] == 1 ? " AND sticky=1" : " OR sticky=1";
    $position_params = $uid_only ? [(int)$fid, (int)$thread['lastpost'], (int)$CURUSER['id']] : [(int)$fid, (int)$thread['lastpost']];
    $query = match ($db->type) {
        "pgsql" => $db->sql_query_prepared(
            "SELECT COUNT(tid) as threads FROM threads WHERE fid = ? AND (lastpost >= ? {$stickybit}) {$visibleonly} {$uid_only} GROUP BY lastpost ORDER BY lastpost DESC",
            $position_params
        ),
        default => $db->sql_query_prepared(
            "SELECT COUNT(tid) as threads FROM threads WHERE fid = ? AND (lastpost >= ? {$stickybit}) {$visibleonly} {$uid_only} ORDER BY lastpost DESC",
            $position_params
        ),
    };

    $thread_position = $db->fetch_field($query, "threads");
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

    $cur_uid    = (int)($CURUSER['id'] ?? 0);
    if ($cur_uid) {
        $user_check   = "uid = ?";
        $user_check_params = [$cur_uid];
    } else {
        $user_check   = "uid = 0 AND ipaddress = ?";
        $user_check_params = [$session->packedip];
    }

    $alreadyvoted = 0;
    $votedfor     = [];
    $query = $db->sql_query_prepared(
        "SELECT * FROM pollvotes WHERE {$user_check} AND pid = ?",
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
    if (is_mod($usergroups)) {
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

// ─── Forum jump / navigation ──────────────────────────────────────────────────

$forumjump        = build_forum_jump("", '', 1, '', 0, true, '', "moveto");
$next_oldest_link = get_thread_link($tid, 0, "nextoldest");
$next_newest_link = get_thread_link($tid, 0, "nextnewest");

mark_thread_read((int)$tid, (int)$fid);

// ─── New thread / reply buttons ───────────────────────────────────────────────

$newthread = $newreply = '';
if ($forum['open'] != 0 && $forum['type'] === "f") {
    if ($forumpermissions['canpostthreads'] != 0) {
        $newthread = '<a href="newthread.php?fid=' . $fid . '" class="button new_thread_button"><span>{$lang->post_thread}</span></a>&nbsp;';
    }
    $canReply = $forumpermissions['canpostreplys'] != 0
        && ($thread['closed'] != 1 || $is_mod)
        && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']));

    if ($canReply) {
        $newreply = '<a href="newreply.php?tid=' . $tid . '" class="btn btn-primary"><i class="fa-solid fa-comment"></i> &nbsp;' . $lang->showthread['post_reply'] . '</a>';
    } elseif ($thread['closed'] == 1) {
        $newreply = '<span class="d-inline-block" tabindex="0" data-bs-toggle="popover" title="' . $thread['subject'] . '" data-bs-content="Thread Closed"><button class="btn btn-primary" type="button" disabled>Thread Closed</button></span>';
    }
}

// ─── Moderation tools ─────────────────────────────────────────────────────────

$is_mod = is_mod($usergroups);
$closeoption = $inlinemod = $moderationoptions = '';
$inlinecount = "0";

if ($is_mod) {
    $closelinkch = $thread['closed'] == 1 ? ' checked="checked"' : '';
    $stickch     = $thread['sticky']  ? ' checked="checked"' : '';
    $closeoption  = '<input type="checkbox" class="form-check-input" name="modoptions[closethread]" value="1"' . $closelinkch . ' /> ' . $lang->showthread['close_thread'] . '<br />';
    $closeoption .= '<input type="checkbox" class="form-check-input" name="modoptions[stickthread]" value="1"' . $stickch . ' /> ' . $lang->showthread['stick_thread'];
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
    $CURUSER['id'] == 0 && $session->is_spider && $threadviews_countspiders == 1 => true,
    $CURUSER['id'] == 0 && !$session->is_spider && $threadviews_countguests == 1 => true,
    $CURUSER['id'] != 0 && ($threadviews_countthreadauthor == 1 || $CURUSER['id'] !== $thread['uid']) => true,
    default => false,
};
if ($shouldCount) {
    if ($delayedthreadviews == 1) {
        $db->shutdown_query("INSERT INTO threadviews (tid) VALUES('{$tid}')");
    } else {
        $db->shutdown_query("UPDATE threads SET views=views+1 WHERE tid='{$tid}'");
    }
    ++$thread['views'];
}

// ─── Search thread ────────────────────────────────────────────────────────────

$search_thread = '';
if ($forumpermissions['cansearch'] != 0) {
    $search_thread = <<<HTML
    <div class="row g-1">
        <div class="col align-self-center">
            <form action="search.php" method="post">
                <input type="hidden" name="action" value="thread" />
                <input type="hidden" name="tid" value="{$thread['tid']}" />
                <div class="input-group input-group-sm">
                    <input type="text" name="keywords" class="form-control border" size="25" />
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-magnifying-glass"></i> {$lang->showthread['search_thread']}
                    </button>
                </div>
            </form>
        </div>
    </div>
    HTML;
}

// ─── Ignored users ────────────────────────────────────────────────────────────

$ignored_users = [];
if ($CURUSER['id'] > 0 && !empty($CURUSER['ignorelist'])) {
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

if (!empty($mybb->input['pid'])) {
    $post = get_post($mybb->input['pid']);
    $postVisible = !empty($post) && !(
        ($post['visible'] == 0 && !($is_mod || ($CURUSER['id'] && $post['uid'] == $CURUSER['id'] && $showownunapproved))) ||
        ($post['visible'] == -1 && !is_moderator($post['fid'], 'canviewdeleted') && $forumpermissions['canviewdeletionnotice'] == 0)
    );
    if (!$postVisible) {
        $footer .= '<script>$(function(){$.jGrowl(\'' . $lang->global['error_invalidpost'] . '\',{theme:\'jgrowl_error\'});})</script>';
    } else {
        $query  = $db->sql_query_prepared("SELECT COUNT(p.dateline) AS count FROM posts p WHERE p.tid = ? AND p.dateline <= ? {$visibleonly_p}", [(int)$tid, (int)$post['dateline']]);
        $result = (int)$db->fetch_field($query, "count");
        $page   = ($result % $perpage === 0) ? $result / $perpage : (int)($result / $perpage) + 1;
    }
}

if ($visible_states !== ["1"]) {
    $query = $db->sql_query_prepared("SELECT COUNT(*) AS replies FROM posts p WHERE p.tid = ? {$visibleonly_p}", [(int)$tid]);
    $thread['replies'] = (int)$db->fetch_field($query, 'replies') - 1;
    $cached_replies    = $thread['replies'] + $thread['unapprovedposts'];
    if (in_array('-1', $visible_states) && in_array('0', $visible_states)) {
        if ($thread['replies'] != $cached_replies) {
            require_once INC_PATH . "/functions_rebuild.php";
            rebuild_thread_counters((int)$thread['tid']);
        }
    }
}

$postcount = (int)$thread['replies'] + 1;
$pages     = (int)ceil($postcount / $perpage);
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

$pid_list = [];
$query = $db->sql_query_prepared(
    "SELECT p.pid FROM posts p WHERE p.tid = ? {$visibleonly_p} ORDER BY p.dateline, p.pid LIMIT {$start}, {$perpage}",
    [(int)$tid]
);
while ($getid = $db->fetch_array($query)) {
    if (empty($pid)) {
        $pid = $getid['pid'];
    }
    $pid_list[] = (int)$getid['pid'];
}
if (!$pid_list) {
    stderr($lang->global['error_invalidthread']);
}

$pid_placeholders = implode(',', array_fill(0, count($pid_list), '?'));
$attachcache = [];
if ($thread['attachmentcount'] > 0) {
    $query = $db->sql_query_prepared(
        "SELECT * FROM attachments WHERE pid IN ({$pid_placeholders})",
        $pid_list
    );
    while ($attachment = $db->fetch_array($query)) {
        $attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
    }
}

$posts = '';
$query = $db->sql_query_prepared(
    "SELECT u.*, u.username AS userusername, p.*, eu.username AS editusername
    FROM posts p
    LEFT JOIN users u ON (u.id=p.uid)
    LEFT JOIN users eu ON (eu.id=p.edituid)
    WHERE p.pid IN ({$pid_placeholders}) ORDER BY p.dateline, p.pid",
    $pid_list
);
while ($post = $db->fetch_array($query)) {
    if ($thread['firstpost'] == $post['pid'] && $thread['visible'] == 0) {
        $post['visible'] = 0;
    }
    $posts .= build_postbit($post);
    $post   = '';
}

$plugins->run_hooks("showthread_linear");

// ─── Quick reply ──────────────────────────────────────────────────────────────

$quickreply    = '';
$canQuickReply = $forumpermissions['canpostreplys'] != 0
    && ($thread['closed'] != 1 || $is_mod)
    && $forum['open'] != 0
    && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']));

if ($canQuickReply) {
    $query    = $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ? ORDER BY pid DESC LIMIT 1", [(int)$tid]);
    $last_pid = $db->fetch_field($query, "pid");
    $posthash = md5($CURUSER['id'] . random_str());

    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_notice = '<div class="red_alert">' . $lang->showthread['moderation_user_posts'] . '</div>';
    }

    $collapse = $collapsed = [];
    if (!empty($mybb->cookies['collapsed'])) {
        $collapse = explode("|", $mybb->cookies['collapsed']);
        foreach ($collapse as $val) {
            $collapsed[$val . "_e"] = "display: none;";
        }
    }

    $quickreply = render_quick_reply($tid, $reply_subject, (int)$last_pid, $posthash, $page, $CURUSER, $mybb->post_code, $closeoption, $moderation_notice, $lang, $BASEURL, $smilies ?? []);
}

// ─── Moderation options ───────────────────────────────────────────────────────

$threadnotesbox = '';
$customthreadtools = $customposttools = '';

$gids      = array_filter(array_unique([...array_filter(explode(',', (string)($CURUSER['additionalgroups'] ?? ''))), $CURUSER['usergroup']]));
$gidswhere = '';
foreach ($gids as $gid) {
    $gid = (int)$gid;
    $gidswhere .= match ($db->type) {
        "pgsql", "sqlite" => " OR ','||groups||',' LIKE '%,{$gid},%'",
        default           => " OR CONCAT(',',`groups`,',') LIKE '%,{$gid},%'",
    };
}



$inlinemoddelete  = '<option value="multideleteposts">Delete Posts Permanently</option>';
$inlinemodmanage  = '<option value="multimergeposts">' . $lang->showthread["mergeposts"] . '</option>'
    . '<option value="multisplitposts">' . $lang->showthread['inline_split_posts'] . '</option>'
    . '<option value="multimoveposts">' . $lang->showthread['inline_move_posts'] . '</option>';
$inlinemodapprove = '<option value="multiapproveposts">' . $lang->showthread['inline_approve_posts'] . '</option>'
    . '<option value="multiunapproveposts">' . $lang->showthread['inline_unapprove_posts'] . '</option>';

$standardposttools = '<optgroup label="Standard Tools">' . $inlinemoddelete . $inlinemodmanage . $inlinemodapprove . '</optgroup>';

$openclosethread        = '<option class="option_mirage" value="openclosethread">Open/Close Thread</option>';
$stickunstickthread     = '<option class="option_mirage" value="stick">Stick/Unstick Thread</option>';
$deletethread           = '<option value="deletethread">Delete Thread Permanently</option>';
$managethread           = '<option class="option_mirage" value="move">Move / Copy Thread</option><option class="option_mirage" value="merge">Merge Threads</option><option class="option_mirage" value="split">Split Thread</option>';
$adminpolloptions       = '';
$approveunapprovethread = '';
$softdeletethread       = '';

if ($pollbox && $is_mod) {
    $adminpolloptions = '<option class="option_mirage" value="deletepoll">{$lang->delete_poll}</option>';
}
$approveunapprovethread = $thread['visible'] == 0
    ? '<option class="option_mirage" value="approvethread">' . $lang->showthread['approve_thread'] . '</option>'
    : '<option class="option_mirage" value="unapprovethread">' . $lang->showthread['unapprove_thread'] . '</option>';

$standardthreadtools = '<optgroup label="' . $lang->showthread['standard_mod_tools'] . '">'
    . $openclosethread . $softdeletethread . $deletethread
    . $adminpolloptions . $stickunstickthread . $managethread . $approveunapprovethread
    . '</optgroup>';

$forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");

if ($usergroups['canstaffpanel'] === '1') {
    $inlinemod        = render_inline_mod($tid, $standardposttools, $customposttools, (string)$inlinecount, $mybb->post_code, $lang, $BASEURL, (int)$thread['replies'] + 1);
    $moderationoptions = render_moderation_options($tid, $standardthreadtools, $customthreadtools, $inlinemod, $mybb->post_code, $lang, $thread, $forumjump, $SITENAME);
}

// ─── Extra links ──────────────────────────────────────────────────────────────

$printthread = '<a href="printthread.php?tid=' . $tid . '" class="links"><i class="fa-solid fa-print"></i></a>';
$sendthread  = ($usergroups['cansendemail'] == 1)
    ? '<a href="sendthread.php?tid=' . $tid . '" class="links"><i class="fa-solid fa-reply"></i></a>'
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
    $addpoll = '<a href="polls.php?action=newpoll&amp;tid=' . $tid . '" class="links" title="' . $lang->showthread['add_poll_to_thread'] . '"><i class="fa-solid fa-square-poll-vertical"></i></a>';
}

// ─── Subscription ─────────────────────────────────────────────────────────────

$addremovesubscription = '';
if ($CURUSER['id']) {
    $query         = $db->sql_query_prepared(
        "SELECT tid FROM threadsubscriptions WHERE tid = ? AND uid = ? LIMIT 1",
        [(int)$tid, (int)$CURUSER['id']]
    );
    $is_subscribed = $db->num_rows($query) > 0;

    $add_remove_subscription      = $is_subscribed ? 'remove' : 'add';
    $add_remove_subscription_text = $is_subscribed
        ? $lang->showthread['unsubscribe_thread']
        : $lang->showthread['subscribe_thread'];

    $addremovesubscription = '<a class="btn btn-secondary" href="usercp.php?action=' . $add_remove_subscription . 'subscription&amp;tid=' . $tid . '&amp;my_post_key=' . $mybb->post_code . '">&nbsp;' . $add_remove_subscription_text . '&nbsp;</a>';
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
    $comma       = '';

    // Один запрос вместо двух — гости и члены вместе
    $query = $db->sql_query_prepared("
        SELECT s.ip, s.uid, s.time, u.username, u.invisible, u.usergroup, u.displaygroup
        FROM sessions s
        LEFT JOIN users u ON (s.uid = u.id)
        WHERE s.time > ? AND s.location2 = ? AND s.nopermission != 1
        ORDER BY u.username ASC, s.time DESC
    ", [(int)$timecut, (int)$tid]);
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
                $user['username']    = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
                $user['reading']     = my_datee($timeformat, $user['time']);
                $onlinemembers      .= $comma . '<a href="' . $user['profilelink'] . '" title="' . $lang->showthread['users_browsing_thread_reading'] . ' (' . $user['reading'] . ')">' . $user['username'] . '</a>' . $invisiblemark;
                $comma               = ', ';
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
    $onlinesep  = ($invisonline !== '' && $onlinemembers) ? ', ' : '';
    $onlinesep2 = (($invisonline !== '' && $guestcount) || ($onlinemembers && $guestcount)) ? ', ' : '';
    $usersbrowsing = <<<HTML
    <div class="card border-0 rounded mt-5">
        <div class="card-body bg-nav rounded">
            <strong>{$lang->showthread['users_browsing_thread']}</strong>
            {$onlinemembers}{$onlinesep}{$invisonline}{$onlinesep2}{$guestsonline}
        </div>
    </div>
    HTML;
}


$plugins->run_hooks("showthread_end");

$test           = get_forum_link($fid);
$thread_deleted = $thread['visible'] == -1 ? 1 : 0;

// ─── Render ───────────────────────────────────────────────────────────────────

stdhead($thread['subject']);

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/showthread.css">';
echo '<script src="' . $BASEURL . '/scripts/ignor.js"></script>';
echo '<script src="' . $BASEURL . '/scripts/showthread.js"></script>';


echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/thread.js?ver=1827"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/edit_post.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/delete_post.js"></script>';
echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/report_post.js"></script>';


?>
<script>
var quickdelete_confirm  = "Are you sure you want to delete this post?";
var quickrestore_confirm = "Are you sure you want to restore this post?";
var allowEditReason      = "1";
var thread_deleted       = "<?= $thread_deleted ?>";
var visible_replies      = "<?= $thread['replies'] ?>";
var forumBaseUrl         = "<?= $test ?>";
lang.save_changes              = "Save Changes";
lang.cancel_edit               = "Cancel Edit";
lang.quick_edit_update_error   = "There was an error editing your reply:";
lang.quick_reply_post_error    = "There was an error posting your reply:";
lang.quick_delete_error        = "There was an error deleting your reply:";
lang.quick_delete_success      = "The post was deleted successfully.";
lang.quick_delete_thread_success = "The thread was deleted successfully.";
lang.quick_restore_error       = "There was an error restoring your reply:";
lang.quick_restore_success     = "The post was restored successfully.";
lang.editreason                = "Edit Reason";
lang.post_deleted_error        = "You can not perform this action to a deleted post.";
lang.softdelete_thread         = "Soft Delete Thread";
lang.restore_thread            = "Restore Thread";
</script>
<?php

build_breadcrumb();





// ── Rating block for showthread.php ──────────────────────────────────────────
// Place this block before render_showthread() call
// Replace $ratingform with $rating_html in render_showthread() call

// Rating data from threads table
$rating_data = [
    'avg'         => $thread['numratings'] > 0
                     ? round($thread['totalratings'] / $thread['numratings'], 1)
                     : 0,
    'count'       => (int)($thread['numratings'] ?? 0),
    'user_rating' => 0,
];

// Current user's rating
if ($CURUSER['id']) {
    $q2 = $db->sql_query_prepared(
        "SELECT rating FROM threadratings WHERE tid = ? AND user_id = ? LIMIT 1",
        [(int)$tid, (int)$CURUSER['id']]
    );
    if ($ur = $db->fetch_array($q2)) {
        $rating_data['user_rating'] = (int)$ur['rating'];
    }
}

// Avg stars
$avg_stars_html = '';
for ($i = 1; $i <= 10; $i++) {
    $filled = $rating_data['avg'] >= $i;
    $half   = !$filled && $rating_data['avg'] >= $i - 0.5;
    if ($filled)       $avg_stars_html .= '<i class="bi bi-star-fill rating-star-filled"></i>';
    elseif ($half)     $avg_stars_html .= '<i class="bi bi-star-half rating-star-filled"></i>';
    else               $avg_stars_html .= '<i class="bi bi-star rating-star-empty"></i>';
}

// User stars
$user_stars_html = '';
if ($CURUSER['id']) {
    for ($i = 1; $i <= 10; $i++) {
        $active           = $rating_data['user_rating'] >= $i ? 'active' : '';
        $user_stars_html .= '<i class="bi bi-star-fill user-star ' . $active . '" data-value="' . $i . '" onclick="rateThread(' . $i . ')"></i>';
    }
    $user_section = '
        <div class="d-flex align-items-center gap-2">
            <div class="user-stars d-flex gap-1" id="user-stars">' . $user_stars_html . '</div>
            <span class="small text-muted" id="rating-hint">'
                . ($rating_data['user_rating'] ? $rating_data['user_rating'] . '/10' : 'rate')
            . '</span>
        </div>';
} else {
    $user_section = '<a href="' . $BASEURL . '/member.php?action=login" class="btn btn-sm btn-outline-primary rounded-pill">Login to rate</a>';
}

$rating_html = '
<style>
.rating-modern { background:#fff; border-radius:16px; padding:16px 20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:all 0.2s; }
.rating-modern:hover { box-shadow:0 4px 16px rgba(0,0,0,0.1); }
.rating-score { font-size:2.2rem; font-weight:800; color:#1a1a2e; line-height:1; }
.rating-stars { display:flex; gap:4px; font-size:1rem; }
.rating-star-filled { color:#f59e0b; }
.rating-star-empty { color:#e9ecef; }
.user-star { font-size:1.5rem; cursor:pointer; transition:0.15s; color:#dee2e6; }
.user-star:hover { transform:scale(1.15); color:#f59e0b !important; }
.user-star.active { color:#f59e0b; }
@media(max-width:768px) { .rating-score { font-size:1.8rem; } .user-star { font-size:1.2rem; } }
</style>
<div class="rating-modern">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="text-center">
                <div class="rating-score">' . ($rating_data['count'] > 0 ? $rating_data['avg'] : '—') . '</div>
                <div class="small text-muted">' . number_format($rating_data['count']) . ' votes</div>
            </div>
            <div class="rating-stars" id="rating-display">' . $avg_stars_html . '</div>
        </div>
        ' . $user_section . '
    </div>
</div>
<script src="' . $BASEURL . '/scripts/rating.js"></script>
<script>ratingInit(' . $rating_data['user_rating'] . ', ' . $tid . ', "' . $BASEURL . '", "thread");</script>';





echo render_showthread($thread, $multipage, $addremovesubscription, $newreply, $pollbox, $rating_html, $posts, $quickreply, $search_thread, $next_oldest_link, $next_newest_link, $addpoll, $printthread, $sendthread, $moderationoptions, $usersbrowsing, $lang, $BASEURL, $tid, $thread_deleted);
echo render_report_modal((int)$CURUSER['id']);
stdfoot();

// ─── Template functions ───────────────────────────────────────────────────────

function render_poll_results_box(array $poll, string $polloptions, string $pollstatus, string $undovote, string $total_votes, string $edit_poll, string $showresults_link): string
{
    return <<<HTML
    <div class="poll-box card border-0 mb-4">
        <div class="poll-header card-header">
            <span class="poll-question">{$poll['question']}</span>
            <span class="poll-badge">Poll</span>
        </div>
        <div class="card-body">
            <div class="poll-meta mb-3">
                {$pollstatus}
                {$undovote}
            </div>
            {$polloptions}
            <div class="poll-total">{$total_votes}</div>
        </div>
        <div class="poll-footer card-footer">
            <div class="poll-footer-left">{$edit_poll}</div>
            <div class="poll-footer-right">{$showresults_link}</div>
        </div>
    </div>
    HTML;
}

function render_poll_vote_box(array $poll, string $polloptions, string $publicnote, string $closeon, string $edit_poll, string $showresults_link, string $post_code): string
{
    $pid = $poll['pid'];
    return <<<HTML
    <form action="polls.php" method="post">
        <input type="hidden" name="my_post_key" value="{$post_code}" />
        <input type="hidden" name="action" value="vote" />
        <input type="hidden" name="pid" value="{$pid}" />
        <div class="poll-box card border-0 mb-4">
            <div class="poll-header card-header">
                <span class="poll-question">{$poll['question']}</span>
                <span class="poll-badge">Poll</span>
            </div>
            <div class="card-body">
                <div class="poll-meta mb-3">{$publicnote} {$closeon}</div>
                {$polloptions}
            </div>
            <div class="poll-footer card-footer">
                <div class="poll-footer-left">
                    <button type="submit" class="btn btn-primary btn-sm">Vote!</button>
                    {$edit_poll}
                </div>
                <div class="poll-footer-right">{$showresults_link}</div>
            </div>
        </div>
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

    return <<<HTML
    <div id="quickreply_spinner" class="showthread_spinner" style="display:none">
        <i class="fa-solid fa-circle-notch fa-spin"></i>
    </div>
    {$moderation_notice}
    <form method="post" action="newreply.php?tid={$tid}&amp;processed=1"
          name="quick_reply_form" id="quick_reply_form">
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
        <div class="row d-flex g-2 mb-4 mt-5">
            <div class="col-auto d-none d-lg-block">
                <img src="{$curuser['avatar']}" class="rounded img-fluid" style="width:100px">
            </div>
            <div class="col">
                <div class="editor_control_bar mb-3 border border-top-5 p-2" id="quickreply_multiquote" style="display:none;">
                    {$lang->showthread['quickreply_multiquote_selected']}
                    <a href="./newreply.php?tid={$tid}&amp;load_all_quotes=1" onclick="return Thread.loadMultiQuoted();">{$lang->showthread['quickreply_multiquote_now']}</a>
                    {$lang->showthread['or']}
                    <a href="javascript:void(0)" onclick="Thread.clearMultiQuoted(); return false;">{$lang->showthread['quickreply_multiquote_deselect']}</a>.
                </div>
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">{$curuser['username']}</h6>
                        {$editor['toolbar']}
                        <textarea class="form-control border-0 p-0" style="resize:vertical;height:150px"
                                  name="message" id="message" tabindex="1"
                                  placeholder="Write a reply to this message..."></textarea>
                        <div id="collapse-reply" class="collapse bg-nav p-2">{$closeoption}</div>
                    </div>
                    <div class="card-footer border-top-0">
                        <button type="submit" class="btn btn-primary" tabindex="2" accesskey="s" id="quick_reply_submit">
                            <i class="fa-solid fa-comment"></i> &nbsp;{$lang->showthread['post_reply']}
                        </button>
                        <a class="btn btn-thread ms-3 me-3" data-bs-toggle="collapse" href="#collapse-reply" role="button">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                        <button type="submit" class="btn btn-thread" name="previewpost" tabindex="3">
                            <i class="fa-solid fa-pencil"></i> &nbsp;{$lang->showthread['preview_post']}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    {$editor['modal']}
    HTML;
}













function render_inline_mod(int $tid, string $standardposttools, string $customposttools, string $inlinecount, string $post_code, object $lang, string $baseurl, int $threadcount): string
{
    $go_text = addslashes($lang->showthread['inline_go']);
    $clear_text = addslashes($lang->showthread['clear']);
    $go_value = $lang->showthread['go'];
    
    return <<<HTML
    <div class="col-lg">
        <script type="text/javascript">
        <!--
            var go_text = "{$go_text}";
            var all_text = "{$threadcount}";
            var inlineType = "thread";
            var inlineId = {$tid};
        // -->
        </script>
        <script src="{$baseurl}/scripts/inline_moderation.js?ver=1821"></script>
        
        <form action="moderation.php" method="post" id="inlinemoderation_options">
            <input type="hidden" name="my_post_key" value="{$post_code}" />
            <input type="hidden" name="tid" value="{$tid}" />
            <input type="hidden" name="modtype" value="inlinepost" />
            
            <div class="row g-1">
                <div class="col d-none d-lg-block">&nbsp;</div>
                <div class="col-auto">
                    <select name="action" id="inlinemoderation_options_selector" class="form-select form-select-sm border w-auto pe-5">
                        {$standardposttools}
                        {$customposttools}
                    </select>
                </div>
                <div class="col-auto">
                    <input type="submit" class="btn btn-sm btn-primary" name="go" 
                           value="{$go_value} ({$inlinecount})" id="inline_go" />
                    <input type="button" class="btn btn-sm btn-primary" 
                           onclick="inlineModeration.clearChecked();" 
                           value="{$clear_text}" />
                </div>
            </div>
        </form>
    </div>
HTML;
}






function render_moderation_options(int $tid, string $standardthreadtools, string $customthreadtools, string $inlinemod, string $post_code, object $lang, array $thread, string $forumjump, string $sitename): string
{
    $subject  = htmlspecialchars_uni($thread['subject']);
    $username = htmlspecialchars_uni($thread['username']);

    return <<<HTML
    <div class="row mt-5 g-1 m-0 py-3 border-top border-bottom">
        <div class="col-lg-6">
            <form action="moderation.php" method="post" id="moderator_options">
                <input type="hidden" name="modtype"     value="thread" />
                <input type="hidden" name="tid"         value="{$tid}" />
                <input type="hidden" name="my_post_key" value="{$post_code}" />
                <div class="row g-1">
                    <div class="col-auto">
                        <select name="action" id="moderator_options_selector" class="form-select form-select-sm w-auto pe-5">
                            <option value="delayedmoderation">{$lang->showthread['delayed_moderation']}</option>
                            {$standardthreadtools}
                            {$customthreadtools}
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary rounded"><i class="fa-solid fa-shuffle"></i> &nbsp;Go</button>
                    </div>
                </div>
            </form>
        </div>
        {$inlinemod}
    </div>

    <!-- Delete posts modal -->
    <div class="modal fade" id="deletePostsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                        <div>
                            <h5 class="modal-title mb-0">Delete Posts Permanently</h5>
                            <small class="opacity-75">Irreversible Action</small>
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
                                    <p class="mb-2 small">You are about to <strong>permanently delete</strong> selected posts. This <strong>cannot be undone</strong>.</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-secondary">Thread ID: <strong>{$tid}</strong></span>
                                        <span class="badge bg-danger">Posts: <strong id="modal_post_count">0</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 pb-3">
                        <h6 class="text-muted mb-2"><i class="fas fa-eye me-1"></i>Posts to be deleted:</h6>
                        <div id="modal_posts_preview" style="max-height:380px;overflow-y:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm px-4" id="confirmDeleteBtn"><i class="fas fa-trash-alt me-1"></i>Delete Permanently</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete thread modal -->
    <div class="modal fade" id="deleteThreadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 py-4 text-white" style="background:linear-gradient(135deg,#f56565,#e53e3e)">
                    <h4 class="modal-title w-100 text-center mb-0"><i class="fas fa-trash-alt me-2"></i>Delete Thread</h4>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="dt-thread-preview mb-4">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-file-alt text-danger fs-4 mt-1 me-3"></i>
                            <div>
                                <h5 class="mb-1" style="overflow-wrap:break-word">{$subject}</h5>
                                <div class="text-muted small">TID: <strong>{$tid}</strong> &nbsp;&nbsp; <i class="fas fa-user me-1"></i>{$username}</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger border-danger mb-4">
                        <h5 class="alert-heading mb-2">Permanent Deletion Warning</h5>
                        <p class="mb-0 small">All posts, attachments, and poll data will be permanently deleted. This cannot be undone.</p>
                    </div>
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="dt_confirm1">
                            <label class="form-check-label fw-bold text-danger" for="dt_confirm1">I understand this is permanent and cannot be undone</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dt_confirm2">
                            <label class="form-check-label text-muted" for="dt_confirm2">I have ensured all important content is backed up</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-arrow-left me-1"></i>Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteThreadBtn" disabled><i class="fas fa-trash-alt me-2"></i>Delete Permanently</button>
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
                        <div><h5 class="modal-title mb-0">Merge Threads</h5><small class="opacity-75">Combine multiple threads into one</small></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="moderation.php" method="post" id="mergeThreadForm">
                        <input type="hidden" name="my_post_key" value="{$post_code}" />
                        <input type="hidden" name="action"      value="do_merge" />
                        <input type="hidden" name="tid"         value="{$tid}" />
                        <div class="p-3 bg-light rounded mb-4 border-start border-primary border-4">
                            <small class="text-muted d-block mb-1">Current Thread</small>
                            <strong>{$subject}</strong>
                            <small class="text-muted d-block mt-1">Thread ID: {$tid}</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">New Subject</label>
                            <input type="text" class="form-control" name="subject" value="{$subject}" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Thread URL to Merge</label>
                            <input type="text" class="form-control" name="threadurl" placeholder="https://example.com/thread-123" required>
                            <div class="mt-3 p-3 bg-light rounded small">
                                The specified thread will be <strong>deleted</strong> and its posts merged into this one.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="mergeThreadForm" class="btn btn-primary btn-sm px-4"><i class="fas fa-code-branch me-1"></i>Merge Threads</button>
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
                        <div><h5 class="modal-title mb-0">Move / Copy Thread</h5><small class="opacity-75">Transfer to another forum</small></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="moderation.php" method="post" id="moveThreadForm">
                        <input type="hidden" name="my_post_key" value="{$post_code}" />
                        <input type="hidden" name="action"      value="do_move" />
                        <input type="hidden" name="tid"         value="{$tid}" />
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-folder me-2 text-primary"></i>Destination Forum</label>
                            {$forumjump}
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Transfer Method</label>
                            <div class="mt-method-option selected" onclick="mtSelectMethod('redirect', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-link fa-lg me-3 text-primary"></i>
                                    <div class="flex-grow-1"><div class="fw-bold small">Move with Redirect</div><small class="text-muted">Leave redirect in original forum</small></div>
                                </div>
                                <input type="number" name="redirect_expire" class="form-control form-control-sm mt-2" placeholder="Redirect days (blank = infinite)" min="1" max="365" />
                                <input type="radio" name="method" value="redirect" checked class="d-none" />
                            </div>
                            <div class="mt-method-option" onclick="mtSelectMethod('move', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-arrow-right fa-lg me-3 text-primary"></i>
                                    <div class="flex-grow-1"><div class="fw-bold small">Move Thread</div><small class="text-muted">Remove from original forum</small></div>
                                </div>
                                <input type="radio" name="method" value="move" class="d-none" />
                            </div>
                            <div class="mt-method-option" onclick="mtSelectMethod('copy', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-copy fa-lg me-3 text-primary"></i>
                                    <div class="flex-grow-1"><div class="fw-bold small">Copy Thread</div><small class="text-muted">Keep original, create copy</small></div>
                                </div>
                                <input type="radio" name="method" value="copy" class="d-none" />
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="moveThreadForm" class="btn btn-primary btn-sm px-4"><i class="fas fa-exchange-alt me-1"></i>Process Thread</button>
                </div>
            </div>
        </div>
    </div>
    HTML;
}

function render_showthread(array $thread, string $multipage, string $addremovesubscription, string $newreply, string $pollbox, string $rating_html, string $posts, string $quickreply, string $search_thread, string $next_oldest_link, string $next_newest_link, string $addpoll, string $printthread, string $sendthread, string $moderationoptions, string $usersbrowsing, object $lang, string $baseurl, int $tid, int $thread_deleted): string
{
    return <<<HTML
    <div class="container-md">

        <div class="row mb-3 p-0 m-0">
            <div class="col p-0 align-self-center text-center text-lg-start mt-3 mt-lg-0">
                {$multipage}
            </div>
            <div class="col-auto m-0 p-0 text-center text-lg-end align-self-center order-first order-lg-last">
                {$addremovesubscription} {$newreply}
            </div>
        </div>
        {$rating_html}
        {$pollbox}

        <div id="posts">{$posts}</div>

        <div class="row mt-3 mb-3 p-0 m-0">
            <div class="col p-0 align-self-center text-center text-lg-start mb-3 mb-lg-0">
                {$multipage}
            </div>
            <div class="col-auto m-0 p-0 text-center text-lg-end align-self-center">
                {$newreply}
            </div>
        </div>

        {$quickreply}

        <div class="container-md">
            <div class="row mt-5 m-0 p-0 border-top border-bottom">
                <div class="col-12 col-lg-5 align-self-center py-3">
                    {$search_thread}
                </div>
                <div class="col text-center text-lg-end align-self-center py-3">
                    <a href="{$next_oldest_link}" class="links"><i class="fa-solid fa-angles-left"></i> {$lang->showthread['next_oldest']}</a>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <a href="{$next_newest_link}" class="links">{$lang->showthread['next_newest']} <i class="fa-solid fa-angles-right"></i></a>
                </div>
            </div>

            <div class="row m-0 p-0 mt-2 text-end">
                <div class="col-12 text-center text-lg-end align-self-center">
                    {$addpoll} {$printthread}&nbsp;&nbsp; {$sendthread}
                </div>
            </div>

            {$moderationoptions}
            {$usersbrowsing}
        </div>
    </div>

    <script>
    if(thread_deleted == "1") {
        document.getElementById('quick_reply_form') && (document.getElementById('quick_reply_form').style.display='none');
        document.querySelectorAll('#moderator_options_selector option.option_mirage').forEach(function(o){o.disabled=true;});
    }
    </script>
    HTML;
}



function render_report_modal(int $user_id): string
{
    global $mybb;
    $postKey = htmlspecialchars($mybb->post_code);

    return <<<HTML
    <div class="modal fade" id="reportForumPostModal" tabindex="-1" aria-labelledby="reportForumPostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-gradient bg-success text-white">
                    <h5 class="modal-title fw-semibold" id="reportForumPostModalLabel">
                        <i class="bi bi-flag-fill me-2"></i>Report Forum Post
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="reportForumPostForm" action="takereport.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="my_post_key"      value="{$postKey}">
                        <input type="hidden" name="type"             id="forumPostReportType"     value="forumpost">
                        <input type="hidden" name="reported_id"      id="forumPostReportedId"     value="">
                        <input type="hidden" name="addedby"          id="forumPostAddedBy"        value="{$user_id}">
                        <input type="hidden" name="reported_user_id" id="forumPostReportedUserId" value="">
                        <input type="hidden" name="forum_id"         id="forumPostForumId"        value="">
                        <input type="hidden" name="thread_id"        id="forumPostThreadId"       value="">

                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            Reporting: <strong id="reportingForumPost">Forum Post</strong>
                        </div>

                        <div class="card border mb-4">
                            <div class="card-header bg-light py-2">
                                <small class="text-muted fw-medium"><i class="bi bi-chat-left-text me-1"></i>Post Preview</small>
                            </div>
                            <div class="card-body py-3">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div>
                                                <span class="fw-medium" id="forumPostAuthorPreview">User</span>
                                                <span class="text-muted small ms-2" id="forumPostDatePreview"></span>
                                            </div>
                                            <span class="badge bg-success">Forum Post</span>
                                        </div>
                                        <h6 class="text-primary mb-2" id="forumPostSubjectPreview">Post Subject</h6>
                                        <div class="mb-2">
                                            <span class="badge bg-light text-dark me-2"><i class="bi bi-grid me-1"></i>Forum: <span id="forumPostForumPreview">General</span></span>
                                            <span class="badge bg-light text-dark"><i class="bi bi-chat-dots me-1"></i>Thread: <span id="forumPostThreadPreview">Discussion</span></span>
                                        </div>
                                        <p class="mb-0 text-muted" id="forumPostPreviewText">Post content will appear here...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="forumPostReportReason" class="form-label fw-medium">
                                <i class="bi bi-exclamation-triangle me-1"></i>Reason for Report
                            </label>
                            <select class="form-select form-select-lg" id="forumPostReportReason" name="reason" required>
                                <option value="" selected disabled>Select a reason...</option>
                                <optgroup label="Content Violations">
                                    <option value="spam">Spam / Advertising</option>
                                    <option value="offensive">Offensive / Abusive Language</option>
                                    <option value="harassment">Harassment / Bullying</option>
                                    <option value="hate_speech">Hate Speech / Discrimination</option>
                                    <option value="explicit">Explicit / Adult Content</option>
                                    <option value="illegal">Illegal Content / Warez</option>
                                </optgroup>
                                <optgroup label="Forum Rules">
                                    <option value="off_topic">Off Topic / Wrong Forum</option>
                                    <option value="double_post">Double Post / Cross-Posting</option>
                                    <option value="flame">Flaming / Trolling</option>
                                    <option value="personal_attack">Personal Attack</option>
                                    <option value="spoiler">Unmarked Spoilers</option>
                                </optgroup>
                                <optgroup label="Other Issues">
                                    <option value="copyright">Copyright Infringement</option>
                                    <option value="personal_info">Personal Information</option>
                                    <option value="malware">Malware Link</option>
                                    <option value="scam">Scam / Fraud</option>
                                    <option value="other">Other Reason</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="forumPostReportDetails" class="form-label fw-medium">
                                <i class="bi bi-chat-text me-1"></i>Additional Details
                            </label>
                            <textarea class="form-control" id="forumPostReportDetails" name="description"
                                      rows="4" placeholder="Please provide more details..." maxlength="2000"></textarea>
                            <div class="form-text d-flex justify-content-between mt-1">
                                <span>Optional but very helpful for moderators</span>
                                <span id="forumPostCharCount">0/2000</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="forumPostRuleViolation" class="form-label fw-medium">
                                <i class="bi bi-journal-text me-1"></i>Specific Rule Violation (Optional)
                            </label>
                            <select class="form-select" id="forumPostRuleViolation" name="rule_violation">
                                <option value="" selected>Not specified</option>
                                <option value="rule_1">Rule 1: No spamming or advertising</option>
                                <option value="rule_2">Rule 2: No offensive language</option>
                                <option value="rule_3">Rule 3: No harassment or bullying</option>
                                <option value="rule_4">Rule 4: Stay on topic</option>
                                <option value="rule_5">Rule 5: No warez or illegal content</option>
                                <option value="rule_6">Rule 6: Respect other members</option>
                                <option value="rule_7">Rule 7: No double posting</option>
                                <option value="rule_8">Rule 8: Use appropriate language</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="forumPostReportEmail" class="form-label fw-medium">
                                <i class="bi bi-envelope me-1"></i>Contact Email (Optional)
                            </label>
                            <input type="email" class="form-control" id="forumPostReportEmail"
                                   name="email" placeholder="your@email.com">
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <label class="form-label fw-medium mb-0">
                                    <i class="bi bi-shield-check me-1"></i>Security Check
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                                        id="forumPostRefreshCaptcha">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="row g-2 align-items-center">
                                <div class="col-6">
                                    <img src="report_captcha.php" alt="Security code" class="border rounded"
                                         id="forumPostCaptchaDisplay" style="cursor:pointer;height:56px;width:100%;object-fit:cover;"
                                         title="Click to refresh">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control"
                                           id="forumPostCaptchaInput" name="captcha_response" placeholder="Enter code" autocomplete="off">
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning small">
                            <div class="d-flex">
                                <i class="bi bi-exclamation-triangle me-2 fs-5"></i>
                                <div>
                                    <strong>Important:</strong> Please only report posts that violate our
                                    <a href="/forum/rules.php" class="alert-link">forum rules</a>.
                                    False reports may result in penalties.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success px-4" id="submitForumPostReport">
                            <i class="bi bi-send me-1"></i>Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    HTML;
}