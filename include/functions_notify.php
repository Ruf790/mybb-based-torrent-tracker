<?php

declare(strict_types=1);

/**
 * Notification center (the bell in the header).
 *
 * A notification row stores only its type + arguments; the text is built at
 * display time from notifications.lang.php, so it always appears in the
 * viewer's current language (switching the language re-translates old items).
 *
 * Placeholders in every n_* lang string are the same for all types:
 *   {1} = actor username (or "System"), {2} = event count (grouped types),
 *   {3}, {4}… = $args passed to notify() (torrent name, amount, …)
 *
 * Adding a type: one entry in notify_types() + n_<type> (and n_<type>_many
 * when 'group' => true) in both notifications.lang.php files.
 */

if (!defined('IN_TRACKER') && !defined('IN_MYBB') && !defined('IN_CRON')) {
    die('Hacking attempt!');
}

const NOTIFY_DROPDOWN_LIMIT = 10;
const NOTIFY_JS_VER         = 2;

/**
 * Scaling.
 * The unread count lives in users.notif_unread (one PK read instead of COUNT(*) on every page).
 * notify_prune() runs by itself at most once per NOTIFY_PRUNE_EVERY seconds (see notify_maybe_prune),
 * deletes in chunks of NOTIFY_PRUNE_CHUNK rows so the table is never locked for long,
 * and keeps at most NOTIFY_MAX_PER_USER newest rows per user.
 */
const NOTIFY_KEEP_READ_DAYS = 60;
const NOTIFY_KEEP_ALL_DAYS  = 180;
const NOTIFY_MAX_PER_USER   = 300;
const NOTIFY_PRUNE_CHUNK    = 5000;
const NOTIFY_PRUNE_EVERY    = 86400;

if (!function_exists('ags_fmt')) {
    /**
     * Substitute {1}, {2}… (and %1$s, %2$s… — $lang->load() converts {n} into those)
     */
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}

/**
 * Types: icon (Font Awesome 6 solid), Bootstrap color, and whether repeated
 * unread events on the same ref_id are merged into one row ("… 5 new comments").
 */
function notify_types(): array
{
    return [
        'torrent_comment' => ['icon' => 'fa-comment',      'color' => 'primary', 'group' => true],
        'comment_reply'   => ['icon' => 'fa-reply',        'color' => 'info',    'group' => true],
        'mention'         => ['icon' => 'fa-at',           'color' => 'warning', 'group' => false],
        // ref_id = who mentioned you: repeated chat mentions from one person merge into one row
        'chat_mention'    => ['icon' => 'fa-comment-dots', 'color' => 'info',    'group' => true],
        'chat_reply'      => ['icon' => 'fa-reply',        'color' => 'info',    'group' => true],
		'chat_dm'         => ['icon' => 'fa-envelope',     'color' => 'primary', 'group' => true],
        'thread_reply'    => ['icon' => 'fa-comments',     'color' => 'primary', 'group' => true],
        'rating'          => ['icon' => 'fa-star',         'color' => 'warning', 'group' => true],
        'reaction'        => ['icon' => 'fa-face-smile',   'color' => 'warning', 'group' => true],
        'claim'           => ['icon' => 'fa-hand-holding-heart', 'color' => 'success', 'group' => true],
        'claim_settled'   => ['icon' => 'fa-calendar-check', 'color' => 'info',  'group' => false],
        'request_filled'  => ['icon' => 'fa-circle-check', 'color' => 'success', 'group' => false],
        'request_vote_filled' => ['icon' => 'fa-check-to-slot', 'color' => 'success', 'group' => false],
        'request_comment' => ['icon' => 'fa-comment-dots', 'color' => 'primary', 'group' => true],
        'offer_uploaded'  => ['icon' => 'fa-gift',         'color' => 'success', 'group' => false],
        'offer_comment'   => ['icon' => 'fa-comments',     'color' => 'primary', 'group' => true],
        'reseed'          => ['icon' => 'fa-seedling',     'color' => 'success', 'group' => false],
        // Один пакет торрентов в одном уведомлении: {3} = сколько, {4} = названия
        'reseed_multi'    => ['icon' => 'fa-seedling',     'color' => 'success', 'group' => false],
        'bonus'           => ['icon' => 'fa-coins',        'color' => 'warning', 'group' => false],
        'new_torrent'     => ['icon' => 'fa-file-circle-plus', 'color' => 'success', 'group' => false],
        'system'          => ['icon' => 'fa-bullhorn',     'color' => 'secondary','group' => false],
    ];
}

/** Lazy-load the lang section (the bell is printed on pages that never load it). */
function notify_lang(): array
{
    global $lang;
    if (!isset($lang->notifications) || !is_array($lang->notifications)) {
        $lang->load('notifications');
    }

	return (array)$lang->notifications;
}

/** Only relative links (no scheme, no //host) — the link is used for a redirect. */
function notify_safe_link(string $link): string
{
    $link = trim($link);
    if ($link === '' || str_starts_with($link, '//') || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $link)) {
        return '';
    }
    return ltrim($link, '/');
}

// ── Writing ─────────────────────────────────────────────────────────────────

/**
 * Create a notification.
 *
 * @param int    $uid    recipient
 * @param string $type   key from notify_types()
 * @param int    $actor  user who caused it (0 = system); never notifies yourself
 * @param int    $ref    torrent/request/comment id — grouped types merge on it
 * @param array  $args   values for {3}, {4}… (plain text, escaped on output)
 * @param string $link   relative URL, e.g. "details.php?id=5#comments"
 */
function notify(int $uid, string $type, int $actor = 0, int $ref = 0, array $args = [], string $link = ''): bool
{
    global $db;

    $types = notify_types();
    if ($uid <= 0 || !isset($types[$type]) || ($actor > 0 && $actor === $uid)) {
        return false;
    }

    $argsJson = json_encode(array_map('strval', array_values($args)), JSON_UNESCAPED_UNICODE);
    $link     = notify_safe_link($link);
    $now      = TIMENOW;

    if ($types[$type]['group'] && $ref > 0) {
        $q = $db->sql_query_prepared(
            'SELECT id FROM notifications WHERE uid = ? AND type = ? AND ref_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 1',
            [$uid, $type, $ref]
        );
        $row = ($q && $db->num_rows($q)) ? $db->fetch_array($q) : null;
        if ($row) {
            $db->sql_query_prepared(
                'UPDATE notifications SET actor_uid = ?, args = ?, link = ?, cnt = cnt + 1, added = ? WHERE id = ?',
                [$actor, $argsJson, $link, $now, (int)$row['id']]
            );
            return true;
        }
    }

    $db->sql_query_prepared(
        'INSERT INTO notifications (uid, type, actor_uid, ref_id, args, link, cnt, is_read, added) VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?)',
        [$uid, $type, $actor, $ref, $argsJson, $link, $now]
    );
    // A merged (grouped) row above was already unread, so only a new row moves the counter
    $db->sql_query_prepared('UPDATE users SET notif_unread = notif_unread + 1 WHERE id = ?', [$uid]);
    return true;
}

/**
 * Same notification for many users at once: one INSERT … SELECT FROM users WHERE $where.
 * $where / $params are trusted SQL from the caller (e.g. "ustatus = 'confirmed' AND usergroup = ?").
 * Not grouped; the actor gets one too if they match (bulk bonus to everyone includes the sender).
 */
function notify_where(string $where, array $params, string $type, int $actor = 0, array $args = [], string $link = ''): void
{
    global $db;
    if (!isset(notify_types()[$type])) {
        return;
    }
    $argsJson = json_encode(array_map('strval', array_values($args)), JSON_UNESCAPED_UNICODE);
    $db->sql_query_prepared(
        "INSERT INTO notifications (uid, type, actor_uid, ref_id, args, link, cnt, is_read, added)
         SELECT id, ?, ?, 0, ?, ?, 1, 0, ? FROM users WHERE {$where}",
        [$type, $actor, $argsJson, notify_safe_link($link), TIMENOW, ...array_values($params)]
    );
    // Same WHERE → same users. If someone changed in between, notifications.php resyncs them.
    $db->sql_query_prepared("UPDATE users SET notif_unread = notif_unread + 1 WHERE {$where}", array_values($params));
}

/**
 * Notify every @username found in $text (max 5 per message).
 * {3} = $context (e.g. torrent name). Returns the uids that were notified,
 * so the caller can skip sending them a second notification for the same event.
 */
function notify_mentions(string $text, int $actor, int $ref, string $context, string $link, array $skip = []): array
{
    global $db;

    // Ignore quoted text: quoting someone's post is not a mention
    $text = preg_replace('~\[quote[^\]]*\].*?\[/quote\]~is', '', $text) ?? $text;
    if (!preg_match_all('~(?<![\p{L}\p{N}_])@([\p{L}\p{N}_.\-]{2,32})~u', $text, $m)) {
        return [];
    }

    $names = array_slice(array_unique(array_map(fn($n) => rtrim($n, '.-'), $m[1])), 0, 5);
    if (!$names) {
        return [];
    }

    $ph = implode(',', array_fill(0, count($names), '?'));
    $q  = $db->sql_query_prepared("SELECT id FROM users WHERE username IN ($ph)", $names);

    $done = [];
    while ($q && ($u = $db->fetch_array($q))) {
        $id = (int)$u['id'];
        if (in_array($id, $skip, true)) {
            continue;
        }
        if (notify($id, 'mention', $actor, $ref, [$context], $link)) {
            $done[] = $id;
        }
    }
    return $done;
}

/**
 * Authors of the comments quoted in $text.
 * Handles MyBB-style [quote='name' pid='5' …] / [quote="name"] / [quote=name]:
 * with pid the author is taken from the comment itself (renames don't break it),
 * otherwise by username.
 */
function notify_quoted_users(string $text): array
{
    global $db;

    if (!preg_match_all('~\[quote=([^\]]*)\]~i', $text, $m)) {
        return [];
    }

    $pids = $names = [];
    foreach (array_slice($m[1], 0, 10) as $attr) {
        if (preg_match('~\bpid=["\']?(\d+)~i', $attr, $p)) {
            $pids[] = (int)$p[1];
        } elseif (preg_match('~^\s*(["\']?)(.+?)\1(?:\s+\w+=|\s*$)~u', $attr, $n)) {
            $names[] = trim($n[2]);
        }
    }

    $uids = [];
    if ($pids = array_values(array_unique($pids))) {
        $ph = implode(',', array_fill(0, count($pids), '?'));
        $q  = $db->sql_query_prepared("SELECT user FROM comments WHERE id IN ($ph)", $pids);
        while ($q && ($r = $db->fetch_array($q))) {
            $uids[] = (int)$r['user'];
        }
    }
    if ($names = array_values(array_unique(array_filter($names)))) {
        $ph = implode(',', array_fill(0, count($names), '?'));
        $q  = $db->sql_query_prepared("SELECT id FROM users WHERE username IN ($ph)", $names);
        while ($q && ($r = $db->fetch_array($q))) {
            $uids[] = (int)$r['id'];
        }
    }
    return array_values(array_unique(array_filter($uids)));
}

/**
 * Everything a new torrent comment can trigger, each person notified once:
 * quoted → comment_reply, @mentioned → mention, torrent owner → torrent_comment.
 */
function notify_comment_event(int $actor, int $torrentId, int $owner, string $torrentName, string $text, string $link): void
{
    $done = [$actor];

    foreach (notify_quoted_users($text) as $uid) {
        if (!in_array($uid, $done, true) && notify($uid, 'comment_reply', $actor, $torrentId, [$torrentName], $link)) {
            $done[] = $uid;
        }
    }

    $done = array_merge($done, notify_mentions($text, $actor, $torrentId, $torrentName, $link, $done));

    if ($owner > 0 && !in_array($owner, $done, true)) {
        notify($owner, 'torrent_comment', $actor, $torrentId, [$torrentName], $link);
    }
}

// ── Reading ─────────────────────────────────────────────────────────────────

/**
 * Unread count from users.notif_unread.
 * $fresh = false (header bell): takes it from $CURUSER when that row already has it — no query at all.
 * $fresh = true (AJAX polling): always reads the column (primary key lookup).
 */
function notify_unread_count(int $uid, bool $fresh = false): int
{
    global $db, $CURUSER;

    if (!$fresh && (int)($CURUSER['id'] ?? 0) === $uid && isset($CURUSER['notif_unread'])) {
        return max(0, (int)$CURUSER['notif_unread']);
    }
    $q   = $db->sql_query_prepared('SELECT notif_unread FROM users WHERE id = ?', [$uid]);
    $row = $q ? $db->fetch_array($q) : null;
    return max(0, (int)($row['notif_unread'] ?? 0));
}

/**
 * Real COUNT(*) → users.notif_unread. Self-healing: notifications.php calls it,
 * so a counter that drifted (manual DB edits, a race in notify_where) fixes itself
 * as soon as the user opens the page.
 */
function notify_resync(int $uid): int
{
    global $db, $CURUSER;

    $q    = $db->sql_query_prepared('SELECT COUNT(*) AS c FROM notifications WHERE uid = ? AND is_read = 0', [$uid]);
    $row  = $q ? $db->fetch_array($q) : null;
    $real = (int)($row['c'] ?? 0);

    $db->sql_query_prepared('UPDATE users SET notif_unread = ? WHERE id = ? AND notif_unread <> ?', [$real, $uid, $real]);
    if ((int)($CURUSER['id'] ?? 0) === $uid) {
        $CURUSER['notif_unread'] = $real;
    }
    return $real;
}

function notify_count(int $uid, bool $unreadOnly = false): int
{
    global $db;
    if ($unreadOnly) {
        return notify_unread_count($uid);
    }
    $q   = $db->sql_query_prepared('SELECT COUNT(*) AS c FROM notifications WHERE uid = ?', [$uid]);
    $row = $q ? $db->fetch_array($q) : null;
    return (int)($row['c'] ?? 0);
}

/** Raw rows (newest first) with the actor's current username. */
function notify_fetch(int $uid, int $limit, int $offset = 0, bool $unreadOnly = false): array
{
    global $db;
    $sql = 'SELECT n.*, u.username AS actor_name
            FROM notifications n
            LEFT JOIN users u ON u.id = n.actor_uid
            WHERE n.uid = ?' . ($unreadOnly ? ' AND n.is_read = 0' : '') . '
            ORDER BY n.id DESC
            LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    $q    = $db->sql_query_prepared($sql, [$uid]);
    $rows = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $rows[] = $r;
    }
    return $rows;
}

function notify_time_ago(int $ts): string
{
    $L    = notify_lang();
    $diff = max(0, TIMENOW - $ts);
    return match (true) {
        $diff < 60          => $L['ago_now'],
        $diff < 3600        => ags_fmt($L['ago_min'],  intdiv($diff, 60)),
        $diff < 86400       => ags_fmt($L['ago_hour'], intdiv($diff, 3600)),
        $diff < 7 * 86400   => ags_fmt($L['ago_day'],  intdiv($diff, 86400)),
        default             => date($L['fmt_date'], $ts),
    };
}

/**
 * Turn a row into display data. 'text' is PLAIN text: escape it in PHP,
 * insert it with textContent in JS.
 */
function notify_render(array $row): array
{
    $L     = notify_lang();
    $types = notify_types();
    $type  = (string)$row['type'];
    $meta  = $types[$type] ?? $types['system'];
    $cnt   = (int)$row['cnt'];

    $key = 'n_' . $type . ($cnt > 1 && !empty($meta['group']) ? '_many' : '');
    $tpl = $L[$key] ?? $L['n_unknown'];

    $actor = (int)$row['actor_uid'] === 0
        ? $L['lbl_system']
        : (string)($row['actor_name'] ?? '' ?: $L['lbl_deleted_user']);

    $args = json_decode((string)($row['args'] ?? ''), true);
    $args = is_array($args) ? array_map('strval', $args) : [];

    $added = (int)$row['added'];

    return [
        'id'    => (int)$row['id'],
        'text'  => ags_fmt($tpl, $actor, $cnt, ...$args),
        'icon'  => $meta['icon'],
        'color' => $meta['color'],
        'time'  => notify_time_ago($added),
        'date'  => date($L['fmt_date'], $added),
        'read'  => (int)$row['is_read'] === 1,
        'url'   => 'notifications.php?go=' . (int)$row['id'],
    ];
}

// ── Changing state ──────────────────────────────────────────────────────────

/** Mark one as read; returns its stored link ('' when not found / not yours). */
function notify_mark_read(int $uid, int $id): ?string
{
    global $db;
    $q   = $db->sql_query_prepared('SELECT link FROM notifications WHERE id = ? AND uid = ?', [$id, $uid]);
    $row = ($q && $db->num_rows($q)) ? $db->fetch_array($q) : null;
    if (!$row) {
        return null;
    }
    $db->sql_query_prepared('UPDATE notifications SET is_read = 1 WHERE id = ? AND uid = ? AND is_read = 0', [$id, $uid]);
    if ($db->affected_rows() > 0) {
        $db->sql_query_prepared('UPDATE users SET notif_unread = GREATEST(notif_unread, 1) - 1 WHERE id = ?', [$uid]);
    }
    return notify_safe_link((string)$row['link']);
}

function notify_mark_all(int $uid): void
{
    global $db;
    $db->sql_query_prepared('UPDATE notifications SET is_read = 1 WHERE uid = ? AND is_read = 0', [$uid]);
    $db->sql_query_prepared('UPDATE users SET notif_unread = 0 WHERE id = ?', [$uid]);
}

function notify_delete_read(int $uid): void
{
    global $db;
    $db->sql_query_prepared('DELETE FROM notifications WHERE uid = ? AND is_read = 1', [$uid]);
}

/** DELETE … LIMIT in a loop: short locks instead of one huge delete. Returns rows removed. */
function notify_delete_chunked(string $where, array $params): int
{
    global $db;
    $total = 0;
    for ($i = 0; $i < 200; $i++) {   // safety stop: 200 × chunk per run
        $db->sql_query_prepared("DELETE FROM notifications WHERE {$where} LIMIT " . NOTIFY_PRUNE_CHUNK, $params);
        $n = $db->affected_rows();
        $total += max(0, $n);
        if ($n < NOTIFY_PRUNE_CHUNK) {
            break;
        }
    }
    return $total;
}

/**
 * Cleanup:
 *   1) read items older than NOTIFY_KEEP_READ_DAYS
 *   2) anything older than NOTIFY_KEEP_ALL_DAYS (unread too)
 *   3) per-user cap: only the newest NOTIFY_MAX_PER_USER rows are kept
 * Steps 2 and 3 can remove unread rows, so the counters of the touched users are recounted.
 */
function notify_prune(int $keepReadDays = NOTIFY_KEEP_READ_DAYS, int $keepAllDays = NOTIFY_KEEP_ALL_DAYS, int $maxPerUser = NOTIFY_MAX_PER_USER): array
{
    global $db;

    // Values come from the settings page (cron passes them); guard against nonsense
    $keepReadDays = max(1, $keepReadDays);
    $keepAllDays  = max($keepReadDays, $keepAllDays);
    $maxPerUser   = max(10, $maxPerUser);

    $stats = ['read' => 0, 'old' => 0, 'cap' => 0];

    $stats['read'] = notify_delete_chunked('is_read = 1 AND added < ?', [TIMENOW - $keepReadDays * 86400]);

    // Users who will lose unread rows in step 2 — their counters need a recount
    $touched = [];
    $q = $db->sql_query_prepared(
        'SELECT DISTINCT uid FROM notifications WHERE is_read = 0 AND added < ?',
        [TIMENOW - $keepAllDays * 86400]
    );
    while ($q && ($r = $db->fetch_array($q))) {
        $touched[(int)$r['uid']] = true;
    }
    $stats['old'] = notify_delete_chunked('added < ?', [TIMENOW - $keepAllDays * 86400]);

    // Per-user cap: find the id of the N-th newest row, delete everything older
    $q = $db->sql_query_prepared(
        'SELECT uid FROM notifications GROUP BY uid HAVING COUNT(*) > ?',
        [$maxPerUser]
    );
    $over = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $over[] = (int)$r['uid'];
    }
    foreach ($over as $uid) {
        $q   = $db->sql_query_prepared(
            'SELECT id FROM notifications WHERE uid = ? ORDER BY id DESC LIMIT 1 OFFSET ' . ($maxPerUser - 1),
            [$uid]
        );
        $row = ($q && $db->num_rows($q)) ? $db->fetch_array($q) : null;
        if ($row) {
            $db->sql_query_prepared('DELETE FROM notifications WHERE uid = ? AND id < ?', [$uid, (int)$row['id']]);
            $stats['cap'] += max(0, $db->affected_rows());
            $touched[$uid] = true;
        }
    }

    foreach (array_keys($touched) as $uid) {
        notify_resync($uid);
    }
    return $stats;
}

/**
 * Runs notify_prune() at most once per NOTIFY_PRUNE_EVERY seconds, without a real cron:
 * notifications_ajax.php calls it; a timestamp file + flock make sure only one request does the work.
 * If you have a real cron, call notify_prune() there and drop the call from the AJAX file.
 */
function notify_maybe_prune(): void
{
    $dir  = (defined('TSDIR') ? TSDIR : dirname(__DIR__)) . '/cache';
    $file = (is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir()) . '/notify_prune.time';

    if (is_file($file) && TIMENOW - (int)@filemtime($file) < NOTIFY_PRUNE_EVERY) {
        return;
    }
    $fh = @fopen($file, 'c');
    if (!$fh) {
        return;
    }
    if (flock($fh, LOCK_EX | LOCK_NB)) {
        // Touch first: if the prune dies halfway, it is not retried on every request
        @touch($file, TIMENOW);
        ignore_user_abort(true);
        notify_prune();
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

// ── Header bell ─────────────────────────────────────────────────────────────

function notify_js_lang(): array
{
    $out = [];
    foreach (notify_lang() as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $out[substr((string)$k, 3)] = (string)$v;
        }
    }
    return $out;
}

/** Bell + dropdown for the header. Prints nothing for guests. */
function notify_bell_html(): string
{
    global $CURUSER, $BASEURL, $mybb;

    if (empty($CURUSER['id'])) {
        return '';
    }

    $L      = notify_lang();
    $e      = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $unread = notify_unread_count((int)$CURUSER['id']);
    $badge  = $unread > 99 ? '99+' : (string)$unread;
    $base   = rtrim((string)$BASEURL, '/');
    $json   = json_encode(notify_js_lang(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $ver    = NOTIFY_JS_VER;

    // notifications.php checks this so the CSS/JS are not printed twice
    if (!defined('AGS_NOTIF_ASSETS')) {
        define('AGS_NOTIF_ASSETS', true);
    }

    return <<<HTML
<div class="dropdown ags-notif" id="agsNotif"
     data-ajax="{$e($base . '/notifications_ajax.php')}"
     data-key="{$e((string)$mybb->post_code)}">
    <button type="button" class="ags-notif-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
            aria-expanded="false" title="{$e($L['tip_bell'])}" aria-label="{$e($L['tip_bell'])}">
        <i class="fa-solid fa-bell"></i>
        <span class="ags-notif-badge" id="agsNotifBadge"{$e($unread > 0 ? '' : ' hidden')}>{$e($badge)}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end ags-notif-menu">
        <div class="ags-notif-head">
            <strong>{$e($L['sec_notifications'])}</strong>
            <button type="button" class="ags-notif-markall" id="agsNotifMarkAll">
                <i class="fa-solid fa-check-double me-1"></i>{$e($L['btn_mark_all'])}
            </button>
        </div>
        <div class="ags-notif-list" id="agsNotifList">
            <div class="ags-notif-empty">{$e($L['lbl_loading'])}</div>
        </div>
        <a class="ags-notif-foot" href="{$e($base . '/notifications.php')}">{$e($L['btn_view_all'])}</a>
    </div>
</div>
<script>window.AGS_NOTIF_LANG = {$json};</script>
<link rel="stylesheet" href="{$e($base)}/include/templates/default/style/notifications.css?ver={$ver}">
<script src="{$e($base)}/scripts/notifications.js?ver={$ver}" defer></script>
HTML;
}

// ── New torrent in a subscribed category (PM / email / bell) ────────────────

/** PM and email texts: notify_upload_* keys in upload.lang.php */
const NOTIFY_UPLOAD_LANG = 'upload';

/** Also put a "new torrent" item in the subscribers' bell (one INSERT … SELECT for all of them) */
const NOTIFY_UPLOAD_BELL = true;

/**
 * English defaults for the email texts: used when the recipient's lang file has no such key,
 * so a missing key never breaks the upload under strict_types.
 * {1} category, {2} torrent link, {3} torrent name, {4} $BASEURL, {5} $SITENAME
 */
const NOTIFY_UPLOAD_DEFAULTS = [
    'notify_upload_email_subject' => 'New torrent in {1}: {3}',
    'notify_upload_email_message' => "A new torrent has been uploaded in your subscribed category {1}:\n\n{3}\n{2}\n\nManage subscriptions: {4}/usercp.php?action=options\n\n— {5}",
];

/** Email texts in one language (cached per language). */
function notify_upload_lang(string $language): array
{
    global $lang;
    static $cache = [];

    // users.language comes from the DB: only a plain folder name, otherwise English
    if (!preg_match('/^[a-z_]+$/', $language)) $language = 'english';
    if (isset($cache[$language])) return $cache[$language];

    $strings = [];
    if (is_object($lang) && method_exists($lang, 'get_section')) {
        $strings = array_filter((array)$lang->get_section(NOTIFY_UPLOAD_LANG, $language), 'is_string');
    }
    return $cache[$language] = array_merge(NOTIFY_UPLOAD_DEFAULTS, array_intersect_key($strings, NOTIFY_UPLOAD_DEFAULTS));
}

/**
 * Everyone subscribed to the category: bell for all of them, PM / email for those who chose it,
 * each in the recipient's own language (users.language).
 */
function notify_upload_subscribers(int $catid, int $torrent_id, string $torrent_name): void
{
    global $BASEURL, $SITENAME, $CURUSER, $db;

    $uploader     = (int)($CURUSER['id'] ?? 0);
    $torrent_link = $BASEURL . '/' . get_torrent_link($torrent_id);
    $notif_tag    = "%[cat{$catid}]%";
    $who          = "enabled = 'yes' AND ustatus = 'confirmed' AND notifs != '' AND notifs LIKE ? AND id != ?";

    $res = $db->sql_query_prepared(
        "SELECT id, username, email, notifs, language FROM users WHERE {$who}",
        [$notif_tag, $uploader]
    );
    if (!$res || $db->num_rows($res) === 0) return;

    $row      = $db->fetch_array($db->sql_query_prepared("SELECT name FROM categories WHERE id = ?", [$catid]));
    $cat_name = (string)($row['name'] ?? 'Unknown Category');

    // Bell: one query for all subscribers. Actor 0 (system): an anonymous upload must not show the uploader.
    // Text: n_new_torrent in notifications.lang.php, {3} category, {4} torrent name.
    if (NOTIFY_UPLOAD_BELL) {
        notify_where($who, [$notif_tag, $uploader], 'new_torrent', 0, [$cat_name, $torrent_name], get_torrent_link($torrent_id));
    }

    require_once INC_PATH . '/functions_pm.php';

    // [ ] in the name / category would break BBCode in the PM (and the subject)
    $pm_name = str_replace(['[', ']'], ['(', ')'], $torrent_name);
    $pm_cat  = str_replace(['[', ']'], ['(', ')'], $cat_name);

    while ($user = $db->fetch_array($res)) {
        $language = (string)($user['language'] ?? '') ?: 'english';

        // PM: in the recipient's language, notify_upload_* keys from global.lang.php
        if (str_contains((string)$user['notifs'], '[pm]')) {
            send_pm([
                'subject'       => ['notify_upload_pm_subject', $pm_cat, $pm_name],
                'message'       => ['notify_upload_pm_message', $pm_cat, $torrent_link, $pm_name, $BASEURL],
                'touid'         => (int)$user['id'],
                'language'      => $language,
                'language_file' => NOTIFY_UPLOAD_LANG,
                'sender'        => ['uid' => -1],
            ], -1, true);
        }

        // Email: same language, plain text
        if (str_contains((string)$user['notifs'], '[email]') && !empty($user['email'])) {
            $L = notify_upload_lang($language);
            my_mail(
                (string)$user['email'],
                ags_fmt($L['notify_upload_email_subject'], $cat_name, $torrent_link, $torrent_name, $BASEURL, $SITENAME),
                ags_fmt($L['notify_upload_email_message'], $cat_name, $torrent_link, $torrent_name, $BASEURL, $SITENAME)
            );
        }
    }
}
