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
  
  require_once INC_PATH . '/functions_multipage.php';

  define ('M_VIP_VERSION', 'v0.4 by xam');
  $do = (isset ($_GET['do']) ? trim ($_GET['do']) : (isset ($_POST['do']) ? trim ($_POST['do']) : ''));
  if ($do == 'update')
  {
    $add = trim ($_POST['add']);
    $limit = intval ($_POST['limit']);
    $userids = $_POST['userids'];
    $page = $_GET['page'] = intval ($_POST['page']);
    if (((is_array ($userids) AND 0 < count ($userids)) AND 0 < $limit))
    {
      if ($add == 'donoruntil')
      {
        $donorlengthadd = $limit * 7;
        ($db->sql_query ('' . 'UPDATE users SET donoruntil = IF(donoruntil=\'0000-00-00 00:00:00\', ADDDATE(NOW(), INTERVAL ' . $donorlengthadd . ' DAY ), ADDDATE( donoruntil, INTERVAL ' . $donorlengthadd . ' DAY)) WHERE id IN (0,' . implode (',', $userids) . ')') OR sqlerr (__FILE__, 35));
      }
      else
      {
        if ($add == 'seedbonus')
        {
          ($db->sql_query ('' . 'UPDATE users SET seedbonus = seedbonus + ' . $limit . ' WHERE id IN (0,' . implode (',', $userids) . ')'));
        }
        else
        {
          if ($add == 'invites')
          {
            ($db->sql_query ('' . 'UPDATE users SET invites = invites + ' . $limit . ' WHERE id IN (0,' . implode (',', $userids) . ')'));
          }
        }
      }
    }
  }

  $where = $link = $username = '';
  if ($do == 'search_user')
  {
    $username = (isset ($_GET['username']) ? trim ($_GET['username']) : (isset ($_POST['username']) ? trim ($_POST['username']) : ''));
    if (!empty ($username))
    {
      $where = ' AND (u.username = ' . $db->sqlesc ($username) . ' OR u.username LIKE ' . $db->sqlesc ('%' . $username . '%') . ') ';
      $link = 'username=' . htmlspecialchars_uni ($username) . '&amp;do=search_user&amp;';
    }
  }

  ($query = $db->sql_query ('SELECT u.*, g.namestyle, g.title 
  FROM users u 
  LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
  WHERE u.usergroup =3  AND g.cansettingspanel=\'no\' AND g.canstaffpanel=\'no\' AND issupermod=\'no\'' . $where));
  $count = $db->num_rows ($query);
  
  $threadcount = $count;

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


$page_url = $_this_script_ . '&amp;' . $link;
$multipage = multipage($threadcount, $perpage, $page, $page_url);
  
  
  
  
  
  
  $sortby = 'u.username';
  $type = ($_GET['type'] == 'DESC' ? 'ASC' : 'DESC');
  $allowed = array ('username', 'seedbonus', 'invites');
  if (((isset ($_GET['sortby']) AND !empty ($_GET['sortby'])) AND in_array ($_GET['sortby'], $allowed)))
  {
    $sortby = 'u.' . $_GET['sortby'];
  }

  stdhead ('Manage VIP Accounts (Total ' . ts_nf ($count) . ' VIP Accounts found)');
  
  
  
  
  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Search User
	</div>
	 </div>
		</div>
  
  ';
  
  
  
  echo '
  <div class="container mt-3">
<form method="post" action="' . $_this_script_ . '" name="search_user">
<input type="hidden" name="do" value="search_user">
<tr>
	<td>Username: <label><input type="text" class="form-control" name="username" value="' . htmlspecialchars_uni ($username) . '"></label> <input type="submit" class="btn btn-primary" value="search user"></td>
</tr>
</form>
</div>
';
  
  echo '<br />
  <div class="container mt-3">
	'.$multipage.'
</div>';

 
 
 echo '<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Manage VIP Accounts (Total ' . ts_nf ($count) . ' VIP Accounts found)
	</div>
	 </div>
		</div>';
 
 
  echo '
<form method="post" action="' . $_this_script_ . '" name="update">
<input type="hidden" name="do" value="update">
<input type="hidden" name="page" value="' . intval ($_GET['page']) . '">




<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Username</th>
        <th>Vip Until</th>
        <th>Donated</th>
		<th>Total Donated</th>
        <th>Points</th>
        <th>Invites</th>
		<td align="center" class="subheader"><input class="form-check-input" type="checkbox" checkall="group" onclick="javascript: return select_deselectAll (\'update\', this, \'group\');"></td>
      </tr>
    </thead>';
	
	
	
  ($query = $db->sql_query ('SELECT u.*, g.namestyle, g.title 
  FROM users u 
  LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
  WHERE u.usergroup = 3
  AND g.cansettingspanel=\'no\' 
  AND g.canstaffpanel=\'no\' 
  AND issupermod=\'no\'' . $where . ' ORDER by ' . $sortby . ' ' . $type . ' LIMIT '.$start.', ' . $perpage . ''));
  
  
  $lang->load ('tsf_forums');
  require_once INC_PATH . '/functions_mkprettytime.php';
  
  while ($vip = $db->fetch_array ($query))
  {
    $lastseen = my_datee ($dateformat, $vip['lastactive']) . ' ' . my_datee ($timeformat, $vip['lastactive']);
    $downloaded = mksize ($vip['downloaded']);
    $uploaded = mksize ($vip['uploaded']);
    $ratio = get_user_ratio ($vip['uploaded'], $vip['downloaded']);
    $ratio = str_replace ('\'', '\\\'', $ratio);
    $tooltip = '<b>' . $lang->tsf_forums['jdate'] . '</b>' . my_datee ($dateformat, $vip['added']) . '<br />' . sprintf ($lang->tsf_forums['tooltip'], $lastseen, $downloaded, $uploaded, $ratio);
    $username = get_user_color ($vip['username'], $vip['namestyle']);
    //$vipuntil = ($vip['donoruntil'] != '0000-00-00 00:00:00' ? my_datee ($dateformat, $vip['donoruntil']) . ' ' . my_datee ($timeformat, $vip['donoruntil']) . ' <br />' . mkprettytime (strtotime ($vip['donoruntil']) - gmtime ()) . ' left' : '<b><font color="red">Unlimited</font></b>');
    //$donated = $vip['donated'];
    //$total_donated = $vip['total_donated'];
    echo '
	<tr>
		<td><a href="' . $BASEURL . '/'.get_profile_link($vip['id']) . '" target="_blank" onmouseover="ddrivetip(\'' . $tooltip . '\', 200)"; onmouseout="hideddrivetip()">' . format_name($vip['username'], $vip['usergroup']) . '</a></td>
		<td align="left">' . $vipuntil . '</td>
		<td align="center">' . $donated . '</td>
		<td align="center">' . $total_donated . '</td>
		<td align="center">' . $vip['seedbonus'] . '</td>
		<td align="center">' . $vip['invites'] . '</td>
		<td align="center"><input class="form-check-input" type="checkbox" name="userids[]" value="' . $vip['id'] . '" checkme="group"></td>
	</tr>
	';
  }

  echo '
<tr>
	<td colspan="7" align="right">	
	Amount: <label><input type="text" class="form-control" value="" size="5" name="limit"></label> 
	<label>
	<select class="form-select form-select-sm border pe-5 w-auto" name="add">
		<option value="donoruntil">Add Extra Donor Time (weeks)</option>
		<option value="seedbonus">Give Extra Karma Points</option>
		<option value="invites">Give Extra Invites</option> 
	</select>
	</label>
	<input type="submit" class="btn btn-primary" value="update selected accounts" class=button>
	</td>
</tr>
';
  //_form_header_close_ ();
  
  
   echo '</table></div></div>';
  
  
  
  
  
  echo '
</form>
<div class="container mt-3">
	'.$multipage.'
</div>';

  stdfoot ();
?>
