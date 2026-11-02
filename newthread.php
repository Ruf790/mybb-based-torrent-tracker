<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'newthread.php');
define('IN_FORUM', true);



require_once 'global.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_compose.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_subscription_method.php';
require_once INC_PATH . '/datahandler.php';
require_once 'cache/smilies.php';

$lang->load('newthread');
$lang->load('post3');

$tid = $pid = 0;
$mybb->input['action'] = $mybb->get_input('action');
$mybb->input['tid']    = $mybb->get_input('tid', MyBB::INPUT_INT);
$mybb->input['pid']    = $mybb->get_input('pid', MyBB::INPUT_INT);

// ── Draft / edit draft ────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'editdraft'
    || ($mybb->get_input('savedraft') && $mybb->input['tid'])
    || ($mybb->input['tid'] && $mybb->input['pid']))
{
    $thread = get_thread($mybb->input['tid']);
    $query  = $db->sql_query_prepared(
        "SELECT * FROM posts WHERE tid = ? AND visible = -2 ORDER BY dateline, pid LIMIT 1",
        [(int)$mybb->input['tid']]
    );
    $post   = $db->fetch_array($query);

    if (!$thread || !$post || $thread['visible'] != -2 || $thread['uid'] != $CURUSER['id']) {
        stderr('invalidthread');
    }

    $pid = $post['pid'];
    $fid = $thread['fid'];
    $tid = $thread['tid'];
   
   $editdraftpid = '<input type="hidden" name="pid" value="'.$pid.'" />';
   
   
} else {
    $fid         = $mybb->get_input('fid', MyBB::INPUT_INT);
    $editdraftpid = '';
}

// ── Forum validation ──────────────────────────────────────────────────────────
$forum = get_forum($fid);

if (!$forum) { stderr($lang->global['error_invalidforum'] ?? 'Invalid forum.', $SITENAME . ' - Forum Not Found', 404, 'forum'); }



build_forum_breadcrumb($fid);
add_breadcrumb($lang->newthread['nav_newthread']);

$forumpermissions = forum_permissions($fid);

if ($forum['open'] == 0 || $forum['type'] !== 'f' || $forum['linkto'] !== '') {
    stderr($lang->global['error_closedinvalidforum'], $SITENAME . ' - Access Denied', 403, '403');
}
if ($forumpermissions['canview'] == 0 || $forumpermissions['canpostthreads'] == 0) {
    print_no_permission();
}

check_forum_password($forum['fid']);

// ── Editor ───────────────────────────────────────────────────────────────────
require_once INC_PATH . '/editor.php';
$editor     = insert_bbcode_editor($smilies, $BASEURL, 'message');
$codebuttons = $editor['toolbar'] . $editor['modal'];

// ── Login/change user box ─────────────────────────────────────────────────────
if ($CURUSER['id'] != 0) {
    $CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);
    
	$loginbox = '
	
	<div class="alert bg-nav p-2 mb-3">
	

<i class="fa-solid fa-user"></i> '.$CURUSER['username'].' &mdash; <a href="member.php?action=logout&amp;logoutkey='.$mybb->user['logoutkey'].'" class="links">'.$lang->global['change_user'].'</a>
	
</div>
	
	';
	
	
} else {
    $username = (!isset($mybb->input['previewpost']) && $mybb->input['action'] !== 'do_newthread')
        ? ''
        : htmlspecialchars_uni($mybb->get_input('username'));
    
	$loginbox = '
	
	<tr>
<td class="trow2"><strong>'.$lang->username.'</strong></td>
<td class="trow2"><input type="text" class="textbox" name="username" size="30" value="'.$username.'" /></td>
</tr>
	
	';
	
	
}

// ── Normalise action ──────────────────────────────────────────────────────────
if (!in_array($mybb->input['action'], ['do_newthread', 'editdraft'], true)) {
    $mybb->input['action'] = 'newthread';
}
if (!empty($mybb->input['previewpost'])) {
    $mybb->input['action'] = 'newthread';
}

if (!$mybb->get_input('posthash') && !$pid) {
    $mybb->input['posthash'] = md5($CURUSER['id'] . random_str());
}

if (empty($_POST) && empty($_FILES) && $mybb->get_input('processed', MyBB::INPUT_INT) == 1) {
    error('error_empty_post_input');
}

$errors = [];
$maximageserror = $attacherror = '';

// ── Attachments: add ─────────────────────────────────────────────────────────
if ($enableattachments == 1
    && ($mybb->get_input('newattachment')
        || $mybb->get_input('updateattachment')
        || (($mybb->input['action'] === 'do_newthread' && $mybb->get_input('submit'))
            || ($mybb->input['action'] === 'newthread' && isset($mybb->input['previewpost']))
            || isset($mybb->input['savedraft']))
        && $_FILES['attachments']))
{
    verify_post_check($mybb->get_input('my_post_key'));

    $attachwhere = ($mybb->input['action'] === 'editdraft' || ($mybb->input['tid'] && $mybb->input['pid']))
        ? "pid='{$pid}'"
        : "posthash='" . $db->escape_string($mybb->get_input('posthash')) . "'";

    $ret = add_attachments($pid, $forumpermissions, $attachwhere, 'newthread');

    if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
        if (isset($ret['success'])) {
            // Шаблон для post.js: {1}=aid, {2}=иконка, {3}=имя файла, {4}=размер
            $ret['template'] = compose_attach_item(
                '{1}', '{2}', '{3}', '{4}',
                compose_att_insert('{1}', 'Insert Into Post') . compose_att_remove('{1}', $lang->post3['btn_remove_attachment'])
            );
            $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
            $usage = $db->fetch_array($query);
            $ret['usage'] = mksize($usage['ausage']);
        }
        header("Content-type: application/json; charset={$charset}");
        echo json_encode($ret);
        exit;
    }

    if (!empty($ret['errors'])) $errors = $ret['errors'];
    if (!$mybb->get_input('submit') && !$mybb->get_input('savedraft')) {
        $mybb->input['action'] = 'newthread';
    }
}

detect_attachmentact();

// ── Attachments: remove ───────────────────────────────────────────────────────
if ($enableattachments == 1
    && $mybb->get_input('attachmentaid', MyBB::INPUT_INT)
    && $mybb->get_input('attachmentact') === 'remove')
{
    verify_post_check($mybb->get_input('my_post_key'));
    remove_attachment($pid, $mybb->get_input('posthash'), $mybb->get_input('attachmentaid', MyBB::INPUT_INT));

    if (!$mybb->get_input('submit')) $mybb->input['action'] = 'newthread';

    if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
        $usage = $db->fetch_array($query);
        header("Content-type: application/json; charset={$charset}");
        echo json_encode(['success' => true, 'usage' => mksize($usage['ausage'])]);
        exit;
    }
}

$thread_errors = '';


// ── DO: post new thread ───────────────────────────────────────────────────────
if ($mybb->input['action'] === 'do_newthread' && $mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    $plugins->run_hooks('newthread_do_newthread_start');

    if ($CURUSER['id'] == 0) {
        $username = $mybb->get_input('username') ?: '';
        $uid      = 0;
    } else {
        $username = $CURUSER['username'];
        $uid      = $CURUSER['id'];
    }

    // Duplicate check
    if (!$mybb->get_input('savedraft') && !$pid) {
        if ($uid > 0) {
            $user_check        = "p.uid = ?";
            $user_check_params = [(int)$uid];
        } else {
            $user_check        = "p.ipaddress = ?";
            $user_check_params = [$session->packedip];
        }
        $query = $db->sql_query_prepared(
            "SELECT p.pid FROM posts p WHERE {$user_check} AND p.fid = ? AND p.subject = ? AND p.message = ? AND p.dateline > ?",
            array_merge($user_check_params, [
                (int)$forum['fid'],
                $mybb->get_input('subject'),
                $mybb->get_input('message'),
                TIMENOW - 600,
            ])
        );
        if ($db->num_rows($query) > 0) stderr('error_post_already_submitted');
    }

    
	require_once INC_PATH . '/datahandlers/post.php';
    $posthandler         = new PostDataHandler('insert');
    $posthandler->action = 'thread';

    $new_thread = [
        'fid'      => $forum['fid'],
        'subject'  => $mybb->get_input('subject'),
        'uid'      => $uid,
        'username' => $username,
        'message'  => $mybb->get_input('message'),
        'ipaddress'=> $session->packedip,
        'posthash' => $mybb->get_input('posthash'),
    ];

    if ($pid !== '') $new_thread['pid'] = $pid;
    $new_thread['savedraft'] = ($mybb->get_input('savedraft') && $CURUSER['id']) ? 1 : 0;

    if (isset($thread['tid']) && $thread['visible'] == -2) {
        $new_thread['tid'] = $thread['tid'];
    }

    $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
    $postoptions += ['subscriptionmethod' => ''];

    $new_thread['options']    = [
        'subscriptionmethod' => $postoptions['subscriptionmethod'],
    ];
    $new_thread['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);

    $posthandler->set_data($new_thread);
    $valid_thread = $posthandler->validate_thread();
    $post_errors  = [];

    if (!$valid_thread) $post_errors = $posthandler->get_friendly_errors();

    if (count($post_errors) > 0) {
        $thread_errors          = inline_error($post_errors);
        $mybb->input['action']  = 'newthread';
    } else {
        $thread_info = $posthandler->insert_thread();
        $tid         = $thread_info['tid'];
        $visible     = $thread_info['visible'];

        $force_redirect     = false;
        $redirect_newthread = '';

        require_once INC_PATH . '/functions_indicators.php';
        mark_thread_read($tid, $fid);

        if ($new_thread['savedraft'] == 1) {
            $redirect_newthread = $lang->newthread['draft_saved'];
            $url = 'usercp.php?action=drafts';
        } elseif ($mybb->get_input('postpoll', MyBB::INPUT_INT) && $forumpermissions['canpostpolls']) {
            $url = "polls.php?action=newpoll&tid=$tid&polloptions=" . $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
            $redirect_newthread .= $lang->newthread['redirect_newthread'] . $lang->newthread['redirect_newthread_poll'];
        } elseif (!$visible) {
            $redirect_newthread .= $lang->newthread['redirect_newthread'] . $lang->newthread['redirect_newthread_moderation'];
            $url = get_forum_link($fid);
            $force_redirect = true;
        } elseif ($visible == 1 && $forumpermissions['canviewthreads'] != 1) {
            $redirect_newthread .= $lang->newthread['redirect_newthread'] . $lang->newthread['redirect_newthread_unviewable'];
            $url = get_forum_link($fid);
            $force_redirect = true;
        } else {
            $redirect_newthread .= $lang->newthread['redirect_newthread'] . $lang->newthread['redirect_newthread_thread'];
            $url = get_thread_link($tid);
        }

        if (isset($mybb->input['quoted_ids']) && isset($mybb->cookies['multiquote'])) {
            if ($mybb->get_input('quoted_ids') === 'all') my_unsetcookie('multiquote');
        }

        $plugins->run_hooks('newthread_do_newthread_end');

        redirect($url, $redirect_newthread, '', $force_redirect);
    }
}

// ── SHOW: newthread form ──────────────────────────────────────────────────────
if (in_array($mybb->input['action'], ['newthread', 'editdraft'], true)) {
    $plugins->run_hooks('newthread_start');

    if (count($errors) > 0) $thread_errors = inline_error($errors);

    $multiquote_external = $quoted_ids = $subject = $message = '';

    if (empty($mybb->input['previewpost']) && !$thread_errors && $mybb->input['action'] !== 'editdraft') {
        $quoted_posts = [];

        if (isset($mybb->cookies['multiquote'])) {
            foreach (explode('|', $mybb->cookies['multiquote']) as $p) {
                $quoted_posts[(int)$p] = (int)$p;
            }
        }

        if (count($quoted_posts) > 0) {
            $quoted_posts_arr    = array_map('intval', array_values($quoted_posts));
            $quoted_placeholders = implode(',', array_fill(0, count($quoted_posts_arr), '?'));
            $unviewable_forums = get_unviewable_forums();
            $inactiveforums    = get_inactive_forums();
            $uv_sql = $unviewable_forums ? "AND t.fid NOT IN ({$unviewable_forums})" : '';
            $ia_sql = $inactiveforums    ? "AND t.fid NOT IN ({$inactiveforums})"    : '';
            
			
			$is_mod2     = is_mod($usergroups);
			$vis_sql = $is_mod2 ? 'AND p.visible != 2' : 'AND p.visible > 0';

            $gp = forum_permissions();
            $onlyusfids = [];
            foreach ($gp as $gpfid => $fp) {
                if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) {
                    $onlyusfids[] = $gpfid;
                }
            }
            $onlyus_sql_params = [];
            $onlyus_sql = !empty($onlyusfids)
                ? "AND ((t.fid IN(" . implode(',', $onlyusfids) . ") AND t.uid=?) OR t.fid NOT IN(" . implode(',', $onlyusfids) . "))"
                : '';
            if (!empty($onlyusfids)) {
                $onlyus_sql_params = [(int)$CURUSER['id']];
            }

            if ($mybb->get_input('load_all_quotes', MyBB::INPUT_INT) == 1) {
                $query = $db->sql_query_prepared("
                    SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, u.username AS userusername
                    FROM posts p
                    LEFT JOIN threads t ON (t.tid=p.tid)
                    LEFT JOIN users u ON (u.id=p.uid)
                    WHERE p.pid IN ({$quoted_placeholders}) {$uv_sql} {$ia_sql} {$onlyus_sql} {$vis_sql}
                    ORDER BY p.dateline, p.pid
                ", array_merge($quoted_posts_arr, $onlyus_sql_params));
                while ($qp = $db->fetch_array($query)) {
                    if ($qp['userusername']) $qp['username'] = $qp['userusername'];
                    $qp['message'] = preg_replace('#(^|\r|\n)/me ([^\r\n<]*)#i', "\\1* {$qp['username']} \\2", $qp['message']);
                    $qp['message'] = preg_replace('#(^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$qp['username']} slaps \\2 with a trout", $qp['message']);
                    $qp['message'] = preg_replace("#\[attachment=([0-9]+?)\]#i", '', $qp['message']);
                    $message .= "[quote='{$qp['username']}' pid='{$qp['pid']}' dateline='{$qp['dateline']}']\n{$qp['message']}\n[/quote]\n\n";
                }
                $quoted_ids = 'all';
            } else {
                $query = $db->sql_query_prepared("
                    SELECT COUNT(*) AS quotes FROM posts p
                    LEFT JOIN threads t ON (t.tid=p.tid)
                    WHERE p.pid IN ({$quoted_placeholders}) {$uv_sql} {$ia_sql} {$onlyus_sql} {$vis_sql}
                ", array_merge($quoted_posts_arr, $onlyus_sql_params));
                $external_quotes = (int)$db->fetch_field($query, 'quotes');

                if ($external_quotes > 0) {
                    $multiquote_text    = $external_quotes === 1 ? $lang->newthread['multiquote_external_one'] : sprintf($lang->newthread['multiquote_external'], $external_quotes);
                    $multiquote_deselect = $lang->newthread['multiquote_external_deselect'] ?? '';
                    $multiquote_quote    = $external_quotes === 1 ? ($lang->newthread['multiquote_external_one_quote'] ?? '') : ($lang->newthread['multiquote_external_quote'] ?? '');

                    $multiquote_external = compose_notice(
                        $multiquote_text . ' <a href="./newthread.php?fid=' . $fid . '&amp;load_all_quotes=1" onclick="return Post.loadMultiQuotedAll();">' . $multiquote_quote . '</a>, '
                        . '<a href="javascript:void(0)" onclick="Post.clearMultiQuoted(); return false;">' . $multiquote_deselect . '</a>',
                        'info', 'fa-quote-left', 'multiquote_unloaded'
                    );
                }
            }
        }
    }

    if (isset($mybb->input['quoted_ids'])) {
        $quoted_ids = htmlspecialchars_uni($mybb->get_input('quoted_ids'));
    }

    $subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';
    $postpollchecked = '';

    $is_preview_or_attach = !empty($mybb->input['previewpost'])
        || $mybb->get_input('attachmentaid', MyBB::INPUT_INT)
        || $mybb->get_input('newattachment')
        || $mybb->get_input('updateattachment')
        || $thread_errors;

    if ($is_preview_or_attach) {
        $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
        if ($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1) $postpollchecked = 'checked="checked"';
        $subscription_method = get_subscription_method($tid, $postoptions);
        $numpolloptions      = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
    } elseif ($mybb->input['action'] === 'editdraft' && $CURUSER['id']) {
       
        $message = htmlspecialchars_uni($post['message']);
        $subject = htmlspecialchars_uni($post['subject']);
        
        
		
		
        $subscription_method = get_subscription_method((int)$tid);
        $numpolloptions      = '2';
    } else {
        $subscription_method = get_subscription_method($tid);
        $numpolloptions      = '2';
    }

    ${$subscription_method . 'subscribe'} = 'checked="checked" ';
    $preview = '';

    // ── Preview ───────────────────────────────────────────────────────────────
    if (!empty($mybb->input['previewpost'])) {
        if ($CURUSER['id'] == 0) {
            $username = $mybb->get_input('username') ?: '';
            $uid      = 0;
        } else {
            $username = $CURUSER['username'];
            $uid      = $CURUSER['id'];
        }

        require_once INC_PATH . '/datahandlers/post.php';
        $posthandler         = new PostDataHandler('insert');
        $posthandler->action = 'thread';

        $new_thread = [
            'fid'      => $forum['fid'],
            'subject'  => $mybb->get_input('subject'),
            'uid'      => $uid,
            'username' => $username,
            'message'  => $mybb->get_input('message'),
            'ipaddress'=> $session->packedip,
            'posthash' => $mybb->get_input('posthash'),
        ];
        if ($pid !== '') $new_thread['pid'] = $pid;
        $posthandler->set_data($new_thread);

        $valid_thread  = $posthandler->verify_message();
        $valid_subject = $posthandler->verify_subject();
        $valid_username = ($new_thread['uid'] == 0) ? $posthandler->verify_author() : true;

        $post_errors = [];
        if (!$valid_thread || !$valid_subject || !$valid_username) {
            $post_errors = $posthandler->get_friendly_errors();
        }

        if (count($post_errors) > 0) {
            $thread_errors = inline_error($post_errors);
        } else {
            $query = $db->sql_query_prepared(
                'SELECT u.* FROM users u WHERE u.id = ? LIMIT 1',
                [(int)$CURUSER['id']]
            );
            $post = $db->fetch_array($query);

            $post['username']    = $username;
            if ($CURUSER['id'])  $post['userusername'] = $CURUSER['username'];
            $post['message']     = $mybb->get_input('message');
            $post['subject']     = $mybb->get_input('subject');
            $post['icon']        = $mybb->get_input('icon', MyBB::INPUT_INT);
            $mybb->input['postoptions'] = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
           
            $post['dateline']    = TIMENOW;
            

            if ($mybb->get_input('pid', MyBB::INPUT_INT)) {
                $attachwhere_sql    = "pid = ?";
                $attachwhere_params = [$mybb->get_input('pid', MyBB::INPUT_INT)];
            } else {
                $attachwhere_sql    = "posthash = ?";
                $attachwhere_params = [$mybb->get_input('posthash')];
            }

            $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE {$attachwhere_sql}", $attachwhere_params);
            while ($attachment = $db->fetch_array($query)) {
                $attachcache[0][$attachment['aid']] = $attachment;
            }

            $preview = build_postbit($post, 1);
        }

        $message = htmlspecialchars_uni($mybb->get_input('message'));
        $subject = htmlspecialchars_uni($mybb->get_input('subject'));
    } elseif ($mybb->get_input('attachmentaid', MyBB::INPUT_INT)
        || $mybb->get_input('newattachment')
        || $mybb->get_input('updateattachment')
        || $thread_errors)
    {
        $message = htmlspecialchars_uni($mybb->get_input('message'));
        $subject = htmlspecialchars_uni($mybb->get_input('subject'));
    }

    

    $posthash = htmlspecialchars_uni($mybb->get_input('posthash'));

    // ── Options: subscription, mod, attachments, poll ─────────────────────────
    $is_mod = is_mod($usergroups);

    $toggles = compose_toggle('collapse-postop', 'fa-gear', $lang->newthread['post_options']);
    $panels  = compose_panel(
        'collapse-postop', 'fa-bell', 'st-soft-primary', $lang->global['thread_subscription_method'],
        compose_subscription($subscribe, $nonesubscribe, $emailsubscribe, $pmsubscribe),
        $lang->global['thread_subscription_method_desc']
    );

    if ($is_mod) {
        $modopts     = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
        $closecheck  = ($modopts['closethread'] ?? 0) == 1 ? 'checked="checked"' : '';
        $stickycheck = ($modopts['stickthread']  ?? 0) == 1 ? 'checked="checked"' : '';

        $toggles .= compose_toggle('collapse-modop', 'fa-screwdriver-wrench', $lang->newthread['mod_options']);
        $panels  .= compose_panel(
            'collapse-modop', 'fa-shield-halved', 'st-soft-danger', $lang->newthread['mod_options'],
            compose_check('checkbox', 'modoptions[closethread]', '1', $closecheck, $lang->newthread['close_thread'])
            . compose_check('checkbox', 'modoptions[stickthread]', '1', $stickycheck, $lang->newthread['stick_thread'])
        );
    }

    // ── Attachments box ───────────────────────────────────────────────────────
    $attachbox = '';
    if ($enableattachments != 0 && $forumpermissions['canpostattachments'] != 0) {
        $attachcount = 0;
        if ($mybb->input['action'] === 'editdraft' || ($mybb->input['tid'] && $mybb->input['pid'])) {
            $attachwhere_sql    = "pid = ?";
            $attachwhere_params = [(int)$pid];
        } else {
            $attachwhere_sql    = "posthash = ?";
            $attachwhere_params = [$posthash];
        }

        $query       = $db->sql_query_prepared("SELECT * FROM attachments WHERE {$attachwhere_sql}", $attachwhere_params);
        $attachments = '';

        while ($attachment = $db->fetch_array($query)) {
            $attachment['size']     = mksize($attachment['filesize']);
            $attachment['icon']     = get_attachment_icon(get_extension($attachment['filename']));
            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
            $aid                    = (string)(int)$attachment['aid'];
            $unapproved             = $attachment['visible'] != 1;

            $attachments .= compose_attach_item(
                $aid, $attachment['icon'], $attachment['filename'], $attachment['size'],
                compose_att_insert($aid, 'Insert Into Post') . compose_att_remove($aid, $lang->post3['btn_remove_attachment']),
                $unapproved,
                $unapproved ? 'attachment.php?aid=' . $aid : ''
            );
            $attachcount++;
        }

        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
        $usage = $db->fetch_array($query);

        $noshowattach = ($usage['ausage'] > ($usergroups['attachquota'] * 1024) && $usergroups['attachquota'] != 0);

        $friendlyquota = $usergroups['attachquota'] == 0 ? 'unlimited' : mksize($usergroups['attachquota'] * 1024);
        $attach_quota  = sprintf($lang->newthread['attach_quota'], $friendlyquota);

        $link_viewattachments = $usage['ausage'] !== null
            ? '<a href="usercp.php?action=attachments">' . $lang->global['view_attachments'] . '</a>'
            : '';

        $attach_buttons = compose_attach_buttons(
            $lang->post3['btn_add_attachment'],
            $lang->post3['btn_update_attachment'],
            $maxattachments == 0 || ($maxattachments != 0 && $attachcount < $maxattachments && !$noshowattach),
            ($usergroups['caneditattachments'] || $forumpermissions['caneditattachments']) && $attachcount > 0
        );

        $attachbox = compose_attachbox($attach_quota, $link_viewattachments, $lang->post3['lbl_new_attachment'], $attach_buttons, $attachments);

        $toggles .= compose_toggle('collapse-attach', 'fa-paperclip', $lang->newthread['attachments']);
        $panels  .= compose_panel('collapse-attach', 'fa-paperclip', 'st-soft-info', $lang->newthread['attachments'], $attachbox);
    }

    // ── Poll ──────────────────────────────────────────────────────────────────
    $maxpolloptions = '10';
    $max_options    = sprintf($lang->newthread['max_options'], $maxpolloptions);

    $toggles .= compose_toggle('collapse-pollop', 'fa-square-poll-vertical', $lang->newthread['poll']);
    $panels  .= compose_panel(
        'collapse-pollop', 'fa-square-poll-vertical', 'st-soft-warning', $lang->newthread['poll'],
        compose_check('checkbox', 'postpoll', '1', $postpollchecked, $lang->newthread['poll_check'])
        . '<div class="cp-pollnum">'
        . '<label class="form-label small mb-1" for="cp_numpolloptions">' . $lang->newthread['num_options'] . '</label>'
        . '<div class="d-flex flex-wrap align-items-center gap-2">'
        . '<input type="number" min="2" max="' . $maxpolloptions . '" class="form-control form-control-sm" name="numpolloptions" id="cp_numpolloptions" value="' . $numpolloptions . '" />'
        . '<span class="st-meta">' . $max_options . '</span></div></div>',
        $lang->newthread['poll_desc']
    );

    // ── Save draft button ─────────────────────────────────────────────────────
    $savedraftbutton = $CURUSER['id']
        ? '<button type="submit" class="btn btn-outline-secondary st-pill" name="savedraft" value="Save as Draft">'
          . '<i class="fa-regular fa-note-sticky me-1"></i>Save as Draft</button>'
        : '';

    // ── Moderation notice ─────────────────────────────────────────────────────
    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_notice = compose_notice($lang->newthread['moderation_user_posts']);
    }

    $php_max_upload_size  = get_php_upload_limit();
    $php_max_file_uploads = (int)ini_get('max_file_uploads');

    // JS-строки post.js из ланга (ключи js_*) -> AGS_LANG без префикса.
    // Если на странице появятся свои js_-ключи, добавить их источник в этот же массив
    // (const AGS_LANG на странице должен быть ровно один).
    $ags_lang = [];
    foreach ($lang->post3 as $ags_key => $ags_val) {
        if (str_starts_with((string)$ags_key, 'js_')) {
            $ags_lang[substr((string)$ags_key, 3)] = $ags_val;
        }
    }
    $ags_lang_json = json_encode($ags_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $post_javascript = '
<script type="text/javascript">
const AGS_LANG = '.$ags_lang_json.';
lang.add_attachment = AGS_LANG.add_attachment;
lang.update_attachment = AGS_LANG.update_attachment;
lang.update_confirm = AGS_LANG.update_confirm;
lang.attachment_missing = AGS_LANG.attachment_missing;
lang.attachment_too_many_files = AGS_LANG.attachment_too_many_files;
lang.attachment_too_big_upload = AGS_LANG.attachment_too_big_upload;
lang.attachment_max_allowed_files = AGS_LANG.attachment_max_allowed_files;
lang.error_maxattachpost = AGS_LANG.error_maxattachpost;
lang.drop_files = AGS_LANG.drop_files;
lang.upload_initiate = AGS_LANG.upload_initiate;
php_max_upload_size = '.$php_max_upload_size.';
php_max_file_uploads = '.$php_max_file_uploads.';
mybb_max_file_uploads = '.$maxattachments.';
</script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/post.js?ver=1833"></script>';

    $plugins->run_hooks('newthread_end');

    $forum['name']  = strip_tags($forum['name']);
    $newthread_in   = sprintf($lang->newthread['newthread_in'], $forum['name']);

    stdhead($newthread_in);
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=6">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/compose.css?ver=1">';
    echo postbit_css_link();
    build_breadcrumb();

    $head = compose_head(
        'fa-pen-to-square', 'st-soft-success', $lang->newthread['post_new_thread'],
        compose_meta('fa-folder-open', '<a href="' . get_forum_link($fid) . '">' . htmlspecialchars_uni($forum['name']) . '</a>')
        . ($CURUSER['id'] ? compose_meta('fa-user', $CURUSER['username']) : '')
    );

    $submitbar = compose_submitbar(
        '<button type="submit" class="btn btn-outline-secondary st-pill" name="previewpost" value="' . $lang->newthread['preview_post'] . '" tabindex="5">'
        . '<i class="fa-solid fa-eye me-1"></i>' . $lang->newthread['preview_post'] . '</button>' . $savedraftbutton,
        '<button type="submit" class="btn btn-primary st-pill px-4" name="post_thread" value="' . $lang->newthread['post_thread'] . '" tabindex="4" accesskey="s">'
        . '<i class="fa-solid fa-paper-plane me-1"></i>' . $lang->newthread['post_thread'] . '</button>'
    );

    echo $post_javascript . compose_insert_script() . '
<div class="container-md st-page cp-page">
' . $head . '
' . compose_preview($preview, $lang->newthread['preview_post']) . '
' . $thread_errors . $attacherror . $moderation_notice . '
<form action="newthread.php?fid=' . $fid . '&amp;processed=1" method="post" enctype="multipart/form-data" name="input" class="cp-form">
<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
<div id="fileIdsContainer"></div>

<div class="st-card cp-editor">
    <div class="st-compose-body">
        ' . compose_input('fa-heading', '<input type="text" class="form-control" name="subject" maxlength="85" placeholder="' . strip_tags($lang->newthread['thread_subject']) . '" value="' . $subject . '" tabindex="1" />') . '
        ' . $codebuttons . '
        <textarea class="form-control st-editor-text" id="message" name="message" tabindex="2" placeholder="Write your Thread using BBCode...">' . $message . '</textarea>
        ' . $multiquote_external . '
    </div>
    <div class="st-optbar">' . $toggles . '</div>
</div>

' . $panels . '
' . $submitbar . '

<input type="hidden" name="action" value="do_newthread" />
<input type="hidden" name="posthash" value="' . $posthash . '" />
<input type="hidden" name="attachmentaid" value="" />
<input type="hidden" name="attachmentact" value="" />
<input type="hidden" name="quoted_ids" value="' . $quoted_ids . '" />
<input type="hidden" name="tid" value="' . $tid . '" />
' . $editdraftpid . '
</form>
</div>';

    stdfoot();
}
