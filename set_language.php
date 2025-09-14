<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function fix_url ($url)
  {
    $url = htmlspecialchars ($url);
    $f[0] = '&amp;';
    $f[1] = ' ';
    $f[2] = '  ';
    $r[0] = '&';
    $r[1] = '&nbsp;';
    $r[2] = '&nbsp;&nbsp;';
    return str_replace ($f, $r, $url);
  }

  define ('SL_VERSION', '0.4 ');
  $language = fix_url ($_GET['language']);
  setcookie ('ts_language', $language, time () + 60 * 60 * 24 * 365, '/');
  if (((isset ($_GET['redirect']) AND $redirect = $_GET['redirect']) AND $redirect == 'yes'))
  {
    $to = (!empty ($_SERVER['HTTP_REFERER']) ? fix_url ($_SERVER['HTTP_REFERER']) : 'index.php');
    header ('Location: ' . $to);
    exit ();
    return 1;
  }

  echo '
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title>Update Language</title>
			<style type="text/css">
				<!--
				.style1
				{
					color: #FF0000;
					font-weight: bold;
					font-size: 10px;
				}
				body,td,th
				{
					font-family: Verdana, Arial, Helvetica, sans-serif;
				}
				-->
			</style>
			<script type="text/javascript">
				setInterval("window.close()",3000);
				opener.location.reload();
			</script>
		</head>
		<body>
			<span class="style1">Language setting has been updated...</span>
		</body>
	</html>';
  exit ();
?>
