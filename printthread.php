<?php
declare(strict_types=1);

define('IN_MYBB',    1);
define('THIS_SCRIPT','printthread.php');
define('SCRIPTNAME', 'printthread.php');
define('IN_FORUM',   true);

require_once 'global.php';
require_once INC_PATH . '/functions_parent_list.php';
require_once INC_PATH . '/functions_post.php';

$lang->load('printthread');

$plugins->run_hooks('printthread_start');

// ── Тред ─────────────────────────────────────────────────────────────────────
$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));

if (!$thread || $thread['visible'] == -1) {
    stderr($lang->printthread['error_invalidthread'] ?? 'Invalid thread.', '', 404, '404');
}



$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
$fid = (int)$thread['fid'];
$tid = (int)$thread['tid'];

// ── Форум ─────────────────────────────────────────────────────────────────────
$forum = get_forum($fid);
if (!$forum || $forum['type'] !== 'f') {
    stderr($lang->printthread['error_invalidforum'] ?? 'Invalid forum.', '', 404, '404');
}

$forumpermissions = forum_permissions($fid);
if (
    $forumpermissions['canview'] == 0 ||
    $forumpermissions['canviewthreads'] == 0 ||
    (isset($forumpermissions['canonlyviewownthreads']) &&
     $forumpermissions['canonlyviewownthreads'] != 0 &&
     $thread['uid'] != $CURUSER['id'])
) {
    print_no_permission();
}

check_forum_password($fid);

// ── Пагинация ─────────────────────────────────────────────────────────────────
$f_postsperpage = (int)($f_postsperpage ?? 0);
$perpage        = $f_postsperpage >= 1 ? $f_postsperpage : 20;
$postcount      = (int)$thread['replies'] + 1;
$pages          = (int)ceil($postcount / $perpage);
$page           = max(1, $mybb->get_input('page', MyBB::INPUT_INT));

if ($page > $pages) $page = 1;
$start = ($page - 1) * $perpage;

$multipage = $postcount > $perpage
    ? printthread_multipage($postcount, $perpage, $page, "printthread.php?tid={$tid}")
    : '';

$thread['threadlink'] = get_thread_link($tid);

// ── Посты ─────────────────────────────────────────────────────────────────────
$postrow_cache = [];
$attachcache   = [];

$query = $db->sql_query_prepared("
    SELECT u.*, u.username AS userusername, p.*
    FROM posts p
    LEFT JOIN users u ON (u.id = p.uid)
    WHERE p.tid = ? AND p.visible = '1'
    ORDER BY p.dateline, p.pid
    LIMIT ?, ?
", [$tid, $start, $perpage]);

while ($query && ($postrow = $db->fetch_array($query))) {
    $postrow_cache[$postrow['pid']] = $postrow;
}

$postrow_cache = array_filter($postrow_cache);

if (!empty($postrow_cache)) {
    $pids = array_keys($postrow_cache);
    $placeholders = implode(',', array_fill(0, count($pids), '?'));

    $queryAttachments = $db->sql_query_prepared("SELECT * FROM attachments WHERE pid IN ({$placeholders})", $pids);
    while ($queryAttachments && ($attachment = $db->fetch_array($queryAttachments))) {
        $attachcache[$attachment['pid']][$attachment['aid']] = $attachment;
    }
}

// ── Сборка постов ─────────────────────────────────────────────────────────────
$postrows = '';

foreach ($postrow_cache as $postrow) {
    if ($postrow['userusername']) {
        $postrow['username'] = $postrow['userusername'];
    }

    $postrow['username']    = htmlspecialchars_uni($postrow['username']);
    $postrow['subject']     = htmlspecialchars_uni($parser->parse_badwords($postrow['subject']));
    $postrow['date']        = my_datee($dateformat ?? '', $postrow['dateline'], '', 0);
    $postrow['profilelink'] = build_profile_link($postrow['username'], (int)$postrow['uid']);

    $parser_options = [
        'allow_html'      => 1,
        'allow_mycode'    => 1,
        'allow_smilies'   => 1,
        'allow_imgcode'   => 1,
        'allow_videocode' => 1,
        'me_username'     => $postrow['username'],
        'shorten_urls'    => 0,
        'filter_badwords' => 1,
    ];

    $postrow['message'] = $parser->parse_message($postrow['message'], $parser_options);

    get_post_attachments((int)$postrow['pid'], $postrow);

    $plugins->run_hooks('printthread_post');

    $postrows .= '
<div class="print-post mb-4 pb-4 border-bottom">
    <div class="print-post-header mb-2">
        <strong>' . $postrow['subject'] . '</strong>
        &mdash; ' . $postrow['profilelink'] . '
        &mdash; <strong>' . $postrow['date'] . '</strong>
    </div>
    <div class="print-post-body">
        ' . $postrow['message'] . '
    </div>
</div>';
}

$plugins->run_hooks('printthread_end');

// ── Хлебные крошки и навигация ────────────────────────────────────────────────
$breadcrumb = makeprintablenav();

$parentsexp = explode(',', $forum['parentlist']);
$tdepth     = str_repeat('-', count($parentsexp) + 1);

// ── Вывод ─────────────────────────────────────────────────────────────────────
// FIX: убраны <html><head><body> и таблицы — используем stdhead/stdfoot
stdhead($thread['subject'] . ' — ' . ($lang->printthread['printable_version'] ?? 'Printable Version'));
?>
<style>
@media print {
    .no-print { display: none !important; }
    .print-post { page-break-inside: avoid; }
}
.print-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #dee2e6; }
.print-nav    { font-size: .875rem; color: #6c757d; margin-bottom: 1rem; }
</style>

<div class="container-md py-4">

    <div class="print-header">
        <h4>
            <?= $thread['displaystyle'] ?>
            <a href="<?= htmlspecialchars($thread['threadlink'], ENT_QUOTES, 'UTF-8') ?>">
                <?= $thread['subject'] ?>
            </a>
            <small class="text-muted">— <?= htmlspecialchars($lang->printthread['printable_version'] ?? 'Printable Version', ENT_QUOTES, 'UTF-8') ?></small>
        </h4>
        <div class="print-nav">
            +- <?= htmlspecialchars($SITENAME, ENT_QUOTES, 'UTF-8') ?>
            (<em><?= htmlspecialchars($BASEURL, ENT_QUOTES, 'UTF-8') ?></em>)<br>
            <?= $breadcrumb ?>
            +<?= $tdepth ?> <?= htmlspecialchars($lang->printthread['thread'] ?? 'Thread', ENT_QUOTES, 'UTF-8') ?>
            <?= $thread['displaystyle'] ?> <?= $thread['subject'] ?>
        </div>
        <?php if ($multipage): ?>
        <div class="mb-2"><?= $multipage ?></div>
        <?php endif; ?>
    </div>

    <?= $postrows ?: '<p class="text-muted">No posts found.</p>' ?>

    <?php if ($multipage): ?>
    <div class="mt-4"><?= $multipage ?></div>
    <?php endif; ?>

    <div class="mt-4 no-print">
        <a href="<?= htmlspecialchars($thread['threadlink'], ENT_QUOTES, 'UTF-8') ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>
            <?= htmlspecialchars($lang->printthread['return_to_thread'] ?? 'Return to thread', ENT_QUOTES, 'UTF-8') ?>
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm ms-2">
            <i class="fas fa-print me-1"></i>
            <?= htmlspecialchars($lang->printthread['print'] ?? 'Print', ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

</div>
<?php
stdfoot();

// ── Вспомогательные функции ───────────────────────────────────────────────────

function makeprintablenav(int $pid = 0, string $depth = '--'): string
{
    global $db, $fid, $forum, $lang, $BASEURL;
    static $pforumcache = null;

    if ($pforumcache === null) {
        $pforumcache = [];
        $parlist     = build_parent_list($fid, 'fid', 'OR', $forum['parentlist']);
        $query       = $db->sql_query_prepared("SELECT name, fid, pid FROM forums WHERE {$parlist} ORDER BY pid, disporder");
        while ($query && ($forumnav = $db->fetch_array($query))) {
            $pforumcache[$forumnav['pid']][$forumnav['fid']] = $forumnav;
        }
    }

    $forums = '';
    foreach ($pforumcache[$pid] ?? [] as $forumnav) {
        $link    = htmlspecialchars($BASEURL . '/' . get_forum_link($forumnav['fid']), ENT_QUOTES, 'UTF-8');
        $name    = htmlspecialchars($forumnav['name'], ENT_QUOTES, 'UTF-8');
        $forums .= '+' . $depth . ' ' . htmlspecialchars($lang->printthread['forum'] ?? 'Forum', ENT_QUOTES, 'UTF-8')
                 . ' ' . $name . ' (<em>' . $link . '</em>)<br>';

        if (!empty($pforumcache[$forumnav['fid']])) {
            $forums .= makeprintablenav((int)$forumnav['fid'], $depth . '-');
        }
    }

    return $forums;
}

function printthread_multipage(int $count, int $perpage, int $current_page, string $url): string
{
    global $lang;

    if ($count <= $perpage) return '';

    $pages  = (int)ceil($count / $perpage);
    $mppage = '';

    for ($p = 1; $p <= $pages; $p++) {
        $mppage .= $p === $current_page
            ? '<strong class="mx-1">' . $p . '</strong>'
            : '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '&amp;page=' . $p . '" class="mx-1">' . $p . '</a>';
    }

    return '<div class="d-flex align-items-center gap-1 flex-wrap">'
         . '<span class="text-muted me-1">' . htmlspecialchars($lang->printthread['pages'] ?? 'Pages', ENT_QUOTES, 'UTF-8') . ':</span>'
         . $mppage
         . '</div>';
}
