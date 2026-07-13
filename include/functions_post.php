<?php

function build_ignore_bit(array $post, string $ignored_message, string &$post_visibility, array $lang): string
{
    $post_visibility = "display: none;";
    $pid             = (int)$post['pid'];
    $profilelink     = $post['profilelink'];
    $show_label      = htmlspecialchars($lang['postbit_show_ignored_post'] ?? 'Show');
    $hide_label      = htmlspecialchars($lang['postbit_hide_ignored_post'] ?? 'Hide');

    return '<div class="ignored_post collapse show" id="ignored_post_' . $pid . '">'
         . '<div class="ignored_post_author"><strong><span class="largetext">' . $profilelink . '</span></strong></div>'
         . '<div class="ignored_post_message">'
         . '<div class="show_ignored_post float_right" id="show_ignored_link_' . $pid . '">'
         . '<button type="button" class="btn btn-outline-primary btn-sm rounded-2 d-flex align-items-center gap-2"'
         . ' onclick="showIgnoredPost(' . $pid . '); return false;"'
         . ' data-show-label="' . $show_label . '"'
         . ' data-hide-label="' . $hide_label . '">'
         . '<i class="bi bi-eye"></i>'
         . '<span>' . $show_label . '</span>'
         . '</button>'
         . '</div>'
         . $ignored_message
         . '</div>'
         . '</div>';
}

function build_postbit_footer(array $post): string
{
    $pid = (int)$post['pid'];

    return '<div class="card-footer border-top-0 py-2 my-0">'
         . '<div class="row mt-0 pt-0 mb-0 pb-0">'

         . '<div class="col-auto align-self-center small text-start pe-0 me-0">'
         . $post['button_multiquote']
         . '</div>'

         . '<div class="col-auto align-self-center small text-start pe-0 me-0">'
         . $post['button_rep']
         . '</div>'

         . '<div class="col align-self-center small text-end">'
         . $post['button_edit']
         . '</div>'

         . '<div class="col-auto text-end align-self-center">'
         . '<div class="hidden-text">'
         . '<div class="dropdown d-flex justify-content-end align-self-center">'
         . '<a class="bg-transparent border-0 text-muted" aria-expanded="true" data-bs-toggle="dropdown" role="button">'
         . '&nbsp;<i class="fa-solid fa-ellipsis-vertical"></i>&nbsp;'
         . '</a>'
         . '<div class="dropdown-menu border">'
         . $post['button_quote']
         . $post['button_quickdelete']
         . $post['button_quickrestore']
         . $post['button_report']
         . $post['button_warn']
         . $post['button_purgespammer']
         . $post['button_reply_pm']
         . $post['button_replyall_pm']
         . $post['button_forward_pm']
         . $post['button_delete_pm']
         . '</div>'
         . '</div>'
         . '</div>'
         . '</div>'

         . '</div>'
         . '</div>';
}

function build_postbit_header(array $post): string
{
    return '<div class="row m-0 p-0 mb-3 mb-lg-0">'

         . '<div class="col-auto d-block d-lg-none m-0 p-0 me-2 align-self-center">'
         . $post['useravatar']
         . '</div>'

         . '<div class="col m-0 p-0 align-self-center">'
         . '<h6 class="card-title mb-0 mb-lg-3">'
         . '<span class="fw-bold">' . $post['profilelink'] . '</span>'
         . ' ' . $post['onlinestatus']
         . ' &nbsp;&nbsp;<span class="text-uppercase text-desc small fw-normal">' . $post['postdate'] . '</span>'
         . '</h6>'
         . '</div>'

         . '<div class="col-auto m-0 p-0 align-self-center text-end text-14">'
         . $post['posturl']
         . '</div>'

         . '</div>';
}

function build_postbit_body(array $post, string $unapproved_shade): string
{
    $pid = (int)$post['pid'];

    return '<div class="card-body inline_row ' . $unapproved_shade . '">'
         . build_postbit_header($post)
         . '<div class="post_body scaleimages" id="pid_' . $pid . '">'
         . $post['message']
         . $post['attachments']
         . '</div>'
         . '<span class="post_edit" id="edited_by_' . $pid . '">' . $post['editedmsg'] . '</span>'
         . $post['signature']
         . $post['poststatus']
         . $post['input_editreason']
         . $post['iplogged']
         . '</div>';
}

function build_postbit_modals(array $post, object $parser, array $parser_options, array $lang): array
{
    $pid        = (int)$post['pid'];
    $message    = htmlspecialchars_uni($post['message']);
    $editreason = htmlspecialchars_uni($post['editreason']);

    $bbcode_buttons = [
        ['[b]',        '[/b]',        '<b>B</b>'],
        ['[i]',        '[/i]',        '<i>I</i>'],
        ['[u]',        '[/u]',        '<u>U</u>'],
        ['[s]',        '[/s]',        '<s>S</s>'],
        ['[left]',     '[/left]',     'Left'],
        ['[center]',   '[/center]',   'Center'],
        ['[right]',    '[/right]',    'Right'],
        ['[color=red]','[/color]',    'Red'],
        ['[size=18]',  '[/size]',     'Size'],
        ['[url]',      '[/url]',      'URL'],
        ['[img]',      '[/img]',      'IMG'],
        ['[video]',    '[/video]',    'Video'],
        ['[youtube]',  '[/youtube]',  'YouTube'],
        ['[quote]',    '[/quote]',    'Quote'],
        ['[code]',     '[/code]',     'Code'],
        ['[list]\n[*]','\n[/list]',   'List'],
        ['[list=1]\n[*]','\n[/list]', '#List'],
    ];

    $toolbar = '';
    foreach ($bbcode_buttons as [$open, $close, $label]) {
        $o = addslashes($open);
        $c = addslashes($close);
        $toolbar .= '<button class="btn btn-sm btn-light" onclick="wrapBBCode(\'' . $o . '\',\'' . $c . '\',' . $pid . ')">' . $label . '</button>';
    }

    $modal_edit = '<div class="modal fade" id="editPostModal' . $pid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-lg"><div class="modal-content">'
        . '<div class="modal-header bg-primary text-white">'
        . '<h5 class="modal-title">Edit Post</h5>'
        . '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
        . '</div>'
        . '<div class="modal-body">'
        . '<div class="mb-2">' . $toolbar . '</div>'
        . '<textarea id="editPostTextarea' . $pid . '" class="form-control mb-3" rows="6">' . $message . '</textarea>'
        . '<div class="mb-3">'
        . '<label for="editReasonInput' . $pid . '" class="form-label">Edit Reason (optional)</label>'
        . '<input type="text" class="form-control" id="editReasonInput' . $pid . '" value="' . $editreason . '">'
        . '</div>'
        . '<h6>Live Preview</h6>'
        . '<div id="editPostPreview' . $pid . '" class="border p-2 bg-light rounded" style="min-height:100px;"></div>'
        . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>'
        . '<button type="button" class="btn btn-primary" id="savePostBtn' . $pid . '">Save Changes</button>'
        . '</div>'
        . '</div></div></div>';

    
	
	$parser_options = array(
	    "allow_html" => 0,
	    "allow_mycode" => 1,
	    "allow_smilies" => 1,
	    "allow_imgcode" => 1,
	    "allow_videocode" => 1,
	    "filter_badwords" => 1
    );
	
	
	
	
	$message2    = $parser->parse_message($post['message'], $parser_options);
    $username    = htmlspecialchars_uni($post['username']);
    $postdate    = $post['postdate'];

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
        . '<span class="small fw-bold text-danger"><i class="fas fa-user me-1"></i>' . $username . '</span>'
        . '<div class="d-flex gap-2 align-items-center">'
        . '<span class="text-muted small">' . $postdate . '</span>'
        . '<span class="badge bg-secondary">PID: ' . $pid . '</span>'
        . '</div>'
        . '</div>'
        . '<div class="card-body py-2 px-3">'
        . '<div class="small" style="max-height:350px;overflow-y:auto;">'
        . '<style>#deletePostModal' . $pid . ' .post_body img{max-width:100%;height:auto;}'
        . '#deletePostModal' . $pid . ' .post_body blockquote{font-size:0.8rem;padding:0.5rem;}</style>'
        . '<div>' . $message2 . '</div>'
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

    return [$modal_edit, $modal_delete];
}

function build_postbit($post, $post_type = 0)
{
    global $db, $altbg, $theme, $mybb, $postcounter, $profile_fields, $regdateformat;
    global $wolcutoffmins, $usergroups, $enableattachments, $CURUSER, $f_postsperpage;
    global $moderator, $forummoderator, $thread, $permissions, $forum_threads;
    global $titlescache, $page, $forumpermissions, $attachcache;
    global $lang, $ismod, $inlinecookie, $inlinecount, $groupscache, $fid;
    global $plugins, $parser, $cache, $ignored_users, $hascustomtitle;

    $hascustomtitle = 0;

    foreach (['aid','pmid','posturl','button_multiquote','subject_extra','attachments',
          'button_rep','button_warn','button_purgespammer','button_pm','button_reply_pm',
          'button_replyall_pm','button_forward_pm','button_delete_pm','replink','warninglevel'] as $f) {
    if (empty($post[$f])) $post[$f] = '';
}
// pid отдельно — должен быть int, не строка
if (empty($post['pid'])) $post['pid'] = 0;

    /* ── Unapproved shade ─────────────────────────────────────────── */
    $unapproved_shade = '';
    if (isset($post['visible']) && $post['visible'] == 0 && $post_type == 0) {
        $altbg = $unapproved_shade = 'unapproved_post';
    } elseif (isset($post['visible']) && $post['visible'] == -1 && $post_type == 0) {
        $altbg = $unapproved_shade = 'unapproved_post deleted_post';
    } elseif ($altbg == 'trow1') {
        $altbg = 'trow2';
    } else {
        $altbg = 'trow1';
    }

    $post['fid'] = $fid;

    /* ── Parser options by post type ─────────────────────────────── */
    switch ($post_type) {
        case 1:
            global $forum;
            $parser_options = ['allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
                               'allow_imgcode'=>1,'allow_videocode'=>1,
                               'me_username'=>$post['username'],'filter_badwords'=>1];
            $id = 0;
            break;
        case 2:
            global $message, $pmid;
            $idtype = 'pmid';
            $parser_options = ['allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
                               'allow_imgcode'=>1,'allow_videocode'=>1,
                               'me_username'=>$post['username'],'filter_badwords'=>1];
            $id = $pmid;
            break;
        case 3:
            global $announcementarray, $message;
            $id = $announcementarray['aid'];
            $parser_options = [];
            break;
        default:
            global $forum, $thread, $tid;
            $oldforum = $forum;
            $id       = (int)$post['pid'];
            $idtype   = 'pid';
            $parser_options = ['allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
                               'allow_imgcode'=>1,'allow_videocode'=>1,'filter_badwords'=>1];
            break;
    }

    if (!$post['username']) $post['username'] = 'guest';

    $parser_options['me_username'] = $post['userusername'] ?: $post['username'];

    $post['username']    = htmlspecialchars_uni($post['username']);
    $post['userusername'] = htmlspecialchars_uni($post['userusername']);

    /* ── Post counter ─────────────────────────────────────────────── */
    if (!$postcounter) {
        $postcounter      = ($page > 1) ? (int)($f_postsperpage ?: 20) * ($page - 1) : 0;
        $post_extra_style = "border-top-width: 0;";
    } elseif ($mybb->get_input('mode') == "threaded") {
        $post_extra_style = "border-top-width: 0;";
    } else {
        $post_extra_style = "margin-top: 5px;";
    }

    if (!$altbg) $altbg = "trow1";
    $postcounter++;

    $post['postdate']      = my_datee('relative', $post['dateline']);
    $post['subject']       = $parser->parse_badwords($post['subject']);
    if ($post_type != 2) $post['subject'] = htmlspecialchars_uni($post['subject']);
    if (empty($post['subject'])) $post['subject'] = '&nbsp;';

    $post['author']        = $post['uid'];
    $post['subject_title'] = $post['subject'];

    /* ── Usergroup ────────────────────────────────────────────────── */
    $usergroup = usergroup_permissions($post['usergroup'] ?: 1);

    if (empty($post['displaygroup'])) $post['displaygroup'] = $post['usergroup'] ?: 1;
    if (empty($post['usergroup']))    $post['usergroup']    = 1;

    $displaygroup = usergroup_displaygroup($post['displaygroup']);
    if (is_array($displaygroup)) $usergroup = array_merge($usergroup, $displaygroup);



    /* ── Registered user vs guest ────────────────────────────────── */
    if ($post['userusername']) {
        $post['username']          = $post['userusername'];
        $post['profilelink_plain'] = get_profile_link((int)$post['id']);
        $post['username_formatted'] = format_name($post['username'], $post['usergroup'], $post['displaygroup']);
        $post['profilelink']       = build_profile_link($post['username_formatted'], (int)$post['id']);

        if (trim($post['usertitle']) != "") $hascustomtitle = 1;
        if ($usergroups['usertitle'] != "" && !$hascustomtitle) $post['usertitle'] = $usergroup['usertitle'];
        $post['usertitle'] = htmlspecialchars_uni($post['usertitle']);

        $postnum           = $post['postnum'];
        $post['postnum']   = ts_nf($post['postnum']);
        $post['threadnum'] = ts_nf($post['threadnum']);

        $moderator = is_mod($usergroups);
        $timecut   = TIMENOW - $wolcutoffmins;

        if ($post['lastactive'] > $timecut && ($post['invisible'] != 1 || $usergroups['canviewwolinvis'] == 1) && $post['lastvisit'] != $post['lastactive']) {
            $post['onlinestatus'] = '<a href="online.php" title="' . $lang->global['postbit_status_online'] . '">'
                . '<i class="fa-solid fa-circle-dot smaller" style="vertical-align:0.115em;padding-left:4px;color:#68c000"></i></a>';
        } else {
            $post['onlinestatus'] = '<i class="fa-solid fa-circle-dot smaller" title="' . $lang->global['postbit_status_offline'] . '" style="vertical-align:0.115em;padding-left:4px;color:#ccc"></i>';
        }

        $post['useravatar'] = '';
        if (true) {
            $useravatar = format_avatar($post['avatar'], $post['avatardimensions']);
            $post['useravatar'] =
                '<div class="d-none d-lg-block">'
                . '<div class="author_avatar"><a href="' . $post['profilelink_plain'] . '">'
                . '<img class="rounded img-fluid" style="width:100px;padding:0" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . '>'
                . '</a></div></div>'
                . '<div class="d-block d-lg-none">'
                . '<div class="author_avatar"><a href="' . $post['profilelink_plain'] . '">'
                . '<img class="rounded img-fluid" style="width:30px;height:30px;padding:0" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . '>'
                . '</a></div></div>';
        }

        $post['userregdate']  = my_datee($regdateformat, $post['added']);
        $post['user_details'] = '<i class="bi bi-chat-fill"></i> ' . $post['postnum'];

		
    } else {
        $post['profilelink']  = format_name($post['username'], 1);
        $post['title']        = htmlspecialchars_uni($usergroup['title'] ?: 'guest');
        $post['userstars']    = $post['useravatar'] = '';
        $post['userregdate']  = $post['postnum'] = 'na';
        $post['button_profile'] = $post['button_email'] = $post['button_www'] = '';
        $post['signature']    = $post['button_pm'] = $post['button_find'] = '';
        $post['onlinestatus'] = $post['replink'] = '';
        $post['user_details'] = '&nbsp;';
        $usergroup['title']   = 'na';
    }

    /* ── Button defaults ─────────────────────────────────────────── */
    foreach (['button_edit','button_quickdelete','button_quickrestore','button_quote',
              'button_quickquote','button_report','button_reply_pm','button_replyall_pm',
              'button_forward_pm','button_delete_pm','input_editreason'] as $f) {
        $post[$f] = '';
    }

    /* ── PM buttons ──────────────────────────────────────────────── */
    if ($post_type == 2 && $post['pmid']) {
        global $replyall;
        $post['button_reply_pm']   = '<a href="private.php?action=send&amp;pmid=' . $id . '&amp;do=reply" class="dropdown-item"><i class="fa-solid fa-reply"></i> &nbsp;' . $lang->private['postbit_button_reply_pm'] . '</a>';
        $post['button_forward_pm'] = '<a href="private.php?action=send&amp;pmid=' . $id . '&amp;do=forward" class="dropdown-item"><i class="fa-solid fa-share"></i> &nbsp;' . $lang->global['postbit_button_forward'] . '</a>';
        $post['button_delete_pm']  = '<a href="private.php?action=delete&amp;pmid=' . $id . '&amp;my_post_key=' . $mybb->post_code . '" class="dropdown-item"><i class="fa-solid fa-trash"></i> &nbsp;' . $lang->global['postbit_button_delete_pm'] . '</a>';
        if ($replyall) {
            $post['button_replyall_pm'] = '<a href="private.php?action=send&amp;pmid=' . $id . '&amp;do=replyall" class="dropdown-item"><i class="fa-solid fa-reply-all"></i> &nbsp;' . $lang->global['postbit_button_reply_all'] . '</a>';
        }
    }

    /* ── Edit/delete buttons + modals ────────────────────────────── */
    $modals = $modaldelete = '';
    $post['editedmsg'] = '';

    if (!$post_type) {
        if (!isset($forumpermissions)) $forumpermissions = forum_permissions($fid);

        if ($post['edituid'] != 0 && $post['edittime'] != 0 && $post['editusername'] != "") {
            $post['editdate']         = my_datee('relative', $post['edittime']);
            $post['editnote']         = 'This post was last modified: ' . $post['editdate'] . ' by';
            $post['editusername']     = htmlspecialchars_uni($post['editusername']);
            $post['editedprofilelink'] = build_profile_link($post['editusername'], $post['edituid']);
            $editreason = '';
            if ($post['editreason'] != '') {
                $post['editreason'] = htmlspecialchars_uni($parser->parse_badwords($post['editreason']));
                $editreason = ' ' . $lang->global['postbit_editreason'] . ': ' . $post['editreason'];
            }
            $post['editedmsg'] = '<div class="mt-3"><span class="small">'
                . $post['editnote'] . ' ' . $post['editedprofilelink'] . '.' . $editreason
                . '</span></div>';
        }

        $is_mod = is_mod($usergroups);

        if ($is_mod || ($forumpermissions['caneditposts'] == 1 && $CURUSER['id'] == $post['uid'] && $thread['closed'] != 1 && $CURUSER['id'] != 0)) {
            $pid = (int)$post['pid'];

            $post['button_edit'] =
                '<div class="d-none d-lg-block"><div class="dropdown">'
                . '<a class="postlinks" aria-expanded="false" data-bs-toggle="dropdown" type="button">'
                . '<i class="fa-solid fa-pencil"></i> &nbsp;' . $lang->global['postbit_button_edit']
                . '</a>'
                . '<div class="dropdown-menu border">'
                . '<a href="javascript:void(0)" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editPostModal' . $pid . '">'
                . '<i class="fa-solid fa-clock"></i> &nbsp;' . $lang->global['postbit_quick_edit'] . '</a>'
                . '<a href="editpost.php?pid=' . $pid . '" class="dropdown-item">'
                . '<i class="fa-solid fa-pen-to-square"></i> &nbsp;' . $lang->global['postbit_full_edit'] . '</a>'
                . '<div class="dropdown-divider"></div>'
                . '<a href="javascript:void(0)" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deletePostModal' . $pid . '">'
                . '<i class="fa-solid fa-trash"></i> &nbsp;Delete</a>'
                . '</div></div></div>'
                . '<div class="d-block d-lg-none"><div class="dropdown">'
                . '<a href="editpost.php?pid=' . $pid . '" class="links me-2">'
                . '<i class="fa-solid fa-pencil"></i> &nbsp;' . $lang->global['postbit_button_edit'] . '</a>'
                . '<a href="javascript:void(0)" class="links text-danger" data-bs-toggle="modal" data-bs-target="#deletePostModal' . $pid . '">'
                . '<i class="fa-solid fa-trash"></i> &nbsp;Delete</a>'
                . '</div></div>';

            [$modals, $modaldelete] = build_postbit_modals($post, $parser, $parser_options, $lang->global);
        }

        /* ── Inline mod checkbox ──────────────────────────────────── */
       if ($is_mod) {
    $inlinecheck = (!empty($inlinecookie) && isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], '|' . $post['pid'] . '|') !== false)
        ? 'checked="checked"' : '';
                

            $post['inlinecheck'] = '<div class="form-check form-switch d-inline-block">'
                . '<input type="checkbox" class="form-check-input" name="inlinemod_' . (int)$post['pid'] . '" id="inlinemod_' . (int)$post['pid'] . '" value="1" style="vertical-align:middle;margin-bottom:7px;" ' . $inlinecheck . '>'
                . '</div>&nbsp;';

            if ($inlinecheck) $inlinecount++;
            if ($post['visible'] == 0) $invisiblepost = 1;
        } else {
            $post['inlinecheck'] = '';
        }

        /* ── Post URL ─────────────────────────────────────────────── */
        $post['postlink'] = get_post_link($post['pid'], $post['tid']);
        $post_number      = ts_nf($postcounter);
        $post['posturl']  = $post['inlinecheck']
            . ' <a href="' . $post['postlink'] . '#pid' . (int)$post['pid'] . '" title="' . $post['subject_title'] . '">#' . $post_number . '</a>';

        /* ── Quote / report buttons ───────────────────────────────── */
        global $forum, $thread;
        $is_mod = is_mod($usergroups);

        if ($forum['open'] != 0 && ($thread['closed'] != 1 || $is_mod) && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']))) {
            $post['button_quote'] =
                '<a href="newreply.php?tid=' . $tid . '&amp;replyto=' . (int)$post['pid'] . '" class="dropdown-item">'
                . '<i class="fa-solid fa-reply"></i> &nbsp;' . $lang->global['postbit_button_quote'] . '</a>'
                . '<a href="#" class="dropdown-item report-post-btn"'
                . ' data-bs-toggle="modal" data-bs-target="#reportForumPostModal"'
                . ' data-post-id="' . (int)$post['pid'] . '"'
                . ' data-user-id="' . (int)$post['uid'] . '"'
                . ' data-post-content="' . htmlspecialchars_uni($post['message']) . '"'
                . ' data-post-author="' . htmlspecialchars_uni($post['username']) . '"'
                . ' data-thread-title="' . htmlspecialchars_uni($thread['subject']) . '"'
                . ' data-thread-id="' . (int)$post['tid'] . '"'
                . ' data-forum-id="' . (int)$forum['fid'] . '"'
                . ' data-forum-name="' . htmlspecialchars_uni($forum['name']) . '"'
                . ' data-post-date="' . (int)$post['dateline'] . '"'
                . ' data-post-subject="' . htmlspecialchars_uni($post['subject']) . '">'
                . '<i class="fa-solid fa-flag"></i> &nbsp;Report Post</a>';
        }

        /* ── Multiquote ───────────────────────────────────────────── */
        if ($forumpermissions['canpostreplys'] != 0
            && ($thread['uid'] == $CURUSER['id'] || empty($forumpermissions['canonlyreplyownthreads']))
            && ($thread['closed'] != 1 || $is_mod)
            && $forum['open'] != 0
            && !$post_type
        ) {
            $post['button_multiquote'] =
                '<a href="javascript:void(0)" onclick="Thread.multiQuote(' . (int)$post['pid'] . '); return false;"'
                . ' style="display:none;" id="multiquote_link_' . (int)$post['pid'] . '"'
                . ' title="' . $lang->global['postbit_multiquote'] . '"'
                . ' class="postbit_multiquote postbit_mirage postlinks">'
                . '<span id="multiquote_' . (int)$post['pid'] . '">'
                . '<i class="fa-solid fa-quote-left"></i> &nbsp;' . $lang->global['postbit_button_multiquote']
                . '</span></a>';
        }

    } elseif ($post_type == 3) {
        $is_mod = is_mod($usergroups);
        if ($is_mod) {
            $post['button_edit'] =
                '<a href="admin/index.php?act=announcements_forum&amp;action=edit&amp;id=' . (int)$post['aid'] . '"'
                . ' title="' . $lang->announcements['announcement_edit'] . '"'
                . ' class="postbit_edit postlinks">'
                . '<i class="fa-solid fa-pencil"></i> &nbsp;' . $lang->global['postbit_button_edit'] . '</a>';

            $post['button_quickdelete'] =
                '<a href="#"'
                . ' onclick="afDelete(' . (int)$post['aid'] . ', \'' . addslashes($post['subject']) . '\'); return false;"'
                . ' class="postbit_qdelete dropdown-item p-2">'
                . '<i class="fa-solid fa-trash"></i> &nbsp;' . $lang->global['postbit_button_qdelete'] . '</a>';
        }
    }

    /* ── Post status ─────────────────────────────────────────────── */
    $post['iplogged']    = '';
    $post['poststatus']  = '';
    if (!$post_type && $post['visible'] != 1) {
        $status_type = '';
        if ($is_mod && $postcounter != 1 && $post['visible'] == 0) {
            $status_type = $lang->global['postbit_post_unapproved'];
        } elseif ($is_mod && $postcounter == 1 && $post['visible'] == 0) {
            $status_type = $lang->global['postbit_thread_unapproved'];
        }
        $post['poststatus'] = '<div class="mt-3"><span class="status_type small">' . $status_type . '</span></div>';
    }

    /* ── Highlight search terms ──────────────────────────────────── */
    if (!empty($mybb->input['highlight'])) {
        $parser_options['highlight'] = $mybb->input['highlight'];
        $post['subject'] = $parser->highlight_message($post['subject'], $parser_options['highlight']);
    }

    /* ── Parse message ───────────────────────────────────────────── */
    $parser_options = ['allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
                       'allow_imgcode'=>1,'allow_videocode'=>1,'filter_badwords'=>1];
    $post['message'] = $parser->parse_message($post['message'], $parser_options);

    $post['attachments'] = '';
    if ($enableattachments != 0) get_post_attachments($id, $post);

    /* ── Signature ───────────────────────────────────────────────── */
    //if ($post['username'] && $post['signature'] != "" && ($CURUSER['id'] == 0 || $CURUSER['showsigs'] != 0)) {
	if ($post['username'] && $post['signature'] != "") {
        $post['signature'] = $parser->parse_message($post['signature'], [
            'allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
            'allow_imgcode'=>0,'me_username'=>1,'filter_badwords'=>1,
        ]);
        $post['signature'] = '<div class="signature scaleimages mt-4"><hr />' . $post['signature'] . '</div>';
    } else {
        $post['signature'] = '';
    }

    /* ── Plugin hooks ────────────────────────────────────────────── */
    $post_visibility = $ignore_bit = $deleted_bit = '';

    switch ($post_type) {
        case 1: $post = $plugins->run_hooks("postbit_prev",         $post); break;
        case 2: $post = $plugins->run_hooks("postbit_pm",           $post); break;
        case 3: $post = $plugins->run_hooks("postbit_announcement",  $post); break;
        default:
            $post = $plugins->run_hooks("postbit", $post);

            if (!isset($ignored_users)) {
                $ignored_users = [];
                if ($CURUSER['id'] > 0 && $CURUSER['ignorelist'] != "") {
                    foreach (explode(',', $CURUSER['ignorelist']) as $uid) {
                        $ignored_users[$uid] = 1;
                    }
                }
            }

            // Unapproved post owned by current user
            if ($CURUSER['id'] && $post['visible'] == 0 && $post['uid'] == $CURUSER['id']) {
                $ignored_message = 'The post made by you is under moderation and currently not visible publicly. It will be visible once a moderator approves it.';
                $ignore_bit      = build_ignore_bit($post, $ignored_message, $post_visibility, $lang->global);
            }

            // Ignored user
            if (is_array($ignored_users) && $post['uid'] != 0 && !empty($ignored_users[$post['uid']]) && empty($deleted_bit)) {
                $ignored_message = 'The contents of this message are hidden because ' . htmlspecialchars_uni($post['username']) . ' is on your <a href="usercp.php?action=editlists">ignore list</a>.';
                $ignore_bit      = build_ignore_bit($post, $ignored_message, $post_visibility, $lang->global);
            }
            break;
    }

    /* ── Deleted post ────────────────────────────────────────────── */
    if ($post_type == 0 && $post['visible'] == -1) {
        return '<div class="row mb-4 ps-5 pe-5">'
             . '<div class="col align-self-center">'
             . '<a id="pid' . (int)$post['pid'] . '" name="pid' . (int)$post['pid'] . '"></a>'
             . '<div id="post_' . (int)$post['pid'] . '" class="post deleted_post_hidden"></div>'
             . '<i class="bi bi-info-circle"></i> This post has been deleted'
             . '</div></div>';
    }

    /* ── Render postbit ──────────────────────────────────────────── */
    $pid     = (int)$post['pid'];
    $footer  = build_postbit_footer($post);
    $body    = build_postbit_body($post, $unapproved_shade);

    $postbit = $ignore_bit . $deleted_bit
             . '<a name="pid' . $pid . '" id="pid' . $pid . '"></a>'
             . '<div class="row g-2 mb-4" style="' . $post_visibility . '" id="post_' . $pid . '">'
             . '<div class="col-auto d-none d-lg-block">' . $post['useravatar'] . '</div>'
             . '<div class="col"><div class="card">'
             . $body
             . '<div class="card-footer border-top-0 py-2 my-0">'
             . '<div class="row mt-0 pt-0 mb-0 pb-0">'
             . '<div class="col-auto align-self-center small text-start pe-0 me-0">' . $post['button_multiquote'] . '</div>'
             . '<div class="col-auto align-self-center small text-start pe-0 me-0">' . $post['button_rep'] . '</div>'
             . '<div class="col align-self-center small text-end">' . $post['button_edit'] . '</div>'
             . $modals
             . $modaldelete
             . '<div class="col-auto text-end align-self-center">'
             . '<div class="hidden-text"><div class="dropdown d-flex justify-content-end align-self-center">'
             . '<a class="bg-transparent border-0 text-muted" aria-expanded="true" data-bs-toggle="dropdown" role="button">&nbsp;<i class="fa-solid fa-ellipsis-vertical"></i>&nbsp;</a>'
             . '<div class="dropdown-menu border">'
             . $post['button_quote'] . $post['button_quickdelete'] . $post['button_quickrestore']
             . $post['button_report'] . $post['button_warn'] . $post['button_purgespammer']
             . $post['button_reply_pm'] . $post['button_replyall_pm'] . $post['button_forward_pm'] . $post['button_delete_pm']
             . '</div></div></div></div>'
             . '</div></div>'
             . '</div></div></div>';

    $GLOBALS['post'] = '';
    return $postbit;
}

function get_post_attachments($id, &$post)
{
    global $attachcache, $mybb, $theme, $forumpermissions, $attachthumbnails, $lang;

    $validationcount = 0;
    $tcount = 0;
    $post['attachmentlist'] = $post['thumblist'] = $post['imagelist'] = '';

    if (!isset($forumpermissions)) {
        $forumpermissions = forum_permissions($post['fid']);
    }

    if (!isset($attachcache[$id]) || !is_array($attachcache[$id])) {
        return;
    }

    foreach ($attachcache[$id] as $aid => $attachment) {
        if (!$attachment['visible']) {
            $validationcount++;
            continue;
        }

        $attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
        $attachment['filesize'] = mksize($attachment['filesize']);
        $ext      = get_extension($attachment['filename']);
        $isimage  = in_array($ext, ['jpeg','jpg','gif','bmp','png']);
        $attachment['icon']      = get_attachment_icon($ext);
        $attachment['downloads'] = ts_nf($attachment['downloads']);

        if (!$attachment['dateuploaded']) {
            $attachment['dateuploaded'] = $post['dateline'];
        }
        $attachdate = my_datee('normal', $attachment['dateuploaded']);

        $title_attr = $lang->global['postbit_attachment_filename'] . ' ' . $attachment['filename'] . "\n"
                    . $lang->global['postbit_attachment_size'] . ' ' . $attachment['filesize'] . "\n"
                    . $attachdate;

        $inline = stripos($post['message'], '[attachment=' . $attachment['aid'] . ']') !== false;

        if ($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $attachthumbnails == "yes") {
            $attbit = '<div class="col-auto">'
                    . '<a href="attachment.php?aid=' . (int)$attachment['aid'] . '" target="_blank">'
                    . '<img src="attachment.php?thumbnail=' . (int)$attachment['aid'] . '" style="width:80px;height:80px" class="img-thumbnail display-inline" alt="" title="' . htmlspecialchars($title_attr) . '">'
                    . '</a></div>';
        } elseif (($attachment['thumbnail'] == "SMALL" || $attachthumbnails == "no") && $isimage) {
            $attbit = '<img src="attachment.php?aid=' . (int)$attachment['aid'] . '" class="attachment" style="width:16px;height:16px;" alt="" title="' . htmlspecialchars($title_attr) . '">&nbsp;&nbsp;&nbsp;';
        } else {
            $attbit = '<div class="row mt-2 g-1 text-muted">'
                    . '<div class="col-auto align-self-center">' . $attachment['icon'] . '</div>'
                    . '<div class="col align-self-center">'
                    . '<a href="attachment.php?aid=' . (int)$attachment['aid'] . '" target="_blank" title="' . $attachdate . '">' . $attachment['filename'] . '</a>'
                    . ' (' . $lang->global['postbit_attachment_size'] . ' <span class="text-dark">' . $attachment['filesize'] . '</span>'
                    . ' ' . $lang->global['postbit_attachment_downloads'] . ' <span class="text-dark">' . $attachment['downloads'] . '</span>)'
                    . '</div></div>';
        }

        if ($inline) {
            $post['message'] = preg_replace('#\[attachment=' . $attachment['aid'] . ']#si', $attbit, $post['message']);
        } else {
            if ($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $attachthumbnails == "yes") {
                $post['thumblist'] .= $attbit;
                if ($tcount == 5) { $post['thumblist'] .= "<br />"; $tcount = 0; }
                ++$tcount;
            } elseif (($attachment['thumbnail'] == "SMALL" || $attachthumbnails == "no") && $isimage) {
                if ($forumpermissions['candlattachments']) {
                    $post['imagelist'] .= $attbit;
                } else {
                    $post['thumblist'] .= $attbit;
                    if ($tcount == 5) { $post['thumblist'] .= "<br />"; $tcount = 0; }
                    ++$tcount;
                }
            } else {
                $post['attachmentlist'] .= $attbit;
            }
        }
    }

    if ($validationcount > 0) {
        $msg = ($validationcount == 1)
            ? $lang->global['postbit_unapproved_attachment']
            : sprintf($lang->global['postbit_unapproved_attachments'], $validationcount);

        $post['attachmentlist'] .= '<div class="row ms-0 me-0 mt-0 border-top p-3 mb-0">'
            . '<div class="col-auto align-self-center small">'
            . '<i class="fa-solid fa-xmark"></i> &nbsp;' . $msg
            . '</div></div>';
    }

    $post['attachedthumbs'] = $post['thumblist']
        ? '<div class="row mt-2">' . $post['thumblist'] . '</div>'
        : '';

    $post['attachedimages'] = $post['imagelist']
        ? $lang->global['postbit_attachments_images'] . '<br />' . $post['imagelist'] . '<br />'
        : '';

    if ($post['attachmentlist'] || $post['thumblist'] || $post['imagelist']) {
        $post['attachments'] = '<div class="mb-0 mt-4">'
            . '<i class="fa-solid fa-paperclip"></i> <strong>' . $lang->global['postbit_attachments'] . '</strong>'
            . '<hr />'
            . $post['attachmentlist']
            . $post['attachedthumbs']
            . $post['attachedimages']
            . '</div>';
    }
}

function return_bytes($val)
{
    $val = trim($val);
    if ($val == "") return 0;
    $last = strtolower($val[strlen($val) - 1]);
    $val  = intval($val);
    switch ($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

function detect_attachmentact()
{
    global $mybb;
    foreach ($mybb->input as $key => $val) {
        if (strpos($key, 'rem_') === 0) {
            $mybb->input['attachmentaid'] = (int)substr($key, 4);
            $mybb->input['attachmentact'] = 'remove';
            break;
        } elseif (strpos($key, 'approveattach_') === 0) {
            $mybb->input['attachmentaid'] = (int)substr($key, 14);
            $mybb->input['attachmentact'] = 'approve';
            break;
        } elseif (strpos($key, 'unapproveattach_') === 0) {
            $mybb->input['attachmentaid'] = (int)substr($key, 16);
            $mybb->input['attachmentact'] = 'unapprove';
            break;
        }
    }
}