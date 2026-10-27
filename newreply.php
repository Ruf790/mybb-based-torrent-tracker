<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'newreply.php');
define("SCRIPTNAME", "newreply.php");



define('IN_FORUM', true);
require_once 'global.php';

require_once INC_PATH . '/functions_post.php';
require_once INC_PATH . '/functions_compose.php';
require_once INC_PATH . '/functions_user.php';
require_once INC_PATH . '/functions_upload.php';
require_once INC_PATH . '/functions_subscription_method.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/datahandler.php';

$lang->load('newreply');
$lang->load('post3');

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
add_breadcrumb($lang->newreply['nav_newreply']);

$forumpermissions = forum_permissions($fid);

if ($forum['open'] == 0 || $forum['type'] !== 'f') {
    stderr($lang->global['error_closedinvalidforum'], $SITENAME . ' - Access Denied', 403, '403');
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

$maximageserror = $attacherror = '';

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
            // Шаблон для post.js: {1}=aid, {2}=иконка, {3}=имя файла, {4}=размер
            $ret['template'] = compose_attach_item(
                '{1}', '{2}', '{3}', '{4}',
                compose_att_insert('{1}', 'Insert Into Post') . compose_att_remove('{1}', $lang->post3['btn_remove_attachment'])
            );
            $query        = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
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
        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
        $usage = $db->fetch_array($query);
        header("Content-type: application/json; charset={$charset}");
        echo json_encode(['success' => true, 'usage' => mksize($usage['ausage'])]);
        exit;
    }
}

$reply_errors = $quoted_ids = '';



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
        if ($uid > 0) {
            $user_check        = "p.uid = ?";
            $user_check_params = [(int)$uid];
        } else {
            $user_check        = "p.ipaddress = ?";
            $user_check_params = [$session->packedip];
        }

        $query = $db->sql_query_prepared(
            "SELECT p.pid, p.visible FROM posts p WHERE {$user_check} AND p.tid = ? AND p.subject = ? AND p.message = ? AND p.visible > -1 AND p.dateline > ?",
            array_merge($user_check_params, [
                (int)$thread['tid'],
                $mybb->get_input('subject'),
                $mybb->get_input('message'),
                TIMENOW - 600,
            ])
        );

        if ($db->num_rows($query) > 0) error('error_post_already_submitted');
    }

   
	require_once INC_PATH . '/datahandlers/post.php';
    $posthandler = new PostDataHandler('insert');

    $post = [
        'tid'       => $mybb->get_input('tid', MyBB::INPUT_INT),
        'replyto'   => $mybb->get_input('replyto', MyBB::INPUT_INT),
        'fid'       => $thread['fid'],
        'subject'   => $mybb->get_input('subject'),
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
        'subscriptionmethod' => trim((string)($postoptions['subscriptionmethod'] ?? '')),
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
                    $query    = $db->sql_query_prepared(
                                    "SELECT pid FROM posts WHERE tid = ? AND pid != ? ORDER BY pid DESC",
                                    [(int)$tid, (int)$pid]
                                );
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

                $query = $db->sql_query_prepared("
                    SELECT u.*, u.username AS userusername, p.*, eu.username AS editusername
                    FROM posts p
                    LEFT JOIN users u ON (u.id=p.uid)
                    LEFT JOIN users eu ON (eu.id=p.edituid)
                    WHERE p.pid = ?
                ", [(int)$pid]);
                $post = $db->fetch_array($query);

                $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE pid = ?", [(int)$pid]);
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
            $quoted_posts_arr = array_map('intval', array_values($quoted_posts));
            $quoted_placeholders = implode(',', array_fill(0, count($quoted_posts_arr), '?'));
            $quoted_ids_arr   = [];

            $unviewable_forums = get_unviewable_forums();
            $inactiveforums    = get_inactive_forums();
            $uf_sql = $unviewable_forums ? "AND t.fid NOT IN ({$unviewable_forums})" : '';
            $if_sql = $inactiveforums    ? "AND t.fid NOT IN ({$inactiveforums})"    : '';

            $gp           = forum_permissions();
            $onlyusfids   = [];
            $onlyusforums = '';
            $onlyusforums_params = [];
            foreach ($gp as $gpfid => $fp) {
                if (isset($fp['canonlyviewownthreads']) && $fp['canonlyviewownthreads'] == 1) {
                    $onlyusfids[] = $gpfid;
                }
            }
            if (!empty($onlyusfids)) {
                $onlyusforums = "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid=?)"
                              . " OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
                $onlyusforums_params = [(int)$CURUSER['id']];
            }

            $visible_where = 'AND p.visible=1';

            require_once INC_PATH . '/functions_posting.php';

            $load_all = $mybb->get_input('load_all_quotes', MyBB::INPUT_INT);
            $query    = $db->sql_query_prepared("
                SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline,
                       u.username AS userusername
                FROM posts p
                LEFT JOIN threads t ON (t.tid=p.tid)
                LEFT JOIN users u ON (u.id=p.uid)
                WHERE p.pid IN ({$quoted_placeholders})
                      {$uf_sql} {$if_sql} {$onlyusforums} {$visible_where}
            ", array_merge($quoted_posts_arr, $onlyusforums_params));

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
                $L = $lang->newreply;
                [$multiquote_text, $multiquote_deselect, $multiquote_quote] = $external_quotes === 1
                    ? [$L['multiquote_external_one'], $L['multiquote_external_one_deselect'], $L['multiquote_external_one_quote']]
                    : [sprintf($L['multiquote_external'], $external_quotes), $L['multiquote_external_deselect'], $L['multiquote_external_quote']];

                $multiquote_external = compose_notice(
                    $multiquote_text . ' <a href="./newreply.php?tid=' . $tid . '" onclick="return Post.loadMultiQuoted();">' . $multiquote_quote . '</a>, '
                    . '<a href="javascript:void(0)" onclick="Post.clearMultiQuoted(); return false;">' . $multiquote_deselect . '</a>',
                    'info', 'fa-quote-left', 'multiquote_unloaded'
                );
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

    $subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';

    if (!empty($mybb->input['previewpost']) || $reply_errors !== '') {
        $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
        $subscription_method = get_subscription_method($tid, $postoptions);
        $subject             = $mybb->input['subject'];
    } elseif ($mybb->input['action'] === 'editdraft' && $CURUSER['id']) {
        $message = htmlspecialchars_uni($post['message']);
        $subject = $post['subject'];
        
		
		
        $subscription_method = get_subscription_method($tid);

    } else {
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
           

            $query = $db->sql_query_prepared("
                SELECT u.*
                FROM users u
                WHERE u.id = ?
            ", [(int)$CURUSER['id']]);
            $post = $db->fetch_array($query);

            $post['username']   = $username;
            if ($CURUSER['id']) $post['userusername'] = $CURUSER['username'];
            $post['message']    = $previewmessage;
            $post['subject']    = $subject;
        
            
            $post['dateline']   = TIMENOW;
            

            if ($mybb->get_input('pid', MyBB::INPUT_INT)) {
                $attachwhere_sql    = "pid = ?";
                $attachwhere_params = [$mybb->get_input('pid', MyBB::INPUT_INT)];
            } else {
                $attachwhere_sql    = "posthash = ?";
                $attachwhere_params = [$mybb->get_input('posthash')];
            }

            $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE {$attachwhere_sql}", $attachwhere_params);
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
        if ($pid) {
            $attachwhere_sql    = "pid = ?";
            $attachwhere_params = [(int)$pid];
        } else {
            $attachwhere_sql    = "posthash = ?";
            $attachwhere_params = [$posthash];
        }
        $attachments = '';

        $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE {$attachwhere_sql}", $attachwhere_params);
        while ($attachment = $db->fetch_array($query)) {
            $attachment['size']     = mksize($attachment['filesize']);
            $attachment['icon']     = get_attachment_icon(get_extension($attachment['filename']));
            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
            $aid                    = (string)(int)$attachment['aid'];

            $attachments .= compose_attach_item(
                $aid, $attachment['icon'], $attachment['filename'], $attachment['size'],
                compose_att_insert($aid, 'Insert Into Post') . compose_att_remove($aid, $lang->post3['btn_remove_attachment'])
            );
            $attachcount++;
        }

        $query        = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [(int)$CURUSER['id']]);
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
            $friendlyusage        = mksize($usage['ausage']);
            $attach_usage         = sprintf($lang->newreply['attach_usage'], $friendlyusage);
            $link_viewattachments = '<a href="usercp.php?action=attachments">' . $lang->global['view_attachments'] . '</a>';
        }

        $attach_buttons = compose_attach_buttons(
            $lang->post3['btn_add_attachment'],
            $lang->post3['btn_update_attachment'],
            $maxattachments == 0 || ($maxattachments != 0 && $attachcount < $maxattachments && !$noshowattach),
            $attachcount > 0
        );

        $attachbox = compose_attachbox($attach_quota, $link_viewattachments, $lang->post3['lbl_new_attachment'], $attach_buttons, $attachments);
    }

    // ── Save draft ────────────────────────────────────────────────────────────
    $savedraftbutton = '';
    if ($CURUSER['id']) {
        $savedraftbutton = '<button type="submit" class="btn btn-outline-secondary st-pill" name="savedraft" value="Save as Draft">'
                         . '<i class="fa-regular fa-note-sticky me-1"></i>Save as Draft</button>';
    }

    // ── Thread review ─────────────────────────────────────────────────────────
    $reviewmore   = '';
    $threadreview = '';

    $query    = $db->sql_query_prepared(
                    "SELECT COUNT(pid) AS post_count FROM posts WHERE tid = ? AND visible = 1",
                    [(int)$tid]
                );
    $numposts = (int)$db->fetch_field($query, 'post_count');

    if ($numposts > $f_postsperpage) {
        $numposts = $f_postsperpage;
        // В строке ланга сломан href (<a href=>"…"), поэтому берём только текст и даём свою ссылку
        $review_more_text = strip_tags(sprintf($lang->newreply['thread_review_more'], $f_postsperpage, get_thread_link($tid)));
        $reviewmore       = '<a class="cp-review__more" href="' . get_thread_link($tid) . '" target="_blank">'
                          . '<i class="fa-solid fa-arrow-up-right-from-square"></i>' . $review_more_text . '</a>';
    }

    $pidin = [];
    $query = $db->sql_query_prepared(
                "SELECT pid FROM posts WHERE tid = ? AND visible = 1 ORDER BY dateline DESC, pid DESC LIMIT {$f_postsperpage}",
                [(int)$tid]
            );
    while ($p = $db->fetch_array($query)) $pidin[] = (int)$p['pid'];

    if (!empty($pidin)) {
        $pidin_placeholders = implode(',', array_fill(0, count($pidin), '?'));

        $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE pid IN ({$pidin_placeholders})", $pidin);
        while ($att = $db->fetch_array($query)) {
            $attachcache[$att['pid']][$att['aid']] = $att;
        }

        $query = $db->sql_query_prepared("
            SELECT p.*, u.username AS userusername
            FROM posts p LEFT JOIN users u ON (p.uid=u.id)
            WHERE pid IN ({$pidin_placeholders})
            ORDER BY dateline DESC, pid DESC
        ", $pidin);

        $reviewbits = '';

        while ($post = $db->fetch_array($query)) {
            if ($post['userusername']) $post['username'] = $post['userusername'];

            $reviewpostdate = my_datee('relative', $post['dateline']);
            $parser_options = [
                'allow_html'     => 0, 'allow_mycode'   => 1, 'allow_smilies'  => 1,
                'allow_imgcode'  => 1, 'allow_videocode' => 1,
                'me_username'    => $post['username'],   'filter_badwords' => 1,
            ];

            $post['username'] = htmlspecialchars_uni($post['username']);

            $plugins->run_hooks('newreply_threadreview_post');

            $post['message'] = $parser->parse_message($post['message'], $parser_options);
            get_post_attachments($post['pid'], $post);

            $reviewbits .= '<div class="cp-review__item">'
                         . '<div class="cp-review__head"><span class="cp-review__who">' . $post['username'] . '</span>'
                         . '<span class="cp-review__date"><i class="fa-regular fa-clock"></i>' . $reviewpostdate . '</span></div>'
                         . '<div class="cp-review__body post_body scaleimages">' . $post['message'] . '</div>'
                         . '</div>';
        }

        $threadreview = '<div class="st-card cp-review">'
                      . '<div class="st-card__head"><span class="st-ico st-soft-info"><i class="fa-solid fa-clock-rotate-left"></i></span>'
                      . $lang->newreply['thread_review'] . '</div>'
                      . '<div class="cp-review__list">' . $reviewbits . '</div>'
                      . $reviewmore
                      . '</div>';
    }

    // ── Post / mod options ────────────────────────────────────────────────────
    $is_mod = is_mod($usergroups);

    $toggles = compose_toggle('collapse-postop', 'fa-gear', $lang->newreply['post_options']);
    $panels  = compose_panel(
        'collapse-postop', 'fa-bell', 'st-soft-primary', $lang->global['thread_subscription_method'],
        compose_subscription($subscribe, $nonesubscribe, $emailsubscribe, $pmsubscribe),
        $lang->global['thread_subscription_method_desc']
    );

    if ($is_mod) {
        if ($mybb->get_input('processed', MyBB::INPUT_INT)) {
            $modopts = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
            $closed  = (int)($modopts['closethread'] ?? 0);
            $stuck   = (int)($modopts['stickthread']  ?? 0);
        } else {
            $closed = $thread['closed'];
            $stuck  = $thread['sticky'];
        }

        $toggles .= compose_toggle('collapse-modop', 'fa-screwdriver-wrench', $lang->newreply['mod_options']);
        $panels  .= compose_panel(
            'collapse-modop', 'fa-shield-halved', 'st-soft-danger', $lang->newreply['mod_options'],
            compose_check('checkbox', 'modoptions[closethread]', '1', $closed ? 'checked="checked"' : '', $lang->newreply['close_thread'])
            . compose_check('checkbox', 'modoptions[stickthread]', '1', $stuck ? 'checked="checked"' : '', $lang->newreply['stick_thread'])
        );
    }

    if ($attachbox !== '') {
        $toggles .= compose_toggle('collapse-attach', 'fa-paperclip', $lang->global['attachments']);
        $panels  .= compose_panel('collapse-attach', 'fa-paperclip', 'st-soft-info', $lang->global['attachments'], $attachbox);
    }

    $post_reply_to = sprintf($lang->newreply['post_reply_to'], $thread['subject']);

    $moderation_notice = '';
    if ($CURUSER['moderateposts'] == 1) {
        $moderation_notice = compose_notice($lang->newreply['moderation_user_posts']);
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

    $plugins->run_hooks('newreply_end');

    $forum['name'] = strip_tags($forum['name']);

    stdhead($post_reply_to);
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=6">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/compose.css?ver=1">';
    echo postbit_css_link();
    build_breadcrumb();

    $head = compose_head(
        'fa-reply', 'st-soft-primary', $lang->newreply['post_new_reply'],
        compose_meta('fa-folder-open', '<a href="' . get_forum_link($fid) . '">' . htmlspecialchars_uni($forum['name']) . '</a>')
        . compose_meta('fa-comments', '<a href="' . get_thread_link($tid) . '">' . $thread['subject'] . '</a>')
    );

    $submitbar = compose_submitbar(
        '<button type="submit" class="btn btn-outline-secondary st-pill" name="previewpost" value="' . $lang->newreply['preview_post'] . '" tabindex="5">'
        . '<i class="fa-solid fa-eye me-1"></i>' . $lang->newreply['preview_post'] . '</button>' . $savedraftbutton,
        '<button type="submit" class="btn btn-primary st-pill px-4" name="post_thread" value="' . $lang->newreply['post_reply'] . '" tabindex="3" accesskey="s">'
        . '<i class="fa-solid fa-paper-plane me-1"></i>' . $lang->newreply['post_reply'] . '</button>'
    );

    echo $post_javascript . compose_insert_script() . '
<div class="container-md st-page cp-page">
' . $head . '
' . compose_preview($preview, $lang->newreply['preview_post']) . '
' . $maximageserror . $attacherror . $reply_errors . $moderation_notice . '
<form action="newreply.php?tid=' . $tid . '&amp;processed=1" method="post" enctype="multipart/form-data" name="input" class="cp-form">
<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
<div id="fileIdsContainer"></div>

<div class="st-card cp-editor">
    <div class="st-compose-body">
        ' . compose_input('fa-heading', '<input type="text" class="form-control" name="subject" maxlength="85" value="' . $subject . '" tabindex="1" aria-label="' . strip_tags($lang->newreply['post_subject']) . '" />') . '
        ' . $codebuttons . '
        <textarea id="message" name="message" class="form-control st-editor-text" rows="20" cols="70" tabindex="2">' . $message . '</textarea>
        ' . $multiquote_external . '
    </div>
    <div class="st-optbar">' . $toggles . '</div>
</div>

' . $panels . '
' . $submitbar . '

<input type="hidden" name="action" value="do_newreply" />
<input type="hidden" name="replyto" value="' . $replyto . '" />
<input type="hidden" name="posthash" value="' . $posthash . '" />
<input type="hidden" name="attachmentaid" value="" />
<input type="hidden" name="attachmentact" value="" />
<input type="hidden" name="quoted_ids" value="' . $quoted_ids . '" />
<input type="hidden" name="tid" value="' . $tid . '" />
' . $editdraftpid . '
</form>

' . $threadreview . '
</div>';

    stdfoot();
}
