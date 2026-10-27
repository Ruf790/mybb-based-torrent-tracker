<?php

/* =============================================================================
   Постбит форума в «новом стиле».
   Разметка — классы pb-* (include/templates/default/style/postbit.css).
   CSS подключается самим build_postbit() один раз за запрос, поэтому пост
   выглядит одинаково на showthread, private, announcements и в предпросмотрах.
   Id/классы, на которые опираются скрипты, сохранены:
     post_{pid}, pid{pid}, pid_{pid}, edited_by_{pid}, inline_row, .card-title,
     multiquote_link_{pid}, inlinemod_{pid}, editPostModal{pid}, deletePostModal{pid},
     ignored_post_{pid}, show_ignored_link_{pid}, .report-post-btn + data-*
   ============================================================================= */

/** <link> на postbit.css — один раз за запрос, не в AJAX-ответах */
function postbit_css_link(): string
{
    static $done = false;
    global $BASEURL;

    if ($done || (defined('SCRIPTNAME') && SCRIPTNAME === 'xmlhttp.php')) {
        return '';
    }
    $done = true;

    return '<link rel="stylesheet" href="' . htmlspecialchars((string)$BASEURL) . '/include/templates/default/style/postbit.css?ver=241">';
}

/** Аватар автора: ссылка + картинка (заглушка-иконка для гостей и запрещённых удалённых аватаров) */
function postbit_avatar(array $post, string $mod = ''): string
{
    $cls = 'pb-avatar' . ($mod !== '' ? ' pb-avatar--' . $mod : '');

    if (empty($post['userusername'])) {
        return '<span class="' . $cls . ' pb-avatar--guest"><i class="fa-solid fa-user"></i></span>';
    }

    $av = format_avatar($post['avatar'] ?? '', $post['avatardimensions'] ?? '');
    $link = $post['profilelink_plain'];

    if (!empty($av['is_html'])) {
        // format_avatar() вернул HTML-заглушку вместо URL — в src его ставить нельзя
        return '<a href="' . $link . '" class="' . $cls . ' pb-avatar--guest"><i class="fa-solid fa-user"></i></a>';
    }

    return '<a href="' . $link . '" class="' . $cls . (!empty($av['is_placeholder']) ? ' pb-avatar--ph' : '') . '">'
         . '<img src="' . $av['image'] . '" alt="" loading="lazy"></a>';
}

function build_ignore_bit(array $post, string $ignored_message, string &$post_visibility, array $lang): string
{
    $post_visibility = "display: none;";
    $pid             = (int)$post['pid'];
    $profilelink     = $post['profilelink'];
    $show_label      = htmlspecialchars($lang['postbit_show_ignored_post'] ?? 'Show');
    $hide_label      = htmlspecialchars($lang['postbit_hide_ignored_post'] ?? 'Hide');

    // ignor.js: #ignored_post_{pid}, #show_ignored_link_{pid} button > i.bi-eye + span, data-*-label, btn-outline-primary/secondary
    return '<div class="ignored_post pb-ignored" id="ignored_post_' . $pid . '">'
         . '<span class="pb-ignored__ico"><i class="fa-solid fa-eye-slash"></i></span>'
         . '<div class="ignored_post_message pb-ignored__text">'
         . '<div class="ignored_post_author pb-ignored__who">' . $profilelink . '</div>'
         . $ignored_message
         . '</div>'
         . '<div class="show_ignored_post" id="show_ignored_link_' . $pid . '">'
         . '<button type="button" class="btn btn-outline-primary btn-sm rounded-pill d-inline-flex align-items-center gap-2"'
         . ' onclick="showIgnoredPost(' . $pid . '); return false;"'
         . ' data-show-label="' . $show_label . '"'
         . ' data-hide-label="' . $hide_label . '">'
         . '<i class="bi bi-eye"></i>'
         . '<span>' . $show_label . '</span>'
         . '</button>'
         . '</div>'
         . '</div>';
}

/** Меню «⋮» в подвале — только если в нём есть пункты */
function build_postbit_menu(array $post): string
{
    $items = $post['button_quote'] . $post['button_quickdelete'] . $post['button_quickrestore']
           . $post['button_report'] . $post['button_warn'] . $post['button_purgespammer']
           . $post['button_reply_pm'] . $post['button_replyall_pm'] . $post['button_forward_pm'] . $post['button_delete_pm'];

    if ($items === '') {
        return '';
    }

    return '<div class="dropdown pb-menu">'
         . '<button type="button" class="pb-more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More">'
         . '<i class="fa-solid fa-ellipsis-vertical"></i></button>'
         . '<div class="dropdown-menu dropdown-menu-end">' . $items . '</div>'
         . '</div>';
}

function build_postbit_footer(array $post): string
{
    return '<div class="pb-foot">'
         . $post['button_multiquote']
         . $post['button_rep']
         . '<span class="pb-foot__sp"></span>'
         . $post['button_edit']
         . build_postbit_menu($post)
         . '</div>';
}

function build_postbit_header(array $post): string
{
    $title = !empty($post['usertitle']) && !empty($post['userusername'])
        ? '<span class="pb-utitle">' . $post['usertitle'] . '</span>'
        : '';

    return '<div class="pb-head">'
         . '<div class="pb-head__av">' . postbit_avatar($post, 'sm') . '</div>'
         . '<div class="pb-who">'
         . '<div class="pb-author card-title">' . $post['profilelink'] . $post['onlinestatus'] . '</div>'
         . '<div class="pb-meta">' . $title
         . '<span class="pb-date"><i class="fa-regular fa-clock"></i>' . $post['postdate'] . '</span>'
         . '</div>'
         . '</div>'
         . '<div class="pb-num">' . $post['posturl'] . '</div>'
         . '</div>';
}

function build_postbit_body(array $post, string $unapproved_shade): string
{
    $pid = (int)$post['pid'];

    return build_postbit_header($post)
         . '<div class="pb-body">'
         . '<div class="post_body scaleimages" id="pid_' . $pid . '">'
         . $post['message']
         . $post['attachments']
         . '</div>'
         . '<span class="post_edit pb-edited-wrap" id="edited_by_' . $pid . '">' . $post['editedmsg'] . '</span>'
         . $post['signature']
         . $post['poststatus']
         . $post['input_editreason']
         . $post['iplogged']
         . '</div>';
}

function build_postbit_modals(array $post, object $parser, array $parser_options, array $lang): array
{
    global $lang;

    $pid        = (int)$post['pid'];
    $message    = htmlspecialchars_uni($post['message']);
    $editreason = htmlspecialchars_uni($post['editreason']);

    $bbcode_buttons = [
        ['[b]',        '[/b]',        '<b>B</b>'],
        ['[i]',        '[/i]',        '<i>I</i>'],
        ['[u]',        '[/u]',        '<u>U</u>'],
        ['[s]',        '[/s]',        '<s>S</s>'],
        ['[left]',     '[/left]',     $lang->showthread['pb_bb_left']],
        ['[center]',   '[/center]',   $lang->showthread['pb_bb_center']],
        ['[right]',    '[/right]',    $lang->showthread['pb_bb_right']],
        ['[color=red]','[/color]',    $lang->showthread['pb_bb_red']],
        ['[size=18]',  '[/size]',     $lang->showthread['pb_bb_size']],
        ['[url]',      '[/url]',      'URL'],
        ['[img]',      '[/img]',      'IMG'],
        ['[video]',    '[/video]',    $lang->showthread['pb_bb_video']],
        ['[youtube]',  '[/youtube]',  'YouTube'],
        ['[quote]',    '[/quote]',    $lang->showthread['pb_bb_quote']],
        ['[code]',     '[/code]',     $lang->showthread['pb_bb_code']],
        ['[list]\n[*]','\n[/list]',   $lang->showthread['pb_bb_list']],
        ['[list=1]\n[*]','\n[/list]', $lang->showthread['pb_bb_list_num']],
        ['[spoiler]',  '[/spoiler]',  $lang->showthread['pb_bb_spoiler']],
    ];

    $toolbar = '';
    foreach ($bbcode_buttons as [$open, $close, $label]) {
        $o = addslashes($open);
        $c = addslashes($close);
        $toolbar .= '<button type="button" class="btn btn-sm btn-light" onclick="wrapBBCode(\'' . $o . '\',\'' . $c . '\',' . $pid . ')">' . $label . '</button>';
    }
    $toolbar .= '<button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#torrentPanel' . $pid . '"><i class="fa-solid fa-magnet"></i> ' . $lang->showthread['pb_torrent'] . '</button>';

    $torrentPanel = '<div class="collapse mb-3" id="torrentPanel' . $pid . '">'
        . '<div class="card card-body">'
        . '<label class="form-label small">' . $lang->showthread['pb_torrent_id_label'] . '</label>'
        . '<div class="input-group">'
        . '<input type="text" inputmode="numeric" class="form-control" id="torrentIdInput' . $pid . '" placeholder="' . $lang->showthread['pb_torrent_placeholder'] . '">'
        . '<button type="button" class="btn btn-primary" id="insertTorrentBtn' . $pid . '">' . $lang->showthread['pb_insert'] . '</button>'
        . '</div>'
        . '<div id="torrentPreview' . $pid . '" class="mt-2"></div>'
        . '</div>'
        . '</div>';

    $modal_edit = '<div class="modal fade" id="editPostModal' . $pid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-lg"><div class="modal-content">'
        . '<div class="modal-header bg-primary text-white">'
        . '<h5 class="modal-title">' . $lang->showthread['pb_edit_title'] . '</h5>'
        . '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
        . '</div>'
        . '<div class="modal-body">'
        . '<div class="mb-2">' . $toolbar . '</div>'
        . $torrentPanel
        . '<textarea id="editPostTextarea' . $pid . '" class="form-control mb-3" rows="6">' . $message . '</textarea>'
        . '<div class="mb-3">'
        . '<label for="editReasonInput' . $pid . '" class="form-label">' . $lang->showthread['pb_edit_reason'] . '</label>'
        . '<input type="text" class="form-control" id="editReasonInput' . $pid . '" value="' . $editreason . '">'
        . '</div>'
        . '<h6>' . $lang->showthread['pb_live_preview'] . '</h6>'
        . '<div id="editPostPreview' . $pid . '" class="border p-2 bg-body-tertiary rounded" style="min-height:100px;"></div>'
        . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $lang->showthread['pb_cancel'] . '</button>'
        . '<button type="button" class="btn btn-primary" id="savePostBtn' . $pid . '">' . $lang->showthread['pb_save_changes'] . '</button>'
        . '</div>'
        . '</div></div></div>';


    $parser_options = [
        "allow_html"      => 0,
        "allow_mycode"    => 1,
        "allow_smilies"   => 1,
        "allow_imgcode"   => 1,
        "allow_videocode" => 1,
        "filter_badwords" => 1,
    ];

    $message2 = $parser->parse_message($post['message'], $parser_options);
    // $post['username'] уже экранирован в build_postbit() — второй htmlspecialchars давал &amp;amp;
    $username = $post['username'];
    $postdate = $post['postdate'];

    $modal_delete = '<div class="modal fade" id="deletePostModal' . $pid . '" tabindex="-1" aria-hidden="true">'
        . '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">'
        . '<div class="modal-header bg-danger text-white">'
        . '<h5 class="modal-title"><i class="fa-solid fa-trash me-2"></i>' . $lang->showthread['pb_delete_title'] . '</h5>'
        . '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>'
        . '</div>'
        . '<div class="modal-body">'
        . '<div class="text-center mb-3">'
        . '<i class="fa-solid fa-triangle-exclamation text-warning fa-3x mb-3"></i>'
        . '<h6 class="fw-bold">' . $lang->showthread['pb_delete_confirm'] . '</h6>'
        . '<p class="text-muted mb-0">' . $lang->showthread['pb_delete_irreversible'] . '</p>'
        . '</div>'
        . '<div class="card border-danger border-opacity-25 mb-3">'
        . '<div class="card-header py-2 px-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">'
        . '<span class="small fw-bold text-danger"><i class="fas fa-user me-1"></i>' . $username . '</span>'
        . '<div class="d-flex gap-2 align-items-center">'
        . '<span class="text-muted small">' . $postdate . '</span>'
        . '<span class="badge bg-secondary">' . $lang->showthread['pb_pid'] . ' ' . $pid . '</span>'
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
        . '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">' . $lang->showthread['pb_deleting'] . '</span></div>'
        . '<p class="mt-2 text-muted">' . $lang->showthread['pb_deleting_post'] . '</p>'
        . '</div>'
        . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">'
        . '<i class="fa-solid fa-xmark me-1"></i>' . $lang->showthread['pb_cancel'] . '</button>'
        . '<button type="button" class="btn btn-danger" id="confirmDeleteBtn' . $pid . '">'
        . '<i class="fa-solid fa-trash me-1"></i>' . $lang->showthread['pb_delete_title'] . '</button>'
        . '</div>'
        . '</div></div></div>';

    return [$modal_edit, $modal_delete, $message2];
}

function build_postbit($post, $post_type = 0)
{
    global $db, $altbg, $theme, $mybb, $postcounter, $profile_fields, $regdateformat;
    global $wolcutoffmins, $usergroups, $enableattachments, $CURUSER, $f_postsperpage;
    global $moderator, $forummoderator, $thread, $permissions, $forum_threads;
    global $titlescache, $page, $forumpermissions, $attachcache;
    global $lang, $ismod, $inlinecookie, $inlinecount, $groupscache, $fid;
    global $parser, $cache, $ignored_users, $hascustomtitle;

    $hascustomtitle = 0;

    // Модалки правки/удаления и подписи поста берут тексты из ланга showthread.
    // На newreply.php (AJAX быстрого ответа), editpost/newthread (предпросмотр) он не загружен.
    if (!is_array($lang->showthread ?? null)) {
        $lang->load('showthread');
    }

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
    // Права и оформление автора (звание, стиль ника) - по его основной группе
    $post['usergroup'] = (int)($post['usergroup'] ?? 0) ?: 1;
    $usergroup         = usergroup_permissions($post['usergroup']);



    /* ── Registered user vs guest ────────────────────────────────── */
    if ($post['userusername']) {
        $post['username']          = $post['userusername'];
        $post['profilelink_plain'] = get_profile_link((int)$post['id']);
        $post['username_formatted'] = format_name($post['username'], $post['usergroup']);
        $post['profilelink']       = build_profile_link($post['username_formatted'], (int)$post['id']);

        if (trim($post['usertitle']) != "") $hascustomtitle = 1;
        if (($usergroup['usertitle'] ?? '') != "" && !$hascustomtitle) $post['usertitle'] = $usergroup['usertitle'];
        $post['usertitle'] = htmlspecialchars_uni($post['usertitle']);

        $postnum           = $post['postnum'];
        $post['postnum']   = ts_nf($post['postnum']);
        $post['threadnum'] = ts_nf($post['threadnum']);

        $moderator = is_mod($usergroups);
        $timecut   = TIMENOW - $wolcutoffmins;

        if ($post['lastactive'] > $timecut && ($post['invisible'] != 1 || $usergroups['canviewwolinvis'] == 1) && $post['lastvisit'] != $post['lastactive']) {
            $post['onlinestatus'] = '<a href="online.php" class="pb-dot pb-dot--on" title="' . $lang->global['postbit_status_online'] . '"></a>';
        } else {
            $post['onlinestatus'] = '<span class="pb-dot" title="' . $lang->global['postbit_status_offline'] . '"></span>';
        }

        // Аватар строит postbit_avatar() (боковой и мобильный варианты)
        $post['useravatar'] = postbit_avatar($post);

        $post['userregdate']  = my_datee($regdateformat, $post['added']);
        $post['user_details'] = '<i class="bi bi-chat-fill"></i> ' . $post['postnum'];

		
    } else {
        $post['profilelink']  = format_name($post['username'], 1);
        $post['title']        = htmlspecialchars_uni($usergroup['title'] ?: 'guest');
        $post['userstars']    = '';
        $post['useravatar']   = postbit_avatar($post);   // иконка-заглушка гостя
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
    $postbit_parsed_message = null;
    $post['editedmsg'] = '';

    if (!$post_type) {
        if (!isset($forumpermissions)) $forumpermissions = forum_permissions($fid);

        if ($post['edituid'] != 0 && $post['edittime'] != 0 && $post['editusername'] != "") {
            $post['editdate']         = my_datee('relative', $post['edittime']);
            $post['editnote']         = sprintf($lang->showthread['pb_edit_note'] ?? '%s', $post['editdate']);
            $post['editusername']     = htmlspecialchars_uni($post['editusername']);
            $post['editedprofilelink'] = build_profile_link($post['editusername'], $post['edituid']);
            $editreason = '';
            if ($post['editreason'] != '') {
                $post['editreason'] = htmlspecialchars_uni($parser->parse_badwords($post['editreason']));
                $editreason = ' ' . $lang->global['postbit_editreason'] . ': ' . $post['editreason'];
            }
            $post['editedmsg'] = '<div class="pb-edited"><i class="fa-regular fa-pen-to-square"></i><span>'
                . $post['editnote'] . ' ' . $post['editedprofilelink'] . '.' . $editreason
                . '</span></div>';
        }

        $is_mod = is_mod($usergroups);

        if ($is_mod || ($forumpermissions['caneditposts'] == 1 && $CURUSER['id'] == $post['uid'] && $thread['closed'] != 1 && $CURUSER['id'] != 0)) {
            $pid = (int)$post['pid'];

            $del_label = $lang->showthread['pb_delete'] ?? 'pb_delete';
            $post['button_edit'] =
                // Десктоп: меню «Изменить» (быстрая правка / полная / удалить)
                '<div class="dropdown pb-only-lg">'
                . '<button type="button" class="pb-act" aria-expanded="false" data-bs-toggle="dropdown">'
                . '<i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '<i class="fa-solid fa-chevron-down pb-act__caret"></i>'
                . '</button>'
                . '<div class="dropdown-menu dropdown-menu-end">'
                . '<a href="javascript:void(0)" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editPostModal' . $pid . '">'
                . '<i class="fa-solid fa-clock"></i> &nbsp;' . $lang->global['postbit_quick_edit'] . '</a>'
                . '<a href="editpost.php?pid=' . $pid . '" class="dropdown-item">'
                . '<i class="fa-solid fa-pen-to-square"></i> &nbsp;' . $lang->global['postbit_full_edit'] . '</a>'
                . '<div class="dropdown-divider"></div>'
                . '<a href="javascript:void(0)" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deletePostModal' . $pid . '">'
                . '<i class="fa-solid fa-trash"></i> &nbsp;' . $del_label . '</a>'
                . '</div></div>'
                // Телефон: сразу полная правка и удаление
                . '<a href="editpost.php?pid=' . $pid . '" class="pb-act pb-only-sm">'
                . '<i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '</a>'
                . '<a href="javascript:void(0)" class="pb-act pb-act--danger pb-only-sm" data-bs-toggle="modal" data-bs-target="#deletePostModal' . $pid . '" title="' . $del_label . '">'
                . '<i class="fa-solid fa-trash"></i></a>';

            [$modals, $modaldelete, $postbit_parsed_message] = build_postbit_modals($post, $parser, $parser_options, $lang->global);
        }

        /* ── Inline mod checkbox ──────────────────────────────────── */
       if ($is_mod) {
    $inlinecheck = (!empty($inlinecookie) && isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], '|' . $post['pid'] . '|') !== false)
        ? 'checked="checked"' : '';
                

            $post['inlinecheck'] = '<span class="form-check form-switch pb-switch">'
                . '<input type="checkbox" class="form-check-input" name="inlinemod_' . (int)$post['pid'] . '" id="inlinemod_' . (int)$post['pid'] . '" value="1" ' . $inlinecheck . '>'
                . '</span>';

            if ($inlinecheck) $inlinecount++;
            if ($post['visible'] == 0) $invisiblepost = 1;
        } else {
            $post['inlinecheck'] = '';
        }

        /* ── Post URL ─────────────────────────────────────────────── */
        $post['postlink'] = get_post_link($post['pid'], $post['tid']);
        $post_number      = ts_nf($postcounter);
        $post['posturl']  = $post['inlinecheck']
            . '<a href="' . $post['postlink'] . '#pid' . (int)$post['pid'] . '" class="pb-numlink" title="' . $post['subject_title'] . '">#' . $post_number . '</a>';

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
                . '<i class="fa-solid fa-flag"></i> &nbsp;' . ($lang->showthread['pb_report_post'] ?? 'pb_report_post') . '</a>';
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
                . ' class="postbit_multiquote postbit_mirage postlinks pb-act">'
                . '<span id="multiquote_' . (int)$post['pid'] . '">'
                . '<i class="fa-solid fa-quote-left"></i> ' . $lang->global['postbit_button_multiquote']
                . '</span></a>';
        }

    } elseif ($post_type == 3) {
        $is_mod = is_mod($usergroups);
        if ($is_mod) {
            $post['button_edit'] =
                '<a href="admin/index.php?act=announcements_forum&amp;action=edit&amp;id=' . (int)$post['aid'] . '"'
                . ' title="' . ($lang->announcements['announcement_edit'] ?? $lang->global['postbit_button_edit']) . '"'
                . ' class="postbit_edit postlinks pb-act">'
                . '<i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '</a>';

            $post['button_quickdelete'] =
                '<a href="#"'
                . ' onclick="afDelete(' . (int)$post['aid'] . ', \'' . addslashes($post['subject']) . '\'); return false;"'
                . ' class="postbit_qdelete dropdown-item">'
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
        if ($status_type !== '') {
            $post['poststatus'] = '<div class="pb-status"><span class="status_type pb-tag pb-soft-warning"><i class="fa-solid fa-hourglass-half"></i>' . $status_type . '</span></div>';
        }
    }

    /* ── Highlight search terms ──────────────────────────────────── */
    if (!empty($mybb->input['highlight'])) {
        $parser_options['highlight'] = $mybb->input['highlight'];
        $post['subject'] = $parser->highlight_message($post['subject'], $parser_options['highlight']);
    }

    /* ── Parse message ───────────────────────────────────────────── */
    $parser_options = ['allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
                       'allow_imgcode'=>1,'allow_videocode'=>1,'filter_badwords'=>1];
    // Если build_postbit_modals() уже распарсила это же сообщение с теми же
    // parser_options (для модалки удаления) — переиспользуем результат вместо
    // повторного прохода парсера (двойной SQL-запрос на [torrent=ID] и т.п.)
    $post['message'] = $postbit_parsed_message ?? $parser->parse_message($post['message'], $parser_options);

    $post['attachments'] = '';
    if ($enableattachments != 0) get_post_attachments($id, $post);

    /* ── Signature ───────────────────────────────────────────────── */
    //if ($post['username'] && $post['signature'] != "" && ($CURUSER['id'] == 0 || $CURUSER['showsigs'] != 0)) {
	if ($post['username'] && $post['signature'] != "") {
        $post['signature'] = $parser->parse_message($post['signature'], [
            'allow_html'=>0,'allow_mycode'=>1,'allow_smilies'=>1,
            'allow_imgcode'=>0,'me_username'=>1,'filter_badwords'=>1,
        ]);
        $post['signature'] = $post['signature'] !== ''
            ? '<div class="signature scaleimages pb-sig">' . $post['signature'] . '</div>'
            : '';
    } else {
        $post['signature'] = '';
    }

    /* ── Ignored / own unapproved (только посты темы) ─────────────── */
    $post_visibility = $ignore_bit = '';

    if ($post_type == 0) {
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
                $ignored_message = ($lang->showthread['pb_unapproved_own'] ?? 'pb_unapproved_own');
                $ignore_bit      = build_ignore_bit($post, $ignored_message, $post_visibility, $lang->global);
            }

            // Ignored user
            if (is_array($ignored_users) && $post['uid'] != 0 && !empty($ignored_users[$post['uid']])) {
                $ignored_message = sprintf($lang->showthread['pb_ignored'] ?? '%s', htmlspecialchars_uni($post['username']));
                $ignore_bit      = build_ignore_bit($post, $ignored_message, $post_visibility, $lang->global);
            }
    }

    /* ── Render postbit ──────────────────────────────────────────── */
    $pid = (int)$post['pid'];

    // .inline_row — на неё вешает .trow_selected inline_moderation.js
    $postbit = postbit_css_link()
             . $ignore_bit
             . '<a name="pid' . $pid . '" id="pid' . $pid . '"></a>'
             . '<div class="pb-post" style="' . $post_visibility . '" id="post_' . $pid . '">'
             . '<div class="pb-side">' . $post['useravatar'] . '</div>'
             . '<article class="pb-card inline_row ' . $unapproved_shade . '">'
             . build_postbit_body($post, $unapproved_shade)
             . build_postbit_footer($post)
             . '</article>'
             . '</div>'
             . $modals
             . $modaldelete;

    $GLOBALS['post'] = '';
    return $postbit;
}

function get_post_attachments($id, &$post)
{
    global $attachcache, $mybb, $theme, $forumpermissions, $attachthumbnails, $lang;

    $validationcount = 0;
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
            $attbit = '<a href="attachment.php?aid=' . (int)$attachment['aid'] . '" target="_blank" class="pb-thumb">'
                    . '<img src="attachment.php?thumbnail=' . (int)$attachment['aid'] . '" alt="" title="' . htmlspecialchars($title_attr) . '">'
                    . '</a>';
        } elseif (($attachment['thumbnail'] == "SMALL" || $attachthumbnails == "no") && $isimage) {
            $attbit = '<img src="attachment.php?aid=' . (int)$attachment['aid'] . '" class="attachment pb-attimg" alt="" title="' . htmlspecialchars($title_attr) . '">';
        } else {
            $attbit = '<div class="pb-file">'
                    . '<span class="pb-file__ico">' . $attachment['icon'] . '</span>'
                    . '<div class="pb-file__text">'
                    . '<a href="attachment.php?aid=' . (int)$attachment['aid'] . '" target="_blank" title="' . $attachdate . '">' . $attachment['filename'] . '</a>'
                    . '<span class="pb-file__meta">' . $lang->global['postbit_attachment_size'] . ' <b>' . $attachment['filesize'] . '</b>'
                    . ' · ' . $lang->global['postbit_attachment_downloads'] . ' <b>' . $attachment['downloads'] . '</b></span>'
                    . '</div></div>';
        }

        if ($inline) {
            $post['message'] = preg_replace('#\[attachment=' . $attachment['aid'] . ']#si', $attbit, $post['message']);
        } else {
            if ($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $attachthumbnails == "yes") {
                $post['thumblist'] .= $attbit;
            } elseif (($attachment['thumbnail'] == "SMALL" || $attachthumbnails == "no") && $isimage) {
                if ($forumpermissions['candlattachments']) {
                    $post['imagelist'] .= $attbit;
                } else {
                    $post['thumblist'] .= $attbit;
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

        $post['attachmentlist'] .= '<div class="pb-status"><span class="pb-tag pb-soft-warning"><i class="fa-solid fa-hourglass-half"></i>' . $msg . '</span></div>';
    }

    $post['attachedthumbs'] = $post['thumblist']
        ? '<div class="pb-thumbs">' . $post['thumblist'] . '</div>'
        : '';

    $post['attachedimages'] = $post['imagelist']
        ? '<div class="pb-atts__lbl">' . $lang->global['postbit_attachments_images'] . '</div><div class="pb-thumbs">' . $post['imagelist'] . '</div>'
        : '';

    if ($post['attachmentlist'] || $post['thumblist'] || $post['imagelist']) {
        $post['attachments'] = '<div class="pb-atts">'
            . '<div class="pb-atts__lbl"><i class="fa-solid fa-paperclip"></i>' . $lang->global['postbit_attachments'] . '</div>'
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