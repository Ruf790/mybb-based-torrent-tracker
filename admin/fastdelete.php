<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('FD_VERSION', '1.0 by xam');
  define("IN_MYBB", 1);
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  
  include_once INC_PATH . '/readconfig.php';
  
  
  
  if ((is_mod($usergroups) OR ($usergroups['candeletetorrent'] == '1' AND $CURUSER['id'] == $row['owner'])))
  {
    $lang->load('delete');

    // Safely get 'id' from POST or GET
    $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    $reason = isset($_POST['reason']) ? $_POST['reason'] : (isset($_GET['reason']) ? $_GET['reason'] : '');
    int_check($id, true); // Optional: depends on how strict int_check is defined

    $res = $db->simple_select("torrents", "name,owner", "id = '{$id}'");
    $row = $db->fetch_array($res);

    if (!$row) 
	{
        stderr($lang->global['error'], $lang->global['notorrentid']);
    }

    if (empty($reason) || strlen($reason) < 3) 
	{
        stdhead('Fast Delete');
        echo '
        <div class="container mt-5">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white fw-bold">Fast Delete</div>
                <div class="card-body">
                    <form method="post" action="' . $_this_script_ . '&id=' . $id . '">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                        <div class="mb-3">
                            <label for="reason" class="form-label">Please enter delete reason:</label>
                            <input type="text" class="form-control" id="reason" name="reason" value="' . htmlspecialchars_uni($reason) . '" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Fast Delete</button>
                    </form>
                </div>
            </div>
        </div>';
        stdfoot();
        exit();
    }

    require_once INC_PATH . '/functions_deletetorrent.php';
    deletetorrent($id);

    $logMessage = 'Fast Deleted! Reason: ' . htmlspecialchars_uni($reason);

    if ($CURUSER['anonymous'] == 'yes' && is_mod($usergroups)) 
	{
        write_log(sprintf($lang->delete['logmsg1'], $id, $row['name'], $logMessage));
    } 
	else 
	{
        write_log(sprintf($lang->delete['logmsg2'], $id, $row['name'], $CURUSER['username'], $logMessage));
    }

    if ($row['owner'] != $CURUSER['id'])
    {
      require_once INC_PATH . '/functions_pm.php';
      
	  $pm = array(
			'subject' => $lang->delete['deleted'],
			'message' => sprintf ($lang->delete['logmsg2'], $id, $row['name'], $CURUSER['username'], $reason),
			'touid' => $row['owner']
	  );
			
	  
	  $pm['sender']['uid'] = -1;
	  send_pm($pm, -1, true);
	   
    }

    kps('-', $kpsupload, $row['owner']);
    $cache->update_torrents();

    redirect($BASEURL . '/browse.php', $lang->delete['deleted'], '', 3, false, false);
    exit();
  }

  

  
  
?>
