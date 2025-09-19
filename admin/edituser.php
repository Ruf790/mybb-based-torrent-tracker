<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  
  
  
  
  
  function months ()
  {
    $months = array ('0' => '---select---', '1' => '1 Week', '2' => '2 Weeks', '3' => '3 Weeks', '4' => '1 Month', '5' => '5 Weeks', '6' => '6 Weeks', '7' => '7 Weeks', '8' => '2 Months', '12' => '3 Months', '16' => '4 Months', '20' => '5 Months', '24' => '6 Months', '28' => '7 Months', '32' => '8 Months', '36' => '9 Months', '40' => '10 Months', '44' => '11 Months', '48' => '12 Months', '255' => 'Unlimited');
    $str = '';
    foreach ($months as $v => $d)
    {
      $str .= '<option value="' . $v . '">' . $d . '</option>';
    }

    return $str;
  }

  function weeks ()
  {
    $weeks = array ('0' => '---select---', '1' => '1 Week', '2' => '2 Weeks', '3' => '3 Weeks', '4' => '4 Weeks', '5' => '5 Weeks', '6' => '6 Weeks', '7' => '7 Weeks', '8' => '8 Weeks', '9' => '9 Weeks', '10' => '10 Weeks', '11' => '11 Weeks', '12' => '12 Weeks', '255' => 'Unlimited');
    $str = '';
    foreach ($weeks as $v => $d)
    {
      $str .= '<option value="' . $v . '">' . $d . '</option>';
    }

    return $str;
  }

  function permission_check ()
  {
    global $userdata;
    global $usergroups;
    global $CURUSER;
    if ((((($userdata['cansettingspanel'] == '1' AND $usergroups['cansettingspanel'] != '1') OR ($userdata['issupermod'] == '1' AND $usergroups['issupermod'] != '1')) OR ($userdata['canstaffpanel'] == '1' AND $usergroups['canstaffpanel'] != '1')) OR $CURUSER['id'] == $userdata['id']))
    {
      print_no_permission (false, true, 'Permission Denied: Protected usergroup!');
      return null;
    }

  }

  function insert_message ($userid, $message, $subject)
  {
    require_once INC_PATH . '/functions_pm.php';
    //send_pm ($userid, $message, $subject);
	
	
	$pm = array(
		'subject' => $subject,
		'message' => $message,
		'touid' => $userid
	);
					
	$pm['sender']['uid'] = -1;
	send_pm($pm, -1, true);
	
	
	
	
  }

  function yesno ($title, $name, $value = 'yes')
  {
    if ($value == 'no')
    {
      $nocheck = ' checked="checked"';
    }
    else
    {
      $yescheck = ' checked="checked"';
    }

    echo '' . '<tr>
<td valign="top" width="40%" align="right">' . $title . '</td>
<td valign="top" width="60%" align="left"><label><input type="radio" class="form-check-input" name="' . $name . '" value="yes"' . (isset ($yescheck) ? $yescheck : '') . ('' . ' />&nbsp;Yes</label> &nbsp;&nbsp;<label><input type="radio" class="form-check-input" name="' . $name . '" value="no"') . (isset ($nocheck) ? $nocheck : '') . ' />&nbsp;No</label></td>
</tr>
';
  }

  function inputbox ($title, $name, $value = '', $class = 'specialboxnn2222', $size = '35', $extra = '', $maxlength = '', $autocomplete = 1, $extra2 = '')
  {
    $value = htmlspecialchars_uni ($value);
    if ($autocomplete != 1)
    {
      $ac = ' autocomplete="off"';
    }
    else
    {
      $ac = '';
    }

    if ($value != '')
    {
      $value = ('' . ' value="' . $value . '"');
    }

    if ($maxlength != '')
    {
      $maxlength = ('' . ' maxlength="' . $maxlength . '"');
    }

    if ($size != '')
    {
      $size = ('' . ' size="' . $size . '"');
    }

    echo ('' . '<tr>
<td valign="top" width="40%" align="right">' . $title . '</td>
<td valign="top" width="60%" align="left">
' . $extra2 . '<input type="text" id="' . $class . '" class="form-control form-control-sm border" name="' . $name . '"') . $size . $maxlength . $ac . $value . ' />
' . $extra . '
</td>
</tr>
';
  }

  function selectbox ($title, $name, $type, $class = 'specialboxnn')
  {
    global $userdata;
    global $usergroups;
	global $db;
    echo '' . '<tr>
<td valign="top" width="40%" align="right">' . $title . '</td><td valign="top" width="60%" align="left">
<select name="' . $name . '" class="form-select">
';
    if ($type == 'trackergroups')
    {
      $query = $db->sql_query ('SELECT gid,title,cansettingspanel,issupermod,canstaffpanel FROM usergroups');
      while ($tclass = $db->fetch_array ($query))
      {
        if (((((($tclass['cansettingspanel'] == '1' AND $usergroups['cansettingspanel'] != '1') OR ($tclass['issupermod'] == '1' AND $usergroups['issupermod'] != '1')) OR ($tclass['canstaffpanel'] == '1' AND $usergroups['canstaffpanel'] != '1')) OR (($tclass['cansettingspanel'] == '1' OR $tclass['issupermod'] == '1') AND $usergroups['cansettingspanel'] != '1')) OR (($tclass['gid'] == UC_ADMINISTRATOR OR $tclass['gid'] == UC_SYSOP) AND $usergroups['cansettingspanel'] != '1')))
        {
          continue;
        }

        echo '<option value="' . $tclass['gid'] . '" ' . ($userdata['usergroup'] == $tclass['gid'] ? 'selected' : '') . '>' . $tclass['title'] . '</option>';
      }
    }

    echo '</select>
</td>
</tr>
';
  }

  function get_user_data ()
  {
    global $userid;
	global $db;
    $res = $db->sql_query ('SELECT u.*, g.cansettingspanel, g.canstaffpanel, g.issupermod FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.id = ' . $db->sqlesc ($userid));
    $arr = $db->fetch_array ($res);
    if (!$arr)
    {
      stderr ('Error', 'No user with this ID!');
    }

    //$query = $db->sql_query ('SELECT supportfor, supportlang FROM ts_support WHERE userid = ' . $db->sqlesc ($userid));
    //if (0 < $db->num_rows ($query))
    //{
      //$supportresult = mysqli_fetch_assoc ($query);
      //$temparray = array_merge ($arr, $supportresult);
      //$arr = $temparray;
     // unset ($temparray);
   // }

    $GLOBALS['userdata'] = $arr;
  }

  function username_exists222 ($username)
  {
    global $db;
	$tracker_query = $db->sql_query ('SELECT username FROM users WHERE username=' . $db->sqlesc ($username) . ' LIMIT 1');
    if (1 <= $db->num_rows ($tracker_query))
    {
      return false;
    }

    return true;
  }

  function validusername ($username)
  {
    if (!preg_match ('|[^a-z\\|A-Z\\|0-9]|', $username))
    {
      return true;
    }

    return false;
  }

  function email_exists ($email)
  {
    global $db;
	$tracker_query = $db->sql_query ('SELECT email FROM users WHERE email=' . $db->sqlesc ($email) . ' LIMIT 1');
    if (1 <= $db->num_rows ($tracker_query))
    {
      return false;
    }

    return true;
  }

  function modcomment ($what = 'Unknown Action taken')
  {
    global $modcomment;
    global $CURUSER;
    if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
    {
      $eol = '
';
    }
    else
    {
      if (strtoupper (substr (PHP_OS, 0, 3) == 'MAC'))
      {
        $eol = '
';
      }
      else
      {
        $eol = '
';
      }
    }

    return gmdate ('Y-m-d') . ' - ' . $what . ' by ' . $CURUSER['username'] . $eol . $modcomment;
  }

  function update_ipban_cache ()
  {
    global $db;
    $query = $db->sql_query ('SELECT * FROM ipbans');
    $_ucache = mysqli_fetch_assoc ($query);
    $content = var_export ($_ucache, true);
    $_filename = TSDIR . '/cache/ipbans.php';
    $_cachefile = @fopen ('' . $_filename, 'w');
    $_cachecontents = '<?php
/** TS Generated Cache#6 - Do Not Alter
 * Cache Name: IPBans
 * Generated: ' . gmdate ('r') . '
*/

';
    $_cachecontents .= '' . '$ipbanscache = ' . $content . ';
?>';
    @fwrite ($_cachefile, $_cachecontents);
    @fclose ($_cachefile);
  }

  $rootpath = './../';
  include $rootpath . '/global.php';
  
  define("IN_MYBB", 1);
  define("IN_ADMINCP", 1);
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  require_once INC_PATH . '/functions_user.php';
  require_once INC_PATH . '/readconfig.php'; 
  
  require_once INC_PATH . '/functions_upload.php';
  
  
  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  gzip ();
  maxsysop ();
  
  
  
  define ('VERSION', 'Edit User Mod v.1.8.3 by xam');
  
  $action = (isset ($_POST['action']) ? htmlspecialchars_uni ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars_uni ($_GET['action']) : ''));
  $do = (isset ($_POST['do']) ? htmlspecialchars_uni ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars_uni ($_GET['do']) : ''));
  $userid = (isset ($_POST['userid']) ? (int)$_POST['userid'] : (isset ($_GET['userid']) ? (int)$_GET['userid'] : ''));
  if ($usergroups['canuserdetails'] != 1)
  {
    print_no_permission (true);
  }

  if (((empty ($action) OR empty ($userid)) OR !is_valid_id ($userid)))
  {
    print_no_permission (true);
  }

  int_check ($userid, true);
  $lang->load ('modtask');
  require_once INC_PATH . '/functions_mkprettytime.php';
  
  
  
  if ($action == 'edituser')
  {
    
    get_user_data ();
    permission_check ();
    stdhead ('Edit User: ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')');
    
	$profilelink_plain2 = $BASEURL.'/'.get_profile_link($userdata['id']);
	
	
	
	echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">';

	?>


<div class="container mt-3">
    <div class="float-start">
        <div class="mb-3">
            <button onclick="window.location.href='<?= $BASEURL ?>/<?= get_profile_link($userdata['id']) ?>';" class="btn btn-primary me-2 mb-2" type="button">
                <i class="bi bi-x-circle text-white"></i> Cancel
            </button>
            <button onclick="window.location.href='<?= $BASEURL ?>/search.php?action=finduserthreads&uid=<?= $userdata['id'] ?>';" class="btn btn-primary me-2 mb-2" type="button">
                <i class="bi bi-card-text text-white"></i> User Threads
            </button>
            <button onclick="window.location.href='<?= $BASEURL ?>/search.php?action=finduser&uid=<?= $userdata['id'] ?>';" class="btn btn-primary me-2 mb-2" type="button">
                <i class="bi bi-chat-left-text text-white"></i> User Posts
            </button>
            <button onclick="window.location.href='<?= $BASEURL ?>/admin/index.php?act=ip_info&userid=<?= $userdata['id'] ?>';" class="btn btn-primary me-2 mb-2" type="button">
                <i class="bi bi-info-circle text-white"></i> IP Info
            </button>
            <button onclick="window.location.href='<?= $_SERVER['SCRIPT_NAME'] ?>?action=resetpasskey&userid=<?= $userdata['id'] ?>';" class="btn btn-primary me-2 mb-2" type="button">
                <i class="bi bi-key text-white"></i> Reset Passkey
            </button>
            <button onclick="window.location.href='<?= $_SERVER['SCRIPT_NAME'] ?>?action=deleteaccount&userid=<?= $userdata['id'] ?>';" class="btn btn-primary me-2 mb-2" type="button">
                <i class="bi bi-trash text-white"></i> Delete
            </button>
        </div>
    </div>
</div>
	   
	   
	   
	   
	   
	   
    </div>
</div><br><br>
<?
	
	
	
echo '
<style>
.avatar-ring2 {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 3px solid #ddd;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
}


</style>

';
	
	
	
    echo '
	
	<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '" name="updateuser" ' . submit_disable ('updateuser', 'updateuser') . '>
	<input type="hidden" name="userid" value="' . $userdata['id'] . '">
	<input type="hidden" name="action" value="updateuser">
	
	<div class="container mt-3">
 
  <div class="card">
    <div class="card-header rounded-bottom text-19 fw-bold">
	Modify User Account for ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')
	</div>
   

   <div class="card-body">
	
	
	';
    echo '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
    //echo '<tbody><tr><td><table class="tborder" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="thead" colspan="4" align="center">Modify User Account for ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')</td></tr>';
    
	  echo '<tbody><tr><td>
	  <table border="0" cellpadding="6" cellspacing="0"><tbody><tr><td align="center">
	  </td></tr>';
  
	
	echo '<tr class="subheader"><td align="center" colspan="2"><i class="fa-solid fa-circle-info text-primary"></i> Required Information</td></tr>';
    inputbox('<i class="fa-solid fa-user text-primary"></i> <b>Username:</b>', 'username', $userdata['username']);
    inputbox('<i class="fa-solid fa-lock text-primary"></i> <b>New Password:</b>', 'password');
    inputbox('<i class="fa-solid fa-envelope text-primary"></i> <b>Email Address:</b>', 'email', $userdata['email']);
    selectbox('<i class="fa-solid fa-users-gear text-primary"></i> <b>Tracker Usergroup:</b>', 'usergroup', 'trackergroups');
    
	
	
	echo '<tr class="subheader"><td align="center" colspan="2"><i class="fa-solid fa-circle-user text-primary"></i> Personal Informations</td></tr>';
    inputbox('<i class="fa-solid fa-award text-primary"></i> <b>User Title:</b>', 'usertitle', htmlspecialchars_uni($userdata['usertitle']));
    inputbox('<i class="fa-solid fa-image text-primary"></i> <b>User Avatar:</b>', 'avatar', htmlspecialchars_uni($userdata['avatar']));
	
	//$useravatar = format_avatar($userdata['avatar'], $userdata['avatardimensions']);
   // $avatar22 = '<img class="rounded img-fluid" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
   
    $useravatar = format_avatar($userdata['avatar'], $userdata['avatardimensions']);

   // Если аватар — это HTML-заглушка (начинается с '<'), выводим её как есть
   if (strpos($useravatar['image'], '<') === 0) 
   {
	   $avatar22 = $useravatar['image']; // <div class="avatar-ring2">No Avatar</div>
   } 
   // Иначе выводим как <img> (стандартный аватар)
   else 
   {
      $avatar22 = '<img class="rounded img-fluid" src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
   }
   
   
   
	
	
	
	print '<tr><td colspan=3 align=center>'.$avatar22.'&nbsp;</td></tr>';
	
	
	
	
	
    print '<tr><td align=\'right\' valign=\'top\'><b>User Signature:</b></td><td colspan=\'2\' align=\'left\'>
	<textarea cols=\'60\' rows=\'6\' name=\'signature\' class="form-control form-control-sm border" id=\'specialboxg\' />' . htmlspecialchars_uni ($userdata['signature']) . '</textarea><br />' . $userdata['signature'] . '</td></tr>
';
    echo '<tr class="subheader"><td align="center" colspan="2">Account Preferences</td></tr>';
    yesno ('<b>Is Donor:</b>', 'donor', ($userdata['donor'] == 'yes' ? 'yes' : 'no'));
    //echo '<tr><td colspan="1" align="right" valign="top"><b>Donor Status:</b></td>';
    echo '<td colspan="3" align="right">
	<table width="100%">
   
   
	</td></tr>';
    

    echo '</td></table></tr>';






$warned = $userdata['warned'] == 'yes';



echo '<tr>
<td colspan="1" align="right" valign="top"><i class="fa-solid fa-triangle-exclamation text-warning"></i> <b>Warning System:</b></td>
<td colspan="3" align="left">
<table class="table table-sm table-borderless align-middle mb-0">';

echo '<tr>
<td><i class="fa-solid fa-user-shield text-primary"></i> Is user warned?</td>
<td>';
if ($warned) {
    echo '<input name="warned" class="form-check-input" value="yes" type="radio" checked> Yes 
    <input name="warned" class="form-check-input" value="no" type="radio"> No';
} else {
    echo '<span class="badge bg-success">Not Warned Yet</span>';
}
echo '</td></tr>';

if ($warned) {
    $warneduntil = mkprettytime($userdata['warneduntil'] - TIMENOW);
    $warneduntilDate = my_datee($dateformat, $userdata['warneduntil']);

    echo '<tr><td><i class="fa-regular fa-clock text-primary"></i> Warned Until</td><td>';
    echo ($warneduntil == '0') ? '<span class="text-danger">(Arbitrary duration)</span>' : $warneduntilDate . ' <small class="text-muted">(' . $warneduntil . ' left)</small>';
    echo '</td></tr>';
} else {
    echo '<tr><td><i class="fa-solid fa-calendar-plus text-primary"></i> Warn User for</td>
    <td><select name="warnlength" class="form-select form-select-sm w-auto d-inline-block ms-2">' . weeks() . '</select></td></tr>';

    echo '<tr><td><i class="fa-solid fa-pen text-primary"></i> Reason of Warning</td>
    <td><input type="text" class="form-control form-control-sm w-75" name="warnpm" placeholder="Reason"></td></tr>';
}

$elapsedlw = mkprettytime(TIMENOW - $userdata['lastwarned']);
include_once INC_PATH . '/readconfig_cleanup.php';

echo '<tr><td><i class="fa-solid fa-list-ol text-primary"></i> Times Warned</td>
<td>' . $userdata['timeswarned'] . ' <small class="text-muted">(Max: ' . $ban_user_limit . ' then ban)</small>';
if ($userdata['timeswarned'] > 1) {
    echo ' <label class="ms-2"><input type="checkbox" name="reset_timeswarned" value="yes"> Reset counter</label>';
}
echo '</td></tr>';

if ($userdata['timeswarned'] == 0 || empty($userdata['warnedby'])) {
    echo '<tr><td><i class="fa-regular fa-clock text-primary"></i> Last Warning</td><td><span class="text-muted">This user hasn\'t been warned yet.</span></td></tr>';
} else {
    if ($userdata['warnedby'] != 'System' && !empty($userdata['warnedby'])) {
        $res = $db->sql_query('SELECT id, username FROM users WHERE id = ' . $userdata['warnedby']);
        $arr = $db->fetch_array($res);
        $warnedby = ' by <a href="' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '">' . htmlspecialchars_uni($arr['username']) . '</a>.';
    } else {
        $warnedby = ' Automatic Warn by System.';
    }
    echo '<tr><td><i class="fa-regular fa-clock text-primary"></i> Last Warning</td><td>' . $elapsedlw . ' ago' . $warnedby . '</td></tr>';
}

$leechwarn = $userdata['leechwarn'] == 'yes';
echo '<tr><td><i class="fa-solid fa-download text-primary"></i> Auto Leech Warning</td><td>';
if ($leechwarn) {
    echo '<span class="text-danger">Yes, Warned (Low Ratio)</span>';
    $leechuntil = mkprettytime($userdata['leechwarnuntil'] - TIMENOW);
    $leechuntilDate = my_datee($dateformat, $userdata['leechwarnuntil']);
    if ($leechuntil != '0') {
        echo '<div><i class="fa-regular fa-clock text-primary"></i> Until: ' . $leechuntilDate . ' <small class="text-muted">(' . $leechuntil . ' left)</small></div>';
    } else {
        echo '<div><i class="fa-regular fa-clock text-primary"></i> Until: <span class="text-danger">UNLIMITED!</span></div>';
    }
} else {
    echo '<span class="text-success">No, Not Warned yet</span>';
}
echo '</td></tr>';

echo '</table></td></tr>';







	
	
	
    //$supportfor = htmlspecialchars_uni ($userdata['supportfor']);
    //$supportlang = htmlspecialchars_uni ($userdata['supportlang']);
    //print '' . '<tr><td class=rowhead>Support Language:</td><td colspan=2 align=left><input type=text name=supportlang value=\'' . $supportlang . '\' id=\'specialboxn\' /></td></tr>
//';
    //print '' . '<tr><td class=rowhead>Support for:</td><td colspan=2 align=left><textarea cols=60 rows=6 name=supportfor class="form-control form-control-sm border" id=specialboxg />' . $supportfor . '</textarea></td></tr>
//';
    $modcomment = htmlspecialchars_uni ($userdata['modcomment']);
    if ($usergroups['cansettingspanel'] != '1')
    {
      print '' . '<tr><td class=rowhead>Comment:</td><td colspan=2 align=left><textarea cols=60 rows=18 name=modcomment class="form-control form-control-sm border" id=specialboxg READONLY>' . $modcomment . '</textarea></td></tr>
';
    }
    else
    {
      print '' . '<tr><td class=rowhead>Comment</td><td colspan=2 align=left><textarea cols=60 rows=18 name=modcomment class="form-control form-control-sm border" id=specialboxg>' . $modcomment . '</textarea></td></tr>
';
    }

    print '<tr><td class=rowhead>Add&nbsp;Comment:</td><td colspan=2 align=left><textarea cols=60 rows=2 name=addcomment class="form-control form-control-sm border" id=specialboxg></textarea></td></tr>
';
    $bonuscomment = htmlspecialchars_uni ($userdata['bonuscomment']);
    print '' . '<tr><td class=rowhead>Seeding Karma:</td><td colspan=2 align=left><textarea cols=60 rows=6 id=specialboxg class="form-control form-control-sm border" name=bonuscomment READONLY>' . $bonuscomment . '</textarea></td></tr>
';
    
	
	






















echo '<tr class="subheader"><td align="center" colspan="2"><i class="fas fa-shield-alt text-primary"></i> Permissions</td></tr>';

print '<tr>
<td class=rowhead><i class="fas fa-user-check text-primary"></i> Account Enabled?</td>
<td colspan="2" align="left">
<span style="float: right">
<i class="fas fa-ban text-danger"></i> Ban/Unban IP? 
<select name="banip">
    <option value="yes">Yes</option>
    <option value="no">No</option>
</select>
</span>
<input name="enabled" class="form-check-input" value="yes" type="radio"' . ($userdata['enabled'] == 'yes' ? ' checked' : '') . '>Yes 
<input name="enabled" class="form-check-input" value="no" type="radio"' . ($userdata['enabled'] == 'no' ? ' checked' : '') . '>No
</td>
</tr>';

if ($userdata['enabled'] == 'no') 
{
    echo '<tr>
    <td class=rowhead><i class="fas fa-exclamation-triangle text-danger"></i> Ban Reason:</td>
    <td colspan="2" align="left">' . $userdata['notifs'] . '</td>
    </tr>';
}


    ($query = $db->sql_query ('SELECT canupload, candownload, cancomment FROM ts_u_perm WHERE userid = ' . $db->sqlesc ($userid)));
    if (0 < $db->num_rows ($query))
    {
      $permresults = mysqli_fetch_assoc ($query);
      $userdata['cancomment'] = ($permresults['cancomment'] == 1 ? 'yes' : 'no');
      
     
      $userdata['canupload'] = ($permresults['canupload'] == 1 ? 'yes' : 'no');
      $userdata['candownload'] = ($permresults['candownload'] == 1 ? 'yes' : 'no');
    }
    else
    {
      $userdata['cancomment'] = $userdata['canupload'] = $userdata['candownload'] = 'yes';
    }

print '<tr>
<td class=rowhead><i class="fas fa-comments text-primary"></i> Torrent Comment possible?</td>
<td colspan="2" align="left">
<input type="radio" class="form-check-input" name="cancomment" value="yes"' . ($userdata['cancomment'] == 'yes' ? ' checked' : '') . '>Yes 
<input type="radio" class="form-check-input" name="cancomment" value="no"' . ($userdata['cancomment'] == 'no' ? ' checked' : '') . '>No
</td>
</tr>';

print '<tr>
<td class=rowhead><i class="fas fa-upload text-primary"></i> Upload possible?</td>
<td colspan="2" align="left">
<input type="radio" class="form-check-input" name="canupload" value="yes"' . ($userdata['canupload'] == 'yes' ? ' checked' : '') . '>Yes 
<input type="radio" class="form-check-input" name="canupload" value="no"' . ($userdata['canupload'] == 'no' ? ' checked' : '') . '>No
</td>
</tr>';

print '<tr>
<td class=rowhead><i class="fas fa-download text-primary"></i> Download possible?</td>
<td colspan="2" align="left">
<input type="radio" class="form-check-input" name="candownload" value="yes"' . ($userdata['candownload'] == 'yes' ? ' checked' : '') . '>Yes 
<input type="radio" class="form-check-input" name="candownload" value="no"' . ($userdata['candownload'] == 'no' ? ' checked' : '') . '>No
</td>
</tr>';

print '<tr>
<td class=rowhead><i class="fas fa-user-cog text-primary"></i> Moderate this user posts?</td>
<td colspan="2" align="left">
<input type="radio" class="form-check-input" name="moderateposts" value="1"' . ($userdata['moderateposts'] == 1 ? ' checked' : '') . '>Yes 
<input type="radio" class="form-check-input" name="moderateposts" value="0"' . ($userdata['moderateposts'] == 0 ? ' checked' : '') . '>No
</td>
</tr>';


























	
	


	
	print '</td></tr>';
    $othervalue = ' disabled=\\"disabled\\" ';
    if (($usergroups['cansettingspanel'] == '1' OR $usergroups['issupermod'] == '1'))
    {
      $othervalue = '';
    }

    echo '<tr class="subheader"><td align="center" colspan="2"><i class="fa fa-ellipsis-h text-primary"></i> Other</td></tr>';

    print '<tr>
<td class="rowhead"><i class="fa fa-star text-primary"></i> Bonus Points:</td>
<td colspan="2" align="left"><input type="text" class="form-control form-control-sm border" name="seedbonus" size="50" id="" value="' . htmlspecialchars_uni($userdata['seedbonus']) . '"' . $othervalue . '/></td>
</tr>';

    print '<tr>
<td class="rowhead"><i class="fa fa-envelope-open-text text-primary"></i> Invites:</td>
<td colspan="2" align="left"><input type="text" class="form-control form-control-sm border" name="invites" size="50" id="" value="' . (int)$userdata['invites'] . '"' . $othervalue . '/></td>
</tr>';

    print '<tr>
<td class="rowhead"><i class="fa fa-upload text-primary"></i> Amount Uploaded:</td>
<td colspan="2" align="left"><input type="text" class="form-control form-control-sm border" size="50" name="uploaded" value="' . htmlspecialchars_uni($userdata['uploaded']) . '"' . $othervalue . '/> (' . mksize($userdata['uploaded']) . ')</td>
</tr>';

    print '<tr>
<td class="rowhead"><i class="fa fa-download text-primary"></i> Amount Downloaded:</td>
<td colspan="2" align="left"><input type="text" class="form-control form-control-sm border" size="50" name="downloaded" value="' . htmlspecialchars_uni($userdata['downloaded']) . '"' . $othervalue . '/> (' . mksize($userdata['downloaded']) . ')</td>
</tr>';










    //print '<tr><td colspan=3 align=right><input type=\'submit\' class=button value=\'Update User\' name=\'updateuser\'> <input type=reset class=button value=\'Reset\'></td></tr></form>
//';
    echo '</form></table></table>
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	</div> 
    <div class="card-footer text-center">
	<tr><td colspan=3 align=center>
	<input type=\'submit\' class="btn btn-primary" value=\'Update User\' name=\'updateuser\'> <input type=reset class="btn btn-primary" value=\'Reset\'></td></tr></form>
	</div>
  
  </div>
</div>';
	
	
	
	
	
	
	
	
	
	
	
	
	
    stdfoot ();
    return 1;
  }

  
  
  
  
  
  
  
if ($action == 'updateuser')
{
    get_user_data();
    permission_check();

    // Получаем данные из POST
    
	$enabled            = isset($_POST['enabled']) ? ($_POST['enabled'] === 'no' ? 'no' : 'yes') : 'yes';
	$username           = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password           = isset($_POST['password']) ? $_POST['password'] : '';
    $email              = isset($_POST['email']) ? trim($_POST['email']) : '';
    $usergroup          = isset($_POST['usergroup']) ? (int)$_POST['usergroup'] : 0;
    $usertitle          = isset($_POST['usertitle']) ? trim($_POST['usertitle']) : '';
    $avatar             = isset($_POST['avatar']) ? trim($_POST['avatar']) : '';
    $avatardimensions   = isset($_POST['avatardimensions']) ? trim($_POST['avatardimensions']) : '';
    $avatartype         = isset($_POST['avatartype']) ? trim($_POST['avatartype']) : '';
    $signature          = isset($_POST['signature']) ? trim($_POST['signature']) : '';
    $donor              = isset($_POST['donor']) ? $_POST['donor'] : 'no';
    $moderateposts      = isset($_POST['moderateposts']) ? (int)$_POST['moderateposts'] : 0;
    $warned             = isset($_POST['warned']) ? $_POST['warned'] : '';
    $warnlength         = isset($_POST['warnlength']) ? (int)$_POST['warnlength'] : 0;
    $warnpm             = isset($_POST['warnpm']) ? trim($_POST['warnpm']) : '';
    $addcomment         = isset($_POST['addcomment']) ? trim($_POST['addcomment']) : '';
    $seedbonus          = isset($_POST['seedbonus']) ? trim($_POST['seedbonus']) : '';
    $invites            = isset($_POST['invites']) ? (int)$_POST['invites'] : 0;
    $uploaded           = isset($_POST['uploaded']) ? trim($_POST['uploaded']) : '';
    $downloaded         = isset($_POST['downloaded']) ? trim($_POST['downloaded']) : '';
    $reset_timeswarned  = isset($_POST['reset_timeswarned']) ? $_POST['reset_timeswarned'] : '';

    $update_array = array();
    $modcomment = $userdata['modcomment'];

    // Username
    if ($username != $userdata['username'] && validusername($username) && username_exists222($username))
    {
        $update_array['username'] = $username;
        $modcomment = modcomment("Username ({$userdata['username']}) changed to ({$username})");
    }
	
	
	if (!empty ($avatar))
    {
          @clearstatcache ();
          $avatar = (isset ($avatar) ? $avatar : '');
          $image_info = @getimagesize ($avatar);
          if ((!$remote_file = @fopen ($avatar, 'rb') OR !$image_info))
          {
            //$error = show__message ($lang->usercp['a_error1']);
			
			
			 $error = '<div class="container mt-3">
 
  <div class="alert alert-danger">
   '.$lang->usercp['a_error1'].'
</div>';
			
            unset ($avatar);
          }
          else
          {
            $user_avatar_size = 0;
            do
            {
              if ((strlen (@fread ($remote_file, 1)) == 0 OR $f_avatar_maxsize < $user_avatar_size))
              {
                break;
              }

              ++$user_avatar_size;
            }while (!(true));

            @fclose ($remote_file);
            //$error = check_avatar ($image_info[0], $image_info[1], $image_info['mime'], $user_avatar_size);
            if ($error)
            {
              unset ($avatar);
            }
          }
     }
	 
	 $avatar_dimensions = $image_info[0]."|".$image_info[1];

    
	
	
	// Password
    if (!empty($password))
    {
        $user['loginkey'] = generate_loginkey();
        $password_fields = create_password($password, false, $user);
        $user = array_merge($user, $password_fields);

        $update_array['loginkey'] = $user['loginkey'];
        $update_array['password'] = $user['password'];
        $update_array['salt'] = $user['salt'];
        $modcomment = modcomment("Password updated");
    }

    // Email
    if ($email != $userdata['email'] && !emailbanned($email) && check_email($email))
    {
        $update_array['email'] = $email;
        $modcomment = modcomment("Email ({$userdata['email']}) changed to ({$email})");
    }

    // Usergroup
    if ($usergroup != $userdata['usergroup'] && is_valid_id($usergroup))
    {
        $update_array['usergroup'] = $usergroup;
        $modcomment = modcomment("Usergroup ({$userdata['usergroup']}) changed to ({$usergroup})");
    }

    // User title
    if ($usertitle != $userdata['usertitle'])
    {
        $update_array['usertitle'] = $usertitle;
        $modcomment = modcomment("Title ({$userdata['usertitle']}) changed to ({$usertitle})");
    }

    
   // Обработка аватара - безопасная версия
   if (isset($_POST['avatar'])) 
   {
    $new_avatar = trim($_POST['avatar']);
    
    // Обновляем только если URL аватара действительно изменился
    if ($new_avatar != $userdata['avatar']) 
	{
        
        // Случай 1: Пользователь хочет удалить аватар (пустая строка)
        if (empty($new_avatar)) 
		{
            
			// Получаем ID пользователя
            $user_id2 = $userdata['id']; // или другой способ получения ID
    
            // Удаляем все файлы аватаров для этого пользователя
            remove_avatars($user_id2);
			
			
			$update_array['avatar'] = '';
            $update_array['avatardimensions'] = '';
            $update_array['avatartype'] = '';
            $modcomment = modcomment("Аватар удалён");
        }
		
        // Случай 2: Новый валидный URL аватара
        elseif (filter_var($new_avatar, FILTER_VALIDATE_URL)) 
		{
            // Пытаемся получить размеры (но не критично, если не получится)
            $image_info = @getimagesize($new_avatar);
            $avatar_dimensions = $image_info ? $image_info[0]."|".$image_info[1] : '';
            
            $update_array['avatar'] = $new_avatar;
            $update_array['avatardimensions'] = $avatar_dimensions;
            $update_array['avatartype'] = 'remote';
            $modcomment = modcomment("Аватар обновлён");
        }
		
        // Случай 3: Невалидный URL - сохраняем старый аватар
        else 
		{
            // Не обновляем $update_array, сохраняем текущий аватар
            $modcomment = modcomment("Попытка обновить аватар: неверный URL");
        }
    }
    // Если аватар не менялся - ничего не делаем
    }
   
   
   
   
   
   
   
   
   
   
   

    // Signature
    if ($signature != $userdata['signature'])
    {
        $update_array['signature'] = $signature;
        $modcomment = modcomment("Signature updated");
    }

    // Donor
    if ($donor != $userdata['donor'])
    {
        $update_array['donor'] = $donor;
        $modcomment = modcomment("Donor status changed to ({$donor})");
    }

    // Moderate posts
    if ($moderateposts != $userdata['moderateposts'])
    {
        $update_array['moderateposts'] = $moderateposts;
        $modcomment = modcomment("Moderateposts status changed to ({$moderateposts})");
    }

    // Warn logic
    if ($warned == 'no' && $userdata['warned'] == 'yes')
    {
        $update_array['warned'] = 'no';
        $update_array['warneduntil'] = '0';
        $modcomment = sprintf($lang->modtask['modcommentwarningremovedby'], gmdate('Y-m-d'), $CURUSER['username'], $modcomment);
    }
    elseif (is_valid_id($warnlength) && $userdata['warned'] == 'no')
    {
        if (empty($warnpm)) $warnpm = 'No Reason Given.';

        if ($warnlength == 255)
        {
            $modcomment = sprintf($lang->modtask['modcommentwarning'], gmdate('Y-m-d'), $CURUSER['username'], $warnpm, $modcomment);
            $update_array['warneduntil'] = '0000-00-00 00:00:00';
        }
        else
        {
            $warneduntil = TIMENOW + $warnlength * 604800;
            $dur = sprintf($lang->modtask['weeks'], $warnlength);
            $modcomment = sprintf($lang->modtask['modcommentwarning2'], gmdate('Y-m-d'), $dur, $CURUSER['username'], $warnpm, $modcomment);
            $update_array['warneduntil'] = $warneduntil;
        }

        $update_array['warned'] = 'yes';
        $update_array['timeswarned'] = $userdata['timeswarned'] + 1;
        $update_array['lastwarned'] = TIMENOW;
        $update_array['warnedby'] = $CURUSER['id'];
    }

    // Extra comment
    if (!empty($addcomment))
    {
        $modcomment = gmdate('Y-m-d') . ' - ' . $addcomment . ' - ' . $CURUSER['username'] . "\n" . $modcomment;
    }

    
	
	// Update Permissions
	$updateperm = array ();
    $updateperm['cancomment'] = ($_POST['cancomment'] == 'yes' ? 1 : 0);
  
    $updateperm['canupload'] = ($_POST['canupload'] == 'yes' ? 1 : 0);
    $updateperm['candownload'] = ($_POST['candownload'] == 'yes' ? 1 : 0);
    ($db->sql_query ('' . 'REPLACE INTO ts_u_perm (userid, cancomment, canupload, candownload) VALUES (' . $userid . ', ' . $updateperm['cancomment'] . ', ' . $updateperm['canupload'] . ', ' . $updateperm['candownload'] . ')'));
  
	
	
	
	
	
	
	// Seed bonus
    if ($seedbonus != $userdata['seedbonus'])
    {
        $update_array['seedbonus'] = $seedbonus;
        $modcomment = modcomment("Seed Bonus changed to ({$seedbonus})");
    }

    // Invites
    if ($invites != $userdata['invites'])
    {
        $update_array['invites'] = $invites;
        $modcomment = modcomment("Invites changed to ({$invites})");
    }

    // Uploaded
    if ($uploaded != $userdata['uploaded'])
    {
        $update_array['uploaded'] = $uploaded;
        $modcomment = modcomment("Uploaded changed to (" . mksize($uploaded) . ")");
    }

    // Downloaded
    if ($downloaded != $userdata['downloaded'])
    {
        $update_array['downloaded'] = $downloaded;
        $modcomment = modcomment("Downloaded changed to (" . mksize($downloaded) . ")");
    }

    // Modcomment
    if ($modcomment != $userdata['modcomment'])
    {
        $update_array['modcomment'] = $modcomment;
    }
	
	
	// Enabled
	if ($enabled !== $userdata['enabled']) 
	{
       $update_array['enabled'] = $enabled;
       $modcomment = modcomment("Account enabled set to ({$enabled})");
    }
	
	
	
	// Reset Times Warned
	if (!empty($_POST['reset_timeswarned'])) 
	{ 
          $update_array['timeswarned'] = 1;     
          $modcomment = modcomment('Warning Count Reset');
    }
	
	
	

    // ✅ Выполняем обновление через твою функцию
    if (!empty($update_array))
    {
        $db->update_query("users", $update_array, "id = " . (int)$userdata['id']);
        write_log("User {$userdata['username']} ({$userdata['id']}) has been edited by {$CURUSER['username']}");
    }

    redirect("admin/edituser.php?action=edituser&userid={$userdata['id']}", "The account ({$userdata['id']}) has been updated...");
    return 1;
}

  
  
  
  
  
  
  
  
  
  
  
  
  
  

  if ($action == 'banaccount')
  {
    get_user_data ();
    permission_check ();
    $reason = trim ($_POST['reason']);
    $db->sql_query ('UPDATE users SET notifs = ' . $db->sqlesc ('Reason: ' . $reason) . ' WHERE id = ' . $db->sqlesc ($userdata['id']));
    write_log ('Following user banned by ' . $CURUSER['username'] . '. User: ' . $userdata['username'] . ' Reason: ' . htmlspecialchars_uni ($reason));
    redirect ('admin/edituser.php?action=edituser&userid=' . $userid, 'The account (' . $userid . ') has been updated...');
    return 1;
  }

 
 
 
if ($action == 'deleteaccount')
{
    if (($usergroups['issupermod'] != '1' AND $usergroups['cansettingspanel'] != '1'))
    {
        print_no_permission();
    }

    get_user_data();
    permission_check();

    $userid = (int)$userdata['id'];
    $username = htmlspecialchars_uni($userdata['username']);

    // Проверяем, пришло ли подтверждение
    if (!isset($_GET['sure']))
    {
       
       
	   stdhead();
echo '
<div class="container mt-5">
    <div class="card shadow-sm border-danger">
        <div class="card-header bg-danger text-white">
            <i class="fa-solid fa-triangle-exclamation"></i> Confirm Account Deletion
        </div>
        <div class="card-body text-center">
            <p class="mb-4">Are you sure you want to delete the following account?</p>
            <h5 class="fw-bold">' . $username . ' (ID: ' . $userid . ')</h5>
            <div class="d-flex justify-content-center mt-4">
                <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=deleteaccount&userid=' . $userid . '&sure=1&my_post_key=' . $mybb->post_code . '" class="btn btn-danger me-3">
                    <i class="fa-solid fa-check"></i> YES, Delete
                </a>
                <a href="' . $_SERVER['SCRIPT_NAME'] . '?action=edituser&userid=' . $userid . '" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
            </div>
        </div>
    </div>
</div>
';
stdfoot();
	   
	   
	   
	   
	   
        return;
    }


    // Получаем данные пользователя
    $user = get_user($userdata['id']);

    require_once INC_PATH . "/datahandlers/user.php";
    $userhandler = new UserDataHandler('delete');

    if (!$userhandler->delete_user($user['id']))
    {
        stderr('Error', 'Cannot delete user!');
        redirect($_SERVER['SCRIPT_NAME']);
    }

    write_log('Account: ' . $userdata['username'] . ' (' . $userdata['id'] . ') has been deleted by ' . $CURUSER['username']);
    stderr('Done, The account <strong>' . htmlspecialchars_uni($userdata['username']) . '</strong> has been successfully deleted', false);
    return 1;
}


  
  
  
  
  
  
  if ($action == 'resetpasskey')
  {
    get_user_data();
    permission_check();

    // Проверяем, был ли уже подтвержденный POST
    if (isset($_POST['sure']) && $_POST['sure'] == 1)
    {
        
        $modcomment = $userdata['modcomment'];

        
		$old_passkey = $userdata['passkey'];
		$passkey = generate_passkey($userdata['username'], $userdata['loginkey']);

      
		$modcomment = gmdate('Y-m-d') . ' - Passkey reset by ' . $CURUSER['username'] . 
              '. Old Passkey: ' . $old_passkey . 
              ' | New Passkey: ' . $passkey . "\n" . $modcomment;
			  
			  
		
        $msg = sprintf($lang->modtask['passkeymsg'], $CURUSER['username']);
        $subject = $lang->modtask['passkeysubject'];

        $db->sql_query('UPDATE users SET passkey = ' . $db->sqlesc($passkey) . ', modcomment = ' . $db->sqlesc($modcomment) . ' WHERE id = ' . $db->sqlesc($userdata['id']));
        insert_message($userid, $msg, $subject);

 	
		write_log('Passkey for user: ' . $userdata['username'] . ' (' . $userdata['id'] . ') has been reset by ' . $CURUSER['username'] . '. Old Passkey: ' . $old_passkey . ' | New Passkey: ' . $passkey);
		
        redirect('admin/edituser.php?action=edituser&userid=' . $userdata['id'], 'The passkey has been updated...');
        return 1;
    }

    // Если ещё не подтверждено — показываем форму подтверждения
    stdhead('Reset Passkey for: ' . $userdata['username']);

    echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '?action=resetpasskey&userid=' . $userdata['id'] . '">
    <input type="hidden" name="userid" value="' . $userdata['id'] . '" />
    <input type="hidden" name="sure" value="1" />
    <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
    <table width="100%" border="0" cellspacing="0" cellpadding="5">
    <tr>
        <td class="thead">Reset Passkey Confirmation</td>
    </tr>
    <tr>
        <td align="center">Are you sure you want to reset passkey for user: <strong>' . htmlspecialchars_uni($userdata['username']) . '</strong>?<br /><br />
        <input type="submit" class="btn btn-primary" value="Yes, Reset Passkey" />
        &nbsp;&nbsp;
        <a href="edituser.php?action=edituser&userid=' . $userdata['id'] . '" class="btn btn-secondary">Cancel</a>
        </td>
    </tr>
    </table>
    </form>';

    stdfoot();
    return 1;
  }

  

  if ($action == 'warnuser')
  {
    get_user_data ();
    permission_check ();
    $modcomment = $userdata['modcomment'];
    if ($userdata['id'] == $CURUSER['id'])
    {
      print_no_permission ();
    }

    if ($do == 'warn')
    {
      require INC_PATH . '/functions_getvar.php';
      getvar (array ('warnpm', 'warnlength', 'hash'));
      if (((empty ($warnpm) OR empty ($hash)) OR !is_valid_id ($warnlength)))
      {
        stderr ('Error', 'Don\'t leave any fields blank!');
      }

      if ((($hash !== $_SESSION['token_code'] OR empty ($hash)) OR empty ($_SESSION['token_code'])))
      {
        unset ($_SESSION[token_code]);
        header ('' . 'Location: ' . $_SERVER['SCRIPT_NAME'] . '?action=warnuser&userid=' . $userdata['id']);
        exit ();
      }

      unset ($_SESSION[token_code]);
      if ($warnlength == '255')
      {
        $modcomment = sprintf ($lang->modtask['modcommentwarning'], gmdate ('Y-m-d'), $CURUSER['username'], $warnpm, $modcomment);
        $msg = sprintf ($lang->modtask['warningmessage'], $CURUSER['username'], $warnpm);
        $tu[] = 'warneduntil = \'0000-00-00 00:00:00\'';
      }
      else
      {
        $warneduntil = TIMENOW + $warnlength * 604800;
        $dur = sprintf ($lang->modtask['weeks'], $warnlength);
        $msg = sprintf ($lang->modtask['warningmessage2'], $dur, $CURUSER['username'], $warnpm);
        $modcomment = sprintf ($lang->modtask['modcommentwarning2'], gmdate ('Y-m-d'), $dur, $CURUSER['username'], $warnpm, $modcomment);
        $tu[] = 'warneduntil = ' . $db->sqlesc ($warneduntil);
      }

      $subject = $lang->modtask['warningsubject'];
      $lastwarned = TIMENOW;
      $tu[] = '' . 'warned = \'yes\', timeswarned = timeswarned + 1, lastwarned = ' . $db->sqlesc($lastwarned) . ', warnedby = ' . $CURUSER['id'];
      insert_message ($userid, $msg, $subject);
      if (((isset ($tu) AND !empty ($userdata['id'])) AND is_valid_id ($userdata['id'])))
      {
        if ((isset ($modcomment) AND $modcomment != $userdata['modcomment']))
        {
          $tu[] = 'modcomment = ' . $db->sqlesc ($modcomment);
        }

        ($db->sql_query ('UPDATE users SET  ' . implode (', ', $tu) . ' WHERE id=' . $db->sqlesc ($userdata['id'])));
      }

      write_log ('Account: ' . $userdata['username.'] . ' (' . $userdata['id'] . ') has been warned by ' . $CURUSER['username']);
      redirect ('userdetails.php?id=' . $userdata['id'], 'User (' . $userdata['id'] . ') has been warned!');
      return 1;
    }

    stdhead ('Warn User: ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')');
    
	$profilelink_plain = get_profile_link($userdata['id']);
	
	$where = array ('Cancel' => $profilelink_plain);
    echo jumpbutton ($where);
    include_once INC_PATH . '/ts_token.php';
    $ts_token = new ts_token ();
    $hash = $ts_token->create_return ();
    echo '<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
		<input type="hidden" name="userid" value="' . $userdata['id'] . '">
		<input type="hidden" name="action" value="warnuser">
		<input type="hidden" name="do" value="warn">
		<input type="hidden" name="hash" value="' . $hash . '">';
    echo '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table class="tborder" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr>
	<td class="thead" colspan="4" align="center">Warn User: ' . $userdata['username'] . ' (UID: ' . $userdata['id'] . ')</td></tr>';
    inputbox ('Warn Reason:', 'warnpm');
    print '<tr><td align=right>Warn Length:</td><td><select name=\'warnlength\' id=specialboxs>
';
    print weeks ();
    print '</select> <input type=submit value=\'Warn User\' class=button></td></tr>
';
    echo '</form></table></table>';
    stdfoot ();
  }

?>
