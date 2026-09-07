<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit("<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> <strong>Error!</strong> Direct initialization of this file is not allowed.</div>");
}

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

stdhead('Manage Site News');

define('IN_EDITOR', true);

require_once $rootpath . 'cache/smilies.php';
require_once INC_PATH . '/editor.php';

$editor = insert_bbcode_editor($smilies, $BASEURL, 'newsMessage');

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
            $title   = htmlspecialchars($arr['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $userid  = (int)$arr['userid'];
            $added   = my_datee('relative', $arr['added'] ?? TIMENOW);
            $poster  = format_name($arr['username'] ?? '', $arr['usergroup'] ?? '');
            $by      = $poster
                ? '<a href="' . $BASEURL . '/' . get_profile_link($userid) . '" class="text-decoration-none fw-bold">' . $poster . '</a>'
                : 'Unknown User';
            $escaped = htmlspecialchars($body2, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $newsItems .= '
            <div class="card shadow-sm mb-4 news-card fade-in-up"
                 data-newsid="' . $newsid . '"
                 data-body="' . $escaped . '"
                 id="news-' . $newsid . '">
                <div class="card-header bg-white py-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="news-icon">
                                <i class="fas fa-newspaper fa-2x text-primary"></i>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-calendar-alt me-1"></i> ' . $added . '
                                <i class="fas fa-user ms-2 me-1"></i> by ' . $by . '
                            </div>
                        </div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary news-edit"
                                    data-newsid="' . $newsid . '"
                                    title="Edit News">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger news-delete"
                                    data-newsid="' . $newsid . '"
                                    title="Delete News">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <h4 class="card-title fw-bold mb-3">' . $title . '</h4>
                    <div class="news-body">' . $body . '</div>
                </div>
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        <i class="fas fa-tag me-1"></i> News ID: #' . $newsid . '
                    </small>
                </div>
            </div>';
        }
    } else {
        $newsItems = '
        <div class="text-center py-5 empty-state fade-in-up" id="emptyState">
            <i class="fas fa-newspaper fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">No News Available</h4>
            <p class="text-muted">Be the first to create a news article!</p>
        </div>';
    }
} catch (\Throwable $e) {
    error_log("News panel error: " . $e->getMessage());
    $newsItems = '
    <div class="alert alert-danger fade-in-up">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Error loading news:</strong> '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8') .
    '</div>';
}
?>



<div class="container py-4">

    <!-- Create News Card -->
    <div class="card shadow-sm mb-4 border-0 fade-in-up">
        <div class="card-header bg-primary text-white py-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-pen-alt fa-2x me-3"></i>
                <div>
                    <h4 class="mb-0 fw-bold">Create News Article</h4>
                    <small class="opacity-75">Share important updates with your community</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <?= $editor['toolbar'] ?>
            <form method="post" id="newsAddForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-heading me-1 text-primary"></i> News Title
                    </label>
                    <input type="text" class="form-control form-control-lg" name="subject"
                           placeholder="Enter an engaging title..." maxlength="255">
                </div>
                <div id="fileIdsContainer"></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-align-left me-1 text-primary"></i> News Content
                    </label>
                    <textarea name="newsMessage" id="newsMessage" class="form-control"
                              rows="8" maxlength="5000"
                              placeholder="Write your news content here..."></textarea>
                    <div class="form-text text-end mt-1">
                        <i class="fas fa-keyboard"></i>
                        <span id="charCount">0</span> / 5000 characters
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="fas fa-paper-plane me-2"></i>Publish News
                    </button>
                    <button type="reset" class="btn btn-outline-secondary px-4 py-2">
                        <i class="fas fa-undo me-2"></i>Clear
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?= $editor['modal'] ?>

    <!-- News Archive -->
    <div class="d-flex align-items-center mb-4 mt-4">
        <i class="fas fa-newspaper fa-2x text-primary me-3"></i>
        <h3 class="mb-0 fw-bold">News Archive</h3>
        <div class="ms-auto">
            <span class="badge bg-primary" id="newsCount"><?= $newsCount ?></span>
        </div>
    </div>

    <div id="newsList">
        <?= $newsItems ?>
    </div>

</div>

<!-- Edit Modal -->
<div class="modal fade" id="newsEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit News Article
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editNewsId">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-heading me-1 text-primary"></i> Title
                    </label>
                    <input type="text" class="form-control" id="editTitle"
                           placeholder="Enter news title" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-align-left me-1 text-primary"></i> Content
                    </label>
                    <div class="mb-2 p-2 bg-light rounded d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[b]','[/b]')"><b>B</b></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[i]','[/i]')"><i>I</i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[u]','[/u]')"><u>U</u></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[s]','[/s]')"><s>S</s></button>
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[left]','[/left]')">Left</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[center]','[/center]')">Center</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[right]','[/right]')">Right</button>
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-danger"    onclick="wrapBB('[color=red]','[/color]')">Red</button>
                        <button type="button" class="btn btn-sm btn-outline-primary"   onclick="wrapBB('[color=blue]','[/color]')">Blue</button>
                        <button type="button" class="btn btn-sm btn-outline-success"   onclick="wrapBB('[color=green]','[/color]')">Green</button>
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[url]','[/url]')">URL</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[img]','[/img]')">IMG</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[video]','[/video]')">Video</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[youtube]','[/youtube]')">YouTube</button>
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[quote]','[/quote]')">Quote</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[code]','[/code]')">Code</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[spoiler]','[/spoiler]')">Spoiler</button>
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[list]\n[*]','\n[/list]')" title="Bulleted List"><i class="fas fa-list-ul"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[list=1]\n[*]','\n[/list]')" title="Numbered List"><i class="fas fa-list-ol"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[*]','')" title="List Item">[*]</button>
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="torrentPanelToggleEdit" title="Embed Torrent Card"><i class="fa-solid fa-magnet"></i> Torrent</button>
                    </div>
                    <!-- Встроенная панель вставки торрента - скрыта, пока не нажата кнопка "Torrent" -->
                    <div id="torrentPanelEdit" class="border rounded p-3 mb-2 d-none">
                        <label class="form-label">Torrent ID or URL</label>
                        <div class="input-group">
                            <input type="text" inputmode="numeric" class="form-control" id="torrentIdInputEdit" placeholder="e.g. 17 or paste the torrent link">
                            <button type="button" class="btn btn-primary" id="insertTorrentBtnEdit">Insert</button>
                        </div>
                        <div id="torrentPreviewEdit" class="mt-2"></div>
                    </div>
                    <textarea id="editBody" class="form-control" rows="8"
                              placeholder="Write your news content here..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-eye me-1 text-primary"></i> Live Preview
                    </label>
                    <div id="bbcodeNewsPreview" class="border p-3 rounded">
                        <small class="text-muted">Preview will appear here as you type...</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveEditBtn" onclick="submitNewsEdit()">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.newsPostKey = <?= json_encode($mybb->post_code ?? '') ?>;
    window.newsBaseUrl = <?= json_encode($BASEURL ?? '') ?>;
</script>
<script src="<?= htmlspecialchars($BASEURL) ?>/admin/scripts/news.js"></script>

<?php
stdfoot();
exit;