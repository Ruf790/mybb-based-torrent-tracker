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

  define ('W_VERSION', '0.8 by xam');
  include_once INC_PATH . '/functions_ratio.php';
  
 

  require_once INC_PATH . '/functions_multipage.php';
  
  
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'showlist'));
  if ($action == 'remove')
  {
    $db->sql_query ('UPDATE users SET warned = \'no\', leechwarn = \'no\', warneduntil = \'0\', leechwarnuntil = \'0\' WHERE id IN (' . implode (', ', $_POST['userid']) . ')');
    $action = 'showlist';
  }

  if ($action == 'showlist')
  {
    stdhead ('Warned Users');
    $countrows = number_format (tsrowcount ('id', 'users', 'enabled = \'yes\' AND usergroup != \'' . UC_BANNED . '\' AND (warned = \'yes\' OR leechwarn = \'yes\')'));
    //$page = 0 + $_GET['page'];
    //$perpage = $ts_perpage;
    //list ($pagertop, $pagerbottom, $limit) = pager ($perpage, $countrows, $_this_script_ . '&action=showlist&', '', false);
	
	
	
	  $threadcount = $countrows;
  
    // How many pages are there?
if(!$torrentsperpage || (int)$torrentsperpage < 1)
{
	$torrentsperpage = 20;
}

$perpage = $torrentsperpage;

if($mybb->input['page'] > 0)
{
	$page = $mybb->input['page'];
	$start = ($page-1) * $perpage;
	$pages = $threadcount/ $perpage;
	$pages = ceil($pages);
	if($page > $pages || $page <= 0)
	{
		$start = 0;
		$page = 1;
	}
}
else
{
	$start = 0;
	$page = 1;
}

$end = $start + $perpage;
$lower = $start + 1;
$upper = $end;

if($upper > $threadcount)
{
	$upper = $threadcount;
}


//$page_url = str_replace("{fid}", $fid,  $_this_script_ . '&action=showlist&', '', false);
$multipage = multipage($threadcount, $perpage, $page, $_this_script_ . '&action=showlist&', '', false);
	
	
	
	echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Warned Users
	</div>
	 </div>
		</div>';
		
		
	 echo '
   
   <div class="container mt-3">
	'.$multipage.'
</div>';	
		
	
	
	
	
    echo '
	<script language=\'JavaScript\'>
	checked = false;
	function checkedAll ()
	{
		if (checked == false){checked = true}else{checked = false}
		for (var i = 0; i < document.getElementById(\'warned\').elements.length; i++)
		{
			document.getElementById(\'warned\').elements[i].checked = checked;
		}
	}
    </script>
	<form method="post" action="' . $_this_script_ . '" name="update">	
	<input type="hidden" name="action" value="remove">
	
	<div class="container mt-3">
    <div class="card">
            
    <table class="table table-hover">
    <thead>
      <tr>
        <th>User</th>
        <th>Registered</th>
        <th>Last Access</th>
		<th>DL</th>
        <th>UL</th>
        <th>Ratio</th>
		<th>Until</th>
		<th>Type</th>
		<td><input class="form-check-input" type="checkbox" checkall="group" onclick="javascript: return select_deselectAll (\'update\', this, \'group\');"></td>
      </tr>
    </thead>';
 
    $query = $db->sql_query ('SELECT u.*, p.canupload, p.candownload, p.cancomment 
	FROM users u 
	LEFT JOIN ts_u_perm p ON (u.id=p.userid) 
	WHERE u.usergroup != \'' . UC_BANNED . '\' AND u.enabled = \'yes\' AND (u.warned = \'yes\' OR u.leechwarn = \'yes\') LIMIT '.$start.', ' . $perpage . '');
 
    if ($db->num_rows ($query) == 0)
    {
      echo '<tr><td colspan="10">No User Found...</tr></td>';
    }
    else
    {
      require_once INC_PATH . '/functions_mkprettytime.php';
      while ($res = $db->fetch_array ($query))
      {
        $icons = get_user_icons ($res);
        $user = '<a href=' . $BASEURL . '/'.get_profile_link($res['id']) . '>' . format_name($res['username'], $res['usergroup']) . '</a>' . $icons;
        $registered = my_datee ($dateformat, $res['added']) . '';
		
		
        $lastaccess = my_datee ($dateformat, $res['lastactive']) . '<br />' . my_datee ($timeformat, $res['lastactive']);
        $downloaded = mksize ($res['downloaded']);
        $uploaded = mksize ($res['uploaded']);
        $ratio = ($res['downloaded'] != 0 ? number_format ($res['uploaded'] / $res['downloaded'], 3) : '---');
        $ratio = '<font color=' . get_ratio_color ($ratio) . '>' . $ratio . '</font>';
        $warneduntil = ($res['warneduntil'] != '0000-00-00 00:00:00' ? $res['warneduntil'] : $res['leechwarnuntil']);
        if ($warneduntil == '0000-00-00 00:00:00')
        {
          $warneduntil = '<font color=red>(Arbitrary duration)</font>';
        }
        else
        {
          //$warneduntil2 = my_datee($dateformat, $res['warneduntil']);
		  //$warneduntil2 = my_datee($dateformat, $res['leechwarnuntil']);
		  //$warneduntil = $warneduntil2 . '<br />(' . mkprettytime($res['warneduntil'] - TIMENOW) . ' to go)';
		  $warneduntil222 = my_datee($dateformat, $res['warneduntil']);
		  $warneduntil = $warneduntil222 . '<br />(' . mkprettytime($res['warneduntil'] - TIMENOW) . ' to go)';
		  
		  
        }

        $warntype = ($res['warned'] == 'yes' ? 'Normal' : ($res['leechwarn'] == 'yes' ? '<span class="badge bg-danger"><strong>Leeh-Warn</strong></span>' : 'Unknown'));
        $remove = '<input class="form-check-input" type="checkbox" name="userid[]" value="' . $res['id'] . '" checkme="group">';
        echo '<tr>
			<td>' . $user . '</td>
			<td>' . $registered . '</td>
			<td>' . $lastaccess . '</td>			
			<td>' . $downloaded . '</td>
			<td>' . $uploaded . '</td>
			<td>' . $ratio . '</td>
			<td>' . $warneduntil .'</td>
			<td align="center">' . $warntype . '</td>
			<td align="center">' . $remove . '</td>
			</tr>';
      }

      echo '<tr><td colspan="9" align="right">' . $multipage . '<input type="submit" value="Remove Warning" class="btn btn-primary"></td></tr>';
    }

    echo '</form></table></div></div>';
    
    stdfoot ();
  }

?>
