<?php


// ─── Инициализация состояния коллапса категорий ───────────────────────────────
$collapse = $collapsed = $collapsedimg = $collapsedthead = [];

if (!empty($mybb->cookies['collapsed'])) {
    $colcookie = $mybb->cookies['collapsed'];
    $collapse  = explode('|', $colcookie);

    foreach ($collapse as $val) {
        $collapsed[$val . '_e']  = 'display: none;';
        $collapsedimg[$val]      = '_collapsed';
        $collapsedthead[$val]    = ' thead_collapsed';
    }
}

// ─────────────────────────────────────────────────────────────────────────────

function subforums_count(array $array = []): int
{
    $count = 0;
    foreach ($array as $array2) {
        $count += count($array2);
    }
    return $count;
}

// ─────────────────────────────────────────────────────────────────────────────

function build_forumbits(int $pid = 0, int $depth = 1): ?array
{
    global $db, $fcache, $moderatorcache, $forumpermissions, $BASEURL,
           $theme, $mybb, $collapsed, $lang, $showdepth, $plugins, $parser, $forum_viewers;
    static $private_forums;
	
    $forum_listing = '';

    if (empty($fcache[$pid]) || !is_array($fcache[$pid])) {
        return null;
    }

    $parent_counters = [
        'threads'          => 0,
        'posts'            => 0,
        'unapprovedposts'  => 0,
        'unapprovedthreads'=> 0,
        'viewers'          => 0,
    ];

    $forum_list = $comma = '';
    $donecount  = 0;

    foreach ($fcache[$pid] as $parent) {
        foreach ($parent as $forum) {
			
		

            $forum['viewers']          = 0;
            $subforums                 = $sub_forums = '';
            $lastpost_data             = ['lastpost' => 0, 'lastposter' => ''];
            $forum_viewers_text        = '';
            $forum_viewers_text_plain  = '';

            $permissions = $forumpermissions[$forum['fid']];

            if ($permissions['canview'] != 1 && '1' == '1') {
				
                continue;
            }

            $forum     = $plugins->run_hooks('build_forumbits_forum', $forum);
            $forum_url = get_forum_link($forum['fid']);

            $hideinfo         = false;
            $hidecounters     = false;
            $hidelastpostinfo = false;
            $showlockicon     = 0;

            if ($permissions['canview'] != 1
                || (isset($permissions['canviewthreads']) && $permissions['canviewthreads'] != 1)
            ) {
                $hideinfo = true;
            }

            if (isset($permissions['canonlyviewownthreads']) && $permissions['canonlyviewownthreads'] == 1) {
                $hidecounters = true;

                if (!is_array($private_forums)) {
                    $private_forums = $fids = [];

                    foreach ($fcache as $fcache_p) {
                        foreach ($fcache_p as $parent_p) {
                            foreach ($parent_p as $forum_p) {
                                if ($forumpermissions[$forum_p['fid']]['canonlyviewownthreads']) {
                                    $fids[] = $forum_p['fid'];
                                }
                            }
                        }
                    }

                    if (!empty($fids)) {
                        $fids  = implode(',', $fids);
                        $query = $db->simple_select(
                            'threads',
                            'tid, fid, subject, lastpost, lastposter, lastposteruid',
                            "uid = '{$CURUSER['id']}' AND fid IN ($fids) AND visible != '-2'",
                            ['order_by' => 'lastpost', 'order_dir' => 'desc']
                        );
                        while ($thread = $db->fetch_array($query)) {
                            if (!isset($private_forums[$thread['fid']])) {
                                $private_forums[$thread['fid']] = $thread;
                            }
                        }
                    }
                }

                if (!empty($private_forums[$forum['fid']]['lastpost'])) {
                    $pf = $private_forums[$forum['fid']];
                    if (!$pf['lastposteruid'] && !$pf['lastposter']) {
                        $pf['lastposter'] = 'guest';
                    }
                    $forum['lastpost'] = $pf['lastpost'];
                    $lastpost_data = [
                        'lastpost'        => $pf['lastpost'],
                        'lastpostsubject' => $pf['subject'],
                        'lastposter'      => $pf['lastposter'],
                        'lastposttid'     => $pf['tid'],
                        'lastposteruid'   => $pf['lastposteruid'],
                    ];
                }
            } else {
                if (!$forum['lastposteruid'] && !$forum['lastposter']) {
                    $forum['lastposter'] = 'guest';
                }
                $lastpost_data = [
                    'lastpost'        => $forum['lastpost'],
                    'lastpostsubject' => $forum['lastpostsubject'],
                    'lastposter'      => $forum['lastposter'],
                    'lastposttid'     => $forum['lastposttid'],
                    'lastposteruid'   => $forum['lastposteruid'],
                ];
            }

            if (!forum_password_validated($forum, true)) {
                $hideinfo     = true;
                $showlockicon = 1;
            }

            if (is_array($forum_viewers) && !empty($forum_viewers[$forum['fid']])) {
                $forum['viewers'] = $forum_viewers[$forum['fid']];
            }

            // Подфорумы
            if (isset($fcache[$forum['fid']])) {
                $forum_info = build_forumbits($forum['fid'], $depth + 1);

                $forum['threads']           += $forum_info['counters']['threads'];
                $forum['posts']             += $forum_info['counters']['posts'];
                $forum['unapprovedthreads'] += $forum_info['counters']['unapprovedthreads'];
                $forum['unapprovedposts']   += $forum_info['counters']['unapprovedposts'];

                if (!empty($forum_info['counters']['viewers'])) {
                    $forum['viewers'] += $forum_info['counters']['viewers'];
                }

                if (isset($forum_info['lastpost']['lastpost'])
                    && $forum_info['lastpost']['lastpost'] > $lastpost_data['lastpost']
                ) {
                    $lastpost_data = $forum_info['lastpost'];
                }

                $sub_forums = $forum_info['forum_list'];
            }

            if ($hideinfo || $hidelastpostinfo) {
                $lastpost_data = ['lastpost' => 0, 'lastposter' => ''];
            }

            if ((!isset($parent_lastpost) || $lastpost_data['lastpost'] > $parent_lastpost['lastpost'])
                && !$hideinfo
            ) {
                $parent_lastpost = $lastpost_data;
            }

            if (!$hideinfo && !$hidecounters) {
                $parent_counters['threads']           += $forum['threads'];
                $parent_counters['posts']             += $forum['posts'];
                $parent_counters['unapprovedposts']   += $forum['unapprovedposts'];
                $parent_counters['unapprovedthreads'] += $forum['unapprovedthreads'];
                if (!empty($forum['viewers'])) {
                    $parent_counters['viewers'] += $forum['viewers'];
                }
            }

            
			
			
			if ($depth > $showdepth) {
                continue;
            }

            $lightbulb = get_forum_lightbulb($forum, $lastpost_data, $showlockicon);
            $unapproved = $hideinfo
                ? ['unapproved_posts' => '', 'unapproved_threads' => '']
                : get_forum_unapproved($forum);

            // Санитизация
            foreach (['name', 'description'] as $field) {
                $forum[$field] = preg_replace('#&(?!\#[0-9]+;)#si', '&amp;', $forum[$field]);
                $forum[$field] = preg_replace('#&([^\#])(?![a-z1-4]{1,10};)#i', '&#038;$1', $forum[$field]);
            }


            // Глубина 3 — строчный список подфорумов
            if ($depth == 3) {
                $subforumsindex       = 2;
                $subforumsstatusicons = 1;

                if ($donecount < $subforumsindex) {
                    $statusicon = '';

                    if ($subforumsstatusicons == 1) {
                        $lightbulb['folder'] = 'mini' . $lightbulb['folder'];
                        $statusicon = '<div class="subforumicon subforum_' . $lightbulb['folder']
                            . ' ajax_mark_read ficons_' . $forum['fid'] . '"'
                            . ' title="' . $lightbulb['altonoff'] . '"'
                            . ' id="mark_read_' . $forum['fid'] . '">'
                            . '<i class="fa-solid fa-circle"></i></div>';
                    }

                    $forum_list .= $statusicon
                        . ' <a href="' . $forum_url . '" class="small-forum">' . $forum['name'] . '</a>&nbsp;&nbsp;&nbsp;&nbsp;';
                    $comma = $lang->global['comma'];
                }

                ++$donecount;
                if ($donecount == $subforumsindex && subforums_count($fcache[$pid]) > $donecount) {
                    $forum_list .= $comma . sprintf(
                        $lang->global['more_subforums'],
                        subforums_count($fcache[$pid]) - $donecount
                    );
                }
                continue;
            }

            // Тип форума: категория или обычный
            $forumcat = ($forum['type'] == 'c') ? '_cat' : '_forum';

            // ── Блок lastpost ──────────────────────────────────────────────
            $lastpost = '';

            if ($forum['linkto'] == '') {

                // Нет постов
                if ($lastpost_data['lastpost'] == 0 && !$hideinfo) {
                    $lastpost = '
<div class="lastpost-empty">
    <div class="d-none d-lg-block">
        <div class="d-flex align-items-center gap-3">
            <div class="position-relative">
                <div class="rounded-circle bg-light p-2" style="width:34px;height:34px;">
                    <i class="fa-regular fa-calendar-xmark position-absolute top-50 start-50 translate-middle text-secondary"></i>
                </div>
            </div>
            <div>
                <div class="small text-uppercase text-muted fw-semibold">Last Post</div>
                <div class="fw-bold text-secondary">Never</div>
            </div>
        </div>
    </div>
    <div class="d-block d-lg-none">
        <div class="bg-dark bg-opacity-10 rounded-3 p-2 text-center">
            <i class="fa-regular fa-calendar-xmark me-1 text-secondary"></i>
            <span class="small text-secondary">Never posted</span>
        </div>
    </div>
</div>';

                } elseif (!$hideinfo) {
                    // Есть посты — строим блок lastpost
                    $lastpost_date       = my_datee('relative', $lastpost_data['lastpost']);
                    $lastpost_data['lastposter'] = htmlspecialchars_uni($lastpost_data['lastposter']);
                    $lastpost_profilelink = build_profile_link($lastpost_data['lastposter'], $lastpost_data['lastposteruid']);
                    $lastpost_link        = get_thread_link($lastpost_data['lastposttid'], 0, 'lastpost');
                    $lastpost_subject     = $full_lastpost_subject = $parser->parse_badwords($lastpost_data['lastpostsubject']);

                    if (my_strlen($lastpost_subject) > 25) {
                        $lastpost_subject = my_substr($lastpost_subject, 0, 25) . '...';
                    }
                    $lastpost_subject      = htmlspecialchars_uni($lastpost_subject);
                    $full_lastpost_subject = htmlspecialchars_uni($full_lastpost_subject);

                    // ── Замена eval() #1 ──────────────────────────────────
                    // Было: eval("\$lastpost = \"".$templates->get("forumbit_depth{$depth}_forum_lastpost")."\";");
                    // Теперь: прямой рендер по глубине
                    if ($depth != 1) {
                        $lastpost = _forumbit_lastpost($depth, $lastpost_data, $lastpost_profilelink, $lastpost_link, $lastpost_subject, $lastpost_date);
                    }
                }

                // Просматривающие форум
                if ($forum['viewers'] > 0) {
                    $forum_viewers_text_plain = ($forum['viewers'] == 1)
                        ? 'viewing_one'
                        : sprintf('viewing_multiple', $forum['viewers']);
                    $forum_viewers_text = '<span class="smalltext text-muted" style="font-size:12px">'
                        . $forum_viewers_text_plain . '</span>';
                }
            }

            // Ссылка / защита паролем — скрываем данные
            if ($forum['linkto'] != '' || $hideinfo || $hidelastpostinfo) {
                $posts   = '-';
                $threads = '-';
                $lastpost = '
<!-- Десктоп -->
<div class="d-none d-lg-block">
    <div class="d-flex align-items-center gap-3 text-muted">
        <div class="flex-shrink-0">
            <div class="rounded-circle bg-warning bg-opacity-10 p-2 d-flex align-items-center justify-content-center"
                 style="width:34px;height:34px;">
                <i class="fa-solid fa-lock fa-sm text-warning"></i>
            </div>
        </div>
        <div class="flex-grow-1">
            <div class="small fw-semibold text-secondary">' . $lang->global['lastvisit_hidden'] . '</div>
            <div class="small text-muted">Privacy protected</div>
        </div>
    </div>
</div>
<!-- Мобайл -->
<div class="d-block d-lg-none">
    <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3">
        <div>
            <div class="small fw-semibold text-secondary">' . $lang->global['lastvisit_hidden'] . '</div>
            <div class="small text-muted">Privacy protected</div>
        </div>
        <div class="rounded-circle bg-warning bg-opacity-10 p-2 d-flex align-items-center justify-content-center"
             style="width:36px;height:36px;">
            <i class="fa-solid fa-lock text-warning"></i>
        </div>
    </div>
</div>';
            } else {
                $posts   = ts_nf($forum['posts']);
                $threads = ts_nf($forum['threads']);
            }

            // Список модераторов
            $modlist = '';
            // (раскомментируй блок ниже, если нужен вывод модераторов)
           
            $done_mods  = ['users' => [], 'groups' => []];
            $mods_parts = [];
            foreach (explode(',', $forum['parentlist']) as $mfid) {
                if (!isset($moderatorcache[$mfid]) || !is_array($moderatorcache[$mfid])) { continue; }
                foreach ($moderatorcache[$mfid] as $modtype) {
                    foreach ($modtype as $moderator) {
                        if ($moderator['isgroup']) {
                            if (in_array($moderator['id'], $done_mods['groups'])) { continue; }
                            $mods_parts[] = htmlspecialchars_uni($moderator['title']);
                            $done_mods['groups'][] = $moderator['id'];
                        } else {
                            if (in_array($moderator['id'], $done_mods['users'])) { continue; }
                            $mods_parts[] = '<a href="' . get_profile_link($moderator['id']) . '">'
                                . htmlspecialchars_uni($moderator['username']) . '</a>';
                            $done_mods['users'][] = $moderator['id'];
                        }
                    }
                }
            }
            if ($mods_parts) {
                $modlist = '<div class="small mt-1">moderated_by ' . implode(', ', $mods_parts) . '</div>';
            }
            

            $forum['description'] = '';

            // Состояние коллапса категории
            $expdisplay    = '';
            $collapsed_name = 'cat_' . $forum['fid'] . '_e';
            if (isset($collapsed[$collapsed_name]) && $collapsed[$collapsed_name] === 'display: none;') {
                $expcolimage = 'pic/collapse_collapsed.png';
                $expdisplay  = 'display: none;';
                $expthead    = ' thead_collapsed';
                $expaltext   = 'expcol_expand';
            } else {
                $expcolimage = 'pic/collapse.png';
                $expthead    = '';
                $expaltext   = 'expcol_collapse';
            }

            $bgcolor = alt_trow();

            // ── Замена eval() #2 ──────────────────────────────────────────
            // Было: eval("\$forum_list .= \"".$templates->get("forumbit_depth$depth$forumcat")."\";");
            // Теперь: прямой рендер по глубине и типу
            
			
			
// Подфорумы
if ($depth == 2 && $sub_forums) {
    $subforums = '<div class="mt-2">' . $sub_forums . '</div>';
} elseif ($depth == 1 && $sub_forums) {
    $subforums = $sub_forums;
}

$forum_list .= _forumbit_render($depth, $forumcat, $forum, $forum_url, $lightbulb, $unapproved,
    $subforums, $lastpost, $posts, $threads, $modlist, $forum_viewers_text,
    $expdisplay, $bgcolor);
        }
    }

    if (!isset($parent_lastpost)) { $parent_lastpost = 0; }
    if (!isset($lightbulb))       { $lightbulb = ''; }

    
	
	return [
        'forum_list' => $forum_list,
        'counters'   => $parent_counters,
        'lastpost'   => $parent_lastpost,
        'lightbulb'  => $lightbulb,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Прямой рендер lastpost по глубине (замена eval #1)
// ─────────────────────────────────────────────────────────────────────────────

function _forumbit_lastpost(
    int    $depth,
    array  $lastpost_data,
    string $lastpost_profilelink,
    string $lastpost_link,
    string $lastpost_subject,
    string $lastpost_date
): string {
    global $lang;
    // depth=2 → forumbit_depth2_forum_lastpost
    // depth=1 → пропускаем (вызывающий код проверяет $depth != 1)
    return '
<div class="d-none d-lg-block">
    <div class="row">
        <div class="col-auto align-self-center">
            <avatarep_uid_[' . $lastpost_data['lastposteruid'] . ']>
        </div>
        <div class="col align-self-center">
            <p class="fs-7 mb-0"><a href="' . $lastpost_link . '">' . $lastpost_subject . '</a></p>
            <p class="small text-muted mb-0 text-uppercase">' . $lastpost_date . '</p>
            <p class="small text-muted mb-0">by <span class="links">' . $lastpost_profilelink . '</span></p>
        </div>
    </div>
</div>
<div class="d-block d-lg-none">
    <div class="row py-3 bg-light mt-2 rounded">
        <div class="col align-self-center">
            <p class="fs-7 mb-0"><a href="' . $lastpost_link . '">' . $lastpost_subject . '</a></p>
            <p class="small text-muted mb-0 text-uppercase">' . $lastpost_date . '</p>
            <p class="small text-muted mb-0">by <span class="links">' . $lastpost_profilelink . '</span></p>
        </div>
        <div class="col-auto align-self-center">
            <avatarep_uid_[' . $lastpost_data['lastposteruid'] . ']>
        </div>
    </div>
</div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// Прямой рендер строки форума по глубине и типу (замена eval #2)
// ─────────────────────────────────────────────────────────────────────────────

function _forumbit_render(
    int    $depth,
    string $forumcat,       // '_cat' | '_forum'
    array  $forum,
    string $forum_url,
    array  $lightbulb,
    array  $unapproved,
    string $subforums,
    string $lastpost,
    string $posts,
    string $threads,
    string $modlist,
    string $forum_viewers_text,
    string $expdisplay,
    string $bgcolor
): string {
    global $BASEURL, $lang;
    // depth=1, type=cat → forumbit_depth1_cat
    if ($depth === 1 && $forumcat === '_cat') {
        return '
		<script type="text/javascript" src="' . $BASEURL . '/scripts/forum.js"></script>
        <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/forum.css">
<div class="category-card mb-4">
    <div class="card-header-custom rounded-top"
         style="background:linear-gradient(135deg,var(--thead-bg,#f8f9fa) 0%,color-mix(in srgb,var(--thead-bg,#f8f9fa) 80%,white 20%) 100%);
                border-bottom:3px solid rgba(var(--theme-rgb,13,110,253),.1);">
        <div class="expand-toggle float-end" onclick="toggleCategory(' . $forum['fid'] . ')">
            <i class="bi bi-chevron-down expand-icon" id="cat_' . $forum['fid'] . '_icon"></i>
        </div>
        <div class="header-content">
            <h5 class="category-title mb-1">
                <a href="' . $forum_url . '" class="category-link">
                    <i class="bi bi-folder2-open me-2" style="color:rgba(var(--theme-rgb,13,110,253),.7);"></i>
                    ' . $forum['name'] . '
                </a>
            </h5>
            <div class="category-description mt-2">
                <p class="text-muted mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    ' . $forum['description'] . '
                </p>
            </div>
        </div>
    </div>
    <div class="card-body-custom rounded-bottom"
         id="cat_' . $forum['fid'] . '_e"
         style="display:' . $expdisplay . ';background:linear-gradient(to bottom,white 0%,#fafbfc 100%);">
        <div class="subforums-container" id="subforums_' . $forum['fid'] . '">
            ' . $subforums . '
        </div>
    </div>
</div>';
    }

    // depth=2, type=cat → forumbit_depth2_cat
    if ($depth === 2 && $forumcat === '_cat') {
        return '
<div class="row p-0">
    <div class="col-1 align-self-center d-none d-md-block" style="width:50px">
        <div id="mark_read_' . $forum['fid'] . '" class="forum_status forum_' . $lightbulb['folder'] . ' ajax_mark_read"
             title="' . $lightbulb['altonoff'] . '"><i class="bi bi-chat-fill"></i></div>
    </div>
    <div class="col-3 align-self-center p-2" style="width:500px">
        <strong><a href="' . $forum_url . '">' . $forum['name'] . '</a></strong>'
        . $forum_viewers_text
        . '<div class="smalltext" style="font-size:16px;">'
        . $forum['description'] . $modlist . $subforums . '</div>
    </div>
    <div class="col align-self-center text-center d-none d-lg-block" style="width:auto">
        ' . $threads . $unapproved['unapproved_threads'] . '<br />
        <p class="text-muted" style="font-size:13px;margin-top:0;">threads</p>
    </div>
    <div class="col align-self-center text-center d-none d-lg-block" style="width:auto">
        ' . $posts . $unapproved['unapproved_posts'] . '
        <p class="text-muted" style="font-size:13px;margin-top:0;">posts</p>
    </div>
    <div class="col-2 align-self-center text-right p-2" style="width:250px">
        ' . $lastpost . '
    </div>
</div><br />';
    }

    // depth=2, type=forum → forumbit_depth2_forum
    if ($depth === 2 && $forumcat === '_forum') {
        return '
<div class="forum-item" data-fid="' . $forum['fid'] . '">
    <div class="forum-card p-4 rounded-4 shadow-sm mb-3 border-0">
        <div class="row g-4 align-items-center">

            <!-- Иконка статуса -->
            <div class="col-auto">
                <div id="mark_read_' . $forum['fid'] . '"
                     class="forum-status-indicator forum_status forum_' . $lightbulb['folder'] . ' ajax_mark_read"
                     onclick="toggleForumRead(' . $forum['fid'] . ')"
                     data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true"
                     data-bs-placement="top"
                     data-bs-title="<i class=\'fa-solid fa-info-circle me-2\'></i>Forum Status"
                     data-bs-content="<div class=\'popover-content\'>
                         <div class=\'mb-2\'><strong>' . $lightbulb['altonoff'] . '</strong></div>
                         <div class=\'small text-muted\'>Click to mark as read</div>
                     </div>">
                    <div class="status-circle pulse-hover">
                        <i class="status-icon fa-solid fa-circle"></i>
                        <span class="new-badge"><i class="fa-solid fa-bolt"></i></span>
                    </div>
                </div>
            </div>

            <!-- Основное содержимое -->
            <div class="col">
                <div class="forum-content">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <h4 class="forum-title mb-0">
                            <a href="' . $forum_url . '" class="forum-link" data-forum="' . $forum['fid'] . '"
                               data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true"
                               data-bs-placement="top"
                               data-bs-title="<i class=\'fa-solid fa-folder-open me-2\'></i>' . $forum['name'] . '"
                               data-bs-content="<div class=\'popover-content\'>
                                   <div class=\'mb-2\'><strong>Description:</strong> ' . $forum['description'] . '</div>
                                   <div class=\'small text-muted\'>Click to open Forum</div>
                               </div>">
                                <i class="forum-icon fa-solid fa-folder-open me-2"></i>
                                <span class="forum-name">' . $forum['name'] . '</span>
                            </a>
                        </h4>
                        <div class="forum-badges d-flex gap-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary"
                                  data-bs-toggle="popover" data-bs-trigger="hover"
                                  data-bs-placement="top"
                                  data-bs-title="<i class=\'fa-solid fa-users me-2\'></i>Online Users">
                                <i class="fa-solid fa-users me-1"></i>' . $forum_viewers_text . '
                            </span>
                            <span class="badge bg-warning bg-opacity-20 text-warning"
                                  data-bs-toggle="popover" data-bs-trigger="hover"
                                  data-bs-placement="top"
                                  data-bs-title="<i class=\'fa-solid fa-clock me-2\'></i>Awaiting Moderation">
                                <i class="fa-solid fa-clock me-1"></i>' . $unapproved['unapproved_threads'] . '
                            </span>
                        </div>
                    </div>
                    <div class="forum-description mb-3">
                        <p class="text-muted mb-2">
                            <i class="fa-solid fa-info-circle me-2 opacity-75"></i>' . $forum['description'] . '
                        </p>
                        <div class="moderators mt-2">
                            <span class="text-muted small">
                                <i class="fa-solid fa-user-shield me-1"></i>Moderators: ' . $modlist . '
                            </span>
                        </div>
                    </div>
                    <div class="subforums-section mt-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa-solid fa-sitemap me-2 text-muted"></i>
                            <span class="small text-muted">Sub Forums:</span>
                        </div>
                        <div class="subforums-grid">' . $subforums . '</div>
                    </div>
                </div>
            </div>

            <!-- Статистика (десктоп) -->
            <div class="col-lg-auto d-none d-lg-block">
                <div class="forum-stats">
                    <div class="stats-grid">
                        <div class="stat-item"
                             data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="top"
                             data-bs-title="<i class=\'fa-solid fa-comments me-2\'></i>Threads Statistics">
                            <div class="stat-value">
                                <span class="count">' . $threads . '</span>
                                <span class="unapproved-count">+' . $unapproved['unapproved_threads'] . '</span>
                            </div>
                            <div class="stat-label">
                                <i class="fa-solid fa-comments me-1"></i>
                                <span class="small">Threads</span>
                            </div>
                        </div>
                        <div class="stat-item"
                             data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="top"
                             data-bs-title="<i class=\'fa-solid fa-comment-dots me-2\'></i>Posts Statistics">
                            <div class="stat-value">
                                <span class="count">' . $posts . '</span>
                                <span class="unapproved-count">+' . $unapproved['unapproved_posts'] . '</span>
                            </div>
                            <div class="stat-label">
                                <i class="fa-solid fa-comment-dots me-1"></i>
                                <span class="small">Posts</span>
                            </div>
                        </div>
                        <div class="stat-item last-post"
                             data-bs-toggle="popover" data-bs-trigger="hover" data-bs-placement="top"
                             data-bs-html="true"
                             data-bs-title="<i class=\'fa-solid fa-clock me-2\'></i>Last Post">
                            <div class="last-post-content">' . $lastpost . '</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Мобильная статистика -->
        <div class="row d-lg-none mt-4">
            <div class="col-12">
                <div class="forum-mobile-stats">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="mobile-stat">
                                <div class="mobile-stat-value">' . $threads . '</div>
                                <div class="mobile-stat-label small text-muted">
                                    <i class="fa-solid fa-comments me-1"></i> Threads
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mobile-stat">
                                <div class="mobile-stat-value">' . $posts . '</div>
                                <div class="mobile-stat-label small text-muted">
                                    <i class="fa-solid fa-comment-dots me-1"></i> Posts
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mobile-lastpost mt-2 pt-3 border-top">
                                <div class="small text-muted mb-1">
                                    <i class="fa-solid fa-clock me-1"></i> Last Post
                                </div>
                                <div class="last-post-content">' . $lastpost . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="forum-decoration">
            <div class="decoration-line"></div>
            <div class="decoration-dots"><span></span><span></span><span></span></div>
        </div>
    </div>
</div>';
    }

    // Fallback: depth=1, type=forum (forumbit_depth1_forum_lastpost — не используется в этом файле)
    return '';
}

// ─────────────────────────────────────────────────────────────────────────────

function get_forum_lightbulb(array $forum, array $lastpost, int $locked = 0): array
{
    global $mybb, $lang, $db, $unread_forums, $CURUSER, $threadreadcut;

    if (!empty($forum['linkto'])) {
        return ['folder' => 'offlink', 'altonoff' => $lang->global['forum_redirect']];
    }

    if ($forum['open'] == 0 || $locked) {
        return ['folder' => 'offclose', 'altonoff' => $lang->global['forum_closed']];
    }

    $forum_read = 0;

    if (!empty($forum['lastread'])) {
        $forum_read = $forum['lastread'];
    } elseif (!empty($mybb->cookies['mybb']['readallforums'])) {
        $forum_read = $mybb->cookies['mybb']['lastvisit'];
    } else {
        $threadcut = TIMENOW - 60 * 60 * 24 * $threadreadcut;

        if (!$CURUSER['id'] && isset($mybb->cookies['mybb']['forumread'])) {
            $forum_read = my_get_array_cookie('forumread', $forum['fid']);
        } elseif ($CURUSER['id'] && $threadreadcut > 0 && $threadcut > $lastpost['lastpost']) {
            $forum_read = $lastpost['lastpost'] + 1;
        }
    }

    if (!$forum_read) {
        $forum_read = $CURUSER['lastvisit'];
    }

    if ($lastpost['lastpost'] > $forum_read && $lastpost['lastpost'] != 0) {
        $unread_forums++;
        return ['folder' => 'on', 'altonoff' => $lang->global['new_posts']];
    }

    return ['folder' => 'off', 'altonoff' => $lang->global['no_new_posts']];
}

// ─────────────────────────────────────────────────────────────────────────────

function get_forum_unapproved(array $forum): array
{
    global $lang, $usergroups;

    $unapproved_posts   = '';
    $unapproved_threads = '';

    if (!is_mod($usergroups)) {
        return ['unapproved_posts' => '', 'unapproved_threads' => ''];
    }

    if ($forum['unapprovedposts']) {
        $label = ($forum['unapprovedposts'] > 1)
            ? sprintf($lang->index['forum_unapproved_posts_count'],  $forum['unapprovedposts'])
            : sprintf($lang->index['forum_unapproved_post_count'], 1);
        $unapproved_posts = '<span title="' . $label . '">(' . ts_nf($forum['unapprovedposts']) . ')</span>';
    }

    if ($forum['unapprovedthreads']) {
        $label = ($forum['unapprovedthreads'] > 1)
            ? sprintf($lang->index['forum_unapproved_threads_count'], $forum['unapprovedthreads'])
            : sprintf($lang->index['forum_unapproved_thread_count'], 1);
        $unapproved_threads = '<span title="' . $label . '">(' . ts_nf($forum['unapprovedthreads']) . ')</span>';
    }

    return [
        'unapproved_posts'   => $unapproved_posts,
        'unapproved_threads' => $unapproved_threads,
    ];
}