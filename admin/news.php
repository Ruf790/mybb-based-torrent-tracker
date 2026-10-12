<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit("<div class='alert alert-danger'><i class='fa-solid fa-triangle-exclamation'></i> <strong>Error!</strong> Direct initialization of this file is not allowed.</div>");
}

$lang->load('news');



$nws_e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Lang strings: $L raw (for JS), $E escaped for HTML (lang values are plain text)
$L = $lang->news;
$E = array_map(static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $L);

require_once INC_PATH . '/class_parser.php';
$parser = new postParser;

$parser_options = [
    "allow_html"      => 0,
    "allow_mycode"    => 1,
    "allow_smilies"   => 1,
    "allow_imgcode"   => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1,
];

stdhead($L['page_title']);

define('IN_EDITOR', true);

require_once $rootpath . 'cache/smilies.php';
require_once INC_PATH . '/editor.php';

$editor = insert_bbcode_editor($smilies, $BASEURL, 'newsMessage');

// ── KPI numbers (one query) ──────────────────────────────────────────────────
$stats = ['total' => 0, 'month' => 0, 'authors' => 0, 'latest' => null];
try {
    $monthStart = mktime(0, 0, 0, (int)date('n'), 1, (int)date('Y'));
    $sres = $db->sql_query_prepared(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(added >= ?), 0) AS month,
                COUNT(DISTINCT userid) AS authors,
                MAX(added) AS latest
         FROM news',
        [$monthStart]
    );
    if ($sres && ($srow = $db->fetch_array($sres))) {
        $stats['total']   = (int)($srow['total'] ?? 0);
        $stats['month']   = (int)($srow['month'] ?? 0);
        $stats['authors'] = (int)($srow['authors'] ?? 0);
        $stats['latest']  = $srow['latest'] ?? null;
    }
} catch (\Throwable $e) {
    error_log("News panel stats error: " . $e->getMessage());
}

$latestText = ($stats['latest'] !== null && $stats['latest'] !== '' && (int)$stats['latest'] > 0)
    ? my_datee('relative', $stats['latest'])
    : '—';

// ── Build news list ───────────────────────────────────────────────────────────
$newsItems  = '';
$newsCount  = 0;

try {
    $res = $db->sql_query_prepared(
        'SELECT n.*, u.username, u.usergroup, u.donor
         FROM news n
         LEFT JOIN users u ON (u.id = n.userid)
         ORDER BY n.added DESC'
    );
    $newsCount = $res ? (int)$db->num_rows($res) : 0;

    if ($newsCount > 0) {
        require_once INC_PATH . '/functions_mkprettytime.php';

        while ($res && ($arr = $db->fetch_array($res))) {
            $newsid  = (int)$arr['id'];
            $body2   = $arr['body'] ?? '';
            $body    = $parser->parse_message($body2, $parser_options);
            $title   = $nws_e((string)($arr['title'] ?? ''));
            $userid  = (int)$arr['userid'];
            $added   = my_datee('relative', $arr['added'] ?? TIMENOW);
            $poster  = format_name($arr['username'] ?? '', $arr['usergroup'] ?? '');
            $by      = $poster
                ? '<a href="' . $BASEURL . '/' . get_profile_link($userid) . '" class="text-decoration-none fw-semibold">' . $poster . '</a>'
                : $E['lbl_unknown_user'];
            $escaped = $nws_e((string)$body2);

            $byLine  = sprintf($E['lbl_by'], $by);
            $idLine  = sprintf($E['lbl_news_id'], $newsid);

            $newsItems .= '
            <article class="nws-card news-card fade-in-up"
                 data-newsid="' . $newsid . '"
                 data-body="' . $escaped . '"
                 id="news-' . $newsid . '">
                <header class="nws-card__head">
                    <div class="nws-icon-sq nws-icon-sm nws-soft-primary"><i class="fa-solid fa-newspaper"></i></div>
                    <div class="nws-card__heading">
                        <h4 class="nws-card__title card-title">' . $title . '</h4>
                        <div class="nws-meta">
                            <span><i class="fa-regular fa-calendar"></i>' . $added . '</span>
                            <span><i class="fa-solid fa-user-pen"></i>' . $byLine . '</span>
                        </div>
                    </div>
                    <div class="nws-card__actions">
                        <button type="button" class="btn btn-sm btn-outline-primary nws-pill news-edit"
                                data-newsid="' . $newsid . '" title="' . $E['tip_edit'] . '">
                            <i class="fa-solid fa-pen-to-square me-1"></i>' . $E['btn_edit'] . '
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger nws-pill news-delete"
                                data-newsid="' . $newsid . '" title="' . $E['tip_delete'] . '">
                            <i class="fa-solid fa-trash-can me-1"></i>' . $E['btn_delete'] . '
                        </button>
                    </div>
                </header>
                <div class="nws-card__body news-body">' . $body . '</div>
                <footer class="nws-card__foot">
                    <span class="nws-chip nws-soft-secondary"><i class="fa-solid fa-hashtag"></i>' . $idLine . '</span>
                </footer>
            </article>';
        }
    } else {
        $newsItems = '
        <div class="nws-card nws-empty empty-state fade-in-up" id="emptyState">
            <div class="nws-icon-sq nws-icon-lg nws-soft-secondary mx-auto mb-3"><i class="fa-solid fa-newspaper"></i></div>
            <h4 class="nws-empty__title">' . $E['sec_empty'] . '</h4>
            <p class="nws-muted mb-0">' . $E['hint_empty'] . '</p>
        </div>';
    }
} catch (\Throwable $e) {
    error_log("News panel error: " . $e->getMessage());
    $newsItems = '
    <div class="alert alert-danger fade-in-up">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <strong>' . $E['lbl_load_error'] . '</strong> '
        . $nws_e($e->getMessage()) .
    '</div>';
}

// ── BBCode toolbar of the edit modal: [open, close, label HTML, tip key, button class] ──
$bbGroups = [
    [
        ['[b]', '[/b]', '<i class="fa-solid fa-bold"></i>',          'tip_bb_bold',      'btn-outline-secondary'],
        ['[i]', '[/i]', '<i class="fa-solid fa-italic"></i>',        'tip_bb_italic',    'btn-outline-secondary'],
        ['[u]', '[/u]', '<i class="fa-solid fa-underline"></i>',     'tip_bb_underline', 'btn-outline-secondary'],
        ['[s]', '[/s]', '<i class="fa-solid fa-strikethrough"></i>', 'tip_bb_strike',    'btn-outline-secondary'],
    ],
    [
        ['[left]',   '[/left]',   '<i class="fa-solid fa-align-left"></i>',   'tip_bb_left',   'btn-outline-secondary'],
        ['[center]', '[/center]', '<i class="fa-solid fa-align-center"></i>', 'tip_bb_center', 'btn-outline-secondary'],
        ['[right]',  '[/right]',  '<i class="fa-solid fa-align-right"></i>',  'tip_bb_right',  'btn-outline-secondary'],
    ],
    [
        ['[color=red]',   '[/color]', '<i class="fa-solid fa-circle nws-dot-red"></i>',   'tip_bb_red',   'btn-outline-secondary'],
        ['[color=blue]',  '[/color]', '<i class="fa-solid fa-circle nws-dot-blue"></i>',  'tip_bb_blue',  'btn-outline-secondary'],
        ['[color=green]', '[/color]', '<i class="fa-solid fa-circle nws-dot-green"></i>', 'tip_bb_green', 'btn-outline-secondary'],
    ],
    [
        ['[url]',     '[/url]',     '<i class="fa-solid fa-link"></i>',        'tip_bb_url',     'btn-outline-secondary'],
        ['[img]',     '[/img]',     '<i class="fa-solid fa-image"></i>',       'tip_bb_img',     'btn-outline-secondary'],
        ['[video]',   '[/video]',   '<i class="fa-solid fa-film"></i>',        'tip_bb_video',   'btn-outline-secondary'],
        ['[youtube]', '[/youtube]', '<i class="fa-solid fa-circle-play"></i>', 'tip_bb_youtube', 'btn-outline-secondary'],
    ],
    [
        ['[quote]',   '[/quote]',   '<i class="fa-solid fa-quote-left"></i>', 'tip_bb_quote',   'btn-outline-secondary'],
        ['[code]',    '[/code]',    '<i class="fa-solid fa-code"></i>',       'tip_bb_code',    'btn-outline-secondary'],
        ['[spoiler]', '[/spoiler]', '<i class="fa-solid fa-eye-slash"></i>',  'tip_bb_spoiler', 'btn-outline-secondary'],
    ],
    [
        ["[list]\n[*]",   "\n[/list]", '<i class="fa-solid fa-list-ul"></i>', 'tip_bb_ul', 'btn-outline-secondary'],
        ["[list=1]\n[*]", "\n[/list]", '<i class="fa-solid fa-list-ol"></i>', 'tip_bb_ol', 'btn-outline-secondary'],
        ['[*]',           '',          '<i class="fa-solid fa-asterisk"></i>', 'tip_bb_li', 'btn-outline-secondary'],
    ],
];

$bbToolbar = '';
foreach ($bbGroups as $gi => $group) {
    if ($gi > 0) {
        $bbToolbar .= '<span class="nws-bb-sep" aria-hidden="true"></span>';
    }
    foreach ($group as [$open, $close, $label, $tipKey, $cls]) {
        $onclick = 'wrapBB(' . json_encode($open) . ',' . json_encode($close) . ')';
        $bbToolbar .= '<button type="button" class="btn btn-sm ' . $cls . ' nws-bb-btn" onclick="' . $nws_e($onclick) . '"'
                    . ' title="' . $E[$tipKey] . '" aria-label="' . $E[$tipKey] . '">' . $label . '</button>';
    }
}
$bbToolbar .= '<span class="nws-bb-sep" aria-hidden="true"></span>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary nws-bb-btn" id="torrentPanelToggleEdit" title="' . $E['tip_bb_torrent'] . '">'
            . '<i class="fa-solid fa-magnet me-1"></i>' . $E['lbl_bb_torrent'] . '</button>';

$charCount = sprintf($E['lbl_char_count'], '<span id="charCount">0</span>', '5000');

// JS strings: js_* keys without the prefix
$jsLang = [];
foreach ($L as $k => $v) {
    if (str_starts_with((string)$k, 'js_')) {
        $jsLang[substr((string)$k, 3)] = (string)$v;
    }
}
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/news.css?ver=1">

<div class="container py-4 nws-page">

    <!-- Header -->
    <div class="nws-card nws-head mb-3">
        <div class="nws-icon-sq nws-soft-primary"><i class="fa-solid fa-newspaper"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="nws-title"><?= $E['pane_title'] ?></h1>
            <p class="nws-sub"><i class="fa-solid fa-circle-info me-1"></i><?= $E['pane_subtitle'] ?></p>
        </div>
        <span class="nws-chip nws-soft-primary d-none d-md-inline-flex">
            <i class="fa-solid fa-bullhorn"></i><?= $E['lbl_head_chip'] ?>
        </span>
    </div>

    <!-- KPI tiles -->
    <div class="nws-kpis mb-3">
        <div class="nws-card nws-kpi">
            <div class="nws-icon-sq nws-icon-sm nws-soft-primary"><i class="fa-solid fa-layer-group"></i></div>
            <div class="min-w-0">
                <div class="nws-kpi__val"><?= number_format($stats['total']) ?></div>
                <div class="nws-kpi__label"><?= $E['lbl_kpi_total'] ?></div>
            </div>
        </div>
        <div class="nws-card nws-kpi">
            <div class="nws-icon-sq nws-icon-sm nws-soft-success"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="min-w-0">
                <div class="nws-kpi__val"><?= number_format($stats['month']) ?></div>
                <div class="nws-kpi__label"><?= $E['lbl_kpi_month'] ?></div>
            </div>
        </div>
        <div class="nws-card nws-kpi">
            <div class="nws-icon-sq nws-icon-sm nws-soft-info"><i class="fa-solid fa-users"></i></div>
            <div class="min-w-0">
                <div class="nws-kpi__val"><?= number_format($stats['authors']) ?></div>
                <div class="nws-kpi__label"><?= $E['lbl_kpi_authors'] ?></div>
            </div>
        </div>
        <div class="nws-card nws-kpi">
            <div class="nws-icon-sq nws-icon-sm nws-soft-warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="min-w-0">
                <div class="nws-kpi__val nws-kpi__val--text"><?= $latestText ?></div>
                <div class="nws-kpi__label"><?= $E['lbl_kpi_latest'] ?></div>
            </div>
        </div>
    </div>

    <!-- Create News Card -->
    <div class="nws-card nws-create mb-4 fade-in-up">
        <div class="nws-section-head">
            <div class="nws-icon-sq nws-icon-sm nws-soft-success"><i class="fa-solid fa-feather-pointed"></i></div>
            <div>
                <h2 class="nws-section-title"><?= $E['sec_create'] ?></h2>
                <small class="nws-muted"><?= $E['hint_create'] ?></small>
            </div>
        </div>
        <div class="nws-create__body">
            <?= $editor['toolbar'] ?>
            <form method="post" id="newsAddForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="newsSubject">
                        <i class="fa-solid fa-heading me-1 nws-accent"></i><?= $E['lbl_title'] ?>
                    </label>
                    <input type="text" class="form-control form-control-lg" name="subject" id="newsSubject"
                           placeholder="<?= $E['ph_title'] ?>" maxlength="255">
                </div>
                <div id="fileIdsContainer"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="newsMessage">
                        <i class="fa-solid fa-align-left me-1 nws-accent"></i><?= $E['lbl_content'] ?>
                    </label>
                    <textarea name="newsMessage" id="newsMessage" class="form-control"
                              rows="8" maxlength="5000"
                              placeholder="<?= $E['ph_content'] ?>"></textarea>
                    <div class="form-text text-end mt-1">
                        <i class="fa-solid fa-keyboard me-1"></i><?= $charCount ?>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary nws-pill px-4">
                        <i class="fa-solid fa-paper-plane me-2"></i><?= $E['btn_publish'] ?>
                    </button>
                    <button type="reset" class="btn btn-outline-secondary nws-pill px-4">
                        <i class="fa-solid fa-rotate-left me-2"></i><?= $E['btn_clear'] ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?= $editor['modal'] ?>

    <!-- News Archive -->
    <div class="nws-archive-head mb-3">
        <div class="nws-icon-sq nws-icon-sm nws-soft-primary"><i class="fa-solid fa-box-archive"></i></div>
        <h3 class="nws-section-title mb-0"><?= $E['sec_archive'] ?></h3>
        <span class="nws-chip nws-soft-secondary ms-auto" id="newsCount"><?= $newsCount ?></span>
    </div>

    <div id="newsList">
        <?= $newsItems ?>
    </div>

</div>

<!-- Edit Modal -->
<div class="modal fade nws-modal" id="newsEditModal" tabindex="-1" aria-hidden="true" aria-labelledby="newsEditTitle">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="nws-icon-sq nws-icon-xs nws-soft-primary"><i class="fa-solid fa-pen-to-square"></i></span>
                    <h5 class="modal-title" id="newsEditTitle"><?= $E['sec_edit'] ?></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $E['aria_close'] ?>"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editNewsId">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="editTitle">
                        <i class="fa-solid fa-heading me-1 nws-accent"></i><?= $E['lbl_edit_title'] ?>
                    </label>
                    <input type="text" class="form-control" id="editTitle"
                           placeholder="<?= $E['ph_edit_title'] ?>" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="editBody">
                        <i class="fa-solid fa-align-left me-1 nws-accent"></i><?= $E['lbl_edit_content'] ?>
                    </label>
                    <div class="nws-bb-toolbar mb-2">
                        <?= $bbToolbar ?>
                    </div>
                    <!-- Inline torrent embed panel - hidden until the "Torrent" button is pressed -->
                    <div id="torrentPanelEdit" class="nws-torrent-panel mb-2 d-none">
                        <label class="form-label small fw-semibold" for="torrentIdInputEdit">
                            <i class="fa-solid fa-magnet me-1 nws-accent"></i><?= $E['lbl_torrent_id'] ?>
                        </label>
                        <div class="input-group">
                            <input type="text" inputmode="numeric" class="form-control" id="torrentIdInputEdit" placeholder="<?= $E['ph_torrent_id'] ?>">
                            <button type="button" class="btn btn-primary" id="insertTorrentBtnEdit">
                                <i class="fa-solid fa-plus me-1"></i><?= $E['btn_insert'] ?>
                            </button>
                        </div>
                        <div id="torrentPreviewEdit" class="mt-2"></div>
                    </div>
                    <textarea id="editBody" class="form-control" rows="8"
                              placeholder="<?= $E['ph_content'] ?>"></textarea>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold">
                        <i class="fa-solid fa-eye me-1 nws-accent"></i><?= $E['lbl_preview'] ?>
                    </label>
                    <div id="bbcodeNewsPreview" class="nws-preview">
                        <small class="nws-muted"><?= $E['hint_preview'] ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary nws-pill px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i><?= $E['btn_cancel'] ?>
                </button>
                <button type="button" class="btn btn-primary nws-pill px-4" id="saveEditBtn" onclick="submitNewsEdit()">
                    <i class="fa-solid fa-floppy-disk me-1"></i><?= $E['btn_save'] ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.newsPostKey = <?= json_encode($mybb->post_code ?? '') ?>;
    window.newsBaseUrl = <?= json_encode($BASEURL ?? '') ?>;
    const AGS_LANG = <?= json_encode($jsLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/news.js?ver=2"></script>

<?php
stdfoot();
exit;
