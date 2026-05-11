<?php
declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// make_searchable_forums
// ─────────────────────────────────────────────────────────────────────────────
function make_searchable_forums(int|string $pid = 0, int|string $selitem = 0, int $addselect = 1, string $depth = ''): string
{
    
    global $db, $pforumcache, $permissioncache, $mybb, $selecteddone,
           $forumlist, $forumlistbits, $theme, $templates, $lang, $forumpass;

$pid     = (int)$pid;
    $selitem = (int)$selitem;


    if (!is_array($pforumcache)) {
        $q = $db->simple_select('forums', 'pid,disporder,fid,password,name',
            "linkto='' AND active!=0", ['order_by' => 'pid, disporder']);
        while ($f = $db->fetch_array($q)) {
            $pforumcache[$f['pid']][$f['disporder']][$f['fid']] = $f;
        }
    }

    if (!is_array($permissioncache)) {
        $permissioncache = forum_permissions();
    }

    if (is_array($pforumcache[$pid])) {
        foreach ($pforumcache[$pid] as $main) {
            foreach ($main as $forum) {
                $hideprivateforums = '1';
                $perms = $permissioncache[$forum['fid']];

                if (($perms['canview'] == 1 || $hideprivateforums == 0) && $perms['cansearch'] != 0) {
                    if ($selitem == $forum['fid']) {
                        $optionselected = 'selected';
                        $selecteddone   = '1';
                    } else {
                        $optionselected = '';
                        $selecteddone   = '0';
                    }
                    
					$forumlistbits .= '
					
					<option value="'.$forum['fid'].'">'.$depth.' '.$forum['name'].'</option>
					
					';
					
					

                    if (!empty($pforumcache[$forum['fid']])) {
                        $newdepth      = $depth . '&nbsp;&nbsp;&nbsp;&nbsp;';
                        $forumlistbits .= make_searchable_forums($forum['fid'], $selitem, 0, $newdepth);
                    }
                }
            }
        }
    }

    if ($addselect) {
        
		$forumlist = '
<select name="forums[]" id="select" class="form-select form-select-sm border mb-4" multiple="multiple">
    <option value="all" selected="selected">' . $lang->search['search_all_forums'] . '</option>
    <option value="all">----------------------</option>
    ' . $forumlistbits . '
</select>

<script>
    var select = document.getElementById(\'select\');
    select.size = select.length;
</script>';
		
		
		
		
		
		
		
		
    }

    return $forumlist ?? '';
}

// ─────────────────────────────────────────────────────────────────────────────
// get_unsearchable_forums
// ─────────────────────────────────────────────────────────────────────────────
function get_unsearchable_forums(int $pid = 0, int $first = 1): string
{
    global $forum_cache, $permissioncache, $mybb, $unsearchableforums, $unsearchable, $forumpass;

    if (!is_array($forum_cache))    cache_forums();
    if (!is_array($permissioncache)) $permissioncache = forum_permissions();

    foreach ($forum_cache as $fid => $forum) {
        $perms = $permissioncache[$forum['fid']] ?? $mybb->usergroup;

        $parents = explode(',', $forum['parentlist']);
        foreach ($parents as $parent) {
            if (($forum_cache[$parent]['active'] ?? 1) == 0) {
                $forum['active'] = 0;
            }
        }

        if ($perms['canview'] != 1 || $perms['cansearch'] != 1
            || !forum_password_validated($forum, true) || $forum['active'] == 0)
        {
            if ($unsearchableforums) $unsearchableforums .= ',';
            $unsearchableforums .= "'{$forum['fid']}'";
        }
    }

    $unsearchable = $unsearchableforums ?? '';

    $pass_protected = get_password_protected_forums();
    if ($unsearchable && $pass_protected) $unsearchable .= ',';
    if ($pass_protected) $unsearchable .= implode(',', $pass_protected);

    return $unsearchable;
}

// ─────────────────────────────────────────────────────────────────────────────
// get_visible_where
// ─────────────────────────────────────────────────────────────────────────────
function get_visible_where(?string $table_alias = null): string
{
    global $CURUSER, $usergroups, $is_mod;

    $dot    = !empty($table_alias) ? $table_alias . '.' : '';
    $is_mod = is_mod($usergroups);

    // Супермодератор / админ
    if ($is_mod) {
        return "{$dot}visible >= -1";
    }

    // Обычный пользователь
    $showownunapproved = '1';
    if ($CURUSER['id'] > 0 && $showownunapproved == 1) {
        return "({$dot}visible = 1 OR ({$dot}visible = 0 AND {$dot}uid = {$CURUSER['id']}))";
    }

    return "{$dot}visible = 1";
}

// ─────────────────────────────────────────────────────────────────────────────
// get_password_protected_forums
// ─────────────────────────────────────────────────────────────────────────────
function get_password_protected_forums(array $fids = []): array|false
{
    global $forum_cache;

    if (!is_array($forum_cache)) {
        $forum_cache = cache_forums();
        if (!$forum_cache) return false;
    }

    if (empty($fids)) $fids = array_keys($forum_cache);

    $pass_fids = [];
    foreach ($fids as $fid) {
        if (!forum_password_validated($forum_cache[$fid], true)) {
            $pass_fids[] = $fid;
            $pass_fids   = array_merge($pass_fids, get_child_list($fid));
        }
    }

    return array_unique($pass_fids);
}

// ─────────────────────────────────────────────────────────────────────────────
// clean_keywords
// ─────────────────────────────────────────────────────────────────────────────
function clean_keywords(string $keywords): string
{
    global $db, $lang;

    $keywords = my_strtolower($keywords);
    $keywords = $db->escape_string_like($keywords);
    $keywords = preg_replace('#\*{2,}#s', '*', $keywords);
    $keywords = str_replace('*', '%', $keywords);
    $keywords = preg_replace('#\s+#s', ' ', $keywords);
    $keywords = str_replace('\\"', '"', $keywords);
    $keywords = trim($keywords);

    if (str_starts_with($keywords, 'or'))  { $keywords = ' ' . substr($keywords, 2); }
    if (str_starts_with($keywords, 'and')) { $keywords = ' ' . substr($keywords, 3); }

    $keywords = trim($keywords);

    if (!$keywords) {
        stderr($lang->search['error_nosearchterms']);
    }

    return $keywords;
}

// ─────────────────────────────────────────────────────────────────────────────
// clean_keywords_ft
// ─────────────────────────────────────────────────────────────────────────────
function clean_keywords_ft(string $keywords): string|false
{
    if (!$keywords) return false;

    $keywords = my_strtolower($keywords);
    $keywords = str_replace('%', '\\%', $keywords);
    $keywords = preg_replace('#\*{2,}#s', '*', $keywords);
    $keywords = preg_replace('#([\[\]\|\.\,:])#s', ' ', $keywords);
    $keywords = preg_replace('#((\+|-|<|>|~)?\(|\))#s', ' $1 ', $keywords);
    $keywords = preg_replace('#\s+#s', ' ', $keywords);

    $min_word_length = 3;
    $word_length_regex = $min_word_length > 2 ? '{1,' . ($min_word_length - 1) . '}' : '';

    $keywords = preg_replace("/(\b.{$word_length_regex})(\s)|(\b.{$word_length_regex}$)/u", '$2', $keywords);
    $keywords = preg_replace('/(\s)+/', '$1', $keywords);
    $keywords = trim($keywords);

    $words          = [[]];
    $keyword_parts  = explode('"', $keywords);
    $boolean        = ['+'];
    $depth          = 0;
    $phrase_operator = '+';
    $inquote        = false;

    foreach ($keyword_parts as $phrase) {
        $phrase = trim($phrase);
        if ($phrase !== '') {
            if ($inquote) {
                if ($phrase_operator) $boolean[$depth] = $phrase_operator;
                $words[$depth][] = "{$boolean[$depth]}\"{$phrase}\"";
                $boolean[$depth] = $phrase_operator = '+';
            } else {
                $split_words = preg_split('#\s{1,}#', $phrase, -1) ?: [];
                if (!$inquote) {
                    $last_char = substr($phrase, -1);
                    if (in_array($last_char, ['+','-','<','>','~'], true)) {
                        $phrase_operator = $last_char;
                    }
                }
                foreach ($split_words as $word) {
                    $word = trim($word);
                    match ($word) {
                        'or' => (function() use (&$boolean, &$words, $depth) {
                            $boolean[$depth] = '';
                            $last = array_pop($words[$depth]);
                            if ($last) {
                                if (str_starts_with($last, '+')) $last = substr($last, 1);
                                $words[$depth][] = $last;
                            }
                        })(),
                        'and'  => ($boolean[$depth] = '+') && false,
                        'not'  => ($boolean[$depth] = '-') && false,
                        ')'    => (function() use (&$words, &$depth, &$boolean) {
                            if ($depth > 0) {
                                $words[$depth - 1][] = $boolean[$depth - 1] . '(' . implode(' ', $words[$depth]) . ')';
                                --$depth;
                            }
                        })(),
                        default => (function() use ($word, &$boolean, &$words, &$depth) {
                            if (in_array($word, ['+(','-(','<(','>(',  '~(','('], true)) {
                                if (strlen($word) == 2) $boolean[$depth] = substr($word, 0, 1);
                                $words[++$depth] = [];
                                $boolean[$depth] = '+';
                                return;
                            }
                            $op = substr($word, 0, 1);
                            if (in_array($op, ['-','+','>','<','~'], true)) {
                                $word = substr($word, 1);
                            } else {
                                $op = $boolean[$depth];
                            }
                            $word = preg_replace('#(-|\+|<|>|~|@)#s', '', $word);
                            $word = preg_replace('#^\*#s', '', $word);
                            $word = $op . $word;
                            if (strlen($word) > 1) {
                                $words[$depth][] = $word;
                                $boolean[$depth] = '+';
                            }
                        })(),
                    };
                }
            }
        }
        $inquote = !$inquote;
    }

    while ($depth > 0) {
        $words[$depth - 1][] = $boolean[$depth - 1] . '(' . implode(' ', $words[$depth]) . ')';
        --$depth;
    }

    return implode(' ', $words[0]);
}













function perform_search_mysql(array $search): array
{
    global $db, $lang, $CURUSER;

    $keywords = '';
    if (!empty(trim($search['keywords'] ?? ''))) {
        $keywords = clean_keywords($search['keywords']);
    }

    if (!$keywords && empty(trim($search['author'] ?? ''))) {
        stderr($lang->search['error_nosearchterms']);
    }

    $minsearchword  = 3;
    $has_author     = !empty(trim($search['author'] ?? ''));
    $subject_lookin = $message_lookin = '';

    if ($keywords) {
        $tfield = match ($db->type) {
            'mysql','mysqli' => 't.subject',
            default          => 'LOWER(t.subject)',
        };
        $pfield = match ($db->type) {
            'mysql','mysqli' => 'p.message',
            default          => 'LOWER(p.message)',
        };

        $keywords_padded = " {$keywords} ";

        if (preg_match('#\s(and|or)\s#', $keywords_padded)) {
            $subject_lookin = ' AND (';
            $message_lookin = ' AND (';
            $keywords_exp   = explode('"', $keywords_padded);
            $inquote        = false;
            $boolean        = '';

            foreach ($keywords_exp as $phrase) {
                if (!$inquote) {
                    $matches       = preg_split('#\s{1,}(and|or)\s{1,}#', $phrase, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
                    $count_matches = count($matches);
                    for ($i = 0; $i < $count_matches; ++$i) {
                        $word = trim($matches[$i]);
                        if (empty($word)) continue;
                        if ($i % 2 && ($word === 'and' || $word === 'or')) {
                            if ($i <= 1 && $subject_lookin === ' AND (') continue;
                            $boolean = $word;
                        } else {
                            $word = trim($word);
                            if (my_strlen($word) < $minsearchword) {
                                stderr(sprintf($lang->error_minsearchlength ?? 'Minimum search word length is %d', $minsearchword));
                            }
                            $subject_lookin .= " {$boolean} {$tfield} LIKE '%{$word}%'";
                            if ($search['postthread'] == 1 || $has_author) {
                                $message_lookin .= " {$boolean} {$pfield} LIKE '%{$word}%'";
                            }
                            $boolean = 'AND';
                        }
                    }
                } else {
                    $phrase = str_replace(['+','-','*'], '', trim($phrase));
                    if (my_strlen($phrase) < $minsearchword) {
                        stderr(sprintf($lang->error_minsearchlength ?? 'Minimum search word length is %d', $minsearchword));
                    }
                    $subject_lookin .= " {$boolean} {$tfield} LIKE '%{$phrase}%'";
                    if ($search['postthread'] == 1 || $has_author) {
                        $message_lookin .= " {$boolean} {$pfield} LIKE '%{$phrase}%'";
                    }
                    $boolean = 'AND';
                }
                if ($subject_lookin === ' AND (') {
                    stderr(sprintf($lang->error_minsearchlength ?? 'Minimum search word length is %d', $minsearchword));
                }
                $inquote = !$inquote;
            }
            $subject_lookin .= ')';
            $message_lookin .= ')';
        } else {
            $keywords = str_replace('"', '', trim($keywords));
            if (my_strlen($keywords) < $minsearchword) {
                stderr(sprintf($lang->error_minsearchlength ?? 'Minimum search word length is %d', $minsearchword));
            }
            $subject_lookin = " AND {$tfield} LIKE '%{$keywords}%'";
            if ($search['postthread'] == 1 || $has_author) {
                $message_lookin = " AND {$pfield} LIKE '%{$keywords}%'";
            }
        }
    }

    // Author
    $post_usersql = $thread_usersql = '';
    if ($has_author) {
        $userids = [];
        $author  = my_strtolower($search['author']);
        if (!empty($search['matchusername'])) {
            $user = get_user_by_username($author);
            if ($user) $userids[] = $user['id'];
        } else {
            $field = match ($db->type) {
                'mysql','mysqli' => 'username',
                default          => 'LOWER(username)',
            };
            $q = $db->simple_select('users', 'id', "{$field} LIKE '%" . $db->escape_string_like($author) . "%'");
            while ($u = $db->fetch_array($q)) $userids[] = $u['id'];
        }
        if (empty($userids)) stderr($lang->search['error_nosearchresults']);
        $uid_list       = implode(',', $userids);
        $post_usersql   = " AND p.uid IN ({$uid_list})";
        $thread_usersql = " AND t.uid IN ({$uid_list})";
    }

    // Date
    $post_datecut = $thread_datecut = '';
    if (!empty($search['postdate'])) {
        $op        = ($search['pddir'] ?? 1) == 0 ? '<=' : '>=';
        $datelimit = TIMENOW - 86400 * (int)$search['postdate'];
        $post_datecut   = " AND p.dateline {$op} '{$datelimit}'";
        $thread_datecut = " AND t.dateline {$op} '{$datelimit}'";
    }

    // Replies
    $thread_replycut = '';
    if (isset($search['numreplies']) && $search['numreplies'] !== '' && !empty($search['findthreadst'])) {
        $op = (int)$search['findthreadst'] === 1 ? '>=' : '<=';
        $thread_replycut = " AND t.replies {$op} '" . (int)$search['numreplies'] . "'";
    }

    // Prefix
    $thread_prefixcut = '';
    $prefixlist = [];
    if (!empty($search['threadprefix']) && $search['threadprefix'][0] !== 'any') {
        foreach ($search['threadprefix'] as $tp) $prefixlist[] = (int)$tp;
    }
    if (count($prefixlist) === 1) {
        $thread_prefixcut = " AND t.prefix='{$prefixlist[0]}'";
    } elseif (count($prefixlist) > 1) {
        $thread_prefixcut = ' AND t.prefix IN (' . implode(',', $prefixlist) . ')';
    }

    // Forums
    $forumin = '';
    if (!empty($search['forums']) && (!is_array($search['forums']) || $search['forums'][0] !== 'all')) {
        if (!is_array($search['forums'])) $search['forums'] = [(int)$search['forums']];
        $fidlist = [];
        foreach ($search['forums'] as $fid) {
            $fid = (int)$fid;
            if ($fid > 0) {
                $fidlist[] = $fid;
                $children  = get_child_list($fid);
                if (is_array($children)) $fidlist = array_merge($fidlist, $children);
            }
        }
        $fidlist = array_unique($fidlist);
        if ($fidlist) $forumin = ' AND t.fid IN (' . implode(',', $fidlist) . ')';
    }

    // Permissions
    $permsql    = '';
    $onlyusfids = [];
    $gp         = forum_permissions();
    foreach ($gp as $fid => $fp) {
        if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
    }
    if ($onlyusfids) {
        $permsql .= 'AND ((t.fid IN(' . implode(',', $onlyusfids) . ") AND t.uid='{$CURUSER['id']}') OR t.fid NOT IN(" . implode(',', $onlyusfids) . '))';
    }
    $uf = get_unsearchable_forums(); if ($uf) $permsql .= " AND t.fid NOT IN ({$uf})";

    // Visibility
    $visiblesql = $post_visiblesql = $plain_post_visiblesql = '';
    if (isset($search['visible'])) {
        $vis = (int)$search['visible'];
        $visiblesql = match ($vis) {
            1  => " AND t.visible = '1'",
            -1 => " AND t.visible = '-1'",
            default => " AND t.visible = '0'",
        };
        if ($search['postthread'] == 1 || $has_author) {
            $post_visiblesql = match ($vis) {
                1  => " AND p.visible = '1'",
                -1 => " AND p.visible = '-1'",
                default => " AND p.visible = '0'",
            };
            $plain_post_visiblesql = match ($vis) {
                1  => " AND visible = '1'",
                -1 => " AND visible = '-1'",
                default => " AND visible = '0'",
            };
        }
    }

    $unapproved_where_t = get_visible_where('t');
    $unapproved_where_p = get_visible_where('p');

    $tidsql = '';
    if (!empty($search['tid'])) $tidsql = " AND t.tid='" . (int)$search['tid'] . "'";

    $limitsql = '';
    $threads = $posts = $firstposts = [];

    // Ищем посты если: postthread=1 (subject+message) ИЛИ есть автор
    if ($search['postthread'] == 1 || $has_author) {

        if (empty($search['tid'])) {
            $q = $db->sql_query("
                SELECT t.tid, t.firstpost
                FROM threads t
                WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut}
                      {$forumin} {$thread_usersql} {$permsql} {$visiblesql}
                      AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%'
                      {$subject_lookin} {$limitsql}
            ");
            while ($t = $db->fetch_array($q)) {
                $threads[$t['tid']] = $t['tid'];
                if ($t['firstpost']) $posts[$t['tid']] = $t['firstpost'];
            }
        }

        $q = $db->sql_query("
            SELECT p.pid, p.tid
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE 1=1 {$post_datecut} {$thread_replycut} {$thread_prefixcut}
                  {$forumin} {$post_usersql} {$permsql} {$tidsql}
                  {$visiblesql} {$post_visiblesql}
                  AND ({$unapproved_where_t}) AND ({$unapproved_where_p})
                  AND t.closed NOT LIKE 'moved|%' {$message_lookin} {$limitsql}
        ");
        while ($p = $db->fetch_array($q)) {
            $posts[$p['pid']]   = $p['pid'];
            $threads[$p['tid']] = $p['tid'];
        }

        if (empty($posts) && empty($threads)) {
            stderr($lang->search['error_nosearchresults']);
        }

        $threads = implode(',', $threads);
        $posts   = implode(',', $posts);

    } else {
        // Только subject
        $q = $db->sql_query("
            SELECT t.tid, t.firstpost
            FROM threads t
            WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut}
                  {$forumin} {$thread_usersql} {$permsql} {$visiblesql}
                  {$subject_lookin} {$limitsql}
        ");
        while ($t = $db->fetch_array($q)) {
            $threads[$t['tid']] = $t['tid'];
            if ($t['firstpost']) $firstposts[$t['tid']] = $t['firstpost'];
        }

        if (empty($threads)) stderr($lang->search['error_nosearchresults']);

        $threads    = implode(',', $threads);
        $firstposts = implode(',', $firstposts);

        if ($firstposts) {
            $q = $db->simple_select('posts', 'pid', "pid IN ({$firstposts}) {$plain_post_visiblesql} {$limitsql}");
            while ($p = $db->fetch_array($q)) $posts[$p['pid']] = $p['pid'];
            $posts = implode(',', $posts);
        }
    }

    return ['threads' => $threads, 'posts' => $posts, 'querycache' => ''];
}





// ─────────────────────────────────────────────────────────────────────────────
// perform_search_mysql_ft
// ─────────────────────────────────────────────────────────────────────────────
function perform_search_mysql_ft(array $search): array
{
    global $db, $lang, $CURUSER;

    $keywords = clean_keywords_ft($search['keywords'] ?? '');

    if (!$keywords && empty(trim($search['author'] ?? ''))) {
        stderr($lang->search['error_nosearchterms']);
    }

    $minsearchword = 4;
    $message_lookin = $subject_lookin = '';

    if ($keywords) {
        $all_too_short = false;
        $keywords_exp  = explode('"', $keywords);
        $inquote       = false;

        foreach ($keywords_exp as $phrase) {
            if (!$inquote) {
                $split_words = preg_split('#\s{1,}#', $phrase, -1) ?: [];
                foreach ($split_words as $word) {
                    $word = str_replace(['+','-','*'], '', $word);
                    if (!$word) continue;
                    $all_too_short = my_strlen($word) < $minsearchword;
                    if (!$all_too_short) break 2;
                }
            } else {
                $phrase = str_replace(['+','-','*'], '', $phrase);
                $all_too_short = my_strlen($phrase) < $minsearchword;
                if (!$all_too_short) break;
            }
            $inquote = !$inquote;
        }

        if ($all_too_short) {
            stderr(sprintf($lang->error_minsearchlength ?? 'Minimum search word length is %d', $minsearchword));
        }

        $kw_esc         = $db->escape_string($keywords);
        $message_lookin = "AND MATCH(message) AGAINST('{$kw_esc}' IN BOOLEAN MODE)";
        $subject_lookin = "AND MATCH(subject) AGAINST('{$kw_esc}' IN BOOLEAN MODE)";
    }

    // Author
    $post_usersql = $thread_usersql = '';
    if (!empty(trim($search['author'] ?? ''))) {
        $userids = [];
        $author  = my_strtolower($search['author']);

        if (!empty($search['matchusername'])) {
            $user = get_user_by_username($author);
            if ($user) $userids[] = $user['id'];
        } else {
            $q = $db->simple_select('users', 'id', "username LIKE '%" . $db->escape_string_like($author) . "%'");
            while ($u = $db->fetch_array($q)) $userids[] = $u['id'];
        }

        if (empty($userids)) stderr($lang->search['error_nosearchresults']);

        $uid_list       = implode(',', $userids);
        $post_usersql   = " AND p.uid IN ({$uid_list})";
        $thread_usersql = " AND t.uid IN ({$uid_list})";
    }

    // Date
    $post_datecut = $thread_datecut = '';
    if (!empty($search['postdate'])) {
        $op        = ($search['pddir'] ?? 1) == 0 ? '<=' : '>=';
        $datelimit = TIMENOW - 86400 * (int)$search['postdate'];
        $post_datecut   = " AND p.dateline {$op} '{$datelimit}'";
        $thread_datecut = " AND t.dateline {$op} '{$datelimit}'";
    }

    // Replies
    $thread_replycut = '';
    if (!empty($search['numreplies']) && !empty($search['findthreadst'])) {
        $op = (int)$search['findthreadst'] === 1 ? '>=' : '<=';
        $thread_replycut = " AND t.replies {$op} '" . (int)$search['numreplies'] . "'";
    }

    // Prefix
    $thread_prefixcut = '';
    $prefixlist = [];
    if (!empty($search['threadprefix']) && $search['threadprefix'][0] !== 'any') {
        foreach ($search['threadprefix'] as $tp) $prefixlist[] = (int)$tp;
    }
    if (count($prefixlist) === 1) {
        $thread_prefixcut = " AND t.prefix='{$prefixlist[0]}'";
    } elseif (count($prefixlist) > 1) {
        $thread_prefixcut = ' AND t.prefix IN (' . implode(',', $prefixlist) . ')';
    }

    // Forums
    $forumin = '';
    if (!empty($search['forums']) && (!is_array($search['forums']) || $search['forums'][0] !== 'all')) {
        if (!is_array($search['forums'])) $search['forums'] = [(int)$search['forums']];
        $fidlist = [];
        foreach ($search['forums'] as $fid) {
            $fid = (int)$fid;
            if ($fid > 0) {
                $fidlist[] = $fid;
                $children  = get_child_list($fid);
                if (is_array($children)) $fidlist = array_merge($fidlist, $children);
            }
        }
        $fidlist = array_unique($fidlist);
        if ($fidlist) $forumin = ' AND t.fid IN (' . implode(',', $fidlist) . ')';
    }

    // Permissions
    $permsql    = '';
    $onlyusfids = [];
    $gp         = forum_permissions();
    foreach ($gp as $fid => $fp) {
        if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
    }
    if ($onlyusfids) {
        $permsql .= 'AND ((t.fid IN(' . implode(',', $onlyusfids) . ") AND t.uid='{$CURUSER['id']}') OR t.fid NOT IN(" . implode(',', $onlyusfids) . '))';
    }
    $uf = get_unsearchable_forums(); if ($uf) $permsql .= " AND t.fid NOT IN ({$uf})";
    $ia = get_inactive_forums();     if ($ia) $permsql .= " AND t.fid NOT IN ({$ia})";

    // Visibility
    $visiblesql = $post_visiblesql = $plain_post_visiblesql = '';
    if (isset($search['visible'])) {
        $vis = (int)$search['visible'];
        $visiblesql = match ($vis) {
            1  => " AND t.visible = '1'",
            -1 => " AND t.visible = '-1'",
            default => " AND t.visible != '1'",
        };
        if ($search['postthread'] == 1) {
            $post_visiblesql = match ($vis) {
                1  => " AND p.visible = '1'",
                -1 => " AND p.visible = '-1'",
                default => " AND p.visible != '1'",
            };
            $plain_post_visiblesql = match ($vis) {
                1  => " AND visible = '1'",
                -1 => " AND visible = '-1'",
                default => " AND visible != '1'",
            };
        }
    }

    $unapproved_where_t = get_visible_where('t');
    $unapproved_where_p = get_visible_where('p');

    $tidsql = '';
    if (!empty($search['tid'])) $tidsql = " AND t.tid='" . (int)$search['tid'] . "'";

    $limitsql = '';

    $threads = $posts = $firstposts = [];

    if ($search['postthread'] == 1) {
        if (empty($search['tid'])) {
            $q = $db->sql_query("
                SELECT t.tid, t.firstpost
                FROM threads t
                WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut}
                      {$forumin} {$thread_usersql} {$permsql} {$visiblesql}
                      AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%'
                      {$subject_lookin} {$limitsql}
            ");
            while ($t = $db->fetch_array($q)) {
                $threads[$t['tid']] = $t['tid'];
                if ($t['firstpost']) $posts[$t['tid']] = $t['firstpost'];
            }
        }

        $q = $db->sql_query("
            SELECT p.pid, p.tid
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            WHERE 1=1 {$post_datecut} {$thread_replycut} {$thread_prefixcut}
                  {$forumin} {$post_usersql} {$permsql} {$tidsql}
                  {$post_visiblesql} {$visiblesql}
                  AND ({$unapproved_where_t}) AND {$unapproved_where_p}
                  AND t.closed NOT LIKE 'moved|%' {$message_lookin} {$limitsql}
        ");
        while ($p = $db->fetch_array($q)) {
            $posts[$p['pid']]   = $p['pid'];
            $threads[$p['tid']] = $p['tid'];
        }

        if (empty($posts) && empty($threads)) stderr($lang->search['error_nosearchresults']);

        $threads = implode(',', $threads);
        $posts   = implode(',', $posts);

    } else {
        $q = $db->sql_query("
            SELECT t.tid, t.firstpost
            FROM threads t
            WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut}
                  {$forumin} {$thread_usersql} {$permsql} {$visiblesql}
                  {$subject_lookin} {$limitsql}
        ");
        while ($t = $db->fetch_array($q)) {
            $threads[$t['tid']] = $t['tid'];
            if ($t['firstpost']) $firstposts[$t['tid']] = $t['firstpost'];
			
        }

        if (empty($threads)) stderr($lang->search['error_nosearchresults']);

        $threads    = implode(',', $threads);
        $firstposts = implode(',', $firstposts);

        if ($firstposts) {
            $q = $db->simple_select('posts', 'pid', "pid IN ({$firstposts}) {$plain_post_visiblesql} {$limitsql}");
            while ($p = $db->fetch_array($q)) $posts[$p['pid']] = $p['pid'];
            $posts = implode(',', $posts);
        }
    }

    return ['threads' => $threads, 'posts' => $posts, 'querycache' => ''];
}

// ─────────────────────────────────────────────────────────────────────────────
// privatemessage_perform_search_mysql  (без изменений логики, только стиль)
// ─────────────────────────────────────────────────────────────────────────────
function privatemessage_perform_search_mysql(array $search): array
{
    global $db, $lang, $CURUSER;

    $keywords = '';
    if (!empty(trim($search['keywords'] ?? ''))) {
        $keywords = clean_keywords($search['keywords']);
    }

    if (!$keywords && empty($search['sender'])) {
        stderr($lang->search['error_nosearchterms']);
    }

    $minsearchword = 3;
    $subject_lookin = $message_lookin = '';
    $searchsql = "uid='{$CURUSER['id']}'";

    if ($keywords) {
        $keywords_padded = " {$keywords} ";
        $sfield = match ($db->type) { 'mysql','mysqli' => 'subject', default => 'LOWER(subject)' };
        $mfield = match ($db->type) { 'mysql','mysqli' => 'message', default => 'LOWER(message)' };

        if (preg_match('#\s(and|or)\s#', $keywords_padded)) {
            $string = 'AND';
            if ($search['subject'] == 1) { $string = 'OR'; $subject_lookin = ' AND ('; }
            if ($search['message'] == 1) { $message_lookin = " {$string} ("; }

            $keywords_exp = explode('"', $keywords_padded);
            $inquote = false; $boolean = '';

            foreach ($keywords_exp as $phrase) {
                if (!$inquote) {
                    $matches = preg_split('#\s{1,}(and|or)\s{1,}#', $phrase, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
                    $n = count($matches);
                    for ($i = 0; $i < $n; ++$i) {
                        $word = trim($matches[$i]);
                        if (empty($word)) continue;
                        if ($i % 2 && ($word === 'and' || $word === 'or')) {
                            $boolean = $word;
                        } else {
                            if (my_strlen($word) < $minsearchword) stderr('error_minsearchlength');
                            if ($search['subject'] == 1) $subject_lookin .= " {$boolean} {$sfield} LIKE '%{$word}%'";
                            if ($search['message'] == 1) $message_lookin .= " {$boolean} {$mfield} LIKE '%{$word}%'";
                            $boolean = 'AND';
                        }
                    }
                } else {
                    $phrase = str_replace(['+','-','*'], '', trim($phrase));
                    if (my_strlen($phrase) < $minsearchword) stderr('error_minsearchlength');
                    if ($search['subject'] == 1) $subject_lookin .= " {$boolean} {$sfield} LIKE '%{$phrase}%'";
                    if ($search['message'] == 1) $message_lookin .= " {$boolean} {$mfield} LIKE '%{$phrase}%'";
                    $boolean = 'AND';
                }
                $inquote = !$inquote;
            }
            if ($search['subject'] == 1) $subject_lookin .= ')';
            if ($search['message'] == 1) $message_lookin .= ')';
            $searchsql .= "{$subject_lookin} {$message_lookin}";
        } else {
            $keywords = str_replace('"', '', trim($keywords));
            if (my_strlen($keywords) < $minsearchword) stderr('error_minsearchlength');
            if ($search['subject'] == 1 && $search['message'] == 1) {
                $searchsql .= " AND ({$sfield} LIKE '%{$keywords}%' OR {$mfield} LIKE '%{$keywords}%')";
            } elseif ($search['subject'] == 1) {
                $searchsql .= " AND {$sfield} LIKE '%{$keywords}%'";
            } elseif ($search['message'] == 1) {
                $searchsql .= " AND {$mfield} LIKE '%{$keywords}%'";
            }
        }
    }

    if (!empty($search['sender'])) {
        $userids = [];
        $sender  = my_strtolower($search['sender']);
        $field   = match ($db->type) { 'mysql','mysqli' => 'username', default => 'LOWER(username)' };
        $q = $db->simple_select('users', 'id', "{$field} LIKE '%" . $db->escape_string_like($sender) . "%'");
        while ($u = $db->fetch_array($q)) $userids[] = $u['id'];
        if (empty($userids)) stderr($lang->search['error_nosearchresults']);
        $searchsql .= ' AND fromid IN (' . implode(',', $userids) . ')';
    }

    if (!is_array($search['folder'])) $search['folder'] = [$search['folder']];
    if (!empty($search['folder'])) {
        $search['folder'] = array_map('intval', $search['folder']);
        $folderids = implode(',', $search['folder']);
        if ($folderids) $searchsql .= " AND folder IN ({$folderids})";
    }

    if (!empty($search['status'])) {
        $statussql = [];
        if ($search['status']['new'])       $statussql[] = " status='0' ";
        if ($search['status']['replied'])   $statussql[] = " status='3' ";
        if ($search['status']['forwarded']) $statussql[] = " status='4' ";
        if ($search['status']['read'])      $statussql[] = " (status != '0' AND readtime > '0') ";
        if (in_array(2, $search['folder'])) $statussql[] = " status='1' ";
        if ($statussql) $searchsql .= ' AND (' . implode('OR', $statussql) . ')';
    }

    $pms = [];
    $q   = $db->simple_select('privatemessages', 'pmid', $searchsql);
    while ($pm = $db->fetch_array($q)) $pms[$pm['pmid']] = $pm['pmid'];

    if (empty($pms)) stderr($lang->search['error_nosearchresults']);

    return ['querycache' => implode(',', $pms)];
}