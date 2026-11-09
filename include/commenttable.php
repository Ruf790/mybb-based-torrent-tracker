<?php

declare(strict_types=1);

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

function commenttable(array $rows, string $type = '', string $edit = '', bool $lc = false, bool $quote = false, bool $return = false): string
{
    global $CURUSER, $BASEURL, $rootpath, $pic_base_url, $lang, $usergroups;
    global $timeformat, $dateformat, $Torrent, $regdateformat;
    global $parser, $plugins, $db, $postcounter, $wolcutoffmins;
    global $mybb;

    $is_mod = is_mod($usergroups);

    // Strings live in the 'details' lang; other pages that render comments may not have loaded it
    if (empty($lang->details)) {
        $lang->load('details');
    }

    require_once INC_PATH . '/functions_post.php';   // постбит: postbit_css_link(), postbit_avatar(), build_postbit_*()
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
        "allow_html" => 0,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "allow_videocode" => 1,
        "filter_badwords" => 1,
		"nofollow_on" => 1
    ];

    $moderator = is_mod($usergroups);
    
    $totalrows = count($rows);
    $quickmenu = '';
    $showcommentstable = '';

    $ajax_quick_edit_loaded = false;
    $ajax_quick_report_loaded = false;
    $QuickVoteLoaded = false;

    ob_start();
?>


<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/include/templates/default/style/comment_attachments.css">
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/edit_delete_comment.js?ver=2"></script>
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/commenttable.js?ver=3"></script>





<?php
    $modals_html = ob_get_clean();
    $showcommentstable = postbit_css_link() . $modals_html;
	
    $torrent_name = isset($Torrent['name']) ? htmlspecialchars_uni($Torrent['name']) : '';
    // Шапка со списком — только там, где известна раздача (не в AJAX-ответах с одним комментарием)
    if ($torrent_name !== '') {
        $showcommentstable .= generateHeaderSection($torrent_name, $moderator);
    }

    // Reactions (include/functions_reactions.php): all comments of the page in one query,
    // CSS + config + JS once per page. Without reactions.sql the comments simply show none.
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
        $p_commenthistory = $p_edit = $p_delete = $p_text = $p_report = $p_quote = '';

        $QuoteTag = htmlspecialchars($db->escape_string(
            '[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]'
        ));
        
        $p_quote .= '<a href="javascript:void(0);" onclick="quote(\'message\', \'comment\', \'' . $QuoteTag . '\');" class="postbit_multiquote postbit_mirage pb-act">
        <i class="fa-solid fa-quote-left"></i>' . htmlspecialchars($lang->details['ct_quote']) . '</a>';

        // Process post data
        $post = processPostData($row, $parser, $parser_options, $moderator, (int)$wolcutoffmins);
        
        $pid = (int)$row['id'];
        $tid = (int)$row['torrentid'];
        $postlink = get_comment_link($pid, $tid);

        // Reactions: pills under the text, the "React" button in the footer next to "Quote"
        // (react_bar() returns '' for a guest when there are none)
        $reactions_html = react_bar('comment', $pid, $reactSum[$pid] ?? [], $canReact, false);
        $react_button   = $canReact ? react_trigger('comment', $pid, 'pb-act') : '';

        // Attachments from bulk pre-fetched array (no extra DB query)
       
$comment_attachments_html = '';
$att_bulk = $GLOBALS['all_attachments'] ?? [];
if (!empty($att_bulk[$pid])) {
    $atts_html = render_comment_attachments_from_array($att_bulk[$pid]);
    $comment_attachments_html = '
    <div class="pb-atts">
        <div class="pb-atts__lbl"><i class="fa-solid fa-paperclip"></i>' . $lang->global['postbit_attachments'] . '</div>
        ' . $atts_html . '
    </div>';
}
		
		
		

        // Process signature
        $signatureRaw = isset($row['signature']) && is_string($row['signature']) ? $row['signature'] : '';
        $sig_parser = [
            "allow_html" => 0,
            "allow_mycode" => 1,
            "allow_smilies" => 1,
            "allow_imgcode" => 1,
            "me_username" => 1,
            "filter_badwords" => 1
        ];
        
        $post['signature'] = $signatureRaw !== '' ? $parser->parse_message($signatureRaw, $sig_parser) : '';
        
		
        $post['signature'] = $post['signature'] !== ''
            ? '<div class="signature scaleimages pb-sig">' . $post['signature'] . '</div>'
            : '';

        // User data
        $post['username'] = $row['username'];
        $post['profilelink_plain'] = get_profile_link($row['user']);
        $post['username_formatted'] = format_name((string)$post['username'], (int)($row['usergroup'] ?? 1));
        $post['profilelink'] = build_profile_link($post['username_formatted'], $row['user']);
        $post['postdate'] = my_datee('relative', $row['dateline']);

        // Поля, которые читают общие функции постбита (include/functions_post.php)
        $post['pid']              = $pid;
        $post['userusername']     = htmlspecialchars_uni((string)($row['username'] ?? ''));
        $post['avatar']           = $row['useravatar'] ?? '';
        $post['avatardimensions'] = $row['avatardimensions'] ?? '';
        $usertitle                = trim((string)($row['usertitle'] ?? ''));
        $post['usertitle']        = htmlspecialchars_uni($usertitle !== '' ? $usertitle : (string)($row['grouptitle'] ?? ''));
        $post['attachments']      = $comment_attachments_html;
        $post['reactions']        = $reactions_html;
        $post['button_react']     = $react_button;
    
			
			
			
			
			
        

        // Initialize empty post elements
        $emptyElements = [
            'input_editreason', 'button_edit', 'button_quickdelete', 'button_quickrestore', 
            'button_quote', 'button_quickquote', 'button_report', 'button_reply_pm',
            'button_replyall_pm', 'button_forward_pm', 'button_delete_pm', 'poststatus',
            'iplogged', 'button_rep', 'button_warn', 'button_purgespammer', 'editreason'
        ];
        
        foreach ($emptyElements as $element) {
            $post[$element] = '';
        }

        // Edited message
        $post['editedmsg'] = '';
        if ($row['editedby'] != 0 && $row['editedat'] != 0 && $row['editedbyuname'] != "") {
            $post['editedat'] = my_datee('relative', $row['editedat']);
            $post['editedbyuname'] = htmlspecialchars_uni($row['editedbyuname']);
            $post['editedprofilelink'] = build_profile_link($row['editedbyuname'], $row['editedby']);
            $post['editnote'] = ags_fmt(htmlspecialchars($lang->details['ct_edited_note']), $post['editedat'], $post['editedprofilelink']);
            
            $editreason = "";
            if ($row['editreason'] != "") {
                $post['editreason'] = $parser->parse_badwords($row['editreason']);
                $post['editreason'] = htmlspecialchars_uni($row['editreason']);
                $editreason = ' ' . ags_fmt(htmlspecialchars($lang->details['ct_edit_reason']), $post['editreason']);
            }
            
            $post['editedmsg'] = '<div class="pb-edited"><i class="fa-regular fa-pen-to-square"></i><span>' . $post['editnote'] . $editreason . '</span></div>';
        }

        $post['input_editreason'] = '
		
		
		<div class="editreason" id="editreason_'.$pid.'_original" style="display: none;">
	        <input type="text" class="form-control border mb-2" style="margin: 6px 0;" name="editreason" size="40" maxlength="150" id="quickedit_'.$pid.'_editreason_original" placeholder="'.$lang->global['postbit_editreason'].'..." value="'.$post['editreason'].'" />
	        </div>
		
		';
		
		
		
		
		

        // Edit button
        if ($row['user'] == $CURUSER['id'] || $moderator) {
            $post['button_edit'] = generateEditButton($pid, $row, $lang);
        }

        // Delete button for moderators
        if ($row['user'] == $CURUSER['id'] || $moderator) {
            $postbit_qdelete = $lang->global['postbit_qdelete_post'];
            $post['button_quickdelete'] = generateDeleteButton($row);
        }


		$QuoteTagRaw = '[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]';
		
		// Quote button
        $post['button_quote'] = '
            <a href="comment.php?action=add&tid=' . $tid . '&quote=' . urlencode($QuoteTagRaw) . '" class="dropdown-item">
                 <i class="fa-solid fa-reply"></i> &nbsp;' . htmlspecialchars($lang->details['ct_reply']) . '
            </a>';

        
        $post['button_multiquote'] = $p_quote;
		
		
        $post['button_report'] = '
           <a class="dropdown-item report-comment-btn" 
           href="#reportCommentModal" 
           data-bs-toggle="modal"
           data-comment-id="' . $row['id'] . '"
           data-comment-author-id="' . $row['user'] . '"
           data-comment-text="' . htmlspecialchars($row['text'], ENT_QUOTES) . '"
           data-comment-author="' . htmlspecialchars($row['username']) . '"
           data-comment-date="' . date('Y-m-d H:i', $row['dateline']) . '"
           data-parent-id="' . $row['torrentid'] . '">
             <i class="fa-solid fa-flag"></i> &nbsp;' . htmlspecialchars($lang->details['report']) . '
           </a>';
		
		

        $postcounter++;
        $post_number = ts_nf($postcounter);
       
        
        $post['posturl'] = ($moderator ? '
            <span class="form-check form-switch pb-switch">
                <input class="form-check-input comment-checkbox" type="checkbox" role="switch"
                       name="comment_ids[]"
                       value="'.$row['id'].'"
                       data-tid="'.$row['torrentid'].'"
                       data-post-id="'.$pid.'"
                       onchange="toggleCommentSelect(this)"
                       id="comment-checkbox-'.$row['id'].'"
                       title="'.htmlspecialchars($lang->details['ct_select_for_deletion'], ENT_QUOTES).'">
            </span>' : '')
            . '<a href="'.$postlink.'#pid'.$pid.'" title="'.$torrent_name.'" class="pb-numlink">#'.$post_number.'</a>';
		
		
		
		
		
		

        // Online status
        $timecut = TIMENOW - (int)$wolcutoffmins;
        if ($row['lastactive'] > $timecut && ($row['invisible'] != 1 || $moderator) && $row['lastvisit'] != $row['lastactive']) {
            $post['onlinestatus'] = '<a href="online.php" class="pb-dot pb-dot--on" title="'.$lang->global['postbit_status_online'].'"></a>';
        } else {
            $post['onlinestatus'] = '<span class="pb-dot" title="'.$lang->global['postbit_status_offline'].'"></span>';
        }

        // Та же разметка, что у постов форума: шапка, текст и подвал строят функции постбита
        $post['commentstables'] = '
        <div class="container mt-3">
            <a name="pid'.$pid.'" id="pid'.$pid.'"></a>
            <div class="pb-post" id="post_'.$pid.'">
                <div class="pb-side">'.postbit_avatar($post).'</div>
                <article class="pb-card inline_row">'
                    . build_postbit_body($post, '')
                    . build_postbit_footer($post) . '
                </article>
            </div>
        </div>';

        // Build comment table
        $showcommentstable .= '<div class="closest" id="comment-' . $row['id'] . '">'
                            . $post['commentstables']
                            . '</div>';
    }

    $showcommentstable .= '<div style="display: block;" id="ajax_comment_preview"></div><div style="display: block;" id="ajax_comment_preview2"></div>';

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
    $post = [];
    
    $post['editedat'] = my_datee('relative', $row['editedat']);
    $post['editnote'] = 'Last edited: ' . $post['editedat'] . ' by';
    $post['editedbyuname'] = htmlspecialchars_uni($row['editedbyuname']);
    $post['editedprofilelink'] = build_profile_link($row['editedbyuname'], $row['editedby']);
    $post['message'] = $parser->parse_message($row['text'], $parser_options);
    
    return $post;
}

/**
 * Generate edit button HTML
 */
function generateEditButton(int $pid, array $row, object $lang): string
{
    return '
<div class="dropdown pb-only-lg">
  <button type="button" class="pb-act" id="editDropdown' . $pid . '" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '<i class="fa-solid fa-chevron-down pb-act__caret"></i>
  </button>
  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="editDropdown' . $pid . '">
    <a href="#"
       class="popup_item dropdown-item edit-comment-btn"
       data-commentid="' . $row['id'] . '"
       data-torrentid="' . $row['torrentid'] . '"
       data-commenttext="' . htmlspecialchars($row['text'], ENT_QUOTES) . '">
      <i class="fa-solid fa-clock"></i> &nbsp;' . $lang->global['postbit_quick_edit'] . '
    </a>
    <a href="comment.php?action=edit&amp;pid=' . $pid . '" class="dropdown-item">
      <i class="fa-solid fa-pen-to-square"></i> &nbsp;' . $lang->global['postbit_full_edit'] . '
    </a>
  </div>
</div>
<a href="comment.php?action=edit&amp;pid=' . $pid . '" class="pb-act pb-only-sm">
  <i class="fa-solid fa-pencil"></i>' . $lang->global['postbit_button_edit'] . '
</a>';
}

/**
 * Generate delete button HTML
 */
function generateDeleteButton(array $row): string
{
    global $lang;

    // Оставляем HTML как есть — strip_tags убираем
    $preview = htmlspecialchars($row['text'] ?? '');

    return '
  <a href="#" 
     class="postbit_qdelete postbit_mirage dropdown-item text-danger" 
     data-commentid="' . $row['id'] . '" 
     data-torrentid="' . $row['torrentid'] . '"
     data-author="' . htmlspecialchars($row['username'] ?? $lang->details['unknown']) . '"
     data-date="' . (isset($row['dateline']) ? date('d M Y, H:i', $row['dateline']) : '') . '"
     data-preview="' . $preview . '"
     data-bs-toggle="modal" data-bs-target="#deleteCommentModal">
     <i class="fa-solid fa-trash"></i>&nbsp;' . htmlspecialchars($lang->details['btn_delete']) . '
  </a>';
}

/**
 * Generate header section HTML
 */
function generateHeaderSection(string $torrent_name, bool $moderator): string
{
    global $mybb, $lang;

    $moderator_html = $moderator ? '
    <script>window.CS_POST_CODE = ' . json_encode($mybb->post_code ?? '') . ';</script>
    <div class="d-flex align-items-center gap-3">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="cursor:pointer;">
            <label class="form-check-label small text-black fw-normal" for="selectAllCheckbox">' . htmlspecialchars($lang->details['ct_select_all']) . '</label>
        </div>
        <button id="mergeCommentsButton" class="btn btn-sm btn-primary d-none" onclick="mergeComments()">
            <i class="fa-solid fa-code-merge me-1"></i>' . htmlspecialchars($lang->details['ct_merge_selected']) . '
        </button>
        <button id="massDeleteButton" class="btn btn-sm btn-danger d-none" onclick="massDeleteComments()">
            <i class="fa-solid fa-trash me-1"></i>' . htmlspecialchars($lang->details['ct_delete_selected']) . '
        </button>
    </div>' : '';

    return '
    </br>
    <div class="container-md">
      <div class="card border-0 mb-4">
        <div class="card-header rounded-bottom text-19 fw-bold d-flex justify-content-between align-items-center">
            ' . $torrent_name . '
            ' . $moderator_html . '
        </div>
      </div>
    </div>';
}

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}
?>