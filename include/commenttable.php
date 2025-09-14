<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

//define("IN_ARCHIVE", true);


  
	
	
  function commenttable ($rows, $type = '', $edit = '', $lc = false, $quote = false, $return = false)
  {
    global $CURUSER;
    global $BASEURL;
    global $rootpath;
    global $pic_base_url;
    global $lang;
    global $usergroups;
    global $timeformat;
    global $dateformat;
    global $useajax;
    global $torrent;
    global $regdateformat;
	global $parser;
	global $plugins;
	global $db;
	global $postcounter;
	global $wolcutoffmins;
	global $mybb;
	global $plugins;
	global $templates;
	global $templatelist;
    //include_once INC_PATH . '/functions_ratio.php';
	
	
	$is_mod = is_mod($usergroups);
	
	require_once(INC_PATH.'/class_parser.php');
    $parser = new postParser;
  

    $parser_options = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"allow_videocode" => 1,
		"filter_badwords" => 1
    );
	
	
	
	


	
	
	
?>





<!-- Delete Comment Modal -->
<div class="modal fade" id="deleteCommentModal" tabindex="-1" aria-labelledby="deleteCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteCommentModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this comment? This action cannot be undone.
      </div>
      <div class="modal-body" id="errorModalBody">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmDeleteComment" type="button" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>






<!-- Edit Comment Modal -->
<div class="modal fade" id="editCommentModal" tabindex="-1" aria-labelledby="editCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editCommentModalLabel">Edit Comment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- BBCode Toolbar -->
        
		
		<div class="mb-2">
  <!-- Text Styles -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[b]', '[/b]')"><b>B</b></button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[i]', '[/i]')"><i>I</i></button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[u]', '[/u]')"><u>U</u></button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[s]', '[/s]')"><s>S</s></button>

  <!-- Alignment -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[left]', '[/left]')">Left</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[center]', '[/center]')">Center</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[right]', '[/right]')">Right</button>

  <!-- Color & Size -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[color=red]', '[/color]')">Red</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[size=18]', '[/size]')">Size</button>

  <!-- Links & Media -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[url]', '[/url]')">URL</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[img]', '[/img]')">IMG</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[video]', '[/video]')">Video</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[youtube]', '[/youtube]')">YouTube</button>

  <!-- Quote & Code -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[quote]', '[/quote]')">Quote</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[code]', '[/code]')">Code</button>

  <!-- Lists -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[list]\n[*]', '\n[/list]')">List</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[list=1]\n[*]', '\n[/list]')">#List</button>
</div>
		
		

        <!-- Textarea -->
        <textarea id="editCommentText" class="form-control mb-3" rows="6" placeholder="Edit your comment..."></textarea>

        <!-- Live Preview -->
        <h6>Live Preview</h6>
        <div id="bbcodePreview" class="border p-2 bg-light rounded" style="min-height: 100px;"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmEditComment" type="button" class="btn btn-primary">Save Changes</button>
      </div>
    </div>
  </div>
</div>









<!-- Mass Delete Confirm Modal -->
<div class="modal fade" id="massDeleteConfirmModal" tabindex="-1" aria-labelledby="massDeleteConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="massDeleteConfirmModalLabel">Confirm Mass Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <span id="selectedCommentsCount" class="fw-bold">0</span> comment(s)? This action cannot be undone.
      </div>
      <div class="modal-body">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmMassDelete" type="button" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>





<?
	
	
	
	
	echo  '<script type="text/javascript" src="'.$BASEURL.'/scripts/edit_delete_comment.js"></script>';
	
	
    $moderator = is_mod ($usergroups);
    $dt = get_date_time (gmtime () - TS_TIMEOUT);
    $totalrows = count ($rows);
    $quickmenu = '';
    $showcommentstable = '';
	
	
    $ajax_quick_edit_loaded = false;
    $quote_loaded = false;
    $ajax_quick_report_loaded = false;
    $QuickVoteLoaded = false;
    //$postcounter = 0;
	
	
	
	
    foreach ($rows as $row)
    {
      

      $p_commenthistory = $p_edit = $p_delete = $p_text = $p_report = $p_quote = '';
      
	 

      if ($quote === true)
      {
        if ($quote_loaded == false)
        {
          $p_quote .= '
				<script type="text/javascript">
					function quote(textarea,form,quote)
					{
						var area=document.forms[form].elements[textarea];
						area.value=area.value+" "+quote+" ";
						area.focus();
					};
				</script>';
				
				
				
          $quote_loaded = true;
        }

		
		$QuoteTag = htmlspecialchars ($db->escape_string('[quote=' . $row['username'] . ' pid=' . $row['id'] . ' dateline=' . $row['dateline'] . ']' . $row['text'] . '[/quote]'));
   
		
		$p_quote .= '<a href="javascript:void(0);" onclick="quote(\'message\', \'comment\', \'' . $QuoteTag . '\');" class="postbit_multiquote postbit_mirage postlinks">
        <i class="fa-solid fa-quote-left"></i> &nbsp;Quote</a>';
		
		
	  
	  }

      
	  //$row['editedby'] = '';
	  //$row['editedbyuname'] = '';

      
	  $post['editedat'] = my_datee('relative', $row['editedat']);
	  $post['editnote'] = sprintf('Last edited: '.$post['editedat'].' by');
	  $post['editedbyuname'] = htmlspecialchars_uni($row['editedbyuname']);
	  $post['editedprofilelink'] = build_profile_link($row['editedbyuname'], $row['editedby']);
	  
	 

	  $parser_options = array(
				"allow_html" => 1,
				"allow_mycode" => 1,
				"allow_smilies" => 1,
				"allow_imgcode" => 1,
				"allow_videocode" => 1,
				"filter_badwords" => 1
			);
	  
	  $post['message'] = $parser->parse_message($row['text'],$parser_options);
	  


	  
	  $pid = $row['id'];
	  $tid = $row['torrentid'];
	  

	  $postlink = get_comment_link($pid, $tid);
	  

	  
	  
	  
	  $sig_parser = array(
    "allow_html"      => 1,
    "allow_mycode"    => 1,
    "allow_smilies"   => 1,
    "allow_imgcode"   => 1,
    "me_username"     => 1,
    "filter_badwords" => 1
);

// Берём подпись, если есть, иначе пустую строку
$signatureRaw = isset($row['signature']) && is_string($row['signature']) ? $row['signature'] : '';
$post['signature'] = $signatureRaw !== '' ? $parser->parse_message($signatureRaw, $sig_parser) : '';

eval("\$post['signature'] = \"".$templates->get("postbit_signature")."\";");
	  
	  
	  
	  
	  
	  
	  
	  
	  // This post was made by a registered user
		$post['username'] = $row['username'];
		$post['profilelink_plain'] = get_profile_link($row['user']);
		$post['username_formatted'] = format_name($post['username'], $row['usergroup'], $row['displaygroup']);
		$post['profilelink'] = build_profile_link($post['username_formatted'], $row['user']);
	  
	    $post['postdate'] = my_datee('relative', $row['dateline']);
	  
	  
	    $post['useravatar'] = '';
		if(isset($CURUSER['showavatars']) && $CURUSER['showavatars'] != 0 || $CURUSER['id'] == 0)
		{
			$useravatar = format_avatar($row['useravatar'], $row['avatardimensions']);
	
			eval("\$post['useravatar'] = \"".$templates->get("postbit_avatar")."\";");
			
			
		}
		
		
	
	$post['input_editreason'] = '';
	$post['button_edit'] = '';
	$post['button_quickdelete'] = '';
	$post['button_quickrestore'] = '';
	$post['button_quote'] = '';
	$post['button_quickquote'] = '';
	$post['button_report'] = '';
	$post['button_reply_pm'] = '';
	$post['button_replyall_pm'] = '';
	$post['button_forward_pm']  = '';
	$post['button_delete_pm'] = '';
	$post['poststatus'] = '';
	$post['iplogged'] = '';
	$post['button_rep'] = '';
	$post['button_warn'] = '';
	$post['pid'] = '';
	$post['editreason'] = '';
	 
	 
	 
	 
	 
	 $post['editedmsg'] = '';
	 
	 
	 
	 if($row['editedby'] != 0 && $row['editedat'] != 0 && $row['editedbyuname'] != "")
	 {
			
			$post['editedat'] = my_datee('relative', $row['editedat']);
	        $post['editnote'] = sprintf('This post was last modified: '.$post['editedat'].' by');
	        $post['editedbyuname'] = htmlspecialchars_uni($row['editedbyuname']);
	        $post['editedprofilelink'] = build_profile_link($row['editedbyuname'], $row['editedby']);
			
			$editreason = "";
			if($row['editreason'] != "")
			{
				$post['editreason'] = $parser->parse_badwords($row['editreason']);
				$post['editreason'] = htmlspecialchars_uni($row['editreason']);
				$editreason = '
				
				
				Edit Reason: '.$post['editreason'].'
				
				
				';
			}
			$post['editedmsg'] = '<div class="mt-3"><span class="small">'.$post['editnote'].' '.$post['editedprofilelink'].''.$editreason.'</span></div>';
			
		}
	 
	 
	

	eval("\$post['input_editreason'] = \"".$templates->get("comment_editreason")."\";");
			
		
		
		
	 if (($row['user'] == $CURUSER['id'] OR $moderator))
     {
		  
		//eval("\$post['button_edit'] = \"".$templates->get("comment_edit")."\";");
	
$post['button_edit'] = '
<!-- Edit dropdown for large screens (lg and up) -->
<div class="d-none d-lg-block">
  <div class="dropdown">
    <a class="postlinks dropdown-toggle" href="#" id="editDropdown'.$pid.'" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fa-solid fa-pencil"></i> &nbsp;'.$lang->global['postbit_button_edit'].'
    </a>
    <div class="dropdown-menu border" aria-labelledby="editDropdown'.$pid.'">
      <a href="#"
         class="popup_item dropdown-item edit-comment-btn"
         data-commentid="'.(int)$row['id'].'"
         data-torrentid="'.(int)$row['torrentid'].'"
         data-commenttext="'.htmlspecialchars($row['text'], ENT_QUOTES).'">
        <i class="fa-solid fa-clock"></i> &nbsp;'.$lang->global['postbit_quick_edit'].'
      </a>
      <a href="comment.php?action=edit&amp;pid='.$pid.'" class="dropdown-item">
        <i class="fa-solid fa-pen-to-square"></i> &nbsp;'.$lang->global['postbit_full_edit'].'
      </a>
    </div>
  </div>
</div>

<!-- Simple link for smaller screens -->
<div class="d-block d-lg-none">
  <a href="comment.php?action=edit&amp;pid='.$pid.'" class="links">
    <i class="fa-solid fa-pencil"></i> &nbsp;Edit
  </a>
</div>';





	 }

	  
			
	
if ($moderator)
{
	
    $postbit_qdelete = $lang->global['postbit_qdelete_post'];    
	   
    
	
	///eval("\$post['button_quickdelete'] = \"".$templates->get("comment_quickdelete")."\";");
	
	
	
$post['button_quickdelete'] = '
  <a href="#" 
     class="postbit_qdelete postbit_mirage dropdown-item" 
     data-commentid="' . $row['id'] . '" 
     data-torrentid="' . $row['torrentid'] . '" 
     data-bs-toggle="modal" data-bs-target="#deleteCommentModal">
     <i class="fa-solid fa-trash"></i>&nbsp;Delete
  </a>';
	
	
		

}

    $post['button_quote'] = '
		<a href="comment.php?action=add&tid='.$tid.'" class="dropdown-item"><i class="fa-solid fa-reply"></i> &nbsp;Reply</a>';
		
	
	$post['button_multiquote'] = $p_quote;
	  
	  

	  
	  $postcounter++;
	  $post_number = ts_nf($postcounter);
	  
	  
	  
	  
	  $torrent_name = isset($torrent['name']) ? htmlspecialchars_uni($torrent['name']) : '';
      eval("\$post['posturl'] = \"".$templates->get("comment_posturl")."\";");
	  
	  
	  
		
		
		
		
	  // Determine the status to show for the user (Online/Offline/Away)
		$timecut = TIMENOW - $wolcutoffmins;
		if($row['lastactive'] > $timecut && ($row['invisible'] != 1 || $moderator) && $row['lastvisit'] != $row['lastactive'])
		{
			
		    eval("\$post['onlinestatus'] = \"".$templates->get("postbit_online")."\";");
		
		
		}
		else
		{
			
			eval("\$post['onlinestatus'] = \"".$templates->get("postbit_offline")."\";");
					
		}
   
		
	 
		
	 
	  $post_visibility = '';
	  
	  
	  eval("\$post['commentstables'] = \"".$templates->get("commentstable")."\";");
	  

	  
	  
	 
	 
    $showcommentstable .= 
    ($postcounter == 1 ? '' : '<br />') . '
    ' . ($postcounter == 1 ? '
    </br>
    <div class="container-md">
      <div class="card border-0 mb-4">
        <div class="card-header rounded-bottom text-19 fw-bold d-flex justify-content-between align-items-center">
            ' . $torrent_name . '
            
            ' . ($moderator ? '
            <div>
                <!-- ЧЕКБОКС "ВЫДЕЛИТЬ ВСЕ" -->
                <div class="form-check form-check-inline me-2">
                    <input class="form-check-input" type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                    <label class="form-check-label small text-black" for="selectAllCheckbox">Select All</label>
                </div>
                <!-- КНОПКА МАССОВОГО УДАЛЕНИЯ -->
                <button id="massDeleteButton" class="btn btn-sm btn-outline-light d-none text-dark" onclick="massDeleteComments()">
                    <i class="fa-solid fa-trash"></i> Delete Selected
                </button>
            </div>
            ' : '') . '
            
        </div>
      </div>
    </div>
    ' : '') . '

    <div class="closest" id="comment-' . $row['id'] . '">
        ' . $post['commentstables'] . '
    </div>';




    }
	
	
	

    $showcommentstable .= '<div style="display: block;" id="ajax_comment_preview"></div><div style="display: block;" id="ajax_comment_preview2"></div>';
    

	if ($return) 
	{
        //echo '<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 2000;"></div>';
        return $showcommentstable;
    }
	

    echo $showcommentstable;

  }

  if (!defined ('IN_TRACKER'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

 
?>
