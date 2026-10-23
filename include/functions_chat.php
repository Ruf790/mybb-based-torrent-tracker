<?php
declare(strict_types=1);

/**
 * AJAX chat: storage, permissions, the xmlhttp.php endpoints and the chat box HTML.
 *
 *   xmlhttp.php?action=chat_fetch    GET  after=<last id>&since=<server time>&pin=<pin ts>
 *                                         [&dm=<peer uid>&dm_after=&dm_since=&seen=1]
 *                                         new + changed messages, online count, pinned message,
 *                                         the open private dialog, unread private dialogs
 *   xmlhttp.php?action=chat_history  GET  before=<oldest id>[&dm=<peer>]   older messages (scroll up)
 *   xmlhttp.php?action=chat_online   GET                         who has the chat open
 *   xmlhttp.php?action=chat_dm_list  GET                         recent private dialogs
 *   xmlhttp.php?action=chat_dm_user  GET  name=<username>        find a user to write to
 *   xmlhttp.php?action=chat_dm_prefs GET                         "no private messages" + blocked users
 *   xmlhttp.php?action=chat_send     POST text, reply_to[, to_uid]
 *   xmlhttp.php?action=chat_edit     POST id, text
 *   xmlhttp.php?action=chat_delete   POST id
 *   xmlhttp.php?action=chat_report   POST id, reason            report a private message to staff
 *   xmlhttp.php?action=chat_dm_block POST uid (0 = everybody), on (1/0)
 *   xmlhttp.php?action=chat_pin      POST text ('' = unpin)                     (staff)
 *   xmlhttp.php?action=chat_ban      POST uid, minutes (0 = permanent), reason  (staff)
 *   xmlhttp.php?action=chat_unban    POST uid                                   (staff)
 *   xmlhttp.php?action=chat_bans     POST                                       (staff) active bans list
 *
 * Private dialogs: chat_messages.to_uid > 0. Only the two participants can read them. Staff
 * never sees them in the chat, the archive or the search; only a message somebody reported
 * (with a few messages around it) shows up under "Reports" in chat_archive.php.
 *
 * Box:     echo chat_render_box();          (index.php, any page after stdhead)
 *          echo chat_render_box(true);      (chat.php, tall version)
 * Archive: chat_archive.php (staff) uses chat_search() / chat_search_count() / chat_reports_*().
 */

if (!defined('IN_TRACKER')) {
    die('Direct access denied.');
}

const CHAT_PAGE_SIZE       = 50;     // messages on first load / per fetch / per history page
const CHAT_MAX_LENGTH      = 1000;   // characters (messages and the pinned message)
const CHAT_FLOOD_SECONDS   = 3;      // between two messages of one user (staff exempt)
const CHAT_EDIT_WINDOW     = 600;    // own message: edit / delete within N seconds
const CHAT_POLL_MS         = 4000;   // visible tab
const CHAT_POLL_HIDDEN_MS  = 20000;  // background tab
const CHAT_ASSET_VER       = 10;
const CHAT_GROUP_SECONDS   = 120;    // messages of one author within N s share avatar/name; 0 = never group
const CHAT_TEXTAREA_ID     = 'chatText'; // id the BBCode editor binds to (one chat box per page)
const CHAT_ONLINE_WINDOW   = 60;     // "in the chat" = polled within N seconds (hidden tabs poll every 20 s)
const CHAT_PRESENCE_WRITE  = 15;     // presence row is rewritten at most once per N seconds
const CHAT_SNIPPET_LENGTH  = 90;     // reply preview / notification text
const CHAT_ARCHIVE_PERPAGE = 50;
const CHAT_DM_LIST_MAX     = 30;     // recent dialogs in the "new dialog" window
const CHAT_REPORT_CONTEXT  = 5;      // messages before / after a reported one that staff sees

// ── Language ─────────────────────────────────────────────

function chat_lang(): array
{
    global $lang;
    if (is_object($lang) && empty($lang->chat)) {
        $lang->load('chat');
    }
    return is_object($lang) && is_array($lang->chat ?? null) ? $lang->chat : [];
}

/** Lang string with {1} (or %1$s after $lang->load()) filled in. */
function chat_t(string $key, string $fallback, string|int ...$args): string
{
    $text = (string)(chat_lang()[$key] ?? $fallback);
    foreach ($args as $i => $v) {
        $n    = $i + 1;
        $text = str_replace(['{' . $n . '}', '%' . $n . '$s'], (string)$v, $text);
    }
    return $text;
}

// ── Permissions ──────────────────────────────────────────

function chat_banned_group(): int
{
    return defined('UC_BANNED') ? (int)UC_BANNED : 9;
}

function chat_can_read(mixed $user): bool
{
    return is_array($user) && (int)($user['id'] ?? 0) > 0 && (int)($user['usergroup'] ?? 0) !== chat_banned_group();
}

function chat_is_mod(): bool
{
    global $usergroups;
    return function_exists('is_mod') && is_mod($usergroups);
}

/** Staff group ids, lowest to highest rank (UC_MODERATOR < UC_ADMINISTRATOR < UC_SYSOP). */
function chat_staff_rank(int $group): int
{
    $ranks = [
        defined('UC_MODERATOR')     ? (int)UC_MODERATOR     : 6 => 1,
        defined('UC_ADMINISTRATOR') ? (int)UC_ADMINISTRATOR : 7 => 2,
        defined('UC_SYSOP')         ? (int)UC_SYSOP         : 8 => 3,
    ];
    return $ranks[$group] ?? 0;
}

/** Staff may ban regular users and staff of a lower rank, never an equal or higher one. */
function chat_can_ban(int $myGroup, int $targetGroup): bool
{
    $target = chat_staff_rank($targetGroup);
    return $target === 0 || $target < chat_staff_rank($myGroup);
}

/** Active chat ban of a user, or null. */
function chat_ban(int $uid): ?array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT until, reason FROM chat_bans WHERE uid = ? AND (until = 0 OR until > ?) LIMIT 1',
        [$uid, TIMENOW]
    );
    $r = $q ? $db->fetch_array($q) : null;
    return $r ?: null;
}

function chat_ban_text(array $ban): string
{
    $until = (int)$ban['until'];
    $text  = $until === 0
        ? chat_t('err_banned_forever', 'You are banned from the chat.')
        : chat_t('err_banned_until', 'You are banned from the chat until {1}.', date('d.m.Y H:i', $until));
    return trim((string)$ban['reason']) !== ''
        ? $text . ' ' . chat_t('lbl_reason', 'Reason: {1}', (string)$ban['reason'])
        : $text;
}

// ── Formatting ───────────────────────────────────────────

function chat_parser(): object
{
    static $parser = null;
    if ($parser === null) {
        require_once INC_PATH . '/class_parser.php';
        $parser = new postParser;
    }
    return $parser;
}

function chat_parse(string $message, string $username): string
{
    // [img] and video on: the editor offers them; chat.css keeps them small
    return (string)chat_parser()->parse_message($message, [
        'allow_html'      => 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => 1,
        'allow_imgcode'   => 1,
        'allow_videocode' => 1,
        'filter_badwords' => 1,
        'me_username'     => $username,
        'nl2br'           => 1,
    ]);
}

/** Plain-text preview of a message: BBCode tags out, whitespace collapsed, cut to $len. */
function chat_plain(string $text, int $len = CHAT_SNIPPET_LENGTH): string
{
    $text = preg_replace('~\[quote[^\]]*\].*?\[/quote\]~is', '', $text) ?? $text;
    $text = preg_replace('~\[(img|video)[^\]]*\].*?\[/\1\]~is', '🖼', $text) ?? $text;
    $text = preg_replace('~\[/?[a-z*]+(?:=[^\]]*)?\]~i', '', $text) ?? $text;
    $text = trim(preg_replace('~\s+~u', ' ', $text) ?? $text);
    return mb_strimwidth($text, 0, $len, '…');
}

function chat_user_html(int $uid, string $username, int $group): string
{
    global $BASEURL;
    $e    = htmlspecialchars($username, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = function_exists('format_name') ? (string)format_name($e, $group) : $e;
    $url  = function_exists('get_profile_link') ? (string)get_profile_link($uid) : 'member.php?action=profile&uid=' . $uid;
    if (!preg_match('~^(https?:)?//~i', $url)) {
        $url = rtrim((string)$BASEURL, '/') . '/' . ltrim($url, '/');
    }
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" class="ch-user" data-name="' . $e . '">' . $name . '</a>';
}

/** Avatar URL (MyBB format_avatar() when present), '' if the user has none. */
function chat_avatar(string $avatar): string
{
    global $BASEURL;
    if ($avatar === '') return '';
    if (function_exists('format_avatar')) {
        $a = format_avatar($avatar, '', '');
        $avatar = (string)($a['image'] ?? $avatar);
    }
    if (!preg_match('~^(https?:)?//~i', $avatar)) {
        $avatar = rtrim((string)$BASEURL, '/') . '/' . ltrim(preg_replace('~^\./~', '', $avatar), '/');
    }
    return $avatar;
}

/** Message date for server-rendered pages (archive): the site's own date helper when present. */
function chat_date(int $ts): string
{
    return function_exists('my_datee') ? (string)my_datee('normal', $ts) : date('d.m.Y H:i', $ts);
}

/**
 * One message for the client. Raw text only for rows the viewer may edit.
 * Time is sent as 'ts' only: the browser formats it in the viewer's time zone.
 */
function chat_format(array $r, array $me, bool $mod): array
{
    $uid     = (int)$r['uid'];
    $myId    = (int)$me['id'];
    $mine    = $uid === $myId;
    $deleted = (int)$r['deleted'] === 1;
    $fresh   = TIMENOW - (int)$r['added'] <= CHAT_EDIT_WINDOW;
    $msg     = (string)$r['message'];
    $user    = (string)($r['username'] ?? '');
    $dm      = (int)($r['to_uid'] ?? 0) > 0;
    // Staff powers end at private dialogs: there only the author may edit / delete (in the usual window)
    $power   = $mod && !$dm;

    $out = [
        'id'      => (int)$r['id'],
        'uid'     => $uid,
        'ts'      => (int)$r['added'],
        'user'    => $user !== '' ? chat_user_html($uid, $user, (int)($r['usergroup'] ?? 2)) : '',
        'name'    => $user,
        'deleted' => $deleted,
        'dm'      => $dm,
    ];
    if ($deleted) return $out;

    $reply = null;
    if ((int)($r['reply_to'] ?? 0) > 0) {
        $rp    = $r['_reply'] ?? null;
        $reply = is_array($rp)
            ? ['id' => (int)$rp['id'], 'uid' => (int)$rp['uid'], 'name' => (string)($rp['username'] ?? ''),
               'text' => (int)$rp['deleted'] === 1 ? '' : chat_plain((string)$rp['message']), 'gone' => (int)$rp['deleted'] === 1]
            : ['id' => (int)$r['reply_to'], 'uid' => 0, 'name' => '', 'text' => '', 'gone' => true];
    }

    $myName  = (string)($me['username'] ?? '');
    $mention = !$dm && !$mine && $myName !== ''
        && (bool)preg_match('/(?<![\p{L}\p{N}_@])@' . preg_quote($myName, '/') . '(?![\p{L}\p{N}_])/iu', $msg);
    $repliedToMe = !$dm && !$mine && $reply !== null && $reply['uid'] === $myId;

    $out += [
        'html'       => chat_parse($msg, $user),
        'avatar'     => chat_avatar((string)($r['avatar'] ?? '')),
        'edited'     => (int)$r['edited_at'] > 0,
        'mine'       => $mine,
        'mention'    => $mention || $repliedToMe,
        'reply'      => $reply,
        'snippet'    => chat_plain($msg),
        'can_edit'   => $power || ($mine && $fresh),
        'can_delete' => $power || ($mine && $fresh),
        'can_report' => $dm && !$mine,
    ];
    if ($out['can_edit']) $out['raw'] = $msg;
    return $out;
}

// ── Reading ──────────────────────────────────────────────

function chat_cols(): string
{
    return 'm.id, m.uid, m.to_uid, m.message, m.added, m.edited_at, m.edited_by, m.deleted, m.reply_to,
            u.username, u.usergroup, u.avatar
       FROM chat_messages m LEFT JOIN users u ON u.id = m.uid';
}

/** Conversation a message belongs to: 'pub' for the public chat, 'a:b' (lower uid first) for a dialog. */
function chat_conv_key(array $r): string
{
    $to = (int)($r['to_uid'] ?? 0);
    if ($to === 0) return 'pub';
    $a = (int)$r['uid'];
    return min($a, $to) . ':' . max($a, $to);
}

/** May this user read this message? Public: everyone who can read the chat. Private: only the two of them. */
function chat_can_see(array $r, int $uid): bool
{
    $to = (int)($r['to_uid'] ?? 0);
    return $to === 0 || (int)$r['uid'] === $uid || $to === $uid;
}

/**
 * Adds '_reply' (the quoted message) to every row that answers another one: one query for all.
 * A quote is only shown when it is in the same conversation: a public message can never
 * show a private one (and the other way round), whatever reply_to says.
 */
function chat_attach_replies(array $rows): array
{
    global $db;
    $ids = array_values(array_unique(array_filter(array_map(static fn(array $r): int => (int)($r['reply_to'] ?? 0), $rows))));
    if (!$ids) return $rows;

    $q = $db->sql_query_prepared(
        'SELECT m.id, m.uid, m.to_uid, m.message, m.deleted, u.username
           FROM chat_messages m LEFT JOIN users u ON u.id = m.uid
          WHERE m.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        $ids
    );
    $map = [];
    while ($q && ($r = $db->fetch_array($q))) $map[(int)$r['id']] = $r;

    foreach ($rows as &$row) {
        $to = (int)($row['reply_to'] ?? 0);
        if ($to <= 0) continue;
        $quoted = $map[$to] ?? null;
        $row['_reply'] = ($quoted && chat_conv_key($quoted) === chat_conv_key($row)) ? $quoted : null;
    }
    unset($row);
    return $rows;
}

/** WHERE part + params for one dialog between $me and $peer (both directions). */
function chat_dm_where(int $me, int $peer): array
{
    return ['((m.uid = ? AND m.to_uid = ?) OR (m.uid = ? AND m.to_uid = ?))', [$me, $peer, $peer, $me]];
}

/**
 * The public feed ($peer = 0) or one private dialog.
 * after = 0: the last CHAT_PAGE_SIZE messages (+ whether older ones exist).
 * after > 0: messages newer than it, plus older ones edited / deleted since $since.
 * @return array{0: array, 1: bool} rows, more
 */
function chat_feed(int $me, int $peer, int $after, int $since): array
{
    global $db;
    $cols = chat_cols();
    $rows = [];
    [$conv, $cp] = $peer > 0 ? chat_dm_where($me, $peer) : ['m.to_uid = 0', []];

    if ($after <= 0) {
        $q = $db->sql_query_prepared("SELECT {$cols} WHERE {$conv} AND m.deleted = 0 ORDER BY m.id DESC LIMIT " . (CHAT_PAGE_SIZE + 1), $cp);
        while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
        $more = count($rows) > CHAT_PAGE_SIZE;
        return [chat_attach_replies(array_reverse(array_slice($rows, 0, CHAT_PAGE_SIZE))), $more];
    }

    $q = $db->sql_query_prepared("SELECT {$cols} WHERE {$conv} AND m.id > ? ORDER BY m.id ASC LIMIT " . CHAT_PAGE_SIZE, [...$cp, $after]);
    while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;

    if ($since > 0) {
        $q = $db->sql_query_prepared("SELECT {$cols} WHERE {$conv} AND m.id <= ? AND m.edited_at >= ? ORDER BY m.id ASC LIMIT 200", [...$cp, $after, $since]);
        while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
    }
    return [chat_attach_replies($rows), false];
}

/** Public feed (kept for code that called it before private dialogs existed). */
function chat_rows(int $after, int $since): array
{
    return chat_feed(0, 0, $after, $since);
}

/**
 * One page of older messages for scrolling up: public ($peer = 0) or a dialog.
 * @return array{0: array, 1: bool} rows (oldest first), more
 */
function chat_history(int $before, int $me = 0, int $peer = 0): array
{
    global $db;
    $rows = [];
    [$conv, $cp] = $peer > 0 ? chat_dm_where($me, $peer) : ['m.to_uid = 0', []];
    $q = $db->sql_query_prepared(
        'SELECT ' . chat_cols() . " WHERE {$conv} AND m.id < ? AND m.deleted = 0 ORDER BY m.id DESC LIMIT " . (CHAT_PAGE_SIZE + 1),
        [...$cp, $before]
    );
    while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
    $more = count($rows) > CHAT_PAGE_SIZE;
    return [chat_attach_replies(array_reverse(array_slice($rows, 0, CHAT_PAGE_SIZE))), $more];
}

/** One message, any conversation: callers check chat_can_see() before showing it. */
function chat_row(int $id): ?array
{
    global $db;
    $q = $db->sql_query_prepared('SELECT ' . chat_cols() . ' WHERE m.id = ?', [$id]);
    $r = $q ? $db->fetch_array($q) : null;
    return $r ? chat_attach_replies([$r])[0] : null;
}

// ── Pictures uploaded from the chat editor ───────────────

/**
 * Deletes the pictures uploaded for these chat messages: the files in /uploads/ and their
 * comment_files rows. Only files whose path really is an editor upload (…/uploads/<32 hex>.<ext>)
 * are unlinked, so a strange file_path can never delete anything else.
 * Returns how many uploads were removed.
 */
function chat_delete_uploads(array $msgIds): int
{
    global $db;
    $msgIds = array_values(array_unique(array_filter(array_map('intval', $msgIds), static fn(int $i): bool => $i > 0)));
    if (!$msgIds) return 0;

    try {
        $q = $db->sql_query_prepared(
            'SELECT id, file_path FROM comment_files WHERE chat_id IN (' . implode(',', array_fill(0, count($msgIds), '?')) . ')',
            $msgIds
        );
        $ids = [];
        while ($q && ($r = $db->fetch_array($q))) {
            $ids[] = (int)$r['id'];
            $path  = str_replace('\\', '/', (string)$r['file_path']);
            if (preg_match('~/uploads/[a-f0-9]{32}\.[a-z0-9]{2,5}$~i', $path) && is_file($path)) {
                @unlink($path);
            }
        }
        if ($ids) {
            $db->sql_query_prepared('DELETE FROM comment_files WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        }
        return count($ids);
    } catch (\Throwable $e) {
        // comment_files.chat_id not added yet, or the file is locked: the message delete itself is fine
        error_log('chat_delete_uploads: ' . $e->getMessage());
        return 0;
    }
}

/** Does this message have an open report? Then its pictures are kept as evidence until staff handles it. */
function chat_has_open_report(int $msgId): bool
{
    global $db;
    try {
        $q = $db->sql_query_prepared('SELECT 1 FROM chat_reports WHERE msg_id = ? AND handled_at = 0 LIMIT 1', [$msgId]);
        return $q && $db->num_rows($q) > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Links the pictures in a chat message to it: comment_files.chat_id = message id.
 * upload_image.php saves an editor upload with no target (the message doesn't exist yet);
 * here every [img] of this message that points to an upload of the same user without a
 * target gets the message id. Someone else's upload or one already used elsewhere is left alone.
 *
 * Matched by the file name only (32 hex chars + extension, unique), not by the whole URL:
 * behind the reverse proxy upload_image.php may store http:// while the message has https://,
 * with or without www.
 * "No target" = NULL or 0: depending on the DB driver a PHP null may have been stored as 0.
 */
function chat_link_uploads(int $msgId, int $uid, string $text, array $fileIds = []): void
{
    global $db;
    // "No target yet" (NULL or 0) and uploaded by this user: never re-link someone else's or a used file
    $free = 'user_id = ?
                AND COALESCE(chat_id, 0) = 0 AND COALESCE(comment_id, 0) = 0 AND COALESCE(news_id, 0) = 0
                AND COALESCE(torrent_id, 0) = 0 AND COALESCE(post_id, 0) = 0 AND COALESCE(messages_id, 0) = 0';

    // 1) The ids the editor collected in #fileIdsContainer (file_ids[] after each upload)
    $fileIds = array_slice(array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn(int $i): bool => $i > 0))), 0, 20);
    if ($fileIds) {
        try {
            $db->sql_query_prepared(
                "UPDATE comment_files SET chat_id = ? WHERE id IN (" . implode(',', array_fill(0, count($fileIds), '?')) . ") AND {$free}",
                [$msgId, ...$fileIds, $uid]
            );
        } catch (\Throwable $e) {
            error_log('chat_link_uploads: ' . $e->getMessage());
        }
    }

    // 2) Fallback: the pictures in the text (a picture pasted after a page reload, an edit)
    if (!preg_match_all('~\[img(?:=[^\]]*)?\]\s*([^\[\s]+)\s*\[/img\]~i', $text, $m)) return;

    $names = [];
    foreach ($m[1] as $url) {
        $path = (string)parse_url(html_entity_decode($url), PHP_URL_PATH);
        if (preg_match('~/uploads/([a-f0-9]{32}\.[a-z0-9]{2,5})$~i', $path, $f)) {
            $names[strtolower($f[1])] = true;
        }
    }
    if (!$names) return;
    $names = array_slice(array_keys($names), 0, 20);

    try {
        $db->sql_query_prepared(
            "UPDATE comment_files SET chat_id = ?
              WHERE LOWER(SUBSTRING_INDEX(file_url, '/', -1)) IN (" . implode(',', array_fill(0, count($names), '?')) . ")
                AND {$free}",
            [$msgId, ...$names, $uid]
        );
    } catch (\Throwable $e) {
        // comment_files.chat_id not added yet: the message itself is fine
        error_log('chat_link_uploads: ' . $e->getMessage());
    }
}

// ── Private dialogs ──────────────────────────────────────

/** A user that can take part in a dialog: exists, enabled, not banned. By id or by exact name (any case). */
function chat_dm_user(int $id = 0, string $name = ''): ?array
{
    global $db;
    if ($id > 0) {
        $q = $db->sql_query_prepared('SELECT id, username, usergroup, avatar, enabled FROM users WHERE id = ? LIMIT 1', [$id]);
    } elseif (($name = trim($name)) !== '') {
        $q = $db->sql_query_prepared('SELECT id, username, usergroup, avatar, enabled FROM users WHERE LOWER(username) = ? LIMIT 1', [mb_strtolower($name)]);
    } else {
        return null;
    }
    $u = $q ? $db->fetch_array($q) : null;
    if (!$u || (string)($u['enabled'] ?? 'yes') !== 'yes' || (int)$u['usergroup'] === chat_banned_group()) return null;
    return $u;
}

/** Peer for the client: id, name, coloured name link, avatar. */
function chat_dm_peer(array $u): array
{
    return [
        'uid'    => (int)$u['id'],
        'name'   => (string)$u['username'],
        'user'   => chat_user_html((int)$u['id'], (string)$u['username'], (int)($u['usergroup'] ?? 2)),
        'avatar' => chat_avatar((string)($u['avatar'] ?? '')),
    ];
}

/**
 * Why $from may not write to $to, or null if it may.
 * 'off' = $to accepts private messages from nobody, 'blocked' = $to blocked $from. Staff is never stopped.
 */
function chat_dm_refused(int $to, int $from, bool $fromStaff): ?string
{
    global $db;
    if ($fromStaff) return null;
    $q = $db->sql_query_prepared('SELECT target FROM chat_dm_blocks WHERE uid = ? AND target IN (0, ?)', [$to, $from]);
    $refused = null;
    while ($q && ($r = $db->fetch_array($q))) {
        $refused = (int)$r['target'] === 0 ? ($refused ?? 'off') : 'blocked';
    }
    return $refused;
}

/** Remembers how far $me has read the dialog with $peer (never moves back). */
function chat_dm_mark_read(int $me, int $peer, int $lastId): void
{
    global $db;
    if ($lastId <= 0) return;
    $db->sql_query_prepared(
        'INSERT INTO chat_dm_read (uid, peer, last_id) VALUES (?, ?, ?) AS new
         ON DUPLICATE KEY UPDATE last_id = GREATEST(chat_dm_read.last_id, new.last_id)',
        [$me, $peer, $lastId]
    );
}

/** Dialogs with unread messages for $me: [['uid', 'name', 'user', 'avatar', 'unread'], …]. */
function chat_dm_unread(int $me): array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT m.uid AS peer, COUNT(*) AS c, MAX(m.id) AS last
           FROM chat_messages m
           LEFT JOIN chat_dm_read r ON r.uid = ? AND r.peer = m.uid
          WHERE m.to_uid = ? AND m.deleted = 0 AND m.id > COALESCE(r.last_id, 0)
          GROUP BY m.uid
          ORDER BY last DESC
          LIMIT 50',
        [$me, $me]
    );
    $counts = [];
    while ($q && ($r = $db->fetch_array($q))) $counts[(int)$r['peer']] = (int)$r['c'];
    if (!$counts) return [];

    $ids = array_keys($counts);
    $q = $db->sql_query_prepared(
        'SELECT id, username, usergroup, avatar FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        $ids
    );
    $out = [];
    while ($q && ($u = $db->fetch_array($q))) {
        $out[] = chat_dm_peer($u) + ['unread' => $counts[(int)$u['id']]];
    }
    return $out;
}

/** Recent dialogs of $me, newest first: peers with the last message time and unread count. */
function chat_dm_list(int $me): array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT x.peer, MAX(x.id) AS last, MAX(x.added) AS ts
           FROM (SELECT to_uid AS peer, id, added FROM chat_messages WHERE uid = ? AND to_uid > 0 AND deleted = 0
                 UNION ALL
                 SELECT uid AS peer, id, added FROM chat_messages WHERE to_uid = ? AND deleted = 0) x
          GROUP BY x.peer
          ORDER BY last DESC
          LIMIT ' . CHAT_DM_LIST_MAX,
        [$me, $me]
    );
    $peers = [];
    while ($q && ($r = $db->fetch_array($q))) $peers[(int)$r['peer']] = (int)$r['ts'];
    if (!$peers) return [];

    $unread = [];
    foreach (chat_dm_unread($me) as $d) $unread[$d['uid']] = $d['unread'];

    $ids = array_keys($peers);
    $q = $db->sql_query_prepared(
        'SELECT id, username, usergroup, avatar FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        $ids
    );
    $users = [];
    while ($q && ($u = $db->fetch_array($q))) $users[(int)$u['id']] = $u;

    $out = [];
    foreach ($peers as $id => $ts) {           // keep the "newest first" order of the first query
        if (!isset($users[$id])) continue;
        $out[] = chat_dm_peer($users[$id]) + ['ts' => $ts, 'unread' => $unread[$id] ?? 0];
    }
    return $out;
}

/** "No private messages from anybody" switch + the users $me blocked. */
function chat_dm_prefs(int $me): array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT b.target, u.id, u.username, u.usergroup, u.avatar
           FROM chat_dm_blocks b LEFT JOIN users u ON u.id = b.target
          WHERE b.uid = ? ORDER BY b.added DESC LIMIT 200',
        [$me]
    );
    $off = false;
    $blocked = [];
    while ($q && ($r = $db->fetch_array($q))) {
        if ((int)$r['target'] === 0) { $off = true; continue; }
        if ($r['id'] !== null) $blocked[] = chat_dm_peer($r);
    }
    return ['off' => $off, 'blocked' => $blocked];
}

// ── Reports (the only way staff sees a private message) ──

function chat_reports_open(): int
{
    global $db;
    $q = $db->sql_query_prepared('SELECT COUNT(DISTINCT msg_id) AS c FROM chat_reports WHERE handled_at = 0', []);
    return (int)(($q ? $db->fetch_array($q) : [])['c'] ?? 0);
}

/**
 * Reported messages, one entry per message (several reports on it are merged), open first.
 * Each entry: message row, reporters [name, reason, added], context rows around it in the same dialog.
 */
function chat_reports_list(bool $open, int $limit, int $offset): array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT msg_id, MIN(added) AS first, MAX(handled_at) AS handled_at, MAX(handled_by) AS handled_by
           FROM chat_reports
          WHERE ' . ($open ? 'handled_at = 0' : 'handled_at > 0') . '
          GROUP BY msg_id
          ORDER BY first ' . ($open ? 'ASC' : 'DESC') . '
          LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
        []
    );
    $items = [];
    while ($q && ($r = $db->fetch_array($q))) $items[(int)$r['msg_id']] = $r;
    if (!$items) return [];

    $out = [];
    foreach ($items as $msgId => $it) {
        $msg = chat_row($msgId);
        $rq  = $db->sql_query_prepared(
            'SELECT c.reporter, c.reason, c.added, u.username, u.usergroup
               FROM chat_reports c LEFT JOIN users u ON u.id = c.reporter
              WHERE c.msg_id = ? ORDER BY c.added ASC',
            [$msgId]
        );
        $reporters = [];
        while ($rq && ($x = $db->fetch_array($rq))) $reporters[] = $x;

        $handler = '';
        if ((int)$it['handled_by'] > 0) {
            $hq = $db->sql_query_prepared('SELECT username FROM users WHERE id = ?', [(int)$it['handled_by']]);
            $handler = (string)(($hq ? $db->fetch_array($hq) : [])['username'] ?? '');
        }
        $out[] = [
            'msg_id'     => $msgId,
            'msg'        => $msg,
            'reporters'  => $reporters,
            'context'    => $msg ? chat_report_context($msg) : [],
            'handled_at' => (int)$it['handled_at'],
            'handled_by' => $handler,
        ];
    }
    return $out;
}

function chat_reports_count(bool $open): int
{
    global $db;
    $q = $db->sql_query_prepared('SELECT COUNT(DISTINCT msg_id) AS c FROM chat_reports WHERE ' . ($open ? 'handled_at = 0' : 'handled_at > 0'), []);
    return (int)(($q ? $db->fetch_array($q) : [])['c'] ?? 0);
}

/** CHAT_REPORT_CONTEXT messages before and after a reported one, from the same dialog only, oldest first. */
function chat_report_context(array $msg): array
{
    global $db;
    $to = (int)$msg['to_uid'];
    if ($to === 0) return [$msg];
    [$conv, $cp] = chat_dm_where((int)$msg['uid'], $to);
    $id   = (int)$msg['id'];
    $rows = [];

    $q = $db->sql_query_prepared('SELECT ' . chat_cols() . " WHERE {$conv} AND m.id < ? ORDER BY m.id DESC LIMIT " . CHAT_REPORT_CONTEXT, [...$cp, $id]);
    while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
    $rows   = array_reverse($rows);
    $rows[] = $msg;
    $q = $db->sql_query_prepared('SELECT ' . chat_cols() . " WHERE {$conv} AND m.id > ? ORDER BY m.id ASC LIMIT " . CHAT_REPORT_CONTEXT, [...$cp, $id]);
    while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
    return chat_attach_replies($rows);
}

// ── Presence ("who is in the chat") ──────────────────────

/** Called on every poll; the row is rewritten at most once per CHAT_PRESENCE_WRITE seconds. */
function chat_touch_presence(int $uid): void
{
    global $db;
    $db->sql_query_prepared(
        'INSERT INTO chat_presence (uid, last_seen) VALUES (?, ?) AS new
         ON DUPLICATE KEY UPDATE last_seen = IF(new.last_seen >= chat_presence.last_seen + ?, new.last_seen, chat_presence.last_seen)',
        [$uid, TIMENOW, CHAT_PRESENCE_WRITE]
    );
}

function chat_online_count(): int
{
    global $db;
    $q = $db->sql_query_prepared('SELECT COUNT(*) AS c FROM chat_presence WHERE last_seen >= ?', [TIMENOW - CHAT_ONLINE_WINDOW]);
    return (int)(($q ? $db->fetch_array($q) : [])['c'] ?? 0);
}

/** Everyone with the chat open, alphabetical: [['uid', 'name', 'html'], …] (html from chat_user_html). */
function chat_online_list(): array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT p.uid, u.username, u.usergroup
           FROM chat_presence p JOIN users u ON u.id = p.uid
          WHERE p.last_seen >= ?
          ORDER BY u.username ASC LIMIT 200',
        [TIMENOW - CHAT_ONLINE_WINDOW]
    );
    $out = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $out[] = [
            'uid'  => (int)$r['uid'],
            'name' => (string)$r['username'],
            'html' => chat_user_html((int)$r['uid'], (string)$r['username'], (int)$r['usergroup']),
        ];
    }
    return $out;
}

/** Is the user in the chat right now (polled within CHAT_ONLINE_WINDOW)? */
function chat_is_present(int $uid): bool
{
    global $db;
    $q = $db->sql_query_prepared('SELECT 1 FROM chat_presence WHERE uid = ? AND last_seen >= ? LIMIT 1', [$uid, TIMENOW - CHAT_ONLINE_WINDOW]);
    return $q && $db->num_rows($q) > 0;
}

// ── Pinned message ───────────────────────────────────────

/** The pinned message row (id = 1) or null. */
function chat_pin_row(): ?array
{
    global $db;
    $q = $db->sql_query_prepared(
        'SELECT p.message, p.uid, p.added, u.username FROM chat_pin p LEFT JOIN users u ON u.id = p.uid WHERE p.id = 1',
        []
    );
    $r = $q ? $db->fetch_array($q) : null;
    return $r ?: null;
}

/** Pin payload for the client. ts is a version: the client only gets the pin when it changed. */
function chat_pin_payload(?array $pin, bool $mod): array
{
    if (!$pin) return ['ts' => 0];
    $out = [
        'ts'   => (int)$pin['added'],
        'html' => chat_parse((string)$pin['message'], (string)($pin['username'] ?? '')),
        'by'   => (string)($pin['username'] ?? ''),
    ];
    if ($mod) $out['raw'] = (string)$pin['message'];
    return $out;
}

// ── Mentions / replies → notification center ─────────────

/** @names in the text that are real, enabled users (max 5, not yourself). Same rules as notify_mentions(). */
function chat_mentions(string $text, int $selfId): array
{
    global $db;
    // Quoting someone is not a mention
    $text = preg_replace('~\[quote[^\]]*\].*?\[/quote\]~is', '', $text) ?? $text;
    if (!preg_match_all('~(?<![\p{L}\p{N}_@])@([\p{L}\p{N}_.\-]{2,32})~u', $text, $m)) return [];
    $names = array_slice(array_values(array_unique(array_map(
        static fn(string $n): string => mb_strtolower(rtrim($n, '.-')), $m[1]
    ))), 0, 5);
    if (!$names) return [];

    $q = $db->sql_query_prepared(
        "SELECT id FROM users WHERE LOWER(username) IN (" . implode(',', array_fill(0, count($names), '?')) . ") AND enabled = 'yes'",
        $names
    );
    $ids = [];
    while ($q && ($r = $db->fetch_array($q))) {
        if ((int)$r['id'] !== $selfId) $ids[] = (int)$r['id'];
    }
    return $ids;
}

/** notify() from the notification center, or false when it is not installed. */
function chat_notify_ready(): bool
{
    if (!function_exists('notify')) {
        $file = INC_PATH . '/functions_notify.php';
        if (!is_file($file)) return false;
        require_once $file;
    }
    return function_exists('notify');
}

/**
 * Bell notification for an @mention (type chat_mention) or a reply (type chat_reply).
 * ref_id = the author, so repeated events from one person merge into one unread row.
 * {3} = a short plain-text snippet of the message.
 */
function chat_notify(string $type, int $toUid, int $fromUid, string $text, string $link = 'chat.php'): void
{
    try {
        if (!chat_notify_ready()) return;       // notification center not installed yet
        // A type the installed functions_notify.php doesn't know yet: notify() skips it by itself
        notify($toUid, $type, $fromUid, $fromUid, [chat_plain($text, 80)], $link);
    } catch (\Throwable $e) {
        // The message is already saved: a bell error must not turn into a chat error
        error_log('chat notify (' . $type . '): ' . $e->getMessage());
    }
}

// ── Archive search (staff) ───────────────────────────────

/**
 * WHERE clause + params from the archive filters:
 *   user (exact name, any case), q (text contains), from / to (Y-m-d), deleted (include deleted).
 * @return array{0: string, 1: array}
 */
function chat_search_where(array $f): array
{
    global $db;
    // Private dialogs are never searchable, not even by staff (reported ones are under "Reports")
    $where  = ['m.to_uid = 0'];
    $params = [];

    $user = trim((string)($f['user'] ?? ''));
    if ($user !== '') {
        $q = $db->sql_query_prepared('SELECT id FROM users WHERE LOWER(username) = ? LIMIT 1', [mb_strtolower($user)]);
        $r = $q ? $db->fetch_array($q) : null;
        $where[]  = 'm.uid = ?';
        $params[] = $r ? (int)$r['id'] : 0;      // unknown name → nothing found, not everything
    }

    $text = trim((string)($f['q'] ?? ''));
    if ($text !== '') {
        $where[]  = 'm.message LIKE ?';
        $params[] = '%' . addcslashes($text, '\\%_') . '%';
    }

    foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
        $d = (string)($f[$key] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($ts = strtotime($d . ($key === 'to' ? ' 23:59:59' : ' 00:00:00'))) !== false) {
            $where[]  = "m.added {$op} ?";
            $params[] = $ts;
        }
    }

    if (empty($f['deleted'])) {
        $where[] = 'm.deleted = 0';
    }

    return [implode(' AND ', $where), $params];
}

function chat_search_count(array $f): int
{
    global $db;
    [$where, $params] = chat_search_where($f);
    $q = $db->sql_query_prepared("SELECT COUNT(*) AS c FROM chat_messages m WHERE {$where}", $params);
    return (int)(($q ? $db->fetch_array($q) : [])['c'] ?? 0);
}

/** Newest first; rows carry '_reply' and the editor's name (edited_name). */
function chat_search(array $f, int $limit, int $offset): array
{
    global $db;
    [$where, $params] = chat_search_where($f);
    $q = $db->sql_query_prepared(
        'SELECT m.id, m.uid, m.to_uid, m.message, m.added, m.edited_at, m.edited_by, m.deleted, m.reply_to,
                u.username, u.usergroup, u.avatar, e.username AS edited_name
           FROM chat_messages m
           LEFT JOIN users u ON u.id = m.uid
           LEFT JOIN users e ON e.id = m.edited_by
          WHERE ' . $where . '
          ORDER BY m.id DESC
          LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
        $params
    );
    $rows = [];
    while ($q && ($r = $db->fetch_array($q))) $rows[] = $r;
    return chat_attach_replies($rows);
}

/** KPI numbers for the archive header. */
function chat_stats(): array
{
    global $db;
    $one = static function (string $sql, array $p = []) use ($db): int {
        $q = $db->sql_query_prepared($sql, $p);
        return (int)(($q ? $db->fetch_array($q) : [])['c'] ?? 0);
    };
    $midnight = (int)strtotime('today', TIMENOW);
    return [
        'total'   => $one('SELECT COUNT(*) AS c FROM chat_messages WHERE to_uid = 0 AND deleted = 0'),
        'today'   => $one('SELECT COUNT(*) AS c FROM chat_messages WHERE to_uid = 0 AND added >= ? AND deleted = 0', [$midnight]),
        'bans'    => $one('SELECT COUNT(*) AS c FROM chat_bans WHERE until = 0 OR until > ?', [TIMENOW]),
        'online'  => chat_online_count(),
        'reports' => chat_reports_open(),
    ];
}

// ── Endpoints ────────────────────────────────────────────

function chat_json(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function chat_fail(string $key, string $fallback, string|int ...$args): never
{
    chat_json(['ok' => false, 'error' => chat_t($key, $fallback, ...$args)]);
}

function chat_text_from_post(bool $allowEmpty = false): string
{
    $text = str_replace("\r\n", "\n", trim((string)($_POST['text'] ?? '')));
    if ($text === '' && !$allowEmpty) chat_fail('err_empty', 'Write something first.');
    if (mb_strlen($text) > CHAT_MAX_LENGTH) chat_fail('err_too_long', 'The message is too long (max {1} characters).', CHAT_MAX_LENGTH);
    return $text;
}

function chat_ajax(string $action): never
{
    global $db, $CURUSER;

    $me = is_array($CURUSER ?? null) ? $CURUSER : [];
    if (!chat_can_read($me)) chat_fail('err_login', 'Log in to use the chat.');

    $uid    = (int)$me['id'];
    $mod    = chat_is_mod();
    $format = static fn(array $r): array => chat_format($r, $me, $mod);

    // ── GET: reading ──
    if ($action === 'chat_fetch') {
        $after = max(0, (int)($_GET['after'] ?? 0));
        $since = max(0, (int)($_GET['since'] ?? 0));
        $pinTs = max(0, (int)($_GET['pin'] ?? 0));
        $peer  = max(0, (int)($_GET['dm'] ?? 0));

        chat_touch_presence($uid);
        [$rows, $more] = chat_feed($uid, 0, $after, $since);
        $ban = chat_ban($uid);
        $pin = chat_pin_row();

        $out = [
            'ok'       => true,
            'now'      => TIMENOW,
            'messages' => array_map($format, $rows),
            'banned'   => $ban ? chat_ban_text($ban) : null,
            'online'   => chat_online_count(),
        ];
        if ($after === 0) $out['more'] = $more;
        if ((int)($pin['added'] ?? 0) !== $pinTs) $out['pin'] = chat_pin_payload($pin, $mod);

        // The private dialog open in the box
        if ($peer > 0 && $peer !== $uid) {
            $dmAfter = max(0, (int)($_GET['dm_after'] ?? 0));
            [$dmRows, $dmMore] = chat_feed($uid, $peer, $dmAfter, max(0, (int)($_GET['dm_since'] ?? 0)));
            $dm = ['peer' => $peer, 'messages' => array_map($format, $dmRows)];
            if ($dmAfter === 0) {
                $dm['more'] = $dmMore;
                $pu = chat_dm_user($peer);
                $dm['user'] = $pu ? chat_dm_peer($pu) : null;     // null: the account is gone / banned
            }
            // Read only when the viewer actually looks at it (tab visible)
            if (!empty($_GET['seen'])) {
                $top = $dmAfter;
                foreach ($dmRows as $r) $top = max($top, (int)$r['id']);
                chat_dm_mark_read($uid, $peer, $top);
            }
            $out['dm'] = $dm;
        }
        $out['dms'] = chat_dm_unread($uid);
        if ($mod) $out['reports'] = chat_reports_open();
        chat_json($out);
    }

    if ($action === 'chat_history') {
        $peer = max(0, (int)($_GET['dm'] ?? 0));
        [$rows, $more] = chat_history(max(1, (int)($_GET['before'] ?? 0)), $uid, $peer === $uid ? 0 : $peer);
        chat_json(['ok' => true, 'messages' => array_map($format, $rows), 'more' => $more]);
    }

    if ($action === 'chat_online') {
        chat_json(['ok' => true, 'users' => chat_online_list()]);
    }

    if ($action === 'chat_dm_list') {
        chat_json(['ok' => true, 'dialogs' => chat_dm_list($uid)]);
    }

    if ($action === 'chat_dm_user') {
        $u = chat_dm_user(max(0, (int)($_GET['uid'] ?? 0)), mb_substr((string)($_GET['name'] ?? ''), 0, 64));
        if (!$u) chat_fail('err_user', 'User not found.');
        if ((int)$u['id'] === $uid) chat_fail('err_dm_self', 'You cannot write to yourself.');
        chat_json(['ok' => true, 'user' => chat_dm_peer($u)]);
    }

    if ($action === 'chat_dm_prefs') {
        chat_json(['ok' => true] + chat_dm_prefs($uid));
    }

    // ── POST: changing ──
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
        chat_fail('err_token', 'Security token expired. Reload the page.');
    }

    switch ($action) {
        case 'chat_send':
            if ($ban = chat_ban($uid)) chat_json(['ok' => false, 'error' => chat_ban_text($ban)]);
            $text = chat_text_from_post();

            if (!$mod) {
                $q    = $db->sql_query_prepared('SELECT MAX(added) AS t FROM chat_messages WHERE uid = ?', [$uid]);
                $last = (int)(($q ? $db->fetch_array($q) : [])['t'] ?? 0);
                if (TIMENOW - $last < CHAT_FLOOD_SECONDS) {
                    chat_fail('err_flood', 'Not so fast: wait {1} s.', CHAT_FLOOD_SECONDS - (TIMENOW - $last));
                }
            }

            // Private message: the recipient must exist and accept it
            $toUid = max(0, (int)($_POST['to_uid'] ?? 0));
            if ($toUid > 0) {
                if ($toUid === $uid) chat_fail('err_dm_self', 'You cannot write to yourself.');
                $target = chat_dm_user($toUid);
                if (!$target) chat_fail('err_user', 'User not found.');
                $why = chat_dm_refused($toUid, $uid, $mod);
                if ($why === 'off')     chat_fail('err_dm_off', '{1} does not accept private messages.', (string)$target['username']);
                if ($why === 'blocked') chat_fail('err_dm_blocked', '{1} does not accept your private messages.', (string)$target['username']);
            }

            // Reply: only to a message that still exists (it may have been deleted while typing)
            // and only inside the same conversation (public to public, a dialog to the same dialog)
            $replyTo     = max(0, (int)($_POST['reply_to'] ?? 0));
            $replyAuthor = 0;
            if ($replyTo > 0) {
                $q  = $db->sql_query_prepared('SELECT uid, to_uid FROM chat_messages WHERE id = ? AND deleted = 0', [$replyTo]);
                $rr = $q ? $db->fetch_array($q) : null;
                if ($rr && chat_conv_key($rr) === chat_conv_key(['uid' => $uid, 'to_uid' => $toUid])) {
                    $replyAuthor = (int)$rr['uid'];
                } else {
                    $replyTo = 0;
                }
            }

            $db->sql_query_prepared('INSERT INTO chat_messages (uid, to_uid, message, added, reply_to) VALUES (?, ?, ?, ?, ?)', [$uid, $toUid, $text, TIMENOW, $replyTo]);
            $id = (int)$db->insert_id();
            // file_ids: "1,2,3" from chat.js (the editor's hidden file_ids[] inputs)
            chat_link_uploads($id, $uid, $text, explode(',', (string)($_POST['file_ids'] ?? '')));

            if ($toUid > 0) {
                // One bell item per sender (grouped), and only when the recipient isn't in the chat:
                // there the tab badge already shows it
                if (!chat_is_present($toUid)) {
                    chat_notify('chat_dm', $toUid, $uid, $text, 'chat.php?dm=' . $uid);
                }
            } else {
                // Each person gets one bell item: a mention wins over a reply
                $mentioned = chat_mentions($text, $uid);
                foreach ($mentioned as $to) {
                    chat_notify('chat_mention', $to, $uid, $text);
                }
                if ($replyAuthor > 0 && $replyAuthor !== $uid && !in_array($replyAuthor, $mentioned, true)) {
                    chat_notify('chat_reply', $replyAuthor, $uid, $text);
                }
            }

            $row = chat_row($id);
            chat_json(['ok' => true, 'now' => TIMENOW, 'message' => $row ? $format($row) : null]);

        case 'chat_edit':
        case 'chat_delete':
            $row = chat_row((int)($_POST['id'] ?? 0));
            // Somebody else's private message is "not found", not "no permission": don't confirm it exists
            if (!$row || (int)$row['deleted'] === 1 || !chat_can_see($row, $uid)) chat_fail('err_not_found', 'Message not found.');
            $mine  = (int)$row['uid'] === $uid;
            $fresh = TIMENOW - (int)$row['added'] <= CHAT_EDIT_WINDOW;
            $power = $mod && (int)$row['to_uid'] === 0;   // staff edits / deletes public messages only
            if (!$power && !($mine && $fresh)) chat_fail('err_no_permission', 'You can no longer change this message.');

            if ($action === 'chat_edit') {
                // A chat ban also stops editing your own messages (otherwise the last one could be rewritten)
                if (!$power && ($ban = chat_ban($uid))) chat_json(['ok' => false, 'error' => chat_ban_text($ban)]);
                $text = chat_text_from_post();
                $db->sql_query_prepared('UPDATE chat_messages SET message = ?, edited_at = ?, edited_by = ? WHERE id = ?',
                    [$text, TIMENOW, $uid, (int)$row['id']]);
                chat_link_uploads((int)$row['id'], $uid, $text);   // a picture added while editing
                if (!$mine && function_exists('write_log')) {
                    write_log("[Chat] message #{$row['id']} of {$row['username']} edited by {$me['username']}");
                }
            } else {
                $db->sql_query_prepared('UPDATE chat_messages SET deleted = 1, edited_at = ?, edited_by = ? WHERE id = ?',
                    [TIMENOW, $uid, (int)$row['id']]);
                // Its pictures go too, unless the message is reported (staff needs to see them first)
                if (!chat_has_open_report((int)$row['id'])) {
                    chat_delete_uploads([(int)$row['id']]);
                }
                if (!$mine && function_exists('write_log')) {
                    write_log("[Chat] message #{$row['id']} of {$row['username']} deleted by {$me['username']}: " . mb_strimwidth((string)$row['message'], 0, 120, '…'));
                }
            }
            $row = chat_row((int)$row['id']);
            chat_json(['ok' => true, 'now' => TIMENOW, 'message' => $row ? $format($row) : null]);

        case 'chat_report':
            // Only a private message you received can be reported (public ones staff sees anyway)
            $row = chat_row((int)($_POST['id'] ?? 0));
            if (!$row || (int)$row['deleted'] === 1 || (int)$row['to_uid'] !== $uid) chat_fail('err_not_found', 'Message not found.');
            $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
            // Checked explicitly: affected_rows() after a prepared INSERT IGNORE is not reliable on every driver
            $dq = $db->sql_query_prepared('SELECT 1 FROM chat_reports WHERE msg_id = ? AND reporter = ? LIMIT 1', [(int)$row['id'], $uid]);
            if ($dq && $db->num_rows($dq) > 0) {
                chat_json(['ok' => true, 'info' => chat_t('flash_report_dup', 'You have already reported this message.')]);
            }
            $db->sql_query_prepared(
                'INSERT IGNORE INTO chat_reports (msg_id, reporter, reason, added) VALUES (?, ?, ?, ?)',
                [(int)$row['id'], $uid, $reason, TIMENOW]
            );
            if (function_exists('write_log')) {
                write_log("[Chat] {$me['username']} reported private message #{$row['id']} of {$row['username']}" . ($reason !== '' ? ": {$reason}" : ''));
            }
            chat_json(['ok' => true, 'info' => chat_t('flash_reported', 'Thank you. Staff will look at this message.')]);

        case 'chat_dm_block':
            // uid = 0: "no private messages from anybody"; otherwise one user. on = 1 block, 0 unblock
            $target = max(0, (int)($_POST['uid'] ?? 0));
            $on     = !empty($_POST['on']);
            if ($target === $uid) chat_fail('err_user', 'User not found.');
            if ($target > 0 && $on) {
                $t = chat_dm_user($target);
                if (!$t) chat_fail('err_user', 'User not found.');
            }
            if ($on) {
                $db->sql_query_prepared('INSERT IGNORE INTO chat_dm_blocks (uid, target, added) VALUES (?, ?, ?)', [$uid, $target, TIMENOW]);
            } else {
                $db->sql_query_prepared('DELETE FROM chat_dm_blocks WHERE uid = ? AND target = ?', [$uid, $target]);
            }
            $info = match (true) {
                $target === 0 && $on  => chat_t('flash_dm_off', 'Nobody can write to you privately now (staff still can).'),
                $target === 0         => chat_t('flash_dm_on', 'Private messages are open again.'),
                $on                   => chat_t('flash_blocked', '{1} can no longer write to you privately.', (string)($t['username'] ?? '')),
                default               => chat_t('flash_unblocked', 'The user is unblocked.'),
            };
            chat_json(['ok' => true, 'info' => $info] + chat_dm_prefs($uid));

        case 'chat_pin':
            if (!$mod) chat_fail('err_staff_only', 'Only staff can do this.');
            $text = chat_text_from_post(true);
            if ($text === '') {
                $db->sql_query_prepared('DELETE FROM chat_pin WHERE id = 1', []);
                if (function_exists('write_log')) write_log("[Chat] pinned message removed by {$me['username']}");
                chat_json(['ok' => true, 'pin' => ['ts' => 0], 'info' => chat_t('flash_unpinned', 'The pinned message is removed.')]);
            }
            // added doubles as a version: never the same value twice, even within one second
            $db->sql_query_prepared(
                'INSERT INTO chat_pin (id, message, uid, added) VALUES (1, ?, ?, ?) AS new
                 ON DUPLICATE KEY UPDATE message = new.message, uid = new.uid, added = GREATEST(chat_pin.added + 1, new.added)',
                [$text, $uid, TIMENOW]
            );
            if (function_exists('write_log')) write_log("[Chat] message pinned by {$me['username']}: " . mb_strimwidth($text, 0, 120, '…'));
            chat_json(['ok' => true, 'pin' => chat_pin_payload(chat_pin_row(), true), 'info' => chat_t('flash_pinned', 'The message is pinned.')]);

        case 'chat_bans':
            if (!$mod) chat_fail('err_staff_only', 'Only staff can do this.');
            $q    = $db->sql_query_prepared(
                'SELECT b.uid, b.until, b.reason, u.username, s.username AS by_name
                   FROM chat_bans b
                   LEFT JOIN users u ON u.id = b.uid
                   LEFT JOIN users s ON s.id = b.banned_by
                  WHERE b.until = 0 OR b.until > ?
                  ORDER BY b.added DESC LIMIT 200',
                [TIMENOW]
            );
            $bans = [];
            while ($q && ($r = $db->fetch_array($q))) {
                $bans[] = [
                    'uid'    => (int)$r['uid'],
                    'name'   => (string)($r['username'] ?? ('#' . $r['uid'])),
                    'until'  => (int)$r['until'],
                    'reason' => (string)$r['reason'],
                    'by'     => (string)($r['by_name'] ?? ''),
                ];
            }
            chat_json(['ok' => true, 'bans' => $bans]);

        case 'chat_ban':
        case 'chat_unban':
            if (!$mod) chat_fail('err_staff_only', 'Only staff can do this.');
            $target = (int)($_POST['uid'] ?? 0);
            $q      = $db->sql_query_prepared('SELECT id, username, usergroup FROM users WHERE id = ?', [$target]);
            $t      = $q ? $db->fetch_array($q) : null;
            if (!$t || $target === $uid) chat_fail('err_user', 'User not found.');
            if ($action === 'chat_ban' && !chat_can_ban((int)($me['usergroup'] ?? 0), (int)$t['usergroup'])) {
                chat_fail('err_ban_staff', 'You cannot ban a member of staff of your rank or higher.');
            }

            if ($action === 'chat_unban') {
                $db->sql_query_prepared('DELETE FROM chat_bans WHERE uid = ?', [$target]);
                if (function_exists('write_log')) write_log("[Chat] {$t['username']} unbanned by {$me['username']}");
                chat_json(['ok' => true, 'info' => chat_t('flash_unbanned', '{1} can write in the chat again.', (string)$t['username'])]);
            }

            $minutes = max(0, min(525600, (int)($_POST['minutes'] ?? 60)));
            $until   = $minutes === 0 ? 0 : TIMENOW + $minutes * 60;
            $reason  = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
            $db->sql_query_prepared(
                'INSERT INTO chat_bans (uid, until, reason, banned_by, added) VALUES (?, ?, ?, ?, ?) AS new
                 ON DUPLICATE KEY UPDATE until = new.until, reason = new.reason, banned_by = new.banned_by, added = new.added',
                [$target, $until, $reason, $uid, TIMENOW]
            );
            if (function_exists('write_log')) {
                write_log("[Chat] {$t['username']} banned by {$me['username']} " . ($minutes === 0 ? 'permanently' : "for {$minutes} min") . ($reason !== '' ? ": {$reason}" : ''));
            }
            chat_json(['ok' => true, 'info' => $minutes === 0
                ? chat_t('flash_banned_forever', '{1} is banned from the chat.', (string)$t['username'])
                : chat_t('flash_banned', '{1} is banned from the chat until {2}.', (string)$t['username'], date('d.m.Y H:i', $until))]);
    }

    chat_fail('err_unknown', 'Unknown action.');
}

// ── Box ──────────────────────────────────────────────────

/**
 * Smilies for insert_bbcode_editor() in its format: [code => file name in pic/smilies/]
 * (bbcode_tools.js builds "/pic/smilies/" + file).
 * Same source as the comment editor: the TS $smilies array (global or include/smilies.php);
 * the MyBB smilies table only as a last resort.
 */
function chat_editor_smilies(): array
{
    global $db;

    $list = $GLOBALS['smilies'] ?? null;
    if (!is_array($list) || !$list) {
        // Same file the comment editor loads: require_once 'cache/smilies.php'
        foreach ([TSDIR . '/cache/smilies.php', INC_PATH . '/smilies.php'] as $file) {
            if (is_file($file)) {
                $smilies = null;
                include $file;               // defines $smilies
                if (is_array($smilies) && $smilies) { $list = $smilies; break; }
            }
        }
    }
    if (is_array($list) && $list) {
        $out = [];
        foreach ($list as $code => $file) {
            if (is_array($file)) {                       // cache row: ['find' => ':)', 'image' => '...']
                $code = (string)strtok((string)($file['find'] ?? ''), "\n");
                $file = (string)($file['image'] ?? '');
            }
            if (is_string($code) && $code !== '' && is_string($file) && $file !== '') {
                $out[$code] = basename($file);
            }
        }
        if ($out) return $out;
    }

    $out = [];
    $q = $db->sql_query_prepared('SELECT find, image FROM smilies ORDER BY disporder', []);
    while ($q && ($r = $db->fetch_array($q))) {
        $find = (string)strtok((string)($r['find'] ?? ''), "\n");
        $file = basename((string)($r['image'] ?? ''));
        if ($find !== '' && $file !== '') $out[$find] = $file;
    }
    return $out;
}

/** The site BBCode editor bound to the chat textarea: ['toolbar' => html, 'modal' => html]. */
function chat_editor(): array
{
    global $BASEURL;
    if (!function_exists('insert_bbcode_editor')) {
        $file = INC_PATH . '/editor.php';
        if (is_file($file)) require_once $file;
    }
    if (!function_exists('insert_bbcode_editor')) return ['toolbar' => '', 'modal' => ''];
    $ed = insert_bbcode_editor(chat_editor_smilies(), rtrim((string)$BASEURL, '/'), CHAT_TEXTAREA_ID);
    return ['toolbar' => (string)($ed['toolbar'] ?? ''), 'modal' => (string)($ed['modal'] ?? '')];
}

/** JS strings: js_* keys, prefix stripped, {1} restored after $lang->load() turned it into %1$s. */
function chat_js_lang(): array
{
    $js = [];
    foreach (chat_lang() as $k => $v) {
        if (str_starts_with((string)$k, 'js_')) {
            $js[substr((string)$k, 3)] = (string)preg_replace('/%(\d+)\$s/', '{$1}', (string)$v);
        }
    }
    return $js;
}

function chat_render_box(bool $page = false): string
{
    global $BASEURL, $CURUSER, $mybb;

    if (!chat_can_read($CURUSER ?? null)) return '';

    $e    = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $base = rtrim((string)$BASEURL, '/');
    $key  = function_exists('generate_post_check') ? (string)generate_post_check() : (string)($mybb->post_code ?? '');
    $mod  = chat_is_mod();

    $editor = chat_editor();

    // Strings go in a data attribute, not a global AGS_LANG: the box sits on pages that have their own.
    $cfg = [
        'endpoint' => $base . '/xmlhttp.php',
        'key'      => $key,
        'me'       => (string)($CURUSER['username'] ?? ''),
        'meId'     => (int)($CURUSER['id'] ?? 0),
        'dm'       => $page ? max(0, (int)($_GET['dm'] ?? 0)) : 0,   // chat.php?dm=<uid>: open that dialog (bell link)
        'mod'      => $mod,
        'poll'     => CHAT_POLL_MS,
        'pollBg'   => CHAT_POLL_HIDDEN_MS,
        'max'      => CHAT_MAX_LENGTH,
        'group'    => CHAT_GROUP_SECONDS,
        'lang'     => chat_js_lang(),
    ];

    // SweetAlert2 once per page: the editor loads it and sets AGS_SWAL_LOADED
    $swal = '';
    if (!defined('AGS_SWAL_LOADED')) {
        define('AGS_SWAL_LOADED', true);
        $swal = '<link rel="stylesheet" href="' . $e($base) . '/include/templates/default/style/sweetalert2.min.css">'
              . '<script src="' . $e($base) . '/scripts/sweetalert2.min.js"></script>';
    }

    $iconBtn = static fn(string $attr, string $icon, string $title, string $extra = ''): string =>
        '<button type="button" class="ch-hbtn' . $extra . '" ' . $attr . ' title="' . $e($title) . '" aria-label="' . $e($title) . '"><i class="fa-solid ' . $icon . '"></i></button>';

    $staff = $mod
        ? $iconBtn('data-ch-pin-edit', 'fa-thumbtack', chat_t('tip_pin', 'Pin a message'))
        . '<a class="ch-hbtn ch-hbtn--badge" href="' . $e($base . '/chat_archive.php') . '" title="' . $e(chat_t('tip_archive', 'Chat archive')) . '" aria-label="' . $e(chat_t('tip_archive', 'Chat archive')) . '"><i class="fa-solid fa-box-archive"></i>'
        . '<span class="ch-badge" data-ch-reports hidden></span></a>'
        . $iconBtn('data-ch-bans', 'fa-user-slash', chat_t('tip_bans', 'Chat bans'))
        : '';

    return $swal
        . '<link rel="stylesheet" href="' . $e($base) . '/include/templates/default/style/chat.css?ver=' . CHAT_ASSET_VER . '">'
        . '<section class="ch' . ($page ? ' ch--page' : '') . '" data-chat="' . $e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '">'
        . '<header class="ch-head">'
        . '<span class="ch-head__icon"><i class="fa-solid fa-comments"></i></span>'
        . '<h2 class="ch-title">' . $e(chat_t('sec_title', 'Chat')) . '</h2>'
        . '<button type="button" class="ch-online" data-ch-online title="' . $e(chat_t('tip_online', 'Who is in the chat')) . '" hidden>'
        . '<span class="ch-online__dot"></span><span data-ch-online-count>0</span></button>'
        . '<span class="ch-status" data-ch-status aria-live="polite"></span>'
        . '<span class="ch-tools">'
        . $iconBtn('data-ch-sound', 'fa-volume-high', chat_t('tip_sound', 'Sound on mention'))
        . $iconBtn('data-ch-dm-prefs', 'fa-user-lock', chat_t('tip_dm_prefs', 'Private messages: who can write to me'))
        . $staff
        . (!$page ? '<a class="ch-hbtn" href="' . $e($base . '/chat.php') . '" title="' . $e(chat_t('tip_open_page', 'Open the full chat')) . '" aria-label="' . $e(chat_t('tip_open_page', 'Open the full chat')) . '"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></a>' : '')
        . '</span>'
        . '</header>'
        // Tabs: the public chat + one per open private dialog (built by chat.js)
        . '<nav class="ch-tabs" data-ch-tabs role="tablist" aria-label="' . $e(chat_t('aria_tabs', 'Conversations')) . '">'
        . '<button type="button" class="ch-tab is-active" data-ch-tab="0" role="tab" aria-selected="true">'
        . '<i class="fa-solid fa-users"></i><span class="ch-tab__name">' . $e(chat_t('tab_public', 'Everyone')) . '</span>'
        . '<span class="ch-badge" data-ch-tab-badge hidden></span></button>'
        . '<span class="ch-tabs__dms" data-ch-tabs-dms></span>'
        . '<button type="button" class="ch-tab ch-tab--add" data-ch-dm-new title="' . $e(chat_t('tip_dm_new', 'Private message')) . '" aria-label="' . $e(chat_t('tip_dm_new', 'Private message')) . '">'
        . '<i class="fa-solid fa-envelope"></i><i class="fa-solid fa-plus ch-tab__plus"></i></button>'
        . '</nav>'
        // Shown on a private tab: who it is with + block
        . '<div class="ch-dmbar" data-ch-dmbar hidden>'
        . '<i class="fa-solid fa-lock"></i><span class="ch-dmbar__text" data-ch-dmbar-text></span>'
        . '<button type="button" class="ch-dmbar__btn" data-ch-dm-block>' . '<i class="fa-solid fa-ban"></i><span>' . $e(chat_t('btn_block', 'Block')) . '</span></button>'
        . '</div>'
        . '<div class="ch-pin" data-ch-pin hidden>'
        . '<i class="fa-solid fa-thumbtack ch-pin__icon"></i>'
        . '<div class="ch-pin__body" data-ch-pin-body></div>'
        . ($mod ? $iconBtn('data-ch-pin-remove', 'fa-xmark', chat_t('tip_unpin', 'Unpin'), ' ch-pin__x') : '')
        . '</div>'
        . '<div class="ch-list" data-ch-list role="log" aria-live="polite" aria-label="' . $e(chat_t('aria_messages', 'Chat messages')) . '">'
        . '<div class="ch-older" data-ch-older hidden><i class="fa-solid fa-spinner fa-spin"></i></div>'
        . '<div class="ch-empty" data-ch-empty><i class="fa-solid fa-spinner fa-spin"></i>' . $e(chat_t('lbl_loading', 'Loading…')) . '</div></div>'
        . '<button type="button" class="ch-newer" data-ch-newer hidden><i class="fa-solid fa-arrow-down"></i>' . $e(chat_t('btn_newer', 'New messages')) . '</button>'
        . '<div class="ch-banned" data-ch-banned hidden></div>'
        . '<form class="ch-form" data-ch-form autocomplete="off">'
        . '<input type="hidden" name="my_post_key" value="' . $e($key) . '">'   // editor image upload reads it
        . '<div id="fileIdsContainer" data-ch-file-ids hidden></div>'           // the editor adds file_ids[] here after each upload
        . ($editor['toolbar'] !== '' ? '<div class="ch-toolbar">' . $editor['toolbar'] . '</div>' : '')
        . '<div class="ch-replybar" data-ch-replybar hidden>'
        . '<i class="fa-solid fa-reply"></i><span class="ch-replybar__text" data-ch-replybar-text></span>'
        . $iconBtn('data-ch-reply-cancel', 'fa-xmark', chat_t('tip_reply_cancel', 'Cancel reply'))
        . '</div>'
        . '<div class="ch-compose">'
        . ($editor['toolbar'] !== '' ? $iconBtn('data-ch-tools aria-pressed="false"', 'fa-font', chat_t('tip_tools', 'Formatting and smilies'), ' ch-tools-btn') : '')
        . '<textarea id="' . CHAT_TEXTAREA_ID . '" class="ch-input" data-ch-input rows="1" maxlength="' . CHAT_MAX_LENGTH . '" placeholder="' . $e(chat_t('ph_message', 'Message… (Enter to send, Shift+Enter for a new line)')) . '" aria-label="' . $e(chat_t('aria_input', 'Message')) . '"></textarea>'
        . '<button type="submit" class="ch-send" data-ch-send title="' . $e(chat_t('btn_send', 'Send')) . '" aria-label="' . $e(chat_t('btn_send', 'Send')) . '"><i class="fa-solid fa-paper-plane"></i></button>'
        . '</div>'
        . '</form>'
        . '</section>'
        . $editor['modal']
        . '<script src="' . $e($base) . '/scripts/chat.js?ver=' . CHAT_ASSET_VER . '" defer></script>';
}
