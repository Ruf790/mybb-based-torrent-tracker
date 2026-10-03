<?php
declare(strict_types=1);

/**
 * Экзамены и задания - порт из NexusPHP (app/Models/Exam.php, ExamUser.php,
 * app/Repositories/ExamRepository.php, ветка php8).
 *
 * Экзамен (type 1): выдаёт стафф или крон (is_discovered), провал = отключение аккаунта.
 * Задание (type 2): пользователь берёт сам на task.php, сдал = награда, провал = штраф.
 *
 * Прогресс: при выдаче в exam_progress записывается стартовое значение каждого
 * показателя (init_value), дальше прирост = текущее - стартовое.
 */

if (!defined('IN_TRACKER') && !defined('IN_CRON') && !defined('STAFF_PANEL')) {
    die('Direct access denied.');
}

require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_bonuslog.php';

// ── Константы (как в Exam / ExamUser) ────────────────────

const EXAM_STATUS_ENABLED  = 0;
const EXAM_STATUS_DISABLED = 1;

const EXAM_TYPE_EXAM = 1;
const EXAM_TYPE_TASK = 2;

const EXAM_INDEX_UPLOADED             = 1;
const EXAM_INDEX_SEED_TIME_AVERAGE    = 2;
const EXAM_INDEX_DOWNLOADED           = 3;
const EXAM_INDEX_SEED_BONUS           = 4;
const EXAM_INDEX_SEED_POINTS          = 5;
const EXAM_INDEX_UPLOAD_TORRENT_COUNT = 6;

const EXAM_INDEXES = [
    EXAM_INDEX_UPLOADED             => ['name' => 'Uploaded',          'unit' => 'GB'],
    EXAM_INDEX_DOWNLOADED           => ['name' => 'Downloaded',        'unit' => 'GB'],
    EXAM_INDEX_SEED_TIME_AVERAGE    => ['name' => 'Average seed time', 'unit' => 'h'],
    EXAM_INDEX_SEED_BONUS           => ['name' => 'Bonus gained',      'unit' => ''],
    EXAM_INDEX_SEED_POINTS          => ['name' => 'Seed points',       'unit' => ''],
    EXAM_INDEX_UPLOAD_TORRENT_COUNT => ['name' => 'Torrents uploaded', 'unit' => ''],
];

const EXAM_FILTER_USER_CLASS                = 'classes';            // здесь: id групп
const EXAM_FILTER_USER_REGISTER_TIME_RANGE  = 'register_time_range';
const EXAM_FILTER_USER_DONATE               = 'donate_status';
const EXAM_FILTER_USER_REGISTER_DAYS_RANGE  = 'register_days_range';

const EXAM_RECURRING = ['Daily' => 'Daily', 'Weekly' => 'Weekly', 'Monthly' => 'Monthly'];

const EXAM_USER_STATUS_NORMAL   = 0;
const EXAM_USER_STATUS_FINISHED = 1;
const EXAM_USER_STATUS_AVOIDED  = -1;

const EXAM_USER_STATUS = [
    EXAM_USER_STATUS_NORMAL   => 'Ongoing',
    EXAM_USER_STATUS_FINISHED => 'Finished',
    EXAM_USER_STATUS_AVOIDED  => 'Avoided',
];

// How a finished attempt ended (exam_users.result), for staff statistics
const EXAM_RESULT_PASSED   = 'passed';   // exam passed / task completed
const EXAM_RESULT_FAILED   = 'failed';   // task failed (penalty) or exam failed by staff
const EXAM_RESULT_DISABLED = 'disabled'; // exam failed, account disabled
const EXAM_RESULT_ABANDONED = 'abandoned'; // task given up by the user (our addition)

// Share of the task's fail penalty taken when the user abandons it: 100 = full, 50 = half, 0 = free
const EXAM_TASK_ABANDON_PENALTY_PERCENT = 50;

// Pass-rate alerts for staff (our addition): checked after checkout, only with enough finished attempts
const EXAM_ANOMALY_MIN_FINISHED = 20;
const EXAM_ANOMALY_LOW_PERCENT  = 10;   // below: too hard, wrong filters, or something isn't counted
const EXAM_ANOMALY_HIGH_PERCENT = 95;   // above: too easy, checks nothing
// Staff panel page, for the link in the PM (relative to $BASEURL)
const EXAM_ADMIN_URL = '/admin/index.php?act=exams';

/**
 * Колонка snatched с временем последней активности - по ней считается число
 * торрентов для «Среднего времени сидирования». Unix timestamp.
 */
const EXAM_SNATCHED_LAST_ACTION = 'last_action';

/** Не пересчитывать прогресс при просмотре страниц чаще, чем раз в N секунд. */
const EXAM_PROGRESS_VIEW_THROTTLE = 300;

const EXAM_GB = 1073741824;

final class ExamException extends RuntimeException {}

// ── Время ────────────────────────────────────────────────

/**
 * Number format for the whole exam module (card, PMs, log, admin, task.php).
 * Uses the site-wide ts_nf() (1,233,333 / 1.5); rounds first so a float like
 * 1.3333333 hours does not come out with ten decimals. Fallback if ts_nf()
 * is not loaded (e.g. in a cron context).
 */
function exam_num(int|float $n, int $decimals = 0): string
{
    $n = $decimals > 0 ? round((float)$n, $decimals) : (int)round((float)$n);
    if (function_exists('ts_nf')) {
        return ts_nf($n);
    }
    return is_float($n) && fmod($n, 1.0) !== 0.0
        ? rtrim(rtrim(number_format($n, $decimals, '.', ','), '0'), '.')
        : number_format((float)$n, 0, '.', ',');
}

function exam_dt(int $ts): string
{
    return date('Y-m-d H:i:s', $ts);
}

function exam_now(): string
{
    return exam_dt(TIMENOW);
}

function exam_ts(?string $dt): int
{
    if ($dt === null || $dt === '' || str_starts_with($dt, '0000')) return 0;
    return (int)strtotime($dt);
}

/** Начало / конец текущего цикла (getRecurringBegin / getRecurringEnd). Неделя с понедельника. */
function exam_recurring_range(string $recurring, int $now): array
{
    return match ($recurring) {
        'Daily'   => [strtotime('today', $now), strtotime('tomorrow', $now) - 1],
        'Weekly'  => [strtotime('monday this week', $now), strtotime('monday next week', $now) - 1],
        'Monthly' => [strtotime('first day of this month 00:00', $now), strtotime('first day of next month 00:00', $now) - 1],
        default   => throw new ExamException("Unknown recurring period: {$recurring}"),
    };
}

/** Exam::getBeginForUser() */
function exam_begin_for_user(array $exam): int
{
    if (!empty($exam['begin']))     return exam_ts($exam['begin']);
    if (!empty($exam['recurring'])) return exam_recurring_range((string)$exam['recurring'], TIMENOW)[0];
    return TIMENOW;
}

/** Calendar days like Carbon::addDays(): same wall-clock time even across a DST change */
function exam_add_days(int $ts, int $days): int
{
    return (int)strtotime('+' . $days . ' days', $ts);
}

/** Exam::getEndForUser() */
function exam_end_for_user(array $exam): int
{
    if (!empty($exam['end']))           return exam_ts($exam['end']);
    if ((int)$exam['duration'] > 0)     return exam_add_days(exam_begin_for_user($exam), (int)$exam['duration']);
    if (!empty($exam['recurring']))     return exam_recurring_range((string)$exam['recurring'], TIMENOW)[1];
    throw new ExamException('Invalid time condition: set exactly one of begin + end / duration / recurring.');
}

/** ExamUser::getBeginAttribute() - своё, иначе экзамена, иначе created_at. */
function exam_user_begin(array $eu, array $exam): int
{
    if (!empty($eu['begin']))        return exam_ts($eu['begin']);
    if (!empty($exam['begin']))      return exam_ts($exam['begin']);
    if ((int)$exam['duration'] > 0)  return exam_ts($eu['created_at']);
    return 0;
}

function exam_user_end(array $eu, array $exam): int
{
    if (!empty($eu['end']))          return exam_ts($eu['end']);
    if (!empty($exam['end']))        return exam_ts($exam['end']);
    if ((int)$exam['duration'] > 0)  return exam_add_days(exam_ts($eu['created_at']), (int)$exam['duration']);
    return 0;
}

// ── Чтение ───────────────────────────────────────────────

function exam_decode(array $exam): array
{
    $exam['indexes'] = is_array($exam['indexes'] ?? null) ? $exam['indexes'] : (json_decode((string)($exam['indexes'] ?? ''), true) ?: []);
    $exam['filters'] = is_array($exam['filters'] ?? null) ? $exam['filters'] : (json_decode((string)($exam['filters'] ?? ''), true) ?: []);
    return $exam;
}

function exam_get(int $id): ?array
{
    global $db;
    $row = $db->fetch_array($db->sql_query_prepared('SELECT * FROM exams WHERE id = ?', [$id]));
    return $row ? exam_decode($row) : null;
}

function exam_user_get(int $id): ?array
{
    global $db;
    $row = $db->fetch_array($db->sql_query_prepared('SELECT * FROM exam_users WHERE id = ?', [$id]));
    return $row ?: null;
}

function exam_fetch_user(int $uid): ?array
{
    global $db;
    $row = $db->fetch_array($db->sql_query_prepared(
        'SELECT id, username, usergroup, added, enabled, ustatus, donor,
                uploaded, downloaded, seedbonus, seed_points, modcomment
         FROM users WHERE id = ?',
        [$uid]
    ));
    return $row ?: null;
}

function exam_checked_indexes(array $exam): array
{
    $out = [];
    foreach ($exam['indexes'] as $index) {
        if (!empty($index['checked']) && isset(EXAM_INDEXES[(int)$index['index']])) {
            $out[] = $index;
        }
    }
    return $out;
}

function exam_type_text(array $exam): string
{
    return (int)$exam['type'] === EXAM_TYPE_TASK ? 'Task' : 'Exam';
}

/** ExamRepository::listValid() */
function exam_list_valid(?int $isDiscovered = null, ?int $type = null, array $excludeIds = []): array
{
    global $db;
    $now    = exam_now();
    $where  = 'status = ? AND (
                 (`begin` IS NOT NULL AND `end` IS NOT NULL AND `begin` <= ? AND `end` >= ?)
              OR ((`begin` IS NULL OR `end` IS NULL) AND (duration > 0 OR recurring IS NOT NULL))
              )';
    $params = [EXAM_STATUS_ENABLED, $now, $now];

    if ($isDiscovered !== null) { $where .= ' AND is_discovered = ?'; $params[] = $isDiscovered; }
    if ($type !== null)         { $where .= ' AND type = ?';          $params[] = $type; }
    foreach ($excludeIds as $id) { $where .= ' AND id <> ?';          $params[] = (int)$id; }

    $out = [];
    $q = $db->sql_query_prepared("SELECT * FROM exams WHERE {$where} ORDER BY priority DESC, id ASC", $params);
    while ($r = $db->fetch_array($q)) {
        $out[] = exam_decode($r);
    }
    return $out;
}

// ── Пользователь подходит? ───────────────────────────────

function exam_user_donate_status(array $user): string
{
    // В users нет donoruntil (в NexusPHP есть) - донор только по флагу donor
    return (string)($user['donor'] ?? 'no') === 'yes' ? 'yes' : 'no';
}

function exam_user_is_normal(array $user): bool
{
    return (string)$user['enabled'] === 'yes' && (string)($user['ustatus'] ?? 'confirmed') === 'confirmed';
}

/** Группы стаффа - экзамен им не выдаётся и бан к ним не применяется. */
function exam_staff_groups(): array
{
    global $db;
    static $groups = null;
    if ($groups === null) {
        $groups = [];
        $q = $db->sql_query_prepared(
            // CAST: колонки числовые, и без него MySQL сравнивал 0 = 'yes' -> 0 = 0 -> true,
            // из-за чего стаффом считались все группы
            "SELECT gid FROM usergroups
             WHERE CAST(canstaffpanel AS CHAR) IN ('1','yes')
                OR CAST(issupermod AS CHAR) IN ('1','yes')
                OR CAST(cansettingspanel AS CHAR) IN ('1','yes')",
            []
        );
        while ($r = $db->fetch_array($q)) {
            $groups[(int)$r['gid']] = true;
        }
    }
    return $groups;
}

function exam_is_staff(array $user): bool
{
    return isset(exam_staff_groups()[(int)$user['usergroup']]);
}

/** ExamRepository::isExamMatchUser() */
function exam_match_user(array $exam, array $user): bool
{
    $f = $exam['filters'];

    $groups = $f[EXAM_FILTER_USER_CLASS] ?? [];
    if ($groups && !in_array((int)$user['usergroup'], array_map('intval', $groups), true)) return false;

    $donate = $f[EXAM_FILTER_USER_DONATE] ?? [];
    if ($donate && !in_array(exam_user_donate_status($user), $donate, true)) return false;

    $added = (int)$user['added'];
    $range = $f[EXAM_FILTER_USER_REGISTER_TIME_RANGE] ?? [];
    if (!empty($range[0]) && $added < exam_ts((string)$range[0])) return false;
    if (!empty($range[1]) && $added > exam_ts((string)$range[1])) return false;

    $days  = intdiv(max(0, TIMENOW - $added), 86400);
    $range = $f[EXAM_FILTER_USER_REGISTER_DAYS_RANGE] ?? [];
    if (isset($range[0]) && $range[0] !== '' && $range[0] !== null && $days < (int)$range[0]) return false;
    if (isset($range[1]) && $range[1] !== '' && $range[1] !== null && $days > (int)$range[1]) return false;

    return exam_user_is_normal($user);
}

// ── Выдача ───────────────────────────────────────────────

function exam_has_ongoing(int $uid): bool
{
    global $db;
    return (bool)$db->fetch_array($db->sql_query_prepared(
        'SELECT 1 FROM exam_users WHERE uid = ? AND status = ? LIMIT 1',
        [$uid, EXAM_USER_STATUS_NORMAL]
    ));
}

function exam_ongoing_count(int $examId): int
{
    global $db;
    $r = $db->fetch_array($db->sql_query_prepared(
        'SELECT COUNT(*) AS n FROM exam_users WHERE exam_id = ? AND status = ?',
        [$examId, EXAM_USER_STATUS_NORMAL]
    ));
    return (int)($r['n'] ?? 0);
}

/** Вставка без проверок (используют assign, автовыдача и повтор). Возвращает exam_users.id. */
function exam_insert_user(array $exam, array $user, int $begin, int $end): int
{
    global $db;
    $now = exam_now();
    $db->sql_query_prepared(
        'INSERT INTO exam_users (uid, exam_id, status, `begin`, `end`, progress, is_done, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NULL, 0, ?, ?)',
        [(int)$user['id'], (int)$exam['id'], EXAM_USER_STATUS_NORMAL, exam_dt($begin), exam_dt($end), $now, $now]
    );
    $id = (int)$db->insert_id();
    $eu = exam_user_get($id);
    if ($eu) {
        exam_update_progress($eu, $exam, $user); // снимок стартовых значений
    }
    return $id;
}

/**
 * ExamRepository::assignToUser()
 * $actor: ['id' => int, 'staff' => bool] - кто выдаёт / берёт.
 */
function exam_assign_to_user(int $uid, int $examId, array $actor, ?int $begin = null, ?int $end = null): int
{
    $exam = exam_get($examId);
    if (!$exam)                                   throw new ExamException('Exam not found.');
    if ((int)$exam['status'] !== EXAM_STATUS_ENABLED) throw new ExamException('This exam is disabled.');
    $user = exam_fetch_user($uid);
    if (!$user)                                   throw new ExamException('User not found.');

    if (!empty($exam['begin']) && exam_ts($exam['begin']) > TIMENOW) throw new ExamException('It has not started yet.');
    if (!empty($exam['end'])   && exam_ts($exam['end'])   < TIMENOW) throw new ExamException('It has already ended.');

    if ((int)$exam['type'] === EXAM_TYPE_EXAM) {
        // Экзамен выдаёт только стафф, и не стаффу
        if (empty($actor['staff']) || exam_is_staff($user)) throw new ExamException('No permission: exams can only be assigned by staff, and not to staff.');
    } else {
        // Задание берут только сами
        if ((int)$actor['id'] !== $uid) throw new ExamException('You can only claim a task for yourself.');
        if ((int)$exam['max_user_count'] > 0 && exam_ongoing_count($examId) >= (int)$exam['max_user_count']) {
            throw new ExamException('The maximum number of participants has been reached.');
        }
    }

    if (!exam_match_user($exam, $user))  throw new ExamException('This user does not match the target users of this exam.');
    if (exam_has_ongoing($uid))          throw new ExamException('Another exam or task is already in progress. Finish it first.');

    $begin ??= exam_begin_for_user($exam);
    $end   ??= exam_end_for_user($exam);
    if ($end <= $begin)                  throw new ExamException('End must be later than begin.');

    return exam_insert_user($exam, $user, $begin, $end);
}

// ── Прогресс ─────────────────────────────────────────────

/** ExamRepository::getProgressValue() - текущее накопленное значение. */
function exam_progress_value(array $user, int $index, array $eu): int
{
    global $db;
    switch ($index) {
        case EXAM_INDEX_UPLOADED:    return (int)$user['uploaded'];
        case EXAM_INDEX_DOWNLOADED:  return (int)$user['downloaded'];
        case EXAM_INDEX_SEED_BONUS:  return (int)floor((float)$user['seedbonus']);
        case EXAM_INDEX_SEED_POINTS: return (int)floor((float)($user['seed_points'] ?? 0));
        case EXAM_INDEX_SEED_TIME_AVERAGE:
            // В NexusPHP users.seedtime; здесь сумма snatched.seedtime
            $r = $db->fetch_array($db->sql_query_prepared(
                'SELECT COALESCE(SUM(seedtime), 0) AS s FROM snatched WHERE userid = ?',
                [(int)$user['id']]
            ));
            return (int)($r['s'] ?? 0);
        case EXAM_INDEX_UPLOAD_TORRENT_COUNT:
            $r = $db->fetch_array($db->sql_query_prepared(
                "SELECT COUNT(*) AS n FROM torrents
                 WHERE owner = ? AND added >= ? AND visible = 'yes' AND banned = 'no'",
                [(int)$user['id'], exam_ts((string)$eu['created_at'])]
            ));
            return (int)($r['n'] ?? 0);
    }
    throw new ExamException("Unknown index: {$index}");
}

/**
 * ExamRepository::updateProgress()
 * Возвращает ['progress' => [...], 'formatted' => [...], 'is_done' => bool] или null.
 */
function exam_update_progress(array $eu, ?array $exam = null, ?array $user = null): ?array
{
    global $db;

    if ((int)$eu['status'] !== EXAM_USER_STATUS_NORMAL) return null;
    $exam ??= exam_get((int)$eu['exam_id']);
    $user ??= exam_fetch_user((int)$eu['uid']);
    if (!$exam || !$user) return null;

    $begin = exam_user_begin($eu, $exam);
    $end   = exam_user_end($eu, $exam);
    if (!$begin || !$end) return null;

    $now      = exam_now();
    $progress = [];

    foreach (exam_checked_indexes($exam) as $index) {
        $idx   = (int)$index['index'];
        $value = exam_progress_value($user, $idx, $eu);

        $row = $db->fetch_array($db->sql_query_prepared(
            'SELECT id, init_value, value FROM exam_progress
             WHERE exam_user_id = ? AND torrent_id = -1 AND `index` = ?
             ORDER BY id DESC LIMIT 1',
            [(int)$eu['id'], $idx]
        ));
        if ($row) {
            if ((int)$row['value'] !== $value) {
                $db->sql_query_prepared('UPDATE exam_progress SET value = ?, updated_at = ? WHERE id = ?', [$value, $now, (int)$row['id']]);
            }
            $init = (int)$row['init_value'];
        } else {
            $init = $value;
            $db->sql_query_prepared(
                'INSERT INTO exam_progress (exam_user_id, exam_id, uid, torrent_id, `index`, init_value, value, created_at, updated_at)
                 VALUES (?, ?, ?, -1, ?, ?, ?, ?, ?)',
                [(int)$eu['id'], (int)$exam['id'], (int)$user['id'], $idx, $init, $value, $now, $now]
            );
        }

        $delta = $value - $init;
        if ($idx === EXAM_INDEX_SEED_TIME_AVERAGE) {
            // Делим на число торрентов с активностью за время экзамена; 0 -> 1
            $col = EXAM_SNATCHED_LAST_ACTION;
            $r   = $db->fetch_array($db->sql_query_prepared(
                "SELECT COUNT(DISTINCT torrentid) AS n FROM snatched WHERE userid = ? AND `{$col}` BETWEEN ? AND ?",
                [(int)$user['id'], $begin, $end]
            ));
            $delta = intdiv($delta, max(1, (int)($r['n'] ?? 0)));
        }
        $progress[$idx] = $delta;
    }

    $formatted = exam_progress_formatted($exam, $progress);
    $isDone    = $formatted !== [] && !in_array(false, array_column($formatted, 'passed'), true);

    $db->sql_query_prepared(
        // done_at: first time all requirements were met (reset if progress drops back)
        'UPDATE exam_users SET progress = ?, is_done = ?,
                done_at = IF(? = 1, COALESCE(done_at, ?), NULL), updated_at = ? WHERE id = ?',
        [json_encode($progress), $isDone ? 1 : 0, $isDone ? 1 : 0, $now, $now, (int)$eu['id']]
    );

    return ['progress' => $progress, 'formatted' => $formatted, 'is_done' => $isDone];
}

function exam_size(float $bytes): string
{
    if (function_exists('mksize')) return (string)mksize($bytes);
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while (abs($bytes) >= 1024 && $i < 4) { $bytes /= 1024; $i++; }
    return number_format($bytes, $i ? 2 : 0) . ' ' . $u[$i];
}

/** ExamRepository::getProgressFormatted() */
function exam_progress_formatted(array $exam, array $progress): array
{
    $out = [];
    foreach (exam_checked_indexes($exam) as $index) {
        $idx = (int)$index['index'];
        if (!array_key_exists($idx, $progress)) continue;

        $current = (int)$progress[$idx];
        $require = (int)$index['require_value'];
        $unit    = EXAM_INDEXES[$idx]['unit'];

        switch ($idx) {
            case EXAM_INDEX_UPLOADED:
            case EXAM_INDEX_DOWNLOADED:
                $curText = exam_size((float)$current);
                $atomic  = $require * EXAM_GB;
                break;
            case EXAM_INDEX_SEED_TIME_AVERAGE:
                $curText = exam_num($current / 3600, 2) . " {$unit}";
                $atomic  = $require * 3600;
                break;
            default:
                $curText = exam_num($current);
                $atomic  = $require;
        }

        $out[] = [
            'index'         => $idx,
            'name'          => EXAM_INDEXES[$idx]['name'],
            'require_value' => $require,
            'require_text'  => trim(exam_num($require, 2) . " {$unit}"),
            'current_value' => $current,
            'current_text'  => $curText,
            'pct'           => $atomic > 0 ? (int)min(100, max(0, floor($current / $atomic * 100))) : 100,
            'passed'        => $current >= $atomic,
        ];
    }
    return $out;
}

// ── ЛС ───────────────────────────────────────────────────

function exam_notify(int $uid, string $subject, string $message): void
{
    // send_pm() из include/functions_pm.php. fromid = -1: отправитель - система,
    // а не текущий пользователь (иначе из админки ЛС ушло бы от имени стаффа).
    if (!function_exists('send_pm')) {
        require_once INC_PATH . '/functions_pm.php';
    }
    send_pm(['subject' => $subject, 'message' => $message, 'touid' => $uid], -1, true);
}

// ── Кроны ────────────────────────────────────────────────

/** ExamRepository::cronjonAssign() + fetchUserAndDoAssign(). Возвращает число выданных. */
function exam_cron_assign(): int
{
    global $db;
    $total = 0;

    foreach (exam_list_valid(1, EXAM_TYPE_EXAM) as $exam) {
        [$where, $params] = exam_filter_sql($exam['filters']);
        array_unshift($where, "u.enabled = 'yes'", "u.ustatus = 'confirmed'");

        // Ни разу не получал этот экзамен и сейчас ничего не сдаёт
        $where[] = 'NOT EXISTS (SELECT 1 FROM exam_users x WHERE x.uid = u.id AND x.exam_id = ?)';
        $params[] = (int)$exam['id'];
        $where[] = 'NOT EXISTS (SELECT 1 FROM exam_users y WHERE y.uid = u.id AND y.status = ?)';
        $params[] = EXAM_USER_STATUS_NORMAL;

        $begin = exam_begin_for_user($exam);
        $end   = exam_end_for_user($exam);
        $staff = exam_staff_groups();
        $minId = 0;

        while (true) {
            $q = $db->sql_query_prepared(
                'SELECT u.id, u.username, u.usergroup, u.added, u.enabled, u.ustatus, u.donor,
                        u.uploaded, u.downloaded, u.seedbonus, u.seed_points
                 FROM users u WHERE ' . implode(' AND ', $where) . ' AND u.id > ? ORDER BY u.id LIMIT 1000',
                array_merge($params, [$minId])
            );
            $batch = 0;
            while ($user = $db->fetch_array($q)) {
                $batch++;
                $minId = (int)$user['id'];
                if (isset($staff[(int)$user['usergroup']])) continue;
                exam_insert_user($exam, $user, $begin, $end);
                $total++;
            }
            if ($batch < 1000) break;
        }
    }
    return $total;
}

/**
 * SQL for the exam's target filters (groups, donor, registration date / days),
 * shared by the auto-assign cron and the coverage check, so both count the same way.
 * Returns [conditions[], params[]] on users u; account state is not included.
 */
function exam_filter_sql(array $f): array
{
    $where  = [];
    $params = [];

    if (!empty($f[EXAM_FILTER_USER_CLASS])) {
        $ids     = array_map('intval', $f[EXAM_FILTER_USER_CLASS]);
        $where[] = 'u.usergroup IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if (!empty($f[EXAM_FILTER_USER_DONATE]) && count($f[EXAM_FILTER_USER_DONATE]) === 1) {
        $where[]  = 'u.donor = ?';
        $params[] = $f[EXAM_FILTER_USER_DONATE][0] === 'yes' ? 'yes' : 'no';
    }
    $r = $f[EXAM_FILTER_USER_REGISTER_TIME_RANGE] ?? [];
    if (!empty($r[0])) { $where[] = 'u.added >= ?'; $params[] = exam_ts((string)$r[0]); }
    if (!empty($r[1])) { $where[] = 'u.added <= ?'; $params[] = exam_ts((string)$r[1]); }
    $r = $f[EXAM_FILTER_USER_REGISTER_DAYS_RANGE] ?? [];
    if (isset($r[0]) && $r[0] !== null && $r[0] !== '') { $where[] = 'u.added <= ?'; $params[] = TIMENOW - (int)$r[0] * 86400; }
    if (isset($r[1]) && $r[1] !== null && $r[1] !== '') { $where[] = 'u.added >= ?'; $params[] = TIMENOW - (int)$r[1] * 86400; }

    if (!$where) $where[] = '1 = 1';
    return [$where, $params];
}

/**
 * Who would get the exam right now, without assigning anything (our addition).
 * Same rules as exam_cron_assign(): staff never, only enabled + confirmed accounts,
 * not to users who ever had this exam, not to users busy with another exam or task.
 * $examId = 0 for an exam that is not saved yet.
 */
function exam_simulate_coverage(array $filters, int $examId = 0, int $sample = 20): array
{
    global $db;

    [$where, $params] = exam_filter_sql($filters);
    $staff = array_keys(exam_staff_groups());
    $notStaff = $staff ? 'u.usergroup NOT IN (' . implode(',', array_map('intval', $staff)) . ')' : '1 = 1';
    $isStaff  = $staff ? 'u.usergroup IN (' . implode(',', array_map('intval', $staff)) . ')' : '1 = 0';

    $active = "(u.enabled = 'yes' AND u.ustatus = 'confirmed')";
    $took   = 'EXISTS (SELECT 1 FROM exam_users x WHERE x.uid = u.id AND x.exam_id = ' . (int)$examId . ')';
    $busy   = 'EXISTS (SELECT 1 FROM exam_users y WHERE y.uid = u.id AND y.status = ' . EXAM_USER_STATUS_NORMAL . ')';
    $w      = implode(' AND ', $where);

    $r = $db->fetch_array($db->sql_query_prepared(
        "SELECT COUNT(*) AS matched,
                SUM({$isStaff}) AS staff,
                SUM(NOT {$isStaff} AND u.enabled <> 'yes') AS disabled,
                SUM(NOT {$isStaff} AND u.enabled = 'yes' AND u.ustatus <> 'confirmed') AS unconfirmed,
                SUM(NOT {$isStaff} AND {$active} AND {$took}) AS took,
                SUM(NOT {$isStaff} AND {$active} AND NOT {$took} AND {$busy}) AS busy,
                SUM(NOT {$isStaff} AND {$active} AND NOT {$took} AND NOT {$busy}) AS will_get
         FROM users u WHERE {$w}",
        $params
    )) ?: [];

    $out = [];
    foreach (['matched', 'staff', 'disabled', 'unconfirmed', 'took', 'busy', 'will_get'] as $k) {
        $out[$k] = (int)($r[$k] ?? 0);
    }

    $out['sample'] = [];
    if ($out['will_get'] > 0 && $sample > 0) {
        $q = $db->sql_query_prepared(
            "SELECT u.id, u.username, u.usergroup, u.displaygroup FROM users u
             WHERE {$w} AND {$notStaff} AND {$active} AND NOT {$took} AND NOT {$busy}
             ORDER BY u.id LIMIT " . (int)$sample,
            $params
        );
        while ($u = $db->fetch_array($q)) $out['sample'][] = $u;
    }
    return $out;
}

/**
 * ExamRepository::cronjobCheckout() - подведение итогов после окончания.
 * Возвращает число обработанных попыток.
 */
function exam_cron_checkout(bool $ignoreTimeRange = false): int
{
    global $db;

    $now   = exam_now();
    $where = 'eu.status = ?';
    $base  = [EXAM_USER_STATUS_NORMAL];
    if (!$ignoreTimeRange) {
        $where .= ' AND (
            (eu.`end` IS NOT NULL AND eu.`end` < ?)
         OR (e.`end` IS NOT NULL AND e.`end` < ?)
         OR (e.duration > 0 AND DATE_ADD(eu.created_at, INTERVAL e.duration DAY) < ?)
        )';
        array_push($base, $now, $now, $now);
    }

    $total = 0;
    $minId = 0;
    $staff = exam_staff_groups();
    // One log line per exam per run instead of a line per user
    $summary = [];
    $note    = static function (array $exam, string $bucket, array $user) use (&$summary): void {
        $id = (int)$exam['id'];
        $summary[$id] ??= ['exam' => $exam, 'passed' => [], 'failed' => [], 'disabled' => []];
        $summary[$id][$bucket][] = $user['username'] . ' (' . (int)$user['id'] . ')';
    };

    while (true) {
        $q = $db->sql_query_prepared(
            "SELECT eu.* FROM exam_users eu JOIN exams e ON e.id = eu.exam_id
             WHERE {$where} AND eu.id > ? ORDER BY eu.id LIMIT 1000",
            array_merge($base, [$minId])
        );
        $rows = [];
        while ($r = $db->fetch_array($q)) {
            $rows[] = $r;
        }
        if (!$rows) break;

        foreach ($rows as $eu) {
            $minId = (int)$eu['id'];
            $total++;
            $euId  = (int)$eu['id'];
            $uid   = (int)$eu['uid'];
            $exam  = exam_get((int)$eu['exam_id']);
            $user  = exam_fetch_user($uid);

            if (!$user || !$exam) {
                $db->sql_query_prepared('DELETE FROM exam_progress WHERE exam_user_id = ?', [$euId]);
                $db->sql_query_prepared('DELETE FROM exam_users WHERE id = ?', [$euId]);
                continue;
            }

            $res    = exam_update_progress($eu, $exam, $user);
            $isDone = $res !== null && $res['is_done'];
            $isTask = (int)$exam['type'] === EXAM_TYPE_TASK;
            $range  = sprintf('%s ~ %s',
                date('d.m.Y H:i', exam_user_begin($eu, $exam)), date('d.m.Y H:i', exam_user_end($eu, $exam)));

            $result = match (true) {
                $isDone                                    => EXAM_RESULT_PASSED,
                $isTask                                    => EXAM_RESULT_FAILED,
                isset($staff[(int)$user['usergroup']])     => EXAM_RESULT_FAILED,
                default                                    => EXAM_RESULT_DISABLED,
            };

            // Закрываем попытку первым делом: если что-то ниже упадёт, повтора не будет
            $db->sql_query_prepared(
                'UPDATE exam_users SET status = ?, result = ?, updated_at = ? WHERE id = ? AND status = ?',
                [EXAM_USER_STATUS_FINISHED, $result, exam_now(), $euId, EXAM_USER_STATUS_NORMAL]
            );
            if ((int)$db->affected_rows() !== 1) continue;
            $db->sql_query_prepared('DELETE FROM exam_progress WHERE exam_user_id = ?', [$euId]);

            if ($isDone) {
                if ($isTask) {
                    $reward = (int)$exam['success_reward_bonus'];
                    if ($reward > 0) {
                        $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?', [$reward, $uid]);
                        bonus_log($uid, $reward, 'exam', "Task completed: {$exam['name']}", (int)$exam['id']);
                    }
                    $note($exam, 'passed', $user);
                    exam_notify($uid, 'Task completed!',
                        "Congratulations! You completed the task [b]{$exam['name']}[/b] in time ({$range}) and received [b]" . exam_num($reward) . "[/b] bonus points.");
                } else {
                    // Our addition (not in NexusPHP): optional bonus reward for passing an exam
                    $reward = (int)$exam['success_reward_bonus'];
                    if ($reward > 0) {
                        $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus + ? WHERE id = ?', [$reward, $uid]);
                        bonus_log($uid, $reward, 'exam', "Exam passed: {$exam['name']}", (int)$exam['id']);
                    }
                    $note($exam, 'passed', $user);
                    exam_notify($uid, 'Exam passed!',
                        "Congratulations! You passed the exam [b]{$exam['name']}[/b] in time ({$range})."
                        . ($reward > 0 ? ' You received [b]' . exam_num($reward) . '[/b] bonus points.' : ''));
                    // Повторяющийся экзамен - сразу следующий цикл, если пользователь всё ещё подходит
                    if (!empty($exam['recurring']) && exam_match_user($exam, $user)) {
                        exam_insert_user($exam, $user, exam_begin_for_user($exam), exam_end_for_user($exam));
                    }
                }
                continue;
            }

            if ($isTask) {
                $deduct = (int)$exam['fail_deduct_bonus'];
                if ($deduct > 0) {
                    $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?', [$deduct, $uid]);
                    bonus_log($uid, -$deduct, 'exam', "Task failed: {$exam['name']}", (int)$exam['id']);
                }
                $note($exam, 'failed', $user);
                exam_notify($uid, 'Task not completed!',
                    "You did not complete the task [b]{$exam['name']}[/b] in time ({$range}). [b]" . exam_num($deduct) . "[/b] bonus points have been deducted.");
                continue;
            }

            // Экзамен не сдан - бан. Стафф не трогаем.
            if (!isset($staff[(int)$user['usergroup']])) {
                $modNote = gmdate('Y-m-d') . " - Exam '{$exam['name']}' not passed ({$range}), disabled by System.\n";
                $db->sql_query_prepared(
                    "UPDATE users SET enabled = 'no', modcomment = CONCAT(?, modcomment) WHERE id = ?",
                    [$modNote, $uid]
                );
                $note($exam, 'disabled', $user);
            } else {
                $note($exam, 'failed', $user);
            }
            exam_notify($uid, 'Exam not passed, account disabled!',
                "You did not pass the exam [b]{$exam['name']}[/b] in time ({$range}), and your account has been disabled.");
        }
    }

    if (function_exists('savelog')) {
        foreach ($summary as $s) {
            savelog(exam_checkout_log_line($s));
        }
    }
    // Pass-rate alerts only for exams that got new results in this run
    foreach ($summary as $s) {
        exam_check_anomaly($s['exam']);
    }
    return $total;
}

/** 'low' / 'high' if the pass rate is out of range, null if fine or too few results to tell. */
function exam_anomaly(?array $stats): ?string
{
    if (!$stats || $stats['finished'] < EXAM_ANOMALY_MIN_FINISHED || $stats['pass_rate'] === null) return null;
    if ($stats['pass_rate'] < EXAM_ANOMALY_LOW_PERCENT)  return 'low';
    if ($stats['pass_rate'] > EXAM_ANOMALY_HIGH_PERCENT) return 'high';
    return null;
}

/** Hint for staff, shown in the admin list and in the PM. */
function exam_anomaly_hint(string $anomaly, bool $isTask): string
{
    return $anomaly === 'low'
        ? 'Very few pass. Check the requirements (too hard for the time?), the target filters, and that stats are counted (announces).'
          . ($isTask ? '' : ' Every fail here disables an account.')
        : 'Almost everyone passes. The requirements may be too easy to check anything.';
}

/**
 * Remembers the state in exams.anomaly and sends admins one PM when the exam goes
 * out of range (again only after it returned to normal and left it once more).
 */
function exam_check_anomaly(array $exam): void
{
    global $db, $BASEURL;

    $id      = (int)$exam['id'];
    $stats   = exam_stats($id)[$id] ?? null;
    $new     = exam_anomaly($stats);
    $old     = isset($exam['anomaly']) && $exam['anomaly'] !== '' ? (string)$exam['anomaly'] : null;
    if ($new === $old) return;

    $db->sql_query_prepared('UPDATE exams SET anomaly = ? WHERE id = ?', [$new, $id]);
    if ($new === null) return;

    $isTask = (int)$exam['type'] === EXAM_TYPE_TASK;
    $kind   = $isTask ? 'task' : 'exam';
    $link   = rtrim((string)$BASEURL, '/') . EXAM_ADMIN_URL . '&do=users&exam_id=' . $id;
    $msg    = "The {$kind} [b]{$exam['name']}[/b] (#{$id}) has a " . ($new === 'low' ? 'very low' : 'very high')
            . ' pass rate: [b]' . exam_num((float)$stats['pass_rate'], 1) . '%[/b] ('
            . exam_num($stats['passed']) . ' of ' . exam_num($stats['finished']) . ' finished'
            . ($stats['disabled'] > 0 ? ', ' . exam_num($stats['disabled']) . ' accounts disabled' : '') . ").\n\n"
            . exam_anomaly_hint($new, $isTask) . "\n\n[url={$link}]Open participants[/url]";

    foreach (exam_admin_uids() as $uid) {
        exam_notify($uid, ($new === 'low' ? 'Low' : 'High') . " pass rate: {$exam['name']}", $msg);
    }
    if (function_exists('savelog')) {
        savelog("[exams] {$kind} #{$id} pass rate " . exam_num((float)$stats['pass_rate'], 1) . "% ({$new}), admins notified");
    }
}

/** Admins (groups with access to the settings panel), enabled accounts only. */
function exam_admin_uids(): array
{
    global $db;
    $uids = [];
    $q = $db->sql_query_prepared(
        "SELECT u.id FROM users u JOIN usergroups g ON g.gid = u.usergroup
         WHERE CAST(g.cansettingspanel AS CHAR) IN ('1','yes') AND u.enabled = 'yes'",
        []
    );
    while ($r = $db->fetch_array($q)) $uids[] = (int)$r['id'];
    return $uids;
}

/**
 * Summary line for one exam: who passed (and the reward), who failed, who was disabled.
 * Long name lists are cut so the log row stays readable.
 */
function exam_checkout_log_line(array $s): string
{
    $exam   = $s['exam'];
    $isTask = (int)$exam['type'] === EXAM_TYPE_TASK;
    $list   = static function (array $names): string {
        $max  = 30;
        $more = count($names) - $max;
        return implode(', ', array_slice($names, 0, $max)) . ($more > 0 ? " and {$more} more" : '');
    };

    $parts = [];
    if ($s['passed']) {
        $reward  = (int)$exam['success_reward_bonus'];
        $parts[] = ($isTask ? 'completed' : 'passed') . ' ' . count($s['passed'])
                 . ($reward > 0 ? ' (+' . exam_num($reward) . ' BP each)' : '') . ': ' . $list($s['passed']);
    }
    if ($s['failed']) {
        $deduct  = $isTask ? (int)$exam['fail_deduct_bonus'] : 0;
        $parts[] = 'failed ' . count($s['failed'])
                 . ($deduct > 0 ? ' (-' . exam_num($deduct) . ' BP each)' : ($isTask ? '' : ' (staff, not disabled)'))
                 . ': ' . $list($s['failed']);
    }
    if ($s['disabled']) {
        $parts[] = 'disabled ' . count($s['disabled']) . ': ' . $list($s['disabled']);
    }

    return sprintf('[exams] %s #%d "%s" checked out - %s',
        $isTask ? 'task' : 'exam', (int)$exam['id'], (string)$exam['name'], implode('; ', $parts));
}

/**
 * Results of finished attempts, per exam (key = exam_id) or all together (key 0).
 * avg_seconds: average time from start to meeting all requirements, over passed
 * attempts that have done_at; accurate to the progress refresh (5 min on view, hourly cron).
 */
function exam_stats(?int $examId = null): array
{
    global $db;

    $q = $db->sql_query_prepared(
        "SELECT exam_id,
                COUNT(*)                         AS finished,
                SUM(result = 'passed')           AS passed,
                SUM(result = 'failed')           AS failed,
                SUM(result = 'disabled')         AS disabled,
                SUM(result = 'abandoned')        AS abandoned,
                SUM(done_at IS NOT NULL AND result = 'passed') AS timed,
                SUM(IF(done_at IS NOT NULL AND result = 'passed',
                       GREATEST(0, TIMESTAMPDIFF(SECOND, GREATEST(COALESCE(`begin`, created_at), created_at), done_at)), 0)) AS timed_sum
         FROM exam_users
         WHERE status = ?" . ($examId ? ' AND exam_id = ?' : '') . '
         GROUP BY exam_id',
        $examId ? [EXAM_USER_STATUS_FINISHED, $examId] : [EXAM_USER_STATUS_FINISHED]
    );

    $empty = ['finished' => 0, 'passed' => 0, 'failed' => 0, 'disabled' => 0, 'abandoned' => 0, 'timed' => 0, 'timed_sum' => 0];
    $out   = [0 => $empty];
    while ($r = $db->fetch_array($q)) {
        $row = [];
        foreach ($empty as $k => $_) {
            $row[$k] = (int)$r[$k];
            $out[0][$k] += $row[$k];
        }
        $out[(int)$r['exam_id']] = $row;
    }
    foreach ($out as &$s) {
        $s['pass_rate']   = $s['finished'] > 0 ? round($s['passed'] * 100 / $s['finished'], 1) : null;
        $s['avg_seconds'] = $s['timed'] > 0 ? intdiv($s['timed_sum'], $s['timed']) : null;
    }
    unset($s);
    return $out;
}

/** Penalty for abandoning a task right now (0 if its requirements are already met). */
function exam_task_abandon_penalty(array $exam, bool $isDone): int
{
    if ($isDone) return 0;
    return (int)round((int)$exam['fail_deduct_bonus'] * EXAM_TASK_ABANDON_PENALTY_PERCENT / 100);
}

/**
 * The user gives up their task (our addition, not in NexusPHP).
 * Attempt is closed as abandoned, part of the fail penalty is taken (see
 * EXAM_TASK_ABANDON_PENALTY_PERCENT; nothing if requirements are already met,
 * but then the reward is lost too), and the user can claim another task at once.
 * Exams can't be abandoned. Returns the deducted amount.
 */
function exam_task_abandon(int $uid, int $examUserId): int
{
    global $db;

    $eu = exam_user_get($examUserId);
    if (!$eu || (int)$eu['uid'] !== $uid || (int)$eu['status'] !== EXAM_USER_STATUS_NORMAL) {
        throw new ExamException('You have no such task in progress.');
    }
    $exam = exam_get((int)$eu['exam_id']);
    if (!$exam || (int)$exam['type'] !== EXAM_TYPE_TASK) {
        throw new ExamException('Only tasks can be abandoned. An exam has to be finished.');
    }
    // After the end it's for the checkout cron to decide, otherwise a failed task
    // could be swapped for the smaller abandon penalty in the last minutes
    if (exam_user_end($eu, $exam) <= TIMENOW) {
        throw new ExamException('Time is up for this task, results are being counted.');
    }

    // Fresh progress so "already met" is decided on current numbers
    $isDone = (bool)(exam_update_progress($eu, $exam)['is_done'] ?? false);
    $deduct = exam_task_abandon_penalty($exam, $isDone);

    // Close first; only the request that actually closed it deducts (double click / two tabs)
    $db->sql_query_prepared(
        'UPDATE exam_users SET status = ?, result = ?, updated_at = ? WHERE id = ? AND status = ?',
        [EXAM_USER_STATUS_FINISHED, EXAM_RESULT_ABANDONED, exam_now(), (int)$eu['id'], EXAM_USER_STATUS_NORMAL]
    );
    if ((int)$db->affected_rows() !== 1) {
        throw new ExamException('This task is already finished.');
    }
    $db->sql_query_prepared('DELETE FROM exam_progress WHERE exam_user_id = ?', [(int)$eu['id']]);

    if ($deduct > 0) {
        $db->sql_query_prepared('UPDATE users SET seedbonus = seedbonus - ? WHERE id = ?', [$deduct, $uid]);
        bonus_log($uid, -$deduct, 'exam', "Task abandoned: {$exam['name']}", (int)$exam['id']);
    }

    exam_notify($uid, 'Task abandoned',
        "You abandoned the task [b]{$exam['name']}[/b]."
        . ($deduct > 0 ? ' [b]' . exam_num($deduct) . '[/b] bonus points have been deducted.' : ' No bonus points were deducted.')
        . ' You can claim another task now.');

    return $deduct;
}

/**
 * Task leaderboard (our addition): who completed the most tasks in [$from, $to).
 * Counts finished attempts with result = passed, by the time they were checked out.
 * bp = sum of the tasks' current rewards (if a reward was edited later, the sum follows it).
 * Ties: more bonus first, then whoever got there first.
 */
function exam_task_leaderboard(int $from, int $to): array
{
    global $db;

    $q = $db->sql_query_prepared(
        "SELECT eu.uid, u.username, u.usergroup, u.displaygroup,
                COUNT(*)                    AS n,
                SUM(e.success_reward_bonus) AS bp,
                MAX(eu.updated_at)          AS last_at
         FROM exam_users eu
         JOIN exams e ON e.id = eu.exam_id
         JOIN users u ON u.id = eu.uid
         WHERE e.type = ? AND eu.status = ? AND eu.result = ?
           AND eu.updated_at >= ? AND eu.updated_at < ?
         GROUP BY eu.uid, u.username, u.usergroup, u.displaygroup
         ORDER BY n DESC, bp DESC, last_at ASC",
        [EXAM_TYPE_TASK, EXAM_USER_STATUS_FINISHED, EXAM_RESULT_PASSED, exam_dt($from), exam_dt($to)]
    );

    $rows = [];
    $rank = 0;
    while ($r = $db->fetch_array($q)) {
        $rows[] = [
            'rank'         => ++$rank,
            'uid'          => (int)$r['uid'],
            'username'     => (string)$r['username'],
            'usergroup'    => (int)$r['usergroup'],
            'displaygroup' => (int)$r['displaygroup'],
            'n'            => (int)$r['n'],
            'bp'           => (int)$r['bp'],
        ];
    }
    return $rows;
}

/** ExamRepository::updateProgressBulk() */
function exam_cron_update_progress(): array
{
    global $db;
    $total = $success = 0;
    $minId = 0;
    while (true) {
        $q = $db->sql_query_prepared(
            'SELECT * FROM exam_users WHERE status = ? AND is_done = 0 AND id > ? ORDER BY id LIMIT 1000',
            [EXAM_USER_STATUS_NORMAL, $minId]
        );
        $n = 0;
        while ($eu = $db->fetch_array($q)) {
            $n++;
            $total++;
            $minId = (int)$eu['id'];
            if (exam_update_progress($eu) !== null) $success++;
        }
        if ($n < 1000) break;
    }
    return compact('total', 'success');
}

// ── Плашка для пользователя (Nexus\Exam\Exam::getCurrent) ─

const EXAM_BG = [
    'blue'   => 'primary', 'green' => 'success', 'red' => 'danger',
    'orange' => 'warning', 'grey'  => 'secondary', 'cyan' => 'info',
];

/**
 * Текущий экзамен / задание пользователя. Вывести после stdhead() на всех страницах,
 * как msgalert() в NexusPHP:  echo exam_render_current((int)$CURUSER['id']);
 */
function exam_render_current(int $uid): string
{
    global $db, $BASEURL;

    $eu = $db->fetch_array($db->sql_query_prepared(
        'SELECT * FROM exam_users WHERE uid = ? AND status = ? ORDER BY exam_id DESC LIMIT 1',
        [$uid, EXAM_USER_STATUS_NORMAL]
    ));
    if (!$eu) return '';
    $exam = exam_get((int)$eu['exam_id']);
    if (!$exam) return '';

    // NexusPHP пересчитывает на каждом показе; здесь не чаще раза в 5 минут
    $formatted = null;
    if (TIMENOW - exam_ts((string)$eu['updated_at']) >= EXAM_PROGRESS_VIEW_THROTTLE) {
        $res = exam_update_progress($eu, $exam);
        $formatted = $res['formatted'] ?? null;
    }
    $formatted ??= exam_progress_formatted($exam, json_decode((string)$eu['progress'], true) ?: []);

    $e      = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $isTask = (int)$exam['type'] === EXAM_TYPE_TASK;
    $tone   = EXAM_BG[(string)$exam['background_color']] ?? 'primary';
    $end    = exam_user_end($eu, $exam);
    $left   = max(0, $end - TIMENOW);
    $leftTx = $left >= 86400 ? intdiv($left, 86400) . 'd ' . intdiv($left % 86400, 3600) . 'h' : intdiv($left, 3600) . 'h ' . intdiv($left % 3600, 60) . 'm';

    $h = '<link rel="stylesheet" href="' . $e((string)$BASEURL) . '/include/templates/default/style/exam.css?ver=1">'
       . '<div class="exb exb-' . $tone . '">'
       . '<div class="exb-head"><i class="fa-solid ' . ($isTask ? 'fa-list-check' : 'fa-graduation-cap') . '"></i><div>'
       . '<strong>' . exam_type_text($exam) . ': ' . $e((string)$exam['name']) . '</strong>'
       . '<span>' . date('d.m.Y H:i', exam_user_begin($eu, $exam)) . ' ~ ' . date('d.m.Y H:i', $end) . ', ' . $leftTx . ' left' . '</span>'
       . '</div>' . ($isTask ? '<a class="exb-link" href="' . $e((string)$BASEURL) . '/task.php">All tasks</a>' : '') . '</div>';

    foreach ($formatted as $f) {
        $h .= '<div class="exb-item' . ($f['passed'] ? ' is-done' : '') . '">'
            . '<div class="exb-line"><span><i class="fa-solid ' . ($f['passed'] ? 'fa-circle-check' : 'fa-circle') . '"></i>' . $e($f['name']) . '</span>'
            . '<span>' . $e($f['current_text']) . ' / ' . $e($f['require_text']) . '</span></div>'
            . '<div class="exb-track"><div class="exb-fill" style="width:' . $f['pct'] . '%"></div></div></div>';
    }

    if (trim((string)$exam['description']) !== '') {
        $h .= '<div class="exb-desc">' . nl2br($e((string)$exam['description'])) . '</div>';
    }
    return $h . '</div>';
}