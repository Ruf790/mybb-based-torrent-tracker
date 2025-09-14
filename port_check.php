<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function checkconnect ($ip = '', $port = '', $timeout = 5)
  {
    return (!$sockres = @fsockopen ($ip, $port, $errno, $errstr, $timeout) ? false : (@fclose ($sockres) ? true : true));
  }

  require_once 'global.php';
  gzip ();
  maxsysop ();
  loggedinorreturn ();
  
  $lang->load ('port_check');
  define ('CC_VERSION', '0.2');
  $message = false;
  if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
  {
    $ip_address = @long2ip (@ip2long (@getip ()));
    $port = @intval ($_POST['port']);
    if (($ip_address AND $port))
    {
      if (!$result = checkconnect ($ip_address, $port))
      {
        $message = sprintf ($lang->port_check['bad'], $port);
      }
      else
      {
        $message = sprintf ($lang->port_check['good'], $port);
      }
    }
  }

  stdhead ($lang->port_check['head']);
  $str = '
<table width="100%" border="0" class="tborder" cellpadding="5" cellspacing="0">
	<tr>
		<td class="thead" colspan="2" align="center">' . $lang->port_check['head'] . '</td>
	</tr>
';
  if (!$message)
  {
    $str .= '
		<script type="text/javascript">
			function show_message()
			{
				document.getElementById(\'message1\').style.display = \'none\';
				document.getElementById(\'message2\').style.display = \'block\';
			}
		</script>
		<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
		<tr>
			<td align="right" width="20%">' . $lang->port_check['field1'] . '</td>
			<td align="left" width="80%">
				<div id="message1" style="display: block;">
				<input type="text" class="textbox" name="port" size="5" value="' . (isset ($port) ? $port : '') . '"> <input type="submit" class="button" value="' . $lang->port_check['field2'] . '" onclick="javascript:show_message()">
				' . $lang->port_check['title'] . '</div>
				<div id="message2" style="display: none;"><img src="' . $BASEURL . '/pic/spinner.gif" class="inlineimg"> ' . $lang->port_check['checking'] . '</div>
			</td>
		</tr>
		</form>
	';
  }
  else
  {
    $str .= '
		<tr>
		<td colspan="2" align="left">' . $message . '</td>
	</tr>
	';
  }

  $str .= '
</table>
';
  echo $str;
  stdfoot ();
?>
