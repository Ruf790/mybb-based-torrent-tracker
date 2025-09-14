<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function whereto ()
  {
    $msg = '<br /><div class=success align=center>Go back to <a href="' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints">Admin Panel</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=add">Add Bonus</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=showlist">Show User List</a> | <a href="' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=reset">Reset User Points (ALL)</a> |<a href="' . $_SERVER['HTTP_REFERRER'] . '"> Click here to return to where you were previously.</a></div>';
    return $msg;
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }
  
  
  require_once INC_PATH . '/functions_multipage.php';

  define ('BS_VERSION', 'v0.3 by xam');
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'adminpanel'));
  $allowed_actions = array ('showlist', 'edituser', 'updateuser', 'updatebonussystem', 'updatebonussystemsave', 'adminpanel', 'add', 'add_save', 'resetall', 'reset');
  if (!in_array ($action, $allowed_actions))
  {
    $action = 'adminpanel';
  }

  $countrows = number_format (tsrowcount ('id', 'users', 'seedbonus > 0')) + 1;
  

  $threadcount = $countrows;

// How many pages are there?
if(!$torrentsperpage || (int)$torrentsperpage < 1)
{
	$torrentsperpage = 20;
}

$perpage = $torrentsperpage;


//if($mybb->input['page'] > 0)
if(isset($mybb->input['page']) && intval($mybb->input['page']) > 0)
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


$page_url = $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=showlist&';
$multipage = multipage($threadcount, $perpage, $page, $page_url);
  
  
  
  
  
  
  
  
  
  
  
  
  if ($action == 'showlist')
  {
    stdhead ('Bonus Points ' . BS_VERSION . ' - SHOWLIST');
    
    echo '
	<div class="container mt-3">
	   '.$multipage.'
    </div>';
    
	
	echo '
	
	<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>User ID</th>
        <th>Username</th>
        <th>Bonus Point</th>
		<th>Bonus Comment</th>
        <th>Total Uploaded</th>
		<th>Update</th>
      </tr>
    </thead>
	
	
	
	';
    ($res = $db->sql_query ('' . 'SELECT id, username, seedbonus, bonuscomment, uploaded FROM users WHERE seedbonus > 0 ORDER by seedbonus DESC LIMIT '.$start.', ' . $perpage . ''));
    while ($arr = mysqli_fetch_array ($res))
    {
      echo '<tr><td align=center><a href=' . $BASEURL . '/'.get_profile_link($arr['id']) . '>' . $arr['id'] . '</a></td>
	  <td align=center><a href=' . $BASEURL . '/'.get_profile_link($arr['id']) . '>' . $arr['username'] . '</a></td>
	  <td align=center>' . $arr['seedbonus'] . ' points</td><td><textarea cols=35 rows=5 class="form-control form-control-sm border">' . $arr['bonuscomment'] . '</textarea></td>
	  <td>' . mksize ($arr['uploaded']) . '</td><td><a href="' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=edituser&id=' . $arr['id'] . '">
	  <span class="badge bg-primary">edit user</span></a></td></tr>';
    }

    echo '<tr><td colspan=6>' . $multipage . '</td></tr>';
    echo '<tr><td colspan=6>' . whereto () . '</td></tr>';
  }
  else
  {
    if ($action == 'edituser')
    {
      $id = (isset ($_POST['id']) ? 0 + $_POST['id'] : (isset ($_GET['id']) ? 0 + $_GET['id'] : ''));
      if (!is_valid_id ($id))
      {
        stderr ('Error', 'Invalid ID');
      }

      $res = $db->sql_query ('SELECT id,username,seedbonus FROM users WHERE id = ' . $db->sqlesc ($id));
      if ($db->num_rows ($res) == '0')
      {
        stderr ('Error', 'Nothing Found!');
      }

      stdhead ('Bonus Points ' . BS_VERSION . ('' . ' - UPDATE USER (' . $id . ')'));
      
      echo '<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
	<input type=hidden name=act value=bonuspoints>
	<input type=hidden name=action value=updateuser>';
      echo '
	  
	  
	  <div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>User ID</th>
        <th>User Name</th>
        <th>User Points</th>
		<th>Update</th>
  
      </tr>
    </thead>
	  
	  
	  
	  
	  
	  
	  ';
      while ($arr = mysqli_fetch_array ($res))
      {
        echo '<tr><td align=center valign=top>
		<input type=hidden name=id value="' . $arr['id'] . '"><a href=' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '>' . $arr['id'] . '</a></td>
		<td align=center valign=top><a href=' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '>' . $arr['username'] . '</a></td>
		<td align=left valign=top><input class="form-control" type=text name=seedbonus value="' . $arr['seedbonus'] . '"></td>
		<td align=center valign=top><input type=submit name=edit value="update points" class="btn btn-primary"></td></tr>';
      }

      echo '</form>';
    }
    else
    {
      if ($action == 'updateuser')
      {
        $id = (isset ($_POST['id']) ? 0 + $_POST['id'] : (isset ($_GET['id']) ? 0 + $_GET['id'] : ''));
        if (!is_valid_id ($id))
        {
          stderr ('Error', 'Invalid ID');
        }

        $seedbonus = $db->sqlesc ($_POST['seedbonus']);
        ($db->sql_query ('' . 'UPDATE users SET seedbonus = ' . $seedbonus . ' WHERE id = ' . $db->sqlesc ($id)) OR stderr ('Update User', '' . 'Unable to update: ' . $id));
        stderr ('Update User', '' . 'User Id: ' . $id . ' successfull updated.' . whereto (), false);
      }
      else
      {
        if ($action == 'updatebonussystem')
        {
          $id = (isset ($_POST['id']) ? 0 + $_POST['id'] : (isset ($_GET['id']) ? 0 + $_GET['id'] : ''));
          if (!is_valid_id ($id))
          {
            stderr ('Error', 'Invalid ID');
          }

          $res = $db->sql_query ('SELECT * FROM bonus WHERE id = ' . $db->sqlesc ($id));
          if ($db->num_rows ($res) == '0')
          {
            stderr ('Error', 'Nothing Found!');
          }

          stdhead ('Bonus Points ' . BS_VERSION . ('' . ' - UPDATE (' . $id . ')'));
         
		  echo '
		  
		  <div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Points</th>
		<th>Description</th>
        <th>Delete?</th>
        <th>Menge</th>
		<th>Update</th>
      </tr>
    </thead>';
		  
		  
		  
		 
          echo '<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
	<input type=hidden name=act value=bonuspoints>
	<input type=hidden name=action value=updatebonussystemsave>';
          while ($arr = mysqli_fetch_array ($res))
          {
            echo '<tr>
			<td align=center valign=top>
			<input type=hidden name=id value="' . $arr['id'] . '">' . $arr['id'] . '</td>
			<td align=left valign=top>
			<input type=text name=bonusname value="' . $arr['bonusname'] . '" class="form-control"></td>
			<td align=center valign=top>
			<input type=text name=points value="' . $arr['points'] . '" size=5 class="form-control"></td>
			<td align=left valign=top>
			<textarea name=description class="form-control form-control-sm border" cols=20 rows=10>' . $arr['description'] . '</textarea></td>
			<td align=center valign=top><input type=checkbox class="form-check-input" name=delete value=1></td>
			<td align=center valign=top>
			<input type=text name=menge class="form-control" value="' . $arr['menge'] . '"><br />1 GB = 1073741824<br />2.5GB = 2684354560<br />5GB = 5368709120<br />10GB = 10737418240<br />and so far...</td>
			<td align=center valign=top><input type=submit name=update value=update class="btn btn-primary"></td></tr>';
          }

          echo '</form>';
        }
        else
        {
          if ($action == 'updatebonussystemsave')
          {
            $id = (isset ($_POST['id']) ? 0 + $_POST['id'] : (isset ($_GET['id']) ? 0 + $_GET['id'] : ''));
            if (!is_valid_id ($id))
            {
              stderr ('Error', 'Invalid ID');
            }

            $bonusname = $db->sqlesc ($_POST['bonusname']);
            $points = $db->sqlesc ($_POST['points']);
            $description = $db->sqlesc ($_POST['description']);
            $sure = $_GET['sure'];
            $delete = (isset ($_POST['delete']) ? htmlspecialchars ($_POST['delete']) : (isset ($_GET['delete']) ? htmlspecialchars ($_GET['delete']) : ''));
            if ($delete)
            {
              if (!$sure)
              {
                stderr ('Delete bonus', 'Sanity check: You are about to delete a bonus. Click
' . '<a href=\'' . $_SERVER['SCRIPT_NAME'] . ('' . '?act=bonuspoints&action=updatebonussystemsave&id=' . $id . '&sure=1&delete=1\'>here</a> if you are sure.'), false);
              }
              else
              {
                ($db->sql_query ('DELETE FROM bonus WHERE id = ' . $db->sqlesc ($id)) OR stderr ('Delete bonus', '' . 'Unable to delete: ' . $id));
                stderr ('Delete bonus', '' . 'Bonus Id: ' . $id . ' successfull deleted.' . whereto (), false);
              }
            }
            else
            {
              ($db->sql_query ('' . 'UPDATE bonus SET bonusname = ' . $bonusname . ', points = ' . $points . ', description = ' . $description . ' WHERE id = ' . $db->sqlesc ($id)) OR stderr ('Update bonus', '' . 'Unable to update: ' . $id));
              stderr ('Update bonus', '' . 'Bonus Id: ' . $id . ' successfull updated.' . whereto (), false);
            }
          }
          else
          {
            if ($action == 'adminpanel')
            {
              stdhead ('Bonus Points ' . BS_VERSION . ' - ADMIN PANEL');
             
             echo '
			        <div class="container mt-3">
                    <div class="card">
            <table class="table table-hover">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Points</th>
		<th>Description</th>
		<th>Update</th>
      </tr>
    </thead>';
			  
			  
			  
			  
              $res = $db->sql_query ('SELECT * FROM bonus ORDER BY id ASC');
              while ($arr = mysqli_fetch_array ($res))
              {
                echo '<tr><td align=center>' . $arr['id'] . '</td><td align=left>' . $arr['bonusname'] . '</td>
				<td align=center>' . $arr['points'] . '</td><td align=left>
				<div align=justify class=success>' . $arr['description'] . '</div></td>
				<td align=center><a href="' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=updatebonussystem&id=' . $arr['id'] . '">
				
				
				<i class="fa-solid fa-pen-to-square fa-xl" style="color: #0658e5;" alt="Edit" title="Edit"></i>
				
				
				</a></td></tr>';
              }

              echo '<tr><td colspan=5>' . whereto () . '</td></tr>';
            }
            else
            {
              if ($action == 'add')
              {
                stdhead ('Bonus Points ' . BS_VERSION . ' - ADD');
               
				
				
				echo '
				
				<div class="container mt-3">
                  <div class="card">
            
                <table class="table table-hover">
                 <thead>
                 <tr>
                    <th>Name</th>
                    <th>Points</th>
		            <th>Description</th>
                     <th>Menge</th>
		            <th>Update</th>
                 </tr>
                </thead>';
				
				
		
				
				
                echo '<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
	<input type=hidden name=act value=bonuspoints>
	<input type=hidden name=action value=add_save>';
                echo '<tr>
				<td align=left valign=top>
				<input type=text name=bonusname class="form-control"></td>
				<td align=left valign=top>
				<input type=text name=points size=5 class="form-control"></td>
				<td align=left valign=top><textarea name=description cols=20 rows=10 class="form-control form-control-sm border"></textarea>
				</td><td align=left valign=top>
				<input type=text name=menge size=10 class="form-control"><br />1 GB = 1073741824<br />2.5GB = 2684354560<br />5GB = 5368709120<br />10GB = 10737418240<br />and so far...</td>
				<td align=left valign=top><input type=submit name=add value=add class="btn btn-primary"></td>';
                echo '</form>';
              }
              else
              {
                if ($action == 'add_save')
                {
                  $bonusname = $db->sqlesc ($_POST['bonusname']);
                  $points = $db->sqlesc ($_POST['points']);
                  $description = $db->sqlesc ($_POST['description']);
                  $menge = $db->sqlesc ($_POST['menge']);
                  ($db->sql_query ('' . 'INSERT INTO bonus (bonusname, points, description, menge, art) VALUES (' . $bonusname . ', ' . $points . ', ' . $description . ', ' . $menge . ', \'traffic\')') OR stderr ('ADD bonus', 'Unable to add bonus!'));
                  stderr ('ADD bonus', 'New bonus successfull added.' . whereto (), false);
                }
                else
                {
                  if ($action == 'resetall')
                  {
                    $sure = $_GET['sure'];
                    $query = 'WHERE enabled=\'yes\' AND ustatus=\'confirmed\'';
                    $usergroup = (isset ($_POST['usergroup']) ? (int)$_POST['usergroup'] : (isset ($_GET['usergroup']) ? (int)$_GET['usergroup'] : ''));
                    if (($usergroup == '-' OR !is_valid_id ($usergroup)))
                    {
                      $usergroup = '';
                    }

                    if (!empty ($usergroup))
                    {
                      $query .= '' . ' AND usergroup = ' . $usergroup;
                    }

                    if (!$sure)
                    {
                      //stderr ('Reset ALL Points', 'Sanity check: You are about to reset all points for following Usergroup: <b>' . ($usergroup ? '[' . get_user_class_name ($usergroup) . ']' : '[ALL Usergroups]') . '</b>. Click <a href=\'' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=resetall&sure=1' . ($usergroup ? '' . '&usergroup=' . $usergroup : '') . '\'>here</a> if you are sure.', false);
                    
					    stderr ('Reset ALL Points, Sanity check: You are about to reset all points for following Usergroup: <b>' . ($usergroup ? '[' . get_user_class_name ($usergroup) . ']' : '[ALL Usergroups]') . '</b>. Click <a href=\'' . $_SERVER['SCRIPT_NAME'] . '?act=bonuspoints&action=resetall&sure=1' . ($usergroup ? '' . '&usergroup=' . $usergroup : '') . '\'>here</a> if you are sure.', false);
                  
					
					}
                    else
                    {
                      ($db->sql_query ('' . 'UPDATE users SET seedbonus = 0.0 ' . $query) OR stderr ('Reset ALL Points, Unable to reset!.' . whereto (), false));
                      stderr ('Reset ALL Points, All points have been reset.' . whereto (), false);
                    }
                  }
                  else
                  {
                    if ($action == 'reset')
                    {
                      stdhead ('Bonus Points ' . BS_VERSION . ' - Reset ALL Points');
                      echo '<table border=0 cellspacing=0 cellpadding=10 width=100%>';
                      echo '<form method=post action="' . $_SERVER['SCRIPT_NAME'] . '">
	<input type=hidden name=act value=bonuspoints>
	<input type=hidden name=action value=resetall>';
                      echo '<tr><td>';
                      echo '<div class=error>Are you sure you want to Reset User Points?</div></td><td><div class=error>';
                      $selectbox = _selectbox_ ('Usergroup', 'usergroup');
                      echo $selectbox . ' <input type=submit value="Reset Points" class=button>';
                      echo '</div></td></tr><tr><td colspan=2>' . whereto () . '</td></tr></form>';
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  echo '</table></div></div>';
  stdfoot ();
?>
