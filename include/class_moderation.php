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
     * Build "moved|N, moved|N2, ..." list from tid array (values only, for binding).
     */
    private function buildMovedList(array $tids): array
    {
        return array_map(fn(int $t) => "moved|{$t}", $tids);
    }

    /**
     * Builds "?,?,?" placeholders and the matching params array for an IN (...) clause.
     */
    private function inClause(array $values): array
    {
        return [implode(',', array_fill(0, count($values), '?')), array_values($values)];
    }

    // ── unapprove_threads ─────────────────────────────────────────────────────

    public function unapprove_threads(array|int $tids): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        $tid_moved_list = $this->buildMovedList($tids);

        $forum_counters = [];
        $user_counters  = [];
        $posts_to_unapprove = [];

        [$ph, $params] = $this->inClause($tids);
        $query = $db->sql_query_prepared("SELECT * FROM threads WHERE tid IN ({$ph})", $params);
        while ($query && ($thread = $db->fetch_array($query))) {
            $forum = get_forum($thread['fid']);
            $fid   = $forum['fid'];

            if ((int)$thread['visible'] === 1) {
                $forum_counters[$fid] ??= [
                    'num_threads'           => 0,
                    'num_posts'             => 0,
                    'num_unapprovedthreads' => 0,
                    'num_unapprovedposts'   => 0,
                ];
                $user_counters[$thread['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];

                ++$forum_counters[$fid]['num_unapprovedthreads'];
                $forum_counters[$fid]['num_unapprovedposts'] += $thread['replies'] + 1;
                ++$forum_counters[$fid]['num_threads'];
                $forum_counters[$fid]['num_posts']           += $thread['replies'] + 1;

                if ($forum['usepostcounts'] != 0) {
                    $q2 = $db->sql_query_prepared(
                        "SELECT COUNT(pid) AS posts, uid FROM posts WHERE tid = ? AND (visible = '1' OR pid = ?) AND uid > 0 GROUP BY uid",
                        [$thread['tid'], $thread['firstpost']]
                    );
                    while ($q2 && ($counter = $db->fetch_array($q2))) {
                        $user_counters[$counter['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];
                        $user_counters[$counter['uid']]['num_posts'] += $counter['posts'];
                    }
                }

                if ($forum['usethreadcounts'] != 0 && !str_starts_with((string)$thread['closed'], 'moved|')) {
                    ++$user_counters[$thread['uid']]['num_threads'];
                }
            }

            $posts_to_unapprove[] = (int)$thread['firstpost'];
        }

        [$ph, $params] = $this->inClause($tids);
        $db->sql_query_prepared("UPDATE threads SET visible = 0 WHERE tid IN ({$ph})", $params);

        // Unapprove redirects too
        $redirect_tids = [];
        [$ph, $params] = $this->inClause($tid_moved_list);
        $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE closed IN ({$ph})", $params);
        while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
            $redirect_tids[] = (int)$redirect_tid;
        }
        if (!empty($redirect_tids)) {
            $this->unapprove_threads($redirect_tids);
        }

        if (!empty($posts_to_unapprove)) {
            [$ph, $params] = $this->inClause($posts_to_unapprove);
            $db->sql_query_prepared("UPDATE posts SET visible = 0 WHERE pid IN ({$ph})", $params);
        }

        $plugins->run_hooks('class_moderation_unapprove_threads', $tids);

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'threads'           => "-{$c['num_threads']}",
                'unapprovedthreads' => "+{$c['num_unapprovedthreads']}",
                'posts'             => "-{$c['num_posts']}",
                'unapprovedposts'   => "+{$c['num_unapprovedposts']}",
            ]);
            update_forum_lastpost((int)$fid);
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

        // Find first posts of unapproved threads → approve whole thread
        $threads_to_update = [];
        [$ph, $params] = $this->inClause($pids);
        $query = $db->sql_query_prepared("
            SELECT p.tid
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$ph}) AND p.visible = '0' AND t.firstpost = p.pid AND t.visible = 0
        ", $params);
        while ($query && ($post = $db->fetch_array($query))) {
            $threads_to_update[] = (int)$post['tid'];
        }
        if (!empty($threads_to_update)) {
            $this->approve_threads($threads_to_update);
        }

        $thread_counters = [];
        $forum_counters  = [];
        $user_counters   = [];
        $approved_pids   = [];

        [$ph, $params] = $this->inClause($pids);
        $query = $db->sql_query_prepared("
            SELECT p.pid, p.tid, p.fid, p.uid, t.visible AS threadvisible
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$ph}) AND p.visible = '0' AND t.firstpost != p.pid
        ", $params);
        while ($query && ($post = $db->fetch_array($query))) {
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
            [$ph, $params] = $this->inClause($approved_pids);
            $db->sql_query_prepared("UPDATE posts SET visible = 1 WHERE pid IN ({$ph})", $params);
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
            update_forum_lastpost((int)$fid);
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

        $threads_to_update = [];
        [$ph, $params] = $this->inClause($pids);
        $query = $db->sql_query_prepared("
            SELECT p.tid
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$ph}) AND p.visible = 1 AND t.firstpost = p.pid AND t.visible = 1
        ", $params);
        while ($query && ($post = $db->fetch_array($query))) {
            $threads_to_update[] = (int)$post['tid'];
        }
        if (!empty($threads_to_update)) {
            $this->unapprove_threads($threads_to_update);
        }

        $thread_counters  = [];
        $forum_counters   = [];
        $user_counters    = [];
        $unapproved_pids  = [];

        [$ph, $params] = $this->inClause($pids);
        $query = $db->sql_query_prepared("
            SELECT p.pid, p.tid, p.visible, p.fid, p.uid, t.visible AS threadvisible
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid IN ({$ph}) AND p.visible = 1 AND t.firstpost != p.pid
        ", $params);
        while ($query && ($post = $db->fetch_array($query))) {
            $unapproved_pids[] = (int)$post['pid'];

            $thread_counters[$post['tid']] ??= ['replies' => 0, 'unapprovedposts' => 0];
            ++$thread_counters[$post['tid']]['unapprovedposts'];
            ++$thread_counters[$post['tid']]['replies'];

            if ((int)$post['threadvisible'] === 1) {
                $forum_counters[$post['fid']] ??= ['num_posts' => 0, 'num_unapproved_posts' => 0];
                ++$forum_counters[$post['fid']]['num_unapproved_posts'];
                ++$forum_counters[$post['fid']]['num_posts'];
            }

            $forum = get_forum($post['fid']);
            if ($forum['usepostcounts'] != 0 && (int)$post['threadvisible'] === 1) {
                $user_counters[$post['uid']] ??= 0;
                --$user_counters[$post['uid']];
            }
        }

        if (empty($unapproved_pids) && empty($threads_to_update)) {
            return false;
        }

        if (!empty($unapproved_pids)) {
            [$ph, $params] = $this->inClause($unapproved_pids);
            $db->sql_query_prepared("UPDATE posts SET visible = 0 WHERE pid IN ({$ph})", $params);
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
            update_forum_lastpost((int)$fid);
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

        $tid_list       = [];
        $forum_counters = [];
        $user_counters  = [];
        $posts_to_approve = [];

        [$ph, $params] = $this->inClause($tids);
        $query = $db->sql_query_prepared("SELECT * FROM threads WHERE tid IN ({$ph})", $params);
        while ($query && ($thread = $db->fetch_array($query))) {
            if ((int)$thread['visible'] !== 0) {
                continue;
            }

            $tid_list[] = (int)$thread['tid'];
            $forum      = get_forum($thread['fid']);
            $fid        = $forum['fid'];

            $forum_counters[$fid] ??= ['num_posts' => 0, 'num_threads' => 0, 'num_unapproved_posts' => 0];
            $user_counters[$thread['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];

            ++$forum_counters[$fid]['num_threads'];
            $forum_counters[$fid]['num_posts']            += $thread['replies'] + 1;
            $forum_counters[$fid]['num_unapproved_posts'] += $thread['replies'] + 1;

            if ($forum['usepostcounts'] != 0) {
                $q2 = $db->sql_query_prepared(
                    "SELECT COUNT(pid) AS posts, uid FROM posts WHERE tid = ? AND (visible = '1' OR pid = ?) AND uid > 0 GROUP BY uid",
                    [$thread['tid'], $thread['firstpost']]
                );
                while ($q2 && ($counter = $db->fetch_array($q2))) {
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

        [$ph, $params] = $this->inClause($tid_list);
        $db->sql_query_prepared("UPDATE threads SET visible = 1 WHERE tid IN ({$ph})", $params);

        $redirect_tids = [];
        [$ph, $params] = $this->inClause($tid_moved_list);
        $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE closed IN ({$ph})", $params);
        while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
            $redirect_tids[] = (int)$redirect_tid;
        }
        if (!empty($redirect_tids)) {
            $this->approve_threads($redirect_tids);
        }

        if (!empty($posts_to_approve)) {
            [$ph, $params] = $this->inClause($posts_to_approve);
            $db->sql_query_prepared("UPDATE posts SET visible = 1 WHERE pid IN ({$ph})", $params);
        }

        $plugins->run_hooks('class_moderation_approve_threads', $tids);

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'threads'           => "+{$c['num_threads']}",
                'unapprovedthreads' => "-{$c['num_threads']}",
                'posts'             => "+{$c['num_posts']}",
                'unapprovedposts'   => "-{$c['num_unapproved_posts']}",
            ]);
            update_forum_lastpost((int)$fid);
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

        $db->sql_query_prepared("DELETE FROM polls WHERE pid = ?", [$pid]);
        $db->sql_query_prepared("DELETE FROM pollvotes WHERE pid = ?", [$pid]);
        $db->sql_query_prepared("UPDATE threads SET poll = 0 WHERE poll = ?", [$pid]);

        return true;
    }
	
	
	
	// ── remove_thread_subscriptions ─────────────────────────────────────────────

    public function remove_thread_subscriptions(array|int $tids, bool $all = true, int $fid = 0): bool
    {
        global $db, $plugins;

        $tids = $this->normalizeIds($tids);
        if (empty($tids)) {
            return false;
        }

        if (!$all) {
            // Delete only subscriptions from users who no longer have permission to read the thread.
            $forum_parentlist = get_parent_list($fid);
            $query = $db->sql_query_prepared(
                "SELECT gid FROM forumpermissions WHERE fid IN ({$forum_parentlist}) AND (canview=0 OR canviewthreads=0)"
            );

            
			
			$groups = [];
while ($query && ($group = $db->fetch_array($query))) {
    $groups[] = (int)$group['gid'];
}

if (count($groups) > 0) {
    [$tid_ph, $tid_params] = $this->inClause($tids);
    [$grp_ph, $grp_params] = $this->inClause($groups);
    $query = $db->sql_query_prepared("
        SELECT s.tid, s.uid
        FROM threadsubscriptions s
        INNER JOIN users u ON (u.id = s.uid)
        WHERE s.tid IN ({$tid_ph})
          AND u.usergroup IN ({$grp_ph})
    ", [...$tid_params, ...$grp_params]);
    while ($query && ($subscription = $db->fetch_array($query))) {
        $db->sql_query_prepared(
            "DELETE FROM threadsubscriptions WHERE uid = ? AND tid = ?",
            [(int)$subscription['uid'], (int)$subscription['tid']]
        );
    }
}
			
			
			
        } else {
            // Delete all subscriptions of these threads
            [$ph, $params] = $this->inClause($tids);
            $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid IN ({$ph})", $params);
        }

        $arguments = ['tids' => $tids, 'all' => $all, 'fid' => $fid];
        $plugins->run_hooks('class_moderation_remove_thread_subscriptions', $arguments);

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

        [$ph, $params] = $this->inClause($tids);
        $db->sql_query_prepared("UPDATE threads SET closed = 1 WHERE tid IN ({$ph}) AND closed NOT LIKE 'moved|%'", $params);

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

        [$ph, $params] = $this->inClause($tids);
        $db->sql_query_prepared("UPDATE threads SET closed = 0 WHERE tid IN ({$ph})", $params);

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

        [$ph, $params] = $this->inClause($tids);
        $db->sql_query_prepared("UPDATE threads SET sticky = 1 WHERE tid IN ({$ph})", $params);

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

        [$ph, $params] = $this->inClause($tids);
        $db->sql_query_prepared("UPDATE threads SET sticky = 0 WHERE tid IN ({$ph})", $params);

        return true;
    }
	
	
	// ── remove_redirects ──────────────────────────────────────────────────────

    public function remove_redirects(int $tid): bool
    {
        global $db, $plugins;

        if ($tid <= 0) {
            return false;
        }

        $plugins->run_hooks('class_moderation_remove_redirects', $tid);

        $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE closed = ?", ["moved|{$tid}"]);
        while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
            $this->delete_thread((int)$redirect_tid);
        }

        return true;
    }
	
	
	
	 // ── expire_thread ─────────────────────────────────────────────────────────

    public function expire_thread(int $tid, int $deletetime): bool
    {
        global $db, $plugins;

        if ($tid <= 0) {
            return false;
        }

        $db->sql_query_prepared("UPDATE threads SET deletetime = ? WHERE tid = ?", [$deletetime, $tid]);

        $arguments = ['tid' => $tid, 'deletetime' => $deletetime];
        $plugins->run_hooks('class_moderation_expire_thread', $arguments);

        return true;
    }
	

    // ── delete_thread ─────────────────────────────────────────────────────────

    public function delete_thread(int $tid): bool
    {
        global $db, $kpscomment;

        $thread = get_thread($tid);
        if (!$thread) {
            return false;
        }

        $forum     = get_forum($thread['fid']);
        $userposts = [];
        $pids      = [];
        $num_unapproved_posts = $num_approved_posts = 0;

        $query = $db->sql_query_prepared("SELECT pid, uid, visible FROM posts WHERE tid = ?", [$tid]);
        while ($query && ($post = $db->fetch_array($query))) {
            $pids[] = (int)$post['pid'];

            if (!function_exists('remove_attachments')) {
                require_once INC_PATH . '/functions_upload.php';
            }
            remove_attachments((int)$post['pid']);

            if ((int)$post['visible'] === 0 || (int)$thread['visible'] === 0) {
                ++$num_unapproved_posts;
            } else {
                ++$num_approved_posts;
                $userposts[$post['uid']]['kps_posts'] = ($userposts[$post['uid']]['kps_posts'] ?? 0) + 1;
                if ($forum['usepostcounts'] != 0) {
                    $userposts[$post['uid']]['num_posts'] = ($userposts[$post['uid']]['num_posts'] ?? 0) + 1;
                }
            }
        }

        // Тема-редирект (moved|), неодобренная тема и форум без счёта тем в threadnum не входили
        if ((int)$thread['visible'] === 1 && $forum['usethreadcounts'] != 0 && !str_starts_with((string)$thread['closed'], 'moved|')) {
            $userposts[$thread['uid']]['num_threads'] = ($userposts[$thread['uid']]['num_threads'] ?? 0) + 1;
        }

        foreach ($userposts as $uid => $subtract) {
            // Бонусы снимаются за каждый одобренный пост, независимо от счёта постов форума
            if (!empty($subtract['kps_posts'])) {
                kps('-', $kpscomment * $subtract['kps_posts'], (int)$uid);
            }

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
            [$ph, $params] = $this->inClause($pids);
            $db->sql_query_prepared("DELETE FROM posts WHERE pid IN ({$ph})", $params);
            [$ph, $params] = $this->inClause($pids);
            $db->sql_query_prepared("DELETE FROM attachments WHERE pid IN ({$ph})", $params);

            // Delete attached files from disk
            [$ph, $params] = $this->inClause($pids);
            $files = $db->sql_query_prepared("SELECT * FROM comment_files WHERE post_id IN ({$ph})", $params);
            while ($files && ($file = $db->fetch_array($files))) {
                if (is_file($file['file_path'])) {
                    @unlink($file['file_path']);
                }
            }
            [$ph, $params] = $this->inClause($pids);
            $db->sql_query_prepared("DELETE FROM comment_files WHERE post_id IN ({$ph})", $params);
        }

        // Delete thread ratings
        $db->sql_query_prepared("DELETE FROM threadratings WHERE tid = ?", [$tid]);

        $db->sql_query_prepared("DELETE FROM threads WHERE tid = ?", [$tid]);
        $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid = ?", [$tid]);
        $db->sql_query_prepared("DELETE FROM polls WHERE tid = ?", [$tid]);
        $db->sql_query_prepared("DELETE FROM pollvotes WHERE pid = ?", [$thread['poll']]);
        $db->sql_query_prepared("DELETE FROM threadsread WHERE tid = ?", [$tid]);
        $db->sql_query_prepared("DELETE FROM reports WHERE type = 'forumpost' AND thread_id = ?", [$tid]);

        // Delete redirect threads
        $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE closed = ?", ["moved|{$tid}"]);
        while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
            $this->delete_thread((int)$redirect_tid);
        }

        $updated_counters = [
            'posts'           => "-{$num_approved_posts}",
            'unapprovedposts' => "-{$num_unapproved_posts}",
        ];

        match ((int)$thread['visible']) {
            1       => $updated_counters['threads']           = -1,
            default => $updated_counters['unapprovedthreads'] = -1,
        };

        // Редирект числится в форуме как один пост
        if (str_starts_with((string)$thread['closed'], 'moved|')) {
            if ((int)$thread['visible'] === 1) {
                $updated_counters['posts'] = -1;
            } else {
                $updated_counters['unapprovedposts'] = -1;
            }
        }

        update_forum_counters($thread['fid'], $updated_counters);
        update_forum_lastpost((int)$thread['fid']);

        return true;
    }

    // ── delete_post ───────────────────────────────────────────────────────────

    public function delete_post(int $pid): bool
    {
        global $db, $plugins, $kpscomment;

        $pid = $plugins->run_hooks('class_moderation_delete_post_start', $pid);
        $pid = (int)$pid;

        $query = $db->sql_query_prepared("
            SELECT p.pid, p.uid, p.fid, p.tid, p.visible, t.visible AS threadvisible
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            WHERE p.pid = ?
        ", [$pid]);
        $post = $query ? $db->fetch_array($query) : null;
        if (!$post) {
            return false;
        }

        // Неодобренный пост (или пост в неодобренной теме) в postnum не входил
        $post_counted = (int)$post['visible'] === 1 && (int)$post['threadvisible'] === 1;
        $forum        = get_forum($post['fid']);
        if ($post_counted && $forum['usepostcounts'] != 0) {
            update_user_counters($post['uid'], ['postnum' => '-1']);
        }

        kps('-', $kpscomment, (int)$post['uid']);

        if (!function_exists('remove_attachments')) {
            require INC_PATH . '/functions_upload.php';
        }
        remove_attachments($pid);

        $db->sql_query_prepared("DELETE FROM posts WHERE pid = ?", [$pid]);
        $db->sql_query_prepared("DELETE FROM reports WHERE type = 'forumpost' AND reported_id = ?", [$pid]);

        // Delete attached files from disk
        $files = $db->sql_query_prepared("SELECT * FROM comment_files WHERE post_id = ?", [$pid]);
        while ($files && ($file = $db->fetch_array($files))) {
            if (is_file($file['file_path'])) {
                @unlink($file['file_path']);
            }
        }
        $db->sql_query_prepared("DELETE FROM comment_files WHERE post_id = ?", [$pid]);

        $plugins->run_hooks('class_moderation_delete_post', $post['pid']);

        // Неодобренный пост считался в unapprovedposts, а не в replies/posts
        update_thread_counters((int)$post['tid'], (int)$post['visible'] === 1
            ? ['replies' => '-1']
            : ['unapprovedposts' => '-1']);
        update_last_post((int)$post['tid']);

        update_forum_counters($post['fid'], $post_counted
            ? ['posts' => '-1']
            : ['unapprovedposts' => '-1']);
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

        $first   = true;
        $message = '';
        $masterpid = $mastertid = $fid = $visible = 0;

        $thread_counters = [];
        $forum_counters  = [];
        $user_counters   = [];
        $threads         = [];

        [$ph, $params] = $this->inClause($pids);
        $query = $db->sql_query_prepared("
            SELECT p.pid, p.uid, p.fid, p.tid, p.visible, p.message,
                   t.visible AS threadvisible, t.replies AS threadreplies,
                   t.firstpost AS threadfirstpost, COUNT(a.aid) AS attachmentcount
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            LEFT JOIN attachments a ON a.pid = p.pid AND a.visible = 1
            WHERE p.pid IN ({$ph})
            GROUP BY p.pid
            ORDER BY p.dateline ASC, p.pid ASC
        ", $params);

        while ($query && ($post = $db->fetch_array($query))) {
            $threads[$post['tid']] = $post['tid'];
            $thread_counters[$post['tid']] ??= ['replies' => 0, 'unapprovedposts' => 0, 'attachmentcount' => 0];

            if ($first) {
                $masterpid = $post['pid'];
                $message   = $post['message'];
                $fid       = $post['fid'];
                $mastertid = $post['tid'];
                $visible   = $post['visible'];
                $first     = false;
            } else {
                $message .= $sep === 'new_line' ? "\n\n {$post['message']}" : "[hr]{$post['message']}";

                $forum_counters[$post['fid']] ??= ['num_posts' => 0, 'unapprovedposts' => 0];

                if ((int)$post['visible'] === 1) {
                    --$thread_counters[$post['tid']]['replies'];
                    $user_counters[$post['uid']] ??= ['num_posts' => 0, 'num_threads' => 0];
                    $post_forum = get_forum($post['fid']);

                    if ($post_forum['usepostcounts'] != 0 && (int)$post['threadvisible'] === 1) {
                        --$user_counters[$post['uid']]['num_posts'];
                    }
                    if ((int)$post['threadfirstpost'] === (int)$post['pid'] && $post_forum['usethreadcounts'] != 0 && (int)$post['threadvisible'] === 1) {
                        --$user_counters[$post['uid']]['num_threads'];
                    }
                    $thread_counters[$post['tid']]['attachmentcount'] -= $post['attachmentcount'];
                } else {
                    --$thread_counters[$post['tid']]['unapprovedposts'];
                }

                if ((int)$post['threadvisible'] === 1 && (int)$post['visible'] === 1) {
                    --$forum_counters[$post['fid']]['num_posts'];
                } else {
                    --$forum_counters[$post['fid']]['unapprovedposts'];
                }

                if ((int)$visible === 1) {
                    $thread_counters[$mastertid]['attachmentcount'] += $post['attachmentcount'];
                }
            }
        }

        $db->sql_query_prepared("UPDATE posts SET message = ? WHERE pid = ?", [$message, $masterpid]);

        [$ph, $params] = $this->inClause($pids);
        $db->sql_query_prepared("DELETE FROM posts WHERE pid IN ({$ph}) AND pid != ?", [...$params, $masterpid]);

        [$ph, $params] = $this->inClause($pids);
        $db->sql_query_prepared("UPDATE attachments SET pid = ? WHERE pid IN ({$ph})", [$masterpid, ...$params]);

        // Fix firstpost if needed
        [$ph, $params] = $this->inClause($pids);
        $query = $db->sql_query_prepared("SELECT tid, uid, fid, visible FROM threads WHERE firstpost IN ({$ph}) AND firstpost != ?", [...$params, $masterpid]);
        while ($query && ($thread = $db->fetch_array($query))) {
            $q2 = $db->sql_query_prepared(
                "SELECT pid, uid, visible FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1",
                [$thread['tid']]
            );
            $new_firstpost = $q2 ? $db->fetch_array($q2) : null;

            if ((int)$thread['visible'] !== (int)$new_firstpost['visible']) {
                $db->sql_query_prepared("UPDATE posts SET visible = ? WHERE pid = ?", [$thread['visible'], $new_firstpost['pid']]);
                $thread_counters[$thread['tid']] ??= ['replies' => 0, 'unapprovedposts' => 0, 'attachmentcount' => 0];
                // Пост переходит из своего счётчика в счётчик видимости темы (как в стоке MyBB)
                if ((int)$new_firstpost['visible'] === 1) {
                    --$thread_counters[$thread['tid']]['replies'];
                } else {
                    --$thread_counters[$thread['tid']]['unapprovedposts'];
                }
                if ((int)$thread['visible'] === 1) {
                    ++$thread_counters[$thread['tid']]['replies'];
                } else {
                    ++$thread_counters[$thread['tid']]['unapprovedposts'];
                }
            }

            $thread_forum = get_forum($thread['fid']);
            if ((int)$new_firstpost['uid'] !== (int)$thread['uid'] && $thread_forum['usethreadcounts'] != 0 && (int)$thread['visible'] === 1) {
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
                'unapprovedposts' => signed($c['unapprovedposts'] ?? 0),
                'attachmentcount' => signed($c['attachmentcount']),
            ]);
            update_last_post($tid);
        }

        foreach ($forum_counters as $fid => $c) {
            update_forum_counters($fid, [
                'posts'           => signed($c['num_posts']),
                'unapprovedposts' => signed($c['unapprovedposts']),
            ]);
            update_forum_lastpost((int)$fid);
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
    // Порт стоковой MyBB 1.8 без мягкого удаления: опрос, тема, рейтинг,
    // счётчики темы/форумов/пользователей, синхронизация первого поста.

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

        $forum_cache = $cache->read('forums');
        $t_postcount   = (int)($forum_cache[$thread['fid']]['usepostcounts'] ?? 1) === 1;
        $m_postcount   = (int)($forum_cache[$mergethread['fid']]['usepostcounts'] ?? 1) === 1;
        $t_threadcount = (int)($forum_cache[$thread['fid']]['usethreadcounts'] ?? 1) === 1;
        $t_visible     = (int)$thread['visible'] === 1;
        $m_visible     = (int)$mergethread['visible'] === 1;

        // ── Опрос: переносим, если у целевой темы его нет; иначе лишний удаляем ──
        if (!$thread['poll'] && $mergethread['poll']) {
            $db->sql_query_prepared("UPDATE polls SET tid = ? WHERE tid = ?", [$tid, $mergetid]);
            $db->sql_query_prepared("UPDATE threads SET poll = ? WHERE tid = ?", [(int)$mergethread['poll'], $tid]);
            // Отвязываем опрос от старой темы, иначе delete_thread() ниже удалит его голоса
            $db->sql_query_prepared("UPDATE threads SET poll = 0 WHERE tid = ?", [$mergetid]);
        } elseif ($mergethread['poll']) {
            $db->sql_query_prepared("DELETE FROM polls WHERE pid = ?", [(int)$mergethread['poll']]);
            $db->sql_query_prepared("DELETE FROM pollvotes WHERE pid = ?", [(int)$mergethread['poll']]);
            $db->sql_query_prepared("UPDATE threads SET poll = 0 WHERE tid = ?", [$mergetid]);
        }

        // ── postnum: одобренные посты присоединяемой темы ──
        // Пост учитывался, если его тема была одобрена и форум считает посты;
        // после слияния — по тем же условиям для целевой темы.
        $user_posts   = [];
        $was_counted  = $m_visible && $m_postcount;
        $will_counted = $t_visible && $t_postcount;
        if ($was_counted !== $will_counted) {
            $delta = $will_counted ? 1 : -1;
            $query = $db->sql_query_prepared(
                "SELECT uid, COUNT(pid) AS postnum FROM posts WHERE tid = ? AND visible = 1 AND uid > 0 GROUP BY uid",
                [$mergetid]
            );
            while ($query && ($post = $db->fetch_array($query))) {
                $user_posts[$post['uid']]['postnum'] = ($user_posts[$post['uid']]['postnum'] ?? 0) + $delta * (int)$post['postnum'];
            }
        }

        // ── Переносим посты, редиректы и жалобы ──
        $db->sql_query_prepared(
            "UPDATE posts SET tid = ?, fid = ?, replyto = 0 WHERE tid = ?",
            [$tid, $thread['fid'], $mergetid]
        );
        $db->sql_query_prepared("UPDATE threads SET closed = ? WHERE closed = ?", ["moved|{$tid}", "moved|{$mergetid}"]);
        // delete_thread() удаляет жалобы по thread_id — переносим их вместе с постами
        $db->sql_query_prepared(
            "UPDATE reports SET thread_id = ? WHERE type = 'forumpost' AND thread_id = ?",
            [$tid, $mergetid]
        );

        // ── Рейтинг: переносим оценки тех, кто ещё не оценивал целевую тему, и пересчитываем ──
        $db->sql_query_prepared(
            "UPDATE threadratings SET tid = ?
             WHERE tid = ? AND user_id NOT IN (
                 SELECT user_id FROM (SELECT user_id FROM threadratings WHERE tid = ?) AS existing
             )",
            [$tid, $mergetid, $tid]
        );
        $query   = $db->sql_query_prepared(
            "SELECT COUNT(*) AS num, COALESCE(SUM(rating), 0) AS total FROM threadratings WHERE tid = ?",
            [$tid]
        );
        $ratings = $query ? $db->fetch_array($query) : ['num' => 0, 'total' => 0];

        $db->sql_query_prepared(
            "UPDATE threads SET subject = ?, numratings = ?, totalratings = ? WHERE tid = ?",
            [$subject, (int)$ratings['num'], (int)$ratings['total'], $tid]
        );

        // ── Подписки ──
        $subscriptions = [];
        $query = $db->sql_query_prepared(
            "SELECT tid, uid FROM threadsubscriptions WHERE tid = ? OR tid = ?",
            [$mergetid, $tid]
        );
        while ($query && ($sub = $db->fetch_array($query))) {
            $subscriptions[(int)$sub['tid']][] = (int)$sub['uid'];
        }
        if (!empty($subscriptions[$mergetid])) {
            $update_users = array_values(array_filter(
                $subscriptions[$mergetid],
                fn(int $user) => !isset($subscriptions[$tid]) || !in_array($user, $subscriptions[$tid], true)
            ));
            if (!empty($update_users)) {
                [$ph, $params] = $this->inClause($update_users);
                $db->sql_query_prepared(
                    "UPDATE threadsubscriptions SET tid = ? WHERE tid = ? AND uid IN ({$ph})",
                    [$tid, $mergetid, ...$params]
                );
            }
        }
        $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE tid = ?", [$mergetid]);

        $merge_data = ['mergetid' => $mergetid, 'tid' => $tid, 'subject' => $subject];
        $plugins->run_hooks('class_moderation_merge_threads', $merge_data);

        // Сбрасываем кеш get_thread(): опрос уже отвязан, delete_thread() должен это видеть
        get_thread($mergetid, true);
        $this->delete_thread($mergetid);

        // ── Счётчики присоединённых постов (с бывшим первым постом) ──
        // В одобренной теме первый пост — в replies, в неодобренной — в unapprovedposts
        $pre_replies    = (int)$mergethread['replies']         + ($m_visible ? 1 : 0);
        $pre_unapproved = (int)$mergethread['unapprovedposts'] + ($m_visible ? 0 : 1);
        $post_replies    = $pre_replies;
        $post_unapproved = $pre_unapproved;

        // ── Первый пост целевой темы мог смениться (присоединённая тема старше) ──
        $query = $db->sql_query_prepared(
            "SELECT pid, uid, visible FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1",
            [$tid]
        );
        $new_firstpost = $query ? $db->fetch_array($query) : null;

        if ($new_firstpost) {
            if ((int)$thread['visible'] !== (int)$new_firstpost['visible']) {
                // Видимость первого поста приводим к видимости темы
                $db->sql_query_prepared("UPDATE posts SET visible = ? WHERE pid = ?", [(int)$thread['visible'], (int)$new_firstpost['pid']]);

                if ((int)$new_firstpost['visible'] === 1) {
                    --$post_replies;
                    ++$post_unapproved;
                } else {
                    --$post_unapproved;
                    ++$post_replies;
                    // Пост стал одобренным в одобренной теме — теперь он учитывается в postnum
                    if ($will_counted && (int)$new_firstpost['uid'] > 0) {
                        $user_posts[$new_firstpost['uid']]['postnum'] = ($user_posts[$new_firstpost['uid']]['postnum'] ?? 0) + 1;
                    }
                }
            }

            if ((int)$new_firstpost['pid'] !== (int)$thread['firstpost']) {
                update_first_post($tid);

                // Автор темы сменился — переносим ему тему в threadnum
                if ((int)$thread['uid'] !== (int)$new_firstpost['uid'] && $t_visible && $t_threadcount) {
                    $user_posts[$thread['uid']]['threadnum']        = ($user_posts[$thread['uid']]['threadnum'] ?? 0) - 1;
                    $user_posts[$new_firstpost['uid']]['threadnum'] = ($user_posts[$new_firstpost['uid']]['threadnum'] ?? 0) + 1;
                }
            }

            // Присоединённые посты считаем ответами на первый пост
            $db->sql_query_prepared(
                "UPDATE posts SET replyto = ? WHERE tid = ? AND replyto = 0 AND pid != ?",
                [(int)$new_firstpost['pid'], $tid, (int)$new_firstpost['pid']]
            );
        }

        // ── Счётчики форумов ──
        // Из старого форума уходят посты в старом состоянии, в целевой приходят в новом.
        // В неодобренной теме все её посты числятся в unapprovedposts форума.
        $forum_delta = [];
        $mfid = (int)$mergethread['fid'];
        $tfid = (int)$thread['fid'];
        foreach ([$mfid, $tfid] as $f) {
            $forum_delta[$f] ??= ['posts' => 0, 'unapprovedposts' => 0];
        }
        if ($m_visible) {
            $forum_delta[$mfid]['posts']           -= $pre_replies;
            $forum_delta[$mfid]['unapprovedposts'] -= $pre_unapproved;
        } else {
            $forum_delta[$mfid]['unapprovedposts'] -= $pre_replies + $pre_unapproved;
        }
        if ($t_visible) {
            $forum_delta[$tfid]['posts']           += $post_replies;
            $forum_delta[$tfid]['unapprovedposts'] += $post_unapproved;
        } else {
            $forum_delta[$tfid]['unapprovedposts'] += $post_replies + $post_unapproved;
        }
        foreach ($forum_delta as $f => $d) {
            if ($d['posts'] !== 0 || $d['unapprovedposts'] !== 0) {
                update_forum_counters($f, [
                    'posts'           => signed($d['posts']),
                    'unapprovedposts' => signed($d['unapprovedposts']),
                ]);
            }
        }
        if ($mfid !== $tfid) {
            update_forum_lastpost($mfid);
        }

        // ── Счётчики пользователей ──
        foreach ($user_posts as $uid => $counters) {
            $update = [];
            foreach ($counters as $key => $val) {
                if ($val !== 0) {
                    $update[$key] = signed($val);
                }
            }
            if (!empty($update)) {
                update_user_counters($uid, $update);
            }
        }

        // ── Счётчики целевой темы ──
        update_thread_counters($tid, [
            'replies'         => signed($post_replies),
            'unapprovedposts' => signed($post_unapproved),
            'attachmentcount' => signed((int)$mergethread['attachmentcount']),
        ]);
        update_last_post($tid);
        update_forum_lastpost($tfid);

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

        // В одобренной теме неодобренные посты считаются отдельно; в неодобренной — все посты неодобренные
        match ((int)$thread['visible']) {
            1       => [$num_threads, $num_posts, $num_unapproved_posts] = [1, $thread['replies'] + 1, (int)$thread['unapprovedposts']],
            default => [$num_unapproved_threads, $num_unapproved_posts] = [1, $thread['replies'] + $thread['unapprovedposts'] + 1],
        };

        $newtid = null;

        switch ($method) {
            case 'redirect':
                $redirect_data = ['tid' => $tid, 'new_fid' => $new_fid];
                $plugins->run_hooks('class_moderation_move_thread_redirect', $redirect_data);

                // Delete existing redirects to the same destination
                $query = $db->sql_query_prepared(
                    "SELECT tid FROM threads WHERE closed = ? AND fid = ?",
                    ["moved|{$tid}", $new_fid]
                );
                while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
                    $this->delete_thread((int)$redirect_tid);
                }

                $db->sql_query_prepared("UPDATE threads SET fid = ? WHERE tid = ?", [$new_fid, $tid]);
                $db->sql_query_prepared("UPDATE posts SET fid = ? WHERE tid = ?", [$new_fid, $tid]);

                
				$redirect_columns = ['fid', 'subject', 'uid', 'username', 'dateline', 'lastpost', 'lastposteruid', 'lastposter', 'views', 'replies', 'closed', 'sticky', 'visible'];
                   $redirect_values  = [
                       $thread['fid'], $thread['subject'], $thread['uid'], $thread['username'],
                       $thread['dateline'], $thread['lastpost'], $thread['lastposteruid'], $thread['lastposter'],
                       0, 0, "moved|{$tid}", $thread['sticky'], (int)$thread['visible'],
                ];
				

                $placeholders = implode(',', array_fill(0, count($redirect_columns), '?'));
                $db->sql_query_prepared(
                    "INSERT INTO threads (`" . implode('`,`', $redirect_columns) . "`) VALUES ({$placeholders})",
                    $redirect_values
                );
                $redirect_tid = (int)$db->insert_id();

                if ($redirect_expire) {
                    $this->expire_thread((int)$redirect_tid, $redirect_expire);
                }

                // Delete redirect if moving back to origin
                $query = $db->sql_query_prepared(
                    "SELECT tid FROM threads WHERE closed LIKE ? AND fid = ?",
                    ["moved|{$tid}", $new_fid]
                );
                while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
                    $this->delete_thread((int)$redirect_tid);
                }
                break;

            case 'copy':
                $copy_data = ['tid' => $tid, 'new_fid' => $new_fid];
                $plugins->run_hooks('class_moderation_copy_thread', $copy_data);

                $copy_columns = ['fid', 'subject', 'uid', 'username', 'dateline', 'firstpost', 'lastpost', 'lastposteruid', 'lastposter', 'views', 'replies', 'unapprovedposts', 'closed', 'sticky', 'visible', 'attachmentcount'];
                $copy_values  = [
                    $new_fid, $thread['subject'], $thread['uid'], $thread['username'], $thread['dateline'],
                    0, $thread['lastpost'], $thread['lastposteruid'], $thread['lastposter'],
                    $thread['views'], $thread['replies'], (int)$thread['unapprovedposts'], $thread['closed'], $thread['sticky'],
                    (int)$thread['visible'], $thread['attachmentcount'],
                ];
                $placeholders = implode(',', array_fill(0, count($copy_columns), '?'));
                $db->sql_query_prepared(
                    "INSERT INTO threads (`" . implode('`,`', $copy_columns) . "`) VALUES ({$placeholders})",
                    $copy_values
                );
                $newtid = (int)$db->insert_id();

                if ($thread['poll'] != 0) {
                    $q     = $db->sql_query_prepared("SELECT * FROM polls WHERE tid = ?", [$thread['tid']]);
                    $poll  = $q ? $db->fetch_array($q) : null;
                    $db->sql_query_prepared(
                        "INSERT INTO polls (`tid`,`question`,`dateline`,`options`,`votes`,`numoptions`,`numvotes`,`timeout`,`closed`,`multiple`,`public`) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                        [$newtid, $poll['question'], $poll['dateline'], $poll['options'], $poll['votes'], $poll['numoptions'], $poll['numvotes'], $poll['timeout'], $poll['closed'], $poll['multiple'], $poll['public']]
                    );
                    $new_pid = (int)$db->insert_id();

                    $q2 = $db->sql_query_prepared("SELECT * FROM pollvotes WHERE pid = ?", [$poll['pid']]);
                    while ($q2 && ($pv = $db->fetch_array($q2))) {
                        $db->sql_query_prepared(
                            "INSERT INTO pollvotes (`pid`,`uid`,`voteoption`,`dateline`) VALUES (?,?,?,?)",
                            [$new_pid, $pv['uid'], $pv['voteoption'], $pv['dateline']]
                        );
                    }
                    $db->sql_query_prepared("UPDATE threads SET poll = ? WHERE tid = ?", [$new_pid, $newtid]);
                }

                $q = $db->sql_query_prepared("SELECT * FROM posts WHERE tid = ?", [$thread['tid']]);
                while ($q && ($post = $db->fetch_array($q))) {
                    $db->sql_query_prepared(
                        "INSERT INTO posts (`tid`,`fid`,`subject`,`uid`,`username`,`dateline`,`ipaddress`,`edituid`,`edittime`,`visible`,`message`) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                        [$newtid, $new_fid, $post['subject'], $post['uid'], $post['username'], $post['dateline'], $post['ipaddress'], $post['edituid'], $post['edittime'], $post['visible'], $post['message']]
                    );
                    $new_pid = (int)$db->insert_id();

                    if ((int)$thread['firstpost'] === (int)$post['pid']) {
                        $db->sql_query_prepared("UPDATE threads SET firstpost = ? WHERE tid = ?", [$new_pid, $newtid]);
                    }

                    $q2 = $db->sql_query_prepared("SELECT * FROM attachments WHERE pid = ?", [$post['pid']]);
                    while ($q2 && ($att = $db->fetch_array($q2))) {
                        $db->sql_query_prepared(
                            "INSERT INTO attachments (`pid`,`uid`,`filename`,`filetype`,`filesize`,`attachname`,`downloads`,`visible`,`thumbnail`) VALUES (?,?,?,?,?,?,?,?,?)",
                            [$new_pid, $att['uid'], $att['filename'], $att['filetype'], $att['filesize'], $att['attachname'], $att['downloads'], $att['visible'], $att['thumbnail']]
                        );
                        $new_aid = (int)$db->insert_id();
                        $post['message'] = str_replace("[attachment={$att['aid']}]", "[attachment={$new_aid}]", $post['message']);
                    }

                    if (str_contains($post['message'], '[attachment=')) {
                        $db->sql_query_prepared("UPDATE posts SET message = ? WHERE pid = ?", [$post['message'], $new_pid]);
                    }
                }

                update_thread_data($newtid);
                break;

            default: // plain move
                $move_data = ['tid' => $tid, 'new_fid' => $new_fid];
                $plugins->run_hooks('class_moderation_move_simple', $move_data);
                $db->sql_query_prepared("UPDATE threads SET fid = ? WHERE tid = ?", [$new_fid, $tid]);
                $db->sql_query_prepared("UPDATE posts SET fid = ? WHERE tid = ?", [$new_fid, $tid]);

                // Тема вернулась в форум, где лежит её редирект, — редирект больше не нужен
                $query = $db->sql_query_prepared(
                    "SELECT tid FROM threads WHERE closed = ? AND fid = ?",
                    ["moved|{$tid}", $new_fid]
                );
                while ($query && ($redirect_tid = $db->fetch_field($query, 'tid'))) {
                    $this->delete_thread((int)$redirect_tid);
                }
                break;
        }

        // Update user post/thread counts if forum counting settings differ
        $query = $db->sql_query_prepared("
            SELECT COUNT(p.pid) AS posts, u.id
            FROM posts p
            LEFT JOIN users u ON u.id = p.uid
            WHERE p.tid = ? AND p.visible = 1
            GROUP BY u.id
            ORDER BY posts DESC
        ", [$tid]);
        while ($query && ($posters = $db->fetch_array($query))) {
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

        update_forum_counters($new_fid, [
            'threads'           => "+{$num_threads}",
            'unapprovedthreads' => "+{$num_unapproved_threads}",
            'posts'             => "+{$num_posts}",
            'unapprovedposts'   => "+{$num_unapproved_posts}",
        ]);
        update_forum_lastpost((int)$new_fid);

        if ($method !== 'copy') {
            if ($method === 'redirect') {
                match ((int)$thread['visible']) {
                    0       => [$num_unapproved_threads--, $num_unapproved_posts--],
                    default => [$num_threads--, $num_posts--],
                };
            }
            update_forum_counters($fid, [
                'threads'           => "-{$num_threads}",
                'unapprovedthreads' => "-{$num_unapproved_threads}",
                'posts'             => "-{$num_posts}",
                'unapprovedposts'   => "-{$num_unapproved_posts}",
            ]);
            update_forum_lastpost((int)$fid);
        }
		
		if ($newtid !== null) {
            return $newtid;
        }

        // Remove thread subscriptions for the users who no longer have permission to view the thread
        $this->remove_thread_subscriptions($tid, false, $new_fid);

        return $newtid ?? $tid;
    }

    // ── split_posts ───────────────────────────────────────────────────────────
    // Порт стоковой MyBB 1.8 без мягкого удаления: счётчики тем, форумов и
    // пользователей, вложения, смена первого поста в старой и целевой теме.

    public function split_posts(array $pids, int $tid, int $moveto, string $newsubject, int $destination_tid = 0): int|false
    {
        global $db, $plugins, $cache;

        $pids = $this->normalizeIds($pids);
        if (empty($pids)) {
            return false;
        }

        $forum_cache = $cache->read('forums');
        $counts_posts   = static fn(int $f): bool => (int)($forum_cache[$f]['usepostcounts'] ?? 1) === 1;
        $counts_threads = static fn(int $f): bool => (int)($forum_cache[$f]['usethreadcounts'] ?? 1) === 1;

        // Самый ранний из отделяемых постов — станет первым в новой теме
        [$ph, $params] = $this->inClause($pids);
        $q = $db->sql_query_prepared(
            "SELECT pid, uid, visible, username, dateline FROM posts WHERE pid IN ({$ph}) ORDER BY dateline, pid LIMIT 1",
            $params
        );
        $post_info = $q ? $db->fetch_array($q) : null;
        if (!$post_info) {
            return false;
        }

        $thread_counters = [];
        $forum_counters  = [];
        $user_counters   = [];
        $newtid          = $destination_tid;

        $init_forum = static function (array &$fc, int $f): void {
            $fc[$f] ??= ['threads' => 0, 'unapprovedthreads' => 0, 'posts' => 0, 'unapprovedposts' => 0];
        };
        $init_user = static function (array &$uc, int $u): void {
            $uc[$u] ??= ['postnum' => 0, 'threadnum' => 0];
        };

        if ($destination_tid === 0) {
            // ── Новая тема ──
            if (empty($forum_cache[$moveto])) {
                return false;
            }
            $visible = (int)$post_info['visible'];

            $split_columns = ['fid', 'subject', 'uid', 'username', 'dateline', 'firstpost', 'lastpost', 'lastposteruid', 'lastposter', 'views', 'replies', 'visible'];
            $split_values  = [
                $moveto, $newsubject, (int)$post_info['uid'], $post_info['username'], (int)$post_info['dateline'],
                (int)$post_info['pid'], (int)$post_info['dateline'], (int)$post_info['uid'], $post_info['username'],
                0, 0, $visible,
            ];
            $placeholders = implode(',', array_fill(0, count($split_columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO threads (`" . implode('`,`', $split_columns) . "`) VALUES ({$placeholders})",
                $split_values
            );
            $newtid = (int)$db->insert_id();

            $init_forum($forum_counters, $moveto);
            if ($visible === 1) {
                ++$forum_counters[$moveto]['threads'];
                if ($counts_threads($moveto) && (int)$post_info['uid'] > 0) {
                    $init_user($user_counters, (int)$post_info['uid']);
                    ++$user_counters[(int)$post_info['uid']]['threadnum'];
                }
            } else {
                ++$forum_counters[$moveto]['unapprovedthreads'];
            }

            $newthread = [
                'tid'      => $newtid,
                'fid'      => $moveto,
                'uid'      => (int)$post_info['uid'],
                'dateline' => (int)$post_info['dateline'],
                'visible'  => $visible,
            ];
        } else {
            // ── Существующая тема ──
            $newthread = get_thread($destination_tid);
            if (!$newthread) {
                return false;
            }
            $moveto = (int)$newthread['fid'];
        }

        $init_forum($forum_counters, $moveto);
        $new_visible   = (int)$newthread['visible'];
        $new_postcount = $counts_posts($moveto);

        // Посты до переноса — нужны их старые tid/fid
        [$ph, $params] = $this->inClause($pids);
        $q = $db->sql_query_prepared("
            SELECT p.pid, p.tid, p.fid, p.visible, p.uid, p.dateline,
                   t.visible AS threadvisible, t.firstpost, COUNT(a.aid) AS postattachmentcount
            FROM posts p
            LEFT JOIN threads t ON t.tid = p.tid
            LEFT JOIN attachments a ON a.pid = p.pid AND a.visible = 1
            WHERE p.pid IN ({$ph})
            GROUP BY p.pid
        ", $params);
        $rows = [];
        while ($q && ($row = $db->fetch_array($q))) {
            $rows[] = $row;
        }

        [$ph, $params] = $this->inClause($pids);
        $db->sql_query_prepared(
            "UPDATE posts SET tid = ?, fid = ?, replyto = 0 WHERE pid IN ({$ph})",
            [$newtid, $moveto, ...$params]
        );

        $thread_counters[$newtid] = ['replies' => 0, 'unapprovedposts' => 0, 'attachmentcount' => 0];

        foreach ($rows as $post) {
            $ptid = (int)$post['tid'];
            $pfid = (int)$post['fid'];
            $uid  = (int)$post['uid'];
            $pvis = (int)$post['visible'];
            $tvis = (int)$post['threadvisible'];
            $old_postcount = $counts_posts($pfid);

            $thread_counters[$ptid] ??= ['replies' => 0, 'unapprovedposts' => 0, 'attachmentcount' => 0];
            $init_forum($forum_counters, $pfid);
            $init_user($user_counters, $uid);

            // ── Убираем из старой темы ──
            if ($pvis === 1) {
                // Переносим в тему/форум, где пост не учитывается в postnum
                if ($tvis === 1 && $old_postcount && (!$new_postcount || $new_visible !== 1)) {
                    --$user_counters[$uid]['postnum'];
                }
                --$thread_counters[$ptid]['replies'];
            } else {
                --$thread_counters[$ptid]['unapprovedposts'];
            }
            if ($tvis === 1 && $pvis === 1) {
                --$forum_counters[$pfid]['posts'];
            } else {
                --$forum_counters[$pfid]['unapprovedposts'];
            }

            // Вложения переходят вместе с постом
            $thread_counters[$ptid]['attachmentcount']   -= (int)$post['postattachmentcount'];
            $thread_counters[$newtid]['attachmentcount'] += (int)$post['postattachmentcount'];

            // ── Отделён первый пост старой темы — назначаем новый ──
            if ((int)$post['firstpost'] === (int)$post['pid']) {
                $fq  = $db->sql_query_prepared(
                    "SELECT pid, visible, uid FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1",
                    [$ptid]
                );
                $nfp = $fq ? $db->fetch_array($fq) : null;

                if ($nfp) {
                    $nfp_uid = (int)$nfp['uid'];
                    $init_user($user_counters, $nfp_uid);

                    // Видимость первого поста приводим к видимости темы
                    if ($tvis !== (int)$nfp['visible']) {
                        $db->sql_query_prepared("UPDATE posts SET visible = ? WHERE pid = ?", [$tvis, (int)$nfp['pid']]);

                        if ((int)$nfp['visible'] === 1) {
                            --$thread_counters[$ptid]['replies'];
                        } else {
                            --$thread_counters[$ptid]['unapprovedposts'];
                        }
                        // До смены пост числился в unapprovedposts форума:
                        // либо тема неодобрена, либо сам пост
                        --$forum_counters[$pfid]['unapprovedposts'];

                        if ($tvis === 1) {
                            ++$thread_counters[$ptid]['replies'];
                            ++$forum_counters[$pfid]['posts'];
                            if ($old_postcount && $nfp_uid > 0) {
                                ++$user_counters[$nfp_uid]['postnum'];
                            }
                        } else {
                            ++$thread_counters[$ptid]['unapprovedposts'];
                            ++$forum_counters[$pfid]['unapprovedposts'];
                        }
                    }

                    // Сменился автор старой темы
                    if ($tvis === 1 && $counts_threads($pfid) && $uid !== $nfp_uid) {
                        --$user_counters[$uid]['threadnum'];
                        ++$user_counters[$nfp_uid]['threadnum'];
                    }

                    update_first_post($ptid);
                }
            }

            // ── Пост стал первым в существующей теме (он старше её первого поста) ──
            if ($destination_tid > 0 && (int)$post['pid'] === (int)$post_info['pid'] && (int)$post['dateline'] < (int)$newthread['dateline']) {
                if ($pvis !== $new_visible) {
                    $db->sql_query_prepared("UPDATE posts SET visible = ? WHERE pid = ?", [$new_visible, (int)$post['pid']]);
                    $pvis = $new_visible;
                }
                if ($new_visible === 1 && $counts_threads($moveto) && $uid !== (int)$newthread['uid']) {
                    ++$user_counters[$uid]['threadnum'];
                    $init_user($user_counters, (int)$newthread['uid']);
                    --$user_counters[(int)$newthread['uid']]['threadnum'];
                }
                update_first_post($newtid);
            }

            // ── Добавляем в новую тему ──
            if ($pvis === 1) {
                // Переносим в тему/форум, где пост начинает учитываться в postnum
                if ($new_visible === 1 && $new_postcount && (!$old_postcount || $tvis !== 1)) {
                    ++$user_counters[$uid]['postnum'];
                }
                ++$thread_counters[$newtid]['replies'];
            } else {
                ++$thread_counters[$newtid]['unapprovedposts'];
            }
            if ($new_visible === 1 && $pvis === 1) {
                ++$forum_counters[$moveto]['posts'];
            } else {
                ++$forum_counters[$moveto]['unapprovedposts'];
            }
        }

        // Первый пост новой темы не входит в replies/unapprovedposts
        if ($destination_tid === 0) {
            if ($new_visible === 1) {
                --$thread_counters[$newtid]['replies'];
            } else {
                --$thread_counters[$newtid]['unapprovedposts'];
            }
        }

        // Перенесённые посты считаем ответами на первый пост целевой темы
        $fq = $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1", [$newtid]);
        $new_first = $fq ? $db->fetch_array($fq) : null;
        if ($new_first) {
            $db->sql_query_prepared(
                "UPDATE posts SET replyto = ? WHERE tid = ? AND replyto = 0 AND pid != ?",
                [(int)$new_first['pid'], $newtid, (int)$new_first['pid']]
            );
        }

        $split_data = [
            'pids'            => $pids,
            'tid'             => $tid,
            'moveto'          => $moveto,
            'newsubject'      => $newsubject,
            'destination_tid' => $destination_tid,
        ];
        $plugins->run_hooks('class_moderation_split_posts', $split_data);

        // ── Пользователи ──
        foreach ($user_counters as $uid => $counters) {
            if ($uid <= 0) {
                continue;
            }
            $update = [];
            foreach ($counters as $key => $val) {
                if ($val !== 0) {
                    $update[$key] = signed($val);
                }
            }
            if (!empty($update)) {
                update_user_counters($uid, $update);
            }
        }

        // ── Темы ──
        foreach ($thread_counters as $t => $counters) {
            if ($t === $newtid) {
                // Тема первого поста новой темы = её название
                if ($destination_tid === 0 && $new_first) {
                    $db->sql_query_prepared(
                        "UPDATE posts SET subject = ?, replyto = 0 WHERE pid = ?",
                        [$newsubject, (int)$new_first['pid']]
                    );
                }
            } else {
                $q = $db->sql_query_prepared("
                    SELECT p.pid, t.subject
                    FROM posts p
                    LEFT JOIN threads t ON p.tid = t.tid
                    WHERE p.tid = ?
                    ORDER BY p.dateline ASC, p.pid ASC
                    LIMIT 1
                ", [$t]);
                $oldthread = $q ? $db->fetch_array($q) : null;
                // Тема могла опустеть целиком — тогда её удалит вызывающий код
                if ($oldthread) {
                    $db->sql_query_prepared(
                        "UPDATE posts SET subject = ?, replyto = 0 WHERE pid = ?",
                        [$oldthread['subject'], (int)$oldthread['pid']]
                    );
                }
            }

            update_thread_counters($t, array_map('signed', $counters));
            update_last_post($t);
        }

        // ── Форумы ──
        foreach ($forum_counters as $f => $counters) {
            update_forum_counters($f, array_map('signed', $counters));
            update_forum_lastpost((int)$f);
        }

        return $newtid;
    }

}