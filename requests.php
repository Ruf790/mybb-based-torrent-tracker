<?php
declare(strict_types=1);


define('IN_MYBB', 1);
define('SCRIPTNAME', 'requests.php');

require_once 'global.php';
$lang->load('requests');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'cache/smilies.php';
require_once INC_PATH . '/editor.php';
require_once INC_PATH . '/functions_mkprettytime.php';
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/class_parser.php';
require_once INC_PATH . '/functions_bonuslog.php';
require_once INC_PATH . '/datahandler.php';


$parser = new postParser;
$parser_options = [
    "allow_html" => 0,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

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
$q = $db->sql_query_prepared("SELECT id, name FROM categories ORDER BY name");
while ($r = $db->fetch_array($q)) $cats[$r['id']] = $r['name'];

// ── AJAX: Vote ────────────────────────────────────────────────────────────────
if ($action === 'vote' && $mybb->request_method === 'post') {
    header('Content-Type: application/json');
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(['success' => false, 'error' => $lang->requests['err_post_key']]);
        exit();
    }
    $uid = (int)$CURUSER['id'];
    $bounty = max(0, (float)($mybb->input['bounty'] ?? 0));
    $req = $db->fetch_array($db->sql_query_prepared("SELECT id, status, user_id FROM requests WHERE id = ?", [$rid]));
    if (!$req) { echo json_encode(['success' => false, 'error' => $lang->requests['err_not_found']]); exit(); }
    if ($req['status'] !== 'open') { echo json_encode(['success' => false, 'error' => $lang->requests['err_closed']]); exit(); }
    if ($req['user_id'] == $uid) { echo json_encode(['success' => false, 'error' => $lang->requests['err_own_vote']]); exit(); }
    if ($db->fetch_array($db->sql_query_prepared("SELECT id FROM request_votes WHERE request_id = ? AND user_id = ?", [$rid, $uid]))) {
        echo json_encode(['success' => false, 'error' => $lang->requests['err_already_voted']]);
        exit();
    }
    if ($bounty > 0) {
        if ((float)$CURUSER['seedbonus'] < $bounty) {
            echo json_encode(['success' => false, 'error' => $lang->requests['err_no_bonus']]);
            exit();
        }
        $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?", [$bounty, $uid]);
        $db->sql_query_prepared("UPDATE requests SET bounty = bounty + ? WHERE id = ?", [$bounty, $rid]);
        bonus_log($uid, -$bounty, 'request', "Bounty added to request #{$rid}", $rid);
    }
    $vote_data = [
        'request_id' => $rid,
        'user_id'    => $uid,
        'bounty'     => $bounty,
        'created_at' => TIMENOW,
    ];
    $columns      = array_keys($vote_data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO request_votes (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($vote_data)
    );
    $db->sql_query_prepared("UPDATE requests SET votes = votes + 1, updated_at = ? WHERE id = ?", [TIMENOW, $rid]);
    $new_votes = (int)$db->fetch_field($db->sql_query_prepared("SELECT votes FROM requests WHERE id = ?", [$rid]), 'votes');
    echo json_encode(['success' => true, 'votes' => $new_votes]);
    exit();
}

// ── AJAX: Add Comment ─────────────────────────────────────────────────────────
if ($action === 'add_comment' && $mybb->request_method === 'post') {
    header('Content-Type: application/json');
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(['success' => false, 'error' => $lang->requests['err_post_key']]);
        exit();
    }
    $message = trim($mybb->get_input('message'));
    if (empty($message)) { echo json_encode(['success' => false, 'error' => $lang->requests['err_empty_message']]); exit(); }

    // The request must exist; title and author are needed for the bell below
    $reqRow = $db->fetch_array($db->sql_query_prepared("SELECT id, title, user_id FROM requests WHERE id = ?", [$rid]));
    if (!$reqRow) {
        echo json_encode(['success' => false, 'error' => $lang->requests['err_not_found']]);
        exit();
    }

    $comment_data = [
        'request_id' => $rid,
        'user_id'    => (int)$CURUSER['id'],
        'message'    => $message,
        'created_at' => TIMENOW,
    ];
    $columns      = array_keys($comment_data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $db->sql_query_prepared(
        "INSERT INTO request_comments (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
        array_values($comment_data)
    );

    // Bell: @mentions, then the request's author (each person once).
    // The comment is already saved: a notify error must not turn into a JSON error (the user would post it again)
    try {
        require_once INC_PATH . '/functions_notify.php';
        $nLink = 'requests.php?action=view&rid=' . $rid;
        $nDone = notify_mentions($message, (int)$CURUSER['id'], $rid, (string)$reqRow['title'], $nLink);
        if (!in_array((int)$reqRow['user_id'], $nDone, true)) {
            notify((int)$reqRow['user_id'], 'request_comment', (int)$CURUSER['id'], $rid, [(string)$reqRow['title']], $nLink);
        }
    } catch (\Throwable $e) {
        error_log('notify (request comment): ' . $e->getMessage());
    }

    echo json_encode(['success' => true]);
    exit();
}



// ── POST: Create Request ──────────────────────────────────────────────────────
if ($action === 'do_create' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->requests['err_post_key']);
    $title = trim($mybb->get_input('title'));
    $description = trim($mybb->get_input('description'));
    $category_id = $mybb->get_input('category_id', MyBB::INPUT_INT);
    $year = ($year_raw = trim($mybb->get_input('year'))) !== '' && (int)$year_raw > 0 ? (int)$year_raw : null;
    $bounty = max(0, (float)($mybb->input['bounty'] ?? 0));
    $errors = [];
    if (strlen($title) < 3) $errors[] = $lang->requests['err_title_short'];
    if (strlen($title) > 255) $errors[] = $lang->requests['err_title_long'];
    if ($bounty > 0 && (float)$CURUSER['seedbonus'] < $bounty) $errors[] = $lang->requests['err_no_bonus'];
    if (empty($errors)) {
        if ($bounty > 0) {
            $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?", [$bounty, (int)$CURUSER['id']]);
        }
        $logBounty = $bounty;
        $db->sql_query_prepared(
            "INSERT INTO requests (user_id, title, description, category_id, year, status, votes, bounty, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'open', 1, ?, ?, ?)",
            [(int)$CURUSER['id'], $title, $description, $category_id, $year, $bounty, TIMENOW, TIMENOW]
        );
        $id = $db->insert_id();
        if (!empty($logBounty)) bonus_log((int)$CURUSER['id'], -$logBounty, 'request', "Bounty for new request #{$id}", (int)$id);
        $vote_data    = ['request_id' => $id, 'user_id' => (int)$CURUSER['id'], 'bounty' => $bounty, 'created_at' => TIMENOW];
        $columns      = array_keys($vote_data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $db->sql_query_prepared(
            "INSERT INTO request_votes (" . implode(', ', $columns) . ") VALUES ({$placeholders})",
            array_values($vote_data)
        );
        $_SESSION['flash'] = ['msg' => $lang->requests['flash_created'], 'type' => 'success'];
        header('Location: requests.php?action=view&rid=' . $id);
        exit();
    }
}

// ── POST: Fill / Cancel / Delete ─────────────────────────────────────────────
if ($action === 'fill' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->requests['err_post_key']);
    $torrent_id = $mybb->get_input('torrent_id', MyBB::INPUT_INT);
    $req = $db->fetch_array($db->sql_query_prepared("SELECT * FROM requests WHERE id = ?", [$rid]));
    if (!$req || $req['status'] !== 'open') stderr($lang->requests['err_invalid_request']);
    if (!$torrent_id || !$db->num_rows($db->sql_query_prepared("SELECT id FROM torrents WHERE id = ?", [$torrent_id]))) {
        $_SESSION['flash'] = ['msg' => ags_fmt($lang->requests['flash_torrent_missing'], $torrent_id), 'type' => 'danger'];
        header('Location: requests.php?action=view&rid=' . $rid);
        exit();
    }
    if ((float)$req['bounty'] > 0) {
        $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?", [$req['bounty'], (int)$CURUSER['id']]);
        bonus_log((int)$CURUSER['id'], (float)$req['bounty'], 'request', "Bounty for filling request #{$rid}", $rid);
    }
    $db->sql_query_prepared(
        "UPDATE requests SET status = ?, filled_by = ?, torrent_id = ?, filled_at = ?, updated_at = ? WHERE id = ?",
        ['filled', (int)$CURUSER['id'], $torrent_id, TIMENOW, TIMENOW, $rid]
    );
    if ($req['user_id'] != $CURUSER['id']) {
        $requester = $db->fetch_array($db->sql_query_prepared("SELECT id, language FROM users WHERE id = ?", [(int)$req['user_id']]));
        if ($requester) {
            require_once INC_PATH . '/functions_pm.php';

            // Скобки в названии ломают [b]...[/b] / [url=...]...[/url]
            $request_title = str_replace(['[', ']'], ['(', ')'], (string)$req['title']);

            // Текст ЛС берётся из requests.lang.php на языке получателя
            send_pm([
                'subject'       => ['pm_filled_subject'],
                'message'       => [
                    'pm_filled_message',
                    $request_title,
                    $BASEURL . '/details.php?id=' . $torrent_id,
                ],
                'touid'         => (int)$requester['id'],
                'language'      => (string)($requester['language'] ?? ''),
                'language_file' => 'requests',
                'sender'        => ['uid' => -1],
            ], -1, true);
        }
    }

    // Bell: the request's author + everyone who voted (the PM to the author stays).
    // The filler gets nothing: notify() skips actor == recipient
    try {
        require_once INC_PATH . '/functions_notify.php';
        $nLink  = 'details.php?id=' . $torrent_id;
        $nTitle = [(string)$req['title']];
        notify((int)$req['user_id'], 'request_filled', (int)$CURUSER['id'], $rid, $nTitle, $nLink);

        $vq = $db->sql_query_prepared(
            'SELECT DISTINCT user_id FROM request_votes WHERE request_id = ? AND user_id <> ?',
            [$rid, (int)$req['user_id']]
        );
        while ($vq && ($v = $db->fetch_array($vq))) {
            notify((int)$v['user_id'], 'request_vote_filled', (int)$CURUSER['id'], $rid, $nTitle, $nLink);
        }
    } catch (\Throwable $e) {
        error_log('notify (request filled): ' . $e->getMessage());
    }

    $_SESSION['flash'] = ['msg' => $lang->requests['flash_filled'], 'type' => 'success'];
    header('Location: requests.php?action=view&rid=' . $rid);
    exit();
}

if ($action === 'cancel' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->requests['err_post_key']);
    $req = $db->fetch_array($db->sql_query_prepared("SELECT user_id, status FROM requests WHERE id = ?", [$rid]));
    if (!$req || ($req['user_id'] != $CURUSER['id'] && !$is_mod)) stderr($lang->requests['err_no_permission']);
    $db->sql_query_prepared("UPDATE requests SET status = ?, updated_at = ? WHERE id = ?", ['cancelled', TIMENOW, $rid]);
    $_SESSION['flash'] = ['msg' => $lang->requests['flash_cancelled'], 'type' => 'info'];
    header('Location: requests.php');
    exit();
}

if ($action === 'delete' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->requests['err_post_key']);
    if (!$is_mod) stderr($lang->requests['err_no_permission']);
    $req = $db->fetch_array($db->sql_query_prepared("SELECT bounty, status FROM requests WHERE id = ?", [$rid]));
    if (!$req) stderr($lang->requests['err_not_found']);
    if ($req['status'] === 'open' && (float)$req['bounty'] > 0) {
        $votes_q = $db->sql_query_prepared("SELECT user_id, bounty FROM request_votes WHERE request_id = ?", [$rid]);
        while ($v = $db->fetch_array($votes_q)) {
            if ((float)$v['bounty'] > 0) {
                $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?", [$v['bounty'], $v['user_id']]);
                bonus_log((int)$v['user_id'], (float)$v['bounty'], 'request', "Bounty refund: request #{$rid} deleted", $rid, (int)$CURUSER['id']);
            }
        }
    }
    $db->sql_query_prepared("DELETE FROM request_votes WHERE request_id = ?", [$rid]);
    $db->sql_query_prepared("DELETE FROM request_comments WHERE request_id = ?", [$rid]);
    $db->sql_query_prepared("DELETE FROM requests WHERE id = ?", [$rid]);
    $_SESSION['flash'] = ['msg' => $lang->requests['flash_deleted'], 'type' => 'success'];
    header('Location: requests.php');
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: Single request
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'view' && $rid) {
    $req = $db->fetch_array($db->sql_query_prepared(
        "SELECT r.*, u.username, u.avatar, u.usergroup
         FROM requests r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.id = ?",
        [$rid]
    ));
    if (!$req) stderr($lang->requests['err_not_found']);

    $my_vote = $db->num_rows($db->sql_query_prepared(
        "SELECT id FROM request_votes WHERE request_id = ? AND user_id = ?",
        [$rid, (int)$CURUSER['id']]
    )) > 0;

    $comments_q = $db->sql_query_prepared(
        "SELECT rc.*, u.username, u.avatar, u.usergroup
         FROM request_comments rc
         LEFT JOIN users u ON u.id = rc.user_id
         WHERE rc.request_id = ?
         ORDER BY rc.created_at ASC",
        [$rid]
    );

    $status_badge = match($req['status']) {
        'open'      => '<span class="badge bg-success bg-opacity-25 text-success"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>' . htmlspecialchars($lang->requests['status_open']) . '</span>',
        'filled'    => '<span class="badge bg-primary bg-opacity-25 text-primary"><i class="fas fa-check me-1"></i>' . htmlspecialchars($lang->requests['status_filled']) . '</span>',
        'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary"><i class="fas fa-times me-1"></i>' . htmlspecialchars($lang->requests['status_cancelled']) . '</span>',
        default     => ''
    };

    stdhead(htmlspecialchars(ags_fmt($lang->requests['title_view'], $req['title'])));
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
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php" title="<?= htmlspecialchars($lang->requests['nav_home']) ?>" aria-label="<?= htmlspecialchars($lang->requests['nav_home']) ?>"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="requests.php"><i class="fas fa-list-alt me-1"></i><?= htmlspecialchars($lang->requests['nav_requests']) ?></a></li>
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
                            <span class="text-warning fw-semibold"><i class="fas fa-coins me-1"></i><?= htmlspecialchars(ags_fmt($lang->requests['lbl_bounty_amount'], number_format((float)$req['bounty'], 1))) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-users me-1"></i><?= htmlspecialchars(ags_fmt($lang->requests['lbl_voters_count'], (int)$req['votes'])) ?></span>
                        </div>
                        <?php if ($req['status'] === 'filled' && $req['torrent_id']): ?>
                        <div class="alert alert-success mt-4 mb-0 rounded-3 animate-fade-in-up">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($lang->requests['txt_filled']) ?> <a href="<?= get_torrent_link($req['torrent_id']) ?>" class="alert-link fw-semibold"><?= htmlspecialchars($lang->requests['lnk_download']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($req['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0 d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!$my_vote && $req['user_id'] != $CURUSER['id']): ?>
                        <button class="btn btn-sm btn-outline-success rounded-pill px-4 pulse-on-hover" onclick="voteRequest(<?= $rid ?>)" id="voteBtn">
                            <i class="fas fa-thumbs-up me-1"></i><?= htmlspecialchars($lang->requests['btn_vote']) ?> (<span id="vote-count"><?= (int)$req['votes'] ?></span>)
                        </button>
                        <div class="input-group input-group-sm" style="width:200px">
                            <input type="number" class="form-control" id="bountyInput" placeholder="<?= htmlspecialchars($lang->requests['ph_add_bp']) ?>" min="0" step="0.1">
                            <span class="input-group-text text-muted small"><?= htmlspecialchars(ags_fmt($lang->requests['lbl_balance'], number_format((float)$CURUSER['seedbonus'], 1))) ?></span>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small"><i class="fas fa-check-circle text-success me-1"></i><?= htmlspecialchars($lang->requests['txt_you_voted']) ?></span>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary rounded-pill px-4 ms-auto pulse-on-hover" data-bs-toggle="modal" data-bs-target="#fillModal">
                            <i class="fas fa-upload me-1"></i><?= htmlspecialchars($lang->requests['btn_fill']) ?>
                        </button>
                        <?php if ($req['user_id'] == $CURUSER['id'] || $is_mod): ?>
                        <form method="post" action="requests.php?action=cancel&rid=<?= $rid ?>" class="d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode($lang->requests['conf_cancel'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 pulse-on-hover"><i class="fas fa-times me-1"></i><?= htmlspecialchars($lang->requests['btn_cancel_request']) ?></button>
                        </form>
                        <?php if ($is_mod): ?>
                        <form method="post" action="requests.php?action=delete&rid=<?= $rid ?>" class="d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode($lang->requests['conf_delete'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">
                            <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 pulse-on-hover"><i class="fas fa-trash-alt me-1"></i><?= htmlspecialchars($lang->requests['btn_delete']) ?></button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card border-0 shadow-sm rounded-4 animate-fade-in-up">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="mb-0"><i class="fas fa-comments me-2"></i><?= htmlspecialchars($lang->requests['sec_comments']) ?></h6>
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
                        <div class="text-center py-4 text-muted small"><?= htmlspecialchars($lang->requests['txt_no_comments']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($req['status'] === 'open'): ?>
                    <div class="card-footer bg-transparent border-0">
                        <div class="d-flex gap-2">
                            <textarea class="form-control form-control-sm rounded-3" id="commentText" rows="2" placeholder="<?= htmlspecialchars($lang->requests['ph_comment']) ?>"></textarea>
                            <button class="btn btn-sm btn-primary rounded-pill px-4 pulse-on-hover" onclick="addComment(<?= $rid ?>)" title="<?= htmlspecialchars($lang->requests['aria_send_comment']) ?>" aria-label="<?= htmlspecialchars($lang->requests['aria_send_comment']) ?>"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 text-center animate-fade-in-up">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-primary" id="vote-count-sidebar"><?= (int)$req['votes'] ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($lang->requests['lbl_voters']) ?></div>
                        <?php if ((float)$req['bounty'] > 0): ?>
                        <hr>
                        <div class="text-warning fw-semibold"><i class="fas fa-coins me-1"></i><?= number_format((float)$req['bounty'], 1) ?> <?= htmlspecialchars($lang->requests['unit_bp']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($lang->requests['lbl_total_bounty']) ?></div>
                        <?php endif; ?>
                        <hr>
                        <div class="small text-muted"><?= htmlspecialchars(ags_fmt($lang->requests['txt_created_ago'], mkprettytime(TIMENOW - $req['created_at']))) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fillModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i><?= htmlspecialchars($lang->requests['pane_fill']) ?></h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($lang->requests['aria_close']) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small"><?= htmlspecialchars($lang->requests['hint_fill']) ?></p>
                    <form method="post" action="requests.php?action=fill&rid=<?= $rid ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->requests['lbl_torrent_id']) ?></label>
                            <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="<?= htmlspecialchars($lang->requests['ph_torrent_id']) ?>">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal"><?= htmlspecialchars($lang->requests['btn_modal_cancel']) ?></button>
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 pulse-on-hover"><?= htmlspecialchars($lang->requests['btn_mark_filled']) ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    $ags_js_lang = [];
    foreach ($lang->requests as $k => $v) {
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
    btn.textContent = '';
    const spinner = document.createElement('span');
    spinner.className = 'loading-spinner me-1';
    btn.appendChild(spinner);
    btn.appendChild(document.createTextNode(' ' + t('voting', 'Voting...')));
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
            alert(data.error || t('vote_error', 'Error processing vote'));
        }
    })
    .catch(error => {
        console.error('Vote error:', error);
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn-loading');
        btn.disabled = false;
        
        // Показываем понятное сообщение об ошибке
        if (error.message.includes('HTTP')) {
            alert(t('server_error', 'Server error (HTTP {1}). Please try again.', error.message.replace('HTTP ', '')));
        } else {
            alert(t('network_error', 'Network error. Please check your connection and try again.'));
        }
    });
}

function addComment(rid) {
    const msg = document.getElementById('commentText').value.trim();
    if (!msg) {
        alert(t('comment_empty', 'Please enter a comment.'));
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
    stdhead(htmlspecialchars($lang->requests['title_create']));
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
                <li class="breadcrumb-item"><a href="<?= $BASEURL ?>/index2.php" title="<?= htmlspecialchars($lang->requests['nav_home']) ?>" aria-label="<?= htmlspecialchars($lang->requests['nav_home']) ?>"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="requests.php"><i class="fas fa-list-alt me-1"></i><?= htmlspecialchars($lang->requests['nav_requests']) ?></a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($lang->requests['nav_new']) ?></li>
            </ol>
        </nav>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i><?= htmlspecialchars($lang->requests['pane_create']) ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>
                <form method="post" action="requests.php?action=do_create">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= htmlspecialchars($lang->requests['lbl_title']) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" name="title" maxlength="255" required value="<?= htmlspecialchars($mybb->get_input('title')) ?>" placeholder="<?= htmlspecialchars($lang->requests['ph_title']) ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->requests['lbl_category']) ?></label>
                            <select class="form-select rounded-3" name="category_id">
                                <option value="0"><?= htmlspecialchars($lang->requests['opt_cat_any']) ?></option>
                                <?php foreach ($cats as $cid => $cname): ?>
                                <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->requests['lbl_year']) ?></label>
                            <input type="number" class="form-control rounded-3" name="year" min="1900" max="<?= date('Y') + 2 ?>" placeholder="2024">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($lang->requests['lbl_bounty']) ?></label>
                            <input type="number" class="form-control rounded-3" name="bounty" min="0" step="0.1" value="0">
                            <div class="form-text"><?= htmlspecialchars(ags_fmt($lang->requests['hint_balance'], number_format((float)$CURUSER['seedbonus'], 1))) ?></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold"><?= htmlspecialchars($lang->requests['lbl_description']) ?></label>
                        <?= $editor['toolbar'] ?>
                        <textarea class="form-control rounded-3" name="description" id="description" rows="6" placeholder="<?= htmlspecialchars($lang->requests['ph_description']) ?>"><?= htmlspecialchars($mybb->get_input('description')) ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="requests.php" class="btn btn-outline-secondary rounded-pill px-4 pulse-on-hover"><?= htmlspecialchars($lang->requests['btn_form_cancel']) ?></a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 pulse-on-hover"><i class="fas fa-paper-plane me-1"></i><?= htmlspecialchars($lang->requests['btn_submit']) ?></button>
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
$where_params = [];
if ($filter_status) { $where[] = "r.status = ?"; $where_params[] = $filter_status; }
if ($filter_cat)    { $where[] = "r.category_id = ?"; $where_params[] = $filter_cat; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->fetch_field(
    $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM requests r $where_sql", $where_params),
    'cnt'
);
$offset  = ($page - 1) * $perpage;

$requests_q = $db->sql_query_prepared(
    "SELECT r.*, u.username, u.avatar
     FROM requests r
     LEFT JOIN users u ON u.id = r.user_id
     $where_sql
     ORDER BY r.$sort DESC
     LIMIT ?, ?",
    [...$where_params, $offset, $perpage]
);

stdhead(htmlspecialchars($lang->requests['title_list']));
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
            <h4 class="mb-0"><i class="fas fa-list-alt me-2 text-primary"></i><?= htmlspecialchars($lang->requests['sec_list']) ?></h4>
            <small class="text-muted"><?= htmlspecialchars(ags_fmt($lang->requests['txt_total'], $total)) ?></small>
        </div>
        <a href="requests.php?action=create" class="btn btn-primary rounded-pill px-4 pulse-on-hover">
            <i class="fas fa-plus me-1"></i><?= htmlspecialchars($lang->requests['btn_new']) ?>
        </a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4 animate-fade-in-up">
        <?php foreach (['open' => $lang->requests['opt_status_open'], 'filled' => $lang->requests['opt_status_filled'], 'cancelled' => $lang->requests['opt_status_cancelled'], '' => $lang->requests['opt_status_all']] as $val => $label): ?>
        <a href="?status=<?= $val ?>&cat=<?= $filter_cat ?>&sort=<?= $sort ?>" class="btn btn-sm <?= $filter_status === $val ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-4 pulse-on-hover">
            <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <?php
        $count = 0;
        while ($req = $db->fetch_array($requests_q)):
            $count++;
            $status_badge = match($req['status']) {
                'open'      => '<span class="badge bg-success bg-opacity-25 text-success">' . htmlspecialchars($lang->requests['status_open']) . '</span>',
                'filled'    => '<span class="badge bg-primary bg-opacity-25 text-primary">' . htmlspecialchars($lang->requests['status_filled']) . '</span>',
                'cancelled' => '<span class="badge bg-secondary bg-opacity-25 text-secondary">' . htmlspecialchars($lang->requests['status_cancelled']) . '</span>',
                default     => ''
            };
        ?>
        <div class="d-flex align-items-center gap-3 p-3 <?= $count > 1 ? 'border-top' : '' ?> req-row animate-fade-in-up">
            <div class="text-center flex-shrink-0" style="min-width:48px">
                <div class="fw-bold text-primary fs-5"><?= (int)$req['votes'] ?></div>
                <div class="text-muted" style="font-size:.65rem"><?= htmlspecialchars($lang->requests['lbl_votes']) ?></div>
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
                    <span><i class="fas fa-clock me-1"></i><?= htmlspecialchars(ags_fmt($lang->requests['txt_ago'], mkprettytime(TIMENOW - $req['created_at']))) ?></span>
                    <?php if ((float)$req['bounty'] > 0): ?>
                    <span class="text-warning"><i class="fas fa-coins me-1"></i><?= number_format((float)$req['bounty'], 1) ?> <?= htmlspecialchars($lang->requests['unit_bp']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($count === 0): ?>
        <div class="text-center py-5 animate-fade-in-up">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h6 class="text-muted"><?= htmlspecialchars($lang->requests['txt_empty']) ?></h6>
            <a href="requests.php?action=create" class="btn btn-primary btn-sm rounded-pill px-4 mt-2 pulse-on-hover">
                <i class="fas fa-plus me-1"></i><?= htmlspecialchars($lang->requests['btn_create_first']) ?>
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