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
?>
<link rel="stylesheet" href="<?= $h(rtrim((string)$BASEURL, '/')) ?>/chat.css?ver=<?= CHAT_ASSET_VER ?>">

<div class="ch-arch container my-3">

    <!-- Header -->
    <div class="ch-arch__head">
        <span class="ch-arch__icon"><i class="fa-solid fa-box-archive"></i></span>
        <div class="ch-arch__titles">
            <h1 class="ch-arch__title"><?= $t('arch_title', 'Chat archive') ?></h1>
            <div class="ch-arch__subtitle"><?= $t('arch_subtitle', 'Search all public chat messages, including deleted ones.') ?></div>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="chat.php">
            <i class="fa-solid fa-arrow-left me-1"></i><?= $t('btn_back_chat', 'Back to chat') ?>
        </a>
    </div>

    <!-- KPI -->
    <div class="row g-3 mt-1">
        <?php foreach ($kpis as [$icon, $color, $label, $value]): ?>
        <div class="col-6 col-lg-3">
            <div class="ch-arch__kpi">
                <span class="ch-arch__kpi-icon" style="background:rgba(var(--bs-<?= $color ?>-rgb),.12);color:var(--bs-<?= $color ?>);">
                    <i class="fa-solid <?= $icon ?>"></i>
                </span>
                <div>
                    <div class="ch-arch__kpi-val"><?= number_format((int)$value, 0, '', ' ') ?></div>
                    <div class="ch-arch__kpi-lbl"><?= $h($label) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Messages / Reports -->
    <nav class="ch-arch__tabs" aria-label="<?= $t('arch_tabs', 'Archive sections') ?>">
        <a class="btn btn-sm <?= $view === 'messages' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="chat_archive.php"<?= $view === 'messages' ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-comments me-1"></i><?= $t('tab_messages', 'Public messages') ?>
        </a>
        <a class="btn btn-sm <?= $view === 'reports' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="chat_archive.php?view=reports"<?= $view === 'reports' ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-flag me-1"></i><?= $t('tab_reports', 'Reports on private messages') ?>
            <?php if ((int)$stats['reports'] > 0): ?><span class="badge rounded-pill text-bg-danger"><?= (int)$stats['reports'] ?></span><?php endif; ?>
        </a>
    </nav>

    <?php if ($view === 'reports'): ?>

    <?php if ($doneMsg): ?>
    <div class="alert alert-<?= $doneMsg[0] ?> mt-3 mb-0" role="status"><?= $h($doneMsg[1]) ?></div>
    <?php endif; ?>

    <div class="alert alert-secondary mt-3 mb-0 small">
        <i class="fa-solid fa-lock me-1"></i><?= $t('rep_privacy', 'Private dialogs are not visible to staff. Here you only see a message somebody reported, with up to {1} messages before and after it.', CHAT_REPORT_CONTEXT) ?>
    </div>

    <div class="d-flex gap-2 mt-3">
        <a class="btn btn-sm rounded-pill <?= $repState === 'open' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="chat_archive.php?view=reports"><?= $t('rep_open', 'Open') ?></a>
        <a class="btn btn-sm rounded-pill <?= $repState === 'done' ? 'btn-dark' : 'btn-outline-secondary' ?>" href="chat_archive.php?view=reports&amp;state=done"><?= $t('rep_handled', 'Handled') ?></a>
    </div>

    <?php if (!$reports): ?>
        <div class="ch-arch__empty mt-3">
            <i class="fa-solid fa-flag"></i>
            <div><?= $repState === 'open' ? $t('rep_empty', 'No open reports.') : $t('rep_empty_done', 'No handled reports yet.') ?></div>
        </div>
    <?php endif; ?>

    <?php foreach ($reports as $rep):
        $m = $rep['msg'];
        if (!$m) continue;
        $toQ  = $db->sql_query_prepared('SELECT username FROM users WHERE id = ?', [(int)$m['to_uid']]);
        $toNm = (string)(($toQ ? $db->fetch_array($toQ) : [])['username'] ?? ('#' . $m['to_uid']));
        $from = (string)($m['username'] ?? ('#' . $m['uid']));
    ?>
    <div class="ch-rep<?= $rep['handled_at'] > 0 ? ' is-handled' : '' ?>">
        <div class="ch-rep__head">
            <span class="ch-arch__id">#<?= (int)$m['id'] ?></span>
            <span class="ch-rep__pair"><?= $h($from) ?><i class="fa-solid fa-arrow-right"></i><?= $h($toNm) ?></span>
            <span class="ch-arch__date ms-auto"><?= $h(chat_date((int)$m['added'])) ?></span>
        </div>
        <div class="ch-rep__who">
            <?php foreach ($rep['reporters'] as $rp): ?>
                <div><i class="fa-solid fa-flag text-danger me-1"></i><?= $t('rep_by', 'Reported by {1}', (string)($rp['username'] ?? ('#' . $rp['reporter']))) ?>
                    · <?= $h(chat_date((int)$rp['added'])) ?><?= trim((string)$rp['reason']) !== '' ? ' · <strong>' . $h((string)$rp['reason']) . '</strong>' : '' ?></div>
            <?php endforeach; ?>
        </div>
        <div class="ch-rep__ctx">
            <?php foreach ($rep['context'] as $c):
                $isRep = (int)$c['id'] === (int)$m['id'];
                $cName = (string)($c['username'] ?? ('#' . $c['uid']));
            ?>
            <div class="ch-rep__msg<?= $isRep ? ' is-reported' : '' ?><?= (int)$c['deleted'] === 1 ? ' is-deleted' : '' ?>">
                <span class="ch-rep__meta"><?= $h(date('d.m H:i', (int)$c['added'])) ?></span>
                <strong><?= $h($cName) ?>:</strong>
                <span class="ch-rep__body"><?= chat_parse((string)$c['message'], $cName) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($rep['handled_at'] > 0): ?>
            <div class="ch-rep__done"><i class="fa-solid fa-check me-1"></i><?= $t('rep_handled_by', 'Handled by {1}, {2}', $rep['handled_by'] !== '' ? $rep['handled_by'] : '—', chat_date($rep['handled_at'])) ?></div>
        <?php else: ?>
        <div class="ch-rep__actions">
            <form method="post" action="chat_archive.php?view=reports">
                <input type="hidden" name="my_post_key" value="<?= $h($postKey) ?>">
                <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                <button type="submit" name="act" value="close" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-check me-1"></i><?= $t('rep_btn_close', 'Nothing wrong, close') ?></button>
                <?php if ((int)$m['deleted'] === 0): ?>
                <button type="submit" name="act" value="delete" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i><?= $t('rep_btn_delete', 'Delete the message') ?></button>
                <?php endif; ?>
            </form>
            <form method="post" action="chat_archive.php?view=reports">
                <input type="hidden" name="my_post_key" value="<?= $h($postKey) ?>">
                <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                <select name="minutes" class="form-select form-select-sm" aria-label="<?= $t('rep_ban_for', 'Ban for') ?>">
                    <option value="60"><?= $t('rep_ban_1h', '1 hour') ?></option>
                    <option value="1440" selected><?= $t('rep_ban_1d', '1 day') ?></option>
                    <option value="10080"><?= $t('rep_ban_7d', '7 days') ?></option>
                    <option value="0"><?= $t('rep_ban_forever', 'Permanently') ?></option>
                </select>
                <button type="submit" name="act" value="ban" class="btn btn-sm btn-danger"><i class="fa-solid fa-user-slash me-1"></i><?= $t('rep_btn_ban', 'Ban {1} from the chat', $from) ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($repPages > 1): ?>
    <div class="mt-3"><?= $repMulti ?></div>
    <?php endif; ?>

    <?php else: ?>

    <!-- Filters -->
    <form class="ch-arch__filters" method="get" action="chat_archive.php">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="archUser"><?= $t('lbl_user', 'User') ?></label>
                <input type="text" class="form-control" id="archUser" name="user" value="<?= $h($filters['user']) ?>" placeholder="<?= $t('ph_user', 'Username') ?>" maxlength="64">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="archText"><?= $t('lbl_text', 'Text contains') ?></label>
                <input type="search" class="form-control" id="archText" name="q" value="<?= $h($filters['q']) ?>" placeholder="<?= $t('ph_text', 'Word or phrase') ?>" maxlength="200">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="archFrom"><?= $t('lbl_from', 'From') ?></label>
                <input type="date" class="form-control" id="archFrom" name="from" value="<?= $h($filters['from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="archTo"><?= $t('lbl_to', 'To') ?></label>
                <input type="date" class="form-control" id="archTo" name="to" value="<?= $h($filters['to']) ?>">
            </div>
            <div class="col-12 col-md-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="archDeleted" name="deleted" value="1"<?= $filters['deleted'] ? ' checked' : '' ?>>
                    <label class="form-check-label" for="archDeleted"><?= $t('lbl_deleted', 'Include deleted') ?></label>
                </div>
            </div>
        </div>
        <div class="ch-arch__actions">
            <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-magnifying-glass me-1"></i><?= $t('btn_search', 'Search') ?></button>
            <?php if ($active): ?>
            <a class="btn btn-outline-secondary rounded-pill px-3" href="chat_archive.php"><i class="fa-solid fa-rotate-left me-1"></i><?= $t('btn_reset', 'Reset') ?></a>
            <?php endif; ?>
            <span class="ch-arch__found ms-auto"><?= $t('lbl_found', 'Found: {1}', number_format($total, 0, '', ' ')) ?></span>
        </div>
    </form>

    <!-- Results -->
    <div class="ch-arch__list">
        <?php if (!$rows): ?>
            <div class="ch-arch__empty">
                <i class="fa-solid fa-magnifying-glass"></i>
                <div><?= $t('lbl_nothing', 'Nothing found.') ?></div>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $deleted = (int)$r['deleted'] === 1;
                $name    = (string)($r['username'] ?? '');
                $reply   = $r['_reply'] ?? null;
            ?>
            <div class="ch-arch__item<?= $deleted ? ' is-deleted' : '' ?>">
                <div class="ch-arch__meta">
                    <span class="ch-arch__id">#<?= (int)$r['id'] ?></span>
                    <span class="ch-arch__date"><?= $h(chat_date((int)$r['added'])) ?></span>
                    <span class="ch-arch__who"><?= $name !== '' ? chat_user_html((int)$r['uid'], $name, (int)($r['usergroup'] ?? 2)) : '#' . (int)$r['uid'] ?></span>
                    <?php if ($deleted): ?>
                        <span class="badge rounded-pill text-bg-danger"><i class="fa-solid fa-trash me-1"></i><?= $t('badge_deleted', 'deleted') ?><?= !empty($r['edited_name']) ? ' · ' . $h((string)$r['edited_name']) : '' ?></span>
                    <?php elseif ((int)$r['edited_at'] > 0): ?>
                        <span class="badge rounded-pill text-bg-secondary"><i class="fa-solid fa-pen me-1"></i><?= !empty($r['edited_name']) && (int)$r['edited_by'] !== (int)$r['uid']
                            ? $t('badge_edited_by', 'edited by {1}', (string)$r['edited_name'])
                            : $t('badge_edited', 'edited') ?></span>
                    <?php endif; ?>
                    <?php if ($name !== ''): ?>
                    <a class="ch-arch__filter-user ms-auto" href="chat_archive.php?<?= $h(http_build_query(['user' => $name, 'deleted' => $filters['deleted'] ?: null])) ?>" title="<?= $t('tip_only_user', 'Only messages of {1}', $name) ?>">
                        <i class="fa-solid fa-filter"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ((int)$r['reply_to'] > 0): ?>
                    <div class="ch-arch__reply">
                        <i class="fa-solid fa-reply me-1"></i>
                        <?php if (is_array($reply)): ?>
                            <strong><?= $h((string)($reply['username'] ?? '')) ?></strong>:
                            <?= (int)$reply['deleted'] === 1 ? '<em>' . $t('reply_gone_srv', 'message deleted') . '</em>' : $h(chat_plain((string)$reply['message'])) ?>
                            <span class="ch-arch__id">#<?= (int)$reply['id'] ?></span>
                        <?php else: ?>
                            <em><?= $t('reply_gone_srv', 'message deleted') ?></em>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="ch-arch__body"><?= chat_parse((string)$r['message'], $name) ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="mt-3"><?= $multipage ?></div>
    <?php endif; ?>

    <?php endif; /* view */ ?>

</div>
<?php
stdfoot();
