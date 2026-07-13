<?php
declare(strict_types=1);

define('SCRIPTNAME', 'requests.php');

require_once 'global.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'cache/smilies.php';
require_once INC_PATH . '/editor.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/class_parser.php';

$parser = new postParser;
$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

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
        echo '<script>document.addEventListener("DOMContentLoaded", function(){ showToast(' . json_encode($f['msg']) . ', ' . json_encode($type) . '); });</script>';
        unset($_SESSION['flash']);
    }
}

if (empty($CURUSER['id'])) print_no_permission();

$is_mod = is_mod($usergroups);
$action = $mybb->get_input('action');
$rid    = $mybb->get_input('rid', MyBB::INPUT_INT);

// ── Load categories ──────────────────────────────────────────────────────────
$cats = [];
$q = $db->sql_query("SELECT id, name FROM categories ORDER BY name");
while ($r = $db->fetch_array($q)) $cats[$r['id']] = $r['name'];

// ── AJAX: Vote ────────────────────────────────────────────────────────────────
if ($action === 'vote' && $mybb->request_method === 'post') {
    header('Content-Type: application/json');
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid post key']);
        exit();
    }
    $uid = (int)$CURUSER['id'];
    $bounty = max(0, (float)($mybb->input['bounty'] ?? 0));
    $req = $db->fetch_array($db->simple_select('requests', 'id, status, user_id', "id='$rid'"));
    if (!$req) { echo json_encode(['success' => false, 'error' => 'Not found']); exit(); }
    if ($req['status'] !== 'open') { echo json_encode(['success' => false, 'error' => 'Request is closed']); exit(); }
    if ($req['user_id'] == $uid) { echo json_encode(['success' => false, 'error' => 'Cannot vote on your own request']); exit(); }
    if ($db->fetch_array($db->simple_select('request_votes', 'id', "request_id='$rid' AND user_id='$uid'"))) {
        echo json_encode(['success' => false, 'error' => 'Already voted']);
        exit();
    }
    if ($bounty > 0) {
        if ((float)$CURUSER['seedbonus'] < $bounty) {
            echo json_encode(['success' => false, 'error' => 'Not enough bonus points']);
            exit();
        }
        $db->sql_query("UPDATE users SET seedbonus = seedbonus - '$bounty' WHERE id = '$uid'");
        $db->sql_query("UPDATE requests SET bounty = bounty + '$bounty' WHERE id = '$rid'");
    }
    $db->insert_query('request_votes', [
        'request_id' => $rid,
        'user_id'    => $uid,
        'bounty'     => $bounty,
        'created_at' => TIMENOW,
    ]);
    $db->sql_query("UPDATE requests SET votes = votes + 1, updated_at = '" . TIMENOW . "' WHERE id = '$rid'");
    $new_votes = (int)$db->fetch_field($db->simple_select('requests', 'votes', "id='$rid'"), 'votes');
    echo json_encode(['success' => true, 'votes' => $new_votes]);
    exit();
}

// ── AJAX: Add Comment ─────────────────────────────────────────────────────────
if ($action === 'add_comment' && $mybb->request_method === 'post') {
    header('Content-Type: application/json');
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid post key']);
        exit();
    }
    $message = trim($mybb->get_input('message'));
    if (empty($message)) { echo json_encode(['success' => false, 'error' => 'Empty message']); exit(); }
    if (!$db->fetch_array($db->simple_select('requests', 'id', "id='$rid'"))) {
        echo json_encode(['success' => false, 'error' => 'Not found']);
        exit();
    }
    $db->insert_query('request_comments', [
        'request_id' => $rid,
        'user_id'    => (int)$CURUSER['id'],
        'message'    => $db->escape_string($message),
        'created_at' => TIMENOW,
    ]);
    echo json_encode(['success' => true]);
    exit();
}

// ── POST: Create Request ──────────────────────────────────────────────────────
if ($action === 'do_create' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    $title = trim($mybb->get_input('title'));
    $description = trim($mybb->get_input('description'));
    $category_id = $mybb->get_input('category_id', MyBB::INPUT_INT);
    $year = ($year_raw = trim($mybb->get_input('year'))) !== '' && (int)$year_raw > 0 ? (int)$year_raw : null;
    $bounty = max(0, (float)($mybb->input['bounty'] ?? 0));
    $errors = [];
    if (strlen($title) < 3) $errors[] = 'Title must be at least 3 characters.';
    if (strlen($title) > 255) $errors[] = 'Title is too long.';
    if ($bounty > 0 && (float)$CURUSER['seedbonus'] < $bounty) $errors[] = 'Not enough bonus points.';
    if (empty($errors)) {
        if ($bounty > 0) $db->sql_query("UPDATE users SET seedbonus = seedbonus - '$bounty' WHERE id = '" . (int)$CURUSER['id'] . "'");
        $year_val = $year !== null && $year > 0 ? (int)$year : 'NULL';
        $db->sql_query("INSERT INTO requests (user_id, title, description, category_id, year, status, votes, bounty, created_at, updated_at)
            VALUES ('" . (int)$CURUSER['id'] . "', '" . $db->escape_string($title) . "', '" . $db->escape_string($description) . "', '$category_id', $year_val, 'open', 1, '$bounty', '" . TIMENOW . "', '" . TIMENOW . "')");
        $id = $db->insert_id();
        $db->insert_query('request_votes', ['request_id' => $id, 'user_id' => (int)$CURUSER['id'], 'bounty' => $bounty, 'created_at' => TIMENOW]);
        $_SESSION['flash'] = ['msg' => 'Request created successfully!', 'type' => 'success'];
        header('Location: requests.php?action=view&rid=' . $id);
        exit();
    }
}

// ── POST: Fill / Cancel / Delete ─────────────────────────────────────────────
if ($action === 'fill' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    $torrent_id = $mybb->get_input('torrent_id', MyBB::INPUT_INT);
    $req = $db->fetch_array($db->simple_select('requests', '*', "id='$rid'"));
    if (!$req || $req['status'] !== 'open') stderr('Invalid request');
    if (!$torrent_id || !$db->num_rows($db->simple_select('torrents', 'id', "id='$torrent_id'"))) {
        $_SESSION['flash'] = ['msg' => 'Torrent ID ' . $torrent_id . ' does not exist.', 'type' => 'danger'];
        header('Location: requests.php?action=view&rid=' . $rid);
        exit();
    }
    if ((float)$req['bounty'] > 0) {
        $db->sql_query("UPDATE users SET seedbonus = seedbonus + '{$req['bounty']}' WHERE id = '" . (int)$CURUSER['id'] . "'");
    }
    $db->update_query('requests', ['status' => 'filled', 'filled_by' => (int)$CURUSER['id'], 'torrent_id' => $torrent_id, 'filled_at' => TIMENOW, 'updated_at' => TIMENOW], "id='$rid'");
    if ($req['user_id'] != $CURUSER['id']) {
        require_once INC_PATH . '/datahandlers/pm.php';
        $pmhandler = new PMDataHandler();
        $requester = $db->fetch_array($db->simple_select('users', 'username', "id='{$req['user_id']}'"));
        if ($requester) {
            $pm = [
                'subject' => 'Your request has been filled!',
                'message' => 'Your request [b]' . $req['title'] . '[/b] has been filled. [url=' . $BASEURL . '/details.php?id=' . $torrent_id . ']Click here to download[/url]',
                'icon' => 0,
                'fromid' => (int)$CURUSER['id'],
                'do' => '',
                'pmid' => 0,
                'ipaddress' => $session->packedip,
                'to' => [$requester['username']],
                'options' => ['savecopy' => 0, 'readreceipt' => 0],
            ];
            $pmhandler->set_data($pm);
            if ($pmhandler->validate_pm()) $pmhandler->insert_pm();
        }
    }
    $_SESSION['flash'] = ['msg' => 'Request marked as filled!', 'type' => 'success'];
    header('Location: requests.php?action=view&rid=' . $rid);
    exit();
}

if ($action === 'cancel' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    $req = $db->fetch_array($db->simple_select('requests', 'user_id, status', "id='$rid'"));
    if (!$req || ($req['user_id'] != $CURUSER['id'] && !$is_mod)) stderr('No permission');
    $db->update_query('requests', ['status' => 'cancelled', 'updated_at' => TIMENOW], "id='$rid'");
    $_SESSION['flash'] = ['msg' => 'Request cancelled.', 'type' => 'info'];
    header('Location: requests.php');
    exit();
}

if ($action === 'delete' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr('Invalid post key');
    if (!$is_mod) stderr('No permission');
    $req = $db->fetch_array($db->simple_select('requests', 'bounty, status', "id='$rid'"));
    if (!$req) stderr('Not found');
    if ($req['status'] === 'open' && (float)$req['bounty'] > 0) {
        $votes_q = $db->simple_select('request_votes', 'user_id, bounty', "request_id='$rid'");
        while ($v = $db->fetch_array($votes_q)) {
            if ((float)$v['bounty'] > 0) $db->sql_query("UPDATE users SET seedbonus = seedbonus + '{$v['bounty']}' WHERE id='{$v['user_id']}'");
        }
    }
    $db->delete_query('request_votes', "request_id='$rid'");
    $db->delete_query('request_comments', "request_id='$rid'");
    $db->delete_query('requests', "id='$rid'");
    $_SESSION['flash'] = ['msg' => 'Request deleted.', 'type' => 'success'];
    header('Location: requests.php');
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: Single request
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'view' && $rid) {
    $req = $db->fetch_array($db->sql_query("
        SELECT r.*, u.username, u.avatar, u.usergroup, u.displaygroup
        FROM requests r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.id = '$rid'
    "));
    if (!$req) stderr('Request not found.');

    $my_vote = $db->num_rows($db->simple_select('request_votes', 'id', "request_id='$rid' AND user_id='" . (int)$CURUSER['id'] . "'")) > 0;

    $comments_q = $db->sql_query("
        SELECT rc.*, u.username, u.avatar, u.usergroup, u.displaygroup
        FROM request_comments rc
        LEFT JOIN users u ON u.id = rc.user_id
        WHERE rc.request_id = '$rid'
        ORDER BY rc.created_at ASC
    ");

    $status_badge = match($req['status']) {
        'open'      => '<span class="badge bg-success bg-opacity-25 text-success"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Open</span>',
        'filled'    => '<span class="badge bg-primary bg-opacity-25 text-primary"><i class="fas fa-check me-1"></i>Filled</span>',
        'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary"><i class="fas fa-times me-1"></i>Cancelled</span>',
        default     => ''
    };

    stdhead('Request: ' . htmlspecialchars($req['title']));
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
            border: 3px solid rgba(13,110,253,0.1);
            border-top-color: #0d6efd;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .vote-number-pop {
            animation: popIn 0.5s ease;
        }
        .comment-slide-in {
            animation: slideDown 0.4s ease;
        }
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }
        .req-row {
            transition: all 0.2s ease;
        }
        .req-row:hover {
            background: rgba(13,110,253,.04);
            transform: translateX(4px);
        }
        .badge { font-weight: 500; }
    </style>

    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4 animate-fade-in-up">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="requests.php"><i class="fas fa-list-alt me-1"></i>Requests</a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width:300px"><?= htmlspecialchars($req['title']) ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4 animate-fade-in-up">
                    <div class="card-header bg-transparent d-flex align-items-center justify-content-between gap-2 flex-wrap border-0 pb-0 pt-3">
                        <div class="d-flex align-items-center gap-3">
                            <?= $status_badge ?>
                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($req['title']) ?></h5>
                        </div>
                        <?php if ($req['category_id'] && isset($cats[$req['category_id']])): ?>
                        <span class="badge bg-light text-dark border rounded-pill px-3">
                            <i class="fas fa-tag me-1"></i><?= htmlspecialchars($cats[$req['category_id']]) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($req['description']): ?>
                        <div class="mb-3 p-3 bg-light rounded-3"><?= $parser->parse_message($req['description'], $parser_options) ?></div>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-3 small text-muted">
                            <span><i class="fas fa-user me-1"></i><a href="<?= $BASEURL ?>/user-<?= $req['user_id'] ?>.html" class="text-decoration-none"><?= htmlspecialchars($req['username']) ?></a></span>
                            <span><i class="fas fa-calendar me-1"></i><?= my_datee($dateformat, $req['created_at']) ?> <?= my_datee($timeformat, $req['created_at']) ?></span>
                            <?php if ($req['year']): ?>
                            <span><i class="fas fa-film me-1"></i><?= (int)$req['year'] ?></span>
                            <?php endif; ?>
                            <?php if ((float)$req['bounty'] > 0): ?>
                            <span class="text-warning fw-semibold"><i class="fas fa-coins me-1"></i><?= number_format((float)$req['bounty'], 1) ?> BP bounty</span>
                            <?php endif; ?>
                            <span><i class="fas fa-users me-1"></i><?= (int)$req['votes'] ?> voters</span>
                        </div>
                        <?php if ($req['status'] === 'filled' && $req['torrent_id']): ?>
                        <div class="alert alert-success mt-4 mb-0 rounded-3 animate-fade-in-up">
                            <i class="fas fa-check-circle me-2"></i>
                            This request was filled! <a href="<?= get_torrent_link($req['torrent_id']) ?>" class="alert-link fw-semibold">Download here</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($req['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0 d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!$my_vote && $req['user_id'] != $CURUSER['id']): ?>
                        <button class="btn btn-sm btn-outline-success rounded-pill px-4 pulse-on-hover" onclick="voteRequest(<?= $rid ?>)" id="voteBtn">
                            <i class="fas fa-thumbs-up me-1"></i>Vote (<span id="vote-count"><?= (int)$req['votes'] ?></span>)
                        </button>
                        <div class="input-group input-group-sm" style="width:200px">
                            <input type="number" class="form-control" id="bountyInput" placeholder="Add BP" min="0" step="0.1">
                            <span class="input-group-text text-muted small">Balance: <?= number_format((float)$CURUSER['seedbonus'], 1) ?></span>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i>You voted</span>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary rounded-pill px-4 ms-auto pulse-on-hover" data-bs-toggle="modal" data-bs-target="#fillModal">
                            <i class="fas fa-upload me-1"></i>Fill
                        </button>
                        <?php if ($req['user_id'] == $CURUSER['id'] || $is_mod): ?>
                        <form method="post" action="requests.php?action=cancel&rid=<?= $rid ?>" class="d-inline" onsubmit="return confirm('Cancel this request?')">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 pulse-on-hover"><i class="fas fa-times me-1"></i>Cancel</button>
                        </form>
                        <?php if ($is_mod): ?>
                        <form method="post" action="requests.php?action=delete&rid=<?= $rid ?>" class="d-inline" onsubmit="return confirm('Delete permanently? Bounty will be refunded.')">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 pulse-on-hover"><i class="fas fa-trash-alt me-1"></i>Delete</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

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
                    <?php if ($req['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0">
                        <div class="d-flex gap-2">
                            <textarea class="form-control form-control-sm rounded-3" id="commentText" rows="2" placeholder="Add a comment..."></textarea>
                            <button class="btn btn-sm btn-primary rounded-pill px-4 pulse-on-hover" onclick="addComment(<?= $rid ?>)"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 text-center animate-fade-in-up">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-primary" id="vote-count-sidebar"><?= (int)$req['votes'] ?></div>
                        <div class="text-muted small">voters</div>
                        <?php if ((float)$req['bounty'] > 0): ?>
                        <hr>
                        <div class="text-warning fw-semibold"><i class="fas fa-coins me-1"></i><?= number_format((float)$req['bounty'], 1) ?> BP</div>
                        <div class="text-muted small">total bounty</div>
                        <?php endif; ?>
                        <hr>
                        <div class="small text-muted">Created <?= mkprettytime(TIMENOW - $req['created_at']) ?> ago</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fillModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Fill Request</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Enter the torrent ID that fulfills this request.</p>
                    <form method="post" action="requests.php?action=fill&rid=<?= $rid ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Torrent ID</label>
                            <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="e.g. 12345">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 pulse-on-hover">Mark as Filled</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
const postKey = '<?= $mybb->post_code ?>';

function voteRequest(rid) {
    const btn = document.getElementById('voteBtn');
    if (!btn) {
        console.error('Vote button not found');
        return;
    }
    
    const bountyInput = document.getElementById('bountyInput');
    const bounty = bountyInput ? parseFloat(bountyInput.value) || 0 : 0;
    
    // Сохраняем оригинальный HTML
    const originalHtml = btn.innerHTML;
    
    // Показываем спиннер
    btn.innerHTML = '<span class="loading-spinner me-1"></span> Voting...';
    btn.classList.add('btn-loading');
    btn.disabled = true;
    
    fetch('requests.php?action=vote&rid=' + rid, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            my_post_key: postKey, 
            bounty: bounty
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Обновляем счетчики с анимацией
            const countEl = document.getElementById('vote-count');
            const sidebarEl = document.getElementById('vote-count-sidebar');
            
            if (countEl) {
                countEl.textContent = data.votes;
                countEl.classList.remove('vote-number-pop');
                // Trigger reflow для перезапуска анимации
                void countEl.offsetWidth;
                countEl.classList.add('vote-number-pop');
            }
            
            if (sidebarEl) {
                sidebarEl.textContent = data.votes;
                sidebarEl.classList.remove('vote-number-pop');
                void sidebarEl.offsetWidth;
                sidebarEl.classList.add('vote-number-pop');
            }
            
            // Показываем успех через flash и перезагружаем
            setTimeout(() => location.reload(), 600);
        } else {
            // Восстанавливаем кнопку
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            alert(data.error || 'Error processing vote');
        }
    })
    .catch(error => {
        console.error('Vote error:', error);
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn-loading');
        btn.disabled = false;
        
        // Показываем понятное сообщение об ошибке
        if (error.message.includes('HTTP')) {
            alert('Server error (HTTP ' + error.message.replace('HTTP ', '') + '). Please try again.');
        } else {
            alert('Network error. Please check your connection and try again.');
        }
    });
}

function addComment(rid) {
    const msg = document.getElementById('commentText').value.trim();
    if (!msg) {
        alert('Please enter a comment.');
        return;
    }
    
    const btn = document.querySelector('#commentsContainer + .card-footer .btn-primary');
    if (!btn) {
        // Fallback - ищем кнопку другим способом
        const btns = document.querySelectorAll('.card-footer .btn-primary');
        if (btns.length > 0) {
            const btnFallback = btns[btns.length - 1];
            btnFallback.innerHTML = '<span class="loading-spinner"></span>';
            btnFallback.disabled = true;
        }
    } else {
        btn.innerHTML = '<span class="loading-spinner"></span>';
        btn.disabled = true;
    }
    
    fetch('requests.php?action=add_comment&rid=' + rid, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            my_post_key: postKey, 
            message: msg
        })
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

// Дополнительно: обработка Enter в поле комментария
document.addEventListener('DOMContentLoaded', function() {
    const commentText = document.getElementById('commentText');
    if (commentText) {
        commentText.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const rid = <?= $rid ?>;
                addComment(rid);
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
    stdhead('New Request');
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
                <li class="breadcrumb-item"><a href="requests.php"><i class="fas fa-list-alt me-1"></i>Requests</a></li>
                <li class="breadcrumb-item active">New Request</li>
            </ol>
        </nav>
        <div class="card border-0 shadow-sm rounded-4 animate-fade-in-up">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i>Create a New Request</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>
                <form method="post" action="requests.php?action=do_create">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" name="title" maxlength="255" required value="<?= htmlspecialchars($mybb->get_input('title')) ?>" placeholder="Movie / TV Show / Game title...">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select class="form-select rounded-3" name="category_id">
                                <option value="0">— Any —</option>
                                <?php foreach ($cats as $cid => $cname): ?>
                                <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Year</label>
                            <input type="number" class="form-control rounded-3" name="year" min="1900" max="<?= date('Y') + 2 ?>" placeholder="2024">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Bounty (BP)</label>
                            <input type="number" class="form-control rounded-3" name="bounty" min="0" step="0.1" value="0">
                            <div class="form-text">Your balance: <?= number_format((float)$CURUSER['seedbonus'], 1) ?> BP</div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description</label>
                        <?= $editor['toolbar'] ?>
                        <textarea class="form-control rounded-3" name="description" id="description" rows="6" placeholder="Add details — quality, language, source, etc."><?= htmlspecialchars($mybb->get_input('description')) ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="requests.php" class="btn btn-outline-secondary rounded-pill px-4 pulse-on-hover">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 pulse-on-hover"><i class="fas fa-paper-plane me-1"></i>Submit</button>
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
$filter_status = in_array($mybb->get_input('status'), ['open','filled','cancelled','']) ? $mybb->get_input('status') : 'open';
$filter_cat    = $mybb->get_input('cat', MyBB::INPUT_INT);
$sort          = in_array($mybb->get_input('sort'), ['votes','bounty','created_at']) ? $mybb->get_input('sort') : 'votes';
$perpage       = 25;
$page          = max(1, $mybb->get_input('page', MyBB::INPUT_INT));

$where = [];
if ($filter_status) $where[] = "r.status = '$filter_status'";
if ($filter_cat)    $where[] = "r.category_id = '$filter_cat'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_q = $db->fetch_field($db->sql_query("SELECT COUNT(*) AS cnt FROM requests r $where_sql"), 'cnt');
$total   = (int)$total_q;
$offset  = ($page - 1) * $perpage;

$requests_q = $db->sql_query("
    SELECT r.*, u.username, u.avatar
    FROM requests r
    LEFT JOIN users u ON u.id = r.user_id
    $where_sql
    ORDER BY r.$sort DESC
    LIMIT $offset, $perpage
");

stdhead('Requests');
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
    .req-row {
        transition: all 0.2s ease;
    }
    .req-row:hover {
        background: rgba(13,110,253,.04);
        transform: translateX(4px);
    }
    .badge { font-weight: 500; }
</style>
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 animate-fade-in-up">
        <div>
            <h4 class="mb-0"><i class="fas fa-list-alt me-2 text-primary"></i>Requests</h4>
            <small class="text-muted"><?= $total ?> total requests</small>
        </div>
        <a href="requests.php?action=create" class="btn btn-primary rounded-pill px-4 pulse-on-hover">
            <i class="fas fa-plus me-1"></i>New Request
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4 animate-fade-in-up">
        <?php foreach (['open' => 'Open', 'filled' => 'Filled', 'cancelled' => 'Cancelled', '' => 'All'] as $val => $label): ?>
        <a href="?status=<?= $val ?>&cat=<?= $filter_cat ?>&sort=<?= $sort ?>" class="btn btn-sm <?= $filter_status === $val ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-4 pulse-on-hover">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <?php
        $count = 0;
        while ($req = $db->fetch_array($requests_q)):
            $count++;
            $status_badge = match($req['status']) {
                'open'      => '<span class="badge bg-success bg-opacity-25 text-success">Open</span>',
                'filled'    => '<span class="badge bg-primary bg-opacity-25 text-primary">Filled</span>',
                'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary">Cancelled</span>',
                default     => ''
            };
        ?>
        <div class="d-flex align-items-center gap-3 p-3 <?= $count > 1 ? 'border-top' : '' ?> req-row animate-fade-in-up">
            <div class="text-center flex-shrink-0" style="min-width:48px">
                <div class="fw-bold text-primary fs-5"><?= (int)$req['votes'] ?></div>
                <div class="text-muted" style="font-size:.65rem">votes</div>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?= $status_badge ?>
                    <a href="requests.php?action=view&rid=<?= $req['id'] ?>" class="fw-semibold text-truncate"><?= htmlspecialchars($req['title']) ?></a>
                    <?php if ($req['year']): ?>
                    <small class="text-muted">(<?= (int)$req['year'] ?>)</small>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-3 mt-1 small text-muted flex-wrap">
                    <?php if (isset($cats[$req['category_id']])): ?>
                    <span><i class="fas fa-tag me-1"></i><?= htmlspecialchars($cats[$req['category_id']]) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($req['username']) ?></span>
                    <span><i class="fas fa-clock me-1"></i><?= mkprettytime(TIMENOW - $req['created_at']) ?> ago</span>
                    <?php if ((float)$req['bounty'] > 0): ?>
                    <span class="text-warning"><i class="fas fa-coins me-1"></i><?= number_format((float)$req['bounty'], 1) ?> BP</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($count === 0): ?>
        <div class="text-center py-5 animate-fade-in-up">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h6 class="text-muted">No requests found</h6>
            <a href="requests.php?action=create" class="btn btn-primary btn-sm rounded-pill px-4 mt-2 pulse-on-hover">
                <i class="fas fa-plus me-1"></i>Create the first request
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($total > $perpage): ?>
    <div class="mt-4 d-flex justify-content-center animate-fade-in-up">
        <?= multipage($total, $perpage, $page, 'requests.php?status=' . $filter_status . '&cat=' . $filter_cat . '&sort=' . $sort . '&page={page}') ?>
    </div>
    <?php endif; ?>
</div>
<?php
stdfoot();