<?php
declare(strict_types=1);

define('IN_MYBB',    1);
define('SCRIPTNAME', 'announcements.php');



define('IN_FORUM', true);
require_once 'global.php';
require_once INC_PATH . '/functions_post.php';

$lang->load('announcements');

$aid = $mybb->get_input('aid', MyBB::INPUT_INT);

// ── Get announcement fid — новая таблица, поле id вместо aid ─────
$query        = $db->sql_query_prepared("SELECT fid FROM announcements WHERE id = ?", [$aid]);
$announcement = $db->fetch_array($query);

$plugins->run_hooks('announcements_start');



// ── Forum permissions ────────────────────────────────────────────
$fid = (int)$announcement['fid'];

if ($fid > 0) {
    $forum = get_forum($fid);
    if (!$forum) stderr('error_invalidforum');

    build_forum_breadcrumb($forum['fid']);

    $forumpermissions = forum_permissions($forum['fid']);
    if ($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0) {
        print_no_permission();
    }

    check_forum_password($forum['fid']);
}

// ── Get full announcement — фильтр по type forum/global, id вместо aid ──
$time  = TIMENOW;




$query = $db->sql_query_prepared("
    SELECT a.id AS announcement_id,
           a.subject, a.message, a.uid, a.added, a.updated,
           a.views, a.startdate, a.enddate, a.fid, a.type,
           u.id AS user_id,
           u.username, u.avatar, u.avatardimensions,
           u.usergroup, u.displaygroup, u.usertitle,
           u.signature, u.postnum, u.threadnum,
           u.lastactive, u.lastvisit, u.invisible
    FROM announcements a
    LEFT JOIN users u ON (u.id = a.uid)
    WHERE a.type IN ('forum', 'global')
      AND a.startdate <= ?
      AND (a.enddate >= ? OR a.enddate = '0')
      AND a.id = ?
", [$time, $time, $aid]);


$announcementarray = $db->fetch_array($query);



if (!$announcementarray) {
    stderr($lang->announcements['error_invalidannouncement']);
}







// ── Usergroup data ───────────────────────────────────────────────
foreach (['title' => 'grouptitle', 'usertitle' => 'groupusertitle',
          'image' => 'groupimage', 'namestyle' => 'namestyle'] as $field => $key) {
    $announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
}


$announcementarray['id'] = $announcementarray['user_id'];

$announcementarray['aid'] = $announcementarray['announcement_id'];

$announcementarray['dateline']     = $announcementarray['startdate'];
$announcementarray['userusername'] = $announcementarray['username'];

$announcement = build_postbit($announcementarray, 3);
$announcementarray['subject'] = $parser->parse_badwords($announcementarray['subject']);

$forum_announcement = sprintf(
    $lang->announcements['forum_announcement'],
    htmlspecialchars_uni($announcementarray['subject'])
);

// ── Cookie — id вместо aid ───────────────────────────────────────
if ($announcementarray['startdate'] > $CURUSER['lastvisit']) {
    $setcookie = true;

    if (isset($mybb->cookies['mybb']['announcements']) &&
        is_scalar($mybb->cookies['mybb']['announcements'])) {
        $cookie = my_unserialize(stripslashes($mybb->cookies['mybb']['announcements']), false);
        if (isset($cookie[$announcementarray['id']])) {
            $setcookie = false;
        }
    }

    if ($setcookie) {
        my_set_array_cookie('announcements', (string)$announcementarray['id'], $announcementarray['startdate'], -1);
    }
}

$plugins->run_hooks('announcements_end');

$forumannouncement = '<html>
<head>
<title>'.$lang->announcements['forum_announcement'].'</title>

<script type="text/javascript">
var announcement_quickdelete_confirm = "'.$lang->announcements['announcement_quickdelete_confirm'].'";
</script>
</head>
<body>
	<div class="container-md">
		
		<div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.$forum_announcement.'
	</div>
		</div>
	'.$announcement.'		
	</div>
</body>
</html>';




stdhead('title');
build_breadcrumb();
echo $forumannouncement;



?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Announcement</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" tabindex="-1"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning fw-bold" id="deleteTitle"></div>
                <p class="text-danger mb-0"><strong>Cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function afDelete(id, subject) {
    document.getElementById('deleteTitle').textContent = subject;
    document.getElementById('deleteConfirmBtn').href =
        'admin/index.php?act=announcements_forum&action=delete&id=' + id + '&sure=yes';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php



stdfoot();