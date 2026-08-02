<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('SCRIPTNAME', 'offers.php');

require_once 'global.php';
require_once 'cache/smilies.php';
require_once INC_PATH . '/editor.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/class_parser.php';

$parser = new postParser;
$parser_options = [
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

if (empty($CURUSER['id'])) print_no_permission();


if (session_status() === PHP_SESSION_NONE) session_start();

$is_mod = is_mod($usergroups);
$action = $mybb->get_input('action');
$oid    = $mybb->get_input('oid', MyBB::INPUT_INT);

// ── Flash helper ──────────────────────────────────────────────────────────────
function show_flash(): void {
    global $BASEURL;
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $type = match($f['type']) {
            'success' => 'success',
            'danger'  => 'error',
            'warning' => 'warning',
            default   => 'info',
        };
        echo '<script src="' . $BASEURL . '/scripts/toast.js"></script>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){ showToast(' . json_encode($f['msg']) . ',' . json_encode($type) . '); });</script>';
        unset($_SESSION['flash']);
    }
}

// ── Load categories ───────────────────────────────────────────────────────────
$cats = [];
$q = $db->sql_query_prepared("SELECT id, name FROM categories ORDER BY name");
while ($r = $db->fetch_array($q)) $cats[$r['id']] = $r['name'];

// ── AJAX: Request upload ──────────────────────────────────────────────────────
if ($action === 'want' && $mybb->request_method === 'post') {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid post key']); exit();
    }
    $uid = (int)$CURUSER['id'];
    $offer = $db->fetch_array($db->sql_query_prepared("SELECT id, status, user_id FROM offers WHERE id = ?", [$oid]));
    if (!$offer) { echo json_encode(['success' => false, 'error' => 'Not found']); exit(); }
    if ($offer['status'] !== 'open') { echo json_encode(['success' => false, 'error' => 'Offer is closed']); exit(); }
    if ($offer['user_id'] == $uid) { echo json_encode(['success' => false, 'error' => 'Cannot request your own offer']); exit(); }
    if ($db->num_rows($db->sql_query_prepared("SELECT id FROM offer_votes WHERE offer_id = ? AND user_id = ?", [$oid, $uid]))) {
        echo json_encode(['success' => false, 'error' => 'Already requested']); exit();
    }
    $vote_data = ['offer_id' => $oid, 'user_id' => $uid, 'created_at' => TIMENOW];
    $columns      = array_keys($vote_data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO offer_votes (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($vote_data)
    );
    $db->sql_query_prepared("UPDATE offers SET requests = requests + 1, updated_at = ? WHERE id = ?", [TIMENOW, $oid]);
    $new_count = (int)$db->fetch_field($db->sql_query_prepared("SELECT requests FROM offers WHERE id = ?", [$oid]), 'requests');
    echo json_encode(['success' => true, 'requests' => $new_count]);
    exit();
}

// ── AJAX: Add Comment ─────────────────────────────────────────────────────────
if ($action === 'add_comment' && $mybb->request_method === 'post') {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid post key']); exit();
    }
    $message = trim($mybb->get_input('message'));
    if (empty($message)) { echo json_encode(['success' => false, 'error' => 'Empty message']); exit(); }
    if (!$db->num_rows($db->sql_query_prepared("SELECT id FROM offers WHERE id = ?", [$oid]))) {
        echo json_encode(['success' => false, 'error' => 'Not found']); exit();
    }
    $comment_data = [
        'offer_id'   => $oid,
        'user_id'    => (int)$CURUSER['id'],
        'message'    => $message,
        'created_at' => TIMENOW,
    ];
    $columns      = array_keys($comment_data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO offer_comments (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($comment_data)
    );
    echo json_encode(['success' => true]);
    exit();
}

// ── POST: Create Offer ────────────────────────────────────────────────────────
if ($action === 'do_create' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    $title = trim($mybb->get_input('title'));
    $description = trim($mybb->get_input('description'));
    $category_id = $mybb->get_input('category_id', MyBB::INPUT_INT);
    $year = ($year_raw = trim($mybb->get_input('year'))) !== '' && (int)$year_raw > 0 ? (int)$year_raw : null;
    $errors = [];
    if (strlen($title) < 3) $errors[] = 'Title must be at least 3 characters.';
    if (strlen($title) > 255) $errors[] = 'Title is too long.';
    if (empty($errors)) {
        $db->sql_query_prepared(
            "INSERT INTO offers (user_id, title, description, category_id, year, status, requests, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'open', 0, ?, ?)",
            [(int)$CURUSER['id'], $title, $description, $category_id, $year, TIMENOW, TIMENOW]
        );
        $id = $db->insert_id();
        $_SESSION['flash'] = ['msg' => 'Offer created successfully!', 'type' => 'success'];
        header('Location: offers.php?action=view&oid=' . $id);
        exit();
    }
}

// ── POST: Mark as Uploaded ────────────────────────────────────────────────────
if ($action === 'upload' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    $offer = $db->fetch_array($db->sql_query_prepared("SELECT * FROM offers WHERE id = ?", [$oid]));
    if (!$offer || ($offer['user_id'] != $CURUSER['id'] && !$is_mod)) stderr('No permission');
    if ($offer['status'] !== 'open') stderr('Offer already closed');
    $torrent_id = $mybb->get_input('torrent_id', MyBB::INPUT_INT);
    if (!$torrent_id || !$db->num_rows($db->sql_query_prepared("SELECT id FROM torrents WHERE id = ?", [$torrent_id]))) {
        $_SESSION['flash'] = ['msg' => 'Torrent ID ' . $torrent_id . ' does not exist.', 'type' => 'danger'];
        header('Location: offers.php?action=view&oid=' . $oid);
        exit();
    }
    $db->sql_query_prepared(
        "UPDATE offers SET status = ?, torrent_id = ?, uploaded_at = ?, updated_at = ? WHERE id = ?",
        ['uploaded', $torrent_id, TIMENOW, TIMENOW, $oid]
    );
    require_once INC_PATH . '/functions_pm.php';
    $votes_q = $db->sql_query_prepared("SELECT user_id FROM offer_votes WHERE offer_id = ?", [$oid]);
    while ($v = $db->fetch_array($votes_q)) {
        if ($v['user_id'] == $CURUSER['id']) continue;
        $pm = ['subject' => 'Offer uploaded: ' . $offer['title'], 'message' => 'The offer [b]' . $offer['title'] . '[/b] has been uploaded! [url=' . $BASEURL . '/details.php?id=' . $torrent_id . ']Download here[/url]', 'touid' => (int)$v['user_id']];
        $pm['sender']['uid'] = -1;
        send_pm($pm, -1, true);
    }
    $_SESSION['flash'] = ['msg' => 'Offer marked as uploaded!', 'type' => 'success'];
    header('Location: offers.php?action=view&oid=' . $oid);
    exit();
}

// ── POST: Cancel ──────────────────────────────────────────────────────────────
if ($action === 'cancel' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    $offer = $db->fetch_array($db->sql_query_prepared("SELECT user_id, status FROM offers WHERE id = ?", [$oid]));
    if (!$offer || ($offer['user_id'] != $CURUSER['id'] && !$is_mod)) stderr('No permission');
    $db->sql_query_prepared("UPDATE offers SET status = ?, updated_at = ? WHERE id = ?", ['cancelled', TIMENOW, $oid]);
    $_SESSION['flash'] = ['msg' => 'Offer cancelled.', 'type' => 'info'];
    header('Location: offers.php');
    exit();
}

// ── POST: Delete ──────────────────────────────────────────────────────────────
if ($action === 'delete' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    if (!$is_mod) stderr('No permission');
    $db->sql_query_prepared("DELETE FROM offer_votes WHERE offer_id = ?", [$oid]);
    $db->sql_query_prepared("DELETE FROM offer_comments WHERE offer_id = ?", [$oid]);
    $db->sql_query_prepared("DELETE FROM offers WHERE id = ?", [$oid]);
    $_SESSION['flash'] = ['msg' => 'Offer deleted.', 'type' => 'success'];
    header('Location: offers.php');
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: Single Offer
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'view' && $oid) {
    $offer = $db->fetch_array($db->sql_query_prepared(
        "SELECT o.*, u.username, u.avatar, u.usergroup, u.displaygroup
         FROM offers o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?",
        [$oid]
    ));
    if (!$offer) stderr('Offer not found.');

    $my_want = $db->num_rows($db->sql_query_prepared(
        "SELECT id FROM offer_votes WHERE offer_id = ? AND user_id = ?",
        [$oid, (int)$CURUSER['id']]
    )) > 0;

    $comments_q = $db->sql_query_prepared(
        "SELECT oc.*, u.username, u.avatar
         FROM offer_comments oc
         LEFT JOIN users u ON u.id = oc.user_id
         WHERE oc.offer_id = ?
         ORDER BY oc.created_at ASC",
        [$oid]
    );

    $status_badge = match($offer['status']) {
        'open'      => '<span class="badge bg-success bg-opacity-25 text-success"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Open</span>',
        'uploaded'  => '<span class="badge bg-primary bg-opacity-25 text-primary"><i class="fas fa-upload me-1"></i>Uploaded</span>',
        'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary"><i class="fas fa-times me-1"></i>Cancelled</span>',
        default     => ''
    };

    stdhead('Offer: ' . htmlspecialchars($offer['title']));
    show_flash();
    ?>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            70% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.10s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.20s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.30s; }
        .animate-fade-in-up:nth-child(7) { animation-delay: 0.35s; }
        .animate-fade-in-up:nth-child(8) { animation-delay: 0.40s; }
        .animate-fade-in-up:nth-child(9) { animation-delay: 0.45s; }
        .animate-fade-in-up:nth-child(10) { animation-delay: 0.50s; }
        .pulse-on-hover:hover {
            animation: pulse 0.6s ease;
        }
        .loading-spinner {
            display: inline-block;
            width: 1.2rem;
            height: 1.2rem;
            border: 3px solid rgba(25,135,84,0.1);
            border-top-color: #198754;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .want-number-pop {
            animation: popIn 0.5s ease;
        }
        .comment-slide-in {
            animation: slideDown 0.4s ease;
        }
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }
        .offer-row {
            transition: all 0.2s ease;
        }
        .offer-row:hover {
            background: rgba(25,135,84,.04);
            transform: translateX(4px);
        }
        .badge { font-weight: 500; }
    </style>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4 animate-fade-in-up">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="offers.php"><i class="fas fa-gift me-1"></i>Offers</a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width:300px"><?= htmlspecialchars($offer['title']) ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- Main -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4 animate-fade-in-up">
                    <div class="card-header bg-transparent d-flex align-items-center justify-content-between gap-2 flex-wrap border-0 pb-0 pt-3">
                        <div class="d-flex align-items-center gap-3">
                            <?= $status_badge ?>
                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($offer['title']) ?></h5>
                        </div>
                        <?php if ($offer['category_id'] && isset($cats[$offer['category_id']])): ?>
                        <span class="badge bg-light text-dark border rounded-pill px-3">
                            <i class="fas fa-tag me-1"></i><?= htmlspecialchars($cats[$offer['category_id']]) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($offer['description']): ?>
                        <div class="mb-3 p-3 bg-light rounded-3"><?= $parser->parse_message($offer['description'], $parser_options) ?></div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-3 small text-muted">
                            <span><i class="fas fa-user me-1"></i><a href="<?= $BASEURL ?>/user-<?= $offer['user_id'] ?>.html" class="text-decoration-none"><?= htmlspecialchars($offer['username']) ?></a></span>
                            <span><i class="fas fa-calendar me-1"></i><?= my_datee($dateformat, $offer['created_at']) ?> <?= my_datee($timeformat, $offer['created_at']) ?></span>
                            <?php if ($offer['year']): ?>
                            <span><i class="fas fa-film me-1"></i><?= (int)$offer['year'] ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-users me-1"></i><?= (int)$offer['requests'] ?> want this</span>
                        </div>

                        <?php if ($offer['status'] === 'uploaded' && $offer['torrent_id']): ?>
                        <div class="alert alert-success mt-4 mb-0 rounded-3 animate-fade-in-up">
                            <i class="fas fa-check-circle me-2"></i>
                            This offer has been uploaded! <a href="<?= get_torrent_link($offer['torrent_id']) ?>" class="alert-link fw-semibold">Download here</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($offer['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0 d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!$my_want && $offer['user_id'] != $CURUSER['id']): ?>
                        <button class="btn btn-sm btn-outline-success rounded-pill px-4 pulse-on-hover" onclick="wantOffer(<?= $oid ?>)" id="wantBtn">
                            <i class="fas fa-hand-paper me-1"></i>I want this! (<span id="want-count"><?= (int)$offer['requests'] ?></span>)
                        </button>
                        <?php else: ?>
                        <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i>
                            <?= $offer['user_id'] == $CURUSER['id'] ? 'Your offer' : 'You requested this' ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($offer['user_id'] == $CURUSER['id'] || $is_mod): ?>
                        <button class="btn btn-sm btn-primary rounded-pill px-4 ms-auto pulse-on-hover" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="fas fa-upload me-1"></i>Uploaded
                        </button>
                        <form method="post" action="offers.php?action=cancel&oid=<?= $oid ?>" class="d-inline" onsubmit="return confirm('Cancel this offer?')">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 pulse-on-hover"><i class="fas fa-times me-1"></i>Cancel</button>
                        </form>
                        <?php if ($is_mod): ?>
                        <form method="post" action="offers.php?action=delete&oid=<?= $oid ?>" class="d-inline" onsubmit="return confirm('Delete this offer permanently?')">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 pulse-on-hover"><i class="fas fa-trash-alt me-1"></i>Delete</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comments -->
                <div class="card border-0 shadow-sm rounded-4 animate-fade-in-up">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="mb-0"><i class="fas fa-comments me-2"></i>Comments</h6>
                    </div>
                    <div class="card-body p-0" id="commentsContainer">
                        <?php
                        $comment_count = 0;
                        while ($c = $db->fetch_array($comments_q)):
                            $comment_count++;
                        ?>
                        <div class="d-flex gap-3 p-3 <?= $comment_count > 1 ? 'border-top' : '' ?> comment-slide-in" style="animation-delay: <?= $comment_count * 0.05 ?>s">
                            <div class="flex-shrink-0">
                                <?php if ($c['avatar']): ?>
                                <img src="<?= $BASEURL ?>/<?= htmlspecialchars($c['avatar']) ?>" class="rounded-circle" width="36" height="36" alt="">
                                <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width:36px;height:36px;font-size:.8rem">
                                    <?= strtoupper(substr($c['username'], 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <a href="<?= $BASEURL ?>/user-<?= $c['user_id'] ?>.html" class="fw-semibold small text-decoration-none"><?= htmlspecialchars($c['username']) ?></a>
                                    <small class="text-muted"><?= my_datee($dateformat, $c['created_at']) ?> <?= my_datee($timeformat, $c['created_at']) ?></small>
                                </div>
                                <p class="mb-0 small"><?= $parser->parse_message($c['message'], $parser_options) ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php if ($comment_count === 0): ?>
                        <div class="text-center py-4 text-muted small">No comments yet.</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($offer['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0">
                        <div class="d-flex gap-2">
                            <textarea class="form-control form-control-sm rounded-3" id="commentText" rows="2" placeholder="Add a comment..."></textarea>
                            <button class="btn btn-sm btn-primary rounded-pill px-4 pulse-on-hover" onclick="addComment(<?= $oid ?>)"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 text-center animate-fade-in-up">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-success" id="want-count-sidebar"><?= (int)$offer['requests'] ?></div>
                        <div class="text-muted small">people want this</div>
                        <hr>
                        <div class="small text-muted">
                            <div class="mb-2"><i class="fas fa-user me-2"></i>Offered by <a href="<?= $BASEURL ?>/user-<?= $offer['user_id'] ?>.html" class="text-decoration-none"><?= htmlspecialchars($offer['username']) ?></a></div>
                            <div class="mb-2"><i class="fas fa-clock me-2"></i><?= mkprettytime(TIMENOW - $offer['created_at']) ?> ago</div>
                            <?php if ($offer['year']): ?>
                            <div><i class="fas fa-film me-2"></i>Year: <?= (int)$offer['year'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Mark as Uploaded</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Enter the torrent ID that you uploaded for this offer.</p>
                    <form method="post" action="offers.php?action=upload&oid=<?= $oid ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Torrent ID</label>
                            <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="e.g. 12345">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 pulse-on-hover">Confirm Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    const postKey = '<?= $mybb->post_code ?>';
    const oid = <?= $oid ?>;

    function wantOffer(oid) {
        const btn = document.getElementById('wantBtn');
        if (!btn) {
            console.error('Want button not found');
            return;
        }
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner me-1"></span> Loading...';
        btn.classList.add('btn-loading');
        btn.disabled = true;
        
        fetch('offers.php?action=want&oid=' + oid, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({my_post_key: postKey})
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const countEl = document.getElementById('want-count');
                const sidebarEl = document.getElementById('want-count-sidebar');
                
                if (countEl) {
                    countEl.textContent = data.requests;
                    countEl.classList.remove('want-number-pop');
                    void countEl.offsetWidth;
                    countEl.classList.add('want-number-pop');
                }
                
                if (sidebarEl) {
                    sidebarEl.textContent = data.requests;
                    sidebarEl.classList.remove('want-number-pop');
                    void sidebarEl.offsetWidth;
                    sidebarEl.classList.add('want-number-pop');
                }
                
                setTimeout(() => location.reload(), 600);
            } else {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-loading');
                btn.disabled = false;
                alert(data.error || 'Error processing request');
            }
        })
        .catch(error => {
            console.error('Want error:', error);
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            
            if (error.message.includes('HTTP')) {
                alert('Server error (HTTP ' + error.message.replace('HTTP ', '') + '). Please try again.');
            } else {
                alert('Network error. Please check your connection and try again.');
            }
        });
    }

    function addComment(oid) {
        const msg = document.getElementById('commentText').value.trim();
        if (!msg) {
            alert('Please enter a comment.');
            return;
        }
        
        const btn = document.querySelector('.card-footer .btn-primary');
        if (btn) {
            btn.innerHTML = '<span class="loading-spinner"></span>';
            btn.disabled = true;
        }
        
        fetch('offers.php?action=add_comment&oid=' + oid, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({my_post_key: postKey, message: msg})
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    btn.disabled = false;
                }
                alert(data.error || 'Error adding comment');
            }
        })
        .catch(error => {
            console.error('Comment error:', error);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                btn.disabled = false;
            }
            if (error.message.includes('HTTP')) {
                alert('Server error (HTTP ' + error.message.replace('HTTP ', '') + '). Please try again.');
            } else {
                alert('Network error. Please check your connection and try again.');
            }
        });
    }

    // Enter для комментариев
    document.addEventListener('DOMContentLoaded', function() {
        const commentText = document.getElementById('commentText');
        if (commentText) {
            commentText.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    addComment(oid);
                }
            });
        }
    });
    </script>
    <?php
    stdfoot();
    exit();
}

// ── VIEW: Create Form ─────────────────────────────────────────────────────────
if ($action === 'create') {
    $editor = insert_bbcode_editor($smilies ?? [], $BASEURL, 'description');
    stdhead('New Offer');
    show_flash();
    ?>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        .pulse-on-hover:hover {
            animation: pulse 0.4s ease;
        }
    </style>
    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4 animate-fade-in-up">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="offers.php"><i class="fas fa-gift me-1"></i>Offers</a></li>
                <li class="breadcrumb-item active">New Offer</li>
            </ol>
        </nav>

        <div class="card border-0 shadow-sm rounded-4 animate-fade-in-up">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="fas fa-gift me-2 text-success"></i>Post a New Offer</h5>
                <small class="text-muted">I have this and can upload it if people want it</small>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>

                <form method="post" action="offers.php?action=do_create">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" name="title" maxlength="255" required
                               value="<?= htmlspecialchars($mybb->get_input('title')) ?>" placeholder="Movie / TV Show / Game title...">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Category</label>
                            <select class="form-select rounded-3" name="category_id">
                                <option value="0">— Any —</option>
                                <?php foreach ($cats as $cid => $cname): ?>
                                <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Year</label>
                            <input type="number" class="form-control rounded-3" name="year" min="1900" max="<?= date('Y') + 2 ?>" placeholder="2024">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <?= $editor['toolbar'] ?>
                        <textarea class="form-control rounded-3" name="description" id="description" rows="6" placeholder="Describe what you have — quality, format, source, size, etc."><?= htmlspecialchars($mybb->get_input('description')) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="offers.php" class="btn btn-outline-secondary rounded-pill px-4 pulse-on-hover">Cancel</a>
                        <button type="submit" class="btn btn-success rounded-pill px-4 pulse-on-hover"><i class="fas fa-gift me-1"></i>Post Offer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?= $editor['modal'] ?>
    <?php
    stdfoot();
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: List (default)
// ═══════════════════════════════════════════════════════════════════════════════
$filter_status = in_array($mybb->get_input('status'), ['open','uploaded','cancelled','']) ? $mybb->get_input('status') : 'open';
$filter_cat    = $mybb->get_input('cat', MyBB::INPUT_INT);
$sort          = in_array($mybb->get_input('sort'), ['requests','created_at']) ? $mybb->get_input('sort') : 'requests';
$perpage       = 25;
$page          = max(1, $mybb->get_input('page', MyBB::INPUT_INT));

$where = [];
$where_params = [];
if ($filter_status) { $where[] = "o.status = ?"; $where_params[] = $filter_status; }
if ($filter_cat)    { $where[] = "o.category_id = ?"; $where_params[] = $filter_cat; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->fetch_field(
    $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM offers o $where_sql", $where_params),
    'cnt'
);
$offset = ($page - 1) * $perpage;

$offers_q = $db->sql_query_prepared(
    "SELECT o.*, u.username
     FROM offers o
     LEFT JOIN users u ON u.id = o.user_id
     $where_sql
     ORDER BY o.$sort DESC
     LIMIT ?, ?",
    [...$where_params, $offset, $perpage]
);

stdhead('Offers');
show_flash();
?>
<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
    .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
    .animate-fade-in-up:nth-child(5) { animation-delay: 0.15s; }
    .animate-fade-in-up:nth-child(6) { animation-delay: 0.18s; }
    .animate-fade-in-up:nth-child(7) { animation-delay: 0.21s; }
    .animate-fade-in-up:nth-child(8) { animation-delay: 0.24s; }
    .animate-fade-in-up:nth-child(9) { animation-delay: 0.27s; }
    .animate-fade-in-up:nth-child(10) { animation-delay: 0.30s; }
    .animate-fade-in-up:nth-child(11) { animation-delay: 0.33s; }
    .animate-fade-in-up:nth-child(12) { animation-delay: 0.36s; }
    .animate-fade-in-up:nth-child(13) { animation-delay: 0.39s; }
    .animate-fade-in-up:nth-child(14) { animation-delay: 0.42s; }
    .animate-fade-in-up:nth-child(15) { animation-delay: 0.45s; }
    .animate-fade-in-up:nth-child(16) { animation-delay: 0.48s; }
    .animate-fade-in-up:nth-child(17) { animation-delay: 0.51s; }
    .animate-fade-in-up:nth-child(18) { animation-delay: 0.54s; }
    .animate-fade-in-up:nth-child(19) { animation-delay: 0.57s; }
    .animate-fade-in-up:nth-child(20) { animation-delay: 0.60s; }
    .pulse-on-hover:hover {
        animation: pulse 0.4s ease;
    }
    .offer-row {
        transition: all 0.2s ease;
    }
    .offer-row:hover {
        background: rgba(25,135,84,.04);
        transform: translateX(4px);
    }
    .badge { font-weight: 500; }
</style>
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 animate-fade-in-up">
        <div>
            <h4 class="mb-0"><i class="fas fa-gift me-2 text-success"></i>Offers</h4>
            <small class="text-muted"><?= $total ?> total offers</small>
        </div>
        <a href="offers.php?action=create" class="btn btn-success rounded-pill px-4 pulse-on-hover">
            <i class="fas fa-plus me-1"></i>Post an Offer
        </a>
    </div>

    <!-- Quick status filters -->
    <div class="d-flex flex-wrap gap-2 mb-4 animate-fade-in-up">
        <?php foreach (['open' => 'Open', 'uploaded' => 'Uploaded', 'cancelled' => 'Cancelled', '' => 'All'] as $val => $label): ?>
        <a href="?status=<?= $val ?>&cat=<?= $filter_cat ?>&sort=<?= $sort ?>" class="btn btn-sm <?= $filter_status === $val ? 'btn-success' : 'btn-outline-secondary' ?> rounded-pill px-4 pulse-on-hover">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <?php
        $count = 0;
        while ($offer = $db->fetch_array($offers_q)):
            $count++;
            $status_badge = match($offer['status']) {
                'open'      => '<span class="badge bg-success bg-opacity-25 text-success">Open</span>',
                'uploaded'  => '<span class="badge bg-primary bg-opacity-25 text-primary">Uploaded</span>',
                'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary">Cancelled</span>',
                default     => ''
            };
        ?>
        <div class="d-flex align-items-center gap-3 p-3 <?= $count > 1 ? 'border-top' : '' ?> offer-row animate-fade-in-up">
            <div class="text-center flex-shrink-0" style="min-width:48px">
                <div class="fw-bold text-success fs-5"><?= (int)$offer['requests'] ?></div>
                <div class="text-muted" style="font-size:.65rem">wants</div>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?= $status_badge ?>
                    <a href="offers.php?action=view&oid=<?= $offer['id'] ?>" class="fw-semibold text-truncate"><?= htmlspecialchars($offer['title']) ?></a>
                    <?php if ($offer['year']): ?>
                    <small class="text-muted">(<?= (int)$offer['year'] ?>)</small>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-3 mt-1 small text-muted flex-wrap">
                    <?php if (isset($cats[$offer['category_id']])): ?>
                    <span><i class="fas fa-tag me-1"></i><?= htmlspecialchars($cats[$offer['category_id']]) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($offer['username']) ?></span>
                    <span><i class="fas fa-clock me-1"></i><?= mkprettytime(TIMENOW - $offer['created_at']) ?> ago</span>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <div class="text-center py-5 animate-fade-in-up">
            <i class="fas fa-gift fa-3x text-muted mb-3"></i>
            <h6 class="text-muted">No offers found</h6>
            <a href="offers.php?action=create" class="btn btn-success btn-sm rounded-pill px-4 mt-2 pulse-on-hover">
                <i class="fas fa-plus me-1"></i>Post the first offer
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($total > $perpage): ?>
    <div class="mt-4 d-flex justify-content-center animate-fade-in-up">
        <?= multipage($total, $perpage, $page, 'offers.php?status=' . $filter_status . '&cat=' . $filter_cat . '&sort=' . $sort . '&page={page}') ?>
    </div>
    <?php endif; ?>
</div>
<?php
stdfoot();