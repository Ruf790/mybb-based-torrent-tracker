<?php
declare(strict_types=1);

define('IN_MYBB',                  1);
define('IGNORE_CLEAN_VARS',        'sid');
define('THIS_SCRIPT',              'search.php');
define('SCRIPTNAME',               'search.php');
define('TSF_FORUMS_TSSEv56',       true);
define('TSF_FORUMS_GLOBAL_TSSEv56',true);



define('IN_FORUM', true);
require_once 'global.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_search.php';


require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;
  
  
$parser_options = array(
	"allow_html" => 0,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
);


$lang->load('search');


function highlight_search_term($text, $keyword)
{
    if(empty($keyword))
    {
        return htmlspecialchars_uni($text);
    }

    $text = htmlspecialchars_uni($text);

    return preg_replace(
        '#(' . preg_quote($keyword, '#') . ')#iu',
        '<mark>$1</mark>',
        $text
    );
}

// Converts a "YYYY-MM-DD" string into a unix timestamp.
// $end=true anchors to 23:59:59 of that day (range "to"), otherwise 00:00:00 (range "from").
function to_search_ts(?string $d, bool $end = false): int
{
    if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return 0;
    }
    return (int)strtotime($d . ($end ? ' 23:59:59' : ' 00:00:00'));
}


// ─────────────────────────────────────────────────────────────────────────────
// CSS / HTML HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function search_css(): string
{
    global $BASEURL;
    return '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/search.css">';
}












function sr_avatar(string $avatar, string $dims, string $username): string
{
    $ua = format_avatar($avatar, $dims, '50|50');
    if (str_starts_with($ua['image'], '<')) return $ua['image'];
    if (!empty($ua['image']) && $ua['image'] !== 'default') {
        return '<img src="' . htmlspecialchars($ua['image']) . '" class="rounded-circle" style="width:50px;height:50px;object-fit:cover;" alt="">';
    }
    $letter = mb_strtoupper(mb_substr($username, 0, 1));
    return '<div class="sr-avatar-placeholder rounded-circle" style="width:50px;height:50px;">' . $letter . '</div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// PREFIX SELECT
// ─────────────────────────────────────────────────────────────────────────────
function build_prefix_select(mixed $fid, mixed $selected_pid = 0, int $multiple = 0, int $previous_pid = 0): string
{
    global $cache, $db, $lang, $mybb, $templates;

    if ($fid !== 'all') $fid = (int)$fid;
    if (empty($prefix_cache)) return '';

    $prefixes = [];
    foreach ($prefix_cache as $prefix) {
        if ($fid !== 'all' && $prefix['forums'] !== '-1') {
            $forums = explode(',', $prefix['forums']);
            if (!in_array($fid, $forums) && $prefix['pid'] != $previous_pid) continue;
        }
        if (is_member($prefix['groups']) || $prefix['pid'] == $previous_pid) {
            $prefixes[$prefix['pid']] = $prefix;
        }
    }
    if (empty($prefixes)) return '';

    $prefixselect = $prefixselect_prefix = '';
    $any_selected     = ($multiple == 1 && $selected_pid === 'any') ? ' selected="selected"' : '';
    $default_selected = ((int)$selected_pid === 0 && $selected_pid !== 'any') ? ' selected="selected"' : '';

    foreach ($prefixes as $prefix) {
        $selected = ($prefix['pid'] == $selected_pid) ? ' selected="selected"' : '';
        $prefix['prefix'] = htmlspecialchars_uni($prefix['prefix']);
        $prefixselect_prefix .= '<option value="' . (int)$prefix['pid'] . '"' . $selected . '>' . $prefix['prefix'] . '</option>';
    }

    if ($multiple !== 0) {
        
		$prefixselect = '<div class="col-auto align-self-center m-0 p-0 me-2">
	<select class="form-select form-select border mb-3 pe-5" name="threadprefix[]" multiple="multiple" size="5">
<option value="any"'.$any_selected.'>any_prefix</option>
<option value="0"'.$default_selected.'>no_prefix</option>
'.$prefixselect_prefix.'
</select>
</div>';
		
		
    } else {
        
		$prefixselect = '<div class="col-auto align-self-center m-0 p-0 me-2"><select class="form-select form-select border mb-3 pe-5" name="threadprefix">
<option value="0"'.$default_selected.'>no_prefix</option>
'.$prefixselect_prefix.'
</select>
		</div>';
		
    }
    return $prefixselect;
}

// ─────────────────────────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────────────────────────
$is_mod = is_mod($mybb->usergroup);
add_breadcrumb($lang->search['nav_search'], 'search.php');

$mybb->input['action'] = $mybb->get_input('action');
if ($mybb->input['action'] === 'results') add_breadcrumb($lang->search['nav_results']);
if (empty($usergroups) || $usergroups['cansearch'] == 0) print_no_permission();

$now             = TIMENOW;
$mybb->input['keywords'] = trim($mybb->get_input('keywords'));
$searchhardlimit = 0;
$limitsql        = $searchhardlimit > 0 ? "LIMIT {$searchhardlimit}" : '';

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: suggest — lightweight AJAX autocomplete (live dropdown while typing)
// ─────────────────────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'suggest')
{
    header('Content-Type: application/json; charset=utf-8');

    $kw = trim($mybb->get_input('q'));

    if(my_strlen($kw) < 2)
    {
        echo json_encode(['results' => []]);
        exit;
    }

    $kwEsc = $db->escape_string($kw);
    $results = [];

    /*
    =====================================================
    THREADS
    =====================================================
    */

    $threads = $db->sql_query("
        SELECT
            t.tid,
            t.subject,
            t.replies,
            f.name AS forumname
        FROM threads t
        LEFT JOIN forums f ON(f.fid=t.fid)
        WHERE t.subject LIKE '%{$kwEsc}%'
        ORDER BY t.lastpost DESC
        LIMIT 5
    ");

    while($row = $db->fetch_array($threads))
    {
        $results[] = [
            'type'    => 'thread',
            'icon'    => 'fa-comments',
            'title' => highlight_search_term($row['subject'], $kw),
            'meta'    => htmlspecialchars_uni($row['forumname']).' · '.$row['replies'].' replies',
            'url'     => get_thread_link($row['tid'])
        ];
    }

    /*
    =====================================================
    POSTS
    =====================================================
    */

    $posts = $db->sql_query("
        SELECT
            p.pid,
            p.subject,
            LEFT(p.message,120) AS snippet,
            t.tid
        FROM posts p
        LEFT JOIN threads t ON(t.tid=p.tid)
        WHERE p.message LIKE '%{$kwEsc}%'
        ORDER BY p.dateline DESC
        LIMIT 5
    ");

    while($row = $db->fetch_array($posts))
    {
        $results[] = [
            'type'    => 'post',
            'icon'    => 'fa-comment',
            'title' => highlight_search_term($row['subject'], $kw),
            'meta'  => highlight_search_term($row['snippet'], $kw),
            'url'     => get_post_link($row['pid'], $row['tid'])
        ];
    }

    /*
    =====================================================
    USERS
    =====================================================
    */

    $users = $db->sql_query("
        SELECT id, username
        FROM users
        WHERE username LIKE '%{$kwEsc}%'
        ORDER BY username ASC
        LIMIT 5
    ");

    while($row = $db->fetch_array($users))
    {
        $results[] = [
            'type'    => 'user',
            'icon'    => 'fa-user',
            'title' => highlight_search_term($row['username'], $kw),
            'meta'    => 'Member',
            'url'     => 'userdetails.php?id='.(int)$row['id']
        ];
    }

    /*
    =====================================================
    FORUMS
    =====================================================
    */

    $forums = $db->sql_query("
        SELECT fid,name,description
        FROM forums
        WHERE name LIKE '%{$kwEsc}%'
        ORDER BY disporder ASC
        LIMIT 5
    ");

    while($row = $db->fetch_array($forums))
    {
        $results[] = [
            'type'    => 'forum',
            'icon'    => 'fa-folder',
           'title' => highlight_search_term($row['name'], $kw),
'meta'  => highlight_search_term(
    my_substr($row['description'], 0, 80),
    $kw
),
            'url'     => get_forum_link($row['fid'])
        ];
    }

    echo json_encode([
        'results' => array_slice($results,0,15)
    ]);

    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: results
// ─────────────────────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'results') {

    $sid    = $db->escape_string($mybb->get_input('sid'));
    $query  = $db->simple_select('searchlog', '*', "sid='$sid'");
    $search = $db->fetch_array($query);
	
	
	

	
	
    if (!$search) stderr($lang->search['error_invalidsearch']);

    $plugins->run_hooks('search_results_start');

    $order  = my_strtolower(htmlspecialchars_uni($mybb->get_input('order')));
    $sortby = my_strtolower(htmlspecialchars_uni($mybb->get_input('sortby')));

    $is_threads = isset($search['resulttype']) && $search['resulttype'] === 'threads';
	


    $sortfield = match ($sortby) {
        'replies' => 't.replies',
        'views'   => 't.views',
        'subject' => $is_threads ? 't.subject' : 'p.subject',
        'forum'   => 'f.name',
        'starter' => $is_threads ? 't.username' : 'p.username',
        'rating'  => $is_threads ? 'avg_rating' : 't.lastpost',
        default   => (function() use (&$sortby, $is_threads): string {
            if ($is_threads) { $sortby = 'lastpost'; return 't.lastpost'; }
            $sortby = 'dateline'; return 'p.dateline';
        })(),
    };

    if ($order !== 'asc') { $order = 'desc'; $oppsort = 'asc'; }
    else                  { $oppsort = 'desc'; }

    $perpage = 20;
    $page    = max(1, (int)$mybb->get_input('page'));
    $start   = ($page - 1) * $perpage;
    $upper   = $start + $perpage;

    $highlight = '';
    if (!empty($search['keywords'])) {
        $highlight = $mybb->seo_support
            ? '?highlight=' . urlencode($search['keywords'])
            : '&amp;highlight=' . urlencode($search['keywords']);
    }

    $sorturl    = "search.php?action=results&amp;sid={$sid}";
    $forumcache = $cache->read('forums');
    $forumsread = [];

    if ($CURUSER['id'] == 0) {
        $q = $db->sql_query("SELECT fid FROM forums WHERE active != 0 ORDER BY pid, disporder");
        $forumsread = isset($mybb->cookies['mybb']['forumread'])
            ? my_unserialize($mybb->cookies['mybb']['forumread'], false) : [];
    } else {
        $q = $db->sql_query("SELECT f.fid, fr.dateline AS lastread FROM forums f LEFT JOIN forumsread fr ON (fr.fid=f.fid AND fr.uid='{$CURUSER['id']}') WHERE f.active!=0 ORDER BY pid, disporder");
    }
    $readforums = [];
    while ($f = $db->fetch_array($q)) {
        if ($CURUSER['id'] == 0 && !empty($forumsread[$f['fid']])) $f['lastread'] = $forumsread[$f['fid']];
        $readforums[$f['fid']] = $f['lastread'] ?? '';
    }
    $fpermissions = forum_permissions();

    // ── Threads ────────────────────────────────────────────────────────────
    if ($is_threads) {

        $unapproved_where_t = get_visible_where('t');
        $threadcount        = 0;
        $threads            = [];

        if ($search['querycache'] !== '') {
            $q = $db->simple_select('threads t', 't.tid', $search['querycache'] . " AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' ORDER BY t.lastpost DESC {$limitsql}");
            while ($t = $db->fetch_array($q)) { $threads[$t['tid']] = $t['tid']; $threadcount++; }
            if (!$threadcount) stderr($lang->search['error_nosearchresults']);
            $search['threads'] = implode(',', $threads);
            $where_conditions  = 't.tid IN (' . $search['threads'] . ')';
        } else {
            $where_conditions = 't.tid IN (' . $search['threads'] . ')';
            $q     = $db->simple_select('threads t', 'COUNT(t.tid) AS resultcount', $where_conditions . " AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' {$limitsql}");
            $cnt   = $db->fetch_array($q);
            if (!$cnt['resultcount']) stderr($lang->search['error_nosearchresults']);
            $threadcount = $cnt['resultcount'];
        }

        $permsql = ''; $onlyusfids = [];
        $gp = forum_permissions();
        foreach ($gp as $fid => $fp) {
            if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
        }
        if (!empty($onlyusfids)) $permsql .= "AND ((t.fid IN(" . implode(',', $onlyusfids) . ") AND t.uid='{$CURUSER['id']}') OR t.fid NOT IN(" . implode(',', $onlyusfids) . "))";
        $uf = get_unsearchable_forums(); if ($uf) $permsql .= " AND t.fid NOT IN ($uf)";
        $ia = get_inactive_forums();     if ($ia) $permsql .= " AND t.fid NOT IN ($ia)";

        $pages = max(1, (int)ceil($threadcount / $perpage));
        if ($page > $pages) { $start = 0; $page = 1; }

        $q = $db->sql_query("
            SELECT t.*, u.username AS userusername, u.avatar, u.avatardimensions, u.usergroup AS u_usergroup,
                   COALESCE(tr.avg_rating, 0) AS avg_rating
            FROM threads t
            LEFT JOIN users u ON (u.id = t.uid)
            LEFT JOIN forums f ON (t.fid = f.fid)
            LEFT JOIN (
                SELECT tid, AVG(rating) AS avg_rating
                FROM threadratings
                GROUP BY tid
            ) tr ON (tr.tid = t.tid)
            WHERE $where_conditions AND ({$unapproved_where_t}) {$permsql} AND t.closed NOT LIKE 'moved|%'
            ORDER BY $sortfield $order
            LIMIT $start, $perpage
        ");

        $thread_cache = [];
        while ($t = $db->fetch_array($q)) {
            $t['threadprefix'] = '';
            $thread_cache[$t['tid']] = $t;
        }
        $thread_ids = implode(',', array_keys($thread_cache));
        if (empty($thread_ids)) stderr($lang->search['error_nosearchresults']);

        // dot icons
        if ($CURUSER['id'] && $thread_cache) {
            $uwp = str_replace('t.', '', $unapproved_where_t);
            $q2  = $db->simple_select('posts', 'DISTINCT tid,uid', "uid='{$CURUSER['id']}' AND tid IN({$thread_ids}) AND ({$uwp})");
            while ($t = $db->fetch_array($q2)) $thread_cache[$t['tid']]['dot_icon'] = 1;
        }
        // read threads
        $threadreadcut = 7;
        if ($CURUSER['id'] && $threadreadcut > 0) {
            $q2 = $db->simple_select('threadsread', 'tid,dateline', "uid='{$CURUSER['id']}' AND tid IN({$thread_ids})");
            while ($rt = $db->fetch_array($q2)) $thread_cache[$rt['tid']]['lastread'] = $rt['dateline'];
        }

        // ── OUTPUT ────────────────────────────────────────────────────────
        stdhead('Search Results');
        build_breadcrumb();
        echo search_css();
        echo '<div class="sr-res-wrap">';

        // Header
        echo '<div class="sr-res-header">';
        echo '<div><div class="sr-res-title"><i class="fas fa-search me-2" style="color:var(--sr-primary)"></i>Thread Results</div>';
        echo '<div class="sr-res-count">Found <strong>' . ts_nf($threadcount) . '</strong> thread' . ($threadcount !== 1 ? 's' : '') . '</div></div>';
        echo '<div class="sr-sort-bar">';
        $sort_opts = ['lastpost'=>'Date','replies'=>'Replies','views'=>'Views','rating'=>'Rating','subject'=>'Subject','starter'=>'Author','forum'=>'Forum'];
        foreach ($sort_opts as $key => $label) {
            $active = $sortby === $key ? ' active' : '';
            $no     = ($sortby === $key && $order === 'asc') ? 'desc' : 'asc';
            echo '<a href="' . $sorturl . '&amp;sortby=' . $key . '&amp;order=' . $no . '" class="sr-sort-btn' . $active . '">' . $label . '</a>';
        }
        echo '</div></div>';

        // Cards
        $f_postsperpage  = 10;
        $maxmultilinks   = 5;

        foreach ($thread_cache as $thread) {
            if ($thread['userusername']) $thread['username'] = $thread['userusername'];
            $thread['username']    = htmlspecialchars_uni($thread['username']);
            $thread['subject']     = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

            $thread_link  = get_thread_link($thread['tid']);
            $lastpostdate = my_datee('relative', $thread['lastpost']);
            $lp_uid       = $thread['lastposteruid'];
            $lp_name      = $lp_uid ? htmlspecialchars_uni($thread['lastposter']) : htmlspecialchars_uni($lang->guest);
            $lp_link      = $lp_uid ? build_profile_link($lp_name, $lp_uid) : $lp_name;

            // new/hot/closed badges
            $badges = '';
            $read_cutoff = TIMENOW - $threadreadcut * 86400;
            $forum_read  = $readforums[$thread['fid']] ?? 0;
            if ($forum_read == 0 || $forum_read < $read_cutoff) $forum_read = $read_cutoff;
            if ($threadreadcut > 0 && $CURUSER['id'] && $thread['lastpost'] > $forum_read) {
                $last_read = $thread['lastread'] ?? $read_cutoff;
            } else {
                $last_read = my_get_array_cookie('threadread', $thread['tid']);
            }
            if ($forum_read > $last_read) $last_read = $forum_read;
            if ($thread['lastpost'] > $last_read && $last_read) {
                $badges .= '<span class="sr-new-badge"><i class="fas fa-circle" style="font-size:6px"></i> New</span>';
            }
            if ($thread['replies'] >= 20 || $thread['views'] >= 150) {
                $badges .= '<span class="sr-new-badge sr-hot-badge"><i class="fas fa-fire"></i> Hot</span>';
            }
            if ($thread['closed'] == 1) {
                $badges .= '<span class="sr-new-badge sr-closed-badge"><i class="fas fa-lock"></i></span>';
            }

            // multipage
            $multipages_html = '';
            $thread['posts'] = $thread['replies'] + 1;
            if ($is_mod) $thread['posts'] += $thread['unapprovedposts'];
            if ($thread['posts'] > $f_postsperpage) {
                $total_pages = (int)ceil($thread['posts'] / $f_postsperpage);
                $stop        = min($total_pages, $maxmultilinks);
                $multipages_html = '<div class="sr-multipage">';
                for ($i = 1; $i <= $stop; $i++) {
                    $multipages_html .= '<a href="' . get_thread_link($thread['tid'], $i) . $highlight . '">' . $i . '</a>';
                }
                if ($total_pages > $maxmultilinks) {
                    $multipages_html .= '<a href="' . get_thread_link($thread['tid'], $total_pages) . $highlight . '">…' . $total_pages . '</a>';
                }
                $multipages_html .= '</div>';
            }

            // forum badge
            $forum_name   = htmlspecialchars($forumcache[$thread['fid']]['name'] ?? '');
            $forum_link   = get_forum_link($thread['fid']);
            $profile_link = get_profile_link((int)$thread['uid']);
            $avatar_html  = sr_avatar($thread['avatar'] ?? '', $thread['avatardimensions'] ?? '', $thread['username']);

            // inline mod checkbox

            echo '<div class="sr-thread-card" onclick="window.location.href=\'' . $thread_link . '\'">';
            echo '<div class="sr-thread-inner">';
            echo '<div class="sr-avatar"><a href="' . $profile_link . '" onclick="event.stopPropagation()">' . $avatar_html . '</a></div>';
            echo '<div class="sr-thread-body">';

            // Subject row
            echo '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px;">';
            echo '<a href="' . $thread_link . '" class="sr-thread-subject" onclick="event.stopPropagation()">';
            echo htmlspecialchars_uni($thread['threadprefix']) . $thread['subject'];
            echo '</a>';
            echo $badges;
            echo '</div>';

            // Meta
            echo '<div class="sr-meta">';
            echo '<a href="' . $forum_link . '" class="sr-sort-btn active" onclick="event.stopPropagation()"><i class="fas fa-folder-open"></i>' . $forum_name . '</a>';
            echo '<span><i class="fas fa-user"></i><a href="' . $profile_link . '" onclick="event.stopPropagation()">' . $thread['username'] . '</a></span>';
            echo '<span><i class="fas fa-comments"></i>' . ts_nf($thread['replies']) . ' replies</span>';
            echo '<span><i class="fas fa-eye"></i>' . ts_nf($thread['views']) . ' views</span>';
            if (!empty($thread['avg_rating']) && (float)$thread['avg_rating'] > 0) {
                echo '<span class="sr-rating-pill"><i class="fas fa-star"></i>' . number_format((float)$thread['avg_rating'], 1) . '</span>';
            }
            echo '<span><i class="fas fa-clock"></i>' . $lastpostdate . ' by ' . $lp_link . '</span>';
            echo '</div>';

            if ($multipages_html) echo $multipages_html;
            echo '</div>'; // body

            // Action button
            echo '<div class="sr-thread-actions">';
            echo '<a href="' . $thread_link . '" class="sr-action-btn sr-action-view" onclick="event.stopPropagation()"><i class="fas fa-arrow-right"></i> View</a>';
            echo '</div>';

            echo '</div>'; // inner
            echo '</div>'; // card
        }

        // Pagination
        $mp_url = "search.php?action=results&amp;sid=$sid&amp;sortby=$sortby&amp;order=$order&amp;uid=" . $mybb->get_input('uid', MyBB::INPUT_INT);
        echo '<div class="sr-pagination">';
        if ($page > 1) echo '<a href="' . $mp_url . '&amp;page=' . ($page - 1) . '" class="sr-page-btn"><i class="fas fa-chevron-left"></i></a>';
        $ps = max(1, $page - 2); $pe = min($pages, $page + 2);
        if ($ps > 1) { echo '<a href="' . $mp_url . '&amp;page=1" class="sr-page-btn">1</a>'; if ($ps > 2) echo '<span class="sr-page-btn disabled">…</span>'; }
        for ($i = $ps; $i <= $pe; $i++) echo '<a href="' . $mp_url . '&amp;page=' . $i . '" class="sr-page-btn' . ($i === $page ? ' active' : '') . '">' . $i . '</a>';
        if ($pe < $pages) { if ($pe < $pages - 1) echo '<span class="sr-page-btn disabled">…</span>'; echo '<a href="' . $mp_url . '&amp;page=' . $pages . '" class="sr-page-btn">' . $pages . '</a>'; }
        if ($page < $pages) echo '<a href="' . $mp_url . '&amp;page=' . ($page + 1) . '" class="sr-page-btn"><i class="fas fa-chevron-right"></i></a>';
        echo '</div>';

        echo '</div>';
        $plugins->run_hooks('search_results_end');
        stdfoot();

    } else {
        // ── Posts ──────────────────────────────────────────────────────────
		
		
        if (empty($search['posts'])) stderr($lang->search['error_nosearchresults']);

        $unapproved_where   = get_visible_where();
        $post_cache_options = [];
        if ($searchhardlimit > 0) $post_cache_options['limit'] = $searchhardlimit;
        if (str_contains($sortfield, 'p.')) {
            $post_cache_options['order_by']  = str_replace('p.', '', $sortfield);
            $post_cache_options['order_dir'] = $order;
        }

        $tids = []; $pids = [];
        $q = $db->simple_select('posts', 'pid, tid', "pid IN(" . $db->escape_string($search['posts']) . ") AND ({$unapproved_where})", $post_cache_options);
		


		
		
        while ($p = $db->fetch_array($q)) 
		{ 
	
	     $pids[$p['pid']] = $p['tid']; $tids[$p['tid']][$p['pid']] = $p['pid']; 
		 
		
	
	  
	    }
		
	
		
		
		

        if (!empty($pids)) {
            $gp = forum_permissions(); $permsql = ''; $onlyusfids = [];
            foreach ($gp as $fid => $fp) { if (!empty($fp['canonlyviewownthreads'])) $onlyusfids[] = $fid; }
            if ($onlyusfids) $permsql .= ' OR (fid IN(' . implode(',', $onlyusfids) . ") AND uid!={$CURUSER['id']})";
            $uf = get_unsearchable_forums(); if ($uf) $permsql .= " OR fid IN ($uf)";
            $ia = get_inactive_forums();     if ($ia) $permsql .= " OR fid IN ($ia)";
            $q = $db->simple_select('threads', 'tid', "tid IN(" . $db->escape_string(implode(',', array_keys($tids))) . ") AND (NOT ({$unapproved_where}){$permsql} OR closed LIKE 'moved|%')");
            while ($t = $db->fetch_array($q)) {
                foreach ($tids[$t['tid']] as $pid) unset($pids[$pid]);
                unset($tids[$t['tid']]);
            }
        }
		
		
		

        $postcount = count($pids);
		
		
		
		
		
        if (!$postcount) stderr($lang->search['error_nosearchresults']);
        $search['posts'] = implode(',', array_keys($pids));
        $tids_str        = implode(',', array_keys($tids));

        $readthreads = [];
        $threadreadcut = 7;
        if ($CURUSER['id'] && $threadreadcut > 0) {
            $q = $db->simple_select('threadsread', 'tid, dateline', "uid='{$CURUSER['id']}' AND tid IN(" . $db->escape_string($tids_str) . ")");
            while ($rt = $db->fetch_array($q)) $readthreads[$rt['tid']] = $rt['dateline'];
        }

        $pages = max(1, (int)ceil($postcount / $perpage));
        if ($page > $pages) { $start = 0; $page = 1; }

        $q = $db->sql_query("
            SELECT p.*, u.username AS userusername, u.avatar, u.avatardimensions, u.usergroup AS u_usergroup,
                   t.subject AS thread_subject, t.replies AS thread_replies, t.views AS thread_views,
                   t.lastpost AS thread_lastpost, t.closed AS thread_closed, t.uid AS thread_uid
            FROM posts p
            LEFT JOIN threads t ON (t.tid = p.tid)
            LEFT JOIN users u ON (u.id = p.uid)
            LEFT JOIN forums f ON (t.fid = f.fid)
            WHERE p.pid IN (" . $db->escape_string($search['posts']) . ")
            ORDER BY $sortfield $order
            LIMIT $start, $perpage
        ");
		

        stdhead('Search Results — Posts');
        build_breadcrumb();
        echo search_css();
        echo '<div class="sr-res-wrap">';

        // Header
        echo '<div class="sr-res-header">';
        echo '<div><div class="sr-res-title"><i class="fas fa-comment-dots me-2" style="color:#10b981"></i>Post Results</div>';
        echo '<div class="sr-res-count">Found <strong>' . ts_nf($postcount) . '</strong> post' . ($postcount !== 1 ? 's' : '') . '</div></div>';
        echo '<div class="sr-sort-bar">';
        $sort_opts2 = ['dateline'=>'Date','subject'=>'Subject','starter'=>'Author','forum'=>'Forum'];
        foreach ($sort_opts2 as $key => $label) {
            $active = $sortby === $key ? ' active' : '';
            $no     = ($sortby === $key && $order === 'asc') ? 'desc' : 'asc';
            echo '<a href="' . $sorturl . '&amp;sortby=' . $key . '&amp;order=' . $no . '" class="sr-sort-btn' . $active . '">' . $label . '</a>';
        }
        echo '</div></div>';

        

        while ($post = $db->fetch_array($q)) {
            if ($post['userusername']) $post['username'] = $post['userusername'];
            $post['username']       = htmlspecialchars_uni($post['username']);
            $post['thread_subject'] = htmlspecialchars_uni($parser->parse_badwords($post['thread_subject']));

            //$parser_opts['me_username'] = $post['username'];
            $clean_msg = $parser->parse_message($post['message'], $parser_options);
			
			$preview = $clean_msg;

            $posted    = my_datee('relative', $post['dateline']);

            $thread_url  = get_thread_link($post['tid']);
            $post_url    = get_post_link($post['pid'], $post['tid']);
            $forum_name  = htmlspecialchars($forumcache[$post['fid']]['name'] ?? '');
            $forum_link  = get_forum_link($post['fid']);
            $profile_url = get_profile_link((int)$post['uid']);
            $avatar_html = sr_avatar($post['avatar'] ?? '', $post['avatardimensions'] ?? '', $post['username']);



            echo '<div class="sr-post-card">';
            echo '<div class="sr-thread-inner">';
            echo '<div class="sr-avatar"><a href="' . $profile_url . '">' . $avatar_html . '</a></div>';
            echo '<div class="sr-thread-body">';

            // Author + timestamp row (who/when, right above what)
            echo '<div class="sr-post-byline">';
            echo '<a href="' . $profile_url . '" class="sr-post-author">' . $post['username'] . '</a>';
            echo '<span class="sr-post-dot">&middot;</span>';
            echo '<span class="sr-post-time"><i class="fas fa-clock"></i>' . $posted . '</span>';
            echo '<span class="badge bg-success-subtle text-success-emphasis sr-post-badge">Post</span>';
            echo '</div>';

            // Subject (what)
            echo '<a href="' . $thread_url . '" class="sr-thread-subject sr-post-subject">' . $post['thread_subject'] . '</a>';

            if ($preview) {
                echo '<div class="sr-snippet"><i class="fas fa-quote-left me-1 opacity-50"></i>' . $preview . '</div>';
            }

            // Forum (where) - on its own row, visually lighter
            echo '<div class="sr-meta sr-post-forum-row">';
            echo '<a href="' . $forum_link . '" class="sr-sort-btn active"><i class="fas fa-folder-open"></i>' . $forum_name . '</a>';
            echo '</div>';

            echo '</div>'; // body

            echo '<div class="sr-thread-actions">';
            echo '<a href="' . $post_url . '#pid' . $post['pid'] . '" class="sr-action-btn sr-action-view"><i class="fas fa-comment"></i> View post</a>';
            echo '<a href="' . $thread_url . '" class="sr-thread-badge-link"><i class="fas fa-list"></i> Thread</a>';
            echo '</div>';

            echo '</div></div>';
        }

        // Pagination
        $mp_url2 = "search.php?action=results&amp;sid=" . htmlspecialchars_uni($mybb->get_input('sid')) . "&amp;sortby=$sortby&amp;order=$order&amp;uid=" . $mybb->get_input('uid', MyBB::INPUT_INT);
        echo '<div class="sr-pagination">';
        if ($page > 1) echo '<a href="' . $mp_url2 . '&amp;page=' . ($page - 1) . '" class="sr-page-btn"><i class="fas fa-chevron-left"></i></a>';
        $ps2 = max(1, $page - 2); $pe2 = min($pages, $page + 2);
        if ($ps2 > 1) { echo '<a href="' . $mp_url2 . '&amp;page=1" class="sr-page-btn">1</a>'; if ($ps2 > 2) echo '<span class="sr-page-btn disabled">…</span>'; }
        for ($i = $ps2; $i <= $pe2; $i++) echo '<a href="' . $mp_url2 . '&amp;page=' . $i . '" class="sr-page-btn' . ($i === $page ? ' active' : '') . '">' . $i . '</a>';
        if ($pe2 < $pages) { if ($pe2 < $pages - 1) echo '<span class="sr-page-btn disabled">…</span>'; echo '<a href="' . $mp_url2 . '&amp;page=' . $pages . '" class="sr-page-btn">' . $pages . '</a>'; }
        if ($page < $pages) echo '<a href="' . $mp_url2 . '&amp;page=' . ($page + 1) . '" class="sr-page-btn"><i class="fas fa-chevron-right"></i></a>';
        echo '</div>';

        echo '</div>';
        $plugins->run_hooks('search_results_end');
        stdfoot();
    }

// ─────────────────────────────────────────────────────────────────────────────
// findguest / finduser / finduserthreads / getnew / getdaily
// ─────────────────────────────────────────────────────────────────────────────
} elseif (in_array($mybb->input['action'], ['findguest','finduser','finduserthreads','getnew','getdaily'], true)) {

    $action = $mybb->input['action'];

    $where_sql = match ($action) {
        'findguest'       => "uid='0'",
        'finduser',
        'finduserthreads' => "uid='" . $mybb->get_input('uid', MyBB::INPUT_INT) . "'",
        'getnew'          => "lastpost >= '" . (int)$CURUSER['lastvisit'] . "'",
        'getdaily'        => (function() use ($mybb): string {
            $days = max(1, $mybb->get_input('days', MyBB::INPUT_INT));
            return "lastpost >= '" . (TIMENOW - 86400 * $days) . "'";
        })(),
    };

    // optional fid filter for getnew/getdaily
    if (in_array($action, ['getnew','getdaily'], true)) {
        if ($mybb->get_input('fid', MyBB::INPUT_INT)) {
            $where_sql .= " AND fid='" . $mybb->get_input('fid', MyBB::INPUT_INT) . "'";
        } elseif ($mybb->get_input('fids')) {
            $fids = array_map('intval', explode(',', $mybb->get_input('fids')));
            if ($fids) $where_sql .= ' AND fid IN (' . implode(',', $fids) . ')';
        }
    }

    $uf = get_unsearchable_forums(); if ($uf) $where_sql .= " AND fid NOT IN ($uf)";
    $ia = get_inactive_forums();     if ($ia) $where_sql .= " AND fid NOT IN ($ia)";
    $where_sql .= ' AND (' . get_visible_where() . ')';

    $gp = forum_permissions(); $onlyusfids = [];
    foreach ($gp as $fid => $fp) {
        if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) $onlyusfids[] = $fid;
    }
    if (!empty($onlyusfids)) {
        if ($action === 'findguest') {
            $where_sql .= ' AND fid NOT IN(' . implode(',', $onlyusfids) . ')';
        } else {
            $where_sql .= " AND ((fid IN(" . implode(',', $onlyusfids) . ") AND uid='{$CURUSER['id']}') OR fid NOT IN(" . implode(',', $onlyusfids) . "))";
        }
    }

    $resulttype = 'threads'; $querycache = ''; $pids = ''; $tids = ''; $comma = '';

    if (in_array($action, ['finduserthreads','getnew','getdaily'], true)) {
        $q = $db->simple_select('threads', 'tid', $where_sql);
        while ($tid = $db->fetch_field($q, 'tid')) { $tids .= $comma . $tid; $comma = ','; }
        $querycache = $where_sql;
    } else {
        $resulttype = 'posts';
        $opts = ['order_by' => 'dateline DESC, pid DESC'];
        if ($searchhardlimit > 0) $opts['limit'] = $searchhardlimit;
        $q = $db->simple_select('posts', 'pid', $where_sql, $opts);
        while ($pid = $db->fetch_field($q, 'pid')) { $pids .= $comma . $pid; $comma = ','; }
        $comma = '';
        $q = $db->simple_select('threads', 'tid', $where_sql);
        while ($tid = $db->fetch_field($q, 'tid')) { $tids .= $comma . $tid; $comma = ','; }
    }

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_do_search_process');
    $db->insert_query('searchlog', [
        'sid'        => $db->escape_string($sid),
        'uid'        => $CURUSER['id'],
        'dateline'   => TIMENOW,
        'ipaddress'  => $db->escape_binary($session->packedip),
        'threads'    => $db->escape_string($tids),
        'posts'      => $db->escape_string($pids),
        'resulttype' => $resulttype,
        'querycache' => $db->escape_string($querycache),
        'keywords'   => '',
    ]);
    redirect("search.php?action=results&sid=$sid", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// do_search
// ─────────────────────────────────────────────────────────────────────────────
} elseif ($mybb->input['action'] === 'do_search') {
	
	
	 
    if (!verify_post_check($mybb->get_input('postcode'))) {
        stderr($lang->search['error_invalidsearch']);
    }

    $plugins->run_hooks('search_do_search_start');

    $searchfloodtime = 30;
    if ($searchfloodtime > 0 && $usergroups['cansearch'] != 1) {
        $cond    = $CURUSER['id'] ? "uid='{$CURUSER['id']}'" : "uid='0' AND ipaddress=" . $db->escape_binary($session->packedip);
        $timecut = TIMENOW - $searchfloodtime;
        $q       = $db->simple_select('searchlog', '*', "$cond AND dateline > '$timecut'", ['order_by'=>'dateline','order_dir'=>'DESC']);
        $ls      = $db->fetch_array($q);
        if (!empty($ls['sid'])) {
            $rt  = $searchfloodtime - (TIMENOW - $ls['dateline']);
            stderr($rt === 1
                ? sprintf($lang->search['error_searchflooding_1'], $searchfloodtime)
                : sprintf($lang->search['error_searchflooding'], $searchfloodtime, $rt)
            );
        }
    }

    $resulttype = $mybb->get_input('showresults') === 'threads' ? 'threads' : 'posts';
    $forums     = isset($mybb->input['forums']) && is_array($mybb->input['forums'])
        ? $mybb->get_input('forums', MyBB::INPUT_ARRAY)
        : [$mybb->get_input('forums')];

    $search_data = [
        'keywords'      => $mybb->input['keywords'],
        'author'        => $mybb->get_input('author'),
        'postthread'    => $mybb->get_input('postthread',    MyBB::INPUT_INT),
        'matchusername' => $mybb->get_input('matchusername', MyBB::INPUT_INT),
        'postdate_from' => to_search_ts($mybb->get_input('postdate_from'), false),
        'postdate_to'   => to_search_ts($mybb->get_input('postdate_to'),   true),
        'forums'        => $forums,
        'findthreadst'  => $mybb->get_input('findthreadst',  MyBB::INPUT_INT),
        'numreplies'    => $mybb->get_input('numreplies',    MyBB::INPUT_INT),
        'threadprefix'  => $mybb->get_input('threadprefix',  MyBB::INPUT_ARRAY),
    ];
	
	
	
	
	
	
	
    if ($is_mod && !empty($mybb->input['visible'])) {
        $search_data['visible'] = $mybb->get_input('visible', MyBB::INPUT_INT);
    }

    if (!$db->can_search) stderr('error_no_search_support');

    $search_results = ($db->supports_fulltext_boolean('posts') && $db->is_fulltext('posts'))
        ? perform_search_mysql_ft($search_data)
        : perform_search_mysql($search_data);

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_do_search_process');
    
	
	
	$db->insert_query('searchlog', [
        'sid'        => $db->escape_string($sid),
        'uid'        => $CURUSER['id'],
        'dateline'   => $now,
        'ipaddress'  => $db->escape_binary($session->packedip),
        'threads'    => $search_results['threads'],
        'posts'      => $search_results['posts'],
        'resulttype' => $resulttype,
        'querycache' => $search_results['querycache'],
        'keywords'   => $db->escape_string($mybb->input['keywords']),
    ]);

    $so = my_strtolower($mybb->get_input('sortordr'));
    $sortorder = in_array($so, ['asc','desc']) ? $so : 'desc';
    $sortby    = htmlspecialchars_uni($mybb->get_input('sortby'));
    $plugins->run_hooks('search_do_search_end');
    redirect("search.php?action=results&sid={$sid}&sortby={$sortby}&order={$sortorder}", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// thread search
// ─────────────────────────────────────────────────────────────────────────────
} elseif ($mybb->input['action'] === 'thread') {

    $thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
    $forum  = get_forum($thread['fid']);
    if (!$forum) stderr($lang->search['error_invalidforum']);
    if ($forum['open'] == 0 || $forum['type'] !== 'f') stderr($lang->search['error_closedinvalidforum']);
    if (!$db->can_search) stderr($lang->search['error_no_search_support']);

    $plugins->run_hooks('search_thread_start');

    $search_results = ($db->supports_fulltext_boolean('posts') && $db->is_fulltext('posts'))
        ? perform_search_mysql_ft(['keywords'=>$mybb->input['keywords'],'postthread'=>1,'tid'=>$mybb->get_input('tid',MyBB::INPUT_INT)])
        : perform_search_mysql(['keywords'=>$mybb->input['keywords'],'postthread'=>1,'tid'=>$mybb->get_input('tid',MyBB::INPUT_INT)]);

    $sid = md5(uniqid(microtime(), true));
    $plugins->run_hooks('search_thread_process');
    $db->insert_query('searchlog', [
        'sid'        => $db->escape_string($sid),
        'uid'        => $CURUSER['id'],
        'dateline'   => $now,
        'ipaddress'  => $db->escape_binary($session->packedip),
        'threads'    => $search_results['threads'],
        'posts'      => $search_results['posts'],
        'resulttype' => 'posts',
        'querycache' => $search_results['querycache'],
        'keywords'   => $db->escape_string($mybb->input['keywords']),
    ]);
    $plugins->run_hooks('search_do_search_end');
    redirect("search.php?action=results&sid=$sid", $lang->search['redirect_searchresults']);

// ─────────────────────────────────────────────────────────────────────────────
// Search form (default)
// ─────────────────────────────────────────────────────────────────────────────
} else {

    $plugins->run_hooks('search_start');
    $srchlist      = make_searchable_forums();
    $prefixselect  = build_prefix_select('all', 'any', 1);
    $maxnamelength = 30;
    $rowspan       = 5;
    $moderator_options = '';
    if ($is_mod) {
        $rowspan += 2;
        
		
		$moderator_options = '<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->search['mod_options'].'</div>
<div class="row g-3 m-auto pb-4 border-bottom pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->search['mod_options'].'
</div>
<div class="col">
<div class="row">
	<div class="col-auto align-self-center">

<select name="visible" class="form-select form-select-sm border pe-5 w-auto">
<option value="">'.$lang->search['display_all'].'</option>
<option value="1">'.$lang->search['display_only_approved'].'</option>
<option value="0">'.$lang->search['display_only_unapproved'].'</option>
<option value="-1">'.$lang->search['display_only_softdeleted'].'</option>
</select>
		
	</div>
	<div class="col-auto align-self-center">

'.$lang->search['results'].'
		
	</div>
	</div>
</div>
</div>';


    }
    $plugins->run_hooks('search_end');

    $kw_val     = htmlspecialchars_uni($mybb->get_input('keywords'));
    $author_val = htmlspecialchars_uni($mybb->get_input('author'));

    stdhead('Forum Search');
    build_breadcrumb();
    echo search_css();
    ?>
    <div class="container mt-3">

        <div class="sr-hero">
            <h1><i class="fas fa-search" style="color:var(--sr-primary)"></i> Forum Search</h1>
            <p>Find threads, posts and discussions across the forum</p>
        </div>

        <!-- Quick links -->
        <div class="sr-quick-links">
            <a href="search.php?action=getnew" class="sr-quick-link"><i class="fas fa-bolt"></i> New posts</a>
            <a href="search.php?action=getdaily&days=1" class="sr-quick-link"><i class="fas fa-calendar-day"></i> Today</a>
            <a href="search.php?action=getdaily&days=7" class="sr-quick-link"><i class="fas fa-calendar-week"></i> This week</a>
            <?php if ($CURUSER['id']): ?>
            <a href="search.php?action=finduserthreads&uid=<?= (int)$CURUSER['id'] ?>" class="sr-quick-link"><i class="fas fa-user"></i> My threads</a>
            <a href="search.php?action=finduser&uid=<?= (int)$CURUSER['id'] ?>" class="sr-quick-link"><i class="fas fa-comment"></i> My posts</a>
            <?php endif; ?>
        </div>

        <div class="sr-card">
            <form action="search.php" method="post" id="srForm">
                <input type="hidden" name="action" value="do_search">
                <input type="hidden" name="postcode" value="<?= generate_post_check() ?>">
                <div class="sr-card-body">

                <!-- Main input -->
                <div class="sr-main-row" style="position:relative;">
                    <input type="text" name="keywords" id="srKeywords"
                           placeholder="Search keywords… (Ctrl+K)"
                           value="<?= $kw_val ?>" autocomplete="off" maxlength="200">
                    <button type="submit" class="sr-btn sr-btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <button type="button" class="sr-btn sr-btn-ghost" id="srClear">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>

                    <div id="srSuggestBox" class="sr-suggest-box" style="display:none;"></div>
                </div>

                <!-- Show results as -->
                <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                    <span class="sr-field-label mb-0">Show as:</span>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="showresults" id="srT" value="threads" checked>
                        <label class="form-check-label small fw-semibold" for="srT">Threads</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="showresults" id="srP" value="posts">
                        <label class="form-check-label small fw-semibold" for="srP">Posts</label>
                    </div>
                </div>

                <hr class="sr-divider">

                <!-- Advanced toggle -->
                <span class="sr-adv-toggle" data-bs-toggle="collapse" data-bs-target="#srAdv">
                    <i class="fas fa-sliders-h"></i> Advanced options
                    <i class="fas fa-chevron-down" style="font-size:10px;transition:transform .2s"></i>
                </span>

                <div class="collapse" id="srAdv">
                    <div class="sr-adv-panel">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-user me-1"></i>Author</label>
                                <input type="text" name="author" class="sr-field-ctrl"
                                       value="<?= $author_val ?>" placeholder="Username…">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="matchusername" value="1" id="srExact">
                                    <label class="form-check-label small text-muted" for="srExact">Exact match only</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-search-plus me-1"></i>Search in</label>
                                <select name="postthread" class="sr-field-ctrl">
                                    <option value="1">Subject &amp; message</option>
                                    <option value="0">Subject only</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="sr-field-label"><i class="fas fa-sort me-1"></i>Sort by</label>
                                <select name="sortby" class="sr-field-ctrl">
                                    <option value="lastpost">Last post date</option>
                                    <option value="dateline">Post date</option>
                                    <option value="subject">Subject</option>
                                    <option value="replies">Replies</option>
                                    <option value="views">Views</option>
                                    <option value="starter">Author</option>
                                    <option value="forum">Forum</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="sr-field-label"><i class="fas fa-sort-amount-down me-1"></i>Order</label>
                                <select name="sortordr" class="sr-field-ctrl">
                                    <option value="desc">Newest first</option>
                                    <option value="asc">Oldest first</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="sr-field-label"><i class="fas fa-calendar me-1"></i>Posted from</label>
                                <input type="text" name="postdate_from" id="srDateFrom" class="sr-field-ctrl"
                                       placeholder="YYYY-MM-DD" autocomplete="off"
                                       value="<?= htmlspecialchars($mybb->get_input('postdate_from')) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="sr-field-label"><i class="fas fa-calendar me-1"></i>Posted to</label>
                                <input type="text" name="postdate_to" id="srDateTo" class="sr-field-ctrl"
                                       placeholder="YYYY-MM-DD" autocomplete="off"
                                       value="<?= htmlspecialchars($mybb->get_input('postdate_to')) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-reply me-1"></i>Minimum replies</label>
                                <input type="number" name="numreplies" class="sr-field-ctrl" value="0" min="0">
                            </div>

                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-comments me-1"></i>Forums</label>
                                <?= $srchlist ?>
                                <small class="text-muted">Hold Ctrl to select multiple</small>
                            </div>

                            <?php if ($prefixselect): ?>
                            <div class="col-md-6">
                                <label class="sr-field-label"><i class="fas fa-tag me-1"></i>Thread prefix</label>
                                <?= $prefixselect ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($moderator_options): ?>
                            <div class="col-12">
                                <?= $moderator_options ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                </div><!-- /.sr-card-body -->
            </form>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <script src="<?= $BASEURL ?>/admin/scripts/flatpickr.js"></script>
    <script src="<?= $BASEURL ?>/scripts/search_page.js"></script>
    <?php
    stdfoot();
}