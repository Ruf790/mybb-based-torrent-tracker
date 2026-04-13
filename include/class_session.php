<?php

declare(strict_types=1);

class Session
{
    public string $sid = '';
    public int $uid = 0;
    public string $ipaddress = '';
    public string $packedip = '';
    public string $useragent = '';
    public bool $is_spider = false;

    /**
     * Request parameters that are to be ignored for location storage
     */
    public array $ignore_parameters = [
        'my_post_key',
        'logoutkey',
    ];

    /**
     * Initialize a session
     */
    public function init(): void
    {
        global $db, $mybb, $cache, $plugins;

        // Get our visitor's IP
        $this->ipaddress = get_ip();
        $this->packedip = my_inet_pton($this->ipaddress);
        $this->useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Attempt to find a session id in the cookies
        if (isset($mybb->cookies['sid']) && !defined('IN_UPGRADE')) {
            $sid = $db->escape_string($mybb->cookies['sid']);

            // Load the session if not using a bot sid
            if (!str_contains($sid, '=')) {
                $query = $db->simple_select("sessions", "*", "sid='{$sid}'");
                $session = $db->fetch_array($query);
                if ($session) {
                    $this->sid = $session['sid'];
                }
            }
        }

        $plugins?->run_hooks('pre_session_load', $this);

        // If we have a valid session id and user id, load that users session
        if (!empty($mybb->cookies['mybbuser'])) {
            $logon = explode("_", $mybb->cookies['mybbuser'], 2);
            $this->load_user((int)$logon[0], $logon[1] ?? '');
        }

        // If no user still, then we have a guest
        if (!isset($mybb->user['id'])) {
            // Detect if this guest is a search engine spider
            if (!$this->sid) {
                $spiders = $cache->read("spiders");
                if (is_array($spiders)) {
                    foreach ($spiders as $spider) {
                        if (str_contains(strtolower($this->useragent), strtolower($spider['useragent']))) {
                            // ✅ ИСПРАВЛЕНО: передаем числовой ID паука
                            $this->load_spider((int)$spider['sid']);
                            break;
                        }
                    }
                }
            }

            // Still nothing? JUST A GUEST!
            if (!$this->is_spider) {
                $this->load_guest();
            }
        }

        // Give the user a cookie if they aren't a spider
        if ($this->sid && (!isset($mybb->cookies['sid']) || $mybb->cookies['sid'] !== $this->sid) && !$this->is_spider) {
            my_setcookie("sid", $this->sid, -1, true);
        }

        $plugins?->run_hooks('post_session_load', $this);
        
        // ✅ Activate cron
        $GLOBALS['ts_cron_image'] = !defined('SKIP_CRON_JOBS');
    }

   

    /**
     * Load a user via the user credentials
     */
    public function load_user(int $uid, string $loginkey = ''): bool
    {
        global $mybb, $db, $lang, $mybbgroups, $cache, $timeformat, $dateformat, $f_postsperpage, $f_threadsperpage, $securehash, $SITENAME;

        $query = $db->sql_query_prepared("
            SELECT u.*, f.*
            FROM users u
            LEFT JOIN userfields f ON (f.ufid=u.id)
            WHERE u.id = ?
            LIMIT 1", [$uid]);

        $mybb->user = $db->fetch_array($query->result);

        // Check the password if we're not using a session
        if (!$mybb->user || empty($loginkey) || $loginkey !== ($mybb->user['loginkey'] ?? '')) {
            unset($mybb->user);
            $this->uid = 0;
            return false;
        }

        $this->uid = $mybb->user['id'];
        $mybb->user['logoutkey'] = md5($mybb->user['loginkey']);
        
        $enablepms = "1";

        // Sort out the private message count for this user
        if (($mybb->user['totalpms'] ?? 0) == -1 || ($mybb->user['unreadpms'] ?? 0) == -1 && $enablepms != 0) {
            $update = 0;
            if ($mybb->user['totalpms'] == -1) $update += 1;
            if ($mybb->user['unreadpms'] == -1) $update += 2;

            require_once INC_PATH."/functions_user.php";
            $pmcount = update_pm_count('', $update);
            if (is_array($pmcount)) {
                $mybb->user = array_merge($mybb->user, $pmcount);
            }
        }

        $mybb->user['pms_total'] = $mybb->user['totalpms'] ?? 0;
        $mybb->user['pms_unread'] = $mybb->user['unreadpms'] ?? 0;

        // Update IP if changed
        $lastip_add = '';
        if (($mybb->user['lastip'] ?? '') != $this->packedip && array_key_exists('lastip', $mybb->user) && !defined('IN_UPGRADE')) {
            $lastip_add = ", lastip=".$db->escape_binary($this->packedip);
        }

        // Update last login if needed
        $last_login = '';
        if (900 < TIMENOW - ($mybb->user['last_login'] ?? 0)) {
            $last_login = ", last_login=".(int)($mybb->user['lastactive'] ?? 0);
        }

        // Generate passkey if needed
        $passkeys = '';
        if (strlen($mybb->user['passkey'] ?? '') != 32) {
            $passkey = generate_passkey($mybb->user['username'], $mybb->user['loginkey']);
            if ($passkey !== false) {
                $passkeys = ", passkey='" . $db->escape_string($passkey) . "'";
            }
        }

        // Update user activity
        $time = TIMENOW;
        if ($time - ($mybb->user['lastactive'] ?? 0) > 900) {
            $db->sql_query("UPDATE users SET lastvisit='{$mybb->user['lastactive']}', lastactive='$time' WHERE id='{$mybb->user['id']}'");
            $mybb->user['lastvisit'] = $mybb->user['lastactive'];
            require_once INC_PATH."/functions_user.php";
            update_pm_count('', 2);
        } else {
            $timespent = TIMENOW - $mybb->user['lastactive'];
            $db->sql_query("UPDATE users SET lastactive='$time', timeonline=timeonline+$timespent{$last_login}{$passkeys}{$lastip_add} WHERE id='{$mybb->user['id']}'");
        }

        // Set user preferences
        if (!empty($mybb->user['dateformat'])) {
            global $date_formats;
            if (!empty($date_formats[$mybb->user['dateformat']])) {
                $dateformat = $date_formats[$mybb->user['dateformat']];
            }
        }

        if (!empty($mybb->user['timeformat'])) {
            global $time_formats;
            if (!empty($time_formats[$mybb->user['timeformat']])) {
                $timeformat = $time_formats[$mybb->user['timeformat']];
            }
        }

        // Set pagination preferences
        if (!empty($mybb->user['threadsperpages'])) {
            $f_threadsperpage = $mybb->user['threadsperpages'];
        }

        if (!empty($mybb->user['postsperpage'])) {
            $f_postsperpage = $mybb->user['postsperpage'];
        }

        // Check user ban status
        $usergroups = $cache->read('usergroups');
        $userGroupId = (int)($mybb->user['usergroup'] ?? 0); // ✅ ИСПРАВЛЕНО: приводим к int

        if (!empty($usergroups[$userGroupId]) && $usergroups[$userGroupId]['isbannedgroup'] == 1) {
            $ban = $db->fetch_array(
                $db->simple_select('banned', '*', 'uid='.$mybb->user['id'], ['limit' => 1])
            );

            if ($ban) {
                $mybb->user['banned'] = 1;
                $mybb->user['bandate'] = $ban['dateline'];
                $mybb->user['banlifted'] = $ban['lifted'];
                $mybb->user['banoldgroup'] = $ban['oldgroup'];
                $mybb->user['banolddisplaygroup'] = $ban['olddisplaygroup'];
                $mybb->user['banoldadditionalgroups'] = $ban['oldadditionalgroups'];
                $mybb->user['banreason'] = $ban['reason'];
            } else {
                $mybb->user['banned'] = 0;
            }
        }

        // Handle ban expiration
        if (!empty($mybb->user['bandate']) && 
            isset($mybb->user['banlifted']) && 
            !empty($mybb->user['banlifted']) && 
            $mybb->user['banlifted'] < $time) {
            
            $db->shutdown_query("UPDATE users SET usergroup='".(int)($mybb->user['banoldgroup'] ?? 0)."', additionalgroups='".$db->escape_string($mybb->user['banoldadditionalgroups'] ?? '')."', displaygroup='".(int)($mybb->user['banolddisplaygroup'] ?? 0)."' WHERE id='".$mybb->user['id']."'");
            $db->shutdown_query("DELETE FROM banned WHERE uid='".$mybb->user['id']."'");
            
            $mybb->user['usergroup'] = $mybb->user['banoldgroup'];
            $mybb->user['displaygroup'] = $mybb->user['banolddisplaygroup'];
            $mybb->user['additionalgroups'] = $mybb->user['banoldadditionalgroups'];
            $mybbgroups = $mybb->user['usergroup'];
        } elseif (!empty($mybb->user['bandate']) && 
                 (empty($mybb->user['banlifted']) || 
                  (!empty($mybb->user['banlifted']) && $mybb->user['banlifted'] > $time))) {
            $mybbgroups = $mybb->user['usergroup'];
        } else {
            $mybbgroups = $mybb->user['usergroup'];
            if (!empty($mybb->user['additionalgroups'])) {
                $mybbgroups .= ','.$mybb->user['additionalgroups'];
            }
        }

        $mybb->usergroup = usergroup_permissions($mybbgroups);
        if (empty($mybb->user['displaygroup'])) {
            $mybb->user['displaygroup'] = $mybb->user['usergroup'];
        }

        $mydisplaygroup = usergroup_displaygroup((int)$mybb->user['displaygroup']); // ✅ ИСПРАВЛЕНО: приводим к int
        if (is_array($mydisplaygroup)) {
            $mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
        }

        if (empty($mybb->user['usertitle'])) {
            $mybb->user['usertitle'] = $mybb->usergroup['usertitle'] ?? '';
        }
        
        $GLOBALS['CURUSER'] = $mybb->user;

        // Validate user access
        $script_name = basename($_SERVER['PHP_SELF']);
        $group_data_results = $usergroups[$mybb->user['usergroup']] ?? [];
        $GLOBALS['usergroups'] = $group_data_results;

        if (($group_data_results['isbannedgroup'] ?? 0) == '1' ||
            ($mybb->user['enabled'] ?? '') == 'no' ||
            (($mybb->user['ustatus'] ?? '') == 'pending' && 
             !($mybb->input['action'] == 'activate' && $script_name == 'member.php'))) {
            print_no_permission(false, true, $mybb->user['notifs'] ?? '');
            exit();
        }

        // Update or create the session
        if (!defined("NO_ONLINE") && !defined('IN_UPGRADE')) {
            if (!empty($this->sid)) {
                $this->update_session($this->sid, $mybb->user['id']);
            } else {
                $this->create_session($mybb->user['id']);
            }
        }
        
        return true;
    }

    /**
     * Load a guest user
     */
    public function load_guest(): void
    {
        global $mybb, $db;

        // Set up defaults
        $time = TIMENOW;
        $mybb->user = [
            'usergroup' => 1,
            'additionalgroups' => '',
            'username' => '',
            'id' => 0,
            'displaygroup' => 1,
            'invisible' => 0,
            'moderateposts' => 0,
            'showquickreply' => 1,
            'signature' => '',
            'sourceeditor' => 0,
            'subscriptionmethod' => 0,
            'suspendposting' => 0
        ];

        $mybbgroups = 1;

        // Handle last visit and activity
        if (isset($mybb->cookies['mybb']['lastvisit'])) {
            $mybb->user['lastactive'] = (int)($mybb->cookies['mybb']['lastactive'] ?? $time);
            
            if ($time - $mybb->user['lastactive'] > 900) {
                // Исправлено: преобразуем int в string для my_setcookie
                my_setcookie("mybb[lastvisit]", (string)$mybb->user['lastactive']);
                $mybb->user['lastvisit'] = $mybb->user['lastactive'];
            } else {
                $mybb->user['lastvisit'] = $mybb->user['lastactive'];
            }
        } else {
            // Исправлено: преобразуем int в string для my_setcookie
            my_setcookie("mybb[lastvisit]", (string)$time);
            $mybb->user['lastvisit'] = $time;
        }

        // Исправлено: преобразуем int в string для my_setcookie
        my_setcookie("mybb[lastactive]", (string)$time);

        // Gather permissions for guest
        $mybb->usergroup = usergroup_permissions($mybbgroups);
        $mydisplaygroup = usergroup_displaygroup(1); // ✅ ИСПРАВЛЕНО: передаем число
        if (is_array($mydisplaygroup)) {
            $mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
        }

        // Update online data
        if (!defined("NO_ONLINE") && !defined('IN_UPGRADE')) {
            if (!empty($this->sid)) {
                $this->update_session($this->sid);
            } else {
                $this->create_session();
            }
        }
    }
	
	
	
	/**
     * Load a search engine spider
     */
    public function load_spider(int $spider_id): void
    {
        global $mybb, $db;

        $query = $db->simple_select("spiders", "*", "sid='{$spider_id}'");
        $spider = $db->fetch_array($query);

        $this->is_spider = true;
        $userGroup = (int)($spider['usergroup'] ?? 1); // ✅ ИСПРАВЛЕНО: приводим к int
        
        $mybb->user = [
            'usergroup' => $userGroup,
            'username' => '',
            'id' => 0,
            'displaygroup' => $userGroup,
            'additionalgroups' => '',
            'invisible' => 0
        ];

        // Gather permissions for spider
        $mybb->usergroup = usergroup_permissions($userGroup);
        $mydisplaygroup = usergroup_displaygroup($userGroup); // ✅ Теперь передаем число
        if (is_array($mydisplaygroup)) {
            $mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
        }

        // Update spider last visit
        if (($spider['lastvisit'] ?? 0) < TIMENOW - 120) {
            $db->update_query("spiders", ["lastvisit" => TIMENOW], "sid='{$spider_id}'");
        }

        // Update online data
        if (!defined("NO_ONLINE") && !defined('IN_UPGRADE')) {
            $this->sid = "bot=".$spider_id;
            $this->create_session();
        }
    }
	
	

    /**
     * Update a user session
     */
    public function update_session(string $sid, int $uid = 0): void
    {
        global $db;

        $speciallocs = $this->get_special_locations();
        
        $onlinedata = [
            'uid' => $uid,
            'time' => TIMENOW,
            'location' => $db->escape_string(substr(get_current_location(false, $this->ignore_parameters), 0, 150)),
            'useragent' => $db->escape_string(substr($this->useragent, 0, 200)),
            'location1' => (int)($speciallocs['1'] ?? 0),
            'location2' => (int)($speciallocs['2'] ?? 0),
            'nopermission' => 0
        ];

        $sid = $db->escape_string($sid);
        $db->update_query("sessions", $onlinedata, "sid='{$sid}'");
    }

    /**
     * Create a new session
     */
    public function create_session(int $uid = 0): void
    {
        global $db;

        $speciallocs = $this->get_special_locations();

        // Delete existing sessions
        if ($uid > 0) {
            $db->delete_query("sessions", "uid='{$uid}'");
        } elseif ($this->is_spider) {
            $db->delete_query("sessions", "sid='{$this->sid}'");
        }

        // Generate session ID
        if ($this->is_spider) {
            $sid = $this->sid;
        } else {
            $sid = md5(random_str(50));
        }

        $onlinedata = [
            'sid' => $sid,
            'uid' => $uid,
            'time' => TIMENOW,
            'ip' => $db->escape_binary($this->packedip),
            'location' => $db->escape_string(substr(get_current_location(false, $this->ignore_parameters), 0, 150)),
            'useragent' => $db->escape_string(substr($this->useragent, 0, 200)),
            'location1' => (int)($speciallocs['1'] ?? 0),
            'location2' => (int)($speciallocs['2'] ?? 0),
            'nopermission' => 0
        ];

        $db->replace_query("sessions", $onlinedata, "sid", false);
        $this->sid = $sid;
        $this->uid = $uid;
    }

    /**
     * Find out the special locations
     */
    public function get_special_locations(): array
    {
        global $mybb, $db;
        
        $array = ['1' => '', '2' => ''];
        $scriptName = $_SERVER['PHP_SELF'] ?? '';

        if (str_contains($scriptName, 'forumdisplay.php')) {
            $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
            if ($fid > 0 && $fid < 4294967296) {
                $array[1] = $fid;
            }
        } elseif (str_contains($scriptName, 'showthread.php')) {
            $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
            if ($tid > 0 && $tid < 4294967296) {
                $array[2] = $tid;
            } elseif (isset($mybb->input['pid']) && !empty($mybb->input['pid'])) {
                $query = $db->simple_select("tsf_posts", "tid", "pid=".$mybb->get_input('pid', MyBB::INPUT_INT), ["limit" => 1]);
                $post = $db->fetch_array($query);
                if ($post) {
                    $array[2] = $post['tid'];
                }
            }

            $thread = get_thread3333($array[2]);
            if ($thread) {
                $array[1] = $thread['fid'];
            }
        }
        
        return $array;
    }
}