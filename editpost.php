<?php

declare(strict_types=1);



define("IN_MYBB", 1);
define('THIS_SCRIPT', 'editpost.php');
define("SCRIPTNAME", "editpost.php");



define('IN_FORUM', true);
require_once 'global.php';



require_once INC_PATH."/functions_post.php";
require_once INC_PATH."/functions_upload.php";
require_once INC_PATH . '/functions_subscription_method.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/datahandler.php';

// Load global language phrases
$lang->load("editpost");
$lang->load("newthread");

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
	stderr($lang->global['error_invalidpost'], $SITENAME . ' - Post Not Found', 404, '404');
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
	stderr($lang->global['error_closedinvalidforum'], $SITENAME . ' - Access Denied', 403, '403');
}



if ($forum['open'] == 0 && !$is_mod) {
    print_no_permission();
}


$breadcrumbprefix = '';

// Make navigation
build_forum_breadcrumb($fid);
add_breadcrumb($breadcrumbprefix.$thread['subject'], get_thread_link($thread['tid']));
add_breadcrumb('Edit Post');

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
        error($lang->global['redirect_threadclosed'] ?? 'Thread is closed');
    }

    // User can't delete unapproved post unless allowed for own
    if ($post['visible'] == 0 && !($showownunapproved && $post['uid'] == $CURUSER['id'])) {
        print_no_permission();
    }

    if ($post['visible'] == -1) {
        error($lang->editpost['error_already_deleted'] ?? 'Already deleted');
    }
}

// Check if this forum is password protected and we have a valid password
check_forum_password($forum['fid']);

if ((empty($_POST) && empty($_FILES)) && $mybb->get_input('processed', MyBB::INPUT_INT) == '1') {
    error('error_empty_post_input');
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
            $attachment = ['aid'=>'{1}', 'icon'=>'{2}', 'filename'=>'{3}', 'size'=>'{4}'];
            
            $postinsert = '<input type="button" class="btn btn-page" name="insert" value="Insert Into Post" id="insertBtn" />

<script>
  document.getElementById("insertBtn").addEventListener("click", function(event) {
    event.preventDefault();
    const attachmentId = ' . $attachment['aid'] . ';
    const textarea = document.getElementById("message");
    const textToInsert = `[attachment=${attachmentId}]`;
    textarea.value += textToInsert;
  });
</script>';
            
            // Moderating options
            $attach_mod_options = '';
            
			$attach_mod_options = '
			
			<input type="submit" class="btn btn-page" name="unapproveattach_'.$attachment['aid'].'" value="'.$lang->editpost['unapprove_attachment'].'" onclick="return Post.attachmentAction('.$attachment['aid'].',"unapprove");" />
			';
			
			
			
            
            $attach_rem_options = '
			
			<input type="submit" class="btn btn-page" name="rem_'.$attachment['aid'].'" value="'.$lang->editpost['remove_attachment'].'" onclick="return Post.removeAttachment('.$attachment['aid'].');" />
			
			';
			
			
			
            $attemplate = '
			
			
			<div class="bg-nav rounded p-2 small mt-2" id="attachment_'.$attachment['aid'].'" data-filename="'.$attachment['filename'].'">
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
        error($lang->global['redirect_threadclosed'] ?? 'Thread is closed');
    }
    if (!$is_mod && $post['visible'] == 0 && !($showownunapproved && $post['uid'] == $CURUSER['id'])) {
        print_no_permission();
    }
    if ($post['visible'] == -1) {
        error($lang->editpost['error_already_deleted'] ?? 'Already deleted');
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
                redirect(get_forum_link($fid), 'Thank you, the thread has been deleted.<br />You will now be returned to the forum');
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
                redirect($redirect, 'Thank you, the post has been deleted.<br />You will now be returned to the thread');
            }
        }
    } else {
        error($lang->editpost['redirect_nodelete'] ?? 'Delete cancelled');
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
        "prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
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
            $redirect_postedited = $lang->editpost['redirect_postedited_poll'] ?? 'Post edited - redirect to poll';
        } elseif ($visible == 0 && $first_post && !$is_mod) {
            // Moderated post
            $redirect_postedited = $lang->editpost['redirect_thread_moderation'] ?? 'Thread awaiting moderation';
            $url = get_forum_link($fid);
        } elseif ($visible == 0 && !$is_mod) {
            $redirect_postedited = $lang->editpost['redirect_post_moderation'] ?? 'Post awaiting moderation';
            $url = get_thread_link($tid);
        } else {
            $redirect_postedited = $lang->editpost['redirect_postedited_redirect'] ?? 'Post edited';
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
<div class="card border-0 mb-4">
    <div class="card-header rounded-bottom text-19 fw-bold">
        '.$lang->editpost['edit_post'].'
    </div>
    <div class="card-body">
        <div class="row g-2 pb-3 border-bottom">
            <div class="col align-self-center">
                <i class="fa-solid fa-circle-exclamation text-danger"></i> '.$lang->editpost['delete_2'].'
            </div>
            <div class="col-auto text-end align-self-center">
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deletePostModal'.$pid.'" tabindex="9">
                    <i class="fa-solid fa-trash-can"></i> '.$lang->editpost['delete_now'].'
                </button>
            </div>
        </div>
    </div>
</div>';

$modal_delete = '<div class="modal fade" id="deletePostModal' . $pid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">'
        . '<div class="modal-header bg-danger text-white">'
        . '<h5 class="modal-title"><i class="fa-solid fa-trash me-2"></i>Delete Post</h5>'
        . '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>'
        . '</div>'
        . '<div class="modal-body">'
        . '<div class="text-center mb-3">'
        . '<i class="fa-solid fa-triangle-exclamation text-warning fa-3x mb-3"></i>'
        . '<h6 class="fw-bold">Are you sure you want to delete this post?</h6>'
        . '<p class="text-muted mb-0">This action cannot be undone.</p>'
        . '</div>'
        . '<div class="card border-danger border-opacity-25 mb-3">'
        . '<div class="card-header py-2 px-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">'
        . '<span class="small fw-bold text-danger"><i class="fas fa-user me-1"></i>' . $modal_username . '</span>'
        . '<div class="d-flex gap-2 align-items-center">'
        . '<span class="text-muted small">' . $modal_postdate . '</span>'
        . '<span class="badge bg-secondary">PID: ' . $pid . '</span>'
        . '</div>'
        . '</div>'
        . '<div class="card-body py-2 px-3">'
        . '<div class="small" style="max-height:350px;overflow-y:auto;">'
        . '<style>#deletePostModal' . $pid . ' .post_body img{max-width:100%;height:auto;}'
        . '#deletePostModal' . $pid . ' .post_body blockquote{font-size:0.8rem;padding:0.5rem;}</style>'
        . '<div>' . $modal_message2 . '</div>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '<div id="deleteLoading' . $pid . '" class="text-center mt-3" style="display:none;">'
        . '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Deleting...</span></div>'
        . '<p class="mt-2 text-muted">Deleting post...</p>'
        . '</div>'
        . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">'
        . '<i class="fa-solid fa-xmark me-1"></i>Cancel</button>'
        . '<button type="button" class="btn btn-danger" id="confirmDeleteBtn' . $pid . '">'
        . '<i class="fa-solid fa-trash me-1"></i>Delete Post</button>'
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
                $attachment['icon'] = '<img src="attachment.php?aid=' . $attachment['aid'] . '" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;vertical-align:middle;">';
            } else {
                $attachment['icon'] = get_attachment_icon($attach_ext);
            }

            $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

            $postinsert = '<input type="button" class="btn btn-page" name="insert" value="Insert Into Post" id="insertBtn" />

<script>
  document.getElementById("insertBtn").addEventListener("click", function(event) {
    event.preventDefault();
    const attachmentId = ' . $attachment['aid'] . ';
    const textarea = document.getElementById("message");
    const textToInsert = `[attachment=${attachmentId}]`;
    textarea.value += textToInsert;
  });
</script>';
            
            // Moderating options
            $attach_mod_options = '';
            if ($is_mod) {
                if ($attachment['visible'] == 1) {
                    
					$attach_mod_options = '
					
					
					<input type="submit" class="btn btn-page" name="unapproveattach_'.$attachment['aid'].'" value="'.$lang->editpost['unapprove_attachment'].'" onclick="return Post.attachmentAction('.$attachment['aid'].',"unapprove");" />
					
					';

					
                } else {
                    
					$attach_mod_options = '
					
					<input type="submit" class="btn btn-page" name="approveattach_'.$attachment['aid'].'" value="'.$lang->editpost['approve_attachment'].'" onclick="return Post.attachmentAction('.$attachment['aid'].',"approve");" />
					
					';
					
					
					
                }
            }

            // Remove Attachment
            $attach_rem_options = '
			
			
			<input type="submit" class="btn btn-page" name="rem_'.$attachment['aid'].'" value="'.$lang->editpost['remove_attachment'].'" onclick="return Post.removeAttachment('.$attachment['aid'].');" />
			
			
			
			';
			
			
            
            if ($attachment['visible'] != 1) {
                
				
				$attachments .= '
				
				<div class="alert bg-danger border-0 mt-2" id="attachment_'.$attachment['aid'].'" data-filename="'.$attachment['filename'].'">
<div class="row">
<div class="col-8 text-start">
		
		'.$attachment['icon'].' <a href="attachment.php?aid='.$attachment['aid'].'" target="_blank">'.$attachment['filename'].'</a> ('.$attachment['size'].') 
		
	</div>
	<div class="col-4 text-end">
		'.$attach_mod_options.' '.$attach_rem_options.' '.$postinsert.'
	</div>
</div>
</div>
				
				
				';
				
				
				
            } else {
                
				
				$attachments .= '

				<div class="bg-nav rounded p-2 small mt-2" id="attachment_'.$attachment['aid'].'" data-filename="'.$attachment['filename'].'">
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
        
        $query = $db->sql_query_prepared("SELECT SUM(filesize) AS ausage FROM attachments WHERE uid = ?", [$CURUSER['id']]);
        $usage = $query ? $db->fetch_array($query) : null;
        
        if ($usage['ausage'] > ($usergroups['attachquota']*1024) && $usergroups['attachquota'] != 0) {
            $noshowattach = 1;
        } else {
            $noshowattach = 0;
        }
        
        if ($usergroups['attachquota'] == 0) {
            $friendlyquota = 'unlimited';
        } else {
            $friendlyquota = mksize($usergroups['attachquota']*1024);
        }

        $attach_quota = sprintf($lang->editpost['attach_quota'] ?? 'Attachment quota: %s', $friendlyquota);

        $link_viewattachments = '';

        if ($usage['ausage'] !== null) {
            $friendlyusage = mksize($usage['ausage']);
            $attach_usage = sprintf($lang->editpost['attach_usage'] ?? 'Attachment usage: %s', $friendlyusage);
            
			$link_viewattachments = '<a href="usercp.php?action=attachments">'.$lang->global['view_attachments'].'</a>';
			
			
        } else {
            $attach_usage = "";
        }

        $attach_update_options = '';
        $attach_add_options = '';
        
        if ($maxattachments == 0 || ($maxattachments != 0 && $attachcount < $maxattachments) && !$noshowattach) {
            
			$attach_add_options = '
			
			<button type="submit" class="btn btn-primary" name="newattachment" value="'.$lang->editpost['add_attachment'].'" tabindex="13"><i class="fa-solid fa-upload"></i> &nbsp;'.$lang->editpost['add_attachment'].'</button>
			
			';
			
			
        }

        if ($attachcount > 0) {
            
			$attach_update_options = '
			
			<button type="submit" class="btn btn-primary" name="updateattachment" value="'.$lang->editpost['update_attachment'].'" tabindex="12"><i class="fa-solid fa-check"></i> &nbsp;'.$lang->editpost['update_attachment'].'&nbsp;</button>
			
			
			';	
			
			
        }

        if ($attach_add_options || $attach_update_options) {
            
			
			$newattach = '
			
			<div class="row mb-2">
	<div class="col-12">

		<div id="upload_bar" style="background: #0066A2; height: 5px; width: 0%;"></div>
		<div id="dropzone" style="padding: 30px 0; background: #f0faf6; cursor: pointer; border-radius: 5px; text-align: center; width:100%">
			<img src="pic/paperclip.png" alt="" />
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
            "prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
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

   
    if ($thread['firstpost'] == $pid) {
        if (!$mybb->get_input('threadprefix', MyBB::INPUT_INT)) {
            $mybb->input['threadprefix'] = $thread['prefix'];
        }
    }
    $prefixselect = "";

   
    $closeoption = '';
    $stickoption = '';
    if ($is_mod) {
        $closeoption = '
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="postoptions[closethread]" id="closethread" value="1"' . (($thread['closed'] ?? 0) == 1 ? ' checked="checked"' : '') . ' />
            <label class="form-check-label" for="closethread">' . ($lang->editpost['close_thread'] ?? 'Close Thread') . '</label>
        </div>';
        $stickoption = '
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="postoptions[stickthread]" id="stickthread" value="1"' . (($thread['sticky'] ?? 0) == 1 ? ' checked="checked"' : '') . ' />
            <label class="form-check-label" for="stickthread">' . ($lang->editpost['stick_thread'] ?? 'Stick Thread') . '</label>
        </div>';
    }

    $editreason = '';
    $alloweditreason = "1";
    
    if ($alloweditreason == 1) {
        
		$editreason = '
		
		<input type="text" class="form-control border mb-3" name="editreason" size="40" placeholder="'.$lang->editpost['editreason'].'" maxlength="150" value="'.$reason.'" tabindex="5" />
		
		';
		
		
        $bgcolor = "trow2";
        $bgcolor2 = "trow1";
    } else {
        $bgcolor = "trow1";
        $bgcolor2 = "trow2";
    }

    // Fetch subscription select box
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
	
	
	
	
	
	
	
	

    $query = $db->sql_query_prepared("SELECT * FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1", [$tid]);
    $firstcheck = $query ? $db->fetch_array($query) : null;

    $time = TIMENOW;
    $polltimelimit = "12";
    
    if ($firstcheck['pid'] == $pid && $thread['poll'] < 1 && $is_mod) {
        $maxpolloptions = "10";
        $max_options = sprintf($lang->editpost['max_options'] ?? 'Max options: %s', $maxpolloptions);
        $numpolloptions = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
        $postpollchecked = '';
        
        if ($numpolloptions < 1) {
            $numpolloptions = 2;
        }
        
        if ($mybb->get_input('postpoll', MyBB::INPUT_INT) == 1) {
            $postpollchecked = 'checked="checked"';
        }
        
        $pollbox = '
		
		
		&nbsp;&nbsp;<a class="links" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-pollop" role="button"><i class="fa-solid fa-circle-plus"></i> &nbsp;'.$lang->newthread['poll'].'</a>&nbsp;&nbsp;
		
		
		
		
		';

        // FIX: панель #collapse-pollop раньше вставлялась как голый HTML
        // без всякого условия ниже по шаблону, независимо от того, есть ли
        // вообще кнопка-триггер ($pollbox) её открыть - отсюда "Undefined
        // variable $postpollchecked/$numpolloptions", когда условие выше
        // ложное (не мод / уже есть опрос / не первый пост). Строим панель
        // тем же условием, что и саму кнопку.
        $pollpanel = '
<!-- pollop -->
<div id="collapse-pollop" class="collapse mt-4">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->editpost['poll'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->editpost['poll'].'
</div>
<div class="col">
<div class="text-desc mb-3">'.$lang->editpost['poll_desc'].'</div>
<label><input type="checkbox" class="form-check-input" name="postpoll" value="1" '.$postpollchecked.' />&nbsp; '.$lang->editpost['poll_check'].'</label>	
<div class="mt-3">'.$lang->editpost['num_options'].'<input type="text" class="form-control border form-control-sm border" style="width: 250px" name="numpolloptions" value="'.$numpolloptions.'" size="10" /> &nbsp;'.$lang->editpost['max_options'].'
</div>	
</div>
</div>
</div>
<!-- pollop -->';
		
		
		
    } else {
        $pollbox = '';
        $pollpanel = '';
    }

    $signature = '';
    $disablesmilies = '';

    $postoptions = '';
    
	$postoptions = '
	
	<a class="links" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-postop" role="button"><i class="fa-solid fa-gear"></i> &nbsp;'.$lang->editpost['post_options'].'</a>
	
	';
	
	

    $moderation_notice = '';

    $php_max_upload_size = get_php_upload_limit();
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
	
	

	
            
    $plugins->run_hooks("editpost_end");

    $forum['name'] = strip_tags($forum['name']);

    $editpost = '
	
	
	<html>
<head>
<title>'.$SITENAME.' - '.$lang->editpost['edit_post'].'</title>

'.$post_javascript.'
		
</head>
<body>

	
	<div class="container-md">
		
'.$preview.'
'.$post_errors.'
'.$attacherror.'
'.$moderation_notice.'
'.$deletebox.'
<form id="editpost" action="editpost.php?pid='.$pid.'&amp;processed=1" method="post" enctype="multipart/form-data" name="input">
<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
<div id="fileIdsContainer"></div>
	
	

		<div class="row m-0 mb-3 p-0 pb-2 border-bottom">
			<div class="col align-self-center m-0 p-0">
'.$editreason.'
			</div>
	</div>
	
	<div class="row m-0 mb-3 p-0 pb-2 border-bottom">
'.$prefixselect.'
			 <div class="col align-self-center m-0 p-0">
			 <input type="text" class="form-control border mb-3" name="subject" maxlength="85" value="'.$subject.'" tabindex="1" />
		</div>
	</div>
		'.$codebuttons.' 
<div class="row">
	<div class="col">
<textarea id="message" name="message" class="form-control form-control-sm border" style="height: 400px" rows="20" cols="70" tabindex="2" >'.$message.'</textarea>
		</div>

	</div>
	
	

<div class="mt-2 mb-3">
'.$postoptions.'		
<a class="links" data-bs-toggle="collapse" aria-expanded="false" aria-controls="collapse-1" href="#collapse-attach" role="button"><i class="fa-solid fa-paperclip"></i> &nbsp;'.$lang->editpost['attachments'].'</a>		
'.$pollbox.'
<button type="submit" class="btn-thread" name="previewpost" value="'.$lang->editpost['preview_post'].'" tabindex="5"><i class="fa-solid fa-pen"></i> &nbsp;'.$lang->editpost['preview_post'].'</button>

		

<!-- pollop -->
'.$pollpanel.'
 <!-- attach -->
<div id="collapse-attach" class="collapse mt-4">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->editpost['attachments'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->editpost['attachments'].'
</div>
<div class="col">
'.$attachbox.'
</div>
</div>
</div>
<!-- attach -->
 <!-- modop -->
<div id="collapse-modop" class="collapse mt-4">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->editpost['mod_options'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->editpost['mod_options'].'
</div>
<div class="col">
'.$closeoption.'
'.$stickoption.'
</div>
</div>
</div>
<!-- modop -->	
<!-- postop -->
<div id="collapse-postop" class="collapse mt-4">
<div class="bg-nav p-2 rounded text-16 d-block d-sm-block d-md-block d-lg-none mb-3">'.$lang->editpost['post_options'].'</div>
<div class="row g-3 border-bottom m-auto pb-4 pt-0 mb-2">
<div class="col-lg-3 d-none d-sm-none d-md-none d-lg-block text-center text-sm-center text-md-center text-lg-end border-end text-16 fw-bold pe-3 me-3">
'.$lang->editpost['post_options'].'
</div>
<div class="col">
'.$signature.'

</div>
</div>
'.$subscriptionmethod.'		
</div>
<!-- postop -->		
			
        </div>
	</div>

	<div class="card-footer text-center">
		<button type="submit" class="btn btn-primary lift" name="submitbutton" value="'.$lang->editpost['update_post'].'" tabindex="3" accesskey="s"><i class="fa-solid fa-pen"></i> &nbsp;'.$lang->editpost['update_post'].'</button>
	</div>
		
<input type="hidden" name="action" value="do_editpost" />
<input type="hidden" name="attachmentaid" value="" />
<input type="hidden" name="attachmentact" value="" />
</form>
	</div></div>

<script>
document.addEventListener(\'DOMContentLoaded\', function() {
    // Показываем родительский элемент fileInput
    if (Post.fileInput && Post.fileInput.parentElement && Post.fileInput.parentElement.parentElement) {
        Post.fileInput.parentElement.parentElement.style.display = \'\';
    }
    
    // Скрываем родительский элемент dropZone
    if (Post.dropZone && Post.dropZone.parentElement && Post.dropZone.parentElement.parentElement) {
        Post.dropZone.parentElement.parentElement.style.display = \'none\';
    }
});
</script>	
</body>
</html>';
	
	
	
	
    stdhead();
    build_breadcrumb();
	
	
	echo '<script type="text/javascript" src="'.$BASEURL.'/scripts/delete_post_editpost.js"></script>';
	
	
	
	
    echo $editpost;
    stdfoot();
}