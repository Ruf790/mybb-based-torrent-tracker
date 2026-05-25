<?php
declare(strict_types=1);



/**
 * XML HTTP Requests handler with stripped down MyBB core for faster response times.
 */
define("IN_MYBB", 1);
define("NO_ONLINE", 1);
define("SCRIPTNAME", "xmlhttp.php");


define ('IN_TRACKER', true);
define ('APP_INITIALIZED', true);
define ('TIMENOW', time ());
  
define ('TSDIR', dirname (__FILE__));
define ('INC_PATH', TSDIR . '/include');


require_once INC_PATH.'/init.php';

$shutdown_queries = $shutdown_functions = array();

// Load some of the stock caches we'll be using.
$groupscache = $cache->read("usergroups");

if(!is_array($groupscache))
{
	$cache->update_usergroups();
	$groupscache = $cache->read("usergroups");
}

// Send no cache headers
header("Expires: Sat, 1 Jan 2000 01:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// Create the session
require_once INC_PATH."/class_session.php";
$session = new session;
$session->init();


global $CURUSER, $plugins, $usergroups;


define('FORUM_ACTIVE', true);
define('FORUM_SECURE', true);
require_once INC_PATH . '/tsf_functions.php';


require_once INC_PATH . '/datahandler.php';



if (function_exists('mb_internal_encoding') && !empty($charset)) {
    @mb_internal_encoding($charset);
}

$charset = $charset ?: "UTF-8";
$lang->load("xmlhttp");

$closed_bypass = ["refresh_captcha", "validate_captcha"];
$mybb->input['action'] = $mybb->get_input('action');

$plugins->run_hooks("xmlhttp");

$is_mod = is_mod($usergroups);





// Main action router
handleXmlHttpAction();


/**
 * Main XML HTTP action router
 */
function handleXmlHttpAction(): void
{
    global $mybb, $CURUSER, $usergroups, $plugins;
    
    match ($mybb->input['action']) {
        'get_users' => handleGetUsers(),
        'get_multiquoted' => handleGetMultiquoted(),
        'edit_post' => handleEditPost(),
        'edit_subject' => handleEditSubject(),
        'get_buddyselect' => handleGetBuddySelect(),
        'complex_password' => handleComplexPassword(),
        'username_availability' => handleUsernameAvailability(),
        'email_availability' => handleEmailAvailability(),
        'search_torrents' => handleSearchTorrents(),
        'quick_comment' => handleQuickComment(),
		'edit_torrent' => handleEditTorrent(),
		'rate_torrent' => handleRateTorrent(),
		'rate_thread' => handleRateThread(),
        default => handleUnknownAction()
    };
}



function get_torrent(int $tid, bool $recache = false): array|false
{
    global $db;
    static $thread_cache = [];

    if (isset($thread_cache[$tid]) && !$recache) {
        return $thread_cache[$tid];
    }

    $query = $db->simple_select("torrents", "*", "id = '{$tid}'");
    $thread = $db->fetch_array($query);

    if ($thread) {
        $thread_cache[$tid] = $thread;
        return $thread;
    }

    $thread_cache[$tid] = false;
    return false;
}




/**
 * Show message helper function
 */
function show_msg(string $message = '', bool $error = true, string $color = 'red', bool $strong = true, string $extra = '', string $extra2 = ''): void
{
    global $shoutboxcharset;
    
    header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-type: text/html; charset=' . $shoutboxcharset);
    
    if ($error) {
        exit('<error>' . $message . '</error>');
    }

    exit($extra . (!empty($color) ? '<font color="' . $color . '">' : '') . ($strong ? '<strong>' : '') . $message . ($strong ? '</strong>' : '') . (!empty($color) ? '</font>' : '') . $extra2);
}

/**
 * Check if comments are allowed for torrent
 */
function allowcomments(int $torrentid = 0): bool
{
    global $is_mod, $db;

    $sql = "SELECT allowcomments FROM torrents WHERE id = ?";
    $params = [$torrentid];

    $query = $db->sql_query_prepared($sql, $params);

    if (!$db->num_rows($query)) {
        return false;
    }

    $result = $db->fetch_array($query);
    $allowcomments = $result['allowcomments'];

    return !($allowcomments != "yes" && !$is_mod);
}





function handleRateTorrent(): void
{
    global $db, $CURUSER, $charset;

    header("Content-Type: application/json; charset={$charset}");

    if (!$CURUSER) {
        echo json_encode(['success' => false]);
        exit;
    }

    $torrent_id = (int)($_POST['torrent_id'] ?? 0);
    $rating     = max(1, min(10, (int)($_POST['rating'] ?? 0)));

    if (!$torrent_id || !$rating) {
        echo json_encode(['success' => false]);
        exit;
    }

    $db->sql_query("
        INSERT INTO torrent_ratings (torrent_id, user_id, rating, added)
        VALUES ({$torrent_id}, {$CURUSER['id']}, {$rating}, " . TIMENOW . ")
        ON DUPLICATE KEY UPDATE rating = {$rating}, added = " . TIMENOW
    );

    $q = $db->sql_query("SELECT ROUND(AVG(rating),1) AS avg, COUNT(id) AS cnt FROM torrent_ratings WHERE torrent_id = {$torrent_id}");
    $row = $db->fetch_array($q);

    echo json_encode([
        'success' => true,
        'avg'     => (float)$row['avg'],
        'count'   => (int)$row['cnt'],
    ]);
    exit;
}




function handleRateThread(): void
{
    global $db, $CURUSER, $charset;
    header("Content-Type: application/json; charset={$charset}");

    if (!$CURUSER) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $tid    = (int)($_POST['tid'] ?? 0);
    $rating = max(1, min(10, (int)($_POST['rating'] ?? 0)));

    if (!$tid || !$rating) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Insert or update user's rating
    $db->sql_query("
        INSERT INTO threadratings (tid, user_id, rating, added)
        VALUES ({$tid}, {$CURUSER['id']}, {$rating}, " . TIMENOW . ")
        ON DUPLICATE KEY UPDATE rating = {$rating}, added = " . TIMENOW
    );

    // Recalculate avg and count
    $q = $db->sql_query(
        "SELECT ROUND(AVG(rating), 1) AS avg, COUNT(id) AS cnt 
         FROM threadratings WHERE tid = {$tid}"
    );
    $row = $db->fetch_array($q);

    $avg   = (float)($row['avg'] ?? 0);
    $count = (int)($row['cnt'] ?? 0);

    // Update threads table cache
    $db->sql_query(
        "UPDATE threads SET 
            numratings   = {$count},
            totalratings = " . (int)round($avg * $count) . "
         WHERE tid = {$tid}"
    );

    echo json_encode([
        'success' => true,
        'avg'     => $avg,
        'count'   => $count,
    ]);
    exit;
}










/**
 * Handle get_users action
 */
function handleGetUsers(): void
{
    global $mybb, $db, $charset, $plugins;
    
    $mybb->input['query'] = ltrim($mybb->get_input('query'));
    $search_type = $mybb->get_input('search_type', MyBB::INPUT_INT);

    if (my_strlen($mybb->input['query']) < 2) {
        exit;
    }

    $limit = $mybb->get_input('getone', MyBB::INPUT_INT) == 1 ? 1 : 15;
    header("Content-type: application/json; charset={$charset}");

    $query_options = [
        "order_by" => "username",
        "order_dir" => "asc",
        "limit_start" => 0,
        "limit" => $limit
    ];

    $plugins->run_hooks("xmlhttp_get_users_start");

    $likestring = $db->escape_string_like($mybb->input['query']);
    $likestring = match ($search_type) {
        1 => '%'.$likestring,
        2 => '%'.$likestring.'%',
        default => $likestring.'%'
    };

    $query = $db->simple_select("users", "id, username", "username LIKE '{$likestring}'", $query_options);
    
    if ($limit == 1) {
        $user = $db->fetch_array($query);
        $data = ['uid' => $user['id'], 'id' => $user['username'], 'text' => $user['username']];
    } else {
        $data = [];
        while ($user = $db->fetch_array($query)) {
            $data[] = ['uid' => $user['id'], 'id' => $user['username'], 'text' => $user['username']];
        }
    }

    $plugins->run_hooks("xmlhttp_get_users_end");
    echo json_encode($data);
    exit;
}

/**
 * Handle get_multiquoted action
 */
function handleGetMultiquoted(): void
{
    global $mybb, $db, $charset, $plugins;
    
    // ВСЕГДА устанавливаем JSON заголовок в начале
    header("Content-type: application/json; charset={$charset}");
    
    // Проверяем куки multiquote
    if (!isset($mybb->cookies['multiquote']) || empty(trim($mybb->cookies['multiquote']))) {
        echo json_encode(["message" => ""]);
        exit;
    }

    $multiquoted = explode("|", $mybb->cookies['multiquote']);
    $plugins->run_hooks("xmlhttp_get_multiquoted_start");

    // Фильтруем и валидируем ID постов
    $quoted_posts = [];
    foreach ($multiquoted as $post) {
        $post = trim($post);
        if (!empty($post) && is_numeric($post)) {
            $post_id = (int)$post;
            if ($post_id > 0) {
                $quoted_posts[] = $post_id;
            }
        }
    }

    // Если нет валидных постов
    if (empty($quoted_posts)) {
        echo json_encode(["message" => ""]);
        exit;
    }

    $quoted_posts_str = implode(",", $quoted_posts);
    $message = '';

    // Строим условие WHERE
    $from_tid = "";
    if (empty($mybb->input['load_all'])) {
        $current_tid = $mybb->get_input('tid', MyBB::INPUT_INT);
        if ($current_tid > 0) {
            $from_tid = "p.tid != '".$current_tid."' AND ";
        }
    }

    require_once INC_PATH."/class_parser.php";
    $parser = new postParser;
    require_once INC_PATH."/functions_posting.php";

    $plugins->run_hooks("xmlhttp_get_multiquoted_intermediate");

    try {
        // Выполняем запрос с обработкой ошибок
        $query = $db->sql_query("
            SELECT p.subject, p.message, p.pid, p.tid, p.username, p.dateline, t.fid, t.uid AS thread_uid, p.visible, u.username AS userusername
            FROM posts p
            LEFT JOIN threads t ON (t.tid=p.tid)
            LEFT JOIN users u ON (u.id=p.uid)
            WHERE {$from_tid}p.pid IN ({$quoted_posts_str}) AND p.visible = 1
            ORDER BY p.dateline, p.pid
        ");
        
        if (!$query) {
            throw new Exception("Database query failed");
        }
        
        $found_posts = 0;
        while ($quoted_post = $db->fetch_array($query)) {
            // Пропускаем невидимые посты
            if ($quoted_post['visible'] != 1) {
                continue;
            }
            
            $parsed_message = parse_quoted_message($quoted_post, false);
            if ($parsed_message) {
                $message .= $parsed_message;
                $found_posts++;
            }
        }
        
        // Если посты не найдены, возвращаем пустое сообщение
        if ($found_posts === 0) {
            echo json_encode(["message" => ""]);
            exit;
        }
        
        $maxquotedepth = "5";
        if ($maxquotedepth != '0') {
            $message = remove_message_quotes($message);
        }

        $plugins->run_hooks("xmlhttp_get_multiquoted_end");
        echo json_encode(["message" => $message]);
        exit;
        
    } catch (Exception $e) {
        // В случае любой ошибки возвращаем пустое сообщение в JSON
        error_log("Multiquote error: " . $e->getMessage());
        echo json_encode(["message" => ""]);
        exit;
    }
}

/**
 * Handle edit_post action
 */
function handleEditPost(): void
{
    global $mybb, $db, $charset, $plugins, $CURUSER;
    
    $post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

    if (!$post || $post['visible'] == -1) {
        xmlhttp_error('post_doesnt_exist');
    }

    $thread = get_thread($post['tid']);
    $forum = get_forum($thread['fid']);

    if (!$thread || !$forum || $forum['type'] != "f") {
        xmlhttp_error('thread_doesnt_exist');
    }

    $plugins->run_hooks("xmlhttp_edit_post_start");

    if ($mybb->get_input('do') == "get_post") {
        header("Content-type: application/json; charset={$charset}");
        echo json_encode($post['message']);
        exit;
    } elseif ($mybb->get_input('do') == "update_post") {
        $message = $mybb->get_input('value');
        $editreason = $mybb->get_input('editreason');
        
        if (my_strtolower($charset) != "utf-8") {
            if (function_exists("iconv")) {
                $message = iconv($charset, "UTF-8//IGNORE", $message);
                $editreason = iconv($charset, "UTF-8//IGNORE", $editreason);
            } elseif (function_exists("mb_convert_encoding")) {
                $message = @mb_convert_encoding($message, $charset, "UTF-8");
                $editreason = @mb_convert_encoding($editreason, $charset, "UTF-8");
            } elseif (my_strtolower($charset) == "iso-8859-1") {
                $message = utf8_decode($message);
                $editreason = utf8_decode($editreason);
            }
        }

        require_once INC_PATH."/datahandlers/post.php";
        $posthandler = new PostDataHandler("update");
        $posthandler->action = "post";

        $updatepost = [
            "pid" => $post['pid'],
            "message" => $message,
            "editreason" => $editreason,
            "edit_uid" => $CURUSER['id']
        ];

        if ($post['pid'] == $thread['firstpost']) {
            $updatepost['prefix'] = $thread['prefix'];
        }

        $posthandler->set_data($updatepost);

        if (!$posthandler->validate_post()) {
            $post_errors = $posthandler->get_friendly_errors();
            xmlhttp_error($post_errors);
        } else {
            $postinfo = $posthandler->update_post();
            $visible = $postinfo['visible'];
            
            if ($visible == 0 && !is_moderator($post['fid'], "canviewunapprove")) {
                if ($thread['firstpost'] == $post['pid']) {
                    echo json_encode(["moderation_thread" => $lang->thread_moderation, 'url' => $mybb->settings['bburl'].'/'.get_forum_link($thread['fid']), "message" => $post['message']]);
                    exit;
                } else {
                    echo json_encode(["moderation_post" => $lang->post_moderation, 'url' => $mybb->settings['bburl'].'/'.get_thread_link($thread['tid']), "message" => $post['message']]);
                    exit;
                }
            }
        }

        require_once INC_PATH."/class_parser.php";
        $parser = new postParser;

        $parser_options = [
            "allow_html" => 1,
            "allow_mycode" => 1,
            "allow_smilies" => 1,
            "allow_imgcode" => 1,
            "allow_videocode" => 1,
            "me_username" => $post['username'],
            "filter_badwords" => 1
        ];

        $post['username'] = htmlspecialchars_uni($post['username']);
        $post['message'] = $parser->parse_message($message, $parser_options);

        $enableattachments = "1";
        if ($enableattachments != 0) {
            $query = $db->simple_select("attachments", "*", "pid='{$post['pid']}'");
            while ($attachment = $db->fetch_array($query)) {
                $attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
            }

            require_once INC_PATH."/functions_post.php";
            get_post_attachments($post['pid'], $post);
        }

        $showeditedby = "1";
        $editedmsg = '';
        
        if ($showeditedby != 0) {
            $post['editdate'] = my_datee('relative', TIMENOW);
            $post['editnote'] = sprintf('This post was last modified: '.$post['editdate'].' by');
            $CURUSER['username'] = htmlspecialchars_uni($CURUSER['username']);
            $post['editedprofilelink'] = build_profile_link($CURUSER['username'], $CURUSER['id']);
            $post['editreason'] = trim($editreason);
            $editreason = "";
            
            if ($post['editreason'] != "") {
                $post['editreason'] = $parser->parse_badwords($post['editreason']);
                $post['editreason'] = htmlspecialchars_uni($post['editreason']);
                $editreason = ' Edit Reason: '.$post['editreason'].'';
            }
            
            $editedmsg = '<div class="mt-3"><span class="small">'.$post['editnote'].' '.$post['editedprofilelink'].''.$editreason.'</span></div>';
        }

        header("Content-type: application/json; charset={$charset}");

        $editedmsg_response = null;
        if (!empty($editedmsg)) {
            $editedmsg_response = str_replace(["\r", "\n"], "", $editedmsg);
        }

        $plugins->run_hooks("xmlhttp_update_post");
        echo json_encode(["message" => $post['message']."\n", "editedmsg" => $editedmsg_response]);
        exit;
    }
}

/**
 * Handle edit_subject action
 */
function handleEditSubject(): void
{
    global $mybb, $db, $charset, $plugins, $CURUSER;
    
    if ($mybb->request_method != "post") {
        exit;
    }

    if ($mybb->get_input('tid', MyBB::INPUT_INT)) {
        $thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
        if (!$thread) {
            xmlhttp_error('thread_doesnt_exist');
        }

        $query_options = ["order_by" => "dateline, pid"];
        $query = $db->simple_select("posts", "pid,uid,dateline", "tid='".$thread['tid']."'", $query_options);
        $post = $db->fetch_array($query);
    } else {
        exit;
    }

    $forum = get_forum($thread['fid']);
    if (!$forum || $forum['type'] != "f") {
        xmlhttp_error('thread_doesnt_exist');
    }

    $plugins->run_hooks("xmlhttp_edit_subject_start");

    $subject = $mybb->get_input('value');
    if (my_strtolower($charset) != "utf-8") {
        if (function_exists("iconv")) {
            $subject = iconv($charset, "UTF-8//IGNORE", $subject);
        } elseif (function_exists("mb_convert_encoding")) {
            $subject = @mb_convert_encoding($subject, $charset, "UTF-8");
        } elseif (my_strtolower($charset) == "iso-8859-1") {
            $subject = utf8_decode($subject);
        }
    }

    if ($thread['subject'] != $subject) {
        require_once INC_PATH."/datahandlers/post.php";
        $posthandler = new PostDataHandler("update");
        $posthandler->action = "post";

        $updatepost = [
            "pid" => $post['pid'],
            "tid" => $thread['tid'],
            "fid" => $forum['fid'],
            "prefix" => $thread['prefix'],
            "subject" => $subject,
            "edit_uid" => $CURUSER['id']
        ];
        
        $posthandler->set_data($updatepost);

        if (!$posthandler->validate_post()) {
            $post_errors = $posthandler->get_friendly_errors();
            xmlhttp_error($post_errors);
        } else {
            $posthandler->update_post();
            $modlogdata = [
                "tid" => $thread['tid'],
                "fid" => $forum['fid']
            ];
            log_moderator_action($modlogdata, 'Edited Post');
        }
    }

    require_once INC_PATH."/class_parser.php";
    $parser = new postParser;

    header("Content-type: application/json; charset={$charset}");
    $plugins->run_hooks("xmlhttp_edit_subject_end");

    $mybb->input['value'] = $parser->parse_badwords($mybb->get_input('value'));
    $subject = substr($mybb->input['value'], 0, 120);
    echo json_encode(["subject" => '<a href="'.get_thread_link($thread['tid']).'">'.htmlspecialchars_uni($subject).'</a>']);
    exit;
}

/**
 * Handle get_buddyselect action
 */
function handleGetBuddySelect(): void
{
    global $CURUSER, $db, $charset, $plugins, $templates, $wolcutoffmins;
    
    header("Content-type: text/plain; charset={$charset}");

    if ($CURUSER['buddylist'] == "") {
        xmlhttp_error('buddylist_error');
    }

    $query_options = [
        "order_by" => "username",
        "order_dir" => "asc"
    ];

    $plugins->run_hooks("xmlhttp_get_buddyselect_start");

    $timecut = TIMENOW - $wolcutoffmins;
    $query = $db->simple_select("users", "id, username, usergroup, displaygroup, lastactive, lastvisit, invisible", "id IN ({$CURUSER['buddylist']})", $query_options);
    
    $online = [];
    $offline = [];
    
    while ($buddy = $db->fetch_array($query)) {
        $buddy['username'] = htmlspecialchars_uni($buddy['username']);
        $buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
        $profile_link = build_profile_link($buddy_name, $buddy['id'], '_blank');
        
        if ($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $CURUSER['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive']) {
            eval("\$online[] = \"".$templates->get("xmlhttp_buddyselect_online")."\";");
        } else {
            eval("\$offline[] = \"".$templates->get("xmlhttp_buddyselect_offline")."\";");
        }
    }
    
    $online = implode("", $online);
    $offline = implode("", $offline);

    $plugins->run_hooks("xmlhttp_get_buddyselect_end");
    eval("\$buddy_select = \"".$templates->get("xmlhttp_buddyselect")."\";");
    echo $buddy_select;
    exit;
}

/**
 * Handle complex_password action
 */
function handleComplexPassword(): void
{
    global $charset, $plugins;
    
    $password = trim($mybb->get_input('password'));
    $password = str_replace([unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)], [" ", "-", "", "", "", ""], $password);

    $minpasswordlength = '6';
    header("Content-type: application/json; charset={$charset}");

    $plugins->run_hooks("xmlhttp_complex_password");

    if (!preg_match("/^.*(?=.{".$minpasswordlength.",})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $password)) {
        echo json_encode('complex_password_fails');
    } else {
        echo json_encode("true");
    }

    exit;
}

/**
 * Handle username_availability action
 */
function handleUsernameAvailability(): void
{
    global $mybb, $db, $charset, $plugins, $lang;
    
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        xmlhttp_error($lang->invalid_post_code);
    }

    require_once INC_PATH."/functions_user.php";
    $username = $mybb->get_input('username');
    $username = trim_blank_chrs($username);
    $username = str_replace([unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)], [" ", "-", "", "", "", ""], $username);
    $username = preg_replace("#\s{2,}#", " ", $username);

    header("Content-type: application/json; charset={$charset}");

    if (empty($username)) {
        echo json_encode('banned_characters_username');
        exit;
    }

    $banned_username = is_banned_username($username, true);
    if ($banned_username) {
        echo json_encode('banned_username');
        exit;
    }

    if (strpos($username, "<") !== false || strpos($username, ">") !== false || strpos($username, "&") !== false || my_strpos($username, "\\") !== false || strpos($username, ";") !== false || strpos($username, ",") !== false || !validate_utf8_string($username, false, false)) {
        echo json_encode('banned_characters_username');
        exit;
    }

    $user = get_user_by_username($username);
    $plugins->run_hooks("xmlhttp_username_availability");

    if ($user) {
        $username_taken = sprintf($lang->xmlhttp['username_taken'], htmlspecialchars_uni($username));
        echo json_encode($username_taken);
        exit;
    } else {
        echo json_encode("true");
        exit;
    }
}

/**
 * Handle email_availability action
 */
function handleEmailAvailability(): void
{
    global $mybb, $charset, $plugins;
    
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        xmlhttp_error('invalid_post_code');
    }

    require_once INC_PATH."/datahandlers/user.php";
    $userhandler = new UserDataHandler("insert");

    $email = $mybb->get_input('email');
    header("Content-type: application/json; charset={$charset}");

    $user = ['email' => $email];
    $userhandler->set_data($user);
    $errors = [];

    if (!$userhandler->verify_email()) {
        $errors = $userhandler->get_friendly_errors();
    }

    $plugins->run_hooks("xmlhttp_email_availability");

    if (!empty($errors)) {
        echo json_encode($errors[0]);
        exit;
    } else {
        echo json_encode("true");
        exit;
    }
}

/**
 * Handle search_torrents action
 */
function handleSearchTorrents(): void
{
    global $db, $charset, $BASEURL;
    
    $input = $_GET['input'] ?? '';
    $input = trim($input);

    if (empty($input)) {
        header("Content-Type: application/json; charset={$charset}");
        echo json_encode([]);
        exit;
    }

    $like_input = "%{$input}%";
    $sql = "
        SELECT id, name, descr, t_image
        FROM torrents
        WHERE name LIKE ? OR descr LIKE ?
        ORDER BY name
        LIMIT 10
    ";

    $params = [$like_input, $like_input];
    $result = $db->sql_query_prepared($sql, $params);

    $torrents = [];
    while ($row = $db->fetch_array($result)) {
        $image_url = !empty($row['t_image'])
            ? (strpos($row['t_image'], 'http') === 0 ? $row['t_image'] : $BASEURL . '/' . ltrim($row['t_image'], '/'))
            : $BASEURL . '/pic/nopreview.gif';

        $torrents[] = [
            'id' => $row['id'],
            'name' => mb_strimwidth($row['name'], 0, 100, '...'),
            'descr' => $row['descr'],
            'image_url' => $image_url
        ];
    }

    header("Content-Type: application/json; charset={$charset}");
    echo json_encode($torrents);
    exit;
}

/**
 * Handle quick_comment action
 */
function handleQuickComment(): void
{
    global $db, $CURUSER, $lang, $shoutboxcharset, $is_mod, $BASEURL, $plugins, $ts_perpage;

	if (!isset($_POST['ajax_quick_comment']) || !isset($_POST['id']) || !isset($_POST['text']) || !$CURUSER) {
        return;
    }

	
	$query = $db->simple_select('users', 'cancomment', "id = '{$CURUSER['id']}'");
    $commentperm = $db->fetch_array($query);
    if ((int)($commentperm['cancomment'] ?? 1) === 0) 
    {
       show_msg('nopermission');
    }
	


    $torrentid = (int)$_POST['id'];
    $lang->load('comment');
    
    if (!allowcomments($torrentid)) {
        show_msg($lang->comment['closed']);
    }

    $text = urldecode($_POST['text']);
    $text = (string)$text;
    
    if (strtolower($shoutboxcharset) != 'utf-8') {
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', $shoutboxcharset, $text);
        } elseif (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, $shoutboxcharset, 'UTF-8');
        } elseif (strtolower($shoutboxcharset) == 'iso-8859-1') {
            $text = utf8_decode($text);
        }
    }

    $query = $db->simple_select("comments", "dateline", "user = '{$CURUSER['id']}'", ['order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1]);
    $last_comment = 0;
    
    if ($db->num_rows($query) > 0) {
        $result = $db->fetch_array($query);
        $last_comment = $result['dateline'];
    }

    $floodmsg = flood_check($lang->comment['floodcomment'], $last_comment > 0 ? (string)$last_comment : null, true);
    $res = $db->simple_select("torrents", "name, owner", "id='".$torrentid."'");
    $arr = $db->fetch_array($res);
    
    if (!empty($floodmsg)) {
        show_msg(str_replace(['<font color="#9f040b" size="2">', '</font>', '<b>', '</b>'], '', $floodmsg));
    } else {
        if (!$arr) {
            show_msg($lang->global['notorrentid']);
        } elseif (empty($text) || empty($torrentid) || !is_valid_id($torrentid)) {
            show_msg($lang->global['dontleavefieldsblank']);
        }
    }

    $commentposted = false;
    if (!$is_mod) {
        $query = $db->simple_select("comments", "id, user, text", "torrent='{$torrentid}'", ['order_by' => 'dateline', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => 1]);
        
        if ($db->num_rows($query) > 0) {
            $last_post55 = $db->fetch_array($query);
            $lastcommentuserid = $last_post55['user'];
            
            if ($lastcommentuserid == $CURUSER['id']) {
                $oldtext = $last_post55['text'];
                $newid = $last_post55['id'];
                $newtext = $oldtext .="\n[hr]\n".$_POST['text'];
                
                $update_comments = [
                    "text" => $db->escape_string($newtext),
                    "editedat" => TIMENOW,
                    "editedby" => $db->escape_string($CURUSER['id'])
                ];
                        
                $db->update_query("comments", $update_comments, "id='{$newid}'");
                
                if ($db->affected_rows()) {
                    $commentposted = true;
					
				
                   // Редирект на последнюю страницу к этому комменту
$count_query = $db->simple_select("comments", "COUNT(id) as total", "torrent='{$torrentid}'");
$count_row   = $db->fetch_array($count_query);
$total       = (int)($count_row['total'] ?? 0);
$perpage     = (int)($ts_perpage ?? 20);
if ($perpage <= 0) $perpage = 20;
$lastpage    = max(1, (int)ceil($total / $perpage));
$currentpage = (int)($_POST['page'] ?? 1);
if ($currentpage <= 0) $currentpage = 1;

if ($lastpage > 1 && $currentpage < $lastpage) {
    $url = get_comment_link($newid, $torrentid, $lastpage) . "#pid{$newid}";
    show_msg('<redirect>' . $url . '</redirect>', false, '', false);
}
				   
				   
					
                }
            }
        }
    }

    if (!$commentposted) {
        $comment_insert_data = [
            "user" => $db->escape_string($CURUSER['id']),
            "torrent" => $db->escape_string($torrentid),
            "dateline" => TIMENOW,
            "text" => $db->escape_string($text)
        ];

        $db->insert_query("comments", $comment_insert_data);
        $cid = $db->insert_id();

        if (!empty($_POST['file_ids'])) {
            $file_ids = array_map('intval', (array)$_POST['file_ids']);
            $id_list = implode(',', $file_ids);

            if (!empty($id_list)) {
                $db->sql_query("UPDATE comment_files SET comment_id = " . $cid . " WHERE id IN ($id_list)");
            }
        }
		
		
		// Привязать вложения из attachments через posthash
        $posthash = trim($_POST['posthash'] ?? '');
        if ($posthash) {
        require_once INC_PATH . '/functions_comment_attachments.php';
          attach_to_comment($posthash, $cid, (int)$CURUSER['id']);
        }	
		
		

        $db->update_query("torrents", ['comments' => 'comments+1'], "id='{$torrentid}'", "1", true);
        $db->update_query("users", ['comms' => 'comms+1'], "id='{$CURUSER['id']}'", "1", true);

        $sql = "SELECT commentpm FROM users WHERE id = ?";
        $params = [(int)$arr['owner']];
        $ras = $db->sql_query_prepared($sql, $params);
        $arg = $db->fetch_array($ras);

        if ($arg['commentpm'] == 1 && $CURUSER['id'] != $arr['owner']) {
            require_once INC_PATH . '/functions_pm.php';
            $url2 = get_comment_link($cid, $torrentid)."#pid{$cid}";
                    
            $pm = [
                'subject' => sprintf($lang->comment['newcommentsub']),
                'message' => sprintf($lang->comment['newcommenttxt'], '[url=' . $BASEURL.'/'.$url2.']' . $arr['name'] . '[/url]'),
                'touid' => $arr['owner']
            ];
            
            $pm['sender']['uid'] = -1;
            send_pm($pm, -1, true);
        }
    }

    require_once INC_PATH . '/commenttable.php';
    
    $subres = $db->sql_query_prepared("
    SELECT 
        c.id, c.torrent AS torrentid, c.text, c.user, c.editreason, c.dateline, c.editedby, c.editedat, 
        uu.username AS editedbyuname, gg.namestyle AS editbynamestyle, 
        u.added AS registered, u.enabled, u.lastactive, u.lastvisit, u.invisible, u.warned, u.leechwarn, u.username, u.usertitle, 
        u.usergroup, u.displaygroup, u.postnum, u.threadnum, u.added, u.comms, u.donor, u.uploaded, u.downloaded, 
        u.avatar AS useravatar, u.avatardimensions, u.signature, 
        g.title AS grouptitle, g.namestyle 
    FROM comments c
    LEFT JOIN users uu ON (c.editedby = uu.id)
    LEFT JOIN usergroups gg ON (uu.usergroup = gg.gid)
    LEFT JOIN users u ON (c.user = u.id)
    LEFT JOIN usergroups g ON (u.usergroup = g.gid)
    WHERE c.id = ?", [$cid]);

    $allrows = [];
    while ($subrow = $db->fetch_array($subres)) {
        $allrows[] = $subrow;
    }
    
    $lcid = (int)($_POST["lcid"] ?? 0);
    define("LCID", $lcid);
    
   
    
	// Считаем страницу для нового комментария
$count_query = $db->simple_select("comments", "COUNT(id) as total", "torrent='{$torrentid}'");
$count_row   = $db->fetch_array($count_query);
$total       = (int)($count_row['total'] ?? 0);
$perpage     = (int)($ts_perpage ?? 20);
if ($perpage <= 0) $perpage = 20;
$lastpage    = max(1, (int)ceil($total / $perpage));
$currentpage = (int)($_POST['page'] ?? 1);
if ($currentpage <= 0) $currentpage = 1;



if ($lastpage > 1 && $currentpage < $lastpage) {
    $url = get_comment_link($cid, $torrentid, $lastpage) . "#pid{$cid}";
    show_msg('<redirect>' . $url . '</redirect>', false, '', false);
}

$showcommenttable = commenttable($allrows, "", "", false, true, true);
show_msg($showcommenttable, false, "", false);
	
	
}







/**
 * Handle edit_torrent action - AJAX обработка редактирования торрента
 */
function handleEditTorrent(): void
{
    global $db, $CURUSER, $lang, $BASEURL, $torrent_dir, $cache, $is_mod;
    
    // Проверяем авторизацию
    if (!isset($CURUSER)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    
    ob_start();

    $errors = [];
    if (empty($_POST['name'])) {
        $errors[] = 'The name cannot be empty';
    }
    if (empty($_POST['descr'])) {
        $errors[] = 'The description cannot be empty';
    }

    if (!empty($errors) && $isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => implode(', ', $errors)
        ]);
        ob_end_flush();
        exit;
    }

    // Получаем ID торрента
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No Torrent ID'
            ]);
            ob_end_flush();
            exit;
        } else {
            die('No Torrent ID');
        }
    }

    // Проверяем права доступа к торренту
    $torrent = get_torrent($id);
    if (!$torrent) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Torrent not found']);
        exit;
    }

    // Проверяем, может ли пользователь редактировать этот торрент
    //if ($CURUSER['id'] !== $torrent['owner'] && !$is_mod) {
        //header('Content-Type: application/json');
        //echo json_encode(['success' => false, 'message' => 'Access denied']);
        //exit;
    //}

    // Basic data
    $name = htmlspecialchars($_POST['name']);
    $descr = htmlspecialchars($_POST['descr']);
    $t_image_file = $_FILES['t_image_file'] ?? [];
    $t_image_file2 = $_FILES['t_image_file2'] ?? [];
    $t_image_url = isset($_POST['t_image_url']) ? htmlspecialchars($_POST['t_image_url']) : '';
    $t_image_url2 = isset($_POST['t_image_url2']) ? htmlspecialchars($_POST['t_image_url2']) : '';
    
    $t_link = $_POST['t_link'] ?? '';
    
    $category = intval($_POST['category'] ?? 0);
    $free = isset($_POST['free']) && $_POST['free'] == 'yes' ? 'yes' : 'no';
    $silver = isset($_POST['silver']) && $_POST['silver'] == 'yes' ? 'yes' : 'no';
    $doubleupload = isset($_POST['doubleupload']) && $_POST['doubleupload'] == 'yes' ? 'yes' : 'no';
    $allowcomments = isset($_POST['allowcomments']) && $_POST['allowcomments'] == 'no' ? 'no' : 'yes';
    $sticky = isset($_POST['sticky']) && $_POST['sticky'] == 'yes' ? 'yes' : 'no';
    $isrequest = isset($_POST['isrequest']) && $_POST['isrequest'] == 'yes' ? 'yes' : 'no';
    $isnuked = isset($_POST['isnuked']) && $_POST['isnuked'] == 'yes' ? 'yes' : 'no';
    $WhyNuked = isset($_POST['WhyNuked']) ? htmlspecialchars($_POST['WhyNuked']) : '';

    $UpdateSet = [
        'name' => $db->escape_string($name),
        'descr' => $db->escape_string($descr),
        'category' => $db->escape_string($category),
        'free' => $free,
        'silver' => $silver,
        'doubleupload' => $doubleupload,
        'allowcomments' => $allowcomments,
        'sticky' => $sticky,
        'isrequest' => $isrequest,
        'isnuked' => $isnuked,
        'WhyNuked' => $isnuked == 'yes' ? $db->escape_string($WhyNuked) : ''
    ];

    
	
	
	
	// Обработка первого изображения (файл) - ОСТАВЛЯЕМ БЕЗ ИЗМЕНЕНИЙ
if (!empty($t_image_file)) 
{
    if (((( 0 < $t_image_file['size'] AND $t_image_file['error'] === 0 ) AND $t_image_file['tmp_name']) AND $t_image_file['name'])) 
    {
        $t_image_url = fix_url($t_image_file['name']);
        $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
        $ImageExt = get_extension($t_image_url);

        if (in_array($ImageExt, $AllowedFileTypes, true)) 
        {
            $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
            $ImageDetails = getimagesize($t_image_file['tmp_name']);

            if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true ))) 
            {
                if ($ImageContents = file_get_contents($t_image_file['tmp_name'])) 
                {
                    $NewImageURL = $torrent_dir . '/images/' . $id . '.' . $ImageExt;

                    if (file_exists($NewImageURL)) 
                    {
                        @unlink($NewImageURL);
                    }

                    if (file_put_contents($NewImageURL, $ImageContents)) 
                    {
                        $COVERIMAGEUPDATED = true;
                        
                        $update_image2 = array(
                            "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
                        );
                    
                        $db->update_query("torrents", $update_image2, "id='{$id}'");
                        $cache->update_torrents();
                    }
                }
            }
        }
    }
}

// Обработка второго изображения (файл) - ОСТАВЛЯЕМ БЕЗ ИЗМЕНЕНИЙ
if (!empty($t_image_file2)) 
{
    if (((( 0 < $t_image_file2['size'] AND $t_image_file2['error'] === 0 ) AND $t_image_file2['tmp_name']) AND $t_image_file2['name'])) 
    {
        $t_image_url2 = fix_url($t_image_file2['name']);
        $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
        $ImageExt = get_extension( $t_image_url2 );

        if (in_array($ImageExt, $AllowedFileTypes, true)) 
        {
            $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
            $ImageDetails = getimagesize($t_image_file2['tmp_name']);

            if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true))) 
            {
                if ($ImageContents = file_get_contents( $t_image_file2['tmp_name'] )) 
                {
                    $NewImageURL = $torrent_dir . '/images/' . $id . '_2.' . $ImageExt;

                    if (file_exists($NewImageURL)) 
                    {
                        @unlink($NewImageURL);
                    }

                    if (file_put_contents($NewImageURL, $ImageContents)) 
                    {
                        $COVERIMAGEUPDATED = true;
                        
                        $update_image22 = array(
                            "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
                        );
                    
                        $db->update_query("torrents", $update_image22, "id='{$id}'");
                        $cache->update_torrents();
                    }
                }
            }
        }
    }
}	

// Обработка первого изображения (URL) - ИСПРАВЛЕННАЯ ВЕРСИЯ
if (!empty($t_image_url)) 
{
    $t_image_url = fix_url($t_image_url);
    $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
    $ImageExt = get_extension($t_image_url);

    if (in_array($ImageExt, $AllowedFileTypes, true)) 
    {
        // УБИРАЕМ getimagesize() для URL - он не работает с удаленными файлами
        include_once(INC_PATH . '/functions_ts_remote_connect.php');

        if ($ImageContents = TS_Fetch_Data($t_image_url, false)) 
        {
            $NewImageURL = $torrent_dir . '/images/' . $id . '.' . $ImageExt;

            if (file_exists($NewImageURL)) 
            {
                @unlink($NewImageURL);
            }

            if (file_put_contents($NewImageURL, $ImageContents)) 
            {
                $COVERIMAGEUPDATED = true;
                
                $update_image = array(
                    "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
                );
                
                $db->update_query("torrents", $update_image, "id='{$id}'");
                $cache->update_torrents();
            }
        }
    }
}

// Обработка второго изображения (URL) - ИСПРАВЛЕННАЯ ВЕРСИЯ
if (!empty($t_image_url2)) 
{
    $t_image_url2 = fix_url($t_image_url2);
    $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
    $ImageExt = get_extension($t_image_url2);

    if (in_array($ImageExt, $AllowedFileTypes, true)) 
    {
        // УБИРАЕМ getimagesize() для URL - он не работает с удаленными файлами
        include_once(INC_PATH . '/functions_ts_remote_connect.php');

        if ($ImageContents = TS_Fetch_Data($t_image_url2, false)) 
        {
            $NewImageURL = $torrent_dir . '/images/' . $id . '_2.' . $ImageExt;

            if (file_exists($NewImageURL)) 
            {
                @unlink($NewImageURL);
            }

            if (file_put_contents($NewImageURL, $ImageContents)) 
            {
                $COVERIMAGEUPDATED = true;
                
                $update_image23 = array(
                    "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
                );
                
                $db->update_query("torrents", $update_image23, "id='{$id}'");
                $cache->update_torrents();
            }
        }
    }
}

// Удаление первого изображения если URL пустой
if (empty($t_image_url)) 
{
    $image_types = array ('gif', 'jpg', 'jpeg', 'png', 'webp');
    foreach ($image_types as $image)
    {
        if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image))
        {
            @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image);
            continue;
        }
    }
    
    $UpdateSet['t_image'] = '';
}

// Удаление второго изображения если URL пустой  
if (empty($t_image_url2)) 
{
    $image_types2 = array ('gif', 'jpg', 'jpeg', 'png', 'webp');
    foreach ($image_types2 as $image2)
    {
        if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2))
        {
            @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2);
            continue;
        }
    }
    
    $UpdateSet['t_image2'] = '';
}
	
	
	
	
	
	
	
	
	
	
	

    // Обработка IMDB ссылки
    if (!empty($t_link)) {
        if (preg_match('@^https:\/\/www\.imdb\.com\/title\/(.*)\/$@isU', $t_link, $result)) {
            if ($result[0]) {
                $t_link = $result[0];
                include_once(INC_PATH . '/ts_imdb.php');
                $Update_tlink = array(
                    "t_link" => $db->escape_string($t_link),
                    "tags" => $db->escape_string($Genre ?? '')
                );
                $db->update_query("torrents", $Update_tlink, "id='{$id}'");
                unset($result);
            }
        } else {
            $Update_tlink = array(
                "t_link" => '',
                "tags" => ''
            );
            $db->update_query("torrents", $Update_tlink, "id='{$id}'");
        }
    } else {
        $Update_tlink = array(
            "t_link" => '',
            "tags" => ''
        );
        $db->update_query("torrents", $Update_tlink, "id='{$id}'");
    }

    // Основное обновление торрента
    $res = $db->update_query('torrents', $UpdateSet, "id='{$id}'");
    if ($res) 
	{
        
		
		// Исправленная версия с проверками
$log_message = sprintf(
    'Torrent edited: %s by %s',
    '[URL='.$BASEURL."/".get_torrent_link($id).']<font color=red>' . $name . '</font>[/URL]', 
    '[URL='.$BASEURL . '/'.get_profile_link($CURUSER['id']).']' . format_name($CURUSER['username'],$CURUSER['usergroup']) . '[/URL]'
);
write_log($log_message);
		

	   $cache->update_torrents();
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Data is Updated',
                'updatedData' => [
                    'name' => $name,
                    'descr' => $descr,
                    't_image' => $UpdateSet['t_image'] ?? ($torrent['t_image'] ?? ''),
                    't_image2' => $UpdateSet['t_image2'] ?? ($torrent['t_image2'] ?? ''),
                    'category' => $category
                ]
            ]);
        } else {
            header("Location: details.php?id=" . $id);
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error Update Data'
            ]);
        } else {
            die('Error Update Data');
        }
    }
    ob_end_flush();
    exit;
}








/**
 * Handle unknown action
 */
function handleUnknownAction(): void
{
    // No action specified or unknown action
    exit;
}

/**
 * Spits an XML Http based error message back to the browser
 */
function xmlhttp_error(string|array $message): void
{
    global $charset;

    header("Content-type: application/json; charset={$charset}");

    if (is_array($message)) {
        $response = [];
        foreach ($message as $error) {
            $response[] = $error;
        }

        echo json_encode(["errors" => [$response]]);
        exit;
    }

    echo json_encode(["errors" => [$message]]);
    exit;
}
?>