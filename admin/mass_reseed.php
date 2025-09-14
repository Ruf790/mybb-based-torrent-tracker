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

  define("IN_MYBB", 1);
  define ('MR_VERSION', 'v0.4 by xam');
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  
  $do = (isset ($_GET['do']) ? htmlspecialchars_uni ($_GET['do']) : (isset ($_POST['do']) ? htmlspecialchars_uni ($_POST['do']) : ''));
  if ($do == 'request_reseed_final')
  {
    $requestfrom = $_POST['requestfrom'];
    $sender = intval ($_POST['sender']);
    $postedtorrents = $_POST['torrents'];
    if (!empty ($postedtorrents))
    {
      $subject = trim ($_POST['subject']);
      $message = trim ($_POST['message']);
      if ((!empty ($subject) AND !empty ($message)))
      {
        if ($requestfrom == 'owner')
        {
          $query = $db->sql_query ('' . 'SELECT t.owner, t.name, t.id, u.username 
		  FROM torrents t 
		  INNER JOIN users u ON (t.owner=u.id) 
		  WHERE t.id IN (' . $postedtorrents . ') AND ts_external != \'yes\' AND seeders = 0');
        }
        else
        {
          $query = $db->sql_query ('' . 'SELECT s.userid as owner, s.torrentid as id, t.name, u.username 
		  FROM snatched s 
		  INNER JOIN torrents t ON (s.torrentid=t.id) 
		  INNER JOIN users u ON (s.userid = u.id) 
		  WHERE s.finished = \'yes\' AND s.torrentid IN (' . $postedtorrents . ')');
        }

        require_once INC_PATH . '/functions_pm.php';
        while ($torrent = $db->fetch_array($query))
        {
          $torrenturl = '[url=' . $BASEURL . '/'.get_torrent_link($torrent['id']) . ']' . $torrent['name'] . '[/url]';
          $msg = str_replace (array ('{username}', '{torrentname}'), array ($torrent['username'], $torrenturl), $message);
            
		  
		  $pm = array(
				'subject' => $db->escape_string($subject),
				'message' => $db->escape_string($msg),
				'touid' => $torrent['owner']
		  );

		  send_pm($pm, $sender, true);
		  
 
        }

        if ($_POST['doubleupload'] == 'yes')
        {
          $db->sql_query ('' . 'UPDATE torrents set doubleupload = \'yes\' WHERE id IN (' . $postedtorrents . ')');
        }
      }
    }
  }

  if ($do == 'request_reseed')
  {
    $torrents = $_POST['torrents'];
    $implode = @implode (',', $torrents);
    if (0 < count ($torrents))
    {
      require 'include/staff_languages.php';
      stdhead ('Request Reseed for Weak Torrents - Request Message');
      echo '
		<script type="text/javascript">
			function TSdoubleupload()
			{
				whatselected = document.forms[\'reseed\'].elements[\'doubleupload\'].value;
				TSnewinput = "\\nPlease Note: Once you start to Re-seed this torrent, you will get Double Upload Credits!";
				if (whatselected == "yes")
				{					
					document.forms[\'reseed\'].elements[\'message\'].focus();
					document.forms[\'reseed\'].elements[\'message\'].value =					
					document.forms[\'reseed\'].elements[\'message\'].value + TSnewinput;
					document.forms[\'reseed\'].elements[\'message\'].focus();
				}
				else
				{
					var str = document.forms[\'reseed\'].elements[\'message\'].value;
					var TSnewtext = str.replace(TSnewinput, "");
					document.forms[\'reseed\'].elements[\'message\'].value = TSnewtext;	
				}
			}
		</script>
		<form method="post" action="' . $_this_script_ . '" name="reseed">
		<input type="hidden" name="do" value="request_reseed_final">
		<input type="hidden" name="torrents" value="' . $implode . '">
		';
      
	  
	  
	  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Request Reseed for Weak Torrents - Request Message
	</div>
	 </div>
		</div>';
		
	  
      echo '
		
		<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">

            

		
		<tr>
			<td>Subject</td><td><input type="text" class="form-control" value="' . $mass_reseed['message']['subject'] . '" name="subject"></td></tr>
		</tr>
		<tr>
			<td>Message</td><td><textarea name="message" class="form-control form-control-sm border" cols="70" rows="15">' . $mass_reseed['message']['body'] . '</textarea></td>
		</tr>
		<tr>
			<td>Double Upload</td><td><select class="form-select form-select-sm border w-auto pe-5" name="doubleupload" onchange="javascript:TSdoubleupload()"><option value="yes">YES</option><option value="no" selected="selected">NO</option></select> Give Double Uploaded amount users who begin to reseed this torrent!</td>
		</tr>
		<tr>
			<td>Sender</td><td><select class="form-select form-select-sm border w-auto pe-5" name="sender"><option value="0">System</option><option value="' . $CURUSER['id'] . '">' . $CURUSER['username'] . '</option></select> <b>Please Note: </b>Do not change {username} and {torrentname} tags which will be automaticly renamed by system.</td>
		</tr>
		<tr>
			<td>Request from</td><td><select class="form-select form-select-sm border w-auto pe-5" name="requestfrom"><option value="owner">Uploader Only</option><option value="all">All snatched users</option></select></td>
		</tr>
		<tr>
			<td colspan="2" align="center"><input type="submit" class="btn btn-primary" value="Request Reseed"> <input type="reset" class="btn btn-primary" value="Reset Form"></td></tr>
		</tr>
		
	</table></div></div>';	
		


		
		
		
      echo '</form>';
      
      stdfoot ();
      exit ();
    }
  }

  $res = $db->sql_query ('SELECT id FROM torrents WHERE ts_external != \'yes\' AND seeders = 0');
  $row = mysqli_fetch_array ($res);
  $count = $row[0];
  
  stdhead ('Request Reseed for Weak Torrents - Show Torrents');
  echo '
	<form method="post" action="' . $_this_script_ . '" name="reseed">
	<input type="hidden" name="do" value="request_reseed">
	';
 
  
  
  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Request Reseed for Weak Torrents - Show Torrents
	</div>
	 </div>
		</div>';
  
  echo '
	
	
	
	
	
	
	<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Name</th>
        <th>Added</th>
        <th>Owner</th>
		<th>Seeders</th>
        <th>Leechers</th>
		<th>Snatched</th>
		<td class="subheader" align="center" width="5%">
		
		<input name="allbox" type="checkbox" class="form-check-input checkall" value="1">
		
		</td>
      </tr>
    </thead>
	
	
	
	
	
	
	
	

';





$query = $db->sql_query('SELECT t.id, t.name, t.seeders, t.leechers, t.times_completed, t.added, t.owner, u.username, u.usergroup
FROM torrents t 
LEFT JOIN users u ON (t.owner=u.id) 
WHERE t.ts_external != \'yes\' AND t.seeders = 0 ORDER BY t.added DESC ' . $limit);

// Инициализируем $postedtorrents как массив
$postedtorrents = !empty($postedtorrents) && is_string($postedtorrents) ? explode(',', $postedtorrents) : [];

  if (0 < $db->num_rows ($query))
  {
    while ($torrent = $db->fetch_array ($query))
    {
      echo '
		
		<tr>
			<td align="left"><a href="' . $BASEURL . '/'.get_torrent_link($torrent['id']).'">' . $torrent['name'] . '</a>
			<a href="' . $BASEURL . '/upload.php?id=' . $torrent['id'] . '">
			<i class="fa-solid fa-pen-to-square fa-lg" style="color: #0658e5;" alt="Edit Torrent" title="Edit Torrent"></i></a>


			<a href="' . $BASEURL . '/admin/index.php?act=fastdelete&id=' . $torrent['id'] . '">
			
			
			<i class="fa-solid fa-trash-can fa-lg" style="color: #eb0f0f;" alt="Delete Torrent" title="Delete Torrent"></i>
			
			</a>' . (@in_array ($torrent['id'], $postedtorrents, true) ? '<br />
			 <span class="badge bg-danger">Re-seed request sent!</span>' : '') . '</td>
			<td align="center">' . my_datee ($dateformat, $torrent['added']) . ' ' . my_datee ($timeformat, $torrent['added']) . '</td>
			<td align="center"><a href="' . $BASEURL . '/'.get_profile_link($torrent['owner']) . '">' . format_name($torrent['username'], $torrent['usergroup']) . '</a></td>
			<td align="center">' . ts_nf ($torrent['seeders']) . '</td>
			<td align="center">' . ts_nf ($torrent['leechers']) . '</td>
			<td align="center"><a href="' . $BASEURL . '/viewsnatches.php?id=' . $torrent['id'] . '">' . ts_nf ($torrent['times_completed']) . '</a></td>
			<td align="center"><input class="form-check-input" type="checkbox" checkme="group" name="torrents[]" value="' . $torrent['id'] . '"></td>
		</tr>
		
		';
    }

    echo '<tr><td colspan="7" align="right"><input type="submit" class="btn btn-primary" value="Request Re-seed for selected torrents"></td></tr>
	
	
	</table>
		</div>
         </div>
	
	';
  }
  else
  {
    echo '
	
	
   
  
	<tr><td colspan="7">There is no weak torrent found!</td></tr>
	
	
	
	';
  }

  echo '</form>
' . $pagerbottom;
  
  
  stdfoot ();
?>
