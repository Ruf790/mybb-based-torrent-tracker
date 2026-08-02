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

<style>
:root { --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-primary { background: var(--gradient-primary) !important; }
.fade-in-up { animation: fadeInUp .5s ease-out; }
@keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:none; } }
.news-card { transition: all .3s ease; border:none; border-radius:15px; overflow:hidden; }
.news-card:hover { transform:translateY(-4px); box-shadow:0 10px 30px rgba(0,0,0,.12) !important; }
.news-icon { width:50px; height:50px; background:rgba(102,126,234,.08); border-radius:12px; display:flex; align-items:center; justify-content:center; }
.empty-state { background:linear-gradient(135deg,#f8f9fa,#e9ecef); border-radius:15px; padding:3rem; }
.form-control,.form-control-lg { border-radius:10px; border:2px solid #e2e8f0; transition:all .3s ease; }
.form-control:focus,.form-control-lg:focus { border-color:#667eea; box-shadow:0 0 0 .2rem rgba(102,126,234,.25); }
.modal-content { border-radius:20px; border:none; overflow:hidden; }
.modal-header { background:var(--gradient-primary); border:none; }
.modal-footer { border-top:1px solid #e2e8f0; }
.btn-sm { border-radius:8px; margin:2px; transition:all .2s ease; }
.btn-sm:hover { transform:translateY(-1px); }
#bbcodeNewsPreview { background:#f8f9fa; border-radius:10px; min-height:100px; max-height:300px; overflow-y:auto; }
@media (max-width:768px) {
    .news-card .card-header > div { flex-direction:column; align-items:flex-start !important; }
    .btn-group { width:100%; }
    .btn-group .btn { flex:1; }
}
</style>

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
                        <span class="mx-1 text-muted">|</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[quote]','[/quote]')">Quote</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="wrapBB('[code]','[/code]')">Code</button>
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
(function () {
'use strict';

var newsPostKey = <?= json_encode($mybb->post_code ?? '') ?>;

// ── Char counter ──────────────────────────────────────────────────────────────
var ta  = document.getElementById('newsMessage');
var cnt = document.getElementById('charCount');
if (ta && cnt) {
    ta.addEventListener('input', function () {
        var l = this.value.length;
        cnt.textContent = l;
        cnt.classList.toggle('text-danger', l > 4800);
        this.classList.toggle('is-invalid', l > 5000);
    });
}

// ── Notification ──────────────────────────────────────────────────────────────
function showNotification(message, type) {
    var cls = type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info');
    var ico = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    var n   = document.createElement('div');
    n.className = 'alert alert-' + cls + ' alert-dismissible fade-in-up position-fixed top-0 start-50 translate-middle-x mt-3';
    n.style.cssText = 'z-index:9999;min-width:300px;max-width:500px;box-shadow:0 5px 20px rgba(0,0,0,.2)';
    n.innerHTML = '<i class="fas ' + ico + ' me-2"></i>' + message +
                  '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(n);
    setTimeout(function () { if (n.parentNode) n.remove(); }, 3500);
}

// ── Update count / empty state ────────────────────────────────────────────────
function updateCount() {
    var cards = document.querySelectorAll('.news-card').length;
    var span  = document.getElementById('newsCount');
    if (span) span.textContent = cards;
    var list  = document.getElementById('newsList');
    if (cards === 0 && list && !list.querySelector('.empty-state')) {
        list.innerHTML =
            '<div class="text-center py-5 empty-state fade-in-up" id="emptyState">' +
            '<i class="fas fa-newspaper fa-4x text-muted mb-3"></i>' +
            '<h4 class="text-muted">No News Available</h4>' +
            '<p class="text-muted">Create your first news article!</p></div>';
    }
}

// ── Add news ──────────────────────────────────────────────────────────────────
var addForm = document.getElementById('newsAddForm');
if (addForm) {
    addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn  = this.querySelector('button[type="submit"]');
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Publishing...';
        btn.disabled  = true;

        var fd = new FormData(this);
        fd.append('action', 'add');
        fd.append('my_post_key', newsPostKey);

        fetch('news_ajax.php', { method: 'POST', body: new URLSearchParams(fd) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showNotification('News published successfully!', 'success');
                    setTimeout(function () { location.reload(); }, 1000);
                } else {
                    showNotification(d.error || 'Failed to add news', 'error');
                    btn.innerHTML = orig;
                    btn.disabled  = false;
                }
            })
            .catch(function () {
                showNotification('Network error. Please try again.', 'error');
                btn.innerHTML = orig;
                btn.disabled  = false;
            });
    });
}

// ── Event delegation: edit & delete ──────────────────────────────────────────
var newsList = document.getElementById('newsList');
if (newsList) {
    newsList.addEventListener('click', function (e) {
        // ── Delete ────────────────────────────────────────────────────────────
        var delBtn = e.target.closest('.news-delete');
        if (delBtn) {
            e.preventDefault();
            var card  = delBtn.closest('.news-card');
            var id    = card ? card.dataset.newsid : null;
            var title = card ? (card.querySelector('.card-title') || {}).textContent || '' : '';
            if (!id) { showNotification('Invalid news item', 'error'); return; }

            if (!confirm('Delete "' + title.trim() + '"?\nThis cannot be undone.')) return;

            var origHTML    = delBtn.innerHTML;
            delBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';
            delBtn.disabled  = true;

            fetch('news_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete&newsid=' + encodeURIComponent(id) + '&my_post_key=' + encodeURIComponent(newsPostKey)
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    card.style.transition = 'all .3s ease';
                    card.style.opacity    = '0';
                    card.style.transform  = 'translateY(-10px)';
                    setTimeout(function () { card.remove(); updateCount(); showNotification('News deleted!', 'success'); }, 300);
                } else {
                    showNotification(d.error || 'Failed to delete', 'error');
                    delBtn.innerHTML = origHTML;
                    delBtn.disabled  = false;
                }
            })
            .catch(function () {
                showNotification('Network error.', 'error');
                delBtn.innerHTML = origHTML;
                delBtn.disabled  = false;
            });
            return;
        }

        // ── Edit ──────────────────────────────────────────────────────────────
        var editBtn = e.target.closest('.news-edit');
        if (editBtn) {
            e.preventDefault();
            var card  = editBtn.closest('.news-card');
            var id    = card ? card.dataset.newsid : null;
            var title = card ? (card.querySelector('.card-title') || {}).textContent || '' : '';
            var body  = card ? (card.dataset.body || '') : '';
            if (!id) { showNotification('Invalid news item', 'error'); return; }

            document.getElementById('editNewsId').value = id;
            document.getElementById('editTitle').value  = title.trim();
            document.getElementById('editBody').value   = body;
            updatePreview();

            new bootstrap.Modal(document.getElementById('newsEditModal')).show();
        }
    });
}

// ── BBCode wrap ───────────────────────────────────────────────────────────────
window.wrapBB = function (open, close) {
    var ta  = document.getElementById('editBody');
    if (!ta) return;
    var s   = ta.selectionStart, en = ta.selectionEnd;
    var sel = ta.value.substring(s, en);
    ta.value = ta.value.substring(0, s) + open + sel + close + ta.value.substring(en);
    ta.focus();
    ta.setSelectionRange(s + open.length, s + open.length + sel.length);
    updatePreview();
};

// ── Live preview ──────────────────────────────────────────────────────────────
function updatePreview() {
    var ta  = document.getElementById('editBody');
    var pre = document.getElementById('bbcodeNewsPreview');
    if (!ta || !pre) return;

    if (!ta.value.trim()) {
        pre.innerHTML = '<small class="text-muted">Preview will appear here...</small>';
        return;
    }

    // flags: g = global, s = dotAll (multiline BBCode)
    pre.innerHTML = ta.value
        .replace(/\[b\]([\s\S]*?)\[\/b\]/g,         '<strong>$1</strong>')
        .replace(/\[i\]([\s\S]*?)\[\/i\]/g,         '<em>$1</em>')
        .replace(/\[u\]([\s\S]*?)\[\/u\]/g,         '<u>$1</u>')
        .replace(/\[s\]([\s\S]*?)\[\/s\]/g,         '<s>$1</s>')
        .replace(/\[left\]([\s\S]*?)\[\/left\]/g,   '<div style="text-align:left">$1</div>')
        .replace(/\[center\]([\s\S]*?)\[\/center\]/g,'<div style="text-align:center">$1</div>')
        .replace(/\[right\]([\s\S]*?)\[\/right\]/g, '<div style="text-align:right">$1</div>')
        .replace(/\[color=(.*?)\]([\s\S]*?)\[\/color\]/g, '<span style="color:$1">$2</span>')
        .replace(/\[size=(\d+)\]([\s\S]*?)\[\/size\]/g,   '<span style="font-size:$1px">$2</span>')
        .replace(/\[url\]([\s\S]*?)\[\/url\]/g,     '<a href="$1" target="_blank">$1</a>')
        .replace(/\[url=(.*?)\]([\s\S]*?)\[\/url\]/g,'<a href="$1" target="_blank">$2</a>')
        .replace(/\[img\]([\s\S]*?)\[\/img\]/g,     '<img src="$1" style="max-width:60%;height:auto" class="rounded">')
        .replace(/\[video\]([\s\S]*?)\[\/video\]/g, '<video controls style="max-width:100%"><source src="$1"></video>')
        .replace(/\[quote\]([\s\S]*?)\[\/quote\]/g, '<blockquote class="border-start border-3 border-primary ps-3 my-2">$1</blockquote>')
        .replace(/\[code\]([\s\S]*?)\[\/code\]/g,   '<code class="bg-dark text-light p-2 rounded d-block">$1</code>')
        .replace(/\[list\]([\s\S]*?)\[\/list\]/g,   '<ul>$1</ul>')
        .replace(/\[list=1\]([\s\S]*?)\[\/list\]/g, '<ol>$1</ol>')
        .replace(/\[\*\](.*?)(?=\n|$)/g,            '<li>$1</li>')
        .replace(/\n/g, '<br>');
}

window.updateNewsPreview = updatePreview;

document.getElementById('editBody')?.addEventListener('input', updatePreview);

document.getElementById('newsEditModal')?.addEventListener('shown.bs.modal', function () {
    updatePreview();
    document.getElementById('editBody')?.focus();
});

// ── Submit edit ───────────────────────────────────────────────────────────────
window.submitNewsEdit = function () {
    var id    = (document.getElementById('editNewsId') || {}).value || '';
    var title = (document.getElementById('editTitle')  || {}).value || '';
    var body  = (document.getElementById('editBody')   || {}).value || '';

    if (!title.trim()) { showNotification('Title cannot be empty.', 'error'); return; }
    if (!body.trim())  { showNotification('Content cannot be empty.', 'error'); return; }

    var btn  = document.getElementById('saveEditBtn');
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    btn.disabled  = true;

    var fd = new FormData();
    fd.append('action', 'edit');
    fd.append('newsid', id);
    fd.append('title',  title);
    fd.append('body',   body);
    fd.append('my_post_key', newsPostKey);

    fetch('news_ajax.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showNotification('News updated!', 'success');
                setTimeout(function () { location.reload(); }, 900);
            } else {
                showNotification(d.error || 'Edit failed.', 'error');
                btn.innerHTML = orig;
                btn.disabled  = false;
            }
        })
        .catch(function () {
            showNotification('Network error.', 'error');
            btn.innerHTML = orig;
            btn.disabled  = false;
        });
};

})();
</script>

<?php
stdfoot();
exit;