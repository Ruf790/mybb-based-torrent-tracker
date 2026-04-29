<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function i2c_realip ()
  {
    $ip = FALSE;
    if (!empty ($_SERVER['HTTP_CLIENT_IP']))
    {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    if (!empty ($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
      $ips = explode (', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
      if ($ip)
      {
        array_unshift ($ips, $ip);
        $ip = FALSE;
      }

      $i = 0;
      while ($i < count ($ips))
      {
        if (!preg_match ('/^(?:10|172\\.(?:1[6-9]|2\\d|3[01])|192\\.168)\\./', $ips[$i]))
        {
          if (version_compare (phpversion (), '5.0.0', '>='))
          {
            if (ip2long ($ips[$i]) != false)
            {
              $ip = $ips[$i];
              break;
            }
          }

          if (ip2long ($ips[$i]) != 0 - 1)
          {
            $ip = $ips[$i];
            break;
          }
        }

        ++$i;
      }
    }

    return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
  }

  function do_post_request ($url, $data, $optional_headers = null)
  {
    $params = array ('http' => array ('method' => 'POST', 'content' => $data));
    if ($optional_headers !== null)
    {
      $params['http']['header'] = $optional_headers;
    }

    $ctx = stream_context_create ($params);
    $fp = @fopen ($url, 'rb', false, $ctx);
    if (!$fp)
    {
      exit ('' . 'Problem with ' . $url . ', ' . $php_errormsg);
    }

    $response = @stream_get_contents ($fp);
    if ($response === false)
    {
      exit ('' . 'Problem reading data from ' . $url . ', ' . $php_errormsg);
    }

    return $response;
  }

  if (!defined ('IN_ADMIN_PANEL'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('ITC_VERSION', '0.5 by xam');
  $do = (isset ($_POST['do']) ? htmlspecialchars ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars ($_GET['do']) : 1));
  stdhead ('Ip to Country');
  $errormessage = '';
  if ($do == 2)
  {
    $ip = ((isset ($_POST['ip_address']) AND !empty ($_POST['ip_address'])) ? $_POST['ip_address'] : ((isset ($_GET['ip_address']) AND !empty ($_GET['ip_address'])) ? $_GET['ip_address'] : i2c_realip ()));
    $post_data = array ();
    $post_data['ip_address'] = $ip;
    if ((function_exists ('curl_init') AND $ch = curl_init ()))
    {
      curl_setopt ($ch, CURLOPT_URL, 'http://ip-to-country.webhosting.info/node/view/36');
      curl_setopt ($ch, CURLOPT_POST, 1);
      curl_setopt ($ch, CURLOPT_POSTFIELDS, $post_data);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
      $postResult = curl_exec ($ch);
      if (curl_errno ($ch))
      {
        exit (curl_error ($ch));
      }

      curl_close ($ch);
    }
    else
    {
      $postResult = do_post_request ('http://ip-to-country.webhosting.info/node/view/36', 'ip_address=' . $ip);
    }

    _form_header_open_ ('Search Result');
    if (empty ($errormessage))
    {
      $regex = '' . '#<b>' . $ip . '</b>(.*).<br><br><img src=(.*)>#U';
      preg_match_all ($regex, $postResult, $result, PREG_SET_ORDER);
      echo '<tr><td>IP Address <b>' . htmlspecialchars_uni ($ip) . '</b>' . $result[0][1] . '.<br /><br /><img src="http://ip-to-country.webhosting.info/' . $result[0][2] . '"></td></tr>';
    }
    else
    {
      echo '<tr><td>' . $errormessage . '</td></tr>';
    }

    _form_header_close_ ();
    echo '<br />';
  }

  $externalpreview = '<div id=\'loading-layer\' style=\'position: absolute; display:none; left:500px; width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000\'><div style=\'font-weight:bold\' id=\'loading-layer-text\' class=\'small\'>Searching... Please wait...</div><br /><img src=\'' . $BASEURL . '/' . $pic_base_url . 'await.gif\' border=\'0\' /></div>';
 // _form_header_open_ ('Ip to Country');
  
  
   echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Ip to Country
	</div>
	 </div>
		</div>';
  
  
  echo '
  
 <div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body"> 
  
  
<tr><td>
<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
<input type="hidden" name="act" value="iptocountry">
<input type="hidden" name="do" value="2">
IP Address: <label><input name="ip_address" type="text" value="' . htmlspecialchars_uni ($ip) . '" class="form-control"></label> 
<input value="Find Country" name="submit" class="btn btn-primary" type="submit" onclick="ts_show(\'loading-layer\')"> 
' . $externalpreview . '
</td></tr></form>

</div>
 </div>
 </div>


';
 
  stdfoot ();
?>
