<?php
declare(strict_types=1);

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Post handling class — rewritten for PHP 8.5
 */
class PostDataHandler extends DataHandler
{
    public $language_file   = 'datahandler_post';
    public $language_prefix = 'postdata';
    public $action          = '';

    public array $post_insert_data   = [];
    public array $post_update_data   = [];
    public array $thread_insert_data = [];
    public array $thread_update_data = [];
    public array $return_values      = [];

    public int  $pid        = 0;
    public int  $tid        = 0;
    public bool $first_post = false;

    // ── verify_author ─────────────────────────────────────────────────────────

    public function verify_author(): bool
    {
        global $lang;

        $post = &$this->data;

        if (!isset($post['uid'])) {
            $this->set_error('invalid_user_id');
            return false;
        }

        if ((int)$post['uid'] > 0 && empty($post['username'])) {
            $user = get_user((int)$post['uid']);
            $post['username'] = $user['username'] ?? '';
            return true;
        }

        if ((int)$post['uid'] === 0 && ($post['username'] ?? '') !== '') {
            require_once INC_PATH . '/datahandlers/user.php';
            $userhandler = new UserDataHandler();
            $userhandler->set_data(['username' => $post['username']]);

            if (!$userhandler->verify_username()) {
                $this->errors = array_merge($this->errors, $userhandler->get_errors());
                return false;
            }
            if ($userhandler->verify_username_exists()) {
                $this->errors = array_merge($this->errors, $userhandler->get_errors());
                return false;
            }
        }

        return true;
    }

    // ── verify_subject ────────────────────────────────────────────────────────

    public function verify_subject(): bool
    {
        $post    = &$this->data;
        $subject = &$post['subject'];
        $subject = trim_blank_chrs($subject);

        if ($this->method === 'update' && !empty($post['pid'])) {
            if (my_strlen($subject) === 0 && $this->first_post) {
                $this->set_error('firstpost_no_subject');
                return false;
            }
            if (my_strlen($subject) === 0) {
                $thread  = get_thread((int)$post['tid']);
                $subject = 'RE: ' . ($thread['subject'] ?? '');
            }
        } elseif ($this->action === 'post') {
            if (my_strlen($subject) === 0) {
                $thread  = get_thread((int)$post['tid']);
                $subject = 'RE: ' . ($thread['subject'] ?? '');
            }
        } else {
            if (my_strlen($subject) === 0) {
                $this->set_error('missing_subject');
                return false;
            }
        }

        $subject_length = my_strlen($subject);
        if ($this->action === 'post' && str_starts_with($subject, 'RE: ')) {
            $subject_length -= 4;
        }

        if ($subject_length > 85) {
            $this->set_error('subject_too_long', my_strlen($subject));
            return false;
        }

        return true;
    }

    // ── verify_message ────────────────────────────────────────────────────────

    public function verify_message(): bool
    {
      
		global $db, $parser, $usergroups, $maxmessagelength, $minmessagelength, $mycodemessagelength;

        $post = &$this->data;
        $post['message'] = trim_blank_chrs($post['message'] ?? '');

        if (my_strlen($post['message']) === 0) {
            $this->set_error('missing_message');
            return false;
        }


        $limit   = $maxmessagelength;
        $dblimit = 0;

        if (stripos($db->type, 'my') !== false) {
            $fields = $db->show_fields_from('posts');
            $idx    = array_search('message', array_column($fields, 'Field'), true);
            $type   = $idx !== false ? strtolower($fields[$idx]['Type']) : 'text';

            $dblimit = match ($type) {
                'longtext'   => 4294967295,
                'mediumtext' => 16777215,
                default      => 65535,
            };
        }

        if ($limit > 0 || $dblimit > 0) {
            $is_mod = is_mod($usergroups);
            if ($limit > 0 && $dblimit > 0) {
                $limit = $is_mod ? $dblimit : min($limit, $dblimit);
            } else {
                $limit = max($limit, $dblimit);
            }

            if (strlen($post['message']) > $limit && (!$is_mod || $limit === $dblimit)) {
                $this->set_error('message_too_long', [$limit, strlen($post['message'])]);
                return false;
            }
        }

        $post['fid'] ??= 0;

        if (!$mycodemessagelength) {
            $message = $parser->text_parse_message($post['message']);
            if (my_strlen($message) < $minmessagelength && $minmessagelength > 0) {
                $this->set_error('message_too_short', [$minmessagelength]);
                return false;
            }
        } elseif (my_strlen($post['message']) < $minmessagelength && $minmessagelength > 0) {
            $this->set_error('message_too_short', [$minmessagelength]);
            return false;
        }

        return true;
    }

    // ── verify_post_flooding ──────────────────────────────────────────────────

    public function verify_post_flooding(): bool
    {
        global $usergroups;

        $post          = &$this->data;
        $postfloodcheck = true;
        $postfloodsecs  = 60;

        if ($postfloodcheck && (int)($post['uid'] ?? 0) !== 0 && !$this->admin_override) {
            if ($this->verify_post_merge(true) !== true) {
                return true;
            }

            $user   = get_user((int)$post['uid']);
            $is_mod = is_mod($usergroups);

            if ((TIMENOW - (int)($user['lastpost'] ?? 0)) <= $postfloodsecs && !$is_mod) {
                $time_to_wait = ($postfloodsecs - (TIMENOW - (int)$user['lastpost'])) + 1;
                if ($time_to_wait === 1) {
                    $this->set_error('post_flooding_one_second');
                } else {
                    $this->set_error('post_flooding', [$time_to_wait]);
                }
                return false;
            }
        }

        return true;
    }

    // ── verify_post_merge ─────────────────────────────────────────────────────

    public function verify_post_merge(bool $simple_mode = false): array|bool
    {
       
		global $db, $session, $postmergemins, $postmergesep, $postmergefignore, $postmergeuignore;

        $post = &$this->data;

        if (empty($post['tid'])) {
            return true;
        }

        if (empty($postmergemins)) {
            return true;
        }

        if (trim($postmergesep) === '') {
            $postmergesep = '[hr]';
        }

        // Раньше здесь был is_member($postmergeuignore, (int)$post['uid']) -
        // инлайним ту же логику напрямую, без вызова отдельной функции.
        $merge_ignore_groups = array_map('intval', explode(',', $postmergeuignore));
        $post_author         = get_user((int)$post['uid']);
        $author_memberships  = array_map('intval', explode(',', (string)($post_author['additionalgroups'] ?? '')));
        $author_memberships[] = (int)($post_author['usergroup'] ?? 0);

        if (array_intersect($merge_ignore_groups, $author_memberships)) {
            return true;
        }

        $query  = $db->sql_query_prepared(
            "SELECT lastpost, fid FROM threads WHERE lastposteruid = ? AND tid = ? LIMIT 1",
            [(int)$post['uid'], (int)$post['tid']]
        );
        $thread = $query ? $db->fetch_array($query) : null;

        if (!$thread || ((int)$postmergemins !== 0 && (TIMENOW - (int)$thread['lastpost']) > ($postmergemins * 60))) {
            return true;
        }

        if ($postmergefignore === -1) {
            return true;
        }

        if ($postmergefignore !== '') {
            $fids = array_map('intval', explode(',', (string)$postmergefignore));
            if (in_array((int)$thread['fid'], $fids, true)) {
                return true;
            }
        }

        if ($simple_mode) {
            return false;
        }

        if (!empty($post['uid'])) {
            $user_check_sql    = 'uid = ?';
            $user_check_params = [(int)$post['uid']];
        } else {
            $user_check_sql    = 'ipaddress = ?';
            $user_check_params = [$session->packedip];
        }

        $query = $db->sql_query_prepared(
            "SELECT pid, message, visible FROM posts WHERE {$user_check_sql} AND tid = ? AND dateline = ? ORDER BY pid DESC LIMIT 1",
            [...$user_check_params, (int)$post['tid'], (int)$thread['lastpost']]
        );
        return $query ? $db->fetch_array($query) : false;
    }

    // ── verify_reply_to ───────────────────────────────────────────────────────

    public function verify_reply_to(): bool
    {
        global $db;

        $post = &$this->data;

        if (!empty($post['replyto'])) {
            $query      = $db->sql_query_prepared("SELECT pid FROM posts WHERE pid = ?", [(int)$post['replyto']]);
            $valid_post = $query ? $db->fetch_array($query) : null;
            if (!empty($valid_post['pid'])) {
                return true;
            }
            $post['replyto'] = 0;
        }

        $query = $db->sql_query_prepared(
            "SELECT pid FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1",
            [(int)$post['tid']]
        );
        $reply_to        = $query ? $db->fetch_array($query) : null;
        $post['replyto'] = (int)($reply_to['pid'] ?? 0);

        return true;
    }

    // ── verify_dateline ───────────────────────────────────────────────────────

    public function verify_dateline(): void
    {
        $dateline = &$this->data['dateline'];
        if ($dateline < 0 || !is_numeric($dateline)) {
            $dateline = TIMENOW;
        }
    }

    // ── validate_post ─────────────────────────────────────────────────────────

    public function validate_post(): bool
    {
        global $db, $plugins;

        $post = &$this->data;
        $this->action = 'post';

        if ($this->method !== 'update' && empty($post['savedraft'])) {
            $this->verify_post_flooding();
        }

        if ($this->method === 'update') {
            if (empty($post['tid'])) {
                $query       = $db->sql_query_prepared("SELECT tid FROM posts WHERE pid = ?", [(int)$post['pid']]);
                $post['tid'] = $query ? (int)$db->fetch_field($query, 'tid') : 0;
            }

            $query       = $db->sql_query_prepared(
                "SELECT pid FROM posts WHERE tid = ? ORDER BY dateline, pid LIMIT 1",
                [(int)$post['tid']]
            );
            $first_check = $query ? $db->fetch_array($query) : null;
            if ((int)($first_check['pid'] ?? 0) === (int)($post['pid'] ?? 0)) {
                $this->first_post = true;
            }
        }

        if ($this->method === 'insert' || array_key_exists('uid',      $post)) { $this->verify_author();   }
        if ($this->method === 'insert' || array_key_exists('subject',  $post)) { $this->verify_subject();  }
        if ($this->method === 'insert' || array_key_exists('message',  $post)) { $this->verify_message();  }
        if ($this->method === 'insert' || array_key_exists('dateline', $post)) { $this->verify_dateline(); }
        if ($this->method === 'insert' || array_key_exists('replyto',  $post)) { $this->verify_reply_to(); }

        $plugins->run_hooks('datahandler_post_validate_post', $this);

        $this->set_validated(true);
        return count($this->get_errors()) === 0;
    }

    // ── insert_post ───────────────────────────────────────────────────────────

    public function insert_post(): array
    {
        global $db, $mybb, $plugins, $cache, $lang, $parser, $usergroups, $BASEURL, $CURUSER, $SITENAME, $kpscomment;

        $post = &$this->data;

        if (!$this->get_validated()) {
            die('The post needs to be validated before inserting it into the DB.');
        }
        if (count($this->get_errors()) > 0) {
            die('The post is not valid.');
        }

        $thread = get_thread((int)$post['tid']);
        $closed = $thread['closed'];
        $is_mod = is_mod($usergroups);

        // Draft
        if (!empty($post['savedraft'])) {
            $visible = -2;
        } else {
            // Subscription
            if ((int)($post['uid'] ?? 0) > 0) {
                require_once INC_PATH . '/functions_user.php';
                if (!empty($post['options']['subscriptionmethod'])) {
                    $notification = match ($post['options']['subscriptionmethod']) {
                        'pm'    => 2,
                        'email' => 1,
                        default => 0,
                    };
                    add_subscribed_thread((int)$post['tid'], $notification, (int)$post['uid']);
                }
            }

            // Mod options
            if ($is_mod && isset($post['modoptions'])) {
                $modoptions        = $post['modoptions'];
                $modlogdata        = ['fid' => $thread['fid'], 'tid' => $thread['tid']];
                $modoptions_update = [];

                if (!empty($modoptions['closethread']) && $thread['closed'] != 1) {
                    $modoptions_update['closed'] = $closed = 1;
                    log_moderator_action($modlogdata, 'Thread Closed');
                }
                if (empty($modoptions['closethread']) && $thread['closed'] == 1) {
                    $modoptions_update['closed'] = $closed = 0;
                    log_moderator_action($modlogdata, 'Thread Opened');
                }
                if (!empty($modoptions['stickthread']) && $thread['sticky'] != 1) {
                    $modoptions_update['sticky'] = 1;
                    log_moderator_action($modlogdata, 'Thread Stuck');
                }
                if (empty($modoptions['stickthread']) && $thread['sticky'] == 1) {
                    $modoptions_update['sticky'] = 0;
                    log_moderator_action($modlogdata, 'Thread Unstuck');
                }
                if (!empty($modoptions_update)) {
                    $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($modoptions_update)));
                    $params = array_values($modoptions_update);
                    $params[] = $thread['tid'];
                    $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);
                }
            }

            $forumpermissions = forum_permissions((int)$post['fid'], (int)($post['uid'] ?? 0));
            $visible = ($forumpermissions['modposts'] == 1 && !$is_mod) ? 0 : 1;

            if ((int)($CURUSER['id'] ?? 0) === (int)($post['uid'] ?? 0) && ($CURUSER['moderateposts'] ?? 0) == 1) {
                $visible = 0;
            }
        }

        $post['pid'] = (int)($post['pid'] ?? 0);
        $post['uid'] = (int)($post['uid'] ?? 0);

        $draft_check = false;
        if ($post['pid'] > 0) {
            $query       = $db->sql_query_prepared(
                "SELECT tid FROM posts WHERE pid = ? AND uid = ? AND visible = '-2'",
                [$post['pid'], $post['uid']]
            );
            $draft_check = $query ? $db->fetch_field($query, 'tid') : false;
        }

        // Post merge
        if ($this->method !== 'update' && $visible == 1) {
            $double_post = $this->verify_post_merge();

            if ($double_post !== true && ($double_post['visible'] ?? null) == $visible) {
                global $postmergesep;
                $_message        = $post['message'];
                $sep             = isset($postmergesep) && trim((string)$postmergesep) !== '' ? (string)$postmergesep : '[hr]';
                $post['message'] = $double_post['message'] .= "\n{$sep}\n" . $post['message'];

                if ($this->validate_post()) {
                    $this->pid = (int)$double_post['pid'];

                    $db->sql_query_prepared(
                        "UPDATE posts SET message = ?, edituid = ?, edittime = ? WHERE pid = ?",
                        [$double_post['message'], $post['uid'], TIMENOW, $double_post['pid']]
                    );

                    if ($draft_check) {
                        $db->sql_query_prepared("DELETE FROM posts WHERE pid = ?", [$post['pid']]);
                    }

                    if (!empty($post['posthash'])) {
                        $query              = $db->sql_query_prepared(
                            "SELECT COUNT(aid) AS attachmentcount FROM attachments WHERE pid = '0' AND visible = '1' AND posthash = ?",
                            [$post['posthash']]
                        );
                        $attachmentcount    = $query ? (int)$db->fetch_field($query, 'attachmentcount') : 0;
                        if ($attachmentcount > 0) {
                            update_thread_counters((int)$post['tid'], ['attachmentcount' => "+{$attachmentcount}"]);
                        }
                        $db->sql_query_prepared(
                            "UPDATE attachments SET pid = ?, posthash = '' WHERE posthash = ? AND pid = '0'",
                            [$double_post['pid'], $post['posthash']]
                        );
                    }

                    $this->return_values = ['pid' => $double_post['pid'], 'visible' => $visible, 'merge' => true];
                    $plugins->run_hooks('datahandler_post_insert_merge', $this);
                    return $this->return_values;
                }

                $post['message'] = $_message;
            }
        }

        // Update user lastpost
        if ($visible == 1) {
            $now       = TIMENOW;
            $set_parts = ['lastpost = ?'];
            $params    = [$now];
            if ($thread['visible'] == 1) {
                $set_parts[] = 'postnum = postnum+1';
            }
            $params[] = $post['uid'];
            $db->sql_query_prepared("UPDATE users SET " . implode(', ', $set_parts) . " WHERE id = ? LIMIT 1", $params);
        }

        // Draft update or new insert
        if ($draft_check) {
            $this->post_update_data = [
                'subject'   => $post['subject'] ?? '',
                'uid'       => $post['uid'],
                'username'  => $post['username'] ?? '',
                'dateline'  => (int)($post['dateline'] ?? TIMENOW),
                'message'   => $post['message'],
                'ipaddress' => $post['ipaddress'] ?? '',
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_post', $this);

            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($this->post_update_data)));
            $params = array_values($this->post_update_data);
            $params[] = $post['pid'];
            $db->sql_query_prepared("UPDATE posts SET {$set} WHERE pid = ?", $params);
            $this->pid = $post['pid'];
        } else {
            $this->post_insert_data = [
                'tid'       => (int)$post['tid'],
                'replyto'   => (int)($post['replyto'] ?? 0),
                'fid'       => (int)$post['fid'],
                'subject'   => $post['subject'] ?? '',
                'uid'       => $post['uid'],
                'username'  => $post['username'] ?? '',
                'dateline'  => (int)($post['dateline'] ?? TIMENOW),
                'message'   => $post['message'],
                'ipaddress' => $post['ipaddress'] ?? '',
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_post', $this);

            $columns      = array_keys($this->post_insert_data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO posts (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($this->post_insert_data)
            );
            $this->pid = (int)$db->insert_id();

            // Attach uploaded files
            $this->attachFileIds($this->pid);
        }

        // Posthash attachments
        if (!empty($post['posthash'])) {
            $db->sql_query_prepared(
                "UPDATE attachments SET pid = ?, posthash = '' WHERE posthash = ? AND pid = '0'",
                [$this->pid, $post['posthash']]
            );
        }

        $thread_update = [];

        if ($visible == 1 && $thread['visible'] == 1) {
            require_once INC_PATH . '/class_parser.php';
            $parser      = new Postparser();
            $done_users  = [];
            $subject     = $parser->parse_badwords($thread['subject']);
            $parser_opts = ['me_username' => $post['username'], 'filter_badwords' => 1];
            $excerpt     = $parser->text_parse_message($post['message'], $parser_opts);

            $query = $db->sql_query_prepared(
                "SELECT u.username, u.email, u.id, u.loginkey, u.added, s.notification
                 FROM threadsubscriptions s
                 LEFT JOIN users u ON (u.id = s.uid)
                 WHERE (s.notification = '1' OR s.notification = '2')
                   AND s.tid = ?
                   AND s.uid != ?
                   AND u.lastactive > ?",
                [(int)$post['tid'], (int)$post['uid'], (int)$thread['lastpost']]
            );

            while ($member = $db->fetch_array($query)) {
                if (isset($done_users[$member['id']])) continue;
                $done_users[$member['id']] = 1;

                $fp = forum_permissions((int)$thread['fid'], (int)$member['id']);
                if ($fp['canview'] == 0 || $fp['canviewthreads'] == 0) continue;

                if ($member['notification'] == 1) {
                    $lang->load('member');
                    $emailsubject = sprintf($lang->member['emailsubject_subscription'] ?? 'New Reply to {1}', $subject);
                    $emailmessage = sprintf(
                        $lang->member['email_subscription'] ?? '',
                        $member['username'], $post['username'] ?: 'guest',
                        $SITENAME, $subject, $excerpt,
                        $BASEURL, str_replace('&amp;', '&', get_thread_link((int)$thread['tid'], 0, 'newpost')),
                        $thread['tid']
                    );
                    $db->sql_query_prepared(
                        "INSERT INTO mailqueue (`mailto`,`mailfrom`,`subject`,`message`,`headers`) VALUES (?,?,?,?,?)",
                        [$member['email'], '', $emailsubject, $emailmessage, '']
                    );
                    $queued_email = true;
                } elseif ($member['notification'] == 2) {
                    require_once INC_PATH . '/functions_pm.php';
                    send_pm([
                        'subject' => sprintf($lang->tsf_forums['pmsubject_subscription'] ?? '', $subject),
                        'message' => sprintf(
                            $lang->tsf_forums['pm_subscription'] ?? '',
                            $member['username'], $post['username'] ?: 'guest',
                            $subject, $excerpt,
                            $BASEURL, str_replace('&amp;', '&', get_thread_link((int)$thread['tid'], 0, 'newpost')),
                            $thread['tid']
                        ),
                        'touid'          => (int)$member['id'],
                        'sender' => ['uid' => -1],
                    ], -1, true);
                }
            }

            if (!empty($queued_email)) {
                $cache->update_mailqueue();
            }

            $thread_update = ['replies' => '+1'];
            update_last_post((int)$post['tid']);
            update_forum_counters((int)$post['fid'], ['posts' => '+1']);
            update_forum_lastpost((int)$thread['fid']);
			
			kps('+', $kpscomment, (int)$post['uid']);

        } elseif ($visible == 0) {
            $thread_update = ['unapprovedposts' => '+1'];
            update_thread_counters((int)$post['tid'], ['unapprovedposts' => '+1']);
            update_forum_counters((int)$post['fid'], ['unapprovedposts' => '+1']);
        } elseif ($thread['visible'] == 0) {
            $thread_update = ['replies' => '+1'];
            update_forum_counters((int)$post['fid'], ['unapprovedposts' => '+1']);
        } elseif ($thread['visible'] == -1) {
            $thread_update = ['replies' => '+1'];
            update_forum_counters((int)$post['fid'], ['deletedposts' => '+1']);
        }

        if ($visible == 1 && $thread['visible'] != 1) {
            update_last_post((int)$post['tid']);
        }

        $query           = $db->sql_query_prepared("SELECT COUNT(aid) AS attachmentcount FROM attachments WHERE pid = ? AND visible = '1'", [$this->pid]);
        $attachmentcount = $query ? (int)$db->fetch_field($query, 'attachmentcount') : 0;
        if ($attachmentcount > 0) {
            $thread_update['attachmentcount'] = "+{$attachmentcount}";
        }
        update_thread_counters((int)$post['tid'], $thread_update);

        $this->return_values = ['pid' => $this->pid, 'visible' => $visible, 'closed' => $closed];
        $plugins->run_hooks('datahandler_post_insert_post_end', $this);
        return $this->return_values;
    }

    // ── validate_thread ───────────────────────────────────────────────────────

    public function validate_thread(): bool
    {
        global $plugins;

        $thread = &$this->data;

        if (empty($thread['savedraft'])) {
            $this->verify_post_flooding();
        }

        if ($this->method === 'insert' || array_key_exists('uid',      $thread)) { $this->verify_author();   }
        if ($this->method === 'insert' || array_key_exists('subject',  $thread)) { $this->verify_subject();  }
        if ($this->method === 'insert' || array_key_exists('message',  $thread)) { $this->verify_message();  }
        if ($this->method === 'insert' || array_key_exists('dateline', $thread)) { $this->verify_dateline(); }

        $plugins->run_hooks('datahandler_post_validate_thread', $this);

        $this->set_validated(true);
        return count($this->get_errors()) === 0;
    }

    // ── insert_thread ─────────────────────────────────────────────────────────

    public function insert_thread(): array
    {
        global $db, $plugins, $cache, $lang, $usergroups, $CURUSER, $SITENAME, $BASEURL, $kpscomment;

        if (!$this->get_validated()) {
            die('The thread needs to be validated before inserting it into the DB.');
        }
        if (count($this->get_errors()) > 0) {
            die('The thread is not valid.');
        }

        $thread = &$this->data;
        $query  = $db->sql_query_prepared("SELECT * FROM forums WHERE fid = ?", [$thread['fid']]);
        $forum  = $query ? $db->fetch_array($query) : null;
        $is_mod = is_mod($usergroups);

        if (!empty($thread['savedraft'])) {
            $visible = -2;
        } else {
            $forumpermissions = forum_permissions((int)$thread['fid'], (int)($thread['uid'] ?? 0));
            $visible = ($forumpermissions['modthreads'] == 1 && !$is_mod) ? 0 : 1;

            if ((int)($CURUSER['id'] ?? 0) === (int)($thread['uid'] ?? 0) && ($CURUSER['moderateposts'] ?? 0) == 1) {
                $visible = 0;
            }
        }

        if (!empty($thread['pid']) && empty($thread['tid'])) {
            $query          = $db->sql_query_prepared("SELECT tid FROM posts WHERE pid = ?", [$thread['pid']]);
            $thread['tid']  = $query ? (int)$db->fetch_field($query, 'tid') : 0;
        }

        $draft_check = false;
        if (!empty($thread['pid']) && (int)$thread['pid'] > 0) {
            $query       = $db->sql_query_prepared(
                "SELECT pid FROM posts WHERE pid = ? AND uid = ? AND visible = '-2'",
                [$thread['pid'], $thread['uid']]
            );
            $draft_check = $query ? $db->fetch_field($query, 'pid') : false;
        }

        if ($draft_check) {
            $this->thread_insert_data = [
                'subject'    => $thread['subject'] ?? '',
                'username'   => $thread['username'] ?? '',
                'dateline'   => (int)($thread['dateline'] ?? TIMENOW),
                'lastpost'   => (int)($thread['dateline'] ?? TIMENOW),
                'lastposter' => $thread['username'] ?? '',
                'visible'    => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread', $this);

            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($this->thread_insert_data)));
            $params = array_values($this->thread_insert_data);
            $params[] = $thread['tid'];
            $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);

            $this->post_insert_data = [
                'subject'   => $thread['subject'] ?? '',
                'username'  => $thread['username'] ?? '',
                'dateline'  => (int)($thread['dateline'] ?? TIMENOW),
                'message'   => $thread['message'] ?? '',
                'ipaddress' => my_inet_pton(get_ip()),
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread_post', $this);

            $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($this->post_insert_data)));
            $params = array_values($this->post_insert_data);
            $params[] = $thread['pid'];
            $db->sql_query_prepared("UPDATE posts SET {$set} WHERE pid = ?", $params);
            $this->tid = (int)$thread['tid'];
            $this->pid = (int)$thread['pid'];
        } else {
            $this->thread_insert_data = [
                'fid'          => (int)$thread['fid'],
                'subject'      => $thread['subject'] ?? '',
                'uid'          => (int)($thread['uid'] ?? 0),
                'username'     => $thread['username'] ?? '',
                'dateline'     => (int)($thread['dateline'] ?? TIMENOW),
                'lastpost'     => (int)($thread['dateline'] ?? TIMENOW),
                'lastposter'   => $thread['username'] ?? '',
                'lastposteruid'=> (int)($thread['uid'] ?? 0),
                'views'        => 0,
                'replies'      => 0,
                'visible'      => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread', $this);

            $columns      = array_keys($this->thread_insert_data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO threads (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($this->thread_insert_data)
            );
            $this->tid = (int)$db->insert_id();

            $this->post_insert_data = [
                'tid'       => $this->tid,
                'fid'       => (int)$thread['fid'],
                'subject'   => $thread['subject'] ?? '',
                'uid'       => (int)($thread['uid'] ?? 0),
                'username'  => $thread['username'] ?? '',
                'dateline'  => (int)($thread['dateline'] ?? TIMENOW),
                'message'   => $thread['message'] ?? '',
                'ipaddress' => my_inet_pton(get_ip()),
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread_post', $this);

            $columns      = array_keys($this->post_insert_data);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $db->sql_query_prepared(
                "INSERT INTO posts (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})",
                array_values($this->post_insert_data)
            );
            $this->pid = (int)$db->insert_id();

            $this->attachFileIds($this->pid);

            $db->sql_query_prepared("UPDATE threads SET firstpost = ? WHERE tid = ?", [$this->pid, $this->tid]);
			
            if ($visible === 1) {
               kps('+', $kpscomment, (int)($thread['uid'] ?? 0));
            }
			
			
			
        }

        if (empty($thread['savedraft'])) {
            // Subscription
            if (!empty($thread['options']['subscriptionmethod']) && (int)($thread['uid'] ?? 0) > 0) {
                $notification = match ($thread['options']['subscriptionmethod']) {
                    'pm'    => 2,
                    'email' => 1,
                    default => 0,
                };
                require_once INC_PATH . '/functions_user.php';
                add_subscribed_thread($this->tid, $notification, (int)$thread['uid']);
            }

            // Mod options
            if ($is_mod && isset($thread['modoptions'])) {
                $modoptions        = $thread['modoptions'];
                $modlogdata        = ['fid' => $thread['fid'], 'tid' => $thread['tid'] ?? $this->tid];
                $modoptions_update = [];

                if (!empty($modoptions['closethread'])) {
                    $modoptions_update['closed'] = 1;
                    log_moderator_action($modlogdata, 'Thread Closed');
                }
                if (!empty($modoptions['stickthread'])) {
                    $modoptions_update['sticky'] = 1;
                    log_moderator_action($modlogdata, 'Thread Stuck');
                }
                if (!empty($modoptions_update)) {
                    $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($modoptions_update)));
                    $params = array_values($modoptions_update);
                    $params[] = $this->tid;
                    $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);
                }
            }

            if ($visible == 1) {
                if ((int)($thread['uid'] ?? 0) > 0) {
                    $user      = get_user((int)$thread['uid']);
                    $set_parts = [];
                    $params    = [];

                    if ((int)($thread['dateline'] ?? 0) > (int)($user['lastpost'] ?? 0)) {
                        $set_parts[] = 'lastpost = ?';
                        $params[]    = (int)$thread['dateline'];
                    }
                    if ($forum['usepostcounts'] != 0)   { $set_parts[] = 'postnum = postnum+1'; }
                    if ($forum['usethreadcounts'] != 0) { $set_parts[] = 'threadnum = threadnum+1'; }

                    if (!empty($set_parts)) {
                        $params[] = $thread['uid'];
                        $db->sql_query_prepared("UPDATE users SET " . implode(', ', $set_parts) . " WHERE id = ? LIMIT 1", $params);
                    }
                }

                // Forum subscriptions
                $subscribeexcerpt = 100;
                $excerpt          = my_substr($thread['message'] ?? '', 0, $subscribeexcerpt);

                $query = $db->sql_query_prepared(
                    "SELECT u.username, u.email, u.id, u.added
                     FROM forumsubscriptions fs
                     LEFT JOIN users u ON (u.id = fs.uid)
                     LEFT JOIN usergroups g ON (g.gid = u.usergroup)
                     WHERE fs.fid = ?
                       AND fs.uid != ?
                       AND u.lastactive > ?
                       AND g.isbannedgroup != 1",
                    [(int)$thread['fid'], (int)($thread['uid'] ?? 0), (int)($forum['lastpost'] ?? 0)]
                );

                $done_users = [];
                while ($member = $db->fetch_array($query)) {
                    if (!empty($done_users[$member['id']])) continue;
                    $done_users[$member['id']] = 1;

                    $fp = forum_permissions((int)$thread['fid'], (int)$member['id']);
                    if ($fp['canview'] == 0 || $fp['canviewthreads'] == 0) continue;

                    $emailsubject = sprintf($lang->emailsubject_forumsubscription ?? '', $forum['name'] ?? '');
                    $emailmessage = sprintf(
                        $lang->email_forumsubscription ?? '',
                        $member['username'], $thread['username'] ?: 'guest',
                        $forum['name'] ?? '', $SITENAME, $thread['subject'] ?? '',
                        $excerpt, $BASEURL, get_thread_link($this->tid), $thread['fid']
                    );

                    $db->sql_query_prepared(
                        "INSERT INTO mailqueue (`mailto`,`mailfrom`,`subject`,`message`,`headers`) VALUES (?,?,?,?,?)",
                        [$member['email'], '', $emailsubject, $emailmessage, '']
                    );
                    $queued_email = true;
                }

                if (!empty($queued_email)) {
                    $cache->update_mailqueue();
                }
            }
        }

        // Posthash
        if (!empty($thread['posthash'])) {
            $db->sql_query_prepared(
                "UPDATE attachments SET pid = ?, posthash = '' WHERE posthash = ? AND pid = '0'",
                [$this->pid, $thread['posthash']]
            );
        }

        if ($visible == 1) {
            update_last_post($this->tid);
            update_forum_counters((int)$thread['fid'], ['threads' => '+1', 'posts' => '+1']);
            update_forum_lastpost((int)$thread['fid']);
        } elseif ($visible == 0) {
            update_forum_counters((int)$thread['fid'], ['unapprovedthreads' => '+1', 'unapprovedposts' => '+1']);
        }

        $query           = $db->sql_query_prepared("SELECT COUNT(aid) AS attachmentcount FROM attachments WHERE pid = ? AND visible = '1'", [$this->pid]);
        $attachmentcount = $query ? (int)$db->fetch_field($query, 'attachmentcount') : 0;
        if ($attachmentcount > 0) {
            update_thread_counters($this->tid, ['attachmentcount' => "+{$attachmentcount}"]);
        }

        $this->return_values = ['pid' => $this->pid, 'tid' => $this->tid, 'visible' => $visible];
        $plugins->run_hooks('datahandler_post_insert_thread_end', $this);
        return $this->return_values;
    }

    // ── update_post ───────────────────────────────────────────────────────────

    public function update_post(): array
    {
        global $db, $plugins, $usergroups, $CURUSER;

        if (!$this->get_validated()) {
            die('The post needs to be validated before inserting it into the DB.');
        }
        if (count($this->get_errors()) > 0) {
            die('The post is not valid.');
        }

        $post = &$this->data;
        $post['pid'] = (int)$post['pid'];

        $existing_post = get_post($post['pid']);
        $post['tid']   = (int)$existing_post['tid'];
        $post['fid']   = (int)$existing_post['fid'];

        $uid    = (int)($post['uid'] ?? 0);
        $is_mod = is_mod($usergroups);

        $visible = match (true) {
            (int)$existing_post['visible'] === 0  => 0,
            (int)$existing_post['visible'] === -1 => -1,
            ($CURUSER['moderateposts'] ?? 0) == 1 && !$is_mod => (function() use ($db, $post) {
                require_once INC_PATH . '/class_moderation.php';
                (new Moderation())->unapprove_posts([$post['pid']]);
                return 0;
            })(),
            default => 1,
        };

        if ($this->first_post) {
            $this->tid = $post['tid'];

            if (isset($post['subject'])) {
                $this->thread_update_data['subject'] = $post['subject'];
            }

            if (!empty($this->thread_update_data)) {
                $plugins->run_hooks('datahandler_post_update_thread', $this);

                $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($this->thread_update_data)));
                $params = array_values($this->thread_update_data);
                $params[] = $post['tid'];
                $db->sql_query_prepared("UPDATE threads SET {$set} WHERE tid = ?", $params);
            }

            if (isset($post['subject'])) {
                $query = $db->sql_query_prepared("SELECT tid, closed FROM threads WHERE closed = ?", ["moved|{$this->tid}"]);
                if ($query && $db->num_rows($query) > 0) {
                    while ($result = $db->fetch_array($query)) {
                        $db->sql_query_prepared("UPDATE threads SET subject = ? WHERE tid = ?", [$post['subject'], (int)$result['tid']]);
                    }
                }
            }
        }

        $this->pid = $post['pid'];

        if (isset($post['subject'])) { $this->post_update_data['subject'] = $post['subject']; }
        if (isset($post['message'])) { $this->post_update_data['message'] = $post['message']; }

        if (isset($post['editreason']) && trim($post['editreason']) !== '') {
            $this->post_update_data['editreason'] = trim($post['editreason']);
        } else {
            $this->post_update_data['editreason'] = '';
        }

        $showeditedby = true;
        if ($showeditedby) {
            $this->post_update_data['edituid']  = (int)($post['edit_uid'] ?? 0);
            $this->post_update_data['edittime'] = TIMENOW;
        }

        $plugins->run_hooks('datahandler_post_update', $this);

        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($this->post_update_data)));
        $params = array_values($this->post_update_data);
        $params[] = $post['pid'];
        $db->sql_query_prepared("UPDATE posts SET {$set} WHERE pid = ?", $params);

        $this->attachFileIds($post['pid']);

        // Subscription
        if (!empty($post['options']['subscriptionmethod']) && $uid > 0) {
            $notification = match ($post['options']['subscriptionmethod']) {
                'pm'    => 2,
                'email' => 1,
                default => 0,
            };
            require_once INC_PATH . '/functions_user.php';
            add_subscribed_thread((int)$post['tid'], $notification, $uid);
        } else {
            $db->sql_query_prepared("DELETE FROM threadsubscriptions WHERE uid = ? AND tid = ?", [$uid, $post['tid']]);
        }

        update_forum_lastpost((int)$post['fid']);
        update_last_post((int)$post['tid']);

        $this->return_values = ['visible' => $visible, 'first_post' => $this->first_post];
        $plugins->run_hooks('datahandler_post_update_end', $this);
        return $this->return_values;
    }

    // ── Helper: attach uploaded file IDs ─────────────────────────────────────

    private function attachFileIds(int $pid): void
    {
        global $db;

        if (empty($_POST['file_ids'])) {
            return;
        }

        $file_ids = array_values(array_filter(array_map('intval', (array)$_POST['file_ids'])));
        if (empty($file_ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
        $db->sql_query_prepared(
            "UPDATE comment_files SET post_id = ? WHERE id IN ({$placeholders})",
            [$pid, ...$file_ids]
        );
    }
}