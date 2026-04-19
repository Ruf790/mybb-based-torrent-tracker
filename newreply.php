<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'newreply.php');
define("SCRIPTNAME", "newreply.php");

$templatelist  = "newreply,previewpost,loginbox,changeuserbox,posticons,newreply_threadreview,newreply_threadreview_post";
$templatelist .= ",forumdisplay_rules_link,newreply_multiquote_external,post_attachments_add,post_subscription_method";
$templatelist .= ",codebuttons,post_attachments_new,post_attachments,post_savedraftbutton,newreply_modoptions";
$templatelist .= ",newreply_threadreview_more,postbit_online,postbit_pm,post_attachments_update";
$templatelist .= ",postbit_warninglevel,postbit_author_user,postbit_edit,postbit_quickdelete,postbit_inlinecheck";
$templatelist .= ",postbit_posturl,postbit_quote,postbit_multiquote,newreply_modoptions_close,newreply_modoptions_stick";
$templatelist .= ",post_attachments_attachment_postinsert,post_attachments_attachment_remove";
$templatelist .= ",post_attachments_attachment_unapproved,post_attachments_attachment,post_attachments_viewlink";
$templatelist .= ",postbit_attachments_attachment,newreply_signature,post_javascript,postbit_groupimage";
$templatelist .= ",postbit_attachments,newreply_postoptions,postbit_author_guest,postbit_signature,postbit_classic";
$templatelist .= ",postbit_attachments_thumbnails_thumbnail,postbit_attachments_images_image";
$templatelist .= ",postbit_attachments_attachment_unapproved,postbit_attachments_thumbnails,postbit_attachments_images";
$templatelist .= ",postbit_gotopost,forumdisplay_password_wrongpass,forumdisplay_password,posticons_icon";
$templatelist .= ",attachment_icon,postbit_reputation_formatted_link,global_moderation_notice,postbit_userstar";
$templatelist .= ",newreply_draftinput,postbit_avatar,forumdisplay_rules,postbit_offline,postbit_find";
$templatelist .= ",postbit_warninglevel_formatted,postbit_ignored,postbit_icon,postbit_email,postbit_report,postbit";

define('IN_FORUM', true);
require_once 'global.php';

require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/datahandler.php';

$lang->load('newreply');
$lang->load('editpost');

$tid     = $mybb->get_input('tid', MyBB::INPUT_INT);
$replyto = $mybb->get_input('replyto', MyBB::INPUT_INT);

if (!empty($mybb->input['ajax'])) {
    unset($mybb->input['previewpost']);
}

// ── Draft ─────────────────────────────────────────────────────────────────────
$pid          = 0;
$editdraftpid = '';
$mybb->input['action'] = $mybb->get_input('action');

if (in_array($mybb->input['action'], ['editdraft', 'do_newreply'], true)
    && $mybb->get_input('pid', MyBB::INPUT_INT))
{
    $pid  = $mybb->get_input('pid', MyBB::INPUT_INT);
    $post = get_post($pid);

    if (!$post)                         error('error_invalidpost');
    if ($CURUSER['id'] != $post['uid']) error('error_post_noperms');

    $pid          = (int)$post['pid'];
    $tid          = (int)$post['tid'];
    $editdraftpid = '<input type="hidden" name="pid" value="'.$pid.'" />';
}

// ── Thread / forum ────────────────────────────────────────────────────────────
$thread = get_thread($tid);
if (!$thread) error('error_invalidthread');

$fid   = (int)$thread['fid'];
$forum = get_forum($fid);
if (!$forum) error('error_invalidforum');

build_forum_breadcrumb($fid);
$thread_subject    = $thread['subject'];
$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb('Post Reply');

$forumpermissions = forum_permissions($fid);

if ($forum['open'] == 0 || $forum['type'] !== 'f') {
    stderr('error_closedinvalidforum');
}
if ($forumpermissions['canview'] == 0 || $forumpermissions['canpostreplys'] == 0) {
    print_no_permission();
}
if (isset($forumpermissions['canonlyviewownthreads'])
    && $forumpermissions['canonlyviewownthreads'] == 1
    && $thread['uid'] != $CURUSER['id'])
{
    print_no_permission();
}
if (isset($forumpermissions['canonlyreplyownthreads'])
    && $forumpermissions['canonlyreplyownthreads'] == 1
    && $thread['uid'] != $CURUSER['id'])
{
    print_no_permission();
}

if ($mybb->get_input('method') === 'quickreply' && !isset($mybb->input['previewpost'])) {
    $mybb->input['postoptions']['subscriptionmethod'] = get_subscription_method(
        $mybb->get_input('tid', MyBB::INPUT_INT)
    );
}

check_forum_password($forum['fid']);

// ── Editor ────────────────────────────────────────────────────────────────────
require_once INC_PATH . '/editor.php';
$editor      = insert_bbcode_editor($smilies, $BASEURL, 'message');
$codebuttons = $editor['toolbar'] . "\n" . $editor['modal'];

// ── Action normalisation ──────────────────────────────────────────────────────
if (!in_array($mybb->input['action'], ['do_newreply', 'editdraft'], true)) {
    $mybb->input['action'] = 'newreply';
}
if (!empty($mybb->input['previewpost'])) {
    $mybb->input['action'] = 'newreply';
}

if (!$mybb->get_input('posthash') && !$pid) {
    $mybb->input['posthash'] = md5($thread['tid'] . $CURUSER['id'] . random_str());
}

if ((empty($_POST) && empty($_FILES)) && $mybb->get_input('processed', MyBB::INPUT_INT) == 1) {
    error('error_empty_post_input');
}

$errors = [];

// ── Attachments ───────────────────────────────────────────────────────────────
$attachbox = '';

if ($enableattachments == 1
    && ($mybb->get_input('newattachment')
        || $mybb->get_input('updateattachment')
        || (($mybb->input['action'] === 'do_newreply' && $mybb->get_input('submit'))
            || ($mybb->input['action'] === 'newreply' && isset($mybb->input['previewpost']))
            || isset($mybb->input['savedraft']))
        && !empty($_FILES['attachments'])))
{
    $attachwhere = $pid
        ? "pid='{$pid}'"
        : "posthash='".$db->escape_string($mybb->get_input('posthash'))."'";

    $ret = add_attachments($pid, '', $attachwhere, 'newreply');

    if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
        if (isset($ret['success'])) {
            $attachment = ['aid' => '{1}', 'icon' => '{2}', 'filename' => '{3}', 'size' => '{4}'];
            $postinsert = '<input type="button" class="btn btn-page" name="insert" value="Insert Into Post"'
                        . ' onclick="(function(){var a='.$attachment['aid'].';var t=\'[attachment=\'+a+\']\';'
                        . 'var e=document.getElementById(\'message\');if(e)e.value+=t;})()" />';
            $attach_rem_options = '<input type="submit" class="btn btn-page" name="rem_'.$attachment['aid'].'"'
                                . ' value="Remove" onclick="return Post.removeAttachment('.$attachment['aid'].');" />';
            $attach_mod_options = '';
            $ret['template'] = '
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
            $query        = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='{$CURUSER['id']}'");
            $usage        = $db->fetch_array($query);
            $ret['usage'] = mksize($usage['ausage']);
        }
        header("Content-type: application/json; charset={$charset}");
        echo json_encode($ret);
        exit;
    }

    if (!empty($ret['errors'])) $errors = $ret['errors'];

    if (!$mybb->get_input('submit') && !$mybb->get_input('savedraft')) {
        $editdraftpid          = '<input type="hidden" name="pid" value="'.$pid.'" />';
        $mybb->input['action'] = 'newreply';
    }
}

detect_attachmentact();

if ($enableattachments == 1
    && $mybb->get_input('attachmentaid', MyBB::INPUT_INT)
    && $mybb->get_input('attachmentact') === 'remove')
{
    remove_attachment($pid, $mybb->get_input('posthash'), $mybb->get_input('attachmentaid', MyBB::INPUT_INT));

    if (!$mybb->get_input('submit')) {
        $editdraftpid          = '<input type="hidden" name="pid" value="'.$pid.'" />';
        $mybb->input['action'] = 'newreply';
    }

    if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
        $query = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='{$CURUSER['id']}'");
        $usage = $db->fetch_array($query);
        header("Content-type: application/json; charset={$charset}");
        echo json_encode(['success' => true, 'usage' => mksize($usage['ausage'])]);
        exit;
    }
}

$reply_errors = $quoted_ids = '';

// Max posts per day
if ($usergroups['maxposts'] > 0) {
    $daycut     = TIMENOW - 86400;
    $query      = $db->simple_select('tsf_posts', 'COUNT(*) AS posts_today',
                    "uid='{$CURUSER['id']}' AND visible!='-1' AND dateline>{$daycut}");
    $post_count = (int)$db->fetch_field($query, 'posts_today');
    if ($post_count >= $usergroups['maxposts']) {
        $lang->error_maxposts = sprintf($lang->error_maxposts, $mybb->usergroup['maxposts']);
        error($lang->error_maxposts);
    }
}

if (!$f_postsperpage || (int)$f_postsperpage < 1) $f_postsperpage = 20;

// ── POST: do_newreply ─────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'do_newreply' && $mybb->request_method === 'post') {
    $plugins->run_hooks('newreply_do_newreply_start');

    if ($CURUSER['id'] == 0) {
        $username = $mybb->get_input('username') ?: '';
        $uid      = 0;
    } else {
        $username = $CURUSER['username'];
        $uid      = $CURUSER['id'];
    }

    // Duplicate check
    if (!$mybb->get_input('savedraft')) {
        $user_check = $uid > 0
            ? "p.uid='{$uid}'"
            : "p.ipaddress=".$db->escape_binary($session->packedip);

        $query = $db->simple_select('tsf_posts p', 'p.pid, p.visible',
            "{$user_check} AND p.tid='{$thread['tid']}'"
            . " AND p.subject='".$db->escape_string($mybb->get_input('subject'))."'"
            . " AND p.message='".$db->escape_string($mybb->get_input('message'))."'"
            . " AND p.visible > -1 AND p.dateline>".(TIMENOW - 600));

        if ($db->num_rows($query) > 0) error('error_post_already_submitted');
    }

    require_once INC_PATH . '/datahandlers/post.php';
    $posthandler = new PostDataHandler('insert');

    $post = [
        'tid'       => $mybb->get_input('tid', MyBB::INPUT_INT),
        'replyto'   => $mybb->get_input('replyto', MyBB::INPUT_INT),
        'fid'       => $thread['fid'],
        'subject'   => $mybb->get_input('subject'),
        'icon'      => $mybb->get_input('icon', MyBB::INPUT_INT),
        'uid'       => $uid,
        'username'  => $username,
        'message'   => $mybb->get_input('message'),
        'ipaddress' => $session->packedip,
        'posthash'  => $mybb->get_input('posthash'),
    ];

    if (isset($mybb->input['pid'])) $post['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);

    $post['savedraft'] = ($mybb->get_input('savedraft') && $CURUSER['id']) ? 1 : 0;

    $postoptions     = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
    $post['options'] = [
        'signature'          => (int)($postoptions['signature']          ?? 0),
        'subscriptionmethod' => (int)($postoptions['subscriptionmethod'] ?? 0),
        'disablesmilies'     => (int)($postoptions['disablesmilies']     ?? 0),
    ];
    $post['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);

    $posthandler->set_data($post);

    require_once INC_PATH . '/functions_indicators.php';
    mark_thread_read($tid, $fid);

    if (!$posthandler->validate_post()) {
        $reply_errors          = inline_error($posthandler->get_friendly_errors());
        $mybb->input['action'] = 'newreply';
    } else {
        $postinfo = $posthandler->insert_post();
        $pid      = $postinfo['pid'];
        $visible  = $postinfo['visible'];
        $closed   = $postinfo['closed'] ?? '';

        $force_redirect     = false;
        $redirect_newreply  = '';

        if ($visible == -2) {
            $redirect_newreply = $lang->newreply['draft_saved'];
            $url               = 'usercp.php?action=drafts';
        } elseif ($visible == 1) {
            $redirect_newreply .= $lang->newreply['redirect_newreply_post'];
            $url               = get_post_link($pid, $tid) . "#pid{$pid}";
        } else {
            $redirect_newreply .= '<br />' . $lang->newreply['redirect_newreply_moderation'];
            $url               = get_thread_link($tid);
            $force_redirect    = true;
        }

        // Multiquote cookie cleanup
        if (isset($mybb->input['quoted_ids']) && isset($mybb->cookies['multiquote'])) {
            if ($mybb->get_input('quoted_ids') === 'all') {
                my_unsetcookie('multiquote');
            } else {
                $qids = explode('|', $mybb->get_input('quoted_ids'));
                $mq   = array_filter(explode('|', $mybb->cookies['multiquote']),
                            fn($id) => !in_array($id, $qids));
                !empty($mq)
                    ? my_setcookie('multiquote', implode(',', $mq))
                    : my_unsetcookie('multiquote');
            }
        }

        $plugins->run_hooks('newreply_do_newreply_end');

        // AJAX quick reply
        if ($mybb->get_input('ajax', MyBB::INPUT_INT)) {
            if ($visible == 1) {
                $postcounter = $thread['replies'] + 1;

                if ($mybb->get_input('lastpid', MyBB::INPUT_INT)) {
                    $query    = $db->simple_select('tsf_posts', 'pid',
                                    "tid='{$tid}' AND pid!='{$pid}'",
                                    ['order_by' => 'pid', 'order_dir' => 'desc']);
                    $new_post = $db->fetch_array($query);
                    if ($new_post['pid'] != $mybb->get_input('lastpid', MyBB::INPUT_INT)) {
                        redirect(get_thread_link($tid, 0, 'lastpost'));
                    }
                }

                $post_page = $f_postsperpage > 0
                    ? (int)ceil(($postcounter + 1) / $f_postsperpage)
                    : 1;

                if ($post_page > $mybb->get_input('from_page', MyBB::INPUT_INT)) {
                    redirect(get_thread_link($tid, 0, 'lastpost'));
                    exit;
                }

                $query = $db->sql_query("
                    SELECT u.*, u.username AS userusername, p.*, eu.username AS editusername
                    FROM tsf_posts p
                    LEFT JOIN users u ON (u.id=p.uid)
                    LEFT JOIN users eu ON (eu.id=p.edituid)
                    WHERE p.pid='{$pid}'
                ");
                $post = $db->fetch_array($query);

                $query = $db->simple_select('attachments', '*', "pid='{$pid}'");
                while ($att = $db->fetch_array($query)) {
                    $attachcache[$att['pid']][$att['aid']] = $att;
                }

                $altbg = (($postcounter - $f_postsperpage) % 2 != 0) ? 'trow1' : 'trow2';
                $pid   = $post['pid'];
                $post  = build_postbit($post);

                $new_posthash = md5($CURUSER['id'] . random_str());
                $data  = $post;
                $data .= "<script type=\"text/javascript\">\n";
                $data .= "var hash=document.getElementById('posthash');if(hash){hash.value='{$new_posthash}';}\n";
                $data .= "if(typeof(inlineModeration)!='undefined'){"
                       . "var el=document.getElementById('inlinemod_{$pid}');"
                       . "if(el){el.addEventListener('click',function(){inlineModeration.checkItem();});}}\n";

                if ($closed == 1) {
                    $data .= "document.querySelectorAll('#quick_reply_form .trow1').forEach(el=>{"
                           . "el.classList.remove('trow1','trow2');el.classList.add('trow_shaded');});\n";
                } else {
                    $data .= "document.querySelectorAll('#quick_reply_form .trow_shaded').forEach(el=>{"
                           . "el.classList.remove('trow_shaded');el.classList.add('trow1');});\n";
                }
                $data .= "</script>\n";

                header("Content-type: application/json; charset={$charset}");
                echo json_encode(['data' => $data]);
                exit;
            } else {
                redirect(get_thread_link($tid, 0, 'lastpost'),
                    $lang->newreply['redirect_newreply_moderation'], '', true);
                exit;
            }
        }

        $redirect_newreply .= sprintf($lang->newreply['redirect_return_forum'], get_forum_link($fid));
        redirect($url, $redirect_newreply, '', $force_redirect);
        exit;
    }
}

// ── Display form ──────────────────────────────────────────────────────────────
if ($mybb->input['action'] === 'newreply' || $mybb->input['action'] === 'editdraft') {
    $plugins->run_hooks('newreply_start');

    $quote_ids = $multiquote_external = $message = '';

    // Quoted posts
    if (empty($mybb->input['previewpost'])
        && !$reply_errors
        && $mybb->input['action'] !== 'editdraft'
        && !$mybb->get_input('attachmentaid', MyBB::INPUT_INT)
        && !$mybb->get_input('newattachment')
        && !$mybb->get_input('updateattachment'))
    {
        $quoted_posts = [];

        if (isset($mybb->cookies['multiquote'])) {
            foreach (explode('|', $mybb->cookies['multiquote']) as $p) {
                if ($p !== '') $quoted_posts[(int)$p] = (int)$p;
            }
        }
        if ($replyto) $quoted_posts[$replyto] = $replyto;

        if (!empty($quoted_posts)) {
            $external_quotes  = 0;
            $quoted_posts_str = implode(',', $quoted_posts);
            $quoted_ids_arr   = [];

            $unviewable_forums = get_unviewable_forums();
            $inactiveforums    = get_inactive_forums();
            $uf_sql = $unviewable_forums ? "AND t.fid NOT IN ({$unviewable_forums})" : '';
            $if_sql = $inactiveforums    ? "AND t.fid NOT IN ({$inactiveforums})"    : '';

            $gp           = forum_permissions();
            $onlyusfids   = [];
            $onlyusforums = '';
            foreach ($gp as $gpfid => $fp) {
                if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) {
                    $onlyusfids[] = $gpfid;
                }
            }
            if (!empty($onlyusfids)) {
                $onlyusforums = "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$CURUSER['id']}')"
                              . " OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
            }

            $visible_where = 'AND p.visible=1';

            require_once INC_PATH . '/functions_posting.php';

            $load_all = $mybb->get_input('load_all_quotes', MyBB::INPUT_INT);
            $query    = $db->sql_query("
                SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline,
                       u.username AS userusername
                FROM tsf_posts p
                LEFT JOIN tsf_threads t ON (t.tid=p.tid)
                LEFT JOIN users u ON (u.id=p.uid)
                WHERE p.pid IN ({$quoted_posts_str})
                      {$uf_sql} {$if_sql} {$onlyusforums} {$visible_where}
            ");

            while ($qp = $db->fetch_array($query)) {
                if ($qp['tid'] == $tid || $load_all == 1) {
                    if ($replyto == $qp['pid']) {
                        $subject = preg_replace('#^RE:\s?#i', '', $qp['subject']);
                        if (my_strlen($subject) > 85) $subject = my_substr($subject, 0, 82) . '...';
                        $subject = 'RE: ' . $subject;
                    }
                    $message        .= parse_quoted_message($qp);
                    $quoted_ids_arr[] = $qp['pid'];
                } else {
                    ++$external_quotes;
                }
            }

            $maxquotedepth = '5';
            if ($maxquotedepth !== '0') $message = remove_message_quotes($message);

            if ($external_quotes > 0) {
                [$multiquote_text, $multiquote_deselect, $multiquote_quote] = $external_quotes === 1
                    ? ['multiquote_external_one', 'multiquote_external_one_deselect', 'multiquote_external_one_quote']
                    : [sprintf('multiquote_external', $external_quotes), 'multiquote_external_deselect', 'multiquote_external_quote'];

                eval("\$multiquote_external = \"".$templates->get('newreply_multiquote_external')."\";");
            }

            $quoted_ids = implode('|', $quoted_ids_arr);
        }
    }

    if (isset($mybb->input['quoted_ids'])) {
        $quoted_ids = htmlspecialchars_uni($mybb->get_input('quoted_ids'));
    }

    if (isset($mybb->input['previewpost'])) $previewmessage = $mybb->get_input('message');
    if (empty($message))                    $message         = $mybb->get_input('message');
    $message = htmlspecialchars_uni($message);

    $postoptionschecked = ['signature' => '', 'disablesmilies' => ''];
    $subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';

    if (!empty($mybb->input['previewpost']) || $reply_errors !== '') {
        $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
        if (($postoptions['signature']      ?? 0) == 1) $postoptionschecked['signature']      = ' checked="checked"';
        if (($postoptions['disablesmilies'] ?? 0) == 1) $postoptionschecked['disablesmilies'] = ' checked="checked"';
        $subscription_method = get_subscription_method($tid, $postoptions);
        $subject             = $mybb->input['subject'];
    } elseif ($mybb->input['action'] === 'editdraft' && $CURUSER['id']) {
        $message = htmlspecialchars_uni($post['message']);
        $subject = $post['subject'];
        if ($post['includesig'] != 0) $postoptionschecked['signature']      = ' checked="checked"';
        if ($post['smilieoff']  == 1) $postoptionschecked['disablesmilies'] = ' checked="checked"';
        $subscription_method = get_subscription_method($tid);
        $mybb->input['icon'] = $post['icon'];
    } else {
        if ($CURUSER['signature'] !== '') $postoptionschecked['signature'] = ' checked="checked"';
        $subscription_method = get_subscription_method($tid);
    }

    ${$subscription_method.'subscribe'} = 'checked="checked" ';

    if (!isset($subject)) {
        $subject = !empty($mybb->input['subject'])
            ? $mybb->get_input('subject')
            : 'RE: ' . (my_strlen($thread_subject) > 85
                ? my_substr($thread_subject, 0, 82) . '...'
                : $thread_subject);
    }

    // ── Preview ───────────────────────────────────────────────────────────────
    $preview = '';
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
        $posthandler->action = 'post';

        $post = [
            'tid'       => $mybb->get_input('tid', MyBB::INPUT_INT),
            'replyto'   => $mybb->get_input('replyto', MyBB::INPUT_INT),
            'fid'       => $thread['fid'],
            'subject'   => $mybb->get_input('subject'),
            'icon'      => $mybb->get_input('icon', MyBB::INPUT_INT),
            'uid'       => $uid,
            'username'  => $username,
            'message'   => $mybb->get_input('message'),
            'ipaddress' => $session->packedip,
            'posthash'  => $mybb->get_input('posthash'),
        ];
        if (isset($mybb->input['pid'])) $post['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);

        $posthandler->set_data($post);

        $valid_post     = $posthandler->verify_message();
        $valid_subject  = $posthandler->verify_subject();
        $valid_username = ($post['uid'] == 0) ? $posthandler->verify_author() : true;

        $post_errors = (!$valid_post || !$valid_subject || !$valid_username)
            ? $posthandler->get_friendly_errors() : [];

        if (count($post_errors) > 0) {
            $reply_errors = inline_error($post_errors);
        } else {
            $quote_ids           = htmlspecialchars_uni($mybb->get_input('quote_ids'));
            $mybb->input['icon'] = $mybb->get_input('icon', MyBB::INPUT_INT);

            $query = $db->sql_query("
                SELECT u.*
                FROM users u
                WHERE u.id='{$CURUSER['id']}'
            ");
            $post = $db->fetch_array($query);

            $postoptions        = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
            $post['username']   = $username;
            if ($CURUSER['id']) $post['userusername'] = $CURUSER['username'];
            $post['message']    = $previewmessage;
            $post['subject']    = $subject;
            $post['icon']       = $mybb->get_input('icon', MyBB::INPUT_INT);
            $post['smilieoff']  = $postoptions['disablesmilies'] ?? 0;
            $post['dateline']   = TIMENOW;
            $post['includesig'] = ($postoptions['signature'] ?? 0) == 1 ? 1 : 0;

            $attachwhere = $mybb->get_input('pid', MyBB::INPUT_INT)
                ? "pid='".$mybb->get_input('pid', MyBB::INPUT_INT)."'"
                : "posthash='".$db->escape_string($mybb->get_input('posthash'))."'";

            $query = $db->simple_select('attachments', '*', $attachwhere);
            while ($att = $db->fetch_array($query)) {
                $attachcache[0][$att['aid']] = $att;
            }

            $preview = build_postbit($post, 1);
        }
    }

    $subject  = htmlspecialchars_uni($parser->parse_badwords($subject));
    $posthash = htmlspecialchars_uni($mybb->get_input('posthash'));

    if (count($errors) > 0) $reply_errors = inline_error($errors);

    // ── Attachments ───────────────────────────────────────────────────────────
    if ($enableattachments != 0 && $forumpermissions['canpostattachments'] != 0) {
        $attachcount = 0;
        $attachwhere = $pid
            ? "pid='$pid'"
            : "posthash='".$db->escape_string($posthash)."'";
        $attachments = '';

        $query = $db->simple_select('attachments', '*', $attachwhere);
        while ($attachment = $db->fetch_array($query)) {
            $attachment['size']     = mksize($attachment['filesize']);
            $attachment['icon']     = get_attachment_icon(get_extension($attachment['filename']));
            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
            $aid                    = (int)$attachment['aid'];

            $attach_mod_options = '';
            $attach_rem_options = '<input type="submit" class="btn btn-page" name="rem_'.$aid.'"'
                                . ' value="Remove" onclick="return Post.removeAttachment('.$aid.');" />';
            $postinsert         = '<input type="button" class="btn btn-page" name="insert"'
                                . ' value="Insert Into Post" id="insertBtn_'.$aid.'" />'
                                . '<script>document.getElementById("insertBtn_'.$aid.'")'
                                . '.addEventListener("click",function(e){e.preventDefault();'
                                . 'var el=document.getElementById("message");'
                                . 'if(el)el.value+=\'[attachment='.$aid.']\';});</script>';

            $attachments .= '
<div class="bg-nav rounded p-2 small mt-2" id="attachment_'.$aid.'">
<div class="row g-1">
<div class="col-lg-8 text-start align-self-center">
    '.$attachment['icon'].' '.$attachment['filename'].' / <strong>'.$attachment['size'].'</strong>
</div>
<div class="col-lg-4 text-lg-end">
    '.$attach_mod_options.' '.$attach_rem_options.' '.$postinsert.'
</div>
</div>
</div>';
            $attachcount++;
        }

        $query        = $db->simple_select('attachments', 'SUM(filesize) AS ausage', "uid='{$CURUSER['id']}'");
        $usage        = $db->fetch_array($query);
        $noshowattach = 0;

        if ($usage['ausage'] > ($usergroups['attachquota'] * 1024) && $usergroups['attachquota'] != 0) {
            $noshowattach = 1;
        }

        $friendlyquota = $usergroups['attachquota'] == 0
            ? $lang->newreply['unlimited']
            : mksize($usergroups['attachquota'] * 1024);

        $attach_quota = sprintf($lang->newreply['attach_quota'], $friendlyquota);

        $link_viewattachments = '';
        if ($usage['ausage'] !== null) {
            $friendlyusage = mksize($usage['ausage']);
            $attach_usage  = sprintf($lang->newreply['attach_usage'], $friendlyusage);
            eval("\$link_viewattachments = \"".$templates->get('post_attachments_viewlink')."\";");
        }

        $maxattachments     = '5';
        $attach_add_options = '';
        if ($maxattachments == 0 || ($maxattachments != 0 && $attachcount < $maxattachments && !$noshowattach)) {
            eval("\$attach_add_options = \"".$templates->get('post_attachments_add')."\";");
        }

        $attach_update_options = '';
        if ($attachcount > 0) {
            eval("\$attach_update_options = \"".$templates->get('post_attachments_update')."\";");
        }

        if ($attach_add_options || $attach_update_options) {
            eval("\$newattach = \"".$templates->get('post_attachments_new')."\";");
        }

        eval("\$attachbox = \"".$templates->get('post_attachments')."\";");
    }

    // ── Save draft ────────────────────────────────────────────────────────────
    $savedraftbutton = '';
    if ($CURUSER['id']) {
        $savedraftbutton = '<button type="submit" class="btn-thread ms-2" name="savedraft" value="Save as Draft">'
                         . '<i class="fa-regular fa-note-sticky"></i>&nbsp;Save as Draft</button>';
    }

    // ── Thread review ─────────────────────────────────────────────────────────
    $reviewmore   = '';
    $threadreview = '';
    $visibility   = "visible='1'";

    $query    = $db->simple_select('tsf_posts', 'COUNT(pid) AS post_count',
                    "tid='{$tid}' AND {$visibility}");
    $numposts = (int)$db->fetch_field($query, 'post_count');

    if ($numposts > $f_postsperpage) {
        $numposts           = $f_postsperpage;
        $thread_review_more = sprintf('thread_review_more', $f_postsperpage, get_thread_link($tid));
        $reviewmore         = '<tr><td class="thead" align="center"><span class="smalltext"><strong>'
                            . $thread_review_more . '</strong></span></td></tr>';
    }

    $pidin = [];
    $query = $db->simple_select('tsf_posts', 'pid', "tid='{$tid}' AND {$visibility}",
                ['order_by' => 'dateline DESC, pid DESC', 'limit' => $f_postsperpage]);
    while ($p = $db->fetch_array($query)) $pidin[] = $p['pid'];

    if (!empty($pidin)) {
        $pidin_str = implode(',', $pidin);

        $query = $db->simple_select('attachments', '*', "pid IN ($pidin_str)");
        while ($att = $db->fetch_array($query)) {
            $attachcache[$att['pid']][$att['aid']] = $att;
        }

        $query = $db->sql_query("
            SELECT p.*, u.username AS userusername
            FROM tsf_posts p LEFT JOIN users u ON (p.uid=u.id)
            WHERE pid IN ($pidin_str)
            ORDER BY dateline DESC, pid DESC
        ");

        $altbg      = 'trow1';
        $reviewbits = '';

        while ($post = $db->fetch_array($query)) {
            if ($post['userusername']) $post['username'] = $post['userusername'];

            $reviewpostdate = my_datee('relative', $post['dateline']);
            $parser_options = [
                'allow_html'     => 1, 'allow_mycode'   => 1, 'allow_smilies'  => 1,
                'allow_imgcode'  => 1, 'allow_videocode' => 1,
                'me_username'    => $post['username'],   'filter_badwords' => 1,
            ];

            $post['username'] = htmlspecialchars_uni($post['username']);
            if ($post['visible'] != 1) $altbg = 'trow_shaded';

            $plugins->run_hooks('newreply_threadreview_post');

            $post['message'] = $parser->parse_message($post['message'], $parser_options);
            get_post_attachments($post['pid'], $post);
            $reviewmessage = $post['message'];

            $reviewbits .= '
<tr>
<td class="tcat"><span class="smalltext"><strong>'
    . $post['username'] . ' - ' . $reviewpostdate . '</strong></span></td>
</tr>
<tr><td class="'.$altbg.' scaleimages">'.$reviewmessage.'</td></tr>';

            $altbg = $altbg === 'trow1' ? 'trow2' : 'trow1';
        }

        $threadreview = '
<table border="0" cellspacing="1" cellpadding="4" class="tborder tfixed">
<tr><td class="thead" style="text-align:center;"><strong>Thread Review</strong></td></tr>
'.$reviewbits.'
'.$reviewmore.'
</table>';
    }

    // ── Post / mod options ────────────────────────────────────────────────────
    $signature = $disablesmilies = '';
    $postoptions = '';
    eval("\$postoptions = \"".$templates->get('newreply_postoptions')."\";");
    $bgcolor = 'trow2';

    $modoptions = '';
    $is_mod     = is_mod($usergroups);

    if ($is_mod) {
        if ($mybb->get_input('processed', MyBB::INPUT_INT)) {
            $modopts = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
            $closed  = (int)($modopts['closethread'] ?? 0);
            $stuck   = (int)($modopts['stickthread']  ?? 0);
        } else {
            $closed = $thread['closed'];
            $stuck  = $thread['sticky'];
        }

        $closecheck  = $closed ? ' checked="checked"' : '';
        $stickycheck = $stuck  ? ' checked="checked"' : '';

        $closeoption = '<input type="checkbox" class="form-check-input" name="modoptions[closethread]" value="1"'
                     . $closecheck.' />&nbsp;<strong>Close Thread</strong>: prevent further posting in this thread';
        $stickoption = '<br /><input type="checkbox" class="form-check-input" name="modoptions[stickthread]" value="1"'
                     . $stickycheck.' />&nbsp;<strong>Stick Thread:</strong> stick this thread to the top of the forum';

        if (!empty($closeoption) || !empty($stickoption)) {
            eval("\$modoptions = \"".$templates->get('newreply_modoptions')."\";");
            $bgcolor = 'trow1';
        }
    }

    eval("\$subscriptionmethod = \"".$templates->get('post_subscription_method')."\";");

    $post_reply_to = sprintf($lang->newreply['post_reply_to'], $thread['subject']);
    $reply_to      = sprintf($lang->newreply['reply_to'], $thread['subject']);

    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_text = $lang->newreply['moderation_user_posts'];
        eval('$moderation_notice = "'.$templates->get('global_moderation_notice').'";');
    }

    $php_max_upload_size  = get_php_upload_limit();
    $php_max_file_uploads = (int)ini_get('max_file_uploads');
    $maxattachments       = '12';

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

    $plugins->run_hooks('newreply_end');

    $forum['name'] = strip_tags($forum['name']);

    stdhead($post_reply_to);
    build_breadcrumb();

    eval("\$newreply = \"".$templates->get('newreply')."\";");
    echo $newreply;

    stdfoot();
}