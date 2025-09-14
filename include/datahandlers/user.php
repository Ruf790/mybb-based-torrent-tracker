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
 * User handling class, provides common structure to handle user data.
 *
 */
 
 
class UserDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	public $language_file = 'datahandler_user';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	public $language_prefix = 'userdata';

	/**
	 * Array of data inserted in to a user.
	 *
	 * @var array
	 */
	public $user_insert_data = array();

	/**
	 * Array of data used to update a user.
	 *
	 * @var array
	 */
	public $user_update_data = array();

	/**
	 * User ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	public $uid = 0;

	/**
	 * Values to be returned after inserting/deleting an user.
	 *
	 * @var array
	 */
	public $return_values = array();

	/**
	 * @var array
	 */
	var $delete_uids = array();

	/**
	 * @var int
	 */
	var $deleted_users = 0;

	/**
	 * Verifies if a username is valid or invalid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_username()
	{
		global $mybb, $maxnamelength, $minnamelength;

		$username = &$this->data['username'];
		require_once INC_PATH.'/functions_user.php';

		// Fix bad characters
		$username = trim_blank_chrs($username);
		$username = str_replace(array(unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)), array(" ", "-", "", "", "", ""), $username);

		// Remove multiple spaces from the username
		$username = preg_replace("#\s{2,}#", " ", $username);

		// Check if the username is not empty.
		if($username == '')
		{
			$this->set_error('missing_username');
			return false;
		}

		// Check if the username belongs to the list of banned usernames.
		if(is_banned_username($username, true))
		{
			$this->set_error('banned_username');
			return false;
		}

		// Check for certain characters in username (<, >, &, commas and slashes)
		if(strpos($username, "<") !== false || strpos($username, ">") !== false || strpos($username, "&") !== false || my_strpos($username, "\\") !== false || strpos($username, ";") !== false || strpos($username, ",") !== false || !validate_utf8_string($username, false, false))
		{
			$this->set_error("bad_characters_username");
			return false;
		}

		// Check if the username is of the correct length.
		
		
		
		if(($maxnamelength != 0 && my_strlen($username) > $maxnamelength) || ($minnamelength != 0 && my_strlen($username) < $minnamelength))
		{
			$this->set_error('invalid_username_length', array($minnamelength, $maxnamelength));
			return false;
		}

		return true;
	}

	/**
	 * Verifies if a usertitle is valid or invalid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_usertitle()
	{
		global $mybb;

		$usertitle = &$this->data['usertitle'];

		$customtitlemaxlength = "40";
		
		// Check if the usertitle is of the correct length.
		if($customtitlemaxlength != 0 && my_strlen($usertitle) > $customtitlemaxlength)
		{
			$this->set_error('invalid_usertitle_length', $customtitlemaxlength);
			return false;
		}

		return true;
	}

	/**
	 * Verifies if a username is already in use or not.
	 *
	 * @return boolean False when the username is not in use, true when it is.
	 */
	function verify_invitehash()
	{
		global $user, $db;
		
		
		//if(isset($user['password2']) && $user['password'] !== $user['password2'])
			
		if (isset($user['invitehash'])) 
		{
          
			$InviteQuery = $db->simple_select("invites", "inviter", "hash='{$user['invitehash']}'");
			
            if ($db->num_rows($InviteQuery)) 
			{
                $Result = $db->fetch_array($InviteQuery);
                $invited_by = $Result["inviter"];
            } 
			else 
			{
				$this->set_error('The invite code you specified is invalid!');
            }
        } 
		else 
		{
           $this->set_error('The invite code you specified is invalid!');
        }
		
		
	}
	
	
	
	
	
	
	function verify_username_exists()
	{
		$username = &$this->data['username'];

		$user = get_user_by_username(trim($username));

		if(!empty($this->data['id']) && !empty($user['id']) && $user['id'] == $this->data['id'])
		{
			unset($user);
		}

		if(!empty($user['id']))
		{
			$this->set_error("username_exists", array($username));
			return true;
		}

		return false;
	}



	/**
	* Verifies if a new password is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_password()
	{
		global $mybb, $requirecomplexpasswords, $minpasswordlength, $maxpasswordlength;

		$user = &$this->data;

		// Always check for the length of the password.
		
		//$minpasswordlength = "6";
		//$maxpasswordlength = "30";
		
		
		if(my_strlen($user['password']) < $minpasswordlength || my_strlen($user['password']) > $maxpasswordlength)
		{
			$this->set_error('invalid_password_length', array($minpasswordlength, $maxpasswordlength));
			return false;
		}

		// Has the user tried to use their email address or username as a password?
		if(!empty($user['email']) && !empty($user['username']))
		{
			if($user['email'] === $user['password'] || $user['username'] === $user['password']
				|| strpos($user['password'], $user['email']) !== false || strpos($user['password'], $user['username']) !== false
				|| strpos($user['email'], $user['password']) !== false || strpos($user['username'], $user['password']) !== false)
			{
				$this->set_error('bad_password_security');
				return false;
			}
		}

		// See if the board has "require complex passwords" enabled.
		//$requirecomplexpasswords = "0";
		
		if($requirecomplexpasswords == 1)
		{
			// Complex passwords required, do some extra checks.
			// First, see if there is one or more complex character(s) in the password.
			if(!preg_match("/^.*(?=.{".$minpasswordlength.",})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $user['password']))
			{
				$this->set_error('no_complex_characters', array($minpasswordlength));
				return false;
			}
		}

		// If we have a "password2" check if they both match
		if(isset($user['password2']) && $user['password'] !== $user['password2'])
		{
			$this->set_error("passwords_dont_match");
			return false;
		}

		// Generate the user login key
		$user['loginkey'] = generate_loginkey();
		
		//$user['secret'] = mksecret();
        //$user['passhash'] = md5($user['secret'] . $user['password'] . $user['secret']);
		
		

		// Combine the password and salt
		$password_fields = create_password($user['password'], false, $user);
		$user = array_merge($user, $password_fields);

		return true;
	}

	/**
	* Verifies usergroup selections and other group details.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_usergroup()
	{
		return true;
	}
	/**
	* Verifies if an email address is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_email()
	{
		global $mybb;

		$user = &$this->data;

		// Check if an email address has actually been entered.
		if(trim_blank_chrs($user['email']) == '')
		{
			$this->set_error('missing_email');
			return false;
		}

		// Check if this is a proper email address.
		if(!validate_email_format($user['email']))
		{
			$this->set_error('invalid_email_format');
			return false;
		}

		// Check banned emails
		if(is_banned_email($user['email'], true))
		{
			$this->set_error('banned_email');
			return false;
		}

		
		// Check signed up emails
		// Ignore the ACP because the Merge System sometimes produces users with duplicate email addresses (Not A Bug)
		$allowmultipleemails = "0";
		
		if($allowmultipleemails == 0 && !defined("IN_ADMINCP"))
		{
			$uid = 0;
			if(isset($user['uid']))
			{
				$uid = $user['uid'];
			}
			if(email_already_in_use($user['email'], $uid))
			{
				$this->set_error('email_already_in_use');
				return false;
			}
		}

		// If we have an "email2", verify it matches the existing email
		if(isset($user['email2']) && $user['email'] != $user['email2'])
		{
			$this->set_error("emails_dont_match");
			
			return false;
		}

		return true;
	}

	/**
	* Verifies if a website is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_website()
	{
		$website = &$this->data['website'];

		if(!empty($website) && !my_validate_url($website))
		{
			$website = 'http://'.$website;
		}

		if(!empty($website) && !my_validate_url($website))
		{
			$this->set_error('invalid_website');
			return false;
		}

		return true;
	}

	/**
	 * Verifies if an ICQ number is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_icq()
	{
		$icq = &$this->data['icq'];

		if($icq != '' && !is_numeric($icq))
		{
			$this->set_error("invalid_icq_number");
			return false;
		}
		$icq = (int)$icq;
		return true;
	}

	/**
	* Verifies if a birthday is valid or not.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_birthday()
	{
		global $mybb;

		$user = &$this->data;
		$birthday = &$user['birthday'];

		if(!is_array($birthday))
		{
			return true;
		}

		// Sanitize any input we have
		$birthday['day'] = (int)$birthday['day'];
		$birthday['month'] = (int)$birthday['month'];
		$birthday['year'] = (int)$birthday['year'];

		// Error if a day and month exists, and the birthday day and range is not in range
		if($birthday['day'] != 0 || $birthday['month'] != 0)
		{
			if($birthday['day'] < 1 || $birthday['day'] > 31 || $birthday['month'] < 1 || $birthday['month'] > 12 || ($birthday['month'] == 2 && $birthday['day'] > 29))
			{
				$this->set_error("invalid_birthday");
				return false;
			}
		}

		// Check if the day actually exists.
		$months = get_bdays($birthday['year']);
		if($birthday['month'] != 0 && $birthday['day'] > $months[$birthday['month']-1])
		{
			$this->set_error("invalid_birthday");
			return false;
		}

		// Error if a year exists and the year is out of range
		if($birthday['year'] != 0 && ($birthday['year'] < (date("Y")-100)) || $birthday['year'] > date("Y"))
		{
			$this->set_error("invalid_birthday");
			return false;
		}
		elseif($birthday['year'] == date("Y"))
		{
			// Error if birth date is in future
			if($birthday['month'] > date("m") || ($birthday['month'] == date("m") && $birthday['day'] > date("d")))
			{
				$this->set_error("invalid_birthday");
				return false;
			}
		}

		// Error if COPPA is on, and the user hasn't verified their age / under 13
		$coppa = "disabled";
		
		if($coppa == "enabled" && ($birthday['year'] == 0 || !$birthday['year']))
		{
			$this->set_error("invalid_birthday_coppa");
			return false;
		}
		elseif(($coppa == "deny" && $birthday['year'] > (date("Y")-13)) && !is_moderator())
		{
			$this->set_error("invalid_birthday_coppa2");
			return false;
		}

		// Make the user's birthday field
		if($birthday['year'] != 0)
		{
			// If the year is specified, put together a d-m-y string
			$user['bday'] = $birthday['day']."-".$birthday['month']."-".$birthday['year'];
		}
		elseif($birthday['day'] && $birthday['month'])
		{
			// If only a day and month are specified, put together a d-m string
			$user['bday'] = $birthday['day']."-".$birthday['month']."-";
		}
		else
		{
			// No field is specified, so return an empty string for an unknown birthday
			$user['bday'] = '';
		}
		return true;
	}

	/**
	 * Verifies if the birthday privacy option is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_birthday_privacy()
	{
		$birthdayprivacy = &$this->data['birthdayprivacy'];
		$accepted = array(
					'none',
					'age',
					'all');

		if(!in_array($birthdayprivacy, $accepted))
		{
			$this->set_error("invalid_birthday_privacy");
			return false;
		}
		else if ($birthdayprivacy == 'age')
		{
			$birthdayyear = &$this->data['birthday']['year'];
			if(empty($birthdayyear))
			{
				$this->set_error("conflicted_birthday_privacy");
				return false;
			}
		}
		return true;
	}

	/**
	* Verifies if the post count field is filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_postnum()
	{
		$user = &$this->data;

		if(isset($user['postnum']) && $user['postnum'] < 0)
		{
			$this->set_error("invalid_postnum");
			return false;
		}

		return true;
	}

	/**
	* Verifies if the thread count field is filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_threadnum()
	{
		$user = &$this->data;

		if(isset($user['threadnum']) && $user['threadnum'] < 0)
		{
			$this->set_error("invalid_threadnum");
			return false;
		}

		return true;
	}

	/**
	* Verifies if a profile fields are filled in correctly.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_profile_fields()
	{
		global $db, $cache;

		$user = &$this->data;
		$profile_fields = &$this->data['profile_fields'];

		// Loop through profile fields checking if they exist or not and are filled in.

		// Fetch all profile fields first.
		$pfcache = $cache->read('profilefields');

		if(is_array($pfcache))
		{
			// Then loop through the profile fields.
			foreach($pfcache as $profilefield)
			{
				if(isset($this->data['profile_fields_editable']) || isset($this->data['registration']) && ($profilefield['required'] == 1 || $profilefield['registration'] == 1))
				{
					$profilefield['editableby'] = -1;
				}

				if(isset($user['usergroup']))
				{
					$usergroup = $user['usergroup'];
				}
				else
				{
					$usergroup = '';
				}
				if(isset($user['additionalgroups']))
				{
					$additionalgroups = $user['additionalgroups'];
				}
				else
				{
					$additionalgroups = '';
				}

				if(!is_member($profilefield['editableby'], array('usergroup' => $usergroup, 'additionalgroups' => $additionalgroups)))
				{
					continue;
				}

				// Does this field have a minimum post count?
				if(!isset($this->data['profile_fields_editable']) && !empty($profilefield['postnum']) && $profilefield['postnum'] > $user['postnum'])
				{
					continue;
				}

				$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
				$profilefield['name'] = htmlspecialchars_uni($profilefield['name']);
				$thing = explode("\n", $profilefield['type'], "2");
				$type = trim($thing[0]);
				$field = "fid{$profilefield['fid']}";

				if(!isset($profile_fields[$field]))
				{
					$profile_fields[$field] = '';
				}

				// If the profile field is required, but not filled in, present error.
				if($type != "multiselect" && $type != "checkbox")
				{
					if(trim($profile_fields[$field]) == "" && $profilefield['required'] == 1 && !defined('IN_ADMINCP') && THIS_SCRIPT != "modcp.php")
					{
						$this->set_error('missing_required_profile_field', array($profilefield['name']));
					}
				}
				elseif(($type == "multiselect" || $type == "checkbox") && $profile_fields[$field] == "" && $profilefield['required'] == 1 && !defined('IN_ADMINCP') && THIS_SCRIPT != "modcp.php")
				{
					$this->set_error('missing_required_profile_field', array($profilefield['name']));
				}

				// Sort out multiselect/checkbox profile fields.
				$options = '';
				if(($type == "multiselect" || $type == "checkbox") && is_array($profile_fields[$field]))
				{
					$expoptions = explode("\n", $thing[1]);
					$expoptions = array_map('trim', $expoptions);
					foreach($profile_fields[$field] as $value)
					{
						if(!in_array(htmlspecialchars_uni($value), $expoptions))
						{
							$this->set_error('bad_profile_field_values', array($profilefield['name']));
						}
						if($options)
						{
							$options .= "\n";
						}
						$options .= $db->escape_string($value);
					}
				}
				elseif($type == "select" || $type == "radio")
				{
					$expoptions = explode("\n", $thing[1]);
					$expoptions = array_map('trim', $expoptions);
					if(!in_array(htmlspecialchars_uni($profile_fields[$field]), $expoptions) && trim($profile_fields[$field]) != "")
					{
						$this->set_error('bad_profile_field_values', array($profilefield['name']));
					}
					$options = $db->escape_string($profile_fields[$field]);
				}
				else
				{
					if($profilefield['maxlength'] > 0 && my_strlen($profile_fields[$field]) > $profilefield['maxlength'])
					{
						$this->set_error('max_limit_reached', array($profilefield['name'], $profilefield['maxlength']));
					}

					if(!empty($profilefield['regex']) && !empty($profile_fields[$field]) && !preg_match("#".$profilefield['regex']."#i", $profile_fields[$field]))
					{
						$this->set_error('bad_profile_field_value', array($profilefield['name']));
					}

					$options = $db->escape_string($profile_fields[$field]);
				}
				$user['user_fields'][$field] = $options;
			}
		}

		return true;
	}


	/**
	* Verifies user options.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function verify_options()
	{
		global $mybb;

		$options = &$this->data['options'];

		if(!is_array($options))
		{
			$options = array();
		}

		// Verify yes/no options.
		$this->verify_yesno_option($options, 'allownotices', 1);
		$this->verify_yesno_option($options, 'hideemail', 0);
		$this->verify_yesno_option($options, 'receivepms', 1);
		$this->verify_yesno_option($options, 'receivefrombuddy', 0);
		$this->verify_yesno_option($options, 'pmnotice', 1);
		$this->verify_yesno_option($options, 'pmnotify', 1);
		$this->verify_yesno_option($options, 'invisible', 0);
		$this->verify_yesno_option($options, 'showimages', 1);
		$this->verify_yesno_option($options, 'showvideos', 1);
		$this->verify_yesno_option($options, 'showsigs', 1);
		$this->verify_yesno_option($options, 'showavatars', 1);
		$this->verify_yesno_option($options, 'showquickreply', 1);
		$this->verify_yesno_option($options, 'showredirect', 1);
		$this->verify_yesno_option($options, 'showcodebuttons', 1);
		$this->verify_yesno_option($options, 'sourceeditor', 0);
		$this->verify_yesno_option($options, 'buddyrequestspm', 1);
		$this->verify_yesno_option($options, 'buddyrequestsauto', 0);

		if($mybb->settings['postlayout'] == 'classic')
		{
			$this->verify_yesno_option($options, 'classicpostbit', 1);
		}
		else
		{
			$this->verify_yesno_option($options, 'classicpostbit', 0);
		}

		if(array_key_exists('subscriptionmethod', $options))
		{
			// Value out of range
			$options['subscriptionmethod'] = (int)$options['subscriptionmethod'];
			if($options['subscriptionmethod'] < 0 || $options['subscriptionmethod'] > 3)
			{
				$options['subscriptionmethod'] = 0;
			}
		}

		if(array_key_exists('dstcorrection', $options))
		{
			// Value out of range
			$options['dstcorrection'] = (int)$options['dstcorrection'];
			if($options['dstcorrection'] < 0 || $options['dstcorrection'] > 2)
			{
				$options['dstcorrection'] = 0;
			}

			if($options['dstcorrection'] == 1)
			{
				$options['dst'] = 1;
			}
			elseif($options['dstcorrection'] == 0)
			{
				$options['dst'] = 0;
			}
		}

		if($this->method == "insert" || (isset($options['threadmode']) && $options['threadmode'] != "linear" && $options['threadmode'] != "threaded" && $options['threadmode'] != ''))
		{
			$options['threadmode'] = '';
		}

        $usertppoptions = "10,15,20,25,30,40,50";
		
		// Verify the "threads per page" option.
		if($this->method == "insert" || (array_key_exists('tpp', $options) && $usertppoptions))
		{
			if(!isset($options['tpp']))
			{
				$options['tpp'] = 0;
			}
			$explodedtpp = explode(",", $usertppoptions);
			if(is_array($explodedtpp))
			{
				@asort($explodedtpp);
				$biggest = $explodedtpp[count($explodedtpp)-1];
				// Is the selected option greater than the allowed options?
				if($options['tpp'] > $biggest)
				{
					$options['tpp'] = $biggest;
				}
			}
			$options['tpp'] = (int)$options['tpp'];
		}
		
		$userpppoptions = "5,10,15,20,25,30,40,50";
		
		// Verify the "posts per page" option.
		if($this->method == "insert" || (array_key_exists('ppp', $options) && $userpppoptions))
		{
			if(!isset($options['ppp']))
			{
				$options['ppp'] = 0;
			}
			$explodedppp = explode(",", $userpppoptions);
			if(is_array($explodedppp))
			{
				@asort($explodedppp);
				$biggest = $explodedppp[count($explodedppp)-1];
				// Is the selected option greater than the allowed options?
				if($options['ppp'] > $biggest)
				{
					$options['ppp'] = $biggest;
				}
			}
			$options['ppp'] = (int)$options['ppp'];
		}
		// Is our selected "days prune" option valid or not?
		if($this->method == "insert" || array_key_exists('daysprune', $options))
		{
			if(!isset($options['daysprune']))
			{
				$options['daysprune'] = 0;
			}
			$options['daysprune'] = (int)$options['daysprune'];
			if($options['daysprune'] < 0)
			{
				$options['daysprune'] = 0;
			}
		}
		$this->data['options'] = $options;
	}

	/**
	 * Verifies if a registration date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_regdate()
	{
		$regdate = &$this->data['regdate'];

		$regdate = (int)$regdate;
		// If the timestamp is below 0, set it to the current time.
		if($regdate <= 0)
		{
			$regdate = TIMENOW;
		}
		return true;
	}

	/**
	 * Verifies if a last visit date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_lastvisit()
	{
		$lastvisit = &$this->data['lastvisit'];

		$lastvisit = (int)$lastvisit;
		// If the timestamp is below 0, set it to the current time.
		if($lastvisit <= 0)
		{
			$lastvisit = TIMENOW;
		}
		return true;

	}

	/**
	 * Verifies if a last active date is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_lastactive()
	{
		$lastactive = &$this->data['lastactive'];

		$lastactive = (int)$lastactive;
		// If the timestamp is below 0, set it to the current time.
		if($lastactive <= 0)
		{
			$lastactive = TIMENOW;
		}
		return true;

	}

	/**
	 * Verifies if an away mode status is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_away()
	{
		global $mybb;

		$user = &$this->data;
		// If the board does not allow "away mode" or the user is marking as not away, set defaults.
		if($mybb->settings['allowaway'] == 0 || !isset($user['away']['away']) || $user['away']['away'] != 1)
		{
			$user['away']['away'] = 0;
			$user['away']['date'] = 0;
			$user['away']['returndate'] = 0;
			$user['away']['awayreason'] = '';
			return true;
		}
		elseif($user['away']['returndate'])
		{
			// Validate the awayreason length, since the db holds 200 chars for this field
			$reasonlength = my_strlen($user['away']['awayreason']);
			if($reasonlength > 200)
			{
				$this->set_error("away_too_long", array($reasonlength - 200));
				return false;
			}

			list($returnday, $returnmonth, $returnyear) = explode('-', $user['away']['returndate']);
			if(!$returnday || !$returnmonth || !$returnyear)
			{
				$this->set_error("missing_returndate");
				return false;
			}

			// Validate the return date lengths
			$user['away']['returndate'] = substr($returnday, 0, 2).'-'.substr($returnmonth, 0, 2).'-'.substr($returnyear, 0, 4);
		}
		return true;
	}

	/**
	 * Verifies if a language is valid for this user or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_language()
	{
		global $lang;

		$language = &$this->data['language'];

		// An invalid language has been specified?
		if($language != '' && !$lang->language_exists($language))
		{
			$this->set_error("invalid_language");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if a style is valid for this user or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_style()
	{
		global $lang;

		$user = &$this->data;

		if(!empty($user['style']))
		{
			$theme = get_theme($user['style']);

			if(empty($theme) || !is_member($theme['allowedgroups'], $user) && $theme['allowedgroups'] != 'all')
			{
				$this->set_error('invalid_style');
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies if this is coming from a spam bot or not
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_checkfields()
	{
		$user = &$this->data;

		// An invalid language has been specified?
		if($user['regcheck1'] !== "" || $user['regcheck2'] !== "true")
		{
			$this->set_error("invalid_checkfield");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if the user timezone is valid.
	 * If the timezone is invalid, the board default is used.
	 *
	 * @return boolean True when timezone was valid, false otherwise
	 */
	function verify_timezone()
	{
		global $mybb, $timezoneoffset;

		$user = &$this->data;

		$timezones = get_supported_timezones();

		if(!isset($user['timezone']) || !array_key_exists($user['timezone'], $timezones))
		{
			$user['timezone'] = $timezoneoffset;
			return false;
		}

		return true;
	}

	/**
	* Validate all user assets.
	*
	* @return boolean True when valid, false when invalid.
	*/
	function validate_user()
	{
		global $mybb, $plugins, $regtype;

		$user = &$this->data;

		// First, grab the old user details if this user exists
		if(!empty($user['uid']))
		{
			$old_user = get_user($user['uid']);
		}

		if($this->method == "insert" || array_key_exists('username', $user))
		{
			// If the username is the same - no need to verify
			if(!isset($old_user['username']) || $user['username'] != $old_user['username'])
			{
				$this->verify_username();
				
				if($regtype == "invite") 
                {
				   $this->verify_invitehash();
				}
				
				
				$this->verify_username_exists();
			}
			else
			{
				unset($user['username']);
			}
		}
		if($this->method == "insert" || array_key_exists('usertitle', $user))
		{
			$this->verify_usertitle();
		}
		if($this->method == "insert" || array_key_exists('password', $user))
		{
			$this->verify_password();
		}
		if($this->method == "insert" || array_key_exists('usergroup', $user))
		{
			$this->verify_usergroup();
		}
		if($this->method == "insert" || array_key_exists('email', $user))
		{
			$this->verify_email();
		}
		if($this->method == "insert" || array_key_exists('website', $user))
		{
			$this->verify_website();
		}
		if($this->method == "insert" || array_key_exists('icq', $user))
		{
			$this->verify_icq();
		}
		if($this->method == "insert" || (isset($user['birthday']) && is_array($user['birthday'])))
		{
			$this->verify_birthday();
		}
		if($this->method == "insert" || array_key_exists('postnum', $user))
		{
			$this->verify_postnum();
		}
		if($this->method == "insert" || array_key_exists('threadnum', $user))
		{
			$this->verify_threadnum();
		}
		if($this->method == "insert" || array_key_exists('profile_fields', $user))
		{
			$this->verify_profile_fields();
		}
		//if($this->method == "insert" || array_key_exists('referrer', $user))
		//{
			//$this->verify_referrer();
		//}
		if($this->method == "insert" || array_key_exists('options', $user))
		{
			$this->verify_options();
		}
		if($this->method == "insert" || array_key_exists('regdate', $user))
		{
			$this->verify_regdate();
		}
		if($this->method == "insert" || array_key_exists('lastvisit', $user))
		{
			$this->verify_lastvisit();
		}
		if($this->method == "insert" || array_key_exists('lastactive', $user))
		{
			$this->verify_lastactive();
		}
		if($this->method == "insert" || array_key_exists('away', $user))
		{
			$this->verify_away();
		}
		//if($this->method == "insert" || array_key_exists('language', $user))
		//{
		//	$this->verify_language();
		//}
		if($this->method == "insert" || array_key_exists('timezone', $user))
		{
			$this->verify_timezone();
		}
		if($this->method == "insert" && array_key_exists('regcheck1', $user) && array_key_exists('regcheck2', $user))
		{
			$this->verify_checkfields();
		}
		if(array_key_exists('birthdayprivacy', $user))
		{
			$this->verify_birthday_privacy();
		}
		if($this->method == "insert" || array_key_exists('style', $user))
		{
			$this->verify_style();
		}
		if($this->method == "insert" || array_key_exists('signature', $user))
		{
			$this->verify_signature();
		}

		$plugins->run_hooks("datahandler_user_validate", $this);

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Inserts a user into the database.
	*
	* @return array
	*/
	function insert_user()
	{
		global $db, $cache, $plugins, $regtype;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The user needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The user is not valid.");
		}

		$user = &$this->data;

		$array = array('postnum', 'threadnum', 'avatar', 'avatartype', 'additionalgroups', 'displaygroup', 'icq', 'skype', 'google', 'bday', 'signature', 'style', 'dateformat', 'timeformat', 'notepad', 'regip', 'lastip', 'coppa_user');
		foreach($array as $value)
		{
			if(!isset($user[$value]))
			{
				$user[$value] = '';
			}
		}

		$array = array('subscriptionmethod', 'dstcorrection');
		foreach($array as $value)
		{
			if(!isset($user['options'][$value]))
			{
				$user['options'][$value] = '';
			}
		}

		// If user is being created from ACP, there is no last visit or last active
		if(defined('IN_ADMINCP'))
		{
			$user['lastvisit'] = $user['lastactive'] = 0;
		}
		
		
		require INC_PATH . '/readconfig.php';
		
		
		$uploaded = 0 < $autogigsignup ? $autogigsignup * 1024 * 1024 * 1024 : 0;
        $seedbonus = 0 < $autosbsignup ? $autosbsignup : 0;
        $usergroup = $_d_usergroup ? $_d_usergroup : 1;
		
		$invites = 0 + $invite_count;
		
		$invited_by = "0";
		
		
		if($regtype == "invite") 
		{
			
		    $InviteQuery = $db->simple_select("invites", "inviter", "hash='{$user['invitehash']}'");
	        if ($db->num_rows($InviteQuery)) 
		    {
              $Result = $db->fetch_array($InviteQuery);
              $invited_by = $Result["inviter"];
            } 
		}
		
		
		$this->user_insert_data = array(
			"username" => $db->escape_string($user['username']),
			"password" => $user['password'],
			"salt" => $user['salt'],
			"loginkey" => $user['loginkey'],
			
			"uploaded" => $db->escape_string($uploaded),
			"seedbonus" => $db->escape_string($seedbonus),
			
			"invites" => $db->escape_string($invites),
			
			"invited_by" => $db->escape_string($invited_by),
			
			"email" => $db->escape_string($user['email']),
			"postnum" => (int)$user['postnum'],
			"threadnum" => (int)$user['threadnum'],
			"avatar" => $db->escape_string($user['avatar']),
			"avatartype" => $db->escape_string($user['avatartype']),
			"usergroup" => $db->escape_string($usergroup),
			"additionalgroups" => $db->escape_string($user['additionalgroups']),
			"displaygroup" => (int)$user['displaygroup'],
			"usertitle" => $db->escape_string(htmlspecialchars_uni($user['usertitle'])),
			"added" => (int)$user['regdate'],
			"lastactive" => (int)$user['lastactive'],
			"lastvisit" => (int)$user['lastvisit'],
			"birthday" => $user['bday'],
			"signature" => $db->escape_string($user['signature']),
			"allownotices" => (int)$user['options']['allownotices'],
			"hideemail" => (int)$user['options']['hideemail'],
			"subscriptionmethod" => (int)$user['options']['subscriptionmethod'],
			"receivepms" => (int)$user['options']['receivepms'],
			"receivefrombuddy" => (int)$user['options']['receivefrombuddy'],
			"pmnotice" => (int)$user['options']['pmnotice'],
			"pmnotify" => (int)$user['options']['pmnotify'],
			"showsigs" => (int)$user['options']['showsigs'],
			"showavatars" => (int)$user['options']['showavatars'],
			"showredirect" => (int)$user['options']['showredirect'],
			"invisible" => (int)$user['options']['invisible'],
			"timezone" => $db->escape_string($user['timezone']),
			"dstcorrection" => (int)$user['options']['dstcorrection'],
			"threadmode" => $user['options']['threadmode'],
			"dateformat" => $db->escape_string($user['dateformat']),
			"timeformat" => $db->escape_string($user['timeformat']),
			"regip" => $db->escape_binary($user['regip']),
			"lastip" => $db->escape_binary($user['lastip']),
			//"language" => $db->escape_string($user['language']),
			"buddyrequestspm" => (int)$user['options']['buddyrequestspm'],
			"buddyrequestsauto" => (int)$user['options']['buddyrequestsauto'],
			"buddylist" => '',
			"ignorelist" => '',
			"pmfolders" => "0**$%%$1**$%%$2**$%%$3**$%%$4**",
			//"notepad" => '',
			//"warningpoints" => 0,
			"moderateposts" => 0,
			"moderationtime" => 0
			//"suspendposting" => 0,
			//"suspensiontime" => 0,
			//"coppauser" => (int)$user['coppa_user'],
			//"classicpostbit" => (int)$user['options']['classicpostbit'],
			//"usernotes" => ''
		);

		if($user['options']['dstcorrection'] == 1)
		{
			$this->user_insert_data['dst'] = 1;
		}
		elseif($user['options']['dstcorrection'] == 0)
		{
			$this->user_insert_data['dst'] = 0;
		}

		$plugins->run_hooks("datahandler_user_insert", $this);

		$this->uid = $db->insert_query("users", $this->user_insert_data);
		

		$user['user_fields']['ufid'] = $this->uid;
		
		
		$secret_questions = array(
		    "userid" => $db->escape_string($this->uid),
		    "passhint" => $user['passhint'],
		    "hintanswer" => $db->escape_string(md5($user['hintanswer']))
		);
			
	    $db->replace_query("ts_secret_questions", $secret_questions);
		
		
		if($regtype == "invite") 
		{
               $db->sql_query("DELETE FROM invites WHERE hash = " . $db->sqlesc($user['invitehash']));
        }
		
		
		
		

		$pfcache = $cache->read('profilefields');

		if(is_array($pfcache))
		{
			foreach($pfcache as $profile_field)
			{
				if(array_key_exists("fid{$profile_field['fid']}", $user['user_fields']))
				{
					continue;
				}
				$user['user_fields']["fid{$profile_field['fid']}"] = '';
			}
		}

		$db->insert_query("userfields", $user['user_fields'], false);

	

		// Update forum stats
		update_stats(array('numusers' => '+1'));

		if((int)$user['usergroup'] == 5)
		{
			$cache->update_awaitingactivation();
		}

		$this->return_values = array(
			"uid" => $this->uid,
			"username" => $user['username'],
			"loginkey" => $user['loginkey'],
			"email" => $user['email'],
			"password" => $user['password'],
			"usergroup" => $user['usergroup']
		);

		$plugins->run_hooks("datahandler_user_insert_end", $this);

		return $this->return_values;
	}

	/**
	* Updates a user in the database.
	*
	* @return bool
	*/
	function update_user()
	{
		global $db, $plugins, $cache;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The user needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The user is not valid.");
		}

		$user = &$this->data;
		$user['uid'] = (int)$user['uid'];
		$this->uid = $user['uid'];

		// Set up the update data.
		if(isset($user['username']))
		{
			$this->user_update_data['username'] = $db->escape_string($user['username']);
		}
		if(isset($user['password']))
		{
			$this->user_update_data['password'] = $user['password'];
		}
		if(isset($user['salt']))
		{
			$this->user_update_data['salt'] = $user['salt'];
		}
		if(isset($user['loginkey']))
		{
			$this->user_update_data['loginkey'] = $user['loginkey'];
		}
		if(isset($user['email']))
		{
			$this->user_update_data['email'] = $db->escape_string($user['email']);
		}
		if(isset($user['postnum']))
		{
			$this->user_update_data['postnum'] = (int)$user['postnum'];
		}
		if(isset($user['threadnum']))
		{
			$this->user_update_data['threadnum'] = (int)$user['threadnum'];
		}
		if(isset($user['avatar']))
		{
			$this->user_update_data['avatar'] = $db->escape_string($user['avatar']);
			$this->user_update_data['avatartype'] = $db->escape_string($user['avatartype']);
		}
		if(isset($user['usergroup']))
		{
			$this->user_update_data['usergroup'] = (int)$user['usergroup'];
		}
		if(isset($user['additionalgroups']))
		{
			$this->user_update_data['additionalgroups'] = $db->escape_string($user['additionalgroups']);
		}
		if(isset($user['displaygroup']))
		{
			$this->user_update_data['displaygroup'] = (int)$user['displaygroup'];
		}
		if(isset($user['usertitle']))
		{
			$this->user_update_data['usertitle'] = $db->escape_string($user['usertitle']);
		}
		if(isset($user['regdate']))
		{
			$this->user_update_data['regdate'] = (int)$user['regdate'];
		}
		if(isset($user['lastactive']))
		{
			$this->user_update_data['lastactive'] = (int)$user['lastactive'];
		}
		if(isset($user['lastvisit']))
		{
			$this->user_update_data['lastvisit'] = (int)$user['lastvisit'];
		}
		if(isset($user['signature']))
		{
			$this->user_update_data['signature'] = $db->escape_string($user['signature']);
		}
		if(isset($user['website']))
		{
			$this->user_update_data['website'] = $db->escape_string($user['website']);
		}
		if(isset($user['icq']))
		{
			$this->user_update_data['icq'] = (int)$user['icq'];
		}
		if(isset($user['skype']))
		{
			$this->user_update_data['skype'] = $db->escape_string($user['skype']);
		}
		if(isset($user['google']))
		{
			$this->user_update_data['google'] = $db->escape_string($user['google']);
		}
		if(isset($user['bday']))
		{
			$this->user_update_data['birthday'] = $user['bday'];
		}
		if(isset($user['birthdayprivacy']))
		{
			$this->user_update_data['birthdayprivacy'] = $db->escape_string($user['birthdayprivacy']);
		}
		if(isset($user['style']))
		{
			$this->user_update_data['style'] = (int)$user['style'];
		}
		if(isset($user['timezone']))
		{
			$this->user_update_data['timezone'] = $db->escape_string($user['timezone']);
		}
		if(isset($user['dateformat']))
		{
			$this->user_update_data['dateformat'] = $db->escape_string($user['dateformat']);
		}
		if(isset($user['timeformat']))
		{
			$this->user_update_data['timeformat'] = $db->escape_string($user['timeformat']);
		}
		if(isset($user['regip']))
		{
			$this->user_update_data['regip'] = $db->escape_binary($user['regip']);
		}
		if(isset($user['lastip']))
		{
			$this->user_update_data['lastip'] = $db->escape_binary($user['lastip']);
		}
		if(isset($user['language']))
		{
			$this->user_update_data['language'] = $db->escape_string($user['language']);
		}
		if(isset($user['away']))
		{
			$this->user_update_data['away'] = (int)$user['away']['away'];
			$this->user_update_data['awaydate'] = $db->escape_string($user['away']['date']);
			$this->user_update_data['returndate'] = $db->escape_string($user['away']['returndate']);
			$this->user_update_data['awayreason'] = $db->escape_string($user['away']['awayreason']);
		}
		if(isset($user['notepad']))
		{
			$this->user_update_data['notepad'] = $db->escape_string($user['notepad']);
		}
		if(isset($user['usernotes']))
		{
			$this->user_update_data['usernotes'] = $db->escape_string($user['usernotes']);
		}
		if(isset($user['options']) && is_array($user['options']))
		{
			foreach($user['options'] as $option => $value)
			{
				$this->user_update_data[$option] = $value;
			}
		}
		if(array_key_exists('coppa_user', $user))
		{
			$this->user_update_data['coppauser'] = (int)$user['coppa_user'];
		}
		// First, grab the old user details for later use.
		$old_user = get_user($user['uid']);

		// If old user has new pmnotice and new user has = yes, keep old value
		if(isset($this->user_update_data['pmnotice']) && $old_user['pmnotice'] == "2" && $this->user_update_data['pmnotice'] == 1)
		{
			unset($this->user_update_data['pmnotice']);
		}

		$plugins->run_hooks("datahandler_user_update", $this);

		if(count($this->user_update_data) < 1 && empty($user['user_fields']))
		{
			return false;
		}

		if(count($this->user_update_data) > 0)
		{
			// Actual updating happens here.
			$db->update_query("users", $this->user_update_data, "id='{$user['uid']}'");
		}

		//$cache->update_moderators();
		if(isset($user['bday']) || isset($user['username']))
		{
			$cache->update_birthdays();
		}

		//if(isset($user['usergroup']) && (int)$user['usergroup'] == 5)
		//{
		//	$cache->update_awaitingactivation();
		//}

		// Maybe some userfields need to be updated?
		if(isset($user['user_fields']) && is_array($user['user_fields']))
		{
			$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
			$fields = $db->fetch_array($query);
			if(empty($fields['ufid']))
			{
				$user_fields = array(
					'ufid' => $user['uid']
				);

				$fields_array = $db->show_fields_from("userfields");
				foreach($fields_array as $field)
				{
					if($field['Field'] == 'ufid')
					{
						continue;
					}
					$user_fields[$field['Field']] = '';
				}
				$db->insert_query("userfields", $user_fields);
			}
			$db->update_query("userfields", $user['user_fields'], "ufid='{$user['uid']}'", false);
		}

		// Let's make sure the user's name gets changed everywhere in the db if it changed.
		if(!empty($this->user_update_data['username']) && $this->user_update_data['username'] != $old_user['username'])
		{
			$username_update = array(
				"username" => $this->user_update_data['username']
			);
			$lastposter_update = array(
				"lastposter" => $this->user_update_data['username']
			);

			$db->update_query("tsf_posts", $username_update, "uid='{$user['uid']}'");
			$db->update_query("tsf_threads", $username_update, "uid='{$user['uid']}'");
			$db->update_query("tsf_threads", $lastposter_update, "lastposteruid='{$user['uid']}'");
			$db->update_query("tsf_forums", $lastposter_update, "lastposteruid='{$user['uid']}'");

			$stats = $cache->read("stats");
			if($stats['lastuid'] == $user['uid'])
			{
				// User was latest to register, update stats
				update_stats(array("numusers" => "+0"));
			}
		}

		return true;
	}

	/**
	 * Provides a method to completely delete a user.
	 *
	 * @param array $delete_uids Array of user information
	 * @param integer $prunecontent Whether if delete threads/posts or not
	 * @return array
	 */
	function delete_user($delete_uids, $prunecontent=0)
	{
		global $db, $plugins, $mybb, $cache, $CURUSER;

		// Yes, validating is required.
		if(count($this->get_errors()) > 0)
		{
			die('The user is not valid.');
		}

		$this->delete_uids = array_map('intval', (array)$delete_uids);

		foreach($this->delete_uids as $key => $uid)
		{
			if(!$uid || is_super_admin($uid) || $uid == $CURUSER['id'])
			{
				// Remove super admins
				unset($this->delete_uids[$key]);
			}
		}

		$plugins->run_hooks('datahandler_user_delete_start', $this);

		$this->delete_uids = implode(',', $this->delete_uids);

		if(empty($this->delete_uids))
		{
			$this->deleted_users = 0;
			$this->return_values = array(
				"deleted_users" => $this->deleted_users
			);

			return $this->return_values;
		}

		$this->delete_content();

		// Delete the user
		$query = $db->delete_query('users', "id IN({$this->delete_uids})");
		$this->deleted_users = $db->affected_rows($query);

		// Are we removing the posts/threads of a user?
		if((int)$prunecontent == 1)
		{
			$this->delete_posts();
			$db->delete_query('tsf_announcements', "uid IN({$this->delete_uids})");
		}
		else
		{
			// We're just updating the UID
			$db->update_query('tsf_pollvotes', array('uid' => 0), "uid IN({$this->delete_uids})");
			$db->update_query('tsf_posts', array('uid' => 0), "uid IN({$this->delete_uids})");
			$db->update_query('tsf_threads', array('uid' => 0), "uid IN({$this->delete_uids})");
			$db->update_query('attachments', array('uid' => 0), "uid IN({$this->delete_uids})");
			$db->update_query('tsf_announcements', array('uid' => 0), "uid IN({$this->delete_uids})");
		}

		$db->update_query('privatemessages', array('fromid' => 0), "fromid IN({$this->delete_uids})");
		

		// Update thread ratings
		//$query = $db->sql_query("
		//	SELECT r.*, t.numratings, t.totalratings
		//	FROM threadratings r
		//	LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=r.tid)
		//	WHERE r.uid IN({$this->delete_uids})
		//");
		//while($rating = $db->fetch_array($query))
		//{
		//	$update_thread = array(
		//		"numratings" => $rating['numratings'] - 1,
		//		"totalratings" => $rating['totalratings'] - $rating['rating']
		//	);
			//$db->update_query("tsf_threads", $update_thread, "tid='{$rating['tid']}'");
		//}

		//$db->delete_query('threadratings', "uid IN({$this->delete_uids})");

		// Update forums & threads if user is the lastposter
		$db->update_query('tsf_forums', array('lastposteruid' => 0), "lastposteruid IN({$this->delete_uids})");
		$db->update_query('tsf_threads', array('lastposteruid' => 0), "lastposteruid IN({$this->delete_uids})");

		// Update forum stats
		update_stats(array('numusers' => '-'.$this->deleted_users));

		$this->return_values = array(
			"deleted_users" => $this->deleted_users
		);

		$plugins->run_hooks("datahandler_user_delete_end", $this);

		// Update  cache
		//$cache->update_moderators();
		$cache->update_forumsdisplay();
		$cache->update_reportedcontent();
		$cache->update_awaitingactivation();
		$cache->update_birthdays();

		return $this->return_values;
	}

	/**
	 * Provides a method to delete users' content
	 *
	 * @param array|bool $delete_uids Array of user ids, false if they're already set (eg when using the delete_user function)
	 */
	function delete_content($delete_uids=false)
	{
		global $db, $plugins, $mybb, $CURUSER;

		if($delete_uids != false)
		{
			$this->delete_uids = array_map('intval', (array)$delete_uids);

			foreach($this->delete_uids as $key => $uid)
			{
				if(!$uid || is_super_admin($uid) || $uid == $CURUSER['id'])
				{
					// Remove super admins
					unset($this->delete_uids[$key]);
				}
			}

			$this->delete_uids = implode(',', $this->delete_uids);
		}

		$plugins->run_hooks('datahandler_user_delete_content', $this);

		if(empty($this->delete_uids))
		{
			return;
		}

		$db->delete_query('userfields', "ufid IN({$this->delete_uids})");
		$db->delete_query('privatemessages', "uid IN({$this->delete_uids})");
		
		
		$db->delete_query('snatched', "userid IN({$this->delete_uids})");
		$db->delete_query('ts_u_perm', "userid IN({$this->delete_uids})");
		//$db->delete_query('ts_user_validation', "userid IN({$this->delete_uids})");
		$db->delete_query('ts_inactivity', "userid IN({$this->delete_uids})");	
		$db->delete_query('comments', "user IN({$this->delete_uids})");
		$db->delete_query('invites', "inviter IN({$this->delete_uids})");
		$db->delete_query('bookmarks', "userid IN({$this->delete_uids})");
		$db->delete_query('peers', "userid IN({$this->delete_uids})");
		
		$db->delete_query('tsf_forumsubscriptions', "uid IN({$this->delete_uids})");
		$db->delete_query('tsf_threadsubscriptions', "uid IN({$this->delete_uids})");
		$db->delete_query('tsf_forumsread', "uid IN({$this->delete_uids})");
		$db->delete_query('tsf_threadsread', "uid IN({$this->delete_uids})");
		$db->delete_query('sessions', "uid IN({$this->delete_uids})");
		$db->delete_query('banned', "uid IN({$this->delete_uids})");
		$db->delete_query('awaitingactivation', "uid IN({$this->delete_uids})");
		$db->delete_query('buddyrequests', "uid IN({$this->delete_uids}) OR touid IN({$this->delete_uids})");
		$db->delete_query('tsf_posts', "uid IN({$this->delete_uids}) AND visible = -2");
		$db->delete_query('tsf_threads', "uid IN({$this->delete_uids}) AND visible = -2");

		// Delete reports made to the profile or reputation of the deleted users (i.e. made by them)
		$db->delete_query('reportedcontent', "type='reputation' AND id3 IN({$this->delete_uids}) OR type='reputation' AND id2 IN({$this->delete_uids})");
		$db->delete_query('reportedcontent', "type='profile' AND id IN({$this->delete_uids})");

		// Update the reports made by the deleted users by setting the uid to 0
		$db->update_query('reportedcontent', array('uid' => 0), "uid IN({$this->delete_uids})");

		// Remove any of the user(s) uploaded avatars
		require_once INC_PATH.'/functions_upload.php';
		foreach(explode(',', $this->delete_uids) as $uid)
		{
			remove_avatars($uid);
		}
	}

	/**
	 * Provides a method to delete an users posts and threads
	 *
	 * @param array|bool $delete_uids Array of user ids, false if they're already set (eg when using the delete_user function)
	 */
	function delete_posts($delete_uids=false)
	{
		global $db, $plugins, $mybb, $CURUSER;

		if($delete_uids != false)
		{
			$this->delete_uids = array_map('intval', (array)$delete_uids);

			foreach($this->delete_uids as $key => $uid)
			{
				if(!$uid || is_super_admin($uid) || $uid == $CURUSER['id'])
				{
					// Remove super admins
					unset($this->delete_uids[$key]);
				}
			}

			$this->delete_uids = implode(',', $this->delete_uids);
		}

		require_once INC_PATH.'/class_moderation.php';
		$moderation = new Moderation();

		$plugins->run_hooks('datahandler_user_delete_posts', $this);

		if(empty($this->delete_uids))
		{
			return;
		}

		// Threads
		$query = $db->simple_select('tsf_threads', 'tid', "uid IN({$this->delete_uids})");
		while($tid = $db->fetch_field($query, 'tid'))
		{
			$moderation->delete_thread($tid);
		}

		// Posts
		$query = $db->simple_select('tsf_posts', 'pid', "uid IN({$this->delete_uids})");
		while($pid = $db->fetch_field($query, 'pid'))
		{
			$moderation->delete_post($pid);
		}
	}

	/**
	 * Provides a method to clear an users profile
	 *
	 * @param array|bool $delete_uids Array of user ids, false if they're already set (eg when using the delete_user function)
	 * @param int $gid The new usergroup if the users should be moved (additional usergroups are always removed)
	 */
	function clear_profile($delete_uids=false, $gid=0)
	{
		global $db, $plugins, $mybb, $CURUSER;

		// delete_uids isn't a nice name, but it's used as the functions above use the same
		if($delete_uids != false)
		{
			$this->delete_uids = array_map('intval', (array)$delete_uids);

			foreach($this->delete_uids as $key => $uid)
			{
				if(!$uid || is_super_admin($uid) || $uid == $CURUSER['id'])
				{
					// Remove super admins
					unset($this->delete_uids[$key]);
				}
			}

			$this->delete_uids = implode(',', $this->delete_uids);
		}

		$update = array(
			"birthday" => "",
			"usertitle" => "",
			"additionalgroups" => "",
			"displaygroup" => 0,
			"signature" => "",
			"avatar" => "",
			'avatardimensions' => '',
			'avatartype' => ''
		);

		if($gid > 0)
		{
			$update["usergroup"] = (int)$gid;
		}

		$plugins->run_hooks('datahandler_user_clear_profile', $this);

		if(empty($this->delete_uids))
		{
			return;
		}

		$db->update_query("users", $update, "id IN({$this->delete_uids})");
		$db->delete_query('userfields', "ufid IN({$this->delete_uids})");

		// Remove any of the user(s) uploaded avatars
		require_once INC_PATH.'/functions_upload.php';
		foreach(explode(',', $this->delete_uids) as $uid)
		{
			remove_avatars($uid);
		}
	}

	public function verify_signature()
	{
		global $mybb, $parser;

		if(!isset($this->data['signature']))
		{
			return true;
		}

		if(!isset($parser))
		{
			require_once INC_PATH."/class_parser.php";
			$parser = new postParser;
		}

		$parser_options = array(
			'allow_html' => 1,
			'allow_mycode' => 1,
			'allow_smilies' => 1,
			'allow_imgcode' => 1,
			"filter_badwords" => 1
		);

		$parsed_sig = $parser->parse_message($this->data['signature'], $parser_options);

		
		$maxsigimages = "2";
		$sigsmilies = "1";
		$sigimgcode = "1";
		
		if((($sigimgcode == 0 && $sigsmilies != 1) &&
			substr_count($parsed_sig, "<img") > 0) ||
			(($sigimgcode == 1 || $sigsmilies == 1) &&
			substr_count($parsed_sig, "<img") > $maxsigimages)
		)
		{
			$imgsallowed = 0;

			if($sigimgcode == 1)
			{
				$imgsallowed = $maxsigimages;
			}

			$this->set_error('too_many_sig_images2', array($imgsallowed));
		}

		$sigcountmycode = "1";
		
		if($sigcountmycode == 0)
		{
			$parsed_sig = $parser->text_parse_message($this->data['signature'], array('signature_parse' => '1'));
		}
		else
		{
			$parsed_sig = $this->data['signature'];
		}

		$siglength = "255";
		
		if($siglength > 0)
		{
			$parsed_sig = preg_replace("#\s#", "", $parsed_sig);
			$sig_length = my_strlen($parsed_sig);

			if($sig_length > $siglength)
			{
				$this->set_error('sig_too_long', array($siglength));

				if($sig_length - $siglength > 1)
				{
					$this->set_error('sig_remove_chars_plural', array($sig_length-$siglength));
				}
				else
				{
					$this->set_error('sig_remove_chars_singular');
				}
			}
		}

		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		return true;
	}
}
