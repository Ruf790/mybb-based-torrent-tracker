<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'userhistory.php');
define('IN_ARCHIVE',  true);

require './global.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_comment_attachments.php';
require_once INC_PATH . '/functions_reactions.php';

$parser         = new postParser;
$parser_options = [
    'allow_html'      => 0, 'allow_mycode'    => 1,
    'allow_smilies'   => 1, 'allow_imgcode'   => 1,
    'allow_videocode' => 1, 'filter_badwords' => 1,
];

$lang->load('userhistory');
$lang->load('details');   // ct_edited_note
$L = $lang->userhistory;

// ── Параметры ─────────────────────────────────────────────────────────────────
$userid = (int)($_GET['id'] ?? 0);

if (!$userid || ((int)$CURUSER['id'] !== $userid && ($usergroups['canstaffpanel'] ?? 0) != 1)) {
    print_no_permission();
}

// ── Пользователь ──────────────────────────────────────────────────────────────
$q    = $db->sql_query_prepared('SELECT username, usergroup, avatar, avatardimensions FROM users WHERE id = ?', [$userid]);
$User = ($q && $db->num_rows($q)) ? $db->fetch_array($q) : null;

if (!$User) {
    stderr($L['invaliduser'] ?? 'Invalid user.', '', 404, '404');
}

$Username   = format_name($User['username'], (int)$User['usergroup']);
$useravatar = format_avatar($User['avatar'], $User['avatardimensions']);

// ── KPI и пагинация одним запросом ────────────────────────────────────────────
$q = $db->sql_query_prepared(
    'SELECT COUNT(*) AS total,
            COUNT(DISTINCT torrent) AS torrents,
            COALESCE(SUM(dateline >= ?), 0) AS month_cnt,
            MAX(dateline) AS last_at
       FROM comments
      WHERE user = ?',
    [TIMENOW - 30 * 86400, $userid]
);
$st = $q ? ($db->fetch_array($q) ?: []) : [];

$kpiTotal    = (int)($st['total'] ?? 0);
$kpiTorrents = (int)($st['torrents'] ?? 0);
$kpiMonth    = (int)($st['month_cnt'] ?? 0);
$kpiLast     = (int)($st['last_at'] ?? 0);

// ── Фильтры ───────────────────────────────────────────────────────────────────
$searchQ = trim((string)($_GET['q'] ?? ''));
$searchQ = mb_substr($searchQ, 0, 100, 'UTF-8');
$onlyAtt = ($_GET['att'] ?? '') === '1';
$isFiltered = $searchQ !== '' || $onlyAtt;

$where  = 'c.user = ?';
$params = [$userid];
if ($searchQ !== '') {
    $like     = '%' . addcslashes($searchQ, '\\%_') . '%';
    $where   .= ' AND (c.text LIKE ? OR t.name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($onlyAtt) {
    $where .= ' AND EXISTS (SELECT 1 FROM attachments a WHERE a.comment_id = c.id AND a.visible = 1)';
}

$threadcount = $kpiTotal;
if ($isFiltered) {
    $countJoin = $searchQ !== '' ? 'LEFT JOIN torrents t ON (c.torrent = t.id)' : '';
    $q = $db->sql_query_prepared(
        "SELECT COUNT(*) AS total FROM comments c {$countJoin} WHERE {$where}",
        $params
    );
    $threadcount = $q ? (int)($db->fetch_field($q, 'total') ?? 0) : 0;
}

// ── Пагинация ─────────────────────────────────────────────────────────────────
$ts_perpage = max(1, (int)($ts_perpage ?? 20));
$page       = max(1, (int)($mybb->input['page'] ?? 1));
$totalPages = $threadcount > 0 ? (int)ceil($threadcount / $ts_perpage) : 1;
if ($page > $totalPages) {
    $page = 1;
}
$start = ($page - 1) * $ts_perpage;

$pageUrl = $_SERVER['SCRIPT_NAME'] . '?action=viewcomments&id=' . $userid
    . ($searchQ !== '' ? '&q=' . urlencode($searchQ) : '')
    . ($onlyAtt ? '&att=1' : '');
$multipage = multipage($threadcount, $ts_perpage, $page, $pageUrl);

// ── Комментарии ───────────────────────────────────────────────────────────────
$result = $db->sql_query_prepared(
    "SELECT c.id, c.torrent, c.user, c.dateline, c.text, c.editedby, c.editedat,
            t.name, t.t_image, uu.username AS editedbyuname
     FROM comments c
     LEFT JOIN torrents t ON (c.torrent = t.id)
     LEFT JOIN users uu ON (uu.id = c.editedby)
     WHERE {$where}
     ORDER BY c.dateline DESC
     LIMIT ?, ?",
    array_merge($params, [$start, $ts_perpage])
);

$rows = [];
while ($result && ($row = $db->fetch_array($result))) {
    $rows[] = $row;
}

// Реакции: все комментарии страницы одним запросом
$myId     = (int)($CURUSER['id'] ?? 0);
$reactSum = [];
try {
    $reactSum = react_summary('comment', array_column($rows, 'id'), $myId);
} catch (\Throwable $ex) {
    error_log('userhistory reactions: ' . $ex->getMessage());
}
// Вложения: все комментарии страницы одним запросом
$attsByComment = [];
if ($rows !== []) {
    $ids = array_map('intval', array_column($rows, 'id'));
    $q   = $db->sql_query_prepared(
        'SELECT * FROM attachments
          WHERE comment_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') AND visible = 1
          ORDER BY dateuploaded ASC',
        $ids
    );
    while ($q && ($att = $db->fetch_array($q))) {
        $attsByComment[(int)$att['comment_id']][] = $att;
    }
}

$canReact = $myId > 0 && !(defined('UC_BANNED') && (int)($CURUSER['usergroup'] ?? 0) === UC_BANNED);
$canEdit  = $myId === $userid || is_mod($usergroups);

// ── Вывод ─────────────────────────────────────────────────────────────────────
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$pageTitle = sprintf((string)($L['chistory'] ?? 'Comment History for %s'), (string)$User['username']);

stdhead($pageTitle);

echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=62">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/userhistory.css?ver=8">';
echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/comment_attachments.css">';
echo react_assets();

$tiles = [
    ['fa-comments',     'st-soft-primary', ts_nf($kpiTotal), $L['lbl_uh_total'] ?? 'Comments'],
    ['fa-magnet',       'st-soft-danger',  ts_nf($kpiTorrents), $L['lbl_uh_torrents'] ?? 'Torrents commented'],
    ['fa-calendar-day', 'st-soft-success', ts_nf($kpiMonth),    $L['lbl_uh_month'] ?? 'Last 30 days'],
    ['fa-clock',        'st-soft-warning', $kpiLast > 0 ? date('d.m.Y', $kpiLast) : '—', $L['lbl_uh_last'] ?? 'Last comment'],
];
?>
<div class="container-md st-page uh-page">

    <header class="st-card st-head mb-3">
        <img class="uh-ava" src="<?= $e((string)$useravatar['image']) ?>" alt="" loading="lazy">
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $Username ?></h1>
            <div class="st-meta mt-1"><?= $e($pageTitle) ?></div>
        </div>
    </header>

    <div class="uh-kpis mb-3">
        <?php foreach ($tiles as [$icon, $soft, $val, $lbl]): ?>
            <div class="st-kpi st-kpi--muted">
                <span class="st-ico <?= $soft ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                <div class="min-w-0">
                    <div class="st-kpi__val"><?= $val ?></div>
                    <div class="st-kpi__lbl"><?= $e((string)$lbl) ?></div>
                </div>
                <i class="fa-solid <?= $icon ?> st-kpi__bg" aria-hidden="true"></i>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="get" action="userhistory.php" class="st-card uh-search mb-3">
        <input type="hidden" name="action" value="viewcomments">
        <input type="hidden" name="id" value="<?= $userid ?>">
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="search" class="form-control" name="q" maxlength="100" value="<?= $e($searchQ) ?>" placeholder="<?= $e((string)($L['ph_uh_search'] ?? 'Search by comment text or torrent name')) ?>">
        </div>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="uhOnlyAtt" name="att" value="1"<?= $onlyAtt ? ' checked' : '' ?>>
            <label class="form-check-label" for="uhOnlyAtt"><?= $e((string)($L['lbl_uh_only_att'] ?? 'With attachments only')) ?></label>
        </div>
        <button type="submit" class="btn btn-primary st-pill"><?= $e((string)($lang->header['hdr_search'] ?? 'Search')) ?></button>
        <?php if ($isFiltered): ?>
            <a class="btn btn-outline-secondary st-pill" href="userhistory.php?action=viewcomments&amp;id=<?= $userid ?>"><?= $e((string)($L['btn_uh_reset'] ?? 'Reset')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($threadcount > $ts_perpage): ?>
        <div class="st-pager mb-3"><?= $multipage ?></div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="st-card st-empty">
            <span class="st-ico st-soft-muted"><i class="fa-regular fa-comments"></i></span>
            <div><?= $e((string)($L['txt_uh_empty'] ?? 'No comments found')) ?></div>
        </div>
    <?php else: ?>
        <?php foreach ($rows as $Comment): ?>
            <?php
            $pid         = (int)$Comment['id'];
            $tid         = (int)$Comment['torrent'];
            $postlink    = $e(get_comment_link($pid, $tid));
            $torrentName = htmlspecialchars_uni($Comment['name'] ?: ($L['txt_uh_deleted'] ?? '[Deleted Torrent]'));
            $commentDate = my_datee(($dateformat ?? 'Y-m-d') . ' - ' . ($timeformat ?? 'H:i:s'), (int)$Comment['dateline']);
            $parsedText  = $parser->parse_message($Comment['text'] ?? '', $parser_options);
            $attachments = render_comment_attachments_from_array($attsByComment[$pid] ?? []);
            $reactions   = react_bar('comment', $pid, $reactSum[$pid] ?? [], $canReact);

            $editedNote = '';
            if ((int)$Comment['editedby'] !== 0 && (int)$Comment['editedat'] !== 0 && (string)($Comment['editedbyuname'] ?? '') !== '') {
                $editedNote = sprintf(
                    (string)($lang->details['ct_edited_note'] ?? 'This post was last modified: %1$s by %2$s'),
                    my_datee('relative', (int)$Comment['editedat']),
                    build_profile_link(htmlspecialchars_uni((string)$Comment['editedbyuname']), (int)$Comment['editedby'])
                );
            }
            ?>
            <article class="st-card uh-item mb-2">
                <div class="uh-item__head">
                    <?php if (!empty($Comment['t_image'])): ?>
                        <img class="uh-item__poster" src="<?= $e((string)$Comment['t_image']) ?>" alt="" loading="lazy"
                             onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'st-ico st-soft-primary',innerHTML:'<i class=&quot;fa-solid fa-magnet&quot;></i>'}))">
                    <?php else: ?>
                        <span class="st-ico st-soft-primary"><i class="fa-solid fa-magnet"></i></span>
                    <?php endif; ?>
                    <div class="flex-grow-1 min-w-0">
                        <a class="uh-item__torrent" href="<?= $postlink ?>"><?= $torrentName ?></a>
                        <div class="st-meta"><i class="fa-regular fa-clock me-1"></i><?= $commentDate ?></div>
                    </div>
                    <?php if ($canEdit && !empty($Comment['name'])): ?>
                        <a class="st-icon-btn edit-comment-btn" href="comment.php?action=edit&amp;pid=<?= $pid ?>" data-commentid="<?= $pid ?>" data-torrentid="<?= $tid ?>" data-commenttext="<?= $e((string)$Comment['text']) ?>" title="<?= $e((string)($lang->global['postbit_button_edit'] ?? 'Edit')) ?>"><i class="fa-solid fa-pencil"></i></a>
                    <?php endif; ?>
                    <a class="st-icon-btn" href="<?= $postlink ?>#pid<?= $pid ?>" title="<?= $e((string)($L['tip_uh_permalink'] ?? 'Permalink')) ?>"><i class="fa-solid fa-link"></i></a>
                </div>
                <div class="uh-item__body">
                    <?= $parsedText ?>
                    <?php if ($attachments): ?>
                        <div class="mt-2"><?= $attachments ?></div>
                    <?php endif; ?>
                    <?php if ($editedNote !== ''): ?>
                        <div class="st-meta mt-2"><i class="fa-regular fa-pen-to-square me-1"></i><?= $editedNote ?></div>
                    <?php endif; ?>
                    <?= $reactions ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($threadcount > $ts_perpage): ?>
        <div class="st-pager mt-3"><?= $multipage ?></div>
    <?php endif; ?>

</div>
<?php
// Модалка быстрого редактирования: разметка из modals.php, логика из edit_delete_comment.js
$uhJsLang = [];
foreach ($lang->details as $uhKey => $uhVal) {
    if (str_starts_with((string)$uhKey, 'js_')) {
        $uhJsLang[substr((string)$uhKey, 3)] = $uhVal;
    }
}
echo '<script>const AGS_LANG = ' . json_encode($uhJsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
echo '<script src="' . $BASEURL . '/scripts/toast.js?ver=2"></script>';
echo '<script src="' . $BASEURL . '/scripts/edit_delete_comment.js?ver=2"></script>';

require_once INC_PATH . '/modals.php';
?>
<script>
// edit2 возвращает карточку для страницы торрента, поэтому здесь после сохранения просто обновляем страницу
(function () {
    var modal = document.getElementById('editCommentModal');
    var save  = document.getElementById('confirmEditComment');
    if (!modal || !save) return;
    var saving = false;
    save.addEventListener('click', function () { saving = true; });
    modal.addEventListener('click', function (e) {
        if (e.target.closest('[data-bs-dismiss="modal"]')) saving = false;
    });
    modal.addEventListener('hidden.bs.modal', function () {
        if (saving) location.reload();
    });
})();
</script>
<?php
stdfoot();
