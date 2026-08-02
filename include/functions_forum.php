<?php

declare(strict_types=1);

if (!defined('FORUM_ACTIVE') || !defined('APP_INITIALIZED') || !defined('FORUM_SECURE')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}



// ── check_forum_password ──────────────────────────────────────────────────────
function check_forum_password(int|string $fid, int|string $pid = 0, bool $return = false): bool
{
    global $mybb, $lang, $forum_cache, $CURUSER, $BASEURL;

    $showform = true;

    if (!is_array($forum_cache)) {
        $forum_cache = cache_forums();
        if (!$forum_cache) {
            return false;
        }
    }

    if (isset($forum_cache[$fid]['parentlist'])) {
        $parents = explode(',', $forum_cache[$fid]['parentlist']);
        rsort($parents);

        foreach ($parents as $parent_id) {
            $parent_id = (int)$parent_id;
            if ($parent_id == $fid || $parent_id == $pid) {
                continue;
            }
            if (($forum_cache[$parent_id]['password'] ?? '') !== '') {
                check_forum_password($parent_id, $fid);
            }
        }
    }

    if (($forum_cache[$fid]['password'] ?? '') !== '') {
        if (isset($mybb->input['pwverify']) && $pid == 0) {
            if (my_hash_equals($forum_cache[$fid]['password'], $mybb->get_input('pwverify'))) {
                my_setcookie("forumpass[{$fid}]", md5($CURUSER['id'] . $mybb->get_input('pwverify')), null, true);
                $showform = false;
            } else {
                
				
				$pwnote = ''.$lang->global['wrong_forum_password'].'';
                
				
				$showform = true;
            }
        } else {
            $showform = !forum_password_validated($forum_cache[$fid]);
        }
    } else {
        $showform = false;
    }

    if ($return) {
        return $showform;
    }

    if ($showform) {
        if ($pid) {
            header("Location: {$BASEURL}/" . get_forum_link($fid));
        } else {
            $_SERVER['REQUEST_URI'] = htmlspecialchars_uni($_SERVER['REQUEST_URI']);
            
			
			
			
$pwform = '
<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/pw-modal.css">
<div class="pw-overlay">
    <form action="' . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') . '" method="post">
        <div class="pw-card">
            <div class="pw-top-bar"></div>
            <div class="pw-header">
                <div class="pw-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <div>
                    <p class="pw-title">' . $lang->global['password_required'] . '</p>
                    <p class="pw-sub">' . htmlspecialchars((string)($SITENAME ?? ''), ENT_QUOTES, 'UTF-8') . '</p>
                </div>
            </div>
            <div class="pw-body">
                <label class="pw-label" for="pwverify">' . $lang->global['enter_password_below'] . '</label>
                <span class="pw-note">' . $lang->global['forum_password_note'] . '</span>
                <div class="pw-input-wrap">
                    <input type="password" name="pwverify" id="pwverify" placeholder="••••••••" autocomplete="current-password">
                    <button type="button" class="pw-toggle" onclick="
                        var i=document.getElementById(\'pwverify\');
                        var ic=this.querySelector(\'i\');
                        i.type=i.type===\'password\'?\'text\':\'password\';
                        ic.className=i.type===\'password\'?\'bi bi-eye\':\'bi bi-eye-slash\';
                    "><i class="bi bi-eye"></i></button>
                </div>
                ' . (!empty($pwnote) ? '<div class="pw-error"><i class="bi bi-exclamation-circle-fill"></i> ' . $pwnote . '</div>' : '') . '
            </div>
            <div class="pw-footer">
                <button type="submit" name="submit" class="pw-btn">
                    <i class="bi bi-key-fill"></i>
                    ' . $lang->global['verify_forum_password'] . '
                </button>
            </div>
        </div>
    </form>
</div>';
			
			
			
			
			
			
			
			
			
			
            stdhead();
            build_breadcrumb();
            echo $pwform;
            stdfoot();
        }
        exit;
    }

    return false;
}

// ── forum_password_validated ──────────────────────────────────────────────────
function forum_password_validated(array $forum, bool $ignore_empty = false, bool $check_parents = false): bool
{
    global $mybb, $forum_cache, $CURUSER;

    if ($check_parents && isset($forum['parentlist'])) {
        if (!is_array($forum_cache)) {
            $forum_cache = cache_forums();
            if (!$forum_cache) {
                return false;
            }
        }

        $parents = explode(',', $forum['parentlist']);
        rsort($parents);

        foreach ($parents as $parent_id) {
            $parent_id = (int)$parent_id;
            if ($parent_id !== (int)$forum['fid'] && !forum_password_validated($forum_cache[$parent_id], true)) {
                return false;
            }
        }
    }

    return ($ignore_empty && $forum['password'] === '') || (
        isset($mybb->cookies['forumpass'][$forum['fid']]) &&
        my_hash_equals(
            md5($CURUSER['id'] . $forum['password']),
            $mybb->cookies['forumpass'][$forum['fid']]
        )
    );
}

// ── forum_permissions ─────────────────────────────────────────────────────────

function forum_permissions(int|string|null $fid = 0, int|string|null $uid = 0, int|string|null $gid = 0): array|bool
{
    global $db, $cache, $groupscache, $forum_cache, $fpermcache, $mybb,
           $cached_forum_permissions_permissions, $cached_forum_permissions, $CURUSER;

    // ----------------------------
    // 🔒 SAFE INIT (CRITICAL FIX)
    // ----------------------------

    $fid = (int)($fid ?? 0);
    $uid = (int)($uid ?? 0);

    if (!is_array($cached_forum_permissions_permissions)) {
        $cached_forum_permissions_permissions = [];
    }

    if (!is_array($cached_forum_permissions)) {
        $cached_forum_permissions = [];
    }

    if (!is_array($CURUSER)) {
        $CURUSER = $mybb->user ?? [];
    }

    // fallback uid
    if ($uid === 0) {
        $uid = (int)($CURUSER['id'] ?? $CURUSER['uid'] ?? 0);
    }

    // ----------------------------
    // 🔒 BUILD GROUP IDS (SAFE)
    // ----------------------------

    $groupperms = [];

    if (empty($gid)) {

        // CASE 1: different user
        if ($uid !== 0 && $uid !== (int)($CURUSER['id'] ?? 0)) {

            $user = get_user($uid);

           

            $gid = trim(
                ($user['usergroup'] ?? '1') .
                ',' .
                ($user['additionalgroups'] ?? '')
            );

            $groupperms = usergroup_permissions($gid);

        } else {

            // CASE 2: current user
            $usergroup = $CURUSER['usergroup'] ?? '1';

            if ($usergroup === '' || $usergroup === null) {
                $usergroup = '1';
            }

            $gid = (string)$usergroup;

            if (!empty($CURUSER['additionalgroups'])) {
                $gid .= ',' . $CURUSER['additionalgroups'];
            }

            $groupperms = (is_array($mybb->usergroup))
                ? $mybb->usergroup
                : usergroup_permissions($gid);
        }

    } else {
        $groupperms = usergroup_permissions($gid);
    }

    // ----------------------------
    // 🔒 FORUM CACHE SAFE LOAD
    // ----------------------------

    if (!is_array($forum_cache)) {
        $forum_cache = cache_forums();
    }


    // ----------------------------
    // 🔒 FORUM PERMISSION CACHE
    // ----------------------------

    if (!is_array($fpermcache)) {
        $fpermcache = $cache->read('forumpermissions');
    }



    // ----------------------------
    // 🔥 RETURN SINGLE FORUM
    // ----------------------------

    if ($fid) {

        if (!isset($cached_forum_permissions_permissions[$gid][$fid])) {

            $cached_forum_permissions_permissions[$gid][$fid] =
                fetch_forum_permissions((int)$fid, $gid, $groupperms);
        }

        return $cached_forum_permissions_permissions[$gid][$fid];
    }

    // ----------------------------
    // 🔥 RETURN ALL FORUMS
    // ----------------------------

    if (empty($cached_forum_permissions[$gid])) {

        foreach ($forum_cache as $forum) {

            if (!isset($forum['fid'])) {
                continue;
            }

            $cached_forum_permissions[$gid][$forum['fid']] =
                fetch_forum_permissions((int)$forum['fid'], $gid, $groupperms);
        }
    }

    return $cached_forum_permissions[$gid] ?? [];
}



// ── fetch_forum_permissions ───────────────────────────────────────────────────
function fetch_forum_permissions(int $fid, string $gid, array $groupperms): array
{
    global $groupscache, $forum_cache, $fpermcache, $mybb;

    $groups                 = array_filter(explode(',', $gid));
    $current_permissions    = [];
    $only_view_own_threads  = 1;
    $only_reply_own_threads = 1;

    if (empty($fpermcache[$fid])) {
        return $groupperms;
    }

    foreach ($groups as $group_id) {
        $group_id = trim($group_id);

        $level_permissions = match(true) {
            !empty($fpermcache[$fid][$group_id])  => $fpermcache[$fid][$group_id],
            !empty($groupscache[$group_id])        => $groupscache[$group_id],
            default                                => null,
        };

        if ($level_permissions === null) {
            continue;
        }

        foreach ($level_permissions as $permission => $access) {
            if (
                empty($current_permissions[$permission]) ||
                $access >= $current_permissions[$permission] ||
                ($access === 'yes' && $current_permissions[$permission] === 'no')
            ) {
                $current_permissions[$permission] = $access;
            }
        }

        if (!empty($level_permissions['canview']) && empty($level_permissions['canonlyviewownthreads'])) {
            $only_view_own_threads = 0;
        }

        if (!empty($level_permissions['canpostreplys']) && empty($level_permissions['canonlyreplyownthreads'])) {
            $only_reply_own_threads = 0;
        }
    }

    if (empty($current_permissions)) {
        $current_permissions = $groupperms;
    }

    $current_permissions['canonlyviewownthreads']  = ($only_view_own_threads  && isset($current_permissions['canonlyviewownthreads']))  ? 1 : 0;
    $current_permissions['canonlyreplyownthreads'] = ($only_reply_own_threads && isset($current_permissions['canonlyreplyownthreads'])) ? 1 : 0;

    return $current_permissions;
}




// ── build_highlight_array ─────────────────────────────────────────────────────
function build_highlight_array_sort(string $a, string $b): int
{
    return strlen($b) - strlen($a);
}

function build_highlight_array(array|string $terms): array
{
    $minsearchword = 3;

    if (is_array($terms)) {
        $terms = implode(' ', $terms);
    }

    $terms = str_replace(['(', ')', '+', '-', '~'], '', $terms);
    $words = [];

    if (str_contains($terms, '"')) {
        $inquote = false;
        foreach (explode('"', $terms) as $phrase) {
            $phrase = htmlspecialchars_uni($phrase);
            if ($phrase === '') {
                $inquote = !$inquote;
                continue;
            }

            if ($inquote) {
                $words[] = trim($phrase);
            } else {
                foreach (preg_split('#\s{1,}#', $phrase, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    if (strlen($word) >= $minsearchword) {
                        $words[] = trim($word);
                    }
                }
            }
            $inquote = !$inquote;
        }
    } else {
        $terms = htmlspecialchars_uni($terms);
        foreach (preg_split('#\s{1,}#', $terms, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (strlen($word) >= $minsearchword) {
                $words[] = trim($word);
            }
        }
    }

    usort($words, 'build_highlight_array_sort');

    $highlight_cache = [];
    $skip = ['', 'or', 'not', 'and'];

    foreach ($words as $word) {
        $word = trim(my_strtolower($word));
        if (in_array($word, $skip, true)) {
            continue;
        }
        $find = '/(?<!&|&#)\b([[:alnum:]]*)('. preg_quote($word, '/') .')(?![^<>]*?>)/ui';
        $highlight_cache[$find] = '$1<span class="highlight" style="padding-left:0;padding-right:0;">$2</span>';
    }

    return $highlight_cache;
}

// ── get_child_list ────────────────────────────────────────────────────────────
function get_child_list(int $fid): array
{
    static $forums_by_parent;

    $forums = [];

    if (!is_array($forums_by_parent)) {
        foreach (cache_forums() as $forum) {
            if ($forum['active'] != 0) {
                $forums_by_parent[$forum['pid']][$forum['fid']] = $forum;
            }
        }
    }

    if (!isset($forums_by_parent[$fid])) {
        return $forums;
    }

    foreach ($forums_by_parent[$fid] as $forum) {
        $forums[] = (int)$forum['fid'];
        $children = get_child_list((int)$forum['fid']);
        if (!empty($children)) {
            $forums = array_merge($forums, $children);
        }
    }

    return $forums;
}



// ── is_member ─────────────────────────────────────────────────────────────────
function is_member(array|string|int $groups, array|int|false $user = false): array
{
    global $CURUSER;

    if (empty($groups)) {
        return [];
    }

    if ($user === false) {
        $user = $CURUSER;
    } elseif (!is_array($user)) {
        $user = get_user((int)$user);
    }

    $memberships   = array_map('intval', explode(',', (string)($user['additionalgroups'] ?? '')));
    $memberships[] = (int)$user['usergroup'];

    if (!is_array($groups)) {
        if ((int)$groups === -1) {
            return $memberships;
        }
        $groups = is_string($groups) ? explode(',', $groups) : [(int)$groups];
    }

    $groups = array_filter(array_map('intval', $groups));

    return array_intersect($groups, $memberships);
}

// ── log_moderator_action ──────────────────────────────────────────────────────
function log_moderator_action(array $data, string $action = ''): void
{
    global $db, $CURUSER, $session;

    $fid  = (int)($data['fid'] ?? 0);  unset($data['fid']);
    $tid  = (int)($data['tid'] ?? 0);  unset($data['tid']);
    $pid  = (int)($data['pid'] ?? 0);  unset($data['pid']);
    $tids = (array)($data['tids'] ?? []); unset($data['tids']);

    $serialized = is_array($data) ? my_serialize($data) : $data;

    $uid       = (int)$CURUSER['id'];
    $dateline  = TIMENOW;
    $ipaddress = $session->packedip;

    // Один или несколько tid — строим общий multi-row INSERT через prepared statement
    $tid_list = $tids ?: [$tid];

    $placeholders = [];
    $params       = [];

    foreach ($tid_list as $t) {
        $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
        array_push($params, $uid, $dateline, $fid, (int)$t, $pid, $action, $serialized, $ipaddress);
    }

    $sql = "INSERT INTO moderatorlog (`uid`,`dateline`,`fid`,`tid`,`pid`,`action`,`data`,`ipaddress`)
            VALUES " . implode(', ', $placeholders);

    $db->sql_query_prepared($sql, $params);
}

// ── get_subscription_method ───────────────────────────────────────────────────
function get_subscription_method(int $tid = 0, array $postoptions = []): string
{
    global $db, $CURUSER;

    $methods = ['', 'none', 'email', 'pm'];
    $method  = max(0, (int)$CURUSER['subscriptionmethod']);

    if ($tid <= 0) {
        return $methods[$method] ?? '';
    }

    if (isset($postoptions['subscriptionmethod'])) {
        $m = trim($postoptions['subscriptionmethod']);
        return in_array($m, $methods, true) ? $m : '';
    }

    $query = $db->sql_query_prepared(
        "SELECT tid, notification FROM threadsubscriptions WHERE tid = ? AND uid = ? LIMIT 1",
        [$tid, (int)$CURUSER['id']]
    );
    $subscription = $query ? $db->fetch_array($query) : null;

    if ($subscription) {
        $method = (int)$subscription['notification'] + 1;
    }

    return $methods[$method] ?? '';
}

// ── get_inactive_forums ───────────────────────────────────────────────────────
function get_inactive_forums(): string
{
    global $forum_cache, $cache;

    if (!$forum_cache) {
        cache_forums();
    }

    $inactive = [];

    foreach ($forum_cache as $fid => $forum) {
        if ($forum['active'] == 0) {
            $inactive[] = $fid;
            foreach ($forum_cache as $fid1 => $forum1) {
                if (
                    str_contains(',' . $forum1['parentlist'] . ',', ',' . $fid . ',') &&
                    !in_array($fid1, $inactive, true)
                ) {
                    $inactive[] = $fid1;
                }
            }
        }
    }

    return implode(',', $inactive);
}

// ── get_unviewable_forums ─────────────────────────────────────────────────────
function get_unviewable_forums(bool $only_readable_threads = false): string
{
    global $forum_cache, $permissioncache, $mybb;

    if (!is_array($forum_cache)) {
        cache_forums();
    }

    if (!is_array($permissioncache)) {
        $permissioncache = forum_permissions();
    }

    $unviewable = [];

    foreach ($forum_cache as $fid => $forum) {
        $perms = $permissioncache[$forum['fid']] ?? $mybb->usergroup;

        $pwverified = forum_password_validated($forum, true);

        if ($pwverified) {
            foreach (explode(',', $forum['parentlist']) as $parent) {
                if (!forum_password_validated($forum_cache[(int)$parent] ?? [], true)) {
                    $pwverified = false;
                    break;
                }
            }
        }

        if (
            empty($perms['canview']) ||
            !$pwverified ||
            ($only_readable_threads && empty($perms['canviewthreads']))
        ) {
            $unviewable[] = $forum['fid'];
        }
    }

    return implode(',', $unviewable);
}

// ── get_attachment_icon ───────────────────────────────────────────────────────
function get_attachment_icon(string $ext): string
{
    global $cache, $attachtypes;

    if (!$attachtypes) {
        $attachtypes = $cache->read('attachtypes');
    }

    $ext  = my_strtolower($ext);
    $name = htmlspecialchars_uni($attachtypes[$ext]['name'] ?? $ext);

    if (!empty($attachtypes[$ext]['icon'])) {
        $icon = trim($attachtypes[$ext]['icon']);

        if (str_starts_with($icon, '<')) {
            if (!str_contains($icon, 'title=')) {
                $pos  = strpos($icon, '>');
                $icon = $pos !== false
                    ? substr($icon, 0, $pos) . " title=\"{$name}\">" . substr($icon, $pos + 1)
                    : $icon;
            }
            if (!str_contains($icon, 'font-size:')) {
                if (str_contains($icon, 'style=')) {
                    $icon = str_replace('style="', 'style="font-size:16px; ', $icon);
                } else {
                    $pos  = strpos($icon, '>');
                    $icon = $pos !== false
                        ? substr($icon, 0, $pos) . ' style="font-size:16px;">' . substr($icon, $pos + 1)
                        : $icon;
                }
            }
            return $icon;
        }
    }

    return "<i class=\"fas fa-file\" title=\"{$name}\" style=\"font-size:16px;color:#ccc;\"></i>";
}

// ── signed ────────────────────────────────────────────────────────────────────
function signed(int $int): string
{
    return $int < 0 ? (string)$int : "+{$int}";
}

// ── get_parent_list ───────────────────────────────────────────────────────────
function get_parent_list(int $fid): string
{
    global $forum_cache;
    static $forumarraycache;

    if (!empty($forumarraycache[$fid])) {
        return $forumarraycache[$fid]['parentlist'];
    }

    if (!empty($forum_cache[$fid])) {
        return $forum_cache[$fid]['parentlist'];
    }

    cache_forums();
    return $forum_cache[$fid]['parentlist'] ?? '';
}

// ── get_announcement_link ─────────────────────────────────────────────────────
function get_announcement_link(int $aid = 0): string
{
    return htmlspecialchars_uni(str_replace('{aid}', (string)$aid, ANNOUNCEMENT_URL));
}

// ── build_parent_list ─────────────────────────────────────────────────────────
function build_parent_list(int $fid, string $column = 'fid', string $joiner = 'OR', string $parentlist = ''): string
{
    if (!$parentlist) {
        $parentlist = get_parent_list($fid);
    }

    $parts = array_map(
        fn($val) => "{$column}='{$val}'",
        explode(',', $parentlist)
    );

    return '(' . implode(" {$joiner} ", $parts) . ')';
}

// ── get_forum ─────────────────────────────────────────────────────────────────
function get_forum(int|string $fid, bool $active_override = false): array|false
{
    global $cache;
    static $forum_cache;

    if (!isset($forum_cache) || !is_array($forum_cache)) {
        $forum_cache = $cache->read('forums');
    }

    if (empty($forum_cache[$fid])) {
        return false;
    }

    if (!$active_override) {
        foreach (explode(',', $forum_cache[$fid]['parentlist']) as $parent) {
            if (($forum_cache[(int)$parent]['active'] ?? 1) == 0) {
                return false;
            }
        }
    }

    return $forum_cache[$fid];
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

// ── update_first_post ─────────────────────────────────────────────────────────
function update_first_post(int $tid): void
{
    global $db;

    $first_query = $db->sql_query_prepared("
        SELECT u.id, u.username, p.pid, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = ?
        ORDER BY p.dateline ASC, p.pid ASC LIMIT 1
    ", [$tid]);
    $first = $first_query ? $db->fetch_array($first_query) : null;

    $db->sql_query_prepared("
        UPDATE threads
        SET firstpost = ?, username = ?, uid = ?, dateline = ?
        WHERE tid = ?
    ", [
        (int)$first['pid'],
        $first['username'] ?: $first['postusername'],
        (int)$first['id'],
        (int)$first['dateline'],
        $tid,
    ]);
}

// ── update_user_counters ──────────────────────────────────────────────────────
function update_user_counters(int|string $uid, array $changes = []): void
{
    global $db;

    $uid = (int)$uid;
    
	$counters = ['postnum', 'threadnum'];
    $query    = $db->sql_query_prepared(
        "SELECT " . implode(',', $counters) . " FROM users WHERE id = ?",
        [$uid]
    );
    $user = $query ? $db->fetch_array($query) : null;

    if (!$user) {
        return;
    }

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
            ? $user[$counter] + (int)$val
            : (int)$val;

        $update[$counter] = max(0, $new);
    }

    if (!empty($update)) {
        // Имена колонок берутся только из фиксированного списка $counters выше — не из ввода
        $set      = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($update)));
        $params   = array_values($update);
        $params[] = $uid;

        $db->sql_query_prepared("UPDATE users SET {$set} WHERE id = ?", $params);
    }
}

// ── update_last_post ──────────────────────────────────────────────────────────
function update_last_post(int $tid): bool
{
    global $db;

    $last_query = $db->sql_query_prepared("
        SELECT u.id, u.username, p.username AS postusername, p.dateline
        FROM posts p LEFT JOIN users u ON u.id = p.uid
        WHERE p.tid = ? AND p.visible = '1'
        ORDER BY p.dateline DESC, p.pid DESC LIMIT 1
    ", [$tid]);
    $last = $last_query ? $db->fetch_array($last_query) : null;

    if (!$last) {
        return false;
    }

    $last['username'] = $last['username'] ?: $last['postusername'];

    if (empty($last['dateline'])) {
        $first_query = $db->sql_query_prepared("
            SELECT u.id, u.username, p.username AS postusername, p.dateline
            FROM posts p LEFT JOIN users u ON u.id = p.uid
            WHERE p.tid = ?
            ORDER BY p.dateline ASC, p.pid ASC LIMIT 1
        ", [$tid]);
        $first = $first_query ? $db->fetch_array($first_query) : null;

        $last['username'] = $first['username'] ?: $first['postusername'];
        $last['id']       = $first['id'];
        $last['dateline'] = $first['dateline'];
    }

    $db->sql_query_prepared("
        UPDATE threads
        SET lastpost = ?, lastposter = ?, lastposteruid = ?
        WHERE tid = ?
    ", [
        (int)$last['dateline'],
        $last['username'],
        (int)$last['id'],
        $tid,
    ]);

    return true;
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

// ── update_forum_counters ─────────────────────────────────────────────────────
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

// ── get_post_link ─────────────────────────────────────────────────────────────
function get_post_link(int|string $pid, int|string $tid = 0): string
{
    $pid = (int)$pid;
    $tid = (int)$tid;
	
	if ($tid > 0) {
        return htmlspecialchars_uni(
            str_replace(['{tid}', '{pid}'], [$tid, $pid], THREAD_URL_POST)
        );
    }
    return htmlspecialchars_uni(str_replace('{pid}', (string)$pid, POST_URL));
}

// ── get_forum_link ────────────────────────────────────────────────────────────
function get_forum_link(int|string $fid, int|string $page = 0): string
{
    $fid  = (int)$fid;
    $page = (int)$page;
	
	
	if ($page > 0) {
        return htmlspecialchars_uni(
            str_replace(['{fid}', '{page}'], [$fid, $page], FORUM_URL_PAGED)
        );
    }
    return htmlspecialchars_uni(str_replace('{fid}', (string)$fid, FORUM_URL));
}

// ── build_breadcrumb ──────────────────────────────────────────────────────────
function build_breadcrumb(): void
{
    global $nav, $navbits, $f_threadsperpage;

    $navsep = '&nbsp; <i class="fa-solid fa-angle-right little"></i> &nbsp;';
    $activesep = '';

    if (is_array($navbits)) {
        reset($navbits);
        foreach ($navbits as $key => $navbit) {
            if (!isset($navbits[$key + 1])) {
                continue;
            }

            $sep = isset($navbits[$key + 2]) ? $navsep : '';
            $multipage_dropdown = '';

            if (!empty($navbit['multipage'])) {
                $tpp = max(1, (int)($f_threadsperpage ?: 20));
                $mp  = multipage((int)$navbit['multipage']['num_threads'], $tpp, (int)$navbit['multipage']['current_page'], $navbit['multipage']['url'], true);
                if ($mp) {
                    $sep = $multipage_dropdown . $sep;
                }
            }

            $navbit['url'] = preg_replace('/&amp;page=1$/', '', str_replace('-page-1.html', '.html', $navbit['url']));
            $nav .= '<a href="' . $navbit['url'] . '">' . $navbit['name'] . '</a>' . $sep;
        }

        $navsize = count($navbits);
        $navbit  = $navbits[$navsize - 1];
    }

    if ($nav) {
        $activesep = '&nbsp; <i class="fa-solid fa-angle-right little"></i> &nbsp;';
    }

    $activebit = '<br><div class="border-bottom border-2 mb-0 mt-3 rounded-0"><h3>' . $navbit['name'] . '</h3></div><br>';

    echo '
    <div class="container mt-3">
        <div class="navigation">
            ' . $nav . $activesep . $activebit . '
        </div>
    </div>';
}

// ── build_forum_breadcrumb ────────────────────────────────────────────────────
function build_forum_breadcrumb(int|string $fid, array $multipage = []): int
{
    global $pforumcache, $forum_cache, $navbits, $BASEURL, $archiveurl;

    
	$fid = (int)$fid;
	
	if (!$pforumcache) {
        if (!is_array($forum_cache)) {
            cache_forums();
        }
        foreach ($forum_cache as $val) {
            $pforumcache[$val['fid']][$val['pid']] = $val;
        }
    }

    if (!is_array($pforumcache[$fid] ?? null)) {
        return 1;
    }

    foreach ($pforumcache[$fid] as $forumnav) {
        if ($fid !== (int)$forumnav['fid']) {
            continue;
        }

        if (!empty($pforumcache[$forumnav['pid']])) {
            build_forum_breadcrumb((int)$forumnav['pid']);
        }

        $navsize = count($navbits);
        $navbits[$navsize]['name'] = preg_replace('#&(?!\#[0-9]+;)#si', '&amp;', $forumnav['name']);

        if (defined('IN_ARCHIVE')) {
            $navbits[$navsize]['url'] = in_array($pforumcache[$fid][$forumnav['pid']]['type'] ?? '', ['f', 'c'])
                ? "{$BASEURL}forum-{$forumnav['fid']}.html"
                : $archiveurl . '/index.php';
        } elseif (!empty($multipage)) {
            $navbits[$navsize]['url']       = get_forum_link((int)$forumnav['fid'], (int)$multipage['current_page']);
            $navbits[$navsize]['multipage'] = array_merge($multipage, ['url' => str_replace('{fid}', (string)$forumnav['fid'], FORUM_URL_PAGED)]);
        } else {
            $navbits[$navsize]['url'] = get_forum_link((int)$forumnav['fid']);
        }
    }

    return 1;
}

// ── get_thread_link ───────────────────────────────────────────────────────────
function get_thread_link(int|string $tid, int|string $page = 0, string $action = ''): string
{
    $tid  = (int)$tid;
    $page = (int)$page;
	
	$template = match(true) {
        $page > 1 && $action !== '' => THREAD_URL_ACTION,
        $page > 1                   => THREAD_URL_PAGED,
        $action !== ''              => THREAD_URL_ACTION,
        default                     => THREAD_URL,
    };

    $link = str_replace(['{tid}', '{page}', '{action}'], [$tid, $page, $action], $template);
    return htmlspecialchars_uni($link);
}



// ── get_post ──────────────────────────────────────────────────────────────────
function get_post(int $pid): array|false
{
    global $db;
    static $post_cache;

    if (isset($post_cache[$pid])) {
        return $post_cache[$pid];
    }

    $query = $db->sql_query_prepared("SELECT * FROM posts WHERE pid = ?", [$pid]);
    $post  = $query ? $db->fetch_array($query) : null;
    $post_cache[$pid] = $post ?: false;

    return $post_cache[$pid];
}


// ── add_breadcrumb ────────────────────────────────────────────────────────────
function add_breadcrumb(string $name, string $url = ''): void
{
    global $navbits;

    if (!is_array($navbits)) {
        $navbits = [];
    }

    $navbits[] = ['name' => $name, 'url' => $url];
}

// ── reset_breadcrumb ──────────────────────────────────────────────────────────
function reset_breadcrumb(): void
{
    global $navbits;

    $first = [
        'name' => $navbits[0]['name'] ?? '',
        'url'  => $navbits[0]['url']  ?? '',
    ];

    if (!empty($navbits[0]['options'])) {
        $first['options'] = $navbits[0]['options'];
    }

    $GLOBALS['navbits'] = [$first];
}



// ── build_forum_jump ──────────────────────────────────────────────────────────
function build_forum_jump(
    int|string $pid        = 0,
    int|string $selitem    = 0,
    int|string $addselect  = 1,
    string     $depth      = '',
    int|string $showextras = 1,
    bool       $showall    = false,
    string     $permissions= '',
    string     $name       = 'fid'
): string {
    global $forum_cache, $jumpfcache, $permissioncache, $mybb;

    $pid        = (int)$pid;
    $selitem    = (int)$selitem;
    $addselect  = (int)$addselect;
    $showextras = (int)$showextras;

    if (!is_array($jumpfcache)) {
        if (!is_array($forum_cache)) {
            cache_forums();
        }
        foreach ($forum_cache as $forum) {
            if ($forum['active'] != 0) {
                $jumpfcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
            }
        }
    }

    if (!is_array($permissioncache)) {
        $permissioncache = forum_permissions();
    }

    $bits = '';

    if (isset($jumpfcache[$pid]) && is_array($jumpfcache[$pid])) {
        foreach ($jumpfcache[$pid] as $main) {
            foreach ($main as $forum) {
                $selected = $selitem === (int)$forum['fid'] ? ' selected="selected"' : '';
                $fname    = htmlspecialchars_uni(strip_tags($forum['name']));
                $bits    .= "<option value=\"{$forum['fid']}\"{$selected}>{$depth} {$fname}</option>";
                if (!empty($forum_cache[$forum['fid']])) {
                    $bits .= build_forum_jump($forum['fid'], $selitem, 0, $depth . '--', $showextras, $showall, $permissions, $name);
                }
            }
        }
    }

    if (!$addselect) {
        return $bits;
    }

    if ($showextras === 0) {
        return "<select name=\"{$name}\" class=\"form-select form-select-sm border pe-5 w-auto\">{$bits}</select>";
    }

    $forum_link = str_contains(FORUM_URL, '.html')
        ? "'" . str_replace('{fid}', "'+option+'", FORUM_URL) . "'"
        : "'" . str_replace('{fid}', "'+option", FORUM_URL);

    return <<<HTML
    <form action="forumdisplay.php" method="get">
        <select name="{$name}" class="form-select form-select-sm border pe-5 w-auto">
            <option value="-4">Private Messages</option>
            <option value="-3">User Control Panel</option>
            <option value="-5">Whos Online</option>
            <option value="-2">Search</option>
            <option value="-1">Forum Home</option>
            {$bits}
        </select>
        <button type="submit" class="btn btn-sm btn-primary rounded">
            <i class="fa-solid fa-shuffle"></i> &nbsp;Go
        </button>
    </form>
    <script>
    document.querySelector('select[name="{$name}"]').addEventListener('change', function() {
        const option = this.value;
        window.location = option < 0
            ? 'forumdisplay.php?fid=' + option
            : {$forum_link};
    });
    </script>
    HTML;
}