<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'newthread.php');
define('IN_FORUM', true);



require_once 'global.php';
require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/datahandler.php';
require_once 'cache/smilies.php';

$lang->load('newthread');
$lang->load('editpost');

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
add_breadcrumb('New Thread');

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
            $attachment = ['aid' => '{1}', 'icon' => '{2}', 'filename' => '{3}', 'size' => '{4}'];
            $postinsert = '<input type="button" class="btn btn-page" name="insert" value="Insert Into Post"
                onclick="(function(){var a=' . $attachment['aid'] . ';var t=\'[attachment=\'+a+\']\';
                var e=document.getElementById(\'message\');if(e)e.value+=t;})()" />';
            
			
			$attach_rem_options = '
			
			<input type="submit" class="btn btn-page" name="rem_'.$attachment['aid'].'" value="'.$lang->editpost['remove_attachment'].'" onclick="return Post.removeAttachment('.$attachment['aid'].');" />
			
			';
            
			
			$attach_mod_options = '';
            
			$attemplate = '
			
			
			<div class="bg-nav rounded p-2 small mt-2" id="attachment_'.$attachment['aid'].'">
<div class="row g-1">
<div class="col-lg-8 text-start align-self-center">
		
	'.$attachment['icon'].' '.$attachment['filename'].' / <strong>'.$attachment['size'].'</strong> 
		
	</div>
	<div class="col-lg-4 text-lg-end">
		'.$attach_mod_options.' '.$attach_rem_options.' '.$postinsert.'
	</div>
</div>
</div>';
			
			
			
			
			
            $ret['template'] = $attemplate;
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

// ── Max posts per day ─────────────────────────────────────────────────────────
if ($mybb->usergroup['maxposts'] > 0) {
    $daycut = TIME_NOW - 86400;
    $query  = $db->sql_query_prepared(
        "SELECT COUNT(*) AS posts_today FROM posts WHERE uid = ? AND visible != -1 AND dateline > ?",
        [(int)$mybb->user['uid'], (int)$daycut]
    );
    $post_count = $db->fetch_field($query, 'posts_today');
    if ($post_count >= $mybb->usergroup['maxposts']) {
        $lang->error_maxposts = $lang->sprintf($lang->error_maxposts, $mybb->usergroup['maxposts']);
        error($lang->error_maxposts);
    }
}

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
        'prefix'   => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
        'icon'     => $mybb->get_input('icon', MyBB::INPUT_INT),
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
    $postoptions += ['signature' => 0, 'subscriptionmethod' => 0, 'disablesmilies' => 0];

    $new_thread['options']    = [
        'signature'          => $postoptions['signature'],
        'subscriptionmethod' => $postoptions['subscriptionmethod'],
        'disablesmilies'     => $postoptions['disablesmilies'],
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
            $redirect_newthread = 'draft_saved';
            $url = 'usercp.php?action=drafts';
        } elseif ($mybb->get_input('postpoll', MyBB::INPUT_INT) && $forumpermissions['canpostpolls']) {
            $url = "polls.php?action=newpoll&tid=$tid&polloptions=" . $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
            $redirect_newthread .= 'redirect_newthread_poll';
        } elseif (!$visible) {
            $redirect_newthread .= 'The administrator has specified that all new threads require moderation. You will now be returned to the thread listing';
            $url = get_forum_link($fid);
            $force_redirect = true;
        } elseif ($visible == 1 && $forumpermissions['canviewthreads'] != 1) {
            $redirect_newthread .= 'You do not have permission to view threads in this forum.';
            $url = get_forum_link($fid);
            $force_redirect = true;
        } else {
            $redirect_newthread .= 'You will now be taken to the new thread';
            $url = get_thread_link($tid);
        }

        if (isset($mybb->input['quoted_ids']) && isset($mybb->cookies['multiquote'])) {
            if ($mybb->get_input('quoted_ids') === 'all') my_unsetcookie('multiquote');
        }

        $plugins->run_hooks('newthread_do_newthread_end');

        if (!$mybb->get_input('postpoll', MyBB::INPUT_INT)) {
            $redirect_newthread .= sprintf('redirect_return_forum', get_forum_link($fid));
        }
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

                    $multiquote_external = '
                    <div id="multiquote_unloaded"><span class="smalltext">' . $multiquote_text . '
                        <a href="./newthread.php?fid=' . $fid . '&amp;load_all_quotes=1" onclick="return Post.loadMultiQuotedAll();">' . $multiquote_quote . '</a>,
                        <a href="javascript:void(0)" onclick="Post.clearMultiQuoted(); return false;">' . $multiquote_deselect . '</a>
                    </span></div>';
                }
            }
        }
    }

    if (isset($mybb->input['quoted_ids'])) {
        $quoted_ids = htmlspecialchars_uni($mybb->get_input('quoted_ids'));
    }

    $postoptionschecked = ['signature' => '', 'disablesmilies' => ''];
    $subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';
    $postpollchecked = '';

    $is_preview_or_attach = !empty($mybb->input['previewpost'])
        || $mybb->get_input('attachmentaid', MyBB::INPUT_INT)
        || $mybb->get_input('newattachment')
        || $mybb->get_input('updateattachment')
        || $thread_errors;

    if ($is_preview_or_attach) {
        $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
        if (($postoptions['signature']     ?? 0) == 1) $postoptionschecked['signature']     = ' checked="checked"';
        if (($postoptions['disablesmilies'] ?? 0) == 1) $postoptionschecked['disablesmilies'] = ' checked="checked"';
        if ($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1) $postpollchecked = 'checked="checked"';
        $subscription_method = get_subscription_method($tid, $postoptions);
        $numpolloptions      = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
    } elseif ($mybb->input['action'] === 'editdraft' && $CURUSER['id']) {
        $mybb->input['threadprefix'] = $thread['prefix'];
        $message = htmlspecialchars_uni($post['message']);
        $subject = htmlspecialchars_uni($post['subject']);
        if ($post['includesig'] != 0) $postoptionschecked['signature']     = ' checked="checked"';
        if ($post['smilieoff']  == 1)  $postoptionschecked['disablesmilies'] = ' checked="checked"';
        $icon = $post['icon'];
        if ($forum['allowpicons'] != 0) $posticons = get_post_icons();
        $subscription_method = get_subscription_method((int)$tid);
        $numpolloptions      = '2';
    } else {
        if ($CURUSER['signature'] !== '') $postoptionschecked['signature'] = ' checked="checked"';
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
            'prefix'   => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
            'subject'  => $mybb->get_input('subject'),
            'icon'     => $mybb->get_input('icon'),
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
            $post['smilieoff']   = $mybb->input['postoptions']['disablesmilies'] ?? 0;
            $post['dateline']    = TIMENOW;
            $post['includesig']  = ($mybb->input['postoptions']['signature'] ?? 0) == 1 ? 1 : 0;

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

    if (!$mybb->get_input('threadprefix', MyBB::INPUT_INT)) $mybb->input['threadprefix'] = 0;

    $posthash = htmlspecialchars_uni($mybb->get_input('posthash'));

    $signature = '';
    
	
	$signature = '
	
	<input type="checkbox" class="form-check-input" name="postoptions[signature]" value="1" tabindex="7"'.$postoptionschecked['signature'].' /> '.$lang->newthread['options_sig'].'
	
	';
	
	

    // ── Post options ──────────────────────────────────────────────────────────
    $postoptions = (!empty($signature) || !empty($disablesmilies))
        ? '<a class="links" data-bs-toggle="collapse" aria-expanded="false" href="#collapse-postop" role="button">
               <i class="fa-solid fa-gear"></i> &nbsp;Post Options:
           </a>'
        : '';

    $bgcolor  = $postoptions ? 'trow2' : 'trow1';
    $bgcolor2 = $postoptions ? 'trow1' : 'trow2';

    // ── Mod options ───────────────────────────────────────────────────────────
    $modoptions  = '';
    $closeoption = '';
    $stickoption = '';
    $is_mod     = is_mod($usergroups);

    if ($is_mod) {
        $modopts     = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
        $closecheck  = ($modopts['closethread'] ?? 0) == 1 ? 'checked="checked"' : '';
        $stickycheck = ($modopts['stickthread']  ?? 0) == 1 ? 'checked="checked"' : '';

        $closeoption = '<input type="checkbox" class="form-check-input" name="modoptions[closethread]" value="1" ' . $closecheck . ' />
            &nbsp;<b>Close Thread</b>: prevent further posting in this thread';

        $stickoption = '<br /><input type="checkbox" class="form-check-input" name="modoptions[stickthread]" value="1" ' . $stickycheck . ' />
            &nbsp;<b>Stick Thread:</b> stick this thread to the top of the forum';

        if ($closeoption || $stickoption) {
            $modoptions = '&nbsp;&nbsp;<a class="links" data-bs-toggle="collapse" aria-expanded="false" href="#collapse-modop" role="button">
                <i class="fa-solid fa-screwdriver-wrench"></i> &nbsp;Moderator Options:
            </a>&nbsp;&nbsp;';
        }
        $bgcolor  = 'trow1';
        $bgcolor2 = 'trow2';
    }

   
	 $subscriptionmethod = '
	
	
	<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->global['thread_subscription_method'].'</div>
<div class="row g-3 m-auto border-bottom pb-4 pt-0 mt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->global['thread_subscription_method'].'
</div>
<div class="col">
	<div class="text-desc mb-3">'.$lang->global['thread_subscription_method_desc'].'</div>
	<label class="form-check-label"><input type="radio" class="form-check-input" name="postoptions[subscriptionmethod]" '.$subscribe.'value="" /> '.$lang->global['no_subscribe'].'</label><br />
	<label class="form-check-label"><input type="radio" class="form-check-input mt-1"  name="postoptions[subscriptionmethod]" '.$nonesubscribe.'value="none" /> '.$lang->global['no_subscribe_notification'].'</label><br />
	<label class="form-check-label"><input type="radio" class="form-check-input mt-1"  name="postoptions[subscriptionmethod]" '.$emailsubscribe.'value="email"/> '.$lang->global['instant_email_subscribe'].'</label><br />
	<label class="form-check-label"><input type="radio" class="form-check-input mt-1"  name="postoptions[subscriptionmethod]" '.$pmsubscribe.'value="pm" /> '.$lang->global['instant_pm_subscribe'].'</label>
</div>
</div>';
	
	
	
	
	

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

            $aid = (int)$attachment['aid'];
            $postinsert = '<input type="button" class="btn btn-page" name="insert" value="Insert Into Post" id="insertBtn_' . $aid . '"
                onclick="(function(){var t=\'[attachment=' . $aid . ']\';var e=document.getElementById(\'message\');if(e)e.value+=t;})()" />';

              $attach_rem_options = '
			
			<input type="submit" class="btn btn-page" name="rem_'.$attachment['aid'].'" value="'.$lang->editpost['remove_attachment'].'" onclick="return Post.removeAttachment('.$attachment['aid'].');" />
			
			';
			
			
			
            $attach_mod_options = '';

            if ($attachment['visible'] != 1) {
                
				$attachments .= '
				
				<div class="alert bg-danger border-0 mt-2" id="attachment_'.$attachment['aid'].'">
<div class="row">
<div class="col-8 text-start">
		
		'.$attachment['icon'].' <a href="attachment.php?aid='.$attachment['aid'].'" target="_blank">'.$attachment['filename'].'</a> ('.$attachment['size'].') 
		
	</div>
	<div class="col-4 text-end">
		'.$attach_mod_options.' '.$attach_rem_options.' '.$postinsert.'
	</div>
</div>
</div>';
				
				
				
				
				
				
            } else {
                
				
				$attachments .= '
				
				
				<div class="bg-nav rounded p-2 small mt-2" id="attachment_'.$attachment['aid'].'">
<div class="row g-1">
<div class="col-lg-8 text-start align-self-center">
		
	'.$attachment['icon'].' '.$attachment['filename'].' / <strong>'.$attachment['size'].'</strong> 
		
	</div>
	<div class="col-lg-4 text-lg-end">
		'.$attach_mod_options.' '.$attach_rem_options.' '.$postinsert.'
	</div>
</div>
</div>';
				
				
				
				
				
				
				
				
				
				
            }
            $attachcount++;
        }

        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
        $usage = $db->fetch_array($query);

        $noshowattach = ($usage['ausage'] > ($usergroups['attachquota'] * 1024) && $usergroups['attachquota'] != 0);

        $friendlyquota = $usergroups['attachquota'] == 0 ? 'unlimited' : mksize($usergroups['attachquota'] * 1024);
        $attach_quota  = sprintf($lang->newthread['attach_quota'], $friendlyquota);

        $link_viewattachments = '';
        if ($usage['ausage'] !== null) {
            $friendlyusage = mksize($usage['ausage']);
            $attach_usage  = sprintf('attach_usage', $friendlyusage);
            
			
			$link_viewattachments = '<a href="usercp.php?action=attachments">'.$lang->global['view_attachments'].'</a>';
			
			
        } else {
            $attach_usage = '';
        }

       
        $attach_add_options = '';
        if ($maxattachments == 0 || ($maxattachments != 0 && $attachcount < $maxattachments && !$noshowattach)) {
            $attach_add_options = '<button type="submit" class="btn btn-primary" name="newattachment" value="Add Attachment" tabindex="13">
                <i class="fa-solid fa-upload"></i> &nbsp;Add Attachment</button>';
        }

        $attach_update_options = '';
        if (($usergroups['caneditattachments'] || $forumpermissions['caneditattachments']) && $attachcount > 0) {
            $attach_update_options = '<button type="submit" class="btn btn-primary" name="updateattachment" value="Update Attachment" tabindex="12">
                <i class="fa-solid fa-check"></i> &nbsp;Update Attachment</button>';
        }

        if ($attach_add_options || $attach_update_options) {
            
			
			$newattach = '
			<div class="row mb-2">
	<div class="col-12">

		<div id="upload_bar" style="background: #0066A2; height: 5px; width: 0%;"></div>
		<div id="dropzone" style="padding: 30px 0; background: #f0faf6; cursor: pointer; border-radius: 5px; text-align: center; width:100%">
			<i class="fas fa-paperclip"></i>
			<div style="pointer-events: none;"></div>
			
		</div>
	</div>
</div>

<label for="attachments[]">'.$lang->editpost['new_attachment'].'</label>
<div class="alert bg-nav mb-0">
<input type="file" name="attachments[]" size="30" class="form-control" multiple="multiple" />
</div>
<div class="mt-2">
'.$attach_add_options.' &nbsp;'.$attach_update_options.'
</div>';
			
			
			
			
        }
        
		
		$attachbox = '
		
		<div class="card border-0">
<div class="pb-3 border-bottom text-muted mb-3">'.$attach_quota.'</div>
'.$newattach.'
'.$attachments.'
</div>';
        
		
		$bgcolor = alt_trow();
    }

    // ── Save draft button ─────────────────────────────────────────────────────
    $savedraftbutton = $CURUSER['id']
        ? '<button type="submit" class="btn-thread ms-2" name="savedraft" value="Save as Draft">
               <i class="fa-regular fa-note-sticky"></i> &nbsp;Save as Draft
           </button>'
        : '';

    // ── Poll box ──────────────────────────────────────────────────────────────
    $maxpolloptions = '10';
    $max_options    = sprintf($lang->newthread['max_options'], $maxpolloptions);
   
    $pollbox = '

	&nbsp;&nbsp;<a class="links" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-pollop" role="button"><i class="fa-solid fa-circle-plus"></i> &nbsp;'.$lang->newthread['poll'].'</a>&nbsp;&nbsp;

	';

    // ── Moderation notice ─────────────────────────────────────────────────────
    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_text = $lang->newthread['moderation_user_posts'];
        
		
		$moderation_notice = '<div class="red_alert">'.$moderation_text.'</div>';
		
		
    }

    $php_max_upload_size  = get_php_upload_limit();
    $php_max_file_uploads = (int)ini_get('max_file_uploads');
   
   
   $post_javascript = '
<script type="text/javascript">
lang.add_attachment = "Add Attachment";
lang.update_attachment = "Update Attachment";
lang.update_confirm = "The following file(s) are already attached and will be updated / replaced with the newly selected one(s). {1} Are you sure?";
lang.attachment_missing = "Please select one or more files before attempting to attach.";
lang.attachment_too_many_files = "You can upload a maximum of {1} files at once.";
lang.attachment_too_big_upload = "You can upload a maximum of {1} MB at once.";
lang.attachment_max_allowed_files = "You can attach {1} more file(s) to this post.";
lang.error_maxattachpost = "Sorry but you cannot attach this file because you have reached the maximum number of attachments allowed per post of {1}";
lang.drop_files = "Click or drop some files here to upload...";
lang.upload_initiate = "Release to initiate upload...";
php_max_upload_size = '.$php_max_upload_size.';
php_max_file_uploads = '.$php_max_file_uploads.';
mybb_max_file_uploads = '.$maxattachments.';
</script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js"></script>
<script type="text/javascript" src="'.$BASEURL.'/scripts/post.js?ver=1832"></script>';
   
   
   

    $plugins->run_hooks('newthread_end');

    $forum['name']  = strip_tags($forum['name']);
    $newthread_in   = sprintf($lang->newthread['newthread_in'], $forum['name']);

    stdhead($newthread_in);
    build_breadcrumb();
    
	
	$newthread = '
	
	<html>
<head>
<title>'.$lang->newthread['newthread_in'].'</title>


'.$post_javascript.'

</head>
<body>
	<div class="container-md">		
'.$preview.'
'.$thread_errors.'
'.$attacherror.'
'.$moderation_notice.'
		

<form action="newthread.php?fid='.$fid.'&amp;processed=1" method="post" enctype="multipart/form-data" name="input">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />

<div id="fileIdsContainer"></div>	

	
	<div class="row m-0 mb-3 p-0 pb-2 border-bottom">
		
		
		<div class="col align-self-center m-0 p-0">
	<input type="text" class="form-control form-control border mb-3" name="subject" maxlength="85" placeholder="'.$lang->newthread['thread_subject'].'" value="'.$subject.'" tabindex="1" />
		</div>
	</div>
	

			'.$codebuttons.'


<div class="row">
	<div class="col">




      
      <textarea class="form-control" id="message" name="message" style="height: 400px" tabindex="2" placeholder="Write your Thread using BBCode...">'.$message.'</textarea>
   
    
	
	
	
	</div>
	
	
	
	

	</div>
	
	

<div class="mt-2 mb-3">
'.$postoptions.'	
'.$modoptions.'
<a class="links" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-attach" role="button"><i class="fa-solid fa-paperclip"></i> &nbsp;'.$lang->newthread['attachments'].'</a>		
'.$pollbox.'
<button type="submit" class="btn-thread" name="previewpost" value="'.$lang->newthread['preview_post'].'" tabindex="5"><i class="fa-solid fa-pen"></i> &nbsp;'.$lang->newthread['preview_post'].'</button>
'.$savedraftbutton.'	
	</div>
	
	
	

<!-- pollop -->
<div id="collapse-pollop" class="collapse">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->newthread['poll'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->newthread['poll'].'
</div>
<div class="col">
<div class="text-desc mb-3">'.$lang->newthread['poll_desc'].'</div>
<label><input type="checkbox" class="form-check-input" name="postpoll" value="1" '.$postpollchecked.' />&nbsp; '.$lang->newthread['poll_check'].'</label>	
<div class="mt-3">'.$lang->newthread['num_options'].'<input type="text" class="form-control border form-control-sm border" style="width: 250px" name="numpolloptions" value="'.$numpolloptions.'" size="10" /> &nbsp;'.$max_options.'
</div>	
</div>
</div>
</div>
<!-- pollop -->		
 <!-- attach -->
<div id="collapse-attach" class="collapse">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->newthread['attachments'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->newthread['attachments'].'
</div>
<div class="col">
'.$attachbox.'
</div>
</div>
</div>
<!-- attach -->
 <!-- modop -->
<div id="collapse-modop" class="collapse">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->newthread['mod_options'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->newthread['mod_options'].'
</div>
<div class="col">
'.$closeoption.'
'.$stickoption.'
</div>
</div>
</div>
<!-- modop -->		
<!-- postop -->
<div id="collapse-postop" class="collapse">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->newthread['post_options'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">

<div class="col">

</div>
</div>
'.$subscriptionmethod.'		
</div>
<!-- postop -->		
	
	'.$multiquote_external.'


	</div>
	<div class="card-footer text-center">
<button type="post_thread" class="btn btn-primary" name="post_thread" value="'.$lang->newthread['post_thread'].'" tabindex="4" accesskey="s"><i class="fa-solid fa-pencil"></i> &nbsp;'.$lang->newthread['post_thread'].'</button></div>
</div>
<input type="hidden" name="action" value="do_newthread" />
<input type="hidden" name="posthash" value="'.$posthash.'" />
<input type="hidden" name="attachmentaid" value="" />
<input type="hidden" name="attachmentact" value="" />
<input type="hidden" name="quoted_ids" value="'.$quoted_ids.'" />
<input type="hidden" name="tid" value="'.$tid.'" />
'.$editdraftpid.'
</form>

	</div></div>

	
<script>
document.addEventListener(\'DOMContentLoaded\', function() {
    // Заменяем старый jQuery код на чистый JavaScript
    if (Post.fileInput && Post.fileInput.parentElement && Post.fileInput.parentElement.parentElement) {
        Post.fileInput.parentElement.parentElement.style.display = \'block\';
    }
    if (Post.dropZone && Post.dropZone.parentElement && Post.dropZone.parentElement.parentElement) {
        Post.dropZone.parentElement.parentElement.style.display = \'none\';
    }
});
</script>
</body>
</html>';
	
	
	
	
	
	
	
	
	
	
    echo $newthread;
    stdfoot();
}