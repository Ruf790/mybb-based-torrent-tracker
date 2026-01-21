<?php



declare(strict_types=1);

function allowcomments(int $torrentid = 0): bool
{
    global $is_mod, $db;
    
    $query = $db->simple_select('torrents', 'allowcomments', "id = '{$torrentid}'");
    $result = $db->fetch_array($query);
    
    return !($result["allowcomments"] != 'yes' && !$is_mod);
}

define("SCRIPTNAME", "comment.php");
define("IN_MYBB", 1);
define('C_VERSION', '1.8.8');
define("IN_ARCHIVE", true);

require_once 'global.php';
require_once 'cache/smilies.php';
require_once INC_PATH.'/class_parser.php';
require_once INC_PATH.'/datahandler.php';

$parser = new postParser();

$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

gzip();
maxsysop();
include_once INC_PATH . '/readconfig.php';

if (!isset($CURUSER)) {
    print_no_permission();
    exit;
}

$query = $db->simple_select('users_perm', 'cancomment', "userid = '{$CURUSER['id']}'");

if ($db->num_rows($query) > 0) {
    $commentperm = $db->fetch_array($query);
    if ($commentperm['cancomment'] == '0') {
        error_no_permission();
        exit;
    }
}

$lang->load('comment');
require INC_PATH . '/commenttable.php';

$is_mod = is_mod($usergroups);
$action = $_GET['action'] ?? '';

// Read body only for POST and accept all field name variants
$msgtext = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['message'] ?? $_POST['msgtext'] ?? $_POST['commentText'] ?? '';
    $msgtext = is_string($raw) ? trim($raw) : '';
}

$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
$avatar = '<img src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';

// Action handlers
match ($action) {
    'close' => handleCloseAction(),
    'open' => handleOpenAction(),
    'add' => handleAddAction(),
    'edit' => handleEditAction(),
    'edit2' => handleEdit2Action(),
    'delete' => handleDeleteAction(),
    'massdelete' => handleMassDeleteAction(),
    default => stderr($lang->global['error'], $lang->global['invalidaction'])
};

exit;

// Action handler functions
function handleCloseAction(): void
{
    global $db;
    
    $torrentid = (int)($_GET['tid'] ?? 0);
    int_check($torrentid, true);
    $db->sql_query('UPDATE torrents SET allowcomments = \'no\' WHERE id = ' . $torrentid);
    redirect('details.php?id=' . $torrentid . '&tab=comments');
    exit;
}

function handleOpenAction(): void
{
    global $db;
    
    $torrentid = (int)($_GET['tid'] ?? 0);
    int_check($torrentid, true);
    $db->sql_query('UPDATE torrents SET allowcomments = \'yes\' WHERE id = ' . $torrentid);
    redirect('details.php?id=' . $torrentid . '&tab=comments');
    exit;
}

function handleAddAction(): void
{
    global $db, $CURUSER, $is_mod, $BASEURL, $lang, $kpscomment, $smilies;

    $torrentid = (int)($_GET['tid'] ?? 0);
    int_check($torrentid, true);

    if (!allowcomments($torrentid)) {
        stderr($lang->comment['closed']);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
        processAddComment($torrentid);
    }

    displayAddCommentForm($torrentid);
}

function processAddComment(int $torrentid): void
{
    global $db, $CURUSER, $is_mod, $BASEURL, $lang, $kpscomment;

    $query = $db->sql_query('SELECT dateline FROM comments WHERE user = ' . $db->escape_string($CURUSER['id']) . ' ORDER BY dateline DESC LIMIT 1');
    $last_comment = 0;

    if ($db->num_rows($query) > 0) {
        $result = $db->fetch_array($query);
        $last_comment = (int)$result["dateline"];
    }

    $rpage = '';
    if (isset($_POST['page']) && is_valid_id($_POST['page'])) {
        $rpage = '&page=' . (int)$_POST['page'];
    }

    $returnto = $BASEURL . '/details.php?id=' . $torrentid . $rpage;
    $rt = '#startquickcomment';
    
    if (($_POST['ctype'] ?? '') == 'quickcomment') {
        $floodmsg = flood_check($lang->comment['floodcomment'], (string)$last_comment, true);
    } else {
        flood_check($lang->comment['floodcomment'], (string)$last_comment);
        $floodmsg = null;
    }

    $res = $db->simple_select('torrents', 'name, owner', "id = '{$torrentid}'");
    $arr = $db->fetch_array($res);

    if (!empty($floodmsg)) {
        $returnto .= '&cerror=3' . $rt;
        header("Location: $returnto");
        exit;
    }

    if (!$arr) {
        if (isset($returnto)) {
            $returnto .= '&cerror=1' . $rt;
            header("Location: $returnto");
            exit;
        } else {
            stderr($lang->global['notorrentid']);
        }
    }

    $msgtext = trim($_POST['msgtext'] ?? '');

    if (empty($msgtext)) {
        if (isset($returnto)) {
            $returnto .= '&tab=comments&cerror=2' . $rt;
            header("Location: $returnto");
            exit;
        } else {
            stderr($lang->global['dontleavefieldsblank']);
        }
    }

    $query = $db->sql_query('SELECT id, user, text FROM comments WHERE torrent = ' . $db->escape_string($torrentid) . ' ORDER BY dateline DESC LIMIT 1');
    $lastcomment = $db->fetch_array($query);
    $lastcommentuserid = $lastcomment['user'] ?? null;

    if ($lastcommentuserid && $lastcommentuserid == $CURUSER['id'] && !$is_mod) {
        // Append to existing comment
        $text = $lastcomment['text'] . "\n[hr]\n" . $msgtext;
        $update_query = ['text' => $text];
        $db->update_query("comments", $update_query, "id='" . $lastcomment['id'] . "'");
        $newid = $lastcomment['id'];
    } else {
        // Create new comment
        $comment_insert_data = [
            "user" => $db->escape_string($CURUSER['id']),
            "torrent" => $db->escape_string($torrentid),
            "dateline" => TIMENOW,
            "text" => $db->escape_string($msgtext)
        ];

        $db->insert_query("comments", $comment_insert_data);
        $newid = $db->insert_id();

        // Attach uploaded files
        if (!empty($_POST['file_ids'])) {
            $file_ids = array_map('intval', (array)$_POST['file_ids']);
            $id_list = implode(',', $file_ids);

            if (!empty($id_list)) {
                $db->sql_query("UPDATE comment_files SET comment_id = " . $newid . " WHERE id IN ($id_list)");
            }
        }

        // Update counters
        $db->update_query("torrents", ['comments' => "comments+1"], "id='{$torrentid}'", "1", true);
        $db->update_query("users", ['comms' => "comms+1"], "id='{$CURUSER['id']}'", "1", true);

        // Send PM notification
        if ($CURUSER['id'] != $arr['owner']) {
            $ras = $db->sql_query('SELECT commentpm FROM users WHERE id = ' . $db->escape_string($arr['owner']));
            $arg = $db->fetch_array($ras);

            if ($arg['commentpm'] == 1) {
                require_once INC_PATH . '/functions_pm.php';
                $url2 = get_comment_link($newid, $torrentid) . "#pid{$newid}";

                $pm = [
                    'subject' => sprintf($lang->comment['newcommentsub']),
                    'message' => sprintf($lang->comment['newcommenttxt'], '[url=' . $BASEURL . '/' . $url2 . ']' . $arr['name'] . '[/url]'),
                    'touid' => $arr['owner']
                ];

                $pm['sender']['uid'] = -1;
                send_pm($pm, -1, true);
            }
        }

        kps('+', $kpscomment, $CURUSER['id']);
    }

    $url = get_comment_link($newid, $torrentid) . "#pid{$newid}";

    // Return JSON success with redirect URL
    header('Content-Type: application/json');
    echo json_encode(['redirect' => $url]);
    exit;
}

function displayAddCommentForm(int $torrentid): void
{
    global $db, $CURUSER, $BASEURL, $lang, $smilies;

    $res = $db->simple_select('torrents', 'name, owner', "id = '{$torrentid}'");
    $arr = $db->fetch_array($res);
    
    if (!$arr) {
        stderr($lang->global['notorrentid']);
    }

    stdhead(sprintf($lang->comment['addcomment'], $arr['name']), true, 'supernote');
    require_once INC_PATH . '/editor.php';
    
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'commentText');
	
	$titlez = sprintf($lang->comment['addcomment'], htmlspecialchars_uni($arr['name']));
    
    echo <<<HTML
<div class="container mt-4">
    <h3>{$titlez}"</h3>
    {$editor['toolbar']}
    
    <form id="commentForm" method="post" name="compose" action="{$_SERVER['SCRIPT_NAME']}?action=add&tid={$torrentid}" novalidate>
        <div class="mb-3">
            <label for="commentText" class="form-label">{$lang->comment['insertcomment']}</label>
            <textarea class="form-control" id="commentText" name="msgtext" rows="6" placeholder="Write your comment using BBCode..." maxlength="500" aria-describedby="charCount" required></textarea>
            <div id="charCount" class="form-text text-end">0 / 500</div>
        </div>

        <input type="hidden" name="ctype" value="quickcomment">
        <input type="hidden" name="submit" value="1">
        <div id="fileIdsContainer"></div>
        <button type="submit" class="btn btn-primary">Save</button>

        <div id="commentText_preview" class="form-control mt-3 d-none"></div>
    </form>
    
    {$editor['modal']}
</div>

<div id="modalOverlay" style="display:none; position: fixed; top:0; left:0; width:100vw; height:100vh; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 10000;">
    <div id="modalBox" style="background: white; padding: 1.5em; border-radius: 8px; max-width: 400px; width: 90%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); text-align: center; position: relative;">
        <div id="modalMessage" style="margin-bottom: 1.5em; font-size: 1.1rem;"></div>
        <button id="modalCloseBtn" style="padding: 0.5em 1em; border: none; background: #007bff; color: white; border-radius: 4px; cursor: pointer; font-size: 1rem;">Close</button>
    </div>
</div>

<script>
function showModal(message) {
    const overlay = document.getElementById('modalOverlay');
    const msg = document.getElementById('modalMessage');
    msg.textContent = message;
    overlay.style.display = 'flex';
}

function hideModal() {
    const overlay = document.getElementById('modalOverlay');
    overlay.style.display = 'none';
}

document.getElementById('modalCloseBtn').addEventListener('click', hideModal);
document.getElementById('modalOverlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) hideModal();
});

document.getElementById('commentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showModal(data.message || 'Error submitting comment.');
        } else if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            showModal('Unexpected response from server.');
        }
    })
    .catch(error => {
        showModal('Failed to submit comment.');
        console.error('Error:', error);
    });
});
</script>
HTML;

    // Display recent comments
    displayRecentComments($torrentid);
    stdfoot();
    exit;
}

function displayRecentComments(int $torrentid): void
{
    global $db, $lang;

    $sql = "SELECT c.id, c.torrent AS torrentid, c.text, c.dateline, c.editreason, c.editedby, c.editedat,
                   u.id AS user, u.username, u.usergroup, u.displaygroup, u.usertitle, u.signature,
                   u.lastactive, u.lastvisit, u.invisible, u.postnum, u.threadnum, u.added, u.comms,
                   u.avatar AS useravatar, u.avatardimensions,
                   g.title AS grouptitle, g.namestyle,
                   uu.username AS editedbyuname, gg.namestyle AS editbynamestyle
            FROM comments c
            LEFT JOIN users u ON u.id = c.user
            LEFT JOIN usergroups g ON g.gid = u.usergroup
            LEFT JOIN users uu ON uu.id = c.editedby
            LEFT JOIN usergroups gg ON gg.gid = uu.usergroup
            WHERE c.torrent = ?
            ORDER BY c.id DESC
            LIMIT 0,5";

    $res = $db->sql_query_prepared($sql, [$torrentid]);
    $allrows = [];

    if ($res && $db->num_rows($res) > 0) {
        while ($row = $db->fetch_array($res)) {
            $allrows[] = $row;
        }
    }

    if (count($allrows) > 0) {
        echo <<<HTML
        <div class="container-md">
            <div class="card border-0 mb-4">
                <div class="card-header rounded-bottom text-19 fw-bold">
                    {$lang->comment['order']}
                </div>
            </div>
        </div>
HTML;
        commenttable($allrows);
    }
}

function handleEditAction(): void
{
    global $db, $CURUSER, $is_mod, $BASEURL, $lang;

    $commentid = (int)($_GET['pid'] ?? 0);
    int_check($commentid, true);

    $res = $db->sql_query('SELECT c.*, t.name, t.id as torrentid FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id= ' . $db->escape_string($commentid));
    $arr = $db->fetch_array($res);
    
    if (!$arr) {
        stderr($lang->global['notorrentid']);
    }

    if ($arr['user'] != $CURUSER['id'] && !$is_mod) {
        print_no_permission(true);
    }

    if (!allowcomments((int)$arr['torrentid'])) {
        stderr($lang->comment['closed']);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
        processEditComment($commentid, $arr);
    }

    displayEditCommentForm($commentid, $arr);
}

function processEditComment(int $commentid, array $commentData): void
{
    global $db, $CURUSER;

    $msgtext = trim($_POST['msgtext'] ?? '');
    if (empty($msgtext)) {
        stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
    }

    $update_comment = [
        "text" => $db->escape_string($msgtext),
        "editedat" => TIMENOW,
        "editedby" => $db->escape_string($CURUSER["id"])
    ];

    $db->update_query("comments", $update_comment, "id='" . $commentid . "'");

    if (!empty($_POST['file_ids'])) {
        $file_ids = array_map('intval', (array)$_POST['file_ids']);
        $id_list = implode(',', $file_ids);

        if (!empty($id_list)) {
            $db->sql_query("UPDATE comment_files SET comment_id = " . $commentid . " WHERE id IN ($id_list)");
        }
    }

    $page = (int)($_GET['page'] ?? 0);
    $returnto = get_comment_link($commentid, $commentData['torrentid']) . "#pid{$commentid}";
    
    header('Location: ' . $returnto);
    exit;
}

function displayEditCommentForm(int $commentid, array $commentData): void
{
    global $BASEURL, $lang, $smilies;

    $page = (int)($_GET['page'] ?? 0);
    $actionUrl = htmlspecialchars($_SERVER['SCRIPT_NAME']) . '?action=edit&pid=' . $commentid . ($page ? '&page='.$page : '');
    $returnto = get_comment_link($commentid, $commentData['torrentid']) . "#pid{$commentid}";

    stdhead(sprintf($lang->comment['adit'], $commentData['name']));
    require_once INC_PATH . '/editor.php';
    
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'commentText');

    echo <<<HTML
<div class="container my-4">
    <h2>Edit Comment for: <strong>{$commentData['name']}</strong></h2>
    {$editor['toolbar']}
    
    <form method="post" name="compose" action="{$actionUrl}">
        <input type="hidden" name="returnto" value="{$returnto}">
        <div class="mb-3">
            <label for="commentText" class="form-label">Comment Text</label>
            <textarea class="form-control" id="commentText" name="msgtext" rows="8">{$commentData['text']}</textarea>
        </div>
        <div id="fileIdsContainer"></div>
        <button type="submit" name="submit" class="btn btn-primary">Save Changes</button>
    </form>
    
    {$editor['modal']}
    <hr>
</div>
HTML;

    stdfoot();
    exit;
}

function handleEdit2Action(): void
{
    global $db, $CURUSER, $is_mod, $lang;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $commentid = (int)($input['pid'] ?? 0);
    $torrentid = (int)($input['tid'] ?? 0);
    $msgtext = trim($input['text'] ?? '');

    if ($commentid <= 0 || $torrentid <= 0 || empty($msgtext)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Missing or invalid data.']);
        exit;
    }

    int_check($commentid, true);

    $res = $db->sql_query('SELECT c.*, t.name, t.id AS torrentid FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id = ' . $db->escape_string($commentid) . ' LIMIT 1');
    $arr = $db->fetch_array($res);

    if (!$arr) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $lang->global['notorrentid']]);
        exit;
    }

    if ($arr['user'] != $CURUSER['id'] && !$is_mod) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'You don\'t have permission to edit this comment.']);
        exit;
    }

    if (!allowcomments((int)$arr['torrentid'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $lang->comment['closed']]);
        exit;
    }

    $update_comment = [
        "text" => $db->escape_string($msgtext),
        "editedat" => TIMENOW,
        "editedby" => (int)$CURUSER["id"]
    ];
    
    $db->update_query("comments", $update_comment, "id = " . $commentid);

    // Return updated comment HTML
    $sql = "SELECT c.id, c.torrent AS torrentid, c.text, c.dateline, c.editreason, c.editedby, c.editedat,
                   u.id AS user, u.username, u.usergroup, u.displaygroup, u.usertitle, u.signature,
                   u.lastactive, u.lastvisit, u.invisible, u.avatar AS useravatar, u.avatardimensions,
                   g.title AS grouptitle, g.namestyle,
                   uu.username AS editedbyuname, gg.namestyle AS editbynamestyle,
                   t.name AS torrent_name
            FROM comments c
            LEFT JOIN users u ON u.id = c.user
            LEFT JOIN usergroups g ON g.gid = u.usergroup
            LEFT JOIN users uu ON uu.id = c.editedby
            LEFT JOIN usergroups gg ON gg.gid = uu.usergroup
            LEFT JOIN torrents t ON t.id = c.torrent
            WHERE c.id = ?
            LIMIT 1";

    $q = $db->sql_query_prepared($sql, [$commentid]);
    $row = $db->fetch_array($q);

    if (!$row) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Updated row not found']);
        exit;
    }

    global $torrent, $postcounter;
    $torrent = ['name' => $row['torrent_name'] ?? $arr['name']];
    $postcounter = 1;

    require_once INC_PATH . '/commenttable.php';

    $level = ob_get_level();
    ob_start();
    $html = commenttable([$row], '', '', false, false, true);
    
    while (ob_get_level() > $level) { 
        ob_end_clean(); 
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'pid' => $commentid, 'html' => $html]);
    exit;
}

function handleDeleteAction(): void
{
    global $db, $CURUSER, $is_mod, $BASEURL, $kpscomment;

    if (!$is_mod) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $commentid = (int)($input['pid'] ?? 0);
    $torrentid = (int)($input['tid'] ?? 0);

    int_check([$commentid, $torrentid], true);

    if ($commentid <= 0 || $torrentid <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $res = $db->sql_query('SELECT torrent, user FROM comments WHERE id= ' . $db->escape_string($commentid));
    $arr = $db->fetch_array($res);

    if (!$arr) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }

    $torrentid = $arr['torrent'];
    $userpostid = $arr['user'];

    // Delete attached files
    $files = $db->simple_select("comment_files", "*", "comment_id = " . $commentid);
    while ($file = $db->fetch_array($files)) {
        if (is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->delete_query("comment_files", "comment_id = " . $commentid);

    // Delete comment
    $db->delete_query("comments", "id='$commentid'");

    if ($torrentid && $db->affected_rows() > 0) {
        $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrentid));
        $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $db->escape_string($userpostid));
    }

    kps('-', $kpscomment, $userpostid);

    $torrent_link = $BASEURL . '/' . get_torrent_link($torrentid);
    $log_message = sprintf(
        'User %s (UID %d) deleted a comment (CID %d) from <a href="%s">Torrent #%d</a>',
        $CURUSER['username'],
        (int)$CURUSER['id'],
        $commentid,
        $torrent_link,
        $torrentid
    );

    write_log($log_message);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

function handleMassDeleteAction(): void
{
    global $db, $CURUSER, $BASEURL, $kpscomment, $usergroups;
	
	$is_mod = is_mod($usergroups);

    if (!$is_mod) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    $comment_ids = isset($_POST['comment_ids']) ? explode(',', $_POST['comment_ids']) : [];
    $torrent_ids = isset($_POST['torrent_ids']) ? explode(',', $_POST['torrent_ids']) : [];

    if (empty($comment_ids) || empty($torrent_ids) || count($comment_ids) !== count($torrent_ids)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $deleted_count = 0;
    $errors = [];

    for ($i = 0; $i < count($comment_ids); $i++) {
        $comment_id = (int)$comment_ids[$i];
        $torrent_id = (int)$torrent_ids[$i];

        if ($comment_id <= 0 || $torrent_id <= 0) {
            $errors[] = "Invalid ID pair: comment=$comment_id, torrent=$torrent_id";
            continue;
        }

        $res = $db->sql_query('SELECT id, user FROM comments WHERE id = ' . $db->escape_string($comment_id) . ' AND torrent = ' . $db->escape_string($torrent_id));
        $comment = $db->fetch_array($res);

        if (!$comment) {
            $errors[] = "Comment #$comment_id not found or doesn't belong to torrent #$torrent_id";
            continue;
        }

        $user_id = $comment['user'];

        // Delete files
        $files = $db->simple_select("comment_files", "*", "comment_id = " . $comment_id);
        while ($file = $db->fetch_array($files)) {
            if (is_file($file['file_path'])) {
                @unlink($file['file_path']);
            }
        }
        $db->delete_query("comment_files", "comment_id = " . $comment_id);

        // Delete comment
        $db->delete_query("comments", "id = '$comment_id'");

        if ($db->affected_rows() > 0) {
            $deleted_count++;
            $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrent_id));
            $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $db->escape_string($user_id));
            kps('-', $kpscomment, $user_id);
        }
    }

    if ($deleted_count > 0) {
        $torrent_id = (int)$torrent_ids[0];
        $torrent_link = $BASEURL . '/' . get_torrent_link($torrent_id);
        
        $log_message = sprintf(
            'Mass Comment Delete: User %s (UID %d) deleted %d comment%s from <a href="%s">Torrent #%d</a>',
            $CURUSER['username'],
            (int)$CURUSER['id'],
            $deleted_count,
            $deleted_count > 1 ? 's' : '',
            $torrent_link,
            $torrent_id
        );
        write_log($log_message);
    }

    if ($deleted_count > 0 && empty($errors)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'deleted' => $deleted_count]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Partial success: ' . implode(', ', $errors),
            'deleted' => $deleted_count
        ]);
    }
    exit;
}
?>