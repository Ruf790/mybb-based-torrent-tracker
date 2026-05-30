<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'userhistory.php');
define('IN_ARCHIVE',  true);

require './global.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';

$parser         = new postParser;
$parser_options = [
    'allow_html'      => 1, 'allow_mycode'    => 1,
    'allow_smilies'   => 1, 'allow_imgcode'   => 1,
    'allow_videocode' => 1, 'filter_badwords' => 1,
];

$lang->load('userdetails');

// ── Параметры ─────────────────────────────────────────────────────────────────
$userid = (int)($_GET['id'] ?? 0);

if (!$userid || ($CURUSER['id'] !== $userid && ($usergroups['canuserdetails'] ?? 0) != 1)) {
    print_no_permission();
}

// ── Пользователь ──────────────────────────────────────────────────────────────
$q    = $db->sql_query_prepared('SELECT username, usergroup, avatar, avatardimensions FROM users WHERE id = ?', [$userid]);
$User = $db->num_rows($q) ? $db->fetch_array($q) : null;

if (!$User) {
    stderr($lang->userdetails['invaliduser'] ?? 'Invalid user.', '', 404, '404');
}

$Username   = format_name($User['username'], (int)$User['usergroup']);
$useravatar = format_avatar($User['avatar'] ?? '', $User['avatardimensions'] ?? '');

// ── Аватар ────────────────────────────────────────────────────────────────────
$avatarImg = (!empty($useravatar['image']) && !str_starts_with($useravatar['image'], '<'))
    ? '<img src="' . htmlspecialchars($useravatar['image'], ENT_QUOTES, 'UTF-8') . '" alt=""'
      . ' ' . ($useravatar['width_height'] ?? '')
      . ' class="rounded-circle border" style="width:72px;height:72px;object-fit:cover;">'
    : '<div class="rounded-circle border bg-body-secondary d-flex align-items-center justify-content-center"'
      . ' style="width:72px;height:72px;">'
      . '<i class="fas fa-user text-muted fs-4"></i></div>';

// ── Пагинация ─────────────────────────────────────────────────────────────────
$q            = $db->simple_select('comments c', 'COUNT(id) AS total', "user = '{$userid}'");
$threadcount  = (int)($db->fetch_field($q, 'total') ?? 0);

$ts_perpage   = max(1, (int)($ts_perpage ?? 20));
$page         = max(1, (int)($mybb->input['page'] ?? 1));
$totalPages   = $threadcount > 0 ? (int)ceil($threadcount / $ts_perpage) : 1;
if ($page > $totalPages) $page = 1;
$start        = ($page - 1) * $ts_perpage;

$multipage    = multipage($threadcount, $ts_perpage, $page,
    $_SERVER['SCRIPT_NAME'] . '?action=viewcomments&id=' . $userid);

// ── Комментарии ───────────────────────────────────────────────────────────────
$result = $db->sql_query_prepared(
    'SELECT c.id, c.torrent, c.dateline, c.text, t.name
     FROM comments c
     LEFT JOIN torrents t ON (c.torrent = t.id)
     WHERE c.user = ?
     ORDER BY c.dateline DESC
     LIMIT ?, ?',
    [$userid, $start, $ts_perpage]
);

// ── Вывод ─────────────────────────────────────────────────────────────────────
// FIX: stdhead() вызывается ДО любого echo, а не после накопления $Output
$pageTitle = sprintf(
    $lang->userdetails['chistory'] ?? 'Comment History for %s',
    htmlspecialchars($User['username'], ENT_QUOTES, 'UTF-8')
);
stdhead($pageTitle);

?>

<style>
/* FIX: style был после stdfoot() — перенесён в правильное место */
.comment-card {
    border-left: 3px solid transparent;
    transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
}
.comment-card:hover {
    border-left-color: var(--bs-primary);
    transform: translateX(4px) translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,.1) !important;
}
/* FIX: bg-f8f9fa → var(--bs-body-bg) для dark mode */
.comment-card:hover {
    background-color: var(--bs-tertiary-bg) !important;
}
.comment-body { font-size: .95rem; line-height: 1.6; }
</style>

<div class="container my-4">

    <!-- Шапка пользователя -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <?= $avatarImg ?>
        <div>
            <h4 class="mb-0"><?= $Username ?></h4>
            <small class="text-muted">
                <?= number_format($threadcount) ?>
                <?= $threadcount === 1 ? 'comment' : 'comments' ?>
            </small>
        </div>
    </div>

    <!-- Пагинация сверху -->
    <?php if ($threadcount > $ts_perpage): ?>
    <div class="mb-4"><?= $multipage ?></div>
    <?php endif; ?>

    <!-- Комментарии -->
    <?php if (!$db->num_rows($result)): ?>
    <div class="text-center py-5">
            <i class="fa-regular fa-comments fa-4x text-muted mb-4"></i>
            <h4 class="text-muted">No comments found</h4>
        </div>
    <?php else: ?>
    <?php while ($Comment = $db->fetch_array($result)): ?>
    <?php
        $pid         = (int)$Comment['id'];
        $tid         = (int)$Comment['torrent'];
        $postlink    = htmlspecialchars(get_comment_link($pid, $tid), ENT_QUOTES, 'UTF-8');
        $torrentName = htmlspecialchars_uni($Comment['name'] ?: '[Deleted Torrent]');
        $commentDate = my_datee(($dateformat ?? 'Y-m-d') . ' - ' . ($timeformat ?? 'H:i:s'), (int)$Comment['dateline']);
        $parsedText  = $parser->parse_message($Comment['text'] ?? '', $parser_options);
    ?>
    <div class="card mb-3 shadow-sm comment-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small">
                        <i class="far fa-clock me-1"></i><?= $commentDate ?>
                    </div>
                    <h6 class="mt-1 mb-0">
                        <a href="<?= $postlink ?>" class="text-decoration-none">
                            <i class="fas fa-magnet me-1 text-primary"></i><?= $torrentName ?>
                        </a>
                    </h6>
                </div>
                <a href="<?= $postlink ?>#pid<?= $pid ?>"
                   class="btn btn-sm btn-outline-secondary flex-shrink-0"
                   title="Permalink">
                    <i class="fas fa-link"></i>
                </a>
            </div>
            <hr class="my-2">
            <div class="comment-body"><?= $parsedText ?></div>
        </div>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>

    <!-- Пагинация снизу -->
    <?php if ($threadcount > $ts_perpage): ?>
    <div class="mt-3"><?= $multipage ?></div>
    <?php endif; ?>

</div>

<?php stdfoot(); ?>