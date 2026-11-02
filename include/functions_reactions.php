<?php
declare(strict_types=1);

/**
 * Reactions with the site's own smilies (pic/smilies/), shared by every kind of item.
 *
 *   reactions (type, item_id, uid, rkey, added)   PK (type, item_id, uid, rkey)
 *     type  'chat' = chat_messages.id; 'comment' reserved for torrent comments
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

const REACT_TYPES         = ['chat', 'comment'];
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
