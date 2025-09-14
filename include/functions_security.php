<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/
  
  function remaining($type = "login")
  {
    global $maxloginattempts, $db;
    $Query = $db->sql_query("SELECT attempts FROM loginattempts WHERE ip=" . $db->sqlesc(USERIPADDRESS) . " LIMIT 1");
    $Result = mysqli_fetch_assoc($Query);
    $total = 0 < $db->num_rows($Query) ? intval($Result["attempts"]) : 0;
    $left = $maxloginattempts - $total;
    return $left <= 2 ? "<font color=\"#f90510\">[" . $left . "]</font>" : "<font color=\"#037621\">[" . $left . "]</font>";
  }

  function failedloginscheck ($type = 'Login')
  {
    global $maxloginattempts;
    global $BASEURL;
    global $ip;
    global $lang;
	global $db;
    if (!$ip)
    {
      $ip = get_ip ();
    }

    $Query = $db->sql_query ("SELECT attempts FROM loginattempts WHERE ip='" . $db->escape_string($ip) . "' LIMIT 1");
    $total = (0 < $db->num_rows ($Query) ? intval ($db->fetch_field ($Query, 'attempts')) : 0);
	

    if ($maxloginattempts <= $total)
    {
      $db->sql_query ('UPDATE loginattempts SET banned = \'yes\' WHERE ip=' . $db->sqlesc ($ip));
      stderr (sprintf ($lang->global['xlocked'], $type), sprintf ($lang->global['xlocked2'], '<a href="' . $BASEURL . '/unbaniprequest.php">', '<a href="' . $BASEURL . '/contactus.php">'), false);
    }

  }

  function failedlogins ($type = 'login', $recover = false, $head = true, $msg = false, $uid = 0)
  {
    global $BASEURL;
    global $ip;
    global $lang;
    global $username;
    global $password;
    global $md5pw;
    global $iphost;
    global $ipaddress;
	global $db;
    if (!$ip)
    {
      $ip = get_ip ();
    }

    $added = TIMENOW;
    $a = mysqli_fetch_row($db->sql_query ('SELECT COUNT(*) FROM loginattempts WHERE ip=' . $db->sqlesc ($ip) . ' LIMIT 0,1'));
    if ($a[0] == 0)
    {
      $db->sql_query ('INSERT INTO loginattempts (ip, added, attempts) VALUES (' . $db->sqlesc ($ip) . ('' . ', ' . $added . ', 1)'));
    }
    else
    {
      $db->sql_query ('UPDATE loginattempts SET attempts = attempts + 1 WHERE ip=' . $db->sqlesc ($ip));
    }

    if ($recover)
    {
      $db->sql_query ('UPDATE loginattempts SET type = \'recover\' WHERE ip = ' . $db->sqlesc ($ip));
    }

    if (($msg AND $uid))
    {
      
	  require_once INC_PATH . '/functions_pm.php';
            
	  $pm = array(
		 'subject' => sprintf ($lang->global['warning']),
		 'message' => sprintf ($lang->global['accountwarn'], $username, $password, $md5pw, $ipaddress, $iphost),
		 'touid' => $uid
	  );
	  
	  $pm['sender']['uid'] = -1;
	  send_pm($pm, -1, true);
	  
    }

    if (($type == 'silent' OR $type == 'login'))
    {
      return null;
    }

    stderr ($lang->global['error'], $type, false, $head);
  }

  if (!defined ('IN_TRACKER'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>
