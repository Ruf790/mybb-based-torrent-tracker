<?php



declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<div class="alert alert-danger m-3" role="alert"><strong>Error!</strong> Direct initialization of this file is not allowed.</div>');
}

$lang->load('announcements');

require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/class_parser.php';
require_once __DIR__ . '/../cache/smilies.php';

// ──────────────────────────────────────────────────────────────────────────────
// Constants & shared state
// ──────────────────────────────────────────────────────────────────────────────

define('B_VERSION', 'v.0.6');
define('ANNOUNCEMENTS_PER_PAGE', 15);
define('ANNOUNCEMENTS_TIMER_SECONDS', 60);
define('ANNOUNCEMENTS_MAX_CHARS', 5000);

if (!function_exists('ags_fmt')) {
    /**
     * Substitutes {1}, {2}… in a lang string. $lang->load() rewrites {N}
     * into %N$s, so both forms are handled. strtr() is single-pass, so
     * placeholders inside the arguments themselves are never re-expanded.
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach (array_values($args) as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string) $arg;
            $map['%' . $n . '$s'] = (string) $arg;
        }
        return strtr($str, $map);
    }
}

/**
 * Collects js_* keys of the announcements lang (prefix stripped) as JSON
 * for `const AGS_LANG = …;`.
 */
function announcementsJsLang(): string
{
    global $lang;

    $out = [];
    foreach ((array) ($lang->announcements ?? []) as $key => $value) {
        $key = (string) $key;
        if (str_starts_with($key, 'js_')) {
            $out[substr($key, 3)] = (string) $value;
        }
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

$parser = new postParser();

$parser_options = [
    'allow_html'     => 0,
    'allow_mycode'   => 1,
    'allow_smilies'  => 1,
    'allow_imgcode'  => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
];

// ──────────────────────────────────────────────────────────────────────────────
// Router
// ──────────────────────────────────────────────────────────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? 'show';
$do     = $_POST['do']     ?? $_GET['do']     ?? '';
$id     = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

match ($action) {
    'show'      => handleShowAction(),
    'see'       => handleSeeAction($id),
    'add'       => handleAddAction($do),
    'edit'      => handleEditAction($id, $do),
    'delete'    => handleDeleteAction($id),
    'duplicate' => handleDuplicateAction($id),
    default     => redirect('admin/index.php?act=announcements', $lang->announcements['flash_invalid_action']),
};

// ──────────────────────────────────────────────────────────────────────────────
// Text / content helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Extracts up to $limit unique meaningful keywords from plain text,
 * filtering common English stop-words.
 *
 * @return string[]
 */
function extractKeywords(string $text, int $limit = 10): array
{
    static $stopWords = [
        'the', 'and', 'for', 'you', 'your', 'with', 'this', 'that', 'have', 'from',
    ];

    $words    = str_word_count(strip_tags($text), 1);
    $filtered = array_unique(array_diff($words, $stopWords));

    return array_slice($filtered, 0, $limit);
}

/**
 * Counts occurrences of common BBCode opening tags in $text.
 *
 * @return array<string, int>  Only tags whose count > 0 are returned.
 */
function countBBCodeTags(string $text): array
{
    $tags   = ['b', 'i', 'u', 'url', 'img', 'quote', 'code', 'color', 'size', 'font'];
    $counts = [];

    foreach ($tags as $tag) {
        $n = preg_match_all('/\[' . preg_quote($tag, '/') . '[^\]]*\]/i', $text);
        if ($n > 0) {
            $counts[$tag] = $n;
        }
    }

    return $counts;
}

/**
 * Classifies the content of a BBCode message into a human-readable label.
 */
function determineContentType(string $text): string
{
    global $lang;

    if (preg_match('/\[img\]/i', $text))  return $lang->announcements['opt_ct_images'];
    if (preg_match('/\[url\]/i', $text))  return $lang->announcements['opt_ct_links'];
    if (strlen($text) > 500)              return $lang->announcements['opt_ct_long'];
    if (strlen($text) < 100)             return $lang->announcements['opt_ct_short'];
    return $lang->announcements['opt_ct_standard'];
}

/**
 * Returns a human-readable estimated reading time (e.g. "3 mins").
 */
function calculateReadingTime(string $text): string
{
    global $lang;

    $words   = str_word_count(strip_tags($text));
    $minutes = (int) ceil($words / 200);
    return ags_fmt($minutes !== 1 ? $lang->announcements['lbl_reading_many'] : $lang->announcements['lbl_reading_one'], $minutes);
}

// ──────────────────────────────────────────────────────────────────────────────
// HTML / view helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Renders the shared Bootstrap delete-confirmation modal.
 * Included once per page; controlled via JS openDeleteModal().
 */
function renderDeleteModal(string $scriptName): void
{
    global $mybb, $lang;
    ?>
    <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= $lang->announcements['del_title'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><?= $lang->announcements['del_about'] ?></p>
                    <div class="alert alert-warning fw-bold mb-3" id="deleteAnnouncementTitle"></div>
                    <p class="text-danger mb-0"><strong><?= $lang->announcements['del_warning'] ?></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang->announcements['btn_cancel'] ?></button>
                    <form id="deleteAnnouncementForm" method="post" action="<?= htmlspecialchars($scriptName) ?>">
                        <input type="hidden" name="act" value="announcements">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteAnnouncementId" value="">
                        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> <?= $lang->announcements['btn_delete'] ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        let _modal;

        function getModal() {
            if (!_modal) {
                _modal = new bootstrap.Modal(document.getElementById('deleteAnnouncementModal'));
            }
            return _modal;
        }

        window.openDeleteModal = function (id, subject) {
            document.getElementById('deleteAnnouncementTitle').textContent = subject;
            document.getElementById('deleteAnnouncementId').value = id;
            getModal().show();
        };
    })();
    </script>
    <?php
}

// ──────────────────────────────────────────────────────────────────────────────
// Action handlers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Lists all announcements with pagination.
 */
function handleShowAction(): void
{
    global $db, $_this_script_, $lang;

    stdhead(ags_fmt($lang->announcements['title_list'], B_VERSION));

	
	$res   = $db->sql_query_prepared("SELECT COUNT(id) AS cnt FROM announcements");
    $total = $res ? (int) ($db->fetch_array($res)['cnt'] ?? 0) : 0;
	
    $page    = max(0, (int) ($_GET['page'] ?? 0));
    $offset  = $page * ANNOUNCEMENTS_PER_PAGE;
    $perPage = ANNOUNCEMENTS_PER_PAGE;

    $res = $db->sql_query_prepared(
        "SELECT * FROM announcements WHERE type = 'tracker' ORDER BY added DESC LIMIT ?, ?",
        [$offset, $perPage]
    );

    $newUrl = $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=add';
    ?>

    <div class="container mt-4">
     
		<div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-bullhorn me-2 text-primary"></i><?= $lang->announcements['sec_tracker_announcements'] ?></h3>
            <a href="<?= $_this_script_ ?>&action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> <?= $lang->announcements['btn_new'] ?>
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= $lang->announcements['sec_list'] ?></h5>
                <span class="badge bg-light text-dark"><?= ags_fmt($lang->announcements['lbl_total'], number_format($total)) ?></span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="60"><?= $lang->announcements['th_id'] ?></th>
                            <th><?= $lang->announcements['th_subject'] ?></th>
                            <th width="300"><?= $lang->announcements['th_preview'] ?></th>
                            <th width="180"><?= $lang->announcements['th_added'] ?></th>
                            <th width="120"><?= $lang->announcements['th_minclass'] ?></th>
                            <th width="140" class="text-center"><?= $lang->announcements['th_actions'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res && $db->num_rows($res) > 0): ?>
                            <?php while ($row = $db->fetch_array($res)): ?>
                                <?php renderAnnouncementRow($row); ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    <?= $lang->announcements['lbl_no_announcements'] ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total > $perPage): ?>
                <div class="card-footer">
                    <?= renderPagination($page, $total, $perPage) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?= renderDeleteModal($_SERVER['SCRIPT_NAME']) ?>

    <?php
    stdfoot();
}

/**
 * Renders a single <tr> for the announcements list.
 */
function renderAnnouncementRow(array $row): void
{
    global $lang;

    $id      = (int) $row['id'];
    $subject = htmlspecialchars($row['subject']);
    $preview = htmlspecialchars(mb_substr(strip_tags($row['message']), 0, 100)) . '…';
    $timeAgo = mkprettytime(TIMENOW - (int) $row['added']);
    $base    = $_SERVER['SCRIPT_NAME'] . '?act=announcements';
    ?>
    <tr>
        <td class="text-center fw-bold"><?= $id ?></td>
        <td>
            <strong><?= $subject ?></strong>
            
        </td>
        <td>
            <div class="text-truncate" style="max-width:300px" title="<?= $preview ?>">
                <?= $preview ?>
            </div>
        </td>
        <td>
            <div class="small">
                <?= my_datee('relative', (int) $row['added']) ?>
                <br>
                <span class="badge bg-secondary"><?= ags_fmt($lang->announcements['lbl_ago'], $timeAgo) ?></span>
            </div>
        </td>
        <td class="text-center">
            <span class="badge bg-info"><?= get_user_class_name((string) $row['minclassread']) ?></span>
        </td>
        <td class="text-center">
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= $base ?>&action=see&id=<?= $id ?>"
                   class="btn btn-outline-info" title="<?= htmlspecialchars($lang->announcements['tip_preview']) ?>">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="<?= $base ?>&action=edit&id=<?= $id ?>"
                   class="btn btn-outline-primary" title="<?= htmlspecialchars($lang->announcements['tip_edit']) ?>">
                    <i class="fas fa-edit"></i>
                </a>
                <button type="button" class="btn btn-outline-danger" title="<?= htmlspecialchars($lang->announcements['tip_delete']) ?>"
                        onclick="openDeleteModal(<?= $id ?>, '<?= htmlspecialchars(addslashes($row['subject'])) ?>')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php
}

/**
 * Returns Bootstrap pagination HTML.
 */
function renderPagination(int $currentPage, int $total, int $perPage): string
{
    $pages = (int) ceil($total / $perPage);
    $html  = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';

    for ($i = 0; $i < $pages; $i++) {
        $active = $currentPage === $i ? ' active' : '';
        $html  .= '<li class="page-item' . $active . '">'
               .  '<a class="page-link" href="?act=announcements&action=show&page=' . $i . '">'
               .  ($i + 1)
               .  '</a></li>';
    }

    return $html . '</ul></nav>';
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Shows a detailed view of one announcement inside a Bootstrap modal.
 */
function handleSeeAction(int $id): void
{
    global $db, $parser, $parser_options, $lang;

    if ($id <= 0) {
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_invalid_id']);
    }

    $current = fetchAnnouncement($id);
    if (!$current) {
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_not_found']);
    }

    // Navigation neighbours
    $prevQ = $db->sql_query_prepared("SELECT id, subject FROM announcements WHERE type = 'tracker' AND id < ? ORDER BY id DESC LIMIT 1", [$id]);
    $prev = $prevQ ? ($db->fetch_array($prevQ) ?: null) : null;

    $nextQ = $db->sql_query_prepared("SELECT id, subject FROM announcements WHERE type = 'tracker' AND id > ? ORDER BY id ASC LIMIT 1", [$id]);
    $next = $nextQ ? ($db->fetch_array($nextQ) ?: null) : null;

    // Position in list
    $totalQ = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM announcements WHERE type = 'tracker'");
    $totalCount      = $totalQ ? (int)$db->fetch_array($totalQ)['c'] : 0;
    $posQ = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM announcements WHERE type = 'tracker' AND id <= ?", [$id]);
    $currentPosition = $posQ ? (int)$db->fetch_array($posQ)['c'] : 0;

    // Related announcements (keyword search, sanitised)
    $related = fetchRelatedAnnouncements($id, $current, $db);

    $viewCount     = (int) ($current['views'] ?? 0);
    $parsedMessage = $parser->parse_message($current['message'], $parser_options);
    $scriptName    = $_SERVER['SCRIPT_NAME'];
    $base          = $scriptName . '?act=announcements';

    stdhead($lang->announcements['title_view']);
    ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= $lang->announcements['sec_preview_page'] ?></h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-info" onclick="seeToggleSidebar()" title="<?= htmlspecialchars($lang->announcements['tip_toggle_sidebar']) ?>">
                        <i class="fas fa-columns"></i>
                    </button>
                    <a href="<?= $base ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> <?= $lang->announcements['btn_back'] ?>
                    </a>
                </div>
            </div>

            <div class="card-body">
                <?php renderSeeModal($current, $prev, $next, $totalCount, $currentPosition, $viewCount, $parsedMessage, $scriptName); ?>

                <!-- Below-modal preview + quick stats -->
                <div class="row mt-4">
                    <?php renderSeePreviewCard($current, $viewCount, $parsedMessage, $scriptName); ?>
                    <?php renderSeeQuickStatsCard($current, $viewCount, $currentPosition, $totalCount); ?>
                </div>
            </div>
        </div>
    </div>

    <?= renderDeleteModal($scriptName) ?>

    <?php
    renderSeeScripts($current, $prev, $next, $viewCount, $scriptName);
    stdfoot();
}

/**
 * Fetches related announcements by keyword matching (safe, parameterised-style).
 */
function fetchRelatedAnnouncements(int $excludeId, array $current, object $db): array
{
    $keywords = array_filter(
        extractKeywords($current['subject'] . ' ' . strip_tags($current['message'])),
        static fn (string $w): bool => strlen($w) > 3
    );

    if (empty($keywords)) {
        return [];
    }

    $conditions = [];
    $params     = [];
    foreach ($keywords as $kw) {
        $conditions[] = "subject LIKE ? OR message LIKE ?";
        $like = '%' . $kw . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = 'SELECT id, subject, added FROM announcements'
         . ' WHERE type = \'tracker\' AND id != ?'
         . ' AND (' . implode(' OR ', $conditions) . ')'
         . ' ORDER BY added DESC LIMIT 5';

    $res     = $db->sql_query_prepared($sql, [$excludeId, ...$params]);
    $related = [];
    while ($res && ($row = $db->fetch_array($res))) {
        $related[] = $row;
    }

    return $related;
}

/**
 * Renders the Bootstrap modal for the "see" action.
 */
function renderSeeModal(
    array  $current,
    ?array $prev,
    ?array $next,
    int    $totalCount,
    int    $currentPosition,
    int    $viewCount,
    string $parsedMessage,
    string $scriptName
): void {
    global $lang;

    $base = $scriptName . '?act=announcements';
    $id   = (int) $current['id'];
    ?>
    <div class="modal fade" id="announcementModal" tabindex="-1"
         aria-labelledby="announcementModalLabel" aria-hidden="false"
         data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow-lg">

                <!-- Header -->
                <div class="modal-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group">
                            <?php if ($prev): ?>
                                <a href="<?= $base ?>&action=see&id=<?= (int)$prev['id'] ?>"
                                   class="btn btn-light btn-sm" title="<?= htmlspecialchars($lang->announcements['tip_prev']) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-light btn-sm" disabled><i class="fas fa-chevron-left"></i></button>
                            <?php endif; ?>

                            <button class="btn btn-light btn-sm" onclick="seeToggleFullscreen()" title="<?= htmlspecialchars($lang->announcements['tip_fullscreen']) ?>">
                                <i class="fas fa-expand"></i>
                            </button>

                            <?php if ($next): ?>
                                <a href="<?= $base ?>&action=see&id=<?= (int)$next['id'] ?>"
                                   class="btn btn-light btn-sm" title="<?= htmlspecialchars($lang->announcements['tip_next']) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-light btn-sm" disabled><i class="fas fa-chevron-right"></i></button>
                            <?php endif; ?>
                        </div>

                        <div class="vr mx-2" style="width:2px;background:rgba(255,255,255,.5)"></div>

                        <h5 class="modal-title mb-0" id="announcementModalLabel">
                            <i class="fas fa-bullhorn me-2"></i>
                            <?= htmlspecialchars($current['subject']) ?>
                        </h5>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="progress" style="width:100px;height:6px"
                             title="<?= htmlspecialchars(ags_fmt($lang->announcements['tip_position'], $currentPosition, $totalCount)) ?>">
                            <div class="progress-bar bg-warning"
                                 style="width:<?= ($totalCount > 0 ? round(($currentPosition / $totalCount) * 100) : 0) ?>%">
                            </div>
                        </div>
                        <div class="text-end small">
                            <div>#<?= $currentPosition ?>/<?= $totalCount ?></div>
                            <div><?= $lang->announcements['lbl_id'] ?>: <?= $id ?></div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                onclick="window.location.href='<?= $base ?>'">
                        </button>
                    </div>
                </div>

                <!-- Body: main content + sidebar -->
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Main (75%) -->
                        <div class="col-lg-9 p-4" id="seeMainCol">
                            <?php renderSeeTabContent($current, $parsedMessage, $viewCount, $totalCount, $currentPosition); ?>
                        </div>

                        <!-- Sidebar (25%) -->
                        <div class="col-lg-3 border-start p-4 bg-light" id="seeSidebar">
                            <?php renderSeeSidebar($current, $prev, $next, $totalCount, $currentPosition, $scriptName); ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php
}

/**
 * Tab navigation + tab panes inside the modal.
 */
function renderSeeTabContent(
    array  $current,
    string $parsedMessage,
    int    $viewCount,
    int    $totalCount,
    int    $currentPosition
): void {
    global $lang;

    $tags        = countBBCodeTags($current['message']);
    $contentType = determineContentType($current['message']);
    $wordCount   = number_format(str_word_count(strip_tags($current['message'])));
    $charCount   = number_format(strlen($current['message']));
    $readingTime = calculateReadingTime($current['message']);
    ?>
    <ul class="nav nav-tabs mb-4" id="announcementTabs" role="tablist">
        <?php
        $tabs = [
            ['content',  'fa-file-alt',    $lang->announcements['tab_content']],
            ['details',  'fa-info-circle', $lang->announcements['tab_details']],
            ['stats',    'fa-chart-bar',   $lang->announcements['tab_stats']],
        ];
        foreach ($tabs as $i => [$pane, $icon, $label]): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#<?= $pane ?>"
                        type="button" role="tab">
                    <i class="fas <?= $icon ?> me-1"></i> <?= $label ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <!-- Content tab -->
        <div class="tab-pane fade show active" id="content" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="seeFontSize(10)"  title="<?= htmlspecialchars($lang->announcements['tip_font_inc']) ?>">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="seeFontSize(-10)" title="<?= htmlspecialchars($lang->announcements['tip_font_dec']) ?>">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="seeToggleDark()" title="<?= htmlspecialchars($lang->announcements['tip_dark']) ?>">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="seeCopy()" title="<?= htmlspecialchars($lang->announcements['tip_copy']) ?>">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="text-muted small">
                    <?= $lang->announcements['lbl_font'] ?> <span id="seeFontIndicator">100%</span>
                </div>
            </div>

            <div class="announcement-content" id="seeContentArea">
                <?= $parsedMessage ?>
            </div>

            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark"><i class="fas fa-font me-1"></i><?= ags_fmt($lang->announcements['lbl_words'], $wordCount) ?></span>
                    <span class="badge bg-light text-dark"><i class="fas fa-ruler me-1"></i><?= ags_fmt($lang->announcements['lbl_chars'], $charCount) ?></span>
                    <span class="badge bg-light text-dark"><i class="fas fa-clock me-1"></i><?= $readingTime ?></span>
                </div>
                <button class="btn btn-sm btn-outline-success" onclick="seeShare()">
                    <i class="fas fa-share-alt me-1"></i> <?= $lang->announcements['btn_share'] ?>
                </button>
            </div>
        </div>

        <!-- Details tab -->
        <div class="tab-pane fade" id="details" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light"><h6 class="mb-0"><?= $lang->announcements['sec_details'] ?></h6></div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4"><?= $lang->announcements['dt_id'] ?></dt>
                                <dd class="col-sm-8">#<?= (int) $current['id'] ?></dd>

                                <dt class="col-sm-4"><?= $lang->announcements['dt_created'] ?></dt>
                                <dd class="col-sm-8">
                                    <?= my_datee('relative', (int) $current['added']) ?><br>
                                    <small class="text-muted">(<?= ags_fmt($lang->announcements['lbl_ago'], mkprettytime(TIMENOW - (int) $current['added'])) ?>)</small>
                                </dd>

                                <dt class="col-sm-4"><?= $lang->announcements['dt_author'] ?></dt>
                               

                                <dt class="col-sm-4"><?= $lang->announcements['dt_target'] ?></dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-info">
                                        <?= get_user_class_name((string) $current['minclassread']) ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-4"><?= $lang->announcements['dt_subject'] ?></dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($current['subject']) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-light"><h6 class="mb-0"><?= $lang->announcements['sec_tech'] ?></h6></div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5"><?= $lang->announcements['dt_bbcode'] ?></dt>
                                <dd class="col-sm-7"><?= implode(', ', array_keys($tags)) ?: '—' ?></dd>

                                <dt class="col-sm-5"><?= $lang->announcements['dt_has_images'] ?></dt>
                                <dd class="col-sm-7"><?= preg_match('/\[img\]/i', $current['message']) ? $lang->announcements['lbl_yes'] : $lang->announcements['lbl_no'] ?></dd>

                                <dt class="col-sm-5"><?= $lang->announcements['dt_has_links'] ?></dt>
                                <dd class="col-sm-7"><?= preg_match('/\[url\]/i', $current['message']) ? $lang->announcements['lbl_yes'] : $lang->announcements['lbl_no'] ?></dd>

                                <dt class="col-sm-5"><?= $lang->announcements['dt_content_type'] ?></dt>
                                <dd class="col-sm-7"><?= $contentType ?></dd>

                                <dt class="col-sm-5"><?= $lang->announcements['dt_modified'] ?></dt>
                                <dd class="col-sm-7">
                                    <?php if (!empty($current['updated']) && (int) $current['updated'] !== 0): ?>
                                        <?= my_datee('relative', (int) $current['updated']) ?>
                                        <br>
                                        <small class="text-muted">
                                            <?= ags_fmt($lang->announcements['lbl_ago'], mkprettytime(TIMENOW - (int) $current['updated'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-success"><?= $lang->announcements['lbl_never'] ?></span>
                                        <br><small class="text-muted"><?= $lang->announcements['lbl_original'] ?></small>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats tab -->
        <div class="tab-pane fade" id="stats" role="tabpanel">
            <div class="row text-center">
                <?php
                $statCards = [
                    [$viewCount,       $lang->announcements['stat_total_views'], 'primary'],
                    [$totalCount,      $lang->announcements['stat_total'],       'success'],
                    [$currentPosition, $lang->announcements['stat_position'],    'info'],
                ];
                foreach ($statCards as [$val, $label, $color]): ?>
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="display-5 text-<?= $color ?>"><?= $val ?></div>
                                <p class="card-text"><?= $label ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($viewCount > 0): ?>
                <div class="card">
                    <div class="card-header bg-light"><h6 class="mb-0"><?= $lang->announcements['sec_view_history'] ?></h6></div>
                    <div class="card-body">
                        <canvas id="seeViewChart" height="100"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Sidebar panel inside the "see" modal.
 */
function renderSeeSidebar(
    array  $current,
    ?array $prev,
    ?array $next,
    int    $totalCount,
    int    $currentPosition,
    string $scriptName
): void {
    global $lang;

    $base = $scriptName . '?act=announcements';
    $id   = (int) $current['id'];
    ?>
    <!-- Quick Actions -->
    <div class="mb-4">
        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-bolt me-2"></i><?= $lang->announcements['sec_quick_actions'] ?></h6>
        <div class="d-grid gap-2">
            <a href="<?= $base ?>&action=edit&id=<?= $id ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit me-2"></i><?= $lang->announcements['btn_edit'] ?>
            </a>
            <button class="btn btn-danger btn-sm"
                    onclick="openDeleteModal(<?= $id ?>, '<?= htmlspecialchars(addslashes($current['subject'])) ?>')">
                <i class="fas fa-trash me-2"></i><?= $lang->announcements['btn_delete'] ?>
            </button>
            <button class="btn btn-success btn-sm" id="duplicateBtn" onclick="seeDuplicate(<?= $id ?>)">
                <i class="fas fa-copy me-2"></i><?= $lang->announcements['btn_duplicate'] ?>
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <div class="mb-4">
        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-compass me-2"></i><?= $lang->announcements['sec_navigation'] ?></h6>
        <div class="d-grid gap-2">
            <?php foreach ([
                ['prev', $prev, 'arrow-left',  $lang->announcements['lbl_prev'], $lang->announcements['lbl_no_prev']],
                ['next', $next, 'arrow-right', $lang->announcements['lbl_next'], $lang->announcements['lbl_no_next']],
            ] as [$dir, $neighbour, $icon, $label, $noLabel]): ?>
                <?php if ($neighbour): ?>
                    <a href="<?= $base ?>&action=see&id=<?= (int)$neighbour['id'] ?>"
                       class="btn btn-outline-primary btn-sm text-start">
                        <i class="fas fa-<?= $icon ?> me-2"></i>
                        <small><?= $label ?></small>
                        <div class="text-truncate" style="font-size:.8em">
                            <?= htmlspecialchars(mb_substr($neighbour['subject'], 0, 25)) ?>
                        </div>
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary btn-sm text-start" disabled>
                        <i class="fas fa-<?= $icon ?> me-2"></i>
                        <small><?= $label ?></small>
                        <div><?= $noLabel ?></div>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Auto-close Timer -->
    <div class="mb-4">
        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-hourglass-half me-2"></i><?= $lang->announcements['sec_timer'] ?></h6>
        <div class="text-center">
            <div class="display-4" id="seeTimer"><?= ANNOUNCEMENTS_TIMER_SECONDS ?></div>
            <small class="text-muted"><?= $lang->announcements['lbl_seconds_left'] ?></small>
            <div class="mt-2 d-flex gap-2 justify-content-center">
                <button class="btn btn-sm btn-outline-warning" onclick="seeResetTimer()">
                    <i class="fas fa-redo"></i> <?= $lang->announcements['btn_reset'] ?>
                </button>
                <button class="btn btn-sm btn-outline-info" id="seeTimerToggleBtn" onclick="seeToggleTimer()">
                    <i class="fas fa-pause"></i> <?= $lang->announcements['btn_pause'] ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Export -->
    <div>
        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-download me-2"></i><?= $lang->announcements['sec_export'] ?></h6>
        <div class="d-grid gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="seeExport('text')">
                <i class="fas fa-file-alt me-2"></i><?= $lang->announcements['btn_export_text'] ?>
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="seeExport('html')">
                <i class="fas fa-code me-2"></i><?= $lang->announcements['btn_export_html'] ?>
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-file-pdf me-2"></i><?= $lang->announcements['btn_print'] ?>
            </button>
        </div>
    </div>

    <!-- Shortcuts cheat-sheet -->
    <div class="mt-4">
        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-keyboard me-2"></i><?= $lang->announcements['sec_shortcuts'] ?></h6>
        <div class="row g-2 text-center">
            <?php
            $shortcuts = [
                ['← →', $lang->announcements['key_navigate']],
                ['F',   $lang->announcements['key_fullscreen']],
                ['C',   $lang->announcements['key_copy']],
                ['D',   $lang->announcements['key_dark']],
                ['ESC', $lang->announcements['key_close']],
            ];
            foreach ($shortcuts as [$key, $action]): ?>
                <div class="col-6">
                    <kbd class="d-block"><?= $key ?></kbd>
                    <small><?= $action ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Preview card (left column below the modal).
 */
function renderSeePreviewCard(array $current, int $viewCount, string $parsedMessage, string $scriptName): void
{
    global $lang;

    $base = $scriptName . '?act=announcements';
    $id   = (int) $current['id'];
    ?>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><?= $lang->announcements['sec_preview'] ?></h6>
                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#announcementModal">
                    <i class="fas fa-external-link-alt me-1"></i><?= $lang->announcements['btn_open_modal'] ?>
                </button>
            </div>
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($current['subject']) ?></h5>
                <p class="card-text text-muted small">
                   
                    <i class="fas fa-calendar me-1"></i><?= my_datee('relative', (int) $current['added']) ?>
                </p>

                <div class="preview-content border rounded p-3 bg-light mb-3" style="max-height:200px;overflow-y:auto">
                    <?= $parsedMessage ?>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-info me-1"><?= get_user_class_name((string) $current['minclassread']) ?></span>
                        <span class="badge bg-secondary">#<?= $id ?></span>
                        <span class="badge bg-danger ms-1"><?= ags_fmt($lang->announcements['lbl_views'], $viewCount) ?></span>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#announcementModal">
                            <i class="fas fa-eye me-1"></i><?= $lang->announcements['btn_full_view'] ?>
                        </button>
                        <a href="<?= $base ?>&action=edit&id=<?= $id ?>" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-edit me-1"></i><?= $lang->announcements['btn_edit'] ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Quick-stats card (right column below the modal).
 */
function renderSeeQuickStatsCard(array $current, int $viewCount, int $currentPosition, int $totalCount): void
{
    global $lang;

    $age = mkprettytime(TIMENOW - (int) $current['added']);
    ?>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light"><h6 class="mb-0"><?= $lang->announcements['sec_quick_stats'] ?></h6></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $stats = [
                        [$lang->announcements['qs_position'], "$currentPosition/$totalCount", 'primary'],
                        [$lang->announcements['qs_views'],    $viewCount,                     'success'],
                        [$lang->announcements['qs_words'],    number_format(str_word_count(strip_tags($current['message']))), 'info'],
                        [$lang->announcements['qs_age'],      $age,                           'warning'],
                    ];
                    foreach ($stats as [$label, $val, $color]): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= $label ?>
                            <span class="badge bg-<?= $color ?> rounded-pill"><?= $val ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Emits all JavaScript required by handleSeeAction — kept together for clarity.
 */
function renderSeeScripts(
    array  $current,
    ?array $prev,
    ?array $next,
    int    $viewCount,
    string $scriptName
): void {
	
	global $BASEURL, $mybb, $lang;
	
    $base        = htmlspecialchars($scriptName . '?act=announcements');
    $prevId      = $prev ? (int) $prev['id'] : 'null';
    $nextId      = $next ? (int) $next['id'] : 'null';
    $subject     = addslashes(htmlspecialchars($current['subject']));
	
	$author      = addslashes($lang->announcements['lbl_author_staff']); // нет поля автора в таблице
    
    $targetClass = addslashes(get_user_class_name((string) $current['minclassread']));
    $rawText     = addslashes(strip_tags($current['message']));
    $parsedMsg   = $current['message']; // for HTML export — already stored in PHP
    $id          = (int) $current['id'];
    $timerInit   = ANNOUNCEMENTS_TIMER_SECONDS;
    ?>
    
	<script src="<?= $BASEURL ?>/scripts/chart.js"></script>
	
    <script>
    const AGS_LANG = <?= announcementsJsLang() ?>;
    </script>
    <script>
    (() => {
        // ── State ──────────────────────────────────────────────────────────────
        let _fontSize      = 100;
        let _timerSecs     = <?= $timerInit ?>;
        let _timerPaused   = false;
        let _timerInterval = null;
        let _fullscreen    = false;
        let _sidebarVisible = true;

        const BASE_URL = '<?= $base ?>';
        const POST_KEY = '<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>';
        const PREV_ID  = <?= $prevId ?>;
        const NEXT_ID  = <?= $nextId ?>;

        // ── Helpers ────────────────────────────────────────────────────────────
        // t(key, fallback, ...args): AGS_LANG lookup with English fallback, {1}/%1$s substitution
        const t = (key, fallback, ...args) => {
            let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string')
                ? AGS_LANG[key] : fallback;
            args.forEach((arg, i) => {
                const v = String(arg);
                s = s.split('{' + (i + 1) + '}').join(v).split('%' + (i + 1) + '$s').join(v);
            });
            return s;
        };

        // Replaces element content with <i class="…"> + plain text (no innerHTML for translations)
        function setIconText(el, iconClass, text) {
            el.textContent = '';
            const icon = document.createElement('i');
            icon.className = iconClass;
            el.appendChild(icon);
            el.appendChild(document.createTextNode(text));
        }

        function notify(msg, type = 'info') {
            const el = document.createElement('div');
            el.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            el.appendChild(document.createTextNode(String(msg)));
            const closeBtn = document.createElement('button');
            closeBtn.type      = 'button';
            closeBtn.className = 'btn-close';
            closeBtn.setAttribute('data-bs-dismiss', 'alert');
            el.appendChild(closeBtn);
            Object.assign(el.style, { top: '20px', right: '20px', zIndex: 9999, minWidth: '280px' });
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        }

        function downloadFile(content, filename, mime) {
            const a = Object.assign(document.createElement('a'), {
                href: URL.createObjectURL(new Blob([content], { type: mime })),
                download: filename,
            });
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // ── Public API (window.*) ──────────────────────────────────────────────
        window.seeFontSize = function (delta) {
            _fontSize = Math.min(200, Math.max(70, _fontSize + delta));
            document.getElementById('seeContentArea').style.fontSize = _fontSize + '%';
            document.getElementById('seeFontIndicator').textContent  = _fontSize + '%';
        };

        window.seeToggleDark = function () {
            document.body.classList.toggle('dark-mode');
            const btn = document.querySelector('[onclick="seeToggleDark()"]');
            const on  = document.body.classList.contains('dark-mode');
            btn.innerHTML = `<i class="fas fa-${on ? 'sun' : 'moon'}"></i>`;
        };

        window.seeToggleFullscreen = function () {
            const dialog = document.querySelector('.modal-dialog');
            _fullscreen = !_fullscreen;
            dialog.classList.toggle('modal-fullscreen', _fullscreen);
            document.querySelector('[onclick="seeToggleFullscreen()"]').innerHTML =
                `<i class="fas fa-${_fullscreen ? 'compress' : 'expand'}"></i>`;
        };

        window.seeToggleSidebar = function () {
            const sidebar  = document.getElementById('seeSidebar');
            const mainCol  = document.getElementById('seeMainCol');
            _sidebarVisible = !_sidebarVisible;
            sidebar.style.display  = _sidebarVisible ? '' : 'none';
            mainCol.className      = _sidebarVisible ? 'col-lg-9 p-4' : 'col-lg-12 p-4';
        };

        window.seeCopy = function () {
            navigator.clipboard.writeText(document.getElementById('seeContentArea').innerText)
                .then(() => notify(t('copied_content', 'Content copied to clipboard!'), 'success'));
        };

        window.seeShare = function () {
            if (navigator.share) {
                navigator.share({ title: '<?= $subject ?>', url: window.location.href });
            } else {
                window.seeCopy();
                notify(t('copied_link', 'Link copied to clipboard!'), 'info');
            }
        };

        // ── Timer ──────────────────────────────────────────────────────────────
        function startTimer() {
            clearInterval(_timerInterval);
            _timerInterval = setInterval(() => {
                if (_timerPaused) return;
                _timerSecs--;
                document.getElementById('seeTimer').textContent = _timerSecs;
                if (_timerSecs <= 0) {
                    clearInterval(_timerInterval);
                    bootstrap.Modal.getInstance(document.getElementById('announcementModal')).hide();
                    setTimeout(() => { window.location.href = BASE_URL; }, 500);
                }
            }, 1000);
        }

        window.seeResetTimer = function () {
            _timerSecs  = <?= $timerInit ?>;
            _timerPaused = false;
            document.getElementById('seeTimer').textContent = _timerSecs;
            updateTimerBtn();
            startTimer();
        };

        window.seeToggleTimer = function () {
            _timerPaused = !_timerPaused;
            updateTimerBtn();
        };

        function updateTimerBtn() {
            const btn = document.getElementById('seeTimerToggleBtn');
            if (_timerPaused) {
                setIconText(btn, 'fas fa-play', ' ' + t('resume', 'Resume'));
            } else {
                setIconText(btn, 'fas fa-pause', ' ' + t('pause', 'Pause'));
            }
            btn.classList.toggle('btn-outline-info',    !_timerPaused);
            btn.classList.toggle('btn-outline-success',  _timerPaused);
        }

        // ── Duplicate ──────────────────────────────────────────────────────────
        window.seeDuplicate = function (id) {
            if (!confirm(t('confirm_duplicate', 'Duplicate this announcement?\n\nA copy will be created with "Copy of" prefix.'))) return;

            const btn = document.getElementById('duplicateBtn');
            btn.disabled  = true;
            setIconText(btn, 'fas fa-spinner fa-spin me-2', t('duplicating', 'Duplicating…'));

            fetch(`${BASE_URL}&action=duplicate&id=${id}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'do=duplicate&my_post_key=' + encodeURIComponent(POST_KEY),
            })
            .then(r => { if (!r.ok) throw new Error(t('network_error', 'Network error')); return r.json(); })
            .then(data => {
                if (data.success) {
                    notify(data.message, 'success');
                    setTimeout(() => { window.location.href = data.redirect_url; }, 1500);
                } else {
                    alert(t('error', 'Error: {1}', data.message));
                    btn.disabled  = false;
                    setIconText(btn, 'fas fa-copy me-2', t('duplicate', 'Duplicate'));
                }
            })
            .catch(err => {
                alert(t('network_error_fmt', 'Network error: {1}', err.message));
                btn.disabled  = false;
                setIconText(btn, 'fas fa-copy me-2', t('duplicate', 'Duplicate'));
            });
        };

        // ── Export ────────────────────────────────────────────────────────────
        window.seeExport = function (format) {
            if (format === 'text') {
                downloadFile(
                    `${t('exp_subject', 'Announcement: {1}', `<?= $subject ?>`)}\n${t('exp_date', 'Date: {1}', '...')}\n${t('exp_author', 'Author: {1}', `<?= $author ?>`)}\n${t('exp_for', 'For: {1}', `<?= $targetClass ?>`)}\n\n<?= $rawText ?>`,
                    'announcement-<?= $id ?>.txt',
                    'text/plain'
                );
            } else {
                const body = document.getElementById('seeContentArea').innerHTML;
                downloadFile(
                    `<!DOCTYPE html><html><head><meta charset="utf-8"><title><?= $subject ?></title></head><body><h1><?= $subject ?></h1>${body}</body></html>`,
                    'announcement-<?= $id ?>.html',
                    'text/html'
                );
            }
        };

        // ── Keyboard shortcuts ────────────────────────────────────────────────
        document.addEventListener('keydown', e => {
            if (e.target.matches('input, textarea, select')) return;
            if (e.key === 'ArrowLeft'  && PREV_ID) window.location.href = `${BASE_URL}&action=see&id=${PREV_ID}`;
            if (e.key === 'ArrowRight' && NEXT_ID) window.location.href = `${BASE_URL}&action=see&id=${NEXT_ID}`;
            if (e.key === 'Escape')  window.location.href = BASE_URL;
            if (e.key === 'f' || e.key === 'F') seeToggleFullscreen();
            if ((e.key === 'c' || e.key === 'C') && !e.ctrlKey && !e.metaKey) seeCopy();
            if (e.key === 'd' || e.key === 'D') seeToggleDark();
            if (e.key === 's' || e.key === 'S') seeToggleSidebar();
            if (e.key === 't' || e.key === 'T') seeToggleTimer();
        });

        // ── Init ──────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            new bootstrap.Modal(document.getElementById('announcementModal')).show();
            startTimer();

            <?php if ($viewCount > 0): ?>
            new Chart(document.getElementById('seeViewChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: [
                        t('day_mon', 'Mon'), t('day_tue', 'Tue'), t('day_wed', 'Wed'), t('day_thu', 'Thu'),
                        t('day_fri', 'Fri'), t('day_sat', 'Sat'), t('day_sun', 'Sun'),
                    ],
                    datasets: [{ label: t('chart_views', 'Views'), data: [12, 19, 8, 15, 22, 13, 18],
                        borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)', tension: 0.4 }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
            <?php endif; ?>
        });
    })();
    </script>

    <style>
        .modal-fullscreen { max-width:95vw!important; width:95vw!important; }
        .dark-mode                         { background:#1a1a1a; color:#fff; }
        .dark-mode .modal-content          { background:#2d2d2d; border-color:#444; }
        .dark-mode .bg-light               { background:#3d3d3d!important; }
        .dark-mode .text-dark              { color:#fff!important; }
        .dark-mode .border                 { border-color:#444!important; }
        .announcement-content img          { max-width:100%; height:auto; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,.1); }
        .tab-pane                          { animation:fadeIn .3s ease-in-out; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    </style>
    <?php
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Handles adding a new announcement.
 */
function handleAddAction(string $do): void
{
    global $db, $mybb, $lang;

    if ($do === 'save') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            redirect('admin/index.php?act=announcements&action=add', $lang->announcements['flash_csrf']);
        }

        $subject      = trim($_POST['subject'] ?? '');
        $message      = trim($_POST['message'] ?? '');
        $minclassread = $_POST['minclassread'] ?? '-';
       

        if ($subject === '' || $message === '') {
            redirect('admin/index.php?act=announcements&action=add', $lang->announcements['flash_required']);
        }

        $minclassValue = ($minclassread === '-') ? 0 : (int) $minclassread;

        $db->sql_query_prepared(
            "INSERT INTO announcements (`subject`,`message`,`minclassread`,`added`,`type`) VALUES (?,?,?,?,?)",
            [$subject, $message, $minclassValue, TIMENOW, 'tracker']
        );

        markUsersAsUnread($minclassValue, $db);
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_added']);
    }

    renderAnnouncementForm('add');
}

/**
 * Handles editing an existing announcement.
 */
function handleEditAction(int $id, string $do): void
{
    global $db, $mybb, $lang;

    if ($id <= 0) {
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_invalid_id']);
    }

    if ($do === 'save') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            redirect("admin/index.php?act=announcements&action=edit&id=$id", $lang->announcements['flash_csrf']);
        }

        $subject      = trim($_POST['subject'] ?? '');
        $message      = trim($_POST['message'] ?? '');
        $minclassread = $_POST['minclassread'] ?? '-';
        

        if ($subject === '' || $message === '') {
            redirect("admin/index.php?act=announcements&action=edit&id=$id", $lang->announcements['flash_required']);
        }

        $minclassValue = ($minclassread === '-') ? 0 : (int) $minclassread;

        $db->sql_query_prepared(
            "UPDATE announcements SET subject = ?, message = ?, minclassread = ?, updated = ? WHERE type = 'tracker' AND id = ?",
            [$subject, $message, $minclassValue, TIMENOW, $id]
        );




        if (($_POST['reset'] ?? '') === 'yes') {
            markUsersAsUnread($minclassValue, $db);
        }

        redirect('admin/index.php?act=announcements', $lang->announcements['flash_updated']);
    }

    $ann = fetchAnnouncement($id);
    if (!$ann) {
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_not_found']);
    }

    renderAnnouncementForm('edit', $ann);
}

/**
 * Handles deleting an announcement (requires ?sure=yes confirmation).
 */
function handleDeleteAction(int $id): void
{
    global $db, $mybb, $lang;

    if ($id <= 0) {
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_invalid_id']);
    }

    // Раньше срабатывало по простой GET-ссылке (?sure=yes), без CSRF
    // вообще - сторонняя страница могла обманом заставить залогиненного
    // стаффа удалить объявление незаметно. Теперь только POST + токен.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'), true)) {
        redirect('admin/index.php?act=announcements', $lang->announcements['flash_bad_request_token']);
    }

    $db->sql_query_prepared("DELETE FROM announcements WHERE type = 'tracker' AND id = ?", [$id]);
    redirect('admin/index.php?act=announcements', $lang->announcements['flash_deleted']);
}

/**
 * Duplicates an announcement. Supports both AJAX and plain HTTP.
 */
function handleDuplicateAction(int $id)
{
    global $db, $mybb, $lang;

    $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');

    if ($id <= 0) {
        return respondDuplicate($isAjax, false, $lang->announcements['flash_invalid_id']);
    }

    // Show confirmation form for non-AJAX GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['do'] ?? '') !== 'duplicate') {
        if ($isAjax) {
            return respondDuplicate($isAjax, false, $lang->announcements['flash_invalid_request']);
        }

        stdhead($lang->announcements['title_duplicate']);
        ?>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header bg-warning"><h5 class="mb-0"><?= $lang->announcements['title_duplicate'] ?></h5></div>
                <div class="card-body">
                    <p><?= $lang->announcements['dup_confirm'] ?></p>
                    <form method="POST">
                        <input type="hidden" name="do" value="duplicate">
                        <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>">
                        <button type="submit" class="btn btn-success"><?= $lang->announcements['btn_yes_duplicate'] ?></button>
                        <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements&action=see&id=<?= $id ?>"
                           class="btn btn-secondary ms-2"><?= $lang->announcements['btn_cancel'] ?></a>
                    </form>
                </div>
            </div>
        </div>
        <?php
        stdfoot();
        return;
    }

    // CSRF - раньше отсутствовал, теперь проверяется до самого дублирования.
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        return respondDuplicate($isAjax, false, $lang->announcements['flash_csrf']);
    }

    $original = fetchAnnouncement($id);
    if (!$original) {
        return respondDuplicate($isAjax, false, $lang->announcements['flash_original_not_found']);
    }

    $newSubject = buildCopySubject($original['subject'], $db);

    $db->sql_query_prepared(
        "INSERT INTO announcements (`subject`,`message`,`minclassread`,`added`,`type`) VALUES (?,?,?,?,?)",
        [$newSubject, $original['message'], $original['minclassread'], TIMENOW, 'tracker']
    );

    $newId = $db->insert_id();

    if (!$newId) {
        return respondDuplicate($isAjax, false, $lang->announcements['flash_dup_failed']);
    }

    markUsersAsUnread((int) $original['minclassread'], $db);

    $redirectUrl = $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=see&id=' . $newId;
    respondDuplicate($isAjax, true, $lang->announcements['flash_dup_ok'], $newId, $redirectUrl);
}

// ──────────────────────────────────────────────────────────────────────────────
// Small private helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fetches a single announcement row or returns null.
 */
function fetchAnnouncement(int $id): ?array
{
    global $db;
    $res = $db->sql_query_prepared("SELECT * FROM announcements WHERE type = 'tracker' AND id = ?", [$id]);
    $row = $res ? $db->fetch_array($res) : null;
    return $row ?: null;
}

/**
 * Marks eligible users' announce_read as 'no'.
 * $minclass === 0 means all users.
 */
function markUsersAsUnread(int $minclass, object $db): void
{
    $base = "UPDATE users SET announce_read = 'no' WHERE enabled = 'yes' AND ustatus = 'confirmed'";
    if ($minclass === 0) {
        $db->sql_query_prepared($base);
    } else {
        $db->sql_query_prepared($base . " AND usergroup = ?", [$minclass]);
    }
}

/**
 * Produces a unique "Copy of …" subject string.
 */
function buildCopySubject(string $originalSubject, object $db): string
{
    global $lang;

    $base = ags_fmt($lang->announcements['lbl_copy_subject'], $originalSubject);
    $q    = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM announcements WHERE type = 'tracker' AND subject LIKE ?", [$base . '%']);
    $row  = $q ? $db->fetch_array($q) : null;
    return ((int) ($row['c'] ?? 0) === 0) ? $base : $base . ' (' . ((int) $row['c'] + 1) . ')';
}

/**
 * Sends a JSON or redirect response for handleDuplicateAction.
 */
function respondDuplicate(
    bool   $isAjax,
    bool   $success,
    string $message,
    int    $newId = 0,
    string $redirectUrl = ''
): void {
    if ($isAjax) {
        header('Content-Type: application/json');
        $payload = ['success' => $success, 'message' => $message];
        if ($success) {
            $payload['new_id']       = $newId;
            $payload['redirect_url'] = $redirectUrl;
        }
        echo json_encode($payload);
        exit;
    }

    redirect(
        $success ? 'admin/index.php?act=announcements&action=see&id=' . $newId
                 : 'admin/index.php?act=announcements',
        $message
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Announcement form (add / edit)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Renders the shared add/edit form.
 */
function renderAnnouncementForm(string $mode, array $data = []): void
{
    global $smilies, $_this_script_, $BASEURL, $mybb, $lang;

    $isEdit = ($mode === 'edit');
    $title  = $isEdit ? $lang->announcements['title_edit'] : $lang->announcements['title_add'];

    stdhead($title . ' ' . B_VERSION);
    ?>
    <script>
        const smilies = <?= json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const AGS_LANG = <?= announcementsJsLang() ?>;
    </script>
    <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bbcode.css">
    <script src="<?= $BASEURL ?>/scripts/bbcode_tools.js"></script>

    
	
	
	
	
	<div class="container mt-3">
	
	
	<div class="d-flex align-items-center gap-2 mb-4">
            <a href="<?= $_this_script_ ?>?act=announcements" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h4 class="mb-0"><i class="fas fa-bullhorn me-2 text-primary"></i><?= $title ?></h4>
        </div>
	
	
	
	
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><?= $title ?></h4>
            </div>
			
			
			
			 
			
			
			
			
			
			
			
			
			
            <div class="card-body">
                <form method="post" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']) ?>">
                    <input type="hidden" name="act"    value="announcements">
                    <input type="hidden" name="action" value="<?= $mode ?>">
                    <input type="hidden" name="do"     value="save">
                    <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>">
					
					
					
					
					

					
					
					
					
					
					
					
					
					
					
					
					
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?= (int) ($data['id'] ?? 0) ?>">
                    <?php endif; ?>
					
					
					
					
					
					
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold"><?= $lang->announcements['lbl_subject'] ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject"
                                   value="<?= htmlspecialchars($data['subject'] ?? '') ?>"
                                   maxlength="120" placeholder="<?= htmlspecialchars($lang->announcements['ph_subject']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?= $lang->announcements['lbl_minclass'] ?></label>
                           <?= _selectbox_('', 'minclassread', true, $lang->announcements['opt_all_users'], $data['minclassread'] ?? 0) ?>
                        </div>
                    </div>
               
					
					
					
					
					
					
					
					
					
					
					

                   

                    <?php if ($isEdit): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="reset" value="yes" id="resetRead">
                            <label class="form-check-label" for="resetRead">
                                <?= $lang->announcements['lbl_mark_unread'] ?>
                            </label>
                            <div class="form-text"><?= $lang->announcements['hint_mark_unread'] ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label"><?= $lang->announcements['lbl_message'] ?></label>
                        <?php renderBBCodeToolbar('message'); ?>
                        <textarea class="form-control" id="message" name="message"
                                  rows="12" required
                                  placeholder="<?= htmlspecialchars($lang->announcements['ph_message']) ?>"
                        ><?= htmlspecialchars($data['message'] ?? '') ?></textarea>
                        <div class="form-text text-end">
                            <span id="charCount">0</span> / <?= ANNOUNCEMENTS_MAX_CHARS ?> <?= $lang->announcements['lbl_characters'] ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?= $_SERVER['SCRIPT_NAME'] ?>?act=announcements" class="btn btn-secondary">
                            <?= $lang->announcements['btn_cancel'] ?>
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i><?= $lang->announcements['btn_save'] ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Live preview -->
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><?= $lang->announcements['sec_preview'] ?></h6></div>
            <div class="card-body">
                <div id="previewArea" class="p-3 border rounded bg-light">
                    <em><?= $lang->announcements['lbl_preview_placeholder'] ?></em>
                </div>
                <button type="button" class="btn btn-outline-info mt-3" onclick="formUpdatePreview()">
                    <i class="fas fa-sync me-1"></i><?= $lang->announcements['btn_update_preview'] ?>
                </button>
            </div>
        </div>
    </div>

    <script>
    (() => {
        // t(key, fallback, ...args): AGS_LANG lookup with English fallback, {1}/%1$s substitution
        const t = (key, fallback, ...args) => {
            let s = (typeof AGS_LANG === 'object' && AGS_LANG !== null && typeof AGS_LANG[key] === 'string')
                ? AGS_LANG[key] : fallback;
            args.forEach((arg, i) => {
                const v = String(arg);
                s = s.split('{' + (i + 1) + '}').join(v).split('%' + (i + 1) + '$s').join(v);
            });
            return s;
        };

        const textarea = document.getElementById('message');
        const counter  = document.getElementById('charCount');

        function updateCounter() { counter.textContent = textarea.value.length; }
        textarea.addEventListener('input', updateCounter);
        document.addEventListener('DOMContentLoaded', updateCounter);

        window.formUpdatePreview = function () {
            const val     = textarea.value.trim();
            const preview = document.getElementById('previewArea');

            if (!val) {
                const empty = document.createElement('em');
                empty.textContent = t('no_preview', 'No content to preview');
                preview.textContent = '';
                preview.appendChild(empty);
                return;
            }

            // Client-side basic BBCode → HTML (server-side parse recommended via AJAX)
            const html = val
                .replace(/\[b\]([\s\S]*?)\[\/b\]/gi,         '<strong>$1</strong>')
                .replace(/\[i\]([\s\S]*?)\[\/i\]/gi,          '<em>$1</em>')
                .replace(/\[u\]([\s\S]*?)\[\/u\]/gi,          '<u>$1</u>')
                .replace(/\[url\]([\s\S]*?)\[\/url\]/gi,      '<a href="$1">$1</a>')
                .replace(/\n/g, '<br>');

            const note = document.createElement('small');
            note.className   = 'text-muted d-block mb-2';
            note.textContent = t('preview_note', 'Basic preview only — final rendering may differ.');

            const body = document.createElement('div');
            body.innerHTML = html; // BBCode→HTML of the message itself, as before

            preview.textContent = '';
            preview.appendChild(note);
            preview.appendChild(body);
        };
    })();
    </script>
    <?php
    stdfoot();
}

/**
 * Outputs the BBCode toolbar buttons for a given textarea ID.
 */
function renderBBCodeToolbar(string $textareaId): void
{
    global $lang;

    $buttons = [
        ['[b]',       '[/b]',       '<strong>B</strong>'],
        ['[i]',       '[/i]',       '<em>I</em>'],
        ['[u]',       '[/u]',       '<u>U</u>'],
        ['[s]',       '[/s]',       'S'],
        ['[url]',     '[/url]',     'URL'],
        ['[img]',     '[/img]',     'IMG'],
        ['[center]',  '[/center]',  $lang->announcements['bb_center']],
        ['[left]',    '[/left]',    $lang->announcements['bb_left']],
        ['[right]',   '[/right]',   $lang->announcements['bb_right']],
        ['[quote]',   '[/quote]',   $lang->announcements['bb_quote']],
        ['[code]',    '[/code]',    $lang->announcements['bb_code']],
        ['[spoiler]', '[/spoiler]', $lang->announcements['bb_spoiler']],
    ];

    // List template items go into an onclick JS string: JS-escape, then HTML-escape
    $listItem1 = htmlspecialchars(addslashes(ags_fmt($lang->announcements['bb_list_item'], 1)), ENT_QUOTES);
    $listItem2 = htmlspecialchars(addslashes(ags_fmt($lang->announcements['bb_list_item'], 2)), ENT_QUOTES);
    ?>
    <div class="mb-2 d-flex flex-wrap gap-1">
        <?php foreach ($buttons as [$open, $close, $label]): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="insertBBCode('<?= $open ?>', '<?= $close ?>', '<?= $textareaId ?>')">
                <?= $label ?>
            </button>
        <?php endforeach; ?>

        <!-- List (multi-line open tag) -->
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="insertBBCode('[list]\n[*]<?= $listItem1 ?>\n[*]<?= $listItem2 ?>\n[/list]', '', '<?= $textareaId ?>')">
            <?= $lang->announcements['bb_list'] ?>
        </button>

        <!-- YouTube -->
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="insertBBCode('[video=youtube]', '[/video]', '<?= $textareaId ?>')">
            <?= $lang->announcements['bb_youtube'] ?>
        </button>

        <!-- Color picker -->
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn"
                    data-textarea="<?= $textareaId ?>">
                🎨 <?= $lang->announcements['bb_color'] ?>
            </button>
            <div class="color-palette d-none"></div>
        </div>

        <!-- Smilies -->
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="smileyBtn">😊</button>
            <div class="smiley-panel d-none border p-2 bg-white shadow-sm position-absolute"
                 id="smileyPanel" style="z-index:1000"></div>
        </div>

        <!-- Size -->
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary size-picker-btn"
                    id="sizeBtn-<?= $textareaId ?>" data-textarea="<?= $textareaId ?>">
                <?= $lang->announcements['bb_size'] ?>
            </button>
            <div class="size-menu dropdown-menu p-2" id="sizeMenu-<?= $textareaId ?>"></div>
        </div>

        <!-- Font -->
        <div class="btn-group position-relative">
            <button type="button" class="btn btn-sm btn-outline-secondary font-picker-btn"
                    id="fontBtn-<?= $textareaId ?>" data-textarea="<?= $textareaId ?>">
                <?= $lang->announcements['bb_font'] ?>
            </button>
            <div class="font-menu dropdown-menu p-2 shadow" id="fontMenu-<?= $textareaId ?>"></div>
        </div>
    </div>
    <?php
}