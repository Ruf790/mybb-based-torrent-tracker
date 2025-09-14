<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

  require_once INC_PATH . '/functions_html.php';
  
  require_once INC_PATH . '/functions_icons.php';
  
  function usertable ($res, $frame_caption)
  {
    global $CURUSER;
    global $BASEURL;
	global $db;
    //_form_header_open_ ($frame_caption);
	
	
	echo '
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$frame_caption.'
	</div>
	 </div>
		</div>';
  

    echo '

  <div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>User</th>
        <th>Uploaded</th>
		<th>Downloaded</th>
        <th>Ratio</th>
        <th>Joined</th>
      </tr>
    </thead>';
	

	
	
	
    $num = 0;
    while ($a = $db->fetch_array ($res))
    {
      ++$num;
      $highlight = ($CURUSER['id'] == $a['userid'] ? ' bgcolor=#BBAF9B' : '');
      if ($a['downloaded'])
      {
        $ratio = $a['uploaded'] / $a['downloaded'];
        $color = get_ratio_color ($ratio);
        $ratio = number_format ($ratio, 2);
        if ($color)
        {
          $ratio = '' . '<font color=' . $color . '>' . $ratio . '</font>';
        }
      }
      else
      {
        $ratio = 'Inf.';
      }

      print '' . '<tr' . $highlight . '><td align=left' . $highlight . '><a href=' . $BASEURL.'/'.get_profile_link($a['userid']) . '><b>' . format_name($a['username'], $a['usergroup']) . '</b>
	  ' . get_user_icons ($a) .'' . ('' . '</td><td align=right' . $highlight . '>') . mksize ($a['uploaded']) . ('' . '</td>
	  <td align=right' . $highlight . '>') . mksize ($a['downloaded']) . ('' . '</td>
	  <td align=right' . $highlight . '>') . $ratio . '</td>
	  <td align=left>' . my_datee('relative', $a['added']) . ' (' . mkprettytime (time () - $a['added']) . ')</td></tr>';
    }

    //end_table ();
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  include_once INC_PATH . '/functions_ratio.php';
  require_once INC_PATH . '/functions_mkprettytime.php';
  stdhead ('Ratio is 100 of above');
  $mainquery = 'SELECT u.id as userid, u.username, u.usergroup, u.donor, u.enabled, u.warned, u.leechwarn, 
  u.added, u.uploaded, u.downloaded, u.uploaded / (NOW() - u.added) AS upspeed, u.downloaded / (NOW() - u.added) AS downspeed, p.canupload, p.candownload, p.cancomment, 
  g.namestyle 
  FROM users u 
  LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
  LEFT JOIN ts_u_perm p ON (u.id=p.userid)
  WHERE u.enabled = \'yes\'';
  $limit = 250;
  $order = 'added ASC';
  $extrawhere = ' AND uploaded / downloaded > 100';
  ($r = $db->sql_query ($mainquery . $extrawhere . ('' . ' ORDER BY ' . $order . ' ') . ('' . ' LIMIT ' . $limit)));
  usertable ($r, 'Ratio Above 100');
  
  
  
  echo '</table></div></div>';
  
  stdfoot ();
?>
