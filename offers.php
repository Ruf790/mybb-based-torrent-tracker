<?php
declare(strict_types=1);

define("IN_MYBB", 1);
define('SCRIPTNAME', 'offers.php');

require_once 'global.php';
$lang->load('offers');
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

// ── String formatter: {1}, {2}… (and %1$s after $lang->load conversion) ──────
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']    = (string)$arg;
            $map['%' . $n . '$s']   = (string)$arg;
        }
        return strtr($str, $map);
    }
}

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
        echo json_encode(['success' => false, 'error' => $lang->offers['err_post_key']]); exit();
    }
    $uid = (int)$CURUSER['id'];
    $offer = $db->fetch_array($db->sql_query_prepared("SELECT id, status, user_id FROM offers WHERE id = ?", [$oid]));
    if (!$offer) { echo json_encode(['success' => false, 'error' => $lang->offers['err_not_found']]); exit(); }
    if ($offer['status'] !== 'open') { echo json_encode(['success' => false, 'error' => $lang->offers['err_closed']]); exit(); }
    if ($offer['user_id'] == $uid) { echo json_encode(['success' => false, 'error' => $lang->offers['err_own_offer']]); exit(); }
    if ($db->num_rows($db->sql_query_prepared("SELECT id FROM offer_votes WHERE offer_id = ? AND user_id = ?", [$oid, $uid]))) {
        echo json_encode(['success' => false, 'error' => $lang->offers['err_already_requested']]); exit();
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
        echo json_encode(['success' => false, 'error' => $lang->offers['err_post_key']]); exit();
    }
    $message = trim($mybb->get_input('message'));
    if (empty($message)) { echo json_encode(['success' => false, 'error' => $lang->offers['err_empty_message']]); exit(); }
    if (!$db->num_rows($db->sql_query_prepared("SELECT id FROM offers WHERE id = ?", [$oid]))) {
        echo json_encode(['success' => false, 'error' => $lang->offers['err_not_found']]); exit();
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
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->offers['err_post_key']);
    $title = trim($mybb->get_input('title'));
    $description = trim($mybb->get_input('description'));
    $category_id = $mybb->get_input('category_id', MyBB::INPUT_INT);
    $year = ($year_raw = trim($mybb->get_input('year'))) !== '' && (int)$year_raw > 0 ? (int)$year_raw : null;
    $errors = [];
    if (strlen($title) < 3) $errors[] = $lang->offers['err_title_short'];
    if (strlen($title) > 255) $errors[] = $lang->offers['err_title_long'];
    if (empty($errors)) {
        $db->sql_query_prepared(
            "INSERT INTO offers (user_id, title, description, category_id, year, status, requests, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'open', 0, ?, ?)",
            [(int)$CURUSER['id'], $title, $description, $category_id, $year, TIMENOW, TIMENOW]
        );
        $id = $db->insert_id();
        $_SESSION['flash'] = ['msg' => $lang->offers['flash_created'], 'type' => 'success'];
        header('Location: offers.php?action=view&oid=' . $id);
        exit();
    }
}

// ── POST: Mark as Uploaded ────────────────────────────────────────────────────
if ($action === 'upload' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->offers['err_post_key']);
    $offer = $db->fetch_array($db->sql_query_prepared("SELECT * FROM offers WHERE id = ?", [$oid]));
    if (!$offer || ($offer['user_id'] != $CURUSER['id'] && !$is_mod)) stderr($lang->offers['err_no_permission']);
    if ($offer['status'] !== 'open') stderr($lang->offers['err_closed']);
    $torrent_id = $mybb->get_input('torrent_id', MyBB::INPUT_INT);
    if (!$torrent_id || !$db->num_rows($db->sql_query_prepared("SELECT id FROM torrents WHERE id = ?", [$torrent_id]))) {
        $_SESSION['flash'] = ['msg' => ags_fmt($lang->offers['flash_torrent_missing'], $torrent_id), 'type' => 'danger'];
        header('Location: offers.php?action=view&oid=' . $oid);
        exit();
    }
    $db->sql_query_prepared(
        "UPDATE offers SET status = ?, torrent_id = ?, uploaded_at = ?, updated_at = ? WHERE id = ?",
        ['uploaded', $torrent_id, TIMENOW, TIMENOW, $oid]
    );
    require_once INC_PATH . '/functions_pm.php';

    // Скобки в названии ломают [b]...[/b] / [url=...]...[/url]
    $offer_title = str_replace(['[', ']'], ['(', ')'], (string)$offer['title']);
    $torrent_url = $BASEURL . '/details.php?id=' . $torrent_id;

    $votes_q = $db->sql_query_prepared(
        "SELECT ov.user_id, u.language
         FROM offer_votes ov
         JOIN users u ON u.id = ov.user_id
         WHERE ov.offer_id = ?",
        [$oid]
    );
    while ($v = $db->fetch_array($votes_q)) {
        if ($v['user_id'] == $CURUSER['id']) continue;
        // Текст ЛС берётся из offers.lang.php на языке получателя
        send_pm([
            'subject'       => ['pm_uploaded_subject', $offer_title],
            'message'       => ['pm_uploaded_message', $offer_title, $torrent_url],
            'touid'         => (int)$v['user_id'],
            'language'      => (string)($v['language'] ?? ''),
            'language_file' => 'offers',
            'sender'        => ['uid' => -1],
        ], -1, true);
    }
    $_SESSION['flash'] = ['msg' => $lang->offers['flash_uploaded'], 'type' => 'success'];
    header('Location: offers.php?action=view&oid=' . $oid);
    exit();
}

// ── POST: Cancel ──────────────────────────────────────────────────────────────
if ($action === 'cancel' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->offers['err_post_key']);
    $offer = $db->fetch_array($db->sql_query_prepared("SELECT user_id, status FROM offers WHERE id = ?", [$oid]));
    if (!$offer || ($offer['user_id'] != $CURUSER['id'] && !$is_mod)) stderr($lang->offers['err_no_permission']);
    $db->sql_query_prepared("UPDATE offers SET status = ?, updated_at = ? WHERE id = ?", ['cancelled', TIMENOW, $oid]);
    $_SESSION['flash'] = ['msg' => $lang->offers['flash_cancelled'], 'type' => 'info'];
    header('Location: offers.php');
    exit();
}

// ── POST: Delete ──────────────────────────────────────────────────────────────
if ($action === 'delete' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->offers['err_post_key']);
    if (!$is_mod) stderr($lang->offers['err_no_permission']);
    $db->sql_query_prepared("DELETE FROM offer_votes WHERE offer_id = ?", [$oid]);
    $db->sql_query_prepared("DELETE FROM offer_comments WHERE offer_id = ?", [$oid]);
    $db->sql_query_prepared("DELETE FROM offers WHERE id = ?", [$oid]);
    $_SESSION['flash'] = ['msg' => $lang->offers['flash_deleted'], 'type' => 'success'];
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
    if (!$offer) stderr($lang->offers['err_not_found']);

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
        'open'      => '<span class="badge bg-success bg-opacity-25 text-success"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>' . htmlspecialchars($lang->offers['status_open']) . '</span>',
        'uploaded'  => '<span class="badge bg-primary bg-opacity-25 text-primary"><i class="fas fa-upload me-1"></i>' . htmlspecialchars($lang->offers['status_uploaded']) . '</span>',
        'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary"><i class="fas fa-times me-1"></i>' . htmlspecialchars($lang->offers['status_cancelled']) . '</span>',
        default     => ''
    };

    stdhead(htmlspecialchars(ags_fmt($lang->offers['title_view'], $offer['title'])));
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
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php" title="<?= htmlspecialchars($lang->offers['nav_home']) ?>" aria-label="<?= htmlspecialchars($lang->offers['nav_home']) ?>"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="offers.php"><i class="fas fa-gift me-1"></i><?= htmlspecialchars($lang->offers['nav_offers']) ?></a></li>
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
                            <span><i class="fas fa-users me-1"></i><?= htmlspecialchars(ags_fmt($lang->offers['lbl_want_count'], (int)$offer['requests'])) ?></span>
                        </div>

                        <?php if ($offer['status'] === 'uploaded' && $offer['torrent_id']): ?>
                        <div class="alert alert-success mt-4 mb-0 rounded-3 animate-fade-in-up">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($lang->offers['txt_uploaded']) ?> <a href="<?= get_torrent_link($offer['torrent_id']) ?>" class="alert-link fw-semibold"><?= htmlspecialchars($lang->offers['lnk_download']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($offer['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0 d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!$my_want && $offer['user_id'] != $CURUSER['id']): ?>
                        <button class="btn btn-sm btn-outline-success rounded-pill px-4 pulse-on-hover" onclick="wantOffer(<?= $oid ?>)" id="wantBtn">
                            <i class="fas fa-hand-paper me-1"></i><?= htmlspecialchars($lang->offers['btn_want']) ?> (<span id="want-count"><?= (int)$offer['requests'] ?></span>)
                        </button>
                        <?php else: ?>
                        <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i>
                            <?= htmlspecialchars($offer['user_id'] == $CURUSER['id'] ? $lang->offers['txt_your_offer'] : $lang->offers['txt_you_requested']) ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($offer['user_id'] == $CURUSER['id'] || $is_mod): ?>
                        <button class="btn btn-sm btn-primary rounded-pill px-4 ms-auto pulse-on-hover" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="fas fa-upload me-1"></i><?= htmlspecialchars($lang->offers['btn_uploaded']) ?>
                        </button>
                        <form method="post" action="offers.php?action=cancel&oid=<?= $oid ?>" class="d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode($lang->offers['conf_cancel'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 pulse-on-hover"><i class="fas fa-times me-1"></i><?= htmlspecialchars($lang->offers['btn_cancel_offer']) ?></button>
                        </form>
                        <?php if ($is_mod): ?>
                        <form method="post" action="offers.php?action=delete&oid=<?= $oid ?>" class="d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode($lang->offers['conf_delete'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 pulse-on-hover"><i class="fas fa-trash-alt me-1"></i><?= htmlspecialchars($lang->offers['btn_delete']) ?></button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comments -->
                <div class="card border-0 shadow-sm rounded-4 animate-fade-in-up">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="mb-0"><i class="fas fa-comments me-2"></i><?= htmlspecialchars($lang->offers['sec_comments']) ?></h6>
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
                        <div class="text-center py-4 text-muted small"><?= htmlspecialchars($lang->offers['txt_no_comments']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($offer['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0">
                        <div class="d-flex gap-2">
                            <textarea class="form-control form-control-sm rounded-3" id="commentText" rows="2" placeholder="<?= htmlspecialchars($lang->offers['ph_comment']) ?>"></textarea>
                            <button class="btn btn-sm btn-primary rounded-pill px-4 pulse-on-hover" onclick="addComment(<?= $oid ?>)" title="<?= htmlspecialchars($lang->offers['aria_send_comment']) ?>" aria-label="<?= htmlspecialchars($lang->offers['aria_send_comment']) ?>"><i class="fas fa-paper-plane"></i></button>
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
                        <div class="text-muted small"><?= htmlspecialchars($lang->offers['lbl_people_want']) ?></div>
                        <hr>
                        <div class="small text-muted">
                            <div class="mb-2"><i class="fas fa-user me-2"></i><?= htmlspecialchars($lang->offers['lbl_offered_by']) ?> <a href="<?= $BASEURL ?>/user-<?= $offer['user_id'] ?>.html" class="text-decoration-none"><?= htmlspecialchars($offer['username']) ?></a></div>
                            <div class="mb-2"><i class="fas fa-clock me-2"></i><?= htmlspecialchars(ags_fmt($lang->offers['txt_ago'], mkprettytime(TIMENOW - $offer['created_at']))) ?></div>
                            <?php if ($offer['year']): ?>
                            <div><i class="fas fa-film me-2"></i><?= htmlspecialchars(ags_fmt($lang->offers['lbl_year_value'], (int)$offer['year'])) ?></div>
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
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i><?= htmlspecialchars($lang->offers['pane_upload']) ?></h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->offers['aria_close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small"><?= htmlspecialchars($lang->offers['hint_upload']) ?></p>
                    <form method="post" action="offers.php?action=upload&oid=<?= $oid ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->offers['lbl_torrent_id']) ?></label>
                            <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="<?= htmlspecialchars($lang->offers['ph_torrent_id']) ?>">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal"><?= htmlspecialchars($lang->offers['btn_modal_cancel']) ?></button>
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 pulse-on-hover"><?= htmlspecialchars($lang->offers['btn_confirm_upload']) ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    $ags_js_lang = [];
    foreach ($lang->offers as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) $ags_js_lang[substr((string)$k, 3)] = $v;
    }
    ?>
    <script>
    const AGS_LANG = <?= json_encode($ags_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Translation helper: t(key, fallback, ...args) — substitutes {1} and %1$s
    function t(key, fallback, ...args) {
        let s = (typeof AGS_LANG === 'object' && AGS_LANG && typeof AGS_LANG[key] === 'string') ? AGS_LANG[key] : fallback;
        args.forEach((a, i) => {
            const n = i + 1;
            s = s.split('{' + n + '}').join(String(a)).split('%' + n + '$s').join(String(a));
        });
        return s;
    }

    const postKey = '<?= $mybb->post_code ?>';
    const oid = <?= $oid ?>;

    function wantOffer(oid) {
        const btn = document.getElementById('wantBtn');
        if (!btn) {
            console.error('Want button not found');
            return;
        }
        
        const originalHtml = btn.innerHTML;
        btn.textContent = '';
        const spinner = document.createElement('span');
        spinner.className = 'loading-spinner me-1';
        btn.appendChild(spinner);
        btn.appendChild(document.createTextNode(' ' + t('loading', 'Loading...')));
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
                alert(data.error || t('want_error', 'Error processing request'));
            }
        })
        .catch(error => {
            console.error('Want error:', error);
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            
            if (error.message.includes('HTTP')) {
                alert(t('server_error', 'Server error (HTTP {1}). Please try again.', error.message.replace('HTTP ', '')));
            } else {
                alert(t('network_error', 'Network error. Please check your connection and try again.'));
            }
        });
    }

    function addComment(oid) {
        const msg = document.getElementById('commentText').value.trim();
        if (!msg) {
            alert(t('comment_empty', 'Please enter a comment.'));
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
                alert(data.error || t('comment_error', 'Error adding comment'));
            }
        })
        .catch(error => {
            console.error('Comment error:', error);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                btn.disabled = false;
            }
            if (error.message.includes('HTTP')) {
                alert(t('server_error', 'Server error (HTTP {1}). Please try again.', error.message.replace('HTTP ', '')));
            } else {
                alert(t('network_error', 'Network error. Please check your connection and try again.'));
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
    stdhead(htmlspecialchars($lang->offers['title_create']));
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
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php" title="<?= htmlspecialchars($lang->offers['nav_home']) ?>" aria-label="<?= htmlspecialchars($lang->offers['nav_home']) ?>"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="offers.php"><i class="fas fa-gift me-1"></i><?= htmlspecialchars($lang->offers['nav_offers']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($lang->offers['nav_new']) ?></li>
            </ol>
        </nav>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="fas fa-gift me-2 text-success"></i><?= htmlspecialchars($lang->offers['pane_create']) ?></h5>
                <small class="text-muted"><?= htmlspecialchars($lang->offers['hint_create']) ?></small>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>

                <form method="post" action="offers.php?action=do_create">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= htmlspecialchars($lang->offers['lbl_title']) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" name="title" maxlength="255" required
                               value="<?= htmlspecialchars($mybb->get_input('title')) ?>" placeholder="<?= htmlspecialchars($lang->offers['ph_title']) ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->offers['lbl_category']) ?></label>
                            <select class="form-select rounded-3" name="category_id">
                                <option value="0"><?= htmlspecialchars($lang->offers['opt_cat_any']) ?></option>
                                <?php foreach ($cats as $cid => $cname): ?>
                                <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->offers['lbl_year']) ?></label>
                            <input type="number" class="form-control rounded-3" name="year" min="1900" max="<?= date('Y') + 2 ?>" placeholder="2024">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold"><?= htmlspecialchars($lang->offers['lbl_description']) ?></label>
                        <?= $editor['toolbar'] ?>
                        <textarea class="form-control rounded-3" name="description" id="description" rows="6" placeholder="<?= htmlspecialchars($lang->offers['ph_description']) ?>"><?= htmlspecialchars($mybb->get_input('description')) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="offers.php" class="btn btn-outline-secondary rounded-pill px-4 pulse-on-hover"><?= htmlspecialchars($lang->offers['btn_form_cancel']) ?></a>
                        <button type="submit" class="btn btn-success rounded-pill px-4 pulse-on-hover"><i class="fas fa-gift me-1"></i><?= htmlspecialchars($lang->offers['btn_submit']) ?></button>
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

stdhead(htmlspecialchars($lang->offers['title_list']));
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
            <h4 class="mb-0"><i class="fas fa-gift me-2 text-success"></i><?= htmlspecialchars($lang->offers['sec_list']) ?></h4>
            <small class="text-muted"><?= htmlspecialchars(ags_fmt($lang->offers['txt_total'], $total)) ?></small>
        </div>
        <a href="offers.php?action=create" class="btn btn-success rounded-pill px-4 pulse-on-hover">
            <i class="fas fa-plus me-1"></i><?= htmlspecialchars($lang->offers['btn_new']) ?>
        </a>
    </div>

    <!-- Quick status filters -->
    <div class="d-flex flex-wrap gap-2 mb-4 animate-fade-in-up">
        <?php foreach (['open' => $lang->offers['opt_status_open'], 'uploaded' => $lang->offers['opt_status_uploaded'], 'cancelled' => $lang->offers['opt_status_cancelled'], '' => $lang->offers['opt_status_all']] as $val => $label): ?>
        <a href="?status=<?= $val ?>&cat=<?= $filter_cat ?>&sort=<?= $sort ?>" class="btn btn-sm <?= $filter_status === $val ? 'btn-success' : 'btn-outline-secondary' ?> rounded-pill px-4 pulse-on-hover">
            <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <?php
        $count = 0;
        while ($offer = $db->fetch_array($offers_q)):
            $count++;
            $status_badge = match($offer['status']) {
                'open'      => '<span class="badge bg-success bg-opacity-25 text-success">' . htmlspecialchars($lang->offers['status_open']) . '</span>',
                'uploaded'  => '<span class="badge bg-primary bg-opacity-25 text-primary">' . htmlspecialchars($lang->offers['status_uploaded']) . '</span>',
                'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary">' . htmlspecialchars($lang->offers['status_cancelled']) . '</span>',
                default     => ''
            };
        ?>
        <div class="d-flex align-items-center gap-3 p-3 <?= $count > 1 ? 'border-top' : '' ?> offer-row animate-fade-in-up">
            <div class="text-center flex-shrink-0" style="min-width:48px">
                <div class="fw-bold text-success fs-5"><?= (int)$offer['requests'] ?></div>
                <div class="text-muted" style="font-size:.65rem"><?= htmlspecialchars($lang->offers['lbl_wants']) ?></div>
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
                    <span><i class="fas fa-clock me-1"></i><?= htmlspecialchars(ags_fmt($lang->offers['txt_ago'], mkprettytime(TIMENOW - $offer['created_at']))) ?></span>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <div class="text-center py-5 animate-fade-in-up">
            <i class="fas fa-gift fa-3x text-muted mb-3"></i>
            <h6 class="text-muted"><?= htmlspecialchars($lang->offers['txt_empty']) ?></h6>
            <a href="offers.php?action=create" class="btn btn-success btn-sm rounded-pill px-4 mt-2 pulse-on-hover">
                <i class="fas fa-plus me-1"></i><?= htmlspecialchars($lang->offers['btn_create_first']) ?>
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