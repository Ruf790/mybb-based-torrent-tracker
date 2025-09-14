<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  $rootpath = './../';
  include $rootpath . '/global.php';
  gzip ();
  dbconn ();
  loggedinorreturn ();
  maxsysop ();
  parked ();
  define ('RP_VERSION', 'v.0.3 by xam');
  require_once 'include/global_config.php';
  if (($usergroups['cansettingspanel'] != 'yes' OR $config['reset_pincode'] != $CURUSER['username']))
  {
    exit ('Permission denied! You must have permission to access Setting panel and your username must be inside in the following configuration file: admin/include/global_config.php');
  }

  if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
  {
    $pincode1 = trim ($_POST['pincode1']);
    $pincode2 = trim ($_POST['pincode2']);
    $sechash = md5 ($SITENAME);
    $pincode_s = md5 (md5 ($sechash) . md5 ($pincode1));
    if ((((!empty ($pincode1) AND !empty ($pincode2)) AND $pincode1 === $pincode2) AND $area = intval ($_POST['area'])))
    {
      ($query = sql_query ('SELECT area FROM pincode WHERE pincode = ' . sqlesc ($pincode_s)) OR sqlerr (__FILE__, 24));
      if (0 < mysql_num_rows ($query))
      {
        $message = 'This pincode in use! Please choose another one!';
      }
      else
      {
        ($get_s = sql_query ('' . 'SELECT area FROM pincode WHERE area = ' . $area . ' LIMIT 1') OR sqlerr (__FILE__, 31));
        if (1 <= mysql_num_rows ($get_s))
        {
          (sql_query ('UPDATE pincode SET pincode = ' . sqlesc ($pincode_s) . ', sechash = ' . sqlesc ($sechash) . ('' . ', area = ' . $area . ' WHERE area = ' . $area . ' LIMIT 1')) OR sqlerr (__FILE__, 33));
        }
        else
        {
          (sql_query ('INSERT INTO pincode (pincode,sechash,area) VALUES (' . sqlesc ($pincode_s) . ', ' . sqlesc ($sechash) . ('' . ', ' . $area . ')')) OR sqlerr (__FILE__, 35));
        }

        if (mysql_affected_rows ())
        {
          $message = 'Your pincode has been updated!';
        }
        else
        {
          $message = 'Can\'t update the pincode!';
        }
      }
    }
    else
    {
      $message = 'Pincode can\'t be empty and must be match!';
    }
  }

  echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
	<head>
		<title>Change Pincode Tool ';
  echo RP_VERSION;
  echo '</title>
		<meta http-equiv="Pragma" content="no-cache" />
		<meta http-equiv="Expires" content="-1" />
		<meta http-equiv="Cache-Control" content="no-cache" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		';
  echo '<s';
  echo 'tyle>
			body
			{
				background: #FFFFFF;
				color: #000000;
				font: 10pt verdana, geneva, lucida, \'lucida grande\', arial, helvetica, sans-serif;
				margin: 5px 10px 10px 10px;
				padding: 0px;
			}
			a:link, body_alink
			{
				color: #22229C;
			}
			a:visited, body_avisited
			{
				color: #22229C;
			}
			a:hover, a:active, body_ahover
			{
				color: #FF4400;
			}
';
  echo '
			td, th, p, li
			{
				font: 10pt verdana, geneva, lucida, \'lucida grande\', arial, helvetica, sans-serif;
			}
		</style>
	</head>
	<body>
		<fieldset style="background: #E1E1E2;">
			<legend><b>Change Pincode Tool ';
  echo RP_VERSION;
  echo '</b></legend>
			<form method="POST" action="';
  echo $_SERVER['SCRIPT_NAME'];
  echo '">
			';
  echo '<font color="red"><b>' . $message . '</b></font>';
  echo '			<table border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td>Enter New Pincode:</td>
					<td><input type="password" name="pincode1" value="" size="20" /></td>
				</tr>
				<tr>
					<td>Re-Enter New Pincode:</td>
					<td><input type="password" name="pincode2" value="" size="20" /></td>
				</tr>
				<tr>
					<td>Use New Pincode For:</td>
					<td>';
  echo '<s';
  echo 'elect name="area"><option value="2">Staff Panel</option><option value="1">Setting Panel</option></select></td>
				</tr>
				<tr>
					<td colspan="2"><input type="submit" value="Reset Pincode" /></td>
				</tr>
			</table>
			<div style="float: right;"><a href="http://templateshares.net/?';
  echo $_SERVER['HTTP_HOST'];
  echo '" target="_blank">http://templateshares.net</a></div>
			</form>
		</fieldset>
	</body>
</html>';
?>
