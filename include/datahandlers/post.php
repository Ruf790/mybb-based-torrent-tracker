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
        global $db, $parser, $usergroups;

        $post = &$this->data;
        $post['message'] = trim_blank_chrs($post['message'] ?? '');

        if (my_strlen($post['message']) === 0) {
            $this->set_error('missing_message');
            return false;
        }

        $maxmessagelength    = 65535;
        $minmessagelength    = 5;
        $mycodemessagelength = true;

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
        global $db, $session;

        $post = &$this->data;

        if (empty($post['tid'])) {
            return true;
        }

        $postmergemins  = 60;
        $postmergesep   = '[hr]';
        $postmergefignore = '';
        $postmergeuignore = '6,7,8';

        if (empty($postmergemins)) {
            return true;
        }

        if (trim($postmergesep) === '') {
            $postmergesep = '[hr]';
        }

        if (is_member($postmergeuignore, (int)$post['uid'])) {
            return true;
        }

        $query  = $db->simple_select('threads', 'lastpost, fid', "lastposteruid='" . (int)$post['uid'] . "' AND tid='" . (int)$post['tid'] . "'", ['limit' => 1]);
        $thread = $db->fetch_array($query);

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

        $user_check = !empty($post['uid'])
            ? "uid='" . (int)$post['uid'] . "'"
            : 'ipaddress=' . $db->escape_binary($session->packedip);

        $query = $db->simple_select('posts', 'pid, message, visible', "{$user_check} AND tid='" . (int)$post['tid'] . "' AND dateline='" . (int)$thread['lastpost'] . "'", ['order_by' => 'pid', 'order_dir' => 'DESC', 'limit' => 1]);
        return $db->fetch_array($query);
    }

    // ── verify_reply_to ───────────────────────────────────────────────────────

    public function verify_reply_to(): bool
    {
        global $db;

        $post = &$this->data;

        if (!empty($post['replyto'])) {
            $query      = $db->simple_select('posts', 'pid', "pid='" . (int)$post['replyto'] . "'");
            $valid_post = $db->fetch_array($query);
            if (!empty($valid_post['pid'])) {
                return true;
            }
            $post['replyto'] = 0;
        }

        $query          = $db->simple_select('posts', 'pid', "tid='" . (int)$post['tid'] . "'", ['limit_start' => 0, 'limit' => 1, 'order_by' => 'dateline, pid']);
        $reply_to       = $db->fetch_array($query);
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
                $query       = $db->simple_select('posts', 'tid', "pid='" . (int)$post['pid'] . "'");
                $post['tid'] = (int)$db->fetch_field($query, 'tid');
            }

            $query       = $db->simple_select('posts', 'pid', "tid='" . (int)$post['tid'] . "'", ['limit' => 1, 'limit_start' => 0, 'order_by' => 'dateline, pid']);
            $first_check = $db->fetch_array($query);
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
                    $db->update_query('threads', $modoptions_update, "tid='{$thread['tid']}'");
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
            $query       = $db->simple_select('posts', 'tid', "pid='{$post['pid']}' AND uid='{$post['uid']}' AND visible='-2'");
            $draft_check = $db->fetch_field($query, 'tid');
        }

        // Post merge
        if ($this->method !== 'update' && $visible == 1) {
            $double_post = $this->verify_post_merge();

            if ($double_post !== true && ($double_post['visible'] ?? null) == $visible) {
                $_message        = $post['message'];
                $postmergesep    = '[hr]';
                $post['message'] = $double_post['message'] .= "\n{$postmergesep}\n" . $post['message'];

                if ($this->validate_post()) {
                    $this->pid = (int)$double_post['pid'];

                    $db->update_query('posts', [
                        'message'  => $db->escape_string($double_post['message']),
                        'edituid'  => $post['uid'],
                        'edittime' => TIMENOW,
                    ], "pid='{$double_post['pid']}'");

                    if ($draft_check) {
                        $db->delete_query('posts', "pid='{$post['pid']}'");
                    }

                    if (!empty($post['posthash'])) {
                        $post['posthash']   = $db->escape_string($post['posthash']);
                        $query              = $db->simple_select('attachments', 'COUNT(aid) AS attachmentcount', "pid='0' AND visible='1' AND posthash='{$post['posthash']}'");
                        $attachmentcount    = (int)$db->fetch_field($query, 'attachmentcount');
                        if ($attachmentcount > 0) {
                            update_thread_counters((int)$post['tid'], ['attachmentcount' => "+{$attachmentcount}"]);
                        }
                        $db->update_query('attachments', ['pid' => $double_post['pid'], 'posthash' => ''], "posthash='{$post['posthash']}' AND pid='0'");
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
            $now          = TIMENOW;
            $update_array = ['lastpost' => "'{$now}'"];
            if ($thread['visible'] == 1) {
                $update_array['postnum'] = 'postnum+1';
            }
            $db->update_query('users', $update_array, "id='{$post['uid']}'", '1', true);
        }

        // Draft update or new insert
        if ($draft_check) {
            $this->post_update_data = [
                'subject'   => $db->escape_string($post['subject'] ?? ''),
                'uid'       => $post['uid'],
                'username'  => $db->escape_string($post['username'] ?? ''),
                'dateline'  => (int)($post['dateline'] ?? TIMENOW),
                'message'   => $db->escape_string($post['message']),
                'ipaddress' => $db->escape_binary($post['ipaddress'] ?? ''),
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_post', $this);
            $db->update_query('posts', $this->post_update_data, "pid='{$post['pid']}'");
            $this->pid = $post['pid'];
        } else {
            $this->post_insert_data = [
                'tid'       => (int)$post['tid'],
                'replyto'   => (int)($post['replyto'] ?? 0),
                'fid'       => (int)$post['fid'],
                'subject'   => $db->escape_string($post['subject'] ?? ''),
                'uid'       => $post['uid'],
                'username'  => $db->escape_string($post['username'] ?? ''),
                'dateline'  => (int)($post['dateline'] ?? TIMENOW),
                'message'   => $db->escape_string($post['message']),
                'ipaddress' => $db->escape_binary($post['ipaddress'] ?? ''),
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_post', $this);
            $this->pid = (int)$db->insert_query('posts', $this->post_insert_data);

            // Attach uploaded files
            $this->attachFileIds($this->pid);
        }

        // Posthash attachments
        if (!empty($post['posthash'])) {
            $post['posthash'] = $db->escape_string($post['posthash']);
            $db->update_query('attachments', ['pid' => $this->pid, 'posthash' => ''], "posthash='{$post['posthash']}' AND pid='0'");
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
                "SELECT u.username, u.email, u.id, u.loginkey, u.salt, u.added, s.notification
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
                    $db->insert_query('mailqueue', [
                        'mailto'   => $db->escape_string($member['email']),
                        'mailfrom' => '',
                        'subject'  => $db->escape_string($emailsubject),
                        'message'  => $db->escape_string($emailmessage),
                        'headers'  => '',
                    ]);
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

        $query           = $db->simple_select('attachments', 'COUNT(aid) AS attachmentcount', "pid='{$this->pid}' AND visible='1'");
        $attachmentcount = (int)$db->fetch_field($query, 'attachmentcount');
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
        $query  = $db->simple_select('forums', '*', "fid='{$thread['fid']}'");
        $forum  = $db->fetch_array($query);
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
            $query          = $db->simple_select('posts', 'tid', "pid='{$thread['pid']}'");
            $thread['tid']  = (int)$db->fetch_field($query, 'tid');
        }

        $draft_check = false;
        if (!empty($thread['pid']) && (int)$thread['pid'] > 0) {
            $query       = $db->simple_select('posts', 'pid', "pid='{$thread['pid']}' AND uid='{$thread['uid']}' AND visible='-2'");
            $draft_check = $db->fetch_field($query, 'pid');
        }

        if ($draft_check) {
            $this->thread_insert_data = [
                'subject'    => $db->escape_string($thread['subject'] ?? ''),
                'username'   => $db->escape_string($thread['username'] ?? ''),
                'dateline'   => (int)($thread['dateline'] ?? TIMENOW),
                'lastpost'   => (int)($thread['dateline'] ?? TIMENOW),
                'lastposter' => $db->escape_string($thread['username'] ?? ''),
                'visible'    => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread', $this);
            $db->update_query('threads', $this->thread_insert_data, "tid='{$thread['tid']}'");

            $this->post_insert_data = [
                'subject'   => $db->escape_string($thread['subject'] ?? ''),
                'username'  => $db->escape_string($thread['username'] ?? ''),
                'dateline'  => (int)($thread['dateline'] ?? TIMENOW),
                'message'   => $db->escape_string($thread['message'] ?? ''),
                'ipaddress' => $db->escape_binary(my_inet_pton(get_ip())),
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread_post', $this);
            $db->update_query('posts', $this->post_insert_data, "pid='{$thread['pid']}'");
            $this->tid = (int)$thread['tid'];
            $this->pid = (int)$thread['pid'];
        } else {
            $this->thread_insert_data = [
                'fid'          => (int)$thread['fid'],
                'subject'      => $db->escape_string($thread['subject'] ?? ''),
                'uid'          => (int)($thread['uid'] ?? 0),
                'username'     => $db->escape_string($thread['username'] ?? ''),
                'dateline'     => (int)($thread['dateline'] ?? TIMENOW),
                'lastpost'     => (int)($thread['dateline'] ?? TIMENOW),
                'lastposter'   => $db->escape_string($thread['username'] ?? ''),
                'lastposteruid'=> (int)($thread['uid'] ?? 0),
                'views'        => 0,
                'replies'      => 0,
                'visible'      => $visible,
                'notes'        => '',
            ];
            $plugins->run_hooks('datahandler_post_insert_thread', $this);
            $this->tid = (int)$db->insert_query('threads', $this->thread_insert_data);

            $this->post_insert_data = [
                'tid'       => $this->tid,
                'fid'       => (int)$thread['fid'],
                'subject'   => $db->escape_string($thread['subject'] ?? ''),
                'uid'       => (int)($thread['uid'] ?? 0),
                'username'  => $db->escape_string($thread['username'] ?? ''),
                'dateline'  => (int)($thread['dateline'] ?? TIMENOW),
                'message'   => $db->escape_string($thread['message'] ?? ''),
                'ipaddress' => $db->escape_binary(my_inet_pton(get_ip())),
                'visible'   => $visible,
            ];
            $plugins->run_hooks('datahandler_post_insert_thread_post', $this);
            $this->pid = (int)$db->insert_query('posts', $this->post_insert_data);

            $this->attachFileIds($this->pid);

            $db->update_query('threads', ['firstpost' => $this->pid], "tid='{$this->tid}'");
			
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
                    $db->update_query('threads', $modoptions_update, "tid='{$this->tid}'");
                }
            }

            if ($visible == 1) {
                if ((int)($thread['uid'] ?? 0) > 0) {
                    $user         = get_user((int)$thread['uid']);
                    $update_query = [];

                    if ((int)($thread['dateline'] ?? 0) > (int)($user['lastpost'] ?? 0)) {
                        $update_query['lastpost'] = "'{$thread['dateline']}'";
                    }
                    if ($forum['usepostcounts'] != 0)   { $update_query['postnum']   = 'postnum+1';   }
                    if ($forum['usethreadcounts'] != 0) { $update_query['threadnum'] = 'threadnum+1'; }

                    if (!empty($update_query)) {
                        $db->update_query('users', $update_query, "id='{$thread['uid']}'", '1', true);
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

                    $db->insert_query('mailqueue', [
                        'mailto'   => $db->escape_string($member['email']),
                        'mailfrom' => '',
                        'subject'  => $db->escape_string($emailsubject),
                        'message'  => $db->escape_string($emailmessage),
                        'headers'  => '',
                    ]);
                    $queued_email = true;
                }

                if (!empty($queued_email)) {
                    $cache->update_mailqueue();
                }
            }
        }

        // Posthash
        if (!empty($thread['posthash'])) {
            $thread['posthash'] = $db->escape_string($thread['posthash']);
            $db->update_query('attachments', ['pid' => $this->pid, 'posthash' => ''], "posthash='{$thread['posthash']}' AND pid='0'");
        }

        if ($visible == 1) {
            update_last_post($this->tid);
            update_forum_counters((int)$thread['fid'], ['threads' => '+1', 'posts' => '+1']);
            update_forum_lastpost((int)$thread['fid']);
        } elseif ($visible == 0) {
            update_forum_counters((int)$thread['fid'], ['unapprovedthreads' => '+1', 'unapprovedposts' => '+1']);
        }

        $query           = $db->simple_select('attachments', 'COUNT(aid) AS attachmentcount', "pid='{$this->pid}' AND visible='1'");
        $attachmentcount = (int)$db->fetch_field($query, 'attachmentcount');
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
                $this->thread_update_data['subject'] = $db->escape_string($post['subject']);
            }

            if (!empty($this->thread_update_data)) {
                $plugins->run_hooks('datahandler_post_update_thread', $this);
                $db->update_query('threads', $this->thread_update_data, "tid='{$post['tid']}'");
            }

            if (isset($post['subject'])) {
                $query = $db->simple_select('threads', 'tid, closed', "closed='moved|{$this->tid}'");
                if ($db->num_rows($query) > 0) {
                    $update_data = ['subject' => $db->escape_string($post['subject'])];
                    while ($result = $db->fetch_array($query)) {
                        $db->update_query('threads', $update_data, "tid='" . (int)$result['tid'] . "'");
                    }
                }
            }
        }

        $this->pid = $post['pid'];

        if (isset($post['subject'])) { $this->post_update_data['subject'] = $db->escape_string($post['subject']); }
        if (isset($post['message'])) { $this->post_update_data['message'] = $db->escape_string($post['message']); }

        if (isset($post['editreason']) && trim($post['editreason']) !== '') {
            $this->post_update_data['editreason'] = $db->escape_string(trim($post['editreason']));
        } else {
            $this->post_update_data['editreason'] = '';
        }

        $showeditedby = true;
        if ($showeditedby) {
            $this->post_update_data['edituid']  = (int)($post['edit_uid'] ?? 0);
            $this->post_update_data['edittime'] = TIMENOW;
        }

        $plugins->run_hooks('datahandler_post_update', $this);
        $db->update_query('posts', $this->post_update_data, "pid='{$post['pid']}'");

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
            $db->delete_query('threadsubscriptions', "uid='{$uid}' AND tid='{$post['tid']}'");
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

        $file_ids = array_filter(array_map('intval', (array)$_POST['file_ids']));
        if (empty($file_ids)) {
            return;
        }

        $id_list = implode(',', $file_ids);
        $db->sql_query("UPDATE comment_files SET post_id = {$pid} WHERE id IN ({$id_list})");
    }
}