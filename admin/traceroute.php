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

  stdhead ();
  if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
  {
    $windows = 1;
    $unix = 0;
  }
  else
  {
    $windows = 0;
    $unix = 1;
  }

  $register_globals = (bool)ini_get ('register_gobals');
  $system = ini_get ('system');
  $unix = (bool)$unix;
  $win = (bool)$windows;
  if ($register_globals)
  {
    $ip = getenv (REMOTE_ADDR);
    $self = $PHP_SELF;
  }
  else
  {
    $action = $_POST['action'];
    $host = $_POST['host'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $self = $_SERVER['SCRIPT_NAME'];
  }

  if ($action == 'do')
  {
    $host = preg_replace ('/[^A-Za-z0-9.]/', '', $host);
    echo '<div class=error>';
    echo 'Trace Output:<br />';
    echo '<pre>';
    if ($unix)
    {
      system ('' . 'traceroute ' . $host);
      system ('killall -q traceroute');
    }
    else
    {
      system ('' . 'tracert ' . $host);
    }

    echo '</pre>';
    echo 'done ...</div>';
  }
  else
  {
    echo '<body bgcolor="#FFFFFF" text="#000000"></body>';
    echo '<p><font size="2">Your IP is: ' . $ip . '</font></p>';
    echo '<form method="post" action="' . $_this_script_ . '">';
    echo ' Enter IP or Host <input type="text" id=specialboxn name="host" value="' . $ip . '">';
    echo ' <input type=hidden name=action value=do><input type="submit" value="Traceroute!" class=button>';
    echo '</form>';
    echo '<br /><b>' . $system . '</b>';
    echo '</body></html>';
  }

  stdfoot ();
?>
