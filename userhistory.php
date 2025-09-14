<?php
define("THIS_SCRIPT", "userhistory.php");
define("IN_ARCHIVE", true);
require("./global.php");
require_once(INC_PATH . '/class_parser.php');
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
if (!$userid || ($CURUSER['id'] != $userid && $usergroups['canuserdetails'] != '1')) 
{
    print_no_permission();
}

$lang->load("userdetails");

// Get total comment count
$query = $db->simple_select("comments c", "COUNT(id) AS commentss", "user = '$userid'");
$threadcount = $db->fetch_field($query, "commentss");

// Pagination setup
$ts_perpage = ($ts_perpage > 0) ? $ts_perpage : 20;
$page = max(1, (int)($mybb->input['page'] ?? 1));
$start = ($page - 1) * $ts_perpage;
$totalPages = ceil($threadcount / $ts_perpage);

if ($page > $totalPages) {
    $start = 0;
    $page = 1;
}

$multipage = multipage($threadcount, $ts_perpage, $page, $_SERVER['SCRIPT_NAME'] . "?action=viewcomments&id=$userid");

// Get user info
$sql = "
    SELECT username, usergroup, avatar, avatardimensions
    FROM users
    WHERE id = ?
";

$params = [(int)$userid]; // приводим к int для безопасности

$User = $db->sql_query_prepared($sql, $params);



if (!$db->num_rows($User)) 
{
    stderr($lang->userdetails['invaliduser'], false);
}
$User = $db->fetch_array($User);
$Username = format_name($User['username'], $User['usergroup']);
$useravatar = format_avatar($User['avatar'], $User['avatardimensions']);
$avatarImg = '<img src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' class="rounded-circle border shadow-sm" style="width:80px;height:80px;">';

// Start HTML Output
$Output = '
<div class="container my-5">
  <div class="d-flex align-items-center mb-4">
    ' . $avatarImg . '
    <div class="ms-3">
      <h4 class="mb-0">' . $Username . '</h4>
      <small class="text-muted">' . ts_nf($threadcount) . ' Comment' . ($threadcount == 1 ? '' : 's') . '</small>
    </div>
  </div>
';

// Load Comments
$sql = "
    SELECT c.id, c.torrent, c.dateline, c.text, t.name
    FROM comments c
    LEFT JOIN torrents t ON (c.torrent = t.id)
    WHERE c.user = ?
    ORDER BY c.dateline DESC
    LIMIT ?, ?
";

// Приводим к int для безопасности
$params = [(int)$userid, (int)$start, (int)$ts_perpage];

$Comments = $db->sql_query_prepared($sql, $params);







if (!$db->num_rows($Comments)) {
    $Output .= '<div class="alert alert-info">No comments found.</div>';
} else {
    while ($Comment = $db->fetch_array($Comments)) {
        $pid = $Comment['id'];
        $tid = $Comment['torrent'];
        $postlink = get_comment_link($pid, $tid);
        $torrentName = htmlspecialchars_uni($Comment['name'] ?: '[Deleted Torrent]');

        $Output .= '
        <div class="card mb-4 shadow-sm comment-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="text-muted small">
                  <i class="fa-regular fa-clock me-1"></i>' . my_datee($dateformat . ' - ' . $timeformat, $Comment['dateline']) . '
                </div>
                <h5 class="card-title mt-1 mb-0">
                  <a href="' . get_torrent_link($tid) . '" class="text-decoration-none text-dark">
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
              ' . $parser->parse_message($Comment['text'], $parser_options) . '
            </div>
          </div>
        </div>';
    }
}

$Output .= '</div>';

stdhead(sprintf($lang->userdetails['chistory'], $User['username']));

// Render pagination if needed
if ($ts_perpage < $threadcount) {
    $Output .= '<div class="container mb-4">' . $multipage . '</div>';
}

echo $Output;

stdfoot();
?>
<style>
.comment-card:hover {
  border-left: 4px solid #0d6efd;
  background-color: #f9f9f9;
  transition: all 0.2s ease-in-out;
}
.comment-body {
  font-size: 0.95rem;
  line-height: 1.5;
}
.card-title a:hover {
  text-decoration: underline;
}
</style>
