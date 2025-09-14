<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/



  function show_helptip ($text, $title = 'Quick Help', $width = 500, $style = 'style=" float: right;"')
  {
    global $BASEURL;
    global $pic_base_url;
    return '
	<span align="justify"' . $style . '>
	<a href="javascript:void(0);" onmouseover="Tip(\'' . $text . '\', WIDTH, ' . $width . ', TITLE, \'' . $title . '\', SHADOW, false, FADEIN, 300, FADEOUT, 300, STICKY, 1, OFFSETX, -20, CLOSEBTN, true, CLICKCLOSE, true)"><img src="' . $BASEURL . '/' . $pic_base_url . 'help_on.gif" border="0" style="vertical-align: middle;"></a></span>';
  }

  function print_submit_rows ($colspan = 2, $class = 'button')
  {
    echo '
	<td class="tdclass1" align="center" valign="top" width="100%" colspan="' . $colspan . '"><input type="submit" value="save" class="' . $class . '"> <input type="reset" value="reset" class="' . $class . '"></td>
	';
  }

  function print_row_header ($text, $colspan = 2)
  {
    echo '
	<tr>
		<td colspan="' . $colspan . '" align="center" class="thead">' . $text . '</td>
	</tr>
	';
  }

  function print_rows ($a, $b, $width1 = '20%', $width2 = '80%', $class1 = 'trow2', $class2 = 'tdclass2', $align1 = 'left', $align2 = 'left', $valign = 'top')
  {
    $output = '
	<tr>
	';
    if ((is_array ($a) AND is_array ($b)))
    {
      $array = array_combine ($a, $b);
      foreach ($array as $left => $right)
      {
        $output .= '
			<td align="' . $align1 . '" width="' . $width1 . '" class="' . $class1 . '" valign="' . $valign . '">' . $left . '</td>
			<td align="' . $align2 . '" width="' . $width2 . '" class="' . $class2 . '" valign="' . $valign . '">' . $right . '</td>
			';
      }
    }
    else
    {
      $output .= '
			<td align="' . $align1 . '" width="' . iif ($b, $width1, '100%') . '" class="' . $class1 . '" valign="' . $valign . '">' . $a . '</td>
			' . iif ($b, '<td align="' . $align2 . '" width="' . $width2 . '" class="' . $class2 . '" valign="' . $valign . '">' . $b . '</td>') . '
			';
    }

    $output .= '
	</tr>';
    echo $output;
  }

  function table_open ($title = '', $tr = true, $colspan = 2, $class = 'thead', $border = 0, $width = '100%', $height = '', $align = 'center', $cellpadding = 5, $cellspacing = 0)
  {
    echo '
	<table width="' . $width . '"' . iif ($height, ' height="' . $height . '"') . ' cellpadding="' . $cellpadding . '" cellspacing="' . $cellspacing . '" border="' . $border . '">
		' . iif ($tr, '
		<tr>
			<td class="' . $class . '" align="' . $align . '" colspan="' . $colspan . '"><strong>' . $title . '</strong></td>
		</tr>') . '
	';
  }

  function table_close ($br = true)
  {
    echo '
	</table>
	' . iif ($br, '<br />') . '
	';
  }

  function construct_select_options ($array, $selectedid = '', $htmlise = false)
  {
    if (is_array ($array))
    {
      $options = '';
      foreach ($array as $key => $val)
      {
        if (is_array ($val))
        {
          $options .= '		<optgroup label="' . iif ($htmlise, htmlspecialchars_uni ($key), $key) . '">
';
          $options .= construct_select_options ($val, $selectedid, $tabindex, $htmlise);
          $options .= '		</optgroup>
';
          continue;
        }
        else
        {
          if (is_array ($selectedid))
          {
            $selected = iif (in_array ($key, $selectedid), ' selected="selected"', '');
          }
          else
          {
            $selected = iif ($key == $selectedid, ' selected="selected"', '');
          }

          $options .= '		<option value="' . iif ($key !== 'no_value', $key) . (('' . '"') . $selected . '>') . iif ($htmlise, htmlspecialchars_uni ($val), $val) . '</option>
';
          continue;
        }
      }
    }

    return $options;
  }

  function open_form ($configname)
  {
    echo '
	<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '?do=' . $configname . '&sessionhash=' . session_id () . '&tshash=' . $_SESSION['hash'] . '" name="' . strtolower ($configname) . '">
	<input type="hidden" name="configname" value="' . strtoupper ($configname) . '">
	<input type="hidden" name="do" value="' . $configname . '">';
  }

  function close_form ()
  {
    echo '
	</form>
	';
  }

  function save_settings ($do = '', $log = true, $writemsg = true)
  {
    global $_POST;
    global $CURUSER;
    global $BASEURL;
    global $rootpath;
    global $cache;
	global $db;
    if (@preg_match ('#^DATET+#', $_POST['configname']))
    {
      $CFGname = 'DATETIME';
    }
    else
    {
      $CFGname = strtoupper ($_POST['configname']);
    }

    if ($CFGname == 'STAFFTEAM')
    {
      $STAFFTEAM = array ();
      $_Query = $db->sql_query ('SELECT u.id, u.username, g.gid FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.enabled = \'yes\' AND (g.cansettingspanel = \'1\' OR g.issupermod = \'1\' OR g.canstaffpanel = \'1\')');
      if (0 < $db->num_rows ($_Query))
      {
        while ($Buffer = mysqli_fetch_assoc ($_Query))
        {
          $STAFFTEAM[$Buffer['id']][$Buffer['username']] = $Buffer['gid'];
        }

        $i = 0;
        while ($i < count ($_POST['staffnames']))
        {
          if (($_POST['staffids'][$i] AND $_POST['staffnames'][$i]))
          {
            if (isset ($STAFFTEAM[$_POST['staffids'][$i]][$_POST['staffnames'][$i]]))
            {
              $save[] = $_POST['staffnames'][$i] . ':' . $_POST['staffids'][$i];
            }
            else
            {
              admin_cp_critical_error ('Invalid Username of Userid Entered. ' . htmlspecialchars_uni ($_POST['staffnames'][$i] . ' : ' . $_POST['staffids'][$i]));
            }
          }

          ++$i;
        }

        $staffteam = @implode (',', $save);
        $filename = CONFIG_DIR . '/STAFFTEAM';
        if (is_writable ($filename))
        {
          if (!$handle = fopen ($filename, 'w'))
          {
            admin_cp_critical_error ('' . 'Cannot open file (' . $filename . ')');
          }

          if (fwrite ($handle, $staffteam) === FALSE)
          {
            admin_cp_critical_error ('' . 'Cannot write to file (' . $filename . ')');
          }

          fclose ($handle);
        }
        else
        {
          admin_cp_critical_error ('' . 'The file ' . $filename . ' is not writable!');
        }
      }
      else
      {
        admin_cp_critical_error ('There is no any Staff Member.');
      }
    }
    else
    {
      if ($CFGname == 'FREELEECH')
      {
        $_START = $_POST['configoption']['start'];
        $_END = $_POST['configoption']['end'];
        $_FLSTYPE = $_POST['configoption']['system'];
        $_filename = TSDIR . '/cache/freeleech.php';
        $_cachefile = @fopen ('' . $_filename, 'w');
        $_cachecontents = '<?php
/** TS Generated Cache#10 - Do Not Alter
 * Cache Name: FreeLeech
 * Generated: ' . gmdate ('r') . '
*/
';
        $_cachecontents .= '$__FLSTYPE = \'' . $_FLSTYPE . '\';
';
        $_cachecontents .= '$__F_START = \'' . $_START . '\';
';
        $_cachecontents .= '$__F_END = \'' . $_END . '\';
?>';
        @fwrite ($_cachefile, $_cachecontents);
        @fclose ($_cachefile);
      }
      else
      {
        if ($CFGname == 'PINCODE')
        {
          $pincode1 = trim ($_POST['configoption']['pincode1']);
          $re_pincode1 = trim ($_POST['configoption']['re_pincode1']);
          $pincode2 = trim ($_POST['configoption']['pincode2']);
          $re_pincode2 = trim ($_POST['configoption']['re_pincode2']);
          $sechash = md5 ($SITENAME);
          if (((!empty ($pincode1) AND !empty ($re_pincode1)) AND $pincode1 === $re_pincode1))
          {
            $pincode_s = md5 (md5 ($sechash) . md5 ($pincode1));
            $get_s = $db->sql_query ('SELECT * FROM pincode WHERE area = 1 LIMIT 1');
            if (1 <= $db->num_rows ($get_s))
            {
              $db->sql_query ('UPDATE pincode SET pincode = ' . sqlesc ($pincode_s) . ', sechash = ' . sqlesc ($sechash) . ', area = 1 WHERE area = 1 LIMIT 1');
            }
            else
            {
              $db->sql_query ('INSERT INTO pincode (pincode,sechash,area) VALUES (' . sqlesc ($pincode_s) . ', ' . sqlesc ($sechash) . ', 1)');
            }
          }

          if (((!empty ($pincode2) AND !empty ($re_pincode2)) AND $pincode2 === $re_pincode2))
          {
            $pincode_d = md5 (md5 ($sechash) . md5 ($pincode2));
            $get_d = $db->sql_query ('SELECT * FROM pincode WHERE area = 2 LIMIT 1');
            if (1 <= $db->num_rows ($get_d))
            {
              $db->sql_query ('UPDATE pincode SET pincode = ' . sqlesc ($pincode_d) . ', sechash = ' . sqlesc ($sechash) . ', area = 2 WHERE area = 2 LIMIT 1');
            }
            else
            {
              $db->sql_query ('INSERT INTO pincode (pincode,sechash,area) VALUES (' . sqlesc ($pincode_d) . ', ' . sqlesc ($sechash) . ', 2)');
            }
          }
        }
        else
        {
         

          if ($CFGname == 'HITRUN')
          {
            $_POST['configoption']['MinFinishDate'] = strtotime ($_POST['configoption']['MinFinishDate']);
            if (is_array ($_POST['usergroup']))
            {
              $_value = array ();
              foreach ($_POST['usergroup'] as $ugid)
              {
                $_value[] = $ugid;
              }

              $_POST['configoption']['HRSkipUsergroups'] = implode (',', $_value);
            }
          }


          $array = array ();
          foreach ($_POST['configoption'] as $configoption => $configvalue)
          {
            $array['' . $configoption] = $configvalue;
			
			
			 //$updated_setting = array(
				//"name" => $db->escape_string($configoption),
				//"value" => $db->escape_string($configvalue)
			//);
			//$db->insert_query("settings", $updated_setting);
			$db->update_query("settings", array('value' => $configvalue), "name='".$db->escape_string($configoption)."'");
		    rebuild_settings();
			
			
          }

          //require_once INC_PATH . '/functions_writeconfig.php';
          //writeconfig ($CFGname, $array);
		  $cache->update($CFGname, $array);
          //rebuild_config_settings ();
 
        }
      }
    }

    $msg = $CFGname . ' Settings has been saved!';
    if ($log)
    {
      write_log ('' . $msg . ' (' . $CURUSER['username'] . ')');
    }

    if ($writemsg)
    {
      admin_cp_redirect ($do, $msg);
      exit ();
    }

  }

  function admin_cp_redirect ($do = '', $msg = '', $extra = '', $delay = '3000')
  {
    global $BASEURL;
    echo '
		<script type="text/javascript">
		<!--
			function delayer()
			{
				window.location = "' . $BASEURL . '/admin/managesettings.php?do=' . $do . ($extra ? '&' . $extra : '') . '&sessionhash=' . session_id () . '&tshash=' . $_SESSION['hash'] . '"
			}
			setTimeout("delayer()", ' . $delay . ')
		//-->
		</script>
		<div class="bluediv"><font color="red"><strong>' . $msg . '</strong></font><br /><br />
		Redirecting....</div>
		';
  }

  function admin_cp_header ($headarea = '', $title = 'TS SE Admin Control Panel', $target = '<base target="main" />', $body = ' class="yui-skin-sam"')
  {
    echo '
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html dir="ltr" lang="en">
		<head>
			<title>' . $title . '</title>
			<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
			<link rel="stylesheet" type="text/css" href="./templates/default.css" />
			
			' . $target . '
			' . $headarea . '
		</head>
		<body' . $body . '>
	';
  }

  function admin_cp_footer ($showcopyright = false)
  {
    echo ($showcopyright ? '
		<div class="tdclass2" align="center" style="border: 2px solid rgb(131, 168, 204);">Powered by Tracker' . O_VERSION . ' © 2006-' . date ('Y') . '</div><br />' : '') . '
		</body>
	</html>
	';
  }

  function admin_collapse ($id, $type = 1)
  {
    global $BASEURL;
    global $pic_base_url;
    global $tscollapse;
    if ($type === 1)
    {
      return '<span style="float: right;"><img id="collapseimg_' . $id . '" src="' . $BASEURL . '/' . $pic_base_url . 'minus' . $tscollapse['collapseimg_' . $id . ''] . '.gif" alt="" border="0" onclick="return toggle_collapse(\'' . $id . '\')" /></span>';
    }

    if ($type === 2)
    {
      return '<tbody id="collapseobj_' . $id . '" style="' . ($tscollapse['' . 'collapseobj_' . $id] ? $tscollapse['' . 'collapseobj_' . $id] : 'none') . '">';
    }

  }

  function make_nav_menu ($first, $second, $islink = false)
  {
    global $db;
	echo '
	<tr>
		<td class="thead">
			' . (!$islink ? frame_link ($first, $second) : $islink) . '
		</td>
	</tr>
	';
  }

  function frame_link ($do, $dotext)
  {
     global $db;
	return '
	<span class="smallfont">
		<a href="managesettings.php?do=' . $do . '&amp;sessionhash=' . session_id () . '&amp;tshash=' . $_SESSION['hash'] . '">' . $dotext . '</a>
	</span>
	';
  }

  function admin_cp_critical_error ($text)
  {
    admin_cp_header ();
    echo '
	<div class="bluediv"><font color="red">' . $text . '</font></div>
	';
    admin_cp_footer (true);
    exit ();
  }

  function calc_cron_time ($stamp)
  {
    $ysecs = 365 * 24 * 60 * 60;
    $mosecs = 31 * 24 * 60 * 60;
    $wsecs = 7 * 24 * 60 * 60;
    $dsecs = 24 * 60 * 60;
    $hsecs = 60 * 60;
    $msecs = 60;
    $years = floor ($stamp / $ysecs);
    $stamp %= $ysecs;
    $months = floor ($stamp / $mosecs);
    $stamp %= $mosecs;
    $weeks = floor ($stamp / $wsecs);
    $stamp %= $wsecs;
    $days = floor ($stamp / $dsecs);
    $stamp %= $dsecs;
    $hours = floor ($stamp / $hsecs);
    $stamp %= $hsecs;
    $minutes = floor ($stamp / $msecs);
    $stamp %= $msecs;
    $seconds = $stamp;
    return array ('years' => $years, 'months' => $months, 'weeks' => $weeks, 'days' => $days, 'hours' => $hours, 'minutes' => $minutes);
  }

  function iif ($expression, $returntrue, $returnfalse = '')
  {
    return ($expression ? $returntrue : $returnfalse);
  }

  function get_count ($name, $where = '', $extra = '')
  {
    global $db;
	($res = $db->sql_query ('SELECT COUNT(*) as ' . $name . ' FROM ' . $where . ' ' . ($extra ? $extra : '')));
    list ($info[$name]) = mysqli_fetch_array ($res);
    return $info[$name];
  }


  if ((@array_key_exists ('show_frame_logo', $_GET) AND $_GET['show_frame_logo'] == 1))
  {
    show_frame_logo ();
    exit ();
  }

  if (!defined ('SETTING_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

 
  @define ('TEMPLATESHARES', 'oops');
  if (!function_exists ('array_combine'))
  {
    function array_combine ($arr1, $arr2)
    {
      $out = array ();
      $arr1 = array_values ($arr1);
      $arr2 = array_values ($arr2);
      foreach ($arr1 as $key1 => $value1)
      {
        $out[(string)$value1] = $arr2[$key1];
      }

      return $out;
    }
  }

?>
