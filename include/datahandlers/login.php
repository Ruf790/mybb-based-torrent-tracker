<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Login handling class, provides common structure to handle login events.
 *
 */
class LoginDataHandler extends DataHandler
{
	/**
	 * The language file used in the data handler.
	 *
	 * @var string
	 */
	public $language_file = 'datahandler_login';

	/**
	 * The prefix for the language variables used in the data handler.
	 *
	 * @var string
	 */
	public $language_prefix = 'logindata';

	/**
	 * Array of data used via login events.
	 *
	 * @var array
	 */
	public $login_data = array();

	/**
	 * @var bool
	 */
	public $captcha_verified = true;

	/**
	 * @var bool|captcha
	 */
	private $captcha = false;

	/**
	 * @var int
	 */
	public $username_method = null;

	/**
	 * @param int $check_captcha
	 */
	function verify_attempts($check_captcha = 0)
	{
		global $db, $mybb;

		$user = &$this->data;

		if($check_captcha)
		{
			if(!isset($mybb->cookies['loginattempts']))
			{
				$mybb->cookies['loginattempts'] = 0;
			}
			if($mybb->settings['failedcaptchalogincount'] > 0 && (isset($user['loginattempts']) && $user['loginattempts'] > $mybb->settings['failedcaptchalogincount'] || (int)$mybb->cookies['loginattempts'] > $mybb->settings['failedcaptchalogincount']))
			{
				$this->captcha_verified = false;
				$this->verify_captcha();
			}
		}
	}

	/**
	 * @return bool
	 */
	function verify_captcha()
	{
		global $db, $mybb;

		$user = &$this->data;
		
		
		$captchaimage = "0";

		if($user['imagestring'] || $captchaimage != 1)
		{
			// Check their current captcha input - if correct, hide the captcha input area
			require_once MYBB_ROOT.'inc/class_captcha.php';
			$this->captcha = new captcha;

			if($this->captcha->validate_captcha() == false)
			{
				// CAPTCHA validation failed
				foreach($this->captcha->get_errors() as $error)
				{
					$this->set_error($error);
				}
				return false;
			}
			else
			{
				$this->captcha_verified = true;
				return true;
			}
		}
		else if($mybb->input['quick_login'] == 1 && $mybb->input['quick_password'] && $mybb->input['quick_username'])
		{
			$this->set_error('regimagerequired');
			return false;
		}
		else
		{
			$this->set_error('regimageinvalid');
			return false;
		}
	}

	/**
	 * @return bool
	 */
	function verify_username()
	{
		$this->get_login_data();

		if(empty($this->login_data) || !$this->login_data['id'])
		{
			$this->invalid_combination();
			return false;
		}

		return true;
	}

	/**
	 * @param bool $strict
	 *
	 * @return bool
	 */
	function verify_password($strict = true)
	{
		global $db, $mybb, $plugins;

		$this->get_login_data();

		if(empty($this->login_data['username']))
		{
			// Username must be validated to apply a password to
			$this->invalid_combination();
			return false;
		}

		$args = array(
			'this' => &$this,
			'strict' => &$strict,
		);

		$plugins->run_hooks('datahandler_login_verify_password_start', $args);

		$user = &$this->data;

		if(!$this->login_data['id'] || $this->login_data['id'] && !$this->login_data['salt'] && $strict == false)
		{
			$this->invalid_combination();
		}

		if($strict == true)
		{
			if(!$this->login_data['loginkey'])
			{
				$this->login_data['loginkey'] = generate_loginkey();

				$sql_array = array(
					"loginkey" => $this->login_data['loginkey']
				);

				$db->update_query("users", $sql_array, "id = '{$this->login_data['id']}'");
			}
		}

		$plugins->run_hooks('datahandler_login_verify_password_end', $args);

		if(!verify_user_password($this->login_data, $user['password']))
		{
			$this->invalid_combination(true);
			return false;
		}

		return true;
	}

	/**
	 * @param bool $show_login_attempts
	 */
	function invalid_combination($show_login_attempts = false)
	{
		global $db, $lang, $mybb, $failedlogintext, $failedlogincount, $username_method;

		//$username_method = "0";
		//$failedlogincount = "10";
		//$failedlogintext = "1";
		
		// Don't show an error when the captcha was wrong!
		if(!$this->captcha_verified)
		{
			return;
		}

		$login_text = '';
		if($show_login_attempts)
		{
			if($failedlogincount != 0 && $failedlogintext == 1 && $this->login_data['id'] != 0)
			{
				$logins = login_attempt_check($this->login_data['id'], false) + 1;
				$login_text = sprintf($lang->member['failed_login_again'], $failedlogincount - $logins);
			}
		}

		switch($username_method)
		{
			case 1:
				$this->set_error('invalidpwordusernameemail', $login_text);
				break;
			case 2:
				$this->set_error('invalidpwordusernamecombo', $login_text);
				break;
			default:
				$this->set_error('invalidpwordusername', $login_text);
				break;
		}
	}

	function get_login_data()
	{
		global $db, $settings, $username_method;

		$user = &$this->data;
		
		//$username_method = "0";

		$options = array(
			'fields' => '*',
			'username_method' => (int)$username_method
		);

		if($this->username_method !== null)
		{
			$options['username_method'] = (int)$this->username_method;
		}

		$this->login_data = get_user_by_username($user['username'], $options);
	}

	/**
	 * @return bool
	 */
	function validate_login()
	{
		global $plugins, $mybb;

		$user = &$this->data;

		$plugins->run_hooks('datahandler_login_validate_start', $this);

		
		$captchaimage = "0";
		
		
		if(!defined('IN_ADMINCP'))
		{
			$this->verify_attempts($captchaimage);
		}

		if(array_key_exists('username', $user))
		{
			$this->verify_username();
		}

		if(array_key_exists('password', $user))
		{
			$this->verify_password();
		}

		$plugins->run_hooks('datahandler_login_validate_end', $this);

		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}

		return true;
	}

	/**
	 * @return bool true
	 */
	function complete_login()
	{
		global $plugins, $db, $mybb, $session;

		$user = &$this->login_data;

		$plugins->run_hooks('datahandler_login_complete_start', $this);

		// Login to MyBB
		my_setcookie('loginattempts', 1);
		my_setcookie("sid", $session->sid, -1, true);

		$newsession = array(
			"uid" => $user['id'],
		);

		$db->update_query("sessions", $newsession, "sid = '{$session->sid}'");
		$db->update_query("users", array("loginattempts" => 1), "id = '{$user['id']}'");

		$remember = null;
		if(!isset($mybb->input['remember']) || $mybb->input['remember'] != "yes")
		{
			$remember = -1;
		}

		my_setcookie("mybbuser", $user['id']."_".$user['loginkey'], $remember, true, "lax");
		
		
		

		if($this->captcha !== false)
		{
			$this->captcha->invalidate_captcha();
		}

		$plugins->run_hooks('datahandler_login_complete_end', $this);

		return true;
	}
}
