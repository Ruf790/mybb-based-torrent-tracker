<?php

declare(strict_types=1);


define("IN_MYBB", 1);
define('THIS_SCRIPT', 'showthread.php');
define("SCRIPTNAME", "showthread.php");

$templatelist = "showthread,postbit,postbit_author_user,postbit_author_guest,showthread_newthread,showthread_newreply,showthread_newreply_closed,postbit_avatar,postbit_find,postbit_pm,postbit_www,postbit_email,postbit_edit,postbit_quote";
$templatelist .= ",multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,showthread_inlinemoderation_softdelete,showthread_poll_editpoll";
$templatelist .= ",postbit_editedby,showthread_similarthreads,showthread_similarthreads_bit,postbit_iplogged_show,postbit_iplogged_hiden,postbit_profilefield,showthread_quickreply,showthread_printthread,showthread_add_poll,showthread_send_thread,showthread_inlinemoderation_restore";
$templatelist .= ",forumjump_advanced,forumjump_special,forumjump_bit,postbit_attachments,postbit_attachments_attachment,postbit_attachments_thumbnails,postbit_attachments_images_image,postbit_attachments_images,showthread_quickreply_options_stick,postbit_status";
$templatelist .= ",postbit_inlinecheck,showthread_inlinemoderation,postbit_attachments_thumbnails_thumbnail,postbit_ignored,postbit_multiquote,showthread_moderationoptions_custom_tool,showthread_moderationoptions_custom,showthread_inlinemoderation_custom_tool";
$templatelist .= ",showthread_usersbrowsing,showthread_usersbrowsing_user,showthread_poll_option,showthread_poll,showthread_quickreply_options_signature,showthread_threaded_bitactive,showthread_threaded_bit,postbit_attachments_attachment_unapproved";
$templatelist .= ",showthread_moderationoptions_openclose,showthread_moderationoptions_stickunstick,showthread_moderationoptions_delete,showthread_moderationoptions_threadnotes,showthread_moderationoptions_manage,showthread_moderationoptions_deletepoll";
$templatelist .= ",postbit_userstar,postbit_reputation_formatted_link,postbit_warninglevel_formatted,postbit_quickrestore,forumdisplay_password,forumdisplay_password_wrongpass,postbit_purgespammer,showthread_inlinemoderation_approve,forumdisplay_thread_icon";
$templatelist .= ",showthread_moderationoptions_softdelete,showthread_moderationoptions_restore,showthread_moderationoptions,showthread_inlinemoderation_standard,showthread_inlinemoderation_manage";
$templatelist .= ",showthread_ratethread,postbit_posturl,postbit_icon,postbit_editedby_editreason,attachment_icon,global_moderation_notice,showthread_poll_option_multiple,postbit_gotopost,postbit_rep_button,postbit_warninglevel,showthread_threadnoteslink";
$templatelist .= ",showthread_moderationoptions_approve,showthread_moderationoptions_unapprove,showthread_inlinemoderation_delete,showthread_moderationoptions_standard,showthread_quickreply_options_close,showthread_inlinemoderation_custom,showthread_search";
$templatelist .= ",postbit_profilefield_multiselect_value,postbit_profilefield_multiselect,showthread_subscription,postbit_deleted_member,postbit_away,postbit_warn,postbit_classic,postbit_reputation,postbit_deleted,postbit_offline,postbit_online,postbit_signature";
$templatelist .= ",postbit_editreason,showthread_threadnotes_viewnotes,showthread_threadedbox,showthread_poll_resultbit,showthread_poll_results,showthread_threadnotes,showthread_classic_header,showthread_poll_undovote,postbit_groupimage,modal_delete,modal_edit";

define('IN_FORUM', true);
require_once 'global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_indicators.php';

$lang->load("showthread");

// ---------------------------------------------------------------------------
// Resolve pid → tid
// ---------------------------------------------------------------------------

if (!empty($mybb->input['pid']) && !$mybb->input['tid']) {
    if (isset($style) && $style['pid'] === $mybb->get_input('pid', MyBB::INPUT_INT) && $style['tid']) {
        $mybb->input['tid'] = $style['tid'];
        unset($style['tid']);
    } else {
        $query = $db->simple_select(
            "tsf_posts",
            "fid,tid,visible",
            "pid=" . $mybb->get_input('pid', MyBB::INPUT_INT),
            ["limit" => 1]
        );
        $post = $db->fetch_array($query);

        if (empty($post)) {
            stderr($lang->tsf_forums['invalid_post']);
        }

        $mybb->input['tid'] = $post['tid'];
    }
}

// ---------------------------------------------------------------------------
// Load thread
// ---------------------------------------------------------------------------

$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));

if (!$thread || str_starts_with((string)$thread['closed'], "moved|")) {
    stderr($lang->global['error_invalidthread']);
}

// Thread prefix
$thread['threadprefix'] = '';
$thread['displayprefix'] = '';

if ($thread['prefix'] != 0) {
    $threadprefix = build_prefixes($thread['prefix']);
    if (!empty($threadprefix['prefix'])) {
        $thread['threadprefix'] = htmlspecialchars_uni($threadprefix['prefix']) . '&nbsp;';
        $thread['displayprefix'] = $threadprefix['displaystyle'] . '&nbsp;';
    }
}

$reply_subject = $parser->parse_badwords($thread['subject']);
$thread['subject'] = htmlspecialchars_uni($reply_subject);

if (my_strlen($reply_subject) > 85) {
    $reply_subject = my_substr($reply_subject, 0, 82) . '...';
}

$reply_subject = htmlspecialchars_uni($reply_subject);
$tid = $thread['tid'];
$fid = $thread['fid'];

$thread['username'] = htmlspecialchars_uni($thread['username'] ?: 'guest');

$forumpermissions = forum_permissions($thread['fid']);

// ---------------------------------------------------------------------------
// Visible states
// ---------------------------------------------------------------------------

$visible_states = ["1", "-1"];
$is_mod = is_mod($usergroups);

if ($is_mod) {
    $visible_states[] = "0";
}

$visible_states = array_unique($visible_states);
$visible_condition = "visible IN (" . implode(',', $visible_states) . ")";

if ($CURUSER['id'] && $showownunapproved) {
    $own_unapproved = ' AND (%1$s' . $visible_condition . ' OR (%1$svisible=0 AND %1$suid=' . (int)$CURUSER['id'] . '))';
    $visibleonly     = sprintf($own_unapproved, null);
    $visibleonly_p   = sprintf($own_unapproved, 'p.');
    $visibleonly_p_t = sprintf($own_unapproved, 'p.') . sprintf($own_unapproved, 't.');
} else {
    $visibleonly     = " AND " . $visible_condition;
    $visibleonly_p   = " AND p." . $visible_condition;
    $visibleonly_p_t = "AND p." . $visible_condition . " AND t." . $visible_condition;
}

// ---------------------------------------------------------------------------
// Visibility check
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Action: newpost
// ---------------------------------------------------------------------------

if ($mybb->input['action'] === "newpost") {
    $lastread = $cutoff = 0;

    $query      = $db->simple_select("tsf_threadsread", "dateline", "uid='{$CURUSER['id']}' AND tid='{$thread['tid']}'");
    $thread_read = $db->fetch_field($query, "dateline");

    if ($threadreadcut > 0 && $CURUSER['id']) {
        $query      = $db->simple_select("tsf_forumsread", "dateline", "fid='{$fid}' AND uid='{$CURUSER['id']}'");
        $forum_read = $db->fetch_field($query, "dateline");
        $read_cutoff = TIMENOW - $threadreadcut * 86400;
        $forum_read  = (!$forum_read || $forum_read < $read_cutoff) ? $read_cutoff : $forum_read;
    } else {
        $forum_read = (int)my_get_array_cookie("forumread", $fid);
    }

    if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
        $cutoff = TIMENOW - $threadreadcut * 86400;
        $lastread = ($thread['lastpost'] > $cutoff) ? ($thread_read ?: 0) : 0;
    }

    if (!$lastread) {
        $readcookie = $threadread = (int)my_get_array_cookie("threadread", $thread['tid']);
        $lastread = ($readcookie > $forum_read) ? $readcookie : $forum_read;
    }

    if ($cutoff && $lastread < $cutoff) {
        $lastread = $cutoff;
    }

    $lastread = (int)$lastread;
    $query    = $db->simple_select(
        "tsf_posts",
        "pid",
        "tid='{$tid}' AND dateline > '{$lastread}' {$visibleonly}",
        ["limit_start" => 0, "limit" => 1, "order_by" => "dateline, pid"]
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

// ---------------------------------------------------------------------------
// Action: lastpost
// ---------------------------------------------------------------------------

if ($mybb->input['action'] === "lastpost") {
    if (str_contains((string)$thread['closed'], "moved|")) {
        $sql    = "SELECT p.pid FROM tsf_posts p LEFT JOIN tsf_threads t ON (p.tid = t.tid) WHERE t.fid = ? AND t.closed NOT LIKE 'moved|%' {$visibleonly_p_t} ORDER BY p.dateline DESC, p.pid DESC LIMIT 1";
        $params = [(int)$thread['fid']];
    } else {
        $sql    = "SELECT pid FROM tsf_posts WHERE tid = ? {$visibleonly} ORDER BY dateline DESC, pid DESC LIMIT 1";
        $params = [(int)$tid];
    }

    $query = $db->sql_query_prepared($sql, $params);
    $pid   = $db->fetch_field($query, "pid");

    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $tid)) . "#pid{$pid}");
    exit;
}

// ---------------------------------------------------------------------------
// Action: nextnewest
// ---------------------------------------------------------------------------

if ($mybb->input['action'] === "nextnewest") {
    $sql    = "SELECT * FROM tsf_threads WHERE fid = ? AND lastpost > ? {$visibleonly} AND closed NOT LIKE 'moved|%' ORDER BY lastpost ASC LIMIT 1";
    $params = [(int)$thread['fid'], (int)$thread['lastpost']];
    $query  = $db->sql_query_prepared($sql, $params);
    $nextthread = $db->fetch_array($query);

    if (!$nextthread) {
        stderr($lang->showthread['error_nonextnewest']);
    }

    $sql    = "SELECT pid FROM tsf_posts WHERE tid = ? ORDER BY dateline DESC, pid DESC LIMIT 1";
    $params = [(int)$nextthread['tid']];
    $query  = $db->sql_query_prepared($sql, $params);
    $pid    = $db->fetch_field($query, "pid");

    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $nextthread['tid'])) . "#pid{$pid}");
    exit;
}

// ---------------------------------------------------------------------------
// Action: nextoldest
// ---------------------------------------------------------------------------

if ($mybb->input['action'] === "nextoldest") {
    $sql    = "SELECT * FROM tsf_threads WHERE fid = ? AND lastpost < ? {$visibleonly} AND closed NOT LIKE 'moved|%' ORDER BY lastpost DESC LIMIT 1";
    $params = [(int)$thread['fid'], (int)$thread['lastpost']];
    $query  = $db->sql_query_prepared($sql, $params);
    $nextthread = $db->fetch_array($query);

    if (!$nextthread) {
        stderr($lang->showthread['error_nonextoldest']);
    }

    $sql    = "SELECT pid FROM tsf_posts WHERE tid = ? ORDER BY dateline DESC, pid DESC LIMIT 1";
    $params = [(int)$nextthread['tid']];
    $query  = $db->sql_query_prepared($sql, $params);
    $pid    = $db->fetch_field($query, "pid");

    header("Location: " . htmlspecialchars_decode(get_post_link($pid, $nextthread['tid'])) . "#pid{$pid}");
    exit;
}

// ---------------------------------------------------------------------------
// Breadcrumb / forum stats
// ---------------------------------------------------------------------------

$pid = $mybb->input['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);
$forum_stats = $cache->read("forumsdisplay");
$breadcrumb_multipage = [];

if ($showforumpagesbreadcrumb) {
    $f_threadsperpage = (int)$f_threadsperpage > 0 ? (int)$f_threadsperpage : 20;

    $query        = $db->simple_select("tsf_forums", "threads, unapprovedthreads, pid", "fid = '{$fid}'", ['limit' => 1]);
    $forum_threads = $db->fetch_array($query);
    $threadcount  = $forum_threads['threads'];

    $uid_only = '';
    if (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1) {
        $uid_only    = " AND uid = '" . $CURUSER['id'] . "'";
        $query       = $db->simple_select("tsf_threads", "COUNT(tid) AS threads", "fid = '$fid' $visibleonly $uid_only", ['limit' => 1]);
        $threadcount = $db->fetch_field($query, "threads");
    }

    if ($threadcount === 0) {
        $query       = $db->simple_select("tsf_threads", "COUNT(tid) AS threads", "fid = '$fid' $visibleonly $uid_only", ['limit' => 1]);
        $threadcount = $db->fetch_field($query, "threads");
    }

    $stickybit = $thread['sticky'] == 1 ? " AND sticky=1" : " OR sticky=1";

    $query = match ($db->type) {
        "pgsql"  => $db->sql_query("SELECT COUNT(tid) as threads FROM tsf_threads WHERE fid = '$fid' AND (lastpost >= '" . (int)$thread['lastpost'] . "'{$stickybit}) {$visibleonly} {$uid_only} GROUP BY lastpost ORDER BY lastpost DESC"),
        default  => $db->simple_select("tsf_threads", "COUNT(tid) as threads", "fid = '$fid' AND (lastpost >= '" . (int)$thread['lastpost'] . "'{$stickybit}) {$visibleonly} {$uid_only}", ['order_by' => 'lastpost', 'order_dir' => 'desc']),
    };

    $thread_position = $db->fetch_field($query, "threads");
    $thread_page     = (int)ceil($thread_position / $f_threadsperpage);

    $breadcrumb_multipage = [
        "num_threads"  => $threadcount,
        "current_page" => $thread_page,
    ];
}

build_forum_breadcrumb($fid, $breadcrumb_multipage);
add_breadcrumb($thread['displayprefix'] . $thread['subject'], get_thread_link($thread['tid']));

$plugins->run_hooks("showthread_start");

// ---------------------------------------------------------------------------
// Main thread view
// ---------------------------------------------------------------------------

if ($mybb->input['action'] === "thread") {

    if ($thread['firstpost'] == 0 || $thread['dateline'] == 0) {
        update_first_post($tid);
    }

    // -----------------------------------------------------------------------
    // Poll
    // -----------------------------------------------------------------------

    $pollbox = "";

    if ($thread['poll']) {
        $query = $db->simple_select("tsf_polls", "*", "pid='" . $thread['poll'] . "'", ["limit" => 1]);
        $poll  = $db->fetch_array($query);

        $poll['timeout'] = $poll['timeout'] * 86400;
        $expiretime      = $poll['dateline'] + $poll['timeout'];
        $now             = TIMENOW;

        $showresults = (
            $poll['closed'] == 1 ||
            $thread['closed'] == 1 ||
            ($expiretime < $now && $poll['timeout'] > 0)
        ) ? 1 : 0;

        $user_check = $CURUSER['id']
            ? "uid='{$CURUSER['id']}'"
            : "uid='0' AND ipaddress=" . $db->escape_binary($session->packedip);

        $alreadyvoted = 0;
        $votedfor     = [];

        $query = $db->simple_select("tsf_pollvotes", "*", "{$user_check} AND pid='" . $poll['pid'] . "'");
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

        $parser_options = [
            "allow_html"      => 1,
            "allow_mycode"    => 1,
            "allow_smilies"   => 1,
            "allow_imgcode"   => 1,
            "allow_videocode" => 1,
            "filter_badwords" => 1,
        ];

        for ($i = 1; $i <= $poll['numoptions']; ++$i) {
            $option  = $parser->parse_message($optionsarray[$i - 1], $parser_options);
            $votes   = (int)($votesarray[$i - 1] ?? 0);
            $totalvotes += $votes;
            $number  = $i;

            $optionbg = !empty($votedfor[$number]) ? "trow2 poll_votedfor" : "trow1";
            $votestar = !empty($votedfor[$number]) ? "*" : "";

            if ($alreadyvoted || $showresults) {
                $percent    = $poll['totvotes'] > 0 ? number_format($votes / $poll['totvotes'] * 100, 2) : "0";
                $imagewidth = (int)round((float)$percent);

                $polloptions .= <<<HTML
                <div class="row pt-3 border-top text-forum">
                    <div class="col-auto text-forum">{$option}{$votestar}</div>
                    <div class="col-lg text-end align-self-center">{$votes} ({$percent}%)</div>
                </div>
                <div class="row pb-3 text-forum">
                    <div class="col-lg">
                        <div class="progress mb-1" style="height: 25px">
                            <div class="progress-bar pg-primary" role="progressbar"
                                 style="width:{$percent}%" aria-valuenow="{$percent}"
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
                HTML;
            } else {
                if ($poll['multiple'] == 1) {
                    $polloptions .= <<<HTML
                    <div class="row py-3 border-top text-forum">
                        <div class="col" style="margin-bottom: 4px; width: 20px">
                            <label class="text-forum fw-normal">
                                <input type="checkbox" class="form-check-input"
                                       name="option[{$number}]" id="option_{$number}" value="1" />
                                &nbsp;&nbsp;{$option}
                            </label>
                        </div>
                    </div>
                    HTML;
                } else {
                    $polloptions .= <<<HTML
                    <div class="row py-3 border-top text-forum">
                        <div class="col" style="margin-bottom: 4px; width: 20px">
                            <label class="text-forum fw-normal">
                                <input type="radio" class="form-check-input"
                                       name="option" id="option_{$number}" value="{$number}" />
                                &nbsp;&nbsp;{$option}
                            </label>
                        </div>
                    </div>
                    HTML;
                }
            }
        }

        $totpercent = $poll['totvotes'] ? "100%" : "0%";

        $edit_poll = '';
        if (is_mod($usergroups)) {
            $edit_poll = '<a href="polls.php?action=editpoll&amp;pid=' . $poll['pid'] . '" title="Edit poll"><i class="fa-solid fa-pencil"></i> Edit poll</a>&nbsp;&nbsp;';
        }

        if ($alreadyvoted || $showresults) {
            $undovote    = '';
            $pollstatus  = '';

            if ($alreadyvoted) {
                $pollstatus = 'You have already voted in this poll';
                $undovote   = '[<a href="polls.php?action=do_undovote&amp;pid=' . $poll['pid'] . '&amp;my_post_key=' . $mybb->post_code . '">Undo vote</a>]';
            }

            $total_votes = $totalvotes . ' vote(s)';

            $pollbox = <<<HTML
            <div class="card border-0 mb-4">
                <div class="card-header rounded text-19 fw-bold">
                    {$poll['question']}<span class="badge bg-primary border border-white float-end text-uppercase small fw-normal">Poll:</span>
                </div>
                <div class="card-body">
                    <div class="row pb-3">
                        <div class="col"><span class="text-desc">{$pollstatus} {$undovote}</span></div>
                    </div>
                    {$polloptions}
                    <div class="text-end"><span class="text-forum">Total {$total_votes} {$totpercent}</span></div>
                </div>
                <div class="card-footer border-top-0 rounded">
                    <div class="row g-1">
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm" value="Vote!">Vote!</button>
                        </div>
                        <div class="col text-end align-self-center">
                            {$edit_poll} &nbsp;
                            <a href="polls.php?action=showresults&amp;pid={$poll['pid']}">
                                <i class="fa-solid fa-square-poll-horizontal"></i> Show Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            HTML;

            $plugins->run_hooks("showthread_poll_results");
        } else {
            $closeon    = '&nbsp;';
            $publicnote = '&nbsp;';

            if ($poll['timeout'] != 0) {
                $closeon = 'This poll will close on: ' . my_datee($dateformat, $expiretime);
            }

            if ($poll['public'] == 1) {
                $publicnote = '<b>Note:</b> This is a public poll, other users will be able to see what you voted for';
            }

            $pollbox = <<<HTML
            <form action="polls.php" method="post">
                <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                <input type="hidden" name="action" value="vote" />
                <input type="hidden" name="pid" value="{$poll['pid']}" />
                <div class="card border-0 mb-4">
                    <div class="card-header rounded text-19 fw-bold">
                        {$poll['question']}<span class="badge bg-primary border border-white float-end text-uppercase small fw-normal">Poll:</span>
                    </div>
                    <div class="card-body">
                        <div class="row pb-3">
                            <div class="col"><span class="text-desc">{$publicnote} {$closeon}</span></div>
                        </div>
                        {$polloptions}
                    </div>
                    <div class="card-footer border-top-0 rounded">
                        <div class="row g-1">
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-sm" value="Vote!">Vote!</button>
                            </div>
                            <div class="col text-end align-self-center">
                                {$edit_poll}
                                <a href="polls.php?action=showresults&amp;pid={$poll['pid']}">
                                    <i class="fa-solid fa-square-poll-horizontal"></i> Show Results
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            HTML;

            $plugins->run_hooks("showthread_poll");
        }
    }

    // -----------------------------------------------------------------------
    // Forum jump, links, mark read
    // -----------------------------------------------------------------------

    $forumjump        = build_forum_jump("", $fid, 1);
    $next_oldest_link = get_thread_link($tid, 0, "nextoldest");
    $next_newest_link = get_thread_link($tid, 0, "nextnewest");

    mark_thread_read((int)$tid, (int)$fid);

    // -----------------------------------------------------------------------
    // New thread / reply buttons
    // -----------------------------------------------------------------------

    $newthread = $newreply = '';

    if ($forum['open'] != 0 && $forum['type'] === "f") {
        if ($forumpermissions['canpostthreads'] != 0) {
            $newthread = '<a href="newthread.php?fid=' . $fid . '" class="button new_thread_button"><span>{$lang->post_thread}</span></a>&nbsp;';
        }

        $canReply = $forumpermissions['canpostreplys'] != 0
            && ($thread['closed'] != 1 || $is_mod)
            && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']));

        if ($canReply) {
            eval("\$newreply = \"" . $templates->get("showthread_newreply") . "\";");
        } elseif ($thread['closed'] == 1) {
            $newreply = <<<HTML
            <span class="d-inline-block" tabindex="0" data-bs-toggle="popover"
                  title="{$thread['subject']}" data-bs-content="Thread Closed">
                <button class="btn btn-primary" type="button" disabled>Thread Closed</button>
            </span>
            HTML;
        }
    }

    // -----------------------------------------------------------------------
    // Moderation tools
    // -----------------------------------------------------------------------

    $is_mod = is_mod($usergroups);

    if ($is_mod) {
        $closelinkch = $thread['closed'] == 1 ? ' checked="checked"' : '';
        $stickch     = $thread['sticky']  ? ' checked="checked"' : '';

        $closeoption  = '<input type="checkbox" class="form-check-input" name="modoptions[closethread]" value="1"' . $closelinkch . ' /> ' . $lang->showthread['close_thread'] . ' <br />';
        $closeoption .= '<input type="checkbox" class="form-check-input" name="modoptions[stickthread]" value="1"' . $stickch . ' /> ' . $lang->showthread['stick_thread'];

        $inlinecount  = "0";
        $inlinecookie = "inlinemod_thread" . $tid;

        $plugins->run_hooks("showthread_ismod");
    } else {
        $modoptions = "&nbsp;";
        $inlinemod = $closeoption = '';
    }

    // -----------------------------------------------------------------------
    // Thread views counter
    // -----------------------------------------------------------------------

    $threadviews_countthreadauthor = "1";
    $threadviews_countspiders      = "0";
    $threadviews_countguests       = "1";

    $shouldCount = match (true) {
        $CURUSER['id'] == 0 && $session->is_spider && $threadviews_countspiders == 1   => true,
        $CURUSER['id'] == 0 && !$session->is_spider && $threadviews_countguests == 1   => true,
        $CURUSER['id'] != 0 && ($threadviews_countthreadauthor == 1 || $CURUSER['id'] !== $thread['uid']) => true,
        default => false,
    };

    if ($shouldCount) {
        if ($delayedthreadviews == 1) {
            $db->shutdown_query("INSERT INTO tsf_threadviews (tid) VALUES('{$tid}')");
        } else {
            $db->shutdown_query("UPDATE tsf_threads SET views=views+1 WHERE tid='{$tid}'");
        }
        ++$thread['views'];
    }

    // -----------------------------------------------------------------------
    // Search thread
    // -----------------------------------------------------------------------

    $search_thread = '';
    if ($forumpermissions['cansearch'] != 0) {
        eval("\$search_thread = \"" . $templates->get("showthread_search") . "\";");
    }

    // -----------------------------------------------------------------------
    // Ignored users
    // -----------------------------------------------------------------------

    $ignored_users = [];
    if ($CURUSER['id'] > 0 && !empty($CURUSER['ignorelist'])) {
        foreach (explode(',', $CURUSER['ignorelist']) as $uid) {
            $ignored_users[(int)$uid] = 1;
        }
    }

    // -----------------------------------------------------------------------
    // Thread mode / pagination
    // -----------------------------------------------------------------------

    $defaultmode = match (true) {
        !empty($CURUSER['threadmode']) => $CURUSER['threadmode'],
        $threadusenetstyle == 1        => 'threaded',
        default                        => 'linear',
    };

    $mybb->input['mode'] ??= $defaultmode;

    $thread_toggle = 'threaded';
    $threadexbox   = '';
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
            $footer .= '<script type="text/javascript">$(function() { $.jGrowl(\'' . $lang->error_invalidpost . '\', {theme: \'jgrowl_error\'}); });</script>';
        } else {
            $sql    = "SELECT COUNT(p.dateline) AS count FROM tsf_posts p WHERE p.tid = ? AND p.dateline <= ? {$visibleonly_p}";
            $params = [(int)$tid, (int)$post['dateline']];
            $query  = $db->sql_query_prepared($sql, $params);
            $result = (int)$db->fetch_field($query, "count");

            $page = ($result % $perpage === 0)
                ? $result / $perpage
                : (int)($result / $perpage) + 1;
        }
    }

    // Recount replies for mods
    if ($visible_states !== ["1"]) {
        $cached_replies = $thread['replies'] + $thread['unapprovedposts'];
        $query          = $db->simple_select("tsf_posts p", "COUNT(*) AS replies", "p.tid='$tid' $visibleonly_p");
        $thread['replies'] = (int)$db->fetch_field($query, 'replies') - 1;

        if (in_array('-1', $visible_states) && in_array('0', $visible_states)) {
            if ($thread['replies'] != $cached_replies) {
                require_once INC_PATH . "/functions_rebuild.php";
                rebuild_thread_counters($thread['tid']);
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
    $upper = $start + $perpage;

    // Highlight & threadmode query strings
    $highlight  = "";
    $threadmode = "";

    if ($mybb->seo_support) {
        if ($mybb->get_input('highlight')) {
            $highlight = "?highlight=" . urlencode($mybb->get_input('highlight'));
        }
        if ($defaultmode !== "linear") {
            $threadmode = $highlight ? "&amp;mode=linear" : "?mode=linear";
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
        if ($defaultmode !== "linear") {
            $threadmode = "&amp;mode=linear";
        }
    }

    $multipage = multipage($postcount, $perpage, $page, str_replace("{tid}", $tid, THREAD_URL_PAGED . $highlight . $threadmode));

    // -----------------------------------------------------------------------
    // Fetch post IDs
    // -----------------------------------------------------------------------

    $pids  = "";
    $comma = '';
    $query = $db->simple_select(
        "tsf_posts p",
        "p.pid",
        "p.tid='$tid' $visibleonly_p",
        ['order_by' => 'p.dateline, p.pid', 'limit_start' => $start, 'limit' => $perpage]
    );

    while ($getid = $db->fetch_array($query)) {
        if (empty($pid)) {
            $pid = $getid['pid'];
        }
        $pids  .= "{$comma}'{$getid['pid']}'";
        $comma  = ",";
    }

    if (!$pids) {
        stderr($lang->global['error_invalidthread']);
    }

    $pids       = "pid IN($pids)";
    $attachcache = [];

    if ($thread['attachmentcount'] > 0) {
        $query = $db->simple_select("attachments", "*", $pids);
        while ($attachment = $db->fetch_array($query)) {
            $attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
        }
    }

    // -----------------------------------------------------------------------
    // Build posts
    // -----------------------------------------------------------------------

    $posts = '';
    $query = $db->sql_query("
        SELECT u.*, u.username AS userusername, p.*, f.*, eu.username AS editusername
        FROM tsf_posts p
        LEFT JOIN users u ON (u.id=p.uid)
        LEFT JOIN userfields f ON (f.ufid=u.id)
        LEFT JOIN users eu ON (eu.id=p.edituid)
        WHERE $pids
        ORDER BY p.dateline, p.pid
    ");

    while ($post = $db->fetch_array($query)) {
        if ($thread['firstpost'] == $post['pid'] && $thread['visible'] == 0) {
            $post['visible'] = 0;
        }
        $posts .= build_postbit($post);
        $post   = '';
    }

    $plugins->run_hooks("showthread_linear");

    // -----------------------------------------------------------------------
    // Quick reply
    // -----------------------------------------------------------------------

    $quickreply = '';
    $canQuickReply = $forumpermissions['canpostreplys'] != 0
        && ($thread['closed'] != 1 || $is_mod)
        && $forum['open'] != 0
        && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']));

    if ($canQuickReply) {
        $query    = $db->simple_select("tsf_posts", "pid", "tid='{$tid}'", ["order_by" => "pid", "order_dir" => "desc", "limit" => 1]);
        $last_pid = $db->fetch_field($query, "pid");

        $postoptionschecked = [
            'signature'   => $CURUSER['signature'] ? 'checked="checked"' : '',
            'emailnotify' => '',
        ];

        $option_signature = '';
        $trow = $thread['closed'] == 1 ? 'trow_shaded' : alt_trow();

        $moderation_notice = '';
        if ($CURUSER['moderateposts'] == 1) {
            $moderation_text = $lang->showthread['moderation_user_posts'];
            eval('$moderation_notice = "' . $templates->get('global_moderation_notice') . '";');
        }

        // Collapse cookies
        $collapse = $collapsed = $collapsedimg = $collapsedthead = [];

        if (!empty($mybb->cookies['collapsed'])) {
            $collapse = explode("|", $mybb->cookies['collapsed']);
            foreach ($collapse as $val) {
                $collapsed[$val . "_e"]  = "display: none;";
                $collapsedimg[$val]      = "_collapsed";
                $collapsedthead[$val]    = " thead_collapsed";
            }
        }

        $posthash = md5($CURUSER['id'] . random_str());

        $collapsedthead['quickreply'] ??= '';
        $collapsedimg['quickreply']   ??= '';
        $collapsed['quickreply_e']    ??= '';

        $expaltext = in_array("quickreply", $collapse) ? '[+]' : '[-]';

        eval("\$quickreply = \"" . $templates->get("showthread_quickreply") . "\";");
    }

    // -----------------------------------------------------------------------
    // Moderation options
    // -----------------------------------------------------------------------

    $moderationoptions = '';
    $threadnotesbox = $viewnotes = '';

    $customthreadtools = $customposttools = $standardthreadtools = $standardposttools = '';

    if (!empty($thread['notes'])) {
        $thread['notes'] = nl2br(htmlspecialchars_uni($thread['notes']));

        if (strlen($thread['notes']) > 200) {
            eval("\$viewnotes = \"" . $templates->get("showthread_threadnotes_viewnotes") . "\";");
            $thread['notes'] = my_substr($thread['notes'], 0, 200) . "... {$viewnotes}";
        }

        $expaltext = in_array("threadnotes", $collapse ?? [])
            ? $lang->expcol_expand
            : $lang->expcol_collapse;

        $threadnotesbox = '<table border="0" cellspacing="{$theme[borderwidth]}" cellpadding="{$theme[tablespace]}" class="tborder tfixed">...</table><br />';
    }

    // Custom mod tools
    $gids = array_filter(array_unique([...(array)explode(',', (string)($CURUSER['additionalgroups'] ?? '')), $CURUSER['usergroup']]));
    $gidswhere = '';

    foreach ($gids as $gid) {
        $gid        = (int)$gid;
        $gidswhere .= match ($db->type) {
            "pgsql", "sqlite" => " OR ','||groups||',' LIKE '%,{$gid},%'",
            default           => " OR CONCAT(',',`groups`,',') LIKE '%,{$gid},%'",
        };
    }

    $query = match ($db->type) {
        "pgsql", "sqlite" => $db->simple_select("modtools", 'tid, name, type', "(','||forums||',' LIKE '%,$fid,%' OR ','||forums||',' LIKE '%,-1,%' OR forums='') AND (groups='' OR ','||groups||',' LIKE '%,-1,%'{$gidswhere})"),
        default           => $db->simple_select("modtools", 'tid, name, type', "(CONCAT(',',forums,',') LIKE '%,$fid,%' OR CONCAT(',',forums,',') LIKE '%,-1,%' OR forums='') AND (`groups`='' OR CONCAT(',',`groups`,',') LIKE '%,-1,%'{$gidswhere})"),
    };

    while ($tool = $db->fetch_array($query)) {
        $tool['name'] = htmlspecialchars_uni($tool['name']);
        if ($tool['type'] === 'p') {
            eval("\$customposttools .= \"" . $templates->get("showthread_inlinemoderation_custom_tool") . "\";");
        } else {
            eval("\$customthreadtools .= \"" . $templates->get("showthread_moderationoptions_custom_tool") . "\";");
        }
    }

    if (!empty($customposttools)) {
        eval("\$customposttools = \"" . $templates->get("showthread_inlinemoderation_custom") . "\";");
    }

    $inlinemodsoftdelete = $inlinemodrestore = $inlinemoddelete = $inlinemodmanage = $inlinemodapprove = '';
    $inlinemoddelete = '<option value="multideleteposts">Delete Posts Permanently</option>';

    $inlinemodmanage = '<option value="multimergeposts">' . $lang->tsf_forums["mergeposts"] . '</option>'
        . '<option value="multisplitposts">' . $lang->showthread['inline_split_posts'] . '</option>'
        . '<option value="multimoveposts">' . $lang->showthread['inline_move_posts'] . '</option>';

    eval("\$inlinemodapprove = \"" . $templates->get("showthread_inlinemoderation_approve") . "\";");

    $standardposttools = '<optgroup label="Standard Tools">' . $inlinemoddelete . $inlinemodmanage . $inlinemodapprove . '</optgroup>';

    eval("\$inlinemod = \"" . $templates->get("showthread_inlinemoderation") . "\";");

    if (!empty($customthreadtools)) {
        eval("\$customthreadtools = \"" . $templates->get("showthread_moderationoptions_custom") . "\";");
    }

    $openclosethread = $stickunstickthread = $deletethread = $threadnotes = '';
    $managethread    = $adminpolloptions   = $approveunapprovethread = '';
    $softdeletethread = '';

    $openclosethread   = '<option class="option_mirage" value="openclosethread">Open/Close Thread</option>';
    $stickunstickthread = '<option class="option_mirage" value="stick">Stick/Unstick Thread</option>';
    $deletethread       = '<option value="deletethread">Delete Thread Permanently</option>';
    $managethread       = '<option class="option_mirage" value="move">Move / Copy Thread</option>'
        . '<option class="option_mirage" value="merge">Merge Threads</option>'
        . '<option class="option_mirage" value="split">Split Thread</option>';

    if ($pollbox && $is_mod) {
        $adminpolloptions = '<option class="option_mirage" value="deletepoll">{$lang->delete_poll}</option>';
    }

    if ($thread['visible'] == 0) {
        eval("\$approveunapprovethread = \"" . $templates->get("showthread_moderationoptions_approve") . "\";");
    } else {
        eval("\$approveunapprovethread = \"" . $templates->get("showthread_moderationoptions_unapprove") . "\";");
    }

    $gobutton = '<button type="submit" class="btn btn-sm btn-primary rounded" value="Go"><i class="fa-solid fa-shuffle"></i> &nbsp;Go</button>';
    eval("\$standardthreadtools = \"" . $templates->get("showthread_moderationoptions_standard") . "\";");

    $forumselect = build_forum_jump("", $fid, 1, '', 0, true, '', "moveto");
	
	
	if ($usergroups['canstaffpanel'] === '1') {
        eval("\$moderationoptions = \"" . $templates->get("showthread_moderationoptions") . "\";");
    }

    // -----------------------------------------------------------------------
    // Extra links
    // -----------------------------------------------------------------------

    $printthread = '<a href="printthread.php?tid=' . $tid . '" class="links"><i class="fa-solid fa-print" title="{$lang->view_printable}"></i></a>';

    $sendthread = ($usergroups['cansendemail'] == 1)
        ? '<a href="sendthread.php?tid=' . $tid . '" class="links"><i class="fa-solid fa-reply" title="{$lang->send_thread}"></i></a>'
        : '';

    $addpoll    = '';
    $time       = TIMENOW;
    $polltimelimit = "12";

    if (
        !$thread['poll'] &&
        ($thread['uid'] == $CURUSER['id'] || $is_mod) &&
        $forum['open'] != 0 &&
        $thread['closed'] != 1 &&
        ($thread['dateline'] > ($time - ($polltimelimit * 3600)) || $polltimelimit == 0)
    ) {
        eval("\$addpoll = \"" . $templates->get("showthread_add_poll") . "\";");
    }

    // -----------------------------------------------------------------------
    // Subscription
    // -----------------------------------------------------------------------

    $add_remove_subscription      = 'add';
    $add_remove_subscription_text = $lang->showthread['subscribe_thread'];
    $addremovesubscription        = '';

    if ($CURUSER['id']) {
        $query = $db->simple_select(
            "tsf_threadsubscriptions",
            "tid",
            "tid='" . (int)$tid . "' AND uid='" . (int)$CURUSER['id'] . "'",
            ['limit' => 1]
        );

        if ($db->num_rows($query) > 0) {
            $add_remove_subscription      = 'remove';
            $add_remove_subscription_text = $lang->showthread['unsubscribe_thread'];
        }

        eval("\$addremovesubscription = \"" . $templates->get("showthread_subscription") . "\";");
    }

    $classic_header = '';

    // -----------------------------------------------------------------------
    // Users browsing
    // -----------------------------------------------------------------------

    $usersbrowsing = '';

    if ($browsingthisthread != 0) {
        $timecut     = TIMENOW - $wolcutoffmins;
        $guestcount  = 0;
        $membercount = 0;
        $inviscount  = 0;
        $onlinemembers = '';
        $doneusers   = [];

        $query      = $db->simple_select("sessions", "COUNT(DISTINCT ip) AS guestcount", "uid = 0 AND time > $timecut AND location2 = $tid AND nopermission != 1");
        $guestcount = (int)$db->fetch_field($query, 'guestcount');

        $query = $db->sql_query("
            SELECT s.ip, s.uid, s.time, u.username, u.invisible, u.usergroup, u.displaygroup
            FROM sessions s
            LEFT JOIN users u ON (s.uid=u.id)
            WHERE s.uid != 0 AND s.time > '$timecut' AND location2='$tid' AND nopermission != 1
            ORDER BY u.username ASC, s.time DESC
        ");

        while ($user = $db->fetch_array($query)) {
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

                    eval("\$onlinemembers .= \"" . $templates->get("showthread_usersbrowsing_user", true, false) . "\";");
                }
            }
        }

        $guestsonline = $guestcount
            ? sprintf($lang->showthread['users_browsing_thread_guests'], $guestcount)
            : '';

        $invisonline = '';
        if ($CURUSER['invisible'] == 1) {
            --$inviscount;
        }

        if ($inviscount && $usergroups['canviewwolinvis'] != 1) {
            $invisonline = sprintf($lang->showthread['users_browsing_thread_invis'], $inviscount);
        }

        $onlinesep  = ($invisonline !== '' && $onlinemembers) ? ', ' : '';
        $onlinesep2 = ($invisonline !== '' && $guestcount) || ($onlinemembers && $guestcount) ? ', ' : '';

        eval("\$usersbrowsing = \"" . $templates->get("showthread_usersbrowsing") . "\";");
    }

    $test           = get_forum_link($fid);
    $thread_deleted = $thread['visible'] == -1 ? 1 : 0;

    $plugins->run_hooks("showthread_end");

    eval("\$showthread = \"" . $templates->get("showthread") . "\";");

    stdhead($thread['subject']);
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            var title = el.getAttribute('data-bs-title') || el.getAttribute('title');
            if (title) el.setAttribute('title', title);
        });
    });
    </script>
    <?php

    build_breadcrumb();
    echo $showthread;

    // -----------------------------------------------------------------------
    // Report modal
    // -----------------------------------------------------------------------

    $userId = (int)$CURUSER['id'];

    echo <<<HTML
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
                        <input type="hidden" name="type"             id="forumPostReportType"     value="forumpost">
                        <input type="hidden" name="reported_id"      id="forumPostReportedId"     value="">
                        <input type="hidden" name="addedby"          id="forumPostAddedBy"        value="{$userId}">
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

    stdfoot();
}