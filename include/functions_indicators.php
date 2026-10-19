<?php
declare(strict_types=1);


/**
 * Mark a particular thread as read for the current user.
 */
function mark_thread_read(int $tid, int $fid): void
{
    global $db, $CURUSER, $threadreadcut;

    if($threadreadcut > 0 && $CURUSER['id'] > 0)
    {
        $db->sql_query_prepared(
            "REPLACE INTO threadsread (tid, uid, dateline) VALUES(?, ?, ?)",
            [$tid, (int)$CURUSER['id'], TIMENOW]
        );
    }
    else
    {
        my_set_array_cookie("threadread", (string)$tid, TIMENOW, -1);
    }

    if(fetch_unread_count($fid) === 0)
    {
        mark_forum_read($fid);
    }
}

/**
 * Mark a particular forum as read.
 */
function mark_forum_read(int $fid): void
{
    global $db, $CURUSER, $threadreadcut;

    if($threadreadcut > 0 && $CURUSER['id'] > 0)
    {
        $db->sql_query_prepared(
            "REPLACE INTO forumsread (fid, uid, dateline) VALUES(?, ?, ?)",
            [$fid, (int)$CURUSER['id'], TIMENOW]
        );
    }
    else
    {
        my_set_array_cookie("forumread", (string)$fid, TIMENOW, -1);
    }
}

/**
 * Marks all forums as read.
 */
function mark_all_forums_read(): void
{
    global $db, $cache, $CURUSER, $threadreadcut;

    if(isset($CURUSER['id']) && $CURUSER['id'] > 0)
    {
        $uid = (int)$CURUSER['id'];
        $db->sql_query_prepared("UPDATE users SET lastvisit = ? WHERE id = ?", [TIMENOW, $uid]);

        require_once INC_PATH . "/functions_user.php";
        update_pm_count('', 2);

        if($threadreadcut > 0)
        {
            $forums = $cache->read('forums') ?: [];
            $fids   = array_map('intval', array_keys($forums));

            // Пакетами по 100 форумов на запрос
            foreach(array_chunk($fids, 100) as $chunk)
            {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?, ?, ?)'));
                $params = [];
                foreach($chunk as $fid)
                {
                    array_push($params, $fid, $uid, TIMENOW);
                }

                $db->sql_query_prepared(
                    "REPLACE INTO forumsread (fid, uid, dateline) VALUES {$placeholders}",
                    $params
                );
            }
        }
    }
    else
    {
        my_setcookie("mybb[readallforums]", '1');
        my_setcookie("mybb[lastvisit]", (string)TIMENOW);

        my_unsetcookie("mybb[threadread]");
        my_unsetcookie("mybb[forumread]");
    }
}


/**
 * Fetches the number of unread threads for the current user in a particular forum.
 *
 * @param int|string $fid The forum(s) ID(s)
 * @return int|false
 */
function fetch_unread_count($fid)
{
    global $mybb, $CURUSER, $threadreadcut;

    $forums_all = $forums_own = [];
    $forums = array_map('intval', is_string($fid) ? explode(',', $fid) : (array)$fid);

    foreach($forums as $forum)
    {
        $forum = (string)$forum;
        $permissions = []; // Здесь нужно добавить получение прав
        if(!empty($permissions['canonlyviewownthreads']))
        {
            $forums_own[] = $forum;
        }
        else
        {
            $forums_all[] = $forum;
        }
    }

    $where = $where2 = '';
    if(!empty($forums_own))
    {
        $where = "(fid IN (".implode(',', $forums_own).") AND uid = {$CURUSER['id']})";
        $where2 = "(t.fid IN (".implode(',', $forums_own).") AND t.uid = {$CURUSER['id']})";
    }

    if(!empty($forums_all))
    {
        if($where)
        {
            $where = "({$where} OR fid IN (".implode(',', $forums_all)."))";
            $where2 = "({$where2} OR t.fid IN (".implode(',', $forums_all)."))";
        }
        else
        {
            $where = 'fid IN ('.implode(',', $forums_all).')';
            $where2 = 't.fid IN ('.implode(',', $forums_all).')';
        }
    }

    $cutoff = TIMENOW - $threadreadcut*60*60*24;

    if($CURUSER['id'] == 0)
    {
        $tids = '';
        $threadsread = $forumsread = [];

        if(isset($mybb->cookies['mybb']['threadread']))
        {
            $threadsread = my_unserialize($mybb->cookies['mybb']['threadread'], false);
        }
        if(isset($mybb->cookies['mybb']['forumread']))
        {
            $forumsread = my_unserialize($mybb->cookies['mybb']['forumread'], false);
        }

        if(!empty($threadsread))
        {
            $tids = implode(',', array_map('intval', array_keys($threadsread)));
        }

        if(!empty($tids))
        {
            $count = 0;
            $query = $GLOBALS['db']->sql_query_prepared(
                "SELECT lastpost, tid, fid FROM threads
                 WHERE visible=1 AND closed NOT LIKE 'moved|%' AND {$where} AND lastpost > ?
                 LIMIT 100",
                [$cutoff]
            );

            while($thread = $GLOBALS['db']->fetch_array($query))
            {
                if((!isset($threadsread[$thread['tid']]) || $thread['lastpost'] > (int)$threadsread[$thread['tid']])
                    && (!isset($forumsread[$thread['fid']]) || $thread['lastpost'] > (int)$forumsread[$thread['fid']]))
                {
                    ++$count;
                }
            }

            return $count;
        }

        return false;
    }
    else
    {
        $db = $GLOBALS['db'];
        $uid = (int)$CURUSER['id'];
        $query = $db->sql_query_prepared("
            SELECT COUNT(t.tid) AS unread_count
            FROM threads t
            LEFT JOIN threadsread tr ON (tr.tid=t.tid AND tr.uid=?)
            LEFT JOIN forumsread fr ON (fr.fid=t.fid AND fr.uid=?)
            WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' AND {$where2} 
              AND t.lastpost > IFNULL(tr.dateline,?) 
              AND t.lastpost > IFNULL(fr.dateline,?) 
              AND t.lastpost > ?
        ", [$uid, $uid, $cutoff, $cutoff, $cutoff]);

        return (int)$db->fetch_field($query, "unread_count");
    }
}


