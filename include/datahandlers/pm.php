<?php


declare(strict_types=1);

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


class PMDataHandler extends DataHandler
{
	
	public $language_file = 'datahandler_pm';

	
	public $language_prefix = 'pmdata';

	
	public $pm_insert_data = array();

	
	public $pm_update_data = array();

	
	public $pmid = 0;

	
	public $return_values = array();

	
	function verify_subject()
	{
		$subject = &$this->data['subject'];

		// Subject is over 85 characters, too long.
		if(my_strlen($subject) > 85)
		{
			$this->set_error("too_long_subject");
			return false;
		}
		// No subject, apply the default [no subject]
		if(!trim_blank_chrs($subject))
		{
			$this->set_error("missing_subject");
			return false;
		}
		return true;
	}

	/**
	 * Verifies if a message for a PM is valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_message()
	{
		$message = &$this->data['message'];

		// No message, return an error.
		if(trim_blank_chrs($message) == '')
		{
			$this->set_error("missing_message");
			return false;
		}

		// If the length of message is beyond SQL limitation for 'text' field
		else if(strlen($message) > 65535)
		{
			$this->set_error("message_too_long", array('65535', strlen($message)));
			return false;
		}

		return true;
	}

	/**
	 * Verifies if the specified sender is valid or not.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_sender()
	{
		global $db, $mybb, $lang;

		$pm = &$this->data;

		// Return if we've already validated
		if(!empty($pm['sender']))
		{
			return true;
		}

		// Fetch the senders profile data.
		$sender = get_user($pm['fromid']);

		// Collect user permissions for the sender.
		$sender_permissions = user_permissions($pm['fromid']);

		// Check if the sender is over their quota or not - if they are, disable draft sending
		if(isset($pm['options']['savecopy']) && $pm['options']['savecopy'] != 0 && empty($pm['saveasdraft']))
		{
			if($sender_permissions['pmquota'] != 0 && $sender['totalpms'] >= $sender_permissions['pmquota'] && $this->admin_override != true)
			{
				$pm['options']['savecopy'] = 0;
			}
		}

		// Assign the sender information to the data.
		$pm['sender'] = array(
			"uid" => $sender['id'] ?? 0,
			"username" => $sender['username'] ?? ''
		);

		return true;
	}

	/**
	 * Verifies if an array of recipients for a private message are valid
	 *
	 * @return boolean True when valid, false when invalid.
	 */
function verify_recipient()
{
    global $cache, $db, $mybb, $lang;

    $pm = &$this->data;

    $recipients = array();
    $invalid_recipients = array();
    
    // Исправление: приводим fromid к integer
    $fromId = (int)$pm['fromid'];
    
    // We have our recipient usernames but need to fetch user IDs
    if(array_key_exists("to", $pm))
    {
        foreach(array("to", "bcc") as $recipient_type)
        {
            if(!isset($pm[$recipient_type]))
            {
                $pm[$recipient_type] = array();
            }
            if(!is_array($pm[$recipient_type]))
            {
                $pm[$recipient_type] = array($pm[$recipient_type]);
            }

            $pm[$recipient_type] = array_map('trim', $pm[$recipient_type]);
            $pm[$recipient_type] = array_filter($pm[$recipient_type]);

            // No recipients? Skip query
            if(empty($pm[$recipient_type]))
            {
                if($recipient_type == 'to' && empty($pm['saveasdraft']))
                {
                    $this->set_error("no_recipients");
                    return false;
                }
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($pm[$recipient_type]), '?'));
            $query = $db->sql_query_prepared("SELECT * FROM users WHERE username IN ({$placeholders})", $pm[$recipient_type]);

            $validUsernames = array();

            while($query && ($user = $db->fetch_array($query)))
            {
                if($recipient_type == "bcc")
                {
                    $user['bcc'] = 1;
                }

                $recipients[] = $user;
                $validUsernames[] = $user['username'];
            }

            foreach($pm[$recipient_type] as $username)
            {
                if(!in_array($username, $validUsernames))
                {
                    $invalid_recipients[] = $username;
                }
            }
        }
    }
    // We have recipient IDs
    else
    {
        foreach(array("toid", "bccid") as $recipient_type)
        {
            if(!isset($pm[$recipient_type]))
            {
                $pm[$recipient_type] = array();
            }
            if(!is_array($pm[$recipient_type]))
            {
                $pm[$recipient_type] = array($pm[$recipient_type]);
            }
            $pm[$recipient_type] = array_map('intval', $pm[$recipient_type]);
            $pm[$recipient_type] = array_filter($pm[$recipient_type]);

            // No recipients? Skip query
            if(empty($pm[$recipient_type]))
            {
                if($recipient_type == 'toid' && !$pm['saveasdraft'])
                {
                    $this->set_error("no_recipients");
                    return false;
                }
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($pm[$recipient_type]), '?'));
            $query = $db->sql_query_prepared("SELECT * FROM users WHERE id IN ({$placeholders})", $pm[$recipient_type]);

            $validUids = array();

            while($query && ($user = $db->fetch_array($query)))
            {
                if($recipient_type == "bccid")
                {
                    $user['bcc'] = 1;
                }

                $recipients[] = $user;
                $validUids[] = $user['id'];
            }

            foreach($pm[$recipient_type] as $uid)
            {
                if(!in_array($uid, $validUids))
                {
                    $invalid_recipients[] = $uid;
                }
            }
        }
    }

    // If we have one or more invalid recipients and we're not saving a draft, error
    if(count($invalid_recipients) > 0)
    {
        $invalid_recipients = implode($lang->comma, array_map("htmlspecialchars_uni", $invalid_recipients));
        $this->set_error("invalid_recipients", array($invalid_recipients));
        return false;
    }

    // ИСПРАВЛЕНИЕ: передаем integer вместо строки
    $sender_permissions = user_permissions($fromId);

    // Are we trying to send this message to more users than the permissions allow?
    if(isset($sender_permissions['maxpmrecipients']) && $sender_permissions['maxpmrecipients'] > 0 && count($recipients) > $sender_permissions['maxpmrecipients'] && $this->admin_override != true)
    {
        $this->set_error("You are only allowed to send messages to ".$sender_permissions['maxpmrecipients']." users at a time");
    }

    // Now we're done with that we loop through each recipient
    $pm['recipients'] = array();
    foreach($recipients as $user)
    {
        // ИСПРАВЛЕНИЕ: передаем integer вместо строки
        $recipient_permissions = user_permissions((int)$user['id']);

        // See if the sender is on the recipients ignore list and that either
        // - admin_override is set or
        // - sender is an administrator
        if($this->admin_override != true && $sender_permissions['canoverridepm'] != 1)
        {
            if(!empty($user['ignorelist']) && strpos(','.$user['ignorelist'].',', ','.$pm['fromid'].',') !== false)
            {
                $this->set_error("recipient_is_ignoring", array(htmlspecialchars_uni($user['username'])));
            }

            // Is the recipient only allowing private messages from their buddy list?
            $allowbuddyonly = "0";
            
            if(empty($pm['saveasdraft']) && $allowbuddyonly == 1 && $user['receivefrombuddy'] == 1 && !empty($user['buddylist']) && strpos(','.$user['buddylist'].',', ','.$pm['fromid'].',') === false)
            {
                $this->set_error('recipient_has_buddy_only', array(htmlspecialchars_uni($user['username'])));
            }

            // Can the recipient actually receive private messages based on their permissions or user setting?
            if(($user['receivepms'] == 0 || $recipient_permissions['canusepms'] == 0) && empty($pm['saveasdraft']))
            {
                $this->set_error("recipient_pms_disabled", array(htmlspecialchars_uni($user['username'])));
                return false;
            }
        }

       
        // Check to see if the user has reached their private message quota - if they have, email them.
        //if($recipient_permissions['pmquota'] != 0 && $user['totalpms'] >= $recipient_permissions['pmquota'] && $sender_permissions['cancp'] != 1 && empty($pm['saveasdraft']) && !$this->admin_override)
		if($recipient_permissions['pmquota'] != 0 && $user['totalpms'] >= $recipient_permissions['pmquota'] && (int)($sender_permissions['canstaffpanel'] ?? 0) !== 1 && empty($pm['saveasdraft']) && !$this->admin_override)
        {
            $lang->load("messages");

            // Язык, на котором сейчас загружен глобальный $lang (язык сессии
            // отправителя) — не обязательно совпадает с языком получателя.
            $sessionlang = $_COOKIE['ts_language'] ?? $defaultlanguage ?? 'english';

            $uselang = 'english';
            if (!empty($user['language']) && is_dir(INC_PATH . '/languages/' . trim((string)$user['language']))) {
                $uselang = trim((string)$user['language']);
            } elseif (!empty($defaultlanguage) && is_dir(INC_PATH . '/languages/' . $defaultlanguage)) {
                $uselang = $defaultlanguage;
            }

            if ($uselang === $sessionlang)
            {
                // У получателя тот же язык, что и у текущей сессии — $lang уже то, что нужно
                $emailsubject = $lang->messages['emailsubject_reachedpmquota'];
                $emailmessage = $lang->messages['email_reachedpmquota'];
            }
            else
            {
                // Язык получателя другой — читаем строки напрямую из ЕГО
                // messages.lang.php, не трогая глобальный $lang (иначе
                // сломаем язык интерфейса для текущего пользователя).
                $l = [];
                $messagesFile = INC_PATH . '/languages/' . $uselang . '/messages.lang.php';
                if (is_file($messagesFile)) {
                    require $messagesFile;
                }
                $emailsubject = $l['emailsubject_reachedpmquota'] ?? $lang->messages['emailsubject_reachedpmquota'];
                $emailmessage = $l['email_reachedpmquota']        ?? $lang->messages['email_reachedpmquota'];
            }
            $emailmessage = sprintf($emailmessage, $user['username'], $SITENAME, $BASEURL);
            $emailsubject = sprintf($emailsubject, $SITENAME, $pm['subject']);

            $db->sql_query_prepared(
                "INSERT INTO mailqueue (`mailto`,`mailfrom`,`subject`,`message`,`headers`) VALUES (?,?,?,?,?)",
                array($user['email'], '', $emailsubject, $emailmessage, '')
            );
            $cache->update_mailqueue();

            if($this->admin_override != true)
            {
                $this->set_error("recipient_reached_quota", array(htmlspecialchars_uni($user['username'])));
            }
        }

        // Everything looks good, assign some specifics about the recipient
        $pm['recipients'][$user['id']] = array(
            "uid" => $user['id'] ?? 0,
            "username" => $user['username'] ?? '',
            "email" => $user['email'] ?? '',
            "lastactive" => $user['lastactive'] ?? 0,
            "pmnotice" => $user['pmnotice'] ?? 0,
            "pmnotify" => $user['pmnotify'] ?? 0,
            "language" => $user['language'] ?? ''
        );

        // If this recipient is defined as a BCC recipient, save it
        if(isset($user['bcc']) && $user['bcc'] == 1)
        {
            $pm['recipients'][$user['uid']]['bcc'] = 1;
        }
    }
    return true;
}

	/**
	* Verify that the user is not flooding the system.
	*
	* @return boolean
	*/
	function verify_pm_flooding()
	{
		global $mybb, $db, $usergroups;

		$pm = &$this->data;

		
		$pmfloodsecs = "60";
		
		$is_mod = is_mod($usergroups);
		
		// Check if post flooding is enabled within MyBB or if the admin override option is specified.
		if($pmfloodsecs > 0 && $pm['fromid'] != 0 && $this->admin_override == false && !$is_mod)
		{
			// Fetch the senders profile data.
			$sender = get_user($pm['fromid']);

			// Calculate last post
			$query = $db->sql_query_prepared(
				"SELECT dateline FROM privatemessages WHERE fromid = ? AND toid != '0' ORDER BY dateline DESC LIMIT 1",
				array($pm['fromid'])
			);
			$sender['lastpm'] = $query ? $db->fetch_field($query, "dateline") : 0;

			// A little bit of calculation magic and moderator status checking.
			if(TIMENOW-$sender['lastpm'] <= $pmfloodsecs)
			{
				// Oops, user has been flooding - throw back error message.
				$time_to_wait = ($pmfloodsecs - (TIMENOW-$sender['lastpm'])) + 1;
				if($time_to_wait == 1)
				{
					$this->set_error("pm_flooding_one_second");
				}
				else
				{
					$this->set_error("pm_flooding", array($time_to_wait));
				}
				return false;
			}
		}
		// All is well that ends well - return true.
		return true;
	}

	/**
	 * Verifies if the various 'options' for sending PMs are valid.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function verify_options()
	{
		$options = &$this->data['options'];

		
		$this->verify_yesno_option($options, 'savecopy', 1);
		

		// Requesting a read receipt?
		if(isset($options['readreceipt']) && $options['readreceipt'] == 1)
		{
			$options['readreceipt'] = 1;
		}
		else
		{
			$options['readreceipt'] = 0;
		}
		return true;
	}

	/**
	 * Validate an entire private message.
	 *
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_pm()
	{
		global $plugins;

		$pm = &$this->data;

		if(empty($pm['savedraft']))
		{
			$this->verify_pm_flooding();
		}

		// Verify all PM assets.
		$this->verify_subject();

		$this->verify_sender();

		$this->verify_recipient();

		$this->verify_message();

		$this->verify_options();

		$plugins->run_hooks("datahandler_pm_validate", $this);

		// Choose the appropriate folder to save in.
		if(!empty($pm['saveasdraft']))
		{
			$pm['folder'] = 3;
		}
		else
		{
			$pm['folder'] = 1;
		}

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
	 * Insert a new private message.
	 *
	 * @return array Array of PM useful data.
	 */
	function insert_pm()
	{
		global $cache, $db, $mybb, $plugins, $lang, $CURUSER;

		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The PM needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The PM is not valid.");
		}

		// Assign data to common variable
		$pm = &$this->data;

		if(empty($pm['pmid']))
		{
			$pm['pmid'] = 0;
		}
		$pm['pmid'] = (int)$pm['pmid'];

		if(empty($pm['icon']) || $pm['icon'] < 0)
		{
			$pm['icon'] = 0;
		}

		$uid = 0;

		// Build recipient list
		$recipient_list = array();
		if(isset($pm['recipients']) && is_array($pm['recipients']))
		{
			foreach($pm['recipients'] as $recipient)
			{
				if(!empty($recipient['bcc']))
				{
					$recipient_list['bcc'][] = $recipient['uid'];
				}
				else
				{
					$recipient_list['to'][] = $recipient['uid'];
					$uid = $recipient['uid'];
				}
			}
		}

		$this->pm_insert_data = array(
			'fromid' => (int)$pm['sender']['uid'],
			'folder' => $pm['folder'],
			'subject' => $pm['subject'],
			'icon' => (int)$pm['icon'],
			'message' => $pm['message'],
			'dateline' => TIMENOW,
			'status' => 0,
			'receipt' => (int)$pm['options']['readreceipt'],
			'readtime' => 0,
			'recipients' => my_serialize($recipient_list),
			'ipaddress' => $pm['ipaddress'] ?? ''
		);

		// Check if we're updating a draft or not.
		$query = $db->sql_query_prepared(
			"SELECT pmid, deletetime FROM privatemessages WHERE folder = '3' AND uid = ? AND pmid = ?",
			array((int)$pm['sender']['uid'], $pm['pmid'])
		);
		$draftcheck = $query ? $db->fetch_array($query) : null;

		// This PM was previously a draft
		if($draftcheck)
		{
			if($draftcheck['deletetime'])
			{
				// This draft was a reply to a PM
				$pm['pmid'] = $draftcheck['deletetime'];
				$pm['do'] = "reply";
			}

			// Delete the old draft as we no longer need it
			$db->sql_query_prepared("DELETE FROM privatemessages WHERE pmid = ?", array($draftcheck['pmid']));
		}

		// Saving this message as a draft
		if(!empty($pm['saveasdraft']))
		{
			$this->pm_insert_data['uid'] = $pm['sender']['uid'];

			// If this is a reply, then piggyback into the deletetime to let us know in the future
			if($pm['do'] == "reply" || $pm['do'] == "replyall")
			{
				$this->pm_insert_data['deletetime'] = $pm['pmid'];
			}

			$plugins->run_hooks("datahandler_pm_insert_updatedraft", $this);

			$columns = array_keys($this->pm_insert_data);
			$placeholders = implode(',', array_fill(0, count($columns), '?'));
			$db->sql_query_prepared(
				"INSERT INTO privatemessages (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
				array_values($this->pm_insert_data)
			);
			$this->pmid = (int)$db->insert_id();

			$plugins->run_hooks("datahandler_pm_insert_updatedraft_commit", $this);

			// If this is a draft, end it here - below deals with complete messages
			return array(
				"draftsaved" => 1
			);
		}

		$this->pmid = array();

		// Save a copy of the PM for each of our recipients
		foreach($pm['recipients'] as $recipient)
		{
			// Send email notification of new PM if it is enabled for the recipient
			$query = $db->sql_query_prepared(
				"SELECT dateline FROM privatemessages WHERE uid = ? AND folder = '1' ORDER BY dateline DESC LIMIT 1",
				array($recipient['uid'])
			);
			$lastpm = $query ? $db->fetch_array($query) : null;
			if($recipient['pmnotify'] == 1 && (empty($lastpm['dateline']) || $recipient['lastactive'] > $lastpm['dateline']))
			{
				if(($recipient['language'] ?? '') != "" && $lang->language_exists($recipient['language']))
				{
					$uselang = $recipient['language'];
				}
				elseif($mybb->settings['bblanguage'])
				{
					$uselang = $mybb->settings['bblanguage'];
				}
				else
				{
					$uselang = "english";
				}
				if($uselang == $mybb->settings['bblanguage'] && !empty($lang->emailsubject_newpm))
				{
					$emailsubject = $lang->emailsubject_newpm;
					$emailmessage = $lang->email_newpm;
				}
				else
				{
					$userlang = new MyLanguage;
					$userlang->set_path(MYBB_ROOT."inc/languages");
					$userlang->set_language($uselang);
					$userlang->load("messages");
					$emailsubject = $userlang->emailsubject_newpm;
					$emailmessage = $userlang->email_newpm;
				}

				if(!($pm['sender']['username'] ?? ''))
				{
					$pm['sender']['username'] = 'Ruff Tracker Engine';
				}

				require_once MYBB_ROOT.'inc/class_parser.php';
				$parser = new Postparser;

				$parser_options = array(
					'me_username'		=> $pm['sender']['username'],
					'filter_badwords'	=> 1
				);

				//$pm['message'] = $parser->text_parse_message($pm['message'], $parser_options);

				$emailmessage = $lang->sprintf($emailmessage, $recipient['username'], $pm['sender']['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $pm['message']);
				$emailsubject = $lang->sprintf($emailsubject, $mybb->settings['bbname'], $pm['subject']);

				$db->sql_query_prepared(
					"INSERT INTO mailqueue (`mailto`,`mailfrom`,`subject`,`message`,`headers`) VALUES (?,?,?,?,?)",
					array($recipient['email'], '', $emailsubject, $emailmessage, '')
				);
				$cache->update_mailqueue();
			}

			$this->pm_insert_data['uid'] = $recipient['uid'];
			$this->pm_insert_data['toid'] = $recipient['uid'];

			$plugins->run_hooks("datahandler_pm_insert", $this);

			$columns = array_keys($this->pm_insert_data);
			$placeholders = implode(',', array_fill(0, count($columns), '?'));
			$db->sql_query_prepared(
				"INSERT INTO privatemessages (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
				array_values($this->pm_insert_data)
			);
			$this->pmid[] = (int)$db->insert_id();

			$plugins->run_hooks("datahandler_pm_insert_commit", $this);

			// If PM noices/alerts are on, show!
			if($recipient['pmnotice'] == 1)
			{
				$db->sql_query_prepared("UPDATE users SET pmnotice = ? WHERE id = ?", array(2, $recipient['uid']));
			}

			// Update private message count (total, new and unread) for recipient
			require_once INC_PATH."/functions_user.php";
			update_pm_count($recipient['uid'], 7, $recipient['lastactive']);
		}

		// Are we replying or forwarding an existing PM?
		if($pm['pmid'])
		{
			if($pm['do'] == "reply" || $pm['do'] == "replyall")
			{
				$db->sql_query_prepared(
					"UPDATE privatemessages SET status = ?, statustime = ? WHERE pmid = ? AND uid = ?",
					array(3, TIMENOW, $pm['pmid'], $pm['sender']['uid'])
				);
			}
			elseif($pm['do'] == "forward")
			{
				$db->sql_query_prepared(
					"UPDATE privatemessages SET status = ?, statustime = ? WHERE pmid = ? AND uid = ?",
					array(4, TIMENOW, $pm['pmid'], $pm['sender']['uid'])
				);
			}
		}

		// If we're saving a copy
		if($pm['options']['savecopy'] != 0)
		{
			if(isset($recipient_list['to']) && is_array($recipient_list['to']) && count($recipient_list['to']) == 1)
			{
				$this->pm_insert_data['toid'] = $uid;
			}
			else
			{
				$this->pm_insert_data['toid'] = 0;
			}
			$this->pm_insert_data['uid'] = (int)$pm['sender']['uid'];
			$this->pm_insert_data['folder'] = 2;
			$this->pm_insert_data['status'] = 1;
			$this->pm_insert_data['receipt'] = 0;

			$plugins->run_hooks("datahandler_pm_insert_savedcopy", $this);

			$columns = array_keys($this->pm_insert_data);
			$placeholders = implode(',', array_fill(0, count($columns), '?'));
			$db->sql_query_prepared(
				"INSERT INTO privatemessages (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
				array_values($this->pm_insert_data)
			);

			$plugins->run_hooks("datahandler_pm_insert_savedcopy_commit", $this);

			// Because the sender saved a copy, update their total pm count
			require_once INC_PATH."/functions_user.php";
			update_pm_count($pm['sender']['uid'], 1);
			
			// --- ВСТАВИТЬ ПРИВЯЗКУ ФАЙЛОВ ЗДЕСЬ ---
			if (!empty($_POST['file_ids']) && is_array($_POST['file_ids'])) {
				$file_ids = array_values(array_filter(array_map('intval', $_POST['file_ids'])));
				if ($file_ids) {
					$id_placeholders = implode(',', array_fill(0, count($file_ids), '?'));
					foreach ((array)$this->pmid as $pmid) {
						$pmid = (int)$pmid;
						$db->sql_query_prepared(
							"UPDATE comment_files SET messages_id = ? WHERE id IN ({$id_placeholders})",
							array_merge([$pmid], $file_ids)
						);
					}
				}
			}
			// --- КОНЕЦ ВСТАВКИ ---
		}

		// Return back with appropriate data
		$this->return_values = array(
			"messagesent" => 1,
			"pmids" => $this->pmid
		);

		$plugins->run_hooks("datahandler_pm_insert_end", $this);

		return $this->return_values;
	}
}