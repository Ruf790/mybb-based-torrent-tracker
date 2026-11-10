<?php

declare(strict_types=1);

/* =============================================================================
   Комментарии к торрентам в «новом стиле» — та же карточка, что у постов форума
   (build_postbit() в functions_post.php): классы pb-* из postbit.css + ct-* из
   comments.css (только то, что есть у комментариев: выбор модератором, шапка-тулбар).
   Id/классы, на которые опираются скрипты, сохранены:
     #comment-{id} (+ .comment-selected), #pid{id}, #post_{id}, #pid_{id}, #edited_by_{id},
     .comment-checkbox (value, data-tid), #selectAllCheckbox, #mergeCommentsButton,
     #massDeleteButton, .edit-comment-btn + data-*, .postbit_qdelete + data-*,
     .report-comment-btn + data-*, .author_avatar img, #ajax_comment_preview(2)
   ============================================================================= */

// ── Lang helpers (same as in details.php; whichever file loads first defines them)
// $lang->load() turns {1} into %1$s, so both forms are substituted.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return $map ? strtr($str, $map) : $str;
    }
}

/** CSS и скрипты комментариев — один раз за запрос (в AJAX-ответах тоже: вставляются в страницу) */
function commenttable_assets(): string
{
    static $done = false;
    global $BASEURL;

    if ($done) {
        return '';
    }
    $done = true;

    $b = htmlspecialchars((string)$BASEURL, ENT_QUOTES);
    return '<link rel="stylesheet" href="' . $b . '/include/templates/default/style/comment_attachments.css">'
         . '<link rel="stylesheet" href="' . $b . '/include/templates/default/style/postbit.css?ver=302">'
         . '<link rel="stylesheet" href="' . $b . '/include/templates/default/style/comments.css?ver=3">'
         . '<script src="' . $b . '/scripts/edit_delete_comment.js?ver=5"></script>'
         . '<script src="' . $b . '/scripts/commenttable.js?ver=2"></script>';
}

/** Аватар автора комментария (.author_avatar — на ней core.js подменяет битые картинки) */
function commenttable_avatar(array $row, string $profile_url, string $mod = ''): string
{
    $cls = 'pb-avatar author_avatar' . ($mod !== '' ? ' pb-avatar--' . $mod : '');

    if (empty($row['user']) || empty($row['username'])) {
        return '<span class="' . $cls . ' pb-avatar--guest"><i class="fa-solid fa-user"></i></span>';
    }

    $av = format_avatar((string)($row['useravatar'] ?? ''), (string)($row['avatardimensions'] ?? ''));
    if (!empty($av['is_html'])) {
        return '<a href="' . $profile_url . '" class="' . $cls . ' pb-avatar--guest"><i class="fa-solid fa-user"></i></a>';
    }

    return '<a href="' . $profile_url . '" class="' . $cls . (!empty($av['is_placeholder']) ? ' pb-avatar--ph' : '') . '">'
         . '<img src="' . $av['image'] . '" alt="" loading="lazy"></a>';
}

function commenttable(array $rows, string $type = '', string $edit = '', bool $lc = false, bool $quote = false, bool $return = false): string
{
    global $CURUSER, $BASEURL, $rootpath, $pic_base_url, $lang, $usergroups;
    global $timeformat, $dateformat, $Torrent, $regdateformat;
    global $parser, $plugins, $db, $postcounter, $wolcutoffmins;
    global $mybb;

    // Strings live in the 'details' lang; other pages that render comments may not have loaded it
    if (empty($lang->details)) {
        $lang->load('details');
    }

    require_once(INC_PATH . '/class_parser.php');
    $parser = new postParser;

    $torrentEmbedIds = [];
    foreach ($rows as $row) {
        if (preg_match_all('#\[torrent=(\d+)\]#i', $row['text'] ?? '', $m)) {
            foreach ($m[1] as $tid) {
                $torrentEmbedIds[] = (int)$tid;
            }
        }
    }
    if (!empty($torrentEmbedIds)) {
        $parser->primeTorrentEmbedCache($torrentEmbedIds);
    }

    $parser_options = [
        "allow_html"      => 0,
        "allow_mycode"    => 1,
        "allow_smilies"   => 1,
        "allow_imgcode"   => 1,
        "allow_videocode" => 1,
        "filter_badwords" => 1,
        "nofollow_on"     => 1,
    ];

    $moderator    = is_mod($usergroups);
    $torrent_name = isset($Torrent['name']) ? htmlspecialchars_uni($Torrent['name']) : '';
    $timecut      = TIMENOW - (int)$wolcutoffmins;

    $showcommentstable = commenttable_assets() . generateHeaderSection($torrent_name, $moderator);

    // Реакции (include/functions_reactions.php): все комментарии страницы одним запросом,
    // CSS + конфиг + JS один раз. Без reactions.sql комментарии просто выводятся без них.
    require_once INC_PATH . '/functions_reactions.php';
    $reactSum = [];
    try {
        $reactSum = react_summary('comment', array_column($rows, 'id'), (int)($CURUSER['id'] ?? 0));
    } catch (\Throwable $e) {
        error_log('comment reactions: ' . $e->getMessage());
    }
    $canReact = (int)($CURUSER['id'] ?? 0) > 0
        && !(defined('UC_BANNED') && (int)($CURUSER['usergroup'] ?? 0) === UC_BANNED);
    $showcommentstable .= react_assets();

    foreach ($rows as $row) {
        // Закреплённый уже выведен первым: тут только считаем его в нумерации
        if (!empty($row['pin_skip'])) {
            $postcounter++;
            continue;
        }
        $is_pinned = !empty($row['is_pinned']);

        $pid = (int)$row['id'];
        $tid = (int)$row['torrentid'];
        $uid = (int)($row['user'] ?? 0);

        $postlink    = get_comment_link($pid, $tid);
        $profile_url = get_profile_link($uid);

        // ── Текст ─────────────────────────────────────────────────────────
        $post    = processPostData($row, $parser, $parser_options, $moderator, (int)$wolcutoffmins);
        $message = $post['message'];

        // Реакции: плашки под текстом, кнопка в подвале рядом с «Цитировать»
        $reactions_html = react_bar('comment', $pid, $reactSum[$pid] ?? [], $canReact, false);
        $react_button   = $canReact ? react_trigger('comment', $pid, 'pb-act') : '';

        // Вложения из заранее собранного массива (без лишних запросов)
        $attachments = '';
        $att_bulk    = $GLOBALS['all_attachments'] ?? [];
        if (!empty($att_bulk[$pid])) {
            $attachments = '<div class="pb-atts att-section">'
                . '<div class="pb-atts__lbl"><i class="fa-solid fa-paperclip"></i>' . $lang->global['postbit_attachments'] . '</div>'
                . render_comment_attachments_from_array($att_bulk[$pid])
                . '</div>';
        }

        // Подпись — только если она есть (раньше пустой блок с <hr> выводился всегда)
        $signature    = '';
        $signatureRaw = isset($row['signature']) && is_string($row['signature']) ? $row['signature'] : '';
        if ($signatureRaw !== '') {
            $sig = $parser->parse_message($signatureRaw, [
                "allow_html"      => 0,
                "allow_mycode"    => 1,
                "allow_smilies"   => 1,
                "allow_imgcode"   => 1,
                "me_username"     => 1,
                "filter_badwords" => 1,
            ]);
            if (trim($sig) !== '') {
                $signature = '<div class="signature scaleimages pb-sig">' . $sig . '</div>';
            }
        }

        // ── Автор ─────────────────────────────────────────────────────────
        $username_raw = (string)($row['username'] ?? '');
        $profilelink  = build_profile_link(format_name(htmlspecialchars_uni($username_raw), (int)($row['usergroup'] ?? 1)), $uid);
        $postdate     = my_datee('relative', $row['dateline']);
        $usertitle    = trim((string)($row['usertitle'] ?? '')) !== '' ? (string)$row['usertitle'] : (string)($row['grouptitle'] ?? '');
        $usertitle    = $usertitle !== '' ? '<span class="pb-utitle">' . htmlspecialchars_uni($usertitle) . '</span>' : '';

        $is_online = $row['lastactive'] > $timecut
            && ($row['invisible'] != 1 || $moderator)
            && $row['lastvisit'] != $row['lastactive'];
        $onlinestatus = $is_online
            ? '<a href="online.php" class="pb-dot pb-dot--on" title="' . $lang->global['postbit_status_online'] . '"></a>'
            : '<span class="pb-dot" title="' . $lang->global['postbit_status_offline'] . '"></span>';

        // ── Изменено ──────────────────────────────────────────────────────
        $editedmsg  = '';
        $editreason = '';
        if ($row['editedby'] != 0 && $row['editedat'] != 0 && $row['editedbyuname'] != "") {
            $editedat     = my_datee('relative', $row['editedat']);
            $editedby     = build_profile_link(htmlspecialchars_uni($row['editedbyuname']), $row['editedby']);
            $editnote     = ags_fmt(htmlspecialchars($lang->details['ct_edited_note']), $editedat, $editedby);
            $reason_html  = '';
            if ($row['editreason'] != "") {
                // раньше parse_badwords() тут же перетирался сырой строкой
                $editreason  = htmlspecialchars_uni($parser->parse_badwords($row['editreason']));
                $reason_html = ' ' . ags_fmt(htmlspecialchars($lang->details['ct_edit_reason']), $editreason);
            }
            $editedmsg = '<div class="pb-edited"><i class="fa-regular fa-pen-to-square"></i><span>' . $editnote . $reason_html . '</span></div>';
        }

        $input_editreason = '<div class="editreason" id="editreason_' . $pid . '_original" style="display: none;">'
            . '<input type="text" class="form-control mb-2" name="editreason" size="40" maxlength="150" id="quickedit_' . $pid . '_editreason_original"'
            . ' placeholder="' . $lang->global['postbit_editreason'] . '..." value="' . $editreason . '" />'
            . '</div>';

        // ── Кнопки ────────────────────────────────────────────────────────
        $can_manage  = $uid === (int)$CURUSER['id'] || $moderator;
        $button_edit = $can_manage ? generateEditButton($pid, $row, $lang) : '';
        $button_del  = $can_manage ? generateDeleteButton($row) : '';

        // Цитата в форму быстрого комментария (quote() из commenttable.js)
        $QuoteTag = htmlspecialchars($db->escape_string(
            '[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]'
        ));
        $button_quote = '<a href="javascript:void(0);" onclick="quote(\'message\', \'comment\', \'' . $QuoteTag . '\');" class="pb-act postbit_multiquote">'
            . '<i class="fa-solid fa-quote-left"></i>' . htmlspecialchars($lang->details['ct_quote']) . '</a>';

        $QuoteTagRaw  = '[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]';
        $menu_reply   = '<a href="comment.php?action=add&amp;tid=' . $tid . '&amp;quote=' . urlencode($QuoteTagRaw) . '" class="dropdown-item">'
            . '<i class="fa-solid fa-reply"></i> &nbsp;' . htmlspecialchars($lang->details['ct_reply']) . '</a>';
        $menu_report  = '<a class="dropdown-item report-comment-btn" href="#reportCommentModal" data-bs-toggle="modal"'
            . ' data-comment-id="' . $pid . '"'
            . ' data-comment-author-id="' . $uid . '"'
            . ' data-comment-text="' . htmlspecialchars($row['text'], ENT_QUOTES) . '"'
            . ' data-comment-author="' . htmlspecialchars($username_raw) . '"'
            . ' data-comment-date="' . date('Y-m-d H:i', (int)$row['dateline']) . '"'
            . ' data-parent-id="' . $tid . '">'
            . '<i class="fa-solid fa-flag"></i> &nbsp;' . htmlspecialchars($lang->details['report']) . '</a>';

        // Закрепить / открепить (только на странице раздачи)
        $menu_pin = ($moderator && $torrent_name !== '')
            ? '<form method="post" action="comment.php?action=pin&amp;cid=' . $pid . '" class="m-0">'
              . '<input type="hidden" name="my_post_key" value="' . htmlspecialchars((string)($mybb->post_code ?? ''), ENT_QUOTES) . '">'
              . '<button type="submit" class="dropdown-item"><i class="fa-solid fa-thumbtack"></i> &nbsp;'
              . htmlspecialchars($is_pinned ? ($lang->details['ct_unpin'] ?? 'Unpin') : ($lang->details['ct_pin'] ?? 'Pin to top'))
              . '</button></form>'
            : '';

        $menu = '<div class="dropdown pb-menu">'
            . '<button type="button" class="pb-more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More">'
            . '<i class="fa-solid fa-ellipsis-vertical"></i></button>'
            . '<div class="dropdown-menu dropdown-menu-end">' . $menu_reply . $menu_pin . $button_del . $menu_report . '</div>'
            . '</div>';

        // ── Номер + выбор модератором ─────────────────────────────────────
        if (!$is_pinned) {
            $postcounter++;
        }
        $select = $moderator
            ? '<span class="form-check form-switch pb-switch">'
              . '<input class="form-check-input comment-checkbox" type="checkbox" role="switch" name="comment_ids[]"'
              . ' value="' . $pid . '" data-tid="' . $tid . '" data-post-id="' . $pid . '"'
              . ' onchange="toggleCommentSelect(this)" id="cmtsel-' . $pid . '"'
              . ' title="' . htmlspecialchars($lang->details['ct_select_for_deletion'], ENT_QUOTES) . '"></span>'
            : '';
        // id чекбокса раньше был comment-checkbox-N: он попадал в [id^="comment-"],
        // и скрипты удаления неверно считали число комментариев на странице
        $posturl = $select . ($is_pinned
            ? '<a href="' . $postlink . '#pid' . $pid . '" class="pb-tag pb-soft-warning text-decoration-none"><i class="fa-solid fa-thumbtack"></i>' . htmlspecialchars($lang->details['ct_pinned'] ?? 'Pinned') . '</a>'
            : '<a href="' . $postlink . '#pid' . $pid . '" class="pb-numlink" title="' . $torrent_name . '">#' . ts_nf($postcounter) . '</a>');

        // ── Карточка ──────────────────────────────────────────────────────
        $showcommentstable .= '<div class="closest ct-item' . ($is_pinned ? ' ct-item--pinned' : '') . '" id="comment-' . $pid . '">'
            . '<a name="pid' . $pid . '" id="pid' . $pid . '"></a>'
            . '<div class="pb-post" id="post_' . $pid . '">'
            .   '<div class="pb-side">' . commenttable_avatar($row, $profile_url) . '</div>'
            .   '<article class="pb-card inline_row">'
            .     '<div class="pb-head">'
            .       '<div class="pb-head__av">' . commenttable_avatar($row, $profile_url, 'sm') . '</div>'
            .       '<div class="pb-who">'
            .         '<div class="pb-author card-title">' . $profilelink . $onlinestatus . '</div>'
            .         '<div class="pb-meta">' . $usertitle . '<span class="pb-date"><i class="fa-regular fa-clock"></i>' . $postdate . '</span></div>'
            .       '</div>'
            .       '<div class="pb-num">' . $posturl . '</div>'
            .     '</div>'
            .     '<div class="pb-body">'
            .       '<div class="post_body scaleimages" id="pid_' . $pid . '">' . $message . '</div>'
            .       $attachments
            .       $reactions_html
            .       '<span class="post_edit pb-edited-wrap" id="edited_by_' . $pid . '">' . $editedmsg . '</span>'
            .       $signature
            .       $input_editreason
            .     '</div>'
            .     '<div class="pb-foot">'
            .       $button_quote
            .       $react_button
            .       '<span class="pb-foot__sp"></span>'
            .       $button_edit
            .       $menu
            .     '</div>'
            .   '</article>'
            . '</div>'
            . '</div>';
    }

    // Контейнеры быстрого комментария — только на странице раздачи, не в AJAX-ответах
    if ($torrent_name !== '') {
        $showcommentstable .= '<div id="ajax_comment_preview"></div><div id="ajax_comment_preview2"></div>';
    }

    if ($return) {
        return $showcommentstable;
    }

    echo $showcommentstable;
    return '';
}

/**
 * Process post data for display
 */
function processPostData(array $row, postParser $parser, array $parser_options, bool $moderator, int $wolcutoffmins): array
{
    return [
        'message' => $parser->parse_message($row['text'], $parser_options),
    ];
}

/**
 * Кнопка «Изменить»: на десктопе меню (быстрая правка / полная), на телефоне — ссылка на полную правку
 */
function generateEditButton(int $pid, array $row, object $lang): string
{
    return '<div class="dropdown pb-only-lg">'
         . '<button type="button" class="pb-act" id="editDropdown' . $pid . '" data-bs-toggle="dropdown" aria-expanded="false">'
         . '<i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '<i class="fa-solid fa-chevron-down pb-act__caret"></i>'
         . '</button>'
         . '<div class="dropdown-menu dropdown-menu-end" aria-labelledby="editDropdown' . $pid . '">'
         . '<a href="#" class="popup_item dropdown-item edit-comment-btn"'
         . ' data-commentid="' . (int)$row['id'] . '"'
         . ' data-torrentid="' . (int)$row['torrentid'] . '"'
         . ' data-commenttext="' . htmlspecialchars($row['text'], ENT_QUOTES) . '">'
         . '<i class="fa-solid fa-clock"></i> &nbsp;' . $lang->global['postbit_quick_edit'] . '</a>'
         . '<a href="comment.php?action=edit&amp;pid=' . $pid . '" class="dropdown-item">'
         . '<i class="fa-solid fa-pen-to-square"></i> &nbsp;' . $lang->global['postbit_full_edit'] . '</a>'
         . '</div></div>'
         . '<a href="comment.php?action=edit&amp;pid=' . $pid . '" class="pb-act pb-only-sm">'
         . '<i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '</a>';
}

/**
 * Пункт «Удалить» в меню «⋮» (модалка #deleteCommentModal, edit_delete_comment.js)
 */
function generateDeleteButton(array $row): string
{
    global $lang;

    return '<a href="#" class="postbit_qdelete dropdown-item text-danger"'
         . ' data-commentid="' . (int)$row['id'] . '"'
         . ' data-torrentid="' . (int)$row['torrentid'] . '"'
         . ' data-author="' . htmlspecialchars($row['username'] ?? $lang->details['unknown']) . '"'
         . ' data-date="' . (isset($row['dateline']) ? date('d M Y, H:i', (int)$row['dateline']) : '') . '"'
         . ' data-preview="' . htmlspecialchars($row['text'] ?? '') . '"'
         . ' data-bs-toggle="modal" data-bs-target="#deleteCommentModal">'
         . '<i class="fa-solid fa-trash"></i> &nbsp;' . htmlspecialchars($lang->details['btn_delete']) . '</a>';
}

/**
 * Панель над списком: название торрента + (модератору) выбрать все / объединить / удалить
 */
function generateHeaderSection(string $torrent_name, bool $moderator): string
{
    global $mybb, $lang;

    // Без названия раздачи (AJAX-ответы, comment.php) панель не нужна
    if ($torrent_name === '') {
        return '';
    }

    $moderator_html = $moderator
        ? '<script>window.CS_POST_CODE = ' . json_encode($mybb->post_code ?? '') . ';</script>'
          . '<div class="ct-bar__mod">'
          . '<label class="form-check form-switch ct-bar__all mb-0" for="selectAllCheckbox">'
          . '<input class="form-check-input" type="checkbox" role="switch" id="selectAllCheckbox" onchange="toggleSelectAll(this)">'
          . '<span class="form-check-label">' . htmlspecialchars($lang->details['ct_select_all']) . '</span></label>'
          . '<button type="button" id="mergeCommentsButton" class="btn btn-sm btn-primary d-none" onclick="mergeComments()">'
          . '<i class="fa-solid fa-code-merge me-1"></i>' . htmlspecialchars($lang->details['ct_merge_selected']) . '</button>'
          . '<button type="button" id="massDeleteButton" class="btn btn-sm btn-danger d-none" onclick="massDeleteComments()">'
          . '<i class="fa-solid fa-trash me-1"></i>' . htmlspecialchars($lang->details['ct_delete_selected']) . '</button>'
          . '</div>'
        : '';

    return '<div class="ct-bar">'
         . ($torrent_name !== '' ? '<span class="ct-bar__title"><i class="fa-solid fa-magnet"></i>' . $torrent_name . '</span>' : '')
         . $moderator_html
         . '</div>';
}

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}
