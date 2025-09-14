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
define('THIS_SCRIPT', 'contact.php');

$templatelist = "contact,post_captcha,post_captcha_recaptcha_invisible,post_captcha_nocaptcha,post_captcha_hcaptcha_invisible,post_captcha_hcaptcha";

require_once('global.php');
//require_once MYBB_ROOT.'inc/class_captcha.php';

// Load global language phrases
$lang->load("contact");




function inline_error($errors, $title="", $json_data=array())
{
	global $theme, $mybb, $db, $lang, $templates, $charset;

	if(!$title)
	{
		$title = $lang->please_correct_errors;
	}

	if(!is_array($errors))
	{
		$errors = array($errors);
	}

	// AJAX error message?
	if($mybb->input['ajax'])
	{
		// Send our headers.
		@header("Content-type: application/json; charset={$charset}");

		if(empty($json_data))
		{
			echo json_encode(array("errors" => $errors));
		}
		else
		{
			echo json_encode(array_merge(array("errors" => $errors), $json_data));
		}
		exit;
	}

	$errorlist = '';

	foreach($errors as $error)
	{
		$errorlist .= $error;
	}

	$errors = '<div class="red_alert">
           <i class="fa-solid fa-circle-exclamation"></i> &nbsp;'.$errorlist.'
            </div>';

	return $errors;
}





$plugins->run_hooks('contact_start');

// Make navigation
//add_breadcrumb($lang->contact, "contact.php");

//if($mybb->settings['contact'] != 1 || (!$CURUSER['id'] && $mybb->settings['contact_guests'] == 1))
//{
	//error_no_permission();
//}

if($contactemail)
{
	$contactemail = $contactemail;
}
else
{
	$contactemail = $adminemail;
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

	$query = $db->simple_select("maillogs", "COUNT(mid) AS sent_count", "{$user_check} AND dateline >= ".(TIME_NOW - (60*60*24)));
	$sent_count = $db->fetch_field($query, "sent_count");
	if($sent_count >= $mybb->usergroup['maxemails'])
	{
		$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
		error($lang->error_max_emails_day);
	}
}

// Check email flood control
if($mybb->usergroup['emailfloodtime'] > 0)
{
	if($CURUSER['id'] > 0)
	{
		$user_check = "fromuid='{$CURUSER['id']}'";
	}
	else
	{
		$user_check = "ipaddress=".$db->escape_binary($session->packedip);
	}

	$timecut = TIME_NOW-$mybb->usergroup['emailfloodtime']*60;

	$query = $db->simple_select("maillogs", "mid, dateline", "{$user_check} AND dateline > '{$timecut}'", array('order_by' => "dateline", 'order_dir' => "DESC"));
	$last_email = $db->fetch_array($query);

	// Users last email was within the flood time, show the error
	if(!empty($last_email['mid']))
	{
		$remaining_time = ($mybb->usergroup['emailfloodtime']*60)-(TIME_NOW-$last_email['dateline']);

		if($remaining_time == 1)
		{
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_second, $mybb->usergroup['emailfloodtime']);
		}
		elseif($remaining_time < 60)
		{
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_seconds, $mybb->usergroup['emailfloodtime'], $remaining_time);
		}
		elseif($remaining_time > 60 && $remaining_time < 120)
		{
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_minute, $mybb->usergroup['emailfloodtime']);
		}
		else
		{
			$remaining_time_minutes = ceil($remaining_time/60);
			$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_minutes, $mybb->usergroup['emailfloodtime'], $remaining_time_minutes);
		}

		error($lang->error_emailflooding);
	}
}

$errors = array();

$mybb->input['message'] = trim_blank_chrs($mybb->get_input('message'));
$mybb->input['subject'] = trim_blank_chrs($mybb->get_input('subject'));
$mybb->input['email'] = trim_blank_chrs($mybb->get_input('email'));

if($mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks('contact_do_start');

	// Validate input
	if(empty($mybb->input['subject']))
	{
		$errors[] = $lang->contact['contact_no_subject'];
	}

	$contact_maxsubjectlength = "85";
	$contact_maxmessagelength = "65535";
	$contact_minmessagelength = "5";
	
	
	if(strlen($mybb->input['subject']) > $contact_maxsubjectlength && $contact_maxsubjectlength > 0)
	{
		$errors[] = sprintf($lang->contact['subject_too_long'], $contact_maxsubjectlength, strlen($mybb->input['subject']));
	}

	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->contact_no_message;
	}
	
	

	if(strlen($mybb->input['message']) > $contact_maxmessagelength && $contact_maxmessagelength > 0)
	{
		$errors[] = sprintf($lang->contact['message_too_long'], $contact_maxmessagelength, strlen($mybb->input['message']));
	}

	if(strlen($mybb->input['message']) < $contact_minmessagelength && $contact_minmessagelength > 0)
	{
		$errors[] = sprintf($lang->contact['message_too_short'], $contact_minmessagelength, strlen($mybb->input['message']));
		
		
	}

	if(empty($mybb->input['email']))
	{
		$errors[] = $lang->contact['contact_no_email'];
	}
	else
	{
		// Validate email
		if(!validate_email_format($mybb->input['email']))
		{
			$errors[] = $lang->contact['contact_no_email'];
		}
	}

	// Should we have a CAPTCHA? Perhaps yes, but only for guests like in other pages...
	if($mybb->settings['captchaimage'] && !$CURUSER['id'])
	{
		$captcha = new captcha;

		if($captcha->validate_captcha() == false)
		{
			// CAPTCHA validation failed
			foreach($captcha->get_errors() as $error)
			{
				$errors[] = $error;
			}
		}
	}
	
	$stopforumspam_on_contact = "0";

	if(!$CURUSER['id'] && $stopforumspam_on_contact)
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
			if($stop_forum_spam_checker->is_user_a_spammer('', $mybb->input['email'], get_ip()))
			{
				$errors[] = $lang->sprintf($lang->error_stop_forum_spam_spammer,
					$stop_forum_spam_checker->getErrorText(array(
						'stopforumspam_check_emails',
						'stopforumspam_check_ips')));
			}
		}
		catch (Exception $e)
		{
			if($mybb->settings['stopforumspam_block_on_error'])
			{
				$errors[] = $lang->error_stop_forum_spam_fetching;
			}
		}
	}

	if(empty($errors))
	{
		
		$contact_badwords = "0";
		
		if($contact_badwords == 1)
		{
			// Load the post parser
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;

			$mybb->input['subject'] = $parser->parse_badwords($mybb->input['subject']);
			$mybb->input['message'] = $parser->parse_badwords($mybb->input['message']);
		}

		$user = $lang->guest;
		if($CURUSER['id'])
		{
			$user = htmlspecialchars_uni($CURUSER['username']).' - '.$BASEURL.'/'.get_profile_link($CURUSER['id']);
		}

		$subject = sprintf($lang->contact['email_contact_subject'], $mybb->input['subject']);
		$message = sprintf($lang->contact['email_contact'], $mybb->input['email'], $user, $CURUSER['ip'], $mybb->input['message']);

		// Email the administrator
		my_mail($contactemail, $subject, $message, '', '', '', false, 'text', '', $mybb->get_input('email', MyBB::INPUT_STRING));

		$plugins->run_hooks('contact_do_end');
		
		//$mail_logging = "2";

		if($mail_logging > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($subject),
				"message" => $db->escape_string($message),
				"dateline" => TIMENOW,
				"fromuid" => $CURUSER['id'],
				"fromemail" => $db->escape_string($mybb->input['email']),
				"touid" => 0,
				"toemail" => $db->escape_string($contactemail),
				"tid" => 0,
				"ipaddress" => $CURUSER['ip'],
				"type" => 3
			);
			$db->insert_query("maillogs", $log_entry);
		}
		
		$mybb->input['from'] = $mybb->get_input('from');
		if(!empty($mybb->input['from']))
		{
			redirect($mybb->input['from'], $lang->contact['contact_success_message'], '', true);
		}
		else
		{
			redirect('index.php', $lang->contact['contact_success_message'], '', true);
		}
	}
	else
	{
		$errors = inline_error($errors);
	}
}

if(empty($errors))
{
	$errors = '';
}

// Generate CAPTCHA?
$captcha = '';

if($mybb->settings['captchaimage'] && !$CURUSER['id'])
{
	$post_captcha = new captcha(true, "post_captcha");

	if($post_captcha->html)
	{
		$captcha = $post_captcha->html;
	}
}

$contact_subject = htmlspecialchars_uni($mybb->input['subject']);
$contact_message = htmlspecialchars_uni($mybb->input['message']);

if($CURUSER['id'] && !$mybb->get_input('email'))
{
	$user_email = htmlspecialchars_uni($CURUSER['email']);
}
else
{
	$user_email = htmlspecialchars_uni($mybb->get_input('email'));
}

if(isset($mybb->input['from']))
{
	$redirect_url = htmlspecialchars_uni($mybb->get_input('from'));
}
else if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $BASEURL) !== false  && strpos($_SERVER['HTTP_REFERER'], "contact.php") === false)
{
	$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
}
else
{
	$redirect_url = '';
}

$plugins->run_hooks('contact_end');

eval("\$page = \"".$templates->get("contact")."\";");

stdhead('title');



echo $page;

stdfoot();
