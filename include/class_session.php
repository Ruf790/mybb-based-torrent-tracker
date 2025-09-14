<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */
 

 

class session
{
	/**
	 * @var int
	 */
	public $sid = 0;
	/**
	 * @var int
	 */
	public $uid = 0;
	/**
	 * @var string
	 */
	public $ipaddress = '';
	/**
	 * @var string
	 */
	public $packedip = '';
	/**
	 * @var string
	 */
	public $useragent = '';
	/**
	 * @var bool
	 */
	public $is_spider = false;

	/**
	 * Request parameters that are to be ignored for location storage
	 *
	 * @var array
	 */
	public $ignore_parameters = array(
		'my_post_key',
		'logoutkey',
	);

	/**
	 * Initialize a session
	 */
	function init()
	{
		global $db, $mybb, $cache, $plugins;

		// Get our visitor's IP.
		$this->ipaddress = get_ip();
		$this->packedip = my_inet_pton($this->ipaddress);

		// Find out the user agent.
		if(isset($_SERVER['HTTP_USER_AGENT']))
		{
			$this->useragent = $_SERVER['HTTP_USER_AGENT'];
		}

		// Attempt to find a session id in the cookies.
		if(isset($mybb->cookies['sid']) && !defined('IN_UPGRADE'))
		{
			$sid = $db->escape_string($mybb->cookies['sid']);

			// Load the session if not using a bot sid
			if(substr($sid, 3, 1) !== '=')
			{
				$query = $db->simple_select("sessions", "*", "sid='{$sid}'");
				$session = $db->fetch_array($query);
				if($session)
				{
					$this->sid = $session['sid'];
				}
			}
		}

		if(isset($plugins))
		{
			$plugins->run_hooks('pre_session_load', $this);
		}

		// If we have a valid session id and user id, load that users session.
		if(!empty($mybb->cookies['mybbuser']))
		{
			$logon = explode("_", $mybb->cookies['mybbuser'], 2);
			$this->load_user($logon[0], $logon[1]);
		}

		// If no user still, then we have a guest.
		if(!isset($mybb->user['id']))
		{
			// Detect if this guest is a search engine spider. (bots don't get a cookied session ID so we first see if that's set)
			if(!$this->sid)
			{
				$spiders = $cache->read("spiders");
				if(is_array($spiders))
				{
					foreach($spiders as $spider)
					{
						if(my_strpos(my_strtolower($this->useragent), my_strtolower($spider['useragent'])) !== false)
						{
							$this->load_spider($spider['sid']);
						}
					}
				}
			}

			// Still nothing? JUST A GUEST!
			if(!$this->is_spider)
			{
				$this->load_guest();
			}
		}

		// As a token of our appreciation for getting this far (and they aren't a spider), give the user a cookie
		if($this->sid && (!isset($mybb->cookies['sid']) || $mybb->cookies['sid'] != $this->sid) && $this->is_spider != true)
		{
			my_setcookie("sid", $this->sid, -1, true);
		}

		if(isset($plugins))
		{
			$plugins->run_hooks('post_session_load', $this);
		}
		
		// ✅ ВСТАВЛЯЕМ ЗДЕСЬ АКТИВАЦИЮ КРОНА
	    $GLOBALS['ts_cron_image'] = !defined('SKIP_CRON_JOBS') ? true : false;
		
	}

	/**
	 * Load a user via the user credentials.
	 *
	 * @param int $uid The user id.
	 * @param string $loginkey The user's loginkey.
	 * @return bool
	 */
	function load_user($uid, $loginkey='')
	{
		global $mybb, $db, $time, $lang, $mybbgroups, $cache, $timeformat, $dateformat, $f_postsperpage, $f_threadsperpage, $securehash, $SITENAME, $iplog1;

		$uid = (int)$uid;
		
		 $query = $db->sql_query_prepared("SELECT u.*, f.*
          FROM users u
          LEFT JOIN userfields f ON (f.ufid=u.id)
          WHERE u.id=?
          LIMIT 1", [(int)$uid]);


		$mybb->user = $db->fetch_array($query);

		// Check the password if we're not using a session
		if(!$mybb->user || empty($loginkey) || $loginkey !== $mybb->user['loginkey'])
		{
			unset($mybb->user);
			$this->uid = 0;
			return false;
		}
		$this->uid = $mybb->user['id'];

		// Set the logout key for this user
		$mybb->user['logoutkey'] = md5($mybb->user['loginkey']);
		
		$enablepms = "1";

		// Sort out the private message count for this user.
		if(($mybb->user['totalpms'] == -1 || $mybb->user['unreadpms'] == -1) && $enablepms != 0) // Forced recount
		{
			$update = 0;
			if($mybb->user['totalpms'] == -1)
			{
				$update += 1;
			}
			if($mybb->user['unreadpms'] == -1)
			{
				$update += 2;
			}

			require_once INC_PATH."/functions_user.php";
			$pmcount = update_pm_count('', $update);
			if(is_array($pmcount))
			{
				$mybb->user = array_merge($mybb->user, $pmcount);
			}
		}
		$mybb->user['pms_total'] = $mybb->user['totalpms'];
		$mybb->user['pms_unread'] = $mybb->user['unreadpms'];

		if($mybb->user['lastip'] != $this->packedip && array_key_exists('lastip', $mybb->user) && !defined('IN_UPGRADE'))
		{
			$lastip_add = ", lastip=".$db->escape_binary($this->packedip);
		}
		else
		{
			$lastip_add = '';
		}
		
		
		//if ($iplog1 == 'yes' && $this->ipaddress != $mybb->user['regip'] && !empty($this->ipaddress)) 
		//{
        //     $escaped_ip = $db->escape_string($this->ipaddress);
         //    $query = $db->simple_select("iplog", "ip", "ip='{$escaped_ip}' AND userid='{$mybb->user['id']}'");

        //     if ($db->num_rows($query) == 0) 
		//	 {
        //           $insert_iplog = array(
        //           "ip" => $escaped_ip,
        //           "userid" => $mybb->user['id']
        //           );
        //           $db->insert_query("iplog", $insert_iplog);
        //     }
        //}
		
		
		
		
		if (900 < TIMENOW - $mybb->user['last_login'])
        {
			$last_login = ", last_login=".intval($mybb->user['lastactive']);
        }
        else
        {
            $last_login = '';
        }
		
		
		
		$passkeys = '';
        
		if (strlen($mybb->user['passkey']) != 32)
		{
            $passkey = generate_passkey($mybb->user['username'], $mybb->user['loginkey']);

            if ($passkey !== false) 
		    {
                $passkeys = ", passkey='" . $db->escape_string($passkey) . "'";
            }
        }
		
		
	
	

		// If the last visit was over 900 seconds (session time out) ago then update lastvisit.
		$time = TIMENOW;
		if($time - $mybb->user['lastactive'] > 900)
		{
			$db->sql_query("UPDATE users SET lastvisit='{$mybb->user['lastactive']}', lastactive='$time' WHERE id='{$mybb->user['id']}'");
			$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			require_once INC_PATH."/functions_user.php";
			update_pm_count('', 2);
		}
		else
		{
			$timespent = TIMENOW - $mybb->user['lastactive'];
			$db->sql_query("UPDATE users SET lastactive='$time', timeonline=timeonline+$timespent{$last_login}{$passkeys}{$lastip_add} WHERE id='{$mybb->user['id']}'");
		}
		
		
		
		
		
		
		
		

		// Sort out the language and forum preferences.
		//if($mybb->user['language'] && $lang->language_exists($mybb->user['language']))
		//{
		//	$mybb->settings['bblanguage'] = $mybb->user['language'];
		///}
		if($mybb->user['dateformat'] != 0 && $mybb->user['dateformat'] != '')
		{
			global $date_formats;
			if(!empty($date_formats[$mybb->user['dateformat']]))
			{
				$dateformat = $date_formats[$mybb->user['dateformat']];
			}
		}

		// Choose time format.
		if($mybb->user['timeformat'] != 0 && $mybb->user['timeformat'] != '')
		{
			global $time_formats;
			if(!empty($time_formats[$mybb->user['timeformat']]))
			{
				$timeformat = $time_formats[$mybb->user['timeformat']];
			}
		}

		
		// Find out the threads per page preference.
	     if($mybb->user['threadsperpages'])
	     {
		    $f_threadsperpage = $mybb->user['threadsperpages'];
	     }
	
	
	     // Find out the posts per page preference.
	     if($mybb->user['postsperpage'])
	     {
		   $f_postsperpage = $mybb->user['postsperpage'];
	     }
		
		
		
		
		

		// Does this user prefer posts in classic mode?
		//if($mybb->user['classicpostbit'])
		//{
		//	$mybb->settings['postlayout'] = 'classic';
		//}
		//else
		//{
		//	$mybb->settings['postlayout'] = 'horizontal';
		//}

		$usergroups = $cache->read('usergroups');

		if(!empty($usergroups[$mybb->user['usergroup']]) && $usergroups[$mybb->user['usergroup']]['isbannedgroup'] == 1)
		{
			$ban = $db->fetch_array(
				$db->simple_select('banned', '*', 'uid='.(int)$mybb->user['id'], array('limit' => 1))
			);

			if($ban)
			{
				$mybb->user['banned'] = 1;
				$mybb->user['bandate'] = $ban['dateline'];
				$mybb->user['banlifted'] = $ban['lifted'];
				$mybb->user['banoldgroup'] = $ban['oldgroup'];
				$mybb->user['banolddisplaygroup'] = $ban['olddisplaygroup'];
				$mybb->user['banoldadditionalgroups'] = $ban['oldadditionalgroups'];
				$mybb->user['banreason'] = $ban['reason'];
			}
			else
			{
				$mybb->user['banned'] = 0;
			}
		}

				// Check if this user is currently banned and if we have to lift it.
		if(!empty($mybb->user['bandate']) && (isset($mybb->user['banlifted']) && !empty($mybb->user['banlifted'])) && $mybb->user['banlifted'] < $time)  // hmmm...bad user... how did you get banned =/
		{
			// must have been good.. bans up :D
			$db->shutdown_query("UPDATE users SET usergroup='".(int)$mybb->user['banoldgroup']."', additionalgroups='".$db->escape_string($mybb->user['banoldadditionalgroups'])."', displaygroup='".(int)$mybb->user['banolddisplaygroup']."' WHERE id='".$mybb->user['id']."'");
			$db->shutdown_query("DELETE FROM banned WHERE uid='".$mybb->user['id']."'");
			// we better do this..otherwise they have dodgy permissions
			$mybb->user['usergroup'] = $mybb->user['banoldgroup'];
			$mybb->user['displaygroup'] = $mybb->user['banolddisplaygroup'];
			$mybb->user['additionalgroups'] = $mybb->user['banoldadditionalgroups'];

			$mybbgroups = $mybb->user['usergroup'];
			if($mybb->user['additionalgroups'])
			{
				$mybbgroups .= ','.$mybb->user['additionalgroups'];
			}
		}
		else if(!empty($mybb->user['bandate']) && (empty($mybb->user['banlifted'])  || !empty($mybb->user['banlifted']) && $mybb->user['banlifted'] > $time))
        {
            $mybbgroups = $mybb->user['usergroup'];
        }
        else
        {
			// Gather a full permission set for this user and the groups they are in.
			$mybbgroups = $mybb->user['usergroup'];
			if($mybb->user['additionalgroups'])
			{
				$mybbgroups .= ','.$mybb->user['additionalgroups'];
			}
        }

		$mybb->usergroup = usergroup_permissions($mybbgroups);
		if(!$mybb->user['displaygroup'])
		{
			$mybb->user['displaygroup'] = $mybb->user['usergroup'];
		}

		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		if(is_array($mydisplaygroup))
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}

		if(!$mybb->user['usertitle'])
		{
			$mybb->user['usertitle'] = $mybb->usergroup['usertitle'];
		}
		
		
		
		$GLOBALS['CURUSER'] = $mybb->user;
    

        $script_name = basename($_SERVER['PHP_SELF']);
        $group_data_results = $usergroups[$mybb->user['usergroup']];
        $GLOBALS['usergroups'] = $group_data_results;

        if (
             $group_data_results['isbannedgroup'] == '1'
             || $mybb->user['enabled'] == 'no'
             || ($mybb->user['ustatus'] == 'pending' && !($mybb->input['action'] == 'activate' && $script_name == 'member.php'))
        )
        {
             print_no_permission(false, true, $mybb->user['notifs']);
             exit();
        }
		
	
		

		// Update or create the session.
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			if(!empty($this->sid))
			{
				$this->update_session($this->sid, $mybb->user['id']);
			}
			else
			{
				$this->create_session($mybb->user['id']);
			}
		}
		return true;
	}
	/**
	 * Load a guest user.
	 *
	 */
	function load_guest()
	{
		global $mybb, $time, $db, $lang;

		// Set up some defaults
		$time = TIMENOW;
		$mybb->user['usergroup'] = 1;
		$mybb->user['additionalgroups'] = '';
		$mybb->user['username'] = '';
		$mybb->user['id'] = 0;
		$mybbgroups = 1;
		$mybb->user['displaygroup'] = 1;
		$mybb->user['invisible'] = 0;
		$mybb->user['moderateposts'] = 0;
		$mybb->user['showquickreply'] = 1;
		$mybb->user['signature'] = '';
		$mybb->user['sourceeditor'] = 0;
		$mybb->user['subscriptionmethod'] = 0;
		$mybb->user['suspendposting'] = 0;

		// Has this user visited before? Lastvisit need updating?
		if(isset($mybb->cookies['mybb']['lastvisit']))
		{
			if(!isset($mybb->cookies['mybb']['lastactive']))
			{
				$mybb->user['lastactive'] = $time;
				$mybb->cookies['mybb']['lastactive'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastactive'] = (int)$mybb->cookies['mybb']['lastactive'];
			}
			if($time - (int)$mybb->cookies['mybb']['lastactive'] > 900)
			{
				my_setcookie("mybb[lastvisit]", $mybb->user['lastactive']);
				$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastvisit'] = (int)$mybb->cookies['mybb']['lastactive'];
			}
		}

		// No last visit cookie, create one.
		else
		{
			my_setcookie("mybb[lastvisit]", $time);
			$mybb->user['lastvisit'] = $time;
		}

		// Update last active cookie.
		my_setcookie("mybb[lastactive]", $time);

		// Gather a full permission set for this guest
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		if(is_array($mydisplaygroup))
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}

		// Update the online data.
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			if(!empty($this->sid))
			{
				$this->update_session($this->sid);
			}
			else
			{
				$this->create_session();
			}
		}
	}

	/**
	 * Load a search engine spider.
	 *
	 * @param int $spider_id The ID of the search engine spider
	 */
	function load_spider($spider_id)
	{
		global $mybb, $time, $db, $lang;

		// Fetch the spider preferences from the database
		$query = $db->simple_select("spiders", "*", "sid='{$spider_id}'");
		$spider = $db->fetch_array($query);

		// Set up some defaults
		$time = TIMENOW;
		$this->is_spider = true;
		if($spider['usergroup'])
		{
			$mybb->user['usergroup'] = $spider['usergroup'];
		}
		else
		{
			$mybb->user['usergroup'] = 1;
		}
		$mybb->user['username'] = '';
		$mybb->user['id'] = 0;
		$mybb->user['displaygroup'] = $mybb->user['usergroup'];
		$mybb->user['additionalgroups'] = '';
		$mybb->user['invisible'] = 0;

		// Set spider language
		//if($spider['language'] && $lang->language_exists($spider['language']))
		//{
		//	$mybb->settings['bblanguage'] = $spider['language'];
		//}

		// Set spider theme
		//if($spider['theme'])
		//{
		//	$mybb->user['style'] = $spider['theme'];
		//}

		// Gather a full permission set for this spider.
		$mybb->usergroup = usergroup_permissions($mybb->user['usergroup']);
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		if(is_array($mydisplaygroup))
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}

		// Update spider last minute (only do so on two minute intervals - decrease load for quick spiders)
		if($spider['lastvisit'] < TIMENOW-120)
		{
			$updated_spider = array(
				"lastvisit" => TIMENOW
			);
			$db->update_query("spiders", $updated_spider, "sid='{$spider_id}'");
		}

		// Update the online data.
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			$this->sid = "bot=".$spider_id;
			$this->create_session();
		}

	}

	/**
	 * Update a user session.
	 *
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}

		//if(!$mybb->user['usertitle'])
		///{
		//	$mybb->user['usertitle'] = $mybb->usergroup['usertitle'];
		//}

		// Update or create the session.
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			if(!empty($this->sid))
			{
	 * @param int $sid The session id.
	 * @param int $uid The user id.
	 */
	function update_session($sid, $uid=0)
	{
		global $db;

		// Find out what the special locations are.
		$speciallocs = $this->get_special_locations();
		if($uid)
		{
			$onlinedata['uid'] = $uid;
		}
		else
		{
			$onlinedata['uid'] = 0;
		}
		$onlinedata['time'] = TIMENOW;

		$onlinedata['location'] = $db->escape_string(substr(get_current_location(false, $this->ignore_parameters), 0, 150));
		$onlinedata['useragent'] = $db->escape_string(my_substr($this->useragent, 0, 200));

		$onlinedata['location1'] = (int)$speciallocs['1'];
		$onlinedata['location2'] = (int)$speciallocs['2'];
		$onlinedata['nopermission'] = 0;
		$sid = $db->escape_string($sid);

		$db->update_query("sessions", $onlinedata, "sid='{$sid}'");
	}

	/**
	 * Create a new session.
	 *
	 * @param int $uid The user id to bind the session to.
	 */
	function create_session($uid=0)
	{
		global $db;
		$speciallocs = $this->get_special_locations();

		// If there is a proper uid, delete by uid.
		if($uid > 0)
		{
			$db->delete_query("sessions", "uid='{$uid}'");
			$onlinedata['uid'] = $uid;
		}
		else
		{
			// Is a spider - delete all other spider references
			if($this->is_spider == true)
			{
				$db->delete_query("sessions", "sid='{$this->sid}'");
			}

			$onlinedata['uid'] = 0;
		}

		// If the user is a search enginge spider, ...
		if($this->is_spider == true)
		{
			$onlinedata['sid'] = $this->sid;
		}
		else
		{
			$onlinedata['sid'] = md5(random_str(50));
		}
		$onlinedata['time'] = TIMENOW;
		$onlinedata['ip'] = $db->escape_binary($this->packedip);
		
		
		$onlinedata['location'] = $db->escape_string(substr(get_current_location(false, $this->ignore_parameters), 0, 150));
		$onlinedata['useragent'] = $db->escape_string(my_substr($this->useragent, 0, 200));

		$onlinedata['location1'] = (int)$speciallocs['1'];
		$onlinedata['location2'] = (int)$speciallocs['2'];
		$onlinedata['nopermission'] = 0;
		$db->replace_query("sessions", $onlinedata, "sid", false);
		$this->sid = $onlinedata['sid'];
		$this->uid = $onlinedata['uid'];
	}

	/**
	 * Find out the special locations.
	 *
	 * @return array Special locations array.
	 */
	function get_special_locations()
	{
		global $mybb, $db;
		$array = array('1' => '', '2' => '');
		if(preg_match("#forumdisplay.php#", $_SERVER['PHP_SELF']) && $mybb->get_input('fid', MyBB::INPUT_INT) > 0 && $mybb->get_input('fid', MyBB::INPUT_INT) < 4294967296)
		{
			$array[1] = $mybb->get_input('fid', MyBB::INPUT_INT);
		}
		elseif(preg_match("#showthread.php#", $_SERVER['PHP_SELF']))
		{
			if($mybb->get_input('tid', MyBB::INPUT_INT) > 0 && $mybb->get_input('tid', MyBB::INPUT_INT) < 4294967296)
			{
				$array[2] = $mybb->get_input('tid', MyBB::INPUT_INT);
			}

			// If there is no tid but a pid, trick the system into thinking there was a tid anyway.
			elseif(isset($mybb->input['pid']) && !empty($mybb->input['pid']))
			{
				$options = array(
					"limit" => 1
				);
				$query = $db->simple_select("tsf_posts", "tid", "pid=".$mybb->get_input('pid', MyBB::INPUT_INT), $options);
				$post = $db->fetch_array($query);
				if($post)
				{
					$array[2] = $post['tid'];
				}
			}

			$thread = get_thread3333($array[2]);
			if($thread)
			{
				$array[1] = $thread['fid'];
			}
		}
		return $array;
	}
}
