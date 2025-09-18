<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/



/////////////Functions.php////////////////////////////////////////////////////////////
function stdhead($title = '', $msgalert = true, $script = '', $script2 = '', $incCSS = '')
{
	global $CURUSER, $SITEONLINE, $SITENAME, $SITEEMAIL, $session, $mail_handler, $header, $showownunapproved, $enableattachments, $templates, $templatelist, $date_formats, $f_threadsperpage, $f_postsperpage, $use_xmlhttprequest, $time_formats, $offline_minutes, $parser, $plugins, $cache, $BASEURL, $db, $mybb, $jumptopagemultipage, $maxmultipagelinks, $offlinemsg, $disablerightclick, $autorefreshtime, $autorefresh, $leftmenu, $gzipcompress, $delay, $url, $cookiedomain, $cookiepath, $cookieprefix, $cookiesamesiteflag, $cookiesecureflag, $rootpath, $pic_base_url, $charset, $metadesc, $metakeywords, $lang, $slogan, $groupscache, $usergroups, $leechwarn_remove_ratio, $cache, $dateformat, $timeformat, $cachetime, $checkconnectable, $timezoneoffset;
	
	

	
	// Проверка автоматического включения сайта
    if ($SITEONLINE === 'no' && isset($offline_minutes)) 
	{
    // Для limited-режима (когда установлен числовой timestamp)
    if (is_numeric($offline_minutes) && $offline_minutes > 0) 
	{
        if (time() > (int)$offline_minutes) 
		{
            $db->sql_query("START TRANSACTION");
            try {
                $db->sql_query("UPDATE settings SET value = 'yes' WHERE name = 'SITEONLINE'");
                $db->sql_query("UPDATE settings SET value = '0' WHERE name = 'offline_minutes'");
                $db->sql_query("COMMIT");
                
                rebuild_settings();
                write_log("[MAINTENANCE] Automatically switched to online - time expired");
                
                // Обновляем глобальные переменные
                $SITEONLINE = 'yes';
                $offline_minutes = 0;
                
            } catch (Exception $e) {
                $db->sql_query("ROLLBACK");
                write_log("[ERROR] Failed to auto-enable site: " . $e->getMessage());
            }
        }
    }
    // Для unlimited-режима ничего не делаем - остается в оффлайне
    // ($offline_minutes === 'unlimited')
    }
	
	
	
	

    if ($SITEONLINE != 'yes' && $CURUSER)
    {
    if ($usergroups['canviewboardclosed'] != '1')
    {
        require_once INC_PATH . '/maintenance_page.php';
        render_maintenance_page();
        exit;
    }
    else
    {
        $offlinemsg = true;
    }
    }



	
	
	$lang->load('header');
	$script_name = $_SERVER['SCRIPT_NAME'];
	//$includescripts = $includeCSS = '';
	
	
	
	$ts_tzoffset = isset($CURUSER['timezone']) ? $CURUSER['timezone'] : $timezoneoffset;
	
	
	
	//if (!empty($incCSS))
	//{
		//$includeCSS = $incCSS;
	//}
	
	
	
	$title = $SITENAME.' :: '.($title != '' ? htmlspecialchars_uni($title) : TS_MESSAGE);
   
	
	//if ($script == 'supernote')
	//{
		//$includescripts .= '
		//<script type="text/javascript" src="'.$BASEURL.'/scripts/menu.js?v='.O_SCRIPT_VERSION.'"></script>';
		//$script = 'collapse';
	//}
	
	

	
	
	if ($CURUSER)
	{
		include_once(INC_PATH.'/functions_ratio.php');
	  
		
		$uploaded = !empty($CURUSER['uploaded']) ? $CURUSER['uploaded'] : 0;
        $downloaded = !empty($CURUSER['downloaded']) ? $CURUSER['downloaded'] : 0;
        $ratio = get_user_ratio($CURUSER['uploaded'] ?? 0, $CURUSER['downloaded'] ?? 0, true);
		
		
		$medaldon = ($CURUSER['donor'] === 'yes') ? '<i class="fa-solid fa-star"></i>' : '';
        $warn = ($CURUSER['warned'] === 'yes') ? '<i class="fa-solid fa-triangle-exclamation fa-bounce text-danger" title="'.$lang->global['imgwarned'].'"></i>' : '';
        $lwarn = ($CURUSER['leechwarn'] === 'yes') ? '<i class="fa-solid fa-triangle-exclamation fa-bounce" style="color:#FFD43B;" title="LeechWarned"></i>' : '';
		

		if ($checkconnectable == 'yes')
		{
			$connectablequery = $db->sql_query("SELECT userid FROM peers WHERE connectable = 'no' AND userid = ".sqlesc($CURUSER['id']));
			$c_count = $db->num_rows($connectablequery);
			if ($c_count[0] > 0)
			{
				$connectablealert = sprintf($lang->global['connectablealert'] , $c_count[0], $BASEURL.'/tsf_forums/', $BASEURL.'/faq.php');
				$warnmessages[] = $connectablealert;
			}
		}
	}
	
	
	
	
	include(INC_PATH.'/templates/default/header.php');	
}
# Function stdfoot v.1.4
function stdfoot()
{	
	global $SITENAME,$BASEURL,$CURUSER,$rootpath,$lang,$usergroups,$db,$mybb,$maintimer,$templates,$templatelist,$session;	
	//$defaulttemplate = ts_template();
	//$script_name = $_SERVER['SCRIPT_NAME'];	
	
	
	include(INC_PATH.'/templates/default/footer.php');	
}

# function jumpbutton v.0.2
function jumpbutton2222222($where)
{
	$str  = '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" class="none">
	<tbody><div class="hoptobuttons">';
	if (!is_array($where)) array($where);
	foreach ($where as $value => $jump)
	{
		if (!empty($value) && !empty($jump)) $str .= ' <input value="'.$value.'" onclick="jumpto(\''.$jump.'\');" class="btn btn-primary" type="button">';
	}
	$str .= '</div></tbody></table>';
	return $str;
}





function jumpbutton($where)
{
    // Если передали строку, оборачиваем в массив
    if (!is_array($where)) {
        $where = array($where);
    }

    $str = '<div class="hoptobuttons d-flex flex-wrap gap-2 justify-content-center">';

    foreach ($where as $value => $jump) 
	{
        if (!empty($value) && !empty($jump)) 
		{
            $str .= '<a href="' . htmlspecialchars($jump) . '" class="btn btn-primary">'
                 . htmlspecialchars($value) . '</a>';
        }
    }

    $str .= '</div>';

    return $str;
}








# Function tr v.0.2
function tr($x,$y,$noesc=0,$relation='')
{
    if ($noesc)
        $a = $y;
    else
	{
        $a = htmlspecialchars_uni($y);
        $a = str_replace("\n", "<br />\n", $a);
    }
    print("<tr".( $relation ? " relation = \"$relation\"" : "")."><td class=\"heading\" valign=\"top\" align=\"right\" width=\"20%\">$x</td><td valign=\"top\" align=\"left\" width=\"80%\">$a</td></tr>\n");
}



function my_validate_url($url, $relative_path=false, $allow_local=false)
{
	if($allow_local)
	{
		$regex = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:localhost|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?))(?::\d{2,5})?(?:[/?#]\S*)?$_iuS';
	}
	else
	{
		$regex = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?)(?::\d{2,5})?(?:[/?#]\S*)?$_iuS';
	}

	if($relative_path && my_substr($url, 0, 1) == '/' || preg_match($regex, $url))
	{
		return true;
	}
	return false;
}





function error($error="", $title="")
{
	global $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins, $charset, $BASEURL, $SITENAME;

	$error = $plugins->run_hooks("error", $error);
	if(!$error)
	{
		$error = 'unknown_error';
	}

	// AJAX error message?
	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		@header("Content-type: application/json; charset={$charset}");
		echo json_encode(array("errors" => array($error)));
		exit;
	}

	if(!$title)
	{
		$title = $SITENAME;
	}

	$timenow = my_datee('relative', TIMENOW);
	//reset_breadcrumb();
	//add_breadcrumb($lang->error);
    
	
	eval("\$errorpage = \"".$templates->get("error")."\";");
	
	echo $errorpage;

	exit;
}




function error_no_permission()
{
	global $mybb, $theme, $templates, $db, $lang, $plugins, $session, $charset, $CURUSER;

	$time = TIMENOW;
	$plugins->run_hooks("no_permission");

	$noperm_array = array (
		"nopermission" => '1',
		"location1" => 0,
		"location2" => 0
	);

	$db->update_query("sessions", $noperm_array, "sid='{$session->sid}'");

	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		header("Content-type: application/json; charset={$charset}");
		echo json_encode(array("errors" => array($lang->error_nopermission_user_ajax)));
		exit;
	}

	if($CURUSER['id'])
	{
		$error_nopermission_user_username = sprintf('You are currently logged in with the username: '.htmlspecialchars_uni($CURUSER['username']).'');
		eval("\$errorpage = \"".$templates->get("error_nopermission_loggedin")."\";");
	}
	else
	{
		// Redirect to where the user came from
		$redirect_url = $_SERVER['PHP_SELF'];
		if($_SERVER['QUERY_STRING'])
		{
			$redirect_url .= '?'.$_SERVER['QUERY_STRING'];
		}

		$redirect_url = htmlspecialchars_uni($redirect_url);
		
		$username_method = "0";

		switch($username_method)
		{
			case 0:
				$lang_username = 'username';
				break;
			case 1:
				$lang_username = 'username1';
				break;
			case 2:
				$lang_username = 'username2';
				break;
			default:
				$lang_username = 'username';
				break;
		}
		eval("\$errorpage = \"".$templates->get("error_nopermission")."\";");
	}

	error($errorpage);
}








function redirect($url, $message="", $title="", $force_redirect=false)
{
	global $header, $footer, $mybb, $theme, $headerinclude, $templates, $lang, $plugins, $redirects, $charset, $CURUSER, $BASEURL, $SITENAME;

	$redirect_args = array('url' => &$url, 'message' => &$message, 'title' => &$title);

	$plugins->run_hooks("redirect", $redirect_args);

	if($mybb->get_input('ajax', MyBB::INPUT_INT))
	{
		// Send our headers.
		//@header("Content-type: text/html; charset={$lang->settings['charset']}");
		$data = "<script type=\"text/javascript\">\n";
		if($message != "")
		{
			$data .=  'alert("'.addslashes($message).'");';
		}
		$url = str_replace("#", "&#", $url);
		$url = htmlspecialchars_decode($url);
		$url = str_replace(array("\n","\r",";"), "", $url);
		$data .=  'window.location = "'.addslashes($url).'";'."\n";
		$data .= "</script>\n";
		//exit;

		@header("Content-type: application/json; charset={$charset}");
		echo json_encode(array("data" => $data));
		exit;
	}

	if(!$message)
	{
		$message = 'You will now be redirected';
	}

	$time = TIMENOW;
	$timenow = my_datee('relative', $time);

	if(!$title)
	{
		$title = $SITENAME;
	}

	// Show redirects only if both ACP and UCP settings are enabled, or ACP is enabled, and user is a guest, or they are forced.
	
	if ($force_redirect === true || 
    (
        $redirects == 1 && 
		(
            !isset($CURUSER['id']) || 
            (isset($CURUSER['showredirect']) && $CURUSER['showredirect'] == 1)
        )
    )
)
{
    $url = str_replace("&amp;", "&", $url);
    $url = htmlspecialchars_uni($url);

    $redirectpage = '
<html>
<head>
<title>' . $title . '</title>
<meta http-equiv="refresh" content="2;URL=' . $url . '" />
<link rel="stylesheet" type="text/css" href="' . $BASEURL . '/include/templates/default/style/bootstrap.min.css" />
</head>
<body>
<div class="container-md pt-5">
<div class="card pt-5 border-0" style="max-width: 100%">
<div class="card-body pt-5">
	<div class="text-dark"><h3 class="mb-0 fw-bold">' . $title . '</h3></div>
	<div class="alert bg-nav mt-2 mb-2" style="font-size: 16px">
		' . $message . ' </div>
	<div class="text-center" style="font-size: 16px">
		<a href="' . $url . '">Click here if you don\'t want to wait any longer</a>
	</div>
</div>
</div>
</body>
</html>';
    
    echo $redirectpage;
}

	
	
	
	
	
	
	
	else
	{
		$url = htmlspecialchars_decode($url);
		$url = str_replace(array("\n","\r",";"), "", $url);

		run_shutdown();

		if(!my_validate_url($url, true, true))
		{
			header("Location: {$BASEURL}/{$url}");
		}
		else
		{
			header("Location: {$url}");
		}
	}

	exit;
}












# Function stdmsg v.0.3
function stdmsg($heading = '', $text = '', $htmlstrip = true, $div = 'error')
{
    if ($htmlstrip)
	{
        $heading = htmlspecialchars_uni($heading);
        $text = htmlspecialchars_uni($text);
    }
    echo show_notice($text, ($div == 'error' ? true : false), $heading);
	
}






function stderr($error="", $title="")
{
	global $SITENAME, $BASEURL, $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins;

	//$error = $plugins->run_hooks("error", $error);
	if(!$error)
	{
		$error = 'unknown_error';
	}

	if(!$title)
	{
		$title = $SITENAME;
	}

	$timenow = my_datee('relative', TIMENOW);
	stdhead();

	$errorpage = '
	
	
	
	
	
<html>
<head>
  <title>'.$title.'</title>

  <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
  <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">


</head>
<body>
 

  
 
    <div class="container mt-3">
	
	<div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">Error</h2>
          <p class="mb-0 opacity-75">A problem occurred while processing your request</p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
          '.$error.'
        </div>

        <div class="d-flex flex-column flex-sm-row gap-3">
          <button onclick="history.back()" class="btn btn-outline-danger flex-grow-1">
            <i class="bi bi-arrow-left me-2"></i> Go Back
          </button>
          <a href="'.$BASEURL.'/" class="btn btn-danger flex-grow-1">
            <i class="bi bi-house me-2"></i> Home Page
          </a>
        </div>
      </div>
    </div>
  </div>

  

  
</body>
</html>';

	echo $errorpage;
	stdfoot();

	exit;
}


//////////////////////////// End Functions.php////////////////////////////////////////







function my_inet_pton($ip)
{
	if(function_exists('inet_pton'))
	{
		return @inet_pton($ip);
	}
	else
	{
		/**
		 * Replace inet_pton()
		 *
		 * @category    PHP
		 * @package     PHP_Compat
		 * @license     LGPL - http://www.gnu.org/licenses/lgpl.html
		 * @copyright   2004-2007 Aidan Lister <aidan@php.net>, Arpad Ray <arpad@php.net>
		 * @link        http://php.net/inet_pton
		 * @author      Arpad Ray <arpad@php.net>
		 * @version     $Revision: 269597 $
		 */
		$r = ip2long($ip);
		if($r !== false && $r != -1)
		{
			return pack('N', $r);
		}

		$delim_count = substr_count($ip, ':');
		if($delim_count < 1 || $delim_count > 7)
		{
			return false;
		}

		$r = explode(':', $ip);
		$rcount = count($r);
		if(($doub = array_search('', $r, 1)) !== false)
		{
			$length = (!$doub || $doub == $rcount - 1 ? 2 : 1);
			array_splice($r, $doub, $length, array_fill(0, 8 + $length - $rcount, 0));
		}

		$r = array_map('hexdec', $r);
		array_unshift($r, 'n*');
		$r = call_user_func_array('pack', $r);

		return $r;
	}
}


function my_inet_ntop($ip)
{
	if(function_exists('inet_ntop'))
	{
		return @inet_ntop($ip);
	}
	else
	{
		/**
		 * Replace inet_ntop()
		 *
		 * @category    PHP
		 * @package     PHP_Compat
		 * @license     LGPL - http://www.gnu.org/licenses/lgpl.html
		 * @copyright   2004-2007 Aidan Lister <aidan@php.net>, Arpad Ray <arpad@php.net>
		 * @link        http://php.net/inet_ntop
		 * @author      Arpad Ray <arpad@php.net>
		 * @version     $Revision: 269597 $
		 */
		switch(strlen($ip))
		{
			case 4:
				list(,$r) = unpack('N', $ip);
				return long2ip($r);
			case 16:
				$r = substr(chunk_split(bin2hex($ip), 4, ':'), 0, -1);
				$r = preg_replace(
					array('/(?::?\b0+\b:?){2,}/', '/\b0+([^0])/e'),
					array('::', '(int)"$1"?"$1":"0$1"'),
					$r);
				return $r;
		}
		return false;
	}
}




function get_ip()
{
	global $mybb, $plugins;

	$ip = strtolower($_SERVER['REMOTE_ADDR']);

	$ip_forwarded_check = "0";
	
	if($ip_forwarded_check)
	{
		$addresses = array();

		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			$addresses = explode(',', strtolower($_SERVER['HTTP_X_FORWARDED_FOR']));
		}
		elseif(isset($_SERVER['HTTP_X_REAL_IP']))
		{
			$addresses = explode(',', strtolower($_SERVER['HTTP_X_REAL_IP']));
		}

		if(is_array($addresses))
		{
			foreach($addresses as $val)
			{
				$val = trim($val);
				// Validate IP address and exclude private addresses
				if(my_inet_ntop(my_inet_pton($val)) == $val && !preg_match("#^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.|fe80:|fe[c-f][0-f]:|f[c-d][0-f]{2}:)#", $val))
				{
					$ip = $val;
					break;
				}
			}
		}
	}

	if(!$ip)
	{
		if(isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$ip = strtolower($_SERVER['HTTP_CLIENT_IP']);
		}
	}

	if($plugins)
	{
		$ip_array = array("ip" => &$ip); // Used for backwards compatibility on this hook with the updated run_hooks() function.
		$plugins->run_hooks("get_ip", $ip_array);
	}

	return $ip;
}







function fetch_ip_range($ipaddress)
{
	// Wildcard
	if(strpos($ipaddress, '*') !== false)
	{
		if(strpos($ipaddress, ':') !== false)
		{
			// IPv6
			$upper = str_replace('*', 'ffff', $ipaddress);
			$lower = str_replace('*', '0', $ipaddress);
		}
		else
		{
			// IPv4
			$ip_bits = count(explode('.', $ipaddress));
			if($ip_bits < 4)
			{
				// Support for 127.0.*
				$replacement = str_repeat('.*', 4-$ip_bits);
				$ipaddress = substr_replace($ipaddress, $replacement, strrpos($ipaddress, '*')+1, 0);
			}
			$upper = str_replace('*', '255', $ipaddress);
			$lower = str_replace('*', '0', $ipaddress);
		}
		$upper = my_inet_pton($upper);
		$lower = my_inet_pton($lower);
		if($upper === false || $lower === false)
		{
			return false;
		}
		return array($lower, $upper);
	}
	// CIDR notation
	elseif(strpos($ipaddress, '/') !== false)
	{
		$ipaddress = explode('/', $ipaddress);
		$ip_address = $ipaddress[0];
		$ip_range = (int)$ipaddress[1];

		if(empty($ip_address) || empty($ip_range))
		{
			// Invalid input
			return false;
		}
		else
		{
			$ip_address = my_inet_pton($ip_address);

			if(!$ip_address)
			{
				// Invalid IP address
				return false;
			}
		}

		/**
		 * Taken from: https://github.com/NewEraCracker/php_work/blob/master/ipRangeCalculate.php
		 * Author: NewEraCracker
		 * License: Public Domain
		 */

		// Pack IP, Set some vars
		$ip_pack = $ip_address;
		$ip_pack_size = strlen($ip_pack);
		$ip_bits_size = $ip_pack_size*8;

		// IP bits (lots of 0's and 1's)
		$ip_bits = '';
		for($i = 0; $i < $ip_pack_size; $i = $i+1)
		{
			$bit = decbin(ord($ip_pack[$i]));
			$bit = str_pad($bit, 8, '0', STR_PAD_LEFT);
			$ip_bits .= $bit;
		}

		// Significative bits (from the ip range)
		$ip_bits = substr($ip_bits, 0, $ip_range);

		// Some calculations
		$ip_lower_bits = str_pad($ip_bits, $ip_bits_size, '0', STR_PAD_RIGHT);
		$ip_higher_bits = str_pad($ip_bits, $ip_bits_size, '1', STR_PAD_RIGHT);

		// Lower IP
		$ip_lower_pack = '';
		for($i=0; $i < $ip_bits_size; $i=$i+8)
		{
			$chr = substr($ip_lower_bits, $i, 8);
			$chr = chr(bindec($chr));
			$ip_lower_pack .= $chr;
		}

		// Higher IP
		$ip_higher_pack = '';
		for($i=0; $i < $ip_bits_size; $i=$i+8)
		{
			$chr = substr($ip_higher_bits, $i, 8);
			$chr = chr( bindec($chr) );
			$ip_higher_pack .= $chr;
		}

		return array($ip_lower_pack, $ip_higher_pack);
	}
	// Just on IP address
	else
	{
		return my_inet_pton($ipaddress);
	}
}



function is_banned_ip($ip_address, $update_lastuse=false)
{
	global $db, $cache;

	$banned_ips = $cache->read("bannedips");
	if(!is_array($banned_ips))
	{
		return false;
	}

	$ip_address = my_inet_pton($ip_address);
	foreach($banned_ips as $banned_ip)
	{
		if(!$banned_ip['filter'])
		{
			continue;
		}

		$banned = false;

		$ip_range = fetch_ip_range($banned_ip['filter']);
		if(is_array($ip_range))
		{
			if(strcmp($ip_range[0], $ip_address) <= 0 && strcmp($ip_range[1], $ip_address) >= 0)
			{
				$banned = true;
			}
		}
		elseif($ip_address == $ip_range)
		{
			$banned = true;
		}
		if($banned)
		{
			// Updating last use
			if($update_lastuse == true)
			{
				$db->update_query("banfilters", array("lastuse" => TIMENOW), "fid='{$banned_ip['fid']}'");
			}
			return true;
		}
	}

	// Still here - good ip
	return false;
}












function is_super_admin($uid)
{
	static $super_admins;

	if(!isset($super_admins))
	{
		global $mybb;
		$super_admins = str_replace(" ", "", $mybb->config['super_admins']);
	}

	if(my_strpos(",{$super_admins},", ",{$uid},") === false)
	{
		return false;
	}
	else
	{
		return true;
	}
}




function my_escape_csv($string, $escape_active_content=true)
{
	if($escape_active_content)
	{
		$active_content_triggers = array('=', '+', '-', '@');
		$delimiters = array(',', ';', ':', '|', '^', "\n", "\t", " ");

		$first_character = mb_substr($string, 0, 1);

		if(
			in_array($first_character, $active_content_triggers, true) ||
			in_array($first_character, $delimiters, true)
		)
		{
			$string = "'".$string;
		}

		foreach($delimiters as $delimiter)
		{
			foreach($active_content_triggers as $trigger)
			{
				$string = str_replace($delimiter.$trigger, $delimiter."'".$trigger, $string);
			}
		}
	}

	$string = str_replace('"', '""', $string);

	return $string;
}



function my_hash_equals($known_string, $user_string)
{
	if(version_compare(PHP_VERSION, '5.6.0', '>='))
	{
		return hash_equals($known_string, $user_string);
	}
	else
	{
		$known_string_length = my_strlen($known_string);
		$user_string_length = my_strlen($user_string);

		if($user_string_length != $known_string_length)
		{
			return false;
		}

		$result = 0;

		for($i = 0; $i < $known_string_length; $i++)
		{
			$result |= ord($known_string[$i]) ^ ord($user_string[$i]);
		}

		return $result === 0;
	}
}




function add_shutdown($name, $arguments=array())
{
	global $shutdown_functions;

	if(!is_array($shutdown_functions))
	{
		$shutdown_functions = array();
	}

	if(!is_array($arguments))
	{
		$arguments = array($arguments);
	}

	if(is_array($name) && method_exists($name[0], $name[1]))
	{
		$shutdown_functions[] = array('function' => $name, 'arguments' => $arguments);
		return true;
	}
	else if(!is_array($name) && function_exists($name))
	{
		$shutdown_functions[] = array('function' => $name, 'arguments' => $arguments);
		return true;
	}

	return false;
}










function update_stats(array $changes = [], bool $force = false): void
{
    global $cache, $db;
    static $stats_changes = [];

    // При первом вызове регистрируем отложенное выполнение
    if (empty($stats_changes)) {
        if (function_exists('add_shutdown')) {
            add_shutdown('update_stats', [[], true]);
        }
    }

    // Инициализация
    if (empty($stats_changes) || ($stats_changes['inserted'] ?? false)) {
        $stats_changes = [
            'numthreads' => '+0',
            'numposts' => '+0',
            'numusers' => '+0',
            'numunapprovedthreads' => '+0',
            'numunapprovedposts' => '+0',
            'numdeletedposts' => '+0',
            'numdeletedthreads' => '+0',
            'inserted' => false
        ];
        $stats = $stats_changes;
    } else {
        $stats = $stats_changes;
    }

    if ($force) {
        if (!empty($changes)) {
            update_stats($changes);
        }
        $stats = $cache->read("stats") ?? [];
        $changes = $stats_changes;
    }

    $new_stats = [];
    $counters = [
        'numthreads',
        'numunapprovedthreads',
        'numposts',
        'numunapprovedposts',
        'numusers',
        'numdeletedposts',
        'numdeletedthreads'
    ];

    foreach ($counters as $counter) {
        if (array_key_exists($counter, $changes)) {
            $val = (string)$changes[$counter];

            if (str_starts_with($val, "+-")) {
                $val = substr($val, 1);
            }

            if (str_starts_with($val, "+") || str_starts_with($val, "-")) {
                $delta = (int)$val;
                if ($delta !== 0) {
                    $oldVal = is_numeric($stats[$counter] ?? 0) ? (int)$stats[$counter] : 0;
                    $newVal = $oldVal + $delta;

                    if (!$force && (isset($stats[$counter][0]) && ($stats[$counter][0] === "+" || $stats[$counter][0] === "-"))) {
                        $new_stats[$counter] = ($newVal >= 0) ? "+{$newVal}" : 0;
                    } else {
                        $new_stats[$counter] = max(0, $newVal);
                    }
                }
            } else {
                $newVal = (int)$val;
                $new_stats[$counter] = max(0, $newVal);
            }
        }
    }

    if (!$force) {
        $stats_changes = array_merge($stats, $new_stats);
        return;
    }

    // Обновляем lastmember, если изменился numusers
    if (array_key_exists('numusers', $changes)) {
        $query = $db->simple_select("users", "id, username", "", [
            'order_by' => 'added',
            'order_dir' => 'DESC',
            'limit' => 1
        ]);
        $lastmember = $db->fetch_array($query);
        if ($lastmember) {
            $new_stats['lastuid'] = (int)$lastmember['uid'];
            $new_stats['lastusername'] = htmlspecialchars_uni($lastmember['username'] ?? '');
        }
    }

    if (!empty($new_stats)) {
        if (is_array($stats)) {
            $stats = array_merge($stats, $new_stats);
        } else {
            $stats = $new_stats;
        }
    }

    // Подсчёты
    $torrents = tsrowcount('id', 'torrents');

    $query = $db->sql_query("SELECT COUNT(id) as totalseeders FROM peers WHERE seeder = 'yes'");
    $Result = $db->fetch_array($query);
    $stats['seeders'] = ts_nf((int)($Result['totalseeders'] ?? 0));

    $query = $db->sql_query("SELECT COUNT(id) as totalleechers FROM peers WHERE seeder = 'no'");
    $Result = $db->fetch_array($query);
    $stats['leechers'] = ts_nf((int)($Result['totalleechers'] ?? 0));

    $stats['peers'] = ts_nf(((int)$stats['seeders']) + ((int)$stats['leechers']));
    $stats['torrents'] = (int)$torrents;

    $result = $db->sql_query("SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul FROM users");
    $row = $db->fetch_array($result);
    $stats['totaldownloaded'] = (int)($row['totaldl'] ?? 0);
    $stats['totaluploaded'] = (int)($row['totalul'] ?? 0);

    // Обновляем строку статистики за сегодня
    $todays_stats = [
        "dateline" => mktime(0, 0, 0, date("m"), date("j"), date("Y")),
        "numusers" => (int)($stats['numusers'] ?? 0),
        "numthreads" => (int)($stats['numthreads'] ?? 0),
        "numposts" => (int)($stats['numposts'] ?? 0),
        "torrents" => (int)($stats['torrents'] ?? 0),
        "seeders" => (int)($stats['seeders'] ?? 0),
        "leechers" => (int)($stats['leechers'] ?? 0),
        "peers" => (int)($stats['peers'] ?? 0),
        "totaldownloaded" => (int)($stats['totaldownloaded'] ?? 0),
        "totaluploaded" => (int)($stats['totaluploaded'] ?? 0)
    ];

    $db->replace_query("stats", $todays_stats, "dateline");

    $cache->update("stats", $stats, "dateline");
    $stats_changes['inserted'] = true;
}


















function unichr($c)
{
	if($c <= 0x7F)
	{
		return chr($c);
	}
	else if($c <= 0x7FF)
	{
		return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
	}
	else if($c <= 0xFFFF)
	{
		return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
									. chr(0x80 | $c & 0x3F);
	}
	else if($c <= 0x10FFFF)
	{
		return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
									. chr(0x80 | $c >> 6 & 0x3F)
									. chr(0x80 | $c & 0x3F);
	}
	else
	{
		return false;
	}
}




function email_already_in_use($email, $uid=0)
{
	global $db;

	$uid_string = "";
	if($uid)
	{
		$uid_string = " AND id != '".(int)$uid."'";
	}
	$query = $db->simple_select("users", "COUNT(email) as emails", "email = '".$db->escape_string($email)."'{$uid_string}");

	if($db->fetch_field($query, "emails") > 0)
	{
		return true;
	}

	return false;
}



function is_banned_username($username, $update_lastuse=false)
{
	global $db;
	$query = $db->simple_select('banfilters', 'filter, fid', "type='2'");
	while($banned_username = $db->fetch_array($query))
	{
		// Make regular expression * match
		$banned_username['filter'] = str_replace('\*', '(.*)', preg_quote($banned_username['filter'], '#'));
		if(preg_match("#(^|\b){$banned_username['filter']}($|\b)#i", $username))
		{
			// Updating last use
			if($update_lastuse == true)
			{
				$db->update_query("banfilters", array("lastuse" => TIMENOW), "fid='{$banned_username['fid']}'");
			}
			return true;
		}
	}
	// Still here - good username
	return false;
}



function is_banned_email($email, $update_lastuse=false)
{
	global $cache, $db;

	$banned_cache = $cache->read("bannedemails");

	if($banned_cache === false)
	{
		// Failed to read cache, see if we can rebuild it
		$cache->update_bannedemails();
		$banned_cache = $cache->read("bannedemails");
	}

	if(is_array($banned_cache) && !empty($banned_cache))
	{
		foreach($banned_cache as $banned_email)
		{
			// Make regular expression * match
			$banned_email['filter'] = str_replace('\*', '(.*)', preg_quote($banned_email['filter'], '#'));

			if(preg_match("#{$banned_email['filter']}#i", $email))
			{
				// Updating last use
				if($update_lastuse == true)
				{
					$db->update_query("banfilters", array("lastuse" => TIMENOW), "fid='{$banned_email['fid']}'");
				}
				return true;
			}
		}
	}

	// Still here - good email
	return false;
}





function get_user($uid)
{
	global $mybb, $db, $CURUSER;
	static $user_cache;

	$uid = (int)$uid;

	if(!empty($mybb->user) && $uid == $CURUSER['id'])
	{
		return $mybb->user;
	}
	elseif(isset($user_cache[$uid]))
	{
		return $user_cache[$uid];
	}
	elseif($uid > 0)
	{
		$query = $db->simple_select("users", "*", "id = '{$uid}'");
		$user_cache[$uid] = $db->fetch_array($query);

		return $user_cache[$uid];
	}
	return array();
}
  



function user_permissions($uid=null)
{
	global $mybb, $cache, $groupscache, $user_cache, $CURUSER;

	// If no user id is specified, assume it is the current user
	if($uid === null)
	{
		$uid = $CURUSER['id'];
	}

	// Its a guest. Return the group permissions directly from cache
	if($uid == 0)
	{
		return $groupscache[1];
	}

	// User id does not match current user, fetch permissions
	if($uid != $CURUSER['id'])
	{
		// We've already cached permissions for this user, return them.
		if(!empty($user_cache[$uid]['permissions']))
		{
			return $user_cache[$uid]['permissions'];
		}

		// This user was not already cached, fetch their user information.
		if(empty($user_cache[$uid]))
		{
			$user_cache[$uid] = get_user($uid);
		}

		// Collect group permissions.
		$gid = $user_cache[$uid]['usergroup'].",".$user_cache[$uid]['additionalgroups'];
		$groupperms = usergroup_permissions($gid);

		// Store group permissions in user cache.
		$user_cache[$uid]['permissions'] = $groupperms;
		return $groupperms;
	}
	// This user is the current user, return their permissions
	else
	{
		return $mybb->usergroup;
	}
}

/**
 * Fetch the usergroup permissions for a specific group or series of groups combined
 *
 * @param int|string $gid A list of groups (Can be a single integer, or a list of groups separated by a comma)
 * @return array Array of permissions generated for the groups, containing also a list of comma-separated checked groups under 'all_usergroups' index
 */
function usergroup_permissions($gid=0)
{
	global $cache, $groupscache, $grouppermignore, $groupzerogreater, $groupzerolesser, $groupxgreater, $grouppermbyswitch;

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	$groups = explode(",", $gid);

	if(count($groups) == 1)
	{
		$groupscache[$gid]['all_usergroups'] = $gid;
		return $groupscache[$gid];
	}

	$usergroup = array();
	$usergroup['all_usergroups'] = $gid;

	// Get those switch permissions from the first valid group.
	$permswitches_usergroup = array();
	$grouppermswitches = array();
	foreach(array_values($grouppermbyswitch) as $permvalue)
	{
		if(is_array($permvalue))
		{
			foreach($permvalue as $perm)
			{
				$grouppermswitches[] = $perm;
			}
		}
		else
		{
			$grouppermswitches[] = $permvalue;
		}
	}
	$grouppermswitches = array_unique($grouppermswitches);
	foreach($groups as $gid)
	{
		if(trim($gid) == "" || empty($groupscache[$gid]))
		{
			continue;
		}
		foreach($grouppermswitches as $perm)
		{
			$permswitches_usergroup[$perm] = $groupscache[$gid][$perm];
		}
		break;	// Only retieve the first available group's permissions as how following action does.
	}

	foreach($groups as $gid)
	{
		if(trim($gid) == "" || empty($groupscache[$gid]))
		{
			continue;
		}

		foreach($groupscache[$gid] as $perm => $access)
		{
			if(!in_array($perm, $grouppermignore))
			{
				if(isset($usergroup[$perm]))
				{
					$permbit = $usergroup[$perm];
				}
				else
				{
					$permbit = "";
				}

				// permission type: 0 not a numerical permission, otherwise a numerical permission.
				// Positive value is for `greater is more` permission, negative for `lesser is more`.
				$perm_is_numerical = 0;
				$perm_numerical_lowerbound = 0;

				// 0 represents unlimited for most numerical group permissions (i.e. private message limit) so take that into account.
				if(in_array($perm, $groupzerogreater))
				{
					// 1 means a `0 or greater` permission. Value 0 means unlimited.
					$perm_is_numerical = 1;
				}
				// Less is more for some numerical group permissions (i.e. post count required for using signature) so take that into account, too.
				else if(in_array($perm, $groupzerolesser))
				{
					// -1 means a `0 or lesser` permission. Value 0 means unlimited.
					$perm_is_numerical = -1;
				}
				// Greater is more, but with a lower bound.
				else if(array_key_exists($perm, $groupxgreater))
				{
					// 2 means a general `greater` permission. Value 0 just means 0.
					$perm_is_numerical = 2;
					$perm_numerical_lowerbound = $groupxgreater[$perm];
				}

				if($perm_is_numerical != 0)
				{
					$update_current_perm = true;

					// Ensure it's an integer.
					$access = (int)$access;
					// Check if this permission should be activatived by another switch permission in current group.
					if(array_key_exists($perm, $grouppermbyswitch))
					{
						if(!is_array($grouppermbyswitch[$perm]))
						{
							$grouppermbyswitch[$perm] = array($grouppermbyswitch[$perm]);
						}

						$update_current_perm = $group_current_perm_enabled = $group_perm_enabled = false;
						foreach($grouppermbyswitch[$perm] as $permswitch)
						{
							if(!isset($groupscache[$gid][$permswitch]))
							{
								continue;
							}
							$permswitches_current = $groupscache[$gid][$permswitch];

							// Determin if the permission is enabled by switches from current group.
							if($permswitches_current == 1 || $permswitches_current == "yes") // Keep yes/no for compatibility?
							{
								$group_current_perm_enabled = true;
							}
							// Determin if the permission is enabled by switches from previously handled groups.
							if($permswitches_usergroup[$permswitch] == 1 || $permswitches_usergroup[$permswitch] == "yes") // Keep yes/no for compatibility?
							{
								$group_perm_enabled = true;
							}
						}

						// Set this permission if not set yet.
						if(!isset($usergroup[$perm]))
						{
							$usergroup[$perm] = $access;
						}

						// If current group's setting enables the permission, we may need to update the user's permission.
						if($group_current_perm_enabled)
						{
							// Only update this permission if both its switch and current group switch are on.
							if($group_perm_enabled)
							{
								$update_current_perm = true;
							}
							// Override old useless value with value from current group.
							else
							{
								$usergroup[$perm] = $access;
							}
						}
					}

					// No switch controls this permission, or permission needs an update.
					if($update_current_perm)
					{
						switch($perm_is_numerical)
						{
							case 1:
							case -1:
								if($access == 0 || $permbit === 0)
								{
									$usergroup[$perm] = 0;
									break;
								}
							default:
								if($perm_is_numerical > 0 && $access > $permbit || $perm_is_numerical < 0 && $access < $permbit)
								{
									$usergroup[$perm] = $access;
								}
								break;
						}
					}

					// Maybe oversubtle, database uses Unsigned on them, but enables usage of permission value with a lower bound.
					if($usergroup[$perm] < $perm_numerical_lowerbound)
					{
						$usergroup[$perm] = $perm_numerical_lowerbound;
					}

					// Work is done for numerical permissions.
					continue;
				}

				if($access > $permbit || ($access == "yes" && $permbit == "no") || !$permbit) // Keep yes/no for compatibility?
				{
					$usergroup[$perm] = $access;
				}
			}
		}

		foreach($permswitches_usergroup as $perm => $value)
		{
			$permswitches_usergroup[$perm] = $usergroup[$perm];
		}
	}

	return $usergroup;
}


































function &get_my_mailhandler($use_buitlin = false)
{
	global $mybb, $plugins, $mail_handler, $mail_message_id;
	static $my_mailhandler;
	static $my_mailhandler_builtin;

	//$mail_handler = "smtp";
	//$mail_message_id = "1";
    $mail_parameters = "";
	
	if($use_buitlin)
	{
		// If our built-in mail handler doesn't exist, create it.
		if(!is_object($my_mailhandler_builtin))
		{
			require_once INC_PATH . "/class_mailhandler.php";

			// Using SMTP.
			if(isset($mail_handler) && $mail_handler == 'smtp')
			{
				require_once INC_PATH . "/mailhandlers/smtp.php";
				$my_mailhandler_builtin = new SmtpMail();
			}
			// Using PHP mail().
			else
			{
				require_once INC_PATH . "/mailhandlers/php.php";
				$my_mailhandler_builtin = new PhpMail();
				if(!empty($mail_parameters))
				{
					$my_mailhandler_builtin->additional_parameters = $mail_parameters;
				}
			}
		}

		if(isset($plugins) && is_object($plugins))
		{
			$plugins->run_hooks('my_mailhandler_builtin_after_init', $my_mailhandler_builtin);
		}

		return $my_mailhandler_builtin;
	}

	// If our mail handler doesn't exist, create it.
	if(!is_object($my_mailhandler))
	{
		require_once INC_PATH . "/class_mailhandler.php";

		if(isset($plugins) && is_object($plugins))
		{
			$plugins->run_hooks('my_mailhandler_init', $my_mailhandler);
		}

		// If no plugin has ever created the mail handler, resort to use the built-in one.
		if(!is_object($my_mailhandler) || !($my_mailhandler instanceof MailHandler))
		{
			$my_mailhandler = &get_my_mailhandler(true);
		}
	}

	return $my_mailhandler;
}






function my_mail($to, $subject, $message, $from="", $charset="", $headers="", $keep_alive=false, $format="text", $message_text="", $return_email="")
{
	global $mybb, $plugins, $mail_handler;

	// Get our mail handler.
	$mail = &get_my_mailhandler();
    
	///$mail_handler = "smtp";
	
	// If MyBB's built-in SMTP mail handler is used, set the keep alive bit accordingly.
	if($keep_alive == true && isset($mail->keep_alive) && isset($mail_handler) && $mail_handler == 'smtp')
	{
		require_once INC_PATH . "/class_mailhandler.php";
		require_once INC_PATH . "/mailhandlers/smtp.php";
		if($mail instanceof MailHandler && $mail instanceof SmtpMail)
		{
			$mail->keep_alive = true;
		}
	}

	// Following variables will help sequential plugins to determine how to process plugin hooks.
	// Mark this variable true if the hooked plugin has sent the mail, otherwise don't modify it.
	$is_mail_sent = false;
	// Mark this variable false if the hooked plugin doesn't suggest sequential plugins to continue processing.
	$continue_process = true;

	$my_mail_parameters = array(
		'to' => &$to,
		'subject' => &$subject,
		'message' => &$message,
		'from' => &$from,
		'charset' => &$charset,
		'headers' => &$headers,
		'keep_alive' => &$keep_alive,
		'format' => &$format,
		'message_text' => &$message_text,
		'return_email' => &$return_email,
		'is_mail_sent' => &$is_mail_sent,
		'continue_process' => &$continue_process,
	);

	if(isset($plugins) && is_object($plugins))
	{
		$plugins->run_hooks('my_mail_pre_build_message', $my_mail_parameters);
	}

	// Build the mail message.
	$mail->build_message($to, $subject, $message, $from, $charset, $headers, $format, $message_text, $return_email);

	if(isset($plugins) && is_object($plugins))
	{
		$plugins->run_hooks('my_mail_pre_send', $my_mail_parameters);
	}

	// Check if the hooked plugins still suggest to send the mail.
	if($continue_process)
	{
		$is_mail_sent = $mail->send();
	}

	if(isset($plugins) && is_object($plugins))
	{
		$plugins->run_hooks('my_mail_post_send', $my_mail_parameters);
	}

	return $is_mail_sent;
}




function validate_email_format($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}



// An array of valid date formats (Used for user selections etc)
$date_formats = array(
	1 => "m-d-Y",
	2 => "m-d-y",
	3 => "m.d.Y",
	4 => "m.d.y",
	5 => "d-m-Y",
	6 => "d-m-y",
	7 => "d.m.Y",
	8 => "d.m.y",
	9 => "F jS, Y",
	10 => "l, F jS, Y",
	11 => "jS F, Y",
	12 => "l, jS F, Y",
	// ISO 8601
	13 => "Y-m-d"
);

// An array of valid time formats (Used for user selections etc)
$time_formats = array(
	1 => "h:i a",
	2 => "h:i A",
	3 => "H:i"
);


function get_bdays($in)
{
	return array(
		31,
		($in % 4 == 0 && ($in % 100 > 0 || $in % 400 == 0) ? 29 : 28),
		31,
		30,
		31,
		30,
		31,
		31,
		30,
		31,
		30,
		31
	);
}







/*
 * Arbitrary limits for _safe_unserialize()
 */
define('MAX_SERIALIZED_INPUT_LENGTH', 10240);
define('MAX_SERIALIZED_ARRAY_LENGTH', 256);
define('MAX_SERIALIZED_ARRAY_DEPTH', 5);


function _safe_unserialize($str, $unlimited = true)
{
	if(!$unlimited && strlen($str) > MAX_SERIALIZED_INPUT_LENGTH)
	{
		// input exceeds MAX_SERIALIZED_INPUT_LENGTH
		return false;
	}

	if(empty($str) || !is_string($str))
	{
		return false;
	}

	$stack = $list = $expected = array();

	/*
	 * states:
	 *   0 - initial state, expecting a single value or array
	 *   1 - terminal state
	 *   2 - in array, expecting end of array or a key
	 *   3 - in array, expecting value or another array
	 */
	$state = 0;
	while($state != 1)
	{
		$type = isset($str[0]) ? $str[0] : '';

		if($type == '}')
		{
			$str = substr($str, 1);
		}
		else if($type == 'N' && $str[1] == ';')
		{
			$value = null;
			$str = substr($str, 2);
		}
		else if($type == 'b' && preg_match('/^b:([01]);/', $str, $matches))
		{
			$value = $matches[1] == '1' ? true : false;
			$str = substr($str, 4);
		}
		else if($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches))
		{
			$value = (int)$matches[1];
			$str = $matches[2];
		}
		else if($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches))
		{
			$value = (float)$matches[1];
			$str = $matches[3];
		}
		else if($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int)$matches[1], 2) == '";')
		{
			$value = substr($matches[2], 0, (int)$matches[1]);
			$str = substr($matches[2], (int)$matches[1] + 2);
		}
		else if(
			$type == 'a' &&
			preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches) &&
			($unlimited || $matches[1] < MAX_SERIALIZED_ARRAY_LENGTH)
		)
		{
			$expectedLength = (int)$matches[1];
			$str = $matches[2];
		}
		else
		{
			// object or unknown/malformed type
			return false;
		}

		switch($state)
		{
			case 3: // in array, expecting value or another array
				if($type == 'a')
				{
					if(!$unlimited && count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH)
					{
						// array nesting exceeds MAX_SERIALIZED_ARRAY_DEPTH
						return false;
					}

					$stack[] = &$list;
					$list[$key] = array();
					$list = &$list[$key];
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if($type != '}')
				{
					$list[$key] = $value;
					$state = 2;
					break;
				}

				// missing array value
				return false;

			case 2: // in array, expecting end of array or a key
				if($type == '}')
				{
					if(count($list) < end($expected))
					{
						// array size less than expected
						return false;
					}

					unset($list);
					$list = &$stack[count($stack)-1];
					array_pop($stack);

					// go to terminal state if we're at the end of the root array
					array_pop($expected);
					if(count($expected) == 0) {
						$state = 1;
					}
					break;
				}
				if($type == 'i' || $type == 's')
				{
					if(!$unlimited && count($list) >= MAX_SERIALIZED_ARRAY_LENGTH)
					{
						// array size exceeds MAX_SERIALIZED_ARRAY_LENGTH
						return false;
					}
					if(count($list) >= end($expected))
					{
						// array size exceeds expected length
						return false;
					}

					$key = $value;
					$state = 3;
					break;
				}

				// illegal array index type
				return false;

			case 0: // expecting array or value
				if($type == 'a')
				{
					if(!$unlimited && count($stack) >= MAX_SERIALIZED_ARRAY_DEPTH)
					{
						// array nesting exceeds MAX_SERIALIZED_ARRAY_DEPTH
						return false;
					}

					$data = array();
					$list = &$data;
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if($type != '}')
				{
					$data = $value;
					$state = 1;
					break;
				}

				// not in array
				return false;
		}
	}

	if(!empty($str))
	{
		// trailing data in input
		return false;
	}
	return $data;
}

function my_unserialize($str, $unlimited = true)
{
	// Ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_unserialize($str, $unlimited);

	if(isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}

	return $out;
}
  
   
   
   
function my_set_array_cookie($name, $id, $value, $expires="")
{
	global $mybb;

	if(isset($mybb->cookies['mybb'][$name]))
	{
		$newcookie = my_unserialize($mybb->cookies['mybb'][$name], false);
	}
	else
	{
		$newcookie = array();
	}

	$newcookie[$id] = $value;
	$newcookie = my_serialize($newcookie);
	my_setcookie("mybb[$name]", addslashes($newcookie), $expires);

	if(isset($mybb->cookies['mybb']) && !is_array($mybb->cookies['mybb']))
	{
		$mybb->cookies['mybb'] = array();
	}

	// Make sure our current viarables are up-to-date as well
	$mybb->cookies['mybb'][$name] = $newcookie;
}


  
function my_get_array_cookie($name, $id)
{
	global $mybb;

	if(!isset($mybb->cookies['mybb'][$name]))
	{
		return false;
	}

	$cookie = my_unserialize($mybb->cookies['mybb'][$name], false);

	if(is_array($cookie) && isset($cookie[$id]))
	{
		return $cookie[$id];
	}
	else
	{
		return 0;
	}
}

  
function my_unsetcookie($name)
{
	global $mybb;

	$expires = -3600;
	my_setcookie($name, "", $expires);

	unset($mybb->cookies[$name]);
}

  
function my_setcookie($name, $value="", $expires="", $httponly=false, $samesite="")
{
	global $mybb, $cookiedomain, $cookiepath, $cookieprefix, $cookiesamesiteflag, $cookiesecureflag;
	

	if(!$cookiepath)
	{
		$cookiepath = "/";
	}

	if($expires == -1)
	{
		$expires = 0;
	}
	elseif($expires == "" || $expires == null)
	{
		$expires = TIMENOW + (60*60*24*365); // Make the cookie expire in a years time
	}
	else
	{
		$expires = TIMENOW + (int)$expires;
	}

	$cookiepath = str_replace(array("\n","\r"), "", $cookiepath);
	$cookiedomain = str_replace(array("\n","\r"), "", $cookiedomain);
	$cookieprefix = str_replace(array("\n","\r", " "), "", $cookieprefix);

	// Versions of PHP prior to 5.2 do not support HttpOnly cookies and IE is buggy when specifying a blank domain so set the cookie manually
	$cookie = "Set-Cookie: {$cookieprefix}{$name}=".urlencode($value);

	if($expires > 0)
	{
		$cookie .= "; expires=".@gmdate('D, d-M-Y H:i:s \\G\\M\\T', $expires);
	}

	if(!empty($cookiepath))
	{
		$cookie .= "; path={$cookiepath}";
	}

	if(!empty($cookiedomain))
	{
		$cookie .= "; domain={$cookiedomain}";
	}

	if($httponly == true)
	{
		$cookie .= "; HttpOnly";
	}

	if($samesite != "" && $cookiesamesiteflag)
	{
		$samesite = strtolower($samesite);

		if($samesite == "lax" || $samesite == "strict")
		{
			$cookie .= "; SameSite=".$samesite;
		}
	}

	if($cookiesecureflag)
	{
		$cookie .= "; Secure";
	}

	$mybb->cookies[$name] = $value;

	header($cookie, false);
	
	
	
}






function trim_blank_chrs($string, $charlist="")
{
	$hex_chrs = array(
		0x09 => 1, // \x{0009}
		0x0A => 1, // \x{000A}
		0x0B => 1, // \x{000B}
		0x0D => 1, // \x{000D}
		0x20 => 1, // \x{0020}
		0xC2 => array(0x81 => 1, 0x8D => 1, 0x90 => 1, 0x9D => 1, 0xA0 => 1, 0xAD => 1), // \x{0081}, \x{008D}, \x{0090}, \x{009D}, \x{00A0}, \x{00AD}
		0xCC => array(0xB7 => 1, 0xB8 => 1), // \x{0337}, \x{0338}
		0xE1 => array(0x85 => array(0x9F => 1, 0xA0 => 1), 0x9A => array(0x80 => 1), 0xA0 => array(0x8E => 1)), // \x{115F}, \x{1160}, \x{1680}, \x{180E}
		0xE2 => array(0x80 => array(0x80 => 1, 0x81 => 1, 0x82 => 1, 0x83 => 1, 0x84 => 1, 0x85 => 1, 0x86 => 1, 0x87 => 1, 0x88 => 1, 0x89 => 1, 0x8A => 1, 0x8B => 1, 0x8C => 1, 0x8D => 1, 0x8E => 1, 0x8F => 1, // \x{2000} - \x{200F}
			0xA8 => 1, 0xA9 => 1, 0xAA => 1, 0xAB => 1, 0xAC => 1, 0xAD => 1, 0xAE => 1, 0xAF => 1), // \x{2028} - \x{202F}
			0x81 => array(0x9F => 1)), // \x{205F}
		0xE3 => array(0x80 => array(0x80 => 1), // \x{3000}
			0x85 => array(0xA4 => 1)), // \x{3164}
		0xEF => array(0xBB => array(0xBF => 1), // \x{FEFF}
			0xBE => array(0xA0 => 1), // \x{FFA0}
			0xBF => array(0xB9 => 1, 0xBA => 1, 0xBB => 1)), // \x{FFF9} - \x{FFFB}
	);

	$hex_chrs_rev = array(
		0x09 => 1, // \x{0009}
		0x0A => 1, // \x{000A}
		0x0B => 1, // \x{000B}
		0x0D => 1, // \x{000D}
		0x20 => 1, // \x{0020}
		0x81 => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{0081}, \x{2001}
		0x8D => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{008D}, \x{200D}
		0x90 => array(0xC2 => 1), // \x{0090}
		0x9D => array(0xC2 => 1), // \x{009D}
		0xA0 => array(0xC2 => 1, 0x85 => array(0xE1 => 1), 0x81 => array(0xE2 => 1), 0xBE => array(0xEF => 1)), // \x{00A0}, \x{1160}, \x{2060}, \x{FFA0}
		0xAD => array(0xC2 => 1, 0x80 => array(0xE2 => 1)), // \x{00AD}, \x{202D}
		0xB8 => array(0xCC => 1), // \x{0338}
		0xB7 => array(0xCC => 1), // \x{0337}
		0x9F => array(0x85 => array(0xE1 => 1), 0x81 => array(0xE2 => 1)), // \x{115F}, \x{205F}
		0x80 => array(0x9A => array(0xE1 => 1), 0x80 => array(0xE2 => 1, 0xE3 => 1)), // \x{1680}, \x{2000}, \x{3000}
		0x8E => array(0xA0 => array(0xE1 => 1), 0x80 => array(0xE2 => 1)), // \x{180E}, \x{200E}
		0x82 => array(0x80 => array(0xE2 => 1)), // \x{2002}
		0x83 => array(0x80 => array(0xE2 => 1)), // \x{2003}
		0x84 => array(0x80 => array(0xE2 => 1)), // \x{2004}
		0x85 => array(0x80 => array(0xE2 => 1)), // \x{2005}
		0x86 => array(0x80 => array(0xE2 => 1)), // \x{2006}
		0x87 => array(0x80 => array(0xE2 => 1)), // \x{2007}
		0x88 => array(0x80 => array(0xE2 => 1)), // \x{2008}
		0x89 => array(0x80 => array(0xE2 => 1)), // \x{2009}
		0x8A => array(0x80 => array(0xE2 => 1)), // \x{200A}
		0x8B => array(0x80 => array(0xE2 => 1)), // \x{200B}
		0x8C => array(0x80 => array(0xE2 => 1)), // \x{200C}
		0x8F => array(0x80 => array(0xE2 => 1)), // \x{200F}
		0xA8 => array(0x80 => array(0xE2 => 1)), // \x{2028}
		0xA9 => array(0x80 => array(0xE2 => 1)), // \x{2029}
		0xAA => array(0x80 => array(0xE2 => 1)), // \x{202A}
		0xAB => array(0x80 => array(0xE2 => 1)), // \x{202B}
		0xAC => array(0x80 => array(0xE2 => 1)), // \x{202C}
		0xAE => array(0x80 => array(0xE2 => 1)), // \x{202E}
		0xAF => array(0x80 => array(0xE2 => 1)), // \x{202F}
		0xA4 => array(0x85 => array(0xE3 => 1)), // \x{3164}
		0xBF => array(0xBB => array(0xEF => 1)), // \x{FEFF}
		0xB9 => array(0xBF => array(0xEF => 1)), // \x{FFF9}
		0xBA => array(0xBF => array(0xEF => 1)), // \x{FFFA}
		0xBB => array(0xBF => array(0xEF => 1)), // \x{FFFB}
	);

	// Start from the beginning and work our way in
	$i = 0;
	do
	{
		// Check to see if we have matched a first character in our utf-8 array
		$offset = match_sequence($string, $hex_chrs);
		if(!$offset)
		{
			// If not, then we must have a "good" character and we don't need to do anymore processing
			break;
		}
		$string = substr($string, $offset);
	}
	while(++$i);

	// Start from the end and work our way in
	$string = strrev($string);
	$i = 0;
	do
	{
		// Check to see if we have matched a first character in our utf-8 array
		$offset = match_sequence($string, $hex_chrs_rev);
		if(!$offset)
		{
			// If not, then we must have a "good" character and we don't need to do anymore processing
			break;
		}
		$string = substr($string, $offset);
	}
	while(++$i);
	$string = strrev($string);

	if($charlist)
	{
		$string = trim($string, $charlist);
	}
	else
	{
		$string = trim($string);
	}

	return $string;
}


function match_sequence($string, $array, $i=0, $n=0)
{
	if($string === "")
	{
		return 0;
	}

	$ord = ord($string[$i]);
	if(array_key_exists($ord, $array))
	{
		$level = $array[$ord];
		++$n;
		if(is_array($level))
		{
			++$i;
			return match_sequence($string, $level, $i, $n);
		}
		return $n;
	}

	return 0;
}




function get_memory_usage()
{
	if(function_exists('memory_get_peak_usage'))
	{
		return memory_get_peak_usage(true);
	}
	elseif(function_exists('memory_get_usage'))
	{
		return memory_get_usage(true);
	}
	return false;
}

function get_server_load()
{
	global $mybb, $lang;

	$serverload = array();

	// DIRECTORY_SEPARATOR checks if running windows
	if(DIRECTORY_SEPARATOR != '\\')
	{
		if(function_exists("sys_getloadavg"))
		{
			// sys_getloadavg() will return an array with [0] being load within the last minute.
			$serverload = sys_getloadavg();
			$serverload[0] = round($serverload[0], 4);
		}
		else if(@file_exists("/proc/loadavg") && $load = @file_get_contents("/proc/loadavg"))
		{
			$serverload = explode(" ", $load);
			$serverload[0] = round($serverload[0], 4);
		}
		if(!is_numeric($serverload[0]))
		{
			if($mybb->safemode)
			{
				return 'unknown';
			}

			// Suhosin likes to throw a warning if exec is disabled then die - weird
			if($func_blacklist = @ini_get('suhosin.executor.func.blacklist'))
			{
				if(strpos(",".$func_blacklist.",", 'exec') !== false)
				{
					return 'unknown';
				}
			}
			// PHP disabled functions?
			if($func_blacklist = @ini_get('disable_functions'))
			{
				if(strpos(",".$func_blacklist.",", 'exec') !== false)
				{
					return 'unknown';
				}
			}

			$load = @exec("uptime");
			$load = explode("load average: ", $load);
			$serverload = explode(",", $load[1]);
			if(!is_array($serverload))
			{
				return 'unknown';
			}
		}
	}
	else
	{
		return 'unknown';
	}

	$returnload = trim($serverload[0]);

	return $returnload;
}







function _safe_serialize( $value )
{
	if(is_null($value))
	{
		return 'N;';
	}

	if(is_bool($value))
	{
		return 'b:'.(int)$value.';';
	}

	if(is_int($value))
	{
		return 'i:'.$value.';';
	}

	if(is_float($value))
	{
		return 'd:'.str_replace(',', '.', $value).';';
	}

	if(is_string($value))
	{
		return 's:'.strlen($value).':"'.$value.'";';
	}

	if(is_array($value))
	{
		$out = '';
		foreach($value as $k => $v)
		{
			$out .= _safe_serialize($k) . _safe_serialize($v);
		}

		return 'a:'.count($value).':{'.$out.'}';
	}

	// safe_serialize cannot my_serialize resources or objects
	return false;
}


function my_serialize($value)
{
	// ensure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if(function_exists('mb_internal_encoding') && (((int)ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_serialize($value);
	if(isset($mbIntEnc))
	{
		mb_internal_encoding($mbIntEnc);
	}

	return $out;
}




function get_thread3333($tid, $recache = false)
{
	global $db;
	static $thread_cache;

	$tid = (int)$tid;

	if(isset($thread_cache[$tid]) && !$recache)
	{
		return $thread_cache[$tid];
	}
	else
	{
		$query = $db->simple_select("tsf_threads", "*", "tid = '{$tid}'");
		$thread = $db->fetch_array($query);

		if($thread)
		{
			$thread_cache[$tid] = $thread;
			return $thread;
		}
		else
		{
			$thread_cache[$tid] = false;
			return false;
		}
	}
}





function get_current_location($fields=false, $ignore=array(), $quick=false)
{
	global $mybb;

	if(defined("MYBB_LOCATION"))
	{
		return MYBB_LOCATION;
	}

	if(!empty($_SERVER['SCRIPT_NAME']))
	{
		$location = htmlspecialchars_uni($_SERVER['SCRIPT_NAME']);
	}
	elseif(!empty($_SERVER['PHP_SELF']))
	{
		$location = htmlspecialchars_uni($_SERVER['PHP_SELF']);
	}
	elseif(!empty($_ENV['PHP_SELF']))
	{
		$location = htmlspecialchars_uni($_ENV['PHP_SELF']);
	}
	elseif(!empty($_SERVER['PATH_INFO']))
	{
		$location = htmlspecialchars_uni($_SERVER['PATH_INFO']);
	}
	else
	{
		$location = htmlspecialchars_uni($_ENV['PATH_INFO']);
	}

	if($quick)
	{
		return $location;
	}

	if(!is_array($ignore))
	{
		$ignore = array($ignore);
	}

	if($fields == true)
	{

		$form_html = '';
		if(!empty($mybb->input))
		{
			foreach($mybb->input as $name => $value)
			{
				if(in_array($name, $ignore) || is_array($name) || is_array($value))
				{
					continue;
				}

				$form_html .= "<input type=\"hidden\" name=\"".htmlspecialchars_uni($name)."\" value=\"".htmlspecialchars_uni($value)."\" />\n";
			}
		}

		return array('location' => $location, 'form_html' => $form_html, 'form_method' => $mybb->request_method);
	}
	else
	{
		$parameters = array();

		if(isset($_SERVER['QUERY_STRING']))
		{
			$current_query_string = $_SERVER['QUERY_STRING'];
		}
		else if(isset($_ENV['QUERY_STRING']))
		{
			$current_query_string = $_ENV['QUERY_STRING'];
		} else
		{
			$current_query_string = '';
		}

		parse_str($current_query_string, $current_parameters);

		foreach($current_parameters as $name => $value)
		{
			if(!in_array($name, $ignore))
			{
				$parameters[$name] = $value;
			}
		}

		if($mybb->request_method === 'post')
		{
			$post_array = array('action', 'fid', 'pid', 'tid', 'uid', 'eid');

			foreach($post_array as $var)
			{
				if(isset($_POST[$var]) && !in_array($var, $ignore))
				{
					$parameters[$var] = $_POST[$var];
				}
			}
		}

		if(!empty($parameters))
		{
			$location .= '?'.http_build_query($parameters, '', '&amp;');
		}

		return $location;
	}
}


function dec_to_utf8($src)
{
	$dest = '';

	if($src < 0)
	{
		return false;
	}
	elseif($src <= 0x007f)
	{
		$dest .= chr($src);
	}
	elseif($src <= 0x07ff)
	{
		$dest .= chr(0xc0 | ($src >> 6));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	elseif($src <= 0xffff)
	{
		$dest .= chr(0xe0 | ($src >> 12));
		$dest .= chr(0x80 | (($src >> 6) & 0x003f));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	elseif($src <= 0x10ffff)
	{
		$dest .= chr(0xf0 | ($src >> 18));
		$dest .= chr(0x80 | (($src >> 12) & 0x3f));
		$dest .= chr(0x80 | (($src >> 6) & 0x3f));
		$dest .= chr(0x80 | ($src & 0x3f));
	}
	else
	{
		// Out of range
		return false;
	}

	return $dest;
}



function my_strlen($string)
{
	global $lang, $charset;

	$string = preg_replace("#&\#([0-9]+);#", "-", $string);

	if(strtolower($charset) == "utf-8")
	{
		// Get rid of any excess RTL and LTR override for they are the workings of the devil
		$string = str_replace(dec_to_utf8(8238), "", $string);
		$string = str_replace(dec_to_utf8(8237), "", $string);

		// Remove dodgy whitespaces
		$string = str_replace(chr(0xCA), "", $string);
	}
	$string = trim($string);

	if(function_exists("mb_strlen"))
	{
		$string_length = mb_strlen($string);
	}
	else
	{
		$string_length = strlen($string);
	}

	return $string_length;
}




function generate_post_check($rotation_shift=0)
{
	global $mybb, $session, $CURUSER;

	$rotation_interval = 6 * 3600;
	$rotation = floor(TIMENOW / $rotation_interval) + $rotation_shift;

	$seed = $rotation;

	if (isset($CURUSER) && isset($CURUSER['id']))
	{
		$seed .= $CURUSER['loginkey'].$CURUSER['salt'].$CURUSER['added'];
	}
	else
	{
	   $seed .= $session->sid;
	}

	if(defined('IN_ADMINCP'))
	{
		$seed .= 'ADMINCP';
	}

	$seed .= 'i3SenbCqQPM26ZRpoQOQghYaYQFYFn2Z';

	return md5($seed);
}



function verify_post_check($code, $silent=false)
{
	global $lang;
	if(
		generate_post_check() !== $code &&
		generate_post_check(-1) !== $code &&
		generate_post_check(-2) !== $code &&
		generate_post_check(-3) !== $code
	)
	{
		if($silent == true)
		{
			return false;
		}
		else
		{
			if(defined("IN_ADMINCP"))
			{
				return false;
			}
			else
			{
				stderr('Authorization code mismatch. Are you accessing this function correctly? Please go back and try again');
			}
		}
	}
	else
	{
		return true;
	}
}



function secure_binary_seed_rng($bytes)
{
	$output = null;

	if(version_compare(PHP_VERSION, '7.0', '>='))
	{
		try
		{
			$output = random_bytes($bytes);
		} catch (Exception $e) {
		}
	}

	if(strlen($output) < $bytes)
	{
		if(@is_readable('/dev/urandom') && ($handle = @fopen('/dev/urandom', 'rb')))
		{
			$output = @fread($handle, $bytes);
			@fclose($handle);
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(function_exists('mcrypt_create_iv'))
		{
			if (DIRECTORY_SEPARATOR == '/')
			{
				$source = MCRYPT_DEV_URANDOM;
			}
			else
			{
				$source = MCRYPT_RAND;
			}

			$output = @mcrypt_create_iv($bytes, $source);
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(function_exists('openssl_random_pseudo_bytes'))
		{
			// PHP <5.3.4 had a bug which makes that function unusable on Windows
			if ((DIRECTORY_SEPARATOR == '/') || version_compare(PHP_VERSION, '5.3.4', '>='))
			{
				$output = openssl_random_pseudo_bytes($bytes, $crypto_strong);
				if ($crypto_strong == false)
				{
					$output = null;
				}
			}
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		if(class_exists('COM'))
		{
			try
			{
				$CAPI_Util = new COM('CAPICOM.Utilities.1');
				if(is_callable(array($CAPI_Util, 'GetRandom')))
				{
					$output = $CAPI_Util->GetRandom($bytes, 0);
				}
			} catch (Exception $e) {
			}
		}
	}
	else
	{
		return $output;
	}

	if(strlen($output) < $bytes)
	{
		// Close to what PHP basically uses internally to seed, but not quite.
		$unique_state = microtime().@getmypid();

		$rounds = ceil($bytes / 16);

		for($i = 0; $i < $rounds; $i++)
		{
			$unique_state = md5(microtime().$unique_state);
			$output .= md5($unique_state);
		}

		$output = substr($output, 0, ($bytes * 2));

		$output = pack('H*', $output);

		return $output;
	}
	else
	{
		return $output;
	}
}


function secure_seed_rng()
{
	$bytes = PHP_INT_SIZE;

	do
	{

		$output = secure_binary_seed_rng($bytes);

		// convert binary data to a decimal number
		if ($bytes == 4)
		{
			$elements = unpack('i', $output);
			$output = abs($elements[1]);
		}
		else
		{
			$elements = unpack('N2', $output);
			$output = abs($elements[1] << 32 | $elements[2]);
		}

	} while($output > PHP_INT_MAX);

	return $output;
}

/**
 * Generates a cryptographically secure random number.
 *
 * @param int $min Optional lowest value to be returned (default: 0)
 * @param int $max Optional highest value to be returned (default: PHP_INT_MAX)
 */
function my_rand($min=0, $max=PHP_INT_MAX)
{
	// backward compatibility
	if($min === null || $max === null || $max < $min)
	{
		$min = 0;
		$max = PHP_INT_MAX;
	}

	if(version_compare(PHP_VERSION, '7.0', '>='))
	{
		try
		{
			$result = random_int($min, $max);
		} catch (Exception $e) {
		}

		if(isset($result))
		{
			return $result;
		}
	}

	$seed = secure_seed_rng();

	$distance = $max - $min;
	return $min + floor($distance * ($seed / PHP_INT_MAX) );
}



function random_str($length=8, $complex=false)
{
	$set = array_merge(range(0, 9), range('A', 'Z'), range('a', 'z'));
	$str = array();

	// Complex strings have always at least 3 characters, even if $length < 3
	if($complex == true)
	{
		// At least one number
		$str[] = $set[my_rand(0, 9)];

		// At least one big letter
		$str[] = $set[my_rand(10, 35)];

		// At least one small letter
		$str[] = $set[my_rand(36, 61)];

		$length -= 3;
	}

	for($i = 0; $i < $length; ++$i)
	{
		$str[] = $set[my_rand(0, 61)];
	}

	// Make sure they're in random order and convert them to a string
	shuffle($str);

	return implode($str);
}




function alt_trow($reset=0)
{
	global $alttrow;

	if($alttrow == "trow1" && !$reset)
	{
		$trow = "trow2";
	}
	else
	{
		$trow = "trow1";
	}

	$alttrow = $trow;

	return $trow;
}



function rebuild_settings()
{
	global $db, $mybb;

	$query = $db->simple_select("settings", "value, name", "", array(
		'order_by' => 'sid',
		'order_dir' => 'ASC',
	));

	$settings = '';
	while($setting = $db->fetch_array($query))
	{
		//$mybb->settings[$setting['name']] = $setting['value'];

		$setting['name'] = addcslashes($setting['name'], "\\'");
		$setting['value'] = addcslashes($setting['value'], '\\"$');
		$settings .= "\${$setting['name']} = \"{$setting['value']}\";\n";
	}

	$settings = "<"."?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n";

	file_put_contents(INC_PATH.'/settings.php', $settings, LOCK_EX);

	//$GLOBALS['settings'] = &$mybb->settings;
}





function get_comment_link($pid, $tid=0)
{
	if($tid > 0)
	{
		$link = str_replace("{id}", $tid, TORRENT_URL_COMMENT);
		$link = str_replace("{pid}", $pid, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		$link = str_replace("{pid}", $pid, COMMENT_URL);
		return htmlspecialchars_uni($link);
	}
}





function get_torrent_link($tid, $page=0, $action='')
{
	if($page > 1)
	{
		if($action)
		{
			$link = TORRENT_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = TORRENT_URL_PAGED;
		}
		$link = str_replace("{id}", $tid, $link);
		$link = str_replace("{page}", $page, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		if($action)
		{
			$link = TORRENT_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = TORRENT_URL;
		}
		$link = str_replace("{id}", $tid, $link);
		return htmlspecialchars_uni($link);
	}
}



function get_profile_link($uid=0, $page=0, $action='')
{
	if($page > 1)
	{
		if($action)
		{
			$link = PROFILE_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = PROFILE_URL_PAGED;
		}
		$link = str_replace("{id}", $uid, $link);
		$link = str_replace("{page}", $page, $link);
		return htmlspecialchars_uni($link);
	}
	else
	{
		if($action)
		{
			$link = PROFILE_URL_ACTION;
			$link = str_replace("{action}", $action, $link);
		}
		else
		{
			$link = PROFILE_URL;
		}
		$link = str_replace("{id}", $uid, $link);
		return htmlspecialchars_uni($link);
	}
}


function get_download_link($uid=0)
{
	$link = str_replace("{id}", $uid, DOWNLOAD_URL);
	return htmlspecialchars_uni($link);
}



function build_profile_link($username="", $uid=0, $target="", $onclick="")
{
	global $mybb, $lang, $BASEURL;

	if(!$username && $uid == 0)
	{
		// Return Guest phrase for no UID, no guest nickname
		return htmlspecialchars_uni('guest');
	}
	elseif($uid == 0)
	{
		// Return the guest's nickname if user is a guest but has a nickname
		return $username;
	}
	else
	{
		// Build the profile link for the registered user
		if(!empty($target))
		{
			$target = " target=\"{$target}\"";
		}

		if(!empty($onclick))
		{
			$onclick = " onclick=\"{$onclick}\"";
		}

		return "<a href=\"{$BASEURL}/".get_profile_link($uid)."\"{$target}{$onclick}>{$username}</a>";
	}
}


function cache_forums($force=false)
{
	global $forum_cache, $cache;

	if($force == true)
	{
		$forum_cache = $cache->read("forums", 1);
		return $forum_cache;
	}

	if(!$forum_cache)
	{
		$forum_cache = $cache->read("forums");
		if(!$forum_cache)
		{
			$cache->update_forums();
			$forum_cache = $cache->read("forums", 1);
		}
	}
	return $forum_cache;
}
 
/**
 * Finds a needle in a haystack and returns it position, mb strings accounted for
 *
 * @param string $haystack String to look in (haystack)
 * @param string $needle What to look for (needle)
 * @param int $offset (optional) How much to offset
 * @return int|bool false on needle not found, integer position if found
 */
function my_strpos($haystack, $needle, $offset=0)
{
	if($needle == '')
	{
		return false;
	}

	if(function_exists("mb_strpos"))
	{
		$position = mb_strpos($haystack, $needle, $offset);
	}
	else
	{
		$position = strpos($haystack, $needle, $offset);
	}

	return $position;
}

function my_strtolower($string)
{
	if(function_exists("mb_strtolower"))
	{
		$string = mb_strtolower($string);
	}
	else
	{
		$string = strtolower($string);
	}

	return $string;
}





function format_avatar2222222222222222222($avatar, $dimensions = '', $max_dimensions = '') 
{
    global $mybb, $theme, $allowremoteavatars, $useravatardims, $useravatar, $maxavatardims;
    static $avatars;

    if (!isset($avatars)) 
	{
        $avatars = [];
    }

    // Если аватар не задан, возвращаем HTML-заглушку
    if (!$avatar) 
	{
		
    return [
        'image' => '<svg class="avatar-ring2" width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
            <text x="50" y="55" text-anchor="middle" font-size="16" fill="#666">No Avatar</text>
        </svg>',
        'width_height' => ''
    ];
    }

    // Проверка на удалённые аватары (если запрещены)
    if (my_strpos($avatar, '://') !== false && !$allowremoteavatars) {
        $avatar = null;
    }

    // Если аватар всё ещё пустой (после проверки), возвращаем заглушку
    if (!$avatar) {
        return [
            'image' => '<div class="avatar-ring2">No Avatar</div>',
            'width_height' => ''
        ];
    }

    // Обработка стандартных аватаров (изображений)
    if (!$max_dimensions) {
        $max_dimensions = $maxavatardims;
    }

    $key = $dimensions ?: 'default';
    $key2 = $max_dimensions ?: 'default';

    if (is_scalar($avatar) && is_scalar($key) && is_scalar($key2)) {
        if (isset($avatars[$avatar][$key][$key2])) {
            return $avatars[$avatar][$key][$key2];
        }
    }

    $avatar_width_height = '';

    if (!empty($dimensions)) 
	{
        $dim_parts = preg_split('/[|x]/', $dimensions);
        if (!empty($dim_parts[0]) && !empty($dim_parts[1])) 
		{
            list($max_width, $max_height) = preg_split('/[|x]/', $max_dimensions);
            if (!empty($max_dimensions) && ($dim_parts[0] > $max_width || $dim_parts[1] > $max_height)) 
			{
                require_once INC_PATH . '/functions_image.php';
                $scaled = scale_image($dim_parts[0], $dim_parts[1], $max_width, $max_height);
                $avatar_width_height = "width=\"{$scaled['width']}\" height=\"{$scaled['height']}\"";
            } 
			else 
			{
                $avatar_width_height = "width=\"{$dim_parts[0]}\" height=\"{$dim_parts[1]}\"";
            }
        }
    }

    $avatars[$avatar][$key][$key2] = [
        'image' => htmlspecialchars_uni($mybb->get_asset_url($avatar)),
        'width_height' => $avatar_width_height
    ];

    return $avatars[$avatar][$key][$key2];
}













function format_avatar($avatar, $dimensions = '', $max_dimensions = '')
{
    global $mybb, $theme, $allowremoteavatars, $useravatardims, $useravatar, $maxavatardims;
    static $avatars;

    if (!isset($avatars)) {
        $avatars = [];
    }

    // helper: parse dims like "34|34" or "34x34"
    $parse_dims = static function($s) {
        if (!$s) return [null, null];
        $p = preg_split('/[|x]/', (string)$s);
        $w = isset($p[0]) && is_numeric($p[0]) ? (int)$p[0] : null;
        $h = isset($p[1]) && is_numeric($p[1]) ? (int)$p[1] : null;
        return [$w, $h];
    };

    // 1) пусто -> SVG заглушка
    if (!$avatar) {
        $html = '<svg class="avatar-ring2" width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">'
              . '<circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>'
              . '<text x="50" y="55" text-anchor="middle" font-size="16" fill="#666">No Avatar</text>'
              . '</svg>';
        return [
            'image'         => $html,     // для обратной совместимости
            'width_height'  => '',
            'html'          => $html,     // готовый HTML
            'is_html'       => true
        ];
    }

    // 2) запрет на удалённые (ловим http(s)://, //, data:)
    $is_remote = (bool)preg_match('~^(?:https?:)?//|data:image/~i', (string)$avatar);
    if ($is_remote && empty($allowremoteavatars)) {
        $html = '<div class="avatar-ring2">No Avatar</div>';
        return [
            'image'         => $html,
            'width_height'  => '',
            'html'          => $html,
            'is_html'       => true
        ];
    }

    // 3) кэш
    $key  = $dimensions ?: 'default';
    $key2 = $max_dimensions ?: 'default';
    if (is_scalar($avatar) && is_scalar($key) && is_scalar($key2)) {
        if (isset($avatars[$avatar][$key][$key2])) {
            return $avatars[$avatar][$key][$key2];
        }
    }

    // 4) размеры
    if (!$max_dimensions) {
        $max_dimensions = $maxavatardims; // например "100|100"
    }
    list($dw, $dh) = $parse_dims($dimensions);
    list($mw, $mh) = $parse_dims($max_dimensions);

    $avatar_width_height = '';
    if ($dw && $dh) {
        if ($mw && $mh && ($dw > $mw || $dh > $mh)) {
            // безопасно тянем functions_image.php
            if (!defined('INC_PATH')) {
                $root = defined('MYBB_ROOT') ? rtrim(MYBB_ROOT, '/\\') : dirname(__DIR__);
                if (is_dir($root.'/inc')) define('INC_PATH', $root.'/inc');
                elseif (is_dir($root.'/include')) define('INC_PATH', $root.'/include');
            }
            if (defined('INC_PATH') && file_exists(INC_PATH.'/functions_image.php')) {
                require_once INC_PATH . '/functions_image.php';
                $scaled = scale_image($dw, $dh, $mw, $mh);
                $avatar_width_height = "width=\"{$scaled['width']}\" height=\"{$scaled['height']}\"";
            } else {
                // fallback — просто обрежем до max без пропорций
                $w = min($dw, (int)$mw);
                $h = min($dh, (int)$mh);
                $avatar_width_height = "width=\"{$w}\" height=\"{$h}\"";
            }
        } else {
            $avatar_width_height = "width=\"{$dw}\" height=\"{$dh}\"";
        }
    }

    // 5) URL ассета (локальный или удалённый)
    // get_asset_url оставит абсолютный URL как есть
    $url = htmlspecialchars_uni($mybb->get_asset_url((string)$avatar));

    $result = [
        'image'         => $url,               // как раньше — строка (URL)
        'width_height'  => $avatar_width_height,
        'html'          => '<img src="'.$url.'" '.($avatar_width_height ?: '').' alt="avatar">', // готовый тег
        'is_html'       => false
    ];

    // кэш
    $avatars[$avatar][$key][$key2] = $result;
    return $result;
}



















function run_shutdown()
{
	global $config, $db, $cache, $plugins, $error_handler, $shutdown_functions, $shutdown_queries, $done_shutdown, $mybb;

	if($done_shutdown == true || !$config || (isset($error_handler) && $error_handler->has_errors))
	{
		return;
	}

	if(empty($shutdown_queries) && empty($shutdown_functions))
	{
		// Nothing to do
		return;
	}

	// Missing the core? Build
	if(!is_object($mybb))
	{
		require_once INC_PATH."/class_core.php";
		$mybb = new MyBB;

		// Load the settings
		require INC_PATH."/settings.php";
		//$mybb->settings = &$settings;
	}

	// If our DB has been deconstructed already (bad PHP 5.2.0), reconstruct
	if(!is_object($db))
	{
		if(!isset($config) || empty($config['database']['type']))
		{
			require INC_PATH."/config.php";
		}

		if(isset($config))
		{
			// Load DB interface
			require_once INC_PATH."/db_base.php";
			require_once INC_PATH . '/AbstractPdoDbDriver.php';

			require_once INC_PATH."/db_".$config['database']['type'].".php";
			switch($config['database']['type'])
			{
				case "sqlite":
					$db = new DB_SQLite;
					break;
				case "pgsql":
					$db = new DB_PgSQL;
					break;
				case "pgsql_pdo":
					$db = new PostgresPdoDbDriver();
					break;
				case "mysqli":
					$db = new DB_MySQLi;
					break;
				case "mysql_pdo":
					$db = new MysqlPdoDbDriver();
					break;
				default:
					$db = new DB_MySQL;
			}

			$db->connect($config['database']);
			if(!defined("TABLE_PREFIX"))
			{
				define("TABLE_PREFIX", $config['database']['table_prefix']);
			}
			$db->set_table_prefix(TABLE_PREFIX);
		}
	}

	// Cache object deconstructed? reconstruct
	if(!is_object($cache))
	{
		require_once INC_PATH."/class_datacache.php";
		$cache = new datacache;
		$cache->cache();
	}
    
	$no_plugins = "0";
	
	// And finally.. plugins
	if(!is_object($plugins) && !defined("NO_PLUGINS") && !($no_plugins == 1))
	{
		require_once INC_PATH."/class_plugins.php";
		$plugins = new pluginSystem;
		$plugins->load();
	}

	// We have some shutdown queries needing to be run
	if(is_array($shutdown_queries))
	{
		// Loop through and run them all
		foreach($shutdown_queries as $query)
		{
			$db->write_query($query);
		}
	}

	// Run any shutdown functions if we have them
	if(is_array($shutdown_functions))
	{
		foreach($shutdown_functions as $function)
		{
			call_user_func_array($function['function'], $function['arguments']);
		}
	}

	$done_shutdown = true;
}




function usergroup_displaygroup($gid)
{
	global $cache, $groupscache, $displaygroupfields;

	if(!is_array($groupscache))
	{
		$groupscache = $cache->read("usergroups");
	}

	$displaygroup = array();
	$group = $groupscache[$gid];

	foreach($displaygroupfields as $field)
	{
		$displaygroup[$field] = $group[$field];
	}

	return $displaygroup;
}


function format_name($username, $usergroup, $displaygroup=0)
{
	global $groupscache, $cache, $plugins;

	static $formattednames = array();

	if(!isset($formattednames[$username]))
	{
		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}

		if($displaygroup != 0)
		{
			$usergroup = $displaygroup;
		}

		$format = "{username}";

		if(isset($groupscache[$usergroup]))
		{
			$ugroup = $groupscache[$usergroup];

			if(strpos($ugroup['namestyle'], "{username}") !== false)
			{
				$format = $ugroup['namestyle'];
			}
		}

		$format = stripslashes($format);

		$parameters = compact('username', 'usergroup', 'displaygroup', 'format');

		$parameters = $plugins->run_hooks('format_name', $parameters);

		$format = $parameters['format'];

		$formattednames[$username] = str_replace("{username}", $username, $format);
	}

	return $formattednames[$username];
}


  function TS_Match( $string, $find )
  {
    return strpos( $string, $find ) === false ? false : true;
  }

  
  function TS_Global($name = "")
  {
    return isset($_GET[(string) $name]) ? !is_array($_GET[(string) $name]) ? trim($_GET[(string) $name]) : $_GET[(string) $name] : (isset($_POST[(string) $name]) ? !is_array($_POST[(string) $name]) ? trim($_POST[(string) $name]) : $_POST[(string) $name] : "");
  }
   
  
 
  


  function show_notice ($notice = '', $iserror = false, $title = '', $BR = '<br />')
  {
    global $BASEURL;
    global $lang;
    //$defaulttemplate = ts_template ();
    $imagepath = $BASEURL . '/include/templates/default/images/';
    $lastword = ($iserror ? 'e' : 'n');
    $uniqeid = md5 (time ());
    return '
	<script type="text/javascript">
		function ts_show_tag(id, status)
		{
			if (TSGetID(id)){if (status == true || status == false){TSGetID(id).style.display = (status == true)?"none":"";}
			else{TSGetID(id).style.display = (TSGetID(id).style.display == "")?"none":"";}}
		}
	</script>
	<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/notification.css" type="text/css" media="screen" />
	<div class="notification-border-' . $lastword . '" id="notification_' . $uniqeid . '" align="center">
		<table class="notification-th-' . $lastword . '" border="0" cellpadding="2" cellspacing="0">
			<tbody>
				<tr>
					<td align="left" width="100%" class="none">
					&nbsp;<img src="' . $imagepath . 'notification_' . $lastword . '.gif" alt="" align="top" border="0" height="14" width="14" />&nbsp;<span class="notification-title-' . $lastword . '" />' . ($title ? $title : $lang->global['sys_message']) . '</span>
					</td>
					<td class="none"><img src="' . $imagepath . 'notification_close.gif" alt="" onclick="ts_show_tag(\'notification_' . $uniqeid . '\', true);" class="hand" border="0" height="13" width="13" /></td>
				</tr>
			</tbody>
		</table>
		<div class="notification-body">
			' . $notice . '
		</div>
	</div>
	' . $BR;
  }

  function maxsysop ()
  {
    global $CURUSER;
    global $rootpath;
    global $lang;
    global $usergroups;
    if (is_mod ($usergroups))
    {
      $results = explode (',', file_get_contents (CONFIG_DIR . '/STAFFTEAM'));
      if (!in_array ($CURUSER['username'] . ':' . $CURUSER['id'], $results, true))
      {
        require_once INC_PATH . '/functions_pm.php';
        send_pm (1, 'Fake Account Detected: Username: ' . $CURUSER['username'] . ' - UserID: ' . $CURUSER['id'] . ' - UserIP : ' . getip (), 'Warning: Fake Account Detected!');
        write_log ('Fake Account Detected: Username: ' . $CURUSER['username'] . ' - UserID: ' . $CURUSER['id'] . ' - UserIP : ' . getip (), 'Warning: Fake Account Detected!');
        stderr ($lang->global['fakeaccount']);
      }

      unset ($results);
    }

  }

  function fix_url ($url)
  {
    $url = htmlspecialchars ($url);
    return str_replace (array ('&amp;', ' '), array ('&', '&nbsp;'), $url);
  }

  
  function htmlspecialchars_uni($message)
  {
    $message = $message ?? '';
    $message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message);
    $message = str_replace("<", "&lt;", $message);
    $message = str_replace(">", "&gt;", $message);
    $message = str_replace("\"", "&quot;", $message);
    return $message;
  }

  



function tsrowcount($column, $table, $where = '')
{
    global $db;

    // Очистка имени колонки и таблицы
    $column = preg_replace('/[^a-zA-Z0-9_*]/', '', $column);
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

    $sql = "SELECT COUNT($column) AS cnt FROM $table";

    // Если условия переданы массивом, строим безопасный WHERE
    if (is_array($where) && !empty($where)) 
	{
        $conds = [];
        foreach ($where as $key => $value) 
		{
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key); // чистим имя колонки
            if (is_int($value)) {
                $conds[] = "$key=$value";
            } 
			else 
			{
                $conds[] = "$key='" . $db->sql_escape($value) . "'";
            }
        }
        $sql .= " WHERE " . implode(' AND ', $conds);
    }
    // Если строка передана напрямую, используем как есть (должна быть безопасной!)
    elseif (is_string($where) && $where !== '') 
	{
        $sql .= " WHERE $where";
    }

    $res = $db->sql_query($sql);
    if (!$res) return 0;

    $row = $db->fetch_array($res);
    return (int)$row['cnt'];
}




  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  

  function write_log ($Text)
  {
    global $db;

	$insert_log = array(
		"added" => TIMENOW,
		"txt" => $db->escape_string($Text)
	);
	$db->insert_query("sitelog", $insert_log);
	
  }

  function kps ($Type = '+', $Points = '1.0', $ID = '')
  {
    global $bonus, $cache, $db;
    
	$kpscache = $cache->read("KPS");
	$bonus = $kpscache['bonus'];

    if (($bonus == 'enable' OR $bonus == 'disablesave'))
    {
      $db->sql_query ('' . 'UPDATE users SET seedbonus = seedbonus ' . $Type . ' \'' . $Points . '\' WHERE id = \'' . $ID . '\'');
    }

  }

  
  
  
  

  function isvalidip ($IP)
  {
    return (preg_match ('' . '/^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$/', $IP) ? true : false);
  }

    
  
function mksize22222222($bytes = 0)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max(0, (float)$bytes);
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $power = min($power, count($units) - 1);
    $value = $bytes / pow(1024, $power);

    return number_format($value, 2) . ' ' . $units[$power];
}




function mksize($bytes = 0)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
    $bytes = (float)$bytes;

    // Если <= 0, сразу возвращаем "0 B"
    if ($bytes <= 0) {
        return '0 B';
    }

    // Считаем степень
    $power = floor(log($bytes, 1024));
    $power = max(0, min($power, count($units) - 1));

    // Рассчитываем значение
    $value = $bytes / pow(1024, $power);

    // Ограничиваем числа больше EB
    if ($power === count($units) - 1 && $bytes >= pow(1024, $power + 1)) {
        return number_format($value, 2) . ' ' . $units[$power] . '+';
    }

    return number_format($value, 2) . ' ' . $units[$power];
}

  
  
  
  
  
  function mksecret($length = 20, $UseNumbers = true)
  {
    if ($UseNumbers) 
	{
	$set = array ("a", "A", "b", "B", "c", "C", "d", "D", "e", "E", "f", "F", "g", "G", "h", "H", "i", "I", "j", "J", "k", "K", "l", "L", "m", "M", "n", "N", "o", "O", "p", "P", "q", "Q", "r", "R", "s", "S", "t", "T", "u", "U", "v", "V", "w", "W", "x", "X", "y", "Y", "z", "Z", "1", "2", "3", "4", "5", "6", "7", "8", "9");
    } 
	else 
	{
        $set = array ("a", "A", "b", "B", "c", "C", "d", "D", "e", "E", "f", "F", "g", "G", "h", "H", "i", "I", "j", "J", "k", "K", "l", "L", "m", "M", "n", "N", "o", "O", "p", "P", "q", "Q", "r", "R", "s", "S", "t", "T", "u", "U", "v", "V", "w", "W", "x", "X", "y", "Y", "z", "Z");
    }
    $str = "";
    for ($i = 1; $i <= $length; $i++) 
	{
        $ch = rand(0, count($set) - 1);
        $str .= $set[$ch];
    }
    return $str;
  }
  
  
  
  
  function getip ()
  {
    $alt_ip = $_SERVER['REMOTE_ADDR'];
    if (isset ($_SERVER['HTTP_CLIENT_IP']))
    {
      $alt_ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    else
    {
      if ((isset ($_SERVER['HTTP_X_FORWARDED_FOR']) AND preg_match_all ('#\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)))
      {
        foreach ($matches[0] as $ip)
        {
          if (!preg_match ('#^(10|172\\.16|192\\.168)\\.#', $ip))
          {
            $alt_ip = $ip;
            break;
          }
        }
      }
      else
      {
        if (isset ($_SERVER['HTTP_FROM']))
        {
          $alt_ip = $_SERVER['HTTP_FROM'];
        }
      }
    }

    return htmlspecialchars ($alt_ip);
  }
  
  

  function securehash ($var = NULL)
  {
    global $SITENAME;
    global $securehash;
    return md5 (md5 ($var) . getip () . md5 ($securehash . $SITENAME));
  }
  
 
  
  
  
  function generate_passkey($username, $loginkey)
  { 
     global $securehash, $SITENAME;

     if (empty($username) || empty($loginkey)) 
	 {
        return false; // Ошибка, если не переданы данные
     }

     // Формируем ключ без IP, чтобы он оставался стабильным
     $passkey = md5($username . TIMENOW . $loginkey . md5($securehash . $SITENAME));

     return $passkey;
  }
  
  
  
  
  
  


  //function parked ()
  //{
    //global $CURUSER;
    //global $lang;
    //if (preg_match ('#A1#is', $CURUSER['options']))
    //{
      //stderr ($lang->global['parked']);
    //}

  //}

  function gzip ($use = false)
  {
    global $gzipcompress;
    if ((((($gzipcompress == 'yes' OR $use) AND @extension_loaded ('zlib')) AND @ini_get ('zlib.output_compression') != '1') AND @ini_get ('output_handler') != 'ob_gzhandler'))
    {
      @ob_start ('ob_gzhandler');
    }

  }
  
  
  
  
  


  function warn_donor ($s, $warnday = 3)
  {
    if ($s < 0)
    {
      $s = 0;
    }

    $t = array ();
    foreach (array ('60:sec', '60:min', '24:hour', '0:day') as $x)
    {
      $y = explode (':', $x);
      if (1 < $y[0])
      {
        $v = $s % $y[0];
        $s = floor ($s / $y[0]);
      }
      else
      {
        $v = $s;
      }

      $t[$y[1]] = $v;
    }

    if ($t['day'] < $warnday)
    {
      return true;
    }

    return false;
  }

  function cutename ($name, $max = 35)
  {
    return htmlspecialchars_uni (($max < strlen ($name) ? substr ($name, 0, $max) . '...' : $name));
  }
  
  function cutename2 ($name, $max = 25)
  {
    return htmlspecialchars_uni (($max < strlen ($name) ? substr ($name, 0, $max) . '...' : $name));
  }
  

  function get_extension ($file)
  {
    return strtolower (substr (strrchr ($file, '.'), 1));
  }

  function dir_list ($dir)
  {
    $dl = array ();
    $ext = '';
    if (!file_exists ($dir))
    {
      error ();
    }

    if ($hd = opendir ($dir))
    {
      while ($sz = readdir ($hd))
      {
        $ext = get_extension ($sz);
        if ((preg_match ('/^\\./', $sz) == 0 AND $ext != 'php'))
        {
          $dl[] = $sz;
          continue;
        }
      }

      closedir ($hd);
      asort ($dl);
      return $dl;
    }

    error ('', 'Couldn\'t open storage folder! Please check the path.');
  }

 
  
  
  function ts_nf($number)
  {
    if (!is_numeric($number)) 
	{
        return '0';
    }
    return number_format($number, 0, '.', ',');
  }


  
  function is_mod($user = array ())
  {
    return isset($user["cansettingspanel"]) && $user["cansettingspanel"] === '1' || isset($user["issupermod"]) && $user["issupermod"] === '1' || isset($user["canstaffpanel"]) && $user["canstaffpanel"] === '1' ? true : false;
  }

  function highlight ($search, $subject, $hlstart = '<b><font color=\'#f7071d\'>', $hlend = '</font></b>')
  {
    $srchlen = strlen ($search);
    if ($srchlen == 0)
    {
      return $subject;
    }

    $find = $subject;
    while ($find = stristr ($find, $search))
    {
      $srchtxt = substr ($find, 0, $srchlen);
      $find = substr ($find, $srchlen);
      $subject = str_replace ($srchtxt, '' . $hlstart . $srchtxt . $hlend, $subject);
    }

    return $subject;
  }


  function get_user_color ($username, $namestyle, $white = false)
  {
    if ($white)
    {
      $new_username = '<font color="#ffffff">' . $username . '</font>';
    }
    else
    {
      $new_username = str_replace ('{username}', $username, $namestyle);
    }

    return $new_username;
  }

  function int_check ($value, $stdhead = false, $stdfood = true, $die = true, $log = true)
  {
    global $CURUSER;
    global $BASEURL;
    global $lang;
	global $db;
    $msg = sprintf ($lang->global['invalididlogmsg'], htmlspecialchars_uni ($_SERVER['REQUEST_URI']), '<a href="' . $BASEURL . '/userdetails.php?id=' . $CURUSER['id'] . '">' . $CURUSER['username'] . '</a>', get_ip (), get_date_time ());
    if (is_array ($value))
    {
      foreach ($value as $val)
      {
        int_check ($val, $stdhead, $stdfood, $die, $log);
      }

      return null;
    }

    if (!is_valid_id ($value))
    {
      if ($stdhead)
      {
        if ($log)
        {
          write_log ($msg);
        }

        stderr ($lang->global['invalididlogged']);
      }
      else
      {
        print $lang->global['invalididlogged2'];
        if ($log)
        {
          write_log ($msg);
        }
      }

      if ($stdfood)
      {
        stdfoot ();
      }

      if ($die)
      {
        exit ();
        return null;
      }
    }
    else
    {
      return true;
    }

  }

  function is_valid_id ($id)
  {
    return ((is_numeric ($id) AND 0 < $id) AND floor ($id) == $id);
  }

  function flood_check ($type = '', $last = '', $shoutbox = false)
  {
    global $lang;
    global $usergroups;
    global $CURUSER;
    //$timecut = time () - $usergroups['floodlimit'];
	$timecut = TIMENOW - $usergroups['floodlimit'];
	
    if (strstr ($last, '-'))
    {
      $last = strtotime ($last);
    }

    if (($timecut <= $last AND $usergroups['floodlimit'] != 0))
    {
      $remaining_time = $usergroups['floodlimit'] - (TIMENOW - $last);
      if ($shoutbox == 0)
      {
        stderr (sprintf ($lang->global['flooderror'], $usergroups['floodlimit'], $type, $remaining_time), false);
        return null;
      }

      $msg = '<font color="#9f040b" size="2">' . sprintf ($lang->global['flooderror'], $usergroups['floodlimit'], $type, $remaining_time) . '</font>';
      return $msg;
    }

  }
  
  
  function print_no_permission ($log = false, $stdhead = true, $extra = '')
  {
    global $lang;
    global $SITENAME;
    global $BASEURL;
    global $CURUSER;
    if ($log)
    {
      $page = htmlspecialchars_uni ($_SERVER['SCRIPT_NAME']);
      $query = htmlspecialchars_uni ($_SERVER['QUERY_STRING']);
      $message = sprintf ($lang->global['permissionlogmessage'], $page, $query, '<a href="' . $BASEURL . '/userdetails.php?id=' . $CURUSER['id'] . '">' . $CURUSER['username'] . '</a>', $CURUSER['ip']);
      write_log ($message);
    }

    if ($stdhead)
    {
      //stdhead ('Permission Denied!');
      stderr( sprintf ($lang->global['print_no_permission'], $SITENAME, ($extra != '' ? '<b>' . $extra . '</b>' : $lang->global['print_no_permission_i'])));
      stdfoot ();
    }
    else
    {
      stderr( sprintf ($lang->global['print_no_permission'], $SITENAME, ($extra != '' ? '<b>' . $extra . '</b>' : $lang->global['print_no_permission_i'])));
      stdfoot ();
    }

    exit ();
  }
  
  
  



  function submit_disable ($formname = '', $buttonname = '', $text = '')
  {
    global $lang;
    $value = '' . 'onsubmit="document.' . $formname . '.' . $buttonname . '.value=\'' . ($text ? $text : $lang->global['pleasewait']) . ('' . '\';document.' . $formname . '.' . $buttonname . '.disabled=true"');
    return $value;
  }



function unichr_callback1($matches)
{
	return unichr(hexdec($matches[1]));
}

function unichr_callback2($matches)
{
	return unichr($matches[1]);
}



function unhtmlentities($string)
{
	// Replace numeric entities
	$string = preg_replace_callback('~&#x([0-9a-f]+);~i', 'unichr_callback1', $string);
	$string = preg_replace_callback('~&#([0-9]+);~', 'unichr_callback2', $string);

	// Replace literal entities
	$trans_tbl = get_html_translation_table(HTML_ENTITIES);
	$trans_tbl = array_flip($trans_tbl);

	return strtr($string, $trans_tbl);
}

function my_substr($string, $start, $length=null, $handle_entities = false)
{
	if($handle_entities)
	{
		$string = unhtmlentities($string);
	}
	if(function_exists("mb_substr"))
	{
		if($length != null)
		{
			$cut_string = mb_substr($string, $start, $length);
		}
		else
		{
			$cut_string = mb_substr($string, $start);
		}
	}
	else
	{
		if($length != null)
		{
			$cut_string = substr($string, $start, $length);
		}
		else
		{
			$cut_string = substr($string, $start);
		}
	}

	if($handle_entities)
	{
		$cut_string = htmlspecialchars_uni($cut_string);
	}
	return $cut_string;
}


function my_datee($format, $stamp=0, $offset="", $ty=1, $adodb=false)
{
	global $mybb, $lang, $plugins,$CURUSER,$dateformat,$timeformat,$regdateformat,$timezoneoffset,$dstcorrection,$datetimesep;

	// If the stamp isn't set, use TIME_NOW
	if(empty($stamp))
	{
		$stamp = TIMENOW;
	}

	if(!$offset && $offset != '0')
	{
		if(isset($CURUSER['id']) && $CURUSER['id'] != 0 && array_key_exists("timezone", $CURUSER))
		{
			$offset = (float)$CURUSER['timezone'];
			$dstcorrection = $CURUSER['dst'];
		}
		else
		{
			$offset = (float)$timezoneoffset;
			$dstcorrection = $dstcorrection;
		}

		// If DST correction is enabled, add an additional hour to the timezone.
		if($dstcorrection == 1)
		{
			++$offset;
			if(my_substr($offset, 0, 1) != "-")
			{
				$offset = "+".$offset;
			}
		}
	}

	if($offset == "-")
	{
		$offset = 0;
	}

	// Using ADOdb?
	if($adodb == true && !function_exists('adodb_date'))
	{
		$adodb = false;
	}

	$todaysdate = $yesterdaysdate = '';
	if($ty && ($format == $dateformat || $format == 'relative' || $format == 'normal'))
	{
		$_stamp = TIMENOW;
		if($adodb == true)
		{
			$date = adodb_date($dateformat, $stamp + ($offset * 3600));
			$todaysdate = adodb_date($dateformat, $_stamp + ($offset * 3600));
			$yesterdaysdate = adodb_date($dateformat, ($_stamp - 86400) + ($offset * 3600));
		}
		else
		{
			$date = gmdate($dateformat, $stamp + ($offset * 3600));
			$todaysdate = gmdate($dateformat, $_stamp + ($offset * 3600));
			$yesterdaysdate = gmdate($dateformat, ($_stamp - 86400) + ($offset * 3600));
		}
	}

	if($format == 'relative')
	{
		// Relative formats both date and time
		$real_date = $real_time = '';
		if($adodb == true)
		{
			$real_date = adodb_date($dateformat, $stamp + ($offset * 3600));
			$real_time = $datetimesep;
			$real_time .= adodb_date($timeformat, $stamp + ($offset * 3600));
		}
		else
		{
			$real_date = gmdate($dateformat, $stamp + ($offset * 3600));
			$real_time = $datetimesep;
			$real_time .= gmdate($timeformat, $stamp + ($offset * 3600));
		}

		if($ty != 2 && abs(TIMENOW - $stamp) < 3600)
		{
			$diff = TIMENOW - $stamp;
			$relative = array('prefix' => '', 'minute' => 0, 'plural' => 'minutes ', 'suffix' => 'ago');

			if($diff < 0)
			{
				$diff = abs($diff);
				$relative['suffix'] = '';
				$relative['prefix'] = $lang->global['rel_in'];
			}

			$relative['minute'] = floor($diff / 60);

			if($relative['minute'] <= 1)
			{
				$relative['minute'] = 1;
				$relative['plural'] = $lang->global['rel_minutes_single'];
			}

			if($diff <= 60)
			{
				// Less than a minute
				$relative['prefix'] = $lang->global['rel_less_than'];
			}

			$date = sprintf($lang->global['rel_time'], $relative['prefix'], $relative['minute'], $relative['plural'], $relative['suffix'], $real_date, $real_time);
		}
		elseif($ty != 2 && abs(TIMENOW - $stamp) < 43200)
		{
			$diff = TIMENOW - $stamp;
			$relative = array('prefix' => '', 'hour' => 0, 'plural' => $lang->global['rel_hours_plural'], 'suffix' => $lang->global['rel_ago']);

			if($diff < 0)
			{
				$diff = abs($diff);
				$relative['suffix'] = '';
				$relative['prefix'] = $lang->global['rel_in'];
			}

			$relative['hour'] = floor($diff / 3600);

			if($relative['hour'] <= 1)
			{
				$relative['hour'] = 1;
				$relative['plural'] = $lang->global['rel_hours_single'];
			}

		    $date = sprintf($lang->global['rel_time'], $relative['prefix'], $relative['hour'], $relative['plural'], $relative['suffix'], $real_date, $real_time);
		}
		else
		{
			if($ty)
			{
				if($todaysdate == $date)
				{
					$date = sprintf($lang->global['today_rel'], $real_date);
				}
				else if($yesterdaysdate == $date)
				{
					$date = sprintf($lang->global['yesterday_rel'], $real_date);
				}
			}

			$date .= $datetimesep;
			if($adodb == true)
			{
				$date .= adodb_date($timeformat, $stamp + ($offset * 3600));
			}
			else
			{
				$date .= gmdate($timeformat, $stamp + ($offset * 3600));
			}
		}
	}
	elseif($format == 'normal')
	{
		// Normal format both date and time
		if($ty != 2)
		{
			if($todaysdate == $date)
			{
				$date = $lang->global['today'];
			}
			else if($yesterdaysdate == $date)
			{
				$date = $lang->global['yesterday'];
			}
		}

		$date .= $datetimesep;
		if($adodb == true)
		{
			$date .= adodb_date($timeformat, $stamp + ($offset * 3600));
		}
		else
		{
			$date .= gmdate($timeformat, $stamp + ($offset * 3600));
		}
	}
	else
	{
		if($ty && $format == $dateformat)
		{
			if($todaysdate == $date)
			{
				$date = $lang->global['today'];
			}
			else if($yesterdaysdate == $date)
			{
				$date = $lang->global['yesterday'];
			}
		}
		else
		{
			if($adodb == true)
			{
				$date = adodb_date($format, $stamp + ($offset * 3600));
			}
			else
			{
				$date = gmdate($format, $stamp + ($offset * 3600));
			}
		}
	}

	if(is_object($plugins))
	{
		$date = $plugins->run_hooks("my_datee", $date);
	}

	return $date;
}



  function my_substrr ($string, $start, $length = '')
  {
    if (function_exists ('mb_substr'))
    {
      if ($length != '')
      {
        $cut_string = mb_substr ($string, $start, $length);
      }
      else
      {
        $cut_string = mb_substr ($string, $start);
      }
    }
    else
    {
      if ($length != '')
      {
        $cut_string = substr ($string, $start, $length);
      }
      else
      {
        $cut_string = substr ($string, $start);
      }
    }

    return $cut_string;
  }

  function get_date_time ($timestamp = 0)
  {
    if ($timestamp)
    {
      return date ('Y-m-d H:i:s', $timestamp);
    }

    return date ('Y-m-d H:i:s');
  }

  function gmtime ()
  {
    return strtotime (get_date_time ());
  }
  
  
  /////////////////////// FROM globalfunctions.php
  

  function unhtmlspecialchars ($text, $doUniCode = false)
  {
    if ($doUniCode)
    {
      $text = preg_replace ('/&#([0-9]+);/esiU', 'convert_int_to_utf8(\'\\1\')', $text);
    }

    return str_replace (array ('&lt;', '&gt;', '&quot;', '&amp;'), array ('<', '>', '"', '&'), $text);
  }

  function check_email ($email)
  {
    return preg_match ('#^[a-z0-9.!\\#$%&\'*+-/=?^_`{|}~]+@([0-9.]+|([^\\s\'"<>]+\\.+[a-z]{2,6}))$#si', $email);
  }

  function parse_email ($link = '', $text = '')
  {
    $rightlink = trim ($link);
    if (empty ($rightlink))
    {
      $rightlink = trim ($text);
    }

    $rightlink = str_replace (array ('`', '"', '\'', '['), array ('&#96;', '&quot;', '&#39;', '&#91;'), $rightlink);
    if ((!trim ($link) OR $text == $rightlink))
    {
      $tmp = unhtmlspecialchars ($rightlink);
      if (55 < strlen ($tmp))
      {
        $text = htmlspecialchars_uni (substr ($tmp, 0, 36) . '...' . substr ($tmp, 0 - 14));
      }
    }

    $rightlink = str_replace ('  ', '', $rightlink);
    if (check_email ($rightlink))
    {
      return '' . '<a href="mailto:' . $rightlink . '">' . $text . '</a>';
    }

    return $text;
  }
  
  function format_urls ($s, $target = '_blank')
  {
    return preg_replace ('/(\\A|[^=\\]\'"a-zA-Z0-9])((http|ftp|https|ftps|irc):\\/\\/[^()<>\\s]+)/i', '' . '\\1<a href="\\2" target="' . $target . '">\\2</a>', $s);
  }


 
  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>
