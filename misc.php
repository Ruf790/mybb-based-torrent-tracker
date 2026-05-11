<?php
declare(strict_types=1);

define('IN_MYBB',           1);
define('IGNORE_CLEAN_VARS', 'sid');
define('THIS_SCRIPT',       'misc.php');
define('IN_FORUM',          true);

require_once 'global.php';
require_once INC_PATH . '/functions_post.php';

$lang->load('misc');

$plugins->run_hooks('misc_start');

$action = $mybb->get_input('action');

// ══════════════════════════════════════════════════════════════════════════
// ACTION: dstswitch
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'dstswitch' && $mybb->request_method === 'post' && $CURUSER['id'] > 0) {

    if ((int)($CURUSER['dstcorrection'] ?? 0) === 2) {
        $db->update_query('users',
            ['dst' => $CURUSER['dst'] == 1 ? 0 : 1],
            "id='{$CURUSER['id']}'"
        );
    }

    if (!isset($mybb->input['ajax'])) {
        redirect('index.php', $lang->misc['dst_settings_updated'] ?? '');
    }
    echo 'done';
    exit;

// ══════════════════════════════════════════════════════════════════════════
// ACTION: markread
// ══════════════════════════════════════════════════════════════════════════
} elseif ($action === 'markread') {

    if ($CURUSER['id'] && verify_post_check($mybb->get_input('my_post_key'), true) !== true) {
        stderr($lang->misc['invalid_post_code'] ?? 'Invalid post code.', '', 403, '403');
    }

    require_once INC_PATH . '/functions_indicators.php';

    if (isset($mybb->input['fid'])) {
        $fid        = $mybb->get_input('fid', MyBB::INPUT_INT);
        $validforum = get_forum($fid);

        if (!$validforum) {
            if (!isset($mybb->input['ajax'])) {
                stderr($lang->misc['error_invalidforum'] ?? 'Invalid forum.', '', 404, '404');
            }
            echo 0;
            exit;
        }

        mark_forum_read($fid);
        $plugins->run_hooks('misc_markread_forum');

        if (!isset($mybb->input['ajax'])) {
            redirect(get_forum_link($fid), $lang->misc['redirect_markforumread'] ?? '');
        }
        echo 1;
        exit;
    }

    $plugins->run_hooks('misc_markread_end');
    mark_all_forums_read();
    redirect('index2.php', $lang->misc['redirect_markforumsread'] ?? '');

// ══════════════════════════════════════════════════════════════════════════
// ACTION: clearpass
// ══════════════════════════════════════════════════════════════════════════
} elseif ($action === 'clearpass') {

    $plugins->run_hooks('misc_clearpass');

    if (isset($mybb->input['fid'])) {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            stderr($lang->misc['invalid_post_code'] ?? 'Invalid post code.', '', 403, '403');
        }
        my_unsetcookie('forumpass[' . $mybb->get_input('fid', MyBB::INPUT_INT) . ']');
        redirect('index.php', $lang->misc['redirect_forumpasscleared'] ?? '');
    }

// ══════════════════════════════════════════════════════════════════════════
// ACTION: whoposted
// ══════════════════════════════════════════════════════════════════════════
} elseif ($action === 'whoposted') {

    $tid    = $mybb->get_input('tid', MyBB::INPUT_INT);
    $modal  = $mybb->get_input('modal', MyBB::INPUT_INT);
    $sort   = $mybb->get_input('sort') === 'username' ? 'username' : 'posts';
    $thread = get_thread($tid);

    if (!$thread || $thread['visible'] == -1) {
        stderr($lang->misc['error_invalidthread'] ?? 'Invalid thread.', '', 404, '404');
    }

    $forum = get_forum($thread['fid']);
    if (!$forum || $forum['type'] !== 'f') {
        stderr($lang->misc['error_invalidforum'] ?? 'Invalid forum.', '', 404, '404');
    }

    $forumpermissions = forum_permissions($forum['fid']);
    if (
        $forumpermissions['canview'] == 0 ||
        $forumpermissions['canviewthreads'] == 0 ||
        (isset($forumpermissions['canonlyviewownthreads']) &&
         $forumpermissions['canonlyviewownthreads'] != 0 &&
         $thread['uid'] != $CURUSER['id'])
    ) {
        print_no_permission();
    }

    check_forum_password($forum['fid']);

    $sortsql = $sort === 'username' ? 'ORDER BY p.username ASC' : 'ORDER BY posts DESC';

    $query = $db->sql_query("
        SELECT COUNT(p.pid) AS posts, p.username AS postusername,
               u.id, u.username, u.usergroup, u.displaygroup
        FROM posts p
        LEFT JOIN users u ON (u.id = p.uid)
        WHERE p.tid = '" . (int)$tid . "' AND p.visible IN (0,1)
        GROUP BY u.id, p.username, u.username, u.usergroup, u.displaygroup
        {$sortsql}
    ");

    $rows     = '';
    $numposts = 0;

    while ($poster = $db->fetch_array($query)) {
        if ($poster['username'] === '') {
            $poster['username'] = $poster['postusername'];
        }
        $poster['username'] = htmlspecialchars_uni($poster['username']);
        $poster_name        = format_name($poster['username'], $poster['usergroup'], $poster['displaygroup']);
        $profile_link       = build_profile_link($poster_name, (int)$poster['id']);
        $numposts          += (int)$poster['posts'];

        $rows .= '
        <div class="d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-user-circle text-muted"></i>
                ' . $profile_link . '
            </div>
            <span class="badge bg-primary rounded-pill">' . ts_nf((int)$poster['posts']) . '</span>
        </div>';
    }

    $numposts_fmt = ts_nf($numposts);
    $BASE         = htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8');

    if ($modal) {
        header('Content-Type: text/html; charset=utf-8');

        $active_user  = $sort === 'username' ? 'fw-bold text-primary' : 'text-muted';
        $active_posts = $sort === 'posts'    ? 'fw-bold text-primary' : 'text-muted';

        echo '
        <div class="modal-header border-bottom px-3 py-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-users text-primary"></i>
                <span class="fw-semibold">'
                    . htmlspecialchars($lang->misc['who_posted'] ?? 'Who posted', ENT_QUOTES, 'UTF-8') . '
                </span>
                <span class="badge bg-primary rounded-pill ms-1">' . $numposts_fmt . '</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light small">
            <a href="#" class="text-decoration-none ' . $active_user . '"
               onclick="whoPostedLoad(' . (int)$tid . ', \'username\'); return false;">
                <i class="fas fa-sort-alpha-down me-1"></i>'
                . htmlspecialchars($lang->misc['user'] ?? 'Username', ENT_QUOTES, 'UTF-8') . '
            </a>
            <a href="#" class="text-decoration-none ' . $active_posts . '"
               onclick="whoPostedLoad(' . (int)$tid . ', \'posts\'); return false;">
                <i class="fas fa-sort-numeric-down me-1"></i>'
                . htmlspecialchars($lang->misc['num_posts'] ?? 'Posts', ENT_QUOTES, 'UTF-8') . '
            </a>
        </div>
        <div class="modal-body p-0" style="max-height:360px; overflow-y:auto;">
            ' . ($rows ?: '<p class="text-muted text-center py-4 mb-0">No posts found.</p>') . '
        </div>';
        exit;
    }

    // Полная страница
    $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

    $breadcrumbprefix = '';
    if ($thread['prefix']) {
        $threadprefix = build_prefixes($thread['prefix']);
        if (!empty($threadprefix['displaystyle'])) {
            $breadcrumbprefix = $threadprefix['displaystyle'] . '&nbsp;';
        }
    }

    build_forum_breadcrumb($forum['fid']);
    add_breadcrumb($breadcrumbprefix . $thread['subject'], get_thread_link($thread['tid']));
    add_breadcrumb($lang->misc['who_posted'] ?? 'Who Posted');

    stdhead($thread['subject'] . ' — ' . ($lang->misc['who_posted'] ?? 'Who Posted'));
    build_breadcrumb();

    $url_user  = $BASE . '/misc.php?action=whoposted&amp;tid=' . (int)$tid . '&amp;sort=username';
    $url_posts = $BASE . '/misc.php?action=whoposted&amp;tid=' . (int)$tid;

    echo '
    <div class="container-md py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-users me-2 text-primary"></i>'
                    . htmlspecialchars($lang->misc['who_posted'] ?? 'Who Posted', ENT_QUOTES, 'UTF-8') . '
                </h5>
                <span class="badge bg-primary rounded-pill">' . $numposts_fmt . '</span>
            </div>
            <div class="d-flex justify-content-between px-4 py-2 border-bottom small">
                <a href="' . $url_user  . '" class="text-decoration-none ' . ($sort === 'username' ? 'fw-bold text-primary' : 'text-muted') . '">
                    <i class="fas fa-sort-alpha-down me-1"></i>'
                    . htmlspecialchars($lang->misc['user'] ?? 'Username', ENT_QUOTES, 'UTF-8') . '
                </a>
                <a href="' . $url_posts . '" class="text-decoration-none ' . ($sort === 'posts' ? 'fw-bold text-primary' : 'text-muted') . '">
                    <i class="fas fa-sort-numeric-down me-1"></i>'
                    . htmlspecialchars($lang->misc['num_posts'] ?? 'Posts', ENT_QUOTES, 'UTF-8') . '
                </a>
            </div>
            <div class="card-body p-0">
                ' . ($rows ?: '<p class="text-muted text-center py-4 mb-0">No posts found.</p>') . '
            </div>
            <div class="card-footer text-center py-3">
                <a href="' . get_thread_link($thread['tid']) . '" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>'
                    . htmlspecialchars($lang->misc['return_to_thread'] ?? 'Return to thread', ENT_QUOTES, 'UTF-8') . '
                </a>
            </div>
        </div>
    </div>';

    stdfoot();

// ══════════════════════════════════════════════════════════════════════════
// ACTION: syndication
// ══════════════════════════════════════════════════════════════════════════
} elseif ($action === 'syndication') {

    $plugins->run_hooks('misc_syndication_start');

    $version = $mybb->get_input('version');
    $forums  = $mybb->get_input('forums', MyBB::INPUT_ARRAY);
    $limit   = $mybb->get_input('limit', MyBB::INPUT_INT);
    $url     = $BASEURL . '/syndication.php';

    add_breadcrumb($lang->misc['nav_syndication'] ?? '');

    $unviewable     = get_unviewable_forums();
    $inactiveforums = get_inactive_forums();
    $unexp          = array_filter(explode(',', $unviewable . ',' . $inactiveforums));

    $syndicate = $urlquery = $flist = [];

    if (is_array($forums) && !in_array('all', $forums, true)) {
        foreach ($forums as $fid) {
            if (ctype_digit((string)$fid) && !in_array($fid, $unexp, true)) {
                $syndicate[]  = $fid;
                $flist[$fid]  = true;
            }
        }
        if (!empty($syndicate)) {
            $urlquery[] = 'fid=' . implode(',', $syndicate);
        }
    }

    $json1check = $atom1check = $rss2check = '';
    if ($version === 'json') {
        $json1check = 'checked';
        $urlquery[] = 'type=' . $version;
    } elseif ($version === 'atom1.0') {
        $atom1check = 'checked';
        $urlquery[] = 'type=' . $version;
    } else {
        $rss2check = 'checked';
    }

    $limit      = empty($limit) ? 15 : min($limit, 50);
    $urlquery[] = 'limit=' . $limit;

    if (!empty($urlquery)) {
        $url .= '?' . implode('&amp;', $urlquery);
    }

    $forumselect = makesyndicateforums(0, '', true, '', $flist, $unexp);

    $plugins->run_hooks('misc_syndication_end');

    stdhead($lang->misc['syndication'] ?? 'Syndication');
    build_breadcrumb();

    echo '
    <div class="container-md py-4">
        <form method="post" action="misc.php?action=syndication">
        <div class="card">
            <div class="card-body">

                <p>' . ($lang->misc['syndication_note'] ?? '') . '</p>

                <div class="mb-3 p-3 border rounded bg-light">
                    <h6>' . htmlspecialchars($lang->misc['syndication_generated_url'] ?? 'Generated URL', ENT_QUOTES, 'UTF-8') . '</h6>
                    <code class="text-break">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</code>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">' . htmlspecialchars($lang->misc['syndication_forum'] ?? 'Forum', ENT_QUOTES, 'UTF-8') . '</label>
                    <div class="text-muted small mb-2">' . ($lang->misc['syndication_forum_desc'] ?? '') . '</div>
                    ' . $forumselect . '
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">' . htmlspecialchars($lang->misc['syndication_version'] ?? 'Version', ENT_QUOTES, 'UTF-8') . '</label>
                    <div class="text-muted small mb-2">' . ($lang->misc['syndication_version_desc'] ?? '') . '</div>
                    <div class="d-flex flex-column gap-1 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="version" value="rss2.0" id="v_rss2" ' . $rss2check . '>
                            <label class="form-check-label" for="v_rss2">' . htmlspecialchars($lang->misc['syndication_version_rss2'] ?? 'RSS 2.0', ENT_QUOTES, 'UTF-8') . '</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="version" value="atom1.0" id="v_atom" ' . $atom1check . '>
                            <label class="form-check-label" for="v_atom">' . htmlspecialchars($lang->misc['syndication_version_atom1'] ?? 'Atom 1.0', ENT_QUOTES, 'UTF-8') . '</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="version" value="json" id="v_json" ' . $json1check . '>
                            <label class="form-check-label" for="v_json">' . htmlspecialchars($lang->misc['syndication_version_json1'] ?? 'JSON', ENT_QUOTES, 'UTF-8') . '</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">' . htmlspecialchars($lang->misc['syndication_limit'] ?? 'Limit', ENT_QUOTES, 'UTF-8') . '</label>
                    <div class="text-muted small mb-2">' . ($lang->misc['syndication_limit_desc'] ?? '') . '</div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" class="form-control w-auto" name="limit"
                               value="' . (int)$limit . '" min="1" max="50" style="width:80px!important">
                        <span class="text-muted small">' . htmlspecialchars($lang->misc['syndication_threads_time'] ?? 'threads', ENT_QUOTES, 'UTF-8') . '</span>
                    </div>
                </div>

            </div>
            <div class="card-footer text-center">
                <button type="submit" class="btn btn-primary" name="make">
                    <i class="fas fa-rss me-2"></i>'
                    . htmlspecialchars($lang->misc['syndication_generate'] ?? 'Generate', ENT_QUOTES, 'UTF-8') . '
                </button>
            </div>
        </div>
        </form>
    </div>';

    stdfoot();

// ══════════════════════════════════════════════════════════════════════════
// ACTION: clearcookies
// ══════════════════════════════════════════════════════════════════════════
} elseif ($action === 'clearcookies') {

    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('misc_clearcookies');

    foreach ([
        'mybbuser', 'mybb[announcements]', 'mybb[lastvisit]', 'mybb[lastactive]',
        'collapsed', 'mybb[forumread]', 'mybb[threadsread]', 'mybbadmin',
        'mybblang', 'mybbtheme', 'multiquote', 'mybb[readallforums]',
        'coppauser', 'coppadob', 'mybb[referrer]',
    ] as $name) {
        my_unsetcookie($name);
    }

    redirect('index.php', $lang->misc['redirect_cookiescleared'] ?? '');
}

// ══════════════════════════════════════════════════════════════════════════
// HELPER: makesyndicateforums
// ══════════════════════════════════════════════════════════════════════════
function makesyndicateforums(
    int    $pid       = 0,
    string $selitem   = '',
    bool   $addselect = true,
    string $depth     = '',
    array  $flist     = [],
    array  $unexp     = []
): string {
    global $db, $forumcache, $permissioncache, $mybb, $forumlistbits, $lang, $CURUSER;

    if (!is_array($forumcache)) {
        $forumcache = [];
        $query = $db->simple_select(
            'forums', '*',
            "linkto = '' AND active != 0",
            ['order_by' => 'pid, disporder']
        );
        while ($forum = $db->fetch_array($query)) {
            $forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }
    }

    if (!is_array($permissioncache)) {
        $permissioncache = forum_permissions();
    }

    foreach ($forumcache[$pid] ?? [] as $main) {
        foreach ($main as $forum) {
            $perms = $permissioncache[$forum['fid']] ?? [];
            if (($perms['canview'] ?? 0) != 1) {
                unset($flist[$forum['fid']]);
                continue;
            }

            $passOk = $forum['password'] === ''
                || (isset($mybb->cookies['forumpass'][$forum['fid']])
                    && my_hash_equals(
                        $mybb->cookies['forumpass'][$forum['fid']],
                        md5($CURUSER['id'] . $forum['password'])
                    ));

            if ($passOk && !in_array($forum['fid'], $unexp, true)) {
                $selected      = isset($flist[$forum['fid']]) ? 'selected' : '';
                $forumlistbits .= '<option value="' . (int)$forum['fid'] . '" ' . $selected . '>'
                               . $depth . ' ' . htmlspecialchars($forum['name'], ENT_QUOTES, 'UTF-8')
                               . '</option>';
            }

            if (!empty($forumcache[$forum['fid']])) {
                makesyndicateforums((int)$forum['fid'], '', false, $depth . '&nbsp;&nbsp;&nbsp;&nbsp;', $flist, $unexp);
            }
        }
    }

    if (!$addselect) return '';

    $addsel = empty($flist) ? 'selected' : '';
    return '
    <select name="forums[]" size="10" multiple class="form-select">
        <option value="all" ' . $addsel . '>'
            . htmlspecialchars($lang->misc['syndicate_all_forums'] ?? 'All Forums', ENT_QUOTES, 'UTF-8') . '
        </option>
        <option value="all" disabled>──────────────────</option>
        ' . ($forumlistbits ?? '') . '
    </select>';
}