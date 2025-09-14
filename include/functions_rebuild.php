<?php
declare(strict_types=1);

/**
 * Rebuild all board statistics (threads, posts, users)
 */
function rebuild_stats(): void
{
    global $db;

    $query = $db->simple_select(
        "tsf_forums",
        "SUM(threads) AS numthreads, SUM(posts) AS numposts, SUM(unapprovedthreads) AS numunapprovedthreads, SUM(unapprovedposts) AS numunapprovedposts"
    );
    $stats = $db->fetch_array($query);

    $query = $db->simple_select("users", "COUNT(id) AS numusers");
    $stats['numusers'] = (int) $db->fetch_field($query, 'numusers');

    update_stats($stats, true);
}

/**
 * Rebuild counters for a specific forum
 */
function rebuild_forum_counters(int $fid): void
{
    global $db;

    // Approved threads/posts
    $query = $db->simple_select(
        'tsf_threads',
        'COUNT(tid) AS threads, SUM(replies) AS replies, SUM(unapprovedposts) AS unapprovedposts',
        "fid='{$fid}' AND visible='1'"
    );
    $count = $db->fetch_array($query);
    $count['threads'] = (int) $count['threads'];
    $count['replies'] = (int) $count['replies'];
    $count['posts'] = $count['threads'] + $count['replies'];
    $count['unapprovedposts'] = (int) $count['unapprovedposts'];

    // Unapproved threads/posts
    $query = $db->simple_select(
        'tsf_threads',
        'COUNT(tid) AS threads, SUM(replies)+SUM(unapprovedposts) AS impliedunapproved',
        "fid='{$fid}' AND visible='0'"
    );
    $count2 = $db->fetch_array($query);
    $count['unapprovedthreads'] = (int) $count2['threads'];
    $count['unapprovedposts'] += (int) $count2['impliedunapproved'] + (int) $count2['threads'];

    update_forum_counters($fid, $count);
    update_forum_lastpost($fid);
}

/**
 * Rebuild counters for a specific thread
 */
function rebuild_thread_counters(int $tid): void
{
    global $db;

    $thread = get_thread($tid);
    if (!$thread) {
        return;
    }

    $count = [];

    // Approved replies
    $query = $db->simple_select(
        "tsf_posts",
        "COUNT(pid) AS replies",
        "tid='{$tid}' AND pid!='{$thread['firstpost']}' AND visible='1'"
    );
    $count['replies'] = (int) $db->fetch_field($query, "replies");

    // Unapproved replies
    $query = $db->simple_select(
        "tsf_posts",
        "COUNT(pid) AS unapprovedposts",
        "tid='{$tid}' AND pid!='{$thread['firstpost']}' AND visible='0'"
    );
    $count['unapprovedposts'] = (int) $db->fetch_field($query, "unapprovedposts");

    // Attachment count
    $query = $db->query("
        SELECT COUNT(aid) AS attachment_count
        FROM attachments a
        LEFT JOIN tsf_posts p ON a.pid=p.pid
        WHERE p.tid='{$tid}' AND a.visible=1
    ");
    $count['attachmentcount'] = (int) $db->fetch_field($query, "attachment_count");

    update_thread_counters($tid, $count);
    update_thread_data($tid);
}

/**
 * Rebuild poll counters
 */
function rebuild_poll_counters(int $pid): void
{
    global $db;

    $query = $db->simple_select("tsf_polls", "pid, numoptions", "pid='{$pid}'");
    $poll = $db->fetch_array($query);
    if (!$poll) {
        return;
    }

    $votes = [];
    $query = $db->simple_select(
        "tsf_pollvotes",
        "voteoption, COUNT(vid) AS vote_count",
        "pid='{$poll['pid']}'",
        ['group_by' => 'voteoption']
    );
    while ($vote = $db->fetch_array($query)) {
        $votes[(int)$vote['voteoption']] = (int)$vote['vote_count'];
    }

    $votesList = [];
    $numVotes = 0;
    for ($i = 1; $i <= (int)$poll['numoptions']; $i++) {
        $votesList[] = $votes[$i] ?? 0;
        $numVotes += $votes[$i] ?? 0;
    }

    $updatedPoll = [
        "votes" => $db->escape_string(implode('||~|~||', $votesList)),
        "numvotes" => $numVotes
    ];
    $db->update_query("tsf_polls", $updatedPoll, "pid='{$poll['pid']}'");
}

