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

/*
EXAMPLE USE:

$post = get from POST data
$thread = get from DB using POST data id

$postHandler = new postDataHandler();
if($postHandler->validate_post($post))
{
	$postHandler->insert_post($post);
}

*/



/**
 * Post handling class, provides common structure to handle post data.
 *
 */
class PostDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	public $language_file = 'datahandler_post';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	public $language_prefix = 'postdata';

	/**
	 * What are we performing?
	 * post = New post
	 * thread = New thread
	 * edit = Editing a thread or post
	 *
	 * @var string
	 */
	public $action;

	/**
	 * Array of data inserted in to a post.
	 *
	 * @var array
	 */
	public $post_insert_data = array();

	/**
	 * Array of data used to update a post.
	 *
	 * @var array
	 */
	public $post_update_data = array();

	/**
	 * Post ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	public $pid = 0;

	/**
	 * Array of data inserted in to a thread.
	 *
	 * @var array
	 */
	public $thread_insert_data = array();

	/**
	 * Array of data used to update a thread.
	 *
	 * @var array
	 */
	public $thread_update_data = array();

	/**
	 * Thread ID currently being manipulated by the datahandlers.
	 *
	 * @var int
	 */
	public $tid = 0;

	/**
	 * Values to be returned after inserting/updating a post/thread.
	 *
	 * @var array
	 */
	public $return_values = array();

	/**
	 * Is this the first post of a thread when editing
	 *
	 * @var boolean
	 */
	public $first_post = false;

	/**
	 * Verifies the author of a post and fetches the username if necessary.
	 *
	 * @return boolean True if the author information is valid, false if invalid.
	 */
	function verify_author()
	{
		global $mybb, $lang;

		$post = &$this->data;

		// Don't have a user ID at all - not good (note, a user id of 0 will still work).
		if(!isset($post['uid']))
		{
			$this->set_error("The user id does not exist. Please supply a valid user id");
			return false;
		}
		// If we have a user id but no username then fetch the username.
		else if($post['uid'] > 0 && empty($post['username']))
		{
			$user = get_user($post['uid']);
			$post['username'] = $user['username'];
		}
		// if the uid is 0 verify the username
		else if($post['uid'] == 0 && $post['username'] != '')
		{
			// Set up user handler
			require_once INC_PATH."/datahandlers/user.php";
			$userhandler = new UserDataHandler();

			$data_array = array('username' => $post['username']);
			$userhandler->set_data($data_array);

			if(!$userhandler->verify_username())
			{
				// invalid username
				$this->errors = array_merge($this->errors, $userhandler->get_errors());
				return false;
			}

			if($userhandler->verify_username_exists())
			{
				// username is in use
				$this->errors = array_merge($this->errors, $userhandler->get_errors());
				return false;
			}
		}

		return true;
	}

	/**
	 * Verifies a post subject.
	 *
	 * @return boolean True when valid, false when not valid.
	 */
	function verify_subject()
	{
		global $db;
		$post = &$this->data;
		$subject = &$post['subject'];
		$subject = trim_blank_chrs($subject);

		if($this->method == "update" && $post['pid'])
		{
			// If this is the first post there needs to be a subject, else make it the default one.
			if(my_strlen($subject) == 0 && $this->first_post)
			{
				$this->set_error("The thread does not have a subject. Please enter a subject");
				return false;
			}
			elseif(my_strlen($subject) == 0)
			{
				$thread = get_thread($post['tid']);
				$subject = "RE: ".$thread['subject'];
			}
		}

		// This is a new post
		else if($this->action == "post")
		{
			if(my_strlen($subject) == 0)
			{
				$thread = get_thread($post['tid']);
				$subject = "RE: ".$thread['subject'];
			}
		}

		// This is a new thread and we require that a subject is present.
		else
		{
			if(my_strlen($subject) == 0)
			{
				$this->set_error("missing_subject");
				return false;
			}
		}

		// If post is reply and begins with "RE: ", remove 4 from subject length.
		$subject_length = my_strlen($subject);
		if($this->action == "post")
		{
			$position_re = my_strpos($subject, "RE: ");
			if($position_re !== false && $position_re == 0)
			{
				$subject_length = $subject_length - 4;
			}
		}

		if($subject_length > 85)
		{
			// Subject is too long
			$this->set_error('The subject is too long. Please enter a subject shorter than 85 characters (currently '.my_strlen($subject).')');
			return false;
		}

		// Subject is valid - return true.
		return true;
	}

	/**
	 * Verifies a post message.
	 *
	 * @return bool
	 */
	function verify_message()
	{
		global $db, $mybb, $parser, $usergroups;

		$post = &$this->data;
		$post['message'] = trim_blank_chrs($post['message']);

		// Do we even have a message at all?
		if(my_strlen($post['message']) == 0)
		{
			$this->set_error("The message is missing. Please enter a message");
			return false;
		}
		else
		{
			$maxmessagelength = "65535";
			$mycodemessagelength = "1";
			$minmessagelength = "5";
			
			$limit = (int)$maxmessagelength;
			$dblimit = 0;

			// If database is mysql or mysqli check field type and set max database limit
			if(stripos($db->type, 'my') !== false)
			{
				$fields = $db->show_fields_from("tsf_posts");
				$type = $fields[array_search('message', array_column($fields, 'Field'))]['Type'];
				switch(strtolower($type))
				{
					case 'longtext':
						$dblimit = 4294967295;
						break;
					case 'mediumtext':
						$dblimit = 16777215;
						break;
					case 'text':
					default:
						$dblimit = 65535;
						break;
				}
			}

			if($limit > 0 || $dblimit > 0)
			{
				if(isset($post['fid']))
				{
					$fid = $post['fid'];
				}
				else
				{
					$fid = 0;
				}
				if(isset($post['uid']))
				{
					$uid = $post['uid'];
				}
				else
				{
					$uid = 0;
				}

				//$is_moderator = is_moderator($fid, "", $uid);
				
				$is_moderator = is_mod($usergroups);
				// Consider minimum in user defined and database limit other than 0
				if($limit > 0 && $dblimit > 0)
				{
					$limit = $is_moderator ? $dblimit : min($limit, $dblimit);
				}
				else
				{
					$limit = max($limit, $dblimit);
				}

				if(strlen($post['message']) > $limit && (!$is_moderator || $limit == $dblimit))
				{
					$this->set_error("The message is too long. Please enter a message shorter than ".$limit." characters (currently ".strlen($post['message']).")");
					return false;
				}
			}

			if(!isset($post['fid']))
			{
				$post['fid'] = 0;
			}

			
			
			if(!$mycodemessagelength)
			{
				// Check to see of the text is full of MyCode
				//require_once MYBB_ROOT."inc/class_parser.php";
				//$parser = new postParser;

				$message = $parser->text_parse_message($post['message']);

				
				
				if(my_strlen($message) < $minmessagelength && $minmessagelength > 0 && !is_moderator($post['fid'], "", $post['uid']))
				{
					$this->set_error("The message is too short. Please enter a message longer than ".$minmessagelength." characters");
					return false;
				}
			}
			//else if(my_strlen($post['message']) < $minmessagelength && $minmessagelength > 0 && !is_moderator($post['fid'], "", $post['uid']))
			else if(my_strlen($post['message']) < $minmessagelength && $minmessagelength > 0)
			{
				$this->set_error("The message is too short. Please enter a message longer than ".$minmessagelength." characters");
				return false;
			}
		}
		return true;
	}


	/**
	* Verify that the user is not flooding the system.
	*
	* @return boolean
	*/
	function verify_post_flooding()
	{
		global $mybb, $usergroups;

		$post = &$this->data;

		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		
		$postfloodcheck = "1";
		$postfloodsecs = "60";
		
		if($postfloodcheck == 1 && $post['uid'] != 0 && $this->admin_override == false)
		{
			if($this->verify_post_merge(true) !== true)
			{
				return true;
			}

			// Fetch the user information for this post - used to check their last post date.
			$user = get_user($post['uid']);

			// A little bit of calculation magic and moderator status checking.
			//if(TIMENOW-$user['lastpost'] <= $postfloodsecs && !is_moderator($post['fid'], "", $user['uid']))
			$is_mod = is_mod($usergroups);
		
		    if(TIMENOW-$user['lastpost'] <= $postfloodsecs && !$is_mod)
			{
				// Oops, user has been flooding - throw back error message.
				$time_to_wait = ($postfloodsecs - (TIMENOW-$user['lastpost'])) + 1;
				if($time_to_wait == 1)
				{
					$this->set_error("You are trying to post a message too quickly after posting a previous message. Please wait 1 more second");
				}
				else
				{
					$this->set_error("You are trying to post a message too quickly after posting a previous message. Please wait ".$time_to_wait." more seconds");
				}
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}

	/**
	 * @param bool $simple_mode
	 *
	 * @return array|bool
	 */
	function verify_post_merge($simple_mode=false)
	{
		global $mybb, $db, $session;

		$post = &$this->data;

		// Are we starting a new thread?
		if(empty($post['tid']))
		{
			return true;
		}

		// Are we even turned on?
		
		$postmergemins = "60";
		$postmergesep = "[hr]";
		//$postmergeuignore = "7";
		$postmergefignore = "";
		
		$postmergeuignore = "5,6,7";
		
		
		if(empty($postmergemins))
		{
			return true;
		}

		// Assign a default separator if none is specified
		if(trim($postmergesep) == "")
		{
			$postmergesep = "[hr]";
		}

		// Check to see if this person is in a usergroup that is excluded
		if(is_member($postmergeuignore, $post['uid']))
		{
			return true;
		}

		// Select the lastpost and fid information for this thread
		$query = $db->simple_select("tsf_threads", "lastpost,fid", "lastposteruid='".$post['uid']."' AND tid='".$post['tid']."'", array('limit' => '1'));
		$thread = $db->fetch_array($query);

		// Check to see if the same author has posted within the merge post time limit
		if(
			!$thread || (
				((int)$postmergemins != 0 && trim($postmergemins) != "") &&
				(TIMENOW-$thread['lastpost']) > ((int)$postmergemins*60)
			)
		)
		{
			return true;
		}

		if($postmergefignore == -1)
		{
			return true;
		}
		elseif($postmergefignore != '')
		{
			$fids = explode(',', (string)$postmergefignore);

			if(is_array($fids))
			{
				foreach($fids as &$fid)
				{
					$fid = (int)$fid;
				}
				unset($fid);

				if(in_array($thread['fid'], $fids))
				{
					return true;
				}
			}
		}

		if($simple_mode == true)
		{
			return false;
		}

		if(!empty($post['uid']))
		{
			$user_check = "uid='".$post['uid']."'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("tsf_posts", "pid,message,visible", "{$user_check} AND tid='".$post['tid']."' AND dateline='".$thread['lastpost']."'", array('order_by' => 'pid', 'order_dir' => 'DESC', 'limit' => 1));
		return $db->fetch_array($query);
	}


	/**
	* Verify the reply-to post.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_reply_to()
	{
		global $db;
		$post = &$this->data;

		// Check if the post being replied to actually exists in this thread.
		if($post['replyto'])
		{
			$query = $db->simple_select("tsf_posts", "pid", "pid='".(int)$post['replyto']."'");
			$valid_post = $db->fetch_array($query);
			if(!$valid_post['pid'])
			{
				$post['replyto'] = 0;
			}
			else
			{
				return true;
			}
		}

		// If this post isn't a reply to a specific post, attach it to the first post.
		if(!$post['replyto'])
		{
			$options = array(
				"limit_start" => 0,
				"limit" => 1,
				"order_by" => "dateline, pid",
			);
			$query = $db->simple_select("tsf_posts", "pid", "tid='{$post['tid']}'", $options);
			$reply_to = $db->fetch_array($query);
			$post['replyto'] = $reply_to['pid'];
		}

		return true;
	}

	/**
	* Verify the post icon.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_post_icon()
	{
		global $cache;

		$post = &$this->data;

		$posticons_cache = $cache->read("posticons");

		// If we don't have a post icon assign it as 0.
		if(empty($post['icon']) || !isset($posticons_cache[$post['icon']]))
		{
			$post['icon'] = 0;
		}
		return true;
	}

	/**
	* Verify the dateline.
	*
	* @return boolean True when valid, false when not valid.
	*/
	function verify_dateline()
	{
		$dateline = &$this->data['dateline'];

		// The date has to be numeric and > 0.
		if($dateline < 0 || is_numeric($dateline) == false)
		{
			$dateline = TIMENOW;
		}
	}

	/**
	 * Verify thread prefix.
	 *
	 * @return boolean True when valid, false when not valid.
	 */
	function verify_prefix()
	{
		$prefix = &$this->data['prefix'];

		// If a valid prefix isn't supplied, don't assign one.
		if(empty($prefix))
		{
			$prefix = 0;
		}
		else
		{
			if(!empty($this->data['tid']))
			{
				// Fetch the thread
				$thread = get_thread($this->data['tid']);
			}

			$prefix_cache = build_prefixes($prefix);

			if(empty($prefix_cache))
			{
				$this->set_error('invalid_prefix');
				return false;
			}
			if($prefix_cache['groups'] != "-1")
			{
				if(!empty($this->data['edit_uid']))
				{
					// Post is being edited
					$user = get_user($this->data['edit_uid']);
				}
				else
				{
					$user = get_user($this->data['uid']);
				}

				if(!is_member($prefix_cache['groups'], array('usergroup' => $user['usergroup'], 'additionalgroups' => $user['additionalgroups'])) && (empty($this->data['tid']) || $prefix != $thread['prefix']))
				{
					$this->set_error('invalid_prefix');
					return false;
				}
			}
			if($prefix_cache['forums'] != "-1")
			{
				// Decide whether this prefix can be used in our forum
				$forums = explode(",", $prefix_cache['forums']);

				if(!in_array($this->data['fid'], $forums) && (empty($this->data['tid']) || $prefix != $thread['prefix']))
				{
					$this->set_error('invalid_prefix');
					return false;
				}
			}
		}

		// Does this forum require a prefix?
		$forum = get_forum($this->data['fid']);

		if($forum['requireprefix'] == 1)
		{
			$num_prefixes = false;

			// Go through each of our prefixes and decide if there are any possible prefixes to use.
			if(!empty($this->data['edit_uid']))
			{
				// Post is being edited
				$user = get_user($this->data['edit_uid']);
			}
			else
			{
				$user = get_user($this->data['uid']);
			}

			$prefix_cache = build_prefixes();

			if(!empty($prefix_cache))
			{
				foreach($prefix_cache as $required)
				{
					if($required['forums'] != "-1")
					{
						// Decide whether this prefix can be used in our forum
						$forums = explode(",", $required['forums']);

						if(!in_array($forum['fid'], $forums))
						{
							continue;
						}
					}

					if(is_member($required['groups'], array('usergroup' => $user['usergroup'], 'additionalgroups' => $user['additionalgroups'])))
					{
						$num_prefixes = true;
					}
				}
			}

			if($prefix == 0 && $num_prefixes)
			{
				$this->set_error('require_prefix');
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a post.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_post()
	{
		global $mybb, $db, $plugins;

		$post = &$this->data;
		$time = TIMENOW;

		$this->action = "post";

		if($this->method != "update" && !$post['savedraft'])
		{
			$this->verify_post_flooding();
		}

		// Are we editing an existing thread or post?
		if($this->method == "update")
		{
			if(empty($post['tid']))
			{
				$query = $db->simple_select("tsf_posts", "tid", "pid='".(int)$post['pid']."'");
				$post['tid'] = $db->fetch_field($query, "tid");
			}
			// Here we determine if we're editing the first post of a thread or not.
			$options = array(
				"limit" => 1,
				"limit_start" => 0,
				"order_by" => "dateline, pid",
			);
			$query = $db->simple_select("tsf_posts", "pid", "tid='".$post['tid']."'", $options);
			$first_check = $db->fetch_array($query);
			if($first_check['pid'] == $post['pid'])
			{
				$this->first_post = true;
			}
		}

		// Verify all post assets.

		if($this->method == "insert" || array_key_exists('uid', $post))
		{
			$this->verify_author();
		}

		if($this->method == "insert" || array_key_exists('subject', $post))
		{
			$this->verify_subject();
		}

		if($this->method == "insert" || array_key_exists('message', $post))
		{
			$this->verify_message();
		}

		if($this->method == "insert" || array_key_exists('dateline', $post))
		{
			$this->verify_dateline();
		}

		if($this->method == "insert" || array_key_exists('replyto', $post))
		{
			$this->verify_reply_to();
		}

		if($this->method == "insert" || array_key_exists('icon', $post))
		{
			$this->verify_post_icon();
		}


		if($this->method == "update" && $this->first_post)
		{
			$this->verify_prefix();
		}

		$plugins->run_hooks("datahandler_post_validate_post", $this);

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
	 * Insert a post into the database.
	 *
	 * @return array Array of new post details, pid and visibility.
	 */
	function insert_post()
	{
		global $db, $mybb, $plugins, $cache, $lang, $parser, $usergroups, $BASEURL, $CURUSER;

		$post = &$this->data;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		// Fetch the thread
		$thread = get_thread($post['tid']);

		$closed = $thread['closed'];

		// This post is being saved as a draft.
		if($post['savedraft'])
		{
			$visible = -2;
		}

		// Otherwise this post is being made now and we have a bit to do.
		else
		{
			// Automatic subscription to the thread
			if($post['uid'] > 0)
			{
				require_once INC_PATH . "/functions_user.php";
				if($post['options']['subscriptionmethod'] == "")
				{
					//remove_subscribed_thread($post['tid'], $post['uid']);
				}
				else
				{
					switch($post['options']['subscriptionmethod'])
					{
						case "pm":
							$notification = 2;
							break;
						case "email":
							$notification = 1;
							break;
						default:
							$notification = 0;
					}
					add_subscribed_thread($post['tid'], $notification, $post['uid']);
				}
			}

			// Perform any selected moderation tools.
			//$ismod = is_moderator($post['fid'], "", $post['uid']);
			//if($ismod && isset($post['modoptions']))
			$is_mod = is_mod($usergroups);
		
		    if($is_mod && isset($post['modoptions']))
			{
				//$lang->load($this->language_file, true);
				

				$modoptions = $post['modoptions'];
				$modlogdata['fid'] = $thread['fid'];
				$modlogdata['tid'] = $thread['tid'];

				$modoptions_update = array();

				// Close the thread.
				//if(!empty($modoptions['closethread']) && $thread['closed'] != 1 && is_moderator($post['fid'], "canopenclosethreads", $post['uid']))
				if(!empty($modoptions['closethread']) && $thread['closed'] != 1)
				{
					$modoptions_update['closed'] = $closed = 1;
					log_moderator_action($modlogdata, 'Thread Closed');
				}

				// Open the thread.
				//if(empty($modoptions['closethread']) && $thread['closed'] == 1 && is_moderator($post['fid'], "canopenclosethreads", $post['uid']))
				if(empty($modoptions['closethread']) && $thread['closed'] == 1)
				{
					$modoptions_update['closed'] = $closed = 0;
					log_moderator_action($modlogdata, 'Thread Opened');
				}

				// Stick the thread.
				if(!empty($modoptions['stickthread']) && $thread['sticky'] != 1)
				{
					$modoptions_update['sticky'] = 1;
					log_moderator_action($modlogdata, 'Thread Stuck');
				}

				// Unstick the thread.
				if(empty($modoptions['stickthread']) && $thread['sticky'] == 1)
				{
					$modoptions_update['sticky'] = 0;
					log_moderator_action($modlogdata, 'Thread Unstuck');
				}

				// Execute moderation options.
				if($modoptions_update)
				{
					$db->update_query('tsf_threads', $modoptions_update, "tid='{$thread['tid']}'");
				}
			}

			// Fetch the forum this post is being made in
			$forum = get_forum($post['fid']);

			// Decide on the visibility of this post.
			//$forumpermissions = forum_permissions($post['fid'], $post['uid']);
			if($forumpermissions['modposts'] == 1 && !$ismod)
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}

			// Are posts from this user being moderated? Change visibility
			if($CURUSER['id'] == $post['uid'] && $CURUSER['moderateposts'] == 1)
			{
				$visible = 0;
			}
		}

		if(!isset($post['pid']))
		{
			$post['pid'] = 0;
		}

		$post['pid'] = (int)$post['pid'];
		$post['uid'] = (int)$post['uid'];

		if($post['pid'] > 0)
		{
			$query = $db->simple_select("tsf_posts", "tid", "pid='{$post['pid']}' AND uid='{$post['uid']}' AND visible='-2'");
			$draft_check = $db->fetch_field($query, "tid");
		}
		else
		{
			$draft_check = false;
		}

		if($this->method != "update" && $visible == 1)
		{
			$double_post = $this->verify_post_merge();

			// Only combine if they are both invisible (mod queue'd forum) or both visible
			if($double_post !== true && $double_post['visible'] == $visible)
			{
				$_message = $post['message'];

				$postmergesep = "[hr]";
				
				$post['message'] = $double_post['message'] .= "\n".$postmergesep."\n".$post['message'];
				
				if ($this->validate_post())
				{
					$this->pid = $double_post['pid'];
					
					$update_query = array(
						"message" => $db->escape_string($double_post['message'])
					);
					$update_query['edituid'] = (int)$post['uid'];
					$update_query['edittime'] = TIMENOW;
					$db->update_query("tsf_posts", $update_query, "pid='".$double_post['pid']."'");
					
					if($draft_check)
					{
						$db->delete_query("tsf_posts", "pid='".$post['pid']."'");
					}
					
					if($post['posthash'])
					{
						// Assign any uploaded attachments with the specific posthash to the merged post.
						$post['posthash'] = $db->escape_string($post['posthash']);
						
						$query = $db->simple_select("attachments", "COUNT(aid) AS attachmentcount", "pid='0' AND visible='1' AND posthash='{$post['posthash']}'");
						$attachmentcount = $db->fetch_field($query, "attachmentcount");
						
						if($attachmentcount > 0)
						{
							// Update forum count
							update_thread_counters($post['tid'], array('attachmentcount' => "+{$attachmentcount}"));
						}
						
						$attachmentassign = array(
							"pid" => $double_post['pid'],
							"posthash" => ''
						);
						$db->update_query("attachments", $attachmentassign, "posthash='{$post['posthash']}' AND pid='0'");
					}
					
					// Return the post's pid and whether or not it is visible.
					$this->return_values = array(
						"pid" => $double_post['pid'],
						"visible" => $visible,
						"merge" => true
					);
					
					$plugins->run_hooks("datahandler_post_insert_merge", $this);
					
					return $this->return_values;
				}
				else
				{
					$post['message'] = $_message;
				}
			}
		}

		if($visible == 1)
		{
			$now = TIMENOW;

			// Yes, the value to the lastpost key in this array has single quotes within double quotes. It's not a bug.
			$update_array = array(
				'lastpost' => "'{$now}'"
			);
			if($thread['visible'] == 1)
			{
				$update_array['postnum'] = 'postnum+1';
			}

			$db->update_query("users", $update_array, "id='{$post['uid']}'", 1, true);
		}

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($draft_check)
		{
			// Update a post that is a draft
			$this->post_update_data = array(
				"subject" => $db->escape_string($post['subject']),
				"icon" => (int)$post['icon'],
				"uid" => $post['uid'],
				"username" => $db->escape_string($post['username']),
				"dateline" => (int)$post['dateline'],
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($CURUSER['ip']),
				"visible" => $visible
			);

			$plugins->run_hooks("datahandler_post_insert_post", $this);

			$db->update_query("tsf_posts", $this->post_update_data, "pid='{$post['pid']}'");
			$this->pid = $post['pid'];
		}
		else
		{
			// Insert the post.
			$this->post_insert_data = array(
				"tid" => (int)$post['tid'],
				"replyto" => (int)$post['replyto'],
				"fid" => (int)$post['fid'],
				"subject" => $db->escape_string($post['subject']),
				"uid" => $post['uid'],
				"username" => $db->escape_string($post['username']),
				"dateline" => $post['dateline'],
				"message" => $db->escape_string($post['message']),
				"ipaddress" => $db->escape_string($CURUSER['ip']),
				"visible" => $visible
			);

			$plugins->run_hooks("datahandler_post_insert_post", $this);

			$this->pid = $db->insert_query("tsf_posts", $this->post_insert_data);
			
			
			
			// Привязываем загруженные файлы к этому комментарию
                if (!empty($_POST['file_ids'])) 
				{
                   $file_ids = array_map('intval', $_POST['file_ids']); // защита
                   $id_list  = implode(',', $file_ids);

                   if (!empty($id_list)) 
				   {
                     $db->sql_query("
                         UPDATE comment_files
                         SET post_id = " . (int)$this->pid . "
                         WHERE id IN ($id_list)
                        ");
                   }
                }
			
			
			
			
			
			
			
			
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($post['posthash'])
		{
			$post['posthash'] = $db->escape_string($post['posthash']);
			$attachmentassign = array(
				"pid" => $this->pid,
				"posthash" => ''
			);
			$db->update_query("attachments", $attachmentassign, "posthash='{$post['posthash']}' AND pid='0'");
		}

		$thread_update = array();
		if($visible == 1 && $thread['visible'] == 1)
		{
			require_once INC_PATH.'/class_parser.php';
			$parser = new Postparser;

			$done_users = array();

			$subject = $parser->parse_badwords($thread['subject']);

			$parser_options = array(
				'me_username'		=> $post['username'],
				'filter_badwords'	=> 1
			);

			$excerpt = $parser->text_parse_message($post['message'], $parser_options);
			//$excerpt = my_substr($excerpt, 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;

			// Fetch any users subscribed to this thread receiving instant notification and queue up their subscription notices
			//$query = $db->sql_query("
			//	SELECT u.username, u.email, u.id, u.loginkey, u.salt, u.added, s.notification
			//	FROM tsf_threadsubscriptions s
			//	LEFT JOIN users u ON (u.id=s.uid)
			//	WHERE (s.notification='1' OR s.notification='2') AND s.tid='{$post['tid']}'
			//	AND s.uid != '{$post['uid']}'
			//	AND u.lastactive>'{$thread['lastpost']}'
			//");


            $query = $db->sql_query_prepared(
               "SELECT u.username, u.email, u.id, u.loginkey, u.salt, u.added, s.notification
                FROM tsf_threadsubscriptions s
                LEFT JOIN users u ON (u.id = s.uid)
                WHERE (s.notification = '1' OR s.notification = '2') 
                AND s.tid = ?
                AND s.uid != ?
                AND u.lastactive > ?", [(int)$post['tid'], (int)$post['uid'], $thread['lastpost']]);






			$args = array(
				'this' => &$this,
				'done_users' => &$done_users,
				'users' => array()
			);

			while($subscribedmember = $db->fetch_array($query->result))
			{
				if(isset($done_users[$subscribedmember['uid']]))
				{
					continue;
				}

				$args['users'][$subscribedmember['id']] = (int)$subscribedmember['uid'];

				$done_users[$subscribedmember['uid']] = 1;

				//$forumpermissions = forum_permissions($thread['fid'], $subscribedmember['uid']);
				//if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
				//{
				    //continue;
				//}

				if($thread['id'] != $subscribedmember['uid'] && $subscribedmember['id'])
				{
					// User isn't a moderator or the author of the thread...
					continue;
				}

				if($subscribedmember['language'] != '' && $lang->language_exists($subscribedmember['language']))
				{
					$uselang = $subscribedmember['language'];
				}
				elseif($mybb->settings['orig_bblanguage'])
				{
					$uselang = $mybb->settings['orig_bblanguage'];
				}
				else
				{
					$uselang = "english";
				}

				if($uselang == $mybb->settings['bblanguage'])
				{
					if($subscribedmember['notification'] == 1)
					{
						$emailsubject = 'New Reply to {1}';
						$emailmessage = 'email_subscription';
					}

					// If the poster is unregistered and hasn't set a username, call them Guest
					if(!$post['uid'] && !$post['username'])
					{
						$post['username'] = htmlspecialchars_uni('guest');
					}
				}
				else
				{

					if(($subscribedmember['notification'] == 1 && !isset($langcache[$uselang]['emailsubject_subscription'])) || !isset($langcache[$uselang]['guest']))
					{
						//$userlang = new MyLanguage;
						//$userlang->set_path(MYBB_ROOT."inc/languages");
						//$userlang->set_language($uselang);
						
						
						
						if($subscribedmember['notification'] == 1)
						{
							$lang->load("member");
						
							$emailsubject_subscription = $lang->member['emailsubject_subscription'];
							$email_subscription = $lang->member['email_subscription'];
						}
						

						$langcache[$uselang]['guest'] = 'guest';
						unset($userlang);
					}
					if($subscribedmember['notification'] == 1)
					{
						$emailsubject = $emailsubject_subscription;
						$emailmessage = $email_subscription;
					}

					// If the poster is unregistered and hasn't set a username, call them Guest
					if(!$post['uid'] && !$post['username'])
					{
						$post['username'] = $langcache[$uselang]['guest'];
					}
				}

				if($subscribedmember['notification'] == 1)
				{
					$emailsubject = sprintf($emailsubject, $subject);

					$emailmessage = sprintf($emailmessage, $subscribedmember['username'], $post['username'], $SITENAME, $subject, $excerpt, $BASEURL, str_replace("&amp;", "&", get_thread_link($thread['tid'], 0, "newpost")), $thread['tid']);
					$new_email = array(
						"mailto" => $db->escape_string($subscribedmember['email']),
						"mailfrom" => '',
						"subject" => $db->escape_string($emailsubject),
						"message" => $db->escape_string($emailmessage),
						"headers" => ''
					);
					$db->insert_query("mailqueue", $new_email);
					unset($userlang);
					$queued_email = 1;
				}
				elseif($subscribedmember['notification'] == 2)
				{
					
					
					require_once INC_PATH . '/functions_pm.php';
					
					$excerpt = $parser->text_parse_message($post['message'], $parser_options);
					
					$pm = array(
						'subject' => sprintf ($lang->tsf_forums['pmsubject_subscription'], $subject),
						'message' => sprintf ($lang->tsf_forums['pm_subscription'], $subscribedmember['username'], $post['username'], $subject, $excerpt, $BASEURL, str_replace("&amp;", "&", get_thread_link($thread['tid'], 0, "newpost")), $thread['tid']),
						'touid' => $subscribedmember['id']
					);
					
					
					/// Workaround for eliminating PHP warnings in PHP 8. Ref: https://github.com/mybb/mybb/issues/4630#issuecomment-1369144163
					$pm['sender']['uid'] = -1;
					send_pm($pm, -1, true);
					
					
				}
			}

			$plugins->run_hooks('datahandler_post_insert_subscribed', $args);

			// Have one or more emails been queued? Update the queue count
			if(isset($queued_email) && $queued_email == 1)
			{
				$cache->update_mailqueue();
			}

			$thread_update = array('replies' => '+1');

			// Update counters
			update_last_post($post['tid']);
			update_forum_counters($post['fid'], array("posts" => "+1"));
			update_forum_lastpost($thread['fid']);
		}
		// Post is stuck in moderation queue
		else if($visible == 0)
		{
			// Update the unapproved posts count for the current thread and current forum
			$thread_update = array('unapprovedposts' => '+1');
			update_thread_counters($post['tid'], array("unapprovedposts" => "+1"));
			update_forum_counters($post['fid'], array("unapprovedposts" => "+1"));
		}
		else if($thread['visible'] == 0)
		{
			// Update the unapproved posts count for the current forum
			$thread_update = array('replies' => '+1');
			update_forum_counters($post['fid'], array("unapprovedposts" => "+1"));
		}
		else if($thread['visible'] == -1)
		{
			// Update the unapproved posts count for the current forum
			$thread_update = array('replies' => '+1');
			update_forum_counters($post['fid'], array("deletedposts" => "+1"));
		}

		// Update last poster
		if($visible == 1 && $thread['visible'] != 1)
		{
			update_last_post($post['tid']);
		}

		$query = $db->simple_select("attachments", "COUNT(aid) AS attachmentcount", "pid='{$this->pid}' AND visible='1'");
		$attachmentcount = $db->fetch_field($query, "attachmentcount");
		if($attachmentcount > 0)
		{
			$thread_update['attachmentcount'] = "+{$attachmentcount}";
		}
		update_thread_counters($post['tid'], $thread_update);

		// Return the post's pid and whether or not it is visible.
		$this->return_values = array(
			"pid" => $this->pid,
			"visible" => $visible,
			"closed" => $closed
		);

		$plugins->run_hooks("datahandler_post_insert_post_end", $this);

		return $this->return_values;
	}

	/**
	 * Validate a thread.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_thread()
	{
		global $mybb, $db, $plugins;

		$thread = &$this->data;

		// Validate all thread assets.

		if(!$thread['savedraft'])
		{
			$this->verify_post_flooding();
		}

		if($this->method == "insert" || array_key_exists('uid', $thread))
		{
			$this->verify_author();
		}

		if($this->method == "insert" || array_key_exists('prefix', $thread))
		{
			$this->verify_prefix();
		}

		if($this->method == "insert" || array_key_exists('subject', $thread))
		{
			$this->verify_subject();
		}

		if($this->method == "insert" || array_key_exists('message', $thread))
		{
			$this->verify_message();
		}

		if($this->method == "insert" || array_key_exists('dateline', $thread))
		{
			$this->verify_dateline();
		}

		if($this->method == "insert" || array_key_exists('icon', $thread))
		{
			$this->verify_post_icon();
		}


		$plugins->run_hooks("datahandler_post_validate_thread", $this);

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
	 * Insert a thread into the database.
	 *
	 * @return array Array of new thread details, tid and visibility.
	 */
	function insert_thread()
	{
		global $db, $mybb, $plugins, $cache, $lang, $usergroups, $CURUSER;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The thread needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The thread is not valid.");
		}

		$thread = &$this->data;

		// Fetch the forum this thread is being made in
		$query = $db->simple_select("tsf_forums", "*", "fid='{$thread['fid']}'");
		$forum = $db->fetch_array($query);

		// This thread is being saved as a draft.
		if($thread['savedraft'])
		{
			$visible = -2;
		}

		// Thread is being made now and we have a bit to do.
		else
		{
			//$forumpermissions = forum_permissions($thread['fid'], $thread['uid']);
			// Decide on the visibility of this post.
			if($forumpermissions['modthreads'] == 1 && !is_moderator($thread['fid'], "", $thread['uid']))
			{
				$visible = 0;
			}
			else
			{
				$visible = 1;
			}

			// Are posts from this user being moderated? Change visibility
			if($CURUSER['id'] == $thread['uid'] && $CURUSER['moderateposts'] == 1)
			{
				$visible = 0;
			}
		}

		// Have a post ID but not a thread ID - fetch thread ID
		if(!empty($thread['pid']) && !$thread['tid'])
		{
			$query = $db->simple_select("tsf_posts", "tid", "pid='{$thread['pid']}");
			$thread['tid'] = $db->fetch_field($query, "tid");
		}

		if(isset($thread['pid']) && $thread['pid'] > 0)
		{
			$query = $db->simple_select("tsf_posts", "pid", "pid='{$thread['pid']}' AND uid='{$thread['uid']}' AND visible='-2'");
			$draft_check = $db->fetch_field($query, "pid");
		}
		else
		{
			$draft_check = false;
		}

		// Are we updating a post which is already a draft? Perhaps changing it into a visible post?
		if($draft_check)
		{
			$this->thread_insert_data = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => (int)$thread['icon'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => (int)$thread['dateline'],
				"lastpost" => (int)$thread['dateline'],
				"lastposter" => $db->escape_string($thread['username']),
				"visible" => $visible
			);

			$plugins->run_hooks("datahandler_post_insert_thread", $this);

			$db->update_query("tsf_threads", $this->thread_insert_data, "tid='{$thread['tid']}'");

			$this->post_insert_data = array(
				"subject" => $db->escape_string($thread['subject']),
				"icon" => (int)$thread['icon'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => (int)$thread['dateline'],
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => $db->escape_string($CURUSER['ip']),
				"visible" => $visible
			);
			$plugins->run_hooks("datahandler_post_insert_thread_post", $this);

			$db->update_query("tsf_posts", $this->post_insert_data, "pid='{$thread['pid']}'");
			$this->tid = $thread['tid'];
			$this->pid = $thread['pid'];
		}

		// Inserting a new thread into the database.
		else
		{
			$this->thread_insert_data = array(
				"fid" => $thread['fid'],
				"subject" => $db->escape_string($thread['subject']),
				"uid" => $thread['uid'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => (int)$thread['dateline'],
				"lastpost" => (int)$thread['dateline'],
				"lastposter" => $db->escape_string($thread['username']),
				"lastposteruid" => $thread['uid'],
				"views" => 0,
				"replies" => 0,
				"visible" => $visible,
				"notes" => ''
			);

			$plugins->run_hooks("datahandler_post_insert_thread", $this);

			$this->tid = $db->insert_query("tsf_threads", $this->thread_insert_data);

			$this->post_insert_data = array(
				"tid" => $this->tid,
				"fid" => $thread['fid'],
				"subject" => $db->escape_string($thread['subject']),
				"uid" => $thread['uid'],
				"username" => $db->escape_string($thread['username']),
				"dateline" => (int)$thread['dateline'],
				"message" => $db->escape_string($thread['message']),
				"ipaddress" => $db->escape_string($CURUSER['ip']),
				"visible" => $visible
			);
			$plugins->run_hooks("datahandler_post_insert_thread_post", $this);

			$this->pid = $db->insert_query("tsf_posts", $this->post_insert_data);
			
			
			// Привязываем загруженные файлы к этому комментарию
            if (!empty($_POST['file_ids'])) 
			{
                   $file_ids = array_map('intval', $_POST['file_ids']); // защита
                   $id_list  = implode(',', $file_ids);

                   if (!empty($id_list)) 
				   {
                     $db->sql_query("
                         UPDATE comment_files
                         SET post_id = " . (int)$this->pid . "
                         WHERE id IN ($id_list)
                        ");
                   }
            }
			
			
			
			
			
			

			// Now that we have the post id for this first post, update the threads table.
			$firstpostup = array("firstpost" => $this->pid);
			$db->update_query("tsf_threads", $firstpostup, "tid='{$this->tid}'");
		}

		// If we're not saving a draft there are some things we need to check now
		if(!$thread['savedraft'])
		{
			if($thread['options']['subscriptionmethod'] != "" && $thread['uid'] > 0)
			{
				switch($thread['options']['subscriptionmethod'])
				{
					case "pm":
						$notification = 2;
						break;
					case "email":
						$notification = 1;
						break;
					default:
						$notification = 0;
				}

				require_once INC_PATH."/functions_user.php";
				add_subscribed_thread($this->tid, $notification, $thread['uid']);
			}

			// Perform any selected moderation tools.
			//if(is_moderator($thread['fid'], "", $thread['uid']) && isset($thread['modoptions']))
			$is_mod = is_mod($usergroups);
		
		    if($is_mod && isset($thread['modoptions']))
			{
				//$lang->load($this->language_file, true);

				$modoptions = $thread['modoptions'];
				$modlogdata['fid'] = $thread['fid'];
				if(isset($thread['tid']))
				{
					$modlogdata['tid'] = $thread['tid'];
				}

				$modoptions_update = array();

				// Close the thread.
				//if(!empty($modoptions['closethread']) && is_moderator($thread['fid'], "canopenclosethreads", $thread['uid']))
				if(!empty($modoptions['closethread']))
				{
					$modoptions_update['closed'] = 1;
					log_moderator_action($modlogdata, 'Thread Closed');
				}

				// Stick the thread.
				//if(!empty($modoptions['stickthread']) && is_moderator($thread['fid'], "canstickunstickthreads", $thread['uid']))
				if(!empty($modoptions['stickthread']))
				{
					$modoptions_update['sticky'] = 1;
					log_moderator_action($modlogdata, 'Thread Stuck');
				}

				// Execute moderation options.
				if($modoptions_update)
				{
					$db->update_query('tsf_threads', $modoptions_update, "tid='{$this->tid}'");
				}
			}
			if($visible == 1)
			{
				// If we have a registered user then update their post count and last post times.
				if($thread['uid'] > 0)
				{
					$user = get_user($thread['uid']);
					$update_query = array();
					// Only update the lastpost column of the user if the date of the thread is newer than their last post.
					if($thread['dateline'] > $user['lastpost'])
					{
						// Yes this has a single quote within a double quote. It's not a bug.
						$update_query['lastpost'] = "'{$thread['dateline']}'";
					}
					// Update the post count if this forum allows post counts to be tracked
					if($forum['usepostcounts'] != 0)
					{
						$update_query['postnum'] = "postnum+1";
					}
					if($forum['usethreadcounts'] != 0)
					{
						$update_query['threadnum'] = 'threadnum+1';
					}

					// Only update the table if we need to.
					if(!empty($update_query))
					{
						$db->update_query("users", $update_query, "id='{$thread['uid']}'", 1, true);
					}
				}

				$done_users = array();

				// Queue up any forum subscription notices to users who are subscribed to this forum.
				$excerpt = $thread['message'];

				// Parse badwords
				//require_once MYBB_ROOT."inc/class_parser.php";
				//$parser = new postParser;
				//$excerpt = $parser->parse_badwords($excerpt);
				//$excerpt = $parser->text_parse_message($excerpt);
				if(strlen($excerpt) > $mybb->settings['subscribeexcerpt'])
				{
					$excerpt = my_substr($excerpt, 0, $mybb->settings['subscribeexcerpt']).$lang->emailbit_viewthread;
				}
				//$query = $db->sql_query("
				//	SELECT u.username, u.email, u.id, u.added
				//	FROM tsf_forumsubscriptions fs
				//	LEFT JOIN users u ON (u.id=fs.uid)
				//	LEFT JOIN usergroups g ON (g.gid=u.usergroup)
				//	WHERE fs.fid='".(int)$thread['fid']."'
				//	AND fs.uid != '".(int)$thread['uid']."'
				//	AND u.lastactive > '{$forum['lastpost']}'
				//	AND g.isbannedgroup != 1
				//");



				 $query = $db->sql_query_prepared("
                    SELECT u.username, u.email, u.id, u.added
                    FROM tsf_forumsubscriptions fs
                    LEFT JOIN users u ON (u.id = fs.uid)
                    LEFT JOIN usergroups g ON (g.gid = u.usergroup)
                    WHERE fs.fid = ?
                    AND fs.uid != ?
                    AND u.lastactive > ?
                    AND g.isbannedgroup != 1", [(int)$thread['fid'],(int)$thread['uid'],$forum['lastpost']]);







				while($subscribedmember = $db->fetch_array($query->result))
				{
					if(!empty($done_users[$subscribedmember['uid']]))
					{
						continue;
					}
					$done_users[$subscribedmember['uid']] = 1;

					//$forumpermissions = forum_permissions($thread['fid'], $subscribedmember['uid']);
					if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
					{
					    continue;
					}

					if(!is_moderator($thread['fid'], "", $subscribedmember['uid']) && $forumpermissions['canonlyviewownthreads'] == 1)
					{
						// In a 'view own only' forum and not a moderator
						continue;
					}

					// Determine the language pack we'll be using to send this email in and load it if it isn't already.
					if($subscribedmember['language'] != '' && $lang->language_exists($subscribedmember['language']))
					{
						$uselang = $subscribedmember['language'];
					}
					else if($mybb->settings['bblanguage'])
					{
						$uselang = $mybb->settings['bblanguage'];
					}
					else
					{
						$uselang = "english";
					}

					if($uselang == $mybb->settings['bblanguage'])
					{
						$emailsubject = $lang->emailsubject_forumsubscription;
						$emailmessage = $lang->email_forumsubscription;

						// If the poster is unregistered and hasn't set a username, call them Guest
						if(!$thread['uid'] && !$thread['username'])
						{
							$thread['username'] = htmlspecialchars_uni($lang->guest);
						}
					}
					else
					{
						if(!isset($langcache[$uselang]['emailsubject_forumsubscription']))
						{
							//$userlang = new MyLanguage;
							//$userlang->set_path(MYBB_ROOT."inc/languages");
							//$userlang->set_language($uselang);
							//$userlang->load("messages");
							//$userlang->load("global");
							$langcache[$uselang]['emailsubject_forumsubscription'] = $userlang->emailsubject_forumsubscription;
							$langcache[$uselang]['email_forumsubscription'] = $userlang->email_forumsubscription;
							$langcache[$uselang]['guest'] = $userlang->guest;
							unset($userlang);
						}
						$emailsubject = $langcache[$uselang]['emailsubject_forumsubscription'];
						$emailmessage = $langcache[$uselang]['email_forumsubscription'];

						// If the poster is unregistered and hasn't set a username, call them Guest
						if(!$thread['uid'] && !$thread['username'])
						{
							$thread['username'] = $langcache[$uselang]['guest'];
						}
					}
					$emailsubject = sprintf($emailsubject, $forum['name']);

					$emailmessage = sprintf($emailmessage, $subscribedmember['username'], $thread['username'], $forum['name'], $SITENAME, $thread['subject'], $excerpt, $BASEURL, get_thread_link($this->tid), $thread['fid']);
					$new_email = array(
						"mailto" => $db->escape_string($subscribedmember['email']),
						"mailfrom" => '',
						"subject" => $db->escape_string($emailsubject),
						"message" => $db->escape_string($emailmessage),
						"headers" => ''
					);
					$db->insert_query("mailqueue", $new_email);
					unset($userlang);
					$queued_email = 1;
				}
				// Have one or more emails been queued? Update the queue count
				if(isset($queued_email) && $queued_email == 1)
				{
					$cache->update_mailqueue();
				}
			}
		}

		// Assign any uploaded attachments with the specific posthash to the newly created post.
		if($thread['posthash'])
		{
			$thread['posthash'] = $db->escape_string($thread['posthash']);
			$attachmentassign = array(
				"pid" => $this->pid,
				"posthash" => ''
			);
			$db->update_query("attachments", $attachmentassign, "posthash='{$thread['posthash']}' AND pid='0'");
		}

		if($visible == 1)
		{
			update_last_post($this->tid);
			update_forum_counters($thread['fid'], array("threads" => "+1", "posts" => "+1"));
			update_forum_lastpost($thread['fid']);
		}
		else if($visible == 0)
		{
			update_forum_counters($thread['fid'], array("unapprovedthreads" => "+1", "unapprovedposts" => "+1"));
		}

		$query = $db->simple_select("attachments", "COUNT(aid) AS attachmentcount", "pid='{$this->pid}' AND visible='1'");
		$attachmentcount = $db->fetch_field($query, "attachmentcount");
		if($attachmentcount > 0)
		{
			update_thread_counters($this->tid, array("attachmentcount" => "+{$attachmentcount}"));
		}

		// Return the post's pid and whether or not it is visible.
		$this->return_values = array(
			"pid" => $this->pid,
			"tid" => $this->tid,
			"visible" => $visible
		);

		$plugins->run_hooks("datahandler_post_insert_thread_end", $this);

		return $this->return_values;
	}

	/**
	 * Updates a post that is already in the database.
	 *
	 * @return array
	 */
	function update_post()
	{
		global $db, $mybb, $plugins, $usergroups, $CURUSER;

		// Yes, validating is required.
		if($this->get_validated() != true)
		{
			die("The post needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The post is not valid.");
		}

		$post = &$this->data;

		$post['pid'] = (int)$post['pid'];

		$existing_post = get_post($post['pid']);
		$post['tid'] = $existing_post['tid'];
		$post['fid'] = $existing_post['fid'];

		if(isset($post['uid']))
		{
			$uid = $post['uid'];
		}
		else
		{
			$uid = 0;
		}

		$forum = get_forum($post['fid']);
		//$forumpermissions = forum_permissions($post['fid'], $uid);

		// Decide on the visibility of this post.
		//$ismod = is_moderator($post['fid'], "", $uid);
		
		$is_mod = is_mod($usergroups);

		// Keep visibility for unapproved and deleted posts
		if($existing_post['visible'] == 0)
		{
			$visible = 0;
		}
		elseif($existing_post['visible'] == -1)
		{
			$visible = -1;
		}
		elseif($CURUSER['moderateposts'] == 1 && !$is_mod)
		{
			$visible = 0;
			require_once INC_PATH."/class_moderation.php";
			$moderation = new Moderation;
			$moderation->unapprove_posts(array($post['pid']));
		}
		else
		{
			$visible = 1;
		}

		// Update the thread details that might have been changed first.
		if($this->first_post)
		{
			$this->tid = $post['tid'];

			//if(isset($post['prefix']))
			//{
				//$this->thread_update_data['prefix'] = (int)$post['prefix'];
			//}

			if(isset($post['subject']))
			{
				$this->thread_update_data['subject'] = $db->escape_string($post['subject']);
			}

			if(isset($post['icon']))
			{
				$this->thread_update_data['icon'] = (int)$post['icon'];
			}
			if(count($this->thread_update_data) > 0)
			{
				$plugins->run_hooks("datahandler_post_update_thread", $this);

				$db->update_query("tsf_threads", $this->thread_update_data, "tid='".(int)$post['tid']."'");
			}

			// Update any moved thread links to have corresponding new subject.
			if(isset($post['subject']))
			{
				$query = $db->simple_select("tsf_threads", "tid, closed", "closed='moved|".$this->tid."'");
				if($db->num_rows($query) > 0)
				{
					$update_data['subject'] = $db->escape_string($post['subject']);
					while($result = $db->fetch_array($query))
					{
						$db->update_query("threads", $update_data, "tid='".(int)$result['tid']."'");
					}
				}
			}
		}

		// Prepare array for post updating.

		$this->pid = $post['pid'];

		if(isset($post['subject']))
		{
			$this->post_update_data['subject'] = $db->escape_string($post['subject']);
		}

		if(isset($post['message']))
		{
			$this->post_update_data['message'] = $db->escape_string($post['message']);
		}

		if(isset($post['editreason']) && trim($post['editreason']) != '')
		{
			$this->post_update_data['editreason'] = $db->escape_string(trim($post['editreason']));
		}
		elseif(empty($post['editreason']))
		{
			$this->post_update_data['editreason'] = '';
		}

		if(isset($post['icon']))
		{
			$this->post_update_data['icon'] = (int)$post['icon'];
		}

		

		// If we need to show the edited by, let's do so.
		//if(($mybb->settings['showeditedby'] == 1 && !is_moderator($post['fid'], "caneditposts", $post['edit_uid'])) || ($mybb->settings['showeditedbyadmin'] == 1 && is_moderator($post['fid'], "caneditposts", $post['edit_uid'])))
		
		$showeditedby = "1";
		
		if($showeditedby == 1)
		{
			$this->post_update_data['edituid'] = (int)$post['edit_uid'];
			$this->post_update_data['edittime'] = TIMENOW;
		}

		$plugins->run_hooks("datahandler_post_update", $this);

		$db->update_query("tsf_posts", $this->post_update_data, "pid='".(int)$post['pid']."'");
		
		
		if (!empty($_POST['file_ids'])) 
		{
               $file_ids = array_map('intval', $_POST['file_ids']);
               $id_list = implode(',', $file_ids);

               if (!empty($id_list)) 
			   {
                 // Привязываем файлы к новости в базе данных
                  $db->sql_query("
                    UPDATE comment_files 
                    SET post_id = " . (int)$post['pid'] . " 
                    WHERE id IN ($id_list)
                 ");
               }
        }
		
		
		
		
		
		
		

		// Automatic subscription to the thread
		if($post && !empty($post['options']['subscriptionmethod']) && $uid > 0)
		{
			switch($post['options']['subscriptionmethod'])
			{
				case "pm":
					$notification = 2;
					break;
				case "email":
					$notification = 1;
					break;
				default:
					$notification = 0;
			}
			require_once INC_PATH."/functions_user.php";
			add_subscribed_thread($post['tid'], $notification, $post['uid']);
		}
		else
		{
			$db->delete_query("tsf_threadsubscriptions", "uid='".(int)$uid."' AND tid='".(int)$post['tid']."'");
		}

		update_forum_lastpost($post['fid']);
		update_last_post($post['tid']);

		// Return the thread's first post id and whether or not it is visible.
		$this->return_values = array(
			'visible' => $visible,
			'first_post' => $this->first_post
		);

		$plugins->run_hooks("datahandler_post_update_end", $this);

		return $this->return_values;
	}
}
