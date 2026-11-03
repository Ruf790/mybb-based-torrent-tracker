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
require_once INC_PATH . '/functions_requests.php';


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

// ── Flash helper (SweetAlert2 via swal-notify.js, same showToast() API) ────────
function show_flash(): void {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $type = match($f['type']) {
            'success' => 'success',
            'danger'  => 'error',
            'warning' => 'warning',
            default   => 'info',
        };
        $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        // Message as text, not HTML (it may contain a request title)
        echo '<script>document.addEventListener("DOMContentLoaded", function(){ var d = document.createElement("div"); d.textContent = '
           . json_encode((string)$f['msg'], $flags) . '; showToast(d.innerHTML, ' . json_encode($type) . '); });</script>';
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
    $imdb_raw = trim($mybb->get_input('imdb'));
    $imdb_id  = $imdb_raw !== '' ? req_imdb_id($imdb_raw) : null;
    $errors = [];
    if ($imdb_raw !== '' && $imdb_id === null) $errors[] = ($lang->requests['err_imdb_invalid'] ?? 'Invalid IMDb link. Example: https://www.imdb.com/title/tt1234567/');
    if (strlen($title) < 3) $errors[] = $lang->requests['err_title_short'];
    if (strlen($title) > 255) $errors[] = $lang->requests['err_title_long'];
    if ($bounty > 0 && (float)$CURUSER['seedbonus'] < $bounty) $errors[] = $lang->requests['err_no_bonus'];
    if (empty($errors)) {
        if ($bounty > 0) {
            $db->sql_query_prepared("UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?", [$bounty, (int)$CURUSER['id']]);
        }
        $logBounty = $bounty;
        $db->sql_query_prepared(
            "INSERT INTO requests (user_id, title, description, category_id, year, imdb_id, status, votes, bounty, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'open', 1, ?, ?, ?)",
            [(int)$CURUSER['id'], $title, $description, $category_id, $year, $imdb_id, $bounty, TIMENOW, TIMENOW]
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
        require_once INC_PATH . '/functions_chat.php';
        chat_bot_post('request', ['id' => (int)$id]);
        $_SESSION['flash'] = ['msg' => $lang->requests['flash_created'], 'type' => 'success'];
        header('Location: requests.php?action=view&rid=' . $id);
        exit();
    }
    $action = 'create';   // show the form again with the errors and the typed values
}

// ── POST: Fill / Cancel / Delete ─────────────────────────────────────────────
if ($action === 'fill' && $mybb->request_method === 'post') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) stderr($lang->requests['err_post_key']);
    $torrent_id = $mybb->get_input('torrent_id', MyBB::INPUT_INT);

    // Закрыть может владелец раздачи или стафф; награда, ЛС и колокольчик — в req_fill()
    $res = req_fill($rid, $torrent_id, (int)$CURUSER['id'], $is_mod);
    if (!$res['ok']) {
        $msg = $lang->requests[$res['error']] ?? ($res['error'] === 'err_fill_not_owner' ? 'You can only fill a request with your own torrent.' : $res['error']);
        $_SESSION['flash'] = ['msg' => ags_fmt($msg, ...($res['args'] ?? [])), 'type' => 'danger'];
        header('Location: requests.php?action=view&rid=' . $rid);
        exit();
    }

    require_once INC_PATH . '/functions_chat.php';
    chat_bot_post('request_filled', ['id' => (int)$rid, 'tid' => (int)$torrent_id, 'by' => (int)$res['filler']]);
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
// Shared view helpers (new style: st-ui.css + requests.css, prefix rq-)
// ═══════════════════════════════════════════════════════════════════════════════
$rqL = static fn(string $k, string $fb): string => (string)($lang->requests[$k] ?? $fb);
$h   = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

/** CSS/JS shared by all request pages (after stdhead()) */
$rq_assets = static function () use ($BASEURL): void {
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/requests.css?ver=1">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">'
       . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>'
       . '<script src="' . $BASEURL . '/scripts/swal-notify.js?ver=2"></script>';
};

/** Status badge */
$rq_status = static function (string $status) use ($lang, $h): string {
    return match ($status) {
        'open'      => '<span class="st-badge st-soft-success"><i class="fa-solid fa-circle-dot"></i>' . $h($lang->requests['status_open']) . '</span>',
        'filled'    => '<span class="st-badge st-soft-primary"><i class="fa-solid fa-check"></i>' . $h($lang->requests['status_filled']) . '</span>',
        'cancelled' => '<span class="st-badge st-soft-muted"><i class="fa-solid fa-ban"></i>' . $h($lang->requests['status_cancelled']) . '</span>',
        default     => '',
    };
};

/** Avatar: stored path / remote URL / first letter */
$rq_avatar = static function (?string $avatar, string $name, int $size = 36) use ($BASEURL, $h): string {
    $avatar = trim((string)$avatar);
    if ($avatar === '') {
        return '<span class="rq-ava rq-ava--letter" style="width:' . $size . 'px;height:' . $size . 'px">' . $h(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>';
    }
    $src = preg_match('#^https?://#i', $avatar) ? $avatar : $BASEURL . '/' . ltrim($avatar, '/');
    return '<img class="rq-ava" src="' . $h($src) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy">';
};

/** Gray KPI tile with a big icon in the corner */
$rq_kpi = static function (string $icon, string $tone, string $value, string $label) use ($h): string {
    return '<div class="st-kpi st-kpi--muted">'
         . '<span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div class="min-w-0"><div class="st-kpi__val">' . $value . '</div><div class="st-kpi__lbl">' . $h($label) . '</div></div>'
         . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i></div>';
};

/** Breadcrumb */
$rq_crumbs = static function (array $items) use ($BASEURL, $lang, $h): string {
    $out = '<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb rq-crumbs mb-0">'
         . '<li class="breadcrumb-item"><a href="' . $BASEURL . '/index2.php" title="' . $h($lang->requests['nav_home']) . '" aria-label="' . $h($lang->requests['nav_home']) . '"><i class="fa-solid fa-house"></i></a></li>';
    foreach ($items as [$label, $url]) {
        $out .= $url !== ''
            ? '<li class="breadcrumb-item"><a href="' . $h($url) . '">' . $h($label) . '</a></li>'
            : '<li class="breadcrumb-item active text-truncate" aria-current="page">' . $h($label) . '</li>';
    }
    return $out . '</ol></nav>';
};

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: Single request
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'view' && $rid) {
    $req = $db->fetch_array($db->sql_query_prepared(
        "SELECT r.*, u.username, u.avatar, u.usergroup,
                f.username AS filler_name, f.usergroup AS filler_group
         FROM requests r
         LEFT JOIN users u ON u.id = r.user_id
         LEFT JOIN users f ON f.id = r.filled_by
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
    $comments = [];
    while ($c = $db->fetch_array($comments_q)) $comments[] = $c;

    $isOpen  = $req['status'] === 'open';
    $isOwner = (int)$req['user_id'] === (int)$CURUSER['id'];
    $tone    = match ($req['status']) { 'open' => 'success', 'filled' => 'primary', default => 'muted' };
    $icon    = match ($req['status']) { 'open' => 'fa-hand-holding-heart', 'filled' => 'fa-circle-check', default => 'fa-ban' };
    $author  = $req['username'] !== null
        ? '<a href="' . $h(get_profile_link((int)$req['user_id'])) . '">' . format_name($h($req['username']), (int)$req['usergroup']) . '</a>'
        : '#' . (int)$req['user_id'];

    stdhead(htmlspecialchars(ags_fmt($lang->requests['title_view'], $req['title'])));
    $rq_assets();
    show_flash();
    ?>
<div class="container-md st-page rq-page py-3">

    <?= $rq_crumbs([[$lang->requests['nav_requests'], 'requests.php'], [(string)$req['title'], '']]) ?>

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1"><?= $rq_status((string)$req['status']) ?>
                <?php if ($req['category_id'] && isset($cats[$req['category_id']])): ?>
                    <span class="st-badge st-soft-muted"><i class="fa-solid fa-tag"></i><?= $h($cats[$req['category_id']]) ?></span>
                <?php endif; ?>
            </div>
            <h1 class="st-title"><?= $h($req['title']) ?><?= $req['year'] ? ' <span class="rq-year">(' . (int)$req['year'] . ')</span>' : '' ?></h1>
            <div class="st-head__meta">
                <span><i class="fa-solid fa-user"></i><?= $author ?></span>
                <span><i class="fa-regular fa-calendar"></i><?= my_datee($dateformat, $req['created_at']) ?> <?= my_datee($timeformat, $req['created_at']) ?></span>
                <?php if (!empty($req['imdb_id'])): ?>
                    <span><span class="rq-imdb me-1">IMDb</span><a href="https://www.imdb.com/title/<?= $h((string)$req['imdb_id']) ?>/" target="_blank" rel="noopener"><?= $h((string)$req['imdb_id']) ?></a></span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- KPI tiles -->
    <div class="rq-kpis rq-kpis--3 mb-3">
        <?= $rq_kpi('fa-users', 'primary', '<span id="vote-count-sidebar">' . number_format((int)$req['votes']) . '</span>', $lang->requests['lbl_voters']) ?>
        <?= $rq_kpi('fa-coins', 'warning', number_format((float)$req['bounty'], 1) . ' <small>' . $h($lang->requests['unit_bp']) . '</small>', $lang->requests['lbl_total_bounty']) ?>
        <?= $rq_kpi('fa-clock', 'info', $h(mkprettytime(TIMENOW - (int)$req['created_at'])), $rqL('kpi_age', 'Request age')) ?>
    </div>

    <?php if ($req['status'] === 'filled' && $req['torrent_id']): ?>
        <div class="rq-filled mb-3">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-circle-check"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= $h($lang->requests['txt_filled']) ?></div>
                <?php if (!empty($req['filler_name'])): ?>
                    <div class="st-meta"><?= $h(ags_fmt($rqL('lbl_filled_by', 'Filled by {1}'), (string)$req['filler_name'])) ?><?= !empty($req['filled_at']) ? ' &middot; ' . my_datee($dateformat, $req['filled_at']) : '' ?></div>
                <?php endif; ?>
            </div>
            <a href="<?= $h(get_torrent_link((int)$req['torrent_id'])) ?>" class="btn btn-sm btn-success st-pill"><i class="fa-solid fa-download me-1"></i><?= $h($lang->requests['lnk_download']) ?></a>
        </div>
    <?php endif; ?>

    <!-- Description + actions -->
    <section class="st-card mb-3">
        <div class="st-card__head"><i class="fa-solid fa-align-left"></i><?= $h($lang->requests['lbl_description']) ?></div>
        <div class="st-card__body">
            <?php if (trim((string)$req['description']) !== ''): ?>
                <div class="rq-desc"><?= $parser->parse_message($req['description'], $parser_options) ?></div>
            <?php else: ?>
                <div class="st-meta"><?= $h($rqL('txt_no_description', 'No description.')) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($isOpen): ?>
        <div class="st-toolbar st-toolbar--bottom rq-actions">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if (!$my_vote && !$isOwner): ?>
                    <button type="button" class="btn btn-sm btn-success st-pill" data-rq-vote="<?= $rid ?>" id="voteBtn">
                        <i class="fa-solid fa-thumbs-up me-1"></i><?= $h($lang->requests['btn_vote']) ?> (<span id="vote-count"><?= (int)$req['votes'] ?></span>)
                    </button>
                    <div class="input-group input-group-sm rq-bounty-input">
                        <span class="input-group-text"><i class="fa-solid fa-coins"></i></span>
                        <input type="number" class="form-control" id="bountyInput" placeholder="<?= $h($lang->requests['ph_add_bp']) ?>" min="0" step="0.1">
                    </div>
                    <span class="st-meta"><?= $h(ags_fmt($lang->requests['lbl_balance'], number_format((float)$CURUSER['seedbonus'], 1))) ?></span>
                <?php else: ?>
                    <span class="st-badge st-soft-success"><i class="fa-solid fa-check"></i><?= $h($lang->requests['txt_you_voted']) ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-primary st-pill" data-bs-toggle="modal" data-bs-target="#fillModal">
                    <i class="fa-solid fa-upload me-1"></i><?= $h($lang->requests['btn_fill']) ?>
                </button>
                <?php if ($isOwner || $is_mod): ?>
                    <form method="post" action="requests.php?action=cancel&amp;rid=<?= $rid ?>" class="d-inline" data-rq-confirm="<?= $h($lang->requests['conf_cancel']) ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <button class="btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-xmark me-1"></i><?= $h($lang->requests['btn_cancel_request']) ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($is_mod): ?>
                    <form method="post" action="requests.php?action=delete&amp;rid=<?= $rid ?>" class="d-inline" data-rq-confirm="<?= $h($lang->requests['conf_delete']) ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <button class="btn btn-sm btn-outline-danger st-pill"><i class="fa-solid fa-trash-can me-1"></i><?= $h($lang->requests['btn_delete']) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Comments -->
    <section class="st-card mb-3" id="rq-comments">
        <div class="st-card__head"><i class="fa-solid fa-comments"></i><?= $h($lang->requests['sec_comments']) ?>
            <span class="st-badge st-soft-muted ms-auto"><?= count($comments) ?></span>
        </div>
        <div id="commentsContainer">
            <?php foreach ($comments as $i => $c):
                $cname = (string)($c['username'] ?? ('#' . (int)$c['user_id'])); ?>
                <div class="rq-comment<?= $i > 0 ? ' border-top' : '' ?>">
                    <?= $rq_avatar($c['avatar'] ?? '', $cname) ?>
                    <div class="flex-grow-1 min-w-0">
                        <div class="rq-comment__head">
                            <a href="<?= $h(get_profile_link((int)$c['user_id'])) ?>"><?= format_name($h($cname), (int)($c['usergroup'] ?? 1)) ?></a>
                            <span class="st-meta"><?= my_datee($dateformat, $c['created_at']) ?> <?= my_datee($timeformat, $c['created_at']) ?></span>
                        </div>
                        <div class="rq-comment__text"><?= $parser->parse_message($c['message'], $parser_options) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$comments): ?>
                <div class="st-empty"><span class="st-ico st-soft-muted"><i class="fa-regular fa-comment"></i></span><div><?= $h($lang->requests['txt_no_comments']) ?></div></div>
            <?php endif; ?>
        </div>
        <?php if ($isOpen): ?>
            <div class="st-toolbar st-toolbar--bottom rq-compose">
                <textarea class="form-control" id="commentText" rows="2" placeholder="<?= $h($lang->requests['ph_comment']) ?>"></textarea>
                <button type="button" class="btn btn-primary st-pill" data-rq-comment="<?= $rid ?>" id="commentBtn" title="<?= $h($lang->requests['aria_send_comment']) ?>" aria-label="<?= $h($lang->requests['aria_send_comment']) ?>"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Fill modal -->
<div class="modal fade" id="fillModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="fa-solid fa-upload me-2"></i><?= $h($lang->requests['pane_fill']) ?></h5>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h($lang->requests['aria_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small"><?= $h($lang->requests['hint_fill']) ?></p>
                <form method="post" action="requests.php?action=fill&amp;rid=<?= $rid ?>">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= $h($lang->requests['lbl_torrent_id']) ?></label>
                        <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="<?= $h($lang->requests['ph_torrent_id']) ?>">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal"><?= $h($lang->requests['btn_modal_cancel']) ?></button>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4"><?= $h($lang->requests['btn_mark_filled']) ?></button>
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
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
<script>
const AGS_LANG = <?= json_encode($ags_js_lang, $flags) ?>;
const RQ_POST_KEY = <?= json_encode((string)$mybb->post_code, $flags) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/requests.js?ver=1"></script>
    <?php
    stdfoot();
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: Create Form
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'create') {
    $editor = insert_bbcode_editor($smilies ?? [], $BASEURL, 'description');
    $selCat = $mybb->get_input('category_id', MyBB::INPUT_INT);
    stdhead(htmlspecialchars($lang->requests['title_create']));
    $rq_assets();
    show_flash();
    ?>
<div class="container-md st-page rq-page py-3">

    <?= $rq_crumbs([[$lang->requests['nav_requests'], 'requests.php'], [$lang->requests['nav_new'], '']]) ?>

    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-plus"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $h($lang->requests['pane_create']) ?></h1>
            <div class="st-meta mt-1"><?= $h($rqL('hint_create', 'Describe what you are looking for. Others can vote and add bonus points to the reward.')) ?></div>
        </div>
        <span class="st-badge st-soft-warning d-none d-md-inline-flex"><i class="fa-solid fa-coins"></i><?= $h(ags_fmt($lang->requests['hint_balance'], number_format((float)$CURUSER['seedbonus'], 1))) ?></span>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="rq-errors mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><?= implode('<br>', array_map($h, $errors)) ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="requests.php?action=do_create" class="st-card">
        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
        <div class="st-card__head"><i class="fa-solid fa-film"></i><?= $h($rqL('sec_what', 'What to find')) ?></div>
        <div class="st-card__body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="rq-title"><?= $h($lang->requests['lbl_title']) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="rq-title" name="title" maxlength="255" required value="<?= $h($mybb->get_input('title')) ?>" placeholder="<?= $h($lang->requests['ph_title']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="rq-imdb"><span class="rq-imdb me-1">IMDb</span><?= $h($rqL('lbl_imdb', 'IMDb link')) ?></label>
                <input type="text" class="form-control" id="rq-imdb" name="imdb" maxlength="255" value="<?= $h($mybb->get_input('imdb')) ?>" placeholder="https://www.imdb.com/title/tt1234567/">
                <div class="form-text"><?= $h($rqL('hint_imdb', 'Optional. With an IMDb link the tracker will offer this request to the uploader of a matching torrent automatically.')) ?></div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="rq-cat"><?= $h($lang->requests['lbl_category']) ?></label>
                    <select class="form-select" id="rq-cat" name="category_id">
                        <option value="0"><?= $h($lang->requests['opt_cat_any']) ?></option>
                        <?php foreach ($cats as $cid => $cname): ?>
                            <option value="<?= (int)$cid ?>"<?= (int)$cid === $selCat ? ' selected' : '' ?>><?= $h($cname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold" for="rq-year"><?= $h($lang->requests['lbl_year']) ?></label>
                    <input type="number" class="form-control" id="rq-year" name="year" min="1900" max="<?= date('Y') + 2 ?>" placeholder="<?= date('Y') ?>" value="<?= $h($mybb->get_input('year')) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-semibold" for="rq-bounty"><?= $h($lang->requests['lbl_bounty']) ?></label>
                    <input type="number" class="form-control" id="rq-bounty" name="bounty" min="0" step="0.1" value="<?= $h($mybb->get_input('bounty') !== '' ? $mybb->get_input('bounty') : '0') ?>">
                </div>
            </div>
        </div>
        <div class="st-card__head rq-head-sep"><i class="fa-solid fa-align-left"></i><?= $h($lang->requests['lbl_description']) ?></div>
        <div class="st-card__body">
            <?= $editor['toolbar'] ?>
            <textarea class="form-control" name="description" id="description" rows="7" placeholder="<?= $h($lang->requests['ph_description']) ?>"><?= $h($mybb->get_input('description')) ?></textarea>
        </div>
        <div class="st-toolbar st-toolbar--bottom justify-content-end">
            <a href="requests.php" class="btn btn-outline-secondary st-pill"><?= $h($lang->requests['btn_form_cancel']) ?></a>
            <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-paper-plane me-1"></i><?= $h($lang->requests['btn_submit']) ?></button>
        </div>
    </form>
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
$filter_mine   = $mybb->get_input('mine', MyBB::INPUT_INT) === 1;
$sort          = in_array($mybb->get_input('sort'), ['votes','bounty','created_at']) ? $mybb->get_input('sort') : 'votes';
$perpage       = 25;
$page          = max(1, $mybb->get_input('page', MyBB::INPUT_INT));

$where = [];
$where_params = [];
if ($filter_status) { $where[] = "r.status = ?"; $where_params[] = $filter_status; }
if ($filter_cat)    { $where[] = "r.category_id = ?"; $where_params[] = $filter_cat; }
if ($filter_mine)   { $where[] = "r.user_id = ?"; $where_params[] = (int)$CURUSER['id']; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->fetch_field(
    $db->sql_query_prepared("SELECT COUNT(*) AS cnt FROM requests r $where_sql", $where_params),
    'cnt'
);
$offset  = ($page - 1) * $perpage;

$requests_q = $db->sql_query_prepared(
    "SELECT r.*, u.username, u.avatar, u.usergroup
     FROM requests r
     LEFT JOIN users u ON u.id = r.user_id
     $where_sql
     ORDER BY r.$sort DESC, r.id DESC
     LIMIT ?, ?",
    [...$where_params, $offset, $perpage]
);

// KPI tiles: one query over the whole table
$kpi = $db->fetch_array($db->sql_query_prepared(
    "SELECT SUM(status = 'open') AS open_n,
            SUM(status = 'filled') AS filled_n,
            COALESCE(SUM(CASE WHEN status = 'open' THEN bounty END), 0) AS pool,
            SUM(user_id = ? AND status = 'open') AS mine_n
     FROM requests",
    [(int)$CURUSER['id']]
)) ?: [];

$qs = static function (array $over = []) use ($filter_status, $filter_cat, $sort, $filter_mine): string {
    $p = array_merge(['status' => $filter_status, 'cat' => $filter_cat ?: '', 'sort' => $sort, 'mine' => $filter_mine ? 1 : ''], $over);
    $p = array_filter($p, static fn($v) => $v !== '' && $v !== null);
    // empty status = "All" must stay in the URL, otherwise the default "open" comes back
    if (($over['status'] ?? $filter_status) === '') $p = ['status' => ''] + $p;
    return 'requests.php?' . http_build_query($p);
};

stdhead(htmlspecialchars($lang->requests['title_list']));
$rq_assets();
show_flash();
?>
<div class="container-md st-page rq-page py-3">

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-list-check"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $h($lang->requests['sec_list']) ?></h1>
            <div class="st-meta mt-1"><?= $h($rqL('hint_list', 'Ask for a release that is missing on the tracker. Vote for others and add bonus points: the uploader who fills a request gets its reward.')) ?></div>
        </div>
        <a href="requests.php?action=create" class="btn btn-primary st-pill"><i class="fa-solid fa-plus me-1"></i><?= $h($lang->requests['btn_new']) ?></a>
    </header>

    <!-- KPI tiles -->
    <div class="rq-kpis mb-3">
        <?= $rq_kpi('fa-circle-dot', 'success', number_format((int)($kpi['open_n'] ?? 0)), $rqL('kpi_open', 'Open requests')) ?>
        <?= $rq_kpi('fa-circle-check', 'primary', number_format((int)($kpi['filled_n'] ?? 0)), $rqL('kpi_filled', 'Filled')) ?>
        <?= $rq_kpi('fa-coins', 'warning', number_format((float)($kpi['pool'] ?? 0), 1) . ' <small>' . $h($lang->requests['unit_bp']) . '</small>', $rqL('kpi_pool', 'Bounty waiting')) ?>
        <a class="st-kpi st-kpi--muted" href="<?= $h($qs(['mine' => $filter_mine ? '' : 1, 'page' => ''])) ?>">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-user"></i></span>
            <div class="min-w-0"><div class="st-kpi__val"><?= number_format((int)($kpi['mine_n'] ?? 0)) ?></div><div class="st-kpi__lbl"><?= $h($rqL('kpi_mine', 'My open requests')) ?></div></div>
            <i class="fa-solid fa-user st-kpi__bg" aria-hidden="true"></i>
        </a>
    </div>

    <!-- Filters -->
    <div class="st-card mb-3">
        <div class="st-toolbar rq-filters">
            <nav class="rq-pills" aria-label="<?= $h($rqL('aria_status', 'Status')) ?>">
                <?php foreach (['open' => ['fa-circle-dot', $lang->requests['opt_status_open']], 'filled' => ['fa-circle-check', $lang->requests['opt_status_filled']], 'cancelled' => ['fa-ban', $lang->requests['opt_status_cancelled']], '' => ['fa-layer-group', $lang->requests['opt_status_all']]] as $val => [$ico, $label]): ?>
                    <a href="<?= $h($qs(['status' => $val, 'page' => ''])) ?>" class="<?= $filter_status === $val ? 'is-active' : '' ?>"><i class="fa-solid <?= $ico ?>"></i><?= $h($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="get" action="requests.php" class="rq-catform">
                    <input type="hidden" name="status" value="<?= $h($filter_status) ?>">
                    <input type="hidden" name="sort" value="<?= $h($sort) ?>">
                    <?php if ($filter_mine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
                    <select name="cat" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="<?= $h($lang->requests['lbl_category']) ?>">
                        <option value="0"><?= $h($lang->requests['lbl_category']) ?>: <?= $h($lang->requests['opt_cat_any']) ?></option>
                        <?php foreach ($cats as $cid => $cname): ?>
                            <option value="<?= (int)$cid ?>"<?= (int)$cid === $filter_cat ? ' selected' : '' ?>><?= $h($cname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <nav class="rq-pills rq-pills--sm" aria-label="<?= $h($rqL('aria_sort', 'Sort')) ?>">
                    <?php foreach (['votes' => ['fa-thumbs-up', $rqL('sort_votes', 'Votes')], 'bounty' => ['fa-coins', $rqL('sort_bounty', 'Bounty')], 'created_at' => ['fa-clock', $rqL('sort_new', 'New')]] as $val => [$ico, $label]): ?>
                        <a href="<?= $h($qs(['sort' => $val, 'page' => ''])) ?>" class="<?= $sort === $val ? 'is-active' : '' ?>"><i class="fa-solid <?= $ico ?>"></i><?= $h($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- List -->
    <section class="st-card mb-3">
        <div class="st-card__head"><i class="fa-solid fa-list"></i><?= $h($lang->requests['sec_list']) ?>
            <span class="st-meta ms-auto"><?= $h(ags_fmt($lang->requests['txt_total'], number_format($total))) ?><?= $filter_mine ? ' &middot; ' . $h($rqL('kpi_mine', 'My open requests')) : '' ?></span>
        </div>
        <?php
        $count = 0;
        while ($req = $db->fetch_array($requests_q)):
            $count++;
            $bounty = (float)$req['bounty'];
        ?>
            <a class="rq-row rq-row--<?= $h((string)$req['status']) ?>" href="requests.php?action=view&amp;rid=<?= (int)$req['id'] ?>">
                <div class="rq-votes"><strong><?= (int)$req['votes'] ?></strong><span><?= $h($lang->requests['lbl_votes']) ?></span></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="rq-row__title">
                        <?= $rq_status((string)$req['status']) ?>
                        <span class="rq-row__name"><?= $h($req['title']) ?></span>
                        <?php if ($req['year']): ?><span class="rq-year">(<?= (int)$req['year'] ?>)</span><?php endif; ?>
                        <?php if (!empty($req['imdb_id'])): ?><span class="rq-imdb" title="<?= $h((string)$req['imdb_id']) ?>">IMDb</span><?php endif; ?>
                    </div>
                    <div class="rq-row__meta">
                        <?php if (isset($cats[$req['category_id']])): ?><span><i class="fa-solid fa-tag"></i><?= $h($cats[$req['category_id']]) ?></span><?php endif; ?>
                        <span><i class="fa-solid fa-user"></i><?= $h((string)$req['username']) ?></span>
                        <span><i class="fa-regular fa-clock"></i><?= $h(ags_fmt($lang->requests['txt_ago'], mkprettytime(TIMENOW - (int)$req['created_at']))) ?></span>
                    </div>
                </div>
                <?php if ($bounty > 0): ?>
                    <span class="rq-bounty"><i class="fa-solid fa-coins"></i><?= number_format($bounty, 1) ?> <?= $h($lang->requests['unit_bp']) ?></span>
                <?php endif; ?>
                <i class="fa-solid fa-chevron-right rq-row__go" aria-hidden="true"></i>
            </a>
        <?php endwhile; ?>

        <?php if ($count === 0): ?>
            <div class="st-empty">
                <span class="st-ico st-soft-muted"><i class="fa-solid fa-inbox"></i></span>
                <div class="mb-2"><?= $h($lang->requests['txt_empty']) ?></div>
                <a href="requests.php?action=create" class="btn btn-primary btn-sm st-pill"><i class="fa-solid fa-plus me-1"></i><?= $h($lang->requests['btn_create_first']) ?></a>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($total > $perpage): ?>
        <div class="st-pager mb-3 d-flex justify-content-center">
            <?= multipage($total, $perpage, $page, str_replace('%7Bpage%7D', '{page}', $qs(['page' => '{page}']))) ?>
        </div>
    <?php endif; ?>
</div>
<?php
stdfoot();
