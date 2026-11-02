<?php
declare(strict_types=1);

/**
 * Reactions with the site's own smilies (pic/smilies/), shared by every kind of item.
 *
 *   reactions (type, item_id, uid, rkey, added)   PK (type, item_id, uid, rkey)
 *     type  'chat' = chat_messages.id, 'comment' = comments.id (torrents), 'post' = posts.pid (forum)
 *     rkey  the smilie code (smilies.stext, e.g. ':thumbsup:'), case-sensitive (utf8mb4_bin)
 *
 * react_toggle()  add / remove one reaction of one user
 * react_summary() reactions of many items in one query, ready for JSON
 * react_picker()  quick set + every smilie, for the client's picker
 *
 * Quick set: setting reactions_quick (comma-separated smilie codes), REACT_DEFAULT_QUICK otherwise.
 */

if (!defined('IN_TRACKER')) {
    die('Direct access denied.');
}

const REACT_TYPES         = ['chat', 'comment', 'post'];
const REACT_MAX_PER_USER  = 3;      // different reactions of one user on one item
const REACT_NAMES_MAX     = 15;     // names in the tooltip of one reaction
const REACT_QUICK_MAX     = 8;
const REACT_DEFAULT_QUICK = ':thumbsup:,:love:,:laugh:,:openmoth:,:cry:,:fire:';

/** Smilie code => image URL, from the smilies cache (the same list the parser uses). */
function react_smilies(): array
{
    static $list = null;
    if ($list !== null) return $list;

    global $cache, $BASEURL, $pic_base_url;
    $raw  = is_object($cache) ? ($cache->read('smilies') ?: []) : [];
    $base = rtrim((string)$BASEURL, '/') . '/' . (string)($pic_base_url ?? 'pic/') . 'smilies/';

    $list = [];
    foreach ((array)$raw as $code => $file) {
        $code = (string)$code;
        $file = (string)$file;
        if ($code === '' || $file === '' || mb_strlen($code) > 20) continue;
        $list[$code] = preg_match('~^(https?:)?//~i', $file) ? $file : $base . ltrim($file, '/');
    }
    return $list;
}

function react_valid_key(string $key): bool
{
    return $key !== '' && isset(react_smilies()[$key]);
}

/** Codes of the quick set that really exist (unknown codes in the setting are skipped). */
function react_quick(): array
{
    $setting = trim((string)($GLOBALS['reactions_quick'] ?? ''));
    $codes   = array_map('trim', explode(',', $setting !== '' ? $setting : REACT_DEFAULT_QUICK));
    $codes   = array_values(array_unique(array_filter($codes, 'react_valid_key')));
    return array_slice($codes, 0, REACT_QUICK_MAX);
}

/** For the client: ['quick' => [[k, src]], 'all' => [[k, src]] (one per picture), 'max' => n]. */
function react_picker(): array
{
    $smilies = react_smilies();
    $quick   = array_map(static fn(string $k): array => ['k' => $k, 'src' => $smilies[$k]], react_quick());

    $all = $seen = [];
    foreach ($smilies as $k => $src) {
        if (isset($seen[$src])) continue;          // :yes: and :thumbsup: share one picture
        $seen[$src] = true;
        $all[]      = ['k' => (string)$k, 'src' => $src];
    }
    return ['quick' => $quick, 'all' => $all, 'max' => REACT_MAX_PER_USER];
}

/**
 * Add the reaction, or remove it when the user already has it.
 * Returns 'added', 'removed', 'limit' (REACT_MAX_PER_USER reached) or 'bad' (unknown type / smilie).
 */
function react_toggle(string $type, int $itemId, int $uid, string $key): string
{
    global $db;
    if (!in_array($type, REACT_TYPES, true) || $itemId <= 0 || $uid <= 0 || !react_valid_key($key)) return 'bad';

    $q = $db->sql_query_prepared(
        'SELECT 1 FROM reactions WHERE type = ? AND item_id = ? AND uid = ? AND rkey = ? LIMIT 1',
        [$type, $itemId, $uid, $key]
    );
    if ($q && $db->num_rows($q) > 0) {
        $db->sql_query_prepared('DELETE FROM reactions WHERE type = ? AND item_id = ? AND uid = ? AND rkey = ?', [$type, $itemId, $uid, $key]);
        return 'removed';
    }

    $q = $db->sql_query_prepared('SELECT COUNT(*) AS c FROM reactions WHERE type = ? AND item_id = ? AND uid = ?', [$type, $itemId, $uid]);
    if ((int)(($q ? $db->fetch_array($q) : [])['c'] ?? 0) >= REACT_MAX_PER_USER) return 'limit';

    $db->sql_query_prepared(
        'INSERT IGNORE INTO reactions (type, item_id, uid, rkey, added) VALUES (?, ?, ?, ?, ?)',
        [$type, $itemId, $uid, $key, TIMENOW]
    );
    return 'added';
}

/**
 * Reactions of many items at once: [item_id => [['k', 'src', 'n', 'mine', 'who' => [names], 'more' => n], …]],
 * each item's reactions in the order they were first given. Smilies removed from the site are skipped.
 */
function react_summary(string $type, array $itemIds, int $me): array
{
    global $db;
    $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn(int $i): bool => $i > 0)));
    if (!$ids || !in_array($type, REACT_TYPES, true)) return [];

    $smilies = react_smilies();
    $q = $db->sql_query_prepared(
        'SELECT r.item_id, r.rkey, COUNT(*) AS n, MAX(r.uid = ?) AS mine, MIN(r.added) AS first_at,
                GROUP_CONCAT(u.username ORDER BY r.added SEPARATOR \'\\n\') AS who
           FROM reactions r LEFT JOIN users u ON u.id = r.uid
          WHERE r.type = ? AND r.item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
          GROUP BY r.item_id, r.rkey
          ORDER BY r.item_id, first_at, r.rkey',
        [$me, $type, ...$ids]
    );

    $out = [];
    while ($q && ($r = $db->fetch_array($q))) {
        $key = (string)$r['rkey'];
        if (!isset($smilies[$key])) continue;
        $n     = (int)$r['n'];
        $names = array_values(array_filter(explode("\n", (string)($r['who'] ?? ''))));
        $shown = array_slice($names, 0, REACT_NAMES_MAX);
        $out[(int)$r['item_id']][] = [
            'k'    => $key,
            'src'  => $smilies[$key],
            'n'    => $n,
            'mine' => (int)$r['mine'] === 1,
            'who'  => $shown,
            'more' => max(0, $n - count($shown)),
        ];
    }
    return $out;
}

/** Remove every reaction of these items (the items themselves are being deleted). */
function react_delete_items(string $type, array $itemIds): void
{
    global $db;
    $ids = array_values(array_filter(array_map('intval', $itemIds)));
    if (!$ids) return;
    $db->sql_query_prepared(
        'DELETE FROM reactions WHERE type = ? AND item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        [$type, ...$ids]
    );
}

// ── Reactions block for pages (torrent comments, forum posts; the chat has its own in chat.js) ──

/** Strings, from the 'details' lang (loaded here when the page hasn't), with English fallbacks. */
function react_lang(): array
{
    global $lang;
    if (is_object($lang) && empty($lang->details)) $lang->load('details');
    $d   = is_object($lang) && is_array($lang->details ?? null) ? $lang->details : [];
    $get = static fn(string $k, string $f): string => (string)preg_replace('/%(\d+)\$s/', '{$1}', (string)($d[$k] ?? $f));
    return [
        'add'       => $get('react_add', 'Add a reaction'),
        'all'       => $get('react_all', 'All smilies'),
        'more'      => $get('react_more', 'and {1} more'),
        'remove'    => $get('react_remove', 'Click to remove yours'),
        'err_bad'   => $get('react_err_bad', 'Unknown reaction.'),
        'err_limit' => $get('react_err_limit', 'Up to {1} reactions on one item.'),
        'err_login' => $get('react_err_login', 'Log in to react.'),
        'err_gone'  => $get('react_err_gone', 'This item no longer exists.'),
        'err_token' => $get('react_err_token', 'Security token expired. Reload the page.'),
    ];
}

/** CSS + config + JS, once per page (later calls return ''). Echo it before the first react_bar(). */
function react_assets(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;

    global $BASEURL, $mybb;
    $base = rtrim((string)$BASEURL, '/');
    $e    = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cfg  = react_picker() + [
        'endpoint' => $base . '/xmlhttp.php',
        'key'      => function_exists('generate_post_check') ? (string)generate_post_check() : (string)($mybb->post_code ?? ''),
        'lang'     => react_lang(),
    ];
    return '<link rel="stylesheet" href="' . $e($base) . '/include/templates/default/style/reactions.css?ver=2">'
        . '<script>window.AGS_REACT = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
        . '<script src="' . $e($base) . '/scripts/reactions.js?ver=2" defer></script>';
}

/**
 * Pills + the "add" button of one item. $list = react_summary(...)[$id] ?? [].
 * $canReact = false (guests): the pills only show who reacted, no "add" button.
 */
function react_bar(string $type, int $id, array $list, bool $canReact = true): string
{
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $L = react_lang();

    $html = '<div class="rx" data-rx-type="' . $e($type) . '" data-rx-id="' . $id . '">';
    foreach ($list as $r) {
        $who = implode(', ', (array)$r['who']);
        if ((int)$r['more'] > 0) $who .= ' ' . str_replace('{1}', (string)(int)$r['more'], $L['more']);
        if (!empty($r['mine']))  $who .= "\n" . $L['remove'];
        $html .= '<button type="button" class="rx-pill' . (!empty($r['mine']) ? ' is-mine' : '') . '" data-key="' . $e((string)$r['k']) . '" title="' . $e($who) . '"' . ($canReact ? '' : ' disabled') . '>'
               . '<img src="' . $e((string)$r['src']) . '" alt="' . $e((string)$r['k']) . '" loading="lazy">'
               . '<span class="rx-n">' . (int)$r['n'] . '</span></button>';
    }
    if ($canReact) {
        $html .= '<button type="button" class="rx-add" title="' . $e($L['add']) . '" aria-label="' . $e($L['add']) . '">'
               . '<i class="fa-regular fa-face-smile"></i><i class="fa-solid fa-plus rx-add__plus"></i></button>';
    } elseif (!$list) {
        return '';                                 // a guest and nothing to show
    }
    return $html . '</div>';
}

/** Does the item exist (and may the viewer react to it)? Chat messages go through chat_react instead. */
function react_item_exists(string $type, int $id): bool
{
    global $db;
    $sql = match ($type) {
        'comment' => 'SELECT 1 FROM comments WHERE id = ? LIMIT 1',
        // Only approved posts of approved threads
        'post'    => 'SELECT 1 FROM posts p JOIN threads t ON t.tid = p.tid WHERE p.pid = ? AND p.visible = 1 AND t.visible = 1 LIMIT 1',
        default   => null,
    };
    if ($sql === null || $id <= 0) return false;
    $q = $db->sql_query_prepared($sql, [$id]);
    return $q && $db->num_rows($q) > 0;
}

/** xmlhttp.php?action=react  POST type, id, key, my_post_key → {ok, reactions} */
function react_ajax(): never
{
    global $CURUSER;
    $L    = react_lang();
    $out  = static function (array $data): never {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };
    $fail = static fn(string $msg) => $out(['ok' => false, 'error' => $msg]);

    $uid = (int)($CURUSER['id'] ?? 0);
    if ($uid <= 0 || (defined('UC_BANNED') && (int)($CURUSER['usergroup'] ?? 0) === UC_BANNED)) $fail($L['err_login']);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) $fail($L['err_token']);

    $type = (string)($_POST['type'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    if ($type === 'chat' || !react_item_exists($type, $id)) $fail($L['err_gone']);

    $res = react_toggle($type, $id, $uid, (string)($_POST['key'] ?? ''));
    if ($res === 'bad')   $fail($L['err_bad']);
    if ($res === 'limit') $fail(str_replace('{1}', (string)REACT_MAX_PER_USER, $L['err_limit']));

    $out(['ok' => true, 'reactions' => react_summary($type, [$id], $uid)[$id] ?? []]);
}

/** Reactions of items that are gone (comments and posts are deleted in many places): run by the daily cleanup. */
function react_prune_orphans(): int
{
    global $db;
    $db->sql_query_prepared("DELETE r FROM reactions r LEFT JOIN comments c ON c.id = r.item_id WHERE r.type = 'comment' AND c.id IS NULL");
    $n = max(0, (int)$db->affected_rows());
    $db->sql_query_prepared("DELETE r FROM reactions r LEFT JOIN chat_messages m ON m.id = r.item_id WHERE r.type = 'chat' AND m.id IS NULL");
    $n += max(0, (int)$db->affected_rows());
    $db->sql_query_prepared("DELETE r FROM reactions r LEFT JOIN posts p ON p.pid = r.item_id WHERE r.type = 'post' AND p.pid IS NULL");
    return $n + max(0, (int)$db->affected_rows());
}
