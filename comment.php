<?php


declare(strict_types=1);



function allowcomments(int $torrentid = 0): bool
{
    global $is_mod, $db;
    
    $query = $db->sql_query_prepared("SELECT allowcomments FROM torrents WHERE id = ?", [$torrentid]);
    $result = $query ? $db->fetch_array($query) : null;
    
    return !($result["allowcomments"] != 'yes' && !$is_mod);
}

define("SCRIPTNAME", "comment.php");
define("IN_MYBB", 1);
define('C_VERSION', '1.8.8');
define("IN_ARCHIVE", true);

require_once 'global.php';
require_once 'cache/smilies.php';

require_once INC_PATH . '/flood_check.php';

require_once INC_PATH.'/class_parser.php';
require_once INC_PATH.'/datahandler.php';

$parser = new postParser();

$parser_options = [
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

gzip();
maxsysop();





if (empty($CURUSER['id'])) {
    print_no_permission();
    exit;
}

$lang->load('comment');


$query = $db->sql_query_prepared("SELECT cancomment FROM users WHERE id = ?", [$CURUSER['id']]);
$commentperm = $query ? $db->fetch_array($query) : null;
if ((int)($commentperm['cancomment'] ?? 1) === 0) {
    stderr(
        $lang->comment['no_comment_permission'] ?? 'You do not have permission to post comments. Contact staff if you believe this is a mistake.',
        '',
        403,
        '403'
    );
}




require INC_PATH . '/commenttable.php';
require_once INC_PATH . '/functions_comment_attachments.php';

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
    'merge' => handleMergeAction(),
    default => stderr($lang->global['error'], $lang->global['invalidaction'])
};

exit;

// Action handler functions
function handleCloseAction(): void
{
    global $db, $usergroups, $lang;

    if (!is_mod($usergroups)) {
        print_no_permission();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($_POST['my_post_key'] ?? '')) {
        stderr($lang->global['error'] ?? 'Error', 'Invalid security token. Please refresh the page and try again.', 403, '403');
    }

    $torrentid = (int)($_GET['tid'] ?? 0);
    int_check($torrentid, true);
    $db->sql_query_prepared('UPDATE torrents SET allowcomments = \'no\' WHERE id = ?', [$torrentid]);
    redirect('details.php?id=' . $torrentid . '&tab=comments');
    exit;
}

function handleOpenAction(): void
{
    global $db, $usergroups, $lang;

    if (!is_mod($usergroups)) {
        print_no_permission();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($_POST['my_post_key'] ?? '')) {
        stderr($lang->global['error'] ?? 'Error', 'Invalid security token. Please refresh the page and try again.', 403, '403');
    }

    $torrentid = (int)($_GET['tid'] ?? 0);
    int_check($torrentid, true);
    $db->sql_query_prepared('UPDATE torrents SET allowcomments = \'yes\' WHERE id = ?', [$torrentid]);
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

    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        if (($_POST['ctype'] ?? '') == 'quickcomment') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token. Please refresh the page and try again.']);
            exit;
        }
        stderr($lang->global['error'] ?? 'Error', 'Invalid security token. Please refresh the page and try again.', 403, '403');
    }

    $query = $db->sql_query_prepared('SELECT dateline FROM comments WHERE user = ? ORDER BY dateline DESC LIMIT 1', [$CURUSER['id']]);
    $last_comment = 0;

    if ($query && $db->num_rows($query) > 0) {
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

    $res = $db->sql_query_prepared("SELECT name, owner FROM torrents WHERE id = ?", [$torrentid]);
    $arr = $res ? $db->fetch_array($res) : null;

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

    $query = $db->sql_query_prepared('SELECT id, user, text FROM comments WHERE torrent = ? ORDER BY dateline DESC LIMIT 1', [$torrentid]);
    $lastcomment = $query ? $db->fetch_array($query) : null;
    $lastcommentuserid = $lastcomment['user'] ?? null;

    if ($lastcommentuserid && $lastcommentuserid == $CURUSER['id'] && !$is_mod) {
        // Append to existing comment
        $text = $lastcomment['text'] . "\n[hr]\n" . $msgtext;
        $db->sql_query_prepared("UPDATE comments SET text = ? WHERE id = ?", [$text, $lastcomment['id']]);
        $newid = $lastcomment['id'];
    } else {
        // Create new comment
        $db->sql_query_prepared(
            "INSERT INTO comments (`user`,`torrent`,`dateline`,`text`) VALUES (?,?,?,?)",
            [$CURUSER['id'], $torrentid, TIMENOW, $msgtext]
        );
        $newid = $db->insert_id();

        // Attach uploaded files via posthash
        $posthash = trim($_POST['posthash'] ?? '');
        if ($posthash) {
            attach_to_comment($posthash, $newid, (int)$CURUSER['id']);
        }

        // Update counters
        $db->sql_query_prepared("UPDATE torrents SET comments = comments+1 WHERE id = ?", [$torrentid]);
        $db->sql_query_prepared("UPDATE users SET comms = comms+1 WHERE id = ?", [$CURUSER['id']]);

        // Send PM notification
        if ($CURUSER['id'] != $arr['owner']) {
            $ras = $db->sql_query_prepared('SELECT commentpm FROM users WHERE id = ?', [$arr['owner']]);
            $arg = $ras ? $db->fetch_array($ras) : null;

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
    global $db, $CURUSER, $BASEURL, $lang, $smilies, $mybb;

    $res = $db->sql_query_prepared("SELECT name, owner FROM torrents WHERE id = ?", [$torrentid]);
    $arr = $res ? $db->fetch_array($res) : null;

    if (!$arr) {
        stderr($lang->global['notorrentid']);
    }

    stdhead(sprintf($lang->comment['addcomment'], $arr['name']), true, 'supernote');

    echo '
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_attachments.css">
    <link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_form.css">
    <script defer src="' . $BASEURL . '/scripts/comment_form.js"></script>
    ';

    require_once INC_PATH . '/editor.php';

    $editor   = insert_bbcode_editor($smilies, $BASEURL, 'commentText');
    $posthash = bin2hex(random_bytes(16));
    $uploader = render_attachment_uploader($posthash, (int)$CURUSER['id']);
    $titlez   = sprintf($lang->comment['addcomment'], htmlspecialchars_uni($arr['name']));

    $prefill = '';
    if (!empty($_GET['quote'])) {
        $prefill = htmlspecialchars(urldecode($_GET['quote']));
    }

    $myPostKey = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES);

    echo <<<HTML
    <div class="container mt-3">
        <div class="comment-form-wrapper">

            <div class="comment-form-title">
                <i class="fa-regular fa-pen-to-square"></i>
                {$titlez}
            </div>

            {$editor['toolbar']}

            <form id="commentForm" method="post" name="compose"
                  action="{$_SERVER['SCRIPT_NAME']}?action=add&tid={$torrentid}" novalidate>

                <div class="mb-3">
                    <label for="commentText" class="comment-form-label">
                        <i class="fa-regular fa-message me-1"></i> {$lang->comment['insertcomment']}
                    </label>
                    <textarea class="form-control comment-textarea"
                              id="commentText"
                              name="msgtext"
                              rows="6"
                              placeholder="Write your comment using BBCode..."
                              maxlength="500"
                              aria-describedby="charCount"
                              required>{$prefill}</textarea>
                    <div id="charCount" class="comment-char-count">0 / 500</div>
                </div>

                {$uploader}

                <input type="hidden" name="ctype" value="quickcomment">
                <input type="hidden" name="submit" value="1">
                <input type="hidden" name="my_post_key" value="{$myPostKey}">
                <div id="fileIdsContainer"></div>

                <div class="d-flex flex-wrap gap-3 mt-3 justify-content-end">
                    <button type="submit" class="btn btn-primary comment-submit-btn">
                        <i class="fa-regular fa-paper-plane"></i> Save Comment
                    </button>
                    <button type="reset" class="btn btn-outline-secondary comment-submit-btn">
                        <i class="fa-regular fa-rotate-right"></i> Clear
                    </button>
                </div>

                <div id="commentText_preview" class="comment-preview-box"></div>

            </form>

            {$editor['modal']}
        </div>
    </div>

    <div id="modalOverlay" class="custom-modal-overlay">
        <div class="custom-modal-box">
            <span id="modalIcon" class="modal-icon info">ℹ️</span>
            <div id="modalMessage" class="modal-message">Loading...</div>
            <button id="modalCloseBtn" class="modal-close-btn">Got it!</button>
        </div>
    </div>
HTML;

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

    $res = $db->sql_query_prepared('SELECT c.*, t.name, t.id as torrentid FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id = ?', [$commentid]);
    $arr = $res ? $db->fetch_array($res) : null;
    
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
    global $db, $CURUSER, $lang;

    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        stderr($lang->global['error'] ?? 'Error', 'Invalid security token. Please refresh the page and try again.', 403, '403');
    }

    $msgtext = trim($_POST['msgtext'] ?? '');
    if (empty($msgtext)) {
        stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
    }

    $db->sql_query_prepared(
        "UPDATE comments SET text = ?, editedat = ?, editedby = ? WHERE id = ?",
        [$msgtext, TIMENOW, $CURUSER["id"], $commentid]
    );

    // Attach new uploads via posthash
    $posthash = trim($_POST['posthash'] ?? '');
    if ($posthash) {
        attach_to_comment($posthash, $commentid, (int)$CURUSER['id']);
    }

    $page = (int)($_GET['page'] ?? 0);
    $returnto = get_comment_link($commentid, $commentData['torrentid']) . "#pid{$commentid}";
    
    header('Location: ' . $returnto);
    exit;
}

function displayEditCommentForm(int $commentid, array $commentData): void
{
    global $BASEURL, $CURUSER, $lang, $smilies, $mybb;

    $page = (int)($_GET['page'] ?? 0);
    $actionUrl = htmlspecialchars($_SERVER['SCRIPT_NAME']) . '?action=edit&pid=' . $commentid . ($page ? '&page='.$page : '');
    $returnto = get_comment_link($commentid, $commentData['torrentid']) . "#pid{$commentid}";
    $myPostKey = htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES);

    stdhead(sprintf($lang->comment['adit'], $commentData['name']));
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_attachments.css">';
    require_once INC_PATH . '/editor.php';
    
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'commentText');

    // Load existing attachments for this comment
    $existing_atts = get_comment_attachments($commentid);
    $posthash = bin2hex(random_bytes(16));
    $uploader = render_attachment_uploader($posthash, (int)$CURUSER['id'], $existing_atts, $commentid);

    echo <<<HTML
<div class="container my-4">
    <h2>Edit Comment for: <strong>{$commentData['name']}</strong></h2>
    {$editor['toolbar']}
    
    <form method="post" name="compose" action="{$actionUrl}">
        <input type="hidden" name="returnto" value="{$returnto}">
        <input type="hidden" name="my_post_key" value="{$myPostKey}">
        <div class="mb-3">
            <label for="commentText" class="form-label">Comment Text</label>
            <textarea class="form-control" id="commentText" name="msgtext" rows="8">{$commentData['text']}</textarea>
        </div>
        {$uploader}
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

    if (!verify_post_check($input['my_post_key'] ?? '')) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }

    $commentid = (int)($input['pid'] ?? 0);
    $torrentid = (int)($input['tid'] ?? 0);
    $msgtext = trim($input['text'] ?? '');

    if ($commentid <= 0 || $torrentid <= 0 || empty($msgtext)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Missing or invalid data.']);
        exit;
    }

    int_check($commentid, true);

    $res = $db->sql_query_prepared('SELECT c.*, t.name, t.id AS torrentid FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id = ? LIMIT 1', [$commentid]);
    $arr = $res ? $db->fetch_array($res) : null;

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

    $db->sql_query_prepared(
        "UPDATE comments SET text = ?, editedat = ?, editedby = ? WHERE id = ?",
        [$msgtext, TIMENOW, (int)$CURUSER["id"], $commentid]
    );

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
    $row = $q ? $db->fetch_array($q) : null;

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

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!verify_post_check($input['my_post_key'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }

    $commentid = (int)($input['pid'] ?? 0);
    $torrentid = (int)($input['tid'] ?? 0);

    int_check([$commentid, $torrentid], true);

    if ($commentid <= 0 || $torrentid <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $res = $db->sql_query_prepared('SELECT torrent, user FROM comments WHERE id = ?', [$commentid]);
    $arr = $res ? $db->fetch_array($res) : null;

    if (!$arr) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }

    // Владелец комментария или модератор — было: только модератор, поэтому
    // обычные пользователи не могли удалить даже свой собственный комментарий
    // (та же проверка владения, что уже используется для edit-действий ниже
    // по этому же файлу).
    if ($arr['user'] != $CURUSER['id'] && !$is_mod) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    $torrentid = $arr['torrent'];
    $userpostid = $arr['user'];

    // Delete attached files from attachments table
    delete_comment_attachments($commentid);
	
	// Delete attached files
    $files = $db->sql_query_prepared("SELECT * FROM comment_files WHERE comment_id = ?", [$commentid]);
    while ($files && ($file = $db->fetch_array($files))) {
        if (is_file($file['file_path'])) {
            @unlink($file['file_path']);
        }
    }
    $db->sql_query_prepared("DELETE FROM comment_files WHERE comment_id = ?", [$commentid]);
	

    // Delete comment
    $db->sql_query_prepared("DELETE FROM comments WHERE id = ?", [$commentid]);

    if ($torrentid && $db->affected_rows() > 0) {
        $db->sql_query_prepared('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ?', [$torrentid]);
        $db->sql_query_prepared('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ?', [$userpostid]);
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($_POST['my_post_key'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page and try again.']);
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

        $res = $db->sql_query_prepared('SELECT id, user FROM comments WHERE id = ? AND torrent = ?', [$comment_id, $torrent_id]);
        $comment = $res ? $db->fetch_array($res) : null;

        if (!$comment) {
            $errors[] = "Comment #$comment_id not found or doesn't belong to torrent #$torrent_id";
            continue;
        }

        $user_id = $comment['user'];

        // Delete attached files from attachments table
        delete_comment_attachments($comment_id);
		
		
		// Delete files
        $files = $db->sql_query_prepared("SELECT * FROM comment_files WHERE comment_id = ?", [$comment_id]);
        while ($files && ($file = $db->fetch_array($files))) {
            if (is_file($file['file_path'])) {
                @unlink($file['file_path']);
            }
        }
        $db->sql_query_prepared("DELETE FROM comment_files WHERE comment_id = ?", [$comment_id]);
		

        // Delete comment
        $db->sql_query_prepared("DELETE FROM comments WHERE id = ?", [$comment_id]);

        if ($db->affected_rows() > 0) {
            $deleted_count++;
            $db->sql_query_prepared('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ?', [$torrent_id]);
            $db->sql_query_prepared('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ?', [$user_id]);
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

function handleMergeAction(): void
{
    global $db, $CURUSER, $BASEURL, $usergroups;

    $is_mod = is_mod($usergroups);
    if (!$is_mod) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    if (!verify_post_check($_POST['my_post_key'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }

    $comment_ids = isset($_POST['comment_ids']) ? explode(',', (string)$_POST['comment_ids']) : [];
    $comment_ids = array_map('intval', $comment_ids);
    $comment_ids = array_values(array_unique(array_filter($comment_ids, fn($v) => $v > 0)));

    if (count($comment_ids) < 2) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Select at least 2 comments to merge.']);
        exit;
    }

    // Тянем выбранные комментарии, от старого к новому
    $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
    $res = $db->sql_query_prepared(
        "SELECT id, torrent, user, text, dateline FROM comments WHERE id IN ({$placeholders}) ORDER BY dateline ASC, id ASC",
        $comment_ids
    );

    $comments = [];
    while ($row = $db->fetch_array($res)) {
        $comments[] = $row;
    }

    if (count($comments) !== count($comment_ids)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'One or more selected comments were not found.']);
        exit;
    }

    // Все комментарии должны быть из одного торрента (у комментария ровно один "дом" — торрент, в отличие
    // от форумных постов, которые можно физически перенести между тредами)
    $torrentid = (int)$comments[0]['torrent'];
    foreach ($comments as $c) {
        if ((int)$c['torrent'] !== $torrentid) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'All selected comments must belong to the same torrent.']);
            exit;
        }
    }

    // Мастер-комментарий — самый старый; остальные склеиваются в него через [hr] и удаляются.
    // Автор мастер-комментария не меняется, даже если остальные — от других юзеров (как в merge_posts на форуме).
    $master   = array_shift($comments);
    $masterid = (int)$master['id'];

    $mergedText   = $master['text'];
    $mergedIds    = [];
    $userCounts   = []; // userid => сколько его комментариев поглощено, для пересчёта users.comms
    foreach ($comments as $c) {
        $mergedText .= "\n[hr]\n" . $c['text'];
        $mergedIds[] = (int)$c['id'];
        $cuid = (int)$c['user'];
        $userCounts[$cuid] = ($userCounts[$cuid] ?? 0) + 1;
    }

    // Переносим вложения на мастер-комментарий вместо удаления —
    // в проекте два параллельных хранилища вложений комментариев:
    // attachments (изображения/файлы через render_attachment_uploader) и comment_files (.attach-файлы)
    [$ph, $mergedParams] = [implode(',', array_fill(0, count($mergedIds), '?')), $mergedIds];
    $db->sql_query_prepared("UPDATE attachments SET comment_id = ? WHERE comment_id IN ({$ph})", [$masterid, ...$mergedParams]);
    $db->sql_query_prepared("UPDATE comment_files SET comment_id = ? WHERE comment_id IN ({$ph})", [$masterid, ...$mergedParams]);

    // Обновляем мастер-комментарий
    $db->sql_query_prepared(
        "UPDATE comments SET text = ?, editedat = ?, editedby = ? WHERE id = ?",
        [$mergedText, TIMENOW, (int)$CURUSER['id'], $masterid]
    );

    // Удаляем поглощённые комментарии
    $db->sql_query_prepared("DELETE FROM comments WHERE id IN ({$ph})", $mergedParams);

    // Обновляем счётчики (N комментариев стало одним)
    $removed = count($mergedIds);
    $db->sql_query_prepared('UPDATE torrents SET comments = IF(comments > ?, comments - ?, 0) WHERE id = ?', [$removed, $removed, $torrentid]);
    foreach ($userCounts as $cuid => $cnt) {
        $db->sql_query_prepared('UPDATE users SET comms = IF(comms > ?, comms - ?, 0) WHERE id = ?', [$cnt, $cnt, $cuid]);
    }

    $torrent_link = $BASEURL . '/' . get_torrent_link($torrentid);
    $log_message = sprintf(
        'Comment Merge: User %s (UID %d) merged %d comment(s) into comment #%d on <a href="%s">Torrent #%d</a>',
        $CURUSER['username'],
        (int)$CURUSER['id'],
        $removed,
        $masterid,
        $torrent_link,
        $torrentid
    );
    write_log($log_message);

    // Возвращаем обновлённый HTML мастер-комментария (та же схема, что и в handleEdit2Action)
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

    $q   = $db->sql_query_prepared($sql, [$masterid]);
    $row = $q ? $db->fetch_array($q) : null;

    global $torrent, $postcounter;
    $torrent     = ['name' => $row['torrent_name'] ?? ''];
    $postcounter = 1;

    require_once INC_PATH . '/commenttable.php';

    $level = ob_get_level();
    ob_start();
    $html = commenttable([$row], '', '', false, false, true);
    while (ob_get_level() > $level) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'     => true,
        'master_id'   => $masterid,
        'removed_ids' => $mergedIds,
        'html'        => $html,
    ]);
    exit;
}
?>