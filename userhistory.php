<?php
declare(strict_types=1);

define("THIS_SCRIPT", "userhistory.php");
define("IN_ARCHIVE", true);

require("./global.php");
require_once INC_PATH . '/class_parser.php';

$parser = new postParser;

$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

require_once INC_PATH . '/functions_multipage.php';

$userid = (int)($_GET['id'] ?? 0);

// Permission check with null-safe operator
if (!$userid || ($CURUSER['id'] !== $userid && ($usergroups['canuserdetails'] ?? 0) != 1)) {
    print_no_permission();
}

$lang->load("userdetails");

// Get total comment count
$query = $db->simple_select("comments c", "COUNT(id) AS commentss", "user = '$userid'");
$threadcount = (int)($db->fetch_field($query, "commentss") ?? 0);

// Pagination setup with modern syntax
$ts_perpage = max(1, $ts_perpage ?? 20);
$page = max(1, (int)($mybb->input['page'] ?? 1));
$start = ($page - 1) * $ts_perpage;
$totalPages = (int)ceil($threadcount / $ts_perpage);

// Boundary check
if ($page > $totalPages && $totalPages > 0) {
    $start = 0;
    $page = 1;
}

$multipage = multipage((int)$threadcount, (int)$ts_perpage, (int)$page, $_SERVER['SCRIPT_NAME'] . "?action=viewcomments&id=$userid");

// Get user info with prepared statement
$sql = "SELECT username, usergroup, avatar, avatardimensions FROM users WHERE id = ?";
$params = [$userid];

$User = $db->sql_query_prepared($sql, $params);

if (!$db->num_rows($User)) {
    stderr($lang->userdetails['invaliduser'] ?? 'Invalid user', false);
}

$User = $db->fetch_array($User);
$Username = format_name($User['username'] ?? '', $User['usergroup'] ?? 0);
$useravatar = format_avatar($User['avatar'] ?? '', $User['avatardimensions'] ?? '');

// Avatar rendering with traditional if/else
if (!empty($useravatar['image'])) {
    $avatarImg = '<img src="' . $useravatar['image'] . '" alt="" ' . 
                 ($useravatar['width_height'] ?? '') . 
                 ' class="rounded-circle border shadow-sm animate__animated" style="width:80px;height:80px;">';
} else {
    $avatarImg = '<div class="rounded-circle border shadow-sm bg-light d-flex align-items-center justify-content-center animate__animated" style="width:80px;height:80px;">
                  <i class="fa fa-user text-muted"></i></div>';
}

// Start HTML Output
$Output = '
<div class="container my-5">
  <div class="d-flex align-items-center mb-4">
    ' . $avatarImg . '
    <div class="ms-3">
      <h4 class="mb-0 animate__animated">' . $Username . '</h4>
      <small class="text-muted">' . $threadcount . ' Comment' . ($threadcount === 1 ? '' : 's') . '</small>
    </div>
  </div>
';


// Add pagination at top
if ($ts_perpage < $threadcount) 
{
    echo '<div class="mb-4">' . $multipage . '</div>';
}



// Load Comments
$start = (int)$start;
$ts_perpage = (int)$ts_perpage;

$result = $db->sql_query_prepared(
    "SELECT c.id, c.torrent, c.dateline, c.text, t.name 
     FROM comments c 
     LEFT JOIN torrents t ON (c.torrent = t.id) 
     WHERE c.user = ?
     ORDER BY c.dateline DESC 
     LIMIT ?, ?",
    [$userid, $start, $ts_perpage]
);

if (!$db->num_rows($result)) {
    $Output .= '<div class="alert alert-info animate__animated animate__fadeIn">No comments found.</div>';
} else {
    $animationDelay = 0;
    while ($Comment = $db->fetch_array($result)) {
        $pid = (int)$Comment['id'];
        $tid = (int)$Comment['torrent'];
        $postlink = get_comment_link($pid, $tid);
        $torrentName = htmlspecialchars_uni($Comment['name'] ?: '[Deleted Torrent]');
        $commentDate = my_datee(($dateformat ?? 'Y-m-d') . ' - ' . ($timeformat ?? 'H:i:s'), $Comment['dateline']);
        $parsedText = $parser->parse_message($Comment['text'] ?? '', $parser_options);

        $Output .= '
        <div class="card mb-4 shadow-sm comment-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="text-muted small">
                  <i class="fa-regular fa-clock me-1"></i>' . $commentDate . '
                </div>
                <h5 class="card-title mt-1 mb-0">
                  <a href="' . $postlink . '" class="text-decoration-none text-dark">
                    <i class="fa fa-magnet me-1 text-primary"></i>' . $torrentName . '
                  </a>
                </h5>
              </div>
              <div>
                <a href="' . $postlink . '#pid' . $pid . '" class="btn btn-sm btn-outline-secondary" title="Permalink">
                  <i class="fa fa-link"></i>
                </a>
              </div>
            </div>
            <hr class="mt-1 mb-3">
            <div class="comment-body">
              ' . $parsedText . '
            </div>
          </div>
        </div>';
    }
}

$Output .= '</div>';

// Page header with null-safe operator
$pageTitle = sprintf($lang->userdetails['chistory'] ?? 'Comment History for %s', $User['username'] ?? 'Unknown User');
stdhead($pageTitle);



// Render pagination if needed
if ($ts_perpage < $threadcount) {
    $Output .= '<div class="container mb-4">' . $multipage . '</div>';
}

echo $Output;

stdfoot();
?>

<style>
.comment-card {
    transition: all 0.3s ease-in-out;
    border-left: 4px solid transparent;
}
.comment-card:hover {
    border-left-color: #0d6efd;
    background-color: #f8f9fa;
    transform: translateX(5px) translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}
.comment-body {
    font-size: 0.95rem;
    line-height: 1.5;
}
.card-title a:hover {
    text-decoration: underline !important;
    color: #0a58ca !important;
}

/* Анимация для аватара при загрузке */
.rounded-circle.animate__animated {
    animation: animate__bounceIn 1s ease-out;
}

/* Анимация для заголовка */
h4.animate__animated {
    animation: animate__fadeInDown 0.8s ease-out;
}
</style>
