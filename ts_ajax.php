<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

  function show_msg ($message = '', $error = true, $color = 'red', $strong = true, $extra = '', $extra2 = '')
  {
    global $shoutboxcharset;
    header ('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header ('Last-Modified: ' . gmdate ('D, d M Y H:i:s') . 'GMT');
    header ('Cache-Control: no-cache, must-revalidate');
    header ('Pragma: no-cache');
    header ('' . 'Content-type: text/html; charset=' . $shoutboxcharset);
    if ($error)
    {
      exit ('<error>' . $message . '</error>');
    }

    exit ($extra . (!empty ($color) ? '<font color="' . $color . '">' : '') . ($strong ? '<strong>' : '') . $message . ($strong ? '</strong>' : '') . (!empty ($color) ? '</font>' : '') . $extra2);
  }


  function allowcomments($torrentid = 0)
  {
    global $is_mod, $db;
    
	$query = $db->simple_select('torrents', 'allowcomments', "id = '{$torrentid}'");
	
    if (!$db->num_rows($query)) 
	{
        return false;
    }
    $Result = $db->fetch_array($query);
    $allowcomments = $Result["allowcomments"];
    if ($allowcomments != "yes" && !$is_mod) 
	{
        return false;
    }
    return true;
  }
  
  

  define ('SKIP_LOCATION_SAVE', true);
  define ('DEBUGMODE', false);
  define("IN_ARCHIVE", true);
  define("IN_MYBB", 1);
  require_once 'global.php';
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  gzip ();
  

  define ('TS_AJAX_VERSION', '1.2.2 ');

  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ();
  }

  if (((strtoupper ($_SERVER['REQUEST_METHOD']) != 'POST' AND $_GET['action'] != 'quick_edit') AND $_GET['action'] != 'autocomplete'))
  {
    exit ();
  }

  $is_mod = is_mod ($usergroups);
  




    if ((((isset ($_POST['ajax_quick_comment']) AND isset ($_POST['id'])) AND isset ($_POST['text'])) AND $CURUSER))
    {
       
	  $query = $db->simple_select("ts_u_perm", "cancomment", "userid='".$CURUSER['id']."'");
	  
      if (0 < $db->num_rows ($query))
      {
        $commentperm = $db->fetch_array ($query);
        if ($commentperm['cancomment'] == '0')
        {
          show_msg ($lang->global['nopermission']);
        }
      }

      $torrentid = intval ($_POST['id']);
      $lang->load ('comment');
      if (allowcomments ($torrentid) == false)
      {
        show_msg ($lang->comment['closed']);
      }

      $text = urldecode ($_POST['text']);
      $text = strval ($text);
      if (strtolower ($shoutboxcharset) != 'utf-8')
      {
        if (function_exists ('iconv'))
        {
          $text = iconv ('UTF-8', $shoutboxcharset, $text);
        }
        else
        {
          if (function_exists ('mb_convert_encoding'))
          {
            $text = mb_convert_encoding ($text, $shoutboxcharset, 'UTF-8');
          }
          else
          {
            if (strtolower ($shoutboxcharset) == 'iso-8859-1')
            {
              $text = utf8_decode ($text);
            }
          }
        }
      }

      
	  $query = $db->simple_select("comments", "dateline", "user = '{$CURUSER['id']}'", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1));
	  
      if (0 < $db->num_rows ($query))
      {
       
		 $Result = $db->fetch_array($query);
         $last_comment = $Result['dateline'];
      }

      $floodmsg = flood_check ($lang->comment['floodcomment'], $last_comment, true);
      
	  $res = $db->simple_select("torrents", "name, owner", "id='".$torrentid."'");
	  
      $arr = $db->fetch_array($res);
      if (!empty ($floodmsg))
      {
        show_msg (str_replace (array ('<font color="#9f040b" size="2">', '</font>', '<b>', '</b>'), '', $floodmsg));
      }
      else
      {
        if (!$arr)
        {
          show_msg ($lang->global['notorrentid']);
        }
        else
        {
          if (((empty ($text) OR empty ($torrentid)) OR !is_valid_id ($torrentid)))
          {
            show_msg ($lang->global['dontleavefieldsblank']);
          }
        }
      }

      $commentposted = false;
      if (!$is_mod)
      {
        
	    $query = $db->simple_select("comments", "id, user, text", "torrent='{$torrentid}'", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => 1));
		
        if (0 < $db->num_rows ($query))
        {
         
		  $last_post55 = $db->fetch_array($query);
		  $lastcommentuserid = $last_post55['user'];
		  
          if ($lastcommentuserid == $CURUSER['id'])
          {
            $oldtext = $last_post55['text'];
            $newid = $last_post55['id'];
            

			$newtext = $oldtext .="\n[hr]\n".$_POST['text'];
			
			$update_comments = array(
			    "text" => $db->escape_string($newtext)
		    );
		    $update_comments['editedat'] = TIMENOW;
		    $update_comments['editedby'] = $db->escape_string($CURUSER['id']);
					
		    $db->update_query("comments", $update_comments, "id='{$newid}'");
			
			
            if ($db->affected_rows ())
            {
              $commentposted = true;
            }
          }
        }
      }

      if (!$commentposted)
      {
       
		// Insert the comment.
		$comment_insert_data = array(
			"user" => $db->escape_string($CURUSER['id']),
			"torrent" => $db->escape_string($torrentid),
			"dateline" => TIMENOW,
			"text" => $db->escape_string($text)
		
		);

		$db->insert_query("comments", $comment_insert_data);
		
        $cid = $db->insert_id ();
      
		
		$update_array['comments'] = 'comments+1';
		$db->update_query("torrents", $update_array, "id='{$torrentid}'", 1, true);
		
		$update_comms['comms'] = 'comms+1';
		$db->update_query("users", $update_comms, "id='{$CURUSER['id']}'", 1, true);
		
		
		$ras = $db->sql_query ('SELECT commentpm FROM users WHERE id = ' . $db->escape_string ($arr['owner']));
		$arg = $db->fetch_array ($ras);
		  
		if (($arg['commentpm'] == 1 && $CURUSER['id'] != $arr['owner']))
        {
            require_once INC_PATH . '/functions_pm.php';
            $url2 = get_comment_link($cid, $torrentid)."#pid{$cid}";
					
			$pm = array(
				'subject' => sprintf ($lang->comment['newcommentsub']),
				'message' => sprintf ($lang->comment['newcommenttxt'], '[url=' . $BASEURL.'/'.$url2.']' . $arr['name'] . '[/url]'),
				'touid' => $arr['owner']
			);
			
			$pm['sender']['uid'] = -1;
			send_pm($pm, -1, true);
		  
		  
		}
		
      }


	  require_once INC_PATH . '/commenttable.php';
      //require_once INC_PATH . '/functions_quick_editor.php';
      $subres = $db->sql_query("SELECT c.id, c.torrent as torrentid, c.text, c.user, c.dateline, c.editedby, c.editedat,
	  c.totalvotes, uu.username as editedbyuname, 
	  gg.namestyle as editbynamestyle, u.postnum, u.threadnum, u.added, u.comms, u.enabled, u.warned, u.leechwarn, 
	  u.username, u.usertitle, u.usergroup, u.options, u.donor, u.uploaded, 
	  u.downloaded, u.avatar as useravatar, u.avatardimensions, u.signature, g.title as grouptitle, g.namestyle 
	  FROM comments c 
	  LEFT JOIN users uu ON (c.editedby=uu.id) 
	  LEFT JOIN usergroups gg ON (uu.usergroup=gg.gid) 
	  LEFT JOIN users u ON (c.user=u.id) 
	  LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
	  WHERE c.id ='$cid' ORDER BY c.id");
				
      $allrows = array();
      while ($subrow = $db->fetch_array($subres)) 
	  {
           $allrows[] = $subrow;
      }
      $lcid = 0;
      if (isset($_POST["lcid"])) 
	  {
          $lcid = intval($_POST["lcid"]);
      }
      define("LCID", $lcid);
      
	  $showcommenttable = commenttable($allrows, "", "", false, true, true);
      show_msg($showcommenttable, false, "", false);
					
      //return 1;
    }

    


?>
