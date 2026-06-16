<?php


declare(strict_types=1);

class Moderation
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Normalize input to a clean array of positive integers.
     */
    private function normalizeIds(array|int $ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * Build "moved|N, moved|N2, ..." SQL list from tid array.
     */
    private function buildMovedList(array $tids): string
    {
        return implode(',', array_map(fn(int $t) => "'moved|{$t}'", $tids));
    }

    // ── unapprove_threads ─────────────────────────────────────────────────────

    public function unapprove_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $tid_list       = implode(',', $tids);
        $tid_moved_list = $this->buildMovedList($tids);

        $forum_counters = [];
        $user_counters  = [];
        $posts_to_unapprove = [];

        $query = $db->simple_select('threads', '*', "tid IN ({$tid_list})");
        while ($thread = $db->fetch_array($query)) {
            $forum = get_forum($thread['fid']);
            $fid   = $forum['fid'];

            if ((int)$thread['visible'] === 1 || (int)$thread['visible'] === -1) {
                $forum_counters[$fid] ??= [
                    'num_threads'           => 0,
                    'num_posts'             => 0,
                    'num_unapprovedthreads' => 0,
                    'num_unapprovedposts'   => 0,
                    'num_deletedthreads'    => 0,
                    'num_deletedposts'      => 0,
                ];
                $user_counters[$thread['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];

                ++$forum_counters[$fid]['num_unapprovedthreads'];
                $forum_counters[$fid]['num_unapprovedposts'] += $thread['replies'] + $thread['deletedposts'] + 1;

                if ((int)$thread['visible'] === 1) {
                    ++$forum_counters[$fid]['num_threads'];
                    $forum_counters[$fid]['num_posts']        += $thread['replies'] + 1;
                    $forum_counters[$fid]['num_deletedposts'] += $thread['deletedposts'];
                } else {
                    ++$forum_counters[$fid]['num_deletedthreads'];
                    $forum_counters[$fid]['num_deletedposts']   += $thread['replies'] + $thread['unapprovedposts'] + $thread['deletedposts'] + 1;
                    $forum_counters[$fid]['num_unapprovedposts'] += $thread['unapprovedposts'];
                }

                if ((int)$thread['visible'] === 1 && $forum['usepostcounts'] != 0) {
                    $q2 = $db->simple_select(
                        'posts', 'COUNT(pid) AS posts, uid',
                        "tid='{$thread['tid']}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid"
                    );
                    while ($counter = $db->fetch_array($q2)) {
                        $user_counters[$counter['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];
                        $user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
                    }
                }

                if ((int)$thread['visible'] === 1 && $forum['usethreadcounts'] != 0 && !str_starts_with((string)$thread['closed'], 'moved|')) {
                    ++$user_counters[$thread['uid']]['num_threads'];
                }
            }

            $posts_to_unapprove[] = (int)$thread['firstpost'];
        }

        $db->update_query('threads', ['visible' => 0], "tid IN ({$tid_list})");

        // Unapprove redirects too
        $redirect_tids = [];
        $query = $db->simple_select('threads', 'tid', "closed IN ({$tid_moved_list})");
        while ($redirect_tid = $db->fetch_field($query, 'tid')) {
            $redirect_tids[] = (int)$redirect_tid;
        }
        if (!empty($redirect_tids)) {
            $this->unapprove_threads($redirect_tids);
        }

        if (!empty($posts_to_unapprove)) {
            $db->update_query('posts', ['visible' => 0], 'pid IN (' . implode(',', $posts_to_unapprove) . ')');
        }

        $plugins->run_hooks('class_moderation_unapprove_threads', $tids);

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'threads'           => "-{$c['num_threads']}",
                'unapprovedthreads' => "+{$c['num_unapprovedthreads']}",
                'posts'             => "-{$c['num_posts']}",
                'unapprovedposts'   => "+{$c['num_unapprovedposts']}",
                'deletedthreads'    => "-{$c['num_deletedthreads']}",
                'deletedposts'      => "-{$c['num_deletedposts']}",
            ]);
            update_forum_lastpost($fid);
        }

        foreach ($user_counters as $uid => $c) {
            update_user_counters($uid, [
                'postnum'   => "-{$c['num_posts']}",
                'threadnum' => "-{$c['num_threads']}",
            ]);
        }

        return true;
    }

    // ── approve_posts ─────────────────────────────────────────────────────────

    public function approve_posts(array $pids): bool
    {
        global $db, $plugins;

        if (empty($pids)) {
            return false;
        }

        $pids     = $this->normalizeIds($pids);
        $pid_list = implode(',', $pids);

        // Find first posts of unapproved threads → approve whole thread
        $threads_to_update = [];
        $query = $db->sql_query("
            SELECT p.tid
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$pid_list}) AND p.visible = '0' AND t.firstpost = p.pid AND t.visible = 0
        ");
        while ($post = $db->fetch_array($query)) {
            $threads_to_update[] = (int)$post['tid'];
        }
        if (!empty($threads_to_update)) {
            $this->approve_threads($threads_to_update);
        }

        $thread_counters = [];
        $forum_counters  = [];
        $user_counters   = [];
        $approved_pids   = [];

        $query = $db->sql_query("
            SELECT p.pid, p.tid, p.fid, p.uid, t.visible AS threadvisible
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$pid_list}) AND p.visible = '0' AND t.firstpost != p.pid
        ");
        while ($post = $db->fetch_array($query)) {
            $approved_pids[] = (int)$post['pid'];

            $thread_counters[$post['tid']] ??= ['replies' => 0];
            ++$thread_counters[$post['tid']]['replies'];

            if ((int)$post['threadvisible'] === 1) {
                $forum_counters[$post['fid']] ??= ['num_posts' => 0];
                ++$forum_counters[$post['fid']]['num_posts'];

                $forum = get_forum($post['fid']);
                if ($forum['usepostcounts'] != 0) {
                    $user_counters[$post['uid']] ??= 0;
                    ++$user_counters[$post['uid']];
                }
            }
        }

        if (empty($approved_pids) && empty($threads_to_update)) {
            return false;
        }

        if (!empty($approved_pids)) {
            $db->update_query('posts', ['visible' => 1], 'pid IN (' . implode(',', $approved_pids) . ')');
        }

        $plugins->run_hooks('class_moderation_approve_posts', $approved_pids);

        foreach ($thread_counters as $tid => $c) {
            update_thread_counters($tid, [
                'unapprovedposts' => "-{$c['replies']}",
                'replies'         => "+{$c['replies']}",
            ]);
            update_last_post($tid);
        }

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'posts'           => "+{$c['num_posts']}",
                'unapprovedposts' => "-{$c['num_posts']}",
            ]);
            update_forum_lastpost($fid);
        }

        foreach ($user_counters as $uid => $count) {
            update_user_counters($uid, ['postnum' => "+{$count}"]);
        }

        return true;
    }

    // ── unapprove_posts ───────────────────────────────────────────────────────

    public function unapprove_posts(array $pids): bool
    {
        global $db, $plugins;

        if (empty($pids)) {
            return false;
        }

        $pids     = $this->normalizeIds($pids);
        $pid_list = implode(',', $pids);

        $threads_to_update = [];
        $query = $db->sql_query("
            SELECT p.tid
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$pid_list}) AND p.visible IN (-1,1) AND t.firstpost = p.pid AND t.visible IN (-1,1)
        ");
        while ($post = $db->fetch_array($query)) {
            $threads_to_update[] = (int)$post['tid'];
        }
        if (!empty($threads_to_update)) {
            $this->unapprove_threads($threads_to_update);
        }

        $thread_counters  = [];
        $forum_counters   = [];
        $user_counters    = [];
        $unapproved_pids  = [];

        $query = $db->sql_query("
            SELECT p.pid, p.tid, p.visible, p.fid, p.uid, t.visible AS threadvisible
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$pid_list}) AND p.visible IN (-1,1) AND t.firstpost != p.pid
        ");
        while ($post = $db->fetch_array($query)) {
            $unapproved_pids[] = (int)$post['pid'];

            $thread_counters[$post['tid']] ??= ['replies' => 0, 'unapprovedposts' => 0];
            ++$thread_counters[$post['tid']]['unapprovedposts'];

            if ((int)$post['visible'] === 1) {
                ++$thread_counters[$post['tid']]['replies'];
            } else {
                $thread_counters[$post['tid']]['deletedposts'] = ($thread_counters[$post['tid']]['deletedposts'] ?? 0) + 1;
            }

            if ((int)$post['threadvisible'] !== 0) {
                $forum_counters[$post['fid']] ??= ['num_posts' => 0, 'num_unapproved_posts' => 0];
                ++$forum_counters[$post['fid']]['num_unapproved_posts'];
                if ((int)$post['visible'] === 1) {
                    ++$forum_counters[$post['fid']]['num_posts'];
                } else {
                    $forum_counters[$post['fid']]['num_deleted_posts'] = ($forum_counters[$post['fid']]['num_deleted_posts'] ?? 0) + 1;
                }
            }

            $forum = get_forum($post['fid']);
            if ($forum['usepostcounts'] != 0 && (int)$post['visible'] === 1 && (int)$post['threadvisible'] === 1) {
                $user_counters[$post['uid']] ??= 0;
                --$user_counters[$post['uid']];
            }
        }

        if (empty($unapproved_pids) && empty($threads_to_update)) {
            return false;
        }

        if (!empty($unapproved_pids)) {
            $db->update_query('posts', ['visible' => 0], 'pid IN (' . implode(',', $unapproved_pids) . ')');
        }

        $plugins->run_hooks('class_moderation_unapprove_posts', $unapproved_pids);

        foreach ($thread_counters as $tid => $c) {
            update_thread_counters($tid, [
                'unapprovedposts' => "+{$c['unapprovedposts']}",
                'replies'         => "-{$c['replies']}",
            ]);
            update_last_post($tid);
        }

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'posts'           => "-{$c['num_posts']}",
                'unapprovedposts' => "+{$c['num_unapproved_posts']}",
            ]);
            update_forum_lastpost($fid);
        }

        foreach ($user_counters as $uid => $count) {
            update_user_counters($uid, ['postnum' => (string)$count]);
        }

        return true;
    }

    // ── approve_threads ───────────────────────────────────────────────────────

    public function approve_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $tids_list      = implode(',', $tids);
        $tid_list       = [];
        $forum_counters = [];
        $user_counters  = [];
        $posts_to_approve = [];

        $query = $db->simple_select('threads', '*', "tid IN ({$tids_list})");
        while ($thread = $db->fetch_array($query)) {
            if ((int)$thread['visible'] === 1 || (int)$thread['visible'] === -1) {
                continue;
            }

            $tid_list[] = (int)$thread['tid'];
            $forum      = get_forum($thread['fid']);
            $fid        = $forum['fid'];

            $forum_counters[$fid] ??= ['num_posts' => 0, 'num_threads' => 0, 'num_unapproved_posts' => 0];
            $user_counters[$thread['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];

            ++$forum_counters[$fid]['num_threads'];
            $forum_counters[$fid]['num_posts']            += $thread['replies'] + 1;
            $forum_counters[$fid]['num_deleted_posts']     = ($forum_counters[$fid]['num_deleted_posts'] ?? 0) + $thread['deletedposts'];
            $forum_counters[$fid]['num_unapproved_posts'] += $thread['deletedposts'] + $thread['replies'] + 1;

            if ($forum['usepostcounts'] != 0) {
                $q2 = $db->simple_select(
                    'posts', 'COUNT(pid) AS posts, uid',
                    "tid='{$thread['tid']}' AND (visible='1' OR pid='{$thread['firstpost']}') AND uid > 0 GROUP BY uid"
                );
                while ($counter = $db->fetch_array($q2)) {
                    $user_counters[$counter['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];
                    $user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
                }
            }

            if ($forum['usethreadcounts'] != 0 && !str_starts_with((string)$thread['closed'], 'moved|')) {
                ++$user_counters[$thread['uid']]['num_threads'];
            }

            $posts_to_approve[] = (int)$thread['firstpost'];
        }

        if (empty($tid_list)) {
            return true;
        }

        $tid_moved_list = $this->buildMovedList($tid_list);
        $tid_list_str   = implode(',', $tid_list);

        $db->update_query('threads', ['visible' => 1], "tid IN ({$tid_list_str})");

        $redirect_tids = [];
        $query = $db->simple_select('threads', 'tid', "closed IN ({$tid_moved_list})");
        while ($redirect_tid = $db->fetch_field($query, 'tid')) {
            $redirect_tids[] = (int)$redirect_tid;
        }
        if (!empty($redirect_tids)) {
            $this->approve_threads($redirect_tids);
        }

        if (!empty($posts_to_approve)) {
            $db->update_query('posts', ['visible' => 1], 'pid IN (' . implode(',', $posts_to_approve) . ')');
        }

        $plugins->run_hooks('class_moderation_approve_threads', $tids);

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'threads'           => "+{$c['num_threads']}",
                'unapprovedthreads' => "-{$c['num_threads']}",
                'posts'             => "+{$c['num_posts']}",
                'unapprovedposts'   => "-{$c['num_unapproved_posts']}",
            ]);
            update_forum_lastpost($fid);
        }

        foreach ($user_counters as $uid => $c) {
            update_user_counters($uid, [
                'postnum'   => "+{$c['num_posts']}",
                'threadnum' => "+{$c['num_threads']}",
            ]);
        }

        return true;
    }

    // ── delete_poll ───────────────────────────────────────────────────────────

    public function delete_poll(int $pid): bool
    {
        global $db, $plugins;

        if ($pid <= 0) {
            return false;
        }

        $plugins->run_hooks('class_moderation_delete_poll', $pid);

        $db->delete_query('polls',     "pid='{$pid}'");
        $db->delete_query('pollvotes', "pid='{$pid}'");
        $db->update_query('threads',   ['poll' => 0], "poll='{$pid}'");

        return true;
    }

    // ── close_threads ─────────────────────────────────────────────────────────

    public function close_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $plugins->run_hooks('class_moderation_close_threads', $tids);

        $tid_list = implode(',', $tids);
        $db->update_query('threads', ['closed' => 1], "tid IN ({$tid_list}) AND closed NOT LIKE 'moved|%'");

        return true;
    }

    // ── open_threads ──────────────────────────────────────────────────────────

    public function open_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $plugins->run_hooks('class_moderation_open_threads', $tids);

        $db->update_query('threads', ['closed' => 0], 'tid IN (' . implode(',', $tids) . ')');

        return true;
    }

    // ── stick_threads ─────────────────────────────────────────────────────────

    public function stick_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $plugins->run_hooks('class_moderation_stick_threads', $tids);

        $db->update_query('threads', ['sticky' => 1], 'tid IN (' . implode(',', $tids) . ')');

        return true;
    }

    // ── unstick_threads ───────────────────────────────────────────────────────

    public function unstick_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $plugins->run_hooks('class_moderation_unstick_threads', $tids);

        $db->update_query('threads', ['sticky' => 0], 'tid IN (' . implode(',', $tids) . ')');

        return true;
    }

    // ── delete_thread ─────────────────────────────────────────────────────────

    public function delete_thread(int $tid): bool
    {
        global $db;

        $thread = get_thread($tid);
        if (!$thread) {
            return false;
        }

        $forum     = get_forum($thread['fid']);
        $userposts = [];
        $pids      = [];
        $num_unapproved_posts = $num_approved_posts = $num_deleted_posts = 0;

        $query = $db->simple_select('posts', 'pid, uid, visible', "tid='{$tid}'");
        while ($post = $db->fetch_array($query)) {
            $pids[] = (int)$post['pid'];

            if (!function_exists('remove_attachments')) {
                require_once INC_PATH . '/functions_upload.php';
            }
            remove_attachments((int)$post['pid']);

            if (((int)$post['visible'] === 0 && (int)$thread['visible'] !== -1) || (int)$thread['visible'] === 0) {
                ++$num_unapproved_posts;
            } elseif ((int)$post['visible'] === -1 || (int)$thread['visible'] === -1) {
                ++$num_deleted_posts;
            } else {
                ++$num_approved_posts;
                $userposts[$post['uid']]['num_posts'] = ($userposts[$post['uid']]['num_posts'] ?? 0) + 1;
            }
        }

        $userposts[$thread['uid']]['num_threads'] = ($userposts[$thread['uid']]['num_threads'] ?? 0) + 1;

        foreach ($userposts as $uid => $subtract) {
            $update = [];
            if (isset($subtract['num_posts'])) {
                $update['postnum'] = "-{$subtract['num_posts']}";
            }
            if (isset($subtract['num_threads'])) {
                $update['threadnum'] = "-{$subtract['num_threads']}";
            }
            if (!empty($update)) {
                update_user_counters($uid, $update);
            }
        }

        if (!empty($pids)) {
            $pids_str = implode(',', $pids);
            $db->delete_query('posts',   "pid IN ({$pids_str})");
            $db->delete_query('attachments', "pid IN ({$pids_str})");

            // Delete attached files from disk
            $files = $db->simple_select('comment_files', '*', "post_id IN ({$pids_str})");
            while ($file = $db->fetch_array($files)) {
                if (is_file($file['file_path'])) {
                    @unlink($file['file_path']);
                }
            }
            $db->delete_query('comment_files', "post_id IN ({$pids_str})");
        }

        // Delete thread ratings
        $db->delete_query('threadratings', "tid='{$tid}'");
		
		$db->delete_query('threads',            "tid='{$tid}'");
        $db->delete_query('threadsubscriptions', "tid='{$tid}'");
        $db->delete_query('polls',              "tid='{$tid}'");
        $db->delete_query('pollvotes',          "pid='{$thread['poll']}'");
        $db->delete_query('threadsread',        "tid='{$tid}'");
        $db->sql_query("DELETE FROM reports WHERE type='forumpost' AND thread_id = {$tid}");

        // Delete redirect threads
        $query = $db->simple_select('threads', 'tid', "closed='moved|{$tid}'");
        while ($redirect_tid = $db->fetch_field($query, 'tid')) {
            $this->delete_thread((int)$redirect_tid);
        }

        $updated_counters = [
            'posts'           => "-{$num_approved_posts}",
            'unapprovedposts' => "-{$num_unapproved_posts}",
        ];

        match ((int)$thread['visible']) {
            1  => $updated_counters['threads']           = -1,
            -1 => $updated_counters['deletedthreads']    = -1,
            default => $updated_counters['unapprovedthreads'] = -1,
        };

        if (str_contains((string)$thread['closed'], 'moved|') && (int)$thread['visible'] === 1) {
            $updated_counters['posts'] = -1;
        }

        update_forum_counters($thread['fid'], $updated_counters);
        update_forum_lastpost((int)$thread['fid']);

        return true;
    }

    // ── delete_post ───────────────────────────────────────────────────────────

    public function delete_post(int $pid): bool
    {
        global $db, $plugins;

        $pid = $plugins->run_hooks('class_moderation_delete_post_start', $pid);
        $pid = (int)$pid;

        $query = $db->sql_query("
            SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible AS threadvisible
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid = '{$pid}'
        ");
        $post = $db->fetch_array($query);
        if (!$post) {
            return false;
        }

        update_user_counters($post['uid'], ['postnum' => '-1']);

        if (!function_exists('remove_attachments')) {
            require INC_PATH . '/functions_upload.php';
        }
        remove_attachments($pid);

        $db->delete_query('posts', "pid='{$pid}'");
        $db->sql_query("DELETE FROM reports WHERE type='forumpost' AND reported_id = {$pid}");

        // Delete attached files from disk
        $files = $db->simple_select('comment_files', '*', "post_id = {$pid}");
        while ($file = $db->fetch_array($files)) {
            if (is_file($file['file_path'])) {
                @unlink($file['file_path']);
            }
        }
        $db->delete_query('comment_files', "post_id = {$pid}");

        $plugins->run_hooks('class_moderation_delete_post', $post['pid']);

        update_thread_counters((int)$post['tid'], ['replies' => '-1']);
        update_last_post((int)$post['tid']);

        update_forum_counters($post['fid'], ['posts' => '-1']);
        update_forum_lastpost((int)$post['fid']);

        return true;
    }

    // ── merge_posts ───────────────────────────────────────────────────────────

    public function merge_posts(array $pids = [], int $tid = 0, string $sep = 'new_line'): int|false
    {
        global $db, $plugins;

        $pids = $this->normalizeIds($pids);
        if (empty($pids) || count($pids) < 2) {
            return false;
        }

        $pidin   = implode(',', $pids);
        $first   = true;
        $message = '';
        $masterpid = $mastertid = $fid = $visible = 0;

        $thread_counters = [];
        $forum_counters  = [];
        $user_counters   = [];
        $threads         = [];

        $query = $db->sql_query("
            SELECT p.pid, p.uid, p.fid, p.tid, p.visible, p.message,
                   t.visible AS threadvisible, t.replies AS threadreplies,
                   t.firstpost AS threadfirstpost, COUNT(a.aid) AS attachmentcount
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            LEFT JOIN attachments a ON a.pid = p.pid AND a.visible = 1
            WHERE p.pid IN ({$pidin})
            GROUP BY p.pid
            ORDER BY p.dateline ASC, p.pid ASC
        ");

        while ($post = $db->fetch_array($query)) {
            $threads[$post['tid']] = $post['tid'];
            $thread_counters[$post['tid']] ??= ['replies' => 0, 'attachmentcount' => 0];

            if ($first) {
                $masterpid = $post['pid'];
                $message   = $post['message'];
                $fid       = $post['fid'];
                $mastertid = $post['tid'];
                $visible   = $post['visible'];
                $first     = false;
            } else {
                $message .= $sep === 'new_line' ? "\n\n {$post['message']}" : "[hr]{$post['message']}";

                $forum_counters[$post['fid']] ??= ['num_posts' => 0];

                if ((int)$post['visible'] === 1) {
                    --$thread_counters[$post['tid']]['replies'];
                    $user_counters[$post['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];

                    if ((int)$post['threadvisible'] === 1) {
                        --$user_counters[$post['uid']]['num_posts'];
                    }
                    if ((int)$post['threadfirstpost'] === (int)$post['pid'] && (int)$post['threadvisible'] === 1) {
                        --$user_counters[$post['uid']]['num_threads'];
                    }
                    $thread_counters[$post['tid']]['attachmentcount'] -= $post['attachmentcount'];
                }

                if ((int)$post['threadvisible'] === 1 && (int)$post['visible'] === 1) {
                    --$forum_counters[$post['fid']]['num_posts'];
                } elseif ((int)$post['threadvisible'] === 0 || ((int)$post['visible'] === 0 && (int)$post['threadvisible'] !== -1)) {
                    $forum_counters[$post['fid']]['unapprovedposts'] = ($forum_counters[$post['fid']]['unapprovedposts'] ?? 0) - 1;
                } else {
                    $forum_counters[$post['fid']]['deletedposts'] = ($forum_counters[$post['fid']]['deletedposts'] ?? 0) - 1;
                }

                if ((int)$visible === 1) {
                    $thread_counters[$mastertid]['attachmentcount'] += $post['attachmentcount'];
                }
            }
        }

        $db->update_query('posts',  ['message' => $db->escape_string($message)], "pid = '{$masterpid}'");
        $db->delete_query('posts',  "pid IN ({$pidin}) AND pid != '{$masterpid}'");
        $db->update_query('attachments', ['pid' => $masterpid], "pid IN ({$pidin})");

        // Fix firstpost if needed
        $query = $db->simple_select('threads', 'tid, uid, fid, visible', "firstpost IN ({$pidin}) AND firstpost != '{$masterpid}'");
        while ($thread = $db->fetch_array($query)) {
            $q2 = $db->simple_select('posts', 'pid, uid, visible', "tid='{$thread['tid']}'", ['order_by' => 'dateline, pid', 'limit' => 1]);
            $new_firstpost = $db->fetch_array($q2);

            if ((int)$thread['visible'] !== (int)$new_firstpost['visible']) {
                $db->update_query('posts', ['visible' => $thread['visible']], "pid='{$new_firstpost['pid']}'");
                if ((int)$new_firstpost['visible'] === 1) {
                    --$thread_counters[$thread['tid']]['replies'];
                }
                if ((int)$thread['visible'] === 1) {
                    ++$thread_counters[$thread['tid']]['replies'];
                }
            }

            if ((int)$new_firstpost['uid'] !== (int)$thread['uid'] && (int)$thread['visible'] === 1) {
                $user_counters[$new_firstpost['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];
                ++$user_counters[$new_firstpost['uid']]['num_threads'];
            }

            update_first_post($thread['tid']);
        }

       $merge_data = ['pids' => $pids, 'tid' => $tid];
       $plugins->run_hooks('class_moderation_merge_posts', $merge_data);

        foreach ($thread_counters as $tid => $c) {
            update_thread_counters($tid, [
                'replies'         => signed($c['replies']),
                'attachmentcount' => signed($c['attachmentcount']),
            ]);
            update_last_post($tid);
        }

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, ['posts' => signed($c['num_posts'])]);
            update_forum_lastpost($fid);
        }

        foreach ($user_counters as $uid => $c) {
            update_user_counters($uid, [
                'postnum'   => "+{$c['num_posts']}",
                'threadnum' => "+{$c['num_threads']}",
            ]);
        }

        return (int)$masterpid;
    }

    // ── merge_threads ─────────────────────────────────────────────────────────

    public function merge_threads(int $mergetid, int $tid, string $subject): bool
    {
        global $db, $mergethread, $thread, $plugins, $cache;

        if (!isset($mergethread['tid']) || (int)$mergethread['tid'] !== $mergetid) {
            $mergethread = get_thread($mergetid);
        }
        if (!isset($thread['tid']) || (int)$thread['tid'] !== $tid) {
            $thread = get_thread($tid);
        }
        if (!$mergethread || !$thread) {
            return false;
        }

        $user_posts = [];

        if ((int)$thread['visible'] !== (int)$mergethread['visible']) {
            $query = $db->sql_query("
                SELECT uid, COUNT(pid) AS postnum
                FROM posts
                WHERE tid='{$mergetid}' AND visible=1
                GROUP BY uid
            ");
            while ($post = $db->fetch_array($query)) {
                $user_posts[$post['uid']]['postnum'] ??= 0;
                if ((int)$mergethread['visible'] === 1) {
                    $user_posts[$post['uid']]['postnum'] -= $post['postnum'];
                } elseif ((int)$thread['visible'] === 1) {
                    $user_posts[$post['uid']]['postnum'] += $post['postnum'];
                }
            }
        }

        $db->update_query('posts', [
            'tid'     => $tid,
            'fid'     => $thread['fid'],
            'replyto' => 0,
        ], "tid='{$mergetid}'");

        $db->update_query('threads', ['closed' => "moved|{$tid}"], "closed='moved|{$mergetid}'");

        // Handle subscriptions
        $subscriptions = [];
        $query = $db->simple_select('threadsubscriptions', 'tid, uid', "tid='{$mergetid}' OR tid='{$tid}'");
        while ($sub = $db->fetch_array($query)) {
            $subscriptions[$sub['tid']][] = $sub['uid'];
        }

        if (!empty($subscriptions[$mergetid])) {
            $update_users = array_filter(
                $subscriptions[$mergetid],
                fn($user) => !isset($subscriptions[$tid]) || !in_array($user, $subscriptions[$tid], true)
            );
            if (!empty($update_users)) {
                $db->update_query('threadsubscriptions', ['tid' => $tid],
                    "tid = '{$mergetid}' AND uid IN (" . implode(',', $update_users) . ')'
                );
            }
        }
        $db->delete_query('threadsubscriptions', "tid = '{$mergetid}'");

        $plugins->run_hooks('class_moderation_merge_threads', [
            'mergetid' => $mergetid,
            'tid'      => $tid,
            'subject'  => $subject,
        ]);

        $this->delete_thread($mergetid);

        // Adjust reply counters
        if ((int)$mergethread['visible'] === 1) {
            ++$mergethread['replies'];
        } elseif ((int)$mergethread['visible'] === -1) {
            ++$mergethread['deletedposts'];
        } else {
            ++$mergethread['unapprovedposts'];
        }

        // Sync first post visibility
        $q = $db->simple_select('posts', 'pid, uid, visible', "tid='{$tid}'", ['order_by' => 'dateline, pid', 'limit' => 1]);
        $new_firstpost = $db->fetch_array($q);

        if ((int)$thread['visible'] !== (int)$new_firstpost['visible']) {
            $db->update_query('posts', ['visible' => $thread['visible']], "pid='{$new_firstpost['pid']}'");
        }

        if ((int)$new_firstpost['pid'] !== (int)$thread['firstpost']) {
            update_first_post($thread['tid']);
        }

        if ((int)$thread['uid'] !== (int)$new_firstpost['uid'] && (int)$thread['visible'] === 1) {
            $user_posts[$thread['uid']]['threadnum']          = ($user_posts[$thread['uid']]['threadnum'] ?? 0) - 1;
            $user_posts[$new_firstpost['uid']]['threadnum']   = ($user_posts[$new_firstpost['uid']]['threadnum'] ?? 0) + 1;
        }

        if ($mergethread['fid'] != $thread['fid']) {
            update_forum_counters($thread['fid'],    ['posts' => "+{$mergethread['replies']}"]);
            update_forum_counters($mergethread['fid'], ['posts' => "-{$mergethread['replies']}"]);
            update_forum_lastpost($mergethread['fid']);
        }

        foreach ($user_posts as $uid => $counters) {
            $update = [];
            foreach ($counters as $key => $val) {
                $update[$key] = $val >= 0 ? "+{$val}" : (string)$val;
            }
            update_user_counters($uid, $update);
        }

        update_thread_counters($tid, [
            'replies'         => "+{$mergethread['replies']}",
            'attachmentcount' => "+{$mergethread['attachmentcount']}",
        ]);
        update_last_post($tid);
        update_forum_lastpost($thread['fid']);

        return true;
    }

    // ── move_thread ───────────────────────────────────────────────────────────

    public function move_thread(int $tid, int $new_fid, string $method = 'redirect', int $redirect_expire = 0): int|false
    {
        global $db, $plugins;

        $thread   = get_thread($tid, true);
        $newforum = get_forum($new_fid);

        if (!$thread || !$newforum) {
            return false;
        }

        $fid   = $thread['fid'];
        $forum = get_forum($fid);

        $num_threads = $num_unapproved_threads = $num_posts = $num_unapproved_posts = 0;
        $num_deleted_posts = $num_deleted_threads = 0;

        match ((int)$thread['visible']) {
            1  => [$num_threads, $num_posts] = [1, $thread['replies'] + 1],
            -1 => [$num_deleted_threads, $num_deleted_posts] = [1, $thread['replies'] + $thread['deletedposts'] + $thread['unapprovedposts'] + 1],
            default => [$num_unapproved_threads, $num_unapproved_posts] = [1, $thread['replies'] + $thread['unapprovedposts'] + $thread['deletedposts'] + 1],
        };

        $newtid = null;

        switch ($method) {
            case 'redirect':
                $plugins->run_hooks('class_moderation_move_thread_redirect', ['tid' => $tid, 'new_fid' => $new_fid]);

                // Delete existing redirects to the same destination
                $query = $db->simple_select('threads', 'tid', "closed='moved|{$tid}' AND fid='{$new_fid}'");
                while ($redirect_tid = $db->fetch_field($query, 'tid')) {
                    $this->delete_thread((int)$redirect_tid);
                }

                $db->update_query('threads', ['fid' => $new_fid], "tid='{$tid}'");
                $db->update_query('posts',   ['fid' => $new_fid], "tid='{$tid}'");

                $redirect_tid = $db->insert_query('threads', [
                    'fid'          => $thread['fid'],
                    'subject'      => $db->escape_string($thread['subject']),
                    'uid'          => $thread['uid'],
                    'username'     => $db->escape_string($thread['username']),
                    'dateline'     => $thread['dateline'],
                    'lastpost'     => $thread['lastpost'],
                    'lastposteruid'=> $thread['lastposteruid'],
                    'lastposter'   => $db->escape_string($thread['lastposter']),
                    'views'        => 0,
                    'replies'      => 0,
                    'closed'       => "moved|{$tid}",
                    'sticky'       => $thread['sticky'],
                    'visible'      => (int)$thread['visible'],
                    'notes'        => '',
                ]);

                if ($redirect_expire) {
                    $this->expire_thread((int)$redirect_tid, $redirect_expire);
                }

                // Delete redirect if moving back to origin
                $query = $db->simple_select('threads', 'tid', "closed LIKE 'moved|{$tid}' AND fid='{$new_fid}'");
                while ($redirect_tid = $db->fetch_field($query, 'tid')) {
                    $this->delete_thread((int)$redirect_tid);
                }
                break;

            case 'copy':
                $copy_data = ['tid' => $tid, 'new_fid' => $new_fid];
                $plugins->run_hooks('class_moderation_copy_thread', $copy_data);

                $newtid = $db->insert_query('threads', [
                    'fid'             => $new_fid,
                    'subject'         => $db->escape_string($thread['subject']),
                    'uid'             => $thread['uid'],
                    'username'        => $db->escape_string($thread['username']),
                    'dateline'        => $thread['dateline'],
                    'firstpost'       => 0,
                    'lastpost'        => $thread['lastpost'],
                    'lastposteruid'   => $thread['lastposteruid'],
                    'lastposter'      => $db->escape_string($thread['lastposter']),
                    'views'           => $thread['views'],
                    'replies'         => $thread['replies'],
                    'closed'          => $thread['closed'],
                    'sticky'          => $thread['sticky'],
                    'visible'         => (int)$thread['visible'],
                    'attachmentcount' => $thread['attachmentcount'],
                    'notes'           => '',
                ]);

                if ($thread['poll'] != 0) {
                    $q     = $db->simple_select('polls', '*', "tid='{$thread['tid']}'");
                    $poll  = $db->fetch_array($q);
                    $new_pid = $db->insert_query('polls', [
                        'tid'       => $newtid,
                        'question'  => $db->escape_string($poll['question']),
                        'dateline'  => $poll['dateline'],
                        'options'   => $db->escape_string($poll['options']),
                        'votes'     => $db->escape_string($poll['votes']),
                        'numoptions'=> $poll['numoptions'],
                        'numvotes'  => $poll['numvotes'],
                        'timeout'   => $poll['timeout'],
                        'closed'    => $poll['closed'],
                        'multiple'  => $poll['multiple'],
                        'public'    => $poll['public'],
                    ]);
                    $q2 = $db->simple_select('pollvotes', '*', "pid='{$poll['pid']}'");
                    while ($pv = $db->fetch_array($q2)) {
                        $db->insert_query('pollvotes', [
                            'pid'        => $new_pid,
                            'uid'        => $pv['uid'],
                            'voteoption' => $pv['voteoption'],
                            'dateline'   => $pv['dateline'],
                        ]);
                    }
                    $db->update_query('threads', ['poll' => $new_pid], "tid='{$newtid}'");
                }

                $q = $db->simple_select('posts', '*', "tid='{$thread['tid']}'");
                while ($post = $db->fetch_array($q)) {
                    $new_post_array = [
                        'tid'       => $newtid,
                        'fid'       => $new_fid,
                        'subject'   => $db->escape_string($post['subject']),
                        'uid'       => $post['uid'],
                        'username'  => $db->escape_string($post['username']),
                        'dateline'  => $post['dateline'],
                        'ipaddress' => $db->escape_binary($post['ipaddress']),
                        'edituid'   => $post['edituid'],
                        'edittime'  => $post['edittime'],
                        'visible'   => $post['visible'],
                        'message'   => $db->escape_string($post['message']),
                    ];
                    $new_pid = $db->insert_query('posts', $new_post_array);

                    if ((int)$thread['firstpost'] === (int)$post['pid']) {
                        $db->update_query('threads', ['firstpost' => $new_pid], "tid='{$newtid}'");
                    }

                    $q2 = $db->simple_select('attachments', '*', "pid='{$post['pid']}'");
                    while ($att = $db->fetch_array($q2)) {
                        $new_aid = $db->insert_query('attachments', [
                            'pid'        => $new_pid,
                            'uid'        => $att['uid'],
                            'filename'   => $db->escape_string($att['filename']),
                            'filetype'   => $db->escape_string($att['filetype']),
                            'filesize'   => $att['filesize'],
                            'attachname' => $db->escape_string($att['attachname']),
                            'downloads'  => $att['downloads'],
                            'visible'    => $att['visible'],
                            'thumbnail'  => $db->escape_string($att['thumbnail']),
                        ]);
                        $post['message'] = str_replace("[attachment={$att['aid']}]", "[attachment={$new_aid}]", $post['message']);
                    }

                    if (str_contains($post['message'], '[attachment=')) {
                        $db->update_query('posts', ['message' => $db->escape_string($post['message'])], "pid='{$new_pid}'");
                    }
                }

                update_thread_data($newtid);
                break;

            default: // plain move
                $plugins->run_hooks('class_moderation_move_simple', ['tid' => $tid, 'new_fid' => $new_fid]);
                $db->update_query('threads', ['fid' => $new_fid], "tid='{$tid}'");
                $db->update_query('posts',   ['fid' => $new_fid], "tid='{$tid}'");
                break;
        }

        // Update user post/thread counts if forum counting settings differ
        $query = $db->sql_query("
            SELECT COUNT(p.pid) AS posts, u.id
            FROM posts p
            LEFT JOIN users u ON u.id = p.uid
            WHERE p.tid = '{$tid}' AND p.visible = 1
            GROUP BY u.id
            ORDER BY posts DESC
        ");
        while ($posters = $db->fetch_array($query)) {
            $pcount = 0;
            if ($forum['usepostcounts'] == 1 && $method !== 'copy' && $newforum['usepostcounts'] == 0 && (int)$thread['visible'] === 1) {
                $pcount -= $posters['posts'];
            }
            if (($forum['usepostcounts'] == 0 || $method === 'copy') && $newforum['usepostcounts'] == 1 && (int)$thread['visible'] === 1) {
                $pcount += $posters['posts'];
            }
            if ($pcount > 0) {
                update_user_counters($posters['id'], ['postnum' => "+{$pcount}"]);
            } elseif ($pcount < 0) {
                update_user_counters($posters['id'], ['postnum' => (string)$pcount]);
            }
        }

        if ($forum['usethreadcounts'] == 1 && $method !== 'copy' && $newforum['usethreadcounts'] == 0 && (int)$thread['visible'] === 1) {
            update_user_counters($thread['uid'], ['threadnum' => '-1']);
        } elseif (($forum['usethreadcounts'] == 0 || $method === 'copy') && $newforum['usethreadcounts'] == 1 && (int)$thread['visible'] === 1) {
            update_user_counters($thread['uid'], ['threadnum' => '+1']);
        }

        update_forum_counters($new_fid, ['threads' => "+{$num_threads}", 'posts' => "+{$num_posts}"]);
        update_forum_lastpost($new_fid);

        if ($method !== 'copy') {
            if ($method === 'redirect') {
                match ((int)$thread['visible']) {
                    -1      => [$num_deleted_threads--, $num_deleted_posts--],
                    0       => [$num_unapproved_threads--, $num_unapproved_posts--],
                    default => [$num_threads--, $num_posts--],
                };
            }
            update_forum_counters($fid, ['threads' => "-{$num_threads}", 'posts' => "-{$num_posts}"]);
            update_forum_lastpost($fid);
        }

        return $newtid ?? $tid;
    }

    // ── split_posts ───────────────────────────────────────────────────────────

    public function split_posts(array $pids, int $tid, int $moveto, string $newsubject, int $destination_tid = 0): int|false
    {
        global $db, $thread, $plugins, $cache;

        $pids  = $this->normalizeIds($pids);
        $newtid = $destination_tid;

        if (empty($pids)) {
            return false;
        }

        if (!isset($thread['tid']) || (int)$thread['tid'] !== $tid) {
            $thread = get_thread($tid);
        }
        if (!$thread) {
            return false;
        }

        $forum_cache = $cache->read('forums');
        $pidin       = implode(',', $pids);

        $newthread = null;
        if ($destination_tid > 0) {
            $newthread = get_thread($destination_tid);
        }

        if ($destination_tid === 0) {
            // Get the oldest post to use as subject/dateline for new thread
            $q = $db->simple_select('posts', 'uid, username, dateline', "pid IN ({$pidin})", ['order_by' => 'dateline, pid', 'limit' => 1]);
            $post_info = $db->fetch_array($q);

            $newtid = $db->insert_query('threads', [
                'fid'           => $moveto,
                'subject'       => $db->escape_string($newsubject),
                'uid'           => $post_info['uid'],
                'username'      => $db->escape_string($post_info['username']),
                'dateline'      => $post_info['dateline'],
                'firstpost'     => 0,
                'lastpost'      => $post_info['dateline'],
                'lastposteruid' => $post_info['uid'],
                'lastposter'    => $db->escape_string($post_info['username']),
                'views'         => 0,
                'replies'       => 0,
                'visible'       => (int)$thread['visible'],
                'notes'         => '',
            ]);
        }

        $newthread ??= get_thread($newtid);

        $thread_counters = [
            $tid    => ['replies' => 0, 'unapprovedposts' => 0, 'deletedposts' => 0],
            $newtid => ['replies' => 0, 'unapprovedposts' => 0, 'deletedposts' => 0],
        ];
        $forum_counters = [
            $thread['fid'] => ['posts' => 0, 'unapprovedposts' => 0, 'deletedposts' => 0],
            $moveto        => ['posts' => 0, 'unapprovedposts' => 0, 'deletedposts' => 0],
        ];
        $user_counters = [];

        $q = $db->sql_query("
            SELECT p.pid, p.uid, p.fid, p.tid, p.visible, p.dateline,
                   t.visible AS threadvisible, t.firstpost AS threadfirstpost
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$pidin})
            ORDER BY p.dateline ASC, p.pid ASC
        ");

        $post_info = null;
        while ($post = $db->fetch_array($query = $q)) {
            $post_info ??= $post;

            $db->update_query('posts', ['tid' => $newtid, 'fid' => $moveto], "pid='{$post['pid']}'");

            // Remove from old thread
            $user_counters[$post['uid']] ??= ['postnum' => 0, 'threadnum' => 0];

            if ((int)$post['visible'] === 1) {
                --$thread_counters[$tid]['replies'];
                --$forum_counters[$thread['fid']]['posts'];
                if ($forum_cache[$post['fid']]['usepostcounts'] == 1 && (int)$post['threadvisible'] === 1) {
                    --$user_counters[$post['uid']]['postnum'];
                }
            } elseif ((int)$post['visible'] === 0) {
                --$thread_counters[$tid]['unapprovedposts'];
                --$forum_counters[$thread['fid']]['unapprovedposts'];
            } else {
                --$thread_counters[$tid]['deletedposts'];
                --$forum_counters[$thread['fid']]['deletedposts'];
            }

            // Add to new thread
            if ((int)$post['visible'] === 1) {
                ++$thread_counters[$newtid]['replies'];
                ++$forum_counters[$moveto]['posts'];
                if ($newthread['visible'] == 1 && $forum_cache[$moveto]['usepostcounts'] == 1) {
                    ++$user_counters[$post['uid']]['postnum'];
                }
            } elseif ((int)$post['visible'] === 0) {
                ++$thread_counters[$newtid]['unapprovedposts'];
                ++$forum_counters[$moveto]['unapprovedposts'];
            } else {
                ++$thread_counters[$newtid]['deletedposts'];
                ++$forum_counters[$moveto]['deletedposts'];
            }
        }

        // Compensate for firstpost of new thread
        if ($destination_tid === 0) {
            match ((int)$newthread['visible']) {
                1       => --$thread_counters[$newtid]['replies'],
                0       => --$thread_counters[$newtid]['unapprovedposts'],
                default => --$thread_counters[$newtid]['deletedposts'],
            };
        }

        $split_data = [
    'pids'           => $pids,
    'tid'            => $tid,
    'moveto'         => $moveto,
    'newsubject'     => $newsubject,
    'destination_tid'=> $destination_tid,
];
$plugins->run_hooks('class_moderation_split_posts', $split_data);

        foreach ($user_counters as $uid => $counters) {
            $update = [];
            foreach ($counters as $key => $val) {
                $update[$key] = $val >= 0 ? "+{$val}" : (string)$val;
            }
            update_user_counters($uid, $update);
        }

        foreach ($thread_counters as $t => $counters) {
            if ($t == $newtid) {
                $q = $db->simple_select('posts', 'pid', "tid='{$newtid}'", ['order_by' => 'dateline, pid', 'limit' => 1]);
                $fp = $db->fetch_array($q);
                $db->update_query('posts', ['subject' => $db->escape_string($newsubject), 'replyto' => 0], "pid='{$fp['pid']}'");
            } else {
                $q = $db->sql_query("
                    SELECT p.pid, t.subject
                    FROM posts p
                    LEFT JOIN threads t ON p.tid = t.tid
                    WHERE p.tid = '{$tid}'
                    ORDER BY p.dateline ASC, p.pid ASC
                    LIMIT 1
                ");
                $oldthread = $db->fetch_array($q);
                $db->update_query('posts', [
                    'subject' => $db->escape_string($oldthread['subject']),
                    'replyto' => 0,
                ], "pid='{$oldthread['pid']}'");
            }

            $c = [];
            foreach ($counters as $key => $val) {
                $c[$key] = $val >= 0 ? "+{$val}" : (string)$val;
            }
            update_thread_counters($t, $c);
            update_last_post($t);
        }

        foreach ($forum_counters as $f => $counters) {
            $c = [];
            foreach ($counters as $key => $val) {
                $c[$key] = $val >= 0 ? "+{$val}" : (string)$val;
            }
            update_forum_counters($f, $c);
            update_forum_lastpost($f);
        }

        return $newtid;
    }
}