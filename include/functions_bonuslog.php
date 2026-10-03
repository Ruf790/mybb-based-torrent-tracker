<?php
declare(strict_types=1);

/**
 * Bonus log: every change of users.seedbonus as one row - how much, why, balance after.
 *
 * bonus_log()         one user (shop, gift, exam, requests, staff to one user)
 * bonus_log_where()   many users at once, same amount (staff to a group / everyone)
 * bonus_log_seeding() seeding cron: one row per user per day, amounts are summed
 *
 * Call it AFTER the seedbonus UPDATE: balance is read from users in the same INSERT.
 * Logging never breaks the money operation: errors are swallowed (and logged).
 */

if (!defined('IN_TRACKER') && !defined('IN_CRON') && !defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

const BONUS_LOG_TYPES = [
    'seeding' => ['Seeding',          'fa-seedling'],
    'shop'    => ['Shop',             'fa-cart-shopping'],
    'gift'    => ['Gifts',            'fa-gift'],
    'exam'    => ['Exams and tasks',  'fa-graduation-cap'],
    'request' => ['Requests',         'fa-hand-holding-dollar'],
    'claim'   => ['Claims',           'fa-hand-holding-heart'],
    'staff'   => ['Staff',            'fa-user-shield'],
    'kps'     => ['Activity',         'fa-star'],
    'other'   => ['Other',            'fa-circle'],
];

function bonus_log_type(string $type): string
{
    return isset(BONUS_LOG_TYPES[$type]) ? $type : 'other';
}

function bonus_log_reason(string $reason): string
{
    return mb_substr(trim($reason), 0, 255);
}

/** One user. $amount: + received, - spent. */
function bonus_log(int $uid, float $amount, string $type, string $reason, ?int $refId = null, ?int $actorId = null): void
{
    global $db;
    if ($uid <= 0 || $amount == 0.0) return;

    try {
        $db->sql_query_prepared(
            'INSERT INTO bonus_logs (uid, amount, balance, type, reason, ref_id, actor_id, added)
             SELECT id, ?, seedbonus, ?, ?, ?, ?, ? FROM users WHERE id = ?',
            [round($amount, 2), bonus_log_type($type), bonus_log_reason($reason), $refId, $actorId, TIMENOW, $uid]
        );
    } catch (Throwable $e) {
        bonus_log_failed($e);
    }
}

/**
 * Many users, same amount, chosen by a WHERE on users (no "WHERE" word, use the
 * same condition and params as the UPDATE that changed their seedbonus).
 */
function bonus_log_where(string $where, array $params, float $amount, string $type, string $reason, ?int $actorId = null): void
{
    global $db;
    if ($amount == 0.0) return;

    try {
        $db->sql_query_prepared(
            "INSERT INTO bonus_logs (uid, amount, balance, type, reason, ref_id, actor_id, added)
             SELECT id, ?, seedbonus, ?, ?, NULL, ?, ? FROM users WHERE {$where}",
            [round($amount, 2), bonus_log_type($type), bonus_log_reason($reason), $actorId, TIMENOW, ...$params]
        );
    } catch (Throwable $e) {
        bonus_log_failed($e);
    }
}

/**
 * Seeding cron: [uid => amount] for one batch. One row per user per day
 * (agg_key "seed:YYYY-MM-DD"): the amount grows, balance and time are the latest.
 */
function bonus_log_seeding(array $amounts): void
{
    global $db;

    $case = [];
    $p    = [];
    $ids  = [];
    foreach ($amounts as $uid => $amount) {
        $uid    = (int)$uid;
        $amount = round((float)$amount, 2);
        if ($uid <= 0 || $amount <= 0) continue;
        $case[] = 'WHEN ? THEN ?';
        array_push($p, $uid, $amount);
        $ids[] = $uid;
    }
    if (!$ids) return;

    $in = implode(',', array_fill(0, count($ids), '?'));
    try {
        // Derived table: its columns can be used in ON DUPLICATE KEY UPDATE
        // (works in MySQL 8.0.19+ and MariaDB, no deprecated VALUES())
        $db->sql_query_prepared(
            "INSERT INTO bonus_logs (uid, amount, balance, type, reason, agg_key, added)
             SELECT s_uid, s_amount, s_balance, 'seeding', 'Seeding', s_key, s_added FROM (
                 SELECT u.id AS s_uid, CASE u.id " . implode(' ', $case) . " END AS s_amount,
                        u.seedbonus AS s_balance, ? AS s_key, ? AS s_added
                 FROM users u WHERE u.id IN ({$in})
             ) AS s
             ON DUPLICATE KEY UPDATE amount = amount + s_amount, balance = s_balance, added = s_added",
            [...$p, 'seed:' . date('Y-m-d', TIMENOW), TIMENOW, ...$ids]
        );
    } catch (Throwable $e) {
        bonus_log_failed($e);
    }
}

/**
 * Balance wiped to 0 (staff reset): every user loses what they had, so the row is
 * "-<balance>". Call it BEFORE the UPDATE that sets seedbonus to 0 - same WHERE.
 */
function bonus_log_reset(string $where, array $params, string $reason, ?int $actorId = null): void
{
    global $db;
    try {
        $db->sql_query_prepared(
            "INSERT INTO bonus_logs (uid, amount, balance, type, reason, ref_id, actor_id, added)
             SELECT id, -seedbonus, 0, 'staff', ?, NULL, ?, ? FROM users WHERE ({$where}) AND seedbonus > 0",
            [bonus_log_reason($reason), $actorId, TIMENOW, ...$params]
        );
    } catch (Throwable $e) {
        bonus_log_failed($e);
    }
}

function bonus_log_failed(Throwable $e): void
{
    if (function_exists('write_log')) {
        write_log('[bonus_log] not written: ' . $e->getMessage());
    } else {
        error_log('[bonus_log] not written: ' . $e->getMessage());
    }
}

/** Rows for a page: filters uid / type / since; newest first. */
function bonus_log_fetch(?int $uid, ?string $type, int $limit, int $offset = 0, ?int $since = null): array
{
    global $db;
    [$w, $p] = bonus_log_where_sql($uid, $type, $since);
    $q = $db->sql_query_prepared(
        "SELECT l.*, u.username, u.usergroup, u.displaygroup, a.username AS actor_name
         FROM bonus_logs l
         LEFT JOIN users u ON u.id = l.uid
         LEFT JOIN users a ON a.id = l.actor_id
         WHERE {$w} ORDER BY l.added DESC, l.id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
        $p
    );
    $rows = [];
    while ($r = $db->fetch_array($q)) $rows[] = $r;
    return $rows;
}

/** [count, received, spent] for the same filters. */
function bonus_log_totals(?int $uid, ?string $type, ?int $since = null): array
{
    global $db;
    [$w, $p] = bonus_log_where_sql($uid, $type, $since);
    $r = $db->fetch_array($db->sql_query_prepared(
        "SELECT COUNT(*) AS n,
                COALESCE(SUM(CASE WHEN l.amount > 0 THEN l.amount END), 0) AS plus,
                COALESCE(SUM(CASE WHEN l.amount < 0 THEN -l.amount END), 0) AS minus
         FROM bonus_logs l WHERE {$w}",
        $p
    )) ?: [];
    return ['count' => (int)($r['n'] ?? 0), 'plus' => (float)($r['plus'] ?? 0), 'minus' => (float)($r['minus'] ?? 0)];
}

function bonus_log_where_sql(?int $uid, ?string $type, ?int $since): array
{
    $w = ['1 = 1'];
    $p = [];
    if ($uid)   { $w[] = 'l.uid = ?';   $p[] = $uid; }
    if ($type !== null && $type !== '' && isset(BONUS_LOG_TYPES[$type])) { $w[] = 'l.type = ?'; $p[] = $type; }
    if ($since) { $w[] = 'l.added >= ?'; $p[] = $since; }
    return [implode(' AND ', $w), $p];
}

/** "+1,234.5" / "−50" for display. */
function bonus_log_amount(float $a): string
{
    $s = rtrim(rtrim(number_format(abs($a), 2, '.', ','), '0'), '.');
    return ($a > 0 ? '+' : '−') . $s;
}