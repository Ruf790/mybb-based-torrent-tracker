<?php
declare(strict_types=1);

/**
 * Chat archive for staff:
 *   - Messages: search every public chat message (deleted ones too) by user, text and date.
 *     Private dialogs are never listed here.
 *   - Reports (?view=reports): private messages somebody reported, each with a few messages
 *     around it from the same dialog. Close / delete the message / ban the author (POST + PRG).
 */
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'chat_archive.php');
define('SCRIPTNAME', 'chat_archive.php');

require_once 'global.php';

$lang->load('chat');
require_once INC_PATH . '/functions_chat.php';
require_once INC_PATH . '/functions_multipage.php';

if (empty($CURUSER['id']) || !chat_is_mod()) {
    print_no_permission();
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$t = static fn(string $key, string $fallback, string|int ...$args): string => $h(chat_t($key, $fallback, ...$args));

// ── Reports: actions (POST → redirect, so F5 never repeats one) ─────────────
$view = ($_GET['view'] ?? '') === 'reports' ? 'reports' : 'messages';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $back = 'chat_archive.php?view=reports';
    if (!verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        header('Location: ' . $back . '&done=token');
        exit;
    }
    $msgId = (int)($_POST['msg_id'] ?? 0);
    $act   = (string)($_POST['act'] ?? '');
    $msg   = $msgId > 0 ? chat_row($msgId) : null;
    $myId  = (int)$CURUSER['id'];
    $done  = 'none';

    // Only a reported message can be acted on from here
    $rq = $db->sql_query_prepared('SELECT 1 FROM chat_reports WHERE msg_id = ? LIMIT 1', [$msgId]);
    if ($msg && $rq && $db->num_rows($rq) > 0) {
        $closeAll = static function () use ($db, $msgId, $myId): void {
            $db->sql_query_prepared('UPDATE chat_reports SET handled_by = ?, handled_at = ? WHERE msg_id = ? AND handled_at = 0', [$myId, TIMENOW, $msgId]);
        };
        $author = (string)($msg['username'] ?? ('#' . $msg['uid']));

        if ($act === 'close') {
            $closeAll();
            write_log("[Chat] report on private message #{$msgId} of {$author} closed by {$CURUSER['username']}");
            $done = 'closed';
        } elseif ($act === 'delete') {
            if ((int)$msg['deleted'] === 0) {
                $db->sql_query_prepared('UPDATE chat_messages SET deleted = 1, edited_at = ?, edited_by = ? WHERE id = ?', [TIMENOW, $myId, $msgId]);
            }
            $closeAll();
            chat_delete_uploads([$msgId]);       // the pictures of the message go with it
            write_log("[Chat] reported private message #{$msgId} of {$author} deleted by {$CURUSER['username']}: " . mb_strimwidth((string)$msg['message'], 0, 120, '…'));
            $done = 'deleted';
        } elseif ($act === 'ban') {
            $target = (int)$msg['uid'];
            if ($target === $myId || !chat_can_ban((int)($CURUSER['usergroup'] ?? 0), (int)($msg['usergroup'] ?? 2))) {
                $done = 'cantban';
            } else {
                $minutes = max(0, min(525600, (int)($_POST['minutes'] ?? 1440)));
                $until   = $minutes === 0 ? 0 : TIMENOW + $minutes * 60;
                $reason  = mb_substr(chat_t('rep_ban_reason', 'Reported private message #{1}', $msgId), 0, 255);
                $db->sql_query_prepared(
                    'INSERT INTO chat_bans (uid, until, reason, banned_by, added) VALUES (?, ?, ?, ?, ?) AS new
                     ON DUPLICATE KEY UPDATE until = new.until, reason = new.reason, banned_by = new.banned_by, added = new.added',
                    [$target, $until, $reason, $myId, TIMENOW]
                );
                $closeAll();
                write_log("[Chat] {$author} banned by {$CURUSER['username']} " . ($minutes === 0 ? 'permanently' : "for {$minutes} min") . " for reported private message #{$msgId}");
                $done = 'banned';
            }
        }
    }
    header('Location: ' . $back . '&done=' . $done);
    exit;
}

// ── Filters (GET) ───────────────────────────────────────────────────────────
$isDate  = static fn(string $d): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
$filters = [
    'user'    => mb_substr(trim((string)($_GET['user'] ?? '')), 0, 64),
    'q'       => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 200),
    'from'    => $isDate((string)($_GET['from'] ?? '')),
    'to'      => $isDate((string)($_GET['to'] ?? '')),
    'deleted' => !empty($_GET['deleted']) ? 1 : 0,
];
$active = $filters['user'] !== '' || $filters['q'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['deleted'];

$stats   = chat_stats();
$total   = $view === 'messages' ? chat_search_count($filters) : 0;
$perpage = CHAT_ARCHIVE_PERPAGE;
$pages   = max(1, (int)ceil($total / $perpage));
$page    = min(max(1, $mybb->get_input('page', MyBB::INPUT_INT)), $pages);
$rows    = $total > 0 ? chat_search($filters, $perpage, ($page - 1) * $perpage) : [];

$query     = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== 0));
$multipage = multipage($total, $perpage, $page, 'chat_archive.php?' . ($query !== '' ? $query . '&' : '') . 'page={page}');

$kpis = [
    ['fa-comments',   'primary', chat_t('kpi_total', 'Messages'),        $stats['total']],
    ['fa-calendar-day', 'success', chat_t('kpi_today', 'Today'),         $stats['today']],
    ['fa-user-slash', 'danger',  chat_t('kpi_bans', 'Active bans'),      $stats['bans']],
    ['fa-flag',       'warning', chat_t('kpi_reports', 'Open reports'),  $stats['reports']],
];

// ── Reports tab ──
$repState = ($_GET['state'] ?? '') === 'done' ? 'done' : 'open';
$repTotal = $view === 'reports' ? chat_reports_count($repState === 'open') : 0;
$repPages = max(1, (int)ceil($repTotal / 20));
$repPage  = min(max(1, $mybb->get_input('page', MyBB::INPUT_INT)), $repPages);
$reports  = $view === 'reports' && $repTotal > 0 ? chat_reports_list($repState === 'open', 20, ($repPage - 1) * 20) : [];
$repMulti = multipage($repTotal, 20, $repPage, 'chat_archive.php?view=reports&state=' . $repState . '&page={page}');
$doneMsg  = match ((string)($_GET['done'] ?? '')) {
    'closed'  => ['success', chat_t('flash_rep_closed', 'The report is closed.')],
    'deleted' => ['success', chat_t('flash_rep_deleted', 'The message is deleted and the report closed.')],
    'banned'  => ['success', chat_t('flash_rep_banned', 'The author is banned from the chat and the report closed.')],
    'cantban' => ['danger',  chat_t('err_ban_staff', 'You cannot ban a member of staff of your rank or higher.')],
    'token'   => ['danger',  chat_t('err_token', 'Security token expired. Reload the page.')],
    'none'    => ['warning', chat_t('flash_rep_none', 'The report was not found (maybe somebody already handled it).')],
    default   => null,
};
$postKey = function_exists('generate_post_check') ? (string)generate_post_check() : (string)($mybb->post_code ?? '');

stdhead(chat_t('arch_title', 'Chat archive'));

$base = $h(rtrim((string)$BASEURL, '/'));
/** Round avatar: picture or the first letter of the name */
$cx_ava = static function (array $r) use ($h): string {
    if ((int)($r['uid'] ?? 0) === CHAT_BOT_UID) {
        return '<span class="cx-ava cx-ava--bot"><i class="fa-solid fa-robot"></i></span>';
    }
    $url  = chat_avatar((string)($r['avatar'] ?? ''));
    $name = (string)($r['username'] ?? '?');
    return $url !== ''
        ? '<img class="cx-ava" src="' . $h($url) . '" alt="" loading="lazy">'
        : '<span class="cx-ava cx-ava--letter">' . $h(mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1))) . '</span>';
};
?>
<link rel="stylesheet" href="<?= $base ?>/include/templates/default/style/chat.css?ver=<?= CHAT_ASSET_VER ?>">
<link rel="stylesheet" href="<?= $base ?>/include/templates/default/style/st-ui.css?ver=12">
<link rel="stylesheet" href="<?= $base ?>/include/templates/default/style/chat_archive.css?ver=1">

<div class="container-md st-page cx-page py-3">

    <!-- Header -->
    <header class="st-card st-head mb-3">
        <div class="st-head__icon st-soft-primary"><i class="fa-solid fa-box-archive"></i></div>
        <div class="flex-grow-1 min-w-0">
            <h1 class="st-title"><?= $t('arch_title', 'Chat archive') ?></h1>
            <div class="st-meta mt-1"><?= $t('arch_subtitle', 'Search all public chat messages, including deleted ones.') ?></div>
        </div>
        <a class="btn btn-outline-secondary st-pill cx-head-btn" href="chat.php"><i class="fa-solid fa-arrow-left me-1"></i><?= $t('btn_back_chat', 'Back to chat') ?></a>
    </header>

    <!-- KPI tiles -->
    <div class="cx-kpis mb-3">
        <?php foreach ($kpis as [$icon, $color, $label, $value]):
            $isRep = $icon === 'fa-flag';
            $tag   = $isRep ? 'a href="chat_archive.php?view=reports"' : 'div'; ?>
        <<?= $tag ?> class="st-kpi st-kpi--muted<?= $isRep && (int)$value > 0 ? ' cx-kpi--alert' : '' ?>">
            <span class="st-ico st-soft-<?= $color ?>"><i class="fa-solid <?= $icon ?>"></i></span>
            <div class="min-w-0"><div class="st-kpi__val"><?= number_format((int)$value, 0, '', ' ') ?></div><div class="st-kpi__lbl"><?= $h($label) ?></div></div>
            <i class="fa-solid <?= $icon ?> st-kpi__bg" aria-hidden="true"></i>
        </<?= $isRep ? 'a' : 'div' ?>>
        <?php endforeach; ?>
    </div>

    <!-- Messages / Reports -->
    <nav class="cx-tabs mb-3" aria-label="<?= $t('arch_tabs', 'Archive sections') ?>">
        <a class="<?= $view === 'messages' ? 'active' : '' ?>" href="chat_archive.php"<?= $view === 'messages' ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-comments"></i><?= $t('tab_messages', 'Public messages') ?>
        </a>
        <a class="<?= $view === 'reports' ? 'active' : '' ?>" href="chat_archive.php?view=reports"<?= $view === 'reports' ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-flag"></i><?= $t('tab_reports', 'Reports on private messages') ?>
            <?php if ((int)$stats['reports'] > 0): ?><span class="cx-tabs__n cx-tabs__n--alert"><?= (int)$stats['reports'] ?></span><?php endif; ?>
        </a>
    </nav>

    <?php if ($view === 'reports'): ?>

    <?php if ($doneMsg): ?>
    <div class="cx-note cx-note--<?= $doneMsg[0] ?> mb-3" role="status">
        <i class="fa-solid <?= $doneMsg[0] === 'success' ? 'fa-circle-check' : ($doneMsg[0] === 'danger' ? 'fa-circle-xmark' : 'fa-triangle-exclamation') ?>"></i><div><?= $h($doneMsg[1]) ?></div>
    </div>
    <?php endif; ?>

    <div class="cx-note mb-3">
        <i class="fa-solid fa-lock"></i><div><?= $t('rep_privacy', 'Private dialogs are not visible to staff. Here you only see a message somebody reported, with up to {1} messages before and after it.', CHAT_REPORT_CONTEXT) ?></div>
    </div>

    <div class="cx-sub mb-3">
        <a class="<?= $repState === 'open' ? 'active' : '' ?>" href="chat_archive.php?view=reports"><i class="fa-solid fa-inbox"></i><?= $t('rep_open', 'Open') ?></a>
        <a class="<?= $repState === 'done' ? 'active' : '' ?>" href="chat_archive.php?view=reports&amp;state=done"><i class="fa-solid fa-check-double"></i><?= $t('rep_handled', 'Handled') ?></a>
    </div>

    <?php if (!$reports): ?>
        <div class="st-card st-empty">
            <span class="st-ico st-soft-success"><i class="fa-solid fa-flag-checkered"></i></span>
            <div><?= $repState === 'open' ? $t('rep_empty', 'No open reports.') : $t('rep_empty_done', 'No handled reports yet.') ?></div>
        </div>
    <?php endif; ?>

    <?php foreach ($reports as $rep):
        $m = $rep['msg'];
        if (!$m) continue;
        $toQ  = $db->sql_query_prepared('SELECT username FROM users WHERE id = ?', [(int)$m['to_uid']]);
        $toNm = (string)(($toQ ? $db->fetch_array($toQ) : [])['username'] ?? ('#' . $m['to_uid']));
        $from = (string)($m['username'] ?? ('#' . $m['uid']));
        $handled = $rep['handled_at'] > 0;
    ?>
    <section class="st-card cx-rep mb-3<?= $handled ? ' is-handled' : '' ?>">
        <div class="st-card__head">
            <span class="st-ico st-soft-<?= $handled ? 'success' : 'danger' ?>"><i class="fa-solid <?= $handled ? 'fa-check' : 'fa-flag' ?>"></i></span>
            <span class="cx-rep__pair"><?= $h($from) ?><i class="fa-solid fa-arrow-right"></i><?= $h($toNm) ?></span>
            <span class="cx-id">#<?= (int)$m['id'] ?></span>
            <span class="st-meta ms-auto"><?= $h(chat_date((int)$m['added'])) ?></span>
        </div>
        <div class="cx-rep__who">
            <?php foreach ($rep['reporters'] as $rp): ?>
            <div class="cx-rep__by">
                <i class="fa-solid fa-flag"></i>
                <span><?= $t('rep_by', 'Reported by {1}', (string)($rp['username'] ?? ('#' . $rp['reporter']))) ?> <span class="st-meta">· <?= $h(chat_date((int)$rp['added'])) ?></span></span>
                <?php if (trim((string)$rp['reason']) !== ''): ?><span class="cx-rep__reason"><?= $h((string)$rp['reason']) ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="cx-rep__ctx">
            <?php foreach ($rep['context'] as $c):
                $isRep = (int)$c['id'] === (int)$m['id'];
                $cName = (string)($c['username'] ?? ('#' . $c['uid']));
                $mine  = (int)$c['uid'] === (int)$m['uid'];
            ?>
            <div class="cx-bub<?= $mine ? ' is-from' : ' is-to' ?><?= $isRep ? ' is-reported' : '' ?><?= (int)$c['deleted'] === 1 ? ' is-deleted' : '' ?>">
                <?= $cx_ava($c) ?>
                <div class="cx-bub__box">
                    <div class="cx-bub__meta"><strong><?= $h($cName) ?></strong> · <?= $h(date('d.m H:i', (int)$c['added'])) ?><?= $isRep ? ' <span class="cx-flag"><i class="fa-solid fa-flag"></i></span>' : '' ?></div>
                    <div class="cx-bub__text"><?= chat_parse((string)$c['message'], $cName) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($handled): ?>
            <div class="cx-rep__done"><i class="fa-solid fa-circle-check"></i><?= $t('rep_handled_by', 'Handled by {1}, {2}', $rep['handled_by'] !== '' ? $rep['handled_by'] : '—', chat_date($rep['handled_at'])) ?></div>
        <?php else: ?>
        <div class="cx-rep__actions">
            <form method="post" action="chat_archive.php?view=reports">
                <input type="hidden" name="my_post_key" value="<?= $h($postKey) ?>">
                <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                <button type="submit" name="act" value="close" class="btn btn-sm btn-outline-success st-pill"><i class="fa-solid fa-check me-1"></i><?= $t('rep_btn_close', 'Nothing wrong, close') ?></button>
                <?php if ((int)$m['deleted'] === 0): ?>
                <button type="submit" name="act" value="delete" class="btn btn-sm btn-outline-danger st-pill"><i class="fa-solid fa-trash me-1"></i><?= $t('rep_btn_delete', 'Delete the message') ?></button>
                <?php endif; ?>
            </form>
            <form method="post" action="chat_archive.php?view=reports" class="cx-ban">
                <input type="hidden" name="my_post_key" value="<?= $h($postKey) ?>">
                <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                <select name="minutes" class="form-select form-select-sm" aria-label="<?= $t('rep_ban_for', 'Ban for') ?>">
                    <option value="60"><?= $t('rep_ban_1h', '1 hour') ?></option>
                    <option value="1440" selected><?= $t('rep_ban_1d', '1 day') ?></option>
                    <option value="10080"><?= $t('rep_ban_7d', '7 days') ?></option>
                    <option value="0"><?= $t('rep_ban_forever', 'Permanently') ?></option>
                </select>
                <button type="submit" name="act" value="ban" class="btn btn-sm btn-danger st-pill"><i class="fa-solid fa-user-slash me-1"></i><?= $t('rep_btn_ban', 'Ban {1} from the chat', $from) ?></button>
            </form>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php if ($repPages > 1): ?>
    <div class="st-pager d-flex justify-content-center mt-3"><?= $repMulti ?></div>
    <?php endif; ?>

    <?php else: ?>

    <!-- Filters -->
    <form class="st-card mb-3" method="get" action="chat_archive.php">
        <div class="st-card__head"><i class="fa-solid fa-magnifying-glass"></i><?= $t('btn_search', 'Search') ?>
            <span class="st-badge st-soft-primary ms-auto"><?= $t('lbl_found', 'Found: {1}', number_format($total, 0, '', ' ')) ?></span>
        </div>
        <div class="st-card__body">
            <div class="cx-filters">
                <div class="cx-f"><label class="st-label" for="archUser"><?= $t('lbl_user', 'User') ?></label>
                    <div class="cx-in"><i class="fa-solid fa-user"></i><input type="text" class="form-control" id="archUser" name="user" value="<?= $h($filters['user']) ?>" placeholder="<?= $t('ph_user', 'Username') ?>" maxlength="64"></div></div>
                <div class="cx-f cx-f--wide"><label class="st-label" for="archText"><?= $t('lbl_text', 'Text contains') ?></label>
                    <div class="cx-in"><i class="fa-solid fa-font"></i><input type="search" class="form-control" id="archText" name="q" value="<?= $h($filters['q']) ?>" placeholder="<?= $t('ph_text', 'Word or phrase') ?>" maxlength="200"></div></div>
                <div class="cx-f"><label class="st-label" for="archFrom"><?= $t('lbl_from', 'From') ?></label>
                    <input type="date" class="form-control" id="archFrom" name="from" value="<?= $h($filters['from']) ?>"></div>
                <div class="cx-f"><label class="st-label" for="archTo"><?= $t('lbl_to', 'To') ?></label>
                    <input type="date" class="form-control" id="archTo" name="to" value="<?= $h($filters['to']) ?>"></div>
            </div>
            <div class="cx-actions">
                <label class="st-check mb-0" for="archDeleted"><input class="form-check-input" type="checkbox" id="archDeleted" name="deleted" value="1"<?= $filters['deleted'] ? ' checked' : '' ?>><span><i class="fa-solid fa-trash-can me-1"></i><?= $t('lbl_deleted', 'Include deleted') ?></span></label>
                <span class="ms-auto"></span>
                <?php if ($active): ?>
                <a class="btn btn-outline-secondary st-pill" href="chat_archive.php"><i class="fa-solid fa-rotate-left me-1"></i><?= $t('btn_reset', 'Reset') ?></a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary st-pill"><i class="fa-solid fa-magnifying-glass me-1"></i><?= $t('btn_search', 'Search') ?></button>
            </div>
        </div>
    </form>

    <!-- Results -->
    <section class="st-card cx-list">
        <?php if (!$rows): ?>
            <div class="st-empty">
                <span class="st-ico st-soft-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <div><?= $t('lbl_nothing', 'Nothing found.') ?></div>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $deleted = (int)$r['deleted'] === 1;
                $name    = (string)($r['username'] ?? '');
                $reply   = $r['_reply'] ?? null;
            ?>
            <div class="cx-row<?= $deleted ? ' is-deleted' : '' ?>">
                <?= $cx_ava($r) ?>
                <div class="cx-row__main">
                    <div class="cx-row__meta">
                        <span class="cx-row__who"><?= (int)$r['uid'] === CHAT_BOT_UID ? chat_bot_user_html() : ($name !== '' ? chat_user_html((int)$r['uid'], $name, (int)($r['usergroup'] ?? 2)) : '#' . (int)$r['uid']) ?></span>
                        <span class="st-meta"><?= $h(chat_date((int)$r['added'])) ?></span>
                        <span class="cx-id">#<?= (int)$r['id'] ?></span>
                        <?php if ($deleted): ?>
                            <span class="st-badge st-soft-danger"><i class="fa-solid fa-trash"></i><?= $t('badge_deleted', 'deleted') ?><?= !empty($r['edited_name']) ? ' · ' . $h((string)$r['edited_name']) : '' ?></span>
                        <?php elseif ((int)$r['edited_at'] > 0): ?>
                            <span class="st-badge st-soft-muted"><i class="fa-solid fa-pen"></i><?= !empty($r['edited_name']) && (int)$r['edited_by'] !== (int)$r['uid']
                                ? $t('badge_edited_by', 'edited by {1}', (string)$r['edited_name'])
                                : $t('badge_edited', 'edited') ?></span>
                        <?php endif; ?>
                        <?php if ($name !== ''): ?>
                        <a class="cx-row__filter ms-auto" href="chat_archive.php?<?= $h(http_build_query(['user' => $name, 'deleted' => $filters['deleted'] ?: null])) ?>" title="<?= $t('tip_only_user', 'Only messages of {1}', $name) ?>">
                            <i class="fa-solid fa-filter"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php if ((int)$r['reply_to'] > 0): ?>
                        <div class="cx-quote">
                            <i class="fa-solid fa-reply"></i>
                            <?php if (is_array($reply)): ?>
                                <strong><?= $h((string)($reply['username'] ?? '')) ?></strong>:
                                <?= (int)$reply['deleted'] === 1 ? '<em>' . $t('reply_gone_srv', 'message deleted') . '</em>' : $h(chat_plain((string)$reply['message'])) ?>
                                <span class="cx-id">#<?= (int)$reply['id'] ?></span>
                            <?php else: ?>
                                <em><?= $t('reply_gone_srv', 'message deleted') ?></em>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="cx-row__body"><?= chat_parse(chat_bot_text((string)$r['message'], false), $name) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($pages > 1): ?>
    <div class="st-pager d-flex justify-content-center mt-3"><?= $multipage ?></div>
    <?php endif; ?>

    <?php endif; /* view */ ?>

</div>
<?php
stdfoot();
