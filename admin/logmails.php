<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

 
  

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
  
  
  
  


  require_once INC_PATH . '/functions_multipage.php';
  
  
  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  stdhead ('Site Mails Log');
  
  
  
  
  if (($_POST['clear'] == 'yes' AND $usergroups['cansettingspanel'] == '1'))
  {
    $db->sql_query ('TRUNCATE TABLE maillogs');
    echo '<font color=red><strong>Log table has been cleared!</strong></font>';
  }
  else
  {
    if ((($_POST['action'] == 'delete' AND !empty ($_POST['logid'])) AND $usergroups['cansettingspanel'] == '1'))
    {
      ($db->sql_query ('DELETE FROM maillogs WHERE mid IN (' . implode (', ', $_POST['logid']) . ')') );
      echo '
	  <div class="container mt-3">
          <div class="red_alert mb-3" role="alert">
          <strong>Total ' .$db->affected_rows () . ' log(s) has been deleted!</strong>
        </div>
        </div>';
    }
  }

  print '<table class="table">';
  print '<tr><td class=tableb align=center><form method="post" action=' . $_this_script_no_act . '?act=searchlog>';
  print '<h2>Search User Email Log:</h2> <label><input type="text" class="form-control form-control-sm border" name="query" size="40" value="' . htmlspecialchars ($searchstr) . '"></label>';
  print '<input type=submit value=search class="btn btn-primary" /><br /><br /></form>';
  print '<form method=post action=' . $_this_script_ . '><input type=hidden name=clear value=yes><input type=submit value=\'clear logs\' class="btn btn-primary"></form>';
  print '</td></tr></table>';





  $res = $db->sql_query ('SELECT COUNT(*) FROM maillogs');
  $row = mysqli_fetch_row ($res);
  $count = $row[0];
  
  $threadcount = $count;
  
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


$page_url = str_replace("{fid}", $fid,  $_this_script_ . '');
$multipage = multipage($threadcount, $perpage, $page, $page_url);
  
  
  ($res = $db->sql_query ('' . 'SELECT mid, dateline, message, fromemail, toemail FROM maillogs ORDER BY dateline DESC LIMIT '.$start.', ' . $perpage . ''));
  if ($db->num_rows ($res) == 0)
  {
    print '<b>Log is empty</b>
';
  }
  else
  {
    
	 echo '<div class="container mt-3">
	'.$multipage.'
     </div>';
	
	echo '<form method=post action="' . $_this_script_ . '"><input type=hidden name=action value=delete>';
    print '<div class="container mt-3">
	
	         <div class="card">
          <table class="table table-hover">
';
    
	
	print '
	<thead>
      <tr>
        <th>Date</th>
        <th>Message</th>
		<th>Delete</th>
      </tr>
    </thead>
';
    while ($arr = $db->fetch_array ($res))
    {
      $color = 'black';
      

	  
	  $date =  my_datee('relative', $arr['dateline']);
	  
	  
      print '' . '
	  
	  <tbody>
      <tr>
        <td>' . $date . '</td>
		
       
        <td>
		<b>From</b>
		' . $arr['fromemail'] . '
		</br>
		<b>To</b>
		' . $arr['toemail'] . '
		</br>
		
		<font color=\'' . $color . '\'><b>'.$parser->parse_message($arr['message'],$parser_options).'</b></font></td>
		<td><input type=checkbox class="form-check-input" name=logid[] value=\'' . (int)$arr['mid'] . '\'></td>
      </tr>
    </tbody>
	  
';
    }

    echo '<tr><td colspan=4 align=right><input type=submit value="delete selected" class="btn btn-primary"> <INPUT type="button" value="check all" onClick="this.value=check(form)" class="btn btn-primary"></td></tr>';
	
	
	
	
	
    print ' </table></div></div>';
  }
  
  
  

  echo '<div class="container mt-3">
	     '.$multipage.'
       </div>';
  
  
  //print '<p>Times are in GMT.</p>';
  
  
  stdfoot ();
?>
