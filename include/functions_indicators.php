<?php
declare(strict_types=1);

/**
 * Mark a particular thread as read for the current user.
 *
 * @param int $tid The thread ID
 * @param int $fid The forum ID of the thread
 */
function mark_thread_read(int $tid, int $fid): void
{
    global $mybb, $CURUSER, $threadreadcut;

    // Can only do "true" tracking for registered users
    if($threadreadcut > 0 && $CURUSER['id'] > 0)
    {
        switch($GLOBALS['db']->type)
        {
            case "pgsql":
            case "sqlite":
                $GLOBALS['db']->replace_query("tsf_threadsread", [
                    'tid' => $tid,
                    'uid' => $CURUSER['id'],
                    'dateline' => TIMENOW
                ], ["tid", "uid"]);
                break;
            default:
                $GLOBALS['db']->write_query("
                    REPLACE INTO tsf_threadsread (tid, uid, dateline)
                    VALUES('{$tid}', '{$CURUSER['id']}', '".TIMENOW."')
                ");
        }
    }
    else
    {
        my_set_array_cookie("threadread", (string)$tid, TIMENOW, -1);
    }

    $unread_count = fetch_unread_count($fid);
    if($unread_count === 0)
    {
        mark_forum_read($fid);
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
    $forums = is_string($fid) ? explode(',', $fid) : (array)$fid;

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
            $query = $GLOBALS['db']->simple_select(
                "tsf_threads",
                "lastpost, tid, fid",
                "visible=1 AND closed NOT LIKE 'moved|%' AND {$where} AND lastpost > '{$cutoff}'",
                ["limit" => 100]
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
        $query = $db->sql_query("
            SELECT COUNT(t.tid) AS unread_count
            FROM tsf_threads t
            LEFT JOIN tsf_threadsread tr ON (tr.tid=t.tid AND tr.uid='{$uid}')
            LEFT JOIN tsf_forumsread fr ON (fr.fid=t.fid AND fr.uid='{$uid}')
            WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' AND {$where2} 
              AND t.lastpost > IFNULL(tr.dateline,{$cutoff}) 
              AND t.lastpost > IFNULL(fr.dateline,{$cutoff}) 
              AND t.lastpost > {$cutoff}
        ");

        return (int)$db->fetch_field($query, "unread_count");
    }
}

/**
 * Mark a particular forum as read.
 */
function mark_forum_read(int $fid): void
{
    global $CURUSER, $threadreadcut;

    if($threadreadcut > 0 && $CURUSER['id'] > 0)
    {
        $db = $GLOBALS['db'];
        $db->shutdown_query("
            REPLACE INTO tsf_forumsread (fid, uid, dateline)
            VALUES('{$fid}', '{$CURUSER['id']}', '".TIMENOW."')
        ");
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
    global $mybb, $db, $cache, $CURUSER, $threadreadcut;

    // Только для зарегистрированных пользователей
    if(isset($CURUSER['id']) && $CURUSER['id'] > 0)
    {
        $uid = (int)$CURUSER['id'];
        $db->update_query("users", ['lastvisit' => TIMENOW], "id='{$uid}'");

        require_once INC_PATH . "/functions_user.php";
        update_pm_count('', 2);

        if($threadreadcut > 0)
        {
            $forums = $cache->read('forums') ?: [];

            $update_count = ceil(count($forums) / 20);
            $update_count = max($update_count, 15);

            $mark_query = ($db->type === 'pgsql' || $db->type === 'sqlite') ? [] : '';

            $done = 0;
            foreach(array_keys($forums) as $fid)
            {
                $fid = (int)$fid; // безопасно привести к int

                if($db->type === 'pgsql' || $db->type === 'sqlite')
                {
                    $mark_query[] = ['fid' => $fid, 'uid' => $uid, 'dateline' => TIMENOW];
                }
                else
                {
                    if($mark_query !== '') $mark_query .= ',';
                    $mark_query .= "('{$fid}', '{$uid}', '".TIMENOW."')";
                }

                $done++;

                // Выполняем батч через каждые $update_count
                if($done % $update_count === 0)
                {
                    if($db->type === 'pgsql' || $db->type === 'sqlite')
                    {
                        foreach($mark_query as $replace_query)
                        {
                            add_shutdown([$db, 'replace_query'], ['forumsread', $replace_query, ['fid', 'uid']]);
                        }
                        $mark_query = [];
                    }
                    else
                    {
                        $db->shutdown_query("REPLACE INTO tsf_forumsread (fid, uid, dateline) VALUES {$mark_query}");
                        $mark_query = '';
                    }
                }
            }

            // Выполнить остаток
            if(!empty($mark_query))
            {
                if($db->type === 'pgsql' || $db->type === 'sqlite')
                {
                    foreach($mark_query as $replace_query)
                    {
                        add_shutdown([$db, 'replace_query'], ['forumsread', $replace_query, ['fid', 'uid']]);
                    }
                }
                else
                {
                    $db->shutdown_query("REPLACE INTO tsf_forumsread (fid, uid, dateline) VALUES {$mark_query}");
                }
            }
        }
    }
    else
    {
        // Гость: сохраняем куки
        my_setcookie("mybb[readallforums]", '1');
        my_setcookie("mybb[lastvisit]", (string)TIMENOW);

        my_unsetcookie("mybb[threadread]");
        my_unsetcookie("mybb[forumread]");
    }
}

