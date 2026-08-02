<?php

declare(strict_types=1);

function commenttable(array $rows, string $type = '', string $edit = '', bool $lc = false, bool $quote = false, bool $return = false): string
{
    global $CURUSER, $BASEURL, $rootpath, $pic_base_url, $lang, $usergroups;
    global $timeformat, $dateformat, $Torrent, $regdateformat;
    global $parser, $plugins, $db, $postcounter, $wolcutoffmins;
    global $mybb;

    $is_mod = is_mod($usergroups);

    
    require_once(INC_PATH . '/class_parser.php');
    $parser = new postParser;

    $parser_options = [
        "allow_html" => 0,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "allow_videocode" => 1,
        "filter_badwords" => 1
    ];

    $moderator = is_mod($usergroups);
    
    $totalrows = count($rows);
    $quickmenu = '';
    $showcommentstable = '';

    $ajax_quick_edit_loaded = false;
    $quote_loaded = false;
    $ajax_quick_report_loaded = false;
    $QuickVoteLoaded = false;

    ob_start();
?>


<link rel="stylesheet" href="<?= htmlspecialchars($BASEURL) ?>/include/templates/default/style/comment_attachments.css">
<script type="text/javascript" src="<?= htmlspecialchars($BASEURL) ?>/scripts/edit_delete_comment.js"></script>

<style>
/* Comment card base transition */
.closest .card {
    transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    border-left: 3px solid transparent !important;
}
/* Selected state */
.comment-selected .card {
    border-color: var(--bs-danger) !important;
    border-left: 3px solid var(--bs-danger) !important;
    background-color: rgba(var(--bs-danger-rgb), 0.035) !important;
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-danger-rgb), 0.12) !important;
}
.comment-selected .card-footer {
    background-color: rgba(var(--bs-danger-rgb), 0.055) !important;
}
/* Switch sizing */
.comment-checkbox {
    width: 2.2em !important;
    height: 1.2em !important;
    cursor: pointer;
}
</style>

<script>
function toggleCommentSelect(checkbox) {
    const wrapper = document.getElementById('comment-' + checkbox.value);
    if (wrapper) {
        wrapper.classList.toggle('comment-selected', checkbox.checked);
    }
    toggleMassDeleteButton();
    toggleMergeButton();
}

function toggleSelectAll(masterSwitch) {
    document.querySelectorAll('.comment-checkbox').forEach(cb => {
        cb.checked = masterSwitch.checked;
        const wrapper = document.getElementById('comment-' + cb.value);
        if (wrapper) {
            wrapper.classList.toggle('comment-selected', masterSwitch.checked);
        }
    });
    toggleMassDeleteButton();
    toggleMergeButton();
}

function toggleMassDeleteButton() {
    const count = document.querySelectorAll('.comment-checkbox:checked').length;
    const btn = document.getElementById('massDeleteButton');
    if (btn) {
        btn.classList.toggle('d-none', count === 0);
        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete Selected (' + count + ')';
    }
}

function toggleMergeButton() {
    const count = document.querySelectorAll('.comment-checkbox:checked').length;
    const btn = document.getElementById('mergeCommentsButton');
    if (btn) {
        // Merge имеет смысл только от 2 выбранных комментариев
        btn.classList.toggle('d-none', count < 2);
        btn.innerHTML = '<i class="fa-solid fa-code-merge"></i> Merge Selected (' + count + ')';
    }
}

function mergeComments() {
    const checked = [...document.querySelectorAll('.comment-checkbox:checked')].map(cb => cb.value);
    if (checked.length < 2) {
        return;
    }
    if (!confirm('Merge ' + checked.length + ' selected comments into one? This cannot be undone.')) {
        return;
    }

    const btn = document.getElementById('mergeCommentsButton');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Merging...';
    }

    fetch('comment.php?action=merge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            comment_ids: checked.join(','),
            my_post_key: window.CS_POST_CODE || ''
        })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + (data.error || 'Unknown error'));
                if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                return;
            }

            // Убираем поглощённые комментарии из DOM
            (data.removed_ids || []).forEach(id => {
                const el = document.getElementById('comment-' + id);
                if (el) el.remove();
            });

            // Заменяем мастер-комментарий на обновлённый HTML
            const masterEl = document.getElementById('comment-' + data.master_id);
            if (masterEl && data.html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = data.html;
                masterEl.replaceWith(...tmp.childNodes);
            }

            toggleMassDeleteButton();
            toggleMergeButton();
        })
        .catch(() => {
            alert('Merge failed. Please try again.');
            if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
        });
}
</script>



<?php
    $modals_html = ob_get_clean();
    $showcommentstable = $modals_html;
	
    $torrent_name = isset($Torrent['name']) ? htmlspecialchars_uni($Torrent['name']) : '';
    $showcommentstable .= generateHeaderSection($torrent_name, $moderator);



    foreach ($rows as $row) {
        $p_commenthistory = $p_edit = $p_delete = $p_text = $p_report = $p_quote = '';

        if ($quote === true && !$quote_loaded) {
            $p_quote .= '
<script type="text/javascript">
    function quote(textarea,form,quote) {
        var area=document.forms[form].elements[textarea];
        area.value=area.value+" "+quote+" ";
        area.focus();
    };
</script>';
            $quote_loaded = true;
        }

        $QuoteTag = htmlspecialchars($db->escape_string(
            '[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]'
        ));
        
        $p_quote .= '<a href="javascript:void(0);" onclick="quote(\'message\', \'comment\', \'' . $QuoteTag . '\');" class="postbit_multiquote postbit_mirage postlinks">
        <i class="fa-solid fa-quote-left"></i> &nbsp;Quote</a>';

        // Process post data
        $post = processPostData($row, $parser, $parser_options, $moderator, (int)$wolcutoffmins);
        
        $pid = (int)$row['id'];
        $tid = (int)$row['torrentid'];
        $postlink = get_comment_link($pid, $tid);

        // Attachments from bulk pre-fetched array (no extra DB query)
       
$comment_attachments_html = '';
$att_bulk = $GLOBALS['all_attachments'] ?? [];
if (!empty($att_bulk[$pid])) {
    $atts_html = render_comment_attachments_from_array($att_bulk[$pid]);
    $comment_attachments_html = '
    <div class="att-section mt-3 pt-2 border-top border-opacity-25">
        <div class="att-section-header text-muted small mb-2">
            <i class="fas fa-paperclip me-1"></i>
            <strong>' . $lang->global['postbit_attachments'] . '</strong>
        </div>
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
        
		
		$post['signature'] = '<div class="signature scaleimages mt-4">
	<hr />
	'.$post['signature'].'
</div>';

        // User data
        $post['username'] = $row['username'];
        $post['profilelink_plain'] = get_profile_link($row['user']);
        $post['username_formatted'] = format_name($post['username'], $row['usergroup'], $row['displaygroup']);
        $post['profilelink'] = build_profile_link($post['username_formatted'], $row['user']);
        $post['postdate'] = my_datee('relative', $row['dateline']);

        // Avatar
        $post['useravatar'] = '';
        
        $useravatar = format_avatar($row['useravatar'], $row['avatardimensions']);
            
			
	    $post['useravatar'] = '
	    <div class="d-none d-sm-none d-md-none d-lg-block d-xxl-block d-xxl-block">
                <div class="author_avatar"><a href="'.$post['profilelink_plain'].'"><img class="rounded img-fluid" style="width: 100px; padding: 0px;" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' /></a></div>
        </div>
        <div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">
                <div class="author_avatar"><a href="'.$post['profilelink_plain'].'"><img class="rounded img-fluid" style="width: 30px; height: 30px; padding: 0px;" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' /></a></div>
        </div>';
			
			
			
			
			
        

        // Initialize empty post elements
        $emptyElements = [
            'input_editreason', 'button_edit', 'button_quickdelete', 'button_quickrestore', 
            'button_quote', 'button_quickquote', 'button_report', 'button_reply_pm',
            'button_replyall_pm', 'button_forward_pm', 'button_delete_pm', 'poststatus',
            'iplogged', 'button_rep', 'button_warn', 'pid', 'editreason'
        ];
        
        foreach ($emptyElements as $element) {
            $post[$element] = '';
        }

        // Edited message
        $post['editedmsg'] = '';
        if ($row['editedby'] != 0 && $row['editedat'] != 0 && $row['editedbyuname'] != "") {
            $post['editedat'] = my_datee('relative', $row['editedat']);
            $post['editnote'] = sprintf('This post was last modified: ' . $post['editedat'] . ' by');
            $post['editedbyuname'] = htmlspecialchars_uni($row['editedbyuname']);
            $post['editedprofilelink'] = build_profile_link($row['editedbyuname'], $row['editedby']);
            
            $editreason = "";
            if ($row['editreason'] != "") {
                $post['editreason'] = $parser->parse_badwords($row['editreason']);
                $post['editreason'] = htmlspecialchars_uni($row['editreason']);
                $editreason = 'Edit Reason: ' . $post['editreason'];
            }
            
            $post['editedmsg'] = '<div class="mt-3"><i class="fa-regular fa-pen-to-square me-1"></i><span class="small">' . $post['editnote'] . ' ' . $post['editedprofilelink'] . '' . $editreason . '</span></div>';
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
        if ($moderator) {
            $postbit_qdelete = $lang->global['postbit_qdelete_post'];
            $post['button_quickdelete'] = generateDeleteButton($row);
        }


		$QuoteTagRaw = '[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]';
		
		// Quote button
        $post['button_quote'] = '
            <a href="comment.php?action=add&tid=' . $tid . '&quote=' . urlencode($QuoteTagRaw) . '" class="dropdown-item">
                 <i class="fa-solid fa-reply"></i> &nbsp;Reply
            </a>';

        
        $post['button_multiquote'] = $p_quote;
		
		
        $post['button_report'] = '
        <li>
           <a class="dropdown-item report-comment-btn" 
           href="#reportCommentModal" 
           data-bs-toggle="modal"
           data-comment-id="' . $row['id'] . '"
           data-comment-author-id="' . $row['user'] . '"
           data-comment-text="' . htmlspecialchars($row['text'], ENT_QUOTES) . '"
           data-comment-author="' . htmlspecialchars($row['username']) . '"
           data-comment-date="' . date('Y-m-d H:i', $row['dateline']) . '"
           data-parent-id="' . $row['torrentid'] . '">
             <i class="fa-solid fa-flag"></i> &nbsp;Report
           </a>
       </li>';
		
		

        $postcounter++;
        $post_number = ts_nf($postcounter);
       
        
        $post['posturl'] = '
		<a name="pid'.$row['id'].'" id="pid'.$row['id'].'"></a>
        <div class="d-flex align-items-center gap-2 justify-content-end">
            <a href="'.$postlink.'#pid'.$pid.'" title="'.$torrent_name.'" class="badge rounded-pill bg-primary text-decoration-none">#'.$post_number.'</a>
            '.($moderator ? '
            <div class="form-check form-switch m-0 p-0">
                <input class="form-check-input comment-checkbox m-0" type="checkbox" role="switch"
                       name="comment_ids[]"
                       value="'.$row['id'].'"
                       data-tid="'.$row['torrentid'].'"
                       data-post-id="'.$pid.'"
                       onchange="toggleCommentSelect(this)"
                       id="comment-checkbox-'.$row['id'].'"
                       title="Select for deletion"
                       style="cursor:pointer;">
            </div>' : '').'
        </div>';
		
		
		
		
		
		

        // Online status
        $timecut = TIMENOW - (int)$wolcutoffmins;
        if ($row['lastactive'] > $timecut && ($row['invisible'] != 1 || $moderator) && $row['lastvisit'] != $row['lastactive']) {
            
			$post['onlinestatus'] = '
			
			<a href="online.php" title="'.$lang->global['postbit_status_online'].'"><i class="fa-solid fa-circle-dot smaller" style="vertical-align: 0.115em; padding-left: 4px; color: #68c000"></i></a>
			
			';
			
			
        } else {
            
			
			$post['onlinestatus'] = '
			
			<i class="fa-solid fa-circle-dot smaller" title="'.$lang->global['postbit_status_offline'].'" style="vertical-align: 0.115em; padding-left: 4px; color: #ccc"></i>
			
			';
			
			
        }

        $post_visibility = '';
        
        
		
		$post['commentstables'] = '
		
		<div class="container mt-3">	
<!-- begin new layout -->
	

<a name="pid'.$pid.'" id="pid'.$post['pid'].'"></a>
<div class="row g-2 mb-4" style="'.$post_visibility.'" id="post_'.$pid.'">
<div class="col-auto d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">'.$post['useravatar'].'</div>
<div class="col">
<div class="card">
<div class="card-body inline_row">
				
<div class="row m-0 p-0 mb-3 mb-sm-3 mb-md-3 mb-lg-0 mb-xl-0 mb-xxl-0">
<div class="col-auto d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none m-0 p-0 me-2 me-sm-2 me-md-2 me-lg-0 me-xl-0 me-xxl-0 align-self-center">
'.$post['useravatar'].'
</div>
<div class="col m-0 p-0 align-self-center">
<h6 class="card-title mb-0 mb-sm-0 mb-md-0 mb-lg-3 mb-xl-3 mb-xxl-3"><span style="font-weight: 700">'.$post['profilelink'].'</span> '.$post['onlinestatus'].' &nbsp;&nbsp;<span class="text-uppercase text-desc small fw-normal">'.$post['postdate'].'</span></span></h6>
</div>
<div class="col-auto m-0 p-0 align-self-center text-end text-14">
'.$post['posturl'].'
</div>
</div>
		

<div  class="post_body scaleimages" id="pid_'.$pid.'">
'.$post['message'].'

</div>
'.$comment_attachments_html.'	





<span class="post_edit" id="edited_by_'.$post['pid'].'">'.$post['editedmsg'].'</span>			
'.$post['signature'].'
'.$post['poststatus'].'	'.$post['input_editreason'].' '.$post['iplogged'].'			
</div>
<div class="card-footer border-top-0 py-2 my-0">
			
			<!-- hidden -->
<div class="row mt-0 pt-0 mb-0 pb-0">
<div class="col-auto align-self-center small text-start pe-0 me-0">
'.$post['button_multiquote'].'</div>	

<div class="col-auto align-self-center small text-start pe-0 me-0">
'.$post['button_rep'].'</div>	

<div class="col align-self-center small text-end">
'.$post['button_edit'].'
</div>

<div class="col-auto text-end align-self-center">
<div class="hidden-text"><div class="dropdown d-flex justify-content-end align-self-center"><a class="bg-transparent border-0 text-muted" aria-expanded="true" data-bs-toggle="dropdown" role="button">&nbsp;<i class="fa-solid fa-ellipsis-vertical"></i>&nbsp;</a>
<div class="dropdown-menu border">
'.$post['button_quote'].'
'.$post['button_quickdelete'].'
'.$post['button_quickrestore'].'
'.$post['button_report'].'
'.$post['button_warn'].'
'.$post['button_reply_pm'].'
'.$post['button_replyall_pm'].'
'.$post['button_forward_pm'].'
'.$post['button_delete_pm'].'
</div>
</div>
</div>
</div>
<!-- /hidden -->
</div>		
</div>
</div>
</div>
</div>

</div>';







		
		

        // Build comment table
        $showcommentstable .= '<br />' .
        '<div class="closest" id="comment-' . $row['id'] . '">' .
        $post['commentstables'] .
        '</div>';
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
<!-- Edit dropdown for large screens (lg and up) -->
<div class="d-none d-lg-block">
  <div class="dropdown">
    <a class="postlinks dropdown-toggle" href="#" id="editDropdown' . $pid . '" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fa-solid fa-pencil"></i> &nbsp;' . $lang->global['postbit_button_edit'] . '
    </a>
    <div class="dropdown-menu border" aria-labelledby="editDropdown' . $pid . '">
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
</div>

<!-- Simple link for smaller screens -->
<div class="d-block d-lg-none">
  <a href="comment.php?action=edit&amp;pid=' . $pid . '" class="links">
    <i class="fa-solid fa-pencil"></i> &nbsp;Edit
  </a>
</div>';
}

/**
 * Generate delete button HTML
 */
function generateDeleteButton(array $row): string
{
    // Оставляем HTML как есть — strip_tags убираем
    $preview = htmlspecialchars($row['text'] ?? '');

    return '
  <a href="#" 
     class="postbit_qdelete postbit_mirage dropdown-item" 
     data-commentid="' . $row['id'] . '" 
     data-torrentid="' . $row['torrentid'] . '"
     data-author="' . htmlspecialchars($row['username'] ?? 'Unknown') . '"
     data-date="' . (isset($row['dateline']) ? date('d M Y, H:i', $row['dateline']) : '') . '"
     data-preview="' . $preview . '"
     data-bs-toggle="modal" data-bs-target="#deleteCommentModal">
     <i class="fa-solid fa-trash"></i>&nbsp;Delete
  </a>';
}

/**
 * Generate header section HTML
 */
function generateHeaderSection(string $torrent_name, bool $moderator): string
{
    global $mybb;

    $moderator_html = $moderator ? '
    <script>window.CS_POST_CODE = ' . json_encode($mybb->post_code ?? '') . ';</script>
    <div class="d-flex align-items-center gap-3">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="cursor:pointer;">
            <label class="form-check-label small text-black fw-normal" for="selectAllCheckbox">Select All</label>
        </div>
        <button id="mergeCommentsButton" class="btn btn-sm btn-primary d-none" onclick="mergeComments()">
            <i class="fa-solid fa-code-merge me-1"></i>Merge Selected
        </button>
        <button id="massDeleteButton" class="btn btn-sm btn-danger d-none" onclick="massDeleteComments()">
            <i class="fa-solid fa-trash me-1"></i>Delete Selected
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