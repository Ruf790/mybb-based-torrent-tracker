<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function _e ($head = '', $text = '')
  {
    echo $head;
  }

  function __ ($head = '', $text = '')
  {
    echo $head;
  }

  function display_serverinfo ()
  {
    global $charset;
    global $BASEURL;
    global $defaulttemplate;
    global $SITENAME;
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" />
<head>
<meta http-equiv="Content-Type" content="text/html; charset=';
    echo $charset;
    echo '" />
<title>';
    echo $SITENAME;
    echo ' - Server Info v1.0 by xam</title>
	';
    echo '<s';
    echo 'tyle type="text/css">
	body {
	font-size: 62.5%;
	font-family: \'Lucida Grande\', Verdana, Arial, Sans-Serif;	
	color: #333;
	text-align: left;
	}
	tr.v td, tr.out
	{
		background-color: #F1F1F1;
	}
	tr.Over
	{
		background: #e5f3ff;
	}
	tr.h th
	{
		font-weight: bold;
		background: #DFDFDF;
	}
	#MYSQLinfo, #PHPinfo {
		display: none;
	}
	</style>
	';
    echo '<s';
    echo 'cript type="text/javascript">
		function toggle_general() {
		document.getElementById(\'GeneralOverview\').style.display = "block";
		document.getElementById(\'PHPinfo\').style.display = "none";
		document.getElementById(\'MYSQLinfo\').style.display = "none";
	}

	// Display PHP Information
	function toggle_php() {
		document.getElementById(\'GeneralOverview\').style.display = "none";
		document';
    echo '.getElementById(\'PHPinfo\').style.display = "block";
		document.getElementById(\'MYSQLinfo\').style.display = "none";
	}

	// Display MYSQL Information
	function toggle_mysql() {
		document.getElementById(\'GeneralOverview\').style.display = "none";
		document.getElementById(\'PHPinfo\').style.display = "none";
		document.getElementById(\'MYSQLinfo\').style.display = "block";
	}
	</script>
	';
    serverinfo_subnavi ();
    get_generalinfo ();
    get_phpinfo ();
    get_mysqlinfo ();
  }

  function get_generalinfo ()
  {
    global $db, $config;
    $query =$db->sql_query ('SELECT VERSION() as version');
    $sqlversion = mysqli_fetch_array ($query);
    $sqlversion = $sqlversion['version'];
    if ('3.23' <= $sqlversion)
    {
      $data_usage = '';
      $index_usage = '';
      $query =$db->sql_query ('' . 'SHOW TABLE STATUS FROM ' . $config['database']['database']);
      while ($res = mysqli_fetch_array ($query))
      {
        $data_usage += $res['Data_length'];
        $index_usage += $res['Index_length'];
      }

      if (!$data_usage)
      {
        $data_usage = __ ('N/A');
      }

      if (!$index_usage)
      {
        $index_usage = __ ('N/A');
      }
    }
    else
    {
      $data_usage = __ ('N/A');
      $index_usage = __ ('N/A');
    }

    $packet_max_query = mysqli_fetch_array ($db->sql_query('SHOW VARIABLES LIKE \'max_allowed_packet\''));
    $packet_max = $packet_max_query['Value'];
    if (!$packet_max)
    {
      $packet_max = __ ('N/A');
    }

    $connection_max_query = mysqli_fetch_array ($db->sql_query('SHOW VARIABLES LIKE \'max_connections\''));
    $connection_max = $connection_max_query['Value'];
    if (!$connection_max)
    {
      $connection_max = __ ('N/A');
    }

    if (ini_get ('short_open_tag'))
    {
      $short_tag = 'On';
    }
    else
    {
      $short_tag = 'Off';
    }

    if (ini_get ('safe_mode'))
    {
      $safe_mode = 'On';
    }
    else
    {
      $safe_mode = 'Off';
    }

    if (get_magic_quotes_gpc ())
    {
      $magic_quotes_gpc = 'On';
    }
    else
    {
      $magic_quotes_gpc = 'Off';
    }

    if (ini_get ('upload_max_filesize'))
    {
      $upload_max = ini_get ('upload_max_filesize');
    }
    else
    {
      $upload_max = __ ('N/A');
    }

    if (ini_get ('post_max_size'))
    {
      $post_max = ini_get ('post_max_size');
    }
    else
    {
      $post_max = __ ('N/A');
    }

    if (ini_get ('max_execution_time'))
    {
      $max_execute = ini_get ('max_execution_time');
    }
    else
    {
      $max_execute = 'N/A';
    }

    if (ini_get ('memory_limit'))
    {
      $memory_limit = ini_get ('memory_limit');
    }
    else
    {
      $memory_limit = __ ('N/A');
    }

    if (function_exists ('gd_info'))
    {
      $gd = gd_info ();
      $gd = $gd['GD Version'];
    }
    else
    {
      ob_start ();
      phpinfo (8);
      $phpinfo = ob_get_contents ();
      ob_end_clean ();
      $phpinfo = strip_tags ($phpinfo);
      $phpinfo = stristr ($phpinfo, 'gd version');
      $phpinfo = stristr ($phpinfo, 'version');
      $gd = substr ($phpinfo, 0, strpos ($phpinfo, '
'));
    }

    if (empty ($gd))
    {
      $gd = __ ('N/A');
    }

    echo '	<div class="wrap" id="GeneralOverview">
		<h2>';
    _e ('General Overview');
    echo '</h2>
		<table width="100%"  border="0" cellspacing="0" cellpadding="3">
			<tr class="h">
				<th>';
    _e ('Variable Name');
    echo '</th>
				<th>';
    _e ('Value');
    echo '</th>
				<th>';
    _e ('Variable Name');
    echo '</th>
				<th>';
    _e ('Value');
    echo '</th>
			</tr>
			<tr>
				<td>';
    _e ('OS');
    echo '</td>
				<td>';
    echo PHP_OS;
    echo '</td>
				<td>';
    _e ('Database Data Disk Usage');
    echo '</td>
				<td>';
    echo format_size ($data_usage);
    echo '</td>
			</tr>
			<tr class="alternate">
				<td>';
    _e ('Server');
    echo '</td>
				<td>';
    echo $_SERVER['SERVER_SOFTWARE'];
    echo '</td>
				<td>';
    _e ('Database Index Disk Usage');
    echo '</td>
				<td>';
    echo format_size ($index_usage);
    echo '</td>
			</tr>
			<tr>
				<td>PHP</td>
				<td>v';
    echo PHP_VERSION;
    echo '</td>
				<td>';
    _e ('MYSQL Maximum Packet Size');
    echo '</td>
				<td>';
    echo format_size ($packet_max);
    echo '</td>
			</tr>
			<tr class="alternate">
				<td>MYSQL</td>
				<td>v';
    echo $sqlversion;
    echo '</td>
				<td>';
    _e ('MYSQL Maximum No. Connection');
    echo '</td>
				<td>';
    echo number_format ($connection_max);
    echo '</td>
			</tr>
			<tr>
				<td>GD</td>
				<td>';
    echo $gd;
    echo '</td>
				<td>';
    _e ('PHP Short Tag');
    echo '</td>
				<td>';
    echo $short_tag;
    echo '</td>
			</tr>
			<tr class="alternate">
				<td>';
    _e ('Server Hostname');
    echo '</td>
				<td>';
    echo $_SERVER['SERVER_NAME'];
    echo '</td>
				<td>';
    _e ('PHP Safe Mode');
    echo '</td>
				<td>';
    echo $safe_mode;
    echo '</td>
			</tr>
			<tr>
				<td>';
    _e ('Server IP:Port');
    echo '</td>
				<td>';
    echo $_SERVER['SERVER_ADDR'];
    echo ':';
    echo $_SERVER['SERVER_PORT'];
    echo '</td>
				<td>';
    _e ('PHP Magic Quotes GPC');
    echo '</td>
				<td>';
    echo $magic_quotes_gpc;
    echo '</td>
			</tr>
			<tr class="alternate">
				<td>';
    _e ('Server Document Root');
    echo '</td>
				<td>';
    echo $_SERVER['DOCUMENT_ROOT'];
    echo '</td>
				<td>';
    _e ('PHP Memory Limit');
    echo '</td>
				<td>';
    echo $memory_limit;
    echo '</td>
			</tr>
			<tr>
				<td>';
    _e ('Server Admin');
    echo '</td>
				<td>';
    echo $_SERVER['SERVER_ADMIN'];
    echo '</td>
				<td>';
    _e ('PHP Max Upload Size');
    echo '</td>
				<td>';
    echo $upload_max;
    echo '</td>
			</tr>
			<tr class="alternate">
				<td>';
    _e ('Server Load');
    echo '</td>
				<td>';
    echo get_serverload ();
    echo '</td>
				<td>';
    _e ('PHP Max Post Size');
    echo '</td>
				<td>';
    echo $post_max;
    echo '</td>
			</tr>
			<tr>
				<td>';
    _e ('Server Date/Time');
    echo '</td>
				<td>';
    echo date ('l, jS F Y, H:i');
    echo '</td>
				<td>';
    _e ('PHP Max Script Execute Time');
    echo '</td>
				<td>';
    echo $max_execute;
    echo 's</td>
			</tr>
		</table>
	</div>
';
  }

  function get_phpinfo ()
  {
    ob_start ();
    phpinfo ();
    $phpinfo = ob_get_contents ();
    ob_end_clean ();
    $phpinfo = strip_tags ($phpinfo, '<table><tr><th><td>');
    $phpinfo = eregi ('<table border="0" cellpadding="3" width="600">(.*)</table>', $phpinfo, $data);
    $phpinfo = $data[0];
    $phpinfo = preg_replace ('!<table border="0" cellpadding="3" width="600">
<tr class="h"><td>
(.*?)
</td></tr>
</table>!', '' . '<h2>$1</h2>', $phpinfo);
    $phpinfo = preg_replace ('!<\\/table>
(.*?)
<table border="0" cellpadding="3" width="600">!', '' . '</table>
<br />
<h2>$1</h2>
<table border="0" cellpadding="3" width="100%">', $phpinfo);
    $phpinfo = preg_replace ('!</table>
<table border="0" cellpadding="3" width="600">
<tr class="v"><td>

(.*?)</td></tr>
</table>!', '' . '<tr class="Out" onmouseover="this.className=\'Over\'" onmouseout="this.className=\'Out\'"><td colspan="2">$1</td></tr>
</table>', $phpinfo);
    $phpinfo = str_replace ('width="600"', 'width="100%"', $phpinfo);
    $phpinfo = str_replace ('<td class="e">', '<td>', $phpinfo);
    $phpinfo = str_replace ('<td class="v">', '<td>', $phpinfo);
    $phpinfo = str_replace ('PHP Credits', '', $phpinfo);
    $phpinfo = str_replace ('Configuration
PHP Core', '<br /><h2>PHP Core Configuration</h2>', $phpinfo);
    $phpinfo = str_replace ('<tr>', '<tr class="Out" onmouseover="this.className=\'Over\'" onmouseout="this.className=\'Out\'">', $phpinfo);
    echo '<div class="wrap" id="PHPinfo">' . '
';
    echo $phpinfo;
    echo '</div>' . '
';
  }

  function get_mysqlinfo ()
  {
    global $db;
	$query =$db->sql_query ('SELECT VERSION() AS version');
    $res = mysqli_fetch_array ($query);
    $sqlversion = $res['version'];
    $query2 =$db->sql_query ('SHOW VARIABLES');
    echo '<div class="wrap" id="MYSQLinfo">' . '
';
    echo '' . '<h2>MYSQL ' . $sqlversion . '</h2>
';
    if ($res)
    {
      echo '<table border="0" cellpadding="3" width="100%">' . '
';
      echo '<tr class="h"><th>Variable Name</th><th>Value</th></tr>' . '
';
      while ($info = mysqli_fetch_array ($query2))
      {
        echo '<tr class="Out" onmouseover="this.className=\'Over\'" onmouseout="this.className=\'Out\'"><td>' . $info['Variable_name'] . '</td><td>' . htmlspecialchars ($info['Value']) . '</td></tr>' . '
';
      }

      echo '</table>' . '
';
    }

    echo '</div>' . '
';
  }

  function serverinfo_subnavi ()
  {
    echo '	<div class="wrap" style="text-align: center">
		<a href="#DisplayGeneral" onclick="toggle_general(); return false;">';
    _e ('<font color=red>Display General Overview</font>');
    echo '</a> - <a href="#DisplayPHP" onclick="toggle_php(); return false;">';
    _e ('<font color=red>Display PHP Information</font>');
    echo '</a> - <a href="#DisplayMYSQL" onclick="toggle_mysql(); return false;">';
    _e ('<font color=red>Display MYSQL Information</font>');
    echo '</a>
	</div>
';
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }
  else
  {
    if (!defined ('STAFF_PANEL_TSSEv56'))
    {
      exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Please install TS SE v5.6 First!.</font>');
    }
  }

  define ('SI_VERSION', '0.2 by xam');
  if (!function_exists ('format_size'))
  {
    function format_size ($rawSize)
    {
      if (1 < $rawSize / 1099511627776)
      {
        return round ($rawSize / 1099511627776, 1) . ' TB';
      }

      if (1 < $rawSize / 1073741824)
      {
        return round ($rawSize / 1073741824, 1) . ' GB';
      }

      if (1 < $rawSize / 1048576)
      {
        return round ($rawSize / 1048576, 1) . ' MB';
      }

      if (1 < $rawSize / 1024)
      {
        return round ($rawSize / 1024, 1) . ' KB';
      }

      return round ($rawSize, 1) . ' bytes';
    }
  }

  if (!function_exists ('get_serverload'))
  {
    function get_serverload ()
    {
      if ((PHP_OS != 'WINNT' AND PHP_OS != 'WIN32'))
      {
        if (file_exists ('/proc/loadavg'))
        {
          if ($fh = @fopen ('/proc/loadavg', 'r'))
          {
            $data = @fread ($fh, 6);
            @fclose ($fh);
            $load_avg = explode (' ', $data);
            $server_load = trim ($load_avg[0]);
          }
        }
        else
        {
          $data = @system ('uptime');
          preg_match ('/(.*):{1}(.*)/', $data, $matches);
          $load_arr = explode (',', $matches[2]);
          $server_load = trim ($load_arr[0]);
        }
      }

      if (!$server_load)
      {
        $server_load = __ ('N/A');
      }

      return $server_load;
    }
  }

  display_serverinfo ();
?>
