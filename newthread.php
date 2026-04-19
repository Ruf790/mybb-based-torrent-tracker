<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'newthread.php');
define('IN_FORUM', true);

$templatelist  = "newthread,previewpost,loginbox,changeuserbox,newthread_postpoll,posticons,codebuttons";
$templatelist .= ",postbit,post_attachments_attachment_unapproved,newreply_modoptions_close,newreply_modoptions_stick";
$templatelist .= ",newthread_disablesmilies,post_attachments_new,post_attachments,post_savedraftbutton";
$templatelist .= ",post_subscription_method,post_attachments_attachment_remove,postbit_warninglevel_formatted,postbit_icon";
$templatelist .= ",forumdisplay_rules,forumdisplay_rules_link,post_attachments_attachment_postinsert,post_attachments_attachment";
$templatelist .= ",newthread_signature,post_prefixselect_prefix,post_prefixselect_single,posticons_icon";
$templatelist .= ",post_captcha_hidden,post_captcha_recaptcha_invisible,post_captcha_nocaptcha";
$templatelist .= ",post_captcha_hcaptcha_invisible,post_captcha_hcaptcha,post_javascript,postbit_gotopost";
$templatelist .= ",newthread_postoptions,post_attachments_add,post_attachments_viewlink";
$templatelist .= ",postbit_avatar,postbit_find,postbit_pm,postbit_rep_button,postbit_www,postbit_email";
$templatelist .= ",postbit_reputation,postbit_warn,postbit_warninglevel,postbit_author_user,postbit_author_guest,post_captcha";
$templatelist .= ",postbit_signature,postbit_classic,postbit_attachments_thumbnails_thumbnail";
$templatelist .= ",postbit_attachments_images_image,postbit_attachments_attachment,postbit_attachments_attachment_unapproved";
$templatelist .= ",postbit_attachments_thumbnails,postbit_attachments_images,postbit_attachments";
$templatelist .= ",postbit_reputation_formatted_link,post_attachments_update,postbit_offline,newreply_modoptions";
$templatelist .= ",newthread_multiquote_external,postbit_profilefield_multiselect_value,postbit_profilefield_multiselect";
$templatelist .= ",newthread_draftinput,global_moderation_notice,postbit_online,postbit_away,attachment_icon";
$templatelist .= ",postbit_userstar,postbit_groupimage";

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
    $query  = $db->simple_select('tsf_posts', '*', "tid='{$mybb->input['tid']}' AND visible='-2'",
        ['order_by' => 'dateline, pid', 'limit' => 1]);
    $post   = $db->fetch_array($query);

    if (!$thread || !$post || $thread['visible'] != -2 || $thread['uid'] != $CURUSER['id']) {
        stderr('invalidthread');
    }

    $pid = $post['pid'];
    $fid = $thread['fid'];
    $tid = $thread['tid'];
    eval("\$editdraftpid = \"".$templates->get('newthread_draftinput')."\";");
} else {
    $fid         = $mybb->get_input('fid', MyBB::INPUT_INT);
    $editdraftpid = '';
}

// ── Forum validation ──────────────────────────────────────────────────────────
$forum = get_forum($fid);
if (!$forum) error('error_invalidforum');

build_forum_breadcrumb($fid);
add_breadcrumb('New Thread');

$forumpermissions = forum_permissions($fid);

if ($forum['open'] == 0 || $forum['type'] !== 'f' || $forum['linkto'] !== '') {
    error('error_closedinvalidforum');
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
    eval("\$loginbox = \"".$templates->get('changeuserbox')."\";");
} else {
    $username = (!isset($mybb->input['previewpost']) && $mybb->input['action'] !== 'do_newthread')
        ? ''
        : htmlspecialchars_uni($mybb->get_input('username'));
    eval("\$loginbox = \"".$templates->get('loginbox')."\";");
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
            eval("\$attach_rem_options = \"".$templates->get('post_attachments_attachment_remove')."\";");
            $attach_mod_options = '';
            eval("\$attemplate = \"".$templates->get('post_attachments_attachment')."\";");
            $ret['template'] = $attemplate;
            $query = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='{$CURUSER['id']}'");
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
        $query = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='{$CURUSER['id']}'");
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
    $query  = $db->simple_select('posts', 'COUNT(*) AS posts_today',
        "uid='{$mybb->user['uid']}' AND visible !='-1' AND dateline>{$daycut}");
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
        $user_check = $uid > 0
            ? "p.uid='{$uid}'"
            : 'p.ipaddress=' . $db->escape_binary($session->packedip);
        $query = $db->simple_select('tsf_posts p', 'p.pid',
            "$user_check AND p.fid='{$forum['fid']}'"
            . " AND p.subject='" . $db->escape_string($mybb->get_input('subject')) . "'"
            . " AND p.message='" . $db->escape_string($mybb->get_input('message')) . "'"
            . ' AND p.dateline>' . (TIMENOW - 600));
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
            $quoted_posts_str  = implode(',', $quoted_posts);
            $unviewable_forums = get_unviewable_forums();
            $inactiveforums    = get_inactive_forums();
            $uv_sql = $unviewable_forums ? "AND t.fid NOT IN ({$unviewable_forums})" : '';
            $ia_sql = $inactiveforums    ? "AND t.fid NOT IN ({$inactiveforums})"    : '';
            $vis_sql = is_moderator($fid) ? 'AND p.visible != 2' : 'AND p.visible > 0';

            $gp = forum_permissions();
            $onlyusfids = [];
            foreach ($gp as $gpfid => $fp) {
                if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) {
                    $onlyusfids[] = $gpfid;
                }
            }
            $onlyus_sql = !empty($onlyusfids)
                ? "AND ((t.fid IN(" . implode(',', $onlyusfids) . ") AND t.uid='{$CURUSER['id']}') OR t.fid NOT IN(" . implode(',', $onlyusfids) . "))"
                : '';

            if ($mybb->get_input('load_all_quotes', MyBB::INPUT_INT) == 1) {
                $query = $db->sql_query("
                    SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, u.username AS userusername
                    FROM tsf_posts p
                    LEFT JOIN tsf_threads t ON (t.tid=p.tid)
                    LEFT JOIN users u ON (u.id=p.uid)
                    WHERE p.pid IN ({$quoted_posts_str}) {$uv_sql} {$ia_sql} {$onlyus_sql} {$vis_sql}
                    ORDER BY p.dateline, p.pid
                ");
                while ($qp = $db->fetch_array($query)) {
                    if ($qp['userusername']) $qp['username'] = $qp['userusername'];
                    $qp['message'] = preg_replace('#(^|\r|\n)/me ([^\r\n<]*)#i', "\\1* {$qp['username']} \\2", $qp['message']);
                    $qp['message'] = preg_replace('#(^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$qp['username']} slaps \\2 with a trout", $qp['message']);
                    $qp['message'] = preg_replace("#\[attachment=([0-9]+?)\]#i", '', $qp['message']);
                    $message .= "[quote='{$qp['username']}' pid='{$qp['pid']}' dateline='{$qp['dateline']}']\n{$qp['message']}\n[/quote]\n\n";
                }
                $quoted_ids = 'all';
            } else {
                $query = $db->sql_query("
                    SELECT COUNT(*) AS quotes FROM tsf_posts p
                    LEFT JOIN tsf_threads t ON (t.tid=p.tid)
                    WHERE p.pid IN ({$quoted_posts_str}) {$uv_sql} {$ia_sql} {$onlyus_sql} {$vis_sql}
                ");
                $external_quotes = (int)$db->fetch_field($query, 'quotes');

                if ($external_quotes > 0) {
                    $multiquote_text    = $external_quotes === 1 ? $lang->multiquote_external_one : sprintf('multiquote_external', $external_quotes);
                    $multiquote_deselect = $lang->multiquote_external_deselect ?? '';
                    $multiquote_quote    = $external_quotes === 1 ? ($lang->multiquote_external_one_quote ?? '') : ($lang->multiquote_external_quote ?? '');

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
        $subscription_method = get_subscription_method($tid);
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

            $attachwhere = $mybb->get_input('pid', MyBB::INPUT_INT)
                ? "pid='" . $mybb->get_input('pid', MyBB::INPUT_INT) . "'"
                : "posthash='" . $db->escape_string($mybb->get_input('posthash')) . "'";

            $query = $db->simple_select('attachments', '*', $attachwhere);
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
    eval("\$signature = \"".$templates->get('newthread_signature')."\";");

    // ── Post options ──────────────────────────────────────────────────────────
    $postoptions = (!empty($signature) || !empty($disablesmilies))
        ? '<a class="links" data-bs-toggle="collapse" aria-expanded="false" href="#collapse-postop" role="button">
               <i class="fa-solid fa-gear"></i> &nbsp;Post Options:
           </a>'
        : '';

    $bgcolor  = $postoptions ? 'trow2' : 'trow1';
    $bgcolor2 = $postoptions ? 'trow1' : 'trow2';

    // ── Mod options ───────────────────────────────────────────────────────────
    $modoptions = '';
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

    eval("\$subscriptionmethod = \"".$templates->get('post_subscription_method')."\";");

    // ── Attachments box ───────────────────────────────────────────────────────
    $attachbox = '';
    if ($enableattachments != 0 && $forumpermissions['canpostattachments'] != 0) {
        $attachcount = 0;
        $attachwhere = ($mybb->input['action'] === 'editdraft' || ($mybb->input['tid'] && $mybb->input['pid']))
            ? "pid='{$pid}'"
            : "posthash='" . $db->escape_string($posthash) . "'";

        $query       = $db->simple_select('attachments', '*', $attachwhere);
        $attachments = '';

        while ($attachment = $db->fetch_array($query)) {
            $attachment['size']     = mksize($attachment['filesize']);
            $attachment['icon']     = get_attachment_icon(get_extension($attachment['filename']));
            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

            $aid = (int)$attachment['aid'];
            $postinsert = '<input type="button" class="btn btn-page" name="insert" value="Insert Into Post" id="insertBtn_' . $aid . '"
                onclick="(function(){var t=\'[attachment=' . $aid . ']\';var e=document.getElementById(\'message\');if(e)e.value+=t;})()" />';

            eval("\$attach_rem_options = \"".$templates->get('post_attachments_attachment_remove')."\";");
            $attach_mod_options = '';

            if ($attachment['visible'] != 1) {
                eval("\$attachments .= \"".$templates->get('post_attachments_attachment_unapproved')."\";");
            } else {
                eval("\$attachments .= \"".$templates->get('post_attachments_attachment')."\";");
            }
            $attachcount++;
        }

        $query = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='{$CURUSER['id']}'");
        $usage = $db->fetch_array($query);

        $noshowattach = ($usage['ausage'] > ($usergroups['attachquota'] * 1024) && $usergroups['attachquota'] != 0);

        $friendlyquota = $usergroups['attachquota'] == 0 ? 'unlimited' : mksize($usergroups['attachquota'] * 1024);
        $attach_quota  = sprintf($lang->newthread['attach_quota'], $friendlyquota);

        $link_viewattachments = '';
        if ($usage['ausage'] !== null) {
            $friendlyusage = mksize($usage['ausage']);
            $attach_usage  = sprintf('attach_usage', $friendlyusage);
            eval("\$link_viewattachments = \"".$templates->get('post_attachments_viewlink')."\";");
        } else {
            $attach_usage = '';
        }

        $maxattachments    = '5';
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
            eval("\$newattach = \"".$templates->get('post_attachments_new')."\";");
        }
        eval("\$attachbox = \"".$templates->get('post_attachments')."\";");
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
    eval("\$pollbox = \"".$templates->get('newthread_postpoll')."\";");

    // ── Moderation notice ─────────────────────────────────────────────────────
    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_text = $lang->newthread['moderation_user_posts'];
        eval('$moderation_notice = "' . $templates->get('global_moderation_notice') . '";');
    }

    $php_max_upload_size  = get_php_upload_limit();
    $php_max_file_uploads = (int)ini_get('max_file_uploads');
    $maxattachments       = '5';
    eval("\$post_javascript = \"".$templates->get('post_javascript')."\";");

    $plugins->run_hooks('newthread_end');

    $forum['name']  = strip_tags($forum['name']);
    $newthread_in   = sprintf($lang->newthread['newthread_in'], $forum['name']);

    stdhead($newthread_in);
    build_breadcrumb();
    eval("\$newthread = \"".$templates->get('newthread')."\";");
    echo $newthread;
    stdfoot();
}