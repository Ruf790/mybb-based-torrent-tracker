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
        // Message as text, not HTML (it may contain an offer title)
        echo '<script>document.addEventListener("DOMContentLoaded", function(){ var d = document.createElement("div"); d.textContent = '
           . json_encode((string)$f['msg'], $flags) . '; showToast(d.innerHTML, ' . json_encode($type) . '); });</script>';
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

    // The offer must exist; title and author are needed for the bell below
    $offRow = $db->fetch_array($db->sql_query_prepared("SELECT id, title, user_id FROM offers WHERE id = ?", [$oid]));
    if (!$offRow) {
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

    // Bell: @mentions, then the offer's author (each person once).
    // The comment is already saved: a notify error must not turn into a JSON error (the user would post it again)
    try {
        require_once INC_PATH . '/functions_notify.php';
        $nLink = 'offers.php?action=view&oid=' . $oid;
        $nDone = notify_mentions($message, (int)$CURUSER['id'], $oid, (string)$offRow['title'], $nLink);
        if (!in_array((int)$offRow['user_id'], $nDone, true)) {
            notify((int)$offRow['user_id'], 'offer_comment', (int)$CURUSER['id'], $oid, [(string)$offRow['title']], $nLink);
        }
    } catch (\Throwable $e) {
        error_log('notify (offer comment): ' . $e->getMessage());
    }

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
        require_once INC_PATH . '/functions_chat.php';
        chat_bot_post('offer', ['id' => (int)$id]);
        $_SESSION['flash'] = ['msg' => $lang->offers['flash_created'], 'type' => 'success'];
        header('Location: offers.php?action=view&oid=' . $id);
        exit();
    }
    $action = 'create';   // show the form again with the errors and the typed values
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

        // Bell next to the PM
        try {
            require_once INC_PATH . '/functions_notify.php';
            notify((int)$v['user_id'], 'offer_uploaded', (int)$CURUSER['id'], $oid, [(string)$offer['title']], 'details.php?id=' . $torrent_id);
        } catch (\Throwable $e) {
            error_log('notify (offer uploaded): ' . $e->getMessage());
        }
    }
    require_once INC_PATH . '/functions_chat.php';
    chat_bot_post('offer_uploaded', ['id' => (int)$oid, 'tid' => (int)$torrent_id, 'by' => (int)$CURUSER['id']]);
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
// Shared view helpers (new style: st-ui.css + requests.css layout, offers.css accent)
// ═══════════════════════════════════════════════════════════════════════════════
$ofL = static fn(string $k, string $fb): string => (string)($lang->offers[$k] ?? $fb);
$h   = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

/** CSS/JS shared by all offer pages (after stdhead()). Layout = requests.css (.rq-page), green accent = offers.css */
$of_assets = static function () use ($BASEURL): void {
    echo '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/st-ui.css?ver=7">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/requests.css?ver=1">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/offers.css?ver=1">'
       . '<link rel="stylesheet" href="' . $BASEURL . '/include/templates/default/style/sweetalert2.min.css">'
       . '<script src="' . $BASEURL . '/scripts/sweetalert2.min.js"></script>'
       . '<script src="' . $BASEURL . '/scripts/swal-notify.js?ver=2"></script>';
};

$of_status = static function (string $status) use ($lang, $h): string {
    return match ($status) {
        'open'      => '<span class="st-badge st-soft-success"><i class="fa-solid fa-circle-dot"></i>' . $h($lang->offers['status_open']) . '</span>',
        'uploaded'  => '<span class="st-badge st-soft-primary"><i class="fa-solid fa-upload"></i>' . $h($lang->offers['status_uploaded']) . '</span>',
        'cancelled' => '<span class="st-badge st-soft-muted"><i class="fa-solid fa-ban"></i>' . $h($lang->offers['status_cancelled']) . '</span>',
        default     => '',
    };
};

$of_avatar = static function (?string $avatar, string $name, int $size = 36) use ($BASEURL, $h): string {
    $avatar = trim((string)$avatar);
    if ($avatar === '') {
        return '<span class="rq-ava rq-ava--letter" style="width:' . $size . 'px;height:' . $size . 'px">' . $h(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>';
    }
    $src = preg_match('#^https?://#i', $avatar) ? $avatar : $BASEURL . '/' . ltrim($avatar, '/');
    return '<img class="rq-ava" src="' . $h($src) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy">';
};

$of_kpi = static function (string $icon, string $tone, string $value, string $label) use ($h): string {
    return '<div class="st-kpi st-kpi--muted">'
         . '<span class="st-ico st-soft-' . $tone . '"><i class="fa-solid ' . $icon . '"></i></span>'
         . '<div class="min-w-0"><div class="st-kpi__val">' . $value . '</div><div class="st-kpi__lbl">' . $h($label) . '</div></div>'
         . '<i class="fa-solid ' . $icon . ' st-kpi__bg" aria-hidden="true"></i></div>';
};

$of_crumbs = static function (array $items) use ($BASEURL, $lang, $h): string {
    $out = '<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb rq-crumbs mb-0">'
         . '<li class="breadcrumb-item"><a href="' . $BASEURL . '/index2.php" title="' . $h($lang->offers['nav_home']) . '" aria-label="' . $h($lang->offers['nav_home']) . '"><i class="fa-solid fa-house"></i></a></li>';
    foreach ($items as [$label, $url]) {
        $out .= $url !== ''
            ? '<li class="breadcrumb-item"><a href="' . $h($url) . '">' . $h($label) . '</a></li>'
            : '<li class="breadcrumb-item active text-truncate" aria-current="page">' . $h($label) . '</li>';
    }
    return $out . '</ol></nav>';
};

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW: Single Offer
// ═══════════════════════════════════════════════════════════════════════════════
if ($action === 'view' && $oid) {
    $offer = $db->fetch_array($db->sql_query_prepared(
        "SELECT o.*, u.username, u.avatar, u.usergroup
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
        "SELECT oc.*, u.username, u.avatar, u.usergroup
         FROM offer_comments oc
         LEFT JOIN users u ON u.id = oc.user_id
         WHERE oc.offer_id = ?
         ORDER BY oc.created_at ASC",
        [$oid]
    );
    $comments = [];
    while ($c = $db->fetch_array($comments_q)) $comments[] = $c;

    $isOpen  = $offer['status'] === 'open';
    $isOwner = (int)$offer['user_id'] === (int)$CURUSER['id'];
    $tone    = match ($offer['status']) { 'open' => 'success', 'uploaded' => 'primary', default => 'muted' };
    $icon    = match ($offer['status']) { 'open' => 'fa-gift', 'uploaded' => 'fa-circle-check', default => 'fa-ban' };
    $author  = $offer['username'] !== null
        ? '<a href="' . $h(get_profile_link((int)$offer['user_id'])) . '">' . format_name($h($offer['username']), (int)$offer['usergroup']) . '</a>'
        : '#' . (int)$offer['user_id'];

    stdhead(htmlspecialchars(ags_fmt($lang->offers['title_view'], $offer['title'])));
    $of_assets();
    show_flash();
    ?>
<div class="container-md st-page rq-page of-page py-3">

    <?= $of_crumbs([[$lang->offers['nav_offers'], 'offers.php'], [(string)$offer['title'], '']]) ?>

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-<?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1"><?= $of_status((string)$offer['status']) ?>
                <?php if ($offer['category_id'] && isset($cats[$offer['category_id']])): ?>
                    <span class="st-badge st-soft-muted"><i class="fa-solid fa-tag"></i><?= $h($cats[$offer['category_id']]) ?></span>
                <?php endif; ?>
            </div>
            <h1 class="st-title"><?= $h($offer['title']) ?><?= $offer['year'] ? ' <span class="rq-year">(' . (int)$offer['year'] . ')</span>' : '' ?></h1>
            <div class="st-head__meta">
                <span><i class="fa-solid fa-user"></i><?= $h($lang->offers['lbl_offered_by']) ?> <?= $author ?></span>
                <span><i class="fa-regular fa-calendar"></i><?= my_datee($dateformat, $offer['created_at']) ?> <?= my_datee($timeformat, $offer['created_at']) ?></span>
            </div>
        </div>
    </header>

    <!-- KPI tiles -->
    <div class="rq-kpis rq-kpis--3 mb-3">
        <?= $of_kpi('fa-hand', 'success', '<span id="want-count-sidebar">' . number_format((int)$offer['requests']) . '</span>', $lang->offers['lbl_people_want']) ?>
        <?= $of_kpi('fa-comments', 'primary', number_format(count($comments)), $lang->offers['sec_comments']) ?>
        <?= $of_kpi('fa-clock', 'info', $h(mkprettytime(TIMENOW - (int)$offer['created_at'])), $ofL('kpi_age', 'Offer age')) ?>
    </div>

    <?php if ($offer['status'] === 'uploaded' && $offer['torrent_id']): ?>
        <div class="rq-filled mb-3">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-circle-check"></i></span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= $h($lang->offers['txt_uploaded']) ?></div>
                <?php if (!empty($offer['uploaded_at'])): ?><div class="st-meta"><?= my_datee($dateformat, $offer['uploaded_at']) ?></div><?php endif; ?>
            </div>
            <a href="<?= $h(get_torrent_link((int)$offer['torrent_id'])) ?>" class="btn btn-sm btn-success st-pill"><i class="fa-solid fa-download me-1"></i><?= $h($lang->offers['lnk_download']) ?></a>
        </div>
    <?php endif; ?>

    <!-- Description + actions -->
    <section class="st-card mb-3">
        <div class="st-card__head"><i class="fa-solid fa-align-left"></i><?= $h($lang->offers['lbl_description']) ?></div>
        <div class="st-card__body">
            <?php if (trim((string)$offer['description']) !== ''): ?>
                <div class="rq-desc"><?= $parser->parse_message($offer['description'], $parser_options) ?></div>
            <?php else: ?>
                <div class="st-meta"><?= $h($ofL('txt_no_description', 'No description.')) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($isOpen): ?>
        <div class="st-toolbar st-toolbar--bottom rq-actions">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if (!$my_want && !$isOwner): ?>
                    <button type="button" class="btn btn-sm btn-success st-pill" data-of-want="<?= $oid ?>" id="wantBtn">
                        <i class="fa-solid fa-hand me-1"></i><?= $h($lang->offers['btn_want']) ?> (<span id="want-count"><?= (int)$offer['requests'] ?></span>)
                    </button>
                <?php else: ?>
                    <span class="st-badge st-soft-success"><i class="fa-solid fa-check"></i><?= $h($isOwner ? $lang->offers['txt_your_offer'] : $lang->offers['txt_you_requested']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isOwner || $is_mod): ?>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-primary st-pill" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fa-solid fa-upload me-1"></i><?= $h($lang->offers['btn_uploaded']) ?>
                </button>
                <form method="post" action="offers.php?action=cancel&amp;oid=<?= $oid ?>" class="d-inline" data-rq-confirm="<?= $h($lang->offers['conf_cancel']) ?>">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <button class="btn btn-sm btn-outline-secondary st-pill"><i class="fa-solid fa-xmark me-1"></i><?= $h($lang->offers['btn_cancel_offer']) ?></button>
                </form>
                <?php if ($is_mod): ?>
                    <form method="post" action="offers.php?action=delete&amp;oid=<?= $oid ?>" class="d-inline" data-rq-confirm="<?= $h($lang->offers['conf_delete']) ?>">
                        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                        <button class="btn btn-sm btn-outline-danger st-pill"><i class="fa-solid fa-trash-can me-1"></i><?= $h($lang->offers['btn_delete']) ?></button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Comments -->
    <section class="st-card mb-3" id="rq-comments">
        <div class="st-card__head"><i class="fa-solid fa-comments"></i><?= $h($lang->offers['sec_comments']) ?>
            <span class="st-badge st-soft-muted ms-auto"><?= count($comments) ?></span>
        </div>
        <div id="commentsContainer">
            <?php foreach ($comments as $i => $c):
                $cname = (string)($c['username'] ?? ('#' . (int)$c['user_id'])); ?>
                <div class="rq-comment<?= $i > 0 ? ' border-top' : '' ?>">
                    <?= $of_avatar($c['avatar'] ?? '', $cname) ?>
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
                <div class="st-empty"><span class="st-ico st-soft-muted"><i class="fa-regular fa-comment"></i></span><div><?= $h($lang->offers['txt_no_comments']) ?></div></div>
            <?php endif; ?>
        </div>
        <?php if ($isOpen): ?>
            <div class="st-toolbar st-toolbar--bottom rq-compose">
                <textarea class="form-control" id="commentText" rows="2" placeholder="<?= $h($lang->offers['ph_comment']) ?>"></textarea>
                <button type="button" class="btn btn-success st-pill" data-of-comment="<?= $oid ?>" id="commentBtn" title="<?= $h($lang->offers['aria_send_comment']) ?>" aria-label="<?= $h($lang->offers['aria_send_comment']) ?>"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Upload modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="fa-solid fa-upload me-2"></i><?= $h($lang->offers['pane_upload']) ?></h5>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="<?= $h($lang->offers['aria_close']) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small"><?= $h($lang->offers['hint_upload']) ?></p>
                <form method="post" action="offers.php?action=upload&amp;oid=<?= $oid ?>">
                    <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?= $h($lang->offers['lbl_torrent_id']) ?></label>
                        <input type="number" class="form-control rounded-3" name="torrent_id" required min="1" placeholder="<?= $h($lang->offers['ph_torrent_id']) ?>">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal"><?= $h($lang->offers['btn_modal_cancel']) ?></button>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4"><?= $h($lang->offers['btn_confirm_upload']) ?></button>
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
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
<script>
const AGS_LANG = <?= json_encode($ags_js_lang, $flags) ?>;
const OF_POST_KEY = <?= json_encode((string)$mybb->post_code, $flags) ?>;
</script>
<script src="<?= $BASEURL ?>/scripts/offers.js?ver=1"></script>
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
    stdhead(htmlspecialchars($lang->offers['title_create']));
    $of_assets();
    show_flash();
    ?>
<div class="container-md st-page rq-page of-page py-3">

    <?= $of_crumbs([[$lang->offers['nav_offers'], 'offers.php'], [$lang->offers['nav_new'], '']]) ?>

    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-success"><i class="fa-solid fa-gift"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $h($lang->offers['pane_create']) ?></h1>
            <div class="st-meta mt-1"><?= $h($lang->offers['hint_create']) ?></div>
        </div>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="rq-errors mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><?= implode('<br>', array_map($h, $errors)) ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="offers.php?action=do_create" class="st-card">
        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">
        <div class="st-card__head"><i class="fa-solid fa-film"></i><?= $h($ofL('sec_what', 'What you offer')) ?></div>
        <div class="st-card__body">
            <div class="mb-3">
                <label class="form-label fw-semibold" for="of-title"><?= $h($lang->offers['lbl_title']) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="of-title" name="title" maxlength="255" required value="<?= $h($mybb->get_input('title')) ?>" placeholder="<?= $h($lang->offers['ph_title']) ?>">
            </div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold" for="of-cat"><?= $h($lang->offers['lbl_category']) ?></label>
                    <select class="form-select" id="of-cat" name="category_id">
                        <option value="0"><?= $h($lang->offers['opt_cat_any']) ?></option>
                        <?php foreach ($cats as $cid => $cname): ?>
                            <option value="<?= (int)$cid ?>"<?= (int)$cid === $selCat ? ' selected' : '' ?>><?= $h($cname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="of-year"><?= $h($lang->offers['lbl_year']) ?></label>
                    <input type="number" class="form-control" id="of-year" name="year" min="1900" max="<?= date('Y') + 2 ?>" placeholder="<?= date('Y') ?>" value="<?= $h($mybb->get_input('year')) ?>">
                </div>
            </div>
        </div>
        <div class="st-card__head rq-head-sep"><i class="fa-solid fa-align-left"></i><?= $h($lang->offers['lbl_description']) ?></div>
        <div class="st-card__body">
            <?= $editor['toolbar'] ?>
            <textarea class="form-control" name="description" id="description" rows="7" placeholder="<?= $h($lang->offers['ph_description']) ?>"><?= $h($mybb->get_input('description')) ?></textarea>
        </div>
        <div class="st-toolbar st-toolbar--bottom justify-content-end">
            <a href="offers.php" class="btn btn-outline-secondary st-pill"><?= $h($lang->offers['btn_form_cancel']) ?></a>
            <button type="submit" class="btn btn-success st-pill"><i class="fa-solid fa-gift me-1"></i><?= $h($lang->offers['btn_submit']) ?></button>
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
$filter_status = in_array($mybb->get_input('status'), ['open','uploaded','cancelled','']) ? $mybb->get_input('status') : 'open';
$filter_cat    = $mybb->get_input('cat', MyBB::INPUT_INT);
$filter_mine   = $mybb->get_input('mine', MyBB::INPUT_INT) === 1;
$sort          = in_array($mybb->get_input('sort'), ['requests','created_at']) ? $mybb->get_input('sort') : 'requests';
$perpage       = 25;
$page          = max(1, $mybb->get_input('page', MyBB::INPUT_INT));

$where = [];
$where_params = [];
if ($filter_status) { $where[] = "o.status = ?"; $where_params[] = $filter_status; }
if ($filter_cat)    { $where[] = "o.category_id = ?"; $where_params[] = $filter_cat; }
if ($filter_mine)   { $where[] = "o.user_id = ?"; $where_params[] = (int)$CURUSER['id']; }
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
     ORDER BY o.$sort DESC, o.id DESC
     LIMIT ?, ?",
    [...$where_params, $offset, $perpage]
);

// KPI tiles: one query over the whole table
$kpi = $db->fetch_array($db->sql_query_prepared(
    "SELECT SUM(status = 'open') AS open_n,
            SUM(status = 'uploaded') AS uploaded_n,
            COALESCE(SUM(CASE WHEN status = 'open' THEN requests END), 0) AS wants,
            SUM(user_id = ? AND status = 'open') AS mine_n
     FROM offers",
    [(int)$CURUSER['id']]
)) ?: [];

$qs = static function (array $over = []) use ($filter_status, $filter_cat, $sort, $filter_mine): string {
    $p = array_merge(['status' => $filter_status, 'cat' => $filter_cat ?: '', 'sort' => $sort, 'mine' => $filter_mine ? 1 : ''], $over);
    $p = array_filter($p, static fn($v) => $v !== '' && $v !== null);
    // empty status = "All" must stay in the URL, otherwise the default "open" comes back
    if (($over['status'] ?? $filter_status) === '') $p = ['status' => ''] + $p;
    return 'offers.php?' . http_build_query($p);
};

stdhead(htmlspecialchars($lang->offers['title_list']));
$of_assets();
show_flash();
?>
<div class="container-md st-page rq-page of-page py-3">

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-success"><i class="fa-solid fa-gift"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $h($lang->offers['sec_list']) ?></h1>
            <div class="st-meta mt-1"><?= $h($ofL('hint_list', 'Offer a release you can upload. If people want it, upload it and mark the offer as uploaded: everyone who asked gets a message.')) ?></div>
        </div>
        <a href="offers.php?action=create" class="btn btn-success st-pill"><i class="fa-solid fa-plus me-1"></i><?= $h($lang->offers['btn_new']) ?></a>
    </header>

    <!-- KPI tiles -->
    <div class="rq-kpis mb-3">
        <?= $of_kpi('fa-gift', 'success', number_format((int)($kpi['open_n'] ?? 0)), $ofL('kpi_open', 'Open offers')) ?>
        <?= $of_kpi('fa-upload', 'primary', number_format((int)($kpi['uploaded_n'] ?? 0)), $ofL('kpi_uploaded', 'Uploaded')) ?>
        <?= $of_kpi('fa-hand', 'warning', number_format((int)($kpi['wants'] ?? 0)), $ofL('kpi_wants', 'People waiting')) ?>
        <a class="st-kpi st-kpi--muted" href="<?= $h($qs(['mine' => $filter_mine ? '' : 1, 'page' => ''])) ?>">
            <span class="st-ico st-soft-info"><i class="fa-solid fa-user"></i></span>
            <div class="min-w-0"><div class="st-kpi__val"><?= number_format((int)($kpi['mine_n'] ?? 0)) ?></div><div class="st-kpi__lbl"><?= $h($ofL('kpi_mine', 'My open offers')) ?></div></div>
            <i class="fa-solid fa-user st-kpi__bg" aria-hidden="true"></i>
        </a>
    </div>

    <!-- Filters -->
    <div class="st-card mb-3">
        <div class="st-toolbar rq-filters">
            <nav class="rq-pills" aria-label="<?= $h($ofL('aria_status', 'Status')) ?>">
                <?php foreach (['open' => ['fa-circle-dot', $lang->offers['opt_status_open']], 'uploaded' => ['fa-upload', $lang->offers['opt_status_uploaded']], 'cancelled' => ['fa-ban', $lang->offers['opt_status_cancelled']], '' => ['fa-layer-group', $lang->offers['opt_status_all']]] as $val => [$ico, $label]): ?>
                    <a href="<?= $h($qs(['status' => $val, 'page' => ''])) ?>" class="<?= $filter_status === $val ? 'is-active' : '' ?>"><i class="fa-solid <?= $ico ?>"></i><?= $h($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="get" action="offers.php" class="rq-catform">
                    <input type="hidden" name="status" value="<?= $h($filter_status) ?>">
                    <input type="hidden" name="sort" value="<?= $h($sort) ?>">
                    <?php if ($filter_mine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
                    <select name="cat" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="<?= $h($lang->offers['lbl_category']) ?>">
                        <option value="0"><?= $h($lang->offers['lbl_category']) ?>: <?= $h($lang->offers['opt_cat_any']) ?></option>
                        <?php foreach ($cats as $cid => $cname): ?>
                            <option value="<?= (int)$cid ?>"<?= (int)$cid === $filter_cat ? ' selected' : '' ?>><?= $h($cname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <nav class="rq-pills rq-pills--sm" aria-label="<?= $h($ofL('aria_sort', 'Sort')) ?>">
                    <?php foreach (['requests' => ['fa-hand', $ofL('sort_wants', 'Most wanted')], 'created_at' => ['fa-clock', $ofL('sort_new', 'New')]] as $val => [$ico, $label]): ?>
                        <a href="<?= $h($qs(['sort' => $val, 'page' => ''])) ?>" class="<?= $sort === $val ? 'is-active' : '' ?>"><i class="fa-solid <?= $ico ?>"></i><?= $h($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- List -->
    <section class="st-card mb-3">
        <div class="st-card__head"><i class="fa-solid fa-list"></i><?= $h($lang->offers['sec_list']) ?>
            <span class="st-meta ms-auto"><?= $h(ags_fmt($lang->offers['txt_total'], number_format($total))) ?><?= $filter_mine ? ' &middot; ' . $h($ofL('kpi_mine', 'My open offers')) : '' ?></span>
        </div>
        <?php
        $count = 0;
        while ($offer = $db->fetch_array($offers_q)):
            $count++;
        ?>
            <a class="rq-row rq-row--<?= $h((string)$offer['status']) ?>" href="offers.php?action=view&amp;oid=<?= (int)$offer['id'] ?>">
                <div class="rq-votes of-wants"><strong><?= (int)$offer['requests'] ?></strong><span><?= $h($lang->offers['lbl_wants']) ?></span></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="rq-row__title">
                        <?= $of_status((string)$offer['status']) ?>
                        <span class="rq-row__name"><?= $h($offer['title']) ?></span>
                        <?php if ($offer['year']): ?><span class="rq-year">(<?= (int)$offer['year'] ?>)</span><?php endif; ?>
                    </div>
                    <div class="rq-row__meta">
                        <?php if (isset($cats[$offer['category_id']])): ?><span><i class="fa-solid fa-tag"></i><?= $h($cats[$offer['category_id']]) ?></span><?php endif; ?>
                        <span><i class="fa-solid fa-user"></i><?= $h((string)$offer['username']) ?></span>
                        <span><i class="fa-regular fa-clock"></i><?= $h(ags_fmt($lang->offers['txt_ago'], mkprettytime(TIMENOW - (int)$offer['created_at']))) ?></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right rq-row__go" aria-hidden="true"></i>
            </a>
        <?php endwhile; ?>

        <?php if ($count === 0): ?>
            <div class="st-empty">
                <span class="st-ico st-soft-muted"><i class="fa-solid fa-gift"></i></span>
                <div class="mb-2"><?= $h($lang->offers['txt_empty']) ?></div>
                <a href="offers.php?action=create" class="btn btn-success btn-sm st-pill"><i class="fa-solid fa-plus me-1"></i><?= $h($lang->offers['btn_create_first']) ?></a>
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
