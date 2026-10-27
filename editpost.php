<?php

declare(strict_types=1);



define("IN_MYBB", 1);
define('THIS_SCRIPT', 'editpost.php');
define("SCRIPTNAME", "editpost.php");



define('IN_FORUM', true);
require_once 'global.php';



require_once INC_PATH."/functions_post.php";
require_once INC_PATH . '/functions_compose.php';
require_once INC_PATH."/functions_upload.php";
require_once INC_PATH . '/functions_subscription_method.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/datahandler.php';

// Load global language phrases
$lang->load("editpost");
$lang->load("newthread");
$lang->load("post3");



$plugins->run_hooks("editpost_start");

// No permission for guests
if (!$CURUSER['id']) {
    print_no_permission();
}

// Get post info
$pid = $mybb->get_input('pid', MyBB::INPUT_INT);

// if we already have the post information...
if (isset($style) && $style['pid'] == $pid && $style['type'] != 'f') {
    $post = &$style;
} else {
    $post = get_post($pid);
}



if (!$post || $post['visible'] == -1) {
	stderr($lang->editpost['flash_invalid_post'], $SITENAME . ' - ' . $lang->editpost['title_post_not_found'], 404, '404');
}





// Get thread info
$tid = $post['tid'];
$thread = get_thread($tid);

if (!$thread) {
    error($lang->global['error_invalidthread']);
}

$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

// Get forum info
$fid = $post['fid'];
$forum = get_forum($fid);

$is_mod = is_mod($usergroups);
$showownunapproved = "0";

if ($thread['visible'] == 0 && !$is_mod || $thread['visible'] == -1 && $is_mod || ($thread['visible'] < -1 && $thread['uid'] != $CURUSER['id'])) {
    if ($thread['visible'] == 0 && !($showownunapproved && $thread['uid'] == $CURUSER['id'])) {
        error($lang->global['error_invalidthread']);
    }
}



if (!$forum || $forum['type'] != "f") {
	stderr($lang->global['error_closedinvalidforum'], $SITENAME . ' - ' . $lang->editpost['title_access_denied'], 403, '403');
}



if ($forum['open'] == 0 && !$is_mod) {
    print_no_permission();
}



// Make navigation
build_forum_breadcrumb($fid);
add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb($lang->editpost['nav_edit_post']);

$forumpermissions = forum_permissions($fid);

// Подключите функцию insert_bbcode_editor
require_once INC_PATH . '/editor.php';

// Вызов функции
$editor = insert_bbcode_editor($smilies, $BASEURL, 'message', $cerror ?? '');

$codebuttons = $editor['toolbar'] . $editor['modal'];

$mybb->input['action'] = $mybb->get_input('action');
if (!$mybb->input['action'] || isset($mybb->input['previewpost'])) {
    $mybb->input['action'] = "editpost";
}

if ($mybb->input['action'] == "deletepost" && $mybb->request_method == "post") {
    $firstpost = ($pid == $thread['firstpost']);

    $can_delete = $is_mod
        || ($firstpost && $forumpermissions['candeletethreads'] == 1 && $CURUSER['id'] == $post['uid'])
        || (!$firstpost && $forumpermissions['candeleteposts'] == 1 && $CURUSER['id'] == $post['uid']);

    if (!$can_delete) {
        print_no_permission();
    }

    if (!$is_mod && $thread['closed'] == 1) {
        error($lang->editpost['flash_thread_closed']);
    }

    // User can't delete unapproved post unless allowed for own
    if ($post['visible'] == 0 && !($showownunapproved && $post['uid'] == $CURUSER['id'])) {
        print_no_permission();
    }

    if ($post['visible'] == -1) {
        error($lang->editpost['flash_already_deleted']);
    }
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if ((empty($_POST) && empty($_FILES)) && $mybb->get_input('processed', MyBB::INPUT_INT) == '1') {
    error($lang->editpost['flash_empty_post_input']);
}

$attacherror = '';
$enableattachments = "1";

if ($enableattachments == 1 && ($mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || ((($mybb->input['action'] == "do_editpost" && isset($mybb->input['submitbutton'])) || ($mybb->input['action'] == "editpost" && isset($mybb->input['previewpost']))) && $_FILES['attachments']))) {
    // Verify incoming POST request
    verify_post_check($mybb->get_input('my_post_key'));

    if ($pid) {
        $attachwhere = "pid = ?";
        $attachwhere_params = [$pid];
    } else {
        $attachwhere = "posthash = ?";
        $attachwhere_params = [$mybb->get_input('posthash')];
    }

    $ret = add_attachments($pid, $forumpermissions, $attachwhere, "editpost", $attachwhere_params);

    if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
        if (isset($ret['success'])) {
            // Шаблон для post.js: {1}=aid, {2}=иконка, {3}=имя файла, {4}=размер.
            // Раньше: id="insertBtn" у каждого вложения (работала только первая кнопка),
            // кнопка «снять одобрение» выводилась и не-модераторам, а в её onclick
            // кавычки ломали атрибут ("unapprove" внутри onclick="...").
            $ret['template'] = compose_attach_item(
                '{1}', '{2}', '{3}', '{4}',
                compose_att_insert('{1}', $lang->editpost['btn_insert_into_post'])
                . ($is_mod ? compose_att_mod('{1}', false, $lang->editpost['btn_unapprove_attachment']) : '')
                . compose_att_remove('{1}', $lang->post3['btn_remove_attachment'])
            );

            $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [$CURUSER['id']]);
            $usage = $query ? $db->fetch_array($query) : null;
            $ret['usage'] = mksize($usage['ausage']);
        }
        
        header("Content-type: application/json; charset={$charset}");
        echo json_encode($ret);
        exit();
    }

    if (!empty($ret['errors'])) {
        $errors = $ret['errors'];
    }

    // Do we have attachment errors?
    if (!empty($errors)) {
        $attacherror = inline_error($errors);
    }

    // If we were dealing with an attachment but didn't click 'Update Post', force the post edit page again.
    if (!isset($mybb->input['submitbutton'])) {
        $mybb->input['action'] = "editpost";
    }
}

detect_attachmentact();

if ($enableattachments == 1 && $mybb->get_input('attachmentaid', MyBB::INPUT_INT) && isset($mybb->input['attachmentact']) && $mybb->input['action'] == "do_editpost" && $mybb->request_method == "post") {
    // Verify incoming POST request
    verify_post_check($mybb->get_input('my_post_key'));

    $mybb->input['attachmentaid'] = $mybb->get_input('attachmentaid', MyBB::INPUT_INT);
    if ($mybb->input['attachmentact'] == "remove") {
        remove_attachment($pid, "", $mybb->input['attachmentaid']);
    } elseif ($mybb->get_input('attachmentact') == "approve" && $is_mod) {
        $db->sql_query_prepared("UPDATE attachments SET visible = 1 WHERE aid = ?", [$mybb->input['attachmentaid']]);
        update_thread_counters((int)$post['tid'], ['attachmentcount' => "+1"]);
    } elseif ($mybb->get_input('attachmentact') == "unapprove" && $is_mod) {
        $db->sql_query_prepared("UPDATE attachments SET visible = 0 WHERE aid = ?", [$mybb->input['attachmentaid']]);
        update_thread_counters((int)$post['tid'], ['attachmentcount' => "-1"]);
    }

    if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [$CURUSER['id']]);
        $usage = $query ? $db->fetch_array($query) : null;

        header("Content-type: application/json; charset={$charset}");
        echo json_encode(["success" => true, "usage" => mksize($usage['ausage'])]);
        exit();
    }

    if (!isset($mybb->input['submitbutton'])) {
        $mybb->input['action'] = "editpost";
    }
}

if ($mybb->input['action'] == "deletepost" && $mybb->request_method == "post") {
    // Verify incoming POST request
    verify_post_check($mybb->get_input('my_post_key'));

    $firstpost = ($pid == $thread['firstpost']);

    // ── Реальная проверка прав перед удалением ──
    $can_delete = $is_mod
        || ($firstpost && $forumpermissions['candeletethreads'] == 1 && $CURUSER['id'] == $post['uid'])
        || (!$firstpost && $forumpermissions['candeleteposts'] == 1 && $CURUSER['id'] == $post['uid']);

    if (!$can_delete) {
        print_no_permission();
    }
    if (!$is_mod && $thread['closed'] == 1) {
        error($lang->editpost['flash_thread_closed']);
    }
    if (!$is_mod && $post['visible'] == 0 && !($showownunapproved && $post['uid'] == $CURUSER['id'])) {
        print_no_permission();
    }
    if ($post['visible'] == -1) {
        error($lang->editpost['flash_already_deleted']);
    }
    // ── конец проверки ──

    $plugins->run_hooks("editpost_deletepost");

    if ($mybb->get_input('delete', MyBB::INPUT_INT) == 1) {
        $modlogdata['fid'] = $fid;
        $modlogdata['tid'] = $tid;
        if ($firstpost) {
            require_once INC_PATH."/class_moderation.php";
            $moderation = new Moderation;

            $moderation->delete_thread((int)$tid);

            write_log('Thread (' . $tid . ' - ' . $thread['subject'] . ') has been deleted by ' . $CURUSER['username']);

            if ($mybb->input['ajax'] == 1) {
                header("Content-type: application/json; charset={$charset}");
                echo json_encode(["data" => '1', "first" => '1', "url" => get_forum_link($fid)]);
            } else {
                redirect(get_forum_link($fid), $lang->editpost['flash_thread_deleted']);
            }
        } else {
            require_once INC_PATH."/class_moderation.php";
            $moderation = new Moderation;

            $moderation->delete_post($pid);

            write_log('Post (' . $pid . ' - ' . $thread['subject'] . ') has been deleted by ' . $CURUSER['username']);

            $query = $db->sql_query_prepared("SELECT pid FROM posts WHERE tid = ? AND dateline <= ? ORDER BY dateline DESC, pid DESC LIMIT 1", [$tid, $post['dateline']]);
            $next_post = $query ? $db->fetch_array($query) : null;
            if ($next_post['pid']) {
                $redirect = get_post_link($next_post['pid'], $tid)."#pid{$next_post['pid']}";
            } else {
                $redirect = get_thread_link($tid);
            }

            if ($mybb->get_input('ajax', MyBB::INPUT_INT) == 1) {
                header("Content-type: application/json; charset={$charset}");
                echo json_encode(["data" => '1', "first" => '0', "url" => $redirect]);
            } else {
                redirect($redirect, $lang->editpost['flash_post_deleted']);
            }
        }
    } else {
        error($lang->editpost['flash_nodelete']);
    }
}

$postoptions = [];

if ($mybb->input['action'] == "do_editpost" && $mybb->request_method == "post") {
    // Verify incoming POST request
    verify_post_check($mybb->get_input('my_post_key'));

    $plugins->run_hooks("editpost_do_editpost_start");

    // Set up posthandler.
    require_once INC_PATH."/datahandlers/post.php";
    $posthandler = new PostDataHandler("update");
    $posthandler->action = "post";

    // Set the post data that came from the input to the $post array.
    $post = [
        "pid" => $mybb->input['pid'],
        "subject" => $mybb->get_input('subject'),
        "uid" => $post['uid'],
        "username" => $post['username'],
        "edit_uid" => $CURUSER['id'],
        "message" => $mybb->get_input('message'),
        "editreason" => $mybb->get_input('editreason'),
    ];

    $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
    if (!isset($postoptions['signature'])) {
        $postoptions['signature'] = 0;
    }
    if (!isset($postoptions['subscriptionmethod'])) {
        $postoptions['subscriptionmethod'] = 0;
    }
    if (!isset($postoptions['disablesmilies'])) {
        $postoptions['disablesmilies'] = 0;
    }

    // Set up the post options from the input.
    $post['options'] = [
        "signature" => $postoptions['signature'],
        "subscriptionmethod" => $postoptions['subscriptionmethod'],
        "disablesmilies" => $postoptions['disablesmilies']
    ];

    $posthandler->set_data($post);

    // Now let the post handler do all the hard work.
    if (!$posthandler->validate_post()) {
        $post_errors = $posthandler->get_friendly_errors();
        $post_errors = inline_error($post_errors);
        $mybb->input['action'] = "editpost";
    } else {
        $postinfo = $posthandler->update_post();
        $visible = $postinfo['visible'];
        $first_post = $postinfo['first_post'];

        // Help keep our attachments table clean.
        $db->sql_query_prepared("DELETE FROM attachments WHERE filename='' OR filesize<1");

        // Did the user choose to post a poll? Redirect them to the poll posting page.
        if ($mybb->get_input('postpoll', MyBB::INPUT_INT) && $forumpermissions['canpostpolls']) {
            $url = "polls.php?action=newpoll&tid=$tid&polloptions=".$mybb->get_input('numpolloptions', MyBB::INPUT_INT);
            $redirect_postedited = $lang->editpost['flash_post_edited_poll'];
        } elseif ($visible == 0 && $first_post && !$is_mod) {
            // Moderated post
            $redirect_postedited = $lang->editpost['flash_thread_moderation'];
            $url = get_forum_link($fid);
        } elseif ($visible == 0 && !$is_mod) {
            $redirect_postedited = $lang->editpost['flash_post_moderation'];
            $url = get_thread_link($tid);
        } else {
            $redirect_postedited = $lang->editpost['flash_post_edited'];
            $url = get_post_link($pid, $tid)."#pid{$pid}";
        }
        $plugins->run_hooks("editpost_do_editpost_end");

        redirect($url, $redirect_postedited);
    }
}

if (!$mybb->input['action'] || $mybb->input['action'] == "editpost") {
    $plugins->run_hooks("editpost_action_start");

    $preview = '';

    $CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);

    $deletebox = '';
	
	$is_mod = is_mod($usergroups);
	
    
    if (isset($post['visible']) && $post['visible'] != -1 && (($thread['firstpost'] == $pid && $is_mod || $forumpermissions['candeletethreads'] == 1 && $CURUSER['id'] == $post['uid'])) || ($thread['firstpost'] != $pid && ($is_mod || $forumpermissions['candeleteposts'] == 1 && $CURUSER['id'] == $post['uid']))) {	
        
		
// Подготовка данных для превью поста в модалке (тот же безопасный parser_options, что и везде)
$modal_username = htmlspecialchars_uni($post['username']);
$modal_postdate = my_datee(($dateformat ?? 'Y-m-d') . ' - ' . ($timeformat ?? 'H:i:s'), (int)$post['dateline']);

$modal_parser_options = [
    'allow_html'      => 0, 'allow_mycode'    => 1,
    'allow_smilies'   => 1, 'allow_imgcode'   => 1,
    'allow_videocode' => 1, 'filter_badwords' => 1,
];
$modal_message2 = $parser->parse_message($post['message'], $modal_parser_options);

$deletebox = '
<div class="st-card st-danger-box cp-delete">
    <span class="st-ico st-soft-danger"><i class="fa-solid fa-trash-can"></i></span>
    <div class="min-w-0 flex-grow-1">' . $lang->editpost['tip_delete_note'] . '</div>
    <button type="button" class="btn btn-outline-danger st-pill" data-bs-toggle="modal" data-bs-target="#deletePostModal' . $pid . '" tabindex="9">
        <i class="fa-solid fa-trash-can me-1"></i>' . $lang->editpost['btn_delete_now'] . '
    </button>
</div>';

// Модалка в стиле модалок постбита (.pb-modal из postbit.css). Id — как ждёт delete_post_editpost.js
$modal_delete = '<div class="modal fade pb-modal" id="deletePostModal' . $pid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">'
        . '<div class="modal-header">'
        . '<span class="pb-modal__ico pb-soft-danger"><i class="fa-solid fa-trash"></i></span>'
        . '<h5 class="modal-title">' . $lang->editpost['modal_delete_title'] . '</h5>'
        . '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
        . '</div>'
        . '<div class="modal-body">'
        . '<div class="pb-warn"><i class="fa-solid fa-triangle-exclamation"></i>'
        . '<div><div class="fw-semibold">' . $lang->editpost['modal_delete_confirm'] . '</div>'
        . '<div class="small">' . $lang->editpost['modal_delete_warning'] . '</div></div></div>'
        . '<div class="pb-quote">'
        . '<div class="pb-quote__head">'
        . '<span class="fw-semibold"><i class="fa-solid fa-user me-1"></i>' . $modal_username . '</span>'
        . '<span class="pb-quote__meta">' . $modal_postdate . ' · ' . sprintf($lang->editpost['lbl_pid'], $pid) . '</span>'
        . '</div>'
        . '<div class="pb-quote__body post_body">' . $modal_message2 . '</div>'
        . '</div>'
        . '<div id="deleteLoading' . $pid . '" class="text-center mt-3" style="display:none;">'
        . '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">' . $lang->editpost['modal_deleting_sr'] . '</span></div>'
        . '<p class="mt-2 text-body-secondary">' . $lang->editpost['modal_deleting'] . '</p>'
        . '</div>'
        . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">'
        . '<i class="fa-solid fa-xmark me-1"></i>' . $lang->editpost['btn_cancel'] . '</button>'
        . '<button type="button" class="btn btn-danger rounded-pill px-3" id="confirmDeleteBtn' . $pid . '">'
        . '<i class="fa-solid fa-trash me-1"></i>' . $lang->editpost['btn_delete_post'] . '</button>'
        . '</div>'
        . '</div></div></div>';

$deletebox .= $modal_delete;
		
		
		
		
		
		
    }
    
    $bgcolor = "trow1";
    
    
    if ($enableattachments != 0) {
        // Get a listing of the current attachments, if there are any
        $attachcount = 0;
        $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE pid = ?", [$pid]);
        $attachments = '';
        while ($query && ($attachment = $db->fetch_array($query))) {
            $attachment['size'] = mksize($attachment['filesize']);

            // Для картинок - маленькое превью самого файла вместо иконки типа файла
            $attach_ext = strtolower(get_extension($attachment['filename']));
            $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            if (in_array($attach_ext, $image_exts, true)) {
                $attachment['icon'] = '<img src="attachment.php?aid=' . (int)$attachment['aid'] . '" alt="" class="cp-att__thumb">';
            } else {
                $attachment['icon'] = get_attachment_icon($attach_ext);
            }

            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
            $aid        = (string)(int)$attachment['aid'];
            $unapproved = $attachment['visible'] != 1;

            // Moderating options
            $attach_mod_options = $is_mod
                ? compose_att_mod($aid, $unapproved, $lang->editpost[$unapproved ? 'btn_approve_attachment' : 'btn_unapprove_attachment'])
                : '';

            $attachments .= compose_attach_item(
                $aid, $attachment['icon'], $attachment['filename'], $attachment['size'],
                compose_att_insert($aid, $lang->editpost['btn_insert_into_post'])
                . $attach_mod_options
                . compose_att_remove($aid, $lang->post3['btn_remove_attachment']),
                $unapproved,
                $unapproved ? 'attachment.php?aid=' . $aid : ''
            );
            $attachcount++;
        }
        
        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [$CURUSER['id']]);
        $usage = $query ? $db->fetch_array($query) : null;
        
        if ($usage['ausage'] > ($usergroups['attachquota']*1024) && $usergroups['attachquota'] != 0) {
            $noshowattach = 1;
        } else {
            $noshowattach = 0;
        }
        
        if ($usergroups['attachquota'] == 0) {
            $friendlyquota = $lang->editpost['lbl_unlimited'];
        } else {
            $friendlyquota = mksize($usergroups['attachquota']*1024);
        }

        $attach_quota = sprintf($lang->editpost['tip_attach_quota'], $friendlyquota);

        $link_viewattachments = '';

        if ($usage['ausage'] !== null) {
            $friendlyusage = mksize($usage['ausage']);
            $attach_usage = sprintf($lang->editpost['tip_attach_usage'], $friendlyusage);
            
			$link_viewattachments = '<a href="usercp.php?action=attachments">'.$lang->editpost['lbl_view_attachments'].'</a>';
			
			
        } else {
            $attach_usage = "";
        }

        $attach_buttons = compose_attach_buttons(
            $lang->post3['btn_add_attachment'],
            $lang->post3['btn_update_attachment'],
            $maxattachments == 0 || ($maxattachments != 0 && $attachcount < $maxattachments) && !$noshowattach,
            $attachcount > 0
        );

        $attachbox = compose_attachbox($attach_quota, $link_viewattachments, $lang->post3['lbl_new_attachment'], $attach_buttons, $attachments);
    } else {
        $attachbox = '';
    }
    
    if (!$mybb->get_input('attachmentaid', MyBB::INPUT_INT) && !$mybb->get_input('newattachment') && !$mybb->get_input('updateattachment') && !isset($mybb->input['previewpost'])) {
        $message = $post['message'];
        $subject = $post['subject'];
        $reason = htmlspecialchars_uni($post['editreason']);
    } else {
        $message = $mybb->get_input('message');
        $subject = $mybb->get_input('subject');
        $reason = htmlspecialchars_uni($mybb->get_input('editreason'));
    }

    $previewmessage = $message;
    $previewsubject = $subject;
    $message = htmlspecialchars_uni($message);
    $subject = htmlspecialchars_uni($subject);

    if (!isset($post_errors)) {
        $post_errors = '';
    }

    $subscribe = $nonesubscribe = $emailsubscribe = $pmsubscribe = '';
    $postoptionschecked = ['signature' => '', 'disablesmilies' => ''];

    if (!empty($mybb->input['previewpost']) || $post_errors) {
        // Set up posthandler.
        require_once INC_PATH."/datahandlers/post.php";
        $posthandler = new PostDataHandler("update");
        $posthandler->action = "post";

        // Set the post data that came from the input to the $post array.
        $post = [
            "pid" => $mybb->input['pid'],
            "subject" => $mybb->get_input('subject'),
            "uid" => $post['uid'],
            "username" => $post['username'],
            "edit_uid" => $CURUSER['id'],
            "message" => $mybb->get_input('message'),
        ];

        $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);
        if (!isset($postoptions['signature'])) {
            $postoptions['signature'] = 0;
        }
        if (!isset($postoptions['emailnotify'])) {
            $postoptions['emailnotify'] = 0;
        }
        if (!isset($postoptions['disablesmilies'])) {
            $postoptions['disablesmilies'] = 0;
        }

        // Set up the post options from the input.
        $post['options'] = [
            "signature" => $postoptions['signature'],
            "emailnotify" => $postoptions['emailnotify'],
            "disablesmilies" => $postoptions['disablesmilies']
        ];

        $posthandler->set_data($post);

        // Now let the post handler do all the hard work.
        if (!$posthandler->validate_post()) {
            $post_errors = $posthandler->get_friendly_errors();
            $post_errors = inline_error($post_errors);
            $mybb->input['action'] = "editpost";
            $mybb->input['previewpost'] = 0;
        } else {
            $postoptions = $mybb->get_input('postoptions', MyBB::INPUT_ARRAY);

            if (isset($postoptions['signature']) && $postoptions['signature'] == 1) {
                $postoptionschecked['signature'] = " checked=\"checked\"";
            }

            if (isset($postoptions['disablesmilies']) && $postoptions['disablesmilies'] == 1) {
                $postoptionschecked['disablesmilies'] = " checked=\"checked\"";
            }
            
            $subscription_method = get_subscription_method((int)$tid, $postoptions);
            ${$subscription_method.'subscribe'} = "checked=\"checked\" ";
        }
    }

    if (!empty($mybb->input['previewpost'])) {
        if (!$post['uid']) {
            $query = $db->sql_query_prepared("SELECT username, dateline FROM posts WHERE pid = ?", [$pid]);
            $postinfo = $query ? $db->fetch_array($query) : null;
        } else {
            $sql = "
               SELECT u.*, p.dateline
               FROM users u
               LEFT JOIN posts p ON (p.uid = u.id)
               WHERE u.id = ? AND p.pid = ? LIMIT 1";

            $params = [
                (int)$post['uid'],
                (int)$pid
            ];

            $query = $db->sql_query_prepared($sql, $params);
            $postinfo = $query ? $db->fetch_array($query) : null;

            $postinfo['userusername'] = $postinfo['username'];
        }

        $query = $db->sql_query_prepared("SELECT * FROM attachments WHERE pid = ?", [$pid]);
        while ($query && ($attachment = $db->fetch_array($query))) {
            $attachcache[0][$attachment['aid']] = $attachment;
        }

        if (!isset($postoptions['disablesmilies'])) {
            $postoptions['disablesmilies'] = 0;
        }

        // Set the values of the post info array.
        $postinfo['message'] = $previewmessage;
        $postinfo['subject'] = $previewsubject;
        $postinfo['smilieoff'] = $postoptions['disablesmilies'];

        $postbit = build_postbit($postinfo, 1);
        $preview = $postbit;
    } elseif (!$post_errors) {
        $preview = '';



        $subscription_method = get_subscription_method((int)$tid, $postoptions);
        ${$subscription_method.'subscribe'} = "checked=\"checked\" ";
    }

   
    $closeoption = '';
    $stickoption = '';
    if ($is_mod) {
        $closeoption = '
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="postoptions[closethread]" id="closethread" value="1"' . (($thread['closed'] ?? 0) == 1 ? ' checked="checked"' : '') . ' />
            <label class="form-check-label" for="closethread">' . $lang->editpost['lbl_close_thread'] . '</label>
        </div>';
        $stickoption = '
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="postoptions[stickthread]" id="stickthread" value="1"' . (($thread['sticky'] ?? 0) == 1 ? ' checked="checked"' : '') . ' />
            <label class="form-check-label" for="stickthread">' . $lang->editpost['lbl_stick_thread'] . '</label>
        </div>';
    }

    $editreason = '';
    $alloweditreason = "1";
    
    if ($alloweditreason == 1) {
        
        $editreason = compose_input('fa-comment-dots',
            '<input type="text" class="form-control" name="editreason" size="40" placeholder="' . $lang->editpost['ph_edit_reason'] . '" maxlength="150" value="' . $reason . '" tabindex="5" />');
		
		
        $bgcolor = "trow2";
        $bgcolor2 = "trow1";
    } else {
        $bgcolor = "trow1";
        $bgcolor2 = "trow2";
    }

    // Fetch subscription select box
    $subscriptionmethod = compose_subscription($subscribe, $nonesubscribe, $emailsubscribe, $pmsubscribe);
	
	
	
	
	
	
	
	

    $query = $db->sql_query_prepared("SELECT * FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1", [$tid]);
    $firstcheck = $query ? $db->fetch_array($query) : null;

    $time = TIMENOW;
    $polltimelimit = "12";
    
    if ($firstcheck['pid'] == $pid && $thread['poll'] < 1 && $is_mod) {
        $maxpolloptions = "10";
        $max_options = sprintf($lang->editpost['hint_poll_max_options'], $maxpolloptions);
        $numpolloptions = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
        $postpollchecked = '';
        
        if ($numpolloptions < 1) {
            $numpolloptions = 2;
        }
        
        if ($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1) {
            $postpollchecked = 'checked="checked"';
        }
        
        $pollbox = compose_toggle('collapse-pollop', 'fa-square-poll-vertical', $lang->editpost['pane_poll']);

        // Панель строится тем же условием, что и кнопка (иначе Undefined variable $postpollchecked)
        $pollpanel = compose_panel(
            'collapse-pollop', 'fa-square-poll-vertical', 'st-soft-warning', $lang->editpost['pane_poll'],
            compose_check('checkbox', 'postpoll', '1', $postpollchecked, $lang->editpost['lbl_poll_check'])
            . '<div class="cp-pollnum">'
            . '<label class="form-label small mb-1" for="cp_numpolloptions">' . $lang->editpost['lbl_poll_num_options'] . '</label>'
            . '<div class="d-flex flex-wrap align-items-center gap-2">'
            . '<input type="number" min="2" max="' . $maxpolloptions . '" class="form-control form-control-sm" name="numpolloptions" id="cp_numpolloptions" value="' . $numpolloptions . '" />'
            . '<span class="st-meta">' . $max_options . '</span></div></div>',
            $lang->editpost['tip_poll_desc']
        );
		
		
		
    } else {
        $pollbox = '';
        $pollpanel = '';
    }

    $signature = '';
    $disablesmilies = '';

    $postoptions = '';
    
    $postoptions = compose_toggle('collapse-postop', 'fa-gear', $lang->editpost['pane_post_options']);
	
	

    $moderation_notice = '';

    $php_max_upload_size = get_php_upload_limit();
    $php_max_file_uploads = (int)ini_get('max_file_uploads');
    
    
    $post_javascript = '
	
	<script type="text/javascript">
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
			<script type="text/javascript" src="'.$BASEURL.'/scripts/toast.js?ver=7"></script>
			<script type="text/javascript" src="'.$BASEURL.'/scripts/post.js?ver=234"></script>';	
	
	

	
            
    $plugins->run_hooks("editpost_end");

    $forum['name'] = strip_tags($forum['name']);

    // ── Панели опций ─────────────────────────────────────────────────────────
    // Модераторские «закрыть/закрепить» не выводим: кнопки-переключателя для них никогда
    // не было, а do_editpost эти поля (postoptions[closethread/stickthread]) не обрабатывает.
    $toggles = $postoptions;
    $panels  = compose_panel(
        'collapse-postop', 'fa-bell', 'st-soft-primary', $lang->global['thread_subscription_method'],
        $signature . $subscriptionmethod,
        $lang->global['thread_subscription_method_desc']
    );
    if ($attachbox !== '') {
        $toggles .= compose_toggle('collapse-attach', 'fa-paperclip', $lang->editpost['pane_attachments']);
        $panels  .= compose_panel('collapse-attach', 'fa-paperclip', 'st-soft-info', $lang->editpost['pane_attachments'], $attachbox);
    }
    $toggles .= $pollbox;
    $panels  .= $pollpanel;

    $head = compose_head(
        'fa-pen', 'st-soft-warning', $lang->editpost['pane_edit_post'],
        compose_meta('fa-folder-open', '<a href="' . get_forum_link($fid) . '">' . htmlspecialchars_uni($forum['name']) . '</a>')
        . compose_meta('fa-comments', '<a href="' . get_post_link($pid, $tid) . '#pid' . $pid . '">' . $thread['subject'] . '</a>')
    );

    $submitbar = compose_submitbar(
        '<button type="submit" class="btn btn-outline-secondary st-pill" name="previewpost" value="' . $lang->editpost['btn_preview_post'] . '" tabindex="5">'
        . '<i class="fa-solid fa-eye me-1"></i>' . $lang->editpost['btn_preview_post'] . '</button>',
        '<button type="submit" class="btn btn-primary st-pill px-4" name="submitbutton" value="' . $lang->editpost['btn_update_post'] . '" tabindex="3" accesskey="s">'
        . '<i class="fa-solid fa-floppy-disk me-1"></i>' . $lang->editpost['btn_update_post'] . '</button>'
    );

    $editpost = $post_javascript . compose_insert_script() . '
<div class="container-md st-page cp-page">
' . $head . '
' . compose_preview($preview, $lang->editpost['btn_preview_post']) . '
' . $post_errors . $attacherror . $moderation_notice . '
' . $deletebox . '
<form id="editpost" action="editpost.php?pid=' . $pid . '&amp;processed=1" method="post" enctype="multipart/form-data" name="input" class="cp-form">
<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
<div id="fileIdsContainer"></div>

<div class="st-card cp-editor">
    <div class="st-compose-body">
        ' . $editreason . '
        ' . compose_input('fa-heading', '<input type="text" class="form-control" name="subject" maxlength="85" value="' . $subject . '" tabindex="1" />') . '
        ' . $codebuttons . '
        <textarea id="message" name="message" class="form-control st-editor-text" rows="20" cols="70" tabindex="2">' . $message . '</textarea>
    </div>
    <div class="st-optbar">' . $toggles . '</div>
</div>

' . $panels . '
' . $submitbar . '

<input type="hidden" name="action" value="do_editpost" />
<input type="hidden" name="attachmentaid" value="" />
<input type="hidden" name="attachmentact" value="" />
</form>
</div>';

    stdhead();
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=6">';
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/compose.css?ver=1">';
    echo postbit_css_link();   // модалка удаления — в стиле постбита
    build_breadcrumb();

	// JS-строки из ланга (ключи js_*) -> AGS_LANG без префикса
	$ags_lang = [];
	foreach ([$lang->post3, $lang->editpost] as $ags_src) {
		foreach ($ags_src as $ags_key => $ags_val) {
			if (str_starts_with((string)$ags_key, 'js_')) {
				$ags_lang[substr((string)$ags_key, 3)] = $ags_val;
			}
		}
	}
	echo '<script>const AGS_LANG = ' . json_encode($ags_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
	echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/delete_post_editpost.js?ver=1"></script>';

    echo $editpost;
    stdfoot();
}