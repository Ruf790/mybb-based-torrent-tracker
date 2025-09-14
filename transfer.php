<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function print_transfer_error ($errormessage = '')
  {
    global $lang;
    exit ('
	<font color="red"><b>' . $errormessage . '</b></font><br />
	<form>
		<input type="button" name="mybutton1" value="' . $lang->transfer['goback'] . '" onClick="history.back()">
	</form>
	');
  }

  require_once 'global.php';
  gzip ();

  loggedinorreturn ();
  maxsysop ();
  $lang->load ('transfer');
  define ('T_VERSION', '1.4.2 ');
  if ($CURUSER['uploaded'] <= 100)
  {
    print_transfer_error ($lang->transfer['noway']);
  }

  $act = (isset ($_POST['act']) ? htmlspecialchars_uni ($_POST['act']) : (isset ($_GET['act']) ? htmlspecialchars_uni ($_GET['act']) : ''));
  $amount = (isset ($_POST['amount']) ? min ($CURUSER['uploaded'], 0 + $_POST['amount']) : (isset ($_GET['amount']) ? min ($CURUSER['uploaded'], 0 + $_GET['amount']) : ''));
  $username = (isset ($_POST['username']) ? htmlspecialchars_uni ($_POST['username']) : (isset ($_GET['username']) ? htmlspecialchars_uni ($_GET['username']) : ''));
  $id = (isset ($_POST['receiver']) ? intval ($_POST['receiver']) : (isset ($_GET['receiver']) ? intval ($_GET['receiver']) : ''));
  if (($usergroups['cansettingspanel'] != '1' AND $usergroups['cantransfer'] != 'yes'))
  {
    print_transfer_error ($lang->global['nopermission']);
  }

  if ((!is_valid_id ($id) AND empty ($username)))
  {
    print_transfer_error ($lang->global['nouserid']);
  }

  if ($act == 'transfer')
  {
    if (((empty ($username) OR empty ($amount)) OR $CURUSER['uploaded'] < $amount))
    {
      print_transfer_error ($lang->global['dontleavefieldsblank']);
    }
    else
    {
      if ($username == $CURUSER['username'])
      {
        print_transfer_error ($lang->transfer['noway2']);
      }
    }

    $hash = $_POST['hash'];
    if ((($hash !== $_SESSION['token_code'] OR empty ($hash)) OR empty ($_SESSION['token_code'])))
    {
      unset ($_SESSION[token_code]);
      print_transfer_error ('Hacking Attempt!');
    }

    unset ($_SESSION[token_code]);
    $query = $db->sql_query ('SELECT id, username, modcomment FROM users WHERE username = ' . $db->sqlesc ($username) . ' AND status = \'confirmed\' AND enabled = \'yes\'');
    if ($db->num_rows ($query) == 0)
    {
      print_transfer_error ($lang->global['nousername']);
    }

    $id = intval ($db->fetch_field ($query, 'id'));
    $username = htmlspecialchars_uni ($db->fetch_field ($query, 'username'));
    $o_m = $db->fetch_field ($query, 'modcomment');
    if ((preg_match ('#' . gmdate ('Y-m-d') . ' - Got#U', $o_m, $pm_results) == true AND $usergroups['cansettingspanel'] != 'yes'))
    {
      print_transfer_error ($lang->transfer['noway4']);
    }
    else
    {
      if ((preg_match ('#' . gmdate ('Y-m-d') . ' - Transfered#U', $CURUSER['modcomment'], $pm_results2) == true AND $usergroups['cansettingspanel'] != 'yes'))
      {
        print_transfer_error ($lang->transfer['noway3']);
      }
    }

    $modcomment = gmdate ('Y-m-d') . ' - Got ' . mksize ($amount) . ('' . ' transfer amount from ' . $CURUSER['username'] . '
') . $o_m;
    $db->sql_query ('UPDATE users SET uploaded = uploaded + ' . $db->sqlesc ($amount) . ', modcomment = ' . $db->sqlesc ($modcomment) . ' WHERE username = ' . $db->sqlesc ($username));
    $o_m = $CURUSER['modcomment'];
    $modcomment = gmdate ('Y-m-d') . ' - Transfered ' . mksize ($amount) . ('' . ' to ' . $username . '
') . $o_m;
    $db->sql_query ('UPDATE users SET uploaded = uploaded - ' . $db->sqlesc ($amount) . ', modcomment = ' . $db->sqlesc ($modcomment) . ' WHERE id = ' . $db->sqlesc ($CURUSER['id']));
    require_once INC_PATH . '/functions_pm.php';
    send_pm ($id, sprintf ($lang->transfer['msgbody'], $username, $CURUSER['username'], mksize ($amount)), $lang->transfer['msgsubject']);
    $completed = sprintf ($lang->transfer['info2'], $username, mksize ($amount));
    unset ($act);
  }

  if ($act == 'calculate')
  {
    $amount = 0 + $_POST['amount'];
    $result_amount = mksize ($amount);
    $info2 = $lang->transfer['result'] . $result_amount;
    $amount = htmlspecialchars_uni ($amount);
    unset ($act);
  }

  if (empty ($act))
  {
    $query = $db->sql_query ('SELECT id, username FROM users WHERE id = ' . $db->sqlesc ($id) . ' AND status = \'confirmed\' AND enabled = \'yes\'');
    if ($db->num_rows ($query) == 0)
    {
      print_transfer_error ($lang->global['nouserid']);
    }

    $id = intval ($db->fetch_field ($query, 'id'));
    $username = htmlspecialchars_uni ($db->fetch_field ($query, 'username'));
    $max = $CURUSER['uploaded'] . ' (' . mksize ($CURUSER['uploaded']) . ')';
    if (empty ($completed))
    {
      $info = sprintf ($lang->transfer['info'], $max);
    }
    else
    {
      $info = $completed;
    }

    include_once INC_PATH . '/ts_token.php';
    $ts_token = new ts_token ();
    $hash = $ts_token->create_return ();
    $javascript = '' . '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title>' . $SITENAME . '</title>

    <style type="text/css">
		body
		{
			margin:0px 0;
			padding:0;
			color:#000000;
			line-height: 1.4em;
			font-style:normal;
			font-variant:normal;
			font-weight:normal;
			font-size:74%;
			font-family:Arial, Sans-Serif
			text-align: left;
		}
        fieldset {
            width: 90%;
            margin: 15px 0px 25px 0px;
            padding: 15px;
        }
        legend {
            font-weight: bold;
        }
        .button {
            text-align: right;
        }
        .button input {
            font-weight: bold;
        }

    </style>

<script language=javascript>

	var message="";
	function clickIE() {if (document.all) {(message);return false;}}
	function clickNS(e) {if
	(document.layers||(document.getElementById&&!document.all)) {
	if (e.which==2||e.which==3) {(message);return false;}}}
	if (document.layers)
	{document.captureEvents(Event.MOUSEDOWN);document.onmousedown=clickNS;}
	else{document.onmouseup=clickNS;document.oncontextmenu=clickIE;}
	document.oncontextmenu=new Function("return false")
</script>

</head>

<body>
	<table width="100%" border="0" align="center">
		<tr>
			<td>
			<fieldset>
				<legend>' . $lang->transfer['head'] . '</legend>
					<table width="100%" border="0">
						<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
						<input type="hidden" name="act" value="transfer">
						<input type="hidden" name="hash" value="' . $hash . '">
						<tr>
							<td align="right">
								' . $lang->transfer['field1'] . '</td><td><input type="text" name="username" size="20" value="' . $username . '">
							</td>
						</tr>
							<tr>
								<td align="right">
									' . $lang->transfer['field2'] . '
								</td>
								<td>
									<input type="text" name="amount" size="20"> <input type="submit" value="' . $lang->transfer['button'] . '">
								</td>
							</tr>
						</form>
					</table>
				</fieldset>
			</td>
		</tr>
	</table>
	<table width="100%" border="0" align="center">
		<tr>
			<td align="left">
				<fieldset>
					<legend>' . $lang->transfer['head2'] . '</legend>
						<table width="100%" border="0">
							<tr>
								<td>
									' . $info . '
								</td>
							</tr>
						</table>
				</fieldset>
			</td>
			<td align="left">
				<fieldset>
					<legend>' . $lang->transfer['head3'] . '</legend>
						<table width="100%" border="0">
							<tr>
								<td><br />
									<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '?receiver=' . $id . '">
										<input type="hidden" name="receiver" value="' . $id . '">
										<input type="hidden" name="act" value="calculate">
										' . $lang->transfer['amount'] . ' <input type="text" name="amount" size="20" value="' . $amount . '"> <input type="submit" value="' . $lang->transfer['head3'] . '">
									</form>
									' . $info2 . '
									<br />
								</td>
							</tr>
						</table>
				</fieldset>
			</td>
		</tr>
	</table>
</body>
</html>';
    echo $javascript;
  }

?>
