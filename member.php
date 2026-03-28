<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define("IGNORE_CLEAN_VARS", "sid");
define('THIS_SCRIPT', 'member.php');
define("SCRIPTNAME", "member.php");
define("ALLOWABLE_PAGE", "register,do_register,login,do_login,logout,lostpw,do_lostpw,activate,resendactivation,do_resendactivation,resetpassword,viewnotes");


$nosession['avatar'] = 1;

$templatelist = "maketable_torrents,torrents_completed,user_profile,torrent_stats,member_register,member_register_hiddencaptcha,member_register_coppa,member_register_agreement_coppa,member_register_agreement,member_register_customfield,member_register_requiredfields,member_profile_findthreads";
$templatelist .= ",member_loggedin_notice,member_profile_away,member_register_regimage,member_register_regimage_recaptcha_invisible,member_register_regimage_nocaptcha,post_captcha_hcaptcha_invisible,post_captcha_hcaptcha,post_captcha_hidden,post_captcha,member_register_referrer";
$templatelist .= ",member_profile_email,member_profile_offline,member_profile_warn,member_profile_warninglevel_link,member_profile_customfields_field,member_profile_customfields,member_profile_adminoptions_manageban,member_profile_adminoptions,member_profile";
$templatelist .= ",member_profile_signature,member_profile_avatar,member_profile_groupimage,member_referrals_link,member_profile_referrals,member_activate,member_lostpw,member_register_additionalfields";
$templatelist .= ",member_profile_modoptions_manageuser,member_profile_modoptions_editprofile,member_profile_modoptions_banuser,member_profile_modoptions_viewnotes,member_profile_modoptions_editnotes";
$templatelist .= ",usercp_profile_profilefields_select_option,usercp_profile_profilefields_multiselect,usercp_profile_profilefields_select,usercp_profile_profilefields_textarea,usercp_profile_profilefields_radio,member_viewnotes";
$templatelist .= ",usercp_options_timezone,usercp_options_timezone_option,usercp_options_language_option,member_profile_customfields_field_multi_item,member_profile_customfields_field_multi";
$templatelist .= ",member_profile_pm,member_profile_contact_details,member_profile_modoptions_manageban";
$templatelist .= ",member_profile_banned_remaining,member_profile_addremove,member_emailuser_guest,member_register_day,usercp_options_tppselect_option,postbit_warninglevel_formatted,member_profile_userstar,member_profile_findposts";
$templatelist .= ",usercp_options_tppselect,usercp_options_pppselect,member_resetpassword,member_login,member_profile_online,usercp_options_pppselect_option,postbit_reputation_formatted,member_emailuser,usercp_profile_profilefields_text";
$templatelist .= ",member_profile_modoptions_ipaddress,member_profile_modoptions,member_profile_banned,member_register_language,member_resendactivation,usercp_profile_profilefields_checkbox,member_register_password,member_coppa_form,torrent_stats";




require_once 'global.php';


define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);
require_once INC_PATH . '/tsf_functions.php';



// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

require_once INC_PATH."/functions_post.php";


require_once INC_PATH."/functions_timezone.php";

require_once INC_PATH.'/functions_mkprettytime.php';

require_once INC_PATH.'/functions_multipage.php';


require_once INC_PATH . '/functions_ratio.php';

require_once INC_PATH . '/readconfig.php';

//include_once INC_PATH . '/functions_login.php';

require_once INC_PATH . '/functions_icons.php';

require_once INC_PATH . '/function_loginattemptcheck.php';

require_once INC_PATH . '/functions_security.php';


require_once INC_PATH."/functions_user.php";

require_once INC_PATH."/functions_modcp.php";

require_once INC_PATH."/class_parser.php";
$parser = new postParser;



  





// ---- avatar upload action ----
if (isset($_GET['action']) && $_GET['action'] === 'upload_avatar') 
{
    // JSON helper
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $json = function($arr, $code = 200){
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    };

    // Авторизация
    if (!$CURUSER) 
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Не авторизован'], 401) : exit('Error: вы не авторизованы.');
    }
    // Текущий юзер (uid + группы)
    $user_uid   = (int)($CURUSER['id'] ?? $CURUSER['id'] ?? $CURUSER['id'] ?? 0);
    //$usergroups = (int)($mybb->user['usergroup'] ?? $CURUSER['usergroup'] ?? 0);

    if ($user_uid <= 0)
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Не авторизован'], 401) : exit('Error: вы не авторизованы.');
    }

    // UID профиля
    $uid = (int)($_POST['id'] ?? $_GET['id'] ?? ($memprofile['id'] ?? 0));
    if ($uid <= 0) {
        $is_ajax ? $json(['ok'=>false,'error'=>'Не указан uid профиля'], 400) : exit('Error: не указан uid профиля.');
    }

    // Права: владелец ИЛИ модератор
    if ($user_uid !== $uid && !is_mod($usergroups)) 
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Нет прав менять этот аватар'], 403)
                 : exit('Error: нет прав менять этот аватар.');
    }

    // CSRF при необходимости
    // if (empty($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
    //     $is_ajax ? $json(['ok'=>false,'error'=>'CSRF проверка не пройдена'], 403) : exit('Ошибка CSRF.');
    // }
	
	
	

    // Файл
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) 
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл не загружен'], 400) : exit('Error: file is not uploaded.');
    }

    // ЛИМИТ: 2 MB (если хочешь 22 MB — поставь 22*1024*1024 и поправь текст)
    $max_size    = 22 * 1024 * 1024; // 2 MB
    $allowed_ext = ['jpg','jpeg','png','gif','webp'];

    $file_name = $_FILES['avatar']['name'];
    $file_tmp  = $_FILES['avatar']['tmp_name'];
    $file_size = $_FILES['avatar']['size'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_ext, true)) 
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Allowed JPG/JPEG/PNG/GIF/WebP'], 415) : exit('Error: Allowed JPG/JPEG/PNG/GIF/WebP.');
    }
    if ($file_size > $max_size) 
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл слишком большой (макс. 2 MB)'], 413) : exit('Error: file is to big max (max. 22 MB).');
    }

    // MIME check (включая webp)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
        $is_ajax ? $json(['ok'=>false,'error'=>'file is not image'], 415) : exit('Error: file is not image.');
    }

    // Путь и имя
	$upload_dir = TSDIR . '/uploads/avatars/';
	
	
    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

    $new_name  = "avatar_{$uid}." . $file_ext;

    $dest_path = $upload_dir . $new_name;

    // Снести старые расширения
    foreach (['jpg','jpeg','png','gif','webp'] as $e) 
	{
        if ($e !== $file_ext) 
		{
            $p = $upload_dir . "avatar_{$uid}." . $e;
            if (is_file($p)) { @unlink($p); }
        }
    }

    if (!move_uploaded_file($file_tmp, $dest_path)) 
	{
        $is_ajax ? $json(['ok'=>false,'error'=>'Не удалось сохранить файл'], 500) : exit('Ошибка: не удалось сохранить файл.');
    }

    $size = @getimagesize($dest_path);
    if (!$size) {
        @unlink($dest_path);
        $is_ajax ? $json(['ok'=>false,'error'=>'Файл повреждён или не изображение'], 415) : exit('Ошибка: файл повреждён или не изображение.');
    }
    [$width, $height] = $size;
    $avatar_dimensions = $width . '|' . $height;

    // Относительный путь для вывода
    $avatar_url = "uploads/avatars/" . $new_name;


    // Обновить БД (у тебя в where — столбец id)
    $updated_avatar = [
        "avatar"           => $avatar_url,
        "avatardimensions" => $avatar_dimensions,
        "avatartype"       => "upload",
    ];
    $db->update_query("users", $updated_avatar, "id='{$uid}'");

    if ($is_ajax) {
        $json(['ok'=>true,'url'=>$avatar_url,'width'=>$width,'height'=>$height,'message'=>'Аватар обновлён']);
    }

    header("Location: member.php?action=profile&uid={$uid}");
    exit;
}







function fetch_ban_times()
{
	global $plugins, $lang;

	// Days-Months-Years
	$ban_times = array(
		"1-0-0" => "1 Day",
		"2-0-0" => "2 Days",
		"3-0-0" => "3 Days",
		"4-0-0" => "4 Days",
		"5-0-0" => "5 Days",
		"6-0-0" => "6 Days",
		"7-0-0" => "1 Week",
		"14-0-0" => "2 Weeks",
		"21-0-0" => "3 Weeks",
		"0-1-0" => "1 Month",
		"0-2-0" => "2 Months",
		"0-3-0" => "3 Months",
		"0-4-0" => "4 Months",
		"0-5-0" => "5 Months",
		"0-6-0" => "6 Months",
		"0-0-1" => "1 Year",
		"0-0-2" => "2 Years"
	);

	$ban_times = $plugins->run_hooks("functions_fetch_ban_times", $ban_times);

	$ban_times['---'] = 'Permanent';
	return $ban_times;
}







// Load global language phrases
$lang->load("member");






$mybb->input['action'] = $mybb->get_input('action');

// Make navigation
switch($mybb->input['action'])
{
	case "register":
	case "do_register":
		add_breadcrumb($lang->member['nav_register']);
		break;
	case "activate":
		add_breadcrumb($lang->member['nav_activate']);
		break;
	case "resendactivation":
		add_breadcrumb($lang->member['nav_resendactivation']);
		break;
	case "lostpw":
		add_breadcrumb($lang->member['nav_lostpw']);
		break;
	case "resetpassword":
		add_breadcrumb($lang->member['nav_resetpassword']);
		break;
	case "login":
		add_breadcrumb($lang->member['nav_login']);
		break;
	case "emailuser":
		add_breadcrumb($lang->member['nav_emailuser']);
		break;
}





function get_age($birthday)
{
	$bday = explode("-", $birthday);
	if(!$bday[2])
	{
		return;
	}

	list($day, $month, $year) = explode("-", my_datee("j-n-Y", TIMENOW, 0, 0));

	$age = $year-$bday[2];

	if(($month == $bday[1] && $day < $bday[0]) || $month < $bday[1])
	{
		--$age;
	}
	return $age;
}


function fix_mktime($format, $year)
{
	// Our little work around for the date < 1970 thing.
	// -2 idea provided by Matt Light (http://www.mephex.com)
	$format = str_replace("Y", $year, $format);
	$format = str_replace("y", my_substr($year, -2), $format);

	return $format;
}









if(($mybb->input['action'] == "register" || $mybb->input['action'] == "do_register") && $mybb->usergroup['cancp'] != 1)
{
	if($disableregs == 1)
	{
		stderr($lang->member['registrations_disabled']);
	}
	
	if (0 < intval($maxusers)) 
    {
        $Count = $db->num_rows($db->sql_query("SELECT id FROM users WHERE id > 0"));
        if ($maxusers <= $Count) 
		{
           stderr($lang->global["signuplimitreached"]);
        }
    }
	
	
	
	if($CURUSER['id'] != 0)
	{
		stderr($lang->member['error_alreadyregistered']);
	}
	if($betweenregstime && $maxregsbetweentime)
	{
		$time = TIMENOW;
		$datecut = $time-(60*60*$betweenregstime);
		$query = $db->simple_select("users", "*", "regip=".$db->escape_binary($session->packedip)." AND added > '$datecut'");
		$regcount = $db->num_rows($query);
		if($regcount >= $maxregsbetweentime)
		{
			$error_alreadyregisteredtime = sprintf($lang->member['error_alreadyregisteredtime'], $regcount, $betweenregstime);
			stderr($error_alreadyregisteredtime);
		}
	}
}

$fromreg = 0;
if($mybb->input['action'] == "do_register" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_register_start");

	// Are checking how long it takes for users to register?
	if($mybb->settings['regtime'] > 0)
	{
		// Is the field actually set?
		if(isset($mybb->input['regtime']))
		{
			// Check how long it took for this person to register
			$timetook = TIMENOW - $mybb->get_input('regtime', MyBB::INPUT_INT);

			// See if they registered faster than normal
			if($timetook < $mybb->settings['regtime'])
			{
				// This user registered pretty quickly, bot detected!
				$lang->error_spam_deny_time = sprintf($lang->error_spam_deny_time, $mybb->settings['regtime'], $timetook);
				error($lang->error_spam_deny_time);
			}
		}
		else
		{
			error($lang->error_spam_deny);
		}
	}

	

	
	if($regtype == "randompass")
	{

		$password_length = (int)$minpasswordlength;
		if($password_length < 8)
		{
			$password_length = min(8, (int)$maxpasswordlength);
		}

		$mybb->input['password'] = random_str($password_length, $requirecomplexpasswords);
		$mybb->input['password2'] = $mybb->input['password'];
	}

	if($regtype == "verify" || $regtype == "admin" || $regtype == "both" || $mybb->get_input('coppa', MyBB::INPUT_INT) == 1)
	{
		$usergroup = 5;
	}
	else
	{
		$usergroup = 2;
	}

	// Set up user handler.
	require_once INC_PATH."/datahandlers/user.php";
	$userhandler = new UserDataHandler("insert");

	$coppauser = 0;
	if(isset($mybb->cookies['coppauser']))
	{
		$coppauser = (int)$mybb->cookies['coppauser'];
	}
	
	
	
    
		
		
	// Set the data for the new user.
	$user = array(
		"username" => $mybb->get_input('username'),
		"password" => $mybb->get_input('password'),
		"password2" => $mybb->get_input('password2'),
		
		"passhint" => $mybb->get_input('passhint'),
		"hintanswer" => $mybb->get_input('hintanswer'),
		
		"invitehash" => $mybb->get_input('invitehash'),
		
		"email" => $mybb->get_input('email'),
		"email2" => $mybb->get_input('email2'),
		"usergroup" => $usergroup,
		"referrer" => $mybb->get_input('referrername'),
		"timezone" => $mybb->get_input('timezoneoffset'),
		"language" => $mybb->get_input('language'),
		"profile_fields" => $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY),
		"regip" => $session->packedip,
		"coppa_user" => $coppauser,
		"regcheck1" => $mybb->get_input('regcheck1'),
		"regcheck2" => $mybb->get_input('regcheck2'),
		"registration" => true
	);

	// Do we have a saved COPPA DOB?
	if(isset($mybb->cookies['coppadob']))
	{
		list($dob_day, $dob_month, $dob_year) = explode("-", $mybb->cookies['coppadob']);
		$user['birthday'] = array(
			"day" => $dob_day,
			"month" => $dob_month,
			"year" => $dob_year
		);
	}

	$user['options'] = array(
		"allownotices" => $mybb->get_input('allownotices', MyBB::INPUT_INT),
		"hideemail" => $mybb->get_input('hideemail', MyBB::INPUT_INT),
		"subscriptionmethod" => $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT),
		"receivepms" => $mybb->get_input('receivepms', MyBB::INPUT_INT),
		"pmnotice" => $mybb->get_input('pmnotice', MyBB::INPUT_INT),
		"pmnotify" => $mybb->get_input('pmnotify', MyBB::INPUT_INT),
		"invisible" => $mybb->get_input('invisible', MyBB::INPUT_INT),
		"dstcorrection" => $mybb->get_input('dstcorrection')
	);

	$userhandler->set_data($user);

	$errors = array();

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	if($mybb->settings['enablestopforumspam_on_register'])
	{
		require_once MYBB_ROOT . '/inc/class_stopforumspamchecker.php';

		$stop_forum_spam_checker = new StopForumSpamChecker(
			$plugins,
			$mybb->settings['stopforumspam_min_weighting_before_spam'],
			$mybb->settings['stopforumspam_check_usernames'],
			$mybb->settings['stopforumspam_check_emails'],
			$mybb->settings['stopforumspam_check_ips'],
			$mybb->settings['stopforumspam_log_blocks']
		);

		try {
			if($stop_forum_spam_checker->is_user_a_spammer($user['username'], $user['email'], get_ip()))
			{
				error(sprintf($lang->error_stop_forum_spam_spammer,
						$stop_forum_spam_checker->getErrorText(array(
							'stopforumspam_check_usernames',
							'stopforumspam_check_emails',
							'stopforumspam_check_ips'
							))));
			}
		}
		catch (Exception $e)
		{
			if($mybb->settings['stopforumspam_block_on_error'])
			{
				error($lang->error_stop_forum_spam_fetching);
			}
		}
	}

	



	$regerrors = '';
	if(!empty($errors))
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
		$email = htmlspecialchars_uni($mybb->get_input('email'));
		$email2 = htmlspecialchars_uni($mybb->get_input('email2'));
		$referrername = htmlspecialchars_uni($mybb->get_input('referrername'));

		$allownoticescheck = $hideemailcheck = $no_auto_subscribe_selected = $instant_email_subscribe_selected = $instant_pm_subscribe_selected = $no_subscribe_selected = '';
		$receivepmscheck = $pmnoticecheck = $pmnotifycheck = $invisiblecheck = $dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';

		if($mybb->get_input('allownotices', MyBB::INPUT_INT) == 1)
		{
			$allownoticescheck = "checked=\"checked\"";
		}

		if($mybb->get_input('hideemail', MyBB::INPUT_INT) == 1)
		{
			$hideemailcheck = "checked=\"checked\"";
		}

		if($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) == 1)
		{
			$no_subscribe_selected = "selected=\"selected\"";
		}
		else if($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) == 2)
		{
			$instant_email_subscribe_selected = "selected=\"selected\"";
		}
		else if($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) == 3)
		{
			$instant_pm_subscribe_selected = "selected=\"selected\"";
		}
		else
		{
			$no_auto_subscribe_selected = "selected=\"selected\"";
		}

		if($mybb->get_input('receivepms', MyBB::INPUT_INT) == 1)
		{
			$receivepmscheck = "checked=\"checked\"";
		}

		if($mybb->get_input('pmnotice', MyBB::INPUT_INT) == 1)
		{
			$pmnoticecheck = " checked=\"checked\"";
		}

		if($mybb->get_input('pmnotify', MyBB::INPUT_INT) == 1)
		{
			$pmnotifycheck = "checked=\"checked\"";
		}

		if($mybb->get_input('invisible', MyBB::INPUT_INT) == 1)
		{
			$invisiblecheck = "checked=\"checked\"";
		}

		if($mybb->get_input('dstcorrection', MyBB::INPUT_INT) == 2)
		{
			$dst_auto_selected = "selected=\"selected\"";
		}
		else if($mybb->get_input('dstcorrection', MyBB::INPUT_INT) == 1)
		{
			$dst_enabled_selected = "selected=\"selected\"";
		}
		else
		{
			$dst_disabled_selected = "selected=\"selected\"";
		}

		$regerrors = inline_error($errors);
		$mybb->input['action'] = "register";
		$fromreg = 1;
	}
	else
	{
		$user_info = $userhandler->insert_user();

		

		if($regtype != "randompass" && empty($mybb->cookies['coppauser']))
		{
			// Log them in
			my_setcookie("mybbuser", $user_info['uid']."_".$user_info['loginkey'], null, true, "lax");
		}

		if(!empty($mybb->cookies['coppauser']))
		{
			$lang->redirect_registered_coppa_activate = sprintf($lang->redirect_registered_coppa_activate, $SITENAME, htmlspecialchars_uni($user_info['username']));
			my_unsetcookie("coppauser");
			my_unsetcookie("coppadob");
			$plugins->run_hooks("member_do_register_end");
			error($lang->redirect_registered_coppa_activate);
		}
		else if($regtype == "verify")
		{
			$activationcode = random_str();
			$now = TIMENOW;
			$activationarray = array(
				"uid" => $user_info['uid'],
				"dateline" => TIMENOW,
				"code" => $activationcode,
				"type" => "r"
			);
			$db->insert_query("awaitingactivation", $activationarray);
			$emailsubject = sprintf($lang->member['emailsubject_activateaccount'], $SITENAME);
			switch($username_method)
			{
				case 0:
					$emailmessage = sprintf($lang->member['email_activateaccount'], $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
				case 1:
					$emailmessage = sprintf($lang->member['email_activateaccount1'], $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
				case 2:
					$emailmessage = sprintf($lang->member['email_activateaccount2'], $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
				default:
					$emailmessage = sprintf($lang->member['email_activateaccount'], $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
			}
			my_mail($user_info['email'], $emailsubject, $emailmessage);

			$redirect_registered_activation = sprintf($lang->member['redirect_registered_activation'], $SITENAME, htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			stderr($redirect_registered_activation);
		}
		else if($regtype == "randompass")
		{
			$emailsubject = sprintf($lang->member['emailsubject_randompassword'], $SITENAME);
			switch($username_method)
			{
				case 0:
					$emailmessage = sprintf($lang->member['email_randompassword'], $user['username'], $SITENAME, $user_info['username'], $mybb->get_input('password'));
					break;
				case 1:
					$emailmessage = sprintf($lang->member['email_randompassword1'], $user['username'], $SITENAME, $user_info['username'], $mybb->get_input('password'));
					break;
				case 2:
					$emailmessage = sprintf($lang->member['email_randompassword2'], $user['username'], $SITENAME, $user_info['username'], $mybb->get_input('password'));
					break;
				default:
					$emailmessage = sprintf($lang->member['email_randompassword'], $user['username'], $SITENAME, $user_info['username'], $mybb->get_input('password'));
					break;
			}
			my_mail($user_info['email'], $emailsubject, $emailmessage);
			
			
			$noperm_array = array (
		       "ustatus" => 'confirmed'
	        );
			$db->update_query("users", $noperm_array, "id='{$user_info['uid']}' AND ustatus='pending' AND enabled = 'yes'");
			
			
			require_once INC_PATH . '/functions_pm.php';
			
			$pm = array(
				'subject' => sprintf ($lang->member['welcomepmsubject'], $SITENAME),
				'message' => sprintf ($lang->member['welcomepmbody'], $user_info['username'], $SITENAME, $BASEURL),
				'touid' => $user_info['uid']
			);
							
			/// Workaround for eliminating PHP warnings in PHP 8. Ref: https://github.com/mybb/mybb/issues/4630#issuecomment-1369144163
			$pm['sender']['uid'] = -1;
			send_pm($pm, -1, true);
			

			$plugins->run_hooks("member_do_register_end");

			stderr($lang->member['redirect_registered_passwordsent']);
		}
		else if($regtype == "admin")
		{
		

			$redirect_registered_admin_activate = sprintf($lang->member['redirect_registered_admin_activate'], $SITENAME, htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			
			stderr($redirect_registered_admin_activate);
		
		}
		else if($regtype == "both")
		{
			$groups = $cache->read("usergroups");
			$admingroups = array();
			if(!empty($groups)) // Shouldn't be...
			{
				foreach($groups as $group)
				{
					if($group['cancp'] == 1)
					{
						$admingroups[] = (int)$group['gid'];
					}
				}
			}

			if(!empty($admingroups))
			{
				$sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
				foreach($admingroups as $admingroup)
				{
					switch($db->type)
					{
						case 'pgsql':
						case 'sqlite':
							$sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
							break;
						default:
							$sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
							break;
					}
				}
				$q = $db->simple_select('users', 'id,username,email,language', $sqlwhere);
				while($recipient = $db->fetch_array($q))
				{
					// First we check if the user's a super admin: if yes, we don't care about permissions
					$is_super_admin = is_super_admin($recipient['uid']);
					if(!$is_super_admin)
					{
						// Include admin functions
						if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
						{
							continue;
						}

						require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";

						// Verify if we have permissions to access user-users
						require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
							// Get admin permissions
							$adminperms = get_admin_permissions($recipient['uid']);
							if(empty($adminperms['user']['users']) || $adminperms['user']['users'] != 1)
							{
								continue; // No permissions
							}
					}

					// Load language
					if($recipient['language'] != $lang->language && $lang->language_exists($recipient['language']))
					{
						$reset_lang = true;
						$lang->set_language($recipient['language']);
						$lang->load("member");
					}

					$subject = sprintf($lang->newregistration_subject, $SITENAME);
					$message = sprintf($lang->newregistration_message, $recipient['username'], $SITENAME, $user['username']);
					my_mail($recipient['email'], $subject, $message);
				}

				// Reset language
				if(isset($reset_lang))
				{
					$lang->set_language($mybb->settings['bblanguage']);
					$lang->load("member");
				}
			}

			$activationcode = random_str();
			$activationarray = array(
				"uid" => $user_info['uid'],
				"dateline" => TIMENOW,
				"code" => $activationcode,
				"type" => "b"
			);
			$db->insert_query("awaitingactivation", $activationarray);
			$emailsubject = sprintf($lang->emailsubject_activateaccount, $SITENAME);
			switch($username_method)
			{
				case 0:
					$emailmessage = sprintf($lang->email_activateaccount, $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
				case 1:
					$emailmessage = sprintf($lang->email_activateaccount1, $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
				case 2:
					$emailmessage = sprintf($lang->email_activateaccount2, $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
				default:
					$emailmessage = sprintf($lang->email_activateaccount, $user_info['username'], $SITENAME, $BASEURL, $user_info['uid'], $activationcode);
					break;
			}
			my_mail($user_info['email'], $emailsubject, $emailmessage);

			$redirect_registered_activation = sprintf($lang->member['redirect_registered_activation'], $SITENAME, htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			stderr($redirect_registered_activation);
		}
		else
		{
			
	
			
			$noperm_array = array (
		       "ustatus" => 'confirmed'
	        );

	   
			$db->update_query("users", $noperm_array, "id='{$user_info['uid']}' AND ustatus='pending' AND enabled = 'yes'");
			
			require_once INC_PATH . '/functions_pm.php';
			
			$pm = array(
				'subject' => sprintf ($lang->member['welcomepmsubject'], $SITENAME),
				'message' => sprintf ($lang->member['welcomepmbody'], $user_info['username'], $SITENAME, $BASEURL),
				'touid' => $user_info['uid']
			);
							
			/// Workaround for eliminating PHP warnings in PHP 8. Ref: https://github.com/mybb/mybb/issues/4630#issuecomment-1369144163
			$pm['sender']['uid'] = -1;
			send_pm($pm, -1, true);
			
			
			
			$redirect_registered = sprintf($lang->member['redirect_registered'], $SITENAME, htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			redirect("index.php", $redirect_registered);
			
		}
	}
}


$faxno = "";


if($mybb->input['action'] == "coppa_form")
{
	if(!$faxno)
	{
		$faxno = "&nbsp;";
	}

	$plugins->run_hooks("member_coppa_form");

	eval("\$coppa_form = \"".$templates->get("member_coppa_form")."\";");
	
	echo $coppa_form;
}

if($mybb->input['action'] == "register")
{
	$bdaysel = '';
	if($coppa == "disabled")
	{
		$bdaysel = $bday2blank = '';
	}
	$mybb->input['bday1'] = $mybb->get_input('bday1', MyBB::INPUT_INT);
	for($day = 1; $day <= 31; ++$day)
	{
		$selected = '';
		if($mybb->input['bday1'] == $day)
		{
			$selected = " selected=\"selected\"";
		}

		eval("\$bdaysel .= \"".$templates->get("member_register_day")."\";");
	}

	$mybb->input['bday2'] = $mybb->get_input('bday2', MyBB::INPUT_INT);
	$bdaymonthsel = array();
	foreach(range(1, 12) as $number)
	{
		$bdaymonthsel[$number] = '';
	}
	$bdaymonthsel[$mybb->input['bday2']] = "selected=\"selected\"";
	$birthday_year = $mybb->get_input('bday3', MyBB::INPUT_INT);

	if($birthday_year == 0)
	{
		$birthday_year = '';
	}

	$under_thirteen = false;
	
	
	// Is COPPA checking enabled?
	if($coppa != "disabled" && !isset($mybb->input['step']))
	{
		// Just selected DOB, we check
		if($mybb->input['bday1'] && $mybb->input['bday2'] && $birthday_year)
		{
			my_unsetcookie("coppauser");

			$months = get_bdays($birthday_year);
			if($mybb->input['bday2'] < 1 || $mybb->input['bday2'] > 12 || $birthday_year < (date("Y")-100) || $birthday_year > date("Y") || $mybb->input['bday1'] > $months[$mybb->input['bday2']-1])
			{
				error($lang->error_invalid_birthday);
			}

			$bdaytime = @mktime(0, 0, 0, $mybb->input['bday2'], $mybb->input['bday1'], $birthday_year);

			// Store DOB in cookie so we can save it with the registration
			my_setcookie("coppadob", "{$mybb->input['bday1']}-{$mybb->input['bday2']}-{$birthday_year}", -1);

			// User is <= 13, we mark as a coppa user
			if($bdaytime >= mktime(0, 0, 0, my_datee('n'), my_datee('d'), my_datee('Y')-13))
			{
				my_setcookie("coppauser", 1, -0);
				$under_thirteen = true;
			}
			else
			{
				my_setcookie("coppauser", 0, -0);
			}
			$mybb->request_method = "";
		}
		// Show DOB select form
		else
		{
			$plugins->run_hooks("member_register_coppa");

			my_unsetcookie("coppauser");

			$coppa_desc = $coppa == 'deny' ? $lang->member['coppa_desc_for_deny'] : $lang->member['coppa_desc'];
			eval("\$coppa = \"".$templates->get("member_register_coppa")."\";");
			
			stdhead('title');
			
			echo $coppa;
			
			exit;
		}
	}

	if((!isset($mybb->input['agree']) && !isset($mybb->input['regsubmit'])) && $fromreg == 0 || $mybb->request_method != "post")
	{
		$coppa_agreement = '';
		// Is this user a COPPA user? We need to show the COPPA agreement too
		
		
		
		if($coppa != "disabled" && (!empty($mybb->cookies['coppauser']) || $under_thirteen))
		{
			if($coppa == "deny")
			{
				stderr($lang->member['error_need_to_be_thirteen']);
			}
			$coppa_agreement_1 = sprintf($lang->member['coppa_agreement_1'], $SITENAME);
			eval("\$coppa_agreement = \"".$templates->get("member_register_agreement_coppa")."\";");
		}

		$plugins->run_hooks("member_register_agreement");

		eval("\$agreement = \"".$templates->get("member_register_agreement")."\";");
		
		stdhead();
		
		echo $agreement;
	}
	else
	{
		$plugins->run_hooks("member_register_start");

		// JS validator extra
		
		if($maxnamelength > 0 && $minnamelength > 0)
		{
			$js_validator_username_length = sprintf($lang->member['js_validator_username_length'], $minnamelength, $maxnamelength);
		}

		if(isset($mybb->input['timezoneoffset']))
		{
			$timezoneoffset = $mybb->get_input('timezoneoffset');
		}
		else
		{
			$timezoneoffset = $timezoneoffset;
		}
		$tzselect = build_timezone_select("timezoneoffset", $timezoneoffset, true);

		
		

		if($usertppoptions)
		{
			$tppoptions = '';
			$explodedtpp = explode(",", $usertppoptions);
			if(is_array($explodedtpp))
			{
				foreach($explodedtpp as $val)
				{
					$val = trim($val);
					$tpp_option = sprintf($lang->member['tpp_option'], $val);
					eval("\$tppoptions .= \"".$templates->get("usercp_options_tppselect_option")."\";");
				}
			}
			eval("\$tppselect = \"".$templates->get("usercp_options_tppselect")."\";");
		}
		if($userpppoptions)
		{
			$pppoptions = '';
			$explodedppp = explode(",", $userpppoptions);
			if(is_array($explodedppp))
			{
				foreach($explodedppp as $val)
				{
					$val = trim($val);
					$ppp_option = sprintf($lang->member['ppp_option'], $val);
					eval("\$pppoptions .= \"".$templates->get("usercp_options_pppselect_option")."\";");
				}
			}
			eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
		}
		
		
		
		
		
		$referrer = '';
		
		$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);
		// Custom profile fields baby!
		$altbg = "trow1";
		$requiredfields = $customfields = '';

		if($regtype == "verify" || $regtype == "admin" || $regtype == "both" || $mybb->get_input('coppa', MyBB::INPUT_INT) == 1)
		{
			$usergroup = 5;
		}
		else
		{
			$usergroup = 2;
		}

		$pfcache = $cache->read('profilefields');

		if(is_array($pfcache))
		{
			$jsvar_reqfields = array();
			foreach($pfcache as $profilefield)
			{
				if($profilefield['required'] != 1 && $profilefield['registration'] != 1 || !is_member($profilefield['editableby'], array('usergroup' => $mybb->user['usergroup'], 'additionalgroups' => $usergroup)))
				{
					continue;
				}

				$code = $select = $val = $options = $expoptions = $useropts = '';
				$seloptions = array();
				$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
				$thing = explode("\n", $profilefield['type'], 2);
				$type = trim($thing[0]);
				$options = isset($thing[1]) ? $thing[1] : null;
				$select = '';
				$field = "fid{$profilefield['fid']}";
				$profilefield['description'] = htmlspecialchars_uni($profilefield['description']);
				$profilefield['name'] = htmlspecialchars_uni($profilefield['name']);
				if(!empty($errors) && isset($mybb->input['profile_fields'][$field]))
				{
					$userfield = $mybb->input['profile_fields'][$field];
				}
				else
				{
					$userfield = '';
				}
				if($type == "multiselect")
				{
					if(!empty($errors))
					{
						$useropts = $userfield;
					}
					else
					{
						$useropts = explode("\n", $userfield);
					}
					if(is_array($useropts))
					{
						foreach($useropts as $key => $val)
						{
							$seloptions[$val] = $val;
						}
					}
					$expoptions = explode("\n", $options);
					if(is_array($expoptions))
					{
						foreach($expoptions as $key => $val)
						{
							$val = trim($val);
							$val = str_replace("\n", "\\n", $val);

							$sel = "";
							if(isset($seloptions[$val]) && $val == $seloptions[$val])
							{
								$sel = ' selected="selected"';
							}

							eval("\$select .= \"".$templates->get("usercp_profile_profilefields_select_option")."\";");
						}
						if(!$profilefield['length'])
						{
							$profilefield['length'] = 3;
						}

						eval("\$code = \"".$templates->get("usercp_profile_profilefields_multiselect")."\";");
					}
				}
				elseif($type == "select")
				{
					$expoptions = explode("\n", $options);
					if(is_array($expoptions))
					{
						foreach($expoptions as $key => $val)
						{
							$val = trim($val);
							$val = str_replace("\n", "\\n", $val);
							$sel = "";
							if($val == $userfield)
							{
								$sel = ' selected="selected"';
							}

							eval("\$select .= \"".$templates->get("usercp_profile_profilefields_select_option")."\";");
						}
						if(!$profilefield['length'])
						{
							$profilefield['length'] = 1;
						}

						eval("\$code = \"".$templates->get("usercp_profile_profilefields_select")."\";");
					}
				}
				elseif($type == "radio")
				{
					$expoptions = explode("\n", $options);
					if(is_array($expoptions))
					{
						foreach($expoptions as $key => $val)
						{
							$checked = "";
							if($val == $userfield)
							{
								$checked = 'checked="checked"';
							}

							eval("\$code .= \"".$templates->get("usercp_profile_profilefields_radio")."\";");
						}
					}
				}
				elseif($type == "checkbox")
				{
					if(!empty($errors))
					{
						$useropts = $userfield;
					}
					else
					{
						$useropts = explode("\n", $userfield);
					}
					if(is_array($useropts))
					{
						foreach($useropts as $key => $val)
						{
							$seloptions[$val] = $val;
						}
					}
					$expoptions = explode("\n", $options);
					if(is_array($expoptions))
					{
						foreach($expoptions as $key => $val)
						{
							$checked = "";
							if(isset($seloptions[$val]) && $val == $seloptions[$val])
							{
								$checked = 'checked="checked"';
							}

							eval("\$code .= \"".$templates->get("usercp_profile_profilefields_checkbox")."\";");
						}
					}
				}
				elseif($type == "textarea")
				{
					$value = htmlspecialchars_uni($userfield);
					eval("\$code = \"".$templates->get("usercp_profile_profilefields_textarea")."\";");
				}
				else
				{
					$value = htmlspecialchars_uni($userfield);
					$maxlength = "";
					if($profilefield['maxlength'] > 0)
					{
						$maxlength = " maxlength=\"{$profilefield['maxlength']}\"";
					}

					eval("\$code = \"".$templates->get("usercp_profile_profilefields_text")."\";");
				}

				if($profilefield['required'] == 1)
				{
					// JS validator extra, choose correct selectors for everything except single select which always has value
					if($type != 'select')
					{
						$jsvar_reqfields[] = array(
							'type' => $type,
							'fid' => $field,
						);
					}

					eval("\$requiredfields .= \"".$templates->get("member_register_customfield")."\";");
				}
				else
				{
					eval("\$customfields .= \"".$templates->get("member_register_customfield")."\";");
				}
			}

			if($requiredfields)
			{
				eval("\$requiredfields = \"".$templates->get("member_register_requiredfields")."\";");
			}

			if($customfields)
			{
				eval("\$customfields = \"".$templates->get("member_register_additionalfields")."\";");
			}
		}

		if(!isset($fromreg) || $fromreg == 0)
		{
			$allownoticescheck = "checked=\"checked\"";
			$hideemailcheck = '';
			$receivepmscheck = "checked=\"checked\"";
			$pmnoticecheck = " checked=\"checked\"";
			$pmnotifycheck = '';
			$invisiblecheck = '';
			if($dstcorrection == 1)
			{
				$enabledstcheck = "checked=\"checked\"";
			}
			$no_auto_subscribe_selected = $instant_email_subscribe_selected = $instant_pm_subscribe_selected = $no_subscribe_selected = '';
			$dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';
			$username = $email = $email2 = '';
			$regerrors = '';
		}
		
		

		

		
		
		
		
		$showsecretquestion = "";

        $questions = [1 => $lang->member["hr0"], 2 => $lang->member["hr1"], 3 => $lang->member["hr2"]];
        $options = "<select name=\"passhint\" id=\"passhint\" class=\"form-select form-select-sm pe-5 w-auto\">";
        foreach ($questions as $v => $q) 
		{
            $options .= "<option value=\"" . $v . "\"" . (isset($passhint) && $passhint == $v ? " selected=\"selected\"" : "") . ">" . $q . "</option>";
        }
        $options .= "</select>";
        $showsecretquestion = "
		<tr>
		   <td width=\"30%\" align=\"right\">" . $lang->member["sq"] . "</td><td width=\"70%\" align=\"left\">" . $options . "</td>
		</tr>
		
	    <tr>
		   <td width=\"30%\" align=\"right\">" . $lang->member["sqa"] . "</td><td width=\"70%\" align=\"left\">
	       <input type=\"text\" class=\"form-control form-control-sm border\" name=\"hintanswer\" id=\"hintanswer\" value=\"" . (isset($hintanswer) ? htmlspecialchars_uni($hintanswer) : "") . "\" autocomplete=\"off\" size=\"35\" />
           </td>
		</tr>";
		
		
		
		
		
		
		
		if($regtype != "randompass")
		{
			// JS validator extra
			$js_validator_password_length = sprintf($lang->member['js_validator_password_length'], $minpasswordlength);

			// See if the board has "require complex passwords" enabled.
			if($requirecomplexpasswords == 1)
			{
				$lang->member['password'] = $lang->member['complex_password'] = sprintf($lang->member['complex_password'], $minpasswordlength);
			}
			eval("\$passboxes = \"".$templates->get("member_register_password")."\";");
		}

		$invitehash = isset($_POST["invitehash"]) ? htmlspecialchars_uni($_POST["invitehash"]) : (isset($_GET["invitehash"]) ? htmlspecialchars_uni($_GET["invitehash"]) : "");
	
	
	
	
	

	$showinvitecode = "";

    
	if($regtype == "invite") 
    {
	
	
	$showinvitecode = '
	<div class="py-3">
         '.$lang->member['invitecode'].'
         <input type="text" class="form-control form-control-sm border" name="invitehash" id="invitehash" value="'.$invitehash.'" />	
		
	</div>';
	
	}
		
		
		

		// Set the time so we can find automated signups
		$time = TIMENOW;
		
	

		$plugins->run_hooks("member_register_end");

		$jsvar_reqfields = json_encode($jsvar_reqfields);

		$validator_javascript = "<script type=\"text/javascript\">
			var regsettings = {
				requiredfields: '{$jsvar_reqfields}',
				minnamelength: '{$minnamelength}',
				maxnamelength: '{$maxnamelength}',
				minpasswordlength: '{$minpasswordlength}',
				questionexists: '{$question_exists}',
				requirecomplexpasswords: '{$requirecomplexpasswords}',
				regtype: '{$regtype}'
				
			};

			lang.js_validator_no_username = '{$lang->member['js_validator_no_username']}';
			lang.js_validator_username_length = '{$js_validator_username_length}';
			lang.js_validator_invalid_email = '{$lang->member['js_validator_invalid_email']}';
			lang.js_validator_email_match = '{$lang->member['js_validator_email_match']}';
			lang.js_validator_not_empty = '{$lang->member['js_validator_not_empty']}';
			lang.js_validator_password_length = '{$lang->member['js_validator_password_length']}';
			lang.js_validator_password_matches = '{$lang->member['js_validator_password_matches']}';
			lang.js_validator_no_image_text = '{$lang->member['js_validator_no_image_text']}';
			lang.js_validator_no_security_question = '{$lang->member['js_validator_no_security_question']}';
			lang.js_validator_bad_password_security = '{$lang->member['js_validator_bad_password_security']}';
		</script>\n";

		eval("\$registration = \"".$templates->get("member_register")."\";");
		
		stdhead();
		build_breadcrumb();
		
		echo $registration;
	}
}

if($mybb->input['action'] == "activate")
{
	$plugins->run_hooks("member_activate_start");

	if(isset($mybb->input['username']))
	{
		$mybb->input['username'] = $mybb->get_input('username');
		$options = array(
			'username_method' => $username_method,
			'fields' => '*',
		);
		$user = get_user_by_username($mybb->input['username'], $options);
		if(!$user)
		{
			switch($username_method)
			{
				case 0:
					stderr('error_invalidpworusername');
					break;
				case 1:
					stderr('error_invalidpworusername1');
					break;
				case 2:
					stderr('error_invalidpworusername2');
					break;
				default:
					stderr('error_invalidpworusername');
					break;
			}
		}
		$uid = $user['id'];
	}
	else
	{
		$user = get_user($mybb->get_input('id', MyBB::INPUT_INT));
	}
	if(isset($mybb->input['code']) && $user)
	{
		$query = $db->simple_select("awaitingactivation", "*", "uid='".$user['id']."' AND (type='r' OR type='e' OR type='b')");
		$activation = $db->fetch_array($query);
		if(!$activation)
		{
			stderr('error_alreadyactivated');
		}
		if($activation['code'] !== $mybb->get_input('code'))
		{
			stderr('error_badactivationcode');
		}

		if($activation['type'] == "b" && $activation['validated'] == 1)
		{
			stderr('error_alreadyvalidated');
		}

		$db->delete_query("awaitingactivation", "uid='".$user['id']."' AND (type='r' OR type='e')");

		if($user['usergroup'] == 5 && $activation['type'] != "e" && $activation['type'] != "b")
		{
			$db->update_query("users", array("usergroup" => 2), "id='".$user['id']."'");

			$cache->update_awaitingactivation();
		}
		if($activation['type'] == "e")
		{
			$newemail = array(
				"email" => $db->escape_string($activation['misc']),
			);
			$db->update_query("users", $newemail, "id='".$user['id']."'");
			$plugins->run_hooks("member_activate_emailupdated");

			redirect("usercp.php", $lang->member['redirect_emailupdated']);
		}
		elseif($activation['type'] == "b")
		{
			$update = array(
				"validated" => 1,
			);
			$db->update_query("awaitingactivation", $update, "uid='".$user['id']."' AND type='b'");
			$plugins->run_hooks("member_activate_emailactivated");

			redirect("index.php", $lang->member['redirect_accountactivated_admin'], "", true);
		}
		else
		{
			$plugins->run_hooks("member_activate_accountactivated");
			
			
			$noperm_array = array (
		       "ustatus" => 'confirmed'
	        );
			$db->update_query("users", $noperm_array, "id='{$user['id']}' AND ustatus='pending' AND enabled = 'yes'");
			

			require_once INC_PATH . '/functions_pm.php';
			
			$pm = array(
				'subject' => sprintf ($lang->member['welcomepmsubject'], $SITENAME),
				'message' => sprintf ($lang->member['welcomepmbody'], $user['username'], $SITENAME, $BASEURL),
				'touid' => $user['id']
			);
							
			/// Workaround for eliminating PHP warnings in PHP 8. Ref: https://github.com/mybb/mybb/issues/4630#issuecomment-1369144163
			$pm['sender']['uid'] = -1;
			send_pm($pm, -1, true);
		

			redirect("index.php", $lang->member['redirect_accountactivated']);
		}
	}
	else
	{
		$plugins->run_hooks("member_activate_form");

		$code = htmlspecialchars_uni($mybb->get_input('code'));

		if(!isset($user['username']))
		{
			$user['username'] = '';
		}
		$user['username'] = htmlspecialchars_uni($user['username']);

		eval("\$activate = \"".$templates->get("member_activate")."\";");
		
		stdhead('title');
		echo $activate;
	}
}

if($mybb->input['action'] == "do_resendactivation" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_resendactivation_start");

	if($regtype == "admin")
	{
		error($lang->error_activated_by_admin);
	}

	$errors = array();

	

	$query = $db->query("
		SELECT u.id, u.username, u.usergroup, u.email, a.code, a.type, a.validated
		FROM users u
		LEFT JOIN awaitingactivation a ON (a.id=u.u.id AND (a.type='r' OR a.type='b'))
		WHERE u.email='".$db->escape_string($mybb->get_input('email'))."'
	");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		error($lang->error_invalidemail);
	}
	else
	{
		if(count($errors) == 0)
		{
			while($user = $db->fetch_array($query))
			{
				if($user['type'] == "b" && $user['validated'] == 1)
				{
					error($lang->error_activated_by_admin);
				}

				if($user['usergroup'] == 5)
				{
					if(!$user['code'])
					{
						$user['code'] = random_str();
						$uid = $user['id'];
						$awaitingarray = array(
							"uid" => $uid,
							"dateline" => TIMENOW,
							"code" => $user['code'],
							"type" => $user['type']
						);
						$db->insert_query("awaitingactivation", $awaitingarray);
					}
					$username = $user['username'];
					$email = $user['email'];
					$activationcode = $user['code'];
					$emailsubject = sprintf($lang->emailsubject_activateaccount, $SITENAME);
					switch($username_method)
					{
						case 0:
							$emailmessage = sprintf($lang->email_activateaccount, $user['username'], $SITENAME, $BASEURL, $user['uid'], $activationcode);
							break;
						case 1:
							$emailmessage = sprintf($lang->email_activateaccount1, $user['username'], $SITENAME, $BASEURL, $user['uid'], $activationcode);
							break;
						case 2:
							$emailmessage = sprintf($lang->email_activateaccount2, $user['username'], $SITENAME, $BASEURL, $user['uid'], $activationcode);
							break;
						default:
							$emailmessage = sprintf($lang->email_activateaccount, $user['username'], $SITENAME, $BASEURL, $user['uid'], $activationcode);
							break;
					}
					my_mail($email, $emailsubject, $emailmessage);
				}
			}

			$plugins->run_hooks("member_do_resendactivation_end");

			redirect("index.php", $lang->redirect_activationresent);
		}
		else
		{
			$mybb->input['action'] = "resendactivation";
		}
	}
}

if($mybb->input['action'] == "resendactivation")
{
	$plugins->run_hooks("member_resendactivation");

	if($regtype == "admin")
	{
		error($lang->error_activated_by_admin);
	}

	if($mybb->user['uid'] && $mybb->user['usergroup'] != 5)
	{
		error($lang->error_alreadyactivated);
	}

	$query = $db->simple_select("awaitingactivation", "*", "uid='".$mybb->user['uid']."' AND type='b'");
	$activation = $db->fetch_array($query);

	if($activation && $activation['validated'] == 1)
	{
		error($lang->error_activated_by_admin);
	}



	if(isset($errors) && count($errors) > 0)
	{
		$errors = inline_error($errors);
		$email = htmlspecialchars_uni($mybb->get_input('email'));
	}
	else
	{
		$errors = '';
		$email = '';
	}

	$plugins->run_hooks("member_resendactivation_end");

	eval("\$activate = \"".$templates->get("member_resendactivation")."\";");
	output_page($activate);
}

if($mybb->input['action'] == "do_lostpw" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_lostpw_start");

	$errors = array();

	

	$query = $db->simple_select("users", "*", "email='".$db->escape_string($mybb->get_input('email'))."'");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		stderr($lang->member['error_invalidemail']);
	}
	else
	{
		if(count($errors) == 0)
		{
			while($user = $db->fetch_array($query))
			{
				$db->delete_query("awaitingactivation", "uid='{$user['id']}' AND type='p'");
				$user['activationcode'] = random_str(30);
				$now = TIMENOW;
				$uid = $user['id'];
				$awaitingarray = array(
					"uid" => $user['id'],
					"dateline" => TIMENOW,
					"code" => $user['activationcode'],
					"type" => "p"
				);
				$db->insert_query("awaitingactivation", $awaitingarray);
				$username = $user['username'];
				$email = $user['email'];
				$activationcode = $user['activationcode'];
				$emailsubject = sprintf($lang->member['emailsubject_lostpw'], $SITENAME);
				switch($username_method)
				{
					case 0:
						$emailmessage = sprintf($lang->member['email_lostpw'], $username, $SITENAME, $BASEURL, $uid, $activationcode);
						break;
					case 1:
						$emailmessage = sprintf($lang->member['email_lostpw1'], $username, $SITENAME, $BASEURL, $uid, $activationcode);
						break;
					case 2:
						$emailmessage = sprintf($lang->member['email_lostpw2'], $username, $SITENAME, $BASEURL, $uid, $activationcode);
						break;
					default:
						$emailmessage = sprintf($lang->member['email_lostpw'], $username, $SITENAME, $BASEURL, $uid, $activationcode);
						break;
				}
				my_mail($email, $emailsubject, $emailmessage);
			}

			$plugins->run_hooks("member_do_lostpw_end");

			redirect("index.php", $lang->member['redirect_lostpwsent'], "", true);
		}
		else
		{
			$mybb->input['action'] = "lostpw";
		}
	}
}

if($mybb->input['action'] == "lostpw")
{
	$plugins->run_hooks("member_lostpw");

	

	if(isset($errors) && count($errors) > 0)
	{
		$errors = inline_error($errors);
		$email = htmlspecialchars_uni($mybb->get_input('email'));
	}
	else
	{
		$errors = '';
		$email = '';
	}

	eval("\$lostpw = \"".$templates->get("member_lostpw")."\";");
	
	stdhead('ititile');
	
	echo $lostpw;
}

if($mybb->input['action'] == "resetpassword")
{
	$plugins->run_hooks("member_resetpassword_start");

	if(isset($mybb->input['username']))
	{
		$mybb->input['username'] = $mybb->get_input('username');
		$options = array(
			'username_method' => $username_method,
			'fields' => '*',
		);
		$user = get_user_by_username($mybb->input['username'], $options);
		if(!$user)
		{
			switch($username_method)
			{
				case 0:
					stderr('error_invalidpworusername');
					break;
				case 1:
					stderr('error_invalidpworusername1');
					break;
				case 2:
					stderr('error_invalidpworusername2');
					break;
				default:
					stderr('error_invalidpworusername');
					break;
			}
		}
	}
	else
	{
		$user = get_user($mybb->get_input('id', MyBB::INPUT_INT));
	}

	if(isset($mybb->input['code']) && $user)
	{
		$query = $db->simple_select("awaitingactivation", "code", "uid='".$user['id']."' AND type='p'");
		$activationcode = $db->fetch_field($query, 'code');
		$now = TIMENOW;
		if(!$activationcode || $activationcode !== $mybb->get_input('code'))
		{
			stderr('error_badlostpwcode');
		}
		$db->delete_query("awaitingactivation", "uid='".$user['id']."' AND type='p'");
		$username = $user['username'];

	
		
		
		// Generate a new password, then update it
		$password_length = (int)$minpasswordlength;

		if($password_length < 8)
		{
			$password_length = min(8, (int)$maxpasswordlength);
		}

		// Set up user handler.
		require_once INC_PATH.'/datahandlers/user.php';
		$userhandler = new UserDataHandler('update');

		do
		{
			$password = random_str($password_length, $requirecomplexpasswords);

			$userhandler->set_data(array(
				'uid'		=> $user['id'],
				'username'	=> $user['username'],
				'email'		=> $user['email'],
				'password'	=> $password
			));

			$userhandler->set_validated(true);
			$userhandler->errors = array();
		} while(!$userhandler->verify_password());

		$userhandler->update_user();

		$logindetails = array(
			'salt'		=> $userhandler->data['salt'],
			'password'	=> $userhandler->data['password'],
			'loginkey'	=> $userhandler->data['loginkey'],
		);

		$email = $user['email'];

		$plugins->run_hooks("member_resetpassword_process");

		$emailsubject = sprintf($lang->member['emailsubject_passwordreset'], $SITENAME);
		$emailmessage = sprintf($lang->member['email_passwordreset'], $username, $SITENAME, $password);
		my_mail($email, $emailsubject, $emailmessage);

		$plugins->run_hooks("member_resetpassword_reset");

		stderr($lang->member['redirect_passwordreset']);
	}
	else
	{
		$plugins->run_hooks("member_resetpassword_form");

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

		$code = htmlspecialchars_uni($mybb->get_input('code'));
		
		$input_username = htmlspecialchars_uni($mybb->get_input('username'));

		stdhead('titlelel');
		
		
		eval("\$activate = \"".$templates->get("member_resetpassword")."\";");
		
		echo $activate;
	}
}


$inline_errors = "";
if($mybb->input['action'] == "do_login" && $mybb->request_method == "post")
{
    verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	$plugins->run_hooks("member_do_login_start");
	
	

	require_once INC_PATH."/datahandlers/login.php";
	$loginhandler = new LoginDataHandler("get");

	if($mybb->get_input('quick_password') && $mybb->get_input('quick_username'))
	{
		$mybb->input['password'] = $mybb->get_input('quick_password');
		$mybb->input['username'] = $mybb->get_input('quick_username');
		$mybb->input['remember'] = $mybb->get_input('quick_remember');
	}

	$user = array(
		'username' => $mybb->get_input('username'),
		'password' => $mybb->get_input('password'),
		'remember' => $mybb->get_input('remember')
	);

	$options = array(
		'fields' => 'loginattempts',
		'username_method' => (int)$username_method,
	);

	$user_loginattempts = get_user_by_username($user['username'], $options);
	if(!empty($user_loginattempts))
	{
		$user['loginattempts'] = (int)$user_loginattempts['loginattempts'];
	}

	$loginhandler->set_data($user);
	$validated = $loginhandler->validate_login();

	if(!$validated)
	{
		$mybb->input['action'] = "login";
		$mybb->request_method = "get";

		$login_user_uid = 0;
		if(!empty($loginhandler->login_data))
		{
			$login_user_uid = (int)$loginhandler->login_data['id'];
			$user['loginattempts'] = (int)$loginhandler->login_data['loginattempts'];
		}

		// Is a fatal call if user has had too many tries
		$logins = login_attempt_check($login_user_uid);

		$db->update_query("users", array('loginattempts' => 'loginattempts+1'), "id='".$login_user_uid."'", 1, true);
		
		$username = $mybb->get_input('username');
        $password = $mybb->get_input('password');
  
		$ipaddress = get_ip();
		$md5pw = md5($password);
        $iphost = @gethostbyaddr(USERIPADDRESS);
		
		failedlogins ('login', false, true, true, (int)$login_user_uid);
		

		$errors = $loginhandler->get_friendly_errors();

		
		
	}
	else if($validated)
	{
		// Successful login
		if($loginhandler->login_data['coppauser'])
		{
			error($lang->error_awaitingcoppa);
		}

		$loginhandler->complete_login();
		
		


		$plugins->run_hooks("member_do_login_end");

		$mybb->input['url'] = $mybb->get_input('url');

		if(!empty($mybb->input['url']) && my_strpos(basename($mybb->input['url']), 'member.php') === false && !preg_match('#^javascript:#i', $mybb->input['url']))
		{
			if((my_strpos(basename($mybb->input['url']), 'newthread.php') !== false || my_strpos(basename($mybb->input['url']), 'newreply.php') !== false) && my_strpos($mybb->input['url'], '&processed=1') !== false)
			{
				$mybb->input['url'] = str_replace('&processed=1', '', $mybb->input['url']);
			}

			$mybb->input['url'] = str_replace('&amp;', '&', $mybb->input['url']);

			if(my_strpos($mybb->input['url'], $BASEURL.'/') !== 0)
			{
				if(my_strpos($mybb->input['url'], '/') === 0)
				{
					$mybb->input['url'] = my_substr($mybb->input['url'], 1);
				}
				$url_segments = explode('/', $mybb->input['url']);
				$mybb->input['url'] = $BASEURL.'/'.end($url_segments);
			}

			// Redirect to the URL if it is not member.php
			redirect($mybb->input['url'], $lang->member['redirect_loggedin']);
		}
		else
		{

			redirect("index.php", $lang->member['redirect_loggedin']);
		}
	}

	$plugins->run_hooks("member_do_login_end");
}

if($mybb->input['action'] == "login")
{
	$plugins->run_hooks("member_login");

	$member_loggedin_notice = "";
	//if($CURUSER['id'] != 0)
	if (isset($CURUSER) && is_array($CURUSER) && isset($CURUSER['id']) && $CURUSER['id'] != 0)	
	
	{
		$CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);
		$already_logged_in = sprintf($lang->member['already_logged_in'], build_profile_link($CURUSER['username'], $CURUSER['id']));
		eval("\$member_loggedin_notice = \"".$templates->get("member_loggedin_notice")."\";");
	}

	// Checks to make sure the user can login; they haven't had too many tries at logging in.
	// Is a fatal call if user has had too many tries. This particular check uses cookies, as a uid is not set yet
	// and we can't check loginattempts in the db
	login_attempt_check();

	// Redirect to the page where the user came from, but not if that was the login page.
	if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], "action=login") === false)
	{
		$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
	}
	else
	{
		$redirect_url = '';
	}

	

	$username = "";
	$password = "";
	if(isset($mybb->input['username']) && $mybb->request_method == "post")
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
	}

	if(isset($mybb->input['password']) && $mybb->request_method == "post")
	{
		$password = htmlspecialchars_uni($mybb->get_input('password'));
	}

	if(!empty($errors))
	{
		$mybb->input['action'] = "login";
		$mybb->request_method = "get";

		$inline_errors = inline_error($errors);
	}

	switch($username_method)
	{
		case 1:
			$lang->member['username'] = $lang->member['username1'];
			break;
		case 2:
			$lang->member['username'] = $lang->member['username2'];
			break;
		default:
			break;
	}

	$plugins->run_hooks("member_login_end");

	eval("\$login = \"".$templates->get("member_login")."\";");
	
	stdhead();
	
	echo $login;
}

if($mybb->input['action'] == "logout")
{
	$plugins->run_hooks("member_logout_start");

	if(!$CURUSER['id'])
	{
		redirect("index.php", $lang->member['redirect_alreadyloggedout']);
	}

	// Check session ID if we have one
	if(isset($mybb->input['sid']) && $mybb->get_input('sid') !== $session->sid)
	{
		stderr($lang->member['error_notloggedout']);
	}
	
	$logoutkey = md5($CURUSER['loginkey']);
	
	// Otherwise, check logoutkey
	if($mybb->get_input('logoutkey') !== $logoutkey)
	{
		stderr($lang->member['error_notloggedout']);
	}

	my_unsetcookie("mybbuser");
	my_unsetcookie("sid");
	

	if($CURUSER['id'])
	{
		$time = TIMENOW;
		// Run this after the shutdown query from session system
		$db->shutdown_query("UPDATE users SET lastvisit='{$time}', lastactive='{$time}' WHERE id='{$CURUSER['id']}'");
		$db->delete_query("sessions", "sid = '{$session->sid}'");
	}

	$plugins->run_hooks("member_logout_end");

	redirect("member.php?action=login", $lang->member['redirect_loggedout']);
}

if($mybb->input['action'] == "viewnotes")
{
	$uid = $mybb->get_input('id', MyBB::INPUT_INT);
	$user = get_user($uid);

	// Make sure we are looking at a real user here.
	if(!$user)
	{
		error($lang->member['error_nomember']);
	}

	if($mybb->user['id'] == 0 || $mybb->usergroup['canmodcp'] != 1)
	{
		print_no_permission();
	}

	$user['username'] = htmlspecialchars_uni($user['username']);
	$lang->view_notes_for = sprintf($lang->view_notes_for, $user['username']);

	$user['usernotes'] = nl2br(htmlspecialchars_uni($user['usernotes']));

	$plugins->run_hooks('member_viewnotes');

	eval("\$viewnotes = \"".$templates->get("member_viewnotes", 1, 0)."\";");
	echo $viewnotes;
	exit;
}

if($mybb->input['action'] == "profile")
{


  if (!isset($CURUSER) || isset($CURUSER) && $CURUSER["id"] == 0) 
  {
    print_no_permission();
  }
  
  
  $parser_options = array(
	"allow_html" => 1,
	"allow_mycode" => 1,
	"allow_smilies" => 1,
	"allow_imgcode" => 1,
	"allow_videocode" => 1,
	"filter_badwords" => 1
  );

  
  gzip ();
  maxsysop ();
  //parked ();
  
 
 
  
	
	$uid = $mybb->get_input('id', MyBB::INPUT_INT);
	if($uid)
	{
		$memprofile = get_user($uid);
	}
	elseif($CURUSER['id'])
	{
		$memprofile = $CURUSER;
	}
	else
	{
		$memprofile = false;
	}

	if(!$memprofile)
	{
		stderr($lang->member['error_nomember']);	
	}
	
	
	$uid = $memprofile['id'];
	
	
	$SameUser = ($uid == $CURUSER['id'] ? true : false);
	$IsStaff = is_mod ($usergroups);
	
	
	if($memprofile['invisible'] == 1 && !$SameUser && !$IsStaff)
    {
	  
	  stderr($lang->member['noperm']);
    }

    if ($memprofile['ustatus'] == 'pending')
    {
       stderr($lang->member['pendinguser']);
    }
	
	

	$plugins->run_hooks("member_profile_start");
	
	
	$me_username = $memprofile['username'];
	$memprofile['username'] = htmlspecialchars_uni($memprofile['username']);
	
	stdhead (sprintf ($lang->member['title'], $memprofile['username']));
	

	
	// Get member's permissions
	$memperms = user_permissions($memprofile['id']);
	
	
	// Set display group
	$displaygroupfields = array("title", "description", "namestyle", "usertitle");

	if(!$memprofile['displaygroup'])
	{
		$memprofile['displaygroup'] = $memprofile['usergroup'];
	}

	$displaygroup = usergroup_displaygroup($memprofile['displaygroup']);
	if(is_array($displaygroup))
	{
		$memperms = array_merge($memperms, $displaygroup);
	}
	
	
	$nav_profile = sprintf($lang->member['nav_profile'], $memprofile['username']);
	add_breadcrumb($nav_profile);
	build_breadcrumb();
	
	$send_user_email = sprintf($lang->member['send_user_email'], $memprofile['username']);
    $send_pms = sprintf($lang->member['send_pm'], $memprofile['username']);
    $users_signature = sprintf($lang->member['users_signature'], $memprofile['username']);
	
	$users_forum_info = sprintf($lang->member['users_forum_info'], $memprofile['username']);
	
	$users_additional_info = sprintf($lang->member['users_additional_info'], $memprofile['username']);
	
	
	
	
	$useravatar = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);

   // Если аватар — это HTML-заглушка (начинается с '<'), выводим её как есть
   if (strpos($useravatar['image'], '<') === 0) 
   {
       $avatar = $useravatar['image']; // <div class="avatar-ring2">No Avatar</div>
   } 
   // Иначе выводим как <img> (стандартный аватар)
   else 
   {
       $avatar = '<img class="rounded img-fluid" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';
   }
	
	


	

	
	
	$website = $sendemail = $sendpm = $contact_details = '';

    
	
	if($usergroups['cansendemail'] == 1 && $uid != $CURUSER['id'] && $memprofile['hideemail'] != 1 && (my_strpos(",".$memprofile['ignorelist'].",", ",".$CURUSER['id'].",") === false || $usergroups['cansendemailoverride'] != 0))
    {
	    $bgcolor = alt_trow();
	    eval("\$sendemail = \"".$templates->get("member_profile_email")."\";");
    }


   

    if($enablepms != 0 && $uid != $CURUSER['id'] && $usergroups['canusepms'] == 1 && (($memprofile['receivepms'] != 0 && $memperms['canusepms'] != 0 && my_strpos(",".$memprofile['ignorelist'].",", ",".$CURUSER['id'].",") === false) || $usergroups['canoverridepm'] == 1))
    {
	   $bgcolor = alt_trow();
	   eval('$sendpm = "'.$templates->get("member_profile_pm").'";');
    }
	
	$any_contact_field = false;

    if($any_contact_field || $sendemail || $sendpm || $website)
    {
	   eval('$contact_details = "'.$templates->get("member_profile_contact_details").'";');
    }
	
	
	
	$signature = '';
    if($memprofile['signature'])
    {
		$sig_parser = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"me_username" => $me_username,
			"filter_badwords" => 1
		);

		
		$memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
		
		eval("\$signature = \"".$templates->get("member_profile_signature")."\";");
	}
	
	
	
 
	
	




 // Подготовленный запрос
$sql = "
    SELECT u.*, p.canupload, p.candownload, p.cancomment
    FROM users u
    LEFT JOIN users_perm p ON (u.id = p.userid)
    WHERE u.id = ?
    LIMIT 1
";

// Выполнение подготовленного запроса
$Query = $db->sql_query_prepared($sql, [$uid]);

// Проверка результата
if ($Query && $db->num_rows($Query) > 0) 
{
    $user = $db->fetch_array($Query);
} 
else 
{
    stderr($lang->member['invaliduser']);
}


  $usericons = get_user_icons ($user);


 

  if ($memprofile['invited_by'])
  {
   
	
	$query = $db->simple_select("users", "username, usergroup", "id = '{$memprofile['invited_by']}'");
	
    if (0 < $db->num_rows ($query))
    {
      $IUser = $db->fetch_array ($query);
      $memprofile['invited_by'] = '<a href="' . get_profile_link($memprofile['invited_by']) . '">' . format_name($IUser['username'], $IUser['usergroup']) . '</a>';	  
    }
  }


  
  
  //$email = (((preg_match ('#I1#is', $user['options']) OR $SameUser) OR $IsStaff) ? $user['email'] : $lang->member['hidden']);
  $uploaded = mksize ($memprofile['uploaded']);
  $downloaded = mksize ($memprofile['downloaded']);
  
 
  

  
  $kps = (($SameUser OR $IsStaff) ? '' . sprintf ($lang->member['kps']) : '');
  
  
  $sr = "";

  



$signature = '';
if($memprofile['signature'])
{
		$sig_parser = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"me_username" => $me_username,
			"filter_badwords" => 1
		);

		
		$memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
		
		eval("\$signature = \"".$templates->get("member_profile_signature")."\";");
		
		
		
}





$formattedname = format_name($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);




$memprofile['timezone'] = (float)$memprofile['timezone'];

if($memprofile['dst'] == 1)
{
		$memprofile['timezone']++;
		if(my_substr($memprofile['timezone'], 0, 1) != "-")
		{
			$memprofile['timezone'] = "+{$memprofile['timezone']}";
		}
}
	
	
$memregdate = my_datee($dateformat, $memprofile['added']);
$memlocaldate = gmdate($dateformat, TIMENOW + ($memprofile['timezone'] * 3600));
$memlocaltime = gmdate($timeformat, TIMENOW + ($memprofile['timezone'] * 3600));
	
$localtime = sprintf(''.$memlocaldate.' at '.$memlocaltime.'');



if($memprofile['birthday'])
{
		$membday = explode("-", $memprofile['birthday']);

		if($memprofile['birthdayprivacy'] != 'none')
		{
			if($membday[0] && $membday[1] && $membday[2])
			{
				$membdayage = sprintf('('.get_age($memprofile['birthday']).' years old)');

				$bdayformat = fix_mktime($dateformat, $membday[2]);
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
				$membday = date($bdayformat, $membday);

				$membdayage = $membdayage;
			}
			elseif($membday[2])
			{
				$membday = mktime(0, 0, 0, 1, 1, $membday[2]);
				$membday = date("Y", $membday);
				$membdayage = '';
			}
			else
			{
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
				$membday = date("F j", $membday);
				$membdayage = '';
			}
		}

		if($memprofile['birthdayprivacy'] == 'age')
		{
			$membday = 'Hidden';
		}
		else if($memprofile['birthdayprivacy'] == 'none')
		{
			$membday = 'Hidden';
			$membdayage = '';
		}
}
else
{
		$membday = 'Not Specified';
		$membdayage = '';
}











echo '<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">';

echo '<link rel="stylesheet" href="'.$BASEURL.'/include/templates/default/style/userclass.css" type="text/css" media="screen" />';


// Get the user title for this user
$usertitle = '';

// User has group title
$usertitle = $memperms['image'];

$usertitle = $usertitle;



// User is currently online and this user has permissions to view the user on the WOL

$timesearch = TIMENOW - $wolcutoffmins*60;
  
$query = $db->simple_select("sessions", "location,nopermission", "uid='$uid' AND time>'{$timesearch}'", array('order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1));
$session = $db->fetch_array($query);
	
$timeonline = 'None Registered';
$memlastvisitdate =  $lang->member['lastvisit_never'];
 
  
$last_seen = max(array($memprofile['lastactive'], $memprofile['lastvisit']));
  
 

if(!empty($last_seen))
{
		// We have some stamp here
		
		//if (((preg_match ('#B1#is', $user['options']) AND !$SameUser) AND !$IsStaff))
		if($memprofile['invisible'] == 1 && !$SameUser && !$IsStaff)
		{
			$memlastvisitdate = $lang->member['hidden'];
			$online_status = $timeonline = $lang->member['hidden'];
		}
		else
		{
			$memlastvisitdate = my_datee('relative', $last_seen);

			if($memprofile['timeonline'] > 0)
			{
				$timeonline = mkprettytime($memprofile['timeonline']);
			}

			// Online?
			if(!empty($session))
			{
				// Fetch their current location
				$lang->load("online");
				
				require_once INC_PATH . '/functions_online.php';
				
				$activity = fetch_wol_activity($session['location'], $session['nopermission']);
				$location = build_friendly_wol_location($activity);
				$location_time = my_datee($timeformat, $last_seen);

				eval("\$online_status = \"".$templates->get("member_profile_online")."\";");
			}
		}
}
  
if(!isset($online_status))
{
	  eval("\$online_status = \"".$templates->get("member_profile_offline")."\";");
}




// ---------- STATUS DOT CLASS ----------
$status_dot_class = ' status-off'; // по умолчанию: оффлайн (серый)

// Если юзер скрыт, и смотрящий не сам юзер и не стафф — всегда серый
if (!empty($memprofile['invisible']) && !$SameUser && !$IsStaff) 
{
    $status_dot_class = ' status-off';
} 
else 
{
    // Онлайн по сессии в пределах $wolcutoffmins
    if (!empty($session)) 
	{
        // online -> зелёный (без доп. класса)
        $status_dot_class = '';
    } 
	else 
	{
        // Не онлайн: решаем "away" или "off" по давности активности
        $away_window = 30 * 60; // 30 минут – порог "отошёл"
        $delta = (int) (TIMENOW - (int)$last_seen);
        if ($delta > 0 && $delta <= $away_window) 
		{
            $status_dot_class = ' status-away'; // жёлтый
        } 
		else 
		{
            $status_dot_class = ' status-off'; // серый
        }
    }
}

// Сделаем готовый HTML для шаблона
$status_dot_html = '<span class="status-dot' . $status_dot_class . '" aria-hidden="true"></span>';










$bannedbit = '';
	
	
if($memperms['isbannedgroup'] == 1 && $usergroups['canuserdetails'] == 1)

{
		// Fetch details on their ban
		$query = $db->simple_select('banned b LEFT JOIN users a ON (b.admin=a.id)', 'b.*, a.username AS adminuser', "b.uid='{$uid}'", array('limit' => 1));

		if($db->num_rows($query))
		{
			$memban = $db->fetch_array($query);

			if($memban['reason'])
			{
				$memban['reason'] = htmlspecialchars_uni($parser->parse_badwords($memban['reason']));
			}
			else
			{
				$memban['reason'] = $lang->na;
			}

			if($memban['lifted'] == 'perm' || $memban['lifted'] == '' || $memban['bantime'] == 'perm' || $memban['bantime'] == '---')
			{
				$banlength = 'permanent';
				$timeremaining = 'na';
				$banned_class = "normal_banned";
			}
			else
			{
				// Set up the array of ban times.
				$bantimes = fetch_ban_times();

				$banlength = $bantimes[$memban['bantime']];
				$remaining = $memban['lifted']-TIMENOW;

				$timeremaining = mkprettytime($remaining);

				$banned_class = '';
				if($remaining < 3600)
				{
					$banned_class = "high_banned";
				}
				else if($remaining < 86400)
				{
					$banned_class = "moderate_banned";
				}
				else if($remaining < 604800)
				{
					$banned_class = "low_banned";
				}
				else
				{
					$banned_class = "normal_banned";
				}
			}
			
			$timeremaining = '<span class="'.$banned_class.'">('.$timeremaining.' remaining)</span>';

			$memban['adminuser'] = build_profile_link(htmlspecialchars_uni($memban['adminuser']), $memban['admin']);

			// Display a nice warning to the user
	
		
			eval('$bannedbit = "'.$templates->get('member_profile_banned').'";');
			
			
		}
		else
		{
			// TODO: more specific output for converted/merged boards where no ban record is merged.
			$bannedbit = '';
		}
}



$memprofile['regip'] = my_inet_ntop($db->unescape_binary($memprofile['regip']));
$memprofile['lastip'] = my_inet_ntop($db->unescape_binary($memprofile['lastip']));

eval("\$ipaddress = \"".$templates->get("member_profile_modoptions_ipaddress")."\";");







$zaza = $cache->read('indexstats');
$stats = $zaza['totalposts'];
$stats22 = $zaza['totalthreads'];


$daysreg = (TIMENOW - $memprofile['added']) / (24*3600);

	if($daysreg < 1)
	{
		$daysreg = 1;
	}
	
// Format post count, per day count and percent of total
	$ppd = $memprofile['postnum'] / $daysreg;
	$ppd = round($ppd, 2);
	if($ppd > $memprofile['postnum'])
	{
		$ppd = $memprofile['postnum'];
	}

	$numposts = $stats;
	if($numposts == 0)
	{
		$post_percent = "0";
	}
	else
	{
		$post_percent = $memprofile['postnum']*100/$numposts;
		$post_percent = round($post_percent, 2);
	}

	if($post_percent > 100)
	{
		$post_percent = 100;
	}
	

	
// Format thread count, per day count and percent of total
$tpd = $memprofile['threadnum'] / $daysreg;
$tpd = round($tpd, 2);
if($tpd > $memprofile['threadnum'])
{
	$tpd = $memprofile['threadnum'];
}

$numthreads = $stats22;
if($numthreads == 0)
{
	$thread_percent = "0";
}
else
{
	$thread_percent = $memprofile['threadnum']*100/$numthreads;
	$thread_percent = round($thread_percent, 2);
}

if($thread_percent > 100)
{
	$thread_percent = 100;
}
	
	

$ppd_percent_total = sprintf(''.ts_nf($ppd).' posts per day | '.$post_percent.' percent of total posts');


$tpd_percent_total = sprintf(''.ts_nf($tpd).' threads per day | '.$thread_percent.' percent of total threads');

	
	





$modoptions = $viewnotes = $editnotes = $editprofile = $banuser = $manageban = $manageuser = '';

$awaybit = '';

$referrals = '';

$groupimage = '';

$userstars = '';

$reputation = '';




if($memperms['isbannedgroup'] == 1 && $usergroups['canuserdetails'] == 1)
{

   eval("\$manageban = \"".$templates->get("member_profile_modoptions_manageban")."\";");
						
}
else
{
		
   eval("\$banuser = \"".$templates->get("member_profile_modoptions_banuser")."\";");
					
}





eval("\$editprofile = \"".$templates->get("member_profile_modoptions_editprofile")."\";");
		

$manageuser = '
    '.$editprofile.'
    '.$banuser.'
    '.$manageban.'
';





if($IsStaff)
{
	

eval("\$modoptions = \"".$templates->get("member_profile_modoptions")."\";");


}









    $findposts = $findthreads = '';
	
    if(!empty($memprofile['postnum']))
	{
		eval("\$findposts = \"".$templates->get("member_profile_findposts")."\";");
	}
	if(!empty($memprofile['threadnum']))
	{
		eval("\$findthreads = \"".$templates->get("member_profile_findthreads")."\";");
	}
	



    
	
	
	
	$add_remove_options = array();
	$buddy_options = $ignore_options = $report_options = '';
	//if($CURUSER['id'] != $uid && $CURUSER['id'] != 0)
	if($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0)
	{
		$buddy_list = explode(',', $CURUSER['buddylist']);
		$ignore_list = explode(',', $CURUSER['ignorelist']);

		if(in_array($uid, $buddy_list))
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_buddy_button', 'lang' => 'Remove from Buddy List');
		}
		else
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;add_username=".urlencode($memprofile['username'])."&amp;my_post_key={$mybb->post_code}", 'class' => 'add_buddy_button', 'lang' => 'Add to Buddy List');
		}

		if(!in_array($uid, $ignore_list))
		{
			eval("\$buddy_options = \"".$templates->get("member_profile_addremove")."\";"); // Add/Remove Buddy
		}

		if(in_array($uid, $ignore_list))
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_ignore_button', 'lang' => 'Remove from Ignore List');
		}
		else
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;add_username=".urlencode($memprofile['username'])."&amp;my_post_key={$mybb->post_code}", 'class' => 'add_ignore_button', 'lang' => 'Add to Ignore List');
		}

		if(!in_array($uid, $buddy_list))
		{
			eval("\$ignore_options = \"".$templates->get("member_profile_addremove")."\";"); // Add/Remove Ignore
		}

		
	
	}
	
	


	

$plugins->run_hooks("member_profile_end");


$invs = '';
 
if ($memprofile['invited_by'])
{
   
	
	$invs2 = sprintf ($lang->member['iby'], $memprofile['invited_by']);
	
	$invs = '
	
	<div class="py-2 border-bottom">
		<span class="text-muted">'.$invs2.'</span> 
    </div>';
	
}









// безопасный htmlspecialchars (если уже есть — пропусти)
if (!function_exists('hsafe')) 
{
    function hsafe($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}




// простая отрисовка FA-иконки категории по классам из БД
function rt_cat_fa(?string $iconRaw, string $title = ''): string {
    $cls = preg_replace('/[^a-z0-9\-\s]/i', '', (string)$iconRaw) ?: 'fa-solid fa-question';
    return '<i class="'.hsafe($cls).'" title="'.hsafe($title).'" aria-hidden="true"></i>';
}





/**
 * Активные раздачи/закачки пользователя из peers.
 * Возвращает: ['seed_html','leech_html','seed_count','leech_count']
 */
function build_user_active_swarm($db, int $uid, int $limit = 10): array
{
    global $BASEURL, $dateformat, $timeformat;

    $uid   = max(0, $uid);
    $limit = max(1, $limit);

    $render = function(string $seeder_val) use ($db, $uid, $limit, $BASEURL, $dateformat, $timeformat) {

        $sql = "
            SELECT t.id, t.name, t.size, t.category, t.t_image, t.t_link,
                   c.name AS cat_name, c.icon AS cat_icon,
                   p.uploaded, p.downloaded, p.to_go, p.last_action,
                   p.seeder
            FROM peers p
            JOIN torrents t ON t.id = p.torrent
            LEFT JOIN categories c ON c.id = t.category
            WHERE p.userid = ? AND p.seeder = ?
            ORDER BY p.last_action DESC
            LIMIT ?
        ";

        $res = $db->sql_query_prepared($sql, [$uid, $seeder_val, $limit]);

        if (!$res || $db->num_rows($res) === 0) {
            return ['html' => '
            <div class="text-center py-4">
                <div class="d-inline-flex flex-column align-items-center gap-2">
                    <i class="bi bi-' . ($seeder_val === 'yes' ? 'cloud-upload' : 'cloud-download') . ' fs-1 text-secondary opacity-25"></i>
                    <div class="text-muted small">Nothing active right now</div>
                </div>
            </div>', 'count' => 0];
        }

        $html = '<div class="d-flex flex-column gap-2">';
        $cnt  = 0;

        while ($r = $db->fetch_array($res)) {
            $cnt++;
            $id   = (int)$r['id'];
            $name = (string)($r['name'] ?? '');
            $cat  = (string)($r['cat_name'] ?? '');
            $icon = rt_cat_fa($r['cat_icon'] ?? '', $cat);
            $link = $BASEURL . '/torrent-' . $id . '.html';

            $up   = mksize((int)($r['uploaded']   ?? 0));
            $dn   = mksize((int)($r['downloaded'] ?? 0));
            $seen = my_datee($dateformat, (int)($r['last_action'] ?? 0))
                  . ' ' . my_datee($timeformat, (int)($r['last_action'] ?? 0));

            // Прогресс
            $size     = max(1, (int)($r['size'] ?? 1));
            $to_go    = (int)($r['to_go'] ?? 0);
            $progress = $seeder_val === 'yes'
                ? 100
                : min(100, max(0, (int)round((1 - $to_go / $size) * 100)));
            $progress_color = $seeder_val === 'yes' ? 'success' : 'info';

            // Скорость
            $upspeed   = (int)($r['upspeed']   ?? 0);
            $downspeed = (int)($r['downspeed'] ?? 0);
            $speed_html = '';
            if ($upspeed > 0 || $downspeed > 0) {
                $speed_html = '
                <div class="d-flex gap-2 mt-1">
                    ' . ($upspeed > 0 ? '
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.65rem;">
                        <i class="bi bi-arrow-up me-1"></i>' . mksize($upspeed) . '/s
                    </span>' : '') . '
                    ' . ($downspeed > 0 ? '
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill" style="font-size:0.65rem;">
                        <i class="bi bi-arrow-down me-1"></i>' . mksize($downspeed) . '/s
                    </span>' : '') . '
                </div>';
            }

            // IMDb
            $imdb_badge = '';
            if (!empty($r['t_link']) && preg_match('#(\d+\.?\d*)/10#', $r['t_link'], $m)) {
                $imdb_badge = '<span class="badge ms-1" style="background:#f5c518;color:#000;font-size:0.6rem;">
                    <i class="bi bi-star-fill me-1"></i>' . $m[1] . '
                </span>';
            }

            // Обложка
            $poster = !empty($r['t_image']) ? htmlspecialchars($r['t_image']) : '';

            // ETA для личей
            $eta_html = '';
            if ($seeder_val === 'no' && $to_go > 0 && $downspeed > 0) {
                $eta_secs = (int)($to_go / $downspeed);
                $eta_html = '
                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill" style="font-size:0.65rem;">
                    <i class="bi bi-stopwatch me-1"></i>' . mkprettytime($eta_secs) . '
                </span>';
            }

            $html .= '
            <div class="card border-0 shadow-sm overflow-hidden hov-scale">
                <div class="d-flex">

                    <!-- Обложка или иконка -->
                    ' . ($poster ? '
                    <a href="' . $link . '" class="flex-shrink-0">
                        <img src="' . $poster . '"
                             style="width:70px;min-height:85px;object-fit:cover;background:#111;"
                             alt="' . hsafe($name) . '"
                             onerror="this.parentElement.style.display=\'none\'">
                    </a>' : '
                    <a href="' . $link . '" class="flex-shrink-0 d-flex align-items-center justify-content-center bg-light"
                       style="width:70px;min-height:85px;">
                        <span class="fs-3">' . $icon . '</span>
                    </a>') . '

                    <div class="card-body p-2 flex-grow-1 min-width-0">

                        <!-- Категория + IMDb -->
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <span class="badge bg-light text-dark border" style="font-size:0.6rem;">
                                ' . $icon . ' ' . hsafe($cat) . '
                            </span>
                            ' . $imdb_badge . '
                        </div>

                        <!-- Название -->
                        <a href="' . $link . '" class="text-decoration-none">
                            <div class="fw-semibold text-dark clamp-2" style="font-size:0.82rem;">
                                ' . htmlspecialchars_uni($name) . '
                            </div>
                        </a>

                        <!-- Прогресс -->
                        <div class="mt-2 mb-1">
                            <div class="d-flex justify-content-between mb-1" style="font-size:0.7rem;">
                                <span class="text-muted">Progress</span>
                                <span class="fw-bold text-' . $progress_color . '">' . $progress . '%</span>
                            </div>
                            <div class="progress" style="height:4px;">
                                <div class="progress-bar bg-' . $progress_color . '"
                                     style="width:' . $progress . '%"></div>
                            </div>
                        </div>

                        <!-- Статистика -->
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.65rem;">
                                <i class="bi bi-arrow-up-circle-fill me-1"></i>' . $up . '
                            </span>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill" style="font-size:0.65rem;">
                                <i class="bi bi-arrow-down-circle-fill me-1"></i>' . $dn . '
                            </span>
                            ' . $eta_html . '
                        </div>

                        <!-- Скорость -->
                        ' . $speed_html . '

                        <!-- Время -->
                        <div class="text-muted mt-1" style="font-size:0.68rem;">
                            <i class="bi bi-clock me-1"></i>' . $seen . '
                        </div>

                    </div>
                </div>
            </div>';
        }

        $html .= '</div>';
        return ['html' => $html, 'count' => $cnt];
    };

    $seed  = $render('yes');
    $leech = $render('no');

    return [
        'seed_html'   => $seed['html'],
        'leech_html'  => $leech['html'],
        'seed_count'  => $seed['count'],
        'leech_count' => $leech['count'],
    ];
}






/**
 * Карточки последних торрентов пользователя
 */

function build_recent_user_torrents($db, int $uid): string
{
    global $BASEURL, $dateformat, $lang;

    $uid = max(0, $uid);

    $sql = "
        SELECT
            t.id, t.name, t.size, t.added,
            t.seeders, t.leechers, t.times_completed,
            t.t_image, t.t_link,
            t.category,
            c.name AS cat_name, c.icon AS cat_icon
        FROM torrents t
        LEFT JOIN categories c ON c.id = t.category
        WHERE t.owner = ?
          AND (t.visible = 'yes' OR t.visible = 1 OR t.visible IS NULL)
          AND (t.banned  = 'no'  OR t.banned  = 0  OR t.banned  IS NULL)
        ORDER BY t.added DESC
        LIMIT 12
    ";

    $res = $db->sql_query_prepared($sql, [(int)$uid]);

    if (!$res || $db->num_rows($res) === 0) {
        return '<div class="text-center p-4">
            <div class="d-inline-flex flex-column align-items-center gap-3">
                <div class="bg-light rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;">
                    <i class="bi bi-cloud-arrow-up fs-1 text-secondary"></i>
                </div>
                <div class="text-secondary fw-medium">No uploads yet.</div>
            </div>
        </div>';
    }

    $html = '<div class="row g-3">';

    while ($r = $db->fetch_array($res)) {
        $id    = (int)$r['id'];
        $name  = $r['name'] ?? '';
        $size  = mksize((int)$r['size']);
        $added = my_datee($dateformat, (int)$r['added']);
        $seed  = ts_nf((int)$r['seeders']);
        $leech = ts_nf((int)$r['leechers']);
        $done  = ts_nf((int)$r['times_completed']);
        $link  = $BASEURL . '/torrent-' . $id . '.html';

        // Категория
        $catName  = (string)($r['cat_name'] ?? '');
        $iconRaw  = trim((string)($r['cat_icon'] ?? ''));
        $iconClass = preg_replace('/[^a-z0-9\-\s]/i', '', $iconRaw) ?: 'fa-solid fa-question';
        $catIcon  = '<i class="' . hsafe($iconClass) . '" title="' . hsafe($catName) . '" aria-hidden="true"></i>';

        // Обложка
        $poster = !empty($r['t_image'])
            ? htmlspecialchars($r['t_image'])
            : '';

        // IMDb рейтинг
        $imdb_badge = '';
        if (!empty($r['t_link']) && preg_match('#(\d+\.?\d*)/10#', $r['t_link'], $m)) {
            $imdb_badge = '<span class="badge ms-1" style="background:#f5c518;color:#000;font-size:0.7rem;">
                <i class="bi bi-star-fill me-1"></i>' . $m[1] . '
            </span>';
        }

        // Health цвет
        $health = (int)$r['seeders'] > 0 ? 'success' : 'danger';

        $html .= '
        <div class="col-12">
            <div class="card border-0 shadow-sm hov-scale overflow-hidden">
                <div class="d-flex">

                    <!-- Обложка -->
                    ' . ($poster ? '
                    <a href="' . $link . '" class="flex-shrink-0">
                        <img src="' . $poster . '"
                             style="width:75px;height:90%;object-fit:cover;min-height:90px;background:#111;"
                             alt="' . hsafe($name) . '"
                             onerror="this.parentElement.style.display=\'none\'">
                    </a>' : '') . '

                    <div class="card-body p-3 flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-width-0">

                                <!-- Категория + название -->
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-light text-dark border" style="font-size:0.7rem;">
                                        ' . $catIcon . ' ' . hsafe($catName) . '
                                    </span>
                                    ' . $imdb_badge . '
                                </div>

                                <a href="' . $link . '" class="text-decoration-none">
                                    <h6 class="fw-semibold text-dark mb-1 clamp-2">' . htmlspecialchars_uni($name) . '</h6>
                                </a>

                                <div class="small text-muted d-flex flex-wrap gap-2 mb-2">
                                    <span><i class="bi bi-hdd me-1"></i>' . hsafe($size) . '</span>
                                    <span><i class="bi bi-calendar3 me-1"></i>' . hsafe($added) . '</span>
                                </div>

                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill">
                                        <i class="bi bi-arrow-up-circle-fill me-1"></i>' . $seed . '
                                    </span>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill">
                                        <i class="bi bi-arrow-down-circle-fill me-1"></i>' . $leech . '
                                    </span>
                                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill">
                                        <i class="bi bi-check2-circle me-1"></i>' . $done . '
                                    </span>
                                </div>
                            </div>

                            <!-- Кнопка скачать -->
                            <div class="flex-shrink-0">
                                <a href="' . $BASEURL . '/download.php/' . $id . '.torrent"
                                   class="btn btn-primary btn-sm rounded-pill"
                                   title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			
			<style>
			.hov-scale {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px !important;
}
.hov-scale:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
}
.clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.min-width-0 { min-width: 0; }
	</style>		
			
			
			
			
        </div>';
    }

    $html .= '</div>';
    return $html;
}














///////////////////


/**
 * Completed torrents из snatched (s.finished='yes').
 * Возвращает ['html' => string, 'count' => int]
 */

function build_user_completed_torrents_from_snatched($db, int $uid, int $limit = 10): array
{
    global $BASEURL, $dateformat, $timeformat;

    $uid   = max(0, $uid);
    $limit = max(1, $limit);

    // Счётчик
    $sql_cnt = "SELECT COUNT(*) AS cnt
                FROM snatched s
                LEFT JOIN torrents t ON (s.torrentid = t.id)
                INNER JOIN categories c ON (t.category = c.id)
                WHERE s.finished = 'yes' AND s.userid = ?";
    $rc    = $db->sql_query_prepared($sql_cnt, [(int)$uid]);
    $rowc  = $rc ? $db->fetch_array($rc) : null;
    $total = (int)($rowc['cnt'] ?? 0);

    // Записи — добавили t_image и t_link
    $sql = "SELECT s.torrentid AS id,
                   s.uploaded, s.downloaded, s.completedat, s.last_action,
                   t.seeders, t.leechers, t.name, t.category,
                   t.t_image, t.t_link,
                   c.name AS categoryname, c.icon AS caticon
            FROM snatched s
            LEFT JOIN torrents t ON (s.torrentid = t.id)
            INNER JOIN categories c ON (t.category = c.id)
            WHERE s.finished = 'yes' AND s.userid = ?
            ORDER BY s.completedat DESC, s.last_action DESC
            LIMIT ?";
    $res = $db->sql_query_prepared($sql, [(int)$uid, (int)$limit]);

    if (!$res || $db->num_rows($res) === 0) {
        return ['html' => '
        <div class="text-center py-4">
            <div class="d-inline-flex flex-column align-items-center gap-3">
                <div class="bg-light rounded-circle p-3 d-flex align-items-center justify-content-center"
                     style="width:70px;height:70px;">
                    <i class="bi bi-check2-circle fs-1 text-secondary opacity-50"></i>
                </div>
                <div class="text-secondary fw-medium">No completed history.</div>
                <div class="small text-muted">Completed torrents will appear here</div>
            </div>
        </div>', 'count' => $total];
    }

    $html = '<div class="d-flex flex-column gap-2">';

    while ($r = $db->fetch_array($res)) {
        $id   = (int)($r['id']   ?? 0);
        $name = (string)($r['name'] ?? '');
        $cat  = (string)($r['categoryname'] ?? '');
        $icon = rt_cat_fa($r['caticon'] ?? '', $cat);
        $link = $BASEURL . '/torrent-' . $id . '.html';

        $when = my_datee($dateformat, $r['completedat'])
              . ' ' . my_datee($timeformat, $r['completedat']);

        $up   = mksize((int)($r['uploaded']   ?? 0));
        $dn   = mksize((int)($r['downloaded'] ?? 0));
        $seed = ts_nf((int)($r['seeders']  ?? 0));
        $lee  = ts_nf((int)($r['leechers'] ?? 0));

        // Ratio
        $dl = (int)($r['downloaded'] ?? 0);
        $ul = (int)($r['uploaded']   ?? 0);
        if ($dl > 0) {
            $ratio_val = $ul / $dl;
            $ratio_str = number_format($ratio_val, 2);
            $ratio_color = $ratio_val >= 1.0 ? 'success' : ($ratio_val >= 0.5 ? 'warning' : 'danger');
        } else {
            $ratio_str   = $ul > 0 ? '∞' : '0.00';
            $ratio_color = $ul > 0 ? 'success' : 'secondary';
        }

        // IMDb
        $imdb_badge = '';
        if (!empty($r['t_link']) && preg_match('#(\d+\.?\d*)/10#', $r['t_link'], $m)) {
            $imdb_badge = '<span class="badge ms-1" style="background:#f5c518;color:#000;font-size:0.65rem;">
                <i class="bi bi-star-fill me-1"></i>' . $m[1] . '
            </span>';
        }

        // Обложка
        $poster = !empty($r['t_image']) ? htmlspecialchars($r['t_image']) : '';

        // Health
        $health_color = (int)$r['seeders'] > 0 ? 'success' : 'danger';
        $health_icon  = (int)$r['seeders'] > 0 ? 'bi-wifi' : 'bi-wifi-off';

        $html .= '
        <div class="card border-0 shadow-sm overflow-hidden hov-scale">
            <div class="d-flex">

                <!-- Обложка -->
                ' . ($poster ? '
                <a href="' . $link . '" class="flex-shrink-0">
                    <img src="' . $poster . '"
                         style="width:75px;min-height:90px;object-fit:cover;background:#111;"
                         alt="' . hsafe($name) . '"
                         onerror="this.parentElement.style.display=\'none\'">
                </a>' : '
                <a href="' . $link . '" class="flex-shrink-0 d-flex align-items-center justify-content-center bg-light"
                   style="width:75px;min-height:90px;">
                    <span class="fs-3">' . $icon . '</span>
                </a>') . '

                <div class="card-body p-2 flex-grow-1 min-width-0">

                    <!-- Категория + IMDb -->
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge bg-light text-dark border" style="font-size:0.65rem;">
                            ' . $icon . ' ' . hsafe($cat) . '
                        </span>
                        ' . $imdb_badge . '
                        <span class="badge bg-' . $health_color . ' bg-opacity-10 text-' . $health_color . ' ms-auto" style="font-size:0.65rem;">
                            <i class="bi ' . $health_icon . ' me-1"></i>' . $seed . '/' . $lee . '
                        </span>
                    </div>

                    <!-- Название -->
                    <a href="' . $link . '" class="text-decoration-none">
                        <div class="fw-semibold text-dark clamp-2" style="font-size:0.85rem;">
                            ' . htmlspecialchars_uni($name) . '
                        </div>
                    </a>

                    <!-- Дата -->
                    <div class="text-muted mt-1" style="font-size:0.72rem;">
                        <i class="bi bi-calendar-check me-1"></i>' . $when . '
                    </div>

                    <!-- Статистика -->
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill" style="font-size:0.65rem;">
                            <i class="bi bi-arrow-up-circle-fill me-1"></i>' . $up . '
                        </span>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill" style="font-size:0.65rem;">
                            <i class="bi bi-arrow-down-circle-fill me-1"></i>' . $dn . '
                        </span>
                        <span class="badge bg-' . $ratio_color . ' bg-opacity-10 text-' . $ratio_color . ' rounded-pill" style="font-size:0.65rem;">
                            <i class="bi bi-percent me-1"></i>' . $ratio_str . '
                        </span>
                    </div>

                </div>
            </div>
        </div>';
    }

    $html .= '</div>';
    return ['html' => $html, 'count' => $total];
}















// предполагаем, что $memprofile содержит uploaded/downloaded и uid
$up   = (int)($memprofile['uploaded']   ?? 0);
$down = (int)($memprofile['downloaded'] ?? 0);

// ratio + формат
if ($down > 0) {
    $ratio_val = $up / $down;
    $ratio     = number_format($ratio_val, 2);
} else {
    $ratio_val = ($up > 0) ? 999 : 0;
    $ratio     = ($up > 0) ? '∞' : '0.00';
}

// CSS-класс по порогам
if ($ratio_val >= 1.0)      { $ratio_class = 'ratio-ok'; }
elseif ($ratio_val >= 0.5)  { $ratio_class = 'ratio-warn'; }
else                        { $ratio_class = 'ratio-bad'; }

// прогресс-бары: доли от суммы
$total = max($up + $down, 1);
$uploaded_percent   = min(100, (int)round($up   / $total * 100));
$downloaded_percent = min(100, (int)round($down / $total * 100));



// Передаём в шаблонные плейсхолдеры (если у тебя — eval шаблонов)
$ratio_class = $ratio_class;

$ratio = $ratio;






$recent_user_torrents = build_recent_user_torrents($db, (int)$uid);

$swarm = build_user_active_swarm($db, (int)$uid, 1000);
$active_seeds   = (string)$swarm['seed_count'];
$active_leeches = (string)$swarm['leech_count'];
$seeding_now    = $swarm['seed_html'];
$leeching_now   = $swarm['leech_html'];


// новое:
$completed = build_user_completed_torrents_from_snatched($db, (int)$uid, 10000);
$completed_list  = $completed['html'];     // список для блока
$times_completed_total = ts_nf($completed['count']); // бейдж «Completed: N»







$is_own_profile = ((int)$CURUSER['id'] === (int)$memprofile['id']);
$is_mod = is_mod($usergroups); // твоя функция
$can_change_avatar = ($is_own_profile || $is_mod) ? 1 : 0;




// просто подключаем файл, без инлайн-скрипта
echo '<script src="'.$BASEURL.'/scripts/upload_avatar.js"></script>';



$report_button = '';

// Проверяем, можно ли репортнуть пользователя
if ($CURUSER['id'] != $memprofile['id'] && $CURUSER['id'] != 0) 
{
    $report_button = '
    <button type="button" 
            class="btn btn-sm btn-outline-danger" 
            onclick="openReportUserModal('.$memprofile['id'].', \''.addslashes($memprofile['username']).'\')"
            data-bs-toggle="tooltip" 
            title="Report this user for violations">
        <i class="fa-solid fa-flag me-1"></i> Report User
    </button>';
}


$reasons_map = [
        'spam' => [
            'text' => 'Spam Account',
            'icon' => 'fa-user-slash',
            'description' => 'User is posting spam content'
        ],
        'harassment' => [
            'text' => 'Harassment/Bullying',
            'icon' => 'fa-ban',
            'description' => 'User is harassing or bullying others'
        ],
        'fake' => [
            'text' => 'Fake Account',
            'icon' => 'fa-mask',
            'description' => 'User is pretending to be someone else'
        ],
        'impersonation' => [
            'text' => 'Impersonation',
            'icon' => 'fa-id-badge',
            'description' => 'User is impersonating another user'
        ],
        'inappropriate' => [
            'text' => 'Inappropriate Profile',
            'icon' => 'fa-eye-slash',
            'description' => 'User has inappropriate profile content'
        ],
        'scam' => [
            'text' => 'Scam/Fraud',
            'icon' => 'fa-skull-crossbones',
            'description' => 'User is involved in scams or fraud'
        ],
        'copyright' => [
            'text' => 'Copyright Infringement',
            'icon' => 'fa-copyright',
            'description' => 'User is sharing copyrighted content'
        ],
        'malware' => [
            'text' => 'Malware Distribution',
            'icon' => 'fa-bug',
            'description' => 'User is distributing malware/viruses'
        ],
        'other' => [
            'text' => 'Other Reason',
            'icon' => 'fa-ellipsis',
            'description' => 'Select for other reasons'
        ]
];


// Текстовый индикатор ratio
$ratio_label = match($ratio_class) {
    'ratio-ok'   => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Good</span>',
    'ratio-warn' => '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Fair</span>',
    default      => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Poor</span>',
};








// Последние комментарии пользователя
$recent_comments_html = '';
$recent_comments_query = $db->sql_query_prepared("
    SELECT c.id, c.text, c.dateline, c.torrent AS torrentid,
           t.name AS torrent_name
    FROM comments c
    LEFT JOIN torrents t ON (c.torrent = t.id)
    WHERE c.user = ?
    ORDER BY c.dateline DESC
    LIMIT 10
", [$uid]);

if ($recent_comments_query && $db->num_rows($recent_comments_query) > 0) {
    while ($comment = $db->fetch_array($recent_comments_query)) {
        $torrent_link = get_torrent_link($comment['torrentid']);
        $comment_date = my_datee($dateformat, $comment['dateline']);
        $comment_time = my_datee($timeformat, $comment['dateline']);

        // Обрезаем текст
        $comment_text = strip_tags($comment['text']);
        $comment_text = mb_strlen($comment_text) > 120
            ? mb_substr($comment_text, 0, 120) . '...'
            : $comment_text;
		
        $pid = $comment['id'];	
		$tid = $comment['torrentid'];
		
		$postlink = get_comment_link($pid, $tid);
        $comment_link = $BASEURL . '/' . $postlink;
		

        $torrent_name = htmlspecialchars_uni($comment['torrent_name'] ?? 'Unknown');

        $recent_comments_html .= '
        <div class="list-row">
            <div class="flex-grow-1 me-3" style="min-width:0;">
                <div class="fw-semibold text-truncate">
                     <a href="' . $comment_link . '#pid' . $pid . '" 
                       class="text-decoration-none">
                        <i class="bi bi-collection-play me-1 text-muted"></i>
                        ' . $torrent_name . '
                    </a>
                </div>
                <div class="muted mt-1" style="font-size:0.85rem;">
                    ' . $comment_text . '
                </div>
            </div>
            <div class="text-end flex-shrink-0">
                <div class="small text-muted">' . $comment_date . '</div>
                <div class="small text-muted">' . $comment_time . '</div>
            </div>
        </div>';
    }
} else {
    $recent_comments_html = '
    <div class="text-center py-4">
        <div class="d-inline-flex flex-column align-items-center gap-3">
            <div class="bg-light rounded-circle p-3 d-flex align-items-center justify-content-center" 
                 style="width:70px;height:70px;">
                <i class="bi bi-chat-square-dots fs-1 text-secondary opacity-50"></i>
            </div>
            <div class="text-secondary fw-medium">No comments yet.</div>
        </div>
    </div>';
}



eval("\$profile = \"".$templates->get("member_profile")."\";");









echo $profile;

    
?>

	
	<div class="modal fade" id="reportUserModal" tabindex="-1" aria-labelledby="reportUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="reportUserForm" method="POST" action="<?= $BASEURL ?>/takereport.php">
                    <input type="hidden" name="type" value="user">
                    <input type="hidden" name="reported_id" value="<?= $memprofile['id'] ?>">
                    <input type="hidden" name="reported_user_id" value="<?= $memprofile['id'] ?>">
                    <input type="hidden" name="addedby" value="<?= $CURUSER['id'] ?>">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="reportUserModalLabel">
                            <i class="fa-solid fa-flag me-2"></i>
                            Report User: <?= $memprofile['username'] ?>
						</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <!-- Причины репорта -->
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fa-solid fa-circle-exclamation me-2"></i>
                                Select Report Reason
                            </h6>
                            
                            <div class="row g-3">
                                <?php foreach ($reasons_map as $key => $reason): ?>
                                <div class="col-md-6">
                                    <div class="report-reason-card">
                                        <input type="radio" 
                                               class="btn-check" 
                                               name="reason" 
                                               id="reason_<?= $key ?>" 
                                               value="<?= $key ?>" 
                                               autocomplete="off">
                                        <label class="btn btn-outline-danger w-100 text-start py-3" 
                                               for="reason_<?= $key ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="fa-solid <?= $reason['icon'] ?> fa-2x"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= $reason['text'] ?></div>
                                                    <div class="small text-muted mt-1"><?= $reason['description'] ?></div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Детали репорта -->
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fa-solid fa-file-alt me-2"></i>
                                Report Details
                            </h6>
                            
                            <div class="mb-3">
                                <label for="reportDescription" class="form-label fw-bold">
                                    Detailed Description <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" 
                                          id="reportDescription" 
                                          name="description" 
                                          rows="5" 
                                          placeholder="Please provide as much detail as possible about why you are reporting this user. Include links, timestamps, and any other relevant information."
                                          required></textarea>
                                <div class="form-text">
                                    <i class="fa-solid fa-lightbulb me-1"></i>
                                    The more information you provide, the easier it will be for our moderators to investigate.
                                </div>
                            </div>
                            
                            <!-- Дополнительная информация -->
                            <div class="mb-3">
                                <label for="additionalInfo" class="form-label">
                                    Additional Information (Optional)
                                </label>
                                <textarea class="form-control" 
                                          id="additionalInfo" 
                                          name="additional_info" 
                                          rows="3" 
                                          placeholder="Any other information that might be helpful..."></textarea>
                            </div>
                            
                            <!-- Ссылки на доказательства -->
                            <div class="mb-3">
                                <label for="evidenceLinks" class="form-label">
                                    <i class="fa-solid fa-link me-1"></i>
                                    Evidence Links (Optional)
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="evidenceLinks" 
                                       name="evidence_links" 
                                       placeholder="Paste URLs to screenshots, posts, or other evidence...">
                            </div>
                        </div>
                        
                        <!-- Конфиденциальность -->
                        <div class="alert alert-info">
                            <div class="d-flex">
                                <i class="fa-solid fa-shield-alt fa-2x me-3 mt-1"></i>
                                <div>
                                    <h6 class="alert-heading mb-2">Privacy Notice</h6>
                                    <p class="mb-1">
                                        <i class="fa-solid fa-check-circle me-1 text-success"></i>
                                        Your report will be handled confidentially
                                    </p>
                                    <p class="mb-1">
                                        <i class="fa-solid fa-check-circle me-1 text-success"></i>
                                        The reported user will not know who reported them
                                    </p>
                                    <p class="mb-0">
                                        <i class="fa-solid fa-check-circle me-1 text-success"></i>
                                        Moderators will review your report within 24 hours
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Предупреждение о ложных репортах -->
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> False or malicious reports may result in action against your account.
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa-solid fa-times me-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?
	
	
	
stdfoot ();



}



if($mybb->input['action'] == "do_emailuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("member_do_emailuser_start");

	// Guests or those without permission can't email other users
	if($usergroups['cansendemail'] == 0)
	{
	   print_no_permission();
	}

	// Check group limits
	if($mybb->usergroup['maxemails'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "{$user_check} AND dateline >= '".(TIMENOW - (60*60*24))."'");
		$sent_count = $db->fetch_field($query, "sent_count");
		if($sent_count >= $mybb->usergroup['maxemails'])
		{
			$lang->error_max_emails_day = sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
			error($lang->error_max_emails_day);
		}
	}

	// Check email flood control
	if($mybb->usergroup['emailfloodtime'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$timecut = TIMENOW-$mybb->usergroup['emailfloodtime']*60;

		$query = $db->simple_select("maillogs", "mid, dateline", "{$user_check} AND dateline > '{$timecut}'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_email = $db->fetch_array($query);

		// Users last email was within the flood time, show the error
		if(isset($last_email['mid']))
		{
			$remaining_time = ($mybb->usergroup['emailfloodtime']*60)-(TIMENOW-$last_email['dateline']);

			if($remaining_time == 1)
			{
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_1_second, $mybb->usergroup['emailfloodtime']);
			}
			elseif($remaining_time < 60)
			{
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_seconds, $mybb->usergroup['emailfloodtime'], $remaining_time);
			}
			elseif($remaining_time > 60 && $remaining_time < 120)
			{
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_1_minute, $mybb->usergroup['emailfloodtime']);
			}
			else
			{
				$remaining_time_minutes = ceil($remaining_time/60);
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_minutes, $mybb->usergroup['emailfloodtime'], $remaining_time_minutes);
			}

			error($lang->error_emailflooding);
		}
	}

	$query = $db->simple_select("users", "id, username, email, hideemail", "id='".$mybb->get_input('id', MyBB::INPUT_INT)."'");
	$to_user = $db->fetch_array($query);

	if(!$to_user['username'])
	{
		stderr('error_invalidusername');
	}

	if($to_user['hideemail'] != 0)
	{
		stderr('error_hideemail');
	}

	$errors = array();

	if($CURUSER['id'])
	{
		$mybb->input['fromemail'] = $CURUSER['email'];
		$mybb->input['fromname'] = $CURUSER['username'];
	}

	if(!validate_email_format($mybb->input['fromemail']))
	{
		$errors[] = 'error_invalidfromemail';
	}

	if(empty($mybb->input['fromname']))
	{
		$errors[] = 'error_noname';
	}

	if(empty($mybb->input['subject']))
	{
		$errors[] = 'rror_no_email_subject';
	}

	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->error_no_email_message;
	}



	if(count($errors) == 0)
	{
		
		
		
		if($mail_handler == 'smtp')
		{
			$from = $mybb->input['fromemail'];
		}
		else
		{
			$from = "{$mybb->input['fromname']} <{$mybb->input['fromemail']}>";
		}

		$message = sprintf($lang->member['email_emailuser'], $to_user['username'], $mybb->input['fromname'], $SITENAME, $BASEURL, $mybb->get_input('message'));
		my_mail($to_user['email'], $mybb->get_input('subject'), $message, '', '', '', false, 'text', '', $from);

		
		
		if($mail_logging > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($mybb->get_input('subject')),
				"message" => $db->escape_string($mybb->get_input('message')),
				"dateline" => TIMENOW,
				"fromuid" => $CURUSER['id'],
				"fromemail" => $db->escape_string($mybb->input['fromemail']),
				"touid" => $to_user['id'],
				"toemail" => $db->escape_string($to_user['email']),
				"tid" => 0,
				"ipaddress" => $db->escape_binary($session->packedip),
				"type" => 1
			);
			$db->insert_query("maillogs", $log_entry);
		}

		$plugins->run_hooks("member_do_emailuser_end");

		redirect(get_profile_link($to_user['id']), 'redirect_emailsent');
	}
	else
	{
		$mybb->input['action'] = "emailuser";
	}
}

if($mybb->input['action'] == "emailuser")
{
	$plugins->run_hooks("member_emailuser_start");

	// Guests or those without permission can't email other users
	if($usergroups['cansendemail'] == 0)
	{
		print_no_permission();
	}

	// Check group limits
	if($mybb->usergroup['maxemails'] > 0)
	{
		if($CURUSER['id'] > 0)
		{
			$user_check = "fromuid='{$CURUSER['id']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "{$user_check} AND dateline >= '".(TIMENOW - (60*60*24))."'");
		$sent_count = $db->fetch_field($query, "sent_count");
		if($sent_count >= $mybb->usergroup['maxemails'])
		{
			$lang->error_max_emails_day = sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
			error($lang->error_max_emails_day);
		}
	}

	// Check email flood control
	if($mybb->usergroup['emailfloodtime'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$timecut = TIMENOW-$mybb->usergroup['emailfloodtime']*60;

		$query = $db->simple_select("maillogs", "mid, dateline", "{$user_check} AND dateline > '{$timecut}'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_email = $db->fetch_array($query);

		// Users last email was within the flood time, show the error
		if(isset($last_email['mid']))
		{
			$remaining_time = ($mybb->usergroup['emailfloodtime']*60)-(TIMENOW-$last_email['dateline']);

			if($remaining_time == 1)
			{
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_1_second, $mybb->usergroup['emailfloodtime']);
			}
			elseif($remaining_time < 60)
			{
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_seconds, $mybb->usergroup['emailfloodtime'], $remaining_time);
			}
			elseif($remaining_time > 60 && $remaining_time < 120)
			{
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_1_minute, $mybb->usergroup['emailfloodtime']);
			}
			else
			{
				$remaining_time_minutes = ceil($remaining_time/60);
				$lang->error_emailflooding = sprintf($lang->error_emailflooding_minutes, $mybb->usergroup['emailfloodtime'], $remaining_time_minutes);
			}

			error($lang->error_emailflooding);
		}
	}

	$query = $db->simple_select("users", "id, username, email, hideemail, ignorelist", "id='".$mybb->get_input('id', MyBB::INPUT_INT)."'");
	$to_user = $db->fetch_array($query);

	$to_user['username'] = htmlspecialchars_uni($to_user['username']);
	$email_user = sprintf($lang->member['email_user'], $to_user['username']);

	if(!$to_user['id'])
	{
		stderr('error_invaliduser');
	}

	if($to_user['hideemail'] != 0)
	{
		stderr('error_hideemail');
	}

	if($to_user['ignorelist'] && (my_strpos(",".$to_user['ignorelist'].",", ",".$CURUSER['id'].",") !== false && $usergroups['cansendemailoverride'] != 1))
	{
		print_no_permission();
	}

	if(isset($errors) && count($errors) > 0)
	{
		$errors = inline_error($errors);
		$fromname = htmlspecialchars_uni($mybb->get_input('fromname'));
		$fromemail = htmlspecialchars_uni($mybb->get_input('fromemail'));
		$subject = htmlspecialchars_uni($mybb->get_input('subject'));
		$message = htmlspecialchars_uni($mybb->get_input('message'));
	}
	else
	{
		$errors = '';
		$fromname = '';
		$fromemail = '';
		$subject = '';
		$message = '';
	}



	$from_email = '';
	if($CURUSER['id'] == 0)
	{
		eval("\$from_email = \"".$templates->get("member_emailuser_guest")."\";");
	}

	$plugins->run_hooks("member_emailuser_end");

	
	
	
	eval("\$emailuser = \"".$templates->get("member_emailuser")."\";");
	
	
	stdhead('title');
	
	echo $emailuser;
	
	stdfoot();
}






if(!$mybb->input['action'])
{
	header("Location: index.php");
}
