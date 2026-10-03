<?php
declare(strict_types=1);

/**
 * Claims - port of NexusPHP claims (app/Models/Claim.php, app/Repositories/ClaimRepository.php).
 *
 * A user who downloaded an old torrent "claims" it and promises to keep seeding it.
 * Once a month every claim taken before that month is settled:
 *   reached   (seeded >= CLAIM_SEED_HOURS or uploaded >= CLAIM_UPLOAD_TIMES x size)
 *             -> bonus for the hours seeded this month
 *   unreached in the first month after claiming -> kept (grace month)
 *   unreached later                             -> claim removed, CLAIM_REMOVE_DEDUCT bonus taken
 * Giving a claim up by hand costs CLAIM_GIVE_UP_DEDUCT.
 *
 * Differences from NexusPHP:
 * - Bonus: NexusPHP takes the seeding bonus per hour of the reached torrents and multiplies
 *   it by their average seed hours. Here: hours seeded this month x CLAIM_BONUS_PER_HOUR
 *   per reached torrent (the same idea without NexusPHP's seeding formula).
 * - Seed time / upload come from snatched by (userid, torrentid) with MAX(): snatched has no
 *   unique key on that pair here, so no snatched_id is stored.
 * - Times are unix ints like the rest of the tracker; every change of bonus goes to the bonus log.
 */

if (!defined('IN_TRACKER') && !defined('IN_CRON') && !defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

require_once INC_PATH . '/functions_bonuslog.php';

// ── Settings (NexusPHP defaults) ─────────────────────────
const CLAIM_ENABLED           = true;
const CLAIM_MIN_AGE_DAYS      = 30;     // torrent must be at least this old (torrent.claim_torrent_ttl)
const CLAIM_MAX_PER_USER      = 1000;   // claims one user can hold
const CLAIM_MAX_PER_TORRENT   = 10;     // users that can claim one torrent
const CLAIM_SEED_HOURS        = 300;    // monthly norm: seed time ...
const CLAIM_UPLOAD_TIMES      = 2;      // ... or upload this many times the torrent size
const CLAIM_BONUS_PER_HOUR    = 0.5;    // bonus per hour seeded this month, per reached torrent
const CLAIM_BONUS_MULTIPLIER  = 1.0;    // torrent.claim_bonus_multiplier
const CLAIM_REMOVE_DEDUCT     = 600;    // per torrent removed for not reaching the norm
const CLAIM_GIVE_UP_DEDUCT    = 400;    // giving up a claim by hand
const CLAIM_MSG_LIST_LIMIT    = 20;     // torrents listed per group in the monthly PM

final class ClaimException extends RuntimeException {}

function claim_month_start(?int $ts = null): int
{
    return (int)strtotime('first day of this month 00:00', $ts ?? TIMENOW);
}

/** Seed time and upload of a user on a torrent (snatched may hold duplicates: MAX). */
function claim_snatch(int $uid, int $tid): ?array
{
    global $db;
    $r = $db->fetch_array($db->sql_query_prepared(
        'SELECT MAX(seedtime) AS seedtime, MAX(uploaded) AS uploaded, COUNT(*) AS n
         FROM snatched WHERE userid = ? AND torrentid = ?',
        [$uid, $tid]
    ));
    return ($r && (int)$r['n'] > 0) ? ['seedtime' => (int)$r['seedtime'], 'uploaded' => (int)$r['uploaded']] : null;
}

function claim_count_user(int $uid): int
{
    global $db;
    $r = $db->fetch_array($db->sql_query_prepared('SELECT COUNT(*) AS n FROM claims WHERE uid = ?', [$uid]));
    return (int)($r['n'] ?? 0);
}

function claim_count_torrent(int $tid): int
{
    global $db;
    $r = $db->fetch_array($db->sql_query_prepared('SELECT COUNT(*) AS n FROM claims WHERE torrent_id = ?', [$tid]));
    return (int)($r['n'] ?? 0);
}

function claim_get(int $uid, int $tid): ?array
{
    global $db;
    $r = $db->fetch_array($db->sql_query_prepared('SELECT * FROM claims WHERE uid = ? AND torrent_id = ?', [$uid, $tid]));
    return $r ?: null;
}

/** Torrent can be claimed at all (age); for the button on the torrent page. */
function claim_torrent_old_enough(array $torrent): bool
{
    return (int)$torrent['added'] <= TIMENOW - CLAIM_MIN_AGE_DAYS * 86400;
}

/** ClaimRepository::canBeClaimByUser(). Returns the snatch, throws why not. */
function claim_check_can(int $uid, int $tid): array
{
    global $db;
    if (!CLAIM_ENABLED) throw new ClaimException('Claims are disabled.');

    $t = $db->fetch_array($db->sql_query_prepared('SELECT id, added FROM torrents WHERE id = ?', [$tid]));
    if (!$t) throw new ClaimException('Torrent not found.');
    if (claim_get($uid, $tid)) throw new ClaimException('You have already claimed this torrent.');

    $snatch = claim_snatch($uid, $tid) ?? throw new ClaimException('You can only claim a torrent you have downloaded.');
    if (!claim_torrent_old_enough($t)) {
        throw new ClaimException('This torrent can be claimed ' . CLAIM_MIN_AGE_DAYS . ' days after it was uploaded.');
    }
    if (claim_count_user($uid) >= CLAIM_MAX_PER_USER) {
        throw new ClaimException('You have reached the maximum of ' . CLAIM_MAX_PER_USER . ' claimed torrents.');
    }
    if (claim_count_torrent($tid) >= CLAIM_MAX_PER_TORRENT) {
        throw new ClaimException('This torrent already has the maximum of ' . CLAIM_MAX_PER_TORRENT . ' claimers.');
    }
    return $snatch;
}

/** ClaimRepository::store(): remembers seed time and upload now; the month counts from here. */
function claim_add(int $uid, int $tid): int
{
    global $db;
    $s = claim_check_can($uid, $tid);
    // INSERT IGNORE + unique (uid, torrent_id): a double click can't create two claims
    $db->sql_query_prepared(
        'INSERT IGNORE INTO claims (uid, torrent_id, seed_time_begin, uploaded_begin, added) VALUES (?, ?, ?, ?, ?)',
        [$uid, $tid, $s['seedtime'], $s['uploaded'], TIMENOW]
    );
    if ((int)$db->affected_rows() !== 1) throw new ClaimException('You have already claimed this torrent.');
    return (int)$db->insert_id();
}

/** ClaimRepository::delete(): giving up costs CLAIM_GIVE_UP_DEDUCT. Returns the deducted amount. */
function claim_give_up(int $uid, int $tid): int
{
    global $db;
    if (!CLAIM_ENABLED) throw new ClaimException('Claims are disabled.');

    $db->sql_query_prepared('DELETE FROM claims WHERE uid = ? AND torrent_id = ?', [$uid, $tid]);
    if ((int)$db->affected_rows() !== 1) throw new ClaimException('You have not claimed this torrent.');

    $deduct = CLAIM_GIVE_UP_DEDUCT;
    if ($deduct > 0) {
        $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?', [$deduct, $uid]);
        bonus_log($uid, -$deduct, 'claim', 'Claim given up: torrent #' . $tid, $tid);
    }
    return $deduct;
}

/**
 * Claims with this month's progress: seeded / uploaded since the begin marks.
 * $by: ['uid' => int] or ['torrent_id' => int].
 */
function claim_list(array $by, int $limit, int $offset = 0): array
{
    global $db;
    [$col, $val] = isset($by['torrent_id']) ? ['c.torrent_id', (int)$by['torrent_id']] : ['c.uid', (int)$by['uid']];
    $q = $db->sql_query_prepared(
        "SELECT c.*, t.name AS torrent_name, t.size, t.added AS torrent_added, t.seeders, t.leechers,
                u.username, u.usergroup, u.displaygroup,
                s.seedtime, s.uploaded
         FROM claims c
         LEFT JOIN torrents t ON t.id = c.torrent_id
         LEFT JOIN users u ON u.id = c.uid
         LEFT JOIN (SELECT userid, torrentid, MAX(seedtime) AS seedtime, MAX(uploaded) AS uploaded
                    FROM snatched GROUP BY userid, torrentid) s
                ON s.userid = c.uid AND s.torrentid = c.torrent_id
         WHERE {$col} = ?
         ORDER BY c.added DESC, c.id DESC
         LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
        [$val]
    );
    $rows = [];
    while ($r = $db->fetch_array($q)) $rows[] = claim_progress($r);
    return $rows;
}

/**
 * Adds seed_this_month, up_this_month, percentages, reached, and what happens at the
 * NEXT settlement: first_period = it is the first settlement for this claim (unreached
 * is kept without penalty), otherwise unreached means removal.
 */
function claim_progress(array $r): array
{
    $seed = max(0, (int)($r['seedtime'] ?? 0) - (int)$r['seed_time_begin']);
    $up   = max(0, (int)($r['uploaded'] ?? 0) - (int)$r['uploaded_begin']);
    $needSeed = CLAIM_SEED_HOURS * 3600;
    $needUp   = CLAIM_UPLOAD_TIMES * (int)($r['size'] ?? 0);

    $r['seed_this_month'] = $seed;
    $r['up_this_month']   = $up;
    $r['seed_pct']        = $needSeed > 0 ? (int)min(100, floor($seed / $needSeed * 100)) : 100;
    $r['up_pct']          = $needUp > 0 ? (int)min(100, floor($up / $needUp * 100)) : 0;
    $r['reached']         = $seed >= $needSeed || ($needUp > 0 && $up >= $needUp);

    // Next settlement: overdue right now (taken before this month and not settled since
    // its start - the cron does it at its next run) or on the 1st of next month
    $monthStart = claim_month_start();
    $settledNow = $r['last_settle_at'] !== null && (int)$r['last_settle_at'] >= $monthStart;
    $next       = ((int)$r['added'] < $monthStart && !$settledNow) ? $monthStart : claim_month_start($monthStart + 32 * 86400);
    // First settlement after claiming = the claim was taken during the month before it
    $r['first_period']    = (int)$r['added'] >= claim_month_start($next - 1);
    $r['next_settle']     = $next;
    return $r;
}

// ── Monthly settlement ───────────────────────────────────

/**
 * ClaimRepository::settleCronjob(). Settles every user that has claims taken before this
 * month and not settled this month yet. Safe to run every hour: a settled claim gets
 * last_settle_at, so nothing is paid twice. Returns [users, reached, removed, bonus].
 */
function claim_settle_cron(): array
{
    global $db;
    $start = claim_month_start();
    $uids  = [];
    $q = $db->sql_query_prepared(
        'SELECT DISTINCT uid FROM claims WHERE added < ? AND (last_settle_at IS NULL OR last_settle_at < ?)',
        [$start, $start]
    );
    while ($r = $db->fetch_array($q)) $uids[] = (int)$r['uid'];

    $total = ['users' => 0, 'reached' => 0, 'removed' => 0, 'bonus' => 0.0];
    foreach ($uids as $uid) {
        try {
            $res = claim_settle_user($uid);
            $total['users']++;
            $total['reached'] += $res['reached'];
            $total['removed'] += $res['removed'];
            $total['bonus']   += $res['bonus'];
        } catch (Throwable $e) {
            if (function_exists('write_log')) write_log("[claims] settle user #{$uid} failed: " . $e->getMessage());
        }
    }
    if ($total['users'] > 0 && function_exists('savelog')) {
        savelog(sprintf('[claims] %s settled: %d user(s), %d torrent(s) reached (+%s BP), %d removed',
            date('Y-m', $start - 1), $total['users'], $total['reached'], exam_num_safe($total['bonus']), $total['removed']));
    }
    return $total;
}

/** ClaimRepository::settleUser() */
function claim_settle_user(int $uid): array
{
    global $db;
    $start     = claim_month_start();
    $prevStart = claim_month_start($start - 1);           // start of the month being settled
    $month     = date('Y-m', $start - 1);

    $q = $db->sql_query_prepared(
        "SELECT c.*, t.id AS tid, t.name AS torrent_name, t.size, s.seedtime, s.uploaded, s.n AS snatches
         FROM claims c
         LEFT JOIN torrents t ON t.id = c.torrent_id
         LEFT JOIN (SELECT userid, torrentid, MAX(seedtime) AS seedtime, MAX(uploaded) AS uploaded, COUNT(*) AS n
                    FROM snatched WHERE userid = ? GROUP BY userid, torrentid) s
                ON s.torrentid = c.torrent_id
         WHERE c.uid = ? AND c.added < ? AND (c.last_settle_at IS NULL OR c.last_settle_at < ?)",
        [$uid, $uid, $start, $start]
    );

    $reached = $remain = $removed = $gone = [];
    $bonus   = 0.0;
    while ($r = $db->fetch_array($q)) {
        if ($r['tid'] === null || $r['snatches'] === null) { $gone[] = (int)$r['id']; continue; }
        $p = claim_progress($r);
        if ($p['reached']) {
            $hours  = $p['seed_this_month'] / 3600;
            $bonus += $hours * CLAIM_BONUS_PER_HOUR * CLAIM_BONUS_MULTIPLIER;
            $reached[] = $p;
        } elseif ((int)$r['added'] >= $prevStart) {
            $remain[] = $p;                                // first month: grace
        } else {
            $removed[] = $p;
        }
    }
    $bonus  = round($bonus, 1);
    $deduct = CLAIM_REMOVE_DEDUCT * count($removed);

    // Gone torrents / snatches: drop silently (as NexusPHP)
    if ($gone) {
        $db->sql_query_prepared('DELETE FROM claims WHERE id IN (' . implode(',', array_map('intval', $gone)) . ')');
    }
    if (!$reached && !$remain && !$removed) return ['reached' => 0, 'removed' => 0, 'bonus' => 0.0];

    // Kept claims: new begin marks for the next month, settled now
    foreach (array_merge($reached, $remain) as $p) {
        $db->sql_query_prepared(
            'UPDATE claims SET seed_time_begin = ?, uploaded_begin = ?, last_settle_at = ? WHERE id = ?',
            [(int)$p['seedtime'], (int)$p['uploaded'], TIMENOW, (int)$p['id']]
        );
    }
    if ($removed) {
        $db->sql_query_prepared('DELETE FROM claims WHERE id IN (' . implode(',', array_map(static fn($p) => (int)$p['id'], $removed)) . ')');
    }

    if ($bonus > 0) {
        $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?', [$bonus, $uid]);
        bonus_log($uid, $bonus, 'claim', "Claims {$month}: " . count($reached) . ' torrent(s) reached');
    }
    if ($deduct > 0) {
        $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?', [$deduct, $uid]);
        bonus_log($uid, -$deduct, 'claim', "Claims {$month}: " . count($removed) . ' torrent(s) removed, not seeded enough');
    }

    claim_notify($uid, "{$month} claim settlement", claim_settle_message($month, $reached, $remain, $removed, $bonus, $deduct));
    return ['reached' => count($reached), 'removed' => count($removed), 'bonus' => $bonus];
}

function claim_settle_message(string $month, array $reached, array $remain, array $removed, float $bonus, int $deduct): string
{
    global $BASEURL;
    $list = static function (array $rows) use ($BASEURL): string {
        $out = [];
        foreach (array_slice($rows, 0, CLAIM_MSG_LIST_LIMIT) as $p) {
            $out[] = '[*][url=' . $BASEURL . '/details.php?id=' . (int)$p['torrent_id'] . ']' . $p['torrent_name'] . '[/url]';
        }
        $more = count($rows) - CLAIM_MSG_LIST_LIMIT;
        return $out ? "[list]\n" . implode("\n", $out) . "\n[/list]" . ($more > 0 ? "... and {$more} more" : '') : '';
    };
    $total = count($reached) + count($remain) + count($removed);

    $m   = ["Your claimed torrents for [b]{$month}[/b]: [b]{$total}[/b]."];
    $m[] = 'Reached the norm: [b]' . count($reached) . '[/b]' . ($reached ? "\n" . $list($reached) : '');
    if ($reached) {
        $m[] = 'You get [b]' . number_format($bonus, 1) . '[/b] bonus points ('
             . rtrim(rtrim(number_format(CLAIM_BONUS_PER_HOUR * CLAIM_BONUS_MULTIPLIER, 2), '0'), '.') . ' per hour seeded).';
    }
    if ($remain) {
        $m[] = 'Not reached, kept for one more month (first month after claiming): [b]' . count($remain) . "[/b]\n" . $list($remain);
    }
    if ($removed) {
        $m[] = 'Not reached, claim removed: [b]' . count($removed) . "[/b]\n" . $list($removed)
             . "\n[b]" . number_format($deduct) . '[/b] bonus points deducted (' . number_format(CLAIM_REMOVE_DEDUCT) . ' per torrent).';
    }
    $m[] = 'Norm per torrent and month: ' . CLAIM_SEED_HOURS . ' hours of seeding or uploading ' . CLAIM_UPLOAD_TIMES
         . ' times its size. [url=' . $BASEURL . '/claim.php]My claims[/url]';
    return implode("\n\n", $m);
}

function claim_notify(int $uid, string $subject, string $message): void
{
    if (!function_exists('send_pm')) {
        require_once INC_PATH . '/functions_pm.php';
    }
    send_pm(['subject' => $subject, 'message' => $message, 'touid' => $uid], -1, true);
}

/** Number formatting without depending on functions_exam.php being loaded. */
function exam_num_safe(float $n): string
{
    return function_exists('exam_num') ? exam_num($n, 1) : number_format($n, 1);
}