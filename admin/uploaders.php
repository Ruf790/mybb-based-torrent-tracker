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

  define ('U_VERSION', '0.4 by xam');
  include_once INC_PATH . '/functions_ratio.php';
  $combine = false;
  if ((isset ($_GET['uploader']) AND is_valid_id ($_GET['uploader'])))
  {
    $uploader = intval ($_GET['uploader']);
    $combine = true;
  }

  $user = array ();
  $query = $db->sql_query ('SELECT id, name, added, owner, seeders, leechers FROM torrents');
  while ($uploads = mysqli_fetch_assoc ($query))
  {
    ++$user['totaltorrents'][$uploads['owner']];
    $user['lastupload'][$uploads['owner']] = ($combine ? $user['lastupload'][$uploads['owner']] : '') . '<a href="' . $BASEURL . '/' . get_torrent_link($uploads['id']) . '"><strong>' . $uploads['name'] . '</strong></a> on ' . my_datee ($dateformat, $uploads['added']) . ' ' . my_datee ($timeformat, $uploads['added']) . ' Seeders: ' . ts_nf ($uploads['seeders']) . ' Leechers: ' . ts_nf ($uploads['leechers']) . '<br />';
  }

  $what = ((isset ($_GET['type']) AND $_GET['type'] == 2) ? 'g.canupload = \'yes\'' : 'u.usergroup=' . UC_UPLOADER);
  if ($combine)
  {
    $what = 'u.id=' . $db->sqlesc ($_GET['uploader']);
  }

  include_once $rootpath . '/admin/include/global_config.php';
  $query = $db->sql_query ('' . 'SELECT u.id FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.enabled=\'yes\' AND ' . $what);
  $total_count = $db->num_rows ($query);
  //list ($pagertop, $pagerbottom, $limit) = pager ($config['uploaders']['query_limit'], $total_count, $_this_script_ . '&amp;' . ($_GET['type'] ? 'type=' . intval ($_GET['type']) . '&amp;' : '') . ($uploader ? 'uploader=' . $uploader . '&amp;' : ''));
  stdhead ($SITENAME . ' Uploader List');
  echo $pagertop;
 



echo ' <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$SITENAME . ' Uploader List (<a href="' . $_this_script_ . '&amp;type=1">Show usergroup = UC_UPLOADER</a> **** <a href="' . $_this_script_ . '&amp;type=2">Show canupload = yes only</a>
	</div>
	 </div>
		</div>';


  echo '

<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Uploader / Ratio</th>
        <th>Last Access</th>
        <th>Uploads</th>
		<th>Last Upload' . ($combine ? 's' : '') . '</th>
     
      </tr>
    </thead>';
	
	
	
  $uploaders = array ();
  $query = $db->sql_query ('' . 'SELECT u.username, u.usergroup, u.id, u.lastactive, u.lastvisit, u.uploaded, u.downloaded
  FROM users u 
  WHERE u.enabled=\'yes\' AND ' . $what . ' ' . $limit);
  while ($res = mysqli_fetch_assoc ($query))
  {
    $info = ($user['lastupload'][$res['id']] ? $user['lastupload'][$res['id']] : 'There is no uploaded torrent detected for this user!');
    
	
	
	$last_seen = max(array($res['lastactive'], $res['lastvisit']));
	if(empty($last_seen))
	{
	    $user['lastvisit'] = 'Never';
	}
	else
	{
		$user['lastvisit'] = my_datee('relative', $last_seen);
			
	}
		
	
	
	
	echo '
	<tr>
	<td align="left" valign="top"><a href="' . $BASEURL . '/' . get_profile_link($res['id']) . '">' . format_name($res['username'], $res['usergroup']) . '</a> (' . get_user_ratio ($res['uploaded'], $res['downloaded']) . ') (<a href="' . $_this_script_ . '&amp;uploader=' . $res['id'] . '">show all</a>)</td>
	<td align="center" valign="top">' . $user['lastvisit'] . '</td>
	<td align="center" valign="top">' . ts_nf ($user['totaltorrents'][$res['id']]) . '</td>
	<td align="left" valign="top">' . $info . '</td>
	</tr>
	';
  }

  
  echo '</table></div></div>';
  
  echo $pagerbottom;
  stdfoot ();
?>
