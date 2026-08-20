<?php
declare(strict_types=1);

define('IN_FORUM', true);
require_once 'global.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_parent_list.php';
require_once INC_PATH . '/class_moderation.php';
require_once INC_PATH . '/functions_forum_jump.php';

$moderation = new Moderation();

$lang->load('moderation');
$plugins->run_hooks('moderation_start');

$tid  = $mybb->get_input('tid',  MyBB::INPUT_INT);
$pid  = $mybb->get_input('pid',  MyBB::INPUT_INT);
$fid  = $mybb->get_input('fid',  MyBB::INPUT_INT);
$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
$modal = $mybb->get_input('modal', MyBB::INPUT_INT);

if ($CURUSER['id'] === 0) {
    print_no_permission();
}

if ($pid) {
    $post = get_post($pid);
    if (!$post) {
        error('error_invalidpost', $lang->error);
    }
    $tid = $post['tid'];
}

if ($tid) {
    $thread = get_thread($tid);
    if (!$thread) {
        error('error_invalidthread', $lang->error);
    }
    $fid = $thread['fid'];
}

if ($fid) {
    $modlogdata['fid'] = $fid;
    $forum = get_forum($fid);
    build_forum_breadcrumb($fid);
}

if ($pmid > 0) {
    $query = $db->sql_query_prepared("SELECT uid, subject, ipaddress, fromid FROM privatemessages WHERE pmid = ?", [$pmid]);
    $pm = $query ? $db->fetch_array($query) : null;
    if (!$pm) {
        error($lang->error_invalidpm, $lang->error);
    }
}

$action = $mybb->get_input('action');
$mybb->input['action'] = $action;

match ($action) {
    'reports'    => add_breadcrumb($lang->reported_posts),
    'allreports' => add_breadcrumb($lang->all_reported_posts),
    default      => null,
};

if (isset($thread)) {
    $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
    add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
    $modlogdata['tid'] = $thread['tid'];
}

if (isset($forum)) {
    check_forum_password($forum['fid']);
}

$log_multithreads_actions = [
    'do_multideletethreads', 'multiclosethreads', 'multiopenthreads',
    'multiapprovethreads', 'multiunapprovethreads', 'multirestorethreads',
    'multisoftdeletethreads', 'multistickthreads', 'multiunstickthreads',
    'do_multimovethreads',
];

if (in_array($action, $log_multithreads_actions)) {
    $tids = !empty($mybb->input['searchid'])
        ? getids($mybb->get_input('searchid'), 'search')
        : getids($fid, 'forum');
    $modlogdata['tids'] = (array)$tids;
    unset($tids);
}

$CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);

$loginbox = '<div class="alert bg-nav p-2 mb-3">
    <i class="fa-solid fa-user"></i> ' . $CURUSER['username'] . ' &mdash;
    <a href="member.php?action=logout&amp;logoutkey=' . $mybb->user['logoutkey'] . '" class="links">'
    . $lang->global['change_user'] . '</a>
</div>';

$allowable_moderation_actions = [
    'cancel_delayedmoderation', 'delayedmoderation',
];

if ($mybb->request_method !== 'post' && !in_array($action, $allowable_moderation_actions)) {
    print_no_permission();
}

$parser_options_default = [
    'allow_html'      => 0,
    'allow_mycode'    => 1,
    'allow_smilies'   => 1,
    'allow_imgcode'   => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

// ── Helper: build month <select> options ────────────────────────────────────
function build_month_options(int $selected, array $lang_months): string {
    $months = ['01','02','03','04','05','06','07','08','09','10','11','12'];
    $names  = array_values($lang_months);
    $html   = '';
    foreach ($months as $i => $m) {
        $sel  = ((int)$m === $selected) ? ' selected="selected"' : '';
        $html .= "<option value=\"{$m}\"{$sel}>{$names[$i]}</option>\n";
    }
    return $html;
}



// ── Moderation assets (SweetAlert2, toast, moderation JS) ───────────────────
$_mod_assets = '<link rel="stylesheet" href="' . htmlspecialchars($BASEURL) . '/include/templates/default/style/sweetalert2.min.css">' . PHP_EOL
    . '<script src="' . htmlspecialchars($BASEURL) . '/scripts/sweetalert2.min.js"></script>' . PHP_EOL
    . '<script src="' . htmlspecialchars($BASEURL) . '/scripts/toast.js"></script>' . PHP_EOL
    . '<script src="' . htmlspecialchars($BASEURL) . '/scripts/moderation.js"></script>';

// ── Begin switch ─────────────────────────────────────────────────────────────
switch ($action) {

    // ── Cancel delayed moderation ────────────────────────────────────────────
    case 'cancel_delayedmoderation':
        verify_post_check($mybb->get_input('my_post_key'));
        add_breadcrumb($lang->moderation['delayed_moderation']);
        $plugins->run_hooks('moderation_cancel_delayedmoderation');
        $db->sql_query_prepared("DELETE FROM delayedmoderation WHERE did = ?", [$mybb->get_input('did', MyBB::INPUT_INT)]);

        if ($tid === 0) {
            moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_delayed_moderation_cancelled']);
        } else {
            moderation_redirect(
                "moderation.php?action=delayedmoderation&amp;tid={$tid}&amp;my_post_key={$mybb->post_code}",
                $lang->moderation['redirect_delayed_moderation_cancelled']
            );
        }
        break;

    // ── Delayed moderation (show/process form) ───────────────────────────────
    case 'do_delayedmoderation':
    case 'delayedmoderation':
        verify_post_check($mybb->get_input('my_post_key'));

        $localized_time_offset = ($CURUSER['timezone'] + $CURUSER['dst']) * 3600;

        if (!$mybb->get_input('date_day', MyBB::INPUT_INT)) {
            $mybb->input['date_day'] = gmdate('d', TIMENOW + $localized_time_offset);
        }
        if (!$mybb->get_input('date_month', MyBB::INPUT_INT)) {
            $mybb->input['date_month'] = gmdate('m', TIMENOW + $localized_time_offset);
        }

        // Inline or single-thread mode
        if (!empty($mybb->input['tid'])) {
            $mybb->input['tids'] = $tid;
        } else {
            $tids = $mybb->get_input('inlinetype') === 'search'
                ? getids($mybb->get_input('searchid'), 'search')
                : getids($mybb->get_input('fid', MyBB::INPUT_INT), 'forum');

            if (count($tids) < 1) {
                stderr($lang->moderation['error_inline_nothreadsselected']);
            }
            $mybb->input['tids'] = $tids;
        }

        add_breadcrumb($lang->moderation['delayed_moderation']);

        $errors = [];
      
        $allowed_types = [
            'move', 'merge', 'removeredirects', 'removesubscriptions',
            'openclosethread', 'deletethread', 'stick', 'approveunapprovethread',
        ];

       

        $mybb->input['delayedmoderation'] = $mybb->get_input('delayedmoderation', MyBB::INPUT_ARRAY);
        $mybb->input['type'] = $mybb->get_input('type');

        // ── Process form submission ──────────────────────────────────────────
        if ($action === 'do_delayedmoderation' && $mybb->request_method === 'post') {

            if (!in_array($mybb->input['type'], $allowed_types)) {
                $mybb->input['type'] = '';
                $errors[] = $lang->moderation['error_delayedmoderation_unsupported_type'];
            }

            if ($mybb->input['type'] === 'move') {
                $delmod = $mybb->input['delayedmoderation'];
                if (!isset($delmod['method']) || !in_array($delmod['method'], ['move', 'redirect', 'copy'])) {
                    $mybb->input['delayedmoderation']['method'] = '';
                    $errors[] = $lang->moderation['error_delayedmoderation_unsupported_method'];
                }

                $newfid   = (int)($delmod['new_forum'] ?? 0);
                $newforum = get_forum($newfid);
                if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') {
                    $errors[] = 'Invalid forum';
                }
                if (($delmod['method'] ?? '') !== 'copy' && $fid === $newfid) {
                    $errors[] = $lang->moderation['error_movetosameforum'];
                }
            }

            $dateDay   = $mybb->get_input('date_day',   MyBB::INPUT_INT);
            $dateMonth = $mybb->get_input('date_month', MyBB::INPUT_INT);
            $dateYear  = $mybb->get_input('date_year',  MyBB::INPUT_INT);

            if ($dateDay < 1 || $dateDay > 31)     $errors[] = $lang->moderation['error_delayedmoderation_invalid_date_day'];
            if ($dateMonth < 1 || $dateMonth > 12) $errors[] = $lang->moderation['error_delayedmoderation_invalid_date_month'];
            if ($dateYear < gmdate('Y', TIMENOW + $localized_time_offset)) {
                $errors[] = $lang->moderation['error_delayedmoderation_invalid_date_year'];
            }

            $date_time_parts = explode(' ', $mybb->get_input('date_time'));
            $time_parts      = explode(':', (string)($date_time_parts[0] ?? ''));
            $hour = (int)($time_parts[0] ?? 0);
            $min  = (int)($time_parts[1] ?? 0);

            if (stristr($mybb->input['date_time'] ?? '', 'pm')) {
                $hour += 12;
                if ($hour >= 24) $hour = 0;
            }

            $rundate = gmmktime($hour, $min, (int)date('s', TIMENOW), $dateMonth, $dateDay, $dateYear)
                       - $localized_time_offset;

            if (!$errors) {
                if (is_array($mybb->input['tids'])) {
                    $mybb->input['tids'] = implode(',', $mybb->input['tids']);
                }

                $db->sql_query_prepared(
                    "INSERT INTO delayedmoderation (`type`,`delaydateline`,`uid`,`tids`,`fid`,`dateline`,`inputs`) VALUES (?,?,?,?,?,?,?)",
                    [
                        $mybb->input['type'],
                        (int)$rundate,
                        $CURUSER['id'],
                        $mybb->input['tids'],
                        $fid,
                        TIMENOW,
                        my_serialize($mybb->input['delayedmoderation']),
                    ]
                );

                $plugins->run_hooks('moderation_do_delayedmoderation');

                $rundate_format = my_datee('relative', $rundate, '', 2);

                if (!empty($mybb->input['tid'])) {
                    moderation_redirect(
                        get_thread_link($thread['tid']),
                        sprintf($lang->moderation['redirect_delayed_moderation_thread'], $rundate_format)
                    );
                } elseif ($mybb->get_input('inlinetype') === 'search') {
                    moderation_redirect(
                        get_forum_link($fid),
                        sprintf($lang->moderation['redirect_delayed_moderation_search'], $rundate_format)
                    );
                } else {
                    moderation_redirect(
                        get_forum_link($fid),
                        sprintf($lang->moderation['redirect_delayed_moderation_forum'], $rundate_format)
                    );
                }
            }

            // Rebuild selected state after errors
            $type_selected   = array_fill_keys($allowed_types, '');
            $type_selected[$mybb->get_input('type')] = 'checked="checked"';
            $method_selected = ['move' => '', 'redirect' => '', 'copy' => ''];
            if (isset($mybb->input['delayedmoderation']['method'])) {
                $method_selected[$mybb->input['delayedmoderation']['method']] = 'checked="checked"';
            }
            foreach (['redirect_expire', 'new_forum', 'subject', 'threadurl'] as $v) {
                $mybb->input['delayedmoderation'][$v] ??= '';
            }
            $mybb->input['delayedmoderation']['redirect_expire'] = (int)$mybb->input['delayedmoderation']['redirect_expire'];
            $mybb->input['delayedmoderation']['new_forum']       = (int)$mybb->input['delayedmoderation']['new_forum'];
            $mybb->input['delayedmoderation']['subject']         = htmlspecialchars_uni($mybb->input['delayedmoderation']['subject']);
            $mybb->input['delayedmoderation']['threadurl']       = htmlspecialchars_uni($mybb->input['delayedmoderation']['threadurl']);
            $forumselect = build_forum_jump('', $mybb->input['delayedmoderation']['new_forum'], 1, '', 0, true, '', 'delayedmoderation[new_forum]');

        } else {
            // Fresh form defaults
            $type_selected   = array_fill_keys($allowed_types, '');
            $type_selected['openclosethread'] = 'checked="checked"';
            $method_selected = ['move' => 'checked="checked"', 'redirect' => '', 'copy' => ''];
            $mybb->input['delayedmoderation']['redirect_expire'] = '';
            $mybb->input['delayedmoderation']['subject']         = $thread['subject'] ?? '';
            $mybb->input['delayedmoderation']['threadurl']       = '';
            $forumselect = build_forum_jump('', $fid, 1, '', 0, true, '', 'delayedmoderation[new_forum]');
        }

        $display_errors = !empty($errors) ? inline_error($errors) : '';
        $forum_cache    = $cache->read('forums');

        $actions = [
            'openclosethread'      => $lang->moderation['open_close_thread'],
            'deletethread'         => $lang->moderation['delete_thread'],
            'move'                 => $lang->moderation['move_copy_thread'],
            'stick'                => $lang->moderation['stick_unstick_thread'],
            'merge'                => $lang->moderation['merge_threads'],
            'removeredirects'      => $lang->moderation['remove_redirects'],
            'removesubscriptions'  => $lang->moderation['remove_subscriptions'],
            'approveunapprovethread' => $lang->moderation['approve_unapprove_thread'],
        ];


        // ── Delayed mods list ────────────────────────────────────────────────
        $delayedmods = '';
        $trow = alt_trow(true);

        if ($tid === 0) {
            $tids = $mybb->get_input('inlinetype') === 'search'
                ? getids($mybb->get_input('searchid'), 'search')
                : getids($fid, 'forum');

            $where_array  = [];
            $where_params = [];
            foreach ($tids as $like) {
                $where_array[] = match ($db->type) {
                    'pgsql', 'sqlite' => "','||d.tids||',' LIKE ?",
                    default           => "CONCAT(',',d.tids,',') LIKE ?",
                };
                $where_params[] = '%,' . $like . ',%';
            }
            $where_statement = implode(' OR ', $where_array);
        } else {
            $where_statement = match ($db->type) {
                'pgsql', 'sqlite' => "','||d.tids||',' LIKE ?",
                default           => "CONCAT(',',d.tids,',') LIKE ?",
            };
            $where_params = ['%,' . $tid . ',%'];
        }

        $query = $db->sql_query_prepared("
            SELECT d.*, u.username, f.name AS fname
            FROM delayedmoderation d
            LEFT JOIN users u ON (u.id = d.uid)
            LEFT JOIN forums f ON (f.fid = d.fid)
            WHERE {$where_statement}
            ORDER BY d.dateline DESC
            LIMIT 0, 20
        ", $where_params);

        while ($delayedmod = $db->fetch_array($query)) {
            $delayedmod['dateline']    = my_datee('normal', $delayedmod['delaydateline'], '', 2);
            $delayedmod['username']    = htmlspecialchars_uni($delayedmod['username']);
            $delayedmod['profilelink'] = build_profile_link($delayedmod['username'], $delayedmod['uid']);
            $delayedmod['action']      = $actions[$delayedmod['type']] ?? $delayedmod['type'];
            $info = '';

            if (strpos($delayedmod['tids'], ',') === false) {
                $delayed_thread           = get_thread($delayedmod['tids']);
                $delayed_thread['link']   = get_thread_link($delayed_thread['tid']);
                $delayed_thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($delayed_thread['subject']));
                $info .= '<strong>' . $lang->moderation['thread'] . '</strong> <a href="' . $delayed_thread['link'] . '">' . $delayed_thread['subject'] . '</a><br />';
            } else {
                $info .= '<strong>' . $lang->moderation['thread'] . '</strong> multiple_threads<br />';
            }

            if ($delayedmod['fname']) {
                $delayedmod['link']  = get_forum_link($delayedmod['fid']);
                $delayedmod['fname'] = htmlspecialchars_uni($delayedmod['fname']);
                $info .= '<strong>' . $lang->moderation['forum'] . '</strong> <a href="' . $delayedmod['link'] . '">' . $delayedmod['fname'] . '</a><br />';
            }

            $delayedmod['inputs'] = my_unserialize($delayedmod['inputs']);

            if ($delayedmod['type'] === 'move') {
                $delayedmod['link'] = get_forum_link($delayedmod['inputs']['new_forum']);
                $delayedmod['name'] = htmlspecialchars_uni($forum_cache[$delayedmod['inputs']['new_forum']]['name']);
                $info .= '<strong>' . $lang->moderation['new_forum'] . '</strong> <a href="' . $delayedmod['link'] . '">' . $delayedmod['name'] . '</a><br />';
            } elseif ($delayedmod['type'] === 'merge') {
                $delayedmod['subject']   = htmlspecialchars_uni($delayedmod['inputs']['subject']);
                $delayedmod['threadurl'] = htmlspecialchars_uni($delayedmod['inputs']['threadurl']);
            }

            $delayedmods .= $delayedmod['profilelink'] . '</div>
    <div class="col-lg align-self-center">' . $delayedmod['dateline'] . '</div>
    <div class="col-lg align-self-center">' . $delayedmod['action'] . '</div>
    <div class="col-lg align-self-center">' . $info . '</div>
    <div class="col-lg align-self-center"><a href="moderation.php?action=cancel_delayedmoderation&amp;tid=' . $tid . '&amp;fid=' . $fid . '&amp;did=' . $delayedmod['did'] . '&amp;my_post_key=' . $mybb->post_code . '">Cancel</a></div>
</div>';
            $trow = alt_trow();
        }

        if (!$delayedmods) {
            $delayedmods = '<div class="py-2 border-top">no_delayed_mods</div>';
        }

        // ── Build thread/inline vars ─────────────────────────────────────────
        $url = '';
        if ($mybb->get_input('tid', MyBB::INPUT_INT)) {
            $lang->threads = $lang->thread;
            $thread['link'] = get_thread_link($tid);
            $threads = '<div class="p-3 mt-3 mb-3 border rounded bg-light"><a href="' . $thread['link'] . '">' . $thread['subject'] . '</a></div>';
            $moderation_delayedmoderation_merge = '<input type="radio" name="type" class="form-check-input" value="merge" ' . $type_selected['merge'] . ' id="type_merge" onclick="toggleType();" />
                <label for="type_merge" class="form-check-label">' . $lang->moderation['merge_threads'] . '</label><br />
                <dd style="margin-top:4px;width:100%;" id="type_merge_expanded">
                    ' . $lang->moderation['new_subject'] . '<br />
                    <input type="text" class="form-control border form-control-sm" name="delayedmoderation[subject]" value="' . ($mybb->input['delayedmoderation']['subject'] ?? '') . '" size="40" /><br />
                    ' . $lang->moderation['thread_to_merge_with'] . '<br />
                    <input type="text" class="form-control border form-control-sm" name="delayedmoderation[threadurl]" value="' . ($mybb->input['delayedmoderation']['threadurl'] ?? '') . '" size="40" />
                    <br /><span class="text-muted">' . $lang->moderation['merge_with_note'] . '</span>';
        } else {
            $tids = $mybb->get_input('inlinetype') === 'search'
                ? getids($mybb->get_input('searchid'), 'search')
                : getids($fid, 'forum');

            if ($mybb->get_input('inlinetype') === 'search') {
                $url = htmlspecialchars_uni($mybb->get_input('url'));
            }
            if (count($tids) < 1) {
                stderr($lang->moderation['error_inline_nothreadsselected']);
            }
            $threads = sprintf($lang->moderation['threads_selected'], count($tids));
            $moderation_delayedmoderation_merge = '';
        }

        $redirect_expire = $mybb->get_input('redirect_expire');

        $moderation_delayedmoderation_move = '<br />' . $lang->moderation['method'] . '<br />
            <label class="form-check-label"><input type="radio" class="form-check-input" name="delayedmoderation[method]" value="move" ' . $method_selected['move'] . ' /> ' . $lang->moderation['method_move'] . '</label><br />
            <label class="form-check-label"><input type="radio" class="form-check-input" name="delayedmoderation[method]" value="redirect" ' . $method_selected['redirect'] . ' /> ' . $lang->moderation['method_move_redirect'] . '</label>
            <input type="text" class="form-control border form-control-sm mt-2" style="width:200px" name="delayedmoderation[redirect_expire]" value="' . $redirect_expire . '" size="3" /> ' . $lang->moderation['redirect_expire_note'] . '<br /><br />
            <label class="form-check-label"><input type="radio" class="form-check-input" name="delayedmoderation[method]" value="copy" ' . $method_selected['copy'] . ' /> ' . $lang->moderation['method_copy'] . '</label><br />';

        // Date day options
        $dateday = '';
        $sel_day = $mybb->get_input('date_day', MyBB::INPUT_INT);
        for ($day = 1; $day <= 31; ++$day) {
            $sel = ($sel_day === $day) ? ' selected="selected"' : '';
            $dateday .= "<option value=\"{$day}\"{$sel}>{$day}</option>";
        }

        $sel_month = $mybb->get_input('date_month', MyBB::INPUT_INT);
        $month_names = [
            $lang->moderation['january'], $lang->moderation['february'], $lang->moderation['march'],
            $lang->moderation['april'],   $lang->moderation['may'],       $lang->moderation['june'],
            $lang->moderation['july'],    $lang->moderation['august'],    $lang->moderation['september'],
            $lang->moderation['october'], $lang->moderation['november'],  $lang->moderation['december'],
        ];
        $datemonth = '';
        foreach (['01','02','03','04','05','06','07','08','09','10','11','12'] as $i => $m) {
            $sel = ((int)$m === $sel_month) ? ' selected="selected"' : '';
            $datemonth .= "<option value=\"{$m}\"{$sel}>{$month_names[$i]}</option>\n";
        }

        $dateyear = gmdate('Y', TIMENOW + $localized_time_offset);
        $datetime = gmdate($timeformat, TIMENOW + $localized_time_offset);

        $openclosethread = '<input type="radio" name="type" class="form-check-input" value="openclosethread" ' . $type_selected['openclosethread'] . ' id="type_openclosethread" onclick="toggleType();" />
            <label for="type_openclosethread" class="form-check-label">' . $lang->moderation['open_close_thread'] . '</label><br />';

        $deletethread = '<input type="radio" name="type" class="form-check-input" value="deletethread" ' . $type_selected['deletethread'] . ' id="type_deletethread" onclick="toggleType();" />
            <label for="type_deletethread" class="form-check-label">' . $lang->moderation['delete_thread'] . '</label><br />';

        $stickunstickthread = '<input type="radio" name="type" class="form-check-input" value="stick" ' . $type_selected['stick'] . ' id="type_stick_unstick_thread" />
            <label for="type_stick_unstick_thread" class="form-check-label">' . $lang->moderation['stick_unstick_thread'] . '</label><br />';

        $approveunapprovethread = '<input type="radio" name="type" class="form-check-input" value="approveunapprovethread" ' . $type_selected['approveunapprovethread'] . ' id="type_approveunapprovethread" onclick="toggleType();" />
            <label for="type_approveunapprovethread" class="form-check-label">' . $lang->moderation['approve_unapprove_thread'] . '</label>';

        $plugins->run_hooks('moderation_delayedmoderation');

        $delayedmoderation = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>{$SITENAME} - {$lang->moderation['delayed_moderation']} | Moderation Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/mod2.css">
</head>
<body>
<div class="container fade-in">
    {$display_errors}
    <div class="glass-card mb-4 overflow-hidden">
        <div class="premium-header">
            <i class="fas fa-clock fa-fw"></i>
            <div><h3>{$lang->moderation['delayed_mod_queue']}</h3><p>Scheduled actions * Pending approval</p></div>
            <i class="fas fa-calendar-week ms-auto opacity-50"></i>
        </div>
        <div class="p-3 p-md-4">
            <div class="d-none d-lg-flex row-header-custom align-items-center">
                <div class="col-3"><i class="fas fa-user-shield me-2"></i> {$lang->moderation['mod_username']}</div>
                <div class="col-3"><i class="fas fa-hourglass-half me-2"></i> {$lang->moderation['time_to_perform_action']}</div>
                <div class="col-2"><i class="fas fa-tasks me-2"></i> {$lang->moderation['mod_actions']}</div>
                <div class="col-2"><i class="fas fa-info-circle me-2"></i> {$lang->moderation['mod_information']}</div>
                <div class="col-2"><i class="fas fa-edit me-2"></i> {$lang->moderation['actions']}</div>
            </div>
            <div id="delayedQueueWrapper">{$delayedmods}</div>
        </div>
    </div>

    <form action="moderation.php" method="post" id="delayedModForm">
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        <input type="hidden" name="action" value="do_delayedmoderation" />
        <input type="hidden" name="url" value="{$url}" />
        <input type="hidden" name="tid" value="{$tid}" />
        <input type="hidden" name="fid" value="{$fid}" />

        <div class="glass-card mb-4">
            <div class="premium-header">
                <i class="fas fa-calendar-alt fa-fw"></i>
                <div><h3>{$lang->moderation['delayed_moderation']}</h3><p>Set a precise date &amp; time for automation</p></div>
            </div>
            <div class="p-4">
                <div class="mb-4" style="background:#eef3fa;border-radius:24px;padding:12px 20px;">
                    <i class="fas fa-info-circle me-2" style="color:#0d6efd;"></i> {$lang->moderation['delayed_moderation_desc']}
                </div>
                {$loginbox}
                <div class="mt-3 fw-semibold">{$threads}</div>
                <div class="mt-4">
                    <label class="form-label fw-semibold mb-2"><i class="fas fa-stopwatch me-1"></i> {$lang->moderation['run_moderation_time']}</label>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3 col-sm-6"><select name="date_day" class="form-select w-100">{$dateday}</select></div>
                        <div class="col-md-3 col-sm-6"><select name="date_month" class="form-select w-100">{$datemonth}</select></div>
                        <div class="col-md-3 col-sm-6"><input type="text" name="date_year" value="{$dateyear}" size="4" maxlength="4" class="form-control w-100" /></div>
                        <div class="col-md-3 col-sm-6"><input type="text" name="date_time" value="{$datetime}" class="form-control w-100" /></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card mb-4">
            <div class="premium-header">
                <i class="fas fa-gavel fa-fw"></i>
                <div><h3>{$lang->moderation['moderation_action']}</h3></div>
            </div>
            <div class="p-4">
                <script type="text/javascript" src="{$BASEURL}/scripts/toggle-type.js"></script>
                <div class="mb-3">
                    {$openclosethread}
                    {$deletethread}
                    {$stickunstickthread}
                </div>
                <div class="radio-card">
                    <input type="radio" name="type" value="move" {$type_selected['move']} id="type_movecopythread" />
                    <label for="type_movecopythread" class="fw-semibold flex-grow-1">{$lang->moderation['move_copy_thread']}</label>
                </div>
                <div id="type_movecopythread_expanded" style="display:none;">
                    <div class="expandable-panel">
                        {$forumselect}
                        <div class="mt-2">{$moderation_delayedmoderation_move}</div>
                    </div>
                </div>
                {$moderation_delayedmoderation_merge}
                <div class="radio-card mt-2">
                    <input type="radio" name="type" value="removeredirects" {$type_selected['removeredirects']} id="type_removeredirects" />
                    <label for="type_removeredirects" class="fw-semibold flex-grow-1">{$lang->moderation['remove_redirects']}</label>
                </div>
                <div class="radio-card">
                    <input type="radio" name="type" value="removesubscriptions" {$type_selected['removesubscriptions']} id="type_removesubscriptions" />
                    <label for="type_removesubscriptions" class="fw-semibold flex-grow-1">{$lang->moderation['remove_subscriptions']}</label>
                </div>
                {$approveunapprovethread}
               
            </div>
            <div class="card-footer text-center border-0 bg-transparent pb-4">
                <button type="submit" class="btn-gradient px-5 py-3">
                    <i class="fas fa-calendar-check me-2"></i> {$lang->moderation['save_delayed_moderation']}
                </button>
            </div>
        </div>
    </form>
</div>
<script type="text/javascript" src="{$BASEURL}/scripts/form-validation2.js"></script>
<link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/mod.css">
</body>
</html>
HTML;

        stdhead('Delayed Moderation');
        echo $delayedmoderation;
echo $_mod_assets;
stdfoot();
        break;

    // ── Open / close thread ──────────────────────────────────────────────────
    case 'openclosethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) stderr('error_thread_deleted');

        if ($thread['closed'] == 1) {
            $openclose = 'opened';
            $redirect  = 'redirect_openthread';
            $moderation->open_threads($tid);
        } else {
            $openclose = 'closed';
            $redirect  = 'redirect_closethread';
            $moderation->close_threads($tid);
        }

        log_moderator_action($modlogdata, sprintf($lang->moderation['mod_process'], $openclose));
        redirect(get_thread_link($thread['tid']), $redirect);
        break;

    // ── Stick / unstick thread ───────────────────────────────────────────────
    case 'stick':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) stderr('error_thread_deleted');

        $plugins->run_hooks('moderation_stick');

        if ($thread['sticky'] == 1) {
            $stuckunstuck = 'unstuck';
            $redirect     = 'redirect_unstickthread';
            $moderation->unstick_threads($tid);
        } else {
            $stuckunstuck = 'stuck';
            $redirect     = 'redirect_stickthread';
            $moderation->stick_threads($tid);
        }

        log_moderator_action($modlogdata, sprintf($lang->moderation['mod_process'], $stuckunstuck));
        redirect(get_thread_link($thread['tid']), $redirect);
        break;

    // ── Remove redirects ─────────────────────────────────────────────────────
    case 'removeredirects':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) error($lang->error_thread_deleted, $lang->error);

        $plugins->run_hooks('moderation_removeredirects');
        $moderation->remove_redirects($tid);
        log_moderator_action($modlogdata, $lang->redirects_removed);
        moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_redirectsremoved);
        break;

    // ── Delete thread ────────────────────────────────────────────────────────
    case 'do_deletethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        $plugins->run_hooks('moderation_do_deletethread');

        $modlogdata['thread_subject'] = $thread['subject'];
        log_moderator_action($modlogdata, sprintf($lang->moderation['thread_deleted'], $thread['subject']));
        $moderation->delete_thread($tid);
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_threaddeleted']);
        break;

    // ── Delete poll (confirmation) ───────────────────────────────────────────
    case 'deletepoll':
        add_breadcrumb('nav_deletepoll');
        $plugins->run_hooks('moderation_deletepoll');

        $q = $db->sql_query_prepared("SELECT pid FROM polls WHERE tid = ?", [$tid]);
        $poll = $q ? $db->fetch_array($q) : null;
        if (!$poll) stderr('error_invalidpoll');

        $deletepoll = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - {$lang->moderation['delete_poll']}</title>
   
    <style>
        .poll-card { max-width:500px; margin:0 auto; border:none; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,.1); }
        .poll-header { background:#dc3545; color:white; padding:25px; border-radius:15px 15px 0 0; text-align:center; }
        .btn-delete { background:#dc3545; border:none; padding:12px 30px; font-weight:600; transition:all .2s; }
        .btn-delete:hover { background:#c82333; transform:translateY(-2px); }
        .security-box { background:#f8f9fa; border-radius:10px; padding:20px; margin:20px 0; }
    </style>
</head>
<body>
    <div class="container mt-3">
        <div class="poll-card">
            <div class="poll-header">
                <div style="font-size:48px"><i class="fas fa-chart-bar"></i></div>
                <h4 class="mb-2">{$lang->moderation['delete_poll']}</h4>
                <p class="mb-0 opacity-75">This action cannot be undone</p>
            </div>
            <form action="moderation.php" method="post">
                <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
                <input type="hidden" name="action" value="do_deletepoll" />
                <input type="hidden" name="tid" value="{$tid}" />
                <input type="hidden" name="delete" value="1" />
                <div class="card-body p-4">
                    <div class="alert alert-danger mb-4">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Warning</h5>
                        <p class="mb-0">{$lang->moderation['delete_poll']}. Once deleted, poll cannot be restored.</p>
                    </div>
                    <div class="security-box">
                        <h6><i class="fas fa-shield-alt me-2"></i>Security Verification</h6>
                        <div class="mt-2">{$loginbox}</div>
                    </div>
                </div>
                <div class="card-footer bg-white py-3 text-end">
                    <a href="showthread.php?tid={$tid}" class="btn btn-outline-secondary me-2"><i class="fas fa-times me-1"></i> Cancel</a>
                    <button type="submit" class="btn btn-delete text-white" name="submit" value="{$lang->moderation['delete_poll']}">
                        <i class="fas fa-trash-alt me-1"></i> {$lang->moderation['delete_poll']}
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
HTML;
        stdhead('Delete Poll');
        echo $deletepoll;
echo $_mod_assets;
stdfoot();
        break;

    // ── Do delete poll ───────────────────────────────────────────────────────
    case 'do_deletepoll':
        verify_post_check($mybb->get_input('my_post_key'));
        if ($thread['visible'] == -1) stderr('error_thread_deleted');
        if (!isset($mybb->input['delete'])) stderr('redirect_pollnotdeleted');

        $q = $db->sql_query_prepared("SELECT pid FROM polls WHERE tid = ?", [$tid]);
        $poll = $q ? $db->fetch_array($q) : null;
        if (!$poll) stderr('error_invalidpoll');

        $plugins->run_hooks('moderation_do_deletepoll');
        log_moderator_action($modlogdata, sprintf($lang->moderation['poll_deleted'], $thread['subject']));
        $moderation->delete_poll($poll['pid']);
        redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_polldeleted']);
        break;

    // ── Approve thread ───────────────────────────────────────────────────────
    case 'approvethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) error($lang->error_thread_deleted, $lang->error);

        $thread = get_thread($tid);
        $plugins->run_hooks('moderation_approvethread');
        log_moderator_action($modlogdata, sprintf($lang->moderation['thread_approved'], $thread['subject']));
        $moderation->approve_threads($tid, $fid);
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_threadapproved']);
        break;

    // ── Unapprove thread ─────────────────────────────────────────────────────
    case 'unapprovethread':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) error($lang->error_thread_deleted, $lang->error);

        $thread = get_thread($tid);
        $plugins->run_hooks('moderation_unapprovethread');
        log_moderator_action($modlogdata, sprintf($lang->moderation['thread_unapproved'], $thread['subject']));
        $moderation->unapprove_threads($tid);
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_threadunapproved']);
        break;

    // ── Move thread ──────────────────────────────────────────────────────────
    case 'do_move':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();

        $moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
        $method = $mybb->get_input('method');

        if ($thread['visible'] == -1) stderr('error_thread_deleted');

        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') {
            stderr('error_invalidforum');
        }
        if ($method !== 'copy' && $thread['fid'] === $moveto) {
            stderr($lang->moderation['error_movetosameforum']);
        }

        $plugins->run_hooks('moderation_do_move');

        $expire = 0;
        if ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0) {
            $expire = TIMENOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400);
        }

        $newtid = $moderation->move_thread($tid, $moveto, $method, $expire);

        $log_msg = match ($method) {
            'copy'  => $lang->moderation['thread_copied'],
            default => $lang->moderation['thread_moved'],
        };
        log_moderator_action($modlogdata, $log_msg);
        redirect(get_thread_link($newtid), $lang->moderation['redirect_threadmoved']);
        break;

    // ── Merge threads ────────────────────────────────────────────────────────
    case 'do_merge':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) stderr('error_thread_deleted');

        $plugins->run_hooks('moderation_do_merge');

        $realurl = explode('#', $mybb->get_input('threadurl'));
        $mybb->input['threadurl'] = $realurl[0];
        $parameters = [];

        if (str_ends_with($mybb->input['threadurl'], 'html')) {
            preg_match('#thread-([0-9]+)?#i', $mybb->input['threadurl'], $threadmatch);
            preg_match('#post-([0-9]+)?#i',   $mybb->input['threadurl'], $postmatch);
            if (!empty($threadmatch[1])) $parameters['tid'] = $threadmatch[1];
            if (!empty($postmatch[1]))   $parameters['pid'] = $postmatch[1];
        } else {
            $splitloc = explode('.php', $mybb->input['threadurl']);
            $temp = explode('&', my_substr($splitloc[1], 1));
            foreach ($temp as $pair) {
                [$k, $v] = explode('=', $pair, 2) + [1 => ''];
                $parameters[$k] = $v;
            }
        }

        if (!empty($parameters['pid']) && empty($parameters['tid'])) {
            $mergetid = (int)get_post($parameters['pid'])['tid'];
        } else {
            $mergetid = (int)($parameters['tid'] ?? 0);
        }

        $mergethread = get_thread($mergetid);
        if (!$mergethread)        stderr('error_badmergeurl');
        if ($mergetid === $tid)   stderr('error_mergewithself');

        $subject = $mybb->get_input('subject') ?: $thread['subject'];
        $moderation->merge_threads($mergetid, $tid, $subject);
        log_moderator_action($modlogdata, $lang->moderation['thread_merged']);
        redirect(get_thread_link($tid), $lang->moderation['redirect_threadsmerged']);
        break;

    // ── Split thread ─────────────────────────────────────────────────────────
    case 'split':
        add_breadcrumb('nav_split');
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) stderr('error_thread_deleted');

        $query = $db->sql_query_prepared("
            SELECT p.*, u.*
            FROM posts p
            LEFT JOIN users u ON (p.uid = u.id)
            WHERE tid = ?
            ORDER BY dateline ASC, pid ASC
        ", [$tid]);

        if ($db->num_rows($query) <= 1) stderr('error_cantsplitonepost');

        $posts = '';
        while ($post = $db->fetch_array($query)) {
            $postdate         = my_datee('relative', $post['dateline']);
            $post['username'] = htmlspecialchars_uni($post['username']);
            $message          = $parser->parse_message($post['message'], $parser_options_default);
            $posts .= '<div class="mt-4 mb-4 border border-5 p-3 rounded">
                Posted by ' . $post['username'] . ' <span class="text-muted">' . $postdate . '</span>
                <input type="checkbox" class="form-check-input" name="splitpost[' . $post['pid'] . ']" value="1" />
                <br /><br />' . $message . '
            </div>';
        }

        clearinline($tid, 'thread');
        $forumselect = build_forum_jump('', $fid, 1, '', 0, true, '', 'moveto');
        $plugins->run_hooks('moderation_split');

        stdhead('Split Thread');
        echo '<form action="moderation.php" method="post">
            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
            <div class="container-md"><div class="card"><div class="card-body">
                <div class="legend mb-4">Split Thread</div>
                <div class="ps-3 pe-3">' . $loginbox . '</div>
                <div class="ps-3 pe-3 mt-3">New Subject:
                    <input type="text" class="form-control border form-control-sm mb-3" name="newsubject" value="[split] ' . $thread['subject'] . '" size="50" />
                    New Forum: ' . $forumselect . '
                </div>
                <div class="legend mt-4 mb-4">Posts to Split</div>
                <div class="ps-3 pe-3">' . $posts . '
                    <div class="mt-3 text-end"><input type="submit" class="btn btn-primary" name="submit" value="Split Thread" /></div>
                    <input type="hidden" name="action" value="do_split" />
                    <input type="hidden" name="tid" value="' . $tid . '" />
                </div>
            </div></div></div>
        </form>';
echo $_mod_assets;
stdfoot();
        break;

    // ── Do split ─────────────────────────────────────────────────────────────
    case 'do_split':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) stderr('error_thread_deleted');

        $plugins->run_hooks('moderation_do_split');

        $splitpost = $mybb->get_input('splitpost', MyBB::INPUT_ARRAY);
        if (empty($splitpost)) stderr('error_nosplitposts');

        $count_q = $db->sql_query_prepared("SELECT COUNT(*) AS totalposts FROM posts WHERE tid = ?", [$tid]);
        $count = $count_q ? $db->fetch_array($count_q) : null;
        if ($count['totalposts'] == 1)                           stderr('error_cantsplitonepost');
        if ($count['totalposts'] == count($splitpost))           stderr('error_cantsplitall');

        $moveto   = !empty($mybb->input['moveto']) ? $mybb->get_input('moveto', MyBB::INPUT_INT) : $fid;
        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') stderr('error_invalidforum');

        $pids  = [];
        $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ?", [$tid]);
        while ($query && ($post = $db->fetch_array($query))) {
            if (isset($splitpost[$post['pid']]) && $splitpost[$post['pid']] == 1) {
                $pids[] = $post['pid'];
            }
        }

        $newtid = $moderation->split_posts($pids, $tid, $moveto, $mybb->get_input('newsubject'));
        moderation_redirect(get_thread_link($newtid), 'The thread has been split successfully.<br />You will now be taken to the new thread');
        break;

    // ── Remove subscriptions ─────────────────────────────────────────────────
    case 'removesubscriptions':
        verify_post_check($mybb->get_input('my_post_key'));
        if (!is_mod($usergroups)) error_no_permission();
        if ($thread['visible'] == -1) error($lang->error_thread_deleted, $lang->error);

        $plugins->run_hooks('moderation_removesubscriptions');
        $moderation->remove_thread_subscriptions($tid, true);
        log_moderator_action($modlogdata, $lang->removed_subscriptions);
        moderation_redirect(get_thread_link($thread['tid']), $lang->redirect_removed_subscriptions);
        break;

    // ── Helper: get inline threads ───────────────────────────────────────────

    // ── Delete threads (inline) ──────────────────────────────────────────────
    case 'do_multideletethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads_input = $mybb->get_input('threads');
        $threadlist    = str_contains($threads_input, ',')
            ? explode(',', $threads_input)
            : explode('|', $threads_input);
        $threadlist    = array_map('intval', $threadlist);

        if (!is_mod($usergroups)) error_no_permission();

        foreach ($threadlist as $t) {
            $moderation->delete_thread($t);
        }

        log_moderator_action($modlogdata, $lang->moderation['multi_deleted_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');

        redirect(get_forum_link($fid), 'The selected threads have been deleted permanently.<br />You will now be returned to your previous location');
        break;

    // ── Helper closure for inline thread operations ──────────────────────────
    // ── Open threads (inline) ────────────────────────────────────────────────
    case 'multiopenthreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->error_inline_nothreadsselected, $lang->error);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->open_threads($threads);
        log_moderator_action($modlogdata, $lang->moderation['multi_opened_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsopened']);
        break;

    // ── Close threads (inline) ───────────────────────────────────────────────
    case 'multiclosethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->error_inline_nothreadsselected, $lang->error);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->close_threads($threads);
        log_moderator_action($modlogdata, $lang->moderation['multi_closed_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsclosed']);
        break;

    // ── Approve threads (inline) ─────────────────────────────────────────────
    case 'multiapprovethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->error_inline_nothreadsselected, $lang->error);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->approve_threads($threads, $fid);
        log_moderator_action($modlogdata, $lang->moderation['multi_approved_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        $cache->update_stats();
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsapproved']);
        break;

    // ── Unapprove threads (inline) ───────────────────────────────────────────
    case 'multiunapprovethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error('error_inline_nothreadsselected', $lang->error);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->unapprove_threads($threads, $fid);
        log_moderator_action($modlogdata, $lang->moderation['multi_unapproved_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        $cache->update_stats();
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsunapproved']);
        break;

    // ── Stick threads (inline) ───────────────────────────────────────────────
    case 'multistickthreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error('error_inline_nothreadsselected', $lang->error);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->stick_threads($threads);
        log_moderator_action($modlogdata, $lang->moderation['multi_stuck_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsstuck']);
        break;

    // ── Unstick threads (inline) ─────────────────────────────────────────────
    case 'multiunstickthreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) error($lang->error_inline_nothreadsselected, $lang->error);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->unstick_threads($threads);
        log_moderator_action($modlogdata, $lang->moderation['multi_unstuck_threads']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');
        moderation_redirect(get_forum_link($fid), $lang->moderation['redirect_inline_threadsunstuck']);
        break;

    // ── Move threads (inline) — show form ────────────────────────────────────
    case 'multimovethreads':
        add_breadcrumb('nav_multi_movethreads');

        $threads = !empty($mybb->input['searchid'])
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($fid, 'forum');

        if (count($threads) < 1) stderr('error_inline_nothreadsselected777');

        $inlineids    = implode('|', $threads);
        $thread_count = count($threads);

        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($fid, 'forum');

        $forumselect = build_forum_jump('', '', 1, '', 0, true, '', 'moveto');
        $return_url  = htmlspecialchars_uni($mybb->get_input('url'));

        $movethreads = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - {$lang->moderation['move_threads']}</title>
   
    <style>
        .method-option { cursor:pointer; border:1px solid #dee2e6; border-radius:.5rem; padding:1rem; margin-bottom:1rem; transition:all .2s; }
        .method-option:hover { background-color:#f8f9fa; }
        .method-option.selected { border-color:#0d6efd; background-color:#e7f1ff; }
        .radio-circle { width:20px; height:20px; border:2px solid #0d6efd; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-left:1rem; }
        .radio-circle-inner { width:10px; height:10px; border-radius:50%; background-color:#0d6efd; display:none; }
        .method-option.selected .radio-circle-inner { display:block; }
        .threads-badge { font-size:2.5rem; font-weight:bold; color:#0d6efd; }
        .redirect-input { max-width:120px; display:inline-block; }
    </style>
</head>
<body>
<div class="container mt-3">
    <div class="card">
        <div class="card-header text-center bg-primary bg-opacity-10">
            <i class="fas fa-exchange-alt fa-2x text-primary"></i>
            <h2 class="h4 mt-2">{$lang->moderation['move_threads']}</h2>
            <p class="mb-0 text-muted">Transfer multiple threads to another forum</p>
            <div class="mt-3">
                <div class="threads-badge"><i class="fas fa-layer-group"></i> {$thread_count}</div>
                <p class="text-muted small mb-0">Threads Selected</p>
            </div>
        </div>
        <form action="moderation.php" method="post" id="multiMoveForm">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <input type="hidden" name="action" value="do_multimovethreads" />
            <input type="hidden" name="fid" value="{$fid}" />
            <input type="hidden" name="threads" value="{$inlineids}" />
            <input type="hidden" name="url" value="{$return_url}" />
            <div class="card-body">
                <div class="mb-4">
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h5>
                            {$loginbox}
                        </div>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Bulk operation on <strong>{$thread_count}</strong> thread(s).
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-folder me-2 text-primary"></i>{$lang->moderation['new_forum']}</label>
                    {$forumselect}
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-cogs me-2 text-primary"></i>{$lang->moderation['method']}</label>
                    <div class="method-option" onclick="selectMethod('move')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-arrow-right fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1"><div class="fw-bold">{$lang->moderation['method_move']}</div></div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <input type="radio" name="method" value="move" class="d-none">
                    </div>
                    <div class="method-option selected" onclick="selectMethod('redirect')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-link fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1"><div class="fw-bold">{$lang->moderation['method_move_redirect']}</div></div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <div class="mt-2">
                            <input type="number" name="redirect_expire" class="form-control redirect-input" placeholder="Days" min="1" max="365">
                            <small class="text-muted ms-2">{$lang->moderation['redirect_expire_note']}</small>
                        </div>
                        <input type="radio" name="method" value="redirect" checked class="d-none">
                    </div>
                    <div class="method-option" onclick="selectMethod('copy')">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-copy fa-2x me-3 text-primary"></i>
                            <div class="flex-grow-1"><div class="fw-bold">{$lang->moderation['method_copy']}</div></div>
                            <div class="radio-circle"><div class="radio-circle-inner"></div></div>
                        </div>
                        <input type="radio" name="method" value="copy" class="d-none">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light text-end">
                <a href="{$return_url}" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left me-1"></i> Cancel</a>
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-exchange-alt me-1"></i> {$lang->moderation['move_threads']}
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function selectMethod(method) {
    document.querySelectorAll('.method-option').forEach(o => { o.classList.remove('selected'); o.querySelector('input[type="radio"]').checked = false; });
    const opt = [...document.querySelectorAll('.method-option')].find(o => o.querySelector('input[type="radio"]').value === method);
    if (opt) { opt.classList.add('selected'); opt.querySelector('input[type="radio"]').checked = true; }
}
selectMethod('redirect');
</script>
</body>
</html>
HTML;

        stdhead();
        echo $movethreads;
echo $_mod_assets;
stdfoot();
        break;

    // ── Do move threads (inline) ─────────────────────────────────────────────
    case 'do_multimovethreads':
        verify_post_check($mybb->get_input('my_post_key'));

        $moveto     = $mybb->get_input('moveto', MyBB::INPUT_INT);
        $method     = $mybb->get_input('method');
        $threadlist = explode('|', $mybb->get_input('threads'));

        if (!is_mod($usergroups)) error_no_permission();

        $tids = array_map('intval', $threadlist);

        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') stderr('error_invalidforum');

        $plugins->run_hooks('moderation_do_multimovethreads');
        log_moderator_action($modlogdata, 'Threads Moved / Copied');

        $expire = $mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0
            ? TIMENOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400)
            : 0;

        foreach ($tids as $t) {
            $moderation->move_thread($t, $moveto, $method, $expire);
        }

        moderation_redirect(get_forum_link($moveto), 'The selected threads have been moved or copied.<br />You will now be taken to the new forum.');
        break;

    // ── Delete posts (inline) ────────────────────────────────────────────────
    case 'do_multideleteposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $postlist = array_map('intval', explode(',', $mybb->get_input('posts')));
        if (!is_mod($usergroups)) error_no_permission();

        $tids = [];

        if ($postlist) {
            $ph = implode(',', array_fill(0, count($postlist), '?'));
            $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE firstpost IN ({$ph})", $postlist);
            while ($query && ($threadid = $db->fetch_field($query, 'tid'))) {
                $tids[] = $threadid;
            }
        }

        $deletecount = 0;
        foreach ($postlist as $p) {
            $moderation->delete_post($p);
            $deletecount++;
        }

        if (!empty($tids)) {
            foreach ($tids as $t) {
                $moderation->delete_thread((int)$t);
            }
            $url = get_forum_link($fid);
        } else {
            $numposts = $db->num_rows($db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ?", [$tid]));
            if (!$numposts) {
                $moderation->delete_thread($tid);
                $url = get_forum_link($fid);
            } else {
                $url = get_thread_link($thread['tid']);
            }
        }

        log_moderator_action($modlogdata, sprintf($lang->moderation['deleted_selective_posts'], $deletecount));
        redirect($url, 'redirect_postsdeleted');
        break;

    // ── Merge posts (inline) — show form ─────────────────────────────────────
    case 'multimergeposts':
        add_breadcrumb($lang->moderation['nav_multi_mergeposts']);

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        // Collect posts from other threads via cookies
        foreach ($mybb->cookies as $key => $value) {
            if (str_contains($key, 'inlinemod_thread') && $key !== "inlinemod_thread{$tid}") {
                foreach (explode('|', $value) as $p) {
                    $p = (int)$p;
                    if ($p) $posts[] = $p;
                }
                my_unsetcookie($key);
            }
        }

        if (empty($posts)) {
            stderr('Sorry, but you did not select any posts to perform inline moderation on, or your previous moderation session has expired. Please select some posts and try again.');
        }

        $postlist = '';
        $placeholders = implode(',', array_fill(0, count($posts), '?'));
        $query = $db->sql_query_prepared("
            SELECT p.*, u.*
            FROM posts p
            LEFT JOIN users u ON (p.uid = u.id)
            WHERE pid IN ({$placeholders})
            ORDER BY dateline ASC, pid ASC
        ", $posts);

        while ($post = $db->fetch_array($query)) {
            $postdate = my_datee('relative', $post['dateline']);
            $message  = $parser->parse_message($post['message'], $parser_options_default);
            $postlist .= '<div class="mt-4 mb-4 border border-5 p-3 rounded">
                Posted by ' . $post['username'] . ' <span class="text-muted">' . $postdate . '</span>
                <input type="checkbox" class="form-check-input" checked="checked" name="mergepost[' . $post['pid'] . ']" value="1" />
                <br /><br />' . $message . '
            </div>';
        }

        $inlineids  = implode('|', $posts);
        $post_count = count($posts);

        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');

        $return_url = htmlspecialchars_uni($mybb->get_input('url'));

        stdhead('Merge Posts');
               echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - Merge Posts</title>
    <style>
        .merge-header { background:linear-gradient(135deg,var(--bs-primary) 0%,#0d6efd 100%) !important; color:white; border-radius:10px 10px 0 0; position:relative; overflow:hidden; }
        .merge-header::after { content:''; position:absolute; inset:0; background:radial-gradient(circle at 85% -20%, rgba(255,255,255,.18), transparent 60%); }
        .merge-icon { width:80px; height:80px; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.3); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }

        .step-badge { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; background:var(--bs-primary); color:white; font-size:.8rem; font-weight:700; margin-right:10px; flex-shrink:0; }
        .step-title { display:flex; align-items:center; }

        .option-card { border:2px solid #e9ecef; border-radius:12px; transition:all .2s ease; cursor:pointer; margin-bottom:15px; position:relative; }
        .option-card:hover { border-color:#b6d4fe; transform:translateY(-2px); box-shadow:0 6px 16px rgba(13,110,253,.08); }
        .option-card.selected { border-color:var(--bs-primary); background:rgba(13,110,253,.05); box-shadow:0 6px 16px rgba(13,110,253,.12); }
        .option-check { position:absolute; top:12px; right:12px; width:22px; height:22px; border-radius:50%; background:var(--bs-primary); color:white; display:none; align-items:center; justify-content:center; font-size:.7rem; }
        .option-card.selected .option-check { display:flex; }
        .option-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-right:15px; flex-shrink:0; }
        .hr-option .option-icon { background:linear-gradient(135deg,var(--bs-primary) 0%,#0d6efd 100%); color:white; }
        .newline-option .option-icon { background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%); color:white; }

        .btn-merge { background:linear-gradient(135deg,var(--bs-primary) 0%,#0d6efd 100%); border:none; padding:12px 30px; font-weight:600; font-size:1.1rem; transition:all .2s ease; }
        .btn-merge:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(13,110,253,.35); }

        .preview-area { background:#f8f9fa; border:1px solid #e9ecef; border-radius:12px; padding:24px; margin-top:16px; }
        .preview-label { color:#6c757d; font-size:.78rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:14px; font-weight:600; }
        .preview-post { display:flex; gap:12px; align-items:flex-start; }
        .preview-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--bs-primary),#6ea8fe); flex-shrink:0; }
        .preview-bubble { background:white; border:1px solid #e9ecef; border-radius:10px; padding:10px 14px; font-size:.9rem; color:#495057; flex:1; }
        .separator-preview { border-top:2px dashed var(--bs-primary); margin:18px 0 18px 46px; opacity:.6; }
        .newline-preview { color:var(--bs-primary); font-size:.78rem; font-weight:600; margin:14px 0 14px 46px; }

        .posts-count-pill { font-size:.85rem; padding:.4rem .8rem; }
    </style>
</head>
<body>
<div class="container mt-3">
    <div class="card border-0 shadow-sm">
        <div class="merge-header p-5">
            <div class="text-center position-relative">
                <div class="merge-icon"><i class="fas fa-shuffle fa-2x"></i></div>
                <h1 class="h3 mb-2 fw-bold">Merge Posts</h1>
                <p class="mb-0 opacity-75">Combine selected posts into a single message</p>
            </div>
        </div>
        <form action="moderation.php" method="post">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <input type="hidden" name="action" value="do_multimergeposts" />
            <input type="hidden" name="tid" value="{$tid}" />
            <input type="hidden" name="url" value="{$return_url}" />
            <div class="card-body p-5">
                <div class="mb-5">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h5 class="h6 mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h5>
                            {$loginbox}
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="h6 mb-4 step-title"><span class="step-badge">1</span>Select Post Separator</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="option-card hr-option selected p-4" onclick="selectOption('hr')">
                                <div class="option-check"><i class="fas fa-check"></i></div>
                                <div class="d-flex align-items-center">
                                    <div class="option-icon"><i class="fas fa-minus fa-lg"></i></div>
                                    <div><h6 class="mb-1 fw-bold">Horizontal Rule</h6><p class="mb-0 text-muted small">Posts separated by a visible line</p></div>
                                </div>
                                <input type="radio" name="sep" value="hr" checked style="display:none;" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="option-card newline-option p-4" onclick="selectOption('new_line')">
                                <div class="option-check"><i class="fas fa-check"></i></div>
                                <div class="d-flex align-items-center">
                                    <div class="option-icon"><i class="fas fa-arrow-down fa-lg"></i></div>
                                    <div><h6 class="mb-1 fw-bold">New Line</h6><p class="mb-0 text-muted small">Posts separated by line breaks</p></div>
                                </div>
                                <input type="radio" name="sep" value="new_line" style="display:none;" />
                            </div>
                        </div>
                    </div>

                    <div class="preview-area">
                        <div class="preview-label">Preview</div>
                        <div class="preview-post">
                            <div class="preview-avatar"></div>
                            <div class="preview-bubble">First post content...</div>
                        </div>
                        <div class="separator-preview" id="hrPreview"></div>
                        <div class="newline-preview d-none" id="newlinePreview"><i class="fas fa-arrow-down me-1"></i>Merged as new paragraph</div>
                        <div class="preview-post">
                            <div class="preview-avatar"></div>
                            <div class="preview-bubble">Second post content...</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <h5 class="h6 mb-4 step-title">
                        <span class="step-badge">2</span>Posts to Merge
                        <span class="badge bg-primary posts-count-pill ms-2">{$post_count} posts selected</span>
                    </h5>
                    {$postlist}
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>Posts will be merged in chronological order.
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="{$return_url}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Cancel &amp; Return</a>
                    <button type="submit" class="btn btn-merge text-white" name="submit" value="Merge Posts">
                        <i class="fas fa-shuffle me-2"></i>Merge Posts
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
function selectOption(sep) {
    document.querySelectorAll('.option-card').forEach(function (card) {
        card.classList.remove('selected');
        card.querySelector('input[type="radio"]').checked = false;
    });

    const card = document.querySelector(sep === 'hr' ? '.hr-option' : '.newline-option');
    card.classList.add('selected');
    card.querySelector('input[type="radio"]').checked = true;

    document.getElementById('hrPreview').classList.toggle('d-none', sep !== 'hr');
    document.getElementById('newlinePreview').classList.toggle('d-none', sep !== 'new_line');
}
</script>
</body>
</html>
HTML;
        break;

    // ── Do merge posts (inline) ──────────────────────────────────────────────
    case 'do_multimergeposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $mergepost = $mybb->get_input('mergepost', MyBB::INPUT_ARRAY);
        if (count($mergepost) <= 1) stderr('error_nomergeposts');

        $postlist = array_map('intval', array_keys($mergepost));

        $masterpid = $moderation->merge_posts($postlist, (int)$tid, $mybb->input['sep'] ?? 'hr');
        log_moderator_action($modlogdata, $lang->moderation['merged_selective_posts']);
        redirect(get_post_link($masterpid) . "#pid{$masterpid}", 'redirect_inline_postsmerged');
        break;

    // ── Split posts (inline) — show form ─────────────────────────────────────
    case 'multisplitposts':
        add_breadcrumb($lang->moderation['nav_multi_splitposts']);

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) stderr($lang->moderation['error_inline_nopostsselected']);
        if (!is_mod($usergroups)) error_no_permission();

        $posts  = array_map('intval', $posts);
        $placeholders = implode(',', array_fill(0, count($posts), '?'));

        // Validate: no single-post threads
        $query = $db->sql_query_prepared("
            SELECT DISTINCT p.tid, COUNT(q.pid) as count
            FROM posts p LEFT JOIN posts q ON (p.tid=q.tid)
            WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid
        ", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) stderr($lang->moderation['error_cantsplitonepost']);
            $pcheck[] = $tcheck['tid'];
        }

        // Validate: not splitting all posts
        $query = $db->sql_query_prepared("
            SELECT DISTINCT p.tid, COUNT(q.pid) as count
            FROM posts p LEFT JOIN posts q ON (p.tid=q.tid)
            WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid
        ", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) stderr($lang->moderation['error_cantsplitall']);

        $inlineids = implode('|', $posts);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');

        $forumselect = build_forum_jump('', $fid, 1, '', 0, true, '', 'moveto');
        $return_url  = htmlspecialchars_uni($mybb->get_input('url'));

        $post_count  = count($posts);  // ← добавь здесь
		
		stdhead('Split Thread');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - {$lang->moderation['split_thread']}</title>
    <link rel="stylesheet" href="{$BASEURL}/include/templates/default/style/split.css">
</head>
<body>
<div class="container mt-3">
    <div class="split-card">
        <div class="split-header">
            <div class="split-icon"><i class="fas fa-arrows-split-up-and-left"></i></div>
            <h1 class="h3 mb-2">{$lang->moderation['split_thread']}</h1>
            <p class="mb-0 opacity-75">Create a new thread from selected posts</p>
            <div class="mt-4">
                <div class="post-count"><i class="fas fa-comments"></i> <span id="postsCount">{$post_count}</span></div>
                <p class="text-white opacity-75 mb-0">Posts Selected for Split</p>
            </div>
        </div>
        <form action="moderation.php" method="post" id="splitThreadForm">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <input type="hidden" name="action" value="do_multisplitposts" />
            <input type="hidden" name="tid" value="{$tid}" />
            <input type="hidden" name="posts" value="{$inlineids}" />
            <input type="hidden" name="url" value="{$return_url}" />
            <div class="card-body p-4">
                <div class="security-box">
                    <h5 class="fw-bold mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h5>
                    {$loginbox}
                </div>
                <div class="info-box">
                    <div class="d-flex">
                        <i class="fas fa-info-circle fa-2x me-3 mt-1 text-primary"></i>
                        <div>
                            <h6 class="mb-2">Split Operation Information</h6>
                            <p class="mb-0 small">Selected posts will be moved from the current thread to create a new separate thread.</p>
                        </div>
                    </div>
                </div>
                <div class="original-thread">
                    <h6 class="fw-bold mb-2"><i class="fas fa-file-alt me-2 text-primary"></i>Original Thread</h6>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-hashtag"></i></div>
                        <div><div class="small text-muted">Thread ID</div><div class="fw-bold">#{$tid}</div></div>
                    </div>
                </div>
                <div class="mb-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle me-2 text-primary"></i>{$lang->moderation['new_thread_info']}</h5>
                    <div class="input-group-custom">
                        <label class="form-label fw-bold text-primary">{$lang->moderation['new_subject']}</label>
                        <input type="text" class="form-control form-control-custom" name="newsubject"
                               value="{$lang->moderation['split_thread_subject']} {$thread['subject']}" required>
                    </div>
                    <div class="input-group-custom">
                        <label class="form-label fw-bold text-primary">{$lang->moderation['new_forum']}</label>
                        {$forumselect}
                    </div>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This operation affects <strong>{$post_count}</strong> post(s). Posts will be moved and original thread will retain remaining posts.
                </div>
            </div>
            <div class="card-footer bg-light py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="{$return_url}" class="btn btn-cancel text-white"><i class="fas fa-arrow-left me-2"></i>Cancel &amp; Return</a>
                    <button type="submit" class="btn btn-split text-white" name="submit" value="{$lang->moderation['split_thread']}">
                        <i class="fas fa-arrows-split-up-and-left me-2"></i>{$lang->moderation['split_thread']}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<script src="{$BASEURL}/scripts/split-thread.js"></script>
</body>
</html>
HTML;
echo $_mod_assets;
stdfoot();
        break;

    // ── Do split posts (inline) ──────────────────────────────────────────────
    case 'do_multisplitposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $plist = array_map('intval', explode('|', $mybb->get_input('posts')));
        if (!is_mod($usergroups)) error_no_permission();

        $posts = [];
        if (!empty($plist)) {
            $ph = implode(',', array_fill(0, count($plist), '?'));
            $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid IN ({$ph})", $plist);
            while ($query && ($p = $db->fetch_field($query, 'pid'))) $posts[] = $p;
        }
        if (empty($posts)) error($lang->error_inline_nopostsselected, $lang->error);

        $placeholders = implode(',', array_fill(0, count($posts), '?'));

        $query  = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) error($lang->error_cantsplitonepost, $lang->error);
            $pcheck[] = $tcheck['tid'];
        }

        $query   = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) error($lang->error_cantsplitall, $lang->error);

        $moveto   = isset($mybb->input['moveto']) ? $mybb->get_input('moveto', MyBB::INPUT_INT) : $fid;
        $newforum = get_forum($moveto);
        if (!$newforum || $newforum['type'] !== 'f' || $newforum['linkto'] !== '') error($lang->error_invalidforum, $lang->error);

        $newtid = $moderation->split_posts($posts, $tid, $moveto, $mybb->get_input('newsubject'));
        log_moderator_action($modlogdata, sprintf($lang->moderation['split_selective_posts'], implode(', ', $posts), $newtid));
        moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_threadsplit']);
        break;

    // ── Move posts (inline) — show form ──────────────────────────────────────
    case 'multimoveposts':
        add_breadcrumb($lang->moderation['nav_multi_moveposts']);

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) stderr($lang->moderation['error_inline_nopostsselected']);
        if (!is_mod($usergroups)) error_no_permission();

        $posts  = array_map('intval', $posts);
        $placeholders = implode(',', array_fill(0, count($posts), '?'));

        $query  = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) error($lang->moderation['error_cantsplitonepost'], $lang->error);
            $pcheck[] = $tcheck['tid'];
        }

        $query   = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) error($lang->moderation['error_cantmoveall'], $lang->error);

        $inlineids  = implode('|', $posts);
        $post_count = count($posts);

        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');

        $return_url = htmlspecialchars_uni($mybb->get_input('url'));

        stdhead();
        build_breadcrumb();
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$SITENAME} - Move Posts</title>
    <style>
        .move-header { background: linear-gradient(135deg, var(--bs-primary) 0%, #0b5ed7 100%); color: white; border-radius: 10px 10px 0 0; }
        .move-icon { width:80px; height:80px; background:rgba(255,255,255,.2); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
        .url-input-container { position:relative; margin-bottom:25px; }
        .url-input { padding-left:45px; padding-right:45px; border:2px solid #e9ecef; border-radius:10px; transition:all .3s; }
        .url-input:focus { border-color:var(--bs-primary); box-shadow:0 0 0 .2rem rgba(13,110,253,.25); }
        .url-icon { position:absolute; left:15px; top:50%; transform:translateY(-50%); color:var(--bs-primary); z-index:4; }
        .url-clear { position:absolute; right:15px; top:50%; transform:translateY(-50%); color:#6c757d; cursor:pointer; opacity:.5; transition:opacity .2s; z-index:4; }
        .url-clear:hover { opacity:1; color:#dc3545; }
        .info-box { background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); border-left:4px solid var(--bs-primary); border-radius:8px; padding:20px; margin-bottom:25px; }
        .info-icon { width:48px; height:48px; background:rgba(13,110,253,.1); border-radius:12px; display:flex; align-items:center; justify-content:center; margin-right:15px; color:var(--bs-primary); }
        .stats-box { background:white; border:2px solid #e9ecef; border-radius:10px; padding:15px; margin-bottom:20px; }
        .stat-item { display:flex; align-items:center; margin-bottom:10px; }
        .stat-item:last-child { margin-bottom:0; }
        .stat-icon { width:36px; height:36px; background:#f8f9fa; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:12px; color:var(--bs-primary); }
        .stat-label { font-size:.9rem; color:#6c757d; }
        .stat-value { font-weight:600; color:#495057; }
        .btn-move { background:linear-gradient(135deg,var(--bs-primary) 0%,#0b5ed7 100%); border:none; padding:12px 30px; font-weight:600; font-size:1.1rem; transition:all .3s; }
        .btn-move:hover { transform:translateY(-2px); box-shadow:0 5px 20px rgba(13,110,253,.3); }
        .post-count-badge { background:linear-gradient(135deg,var(--bs-primary) 0%,#0b5ed7 100%); color:white; padding:5px 15px; border-radius:20px; font-size:.9rem; font-weight:600; display:inline-block; margin-left:10px; }
        .thread-preview { background:white; border:2px dashed #dee2e6; border-radius:10px; padding:20px; margin-top:20px; display:none; }
        .thread-preview.show { display:block; }
        .thread-example { font-size:.85rem; color:#6c757d; margin-top:5px; padding:8px 12px; background:#f8f9fa; border-radius:6px; border-left:3px solid var(--bs-primary); }
    </style>
</head>
<body>
<div class="container mt-3">
    <div class="card">
        <div class="move-header p-5">
            <div class="text-center">
                <div class="move-icon"><i class="fas fa-arrow-right fa-2x"></i></div>
                <h1 class="h3 mb-2">Move Posts</h1>
                <p class="mb-0 opacity-75">Transfer selected posts to another thread</p>
            </div>
        </div>
        <form action="moderation.php" method="post" id="moveForm">
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <input type="hidden" name="action" value="do_multimoveposts" />
            <input type="hidden" name="tid" value="{$tid}" />
            <input type="hidden" name="posts" value="{$inlineids}" />
            <input type="hidden" name="url" value="{$return_url}" />
            <div class="card-body p-5">
                <div class="mb-5">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h5 class="h6 mb-3"><i class="fas fa-shield-alt me-2 text-primary"></i>Security Verification</h5>
                            {$loginbox}
                        </div>
                    </div>
                </div>
                <div class="info-box mb-4">
                    <div class="d-flex align-items-start">
                        <div class="info-icon"><i class="fas fa-info-circle fa-lg"></i></div>
                        <div>
                            <h5 class="h6 mb-2">How to move posts</h5>
                            <p class="mb-0 text-muted small">Copy the full URL of the destination thread and paste it below. Posts will be moved while preserving content, authors, and timestamps.</p>
                        </div>
                    </div>
                </div>
                <div class="stats-box mb-4">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-comments"></i></div>
                        <div><div class="stat-label">Posts to move</div><div class="stat-value">{$post_count} posts</div></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-hashtag"></i></div>
                        <div><div class="stat-label">Current thread ID</div><div class="stat-value">#{$tid}</div></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold mb-3">
                        <i class="fas fa-link me-2 text-primary"></i>Destination Thread URL
                        <span class="post-count-badge">{$post_count} posts selected</span>
                    </label>
                    <div class="url-input-container">
                        <i class="fas fa-link url-icon"></i>
                        <input type="text" class="form-control url-input" name="threadurl" id="threadUrl"
                               placeholder="https://yourforum.com/showthread.php?tid=123" autocomplete="off" required>
                        <i class="fas fa-times url-clear" id="clearUrl" onclick="document.getElementById('threadUrl').value='';document.getElementById('threadPreview').classList.remove('show');"></i>
                    </div>
                    <div class="thread-example">
                        <i class="fas fa-lightbulb me-2 text-warning"></i>
                        <strong>Example:</strong> https://example.com/forum/showthread.php?tid=456
                    </div>
                    <div class="thread-preview" id="threadPreview">
                        <div class="preview-title d-flex align-items-center fw-bold mb-2">
                            <i class="fas fa-eye me-2 text-primary"></i>Thread Preview
                        </div>
                        <div class="preview-content text-muted" id="previewContent">Enter a valid thread URL to see preview...</div>
                    </div>
                </div>
                <div class="alert alert-warning">
                    <div class="d-flex">
                        <i class="fas fa-exclamation-triangle fa-lg me-3 mt-1 text-warning"></i>
                        <div>
                            <h6 class="alert-heading mb-2">Important Notes</h6>
                            <ul class="mb-0 small">
                                <li>Posts will be removed from the current thread and added to the destination thread</li>
                                <li>The operation cannot be undone automatically</li>
                                <li>Make sure you have permission to move posts to the destination thread</li>
                                <li>All post metadata will be preserved</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light py-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-3 mb-md-0">
                        <a href="{$return_url}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Cancel &amp; Return</a>
                    </div>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-outline-primary" id="validateBtn">
                            <i class="fas fa-check-circle me-2"></i>Validate URL
                        </button>
                        <button type="submit" class="btn btn-move text-white" name="submit" value="Move Posts">
                            <i class="fas fa-arrow-right me-2"></i>Move Posts
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('validateBtn')?.addEventListener('click', function() {
    const url = document.getElementById('threadUrl').value.trim();
    const preview = document.getElementById('threadPreview');
    const content = document.getElementById('previewContent');
    if (!url) { alert('Please enter a thread URL first.'); return; }
    const tidMatch = url.match(/[?&]tid=(\d+)/) || url.match(/thread-(\d+)/);
    if (tidMatch) {
        preview.classList.add('show');
        content.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading thread info... Thread ID: <strong>' + tidMatch[1] + '</strong>';
    } else {
        preview.classList.add('show');
        content.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>Could not find thread ID in URL. Please check the URL format.</span>';
    }
});
document.getElementById('clearUrl')?.addEventListener('click', function() {
    document.getElementById('threadUrl').value = '';
    document.getElementById('threadPreview').classList.remove('show');
});
</script>
</body>
</html>
HTML;
echo $_mod_assets;
stdfoot();
        break;

    // ── Do move posts (inline) ───────────────────────────────────────────────
    case 'do_multimoveposts':
        verify_post_check($mybb->get_input('my_post_key'));
        $plugins->run_hooks('moderation_do_multimoveposts');

        $realurl = explode('#', $mybb->get_input('threadurl'));
        $mybb->input['threadurl'] = $realurl[0];
        $parameters = [];

        if (str_ends_with($mybb->input['threadurl'], 'html')) {
            preg_match('#thread-([0-9]+)?#i', $mybb->input['threadurl'], $threadmatch);
            preg_match('#post-([0-9]+)?#i',   $mybb->input['threadurl'], $postmatch);
            if (!empty($threadmatch[1])) $parameters['tid'] = $threadmatch[1];
            if (!empty($postmatch[1]))   $parameters['pid'] = $postmatch[1];
        } else {
            $splitloc = explode('.php', $mybb->input['threadurl']);
            foreach (explode('&', my_substr($splitloc[1], 1)) as $pair) {
                [$k, $v] = explode('=', $pair, 2) + [1 => ''];
                $parameters[$k] = $v;
            }
        }

        if (!empty($parameters['pid']) && empty($parameters['tid'])) {
            $newtid = (int)$db->fetch_field($db->sql_query_prepared("SELECT tid FROM posts WHERE pid = ?", [(int)$parameters['pid']]), 'tid');
        } else {
            $newtid = (int)($parameters['tid'] ?? 0);
        }

        $newthread = get_thread($newtid);
        if (!$newthread)    stderr($lang->moderation['error_badmovepostsurl']);
        if ($newtid === $tid) stderr($lang->moderation['error_movetoself']);

        $plist = array_map('intval', explode('|', $mybb->get_input('posts')));
        if (!is_mod($usergroups)) error_no_permission();

        $posts = [];
        if (!empty($plist)) {
            $ph = implode(',', array_fill(0, count($plist), '?'));
            $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid IN ({$ph})", $plist);
            while ($query && ($p = $db->fetch_field($query, 'pid'))) $posts[] = $p;
        }
        if (empty($posts)) stderr($lang->moderation['error_inline_nopostsselected']);

        $placeholders = implode(',', array_fill(0, count($posts), '?'));
        $query  = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) GROUP BY p.tid, p.pid", $posts);
        $pcheck = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ((int)$tcheck['count'] <= 1) stderr($lang->moderation['error_cantsplitonepost']);
            $pcheck[] = $tcheck['tid'];
        }

        $query   = $db->sql_query_prepared("SELECT DISTINCT p.tid, COUNT(q.pid) as count FROM posts p LEFT JOIN posts q ON (p.tid=q.tid) WHERE p.pid IN ({$placeholders}) AND q.pid NOT IN ({$placeholders}) GROUP BY p.tid, p.pid", [...$posts, ...$posts]);
        $pcheck2 = [];
        while ($tcheck = $db->fetch_array($query)) {
            if ($tcheck['count'] > 0) $pcheck2[] = $tcheck['tid'];
        }
        if (count($pcheck2) !== count($pcheck)) stderr($lang->moderation['error_cantmoveall']);

       $newtid = $moderation->split_posts($posts, $tid, (int)$newthread['fid'], $newthread['subject'], $newtid);
        log_moderator_action($modlogdata, sprintf($lang->moderation['move_selective_posts'], implode(', ', $posts), $newtid));
        moderation_redirect(get_thread_link($newtid), $lang->moderation['redirect_moveposts']);
        break;

    // ── Approve posts (inline) ───────────────────────────────────────────────
    case 'multiapproveposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) stderr($lang->moderation['error_inline_nopostsselected']);
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->approve_posts(array_map('intval', $posts));
        log_moderator_action($modlogdata, $lang->moderation['multi_approve_posts']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_inline_postsapproved']);
        break;

    // ── Unapprove posts (inline) ─────────────────────────────────────────────
    case 'multiunapproveposts':
        verify_post_check($mybb->get_input('my_post_key'));

        $posts = $mybb->get_input('inlinetype') === 'search'
            ? getids($mybb->get_input('searchid'), 'search')
            : getids($tid, 'thread');

        if (count($posts) < 1) error($lang->moderation['error_inline_nopostsselected'], 'error');
        if (!is_mod($usergroups)) error_no_permission();

        $moderation->unapprove_posts(array_map('intval', $posts));
        log_moderator_action($modlogdata, $lang->moderation['multi_unapprove_posts']);
        $mybb->get_input('inlinetype') === 'search'
            ? clearinline($mybb->get_input('searchid', MyBB::INPUT_INT), 'search')
            : clearinline($tid, 'thread');
        moderation_redirect(get_thread_link($thread['tid']), $lang->moderation['redirect_inline_postsunapproved']);
        break;

}

// ── Helper functions ──────────────────────────────────────────────────────────

function getids(string|int $id, string $type): array
{
    global $mybb;

    $newids = [];
    $cookie = "inlinemod_{$type}{$id}";

    if (!isset($mybb->cookies[$cookie])) {
        return $newids;
    }

    foreach (explode('|', $mybb->cookies[$cookie]) as $cookie_id) {
        if ($cookie_id === '') continue;
        if ($cookie_id === 'ALL') {
            $newids += getallids($id, $type);
        } else {
            $newids[] = (int)$cookie_id;
        }
    }

    return $newids;
}

function getallids(string|int $id, string $type): array
{
    global $db, $mybb, $CURUSER;

    $ids         = [];
    $removed_ids = [];
    $cookie      = "inlinemod_{$type}{$id}_removed";

    if (isset($mybb->cookies[$cookie])) {
        $removed_ids = explode('|', $mybb->cookies[$cookie]);
        if (!is_array($removed_ids)) $removed_ids = [];
    }

    if ($type === 'forum') {
        $query = $db->sql_query_prepared("SELECT tid FROM threads WHERE fid = ?", [(int)$id]);
        while ($query && ($tid = $db->fetch_field($query, 'tid'))) {
            if (!in_array($tid, $removed_ids)) $ids[] = $tid;
        }
    } elseif ($type === 'search') {
        $query     = $db->sql_query_prepared("SELECT resulttype, posts, threads FROM searchlog WHERE sid = ? AND uid = ?", [$id, $CURUSER['id']]);
        $searchlog = $query ? $db->fetch_array($query) : null;
        $ids       = explode(',', $searchlog['resulttype'] === 'posts' ? $searchlog['posts'] : $searchlog['threads']);

        if (is_array($ids)) {
            foreach ($ids as $key => $tid) {
                if (in_array($tid, $removed_ids)) unset($ids[$key]);
            }
        }
    }

    return $ids;
}

function clearinline(string|int $id, string $type): void
{
    my_unsetcookie("inlinemod_{$type}{$id}");
    my_unsetcookie("inlinemod_{$type}{$id}_removed");
}

function extendinline(string|int $id, string $type): void
{
    my_setcookie("inlinemod_{$type}{$id}",         '', TIMENOW + 3600);
    my_setcookie("inlinemod_{$type}{$id}_removed", '', TIMENOW + 3600);
}

function moderation_redirect(string $url, string $message = '', string $title = ''): void
{
    global $mybb, $BASEURL;

    if (!empty($mybb->input['url'])) {
        $url = htmlentities($mybb->input['url']);
    }

    if (!str_starts_with($url, $BASEURL . '/')) {
    if (str_starts_with($url, '/')) $url = my_substr($url, 1);
    $parts = explode('/', $url);
    $url = $BASEURL . '/' . end($parts);
}

    redirect($url, $message, $title);
}